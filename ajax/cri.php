<?php
include('../../../inc/includes.php');

Html::header_nocache();
Session::checkLoginUser();


switch ($_POST['action']) {//action bouton généré PDF formulaire ticket
   case 'showCriForm' :
      $PluginRpCri = new PluginRpCri();
      $params                  = $_POST["params"];
      $PluginRpCri->showForm($params["job"], ['modal' => $_POST["modal"]]);
      break;
}
