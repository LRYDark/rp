<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to tdis file");
}

class PluginRpCri extends CommonDBTM {

   static $rightname = 'plugin_rp_cri_create';

   static function getTypeName($nb = 0) {
      return _n('Rapport / Prise en charge', 'Rapport / Prise en charge', $nb, 'rp');
   }

   function showForm($ID, $options = []) {
      global $DB, $CFG_GLPI;

      $config = PluginRpConfig::getInstance();
      $job    = new Ticket();
      $plugin = new Plugin();
      $job->getfromDB($ID);

      $params = ['job'         => $ID,
                  'form'       => 'formReport',
                  'root_doc'   => PLUGIN_RP_WEBDIR];

      if($config->fields['use_publictask'] == 1){
         $is_private = "AND is_private = 0";
      }else{
         $is_private = "";
      }         
         //---------------------SQL / VAR ----------------------
         $result = $DB->query("SELECT * FROM glpi_tickets INNER JOIN glpi_entities 
         ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $ID")->fetch_object();
                           
         $resultclient = $DB->query("SELECT * FROM glpi_plugin_rp_dataclient WHERE id_ticket = $ID")->fetch_object();

         //---------------------SQL / VAR ----------------------
            if(!empty($resultclient->id_ticket)){
               $society = $resultclient->society;
               $town = $resultclient->town;
               $address = $resultclient->address;
               $postcode = $resultclient->postcode;
               $phone = $resultclient->phone;
               $email = $resultclient->email;
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
         echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/inc/cripdf.class.php\" method=\"post\" name=\"formReport\">";

         echo Html::hidden('REPORT_ID', ['value' => $ID]);

         // tableau bootstrap -> glpi
         $querytask = "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $ID $is_private";
         $resulttask = $DB->query($querytask);
         $numbertask = $DB->numrows($resulttask);

         echo '<div class="table-responsive">';
         echo "<table class='table'>"; 
      
         if($numbertask > 0 && $_POST["modal"] == "form_rapport_hotline" || $_POST["modal"] == "form_client"){
            $description = $result->content;
            echo "<tr>";
               echo "<td style='width: 28%;' class='table-active'>";
                  echo 'Description du Probl??me :';
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
         }

         // ---- formulaire client-------------------------------   
         if($_POST["modal"] == "form_client"){
         echo "</table><br>";
         // --- infos client ----------------------------------------------
         echo "<table class='table'>";
               $items = $DB->query("SELECT requesttypes_id FROM `glpi_tickets` WHERE id = $ID")->fetch_object();     

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
                  echo "<td style='width: 28%;' class='table-active'>";
                     echo 'Informations PC :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="serialnumber">Num??ro de serie</label><br>';
                     echo '<input type="text" name="serialnumber" required="" placeholder="Num??ro de serie" value="'.$serialnumber.'">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="model">Marque / Model</label><br>';
                     echo '<input type="text" name="model" placeholder="Marque / Model">';
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-active'>";
                     echo 'Personne en charge du mat??riel :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="NameRespMat">Nom / Pr??nom</label><br>';
                     echo '<input type="text" name="NameRespMat" required="" placeholder="Nom du Responsable mat??riel">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="CoordRespMat">T??l??phone / Mail</label><br>';
                     echo '<input type="text" name="CoordRespMat" required="" placeholder="Mail/Tel du Responsable mat??riel">';
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
                  echo "<td class='table-active'>";
                     echo '';
                  echo "</td>";

                  echo "<td>";
                        echo "<label for='equal'>L'utilisateur du materiel est diff??rent de <br> la personne l'ayant pris en charge ?</label>";
                     echo '<input type="checkbox" name="equal" value="equal" id="foo">';
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 4
               echo "<tr id='bar'>";
                  echo "<td class='table-active'>";
                     echo 'Utilisateur du mat??riel :';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="NameRespMat">Nom / Pr??nom</label><br>';
                     echo '<input type="text" name="NameUtilpMat" placeholder="Nom de l utilisateur">';
                  echo "</td>";

                  echo "<td>";
                        echo '<label for="CoordRespMat">T??l??phone / Mail</label><br>';
                     echo '<input type="text" name="CoordUtilpMat" placeholder="Mail/Tel de l utilisateur">';
                  echo "</td>";
               echo "</tr>";

               echo "<td>";
                  echo '';
               echo "</td>";
            
               //---------------------------------------------------------------
               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-active'>";
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
                  echo "<td class='table-active'>";
                     echo 'Sauvegarde des donn??es ?';
                  echo "</td>";

                  echo "<td>";
                     echo '<input type="radio" name="DataSave" value="Oui" checked> OUI &emsp;&emsp;&emsp;';
                     echo '<input type="radio" name="DataSave" value="Non"> NON';
                  echo "</td>";
               echo "</tr>";

               echo "<tr>";
                  echo "<td class='table-active'>";
                     echo 'Formatage autoris?? ?';
                  echo "</td>";

                  echo "<td>";
                     echo '<input type="radio" name="DataFormatting" value="Oui"> OUI &emsp;&emsp;&emsp;';
                     echo '<input type="radio" name="DataFormatting" value="Non" checked> NON';
                  echo "</td>";
               echo "</tr>"; 

               // TABLEAU 4
               echo "<tr>";
                  echo "<td class='table-active'>";
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
               echo "<td style='width: 28%;' class='table-active'>";
                  echo 'Nom de la Soci??t?? / Client* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="text" required="" id="society" name="society" value="'.$society.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-active'>";
                  echo 'Adresse* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="text" id="address" required="" name="address" value="'.$address.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-active'>";
                  echo 'Ville* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="text" id="town" required="" name="town" value="'.$town.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-active'>";
                  echo 'Code postal* :';
               echo "</td>";

               echo "<td>";
                  echo '<input type="tel" id="postcode" required="" name="postcode" value="'.$postcode.'">';
               echo "</td>";
            echo "</tr>";

            echo "<tr>";
               echo "<td class='table-active'>";
                  echo 'N?? de t??l??phone :';
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
               $resulttask = $DB->query($querytask);
               $numbertask = $DB->numrows($resulttask);

               if($numbertask > 0){
                  $i=1;
                  while ($data = $DB->fetchArray($resulttask)) {
                     $checked = "";

                     echo "<tr>";
                        echo "<td style='width: 25%;' class='table-active'>";
                        echo 'Tache N??'.$i++.'';
                        if ($data['is_private'] == 1) echo ' - <span style="color:red"> Priv??e <i class="ti ti-lock" aria-label="Priv??"></i></span>';
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
                  echo "<b>" . __("Vous ne pouvez pas g??n??rer de rapport sans t??che(s).") . "</b></div>";
                  exit; 
               }

               $querysuivi = "SELECT glpi_itilfollowups.id, content, date, name, is_private FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $ID $is_private";
               $resultsuivi = $DB->query($querysuivi);
               $numbersuivi = $DB->numrows($resultsuivi);

               if($numbersuivi > 0){
                  $i=1;
                  while ($dataSuivi = $DB->fetchArray($resultsuivi)) {
                     $descriptionSuivi = $dataSuivi["content"];
                     $dateSuivi = $dataSuivi["date"]; 
                     $checked = "";

                     echo "<tr>";
                        echo "<td style='widtd: 25%;' class='table-active'>";
                        echo 'Suivi N??'.$i++.'';
                        if ($dataSuivi['is_private'] == 1) echo ' - <span style="color:red"> Priv?? <i class="ti ti-lock" aria-label="Priv??"></i></span>';
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
                  echo "<td class='table-active'>";
                     echo "Affichage du temps d'intervention";
                  echo "</td>";

                  echo "<td>";
                        echo '<input type="checkbox" name="rapporttime" value="yes" checked>';
                  echo "</td>";
               echo "</tr>";
            }

            //----------------------------------------------------------
            $signature = "false";
            if ($_POST["modal"]  == "form_rapport_hotline" && $config->fields['sign_rp_hotl'] == 1)$signature = "true";
            if ($_POST["modal"]  == "form_rapport" && $config->fields['sign_rp_tech'] == 1)$signature = "true";
            if ($_POST["modal"]  == "form_client" && $config->fields['sign_rp_charge'] == 1)$signature = "true";

            if($signature == 'true'){
               echo "<tr>";
                  echo "<td class='table-active'>";
                     echo ' ';
                  echo "</td>";

                  echo "<td>";
                     echo '<b> ______________ SIGNATURE CLIENT ______________<b>';
                  echo "</td>";
               echo "</tr>";
               // TABLEAU 1
               echo "<tr>";
                  echo "<td class='table-active'>";
                     echo 'Nom / Prenom du client';
                  echo "</td>";

                  echo "<td>";
                     echo "<input type='text' id='name' name='name' placeholder='Nom / Prenom du client' required=''>";
                  echo "</td>";
               echo "</tr>";

               // TABLEAU 3
               echo "<tr>";
                  echo "<td class='table-active'>";
                     echo 'Signature client';
                  echo "</td>";

                  echo "<td>";
                     echo "<canvas id='sig-canvas' class='sig' widtd='320' height='80'></canvas>";
                  echo "</td>";
               echo "</tr>";
            }
            // Mail
            echo "<tr>";
               echo "<td class='table-active'>";
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
                  echo "<input type='submit' name='add_cri' id='sig-submitBtn' value='G??n??ration du PDF' class='submit'>";
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
