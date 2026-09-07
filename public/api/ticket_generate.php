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

function rp_generate_end(int $status, array $payload): void
{
   if (!headers_sent()) {
      header('Content-Type: application/json; charset=UTF-8');
   }
   http_response_code($status);
   echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   exit;
}

function rp_generate_bool(mixed $value): bool
{
   if (is_bool($value)) {
      return $value;
   }
   $txt = strtolower(trim((string)$value));
   return in_array($txt, ['1', 'true', 'yes', 'on'], true);
}

function rp_generate_input(): array
{
   $input = $_POST;
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

function rp_generate_doc_type(string $raw): array
{
   $normalized = strtolower(trim($raw));
   $normalized = str_replace([' ', '-'], '_', $normalized);

   return match ($normalized) {
      '', 'intervention_report', 'rapport_intervention', 'report', 'rapport', 'formrapport' => [
         'api'            => 'intervention_report',
         'form'           => 'FormRapport',
         'type_id'        => 1,
         'tasks_required' => true,
      ],
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
      default => [],
   };
}

function rp_generate_parse_ids(mixed $value): array
{
   if (is_array($value)) {
      $raw = $value;
   } else {
      $txt = trim((string)$value);
      if ($txt === '') {
         return [];
      }
      $raw = preg_split('/[\s,;]+/', $txt) ?: [];
   }

   $out = [];
   foreach ($raw as $item) {
      $id = (int)$item;
      if ($id > 0) {
         $out[$id] = $id;
      }
   }
   return array_values($out);
}

function rp_generate_fetch_tasks(DBmysql $DB, int $ticket_id, bool $public_only): array
{
   $extra = $public_only ? "AND tt.is_private = 0" : "";
   $res = $DB->doQuery("SELECT tt.id, tt.content, tt.date, tt.actiontime, tt.is_private, u.name
                        FROM `glpi_tickettasks` tt
                        LEFT JOIN `glpi_users` u ON u.id = tt.users_id
                        WHERE tt.tickets_id = $ticket_id $extra
                        ORDER BY tt.date ASC, tt.id ASC");

   $out = [];
   if (!$res) {
      return $out;
   }

   while ($row = $DB->fetchassoc($res)) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
         continue;
      }
      $out[$id] = [
         'id'         => $id,
         'content'    => (string)($row['content'] ?? ''),
         'date'       => (string)($row['date'] ?? ''),
         'actiontime' => (int)($row['actiontime'] ?? 0),
         'is_private' => (int)($row['is_private'] ?? 0),
         'name'       => (string)($row['name'] ?? ''),
      ];
   }
   return $out;
}

function rp_generate_fetch_followups(DBmysql $DB, int $ticket_id, bool $public_only): array
{
   $extra = $public_only ? "AND f.is_private = 0" : "";
   $res = $DB->doQuery("SELECT f.id, f.content, f.date, f.is_private, u.name
                        FROM `glpi_itilfollowups` f
                        LEFT JOIN `glpi_users` u ON u.id = f.users_id
                        WHERE f.items_id = $ticket_id
                          AND (f.itemtype = 'Ticket' OR f.itemtype IS NULL OR f.itemtype = '')
                          $extra
                        ORDER BY f.date ASC, f.id ASC");

   $out = [];
   if (!$res) {
      return $out;
   }

   while ($row = $DB->fetchassoc($res)) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
         continue;
      }
      $out[$id] = [
         'id'      => $id,
         'content' => (string)($row['content'] ?? ''),
         'date'    => (string)($row['date'] ?? ''),
         'name'    => (string)($row['name'] ?? ''),
         'is_private' => (int)($row['is_private'] ?? 0),
      ];
   }
   return $out;
}

