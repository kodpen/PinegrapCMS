/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the Adjust tool: the adjustments and filters, their preview
 * and the colour layer a server without Imagick saves from.
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon, section = X.section, button = X.button;

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

    // The panel's tab and whether Details is open.
    Editor.inits.push(function () {
        this.adjustTab = 'adjust';
        this.adjustMore = false;
    });

    Editor.panels.adjust = function (body) {
        if (this.doc.source) {
            this.adjustPanel(body);
        }
    };

    Editor.headers.adjust = function () {
        var self = this;

        return this.hasColor() ? el('button', { type: 'button', class: 'pgie-link', text: t('reset'), onclick: function () {
            self.change(function (doc) {
                doc.adjust = {};
                doc.filter = 'none';
            });
        } }) : null;
    };

    Editor.hints.adjust = 'hintAdjust';
    Editor.views.unshift(Editor.prototype.renderColor);
}(window.PgImageEditor.parts));
