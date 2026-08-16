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

      return self::$cache[$ticket_id] = $info;
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
               $value = implode(', ', array_filter(array_map('strval', $value)));
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

      if ($info['serial'] !== '' && $info['marque'] !== '') {
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
         // libellé (nombreuses variantes) puis séparateur optionnel puis valeur
         $pattern = '/(?:s\s*\/?\s*\.?\s*n|serial(?:\s*(?:number|no|n[°º]))?|'
                  . 'n[°º]?\s*(?:de\s*)?s[ée]rie|num[ée]ro\s*(?:de\s*)?s[ée]rie|'
                  . 'num\.?\s*s[ée]rie|service\s*tag)'
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
   }

   /**
    * Demandeur du ticket : nom complet, e-mail, téléphone.
    */
   private static function fillFromRequester(int $ticket_id, array &$info): void {
      global $DB;

      $row = $DB->request([
         'SELECT'     => ['u.id', 'u.name', 'u.realname', 'u.firstname', 'u.phone', 'u.phone2', 'u.mobile'],
         'FROM'       => 'glpi_tickets_users AS tu',
         'INNER JOIN' => [
            'glpi_users AS u' => ['ON' => ['tu' => 'users_id', 'u' => 'id']],
         ],
         'WHERE'      => ['tu.tickets_id' => $ticket_id, 'tu.type' => CommonITILActor::REQUESTER],
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

      // « Téléphone / Mail » de la fiche de prise en charge
      $coord = array_filter([trim((string)$info['email']), trim((string)$info['phone'])]);
      self::setIfEmpty($info, 'contact_coord', implode(' / ', $coord));
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
