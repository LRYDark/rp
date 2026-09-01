<?php
/**
 * Page mobile du plugin RP, ouverte par le QR code imprimé sur le rapport de
 * préparation : <url>/plugins/rp/front/mobile.php?id=<ticket>&k=<HMAC>
 *
 * Sécurité : session GLPI obligatoire (redirection login native), jeton HMAC
 * du ticket, puis visibilité native du ticket (`canViewItem`).
 *
 * Deux publics, une seule URL : le technicien (droit RP 'mobile') obtient cet
 * écran d'action ; toute autre personne autorisée à voir le ticket — le client
 * demandeur au premier chef — est renvoyée sur le ticket natif, dans son
 * interface. Le QR voyage avec le matériel : il est scanné par les deux.
 *
 * Présentation : composants natifs GLPI (carte, liste, badges, boutons Tabler)
 * et mise en page standard, sans habillage propre au plugin.
 */
include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

$ticket_id = (int)($_GET['id'] ?? 0);
$token     = (string)($_GET['k'] ?? '');

/*
 * Droit d'utiliser l'interface mobile du plugin : il commande les DEUX boutons
 * d'action (compléter l'intervention, faire signer), pas la lecture du ticket.
 */
$rp_can_mobile = PluginRpAccess::canUse('mobile');

/*
 * Jeton d'abord : sans HMAC valide, aucun ticket n'est lu. C'est lui qui
 * empêche l'énumération, et il reste le premier verrou quel que soit le profil.
 */
$qr_valid = $ticket_id > 0 && PluginRpQrcode::checkTicketToken($ticket_id, $token);

$ticket = new Ticket();
// `canViewItem()` : la visibilité native GLPI (demandeur, observateur,
// attribué, entité). C'est elle, et non le droit RP, qui décide de l'accès.
$ticket_ok = $qr_valid && $ticket->getFromDB($ticket_id) && $ticket->canViewItem();

/*
 * Renvoi vers le ticket natif pour qui n'a pas l'interface mobile.
 *
 * Le QR est imprimé sur un rapport d'atelier qui accompagne le matériel : il
 * est aussi scanné par le client. Lui opposer « vous n'avez pas le droit
 * "Rapport technicien" » n'avait aucun sens — il n'en veut pas, il veut voir
 * son ticket, et GLPI sait déjà s'il en a le droit. On le dépose donc sur le
 * ticket, dans SON interface : `ticket.form.php` sert les deux (cf. le tableau
 * `$menus` de front/ticket.form.php), en simplifiée comme en centrale.
 *
 * Ce n'est pas un contournement : la redirection exige un jeton valide ET
 * `canViewItem()`. Qui ne voit pas le ticket n'est pas redirigé, il est refusé
 * plus bas.
 *
 * Placé AVANT `Html::header()` : une redirection après le premier octet de
 * sortie est impossible.
 */
if ($ticket_ok && !$rp_can_mobile) {
   /*
    * En interface centrale, c'est un technicien : le plus souvent son téléphone
    * a ouvert la session avec son profil par défaut, pas celui de son poste. Un
    * mot le lui dit, sinon il ne comprendrait pas pourquoi il arrive sur le
    * ticket brut au lieu de l'écran mobile. Le client, lui, ne voit rien.
    */
   if (Session::getCurrentInterface() === 'central') {
      Session::addMessageAfterRedirect(
         htmlspecialchars(
            __("Interface mobile RP indisponible avec ce profil : ouverture du ticket. Changez de profil pour retrouver l'écran de signature.", 'rp'),
            ENT_QUOTES
         ),
         true,
         INFO
      );
   }
   Html::redirect($CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $ticket_id);
}

/*
 * En-tête de l'interface de l'utilisateur, pas celle du plugin.
 *
 * Depuis que le QR est scanné aussi par les clients, cette page peut être
 * atteinte en interface simplifiée (QR périmé, ticket d'un autre) : `header()`
 * y monterait le menu de l'interface centrale à quelqu'un qui n'y a pas droit.
 * `helpFooter()` n'étant qu'un alias de `footer()`, seul l'en-tête se distingue.
 */
if (Session::getCurrentInterface() === 'central') {
   Html::header(
      __('Intervention mobile', 'rp'),
      $_SERVER['PHP_SELF'],
      'helpdesk',
      'Ticket'
   );
} else {
   Html::helpHeader(__('Intervention mobile', 'rp'));
}

/**
 * Message d'erreur en composant natif, centré comme le reste de la page.
 */
