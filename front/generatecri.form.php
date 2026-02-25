<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

global $DB;

$PluginRpGenerateCri = new PluginRpGenerateCri();
$PluginRpCri         = new PluginRpCri();
$ticket              = new Ticket();
$UserID = (int)Session::getLoginUserID();

function pluginRpGenerateCriCheckCSRF(array $data): void {
    if (!empty($data['plugin_rp_generatecri_csrf_token'])) {
        Session::checkCSRF(['_glpi_csrf_token' => (string)$data['plugin_rp_generatecri_csrf_token']], true);
        return;
    }
    Session::checkCSRF($data, true);
}

if (isset($_POST['generatecri'])) {
   if(Session::haveRight("plugin_rp_Signature", CREATE)){
      pluginRpGenerateCriCheckCSRF($_POST);

      $url = (string)($_POST['url'] ?? '');
      $exists = $DB->request([
         'SELECT' => ['user_id'],
         'FROM'   => 'glpi_plugin_rp_signtech',
         'WHERE'  => ['user_id' => $UserID],
         'LIMIT'  => 1,
      ]);

      if (count($exists) === 0) {
         if ($DB->insert('glpi_plugin_rp_signtech', ['user_id' => $UserID, 'seing' => $url, 'version' => 2])) {
            Session::addMessageAfterRedirect(
               __("Signature enregistrée avec succès.", 'rp'),
               true,
               INFO
           );
         } else {
            Session::addMessageAfterRedirect(
               __("Erreur lors de l'enregistrement de la signature.", 'rp'),
               true,
               ERROR
           );
         }
      } else {
         if (Session::haveRight("plugin_rp_Signature", UPDATE)) {
            if ($DB->update('glpi_plugin_rp_signtech', ['seing' => $url, 'version' => 2], ['user_id' => $UserID])) {
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
            } else {
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
      pluginRpGenerateCriCheckCSRF($_POST);

      $exists = $DB->request([
         'SELECT' => ['user_id'],
         'FROM'   => 'glpi_plugin_rp_signtech',
         'WHERE'  => ['user_id' => $UserID],
         'LIMIT'  => 1,
      ]);

      if (count($exists) > 0) {
         if ($DB->delete('glpi_plugin_rp_signtech', ['user_id' => $UserID])) {
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
         } else {
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
   pluginRpGenerateCriCheckCSRF($_POST);
   Html::back();
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
