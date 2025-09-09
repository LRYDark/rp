<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpCriDetail extends CommonDBTM {

   static $rightname = "plugin_rp";
   
   static function getIcon() {
      return "fa-solid fa-file";
   }

   static function getTypeName($nb = 0) {
      return _n('Rapport / Prise en charge', 'Rapport / Prise en charge', $nb, 'rp');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Ticket' && Session::haveRight("plugin_rp_rapport_tech", READ) || Session::haveRight("plugin_rp_rapport_tech", CREATE) || Session::haveRight("plugin_rp_rapport_hotline", READ) || Session::haveRight("plugin_rp_rapport_hotline", CREATE)) {
         $nb = self::countForItem($item);
         switch ($item->getType()) {
            case 'Ticket' :
               if ($_SESSION['glpishow_count_on_tabs']) {
                  return self::createTabEntry(self::getTypeName($nb), $nb);
               } else {
                  return self::getTypeName($nb);
               }
            default :
               return self::getTypeName($nb);
         }
      }
      return '';
   }

   /**
    * @param $item    CommonDBTM object
   **/
   public static function countForItem(CommonGLPI $item) { 
      if(Session::haveRight("plugin_rt_rt", READ)){
         return countElementsInTable('glpi_plugin_rp_cridetails', ['id_ticket' => $item->getID()]); 
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI, $DB;
         self::addReports($item, $item->getField('id'));
      return true;
   }

   /**
      Formulaire ticket

    * @param \Ticket $ticket
    * @param array   $options
    */
   static function addReports(Ticket $ticket, $options = []) { //ticket formulaire
      global $DB, $CFG_GLPI;
      $UserID     = Session::getLoginUserID();
      $config     = PluginRpConfig::getInstance();
      $ID         = $ticket->fields['id'];
      $modal      = 'rp_cri_form' . $ID;

      // ===== Petit trait coloré sous les titres =====
      echo "<style>
      .rp-title {
         position: relative;
         padding-bottom: .25rem;
         display: inline-block;
      }
      .rp-title::after {
         content: '';
         position: absolute;
         left: 0;
         bottom: -2px;
         width: 85px;      /* longueur du trait (ajuste si besoin) */
         height: 2px;      /* épaisseur du trait */
         background: var(--rp-title-color, #0d6efd);
         border-radius: 2px;
      }
      </style>";

         if($config->fields['multi_display'] != 0){
            $multi_display = "ORDER BY date DESC LIMIT ".$config->fields['multi_display'];
         }else{
            $multi_display = "ORDER BY date DESC LIMIT 1";
         }      

// __________________________________________ FICHE DE PRISE EN CHARGE __________________________________________
      // ----- bouton génération fiche client -----  
      $crifiche = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0")->fetch_object();          

      if(Session::haveRight("plugin_rp_rapport_tech", CREATE) || Session::haveRight("plugin_rp_rapport_tech", READ)){
         // Bordure BLEUE (#007bff)
         echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #007bff;'>";
            echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

               // ===== Titre avec trait bleu =====
               echo "<h3 class='card-title mb-0'>
                        <span class='rp-title' style='--rp-title-color:#007bff'>
                           <i class='fa-regular fa-file-lines me-2'></i>".
                           __("Fiche de prise en charge", 'rp').
                        "</span>
                     </h3>";

               if(Session::haveRight("plugin_rp_rapport_tech", CREATE)){
                  $modalclient = 'form_client';
                  
                     // GENERATE        
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           // Libellé simplifié: Générer / Régénérer
                           if(!empty($crifiche->id_documents)){
                              if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                                 $ClientTitel = "Régénérer";
                              }else{$ClientTitel = "Générer";}
                           }else{
                              $ClientTitel = "Générer";
                           }

                           if(!empty($crifiche->id_documents)){

                              $usercrifiche = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 0 AND id_ticket= $ID")->fetch_object();
                              
                              if(Session::haveRight("plugin_rp_rapport_tech", UPDATE) || empty($usercrifiche->users_id)){
                                 echo "<div class='ms-auto d-inline-block'>";
                                 echo Html::submit($ClientTitel, [
                                 'name'    => 'showCriForm',
                                 'class'   => 'btn btn-primary',
                                 'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalclient\", " . json_encode($params) . "); return false;"
                                 ]);
                                 echo "</div>";
                              }
                           }else{
                              echo "<div class='ms-auto d-inline-block'>";
                              echo Html::submit($ClientTitel, [
                              'name'    => 'showCriForm',
                              'class'   => 'btn btn-primary',
                              'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalclient\", " . json_encode($params) . "); return false;"
                              ]);
                              echo "</div>";
                           }
               }
            echo "</div>"; // card-header

            echo "<div class='card-body'>";
               // __________________________________________
               if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                  if(empty($crifiche->id_documents)){
                     echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucune fiche de prise en charge générée !</div>";
                  }else{       
                     echo "<div class='table-responsive'>";
                        echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                           echo "<thead class='table-light'>";
                              echo "<tr>";
                                 echo "<th style='width:160px'>Date de création</th>";
                                 echo "<th style='width:150px'>Nom du signataire</th>";
                                 echo "<th style='width:230px'>Envoyer à</th>";
                                 echo "<th style='width:110px'>Fichier</th>";
                                 echo "<th>Nom du fichier</th>";
                              echo "</tr>";
                           echo "</thead>";
                           echo "<tbody>";
                                                      
                           $docdatafiche = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0 $multi_display";
                           $docdatafiche = $DB->doQuery($docdatafiche);
                     
                           while ($data = $DB->fetchArray($docdatafiche)) {
                                 $iddoc = $data["id_documents"]; 
                                 if(empty($data["email"])) {
                                    $data["email"] = "-";
                                 }
                                 $docfiche = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                         INNER JOIN `glpi_documents` 
                                                         ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                         WHERE id_documents = $iddoc")->fetch_object();
                     
                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";
                                    echo "<td>". $data["email"] ."</td>";

                                       if(empty($docfiche->filename)){
                                          echo "<td class='text-muted'>Document supprimé</td>";
                                          echo "<td class='text-muted'>-</td>";
                                       }else{
                                          $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/fiches/" . $docfiche->filename;
                                          if(file_exists($seepath)){
                                             echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a href='document.form.php?id=$iddoc'>". $docfiche->filename  ."</a></td>";
                                          }
                                          else{
                                             echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $docfiche->filename ."</a></td>";
                                          }
                                       }
                                 echo "</tr>";
                              }   

                           echo "</tbody>";
                        echo "</table>";
                     echo "</div>";
                  }
               }
            echo "</div>"; // card-body
         echo "</div>"; // card
      }

// __________________________________________ RAPPORT D'INTERVENTION __________________________________________
      // -------- bouton génération rapport -------
      $crirapport = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1")->fetch_object();

      if(Session::haveRight("plugin_rp_rapport_tech", CREATE) || Session::haveRight("plugin_rp_rapport_tech", READ)){
         // Bordure VERTE (#28a745)
        echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #28a745;'>";
            echo "<div class='card-header d-flex align-items-center justify-content-between'>";

               // ===== Titre avec trait vert =====
               echo "<h3 class='card-title mb-0'>
                        <span class='rp-title' style='--rp-title-color:#28a745'>
                           <i class='fa-regular fa-file-lines me-2'></i>".
                           __("Rapport d'intervention", 'rp').
                        "</span>
                     </h3>";

               if(Session::haveRight("plugin_rp_rapport_tech", CREATE)){
                  $modalrapport = 'form_rapport';

                  // GENERATE          
                     $params = ['job'        => $ticket->fields['id'],
                              'root_doc'   => PLUGIN_RP_WEBDIR];

                     // Libellé simplifié: Générer / Régénérer
                     if(!empty($crirapport->id_documents)){
                        if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                           $RapportTitel = "Régénérer";
                        }else{$RapportTitel = "Générer";}
                     }else{
                        $RapportTitel = "Générer";
                     }

                     if(!empty($crirapport->id_documents)){

                        $usercrirapport = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 1 AND id_ticket= $ID")->fetch_object();
                           
                        if(Session::haveRight("plugin_rp_rapport_tech", UPDATE) || empty($usercrirapport->users_id)){
                           echo "<div class='ms-auto d-inline-block'>";
                           echo Html::submit($ClientTitel, [
                           'name'    => 'showCriForm',
                           'class'   => 'btn btn-primary',
                           'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapport\", " . json_encode($params) . "); return false;"
                           ]);
                           echo "</div>";
                        }
                     }else{
                        echo "<div class='ms-auto d-inline-block'>";
                        echo Html::submit($ClientTitel, [
                        'name'    => 'showCriForm',
                        'class'   => 'btn btn-primary',
                        'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapport\", " . json_encode($params) . "); return false;"
                        ]);
                        echo "</div>";
                     }
               }
            echo "</div>";

            echo "<div class='card-body'>";
               // __________________________________________
               if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                  if(empty($crirapport->id_documents)){
                     echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucun rapport de généré !</div>";
                  }else{          
                     echo "<div class='table-responsive'>";
                        echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                           echo "<thead class='table-light'>";
                              echo "<tr>";
                                 echo "<th style='width:160px'>Date de création</th>";
                                 echo "<th style='width:150px'>Nom du signataire</th>";
                                 echo "<th style='width:230px'>Envoyer à</th>";
                                 echo "<th style='width:110px'>Fichier</th>";
                                 echo "<th>Nom du fichier</th>";
                              echo "</tr>";
                           echo "</thead>";
                           echo "<tbody>";

                           $docdatarapport = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1 $multi_display";
                           $docdatarapport = $DB->doQuery($docdatarapport);
                     
                           while ($data = $DB->fetchArray($docdatarapport)) {
                                 $iddoc = $data["id_documents"]; 
                                 if(empty($data["email"])) {
                                    $data["email"] = "-";
                                 }
                                 $docrapport = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                         INNER JOIN `glpi_documents` 
                                                         ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                         WHERE id_documents = $iddoc")->fetch_object();
                     
                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";
                                    echo "<td>". $data["email"] ."</td>";

                                       if(empty($docrapport->filename)){
                                          echo "<td class='text-muted'>Document supprimé</td>";
                                          echo "<td class='text-muted'>-</td>";
                                       }else{
                                          $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapports/" . $docrapport->filename;
                                          $seepathMassAction = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsMass/" . $docrapport->filename;
                                          if(file_exists($seepath) || file_exists($seepathMassAction)){
                                             echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a href='document.form.php?id=$iddoc'>". $docrapport->filename  ."</a></td>";
                                          }
                                          else{
                                             echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $docrapport->filename ."</a></td>";
                                          }
                                       }
                                 echo "</tr>";
                           }   

                           echo "</tbody>";
                        echo "</table>";
                     echo "</div>";
                  }
               }
            echo "</div>";
         echo "</div>";
      }