$rp_show_error = static function (string $icon, string $message): void {
   echo "<div class='row justify-content-center'>";
   echo "  <div class='col-12 col-md-8 col-lg-6'>";
   echo "    <div class='alert alert-danger' role='alert'>";
   echo "      <div class='d-flex'>";
   echo "        <div class='me-2'><i class='ti ti-" . $icon . " fs-2'></i></div>";
   echo "        <div>" . $message . "</div>";
   echo "      </div>";
   echo "    </div>";
   echo "  </div>";
   echo "</div>";
};

if (!$qr_valid) {
   $rp_show_error(
      'alert-triangle',
      __("QR code invalide ou lien expiré. Régénérez le rapport d'atelier pour obtenir un nouveau QR code.", 'rp')
   );
   Html::footer();
   exit;
}
/*
 * Seul refus qui subsiste : la visibilité du ticket.
 *
 * Le profil actif n'est rappelé qu'en interface centrale. Pour un technicien
 * c'est presque toujours l'explication — son téléphone a ouvert la session avec
 * son profil par défaut, pas celui de son poste — et il peut agir dessus. Pour
 * un client, ce nom de profil ne veut rien dire et n'ouvre aucune porte.
 */
if (!$ticket_ok) {
   $message = __("Vous n'avez pas accès à ce ticket.", 'rp');
   $profil  = (string)($_SESSION['glpiactiveprofile']['name'] ?? '');
   if (Session::getCurrentInterface() === 'central' && $profil !== '') {
      $message = "<div class='fw-bold'>" . $message . "</div>"
         . "<div class='mt-2 text-muted'>"
         . sprintf(
            __('Profil actif : %s. Si vous en avez un autre, changez-en avant de rouvrir le QR code.', 'rp'),
            htmlspecialchars($profil, ENT_QUOTES)
         )
         . "</div>";
   }
   $rp_show_error('lock', $message);
   Html::footer();
   exit;
}

// ---- Données affichées : uniquement l'essentiel ----
$entity_name = Dropdown::getDropdownName('glpi_entities', (int)$ticket->fields['entities_id']);
$status_name = Ticket::getStatus((int)$ticket->fields['status']);

/*
 * TOUS les bons du ticket, et non le plus recent.
 *
 * La page n'en lisait qu'un (`LIMIT 1`) : sur un ticket qui en porte
 * plusieurs, le technicien voyait « Bon de livraison : a faire signer » au
 * singulier et repartait en croyant n'en avoir qu'un a faire signer. Le
 * formulaire, lui, les a toujours tous proposes — c'est la page qui mentait.
 */
$gestion_active   = Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri');
$gestion_bl_id    = 0;   // point d'entree du formulaire : le plus ancien bon non signe
$gestion_names    = [];  // libelles, dans l'ordre d'affichage
$gestion_unsigned = 0;
$gestion_total    = 0;

if ($gestion_active) {
   foreach ($DB->request([
      'SELECT' => ['id', 'signed', 'bl'],
      'FROM'   => 'glpi_plugin_gestion_surveys',
      'WHERE'  => ['tickets_id' => $ticket_id],
      'ORDER'  => ['signed ASC', 'id DESC'],
      'LIMIT'  => 20,
   ]) as $bl_row) {
      $gestion_total++;
      $bl_label = trim((string)($bl_row['bl'] ?? ''));
      $gestion_names[] = ($bl_label !== '') ? $bl_label : ('#' . (int)$bl_row['id']);
      if ((int)$bl_row['signed'] !== 1) {
         $gestion_unsigned++;
         // Tri `id DESC` : le dernier non signe rencontre est le PLUS ANCIEN,
         // celui qui attend depuis le plus longtemps. Meme regle que le
         // bouton flottant (ajax/ticket_actions.php).
         $gestion_bl_id = (int)$bl_row['id'];
      }
   }
}

/*
 * Bouton « Livré — faire signer ».
 *
 * Les paramètres transitent par des attributs `data-*` et non par un `onclick`
 * en ligne : `json_encode()` produit des guillemets doubles, qui refermaient
 * l'attribut au premier champ et rendaient le bouton inopérant. Le passage par
 * `data-*` (échappé) puis `JSON.parse` supprime tout risque d'échappement.
 */
