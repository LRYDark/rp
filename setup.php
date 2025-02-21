<?php

define('PLUGIN_RP_VERSION', '3.0.4');
$_SESSION['PLUGIN_RP_VERSION'] = PLUGIN_RP_VERSION;

// Minimal GLPI version,
define("PLUGIN_RP_MIN_GLPI", "10.0.3");
// Maximum GLPI version,
define("PLUGIN_RP_MAX_GLPI", "10.2.1");

if (!defined("PLUGIN_RP_DIR")) {
   define("PLUGIN_RP_DIR", Plugin::getPhpDir("rp"));
   define("PLUGIN_RP_NOTFULL_DIR", Plugin::getPhpDir("rp",false));
   define("PLUGIN_RP_WEBDIR", Plugin::getWebDir("rp"));
   define("PLUGIN_RP_NOTFULL_WEBDIR", Plugin::getWebDir("rp",false));
}

/****************************************************************************************************************************************** */
if (!isset($_SESSION['alert_displayedRP']) && isset($_SESSION['glpiID']) && $_SESSION['glpiactiveprofile']['name'] == 'Super-Admin'){
   $_SESSION['alert_displayedRP'] = true;
   //token GitHub et identification du répertoire
   global $DB;
   $tokenID = $DB->query("SELECT token FROM `glpi_plugin_rt_configs` WHERE id = 1")->fetch_object();
   if (!empty($tokenID->token)){
      $token = $tokenID->token;
      $owner = 'LRYDark';
      $repo = 'rp';

      // Créez une fonction pour effectuer des requêtes à l'API GitHub
      function requestGitHubAPIRP($url, $token) {
         $ch = curl_init($url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $token,
            'User-Agent: PHP-Script',
            'Accept: application/vnd.github+json'
         ]);
         $response = curl_exec($ch);
         curl_close($ch);
         return json_decode($response, true);
      }
      
      // Récupérer la dernière version (release) disponible
      function getLatestReleaseRP($owner, $repo, $token) {
         $url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
         return requestGitHubAPIRP($url, $token);
      }

      //$latestRelease = getLatestRelease($owner, $repo, $token);
      $latestRelease = getLatestReleaseRP($owner, $repo, $token);

      if(isset($latestRelease['tag_name'])){
         $version = str_replace("rp-", "", $latestRelease['tag_name']);// Utilisation de str_replace pour retirer "rp-"

         if ($version > PLUGIN_RP_VERSION){
            // Afficher la pop-up avec JavaScript
            echo "<script>
               window.addEventListener('load', function() {
                  alert('Une nouvelle version du plugin rp est disponible (version : " . $latestRelease['tag_name'] . "). <br>Veuillez mettre à jour dès que possible.');
               });
            </script>";
         }
      }
   }
}
/****************************************************************************************************************************************** */

// Init the hooks of the plugins -Needed
function plugin_init_rp() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['rp'] = true;
   $PLUGIN_HOOKS['change_profile']['rp'] = ['PluginRpProfile', 'initProfile'];

   $plugin = new Plugin();

   if (Session::getLoginUserID()) {
      Plugin::registerClass('PluginRpProfile', ['addtabon' => 'Profile']);
      Plugin::registerClass('PluginRpCriDetail', ['addtabon' => 'Ticket']);
      
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

   if(Session::haveRight("plugin_rp_pdf", CREATE)){
      $PLUGIN_HOOKS['use_massive_action']['rp'] = 1;
      $PLUGIN_HOOKS['plugin_rp']['Ticket']      = 'PluginRpTicket';
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
      'homepage'       => 'https://www.jcd-groupe.fr/',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_RP_MIN_GLPI,
            'max' => PLUGIN_RP_MAX_GLPI,
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
