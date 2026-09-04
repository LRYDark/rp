<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Garde d'idempotence de la file d'attente des signatures hors-ligne.
 *
 * Une signature recueillie sans réseau est mise en file dans le NAVIGATEUR du
 * technicien, puis rejouée telle quelle dès que la connexion revient. Le rejeu
 * est la requête d'origine, à l'identique : rien n'est réimplémenté ici.
 *
 * Ce qu'il manque à un rejeu, c'est de savoir si la première tentative avait
 * abouti. Un délai d'attente dépassé côté téléphone NE PROUVE PAS que le
 * serveur n'a rien fait : la requête a pu arriver entière et le PDF partir,
 * seule la réponse s'étant perdue. Sans cette garde, le rejeu produirait un
 * SECOND rapport signé et un SECOND mail au client.
 *
 * D'où l'identifiant `sign_uid`, forgé par le navigateur AVANT le premier envoi
 * et rejoué à l'identique : c'est lui, et non l'horodatage ou le contenu, qui
 * dit « c'est la même signature ».
 *
 * La table appartient au plugin RP et ne parle QUE des documents produits par
 * RP. Le plugin Gestion tient la sienne, pour les siens. Aucun des deux
 * n'interroge la table de l'autre : c'est ce qui leur permet d'être
 * désinstallés séparément sans que la file de l'autre cesse de fonctionner.
 */
class PluginRpOfflineQueue {

   const TABLE = 'glpi_plugin_rp_offline_queue';

   /**
    * Au-delà de ce délai, une tentative encore marquée « en cours » est
    * considérée comme morte et redevient rejouable.
    *
    * Sans ce garde-fou, un PHP tué en pleine génération (mémoire, redémarrage
    * du serveur) laisserait la ligne à « en cours » POUR TOUJOURS : la
    * signature ne repartirait jamais, et rien ne le dirait.
    */
   const STALE_SECONDS = 600;

   /**
    * Identifiant réclamé par la requête HTTP en cours.
    *
    * Les traitements s'INCLUENT les uns les autres (`traitement_combined.php`
    * inclut `cripdf.form.php` puis `traitement.php`) : sans ce témoin, une
    * signature combinée ouvrirait plusieurs lignes pour un seul envoi. Seul le
    * point d'entrée réclame ; les fichiers inclus se taisent.
    */
   private static ?string $claimed = null;

   /**
    * Table présente ? Sinon on la crée, ici et maintenant.
    *
    * Pourquoi pas une migration classique ?
    *
    * Parce que GLPI ne propose « Mettre à jour » que si la version du plugin a
    * changé, et que celle-ci n'a PAS bougé. Une migration accrochée à un numéro
    * de version ne serait donc jamais jouée : la table n'existerait sur aucune
    * installation déjà en place, et la garde d'idempotence — le seul rempart
    * contre le doublon de rapport et de mail — serait morte-née.
    *
    * La création se fait donc à la demande, une fois par requête au plus. C'est
    * le même parti que prend déjà `traitement.php` du plugin Gestion, qui
    * s'assure de ses colonnes optionnelles à chaque passage.
    */
   static function isAvailable(): bool {
      global $DB;

      // Une seule vérification par requête : au-delà, on interrogerait le
      // schéma à chaque appel de `find()`, pour une réponse invariable.
      static $ready = null;
      if ($ready !== null) {
         return $ready;
      }

      if ($DB->tableExists(self::TABLE)) {
         $ready = true;
         return true;
      }

      self::createTable();
      $ready = $DB->tableExists(self::TABLE);
      return $ready;
   }