if ($gestion_bl_id > 0) {
   // Bons restant a signer : on enchaine sur le formulaire du plugin Gestion.
   $gestion_webdir = defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion');
   $sign_handler = 'gestion';
   $sign_modal   = (string)$gestion_bl_id;
   /*
    * On saute le modal de choix du plugin Gestion pour ouvrir directement le
    * formulaire de signature. Ce modal pose la question « compléter
    * l'intervention ou signer ? » en rappelant les informations du ticket — or
    * cette page vient précisément de les afficher et de poser la même question.
    * Sans cela, le technicien répondait deux fois de suite.
    */
   $sign_params  = [
      'job'        => $ticket_id,
      'root_doc'   => $gestion_webdir,
      'root_modal' => 'rp-mobile-modal',
   ];
   /*
    * Le mode est PRESELECTIONNE, il n'est pas demande ici.
    *
    * « Que signe le client ? » a sa place dans le formulaire de signature, qui
    * la pose deja (`PluginGestionCri::renderCombinedModeRadio`) et sait la
    * reposer quand le technicien change d'avis. La reposer sur cette page
    * revenait a la lui montrer deux fois pour une seule reponse.
    *
    * QUEL formulaire, en revanche, n'est pas decide ici : `defaultCombinedMode()`
    * du plugin Gestion en juge, comme pour tous les autres ecrans. Forcer
    * « Rapport + BL » en dur faisait de cette page la seule a regenerer un
    * rapport deja signe.
    */
   $rp_mode = method_exists('PluginGestionCri', 'defaultCombinedMode')
      ? PluginGestionCri::defaultCombinedMode($ticket_id)
      : 'both';
   $sign_params[$rp_mode === 'bl' ? 'force_bl' : 'force_combined'] = 1;

   // scripts_gestion.js est déjà chargé par le hook du plugin Gestion ;
   // seule cette racine lui manque pour retrouver ses propres URL.
   echo "<script>window.GLPI_PLUG_RP = " . json_encode($gestion_webdir) . ";</script>";
} else {
   /*
    * Aucun bon a signer — plugin Gestion inactif, ticket sans bon, ou tous
    * deja signes. La page se replie sur le rapport seul : c'est la degradation
    * attendue, pas une erreur.
    */
   $sign_handler = 'rp';
   $sign_modal   = 'form_rapport';
   $sign_params  = ['job' => $ticket_id, 'root_doc' => PLUGIN_RP_WEBDIR];
}

// « Compléter l'intervention » : on bascule sur le parcours natif GLPI —
// ouverture du ticket avec le formulaire de tâche déplié, puis signature
// automatique du rapport une fois la tâche enregistrée (cf. public/js/fab_rp.js).
$complete_url = $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $ticket_id . '&rp_action=newtask';

/**
 * Une ligne d'information de la carte.
 *
 * @param bool $stacked valeur placée SOUS le libellé et alignée à gauche.
 *                      Réservé aux valeurs longues, comme l'arborescence
 *                      complète d'une entité : sur deux colonnes elle se
 *                      renvoie à la ligne au milieu des mots et devient
 *                      difficile à lire.
 */
$rp_info_row = static function (
   string $label,
   string $value_html,
   bool $stacked = false,
   string $note_html = ''
): void {
   echo "<div class='list-group-item'>";
   if ($stacked) {
      echo "  <div class='text-secondary'>" . $label . "</div>";
      echo "  <div class='fw-bold mt-2'>" . $value_html . "</div>";
   } else {
      echo "  <div class='d-flex justify-content-between align-items-center gap-3'>";
      echo "    <span class='text-secondary'>" . $label . "</span>";
      echo "    <span class='fw-bold text-end'>" . $value_html . "</span>";
      echo "  </div>";
   }
   if ($note_html !== '') {
      // Pleine largeur et `text-break` : un nom de fichier n'a pas d'espaces
      // où se couper, il déborderait dans une colonne étroite.
      echo "  <div class='text-secondary small mt-3 text-break'>" . $note_html . "</div>";
   }
   echo "</div>";
};

echo "<div class='row justify-content-center'>";
echo "  <div class='col-12 col-md-8 col-lg-6 rp-mobile-page'>";

echo "    <div id='rp_cri_error' class='alert alert-danger' style='display:none;'></div>";

echo "    <div class='card'>";

echo "      <div class='card-header'>";
echo "        <div>";
echo "          <div class='card-title mb-1'>" . sprintf(__('Ticket #%s', 'rp'), sprintf('%07d', $ticket_id)) . "</div>";
echo "          <div class='text-secondary'>" . htmlspecialchars((string)($ticket->fields['name'] ?? ''), ENT_QUOTES) . "</div>";
echo "        </div>";
echo "      </div>";