function rp_generate_default_selection(array $items, PluginRpConfig $config, string $kind): array
{
   if ((int)($config->fields['choice'] ?? 0) !== 1) {
      return $items;
   }

   if ($kind === 'task') {
      $allow_public = (int)($config->fields['check_public_task'] ?? 0) === 1;
      $allow_private = (int)($config->fields['check_private_task'] ?? 0) === 1;
   } else {
      $allow_public = (int)($config->fields['check_public_suivi'] ?? 0) === 1;
      $allow_private = (int)($config->fields['check_private_suivi'] ?? 0) === 1;
   }

   $selected = [];
   foreach ($items as $id => $row) {
      $is_private = (int)($row['is_private'] ?? 0) === 1;
      if (($is_private && $allow_private) || (!$is_private && $allow_public)) {
         $selected[$id] = $row;
      }
   }

   return $selected;
}

/*
 * Ancien choix entre les deux chartes figées, conservé UNIQUEMENT pour les
 * bases où la table des chartes n'existe pas encore : le repli de
 * PluginRpCharte relit dans ce cas `entity_parrent1` / `entity_parrent2` dans
 * le POST, il faut donc continuer à lui envoyer ces valeurs-là. Dès qu'une
 * charte existe, c'est son identifiant qui circule et cette fonction n'est plus
 * appelée.
 */
function rp_generate_resolve_entity_group(
   DBmysql $DB,
   array $ticket_fields,
   PluginRpConfig $config,
   string $requested
): string {
   $requested = strtolower(trim($requested));
   if (in_array($requested, ['entity_parrent1', 'entity_parrent2'], true)) {
      return $requested;
   }

   $p1 = (int)($config->fields['entity_parrent1'] ?? 0);
   $p2 = (int)($config->fields['entity_parrent2'] ?? 0);

   if ($p1 <= 0 && $p2 <= 0) {
      return 'entity_parrent1';
   }
   if ($p1 > 0 && $p2 <= 0) {
      return 'entity_parrent1';
   }
   if ($p1 <= 0 && $p2 > 0) {
      return 'entity_parrent2';
   }

   $entity_id = (int)($ticket_fields['entities_id'] ?? 0);
   if ($entity_id <= 0) {
      return 'entity_parrent1';
   }

   $entity_row = $DB->doQuery("SELECT completename FROM `glpi_entities` WHERE id = $entity_id LIMIT 1");
   if (!$entity_row || $DB->numrows($entity_row) !== 1) {
      return 'entity_parrent1';
   }
   $entity_data = $DB->fetchassoc($entity_row);
   $complete = (string)($entity_data['completename'] ?? '');
   if ($complete === '') {
      return 'entity_parrent1';
   }
   $parts = array_map('trim', explode('>', $complete));

   $p1_name_row = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $p1 LIMIT 1");
   $p2_name_row = $DB->doQuery("SELECT name FROM `glpi_entities` WHERE id = $p2 LIMIT 1");
   $p1_name = ($p1_name_row && $DB->numrows($p1_name_row) === 1) ? (string)($DB->fetchassoc($p1_name_row)['name'] ?? '') : '';
   $p2_name = ($p2_name_row && $DB->numrows($p2_name_row) === 1) ? (string)($DB->fetchassoc($p2_name_row)['name'] ?? '') : '';

   if ($p2_name !== '' && in_array($p2_name, $parts, true)) {
      return 'entity_parrent2';
   }
   if ($p1_name !== '' && in_array($p1_name, $parts, true)) {
      return 'entity_parrent1';
   }
   return 'entity_parrent1';
}

/**
 * Charte du rapport demandée par l'appelant, sinon celle de l'entité du ticket.
 *
 * Les chaînes historiques `entity_parrent1` / `entity_parrent2` sont traitées à
 * part : l'application technicien déjà déployée les envoie encore, or
 * PluginRpCharte::resolve() les lit comme l'identifiant 0 et retomberait sur
 * l'entité, en ignorant sans le dire le choix explicite de l'appelant.
 *
 * @param mixed $requested   valeur reçue dans le payload (identifiant ou ancienne chaîne)
 * @param int   $entities_id entité du ticket, pour la présélection
 */
