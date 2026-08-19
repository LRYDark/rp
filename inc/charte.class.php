<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Charte graphique d'un rapport : logo, couleur du texte des bandeaux et lignes
 * de bas de page, rattachée à une entité.
 *
 * Remplace les colonnes numérotées `entity_parrent1/2`, `color_text1/2`,
 * `logo_id`/`logo_id2` de la configuration, qui limitaient le plugin à deux
 * chartes et ne permettaient qu'un seul bas de page pour les deux. Une charte
 * par ligne, en nombre libre.
 *
 * Le choix reste manuel au moment de la génération (bouton radio « Type de
 * rapport »), simplement présélectionné d'après l'entité du ticket. Une entité
 * qui ne correspond à aucune charte retombe sur celle marquée par défaut,
 * comme le faisait l'ancien code avec la charte 1.
 */
/*
 * Classe volontairement AUTONOME, sans héritage de CommonDBTM.
 *
 * Elle n'a besoin d'aucun service de CommonDBTM : ni onglet, ni moteur de
 * recherche, ni massive action — seulement quelques lectures et un rendu. En
 * hériter n'apportait rien et exposait à des collisions de signature : PHP
 * refuse de charger une classe dont une méthode statique contredit celle du
 * parent. `getById()` et `getIcon()` existent toutes deux dans CommonDBTM, et
 * la première a fait échouer le chargement du plugin. Sans héritage, aucun nom
 * de méthode n'est réservé.
 */
class PluginRpCharte {

   const TABLE = 'glpi_plugin_rp_chartes';

   static function getTypeName($nb = 0) {
      return _n('Charte de rapport', 'Chartes de rapport', $nb, 'rp');
   }

   /**
    * Toutes les chartes, dans l'ordre d'affichage.
    *
    * @return array liste de tableaux associatifs (vide si la table n'existe pas
    *               encore, par exemple entre deux migrations)
    */
   static function getAll(): array {
      global $DB;

      if (!$DB->tableExists(self::TABLE)) {
         return [];
      }
      $rows = [];
      foreach ($DB->request(['FROM' => self::TABLE, 'ORDER' => ['rank ASC', 'id ASC']]) as $row) {
         $rows[(int)$row['id']] = $row;
      }
      return $rows;
   }

   /**
    * Une charte par son identifiant, ou null.
    */
   static function getById(int $id): ?array {
      global $DB;

      if ($id <= 0 || !$DB->tableExists(self::TABLE)) {
         return null;
      }
      $row = $DB->request(['FROM' => self::TABLE, 'WHERE' => ['id' => $id], 'LIMIT' => 1])->current();
      return $row ?: null;
   }

   /**
    * Charte par défaut : celle marquée comme telle, sinon la première.
    * Sert de repli pour toute entité ne correspondant à aucune charte.
    */
   static function getDefault(): ?array {
      global $DB;

      if (!$DB->tableExists(self::TABLE)) {
         return null;
      }
      $row = $DB->request([
         'FROM'  => self::TABLE,
         'WHERE' => ['is_default' => 1],
         'ORDER' => ['rank ASC', 'id ASC'],
         'LIMIT' => 1,
      ])->current();
      if ($row) {
         return $row;
      }
      $row = $DB->request(['FROM' => self::TABLE, 'ORDER' => ['rank ASC', 'id ASC'], 'LIMIT' => 1])->current();
      return $row ?: null;
   }

