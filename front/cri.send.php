<?php

include('../../../inc/includes.php');

if (isset($_GET["file"])) { // for other file
   $splitter = explode("/", $_GET["file"]);

   if (count($splitter) == 3) {

      if (file_exists(GLPI_DOC_DIR . "/" . $_GET["file"])) {
         if (!isset($_GET["seefile"])) {
            Toolbox::sendFile(GLPI_DOC_DIR . "/" . $_GET["file"], $splitter[2]);
         } else {
            $doc                     = new Document();
            $doc->fields['filepath'] = $_GET["file"];
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
         Html::displayErrorAndDie(__('Unauthorized access to this file'), true);
      }
   } else {
      Html::displayErrorAndDie(__('Invalid filename'), true);
   }
}