function rp_generate_resolve_charte(mixed $requested, int $entities_id): ?array
{
   $txt = strtolower(trim(is_scalar($requested) ? (string)$requested : ''));

   if ($txt === 'entity_parrent1' || $txt === 'entity_parrent2') {
      /*
       * La migration 3.3.0 a repris la charte 1 au rang 1 et la charte 2 au
       * rang 2 : c'est ce rang, et lui seul, qui permet d'honorer encore
       * l'ancienne valeur après reprise.
       */
      $rank = ($txt === 'entity_parrent2') ? 2 : 1;
      foreach (PluginRpCharte::getAll() as $charte) {
         if ((int)($charte['rank'] ?? 0) === $rank) {
            return $charte;
         }
      }
      // Rang absent : l'ancienne charte n'avait jamais été configurée, donc pas
      // reprise. La présélection par entité vaut mieux qu'aucune charte.
      return PluginRpCharte::getForEntity($entities_id);
   }

   return PluginRpCharte::resolve($requested, $entities_id);
}

function rp_generate_call_cripdf(array $payload, string $rootdoc, array $cfg): array
{
   $url_base = trim((string)($cfg['url_base'] ?? ''));
   if ($url_base === '') {
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
      $url_base = $scheme . '://' . $host . rtrim($rootdoc, '/');
   }

   $target = rtrim($url_base, '/') . '/plugins/rp/front/cripdf.form.php';

   $fallback = static function () use ($payload): array {
      $backup_post = $_POST;
      $backup_get = $_GET;
      $cwd = getcwd();
      $response = '';

      $_POST = $payload;
      $_GET = [];

      ob_start();
      @chdir(PLUGIN_RP_DIR . '/front');
      /*
       * Marqueur lu par le générateur : il produit alors le document sans
       * décider de l'afficher ni de rediriger. L'API veut le PDF dans sa
       * réponse, quel que soit le réglage d'affichage, qui ne concerne que les
       * techniciens devant leur écran.
       */
      $GLOBALS['PLUGIN_RP_PDF_EMBEDDED'] = true;
      include PLUGIN_RP_DIR . '/front/cripdf.form.php';
      unset($GLOBALS['PLUGIN_RP_PDF_EMBEDDED']);
      $response = (string)ob_get_clean();

      if ($cwd !== false) {
         @chdir($cwd);
      }
      $_POST = $backup_post;
      $_GET = $backup_get;

      return [
         'ok'        => true,
         'http_code' => 200,
         'response'  => $response,
      ];
   };

   if (function_exists('curl_init')) {
      $headers = [
         'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
         'X-Requested-With: XMLHttpRequest',
      ];
      if (!empty($payload['_glpi_csrf_token'])) {
         $headers[] = 'X-Glpi-Csrf-Token: ' . (string)$payload['_glpi_csrf_token'];
      }

      $cookie = '';
      $sid = session_id();
      if ($sid !== '') {
         $cookie = session_name() . '=' . $sid;
      }

      $ch = curl_init($target);
      curl_setopt_array($ch, [
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_POST => true,
         CURLOPT_POSTFIELDS => http_build_query($payload),
         CURLOPT_HTTPHEADER => $headers,
         CURLOPT_TIMEOUT => 120,
         CURLOPT_CONNECTTIMEOUT => 10,
         CURLOPT_FOLLOWLOCATION => true,
      ]);
      if ($cookie !== '') {
         curl_setopt($ch, CURLOPT_COOKIE, $cookie);
      }

      $response = curl_exec($ch);
      if ($response === false) {
         $error = (string)curl_error($ch);
         curl_close($ch);
         try {
            $fallback_res = $fallback();
            $fallback_res['fallback'] = 'include_after_curl_error';
            $fallback_res['curl_error'] = $error;
            return $fallback_res;
         } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'curl_failed', 'message' => $error];
         }
      }

      $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return [
         'ok'        => ($http_code >= 200 && $http_code < 500),
         'http_code' => $http_code,
         'response'  => (string)$response,
      ];
   }

   return $fallback();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
   rp_generate_end(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$auth = PluginRpApiAuth::authenticateRequest(false);
if (empty($auth['ok'])) {
   rp_generate_end((int)($auth['code'] ?? 401), [
      'ok'      => false,
      'error'   => (string)($auth['error'] ?? 'auth_failed'),
      'message' => (string)($auth['message'] ?? 'Authentification API invalide'),
   ]);
}

$input = rp_generate_input();
$ticket_id = (int)($input['ticket_id'] ?? $input['id'] ?? 0);
if ($ticket_id <= 0) {
   rp_generate_end(422, ['ok' => false, 'error' => 'missing_ticket_id']);
}

$doc_type = rp_generate_doc_type((string)($input['document_type'] ?? $input['type'] ?? ''));
if (empty($doc_type)) {
   rp_generate_end(422, ['ok' => false, 'error' => 'invalid_document_type']);
}

// Règles d'accès RP (droit profil + liste allow/deny) sur l'utilisateur API authentifié
$rp_features_by_type = [0 => 'fiche', 1 => 'rapport_tech', 2 => 'rapport_hotline', 3 => 'preparation'];
$rp_feature = $rp_features_by_type[(int)$doc_type['type_id']] ?? 'rapport_tech';
if (!PluginRpAccess::canUse($rp_feature)) {
   rp_generate_end(403, [
      'ok'      => false,
      'error'   => 'rp_access_denied',
      'message' => "Accès refusé par les droits ou les règles d'accès du plugin RP",
   ]);
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticket_id)) {
   rp_generate_end(404, ['ok' => false, 'error' => 'ticket_not_found', 'ticket_id' => $ticket_id]);
}

