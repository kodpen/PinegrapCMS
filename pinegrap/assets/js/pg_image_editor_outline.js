/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: text to outlines. A text layer's letters become one path
 * layer read from the font file itself (the model's own OpenType reader),
 * on the same layout the stage draws.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
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

    var Editor = X.Editor, M = X.M, find = X.find, t = X.t, el = X.el, config = X.config, button = X.button;

    X.icons.outline = 'M5 19L10.5 5h3L19 19M7.6 13h8.8';

    // Parsed fonts by family, weight and style, once per page; a failed
    // load is tried again on the next conversion.
    var fonts = {};

    // A text layer's own fields, which a path does not have.
    var TEXT_FIELDS = ['text', 'font', 'size', 'weight', 'italic', 'align', 'lineHeight', 'spacing', 'bg', 'pad', 'radius', 'outline', 'shadow'];

    // A Google or site family's file through the editor's action font
    // (the server keeps the TrueType file Google serves to non-browsers).
    function loadFont(family, weight, italic) {
        var key = family + '|' + weight + '|' + (italic ? 1 : 0), cfg = config();

        if (!fonts[key]) {
            fonts[key] = fetch(cfg.api, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: cfg.token || '', action: 'font', family: family, weight: weight, italic: !!italic })
            }).then(function (res) {
                if (!res.ok || ((res.headers.get('Content-Type') || '').indexOf('font/') !== 0)) {
                    throw new Error('font');
                }

                return res.arrayBuffer();
            }).then(function (buf) {
                return M.fontRead(buf);
            });
            fonts[key].catch(function () { delete fonts[key]; });
        }

        return fonts[key];
    }

    // Why a text cannot be outlined before its font is read, or ''.
    function refusal(l) {
        if (String(l.font || '').slice(0, 2) !== 'g:') {
            return t('outlineSystemFont');
        }

        return M.outlineNeedsShaping(l.text) ? t('outlineShaping') : '';
    }

    // The selected texts become paths in one undo step; a text with a
    // background keeps it as a rounded box under the letters.
    Editor.prototype.convertToOutlines = function (btn) {
        var self = this, texts = this.selectedTexts().filter(function (l) { return !l.locked && String(l.text || '').trim(); });
        var why = '';

        if (this.textEdit) {
            this.endTextEdit();
        }

        texts.forEach(function (l) {
            why = why || refusal(l);
        });

        if (why || !texts.length) {
            if (why) {
                this.showMessage(why, true);
            }

            return;
        }

        if (btn) {
            btn.disabled = true;
        }

        Promise.all(texts.map(function (l) {
            return loadFont(M.textFamilyName(String(l.font).slice(2)), Number(l.weight) || 400, !!l.italic).then(function (f) {
                return { l: l, out: M.outlineText(f, l, self.textLayout(l)) };
            });
        })).then(function (done) {
            var missing = [];

            done.forEach(function (d) {
                d.out.missing.forEach(function (c) {
                    if (missing.indexOf(c) < 0) {
                        missing.push(c);
                    }
                });
            });

            if (missing.length) {
                self.showMessage(t('outlineMissing').replace('{chars}', missing.slice(0, 12).join(' ')), true);
                return;
            }

            self.change(function (doc) {
                done.forEach(function (d) {
                    var l = find(doc, d.l.id), at = doc.layers.indexOf(l), fx, keep, box;

                    if (!l || !d.out.contours.length) {
                        return;
                    }

                    // Its other fields (id, name, lock, motion) stay.
                    fx = M.textEffects(l);
                    keep = M.clone(l);
                    TEXT_FIELDS.forEach(function (key) { delete keep[key]; });

                    if (l.bg) {
                        box = {
                            type: 'rect', id: M.newId(doc), x: l.x, y: l.y, w: l.w, h: l.h, rot: l.rot || 0, opacity: (l.opacity === undefined) ? 1 : l.opacity,
                            color: l.bg, fill: 'solid', stroke: 0, dash: 'solid', radius: fx.radius
                        };

                        if (fx.shadow) {
                            box.shadow = fx.shadow;
                        }
                    }

                    doc.layers[at] = Object.assign(keep, M.outlineLayer(l, d.out.contours));

                    if (box) {
                        doc.layers.splice(at, 0, box);
                    }
                });
            });
        }).catch(function () {
            self.showMessage(t('outlineFontError'), true);
        }).then(function () {
            if (btn) {
                btn.disabled = false;
            }
        });
    };

    // The button under the text controls when texts are selected.
    var controls = Editor.prototype.textControls;

    Editor.prototype.textControls = function (body, layers) {
        var self = this, b;

        controls.call(this, body, layers);

        if (layers && layers.length && layers.some(function (l) { return !l.locked; })) {
            b = button('pgie-btn', t('outlineConvert'), 'outline', function () { self.convertToOutlines(b); }, true);
            body.appendChild(b);
            body.appendChild(el('p', { class: 'pgie-note', text: t('outlineNote') }));
        }
    };
}(window.PgImageEditor.parts));