   /**
    * Création de la table, appelée à l'installation comme à la demande.
    *
    * La clé UNIQUE sur `uid` n'est pas un confort d'indexation : c'est ELLE qui
    * garantit l'idempotence quand deux rejeux partent en même temps depuis deux
    * onglets. Le second se voit refuser l'insertion et relit la ligne du
    * premier au lieu de produire un doublon.
    */
   static function createTable(): void {
      global $DB;

      if ($DB->tableExists(self::TABLE)) {
         return;
      }

      $query = "CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
         `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
         `uid` VARCHAR(64) NOT NULL,
         `tickets_id` INT UNSIGNED NOT NULL DEFAULT 0,
         `users_id` INT UNSIGNED NOT NULL DEFAULT 0,
         `state` VARCHAR(16) NOT NULL DEFAULT 'running',
         `captured_at` TIMESTAMP NULL DEFAULT NULL,
         `date_start` TIMESTAMP NULL DEFAULT NULL,
         `date_end` TIMESTAMP NULL DEFAULT NULL,
         `documents_id` INT UNSIGNED NOT NULL DEFAULT 0,
         `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
         `message` TEXT NULL,
         PRIMARY KEY (`id`),
         UNIQUE KEY `uid` (`uid`),
         KEY `tickets_id` (`tickets_id`)
         ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

      /*
       * Échec NON bloquant, contrairement au `or die()` de l'installation.
       *
       * On est ici en plein parcours de signature : un utilisateur de base de
       * données sans droit `CREATE` doit coûter la garde, pas le rapport du
       * technicien. `claim()` le voit et poursuit sans garde, en le journalisant.
       *
       * `try/catch` et non un test de retour : `DBmysql::doQuery()` LÈVE une
       * `RuntimeException` sur erreur SQL, elle ne renvoie jamais `false`. Un
       * simple `if (!$DB->doQuery(...))` n'aurait donc rien intercepté du tout,
       * et l'exception aurait coupé la signature — précisément ce qu'on veut
       * éviter.
       */
      try {
         $DB->doQuery($query);
      } catch (\Throwable $e) {
         Toolbox::logInFile(
            'plugin-rp',
            "File hors-ligne : création de " . self::TABLE . " impossible — " . $e->getMessage() . "\n"
         );
      }
   }

   /**
    * Taille maximale d'un POST accepté par ce serveur, en octets.
    *
    * La file s'en sert pour REFUSER de mettre en attente une signature que le
    * serveur rejetterait de toute façon : un élément condamné à échouer
    * indéfiniment est pire qu'un échec annoncé tout de suite, tant que le
    * technicien peut encore retirer une photo.
    *
    * 0 quand la limite est absente ou illimitée.
    */
   static function postMaxBytes(): int {
      $raw = trim((string)ini_get('post_max_size'));
      if ($raw === '' || $raw === '0' || $raw === '-1') {
         return 0;
      }
      $n = (float)$raw;
      switch (strtolower(substr($raw, -1))) {
         case 'g':
            $n *= 1024;
            // pas de break : cascade volontaire g -> m -> k
         case 'm':
            $n *= 1024;
            // pas de break
         case 'k':
            $n *= 1024;
      }
      return (int)$n;
   }

   /**
    * Déclaration lue par le module de file, côté navigateur.
    *
    * Sa PRÉSENCE est le signal : un élément visant un plugin qui ne se déclare
    * plus est marqué orphelin plutôt que rejoué dans le vide. Aucune requête
    * au serveur n'est nécessaire pour le savoir.
    */
   static function headerTag(): array {
      return [
         'tag'        => 'meta',
         'properties' => [
            'name'    => 'rp:outbox',
            'content' => json_encode([
               'webdir'   => PLUGIN_RP_WEBDIR,
               'post_max' => self::postMaxBytes(),
            ]),
         ],
      ];
   }

   /**
    * Normalise un identifiant de signature.
    *
    * Il vient du navigateur : on n'en accepte que la forme attendue (UUID ou
    * chaîne hexadécimale), jamais tel quel. Une valeur qui ne s'y conforme pas
    * est traitée comme absente — le traitement se déroule alors normalement,
    * simplement sans garde.
    */
   static function normalizeUid(?string $uid): string {
      $uid = strtolower(trim((string)$uid));
      if ($uid === '' || strlen($uid) > 64) {
         return '';
      }
      return preg_match('/^[a-f0-9-]{16,64}$/', $uid) === 1 ? $uid : '';
   }

   /**
    * Écriture d'état, qui ne peut jamais faire tomber la requête en cours.
    *
    * `DBmysql::doQuery()` LÈVE une `RuntimeException` sur erreur SQL — elle ne
    * renvoie pas `false`. Or ces écritures ont lieu à des moments où une
    * exception ferait plus de mal que le problème qu'elle signale : au milieu
    * d'une génération de rapport, et jusque dans la fonction d'arrêt de
    * `hold()`, où elle passerait après la réponse sans rien réparer.
    *
    * En cas d'échec, l'état reste tel quel : la ligne « en cours » expirera
    * d'elle-même au bout de STALE_SECONDS et la file pourra rejouer. On perd la
    * précision, jamais la signature.
    */
   private static function writeState(string $uid, array $data): void {
      global $DB;
      try {
         $DB->update(self::TABLE, $data, ['uid' => $uid]);
      } catch (\Throwable $e) {
         Toolbox::logInFile(
            'plugin-rp',
            "File hors-ligne : état non écrit pour $uid — " . $e->getMessage() . "\n"
         );
      }
   }

