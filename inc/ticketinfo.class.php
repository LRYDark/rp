<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Détection automatique des informations d'un ticket pour préremplir les
 * formulaires du plugin (rapport de préparation, fiche de prise en charge).
 *
 * L'objectif est d'éviter les doubles saisies : le numéro de série, la marque,
 * le modèle et les coordonnées sont très souvent déjà présents quelque part
 * (matériel associé au ticket, réponses d'un formulaire GLPI, texte du ticket,
 * données client déjà enregistrées par le plugin).
 *
 * Ordre de priorité : données déjà saisies dans RP > matériel associé au ticket
 * > réponses de formulaire GLPI > texte libre (titre, description, suivis, tâches).
 */
class PluginRpTicketInfo {

   /** @var array<int,array> cache par ticket */
   private static $cache = [];

   /**
    * Toutes les informations détectables d'un ticket.
    *
    * @return array{serial:string,marque:string,modele:string,materiel:string,
    *               contact_name:string,contact_coord:string,email:string,phone:string}
    */
   static function detect(int $ticket_id): array {
      if (isset(self::$cache[$ticket_id])) {
         return self::$cache[$ticket_id];
      }

      $info = [
         'serial'        => '',
         'marque'        => '',
         'modele'        => '',
         'materiel'      => '',
         'contact_name'  => '',
         'contact_coord' => '',
         'email'         => '',
         'phone'         => '',
      ];

      if ($ticket_id <= 0) {
         return self::$cache[$ticket_id] = $info;
      }

      // 1) Données client déjà enregistrées par le plugin (fiche de prise en charge)
      self::fillFromDataclient($ticket_id, $info);

      // 2) Matériel associé au ticket (source la plus fiable)
      self::fillFromLinkedItems($ticket_id, $info);

      // 3) Réponses d'un formulaire GLPI ayant généré le ticket
      self::fillFromFormAnswers($ticket_id, $info);

      // 4) Texte libre du ticket (titre, description, suivis, tâches)
      self::fillFromText($ticket_id, $info);

      // 5) Demandeur du ticket : nom / e-mail / téléphone
      self::fillFromRequester($ticket_id, $info);

      /*
       * « Téléphone / Mail » de la fiche de prise en charge, assemblé ICI et
       * non dans la recherche du demandeur : celle-ci peut ne rien retenir
       * (elle écarte l'utilisateur connecté), et le téléphone ou l'e-mail
       * trouvés dans le texte du ticket n'auraient alors jamais été reportés.
       */
      if (trim((string)$info['contact_coord']) === '') {
         $coord = array_filter([trim((string)$info['email']), trim((string)$info['phone'])]);
         $info['contact_coord'] = implode(' / ', $coord);
      }

      return self::$cache[$ticket_id] = $info;
   }

   /**
    * Le ticket a-t-il été créé par un formulaire GLPI ?
    *
    * GLPI 11 ne marque pas le ticket lui-même : la source (`requesttypes_id`)
    * reste celle du canal et ne dit rien du formulaire. Le lien est porté par
    * une table de relation du moteur de formulaires,
    * `glpi_forms_destinations_answerssets_formdestinationitems`, qui rattache
    * un jeu de réponses (`glpi_forms_answerssets`) à chaque élément produit —
    * ticket, changement, problème... C'est donc la présence d'une ligne
    * (itemtype = Ticket, items_id = ce ticket) qui fait foi.
    */
   static function isFromForm(int $ticket_id): bool {
      global $DB;

      if ($ticket_id <= 0
          || !$DB->tableExists('glpi_forms_destinations_answerssets_formdestinationitems')) {
         return false;
      }

      return countElementsInTable(
         'glpi_forms_destinations_answerssets_formdestinationitems',
         ['itemtype' => 'Ticket', 'items_id' => $ticket_id]
      ) > 0;
   }

