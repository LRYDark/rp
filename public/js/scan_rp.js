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
         results: modalEl.querySelector('#rpScanResults')
      };

      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
         modal = new bootstrap.Modal(modalEl, {});
      }

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
      body.append('_glpi_csrf_token', csrf());

      fetch(ajaxUrl, {
         method: 'POST',
         credentials: 'same-origin',
         headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
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
         renderResults(data.results || []);
      })
      .catch(function () {
         els.results.innerHTML = '';
         message('Erreur de communication avec GLPI.');
      });
   }

   function renderResults(items) {
      els.results.innerHTML = '';
      if (!items.length) {
         els.results.innerHTML = '<div class="list-group-item text-muted">Aucun résultat.</div>';
         return;
      }
      items.forEach(function (item) {
         var row = document.createElement('div');
         row.className = 'list-group-item';

         var badge = '';
         if (item.badge && item.badge.label) {
            badge = '<span class="badge ms-2 '
               + (item.badge.style === 'warn' ? 'bg-warning text-dark' : 'bg-success')
               + '">' + esc(item.badge.label) + '</span>';
         }

         var actions = '';
         (item.actions || []).forEach(function (action) {
            actions += '<a class="btn btn-sm '
               + (action.primary ? 'btn-primary' : 'btn-outline-secondary')
               + ' me-2 mt-2" href="' + esc(action.url) + '">'
               + '<i class="' + esc(action.icon || 'ti ti-arrow-right') + ' me-1"></i>'
               + esc(action.label) + '</a>';
         });

         row.innerHTML =
            '<div class="d-flex align-items-center flex-wrap">'
            + '<span class="fw-bold">' + esc(item.title || '') + '</span>' + badge
            + '</div>'
            + (item.subtitle ? '<div class="text-muted small">' + esc(item.subtitle) + '</div>' : '')
            + '<div class="d-flex flex-wrap">' + actions + '</div>';

         els.results.appendChild(row);
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
