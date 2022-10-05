<?php
include('../../../inc/includes.php');

require("../fpdf/font/symbol.php");

Session::checkLoginUser();
if (!isset($_POST["cri"])) $_POST["cri"] = "";
if (!isset($_GET["action"])) $_GET["action"] = "";

Html::popHeader(__('Generation of the intervention report', 'rp'));

$PluginRpCri           = new PluginRpCri();
$PluginRpCriTechnician = new PluginRpCriTechnician();
$criDetail                         = new PluginRpCriDetail();

if (isset($_POST["addcridetail"])) {
   if ($PluginRpCri->canCreate()) {
      $criDetail->add($_POST);
   }
   if(strpos($_SERVER['HTTP_REFERER'],"generatecri.form.php") > 0){
      Html::redirect(PLUGIN_RP_WEBDIR."/front/generatecri.form.php?download=1&tickets_id=".$_POST['tickets_id']);
   } else{
      Html::back();
   }

} else if (isset($_POST["updatecridetail"])) {
   if ($PluginRpCri->canCreate()) {
      if (isset($_POST['withcontract']) && !$_POST['withcontract']) {
         $_POST['contracts_id']                          = 0;
         $_POST['plugin_rp_contractdays_id'] = 0;
      }
      $criDetail->update($_POST);
   }
   Html::back();

} else if (isset($_POST["delcridetail"])) {
   if ($PluginRpCri->canCreate()) {
      $criDetail->delete($_POST);
   }
   Html::back();

} else if (isset($_POST["purgedoc"])) {
   $doc         = new Document();
   $input['id'] = $_POST['documents_id'];
   if ($doc->delete($input, 1)) {
      \Glpi\Event::log($input['id'], "documents", 4, "document", $_SESSION["glpiname"] . " " . __('Delete permanently'));
   }
   Html::back();

}

else {
   $PluginRpCri->showForm($_GET["job"], ['action' => $_GET["action"]]);
}

Html::popFooter();