   static function find(string $uid): ?array {
      global $DB;

      if ($uid === '' || !self::isAvailable()) {
         return null;
      }

      $row = $DB->request([
         'FROM'  => self::TABLE,
         'WHERE' => ['uid' => $uid],
         'LIMIT' => 1,
      ])->current();

      return $row ? (array)$row : null;
   }

   /**
    * Réserve le traitement d'une signature, ou refuse de le refaire.
    *
    * @return array{go:bool, state:string, row:?array}
    *         `go` à faux : le traitement doit s'arrêter là. `state` dit
    *         pourquoi — « done » (déjà produit) ou « running » (une tentative
    *         est encore en vol).
    */
   static function claim(string $uid, int $tickets_id = 0, string $captured_at = ''): array {
      global $DB;

      $uid = self::normalizeUid($uid);

      // Pas d'identifiant : parcours ordinaire, aucune garde à poser.
      if ($uid === '') {
         return ['go' => true, 'state' => 'nouid', 'row' => null];
      }

      // Déjà réclamé par cette même requête (fichier inclus) : on laisse passer
      // sans rien réécrire.
      if (self::$claimed === $uid) {
         return ['go' => true, 'state' => 'nested', 'row' => null];
      }

      if (!self::isAvailable()) {
         /*
          * Table introuvable ET impossible à créer — en pratique un utilisateur
          * de base sans droit `CREATE`. On NE BLOQUE PAS : mieux vaut un rapport
          * produit sans garde qu'une signature perdue. Le doublon redevient
          * possible, et c'est justement pour cela qu'on le trace.
          */
         Toolbox::logInFile(
            'plugin-rp',
            "File hors-ligne : table indisponible, signature $uid traitée sans garde d'idempotence\n"
         );
         return ['go' => true, 'state' => 'nogard', 'row' => null];
      }

      $now  = date('Y-m-d H:i:s');
      $me   = (int)Session::getLoginUserID();
      $seen = self::find($uid);

      if ($seen === null) {
         /*
          * `try/catch` et non un test de retour.
          *
          * `DBmysql::insert()` renvoie `true` sans condition, et c'est
          * `doQuery()` qui LÈVE une `RuntimeException` en cas d'erreur SQL. Un
          * `if (!$DB->insert(...))` serait donc du code mort, et la violation de
          * clé unique — le cas que cette garde existe précisément pour traiter —
          * remonterait en erreur fatale au milieu d'une signature.
          */
         $inserted = false;
         try {
            $DB->insert(self::TABLE, [
               'uid'         => $uid,
               'tickets_id'  => $tickets_id,
               'users_id'    => $me,
               'state'       => 'running',
               'captured_at' => self::normalizeCapturedAt($captured_at),
               'date_start'  => $now,
               'attempts'    => 1,
            ]);
            $inserted = true;
         } catch (\Throwable $e) {
            // Très probablement la clé unique : deux rejeux partis en même
            // temps depuis deux onglets, le premier a gagné la course.
            $inserted = false;
         }

         if ($inserted) {
            self::hold($uid);
            return ['go' => true, 'state' => 'new', 'row' => null];
         }

         // Si la ligne est bien là, c'était la course : on applique la règle
         // ci-dessous, celle de « une tentative est déjà en vol ».
         $seen = self::find($uid);
         if ($seen === null) {
            // Échec pour une autre raison : on n'a pas de garde, on le dit.
            Toolbox::logInFile(
               'plugin-rp',
               "File hors-ligne : signature $uid non enregistrée, traitée sans garde\n"
            );
            return ['go' => true, 'state' => 'nogard', 'row' => null];
         }
      }

      $state = (string)($seen['state'] ?? '');

      if ($state === 'done') {
         return ['go' => false, 'state' => 'done', 'row' => $seen];
      }

      if ($state === 'running') {
         $started = strtotime((string)($seen['date_start'] ?? '')) ?: 0;
         if ($started > 0 && (time() - $started) < self::STALE_SECONDS) {
            // Une tentative est encore en vol : on ne travaille pas en double.
            return ['go' => false, 'state' => 'running', 'row' => $seen];
         }
      }

      // Échec précédent, ou tentative morte : on reprend la main.
      self::writeState($uid, [
         'state'      => 'running',
         'users_id'   => $me,
         'date_start' => $now,
         'date_end'   => null,
         'attempts'   => (int)($seen['attempts'] ?? 0) + 1,
      ]);

      self::hold($uid);
      return ['go' => true, 'state' => 'retry', 'row' => $seen];
   }

