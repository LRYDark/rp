<?php

define('PLUGIN_RP_VERSION', '3.3.1');

/**
 * Révision des fichiers JS/CSS.
 *
 * GLPI suffixe les assets d'un plugin avec sa version : sans changement de
 * version, les navigateurs continuent de servir l'ancien fichier. Cette
 * révision permet de forcer le rechargement d'un JS ou d'un CSS SANS toucher à
 * la version du plugin (donc sans repasser par « Mettre à jour »).
 *
 * À incrémenter à chaque modification d'un fichier de public/js ou public/css.
 */
define('PLUGIN_RP_ASSETS_REV', '53');
$_SESSION['PLUGIN_RP_VERSION'] = PLUGIN_RP_VERSION;

// Minimal GLPI version,
define("PLUGIN_RP_MIN_GLPI", "11.0.0");
// Maximum GLPI version,
define("PLUGIN_RP_MAX_GLPI", "11.2.0");

if (!defined("PLUGIN_RP_DIR")) {
   define("PLUGIN_RP_DIR", Plugin::getPhpDir("rp"));
   define("PLUGIN_RP_NOTFULL_DIR", Plugin::getPhpDir("rp",false));
   define("PLUGIN_RP_WEBDIR", Plugin::getWebDir("rp"));
   define("PLUGIN_RP_NOTFULL_WEBDIR", Plugin::getWebDir("rp",false));
}

/**
 * GLPI 11 : Document::add()/update() supprime silencieusement `filepath` et `sha1sum`
 * de l'input (blacklist dans Document::filterFields, src/Document.php) => les Documents
 * crees par le plugin pointaient sur un chemin vide (erreur Safe\fread au telechargement).
 * Ce helper reecrit les deux champs directement en base APRES Document::add()/update(),
 * avec le chemin relatif a GLPI_DOC_DIR (convention core). A appeler apres CHAQUE
 * creation/mise a jour de Document du plugin.
 *
 * @param int    $doc_id        id du Document
 * @param string $relative_path chemin relatif a GLPI_DOC_DIR (ex: '_plugins/rp/rapports/xxx.pdf')
 * @return bool  true si le filepath a ete ecrit (fichier physique present)
 */
if (!function_exists('pluginRpFixDocumentFile')) {
   function pluginRpFixDocumentFile(int $doc_id, string $relative_path): bool {
      global $DB;
      if ($doc_id <= 0) {
         return false;
      }
      $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
      $fullpath = GLPI_DOC_DIR . '/' . $relative_path;
      if ($relative_path === '' || !is_file($fullpath)) {
         Toolbox::logInFile('plugin-rp', "Document #$doc_id : fichier introuvable pour filepath '$relative_path' — champ non corrige\n");
         return false;
      }
      $sha1 = @sha1_file($fullpath);
      return (bool)$DB->update('glpi_documents', [
         'filepath' => $relative_path,
         'sha1sum'  => ($sha1 !== false ? $sha1 : null),
      ], ['id' => $doc_id]);
   }
}

/**
 * Dossier de rangement daté d'un type de document : <base>/<annee>/<mois>.
 *
 * Les rapports s'entassaient à plat dans `_plugins/rp/rapports/` & co : au bout
 * de quelques milliers de PDF, le dossier devient impraticable — sauvegarde,
 * recherche manuelle, listing FTP. Le classement reprend EXACTEMENT celui du
 * plugin Gestion (`front/traitement.php`), mois en toutes lettres et sans
 * accent, pour que les deux plugins se rangent de la même façon.
 *
 * Les anciens fichiers ne bougent pas : rien ne les cherche par dossier, tout
 * passe par `glpi_documents.filepath` qui reste exact.
 *
 * @param string $base sous-dossier du plugin ('fiches', 'rapports', ...)
 * @return array{0:string,1:string} [chemin relatif à GLPI_DOC_DIR, chemin absolu]
 *                                  tous deux terminés par '/'
 */
