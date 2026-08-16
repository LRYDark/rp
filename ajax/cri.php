<?php
include('../../../inc/includes.php');

Html::header_nocache();
Session::checkLoginUser();

$action = (string)($_POST['action'] ?? '');

switch ($action) {//action bouton généré PDF formulaire ticket
   case 'showCriForm' :
      $params = $_POST["params"] ?? [];
      if (!is_array($params)) {
         break;
      }

      $modal  = (string)($_POST["modal"] ?? '');
      $job_id = (int)($params["job"] ?? 0);

      // Règles d'accès RP + droits profil : le formulaire expose tâches, suivis
      // et e-mails du ticket, il doit être aussi protégé que la génération.
      switch ($modal) {
         case 'form_client':
         case 'form_rapport':
            PluginRpAccess::checkUseAjax('rapport_tech');
            break;
         case 'form_rapport_hotline':
            PluginRpAccess::checkUseAjax('rapport_hotline');
            break;
         case 'form_preparation':
            PluginRpAccess::checkUseAjax('preparation');
            break;
         default:
            http_response_code(400);
            exit;
      }

      // L'utilisateur doit pouvoir voir le ticket demandé
      $ticket = new Ticket();
      if ($job_id <= 0 || !$ticket->getFromDB($job_id) || !$ticket->canViewItem()) {
         http_response_code(403);
         echo __("Vous n'avez pas accès à ce ticket.", 'rp');
         exit;
      }

      if ($modal === 'form_preparation') {
         PluginRpPreparation::showFormModal($job_id);
         break;
      }

      $PluginRpCri = new PluginRpCri();
      $PluginRpCri->showForm($job_id, ['modal' => $modal]);
      break;
}