   private static function setIfEmpty(array &$info, string $key, $value): void {
      $value = trim((string)$value);
      if ($value !== '' && trim((string)($info[$key] ?? '')) === '') {
         $info[$key] = $value;
      }
   }

   private static function fillFromDataclient(int $ticket_id, array &$info): void {
      global $DB;

      $row = $DB->request([
         'SELECT' => ['serial_number', 'email', 'phone'],
         'FROM'   => 'glpi_plugin_rp_dataclient',
         'WHERE'  => ['id_ticket' => $ticket_id],
         'LIMIT'  => 1,
      ])->current();
      if ($row) {
         self::setIfEmpty($info, 'serial', $row['serial_number'] ?? '');
         self::setIfEmpty($info, 'email', $row['email'] ?? '');
         self::setIfEmpty($info, 'phone', $row['phone'] ?? '');
      }
   }

   /**
    * Matériel associé au ticket : numéro de série (ou numéro d'inventaire),
    * fabricant, modèle et type de matériel.
    */
   private static function fillFromLinkedItems(int $ticket_id, array &$info): void {
      global $DB;

      $links = $DB->request([
         'SELECT' => ['itemtype', 'items_id'],
         'FROM'   => 'glpi_items_tickets',
         'WHERE'  => ['tickets_id' => $ticket_id],
      ]);

      foreach ($links as $link) {
         $itemtype = (string)($link['itemtype'] ?? '');
         $items_id = (int)($link['items_id'] ?? 0);
         if ($itemtype === '' || $items_id <= 0 || !class_exists($itemtype)) {
            continue;
         }
         $item = new $itemtype();
         if (!($item instanceof CommonDBTM) || !$item->getFromDB($items_id)) {
            continue;
         }

         // numéro de série, sinon numéro d'inventaire
         if ($item->isField('serial')) {
            self::setIfEmpty($info, 'serial', $item->fields['serial'] ?? '');
         }
         if ($item->isField('otherserial')) {
            self::setIfEmpty($info, 'serial', $item->fields['otherserial'] ?? '');
         }

         // fabricant
         if ($item->isField('manufacturers_id') && (int)$item->fields['manufacturers_id'] > 0) {
            self::setIfEmpty($info, 'marque', Dropdown::getDropdownName('glpi_manufacturers', (int)$item->fields['manufacturers_id']));
         }

         // modèle (table dépendante de l'itemtype : computermodels_id, monitormodels_id...)
         $model_fk = method_exists($item, 'getModelForeignKeyField') ? $item->getModelForeignKeyField() : null;
         if ($model_fk !== null && $item->isField($model_fk) && (int)$item->fields[$model_fk] > 0) {
            $model_table = getTableNameForForeignKeyField($model_fk);
            self::setIfEmpty($info, 'modele', Dropdown::getDropdownName($model_table, (int)$item->fields[$model_fk]));
         }

         // désignation du matériel : nom de l'objet + son type
         self::setIfEmpty($info, 'materiel', trim($itemtype::getTypeName(1) . ' ' . (string)($item->fields['name'] ?? '')));

         if ($info['serial'] !== '' && $info['marque'] !== '') {
            break;
         }
      }
   }

