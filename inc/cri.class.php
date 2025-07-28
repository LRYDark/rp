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

   // Fonction de détection mobile
   private function isMobile() {
      return preg_match('/(android|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile)/i', $_SERVER['HTTP_USER_AGENT']);
   }

   public function showForm($ID, $options = []) {
      global $DB, $CFG_GLPI;
      $uniq = 'cri'.mt_rand(10000,99999);

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
         //---------------------SQL / VAR ----------------------
         ?>
            <style> /*Style du modale et du tableau */

                  /* Styles existants pour desktop */
        .modal-dialog { 
            max-width: 1050px; 
            margin: 1.75rem auto; 
        }
        .table td, .table td { 
            border: none !important;
        }
        
        /* Styles pour mobile */
        @media (max-width: 768px) {
            .mobile-form-row {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                margin-bottom: 15px;
                padding: 15px;
            }
            
            .mobile-form-label {
                font-weight: bold;
                color: #495057;
                margin-bottom: 8px;
                display: block;
                font-size: 14px;
            }
            
            .mobile-form-content {
                width: 100%;
            }
            
            .mobile-form-content input,
            .mobile-form-content textarea,
            .mobile-form-content select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                font-size: 16px; /* Évite le zoom sur iOS */
            }
            
            .mobile-signature-section {
                background: #e9ecef;
                border-radius: 5px;
                padding: 15px;
                margin: 15px 0;
            }
            
            .mobile-checkbox-group {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 10px 0;
            }
            
            .mobile-radio-group {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .mobile-radio-item {
                display: flex;
                align-items: center;
                gap: 8px;
            }
        }
        
        /* Vos styles de signature existants... */
        #<?= $uniq ?> .canvas.sig-base { image-rendering: auto; }

               .modal-dialog { 
                  max-width: 1050px; 
                  margin: 1.75rem auto; 
               }
               .table td, .table td { 
                  border: none !important;
               }
               
               /* ou 'pixelated' si tu préfères des bords plus francs */
               #<?= $uniq ?> .canvas.sig-base { image-rendering: auto; }
               /* Container & bouton zoom (inchangé) */
               #<?= $uniq ?> .signature-container{position:relative;display:inline-block}
               #<?= $uniq ?> .zoom-btn{position:absolute;top:-28px;right:0;background:#007bff;color:#fff;border:0;padding:5px 10px;border-radius:3px;cursor:pointer;font-size:12px;z-index:1}
               #<?= $uniq ?> .zoom-btn:hover{background:#0056b3}

               /* Overlay plein écran */
               #<?= $uniq ?> .signature-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:200000}
               #<?= $uniq ?> .signature-modal.active{display:block}
               #<?= $uniq ?> .modal-wrapper{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:10px}

               /* >>> Renommées pour éviter Bootstrap <<< */
               #<?= $uniq ?> .cri-modal-content{
               background:#fff;border-radius:10px;position:relative;
               width:100%;height:100%;max-width:1200px;max-height:600px;
               display:flex !important;                 /* évite la pile verticale bootstrap */
               flex-direction:row !important;           /* canvas + panneau côte à côte */
               overflow:hidden;
               }

               #<?= $uniq ?> .cri-canvas-wrapper{
               flex:1 1 auto;display:flex;align-items:center;justify-content:center;
               padding:20px;background:#f8f9fa;min-width:0;
               }

               #<?= $uniq ?> .modal-canvas{border:2px solid #333;background:#fff;touch-action:none;max-width:100%;max-height:100%;cursor:crosshair}

               #<?= $uniq ?> .cri-controls-panel{
               flex:0 0 150px;background:#e9ecef;display:flex;flex-direction:column;
               justify-content:center;padding:20px;border-left:1px solid #dee2e6
               }
               #<?= $uniq ?> .cri-controls-panel button{margin:10px 0;padding:12px 20px;border:0;border-radius:5px;cursor:pointer;font-size:14px;font-weight:700;transition:.2s}
               #<?= $uniq ?> .btn-validate{background:#28a745;color:#fff}
               #<?= $uniq ?> .btn-validate:hover{background:#218838}
               #<?= $uniq ?> .btn-cancel{background:#dc3545;color:#fff}
               #<?= $uniq ?> .btn-cancel:hover{background:#c82333}
               #<?= $uniq ?> .btn-clear{background:#ffc107;color:#000}
               #<?= $uniq ?> .btn-clear:hover{background:#e0a800}

               /* Mobile uniquement */
               @media (max-width: 1024px) {
               #<?= $uniq ?> .signature-modal.active { inset: 0; }
               #<?= $uniq ?> .cri-modal-content{
                  width: 100svw;   /* sinon 100vw si svw non supporté */
                  height: 100svh;  /* sinon 100vh */
                  max-width: none;
                  max-height: none;
                  border-radius: 0;
               }
               #<?= $uniq ?> .cri-canvas-wrapper{ padding: max(12px, env(safe-area-inset-left)); }
               #<?= $uniq ?> .cri-controls-panel{ flex-basis: 110px; padding: 10px; }
               }

               /* corrige le sélecteur */
               #<?= $uniq ?> canvas.sig-base { image-rendering: auto; }

               /* overlay “tournez l’écran” */
               #<?= $uniq ?> .rotate-gate{ position:absolute; inset:0; display:none;
               align-items:center; justify-content:center; background:rgba(0,0,0,.65);
               color:#fff; z-index: 1000; text-align:center; padding: 24px; }
               #<?= $uniq ?> .rotate-gate.show{ display:flex; }
            </style>
         <?php
         
         echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/front/cripdf.form.php\" method=\"post\" name=\"formReport\">";

         echo Html::hidden('REPORT_ID', ['value' => $ID]);

         // tableau bootstrap -> glpi
         $querytask = "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $ID $is_private";
         $resulttask = $DB->query($querytask);
         $numbertask = $DB->numrows($resulttask);


      // Détection mobile et rendu conditionnel
         if ($this->isMobile()) { // ########################################################### VERSION POUR MOBILE ###########################################################
            
            
            echo '<div class="mobile-form-container">';
            
            if($_POST["modal"] != "form_client" && $numbertask > 0 || $_POST["modal"] == "form_client"){
                  if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0){
                     // Type de rapport - Version mobile
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Type de rapport:</div>';

                              $entity_parrent1_id = $config->fields['entity_parrent1'];
                              $entity_parrent1 = $DB->query("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent1_id")->fetch_object();
                              $entity_parrent2_id = $config->fields['entity_parrent2'];
                              $entity_parrent2 = $DB->query("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent2_id")->fetch_object();
                              
                              $group = $this->getEntityGroupFromEntityId($result->id, $entity_parrent1->name, $entity_parrent2->name);
                              $checked1 = ($group == 'entity_parrent1' || ($group != 'entity_parrent2')) ? 'checked' : '';
                              $checked2 = ($group == 'entity_parrent2') ? 'checked' : '';
                              
                              echo '<div class="mobile-radio-group">';
                                 echo '<div class="mobile-radio-item">';
                                    echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent1\" $checked1 id=\"entity1_mobile\">";
                                    echo "<label for=\"entity1_mobile\">" . $entity_parrent1->name . "</label>";
                                 echo '</div>';
                                 echo '<div class="mobile-radio-item">';
                                    echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent2\" $checked2 id=\"entity2_mobile\">";
                                    echo "<label for=\"entity2_mobile\">" . $entity_parrent2->name . "</label>";
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
            
            if($_POST["modal"] != "form_client" && $numbertask > 0){
               $description = $result->content;
                  // Description du problème - Version mobile
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">Description du Problème</div>';
                     $checked = ($_POST["modal"] == "form_rapport_hotline" || $_POST["modal"] == "form_client") ? "checked" : "";
                     echo '<div class="mobile-checkbox-group">';
                        echo '<input type="checkbox" value="check" name="CHECK_DESCRIPTION_TICKET" '.$checked.' id="desc_check">';
                        echo '<label for="desc_check">Visible dans le rapport</label>';
                     echo '</div>';
                     echo '<div class="mobile-form-content">';
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
            
            // Formulaire client - Version mobile
            if($_POST["modal"] == "form_client"){
                  // Informations PC
                  $items = $DB->query("SELECT requesttypes_id FROM `glpi_tickets` WHERE id = $ID")->fetch_object();
                  if($items->requesttypes_id == 1){
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Informations PC</div>';
                        echo '<div class="mobile-form-content">';
                              echo '<label for="serialnumber">Numéro de série</label>';
                              echo '<input type="text" name="serialnumber" required placeholder="Numéro de série" value="'.$serialnumber.'">';
                              echo '<br><br>';
                              echo '<label for="model">Marque / Modèle</label>';
                              echo '<input type="text" name="model" placeholder="Marque / Modèle">';
                        echo '</div>';
                     echo '</div>';
                     
                     // Personne en charge du matériel
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Personne en charge du matériel</div>';
                        echo '<div class="mobile-form-content">';
                              echo '<label for="NameRespMat">Nom / Prénom</label>';
                              echo '<input type="text" name="NameRespMat" required placeholder="Nom du Responsable matériel">';
                              echo '<br><br>';
                              echo '<label for="CoordRespMat">Téléphone / Mail</label>';
                              echo '<input type="text" name="CoordRespMat" required placeholder="Mail/Tel du Responsable matériel">';
                        echo '</div>';
                     echo '</div>';
                     
                     // Utilisateur différent
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Utilisateur différent</div>';
                        echo '<div class="mobile-checkbox-group">';
                              echo '<input type="checkbox" name="equal" value="equal" id="foo">';
                              echo '<label for="foo">L\'utilisateur du matériel est différent de la personne l\'ayant pris en charge</label>';
                        echo '</div>';
                     echo '</div>';
                     
                     // Section utilisateur (cachée par défaut)
                     echo '<div id="bar" class="mobile-form-row" style="display:none;">';
                        echo '<div class="mobile-form-label">Utilisateur du matériel</div>';
                        echo '<div class="mobile-form-content">';
                              echo '<label for="NameUtilpMat">Nom / Prénom</label>';
                              echo '<input type="text" name="NameUtilpMat" placeholder="Nom de l\'utilisateur">';
                              echo '<br><br>';
                              echo '<label for="CoordUtilpMat">Téléphone / Mail</label>';
                              echo '<input type="text" name="CoordUtilpMat" placeholder="Mail/Tel de l\'utilisateur">';
                        echo '</div>';
                     echo '</div>';
                     
                     // Accessoires
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Accessoires</div>';
                           echo '<div class="mobile-checkbox-group">';
                              echo '<input type="checkbox" name="mouse" value="Souris / " id="mouse"> <label for="mouse">Souris</label>';
                           echo '</div>';
                           echo '<div class="mobile-checkbox-group">';
                              echo '<input type="checkbox" name="keyboard" value="Clavier / " id="keyboard"> <label for="keyboard">Clavier</label>';
                           echo '</div>';
                           echo '<div class="mobile-checkbox-group">';
                              echo '<input type="checkbox" name="bag" value="Sachoche / " id="bag"> <label for="bag">Sacoche</label>';
                           echo '</div>';
                           echo '<div class="mobile-checkbox-group">';
                              echo '<input type="checkbox" name="feed" value="Alimentation / " id="feed"> <label for="feed">Alimentation</label>';
                           echo '</div>';
                           echo '<div class="mobile-checkbox-group">';
                              echo '<input type="checkbox" name="dockstation" value="Dock Station / " id="dock"> <label for="dock">Dock Station</label>';
                           echo '</div>';
                           echo '<br>';
                           echo '<div class="mobile-form-content">';
                              echo '<label for="other">Autres :</label>';
                              echo '<textarea name="other" maxlength="100" placeholder="Autre(s) accessoire(s)" rows="3"></textarea>';
                        echo '</div>';
                     echo '</div>';
                     
                     // Sauvegarde et formatage
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Sauvegarde des données ?</div>';
                        echo '<div class="mobile-radio-group">';
                              echo '<div class="mobile-radio-item">';
                                 echo '<input type="radio" name="DataSave" value="Oui" checked id="save_yes">';
                                 echo '<label for="save_yes">OUI</label>';
                              echo '</div>';
                              echo '<div class="mobile-radio-item">';
                                 echo '<input type="radio" name="DataSave" value="Non" id="save_no">';
                                 echo '<label for="save_no">NON</label>';
                              echo '</div>';
                        echo '</div>';
                     echo '</div>';
                     
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Formatage autorisé ?</div>';
                        echo '<div class="mobile-radio-group">';
                              echo '<div class="mobile-radio-item">';
                                 echo '<input type="radio" name="DataFormatting" value="Oui" id="format_yes">';
                                 echo '<label for="format_yes">OUI</label>';
                              echo '</div>';
                              echo '<div class="mobile-radio-item">';
                                 echo '<input type="radio" name="DataFormatting" value="Non" checked id="format_no">';
                                 echo '<label for="format_no">NON</label>';
                              echo '</div>';
                        echo '</div>';
                     echo '</div>';
                     
                     // Informations de session
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Informations de session</div>';
                        echo '<div class="mobile-form-content">';
                              echo '<label for="idsession">Nom d\'ouverture de session</label>';
                              echo '<input type="text" name="idsession" autocomplete="off" placeholder="Nom d\'ouverture de session">';
                              echo '<br><br>';
                              echo '<label for="id_password">Mot de passe</label>';
                              echo '<div style="position:relative;">';
                                 echo '<input type="password" name="userpassword" autocomplete="new-password" required id="id_password" placeholder="Mot de passe">';
                                 echo '<i class="far fa-eye" id="togglePassword" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;"></i>';
                              echo '</div>';
                        echo '</div>';
                     echo '</div>';
                  }
                  
                  // Informations client
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">Nom de la Société / Client *</div>';
                     echo '<div class="mobile-form-content">';
                        echo '<input type="text" required id="society" name="society" value="'.$society.'">';
                     echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">Adresse *</div>';
                     echo '<div class="mobile-form-content">';
                        echo '<input type="text" id="address" required name="address" value="'.$address.'">';
                     echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">Ville *</div>';
                     echo '<div class="mobile-form-content">';
                        echo '<input type="text" id="town" required name="town" value="'.$town.'">';
                     echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">Code postal *</div>';
                     echo '<div class="mobile-form-content">';
                        echo '<input type="tel" id="postcode" required name="postcode" value="'.$postcode.'">';
                     echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">N° de téléphone</div>';
                     echo '<div class="mobile-form-content">';
                        echo '<input type="tel" id="phone" name="phone" value="'.$phone.'">';
                     echo '</div>';
                  echo '</div>';
                  
                  echo "<input type='hidden' name='Form' value='FormClient' />";
            }
            
            // Version mobile pour les tâches et suivis (formulaire rapport)
            elseif($_POST["modal"] == "form_rapport" || $_POST["modal"] == "form_rapport_hotline"){
                  echo "<input type='hidden' name='Form' value='FormRapport' />";
                  
                  if($numbertask > 0){
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Liste des tâches</div>';
                     echo '</div>';
                     
                     $i=1;
                     while ($data = $DB->fetchArray($resulttask)) {
                        $checked = "";
                        
                        echo '<div class="mobile-form-row">';
                              echo '<div class="mobile-form-label">';
                                 echo 'Tâche N°'.$i++;
                                 if ($data['is_private'] == 1) echo ' - <span style="color:red">Privée <i class="ti ti-lock"></i></span>';
                                 echo '<br><small>'.$data["date"].' - '.$data['name'].'</small>';
                              echo '</div>';
                              
                              if($config->fields['choice'] == 1){
                                 if($config->fields['check_public_task'] == 1 && $data['is_private'] == 0){
                                    $checked = "checked";
                                 }
                                 if($config->fields['check_private_task'] == 1 && $data['is_private'] == 1){
                                    $checked = "checked";
                                 }
                                 echo '<div class="mobile-checkbox-group">';
                                    echo '<input type="checkbox" value="check" name="tasks_pdf_'.$data['id'].'" '.$checked.' id="task_'.$data['id'].'">';
                                    echo '<label for="task_'.$data['id'].'">Visible dans le rapport</label>';
                                 echo '</div>';
                              }else{
                                 echo '<input type="hidden" value="check" name="tasks_pdf_'.$data['id'].'" checked/>';
                              }
                              
                              echo '<input type="hidden" value="'.$data["date"].'" name="tasks_date_'.$data['id'].'" />';
                              echo '<input type="hidden" value="'.$data["actiontime"].'" name="tasks_time_'.$data['id'].'" />';
                              echo '<input type="hidden" value="'.$data["name"].'" name="tasks_name_'.$data['id'].'" />';
                              
                              echo '<div class="mobile-form-content">';
                                 Html::textarea([
                                    'name'              => 'TASKS_DESCRIPTION'.$data['id'],
                                    'value'             => Glpi\RichText\RichText::getSafeHtml($data["content"], true),
                                    'enable_richtext'   => true,
                                    'enable_fileupload' => false,
                                    'enable_images'     => false,
                                 ]);
                              echo '</div>';
                        echo '</div>';
                        
                        // Gestion des images et calcul du temps (votre code existant)
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
                  
                  // Suivis (version mobile similaire aux tâches)
                  $querysuivi = "SELECT glpi_itilfollowups.id, content, date, name, is_private FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $ID $is_private";
                  $resultsuivi = $DB->query($querysuivi);
                  $numbersuivi = $DB->numrows($resultsuivi);
                  
                  if($numbersuivi > 0){
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Liste des suivis</div>';
                     echo '</div>';
                     
                     $i=1;
                     while ($dataSuivi = $DB->fetchArray($resultsuivi)) {
                        // Code similaire aux tâches pour les suivis...
                        // (je raccourcis ici mais suivez le même pattern)
                     }
                  }
                  
                  // Options d'affichage
                  echo '<div class="mobile-form-row">';
                     echo '<div class="mobile-form-label">Options d\'affichage</div>';
                     echo '<div class="mobile-checkbox-group">';
                        echo '<input type="checkbox" name="rapporttime" value="yes" checked id="show_time">';
                        echo '<label for="show_time">Afficher le temps d\'intervention ('.mb_convert_encoding(floor($sumtask / 3600).str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8').')</label>';
                     echo '</div>';
                  echo '</div>';
                  
                  // Images des tâches et suivis si présentes
                  if($img_sum_task != 0){
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-checkbox-group">';
                              $checkedimgtask = ($config->fields['ImgTasks'] == 1) ? "checked" : "";
                              echo '<input type="checkbox" name="rapportimgtask" value="yes" '.$checkedimgtask.' id="show_img_task">';
                              echo '<label for="show_img_task">Afficher les images des tâches ('.$img_sum_task.' image(s))</label>';
                        echo '</div>';
                     echo '</div>';
                  }
                  
                  if($img_sum_suivi != 0){
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-checkbox-group">';
                              $checkedimgsuivis = ($config->fields['ImgSuivis'] == 1) ? "checked" : "";
                              echo '<input type="checkbox" name="rapportimgsuivi" value="yes" '.$checkedimgsuivis.' id="show_img_suivi">';
                              echo '<label for="show_img_suivi">Afficher les images des suivis ('.$img_sum_suivi.' image(s))</label>';
                        echo '</div>';
                     echo '</div>';
                  }
            }
            
            // Section signature mobile
            $signature = "false";
            if ($_POST["modal"]  == "form_rapport_hotline" && $config->fields['sign_rp_hotl'] == 1) $signature = "true";
            if ($_POST["modal"]  == "form_rapport" && $config->fields['sign_rp_tech'] == 1) $signature = "true";
            if ($_POST["modal"]  == "form_client" && $config->fields['sign_rp_charge'] == 1) $signature = "true";
            
            if($signature == 'true'){
                  echo '<div class="mobile-signature-section">';
                     echo '<div class="mobile-form-label">SIGNATURE CLIENT</div>';
                     
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Nom / Prénom du client</div>';
                        echo '<div class="mobile-form-content">';
                              echo '<input type="text" id="name" name="name" placeholder="Nom / Prénom du client" required>';
                        echo '</div>';
                     echo '</div>';
                     
                     echo '<div class="mobile-form-row">';
                        echo '<div class="mobile-form-label">Signature client</div>';
                        echo '<div class="mobile-form-content">';
                              // Votre code de signature existant
                              echo "<div id='".$uniq."' class='cri-signature-root' style='position:relative'>";
                                 echo "<tr><br>";
                                    echo "<div id='".$uniq."' class='cri-signature-root' style='position:relative'>";
                                       // petit canvas + bouton zoom + bouton effacer
                                       echo "  <div class='signature-container'>";
                                       echo "    <button type='button' class='zoom-btn'>Agrandir <i class='fa-solid fa-up-right-and-down-left-from-center'></i></button>";
                                       echo "    <canvas id='sig-canvas-".$uniq."' width='320' height='80' class='sig-base' style='border:1px solid #ccc;'></canvas>";
                                       echo "  </div>";
                                       echo "  <br>";
                                       echo "  <button type='button' id='sig-clearBtn-".$uniq."' class='resetButton' style='margin:5px 0 0 0; padding:5px 10px;'>Supprimer la signature</button>";

                                       // modal interne pour le zoom (overlay)
                                       echo "  <div class='signature-modal' aria-hidden='true'>";
                                       echo "    <div class='modal-wrapper'>";                       // <- on garde
                                       echo "      <div class='cri-modal-content'>";                 // <- renommé
                                       echo "      <div class='rotate-gate'>\n";
                                       echo "        <div>\n";
                                       echo "          <div style='font-size:18px;font-weight:700;margin-bottom:8px'>\n";
                                       echo "            Tournez votre téléphone en mode paysage\n";
                                       echo "          </div>\n";
                                       echo "          <div style='opacity:0.9'>La zone de signature va s’agrandir automatiquement.</div>\n";
                                       echo "        </div>\n";
                                       echo "      </div>\n";
                                       echo "        <div class='cri-canvas-wrapper'>";              // <- renommé
                                       echo "          <canvas id='modal-canvas-".$uniq."' class='modal-canvas'></canvas>";
                                       echo "        </div>";
                                       echo "        <div class='cri-controls-panel'>";              // <- renommé
                                       echo "          <button type='button' class='btn-validate'>Valider</button>";
                                       echo "          <button type='button' class='btn-clear'>Effacer</button>";
                                       echo "          <button type='button' class='btn-cancel'>Annuler</button>";
                                       echo "        </div>";
                                       echo "      </div>";
                                       echo "    </div>";
                                       echo "  </div>";
                                    echo "</div>";
                                 echo "</tr><br>";
                              echo "</div>";
                        echo '</div>';
                     echo '</div>';
                  echo '</div>';
            }
            
            // Email client
            echo '<div class="mobile-form-row">';
                  echo '<div class="mobile-form-label">Mail client</div>';
                  if ($config->fields['email'] == 1){
                     echo '<div class="mobile-checkbox-group">';
                        echo '<input type="checkbox" name="mailtoclient" value="1" id="send_email">';
                        echo '<label for="send_email">Envoyer le PDF par email</label>';
                     echo '</div>';
                  }
                  echo '<div class="mobile-form-content">';
                     echo '<input type="email" id="mail" name="email" value="'.$email.'" placeholder="Email du client">';
                  echo '</div>';
            echo '</div>';
            
            // Bouton de génération
            echo '<div class="mobile-form-row" style="text-align: center;">';
                  echo '<input type="submit" name="add_cri" id="sig-submitBtn" value="Génération du PDF" class="submit" style="width: 100%; padding: 15px; font-size: 16px; background: #007bff; color: white; border: none; border-radius: 5px;">';
            echo '</div>';
            
            echo '</div>'; // Fin mobile-form-container
            
         } else { // ########################################################### VERSION POUR PC ###########################################################

            echo '<div class="table-responsive">';
            echo "<table class='table'>"; 
      
            if($_POST["modal"] != "form_client" && $numbertask > 0 || $_POST["modal"] == "form_client"){
               if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0){
                  // Récupération des noms des entités
                  $entity_parrent1_id = $config->fields['entity_parrent1'];
                  $entity_parrent1 = $DB->query("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent1_id")->fetch_object();

                  $entity_parrent2_id = $config->fields['entity_parrent2'];
                  $entity_parrent2 = $DB->query("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent2_id")->fetch_object();

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
                  $resulttask = $DB->query($querytask);
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
                                 $ImgIdDoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
                                 if (isset($ImgIdDoc->documents_id)){
                                    $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
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
                                 $ImgIdDoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
                                 if (isset($ImgIdDoc->documents_id)){
                                    $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
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

                  // TABLEAU 3 : Canvas pour signature et bouton de suppression
                     echo "<tr>";
                        echo "<td class='table-secondary'>";
                           echo 'Signature client';
                        echo "</td>";

                        echo "<td>";
                           echo "<div id='".$uniq."' class='cri-signature-root' style='position:relative'>";
                              // petit canvas + bouton zoom + bouton effacer
                              echo "  <div class='signature-container'>";
                              echo "    <button type='button' class='zoom-btn'>Agrandir <i class='fa-solid fa-up-right-and-down-left-from-center'></i></button>";
                              echo "    <canvas id='sig-canvas-".$uniq."' width='320' height='80' class='sig-base' style='border:1px solid #ccc;'></canvas>";
                              echo "  </div>";
                              echo "  <br>";
                              echo "  <button type='button' id='sig-clearBtn-".$uniq."' class='resetButton' style='margin:5px 0 0 0; padding:5px 10px;'>Supprimer la signature</button>";

                              // modal interne pour le zoom (overlay)
                              echo "  <div class='signature-modal' aria-hidden='true'>";
                              echo "    <div class='modal-wrapper'>";                       // <- on garde
                              echo "      <div class='cri-modal-content'>";                 // <- renommé
                              echo "      <div class='rotate-gate'>\n";
                              echo "        <div>\n";
                              echo "          <div style='font-size:18px;font-weight:700;margin-bottom:8px'>\n";
                              echo "            Tournez votre téléphone en mode paysage\n";
                              echo "          </div>\n";
                              echo "          <div style='opacity:0.9'>La zone de signature va s’agrandir automatiquement.</div>\n";
                              echo "        </div>\n";
                              echo "      </div>\n";
                              echo "        <div class='cri-canvas-wrapper'>";              // <- renommé
                              echo "          <canvas id='modal-canvas-".$uniq."' class='modal-canvas'></canvas>";
                              echo "        </div>";
                              echo "        <div class='cri-controls-panel'>";              // <- renommé
                              echo "          <button type='button' class='btn-validate'>Valider</button>";
                              echo "          <button type='button' class='btn-clear'>Effacer</button>";
                              echo "          <button type='button' class='btn-cancel'>Annuler</button>";
                              echo "        </div>";
                              echo "      </div>";
                              echo "    </div>";
                              echo "  </div>";
                           echo "</div>";
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
      }
         
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
            (function(){
               const root = document.getElementById('<?= $uniq ?>');
               if (!root) return;

               // Elements
               const originalCanvas = root.querySelector('#sig-canvas-<?= $uniq ?>');
               const modalCanvas    = root.querySelector('#modal-canvas-<?= $uniq ?>');
               const modalOverlay   = root.querySelector('.signature-modal');
               const btnZoom        = root.querySelector('.zoom-btn');
               const btnClearBase   = root.querySelector('#sig-clearBtn-<?= $uniq ?>');
               const btnValidate    = root.querySelector('.btn-validate');
               const btnClearModal  = root.querySelector('.btn-clear');
               const btnCancel      = root.querySelector('.btn-cancel');

               // Contexts
               const originalCtx = originalCanvas.getContext('2d');
               const modalCtx    = modalCanvas.getContext('2d');

               // Canvas/calque d'export (non affiché)
               const modalExportCanvas = document.createElement('canvas');
               const modalExportCtx    = modalExportCanvas.getContext('2d');

               // ---- constantes d'épaisseur (en pixels CSS) ----
               const TARGET_BASE_LINE   = 1.8; // épaisseur VISIBLE voulue dans le petit canvas
               const VISUAL_MODAL_LINE  = 1.6; // un poil plus fin visuellement dans le modal

               // ---- setup générique d'un ctx ----
               function setup(ctx, lw){
                  ctx.strokeStyle = '#000';
                  ctx.lineWidth   = lw;
                  ctx.lineCap     = 'round';
                  ctx.lineJoin    = 'round';
               }

               // ---- prise en compte du DPR (HiDPI) ----
               function fixDPR(canvas, ctx, cssW, cssH){
                  const dpr = window.devicePixelRatio || 1;
                  canvas.style.width  = cssW + 'px';
                  canvas.style.height = cssH + 'px';
                  canvas.width  = Math.round(cssW * dpr);
                  canvas.height = Math.round(cssH * dpr);
                  ctx.setTransform(dpr, 0, 0, dpr, 0, 0); // 1 unité = 1 px CSS
               }

               // ---- petit canvas (base) ----
               const baseCSSW = originalCanvas.clientWidth  || 320;
               const baseCSSH = originalCanvas.clientHeight || 80;
               const DPR_BASE = fixDPR(originalCanvas, originalCtx, baseCSSW, baseCSSH);
               // épaisseur visible voulue * DPR
               setup(originalCtx, TARGET_BASE_LINE);

               // ---- modal : applique styles après avoir fixé taille & DPR ----
               function applyModalStyle(){
                  const ratio = modalExportCanvas.width / originalCanvas.width; // deux tailles *internes* (DPR inclus)
                  const exportLine = Math.max(1, TARGET_BASE_LINE * ratio);     // pour conserver l’épaisseur après réduction
                  setup(modalCtx,       VISUAL_MODAL_LINE);                     // visuel modal
                  setup(modalExportCtx, exportLine);                            // calque d’export (épais)
                  }

                  // Dessin (Pointer Events)
                  let currentCanvas = originalCanvas;
                  let currentCtx    = originalCtx;
                  let drawing = false;
                  let lastPos = {x:0,y:0};

                  function getPos(e, canvas){
                  const rect = canvas.getBoundingClientRect();
                  const t = e.touches?.[0] || e.changedTouches?.[0] || e;
                  return { x: t.clientX - rect.left, y: t.clientY - rect.top };
               }

               function start(e, canvas){
                  e.preventDefault();
                  currentCanvas = canvas;
                  currentCtx    = canvas.getContext('2d');
                  drawing = true;
                  lastPos = getPos(e, canvas);
                  if (e.pointerId != null) canvas.setPointerCapture(e.pointerId);
               }

               // ---- dessin : si on dessine dans le modal, doubler sur le calque export ----
               function move(e){
                  if (!drawing) return;
                  const p = getPos(e, currentCanvas);
                  currentCtx.beginPath();
                  currentCtx.moveTo(lastPos.x, lastPos.y);
                  currentCtx.lineTo(p.x, p.y);
                  currentCtx.stroke();

                  if (currentCanvas === modalCanvas) {
                     modalExportCtx.beginPath();
                     modalExportCtx.moveTo(lastPos.x, lastPos.y);
                     modalExportCtx.lineTo(p.x, p.y);
                     modalExportCtx.stroke();
                  }
                  lastPos = p;
               }

               function end(e){
                  if (!drawing) return;
                  drawing = false;
                  currentCtx.beginPath();
                  if (e && e.pointerId != null) { try { currentCanvas.releasePointerCapture(e.pointerId); } catch{} }
               }

               function bindCanvas(canvas){
                  canvas.addEventListener('pointerdown', (e)=>start(e, canvas));
                  canvas.addEventListener('pointermove', move);
                  canvas.addEventListener('pointerup', end);
                  canvas.addEventListener('pointercancel', end);
                  // éviter zoom iOS/scroll pendant dessin
                  canvas.addEventListener('touchstart', (e)=>e.preventDefault(), {passive:false});
                  canvas.addEventListener('touchmove',  (e)=>e.preventDefault(), {passive:false});
               }

               bindCanvas(originalCanvas);
               bindCanvas(modalCanvas);

               // Effacer base
               btnClearBase.addEventListener('click', ()=>{
                  originalCtx.clearRect(0,0,originalCanvas.width, originalCanvas.height);
                  setup(originalCtx, TARGET_BASE_LINE);
               });
               btnClearModal.addEventListener('click', ()=>{
                  modalCtx.clearRect(0,0,modalCanvas.width, modalCanvas.height);
                  modalExportCtx.clearRect(0,0,modalExportCanvas.width, modalExportCanvas.height);
                  applyModalStyle();
               });

               // --- helpers ---
               const isMobileScreen = () => window.innerWidth <= 1024;
               const isLandscape    = () => window.matchMedia("(orientation: landscape)").matches;
               const rotateGate     = root.querySelector('.rotate-gate');

               // Taille “desktop” (modal d’origine) : on cale sur l’espace dispo du wrapper
               function sizeModalCanvasDesktop(){
               const wrapper = root.querySelector('.cri-canvas-wrapper');
               const r = wrapper.getBoundingClientRect();
               // on garde ton ratio ~3:1, SANS toucher au layout du modal
               const pad = 20, aspect = 3;
               let w = Math.max(360, Math.floor(r.width  - pad*2));
               let h = Math.max(120, Math.floor(r.height - pad*2));
               if (w / h > aspect) { w = Math.floor(h * aspect); } else { h = Math.floor(w / aspect); }

               fixDPR(modalCanvas, modalCtx, w, h);
                  modalExportCanvas.width  = modalCanvas.width;
                  modalExportCanvas.height = modalCanvas.height;
                  applyModalStyle();
               }

               // Taille “mobile paysage” : plein écran (moins le panneau de boutons)
               function sizeModalCanvasMobile(){
                  const panelW = Math.max(100, root.querySelector('.cri-controls-panel').getBoundingClientRect().width || 120);
                  const pad = 20, aspect = 3;
                  let availW = Math.max(320, window.innerWidth  - panelW - pad*2);
                  let availH = Math.max(160, window.innerHeight - pad*2);
                  let w = availW, h = availH;
                  if (w / h > aspect) { w = Math.floor(h * aspect); } else { h = Math.floor(w / aspect); }

                  fixDPR(modalCanvas, modalCtx, w, h);
                  modalExportCanvas.width  = modalCanvas.width;
                  modalExportCanvas.height = modalCanvas.height;
                  applyModalStyle();
               }

               // Affiche/masque l’overlay “tournez” et redimensionne en mobile
               function updateOrientationGate(){
               if (!isMobileScreen()) return; // ne rien faire sur desktop
               if (isLandscape()) {
                  rotateGate.classList.remove('show');
                  sizeModalCanvasMobile();
               } else {
                  rotateGate.classList.add('show');
               }
               }

               // ---- ouverture du modal ----
               btnZoom.addEventListener('click', ()=>{
               document.documentElement.classList.add('no-scroll');
               modalOverlay.classList.add('active');

               if (isMobileScreen()) {
                  // mobile : impose paysage
                  updateOrientationGate();
                  if (isLandscape()) {
                     modalCtx.clearRect(0,0,modalCanvas.width, modalCanvas.height);
                     modalExportCtx.clearRect(0,0,modalExportCanvas.width, modalExportCanvas.height);
                     modalCtx.drawImage(originalCanvas, 0, 0, modalCanvas.width, modalCanvas.height);
                     modalExportCtx.drawImage(originalCanvas, 0, 0, modalExportCanvas.width, modalExportCanvas.height);
                  }
               } else {
                  // desktop : garder la taille du modal d’origine
                  sizeModalCanvasDesktop();
                  modalCtx.clearRect(0,0,modalCanvas.width, modalCanvas.height);
                  modalExportCtx.clearRect(0,0,modalExportCanvas.width, modalExportCanvas.height);
                  modalCtx.drawImage(originalCanvas, 0, 0, modalCanvas.width, modalCanvas.height);
                  modalExportCtx.drawImage(originalCanvas, 0, 0, modalExportCanvas.width, modalExportCanvas.height);
               }
               });

               // Recalcule seulement en mobile
               window.addEventListener('orientationchange', updateOrientationGate);
               window.addEventListener('resize', updateOrientationGate);

               // Fermer modal = retirer no-scroll
               btnCancel.addEventListener('click', ()=>{
                  modalOverlay.classList.remove('active');
                  document.documentElement.classList.remove('no-scroll');
               });
               btnValidate.addEventListener('click', ()=>{
                  const tw = originalCanvas.width, th = originalCanvas.height;
                  originalCtx.clearRect(0,0,tw,th);
                  originalCtx.save();
                  originalCtx.imageSmoothingEnabled = true;
                  originalCtx.imageSmoothingQuality = 'high';
                  originalCtx.drawImage(modalExportCanvas, 0, 0, tw, th);
                  originalCtx.restore();

                  modalOverlay.classList.remove('active');
                  document.documentElement.classList.remove('no-scroll');
               });

               // ---- valider : copie sans lissage depuis le calque d’export ----
               btnValidate.addEventListener('click', ()=>{
                  const tw = originalCanvas.width;
                  const th = originalCanvas.height;

                  originalCtx.clearRect(0,0,tw,th);
                  originalCtx.save();
                  originalCtx.imageSmoothingEnabled = true;       // <= activer
                  originalCtx.imageSmoothingQuality = 'high';     // 'low' | 'medium' | 'high'
                  originalCtx.drawImage(modalExportCanvas, 0, 0, tw, th);
                  originalCtx.restore();

                  modalOverlay.classList.remove('active');
               });

               // Annuler
               btnCancel.addEventListener('click', ()=>{
                  modalOverlay.classList.remove('active');
                  end({});
               });

               // Remplir le champ caché à l'envoi
               const submitBtn  = document.getElementById('sig-submitBtn');
               const hiddenArea = document.getElementById('sig-dataUrl');
               if (submitBtn && hiddenArea && !submitBtn.dataset.sigInit) {
                  submitBtn.dataset.sigInit = '1';
                  submitBtn.addEventListener('click', function(){
                  hiddenArea.value = originalCanvas.toDataURL();
                  });
               }

               // anti double-tap zoom iOS
               document.addEventListener('touchend', (function(){
                  let last = 0;
                  return function(e){
                  const now = Date.now();
                  if (now - last < 300) e.preventDefault();
                  last = now;
                  };
               })(), {passive:false});

            })();            
         </script>
      <?php    
   }
}
