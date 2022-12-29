<?php

define('PLUGIN_RP_VERSION', '2.0.6');

if (!defined("PLUGIN_RP_DIR")) {
   define("PLUGIN_RP_DIR", Plugin::getPhpDir("rp"));
   define("PLUGIN_RP_NOTFULL_DIR", Plugin::getPhpDir("rp",false));
   define("PLUGIN_RP_WEBDIR", Plugin::getWebDir("rp"));
   define("PLUGIN_RP_NOTFULL_WEBDIR", Plugin::getWebDir("rp",false));
}

// Init the hooks of the plugins -Needed
function plugin_init_rp() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['rp'] = true;
   $PLUGIN_HOOKS['change_profile']['rp'] = ['PluginRpProfile', 'initProfile'];

   $plugin = new Plugin();

   if (Session::getLoginUserID()) {
      Plugin::registerClass('PluginRpProfile', ['addtabon' => 'Profile']);
      Plugin::registerClass('PluginRpCriDetail', ['addtabon'       => 'Ticket']);

      // Add specific files to add to the header : javascript or css
      $PLUGIN_HOOKS['add_css']['rp'] = ["rp.css", "style.css"];

      if (isset($_SESSION['glpiactiveprofile']['interface'])
          && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $PLUGIN_HOOKS['add_javascript']['rp'] = ['scripts/scripts-rp.js'];
      }

      $PLUGIN_HOOKS['post_init']['rp'] = 'plugin_rp_postinit';
   }
   
   if(Session::haveRight("plugin_rp_rapport_tech", CREATE)){
      if(Session::haveRight("plugin_rp_Signature", CREATE) && Session::haveRight("plugin_rp_Signature", READ)){
         $PLUGIN_HOOKS["menu_toadd"]['rp']['tools']  = 'PluginRpGenerateCRI';
      }
   }

   if (Session::haveRight("plugin_rp", UPDATE)) {
      $PLUGIN_HOOKS['config_page']['rp'] = 'front/config.form.php';
   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_rp() {

   return [
      'name'           => __('Rapport', 'rp'),
      'version'        => PLUGIN_RP_VERSION,
      'author'         => "REINERT Joris",
      //'license'        => 'GPLv2+',
      'homepage'       => 'https://www.jcd-groupe.fr/',
      'requirements'   => [
         'glpi' => [
            'min' => '10.0.0',
            'max' => '10.0.6',
            'dev' => false
         ]
      ]
   ];
}

/**
 * @return bool
 */
function plugin_rp_check_prerequisites() {
   return true;
}
