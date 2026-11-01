/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the crop tool: the crop box and its handles, ratios, quarter
 * turns and flips, straightening, the crop shape and the output size.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, section = X.section, tile = X.tile, decimal = X.decimal, shapeRadius = X.shapeRadius, button = X.button;

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

    Editor.panels.crop = function (body) {
        if (this.cropping()) {
            this.cropPanel(body);
        }
    };

    Editor.headers.crop = function () {
        var self = this;

        return this.cropping() ? el('button', { type: 'button', class: 'pgie-link', text: t('reset'), onclick: function () { self.resetGeometry(); } }) : null;
    };

    Editor.views.push(Editor.prototype.renderCrop);
}(window.PgImageEditor.parts));
