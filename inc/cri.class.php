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
      global $DB, $CFG_GLPI, $PLUGIN_HOOKS;
      $uniq = 'cri'.mt_rand(10000,99999);

      // Inclure les fichiers CSS et JS externes
      echo '<link rel="stylesheet" href="' . PLUGIN_RP_WEBDIR . '/public/css/signature_rp.css">';
      echo '<script src="' . PLUGIN_RP_WEBDIR . '/public/js/scripts_rp.js?v=' . time() . '" defer></script>';

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
      $result = $DB->doQuery("SELECT * FROM glpi_tickets INNER JOIN glpi_entities 
      ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $ID")->fetch_object();

      $emailentity = $DB->doQuery("SELECT GROUP_CONCAT(email SEPARATOR ',') AS emails FROM ( SELECT DISTINCT u.email AS email FROM glpi_useremails u JOIN glpi_users us ON us.id = u.users_id JOIN glpi_tickets t ON t.id = $ID WHERE us.entities_id = t.entities_id AND u.email IS NOT NULL AND u.email <> '' AND us.is_deleted = 0 UNION SELECT DISTINCT e.email FROM glpi_entities e JOIN glpi_tickets t ON t.entities_id = e.id WHERE t.id = $ID AND e.email IS NOT NULL AND e.email <> '' ) AS mails;")->fetch_object();   
                        
      $resultclient = $DB->doQuery("SELECT * FROM glpi_plugin_rp_dataclient WHERE id_ticket = $ID")->fetch_object();

      //---------------------SQL / VAR ----------------------
      if(!empty($resultclient->id_ticket)){
         $society = $resultclient->society;
         $town = $resultclient->town;
         $address = $resultclient->address;
         $postcode = $resultclient->postcode;
         $phone = $resultclient->phone;
         $emailbdd = $resultclient->email;
         if($resultclient->email == ''){
            $emailbdd = $result->email;
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
         $emailbdd = $result->email;
         $serialnumber = "";
      }

      if (!empty($emailentity->emails)) {
         $email = $emailentity->emails;
      } else {
         $email = '';
      }

      // Si $emailbdd n'est pas vide
      if (!empty($emailbdd)) {
         // Convertir la liste existante en tableau
         $emailsArray = array_filter(array_map('trim', explode(',', $email)));

         // Ajouter le nouvel email s'il n'est pas déjà présent
         if (!in_array($emailbdd, $emailsArray)) {
            $emailsArray[] = $emailbdd;
         }

         // Reformater en chaîne séparée par des virgules
         $email = implode(',', $emailsArray);
      }

      
      echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/front/cripdf.form.php\" method=\"post\" name=\"formReport\">";
      echo Html::hidden('REPORT_ID', ['value' => $ID]);

      // Requête pour les tâches
      $querytask = "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $ID $is_private";
      $resulttask = $DB->doQuery($querytask);
      $numbertask = $DB->numrows($resulttask);

      echo '<div class="form-container">';
      
      // === CARTE TYPE DE RAPPORT ===
      if($_POST["modal"] != "form_client" && $numbertask > 0 || $_POST["modal"] == "form_client"){
         if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0){
            echo '<div class="form-card">';
               echo '<div class="form-label">Type de rapport</div>';
               echo '<div class="form-content">';
                  
                  $entity_parrent1_id = $config->fields['entity_parrent1'];
                  $entity_parrent1 = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent1_id")->fetch_object();
                  $entity_parrent2_id = $config->fields['entity_parrent2'];
                  $entity_parrent2 = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent2_id")->fetch_object();
                  
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

         // === CARTE DESCRIPTION DU PROBLÈME ===
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
         $items = $DB->doQuery("SELECT requesttypes_id FROM `glpi_tickets` WHERE id = $ID")->fetch_object();
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
               $ImgIdDoc = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
               if (isset($ImgIdDoc->documents_id)){
                  $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
               }
               if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
                  $img_sum_task ++;
               }
               $sumtask += $data["actiontime"];
            }
         
         
            // === SUIVIS ===
            $querysuivi = "SELECT glpi_itilfollowups.id, content, date, name, is_private FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $ID $is_private";
            $resultsuivi = $DB->doQuery($querysuivi);
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
                  $ImgIdDoc = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
                  if (isset($ImgIdDoc->documents_id)){
                     $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
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
         }else{
            header("Refresh:0");
            echo "<div class='alert alert-important alert-warning d-flex'>";
            echo "<b>" . __("Vous ne pouvez pas générer de rapport sans tâche(s).") . "</b></div>";
            exit;
         }
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
                  echo "    <canvas id='sig-canvas-".$uniq."' height='300' class='sig-base'></canvas>";
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

         if (Plugin::isPluginActive("gestion")) {
            // Fonction pour vérifier les utilisateurs autorisés
            function isCurrentUserAuthorized($authorized_users_string) {
               $current_user_id = $_SESSION['glpiID'];
               $authorized_users = json_decode($authorized_users_string, true);
               
               return is_array($authorized_users) && in_array($current_user_id, $authorized_users);
            }

            // Inclure les fichiers JS pour signature déportée
            //echo '<script>const GLPI_PLUG_RP = "'.PLUGIN_GESTION_WEBDIR.'";</script>';
            echo '<script>
               window.GLPI_PLUG_RP = "' . PLUGIN_GESTION_WEBDIR . '";
            </script>';
            echo '<script src="' . PLUGIN_GESTION_WEBDIR . '/public/js/scripts_gestion.js?v=' . time() . '" defer></script>';

            // Vérifier si la signature déportée est activée
            try {
               $config_gestion = PluginGestionConfig::getInstance();
               $remote_signature_enabled = $config_gestion->fields['RemoteSignatureOn'] == 1;
               $user_authorized = isCurrentUserAuthorized($config_gestion->fields['RemoteSignatureUsers']);
            } catch (Exception $e) {
               $remote_signature_enabled = false;
               $user_authorized = false;
            }

            if ($remote_signature_enabled && $user_authorized) {                 
               // === CARTE SIGNATURE DÉPORTÉE (tablette) ===
               
               $can_remote = false;
               $devices = [];
               // Lire la configuration directement depuis la table du plugin gestion
               try {
                     $cfgrow = [];
                     $rescfg = $DB->doQuery("SELECT * FROM glpi_plugin_gestion_configs LIMIT 1");
                     if ($rescfg && $DB->numrows($rescfg) > 0) {
                        $cfgrow = $DB->fetchassoc($rescfg);
                     }
                     $enabled = isset($cfgrow['enable_remote_signature']) ? (int)$cfgrow['enable_remote_signature'] : 1;
                     $allowed_users = [];
                     if (isset($cfgrow['remote_allowed_users']) && $cfgrow['remote_allowed_users'] !== '') {
                        $raw = $cfgrow['remote_allowed_users'];
                        if (is_string($raw) && strlen($raw) > 0) {
                           if ($raw[0] === '[') {
                              $decoded = json_decode($raw, true);
                              if (is_array($decoded)) {
                                 foreach ($decoded as $u) { $allowed_users[] = (int)$u; }
                              }
                           } else {
                              foreach (preg_split('/[\s,;]+/', $raw) as $u) { if ($u !== '') $allowed_users[] = (int)$u; }
                           }
                        }
                     }
                     $uid = (int)Session::getLoginUserID();
                     $can_remote = (bool)$enabled && (empty($allowed_users) || in_array($uid, $allowed_users, true));
                     if ($can_remote) {
                        $resdev = $DB->doQuery("SELECT id, device_id, serial, device_token, is_active FROM glpi_plugin_gestion_signaturedevices WHERE is_active = 1 ORDER BY device_id ASC");
                        if ($resdev) {
                           while ($r = $DB->fetchassoc($resdev)) { $devices[] = $r; }
                        }
                     }
               } catch (Throwable $e) {
                     $can_remote = false;
                     $devices = [];
               }
               
               if (!empty($devices)) {
                  echo '<div class="form-card">';
                  echo '  <div class="form-label">Signature déportée (tablette)</div>';
                  echo '  <div class="form-content">';
                  echo '    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
               
                  // Build device list with tokens directly from DB
                  $rows = [];
                  $res = $DB->doQuery("SELECT device_id, serial, device_token FROM glpi_plugin_gestion_signaturedevices WHERE is_active = 1");
                  if ($res) {
                     while ($r = $DB->fetchassoc($res)) { $rows[] = $r; }
                  }

                  echo '      <select id="remote-device" style="padding:6px">';
                  foreach ($rows as $d) {
                     $did = Html::entities_deep($d['device_id']);
                     $tok = Html::entities_deep($d['device_token']);
                     $ser = Html::entities_deep($d['serial']);
                     $label = $did . ($ser ? ' · ' . $ser : '');
                     echo '        <option value="'.$did.'" data-token="'.$tok.'" data-has-token="'.(!empty($tok) ? '1' : '0').'">'.$label.'</option>';
                  }
                  
                  // expose ticket id for JS
                  $ticket_id_js = isset($ID) ? (int)$ID : 0;
                  echo '<input type="hidden" id="remote-ticket-id" value="'.$ticket_id_js.'">';
                  echo '      </select>';

                  // Alerts
                  $no_rows = (count($rows) === 0);
                  $no_token = true;
                  foreach ($rows as $d) { if (!empty($d['device_token'])) { $no_token = false; break; } }

                  if ($no_rows) {
                     echo '<div class="alert alert-important alert-danger glpi-debug-alert" style="z-index:10000">';
                     echo __('Aucune tablette active. Ajoutez-en au moins une dans la configuration.', 'gestion');
                     echo '</div>';
                  } else if ($no_token) {
                     echo '<div class="alert alert-important alert-danger glpi-debug-alert" style="z-index:10000">';
                     echo __('Aucun token de tablette détecté : supprimez puis ré-ajoutez la tablette dans la configuration pour générer un token.', 'gestion');
                     echo '</div>';
                  }

                  echo '      <button type="button" id="remote-start" class="btn btn-primary">Demander la signature</button>';
                  echo '      <span id="remote-status" class="text-muted"></span>';
                  echo '    </div>';
                  echo '  </div>';
                  echo '</div>';
                  
                  // VERSION FUTURE : Préparer les paramètres automatiquement (TICKET + TÂCHES SANS DOCUMENT)
                  $autoParams = [];
                  
                  // Entity (récupérer l'entité du ticket)
                  $entity_name = '';
                  try {
                     $entity_sql = "SELECT e.name FROM glpi_entities e 
                                 JOIN glpi_tickets t ON e.id = t.entities_id 
                                 WHERE t.id = " . (int)$ID . " LIMIT 1";
                     $entity_result = $DB->doQuery($entity_sql);
                     if ($entity_result && $DB->numrows($entity_result) > 0) {
                        $entity_row = $DB->fetchAssoc($entity_result);
                        $entity_name = $entity_row['name'];
                     }
                  } catch (Exception $e) {
                     $entity_name = '';
                  }
                  
                  if (!empty($entity_name)) {
                     $autoParams['entity_name'] = $entity_name;
                  }
                  
                  // Informations du ticket (titre, description)
                  try {
                     $ticket_sql = "SELECT name, content FROM glpi_tickets WHERE id = " . (int)$ID . " LIMIT 1";
                     $ticket_result = $DB->doQuery($ticket_sql);
                     if ($ticket_result && $DB->numrows($ticket_result) > 0) {
                        $ticket_row = $DB->fetchAssoc($ticket_result);

                        if (!empty($ticket_row['name'])) {
                           $autoParams['ticket_title'] = $ticket_row['name'];
                        }

                        if (isset($ticket_row['content'])) {
                           // Conserver 100% du contenu, encodé en entités HTML (comme ce que tu envoies déjà : &#60;div&#62; ...)
                           $fullHtml = (string)$ticket_row['content'];
                           $autoParams['ticket_description'] = htmlentities($fullHtml, ENT_NOQUOTES, 'UTF-8');
                        }
                     }
                  } catch (Exception $e) {
                     // ignore
                  }
                  
                  // Client/Demandeur du ticket
                  try {
                     $requester_sql = "SELECT u.realname, u.firstname, u.name as username 
                                    FROM glpi_users u 
                                    JOIN glpi_tickets_users tu ON u.id = tu.users_id 
                                    WHERE tu.tickets_id = " . (int)$ID . " 
                                    AND tu.type = 1 
                                    LIMIT 1";
                     $requester_result = $DB->doQuery($requester_sql);
                     if ($requester_result && $DB->numrows($requester_result) > 0) {
                        $requester_row = $DB->fetchAssoc($requester_result);
                        $client_name = trim(($requester_row['firstname'] ?? '') . ' ' . ($requester_row['realname'] ?? ''));
                        if (empty($client_name)) {
                           $client_name = $requester_row['username'] ?? '';
                        }
                        if (!empty($client_name)) {
                           $autoParams['client_name'] = $client_name;
                        }
                     }
                  } catch (Exception $e) {
                     // Ignore les erreurs
                  }
                  
                  // NOUVEAU : Récupérer les tâches du ticket
                  try {
                     $tasks_sql = "SELECT tt.content, tt.date, u.realname, u.firstname 
                                 FROM glpi_tickettasks tt 
                                 LEFT JOIN glpi_users u ON tt.users_id = u.id 
                                 WHERE tt.tickets_id = " . (int)$ID . " 
                                 AND tt.is_private = 0 
                                 ORDER BY tt.date DESC 
                                 LIMIT 5";
                     $tasks_result = $DB->doQuery($tasks_sql);
                     if ($tasks_result && $DB->numrows($tasks_result) > 0) {
                        $tasks = [];
                        while ($task_row = $DB->fetchAssoc($tasks_result)) {
                           $task_content = (string)$task_row['content']; // conserve le HTML
                           $task_content = htmlentities($task_content, ENT_NOQUOTES, 'UTF-8');

                           $author = trim(($task_row['firstname'] ?? '') . ' ' . ($task_row['realname'] ?? ''));
                           if (empty($author)) {
                              $author = 'Système';
                           }
                           
                           if (!empty($task_content)) {
                              $tasks[] = [
                                 'content' => $task_content
                              ];
                           }
                        }
                        
                        if (!empty($tasks)) {
                           $autoParams['ticket_tasks'] = $tasks;
                        }
                     }
                  } catch (Exception $e) {
                     // Ignore les erreurs
                  }
                  
                  // NOUVEAU : Temps total d'intervention (utiliser la variable $sumtask existante)
                  if (isset($sumtask) && $sumtask > 0) {
                     $hours = floor($sumtask / 3600);
                     $minutes = floor(($sumtask % 3600) / 60);
                     $time_formatted = $hours . 'h' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
                     $autoParams['total_time'] = $time_formatted;
                     $autoParams['total_seconds'] = $sumtask;
                  }

                  // NOUVEAU : Ajouter l'email s'il est disponible
                  if (!empty($email)) {
                     $autoParams['client_email'] = $email;
                  }
                  
                  // Convertir en JSON pour JavaScript
                  $autoParamsJson = !empty($autoParams) ? json_encode($autoParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';
                  
                  echo '<script>
                  // Variable globale contenant les paramètres automatiques
                  window.REMOTE_SIGN_AUTO_PARAMS = ' . $autoParamsJson . ';
                  
                  // Script de récupération automatique des signatures déportées
                  (function() {
                  function initRemoteSignatureCapture() {
                     const stat = document.getElementById("remote-status");
                     if (!stat) {
                        setTimeout(initRemoteSignatureCapture, 2000);
                        return;
                     }
                     
                     // Observer les changements de statut
                     let lastStatus = stat.textContent;
                     const checkStatus = function() {
                        const currentStatus = stat.textContent;
                        if (currentStatus !== lastStatus) {
                        lastStatus = currentStatus;
                        
                        // Si on voit "Signature reçue", récupérer les données
                        if (currentStatus.includes("Signature reçue")) {
                           setTimeout(retrieveSignatureData, 100);
                        }
                        }
                     };
                     
                     setInterval(checkStatus, 500);
                     
                     async function retrieveSignatureData() {
                        try {
                        const sel = document.getElementById("remote-device");
                        if (!sel) return;
                        
                        const opt = sel.options[sel.selectedIndex];
                        const device_id = opt.value;
                        const device_token = opt.getAttribute("data-token");
                        const ticket_id = '.((int)$ID).';
                        
                        const r = await RemoteSign.pollTicket(ticket_id, { device_id, device_token });
                        
                        if (r.ok && r.ready && r.signature_base64) {
                           // Remplir le champ caché
                           const hiddenArea = document.getElementById("sig-dataUrl");
                           if (hiddenArea) {
                              const sigData = r.signature_base64.startsWith("data:") ? r.signature_base64 : "data:image/png;base64," + r.signature_base64;
                              hiddenArea.value = sigData;
                           }
                           
                           // Dessiner sur le canvas
                           const allCanvas = document.querySelectorAll("canvas");
                           if (allCanvas.length > 0) {
                              const canvas = allCanvas[0];
                              const ctx = canvas.getContext("2d");
                              
                              const img = new Image();
                              img.onload = function() {
                              ctx.clearRect(0, 0, canvas.width, canvas.height);
                              
                              const tempCanvas = document.createElement("canvas");
                              tempCanvas.width = img.width;
                              tempCanvas.height = img.height;
                              const tempCtx = tempCanvas.getContext("2d");
                              
                              tempCtx.drawImage(img, 0, 0);
                              
                              const imageData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
                              const data = imageData.data;
                              
                              for (let i = 0; i < data.length; i += 4) {
                                 const alpha = data[i + 3];
                                 if (alpha > 0) {
                                    data[i] = 0;
                                    data[i + 1] = 0;
                                    data[i + 2] = 0;
                                 }
                              }
                              
                              tempCtx.putImageData(imageData, 0, 0);
                              ctx.drawImage(tempCanvas, 0, 0, canvas.width, canvas.height);
                              };
                              img.onerror = function() {
                              console.error("Erreur chargement signature image");
                              };
                              const sigData = r.signature_base64.startsWith("data:") ? r.signature_base64 : "data:image/png;base64," + r.signature_base64;
                              img.src = sigData;
                           }
                           
                           // Remplir le champ nom
                           const nameField = document.getElementById("name");
                           if (nameField && r.signer_name) {
                              nameField.value = r.signer_name;
                           }
                           
                           // Remplir le champ email
                           const emailField = document.getElementById("mail");
                           if (emailField && r.signer_email) {
                              emailField.value = r.signer_email;
                              const emailCheckbox = document.getElementById("send_email");
                              if (emailCheckbox && r.signer_email.trim() !== "") {
                                 emailCheckbox.checked = true;
                              }
                           }
                        }
                        
                        } catch (e) {
                        console.error("Erreur récupération signature:", e);
                        }
                     }
                  }

                  // Initialiser
                  if (document.readyState === "loading") {
                     document.addEventListener("DOMContentLoaded", initRemoteSignatureCapture);
                  } else {
                     setTimeout(initRemoteSignatureCapture, 100);
                  }
                  })();
                  </script>';
               }
            }
         }
      }
      
      ?>
      <style>
      .modal-dialog { 
            max-width: 1050px; 
            margin: 1.75rem auto; 
      }

      .email-combo-container {
         position: relative;
         width: 100%;
      }

      .email-input {
         width: 100%;
         padding-right: 28px; 
         padding: 8px 20px 8px 8px;
         border: 1px solid #ddd;
         border-radius: 4px;
         background: white;
         font-size: 14px;
         box-sizing: border-box;
      }

      .email-dropdown-btn {
         position: absolute;
         right: 57%;
         top: 1px;
         bottom: 1px;
         background: none;
         border: none;
         cursor: pointer;
         color: #666;
         font-size: 10px;
         display: flex;
         align-items: center;
         justify-content: center;
         border-radius: 0 3px 3px 0;
         font-size: 11px; /* Taille pour bien afficher ⌄ */
         transition: transform 0.3s ease; /* Animation fluide */
      }

      /* Quand le bouton est actif (dropdown ouvert) */
      .email-dropdown-btn.open {
         transform: rotate(180deg); /* Rotation de la flèche */
      }

      .email-dropdown {
         position: absolute;
         top: 100%;
         left: 0;
         width: 44%; 
         right: auto; /* pour l’aligner avec le bouton */
         background: white;
         border: 1px solid #ddd;
         border-top: none;
         border-radius: 0 0 4px 4px;
         max-height: 200px;
         overflow-y: auto;
         z-index: 1000;
         display: none;
         box-shadow: 0 2px 5px rgba(0,0,0,0.2);
         box-sizing: border-box;
      }

      .email-option {
         padding: 8px;
         cursor: pointer;
         border-bottom: 1px solid #eee;
      }

      .email-option:hover {
         background: #f5f5f5;
      }

      .email-option:last-child {
         border-bottom: none;
      }
      </style>

      <script>
      function showEmailDropdown() {
         var dropdown = document.getElementById("email_dropdown_list");
         if (!dropdown) return; // pas de dropdown si pas d'emails

         dropdown.style.display = "block";

         // Ajoute l'état "ouvert" sur la flèche
         var btn = document.querySelector(".email-dropdown-btn");
         if (btn) btn.classList.add("open");
      }

      function toggleEmailDropdown() {
         var dropdown = document.getElementById("email_dropdown_list");
         if (!dropdown) return;

         var btn = document.querySelector(".email-dropdown-btn");
         var isOpen = dropdown.style.display === "block";

         dropdown.style.display = isOpen ? "none" : "block";
         if (btn) btn.classList.toggle("open", !isOpen);
      }

      function selectEmail(email) {
         document.getElementById("mail").value = email;

         var dropdown = document.getElementById("email_dropdown_list");
         if (dropdown) dropdown.style.display = "none";

         // Ferme visuellement la flèche
         var btn = document.querySelector(".email-dropdown-btn");
         if (btn) btn.classList.remove("open");
      }

      // Fermer le dropdown si on clique ailleurs
      document.addEventListener("click", function(event) {
         var container = document.querySelector(".email-combo-container");
         var dropdown  = document.getElementById("email_dropdown_list");
         if (!dropdown) return;

         if (!container.contains(event.target)) {
            dropdown.style.display = "none";

            // Ferme visuellement la flèche
            var btn = document.querySelector(".email-dropdown-btn");
            if (btn) btn.classList.remove("open");
         }
      });
      </script>
      <?php

      // Traitement de la variable $email pour créer un tableau
      $emailArray = array();
      if (!empty($email)) {
         $emailArray = array_filter(array_map('trim', explode(',', $email)));
         // Supprimer les doublons et réindexer
         $emailArray = array_values(array_unique($emailArray));
      }
      
      // Premier email par défaut
      $defaultEmail = !empty($emailArray) ? $emailArray[0] : '';
      
      echo '<div class="form-card">';
         echo '<div class="form-label">Mail client</div>';
         echo '<div class="form-content">';
            if ($config->fields['email'] == 1){
               echo '<div class="checkbox-group">';
                  echo '<input type="checkbox" name="mailtoclient" value="1" id="send_email">';
                  echo '<label for="send_email">Envoyer le PDF par email</label>';
               echo '</div>';
            }
               
               echo '<div class="email-combo-container">';
                  // Input principal (celui qui sera envoyé)
                  echo '<input type="email" id="mail" name="email" class="email-input" value="' . htmlspecialchars($defaultEmail) . '" placeholder="Email du client" onclick="showEmailDropdown()" onfocus="showEmailDropdown()">';
                  
                  // Bouton dropdown si on a des emails
                  if (!empty($emailArray)) {
                     echo '<button type="button" class="email-dropdown-btn" onclick="toggleEmailDropdown()"><i class="fa-solid fa-chevron-down"></i></button>';
                     
                     // Dropdown personnalisé
                     echo '<div id="email_dropdown_list" class="email-dropdown">';
                           foreach ($emailArray as $emailOption) {
                              echo '<div class="email-option" onclick="selectEmail(\'' . htmlspecialchars($emailOption, ENT_QUOTES) . '\')">';
                              echo htmlspecialchars($emailOption);
                              echo '</div>';
                           }
                     echo '</div>';
                  }
                  
               echo '</div>';
               
         echo '</div>';
      echo '</div>';
      
      // === CARTE ACTIONS ===
      echo '<div class="form-card actions-card" id="actions-bottom">';   // <— id ajouté
         echo '<div class="form-content">';
            echo '<input type="submit" name="add_cri" id="sig-submitBtn" value="Génération du PDF" class="submit-btn">';
         echo '</div>';
      echo '</div>';
      
      echo '</div>'; // Fin form-container

      // Bouton flottant "Aller en bas"
      echo '<button type="button" class="fab-go-bottom" title="Aller en bas" aria-label="Aller en bas">↓</button>';
      
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
         setTimeout(function() {
            // 4. Bouton "Aller en bas de la page"
            const goBottomBtn = document.querySelector('.fab-go-bottom');
            if (goBottomBtn) {
            goBottomBtn.addEventListener('click', () => {
               // Fermer une modale de signature si elle est ouverte
               const openedModal = document.querySelector('.signature-modal[aria-hidden="false"], .signature-modal:not([aria-hidden])');
               if (openedModal) {
                  openedModal.setAttribute('aria-hidden', 'true');
               }
               document.documentElement.classList.remove('no-scroll');
               document.body.classList.remove('no-scroll');

               // Cibler la carte Actions si présente, sinon bas de page
               const target = document.getElementById('actions-bottom');
               if (target && typeof target.scrollIntoView === 'function') {
                  target.scrollIntoView({ behavior: 'smooth', block: 'start' });
               } else {
                  window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
               }
            });
            }
            
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
            
            // Vérifier que la fonction existe avant de l'appeler
            if (typeof initializeSignatureRp === 'function') {
               initializeSignatureRp('<?php echo $uniq; ?>');
            } else {
               // Si la fonction n'existe pas encore, attendre un peu
               setTimeout(function() {
                  if (typeof initializeSignatureRp === 'function') {
                        initializeSignatureRp('<?php echo $uniq; ?>');
                  }
               }, 500);
            }
            
         }, 100); // Délai de 100ms pour s'assurer que tout est chargé
      </script>
      <?php
   }
}
?>