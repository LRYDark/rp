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

   PluginRpProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   PluginRpProfile::initProfile();
   $DB->query("DROP TABLE IF EXISTS `glpi_plugin_rp_profiles`;") or die($DB->error());

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
   $DB->query($query) or die($DB->error());

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
   $DB->query($query) or die($DB->error());

   $query= "CREATE TABLE IF NOT EXISTS `glpi_plugin_rp_signtech` ( 
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,  
      `user_id` INT UNSIGNED,
      `seing` TEXT,
      PRIMARY KEY (`id`),
      UNIQUE KEY (`user_id`)
      ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   $DB->query($query) or die($DB->error());

   // BDD CONFIG
      if (!$DB->tableExists("glpi_plugin_rp_configs")) {
         $query= "CREATE TABLE IF NOT EXISTS `glpi_plugin_rp_configs` ( 
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , 
            `time` TINYINT(1),
            `time_hotl` TINYINT(1),
            `multi_doc` TINYINT(1),
            `date` TINYINT(1),
            `multi_display` INT(10),
            `use_publictask` TINYINT(1), 
            `choice` TINYINT(1),
            `check_private_suivi` TINYINT(1),
            `check_public_suivi` TINYINT(1),
            `check_private_task` TINYINT(1),
            `check_public_task` TINYINT(1),
            `sign_rp_charge` TINYINT(1),
            `sign_rp_tech` TINYINT(1),
            `sign_rp_hotl` TINYINT(1),
            `email` TINYINT(1),
            `titel_pc` varchar(255),
            `titel_rt` varchar(255),
            `titel_rh` varchar(255),
            `line1` varchar(255),
            `line2` varchar(255),
            `margin_left` INT(10),
            `margin_top` INT(10),
            `cut` INT(10),
            `logo_id` INT(10) NULL,
            `token` varchar(255) NULL,
            `ImgTasks` TINYINT(1),
            `ImgSuivis` TINYINT(1),
            PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
         $DB->query($query) or die($DB->error());

         $query= "INSERT INTO `glpi_plugin_rp_configs` (`time`, `time_hotl`, `multi_doc`, `date`, `multi_display`, `use_publictask`, `choice`, `check_private_suivi`, `check_public_suivi`, `check_private_task`, `check_public_task`, `sign_rp_charge`, `sign_rp_tech`, `sign_rp_hotl`, `email`, `titel_pc`, `titel_rt`, `titel_rh`, `line1`, `line2`, `margin_left`, `margin_top`, `cut`, `logo_id`, `token`, `ImgTasks`, `ImgSuivis`) 
            VALUES (1 ,0 ,0 ,0 ,0 ,0 ,1 ,0 ,0 ,0 ,1 ,1 ,1 ,0 ,1,'FICHE DE PRISE EN CHARGE','RAPPORT D\\'INTERVENTION','RAPPORT','193 rue du général metman, 57070 Metz','03 87 18 49 20',21,15,27,NULL,NULL,1,0);";
         $DB->query($query) or die($DB->error());
      }else{
         //******************************************************************************* */
            /*$query= "ALTER TABLE glpi_plugin_rp_configs ADD ImgTasks TINYINT(1)";
            $DB->query($query) or die($DB->error()); // pour version 2.1.0
            $query= "ALTER TABLE glpi_plugin_rp_configs ADD ImgSuivis TINYINT(1)";
            $DB->query($query) or die($DB->error()); // pour version 2.1.0

            $query= "UPDATE glpi_plugin_rp_configs SET ImgTasks = 1 WHERE id=1";
            $DB->query($query) or die($DB->error());// pour version 2.1.0
            $query= "UPDATE glpi_plugin_rp_configs SET ImgSuivis = 0 WHERE id=1";
            $DB->query($query) or die($DB->error());// pour version 2.1.0

            $query= "ALTER TABLE glpi_plugin_rp_configs ADD token varchar(255) NULL";
            $DB->query($query) or die($DB->error()); // pour version 2.1.0*/


            /*$query= "ALTER TABLE glpi_plugin_rp_configs ADD check_private_suivi TINYINT(1)";
            $DB->query($query) or die($DB->error());
            $query= "ALTER TABLE glpi_plugin_rp_configs ADD check_public_suivi TINYINT(1)";
            $DB->query($query) or die($DB->error());

            $query= "ALTER TABLE glpi_plugin_rp_configs CHANGE check_private check_private_task TINYINT(1)";
            $DB->query($query) or die($DB->error());
            $query= "ALTER TABLE glpi_plugin_rp_configs CHANGE check_public check_public_task TINYINT(1)";
            $DB->query($query) or die($DB->error());

            $query= "UPDATE glpi_plugin_rp_configs SET check_private_suivi = 0 WHERE id=1";
            $DB->query($query) or die($DB->error());
            $query= "UPDATE glpi_plugin_rp_configs SET check_public_suivi = 0 WHERE id=1";
            $DB->query($query) or die($DB->error());*/

            //$query= "UPDATE glpi_documents SET is_recursive = 1;";
            //$DB->query($query) or die($DB->error());
         //******************************************************************************* */
      }
   // BDD CONFIG

   return true;
}

function plugin_rp_uninstall() {
   global $DB;

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp";
      Toolbox::deleteDir($rep_files_rp);

      include_once(PLUGIN_RP_DIR . "/inc/profile.class.php");

   PluginRpProfile::removeRightsFromSession();
   PluginRpProfile::removeRightsFromDB();

   $tables = ["glpi_plugin_rp_dataclient",
              "glpi_plugin_rp_cridetails",
              "glpi_plugin_rp_configs",
              "glpi_plugin_rp_signtech"];

   foreach ($tables as $table)
      $DB->query("DROP TABLE IF EXISTS `$table`;");

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

   /*$PLUGIN_HOOKS['item_purge']['rp']["Document"]
      = ['PluginRpEntityLogo', 'cleanForItem'];*/
}

function plugin_rp_MassiveActions($type) {
   global $PLUGIN_HOOKS;

   switch ($type) {
      default :
         if (isset($PLUGIN_HOOKS['plugin_rp'][$type])) {
            return ['PluginRpCommon'.MassiveAction::CLASS_ACTION_SEPARATOR.'DoIt'
                     => __('Print to pdf 5', 'rp')];
         }
   }
   return [];
}