$ticket_fields = $ticket->fields;
$public_only = (int)($config->fields['use_publictask'] ?? 0) === 1;

$tasks = rp_generate_fetch_tasks($DB, $ticket_id, $public_only);
$requested_task_ids = rp_generate_parse_ids($input['task_ids'] ?? []);
$selected_tasks = [];
if (!empty($requested_task_ids)) {
   foreach ($requested_task_ids as $task_id) {
      if (isset($tasks[$task_id])) {
         $selected_tasks[$task_id] = $tasks[$task_id];
      }
   }
} else {
   $selected_tasks = rp_generate_default_selection($tasks, $config, 'task');
}

if (!empty($doc_type['tasks_required']) && count($selected_tasks) === 0) {
   rp_generate_end(422, [
      'ok'         => false,
      'error'      => 'tasks_required',
      'message'    => 'Le rapport d intervention requiert au moins une tache',
      'ticket_id'  => $ticket_id,
      'task_count' => count($tasks),
   ]);
}

$include_followups = rp_generate_bool($input['include_followups'] ?? '1');
$followups = $include_followups ? rp_generate_fetch_followups($DB, $ticket_id, $public_only) : [];
$requested_followup_ids = rp_generate_parse_ids($input['followup_ids'] ?? []);
$selected_followups = [];
if ($include_followups) {
   if (!empty($requested_followup_ids)) {
      foreach ($requested_followup_ids as $fid) {
         if (isset($followups[$fid])) {
            $selected_followups[$fid] = $followups[$fid];
         }
      }
   } else {
      $selected_followups = rp_generate_default_selection($followups, $config, 'followup');
   }
}

$data_client = null;
$res_client = $DB->doQuery("SELECT * FROM `glpi_plugin_rp_dataclient` WHERE id_ticket = $ticket_id LIMIT 1");
if ($res_client && $DB->numrows($res_client) === 1) {
   $data_client = $DB->fetchassoc($res_client);
}

