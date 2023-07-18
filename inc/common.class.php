<?php
class PluginRpCommon extends CommonGLPI {

   protected $obj= NULL;

   static $rightname = "plugin_rp";

   /**
    * Constructor, should intialize $this->obj property
   **/
   function __construct(CommonGLPI $obj=NULL) {
   }

   /**
    * @since version 0.85
   **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'DoIt':
            $cont = $ma->POST['container'];
            $opt = ['id' => 'rpmassubmit'];
            echo Html::submit(_sx('button', 'Post'), $opt);
            return true;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

      switch ($ma->getAction()) {
         case 'DoIt' :
            foreach ($ids as $key => $val) {
               if ($val) {
                  $tab_id[]=$key;
               }

                  if ($item->getFromDB($key)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                     $ma->addMessage(__("Something went wrong"));
                  }
             }
             $_SESSION["plugin_rp"]["type"]   = $item->getType();
             $_SESSION["plugin_rp"]["tab_id"] = serialize($tab_id);
             echo "<script type='text/javascript'>
                      location.href='../plugins/rp/front/export.massive.php'</script>";
             return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }

   function exportZIP($SeePath, $pdfFiles){
      // Créez un nouveau fichier zip
      $zip = new ZipArchive();
      $zipFileName = $SeePath . '/Rapport_Export-'.date('Ymd-His').'.zip';
      if ($zip->open($zipFileName, ZipArchive::CREATE)!==TRUE) {
         exit("Impossible d'ouvrir le fichier <$zipFileName>\n");
      }

      // Ajoutez les fichiers PDF au fichier zip
      foreach($pdfFiles as $pdfFile) {
         $zip->addFile($pdfFile, basename($pdfFile));
      }

      // Fermez le fichier zip
      $zip->close();

      message("<br>Documents enregistrés avec succès : <br><a href='".PLUGIN_RP_WEBDIR."/front/download.export.php?zipname=$zipFileName'>Télécharger les rapports en ZIP</a>", INFO);
   }
}