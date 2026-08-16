<?php
/**
 * Alias : redirige vers le tableau des rapports (front/cridetail.php).
 */
include('../../../inc/includes.php');

Session::checkLoginUser();

Html::redirect(PLUGIN_RP_WEBDIR . '/front/cridetail.php');