echo "      <div class='list-group list-group-flush'>";
$rp_info_row(__('Client', 'rp'), htmlspecialchars($entity_name, ENT_QUOTES), true);
$rp_info_row(__('Statut', 'rp'), htmlspecialchars($status_name, ENT_QUOTES));
if ($gestion_total > 0) {
   /*
    * Le NOMBRE restant, pas un simple etat.
    *
    * « A faire signer » ne disait pas combien : sur un ticket a plusieurs
    * bons, le technicien ne savait pas s'il en restait un ou quatre. Les
    * libelles sont listes dessous — c'est ce qu'il compare au papier qu'il a
    * en main.
    */
   $bl_note = !empty($gestion_names)
      ? "<i class='ti ti-file-text me-1'></i>"
         . htmlspecialchars(implode(', ', $gestion_names), ENT_QUOTES)
      : '';
   if ($gestion_unsigned > 0) {
      $bl_badge = "<span class='badge bg-warning text-dark'>"
         . ($gestion_unsigned > 1
            ? sprintf(__('%d à faire signer', 'rp'), $gestion_unsigned)
            : __('À faire signer', 'rp'))
         . "</span>";
   } else {
      $bl_badge = "<span class='badge bg-success text-white'>"
         . ($gestion_total > 1 ? __('Tous signés', 'rp') : __('Signé', 'rp'))
         . "</span>";
   }
   $rp_info_row(
      _n('Bon de livraison', 'Bons de livraison', $gestion_total, 'rp'),
      $bl_badge,
      false,
      $bl_note
   );
} else {
   $rp_info_row(
      __('Bon de livraison', 'rp'),
      "<span class='text-secondary fw-normal'>" . __('Aucun BL associé', 'rp') . "</span>"
   );
}
echo "      </div>";

// Boutons pleine largeur, empilés : cible large et atteignable au pouce.
// Sur téléphone, `rp-mobile-actions` les fixe en bas de l'écran (voir le CSS),
// pour la même raison que les autres écrans des plugins s'ouvrent par le bas.
echo "      <div class='card-body d-grid gap-3 rp-mobile-actions'>";
echo "        <a class='btn btn-primary btn-lg' href='" . htmlspecialchars($complete_url, ENT_QUOTES) . "'>";
echo "          <i class='ti ti-list-check me-2'></i>" . __("Compléter l'intervention", 'rp');
echo "        </a>";
echo "        <button type='button' class='btn btn-success btn-lg' "
   . "data-rp-sign='1' "
   . "data-rp-handler='" . $sign_handler . "' "
   . "data-rp-modal='" . htmlspecialchars($sign_modal, ENT_QUOTES) . "' "
   . "data-rp-params='" . htmlspecialchars(json_encode($sign_params), ENT_QUOTES) . "'>";
echo "          <i class='ti ti-file-signature me-2'></i>" . __('Livré — faire signer', 'rp');
echo "        </button>";
echo "      </div>";

echo "    </div>"; // card

// Conteneurs des fenêtres de signature (RP et Gestion)
echo "    <div id='form_rapport' style='display:none;'></div>";
echo "    <div id='rp-mobile-modal'></div>";

echo "  </div>";
echo "</div>";

?>
<script>
   // Un seul écouteur délégué : le bouton reste inerte tant que les scripts
   // des plugins ne sont pas chargés, plutôt que de lever une erreur silencieuse.
   document.addEventListener('click', function (event) {
      const button = event.target.closest('[data-rp-sign]');
      if (!button) {
         return;
      }
      event.preventDefault();

      let params = {};
      try {
         params = JSON.parse(button.dataset.rpParams || '{}');
      } catch (e) {
         params = {};
      }

      const handler = button.dataset.rpHandler;
      const modal   = button.dataset.rpModal;

      if (handler === 'gestion' && typeof gestion_loadCriForm === 'function') {
         gestion_loadCriForm('showCriForm', modal, params);
         return;
      }
      if (typeof rp_loadCriForm === 'function') {
         rp_loadCriForm('showCriForm', modal, params);
         return;
      }

      const error = document.getElementById('rp_cri_error');
      if (error) {
         error.textContent = <?php echo json_encode(__('Le formulaire de signature n\'a pas pu être chargé. Rechargez la page.', 'rp')); ?>;
         error.style.display = '';
      }
   });
</script>
<?php

Html::footer();