   /**
    * Réponses d'un formulaire GLPI 11 ayant généré le ticket : on lit le libellé
    * de chaque question pour repérer numéro de série, marque et modèle.
    */
   private static function fillFromFormAnswers(int $ticket_id, array &$info): void {
      global $DB;

      if (!$DB->tableExists('glpi_forms_answerssets')
          || !$DB->tableExists('glpi_forms_destinations_answerssets_formdestinationitems')) {
         return;
      }

      $rows = $DB->request([
         'SELECT'     => ['a.answers'],
         'FROM'       => 'glpi_forms_destinations_answerssets_formdestinationitems AS d',
         'INNER JOIN' => [
            'glpi_forms_answerssets AS a' => [
               'ON' => ['d' => 'forms_answerssets_id', 'a' => 'id'],
            ],
         ],
         'WHERE'      => ['d.itemtype' => 'Ticket', 'd.items_id' => $ticket_id],
      ]);

      foreach ($rows as $row) {
         $answers = json_decode((string)($row['answers'] ?? ''), true);
         if (!is_array($answers)) {
            continue;
         }
         foreach ($answers as $answer) {
            if (!is_array($answer)) {
               continue;
            }
            $label = self::normalize((string)($answer['question_label'] ?? ''));
            $value = $answer['raw_answer'] ?? '';
            if (is_array($value)) {
               /*
                * Une réponse peut être imbriquée : question « élément » (couple
                * itemtype / items_id), choix multiples de listes... `strval` sur
                * un sous-tableau levait « Array to string conversion » à chaque
                * ouverture d'un formulaire de rapport. Seuls les scalaires sont
                * retenus, à tous les niveaux.
                */
               $flat = [];
               array_walk_recursive($value, static function ($v) use (&$flat) {
                  if (is_scalar($v)) {
                     $flat[] = (string)$v;
                  }
               });
               $value = implode(', ', array_filter($flat));
            }
            $value = trim((string)$value);
            if ($label === '' || $value === '') {
               continue;
            }

            if (self::labelMatches($label, ['sn', 's n', 'serial', 'serial number', 'serialnumber',
                                            'numero de serie', 'num serie', 'n serie', 'no serie',
                                            'numero serie', 'service tag', 'servicetag'])) {
               self::setIfEmpty($info, 'serial', $value);
            } elseif (self::labelMatches($label, ['marque', 'fabricant', 'manufacturer', 'constructeur'])) {
               self::setIfEmpty($info, 'marque', $value);
            } elseif (self::labelMatches($label, ['modele', 'model', 'reference', 'ref'])) {
               self::setIfEmpty($info, 'modele', $value);
            } elseif (self::labelMatches($label, ['materiel', 'equipement', 'appareil', 'machine', 'produit'])) {
               self::setIfEmpty($info, 'materiel', $value);
            }
         }
      }
   }

