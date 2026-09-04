/**
 * Lien mobile des tickets : bouton de copie, et lien dans le message de
 * création d'un ticket.
 *
 * Chargé sur toutes les pages dès que l'utilisateur a le droit de partager le
 * lien et n'a pas masqué les deux endroits dans ses préférences. Deux rôles :
 *
 *  1. le bouton « Copier » (`data-rp-copy-link`) — celui du champ de la fiche
 *     du ticket comme celui ajouté dans les toasts ;
 *  2. l'ajout du lien dans le toast qui annonce la création d'un ticket.
 *
 * Le toast n'est pas le nôtre. Celui de la fiche classique est rendu par le
 * noyau à la page suivante (« Élément ajouté : Ticket #12 ») ; celui d'un
 * formulaire GLPI est construit par `glpi_toast_info` à partir d'une réponse
 * JSON que le plugin ne peut pas modifier. On ne peut ni les remplacer, ni
 * intercepter `glpi_toast_info` (déclaré en `const`, donc hors d'atteinte).
 * Reste à les OBSERVER : Bootstrap émet `shown.bs.toast` sur chaque toast
 * affiché, et l'événement remonte jusqu'au document.
 *
 * Pour chaque toast contenant un lien vers un ticket, le serveur est interrogé
 * (ajax/mobilelink.php) : il ne répond que pour les tickets que CETTE session
 * vient de créer. C'est lui qui distingue une création d'une modification —
 * le navigateur ne fait que poser la question.
 *
 * Déclaration lue dans <meta name="rp:mobilelink"> (posée par setup.php).
 */
