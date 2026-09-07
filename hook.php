<?php
function plugin_rp_install() {
   global $DB;

   include_once(PLUGIN_RP_DIR . "/inc/profile.class.php");
   include_once(PLUGIN_RP_DIR . "/inc/cridetail.class.php");
   include_once(PLUGIN_RP_DIR . "/inc/config.class.php");

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp/fiches";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp/rapports";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsHotline";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp/logo";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsMass";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsPreparation";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   PluginRpProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   PluginRpProfile::initProfile();
   
   $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_rp_profiles`;") or die($DB->error());

   $query= "CREATE TABLE IF NOT EXISTS `glpi_plugin_rp_dataclient` ( 
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , 
      `id_ticket` INT(11), 
      `society` VARCHAR(100), 
      `address` VARCHAR(255),
      `town` VARCHAR(100), 
      `postcode` VARCHAR(100), 
      `phone` VARCHAR(100), 
      `email` VARCHAR(255),
      `serial_number` VARCHAR(255),
      PRIMARY KEY (`id`) ,
      UNIQUE KEY (`id_ticket`) 
      ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   $DB->doQuery($query) or die($DB->error());

   $query= "CREATE TABLE IF NOT EXISTS `glpi_plugin_rp_cridetails` ( 
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , 
      `id_ticket` INT(11), 
      `id_documents` int(11),
      `type` int(11),
      `nameclient` VARCHAR(255),
      `email` VARCHAR(255),
      `send_mail` int(11),
      `date` TIMESTAMP,
      `users_id` int UNSIGNED,
      `id_task` int(11) NULL,
      PRIMARY KEY (`id`) 
      ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   $DB->doQuery($query) or die($DB->error());

   $query= "CREATE TABLE IF NOT EXISTS `glpi_plugin_rp_signtech` ( 
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,  
      `user_id` INT UNSIGNED,
      `seing` MEDIUMTEXT,
      `version` tinyint(4) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`),
      UNIQUE KEY (`user_id`)
      ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   $DB->doQuery($query) or die($DB->error());

   // BDD CONFIG
      // version interne de migration (mets la tienne)
      $migration = new Migration('3.1.0_GLPI_11_RP_plugin');

      // --- 1) Création de table via Migration (PAS de queryOrDie direct) ---
      if (!$DB->tableExists('glpi_plugin_rp_configs')) {
         $migration->addPostQuery(
            "CREATE TABLE `glpi_plugin_rp_configs` (
               `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
               `time` TINYINT(1) DEFAULT 0,
               `time_hotl` TINYINT(1) DEFAULT 0,
               `multi_doc` TINYINT(1) DEFAULT 0,
               `date` TINYINT(1) DEFAULT 0,
               `multi_display` INT UNSIGNED DEFAULT 0,
               `use_publictask` TINYINT(1) DEFAULT 0,
               `use_publictask_massaction` TINYINT(1) DEFAULT 0,
               `choice` TINYINT(1) DEFAULT 0,
               `update_task_on_generate` TINYINT(1) DEFAULT 0,
               `check_private_suivi` TINYINT(1) DEFAULT 0,
               `check_public_suivi` TINYINT(1) DEFAULT 0,
               `check_private_task` TINYINT(1) DEFAULT 0,
               `check_public_task` TINYINT(1) DEFAULT 0,
               `sign_rp_charge` TINYINT(1) DEFAULT 0,
               `sign_rp_tech` TINYINT(1) DEFAULT 0,
               `sign_rp_hotl` TINYINT(1) DEFAULT 0,
               `email` TINYINT(1) DEFAULT 0,
               `titel_pc` VARCHAR(255) NULL,
               `titel_rt` VARCHAR(255) NULL,
               `titel_rh` VARCHAR(255) NULL,
               `line1` VARCHAR(255) NULL,
               `line2` VARCHAR(255) NULL,
               `margin_left` INT UNSIGNED DEFAULT 0,
               `margin_top` INT UNSIGNED DEFAULT 0,
               `cut` INT UNSIGNED DEFAULT 0,
               `logo_id` INT UNSIGNED NULL,
               `token` VARCHAR(255) NULL,
               `ImgTasks` TINYINT(1) DEFAULT 0,
               `ImgSuivis` TINYINT(1) DEFAULT 0,
               `gabarit` INT UNSIGNED DEFAULT 0,
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
         );
      } else {
         // --- 2) Normalisations si table déjà existante (migration depuis GLPI 10) ---
         $migration->addPreQuery(
            "UPDATE `glpi_plugin_rp_configs`
                SET `logo_id` = NULL
              WHERE `logo_id` IS NOT NULL
                AND (`logo_id` NOT REGEXP '^[0-9]+$')"
         );

         $migration->changeField('glpi_plugin_rp_configs', 'id',            'id',            'autoincrement');
         $migration->changeField('glpi_plugin_rp_configs', 'logo_id',       'logo_id',       'INT UNSIGNED DEFAULT NULL');
         $migration->changeField('glpi_plugin_rp_configs', 'multi_display', 'multi_display', 'INT UNSIGNED NOT NULL DEFAULT 0');
         $migration->changeField('glpi_plugin_rp_configs', 'margin_left',   'margin_left',   'INT UNSIGNED NOT NULL DEFAULT 0');
         $migration->changeField('glpi_plugin_rp_configs', 'margin_top',    'margin_top',    'INT UNSIGNED NOT NULL DEFAULT 0');
         $migration->changeField('glpi_plugin_rp_configs', 'cut',           'cut',           'INT UNSIGNED NOT NULL DEFAULT 0');
         $migration->changeField('glpi_plugin_rp_configs', 'gabarit',       'gabarit',       'INT UNSIGNED NOT NULL DEFAULT 0');
      }

      // Exécute la migration (crée/altère réellement la table)
      $migration->executeMigration();

      // --- 3) Seed par défaut (API DB GLPI) ---
      if (!countElementsInTable('glpi_plugin_rp_configs')) {
         $DB->insert('glpi_plugin_rp_configs', [
            'time'                      => 1,
            'time_hotl'                 => 0,
            'multi_doc'                 => 0,
            'date'                      => 0,
            'multi_display'             => 0,
            'use_publictask'            => 0,
             'use_publictask_massaction' => 1,
             'choice'                    => 1,
             'update_task_on_generate'   => 0,
             'check_private_suivi'       => 0,
            'check_public_suivi'        => 0,
            'check_private_task'        => 0,
            'check_public_task'         => 1,
            'sign_rp_charge'            => 1,
            'sign_rp_tech'              => 1,
            'sign_rp_hotl'              => 0,
            'email'                     => 1,
            'titel_pc'                  => "FICHE DE PRISE EN CHARGE",
            'titel_rt'                  => "RAPPORT D'INTERVENTION",
            'titel_rh'                  => "RAPPORT",
            'line1'                     => "193 rue du général metman, 57070 Metz",
            'line2'                     => "03 87 18 49 20",
            'margin_left'               => 21,
            'margin_top'                => 15,
            'cut'                       => 27,
            'logo_id'                   => null,
            'token'                     => null,
            'ImgTasks'                  => 1,
            'ImgSuivis'                 => 0,
            'gabarit'                   => 0,
         ]);
      }

      //install 3.0.0
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '2.3.0'){
         include(PLUGIN_RP_DIR . "/install/install_300.php");
         install300(); 
      }

      //update 2.3.0 to 3.0.0
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '2.3.0'){
         include(PLUGIN_RP_DIR . "/install/update_230_300.php");
         update230to300(); 
      }

      //update 3.0.6 to next
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '3.0.5'){
         include(PLUGIN_RP_DIR . "/install/update_306_next.php");
         update_306_next(); 
      }

      //update 3.1.0 to next
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '3.0.9'){
         include(PLUGIN_RP_DIR . "/install/update_310_next.php");
         update_310_next();
      }

      //update 3.2.3 : reparation filepath des Documents (blacklist GLPI 11)
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '3.2.2'){
         include(PLUGIN_RP_DIR . "/install/update_323_next.php");
         update_323_next();
      }

      //update 3.3.0 : mise à jour unique — accès individuels, rapport de préparation,
      //QR code, interface mobile, tableau des rapports et boutons flottants
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '3.2.3'){
         include(PLUGIN_RP_DIR . "/install/update_323_330.php");
         update_323_330();
      }

      //update 3.3.1 : groupe de livraison, rattrapage des chartes de rapport,
      //file d'attente des signatures hors-ligne
      // NB : la garde compare à la version PRÉCÉDENTE, comme les blocs au-dessus.
      // Comparaison de CHAÎNES : rester en 3.3.x ou 3.4.0, car '3.10.0' > '3.3.0'
      // serait faux.
      if($DB->tableExists("glpi_plugin_rp_configs") && $_SESSION['PLUGIN_RP_VERSION'] > '3.3.0'){
         include(PLUGIN_RP_DIR . "/install/update_330_331.php");
         update_330_331();
      }
   // BDD CONFIG

   return true;
}

function plugin_rp_uninstall() {
   global $DB;

   include_once(PLUGIN_RP_DIR . "/inc/profile.class.php");

   PluginRpProfile::removeRightsFromSession();
   PluginRpProfile::removeRightsFromDB();

   $tables = ["glpi_plugin_rp_dataclient",
              "glpi_plugin_rp_cridetails",
              "glpi_plugin_rp_configs",
              "glpi_plugin_rp_signtech",
              "glpi_plugin_rp_accessrules",
              "glpi_plugin_rp_preparations",
              "glpi_plugin_rp_userprefs",
              "glpi_plugin_rp_chartes",
              "glpi_plugin_rp_offline_queue"];

   foreach ($tables as $table)
      $DB->doQuery("DROP TABLE IF EXISTS `$table`;");

   $notifications_templates = $DB->doQuery("SELECT * FROM glpi_notificationtemplates WHERE comment = 'Created by the plugin RP';");
   while ($notification_template = $DB->fetchArray($notifications_templates)) {
      $id_notificationtemplates = $notification_template['id'];

      $DB->doQuery("DELETE FROM `glpi_notificationtemplatetranslations` WHERE `notificationtemplates_id` = $id_notificationtemplates;");
   }
   $tables_glpi = ["glpi_notificationtemplates"];
   foreach ($tables_glpi as $table_glpi) {
      $DB->doQuery("DELETE FROM `$table_glpi` WHERE `comment` = 'Created by the plugin RP';");
   }

   return true;
}

function plugin_rp_postinit() {
   global $PLUGIN_HOOKS;

   $plugin = 'rp';
   foreach (['add_css', 'add_javascript'] as $type) {
      if (isset($PLUGIN_HOOKS[$type][$plugin])) {
         foreach ($PLUGIN_HOOKS[$type][$plugin] as $data) {
            if (!empty($PLUGIN_HOOKS[$type])) {
               foreach ($PLUGIN_HOOKS[$type] as $key => $plugins_data) {
                  if (is_array($plugins_data) && $key != $plugin) {
                     foreach ($plugins_data as $key2 => $values) {
                        if ($values == $data) {
                           unset($PLUGIN_HOOKS[$type][$key][$key2]);
                        }
                     }
                  }
               }
            }
         }
      }
   }

   /*
    * Lien mobile : passer AVANT les autres plugins dans `post_item_form`.
    *
    * Les plugins Credit et Gestion rendent leur bloc du panneau ticket en
    * FERMANT la section « Ticket » de GLPI pour ouvrir la leur (section
    * accordéon laissée ouverte, que le gabarit du noyau referme). Tout ce
    * qu'un plugin écrit après eux tombe donc DANS leur bloc : le champ
    * « Lien mobile » s'affichait sous « Option par défaut pour le crédit ».
    *
    * L'ordre d'appel des hooks est celui du chargement des plugins (ordre de
    * la table glpi_plugins), sur lequel RP n'a pas la main. Ce hook post_init
    * s'exécute une fois TOUS les plugins initialisés : RP se replace en tête,
    * et son champ suit directement « ID externe », dans la section « Ticket ».
    */
   if (isset($PLUGIN_HOOKS['post_item_form']['rp'])) {
      $PLUGIN_HOOKS['post_item_form'] =
         ['rp' => $PLUGIN_HOOKS['post_item_form']['rp']] + $PLUGIN_HOOKS['post_item_form'];
   }

   /*$PLUGIN_HOOKS['item_purge']['rp']["Document"]
      = ['PluginRpEntityLogo', 'cleanForItem'];*/
}

/**
 * Hook auto GLPI (giveItem) : rendu des cellules du moteur de recherche pour le
 * tableau « Rapport PDF » (PluginRpCriDetail). Retourner '' laisse le rendu
 * standard (getSpecificValueToDisplay) s'appliquer — c'est le cas des exports.
 */
function plugin_rp_giveItem($itemtype, $orig_id, $data, $num) {
   global $CFG_GLPI;

   if ($itemtype !== 'PluginRpCriDetail') {
      return '';
   }

   // Colonne « Ticket » (option 3) : id cliquable vers le ticket
   if ((int)$orig_id === 3) {
      if (Search::$output_type != Search::HTML_OUTPUT) {
         return '';
      }
      $tickets_id = (int)($data[$num][0]['name'] ?? 0);
      if ($tickets_id <= 0) {
         return ' ';
      }
      $ticket = new Ticket();
      if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
         return '#' . sprintf('%07d', $tickets_id);
      }
      return '<a href="' . htmlspecialchars($ticket->getLinkURL(), ENT_QUOTES) . '">#'
         . sprintf('%07d', $tickets_id) . ' - '
         . htmlspecialchars((string)$ticket->fields['name'], ENT_QUOTES) . '</a>';
   }

   // Colonne « Document » (option 9) : nom du PDF, cliquable vers la fiche
   // Document de GLPI (document.form.php), pas vers le téléchargement du fichier
   // — c'est le bouton « Visualiser » (option 14) qui ouvre le PDF lui-même.
   if ((int)$orig_id === 9) {
      if (Search::$output_type != Search::HTML_OUTPUT) {
         return '';
      }
      $doc_id = (int)($data[$num][0]['name'] ?? 0);
      if ($doc_id <= 0) {
         return '-';
      }
      $doc = new Document();
      if (!$doc->getFromDB($doc_id)) {
         return __('Document supprimé', 'rp');
      }
      $filename = htmlspecialchars((string)$doc->fields['filename'], ENT_QUOTES);
      if (!$doc->canViewItem()) {
         return $filename;
      }
      return '<a href="' . htmlspecialchars(Document::getFormURLWithID($doc_id), ENT_QUOTES) . '">'
         . '<i class="far fa-file-pdf me-1"></i>' . $filename . '</a>';
   }

   // Colonne « Visualiser » (option 14) : bouton d'ouverture du PDF
   if ((int)$orig_id === 14) {
      $doc_id = (int)($data[$num][0]['name'] ?? 0);

      // Exports (CSV, PDF...) : texte simple, pas de HTML.
      if (Search::$output_type != Search::HTML_OUTPUT) {
         return $doc_id > 0 ? __('Disponible', 'rp') : __('Document supprimé', 'rp');
      }

      $doc = new Document();
      if ($doc_id <= 0 || !$doc->getFromDB($doc_id)) {
         return '<span class="text-muted"><i class="ti ti-file-off me-1"></i>'
            . __s('Document supprimé', 'rp') . '</span>';
      }

      return '<a class="btn btn-sm btn-primary" target="_blank" href="'
         . $CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . $doc_id
         . '" title="' . __s('Ouvrir le rapport PDF', 'rp') . '">'
         . '<i class="ti ti-eye me-1"></i>' . __s('Visualiser', 'rp') . '</a>';
   }

   return '';
}

function plugin_rp_MassiveActions($type) {
   global $PLUGIN_HOOKS;

   switch ($type) {
      default :
         if (isset($PLUGIN_HOOKS['plugin_rp'][$type])) {
            return ['PluginRpCommon'.MassiveAction::CLASS_ACTION_SEPARATOR.'DoIt'
                     => __('Rapport PDF', 'rp')];
         }
   }
   return [];
}
