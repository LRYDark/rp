<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

if (!isset($_GET["id"])) $_GET["id"] = 0;
if (!isset($_GET["users_id"])) {
   $users_id = Session::getLoginUserID();
} else {
   $users_id = $_GET["users_id"];
}

$cri = new TicketTask();

$cri->checkGlobal(READ);

$plugin = new Plugin();

if (Session::getCurrentInterface() == 'central') {
   Html::header(__('Entities portal', 'rp'), '', "management", "pluginrpentity");
} else {
   if ($plugin->isActivated('servicecatalog')) {
      PluginServicecatalogMain::showDefaultHeaderHelpdesk(__('Entities portal', 'rp'));
   } else {
      Html::helpHeader(__('Entities portal', 'rp'));
   }
}

$cri->display($_GET);

if (Session::getCurrentInterface() != 'central'
    && $plugin->isActivated('servicecatalog')) {

   PluginServicecatalogMain::showNavBarFooter('rp');
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
