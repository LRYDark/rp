<?php

include('../../../inc/includes.php');
Session::checkLoginUser();
PluginRpAccess::checkUse('massif');

$zipFileName = (string)($_GET["zipname"] ?? '');
if ($zipFileName === '' || str_contains($zipFileName, "\0") || str_contains($zipFileName, '..')) {
    Html::displayErrorAndDie(__('Invalid filename'), true);
}

$allowedBase = realpath(GLPI_PLUGIN_DOC_DIR . '/rp/rapportsMass');
$realZip = realpath($zipFileName);

if ($allowedBase === false || $realZip === false || !is_file($realZip)) {
    Html::displayErrorAndDie(__('Unauthorized access to this file'), true);
}

$allowedPrefix = rtrim($allowedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!str_starts_with($realZip, $allowedPrefix) || strtolower((string)pathinfo($realZip, PATHINFO_EXTENSION)) !== 'zip') {
    Html::displayErrorAndDie(__('Unauthorized access to this file'), true);
}

return Toolbox::getFileAsResponse($realZip, basename($realZip));
