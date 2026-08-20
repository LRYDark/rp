<?php
/**
 * Page mobile du plugin RP, ouverte par le QR code imprimé sur le rapport de
 * préparation : <url>/plugins/rp/front/mobile.php?id=<ticket>&k=<HMAC>
 *
 * Sécurité : session GLPI obligatoire (redirection login native), jeton HMAC
 * du ticket, règles d'accès RP (feature 'mobile') et visibilité du ticket.
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
 * Droit d'utiliser l'interface mobile — évalué ici, mais REFUSÉ plus bas.
 *
 * Cette page est ouverte en scannant un QR code, souvent devant le client. Un
 * refus sec de GLPI y affichait une page d'erreur technique, sans dire quel
 * droit manquait ni sous quel profil : le technicien n'avait aucun moyen de
 * comprendre, encore moins d'agir. Le refus est donc rendu comme les autres
 * erreurs de cette page, avec le nom du droit à ouvrir.
 *
 * Le contrôle n'est pas affaibli pour autant : il précède toujours l'affichage
 * de la moindre information du ticket.
 */
$rp_can_mobile = PluginRpAccess::canUse('mobile');

$qr_valid = $ticket_id > 0 && PluginRpQrcode::checkTicketToken($ticket_id, $token);

$ticket = new Ticket();
// `$rp_can_mobile` en tête : sans le droit, le ticket n'est même pas chargé.
$ticket_ok = $rp_can_mobile && $qr_valid && $ticket->getFromDB($ticket_id) && $ticket->canViewItem();

Html::header(
   __('Intervention mobile', 'rp'),
   $_SERVER['PHP_SELF'],
   'helpdesk',
   'Ticket'
);

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

/*
 * Refus d'accès, énoncé plutôt que subi.
 *
 * Le profil ACTIF est nommé : sur téléphone, GLPI ouvre la session avec le
 * profil par défaut de l'utilisateur, qui n'est pas toujours celui dont il se
 * sert sur son poste. C'est la cause la plus fréquente d'un refus ici, et elle
 * se corrige en changeant de profil, sans toucher aux droits.
 */
if (!$rp_can_mobile) {
   $features = PluginRpAccess::getFeatures();
   $libelle  = $features['mobile']['label'] ?? __('Interface mobile (QR code)', 'rp');
   $profil   = (string)($_SESSION['glpiactiveprofile']['name'] ?? '');

   $message = "<div class='fw-bold'>"
      . __("Vous n'avez pas accès à l'interface mobile.", 'rp') . "</div>";
   $message .= "<div class='mt-2'>"
      . sprintf(
         __("Fonctionnalité « %s » : elle demande le droit « Rapport technicien » avec l'autorisation de créer.", 'rp'),
         htmlspecialchars($libelle, ENT_QUOTES)
      )
      . "</div>";
   if ($profil !== '') {
      $message .= "<div class='mt-2 text-muted'>"
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

if (!$qr_valid) {
   $rp_show_error(
      'alert-triangle',
      __("QR code invalide ou lien expiré. Régénérez le rapport d'atelier pour obtenir un nouveau QR code.", 'rp')
   );
   Html::footer();
   exit;
}
if (!$ticket_ok) {
   $rp_show_error('lock', __("Vous n'avez pas accès à ce ticket.", 'rp'));
   Html::footer();
   exit;
}

// ---- Données affichées : uniquement l'essentiel ----
$entity_name = Dropdown::getDropdownName('glpi_entities', (int)$ticket->fields['entities_id']);
$status_name = Ticket::getStatus((int)$ticket->fields['status']);

// BL éventuel via le plugin Gestion (même requête que l'onglet ticket)
$gestion_bl_id   = 0;
$gestion_signed  = null;
$gestion_bl_name = '';
if (Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri')) {
   $bl_row = $DB->request([
      'SELECT' => ['id', 'signed', 'bl'],
      'FROM'   => 'glpi_plugin_gestion_surveys',
      'WHERE'  => ['tickets_id' => $ticket_id],
      'ORDER'  => ['signed ASC', 'id DESC'],
      'LIMIT'  => 1,
   ])->current();
   if ($bl_row) {
      $gestion_signed  = (int)$bl_row['signed'];
      $gestion_bl_name = trim((string)($bl_row['bl'] ?? ''));
      if ($gestion_signed === 0) {
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
   // BL non signé : on enchaîne sur le formulaire du plugin Gestion
   $gestion_webdir = defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion');
   $sign_handler = 'gestion';
   $sign_modal   = (string)$gestion_bl_id;
   // `force_combined` : on saute le modal de choix du plugin Gestion pour
   // ouvrir directement le formulaire Rapport + BL. Ce modal pose la question
   // « compléter l'intervention ou signer ? » en rappelant les informations du
   // ticket — or cette page vient précisément de les afficher et de poser la
   // même question. Sans cela, le technicien répondait deux fois de suite.
   $sign_params  = [
      'job'            => $ticket_id,
      'root_doc'       => $gestion_webdir,
      'root_modal'     => 'rp-mobile-modal',
      'force_combined' => 1,
   ];
   // scripts_gestion.js est déjà chargé par le hook du plugin Gestion ;
   // seule cette racine lui manque pour retrouver ses propres URL.
   echo "<script>window.GLPI_PLUG_RP = " . json_encode($gestion_webdir) . ";</script>";
} else {
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
if ($gestion_signed !== null) {
   // Nom du document à signer, seulement s'il est renseigné.
   $bl_note = $gestion_bl_name !== ''
      ? "<i class='ti ti-file-text me-1'></i>" . htmlspecialchars($gestion_bl_name, ENT_QUOTES)
      : '';
   $rp_info_row(
      __('Bon de livraison', 'rp'),
      $gestion_signed === 0
         ? "<span class='badge bg-warning text-dark'>" . __('À faire signer', 'rp') . "</span>"
         : "<span class='badge bg-success text-white'>" . __('Signé', 'rp') . "</span>",
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
