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

global $CFG_GLPI;
$rootdoc = rtrim((string)($CFG_GLPI['root_doc'] ?? '/glpi'), '/');

function rp_sign_end(int $status, array $payload): void
{
   if (!headers_sent()) {
      header('Content-Type: application/json; charset=UTF-8');
   }
   http_response_code($status);
   echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   exit;
}

function rp_sign_input(): array
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

function rp_sign_mode(string $raw): string
{
   $mode = strtolower(trim($raw));
   $mode = str_replace(['-', ' '], '_', $mode);
   return match ($mode) {
      '', 'auto' => 'auto',
      'report', 'rp', 'rapport' => 'report',
      'both', 'all', 'combined', 'fusion' => 'both',
      default => '',
   };
}

function rp_sign_headers_normalized(): array
{
   $raw = [];
   if (function_exists('getallheaders')) {
      $all = getallheaders();
      if (is_array($all)) {
         $raw = $all;
      }
   }

   if (empty($raw)) {
      foreach ($_SERVER as $k => $v) {
         if (!str_starts_with((string)$k, 'HTTP_')) {
            continue;
         }
         $name = str_replace('_', '-', substr((string)$k, 5));
         $name = implode('-', array_map('ucfirst', explode('-', strtolower($name))));
         $raw[$name] = (string)$v;
      }
   }

   $out = [];
   foreach ($raw as $k => $v) {
      $out[strtolower((string)$k)] = (string)$v;
   }
   return $out;
}

function rp_sign_forward_headers(array $headers_norm): array
{
   $forward = ['Accept' => 'application/json'];
   $allowed = [
      'authorization'          => 'Authorization',
      'app-token'              => 'App-Token',
      'session-token'          => 'Session-Token',
      'glpi-entity'            => 'Glpi-Entity',
      'glpi-entity-recursive'  => 'Glpi-Entity-Recursive',
      'glpi-profile'           => 'Glpi-Profile',
   ];

   foreach ($allowed as $src => $dst) {
      if (!empty($headers_norm[$src])) {
         $forward[$dst] = (string)$headers_norm[$src];
      }
   }
   return $forward;
}

function rp_sign_base_url(string $rootdoc, array $cfg): string
{
   $url_base = trim((string)($cfg['url_base'] ?? ''));
   if ($url_base !== '') {
      return rtrim($url_base, '/');
   }
   $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
   $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
   return $scheme . '://' . $host . rtrim($rootdoc, '/');
}