   /**
    * Charte à présélectionner pour un ticket, d'après son entité.
    *
    * La correspondance reprend exactement la règle de l'ancien
    * `getEntityGroupFromEntityId()` : le NOM de l'entité de la charte doit
    * apparaître dans l'arborescence complète de l'entité du ticket. Une entité
    * fille hérite donc de la charte de son entité parente.
    *
    * @param int $entities_id entité du ticket
    */
   static function getForEntity(int $entities_id): ?array {
      global $DB;

      $all = self::getAll();
      if (empty($all)) {
         return null;
      }
      if ($entities_id <= 0) {
         return self::getDefault();
      }

      /*
       * Correspondance par IDENTIFIANT, jamais par nom.
       *
       * La première version comparait le nom de l'entité de la charte aux
       * segments du chemin complet de l'entité du ticket. C'était faux :
       * `Entity` étend `CommonTreeDropdown`, donc `Dropdown::getDropdownName()`
       * renvoie le CHEMIN COMPLET (« Root entity > EASI SUPPORT ») et non le
       * nom court — la comparaison ne pouvait aboutir que pour une entité
       * racine. Toutes les autres retombaient silencieusement sur la charte par
       * défaut : les PDF restaient habillés, mais le rattachement par entité ne
       * fonctionnait pas.
       *
       * Le lignage par identifiants est exact et insensible aux renommages.
       *
       * NB : l'entité racine porte l'identifiant 0, valeur également utilisée
       * par « aucune entité » dans le sélecteur. Une charte rattachée à la
       * racine n'est donc pas distinguable d'une charte sans entité — c'est la
       * charte par défaut qui joue ce rôle.
       */
      $lineage   = array_map('intval', array_values(getAncestorsOf('glpi_entities', $entities_id)));
      $lineage[] = $entities_id;
      $lineage   = array_values(array_unique(array_filter($lineage, static fn($id) => $id > 0)));
      if (empty($lineage)) {
         return self::getDefault();
      }

      $by_entity = [];
      foreach ($all as $charte) {
         $charte_entity = (int)($charte['entities_id'] ?? 0);
         if ($charte_entity > 0) {
            $by_entity[$charte_entity] = $charte;
         }
      }
      if (empty($by_entity)) {
         return self::getDefault();
      }

      // La charte la plus PROCHE l'emporte : une entité fille peut donc avoir
      // sa propre charte tout en héritant de celle de sa mère à défaut.
      foreach ($DB->request([
         'SELECT' => ['id'],
         'FROM'   => 'glpi_entities',
         'WHERE'  => ['id' => $lineage],
         'ORDER'  => ['level DESC'],
      ]) as $row) {
         $eid = (int)$row['id'];
         if (isset($by_entity[$eid])) {
            return $by_entity[$eid];
         }
      }

      return self::getDefault();
   }

   /*
    * ------------------------------------------------------------------
    * Charte du document en cours de génération
    *
    * Fixée UNE fois au début de la génération, puis lue partout : les
    * générateurs de PDF testaient jusqu'ici `$_POST['entity_parrent']` à plus
    * de cent endroits, avec une paire de `if` recopiée pour chaque couleur.
    * Ajouter une troisième charte aurait voulu dire ajouter un troisième `if`
    * partout. Ces accesseurs suppriment le problème à la racine.
    *
    * Les méthodes de FPDF (Header, Footer) n'ont accès ni au ticket ni à ses
    * variables : d'où le passage par une propriété statique plutôt que par un
    * paramètre.
    * ------------------------------------------------------------------
    */
   private static $current = null;

   /** À appeler une fois, au début de la génération d'un document. */
   static function setCurrent(?array $charte): void {
      self::$current = $charte;
   }

   static function current(): ?array {
      return self::$current;
   }

   /**
    * Repli sur les anciennes colonnes de configuration.
    *
    * FILET DE SÉCURITÉ INDISPENSABLE : tant que la migration qui crée les
    * chartes n'a pas tourné, la table est absente ou vide et `setCurrent()`
    * reçoit null. Sans ce repli, les PDF sortiraient avec les couleurs par
    * défaut et sans logo — une régression visible sur une base non migrée.
    *
    * On retombe alors exactement sur le comportement d'origine, en relisant la
    * valeur historique `entity_parrent1` / `entity_parrent2` du POST.
    */
   /**
    * Choix historique imposé par un appelant qui n'a pas de POST.
    *
    * L'export massif est atteint par une redirection, donc en GET : le repli
    * ci-dessous n'y trouverait jamais `entity_parrent` et retomberait toujours
    * sur la charte 1, alors que l'utilisateur a pu choisir la seconde dans le
    * menu de l'action massive. Cet appelant renseigne donc son choix ici.
    */
   private static $legacy_choice = '';