   /**
    * Prend possession de la requête pour la durée du traitement.
    *
    * `ignore_user_abort` est le point CENTRAL de tout le dispositif.
    *
    * Le générateur envoie le PDF au navigateur bien AVANT d'enregistrer le
    * fichier, d'écrire en base et d'envoyer le mail. Si le téléphone raccroche
    * — et sur un réseau qui passe mal, il raccroche — PHP s'interrompt à la
    * prochaine écriture : document produit, base incomplète, mail jamais parti.
    * Le rejeu créerait alors un second document.
    *
    * En ignorant la coupure, le serveur termine son travail quoi qu'il arrive.
    * Le cas « ça a expiré côté technicien mais c'est bien passé » devient le cas
    * NORMAL, que la file résout d'elle-même en interrogeant l'état avant de
    * rejouer, au lieu d'être une corruption silencieuse.
    */
   private static function hold(string $uid): void {
      self::$claimed = $uid;

      @ignore_user_abort(true);

      /*
       * Filet : si le script meurt sans passer par `complete()`, la ligne
       * resterait « en cours » et bloquerait le rejeu pendant STALE_SECONDS.
       * On la marque échouée tout de suite, pour que la file reparte sans
       * attendre.
       */
      register_shutdown_function(static function () use ($uid) {
         if (self::$claimed !== $uid) {
            return;
         }
         $error = error_get_last();
         self::fail(
            $uid,
            $error !== null
               ? ('Interrompu : ' . (string)($error['message'] ?? 'erreur fatale'))
               : 'Interrompu avant la fin du traitement'
         );
      });
   }

   /**
    * Le document est produit et enregistré : la signature ne repartira plus.
    */
   static function complete(string $uid, int $documents_id = 0, string $message = ''): void {
      global $DB;

      $uid = self::normalizeUid($uid);
      if ($uid === '' || !self::isAvailable()) {
         return;
      }

      self::writeState($uid, [
         'state'        => 'done',
         'date_end'     => date('Y-m-d H:i:s'),
         'documents_id' => $documents_id,
         'message'      => ($message !== '' ? mb_substr($message, 0, 800) : null),
      ]);

      // Le filet de `hold()` n'a plus lieu d'être.
      if (self::$claimed === $uid) {
         self::$claimed = null;
      }
   }

   static function fail(string $uid, string $message = ''): void {
      global $DB;

      $uid = self::normalizeUid($uid);
      if ($uid === '' || !self::isAvailable()) {
         return;
      }

      self::writeState($uid, [
         'state'    => 'failed',
         'date_end' => date('Y-m-d H:i:s'),
         'message'  => ($message !== '' ? mb_substr($message, 0, 800) : null),
      ]);

      if (self::$claimed === $uid) {
         self::$claimed = null;
      }
   }

   /**
    * Identifiant réclamé par la requête en cours, pour que le générateur sache
    * qu'il travaille sur une signature différée.
    */
   static function currentUid(): string {
      return (string)self::$claimed;
   }

   /**
    * Fuseau des dates de capture, EXPLICITE aux deux bouts.
    *
    * La garde s'exécute en tête de `cripdf.form.php`, AVANT son
    * `date_default_timezone_set('Europe/Paris')` : le fuseau ambiant y est
    * celui du noyau GLPI — UTC. La capture était donc stockée en UTC puis
    * relue comme de l'heure locale : le PDF affichait « 08:31 » pour une
    * signature de 10:31. C'est l'écart de 2 h déjà rencontré sur d'autres
    * dates de ce plugin.
    *
    * Le fuseau est celui que le plugin impose partout ailleurs, et il est
    * nommé à l'écriture COMME à la lecture : la valeur ne dépend plus de
    * l'endroit du fichier où on se trouve quand on la manipule.
    */
   private static function timezone(): \DateTimeZone {
      return new \DateTimeZone('Europe/Paris');
   }

