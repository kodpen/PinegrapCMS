/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: element sets at the top of the Sticker tool. The section
 * "Elements" shows a chip per set (Lines, Badges, Ribbons, Blobs, Arrows,
 * Speech, Ornaments) and that set's shapes, four a row; a press puts the
 * shape in the middle of the stage as an svg path layer, selected. With
 * elements selected, their colour and, on a stroked one, its width.
 *
 * The shapes are a file (assets/canvas/elements.json, config elements)
 * read when the Sticker panel first opens and checked by the model's
 * M.elementSets(); the markup, its paint and the placed layer are
 * M.elementMarkup(), M.elementPaint(), M.elementRepaint() and
 * M.elementLayer(). A part of window.PgImageEditor bundled after the svg
 * layers and the icons; it puts its section before the Sticker tool's own
 * panel (docs/memory/ui/image-editor-parts.md).
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

    // The checked elements: null until read, then a list (empty when the
    // file could not be read); one read a page.
    var elements = null, reading = null;

    function readElements() {
        var url = X.config().elements;

        if (!reading) {
            reading = (url && window.fetch) ? window.fetch(url, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) {
                    throw new Error('elements');
                }

                return r.json();
            }).then(function (data) {
                elements = M.elementSets(data);
            }, function () {
                elements = [];
            }) : Promise.resolve().then(function () { elements = []; });
        }

        return reading;
    }

    // The checked elements read so far (the photo frames take the badges).
    X.elementList = function () {
        return elements || [];
    };

    // The unlocked element layers of a list, or none unless every one is.
    function elementLayers(layers) {
        return (layers.length && layers.every(function (l) { return (l.type === 'svg') && l.element && !l.locked && M.elementPaint(l.markup); })) ? layers : [];
    }

    // An element in the middle of what the stage shows, selected; one undo
    // step.
    Editor.prototype.addElement = function (e) {
        var l = M.elementLayer(e, this.viewRect(), this.stickerCentre(), COLOR);

        l.name = t(e.label);
        this.addLayers([l], 0);
    };

    // A new colour (null keeps it) and width (undefined keeps it) on the
    // element layers named; one undo step, or one per drag when live.
    Editor.prototype.paintElements = function (ids, color, width, live) {
        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if ((ids.indexOf(l.id) >= 0) && l.element) {
                    l.markup = M.elementRepaint(l.markup, color, width);
                }
            });
        }, live ? 'element-' + ((color === null) ? 'width' : 'color') : null, !!live);
    };

    // The colour of selected elements and, when each is stroked, the width:
    // Select's panel and the Sticker tool's.
    Editor.prototype.elementPanel = function (body, picked) {
        var self = this, ids = picked.map(function (l) { return l.id; }), first = M.elementPaint(picked[0].markup);
        var stroked = picked.every(function (l) { return M.elementPaint(l.markup).width !== null; });

        body.appendChild(X.swatches(t('elementColor'), first.color, function (c, live) {
            self.paintElements(ids, c, undefined, live);

            if (!live) {
                self.history.key = null;
            }
        }));

        if (stroked) {
            var value = el('span', { class: 'pgie-val', text: String(first.width) });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: String(M.ELEMENT_WIDTH[0]), max: String(M.ELEMENT_WIDTH[1]), step: '1', value: String(first.width), 'aria-label': t('elementWidth') });

            slider.addEventListener('input', function () {
                value.textContent = slider.value;
                self.paintElements(ids, null, Number(slider.value), true);
            });
            slider.addEventListener('change', function () { self.history.key = null; });
            body.appendChild(section(t('elementWidth'), slider, value));
        }
    };

    function preview(e) {
        var svg = document.createElementNS(SVGNS, 'svg');

        svg.setAttribute('viewBox', '0 0 ' + e.w + ' ' + e.h);
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.innerHTML = M.elementMarkup(e, 'currentColor');

        return svg;
    }

    Editor.prototype.elementTiles = function (holder) {
        var self = this, set = this.elementSet;

        holder.textContent = '';

        if (!elements.length) {
            holder.appendChild(el('p', { class: 'pgie-note', text: t('elementsError') }));

            return;
        }

        elements.forEach(function (e) {
            if (e.set === set) {
                holder.appendChild(el('button', { type: 'button', class: 'pgie-elb', title: t(e.label), 'aria-label': t(e.label), onclick: function () {
                    self.addElement(e);
                } }, [preview(e)]));
            }
        });
    };

    // "Elements": a chip per set and the chosen set's shapes.
    Editor.prototype.elementsSection = function (body) {
        var self = this, chips = el('div', { class: 'pgie-seg pgie-gf-cats', role: 'group', 'aria-label': t('elements') });
        var tiles = el('div', { class: 'pgie-els', role: 'group', 'aria-label': t('elementSet_' + this.elementSet) });

        M.ELEMENT_SETS.forEach(function (key) {
            var on = (key === self.elementSet);

            chips.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: t('elementSet_' + key), onclick: function () {
                self.elementSet = key;
                self.renderPanel();
            } }));
        });

        body.appendChild(section(t('elements'), el('div', { class: 'pgie-elbox' }, [chips, tiles])));

        if (elements) {
            this.elementTiles(tiles);
        } else {
            readElements().then(function () {
                if (tiles.isConnected) {
                    self.elementTiles(tiles);
                }
            });
        }
    };

    // The section first, then the colour and width of selected elements,
    // then the Sticker tool's own panel.
    var stickerPanel = Editor.panels.sticker;

    Editor.panels.sticker = function (body) {
        var picked = elementLayers(this.selected());

        this.elementsSection(body);

        if (picked.length) {
            this.elementPanel(body, picked);
        }

        stickerPanel.call(this, body);
    };

    // Select's panel for element layers: their colour and width instead of
    // an svg layer's fill and outline.
    var svgPanel = Editor.prototype.svgPanel;

    Editor.prototype.svgPanel = function (body, layers) {
        var picked = elementLayers(layers);

        if (picked.length) {
            this.elementPanel(body, picked);
        } else {
            svgPanel.call(this, body, layers);
        }
    };

    Editor.inits.push(function () {
        this.elementSet = M.ELEMENT_SETS[0];
    });
}(window.PgImageEditor.parts));
