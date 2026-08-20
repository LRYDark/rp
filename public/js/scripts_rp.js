// Fonction d'initialisation globale appelée depuis le PHP
function initializeSignatureRp(uniqId) {
  // ---------- Signature ----------
  // Moteur réécrit : l'historique vectoriel normalisé (0..1) est la source de
  // vérité unique ; base et modale n'en sont que des rendus. Les coordonnées
  // sont converties avec une échelle séparée par axe, mesurée au moment du
  // tracé : aucun décalage possible même si le CSS réduit le canvas.
  (function () {
    const root = document.getElementById(uniqId);
    if (!root) return;

    // Elements
    const originalCanvas = root.querySelector("#sig-canvas-" + uniqId);
    const modalCanvas    = root.querySelector("#modal-canvas-" + uniqId);
    const modalOverlay   = root.querySelector(".sig-modal");
    const btnZoom        = root.querySelector(".zoom-btn");
    const btnClearBase   = root.querySelector("#sig-clearBtn-" + uniqId);
    // Classes dédiées : le plugin Gestion style `.btn-validate` / `.btn-clear`
    // en vert et jaune, ce qui écraserait l'habillage GLPI des boutons.
    const btnValidate    = root.querySelector(".sig-btn-validate");
    const btnClearModal  = root.querySelector(".sig-btn-clear");
    const canvasWrapper  = root.querySelector(".cri-canvas-wrapper");
    if (!originalCanvas || !modalCanvas) return;

    // Fenêtre d'agrandissement : modal natif GLPI (Bootstrap).
    // Le formulaire est lui-même affiché dans un modal GLPI : imbriquer deux
    // modals Bootstrap laisse un voile résiduel qui masque toute la page à la
    // fermeture. On sort donc la fenêtre à la racine du document.
    let bsModal = null;
    if (modalOverlay) {
      if (modalOverlay.id) {
        // Le formulaire peut être rechargé plusieurs fois : on retire la
        // fenêtre précédemment déplacée pour ne pas cumuler les doublons.
        document.querySelectorAll("body > .sig-modal#" + CSS.escape(modalOverlay.id))
          .forEach((old) => { if (old !== modalOverlay) old.remove(); });
      }
      if (modalOverlay.parentElement !== document.body) {
        document.body.appendChild(modalOverlay);
      }
      if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        bsModal = bootstrap.Modal.getOrCreateInstance(modalOverlay, {});
      }
    }

    const originalCtx = originalCanvas.getContext("2d");
    const modalCtx    = modalCanvas.getContext("2d");

    // Épaisseurs (px CSS)
    const BASE_LINE        = 2.00; // tracé live sur le canvas de base
    const MODAL_LINE       = 1.80; // tracé dans la modale
    const BASE_EXPORT_LINE = 2.40; // re-rendu sur la base à la validation de la modale

    let modalIsOpen = false;

    // ---------- Historique vectoriel ----------
    // Chaque trait = { pts: [{x,y}...] en coordonnées 0..1, w: épaisseur
    // exprimée EN FRACTION de la largeur du canvas où il a été tracé.
    // Mémoriser l'épaisseur relative est indispensable : un trait dessiné dans
    // la grande fenêtre doit être réduit dans les mêmes proportions que le
    // dessin quand il est reporté dans la petite zone, sinon il paraît énorme.
    let paths = [];
    let currentPath = null;

    // Épaisseur minimale à l'affichage, pour rester visible dans le PDF
    const MIN_RENDER_LINE = 0.9;

    /**
     * Événement -> coordonnées relatives.
     *
     * Les DEUX axes sont divisés par la LARGEUR : les proportions du tracé sont
     * ainsi conservées telles quelles. C'est ce qui permet à la zone de dessin
     * d'avoir n'importe quelle forme (haute sur un téléphone, large sur un
     * écran) sans jamais déformer la signature.
     */
    function getNorm(e, canvas) {
      const rect = canvas.getBoundingClientRect();
      const p = e.touches?.[0] || e.changedTouches?.[0] || e;
      const w = rect.width || 1;
      return {
        x: (p.clientX - rect.left) / w,
        y: (p.clientY - rect.top)  / w
      };
    }

    // Épaisseur en px bitmap équivalente à cssLine px CSS affichés
    function lineWidthFor(canvas, cssLine) {
      const rect = canvas.getBoundingClientRect();
      const s = rect.width > 0 ? canvas.width / rect.width : (window.devicePixelRatio || 1);
      return Math.max(1, cssLine * s);
    }

    function setupStroke(ctx, lw) {
      ctx.strokeStyle = "#000";
      ctx.lineCap = "round";
      ctx.lineJoin = "round";
      ctx.lineWidth = lw;
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = "high";
    }

    function drawSegmentNorm(ctx, canvas, from, to, cssLine) {
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      setupStroke(ctx, lineWidthFor(canvas, cssLine));
      ctx.beginPath();
      // Les deux axes utilisent la largeur : cf. getNorm()
      ctx.moveTo(from.x * canvas.width, from.y * canvas.width);
      ctx.lineTo(to.x   * canvas.width, to.y   * canvas.width);
      ctx.stroke();
    }

    /**
     * Redessine tout l'historique. Les coordonnées stockées sont multipliées
     * par la largeur du canvas : le tracé garde donc exactement ses
     * proportions, et son épaisseur suit la même échelle.
     */
    function renderHistoryOn(canvas, ctx, fallbackCssLine) {
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      const rect = canvas.getBoundingClientRect();
      const cssWidth = rect.width || canvas.width;
      const W = canvas.width;

      for (const path of paths) {
        const pts = path.pts || path; // tolère un ancien historique
        if (!pts || pts.length < 2) continue;

        const cssLine = (typeof path.w === "number" && path.w > 0)
          ? Math.max(MIN_RENDER_LINE, path.w * cssWidth)
          : fallbackCssLine;

        setupStroke(ctx, lineWidthFor(canvas, cssLine));
        ctx.beginPath();
        ctx.moveTo(pts[0].x * W, pts[0].y * W);
        for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i].x * W, pts[i].y * W);
        ctx.stroke();
      }
    }

    /**
     * Recadre le tracé pour qu'il occupe au mieux un canvas donné, sans jamais
     * le déformer : on mesure l'encombrement réel de la signature, on l'agrandit
     * ou on le réduit d'un seul facteur, et on le centre.
     *
     * Les coordonnées stockées sont réécrites : la zone de dessin peut donc
     * changer de forme (pivot du téléphone, passage à la petite case) sans que
     * le tracé sorte du cadre ni ne se déforme.
     */
    function refitPathsTo(canvas) {
      let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      for (const path of paths) {
        const pts = path.pts || path;
        if (!pts) continue;
        for (const q of pts) {
          if (q.x < minX) minX = q.x;
          if (q.x > maxX) maxX = q.x;
          if (q.y < minY) minY = q.y;
          if (q.y > maxY) maxY = q.y;
        }
      }
      if (!isFinite(minX) || canvas.width <= 0 || canvas.height <= 0) return;

      const bw = maxX - minX;
      const bh = maxY - minY;
      // Un simple point ou un trait minuscule ne doit pas être agrandi
      if (bw < 0.02 && bh < 0.02) return;

      const padX = canvas.width * 0.04;
      const padY = canvas.height * 0.06;
      const availW = Math.max(1, canvas.width - 2 * padX);
      const availH = Math.max(1, canvas.height - 2 * padY);
      const fitFactor = Math.min(availW / Math.max(bw, 1e-6), availH / Math.max(bh, 1e-6));

      // Échelle « naturelle » : celle d'un report direct, sans retouche.
      // Si le tracé tient déjà tel quel, on ne change RIEN — la signature
      // garde exactement la taille et la position d'avant. On ne réduit que
      // lorsqu'elle déborderait du cadre.
      const naturalFactor = canvas.width;
      if (fitFactor >= naturalFactor) {
        return;
      }
      const factor = fitFactor;

      const offX = (canvas.width - bw * factor) / 2 - minX * factor;
      const offY = (canvas.height - bh * factor) / 2 - minY * factor;
      const inv = 1 / canvas.width;

      for (const path of paths) {
        const pts = path.pts || path;
        if (!pts) continue;
        for (const q of pts) {
          q.x = (q.x * factor + offX) * inv;
          q.y = (q.y * factor + offY) * inv;
        }
        if (typeof path.w === "number") {
          path.w = path.w * factor * inv;
        }
      }
    }

    function setCanvasSize(canvas, cssW, cssH) {
      const dpr = window.devicePixelRatio || 1;
      canvas.style.width  = cssW + "px";
      canvas.style.height = cssH + "px";
      canvas.width  = Math.max(1, Math.round(cssW * dpr));
      canvas.height = Math.max(1, Math.round(cssH * dpr));
    }

    // ---------- Canvas de base ----------
    const initRect = originalCanvas.getBoundingClientRect();
    const INITIAL_BASE_W = Math.max(200, Math.round(initRect.width  || originalCanvas.clientWidth  || 320));
    const INITIAL_BASE_H = Math.max( 60, Math.round(initRect.height || originalCanvas.clientHeight ||  80));
    const BASE_ASPECT = INITIAL_BASE_W / INITIAL_BASE_H || 4;

    function adaptCanvasSize() {
      const container = originalCanvas.closest(".signature-container") || originalCanvas.parentElement;
      if (!container) return;
      const cs = getComputedStyle(container);
      const padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
      const cssW = Math.max(200, Math.floor(container.clientWidth - padX));
      const cssH = Math.max(60, Math.round(cssW / BASE_ASPECT));
      if (parseInt(originalCanvas.style.width, 10) === cssW
          && parseInt(originalCanvas.style.height, 10) === cssH) {
        return;
      }
      // Le bitmap est préservé : une signature déportée peut avoir été chargée en image
      const backup = document.createElement("canvas");
      backup.width  = originalCanvas.width;
      backup.height = originalCanvas.height;
      const hasBitmap = backup.width > 0 && backup.height > 0;
      if (hasBitmap) backup.getContext("2d").drawImage(originalCanvas, 0, 0);
      setCanvasSize(originalCanvas, cssW, cssH);
      if (hasBitmap) {
        originalCtx.setTransform(1, 0, 0, 1, 0, 0);
        originalCtx.imageSmoothingEnabled = true;
        originalCtx.imageSmoothingQuality = "high";
        originalCtx.drawImage(backup, 0, 0, backup.width, backup.height,
                                      0, 0, originalCanvas.width, originalCanvas.height);
      }
    }

    setCanvasSize(originalCanvas, INITIAL_BASE_W, INITIAL_BASE_H);
    adaptCanvasSize();
    const baseContainer = originalCanvas.closest(".signature-container") || originalCanvas.parentElement;
    if (baseContainer && "ResizeObserver" in window) {
      new ResizeObserver(() => adaptCanvasSize()).observe(baseContainer);
    }
    window.addEventListener("load", adaptCanvasSize);

    // ---------- Modale : taille d'après le rect réel du wrapper ----------
    /**
     * Mesure d'une unité de hauteur (`svh`, `lvh`, `dvh`) par une sonde
     * invisible. Sert uniquement au relevé de diagnostic : la mise en page,
     * elle, s'appuie sur `visualViewport` (voir visibleHeight).
     */
    const unitProbes = {};
    function measuredUnit(unit) {
      try {
        let probe = unitProbes[unit];
        if (!probe || !probe.isConnected) {
          probe = document.createElement("div");
          probe.setAttribute("aria-hidden", "true");
          probe.style.cssText =
            "position:fixed;top:0;left:0;width:0;" +
            "height:100vh;height:100" + unit + ";" +
            "visibility:hidden;pointer-events:none;";
          document.body.appendChild(probe);
          unitProbes[unit] = probe;
        }
        return Math.round(probe.getBoundingClientRect().height);
      } catch (e) {
        return 0;
      }
    }

    /** Valeur réelle d'une marge de sécurité de l'écran (encoche, barre d'accueil). */
    const envProbes = {};
    function measuredEnv(name) {
      try {
        let probe = envProbes[name];
        if (!probe || !probe.isConnected) {
          probe = document.createElement("div");
          probe.setAttribute("aria-hidden", "true");
          probe.style.cssText =
            "position:fixed;top:0;left:0;width:0;height:0;" +
            "height:env(" + name + ", 0px);" +
            "visibility:hidden;pointer-events:none;";
          document.body.appendChild(probe);
          envProbes[name] = probe;
        }
        return Math.round(probe.getBoundingClientRect().height);
      } catch (e) {
        return 0;
      }
    }

    /**
     * Hauteur réellement visible.
     *
     * Aucune source n'est fiable seule sur iOS, on les croise donc :
     * `visualViewport.height` sous-estime en paysage (Safari masque sa barre
     * mais continue de la déduire), la sonde `100svh` sous-estime toujours
     * (barres supposées déployées) et `100vh` surestime toujours. `100dvh`
     * décrit l'état courant : c'est la valeur utile, et `100lvh` sert de borne
     * haute pour les navigateurs qui ignorent `dvh`.
     *
     * Un écouteur `resize` sur `visualViewport` relance la mise en page à
     * chaque changement, et `fitToVisible()` corrige ensuite par la mesure
     * aussi bien un excès qu'un manque.
     */
    let cachedVisibleHeight = null;

    /** À appeler au début de chaque passe de mise en page. */
    function invalidateVisibleHeight() {
      cachedVisibleHeight = null;
    }

    function visibleHeight() {
      if (cachedVisibleHeight !== null) {
        return cachedVisibleHeight;
      }
      const vv = window.visualViewport;
      let h = Math.round((vv && vv.height) ? vv.height : window.innerHeight);

      // `dvh` = hauteur disponible dans l'état COURANT des barres du
      // navigateur. En paysage, Safari masque sa barre d'outils mais continue
      // de la déduire de `visualViewport.height` : la fenêtre était alors calée
      // sur une hauteur qui n'existe plus, d'où la bande vide en bas. On retient
      // donc la plus grande des deux. En portrait la barre reste affichée, les
      // deux valeurs coïncident et rien ne change.
      const dvh = measuredUnit("dvh");
      if (dvh > 0) {
        h = Math.max(h, dvh);
      }

      // Jamais au-delà du viewport large : borne les navigateurs qui ignorent
      // `dvh` et retombent alors sur `100vh`.
      const lvh = measuredUnit("lvh");
      if (lvh > 0) {
        h = Math.min(h, lvh);
      }
      cachedVisibleHeight = h;
      return h;
    }

    /* ------------------------------------------------------------------
     * Relevé de mesures (diagnostic)
     *
     * Les modèles de « viewport » d'iOS ne sont pas observables depuis le
     * poste de développement : plusieurs hypothèses plausibles donnent la même
     * mise en page en paysage et divergent en portrait. Ce relevé affiche les
     * valeurs réelles du téléphone ET trace des repères de couleur, pour que
     * l'écart se lise directement sur une capture d'écran.
     *
     * Activation : ajouter `sigdebug=1` à l'URL (mémorisé ensuite pour le
     * navigateur). Désactivation : `sigdebug=0`.
     * ------------------------------------------------------------------ */
    function sigDebugEnabled() {
      try {
        const url = String(location.search) + String(location.hash);
        if (url.indexOf("sigdebug=1") !== -1) {
          localStorage.setItem("rp_sig_debug", "1");
        } else if (url.indexOf("sigdebug=0") !== -1) {
          localStorage.removeItem("rp_sig_debug");
        }
        return localStorage.getItem("rp_sig_debug") === "1";
      } catch (e) {
        return false;
      }
    }

    let debugPanel = null;
    const debugMarks = {};

    function debugMark(name, color, y) {
      let mark = debugMarks[name];
      if (!mark || !mark.isConnected) {
        mark = document.createElement("div");
        mark.setAttribute("aria-hidden", "true");
        mark.style.cssText =
          "position:fixed;left:0;width:100%;height:0;border-top:2px dashed " +
          color + ";z-index:2147483646;pointer-events:none;";
        document.body.appendChild(mark);
        debugMarks[name] = mark;
      }
      mark.style.top = Math.round(y) + "px";
    }

    function renderSigDebug() {
      if (!sigDebugEnabled()) return;
      const vv = window.visualViewport;
      const de = document.documentElement;
      const overlayRect = modalOverlay ? modalOverlay.getBoundingClientRect() : null;
      const footer = modalOverlay ? modalOverlay.querySelector(".modal-footer") : null;
      const footerRect = footer ? footer.getBoundingClientRect() : null;
      const wrapRect = canvasWrapper ? canvasWrapper.getBoundingClientRect() : null;

      const svh = measuredUnit("svh");
      const lvh = measuredUnit("lvh");
      const dvh = measuredUnit("dvh");

      // Repères : vert = hauteur annoncée visible, rouge = repli `svh`,
      // bleu = bas réel de la fenêtre, orange = bas réel des boutons.
      debugMark("vv", "#00c000", vv ? vv.height : window.innerHeight);
      debugMark("svh", "#e00000", svh);
      if (overlayRect) debugMark("overlay", "#0060ff", overlayRect.bottom);
      if (footerRect) debugMark("footer", "#ff8c00", footerRect.bottom);

      const r = (n) => (n == null ? "-" : Math.round(n));
      const lines = [
        "orient  " + (window.innerWidth > window.innerHeight ? "paysage" : "portrait"),
        "screen  " + r(screen.width) + "x" + r(screen.height) + "  dpr " + (window.devicePixelRatio || 1),
        "inner   " + r(window.innerWidth) + "x" + r(window.innerHeight),
        "client  " + r(de.clientWidth) + "x" + r(de.clientHeight),
        "vv      h " + r(vv && vv.height) + "  offTop " + r(vv && vv.offsetTop) +
          "  pageTop " + r(vv && vv.pageTop) + "  scale " + (vv ? vv.scale : "-"),
        "units   svh " + r(svh) + "  lvh " + r(lvh) + "  dvh " + r(dvh) +
          "  barre " + r(lvh - svh),
        "safe    haut " + r(measuredEnv("safe-area-inset-top")) +
          "  bas " + r(measuredEnv("safe-area-inset-bottom")),
        "overlay top " + r(overlayRect && overlayRect.top) + "  bot " + r(overlayRect && overlayRect.bottom) +
          "  h " + r(overlayRect && overlayRect.height),
        "footer  top " + r(footerRect && footerRect.top) + "  bot " + r(footerRect && footerRect.bottom),
        "wrapper top " + r(wrapRect && wrapRect.top) + "  bot " + r(wrapRect && wrapRect.bottom),
        "canvas  " + r(modalCanvas && parseFloat(modalCanvas.style.width)) + "x" +
          r(modalCanvas && parseFloat(modalCanvas.style.height)),
        "calcul  visibleHeight " + r(visibleHeight()),
      ];

      if (!debugPanel || !debugPanel.isConnected) {
        debugPanel = document.createElement("pre");
        debugPanel.setAttribute("aria-hidden", "true");
        debugPanel.style.cssText =
          "position:fixed;left:0;top:0;margin:0;padding:4px 6px;" +
          "max-width:100%;background:rgba(0,0,0,.82);color:#fff;" +
          "font:600 10px/1.35 ui-monospace,Menlo,Consolas,monospace;" +
          "white-space:pre;z-index:2147483647;pointer-events:none;";
        document.body.appendChild(debugPanel);
      }
      debugPanel.textContent = lines.join("\n");
    }

    function clearSigDebug() {
      if (debugPanel && debugPanel.isConnected) debugPanel.remove();
      Object.keys(debugMarks).forEach((k) => {
        if (debugMarks[k] && debugMarks[k].isConnected) debugMarks[k].remove();
      });
    }

    function sizeModalCanvas() {
      const wrapper = canvasWrapper;
      if (!wrapper) return;
      const r  = wrapper.getBoundingClientRect();
      const cs = getComputedStyle(wrapper);
      const padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight)  || 0);
      const padY = (parseFloat(cs.paddingTop)  || 0) + (parseFloat(cs.paddingBottom) || 0);
      // La zone occupe TOUT l'espace disponible, sans contrainte de format :
      // imposer le ratio de la petite case (4:1) réduisait la hauteur utile à
      // « largeur / 4 » et laissait une immense bande vide sur un téléphone.
      const w = Math.max(160, Math.floor(r.width - padX));

      // Hauteur bornée à ce qui est RÉELLEMENT visible : on mesure où commence
      // la zone et jusqu'où va l'écran utile. Ainsi elle ne peut pas passer
      // sous la barre du navigateur, quelle que soit la géométrie du modal.
      // `visibleBottom` est exprimé dans le même repère que `r.top` : celui de
      // la fenêtre ancrée en haut de la zone visible (voir applyModalHeight).
      const visibleBottom = visibleHeight();
      const room = Math.floor(visibleBottom - r.top - padY - 8);
      const h = Math.max(120, Math.min(Math.floor(r.height - padY), room));

      // Rien à faire si la zone n'a pas changé de taille : réallouer le bitmap
      // et redessiner tout l'historique pour un résultat identique coûtait cher
      // en paysage, où la zone est bien plus grande, et se produisait à chaque
      // événement de redimensionnement du navigateur.
      if (parseInt(modalCanvas.style.width, 10) === w
          && parseInt(modalCanvas.style.height, 10) === h) {
        return;
      }

      setCanvasSize(modalCanvas, w, h);
      // Le tracé est recadré dans la nouvelle zone : il reste entièrement
      // visible et à ses proportions, même après un pivot de l'écran.
      refitPathsTo(modalCanvas);
      renderHistoryOn(modalCanvas, modalCtx, MODAL_LINE);
    }

    /**
     * Hauteur réellement visible.
     *
     * Sur iOS, `position: fixed` couvre la zone SOUS la barre d'outils de
     * Safari : le pied de page du modal se retrouve caché derrière, ce qui
     * oblige à faire défiler. `visualViewport` donne la hauteur réellement
     * visible ; on la transmet au CSS pour que la fenêtre s'y adapte.
     */
    /**
     * Cale la fenêtre sur la zone réellement visible.
     *
     * La fenêtre est laissée collée en HAUT (`top: 0`) : sur iOS, un élément
     * `position: fixed` est ancré au viewport VISUEL dès que la page ne défile
     * plus — et la page est justement figée pendant la signature. Ajouter
     * `visualViewport.offsetTop` revenait donc à compter le décalage une
     * seconde fois : en paysage `offsetTop` vaut 0 et le rendu était correct,
     * en portrait il ne l'est pas, ce qui poussait la fenêtre vers le bas
     * (espace en haut, zone de tracé débordant sous la barre de Safari).
     * Seule la HAUTEUR est imposée, à partir de la zone réellement utilisable.
     */
    function applyModalHeight() {
      if (!modalOverlay) return;

      const h = visibleHeight();
      if (h <= 0) return;

      modalOverlay.style.position = "fixed";
      modalOverlay.style.left = "0px";
      modalOverlay.style.width = "100%";
      modalOverlay.style.top = "0px";
      modalOverlay.style.height = h + "px";
    }

    /**
     * Rattrapage par constat, dans les DEUX sens.
     *
     * Le calcul de hauteur repose sur le modèle de « viewport » du navigateur,
     * dont iOS s'écarte de façons difficiles à prévoir. Plutôt que de faire
     * confiance au calcul, on mesure où le bas du bloc blanc atterrit vraiment
     * et on corrige l'écart : on retranche s'il déborde, on ajoute s'il laisse
     * une bande vide. La correction est donc valable quelle que soit la cause,
     * et ne suppose plus qu'un dépassement soit le seul défaut possible.
     *
     * Garde-fous : trois passes au maximum, arrêt dès que le bas ne bouge plus
     * (une règle CSS le bloque, inutile d'étirer la fenêtre dans le vide, on
     * revient alors à la hauteur d'origine) et hauteur bornée au double de la
     * zone visible.
     *
     * La zone de tracé n'est PAS redimensionnée à chaque passe : seule la
     * hauteur de la fenêtre change, et c'est elle seule que mesurent les
     * passes. Redimensionner le canvas réalloue son bitmap et redessine tout
     * l'historique — en paysage, où la zone est bien plus grande, le faire
     * trois fois de suite pour rien coûtait très cher.
     */
    function fitToVisible() {
      if (!modalOverlay) return false;
      const content = modalOverlay.querySelector(".modal-content");
      if (!content) return false;

      const original = modalOverlay.style.height;
      let lastBottom = null;
      let changed = false;

      for (let pass = 0; pass < 3; pass++) {
        const target = visibleHeight();
        const bottom = Math.round(content.getBoundingClientRect().bottom);
        const delta = target - bottom;
        if (Math.abs(delta) <= 1) return changed;

        if (lastBottom !== null && bottom === lastBottom) {
          // Le contenu ne suit plus la fenêtre : on ne gagne rien à l'étirer.
          modalOverlay.style.height = original;
          return true;
        }
        lastBottom = bottom;

        const current = Math.round(parseFloat(modalOverlay.style.height) || target);
        const next = Math.max(200, Math.min(target * 2, current + delta));
        if (next === current) return changed;
        modalOverlay.style.height = next + "px";
        changed = true;
      }
      return changed;
    }

    let refreshQueued = false;
    function refreshModalLayout() {
      if (!modalIsOpen || refreshQueued) return;
      refreshQueued = true;
      // deux frames pour laisser le reflow (pivot mobile) se stabiliser
      requestAnimationFrame(() => requestAnimationFrame(() => {
        refreshQueued = false;
        if (!modalIsOpen) return;
        // Les sondes de hauteur ne sont mesurées qu'une fois pour toute la
        // passe : leur lecture force un recalcul de mise en page, et le
        // viewport, lui, ne bouge pas pendant qu'on ajuste la fenêtre.
        invalidateVisibleHeight();
        // La hauteur de la fenêtre est arrêtée d'abord — calcul puis
        // rattrapage par la mesure — et la zone de tracé n'est dimensionnée
        // qu'ensuite, une seule fois.
        applyModalHeight();
        fitToVisible();
        sizeModalCanvas();
        renderSigDebug();
      }));
    }

    // ---------- Dessin ----------
    let drawing = false;
    let activeCanvas = null;
    let lastNorm = null;

    function start(e, canvas) {
      e.preventDefault();
      drawing = true;
      activeCanvas = canvas;
      lastNorm = getNorm(e, canvas);
      // Épaisseur mémorisée en fraction de la largeur du canvas utilisé
      const cssLine = (canvas === modalCanvas) ? MODAL_LINE : BASE_LINE;
      const cssWidth = canvas.getBoundingClientRect().width || canvas.width || 1;
      currentPath = { pts: [lastNorm], w: cssLine / cssWidth };
      if (e.pointerId != null) { try { canvas.setPointerCapture(e.pointerId); } catch (err) {} }
    }

    function move(e) {
      if (!drawing || !activeCanvas) return;
      const p = getNorm(e, activeCanvas);
      if (activeCanvas === modalCanvas) {
        drawSegmentNorm(modalCtx, modalCanvas, lastNorm, p, MODAL_LINE);
      } else {
        drawSegmentNorm(originalCtx, originalCanvas, lastNorm, p, BASE_LINE);
      }
      lastNorm = p;
      if (currentPath) currentPath.pts.push(p);
    }

    function end(e) {
      if (!drawing) return;
      drawing = false;
      if (currentPath && currentPath.pts.length > 1) paths.push(currentPath);
      currentPath = null;
      if (e && e.pointerId != null && activeCanvas) {
        try { activeCanvas.releasePointerCapture(e.pointerId); } catch (err) {}
      }
    }

    function bindCanvas(canvas) {
      canvas.addEventListener("pointerdown", (e) => start(e, canvas));
      canvas.addEventListener("pointermove", move);
      canvas.addEventListener("pointerup",   end);
      canvas.addEventListener("pointercancel", end);
      canvas.addEventListener("touchstart", e => e.preventDefault(), { passive: false });
      canvas.addEventListener("touchmove",  e => e.preventDefault(), { passive: false });
    }
    bindCanvas(originalCanvas);
    bindCanvas(modalCanvas);

    // ---------- Effacer ----------
    function wipeAll() {
      paths = [];
      currentPath = null;
      originalCtx.setTransform(1, 0, 0, 1, 0, 0);
      originalCtx.clearRect(0, 0, originalCanvas.width, originalCanvas.height);
      modalCtx.setTransform(1, 0, 0, 1, 0, 0);
      modalCtx.clearRect(0, 0, modalCanvas.width, modalCanvas.height);
    }

    if (btnClearBase) {
      btnClearBase.addEventListener("click", () => {
        wipeAll();
        const hidden = document.getElementById("sig-dataUrl");
        if (hidden) hidden.value = "";
      });
    }
    if (btnClearModal) {
      btnClearModal.addEventListener("click", () => { wipeAll(); });
    }

    // ---------- Ouverture / fermeture (modal natif GLPI) ----------
    if (btnZoom) {
      btnZoom.addEventListener("click", () => {
        if (bsModal) {
          bsModal.show();
        }
      });
    }

    if (modalOverlay) {
      // Le canvas ne peut être dimensionné qu'une fois le modal réellement
      // affiché (avant, le corps du modal n'a pas de dimensions).
      /*
       * Verrou de défilement.
       *
       * Sur iOS, `overflow: hidden` sur le body ne bloque pas le défilement :
       * la page derrière continue de glisser, ce qui donne l'impression que la
       * fenêtre elle-même défile et fait sortir les boutons de l'écran. Seul
       * `position: fixed` fige réellement la page ; on mémorise la position
       * pour la restaurer à la fermeture.
       */
      let lockedScrollY = 0;

      function lockPageScroll() {
        lockedScrollY = window.scrollY || window.pageYOffset || 0;
        document.documentElement.classList.add("no-scroll");
        document.body.classList.add("no-scroll");
        document.body.style.top = (-lockedScrollY) + "px";
      }

      /**
       * Restauration INSTANTANÉE de la position.
       *
       * GLPI charge Tabler, qui pose `:root { scroll-behavior: smooth }`. La
       * page ayant été figée en haut pendant la signature, un simple
       * `scrollTo()` déclenchait une animation sur toute la hauteur du ticket :
       * la carte de signature, et donc le tracé qui vient d'être validé,
       * n'apparaissaient qu'au bout de ce voyage. On neutralise le défilement
       * animé le temps de la remise en place.
       */
      function withoutSmoothScroll(action) {
        const docEl = document.documentElement;
        const previous = docEl.style.scrollBehavior;
        docEl.style.scrollBehavior = "auto";
        try {
          action();
        } finally {
          docEl.style.scrollBehavior = previous;
        }
      }

      function unlockPageScroll() {
        document.documentElement.classList.remove("no-scroll");
        document.body.classList.remove("no-scroll");
        document.body.style.top = "";
        withoutSmoothScroll(() => window.scrollTo(0, lockedScrollY));
      }

      // Rien ne doit défiler à l'intérieur de la fenêtre : sa mise en page
      // garantit que tout tient à l'écran.
      modalOverlay.addEventListener("touchmove", (e) => {
        if (e.cancelable) e.preventDefault();
      }, { passive: false });

      modalOverlay.addEventListener("show.bs.modal", () => {
        // Hors passe de mise en page : la valeur mémorisée date de l'ouverture
        // précédente, il faut la remesurer.
        invalidateVisibleHeight();
        applyModalHeight();
        lockPageScroll();
      });
      modalOverlay.addEventListener("shown.bs.modal", () => {
        modalIsOpen = true;
        refreshModalLayout();
        // Les barres du navigateur peuvent encore être en train de se déployer
        // au moment de l'ouverture : on remesure une fois l'animation passée,
        // sans quoi la fenêtre resterait calée sur un état transitoire.
        setTimeout(refreshModalLayout, 200);
        setTimeout(refreshModalLayout, 500);
      });

      // Le focus doit sortir de la fenêtre AVANT qu'elle ne soit masquée,
      // sinon le navigateur signale un élément focalisé rendu inaccessible.
      modalOverlay.addEventListener("hide.bs.modal", () => {
        const active = document.activeElement;
        if (active && modalOverlay.contains(active) && typeof active.blur === "function") {
          active.blur();
        }
      });

      modalOverlay.addEventListener("hidden.bs.modal", () => {
        modalIsOpen = false;
        clearSigDebug();
        unlockPageScroll();
        // On rend la fenêtre à son état d'origine pour ne rien figer
        modalOverlay.style.position = "";
        modalOverlay.style.left = "";
        modalOverlay.style.width = "";
        modalOverlay.style.top = "";
        modalOverlay.style.height = "";
        if (btnZoom && typeof btnZoom.focus === "function") {
          try { btnZoom.focus({ preventScroll: true }); } catch (e) {}
        }
        // La position d'avant l'agrandissement vient d'être restaurée : la
        // carte de signature est donc déjà à l'écran dans l'immense majorité
        // des cas. On ne déplace la page que si elle ne l'est pas, et sans
        // animation — un second défilement animé par-dessus le premier était
        // la seconde moitié de l'attente ressentie.
        if (root && typeof root.getBoundingClientRect === "function") {
          const rect = root.getBoundingClientRect();
          const viewHeight = window.innerHeight || document.documentElement.clientHeight || 0;
          const alreadyVisible = rect.top < viewHeight && rect.bottom > 0;
          if (!alreadyVisible && typeof root.scrollIntoView === "function") {
            withoutSmoothScroll(() => {
              try {
                root.scrollIntoView({ block: "center" });
              } catch (e) {
                root.scrollIntoView();
              }
            });
          }
        }
      });
    }

    function closeModal() {
      if (bsModal) {
        bsModal.hide();
      }
      modalIsOpen = false;
    }

    // ---------- Valider : re-rendu vectoriel sur la base ----------
    // Le tracé est ré-appliqué sur le petit canvas à partir de l'historique
    // normalisé : la signature s'adapte exactement à la zone du PDF, quelle que
    // soit la taille utilisée pour la dessiner.
    if (btnValidate) {
      btnValidate.addEventListener("click", () => {
        // La signature est adaptée à la petite case : mise à l'échelle et
        // centrée, sans déformation, quelle que soit la taille utilisée pour
        // la tracer. C'est ce rendu qui part dans le PDF.
        refitPathsTo(originalCanvas);
        renderHistoryOn(originalCanvas, originalCtx, BASE_EXPORT_LINE);
        closeModal();
      });
    }

    // ---------- Écoutes globales ----------
    window.addEventListener("orientationchange", () => {
      adaptCanvasSize();
      refreshModalLayout();
      // certains navigateurs mobiles ne stabilisent le viewport qu'après coup
      setTimeout(() => { adaptCanvasSize(); refreshModalLayout(); }, 250);
    });
    window.addEventListener("resize", () => {
      adaptCanvasSize();
      refreshModalLayout();
    });
    if (window.visualViewport) {
      // iOS : la hauteur visible change quand les barres du navigateur
      // apparaissent ou disparaissent
      window.visualViewport.addEventListener("resize", refreshModalLayout);
      // Pas d'écoute de `scroll` : la page est figée pendant la signature et la
      // fenêtre n'est plus calée sur `offsetTop`. L'événement se déclenchait à
      // chaque image d'animation des barres du navigateur et relançait une
      // passe de mise en page complète pour rien.
    }

    // Champ hidden
    const submitBtn  = document.getElementById("sig-submitBtn");
    const hiddenArea = document.getElementById("sig-dataUrl");
    if (submitBtn && hiddenArea && !submitBtn.dataset.sigInit) {
      submitBtn.dataset.sigInit = "1";
      submitBtn.addEventListener("click", function () {
        hiddenArea.value = originalCanvas.toDataURL();
      });
    }

    // Anti double-tap zoom iOS (une seule fois par page)
    if (!document.documentElement.dataset.sigNoDoubleTap) {
      document.documentElement.dataset.sigNoDoubleTap = "1";
      document.addEventListener("touchend", (function () {
        let last = 0;
        return function (e) { const now = Date.now(); if (now - last < 300) e.preventDefault(); last = now; };
      })(), { passive: false });
    }
  })();
}