   /**
    * Mention à imprimer sous la signature quand elle a été recueillie
    * NETTEMENT avant d'être transmise.
    *
    * Le générateur horodate tout au moment où il travaille : un rapport rejoué
    * à 18 h porte 18 h, alors que le client a signé à 14 h 32. Plutôt que de
    * réécrire toutes les dates du document — le générateur en pose des dizaines,
    * et ce plugin a déjà un passif d'horloges qui divergent — on ajoute ce que
    * la date d'édition ne dit pas : QUAND le client a signé.
    *
    * Vide pour un envoi immédiat, qui est le cas courant : la date d'édition y
    * est déjà celle de la signature, et la répéter n'apprendrait rien.
    *
    * @param int $min_gap seuil en secondes sous lequel on considère que la
    *                     signature et l'édition sont le même instant.
    */
   static function deferredLabel(string $uid, int $min_gap = 120): string {
      $row = self::find(self::normalizeUid($uid));
      if ($row === null || empty($row['captured_at'])) {
         return '';
      }
      $dt = \DateTime::createFromFormat(
         'Y-m-d H:i:s',
         (string)$row['captured_at'],
         self::timezone()
      );
      if ($dt === false) {
         return '';
      }
      /*
       * `$min_gap <= 0` : la date est due QUOI QU'IL ARRIVE — c'est le cas du
       * rejeu, qui s'annonce par `sign_replayed`. Le seuil ne subsiste que pour
       * les envois directs, où répéter une capture vieille de quelques secondes
       * n'apprendrait rien que la date d'édition ne dise déjà.
       */
      if ($min_gap > 0 && (time() - $dt->getTimestamp()) < $min_gap) {
         return '';
      }
      return $dt->format('d/m/Y H:i');
   }

   /**
    * Horodatage de capture reçu du navigateur.
    *
    * Refusé s'il est dans le futur ou vieux de plus de 30 jours : une horloge
    * de téléphone déréglée ne doit pas écrire n'importe quoi sur un document
    * signé. Dans le doute, on ne stocke rien plutôt qu'une date fausse.
    */
   private static function normalizeCapturedAt(string $raw): ?string {
      $raw = trim($raw);
      if ($raw === '') {
         return null;
      }
      // La chaîne ISO du navigateur porte son fuseau (le « Z » d'UTC) :
      // `strtotime` donne le bon instant quel que soit le fuseau ambiant.
      $stamp = strtotime($raw);
      if ($stamp === false || $stamp <= 0) {
         return null;
      }
      $now = time();
      if ($stamp > $now + 300 || $stamp < $now - (30 * 86400)) {
         return null;
      }
      // Stockage dans le fuseau NOMMÉ de la classe, jamais dans l'ambiant.
      return (new \DateTime('@' . $stamp))->setTimezone(self::timezone())->format('Y-m-d H:i:s');
   }

   /**
    * États demandés par la file du navigateur AVANT de rejouer.
    *
    * C'est ce qui rend le dispositif auto-réparant : une signature partie mais
    * dont la réponse s'est perdue est vue « done » ici, et retirée de la file
    * sans qu'un second document soit produit.
    *
    * @param string[] $uids
    * @return array<string, array{state:string, document_url:string, date:string}>
    */
   static function statesFor(array $uids): array {
      global $CFG_GLPI;

      $out = [];
      foreach ($uids as $raw) {
         $uid = self::normalizeUid(is_string($raw) ? $raw : '');
         if ($uid === '') {
            continue;
         }

         $row = self::find($uid);
         if ($row === null) {
            $out[$uid] = ['state' => 'unknown', 'document_url' => '', 'date' => ''];
            continue;
         }

         $state = (string)($row['state'] ?? 'unknown');
         if ($state === 'running') {
            $started = strtotime((string)($row['date_start'] ?? '')) ?: 0;
            if ($started > 0 && (time() - $started) >= self::STALE_SECONDS) {
               // Tentative morte : pour la file, c'est un échec à rejouer.
               $state = 'failed';
            }
         }

         $doc_id = (int)($row['documents_id'] ?? 0);
         $out[$uid] = [
            'state'        => $state,
            'document_url' => $doc_id > 0
               ? ((string)($CFG_GLPI['root_doc'] ?? '') . '/front/document.send.php?docid=' . $doc_id)
               : '',
            'date'         => (string)($row['date_end'] ?? $row['date_start'] ?? ''),
         ];
      }

      return $out;
   }
}
