<?php
define('GLPI_ROOT', '../../..');
include(GLPI_ROOT . "/inc/includes.php");

Session::checkRight("profile", "rp");

$prof = new PluginRpProfile();

//Save profile
if (isset ($_POST['update'])) {
   $prof->update($_POST);
   Html::back();
}

?>