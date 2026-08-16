<?php
/**
 * Actions de signature disponibles pour un ticket (bouton flottant du ticket).
 *
 * La liste s'adapte au contenu réel du ticket et aux plugins actifs :
 *   - tâches présentes            -> rapport d'intervention
 *   - aucune tâche                -> fiche de prise en charge
 *   - BL associé non signé        -> signature du BL (plugin Gestion)
 *   - BL + tâches                 -> modal combiné « BL + rapport » de Gestion
 *
 * Fonctionne avec le plugin RP seul, le plugin Gestion seul, ou les deux :
 * chaque action est filtrée par les droits et par la présence du plugin.
 *
 * Entrée : ticket_id
 * Sortie : { ok, ticket_id, has_tasks, actions: [ { key, label, icon, mode, ... } ] }
 */

// Le chargement des plugins peut émettre du HTML : on le neutralise pour
// garantir une réponse JSON valide.
ob_start();
include('../../../inc/includes.php');
while (ob_get_level() > 0) {
   ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

function rp_ticket_actions_end(array $payload, int $status = 200): void {
   while (ob_get_level() > 0) {
      ob_end_clean();
   }
   http_response_code($status);
   echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   exit;
}

$ticket_id = (int)($_POST['ticket_id'] ?? $_GET['ticket_id'] ?? 0);
if ($ticket_id <= 0) {
   rp_ticket_actions_end(['ok' => false, 'error' => 'missing_ticket_id'], 422);
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticket_id) || !$ticket->canViewItem()) {
   rp_ticket_actions_end(['ok' => false, 'error' => 'forbidden'], 403);
}

// ---- État du ticket ------------------------------------------------------
$config     = PluginRpConfig::getInstance();
$publiconly = (int)($config->fields['use_publictask'] ?? 0) === 1;

$task_criteria = ['tickets_id' => $ticket_id];
if ($publiconly) {
   $task_criteria['is_private'] = 0;
}
$nb_tasks = countElementsInTable('glpi_tickettasks', $task_criteria);

// ---- Plugin Gestion : BL associé ----------------------------------------
$gestion_active = Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri');
$gestion_webdir = $gestion_active
   ? (defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion'))
   : '';
$can_sign_bl = $gestion_active && Session::haveRight('plugin_gestion_survey', READ);

$bl = null;
if ($can_sign_bl && $DB->tableExists('glpi_plugin_gestion_surveys')) {
   $row = $DB->request([
      'SELECT' => ['id', 'bl', 'signed'],
      'FROM'   => 'glpi_plugin_gestion_surveys',
      'WHERE'  => ['tickets_id' => $ticket_id],
      'ORDER'  => ['signed ASC', 'id DESC'],
      'LIMIT'  => 1,
   ])->current();
   if ($row) {
      $bl = [
         'id'     => (int)$row['id'],
         'name'   => (string)$row['bl'],
         'signed' => (int)$row['signed'],
      ];
   }
}

// ---- Droits RP -----------------------------------------------------------
$can_report      = PluginRpAccess::canUse('rapport_tech', CREATE);
$can_hotline     = PluginRpAccess::canUse('rapport_hotline', CREATE);
$can_preparation = PluginRpAccess::canUse('preparation', CREATE);

$actions = [];

/**
 * Les actions « modal » sont ouvertes par les fonctions JS déjà utilisées par
 * l'onglet du ticket : rp_loadCriForm() (plugin RP) et gestion_loadCriForm()
 * (plugin Gestion). Aucune logique de signature n'est réécrite ici.
 */

// BL non signé + tâches : modal combiné « Rapport + BL »
// (le modal de Gestion propose ensuite le choix en haut)
if ($bl !== null && $bl['signed'] === 0 && $nb_tasks > 0 && $can_report) {
   $actions[] = [
      'key'     => 'combined',
      'label'   => __('Signer le BL + le rapport', 'rp'),
      'hint'    => $bl['name'],
      'icon'    => 'ti ti-file-signature',
      'mode'    => 'gestion',
      'bl_id'   => $bl['id'],
      'primary' => true,
   ];
}

// BL non signé : toujours proposé seul, y compris quand le combiné est possible
if ($bl !== null && $bl['signed'] === 0) {
   $actions[] = [
      'key'     => 'bl',
      'label'   => __('Signer le bon de livraison', 'rp'),
      'hint'    => $bl['name'],
      'icon'    => 'ti ti-signature',
      'mode'    => 'gestion',
      'bl_only' => true,
      'bl_id'   => $bl['id'],
      'primary' => ($nb_tasks === 0 || !$can_report),
   ];
}

// Rapport d'intervention (nécessite au moins une tâche)
if ($can_report && $nb_tasks > 0) {
   $actions[] = [
      'key'     => 'rapport',
      'label'   => __("Signer le rapport d'intervention", 'rp'),
      'hint'    => sprintf(_n('%d tâche', '%d tâches', $nb_tasks, 'rp'), $nb_tasks),
      'icon'    => 'ti ti-file-check',
      'mode'    => 'rp',
      'modal'   => 'form_rapport',
      'primary' => ($bl === null || $bl['signed'] === 1),
   ];
}

// Fiche de prise en charge (pas de tâche : le rapport n'est pas générable)
if ($can_report && $nb_tasks === 0) {
   $actions[] = [
      'key'     => 'prise_en_charge',
      'label'   => __('Signer la fiche de prise en charge', 'rp'),
      'hint'    => __('Aucune tâche sur ce ticket', 'rp'),
      'icon'    => 'ti ti-file-text',
      'mode'    => 'rp',
      'modal'   => 'form_client',
      'primary' => ($bl === null),
   ];
}

// Rapport hotline
if ($can_hotline && $nb_tasks > 0) {
   $actions[] = [
      'key'     => 'hotline',
      'label'   => __('Signer le rapport hotline', 'rp'),
      'icon'    => 'ti ti-headset',
      'mode'    => 'rp',
      'modal'   => 'form_rapport_hotline',
      'primary' => false,
   ];
}

// Rapport de préparation (atelier)
if ($can_preparation) {
   $actions[] = [
      'key'     => 'preparation',
      'label'   => __("Rapport d'atelier", 'rp'),
      'icon'    => 'ti ti-tools',
      'mode'    => 'rp',
      'modal'   => 'form_preparation',
      'primary' => false,
   ];
}

// BL déjà signé : simple information
$notice = '';
if ($bl !== null && $bl['signed'] === 1) {
   $notice = sprintf(__('Bon de livraison %s déjà signé.', 'rp'), $bl['name']);
}

rp_ticket_actions_end([
   'ok'            => true,
   'ticket_id'     => $ticket_id,
   'ticket_name'   => (string)$ticket->fields['name'],
   'has_tasks'     => $nb_tasks > 0,
   'notice'        => $notice,
   'gestion_webdir' => $gestion_webdir,
   'rp_webdir'     => PLUGIN_RP_WEBDIR,
   'actions'       => $actions,
]);