   /**
    * Texte libre du ticket : titre, description, suivis et tâches.
    * Couvre les nombreuses écritures possibles (SN, S/N, sn:, n° de série,
    * numéro de serie, serial number, service tag...).
    */
   private static function fillFromText(int $ticket_id, array &$info): void {
      global $DB;

      // Le texte alimente aussi le contact client : ne sortir que si TOUT est
      // déjà connu, sinon la recherche du contact serait sautée dès qu'un
      // matériel est rattaché au ticket.
      if ($info['serial'] !== '' && $info['marque'] !== ''
          && $info['contact_name'] !== '' && $info['phone'] !== '' && $info['email'] !== '') {
         return;
      }

      $texts = [];
      $ticket = $DB->request([
         'SELECT' => ['name', 'content'],
         'FROM'   => 'glpi_tickets',
         'WHERE'  => ['id' => $ticket_id],
         'LIMIT'  => 1,
      ])->current();
      if ($ticket) {
         $texts[] = (string)($ticket['name'] ?? '');
         $texts[] = (string)($ticket['content'] ?? '');
      }
      foreach ($DB->request(['SELECT' => ['content'], 'FROM' => 'glpi_itilfollowups',
                             'WHERE' => ['itemtype' => 'Ticket', 'items_id' => $ticket_id]]) as $row) {
         $texts[] = (string)($row['content'] ?? '');
      }
      foreach ($DB->request(['SELECT' => ['content'], 'FROM' => 'glpi_tickettasks',
                             'WHERE' => ['tickets_id' => $ticket_id]]) as $row) {
         $texts[] = (string)($row['content'] ?? '');
      }

      $blob = self::toPlainText(implode("\n", $texts));
      if (trim($blob) === '') {
         return;
      }

      if ($info['serial'] === '') {
         /*
          * Libellé (nombreuses variantes) puis séparateur optionnel puis valeur.
          *
          * `n[°ºo]` et non `n[°º]` : « No de Serie » s'écrit très souvent avec
          * un O, pas un symbole degré — cette seule lettre manquante faisait
          * échouer toute la détection sur les tickets rédigés ainsi.
          * Le point après l'abréviation est admis (« N. de série », « Num. »).
          */
         $pattern = '/(?:s\s*[\/\.]?\s*n\.?|serial(?:\s*(?:number|no|n[°ºo]))?|'
                  . 'n[°ºo]?\.?\s*(?:de\s*)?s[ée]rie|num[ée]ro\s*(?:de\s*)?s[ée]rie|'
                  . 'num\.?\s*(?:de\s*)?s[ée]rie|nr\.?\s*(?:de\s*)?s[ée]rie|'
                  . 'service\s*tag)'
                  . '\s*[:=\-–]?\s*([A-Za-z0-9][A-Za-z0-9\-\/\.]{3,30})/iu';
         if (preg_match_all($pattern, $blob, $matches)) {
            foreach ($matches[1] as $candidate) {
               $candidate = trim($candidate, " \t\n\r\0\x0B-./");
               // un vrai numéro de série contient au moins un chiffre
               if ($candidate !== '' && preg_match('/\d/', $candidate) && strlen($candidate) >= 4) {
                  self::setIfEmpty($info, 'serial', $candidate);
                  break;
               }
            }
         }
      }

      if ($info['marque'] === '') {
         if (preg_match('/(?:marque|fabricant|constructeur)\s*[:=\-–]?\s*([A-Za-zÀ-ÿ0-9][A-Za-zÀ-ÿ0-9\- ]{1,40})/iu', $blob, $m)) {
            self::setIfEmpty($info, 'marque', trim($m[1]));
         }
      }
      if ($info['modele'] === '') {
         if (preg_match('/(?:mod[èe]le|model)\s*[:=\-–]?\s*([A-Za-zÀ-ÿ0-9][A-Za-zÀ-ÿ0-9\-\.\/ ]{1,40})/iu', $blob, $m)) {
            self::setIfEmpty($info, 'modele', trim($m[1]));
         }
      }

      /*
       * Contact CLIENT écrit dans le ticket.
       *
       * Cherché ici, donc AVANT le demandeur : sur un ticket créé par le
       * technicien lui-même, le demandeur est le compte GLPI du technicien, et
       * la fiche de prise en charge se retrouvait à son nom au lieu de celui du
       * client. Ce qui est écrit dans la demande fait foi.
       */
      if ($info['contact_name'] === '') {
         $name_pattern = '/(?:contact|responsable(?:\s*mat[ée]riel)?|interlocuteur|'
                       . 'utilisateur|a\s*l.attention\s*de|pour\s*le\s*compte\s*de)'
                       . '\s*[:=\-–]?\s*([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\- ]{2,40})/iu';
         if (preg_match($name_pattern, $blob, $m)) {
            // La capture s'arrête au premier retour à la ligne ou à la ponctuation
            $candidate = trim(preg_split('/[\r\n,;\/|]/u', $m[1])[0] ?? '');
            if (mb_strlen($candidate) >= 3) {
               self::setIfEmpty($info, 'contact_name', $candidate);
            }
         }
      }

      if ($info['email'] === '') {
         if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $blob, $m)) {
            self::setIfEmpty($info, 'email', $m[0]);
         }
      }

      if ($info['phone'] === '') {
         // Numéro annoncé par un libellé, puis, à défaut, tout numéro français
         $labelled = '/(?:t[ée]l(?:[ée]phone)?|portable|mobile|gsm|num[ée]ro)'
                   . '\s*[:=\-–]?\s*((?:\+33|0)[\d\s.\-]{8,14}\d)/iu';
         if (preg_match($labelled, $blob, $m)) {
            self::setIfEmpty($info, 'phone', trim($m[1]));
         } elseif (preg_match('/\b(?:\+33|0)[\s.\-]?[1-9](?:[\s.\-]?\d{2}){4}\b/u', $blob, $m)) {
            self::setIfEmpty($info, 'phone', trim($m[0]));
         }
      }
   }

   /**
    * Demandeur du ticket : nom complet, e-mail, téléphone.
    *
    * Dernier recours seulement, et JAMAIS l'utilisateur connecté : quand le
    * technicien saisit lui-même le ticket, il en devient le demandeur, et la
    * fiche de prise en charge se remplissait alors à son nom au lieu de celui
    * du client. Mieux vaut un champ vide, que le technicien complète, qu'un
    * nom faux qu'il risque de laisser passer.
    */
   private static function fillFromRequester(int $ticket_id, array &$info): void {
      global $DB;

      $where = ['tu.tickets_id' => $ticket_id, 'tu.type' => CommonITILActor::REQUESTER];
      $self  = (int)Session::getLoginUserID();
      if ($self > 0) {
         $where[] = ['NOT' => ['tu.users_id' => $self]];
      }

      $row = $DB->request([
         'SELECT'     => ['u.id', 'u.name', 'u.realname', 'u.firstname', 'u.phone', 'u.phone2', 'u.mobile'],
         'FROM'       => 'glpi_tickets_users AS tu',
         'INNER JOIN' => [
            'glpi_users AS u' => ['ON' => ['tu' => 'users_id', 'u' => 'id']],
         ],
         'WHERE'      => $where,
         'ORDER'      => ['tu.id ASC'],
         'LIMIT'      => 1,
      ])->current();

      if (!$row) {
         return;
      }

      $fullname = trim(trim((string)($row['firstname'] ?? '')) . ' ' . trim((string)($row['realname'] ?? '')));
      if ($fullname === '') {
         $fullname = (string)($row['name'] ?? '');
      }
      self::setIfEmpty($info, 'contact_name', $fullname);
      self::setIfEmpty($info, 'phone', $row['phone'] ?? '');
      self::setIfEmpty($info, 'phone', $row['mobile'] ?? '');
      self::setIfEmpty($info, 'phone', $row['phone2'] ?? '');

      $mail = $DB->request([
         'SELECT' => ['email'],
         'FROM'   => 'glpi_useremails',
         'WHERE'  => ['users_id' => (int)$row['id']],
         'ORDER'  => ['is_default DESC', 'id ASC'],
         'LIMIT'  => 1,
      ])->current();
      if ($mail) {
         self::setIfEmpty($info, 'email', $mail['email'] ?? '');
      }

      // `contact_coord` est assemblé dans detect(), une fois toutes les sources
      // explorées : voir le commentaire là-bas.
   }

   /**
    * Normalisation d'un libellé : minuscules, sans accents ni ponctuation.
    */
   private static function normalize(string $text): string {
      $text = self::toPlainText($text);
      $text = mb_strtolower($text, 'UTF-8');
      $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
      if ($translit !== false) {
         $text = $translit;
      }
      $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
      return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
   }

   private static function labelMatches(string $normalized_label, array $keywords): bool {
      foreach ($keywords as $keyword) {
         if ($normalized_label === $keyword
             || str_starts_with($normalized_label, $keyword . ' ')
             || str_contains($normalized_label, ' ' . $keyword . ' ')
             || str_ends_with($normalized_label, ' ' . $keyword)) {
            return true;
         }
      }
      return false;
   }

   /**
    * HTML riche GLPI -> texte brut exploitable par les expressions régulières.
    */
   private static function toPlainText(string $html): string {
      $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</tr>'], "\n", $text);
      $text = strip_tags($text);
      $text = str_replace("\xC2\xA0", ' ', $text); // espace insécable
      return trim($text);
   }
}