/**
 * Titre du modal selon le document demandé, pour que le technicien identifie
 * tout de suite ce qu'il est en train de générer.
 */
function rp_getCriFormTitle(modal) {
    switch (modal) {
        case 'form_client':
            return __('Fiche de prise en charge', 'rp');
        case 'form_rapport':
            return __("Rapport d'intervention", 'rp');
        case 'form_rapport_hotline':
            return __('Rapport hotline', 'rp');
        case 'form_preparation':
            return __("Rapport d'atelier", 'rp');
        default:
            return __('Rapport / Fiche de prise en charge', 'rp');
    }
}

function rp_loadCriForm(action, modal, params) {
    var formInput;

    if (params.form != undefined) {
        formInput = getRpFormData($('form[name="' + params.form + '"]'));
    }

    $.ajax({
        url: params.root_doc + '/ajax/cri.php',
        type: "POST",
        dataType: "html",
        data: {
            'action': action,
            'params': params,
            'pdf_action': params.pdf_action,
            'formInput': formInput,
            'modal': modal
        },
        success: function (response, opts) {
            try {
                var json = $.parseJSON(response);
                if (!json.success) {
                    $("#rp_cri_error").html(json.message).show().delay(2000).fadeOut('slow');
                }

            } catch (err) {
                $('#' + modal).html(response);

                switch (action) {

                    case 'saveCri':
                        // $('#' + modal).dialog('close');
                        window.location.reload();
                        break;
                    default:
                        glpi_html_dialog({
                            title: rp_getCriFormTitle(modal),
                            body: response,
                            id: action,
                        })
                        break;
                }
            }
        },
        // Sans cela, un refus de droits (403) ou une erreur serveur ne
        // produisait strictement RIEN à l'écran : le bouton semblait mort.
        error: function (xhr) {
            var message = (xhr && xhr.responseText) ? xhr.responseText : '';
            if (!message || message.length > 300) {
                message = __('Le formulaire n\'a pas pu être ouvert.', 'rp')
                    + ' (' + ((xhr && xhr.status) || '?') + ')';
            }
            var box = $("#rp_cri_error");
            if (box.length) {
                box.html(message).show();
            } else {
                alert(message);
            }
        }
    });
}

