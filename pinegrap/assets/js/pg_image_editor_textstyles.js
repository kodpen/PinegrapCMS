/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: text styles and font pairs at the top of the Text panel.
 * Three full-width buttons, each written in its own style ("Add a heading",
 * "Add a subheading", "Add body text"), put a text layer sized to the board
 * in its middle. Under them the section "Font pairs": twelve tiles, each a
 * heading and a line in the pair's two Google faces; a press puts both as
 * one group.
 *
 * The pairs are a file (assets/canvas/font-pairs.json, config fontPairs)
 * read when the Text panel first opens and checked by the model's
 * M.fontPairs(); the sizes and the stack in the board's middle are
 * M.textPreset(), M.fontPairStyles() and M.textStack(). A part of
 * window.PgImageEditor bundled after the Text tool and the Google Fonts
 * sheet; it puts its rows before the Text tool's own panel
 * (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, section = X.section;

    var PRESETS = [['heading', 'textAddHeading'], ['subheading', 'textAddSubheading'], ['body', 'textAddBody']];

    // The checked pairs: null until read, then a list (empty when the file
    // could not be read); one read a page.
    var pairs = null, reading = null;

    function known(family) {
        return !!window.PgGoogleFonts && (window.PgGoogleFonts.all().indexOf(family) >= 0);
    }

    function readPairs() {
        var url = X.config().fontPairs;

        if (!reading) {
            reading = (url && window.fetch) ? window.fetch(url, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) {
                    throw new Error('font pairs');
                }

                return r.json();
            }).then(function (data) {
                pairs = M.fontPairs(data, known);
            }, function () {
                pairs = [];
            }) : Promise.resolve().then(function () { pairs = []; });
        }

        return reading;
    }

    // Text layers of the given styles and words stacked in the board's
    // middle, two or more as one group; one undo step, the new layers
    // selected.
    Editor.prototype.addStyledTexts = function (styles, words) {
        var self = this, board = this.viewRect(), added = [], layers, at;

        if (this.textEdit) {
            this.endTextEdit();
        }

        layers = styles.map(function (s, i) {
            var l = M.clone(s), lay;

            l.type = 'text';
            l.text = words[i];
            l.rot = 0;
            l.opacity = 1;
            lay = self.textLayout(l);
            l.w = lay.w;
            l.h = lay.h;

            return l;
        });
        at = M.textStack(board, layers, (layers.length > 1) ? Math.round(layers[1].size * 0.6) : 0);

        this.change(function (doc) {
            var g = (layers.length > 1) ? M.groupNewId(doc) : null;

            layers.forEach(function (l, i) {
                l.x = at[i].x;
                l.y = at[i].y;
                l.id = M.newId(doc);

                if (g) {
                    l.grp = [g];
                }

                doc.layers.push(l);
                added.push(l.id);
            });
        });
        this.select(added);
    };

    // A pair's faces, linked when its tile is drawn; the stage refits the
    // texts when a face comes later (image-editor-text.md).
    function pairFaces(editor, pair) {
        return Promise.all(['head', 'line'].map(function (k) {
            return editor.loadTextFont({ font: 'g:' + pair[k].family, size: 20, weight: pair[k].weight, text: t(pair[k].text) });
        }));
    }

    Editor.prototype.fontPairTiles = function (holder) {
        var self = this;

        holder.textContent = '';

        if (!pairs.length) {
            holder.appendChild(el('p', { class: 'pgie-note', text: t('fontPairsError') }));

            return;
        }

        pairs.forEach(function (pair) {
            var head = el('b', { text: t(pair.head.text) }), line = el('span', { text: t(pair.line.text) });

            head.style.fontFamily = M.textFamily('g:' + pair.head.family);
            head.style.fontWeight = String(pair.head.weight);
            line.style.fontFamily = M.textFamily('g:' + pair.line.family);
            line.style.fontWeight = String(pair.line.weight);
            pairFaces(self, pair);

            holder.appendChild(el('button', { type: 'button', class: 'pgie-tpair', title: pair.head.family + ' + ' + pair.line.family,
                'aria-label': t(pair.head.text) + ', ' + t(pair.line.text) + ' (' + pair.head.family + ', ' + pair.line.family + ')',
                onclick: function () {
                    self.addStyledTexts(M.fontPairStyles(pair, self.viewRect(), self.textStyle), [t(pair.head.text), t(pair.line.text)]);
                } }, [head, line]));
        });
    };

    // The rows before the Text tool's own panel.
    Editor.prototype.textStylesPanel = function (body) {
        var self = this, board = this.viewRect(), list = el('div', { class: 'pgie-tpresets' }), tiles = el('div', { class: 'pgie-tpairs', role: 'group', 'aria-label': t('fontPairs') });

        PRESETS.forEach(function (p) {
            var s = M.textPreset(p[0], board, self.textStyle), b = el('button', { type: 'button', class: 'pgie-tpreset pgie-tpreset-' + p[0], onclick: function () {
                self.addStyledTexts([M.textPreset(p[0], self.viewRect(), self.textStyle)], [t(p[1])]);
            } }, [el('span', { text: t(p[1]) })]);

            b.style.fontFamily = M.textFamily(s.font);
            list.appendChild(b);
        });

        body.appendChild(list);
        body.appendChild(section(t('fontPairs'), tiles));

        if (pairs) {
            this.fontPairTiles(tiles);
        } else {
            readPairs().then(function () {
                if (tiles.isConnected) {
                    self.fontPairTiles(tiles);
                }
            });
        }
    };

    var textPanel = Editor.panels.text;

    // Not on a caller's mini bar, which marks a screenshot.
    Editor.panels.text = function (body) {
        if (!this.tools.mini) {
            this.textStylesPanel(body);
        }

        textPanel.call(this, body);
    };
}(window.PgImageEditor.parts));
