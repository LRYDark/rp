<?php

Session::checkLoginUser();
PluginRpAccess::checkUse('massif');

$zipFileName = (string)($_GET["zipname"] ?? '');
if ($zipFileName === '' || str_contains($zipFileName, "\0") || str_contains($zipFileName, '..')) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid filename'));
}

$allowedBase = realpath(GLPI_PLUGIN_DOC_DIR . '/rp/rapportsMass');
$realZip = realpath($zipFileName);

if ($allowedBase === false || $realZip === false || !is_file($realZip)) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Unauthorized access to this file'));
}

$allowedPrefix = rtrim($allowedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!str_starts_with($realZip, $allowedPrefix) || strtolower((string)pathinfo($realZip, PATHINFO_EXTENSION)) !== 'zip') {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Unauthorized access to this file'));
}

return Toolbox::getFileAsResponse($realZip, basename($realZip));
