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
      global $DB, $CFG_GLPI;
      $config         = PluginRpConfig::getInstance();

      switch ($ma->getAction()) {
         case 'DoIt':
               $cont = $ma->POST['container'];
               
               echo "<table class='tab_cadre_fixe'>";
               echo "<tr class='tab_bg_1'>";
               echo "<td>" . __('Type de rapport', 'rp') . " <span class='red'>*</span></td>";
               echo "<td>";

               $entity_parrent1_id = (int)($config->fields['entity_parrent1'] ?? 0);
               $entity_parrent2_id = (int)($config->fields['entity_parrent2'] ?? 0);
               $entityNames = [];
               $entityIds = array_values(array_unique(array_filter([$entity_parrent1_id, $entity_parrent2_id])));
               if (!empty($entityIds)) {
                  foreach ($DB->request([
                     'SELECT' => ['id', 'name'],
                     'FROM'   => 'glpi_entities',
                     'WHERE'  => ['id' => $entityIds]
                  ]) as $entityRow) {
                     $entityNames[(int)($entityRow['id'] ?? 0)] = (string)($entityRow['name'] ?? '');
                  }
               }
               $entity_parrent1_name = $entityNames[$entity_parrent1_id] ?? '';
               $entity_parrent2_name = $entityNames[$entity_parrent2_id] ?? '';
                              
               $options = [
                  0                      => '-----',
                  'auto'                 => 'Mode Auto',
                  'entity_parrent1'      => 'Rapport '.$entity_parrent1_name,
                  'entity_parrent2'      => 'Rapport '.$entity_parrent2_name                  
               ];
               
               // Préserver la valeur sélectionnée en cas d'erreur
               $selected_value = isset($_SESSION['massiveaction_selected_report_type']) ? 
                              $_SESSION['massiveaction_selected_report_type'] : 0;
               
               Dropdown::showFromArray(
                  'report_type', 
                  $options, 
                  [
                     'value' => $selected_value,
                     'width' => '200px'
                  ]
               );
               
               echo "</td>";
               echo "</tr>";
               echo "</table>";
               
               // Zone pour afficher les messages d'erreur
               echo "<div id='report_validation_message' style='margin-top: 10px; display: none;'></div>";
               
               // Validation côté client avec message dans la page
               echo "<script>
                  function validateForm() {
                     var select = document.querySelector('[name=\"report_type\"]');
                     var messageDiv = document.getElementById('report_validation_message');
                     
                     if (!select || select.value == '0' || select.value == '') {
                           messageDiv.innerHTML = '<div class=\"alert alert-warning\"><i class=\"fas fa-exclamation-triangle\"></i> " . __('Veuillez sélectionner un type de rapport', 'rp') . "</div>';
                           messageDiv.style.display = 'block';
                           
                           // Scroll vers le message
                           messageDiv.scrollIntoView({behavior: 'smooth'});
                           
                           return false;
                     } else {
                           messageDiv.style.display = 'none';
                           return true;
                     }
                  }
               </script>";
               
               $opt = [
                  'id' => 'rpmassubmit',
                  'onclick' => 'return validateForm();'
               ];
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
         case 'DoIt':
               // IMPORTANT : Vérifier le type de rapport AVANT tout traitement
               $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : '';
               
               // Sauvegarder la valeur pour préserver l'état du dropdown
               $_SESSION['massiveaction_selected_report_type'] = $report_type;
               
               // Validation du type de rapport - ARRÊTER ici si invalide
               if (empty($report_type) || $report_type == '0') {
                  $ma->addMessage(__("❌ Vous devez sélectionner un type de rapport", 'rp'));
                  
                  // Marquer tous les éléments comme échoués SANS les traiter
                  foreach ($ids as $key => $val) {
                     if ($val) {
                           $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                     }
                  }
                  // IMPORTANT : RETURN ici pour arrêter le traitement
                  return;
               }
               
               // Validation que le type de rapport est dans la liste autorisée
               $valid_types = ['entity_parrent1', 'entity_parrent2', 'auto'];
               if (!in_array($report_type, $valid_types)) {
                  $ma->addMessage(__("❌ Type de rapport invalide : " . $report_type, 'rp'));
                  
                  foreach ($ids as $key => $val) {
                     if ($val) {
                           $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                     }
                  }
                  // IMPORTANT : RETURN ici pour arrêter le traitement
                  return;
               }
               
               // Si on arrive ici, le type de rapport est valide
               // On peut nettoyer la session temporaire
               unset($_SESSION['massiveaction_selected_report_type']);
               
               // Traitement normal des éléments
               $tab_id = [];
               $has_errors = false;
               
               foreach ($ids as $key => $val) {
                  if ($val) {
                     $tab_id[] = $key;
                     
                     if ($item->getFromDB($key)) {
                           $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                     } else {
                           $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                           $ma->addMessage(__("Erreur lors du traitement de l'élément ID: " . $key));
                           $has_errors = true;
                     }
                  }
               }
               
               // Seulement rediriger si il n'y a pas d'erreurs ET qu'on a des éléments à traiter
               if (!$has_errors && count($tab_id) > 0) {
                  // Stockage en session
                  $_SESSION["plugin_rp"]["type"] = $item->getType();
                  $_SESSION["plugin_rp"]["tab_id"] = serialize($tab_id);
                  $_SESSION["plugin_rp"]["report_type"] = $report_type;
                                 
                  // Redirection vers la page d'export
                  echo "<script type='text/javascript'>
                           location.href='../plugins/rp/front/export.massive.php';
                        </script>";
               } elseif (count($tab_id) == 0) {
                  $ma->addMessage(__("❌ Aucun élément à traiter"));
               }
               
               return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }

   function exportZIP($SeePath, $pdfFiles){

      $doc        = new Document();
      $zip        = new ZipArchive();

      // Créez un nouveau fichier zip
      $FileName = '/RapportPDF_Export-'.date('Ymd-His').'.zip';
      $zipFileName = $SeePath . $FileName;
      if ($zip->open($zipFileName, ZipArchive::CREATE)!==TRUE) {
         exit("Impossible d'ouvrir le fichier <$zipFileName>\n");
      }

      // Ajoutez les fichiers PDF au fichier zip
      foreach($pdfFiles as $pdfFile) {
         $zip->addFile($pdfFile, basename($pdfFile));
      }

      // Fermez le fichier zip
      $zip->close();

      $input = ['name'        => addslashes('Rapport PDF : Export massif du - ' . date("Y-m-d à H:i:s")),
                'filename'    => addslashes($FileName),
                'filepath'    => addslashes('_plugins/rp/rapportsMass' . $FileName),
                'mime'        => 'application/zip',
                'users_id'    => Session::getLoginUserID(),
                //'entities_id' => $ticket_entities->entities_id,
                //'tickets_id'  => $Ticket_id,
                'is_recursive'=> 1];

      if($NewDoc = $doc->add($input)){
         message("<br>Documents enregistrés avec succès : <br><a href='".PLUGIN_RP_WEBDIR."/front/download.export.php?zipname=$zipFileName'>Télécharger les rapports en ZIP</a>", INFO);
      }else{
         message("Erreur lors de la création des rapports", ERROR);
      }

   }
}