$entity_row = null;
$entity_id = (int)($ticket_fields['entities_id'] ?? 0);
if ($entity_id > 0) {
   $res_entity = $DB->doQuery("SELECT * FROM `glpi_entities` WHERE id = $entity_id LIMIT 1");
   if ($res_entity && $DB->numrows($res_entity) === 1) {
      $entity_row = $DB->fetchassoc($res_entity);
   }
}

$signer_name = trim((string)($input['signer_name'] ?? $input['name'] ?? ''));
$signer_email = trim((string)($input['signer_email'] ?? $input['email'] ?? ''));
$signature = (string)($input['signature'] ?? $input['url'] ?? '');
$mail_to_client = rp_generate_bool($input['mail_to_client'] ?? $input['mailtoclient'] ?? ($signer_email !== '' ? '1' : '0'));

if ($signer_name === '') {
   $signer_name = (string)($auth['tech_label'] ?? $auth['tech_login'] ?? '-');
}

$description = (string)($input['description'] ?? $input['DESCRIPTION_TICKET'] ?? (string)($ticket_fields['content'] ?? ''));
/*
 * Charte du rapport. Le corps JSON peut porter n'importe quel type, d'où la
 * réduction aux scalaires avant toute comparaison.
 */
$charte_requested = $input['charte_id'] ?? $input['charte'] ?? $input['entity_group'] ?? '';
$charte_requested = is_scalar($charte_requested) ? (string)$charte_requested : '';

$charte = rp_generate_resolve_charte($charte_requested, $entity_id);
PluginRpCharte::setCurrent($charte);

/*
 * Le générateur tourne dans une AUTRE requête (appel cURL vers cripdf.form.php) :
 * la charte fixée ci-dessus n'y survit pas, seul le POST la suit. `entity_parrent`
 * garde son nom historique et transporte désormais l'identifiant de la charte.
 *
 * Tant qu'aucune charte n'existe (migration pas encore jouée), on continue
 * d'envoyer l'ancienne valeur : c'est la seule que comprenne le repli sur les
 * colonnes numérotées de la configuration.
 */
$entity_group = $charte !== null
   ? (string)(int)$charte['id']
   : rp_generate_resolve_entity_group($DB, $ticket_fields, $config, $charte_requested);

