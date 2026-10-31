/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: window.PgImageEditor.
 *
 * The modal every screen edits pictures in. pg_image_editor_includes()
 * prints window.pgImageEditor, which loads this bundle on the first open and
 * calls PgImageEditor.open(options):
 *
 *   pgImageEditor.open({
 *       src: url, fileId: id, fileName: name,    a picture, or
 *       blank: { width, height, background },    an empty artboard
 *       save: { mode, folderId, name },          how Save writes (below)
 *       profile: 'photo' | 'canvas' | 'annotate' | 'optimise',
 *       tools: ['arrow', ...],                    a subset: the mini bar
 *       theme: 'light' | 'dark',                  default: the panel's
 *       fullscreen: true,                         open filling the window
 *       onSave: function (result) {},            result.doc, result.file
 *       onClose: function () {}
 *   }).then(function (result) {});              null when closed unsaved
 *
 * Save goes to image_editor_api.php (pgImageEditorConfig.api): a file
 * (fileId) is written over ('replace', or 'copy' when the server says it may
 * not be) and an artboard given save.folderId becomes a new file ('new');
 * result.file is the saved file. Without either, or with save.mode
 * 'callback', the document goes to onSave and nothing is written.
 *
 * One editor is open at a time. Labels come from
 * window.pgImageEditorConfig.strings, geometry from PgImageEditorModel
 * (assets/js/pg_image_editor_model.js). docs/memory/ui/image-editor-modal.md
 * describes the parts, docs/memory/platform/image-editor-save.md the save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var M = window.PgImageEditorModel;
    var SVGNS = 'http://www.w3.org/2000/svg';
    var PAD = 32;
    var ZOOM_STEP = 1.25;

    var ICONS = {
        select: 'M6 3.5l12 7.1-5.2 1.5-2.5 5.1z',
        crop: 'M7 3v12a2 2 0 0 0 2 2h12M3 7h12a2 2 0 0 1 2 2v12',
        adjust: 'M4 7h9M17 7h3M15 4.5v5M4 12h3M11 12h9M9 9.5v5M4 17h11M19 17h1M17 14.5v5',
        draw: 'M4 20l1.2-4.6L15.4 5.2a2.1 2.1 0 0 1 3 0l.4.4a2.1 2.1 0 0 1 0 3L8.6 18.8zM13.6 7l3.4 3.4',
        text: 'M5 7.5V5h14v2.5M12 5v14M9 19h6',
        redact: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        sticker: 'M3.5 11.8V4.6a1.1 1.1 0 0 1 1.1-1.1h7.2l8.3 8.3a1.5 1.5 0 0 1 0 2.1l-6.1 6.1a1.5 1.5 0 0 1-2.1 0zM8 8h.01',
        frame: 'M3.5 3.5h17v17h-17zM7 7h10v10H7z',
        vector: 'M5 18.5C6 10 13 6 19 5.5M3.5 17h3v3h-3zM17.5 4h3v3h-3zM11 6.5l-5 1.5',
        image: 'M3.5 5.5h17v13h-17zM3.5 15.5l5-5 4.5 4.5 2.5-2.5 5 5M15.5 9.5h.01',
        artboard: 'M7 3v4M3 7h4M17 3v4M21 7h-4M7 21v-4M3 17h4M17 21v-4M21 17h-4M7 7h10v10H7z',
        export: 'M12 4v11M7.5 10.5L12 15l4.5-4.5M4.5 15v4.5h15V15',
        arrow: 'M5 19L19 5M10.5 5H19v8.5',
        line: 'M5 19L19 5',
        rect: 'M4 6.5h16v11H4z',
        ellipse: 'M12 5.5c4.7 0 8.5 2.9 8.5 6.5s-3.8 6.5-8.5 6.5-8.5-2.9-8.5-6.5S7.3 5.5 12 5.5z',
        pen: 'M3.5 16.5c2.5-5 4.5-7.5 6.5-5.5s.5 5.5 3 5.5 3.5-6 7.5-8.5',
        layers: 'M12 3.5l8.5 4.5-8.5 4.5L3.5 8zM3.5 12l8.5 4.5 8.5-4.5M3.5 16l8.5 4.5 8.5-4.5',
        undo: 'M9 14L4.5 9.5 9 5M4.5 9.5H14a5.5 5.5 0 0 1 0 11h-3',
        redo: 'M15 14l4.5-4.5L15 5M19.5 9.5H10a5.5 5.5 0 0 0 0 11h3',
        close: 'M6 6l12 12M18 6L6 18',
        expand: 'M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5',
        shrink: 'M9 4v5H4M15 4v5h5M20 15h-5v5M4 15h5v5',
        plus: 'M12 5v14M5 12h14',
        minus: 'M5 12h14',
        fit: 'M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5M9 9h6v6H9z',
        rotl: 'M3.5 12a8.5 8.5 0 1 0 2.5-6L3.5 8.5M3.5 3.5v5h5',
        rotr: 'M20.5 12a8.5 8.5 0 1 1-2.5-6l2.5 2.5M20.5 3.5v5h-5',
        fliph: 'M12 3v18M8.5 7L4 12l4.5 5zM15.5 7l4.5 5-4.5 5z',
        flipv: 'M3 12h18M7 8.5L12 4l5 4.5zM7 15.5l5 4.5 5-4.5z',
        chev: 'M6.5 9.5l5.5 5.5 5.5-5.5',
        eye: 'M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12zM12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        eyeoff: 'M3.5 3.5l17 17M10.4 5.7A9 9 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-2.9 3.7M6.6 6.6C4 8.3 2.5 12 2.5 12s3.5 6.5 9.5 6.5a8.8 8.8 0 0 0 4.1-1',
        lock: 'M6.5 11h11v9h-11zM8.5 11V8a3.5 3.5 0 0 1 7 0v3',
        unlock: 'M6.5 11h11v9h-11zM8.5 11V8a3.5 3.5 0 0 1 6.8-1.2',
        front: 'M12 20V9M7 14l5-5 5 5M5 4.5h14',
        forward: 'M12 19V5M7 10l5-5 5 5',
        backward: 'M12 5v14M7 14l5 5 5-5',
        back: 'M12 4v11M7 10l5 5 5-5M5 19.5h14',
        alignleft: 'M4 3v18M8 7h10v4H8zM8 14h6v4H8z',
        aligncenter: 'M12 3v18M6 7h12v4H6zM8 14h8v4H8z',
        alignright: 'M20 3v18M6 7h10v4H6zM10 14h6v4h-6z',
        aligntop: 'M3 4h18M7 8h4v10H7zM14 8h4v6h-4z',
        alignmiddle: 'M3 12h18M7 6h4v12H7zM14 8h4v8h-4z',
        alignbottom: 'M3 20h18M7 6h4v10H7zM14 10h4v6h-4z',
        distx: 'M4 3v18M20 3v18M9.5 8h5v8h-5z',
        disty: 'M3 4h18M3 20h18M8 9.5h8v5H8z',
        duplicate: 'M8.5 8.5h11v11h-11zM4.5 15.5v-11h11',
        trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3'
    };

    var current = null, serial = 0;

    function config() {
        return window.pgImageEditorConfig || {};
    }

    // A layer of doc by id.
    function find(doc, id) {
        for (var i = 0; i < doc.layers.length; i++) {
            if (doc.layers[i].id === id) {
                return doc.layers[i];
            }
        }

        return null;
    }

    function t(key) {
        var s = config().strings || {};

        return (s[key] !== undefined) ? s[key] : key;
    }

    // A DOM element: attributes (on* are listeners, text is the text) and
    // children (strings become text).
    function el(tag, attrs, children) {
        var n = document.createElement(tag);

        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];

            if ((v === null) || (v === undefined) || (v === false)) {
                return;
            }

            if (k === 'class') {
                n.className = v;
            } else if (k === 'text') {
                n.textContent = v;
            } else if (k.slice(0, 2) === 'on') {
                n.addEventListener(k.slice(2), v);
            } else {
                n.setAttribute(k, (v === true) ? '' : String(v));
            }
        });

        (children || []).forEach(function (c) {
            if (c) {
                n.appendChild((typeof c === 'string') ? document.createTextNode(c) : c);
            }
        });

        return n;
    }

    function icon(name) {
        var s = document.createElementNS(SVGNS, 'svg'), p = document.createElementNS(SVGNS, 'path');

        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('aria-hidden', 'true');
        s.setAttribute('focusable', 'false');
        s.setAttribute('class', 'pgie-ic');
        p.setAttribute('d', ICONS[name] || '');
        s.appendChild(p);

        return s;
    }

    // A panel section: a label line (with an optional value on the right) and
    // its control.
    function section(label, control, value) {
        return el('div', { class: 'pgie-sec' }, [el('div', { class: 'pgie-lbl' }, [el('span', { text: label }), value || null]), control]);
    }

    function tile(iconName, label, onClick) {
        var b = el('button', { type: 'button', class: 'pgie-tile', onclick: onClick });

        b.appendChild(icon(iconName));
        b.appendChild(el('span', { text: label }));

        return b;
    }

    // A number with the panel's decimal separator.
    function decimal(v) {
        var cfg = window.pgImageEditorConfig, sep = (cfg && cfg.decimal) || '.';

        return String(Math.round(v * 10) / 10).replace('.', sep);
    }

    // The crop shape as a border radius on screen: rounded corners take 12 %
    // of the shorter side, a circle is the ellipse in the box.
    function shapeRadius(shape, box, k) {
        if (shape === 'circle') {
            return '50%';
        }

        return (shape === 'rounded') ? (Math.min(box.w, box.h) * k * 0.12) + 'px' : '';
    }

    // An icon button named by its label; withText also shows the label.
    function button(cls, label, iconName, onClick, withText) {
        var b = el('button', { type: 'button', class: cls, title: label, 'aria-label': withText ? null : label, onclick: onClick });

        if (iconName) {
            b.appendChild(icon(iconName));
        }

        if (withText) {
            b.appendChild(el('span', { text: label }));
        }

        return b;
    }

    function panelTheme() {
        var a = document.documentElement.getAttribute('data-bs-theme');

        return (a === 'dark') ? 'dark' : 'light';
    }

    function Editor(opts, resolve) {
        var save = opts.save || {};

        this.opts = opts;
        this.resolve = resolve;
        // replace, copy, new or callback: see the top of this file.
        this.saveMode = save.mode || (opts.fileId ? 'replace' : ((opts.blank && save.folderId) ? 'new' : 'callback'));

        if (!config().api) {
            this.saveMode = 'callback';
        }

        this.saving = false;
        this.saveMenu = null;
        this.adjustTab = 'adjust';
        this.adjustMore = false;
        this.fxId = 'pgie-fx-' + (++serial);
        // Layers: the selected ids, a drag on the stage, copied layers,
        // loaded pictures by address, and whether the SVG is out of date.
        this.sel = [];
        this.drag = null;
        this.clip = null;
        this.guides = null;
        this.pictures = {};
        this.layersDirty = true;
        this.tools = M.resolveTools(opts.blank ? (opts.profile || 'canvas') : opts.profile, opts.tools);
        this.tool = this.tools.list[0];
        // On a small screen the panel is a sheet (pg_image_editor.src.css):
        // open for a tool, closed for Select until it is asked for.
        this.sheetOpen = (this.tool !== 'select');
        this.tap = null;
        this.lastTouch = 0;
        this.doc = M.newDocument(opts);
        this.history = new M.History(100);
        this.dirty = false;
        // The document as it opened: dirty is any difference from it.
        this.opening = null;
        this.ready = false;
        this.view = { k: 1, x: 0, y: 0 };
        this.fitMode = true;
        this.full = !!opts.fullscreen;
        this.space = false;
        this.pointers = {};
        this.pan = null;
        this.returnFocus = document.activeElement;
        this.build();
        this.load();
    }

    Editor.prototype.build = function () {
        var self = this, mini = this.tools.mini;
        var theme = (this.opts.theme === 'dark' || this.opts.theme === 'light') ? this.opts.theme : panelTheme();
        var name = this.opts.fileName || (this.opts.blank ? t('newImage') : '');

        this.undoBtn = button('pgie-ibtn', t('undo'), 'undo', function () { self.undo(); });
        this.redoBtn = button('pgie-ibtn', t('redo'), 'redo', function () { self.redo(); });
        this.fullBtn = button('pgie-ibtn pgie-fullbtn', t('fullscreen'), 'expand', function () { self.setFull(!self.full); });
        this.meta = el('div', { class: 'pgie-fmeta' });

        this.nameEl = el('div', { class: 'pgie-fname', text: name || t('dialog') });
        this.saveBox = el('div', { class: 'pgie-savebox' });

        var top = el('header', { class: 'pgie-top' }, [
            mini ? null : button('pgie-ibtn', t('close'), 'close', function () { self.requestClose(); }),
            el('div', { class: 'pgie-file' }, [this.nameEl, this.meta]),
            mini ? null : el('div', { class: 'pgie-sepv' }),
            mini ? null : this.undoBtn,
            mini ? null : this.redoBtn,
            el('div', { class: 'pgie-grow' }),
            mini ? button('pgie-btn', t('cancel'), null, function () { self.requestClose(); }, true) : null,
            this.saveBox,
            this.fullBtn
        ]);

        this.renderSave();

        this.img = null;
        this.layersSvg = document.createElementNS(SVGNS, 'svg');
        this.layersSvg.setAttribute('class', 'pgie-layers');
        this.layersSvg.setAttribute('aria-hidden', 'true');
        // The colour step and sharpening as an SVG filter on the picture, the
        // vignette as a gradient over it (renderColor()).
        this.fxSvg = document.createElementNS(SVGNS, 'svg');
        this.fxSvg.setAttribute('class', 'pgie-fx');
        this.fxSvg.setAttribute('aria-hidden', 'true');
        this.fxSvg.innerHTML = '<filter id="' + this.fxId + '" color-interpolation-filters="sRGB" x="0" y="0" width="100%" height="100%">'
            + '<feColorMatrix type="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 1 0"/>'
            + '<feComponentTransfer><feFuncR type="gamma" exponent="1"/><feFuncG type="gamma" exponent="1"/><feFuncB type="gamma" exponent="1"/></feComponentTransfer>'
            + '<feConvolveMatrix order="3" kernelMatrix="0 0 0 0 1 0 0 0 0" preserveAlpha="true" edgeMode="duplicate"/></filter>';
        this.vignette = el('div', { class: 'pgie-vignette' });
        this.frame = el('div', { class: 'pgie-frame' }, [this.vignette, this.layersSvg]);
        this.notice = el('div', { class: 'pgie-notice', role: 'status', text: this.opts.blank ? '' : t('loading') });
        this.cropEl = el('div', { class: 'pgie-cropbox' }, [
            el('span', { class: 'pgie-third pgie-v1' }), el('span', { class: 'pgie-third pgie-v2' }),
            el('span', { class: 'pgie-third pgie-h1' }), el('span', { class: 'pgie-third pgie-h2' })
        ]);
        ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].forEach(function (h) {
            self.cropEl.appendChild(el('span', { class: 'pgie-h', 'data-h': h }));
        });
        this.cropEl.addEventListener('pointerdown', function (e) { self.startCropDrag(e); });
        this.cropEl.addEventListener('pointermove', function (e) { self.moveCropDrag(e); });
        this.cropEl.addEventListener('pointerup', function (e) { self.endCropDrag(e); });
        this.cropEl.addEventListener('pointercancel', function (e) { self.endCropDrag(e); });
        this.selEl = el('div', { class: 'pgie-sel' });
        this.stage = el('main', { class: 'pgie-stage', tabindex: '-1' }, [this.frame, this.selEl, this.cropEl, this.notice]);

        var body = el('div', { class: 'pgie-body' });

        if (mini) {
            this.mini = el('div', { class: 'pgie-mini', role: 'toolbar', 'aria-label': t('tools') });
            this.stage.appendChild(this.mini);
            body.appendChild(this.stage);
        } else {
            this.rail = el('nav', { class: 'pgie-rail', 'aria-label': t('tools') });
            this.panel = el('aside', { class: 'pgie-panel' });
            body.appendChild(this.rail);
            body.appendChild(this.stage);
            body.appendChild(this.panel);
        }

        this.zoomLabel = el('button', { type: 'button', class: 'pgie-zoomval', title: t('zoomActual'), onclick: function () { self.zoomTo(1); } });
        this.sizeLabel = el('span', { class: 'pgie-size' });

        this.hint = el('span', { class: 'pgie-hint', text: t('hintView') });

        var foot = el('footer', { class: 'pgie-foot' }, [
            this.hint,
            el('div', { class: 'pgie-grow' }),
            this.sizeLabel,
            button('pgie-ibtn sm', t('zoomOut'), 'minus', function () { self.zoomBy(1 / ZOOM_STEP); }),
            this.zoomLabel,
            button('pgie-ibtn sm', t('zoomIn'), 'plus', function () { self.zoomBy(ZOOM_STEP); }),
            button('pgie-ibtn sm', t('zoomFit'), 'fit', function () { self.fit(); })
        ]);

        this.confirmBox = null;
        this.root = el('div', { class: 'pgie' + (theme === 'dark' ? ' pgie-dark' : '') + (mini ? ' pgie-ismini' : '') }, [this.fxSvg, top, body, foot]);
        this.dialog = el('dialog', { class: 'pgie-dialog', 'aria-label': name ? t('dialog') + ': ' + name : t('dialog') }, [this.root]);

        // Escape is handled on keydown (onKey), whose prevented default
        // sends no cancel; this catches the other close requests (a back
        // gesture). The browser may refuse to hold a second cancel without a
        // click between, so a close the editor did not make reopens it.
        this.dialog.addEventListener('cancel', function (e) {
            e.preventDefault();
            self.onEscape();
        });
        this.dialog.addEventListener('close', function () { self.onDialogClosed(); });
        this.dialog.addEventListener('keydown', function (e) { self.onKey(e); });
        this.dialog.addEventListener('paste', function (e) { self.onPaste(e); });
        // A press outside the save menu closes it.
        this.dialog.addEventListener('pointerdown', function (e) {
            if (self.saveMenu && !self.saveBox.contains(e.target)) {
                self.toggleSaveMenu(false);
            }
        }, true);
        this.dialog.addEventListener('keyup', function (e) {
            if (e.key === ' ' || e.code === 'Space') {
                self.space = false;
                self.stage.classList.remove('pgie-panning');
            }
        });
        this.stage.addEventListener('wheel', function (e) { self.onWheel(e); }, { passive: false });
        this.stage.addEventListener('pointerdown', function (e) { self.onDown(e); });
        this.stage.addEventListener('pointermove', function (e) { self.onMove(e); });
        this.stage.addEventListener('pointerup', function (e) { self.onUp(e); });
        this.stage.addEventListener('pointercancel', function (e) { self.onUp(e); });
        // Touch has its own double tap (onTap); a dblclick the browser makes
        // from those taps is left out.
        this.stage.addEventListener('dblclick', function (e) {
            if (Date.now() - self.lastTouch > 700) {
                self.onDouble(e);
            }
        });

        this.onFullscreenChange = function () {
            if (!document.fullscreenElement && self.full) {
                self.setFull(false, true);
            }
        };
        document.addEventListener('fullscreenchange', this.onFullscreenChange);

        if (typeof ResizeObserver !== 'undefined') {
            this.resizer = new ResizeObserver(function () { self.onResize(); });
            this.resizer.observe(this.stage);
        } else {
            this.onWindowResize = function () { self.onResize(); };
            window.addEventListener('resize', this.onWindowResize);
        }

        document.body.appendChild(this.dialog);
        this.dialog.classList.toggle('pgie-dialog--full', this.full);
        this.dialog.showModal();
        // The stage takes the focus, so the keys work at once and no button
        // is drawn focused on opening.
        this.stage.focus();
        this.renderTools();
        this.render();
    };

    // ---- the picture ----

    Editor.prototype.load = function () {
        var self = this;

        if (this.doc.artboard) {
            this.frame.style.background = this.doc.artboard.background === 'transparent' ? '' : this.doc.artboard.background;
            this.frame.classList.toggle('pgie-checker', this.doc.artboard.background === 'transparent');
            this.setReady();
            return;
        }

        if ((this.saveMode !== 'callback') && this.opts.fileId) {
            // The server names the file, its address and whether a save may
            // write over it.
            this.request({ action: 'open', file_id: this.opts.fileId }).then(function (r) {
                if (!r || (r.status !== 'success')) {
                    self.fail((r && r.message) || t('loadError'));
                    return;
                }

                self.file = r.file;

                if (!r.file.replace && (self.saveMode === 'replace')) {
                    self.saveMode = 'copy';
                    self.renderSave();
                }

                self.nameEl.textContent = r.file.name;
                self.dialog.setAttribute('aria-label', t('dialog') + ': ' + r.file.name);

                if (r.file.notice) {
                    self.showMessage(r.file.notice);
                }

                self.loadImage(self.opts.src || r.file.url);
            }, function () {
                self.fail(t('loadError'));
            });

            return;
        }

        this.loadImage(this.opts.src || '');
    };

    Editor.prototype.fail = function (message) {
        this.notice.textContent = message;
        this.notice.classList.add('pgie-error');
    };

    Editor.prototype.loadImage = function (src) {
        var self = this;
        var img = new Image();

        img.className = 'pgie-img';
        img.alt = '';
        img.draggable = false;
        img.onload = function () {
            self.doc.source.width = img.naturalWidth;
            self.doc.source.height = img.naturalHeight;
            img.style.width = img.naturalWidth + 'px';
            img.style.height = img.naturalHeight + 'px';
            self.img = img;
            self.frame.insertBefore(img, self.vignette);
            self.setReady();
        };
        img.onerror = function () {
            self.fail(t('loadError'));
        };
        img.src = src;
    };

    Editor.prototype.setReady = function () {
        if (!this.opening) {
            this.opening = M.clone(this.doc);
        }

        this.ready = true;
        this.notice.textContent = '';
        this.renderMeta();
        this.fit();
    };

    // The size the picture will be saved at, under the file name.
    Editor.prototype.renderMeta = function () {
        var a = this.doc.artboard, out = a ? { w: a.width, h: a.height } : M.outputSize(this.doc);

        this.meta.textContent = out.w + ' × ' + out.h + ' px';
        this.sizeLabel.textContent = this.meta.textContent;
    };

    // ---- geometry ----

    Editor.prototype.cropping = function () {
        return (this.tool === 'crop') && !!this.doc.source;
    };

    Editor.prototype.turns = function () {
        var g = this.doc.geometry, src = this.doc.source;

        return M.flipRot(src.width, src.height, g.rot, g.flipX, g.flipY);
    };

    // The part of the rotated space the frame shows: all of it while
    // cropping, else the crop box; a blank document's artboard.
    Editor.prototype.viewRect = function () {
        if (!this.doc.source) {
            return { x: 0, y: 0, w: this.doc.artboard.width, h: this.doc.artboard.height };
        }

        if (this.cropping()) {
            var fr = this.turns();

            return { x: 0, y: 0, w: fr.rw, h: fr.rh };
        }

        return M.cropBox(this.doc);
    };

    // A point of the page in the rotated space.
    Editor.prototype.toDoc = function (clientX, clientY) {
        var r = this.stage.getBoundingClientRect(), v = this.view, vr = this.viewRect();

        return [(clientX - r.left - v.x) / v.k + vr.x, (clientY - r.top - v.y) / v.k + vr.y];
    };

    // ---- view ----

    Editor.prototype.stageSize = function () {
        return { w: this.stage.clientWidth, h: this.stage.clientHeight };
    };

    Editor.prototype.fit = function () {
        if (!this.ready) {
            return;
        }

        var vr = this.viewRect(), st = this.stageSize();

        this.cover = this.panelCover();
        this.fitMode = true;
        this.view = M.fitView(vr.w, vr.h, st.w, st.h, this.cover, (st.w < 600) ? 12 : PAD);
        this.renderView();
    };

    // How much of the stage's right side an open panel sheet covers
    // (pg_image_editor.src.css: an absolute panel on a small or short
    // screen); 0 where the panel sits beside or below the stage.
    Editor.prototype.panelCover = function () {
        if (!this.panel || !this.sheetOpen || (window.getComputedStyle(this.panel).position !== 'absolute')) {
            return 0;
        }

        var s = this.stage.getBoundingClientRect(), p = this.panel.getBoundingClientRect();

        return (p.width > 0) ? Math.max(0, Math.round(s.right - p.left)) : 0;
    };

    Editor.prototype.zoomBy = function (factor, px, py) {
        if (!this.ready) {
            return;
        }

        var st = this.stageSize();

        this.fitMode = false;
        this.view = M.zoomAt(this.view, factor, (px === undefined) ? st.w / 2 : px, (py === undefined) ? st.h / 2 : py);
        this.renderView();
    };

    Editor.prototype.zoomTo = function (k) {
        this.zoomBy(k / this.view.k);
    };

    Editor.prototype.onResize = function () {
        if (this.fitMode) {
            this.fit();
        }
    };

    Editor.prototype.renderView = function () {
        if (!this.ready) {
            return;
        }

        var vr = this.viewRect(), v = this.view, k = v.k;

        this.frame.style.transform = 'translate(' + v.x + 'px, ' + v.y + 'px)';
        this.frame.style.width = (vr.w * k) + 'px';
        this.frame.style.height = (vr.h * k) + 'px';
        this.frame.style.borderRadius = this.cropping() ? '' : shapeRadius(this.doc.geometry.cropShape, vr, k);
        this.layersSvg.setAttribute('viewBox', [vr.x, vr.y, vr.w, vr.h].join(' '));

        if (this.img) {
            var m = M.mul(M.mul(M.scale(k), M.translate(-vr.x, -vr.y)), M.pictureMatrix(this.doc));

            this.img.style.transform = 'matrix(' + m.map(function (n) { return Math.round(n * 1e6) / 1e6; }).join(', ') + ')';
        }

        this.zoomLabel.textContent = Math.round(k * 100) + ' %';
        this.renderColor();
        this.renderLayers();
        this.renderSelection();
        this.renderCrop();
    };

    // ---- adjust ----

    // The preview of the colour step, the sharpening and the vignette; the
    // server draws the same numbers (platform/image-editor-save.md).
    Editor.prototype.renderColor = function () {
        var v = M.colorValues(this.doc), c = M.colorMatrix(v), k = M.sharpenKernel(v.sharpen), stops = M.vignetteStops(v.vignette);
        var key = JSON.stringify([c, k, stops]);

        if (!this.img || (key === this.colorKey)) {
            return;
        }

        var f = this.fxSvg.firstChild, matrix = f.childNodes[0], funcs = f.childNodes[1].childNodes, convolve = f.childNodes[2];

        this.colorKey = key;
        matrix.setAttribute('values', (c ? c.matrix : [1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0]).join(' '));

        for (var i = 0; i < 3; i++) {
            funcs[i].setAttribute('exponent', String(c ? c.gamma : 1));
        }

        convolve.setAttribute('kernelMatrix', (k || [0, 0, 0, 0, 1, 0, 0, 0, 0]).join(' '));
        this.img.style.filter = (c || k) ? 'url(#' + this.fxId + ')' : '';
        this.vignette.style.background = stops ? 'radial-gradient(ellipse farthest-corner at 50% 50%, ' + stops.map(function (s) {
            return 'rgba(0, 0, 0, ' + s[1] + ') ' + (s[0] * 100) + '%';
        }).join(', ') + ')' : '';
    };

    Editor.prototype.hasColor = function () {
        var a = this.doc.adjust || {};

        return ((this.doc.filter || 'none') !== 'none') || Object.keys(a).some(function (key) { return !!a[key]; });
    };

    Editor.prototype.adjustPanel = function (body) {
        var self = this, tabs = el('div', { class: 'pgie-seg', role: 'tablist' });

        [['adjust', t('adjustments')], ['filters', t('filters')]].forEach(function (tab) {
            var on = (self.adjustTab === tab[0]);

            tabs.appendChild(el('button', { type: 'button', role: 'tab', class: on ? 'on' : '', 'aria-selected': on ? 'true' : 'false', text: tab[1], onclick: function () {
                self.adjustTab = tab[0];
                self.render();
            } }));
        });

        body.appendChild(tabs);

        if (this.adjustTab === 'filters') {
            body.appendChild(this.filterTiles());
            return;
        }

        // Five sliders, the rest under Details.
        M.ADJUST.forEach(function (d, i) {
            if ((i < 5) || self.adjustMore) {
                body.appendChild(self.adjustSlider(d));
            }
        });

        body.appendChild(el('button', { type: 'button', class: 'pgie-more' + (this.adjustMore ? ' on' : ''), 'aria-expanded': this.adjustMore ? 'true' : 'false', onclick: function () {
            self.adjustMore = !self.adjustMore;
            self.render();
        } }, [el('span', { text: t('details') }), icon('chev')]));
    };

    // A slider of one adjustment; a double click sets it back to 0.
    Editor.prototype.adjustSlider = function (d) {
        var self = this, key = d[0], value = Number(this.doc.adjust[key]) || 0;
        var shown = function (n) { return ((n > 0) ? '+' : '') + n; };
        var label = el('span', { class: 'pgie-val', text: shown(value) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(d[1]), max: String(d[2]), step: '1', value: String(value), 'aria-label': t('adj_' + key) });
        var set = function (n, step) {
            label.textContent = shown(n);
            self.change(function (doc) { doc.adjust[key] = n; }, step, true);
        };

        slider.addEventListener('input', function () { set(Number(slider.value), 'adjust-' + key); });
        // Letting go ends the undo step: the next drag is one of its own.
        slider.addEventListener('change', function () { self.history.key = null; });
        slider.addEventListener('dblclick', function () {
            slider.value = '0';
            set(0, null);
        });

        return section(t('adj_' + key), slider, label);
    };

    Editor.prototype.filterTiles = function () {
        var self = this, thumbs = this.filterThumbs();
        var grid = el('div', { class: 'pgie-filters', role: 'group', 'aria-label': t('filters') });

        M.FILTER_NAMES.forEach(function (name) {
            var on = ((self.doc.filter || 'none') === name);

            grid.appendChild(el('button', { type: 'button', class: 'pgie-ftile' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', onclick: function () {
                self.change(function (doc) { doc.filter = name; });
            } }, [
                el('span', { class: 'pgie-fthumb', style: thumbs[name] ? 'background-image: url(' + thumbs[name] + ')' : null }),
                el('span', { text: t('filter_' + name) })
            ]));
        });

        return grid;
    };

    // Small copies of the picture with each filter over the adjustments,
    // made once for the adjustments they show.
    Editor.prototype.filterThumbs = function () {
        var self = this, key = JSON.stringify(this.doc.adjust || {}), out = {};

        if (this.thumbs && (this.thumbs.key === key)) {
            return this.thumbs.urls;
        }

        if (!this.img) {
            return out;
        }

        try {
            var nw = this.img.naturalWidth, nh = this.img.naturalHeight, k = Math.min(112 / nw, 76 / nh, 1);
            var canvas = document.createElement('canvas'), g = canvas.getContext('2d'), base;

            canvas.width = Math.max(1, Math.round(nw * k));
            canvas.height = Math.max(1, Math.round(nh * k));
            g.drawImage(this.img, 0, 0, canvas.width, canvas.height);
            base = g.getImageData(0, 0, canvas.width, canvas.height);

            M.FILTER_NAMES.forEach(function (name) {
                var c = M.colorMatrix(M.colorValues({ adjust: self.doc.adjust, filter: name }));
                var data = g.createImageData(canvas.width, canvas.height);

                data.data.set(base.data);

                if (c) {
                    M.applyColor(data.data, c);
                }

                g.putImageData(data, 0, 0);
                out[name] = canvas.toDataURL('image/png');
            });
        } catch (err) {
            // A picture from another site cannot be read back: plain tiles.
            out = {};
        }

        this.thumbs = { key: key, urls: out };

        return out;
    };

    // The colour step for a server without Imagick: the picture as loaded,
    // colour applied, no larger than the output needs (at most 16 MP, a
    // phone browser's canvas). Its crop follows by the size.
    Editor.prototype.drawColour = function () {
        var img = this.img, nw = img.naturalWidth, nh = img.naturalHeight, fr = this.turns();
        var crop = M.cropBox(this.doc), out = M.outputSize(this.doc);
        var need = Math.max(out.w / crop.w, out.h / crop.h) * M.coverScale(crop, fr.rw, fr.rh, this.doc.geometry.straighten || 0);
        var k = Math.min(1, need, Math.sqrt(16e6 / (nw * nh)));

        try {
            var canvas = document.createElement('canvas'), g = canvas.getContext('2d'), data;

            canvas.width = Math.max(1, Math.round(nw * k));
            canvas.height = Math.max(1, Math.round(nh * k));
            g.drawImage(img, 0, 0, canvas.width, canvas.height);
            data = g.getImageData(0, 0, canvas.width, canvas.height);
            M.applyColor(data.data, M.colorMatrix(M.colorValues(this.doc)));
            g.putImageData(data, 0, 0);

            return canvas.toDataURL(/\.jpe?g$/i.test(this.file ? this.file.name : '') ? 'image/jpeg' : 'image/png', 0.95);
        } catch (err) {
            return null;
        }
    };

    // ---- layers ----

    Editor.prototype.layer = function (id) {
        return find(this.doc, id);
    };

    Editor.prototype.selected = function () {
        var self = this;

        return this.doc.layers.filter(function (l) { return self.sel.indexOf(l.id) >= 0; });
    };

    Editor.prototype.select = function (ids) {
        this.sel = ids;
        this.render();
    };

    // After an undo, only ids still in the document stay selected.
    Editor.prototype.keepSelection = function () {
        var self = this;

        this.sel = this.sel.filter(function (id) { return !!self.layer(id); });
    };

    // A picture by address, loaded once, for the overlay and the artboard.
    Editor.prototype.picture = function (src) {
        var self = this;

        if (!this.pictures[src]) {
            this.pictures[src] = new Promise(function (resolve, reject) {
                var img = new Image();

                img.onload = function () {
                    self.pictures[src] = img;
                    resolve(img);
                };
                img.onerror = reject;
                img.src = src;
            });
        }

        return Promise.resolve(this.pictures[src]);
    };

    Editor.prototype.renderLayers = function () {
        var self = this, svg = this.layersSvg;

        if (!this.layersDirty) {
            return;
        }

        this.layersDirty = false;

        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }

        this.doc.layers.forEach(function (l) {
            var n = l.hidden ? null : self.layerNode(l);

            if (n) {
                svg.appendChild(n);
            }
        });
    };

    // One layer as SVG in the rotated space: a picture for now; shapes,
    // text and stickers come with their tools.
    Editor.prototype.layerNode = function (l) {
        if (l.type !== 'image') {
            return null;
        }

        var n = document.createElementNS(SVGNS, 'image');

        n.setAttribute('href', l.src);
        n.setAttribute('x', l.x);
        n.setAttribute('y', l.y);
        n.setAttribute('width', l.w);
        n.setAttribute('height', l.h);
        n.setAttribute('preserveAspectRatio', 'none');

        if (l.rot) {
            n.setAttribute('transform', 'rotate(' + l.rot + ' ' + (l.x + l.w / 2) + ' ' + (l.y + l.h / 2) + ')');
        }

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            n.setAttribute('opacity', l.opacity);
        }

        return n;
    };

    // The outlines of the selected layers on the stage; one unlocked layer
    // gets eight handles and the turning handle; guides while a move snaps.
    Editor.prototype.renderSelection = function () {
        var self = this, box = this.selEl, v = this.view, vr = this.viewRect(), k = v.k, picked = this.selected();

        box.textContent = '';

        if (!this.ready || this.cropping() || (this.tool !== 'select')) {
            return;
        }

        picked.forEach(function (l) {
            var d = el('div', { class: 'pgie-selbox' + (l.locked ? ' pgie-locked' : '') });

            d.style.left = (v.x + (l.x - vr.x) * k) + 'px';
            d.style.top = (v.y + (l.y - vr.y) * k) + 'px';
            d.style.width = (l.w * k) + 'px';
            d.style.height = (l.h * k) + 'px';
            d.style.transform = l.rot ? 'rotate(' + l.rot + 'deg)' : '';

            if ((picked.length === 1) && !l.locked) {
                ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w', 'rot'].forEach(function (h) {
                    d.appendChild(el('span', { class: 'pgie-sh', 'data-h': h, title: (h === 'rot') ? t('rotate') : null }));
                });
            }

            box.appendChild(d);
        });

        (this.guides ? this.guides.gx : []).forEach(function (x) {
            box.appendChild(el('div', { class: 'pgie-guide pgie-gx', style: 'left: ' + (v.x + (x - vr.x) * k) + 'px' }));
        });

        (this.guides ? this.guides.gy : []).forEach(function (y) {
            box.appendChild(el('div', { class: 'pgie-guide pgie-gy', style: 'top: ' + (v.y + (y - vr.y) * k) + 'px' }));
        });
    };

    // A press with the Select tool: a handle of the one selected layer
    // resizes or turns it; a layer is selected (Shift adds or removes it)
    // and moved with the rest of the selection; the empty stage clears it.
    Editor.prototype.startDrag = function (e) {
        var p = this.toDoc(e.clientX, e.clientY), h = e.target.getAttribute && e.target.getAttribute('data-h');
        var one = (this.sel.length === 1) ? this.layer(this.sel[0]) : null, hit;

        if (h && one) {
            this.drag = { kind: (h === 'rot') ? 'rotate' : 'resize', h: h, start: M.clone(one) };
        } else {
            hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k);

            if (!hit) {
                if (this.sel.length && !e.shiftKey) {
                    this.select([]);
                }

                return;
            }

            if (e.shiftKey) {
                this.select((this.sel.indexOf(hit.id) >= 0) ? this.sel.filter(function (id) { return id !== hit.id; }) : this.sel.concat([hit.id]));
                return;
            }

            if (this.sel.indexOf(hit.id) < 0) {
                this.select([hit.id]);
            }

            this.drag = { kind: 'move', at: p, start: this.selected().filter(function (l) { return !l.locked; }).map(M.clone) };
        }

        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.moveDrag = function (e) {
        var d = this.drag, p = this.toDoc(e.clientX, e.clientY), k = this.view.k;

        if (d.kind === 'move') {
            var dx = p[0] - d.at[0], dy = p[1] - d.at[1], moved = M.bounds(d.start);

            this.guides = null;

            // Alt moves freely; else an edge or the middle snaps to the
            // canvas and the other layers.
            if (moved && !e.altKey) {
                var lines = this.snapLines(), s = M.snapBox({ x: moved.x + dx, y: moved.y + dy, w: moved.w, h: moved.h }, lines.x, lines.y, 6 / k);

                dx += s.dx;
                dy += s.dy;
                this.guides = (s.gx.length || s.gy.length) ? s : null;
            }

            this.change(function (doc) {
                d.start.forEach(function (o) {
                    var l = find(doc, o.id);

                    l.x = o.x + dx;
                    l.y = o.y + dy;
                });
            }, 'drag', true);
        } else if (d.kind === 'resize') {
            // A picture keeps its shape from a corner; Shift does the other.
            var b = M.resizeBox(d.start, d.h, p[0], p[1], e.shiftKey !== (d.start.type === 'image'), 4 / k);

            this.change(function (doc) {
                var l = find(doc, d.start.id);

                l.x = b.x;
                l.y = b.y;
                l.w = b.w;
                l.h = b.h;
            }, 'drag', true);
        } else {
            var a = M.angleTo(d.start, p[0], p[1], e.shiftKey);

            this.change(function (doc) { find(doc, d.start.id).rot = a; }, 'drag', true);
        }
    };

    Editor.prototype.endDrag = function () {
        if (!this.drag) {
            return;
        }

        this.drag = null;
        this.guides = null;
        this.history.key = null;
        this.render();
    };

    // What a moved layer snaps to: the canvas's edges and middle, and the
    // edges and middles of the other visible layers.
    Editor.prototype.snapLines = function () {
        var self = this, vr = this.viewRect(), xs = [vr.x, vr.x + vr.w / 2, vr.x + vr.w], ys = [vr.y, vr.y + vr.h / 2, vr.y + vr.h];

        this.doc.layers.forEach(function (l) {
            if (!l.hidden && (self.sel.indexOf(l.id) < 0)) {
                var b = M.bounds([l]);

                xs.push(b.x, b.x + b.w / 2, b.x + b.w);
                ys.push(b.y, b.y + b.h / 2, b.y + b.h);
            }
        });

        return { x: xs, y: ys };
    };

    // The keys of the selection: arrows nudge (Shift: 10), Delete removes,
    // Ctrl+D duplicates, Ctrl+C copies, Ctrl+A selects all. True when used.
    Editor.prototype.layerKey = function (e, mod, k) {
        var ids = this.sel, step = e.shiftKey ? 10 : 1, free = this.selected().filter(function (l) { return !l.locked; });

        if (this.tool !== 'select') {
            return false;
        }

        if (mod && (k === 'a' || k === 'A')) {
            this.select(this.doc.layers.map(function (l) { return l.id; }));
            return true;
        }

        if (!ids.length) {
            return false;
        }

        if (mod && (k === 'c' || k === 'C')) {
            this.clip = this.selected().map(M.clone);
            return true;
        }

        if (mod && (k === 'd' || k === 'D')) {
            this.addLayers(this.selected(), 10);
            return true;
        }

        if (!mod && (k === 'Delete' || k === 'Backspace') && free.length) {
            this.removeLayers();
            return true;
        }

        var dir = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[k];

        if (!mod && dir && free.length) {
            this.change(function (doc) {
                free.forEach(function (o) {
                    var l = find(doc, o.id);

                    l.x += dir[0] * step;
                    l.y += dir[1] * step;
                });
            }, 'nudge');
            return true;
        }

        return false;
    };

    // Copies of layers with new ids, moved by offset, on top and selected.
    Editor.prototype.addLayers = function (list, offset) {
        var added = [];

        this.change(function (doc) {
            list.forEach(function (o) {
                var l = M.clone(o);

                l.id = M.newId(doc);
                l.x += offset;
                l.y += offset;
                doc.layers.push(l);
                added.push(l.id);
            });
        });
        this.select(added);
    };

    Editor.prototype.removeLayers = function () {
        var ids = this.selected().filter(function (l) { return !l.locked; }).map(function (l) { return l.id; });

        this.change(function (doc) {
            doc.layers = doc.layers.filter(function (l) { return ids.indexOf(l.id) < 0; });
        });
        this.select(this.sel.filter(function (id) { return ids.indexOf(id) < 0; }));
    };

    // A picture pasted from the clipboard becomes a layer; else layers
    // copied with Ctrl+C come back. Not into a text field.
    Editor.prototype.onPaste = function (e) {
        var items = (e.clipboardData && e.clipboardData.items) || [], file = null, i;

        if (!this.ready || this.saving || this.confirmBox || /^(INPUT|TEXTAREA)$/.test(e.target.tagName || '')) {
            return;
        }

        for (i = 0; i < items.length; i++) {
            if ((items[i].kind === 'file') && /^image\//.test(items[i].type)) {
                file = items[i].getAsFile();
            }
        }

        if (file) {
            e.preventDefault();
            this.addPicture(file);
        } else if (this.clip && this.clip.length) {
            e.preventDefault();
            this.toSelect();
            this.addLayers(this.clip, 10);
        }
    };

    // The Select tool, keeping a crop being drawn.
    Editor.prototype.toSelect = function () {
        if (this.cropping()) {
            this.applyCrop('select');
        } else {
            this.tool = 'select';
        }
    };

    // A picture file as a layer in the middle of the canvas, at most 60 % of
    // it; one larger than 2000 px is kept at that size.
    Editor.prototype.addPicture = function (file) {
        var self = this, reader = new FileReader();

        reader.onload = function () {
            var img = new Image();

            img.onload = function () {
                var src = String(reader.result), w = img.naturalWidth, h = img.naturalHeight, k = Math.min(1, 2000 / Math.max(w, h));

                if (k < 1) {
                    var canvas = document.createElement('canvas');

                    canvas.width = Math.round(w * k);
                    canvas.height = Math.round(h * k);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    src = canvas.toDataURL('image/png');
                }

                self.picture(src).then(function () {
                    var vr = self.viewRect(), fit = Math.min(1, 0.6 * vr.w / w, 0.6 * vr.h / h), lw = w * fit, lh = h * fit;

                    self.toSelect();
                    self.addLayers([{ type: 'image', src: src, x: vr.x + (vr.w - lw) / 2, y: vr.y + (vr.h - lh) / 2, w: lw, h: lh, rot: 0, opacity: 1 }], 0);
                });
            };
            img.src = String(reader.result);
        };
        reader.readAsDataURL(file);
    };

    Editor.prototype.layerName = function (l) {
        var same = this.doc.layers.filter(function (o) { return o.type === l.type; });

        return t('layer_' + l.type) + ((same.length > 1) ? ' ' + (same.indexOf(l) + 1) : '');
    };

    // The Select tool's panel: the selection's arrange, align, opacity,
    // duplicate and delete; then every layer, the top one first.
    Editor.prototype.layersPanel = function (body) {
        var self = this, picked = this.selected(), free = picked.filter(function (l) { return !l.locked; });
        var row = function (items) { return el('div', { class: 'pgie-btnrow' }, items); };
        var act = function (name, label, fn, off) {
            var b = button('pgie-ibtn', label, name, fn);

            b.disabled = !!off;

            return b;
        };

        if (!this.doc.layers.length) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('noLayers') }));
            return;
        }

        if (picked.length) {
            var arrange = function (how) {
                self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, how); });
            };
            var align = function (how) {
                var target = (picked.length > 1) ? M.bounds(free) : self.viewRect(), moves = M.align(free.map(function (l) { return M.bounds([l]); }), how, target);

                self.change(function (doc) {
                    free.forEach(function (o, i) {
                        var l = find(doc, o.id);

                        l.x += moves[i][0];
                        l.y += moves[i][1];
                    });
                });
            };
            var spread = function (axis) {
                var moves = M.distribute(free.map(function (l) { return M.bounds([l]); }), axis);

                self.change(function (doc) {
                    free.forEach(function (o, i) {
                        var l = find(doc, o.id);

                        l.x += moves[i][0];
                        l.y += moves[i][1];
                    });
                });
            };
            var opacity = Math.round(100 * ((picked[0].opacity === undefined) ? 1 : picked[0].opacity));
            var value = el('span', { class: 'pgie-val', text: opacity + ' %' });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: '0', max: '100', step: '1', value: String(opacity), 'aria-label': t('opacity') });

            slider.addEventListener('input', function () {
                var n = Number(slider.value);

                value.textContent = n + ' %';
                self.change(function (doc) {
                    picked.forEach(function (o) { find(doc, o.id).opacity = n / 100; });
                }, 'opacity', true);
            });
            slider.addEventListener('change', function () { self.history.key = null; });

            body.appendChild(section(t('arrange'), row([
                act('front', t('bringFront'), function () { arrange('top'); }),
                act('forward', t('bringForward'), function () { arrange('up'); }),
                act('backward', t('sendBackward'), function () { arrange('down'); }),
                act('back', t('sendBack'), function () { arrange('bottom'); })
            ])));
            body.appendChild(section(t('align'), row([
                act('alignleft', t('alignLeft'), function () { align('left'); }, !free.length),
                act('aligncenter', t('alignCenter'), function () { align('center'); }, !free.length),
                act('alignright', t('alignRight'), function () { align('right'); }, !free.length),
                act('aligntop', t('alignTop'), function () { align('top'); }, !free.length),
                act('alignmiddle', t('alignMiddle'), function () { align('middle'); }, !free.length),
                act('alignbottom', t('alignBottom'), function () { align('bottom'); }, !free.length),
                act('distx', t('distributeX'), function () { spread('x'); }, free.length < 3),
                act('disty', t('distributeY'), function () { spread('y'); }, free.length < 3)
            ])));
            body.appendChild(section(t('opacity'), slider, value));
            body.appendChild(row([
                act('duplicate', t('duplicate'), function () { self.addLayers(self.selected(), 10); }),
                act('trash', t('delete'), function () { self.removeLayers(); }, !free.length)
            ]));
        }

        var list = el('ul', { class: 'pgie-layerlist', 'aria-label': t('layers') });

        this.doc.layers.slice().reverse().forEach(function (l) {
            var on = (self.sel.indexOf(l.id) >= 0);
            var flag = function (key, label, iconName) {
                return button('pgie-ibtn sm', label, iconName, function () {
                    self.change(function (doc) {
                        var x = find(doc, l.id);

                        x[key] = !x[key];
                    });
                });
            };

            list.appendChild(el('li', { class: 'pgie-lrow' + (on ? ' on' : '') + (l.hidden ? ' off' : '') }, [
                el('button', { type: 'button', class: 'pgie-lname', 'aria-pressed': on ? 'true' : 'false', text: self.layerName(l), onclick: function (e) {
                    self.select(e.shiftKey ? ((on ? self.sel.filter(function (id) { return id !== l.id; }) : self.sel.concat([l.id]))) : [l.id]);
                } }),
                flag('hidden', l.hidden ? t('show') : t('hide'), l.hidden ? 'eyeoff' : 'eye'),
                flag('locked', l.locked ? t('unlock') : t('lock'), l.locked ? 'lock' : 'unlock')
            ]));
        });

        body.appendChild(list);
    };

    // ---- crop ----

    Editor.prototype.renderCrop = function () {
        var on = this.cropping();

        this.root.classList.toggle('pgie-cropping', on);

        if (!on) {
            return;
        }

        var b = M.cropBox(this.doc), v = this.view, k = v.k, st = this.cropEl.style;

        st.left = (v.x + b.x * k) + 'px';
        st.top = (v.y + b.y * k) + 'px';
        st.width = (b.w * k) + 'px';
        st.height = (b.h * k) + 'px';
        st.borderRadius = shapeRadius(this.doc.geometry.cropShape, b, k);
    };

    // The crop ratio as width / height; 0 is free.
    Editor.prototype.ratioValue = function () {
        if (this.cropRatio === -1) {
            var fr = this.turns();

            return fr.rw / fr.rh;
        }

        return this.cropRatio || 0;
    };

    // A set output size keeps the crop box's ratio: its height follows.
    function keepOutput(d) {
        var o = d.geometry.output, c = d.geometry.crop;

        if (o && c) {
            o.h = Math.max(1, Math.round(o.w * c.h / c.w));
        }
    }

    Editor.prototype.startCropDrag = function (e) {
        if (e.button > 0) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        this.dragCount = (this.dragCount || 0) + 1;
        this.cropDrag = { id: e.pointerId, handle: e.target.getAttribute('data-h') || '', box: M.cropBox(this.doc), at: this.toDoc(e.clientX, e.clientY), key: 'crop' + this.dragCount };

        try {
            this.cropEl.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.moveCropDrag = function (e) {
        var d = this.cropDrag;

        if (!d || (e.pointerId !== d.id)) {
            return;
        }

        var fr = this.turns(), p = this.toDoc(e.clientX, e.clientY), min = Math.max(16, 24 / this.view.k);
        var box = d.handle ? M.resizeCrop(d.box, d.handle, p[0], p[1], this.ratioValue(), fr.rw, fr.rh, min) : M.moveCrop(d.box, p[0] - d.at[0], p[1] - d.at[1], fr.rw, fr.rh);

        this.change(function (doc) { doc.geometry.crop = box; keepOutput(doc); }, d.key, true);
    };

    Editor.prototype.endCropDrag = function (e) {
        if (this.cropDrag && (e.pointerId === this.cropDrag.id)) {
            this.cropDrag = null;
            this.renderMeta();
            this.renderPanel();
        }
    };

    // Entering the crop tool remembers the document, so Cancel can bring it
    // back and drop the steps taken since.
    Editor.prototype.enterCrop = function () {
        this.cropEntry = { doc: M.clone(this.doc), depth: this.history.past.length };
        this.cropRatio = 0;
    };

    // Leaving the crop tool keeps what was done; a box as big as the picture
    // is no crop.
    Editor.prototype.applyCrop = function (next) {
        var g = this.doc.geometry, fr = this.turns(), c = g.crop;

        if (c && (c.x < 0.5) && (c.y < 0.5) && (Math.abs(c.w - fr.rw) < 1) && (Math.abs(c.h - fr.rh) < 1)) {
            g.crop = null;
        }

        this.settleDirty();
        this.cropEntry = null;
        this.tool = next || 'select';
        this.sheetOpen = (this.tool !== 'select');
        this.renderMeta();
        this.fit();
        this.render();
    };

    Editor.prototype.cancelCrop = function () {
        var entry = this.cropEntry;

        if (!entry) {
            return;
        }

        this.doc = entry.doc;

        if (this.history.past.length > entry.depth) {
            this.history.past.length = entry.depth;
        }

        this.history.future = [];
        this.history.key = null;
        this.settleDirty();
        this.cropEntry = null;
        this.tool = 'select';
        this.sheetOpen = false;
        this.renderMeta();
        this.fit();
        this.render();
    };

    Editor.prototype.setRatio = function (r) {
        this.cropRatio = r;

        var ratio = this.ratioValue(), fr = this.turns();

        if (!ratio) {
            this.render();
            return;
        }

        var box = M.fitRatio(M.cropBox(this.doc), ratio, fr.rw, fr.rh);

        this.change(function (d) { d.geometry.crop = box; keepOutput(d); });
    };

    // A circle starts square.
    Editor.prototype.setShape = function (shape) {
        var box = null;

        if ((shape === 'circle') && (this.cropRatio !== 1)) {
            var fr = this.turns();

            this.cropRatio = 1;
            box = M.fitRatio(M.cropBox(this.doc), 1, fr.rw, fr.rh);
        }

        this.change(function (d) {
            d.geometry.cropShape = shape;

            if (box) {
                d.geometry.crop = box;
                keepOutput(d);
            }
        });
    };

    Editor.prototype.setOutput = function (axis, value) {
        var c = M.cropBox(this.doc);

        if (!isFinite(value) || (value < 1)) {
            this.render();
            return;
        }

        value = Math.round(M.clamp(value, 1, 8000));

        var o = (axis === 'w') ? { w: value, h: Math.max(1, Math.round(value * c.h / c.w)) } : { w: Math.max(1, Math.round(value * c.w / c.h)), h: value };

        this.change(function (d) { d.geometry.output = o; });
    };

    Editor.prototype.resetGeometry = function () {
        var next = M.reorient(this.doc, { rot: 0, flipX: false, flipY: false });

        next.geometry.straighten = 0;
        next.geometry.crop = null;
        next.geometry.output = null;
        next.geometry.cropShape = 'rect';
        this.cropRatio = 0;
        this.replaceDoc(next);
    };

    Editor.prototype.cropPanel = function (body) {
        var self = this, g = this.doc.geometry, out = M.outputSize(this.doc);
        var ratios = el('div', { class: 'pgie-chips', role: 'group', 'aria-label': t('cropRatio') });
        var shapes = el('div', { class: 'pgie-chips', role: 'group', 'aria-label': t('cropShape') });

        M.RATIOS.forEach(function (r) {
            var on = ((self.cropRatio || 0) === r[1]);
            var label = (r[0] === 'free') ? t('cropFree') : (r[0] === 'original') ? t('cropOriginal') : r[0];

            ratios.appendChild(el('button', { type: 'button', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', text: label, onclick: function () { self.setRatio(r[1]); } }));
        });

        [['rect', t('shapeRect')], ['rounded', t('shapeRounded')], ['circle', t('shapeCircle')]].forEach(function (sh) {
            var on = ((g.cropShape || 'rect') === sh[0]);

            shapes.appendChild(el('button', { type: 'button', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', text: sh[1], onclick: function () { self.setShape(sh[0]); } }));
        });

        var deg = function (v) { return ((v > 0) ? '+' : '') + decimal(v || 0) + '°'; };
        var value = el('span', { class: 'pgie-val', text: deg(g.straighten) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: '-45', max: '45', step: '0.5', value: String(g.straighten || 0), 'aria-label': t('straighten') });

        slider.addEventListener('input', function () {
            var v = Number(slider.value);

            value.textContent = deg(v);
            self.change(function (d) { d.geometry.straighten = v; }, 'straighten', true);
        });

        var w = el('input', { type: 'number', class: 'pgie-input', min: '1', max: '8000', value: String(out.w), 'aria-label': t('width') });
        var h = el('input', { type: 'number', class: 'pgie-input', min: '1', max: '8000', value: String(out.h), 'aria-label': t('height') });

        w.addEventListener('change', function () { self.setOutput('w', Number(w.value)); });
        h.addEventListener('change', function () { self.setOutput('h', Number(h.value)); });

        body.appendChild(section(t('cropRatio'), ratios));
        body.appendChild(section(t('rotateFlip'), el('div', { class: 'pgie-tiles' }, [
            tile('rotl', t('rotateLeft'), function () { self.replaceDoc(M.turnDocument(self.doc, -1)); }),
            tile('rotr', t('rotateRight'), function () { self.replaceDoc(M.turnDocument(self.doc, 1)); }),
            tile('fliph', t('flipH'), function () { self.replaceDoc(M.flipDocument(self.doc, 'x')); }),
            tile('flipv', t('flipV'), function () { self.replaceDoc(M.flipDocument(self.doc, 'y')); })
        ])));
        body.appendChild(section(t('straighten'), slider, value));
        body.appendChild(section(t('cropShape'), shapes));
        body.appendChild(section(t('outputSize'), el('div', { class: 'pgie-wh' }, [w, el('span', { text: '×' }), h, el('span', { text: 'px' })])));
        body.appendChild(el('div', { class: 'pgie-actions' }, [
            el('button', { type: 'button', class: 'pgie-btn', text: t('cancel'), onclick: function () { self.cancelCrop(); } }),
            el('button', { type: 'button', class: 'pgie-btn pgie-primary', text: t('apply'), onclick: function () { self.applyCrop(); } })
        ]));
    };

    Editor.prototype.onWheel = function (e) {
        if (!this.ready) {
            return;
        }

        e.preventDefault();

        var r = this.stage.getBoundingClientRect();

        if (e.ctrlKey || e.metaKey) {
            this.zoomBy(Math.exp(-e.deltaY * 0.0025), e.clientX - r.left, e.clientY - r.top);
            return;
        }

        this.fitMode = false;
        this.view = { k: this.view.k, x: this.view.x - (e.shiftKey ? e.deltaY : e.deltaX), y: this.view.y - (e.shiftKey ? 0 : e.deltaY) };
        this.renderView();
    };

    // Moving the view: the middle button, Space and drag, or two fingers
    // (which also pinch to zoom). One finger and the left button belong to
    // the tools.
    Editor.prototype.onDown = function (e) {
        this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };

        var ids = Object.keys(this.pointers);

        if ((e.button === 1) || this.space || (ids.length === 2)) {
            // A second finger ends a layer drag where it is.
            this.endDrag();
            e.preventDefault();
            this.startPan();

            try {
                this.stage.setPointerCapture(e.pointerId);
            } catch (err) {
                // The pointer is already gone.
            }
        } else if ((e.button === 0) && (ids.length === 1) && this.ready && (this.tool === 'select')) {
            this.startDrag(e);
        }
    };

    Editor.prototype.startPan = function () {
        var ids = Object.keys(this.pointers);

        this.pan = { view: this.view, at: this.centroid(ids), dist: this.spread(ids), count: ids.length };
        this.fitMode = false;
        this.stage.classList.add('pgie-panning');
    };

    Editor.prototype.centroid = function (ids) {
        var x = 0, y = 0, self = this;

        ids.forEach(function (id) { x += self.pointers[id].x; y += self.pointers[id].y; });

        return { x: x / ids.length, y: y / ids.length };
    };

    Editor.prototype.spread = function (ids) {
        if (ids.length < 2) {
            return 0;
        }

        var a = this.pointers[ids[0]], b = this.pointers[ids[1]];

        return Math.hypot(a.x - b.x, a.y - b.y);
    };

    Editor.prototype.onMove = function (e) {
        if (!this.pointers[e.pointerId]) {
            return;
        }

        this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };

        if (this.drag) {
            this.moveDrag(e);
            return;
        }

        if (!this.pan) {
            return;
        }

        var ids = Object.keys(this.pointers);

        if (ids.length !== this.pan.count) {
            this.startPan();
            return;
        }

        var c = this.centroid(ids), r = this.stage.getBoundingClientRect();
        var v = { k: this.pan.view.k, x: this.pan.view.x + c.x - this.pan.at.x, y: this.pan.view.y + c.y - this.pan.at.y };

        if (this.pan.dist > 0) {
            v = M.zoomAt(v, this.spread(ids) / this.pan.dist, c.x - r.left, c.y - r.top);
        }

        this.view = v;
        this.renderView();
    };

    Editor.prototype.onUp = function (e) {
        delete this.pointers[e.pointerId];

        if (this.drag) {
            this.endDrag();
        }

        if (!this.pan) {
            this.onTap(e);
            return;
        }

        if (Object.keys(this.pointers).length) {
            this.startPan();
            return;
        }

        this.pan = null;

        if (!this.space) {
            this.stage.classList.remove('pgie-panning');
        }
    };

    // A double click, or two taps of a finger close together, on the stage:
    // the actual size at that point from the fitted view, else the fitted
    // view again. Not on the mini bar's buttons.
    Editor.prototype.onDouble = function (e) {
        if (!this.ready || (e.target && e.target.closest && e.target.closest('.pgie-mini'))) {
            return;
        }

        if (this.fitMode) {
            var r = this.stage.getBoundingClientRect();

            this.zoomBy(1 / this.view.k, e.clientX - r.left, e.clientY - r.top);
        } else {
            this.fit();
        }
    };

    Editor.prototype.onTap = function (e) {
        if (e.pointerType !== 'touch') {
            return;
        }

        var now = Date.now(), last = this.tap;

        this.lastTouch = now;
        this.tap = { t: now, x: e.clientX, y: e.clientY };

        if (last && (now - last.t < 320) && (Math.hypot(e.clientX - last.x, e.clientY - last.y) < 30)) {
            this.tap = null;
            this.onDouble(e);
        }
    };

    // ---- tools ----

    Editor.prototype.renderTools = function () {
        var self = this, host = this.tools.mini ? this.mini : this.rail;

        host.textContent = '';
        this.toolButtons = {};

        this.tools.list.forEach(function (id) {
            var b = button(self.tools.mini ? 'pgie-mtool' : 'pgie-tool', t('tool_' + id), id, function () { self.pickTool(id); }, true);

            self.toolButtons[id] = b;
            host.appendChild(b);
        });

        if (this.tools.mini) {
            host.appendChild(el('span', { class: 'pgie-sepv' }));
            host.appendChild(this.undoBtn);
            host.appendChild(this.redoBtn);
        } else {
            host.appendChild(el('div', { class: 'pgie-grow' }));
        }
    };

    Editor.prototype.pickTool = function (id) {
        if (this.tools.list.indexOf(id) < 0) {
            return;
        }

        // Another tool after Crop keeps the crop.
        if (this.cropping() && (id !== 'crop')) {
            this.applyCrop(id);
            return;
        }

        var entering = (id === 'crop') && (this.tool !== 'crop') && !!this.doc.source;

        // A tool opens the sheet; pressing the active tool again closes or
        // opens it; Select opens it only on that second press.
        this.sheetOpen = (id === this.tool) ? !this.sheetOpen : (id !== 'select');
        this.tool = id;

        if (entering) {
            this.enterCrop();
            this.fit();
        }

        this.render();
    };

    Editor.prototype.renderPanel = function () {
        if (!this.panel) {
            return;
        }

        var title = (this.tool === 'select') ? t('layers') : t('tool_' + this.tool);
        var body = el('div', { class: 'pgie-pb' });

        if (this.tool === 'select') {
            this.layersPanel(body);
        } else if (this.cropping()) {
            this.cropPanel(body);
        } else if ((this.tool === 'adjust') && this.doc.source) {
            this.adjustPanel(body);
        }

        this.panel.textContent = '';
        this.panel.setAttribute('aria-label', title);
        var self = this;

        this.panel.appendChild(el('div', { class: 'pgie-ph' }, [
            el('h2', { text: title }),
            el('div', { class: 'pgie-grow' }),
            this.cropping() ? el('button', { type: 'button', class: 'pgie-link', text: t('reset'), onclick: function () { self.resetGeometry(); } }) : null,
            ((this.tool === 'adjust') && this.hasColor()) ? el('button', { type: 'button', class: 'pgie-link', text: t('reset'), onclick: function () {
                self.change(function (doc) {
                    doc.adjust = {};
                    doc.filter = 'none';
                });
            } }) : null,
            button('pgie-ibtn sm pgie-sheet-close', t('closePanel'), 'close', function () {
                self.sheetOpen = false;
                self.render();
            })
        ]));
        this.panel.appendChild(body);
    };

    Editor.prototype.render = function () {
        var self = this;

        Object.keys(this.toolButtons || {}).forEach(function (id) {
            var on = (id === self.tool);

            self.toolButtons[id].classList.toggle('on', on);
            self.toolButtons[id].setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        this.root.classList.toggle('pgie-sheet-closed', !this.sheetOpen);

        // An opened or closed panel sheet changes the room a fitted picture has.
        if (this.fitMode && this.ready && (this.panelCover() !== (this.cover || 0))) {
            this.fit();
        }
        this.hint.textContent = (this.tool === 'adjust') ? t('hintAdjust') : ((this.tool === 'select') ? t('hintSelect') : t('hintView'));
        this.undoBtn.disabled = !this.history.canUndo();
        this.redoBtn.disabled = !this.history.canRedo();
        this.renderPanel();
        this.renderView();

        // A button the redraw took away leaves the focus on the page body,
        // where the editor's keys no longer arrive: the stage takes it back.
        if (!this.dialog.contains(document.activeElement)) {
            this.stage.focus({ preventScroll: true });
        }
    };

    // ---- document changes ----

    // Every change goes through here: the history keeps the document as it
    // was, mutate() changes it. Calls with the same key in a row (a slider
    // drag) are one undo step.
    // viewOnly redraws the stage and the undo buttons but not the panel, so
    // a slider being dragged keeps its place.
    Editor.prototype.change = function (mutate, key, viewOnly) {
        this.history.push(M.clone(this.doc), key);
        mutate(this.doc);
        this.dirty = true;
        this.layersDirty = true;
        // A ratio, a shape or a box drag can change the saved size.
        this.renderMeta();

        if (viewOnly) {
            this.undoBtn.disabled = !this.history.canUndo();
            this.redoBtn.disabled = !this.history.canRedo();
            this.renderView();
            return;
        }

        this.render();
    };

    // A whole new document from the model (a turn, a flip, a reset).
    Editor.prototype.replaceDoc = function (next, key) {
        this.history.push(M.clone(this.doc), key);
        this.doc = next;
        this.settleDirty();
        this.layersDirty = true;
        this.renderMeta();
        this.fit();
        this.render();
    };

    // Changed is different from the opening document: Cancel, undo or four
    // quarter turns that bring it back leave nothing to save.
    Editor.prototype.settleDirty = function () {
        this.dirty = !M.sameDocument(this.doc, this.opening);
    };

    Editor.prototype.restore = function (snapshot) {
        if (!snapshot) {
            return;
        }

        this.doc = snapshot;
        this.settleDirty();
        this.layersDirty = true;
        this.keepSelection();
        this.setReady();
        this.render();
    };

    Editor.prototype.undo = function () {
        this.restore(this.history.undo(M.clone(this.doc)));
    };

    Editor.prototype.redo = function () {
        this.restore(this.history.redo(M.clone(this.doc)));
    };

    // ---- keys ----

    Editor.prototype.onKey = function (e) {
        var mod = e.ctrlKey || e.metaKey, k = e.key, tg = e.target;
        // A slider, a checkbox or a button keeps the editor's keys.
        var typing = (/^(TEXTAREA|SELECT)$/.test((e.target && e.target.tagName) || '') || (e.target && e.target.isContentEditable))
            || ((e.target && e.target.tagName) === 'INPUT' && !/^(range|checkbox|radio|button)$/.test(e.target.type));

        // A focused button, link or field that uses Enter or Space itself
        // gets pressed instead of the editor's shortcut.
        var owns = M.ownsKey(tg && tg.tagName ? {
            tag: tg.tagName,
            type: tg.type,
            role: tg.getAttribute ? tg.getAttribute('role') : null,
            editable: tg.isContentEditable
        } : null, k);

        if (e.isComposing) {
            return;
        }

        if (this.saving) {
            e.preventDefault();
            return;
        }

        // Escape on the key itself, in every state: the dialog's cancel
        // event can no longer be held after one press without a click.
        if (k === 'Escape') {
            e.preventDefault();

            if (this.saveMenu) {
                this.toggleSaveMenu(false);
                this.menuBtn.focus();
            } else {
                this.onEscape();
            }

            return;
        }

        if (mod && (k === 's' || k === 'S')) {
            e.preventDefault();
            this.save();
            return;
        }

        if (this.confirmBox || this.saveMenu || typing) {
            return;
        }

        if (this.layerKey(e, mod, k)) {
            e.preventDefault();
            return;
        }

        if (this.cropping() && (k === 'Enter') && !owns) {
            e.preventDefault();
            this.applyCrop();
            return;
        }

        if (mod && (k === 'z' || k === 'Z')) {
            e.preventDefault();

            if (e.shiftKey) {
                this.redo();
            } else {
                this.undo();
            }

            return;
        }

        if (mod && (k === 'y' || k === 'Y')) {
            e.preventDefault();
            this.redo();
            return;
        }

        if (mod) {
            return;
        }

        if (k === ' ' && owns) {
            return;
        } else if (k === ' ' && !this.space) {
            e.preventDefault();
            this.space = true;
            this.stage.classList.add('pgie-panning');
        } else if (k === '+' || k === '=') {
            this.zoomBy(ZOOM_STEP);
        } else if (k === '-' || k === '_') {
            this.zoomBy(1 / ZOOM_STEP);
        } else if (k === '0') {
            this.fit();
        } else if (k === '1') {
            this.zoomTo(1);
        }
    };

    Editor.prototype.onEscape = function () {
        if (this.saving) {
            return;
        }

        if (this.confirmBox) {
            this.closeConfirm();
        } else if (this.cropping()) {
            this.cancelCrop();
        } else if (this.sel.length) {
            this.select([]);
        } else if (this.full) {
            this.setFull(false);
        } else {
            this.requestClose();
        }
    };

    // ---- full screen ----

    // The class fills the window; the Fullscreen API, where the browser
    // allows it, then fills the screen. Leaving the browser's full screen
    // (its own Escape) drops back to the window too.
    Editor.prototype.setFull = function (on, fromBrowser) {
        this.full = !!on;
        this.dialog.classList.toggle('pgie-dialog--full', this.full);
        this.fullBtn.textContent = '';
        this.fullBtn.appendChild(icon(this.full ? 'shrink' : 'expand'));
        this.fullBtn.title = this.full ? t('exitFullscreen') : t('fullscreen');
        this.fullBtn.setAttribute('aria-label', this.fullBtn.title);
        this.fullBtn.setAttribute('aria-pressed', this.full ? 'true' : 'false');

        if (fromBrowser) {
            return;
        }

        try {
            if (this.full && this.dialog.requestFullscreen && !document.fullscreenElement) {
                this.dialog.requestFullscreen().catch(function () { /* the window class stays */ });
            } else if (!this.full && document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(function () { /* already left */ });
            }
        } catch (err) {
            // No Fullscreen API: the window class is enough.
        }
    };

    // ---- save and close ----

    // The Save button, with a menu (Save as a copy) beside it when Save
    // writes over a file.
    Editor.prototype.renderSave = function () {
        var self = this, mini = this.tools.mini;

        this.saveBox.textContent = '';
        this.saveMenu = null;
        this.saveBtn = button('pgie-btn pgie-primary', mini ? t('done') : t('save'), null, function () { self.save(); }, true);
        this.saveBox.appendChild(this.saveBtn);
        this.menuBtn = null;

        if ((this.saveMode === 'replace') && !mini) {
            this.menuBtn = button('pgie-btn pgie-primary pgie-caret', t('moreSave'), 'chev', function () { self.toggleSaveMenu(); });
            this.menuBtn.setAttribute('aria-haspopup', 'menu');
            this.menuBtn.setAttribute('aria-expanded', 'false');
            this.saveBox.appendChild(this.menuBtn);
        }

        this.saveBox.classList.toggle('pgie-split', !!this.menuBtn);
    };

    Editor.prototype.toggleSaveMenu = function (open) {
        var self = this;

        open = (open === undefined) ? !this.saveMenu : open;

        if (this.saveMenu) {
            this.saveBox.removeChild(this.saveMenu);
            this.saveMenu = null;
        }

        if (!this.menuBtn) {
            return;
        }

        this.menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (!open) {
            return;
        }

        var copy = el('button', { type: 'button', role: 'menuitem', text: t('saveCopy'), onclick: function () {
            self.toggleSaveMenu(false);
            self.save('copy');
        } });

        this.saveMenu = el('div', { class: 'pgie-menu', role: 'menu', 'aria-label': t('moreSave') }, [copy]);
        this.saveBox.appendChild(this.saveMenu);
        copy.focus();
    };

    Editor.prototype.request = function (body) {
        var cfg = config();

        body.token = cfg.token || '';

        return fetch(cfg.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body)
        }).then(function (res) {
            // A refusal is JSON too; anything else reads as no answer.
            return res.json().catch(function () { return null; });
        });
    };

    // An artboard as the PNG a new file starts from: its fill and layers.
    Editor.prototype.drawArtboard = function () {
        var a = this.doc.artboard, canvas = document.createElement('canvas'), g;

        canvas.width = a.width;
        canvas.height = a.height;
        g = canvas.getContext('2d');

        if (a.background && (a.background !== 'transparent')) {
            g.fillStyle = a.background;
            g.fillRect(0, 0, a.width, a.height);
        }

        this.paintLayers(g, { x: 0, y: 0, w: a.width, h: a.height }, a.width, a.height);

        return canvas.toDataURL('image/png');
    };

    // The layers over a picture, drawn at its output size into a clear PNG
    // the server lays over the pixels it makes; null without layers.
    Editor.prototype.drawOverlay = function () {
        if (!this.doc.layers.some(function (l) { return !l.hidden; })) {
            return null;
        }

        var out = M.outputSize(this.doc), canvas = document.createElement('canvas');

        canvas.width = out.w;
        canvas.height = out.h;
        this.paintLayers(canvas.getContext('2d'), M.cropBox(this.doc), out.w, out.h);

        return canvas.toDataURL('image/png');
    };

    // Each visible layer onto g, the box of the rotated space drawn at w x h.
    Editor.prototype.paintLayers = function (g, box, w, h) {
        var self = this, sx = w / box.w, sy = h / box.h;

        this.doc.layers.forEach(function (l) {
            var img = self.pictures[l.src];

            if (l.hidden || (l.type !== 'image') || !img || !img.complete) {
                return;
            }

            g.setTransform(sx, 0, 0, sy, -box.x * sx, -box.y * sy);
            g.translate(l.x + l.w / 2, l.y + l.h / 2);
            g.rotate((l.rot || 0) * Math.PI / 180);
            g.globalAlpha = (l.opacity === undefined) ? 1 : l.opacity;
            g.drawImage(img, -l.w / 2, -l.h / 2, l.w, l.h);
        });

        g.setTransform(1, 0, 0, 1, 0, 0);
        g.globalAlpha = 1;
    };

    // Every layer picture loaded, for drawing.
    Editor.prototype.picturesReady = function () {
        var self = this;

        return Promise.all(this.doc.layers.filter(function (l) { return l.type === 'image'; }).map(function (l) {
            return self.picture(l.src);
        }));
    };

    Editor.prototype.setSaving = function (on) {
        this.saving = on;
        this.root.classList.toggle('pgie-busy', on);
        this.root.setAttribute('aria-busy', on ? 'true' : 'false');
        this.saveBtn.disabled = on;
        this.saveBtn.lastChild.textContent = on ? t('saving') : (this.tools.mini ? t('done') : t('save'));

        if (this.menuBtn) {
            this.menuBtn.disabled = on;
        }

        // A button disabled while it had the focus drops it to the page,
        // where the editor's keys no longer arrive.
        if (!on && !this.dialog.contains(document.activeElement)) {
            this.stage.focus({ preventScroll: true });
        }
    };

    // A line over the stage: what the server said, gone after a while.
    Editor.prototype.showMessage = function (message, isError) {
        var self = this;

        this.hideMessage();
        this.toastEl = el('div', { class: 'pgie-toast' + (isError ? ' pgie-error' : ''), role: isError ? 'alert' : 'status', text: message });
        this.stage.appendChild(this.toastEl);
        this.toastTimer = setTimeout(function () { self.hideMessage(); }, isError ? 8000 : 5000);
    };

    Editor.prototype.hideMessage = function () {
        clearTimeout(this.toastTimer);

        if (this.toastEl && this.toastEl.parentNode) {
            this.toastEl.parentNode.removeChild(this.toastEl);
        }

        this.toastEl = null;
    };

    // mode: the editor's own (saveMode) or 'copy' from the menu. The server
    // makes the pixels from the original and answers with the saved file.
    Editor.prototype.save = function (mode) {
        var self = this, body;

        if (!this.ready || this.saving) {
            return;
        }

        // A crop being drawn is kept, as leaving the tool keeps it.
        if (this.cropping()) {
            this.applyCrop();
        }

        mode = mode || this.saveMode;

        if (mode === 'callback') {
            var result = { doc: M.clone(this.doc), fileId: this.doc.source ? this.doc.source.fileId : 0 };

            if (typeof this.opts.onSave === 'function') {
                this.opts.onSave(result);
            }

            this.finish(result);
            return;
        }

        // Nothing changed is nothing to write over.
        if ((mode === 'replace') && !this.dirty) {
            this.finish(null);
            return;
        }

        this.hideMessage();
        this.setSaving(true);
        this.picturesReady().then(function () {
            self.send(mode);
        }, function () {
            self.setSaving(false);
            self.showMessage(t('saveError'), true);
        });
    };

    Editor.prototype.send = function (mode) {
        var self = this, body = { action: 'save', mode: mode, doc: this.doc };

        // Without Imagick the server takes the colour step from here.
        if ((mode !== 'new') && !config().serverColor && M.colorMatrix(M.colorValues(this.doc))) {
            body.colour = this.drawColour();

            if (!body.colour) {
                this.setSaving(false);
                this.showMessage(t('saveError'), true);
                return;
            }
        }

        if (mode === 'new') {
            body.folder_id = (this.opts.save && this.opts.save.folderId) || 0;
            body.name = (this.opts.save && this.opts.save.name) || this.opts.fileName || '';
            body.picture = this.drawArtboard();
        } else {
            body.file_id = this.opts.fileId;
            body.overlay = this.drawOverlay();
        }

        this.request(body).then(function (r) {
            if (!r || (r.status !== 'success')) {
                self.setSaving(false);
                self.showMessage((r && r.message) || t('saveError'), true);
                return;
            }

            var result = { file: r, doc: M.clone(self.doc), fileId: r.id };

            if (typeof self.opts.onSave === 'function') {
                self.opts.onSave(result);
            }

            self.finish(result);
        }, function () {
            self.setSaving(false);
            self.showMessage(t('saveError'), true);
        });
    };

    Editor.prototype.requestClose = function () {
        if (!this.dirty) {
            this.finish(null);
            return;
        }

        var self = this;
        var keep = el('button', { type: 'button', class: 'pgie-btn', text: t('keepEditing'), onclick: function () { self.closeConfirm(); } });
        var drop = el('button', { type: 'button', class: 'pgie-btn pgie-danger', text: t('discard'), onclick: function () { self.finish(null); } });

        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-confirm-title', 'aria-describedby': 'pgie-confirm-text' }, [
            el('div', { class: 'pgie-confirm-box' }, [
                el('h2', { id: 'pgie-confirm-title', text: t('unsavedTitle') }),
                el('p', { id: 'pgie-confirm-text', text: t('unsavedText') }),
                el('div', { class: 'pgie-confirm-actions' }, [keep, drop])
            ])
        ]);
        this.root.appendChild(this.confirmBox);
        keep.focus();
    };

    Editor.prototype.closeConfirm = function () {
        if (this.confirmBox) {
            this.confirmBox.parentNode.removeChild(this.confirmBox);
            this.confirmBox = null;
        }
    };

    // The dialog closed without finish(): the browser let a close request
    // through. Show it again with its state, the question too if open;
    // where it cannot be shown again, end the editing as unsaved so the
    // promise settles and the editor opens again.
    Editor.prototype.onDialogClosed = function () {
        if (this.finished) {
            return;
        }

        try {
            this.dialog.showModal();
        } catch (err) {
            this.finish(null);
            return;
        }

        var keep = this.confirmBox && this.confirmBox.querySelector('button');

        (keep || this.stage).focus();
    };

    Editor.prototype.finish = function (result) {
        if (this.finished) {
            return;
        }

        this.finished = true;
        this.hideMessage();

        if (this.resizer) {
            this.resizer.disconnect();
        }

        if (this.onWindowResize) {
            window.removeEventListener('resize', this.onWindowResize);
        }

        document.removeEventListener('fullscreenchange', this.onFullscreenChange);

        if (document.fullscreenElement === this.dialog && document.exitFullscreen) {
            document.exitFullscreen().catch(function () { /* already left */ });
        }

        this.dialog.close();

        if (this.dialog.parentNode) {
            this.dialog.parentNode.removeChild(this.dialog);
        }

        if (this.returnFocus && this.returnFocus.focus) {
            try {
                this.returnFocus.focus();
            } catch (err) {
                // The element left the page.
            }
        }

        current = null;

        if (!result && (typeof this.opts.onClose === 'function')) {
            this.opts.onClose();
        }

        this.resolve(result);
    };

    window.PgImageEditor = {
        version: 1,

        open: function (options) {
            if (current) {
                return current.promise;
            }

            var holder = {};

            holder.promise = new Promise(function (resolve) {
                holder.editor = new Editor(options || {}, resolve);
            });
            current = holder;

            return holder.promise;
        },

        // The open editor, for the panel's own scripts and the browser tests.
        current: function () {
            return current ? current.editor : null;
        }
    };
}());
