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
    * Carte « Que devient le matériel ? », en tête des deux formulaires.
    *
    * Rendu UNIQUE, appelé aussi bien par le rapport d'atelier que par le
    * rapport d'intervention chargé depuis lui : les deux réponses restent donc
    * visibles et cochables après la bascule. Auparavant, choisir « le client
    * repart avec » remplaçait le formulaire par celui d'intervention, qui ne
    * porte pas cette carte — le choix disparaissait avec elle et il fallait
    * rouvrir le modal pour revenir en arrière.
    *
    * @param string $current Formulaire actuellement affiché : 'form_preparation'
    *                        ou 'form_rapport'. Décide de la réponse cochée, et
    *                        de celle qui bascule vers l'autre formulaire.
    */
   static function showDestinationCard(int $ticket_id, string $current): void {
      $config          = PluginRpConfig::getInstance();
      $livraison_group = (int)($config->fields['groups_id_livraison'] ?? 0);
      $switch_params   = ['job' => $ticket_id, 'root_doc' => PLUGIN_RP_WEBDIR];
      $atelier         = ($current !== 'form_rapport');

      /*
       * Un bon de livraison attend d'être signé : « le client repart avec »
       * mène alors DIRECTEMENT au formulaire « Rapport + BL ».
       *
       * Le client est devant le technicien, avec sa machine et son bon : les
       * deux signatures se prennent dans le même geste. Passer d'abord par le
       * rapport seul, puis cocher « + BL », c'était deux clics pour une seule
       * situation — et l'oubli du bon à chaque distraction. Le choix reste
       * modifiable une fois arrivé, c'est la carte suivante.
       */
      $bl = ($atelier && class_exists('PluginRpTicketActions'))
         ? PluginRpTicketActions::getSignableBl($ticket_id)
         : null;

      /*
       * `data-params` et `data-bl` : ce que la bascule du plugin Gestion vient
       * lire, exactement comme sur ses propres écrans. `data-rp-params` reste
       * à côté pour le retour vers l'atelier — les deux fonctions cherchent
       * des attributs distincts et ne se marchent pas dessus.
       */
      $bl_attrs = '';
      if ($bl !== null) {
         $bl_params = [
            'job'          => $ticket_id,
            'root_doc'     => defined('PLUGIN_GESTION_WEBDIR')
               ? PLUGIN_GESTION_WEBDIR
               : Plugin::getWebDir('gestion'),
            // Pour que cette carte reste affichée une fois arrivé : le
            // technicien doit pouvoir revenir sur sa réponse.
            'from_atelier' => 1,
         ];
         $bl_attrs = ' data-bl="' . (int)$bl['id'] . '" data-params="'
            . htmlspecialchars(json_encode($bl_params), ENT_QUOTES) . '"';
      }

      echo '<div class="form-card card-preparation" data-rp-params="'
         . htmlspecialchars(json_encode($switch_params), ENT_QUOTES) . '"'
         . $bl_attrs . '>';
         echo '<div class="form-label">Que devient le matériel ?</div>';
         echo '<div class="form-content">';
            /*
             * Témoin posté par le seul formulaire d'atelier : sans lui,
             * impossible de distinguer un formulaire soumis sans livraison d'un
             * appelant qui ignore ce champ (API, régénération), auquel on doit
             * conserver le QR code.
             *
             * Absent du rapport d'intervention : ce document ne commande jamais
             * de livraison, et le générateur ne lit ce groupe que pour
             * `Form=FormPreparation`.
             */
            if ($atelier) {
               echo '<input type="hidden" name="prep_livraison_choisie" value="1">';
            }
            /*
             * Un SEUL groupe de boutons — même attribut `name` : deux noms
             * distincts n'auraient pas été exclusifs, et les deux réponses
             * seraient apparues cochées en même temps.
             *
             * La valeur d'une réponse non cochée est le nom du modal à charger :
             * la choisir recharge l'autre formulaire à la place de celui-ci,
             * elle n'est donc jamais envoyée. Seule `1`, sur le formulaire
             * d'atelier, commande la livraison.
             */
            echo '<div class="radio-group">';
               echo '<div class="radio-item">';
                  echo '<input type="radio" name="prep_livraison" '
                     . 'value="' . ($atelier ? '1' : 'form_preparation') . '" '
                     . ($atelier ? 'checked ' : 'onchange="rp_switchReportForm(this);" ')
                     . 'id="prep_dest_livrer_' . $ticket_id . '">';
                  echo '<label for="prep_dest_livrer_' . $ticket_id . '">'
                     . "Il part en livraison <small class='text-muted'>— rapport d'atelier</small>"
                     . '</label>';
               echo '</div>';
               echo '<div class="radio-item">';
                  /*
                   * Deux destinations pour la même réponse, selon qu'un bon
                   * attend ou non :
                   *   - un bon à signer -> la bascule du plugin Gestion, qui
                   *     charge « Rapport + BL » (value 'both' : c'est ce que
                   *     cette fonction attend pour le mode combiné) ;
                   *   - aucun bon -> la bascule du plugin RP, rapport seul.
                   */
                  $remis_switch = 'onchange="rp_switchReportForm(this);" ';
                  $remis_value  = 'form_rapport';
                  if ($atelier && $bl !== null) {
                     $remis_switch = 'onchange="gestion_switchCombinedMode(this);" ';
                     $remis_value  = 'both';
                  }
                  echo '<input type="radio" name="prep_livraison" '
                     . 'value="' . $remis_value . '" '
                     . ($atelier ? $remis_switch : 'checked ')
                     . 'id="prep_dest_remis_' . $ticket_id . '">';
                  echo '<label for="prep_dest_remis_' . $ticket_id . '">'
                     . "Le client repart avec <small class='text-muted'>— rapport d'intervention"
                     . ($atelier && $bl !== null ? ' + BL' : '')
                     . ", signé par lui</small>"
                     . '</label>';
               echo '</div>';
            echo '</div>';
            echo '<div class="text-muted" style="font-size:13px;margin-top:6px;">'
               . "<i class='ti ti-qrcode'></i> En livraison, le PDF porte un QR code que le technicien "
               . "scanne chez le client pour faire signer le rapport d'intervention";
            if ($livraison_group > 0) {
               echo ", et une tâche est attribuée au groupe "
                  . htmlspecialchars(Dropdown::getDropdownName('glpi_groups', $livraison_group), ENT_QUOTES);
            }
            echo '.</div>';
         echo '</div>';
      echo '</div>';
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

      /*
       * `target="_blank"` : la réponse à cette soumission EST le PDF
       * (Content-Type: application/pdf). Sans nouvel onglet, l'utilisateur
       * quitte le ticket pour se retrouver devant un document, et doit y
       * revenir à la main pour l'étape suivante. Le PDF s'ouvre donc à côté, et
       * la page du ticket reste vivante — c'est ce qui permet d'enchaîner.
       */
      // Sauf si le réglage « Affichage du PDF après signature » est sur Non :
      // rien ne s'ouvrirait alors dans cet onglet, le générateur ramène au ticket.
      $prep_display_pdf = (int)($config->fields['DisplayPdfEnd'] ?? 1) === 1;
      echo "<form action=\"" . PLUGIN_RP_WEBDIR . "/front/cripdf.form.php\" method=\"post\" name=\"formPreparation\""
         . ($prep_display_pdf ? ' target="_blank"' : '') . ">";
      echo Html::hidden('REPORT_ID', ['value' => $ticket_id]);
      echo Html::hidden('Form', ['value' => 'FormPreparation']);

      echo '<div class="form-container">';

      self::showDestinationCard($ticket_id, 'form_preparation');

      /*
       * === CHARTE (« Type de rapport »), même logique que les autres rapports ===
       *
       * Les chartes viennent maintenant d'une table, en nombre libre : le
       * formulaire n'a plus à connaître deux entités parentes codées en dur, il
       * se contente d'en proposer autant qu'il en existe.
       *
       * Le champ garde son nom historique `entity_parrent`, lu à de nombreux
       * endroits du générateur de PDF ; seule sa VALEUR change et porte
       * désormais l'identifiant de la charte.
       */
      $chartes = PluginRpCharte::getAll();

      if (count($chartes) > 1) {
         // Simple présélection d'après l'entité du ticket : le choix reste
         // manuel, comme avant, l'utilisateur peut toujours en changer.
         $selected    = PluginRpCharte::getForEntity((int)($result->entities_id ?? 0));
         $selected_id = (int)($selected['id'] ?? 0);
         // Garantit qu'un bouton est toujours coché : l'ancien formulaire
         // n'offrait jamais un groupe de radios entièrement vide, et un envoi
         // sans charte retomberait sur le repli au lieu du choix affiché.
         if (!isset($chartes[$selected_id])) {
            $selected_id = (int)array_key_first($chartes);
         }

         echo '<div class="form-card">';
            echo '<div class="form-label">Type de rapport</div>';
            echo '<div class="form-content">';
               echo '<div class="radio-group">';
                  foreach ($chartes as $charte) {
                     $charte_id = (int)$charte['id'];
                     // Identifiant DOM dérivé de la charte : le nombre de
                     // boutons n'est plus connu à l'écriture du formulaire.
                     $dom_id  = 'prep_charte_' . $charte_id;
                     $checked = ($charte_id === $selected_id) ? ' checked' : '';
                     echo '<div class="radio-item">';
                        echo '<input type="radio" name="entity_parrent" value="' . $charte_id . '"'
                           . $checked . ' id="' . $dom_id . '">';
                        echo '<label for="' . $dom_id . '">'
                           . htmlspecialchars((string)($charte['name'] ?? ''), ENT_QUOTES) . '</label>';
                     echo '</div>';
                  }
               echo '</div>';
            echo '</div>';
         echo '</div>';
      } else if (count($chartes) === 1) {
         // Une seule charte : rien à choisir, on la poste sans encombrer le
         // formulaire d'un bouton unique déjà coché.
         echo '<input name="entity_parrent" type="hidden" value="' . (int)array_key_first($chartes) . '" />';
      } else if ($config->fields['entity_parrent1'] != 0 && $config->fields['entity_parrent2'] != 0) {
         /*
          * Aucune charte en base : la migration n'a pas encore tourné. On rejoue
          * à l'identique l'ancien choix sur les colonnes numérotées, que les
          * accesseurs de PluginRpCharte savent encore relire.
          */
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
            /*
             * DÉCOCHÉE par défaut ici, contrairement aux autres rapports.
             *
             * Un rapport d'atelier rend compte de ce qui a été FAIT sur la
             * machine. La demande initiale du client, elle, est déjà connue de
             * lui et alourdit un document destiné à l'atelier — souvent
             * rédigée au téléphone, dans des termes qui n'ont plus grand-chose
             * à voir avec la panne réelle.
             *
             * La case reste là : elle se coche quand la demande éclaire le
             * travail effectué.
             */
            echo '<div class="checkbox-group">';
               echo '<input type="checkbox" value="check" name="CHECK_DESCRIPTION_TICKET" id="prep_desc_check">';
               echo '<label for="prep_desc_check">Visible dans le rapport</label>';
            echo '</div>';
            PluginRpRichText::show('DESCRIPTION_TICKET', $description);
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

                  PluginRpRichText::show('TASKS_DESCRIPTION' . $data['id'], $data['content']);
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
               PluginRpRichText::show('prep_travaux', $val('travaux'));
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

      /*
       * === COMMENTAIRE INTERNE ===
       *
       * Même champ que sur le rapport d'intervention, au même moment du
       * parcours : juste avant la signature du technicien, qui est apposée dans
       * le PDF. Ce qu'il écrit ici ne part PAS dans le document — il devient un
       * suivi PRIVÉ du ticket, invisible du client.
       *
       * Laissé vide, rien n'est ajouté.
       */
      echo '<div class="form-card card-followup">';
         echo '<div class="form-label">Commentaire interne</div>';
         echo '<div class="form-content">';
            echo '<div class="text-muted" style="font-size:13px;margin-bottom:8px;">'
               . "<i class='ti ti-lock'></i> Ajouté au ticket comme suivi privé, absent du PDF. "
               . "Laissez vide pour ne rien ajouter."
               . '</div>';
            PluginRpRichText::show('rp_commentaire', '');
         echo '</div>';
      echo '</div>';

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
