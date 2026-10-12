<?php
/**
 * Alias : redirige vers le tableau des rapports (front/cridetail.php).
 */

Session::checkLoginUser();

Html::redirect(PLUGIN_RP_WEBDIR . '/front/cridetail.php');
