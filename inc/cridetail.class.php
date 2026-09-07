<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpCriDetail extends CommonDBTM implements \Glpi\Search\DefaultSearchRequestInterface {

   // Droit dédié au tableau des rapports (menu Outils) : READ / UPDATE / PURGE
   static $rightname = "plugin_rp_liste";

   static function getIcon() {
      return "fa-solid fa-file";
   }

   static function getTypeName($nb = 0) {
      return _n('Rapport / Prise en charge', 'Rapport / Prise en charge', $nb, 'rp');
   }

   /**
    * Libellés des types de rapports (colonne `type` de glpi_plugin_rp_cridetails).
    */
   static function getTypeLabels(): array {
      return [
         0 => __('Fiche de prise en charge', 'rp'),
         1 => __("Rapport d'intervention", 'rp'),
         2 => __('Rapport hotline', 'rp'),
         3 => __("Rapport d'atelier", 'rp'),
      ];
   }

   /**
    * Tri par défaut de la liste (front/report.php) : derniers rapports en premier.
    * Option 4 = date (cf. rawSearchOptions).
    */
   public static function getDefaultSearchRequest(): array {
      return [
         'sort'  => 4,
         'order' => 'DESC',
      ];
   }

   function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);
      return $ong;
   }

   /**
    * Le PDF part avec la ligne.
    *
    * Appelée par GLPI à chaque purge — action massive de l'onglet ticket comme
    * du tableau « Rapport PDF ». Le tableau supprimait déjà des lignes sans
    * toucher aux fichiers : les PDF restaient sur le disque, référencés par
    * plus rien. Un seul endroit fait désormais le ménage, quel que soit
    * l'écran d'où part la suppression.
    *
    * Le Document n'est purgé que s'il n'appartient qu'à cette ligne : rien
    * n'interdit qu'un autre rapport le référence encore, et lui retirer son
    * fichier le laisserait pointer dans le vide.
    */
   function cleanDBonPurge() {
      $doc_id = (int)($this->fields['id_documents'] ?? 0);
      if ($doc_id <= 0) {
         return;
      }

      $shared = countElementsInTable('glpi_plugin_rp_cridetails', [
         'id_documents' => $doc_id,
         'NOT'          => ['id' => (int)$this->fields['id']],
      ]);
      if ($shared > 0) {
         return;
      }

      /*
       * Même retenue vis-à-vis du plugin Gestion : après une signature groupée,
       * ce document est le PDF fusionné, celui des bons de livraison signés
       * autant que celui du rapport. Supprimer la ligne du rapport ne doit pas
       * emporter les bons avec elle — la ligne s'en va, le document reste.
       */
      if (pluginRpDocumentSharedWithBl($doc_id)) {
         return;
      }

      $doc = new Document();
      if ($doc->getFromDB($doc_id)) {
         // 2e argument : purge. Un `delete` simple mettrait le Document à la
         // corbeille en laissant le PDF sur le disque, or c'est lui qu'on veut
         // voir partir (Document::cleanDBonPurge s'en charge).
         $doc->delete(['id' => $doc_id], 1);
      }
   }

   /**
    * Qui peut supprimer définitivement CE rapport.
    *
    * Deux portes, parce que deux écrans mènent ici :
    *   - le droit de purge du TYPE (`plugin_rp_fiche` / `_rapport_tech` / `_hotline` /
    *     `_preparation`), pour le technicien qui fait le ménage sur son ticket ;
    *   - `plugin_rp_liste` en purge, droit historique du tableau « Rapport PDF »
    *     du menu Gestion, conservé tel quel pour ne rien retirer à personne.
    */
   function canPurgeItem(): bool {
      if (!parent::canPurgeItem()) {
         return false;
      }

      $features = [0 => 'fiche', 1 => 'rapport_tech',
                   2 => 'rapport_hotline', 3 => 'preparation'];
      $feature  = $features[(int)($this->fields['type'] ?? -1)] ?? '';

      if ($feature !== '' && PluginRpAccess::canUse($feature, PURGE)) {
         return true;
      }
      return Session::haveRight('plugin_rp_liste', PURGE);
   }

   /**
    * Droit de classe : au moins un type supprimable, ou le tableau.
    *
    * `$rightname` vaut `plugin_rp_liste` ; sans cet élargissement, un profil
    * autorisé à purger ses rapports d'atelier mais pas le tableau général
    * n'aurait jamais vu l'action, `canPurgeItem()` n'étant même pas consulté.
    *
    * Le tableau « Rapport PDF », lui, ne propose l'action qu'avec le droit de
    * la liste : cf. getForbiddenStandardMassiveAction().
    */
   static function canPurge(): bool {
      return parent::canPurge()
         || PluginRpAccess::canUse('fiche', PURGE)
         || PluginRpAccess::canUse('rapport_tech', PURGE)
         || PluginRpAccess::canUse('rapport_hotline', PURGE)
         || PluginRpAccess::canUse('preparation', PURGE);
   }

   /**
    * Le tableau « Rapport PDF » ne propose la suppression qu'avec le droit de
    * la liste (`plugin_rp_liste` en purge).
    *
    * `canPurge()` s'élargit aux droits par type pour l'onglet du ticket, et
    * GLPI consulte cette même méthode pour composer le menu des actions
    * massives du tableau, sans savoir d'où il est appelé. Un profil autorisé
    * à purger ses rapports depuis le ticket voyait donc « Supprimer
    * définitivement » dans le tableau, et pouvait l'exécuter, sans le droit
    * de la liste : à rebours de l'aide de l'onglet profil, qui réserve chaque
    * droit à son écran.
    *
    * Interdire l'action standard la retire du menu du tableau
    * (MassiveAction::getAllMassiveActions), et GLPI écarte au traitement les
    * lignes d'un itemtype qui l'interdit. L'onglet du ticket n'est pas
    * concerné : il déclare sa propre action `purge` en `specific_actions`,
    * clé nue que GLPI compare telle quelle à cette liste. La clé PRÉFIXÉE est
    * donc indispensable ici : la forme nue `purge` correspondrait aussi à
    * celle de l'onglet, dont toutes les lignes seraient alors écartées.
    */
   function getForbiddenStandardMassiveAction(): array {
      $forbidden = parent::getForbiddenStandardMassiveAction();
      if (!Session::haveRight('plugin_rp_liste', PURGE)) {
         $forbidden[] = 'MassiveAction' . MassiveAction::CLASS_ACTION_SEPARATOR . 'purge';
      }
      return $forbidden;
   }

   /**
    * Entrée de menu Gestion > Rapport PDF (tableau avec les filtres GLPI).
    */
   static function getMenuContent() {
      $menu = [
         'title' => __('Rapport PDF', 'rp'),
         'page'  => PLUGIN_RP_NOTFULL_WEBDIR . '/front/cridetail.php',
         'icon'  => self::getIcon(),
         'links' => [
            'search' => PLUGIN_RP_NOTFULL_WEBDIR . '/front/cridetail.php',
         ],
      ];
      return $menu;
   }

   /**
    * Colonnes du moteur de recherche GLPI (front/report.php).
    */
   function rawSearchOptions() {
      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => __('Rapports', 'rp'),
      ];

      $tab[] = [
         'id'            => '1',
         'table'         => $this->getTable(),
         'field'         => 'id',
         'name'          => __('ID'),
         'datatype'      => 'itemlink',
         'itemlink_type' => $this->getType(),
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => '2',
         'table'         => $this->getTable(),
         'field'         => 'type',
         'name'          => __('Type de rapport', 'rp'),
         'datatype'      => 'specific',
         'searchtype'    => ['equals', 'notequals'],
         'massiveaction' => false,
      ];

      // NB : id_ticket / id_documents ne suivent pas la convention de nommage des
      // clés étrangères GLPI (tickets_id / documents_id) — le datatype itemlink
      // avec linkfield est peu fiable dans ce cas (mauvais id repris pour le lien).
      // Rendu assuré par plugin_rp_giveItem() dans hook.php, sans jointure.
      $tab[] = [
         'id'            => '3',
         'table'         => $this->getTable(),
         'field'         => 'id_ticket',
         'name'          => __('Ticket'),
         'datatype'      => 'specific',
         'searchtype'    => ['equals', 'contains'],
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => '4',
         'table'         => $this->getTable(),
         'field'         => 'date',
         'name'          => __('Date de création'),
         'datatype'      => 'datetime',
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => '5',
         'table'         => $this->getTable(),
         'field'         => 'nameclient',
         'name'          => __('Nom du signataire', 'rp'),
         'datatype'      => 'text',
         'massiveaction' => true,
      ];

      $tab[] = [
         'id'            => '6',
         'table'         => $this->getTable(),
         'field'         => 'email',
         'name'          => _n('Email', 'Emails', 1),
         'datatype'      => 'text',
         'massiveaction' => true,
      ];

      $tab[] = [
         'id'            => '7',
         'table'         => $this->getTable(),
         'field'         => 'send_mail',
         'name'          => __('Envoyé par email', 'rp'),
         'datatype'      => 'bool',
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => '8',
         'table'         => 'glpi_users',
         'field'         => 'name',
         'linkfield'     => 'users_id',
         'name'          => __('Technicien', 'rp'),
         'datatype'      => 'dropdown',
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => '9',
         'table'         => $this->getTable(),
         'field'         => 'id_documents',
         'name'          => Document::getTypeName(1),
         'datatype'      => 'specific',
         'nosearch'      => true,
         'nosort'        => true,
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => '80',
         'table'         => 'glpi_entities',
         'field'         => 'completename',
         'name'          => Entity::getTypeName(1),
         'datatype'      => 'dropdown',
         'massiveaction' => false,
      ];

      // Colonne « Visualiser » : bouton d'ouverture du PDF (comme la colonne
      // « Signature » de PluginGestionSurvey). Rendu par plugin_rp_giveItem().
      $tab[] = [
         'id'            => '14',
         'table'         => $this->getTable(),
         'field'         => 'id_documents',
         'name'          => __('Visualiser', 'rp'),
         'datatype'      => 'specific',
         'nosearch'      => true,
         'nosort'        => true,
         'massiveaction' => false,
      ];

      return $tab;
   }

   static function getSpecificValueToDisplay($field, $values, array $options = []) {
      global $DB;
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      if ($field === 'type') {
         $labels = self::getTypeLabels();
         return $labels[(int)$values[$field]] ?? $values[$field];
      }
      if ($field === 'id_ticket') {
         $ticket_id = (int)$values[$field];
         return $ticket_id > 0 ? '#' . sprintf('%07d', $ticket_id) : '-';
      }
      if ($field === 'id_documents') {
         $doc_id = (int)$values[$field];
         if ($doc_id <= 0) {
            return '-';
         }
         $row = $DB->request([
            'SELECT' => ['filename'],
            'FROM'   => 'glpi_documents',
            'WHERE'  => ['id' => $doc_id],
            'LIMIT'  => 1,
         ])->current();
         return $row ? (string)$row['filename'] : __('Document supprimé', 'rp');
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      if ($field === 'type') {
         $options['display'] = false;
         return Dropdown::showFromArray($name, self::getTypeLabels(), [
            'display' => false,
            'value'   => $values[$field],
         ]);
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * Fiche d'une ligne du tableau (front/cridetail.form.php).
    * Lecture pour tous les détenteurs du droit liste ; seuls nameclient, email
    * et send_mail sont modifiables (le document signé n'est jamais altéré).
    */
   function showForm($ID, array $options = []) {
      global $DB;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      $canedit = self::canUpdate();
      $labels  = self::getTypeLabels();

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Type de rapport', 'rp') . "</td>";
      echo "<td>" . ($labels[(int)$this->fields['type']] ?? '-') . "</td>";
      echo "<td>" . __('Ticket') . "</td>";
      echo "<td><a href='" . Ticket::getFormURLWithID((int)$this->fields['id_ticket']) . "'>#" . sprintf('%07d', (int)$this->fields['id_ticket']) . "</a></td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Date de création') . "</td>";
      echo "<td>" . Html::convDateTime($this->fields['date']) . "</td>";
      echo "<td>" . __('Technicien', 'rp') . "</td>";
      echo "<td>" . getUserName((int)$this->fields['users_id']) . "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Nom du signataire', 'rp') . "</td>";
      echo "<td>";
      if ($canedit) {
         echo Html::input('nameclient', ['value' => $this->fields['nameclient'], 'maxlength' => 255]);
      } else {
         echo htmlspecialchars((string)$this->fields['nameclient'], ENT_QUOTES);
      }
      echo "</td>";
      echo "<td>" . _n('Email', 'Emails', 1) . "</td>";
      echo "<td>";
      if ($canedit) {
         echo Html::input('email', ['value' => $this->fields['email'], 'maxlength' => 255]);
      } else {
         echo htmlspecialchars((string)$this->fields['email'], ENT_QUOTES);
      }
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Envoyé par email', 'rp') . "</td>";
      echo "<td>";
      if ($canedit) {
         Dropdown::showYesNo('send_mail', (int)$this->fields['send_mail']);
      } else {
         echo Dropdown::getYesNo((int)$this->fields['send_mail']);
      }
      echo "</td>";
      echo "<td>" . Document::getTypeName(1) . "</td>";
      echo "<td>";
      $doc_id = (int)$this->fields['id_documents'];
      if ($doc_id > 0) {
         $doc_row = $DB->request([
            'SELECT' => ['filename'],
            'FROM'   => 'glpi_documents',
            'WHERE'  => ['id' => $doc_id],
            'LIMIT'  => 1,
         ])->current();
         if ($doc_row) {
            global $CFG_GLPI;
            echo "<a class='btn btn-sm btn-outline-secondary me-2' href='" . $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=$doc_id' target='_blank'><i class='far fa-file-pdf me-1'></i>" . __('Ouvrir', 'rp') . "</a>";
            echo "<a href='" . Document::getFormURLWithID($doc_id) . "'>" . htmlspecialchars((string)$doc_row['filename'], ENT_QUOTES) . "</a>";
         } else {
            echo "<span class='text-muted'>" . __('Document supprimé', 'rp') . "</span>";
         }
      } else {
         echo "-";
      }
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options + ['candel' => false]);
      return true;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Ticket'
          && (PluginRpAccess::canUse('fiche', READ)
              || PluginRpAccess::canUse('fiche', CREATE)
              || PluginRpAccess::canUse('rapport_tech', READ)
              || PluginRpAccess::canUse('rapport_tech', CREATE)
              || PluginRpAccess::canUse('rapport_hotline', READ)
              || PluginRpAccess::canUse('rapport_hotline', CREATE)
              || PluginRpAccess::canUse('preparation', READ)
              || PluginRpAccess::canUse('preparation', CREATE))) {
         $nb = self::countForItem($item);
         switch ($item->getType()) {
            case 'Ticket' :
               if ($_SESSION['glpishow_count_on_tabs']) {
                  return self::createTabEntry(self::getTypeName($nb), $nb);
               } else {
                  return self::getTypeName($nb);
               }
            default :
               return self::getTypeName($nb);
         }
      }
      return '';
   }

   /**
    * @param $item    CommonDBTM object
   **/
   public static function countForItem(CommonGLPI $item) {
      // NB : historiquement conditionné par erreur au droit "plugin_rt_rt" du plugin RT
      if (PluginRpAccess::canUse('fiche', READ)
          || PluginRpAccess::canUse('rapport_tech', READ)
          || PluginRpAccess::canUse('rapport_hotline', READ)
          || PluginRpAccess::canUse('preparation', READ)) {
         return countElementsInTable('glpi_plugin_rp_cridetails', ['id_ticket' => $item->getID()]);
      }
      return 0;
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI, $DB;
         self::addReports($item, $item->getField('id'));
      return true;
   }

   /**
      Formulaire ticket

    * @param \Ticket $ticket
    * @param array   $options
    */
   /**
    * Ce qui a été SIGNÉ, tout en haut de l'onglet.
    *
    * Ne liste pas les documents produits, mais les signatures obtenues : c'est
    * la seule information qui dise si l'affaire est réellement close. Un PDF
    * généré ne prouve rien, un PDF signé si.
    *
    * Deux signataires possibles selon le document :
    *   - le CLIENT sur la prise en charge, le rapport d'intervention et la
    *     hotline (signature activable en configuration) ;
    *   - le TECHNICIEN sur le rapport d'atelier, remis en main propre.
    *
    * Le nom enregistré n'est celui du client que pour les types 0 et 1 : la
    * hotline et l'atelier y stockent celui du technicien
    * (cf. front/cripdf.form.php). On n'affiche donc un nom de client que là où
    * c'en est vraiment un, plutôt que d'annoncer une signature qui n'existe pas.
    */
   /**
    * Pour chaque type : la fonctionnalité qui en gouverne la lecture, le
    * réglage qui active sa signature, et qui signe.
    *
    * Le RAPPORT HOTLINE est volontairement absent : à la génération, son
    * champ signataire est écrasé par le nom du technicien, sans condition
    * (front/cripdf.form.php:1417). Même lorsqu'un client signe à l'écran,
    * son nom n'est jamais enregistré — annoncer « signé par le client » y
    * serait donc faux, et le champ n'étant jamais vide, TOUS les rapports
    * hotline seraient déclarés signés.
    */
   static function getSignatureTypes(): array {
      return [
         0 => ['feature' => 'fiche',        'flag' => 'sign_rp_charge', 'client' => true],
         1 => ['feature' => 'rapport_tech', 'flag' => 'sign_rp_tech',   'client' => true],
         3 => ['feature' => 'preparation',  'flag' => 'sign_rp_prep',   'client' => false],
      ];
   }

   /**
    * Les signatures obtenues sur ce ticket, les plus récentes d'abord.
    *
    * Source UNIQUE du bandeau « Signatures » et du pliage des cartes : les deux
    * doivent dire la même chose. Une carte repliée sans la ligne correspondante
    * au-dessus serait un document escamoté sans raison visible.
    */
   static function getSignedRows(int $ticket_id): array {
      global $DB;

      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_cridetails')) {
         return [];
      }

      $config = PluginRpConfig::getInstance();
      $types  = self::getSignatureTypes();

      // Types à la fois visibles par l'utilisateur ET dont la signature est
      // activée : un document sans signature configurée n'a rien à dire ici.
      $visible = [];
      foreach ($types as $type => $def) {
         $readable = PluginRpAccess::canUse($def['feature'], READ)
                  || PluginRpAccess::canUse($def['feature'], CREATE)
                  // Le rapport d'intervention se voit aussi depuis l'atelier,
                  // dont il est la conclusion (cf. PluginRpAccess::canProduce).
                  || ($type === 1 && PluginRpAccess::canProduce('rapport_tech'));
         if ($readable && (int)($config->fields[$def['flag']] ?? 0) === 1) {
            $visible[] = $type;
         }
      }
      if (empty($visible)) {
         return [];
      }

      $rows = [];
      foreach ($DB->request([
         'FROM'  => 'glpi_plugin_rp_cridetails',
         'WHERE' => ['id_ticket' => $ticket_id, 'type' => $visible],
         'ORDER' => ['date DESC'],
      ]) as $row) {
         $type = (int)$row['type'];
         // Signature client sans signataire enregistré : le document existe,
         // mais rien ne prouve qu'il a été signé. On ne l'annonce pas.
         if ($types[$type]['client'] && trim((string)($row['nameclient'] ?? '')) === '') {
            continue;
         }
         $rows[] = $row;
      }
      return $rows;
   }

   /**
    * Types de document dont la signature est acquise, dédoublonnés.
    */
   static function getSignedTypes(int $ticket_id): array {
      $found = [];
      foreach (self::getSignedRows($ticket_id) as $row) {
         $found[(int)$row['type']] = true;
      }
      return array_keys($found);
   }


   /**
    * Date du dernier document produit d'un type, pour ce ticket.
    *
    * Sert au plugin Gestion : quand il propose de signer un bon SANS rapport —
    * parce qu'un rapport existe déjà —, il annonce de quand date ce rapport.
    * Un rapport vieux de trois semaines ne décrit plus l'intervention, et le
    * technicien doit pouvoir le voir avant de faire signer le client.
    *
    * Lit la présence, pas la signature : la date reste utile même sur une
    * installation où la signature du rapport est désactivée.
    *
    * @return string|null date SQL, ou null si aucun document de ce type
    */
   static function getLastReportDate(int $ticket_id, int $type = 1): ?string {
      global $DB;

      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_cridetails')) {
         return null;
      }

      $row = $DB->request([
         'SELECT' => ['date'],
         'FROM'   => 'glpi_plugin_rp_cridetails',
         'WHERE'  => ['id_ticket' => $ticket_id, 'type' => $type],
         'ORDER'  => ['date DESC'],
         'LIMIT'  => 1,
      ])->current();

      $date = $row ? trim((string)($row['date'] ?? '')) : '';
      return $date !== '' ? $date : null;
   }

   /**
    * Chemin absolu du PDF du dernier rapport de ce type, quand il fait foi.
    *
    * Sert au plugin Gestion : quand il signe un bon SEUL — parce qu'un rapport
    * existe déjà et que le ticket n'a pas bougé —, il joint ce rapport au PDF
    * du bon, pour que le client reparte avec un document complet plutôt qu'avec
    * deux moitiés à rapprocher.
    *
    * ---- Même définition de « signé » qu'ailleurs ----
    *
    * `getSignedRows()` est la source unique : signature acquise, type lisible
    * par l'utilisateur, et signature activée en configuration. Quand elle ne
    * l'est PAS pour ce type, aucun rapport ne peut être « signé » — on retombe
    * alors sur le plus récent présent, exactement l'arbitrage de
    * `PluginGestionCri::hasSignedRpReport()`. Les deux plugins doivent décider
    * sur les mêmes critères, sinon Gestion joindrait un document que RP ne
    * considère pas comme fait.
    *
    * ---- Les droits ne se contournent pas ----
    *
    * Ce chemin mène au contenu du rapport. `getSignedRows()` filtre déjà sur la
    * lecture de la fonctionnalité ; la branche de repli refait le même test.
    * Un utilisateur qui n'a pas accès aux rapports obtient `null` et signe son
    * bon seul — c'est une dégradation, pas un refus.
    *
    * @return array{id:int,path:string}|null identifiant `glpi_documents` et
    *                                        chemin absolu, ou null si aucun
    *                                        rapport exploitable
    */
   static function getLastReportDocument(int $ticket_id, int $type = 1): ?array {
      global $DB;

      $ticket_id = (int)$ticket_id;
      $type      = (int)$type;

      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_cridetails')) {
         return null;
      }

      $types   = self::getSignatureTypes();
      $flag    = $types[$type]['flag'] ?? null;
      $feature = $types[$type]['feature'] ?? 'rapport_tech';
      $config  = PluginRpConfig::getInstance();

      $documents_id = 0;

      if ($flag !== null && (int)($config->fields[$flag] ?? 0) === 1) {
         // Signature exigée : seule une ligne réellement signée compte.
         // getSignedRows() trie du plus récent au plus ancien.
         foreach (self::getSignedRows($ticket_id) as $row) {
            if ((int)$row['type'] === $type) {
               $documents_id = (int)($row['id_documents'] ?? 0);
               break;
            }
         }
      } else {
         if (!PluginRpAccess::canUse($feature, READ)
             && !PluginRpAccess::canUse($feature, CREATE)) {
            return null;
         }
         $row = $DB->request([
            'SELECT' => ['id_documents'],
            'FROM'   => 'glpi_plugin_rp_cridetails',
            'WHERE'  => ['id_ticket' => $ticket_id, 'type' => $type],
            'ORDER'  => ['date DESC'],
            'LIMIT'  => 1,
         ])->current();
         $documents_id = $row ? (int)($row['id_documents'] ?? 0) : 0;
      }

      if ($documents_id <= 0) {
         return null;
      }

      $doc = $DB->request([
         'SELECT' => ['filepath'],
         'FROM'   => 'glpi_documents',
         'WHERE'  => ['id' => $documents_id],
         'LIMIT'  => 1,
      ])->current();

      $filepath = $doc ? ltrim(str_replace('\\', '/', (string)($doc['filepath'] ?? '')), '/') : '';
      if ($filepath === '') {
         return null;
      }

      /*
       * Le fichier peut manquer : Document purgé à la main, archivage distant,
       * migration de GLPI_DOC_DIR. L'appelant traite `null` comme « pas de
       * rapport à joindre » et signe le bon seul — jamais d'échec bloquant pour
       * un document d'appoint.
       */
      $full = GLPI_DOC_DIR . '/' . $filepath;
      if (!is_file($full)) {
         return null;
      }

      return ['id' => $documents_id, 'path' => $full];
   }

   /**
    * Ce qui a bougé sur le ticket DEPUIS le dernier rapport de ce type.
    *
    * Un rapport décrit l'intervention telle qu'elle était au moment où il a été
    * produit. Si des tâches ou des suivis ont été ajoutés ou modifiés depuis, il
    * ne la décrit plus — et le faire signer au client reviendrait à lui faire
    * signer un document incomplet.
    *
    * Seuls les TÂCHES et les SUIVIS comptent. Un changement de statut, une
    * clôture, une réattribution ne changent rien à ce que raconte le rapport :
    * les compter aurait déclenché l'alerte sur des tickets où il n'y avait rien
    * à régénérer.
    *
    * Le filtre `use_publictask` est repris tel quel : sur une installation où
    * les tâches privées sont exclues du rapport, en ajouter une ne le périme pas.
    *
    * Tolérance de 30 secondes, et pas davantage. La génération du rapport crée
    * elle-même un suivi (commentaire interne) ou une tâche (livraison depuis
    * l'atelier) quelques secondes après avoir horodaté la ligne : sans ce
    * délai, tout rapport se serait déclaré périmé dès sa propre création.
    * Elle était à 2 minutes — assez pour manquer une tâche ajoutée dans la
    * foulée, c'est-à-dire précisément le cas où l'on veut être averti.
    *
    * @return array{tasks:int,followups:int} tout à zéro s'il n'y a pas de rapport
    */
   /**
    * Ce qui a bougé sur le ticket DEPUIS le dernier rapport de ce type.
    *
    * ---- Tout est calculé PAR LA BASE, volontairement ----
    *
    * La comparaison porte sur des colonnes de la base : c'est donc l'horloge de
    * la base qui doit trancher, pas celle de PHP. Les versions précédentes
    * lisaient la date du rapport, la convertissaient avec `strtotime()`,
    * ajoutaient le délai puis renvoyaient une chaîne dans la requête — trois
    * conversions PHP au milieu d'une comparaison SQL.
    *
    * Or les deux horloges divergent : GLPI pose son fuseau sur PHP *et* sur la
    * session MySQL avant d'écrire (`DBmysql::setTimezone`), tandis que le
    * plugin horodate ses propres lignes avec `date()`, hors de ce cadre.
    * Constaté sur cette installation : 2 heures pile d'écart, si bien qu'une
    * tâche ajoutée APRÈS le rapport paraissait antérieure de 1 h 35 — et
    * qu'aucune modification ne pouvait jamais être détectée.
    *
    * Ici, MySQL compare des colonnes entre elles et calcule lui-même le délai
    * (`DATE_ADD ... INTERVAL`). Les deux côtés subissent exactement la même
    * conversion de fuseau : le décalage ne peut plus s'introduire.
    *
    * ---- Ce qui compte comme changement ----
    *
    * Seuls les TÂCHES et les SUIVIS. Un changement de statut, une clôture, une
    * réattribution ne changent rien à ce que raconte le rapport ; les compter
    * aurait alerté sur des tickets où il n'y avait rien à régénérer.
    *
    * Le filtre `use_publictask` est repris tel quel : sur une installation où
    * les tâches privées sont exclues du rapport, en ajouter une ne le périme pas.
    *
    * Référence : `glpi_documents.date_mod` du document produit — écrite par le
    * cœur de GLPI, comme les tâches et les suivis. Repli sur la date de la ligne
    * du plugin si le rapport n'a pas de document.
    *
    * Tolérance de 30 secondes : la génération crée elle-même un suivi
    * (commentaire interne) ou une tâche (livraison depuis l'atelier) juste après
    * avoir horodaté le document. Sans ce délai, tout rapport se déclarerait
    * périmé dès sa propre création.
    *
    * @return array{tasks:int,followups:int} tout à zéro s'il n'y a pas de rapport
    */
   static function countChangesSinceReport(int $ticket_id, int $type = 1): array {
      global $DB;

      $none      = ['tasks' => 0, 'followups' => 0];
      $ticket_id = (int)$ticket_id;
      $type      = (int)$type;

      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_cridetails')) {
         return $none;
      }

      $config      = PluginRpConfig::getInstance();
      $only_public = (int)($config->fields['use_publictask'] ?? 0) === 1;

      /*
       * Instant de référence, entièrement évalué par la base.
       *
       * `COALESCE` : le document fait foi ; à défaut — ligne sans document —
       * on retombe sur la date du plugin. Si le ticket n'a aucun rapport, le
       * tout vaut NULL, la comparaison vaut NULL, et rien n'est compté.
       */
      $ref_sql = "DATE_ADD(COALESCE(
                     (SELECT MAX(d.`date_mod`)
                        FROM `glpi_plugin_rp_cridetails` c
                        INNER JOIN `glpi_documents` d ON d.`id` = c.`id_documents`
                       WHERE c.`id_ticket` = $ticket_id AND c.`type` = $type),
                     (SELECT MAX(c2.`date`)
                        FROM `glpi_plugin_rp_cridetails` c2
                       WHERE c2.`id_ticket` = $ticket_id AND c2.`type` = $type)
                  ), INTERVAL 30 SECOND)";

      /*
       * `GREATEST` sur les trois horodatages : `date` est la date métier, que
       * l'utilisateur peut antidater ; `date_creation` et `date_mod` disent
       * quand la ligne est réellement apparue ou a changé. Le repli à 1971
       * neutralise les colonnes NULL sans les faire gagner.
       */
      $newest = "GREATEST(
                    COALESCE(%1\$s.`date_mod`,      '1971-01-01 00:00:00'),
                    COALESCE(%1\$s.`date_creation`, '1971-01-01 00:00:00'),
                    COALESCE(%1\$s.`date`,          '1971-01-01 00:00:00')
                 )";

      $count = static function (string $sql) use ($DB): int {
         $res = $DB->doQuery($sql);
         $row = $res ? $res->fetch_assoc() : null;
         return (int)($row['cpt'] ?? 0);
      };

      $tasks = $count(
         "SELECT COUNT(*) AS cpt
            FROM `glpi_tickettasks` t
           WHERE t.`tickets_id` = $ticket_id"
         . ($only_public ? " AND t.`is_private` = 0" : '')
         . " AND " . sprintf($newest, 't') . " > $ref_sql"
      );

      $followups = 0;
      if ($DB->tableExists('glpi_itilfollowups')) {
         $followups = $count(
            "SELECT COUNT(*) AS cpt
               FROM `glpi_itilfollowups` f
              WHERE f.`itemtype` = 'Ticket' AND f.`items_id` = $ticket_id"
            . ($only_public ? " AND f.`is_private` = 0" : '')
            . " AND " . sprintf($newest, 'f') . " > $ref_sql"
         );
      }

      return ['tasks' => $tasks, 'followups' => $followups];
   }
   /**
    * Identifiants des lignes dont la signature est acquise.
    *
    * Depuis que la carte « Signatures » a disparu — elle redisait ce que les
    * cartes de couleur montrent déjà —, c'est cette liste qui porte le badge
    * « Signé » au bon endroit : sur la ligne du document concerné, et non sur
    * un récapitulatif séparé.
    */   static function getSignedRowIds(int $ticket_id): array {
      $ids = [];
      foreach (self::getSignedRows($ticket_id) as $row) {
         $ids[] = (int)$row['id'];
      }
      return $ids;
   }

   /**
    * Ce qui distingue chaque type de document : où son PDF est rangé, qui
    * signe, et par quel droit il se gouverne.
    *
    * Les quatre cartes rendaient auparavant quatre tableaux presque identiques,
    * recopiés à la main — d'où des divergences (colonnes, chemins de secours,
    * messages) qu'aucune n'avait voulues.
    */
   private static function getDocumentTypeDef(int $type): ?array {
      /*
       * Aucune liste de dossiers ici, volontairement : l'existence du fichier
       * se vérifie sur `glpi_documents.filepath`, seul chemin qui fasse foi.
       * Deviner le dossier à partir du type était déjà faux pour les rapports
       * produits en lot (`rapportsMass`), et le serait devenu pour tous depuis
       * le rangement par année/mois.
       */
      $defs = [
         0 => ['feature' => 'fiche',
               'signer'  => __('Signataire', 'rp'),
               'email'   => true,
               'empty'   => __('Aucune fiche de prise en charge générée !', 'rp')],
         1 => ['feature' => 'rapport_tech',
               'signer'  => __('Signataire', 'rp'),
               'email'   => true,
               'empty'   => __('Aucun rapport de généré !', 'rp')],
         2 => ['feature' => 'rapport_hotline',
               // Hotline : le champ porte le nom du technicien, jamais celui
               // du client (cf. getSignatureTypes).
               'signer'  => __('Technicien', 'rp'),
               'email'   => true,
               'empty'   => __('Aucun rapport de généré !', 'rp')],
         3 => ['feature' => 'preparation',
               'signer'  => __('Technicien atelier', 'rp'),
               'email'   => false,
               'empty'   => __("Aucun rapport d'atelier généré !", 'rp')],
      ];

      return $defs[$type] ?? null;
   }

   /**
    * Documents d'un type, présentés comme le bandeau « Signatures ».
    *
    * Le tableau à cinq colonnes disait la même chose que la carte au-dessus de
    * lui, dans une forme différente : deux mises en page pour un seul contenu.
    * La liste reprend celle des signatures — libellé fort, précisions en gris,
    * actions et date à droite — si bien que l'onglet ne se lit plus que d'une
    * seule façon. Elle tient aussi sur un téléphone, ce qu'un tableau à cinq
    * colonnes ne faisait pas.
    */
   static function showDocumentList(int $ticket_id, int $type, int $limit): void {
      global $DB;

      $def = self::getDocumentTypeDef($type);
      if ($def === null) {
         return;
      }

      $rows = [];
      foreach ($DB->request([
         'FROM'  => 'glpi_plugin_rp_cridetails',
         'WHERE' => ['id_ticket' => $ticket_id, 'type' => $type],
         'ORDER' => ['date DESC'],
         'LIMIT' => max(1, $limit),
      ]) as $row) {
         $rows[] = $row;
      }

      if (empty($rows)) {
         echo "<div class='card-body'>";
         echo "  <div class='alert alert-info mb-0'>"
            . "<i class='fa-solid fa-circle-info me-2'></i>" . $def['empty'] . "</div>";
         echo "</div>";
         return;
      }

      $signed_ids = self::getSignedRowIds($ticket_id);

      /*
       * Suppression par les ACTIONS MASSIVES de GLPI, pas par un bouton de ligne.
       *
       * Un bouton « Supprimer » sur chaque ligne EN PLUS de la barre « Actions »
       * offrait deux fois la même chose et chargeait la ligne d'un cinquième
       * élément. On garde le mécanisme natif : case à cocher + barre d'actions,
       * comme l'onglet « Gestion BL ». Le ménage sur le disque est fait par
       * `cleanDBonPurge()`, donc identique quelle que soit la voie empruntée.
       */
      $can_purge = self::canPurge();
      $rand      = mt_rand();
      $container = 'mass' . __CLASS__ . $type . $rand;

      if ($can_purge) {
         echo Html::getOpenMassiveActionsForm($container);
         $massiveactionparams = [
            'num_displayed'    => count($rows),
            'container'        => $container,
            'rand'             => $rand,
            'display'          => false,
            'specific_actions' => [
               'purge' => _x('button', 'Supprimer définitivement de GLPI'),
            ],
         ];
      }

      echo "<div class='list-group list-group-flush'>";

      foreach ($rows as $row) {
         $doc_id   = (int)($row['id_documents'] ?? 0);
         $filename = '';
         $exists   = false;

         if ($doc_id > 0) {
            $doc = $DB->request([
               'SELECT' => ['filename', 'filepath'],
               'FROM'   => 'glpi_documents',
               'WHERE'  => ['id' => $doc_id],
               'LIMIT'  => 1,
            ])->current();
            $filename = trim((string)($doc['filename'] ?? ''));
            /*
             * Existence jugée sur le `filepath` enregistré, jamais sur un
             * dossier deviné : c'est le seul chemin qui reste juste pour les
             * anciens PDF à plat comme pour les nouveaux rangés par année/mois,
             * et pour ceux produits par l'export massif.
             */
            $filepath = ltrim(str_replace('\\', '/', (string)($doc['filepath'] ?? '')), '/');
            $exists   = $filepath !== '' && is_file(GLPI_DOC_DIR . '/' . $filepath);
         }

         $is_signed = in_array((int)$row['id'], $signed_ids, true);

         echo "<div class='list-group-item py-2'>";
         echo "  <div class='d-flex justify-content-between align-items-start flex-wrap gap-2'>";

         // ---- Identité du document ----
         echo "    <div class='d-flex align-items-start gap-2 text-break'>";
         if ($can_purge) {
            echo "<div class='pt-1'>" . Html::getMassiveActionCheckBox(__CLASS__, (int)$row['id']) . "</div>";
         }
         echo "      <div>";
         if ($filename === '') {
            echo "      <div class='fw-bold text-secondary'>"
               . "<i class='ti ti-file-off me-1'></i>" . __('Document supprimé', 'rp') . "</div>";
         } else {
            /*
             * Le fichier absent du disque n'est pas masqué : la ligne existe en
             * base, la cacher laisserait croire que rien n'a été généré. Elle
             * est signalée en rouge, et reste supprimable pour faire le ménage.
             */
            $class = $exists ? 'fw-bold' : 'fw-bold text-danger';
            echo "      <div class='" . $class . "'>"
               . "<a href='document.form.php?id=" . $doc_id . "'>"
               . htmlspecialchars($filename, ENT_QUOTES) . "</a></div>";
            if (!$exists) {
               echo "      <div class='text-danger small mt-1'>"
                  . "<i class='ti ti-alert-triangle me-1'></i>"
                  . __('Fichier introuvable sur le disque', 'rp') . "</div>";
            }
         }

         echo "      <div class='text-secondary small mt-1'>";
         $signer = trim((string)($row['nameclient'] ?? ''));
         echo $def['signer'] . ' : <strong>'
            . ($signer !== '' ? htmlspecialchars($signer, ENT_QUOTES) : '-') . '</strong>';
         if ($def['email']) {
            $email = trim((string)($row['email'] ?? ''));
            if ($email !== '') {
               echo "<br><i class='ti ti-mail me-1'></i>" . htmlspecialchars($email, ENT_QUOTES);
            }
         }
         echo "        </div>";
         echo "      </div>";
         echo "    </div>";

         // ---- Action et date ----
         echo "    <div class='text-end'>";
         if ($doc_id > 0) {
            echo "      <a class='btn btn-sm btn-outline-secondary" . ($exists ? '' : ' text-danger')
               . "' href='document.send.php?docid=" . $doc_id . "' target='_blank'>"
               . "<i class='far fa-file-pdf me-1'></i>" . __('Ouvrir', 'rp') . "</a>";
         }

         /*
          * « Signé le » plutôt qu'une date nue.
          *
          * La colonne « Date de création » du tableau ne disait pas ce que la
          * date raconte : sur un document signé, c'est l'instant de la
          * signature qui compte, pas celui de la mise en page. Là où la
          * signature n'est pas établie — hotline, ou document jamais
          * contresigné — on annonce « Généré le », qui est exact.
          */
         $date = trim((string)($row['date'] ?? ''));
         if ($date !== '') {
            $label = $is_signed ? __('Signé le', 'rp') : __('Généré le', 'rp');
            echo "      <div class='text-secondary small mt-1'>"
               . $label . ' : ' . htmlspecialchars(Html::convDateTime($date), ENT_QUOTES) . "</div>";
         }
         echo "    </div>";

         echo "  </div>";
         echo "</div>";
      }

      echo "</div>"; // list-group

      /*
       * Barre d'actions unique, sous la liste : deux barres pour une carte qui
       * n'affiche souvent qu'une ligne alourdiraient plus qu'elles n'aideraient.
       *
       * `forcecreate` est INDISPENSABLE ici : `Html::showMassiveActions()` ne
       * déclare la fenêtre modale que sur l'appel `ontop` (cf. src/Html.php).
       * Avec la seule barre du bas, le lien appelait une fonction JS jamais
       * définie — « modal_massiveaction_window… is not defined ».
       */
      if ($can_purge) {
         $massiveactionparams['ontop']       = false;
         $massiveactionparams['forcecreate'] = true;
         echo "<div class='card-body py-2'>";
         echo Html::showMassiveActions($massiveactionparams);
         echo "</div>";
         echo Html::closeForm(false);
      }
   }

   /**
    * Badge « Signé », accolé au titre de la carte.
    *
    * Il a pris la place du chevron de pliage, retiré avec lui : la carte ne se
    * replie plus. Le pliage cachait la liste derrière un geste, et le badge
    * était affiché deux fois — en tête et sur chaque ligne — pour compenser.
    * Un seul badge, contre le titre, à l''endroit exact où le regard cherche
    * l''état du document.
    *
    * La hotline n''en reçoit jamais : sa signature n''est pas traçable
    * (cf. getSignatureTypes), l''annoncer serait faux.
    */
   private static function signedBadge(bool $signed, string $label = ''): string {
      if (!$signed) {
         return '';
      }
      // Même gabarit que les pastilles de l'onglet « Gestion BL » : `inline-flex`
      // centré, pour que l'icône et le libellé s'alignent de la même façon.
      return "<span class='badge d-inline-flex align-items-center bg-success text-white'>"
         . "<i class='ti ti-check me-1'></i>" . ($label !== '' ? $label : __('Signé', 'rp')) . "</span>";
   }

   /**
    * Bandeau « étape suivante », au-dessus des quatre cartes.
    *
    * Il ne remplace ni ne masque rien : les cartes restent en dessous, avec
    * leurs boutons. Il indique seulement ce qu'il reste logiquement à faire, et
    * l'ouvre en un clic — le technicien n'a plus à choisir entre quatre
    * documents pour retrouver celui de son étape.
    *
    * La recommandation vient de PluginRpTicketActions, la même source que le
    * bouton flottant et le scanner : les trois écrans ne peuvent pas diverger.
    * Si aucune étape ne se dégage — tout est fait, ou les droits manquent —
    * rien n'est affiché, plutôt qu'un bouton qui ne mènerait nulle part.
    */
   static function showNextStep(int $ticket_id): void {
      echo self::getNextStepHtml($ticket_id);
   }

   /**
    * Le bandeau en HTML plutôt qu'à l'écran.
    *
    * Séparé de `showNextStep()` pour que l'onglet « Gestion BL » du plugin
    * Gestion puisse l'insérer dans la chaîne qu'il construit avant de l'émettre :
    * un `echo` direct s'y serait affiché avant tout le reste de l'onglet, pas
    * au-dessus du tableau.
    *
    * @return string vide si aucune étape ne se dégage
    */
   static function getNextStepHtml(int $ticket_id): string {
      $payload = PluginRpTicketActions::build($ticket_id);
      if ($payload === null || empty($payload['next'])) {
         return '';
      }

      $action = null;
      foreach (($payload['actions'] ?? []) as $candidate) {
         if (($candidate['key'] ?? '') === $payload['next']) {
            $action = $candidate;
            break;
         }
      }
      if ($action === null) {
         return '';
      }

      // Les paramètres d'ouverture reprennent exactement ceux des cartes : on
      // réutilise rp_loadCriForm et gestion_loadCriForm, aucune logique de
      // signature n'est réécrite ici.
      if (($action['mode'] ?? '') === 'gestion') {
         $params  = [
            'job'        => $ticket_id,
            'root_doc'   => (string)($payload['gestion_webdir'] ?? ''),
            'root_modal' => 'rp-next-step',
         ];
         if (empty($action['bl_only'])) {
            $params['force_combined'] = 1;
         } else {
            $params['force_bl'] = 1;
         }
         $onclick = 'gestion_loadCriForm("showCriForm", "' . (int)$action['bl_id'] . '", '
            . json_encode($params) . '); return false;';
      } else {
         $params  = ['job' => $ticket_id, 'root_doc' => PLUGIN_RP_WEBDIR];
         $onclick = 'rp_loadCriForm("showCriForm", "' . (string)$action['modal'] . '", '
            . json_encode($params) . '); return false;';
      }

      /*
       * `btn-info` et non `btn-primary` : sur cet écran, TOUS les autres boutons
       * (Générer, Régénérer, Actions) sont en `primary`. Le bandeau ne se
       * distinguait donc que par sa position, et se noyait dans la colonne de
       * droite. Le bleu Tabler tranche sans reprendre le vert, déjà porté par
       * les badges « Signé ».
       *
       * Classe sémantique plutôt qu'une couleur en dur : `--tblr-info` est
       * redéfinie par chaque thème GLPI, y compris sombre. Un `#4299e1` écrit
       * ici resterait figé et finirait illisible.
       *
       * Le liseré gauche porte le même signal : la couleur seule ne suffit pas
       * à distinguer un bouton pour qui la perçoit mal.
       *
       * `border-left` et non `card-status-start` : ce dernier est un filet de
       * 2 px posé en absolu, qui ne se comportait pas comme le liseré des
       * quatre cartes de rapport — celles-ci utilisent une vraie bordure de
       * 4 px, qui épouse les angles arrondis de la carte. Le bandeau se
       * distinguait donc d'elles par sa forme autant que par sa couleur. La
       * couleur reste `var(--tblr-info)`, redéfinie par chaque thème.
       */
      $html  = "<div class='card mb-3' style='border-left:4px solid var(--tblr-info);'>";
      $html .= "  <div class='card-body d-flex align-items-center justify-content-between flex-wrap gap-2'>";
      $html .= "    <div>";
      $html .= "      <div class='text-secondary small'>" . __('Étape suivante', 'rp') . "</div>";
      $html .= "      <div class='fw-bold'>" . htmlspecialchars((string)$action['label'], ENT_QUOTES) . "</div>";
      if (!empty($action['hint'])) {
         $html .= "   <div class='text-secondary small'>"
            . htmlspecialchars((string)$action['hint'], ENT_QUOTES) . "</div>";
      }
      $html .= "    </div>";
      $html .= "    <button type='button' class='btn btn-info btn-lg' onclick='"
         . htmlspecialchars($onclick, ENT_QUOTES) . "'>";
      $html .= "      <i class='" . htmlspecialchars((string)($action['icon'] ?? 'ti ti-file'), ENT_QUOTES) . " me-2'></i>"
         . __('Continuer', 'rp');
      $html .= "    </button>";
      $html .= "  </div>";
      $html .= "</div>";

      /*
       * Séparateur : ce qu'il reste à faire, puis ce qui existe déjà.
       *
       * Court et centré plutôt qu'un filet pleine largeur — il marque une
       * respiration, pas une frontière de section. Rendu ici et non par les
       * appelants pour qu'il disparaisse avec le bandeau : sans étape à
       * proposer, il n'y a rien à séparer.
       *
       * `currentColor` via `<hr>` : la couleur suit le thème GLPI, sombre
       * compris, sans qu'aucune valeur ne soit écrite en dur.
       */
      $html .= "<hr class='mx-auto' style='max-width:50%;border-top-width:4px;opacity:.4;margin-top:4rem;margin-bottom:4rem;'>";

      return $html;
   }

   static function addReports(Ticket $ticket, $options = []) { //ticket formulaire
      global $DB, $CFG_GLPI;
      $UserID     = Session::getLoginUserID();
      $config     = PluginRpConfig::getInstance();
      $ID         = $ticket->fields['id'];
      $modal      = 'rp_cri_form' . $ID;

      // ===== Petit trait coloré sous les titres =====
      echo "<style>
      .rp-title {
         position: relative;
         padding-bottom: .25rem;
         display: inline-block;
      }
      .rp-title::after {
         content: '';
         position: absolute;
         left: 0;
         bottom: -2px;
         width: 85px;      /* longueur du trait (ajuste si besoin) */
         height: 2px;      /* épaisseur du trait */
         background: var(--rp-title-color, #0d6efd);
         border-radius: 2px;
      }
      </style>";

      /*
       * L'action d'abord : le technicien ouvre cet onglet pour avancer.
       *
       * La carte « Signatures » qui suivait a été retirée : elle reprenait, dans
       * une seconde mise en page, ce que les cartes de couleur portent
       * désormais elles-mêmes — badge « Signé » contre le titre, « Signé le »
       * sur chaque ligne. Deux endroits pour une même information, c'était un
       * doublon à tenir à jour et une hauteur d'écran perdue.
       */
      self::showNextStep($ID);

      /*
       * Un type est « signé » ou il ne l'est pas : c'est tout ce dont les
       * cartes ont besoin depuis que le pliage a disparu. Les cartes restent
       * ouvertes, le badge dit l'état, la liste est là — plus rien à déplier.
       *
       * La hotline (type 2) n'entre jamais dans les signatures traçables
       * (cf. getSignatureTypes) : sa carte n'a donc jamais de badge.
       */
      $rp_signed      = self::getSignedTypes($ID);
      $rp_signed_type = static fn(int $type): bool => in_array($type, $rp_signed, true);

      // Nombre de documents affichés par carte (réglage « mode multi-doc ») :
      // le même que celui appliqué aux tableaux qui précédaient ces listes.
      $rp_limit = ((int)($config->fields['multi_display'] ?? 0)) > 0
         ? (int)$config->fields['multi_display']
         : 1;

// __________________________________________ FICHE DE PRISE EN CHARGE __________________________________________
      // ----- bouton génération fiche client -----  
      $crifiche = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0")->fetch_object();

      if(PluginRpAccess::canUse('fiche', CREATE) || PluginRpAccess::canUse('fiche', READ)){
         // Bordure BLEUE (#007bff)
         echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #007bff;'>";
            echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

               // ===== Titre avec trait bleu, chevron accolé =====
               echo "<div class='d-flex align-items-center gap-2'>";
                  echo "<h3 class='card-title mb-0'>
                           <span class='rp-title' style='--rp-title-color:#007bff'>
                              <i class='fa-regular fa-file-lines me-2'></i>".
                              __("Fiche de prise en charge", 'rp').
                           "</span>
                        </h3>";
                  echo self::signedBadge($rp_signed_type(0));
               echo "</div>";

               // Bloc d'action, à l'opposé du titre : le
               // `justify-content-between` de l'en-tête n'accepte que deux blocs.
               echo "<div class='d-flex align-items-center gap-2'>";

               if(PluginRpAccess::canUse('fiche', CREATE)){
                  $modalclient = 'form_client';

                     // GENERATE        
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           // Libellé simplifié: Générer / Régénérer
                           if(!empty($crifiche->id_documents)){
                              if(PluginRpAccess::canUse('fiche', READ)){
                                 $ClientTitel = "Régénérer";
                              }else{$ClientTitel = "Générer";}
                           }else{
                              $ClientTitel = "Générer";
                           }

                           if(!empty($crifiche->id_documents)){

                              $usercrifiche = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 0 AND id_ticket= $ID")->fetch_object();
                              
                              if(PluginRpAccess::canUse('fiche', UPDATE) || empty($usercrifiche->users_id)){
                                 echo Html::submit($ClientTitel, ['name'    => 'showCriForm',
                                 'class'   => 'btn btn-primary',
                                 'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalclient\", " . json_encode($params) . "); return false;"]);
                              }
                           }else{
                              echo Html::submit($ClientTitel, ['name'    => 'showCriForm',
                              'class'   => 'btn btn-primary',
                              'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalclient\", " . json_encode($params) . "); return false;"]);
                           }
               }

               echo "</div>"; // actions
            echo "</div>"; // card-header

               if(PluginRpAccess::canUse('fiche', READ)){
                  self::showDocumentList($ID, 0, $rp_limit);
               }
         echo "</div>"; // card
      }

// __________________________________________ RAPPORT D'INTERVENTION __________________________________________
      // -------- bouton génération rapport -------
      $crirapport = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1")->fetch_object();

      if(PluginRpAccess::canUse('rapport_tech', CREATE) || PluginRpAccess::canUse('rapport_tech', READ)){
         // Bordure VERTE (#28a745)
        echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #28a745;'>";
            echo "<div class='card-header d-flex align-items-center justify-content-between'>";

               // ===== Titre avec trait vert, chevron accolé =====
               echo "<div class='d-flex align-items-center gap-2'>";
                  echo "<h3 class='card-title mb-0'>
                           <span class='rp-title' style='--rp-title-color:#28a745'>
                              <i class='fa-regular fa-file-lines me-2'></i>".
                              __("Rapport d'intervention", 'rp').
                           "</span>
                        </h3>";
                  echo self::signedBadge($rp_signed_type(1));
               echo "</div>";

               echo "<div class='d-flex align-items-center gap-2'>";

               if(PluginRpAccess::canUse('rapport_tech', CREATE)){
                  $modalrapport = 'form_rapport';

                  // GENERATE
                     $params = ['job'        => $ticket->fields['id'],
                              'root_doc'   => PLUGIN_RP_WEBDIR];

                     // --- Symétrie avec le plugin Gestion ---
                     // Si Gestion est actif ET qu'un BL non signé est associé au ticket, on
                     // ouvre le MÊME modal « Gestion BL » (radios « Signature Rapport » /
                     // « Signature Rapport + BL », défaut Rapport + BL) que depuis l'onglet
                     // Gestion. Sans Gestion (ou sans BL), comportement inchangé : rapport seul.
                     $rp_report_onclick = "rp_loadCriForm(\"showCriForm\", \"$modalrapport\", " . json_encode($params) . ");";
                     if (Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri')) {
                        $unsigned_bl = $DB->request([
                           'SELECT' => ['id'],
                           'FROM'   => 'glpi_plugin_gestion_surveys',
                           'WHERE'  => ['tickets_id' => $ID, 'signed' => 0],
                           'ORDER'  => ['id DESC'],
                           'LIMIT'  => 1,
                        ])->current();
                        if ($unsigned_bl) {
                           $gestion_bl_id  = (int)$unsigned_bl['id'];
                           $gestion_webdir = defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion');
                           /*
                            * `force_combined` : ce bouton EST celui du rapport.
                            *
                            * Sans cette précision, le plugin Gestion appliquait son
                            * choix par défaut — « Signature BL » dès qu'un rapport
                            * existait déjà — et cliquer « Régénérer » sur la carte
                            * Rapport ouvrait un formulaire qui ne régénérait aucun
                            * rapport. Le défaut de Gestion vaut quand on part d'un
                            * BL ; ici on part du rapport, l'intention est connue.
                            *
                            * Le choix reste affiché : le technicien peut basculer.
                            */
                           $gestion_params = [
                              'job'            => (int)$ID,
                              'root_doc'       => $gestion_webdir,
                              'root_modal'     => 'ticket-form',
                              'force_combined' => 1,
                           ];
                           $rp_report_onclick = "gestion_loadCriForm('showCriForm', '$gestion_bl_id', " . json_encode($gestion_params) . "); return false;";
                        }
                     }

                     // Libellé simplifié: Générer / Régénérer
                     if(!empty($crirapport->id_documents)){
                        if(PluginRpAccess::canUse('rapport_tech', READ)){
                           $RapportTitel = "Régénérer";
                        }else{$RapportTitel = "Générer";}
                     }else{
                        $RapportTitel = "Générer";
                     }

                     if(!empty($crirapport->id_documents)){

                        $usercrirapport = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 1 AND id_ticket= $ID")->fetch_object();
                           
                        if(PluginRpAccess::canUse('rapport_tech', UPDATE) || empty($usercrirapport->users_id)){
                           echo Html::submit($RapportTitel, ['name'    => 'showCriForm',
                           'class'   => 'btn btn-primary',
                           'onclick' => $rp_report_onclick]);
                        }
                     }else{
                        echo Html::submit($RapportTitel, ['name'    => 'showCriForm',
                        'class'   => 'btn btn-primary',
                        'onclick' => $rp_report_onclick]);
                     }
               }

               echo "</div>"; // actions
            echo "</div>"; // card-header

               if(PluginRpAccess::canUse('rapport_tech', READ)){
                  self::showDocumentList($ID, 1, $rp_limit);
               }
         echo "</div>"; // card
      }

// __________________________________________ RAPPORT HOTLINE __________________________________________
      // -------- bouton génération rapport HOTLINE-------

         $crirapporthotline = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2")->fetch_object();

         if(PluginRpAccess::canUse('rapport_hotline', CREATE) || PluginRpAccess::canUse('rapport_hotline', READ)){
            // Bordure JAUNE (#ffc107) — déjà présente
            echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #ffc107;'>";
               echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

                  // ===== Titre avec trait jaune, chevron accolé =====
                  echo "<div class='d-flex align-items-center gap-2'>";
                     echo "<h3 class='card-title mb-0'>
                              <span class='rp-title' style='--rp-title-color:#ffc107'>
                                 <i class='fa-regular fa-file-lines me-2'></i>".
                                 __("Rapport d'intervention hotline", 'rp').
                              "</span>
                           </h3>";
                     echo self::signedBadge($rp_signed_type(2));
                  echo "</div>";

                  echo "<div class='d-flex align-items-center gap-2'>";

                  if(PluginRpAccess::canUse('rapport_hotline', CREATE)){
                     $modalrapporthotline = 'form_rapport_hotline';

                     // GENERATE          
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           // Libellé simplifié: Générer / Régénérer
                           if(!empty($crirapporthotline->id_documents)){
                              if(PluginRpAccess::canUse('rapport_hotline', READ)){
                                 $RapportTitelHotline = "Régénérer";
                              }else{$RapportTitelHotline = "Générer";}
                           }else{
                              $RapportTitelHotline = "Générer";
                           }

                           if(!empty($crirapporthotline->id_documents)){

                              $usercrihotline = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 2 AND id_ticket= $ID")->fetch_object();
                              
                              if(PluginRpAccess::canUse('rapport_hotline', UPDATE) || empty($usercrihotline->users_id)){
                                 echo Html::submit($RapportTitelHotline, ['name'    => 'showCriForm',
                                 'class'   => 'btn btn-primary',
                                 'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapporthotline\", " . json_encode($params) . ");"]);
                              }
                           }else{
                              echo Html::submit($RapportTitelHotline, ['name'    => 'showCriForm',
                              'class'   => 'btn btn-primary',
                              'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapporthotline\", " . json_encode($params) . ");"]);
                           }
                  }

                  echo "</div>"; // actions
               echo "</div>"; // card-header

                  if(PluginRpAccess::canUse('rapport_hotline', READ)){
                     self::showDocumentList($ID, 2, $rp_limit);
                  }
            echo "</div>"; // card
         }

// __________________________________________ RAPPORT DE PREPARATION __________________________________________
      // -------- bouton génération rapport de préparation (atelier) -------

         $criprep = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=3")->fetch_object();

         if(PluginRpAccess::canUse('preparation', CREATE) || PluginRpAccess::canUse('preparation', READ)){
            // Bordure VIOLETTE (#6f42c1)
            echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #6f42c1;'>";
               echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

                  // ===== Titre avec trait violet, chevron accolé =====
                  echo "<div class='d-flex align-items-center gap-2'>";
                     echo "<h3 class='card-title mb-0'>
                              <span class='rp-title' style='--rp-title-color:#6f42c1'>
                                 <i class='fa-solid fa-screwdriver-wrench me-2'></i>".
                                 __("Rapport d'atelier", 'rp').
                              "</span>
                           </h3>";
                     echo self::signedBadge($rp_signed_type(3));
                     // L'intervention qui conclut l'atelier est listée sous cette
                     // carte : son état de signature se lit donc ici aussi.
                     echo self::signedBadge($rp_signed_type(1), __('Intervention signée', 'rp'));
                  echo "</div>";

                  echo "<div class='d-flex align-items-center gap-2'>";

                  if(PluginRpAccess::canUse('preparation', CREATE)){
                     $modalpreparation = 'form_preparation';
                     $params = ['job'      => $ticket->fields['id'],
                                'root_doc' => PLUGIN_RP_WEBDIR];

                     if(!empty($criprep->id_documents)){
                        $PrepTitel = "Régénérer";
                     }else{
                        $PrepTitel = "Générer";
                     }

                     if(!empty($criprep->id_documents)){
                        $usercriprep = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 3 AND id_ticket= $ID")->fetch_object();

                        if(PluginRpAccess::canUse('preparation', UPDATE) || empty($usercriprep->users_id)){
                           echo Html::submit($PrepTitel, ['name'    => 'showCriForm',
                           'class'   => 'btn btn-primary',
                           'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalpreparation\", " . json_encode($params) . "); return false;"]);
                        }
                     }else{
                        echo Html::submit($PrepTitel, ['name'    => 'showCriForm',
                        'class'   => 'btn btn-primary',
                        'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalpreparation\", " . json_encode($params) . "); return false;"]);
                     }
                  }

                  echo "</div>"; // actions
               echo "</div>"; // card-header

                  if(PluginRpAccess::canUse('preparation', READ) || PluginRpAccess::canUse('preparation', CREATE)){
                     self::showDocumentList($ID, 3, $rp_limit);

                     /*
                      * Les rapports d'intervention, AUSSI sous l'atelier.
                      *
                      * Un rapport d'atelier pur n'est pas un rapport d'intervention ;
                      * mais celui produit en conclusion de l'atelier (« le client
                      * repart avec », QR code) est exactement le même document que
                      * celui de la carte « Rapport d'intervention ». Le technicien
                      * atelier, qui a le droit de le produire sans avoir cette
                      * carte, doit pouvoir voir qu'il existe et qu'il est signé.
                      * Même limite d'affichage que les autres cartes (réglage
                      * « Enregistrement de plusieurs rapports »). Le bloc n'apparaît
                      * que s'il y a quelque chose à montrer : pas de « Aucun
                      * rapport » ici, la carte a déjà le sien.
                      */
                     if (!empty($crirapport->id_documents)) {
                        // Le dernier rapport d'intervention et son auteur : le
                        // sous-titre dit QUI a conclu, pas seulement qu'on a conclu.
                        $rp_last_inter = $DB->request([
                           'SELECT' => ['users_id'],
                           'FROM'   => 'glpi_plugin_rp_cridetails',
                           'WHERE'  => ['id_ticket' => $ID, 'type' => 1],
                           'ORDER'  => ['date DESC'],
                           'LIMIT'  => 1,
                        ])->current();
                        $rp_author = (int)($rp_last_inter['users_id'] ?? 0);
                        $rp_title  = $rp_author > 0
                           ? sprintf(__("Rapport d'intervention - généré par %s", 'rp'), getUserName($rp_author))
                           : __("Rapport d'intervention", 'rp');
                        echo "<div class='px-3 pt-3 pb-1 text-secondary small text-uppercase fw-bold'>"
                           . "<i class='ti ti-file-check me-1'></i>"
                           . htmlspecialchars($rp_title, ENT_QUOTES)
                           . "</div>";
                        self::showDocumentList($ID, 1, $rp_limit);
                     }
                  }
            echo "</div>"; // card
         }
   }
}