(function () {
   'use strict';

   var META_NAME = 'rp:mobilelink';

   function readDeclaration() {
      var el = document.querySelector('meta[name="' + META_NAME + '"]');
      if (!el) {
         return null;
      }
      try {
         var decl = JSON.parse(el.getAttribute('content') || '{}');
         return (decl && typeof decl === 'object') ? decl : null;
      } catch (e) {
         return null;
      }
   }

   var decl = readDeclaration();
   if (!decl) {
      return;
   }
   var labels = decl.labels || {};

   /* ---------------------------------------------------------------------
    *  Copie du lien
    * ------------------------------------------------------------------- */

   function flash(button, text, ok) {
      var icon = button.innerHTML;
      button.innerHTML = '<i class="ti ' + (ok ? 'ti-check text-success' : 'ti-alert-triangle text-warning') + '"></i>';
      button.setAttribute('title', text);
      setTimeout(function () { button.innerHTML = icon; }, 1800);
   }

   /**
    * `navigator.clipboard` n'existe QUE dans un contexte sécurisé : sur un GLPI
    * servi en http — le cas de bien des installations sur réseau local — il est
    * simplement absent, et un bouton qui s'appuierait sur lui seul ne ferait
    * rien du tout, sans erreur visible. D'où le repli sur `execCommand('copy')`,
    * obsolète mais universel, puis la sélection du champ en dernier recours :
    * l'utilisateur n'a alors qu'à copier lui-même.
    */
   function copyFrom(input, button) {
      var okText = labels.copied || '';
      var koText = labels.failed || '';

      if (navigator.clipboard && window.isSecureContext) {
         navigator.clipboard.writeText(input.value)
            .then(function () { flash(button, okText, true); })
            .catch(function () { input.select(); flash(button, koText, false); });
         return;
      }

      input.select();
      input.setSelectionRange(0, input.value.length);
      var done = false;
      try {
         done = document.execCommand('copy');
      } catch (e) {
         done = false;
      }
      flash(button, done ? okText : koText, done);
   }

   if (!window.rpMobileLinkBound) {
      window.rpMobileLinkBound = true;
      document.addEventListener('click', function (event) {
         var button = event.target.closest('[data-rp-copy-link]');
         if (!button) {
            return;
         }
         event.preventDefault();
         var input = document.getElementById(button.getAttribute('data-rp-copy-link'));
         if (input) {
            copyFrom(input, button);
         }
      });
   }

   /* ---------------------------------------------------------------------
    *  Lien dans le message de création
    * ------------------------------------------------------------------- */

   if (parseInt(decl.toast, 10) !== 1
       || typeof decl.ajax !== 'string' || decl.ajax === ''
       || typeof window.fetch !== 'function') {
      return;
   }

   // Lien vers un ticket tel que le noyau l'écrit (CommonDBTM::getLink), avec
   // ou sans autres paramètres devant l'identifiant.
   var TICKET_RE = /\/front\/ticket\.form\.php\?(?:[^#]*&)?id=(\d+)/;
   var delay = parseInt(decl.delay, 10) || 30000;

   function ticketIdsIn(toastEl) {
      var ids = [];
      toastEl.querySelectorAll('.toast-body a[href]').forEach(function (a) {
         var m = TICKET_RE.exec(a.getAttribute('href') || '');
         if (!m) {
            return;
         }
         var id = parseInt(m[1], 10);
         if (id > 0 && ids.indexOf(id) === -1) {
            ids.push(id);
         }
      });
      return ids;
   }

   /**
    * Prolonge l'affichage du toast une fois le lien ajouté.
    *
    * Dix secondes suffisent à lire « Élément ajouté », pas à copier un lien.
    * Bootstrap ne permet pas de changer le délai d'un toast déjà affiché par
    * son API publique ; recréer l'instance rejouerait l'animation d'entrée.
    * On fait donc exactement ce que fait Bootstrap lui-même à l'affichage :
    * annuler le compte à rebours en cours et le relancer avec le nouveau
    * délai. `_maybeScheduleHide` respecte la souris posée sur le toast, comme
    * d'habitude. Ces méthodes sont internes : tout est vérifié avant usage, et
    * si Bootstrap change, le toast garde simplement son délai d'origine.
    */
   function extendDelay(toastEl) {
      try {
         var Toast = window.bootstrap && window.bootstrap.Toast;
         var inst = Toast ? Toast.getInstance(toastEl) : null;
         if (!inst || !inst._config
             || typeof inst._clearTimeout !== 'function'
             || typeof inst._maybeScheduleHide !== 'function') {
            return;
         }
         if ((parseInt(inst._config.delay, 10) || 0) >= delay) {
            return;
         }
         inst._config.delay = delay;
         inst._clearTimeout();
         inst._maybeScheduleHide();
      } catch (e) {
         // délai d'origine conservé
      }
   }

   /**
    * Le bloc ajouté au toast : même champ et même bouton que sur la fiche du
    * ticket, en taille réduite. Construit par le DOM, jamais par du HTML
    * assemblé : l'URL et le titre du ticket viennent du serveur.
    */
   function buildBlock(id, link, withNumber) {
      var domId = 'rp_mobile_link_toast_' + id;

      var wrap = document.createElement('div');
      wrap.className = 'rp-mobilelink-toast mt-2';

      var label = document.createElement('label');
      label.className = 'form-label mb-1';
      label.htmlFor = domId;
      var labelIcon = document.createElement('i');
      labelIcon.className = 'ti ti-device-mobile me-1';
      label.appendChild(labelIcon);
      label.appendChild(document.createTextNode(
         (labels.title || 'Lien mobile') + (withNumber ? ' (#' + id + ')' : '')
      ));

      var group = document.createElement('div');
      group.className = 'input-group input-group-sm';

      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'form-control';
      input.readOnly = true;
      input.id = domId;
      input.value = link.url;
      input.addEventListener('click', function () { this.select(); });

      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-outline-secondary px-3';
      button.setAttribute('data-rp-copy-link', domId);
      button.title = labels.copy || '';
      button.setAttribute('aria-label', labels.copy || '');
      var copyIcon = document.createElement('i');
      copyIcon.className = 'ti ti-copy';
      button.appendChild(copyIcon);

      group.appendChild(input);
      group.appendChild(button);
      wrap.appendChild(label);
      wrap.appendChild(group);
      return wrap;
   }

   function enrich(toastEl) {
      if (toastEl.dataset.rpMobilelink) {
         return;
      }
      toastEl.dataset.rpMobilelink = '1';

      var ids = ticketIdsIn(toastEl);
      if (!ids.length) {
         return;
      }

      var query = ids.map(function (id) {
         return encodeURIComponent('tickets_id[]') + '=' + id;
      }).join('&');

      fetch(decl.ajax + '?' + query, {
         credentials: 'same-origin',
         headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
         .then(function (response) { return response.ok ? response.json() : null; })
         .then(function (data) {
            if (!data || !data.links || !document.body.contains(toastEl)) {
               return;
            }
            var body = toastEl.querySelector('.toast-body');
            if (!body) {
               return;
            }
            var found = ids.filter(function (id) {
               return data.links[id] && typeof data.links[id].url === 'string' && data.links[id].url !== '';
            });
            found.forEach(function (id) {
               body.appendChild(buildBlock(id, data.links[id], found.length > 1));
            });
            if (found.length) {
               extendDelay(toastEl);
            }
         })
         .catch(function () {
            // silencieux : le toast reste tel quel
         });
   }

   document.addEventListener('shown.bs.toast', function (event) {
      var el = event.target;
      if (el && el.classList && el.classList.contains('toast')) {
         enrich(el);
      }
   });
})();