function rp_sign_http_call(string $method, string $url, array $headers, array $payload = []): array
{
   if (!function_exists('curl_init')) {
      return [
         'ok'      => false,
         'status'  => 0,
         'error'   => 'curl_unavailable',
         'message' => 'Extension cURL indisponible',
      ];
   }

   $method = strtoupper(trim($method));
   if ($method === 'GET' && !empty($payload)) {
      $query = http_build_query($payload);
      $url .= (str_contains($url, '?') ? '&' : '?') . $query;
   }

   $ch = curl_init($url);
   $header_lines = [];
   foreach ($headers as $k => $v) {
      $header_lines[] = $k . ': ' . $v;
   }

   $opts = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => $header_lines,
      CURLOPT_TIMEOUT        => 120,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CUSTOMREQUEST  => $method,
   ];

   if ($method === 'POST') {
      $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json; charset=UTF-8';
      $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   curl_setopt_array($ch, $opts);
   $response = curl_exec($ch);
   if ($response === false) {
      $error = (string)curl_error($ch);
      curl_close($ch);
      return [
         'ok'      => false,
         'status'  => 0,
         'error'   => 'http_call_failed',
         'message' => $error,
      ];
   }

   $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);

   $decoded = json_decode((string)$response, true);
   return [
      'ok'     => ($status >= 200 && $status < 300),
      'status' => $status,
      'json'   => is_array($decoded) ? $decoded : null,
      'raw'    => (string)$response,
   ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
   rp_sign_end(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$auth = PluginRpApiAuth::authenticateRequest(false);
if (empty($auth['ok'])) {
   rp_sign_end((int)($auth['code'] ?? 401), [
      'ok'      => false,
      'error'   => (string)($auth['error'] ?? 'auth_failed'),
      'message' => (string)($auth['message'] ?? 'Authentification API invalide'),
   ]);
}

$input = rp_sign_input();
$ticket_id = (int)($input['ticket_id'] ?? $input['id'] ?? 0);
if ($ticket_id <= 0) {
   rp_sign_end(422, ['ok' => false, 'error' => 'missing_ticket_id']);
}

$requested_mode = rp_sign_mode((string)($input['mode'] ?? 'auto'));
if ($requested_mode === '') {
   rp_sign_end(422, ['ok' => false, 'error' => 'invalid_mode']);
}

$headers_norm = rp_sign_headers_normalized();
$forward_headers = rp_sign_forward_headers($headers_norm);
$base_url = rp_sign_base_url($rootdoc, $CFG_GLPI);

$gestion_available = Plugin::isPluginActive('gestion')
   && class_exists('PluginGestionConfig')
   && file_exists(GLPI_ROOT . '/plugins/gestion/public/api/combined_sign.php');

if (session_status() === PHP_SESSION_ACTIVE) {
   session_write_close();
}

// report only path (or fallback if Gestion plugin unavailable)
if ($requested_mode === 'report' || !$gestion_available) {
   if ($requested_mode === 'both' && !$gestion_available) {
      rp_sign_end(503, [
         'ok'      => false,
         'error'   => 'gestion_unavailable',
         'message' => 'Le mode both requiert le plugin Gestion actif',
      ]);
   }

   $report_call = rp_sign_http_call(
      'POST',
      $base_url . '/plugins/rp/api/ticket_generate.php',
      $forward_headers,
      $input
   );

   if (!$report_call['ok']) {
      rp_sign_end($report_call['status'] > 0 ? (int)$report_call['status'] : 500, [
         'ok'      => false,
         'error'   => 'report_generate_failed',
         'message' => is_array($report_call['json']) ? (string)($report_call['json']['message'] ?? $report_call['json']['error'] ?? 'Report generation failed') : 'Report generation failed',
         'raw'     => substr((string)($report_call['raw'] ?? ''), 0, 500),
      ]);
   }

   $data = is_array($report_call['json']) ? $report_call['json'] : ['ok' => true];
   $data['mode'] = 'report';
   $data['requested_mode'] = $requested_mode;
   $data['bridge'] = 'rp_ticket_sign';
   rp_sign_end((int)$report_call['status'], $data);
}

// combined path via Gestion orchestrator
$combined_payload = $input;
$combined_payload['mode'] = $requested_mode;
$combined_call = rp_sign_http_call(
   'POST',
   $base_url . '/plugins/gestion/api/combined_sign.php',
   $forward_headers,
   $combined_payload
);

if ($combined_call['ok']) {
   $data = is_array($combined_call['json']) ? $combined_call['json'] : ['ok' => true];
   $data['bridge'] = 'rp_ticket_sign';
   rp_sign_end((int)$combined_call['status'], $data);
}

// In auto mode, if combined cannot run in both, fallback to report only.
$combined_error = is_array($combined_call['json']) ? (string)($combined_call['json']['error'] ?? '') : '';
$auto_fallback_errors = [
   'cannot_resolve_mode',
   'missing_bl_or_survey',
   'missing_ticket_bl_association',
   'ticket_bl_mismatch',
   'rp_unavailable',
];

if ($requested_mode === 'auto' && in_array($combined_error, $auto_fallback_errors, true)) {
   $report_call = rp_sign_http_call(
      'POST',
      $base_url . '/plugins/rp/api/ticket_generate.php',
      $forward_headers,
      $input
   );

   if ($report_call['ok']) {
      $data = is_array($report_call['json']) ? $report_call['json'] : ['ok' => true];
      $data['mode'] = 'report';
      $data['requested_mode'] = 'auto';
      $data['bridge'] = 'rp_ticket_sign';
      $data['combined_fallback'] = [
         'error'   => $combined_error,
         'status'  => (int)($combined_call['status'] ?? 0),
      ];
      rp_sign_end((int)$report_call['status'], $data);
   }
}

rp_sign_end($combined_call['status'] > 0 ? (int)$combined_call['status'] : 500, [
   'ok'      => false,
   'error'   => 'combined_sign_failed',
   'message' => is_array($combined_call['json']) ? (string)($combined_call['json']['message'] ?? $combined_call['json']['error'] ?? 'Echec du flux combine') : 'Echec du flux combine',
   'raw'     => substr((string)($combined_call['raw'] ?? ''), 0, 500),
]);