/*
 * Après génération d'un rapport : refermer la fenêtre et rafraîchir le ticket.
 *
 * Les formulaires de rapport s'envoient dans un NOUVEL ONGLET (target="_blank"),
 * pour que la page du ticket reste vivante. Effet de bord : la fenêtre de saisie
 * reste elle aussi ouverte, avec un jeton CSRF que l'envoi vient de consommer —
 * GLPI 11 invalide le jeton dès qu'il a servi. Un second « Générer » sans
 * recharger était donc rejeté avec une erreur d'accès.
 *
 * On referme la fenêtre et on recharge le ticket : le prochain formulaire sera
 * demandé au serveur, donc muni d'un jeton neuf, et l'onglet affiche au passage
 * le rapport qui vient d'être produit ainsi que l'étape suivante mise à jour.
 */
/*
 * Posé UNE SEULE FOIS pour la durée de la page.
 *
 * Chaque bascule d'un modal remplace son contenu par $().html(), ce qui
 * re-télécharge et ré-exécute ce fichier. Sans ce garde-fou, un écouteur
 * s'ajoutait à chaque changement d'avis : autant de fermetures et de
 * rechargements programmés que d'allers-retours entre les formulaires.
 */
if (!document.documentElement.dataset.rpSubmitReloadBound) {
document.documentElement.dataset.rpSubmitReloadBound = '1';

document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || (form.name !== 'formReport' && form.name !== 'formPreparation')) {
        return;
    }

    /*
     * Retour visuel immédiat.
     *
     * Générer un rapport prend plusieurs secondes — images, fusion, envoi du
     * mail. Sans rien à l'écran, le technicien croit son clic perdu et
     * recommence ; le second envoi part alors avec un jeton CSRF déjà consommé
     * et se fait refuser. Le voile occupe l'attente ET bloque ce second clic.
     *
     * Le plugin Gestion pose déjà le sien sur ses propres formulaires : on ne
     * doublonne pas, sinon deux voiles se superposent.
     */
    if (!document.getElementById('gestion-loader')) {
        var overlay = document.getElementById('rp-loader');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'rp-loader';
            overlay.className = 'rp-loader-overlay';
            overlay.innerHTML = '<div class="rp-loader-spinner"></div>'
                + '<div class="rp-loader-text"></div>';
            document.body.appendChild(overlay);
        }

        /*
         * Le mot suit ce que le technicien vient de faire : « Signature » quand
         * le client a signé à l'écran, « Génération » quand le document part
         * sans lui — rapport d'atelier, ou rapport dont la signature client est
         * désactivée dans les réglages. C'est le formulaire qui le dit, via
         * data-rp-signature.
         *
         * Réécrit à chaque envoi : le voile est réutilisé d'une bascule à
         * l'autre, et garderait sinon le mot du formulaire précédent.
         */
        var withSign = form.getAttribute('data-rp-signature') === '1';
        var label = overlay.querySelector('.rp-loader-text');
        if (label) {
            label.textContent = withSign
                ? 'Signature en cours, veuillez patienter...'
                : 'Génération en cours, veuillez patienter...';
        }

        overlay.classList.add('active');
    }

    var modalEl = form.closest ? form.closest('.modal') : null;
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
            instance.hide();
        }
    }

    // Délai : laisser le navigateur ouvrir l'onglet du PDF avant de recharger.
    setTimeout(function () {
        window.location.reload();
    }, 1500);
}, true);

} // fin du garde-fou rpSubmitReloadBound

