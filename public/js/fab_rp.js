/**
 * Boutons flottants du plugin RP : socle commun + bouton des tickets.
 *
 * `window.RpFab` fournit la fabrique de bouton flottant déplaçable, réutilisée
 * par le bouton de scan de la page d'accueil (scan_rp.js) et par le bouton de
 * signature des tickets (ci-dessous).
 *
 * Habillage : composants natifs GLPI/Tabler uniquement (btn, modal, list-group,
 * badge, alert). Le CSS du plugin ne fournit que la position flottante.
 *
 * Affichage piloté par la balise <meta name="rp:fab"> injectée par le plugin
 * (droits de profil croisés avec les préférences de l'utilisateur) :
 *   0 = jamais, 1 = mobile uniquement (défaut), 2 = toujours.
 */
(function () {
   'use strict';

   function readPrefs() {
      var meta = document.querySelector('meta[name="rp:fab"]');
      if (!meta || !meta.content) {
         return {};
      }
      try {
         return JSON.parse(meta.content) || {};
      } catch (e) {
         return {};
      }
   }

   var prefs = readPrefs();
   window.RP_FAB_PREFS = prefs;
   var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc ? CFG_GLPI.root_doc : '')
      .replace(/\/$/, '');

   function isMobile() {
      return window.matchMedia('(max-width: 768px)').matches;
   }

   /**
    * Le bouton doit-il être affiché ? (mode + taille d'écran)
    */
   function shouldShow(mode) {
      var value = parseInt(mode, 10);
      if (value === 2) {
         return true;
      }
      if (value === 1) {
         return isMobile();
      }
      return false;
   }

   /**
    * Crée un bouton flottant déplaçable. La position est mémorisée par bouton
    * dans le navigateur, et corrigée si elle sort de l'écran.
    */
   function createFab(options) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-icon rounded-circle rp-fab';
      btn.title = options.title || '';
      btn.setAttribute('aria-label', options.title || '');
      btn.innerHTML = '<i class="' + (options.icon || 'ti ti-plus') + '"></i>';
      document.body.appendChild(btn);

      var storageKey = 'rp_fab_pos_' + (options.id || 'default');
      var dragging = false;
      var moved = false;
      var startX = 0, startY = 0, originLeft = 0, originTop = 0;

      function clamp(left, top) {
         var maxLeft = window.innerWidth - btn.offsetWidth - 4;
         var maxTop = window.innerHeight - btn.offsetHeight - 4;
         return {
            left: Math.max(4, Math.min(left, maxLeft)),
            top: Math.max(4, Math.min(top, maxTop))
         };
      }

      function applyPosition(left, top) {
         var pos = clamp(left, top);
         btn.style.left = pos.left + 'px';
         btn.style.top = pos.top + 'px';
         btn.style.right = 'auto';
         btn.style.bottom = 'auto';
      }

      // Position mémorisée
      try {
         var saved = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
         if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
            applyPosition(saved.left, saved.top);
         }
      } catch (e) { /* position par défaut (CSS) */ }

      btn.addEventListener('pointerdown', function (e) {
         dragging = true;
         moved = false;
         startX = e.clientX;
         startY = e.clientY;
         var rect = btn.getBoundingClientRect();
         originLeft = rect.left;
         originTop = rect.top;
         btn.setPointerCapture(e.pointerId);
      });

      btn.addEventListener('pointermove', function (e) {
         if (!dragging) {
            return;
         }
         var dx = e.clientX - startX;
         var dy = e.clientY - startY;
         if (!moved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
            moved = true;
         }
         if (moved) {
            e.preventDefault();
            applyPosition(originLeft + dx, originTop + dy);
         }
      });

      function endDrag(e) {
         if (!dragging) {
            return;
         }
         dragging = false;
         try {
            btn.releasePointerCapture(e.pointerId);
         } catch (err) { /* ignore */ }

         if (moved) {
            var rect = btn.getBoundingClientRect();
            try {
               window.localStorage.setItem(storageKey, JSON.stringify({
                  left: rect.left,
                  top: rect.top
               }));
            } catch (err) { /* stockage indisponible */ }
         }
      }

      btn.addEventListener('pointerup', endDrag);
      btn.addEventListener('pointercancel', endDrag);

      // Un déplacement ne doit pas déclencher l'action du bouton
      btn.addEventListener('click', function (e) {
         if (moved) {
            e.preventDefault();
            e.stopPropagation();
            moved = false;
            return;
         }
         if (typeof options.onClick === 'function') {
            options.onClick(e);
         }
      });

      // Repositionnement si la fenêtre change de taille
      window.addEventListener('resize', function () {
         var rect = btn.getBoundingClientRect();
         if (btn.style.left) {
            applyPosition(rect.left, rect.top);
         }
      });

      return btn;
   }

   window.RpFab = {
      create: createFab,
      shouldShow: shouldShow,
      isMobile: isMobile,
      root: root
   };

   // =========================================================================
   //  Bouton de signature sur les tickets
   // =========================================================================

   if (!shouldShow(prefs.fab_ticket)) {
      return;
   }

   var ticketId = 0;
   var els = {};
   var modal = null;
   var STORAGE_PENDING = 'rp_pending_sign';
   var STORAGE_CLAIM = 'rp_claim_flash';

   /*
    * Message a afficher au prochain rendu du panneau. La reprise d'un bon
    * recharge la page — l'onglet « Gestion » et le compteur de bons sont rendus
    * cote serveur — donc le message transite par sessionStorage, sinon il
    * disparaitrait avec le rechargement.
    */
   var pendingFlash = '';
   try {
      pendingFlash = window.sessionStorage.getItem(STORAGE_CLAIM) || '';
      if (pendingFlash) {
         window.sessionStorage.removeItem(STORAGE_CLAIM);
      }
   } catch (e) {
      pendingFlash = '';
   }

   /**
    * Détection de la page ticket par le DOM (indépendante de la route GLPI) :
    * le pied de page de la timeline expose les blocs de réponse natifs.
    */
   /**
    * Identifiant du ticket affiché, ou 0 si la page n'est pas une fiche ticket.
    * Plusieurs sources sont testées pour rester indépendant de la mise en page
    * et de la route utilisée par GLPI.
    */
   function findTicketId() {
      // 1) Bloc principal de la timeline : porte l'itemtype et l'identifiant
      var main = document.querySelector('.ITILContent[data-itemtype="Ticket"][data-items-id]');
      if (main) {
         var fromDom = parseInt(main.getAttribute('data-items-id'), 10);
         if (fromDom > 0) {
            return fromDom;
         }
      }

      // 2) Conteneur de la fiche + champ caché items_id
      var container = document.querySelector('#itil-object-container, #itil-footer, #new-itilobject-form');
      if (container) {
         var input = document.querySelector('input[name="items_id"]');
         if (input) {
            var fromInput = parseInt(input.value, 10);
            if (fromInput > 0) {
               return fromInput;
            }
         }
      }

      // 3) Route héritée /front/ticket.form.php?id=N
      if (/\/front\/ticket\.form\.php$/i.test(window.location.pathname || '')) {
         var match = (window.location.search || '').match(/[?&]id=(\d+)/);
         if (match) {
            return parseInt(match[1], 10);
         }
      }

      return 0;
   }

   /**
    * La fiche ticket peut être construite après le chargement initial : on
    * réessaie pendant quelques secondes avant d'abandonner.
    */
   function waitForTicket(callback) {
      var found = findTicketId();
      if (found > 0) {
         callback(found);
         return;
      }

      var attempts = 0;
      var timer = setInterval(function () {
         attempts++;
         var id = findTicketId();
         if (id > 0) {
            clearInterval(timer);
            callback(id);
         } else if (attempts >= 40) { // ~8 secondes
            clearInterval(timer);
         }
      }, 200);
   }

   function buildUI() {
      waitForTicket(function (id) {
         ticketId = id;
         buildTicketUI();
      });
   }

   function buildTicketUI() {
      var wrapper = document.createElement('div');
      wrapper.innerHTML = [
         '<div class="modal fade rp-sheet" id="rpTicketFabModal" tabindex="-1" aria-labelledby="rpTicketFabLabel" aria-hidden="true">',
         // Ordinateur : position GLPI par défaut (en haut) ; téléphone : panneau bas
         '  <div class="modal-dialog">',
         '    <div class="modal-content">',
         '      <div class="modal-header">',
         '        <h5 class="modal-title" id="rpTicketFabLabel"><i class="ti ti-signature me-2"></i>Signatures</h5>',
         '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>',
         '      </div>',
         '      <div class="modal-body">',
         '        <div id="rpTicketFabBody"></div>',
         '      </div>',
         '    </div>',
         '  </div>',
         '</div>'
      ].join('');
      var modalEl = wrapper.firstElementChild;
      document.body.appendChild(modalEl);

      els.modalEl = modalEl;
      els.body = modalEl.querySelector('#rpTicketFabBody');

      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
         modal = new bootstrap.Modal(modalEl, {});
      }

      createFab({
         id: 'ticket',
         icon: 'ti ti-signature',
         title: 'Signatures du ticket',
         onClick: openActions
      });

      /*
       * Réouverture après la bascule.
       *
       * L'association recharge la page — l'onglet « Gestion » et le compteur de
       * bons sont rendus côté serveur. Le panneau se rouvre donc de lui-même sur
       * le message de réussite, sinon l'écran revenait au ticket nu et il fallait
       * rappeler le bouton flottant pour voir ce qui venait de se passer.
       *
       * `pendingFlash` ne vaut quelque chose qu'au retour d'une bascule : c'est
       * lui, et rien d'autre, qui distingue ce rechargement d'un affichage normal.
       */
      if (pendingFlash) {
         openActions();
         return;
      }

      // Enchaînement « tâche ajoutée -> signature »
      handlePendingFlow();
   }

   function openActions() {
      if (!modal) {
         return;
      }
      els.body.innerHTML = '<div class="text-center text-muted py-3">'
         + '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement…</div>';
      modal.show();
      loadActions();
   }

   function loadActions(callback) {
      fetch(root + '/plugins/rp/ajax/ticket_actions.php?ticket_id=' + ticketId, {
         credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
         if (typeof callback === 'function') {
            callback(data);
            return;
         }
         renderActions(data);
      })
      .catch(function () {
         if (typeof callback === 'function') {
            callback(null);
            return;
         }
         els.body.innerHTML = '<div class="alert alert-danger mb-0">Erreur de communication avec GLPI.</div>';
      });
   }

   function renderActions(data) {
      if (!data || !data.ok) {
         els.body.innerHTML = '<div class="alert alert-danger mb-0">Actions indisponibles pour ce ticket.</div>';
         return;
      }

      var html = '';
      if (pendingFlash) {
         html += '<div class="alert alert-success">' + esc(pendingFlash) + '</div>';
         pendingFlash = '';
      }
      if (data.notice) {
         html += '<div class="alert alert-info">' + esc(data.notice) + '</div>';
      }

      var claim = claimSection(data);

      if (!data.actions || !data.actions.length) {
         html += '<div class="alert alert-warning' + (claim ? '' : ' mb-0') + '">'
            + 'Aucune signature disponible pour ce ticket.</div>';
         els.body.innerHTML = html + claim;
         bindClaim(data);
         return;
      }

      /*
       * Étape suivante mise en avant.
       *
       * Le serveur renvoie la CLÉ de l'action recommandée (data.next) ; on
       * ajoute un gros bouton en tête, portant le MÊME index que l'action
       * correspondante. Le dispatch du clic est donc inchangé, et la liste
       * complète reste dessous : rien n'est retiré, l'étape probable est
       * seulement plus rapide à atteindre.
       */
      var nextIndex = -1;
      if (data.next) {
         data.actions.forEach(function (action, index) {
            if (nextIndex === -1 && action.key === data.next) {
               nextIndex = index;
            }
         });
      }
      if (nextIndex !== -1) {
         var next = data.actions[nextIndex];
         html += '<div class="card mb-3">'
            + '<div class="card-body py-3">'
            + '<div class="text-secondary small mb-2">Étape suivante</div>'
            + '<button type="button" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center"'
            + ' data-rp-action="' + nextIndex + '">'
            + '<i class="' + esc(next.icon || 'ti ti-signature') + ' me-2"></i>'
            + esc(next.label)
            + '</button>'
            + '</div></div>';
      }

      html += '<div class="list-group">';
      data.actions.forEach(function (action, index) {
         html += '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center"'
            + ' data-rp-action="' + index + '">'
            + '<i class="' + esc(action.icon || 'ti ti-signature') + ' me-3 fs-3"></i>'
            + '<span class="flex-fill text-start" style="min-width:0">'
            + '<span class="d-block fw-bold">' + esc(action.label) + '</span>'
            + (action.hint
               ? '<span class="d-block text-muted small text-truncate" title="' + esc(action.hint) + '">'
                  + esc(action.hint) + '</span>'
               : '')
            + '</span>'
            + (action.primary ? '<span class="badge bg-primary ms-2 flex-shrink-0">Conseillé</span>' : '')
            + '</button>';
      });
      html += '</div>';

      els.body.innerHTML = html + claim;

      els.body.querySelectorAll('[data-rp-action]').forEach(function (button) {
         button.addEventListener('click', function () {
            var action = data.actions[parseInt(button.getAttribute('data-rp-action'), 10)];
            runAction(action, data);
         });
      });
      bindClaim(data);
   }

   /*
    * Bons cités par ce ticket mais rattachés à un AUTRE ticket.
    *
    * Placé en bas et en petit : c'est une réparation, pas le geste courant.
    * Le panneau nomme le ticket qui détient chaque bon — sans ce numéro, on
    * ne sait pas si le doublon est celui qu'on regarde ou l'autre.
    *
    * Le service est celui du plugin Gestion (`data.gestion_webdir`) : la
    * table des bons lui appartient, RP ne fait que relayer.
    */

   /*
    * Ce que l'utilisateur vient de demander, tant qu'il ne l'a pas confirmé :
    * { ids: [...], text: '...' }, sinon null.
    *
    * Déplacer un bon ne se rattrape pas d'un clic, et sur mobile un panneau
    * qu'on fait défiler se touche par accident : rien ne part sans une
    * seconde intention explicite.
    */
   var claimPending = null;
   var claimError = '';

   function claimSection(data) {
      var rows = (data && data.claimable) || [];
      if (!rows.length) {
         return '';
      }

      var many = rows.length > 1;
      var movable = data.can_claim_bl && !!data.gestion_webdir;
      var html = '<div id="rpClaimBox" class="mt-3 pt-3 border-top">';

      if (claimError) {
         html += '<div class="alert alert-danger py-2 small">' + esc(claimError) + '</div>';
         claimError = '';
      }

      // En attente de confirmation : la liste laisse la place à la question.
      if (claimPending) {
         return html + '<div class="text-secondary small mb-2">'
            + '<i class="ti ti-alert-triangle me-1"></i>Confirmer l\'association'
            + '</div>'
            + '<div class="mb-2">' + esc(claimPending.text) + '</div>'
            + '<div class="d-flex flex-wrap align-items-center gap-2">'
            /*
             * Vert pour ce qui agit, gris pour ce qui renonce : sur une question
             * fermée, la couleur porte la réponse avant que le mot ne soit lu.
             * Les deux gardent la hauteur allégée du bouton d'origine.
             */
            + '<button type="button" class="btn btn-secondary flex-shrink-0 px-3"'
            + ' style="padding-top:.25rem;padding-bottom:.25rem" data-rp-claim-cancel="1">'
            + '<i class="ti ti-x me-1"></i>Annuler'
            + '</button>'
            + '<button type="button" class="btn btn-success flex-shrink-0 px-3 ms-auto"'
            + ' style="padding-top:.25rem;padding-bottom:.25rem" data-rp-claim-confirm="1">'
            + '<i class="ti ti-check me-1"></i>Confirmer'
            + '</button>'
            + '</div></div>';
      }

      html += '<div class="text-secondary small mb-2">'
         + '<i class="ti ti-alert-triangle me-1"></i>'
         + (many ? 'Bons de livraison rattachés à un autre ticket' : 'Bon de livraison rattaché à un autre ticket')
         + '</div>'
         + '<ul class="list-unstyled small text-muted mb-2">';

      rows.forEach(function (row, index) {
         var target = row.ticket_exists
            ? ('ticket #' + row.tickets_id + (row.ticket_name ? ' — ' + esc(row.ticket_name) : ''))
            : ('ticket #' + row.tickets_id + ' (supprimé)');
         var name = esc(row.bl_number || row.bl);
         /*
          * Le NUMERO est l'élément cliquable, pas un bouton ajouté à côté.
          *
          * Un bouton par ligne remplissait le panneau de boutons oranges pour
          * une action qui n'arrive presque jamais ; le numéro, lui, est déjà
          * ce que l'œil cherche, et c'est exactement ce qu'on veut désigner.
          */
         html += '<li class="mb-1">'
            + (movable
               ? '<a href="#" class="fw-bold" data-rp-claim-one="' + index + '">' + name + '</a>'
               : '<span class="fw-bold">' + name + '</span>')
            + ' · ' + target
            + '</li>';
      });
      html += '</ul>';

      if (!movable) {
         html += '<div class="text-muted small fst-italic mb-0">'
            + 'Vous n\'avez pas le droit de déplacer un bon de livraison.</div>';
      } else {
         /*
          * Le bouton solde TOUT ; le geste par bon passe par son numéro. La
          * phrase de gauche est donc là pour annoncer ce second geste, que
          * rien ne signalerait autrement.
          */
         html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">'
            + '<span class="text-muted small">'
            + (many ? 'Touchez un numéro pour n\'associer que celui-là.'
               : 'Il sera retiré de son ticket actuel.')
            + '</span>'
            + '<button type="button" class="btn btn-primary flex-shrink-0 px-3 ms-auto"'
            + ' style="padding-top:.25rem;padding-bottom:.25rem" data-rp-claim-all="1">'
            + '<i class="ti ti-link me-1"></i>'
            + (many ? 'Associer les ' + rows.length + ' à ce ticket' : 'Associer à ce ticket')
            + '</button>'
            + '</div>';
      }

      return html + '</div>';
   }

   function claimRowText(row) {
      return (row.bl_number || row.bl) + ' sera retiré du ticket #' + row.tickets_id
         + ' et associé à ce ticket.';
   }

   function bindClaim(data) {
      var box = els.body.querySelector('#rpClaimBox');
      if (!box) {
         return;
      }
      var rows = (data && data.claimable) || [];

      box.querySelectorAll('[data-rp-claim-one]').forEach(function (link) {
         link.addEventListener('click', function (event) {
            event.preventDefault();
            var row = rows[parseInt(link.getAttribute('data-rp-claim-one'), 10)];
            if (row) {
               claimPending = { ids: [row.id], text: claimRowText(row) };
               redrawClaim(data);
            }
         });
      });

      var all = box.querySelector('[data-rp-claim-all]');
      if (all) {
         all.addEventListener('click', function () {
            claimPending = {
               ids: rows.map(function (row) { return row.id; }),
               text: rows.length > 1
                  ? ('Les ' + rows.length + ' bons seront retirés de leur ticket actuel et associés à ce ticket.')
                  : claimRowText(rows[0])
            };
            redrawClaim(data);
         });
      }

      var cancel = box.querySelector('[data-rp-claim-cancel]');
      if (cancel) {
         cancel.addEventListener('click', function () {
            claimPending = null;
            redrawClaim(data);
         });
      }

      var confirm = box.querySelector('[data-rp-claim-confirm]');
      if (confirm) {
         confirm.addEventListener('click', function () { claimBl(confirm, data); });
      }
   }

   /**
    * Redessine la SEULE section des bons.
    *
    * Le reste du panneau n'a pas bougé ; le reconstruire ferait sauter la
    * liste des signatures sous le doigt, et sur mobile on perdrait sa place
    * dans le panneau à chaque aller-retour vers la confirmation.
    */
   function redrawClaim(data) {
      var box = els.body.querySelector('#rpClaimBox');
      if (box) {
         box.outerHTML = claimSection(data);
         bindClaim(data);
      }
   }

   /**
    * La CAUSE, pas « échec ».
    *
    * Un bouton qui se contente d'annoncer son échec n'apprend rien : selon le
    * code renvoyé, il faut recharger la page, demander un droit, ou aller lire
    * le journal. Le code brut est conservé pour les cas non prévus — il vaut
    * mieux le montrer que de le taire.
    */
   function claimErrorText(res) {
      var code = (res && res.error) || '';
      if (code === 'invalid_csrf') {
         return 'Session expirée : rechargez la page, puis réessayez.';
      }
      if (code === 'forbidden') {
         return 'Droits insuffisants pour déplacer un bon sur ce ticket.';
      }
      if (code === 'claim_failed') {
         return 'Le déplacement a échoué (détail dans le journal plugin-gestion).';
      }
      return 'Association impossible' + (code ? ' (' + code + ')' : '') + '.';
   }

   /**
    * Bascule des bons confirmés, puis rechargement de la page.
    *
    * L'onglet « Gestion » et le compteur de bons sont rendus côté serveur :
    * sans rechargement ils continueraient à afficher le ticket tel qu'il
    * était avant le déplacement. Le message de réussite transite donc par
    * sessionStorage, sinon il partirait avec la page.
    */
   function claimBl(button, data) {
      var pending = claimPending;
      if (!pending || !pending.ids.length) {
         return;
      }
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Association…';

      var body = new URLSearchParams();
      body.append('ticket_id', String(ticketId));
      body.append('_glpi_csrf_token', claimCsrf());
      pending.ids.forEach(function (id) { body.append('ids[]', String(id)); });

      fetch(data.gestion_webdir + '/ajax/claim_bl.php', {
         method: 'POST',
         credentials: 'same-origin',
         /*
          * GLPI 11 contrôle le jeton CSRF dans un écouteur du noyau, AVANT
          * d'atteindre le script (CheckCsrfListener). Signalée AJAX, la requête
          * voit son jeton lu dans `X-Glpi-Csrf-Token` et CONSERVÉ ; sinon il est
          * lu dans le corps et CONSOMMÉ — le contrôle du script échouait alors
          * juste après, sur un jeton que GLPI venait lui-même de retirer.
          */
         headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Glpi-Csrf-Token': claimCsrf()
         },
         body: body.toString()
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
         claimPending = null;
         if (!res || !res.ok) {
            claimError = claimErrorText(res);
            redrawClaim(data);
            return;
         }
         var moved = (res.moved || []).length;
         if (!moved) {
            claimError = 'Aucun bon n\'a pu être associé.';
            redrawClaim(data);
            return;
         }
         try {
            window.sessionStorage.setItem(
               STORAGE_CLAIM,
               moved > 1 ? (moved + ' bons de livraison ont été associés à ce ticket.')
                  : 'Le bon de livraison a été associé à ce ticket.'
            );
         } catch (e) { /* non bloquant */ }
         window.location.reload();
      })
      .catch(function () {
         claimPending = null;
         claimError = 'Erreur de communication avec GLPI.';
         redrawClaim(data);
      });
   }

   function claimCsrf() {
      var meta = document.querySelector('meta[property="glpi:csrf_token"]');
      if (meta && meta.content) {
         return meta.content;
      }
      var input = document.querySelector('input[name="_glpi_csrf_token"]');
      return input ? input.value : '';
   }

   /**
    * Ouvre le modal de signature existant : celui du plugin RP
    * (rp_loadCriForm) ou celui du plugin Gestion (gestion_loadCriForm).
    */
   function runAction(action, data) {
      if (!action) {
         return;
      }
      if (modal) {
         modal.hide();
      }

      setTimeout(function () {
         if (action.mode === 'gestion') {
            if (typeof gestion_loadCriForm === 'function') {
               var params = {
                  job: ticketId,
                  root_doc: data.gestion_webdir,
                  root_modal: 'ticket-form'
               };
               // « Signer le BL seul » : mode déjà prévu par le plugin Gestion
               if (action.bl_only) {
                  params.force_bl = 1;
               }
               gestion_loadCriForm('showCriForm', String(action.bl_id), params);
            } else {
               window.location.href = data.gestion_webdir + '/front/survey.form.php?id=' + action.bl_id;
            }
            return;
         }

         if (typeof rp_loadCriForm === 'function') {
            rp_loadCriForm('showCriForm', action.modal, {
               job: ticketId,
               root_doc: data.rp_webdir
            });
         }
      }, 250);
   }

   // ---- Parcours « compléter l'intervention » -------------------------------

   /**
    * Ouvre le formulaire de tâche natif de GLPI (bloc repliable de la timeline)
    * puis, une fois la tâche enregistrée et la page rechargée, ouvre le modal
    * de signature du rapport.
    */
   function handlePendingFlow() {
      var params = new URLSearchParams(window.location.search);

      if (params.get('rp_action') === 'newtask') {
         setPending(ticketId);
         // On retire le paramètre de l'URL : après enregistrement, GLPI revient
         // sur la page précédente et le formulaire de tâche se rouvrirait.
         params.delete('rp_action');
         if (window.history && window.history.replaceState) {
            var query = params.toString();
            window.history.replaceState({}, document.title,
               window.location.pathname + (query ? '?' + query : '') + window.location.hash);
         }
         openNativeTaskForm();
         return;
      }

      if (getPending() === ticketId) {
         clearPending();
         // La tâche vient d'être enregistrée : on enchaîne sur la signature
         loadActions(function (data) {
            if (!data || !data.ok || !data.actions || !data.actions.length) {
               return;
            }
            var preferred = data.actions.filter(function (a) { return a.primary; })[0] || data.actions[0];
            runAction(preferred, data);
         });
      }
   }

   function openNativeTaskForm() {
      var attempts = 0;
      var tryOpen = function () {
         attempts++;
         var button = document.querySelector('#itil-footer .answer-action[data-bs-target="#new-TicketTask-block"]');
         if (button) {
            button.click();
            setTimeout(function () {
               var block = document.querySelector('#new-TicketTask-block');
               if (block && typeof block.scrollIntoView === 'function') {
                  block.scrollIntoView({ behavior: 'smooth', block: 'center' });
               }
            }, 350);
            return;
         }
         // Repli : ouverture directe du bloc repliable
         var target = document.querySelector('#new-TicketTask-block');
         if (target && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
            bootstrap.Collapse.getOrCreateInstance(target).show();
            return;
         }
         if (attempts < 20) {
            setTimeout(tryOpen, 200);
         }
      };
      setTimeout(tryOpen, 300);
   }

   function setPending(id) {
      try {
         window.sessionStorage.setItem(STORAGE_PENDING, String(id));
      } catch (e) { /* ignore */ }
   }

   function getPending() {
      try {
         return parseInt(window.sessionStorage.getItem(STORAGE_PENDING) || '0', 10);
      } catch (e) {
         return 0;
      }
   }

   function clearPending() {
      try {
         window.sessionStorage.removeItem(STORAGE_PENDING);
      } catch (e) { /* ignore */ }
   }

   function esc(text) {
      return String(text === undefined || text === null ? '' : text)
         .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', buildUI);
   } else {
      buildUI();
   }
})();