$society = trim((string)($input['society'] ?? ''));
$town = trim((string)($input['town'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$postcode = trim((string)($input['postcode'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$serialnumber = trim((string)($input['serialnumber'] ?? ''));

if ($society === '') {
   $society = trim((string)($data_client['society'] ?? $entity_row['comment'] ?? $entity_row['completename'] ?? '-'));
}
if ($town === '') {
   $town = trim((string)($data_client['town'] ?? $entity_row['town'] ?? '-'));
}
if ($address === '') {
   $address = trim((string)($data_client['address'] ?? $entity_row['address'] ?? '-'));
}
if ($postcode === '') {
   $postcode = trim((string)($data_client['postcode'] ?? $entity_row['postcode'] ?? '0'));
}
if ($phone === '') {
   $phone = trim((string)($data_client['phone'] ?? $entity_row['phonenumber'] ?? '-'));
}
if ($serialnumber === '') {
   $serialnumber = trim((string)($data_client['serial_number'] ?? ''));
}
if ($signer_email === '') {
   $signer_email = trim((string)($input['client_email'] ?? $data_client['email'] ?? $entity_row['email'] ?? ''));
}

$technician_user_id = (int)($input['users_id_tech'] ?? 0);
if ($technician_user_id <= 0) {
   $technician_user_id = (int)($auth['user_id'] ?? 0);
}

$payload = [
   'REPORT_ID'             => (string)$ticket_id,
   'Form'                  => (string)$doc_type['form'],
   'entity_parrent'        => $entity_group,
   'name'                  => $signer_name !== '' ? $signer_name : '-',
   'email'                 => $signer_email,
   'mailtoclient'          => ($mail_to_client && $signer_email !== '') ? '1' : '0',
   'url'                   => $signature,
   'users_id_tech'         => (string)$technician_user_id,
   'CHECK_DESCRIPTION_TICKET' => 'check',
   'DESCRIPTION_TICKET'    => $description,
   'society'               => $society !== '' ? $society : '-',
   'town'                  => $town !== '' ? $town : '-',
   'address'               => $address !== '' ? $address : '-',
   'postcode'              => $postcode !== '' ? $postcode : '0',
   'phone'                 => $phone !== '' ? $phone : '-',
   'serialnumber'          => $serialnumber,
];

$show_total_time = rp_generate_bool($input['show_total_time'] ?? '1');
if ($show_total_time) {
   $payload['rapporttime'] = 'yes';
}

$include_task_images = rp_generate_bool($input['include_task_images'] ?? ((int)($config->fields['ImgTasks'] ?? 0) === 1 ? '1' : '0'));
if ($include_task_images) {
   $payload['rapportimgtask'] = 'yes';
}

$include_followup_images = rp_generate_bool($input['include_followup_images'] ?? ((int)($config->fields['ImgSuivis'] ?? 0) === 1 ? '1' : '0'));
if ($include_followup_images) {
   $payload['rapportimgsuivi'] = 'yes';
}

foreach ($selected_tasks as $task) {
   $id = (int)$task['id'];
   $payload['tasks_pdf_' . $id] = 'check';
   $payload['TASKS_DESCRIPTION' . $id] = (string)$task['content'];
   $payload['tasks_date_' . $id] = (string)$task['date'];
   $payload['tasks_time_' . $id] = (string)$task['actiontime'];
   $payload['tasks_name_' . $id] = (string)$task['name'];
}

foreach ($selected_followups as $followup) {
   $id = (int)$followup['id'];
   $payload['suivis_pdf_' . $id] = 'check';
   $payload['SUIVIS_DESCRIPTION' . $id] = (string)$followup['content'];
   $payload['suivis_date_' . $id] = (string)($followup['date'] ?? '');
   $payload['suivis_name_' . $id] = (string)($followup['name'] ?? '');
}

if ($doc_type['form'] === 'FormClient') {
   $payload['model'] = (string)($input['model'] ?? '');
   $payload['idsession'] = (string)($input['idsession'] ?? '');
   $payload['userpassword'] = (string)($input['userpassword'] ?? '');
   $payload['NameRespMat'] = (string)($input['NameRespMat'] ?? '');
   $payload['CoordRespMat'] = (string)($input['CoordRespMat'] ?? '');
   $payload['NameUtilpMat'] = (string)($input['NameUtilpMat'] ?? '');
   $payload['CoordUtilpMat'] = (string)($input['CoordUtilpMat'] ?? '');
   $payload['DataSave'] = (string)($input['DataSave'] ?? 'Oui');
   $payload['DataFormatting'] = (string)($input['DataFormatting'] ?? 'Non');

   if (rp_generate_bool($input['equal'] ?? '0')) {
      $payload['equal'] = 'equal';
   }
   if (rp_generate_bool($input['mouse'] ?? '0')) {
      $payload['mouse'] = 'Souris / ';
   }
   if (rp_generate_bool($input['keyboard'] ?? '0')) {
      $payload['keyboard'] = 'Clavier / ';
   }
   if (rp_generate_bool($input['bag'] ?? '0')) {
      $payload['bag'] = 'Sachoche / ';
   }
   if (rp_generate_bool($input['feed'] ?? '0')) {
      $payload['feed'] = 'Alimentation / ';
   }
   if (rp_generate_bool($input['dockstation'] ?? '0')) {
      $payload['dockstation'] = 'Dock Station / ';
   }
   $payload['other'] = (string)($input['other'] ?? '');
}

$csrf = Session::getNewCSRFToken();
$_SESSION['glpicsrftoken'] = $csrf;
$payload['_glpi_csrf_token'] = $csrf;
if (session_status() === PHP_SESSION_ACTIVE) {
   session_write_close();
}

$call = rp_generate_call_cripdf($payload, $rootdoc, $CFG_GLPI);
if (empty($call['ok'])) {
   rp_generate_end(500, [
      'ok'      => false,
      'error'   => (string)($call['error'] ?? 'generate_call_failed'),
      'message' => (string)($call['message'] ?? 'Erreur lors de la generation'),
      'generate_http' => (int)($call['http_code'] ?? 0),
      'generate_response' => substr((string)($call['response'] ?? ''), 0, 400),
   ]);
}

$type_id = (int)$doc_type['type_id'];
$user_id = (int)($auth['user_id'] ?? 0);
$detail = null;

$res = $DB->doQuery("SELECT *
                     FROM `glpi_plugin_rp_cridetails`
                     WHERE id_ticket = $ticket_id
                       AND type = $type_id
                       AND users_id = $user_id
                     ORDER BY date DESC, id DESC
                     LIMIT 1");
if ($res && $DB->numrows($res) === 1) {
   $detail = $DB->fetchassoc($res);
}

if ($detail === null) {
   $res = $DB->doQuery("SELECT *
                        FROM `glpi_plugin_rp_cridetails`
                        WHERE id_ticket = $ticket_id
                          AND type = $type_id
                        ORDER BY date DESC, id DESC
                        LIMIT 1");
   if ($res && $DB->numrows($res) === 1) {
      $detail = $DB->fetchassoc($res);
   }
}

if ($detail === null) {
   rp_generate_end(500, [
      'ok'                => false,
      'error'             => 'generate_not_persisted',
      'generate_http'     => (int)($call['http_code'] ?? 0),
      'message'           => 'La generation ne semble pas avoir ete enregistree en base.',
   ]);
}

$doc_id = (int)($detail['id_documents'] ?? 0);
$filename = '';
$filepath = '';
if ($doc_id <= 0) {
   rp_generate_end(500, [
      'ok'            => false,
      'error'         => 'missing_generated_document',
      'cridetail_id'  => (int)($detail['id'] ?? 0),
      'ticket_id'     => $ticket_id,
      'document_type' => (string)$doc_type['api'],
   ]);
}

if ($doc_id > 0) {
   $doc_res = $DB->doQuery("SELECT filename, filepath FROM `glpi_documents` WHERE id = $doc_id LIMIT 1");
   if ($doc_res && $DB->numrows($doc_res) === 1) {
      $doc_row = $DB->fetchassoc($doc_res);
      $filename = (string)($doc_row['filename'] ?? '');
      $filepath = (string)($doc_row['filepath'] ?? '');
   }
}

rp_generate_end(200, [
   'ok'                   => true,
   'ticket_id'            => $ticket_id,
   'document_type'        => (string)$doc_type['api'],
   'glpi_form'            => (string)$doc_type['form'],
   'cridetail_id'         => (int)($detail['id'] ?? 0),
   'id_documents'         => $doc_id,
   'filename'             => $filename,
   'filepath'             => $filepath,
   'document_url'         => $doc_id > 0 ? ($rootdoc . '/front/document.send.php?docid=' . $doc_id) : '',
   'date'                 => (string)($detail['date'] ?? ''),
   'send_mail'            => (int)($detail['send_mail'] ?? 0),
   'nameclient'           => (string)($detail['nameclient'] ?? ''),
   'email'                => (string)($detail['email'] ?? ''),
   'users_id'             => (int)($detail['users_id'] ?? 0),
   'tasks_selected'       => count($selected_tasks),
   'followups_selected'   => count($selected_followups),
   'technician_login'     => (string)($auth['tech_login'] ?? ''),
   'technician_label'     => (string)($auth['tech_label'] ?? ''),
   'required_fields'      => ['ticket_id', 'document_type'],
   'optional_fields'      => [
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
      'charte_id',
      'entity_group',
   ],
]);
