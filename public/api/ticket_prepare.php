<?php
$_cors_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$_cors_allowed = getenv('GLPI_API_CORS_ORIGIN') ?: '*';
if ($_cors_allowed === '*' || $_cors_origin === $_cors_allowed) {
    header('Access-Control-Allow-Origin: ' . ($_cors_allowed === '*' ? '*' : $_cors_origin));
} else {
    header('Access-Control-Allow-Origin: ' . $_cors_allowed);
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, App-Token, Session-Token, User-Token, X-App-Token, Glpi-Entity, Glpi-Entity-Recursive, Glpi-Profile');
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
   http_response_code(200);
   exit;
}

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', realpath(__DIR__ . '/../../..'));
}

if (!defined('PLUGIN_RP_DIR')) {
   define('PLUGIN_RP_DIR', realpath(__DIR__ . '/../..'));
}

define('NOLOGIN', 1);
define('NOHEADER', 1);
define('NOTOKENRENEWAL', 1);

require GLPI_ROOT . '/inc/includes.php';
require_once PLUGIN_RP_DIR . '/inc/apiauth.class.php';

global $DB, $CFG_GLPI;
$config = PluginRpConfig::getInstance();
$rootdoc = rtrim((string)($CFG_GLPI['root_doc'] ?? '/glpi'), '/');

function rp_prepare_end(int $status, array $payload): void
{
   if (!headers_sent()) {
      header('Content-Type: application/json; charset=UTF-8');
   }
   http_response_code($status);
   echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   exit;
}

function rp_prepare_input(): array
{
   $input = array_merge($_GET, $_POST);
   $content_type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
   if (str_contains($content_type, 'application/json')) {
      $raw = PluginRpApiAuth::getRawInputBody();
      if ($raw !== '') {
         $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
         $json = json_decode($raw, true);
         if (is_array($json)) {
            $input = array_merge($input, $json);
         }
      }
   }
   return $input;
}

function rp_prepare_doc_type(string $raw): array
{
   $normalized = strtolower(trim($raw));
   $normalized = str_replace([' ', '-'], '_', $normalized);

   return match ($normalized) {
      'charge_sheet', 'prise_en_charge', 'fiche_prise_en_charge', 'fiche', 'formclient' => [
         'api'            => 'charge_sheet',
         'form'           => 'FormClient',
         'type_id'        => 0,
         'tasks_required' => false,
      ],
      'hotline_report', 'rapport_hotline', 'hotline', 'formrapporthotline' => [
         'api'            => 'hotline_report',
         'form'           => 'FormRapportHotline',
         'type_id'        => 2,
         'tasks_required' => true,
      ],
      default => [
         'api'            => 'intervention_report',
         'form'           => 'FormRapport',
         'type_id'        => 1,
         'tasks_required' => true,
      ],
   };
}

function rp_prepare_task_count(DBmysql $DB, int $ticket_id, bool $public_only): int
{
   $extra = $public_only ? "AND is_private = 0" : "";
   $res = $DB->doQuery("SELECT COUNT(*) AS nb
                        FROM `glpi_tickettasks`
                        WHERE tickets_id = $ticket_id $extra");
   if (!$res || $DB->numrows($res) !== 1) {
      return 0;
   }
   $row = $DB->fetchassoc($res);
   return (int)($row['nb'] ?? 0);
}

function rp_prepare_last_documents(DBmysql $DB, int $ticket_id, string $rootdoc): array
{
   $out = [
      'charge_sheet'        => null,
      'intervention_report' => null,
      'hotline_report'      => null,
   ];

   $map = [
      0 => 'charge_sheet',
      1 => 'intervention_report',
      2 => 'hotline_report',
   ];

   $res = $DB->doQuery("SELECT c.id, c.id_documents, c.type, c.date, c.users_id, c.nameclient, c.email,
                               d.filename
                        FROM `glpi_plugin_rp_cridetails` c
                        LEFT JOIN `glpi_documents` d ON d.id = c.id_documents
                        WHERE c.id_ticket = $ticket_id
                        ORDER BY c.date DESC, c.id DESC
                        LIMIT 50");
   if (!$res) {
      return $out;
   }

   while ($row = $DB->fetchassoc($res)) {
      $type_id = (int)($row['type'] ?? -1);
      if (!array_key_exists($type_id, $map)) {
         continue;
      }
      $key = $map[$type_id];
      if ($out[$key] !== null) {
         continue;
      }
      $doc_id = (int)($row['id_documents'] ?? 0);
      $out[$key] = [
         'cridetail_id'  => (int)($row['id'] ?? 0),
         'id_documents'  => $doc_id,
         'filename'      => (string)($row['filename'] ?? ''),
         'date'          => (string)($row['date'] ?? ''),
         'users_id'      => (int)($row['users_id'] ?? 0),
         'nameclient'    => (string)($row['nameclient'] ?? ''),
         'email'         => (string)($row['email'] ?? ''),
         'document_url'  => $doc_id > 0 ? ($rootdoc . '/front/document.send.php?docid=' . $doc_id) : '',
      ];
   }

   return $out;
}

if (!in_array((string)($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
   rp_prepare_end(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$auth = PluginRpApiAuth::authenticateRequest(false);
if (empty($auth['ok'])) {
   rp_prepare_end((int)($auth['code'] ?? 401), [
      'ok'      => false,
      'error'   => (string)($auth['error'] ?? 'auth_failed'),
      'message' => (string)($auth['message'] ?? 'Authentification API invalide'),
   ]);
}

$input = rp_prepare_input();
$ticket_id = (int)($input['ticket_id'] ?? $input['id'] ?? 0);
$doc_type = rp_prepare_doc_type((string)($input['document_type'] ?? $input['type'] ?? ''));

if ($ticket_id <= 0) {
   rp_prepare_end(422, ['ok' => false, 'error' => 'missing_ticket_id']);
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticket_id)) {
   rp_prepare_end(404, ['ok' => false, 'error' => 'ticket_not_found', 'ticket_id' => $ticket_id]);
}

$public_only = (int)($config->fields['use_publictask'] ?? 0) === 1;
$task_count = rp_prepare_task_count($DB, $ticket_id, $public_only);
$can_generate = !$doc_type['tasks_required'] || $task_count > 0;

$last_docs = rp_prepare_last_documents($DB, $ticket_id, $rootdoc);

rp_prepare_end(200, [
   'ok'                => true,
   'ticket_id'         => $ticket_id,
   'ticket_name'       => (string)($ticket->fields['name'] ?? ''),
   'ticket_description'=> (string)($ticket->fields['content'] ?? ''),
   'document_type'     => (string)$doc_type['api'],
   'glpi_form'         => (string)$doc_type['form'],
   'tasks_required'    => (bool)$doc_type['tasks_required'],
   'tasks_count'       => $task_count,
   'can_generate'      => $can_generate,
   'last_documents'    => $last_docs,
   'technician_login'  => (string)($auth['tech_login'] ?? ''),
   'technician_label'  => (string)($auth['tech_label'] ?? ''),
   'required_fields'   => ['ticket_id', 'document_type'],
   'optional_fields'   => [
      'signer_name',
      'signer_email',
      'mail_to_client',
      'signature',
      'task_ids',
      'followup_ids',
      'include_followups',
      'show_total_time',
      'include_task_images',
      'include_followup_images',
      'description',
      'entity_group',
   ],
]);
