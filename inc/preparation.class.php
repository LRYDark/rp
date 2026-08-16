<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Rapport de préparation (matériel réparé/préparé en atelier avant livraison).
 *
 * Les données saisies sont stockées dans `glpi_plugin_rp_preparations` (1 ligne
 * par ticket, mise à jour à chaque régénération) pour rester réexploitables :
 * préremplissage du formulaire, page mobile, rapport final. Le PDF généré est
 * un Document GLPI classique enregistré dans `glpi_plugin_rp_cridetails` avec
 * `type = 3` (comme les types 0/1/2 existants).
 */
class PluginRpPreparation extends CommonDBTM {

   static $rightname = "plugin_rp_rapport_preparation";

   static function getTypeName($nb = 0) {
      return _n("Rapport d'atelier", "Rapports d'atelier", $nb, 'rp');
   }

   static function getIcon() {
      return "fa-solid fa-screwdriver-wrench";
   }

   /**
    * Données de préparation d'un ticket (ou null).
    */
   static function getForTicket(int $ticket_id): ?array {
      global $DB;

      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_preparations')) {
         return null;
      }
      $row = $DB->request([
         'FROM'  => 'glpi_plugin_rp_preparations',
         'WHERE' => ['id_ticket' => $ticket_id],
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   /**
    * Enregistre (insert ou update) les données de préparation d'un ticket.
    * Appelé par front/cripdf.form.php lors de la génération du PDF (Form=FormPreparation).
    *
    * @param int   $ticket_id
    * @param array $data      champs déjà filtrés (clés = colonnes)
    */
   static function saveForTicket(int $ticket_id, array $data): bool {
      global $DB;

      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_preparations')) {
         return false;
      }

      $existing = self::getForTicket($ticket_id);
      if ($existing) {
         return (bool)$DB->update('glpi_plugin_rp_preparations', $data, ['id' => (int)$existing['id']]);
      }
      $data['id_ticket'] = $ticket_id;
      return (bool)$DB->insert('glpi_plugin_rp_preparations', $data);
   }

   /**
    * Champs saisis dans le formulaire : nom POST => colonne.
    * NB : ne pas nommer getFormFields() — méthode NON statique de CommonDBTM
    * en GLPI 11, la redéclarer statique est une erreur de compilation.
    *
    * Le matériel est identifié par son numéro de série (unique) + sa marque ;
    * le problème initial vient de la description du ticket (carte commune aux
    * autres rapports), il n'est donc pas ressaisi ici.
    */
   static function getPrepFormFields(): array {
      return [
         'prep_marque'  => 'marque',
         'prep_serial'  => 'serial',
         'prep_travaux' => 'travaux',
      ];
   }

   /**
    * Formulaire modal « Rapport d'atelier » (chargé via ajax/cri.php,
    * modal 'form_preparation'). POST vers front/cripdf.form.php, Form=FormPreparation.
    *
    * Même habillage que les autres modals du plugin (cartes .form-card,
    * éditeur riche, case « Visible dans le rapport »).
    */
   static function showFormModal(int $ticket_id): void {
      global $DB, $CFG_GLPI;

      $config = PluginRpConfig::getInstance();

      echo '<link rel="stylesheet" href="' . PLUGIN_RP_WEBDIR . '/public/css/signature_rp.css?r=' . (defined('PLUGIN_RP_ASSETS_REV') ? PLUGIN_RP_ASSETS_REV : '1') . '">';
      echo '<script src="' . PLUGIN_RP_WEBDIR . '/public/js/scripts_rp.js?r=' . (defined('PLUGIN_RP_ASSETS_REV') ? PLUGIN_RP_ASSETS_REV : '1') . '" defer></script>';

      $result = $DB->doQuery("SELECT glpi_tickets.* FROM glpi_tickets WHERE id = $ticket_id")->fetch_object();
      if (!$result) {
         echo "<div class='alert alert-danger'>Ticket introuvable.</div>";
         return;
      }

      $prep = self::getForTicket($ticket_id) ?? [];
      // Détection automatique : matériel associé, formulaire GLPI, texte du ticket
      $auto = PluginRpTicketInfo::detect($ticket_id);

      // valeurs par défaut : données déjà saisies > détection automatique
      $val = static function (string $key, string $fallback = '') use ($prep): string {
         return trim((string)($prep[$key] ?? '')) !== '' ? (string)$prep[$key] : $fallback;
      };

      echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/front/cripdf.form.php\" method=\"post\" name=\"formPreparation\">";
      echo Html::hidden('REPORT_ID', ['value' => $ticket_id]);
      echo Html::hidden('Form', ['value' => 'FormPreparation']);

      echo '<div class="form-container">';

      // === CHARTE (entité parente), même logique que les autres rapports ===
      if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0) {
         $entity_parrent1_id = (int)$config->fields['entity_parrent1'];
         $entity_parrent1 = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent1_id")->fetch_object();
         $entity_parrent2_id = (int)$config->fields['entity_parrent2'];
         $entity_parrent2 = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $entity_parrent2_id")->fetch_object();

         $cri   = new PluginRpCri();
         $group = $cri->getEntityGroupFromEntityId($result->id, $entity_parrent1->name, $entity_parrent2->name);
         $checked1 = ($group != 'entity_parrent2') ? 'checked' : '';
         $checked2 = ($group == 'entity_parrent2') ? 'checked' : '';

         echo '<div class="form-card">';
            echo '<div class="form-label">Type de rapport</div>';
            echo '<div class="form-content">';
               echo '<div class="radio-group">';
                  echo '<div class="radio-item">';
                     echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent1\" $checked1 id=\"prep_entity1\">";
                     echo "<label for=\"prep_entity1\">" . $entity_parrent1->name . "</label>";
                  echo '</div>';
                  echo '<div class="radio-item">';
                     echo "<input type=\"radio\" name=\"entity_parrent\" value=\"entity_parrent2\" $checked2 id=\"prep_entity2\">";
                     echo "<label for=\"prep_entity2\">" . $entity_parrent2->name . "</label>";
                  echo '</div>';
               echo '</div>';
            echo '</div>';
         echo '</div>';
      } else if ($config->fields['entity_parrent1'] == 0 && $config->fields['entity_parrent2'] != 0) {
         echo '<input name="entity_parrent" type="hidden" value="entity_parrent2" />';
      } else {
         echo '<input name="entity_parrent" type="hidden" value="entity_parrent1" />';
      }

      // === MATÉRIEL : le numéro de série suffit à identifier, + la marque ===
      echo '<div class="form-card card-preparation">';
         echo '<div class="form-label">Matériel</div>';
         echo '<div class="form-content">';
            echo '<div class="form-row">';
               echo '<div class="form-col">';
                  // Obligatoire : c'est la seule donnée qui identifie le matériel
                  // de façon certaine, la marque ne suffit pas.
                  echo '<label for="prep_serial">Numéro de série <span style="color:#d63939">*</span></label>';
                  echo '<input type="text" id="prep_serial" name="prep_serial" required placeholder="Numéro de série" value="'
                     . htmlspecialchars($val('serial', $auto['serial']), ENT_QUOTES) . '">';
               echo '</div>';
               echo '<div class="form-col">';
                  echo '<label for="prep_marque">Marque</label>';
                  echo '<input type="text" id="prep_marque" name="prep_marque" placeholder="Marque" value="'
                     . htmlspecialchars($val('marque', $auto['marque']), ENT_QUOTES) . '">';
               echo '</div>';
            echo '</div>';
            if (trim($auto['serial']) !== '' && trim((string)($prep['serial'] ?? '')) === '') {
               echo '<div class="text-muted" style="font-size:13px;margin-top:6px;">'
                  . '<i class="ti ti-wand"></i> Informations détectées automatiquement depuis le ticket, modifiables.'
                  . '</div>';
            }
         echo '</div>';
      echo '</div>';

      // === DESCRIPTION DU PROBLÈME (identique aux autres rapports) ===
      $description = (string)($result->content ?? '');
      echo '<div class="form-card card-description">';
         echo '<div class="form-label">Description du Problème</div>';
         echo '<div class="form-content">';
            echo '<div class="checkbox-group">';
               echo '<input type="checkbox" value="check" name="CHECK_DESCRIPTION_TICKET" checked id="prep_desc_check">';
               echo '<label for="prep_desc_check">Visible dans le rapport</label>';
            echo '</div>';
            Html::textarea([
               'name'              => 'DESCRIPTION_TICKET',
               'value'             => Glpi\RichText\RichText::getSafeHtml($description),
               'enable_richtext'   => true,
               'enable_fileupload' => false,
               'enable_images'     => false,
            ]);
         echo '</div>';
      echo '</div>';

      /*
       * === TRAVAUX EFFECTUÉS ===
       *
       * « Travaux effectués » et les tâches du ticket décrivent la même chose.
       * Deux cas, donc, plutôt qu'une saisie qui ferait doublon :
       *
       *  - le ticket porte déjà des tâches : on les reprend telles quelles,
       *    exactement comme le rapport d'intervention — et sans les suivis,
       *    qui ne relatent pas les travaux ;
       *  - aucune tâche : saisie libre obligatoire, avec le temps passé. Elle
       *    créera la tâche manquante à la génération du PDF, pour que le ticket
       *    porte bien la trace de l'intervention.
       */
      $is_private = ((int)($config->fields['use_publictask'] ?? 0) === 1) ? 'AND is_private = 0' : '';
      $resulttask = $DB->doQuery(
         "SELECT glpi_tickettasks.id, content, date, name, actiontime, is_private
          FROM glpi_tickettasks
          INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id
          WHERE tickets_id = $ticket_id $is_private"
      );
      $numbertask = $resulttask ? $DB->numrows($resulttask) : 0;

      if ($numbertask > 0) {
         $i = 1;
         while ($data = $DB->fetchArray($resulttask)) {
            echo '<div class="form-card card-task">';
               echo '<div class="form-label">';
                  echo 'Tâche N°' . $i++;
                  if ($data['is_private'] == 1) {
                     echo ' - <span style="color:red">Privée <i class="ti ti-lock"></i></span>';
                  }
                  echo '<br><small class="task-meta">' . $data['date'] . ' - ' . $data['name'] . '</small>';
               echo '</div>';

               echo '<div class="form-content">';
                  if ((int)$config->fields['choice'] === 1) {
                     // Cochées par défaut, contrairement au rapport d'intervention :
                     // ici les tâches SONT les travaux effectués, rubrique
                     // obligatoire du rapport. La case reste disponible pour en
                     // exclure une ponctuellement.
                     $checked = 'checked';
                     echo '<div class="checkbox-group">';
                        echo '<input type="checkbox" value="check" name="tasks_pdf_' . $data['id'] . '" ' . $checked . ' id="prep_task_' . $data['id'] . '">';
                        echo '<label for="prep_task_' . $data['id'] . '">Visible dans le rapport</label>';
                     echo '</div>';
                  } else {
                     echo '<input type="hidden" value="check" name="tasks_pdf_' . $data['id'] . '" />';
                  }

                  echo '<input type="hidden" value="' . htmlspecialchars((string)$data['date'], ENT_QUOTES) . '" name="tasks_date_' . $data['id'] . '" />';
                  echo '<input type="hidden" value="' . htmlspecialchars((string)$data['actiontime'], ENT_QUOTES) . '" name="tasks_time_' . $data['id'] . '" />';
                  echo '<input type="hidden" value="' . htmlspecialchars((string)$data['name'], ENT_QUOTES) . '" name="tasks_name_' . $data['id'] . '" />';

                  Html::textarea([
                     'name'              => 'TASKS_DESCRIPTION' . $data['id'],
                     'value'             => Glpi\RichText\RichText::getSafeHtml($data['content']),
                     'enable_richtext'   => true,
                     'enable_fileupload' => false,
                     'enable_images'     => false,
                  ]);
               echo '</div>';
            echo '</div>';
         }
      } else {
         echo '<div class="form-card card-preparation">';
            echo '<div class="form-label">Travaux effectués <span style="color:#d63939">*</span></div>';
            echo '<div class="form-content">';
               echo '<div class="text-muted" style="font-size:13px;margin-bottom:8px;">'
                  . "<i class='ti ti-info-circle'></i> Ce ticket ne porte aucune tâche : votre saisie en créera une, "
                  . "avec le temps passé indiqué ci-dessous."
                  . '</div>';
               Html::textarea([
                  'name'              => 'prep_travaux',
                  'value'             => Glpi\RichText\RichText::getSafeHtml($val('travaux')),
                  'enable_richtext'   => true,
                  'enable_fileupload' => false,
                  'enable_images'     => false,
               ]);
               echo '<div style="margin-top:14px;">';
                  echo '<label for="dropdown_prep_actiontime">Temps passé</label><br>';
                  Dropdown::showTimeStamp('prep_actiontime', [
                     'value'           => 300,
                     'min'             => 0,
                     'addfirstminutes' => true,
                  ]);
               echo '</div>';
            echo '</div>';
         echo '</div>';
      }

      // === CARTE ACTIONS (identique aux autres modals) ===
      echo '<div class="form-card actions-card" id="actions-bottom">';
         echo '<div class="form-content">';
            echo '<input type="submit" name="add_cri" id="sig-submitBtn" value="Génération du PDF" class="submit-btn">';
         echo '</div>';
      echo '</div>';

      echo '</div>'; // form-container

      // Bouton flottant « Aller en bas », comme les autres modals
      echo '<button type="button" class="fab-go-bottom" title="Aller en bas" aria-label="Aller en bas">↓</button>';

      Html::closeForm();

      echo "<script>
         setTimeout(function() {
            var goBottomBtn = document.querySelector('.fab-go-bottom');
            if (goBottomBtn) {
               goBottomBtn.addEventListener('click', function() {
                  var target = document.getElementById('actions-bottom');
                  if (target && typeof target.scrollIntoView === 'function') {
                     target.scrollIntoView({behavior: 'smooth', block: 'end'});
                  }
               });
            }
         }, 300);
      </script>";
   }
}
