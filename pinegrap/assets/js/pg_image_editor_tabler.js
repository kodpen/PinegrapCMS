/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: Tabler Icons beside Bootstrap Icons in the Sticker tool.
 * The Icons section gains the switch "Bootstrap" / "Tabler"; Tabler's file
 * (assets/canvas/tabler-icons/icons.json, config tabler, name => path) is
 * read the first time that set is picked and checked by M.tablerIcons(). A
 * press puts the icon in the middle of the stage as an svg path layer,
 * selected; with Tabler icons selected, their colour and stroke width.
 *
 * The markup, its paint and the placed layer are M.tablerMarkup(),
 * M.tablerPaint(), M.tablerRepaint() and M.tablerLayer(). A part of
 * window.PgImageEditor bundled after the icons and the elements; the Icons
 * section asks it for the switch and the grid (iconSetSwitch(),
 * tablerFill()) (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, el = X.el, section = X.section;
    var t = function (key) { return X.t(key); };

    var COLOR = '#212529';
    var SHOWN = 120;

    // The checked icons: null until read, then a map (null again after a
    // failed read, so the next pick tries again); one read a page.
    var icons = null, reading = null;

    function readIcons() {
        var url = X.config().tabler;

        if (!reading) {
            reading = (url && window.fetch) ? window.fetch(url, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) {
                    throw new Error('tabler');
                }

                return r.json();
            }).then(function (data) {
                icons = M.tablerIcons(data);
            }) : Promise.reject(new Error('tabler'));
            reading.catch(function () { reading = null; });
        }

        return reading;
    }

    // The unlocked Tabler icon layers of a list, or none unless every one is.
    function tablerLayers(layers) {
        return (layers.length && layers.every(function (l) { return (l.type === 'svg') && l.tabler && !l.locked && M.tablerPaint(l.markup); })) ? layers : [];
    }

    function preview(d) {
        var svg = document.createElementNS(SVGNS, 'svg');

        svg.setAttribute('viewBox', '0 0 ' + M.TABLER_BOX + ' ' + M.TABLER_BOX);
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.innerHTML = M.tablerMarkup(d, 'currentColor', 2);

        return svg;
    }

    // ---- the Icons section ----

    // The set switch over the icon search; none while Change replaces a
    // Bootstrap icon.
    Editor.prototype.iconSetSwitch = function () {
        var self = this, st = this.glyphs, seg = el('div', { class: 'pgie-seg pgie-iconsets', role: 'group', 'aria-label': t('tablerSet') });

        if (this.swapTarget()) {
            return null;
        }

        [['bi', 'tablerSetBi'], ['tabler', 'tablerSetTabler']].forEach(function (set) {
            var on = (st.set === set[0]);

            seg.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: t(set[1]), onclick: function () {
                st.set = set[0];
                self.renderPanel();
            } }));
        });

        return seg;
    };

    // Tabler's tiles for the query, the file read first when needed.
    Editor.prototype.tablerFill = function (grid, note, query) {
        var self = this;

        if (!icons) {
            readIcons().then(function () {
                if (grid.isConnected) {
                    self.tablerFill(grid, note, self.glyphs.query);
                }
            }, function () {
                note.textContent = t('glyphIconError');
            });

            return;
        }

        var r = M.tablerSearch(icons, query, SHOWN);

        grid.textContent = '';
        note.textContent = '';
        r.icons.forEach(function (icon) {
            grid.appendChild(el('button', { type: 'button', class: 'pgie-glyph pgie-glyph-tb', title: icon.name, 'aria-label': icon.name, onclick: function () {
                self.addTabler(icon.name, icon.d);
            } }, [preview(icon.d)]));
        });

        // Tabler's names are English, as Bootstrap's.
        if (!r.found) {
            note.textContent = t('glyphIconNone') + ' ' + t('glyphIconEnglish');
        } else if (r.found > SHOWN) {
            note.textContent = t('glyphIconMore');
        }
    };

    // ---- adding and painting ----

    // An icon in the middle of what the stage shows, selected; one undo step.
    Editor.prototype.addTabler = function (name, d) {
        var l = M.tablerLayer(name, d, this.viewRect(), this.stickerCentre(), COLOR);

        l.name = t('glyphIcon') + ' · ' + name;
        this.addLayers([l], 0);
    };

    // A new colour (null keeps it) and width (undefined keeps it) on the
    // Tabler icon layers named; one undo step, or one per drag when live.
    Editor.prototype.paintTabler = function (ids, color, width, live) {
        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if ((ids.indexOf(l.id) >= 0) && l.tabler) {
                    l.markup = M.tablerRepaint(l.markup, color, width);
                }
            });
        }, live ? 'tabler-' + ((color === null) ? 'width' : 'color') : null, !!live);
    };

    // The colour and stroke width of selected Tabler icons: Select's panel
    // and the Sticker tool's.
    Editor.prototype.tablerPanel = function (body, picked) {
        var self = this, ids = picked.map(function (l) { return l.id; }), first = M.tablerPaint(picked[0].markup);
        var value = el('span', { class: 'pgie-val', text: String(first.width) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(M.TABLER_WIDTH[0]), max: String(M.TABLER_WIDTH[1]), step: String(M.TABLER_STEP),
            value: String(first.width), 'aria-label': t('tablerWidth') });

        body.appendChild(X.swatches(t('glyphColor'), first.color, function (c, live) {
            self.paintTabler(ids, c, undefined, live);

            if (!live) {
                self.history.key = null;
            }
        }));

        slider.addEventListener('input', function () {
            value.textContent = slider.value;
            self.paintTabler(ids, null, Number(slider.value), true);
        });
        slider.addEventListener('change', function () { self.history.key = null; });
        body.appendChild(section(t('tablerWidth'), slider, value));
    };

    // The Sticker tool's panel: the colour and width of selected Tabler
    // icons first, then the rest.
    var stickerPanel = Editor.panels.sticker;

    Editor.panels.sticker = function (body) {
        var picked = tablerLayers(this.selected());

        if (picked.length) {
            this.tablerPanel(body, picked);
        }

        stickerPanel.call(this, body);
    };

    // Select's panel for Tabler icons: their colour and width instead of an
    // svg layer's fill and outline.
    var svgPanel = Editor.prototype.svgPanel;

    Editor.prototype.svgPanel = function (body, layers) {
        var picked = tablerLayers(layers);

        if (picked.length) {
            this.tablerPanel(body, picked);
        } else {
            svgPanel.call(this, body, layers);
        }
    };
}(window.PgImageEditor.parts));
