<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

$PluginRpGenerateCri = new PluginRpGenerateCri();
$PluginRpCri         = new PluginRpCri();
$ticket              = new Ticket();
$UserID = Session::getLoginUserID();

if (isset($_POST['generatecri'])) {
   if(Session::haveRight("plugin_rp_Signature", CREATE)){

      $url = $_POST['url'];
      $seing = $DB->query("SELECT user_id FROM `glpi_plugin_rp_signtech` WHERE user_id = $UserID")->fetch_object();

      if(empty($seing)){
         $query= "INSERT INTO `glpi_plugin_rp_signtech` (`user_id`, `seing`) VALUES ($UserID, '$url');";
         if($DB->query($query)){
            Session::addMessageAfterRedirect(
               __("Signature enregistrée avec succès.", 'rp'),
               true,
               INFO
           );
         }else{
            Session::addMessageAfterRedirect(
               __("Erreur lors de l'enregistrement de la signature.", 'rp'),
               true,
               ERROR
           );
         }
      }else{
         if(Session::haveRight("plugin_rp_Signature", UPDATE)){
            $query= "UPDATE glpi_plugin_rp_signtech SET seing='$url' WHERE user_id = $UserID;";
            if($DB->query($query)){
               Session::addMessageAfterRedirect(
                  __("Signature modifiée avec succès.", 'rp'),
                  true,
                  INFO
              );
              Session::addMessageAfterRedirect(
                  __("<i class='fa-solid fa-triangle-exclamation'></i> Vous-venez de modifier votre signature.", 'rp'),
                  true,
                  WARNING
               );
            }else{
               Session::addMessageAfterRedirect(
                  __("Erreur lors de la modification de la signature.", 'rp'),
                  true,
                  ERROR
              );
            }
         }
      }
      
      Html::back();
   }
}

if (isset($_POST['delete'])) {
   if (Session::haveRight('plugin_rp_Signature', PURGE)) {

      $url = $_POST['url'];
      $seing = $DB->query("SELECT user_id FROM `glpi_plugin_rp_signtech` WHERE user_id = $UserID")->fetch_object();

      if(!empty($seing)){
         $query= "DELETE FROM `glpi_plugin_rp_signtech` WHERE `user_id` = $UserID;";
         if($DB->query($query)){
            Session::addMessageAfterRedirect(
               __("Signature supprimée avec succès.", 'rp'),
               true,
               INFO
            );
            Session::addMessageAfterRedirect(
               __("<i class='fa-solid fa-triangle-exclamation'></i> Vous-venez de supprimer votre signature.", 'rp'),
               true,
               WARNING
            );
         }else{
            Session::addMessageAfterRedirect(
               __("Erreur lors de la supression de la signature.", 'rp'),
               true,
               ERROR
           );
         }
      }
      
      Html::back();
   }
}

if (isset($_POST['remove'])) {
   Html::back();
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
