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
 *       profile: 'photo' | 'canvas' | 'annotate' | 'optimise',
 *       tools: ['arrow', ...],                    a subset: the mini bar
 *       theme: 'light' | 'dark',                  default: the panel's
 *       fullscreen: true,                         open filling the window
 *       onSave: function (result) {},            result.doc: the document
 *       onClose: function () {}
 *   }).then(function (result) {});              null when closed unsaved
 *
 * One editor is open at a time. Labels come from
 * window.pgImageEditorConfig.strings, geometry from PgImageEditorModel
 * (assets/js/pg_image_editor_model.js). docs/memory/ui/image-editor-modal.md
 * describes the parts.
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
        fit: 'M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5M9 9h6v6H9z'
    };

    var current = null;

    function t(key) {
        var cfg = window.pgImageEditorConfig, s = (cfg && cfg.strings) || {};

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
        this.opts = opts;
        this.resolve = resolve;
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

        var top = el('header', { class: 'pgie-top' }, [
            mini ? null : button('pgie-ibtn', t('close'), 'close', function () { self.requestClose(); }),
            el('div', { class: 'pgie-file' }, [el('div', { class: 'pgie-fname', text: name || t('dialog') }), this.meta]),
            mini ? null : el('div', { class: 'pgie-sepv' }),
            mini ? null : this.undoBtn,
            mini ? null : this.redoBtn,
            el('div', { class: 'pgie-grow' }),
            mini ? button('pgie-btn', t('cancel'), null, function () { self.requestClose(); }, true) : null,
            button('pgie-btn pgie-primary', mini ? t('done') : t('save'), null, function () { self.save(); }, true),
            this.fullBtn
        ]);

        this.img = null;
        this.layersSvg = document.createElementNS(SVGNS, 'svg');
        this.layersSvg.setAttribute('class', 'pgie-layers');
        this.layersSvg.setAttribute('aria-hidden', 'true');
        this.frame = el('div', { class: 'pgie-frame' }, [this.layersSvg]);
        this.notice = el('div', { class: 'pgie-notice', role: 'status', text: this.opts.blank ? '' : t('loading') });
        this.stage = el('main', { class: 'pgie-stage', tabindex: '-1' }, [this.frame, this.notice]);

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

        var foot = el('footer', { class: 'pgie-foot' }, [
            el('span', { class: 'pgie-hint', text: t('hintView') }),
            el('div', { class: 'pgie-grow' }),
            this.sizeLabel,
            button('pgie-ibtn sm', t('zoomOut'), 'minus', function () { self.zoomBy(1 / ZOOM_STEP); }),
            this.zoomLabel,
            button('pgie-ibtn sm', t('zoomIn'), 'plus', function () { self.zoomBy(ZOOM_STEP); }),
            button('pgie-ibtn sm', t('zoomFit'), 'fit', function () { self.fit(); })
        ]);

        this.confirmBox = null;
        this.root = el('div', { class: 'pgie' + (theme === 'dark' ? ' pgie-dark' : '') + (mini ? ' pgie-ismini' : '') }, [top, body, foot]);
        this.dialog = el('dialog', { class: 'pgie-dialog', 'aria-label': name ? t('dialog') + ': ' + name : t('dialog') }, [this.root]);

        this.dialog.addEventListener('cancel', function (e) {
            e.preventDefault();
            self.onEscape();
        });
        this.dialog.addEventListener('keydown', function (e) { self.onKey(e); });
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

        var img = new Image();

        img.className = 'pgie-img';
        img.alt = '';
        img.draggable = false;
        img.onload = function () {
            self.doc.source.width = img.naturalWidth;
            self.doc.source.height = img.naturalHeight;
            self.img = img;
            self.frame.insertBefore(img, self.layersSvg);
            self.setReady();
        };
        img.onerror = function () {
            self.notice.textContent = t('loadError');
            self.notice.classList.add('pgie-error');
        };
        img.src = this.opts.src || '';
    };

    Editor.prototype.setReady = function () {
        var size = M.docSize(this.doc);

        this.ready = true;
        this.notice.textContent = '';
        this.layersSvg.setAttribute('viewBox', '0 0 ' + size.w + ' ' + size.h);
        this.meta.textContent = size.w + ' × ' + size.h + ' px';
        this.sizeLabel.textContent = this.meta.textContent;
        this.fit();
    };

    // ---- view ----

    Editor.prototype.stageSize = function () {
        return { w: this.stage.clientWidth, h: this.stage.clientHeight };
    };

    Editor.prototype.fit = function () {
        if (!this.ready) {
            return;
        }

        var size = M.docSize(this.doc), st = this.stageSize();
        var k = Math.min(1, M.fitZoom(size.w, size.h, st.w, st.h, (st.w < 600) ? 12 : PAD));

        this.fitMode = true;
        this.view = M.centred(size.w, size.h, st.w, st.h, k);
        this.renderView();
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
        var size = M.docSize(this.doc), v = this.view;

        this.frame.style.transform = 'translate(' + v.x + 'px, ' + v.y + 'px)';
        this.frame.style.width = (size.w * v.k) + 'px';
        this.frame.style.height = (size.h * v.k) + 'px';
        this.zoomLabel.textContent = Math.round(v.k * 100) + ' %';
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
            e.preventDefault();
            this.startPan();

            try {
                this.stage.setPointerCapture(e.pointerId);
            } catch (err) {
                // The pointer is already gone.
            }
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

        // A tool opens the sheet; pressing the active tool again closes or
        // opens it; Select opens it only on that second press.
        this.sheetOpen = (id === this.tool) ? !this.sheetOpen : (id !== 'select');
        this.tool = id;
        this.render();
    };

    Editor.prototype.renderPanel = function () {
        if (!this.panel) {
            return;
        }

        var title = (this.tool === 'select') ? t('layers') : t('tool_' + this.tool);
        var body = el('div', { class: 'pgie-pb' });

        if (this.tool === 'select') {
            body.appendChild(this.doc.layers.length ? el('ul', { class: 'pgie-layerlist' }) : el('p', { class: 'pgie-note', text: t('noLayers') }));
        }

        this.panel.textContent = '';
        this.panel.setAttribute('aria-label', title);
        var self = this;

        this.panel.appendChild(el('div', { class: 'pgie-ph' }, [
            el('h2', { text: title }),
            el('div', { class: 'pgie-grow' }),
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
        this.undoBtn.disabled = !this.history.canUndo();
        this.redoBtn.disabled = !this.history.canRedo();
        this.renderPanel();
        this.renderView();
    };

    // ---- document changes ----

    // Every change goes through here: the history keeps the document as it
    // was, mutate() changes it. Calls with the same key in a row (a slider
    // drag) are one undo step.
    Editor.prototype.change = function (mutate, key) {
        this.history.push(M.clone(this.doc), key);
        mutate(this.doc);
        this.dirty = true;
        this.render();
    };

    Editor.prototype.restore = function (snapshot) {
        if (!snapshot) {
            return;
        }

        this.doc = snapshot;
        this.dirty = true;
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
        var mod = e.ctrlKey || e.metaKey, k = e.key;
        var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((e.target && e.target.tagName) || '') || (e.target && e.target.isContentEditable);

        if (this.confirmBox || typing) {
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

        if (k === ' ' && !this.space) {
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
        if (this.confirmBox) {
            this.closeConfirm();
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

    Editor.prototype.save = function () {
        var result = { doc: M.clone(this.doc), fileId: this.doc.source ? this.doc.source.fileId : 0 };

        if (typeof this.opts.onSave === 'function') {
            this.opts.onSave(result);
        }

        this.finish(result);
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

    Editor.prototype.finish = function (result) {
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
