/* ============================================================================
 * Pinegrap Lightbox + Carousel Enhancements
 * ----------------------------------------------------------------------------
 * Fancybox-inspired image viewer with pan, pinch/wheel zoom, swipe nav,
 * keyboard shortcuts, and a thumbnail strip. Dependency-free vanilla JS.
 *
 * Public API (window.pgLightbox):
 *   open(images, startIndex=0, opts={})  - open a custom image array
 *   close()                              - close the active overlay
 *
 * Auto-init: any element with `[data-pg-lb-gallery="ID"]` becomes a trigger.
 * Clicking it opens a gallery composed of every other element on the page
 * sharing the same `data-pg-lb-gallery` value, sorted by `data-pg-lb-index`.
 * Each trigger contributes its `src` (or `data-pg-lb-src` override) to the
 * gallery and the alt/title to the caption.
 * ========================================================================== */

(function (global) {
    'use strict';
    if (global.pgLightbox && global.pgLightbox.__pgInit) return; // idempotent

    // ── Tunables ────────────────────────────────────────────────────────────
    var MIN_ZOOM        = 1;       // 1x = fit-to-screen baseline
    var MAX_ZOOM        = 6;       // max user-driven zoom
    var WHEEL_STEP      = 0.18;    // % zoom per wheel notch (decimal scale)
    var DBLCLICK_ZOOM   = 2.4;     // double-click zoom level
    var SWIPE_THRESHOLD = 60;      // px horizontal drag at scale=1 to flip slide
    var SWIPE_VELOCITY  = 0.4;     // px/ms — fast flick triggers nav at lower threshold
    var TAP_MAX_MOVE    = 8;       // px movement still counts as tap (close on background)
    var TAP_MAX_TIME    = 220;     // ms hold still counts as tap

    // ── State ───────────────────────────────────────────────────────────────
    var images = [];        // [{src, alt}]
    var currentIndex = 0;
    var overlay = null;     // root element
    var stage   = null;
    var img     = null;
    var thumbs  = null;
    var counter = null;
    var caption = null;
    var spinner = null;
    var prevBtn = null;
    var nextBtn = null;
    var openedAt = 0;

    // Per-image transform state (rebuilt on each navigation)
    var scale = 1;
    var tx = 0, ty = 0;       // image translate (px)
    var imgNatural = { w: 0, h: 0 };
    var stageRect  = { w: 0, h: 0 };
    var fitScale   = 1;       // computed contain-fit scale (image natural → stage)

    // ── DOM build (lazy — first open) ───────────────────────────────────────
    function build() {
        if (overlay) return;
        overlay = document.createElement('div');
        overlay.className = 'pgl-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = [
            '<div class="pgl-toolbar">',
                '<span class="pgl-counter" data-pgl="counter">0 / 0</span>',
                '<span class="pgl-spacer"></span>',
                '<button type="button" class="pgl-btn" data-pgl="zoom-out" title="Uzaklaştır (−)" aria-label="Uzaklaştır"><i class="bi bi-zoom-out"></i></button>',
                '<button type="button" class="pgl-btn" data-pgl="zoom-in"  title="Yakınlaştır (+)" aria-label="Yakınlaştır"><i class="bi bi-zoom-in"></i></button>',
                '<button type="button" class="pgl-btn" data-pgl="reset"    title="Sıfırla (0)"     aria-label="Sıfırla"><i class="bi bi-arrows-angle-contract"></i></button>',
                '<button type="button" class="pgl-btn" data-pgl="fullscreen" title="Tam ekran (F)" aria-label="Tam ekran"><i class="bi bi-arrows-fullscreen"></i></button>',
                '<button type="button" class="pgl-btn" data-pgl="close"    title="Kapat (Esc)"     aria-label="Kapat"><i class="bi bi-x-lg"></i></button>',
            '</div>',
            '<button type="button" class="pgl-nav pgl-prev" data-pgl="prev" aria-label="Önceki"><i class="bi bi-chevron-left"></i></button>',
            '<button type="button" class="pgl-nav pgl-next" data-pgl="next" aria-label="Sonraki"><i class="bi bi-chevron-right"></i></button>',
            '<div class="pgl-stage" data-pgl="stage">',
                '<img class="pgl-img" data-pgl="img" alt="" draggable="false">',
                '<div class="pgl-spinner" data-pgl="spinner" aria-hidden="true"></div>',
                '<div class="pgl-error">Görsel yüklenemedi.</div>',
                '<div class="pgl-caption" data-pgl="caption"></div>',
            '</div>',
            '<div class="pgl-thumbs" data-pgl="thumbs"></div>'
        ].join('');
        document.body.appendChild(overlay);

        stage   = overlay.querySelector('[data-pgl="stage"]');
        img     = overlay.querySelector('[data-pgl="img"]');
        thumbs  = overlay.querySelector('[data-pgl="thumbs"]');
        counter = overlay.querySelector('[data-pgl="counter"]');
        caption = overlay.querySelector('[data-pgl="caption"]');
        spinner = overlay.querySelector('[data-pgl="spinner"]');
        prevBtn = overlay.querySelector('[data-pgl="prev"]');
        nextBtn = overlay.querySelector('[data-pgl="next"]');

        // iPhone Safari has no element fullscreen at all — do not offer a dead button.
        if (!fullscreenSupported()) {
            var fsBtn = overlay.querySelector('[data-pgl="fullscreen"]');
            if (fsBtn) fsBtn.remove();
        }

        // Toolbar buttons
        overlay.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pgl]');
            if (!b) return;
            var act = b.getAttribute('data-pgl');
            switch (act) {
                case 'close':       close(); break;
                case 'prev':        navigate(-1); break;
                case 'next':        navigate(+1); break;
                case 'zoom-in':     zoomBy(+1); break;
                case 'zoom-out':    zoomBy(-1); break;
                case 'reset':       resetTransform(true); break;
                case 'fullscreen':  toggleFullscreen(); break;
            }
        });

        // Background click (clicking the dim area, not the image) closes
        overlay.addEventListener('mousedown', function (e) {
            // Only handle clicks DIRECTLY on the overlay or stage (not on
            // descendants like the image, buttons, caption). Combined with
            // the tap detection in pointer handlers below.
            if (e.target === overlay) close();
        });
        // Stage tap (image area) — tap on the dim margin around the image
        // closes; tap on the image itself does nothing (gesture handlers run).
        stage.addEventListener('click', function (e) {
            if (e.target === stage) close();
        });

        bindStageGestures();
    }

    // ── Gesture binding (pan / pinch / swipe) ───────────────────────────────
    function bindStageGestures() {
        var pointers = {};         // active pointers by id → {x,y,startX,startY}
        var startScale = 1;
        var pinchStartDist = 0;
        var pinchStartTx = 0, pinchStartTy = 0;
        var pinchCenter = { x: 0, y: 0 };
        var dragStart = null;      // {x, y, time, startTx, startTy}
        var swipeAccum = 0;
        var lastTouchEndAt = 0;

        function updateClass() {
            stage.classList.toggle('pgl-grabbing', !!dragStart);
            stage.classList.toggle('pgl-pinching', Object.keys(pointers).length >= 2);
            stage.classList.toggle('pgl-zoomed',   scale > 1.001);
        }

        // Pointer down
        stage.addEventListener('pointerdown', function (e) {
            // Buttons/clicks on toolbar etc. shouldn't start drags
            if (e.target.closest('button, .pgl-thumbs, .pgl-toolbar, .pgl-nav')) return;
            try { stage.setPointerCapture(e.pointerId); } catch (_) {}
            pointers[e.pointerId] = {
                x: e.clientX, y: e.clientY,
                startX: e.clientX, startY: e.clientY,
                time: Date.now()
            };
            var ids = Object.keys(pointers);
            if (ids.length === 1) {
                dragStart = {
                    x: e.clientX, y: e.clientY,
                    time: Date.now(),
                    startTx: tx, startTy: ty
                };
                swipeAccum = 0;
            } else if (ids.length === 2) {
                var p1 = pointers[ids[0]], p2 = pointers[ids[1]];
                pinchStartDist = Math.hypot(p1.x - p2.x, p1.y - p2.y);
                pinchStartTx = tx; pinchStartTy = ty;
                pinchCenter = {
                    x: (p1.x + p2.x) / 2,
                    y: (p1.y + p2.y) / 2
                };
                startScale = scale;
                dragStart = null;     // pinch overrides drag
            }
            updateClass();
        }, { passive: true });

        // Pointer move
        stage.addEventListener('pointermove', function (e) {
            if (!pointers[e.pointerId]) return;
            pointers[e.pointerId].x = e.clientX;
            pointers[e.pointerId].y = e.clientY;
            var ids = Object.keys(pointers);

            if (ids.length === 2) {
                // Pinch zoom around midpoint
                var p1 = pointers[ids[0]], p2 = pointers[ids[1]];
                var dist = Math.hypot(p1.x - p2.x, p1.y - p2.y);
                if (pinchStartDist > 0) {
                    var ratio = dist / pinchStartDist;
                    var newScale = clamp(startScale * ratio, MIN_ZOOM, MAX_ZOOM);
                    var sRect = stage.getBoundingClientRect();
                    var cx = pinchCenter.x - sRect.left;
                    var cy = pinchCenter.y - sRect.top;
                    // Keep pinch midpoint anchored in image space
                    var imgX = (cx - pinchStartTx) / startScale;
                    var imgY = (cy - pinchStartTy) / startScale;
                    tx = cx - imgX * newScale;
                    ty = cy - imgY * newScale;
                    scale = newScale;
                    clampPan();
                    applyTransform();
                }
                e.preventDefault();
                return;
            }

            if (dragStart) {
                var dx = e.clientX - dragStart.x;
                var dy = e.clientY - dragStart.y;
                if (scale > 1.01) {
                    // Pan when zoomed
                    tx = dragStart.startTx + dx;
                    ty = dragStart.startTy + dy;
                    clampPan();
                    applyTransform();
                } else {
                    // Swipe accumulator at fit-zoom: small follow-along feedback
                    swipeAccum = dx;
                    var pull = Math.max(-120, Math.min(120, dx));
                    img.style.transition = 'none';
                    img.style.transform = 'translate(' + (tx + pull) + 'px,' + ty + 'px) scale(' + scale + ')';
                }
            }
        });

        // Pointer up / cancel
        function pointerEnd(e) {
            var hadPointer = !!pointers[e.pointerId];
            delete pointers[e.pointerId];
            try { stage.releasePointerCapture(e.pointerId); } catch (_) {}
            if (!hadPointer) return;
            var ids = Object.keys(pointers);

            if (ids.length === 1) {
                // pinch ended → preserve drag for the remaining pointer
                var rem = pointers[ids[0]];
                dragStart = {
                    x: rem.x, y: rem.y,
                    time: Date.now(),
                    startTx: tx, startTy: ty
                };
                pinchStartDist = 0;
            } else if (ids.length === 0) {
                if (dragStart) {
                    var totalDx = e.clientX - dragStart.x;
                    var totalDy = e.clientY - dragStart.y;
                    var dist = Math.hypot(totalDx, totalDy);
                    var elapsed = Date.now() - dragStart.time;
                    var velocity = Math.abs(totalDx) / Math.max(1, elapsed);
                    if (scale <= 1.01 && (Math.abs(totalDx) > SWIPE_THRESHOLD || velocity > SWIPE_VELOCITY)) {
                        // Swipe → navigate
                        navigate(totalDx < 0 ? +1 : -1);
                    } else if (scale <= 1.01 && dist > 0) {
                        // Snap back if drag at fit-zoom didn't trigger swipe
                        applyTransform();
                    } else {
                        clampPan();
                        applyTransform();
                    }
                    dragStart = null;
                }
            }
            updateClass();
        }
        stage.addEventListener('pointerup',     pointerEnd);
        stage.addEventListener('pointercancel', pointerEnd);
        stage.addEventListener('pointerleave',  pointerEnd);

        // Wheel zoom (around cursor)
        stage.addEventListener('wheel', function (e) {
            e.preventDefault();
            var sRect = stage.getBoundingClientRect();
            var cx = e.clientX - sRect.left;
            var cy = e.clientY - sRect.top;
            var delta = -Math.sign(e.deltaY) * WHEEL_STEP;
            zoomAt(cx, cy, scale * (1 + delta));
        }, { passive: false });

        // Double-click toggle zoom (1x ↔ 2.4x at click point)
        stage.addEventListener('dblclick', function (e) {
            if (e.target.closest('button, .pgl-thumbs, .pgl-toolbar, .pgl-nav')) return;
            var sRect = stage.getBoundingClientRect();
            var cx = e.clientX - sRect.left;
            var cy = e.clientY - sRect.top;
            if (scale > 1.01) {
                resetTransform(true);
            } else {
                zoomAt(cx, cy, DBLCLICK_ZOOM);
            }
        });
    }

    // ── Zoom helpers ────────────────────────────────────────────────────────
    function zoomBy(direction) {
        // Zoom around image center
        var sRect = stage.getBoundingClientRect();
        zoomAt(sRect.width / 2, sRect.height / 2, scale * (1 + direction * 0.25));
    }

    function zoomAt(cx, cy, newScale) {
        newScale = clamp(newScale, MIN_ZOOM, MAX_ZOOM);
        if (Math.abs(newScale - scale) < 0.001) return;
        // Keep the point under (cx,cy) anchored as zoom changes
        var imgX = (cx - tx) / scale;
        var imgY = (cy - ty) / scale;
        tx = cx - imgX * newScale;
        ty = cy - imgY * newScale;
        scale = newScale;
        clampPan();
        applyTransform();
    }

    function resetTransform(animate) {
        scale = 1;
        // Center the fitted image inside the stage
        var fitW = imgNatural.w * fitScale;
        var fitH = imgNatural.h * fitScale;
        tx = (stageRect.w - fitW) / 2;
        ty = (stageRect.h - fitH) / 2;
        scale = fitScale;
        if (!animate) img.style.transition = 'none';
        applyTransform();
        if (!animate) {
            // Force reflow then re-enable transitions for subsequent animated changes
            // eslint-disable-next-line no-unused-expressions
            img.offsetHeight;
            img.style.transition = '';
        }
        stage.classList.remove('pgl-zoomed');
    }

    function clampPan() {
        // When zoomed in, keep at least part of the image visible.
        // When at fit-scale or smaller, center it.
        var displayW = imgNatural.w * scale;
        var displayH = imgNatural.h * scale;
        if (displayW <= stageRect.w) {
            tx = (stageRect.w - displayW) / 2;
        } else {
            var minX = stageRect.w - displayW;
            tx = clamp(tx, minX, 0);
        }
        if (displayH <= stageRect.h) {
            ty = (stageRect.h - displayH) / 2;
        } else {
            var minY = stageRect.h - displayH;
            ty = clamp(ty, minY, 0);
        }
    }

    function applyTransform() {
        if (!img) return;
        img.style.transition = ''; // ensure default applies (cleared during gestures)
        img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
    }

    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

    // ── Image loading + fit calculation ─────────────────────────────────────
    function showImageAt(index) {
        currentIndex = (index + images.length) % images.length;
        var rec = images[currentIndex];
        if (!rec) return;
        stage.classList.remove('pgl-error');
        stage.classList.add('pgl-loading');
        // Reset transforms preemptively so the previous image's pan doesn't
        // briefly flash on the new one.
        scale = 1; tx = 0; ty = 0;
        img.style.transition = 'none';
        img.style.transform = 'translate(0,0) scale(1)';
        // Stage rect for fit calculation
        var sRect = stage.getBoundingClientRect();
        stageRect = { w: sRect.width, h: sRect.height };
        // Load via a fresh Image() so we know the natural size before display
        var probe = new Image();
        probe.onload = function () {
            stage.classList.remove('pgl-loading');
            imgNatural = { w: probe.naturalWidth || 1, h: probe.naturalHeight || 1 };
            // Fit calculation: contain image inside stage
            var fitW = stageRect.w / imgNatural.w;
            var fitH = stageRect.h / imgNatural.h;
            fitScale = Math.min(fitW, fitH, 1);  // never upscale beyond 1:1 by default
            // Set image to natural size; transform handles the visible scale
            img.src = rec.src;
            img.alt = rec.alt || '';
            img.style.width  = imgNatural.w + 'px';
            img.style.height = imgNatural.h + 'px';
            // Center + fit
            scale = fitScale;
            tx = (stageRect.w - imgNatural.w * fitScale) / 2;
            ty = (stageRect.h - imgNatural.h * fitScale) / 2;
            // Re-enable transition (skipping animation for the first paint)
            // eslint-disable-next-line no-unused-expressions
            img.offsetHeight;
            img.style.transition = '';
            applyTransform();
        };
        probe.onerror = function () {
            stage.classList.remove('pgl-loading');
            stage.classList.add('pgl-error');
        };
        probe.src = rec.src;

        // Caption + counter + thumbs sync
        if (caption) caption.textContent = rec.caption || '';
        if (counter) counter.textContent = (currentIndex + 1) + ' / ' + images.length;
        if (thumbs) {
            Array.prototype.forEach.call(thumbs.children, function (t, i) {
                t.classList.toggle('pgl-thumb-active', i === currentIndex);
            });
            // Scroll active thumb into view
            var active = thumbs.children[currentIndex];
            if (active && active.scrollIntoView) {
                try { active.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' }); } catch (_) {}
            }
        }
        if (prevBtn) prevBtn.disabled = images.length <= 1;
        if (nextBtn) nextBtn.disabled = images.length <= 1;
    }

    function navigate(delta) {
        if (images.length <= 1) return;
        showImageAt(currentIndex + delta);
    }

    // ── Thumbs ──────────────────────────────────────────────────────────────
    function buildThumbs() {
        thumbs.innerHTML = '';
        if (images.length <= 1) {
            thumbs.hidden = true;
            return;
        }
        thumbs.hidden = false;
        images.forEach(function (rec, i) {
            var t = document.createElement('img');
            t.className = 'pgl-thumb';
            t.src = rec.src;
            t.alt = '';
            t.loading = 'lazy';
            t.addEventListener('click', function () { showImageAt(i); });
            thumbs.appendChild(t);
        });
    }

    // ── Open / close ────────────────────────────────────────────────────────
    function open(imgs, startIndex, opts) {
        if (!imgs || !imgs.length) return;
        build();
        opts = opts || {};
        // Normalize: allow array of strings OR array of {src, alt, caption}
        images = imgs.map(function (it) {
            if (typeof it === 'string') return { src: it, alt: '', caption: '' };
            return { src: it.src, alt: it.alt || '', caption: it.caption || '' };
        }).filter(function (r) { return r.src; });
        if (!images.length) return;
        currentIndex = clamp(startIndex || 0, 0, images.length - 1);
        buildThumbs();
        document.body.classList.add('pgl-lock');
        overlay.classList.add('pgl-open');
        openedAt = Date.now();
        showImageAt(currentIndex);
        // Keyboard listener attached on open, removed on close
        document.addEventListener('keydown', onKeydown, true);
        // Window resize → recompute fit
        window.addEventListener('resize', onResize);
    }

    function close() {
        if (!overlay || !overlay.classList.contains('pgl-open')) return;
        overlay.classList.remove('pgl-open');
        document.body.classList.remove('pgl-lock');
        document.removeEventListener('keydown', onKeydown, true);
        window.removeEventListener('resize', onResize);
        // Exit fullscreen if the lightbox put us there
        if (fullscreenElement()) {
            leaveFullscreen();
        }
    }

    function onKeydown(e) {
        if (e.defaultPrevented) return;
        switch (e.key) {
            case 'Escape':     e.preventDefault(); close(); break;
            case 'ArrowLeft':  e.preventDefault(); navigate(-1); break;
            case 'ArrowRight': e.preventDefault(); navigate(+1); break;
            case '+':
            case '=':          e.preventDefault(); zoomBy(+1); break;
            case '-':
            case '_':          e.preventDefault(); zoomBy(-1); break;
            case '0':
            case '1':          e.preventDefault(); resetTransform(true); break;
            case 'f':
            case 'F':          e.preventDefault(); toggleFullscreen(); break;
        }
    }

    var resizeRaf = 0;
    function onResize() {
        if (resizeRaf) return;
        resizeRaf = requestAnimationFrame(function () {
            resizeRaf = 0;
            // Re-fit current image to new stage size
            showImageAt(currentIndex);
        });
    }

    /*
     * Fullscreen helpers.
     *
     * Safari only shipped the unprefixed Fullscreen API in 16.4; everything older
     * exposes the webkit prefixed names only, and those return undefined instead of a
     * promise, so chaining .catch() on them throws. iPhone Safari has no element
     * fullscreen at all. Feature detect once and degrade quietly.
     */
    function fullscreenElement() {
        return document.fullscreenElement
            || document.webkitFullscreenElement
            || document.mozFullScreenElement
            || document.msFullscreenElement
            || null;
    }

    function fullscreenSupported() {
        var el = document.documentElement;
        return !!(el.requestFullscreen
            || el.webkitRequestFullscreen
            || el.webkitRequestFullScreen
            || el.mozRequestFullScreen
            || el.msRequestFullscreen);
    }

    function callFullscreen(target, names) {
        for (var i = 0; i < names.length; i++) {
            var fn = target[names[i]];
            if (typeof fn === 'function') {
                try {
                    var result = fn.call(target);
                    // Only the standard API returns a promise.
                    if (result && typeof result.catch === 'function') {
                        result.catch(function () { });
                    }
                } catch (_) { }
                return true;
            }
        }
        return false;
    }

    function enterFullscreen(el) {
        return callFullscreen(el, ['requestFullscreen', 'webkitRequestFullscreen', 'webkitRequestFullScreen', 'mozRequestFullScreen', 'msRequestFullscreen']);
    }

    function leaveFullscreen() {
        return callFullscreen(document, ['exitFullscreen', 'webkitExitFullscreen', 'webkitCancelFullScreen', 'mozCancelFullScreen', 'msExitFullscreen']);
    }

    function toggleFullscreen() {
        if (!fullscreenElement()) {
            enterFullscreen(document.documentElement);
        } else {
            leaveFullscreen();
        }
    }

    // ── Trigger auto-binding ────────────────────────────────────────────────
    // Delegated click on `[data-pg-lb-gallery]` — collect all triggers in the
    // same gallery, build the image array, open at the clicked index.
    function onTriggerClick(e) {
        var t = e.target.closest('[data-pg-lb-gallery]');
        if (!t) return;
        // Don't hijack if the trigger is inside a link (navigation should still work)
        if (t.tagName === 'A' && t.hasAttribute('href')) {
            e.preventDefault();
        } else {
            e.preventDefault();
        }
        var galleryId = t.getAttribute('data-pg-lb-gallery');
        var nodes = document.querySelectorAll('[data-pg-lb-gallery="' + cssEscape(galleryId) + '"]');
        var collected = [];
        var clickedIndex = 0;
        Array.prototype.forEach.call(nodes, function (n) {
            var src = n.getAttribute('data-pg-lb-src') || n.getAttribute('src') || n.getAttribute('href');
            if (!src) return;
            var rec = {
                src:     src,
                alt:     n.getAttribute('alt')   || n.getAttribute('data-pg-lb-alt')     || '',
                caption: n.getAttribute('title') || n.getAttribute('data-pg-lb-caption') || '',
                _node:   n,
                _index:  parseInt(n.getAttribute('data-pg-lb-index') || '0', 10)
            };
            collected.push(rec);
        });
        // Sort by data-pg-lb-index so designer can control display order
        collected.sort(function (a, b) { return a._index - b._index; });
        // De-duplicate: keep first occurrence of each src (carousel + thumbs
        // share gallery IDs, so the same src may appear twice with the same
        // index — we want one entry per image)
        var seen = {};
        var unique = [];
        for (var i = 0; i < collected.length; i++) {
            if (!seen[collected[i].src]) {
                seen[collected[i].src] = 1;
                if (collected[i]._node === t) clickedIndex = unique.length;
                unique.push(collected[i]);
            } else if (collected[i]._node === t) {
                // Find the unique entry's position
                for (var j = 0; j < unique.length; j++) {
                    if (unique[j].src === collected[i].src) { clickedIndex = j; break; }
                }
            }
        }
        if (!unique.length) return;
        open(unique, clickedIndex);
    }

    // CSS.escape() polyfill — needed for IDs containing colons / brackets
    function cssEscape(str) {
        if (global.CSS && typeof global.CSS.escape === 'function') return global.CSS.escape(str);
        return String(str).replace(/[^a-zA-Z0-9_-]/g, function (c) {
            return '\\' + c.charCodeAt(0).toString(16) + ' ';
        });
    }

    // ── Inline carousel sync ────────────────────────────────────────────────
    // Bootstrap 5 carousel doesn't auto-sync the thumbnails strip. Wire every
    // .pg-carousel to its .pg-carousel-thumbs sibling: thumb click → slide,
    // slide change → highlight matching thumb.
    function initInlineCarousels() {
        var carousels = document.querySelectorAll('.pg-carousel');
        Array.prototype.forEach.call(carousels, function (car) {
            if (car.dataset.pgCarouselWired === '1') return;
            car.dataset.pgCarouselWired = '1';
            // Find the thumbs strip — it's the next sibling that follows
            // the carousel in the DOM, but inside the same parent wrapper.
            var thumbStrip = car.parentNode && car.parentNode.querySelector('.pg-carousel-thumbs');
            if (!thumbStrip) return;
            // Sync active thumb when slide changes (Bootstrap fires `slid.bs.carousel`)
            car.addEventListener('slid.bs.carousel', function (e) {
                var idx = e.to;
                Array.prototype.forEach.call(thumbStrip.children, function (t, i) {
                    t.classList.toggle('active', i === idx);
                });
            });
        });
    }

    // ── Public API ──────────────────────────────────────────────────────────
    var api = {
        __pgInit: true,
        open:    open,
        close:   close,
        next:    function () { navigate(+1); },
        prev:    function () { navigate(-1); },
        version: '1.0.0'
    };
    global.pgLightbox = api;

    // ── Auto-init ───────────────────────────────────────────────────────────
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }
    ready(function () {
        // Use capture so we beat any other click handlers that might
        // stopPropagation on the trigger (e.g. parent <a> handlers).
        document.addEventListener('click', onTriggerClick, true);
        initInlineCarousels();
        // Re-scan periodically for AJAX-injected carousels (cheap; runs
        // briefly only if .pg-carousel-not-yet-wired exists).
        var mo = new MutationObserver(function () { initInlineCarousels(); });
        try { mo.observe(document.body, { childList: true, subtree: true }); } catch (_) {}
    });
})(typeof window !== 'undefined' ? window : this);
