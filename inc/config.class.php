<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpConfig extends CommonDBTM {

   private static $instance;

   function showConfigForm() {

      global $DB, $CFG_GLPI;
      $upload_logo_url = Plugin::getWebDir('rp') . '/front/uplogo.php';
      $api_hl_enabled = Config::isHlApiEnabled();
      $api_legacy_enabled = !empty($CFG_GLPI['enable_api']);
      $api_glpi_enabled = $api_hl_enabled || $api_legacy_enabled;
      $api_rootdoc = rtrim((string)($CFG_GLPI['root_doc'] ?? '/glpi'), '/');
      $api_base_url = rtrim((string)($CFG_GLPI['url_base'] ?? ''), '/');
      if ($api_base_url === '') {
         $api_base_url = $api_rootdoc;
      }
      $api_prepare_endpoint = $api_rootdoc . '/plugins/rp/api/ticket_prepare.php';
      $api_generate_endpoint = $api_rootdoc . '/plugins/rp/api/ticket_generate.php';
      $api_sign_endpoint = $api_rootdoc . '/plugins/rp/api/ticket_sign.php';
      $api_token_endpoint = $api_rootdoc . '/api.php/v2.2/token';
      $api_legacy_init_session_endpoint = $api_rootdoc . '/api.php/v1/initSession';
      echo "<form name='form' method='post' action='" .
           Toolbox::getItemTypeFormURL('PluginRpConfig') . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('plugin_rp_csrf_token', ['value' => Session::getNewCSRFToken(true)]);

      $openCard = static function (string $title, string $subtitle = ''): void {
         echo "<div class='card mb-3 rp-config-card'>";
         echo "<div class='card-header rp-config-card-header bg-blue-lt'><h3 class='card-title text-blue mb-0'>" . $title . "</h3></div>";
         echo "<div class='card-body'>";
         if ($subtitle !== '') {
            echo "<div class='text-muted mb-2'>" . $subtitle . "</div>";
         }
         echo "<div class='table-responsive'><table class='table table-sm align-middle mb-0'>";
      };
      $closeCard = static function (): void {
         echo "</table></div></div></div>";
      };

      $openCard(__('Configuration Mail', 'rp'));
         echo "<tr class='tab_bg_1'>";
         echo "<td> Gabarit : Modèle de notifications </td>";
         echo "<td>";

         //notificationtemplates_id
         Dropdown::show('NotificationTemplate', [
            'name' => 'gabarit',
            'value' => $this->fields["gabarit"],
            'display_emptychoice' => 1,
            'specific_tags' => [],
            'itemtype' => 'NotificationTemplate',
            'displaywith' => [],
            'emptylabel' => "-----",
            'used' => [],
            'toadd' => [],
            'entity_restrict' => 0,
         ]); 
         echo "</td></tr>";

         // balises prise en charge
            echo "<tr class='tab_bg_1'><td colspan='2'><button type='button' id='rp-tags-toggle' class='btn btn-link p-0 text-start text-decoration-none fw-bold'><span id='rp-tags-toggle-icon' class='me-1'>&uarr;</span>Balises prisent en charge :</button></td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##document.weblink##  </td><td> Document : Lien web (PDF) </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td>  ##ticket.id##  </td><td> ticket : ID </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.url##   </td><td> ticket : URL </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.creationdate##  </td><td> ticket : Date d'ouverture </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.closedate##  </td><td> ticket : Date de clôture </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##task.time##  </td><td> Tâche  : Durée des taches séléctioné pour le rapport </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.description##  </td><td> Ticket : Description </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.entity.address##   </td><td> Entité (Adresse) </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.entity##  </td><td> Entité (Nom complet) </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.category##  </td><td> ticket : Catégorie </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.time##  </td><td> ticket : Durée totale </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##ticket.title##  </td><td> ticket : Titre </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td colspan='2'><b>Nouvelles balises :</b></td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##rapport.type.titel##  </td><td> rapport : Type(Titre) </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td>  ##rapport.type##  </td><td> rapport : Type </td></tr>";
            echo "<tr class='tab_bg_1 rp-tags-row' style='display:none;'><td> ##rapport.date.creation##  </td><td> rapport : date de création du rapport </td></tr>";
            echo "<tr style='display:none;'><td colspan='2'><script>
               (function () {
                  var btn = document.getElementById('rp-tags-toggle');
                  var icon = document.getElementById('rp-tags-toggle-icon');
                  var rows = document.querySelectorAll('.rp-tags-row');
                  if (!btn || !icon || !rows.length) {
                     return;
                  }

                  var setExpanded = function (expanded) {
                     rows.forEach(function (row) {
                        row.style.display = expanded ? '' : 'none';
                     });
                     icon.innerHTML = expanded ? '&darr;' : '&uarr;';
                     btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                  };

                  setExpanded(false);
                  btn.addEventListener('click', function () {
                     setExpanded(btn.getAttribute('aria-expanded') !== 'true');
                  });
               })();
            </script></td></tr>";
         // balises prise en charge
      
         $closeCard();
         $openCard(__('Options', 'rp'));

         echo "<tr class='tab_bg_1'>";
         echo "<td> Token GitHub </td>";
         echo "<td>";
         echo Html::input('token', ['value' => $this->fields['token'], 'size' => 60, 'maxlength' => 80]); // bouton / token github
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Affichage du temps de trajet dans les rapports technicien', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("time", $this->fields["time"]); // bouton d'affchage du temps de trajet pour les rapports tech
         echo "</td></tr>";
         echo "<tr class='tab_bg_1 top'><td>" . __('Affichage du temps de trajet supérieur à 0 dans les rapports hotline', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("time_hotl", $this->fields["time_hotl"]); // bouton d'affchage du temps de trajet pour les rapports hotline
         echo "</td></tr>";
            echo "<tr class='tab_bg_1 center'><td colspan='2'><span style=\"font-weight:bold; color:red\">" . __("Attention : L'utilisation du temps de trajet nécessite le plugin « rt ».", 'rp') . "</span></td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Enregistrement de plusieurs rapports', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("multi_doc", $this->fields["multi_doc"]); // bouton de possiblité de créé plusieurs rapport
         echo "</td></tr>";
         
         if($this->fields["multi_doc"] == 1){ //si plusieurs rapports = oui alors on laisse la possibilité d'afficher un nombre de rapport voulu sur l'ecran des rapports dans le ticket / max = 20
            if($this->fields["multi_display"] == 0){
               $DB->doQuery("UPDATE glpi_plugin_rp_configs SET multi_display = 5 WHERE id = 1"); // update si plusieurs rapports = oui on mais affichage de rapport sur 5 par defaut
               $this->fields["multi_display"] = 5;
            }
               echo "<tr class='tab_bg_1 top'><td>" . __('Nombre(s) de rapport(s) affiché(s)', 'rp') . "</td>";
               echo "<td>";
               Dropdown::showNumber("multi_display", ['value' => $this->fields["multi_display"],
                                                      'min'   => 1,
                                                      'max'   => 20,
                                                      'step'  => 1]);
               echo "</td></tr>";
         }else{
            $DB->doQuery("UPDATE glpi_plugin_rp_configs SET multi_display = 0 WHERE id = 1");
         }
            echo "<tr class='tab_bg_1 center'><td colspan='2'><span style=\"font-weight:bold; color:red\">" . __("Attention : si vous interdisez l'enregistrement de plusieurs rapport, cela écrasera le dernier rapport généré pour le remplacer.", 'rp') . "</span></td></tr>";

      $closeCard();
      $openCard(__('Options de génération du PDF', 'rp'));

         echo "<tr class='tab_bg_1 top'><td>" . __("L'affichage des images pour les tâches sont cochés par défaut", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("ImgTasks", $this->fields["ImgTasks"]); // bouton d'affchage des tâches et suivis publics uniquement
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("L'affichage des images pour les suivis sont cochés par défaut", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("ImgSuivis", $this->fields["ImgSuivis"]); // bouton affichage de la séléction des tâches et suivis
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("Désactiver la date de création dans l'entête du PDF", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("date", $this->fields["date"]); // bouton d'affchage de la date dans le pdf
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Seul les tâches et suivis publics sont visible lors de la génération', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("use_publictask", $this->fields["use_publictask"]); // bouton d'affchage des tâches et suivis publics uniquement
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Permettre la séléction des tâches et suivis avant la génération', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("choice", $this->fields["choice"]); // bouton affichage de la séléction des tâches et suivis
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("Mettre à jour les tâches/suivis/description modifiées depuis le modal lors de la génération", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("update_task_on_generate", $this->fields["update_task_on_generate"] ?? 0);
         echo "</td></tr>";

         //taches 
         if ($this->fields["choice"] == 1){
            echo "<tr class='tab_bg_1 top'><td>" . __("Les tâches publics sont cochés par défaut", 'rp') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("check_public_task", $this->fields["check_public_task"]);
            echo "</td></tr>";
      
            if ($this->fields["use_publictask"] == 0){
               echo "<tr class='tab_bg_1 top'><td>" . __("Les tâches privés sont cochés par défaut", 'rp') . "</td>";
               echo "<td>";
               Dropdown::showYesNo("check_private_task", $this->fields["check_private_task"]);
               echo "</td></tr>";
               
            }else{
               $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_private_task = 0 WHERE id = 1"); // update si tâches et suivis publics visible = non
            }
         }else{
            $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_public_task = 0, check_private = 0 WHERE id = 1"); // update si Permettre la séléction des tâches et suivis = non
         }

         //suivis
         if ($this->fields["choice"] == 1){
            echo "<tr class='tab_bg_1 top'><td>" . __("Les suivis publics sont cochés par défaut", 'rp') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("check_public_suivi", $this->fields["check_public_suivi"]);
            echo "</td></tr>";
      
            if ($this->fields["use_publictask"] == 0){
               echo "<tr class='tab_bg_1 top'><td>" . __("Les suivis privés sont cochés par défaut", 'rp') . "</td>";
               echo "<td>";
               Dropdown::showYesNo("check_private_suivi", $this->fields["check_private_suivi"]);
               echo "</td></tr>";
               
            }else{
               $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_private_suivi = 0 WHERE id = 1"); // update si tâches et suivis publics visible = non
            }
         }else{
            $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_public_suivi = 0, check_private = 0 WHERE id = 1"); // update si Permettre la séléction des tâches et suivis = non
         }

      $closeCard();
      $openCard(__('Options de génération du PDF Massives Actions', 'rp'));

         echo "<tr class='tab_bg_1 top'><td>" . __('Seul les tâches et suivis publics sont visible lors de la génération des PDF avec massives actions', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("use_publictask_massaction", $this->fields["use_publictask_massaction"]); // bouton d'affchage des tâches et suivis publics uniquement
         echo "</td></tr>";

      $closeCard();
      $openCard(__('Options de signature', 'rp'));

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur la prise en charge', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_charge", $this->fields["sign_rp_charge"]); // bouton fonctionnalité affichage ou non de la signature prise en charge
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur le rapport technicien', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_tech", $this->fields["sign_rp_tech"]); // bouton fonctionnalité affichage ou non de la signature tech
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur le rapport hotline', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_hotl", $this->fields["sign_rp_hotl"]); // bouton fonctionnalité affichage ou non de la signature hotline
         echo "</td></tr>";

         if (array_key_exists('sign_rp_prep', $this->fields)) {
            echo "<tr class='tab_bg_1 top'><td>" . __('Signature du technicien sur le rapport de préparation', 'rp') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("sign_rp_prep", $this->fields["sign_rp_prep"]); // signature technicien atelier sur le rapport de préparation
            echo "</td></tr>";
         }

      $closeCard();
      $openCard(
         __("Accès individuels par utilisateur", 'rp'),
         __("Par défaut, l'accès suit les droits du profil GLPI. Le mode « Autoriser » donne accès aux utilisateurs sélectionnés même sans le droit de profil ; le mode « Refuser » leur retire l'accès même avec le droit de profil. Liste vide = droits du profil uniquement.", 'rp')
      );

         echo Html::hidden('rp_access_save', ['value' => 1]);
         $rp_access_modes = [
            PluginRpAccess::MODE_PROFILE => __('Droits du profil GLPI (par défaut)', 'rp'),
            PluginRpAccess::MODE_ALLOW   => __('Autoriser les utilisateurs sélectionnés', 'rp'),
            PluginRpAccess::MODE_DENY    => __('Refuser les utilisateurs sélectionnés', 'rp'),
         ];
         echo "<tr class='tab_bg_1'>";
         echo "<th>" . __('Fonctionnalité', 'rp') . "</th>";
         echo "<th style='width:320px'>" . __('Mode', 'rp') . "</th>";
         echo "<th style='min-width:300px'>" . __('Utilisateurs concernés', 'rp') . "</th>";
         echo "</tr>";
         foreach (PluginRpAccess::getFeatures() as $rp_feature => $rp_feature_data) {
            $rp_rule = PluginRpAccess::getRule($rp_feature) ?? ['mode' => PluginRpAccess::MODE_PROFILE, 'users' => []];
            echo "<tr class='tab_bg_1 top'>";
            echo "<td>" . $rp_feature_data['label'] . "</td>";
            echo "<td>";
            Dropdown::showFromArray('rp_access_mode_' . $rp_feature, $rp_access_modes, [
               'value' => $rp_rule['mode'],
               'width' => '100%',
            ]);
            echo "</td>";
            echo "<td>";
            Dropdown::show('User', [
               'name'     => 'rp_access_users_' . $rp_feature . '[]',
               'multiple' => true,
               'value'    => $rp_rule['users'],
               'width'    => '100%',
            ]);
            echo "</td>";
            echo "</tr>";
         }

      $closeCard();
      $openCard(__("Options d'envoi par mail", 'rp'));

         echo "<tr class='tab_bg_1 top'><td>" . __("Possiblité d'envoyer par email le PDF", 'rp') . "</td>"; // bouton fonctionnalité envoie mail
         echo "<td>";
         Dropdown::showYesNo("email", $this->fields["email"]);
         echo "</td></tr>";

      $closeCard();
      $openCard(__("Titre des rapports", 'rp'));

         // Générer les options du menu déroulant
         $positioning = [];
         $positioning[0] = "Droite";
         $positioning[1] = "Centre";
         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __("Positionnement du titre", "rp") . "</td><td>";
               // Afficher le menu déroulant avec Dropdown::show()
               Dropdown::showFromArray(
                  'potitle',  // Nom de l'identifiant du champ
                  $positioning,    // Tableau des options
                  [
                     'value'      => $this->fields["potitle"],        // Valeur sélectionnée par défaut (optionnel)
                  ]
               );
            echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Prise en charge </td>";
         echo "<td>";
         echo Html::input('titel_pc', ['value' => $this->fields['titel_pc'], 'size' => 40, 'maxlength' => 25]); // bouton / titre de la prise en charge 
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Rapport technicien </td>";
         echo "<td>";
         echo Html::input('titel_rt', ['value' => $this->fields['titel_rt'], 'size' => 40, 'maxlength' => 25]); // bouton / titre du rapport technicien
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Rapport hotline </td>";
         echo "<td>";
         echo Html::input('titel_rh', ['value' => $this->fields['titel_rh'], 'size' => 40, 'maxlength' => 25]); // bouton / titre du rapport hotline
         echo "</td>";
         echo "</tr>";

         if (array_key_exists('titel_prep', $this->fields)) {
            echo "<tr class='tab_bg_1'>";
            echo "<td> Rapport de préparation </td>";
            echo "<td>";
            echo Html::input('titel_prep', ['value' => $this->fields['titel_prep'], 'size' => 40, 'maxlength' => 25]); // titre du rapport de préparation
            echo "</td>";
            echo "</tr>";
         }

   // Logo config taille et bas de de page ------------------------------------------------------
   $allowed_entities = [];
   $query = "SELECT id FROM glpi_entities WHERE entities_id = 0";
   $result = $DB->doQuery($query);

   if ($result) {
      while ($data = $DB->fetchassoc($result)) {
         $allowed_entities[$data['id']] = Dropdown::getDropdownName("glpi_entities", $data['id']);
      }
   }


      $closeCard();
      $openCard(
         __("Configuration du bas de page - Logo 1", 'rp'),
         __("Laisser le champ 'Entité parente' vide pour désactiver.", 'rp')
      );
         echo "<tr class='tab_bg_1'>";
         echo "<td> 1er ligne du bas de page </td>";
         echo "<td>";
         echo Html::input('line1', ['value' => $this->fields['line1'], 'size' => 60, 'maxlength' => 80]);// bouton configuration du bas de page line 1
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> 2ème ligne du bas de page </td>";
         echo "<td>";
         echo Html::input('line2', ['value' => $this->fields['line2'], 'size' => 60, 'maxlength' => 80]); // bouton configuration du bas de page line 2
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Entité parente') . "</td>";
            echo "<td>";
            Dropdown::showFromArray('entity_parrent1', $allowed_entities, [
               'value'               => $this->fields["entity_parrent1"],
               'display_emptychoice' => true,
               'emptylabel'          => "-----"
            ]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __("Couleur du PDF", "rp") . "</td><td>";
               echo '<input type="color" name="color1" value="'.$this->fields['color1'].'">';
            echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __("Couleur des titres du PDF", "rp") . "</td><td>";
               echo '<input type="color" name="color_text1" value="'.$this->fields['color_text1'].'">';
            echo "</td>";
         echo "</tr>";

      $closeCard();
      $openCard(
         __("Configuration du bas de page - Logo 2", 'rp'),
         __("Laisser le champ 'Entité parente' vide pour désactiver.", 'rp')
      );
         echo "<tr class='tab_bg_1'>";
         echo "<td> 1er ligne du bas de page </td>";
         echo "<td>";
         echo Html::input('line3', ['value' => $this->fields['line3'], 'size' => 60, 'maxlength' => 80]);// bouton configuration du bas de page line 1
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> 2ème ligne du bas de page </td>";
         echo "<td>";
         echo Html::input('line4', ['value' => $this->fields['line4'], 'size' => 60, 'maxlength' => 80]); // bouton configuration du bas de page line 2
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Entité parente') . "</td>";
            echo "<td>";
            Dropdown::showFromArray('entity_parrent2', $allowed_entities, [
               'value'               => $this->fields["entity_parrent2"],
               'display_emptychoice' => true,
               'emptylabel'          => "-----"
            ]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __("Couleur du PDF", "rp") . "</td><td>";
               echo '<input type="color" name="color2" value="'.$this->fields['color2'].'">';
            echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
            echo "<td>" . __("Couleur des titres du PDF", "rp") . "</td><td>";
               echo '<input type="color" name="color_text2" value="'.$this->fields['color_text2'].'">';
            echo "</td>";
         echo "</tr>";

      ?><script>
         document.addEventListener('DOMContentLoaded', function () {
            const select1 = document.querySelector('select[name="entity_parrent1"]');
            const select2 = document.querySelector('select[name="entity_parrent2"]');

            function updateOptions() {
               const val1 = select1.value;
               const val2 = select2.value;

               // Réactive toutes les options
               for (let opt of select1.options) opt.disabled = false;
               for (let opt of select2.options) opt.disabled = false;

               // Désactive l'option sélectionnée dans l'autre menu
               if (val2) {
                  const opt1 = select1.querySelector(`option[value="${val2}"]`);
                  if (opt1) opt1.disabled = true;
               }
               if (val1) {
                  const opt2 = select2.querySelector(`option[value="${val1}"]`);
                  if (opt2) opt2.disabled = true;
               }
            }

            select1.addEventListener('change', updateOptions);
            select2.addEventListener('change', updateOptions);

            updateOptions(); // Initialisation
         });
      </script><?php

      $closeCard();
      $openCard(__("Positionnement logo", 'rp'));
         echo "<tr class='tab_bg_1 top'><td>" . __('Marge à gauche du logo', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showNumber("margin_left", ['value' => $this->fields["margin_left"], // bouton configuration de la marge a gauche
                                                'min'   => 1,
                                                'max'   => 60,
                                                'step'  => 1]);
         echo " dpi </td></tr>";
         echo "<tr class='tab_bg_1 top'><td>" . __('Marge au dessus du logo', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showNumber("margin_top", ['value' => $this->fields["margin_top"], // bouton configuration de la marge au dessus
                                                'min'   => 1,
                                                'max'   => 60,
                                                'step'  => 1]);
         echo " dpi </td></tr>";
         echo "<tr class='tab_bg_1 top'><td>" . __('Taille du logo', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showNumber("cut", ['value' => $this->fields["cut"], // bouton configuration de la taille du logo
                                                'min'   => 1,
                                                'max'   => 60,
                                                'step'  => 1]);
         echo " dpi </td></tr>";

      $closeCard();

      if ($api_glpi_enabled) {
         $openCard(__('API application tierce', 'rp'));
         echo "<tr class='tab_bg_1 top'><td colspan='2'>";
         echo "<div class='d-flex flex-wrap align-items-center gap-2'>";
         if ($api_hl_enabled) {
            echo "<span class='badge bg-success'>" . __('API v2 active', 'rp') . "</span>";
         }
         if ($api_legacy_enabled) {
            echo "<span class='badge bg-warning text-dark'>" . __('API legacy active', 'rp') . "</span>";
         }
         echo "<a class='btn btn-outline-primary btn-sm' target='_blank' rel='noopener' href='" . Html::entities_deep($api_rootdoc . "/plugins/rp/front/api_docs.php") . "'>" . __('Voir doc API', 'rp') . "</a>";
         echo "</div>";
         echo "<div class='form-text text-muted mt-2'>" . __('Compatible OAuth v2 (Bearer) et legacy (App-Token + user_token/session_token). En v2.2, utiliser un token utilisateur. L API RP sert a preparer/generer les PDF; la lecture detaillee du ticket se fait via l API GLPI.', 'rp') . "</div>";
         echo "</td></tr>";
         $closeCard();
      }
   // Logo config taille et bas de de page ------------------------------------------------------

      echo Html::hidden('id', ['value' => 1]); // revoie l'id 1 dans la methode post (id = 1 car la bdd config comptient que 1 seul ligne)
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";
      echo "<td class='right'>";
      echo "<input type='submit' class='submit' name='update' value=\"" . __('Save') . "\">";
      echo "</td>";
      echo "</tr>";
      echo "</table>";

      Html::closeForm();

      if ($api_glpi_enabled): ?>
      <div class="modal fade" id="rpApiDocModal" tabindex="-1" aria-labelledby="rpApiDocModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-lg">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="rpApiDocModalLabel"><?php echo __('Documentation API du plugin RP', 'rp'); ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('Fermer', 'rp'); ?>"></button>
               </div>
               <div class="modal-body">
                  <p class="mb-2"><strong><?php echo __('Pré-requis', 'rp'); ?></strong></p>
                  <ul class="mb-3">
                     <li><?php echo __('Activer au moins une API GLPI : v2 (High-Level) ou legacy.', 'rp'); ?></li>
                     <li><?php echo __('Ces endpoints RP acceptent les deux méthodes d authentification.', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Méthode 1 : OAuth v2 (recommandée)', 'rp'); ?></strong></p>
                  <pre class="bg-light p-2 rounded"><code>Authorization: Bearer &lt;access_token_oauth&gt;</code></pre>
                  <ul class="mb-3">
                     <li><?php echo __('Le token OAuth doit etre lie a un utilisateur.', 'rp'); ?></li>
                     <li><?php echo __('En v2.2, utiliser grant_type=password (login + mot de passe GLPI) ou authorization_code.', 'rp'); ?></li>
                     <li><?php echo __('client_credentials seul renverra user_context_required.', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Endpoint OAuth token (GLPI v2.2)', 'rp'); ?></strong></p>
                  <pre class="bg-light p-2 rounded"><code>POST <?php echo htmlspecialchars($api_token_endpoint, ENT_QUOTES, 'UTF-8'); ?></code></pre>

                  <p class="mb-2 mt-3"><strong><?php echo __('Méthode 2 : Legacy v1 (jeton utilisateur)', 'rp'); ?></strong></p>
                  <pre class="bg-light p-2 rounded"><code>App-Token: &lt;app_token_glpi&gt;
Authorization: user_token &lt;user_token_preferences&gt;</code></pre>
                  <p class="mb-2"><strong><?php echo __('Alternative legacy (session)', 'rp'); ?></strong></p>
                  <pre class="bg-light p-2 rounded"><code>POST <?php echo htmlspecialchars($api_legacy_init_session_endpoint, ENT_QUOTES, 'UTF-8'); ?></code></pre>

                  <p class="mb-2"><strong><?php echo __('Rôle des APIs', 'rp'); ?></strong></p>
                  <ul class="mb-3">
                     <li><?php echo __('API GLPI core: lecture complete des tickets (titre, description, etc.).', 'rp'); ?></li>
                     <li><?php echo __('API RP: preparation et generation des PDF (rapport intervention/hotline, fiche prise en charge).', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Endpoints RP', 'rp'); ?></strong></p>
                  <div class="mb-2">
                     <div><code>GET <?php echo htmlspecialchars($api_prepare_endpoint, ENT_QUOTES, 'UTF-8'); ?>?ticket_id=123&document_type=intervention_report</code></div>
                     <small class="text-muted"><?php echo __('Vérifie le ticket, le contexte et les contraintes de génération. Renvoie aussi ticket_name et ticket_description.', 'rp'); ?></small>
                  </div>
                  <div class="mb-3">
                     <div><code>POST <?php echo htmlspecialchars($api_generate_endpoint, ENT_QUOTES, 'UTF-8'); ?></code></div>
                     <small class="text-muted"><?php echo __('Génère un PDF RP (rapport / fiche) depuis un ticket.', 'rp'); ?></small>
                  </div>
                  <div class="mb-3">
                     <div><code>POST <?php echo htmlspecialchars($api_sign_endpoint, ENT_QUOTES, 'UTF-8'); ?></code></div>
                     <small class="text-muted"><?php echo __('Endpoint principal recommande pour la signature rapport. Controle si un BL associe existe et peut lancer BL + rapport selon le mode.', 'rp'); ?></small>
                  </div>

                  <p class="mb-2"><strong><?php echo __('Champs de génération', 'rp'); ?></strong></p>
                  <ul class="mb-3">
                     <li><code>mode</code> : <?php echo __('optionnel (auto|report|both). Defaut: auto.', 'rp'); ?></li>
                     <li><code>ticket_id</code> : <?php echo __('obligatoire.', 'rp'); ?></li>
                     <li><code>document_type</code> : <?php echo __('obligatoire (intervention_report, hotline_report, charge_sheet).', 'rp'); ?></li>
                     <li><code>task_ids</code> : <?php echo __('optionnel, mais au moins une tâche est obligatoire pour intervention_report/hotline_report.', 'rp'); ?></li>
                     <li><code>signer_name</code>, <code>signer_email</code>, <code>mail_to_client</code>, <code>signature</code> : <?php echo __('optionnels.', 'rp'); ?></li>
                     <li><code>followup_ids</code>, <code>description</code>, <code>entity_group</code> : <?php echo __('optionnels.', 'rp'); ?></li>
                     <li><code>include_followups</code>, <code>show_total_time</code>, <code>include_task_images</code>, <code>include_followup_images</code> : <?php echo __('optionnels.', 'rp'); ?></li>
                     <li><code>bl</code> ou <code>survey_id</code> : <?php echo __('optionnels. Utiles en mode both/auto pour signer aussi le BL associe.', 'rp'); ?></li>
                     <li><code>comment</code>, <code>counter_invoice_client</code> : <?php echo __('optionnels. Transmis au flux BL si celui-ci est lance.', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Comportement selon la configuration RP', 'rp'); ?></strong></p>
                  <ul class="mb-3">
                     <li><code>use_publictask=1</code> : <?php echo __('les tâches/suivis privés sont exclus.', 'rp'); ?></li>
                     <li><code>choice=1</code> : <?php echo __('la sélection par défaut suit check_public_task/check_private_task/check_public_suivi/check_private_suivi.', 'rp'); ?></li>
                     <li><?php echo __('Si task_ids/followup_ids sont fournis, ils priorisent la sélection par défaut.', 'rp'); ?></li>
                     <li><?php echo __('Si description n est pas fournie, la description réelle du ticket est utilisée automatiquement.', 'rp'); ?></li>
                     <li><?php echo __('Si include_task_images/include_followup_images ne sont pas fournis, les valeurs ImgTasks/ImgSuivis de la config sont utilisées.', 'rp'); ?></li>
                     <li><?php echo __('En mode auto (ticket_sign), si un BL associe existe et que le plugin Gestion est actif, le flux tente BL + rapport.', 'rp'); ?></li>
                     <li><?php echo __('En mode both, ticket et BL doivent etre associes.', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Ordre des flux conseillés', 'rp'); ?></strong></p>
                  <ul class="mb-3">
                     <li><?php echo __('Cas principal: partir de ticket_sign (plugin RP) pour signer/generer le rapport, puis ajouter la signature BL si association ticket-BL trouvee.', 'rp'); ?></li>
                     <li><?php echo __('Cas inverse (plus rare): partir d un BL via l endpoint Gestion combine_sign en mode auto/both pour lancer aussi la generation rapport si le ticket associe est trouve.', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Erreurs utiles de génération', 'rp'); ?></strong></p>
                  <ul class="mb-3">
                     <li><code>tasks_required</code> : <?php echo __('aucune tâche sélectionnée pour un rapport qui en exige.', 'rp'); ?></li>
                     <li><code>generate_call_failed</code> : <?php echo __('échec du traitement interne RP. Les champs generate_http et generate_response aident au diagnostic.', 'rp'); ?></li>
                  </ul>

                  <p class="mb-2"><strong><?php echo __('Exemple JSON', 'rp'); ?></strong></p>
                  <pre class="bg-light p-2 rounded mb-0"><code>{
  "mode": "auto",
  "ticket_id": 123,
  "document_type": "intervention_report",
  "signer_name": "Client Nom",
  "signer_email": "",
  "mail_to_client": 0,
  "signature": "data:image/png;base64,...",
  "bl": "BL202852",
  "include_followups": 1,
  "show_total_time": 1,
  "include_task_images": 0,
  "include_followup_images": 0
}</code></pre>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Fermer', 'rp'); ?></button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" id="rpApiTesterModal" tabindex="-1" aria-labelledby="rpApiTesterModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-xl">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="rpApiTesterModalLabel"><?php echo __('Test API / Authentification RP', 'rp'); ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('Fermer', 'rp'); ?>"></button>
               </div>
               <div class="modal-body">
                  <div class="row g-3">
                     <div class="col-md-4">
                        <label for="rpApiTestMode" class="form-label"><?php echo __('Mode de test', 'rp'); ?></label>
                        <select id="rpApiTestMode" class="form-select">
                           <option value="v2_password"><?php echo __('OAuth v2.2 (grant password)', 'rp'); ?></option>
                           <option value="v1_user_token"><?php echo __('Legacy v1 (App-Token + user_token)', 'rp'); ?></option>
                        </select>
                     </div>
                     <div class="col-md-8">
                        <label for="rpApiTestBaseUrl" class="form-label"><?php echo __('Base URL GLPI', 'rp'); ?></label>
                        <input id="rpApiTestBaseUrl"
                               class="form-control"
                               value="<?php echo htmlspecialchars($api_base_url, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="https://example.tld/glpi">
                     </div>
                     <div class="col-md-4">
                        <label for="rpApiTestTicketId" class="form-label"><?php echo __('Ticket ID de test', 'rp'); ?></label>
                        <input id="rpApiTestTicketId" class="form-control" value="1" placeholder="123">
                     </div>
                     <div class="col-md-4">
                        <label for="rpApiTestDocType" class="form-label"><?php echo __('Type de document', 'rp'); ?></label>
                        <select id="rpApiTestDocType" class="form-select">
                           <option value="intervention_report">intervention_report</option>
                           <option value="hotline_report">hotline_report</option>
                           <option value="charge_sheet">charge_sheet</option>
                        </select>
                     </div>
                  </div>

                  <div id="rpApiTestV2Fields" class="row g-3 mt-1">
                     <div class="col-md-6">
                        <label for="rpApiClientId" class="form-label"><?php echo __('Client ID OAuth', 'rp'); ?></label>
                        <input id="rpApiClientId" class="form-control" placeholder="client_id">
                     </div>
                     <div class="col-md-6">
                        <label for="rpApiClientSecret" class="form-label"><?php echo __('Client secret OAuth', 'rp'); ?></label>
                        <input id="rpApiClientSecret" type="password" class="form-control" placeholder="client_secret">
                     </div>
                     <div class="col-md-6">
                        <label for="rpApiUsername" class="form-label"><?php echo __('Login GLPI', 'rp'); ?></label>
                        <input id="rpApiUsername" class="form-control" placeholder="login">
                     </div>
                     <div class="col-md-6">
                        <label for="rpApiPassword" class="form-label"><?php echo __('Mot de passe GLPI', 'rp'); ?></label>
                        <input id="rpApiPassword" type="password" class="form-control" placeholder="mot de passe">
                     </div>
                  </div>

                  <div id="rpApiTestV1Fields" class="row g-3 mt-1" style="display:none;">
                     <div class="col-md-6">
                        <label for="rpApiAppToken" class="form-label"><?php echo __('App-Token', 'rp'); ?></label>
                        <input id="rpApiAppToken" class="form-control" placeholder="app_token">
                     </div>
                     <div class="col-md-6">
                        <label for="rpApiUserToken" class="form-label"><?php echo __('User token (préférences GLPI)', 'rp'); ?></label>
                        <input id="rpApiUserToken" class="form-control" placeholder="user_token">
                     </div>
                  </div>

                  <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                     <button type="button" class="btn btn-primary" id="rpApiRunTestBtn"><?php echo __('Tester l API', 'rp'); ?></button>
                     <button type="button" class="btn btn-outline-secondary" id="rpApiBuildPsBtn"><?php echo __('Générer script PowerShell', 'rp'); ?></button>
                     <button type="button" class="btn btn-outline-secondary" id="rpApiCopyPsBtn"><?php echo __('Copier le script', 'rp'); ?></button>
                     <button type="button" class="btn btn-outline-secondary" id="rpApiPopupPsBtn"><?php echo __('Ouvrir dans une fenêtre', 'rp'); ?></button>
                  </div>

                  <div class="mt-3">
                     <label for="rpApiPsScript" class="form-label"><?php echo __('Script PowerShell généré', 'rp'); ?></label>
                     <textarea id="rpApiPsScript" class="form-control font-monospace" rows="12"></textarea>
                  </div>

                  <div class="mt-3">
                     <label for="rpApiTestResult" class="form-label"><?php echo __('Résultat du test HTTP', 'rp'); ?></label>
                     <pre id="rpApiTestResult" class="bg-light p-2 rounded small mb-0"></pre>
                  </div>
               </div>
               <div class="modal-footer">
                  <a class="btn btn-secondary" target="_blank" rel="noopener" href="<?php echo Html::entities_deep($api_rootdoc . '/plugins/rp/front/api_docs.php'); ?>"><?php echo __('Voir doc API', 'rp'); ?></a>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Fermer', 'rp'); ?></button>
               </div>
            </div>
         </div>
      </div>

      <script>
      (function () {
         if (window.rpApiTesterInit) {
            return;
         }
         window.rpApiTesterInit = true;

         const get = (id) => document.getElementById(id);
         const modeEl = get('rpApiTestMode');
         const resultEl = get('rpApiTestResult');
         const scriptEl = get('rpApiPsScript');

         const fields = {
            baseUrl: get('rpApiTestBaseUrl'),
            ticketId: get('rpApiTestTicketId'),
            docType: get('rpApiTestDocType'),
            clientId: get('rpApiClientId'),
            clientSecret: get('rpApiClientSecret'),
            username: get('rpApiUsername'),
            password: get('rpApiPassword'),
            appToken: get('rpApiAppToken'),
            userToken: get('rpApiUserToken'),
            v2Box: get('rpApiTestV2Fields'),
            v1Box: get('rpApiTestV1Fields')
         };

         const escPs = (value) => String(value ?? '').replace(/'/g, "''");
         const normalizeBase = (txt) => String(txt ?? '').trim().replace(/\/+$/, '');
         const absolutizeBase = (txt) => {
            const base = normalizeBase(txt);
            if (base.startsWith('/')) {
               return window.location.origin + base;
            }
            return base;
         };
         const setResult = (txt) => {
            resultEl.textContent = String(txt ?? '');
         };
         const setMode = () => {
            const v2 = modeEl.value === 'v2_password';
            fields.v2Box.style.display = v2 ? '' : 'none';
            fields.v1Box.style.display = v2 ? 'none' : '';
         };
         const readBody = async (res) => {
            const txt = await res.text();
            try {
               return JSON.stringify(JSON.parse(txt), null, 2);
            } catch (e) {
               return txt;
            }
         };
         const clip = async (text) => {
            if (navigator.clipboard && window.isSecureContext) {
               await navigator.clipboard.writeText(text);
               return;
            }
            scriptEl.focus();
            scriptEl.select();
            document.execCommand('copy');
         };

         const buildScript = () => {
            const mode = modeEl.value;
            const base = absolutizeBase(fields.baseUrl.value);
            const ticketId = fields.ticketId.value.trim();
            const docType = fields.docType.value;

            if (mode === 'v2_password') {
               return [
                  "$BaseUrl = '" + escPs(base) + "'",
                  "$TicketId = '" + escPs(ticketId) + "'",
                  "$DocType = '" + escPs(docType) + "'",
                  "$ClientId = '" + escPs(fields.clientId.value) + "'",
                  "$ClientSecret = '" + escPs(fields.clientSecret.value) + "'",
                  "$Username = '" + escPs(fields.username.value) + "'",
                  "$Password = '" + escPs(fields.password.value) + "'",
                  "",
                  "$token = Invoke-RestMethod -Method POST -Uri \"$BaseUrl/api.php/v2.2/token\" -ContentType \"application/x-www-form-urlencoded\" -Body @{",
                  "    grant_type    = 'password'",
                  "    client_id     = $ClientId",
                  "    client_secret = $ClientSecret",
                  "    username      = $Username",
                  "    password      = $Password",
                  "    scope         = 'api user'",
                  "}",
                  "",
                  "$headers = @{ Authorization = \"Bearer $($token.access_token)\"; Accept = 'application/json' }",
                  "$prepare = Invoke-WebRequest -Method GET -Uri \"$BaseUrl/plugins/rp/api/ticket_prepare.php?ticket_id=$TicketId&document_type=$DocType\" -Headers $headers -TimeoutSec 180",
                  "$prepare.Content",
                  "",
                  "$genHeaders = @{ Authorization = \"Bearer $($token.access_token)\"; Accept = 'application/json'; 'Content-Type' = 'application/json' }",
                  "$payload = @{",
                  "    mode = 'auto'",
                  "    ticket_id = [int]$TicketId",
                  "    document_type = $DocType",
                  "    signer_name = $Username",
                  "    signer_email = ''",
                  "    signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='",
                  "    mail_to_client = 0",
                  "    include_followups = 1",
                  "    show_total_time = 1",
                  "    include_task_images = 0",
                  "    include_followup_images = 0",
                  "} | ConvertTo-Json -Depth 10",
                  "Invoke-WebRequest -Method POST -Uri \"$BaseUrl/plugins/rp/api/ticket_sign.php\" -Headers $genHeaders -Body $payload -ContentType 'application/json' -TimeoutSec 180"
               ].join("\n");
            }

            return [
               "$BaseUrl = '" + escPs(base) + "'",
               "$TicketId = '" + escPs(ticketId) + "'",
               "$DocType = '" + escPs(docType) + "'",
               "$AppToken = '" + escPs(fields.appToken.value) + "'",
               "$UserToken = '" + escPs(fields.userToken.value) + "'",
               "",
               "$headers = @{",
               "    'App-Token'     = $AppToken",
               "    'Authorization' = \"user_token $UserToken\"",
               "    'Accept'        = 'application/json'",
               "}",
               "$prepare = Invoke-WebRequest -Method GET -Uri \"$BaseUrl/plugins/rp/api/ticket_prepare.php?ticket_id=$TicketId&document_type=$DocType\" -Headers $headers -TimeoutSec 180",
               "$prepare.Content",
               "",
               "$genHeaders = @{",
               "    'App-Token'     = $AppToken",
               "    'Authorization' = \"user_token $UserToken\"",
               "    'Accept'        = 'application/json'",
               "    'Content-Type'  = 'application/json'",
               "}",
               "$payload = @{",
               "    mode = 'auto'",
               "    ticket_id = [int]$TicketId",
               "    document_type = $DocType",
               "    signer_name = 'API Legacy'",
               "    signer_email = ''",
               "    signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='",
               "    mail_to_client = 0",
               "    include_followups = 1",
               "    show_total_time = 1",
               "    include_task_images = 0",
               "    include_followup_images = 0",
               "} | ConvertTo-Json -Depth 10",
               "Invoke-WebRequest -Method POST -Uri \"$BaseUrl/plugins/rp/api/ticket_sign.php\" -Headers $genHeaders -Body $payload -ContentType 'application/json' -TimeoutSec 180"
            ].join("\n");
         };

         const runTest = async () => {
            const base = normalizeBase(fields.baseUrl.value);
            const ticketId = fields.ticketId.value.trim();
            const docType = fields.docType.value;
            if (!base || !ticketId) {
               setResult("Base URL et ticket_id sont obligatoires.");
               return;
            }

            setResult("Test en cours...");
            try {
               if (modeEl.value === 'v2_password') {
                  const tokenForm = new URLSearchParams();
                  tokenForm.set('grant_type', 'password');
                  tokenForm.set('client_id', fields.clientId.value.trim());
                  tokenForm.set('client_secret', fields.clientSecret.value);
                  tokenForm.set('username', fields.username.value.trim());
                  tokenForm.set('password', fields.password.value);
                  tokenForm.set('scope', 'api user');

                  const tokenRes = await fetch(base + '/api.php/v2.2/token', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                     body: tokenForm
                  });
                  const tokenRaw = await tokenRes.text();
                  let tokenBody = {};
                  try {
                     tokenBody = JSON.parse(tokenRaw);
                  } catch (e) {
                     tokenBody = {};
                  }
                  if (!tokenRes.ok || !tokenBody.access_token) {
                     setResult("OAuth token KO (HTTP " + tokenRes.status + ")\n" + tokenRaw);
                     return;
                  }

                  const prepareRes = await fetch(base + '/plugins/rp/api/ticket_prepare.php?ticket_id=' + encodeURIComponent(ticketId) + '&document_type=' + encodeURIComponent(docType), {
                     headers: { 'Authorization': 'Bearer ' + tokenBody.access_token, 'Accept': 'application/json' }
                  });
                  setResult("Token OK (HTTP " + tokenRes.status + ")\n\nPrepare HTTP " + prepareRes.status + "\n" + (await readBody(prepareRes)));
                  return;
               }

               const prepareRes = await fetch(base + '/plugins/rp/api/ticket_prepare.php?ticket_id=' + encodeURIComponent(ticketId) + '&document_type=' + encodeURIComponent(docType), {
                  headers: {
                     'App-Token': fields.appToken.value.trim(),
                     'Authorization': 'user_token ' + fields.userToken.value.trim(),
                     'Accept': 'application/json'
                  }
               });
               setResult("Prepare HTTP " + prepareRes.status + "\n" + (await readBody(prepareRes)));
            } catch (e) {
               setResult("Erreur JS: " + e.message);
            }
         };

         get('rpApiRunTestBtn').addEventListener('click', runTest);
         get('rpApiBuildPsBtn').addEventListener('click', function () {
            scriptEl.value = buildScript();
         });
         get('rpApiCopyPsBtn').addEventListener('click', async function () {
            scriptEl.value = buildScript();
            await clip(scriptEl.value);
            setResult("Script copié dans le presse-papiers.");
         });
         get('rpApiPopupPsBtn').addEventListener('click', function () {
            scriptEl.value = buildScript();
            const popup = window.open('', '_blank', 'width=980,height=760');
            if (!popup) {
               setResult("Popup bloquée par le navigateur.");
               return;
            }
            const escaped = scriptEl.value
               .replace(/&/g, '&amp;')
               .replace(/</g, '&lt;')
               .replace(/>/g, '&gt;');
            popup.document.write('<!doctype html><html><head><meta charset=\"utf-8\"><title>Script PowerShell API RP</title></head><body style=\"font-family:monospace;padding:12px;\"><h3>Script PowerShell</h3><pre style=\"white-space:pre-wrap;word-break:break-word;\">' + escaped + '</pre></body></html>');
            popup.document.close();
         });

         modeEl.addEventListener('change', function () {
            setMode();
            scriptEl.value = buildScript();
         });

         setMode();
         scriptEl.value = buildScript();
      })();
      </script>
      <?php endif;

   // Logo index
      $document_send_base = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/front/document.send.php?docid=';
      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
         echo "<tr><th colspan='3'>" . __("LOGO 1", 'rp') . "</th></tr>";
         echo "<tr class='tab_bg_1'>";
            echo "<td width='35%'>";

            $doc = new Document();
            $img = $doc->find(['id' => $this->fields['logo_id']]); // explore et recupére les values bdd comptenu dans document a la ligne id = logo_id enregistré en base config 
            $img = reset($img); // remet le curseur au debut du tableau ci dessus
            $has_logo = false;
            if (is_array($img) && !empty($img['filepath'])) { // verification que la varible soit non vide
               $raw_filepath = (string)$img['filepath'];
               $normalized_filepath = stripslashes($raw_filepath);
               $raw_fullpath = GLPI_DOC_DIR . '/' . $raw_filepath;
               $normalized_fullpath = GLPI_DOC_DIR . '/' . $normalized_filepath;

               // Auto-repair old escaped filepath values (eg: d\'ecran.png)
               if (!file_exists($raw_fullpath) && file_exists($normalized_fullpath) && !empty($img['id'])) {
                  $DB->update('glpi_documents', ['filepath' => $normalized_filepath], ['id' => (int)$img['id']]);
                  $raw_fullpath = $normalized_fullpath;
               }

               $has_logo = file_exists($raw_fullpath) || file_exists($normalized_fullpath);
            }

            if ($has_logo) {
               $fichier = $document_send_base . (int)$this->fields["logo_id"];
               echo "<img src='$fichier' height='110' />";
            } else {
               echo 'Aucun logo';
            }
            echo "</td>";
            echo "<td>";
               echo "<form action='" . htmlspecialchars($upload_logo_url, ENT_QUOTES, 'UTF-8') . "' method='post' enctype='multipart/form-data' class='fileupload'>";
               echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
               echo "<input name='IdLogo' type='hidden' value='logo1' />";
               echo "<input type='file' name='photo' size='25' /><p><br>";
               echo "<input class='submit' type='submit' name='submit' value='" . __('Send') . "' />";
               echo "</form>"; // formulaire d'enregistrement du logo
            echo "</td>";
         echo "<td></td><td></td></tr>";
      echo "</table></div>";
	// Logo index	

   // Logo index
      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
         echo "<tr><th colspan='3'>" . __("LOGO 2", 'rp') . "</th></tr>";
         echo "<tr class='tab_bg_1'>";
            echo "<td width='35%'>";

            $doc = new Document();
            $img = $doc->find(['id' => $this->fields['logo_id2']]); // explore et recupére les values bdd comptenu dans document a la ligne id = logo_id enregistré en base config 
            $img = reset($img); // remet le curseur au debut du tableau ci dessus
            $has_logo = false;
            if (is_array($img) && !empty($img['filepath'])) { // verification que la varible soit non vide
               $raw_filepath = (string)$img['filepath'];
               $normalized_filepath = stripslashes($raw_filepath);
               $raw_fullpath = GLPI_DOC_DIR . '/' . $raw_filepath;
               $normalized_fullpath = GLPI_DOC_DIR . '/' . $normalized_filepath;

               // Auto-repair old escaped filepath values (eg: d\'ecran.png)
               if (!file_exists($raw_fullpath) && file_exists($normalized_fullpath) && !empty($img['id'])) {
                  $DB->update('glpi_documents', ['filepath' => $normalized_filepath], ['id' => (int)$img['id']]);
                  $raw_fullpath = $normalized_fullpath;
               }

               $has_logo = file_exists($raw_fullpath) || file_exists($normalized_fullpath);
            }

            if ($has_logo) {
               $fichier = $document_send_base . (int)$this->fields["logo_id2"];
               echo "<img src='$fichier' height='110' />";
            } else {
               echo 'Aucun logo';
            }
            echo "</td>";
            echo "<td>";
               echo "<form action='" . htmlspecialchars($upload_logo_url, ENT_QUOTES, 'UTF-8') . "' method='post' enctype='multipart/form-data' class='fileupload'>";
               echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
               echo "<input name='IdLogo' type='hidden' value='logo2' />";
               echo "<input type='file' name='photo' size='25' /><p><br>";
               echo "<input class='submit' type='submit' name='submit' value='" . __('Send') . "' />";
               echo "</form>"; // formulaire d'enregistrement du logo
            echo "</td>";
         echo "<td></td><td></td></tr>";
      echo "</table></div>";
	// Logo index	
   }

   static function getTypeName($nb = 0) {
      return __('Rapport', 'rp');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() === 'Config') {
         return __('Rapport', 'rp');
      }

      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() === 'Config') {
         $config = self::getInstance();
         $config->showConfigForm();
      }

      return true;
   }

   public static function getInstance() {
      if (!isset(self::$instance)) {
         $temp = new PluginRpConfig();
         $temp->getFromDB('1');
         self::$instance = $temp;
      }

      return self::$instance;
   }
}
