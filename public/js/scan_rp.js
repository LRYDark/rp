/**
 * Bouton flottant « Scanner / Rechercher » du plugin RP.
 *
 * Affiché sur la page d'accueil GLPI. Ouvre un modal qui permet de :
 *  - rechercher un BL (BL123456), un ticket (numéro ou mot-clé) ;
 *  - scanner un QR code (rapport de préparation RP, ticket) ;
 *  - photographier un document et en lire le numéro de BL ou de ticket (OCR).
 *
 * L'habillage utilise les composants natifs de GLPI/Tabler (modal Bootstrap,
 * input-group, list-group, badge, alert, btn) : aucun style maison en dehors
 * de la position du bouton et du cadre de visée de la caméra.
 * Le modal est en plein écran sur téléphone (classe native
 * `modal-fullscreen-sm-down`) pour éviter toute distraction.
 *
 * Les actions renvoient vers les pages existantes : signature du BL du plugin
 * Gestion, ticket GLPI, page mobile RP. La résolution est faite par
 * ajax/scan.php, aucune logique métier n'est dupliquée ici.
 */
(function () {
   'use strict';

   // Socle commun (bouton flottant déplaçable, préférences d'affichage)
   if (!window.RpFab) {
      return;
   }

   // Droit de profil + préférence utilisateur (mobile uniquement par défaut)
   var prefs = window.RP_FAB_PREFS || {};
   if (!window.RpFab.shouldShow(prefs.fab_home)) {
      return;
   }

   var root = window.RpFab.root;
   var ajaxUrl = root + '/plugins/rp/ajax/scan.php';

   // ---- Page d'accueil GLPI uniquement --------------------------------------
   // GLPI 11 sert l'accueil via /Central (route moderne) ET /front/central.php
   // (route héritée) ; l'interface simplifiée via /Helpdesk.
   var path = (window.location.pathname || '').replace(/\/+$/, '');
   var isHome = /\/front\/central\.php$/i.test(path)
             || /\/central$/i.test(path)
             || /\/helpdesk$/i.test(path)
             || /\/front\/helpdesk\.public\.php$/i.test(path)
             || /\/index\.php$/i.test(path)
             || path === ''
             || path === root;
   if (!isHome) {
      return;
   }

   var els = {};
   var modal = null;
   var searchTimer = null;
   var cameraStream = null;
   var scanLoopActive = false;
   var detector = null;
   var ocrBusy = false;
   var capsLoaded = false;

   // ---- Construction de l'interface (composants natifs GLPI) ----------------
   function buildUI() {
      var fab = window.RpFab.create({
         id:      'home',
         icon:    'ti ti-scan',
         title:   'Scanner / Rechercher un BL ou un ticket',
         onClick: open
      });

      var wrapper = document.createElement('div');
      wrapper.innerHTML = [
         '<div class="modal fade rp-sheet" id="rpScanModal" tabindex="-1" aria-labelledby="rpScanModalLabel" aria-hidden="true">',
         // Ordinateur : position GLPI par défaut ; téléphone : panneau ancré en
         // bas de l'écran, accessible à une main (cf. signature_rp.css)
         '  <div class="modal-dialog modal-lg">',
         '    <div class="modal-content">',
         '      <div class="modal-header">',
         '        <h5 class="modal-title" id="rpScanModalLabel"><i class="ti ti-scan me-2"></i>Scanner / Rechercher</h5>',
         '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>',
         '      </div>',
         '      <div class="modal-body">',
         // Deux questions distinctes, deux onglets : « quel est ce numéro ? »
         // (identification immédiate, la frappe cherche au fil des lettres) et
         // « où ai-je vu ce mot ? » (fouille du contenu, lancée à la demande).
         // Les mêler dans un seul champ obligerait à deviner l\'intention.
         '        <ul class="nav nav-tabs mb-3" role="tablist">',
         '          <li class="nav-item" role="presentation">',
         '            <button class="nav-link active" id="rpScanTabBtn" data-bs-toggle="tab"',
         '                    data-bs-target="#rpScanPaneScan" type="button" role="tab"',
         '                    aria-controls="rpScanPaneScan" aria-selected="true">',
         '              <i class="ti ti-scan me-1"></i>BL / Ticket',
         '            </button>',
         '          </li>',
         '          <li class="nav-item" role="presentation">',
         '            <button class="nav-link" id="rpDeepTabBtn" data-bs-toggle="tab"',
         '                    data-bs-target="#rpScanPaneDeep" type="button" role="tab"',
         '                    aria-controls="rpScanPaneDeep" aria-selected="false">',
         '              <i class="ti ti-list-search me-1"></i>Par mot-clé',
         '            </button>',
         '          </li>',
         '        </ul>',
         '        <div class="tab-content">',
         '        <div class="tab-pane fade show active" id="rpScanPaneScan" role="tabpanel" aria-labelledby="rpScanTabBtn">',
         '        <div class="input-group mb-2">',
         '          <input type="text" class="form-control" id="rpScanInput" inputmode="search"',
         '                 autocomplete="off" autocapitalize="characters" spellcheck="false"',
         '                 placeholder="N° de BL, n° de ticket ou mot-clé">',
         '          <button type="button" class="btn btn-outline-secondary" id="rpScanCamBtn" title="Scanner avec la caméra">',
         '            <i class="ti ti-camera"></i>',
         '          </button>',
         '        </div>',
         '        <div class="alert alert-danger d-none" id="rpScanMsg" role="alert"></div>',
         // Appareil photo natif : utilisé sur les navigateurs sans détection QR
         // en direct (Safari/iOS). Aucune autorisation caméra n\'est demandée au
         // site, c\'est l\'appareil photo du téléphone qui s\'ouvre.
         '        <input type="file" accept="image/*" capture="environment" id="rpScanFile" class="d-none">',
         '        <div class="d-none mb-3" id="rpScanCamera">',
         '          <div class="rp-scan-video-wrap">',
         '            <video id="rpScanVideo" autoplay playsinline muted></video>',
         '            <div class="rp-scan-frame">',
         '              <span class="tl"></span><span class="tr"></span>',
         '              <span class="bl"></span><span class="br"></span>',
         '            </div>',
         '          </div>',
         '          <div class="text-center text-muted small mt-2" id="rpScanHint">',
         '            Visez le QR code, ou photographiez le numéro',
         '          </div>',
         '          <div class="text-center mt-2">',
         '            <button type="button" class="btn btn-primary btn-sm me-2" id="rpScanShoot">',
         '              <i class="ti ti-camera me-1"></i>Lire le numéro',
         '            </button>',
         '            <button type="button" class="btn btn-outline-secondary btn-sm" id="rpScanStop">Annuler</button>',
         '          </div>',
         '        </div>',
         '        <div class="list-group" id="rpScanResults"></div>',
         '        </div>',
         // ---- Onglet « Dans les tickets » : recherche par mot-clé ----
         '        <div class="tab-pane fade" id="rpScanPaneDeep" role="tabpanel" aria-labelledby="rpDeepTabBtn">',
         // Interrupteur de portée. Les deux recherches n'ont rien de comparable
         // en coût : fouiller le texte des tickets lit des centaines de milliers
         // de lignes, retrouver un client en lit quelques centaines. Les cumuler
         // ferait payer la première à qui ne demandait que la seconde.
         // Discret et de la taille de ce qu'il fait : ce choix accompagne la
         // recherche, il ne la précède pas en importance. Pleine largeur et en
         // couleur d'accent, il occupait le regard avant le champ lui-même.
         '          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">',
         '            <span class="text-muted small">Chercher dans</span>',
         '            <div class="btn-group btn-group-sm" role="group" aria-label="Où chercher">',
         '              <input type="radio" class="btn-check" name="rpDeepScope" id="rpDeepScopeTicket" value="ticket" autocomplete="off" checked>',
         '              <label class="btn btn-outline-secondary" for="rpDeepScopeTicket"><i class="ti ti-ticket me-1"></i>Tickets</label>',
         '              <input type="radio" class="btn-check" name="rpDeepScope" id="rpDeepScopeEntity" value="entity" autocomplete="off">',
         '              <label class="btn btn-outline-secondary" for="rpDeepScopeEntity"><i class="ti ti-building me-1"></i>Entités</label>',
         '            </div>',
         '          </div>',
         '          <div class="input-group mb-2">',
         '            <input type="text" class="form-control" id="rpDeepInput" inputmode="search"',
         '                   autocomplete="off" autocapitalize="off" spellcheck="false"',
         '                   placeholder="N° de série, mot-clé">',
         '            <button type="button" class="btn btn-primary" id="rpDeepBtn">',
         '              <i class="ti ti-search me-1"></i>Rechercher',
         '            </button>',
         '          </div>',
         '          <div class="form-text mb-2" id="rpDeepHint"></div>',
         '          <div class="alert alert-danger d-none" id="rpDeepMsg" role="alert"></div>',
         '          <div class="list-group" id="rpDeepResults"></div>',
         '        </div>',
         '        </div>',
         '      </div>',
         '    </div>',
         '  </div>',
         '</div>'
      ].join('');
      var modalEl = wrapper.firstElementChild;
      document.body.appendChild(modalEl);

      els = {
         fab:     fab,
         modalEl: modalEl,
         input:   modalEl.querySelector('#rpScanInput'),
         cambtn:  modalEl.querySelector('#rpScanCamBtn'),
         msg:     modalEl.querySelector('#rpScanMsg'),
         file:    modalEl.querySelector('#rpScanFile'),
         camera:  modalEl.querySelector('#rpScanCamera'),
         video:   modalEl.querySelector('#rpScanVideo'),
         hint:    modalEl.querySelector('#rpScanHint'),
         shoot:   modalEl.querySelector('#rpScanShoot'),
         stop:    modalEl.querySelector('#rpScanStop'),
         results: modalEl.querySelector('#rpScanResults'),
         deepTab:     modalEl.querySelector('#rpDeepTabBtn'),
         deepInput:   modalEl.querySelector('#rpDeepInput'),
         deepBtn:     modalEl.querySelector('#rpDeepBtn'),
         deepMsg:     modalEl.querySelector('#rpDeepMsg'),
         deepHint:    modalEl.querySelector('#rpDeepHint'),
         deepResults: modalEl.querySelector('#rpDeepResults'),
         deepScopes:  modalEl.querySelectorAll('input[name="rpDeepScope"]')
      };

      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
         modal = new bootstrap.Modal(modalEl, {});
      }

      bindSignActions();

      // Le clic est géré par RpFab (pour distinguer clic et déplacement)
      modalEl.addEventListener('shown.bs.modal', function () {
         loadCaps();
         // Pas de focus automatique sur téléphone : le clavier s'ouvrirait et
         // le premier appui sur un bouton ne servirait qu'à le refermer.
         if (!window.RpFab.isMobile()) {
            els.input.focus();
         }
      });
      modalEl.addEventListener('hidden.bs.modal', stopCamera);

      els.input.addEventListener('input', function () {
         clearTimeout(searchTimer);
         var value = els.input.value;
         searchTimer = setTimeout(function () { search(value); }, 350);
      });
      els.input.addEventListener('keydown', function (e) {
         if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            search(els.input.value);
         }
      });

      // Scan en direct quand le navigateur sait lire les QR codes (Chrome
      // Android) ; sinon appareil photo natif (Safari/iOS) : plus simple, plus
      // net, et sans autorisation caméra à redonner à chaque fois.
      els.cambtn.addEventListener('click', function () {
         if (hasLiveScan()) {
            startCamera();
         } else {
            els.file.click();
         }
      });
      els.file.addEventListener('change', function () {
         var picked = els.file.files && els.file.files[0];
         if (picked) {
            readImageFile(picked);
         }
         els.file.value = '';
      });
      els.stop.addEventListener('click', stopCamera);
      els.shoot.addEventListener('click', shootAndRead);

      /*
       * Onglet « Dans les tickets » : la recherche ne part QU'au bouton (ou à
       * Entrée). Elle fouille la description, les tâches et les suivis de tous
       * les tickets visibles : la déclencher à chaque lettre ferait courir au
       * serveur cinq requêtes lourdes pour un numéro de série de dix
       * caractères, dont une seule intéresse.
       */
      els.deepBtn.addEventListener('click', function () {
         deepSearch(els.deepInput.value);
      });
      els.deepInput.addEventListener('keydown', function (e) {
         if (e.key === 'Enter') {
            e.preventDefault();
            deepSearch(els.deepInput.value);
         }
      });
      /*
       * Changer de portée efface les résultats affichés : ils répondaient à
       * l'autre question, les laisser sous un interrupteur qui dit maintenant
       * le contraire tromperait sur ce qu'ils sont. La recherche ne repart pas
       * toute seule pour autant — c'est au bouton de la déclencher.
       */
      Array.prototype.forEach.call(els.deepScopes, function (radio) {
         radio.addEventListener('change', function () {
            els.deepResults.innerHTML = '';
            deepMessage('');
            applyScope();
         });
      });

      // La caméra appartient au premier onglet : en partir doit l'éteindre.
      els.deepTab.addEventListener('shown.bs.tab', function () {
         stopCamera();
         if (!window.RpFab.isMobile()) {
            els.deepInput.focus();
         }
      });

      applyScope();
   }

   function open() {
      if (modal) {
         modal.show();
         return;
      }
      // Repli si Bootstrap n'est pas disponible
      window.location.href = root + '/front/central.php';
   }

   function message(text, type) {
      if (!text) {
         els.msg.classList.add('d-none');
         els.msg.textContent = '';
         return;
      }
      els.msg.className = 'alert alert-' + (type === 'info' ? 'info' : 'danger');
      els.msg.textContent = text;
   }

   function csrf() {
      var meta = document.querySelector('meta[property="glpi:csrf_token"]');
      if (meta && meta.content) {
         return meta.content;
      }
      var input = document.querySelector('input[name="_glpi_csrf_token"]');
      return input ? input.value : '';
   }

   /**
    * Les libellés s'adaptent aux plugins réellement actifs : si le plugin
    * Gestion est coupé, le bouton reste utilisable pour les tickets seuls
    * (et inversement).
    */
   function loadCaps() {
      if (capsLoaded) {
         return;
      }
      capsLoaded = true;
      fetch(ajaxUrl + '?caps=1', { credentials: 'same-origin' })
         .then(function (r) { return r.json(); })
         .then(function (data) {
            var caps = (data && data.caps) ? data.caps : null;
            if (!caps) {
               return;
            }
            if (caps.bl && caps.ticket) {
               els.input.placeholder = 'N° de BL, n° de ticket ou mot-clé';
            } else if (caps.bl) {
               els.input.placeholder = 'N° de BL (ex : BL123456)';
            } else if (caps.ticket) {
               els.input.placeholder = 'N° de ticket ou mot-clé';
            } else {
               message("Vous n'avez accès ni aux tickets ni aux bons de livraison.");
            }
         })
         .catch(function () { /* libellés par défaut conservés */ });
   }

   // ---- Recherche / résolution ---------------------------------------------
   function search(value) {
      var q = (value || '').trim();
      if (q.length < 2) {
         els.results.innerHTML = '';
         message('');
         return;
      }

      els.results.innerHTML = '<div class="list-group-item text-center text-muted">'
         + '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Recherche…</div>';

      var body = new URLSearchParams();
      body.append('q', q);
      // Conservé pour compatibilité, mais c'est l'en-tête ci-dessous que GLPI 11
      // utilise réellement (voir les en-têtes de la requête).
      body.append('_glpi_csrf_token', csrf());

      fetch(ajaxUrl, {
         method: 'POST',
         credentials: 'same-origin',
         /*
          * GLPI 11 contrôle le jeton CSRF dans un écouteur du noyau, AVANT
          * d'atteindre le script. Deux comportements :
          *   - requête signalée AJAX (`X-Requested-With`) : le jeton est lu dans
          *     l'en-tête `X-Glpi-Csrf-Token` et CONSERVÉ, donc réutilisable ;
          *   - sinon : il est lu dans le corps et CONSOMMÉ, donc valable une
          *     seule fois — toute recherche suivante échouait.
          * C'est la convention des requêtes AJAX de GLPI lui-même.
          */
         headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Glpi-Csrf-Token': csrf()
         },
         body: body.toString()
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
         if (data && data.redirect) {
            window.location.href = data.redirect;
            return;
         }
         if (!data || data.ok === false) {
            els.results.innerHTML = '';
            message((data && data.error) || 'Recherche impossible.');
            return;
         }
         message('');
         renderResults(els.results, data.results || []);
      })
      .catch(function () {
         els.results.innerHTML = '';
         message('Erreur de communication avec GLPI.');
      });
   }

   /**
    * Rend une liste de résultats dans le conteneur donné.
    *
    * Les deux onglets partagent ce rendu : le serveur leur renvoie la même
    * forme, et une ligne de résultat doit se présenter pareil quel que soit le
    * chemin qui l'a trouvée.
    */
   function renderResults(container, items) {
      container.innerHTML = '';
      if (!items.length) {
         container.innerHTML = '<div class="list-group-item text-muted">Aucun résultat.</div>';
         return;
      }
      items.forEach(function (item) {
         var row = document.createElement('div');
         row.className = 'list-group-item';

         var badge = '';
         if (item.badge && item.badge.label) {
            // `muted` : un état constaté (statut du ticket, entité), qui informe
            // sans rien réclamer — le vert et l'orange sont réservés à ce qui
            // attend une action.
            var tone = 'bg-success';
            if (item.badge.style === 'warn') {
               tone = 'bg-warning text-dark';
            } else if (item.badge.style === 'muted') {
               tone = 'bg-secondary';
            }
            badge = '<span class="badge ms-2 ' + tone + '">' + esc(item.badge.label) + '</span>';
         }

         var actions = '';
         (item.actions || []).forEach(function (action) {
            var classes = 'btn btn-sm '
               + (action.primary ? 'btn-primary' : 'btn-outline-secondary')
               + ' me-2 mt-2';
            var inner = '<i class="' + esc(action.icon || 'ti ti-arrow-right') + ' me-1"></i>'
               + esc(action.label);

            // Action ouvrant un formulaire sur place : signature du BL, rapport
            // d'intervention, fiche de prise en charge, rapport d'atelier...
            // Le lien reste en repli si le script du plugin concerné n'est pas
            // chargé (cf. le clic plus bas).
            if (action.open && signHandlerAvailable(action.open.handler)) {
               actions += '<button type="button" class="' + classes + ' rp-scan-sign" '
                  + 'data-open="' + esc(JSON.stringify(action.open)) + '">'
                  + inner + '</button>';
               return;
            }
            actions += '<a class="' + classes + '" href="' + esc(action.url) + '">'
               + inner + '</a>';
         });

         row.innerHTML =
            '<div class="d-flex align-items-center flex-wrap">'
            + '<span class="fw-bold">' + esc(item.title || '') + '</span>' + badge
            + '</div>'
            + (item.subtitle ? '<div class="text-muted small">' + esc(item.subtitle) + '</div>' : '')
            + '<div class="d-flex flex-wrap rp-scan-actions">' + actions + '</div>';

         container.appendChild(row);
      });
   }

   // ---- Onglet « Par mot-clé » ----------------------------------------------
   function deepScope() {
      for (var i = 0; i < els.deepScopes.length; i++) {
         if (els.deepScopes[i].checked) {
            return els.deepScopes[i].value;
         }
      }
      return 'ticket';
   }

   /** Le champ et l'explication suivent la portée choisie. */
   function applyScope() {
      if (deepScope() === 'entity') {
         els.deepInput.placeholder = 'Client, désignation, adresse';
         els.deepHint.textContent = "Cherche le client par son nom, sa désignation ou son adresse, "
            + 'et remonte ses tickets les plus récents.';
      } else {
         els.deepInput.placeholder = 'N° de série, mot-clé';
         els.deepHint.textContent = 'Cherche dans le titre, la description, les tâches, les suivis, '
            + "le n° de série du matériel et les BL. 20 tickets au maximum.";
      }
   }

   function deepMessage(text) {
      if (!text) {
         els.deepMsg.classList.add('d-none');
         els.deepMsg.textContent = '';
         return;
      }
      els.deepMsg.className = 'alert alert-danger';
      els.deepMsg.textContent = text;
   }

   function deepSearch(value) {
      var q = (value || '').trim();
      if (q.length < 3) {
         els.deepResults.innerHTML = '';
         deepMessage('Saisissez au moins 3 caractères.');
         return;
      }
      deepMessage('');
      els.deepResults.innerHTML = '<div class="list-group-item text-center text-muted">'
         + '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Recherche…</div>';

      var body = new URLSearchParams();
      body.append('mode', 'deep');
      body.append('scope', deepScope());
      body.append('q', q);
      body.append('_glpi_csrf_token', csrf());

      fetch(ajaxUrl, {
         method: 'POST',
         credentials: 'same-origin',
         headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Glpi-Csrf-Token': csrf()
         },
         body: body.toString()
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
         if (!data || data.ok === false) {
            els.deepResults.innerHTML = '';
            deepMessage((data && data.error) || 'Recherche impossible.');
            return;
         }
         renderResults(els.deepResults, data.results || []);
         if (data.loose) {
            // Le terme n'a pas été trouvé tel quel : ces résultats viennent du
            // second passage, qui tolère les séparateurs. Le dire explique à la
            // fois l'attente et pourquoi le texte trouvé ne s'écrit pas comme
            // ce qui a été tapé.
            var loose = document.createElement('div');
            loose.className = 'list-group-item text-muted small';
            loose.textContent = 'Terme introuvable tel quel : résultats trouvés en ignorant les séparateurs.';
            els.deepResults.insertBefore(loose, els.deepResults.firstChild);
         }
         if (data.truncated) {
            // Une liste tronquée en silence se lit comme une liste complète :
            // le technicien croirait avoir vu tous les tickets du client.
            var more = document.createElement('div');
            more.className = 'list-group-item text-muted small';
            more.textContent = 'Seuls les 20 tickets les plus récents sont affichés — précisez la recherche.';
            els.deepResults.appendChild(more);
         }
      })
      .catch(function () {
         els.deepResults.innerHTML = '';
         deepMessage('Erreur de communication avec GLPI.');
      });
   }

   /**
    * Le script capable d'ouvrir ce formulaire est-il chargé ?
    * Le plugin Gestion peut être désactivé, et RP peut l'être aussi côté
    * Gestion : sans ce test on afficherait un bouton inerte.
    */
   function signHandlerAvailable(handler) {
      if (handler === 'gestion') {
         return typeof gestion_loadCriForm === 'function';
      }
      return typeof rp_loadCriForm === 'function';
   }

   /*
    * Clic sur une action de formulaire : on FERME d'abord la feuille de scan,
    * puis on ouvre le formulaire. Imbriquer deux modals Bootstrap laisse un
    * voile résiduel qui masque toute la page une fois le second refermé.
    */
   function bindSignActions() {
      els.results.addEventListener('click', function (event) {
         var button = event.target.closest('.rp-scan-sign');
         if (!button) {
            return;
         }
         event.preventDefault();

         var action;
         try {
            action = JSON.parse(button.dataset.open || '{}');
         } catch (e) {
            return;
         }
         if (!action.modal || !signHandlerAvailable(action.handler)) {
            return;
         }

         var open = function () {
            if (action.handler === 'gestion') {
               gestion_loadCriForm('showCriForm', String(action.modal), action.params || {});
            } else {
               rp_loadCriForm('showCriForm', String(action.modal), action.params || {});
            }
         };

         if (modal && els.modalEl) {
            els.modalEl.addEventListener('hidden.bs.modal', open, { once: true });
            modal.hide();
         } else {
            open();
         }
      });
   }

   function esc(text) {
      return String(text === undefined || text === null ? '' : text)
         .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
   }

   /**
    * Le navigateur sait-il lire les QR codes en direct ?
    * Safari (iOS) ne fournit pas BarcodeDetector : on y préfère l'appareil
    * photo natif, plus simple pour le technicien.
    */
   function hasLiveScan() {
      return typeof BarcodeDetector !== 'undefined';
   }

   function showBusy(text) {
      els.results.innerHTML = '<div class="list-group-item text-center text-muted">'
         + '<span class="spinner-border spinner-border-sm me-2" role="status"></span>'
         + esc(text) + '</div>';
   }

   // ---- Photo prise avec l'appareil natif -----------------------------------

   /**
    * Lit la photo prise par le technicien : QR code d'abord, puis lecture du
    * numéro (OCR) si aucun QR n'est trouvé.
    */
   function readImageFile(file) {
      message('');
      showBusy('Lecture de la photo…');

      var url = URL.createObjectURL(file);
      var img = new Image();

      img.onload = function () {
         // On limite la taille : au-delà, l'OCR est plus lent sans être meilleur
         var maxDim = 1600;
         var ratio = Math.min(1, maxDim / Math.max(img.width, img.height));
         var canvas = document.createElement('canvas');
         canvas.width = Math.max(1, Math.round(img.width * ratio));
         canvas.height = Math.max(1, Math.round(img.height * ratio));
         canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
         URL.revokeObjectURL(url);
         decodeCanvas(canvas);
      };

      img.onerror = function () {
         URL.revokeObjectURL(url);
         els.results.innerHTML = '';
         message('Photo illisible, réessayez.');
      };

      img.src = url;
   }

   function decodeCanvas(canvas) {
      decodeQrFromCanvas(canvas)
         .then(function (value) {
            if (value) {
               els.input.value = value.length > 60 ? '' : value;
               search(value);
               return null;
            }
            showBusy('Lecture du numéro…');
            return loadOcr()
               .then(function () { return window.Tesseract.recognize(canvas, 'eng'); })
               .then(function (res) {
                  var text = (res && res.data && res.data.text) ? res.data.text : '';
                  var reference = extractReference(text);
                  if (reference !== '') {
                     els.input.value = reference;
                     search(reference);
                  } else {
                     els.results.innerHTML = '';
                     message('Numéro non reconnu sur la photo, saisissez-le.');
                  }
                  return null;
               });
         })
         .catch(function () {
            els.results.innerHTML = '';
            message('Lecture automatique indisponible, saisissez le numéro.');
         });
   }

   /**
    * Décodage d'un QR code présent sur la photo : API native si disponible,
    * sinon bibliothèque chargée à la demande.
    */
   function decodeQrFromCanvas(canvas) {
      if (typeof BarcodeDetector !== 'undefined') {
         try {
            var nativeDetector = new BarcodeDetector({ formats: ['qr_code'] });
            return nativeDetector.detect(canvas)
               .then(function (codes) {
                  return (codes && codes.length && codes[0].rawValue) ? codes[0].rawValue : '';
               })
               .catch(function () { return ''; });
         } catch (e) { /* on tente la bibliothèque ci-dessous */ }
      }

      return loadJsQr()
         .then(function () {
            if (!window.jsQR) {
               return '';
            }
            var data = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
            var found = window.jsQR(data.data, data.width, data.height, { inversionAttempts: 'attemptBoth' });
            return (found && found.data) ? found.data : '';
         })
         .catch(function () { return ''; });
   }

   function loadJsQr() {
      if (window.jsQR) {
         return Promise.resolve();
      }
      return new Promise(function (resolve, reject) {
         var s = document.createElement('script');
         s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
         s.onload = function () { resolve(); };
         s.onerror = function () { reject(new Error('jsqr')); };
         document.head.appendChild(s);
      });
   }

   // ---- Caméra en direct (navigateurs compatibles) ---------------------------
   function startCamera() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
         message("La caméra n'est pas disponible sur cet appareil.");
         return;
      }
      message('');
      els.camera.classList.remove('d-none');
      // Retour immédiat : l'ouverture de la caméra (et l'autorisation du
      // navigateur) peut prendre un instant
      els.hint.textContent = 'Ouverture de la caméra…';

      navigator.mediaDevices.getUserMedia({
         video: { facingMode: { ideal: 'environment' } },
         audio: false
      })
      .then(function (stream) {
         cameraStream = stream;
         els.video.srcObject = stream;
         scanLoopActive = true;
         els.hint.textContent = 'Visez le QR code, ou photographiez le numéro';
         startQrLoop();
      })
      .catch(function () {
         els.camera.classList.add('d-none');
         message("Accès à la caméra refusé.");
      });
   }

   function stopCamera() {
      scanLoopActive = false;
      if (cameraStream) {
         cameraStream.getTracks().forEach(function (t) { t.stop(); });
         cameraStream = null;
      }
      els.video.srcObject = null;
      els.camera.classList.add('d-none');
      els.hint.textContent = 'Visez le QR code, ou photographiez le numéro';
   }

   /**
    * Détection QR native (BarcodeDetector). Absente sur certains navigateurs :
    * dans ce cas seule la lecture OCR par photo est proposée.
    */
   function startQrLoop() {
      if (typeof BarcodeDetector === 'undefined') {
         els.hint.textContent = 'Photographiez le numéro puis « Lire le numéro »';
         return;
      }
      try {
         detector = detector || new BarcodeDetector({ formats: ['qr_code'] });
      } catch (e) {
         els.hint.textContent = 'Photographiez le numéro puis « Lire le numéro »';
         return;
      }

      var tick = function () {
         if (!scanLoopActive || !els.video.videoWidth) {
            if (scanLoopActive) {
               setTimeout(tick, 300);
            }
            return;
         }
         detector.detect(els.video)
            .then(function (codes) {
               if (codes && codes.length && codes[0].rawValue) {
                  var value = codes[0].rawValue;
                  stopCamera();
                  els.input.value = value.length > 60 ? '' : value;
                  search(value);
                  return;
               }
               if (scanLoopActive) {
                  setTimeout(tick, 300);
               }
            })
            .catch(function () {
               if (scanLoopActive) {
                  setTimeout(tick, 500);
               }
            });
      };
      setTimeout(tick, 400);
   }

   /**
    * Extrait un numéro de BL ou de ticket d'un texte reconnu par l'OCR.
    * Les rapports du plugin impriment « TICKET : 55375 », les bons de
    * livraison « BL208207 ».
    */
   function extractReference(text) {
      var flat = (text || '').replace(/\s+/g, ' ');

      var bl = flat.match(/\bB\s?[LC]\s?0*(\d{3,8})\b/i);
      if (bl) {
         return 'BL' + bl[1];
      }
      var ticket = flat.match(/\bTICKET\s*(?:N\s*[°ºo]?)?\s*[:#-]?\s*0*(\d{2,10})\b/i);
      if (ticket) {
         return ticket[1];
      }
      var hashed = flat.match(/#\s*0*(\d{4,10})\b/);
      if (hashed) {
         return hashed[1];
      }
      return '';
   }

   /**
    * Photo + OCR : lecture du numéro de BL ou de ticket sur un document.
    * La bibliothèque OCR n'est chargée qu'au moment du besoin.
    */
   function shootAndRead() {
      if (ocrBusy || !els.video.videoWidth) {
         return;
      }
      ocrBusy = true;
      els.hint.textContent = 'Lecture en cours…';

      var canvas = document.createElement('canvas');
      canvas.width = els.video.videoWidth;
      canvas.height = els.video.videoHeight;
      canvas.getContext('2d').drawImage(els.video, 0, 0, canvas.width, canvas.height);

      loadOcr()
         .then(function () {
            return window.Tesseract.recognize(canvas, 'eng');
         })
         .then(function (res) {
            ocrBusy = false;
            var text = (res && res.data && res.data.text) ? res.data.text : '';
            var reference = extractReference(text);
            if (reference !== '') {
               stopCamera();
               els.input.value = reference;
               search(reference);
            } else {
               els.hint.textContent = 'Numéro non reconnu, réessayez ou saisissez-le';
            }
         })
         .catch(function () {
            ocrBusy = false;
            stopCamera();
            message('Lecture automatique indisponible, saisissez le numéro.');
         });
   }

   function loadOcr() {
      if (window.Tesseract) {
         return Promise.resolve();
      }
      return new Promise(function (resolve, reject) {
         var s = document.createElement('script');
         s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
         s.onload = function () { resolve(); };
         s.onerror = function () { reject(new Error('ocr')); };
         document.head.appendChild(s);
      });
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', buildUI);
   } else {
      buildUI();
   }
})();
