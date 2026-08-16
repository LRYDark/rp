<?php
include('../../../inc/includes.php');

require("../fpdf/font/symbol.php");

Session::checkLoginUser();
if (!isset($_POST["cri"])) $_POST["cri"] = "";
if (!isset($_GET["action"])) $_GET["action"] = "";

Html::popHeader(__('Generation of the intervention report', 'rp'));

$PluginRpCri = new PluginRpCri();
$criDetail   = new PluginRpCriDetail();

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
   // Sécurité : purge d'un Document uniquement si l'utilisateur a le droit de
   // purge sur les documents ET que le document appartient bien au plugin RP
   $doc    = new Document();
   $doc_id = (int)($_POST['documents_id'] ?? 0);
   $is_rp_doc = $doc_id > 0
      && countElementsInTable('glpi_plugin_rp_cridetails', ['id_documents' => $doc_id]) > 0;
   if ($is_rp_doc && $doc->getFromDB($doc_id) && $doc->canPurgeItem()) {
      if ($doc->delete(['id' => $doc_id], 1)) {
         \Glpi\Event::log($doc_id, "documents", 4, "document", $_SESSION["glpiname"] . " " . __('Delete permanently'));
      }
   } else {
      Session::addMessageAfterRedirect(__("Vous n'avez pas les droits requis pour supprimer ce document.", 'rp'), true, ERROR);
   }
   Html::back();

}

else {
   $PluginRpCri->showForm($_GET["job"], ['action' => $_GET["action"]]);
}

Html::popFooter();
