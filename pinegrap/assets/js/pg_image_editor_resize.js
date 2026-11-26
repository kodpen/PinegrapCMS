/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: "Resize to other sizes" in the Canvas tool.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * Under the canvas size a button opens a list of the five preset sizes and
 * a custom one. Make draws a copy of the canvas at each picked size, its
 * layers placed and scaled by the model's rule (M.resizeDoc()), and saves
 * it as a new canvas in Files: beside the canvas's file, or in the folder a
 * new canvas is made in. A line then says how many were made and names the
 * texts that came out under the readable floor, with Show selecting them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, button = X.button;

    // Where the made canvases go: beside the canvas's file (a copy), into
    // the folder a new canvas is made in (new); null where Files cannot be
    // reached (a channel's canvas, a caller's callback).
    Editor.prototype.resizeTarget = function () {
        if ((this.saveMode === 'replace') && this.opts.fileId) {
            return { mode: 'copy' };
        }

        if ((this.saveMode === 'new') && this.opts.save && this.opts.save.folderId) {
            return { mode: 'new', folder: this.opts.save.folderId };
        }

        return null;
    };

    // The name the made canvases start from.
    Editor.prototype.resizeBaseName = function () {
        return (this.opts.save && this.opts.save.name) || this.opts.fileName || (this.file && this.file.name) || '';
    };

    // A text layer's words for the line, on one line and short.
    function textLabel(l) {
        var s = String((l && l.text) || '').replace(/\s+/g, ' ').trim();

        return (s.length > 24) ? s.slice(0, 23) + '…' : s;
    }

    // The texts of the canvas by id, in every scene.
    Editor.prototype.resizeTexts = function () {
        var map = {}, put = function (l) {
            if (l && (l.type === 'text')) {
                map[l.id] = l;
            }
        };

        (this.doc.layers || []).forEach(put);
        (this.doc.scenes || []).forEach(function (s) {
            (s.layers || []).forEach(put);
        });

        return map;
    };

    // The line after Make: how many were made (or where it stopped), and
    // the texts under the floor with Show selecting those on the open
    // scene.
    Editor.prototype.resizeLine = function () {
        var self = this, r = this.resizeState.result, texts = this.resizeTexts(), names = [], show = [], parts = [];

        if (r.error) {
            parts.push(t('resizeStopped').replace('{done}', r.made).replace('{n}', r.n));
        } else {
            parts.push((r.made === 1) ? t('resizeMadeOne') : t('resizeMade').replace('{n}', r.made));
        }

        r.small.forEach(function (s) {
            var l = texts[s.id], name = textLabel(l);

            if (l && name && (names.indexOf(name) < 0)) {
                names.push(name);
            }

            if (!s.scene && (self.doc.layers || []).some(function (x) { return x.id === s.id; }) && (show.indexOf(s.id) < 0)) {
                show.push(s.id);
            }
        });

        var line = el('p', { class: 'pgie-note pgie-resize-line', role: 'status' }, [el('span', { text: parts.join(' ') })]);

        if (names.length) {
            line.appendChild(el('span', { class: 'pgie-resize-small', text: t('resizeSmall').replace('{floor}', M.RESIZE_FLOOR).replace('{list}', names.join(', ')) }));
        }

        if (show.length) {
            line.appendChild(el('button', { type: 'button', class: 'pgie-btn pgie-resize-show', text: t('resizeShow'), onclick: function () {
                self.pickTool('select');
                self.select(show.filter(function (id) { return (self.doc.layers || []).some(function (x) { return x.id === id; }); }));
            } }));
        }

        return line;
    };

    // The button, and when it is open the sizes, the note and Make.
    Editor.prototype.resizeSection = function () {
        var self = this, s = this.resizeState, where = this.resizeTarget(), box = el('div', { class: 'pgie-resize' });
        var toggle = button('pgie-btn pgie-resize-open', t('resizeOpen'), 'fit', function () {
            s.open = !s.open;
            self.renderPanel();
        }, true);

        toggle.setAttribute('aria-expanded', s.open ? 'true' : 'false');
        box.appendChild(toggle);

        if (s.result && !s.busy) {
            box.appendChild(this.resizeLine());
        }

        if (!s.open) {
            return box;
        }

        var list = el('div', { class: 'pgie-resize-list', role: 'group', 'aria-label': t('resizeOpen') });
        var go = el('button', { type: 'button', class: 'pgie-btn pgie-primary pgie-resize-make' });
        var note = el('p', { class: 'pgie-note pgie-resize-why', role: 'status' });
        var refresh = function () {
            var n = M.resizeSizes(s.ticks, s.custom).length;

            go.textContent = (n === 1) ? t('resizeMakeOne') : t('resizeMake').replace('{n}', n);
            go.disabled = !where || !n || s.busy;
            note.textContent = !where ? t('resizeNoFiles') : (n ? '' : t('resizeEmpty'));
            note.hidden = !note.textContent;
        };
        var tick = function (label, on, change) {
            var input = el('input', { type: 'checkbox' });

            input.checked = on;
            input.addEventListener('change', function () {
                change(input.checked);
                refresh();
            });

            return { input: input, row: el('label', { class: 'pgie-check' }, [input, el('span', { text: label })]) };
        };

        M.BOARD_PRESETS.forEach(function (p) {
            list.appendChild(tick(t('preset_' + p[0]) + ' · ' + p[1] + ' × ' + p[2], s.ticks.indexOf(p[0]) >= 0, function (on) {
                s.ticks = s.ticks.filter(function (k) { return k !== p[0]; }).concat(on ? [p[0]] : []);
            }).row);
        });

        var custom = tick(t('resizeCustom'), s.custom.on, function (on) { s.custom.on = on; });
        var side = function (key, label) {
            var input = el('input', { type: 'number', class: 'pgie-input', min: '16', max: '8000', step: '1', value: String(s.custom[key]), 'aria-label': label });

            input.addEventListener('input', function () {
                s.custom[key] = input.value;

                // Typing a side ticks the custom size.
                if (!s.custom.on && (input.value !== '')) {
                    s.custom.on = true;
                    custom.input.checked = true;
                }

                refresh();
            });

            return input;
        };

        list.appendChild(custom.row);
        list.appendChild(el('div', { class: 'pgie-wh pgie-resize-custom' }, [side('w', t('width')), el('span', { text: '×' }), side('h', t('height')), el('span', { text: 'px' })]));
        go.addEventListener('click', function () { self.resizeMake(); });

        box.appendChild(list);
        box.appendChild(el('p', { class: 'pgie-note', text: t('resizeNote') }));
        box.appendChild(go);
        box.appendChild(note);
        refresh();

        return box;
    };

    // Each picked size drawn and saved as a new canvas, one after the
    // other; a failure stops the rest. The caller hears of the files made
    // (options.onCreated: the File Manager shows them).
    Editor.prototype.resizeMake = function () {
        var self = this, s = this.resizeState, where = this.resizeTarget(), sizes = M.resizeSizes(s.ticks, s.custom), base = this.resizeBaseName();
        var made = [], small = [];

        if (!where || !sizes.length || s.busy || this.saving || !this.doc.artboard) {
            return;
        }

        s.busy = true;
        s.result = null;
        this.renderPanel();

        var done = function (e) {
            s.busy = false;
            s.result = { made: made.length, n: sizes.length, small: small, error: !!e };
            self.hideMessage();

            if (made.length && (typeof self.opts.onCreated === 'function')) {
                self.opts.onCreated(made);
            }

            if (!self.finished) {
                self.renderPanel();

                if (e) {
                    self.showMessage((e && e.message) || t('saveError'), true);
                }
            }
        };

        sizes.reduce(function (chain, size) {
            return chain.then(function () {
                if (self.finished) {
                    return null;
                }

                var r = M.resizeDoc(self.doc, size[1], size[2]), copy = M.canvasOpenDoc(r.doc) || r.doc, name = M.resizeName(base, size[1], size[2]);

                self.showMessage(t('resizeMaking').replace('{done}', made.length).replace('{n}', sizes.length));

                return self.withDoc(copy, function () { return self.layersReady(); }).then(function () {
                    var body = self.withDoc(copy, function () { return self.sendBody(where.mode, (where.mode === 'new') ? { folder: { id: where.folder } } : null); });

                    if (!body) {
                        throw new Error(t('saveError'));
                    }

                    // A made size is always a new canvas file, whatever the
                    // export settings ask of the canvas's own save.
                    delete body.export;
                    body.mode = where.mode;

                    if (where.mode === 'copy') {
                        body.copy_name = name;
                    } else {
                        body.name = name;
                    }

                    return self.request(body);
                }).then(function (a) {
                    if (!a || (a.status !== 'success')) {
                        throw new Error((a && a.message) || t('saveError'));
                    }

                    made.push({ id: a.id, name: a.name || name, url: a.url || '' });
                    r.small.forEach(function (x) { small.push(x); });
                });
            });
        }, Promise.resolve()).then(function () { done(null); }, done);
    };

    // Under the canvas size of the Canvas tool on a canvas (a picture's
    // canvas has one size: its own).
    var artboardPanel = Editor.panels.artboard;

    Editor.panels.artboard = function (body) {
        var before = body.children.length;

        artboardPanel.call(this, body);

        if (this.doc.artboard && (body.children.length > before)) {
            body.insertBefore(this.resizeSection(), body.children[before + 1] || null);
        }
    };

    Editor.inits.push(function () {
        this.resizeState = { open: false, ticks: [], custom: { on: false, w: '', h: '' }, busy: false, result: null };
    });
}(window.PgImageEditor.parts));
