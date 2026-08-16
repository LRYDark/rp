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

function rp_prepare_bool(mixed $value): bool
{
   if (is_bool($value)) {
      return $value;
   }
   $txt = strtolower(trim((string)$value));
   return in_array($txt, ['1', 'true', 'yes', 'on'], true);
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
      'preparation_report', 'rapport_preparation', 'rapport_de_preparation', 'preparation', 'atelier', 'formpreparation' => [
         'api'            => 'preparation_report',
         'form'           => 'FormPreparation',
         'type_id'        => 3,
         'tasks_required' => false,
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

function rp_prepare_fetch_tasks(DBmysql $DB, int $ticket_id, bool $public_only): array
{
   $extra = $public_only ? "AND tt.is_private = 0" : "";
   $res = $DB->doQuery("SELECT tt.id, tt.content, tt.date, tt.actiontime, tt.is_private, u.name, u.realname, u.firstname
                        FROM `glpi_tickettasks` tt
                        LEFT JOIN `glpi_users` u ON u.id = tt.users_id
                        WHERE tt.tickets_id = $ticket_id $extra
                        ORDER BY tt.date ASC, tt.id ASC");
   if (!$res) {
      return [];
   }

   $out = [];
   while ($row = $DB->fetchassoc($res)) {
      $author_label = trim((string)($row['realname'] ?? '') . ' ' . (string)($row['firstname'] ?? ''));
      if ($author_label === '') {
         $author_label = (string)($row['name'] ?? '');
      }
      $out[] = [
         'id'         => (int)($row['id'] ?? 0),
         'content'    => (string)($row['content'] ?? ''),
         'date'       => (string)($row['date'] ?? ''),
         'actiontime' => (int)($row['actiontime'] ?? 0),
         'time'       => (int)($row['actiontime'] ?? 0), // alias legacy kiosk
         'is_private' => (int)($row['is_private'] ?? 0),
         'author'     => $author_label,
         'author_login' => (string)($row['name'] ?? ''),
      ];
   }
   return $out;
}

function rp_prepare_fetch_followups(DBmysql $DB, int $ticket_id, bool $public_only): array
{
   $extra = $public_only ? "AND f.is_private = 0" : "";
   $res = $DB->doQuery("SELECT f.id, f.content, f.date, f.is_private, u.name, u.realname, u.firstname
                        FROM `glpi_itilfollowups` f
                        LEFT JOIN `glpi_users` u ON u.id = f.users_id
                        WHERE f.items_id = $ticket_id
                          AND (f.itemtype = 'Ticket' OR f.itemtype IS NULL OR f.itemtype = '')
                          $extra
                        ORDER BY f.date ASC, f.id ASC");
   if (!$res) {
      return [];
   }

   $out = [];
   while ($row = $DB->fetchassoc($res)) {
      $author_label = trim((string)($row['realname'] ?? '') . ' ' . (string)($row['firstname'] ?? ''));
      if ($author_label === '') {
         $author_label = (string)($row['name'] ?? '');
      }
      $out[] = [
         'id'         => (int)($row['id'] ?? 0),
         'content'    => (string)($row['content'] ?? ''),
         'date'       => (string)($row['date'] ?? ''),
         'is_private' => (int)($row['is_private'] ?? 0),
         'author'     => $author_label,
         'author_login' => (string)($row['name'] ?? ''),
      ];
   }
   return $out;
}

function rp_prepare_guess_client_email(DBmysql $DB, int $ticket_id, int $entity_id): string
{
   try {
      if ($DB->tableExists('glpi_plugin_rp_dataclient')) {
         $res_data = $DB->doQuery("SELECT email FROM `glpi_plugin_rp_dataclient`
                                   WHERE id_ticket = $ticket_id
                                   ORDER BY id DESC
                                   LIMIT 1");
         if ($res_data && $DB->numrows($res_data) === 1) {
            $row_data = $DB->fetchassoc($res_data);
            $email = trim((string)($row_data['email'] ?? ''));
            if ($email !== '') {
               return $email;
            }
         }
      }
   } catch (Throwable $e) {
      // non bloquant
   }

   try {
      if ($entity_id > 0) {
         $res_entity = $DB->doQuery("SELECT email FROM `glpi_entities` WHERE id = $entity_id LIMIT 1");
         if ($res_entity && $DB->numrows($res_entity) === 1) {
            $row_entity = $DB->fetchassoc($res_entity);
            $email = trim((string)($row_entity['email'] ?? ''));
            if ($email !== '') {
               return $email;
            }
         }
      }
   } catch (Throwable $e) {
      // non bloquant
   }

   try {
      $sql = "SELECT email FROM (
                 SELECT DISTINCT ue.email AS email
                 FROM `glpi_tickets_users` tu
                 INNER JOIN `glpi_useremails` ue ON ue.users_id = tu.users_id
                 WHERE tu.tickets_id = $ticket_id
                   AND ue.email IS NOT NULL
                   AND ue.email <> ''
                 UNION
                 SELECT DISTINCT e.email AS email
                 FROM `glpi_entities` e
                 WHERE e.id = $entity_id
                   AND e.email IS NOT NULL
                   AND e.email <> ''
              ) x
              LIMIT 1";
      $res = $DB->doQuery($sql);
      if ($res && $DB->numrows($res) === 1) {
         $row = $DB->fetchassoc($res);
         return trim((string)($row['email'] ?? ''));
      }
   } catch (Throwable $e) {
      // non bloquant
   }

   return '';
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
$include_tasks = rp_prepare_bool($input['include_tasks'] ?? $input['with_tasks'] ?? '0');
$include_followups_details = rp_prepare_bool($input['include_followups_details'] ?? $input['with_followups'] ?? '0');
$include_client_email = rp_prepare_bool($input['include_client_email'] ?? '0');

if ($ticket_id <= 0) {
   rp_prepare_end(422, ['ok' => false, 'error' => 'missing_ticket_id']);
}

// Règles d'accès RP (droit profil + liste allow/deny) sur l'utilisateur API authentifié
$rp_features_by_type = [0 => 'rapport_tech', 1 => 'rapport_tech', 2 => 'rapport_hotline', 3 => 'preparation'];
$rp_feature = $rp_features_by_type[(int)$doc_type['type_id']] ?? 'rapport_tech';
if (!PluginRpAccess::canUse($rp_feature)) {
   rp_prepare_end(403, [
      'ok'      => false,
      'error'   => 'rp_access_denied',
      'message' => "Accès refusé par les droits ou les règles d'accès du plugin RP",
   ]);
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticket_id)) {
   rp_prepare_end(404, ['ok' => false, 'error' => 'ticket_not_found', 'ticket_id' => $ticket_id]);
}

$public_only = (int)($config->fields['use_publictask'] ?? 0) === 1;
$task_count = rp_prepare_task_count($DB, $ticket_id, $public_only);
$can_generate = !$doc_type['tasks_required'] || $task_count > 0;

$last_docs = rp_prepare_last_documents($DB, $ticket_id, $rootdoc);
$entity_id = (int)($ticket->fields['entities_id'] ?? 0);
$entity_name = '';
if ($entity_id > 0) {
   $entity = new Entity();
   if ($entity->getFromDB($entity_id)) {
      $entity_name = (string)($entity->fields['name'] ?? '');
   }
}

$tasks = $include_tasks ? rp_prepare_fetch_tasks($DB, $ticket_id, $public_only) : null;
$followups = $include_followups_details ? rp_prepare_fetch_followups($DB, $ticket_id, $public_only) : null;
$client_email = $include_client_email ? rp_prepare_guess_client_email($DB, $ticket_id, $entity_id) : '';

rp_prepare_end(200, [
   'ok'                => true,
   'ticket_id'         => $ticket_id,
   'ticket_name'       => (string)($ticket->fields['name'] ?? ''),
   'ticket_title'      => (string)($ticket->fields['name'] ?? ''), // alias legacy/tablette
   'ticket_description'=> (string)($ticket->fields['content'] ?? ''),
   'entity_id'         => $entity_id,
   'entity_name'       => $entity_name,
   'client_email'      => $client_email !== '' ? $client_email : null,
   'document_type'     => (string)$doc_type['api'],
   'glpi_form'         => (string)$doc_type['form'],
   'tasks_required'    => (bool)$doc_type['tasks_required'],
   'tasks_count'       => $task_count,
   'tasks'             => $tasks,
   'followups'         => $followups,
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
      'include_tasks (prepare only)',
      'include_followups_details (prepare only)',
      'include_client_email (prepare only)',
   ],
]);
