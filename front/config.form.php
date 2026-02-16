<?php
include('../../../inc/includes.php');

global $CFG_GLPI;

$plugin = new Plugin();
if (!$plugin->isInstalled('rp') || !$plugin->isActivated('rp')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginRpConfig();

if (isset($_POST['update'])) {
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