// __________________________________________ RAPPORT HOTLINE __________________________________________
      // -------- bouton génération rapport HOTLINE-------

         $crirapporthotline = $DB->doQuery("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2")->fetch_object();

         if(Session::haveRight("plugin_rp_rapport_hotline", CREATE) || Session::haveRight("plugin_rp_rapport_hotline", READ)){
            // Bordure JAUNE (#ffc107) — déjà présente
            echo "<div class='card shadow-sm mb-4' style='border-left: 4px solid #ffc107;'>";
               echo "<div class='card-header d-flex align-items-center justify-content-between' style='background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;'>";

                  // ===== Titre avec trait jaune =====
                  echo "<h3 class='card-title mb-0'>
                           <span class='rp-title' style='--rp-title-color:#ffc107'>
                              <i class='fa-regular fa-file-lines me-2'></i>".
                              __("Rapport d'intervention hotline", 'rp').
                           "</span>
                        </h3>";

                  if(Session::haveRight("plugin_rp_rapport_hotline", CREATE)){
                     $modalrapporthotline = 'form_rapport_hotline';

                     // GENERATE          
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           // Libellé simplifié: Générer / Régénérer
                           if(!empty($crirapporthotline->id_documents)){
                              if(Session::haveRight("plugin_rp_rapport_hotline", READ)){
                                 $RapportTitelHotline = "Régénérer";
                              }else{$RapportTitelHotline = "Générer";}
                           }else{
                              $RapportTitelHotline = "Générer";
                           }

                           if(!empty($crirapporthotline->id_documents)){

                              $usercrihotline = $DB->doQuery("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 2 AND id_ticket= $ID")->fetch_object();
                              
                              if(Session::haveRight("plugin_rp_rapport_hotline", UPDATE) || empty($usercrihotline->users_id)){
                                 echo "<div class='ms-auto d-inline-block'>";
                                 echo Html::submit($ClientTitel, [
                                 'name'    => 'showCriForm',
                                 'class'   => 'btn btn-primary',
                                 'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapporthotline\", " . json_encode($params) . "); return false;"
                                 ]);
                                 echo "</div>";
                              }
                           }else{
                              echo "<div class='ms-auto d-inline-block'>";
                              echo Html::submit($ClientTitel, [
                              'name'    => 'showCriForm',
                              'class'   => 'btn btn-primary',
                              'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapporthotline\", " . json_encode($params) . "); return false;"
                              ]);
                              echo "</div>";
                           }
                  }
               echo "</div>";

               echo "<div class='card-body'>";
                  // __________________________________________
                  if(Session::haveRight("plugin_rp_rapport_hotline", READ)){
                     if(empty($crirapporthotline->id_documents)){
                        echo "<div class='alert alert-info mb-0'><i class='fa-solid fa-circle-info' style='margin-top:4px;'></i>Aucun rapport de généré !</div>";
                     }else{          
                        echo "<div class='table-responsive'>";
                           echo "<table class='table table-sm table-striped table-hover align-middle mb-0'>";
                              echo "<thead class='table-light'>";
                                 echo "<tr>";
                                    echo "<th style='width:160px'>Date de création</th>";
                                    echo "<th style='width:150px'>Nom du technicien</th>";
                                    echo "<th style='width:230px'>Envoyer à</th>";
                                    echo "<th style='width:110px'>Fichier</th>";
                                    echo "<th>Nom du fichier</th>";
                                 echo "</tr>";
                              echo "</thead>";
                              echo "<tbody>";

                              $docdatahotline = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2 $multi_display";
                              $docdatahotline = $DB->doQuery($docdatahotline);
                        
                              while ($data = $DB->fetchArray($docdatahotline)) {
                                    $iddoc = $data["id_documents"]; 
                                    if(empty($data["email"])) {
                                       $data["email"] = "-";
                                    }
                                    $dochotline = $DB->doQuery("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                            INNER JOIN `glpi_documents` 
                                                            ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                            WHERE id_documents = $iddoc")->fetch_object();
                        
                                 echo "<tr>";
                                    echo "<td><span class='text-nowrap'>". $data["date"] ."</span></td>";
                                    echo "<td>". $data["nameclient"] ."</td>";
                                    echo "<td>". $data["email"] ."</td>";

                                       if(empty($dochotline->filename)){
                                          echo "<td class='text-muted'>Document supprimé</td>";
                                          echo "<td class='text-muted'>-</td>";
                                       }else{
                                          $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsHotline/" . $dochotline->filename;
                                          $seepathMassAction = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsMass/" . $dochotline->filename;
                                          if(file_exists($seepath) || file_exists($seepathMassAction)){
                                             echo "<td><a class='btn btn-sm btn-outline-secondary' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a href='document.form.php?id=$iddoc'>". $dochotline->filename  ."</a></td>";
                                          }
                                          else{
                                             echo "<td><a class='btn btn-sm btn-outline-secondary text-danger' href='document.send.php?docid=$iddoc' target='_blank'><i class='far fa-file-pdf me-1'></i>Ouvrir</a></td>";
                                             echo "<td><a class='text-danger' href='document.form.php?id=$iddoc'>". $dochotline->filename ."</a></td>";
                                          }
                                       }
                                 echo "</tr>";
                              }   

                              echo "</tbody>";
                           echo "</table>";
                        echo "</div>";
                     }
                  }
               echo "</div>";
            echo "</div>";
         }
   }
}