   static function setLegacyChoice(string $value): void {
      self::$legacy_choice = $value;
   }

   private static function legacy(): array {
      $config = PluginRpConfig::getInstance();
      $posted = self::$legacy_choice !== ''
         ? self::$legacy_choice
         : (string)($_POST['entity_parrent'] ?? '');
      $second = ($posted === 'entity_parrent2');

      return $second
         ? [
            'color_bg'   => (string)($config->fields['color2'] ?? '#2980b9'),
            'color_text' => (string)($config->fields['color_text2'] ?? '#ffffff'),
            'logo_id'    => (int)($config->fields['logo_id2'] ?? 0),
            'line1'      => (string)($config->fields['line3'] ?? ''),
            'line2'      => (string)($config->fields['line4'] ?? ''),
         ]
         : [
            'color_bg'   => (string)($config->fields['color1'] ?? '#2980b9'),
            'color_text' => (string)($config->fields['color_text1'] ?? '#ffffff'),
            'logo_id'    => (int)($config->fields['logo_id'] ?? 0),
            'line1'      => (string)($config->fields['line1'] ?? ''),
            'line2'      => (string)($config->fields['line2'] ?? ''),
         ];
   }

   /** Charte en cours, ou repli sur l'ancienne configuration. */
   private static function effective(): array {
      return self::$current ?? self::legacy();
   }

   /** Couleur de remplissage des bandeaux et du cartouche de titre. */
   static function colorBg(): string {
      $value = trim((string)(self::effective()['color_bg'] ?? ''));
      return $value !== '' ? $value : '#2980b9';
   }

   /** Couleur du texte des titres. */
   static function colorText(): string {
      $value = trim((string)(self::effective()['color_text'] ?? ''));
      return $value !== '' ? $value : '#ffffff';
   }

   static function logoId(): int {
      return (int)(self::effective()['logo_id'] ?? 0);
   }

   /** Les deux lignes de bas de page, toujours deux entrées. */
   static function footerLines(): array {
      $charte = self::effective();
      return [
         (string)($charte['line1'] ?? ''),
         (string)($charte['line2'] ?? ''),
      ];
   }

   /**
    * Charte demandée par un formulaire de génération, avec repli.
    *
    * @param mixed $posted valeur du champ `entity_parrent` du POST
    * @param int   $entities_id entité du ticket, pour le repli par entité
    */
   static function resolve($posted, int $entities_id = 0): ?array {
      $id = (int)$posted;
      if ($id > 0) {
         $charte = self::getById($id);
         if ($charte) {
            return $charte;
         }
      }
      return self::getForEntity($entities_id);
   }

   /**
    * Enregistre le fichier envoyé comme logo d'une charte.
    *
    * Regroupé ici pour que la création et la modification d'une charte se
    * fassent en UN seul enregistrement : sans cela, il fallait créer la charte
    * puis revenir lui envoyer son logo.
    *
    * L'ancien logo est supprimé — fichier physique et Document GLPI — pour ne
    * pas accumuler des images orphelines à chaque changement.
    *
    * @param int   $charte_id charte cible, supposée existante
    * @param array $file      entrée de $_FILES
    * @return string chaîne vide si tout s'est bien passé, message d'erreur sinon
    */
   static function storeLogo(int $charte_id, array $file): string {
      global $DB;

      // Aucun fichier fourni : ce n'est pas une erreur, le champ est facultatif.
      if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
         return '';
      }
      if (!empty($file['error'])) {
         return __("Erreur lors de l'envoi du logo.", 'rp');
      }

      $charte = self::getById($charte_id);
      if ($charte === null) {
         return __('Charte introuvable.', 'rp');
      }

      // Contrôle du contenu réel, pas de l'extension annoncée.
      $info  = @getimagesize($file['tmp_name']);
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($file['tmp_name']);
      $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (($file['size'] ?? 0) > 10240000 || $info === false || !in_array($mime, $allowed, true)) {
         return __("Le logo dépasse 10 Mo ou n'est pas une image valide (JPEG, PNG, GIF, WEBP).", 'rp');
      }

