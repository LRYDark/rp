<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
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

      $result = $DB->query($query);
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
      $uniq = 'cri'.mt_rand(10000,99999);

      // Inclure les fichiers CSS et JS externes
      echo '<link rel="stylesheet" href="' . PLUGIN_RP_WEBDIR . '/css/signature.css">';
      echo '<script src="' . PLUGIN_RP_WEBDIR . '/scripts/signature.js" defer></script>';

      $config = PluginRpConfig::getInstance();
      $job    = new Ticket();
      $plugin = new Plugin();
      $job->getfromDB($ID);
      $img_sum_task = 0;
      $img_sum_suivi = 0;
      $sumtask = 0;

      $params = ['job'         => $ID,
                 'form'        => 'formReport',
                 'root_doc'    => PLUGIN_RP_WEBDIR];

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
      
      echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/front/cripdf.form.php\" method=\"post\" name=\"formReport\">";
      echo Html::hidden('REPORT_ID', ['value' => $ID]);

      // Requête pour les tâches
      $querytask = "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $ID $is_private";
      $resulttask = $DB->query($querytask);
      $numbertask = $DB->numrows($resulttask);

      echo '<div class="form-container">';
      
      // === CARTE TYPE DE RAPPORT ===
      if($_POST["modal"] != "form_client" && $numbertask > 0 || $_POST["modal"] == "form_client"){
         if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0){
            echo '<div class="form-card">';
               echo '<div class="form-label">Type de rapport</div>';
               echo '<div class="form-content">';
                  
                  $entity_parrent1_id = $config->fields['entity_parrent1'];
                  $entity_parrent1 = $DB->query("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent1_id")->fetch_object();
                  $entity_parrent2_id = $config->fields['entity_parrent2'];
                  $entity_parrent2 = $DB->query("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent2_id")->fetch_object();
                  
                  $group = $this->getEntityGroupFromEntityId($result->id, $entity_parrent1->name, $entity_parrent2->name);
                  $checked1 = ($group == 'entity_parrent1' || ($group != 'entity_parrent2')) ? 'checked' : '';
                  $checked2 = ($group == 'entity_parrent2') ? 'checked' : '';
                  
                  echo '<div class="radio-group">';
                     echo '<div class="radio-item">';
                        echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent1\" $checked1 id=\"entity1\">";
                        echo "<label for=\"entity1\">" . $entity_parrent1->name . "</label>";
                     echo '</div>';
                     echo '<div class="radio-item">';
                        echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent2\" $checked2 id=\"entity2\">";
                        echo "<label for=\"entity2\">" . $entity_parrent2->name . "</label>";
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
         }
         
         // Gestion des cas avec une seule entité
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
      
      // === CARTE DESCRIPTION DU PROBLÈME ===
      if($_POST["modal"] != "form_client" && $numbertask > 0){
         $description = $result->content;
         echo '<div class="form-card card-description">';
            echo '<div class="form-label">Description du Problème</div>';
            echo '<div class="form-content">';
               $checked = ($_POST["modal"] == "form_rapport_hotline" || $_POST["modal"] == "form_client") ? "checked" : "";
               echo '<div class="checkbox-group">';
                  echo '<input type="checkbox" value="check" name="CHECK_DESCRIPTION_TICKET" '.$checked.' id="desc_check">';
                  echo '<label for="desc_check">Visible dans le rapport</label>';
               echo '</div>';
               Html::textarea([
                  'name'              => 'DESCRIPTION_TICKET',
                  'value'             => Glpi\RichText\RichText::getSafeHtml($description, true),
                  'enable_richtext'   => true,
                  'enable_fileupload' => false,
                  'enable_images'     => false,
               ]);
            echo '</div>';
         echo '</div>';
      }
      
      // === FORMULAIRE CLIENT ===
      if($_POST["modal"] == "form_client"){
         echo "<input type='hidden' name='Form' value='FormClient' />";
         
         // Informations PC
         $items = $DB->query("SELECT requesttypes_id FROM `glpi_tickets` WHERE id = $ID")->fetch_object();
         if($items->requesttypes_id == 1){
            echo '<div class="form-card">';
               echo '<div class="form-label">Informations PC</div>';
               echo '<div class="form-content">';
                  echo '<div class="form-row">';
                     echo '<div class="form-col">';
                        echo '<label for="serialnumber">Numéro de série</label>';
                        echo '<input type="text" name="serialnumber" required placeholder="Numéro de série" value="'.$serialnumber.'">';
                     echo '</div>';
                     echo '<div class="form-col">';
                        echo '<label for="model">Marque / Modèle</label>';
                        echo '<input type="text" name="model" placeholder="Marque / Modèle">';
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
            
            // Personne en charge du matériel
            echo '<div class="form-card">';
               echo '<div class="form-label">Personne en charge du matériel</div>';
               echo '<div class="form-content">';
                  echo '<div class="form-row">';
                     echo '<div class="form-col">';
                        echo '<label for="NameRespMat">Nom / Prénom</label>';
                        echo '<input type="text" name="NameRespMat" required placeholder="Nom du Responsable matériel">';
                     echo '</div>';
                     echo '<div class="form-col">';
                        echo '<label for="CoordRespMat">Téléphone / Mail</label>';
                        echo '<input type="text" name="CoordRespMat" required placeholder="Mail/Tel du Responsable matériel">';
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
            
            // Utilisateur différent
            echo '<div class="form-card">';
               echo '<div class="form-label">Utilisateur différent</div>';
               echo '<div class="form-content">';
                  echo '<div class="checkbox-group">';
                     echo '<input type="checkbox" name="equal" value="equal" id="foo">';
                     echo '<label for="foo">L\'utilisateur du matériel est différent de la personne l\'ayant pris en charge</label>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
            
            // Section utilisateur (cachée par défaut)
            echo '<div id="bar" class="form-card" style="display:none;">';
               echo '<div class="form-label">Utilisateur du matériel</div>';
               echo '<div class="form-content">';
                  echo '<div class="form-row">';
                     echo '<div class="form-col">';
                        echo '<label for="NameUtilpMat">Nom / Prénom</label>';
                        echo '<input type="text" name="NameUtilpMat" placeholder="Nom de l\'utilisateur">';
                     echo '</div>';
                     echo '<div class="form-col">';
                        echo '<label for="CoordUtilpMat">Téléphone / Mail</label>';
                        echo '<input type="text" name="CoordUtilpMat" placeholder="Mail/Tel de l\'utilisateur">';
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
            
            // Accessoires
            echo '<div class="form-card">';
               echo '<div class="form-label">Accessoires</div>';
               echo '<div class="form-content">';
                  echo '<div class="accessory-grid">';
                     echo '<div class="checkbox-group">';
                        echo '<input type="checkbox" name="mouse" value="Souris / " id="mouse">';
                        echo '<label for="mouse">Souris</label>';
                     echo '</div>';
                     echo '<div class="checkbox-group">';
                        echo '<input type="checkbox" name="keyboard" value="Clavier / " id="keyboard">';
                        echo '<label for="keyboard">Clavier</label>';
                     echo '</div>';
                     echo '<div class="checkbox-group">';
                        echo '<input type="checkbox" name="bag" value="Sachoche / " id="bag">';
                        echo '<label for="bag">Sacoche</label>';
                     echo '</div>';
                     echo '<div class="checkbox-group">';
                        echo '<input type="checkbox" name="feed" value="Alimentation / " id="feed">';
                        echo '<label for="feed">Alimentation</label>';
                     echo '</div>';
                     echo '<div class="checkbox-group">';
                        echo '<input type="checkbox" name="dockstation" value="Dock Station / " id="dock">';
                        echo '<label for="dock">Dock Station</label>';
                     echo '</div>';
                  echo '</div>';
                  echo '<div class="form-row" style="margin-top: 15px;">';
                     echo '<div class="form-col">';
                        echo '<label for="other">Autres :</label>';
                        echo '<textarea name="other" maxlength="100" placeholder="Autre(s) accessoire(s)" rows="3"></textarea>';
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
            
            // Sauvegarde et formatage
            echo '<div class="form-card">';
               echo '<div class="form-label">Sauvegarde et formatage</div>';
               echo '<div class="form-content">';
                  echo '<div class="form-row">';
                     echo '<div class="form-col">';
                        echo '<div class="form-label-small">Sauvegarde des données ?</div>';
                        echo '<div class="radio-group">';
                           echo '<div class="radio-item">';
                              echo '<input type="radio" name="DataSave" value="Oui" checked id="save_yes">';
                              echo '<label for="save_yes">OUI</label>';
                           echo '</div>';
                           echo '<div class="radio-item">';
                              echo '<input type="radio" name="DataSave" value="Non" id="save_no">';
                              echo '<label for="save_no">NON</label>';
                           echo '</div>';
                        echo '</div>';
                     echo '</div>';
                     echo '<div class="form-col">';
                        echo '<div class="form-label-small">Formatage autorisé ?</div>';
                        echo '<div class="radio-group">';
                           echo '<div class="radio-item">';
                              echo '<input type="radio" name="DataFormatting" value="Oui" id="format_yes">';
                              echo '<label for="format_yes">OUI</label>';
                           echo '</div>';
                           echo '<div class="radio-item">';
                              echo '<input type="radio" name="DataFormatting" value="Non" checked id="format_no">';
                              echo '<label for="format_no">NON</label>';
                           echo '</div>';
                        echo '</div>';
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
            
            // Informations de session
            echo '<div class="form-card">';
               echo '<div class="form-label">Informations de session</div>';
               echo '<div class="form-content">';
                  echo '<div class="form-row">';
                     echo '<div class="form-col">';
                        echo '<label for="idsession">Nom d\'ouverture de session</label>';
                        echo '<input type="text" name="idsession" autocomplete="off" placeholder="Nom d\'ouverture de session">';
                     echo '</div>';
                     echo '<div class="form-col">';
                        echo '<label for="id_password">Mot de passe</label>';
                        echo '<div class="password-input">';
                           echo '<input type="password" name="userpassword" autocomplete="new-password" required id="id_password" placeholder="Mot de passe">';
                           echo '<i class="far fa-eye" id="togglePassword"></i>';
                        echo '</div>';
                     echo '</div>';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
         }
         
         // Informations client
         echo '<div class="form-card">';
            echo '<div class="form-label">Informations client</div>';
            echo '<div class="form-content">';
               echo '<div class="form-row">';
                  echo '<div class="form-col">';
                     echo '<label for="society">Nom de la Société / Client *</label>';
                     echo '<input type="text" required id="society" name="society" value="'.$society.'">';
                  echo '</div>';
                  echo '<div class="form-col">';
                     echo '<label for="address">Adresse *</label>';
                     echo '<input type="text" id="address" required name="address" value="'.$address.'">';
                  echo '</div>';
               echo '</div>';
               echo '<div class="form-row">';
                  echo '<div class="form-col">';
                     echo '<label for="town">Ville *</label>';
                     echo '<input type="text" id="town" required name="town" value="'.$town.'">';
                  echo '</div>';
                  echo '<div class="form-col">';
                     echo '<label for="postcode">Code postal *</label>';
                     echo '<input type="tel" id="postcode" required name="postcode" value="'.$postcode.'">';
                  echo '</div>';
               echo '</div>';
               echo '<div class="form-row">';
                  echo '<div class="form-col">';
                     echo '<label for="phone">N° de téléphone</label>';
                     echo '<input type="tel" id="phone" name="phone" value="'.$phone.'">';
                  echo '</div>';
               echo '</div>';
            echo '</div>';
         echo '</div>';
         
      }elseif($_POST["modal"] == "form_rapport" || $_POST["modal"] == "form_rapport_hotline"){
         echo "<input type='hidden' name='Form' value='FormRapport' />";
         
         // === TÂCHES ===
         if($numbertask > 0){
            $i=1;
            while ($data = $DB->fetchArray($resulttask)) {
               $checked = "";
               
               echo '<div class="form-card card-task">';
                  echo '<div class="form-label">';
                     echo 'Tâche N°'.$i++;
                     if ($data['is_private'] == 1) echo ' - <span style="color:red">Privée <i class="ti ti-lock"></i></span>';
                     echo '<br><small class="task-meta">'.$data["date"].' - '.$data['name'].'</small>';
                  echo '</div>';
                  
                  echo '<div class="form-content">';
                     if($config->fields['choice'] == 1){
                        if($config->fields['check_public_task'] == 1 && $data['is_private'] == 0){
                           $checked = "checked";
                        }
                        if($config->fields['check_private_task'] == 1 && $data['is_private'] == 1){
                           $checked = "checked";
                        }
                        echo '<div class="checkbox-group">';
                           echo '<input type="checkbox" value="check" name="tasks_pdf_'.$data['id'].'" '.$checked.' id="task_'.$data['id'].'">';
                           echo '<label for="task_'.$data['id'].'">Visible dans le rapport</label>';
                        echo '</div>';
                     }else{
                        echo '<input type="hidden" value="check" name="tasks_pdf_'.$data['id'].'" checked/>';
                     }
                     
                     echo '<input type="hidden" value="'.$data["date"].'" name="tasks_date_'.$data['id'].'" />';
                     echo '<input type="hidden" value="'.$data["actiontime"].'" name="tasks_time_'.$data['id'].'" />';
                     echo '<input type="hidden" value="'.$data["name"].'" name="tasks_name_'.$data['id'].'" />';
                     
                     Html::textarea([
                        'name'              => 'TASKS_DESCRIPTION'.$data['id'],
                        'value'             => Glpi\RichText\RichText::getSafeHtml($data["content"], true),
                        'enable_richtext'   => true,
                        'enable_fileupload' => false,
                        'enable_images'     => false,
                     ]);
                  echo '</div>';
               echo '</div>';
               
               // Gestion des images et calcul du temps
               $IdImg = $data['id'];
               $ImgIdDoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
               if (isset($ImgIdDoc->documents_id)){
                  $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
               }
               if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
                  $img_sum_task ++;
               }
               $sumtask += $data["actiontime"];
            }
         }
         
         // === SUIVIS ===
         $querysuivi = "SELECT glpi_itilfollowups.id, content, date, name, is_private FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $ID $is_private";
         $resultsuivi = $DB->query($querysuivi);
         $numbersuivi = $DB->numrows($resultsuivi);
         
         if($numbersuivi > 0){
            $i=1;
            while ($dataSuivi = $DB->fetchArray($resultsuivi)) {
               $checked = "";
               
               echo '<div class="form-card card-followup">';
                  echo '<div class="form-label">';
                     echo 'Suivi N°'.$i++;
                     if ($dataSuivi['is_private'] == 1) echo ' - <span style="color:red">Privé <i class="ti ti-lock"></i></span>';
                     echo '<br><small class="task-meta">'.$dataSuivi['date'].' - '.$dataSuivi['name'].'</small>';
                  echo '</div>';
                  
                  echo '<div class="form-content">';
                     if($config->fields['choice'] == 1){
                        if($config->fields['check_public_suivi'] == 1 && $dataSuivi['is_private'] == 0){
                           $checked = "checked";
                        }
                        if($config->fields['check_private_suivi'] == 1 && $dataSuivi['is_private'] == 1){
                           $checked = "checked";
                        }
                        echo '<div class="checkbox-group">';
                           echo '<input type="checkbox" value="check" name="suivis_pdf_'.$dataSuivi['id'].'" '.$checked.' id="suivi_'.$dataSuivi['id'].'">';
                           echo '<label for="suivi_'.$dataSuivi['id'].'">Visible dans le rapport</label>';
                        echo '</div>';
                     }else{
                        echo '<input type="hidden" value="check" name="suivis_pdf_'.$dataSuivi['id'].'" checked/>';
                     }
                     
                     echo '<input type="hidden" value="'.$dataSuivi["date"].'" name="suivis_date_'.$dataSuivi['id'].'" />';
                     echo '<input type="hidden" value="'.$dataSuivi["name"].'" name="suivis_name_'.$dataSuivi['id'].'" />';
                     
                     Html::textarea([
                        'name'              => 'SUIVIS_DESCRIPTION'.$dataSuivi['id'],
                        'value'             => Glpi\RichText\RichText::getSafeHtml($dataSuivi["content"], true),
                        'enable_richtext'   => true,
                        'enable_fileupload' => false,
                        'enable_images'     => false,
                     ]);
                  echo '</div>';
               echo '</div>';
               
               // Gestion des images
               $IdImg = $dataSuivi['id'];
               $ImgIdDoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
               if (isset($ImgIdDoc->documents_id)){
                  $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
               }
               if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
                  $img_sum_suivi ++;
               }
            }
         }
         
         // === OPTIONS D'AFFICHAGE ===
         echo '<div class="form-card">';
            echo '<div class="form-label">Options d\'affichage</div>';
            echo '<div class="form-content">';
               echo '<div class="checkbox-group">';
                  echo '<input type="checkbox" name="rapporttime" value="yes" checked id="show_time">';
                  echo '<label for="show_time">Afficher le temps d\'intervention ('.mb_convert_encoding(floor($sumtask / 3600).str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8').')</label>';
               echo '</div>';
               
               // Images des tâches si présentes
               if($img_sum_task != 0){
                  $checkedimgtask = ($config->fields['ImgTasks'] == 1) ? "checked" : "";
                  echo '<div class="checkbox-group">';
                     echo '<input type="checkbox" name="rapportimgtask" value="yes" '.$checkedimgtask.' id="show_img_task">';
                     echo '<label for="show_img_task">Afficher les images des tâches ('.$img_sum_task.' image(s))</label>';
                  echo '</div>';
               }
               
               // Images des suivis si présentes
               if($img_sum_suivi != 0){
                  $checkedimgsuivis = ($config->fields['ImgSuivis'] == 1) ? "checked" : "";
                  echo '<div class="checkbox-group">';
                     echo '<input type="checkbox" name="rapportimgsuivi" value="yes" '.$checkedimgsuivis.' id="show_img_suivi">';
                     echo '<label for="show_img_suivi">Afficher les images des suivis ('.$img_sum_suivi.' image(s))</label>';
                  echo '</div>';
               }
            echo '</div>';
         echo '</div>';
      }
      
      // === SIGNATURE CLIENT ===
      $signature = "false";
      if ($_POST["modal"] == "form_rapport_hotline" && $config->fields['sign_rp_hotl'] == 1) $signature = "true";
      if ($_POST["modal"] == "form_rapport" && $config->fields['sign_rp_tech'] == 1) $signature = "true";
      if ($_POST["modal"] == "form_client" && $config->fields['sign_rp_charge'] == 1) $signature = "true";
      
      if($signature == 'true'){
         // === CARTE SIGNATURE ===
         echo '<div class="form-card signature-card">';
            echo '<div class="form-label">SIGNATURE CLIENT</div>';
            
            // SOUS-CARTE 1 : Nom du client
            echo '<div class="signature-sub-card">';
               echo '<div class="signature-sub-title">Nom / Prénom du client</div>';
               echo '<input type="text" id="name" name="name" placeholder="Nom / Prénom du client" required>';
            echo '</div>';
            
            // SOUS-CARTE 2 : Canvas signature
            echo '<div class="signature-sub-card">';
               echo '<div class="signature-sub-title">Signature client</div>';
               echo "<div id='".$uniq."' class='cri-signature-root'>";
                  echo "  <div class='signature-container'>";
                  echo "    <button type='button' class='zoom-btn'>Agrandir <i class='fa-solid fa-up-right-and-down-left-from-center'></i></button>";
                  echo "    <canvas id='sig-canvas-".$uniq."' width='400' height='120' class='sig-base'></canvas>";
                  echo "  </div>";
                  echo "  <button type='button' id='sig-clearBtn-".$uniq."' class='resetButton'>Supprimer la signature</button>";

                  // Modal interne pour le zoom
                  echo "  <div class='signature-modal' aria-hidden='true'>";
                  echo "    <div class='modal-wrapper'>";
                  echo "      <div class='cri-modal-content'>";
                  echo "        <div class='rotate-gate'>";
                  echo "          <button type='button' class='rotate-close-btn' aria-label='Fermer'>&times;</button>";
                  echo "          <div>";
                  echo "            <div style='font-size:18px;font-weight:700;margin-bottom:8px'>";
                  echo "              Tournez votre téléphone en mode paysage";
                  echo "            </div>";
                  echo "            <div style='opacity:0.9'>La zone de signature va s'agrandir automatiquement.</div>";
                  echo "          </div>";
                  echo "        </div>";
                  echo "        <div class='cri-canvas-wrapper'>";
                  echo "          <canvas id='modal-canvas-".$uniq."' class='modal-canvas'></canvas>";
                  echo "        </div>";
                  echo "        <div class='cri-controls-panel'>";
                  echo "          <button type='button' class='btn-validate'>Valider</button>";
                  echo "          <button type='button' class='btn-clear'>Effacer</button>";
                  echo "          <button type='button' class='btn-cancel'>Annuler</button>";
                  echo "        </div>";
                  echo "      </div>";
                  echo "    </div>";
                  echo "  </div>";
               echo "</div>";
            echo '</div>';
            
         echo '</div>';
      }
      
      // === CARTE EMAIL ===
      echo '<div class="form-card">';
         echo '<div class="form-label">Mail client</div>';
         echo '<div class="form-content">';
            if ($config->fields['email'] == 1){
               echo '<div class="checkbox-group">';
                  echo '<input type="checkbox" name="mailtoclient" value="1" id="send_email">';
                  echo '<label for="send_email">Envoyer le PDF par email</label>';
               echo '</div>';
            }
            echo '<input type="email" id="mail" name="email" value="'.$email.'" placeholder="Email du client">';
         echo '</div>';
      echo '</div>';
      
      // === CARTE ACTIONS ===
      echo '<div class="form-card actions-card">';
         echo '<div class="form-content">';
            echo '<input type="submit" name="add_cri" id="sig-submitBtn" value="Génération du PDF" class="submit-btn">';
         echo '</div>';
      echo '</div>';
      
      echo '</div>'; // Fin form-container
      
      // Champ caché pour la signature
      if($_POST["modal"] != "form_rapport_hotline"){
         echo '<textarea readonly name="url" id="sig-dataUrl" class="form-control" rows="0" cols="150" style="display: none;"></textarea>';
      }
      if($_POST["modal"] == "form_rapport_hotline"){
         echo "<input type='hidden' name='Form' value='FormRapportHotline'/>";
      }

      Html::closeForm();
      
      ?>
      <script>
      // Version simple qui fonctionne toujours
      setTimeout(function() {         
         // 1. Toggle password
         const togglePassword = document.querySelector('#togglePassword');
         const password = document.querySelector('#id_password');
         
         if (togglePassword && password) {
            togglePassword.addEventListener('click', function (e) {
               const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
               password.setAttribute('type', type);
               this.classList.toggle('fa-eye-slash');
            });
         }
         
         // 2. Script pour utilisateur différent  
         const fooCheckbox = document.getElementById('foo');
         const barElement = document.getElementById('bar');
         
         if (fooCheckbox && barElement) {
            fooCheckbox.addEventListener('change', function() {
               if (this.checked) {
                  barElement.style.display = 'block';
               } else {
                  barElement.style.display = 'none';
               }
            });
         }
         
         // 3. Initialiser la signature
         function initSignature() {
            if (typeof initializeSignature === 'function') {
               initializeSignature('<?php echo $uniq; ?>');
            } else {
               setTimeout(initSignature, 100);
            }
         }
         initSignature();
         
      }, 100); // Délai de 100ms pour s'assurer que tout est chargé
      </script>
      <?php
   }
}
?>