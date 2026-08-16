<?php
/**
 * Page mobile du plugin RP, ouverte par le QR code imprimé sur le rapport de
 * préparation : <url>/plugins/rp/front/mobile.php?id=<ticket>&k=<HMAC>
 *
 * Sécurité : session GLPI obligatoire (redirection login native), jeton HMAC
 * du ticket, règles d'accès RP (feature 'mobile') et visibilité du ticket.
 */
include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

$ticket_id = (int)($_GET['id'] ?? 0);
$token     = (string)($_GET['k'] ?? '');

PluginRpAccess::checkUse('mobile');

$qr_valid = $ticket_id > 0 && PluginRpQrcode::checkTicketToken($ticket_id, $token);

$ticket = new Ticket();
$ticket_ok = $qr_valid && $ticket->getFromDB($ticket_id) && $ticket->canViewItem();

Html::popHeader(__('Intervention mobile', 'rp'));

echo '<link rel="stylesheet" href="' . PLUGIN_RP_WEBDIR . '/public/css/signature_rp.css?r=' . PLUGIN_RP_ASSETS_REV . '">';
echo '<script src="' . PLUGIN_RP_WEBDIR . '/public/js/scripts_rp.js?r=' . PLUGIN_RP_ASSETS_REV . '" defer></script>';

if (!$qr_valid) {
   echo "<div class='rp-mobile-wrap'><div class='alert alert-danger m-3'>";
   echo "<i class='fa-solid fa-triangle-exclamation me-2'></i>";
   echo __("QR code invalide ou lien expiré. Régénérez le rapport de préparation pour obtenir un nouveau QR code.", 'rp');
   echo "</div></div>";
   Html::popFooter();
   exit;
}
if (!$ticket_ok) {
   echo "<div class='rp-mobile-wrap'><div class='alert alert-danger m-3'>";
   echo "<i class='fa-solid fa-lock me-2'></i>";
   echo __("Vous n'avez pas accès à ce ticket.", 'rp');
   echo "</div></div>";
   Html::popFooter();
   exit;
}

// ---- Données affichées : uniquement l'essentiel ----
$entity_name = Dropdown::getDropdownName('glpi_entities', (int)$ticket->fields['entities_id']);
$status_name = Ticket::getStatus((int)$ticket->fields['status']);
$prep        = PluginRpPreparation::getForTicket($ticket_id);

// Matériel : données du rapport de préparation, sinon détection automatique
$auto     = PluginRpTicketInfo::detect($ticket_id);
$mat_marque = trim((string)($prep['marque'] ?? '')) !== '' ? (string)$prep['marque'] : (string)$auto['marque'];
$mat_serial = trim((string)($prep['serial'] ?? '')) !== '' ? (string)$prep['serial'] : (string)$auto['serial'];

$materiel = trim($mat_marque);
if (trim($mat_serial) !== '') {
   $materiel .= ($materiel !== '' ? ' — ' : '') . 'S/N ' . $mat_serial;
}

// BL éventuel via le plugin Gestion (même requête que l'onglet ticket)
$gestion_bl_id  = 0;
$gestion_signed = null;
if (Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri')) {
   $bl_row = $DB->request([
      'SELECT' => ['id', 'signed'],
      'FROM'   => 'glpi_plugin_gestion_surveys',
      'WHERE'  => ['tickets_id' => $ticket_id],
      'ORDER'  => ['signed ASC', 'id DESC'],
      'LIMIT'  => 1,
   ])->current();
   if ($bl_row) {
      $gestion_signed = (int)$bl_row['signed'];
      if ($gestion_signed === 0) {
         $gestion_bl_id = (int)$bl_row['id'];
      }
   }
}

$rp_params = ['job' => $ticket_id, 'root_doc' => PLUGIN_RP_WEBDIR];

// Bouton « Livré — faire signer » : flux combiné Gestion si BL non signé, sinon rapport seul
if ($gestion_bl_id > 0) {
   $gestion_webdir = defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion');
   $gestion_params = ['job' => $ticket_id, 'root_doc' => $gestion_webdir, 'root_modal' => 'rp-mobile-modal'];
   $sign_onclick   = "gestion_loadCriForm('showCriForm', '$gestion_bl_id', " . json_encode($gestion_params) . "); return false;";
   echo '<script src="' . ($gestion_webdir) . '/public/js/scripts_gestion.js" defer></script>';
   echo "<script>window.GLPI_PLUG_RP = '" . $gestion_webdir . "';</script>";
} else {
   $sign_onclick = "rp_loadCriForm('showCriForm', 'form_rapport', " . json_encode($rp_params) . "); return false;";
}

// « Compléter l'intervention » : on bascule sur le parcours natif GLPI —
// ouverture du ticket avec le formulaire de tâche déplié, puis signature
// automatique du rapport une fois la tâche enregistrée (cf. public/js/fab_rp.js).
$complete_url = $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $ticket_id . '&rp_action=newtask';

echo "<div class='rp-mobile-wrap'>";

   echo "<div class='rp-mobile-card'>";
      echo "<div class='rp-mobile-ticket'>Ticket #" . sprintf('%07d', $ticket_id) . "</div>";
      echo "<div class='rp-mobile-title'>" . htmlspecialchars($ticket->fields['name'] ?? '', ENT_QUOTES) . "</div>";
      echo "<table class='rp-mobile-infos'>";
         echo "<tr><td>Client</td><td>" . htmlspecialchars($entity_name, ENT_QUOTES) . "</td></tr>";
         if ($materiel !== '') {
            echo "<tr><td>Matériel</td><td>" . htmlspecialchars($materiel, ENT_QUOTES) . "</td></tr>";
         }
         echo "<tr><td>Statut</td><td>" . htmlspecialchars($status_name, ENT_QUOTES) . "</td></tr>";
         if ($gestion_signed !== null) {
            echo "<tr><td>Bon de livraison</td><td>" . ($gestion_signed === 0
               ? "<span class='badge bg-warning text-dark'>BL à faire signer</span>"
               : "<span class='badge bg-success'>BL signé</span>") . "</td></tr>";
         } else {
            echo "<tr><td>Bon de livraison</td><td><span class='text-muted'>Aucun BL associé</span></td></tr>";
         }
      echo "</table>";
   echo "</div>";

   echo "<div class='rp-mobile-actions'>";
      echo "<a class='rp-mobile-btn rp-mobile-btn-complete' href='" . $complete_url . "'>";
      echo "<i class='fa-solid fa-list-check'></i> " . __("Compléter l'intervention", 'rp');
      echo "</a>";
      echo "<button type='button' class='rp-mobile-btn rp-mobile-btn-sign' onclick=\"$sign_onclick\">";
      echo "<i class='fa-solid fa-file-signature'></i> " . __('Livré — faire signer', 'rp');
      echo "</button>";
   echo "</div>";

   // conteneurs des modals (RP + Gestion)
   echo "<div id='form_rapport' style='display:none;'></div>";
   echo "<div id='rp-mobile-modal'></div>";

echo "</div>";

Html::popFooter();
