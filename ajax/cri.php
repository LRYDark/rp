<?php
include('../../../inc/includes.php');

Html::header_nocache();
Session::checkLoginUser();

$action = (string)($_POST['action'] ?? '');

switch ($action) {//action bouton généré PDF formulaire ticket
   case 'showCriForm' :
      $PluginRpCri = new PluginRpCri();
      $params = $_POST["params"] ?? [];
      if (!is_array($params)) {
         break;
      }
      $PluginRpCri->showForm($params["job"] ?? 0, ['modal' => ($_POST["modal"] ?? '')]);
      break;
}
