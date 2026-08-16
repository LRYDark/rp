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
         3 => __('Rapport de préparation', 'rp'),
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
          && (PluginRpAccess::canUse('rapport_tech', READ)
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
      if (Session::haveRight("plugin_rp_rapport_tech", READ)
          || Session::haveRight("plugin_rp_rapport_hotline", READ)
          || Session::haveRight("plugin_rp_rapport_preparation", READ)) {
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

         if($config->fields['multi_display'] != 0){
            $multi_display = "ORDER BY date DESC LIMIT ".$config->fields['multi_display'];
         }else{
            $multi_display = "ORDER BY date DESC LIMIT 1";
         }      

// __________________________________________ FICHE DE PRISE EN CHARGE __________________________________________
      // ----- bouton génération fiche client -----  
      $crifiche = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0")->fetch_object();

      if(PluginRpAccess::canUse('rapport_tech', CREATE) || Session::haveRight("plugin_rp_rapport_tech", READ)){
         // Bordure BLEUE (#007bff)
         echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #007bff;'>";
            echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

               // ===== Titre avec trait bleu =====
               echo "<h3 class='card-title mb-0'>
                        <span class='rp-title' style='--rp-title-color:#007bff'>
                           <i class='fa-regular fa-file-lines me-2'></i>".
                           __("Fiche de prise en charge", 'rp').
                        "</span>
                     </h3>";

               if(PluginRpAccess::canUse('rapport_tech', CREATE)){
                  $modalclient = 'form_client';
                  
                     // GENERATE        
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           // Libellé simplifié: Générer / Régénérer
                           if(!empty($crifiche->id_documents)){
                              if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                                 $ClientTitel = "Régénérer";
                              }else{$ClientTitel = "Générer";}
                           }else{
                              $ClientTitel = "Générer";
                           }

                           if(!empty($crifiche->id_documents)){

                              $usercrifiche = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 0 AND id_ticket= $ID")->fetch_object();
                              
                              if(PluginRpAccess::canUse('rapport_tech', UPDATE) || empty($usercrifiche->users_id)){
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
            echo "</div>"; // card-header

            echo "<div class='card-body'>";
               // __________________________________________
               if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                  if(empty($crifiche->id_documents)){
                     echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucune fiche de prise en charge générée !</div>";
                  }else{       
                     echo "<div class='table-responsive'>";
                        echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                           echo "<thead class='table-light'>";
                              echo "<tr>";
                                 echo "<th style='width:160px'>Date de création</th>";
                                 echo "<th style='width:150px'>Nom du signataire</th>";
                                 echo "<th style='width:230px'>Envoyer à</th>";
                                 echo "<th style='width:110px'>Fichier</th>";
                                 echo "<th>Nom du fichier</th>";
                              echo "</tr>";
                           echo "</thead>";
                           echo "<tbody>";
                                                      
                           $docdatafiche = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0 $multi_display";
                           $docdatafiche = $DB->doQuery($docdatafiche);
                     
                           while ($data = $DB->fetchArray($docdatafiche)) {
                                 $iddoc = $data["id_documents"]; 
                                 if(empty($data["email"])) {
                                    $data["email"] = "-";
                                 }
                                 $docfiche = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                         INNER JOIN `glpi_documents` 
                                                         ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                         WHERE id_documents = $iddoc")->fetch_object();
                     
                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";
                                    echo "<td>". $data["email"] ."</td>";

                                       if(empty($docfiche->filename)){
                                          echo "<td class='text-muted'>Document supprimé</td>";
                                          echo "<td class='text-muted'>-</td>";
                                       }else{
                                          $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/fiches/" . $docfiche->filename;
                                          if(file_exists($seepath)){
                                             echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a href='document.form.php?id=$iddoc'>". $docfiche->filename  ."</a></td>";
                                          }
                                          else{
                                             echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $docfiche->filename ."</a></td>";
                                          }
                                       }
                                 echo "</tr>";
                              }   

                           echo "</tbody>";
                        echo "</table>";
                     echo "</div>";
                  }
               }
            echo "</div>"; // card-body
         echo "</div>"; // card
      }

// __________________________________________ RAPPORT D'INTERVENTION __________________________________________
      // -------- bouton génération rapport -------
      $crirapport = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1")->fetch_object();

      if(PluginRpAccess::canUse('rapport_tech', CREATE) || Session::haveRight("plugin_rp_rapport_tech", READ)){
         // Bordure VERTE (#28a745)
        echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #28a745;'>";
            echo "<div class='card-header d-flex align-items-center justify-content-between'>";

               // ===== Titre avec trait vert =====
               echo "<h3 class='card-title mb-0'>
                        <span class='rp-title' style='--rp-title-color:#28a745'>
                           <i class='fa-regular fa-file-lines me-2'></i>".
                           __("Rapport d'intervention", 'rp').
                        "</span>
                     </h3>";

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
                           $gestion_params = ['job' => (int)$ID, 'root_doc' => $gestion_webdir, 'root_modal' => 'ticket-form'];
                           $rp_report_onclick = "gestion_loadCriForm('showCriForm', '$gestion_bl_id', " . json_encode($gestion_params) . "); return false;";
                        }
                     }

                     // Libellé simplifié: Générer / Régénérer
                     if(!empty($crirapport->id_documents)){
                        if(Session::haveRight("plugin_rp_rapport_tech", READ)){
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
            echo "</div>";

            echo "<div class='card-body'>";
               // __________________________________________
               if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                  if(empty($crirapport->id_documents)){
                     echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucun rapport de généré !</div>";
                  }else{          
                     echo "<div class='table-responsive'>";
                        echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                           echo "<thead class='table-light'>";
                              echo "<tr>";
                                 echo "<th style='width:160px'>Date de création</th>";
                                 echo "<th style='width:150px'>Nom du signataire</th>";
                                 echo "<th style='width:230px'>Envoyer à</th>";
                                 echo "<th style='width:110px'>Fichier</th>";
                                 echo "<th>Nom du fichier</th>";
                              echo "</tr>";
                           echo "</thead>";
                           echo "<tbody>";

                           $docdatarapport = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1 $multi_display";
                           $docdatarapport = $DB->doQuery($docdatarapport);
                     
                           while ($data = $DB->fetchArray($docdatarapport)) {
                                 $iddoc = $data["id_documents"]; 
                                 if(empty($data["email"])) {
                                    $data["email"] = "-";
                                 }
                                 $docrapport = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                         INNER JOIN `glpi_documents` 
                                                         ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                         WHERE id_documents = $iddoc")->fetch_object();
                     
                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";
                                    echo "<td>". $data["email"] ."</td>";

                                       if(empty($docrapport->filename)){
                                          echo "<td class='text-muted'>Document supprimé</td>";
                                          echo "<td class='text-muted'>-</td>";
                                       }else{
                                          $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapports/" . $docrapport->filename;
                                          $seepathMassAction = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsMass/" . $docrapport->filename;
                                          if(file_exists($seepath) || file_exists($seepathMassAction)){
                                             echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a href='document.form.php?id=$iddoc'>". $docrapport->filename  ."</a></td>";
                                          }
                                          else{
                                             echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $docrapport->filename ."</a></td>";
                                          }
                                       }
                                 echo "</tr>";
                           }   

                           echo "</tbody>";
                        echo "</table>";
                     echo "</div>";
                  }
               }
            echo "</div>";
         echo "</div>";
      }

// __________________________________________ RAPPORT HOTLINE __________________________________________
      // -------- bouton génération rapport HOTLINE-------

         $crirapporthotline = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2")->fetch_object();

         if(PluginRpAccess::canUse('rapport_hotline', CREATE) || Session::haveRight("plugin_rp_rapport_hotline", READ)){
            // Bordure JAUNE (#ffc107) — déjà présente
            echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #ffc107;'>";
               echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

                  // ===== Titre avec trait jaune =====
                  echo "<h3 class='card-title mb-0'>
                           <span class='rp-title' style='--rp-title-color:#ffc107'>
                              <i class='fa-regular fa-file-lines me-2'></i>".
                              __("Rapport d'intervention hotline", 'rp').
                           "</span>
                        </h3>";

                  if(PluginRpAccess::canUse('rapport_hotline', CREATE)){
                     $modalrapporthotline = 'form_rapport_hotline';

                     // GENERATE          
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           // Libellé simplifié: Générer / Régénérer
                           if(!empty($crirapporthotline->id_documents)){
                              if(Session::haveRight("plugin_rp_rapport_hotline", READ)){
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
               echo "</div>";

               echo "<div class='card-body'>";
                  // __________________________________________
                  if(Session::haveRight("plugin_rp_rapport_hotline", READ)){
                     if(empty($crirapporthotline->id_documents)){
                        echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucun rapport de généré !</div>";
                     }else{          
                        echo "<div class='table-responsive'>";
                           echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                              echo "<thead class='table-light'>";
                                 echo "<tr>";
                                    echo "<th style='width:160px'>Date de création</th>";
                                    echo "<th style='width:150px'>Nom du technicien</th>";
                                    echo "<th style='width:230px'>Envoyer à</th>";
                                    echo "<th style='width:110px'>Fichier</th>";
                                    echo "<th>Nom du fichier</th>";
                                 echo "</tr>";
                              echo "</thead>";
                              echo "<tbody>";

                              $docdatahotline = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2 $multi_display";
                              $docdatahotline = $DB->doQuery($docdatahotline);
                        
                              while ($data = $DB->fetchArray($docdatahotline)) {
                                    $iddoc = $data["id_documents"]; 
                                    if(empty($data["email"])) {
                                       $data["email"] = "-";
                                    }
                                    $dochotline = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                            INNER JOIN `glpi_documents` 
                                                            ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                            WHERE id_documents = $iddoc")->fetch_object();
                        
                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";
                                    echo "<td>". $data["email"] ."</td>";

                                       if(empty($dochotline->filename)){
                                          echo "<td class='text-muted'>Document supprimé</td>";
                                          echo "<td class='text-muted'>-</td>";
                                       }else{
                                          $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsHotline/" . $dochotline->filename;
                                          $seepathMassAction = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsMass/" . $dochotline->filename;
                                          if(file_exists($seepath) || file_exists($seepathMassAction)){
                                             echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a href='document.form.php?id=$iddoc'>". $dochotline->filename  ."</a></td>";
                                          }
                                          else{
                                             echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $dochotline->filename ."</a></td>";
                                          }
                                       }
                                 echo "</tr>";
                              }   

                              echo "</tbody>";
                           echo "</table>";
                        echo "</div>";
                     }
                  }
               echo "</div>";
            echo "</div>";
         }

// __________________________________________ RAPPORT DE PREPARATION __________________________________________
      // -------- bouton génération rapport de préparation (atelier) -------

         $criprep = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=3")->fetch_object();

         if(PluginRpAccess::canUse('preparation', CREATE) || Session::haveRight("plugin_rp_rapport_preparation", READ)){
            // Bordure VIOLETTE (#6f42c1)
            echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #6f42c1;'>";
               echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

                  echo "<h3 class='card-title mb-0'>
                           <span class='rp-title' style='--rp-title-color:#6f42c1'>
                              <i class='fa-solid fa-screwdriver-wrench me-2'></i>".
                              __("Rapport de préparation", 'rp').
                           "</span>
                        </h3>";

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
               echo "</div>";

               echo "<div class='card-body'>";
                  if(Session::haveRight("plugin_rp_rapport_preparation", READ) || PluginRpAccess::canUse('preparation', CREATE)){
                     if(empty($criprep->id_documents)){
                        echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucun rapport de préparation généré !</div>";
                     }else{
                        echo "<div class='table-responsive'>";
                           echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                              echo "<thead class='table-light'>";
                                 echo "<tr>";
                                    echo "<th style='width:160px'>Date de création</th>";
                                    echo "<th style='width:150px'>Technicien atelier</th>";
                                    echo "<th style='width:110px'>Fichier</th>";
                                    echo "<th>Nom du fichier</th>";
                                 echo "</tr>";
                              echo "</thead>";
                              echo "<tbody>";

                              $docdataprep = $DB->doQuery("SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=3 $multi_display");

                              while ($data = $DB->fetchArray($docdataprep)) {
                                 $iddoc = (int)$data["id_documents"];
                                 $docprep = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                         INNER JOIN `glpi_documents`
                                                         ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`)
                                                         WHERE id_documents = $iddoc")->fetch_object();

                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";

                                    if(empty($docprep->filename)){
                                       echo "<td class='text-muted'>Document supprimé</td>";
                                       echo "<td class='text-muted'>-</td>";
                                    }else{
                                       $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsPreparation/" . $docprep->filename;
                                       if(file_exists($seepath)){
                                          echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                          echo "<td><a href='document.form.php?id=$iddoc'>". $docprep->filename ."</a></td>";
                                       }else{
                                          echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                          echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $docprep->filename ."</a></td>";
                                       }
                                    }
                                 echo "</tr>";
                              }

                              echo "</tbody>";
                           echo "</table>";
                        echo "</div>";
                     }
                  }
               echo "</div>";
            echo "</div>";
         }
   }
}
