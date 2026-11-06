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
 * picture on; an outward frame grows it too. Import in the top bar adds
 * pictures as layers and SVG files as SVG layers, from this computer or
 * Files (a drop and the clipboard too); a caller that names the Image tool
 * gets its panel. A save keeps pictures from this computer as files in the
 * target folder.
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
            c = M.pictureBox(this.doc);
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
    // layers: the save box less the crop box (a checker for transparent),
    // its hole in the crop shape, which cuts the picture only, as the save
    // does.
    Editor.prototype.renderMat = function () {
        var b = this.board, show = this.ready && !!this.doc.source && !this.cropping() && !!M.saveLayout(this.doc);

        this.placeVignette(show);

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

        var vr = this.viewRect(), c = M.pictureBox(this.doc), x0 = Math.max(vr.x, c.x), y0 = Math.max(vr.y, c.y);
        var x1 = Math.min(vr.x + vr.w, c.x + c.w), y1 = Math.min(vr.y + vr.h, c.y + c.h), fill = this.boardFill(), q = 10 / this.view.k;
        var d = 'M' + vr.x + ' ' + vr.y + 'h' + vr.w + 'v' + vr.h + 'h' + (-vr.w) + 'Z';
        var pattern = b.mat.firstChild.firstChild, shape = this.doc.geometry.cropShape || 'rect';

        // A rounded or round hole is the whole crop box's shape: the canvas
        // edge trims it as the save does, the even-odd rule leaves its part
        // off the canvas outside the view.
        if ((x1 > x0) && (y1 > y0)) {
            d += (shape === 'rect') ? M.cropShapePath({ x: x0, y: y0, w: x1 - x0, h: y1 - y0 }, 'rect') : M.cropShapePath(c, shape);
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

    // The vignette belongs to the picture's adjustments: over the crop box
    // only, as the save draws it, when the canvas reaches past it.
    Editor.prototype.placeVignette = function (on) {
        var st = this.vignette.style, vr, c, k;

        if (!on) {
            st.left = st.top = st.width = st.height = '';
            return;
        }

        vr = this.viewRect();
        c = M.pictureBox(this.doc);
        k = this.view.k;
        st.left = ((c.x - vr.x) * k) + 'px';
        st.top = ((c.y - vr.y) * k) + 'px';
        st.width = (c.w * k) + 'px';
        st.height = (c.h * k) + 'px';
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

    // A file saved before as a canvas opens from its document (the open
    // answer's canvas and doc), its layers editable, with the canvas's
    // tools and no New image dialog.
    var openFile = Editor.prototype.openFile;

    Editor.prototype.openFile = function (r) {
        var doc = (r && r.canvas && !this.opts.src) ? M.canvasOpenDoc(r.doc) : null;

        if (!doc) {
            openFile.call(this, r);
            return;
        }

        this.versionCount = Number(r.versions) || 0;
        this.versionBase = 0;
        this.doc = doc;
        this.layersDirty = true;

        if (!this.tools.mini) {
            this.tools = M.resolveTools('canvas', this.opts.tools);
            this.tool = this.tools.list[0];
            this.renderTools();
        }

        if (this.renderVersionCount) {
            this.renderVersionCount();
        }

        this.frame.style.background = (doc.artboard.background === 'transparent') ? '' : doc.artboard.background;
        this.frame.classList.toggle('pgie-checker', doc.artboard.background === 'transparent');
        this.setReady();
        this.render();
    };

    // A picture's save lays it on its canvas (extend); a canvas reopened
    // from its file names it for a replace or a copy (the core sends its
    // PNG, picture); pictures from this computer go along to be kept as files
    // (pictures) in the target folder, a canvas another endpoint keeps
    // included (save.url, the Workspace): not for a picture's copy, which
    // keeps no document.
    Editor.saveHooks.push(function (body, mode) {
        var layout = (mode !== 'new') ? M.saveLayout(this.doc) : null, list = [];

        if (layout) {
            body.extend = layout;
        }

        if (this.doc.artboard && ((mode === 'replace') || (mode === 'copy'))) {
            body.file_id = this.opts.fileId;
        }

        if ((mode === 'replace') || (mode === 'new') || (((mode === 'copy') || (mode === 'url')) && this.doc.artboard)) {
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
                self.importFile(f);
            });
        });
    };

    // ---- Import in the top bar ----

    X.icons['import'] = 'M12 15V4M7.5 8.5L12 4l4.5 4.5M4.5 15v4.5h15V15';

    function isSvgFile(f) {
        return /^image\/svg\+xml$/i.test(f.type || '') || /\.svg$/i.test(f.name || '');
    }

    // Whether Files can be listed and an SVG passed through the server's
    // sanitizer: the editor's API, not a caller's own save.
    Editor.prototype.importApi = function () {
        return !!X.config().api && (this.saveMode !== 'callback');
    };

    // A file from this computer or a drop: an SVG becomes one SVG layer
    // (svgImport(), through the sanitizer), any other picture a picture
    // layer as the Image tool makes it.
    Editor.prototype.importFile = function (f) {
        var self = this;

        if (isSvgFile(f) && this.svgImport && this.importApi()) {
            var reader = new FileReader();

            reader.onload = function () {
                self.importSvgRequest({ svg: String(reader.result) }, f.name || '');
            };
            reader.readAsText(f);
        } else if (/^image\//.test(f.type || '')) {
            this.addPicture(f);
        }
    };

    // The sanitized SVG from the server, made a layer; a refusal is shown.
    Editor.prototype.importSvgRequest = function (body, name) {
        var self = this;

        body.action = 'svg_import';
        this.request(body).then(function (r) {
            if (self.finished) {
                return;
            }

            if (!r || (r.status !== 'success') || !self.svgImport(r.svg, name || r.name || '')) {
                self.showMessage((r && r.message) || t('importError'), true);
            }
        }, function () {
            self.showMessage(t('importError'), true);
        });
    };

    Editor.prototype.pickImports = function () {
        var self = this, input = el('input', { type: 'file', accept: 'image/*,.svg', multiple: true, hidden: true });

        input.addEventListener('change', function () {
            Array.prototype.forEach.call(input.files || [], function (f) {
                self.importFile(f);
            });
            input.parentNode.removeChild(input);
        });
        this.root.appendChild(input);
        input.click();
    };

    Editor.prototype.toggleImportMenu = function (open) {
        var self = this, ib = this.importBox;

        open = (open === undefined) ? !this.importMenu : open;

        if (this.importMenu) {
            ib.removeChild(this.importMenu);
            this.importMenu = null;
            document.removeEventListener('pointerdown', this.importOutside, true);
        }

        this.importBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (!open) {
            return;
        }

        var item = function (label, fn) {
            return el('button', { type: 'button', role: 'menuitem', text: label, onclick: function () {
                self.toggleImportMenu(false);
                fn();
            } });
        };
        var items = [item(t('importComputer'), function () { self.pickImports(); })];

        if (this.importApi()) {
            items.push(item(t('importSite'), function () { self.openImportFiles(); }));
        }

        this.importMenu = el('div', { class: 'pgie-menu pgie-importmenu', role: 'menu', 'aria-label': t('import') }, items);

        // Arrows move between the items; the editor's keys stay out.
        this.importMenu.addEventListener('keydown', function (e) {
            var list = Array.prototype.slice.call(self.importMenu.querySelectorAll('button')), at = list.indexOf(document.activeElement);

            if ((e.key === 'Escape') || (e.key === 'Tab')) {
                return;
            }

            e.stopPropagation();

            if ((e.key === 'ArrowDown') || (e.key === 'ArrowUp')) {
                e.preventDefault();
                list[(at + ((e.key === 'ArrowDown') ? 1 : list.length - 1)) % list.length].focus();
            } else if ((e.key === 'Home') || (e.key === 'End')) {
                e.preventDefault();
                list[(e.key === 'Home') ? 0 : list.length - 1].focus();
            }
        });
        this.importOutside = function (e) {
            if (self.importMenu && !ib.contains(e.target)) {
                self.toggleImportMenu(false);
            }
        };
        document.addEventListener('pointerdown', this.importOutside, true);
        ib.appendChild(this.importMenu);
        items[0].focus();
    };

    // "From the site": the Files list of the sticker part (pictures and SVG
    // files, the newest or by name) over the editor; a press imports one,
    // or, given pick(file), hands that picture (no SVG) to it.
    Editor.prototype.openImportFiles = function (pick) {
        var self = this, st = this.importFiles, timer = null;

        st.pick = (typeof pick === 'function') ? pick : null;
        var find = el('input', { type: 'search', class: 'pgie-input pgie-stkfind', value: st.search, placeholder: t('stickerFilesFind'), 'aria-label': t('stickerFilesFind') });
        var close = button('pgie-ibtn', t('close'), 'close', function () { self.closeConfirm(); });

        st.grid = el('div', { class: 'pgie-stkfiles pgie-importfiles' });
        find.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { self.loadImportFiles(find.value); }, 350);
        });

        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-import-title' }, [
            el('div', { class: 'pgie-confirm-box pgie-importbox' }, [
                el('div', { class: 'pgie-importhead' }, [el('h2', { id: 'pgie-import-title', text: t('importSite') }), close]),
                find,
                st.grid
            ])
        ]);
        this.confirmReturn = st.pick ? document.activeElement : this.importBtn;
        this.root.appendChild(this.confirmBox);
        this.loadImportFiles(st.search);
        find.focus();
    };

    Editor.prototype.loadImportFiles = function (search) {
        var self = this, st = this.importFiles, ask = ++st.serial;

        st.search = search || '';
        this.request({ action: 'sticker_files', search: st.search, svg: !st.pick }).then(function (r) {
            if (ask === st.serial) {
                self.showImportFiles((r && (r.status === 'success')) ? (r.files || []) : null);
            }
        }, function () {
            if (ask === st.serial) {
                self.showImportFiles(null);
            }
        });
    };

    Editor.prototype.showImportFiles = function (list) {
        var self = this, grid = this.importFiles.grid;

        if (!grid || !this.confirmBox || !this.confirmBox.contains(grid)) {
            return;
        }

        grid.textContent = '';

        if (!list || !list.length) {
            grid.appendChild(el('p', { class: 'pgie-note', text: t(list ? 'stickerFilesNone' : 'stickerFilesError') }));
            return;
        }

        list.forEach(function (f) {
            grid.appendChild(el('button', { type: 'button', class: 'pgie-stkfile', title: f.name, 'aria-label': f.name, onclick: function () {
                var pick = self.importFiles.pick;

                self.closeConfirm();

                if (pick) {
                    pick(f);
                    return;
                }

                self.toSelect();

                if (f.svg) {
                    self.importSvgRequest({ file_id: f.id }, f.name);
                } else {
                    self.addFilePicture(f);
                }
            } }, [
                el('img', { src: f.url, alt: '', loading: 'lazy', decoding: 'async', draggable: 'false' })
            ]));
        });
    };

    // The Import button, before Save, where layers can be added: not on the
    // mini bar or a file being optimised.
    Editor.topItems.push(function () {
        var self = this;

        if (this.tools.list.indexOf('select') < 0) {
            return null;
        }

        this.importBtn = el('button', { type: 'button', class: 'pgie-btn pgie-importbtn', title: t('import'), 'aria-haspopup': 'menu', 'aria-expanded': 'false', onclick: function () {
            self.toggleImportMenu();
        } }, [X.icon('import'), el('span', { class: 'pgie-vlabel', text: t('import') })]);
        this.importBox = el('div', { class: 'pgie-importwrap' }, [this.importBtn]);

        return this.importBox;
    });

    Editor.inits.push(function () {
        this.importMenu = null;
        this.importFiles = { search: '', serial: 0, grid: null };
    });

    // Escape closes the menu first, the focus back on its button.
    Editor.escapes.unshift(function () {
        if (!this.importMenu) {
            return false;
        }

        this.toggleImportMenu(false);
        this.importBtn.focus();

        return true;
    });

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
            check();
        };
        // A custom side must be a number of 16-8000 px: an empty or refused
        // field (the browser reads '12,5' as '') is marked and Create waits,
        // instead of the side silently becoming the 16 px minimum.
        var sideOk = function (v) {
            var n = (String(v).trim() === '') ? NaN : Number(v);

            return isFinite(n) && (n >= 16) && (n <= 8000);
        };
        var check = function () {
            var mine = (s.preset === 'custom'), okW = !mine || sideOk(w.value), okH = !mine || sideOk(h.value);

            w.setAttribute('aria-invalid', okW ? 'false' : 'true');
            h.setAttribute('aria-invalid', okH ? 'false' : 'true');
            create.disabled = !(okW && okH);

            return okW && okH;
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
                check();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();

                    if (check()) {
                        self.closeNewImage(s);
                    }
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

    // ---- the picture as the bottom layer ----

    var PIC_HANDLES = ['nw', 'ne', 'se', 'sw'];

    // The picture is the Select tool's to move: unlocked, not while cropping.
    Editor.prototype.pictureOn = function () {
        return this.ready && (this.tool === 'select') && !this.cropping() && M.pictureFree(this.doc);
    };

    // The picture selected (true) or not; a layer selected drops it.
    Editor.prototype.selectPicture = function (on) {
        this.board.pic = !!on && M.pictureFree(this.doc);

        if (this.board.pic) {
            this.sel = [];
        }

        this.render();
    };

    // Unlocked or locked again, where it is: one undo step. Unlocking a
    // photo without a canvas makes one on the picture.
    Editor.prototype.lockPicture = function (locked) {
        var next = M.lockPicture(this.doc, locked);

        this.board.pic = !locked && (this.tool === 'select');

        if (this.board.pic) {
            this.sel = [];
        }

        this.change(function (doc) { doc.canvas = next.canvas; });
    };

    // The picture deleted: the canvas with its fill and the layers become
    // an artboard (M.dropPicture()), one undo step.
    Editor.prototype.deletePicture = function () {
        this.board.pic = false;
        this.replaceDoc(M.dropPicture(this.doc));
        this.syncKind();
    };

    // The rail, the picture and the frame's fill follow the document's kind
    // after a deletion of the picture and its undo or redo.
    Editor.prototype.syncKind = function () {
        var a = this.doc.artboard, want = a ? 'canvas' : this.board.profile;

        if (this.img) {
            this.img.style.display = a ? 'none' : '';
        }

        this.frame.style.background = (a && (a.background !== 'transparent')) ? a.background : '';
        this.frame.classList.toggle('pgie-checker', !!a && (a.background === 'transparent'));

        if (a || !M.pictureFree(this.doc)) {
            this.board.pic = false;
        }

        if (!this.tools.mini && this.board.profile && (this.tools.profile !== want)) {
            this.tools = M.resolveTools(want, this.opts.tools);

            if (this.tools.list.indexOf(this.tool) < 0) {
                this.tool = this.tools.list[0];
            }

            this.renderTools();
            this.fit();
        }
    };

    // A press with Select: a handle of the selected picture resizes it, a
    // press on the picture where no layer lies selects and moves it.
    var startDrag = Editor.prototype.startDrag;

    Editor.prototype.startDrag = function (e) {
        var b = this.board, h = e.target && e.target.getAttribute && e.target.getAttribute('data-h'), p, box, hit;

        if (!this.pictureOn()) {
            b.pic = false;
            return startDrag.call(this, e);
        }

        p = this.toDoc(e.clientX, e.clientY);
        box = M.pictureBox(this.doc);

        if (b.pic && h && b.picEl && b.picEl.contains(e.target)) {
            this.drag = { kind: 'picture', h: h, start: box, doc: M.clone(this.doc) };
        } else {
            // A redacted area takes the press only inside its outline; an
            // erased or adjusted area is the picture's own, so a press in a
            // see-through hole takes the picture with it.
            hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k, function (l, q) { return !M.pictureArea(l) && M.areaHit(l, q); });

            if (hit || (p[0] < box.x) || (p[1] < box.y) || (p[0] > box.x + box.w) || (p[1] > box.y + box.h)) {
                b.pic = false;
                return startDrag.call(this, e);
            }

            if (!b.pic) {
                this.selectPicture(true);
            }

            this.drag = { kind: 'picture', h: null, at: p, start: box, doc: M.clone(this.doc) };
        }

        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    // A move snaps to the canvas and the layers (Alt moves freely); a
    // corner resizes, the ratio kept. One undo step a drag.
    var moveDrag = Editor.prototype.moveDrag;

    Editor.prototype.moveDrag = function (e) {
        var d = this.drag, p, r, k = this.view.k;

        if (!d || (d.kind !== 'picture')) {
            return moveDrag.call(this, e);
        }

        p = this.toDoc(e.clientX, e.clientY);

        if (d.h) {
            r = M.resizeBox({ x: d.start.x, y: d.start.y, w: d.start.w, h: d.start.h }, d.h, p[0], p[1], true, 8 / k);
        } else {
            r = { x: d.start.x + p[0] - d.at[0], y: d.start.y + p[1] - d.at[1], w: d.start.w, h: d.start.h };
            this.guides = null;

            if (!e.altKey) {
                var lines = this.snapLines(), s = M.snapBox(r, lines.x, lines.y, 6 / k);

                r.x += s.dx;
                r.y += s.dy;
                this.guides = (s.gx.length || s.gy.length) ? s : null;
            }
        }

        var next = M.placePicture(d.doc, r);

        this.change(function (doc) {
            doc.canvas = next.canvas;
            doc.layers = next.layers;
        }, 'drag', true);
    };

    Editor.pointers.select.down = Editor.prototype.startDrag;
    Editor.pointers.select.move = Editor.prototype.moveDrag;

    // Choosing a layer drops the picture from the selection.
    var select = Editor.prototype.select;

    Editor.prototype.select = function (ids) {
        if (ids.length) {
            this.board.pic = false;
        }

        select.call(this, ids);
    };

    // The selected picture's outline and corner handles over the stage.
    Editor.views.push(function () {
        var b = this.board, on = b.pic && this.pictureOn(), box, v, vr, k, d;

        b.picEl = null;

        if (!on) {
            return;
        }

        box = M.pictureBox(this.doc);
        v = this.view;
        vr = this.viewRect();
        k = v.k;
        d = el('div', { class: 'pgie-selbox pgie-picsel' }, PIC_HANDLES.map(function (h) { return el('span', { class: 'pgie-sh', 'data-h': h }); }));
        d.style.left = (v.x + (box.x - vr.x) * k) + 'px';
        d.style.top = (v.y + (box.y - vr.y) * k) + 'px';
        d.style.width = (box.w * k) + 'px';
        d.style.height = (box.h * k) + 'px';
        this.selEl.appendChild(d);
        b.picEl = d;
    });

    // Delete removes the selected picture, the arrows nudge it.
    Editor.keys.push(function (e, mod, k) {
        var dir = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[k], step = e.shiftKey ? 10 : 1, box, next;

        if (!this.board.pic || !this.pictureOn() || mod) {
            return false;
        }

        if ((k === 'Delete') || (k === 'Backspace')) {
            this.deletePicture();
            return true;
        }

        if (!dir) {
            return false;
        }

        box = M.pictureBox(this.doc);
        next = M.placePicture(this.doc, { x: box.x + dir[0] * step, y: box.y + dir[1] * step, w: box.w, h: box.h });
        this.change(function (doc) {
            doc.canvas = next.canvas;
            doc.layers = next.layers;
        }, 'nudge');

        return true;
    });

    Editor.escapes.push(function () {
        if (!this.board.pic) {
            return false;
        }

        this.selectPicture(false);

        return true;
    });

    // The picture's row at the foot of the layers list: Background, its
    // lock; a press selects it when it is unlocked.
    var layerList = Editor.prototype.layerList;

    Editor.prototype.layerList = function (body) {
        var self = this, free = M.pictureFree(this.doc), list, on = this.board.pic && free;

        layerList.call(this, body);

        if (!this.ready || !this.doc.source || this.doc.artboard || this.tools.mini) {
            return;
        }

        list = body.querySelector('.pgie-layerlist');

        if (!list) {
            list = el('ul', { class: 'pgie-layerlist', 'aria-label': t('layers') });
            body.appendChild(list);
        }

        list.appendChild(el('li', { class: 'pgie-lrow pgie-picrow' + (on ? ' on' : '') }, [
            el('button', { type: 'button', class: 'pgie-lname', 'aria-pressed': on ? 'true' : 'false', 'aria-disabled': free ? null : 'true', onclick: function () {
                if (!free) {
                    return;
                }

                self.tool = 'select';
                self.selectPicture(true);
            } }, [X.icon('image'), el('span', { text: t('boardBackground') })]),
            button('pgie-ibtn sm', free ? t('lock') : t('unlock'), free ? 'unlock' : 'lock', function () { self.lockPicture(free); })
        ]));
    };

    // Select's panel: the selected picture's lock and delete; with nothing
    // selected on a photo the list, the picture's row included.
    var picturePanel = Editor.panels.select;

    Editor.panels.select = function (body) {
        var self = this;

        // The file's name under the panel's title (renderPanel() below).
        if (this.board.pic && this.pictureOn()) {
            body.appendChild(el('div', { class: 'pgie-sec' }, [
                el('div', { class: 'pgie-picname' }, [X.icon('image'), el('span', { text: this.nameEl.textContent || t('pictureTitle') })]),
                el('div', { class: 'pgie-btnrow' }, [
                    button('pgie-ibtn', t('lock'), 'lock', function () { self.lockPicture(true); }),
                    button('pgie-ibtn', t('pictureDelete'), 'trash', function () { self.deletePicture(); })
                ])
            ]));
            return;
        }

        if (this.doc.source && !this.doc.artboard && !this.sel.length) {
            this.layerList(body);
            return;
        }

        picturePanel.call(this, body);
    };

    // The selected picture titles the panel as its row in the layers list
    // does (Background), not as the list.
    var renderPanel = Editor.prototype.renderPanel;

    Editor.prototype.renderPanel = function () {
        renderPanel.call(this);

        var h = this.panel && !this.layersOpen && this.board.pic && this.pictureOn() && this.panel.querySelector('.pgie-ph h2');

        if (h) {
            h.textContent = t('boardBackground');
            this.panel.setAttribute('aria-label', h.textContent);
        }
    };

    // The Canvas panel on a photo: unlock the picture there too.
    var boardPanel = Editor.panels.artboard;

    Editor.panels.artboard = function (body) {
        var self = this, box;

        boardPanel.call(this, body);

        if (this.doc.source && !this.doc.artboard && this.ready) {
            box = el('input', { type: 'checkbox' });
            box.checked = M.pictureFree(this.doc);
            box.addEventListener('change', function () { self.lockPicture(!box.checked); });
            body.appendChild(el('label', { class: 'pgie-check' }, [box, el('span', { text: t('pictureFree') })]));
        }
    };

    Editor.inits.push(function () {
        // pic: the picture selected; picEl: its outline; profile: the
        // photo's tools, given back when a deletion is undone.
        this.board.pic = false;
        this.board.picEl = null;
        this.board.profile = this.opts.blank ? null : this.tools.profile;
    });

    Editor.hints.artboard = 'hintArtboard';
    Editor.hints.image = 'hintImage';
}(window.PgImageEditor.parts));
