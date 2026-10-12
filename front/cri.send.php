<?php

Session::checkLoginUser();

if (isset($_GET["file"])) { // for other file
   $requested = str_replace('\\', '/', (string)($_GET["file"] ?? ''));
   $requested = ltrim($requested, '/');

   if ($requested === '' || str_contains($requested, "\0") || str_contains($requested, '..')) {
      throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid filename'));
   }

   $splitter = explode("/", $requested);

   if (count($splitter) == 3 && $splitter[0] === '_plugins') {
      $docRoot = realpath(GLPI_DOC_DIR);
      $fullpath = GLPI_DOC_DIR . "/" . $requested;
      $realpath = realpath($fullpath);

      if ($docRoot !== false && $realpath !== false && is_file($realpath) && str_starts_with($realpath, rtrim($docRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
         if (!isset($_GET["seefile"])) {
            Toolbox::getFileAsResponse($realpath, $splitter[2])->send();
         } else {
            $doc                     = new Document();
            $doc->fields['filepath'] = $requested;
            $doc->fields['mime']     = 'application/pdf';
            $doc->fields['filename'] = $splitter[2];

            //Document send method that has changed.
            //Because of : document.class.php
            //if (!in_array($extension, array('jpg', 'png', 'gif', 'bmp'))) {
            //   $attachment = " attachment;";
            //}
            $cri = new PluginRpCri();
            $cri->send($doc);
         }
      } else {
         throw new \Glpi\Exception\Http\BadRequestHttpException(__('Unauthorized access to this file'));
      }
   } else {
      throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid filename'));
   }
}
