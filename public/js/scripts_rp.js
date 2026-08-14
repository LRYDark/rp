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
    const modalOverlay   = root.querySelector(".signature-modal");
    const btnZoom        = root.querySelector(".zoom-btn");
    const btnClearBase   = root.querySelector("#sig-clearBtn-" + uniqId);
    const btnValidate    = root.querySelector(".btn-validate");
    const btnClearModal  = root.querySelector(".btn-clear");
    const btnCancel      = root.querySelector(".btn-cancel");
    const rotateGate     = root.querySelector(".rotate-gate");
    const rotateCloseBtn = root.querySelector(".rotate-close-btn");
    if (!originalCanvas || !modalCanvas) return;

    const originalCtx = originalCanvas.getContext("2d");
    const modalCtx    = modalCanvas.getContext("2d");

    // Épaisseurs (px CSS)
    const BASE_LINE        = 2.00; // tracé live sur le canvas de base
    const MODAL_LINE       = 1.80; // tracé dans la modale
    const BASE_EXPORT_LINE = 2.40; // re-rendu sur la base à la validation de la modale

    let modalIsOpen = false;

    // ---------- Historique vectoriel ----------
    let paths = [];        // chaque trait = [{x,y} ...] en coordonnées normalisées 0..1
    let currentPath = null;

    // Événement -> coordonnées normalisées, échelle séparée par axe (anti-décalage)
    function getNorm(e, canvas) {
      const rect = canvas.getBoundingClientRect();
      const p = e.touches?.[0] || e.changedTouches?.[0] || e;
      return {
        x: (p.clientX - rect.left) / (rect.width  || 1),
        y: (p.clientY - rect.top)  / (rect.height || 1)
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
      ctx.moveTo(from.x * canvas.width, from.y * canvas.height);
      ctx.lineTo(to.x   * canvas.width, to.y   * canvas.height);
      ctx.stroke();
    }

    function renderHistoryOn(canvas, ctx, cssLine) {
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      setupStroke(ctx, lineWidthFor(canvas, cssLine));
      const W = canvas.width, H = canvas.height;
      for (const path of paths) {
        if (path.length < 2) continue;
        ctx.beginPath();
        ctx.moveTo(path[0].x * W, path[0].y * H);
        for (let i = 1; i < path.length; i++) ctx.lineTo(path[i].x * W, path[i].y * H);
        ctx.stroke();
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
    const isMobilePhone = () => Math.min(window.innerWidth, window.innerHeight) <= 768;
    const isLandscape   = () => window.innerWidth > window.innerHeight;

    function sizeModalCanvas() {
      const wrapper = root.querySelector(".cri-canvas-wrapper");
      if (!wrapper) return;
      const r  = wrapper.getBoundingClientRect();
      const cs = getComputedStyle(wrapper);
      const padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight)  || 0);
      const padY = (parseFloat(cs.paddingTop)  || 0) + (parseFloat(cs.paddingBottom) || 0);
      let w = Math.max(200, Math.floor(r.width  - padX));
      let h = Math.max(100, Math.floor(r.height - padY));
      // même ratio que la base pour que l'historique normalisé ne soit pas déformé
      if (w / h > BASE_ASPECT) w = Math.floor(h * BASE_ASPECT); else h = Math.floor(w / BASE_ASPECT);
      setCanvasSize(modalCanvas, w, h);
      renderHistoryOn(modalCanvas, modalCtx, MODAL_LINE);
    }

    let refreshQueued = false;
    function refreshModalLayout() {
      if (!modalIsOpen || refreshQueued) return;
      refreshQueued = true;
      // deux frames pour laisser le reflow (pivot mobile) se stabiliser
      requestAnimationFrame(() => requestAnimationFrame(() => {
        refreshQueued = false;
        if (!modalIsOpen) return;
        if (isMobilePhone() && !isLandscape()) {
          rotateGate?.classList.add("show");
        } else {
          rotateGate?.classList.remove("show");
          sizeModalCanvas();
        }
      }));
    }

    // ---------- Dessin ----------
    let drawing = false;
    let activeCanvas = null;
    let lastNorm = null;

    function start(e, canvas) {
      e.preventDefault();
      if (canvas === modalCanvas && rotateGate && rotateGate.classList.contains("show")) return;
      drawing = true;
      activeCanvas = canvas;
      lastNorm = getNorm(e, canvas);
      currentPath = [lastNorm];
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
      if (currentPath) currentPath.push(p);
    }

    function end(e) {
      if (!drawing) return;
      drawing = false;
      if (currentPath && currentPath.length > 1) paths.push(currentPath);
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

    // ---------- Ouverture / fermeture modale ----------
    if (btnZoom) {
      btnZoom.addEventListener("click", () => {
        modalIsOpen = true;
        document.documentElement.classList.add("no-scroll");
        modalOverlay.classList.add("active");
        modalOverlay.removeAttribute("aria-hidden");
        modalOverlay.removeAttribute("inert");
        setTimeout(() => {
          const focusTarget = rotateCloseBtn || btnCancel || btnValidate || modalOverlay;
          if (focusTarget && typeof focusTarget.focus === "function") { try { focusTarget.focus(); } catch (e) {} }
        }, 0);
        refreshModalLayout();
      });
    }

    function closeModal() {
      modalIsOpen = false;
      try {
        const ae = document.activeElement;
        if (ae && modalOverlay && modalOverlay.contains(ae)) {
          if (btnZoom && typeof btnZoom.focus === "function") { btnZoom.focus(); }
        }
      } catch (e) {}
      modalOverlay.classList.remove("active");
      rotateGate?.classList.remove("show");
      document.documentElement.classList.remove("no-scroll");
      modalOverlay?.setAttribute("aria-hidden", "true");
      modalOverlay?.setAttribute("inert", "");
    }
    if (btnCancel) btnCancel.addEventListener("click", closeModal);

    // Fermer le message pivot sans tourner : signature possible en portrait (échappatoire)
    if (rotateCloseBtn) {
      rotateCloseBtn.addEventListener("click", () => {
        rotateGate?.classList.remove("show");
        sizeModalCanvas();
      });
    }

    // ---------- Valider : re-rendu vectoriel sur la base ----------
    if (btnValidate) {
      btnValidate.addEventListener("click", () => {
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
      window.visualViewport.addEventListener("resize", refreshModalLayout);
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
                            title: __('Rapport / Fiche de prise en charge', 'rp'),
                            body: response,
                            id: action,
                        })
                        break;
                }
            }
        }
    });
}
