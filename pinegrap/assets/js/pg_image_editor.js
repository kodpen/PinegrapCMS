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
 *       save: { mode, folderId, name, url, label },  how Save writes (below)
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
 * result.file is the saved file. A file named by fileName alone is looked up
 * by its name. save.url ('url') sends what a replace sends, the document and
 * the overlay, to that address instead (Workspace); for an artboard the PNG
 * drawn here (picture) in place of the overlay; save.label names its
 * button. opts.doc with opts.blank opens a canvas saved before with its
 * layers (M.canvasOpenDoc()). Without any of these, or with save.mode 'callback', the document
 * goes to onSave and nothing is written.
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

    // The shared palette: a colour and its label's key.
    var COLORS = [['#ffffff', 'colorWhite'], ['#1a1c1e', 'colorBlack'], ['#e53935', 'colorRed'], ['#fb8c00', 'colorOrange'],
        ['#fdd835', 'colorYellow'], ['#43a047', 'colorGreen'], ['#1e88e5', 'colorBlue'], ['#8e24aa', 'colorPurple']];

    // A panel section of colour swatches; value is the chosen colour.
    // pick(color, live): live is true while the colour input moves (one undo
    // step), false for a swatch or the input's change. opts.custom (default
    // true) adds the colour input; opts.transparent puts a transparent
    // swatch first.
    function swatches(label, value, pick, opts) {
        var row = el('div', { class: 'pgie-swatches' }), now = String(value || '').toLowerCase(), list = COLORS.slice();

        opts = opts || {};

        if (opts.transparent) {
            list.unshift(['transparent', 'colorTransparent']);
        }

        list.forEach(function (c) {
            var on = (c[0] === now);

            row.appendChild(el('button', { type: 'button', class: 'pgie-swatch' + ((c[0] === 'transparent') ? ' pgie-checker' : '') + (on ? ' on' : ''),
                style: (c[0] === 'transparent') ? null : 'background: ' + c[0], 'aria-pressed': on ? 'true' : 'false', 'aria-label': t(c[1]), title: t(c[1]),
                onclick: function () { pick(c[0], false); } }));
        });

        if (opts.custom !== false) {
            var input = el('input', { type: 'color', class: 'pgie-colorin', value: /^#[0-9a-f]{6}$/.test(now) ? now : '#000000', 'aria-label': t('customColor'), title: t('customColor') });

            input.addEventListener('input', function () { pick(input.value, true); });
            input.addEventListener('change', function () { pick(input.value, false); });
            row.appendChild(input);
        }

        return section(label, row);
    }

    function panelTheme() {
        var a = document.documentElement.getAttribute('data-bs-theme');

        return (a === 'dark') ? 'dark' : 'light';
    }

    function Editor(opts, resolve) {
        var save = opts.save || {}, self = this;

        this.opts = opts;
        this.resolve = resolve;
        // replace, copy, new or callback: see the top of this file.
        this.saveMode = save.mode || (save.url ? 'url' : (opts.fileId ? 'replace' : ((opts.blank && save.folderId) ? 'new' : 'callback')));

        if (!config().api || ((this.saveMode === 'url') && !save.url)) {
            this.saveMode = 'callback';
        }

        this.saving = false;
        this.saveMenu = null;
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
        // A canvas saved before (opts.doc with opts.blank) opens with its
        // layers, texts still editable.
        this.reopened = (opts.blank && opts.doc) ? M.canvasOpenDoc(opts.doc) : null;

        if (this.reopened) {
            this.doc = this.reopened;
        }

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
        Editor.inits.forEach(function (init) { init.call(self); });

        // The New image dialog has nothing to ask of a canvas opened again.
        if (this.reopened && this.board) {
            this.board.ask = false;
        }

        this.build();
        this.load();
    }

    // What the tool files (pg_image_editor_<tool>.js, bundled after this
    // one) hang on the editor, so a tool lives in its own file; this is the
    // editor in every function:
    //   inits                     [fn()]: sets the tool's own fields, before
    //                             the dialog is built
    //   panels[tool](body)        fills the tool's panel
    //   headers[tool]()           an element for the panel's header, or null
    //   hints[tool]               the string key of the footer's hint
    //   pointers[tool]            { down(e), move(e), up(e), double(e) }: a
    //                             press with one finger or the left button;
    //                             down sets this.drag while it drags; double
    //                             is true when it used a double click (else
    //                             the view zooms)
    //   selectTools               [tool]: tools that show the selection's
    //                             handles and take its keys (Select's own
    //                             press is this.startDrag(e))
    //   layerTypes[type]          node(l): an SVG element in the rotated
    //                             space; paint(g, l): onto a canvas; and as
    //                             needed: ratio (a corner keeps the shape),
    //                             ready(l) (a promise of what painting needs:
    //                             a picture, a font), name(l) (the layers
    //                             list's), hit(l, p, slack) (false when p, in
    //                             the layer's frame, misses the drawing),
    //                             edit(l) (a double click: type in place),
    //                             panel(body, layers) (controls for selected
    //                             layers of the type), handles(l) (more
    //                             handles [{ h, x, y, title }] in the layer's
    //                             frame from its box's corner; box: false
    //                             leaves out the box's), drag(start, h, x, y,
    //                             e) (the fields such a handle changes),
    //                             resize(start, box, h) (the fields a box
    //                             handle changes, box from M.resizeBox())
    //   keys                      [fn(e, mod, key)]: true when it used the key
    //   escapes                   [fn()]: true when it used Escape (ends a
    //                             tool's own state before the selection)
    //   views                     [fn()]: run by every redraw of the stage
    //   saveHooks                 [fn(body, mode)]: add to a save request
    //   topItems, menuItems       [fn()]: an element for the top bar (before
    //                             Save) or the save menu, or null
    Editor.inits = [];
    Editor.panels = {};
    Editor.headers = {};
    Editor.hints = {};
    Editor.pointers = {};
    Editor.selectTools = [];
    Editor.layerTypes = {};
    Editor.keys = [];
    Editor.escapes = [];
    Editor.views = [];
    Editor.saveHooks = [];
    Editor.topItems = [];
    Editor.menuItems = [];

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
            mini ? button('pgie-btn', t('cancel'), null, function () { self.requestClose(); }, true) : null
        ].concat(Editor.topItems.map(function (item) { return mini ? null : item.call(self); }), [
            this.saveBox,
            this.fullBtn
        ]));

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

        if (((this.saveMode === 'replace') || (this.saveMode === 'copy')) && (this.opts.fileId || this.opts.fileName)) {
            // The server names the file, its address and whether a save may
            // write over it; a file asked for by name gets its id here.
            this.request(this.opts.fileId ? { action: 'open', file_id: this.opts.fileId } : { action: 'open', file_name: this.opts.fileName }).then(function (r) {
                if (!r || (r.status !== 'success')) {
                    self.fail((r && r.message) || t('loadError'));
                    return;
                }

                self.file = r.file;
                self.opts.fileId = r.file.id;
                self.doc.source.fileId = r.file.id;

                if (!r.file.replace && (self.saveMode === 'replace')) {
                    self.saveMode = 'copy';
                    self.renderSave();
                }

                self.nameEl.textContent = r.file.name;
                self.dialog.setAttribute('aria-label', t('dialog') + ': ' + r.file.name);

                if (r.file.notice) {
                    self.showMessage(r.file.notice);
                }

                self.openFile(r);
            }, function () {
                self.fail(t('loadError'));
            });

            return;
        }

        this.loadImage(this.opts.src || '');
    };

    // The open answer's picture into the stage; the versions part loads the
    // version a saved document was drawn on instead.
    Editor.prototype.openFile = function (r) {
        this.loadImage(this.opts.src || r.file.url);
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

    // stageInset: the height a bar along the stage's bottom takes (the
    // Animate tool's play bar), which a fitted picture stays above.
    Editor.prototype.stageSize = function () {
        return { w: this.stage.clientWidth, h: this.stage.clientHeight - (this.stageInset || 0) };
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

        var self = this;

        Editor.views.forEach(function (view) { view.call(self); });
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
            // A second finger ends a tool's drag where it is.
            if (this.drag && Editor.pointers[this.tool]) {
                Editor.pointers[this.tool].up.call(this, e);
            }

            e.preventDefault();
            this.startPan();

            try {
                this.stage.setPointerCapture(e.pointerId);
            } catch (err) {
                // The pointer is already gone.
            }
        } else if ((e.button === 0) && (ids.length === 1) && this.ready && Editor.pointers[this.tool]) {
            Editor.pointers[this.tool].down.call(this, e);
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

        if (this.drag && Editor.pointers[this.tool]) {
            Editor.pointers[this.tool].move.call(this, e);
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

        if (this.drag && Editor.pointers[this.tool]) {
            Editor.pointers[this.tool].up.call(this, e);
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
        var tool = Editor.pointers[this.tool];

        if (!this.ready || (e.target && e.target.closest && e.target.closest('.pgie-mini'))) {
            return;
        }

        if (tool && tool.double && tool.double.call(this, e)) {
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

        // Select with nothing selected on a canvas shows the canvas first.
        var title = (this.tool !== 'select') ? t('tool_' + this.tool) : ((this.doc.artboard && !this.sel.length) ? t('tool_artboard') : t('layers'));
        var body = el('div', { class: 'pgie-pb' });

        if (Editor.panels[this.tool]) {
            Editor.panels[this.tool].call(this, body);
        }

        this.panel.textContent = '';
        this.panel.setAttribute('aria-label', title);
        var self = this;

        this.panel.appendChild(el('div', { class: 'pgie-ph' }, [
            el('h2', { text: title }),
            el('div', { class: 'pgie-grow' }),
            Editor.headers[this.tool] ? Editor.headers[this.tool].call(this) : null,
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
        this.hint.textContent = t(Editor.hints[this.tool] || 'hintView');
        this.undoBtn.disabled = !this.history.canUndo();
        this.redoBtn.disabled = !this.history.canRedo();

        var before = this.panelControls(), index = before.nodes.indexOf(document.activeElement);

        this.renderPanel();
        this.renderView();

        // A button the redraw took away leaves the focus on the page body,
        // where the editor's keys no longer arrive: its new copy in the panel
        // takes it back, else the stage.
        if (!this.dialog.contains(document.activeElement)) {
            var after = this.panelControls(), back = after.nodes[M.focusMatch(before.list, index, after.list)];

            // A button the change disabled (Bring to front, at the front) cannot hold it.
            (back && !back.disabled ? back : this.stage).focus({ preventScroll: true });
        }
    };

    // The panel's focusable controls in order, each described for
    // M.focusMatch() by its tag and label (name) and its tag and classes (cls).
    Editor.prototype.panelControls = function () {
        var nodes = this.panel ? Array.prototype.slice.call(this.panel.querySelectorAll('button, input, select, textarea, a[href], [tabindex]')) : [];

        return {
            nodes: nodes,
            list: nodes.map(function (n) {
                var label = n.getAttribute('aria-label') || n.getAttribute('title') || (n.textContent || '').trim();

                return { name: n.tagName + '|' + (n.type || '') + '|' + label, cls: n.tagName + '|' + (n.getAttribute('class') || '') };
            })
        };
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

        // A focused slider or radio button steps its own value with the
        // arrows, Home, End and the page keys; Ctrl+Z still reaches the editor.
        if (owns && !mod && (k !== 'Enter') && (k !== ' ')) {
            return;
        }

        for (var i = 0; i < Editor.keys.length; i++) {
            if (Editor.keys[i].call(this, e, mod, k)) {
                e.preventDefault();
                return;
            }
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

        for (var i = 0; i < Editor.escapes.length; i++) {
            if (!this.confirmBox && Editor.escapes[i].call(this)) {
                return;
            }
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
        this.saveBtn = button('pgie-btn pgie-primary', this.saveLabel(), null, function () { self.save(); }, true);
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

    // A caller's own word for its save (save.label), else Done or Save.
    Editor.prototype.saveLabel = function () {
        var save = this.opts.save || {};

        if ((this.saveMode === 'url') && save.label) {
            return String(save.label);
        }

        return this.tools.mini ? t('done') : t('save');
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

        this.saveMenu = el('div', { class: 'pgie-menu', role: 'menu', 'aria-label': t('moreSave') }, [copy].concat(Editor.menuItems.map(function (item) { return item.call(self); })));
        this.saveBox.appendChild(this.saveMenu);
        copy.focus();
    };

    // url: another endpoint that takes the same body (save.url).
    Editor.prototype.request = function (body, url) {
        var cfg = config();

        body.token = cfg.token || '';

        return fetch(url || cfg.api, {
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

    Editor.prototype.setSaving = function (on) {
        this.saving = on;
        this.root.classList.toggle('pgie-busy', on);
        this.root.setAttribute('aria-busy', on ? 'true' : 'false');
        this.saveBtn.disabled = on;
        this.saveBtn.lastChild.textContent = on ? t('saving') : this.saveLabel();

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
        if (((mode === 'replace') || (mode === 'url')) && !this.dirty) {
            this.finish(null);
            return;
        }

        this.hideMessage();
        this.setSaving(true);
        this.layersReady().then(function () {
            self.send(mode);
        }, function () {
            self.setSaving(false);
            self.showMessage(t('saveError'), true);
        });
    };

    Editor.prototype.send = function (mode) {
        var self = this, body = { action: 'save', mode: mode, doc: this.doc };

        // Without Imagick the server takes the colour step from here.
        if (!this.doc.artboard && !config().serverColor && M.colorMatrix(M.colorValues(this.doc))) {
            body.colour = this.drawColour();

            if (!body.colour) {
                this.setSaving(false);
                this.showMessage(t('saveError'), true);
                return;
            }
        }

        // An artboard is the PNG drawn here: a new file, or a canvas another
        // endpoint keeps (save.url, Workspace).
        if (this.doc.artboard) {
            if (mode === 'new') {
                body.folder_id = (this.opts.save && this.opts.save.folderId) || 0;
            }

            body.name = (this.opts.save && this.opts.save.name) || this.opts.fileName || '';
            body.picture = this.drawArtboard();
        } else {
            if (mode !== 'url') {
                body.file_id = this.opts.fileId;
            }

            body.overlay = this.drawOverlay();
        }

        Editor.saveHooks.forEach(function (hook) { hook.call(self, body, mode); });

        this.request(body, (mode === 'url') ? this.opts.save.url : '').then(function (r) {
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
        },

        // For the tool files: the editor and the helpers they build with.
        parts: {
            Editor: Editor, M: M, SVGNS: SVGNS, icons: ICONS, config: config, find: find, t: t, el: el, icon: icon,
            section: section, tile: tile, decimal: decimal, shapeRadius: shapeRadius, button: button, colors: COLORS, swatches: swatches
        }
    };
}());
