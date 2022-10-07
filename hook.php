<?php
function plugin_rp_install() {
   global $DB;

   include_once(PLUGIN_RP_DIR . "/inc/profile.class.php");
   include_once(PLUGIN_RP_DIR . "/inc/cridetail.class.php");

   $rep_files_rp = GLPI_PLUGIN_DOC_DIR . "/rp";
   if (!is_dir($rep_files_rp))
      mkdir($rep_files_rp);

   $rep_files_rp_fiche = GLPI_PLUGIN_DOC_DIR . "/rp/fiches";
   if (!is_dir($rep_files_rp_fiche))
      mkdir($rep_files_rp_fiche);

   $rep_files_rp_rapport = GLPI_PLUGIN_DOC_DIR . "/rp/rapports";
   if (!is_dir($rep_files_rp_rapport))
      mkdir($rep_files_rp_rapport);

   $rep_files_rp_rapport = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsHotline";
   if (!is_dir($rep_files_rp_rapport))
      mkdir($rep_files_rp_rapport);

   PluginRpProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   PluginRpProfile::initProfile();
   $DB->query("DROP TABLE IF EXISTS `glpi_plugin_rp_profiles`;");

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
   $DB->query($query);

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
      PRIMARY KEY (`id`) 
      ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   $DB->query($query);

   if ($DB->tableExists("glpi_plugin_rp_cridetails")) {
      $query= "ALTER TABLE glpi_plugin_rp_cridetails
               ADD id_task INT(11) NULL";
      $DB->query($query);
   }

   $query= "CREATE TABLE IF NOT EXISTS `glpi_plugin_rp_signtech` ( 
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,  
      `user_id` INT UNSIGNED,
      `seing` TEXT,
      PRIMARY KEY (`id`),
      UNIQUE KEY (`user_id`)
      ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   $DB->query($query);

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

   $PLUGIN_HOOKS['item_purge']['rp']["Document"]
      = ['PluginRpEntityLogo', 'cleanForItem'];
}