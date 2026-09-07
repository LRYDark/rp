<?php
/**
 * Résolution des recherches et des scans du bouton flottant RP.
 *
 * Réutilise l'existant plutôt que de le dupliquer :
 *  - normalisation des numéros de BL : pluginGestionBlNumber() (plugin Gestion)
 *  - page de signature du BL : front/survey.form.php du plugin Gestion
 *  - page mobile RP : front/mobile.php (jeton HMAC de PluginRpQrcode)
 *
 * Entrées POST : action=search|resolve, q=<texte scanné ou saisi>
 * Sortie JSON : { ok, results: [ { type, title, subtitle, badge, actions[] } ] }
 */
// Le chargement des plugins peut émettre du HTML (bandeau d'information du
// plugin RP) : on le neutralise pour garantir une réponse JSON valide.
ob_start();
include('../../../inc/includes.php');
while (ob_get_level() > 0) {
   ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

$rootdoc = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/');

function rp_scan_end(array $payload, int $status = 200): void {
   while (ob_get_level() > 0) {
      ob_end_clean();
   }
   http_response_code($status);
   echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   exit;
}

$gestion_active = Plugin::isPluginActive('gestion');
$gestion_webdir = $gestion_active
   ? (defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion'))
   : '';
// Le bouton doit rester utilisable même si le plugin Gestion est désactivé :
// dans ce cas seule la partie ticket est proposée (et inversement).
// Bons de livraison : atteignables ET couverts par le droit « Boutons
// flottants » de Gestion sur le bouton d'accueil. Sans ce droit, le bouton ne
// propose que la part de RP.
$can_read_bl     = PluginRpUserpref::blInButton('fab_home');
// Part de RP : QR code des rapports d'atelier, actions de rapport sur les
// tickets trouvés. Sans le droit RP sur ce bouton, il ne reste que les bons
// et l'ouverture des tickets.
$rp_in_home      = PluginRpUserpref::hasRpRight('fab_home');
$can_read_ticket = Session::haveRight('ticket', READ)
   || Session::haveRight('ticket', Ticket::READMY)
   || Session::haveRight('ticket', Ticket::READGROUP)
   || Session::haveRight('ticket', Ticket::READASSIGN);

// Capacités : permet à l'interface d'adapter ses libellés aux plugins actifs
if (!empty($_REQUEST['caps'])) {
   rp_scan_end([
      'ok'   => true,
      'caps' => [
         'bl'     => $can_read_bl,
         'ticket' => $can_read_ticket,
      ],
   ]);
}

$q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
if ($q === '') {
   rp_scan_end(['ok' => true, 'results' => []]);
}

/*
 * ---- Recherche approfondie (2e onglet du modal) ----
 *
 * Parcours distinct, et non un repli du premier : ici on ne résout pas un
 * identifiant, on fouille le CONTENU des tickets. C'est plus long — d'où le
 * bouton « Rechercher » plutôt qu'une frappe qui déclenche — et cela ne doit
 * jamais rediriger : un terme qui ressemble à une URL ou à un numéro de ticket
 * reste ici un terme à chercher.
 */
if (($_POST['mode'] ?? $_GET['mode'] ?? '') === 'deep') {
   if (!$can_read_ticket) {
      rp_scan_end(['ok' => false, 'error' => __("Vous n'avez pas accès aux tickets.", 'rp')]);
   }
   if (mb_strlen($q) < PluginRpScanSearch::MIN_LENGTH) {
      rp_scan_end(['ok' => false, 'error' => sprintf(
         __('Saisissez au moins %d caractères.', 'rp'),
         PluginRpScanSearch::MIN_LENGTH
      )]);
   }
   /*
    * Portée choisie par l'interrupteur devant le champ. Les deux ne se cumulent
    * pas : fouiller le texte des tickets coûte cent fois ce que coûte retrouver
    * un client, et qui cherche un client n'a pas à payer ce prix.
    */
   $scope = ($_POST['scope'] ?? $_GET['scope'] ?? '') === PluginRpScanSearch::SCOPE_ENTITY
      ? PluginRpScanSearch::SCOPE_ENTITY
      : PluginRpScanSearch::SCOPE_TICKET;

   rp_scan_end(['ok' => true] + PluginRpScanSearch::run($q, PluginRpScanSearch::LIMIT, $scope));
}

$results = [];

/**
 * Le QR code du plugin (rapport de préparation) encode une URL absolue vers
 * front/mobile.php : on redirige directement si le jeton est valide.
 */
if (preg_match('#/plugins/rp/front/mobile\.php\?(.*)$#i', $q, $m)) {
   parse_str(html_entity_decode($m[1]), $params);
   $ticket_id = (int)($params['id'] ?? 0);
   $token     = (string)($params['k'] ?? '');
   if ($ticket_id <= 0 || !PluginRpQrcode::checkTicketToken($ticket_id, $token)) {
      rp_scan_end(['ok' => false, 'error' => __('QR code invalide ou expiré.', 'rp')]);
   }
   if ($rp_in_home) {
      rp_scan_end([
         'ok'       => true,
         'redirect' => $rootdoc . '/plugins/rp/front/mobile.php?id=' . $ticket_id . '&k=' . urlencode($token),
      ]);
   }
   // Sans le droit RP sur ce bouton, la page mobile n'est pas de son ressort :
   // le QR ne vaut que comme numéro de ticket, et la recherche continue avec.
   $q = (string)$ticket_id;
}

// Autre URL du même GLPI (QR code d'un ticket, d'un document...) : on suit le lien
if (preg_match('#^https?://#i', $q)) {
   $base_host = parse_url((string)($CFG_GLPI['url_base'] ?? ''), PHP_URL_HOST);
   $scan_host = parse_url($q, PHP_URL_HOST);
   if ($base_host !== null && $scan_host !== null && strcasecmp($base_host, $scan_host) === 0) {
      rp_scan_end(['ok' => true, 'redirect' => $q]);
   }
   rp_scan_end(['ok' => false, 'error' => __('Ce QR code ne correspond pas à ce GLPI.', 'rp')]);
}

/**
 * Construit une ligne de résultat pour un bon de livraison.
 */
function rp_scan_bl_result(array $row, string $gestion_webdir, string $rootdoc): array {
   global $DB;

   $survey_id  = (int)$row['id'];
   $signed     = (int)($row['signed'] ?? 0);
   $tickets_id = (int)($row['tickets_id'] ?? 0);

   if ($signed === 1) {
      $actions = [[
         'label'   => __('Voir le BL signé', 'rp'),
         'url'     => $gestion_webdir . '/front/survey.form.php?id=' . $survey_id,
         'icon'    => 'ti ti-eye',
         'primary' => false,
      ]];
   } else {
      /*
       * Le formulaire de signature s'ouvre DIRECTEMENT, sans passer par la page
       * du BL puis par le modal de choix : le ticket associé est déjà connu ici,
       * il n'y a donc aucune question à reposer.
       *
       *  - ticket associé  => formulaire combiné « Rapport + BL » (force_combined)
       *  - aucun ticket    => formulaire du BL seul, parcours normal
       *
       * `force_combined` reste contrôlé côté serveur : sans tâche sur le ticket,
       * gestion/ajax/cri.php retombe sur son message et ses choix habituels.
       */
      // Le combiné n'est annoncé que si la part RP est ouverte sur ce bouton ;
      // gestion/ajax/cri.php retombe de toute façon sur le bon seul sinon.
      $combined    = $tickets_id > 0
         && PluginRpUserpref::hasRpRight('fab_home')
         && PluginRpAccess::canUse('rapport_tech', CREATE);
      $sign_params = [
         'job'        => $tickets_id,
         'root_doc'   => $gestion_webdir,
         'root_modal' => 'rp-scan-sign',
      ];
      if ($combined) {
         $sign_params['force_combined'] = 1;
      }

      $actions = [[
         'label'   => $combined ? __('Signer BL + rapport', 'rp') : __('Signer le BL', 'rp'),
         // Repli si le script du plugin Gestion n'est pas chargé : on ouvre la
         // page du BL, d'où la signature reste accessible.
         'url'     => $gestion_webdir . '/front/survey.form.php?id=' . $survey_id,
         'icon'    => 'ti ti-signature',
         'primary' => true,
         'open'    => [
            'handler' => 'gestion',
            'modal'   => (string)$survey_id,
            'params'  => $sign_params,
         ],
      ]];
   }

   // BL rattaché à un ticket : proposer aussi l'ouverture du ticket
   $subtitle = __('Aucun ticket associé', 'rp');
   if ($tickets_id > 0) {
      $ticket = new Ticket();
      if ($ticket->getFromDB($tickets_id) && $ticket->canViewItem()) {
         $subtitle  = '#' . sprintf('%07d', $tickets_id) . ' — ' . (string)$ticket->fields['name'];
         $actions[] = [
            'label'   => __('Ouvrir le ticket', 'rp'),
            'url'     => $rootdoc . '/front/ticket.form.php?id=' . $tickets_id,
            'icon'    => 'ti ti-ticket',
            'primary' => $signed === 1,
         ];
      } else {
         $subtitle = '#' . sprintf('%07d', $tickets_id);
      }
   }

   return [
      'type'     => 'bl',
      'title'    => (string)($row['bl'] ?? ''),
      'subtitle' => $subtitle,
      'badge'    => $signed === 1 ? ['label' => __('Signé', 'rp'), 'style' => 'ok']
                                  : ['label' => __('À signer', 'rp'), 'style' => 'warn'],
      'actions'  => $actions,
   ];
}

/**
 * Construit une ligne de résultat pour un ticket (+ son BL éventuel).
 */
function rp_scan_ticket_result(Ticket $ticket, string $gestion_webdir, string $rootdoc, bool $can_read_bl): array {
   global $DB;

   $ticket_id = (int)$ticket->fields['id'];
   $actions   = [[
      'label'   => __('Ouvrir le ticket', 'rp'),
      'url'     => $rootdoc . '/front/ticket.form.php?id=' . $ticket_id,
      'icon'    => 'ti ti-ticket',
      'primary' => false,
   ]];

   /*
    * Mêmes actions que le bouton flottant du ticket : fiche de prise en charge,
    * rapport d'intervention, hotline, rapport d'atelier, signature du BL...
    * filtrées par les droits RP de l'utilisateur. Elles ouvrent le formulaire
    * directement, sans passer par le ticket.
    *
    * Source unique volontairement partagée (PluginRpTicketActions) : les deux
    * entrées doivent proposer la même chose, sans risque de divergence.
    */
   $payload = PluginRpTicketActions::build($ticket_id, 'fab_home');
   $next    = (string)($payload['next'] ?? '');
   foreach (($payload['actions'] ?? []) as $action) {
      /*
       * Une seule action est mise en avant : l'étape suivante du cheminement.
       * À défaut d'étape identifiée, on retombe sur le drapeau `primary` d'
       * origine, pour ne jamais laisser une liste entièrement terne — le
       * scanner rend les actions non prioritaires en gris.
       */
      $is_next = ($next !== '' && ($action['key'] ?? '') === $next);
      $entry = [
         'label'   => (string)$action['label'],
         'icon'    => (string)($action['icon'] ?? 'ti ti-file'),
         'primary' => $next !== '' ? $is_next : !empty($action['primary']),
         // Repli si le script du plugin concerné n'est pas chargé.
         'url'     => $rootdoc . '/front/ticket.form.php?id=' . $ticket_id,
      ];

      if (($action['mode'] ?? '') === 'rp') {
         $entry['open'] = [
            'handler' => 'rp',
            'modal'   => (string)($action['modal'] ?? ''),
            'params'  => ['job' => $ticket_id, 'root_doc' => PLUGIN_RP_WEBDIR],
         ];
      } elseif (($action['mode'] ?? '') === 'gestion' && !empty($action['bl_id'])) {
         $params = [
            'job'        => $ticket_id,
            'root_doc'   => $gestion_webdir,
            'root_modal' => 'rp-scan-sign',
         ];
         // « BL seul » et « BL + rapport » se distinguent par ce seul drapeau.
         if (empty($action['bl_only'])) {
            $params['force_combined'] = 1;
         } else {
            $params['force_bl'] = 1;
         }
         $entry['open'] = [
            'handler' => 'gestion',
            'modal'   => (string)$action['bl_id'],
            'params'  => $params,
         ];
      }

      $actions[] = $entry;
   }

   $badge    = null;
   $subtitle = Dropdown::getDropdownName('glpi_entities', (int)$ticket->fields['entities_id']);

   /*
    * BL associé : uniquement pour enrichir le libellé et l'état affichés. Les
    * boutons de signature, eux, viennent de PluginRpTicketActions plus haut —
    * les recréer ici ferait doublon, et l'un des deux finirait par diverger.
    *
    * Le lien « Intervention mobile » a disparu : cette page est la destination
    * du QR code, elle ne fait que reproposer ce que le scanner offre déjà.
    */
   if ($can_read_bl && $DB->tableExists('glpi_plugin_gestion_surveys')) {
      $bl = $DB->request([
         'SELECT' => ['id', 'bl', 'signed'],
         'FROM'   => 'glpi_plugin_gestion_surveys',
         'WHERE'  => ['tickets_id' => $ticket_id],
         'ORDER'  => ['signed ASC', 'id DESC'],
         'LIMIT'  => 1,
      ])->current();
      if ($bl) {
         $signed    = (int)$bl['signed'];
         $subtitle .= ' — ' . (string)$bl['bl'];
         $badge     = $signed === 1 ? ['label' => __('BL signé', 'rp'), 'style' => 'ok']
                                    : ['label' => __('BL à signer', 'rp'), 'style' => 'warn'];
      }
   }

   return [
      'type'     => 'ticket',
      'title'    => '#' . sprintf('%07d', $ticket_id) . ' — ' . (string)$ticket->fields['name'],
      'subtitle' => $subtitle,
      'badge'    => $badge,
      'actions'  => $actions,
   ];
}

// ---- 1) Numéro de BL (BL123456, bl 123456, texte OCR contenant un BL) ----
$bl_number = '';
if (preg_match('/\bB[LC]\s?0*(\d{3,8})\b/i', $q, $m)) {
   $bl_number = 'BL' . $m[1];
   // normalisation officielle du plugin Gestion si disponible
   if (function_exists('pluginGestionBlNumber')) {
      $normalized = pluginGestionBlNumber($bl_number);
      if ($normalized !== '') {
         $bl_number = $normalized;
      }
   }
}

if ($bl_number !== '' && $can_read_bl) {
   $rows = $DB->request([
      'SELECT' => ['id', 'bl', 'signed', 'tickets_id'],
      'FROM'   => 'glpi_plugin_gestion_surveys',
      'WHERE'  => [
         'OR' => [
            ['bl_number' => $bl_number],
            ['bl'        => ['LIKE', $bl_number . '%']],
         ],
      ],
      'ORDER'  => ['signed ASC', 'id DESC'],
      'LIMIT'  => 10,
   ]);
   foreach ($rows as $row) {
      $results[] = rp_scan_bl_result($row, $gestion_webdir, $rootdoc);
   }
}

// ---- 2) Numéro de ticket (saisi seul, ou repéré dans un texte OCR) ----
$ticket_id = 0;
if (preg_match('/^#?\s*0*(\d{1,10})$/', $q, $m)) {
   $ticket_id = (int)$m[1];
} elseif (preg_match('/\bTICKET\s*(?:N\s*[°ºo]?)?\s*[:#-]?\s*0*(\d{2,10})\b/iu', $q, $m)) {
   // les rapports du plugin impriment « TICKET : 55375 »
   $ticket_id = (int)$m[1];
}

if ($can_read_ticket && $ticket_id > 0) {
   $ticket = new Ticket();
   if ($ticket->getFromDB($ticket_id) && $ticket->canViewItem()) {
      $results[] = rp_scan_ticket_result($ticket, $gestion_webdir, $rootdoc, $can_read_bl);
   }
}

// ---- 3) Recherche libre sur le titre du ticket ----
if ($can_read_ticket && count($results) === 0 && mb_strlen($q) >= 3) {
   $found = $DB->request([
      'SELECT' => ['id'],
      'FROM'   => 'glpi_tickets',
      'WHERE'  => [
         'is_deleted' => 0,
         'name'       => ['LIKE', '%' . $q . '%'],
      ] + getEntitiesRestrictCriteria('glpi_tickets'),
      'ORDER'  => ['id DESC'],
      'LIMIT'  => 8,
   ]);
   foreach ($found as $row) {
      $ticket = new Ticket();
      if ($ticket->getFromDB((int)$row['id']) && $ticket->canViewItem()) {
         $results[] = rp_scan_ticket_result($ticket, $gestion_webdir, $rootdoc, $can_read_bl);
      }
   }
}

rp_scan_end(['ok' => true, 'results' => $results]);
