<?php
/**
 * Actions de signature disponibles pour un ticket (bouton flottant du ticket).
 *
 * Le calcul lui-même vit dans PluginRpTicketActions : les résultats du scanner
 * s'appuient sur la même source, pour que les deux entrées proposent
 * exactement les mêmes possibilités, filtrées par les mêmes droits.
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

$payload = PluginRpTicketActions::build($ticket_id);
if ($payload === null) {
   rp_ticket_actions_end(['ok' => false, 'error' => 'forbidden'], 403);
}

rp_ticket_actions_end($payload);
