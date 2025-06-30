<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to tdis file");
}

class PluginRpCri extends CommonDBTM {

   static $rightname = 'plugin_rp_cri_create';

   static function getTypeName($nb = 0) {
      return _n('Rapport / Prise en charge', 'Rapport / Prise en charge', $nb, 'rp');
   }

   public function getEntityGroupFromEntityId($entityId, $config1, $config2) {
      global $DB;
   
      // 1. Récupérer le chemin complet de l'entité
      $query = "SELECT completename
               FROM glpi_entities
               WHERE id = " . (int)$entityId;

      $result = $DB->doQuery($query);
      if (!$result || $DB->numrows($result) == 0) {
         return null; // Entité non trouvée
      }

      $row = $DB->fetchassoc($result);
      $completeName = $row['completename']; // ex: "Entité racine > AUTRES > AUTRES2"

      // 2. Découper la hiérarchie
      $entities = array_map('trim', explode('>', $completeName));

      // 3. Vérifier si l'entité fait partie d'un des groupes configurés
      if (!empty($config1) && in_array($config1, $entities)) {
         return 'entity_parrent1';
      }

      if (!empty($config2) && in_array($config2, $entities)) {
         return 'entity_parrent2';
      }

      // 4. Sinon, aucun groupe trouvé
      return 'autre';
   }


