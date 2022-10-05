<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpCriDetail extends CommonDBTM {

   static $rightname = "plugin_rp";
   
   static function getIcon() {
      return "fas fa-user-tie";
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Ticket' && Session::haveRight("plugin_rp_rapport_tech", READ)) {
         return PluginRpCri::getTypeName(1);
      }elseif ($item->getType() == 'Ticket' && Session::haveRight("plugin_rp_rapport_tech", CREATE)) {
         return PluginRpCri::getTypeName(1);
      }

      if ($item->getType() == 'Ticket' && Session::haveRight("plugin_rp_rapport_hotline", READ)) {
         return PluginRpCri::getTypeName(1);
      }elseif ($item->getType() == 'Ticket' && Session::haveRight("plugin_rp_rapport_hotline", CREATE)) {
         return PluginRpCri::getTypeName(1);
      }
      return '';
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
      $UserID = Session::getLoginUserID();

         $ID = $ticket->fields['id'];
         $modal = 'rp_cri_form' . $ID;

   echo "<table class='tab_cadre_fixe'>";
// __________________________________________ __________________________________________ __________________________________________
      // ----- bouton génération fiche client -----  
      $crifiche = $DB->query("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0")->fetch_object();          
                        
         echo "<table class='tab_cadre_fixe'>";

            if(Session::haveRight("plugin_rp_rapport_tech", CREATE) || Session::haveRight("plugin_rp_rapport_tech", READ)){
               echo "<tr class='tab_bg_1'><th>";
                  echo __("Fiche de prise en charge", 'rp');
               echo "</th></tr>";
            }

            if(Session::haveRight("plugin_rp_rapport_tech", CREATE)){
               $modalclient = 'form_client';
                  echo "<td class='center'>";
                  
                     // GENERATE        
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           if(!empty($crifiche->id_documents)){
                              if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                                 $ClientTitel = __("Régénérer une fiche de prise en charge", 'rp');
                              }else{$ClientTitel = __("Générer une fiche de prise en charge", 'rp');}
                           }else{
                              $ClientTitel = __("Générer une fiche de prise en charge", 'rp');
                           }

                           if(!empty($crifiche->id_documents)){

                              $usercrifiche = $DB->query("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 0 AND id_ticket= $ID")->fetch_object();
                              
                              if(Session::haveRight("plugin_rp_rapport_tech", UPDATE) || empty($usercrifiche->users_id)){
                                 echo Html::submit($ClientTitel, ['name'    => 'showCriForm',
                                 'class'   => 'btn btn-primary',
                                 'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalclient\", " . json_encode($params) . ");"]);
                              }
                           }else{
                              echo Html::submit($ClientTitel, ['name'    => 'showCriForm',
                              'class'   => 'btn btn-primary',
                              'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalclient\", " . json_encode($params) . ");"]);
                           }
            }
            echo "</td>";
         echo "</table>";
         // __________________________________________
         if(Session::haveRight("plugin_rp_rapport_tech", READ)){

            echo '<div class="table-responsive">';
               echo "<table class='tab_cadre center' width='80%'>";
                  echo "<tr>";
                     echo "<th width='160px'>Date de création</th>";
                     echo "<th width='150px'>Nom du signataire</th>";
                     echo "<th width='230px'>Envoyer à</th>";
                     echo "<th width='90px'>Fichier</th>";
                     echo "<th>Nom du fichier</th>";
                  echo "</tr>";
                                       
                  if(empty($crifiche->id_documents)){
                     echo "<table width='100%'>";
                        echo "<tr>";
                           echo "<td class='center'><br> Aucune fiche de prise en charge générée !</td>";
                        echo "</tr>";   
                     echo "</table>";
                  }else{       
                     $docdatafiche = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=0";
                     $docdatafiche = $DB->query($docdatafiche);
               
                     while ($data = $DB->fetchArray($docdatafiche)) {
                           $iddoc = $data["id_documents"]; 
                           if(empty($data["email"])) {
                              $data["email"] = "-";
                           }
                           $docfiche = $DB->query("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                   INNER JOIN `glpi_documents` 
                                                   ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                   WHERE id_documents = $iddoc")->fetch_object();
               
                           echo "<tr>";
                              echo "<td>". $data["date"] ."</td>";
                              echo "<td>". $data["nameclient"] ."</td>";
                              echo "<td>". $data["email"] ."</td>";

                                 if(empty($docfiche->filename)){
                                    echo "<td>Document supprimé</td>";
                                    echo "<td>-</td>";
                                 }else{
                                    $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/fiches/" . $docfiche->filename;
                                    if(file_exists($seepath)){
                                       echo "<td><a href='document.send.php?docid=$iddoc'><i class='far fa-file-pdf'></i> Ouvrir</a></td>";
                                       echo "<td><a href='document.form.php?id=$iddoc'>". $docfiche->filename  ."</a></td>";
                                    }
                                    else{
                                       echo "<td><a style='color : red;' href='document.send.php?docid=$iddoc'><i class='far fa-file-pdf'></i> Ouvrir</a></td>";
                                       echo "<td><a style='color : red;' href='document.form.php?id=$iddoc'>". $docfiche->filename ."</a></td>";
                                    }
                                 }
                           echo "</tr>";
                        }   
                  }
                                          
               echo "</table><br><br>";
            echo "</div>";
         }
// __________________________________________ __________________________________________ __________________________________________
      // -------- bouton génération repport -------
      $crirapport = $DB->query("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1")->fetch_object();

         echo "<table class='tab_cadre_fixe'>";

            if(Session::haveRight("plugin_rp_rapport_tech", CREATE) || Session::haveRight("plugin_rp_rapport_tech", READ)){
               echo "<tr class='tab_bg_1'><th>";
                  echo __("Rapport d'intervention", 'rp');
               echo "</th></tr>";
            }

            if(Session::haveRight("plugin_rp_rapport_tech", CREATE)){
               $modalrapport = 'form_rapport';
                  echo "<td class='center'>";

                  // GENERATE          
                     $params = ['job'        => $ticket->fields['id'],
                              'root_doc'   => PLUGIN_RP_WEBDIR];

                     if(!empty($crirapport->id_documents)){
                        if(Session::haveRight("plugin_rp_rapport_tech", READ)){
                           $RapportTitel = __("Régénérer un rapport d'intervention", 'rp');
                        }else{$RapportTitel = __("Générer un rapport d'intervention", 'rp');}
                     }else{
                        $RapportTitel = __("Générer un rapport d'intervention", 'rp');
                     }

                     if(!empty($crirapport->id_documents)){

                        $usercrirapport = $DB->query("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 1 AND id_ticket= $ID")->fetch_object();
                           
                        if(Session::haveRight("plugin_rp_rapport_tech", UPDATE) || empty($usercrirapport->users_id)){
                           echo Html::submit($RapportTitel, ['name'    => 'showCriForm',
                           'class'   => 'btn btn-primary',
                           'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapport\", " . json_encode($params) . ");"]);
                        }
                     }else{
                        echo Html::submit($RapportTitel, ['name'    => 'showCriForm',
                        'class'   => 'btn btn-primary',
                        'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapport\", " . json_encode($params) . ");"]);
                     }
            }
            echo "</td>";
         echo "</table>";
         // __________________________________________
         if(Session::haveRight("plugin_rp_rapport_tech", READ)){

            echo '<div class="table-responsive">';
               echo "<table class='tab_cadre center' width='80%'>";
                  echo "<tr>";
                     echo "<th width='160px'>Date de création</th>";
                     echo "<th width='150px'>Nom du signataire</th>";
                     echo "<th width='230px'>Envoyer à</th>";
                     echo "<th width='90px'>Fichier</th>";
                     echo "<th>Nom du fichier</th>";
                  echo "</tr>";     

                  if(empty($crirapport->id_documents)){
                     echo "<table width='100%'>";
                        echo "<tr>";
                           echo "<td class='center'><br> Aucun rapport de généré !</td>";
                        echo "</tr>";   
                     echo "</table>";

                  }else{          
                     $docdatarapport = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=1";
                     $docdatarapport = $DB->query($docdatarapport);
               
                     while ($data = $DB->fetchArray($docdatarapport)) {
                           $iddoc = $data["id_documents"]; 
                           if(empty($data["email"])) {
                              $data["email"] = "-";
                           }
                           $docrapport = $DB->query("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                   INNER JOIN `glpi_documents` 
                                                   ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                   WHERE id_documents = $iddoc")->fetch_object();
               
                           echo "<tr>";
                              echo "<td>". $data["date"] ."</td>";
                              echo "<td>". $data["nameclient"] ."</td>";
                              echo "<td>". $data["email"] ."</td>";

                                 if(empty($docrapport->filename)){
                                    echo "<td>Document supprimé</td>";
                                    echo "<td>-</td>";
                                 }else{
                                    $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapports/" . $docrapport->filename;
                                    if(file_exists($seepath)){
                                       echo "<td><a href='document.send.php?docid=$iddoc'><i class='far fa-file-pdf'></i> Ouvrir</a></td>";
                                       echo "<td><a href='document.form.php?id=$iddoc'>". $docrapport->filename  ."</a></td>";
                                    }
                                    else{
                                       echo "<td><a style='color : red;' href='document.send.php?docid=$iddoc'><i class='far fa-file-pdf'></i> Ouvrir</a></td>";
                                       echo "<td><a style='color : red;' href='document.form.php?id=$iddoc'>". $docrapport->filename ."</a></td>";
                                    }
                                 }
                           echo "</tr>";
                     }   
                  }
               echo "</table><br><br>";
            echo "</div>";
         }
// __________________________________________ __________________________________________ __________________________________________
      // -------- bouton génération repport HOTLINE-------

         $crirapporthotline = $DB->query("SELECT id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2")->fetch_object();

            echo "<table class='tab_cadre_fixe'>";
            
               if(Session::haveRight("plugin_rp_rapport_hotline", CREATE) || Session::haveRight("plugin_rp_rapport_hotline", READ)){
                     echo "<tr class='tab_bg_1'><th>";
                        echo __("Rapport d'intervention hotline", 'rp');
                     echo "</th></tr>";
               }

               if(Session::haveRight("plugin_rp_rapport_hotline", CREATE)){
                  $modalrapporthotline = 'form_rapport_hotline';
                     echo "<td class='center'>";

                     // GENERATE          
                        $params = ['job'        => $ticket->fields['id'],
                                 'root_doc'   => PLUGIN_RP_WEBDIR];

                           if(!empty($crirapporthotline->id_documents)){
                              if(Session::haveRight("plugin_rp_rapport_hotline", READ)){
                                 $RapportTitelHotline = __("Régénérer un nouveau rapport", 'rp');
                              }else{$RapportTitelHotline = __("Générer un rapport d'intervention", 'rp');}
                           }else{
                              $RapportTitelHotline = __("Générer un rapport d'intervention", 'rp');
                           }

                           if(!empty($crirapporthotline->id_documents)){

                              $usercrihotline = $DB->query("SELECT users_id FROM `glpi_plugin_rp_cridetails` WHERE users_id= $UserID AND type = 2 AND id_ticket= $ID")->fetch_object();
                              
                              if(Session::haveRight("plugin_rp_rapport_hotline", UPDATE) || empty($usercrihotline->users_id)){
                                 echo Html::submit($RapportTitelHotline, ['name'    => 'showCriForm',
                                 'class'   => 'btn btn-primary',
                                 'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapporthotline\", " . json_encode($params) . ");"]);
                              }
                           }else{
                              echo Html::submit($RapportTitelHotline, ['name'    => 'showCriForm',
                              'class'   => 'btn btn-primary',
                              'onclick' => "rp_loadCriForm(\"showCriForm\", \"$modalrapporthotline\", " . json_encode($params) . ");"]);
                           }
               }
               echo "</td>";
            echo "</table>";
         // __________________________________________
         if(Session::haveRight("plugin_rp_rapport_hotline", READ)){

            echo '<div class="table-responsive">';
               echo "<table class='tab_cadre center' width='80%'>";
                  echo "<tr>";
                     echo "<th width='160px'>Date de création</th>";
                     echo "<th width='150px'>Nom du technicien</th>";
                     echo "<th width='230px'>Envoyer à</th>";
                     echo "<th width='90px'>Fichier</th>";
                     echo "<th>Nom du fichier</th>";
                  echo "</tr>"; 
                  
                  if(empty($crirapporthotline->id_documents)){
                     echo "<table width='100%'>";
                        echo "<tr>";
                           echo "<td class='center'><br> Aucun rapport de généré !</td>";
                        echo "</tr>";   
                     echo "</table>";

                  }else{          
                     $docdatahotline = "SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket= $ID AND type=2";
                     $docdatahotline = $DB->query($docdatahotline);
               
                     while ($data = $DB->fetchArray($docdatahotline)) {
                           $iddoc = $data["id_documents"]; 
                           if(empty($data["email"])) {
                              $data["email"] = "-";
                           }
                           $dochotline = $DB->query("SELECT filename FROM `glpi_plugin_rp_cridetails`
                                                   INNER JOIN `glpi_documents` 
                                                   ON (`glpi_plugin_rp_cridetails`.`id_documents` = `glpi_documents`.`id`) 
                                                   WHERE id_documents = $iddoc")->fetch_object();
               
                        echo "<tr>";
                           echo "<td>". $data["date"] ."</td>";
                           echo "<td>". $data["nameclient"] ."</td>";
                           echo "<td>". $data["email"] ."</td>";

                              if(empty($dochotline->filename)){
                                 echo "<td>Document supprimé</td>";
                                 echo "<td>-</td>";
                              }else{
                                 $seepath = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsHotline/" . $dochotline->filename;
                                 if(file_exists($seepath)){
                                    echo "<td><a href='document.send.php?docid=$iddoc'><i class='far fa-file-pdf'></i> Ouvrir</a></td>";
                                    echo "<td><a href='document.form.php?id=$iddoc'>". $dochotline->filename  ."</a></td>";
                                 }
                                 else{
                                    echo "<td><a style='color : red;' href='document.send.php?docid=$iddoc'><i class='far fa-file-pdf'></i> Ouvrir</a></td>";
                                    echo "<td><a style='color : red;' href='document.form.php?id=$iddoc'>". $dochotline->filename ."</a></td>";
                                 }
                              }
                        echo "</tr>";
                     }   
                  }
               echo "</table><br><br>";
            echo "</div>";
         }
   }
}
