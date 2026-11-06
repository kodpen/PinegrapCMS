/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: presenting a canvas's scenes, and their PDF. Present in the
 * top bar (a canvas with two scenes or more) fills the screen with one scene
 * at a time, letterboxed, playing its motion once and holding its end; a
 * click or tap, Space, the arrow keys, Page Up and Down or a swipe moves on
 * (moving forward plays the transition the scene arrives by), Home and End
 * go to the first and last, Escape leaves. A user who asked for less motion
 * sees each scene at rest.
 *
 * PDF joins Save animation's formats with two scenes or more: a page per
 * scene at rest, each a JPEG at the chosen size on white, written by
 * M.pdfFile() on the page (no worker: a page is one canvas encode).
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_scenes.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon, button = X.button;
    // A presented scene is drawn at most this many pixels on its longest edge.
    var DRAW_MAX = 2560;
    // The Escape hint shows this long after Present opens.
    var HINT_MS = 3000;
    // A PDF page's JPEG quality; the bytes a page and the file add besides
    // its JPEG, for the estimate.
    var JPEG_QUALITY = 0.9, PDF_HEAD = 400, PDF_PAGE = 420;

    X.icons.p_present = 'M3 4h18v12H3zM12 16v4M8 20h8M10 7.5v5l4-2.5z';
    X.icons.p_prev = 'M15 6l-6 6 6 6';
    X.icons.p_next = 'M9 6l6 6-6 6';

    function reducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function now() {
        return (window.performance && performance.now) ? performance.now() : Date.now();
    }

    // ---- Present ----

    Editor.prototype.presentOffered = function () {
        return !!this.doc.artboard && (M.sceneCount(this.doc) > 1);
    };

    Editor.prototype.openPresent = function () {
        var self = this;

        if (!this.ready || this.saving || this.present || this.animBox || !this.presentOffered()) {
            return;
        }

        if (this.playing) {
            this.pause();
        }

        if (this.time !== null) {
            this.stopMotion(true);
        }

        var P = this.present = {
            index: M.sceneIndex(this.doc), count: M.sceneCount(this.doc), forward: false, start: 0, token: 0,
            still: reducedMotion(), back: document.activeElement, press: null, hintTimer: 0
        };

        P.canvas = el('canvas', { class: 'pgie-pcanvas', 'aria-hidden': 'true' });
        P.counter = el('span', { class: 'pgie-pcount', 'aria-live': 'polite' });
        P.prev = button('pgie-ibtn', t('presentPrev'), 'p_prev', function () { self.presentAct('prev'); });
        P.next = button('pgie-ibtn', t('presentNext'), 'p_next', function () { self.presentAct('next'); });
        P.hint = el('p', { class: 'pgie-phint', text: t('presentLeave') });
        P.bar = el('div', { class: 'pgie-pbar' }, [P.prev, P.counter, P.next, button('pgie-ibtn', t('presentClose'), 'close', function () { self.closePresent(); })]);
        P.box = el('div', { class: 'pgie-present', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('presentDialog'), tabindex: '-1' }, [P.canvas, P.hint, P.bar]);

        P.box.addEventListener('keydown', function (e) {
            var action = M.presentKey(e.key), onButton = e.target && (e.target.tagName === 'BUTTON');

            // A focused button takes its own Enter and Space.
            if (action && !(onButton && ((e.key === 'Enter') || (e.key === ' '))) && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                self.presentAct(action);
            }

            e.stopPropagation();
        });
        P.box.addEventListener('pointerdown', function (e) {
            P.press = (e.button === 0) && !P.bar.contains(e.target) ? { id: e.pointerId, x: e.clientX, y: e.clientY } : null;
        });
        P.box.addEventListener('pointerup', function (e) {
            var press = P.press;

            P.press = null;

            if (press && (press.id === e.pointerId)) {
                var g = M.presentGesture(e.clientX - press.x, e.clientY - press.y);

                if (g) {
                    self.presentAct(g === 'tap' ? 'next' : g);
                }
            }
        });
        P.box.addEventListener('pointercancel', function () { P.press = null; });

        // Leaving the browser's full screen (its own Escape) leaves Present.
        P.onFull = function () {
            if (P.full && (document.fullscreenElement !== P.box)) {
                self.closePresent();
            } else if (document.fullscreenElement === P.box) {
                P.full = true;
            }
        };
        P.onResize = function () { self.presentDraw(P.token); };
        document.addEventListener('fullscreenchange', P.onFull);
        window.addEventListener('resize', P.onResize);

        this.root.appendChild(P.box);
        P.box.focus({ preventScroll: true });

        try {
            if (P.box.requestFullscreen) {
                P.box.requestFullscreen().catch(function () { /* the box fills the window */ });
            }
        } catch (err) {
            // No Fullscreen API: the box fills the window.
        }

        P.hintTimer = setTimeout(function () { P.hint.classList.add('pgie-pgone'); }, HINT_MS);
        this.layersReady().then(function () {
            if (self.present === P) {
                self.presentShow(P.index, false);
            }
        }, function () {
            self.presentShow(P.index, false);
        });
    };

    Editor.prototype.closePresent = function () {
        var P = this.present;

        if (!P) {
            return;
        }

        this.present = null;
        P.token++;
        clearTimeout(P.hintTimer);
        document.removeEventListener('fullscreenchange', P.onFull);
        window.removeEventListener('resize', P.onResize);

        try {
            if ((document.fullscreenElement === P.box) && document.exitFullscreen) {
                document.exitFullscreen().catch(function () { /* already left */ });
            }
        } catch (err) {
            // Nothing to leave.
        }

        P.box.remove();
        ((P.back && !P.back.disabled && this.dialog.contains(P.back)) ? P.back : this.stage).focus({ preventScroll: true });
    };

    // A key, a button or a gesture: next, prev, first, last or close.
    Editor.prototype.presentAct = function (action) {
        var P = this.present;

        if (!P) {
            return;
        }

        var to = M.presentGo(P.index, P.count, action);

        if (to < 0) {
            this.closePresent();
        } else if (to !== P.index) {
            this.presentShow(to, to === P.index + 1);
        }
    };

    // Scene index from its start; forward plays the transition it arrives by.
    Editor.prototype.presentShow = function (index, forward) {
        var P = this.present;

        P.index = index;
        P.forward = forward;
        P.start = now();
        P.token++;
        P.counter.textContent = t('presentCount').replace('{n}', index + 1).replace('{count}', P.count);
        P.prev.disabled = (index === 0);
        P.next.disabled = (index >= P.count - 1);
        this.presentDraw(P.token);
    };

    // One frame of the shown scene, then the next one on the following
    // animation frame until the scene has reached its end; a resize or a
    // move to another scene starts a new run (token) and drops this one.
    Editor.prototype.presentDraw = function (token) {
        var self = this, P = this.present;

        if (!P || (token !== P.token)) {
            return;
        }

        if (P.busy) {
            P.again = true;
            return;
        }

        var out = M.saveSize(this.doc), box = P.box.getBoundingClientRect();
        var fit = M.presentFit(out.w, out.h, Math.max(1, box.width), Math.max(1, box.height));
        var dpr = Math.min(2, window.devicePixelRatio || 1), k = Math.min(dpr, DRAW_MAX / Math.max(fit.w, fit.h));
        var w = Math.max(1, Math.round(fit.w * k)), h = Math.max(1, Math.round(fit.h * k));
        var at = P.still ? { whole: null, local: null } : M.presentTime(this.doc, P.index, now() - P.start, P.forward);
        var duration = M.sceneList(this.doc)[P.index].duration;

        P.canvas.style.left = fit.x + 'px';
        P.canvas.style.top = fit.y + 'px';
        P.canvas.style.width = fit.w + 'px';
        P.canvas.style.height = fit.h + 'px';
        P.busy = true;
        P.again = false;

        var drawing = (at.whole !== null) ? this.renderFrame(at.whole, w, h, P.canvas) : this.renderFrame(at.local, w, h, P.canvas, M.sceneView(this.doc, P.index));

        drawing.then(null, function () {}).then(function () {
            P.busy = false;

            if (self.present !== P) {
                return;
            }

            if (P.again || ((token === P.token) && (at.local !== null) && (at.local < duration))) {
                window.requestAnimationFrame(function () { self.presentDraw(P.token); });
            }
        });
    };

    // "Present" in the top bar, on a canvas with scenes.
    Editor.topItems.push(function () {
        var self = this;

        this.presentBtn = el('button', { type: 'button', class: 'pgie-btn pgie-presentbtn', title: t('presentTitle'), onclick: function () { self.openPresent(); } }, [
            icon('p_present'), el('span', { class: 'pgie-vlabel', text: t('present') })
        ]);
        this.presentBtn.hidden = true;

        return this.presentBtn;
    });

    Editor.views.push(function () {
        if (this.presentBtn) {
            this.presentBtn.hidden = !this.presentOffered();
        }
    });

    // ---- PDF ----

    // Save animation's job for a PDF (animJob() hands it over): every
    // scene at rest at the chosen size, on white, as a JPEG page; estimate
    // true answers the size from the middle scene's page.
    Editor.prototype.pdfJob = function (set, estimate, progress) {
        var self = this, doc = this.doc, count = M.sceneCount(doc), size = M.animSize(M.saveSize(doc), set.edge, false);
        var canvas = document.createElement('canvas'), flat = document.createElement('canvas'), job = { cancelled: false, enc: null };

        job.cancel = function () {
            job.cancelled = true;

            if (job.reject) {
                job.reject(null);
            }
        };

        function page(i) {
            return self.renderFrame(null, size.w, size.h, canvas, M.sceneView(doc, i)).then(function () {
                var g;

                flat.width = size.w;
                flat.height = size.h;
                g = flat.getContext('2d');
                g.fillStyle = '#ffffff';
                g.fillRect(0, 0, size.w, size.h);
                g.drawImage(canvas, 0, 0);

                return new Promise(function (resolve, reject) {
                    flat.toBlob(function (blob) { return blob ? resolve(blob) : reject(new Error(t('animError'))); }, 'image/jpeg', JPEG_QUALITY);
                });
            }).then(function (blob) {
                return blob.arrayBuffer();
            }).then(function (buf) {
                return { jpeg: new Uint8Array(buf), w: size.w, h: size.h };
            });
        }

        job.promise = new Promise(function (resolve, reject) {
            var pages = [];

            job.reject = reject;
            progress('base', 0, count);

            function next(i) {
                if (job.cancelled) {
                    return null;
                }

                if (i >= count) {
                    progress('encode', count, count);
                    resolve(new Blob([M.pdfFile(pages)], { type: 'application/pdf' }));
                    return null;
                }

                return page(i).then(function (p) {
                    if (job.cancelled) {
                        return null;
                    }

                    pages.push(p);
                    self.animPreview(canvas);
                    progress('frames', i + 1, count);

                    return next(i + 1);
                });
            }

            self.layersReady().then(function () {
                if (estimate) {
                    return page(Math.floor(count / 2)).then(function (p) {
                        resolve({ bytes: M.animEstimate(PDF_HEAD, p.jpeg.length + PDF_PAGE, count), count: count, size: size });
                    });
                }

                progress('frames', 0, count);

                return next(0);
            }).then(null, reject);
        });

        return job;
    };

    Editor.inits.push(function () {
        this.present = null;
        this.presentBtn = null;
    });
}(window.PgImageEditor.parts));