/**
 * Bascule entre les deux documents depuis le haut d'un modal de rapport.
 *
 * Le formulaire est rechargé DANS le modal déjà ouvert, jamais dans un second :
 * empiler deux fenêtres Bootstrap laisse un voile résiduel qui masque la page à
 * la fermeture. Même mécanisme que la bascule « Rapport / Rapport + BL » du
 * plugin Gestion, éprouvée de longue date.
 *
 * @param {HTMLInputElement} radio bouton radio dont la valeur est le nom du modal cible
 */
function rp_switchReportForm(radio) {
    try {
        var wrap = radio.closest ? radio.closest('[data-rp-params]') : null;
        if (!wrap) {
            return;
        }
        var params = {};
        try {
            params = JSON.parse(wrap.getAttribute('data-rp-params') || '{}');
        } catch (e) {
            params = {};
        }

        var container = radio.closest('.modal-body') || radio.closest('.modal-content') || wrap.parentElement;
        if (!container) {
            return;
        }

        $.ajax({
            url: (params.root_doc || '') + '/ajax/cri.php',
            type: 'POST',
            dataType: 'html',
            timeout: 15000,
            // from_atelier : le formulaire cible saura qu'il vient de l'atelier
            // et gardera la question « Que devient le matériel ? » en tête,
            // pour que le choix reste modifiable après la bascule.
            data: {
                action: 'showCriForm',
                params: params,
                modal: radio.value,
                from_atelier: 1
            }
        }).done(function (html) {
            // .html() de jQuery exécute les scripts du formulaire rechargé
            $(container).html(html);
        }).fail(function () {
            alert("Impossible de charger le formulaire.");
        });
    } catch (e) {
        console.error(e);
    }
}