      $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$file['name']));
      $name = ltrim((string)$name, '.');
      if ($name === '') {
         $name = 'logo_' . date('YmdHis') . '.png';
      }
      // Préfixe unique : deux chartes peuvent recevoir un fichier homonyme.
      $name = 'charte' . $charte_id . '_' . date('YmdHis') . '_' . $name;

      $relative = '_plugins/rp/logo/' . $name;
      $dir      = GLPI_PLUGIN_DOC_DIR . '/rp/logo/';
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
         return __("Impossible de créer le dossier des logos.", 'rp');
      }

      $doc    = new Document();
      $new_id = $doc->add([
         'name'         => $name,
         'filename'     => $name,
         'filepath'     => $relative,
         'mime'         => (string)($info['mime'] ?? 'application/octet-stream'),
         'users_id'     => Session::getLoginUserID(),
         'is_recursive' => 1,
      ]);
      if (!$new_id) {
         return __("Échec de l'enregistrement du logo.", 'rp');
      }

      if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
         $doc->delete(['id' => (int)$new_id], 1);
         return __("Échec du déplacement du fichier logo.", 'rp');
      }
      // GLPI 11 blackliste filepath/sha1sum dans Document::add() : réécriture
      // directe, APRÈS le déplacement physique ci-dessus.
      pluginRpFixDocumentFile((int)$new_id, $relative);

      // Ancien logo : fichier puis Document, dans cet ordre.
      $old_id = (int)($charte['logo_id'] ?? 0);
      if ($old_id > 0) {
         $old = new Document();
         if ($old->getFromDB($old_id)) {
            $old_path = (string)($old->fields['filepath'] ?? '');
            foreach ([$old_path, stripslashes($old_path)] as $candidate) {
               if ($candidate !== '' && file_exists(GLPI_DOC_DIR . '/' . $candidate)) {
                  @unlink(GLPI_DOC_DIR . '/' . $candidate);
                  break;
               }
            }
            $old->delete(['id' => $old_id], 1);
         }
      }

      $DB->update(self::TABLE, ['logo_id' => (int)$new_id], ['id' => $charte_id]);

      return '';
   }

   /**
    * Aperçu du logo d'une charte, ou un libellé si aucun n'est enregistré.
    */
   static function renderLogo(int $logo_id, int $height = 40): string {
      global $CFG_GLPI;

      if ($logo_id <= 0) {
         return "<span class='text-secondary'>" . __('Aucun logo', 'rp') . "</span>";
      }
      $doc = new Document();
      if (!$doc->getFromDB($logo_id)) {
         return "<span class='text-secondary'>" . __('Aucun logo', 'rp') . "</span>";
      }
      $path = (string)($doc->fields['filepath'] ?? '');
      if ($path === '' || !file_exists(GLPI_DOC_DIR . '/' . $path)) {
         return "<span class='text-danger'>" . __('Logo introuvable', 'rp') . "</span>";
      }
      $url = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/')
         . '/front/document.send.php?docid=' . $logo_id;

      return "<img src='" . htmlspecialchars($url, ENT_QUOTES) . "' alt='' "
         . "style='max-height:" . (int)$height . "px;max-width:160px;'>";
   }

   /**
    * Liste des chartes dans la page de configuration : une carte par charte,
    * modification dans un modal, ajout et suppression sur place.
    *
    * Remplace les deux blocs « Configuration du bas de page - Logo 1 / 2 »,
    * qui étaient figés à deux et dissociaient le logo de son bas de page.
    */
   static function showConfigList(): void {
      global $CFG_GLPI;

      $chartes   = self::getAll();
      $form_url  = Plugin::getWebDir('rp') . '/front/charte.form.php';
      /*
       * GLPI 11 valide `_glpi_csrf_token` dans un écouteur du noyau, AVANT
       * d'atteindre le fichier appelé : c'est CE nom de champ qu'il faut poster,
       * un jeton sous un autre nom serait rejeté sans jamais être lu.
       * Jeton autonome : l'onglet de configuration est chargé en AJAX, et le
       * jeton courant de la page y est souvent déjà consommé.
       */
      $token = Session::getNewCSRFToken(true);

      echo "<div class='card mb-3'>";
      echo "  <div class='card-header d-flex justify-content-between align-items-center'>";
      echo "    <div>";
      echo "      <div class='card-title mb-1'>" . __('Chartes de rapport', 'rp') . "</div>";
      echo "      <div class='text-secondary small'>"
         . __("Logo, couleurs et bas de page appliqués au PDF, selon l'entité du ticket.", 'rp')
         . "</div>";
      echo "    </div>";
      echo "    <button type='button' class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#rp-charte-add'>"
         . "<i class='ti ti-plus me-1'></i>" . __('Ajouter une charte', 'rp') . "</button>";
      echo "  </div>";

      if (empty($chartes)) {
         echo "  <div class='card-body'>";
         echo "    <div class='alert alert-info mb-0'>"
            . __("Aucune charte n'est définie. Ajoutez-en une pour personnaliser vos rapports.", 'rp')
            . "</div>";
         echo "  </div>";
      } else {
         echo "  <div class='list-group list-group-flush'>";
         foreach ($chartes as $charte) {
            self::showConfigRow($charte, $form_url, $token, count($chartes));
         }
         echo "  </div>";
      }
      echo "</div>";

      self::showEditModal(null, $form_url, $token);
   }

   /** Une ligne de la liste, avec son modal de modification. */
   private static function showConfigRow(
      array $charte,
      string $form_url,
      string $token,
      int $total
   ): void {
      $id      = (int)$charte['id'];
      $default = (int)($charte['is_default'] ?? 0) === 1;
      $entity  = (int)($charte['entities_id'] ?? 0);
      $modal   = 'rp-charte-edit-' . $id;

      echo "<div class='list-group-item'>";
      echo "  <div class='row align-items-center g-3'>";

      // Aperçu seul : le logo se change dans le modal, avec le reste des
      // réglages, en un seul enregistrement.
      echo "    <div class='col-12 col-md-3 text-center'>";
      echo        self::renderLogo((int)($charte['logo_id'] ?? 0), 60);
      echo "    </div>";

      // Identité et réglages
      echo "    <div class='col-12 col-md-6'>";
      echo "      <div class='fw-bold'>" . htmlspecialchars((string)($charte['name'] ?? ''), ENT_QUOTES);
      if ($default) {
         // `text-white` explicite : `bg-blue` est une couleur utilitaire Tabler,
         // elle ne fixe QUE le fond. Sans cela le libellé héritait de la couleur
         // du texte environnant et devenait illisible sur le fond bleu.
         echo "   <span class='badge bg-blue text-white ms-2'>" . __('Par défaut', 'rp') . "</span>";
      }
      echo "      </div>";
      echo "      <div class='text-secondary small mt-1'>";
      echo          __('Entité') . " : "
         . ($entity > 0
            ? htmlspecialchars(Dropdown::getDropdownName('glpi_entities', $entity), ENT_QUOTES)
            : "<em>" . __('aucune', 'rp') . "</em>");
      echo "      </div>";
      echo "      <div class='d-flex align-items-center gap-2 mt-2 small'>";
      echo "        <span class='d-inline-block rounded' style='width:18px;height:18px;background:"
         . htmlspecialchars((string)($charte['color_bg'] ?? '#2980b9'), ENT_QUOTES)
         . ";border:1px solid var(--tblr-border-color,#dee2e6);'></span>";
      echo "        <span class='text-secondary'>" . __('Fond', 'rp') . "</span>";
      echo "        <span class='d-inline-block rounded ms-2' style='width:18px;height:18px;background:"
         . htmlspecialchars((string)($charte['color_text'] ?? '#ffffff'), ENT_QUOTES)
         . ";border:1px solid var(--tblr-border-color,#dee2e6);'></span>";
      echo "        <span class='text-secondary'>" . __('Texte', 'rp') . "</span>";
      echo "      </div>";
      $line1 = trim((string)($charte['line1'] ?? ''));
      $line2 = trim((string)($charte['line2'] ?? ''));
      if ($line1 !== '' || $line2 !== '') {
         echo "   <div class='text-secondary small mt-2 text-break'>"
            . "<i class='ti ti-layout-bottombar me-1'></i>"
            . htmlspecialchars(trim($line1 . ' ' . $line2), ENT_QUOTES) . "</div>";
      }
      echo "    </div>";

      // Actions
      echo "    <div class='col-12 col-md-3 text-md-end'>";
      echo "      <button type='button' class='btn btn-sm btn-outline-primary mb-1' "
         . "data-bs-toggle='modal' data-bs-target='#" . $modal . "'>"
         . "<i class='ti ti-edit me-1'></i>" . __('Modifier', 'rp') . "</button>";
      if ($total > 1) {
         echo "   <form action='" . htmlspecialchars($form_url, ENT_QUOTES) . "' method='post' class='d-inline'>";
         echo       Html::hidden('_glpi_csrf_token', ['value' => $token]);
         echo "     <input type='hidden' name='id' value='" . $id . "'>";
         echo "     <button type='submit' name='delete_charte' class='btn btn-sm btn-outline-danger mb-1' "
            . "onclick=\"return confirm('" . __('Supprimer cette charte ?', 'rp') . "');\">"
            . "<i class='ti ti-trash me-1'></i>" . __('Supprimer', 'rp') . "</button>";
         echo "   </form>";
      }
      echo "    </div>";

      echo "  </div>";
      echo "</div>";

      self::showEditModal($charte, $form_url, $token);
   }

   /**
    * Modal d'ajout (charte à null) ou de modification.
    * Logo et bas de page y sont réunis, ce qui manquait dans l'ancienne page.
    */
   private static function showEditModal(
      ?array $charte,
      string $form_url,
      string $token
   ): void {
      $is_new = ($charte === null);
      $id     = $is_new ? 0 : (int)$charte['id'];
      $modal  = $is_new ? 'rp-charte-add' : 'rp-charte-edit-' . $id;
      $title  = $is_new ? __('Ajouter une charte', 'rp') : __('Modifier la charte', 'rp');
      $field  = static function (string $key, string $fallback = '') use ($charte): string {
         return htmlspecialchars((string)($charte[$key] ?? $fallback), ENT_QUOTES);
      };

      echo "<div class='modal fade' id='" . $modal . "' tabindex='-1' aria-hidden='true'>";
      echo "  <div class='modal-dialog modal-lg modal-dialog-scrollable'>";
      echo "    <div class='modal-content'>";
      // `multipart` : le logo est envoyé avec le reste des réglages, pour que la
      // création d'une charte tienne en un seul enregistrement.
      echo "      <form action='" . htmlspecialchars($form_url, ENT_QUOTES) . "' method='post' "
         . "enctype='multipart/form-data'>";
      echo          Html::hidden('_glpi_csrf_token', ['value' => $token]);
      if (!$is_new) {
         echo "     <input type='hidden' name='id' value='" . $id . "'>";
      }

      echo "        <div class='modal-header'>";
      echo "          <h5 class='modal-title'>" . $title . "</h5>";
      echo "          <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Fermer'></button>";
      echo "        </div>";

      echo "        <div class='modal-body'>";

      echo "          <div class='mb-3'>";
      echo "            <label class='form-label' for='" . $modal . "-name'>" . __('Nom') . "</label>";
      echo "            <input type='text' class='form-control' id='" . $modal . "-name' name='name' "
         . "value='" . $field('name') . "' placeholder='" . __('Nom de la charte', 'rp') . "'>";
      echo "          </div>";

      echo "          <div class='mb-3'>";
      echo "            <label class='form-label' for='" . $modal . "-logo'>" . __('Logo', 'rp') . "</label>";
      if (!$is_new && (int)($charte['logo_id'] ?? 0) > 0) {
         echo "         <div class='mb-2'>" . self::renderLogo((int)$charte['logo_id'], 50) . "</div>";
      }
      echo "            <input type='file' class='form-control' id='" . $modal . "-logo' "
         . "name='photo' accept='image/*'>";
      echo "            <div class='form-text'>"
         . ($is_new
            ? __('JPEG, PNG, GIF ou WEBP, 10 Mo maximum.', 'rp')
            : __("Laisser vide pour conserver le logo actuel. JPEG, PNG, GIF ou WEBP, 10 Mo maximum.", 'rp'))
         . "</div>";
      echo "          </div>";

      echo "          <div class='mb-3'>";
      echo "            <label class='form-label'>" . __('Entité') . "</label>";
      Entity::dropdown([
         'name'                => 'entities_id',
         'value'               => $is_new ? 0 : (int)($charte['entities_id'] ?? 0),
         'display_emptychoice' => true,
         'emptylabel'          => '-----',
         'rand'                => $id + 1,
      ]);
      echo "            <div class='form-text'>"
         . __("Les tickets de cette entité, et de ses entités filles, utiliseront cette charte.", 'rp')
         . "</div>";
      echo "          </div>";

      echo "          <div class='row mb-3'>";
      echo "            <div class='col-6'>";
      echo "              <label class='form-label' for='" . $modal . "-bg'>"
         . __('Couleur des éléments du PDF', 'rp') . "</label>";
      echo "              <input type='color' class='form-control form-control-color' "
         . "id='" . $modal . "-bg' name='color_bg' value='" . $field('color_bg', '#2980b9') . "'>";
      echo "            </div>";
      echo "            <div class='col-6'>";
      echo "              <label class='form-label' for='" . $modal . "-text'>"
         . __('Couleur du texte des titres', 'rp') . "</label>";
      echo "              <input type='color' class='form-control form-control-color' "
         . "id='" . $modal . "-text' name='color_text' value='" . $field('color_text', '#ffffff') . "'>";
      echo "            </div>";
      echo "          </div>";

      echo "          <div class='mb-3'>";
      echo "            <label class='form-label' for='" . $modal . "-line1'>"
         . __('Bas de page — 1re ligne', 'rp') . "</label>";
      echo "            <input type='text' class='form-control' id='" . $modal . "-line1' "
         . "name='line1' maxlength='80' value='" . $field('line1') . "'>";
      echo "          </div>";

      echo "          <div class='mb-3'>";
      echo "            <label class='form-label' for='" . $modal . "-line2'>"
         . __('Bas de page — 2e ligne', 'rp') . "</label>";
      echo "            <input type='text' class='form-control' id='" . $modal . "-line2' "
         . "name='line2' maxlength='80' value='" . $field('line2') . "'>";
      echo "          </div>";

      /*
       * Charte par défaut, proposée aussi à la création : sans cela il fallait
       * créer la charte puis la rouvrir pour la désigner.
       *
       * La case est cochée et désactivée sur la charte qui l'est déjà : le rôle
       * est exclusif et ne se retire pas, il se transfère en cochant une autre.
       * Un champ désactivé n'étant pas posté, la marque est bien conservée.
       */
      $checked = (!$is_new && (int)($charte['is_default'] ?? 0) === 1) ? 'checked disabled' : '';
      echo "          <div class='form-check'>";
      echo "            <input type='checkbox' class='form-check-input' id='" . $modal . "-default' "
         . "name='is_default' value='1' " . $checked . ">";
      echo "            <label class='form-check-label' for='" . $modal . "-default'>"
         . __('Charte par défaut', 'rp') . "</label>";
      echo "            <div class='form-text'>"
         . __("Utilisée pour les entités qui ne correspondent à aucune charte.", 'rp')
         . "</div>";
      echo "          </div>";

      echo "        </div>";

      echo "        <div class='modal-footer'>";
      echo "          <button type='button' class='btn btn-outline-secondary' data-bs-dismiss='modal'>"
         . __('Annuler') . "</button>";
      echo "          <button type='submit' class='btn btn-primary' "
         . "name='" . ($is_new ? 'add_charte' : 'update_charte') . "'>"
         . __('Enregistrer') . "</button>";
      echo "        </div>";

      echo "      </form>";
      echo "    </div>";
      echo "  </div>";
      echo "</div>";
   }
}
