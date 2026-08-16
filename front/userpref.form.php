<?php
/**
 * Enregistrement des préférences personnelles du plugin RP
 * (onglet « Rapport » des Préférences GLPI).
 */
include('../../../inc/includes.php');

Session::checkLoginUser();

/**
 * Même mécanisme que l'écran de configuration du plugin : le formulaire est
 * rendu dans un onglet chargé en AJAX et porte un jeton autonome dédié, validé
 * sans être consommé (l'onglet n'est pas re-rendu après l'enregistrement, il
 * doit rester utilisable pour un second enregistrement).
 */
function pluginRpUserprefCheckCSRF(array $data): void {
   if (!empty($data['plugin_rp_userpref_csrf_token'])) {
      Session::checkCSRF([
         '_glpi_csrf_token' => (string)$data['plugin_rp_userpref_csrf_token']
      ], true);
      return;
   }

   Session::checkCSRF($data, true);
}

if (isset($_POST['update_rp_prefs'])) {
   pluginRpUserprefCheckCSRF($_POST);

   if (PluginRpUserpref::saveForUser($_POST)) {
      Session::addMessageAfterRedirect(__('Préférences enregistrées.', 'rp'), true, INFO);
   } else {
      Session::addMessageAfterRedirect(__("Échec de l'enregistrement des préférences.", 'rp'), true, ERROR);
   }
}

Html::back();