   public function showForm($ID, $options = []) {
      global $DB, $CFG_GLPI;

      $config = PluginRpConfig::getInstance();
      $job    = new Ticket();
      $plugin = new Plugin();
      $job->getfromDB($ID);
      $img_sum_task = 0;
      $img_sum_suivi = 0;
      $sumtask = 0;

      $params = ['job'         => $ID,
                 'form'       => 'formReport',
                 'root_doc'   => PLUGIN_RP_WEBDIR];

      if($config->fields['use_publictask'] == 1){
         $is_private = "AND is_private = 0";
      }else{
         $is_private = "";
      }         
         //---------------------SQL / VAR ----------------------
         $result = $DB->doQuery("SELECT * FROM glpi_tickets INNER JOIN glpi_entities 
         ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $ID")->fetch_object();
                           
         $resultclient = $DB->doQuery("SELECT * FROM glpi_plugin_rp_dataclient WHERE id_ticket = $ID")->fetch_object();

         //---------------------SQL / VAR ----------------------
            if(!empty($resultclient->id_ticket)){
               $society = $resultclient->society;
               $town = $resultclient->town;
               $address = $resultclient->address;
               $postcode = $resultclient->postcode;
               $phone = $resultclient->phone;
               $email = $resultclient->email;
               if($resultclient->email == ''){
                  $email = $result->email;
               }
               $serialnumber = $resultclient->serial_number;
            }else{
               $society = $result->comment;
               if(empty($society)){
                  $society = $result->completename;
               }
               $town = $result->town;
               $address = $result->address;
               $postcode = $result->postcode;
               $phone = $result->phonenumber;
               $email = $result->email;
               $serialnumber = "";
            }
         //---------------------SQL / VAR ----------------------
         ?>
            <style> /*Style du modale et du tableau */
               .modal-dialog { 
                  max-width: 1050px; 
                  margin: 1.75rem auto; 
               }
               .table td, .table td { 
                  border: none !important;
               }
            </style>
         <?php
         
         echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/front/cripdf.form.php\" method=\"post\" name=\"formReport\">";

         echo Html::hidden('REPORT_ID', ['value' => $ID]);

         // tableau bootstrap -> glpi
         $querytask = "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $ID $is_private";
         $resulttask = $DB->doQuery($querytask);
         $numbertask = $DB->numrows($resulttask);

         echo '<div class="table-responsive">';
         echo "<table class='table'>"; 
   
         if($_POST["modal"] != "form_client" && $numbertask > 0 || $_POST["modal"] == "form_client"){
            if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0){
               // Récupération des noms des entités
               $entity_parrent1_id = $config->fields['entity_parrent1'];
               $entity_parrent1 = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent1_id")->fetch_object();

               $entity_parrent2_id = $config->fields['entity_parrent2'];
               $entity_parrent2 = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent2_id")->fetch_object();

               // fonction
               $group = $this->getEntityGroupFromEntityId($result->id, $entity_parrent1->name, $entity_parrent2->name);

               echo "<tr>";
                  echo "<td style='width: 26%;' class='table-info'>";
                     echo 'Type de rapport:';
                  echo "</td>";
                  echo "<td>";
                     // Détermination de la sélection
                     $checked1 = ($group == 'entity_parrent1' || ($group != 'entity_parrent2')) ? 'checked' : '';
                     $checked2 = ($group == 'entity_parrent2') ? 'checked' : '';

                     echo '<label>';
                        echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent1\" $checked1>";
                        echo $entity_parrent1->name;
                     echo '</label><br>';

                     echo '<label>';
                        echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent2\" $checked2>";
                        echo $entity_parrent2->name;
                     echo '</label>';

                  echo "</td>";
               echo "</tr>";
            }
            if ($config->fields['entity_parrent1'] == 0 && $config->fields['entity_parrent2'] != 0){
               echo '<input name="entity_parrent" type="hidden" value="entity_parrent2" />';
            }
            if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] == 0){
               echo '<input name="entity_parrent" type="hidden" value="entity_parrent1" />';
            }
            if ($config->fields['entity_parrent1'] == 0 && $config->fields['entity_parrent2'] == 0){
               echo '<input name="entity_parrent" type="hidden" value="entity_parrent1" />';
            }
         }
   
         if($_POST["modal"] != "form_client" && $numbertask > 0){
            $description = $result->content;
            echo "<tr>";
               echo "<td style='width: 26%;' class='table-info'>";
                  echo 'Description du Problème :';
               
                  if($_POST["modal"] == "form_rapport_hotline" || $_POST["modal"] == "form_client"){
                     $checked = "checked";
                  }else{
                     $checked = "";
                  }
                  echo "<br>";
                  echo 'Visible dans le rapport <input type="checkbox" value="check" name="CHECK_DESCRIPTION_TICKET" '.$checked.'>';
               echo "</td>";

               echo "<td>";
                  Html::textarea([
                     'name'              => 'DESCRIPTION_TICKET',
                     'value'             => Glpi\RichText\RichText::getSafeHtml($description, true),
                     'enable_richtext'   => true,
                     'enable_fileupload' => false,
                     'enable_images'     => false,
                  ]);
               echo "</td>";
            echo "</tr>";
         }elseif($_POST["modal"] == "form_client"){
            $description = $result->content;
            echo "<tr>";
               echo "<td style='width: 26%;' class='table-info'>";
                  echo 'Description du Problème :';

               echo Html::hidden('CHECK_DESCRIPTION_TICKET', ['value' => 'check']);
               echo "<td>";
                  Html::textarea([
                     'name'              => 'DESCRIPTION_TICKET',
                     'value'             => Glpi\RichText\RichText::getSafeHtml($description, true),
                     'enable_richtext'   => true,
                     'enable_fileupload' => false,
                     'enable_images'     => false,
                  ]);
               echo "</td>";
            echo "</tr>";
         }

         // ---- formulaire client-------------------------------   
         if($_POST["modal"] == "form_client"){
         echo "</table><br>";
         // --- infos client ----------------------------------------------
         echo "<table class='table'>";
               $items = $DB->doQuery("SELECT requesttypes_id FROM `glpi_tickets` WHERE id = $ID")->fetch_object();     

            if($items->requesttypes_id == 1){
               ?>
                  <script>
                     //--------------------------------------------------- script eyes password
                     const togglePassword = document.querySelector('#togglePassword');
                     const password = document.querySelector('#id_password');
                     
                     togglePassword.addEventListener('click', function (e) {
                        // toggle the type attribute
                        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                        password.setAttribute('type', type);
                        // toggle the eye slash icon
                        this.classList.toggle('fa-eye-slash');
                     });
                  </script>
               <?php
               // TABLEAU 4
               echo "<tr>";
                  echo "<td style='width: 28%;' class='table-secondary'>";
                     echo 'Informations PC :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="serialnumber">Numéro de serie</label><br>';
                     echo '<input type="text" name="serialnumber" required="" placeholder="Numéro de serie" value="'.$serialnumber.'">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="model">Marque / Model</label><br>';
                     echo '<input type="text" name="model" placeholder="Marque / Model">';
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Personne en charge du matériel :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="NameRespMat">Nom / Prénom</label><br>';
                     echo '<input type="text" name="NameRespMat" required="" placeholder="Nom du Responsable matériel">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="CoordRespMat">Téléphone / Mail</label><br>';
                     echo '<input type="text" name="CoordRespMat" required="" placeholder="Mail/Tel du Responsable matériel">';
                  echo "</td>";
               echo "</tr>";

               ?>
               <script>
                  $(function(){
                     var mySpan = $("#bar").hide();
                     $("#foo").click(function(){
                        if($(this).is(":checked"))
                        mySpan.show();
                        else
                        mySpan.hide();
                     });
                  });
               </script>
               <?php
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo '';
                  echo "</td>";

                  echo "<td>";
                        echo "<label for='equal'>L'utilisateur du materiel est différent de <br> la personne l'ayant pris en charge ?</label>";
                     echo '<input type="checkbox" name="equal" value="equal" id="foo">';
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 4
               echo "<tr id='bar'>";
                  echo "<td class='table-secondary'>";
                     echo 'Utilisateur du matériel :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="NameRespMat">Nom / Prénom</label><br>';
                     echo '<input type="text" name="NameUtilpMat" placeholder="Nom de l utilisateur">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="CoordRespMat">Téléphone / Mail</label><br>';
                     echo '<input type="text" name="CoordUtilpMat" placeholder="Mail/Tel de l utilisateur">';
                  echo "</td>";
               echo "</tr>";

               echo "<td>";
                  echo '';
               echo "</td>";
            
               //---------------------------------------------------------------
               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Accessoires :';
                  echo "</td>";

                  echo "<td>";
                     echo '<input type="checkbox" name="mouse" value="Souris / "> Souris <br>';
                     echo '<input type="checkbox" name="keyboard" value="Clavier / "> Clavier <br>';
                     echo '<input type="checkbox" name="bag" value="Sachoche / "> Sachoche <br>';
                     echo '<input type="checkbox" name="feed" value="Alimentation / "> Alimentation <br>';
                     echo '<input type="checkbox" name="dockstation" value="Dock Station / "> Dock Station <br>';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="other">Autres :</label><br>';
                     echo '<textarea cols="20" rows="3" name="other" maxlengtd="100" placeholder="Autre(s) accessoire(s)"></textarea>';
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Sauvegarde des données ?';
                  echo "</td>";

                  echo "<td>";
                     echo '<input type="radio" name="DataSave" value="Oui" checked> OUI &emsp;&emsp;&emsp;';
                     echo '<input type="radio" name="DataSave" value="Non"> NON';
                  echo "</td>";
               echo "</tr>";

               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Formatage autorisé ?';
                  echo "</td>";

                  echo "<td>";
                     echo '<input type="radio" name="DataFormatting" value="Oui"> OUI &emsp;&emsp;&emsp;';
                     echo '<input type="radio" name="DataFormatting" value="Non" checked> NON';
                  echo "</td>";
               echo "</tr>"; 

               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Informations de session :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="session">Nom d ouverture de session</label><br>';
                     echo '<input type="text" name="idsession" autocomplete="off" placeholder="Nom d ouverture de session">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="password">Mot de passe</label><br>';
                     echo '<input type="password" name="userpassword" autocomplete="new-password" required="" id="id_password" placeholder="Mot de passe">';
                     echo ' <i class="far fa-eye" id="togglePassword" style="margin-left: -30px; cursor: pointer;"></i>';
                  echo "</td>";
               echo "</tr>";
            }
               echo "<td>";
                  echo '';
               echo "</td>";
               
             // TABLEAU 1
            echo "<tr>";
               echo "<td style='width: 28%;' class='table-secondary'>";
                  echo 'Nom de la Société / Client* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="text" required="" id="society" name="society" value="'.$society.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-secondary'>";
                  echo 'Adresse* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="text" id="address" required="" name="address" value="'.$address.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-secondary'>";
                  echo 'Ville* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="text" id="town" required="" name="town" value="'.$town.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-secondary'>";
                  echo 'Code postal* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="tel" id="postcode" required="" name="postcode" value="'.$postcode.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-secondary'>";
                  echo 'N° de téléphone :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="tel" id="phone" name="phone" value="'.$phone.'">';
               echo "</td>";
            echo "</tr>";

            // --- infos client ----------------------------------------------                 
            echo "<input type='hidden' name='Form' value='FormClient' />";
            
            }elseif($_POST["modal"] == "form_rapport" || $_POST["modal"] == "form_rapport_hotline"){
               echo "<input type='hidden' name='Form' value='FormRapport' />";

               $querytask = "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $ID $is_private";
               $resulttask = $DB->doQuery($querytask);
               $numbertask = $DB->numrows($resulttask);

               if($numbertask > 0){
                  $i=1;
                  while ($data = $DB->fetchArray($resulttask)) {
                     $checked = "";

                     echo "<tr>";
                        echo "<td style='width: 25%;' class='table-warning'>";
                        if($i == 1){
                           echo '<H3>Liste des tâches : </H3><br>';
                        }
                        echo 'Tache N°'.$i++.'';
                        if ($data['is_private'] == 1) echo ' - <span style="color:red"> Privée <i class="ti ti-lock" aria-label="Privé"></i></span>';
                           echo'<br><h5 style="font-weight: normal; margin-top: -0px;">'.$data["date"].' - '.$data['name'].'</h5>';
                              //selection avant ajout dans le pdf
                                 if($config->fields['choice'] == 1){
                                    if($config->fields['check_public_task'] == 1 && $data['is_private'] == 0){
                                       $checked = "checked";
                                    }
                                    if($config->fields['check_private_task'] == 1 && $data['is_private'] == 1){
                                       $checked = "checked";
                                    }
                                    echo 'Visible dans le rapport <input type="checkbox" value="check" name="tasks_pdf_'.$data['id'].'" '.$checked.'>';
                                 }else{
                                    echo '<input type="hidden" value="check" name="tasks_pdf_'.$data['id'].'" checked/>';
                                 }
                                 echo '<input type="hidden" value="'.$data["date"].'" name="tasks_date_'.$data['id'].'" />';
                                 echo '<input type="hidden" value="'.$data["actiontime"].'" name="tasks_time_'.$data['id'].'" />';
                                 echo '<input type="hidden" value="'.$data["name"].'" name="tasks_name_'.$data['id'].'" />';

                              //récupération de l'ID de l'image s'il y en a une.
                              $IdImg = $data['id'];
                              $ImgIdDoc = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
                              if (isset($ImgIdDoc->documents_id)){
                                 $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
                              }
                              if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
                                 $img_sum_task ++;
                              }
                              $sumtask += $data["actiontime"];
                                 
                              //selection avant ajout dans le pdf
                        echo "</td>";
         
                        echo "<td>";
                           Html::textarea([
                              'name'              => 'TASKS_DESCRIPTION'.$data['id'],
                              'value'             => Glpi\RichText\RichText::getSafeHtml($data["content"], true),
                              'enable_richtext'   => true,
                              'enable_fileupload' => false,
                              'enable_images'     => false,
                           ]);
                        echo "</td>";
                     echo "</tr>";
                  }
               }else{
                  header("Refresh:0");
                  echo "<div class='alert alert-important alert-warning d-flex'>";
                  echo "<b>" . __("Vous ne pouvez pas générer de rapport sans tâche(s).") . "</b></div>";
                  exit; 
               }

               $querysuivi = "SELECT glpi_itilfollowups.id, content, date, name, is_private FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $ID $is_private";
               $resultsuivi = $DB->doQuery($querysuivi);
               $numbersuivi = $DB->numrows($resultsuivi);

               if($numbersuivi > 0){
                  $i=1;
                  while ($dataSuivi = $DB->fetchArray($resultsuivi)) {
                     $descriptionSuivi = $dataSuivi["content"];
                     $dateSuivi = $dataSuivi["date"]; 
                     $checked = "";

                     echo "<tr>";
                        echo "<td style='widtd: 25%;' class='table-active'>";
                        if($i == 1){
                           echo '<H3>Liste des suivis : </H3><br>';
                        }
                        echo 'Suivi N°'.$i++.'';
                        if ($dataSuivi['is_private'] == 1) echo ' - <span style="color:red"> Privé <i class="ti ti-lock" aria-label="Privé"></i></span>';
                           echo'<br><h5 style="font-weight: normal; margin-top: -0px;">'.$dateSuivi.' - '.$dataSuivi['name'].'</h5>';
                              //selection avant ajout dans le pdf
                                 if($config->fields['choice'] == 1){
                                    if($config->fields['check_public_suivi'] == 1 && $dataSuivi['is_private'] == 0){
                                       $checked = "checked";
                                    }
                                    if($config->fields['check_private_suivi'] == 1 && $dataSuivi['is_private'] == 1){
                                       $checked = "checked";
                                    }
                                    echo 'Visible dans le rapport <input type="checkbox" value="check" name="suivis_pdf_'.$dataSuivi['id'].'" '.$checked.'>';
                                 }else{
                                    echo '<input type="hidden" value="check" name="suivis_pdf_'.$dataSuivi['id'].'" checked/>';
                                 }
                                 echo '<input type="hidden" value="'.$dataSuivi["date"].'" name="suivis_date_'.$dataSuivi['id'].'" />';
                                 echo '<input type="hidden" value="'.$dataSuivi["name"].'" name="suivis_name_'.$dataSuivi['id'].'" />';
                                 
                              //récupération de l'ID de l'image s'il y en a une.
                              $IdImg = $dataSuivi['id'];
                              $ImgIdDoc = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
                              if (isset($ImgIdDoc->documents_id)){
                                 $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
                              }
                              if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
                                 $img_sum_suivi ++;
                              }
                           //selection avant ajout dans le pdf
                        echo "</td>";
         
                        echo "<td>";
                           Html::textarea([
                              'name'              => 'SUIVIS_DESCRIPTION'.$dataSuivi['id'],
                              'value'             => Glpi\RichText\RichText::getSafeHtml($descriptionSuivi, true),
                              'enable_richtext'   => true,
                              'enable_fileupload' => false,
                              'enable_images'     => false,
                           ]);
                        echo "</td>";
                     echo "</tr>";
                  }
               }
               // Affichage du temps d'intervention
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo "Affichage du temps d'intervention";
                  echo "</td>";

                  echo "<td>";
                        echo '<input type="checkbox" name="rapporttime" value="yes" checked>';
                        echo "\t".mb_convert_encoding(floor($sumtask / 3600).str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8');
                  echo "</td>";
               echo "</tr>";

               // Affichage des images tâches
               if($img_sum_task != 0){
                  $checkedimgtask = "";
                  echo "<tr>";
                     echo "<td class='table-secondary'>";
                        echo "Afficher les images des tâches";
                     echo "</td>";

                     if($config->fields['ImgTasks'] == 1){
                        $checkedimgtask = "checked";
                     }
                     echo "<td>";
                           echo '<input type="checkbox" name="rapportimgtask" value="yes" '.$checkedimgtask.'>';
                           echo "\t".$img_sum_task.' Image(s)';
                     echo "</td>";
                  echo "</tr>";
               }

               // Affichage des images suivi
               if($img_sum_suivi != 0){
                  $checkedimgsuivis = "";
                  echo "<tr>";
                     echo "<td class='table-secondary'>";
                        echo "Afficher les images des suivis";
                     echo "</td>";

                     if($config->fields['ImgSuivis'] == 1){
                        $checkedimgsuivis = "checked";
                     }
                     echo "<td>";
                           echo '<input type="checkbox" name="rapportimgsuivi" value="yes" '.$checkedimgsuivis.'>';
                           echo "\t".$img_sum_suivi.' Image(s)';
                     echo "</td>";
                  echo "</tr>";
               }
            }

            //----------------------------------------------------------
            $signature = "false";
            if ($_POST["modal"]  == "form_rapport_hotline" && $config->fields['sign_rp_hotl'] == 1)$signature = "true";
            if ($_POST["modal"]  == "form_rapport" && $config->fields['sign_rp_tech'] == 1)$signature = "true";
            if ($_POST["modal"]  == "form_client" && $config->fields['sign_rp_charge'] == 1)$signature = "true";

            if($signature == 'true'){
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo ' ';
                  echo "</td>";

                  echo "<td>";
                     echo '<b> ______________ SIGNATURE CLIENT ______________<b>';
                  echo "</td>";
               echo "</tr>";
               // TABLEAU 1
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Nom / Prenom du client';
                  echo "</td>";

                  echo "<td>";
                     echo "<input type='text' id='name' name='name' placeholder='Nom / Prenom du client' required=''>";
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 3
               echo "<tr>";
                  echo "<td class='table-secondary'>";
                     echo 'Signature client';
                  echo "</td>";

                  echo "<td>";
                     //echo "<canvas id='sig-canvas' class='sig' widtd='320' height='80'></canvas>";
                     echo "<canvas id='sig-canvas' class='sig' value='sig-image' width='320' height='80' style='border: 1px solid black;'></canvas>";
                        ?><style>
                           #sig-canvas {
                           border: 1px solid #ccc;
                           border-radius: 6px;
                           background-color: #ffffff;
                           box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                           }
                        </style><?php
                  echo "</td>";
               echo "</tr>";
            }
            // Mail
            echo "<tr>";
               echo "<td class='table-secondary'>";
                  echo 'Mail client';
                  if ($config->fields['email'] == 1){
                     echo'<br><h5 style="font-weight: normal; margin-top: -0px;"> Cocher pour envoyer le PDF par email. </h5>';
                  }
               echo "</td>";

               echo "<td>";
                  if ($config->fields['email'] == 1){
                     echo '<input type="checkbox" name="mailtoclient" value="1">&emsp;';
                  }
                  echo "<input type='mail' id='mail' name='email' value='".$email."' style='widtd: 250px;'>";
               echo "</td>";
            echo "</tr>";
         
            //TABLEAU 4 BOUTON generation pdf
            echo "<tr>";
               echo "<td>";
                  echo '';
               echo "</td>";

               echo "<td>";
                  echo "<input type='submit' name='add_cri' id='sig-submitBtn' value='Génération du PDF' class='submit'>";
               echo "</td>";
            echo "</tr>";
         echo "</table>"; 
      echo "</div>";
         
      if($_POST["modal"] != "form_rapport_hotline"){
         echo'<textarea readonly name="url" id="sig-dataUrl" class="form-control" rows="0" cols="150" style=" color: transparent; border: none; background: none; outline: none;  resize : none; "></textarea>';
      }
      if($_POST["modal"] == "form_rapport_hotline"){
         echo "<input type='hidden' name='Form' value='FormRapportHotline'/>";
      }

      Html::closeForm();

      /*if($_POST["modal"] == "form_client"){
         //TABLEAU  BOUTON clear signature 
         echo "<div style=' position: absolute; margin-top: -31%; margin-left: 24.5%;'><button id='sig-clearBtn' class='resetButton'>Supprimer signature</button></div>";        
      }elseif($_POST["modal"] == "form_rapport"){
         //TABLEAU  BOUTON clear signature
         echo "<div style=' position: absolute; margin-top: -21%; margin-left: 33.5%;'><button id='sig-clearBtn' class='resetButton'>Supprimer signature</button></div>";
      }*/
         ?>
         <script>
            //--------------------------------------------------- signature
               window.requestAnimFrame = (function(callback) {
                  return window.requestAnimationFrame ||
                     window.webkitRequestAnimationFrame ||
                     window.mozRequestAnimationFrame ||
                     window.oRequestAnimationFrame ||
                     window.msRequestAnimaitonFrame ||
                     function(callback) {
                     window.setTimeout(callback, 1000 / 60);
                     };
               })();

               var canvas = document.getElementById("sig-canvas");
               var ctx = canvas.getContext("2d");
               ctx.strokeStyle = "#222222";
               ctx.lineWidtd = 1;

               var drawing = false;
               var mousePos = {
                  x: 0,
                  y: 0
               };
               var lastPos = mousePos;

               canvas.addEventListener("mousedown", function(e) {
                  drawing = true;
                  lastPos = getMousePos(canvas, e);
               }, false);

               canvas.addEventListener("mouseup", function(e) {
                  drawing = false;
               }, false);

               canvas.addEventListener("mousemove", function(e) {
                  mousePos = getMousePos(canvas, e);
               }, false);

               // Add touch event support for mobile
               canvas.addEventListener("touchmove", function(e) {
                  var touch = e.touches[0];
                  e.preventDefault(); 
                  var me = new MouseEvent("mousemove", {
                     clientX: touch.clientX,
                     clientY: touch.clientY
                  });
                  canvas.dispatchEvent(me);
               }, false);

               canvas.addEventListener("touchstart", function(e) {
                  mousePos = getTouchPos(canvas, e);
                  e.preventDefault(); 
                  var touch = e.touches[0];
                  var me = new MouseEvent("mousedown", {
                     clientX: touch.clientX,
                     clientY: touch.clientY
                  });
                  canvas.dispatchEvent(me);
               }, false);

               canvas.addEventListener("touchend", function(e) {
                  e.preventDefault(); 
                  var me = new MouseEvent("mouseup", {});
                  canvas.dispatchEvent(me);
               }, false);

               function getMousePos(canvasDom, mouseEvent) {
                  var rect = canvasDom.getBoundingClientRect();
                  return {
                     x: mouseEvent.clientX - rect.left,
                     y: mouseEvent.clientY - rect.top
                  }
               }

               function getTouchPos(canvasDom, touchEvent) {
                  var rect = canvasDom.getBoundingClientRect();
                  return {
                     x: touchEvent.touches[0].clientX - rect.left,
                     y: touchEvent.touches[0].clientY - rect.top
                  }
               }

               function renderCanvas() {
                  if (drawing) {
                     ctx.moveTo(lastPos.x, lastPos.y);
                     ctx.lineTo(mousePos.x, mousePos.y);
                     ctx.stroke();
                     lastPos = mousePos;
                  }
               }

               // Prevent scrolling when touching tde canvas
               document.body.addEventListener("touchstart", function(e) {
                  if (e.target == canvas) {
                     e.preventDefault();
                  }
               }, false);
               document.body.addEventListener("touchend", function(e) {
                  if (e.target == canvas) {
                     e.preventDefault();
                  }
               }, false);
               document.body.addEventListener("touchmove", function(e) {
                  if (e.target == canvas) {
                     e.preventDefault();
                  }
               }, false);

               (function drawLoop() {
                  requestAnimFrame(drawLoop);
                  renderCanvas();
               })();

               function clearCanvas() {
                  canvas.widtd = canvas.widtd;
               }

            // Set up tde UI
               var sigText = document.getElementById("sig-dataUrl");
               var submitBtn = document.getElementById("sig-submitBtn");

               submitBtn.addEventListener("click", function(e) {
                  var dataUrl = canvas.toDataURL();
                  sigText.innerHTML = dataUrl;
               }, false);
               
               //--------------------------------------------------- BTN SUPPRIMER
               var clearBtn = document.getElementById("sig-clearBtn");
               clearBtn.addEventListener("click", function(e) {
                  clearCanvas();
                  sigImage.setAttribute("src", "");
               }, false);
            
         </script>
      <?php    
   }
}
