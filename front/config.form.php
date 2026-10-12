<?php

global $CFG_GLPI;

$plugin = new Plugin();
if (!$plugin->isInstalled('rp') || !$plugin->isActivated('rp')) {
   throw new \Glpi\Exception\Http\NotFoundHttpException();
}

Session::checkRight('config', UPDATE);

$config = new PluginRpConfig();

// GLPI 12 : la protection CSRF est assurée par le noyau (en-têtes Sec-Fetch-Site / Origin), sans jeton.
if (isset($_POST['update'])) {
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
