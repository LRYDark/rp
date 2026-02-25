<?php
include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isInstalled('rp') || !$plugin->isActivated('rp')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', READ);

global $CFG_GLPI;
$rootdoc = rtrim((string)($CFG_GLPI['root_doc'] ?? '/glpi'), '/');
$api_rootdoc = $rootdoc;
$api_base_url = rtrim((string)($CFG_GLPI['url_base'] ?? ''), '/');
if ($api_base_url === '') {
   $api_base_url = $api_rootdoc;
}

Html::header(__('Documentation API RP', 'rp'), $_SERVER['PHP_SELF'], 'config', 'PluginRpConfig');
?>
<style>
.rp-api-doc .doc-card { border:1px solid #dfe3e8; border-radius:12px; padding:16px; margin-bottom:14px; background:#fff; }
.rp-api-doc .doc-meta { color:#6b7280; font-size:12px; margin-bottom:8px; }
.rp-api-doc .doc-note { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin:8px 0; }
.rp-api-doc .doc-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:8px; }
.rp-api-doc .doc-table th, .rp-api-doc .doc-table td { border:1px solid #e5e7eb; padding:8px; vertical-align:top; }
.rp-api-doc .doc-table th { background:#f8fafc; text-align:left; }
.rp-api-doc .method { display:inline-block; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:600; margin-right:6px; }
.rp-api-doc .method-get { background:#dcfce7; color:#166534; }
.rp-api-doc .method-post { background:#dbeafe; color:#1d4ed8; }
.rp-api-doc .doc-steps { margin:8px 0 0 18px; }
.rp-api-doc pre { background:#0f172a; color:#e2e8f0; padding:10px; border-radius:8px; overflow:auto; }
</style>

<div class="rp-api-doc">
  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title mb-0"><?php echo __('Documentation API RP', 'rp'); ?></h3></div>
    <div class="card-body">
      <p class="mb-2"><?php echo __('Endpoints publics (URL) : /plugins/rp/api/*.php. Fichiers physiques : /plugins/rp/public/api/*.php', 'rp'); ?></p>
      <p class="mb-2"><?php echo __('Auth supportée : OAuth v2 (Bearer utilisateur) et legacy (App-Token + user_token / Session-Token).', 'rp'); ?></p>
      <input id="rpApiSearch" class="form-control" type="search" placeholder="<?php echo __('Rechercher endpoint, paramètre, GET/POST, curl, body JSON, erreur…', 'rp'); ?>">
      <div class="form-text"><?php echo __('Astuce : essayez "ticket prepare", "ticket_generate", "ticket sign", "GET", "POST JSON", "curl", "body json", "invalid_document_type".', 'rp'); ?></div>
    </div>
  </div>

  <div class="doc-card" data-doc-item data-search="comment construire url get post json body querystring ticket_prepare ticket_generate ticket_sign curl powershell">
    <div class="doc-meta">GUIDE RAPIDE</div>
    <h4><?php echo __('Comment construire un appel RP (GET vs POST JSON)', 'rp'); ?></h4>
    <div class="doc-note">
      <strong><?php echo __('Résumé', 'rp'); ?></strong> :
      <?php echo __('`ticket_prepare` est souvent utilisé en GET (paramètres dans l’URL). `ticket_generate` et `ticket_sign` sont des POST JSON : l’URL est l’endpoint seul, les paramètres vont dans le body JSON.', 'rp'); ?>
    </div>
    <table class="doc-table">
      <thead>
        <tr>
          <th><?php echo __('Endpoint', 'rp'); ?></th>
          <th><?php echo __('Méthode conseillée', 'rp'); ?></th>
          <th><?php echo __('Construction URL', 'rp'); ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>ticket_prepare.php</code></td>
          <td><span class="method method-get">GET</span></td>
          <td><code><?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_prepare.php?ticket_id=55347&document_type=intervention_report', ENT_QUOTES); ?></code></td>
        </tr>
        <tr>
          <td><code>ticket_generate.php</code></td>
          <td><span class="method method-post">POST JSON</span></td>
          <td><code><?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_generate.php', ENT_QUOTES); ?></code> (<?php echo __('sans query string', 'rp'); ?>)</td>
        </tr>
        <tr>
          <td><code>ticket_sign.php</code></td>
          <td><span class="method method-post">POST JSON</span></td>
          <td><code><?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_sign.php', ENT_QUOTES); ?></code> (<?php echo __('sans query string', 'rp'); ?>)</td>
        </tr>
      </tbody>
    </table>
    <pre><code># GET = paramètres dans l'URL
curl -X GET "<?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_prepare.php?ticket_id=55347&document_type=intervention_report&include_tasks=1', ENT_QUOTES); ?>" \
  -H "Authorization: Bearer &lt;token&gt;" \
  -H "Accept: application/json"

# POST JSON = endpoint seul + body JSON
curl -X POST "<?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_generate.php', ENT_QUOTES); ?>" \
  -H "Authorization: Bearer &lt;token&gt;" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"ticket_id":55347,"document_type":"intervention_report","signature":"data:image/png;base64,..."}'</code></pre>
  </div>

  <div class="doc-card" data-doc-item data-search="ticket_prepare include_tasks include_followups_details include_client_email ticket_id document_type tasks followups entity_name client_email">
    <div class="doc-meta">GET/POST <?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_prepare.php', ENT_QUOTES); ?></div>
    <h4>`ticket_prepare.php`</h4>
    <p><?php echo __('Prépare la génération d’un rapport RP et retourne les métadonnées du ticket. Extensions additives : détails tâches/suivis/email client sur demande.', 'rp'); ?></p>
    <div class="doc-note">
      <span class="method method-get">GET</span><?php echo __('usage le plus simple (lecture/préparation)', 'rp'); ?><br>
      <span class="method method-post">POST JSON</span><?php echo __('possible aussi si vous préférez envoyer les paramètres dans le body', 'rp'); ?>
    </div>
    <table class="doc-table">
      <thead>
        <tr>
          <th><?php echo __('Paramètre', 'rp'); ?></th>
          <th><?php echo __('Type', 'rp'); ?></th>
          <th><?php echo __('Obligatoire', 'rp'); ?></th>
          <th><?php echo __('Description', 'rp'); ?></th>
        </tr>
      </thead>
      <tbody>
        <tr><td><code>ticket_id</code> (ou <code>id</code>)</td><td>int</td><td><?php echo __('Oui', 'rp'); ?></td><td><?php echo __('Identifiant du ticket GLPI', 'rp'); ?></td></tr>
        <tr><td><code>document_type</code> (ou <code>type</code>)</td><td>string</td><td><?php echo __('Oui', 'rp'); ?></td><td><?php echo __('Ex: `intervention_report`, `hotline_report`, `charge_sheet`', 'rp'); ?></td></tr>
        <tr><td><code>include_tasks</code></td><td>bool/int</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Ajoute `tasks[]` dans la réponse', 'rp'); ?></td></tr>
        <tr><td><code>include_followups_details</code></td><td>bool/int</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Ajoute `followups[]` détaillés', 'rp'); ?></td></tr>
        <tr><td><code>include_client_email</code></td><td>bool/int</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Ajoute `client_email` (si trouvé)', 'rp'); ?></td></tr>
      </tbody>
    </table>
    <pre><code>GET .../ticket_prepare.php?ticket_id=55347&amp;document_type=intervention_report
  &amp;include_tasks=1
  &amp;include_followups_details=1
  &amp;include_client_email=1</code></pre>
    <pre><code>{
  "ok": true,
  "ticket_id": 55347,
  "ticket_title": "Intervention imprimante",
  "ticket_description": "...",
  "entity_name": "Agence Paris",
  "client_email": "client@example.com",
  "tasks_count": 3,
  "tasks": [{"id":101,"content":"...","author":"Dupont Jean","time":600}],
  "followups": [{"id":21,"content":"...","author":"Dupont Jean"}],
  "last_documents": { ... }
}</code></pre>
    <pre><code># GET (préparation / affichage avant signature)
curl -X GET "<?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_prepare.php?ticket_id=55347&document_type=intervention_report&include_tasks=1&include_followups_details=1&include_client_email=1', ENT_QUOTES); ?>" \
  -H "Authorization: Bearer &lt;token&gt;" -H "Accept: application/json"

# POST JSON (équivalent fonctionnel)
curl -X POST "<?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_prepare.php', ENT_QUOTES); ?>" \
  -H "Authorization: Bearer &lt;token&gt;" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"ticket_id":55347,"document_type":"intervention_report","include_tasks":1,"include_followups_details":1,"include_client_email":1}'</code></pre>
    <div class="doc-note">
      <strong><?php echo __('Erreurs fréquentes', 'rp'); ?></strong> :
      <code>missing_ticket_id</code>, <code>ticket_not_found</code>.
    </div>
  </div>

  <div class="doc-card" data-doc-item data-search="ticket_generate document_type task_ids followup_ids users_id_tech signer_name signature description entity_group">
    <div class="doc-meta">POST <?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_generate.php', ENT_QUOTES); ?></div>
    <h4>`ticket_generate.php`</h4>
    <p><?php echo __('Génère un document RP (prise en charge / rapport intervention / hotline) et appelle le flux historique `cripdf.form.php`.', 'rp'); ?></p>
    <div class="doc-note">
      <span class="method method-post">POST JSON</span>
      <strong><?php echo __('Construction URL', 'rp'); ?></strong> :
      <code><?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_generate.php', ENT_QUOTES); ?></code>
      (<?php echo __('endpoint seul', 'rp'); ?>). <?php echo __('Les paramètres (ticket_id, document_type, signature, etc.) se mettent dans le body JSON.', 'rp'); ?>
    </div>
    <table class="doc-table">
      <thead>
        <tr>
          <th><?php echo __('Champ', 'rp'); ?></th>
          <th><?php echo __('Type', 'rp'); ?></th>
          <th><?php echo __('Obligatoire', 'rp'); ?></th>
          <th><?php echo __('Notes', 'rp'); ?></th>
        </tr>
      </thead>
      <tbody>
        <tr><td><code>ticket_id</code> (ou <code>id</code>)</td><td>int</td><td><?php echo __('Oui', 'rp'); ?></td><td><?php echo __('Identifiant du ticket', 'rp'); ?></td></tr>
        <tr><td><code>document_type</code> (ou <code>type</code>)</td><td>string</td><td><?php echo __('Oui', 'rp'); ?></td><td><?php echo __('Type de document RP', 'rp'); ?></td></tr>
        <tr><td><code>signature</code></td><td>string</td><td><?php echo __('Selon usage', 'rp'); ?></td><td><?php echo __('Data URL PNG base64 ; requis pour un flux de signature', 'rp'); ?></td></tr>
        <tr><td><code>signer_name</code> / <code>name</code></td><td>string</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Si absent, peut retomber sur le technicien authentifié', 'rp'); ?></td></tr>
        <tr><td><code>signer_email</code></td><td>string</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Email du client/signataire', 'rp'); ?></td></tr>
        <tr><td><code>users_id_tech</code></td><td>int</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Technicien RP sélectionné', 'rp'); ?></td></tr>
        <tr><td><code>task_ids</code></td><td>array[int]</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Sous-ensemble des tâches à inclure', 'rp'); ?></td></tr>
        <tr><td><code>followup_ids</code></td><td>array[int]</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Sous-ensemble des suivis à inclure', 'rp'); ?></td></tr>
        <tr><td><code>include_followups</code></td><td>bool/int</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Inclure les suivis (si activé)', 'rp'); ?></td></tr>
        <tr><td><code>description</code></td><td>string</td><td><?php echo __('Non', 'rp'); ?></td><td><?php echo __('Texte libre du rapport', 'rp'); ?></td></tr>
      </tbody>
    </table>
    <pre><code>{
  "ticket_id": 55347,
  "document_type": "intervention_report",
  "signer_name": "Client Nom",
  "signer_email": "client@example.com",
  "signature": "data:image/png;base64,...",
  "users_id_tech": 7,
  "task_ids": [101,102],
  "followup_ids": [21],
  "include_followups": 1,
  "description": "Description rapport"
}</code></pre>
    <pre><code># IMPORTANT: ticket_generate = POST JSON (pas un GET)
# URL = endpoint seul, pas de ?ticket_id=... obligatoire
curl -X POST "<?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_generate.php', ENT_QUOTES); ?>" \
  -H "Authorization: Bearer &lt;token&gt;" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "ticket_id": 55347,
    "document_type": "intervention_report",
    "signer_name": "Client Nom",
    "signature": "data:image/png;base64,...",
    "users_id_tech": 7,
    "task_ids": [101, 102],
    "followup_ids": [21],
    "include_followups": 1
  }'</code></pre>
    <div class="doc-note">
      <strong><?php echo __('Erreurs fréquentes', 'rp'); ?></strong> :
      <code>missing_ticket_id</code>, <code>invalid_document_type</code>, <code>ticket_not_found</code>.
    </div>
  </div>

  <div class="doc-card" data-doc-item data-search="ticket_sign mode report both auto bridge gestion combined_sign users_id_tech">
    <div class="doc-meta">POST <?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_sign.php', ENT_QUOTES); ?></div>
    <h4>`ticket_sign.php`</h4>
    <p><?php echo __('Endpoint RP orienté signature. Mode `report` = génération RP seule. Modes `auto`/`both` passent par Gestion `combined_sign` si disponible.', 'rp'); ?></p>
    <div class="doc-note">
      <span class="method method-post">POST JSON</span>
      <?php echo __('URL = endpoint seul (`ticket_sign.php`), puis body JSON avec `mode`, `ticket_id`, `document_type`, signature, etc.', 'rp'); ?>
    </div>
    <table class="doc-table">
      <thead>
        <tr>
          <th><?php echo __('Mode', 'rp'); ?></th>
          <th><?php echo __('Effet', 'rp'); ?></th>
        </tr>
      </thead>
      <tbody>
        <tr><td><code>report</code></td><td><?php echo __('Force le flux RP uniquement (génération/signature RP)', 'rp'); ?></td></tr>
        <tr><td><code>auto</code></td><td><?php echo __('Essaie le meilleur chemin ; peut fallback sur RP seul si Gestion/combined indisponible', 'rp'); ?></td></tr>
        <tr><td><code>both</code></td><td><?php echo __('Demande BL + RP via le pont Gestion (`combined_sign`) si disponible', 'rp'); ?></td></tr>
      </tbody>
    </table>
    <pre><code>{
  "mode": "report",
  "ticket_id": 55347,
  "document_type": "intervention_report",
  "signer_name": "Client Nom",
  "signer_email": "",
  "signature": "data:image/png;base64,...",
  "users_id_tech": 7,
  "task_ids": [101,102],
  "followup_ids": [21],
  "include_followups": 1
}</code></pre>
    <pre><code># Exemple POST JSON (signature RP orientée métier)
curl -X POST "<?php echo htmlspecialchars($rootdoc . '/plugins/rp/api/ticket_sign.php', ENT_QUOTES); ?>" \
  -H "Authorization: Bearer &lt;token&gt;" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"mode":"auto","ticket_id":55347,"document_type":"intervention_report","signer_name":"Client","signature":"data:image/png;base64,...","users_id_tech":7}'</code></pre>
    <div class="doc-note">
      <strong><?php echo __('Erreurs fréquentes', 'rp'); ?></strong> :
      <code>missing_ticket_id</code>, <code>invalid_mode</code>, <code>cannot_resolve_mode</code>.
      <?php echo __('Le mode `both` requiert le plugin Gestion actif.', 'rp'); ?>
    </div>
  </div>

  <div class="doc-card" data-doc-item data-search="auth oauth bearer legacy app-token user_token session-token">
    <div class="doc-meta">AUTH</div>
    <h4>Authentification</h4>
    <pre><code>OAuth v2: Authorization: Bearer &lt;access_token_utilisateur&gt;
Legacy : App-Token + Authorization: user_token &lt;token&gt;
Legacy : App-Token + Session-Token</code></pre>
    <pre><code>Token OAuth (GLPI v2.2)
POST <?php echo htmlspecialchars($rootdoc . '/api.php/v2.2/token', ENT_QUOTES); ?></code></pre>
  </div>

  <div class="doc-card" data-doc-item data-search="workflow sequence preparation generation signature rapide ticket rp prepare generate sign auto report both">
    <div class="doc-meta">WORKFLOW</div>
    <h4><?php echo __('Exemples de séquences d’appel RP', 'rp'); ?></h4>
    <p><strong><?php echo __('Préparer puis afficher un ticket (avant signature)', 'rp'); ?></strong></p>
    <ol class="doc-steps">
      <li><?php echo __('`GET ticket_prepare.php?ticket_id=...&document_type=...`', 'rp'); ?></li>
      <li><?php echo __('Optionnel: activer `include_tasks=1`, `include_followups_details=1`, `include_client_email=1` pour alimenter un écran de choix / récap.', 'rp'); ?></li>
    </ol>
    <p class="mt-3"><strong><?php echo __('Signer/générer un rapport RP', 'rp'); ?></strong></p>
    <ol class="doc-steps">
      <li><?php echo __('Envoyer `POST ticket_sign.php` en mode `report` ou `auto` avec signature base64 et infos signataire.', 'rp'); ?></li>
      <li><?php echo __('Si vous voulez piloter uniquement la génération RP (sans logique de mode), utiliser `POST ticket_generate.php`.', 'rp'); ?></li>
    </ol>
    <p class="mt-3"><strong><?php echo __('Question fréquente: "Comment construire l’URL de ticket_generate ?"', 'rp'); ?></strong></p>
    <div class="doc-note">
      <?php echo __('Réponse: URL fixe = endpoint seul (`/plugins/rp/api/ticket_generate.php`), puis body JSON avec les paramètres. On ne met pas `ticket_id` dans la query string sauf choix volontaire de votre client HTTP.', 'rp'); ?>
    </div>
  </div>

  <div class="doc-card" data-doc-item data-search="test authentification api oauth bearer legacy app-token user_token modal config rp">
    <div class="doc-meta">OUTILS</div>
    <h4><?php echo __('Test API / Authentification RP', 'rp'); ?></h4>
    <p class="mb-2"><?php echo __('Ouvre le testeur interactif (OAuth v2 / legacy) directement sur cette page de documentation.', 'rp'); ?></p>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rpApiTesterModal">
      <?php echo __('Test', 'rp'); ?>
    </button>
  </div>
</div>

<div class="modal fade" id="rpApiTesterModal" tabindex="-1" aria-labelledby="rpApiTesterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rpApiTesterModalLabel"><?php echo __('Test API / Authentification RP', 'rp'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('Fermer', 'rp'); ?>"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label for="rpApiTestMode" class="form-label"><?php echo __('Mode de test', 'rp'); ?></label>
            <select id="rpApiTestMode" class="form-select">
              <option value="v2_password"><?php echo __('OAuth v2.2 (grant password)', 'rp'); ?></option>
              <option value="v1_user_token"><?php echo __('Legacy v1 (App-Token + user_token)', 'rp'); ?></option>
            </select>
          </div>
          <div class="col-md-8">
            <label for="rpApiTestBaseUrl" class="form-label"><?php echo __('Base URL GLPI', 'rp'); ?></label>
            <input id="rpApiTestBaseUrl"
                   class="form-control"
                   value="<?php echo htmlspecialchars($api_base_url, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="https://example.tld/glpi">
          </div>
          <div class="col-md-4">
            <label for="rpApiTestTicketId" class="form-label"><?php echo __('Ticket ID de test', 'rp'); ?></label>
            <input id="rpApiTestTicketId" class="form-control" value="1" placeholder="123">
          </div>
          <div class="col-md-4">
            <label for="rpApiTestDocType" class="form-label"><?php echo __('Type de document', 'rp'); ?></label>
            <select id="rpApiTestDocType" class="form-select">
              <option value="intervention_report">intervention_report</option>
              <option value="hotline_report">hotline_report</option>
              <option value="charge_sheet">charge_sheet</option>
            </select>
          </div>
        </div>

        <div id="rpApiTestV2Fields" class="row g-3 mt-1">
          <div class="col-md-6">
            <label for="rpApiClientId" class="form-label"><?php echo __('Client ID OAuth', 'rp'); ?></label>
            <input id="rpApiClientId" class="form-control" placeholder="client_id">
          </div>
          <div class="col-md-6">
            <label for="rpApiClientSecret" class="form-label"><?php echo __('Client secret OAuth', 'rp'); ?></label>
            <input id="rpApiClientSecret" type="password" class="form-control" placeholder="client_secret">
          </div>
          <div class="col-md-6">
            <label for="rpApiUsername" class="form-label"><?php echo __('Login GLPI', 'rp'); ?></label>
            <input id="rpApiUsername" class="form-control" placeholder="login">
          </div>
          <div class="col-md-6">
            <label for="rpApiPassword" class="form-label"><?php echo __('Mot de passe GLPI', 'rp'); ?></label>
            <input id="rpApiPassword" type="password" class="form-control" placeholder="mot de passe">
          </div>
        </div>

        <div id="rpApiTestV1Fields" class="row g-3 mt-1" style="display:none;">
          <div class="col-md-6">
            <label for="rpApiAppToken" class="form-label"><?php echo __('App-Token', 'rp'); ?></label>
            <input id="rpApiAppToken" class="form-control" placeholder="app_token">
          </div>
          <div class="col-md-6">
            <label for="rpApiUserToken" class="form-label"><?php echo __('User token (préférences GLPI)', 'rp'); ?></label>
            <input id="rpApiUserToken" class="form-control" placeholder="user_token">
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
          <button type="button" class="btn btn-primary" id="rpApiRunTestBtn"><?php echo __('Tester l API', 'rp'); ?></button>
          <button type="button" class="btn btn-outline-secondary" id="rpApiBuildPsBtn"><?php echo __('Générer script PowerShell', 'rp'); ?></button>
          <button type="button" class="btn btn-outline-secondary" id="rpApiCopyPsBtn"><?php echo __('Copier le script', 'rp'); ?></button>
          <button type="button" class="btn btn-outline-secondary" id="rpApiPopupPsBtn"><?php echo __('Ouvrir dans une fenêtre', 'rp'); ?></button>
        </div>

        <div class="mt-3">
          <label for="rpApiPsScript" class="form-label"><?php echo __('Script PowerShell généré', 'rp'); ?></label>
          <textarea id="rpApiPsScript" class="form-control font-monospace" rows="12"></textarea>
        </div>

        <div class="mt-3">
          <label for="rpApiTestResult" class="form-label"><?php echo __('Résultat du test HTTP', 'rp'); ?></label>
          <pre id="rpApiTestResult" class="bg-light p-2 rounded small mb-0"></pre>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Fermer', 'rp'); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  if (window.rpApiTesterInit) {
    return;
  }
  window.rpApiTesterInit = true;

  const get = (id) => document.getElementById(id);
  const modeEl = get('rpApiTestMode');
  const resultEl = get('rpApiTestResult');
  const scriptEl = get('rpApiPsScript');

  const fields = {
    baseUrl: get('rpApiTestBaseUrl'),
    ticketId: get('rpApiTestTicketId'),
    docType: get('rpApiTestDocType'),
    clientId: get('rpApiClientId'),
    clientSecret: get('rpApiClientSecret'),
    username: get('rpApiUsername'),
    password: get('rpApiPassword'),
    appToken: get('rpApiAppToken'),
    userToken: get('rpApiUserToken'),
    v2Box: get('rpApiTestV2Fields'),
    v1Box: get('rpApiTestV1Fields')
  };

  const input = document.getElementById('rpApiSearch');
  const cards = Array.from(document.querySelectorAll('[data-doc-item]'));
  const normalizeSearch = (value) => {
    let txt = String(value ?? '').toLowerCase();
    if (typeof txt.normalize === 'function') {
      txt = txt.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return txt
      .replace(/[_./:\\-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  };
  const cardSearchIndex = new Map();
  cards.forEach((card) => {
    const raw = ((card.getAttribute('data-search') || '') + ' ' + (card.innerText || ''));
    cardSearchIndex.set(card, normalizeSearch(raw));
  });
  if (input) {
    input.addEventListener('input', function(){
      const q = normalizeSearch(input.value || '');
      const tokens = q ? q.split(' ').filter(Boolean) : [];
      cards.forEach(card => {
        const text = cardSearchIndex.get(card) || '';
        const visible = tokens.length === 0 || tokens.every((t) => text.indexOf(t) !== -1);
        card.style.display = visible ? '' : 'none';
      });
    });
  }

  const escPs = (value) => String(value ?? '').replace(/'/g, "''");
  const normalizeBase = (txt) => String(txt ?? '').trim().replace(/\/+$/, '');
  const absolutizeBase = (txt) => {
    const base = normalizeBase(txt);
    if (base.startsWith('/')) {
      return window.location.origin + base;
    }
    return base;
  };
  const setResult = (txt) => {
    resultEl.textContent = String(txt ?? '');
  };
  const setMode = () => {
    const v2 = modeEl.value === 'v2_password';
    fields.v2Box.style.display = v2 ? '' : 'none';
    fields.v1Box.style.display = v2 ? 'none' : '';
  };
  const readBody = async (res) => {
    const txt = await res.text();
    try {
      return JSON.stringify(JSON.parse(txt), null, 2);
    } catch (e) {
      return txt;
    }
  };
  const clip = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }
    scriptEl.focus();
    scriptEl.select();
    document.execCommand('copy');
  };

  const buildScript = () => {
    const mode = modeEl.value;
    const base = absolutizeBase(fields.baseUrl.value);
    const ticketId = fields.ticketId.value.trim();
    const docType = fields.docType.value;
    const prepareUrl = "$BaseUrl/plugins/rp/api/ticket_prepare.php?ticket_id=$TicketId&document_type=$DocType&include_tasks=1&include_followups_details=1&include_client_email=1";

    if (mode === 'v2_password') {
      return [
        "$BaseUrl = '" + escPs(base) + "'",
        "$TicketId = '" + escPs(ticketId) + "'",
        "$DocType = '" + escPs(docType) + "'",
        "$ClientId = '" + escPs(fields.clientId.value) + "'",
        "$ClientSecret = '" + escPs(fields.clientSecret.value) + "'",
        "$Username = '" + escPs(fields.username.value) + "'",
        "$Password = '" + escPs(fields.password.value) + "'",
        "",
        "$token = Invoke-RestMethod -Method POST -Uri \"$BaseUrl/api.php/v2.2/token\" -ContentType \"application/x-www-form-urlencoded\" -Body @{",
        "    grant_type    = 'password'",
        "    client_id     = $ClientId",
        "    client_secret = $ClientSecret",
        "    username      = $Username",
        "    password      = $Password",
        "    scope         = 'api user'",
        "}",
        "",
        "$headers = @{ Authorization = \"Bearer $($token.access_token)\"; Accept = 'application/json' }",
        "$prepare = Invoke-WebRequest -Method GET -Uri \"" + prepareUrl + "\" -Headers $headers -TimeoutSec 180",
        "$prepare.Content",
        "",
        "$genHeaders = @{ Authorization = \"Bearer $($token.access_token)\"; Accept = 'application/json'; 'Content-Type' = 'application/json' }",
        "$payload = @{",
        "    mode = 'auto'",
        "    ticket_id = [int]$TicketId",
        "    document_type = $DocType",
        "    signer_name = $Username",
        "    signer_email = ''",
        "    signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='",
        "    mail_to_client = 0",
        "    include_followups = 1",
        "    show_total_time = 1",
        "    include_task_images = 0",
        "    include_followup_images = 0",
        "} | ConvertTo-Json -Depth 10",
        "Invoke-WebRequest -Method POST -Uri \"$BaseUrl/plugins/rp/api/ticket_sign.php\" -Headers $genHeaders -Body $payload -ContentType 'application/json' -TimeoutSec 180"
      ].join("\n");
    }

    return [
      "$BaseUrl = '" + escPs(base) + "'",
      "$TicketId = '" + escPs(ticketId) + "'",
      "$DocType = '" + escPs(docType) + "'",
      "$AppToken = '" + escPs(fields.appToken.value) + "'",
      "$UserToken = '" + escPs(fields.userToken.value) + "'",
      "",
      "$headers = @{",
      "    'App-Token'     = $AppToken",
      "    'Authorization' = \"user_token $UserToken\"",
      "    'Accept'        = 'application/json'",
      "}",
      "$prepare = Invoke-WebRequest -Method GET -Uri \"" + prepareUrl + "\" -Headers $headers -TimeoutSec 180",
      "$prepare.Content",
      "",
      "$genHeaders = @{",
      "    'App-Token'     = $AppToken",
      "    'Authorization' = \"user_token $UserToken\"",
      "    'Accept'        = 'application/json'",
      "    'Content-Type'  = 'application/json'",
      "}",
      "$payload = @{",
      "    mode = 'auto'",
      "    ticket_id = [int]$TicketId",
      "    document_type = $DocType",
      "    signer_name = 'API Legacy'",
      "    signer_email = ''",
      "    signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='",
      "    mail_to_client = 0",
      "    include_followups = 1",
      "    show_total_time = 1",
      "    include_task_images = 0",
      "    include_followup_images = 0",
      "} | ConvertTo-Json -Depth 10",
      "Invoke-WebRequest -Method POST -Uri \"$BaseUrl/plugins/rp/api/ticket_sign.php\" -Headers $genHeaders -Body $payload -ContentType 'application/json' -TimeoutSec 180"
    ].join("\n");
  };

  const runTest = async () => {
    const base = normalizeBase(fields.baseUrl.value);
    const ticketId = fields.ticketId.value.trim();
    const docType = fields.docType.value;
    if (!base || !ticketId) {
      setResult("Base URL et ticket_id sont obligatoires.");
      return;
    }

    setResult("Test en cours...");
    try {
      const prepareSuffix = '/plugins/rp/api/ticket_prepare.php?ticket_id=' + encodeURIComponent(ticketId)
        + '&document_type=' + encodeURIComponent(docType)
        + '&include_tasks=1&include_followups_details=1&include_client_email=1';

      if (modeEl.value === 'v2_password') {
        const tokenForm = new URLSearchParams();
        tokenForm.set('grant_type', 'password');
        tokenForm.set('client_id', fields.clientId.value.trim());
        tokenForm.set('client_secret', fields.clientSecret.value);
        tokenForm.set('username', fields.username.value.trim());
        tokenForm.set('password', fields.password.value);
        tokenForm.set('scope', 'api user');

        const tokenRes = await fetch(base + '/api.php/v2.2/token', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: tokenForm
        });
        const tokenRaw = await tokenRes.text();
        let tokenBody = {};
        try {
          tokenBody = JSON.parse(tokenRaw);
        } catch (e) {
          tokenBody = {};
        }
        if (!tokenRes.ok || !tokenBody.access_token) {
          setResult("OAuth token KO (HTTP " + tokenRes.status + ")\n" + tokenRaw);
          return;
        }

        const prepareRes = await fetch(base + prepareSuffix, {
          headers: { 'Authorization': 'Bearer ' + tokenBody.access_token, 'Accept': 'application/json' }
        });
        setResult("Token OK (HTTP " + tokenRes.status + ")\n\nPrepare HTTP " + prepareRes.status + "\n" + (await readBody(prepareRes)));
        return;
      }

      const prepareRes = await fetch(base + prepareSuffix, {
        headers: {
          'App-Token': fields.appToken.value.trim(),
          'Authorization': 'user_token ' + fields.userToken.value.trim(),
          'Accept': 'application/json'
        }
      });
      setResult("Prepare HTTP " + prepareRes.status + "\n" + (await readBody(prepareRes)));
    } catch (e) {
      setResult("Erreur JS: " + e.message);
    }
  };

  get('rpApiRunTestBtn').addEventListener('click', runTest);
  get('rpApiBuildPsBtn').addEventListener('click', function () {
    scriptEl.value = buildScript();
  });
  get('rpApiCopyPsBtn').addEventListener('click', async function () {
    scriptEl.value = buildScript();
    await clip(scriptEl.value);
    setResult("Script copié dans le presse-papiers.");
  });
  get('rpApiPopupPsBtn').addEventListener('click', function () {
    scriptEl.value = buildScript();
    const popup = window.open('', '_blank', 'width=980,height=760');
    if (!popup) {
      setResult("Popup bloquée par le navigateur.");
      return;
    }
    const escaped = scriptEl.value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    popup.document.write('<!doctype html><html><head><meta charset=\"utf-8\"><title>Script PowerShell API RP</title></head><body style=\"font-family:monospace;padding:12px;\"><h3>Script PowerShell</h3><pre style=\"white-space:pre-wrap;word-break:break-word;\">' + escaped + '</pre></body></html>');
    popup.document.close();
  });

  modeEl.addEventListener('change', function () {
    setMode();
    scriptEl.value = buildScript();
  });

  setMode();
  scriptEl.value = buildScript();
})();
</script>

<?php Html::footer(); ?>
