<?php
include('../../../inc/includes.php');

global $CFG_GLPI;

$plugin = new Plugin();
if (!$plugin->isInstalled('rp') || !$plugin->isActivated('rp')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginRpConfig();

function pluginRpCheckCSRF(array $data): void {
   if (!empty($data['plugin_rp_csrf_token'])) {
      Session::checkCSRF([
         '_glpi_csrf_token' => (string)$data['plugin_rp_csrf_token']
      ], true);
      return;
   }

   Session::checkCSRF($data, true);
}

if (isset($_POST['update'])) {
   pluginRpCheckCSRF($_POST);
   PluginRpAccess::saveFromPost($_POST);
   if (!$config->update($_POST)) {
      Session::addMessageAfterRedirect(
         __('Error during update', 'rp'),
         true,
         ERROR
      );
   }
   Html::back();
}

Html::redirect($CFG_GLPI['root_doc'] . "/front/config.form.php?forcetab=" . urlencode('PluginRpConfig$1'));
