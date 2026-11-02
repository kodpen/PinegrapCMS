/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: export settings: format, quality, longest edge, progressive
 * JPEG, PNG colour reduction, and the size before and after.
 *
 * The panel's settings go with a save as export (M.exportRequest()); a
 * format other than the file's own is saved as a copy, so the file keeps its
 * format and its address. The size after is the server's own encode of the
 * same recipe (action estimate), asked a moment after the last change.
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, section = X.section;

    var LABELS = { jpeg: 'JPEG', png: 'PNG', webp: 'WebP', avif: 'AVIF' };

    // How long after the last change the size is asked for.
    var ESTIMATE_DELAY = 450;

    Editor.inits.push(function () {
        this.exportSet = M.exportDefaults();
        // The last estimate: before, after, formats, colors (null till then).
        this.exportInfo = null;
        this.exportBusy = false;
        this.exportJob = 0;
        this.exportTimer = 0;
        // Whether the panel asked for the size since it opened.
        this.exportAsked = false;
    });

    // A picture saved through the API (not a canvas, not another endpoint).
    Editor.prototype.exportable = function () {
        return !this.doc.artboard && !!this.opts.fileId && ((this.saveMode === 'replace') || (this.saveMode === 'copy'));
    };

    // The file's own format, from its name.
    Editor.prototype.exportOwn = function () {
        return M.fileFormat((this.file && this.file.name) || this.opts.fileName || '');
    };

    // What a save carries for the panel's settings, or null.
    Editor.prototype.exportRequest = function () {
        return this.exportable() ? M.exportRequest(this.exportSet, this.exportOwn()) : null;
    };

    Editor.prototype.setExport = function (patch) {
        var self = this;

        Object.keys(patch).forEach(function (key) { self.exportSet[key] = patch[key]; });
        this.render();
        this.estimateLater();
    };

    // The export panel, from the save menu or the optimise profile.
    Editor.prototype.openExport = function () {
        if (this.cropping()) {
            this.applyCrop();
        }

        this.tool = 'export';
        this.layersOpen = false;
        this.sheetOpen = true;
        this.render();
    };

    Editor.prototype.estimateLater = function () {
        var self = this;

        clearTimeout(this.exportTimer);

        if (this.tool !== 'export' || !this.exportable() || !this.ready) {
            return;
        }

        this.exportTimer = setTimeout(function () { self.estimate(); }, ESTIMATE_DELAY);
    };

    // The server encodes the picture with these settings and answers its
    // size; an answer to an older request is dropped.
    Editor.prototype.estimate = function () {
        var self = this, job = ++this.exportJob, layout = M.saveLayout(this.doc);
        var body = { action: 'estimate', file_id: this.opts.fileId, doc: this.doc, base: this.versionBase || 0 };

        body.export = this.exportRequest();

        if (layout) {
            body.extend = layout;
        }

        this.exportBusy = true;
        this.renderExportCard();

        this.request(body).then(function (r) {
            if ((job !== self.exportJob) || self.finished) {
                return;
            }

            self.exportBusy = false;
            self.exportInfo = (r && (r.status === 'success')) ? r : { failed: true };
            self.render();
        }, function () {
            if (job === self.exportJob) {
                self.exportBusy = false;
                self.exportInfo = { failed: true };
                self.render();
            }
        });
    };

    // A row of chips, one pressed.
    function chips(label, items, value, pick) {
        var row = el('div', { class: 'pgie-chips', role: 'group', 'aria-label': label });

        items.forEach(function (item) {
            var on = (item[0] === value);

            row.appendChild(el('button', { type: 'button', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', text: item[1], onclick: function () { pick(item[0]); } }));
        });

        return row;
    }

    function check(label, on, change) {
        var input = el('input', { type: 'checkbox' });

        input.checked = !!on;
        input.addEventListener('change', function () { change(input.checked); });

        return el('label', { class: 'pgie-check' }, [input, el('span', { text: label })]);
    }

    Editor.prototype.exportPanel = function (body) {
        var self = this, ex = this.exportSet, own = this.exportOwn(), info = this.exportInfo || {};
        var format = M.exportFormat(ex.format, own);
        var formats = [['keep', t('exKeep') + (LABELS[own] ? ' (' + LABELS[own] + ')' : '')]];

        if (!this.exportable()) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('exUnknown') }));
            return;
        }

        (info.formats || ['jpeg', 'png']).forEach(function (f) {
            if (LABELS[f] && (f !== own)) {
                formats.push([f, LABELS[f]]);
            }
        });

        body.appendChild(section(t('exFormat'), chips(t('exFormat'), formats, ex.format, function (f) { self.setExport({ format: f }); })));

        // Quality is a lossy format's; PNG and a kept GIF have none.
        if ((format === 'jpeg') || (format === 'webp') || (format === 'avif')) {
            var value = el('span', { class: 'pgie-val', text: String(ex.quality) });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: '40', max: '100', step: '1', value: String(ex.quality), 'aria-label': t('exQuality') });

            slider.addEventListener('input', function () { value.textContent = slider.value; });
            slider.addEventListener('change', function () { self.setExport({ quality: Number(slider.value) }); });
            body.appendChild(section(t('exQuality'), slider, value));
        }

        body.appendChild(section(t('exEdge'), chips(t('exEdge'), M.EXPORT_EDGES.map(function (edge) {
            return [edge, edge ? String(edge) : t('exOriginal')];
        }), ex.edge, function (edge) { self.setExport({ edge: edge }); })));

        var options = [];

        if (format === 'jpeg') {
            options.push(check(t('exProgressive'), ex.progressive !== false, function (on) { self.setExport({ progressive: on }); }));
        }

        if ((format === 'png') && info.colors) {
            options.push(check(t('exColors'), ex.colors, function (on) { self.setExport({ colors: on }); }));
        }

        if (options.length) {
            body.appendChild(section(t('exOptions'), el('div', {}, options)));
        }

        body.appendChild(section(t('exMeta'), el('p', { class: 'pgie-note', text: t('exMetaNote') })));

        this.exportCard = el('div', { class: 'pgie-excard', 'aria-live': 'polite' });
        body.appendChild(section(t('exResult'), this.exportCard));
        this.renderExportCard();

        if (M.exportMode('replace', ex.format, own) === 'copy') {
            body.appendChild(el('p', { class: 'pgie-note', text: t('exCopyNote') }));
        }

        body.appendChild(el('button', { type: 'button', class: 'pgie-btn pgie-primary pgie-wide', text: t('exSaveWith'), onclick: function () { self.save(); } }));
    };

    // Before and after: the sizes, the dimensions, a bar and the change.
    Editor.prototype.renderExportCard = function () {
        var card = this.exportCard, info = this.exportInfo || {}, sep = (X.config() || {}).decimal;

        if (!card) {
            return;
        }

        var before = info.before || null, after = info.after || null;
        var change = (before && after && !this.exportBusy) ? M.exportChange(before.size, after.size) : null;
        var afterText = this.exportBusy ? t('exWorking') : (after ? M.exportBytes(after.size, sep) : (this.exportInfo ? t('exUnknown') : t('exWorking')));

        card.textContent = '';
        card.classList.toggle('pgie-busy', !!this.exportBusy);
        card.appendChild(el('div', { class: 'pgie-exrow' }, [
            el('span', { text: t('exBefore') }),
            el('b', { text: before ? M.exportBytes(before.size, sep) : '–' }),
            el('span', { class: 'pgie-exdim', text: before ? before.width + ' × ' + before.height : '' })
        ]));
        card.appendChild(el('div', { class: 'pgie-exrow' }, [
            el('span', { text: t('exAfter') }),
            el('b', { text: afterText }),
            el('span', { class: 'pgie-exdim', text: (after && !this.exportBusy) ? after.width + ' × ' + after.height : '' })
        ]));

        var bar = el('span');

        bar.style.width = (change ? change.bar : 100) + '%';
        card.appendChild(el('div', { class: 'pgie-exbar' }, [bar]));

        if (change) {
            card.appendChild(el('div', { class: 'pgie-exsave' + ((change.percent > 0) ? ' pgie-heavier' : ''), text: ((change.percent > 0) ? '+' : (change.percent < 0 ? '−' : '')) + Math.abs(change.percent) + '%' }));
        }
    };

    // The panel asks for the size once each time it opens (from the menu, or
    // first in the optimise profile once the picture is in).
    Editor.views.push(function () {
        if ((this.tool !== 'export') || !this.ready) {
            this.exportAsked = false;
        } else if (!this.exportAsked) {
            this.exportAsked = true;
            this.estimateLater();
        }
    });

    Editor.panels.export = Editor.prototype.exportPanel;
    Editor.hints.export = 'hintExport';

    // The settings go with the save; another format is a copy beside the
    // file, which keeps no document and so takes no pictures along.
    Editor.saveHooks.push(function (body, mode) {
        var request = ((mode === 'replace') || (mode === 'copy')) ? this.exportRequest() : null;

        if (!request) {
            return;
        }

        body.export = request;
        body.mode = M.exportMode(mode, this.exportSet.format, this.exportOwn());

        if (body.mode === 'copy') {
            delete body.pictures;
        }
    });

    // "Export settings…" in the save menu.
    Editor.menuItems.push(function () {
        var self = this;

        if (!this.exportable()) {
            return null;
        }

        return el('button', { type: 'button', role: 'menuitem', text: t('exportSettings'), onclick: function () {
            self.toggleSaveMenu(false);
            self.openExport();
        } });
    });
}(window.PgImageEditor.parts));
