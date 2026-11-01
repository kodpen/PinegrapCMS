/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the artboard: the canvas size, the New image dialog and growing
 * or trimming a picture's canvas.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * A blank document opens on the New image dialog (a preset size or a custom
 * one, and a background). The Canvas tool drags the board by its edges and
 * corners, sets its size on a nine-point anchor, fits it to the layers or
 * the selection: the artwork stays where it is and the board slides over
 * it. On a picture the same tool grows or trims its canvas around the crop
 * box (doc.canvas, the rest in a fill), which the server's save lays the
 * picture on; an outward frame grows it too. The Image tool adds pictures as
 * layers, from this computer, Files, a drop or the clipboard; a save keeps
 * those from this computer as files in the target folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, section = X.section, button = X.button;
    var SVGNS = X.SVGNS;

    var HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
    var ANCHORS = [[0, 0, 'anchorTopLeft'], [0.5, 0, 'anchorTop'], [1, 0, 'anchorTopRight'], [0, 0.5, 'anchorLeft'], [0.5, 0.5, 'anchorCenter'],
        [1, 0.5, 'anchorRight'], [0, 1, 'anchorBottomLeft'], [0.5, 1, 'anchorBottom'], [1, 1, 'anchorBottomRight']];

    // ---- the board ----

    // The board in the rotated space: an artboard's, a picture's canvas
    // (without an outward frame); null before a picture has loaded.
    Editor.prototype.boardBox = function () {
        if (this.doc.artboard) {
            return { x: 0, y: 0, w: this.doc.artboard.width, h: this.doc.artboard.height };
        }

        return (this.ready && this.doc.source) ? M.canvasBox(this.doc) : null;
    };

    // The board's fill: an artboard's background, a picture canvas's.
    Editor.prototype.boardFill = function () {
        return this.doc.artboard ? this.doc.artboard.background : (this.doc.canvas ? this.doc.canvas.background : '#ffffff');
    };

    // The board moved to r (in its own units, M.applyBoard()): one undo
    // step. A view the user zoomed or moved keeps the artwork where it is on
    // the screen; a fitted view fits the new board. On a picture the layers
    // stay and the canvas moves (M.applyCanvas()); followBoard() keeps the
    // view.
    Editor.prototype.setBoard = function (r) {
        if (!this.doc.artboard) {
            var canvas = M.applyCanvas(this.doc, r).canvas;

            if (!M.sameDocument(canvas, this.doc.canvas || null)) {
                this.change(function (doc) { doc.canvas = canvas; });
            }

            return;
        }

        var next = M.applyBoard(this.doc, r), v = this.view;

        if ((next.artboard.width === this.doc.artboard.width) && (next.artboard.height === this.doc.artboard.height) && !Math.round(r.x) && !Math.round(r.y)) {
            return;
        }

        this.change(function (doc) {
            doc.artboard = next.artboard;
            doc.layers = next.layers;
        });

        if (this.fitMode) {
            this.fit();
        } else {
            this.view = { k: v.k, x: v.x + Math.round(r.x) * v.k, y: v.y + Math.round(r.y) * v.k };
            this.renderView();
        }
    };

    // A size typed in or a preset: placed by the anchor.
    Editor.prototype.setBoardSize = function (w, h) {
        var b = this.boardBox();

        this.setBoard(M.boardAnchored(b.w, b.h, w, h, this.board.anchor[0], this.board.anchor[1]));
    };

    // The content's box in the board's units: the visible layers, or the
    // selected ones; on a picture with the crop box (the picture) too.
    Editor.prototype.contentBox = function (selected) {
        var b = this.boardBox(), r = M.boardAround(selected ? this.selected() : this.doc.layers), c;

        if (!b) {
            return null;
        }

        if (!this.doc.artboard && !selected) {
            c = M.cropBox(this.doc);
            r = r ? { x: Math.min(r.x, c.x), y: Math.min(r.y, c.y), w: Math.max(r.x + r.w, c.x + c.w) - Math.min(r.x, c.x), h: Math.max(r.y + r.h, c.y + c.h) - Math.min(r.y, c.y) } : c;
        }

        return r ? { x: r.x - b.x, y: r.y - b.y, w: r.w, h: r.h } : null;
    };

    Editor.prototype.fitBoard = function (selected) {
        var r = this.contentBox(selected);

        if (r) {
            this.setBoard(r);
        }
    };

    Editor.prototype.setBoardBackground = function (color, live) {
        this.change(function (doc) {
            if (doc.artboard) {
                doc.artboard.background = color;
            } else {
                doc.canvas = doc.canvas || { l: 0, t: 0, r: 0, b: 0, background: '#ffffff' };
                doc.canvas.background = color;
            }
        }, 'board-background', live);

        if (!live) {
            this.history.key = null;
        }
    };

    // The dashed board and its handles over the stage while the Canvas tool
    // is on: the board as it is, or as a drag would leave it, with its size.
    Editor.prototype.renderBoard = function () {
        var a = this.doc.artboard, b = this.board, box = this.boardBox(), on = !!box && (this.tool === 'artboard') && !b.dialog && !this.cropping();

        if (a) {
            this.frame.style.background = (a.background === 'transparent') ? '' : a.background;
            this.frame.classList.toggle('pgie-checker', a.background === 'transparent');
        }

        if (!b.el) {
            if (!on) {
                return;
            }

            b.size = el('span', { class: 'pgie-boardsize' });
            b.el = el('div', { class: 'pgie-board' }, [b.size].concat(HANDLES.map(function (h) { return el('span', { class: 'pgie-h', 'data-h': h }); })));
            this.stage.appendChild(b.el);
        }

        b.el.hidden = !on;

        if (!on) {
            return;
        }

        var r = b.ghost || { x: 0, y: 0, w: box.w, h: box.h }, v = this.view, k = v.k, st = b.el.style, vr = this.viewRect();

        st.left = (v.x + (box.x - vr.x + r.x) * k) + 'px';
        st.top = (v.y + (box.y - vr.y + r.y) * k) + 'px';
        st.width = (r.w * k) + 'px';
        st.height = (r.h * k) + 'px';
        b.size.textContent = Math.round(r.w) + ' × ' + Math.round(r.h) + ' px';
    };

    Editor.pointers.artboard = {
        down: function (e) {
            var h = this.boardBox() && e.target && e.target.getAttribute && e.target.getAttribute('data-h');

            if (!h || !this.board.el || !this.board.el.contains(e.target)) {
                return;
            }

            e.preventDefault();
            this.drag = { kind: 'board', h: h, x: e.clientX, y: e.clientY };

            try {
                this.stage.setPointerCapture(e.pointerId);
            } catch (err) {
                // The pointer is already gone.
            }
        },
        move: function (e) {
            var d = this.drag, a = this.boardBox(), k = this.view.k;

            this.board.ghost = M.boardDrag(a.w, a.h, d.h, (e.clientX - d.x) / k, (e.clientY - d.y) / k, e.shiftKey, e.altKey);
            this.renderBoard();
        },
        up: function () {
            var ghost = this.board.ghost;

            this.drag = null;
            this.board.ghost = null;

            if (ghost) {
                this.setBoard(ghost);
            }

            this.renderBoard();
        }
    };

    // ---- the panel ----

    // Canvas size, then (the Canvas tool) the anchor, fitting and the
    // presets, then the background.
    Editor.prototype.boardSection = function (body, full) {
        var self = this, box = this.boardBox(), a = { width: Math.round(box.w), height: Math.round(box.h) }, b = this.board;
        var w = el('input', { type: 'number', class: 'pgie-input', min: '16', max: '8000', value: String(a.width), 'aria-label': t('width') });
        var h = el('input', { type: 'number', class: 'pgie-input', min: '16', max: '8000', value: String(a.height), 'aria-label': t('height') });
        var commit = function () { self.setBoardSize(w.value, h.value); };

        w.addEventListener('change', commit);
        h.addEventListener('change', commit);
        body.appendChild(section(t('canvasSize'), el('div', { class: 'pgie-wh' }, [w, el('span', { text: '×' }), h, el('span', { text: 'px' })])));

        if (full) {
            var grid = el('div', { class: 'pgie-anchors', role: 'group', 'aria-label': t('anchor') });

            ANCHORS.forEach(function (p) {
                var on = (b.anchor[0] === p[0]) && (b.anchor[1] === p[1]);

                grid.appendChild(el('button', { type: 'button', class: 'pgie-anchor' + (on ? ' on' : ''), title: t(p[2]), 'aria-label': t(p[2]), 'aria-pressed': on ? 'true' : 'false',
                    onclick: function () {
                        b.anchor = [p[0], p[1]];
                        self.renderPanel();
                    } }, [el('span')]));
            });

            var fits = el('div', { class: 'pgie-boardfits' }, [
                button('pgie-btn', t('fitContent'), 'fit', function () { self.fitBoard(false); }, true),
                this.sel.length ? button('pgie-btn', t('fitSelection'), 'fit', function () { self.fitBoard(true); }, true) : null
            ]);

            fits.firstChild.disabled = !this.contentBox(false);
            body.appendChild(section(t('anchor'), el('div', { class: 'pgie-boardrow' }, [grid, fits])));

            var chips = el('div', { class: 'pgie-chips' }), now = M.boardPreset(a.width, a.height);

            M.BOARD_PRESETS.forEach(function (p) {
                chips.appendChild(el('button', { type: 'button', class: 'pgie-chip' + ((now === p[0]) ? ' on' : ''), text: t('preset_' + p[0]), title: p[1] + ' × ' + p[2] + ' px',
                    'aria-pressed': (now === p[0]) ? 'true' : 'false', onclick: function () { self.setBoardSize(p[1], p[2]); } }));
            });
            body.appendChild(section(t('boardPresets'), chips));
        }

        body.appendChild(X.swatches(t('boardBackground'), this.boardFill(), function (color, live) { self.setBoardBackground(color, live); }, { transparent: true }));
    };

    Editor.panels.artboard = function (body) {
        if (this.boardBox()) {
            this.boardSection(body, true);
        }
    };

    // ---- a picture's canvas ----

    // What the canvas adds around the picture, in its fill, under the
    // layers: the save box less the crop box (a checker for transparent).
    Editor.prototype.renderMat = function () {
        var b = this.board, show = this.ready && !!this.doc.source && !this.cropping() && !!M.saveLayout(this.doc);

        if (!b.mat) {
            if (!show) {
                return;
            }

            b.mat = document.createElementNS(SVGNS, 'svg');
            b.mat.setAttribute('class', 'pgie-mat');
            b.mat.setAttribute('aria-hidden', 'true');
            b.mat.innerHTML = '<defs><pattern id="' + this.fxId + '-mat" patternUnits="userSpaceOnUse"><rect fill="#ffffff"/><path fill="#d5dbe1"/></pattern></defs><path fill-rule="evenodd"/>';
            this.frame.insertBefore(b.mat, this.layersSvg);
        }

        b.mat.style.display = show ? '' : 'none';

        if (!show) {
            return;
        }

        var vr = this.viewRect(), c = M.cropBox(this.doc), x0 = Math.max(vr.x, c.x), y0 = Math.max(vr.y, c.y);
        var x1 = Math.min(vr.x + vr.w, c.x + c.w), y1 = Math.min(vr.y + vr.h, c.y + c.h), fill = this.boardFill(), q = 10 / this.view.k;
        var d = 'M' + vr.x + ' ' + vr.y + 'h' + vr.w + 'v' + vr.h + 'h' + (-vr.w) + 'Z';
        var pattern = b.mat.firstChild.firstChild;

        if ((x1 > x0) && (y1 > y0)) {
            d += 'M' + x0 + ' ' + y0 + 'H' + x1 + 'V' + y1 + 'H' + x0 + 'Z';
        }

        b.mat.setAttribute('viewBox', [vr.x, vr.y, vr.w, vr.h].join(' '));
        b.mat.lastChild.setAttribute('d', d);
        b.mat.lastChild.setAttribute('fill', (fill === 'transparent') ? 'url(#' + this.fxId + '-mat)' : fill);
        pattern.setAttribute('width', 2 * q);
        pattern.setAttribute('height', 2 * q);
        pattern.firstChild.setAttribute('width', 2 * q);
        pattern.firstChild.setAttribute('height', 2 * q);
        pattern.lastChild.setAttribute('d', 'M0 0h' + q + 'v' + q + 'h' + (-q) + 'ZM' + q + ' ' + q + 'h' + q + 'v' + q + 'h' + (-q) + 'Z');
    };

    // The shown box changed by the canvas or an outward frame (an edit, undo,
    // redo): a fitted view fits it, another keeps the artwork where it is on
    // the screen. True when the view was drawn again.
    Editor.prototype.followBoard = function () {
        if (!this.ready || !this.doc.source) {
            return false;
        }

        var vr = this.viewRect(), last = this.board.last, crop = this.cropping(), v = this.view;

        this.board.last = { crop: crop, x: vr.x, y: vr.y, w: vr.w, h: vr.h };

        if (!last || crop || last.crop || ((last.x === vr.x) && (last.y === vr.y) && (last.w === vr.w) && (last.h === vr.h))) {
            return false;
        }

        if (this.fitMode) {
            this.fit();
        } else {
            this.view = { k: v.k, x: v.x + (vr.x - last.x) * v.k, y: v.y + (vr.y - last.y) * v.k };
            this.renderView();
        }

        return true;
    };

    // The Frame panel on a picture: a line, a thick line or a polaroid may
    // lie outside it, growing the canvas.
    var framePanel = Editor.panels.frame;

    Editor.panels.frame = function (body) {
        var self = this, fr = this.doc.frame;

        framePanel.call(this, body);

        if (this.doc.source && fr && M.FRAME_OUT.hasOwnProperty(fr.kind)) {
            var box = el('input', { type: 'checkbox' });

            box.checked = !!fr.outside;
            box.addEventListener('change', function () { self.setFrame({ outside: box.checked }); });
            body.appendChild(el('label', { class: 'pgie-check' }, [box, el('span', { text: t('frameOutside') })]));
        }
    };

    // A picture's save lays it on its canvas (extend); pictures from this
    // computer go along to be kept as files (pictures) in the target folder.
    Editor.saveHooks.push(function (body, mode) {
        var layout = (mode !== 'new') ? M.saveLayout(this.doc) : null, list = [];

        if (layout) {
            body.extend = layout;
        }

        if ((mode === 'replace') || (mode === 'copy') || (mode === 'new')) {
            this.doc.layers.forEach(function (l) {
                if ((l.type === 'image') && !l.fileId && /^data:image\/(png|jpeg);base64,/.test(l.src || '')) {
                    list.push({ id: l.id, data: l.src, name: l.fileName || '' });
                }
            });
        }

        if (list.length) {
            body.pictures = list;
        }
    });

    // The files a save kept: their layers point at them from now on.
    Editor.prototype.takePictures = function (saved) {
        if (!saved || (typeof saved !== 'object')) {
            return;
        }

        this.doc.layers.forEach(function (l) {
            if (Object.prototype.hasOwnProperty.call(saved, l.id) && saved[l.id]) {
                l.src = saved[l.id].url;
                l.fileId = saved[l.id].id;
                delete l.fileName;
            }
        });
    };

    // Select with nothing selected on a canvas: the canvas first, then the
    // layers.
    var layersPanel = Editor.panels.select;

    Editor.panels.select = function (body) {
        if (this.doc.artboard && !this.sel.length) {
            this.boardSection(body, false);

            if (this.doc.layers.length) {
                body.appendChild(el('div', { class: 'pgie-lbl', text: t('layers') }));
            }
        }

        layersPanel.call(this, body);
    };

    // ---- the Image tool ----

    Editor.prototype.pickPictures = function () {
        var self = this, input = el('input', { type: 'file', accept: 'image/*', multiple: true, hidden: true });

        input.addEventListener('change', function () {
            Array.prototype.forEach.call(input.files || [], function (f) {
                if (/^image\//.test(f.type)) {
                    self.addPicture(f);
                }
            });
            input.parentNode.removeChild(input);
        });
        this.root.appendChild(input);
        input.click();
    };

    Editor.panels.image = function (body) {
        var self = this;

        body.appendChild(el('div', { class: 'pgie-sec' }, [
            button('pgie-btn pgie-primary', t('imagePick'), 'image', function () { self.pickPictures(); }, true),
            el('p', { class: 'pgie-note', text: t('imageNote') })
        ]));

        if (X.config().api && (this.saveMode !== 'callback') && this.stickerFilesSection) {
            this.stickerFilesSection(body);
        }
    };

    // Pictures dropped on the stage become layers, with any tool.
    Editor.prototype.bindDrop = function () {
        var self = this, files = function (e) {
            var types = (e.dataTransfer && e.dataTransfer.types) || [];

            return self.ready && !self.saving && !self.board.dialog && (Array.prototype.indexOf.call(types, 'Files') >= 0);
        };

        this.stage.addEventListener('dragover', function (e) {
            if (files(e)) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
            }
        });
        this.stage.addEventListener('drop', function (e) {
            if (!files(e)) {
                return;
            }

            e.preventDefault();
            Array.prototype.forEach.call(e.dataTransfer.files || [], function (f) {
                if (/^image\//.test(f.type)) {
                    self.addPicture(f);
                }
            });
        });
    };

    // ---- the New image dialog ----

    // Over the editor: the presets (custom shows width and height), the
    // backgrounds, Cancel (the canvas as it was opened) and Create. Its keys
    // stay in it: Tab goes round, Escape cancels, Enter in a size creates.
    Editor.prototype.openNewImage = function () {
        var self = this, a = this.doc.artboard;
        var s = { preset: M.boardPreset(a.width, a.height), w: a.width, h: a.height, bg: a.background };
        var presets = el('div', { class: 'pgie-presets' }), bgs = el('div', { class: 'pgie-bgopts' });
        var w = el('input', { type: 'number', class: 'pgie-input', min: '16', max: '8000', value: String(s.w), 'aria-label': t('width') });
        var h = el('input', { type: 'number', class: 'pgie-input', min: '16', max: '8000', value: String(s.h), 'aria-label': t('height') });
        var custom = el('div', { class: 'pgie-wh' }, [w, el('span', { text: '×' }), h, el('span', { text: 'px' })]);
        var create = el('button', { type: 'button', class: 'pgie-btn pgie-primary', text: t('create'), onclick: function () { self.closeNewImage(s); } });
        var cancel = el('button', { type: 'button', class: 'pgie-btn', text: t('cancel'), onclick: function () { self.closeNewImage(null); } });
        var refresh = function () {
            Array.prototype.forEach.call(presets.children, function (n) {
                var on = n.getAttribute('data-preset') === s.preset;

                n.classList.toggle('on', on);
                n.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            Array.prototype.forEach.call(bgs.children, function (n) {
                var on = n.getAttribute('data-bg') === s.bg;

                n.classList.toggle('on', on);
                n.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            custom.hidden = (s.preset !== 'custom');
        };

        M.BOARD_PRESETS.concat([['custom', 0, 0]]).forEach(function (p) {
            var pw = p[1] || 1, ph = p[2] || 1, k = 30 / Math.max(pw, ph);

            presets.appendChild(el('button', { type: 'button', class: 'pgie-preset', 'data-preset': p[0], onclick: function () {
                s.preset = p[0];
                refresh();

                if (p[0] === 'custom') {
                    w.focus();
                }
            } }, [
                el('span', { class: 'pgie-pthumb' }, [el('span', { class: (p[0] === 'custom') ? 'pgie-pcustom' : null,
                    style: 'width: ' + Math.max(8, Math.round(pw * k)) + 'px; height: ' + Math.max(8, Math.round(ph * k)) + 'px' })]),
                el('span', { class: 'pgie-ptext' }, [
                    el('b', { text: t('preset_' + p[0]) }),
                    el('span', { text: p[1] ? p[1] + ' × ' + p[2] + ' px' : t('width') + ' × ' + t('height') })
                ])
            ]));
        });

        M.BOARD_BACKGROUNDS.forEach(function (c) {
            bgs.appendChild(el('button', { type: 'button', class: 'pgie-bgopt', 'data-bg': c[0], onclick: function () {
                s.bg = c[0];
                refresh();
            } }, [
                el('span', { class: 'pgie-bgsw' + ((c[0] === 'transparent') ? ' pgie-checker' : ''), style: (c[0] === 'transparent') ? null : 'background: ' + c[0] }),
                el('span', { text: t(c[1]) })
            ]));
        });

        [w, h].forEach(function (input) {
            input.addEventListener('input', function () {
                s.w = w.value;
                s.h = h.value;
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.closeNewImage(s);
                }
            });
        });

        var box = el('div', { class: 'pgie-confirm-box pgie-newbox' }, [
            el('h2', { id: 'pgie-new-title', text: t('newImage') }),
            presets,
            custom,
            section(t('boardBackground'), bgs),
            el('div', { class: 'pgie-confirm-actions' }, [cancel, create])
        ]);

        this.board.dialog = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-new-title' }, [box]);
        this.board.dialog.addEventListener('keydown', function (e) {
            var list = Array.prototype.filter.call(box.querySelectorAll('button, input'), function (n) { return !n.disabled && n.offsetParent !== null; });
            var i = list.indexOf(document.activeElement);

            // The editor's own keys wait till the dialog has closed.
            e.stopPropagation();

            if (e.key === 'Escape') {
                e.preventDefault();
                self.closeNewImage(null);
            } else if ((e.key === 'Tab') && list.length && (e.shiftKey ? i <= 0 : i === list.length - 1)) {
                e.preventDefault();
                list[e.shiftKey ? list.length - 1 : 0].focus();
            }
        });
        this.board.dialog.addEventListener('paste', function (e) { e.stopPropagation(); });
        refresh();
        this.root.appendChild(this.board.dialog);
        (presets.querySelector('.on') || create).focus();
    };

    // s: the dialog's choice, or null for Cancel. The chosen canvas is the
    // one the editor opened with: nothing to save yet, nothing to undo.
    Editor.prototype.closeNewImage = function (s) {
        var a = this.doc.artboard, p;

        if (s) {
            p = M.BOARD_PRESETS.filter(function (x) { return x[0] === s.preset; })[0];
            a.width = p ? p[1] : M.boardSide(s.w);
            a.height = p ? p[2] : M.boardSide(s.h);
            a.background = s.bg;
            this.opening = M.clone(this.doc);
            this.settleDirty();
            this.layersDirty = true;
            this.renderMeta();
        }

        this.board.dialog.parentNode.removeChild(this.board.dialog);
        this.board.dialog = null;
        this.fit();
        this.render();
        this.stage.focus({ preventScroll: true });
    };

    Editor.inits.push(function () {
        // anchor: where a typed size keeps the board; ghost: the board while
        // a handle is dragged; dialog: the New image dialog while it is open.
        // mat: a picture canvas's fill; last: the box last shown.
        this.board = { anchor: [0.5, 0.5], ghost: null, el: null, size: null, dialog: null, ask: !!this.opts.blank && !this.tools.mini, bound: false,
            mat: null, last: null };
    });

    Editor.views.push(function () {
        if (!this.board.bound) {
            this.board.bound = true;
            this.bindDrop();
        }

        if (this.board.ask) {
            this.board.ask = false;
            this.openNewImage();
        }

        if (this.followBoard()) {
            return;
        }

        this.renderMat();
        this.renderBoard();
    });

    Editor.hints.artboard = 'hintArtboard';
    Editor.hints.image = 'hintImage';
}(window.PgImageEditor.parts));