if (!function_exists('pluginRpDatedFolder')) {
   function pluginRpDatedFolder(string $base): array {
      $months = [1 => 'janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin',
                 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
      $month  = $months[(int)date('n')] ?? strtolower(date('F'));

      $relative = '_plugins/rp/' . trim($base, '/') . '/' . date('Y') . '/' . $month . '/';
      $absolute = GLPI_DOC_DIR . '/' . $relative;

      /*
       * Création RÉCURSIVE : au premier document du mois, ni l'année ni le mois
       * n'existent. Sans ce dossier, l'écriture du PDF échoue et le Document
       * GLPI enregistré ensuite pointe dans le vide — c'est le « Fichier
       * introuvable sur le disque » des listes.
       *
       * Le `is_dir()` final n'est pas redondant : deux générations simultanées
       * peuvent créer le même dossier, et le `mkdir` perdant renvoie false
       * alors que le dossier existe bel et bien.
       */
      if (!is_dir($absolute) && !@mkdir($absolute, 0755, true) && !is_dir($absolute)) {
         // Repli sur le dossier historique plutôt que d'échouer : un PDF rangé
         // à plat vaut mieux qu'un rapport perdu. Il est créé à l'installation,
         // mais on s'en assure — un dossier supprimé à la main ne doit pas
         // faire perdre le document.
         Toolbox::logInFile('plugin-rp', "Dossier daté impossible à créer : $absolute\n");
         $relative = '_plugins/rp/' . trim($base, '/') . '/';
         $absolute = GLPI_DOC_DIR . '/' . $relative;
         if (!is_dir($absolute)) {
            @mkdir($absolute, 0755, true);
         }
      }

      return [$relative, $absolute];
   }
}

/**
 * Ce document GLPI est-il aussi celui de bons de livraison signés ?
 *
 * Quand les deux plugins travaillent ensemble, une signature groupée produit UN
 * seul PDF — rapport + bons — et les deux plugins pointent dessus. Le rapport
 * n'en est plus le seul propriétaire : le réécrire ou l'effacer emporterait des
 * bons signés avec lui.
 *
 * Répond toujours `false` si le plugin Gestion n'est pas là : RP reste alors
 * seul maître de ses documents, et son mode mono-document fonctionne comme
 * avant sans dépendre de quoi que ce soit.
 *
 * @param int $documents_id identifiant `glpi_documents`
 * @return bool
 */
if (!function_exists('pluginRpDocumentSharedWithBl')) {
   function pluginRpDocumentSharedWithBl(int $documents_id): bool {
      global $DB;

      if ($documents_id <= 0 || !$DB->tableExists('glpi_plugin_gestion_surveys')) {
         return false;
      }

      return countElementsInTable(
         'glpi_plugin_gestion_surveys',
         ['doc_id' => $documents_id, 'signed' => 1]
      ) > 0;
   }
}

$plugin = new Plugin();
if ($plugin->isInstalled('rp') && $plugin->isActivated('rp')) {
   if (!isset($_SESSION['alert_displayedRP']) && isset($_SESSION['glpiID'])) {
      global $DB;

      $sessionID = (int)($_SESSION['glpiID'] ?? 0);
      $result = $DB->doQuery("SELECT version FROM `glpi_plugin_rp_signtech` WHERE user_id = $sessionID");

      if ($result) {
         $usercrihotline = $result->fetch_object();

         if (isset($usercrihotline->version)) {
            $version = (int)$usercrihotline->version;

            if ($version === 1) {
               $_SESSION['alert_displayedRP'] = true;
               $generateUrl = PLUGIN_RP_WEBDIR . '/front/generatecri.php';

               // Injecte cette URL dans le script JavaScript
               echo "<script>
                  window.addEventListener('load', function() {
                     const messageBox = document.createElement('div');
                     messageBox.style.position = 'fixed';
                     messageBox.style.top = '20px';
                     messageBox.style.left = '50%';
                     messageBox.style.transform = 'translateX(-50%)';
                     messageBox.style.backgroundColor = '#fffffaff ';
                     messageBox.style.color = '#000000ff';
                     messageBox.style.padding = '15px 20px';
                     messageBox.style.border = '2px solid #c13333';
                     messageBox.style.borderRadius = '5px';
                     messageBox.style.zIndex = '10000';
                     messageBox.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
                     messageBox.style.maxWidth = '400px';
                     messageBox.style.fontFamily = 'Arial, sans-serif';
                     messageBox.innerHTML = `
                        <div style='display: flex; justify-content: space-between; align-items: center;'>
                           <strong>Information importante</strong>
                           <span style='cursor: pointer; font-weight: bold;' onclick='this.parentElement.parentElement.remove();'>&times;</span>
                        </div>
                        <div style='margin-top: 10px;'>
                           Suite à la mise à jour et à la refonte du plugin RP, il est nécessaire de recréer votre signature.<br><br>
                           <a href='{$generateUrl}' target='_blank' style='display:inline-block;padding:8px 12px;background-color:#007bff;color:#fff;text-decoration:none;border-radius:4px;'>Créer ma signature</a><br><br>
                           Merci pour votre compréhension.
                        </div>
                     `;
                     document.body.appendChild(messageBox);
                  });
               </script>";
            }
         }
      }
   }
}

// Init the hooks of the plugins -Needed
function plugin_init_rp() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['rp'] = true;
   $PLUGIN_HOOKS['change_profile']['rp'] = [PluginRpProfile::class, 'initProfile'];

   $plugin = new Plugin();
   if ($plugin->isInstalled('rp') && $plugin->isActivated('rp')) {
      $api_pattern_prepare = '#^/api/ticket_prepare\.php(?:/.*)?$#';
      $api_pattern_generate = '#^/api/ticket_generate\.php(?:/.*)?$#';
      $api_pattern_sign = '#^/api/ticket_sign\.php(?:/.*)?$#';

      \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
         'rp',
         $api_pattern_prepare,
         \Glpi\Http\Firewall::STRATEGY_NO_CHECK
      );
      \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
         'rp',
         $api_pattern_generate,
         \Glpi\Http\Firewall::STRATEGY_NO_CHECK
      );
      \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
         'rp',
         $api_pattern_sign,
         \Glpi\Http\Firewall::STRATEGY_NO_CHECK
      );

      \Glpi\Http\SessionManager::registerPluginStatelessPath('rp', $api_pattern_prepare);
      \Glpi\Http\SessionManager::registerPluginStatelessPath('rp', $api_pattern_generate);
      \Glpi\Http\SessionManager::registerPluginStatelessPath('rp', $api_pattern_sign);

      if (Session::getLoginUserID()) {
         Plugin::registerClass('PluginRpProfile', ['addtabon' => 'Profile']);
         Plugin::registerClass('PluginRpCriDetail', ['addtabon' => 'Ticket']);
         Plugin::registerClass('PluginRpUserpref', ['addtabon' => 'Preference']);

         // `?r=` : révision des assets, pour forcer le rechargement des JS/CSS
         // sans changer la version du plugin (GLPI ajoute ensuite son `&v=`).
         $rp_rev = '?r=' . PLUGIN_RP_ASSETS_REV;

         $PLUGIN_HOOKS['add_css']['rp'] = ["css/signature_rp.css" . $rp_rev];
         $PLUGIN_HOOKS['add_javascript']['rp'] = [
            'js/scripts_rp.js' . $rp_rev
         ];

         /*
          * Boutons flottants (accueil et ticket). L'affichage dépend :
          *   - du droit de profil `plugin_rp_boutons` (bit READ = accueil,
          *     bit UPDATE = ticket) ;
          *   - de la préférence personnelle de l'utilisateur
          *     (0 = jamais, 1 = mobile uniquement par défaut, 2 = toujours).
          * Le socle fab_rp.js doit être chargé avant scan_rp.js.
          */
         $rp_mode_home   = PluginRpUserpref::getEffectiveMode('fab_home');
         $rp_mode_ticket = PluginRpUserpref::getEffectiveMode('fab_ticket');

         // Le bouton d'accueil n'a d'intérêt que si l'utilisateur peut
         // exploiter au moins un des deux plugins
         $rp_can_scan = PluginRpAccess::canUse('mobile')
            || PluginRpAccess::canUse('rapport_tech', CREATE)
            || (Plugin::isPluginActive('gestion') && Session::haveRight('plugin_gestion_survey', READ));
         if (!$rp_can_scan) {
            $rp_mode_home = PluginRpUserpref::MODE_NEVER;
         }

         if ($rp_mode_home !== PluginRpUserpref::MODE_NEVER
             || $rp_mode_ticket !== PluginRpUserpref::MODE_NEVER) {
            $PLUGIN_HOOKS['add_javascript']['rp'][] = 'js/fab_rp.js' . $rp_rev;
            if ($rp_mode_home !== PluginRpUserpref::MODE_NEVER) {
               $PLUGIN_HOOKS['add_javascript']['rp'][] = 'js/scan_rp.js' . $rp_rev;
            }
            // Les préférences sont transmises par une balise meta native
            // (le hook add_header_tag ne rend que des balises à attributs)
            $PLUGIN_HOOKS['add_header_tag']['rp'] = [
               [
                  'tag'        => 'meta',
                  'properties' => [
                     'name'    => 'rp:fab',
                     'content' => json_encode([
                        'fab_home'   => $rp_mode_home,
                        'fab_ticket' => $rp_mode_ticket,
                     ]),
                  ],
               ],
            ];
         }

         $PLUGIN_HOOKS['post_init']['rp'] = 'plugin_rp_postinit';
      }
      
      if(Session::getLoginUserID() && PluginRpAccess::canUse('rapport_tech', CREATE)){
         if(Session::haveRight("plugin_rp_Signature", CREATE) && Session::haveRight("plugin_rp_Signature", READ)){
            $PLUGIN_HOOKS["menu_toadd"]['rp']['tools'] = 'PluginRpGenerateCRI';
         }
      }
      // Tableau « Rapport PDF » dans le menu Gestion
      if(Session::getLoginUserID() && Session::haveRight('plugin_rp_liste', READ)){
         $PLUGIN_HOOKS["menu_toadd"]['rp']['management'] = 'PluginRpCriDetail';
      }

      if(Session::getLoginUserID() && PluginRpAccess::canUse('massif')){
         $PLUGIN_HOOKS['use_massive_action']['rp'] = 1;
         $PLUGIN_HOOKS['plugin_rp']['Ticket']      = 'PluginRpTicket';
      }

      $PLUGIN_HOOKS['config_page']['rp'] = '../../front/config.form.php?forcetab=' . urlencode('PluginRpConfig$1');
      Plugin::registerClass('PluginRpConfig', ['addtabon' => 'Config']);
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
            'max' => PLUGIN_RP_MAX_GLPI
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
