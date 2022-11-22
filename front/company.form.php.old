<?php
include('../../../inc/includes.php');

$company = new PluginRpCompany();

if (isset($_POST["add"])) {
   $company->check(-1, CREATE);
   $newID = $company->add($_POST);
   if ($_SESSION['glpibackcreated']) {
      Html::redirect($company->getFormURL() . "?id=" . $newID);
   }
   Html::back();
} else if (isset($_POST["update"])) {
   $company->check($_POST["id"], UPDATE);
   $company->update($_POST);
   Html::back();
} else if (isset($_POST["purge"])) {
   $company_id = $_POST["id"];
   $company->check($_POST["id"], PURGE);
   $company->delete($_POST, 1);
   header('Location: ../front/config.form.php');
} else {
   Html::header(PluginRpCompany::getTypeName(2), '', "management", "pluginrpentity", "company");
   $company->display($_GET);
   Html::footer();
}
