/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: text layers: typing in place, fonts, sizes, colours, a background
 * box, outline and shadow.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * The stage and the saved overlay draw a text from one layout
 * (PgImageEditorModel.textLayout()) measured on one canvas, so what is seen
 * is what is saved (docs/memory/ui/image-editor-text.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, icon = X.icon, section = X.section, button = X.button;

    var COLORS = ['#ffffff', '#1a1c1e', '#e53935', '#fb8c00', '#fdd835', '#43a047', '#1e88e5', '#8e24aa'];
    var BACKGROUNDS = [['', 'textNone'], ['#ffffff', 'textWhite'], ['#000000', 'textDark'], ['rgba(0,0,0,0.5)', 'textSoft']];
    var OUTLINES = [['', 'textNone'], ['#000000', 'textDark'], ['#ffffff', 'textWhite']];
    var SHADOWS = {
        soft: { color: 'rgba(0,0,0,0.55)', blur: 0.12, dx: 0.03, dy: 0.05 },
        hard: { color: 'rgba(0,0,0,0.8)', blur: 0, dx: 0.05, dy: 0.06 },
        glow: { color: 'rgba(255,255,255,0.9)', blur: 0.18, dx: 0, dy: 0 }
    };
    var FONTS = [['sans', 'textFontSans'], ['serif', 'textFontSerif'], ['hand', 'textFontHand'], ['display', 'textFontDisplay'], ['mono', 'textFontMono']];

    X.icons.textBold = 'M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z';
    X.icons.textItalic = 'M10 5h8M6 19h8M14 5l-4 14';
    X.icons.textLeft = 'M4 6h16M4 10h10M4 14h16M4 18h10';
    X.icons.textCenter = 'M4 6h16M7 10h10M4 14h16M7 18h10';
    X.icons.textRight = 'M4 6h16M10 10h10M4 14h16M10 18h10';

    var measureCtx = null, metricsCache = {}, linked = {}, serial = 0;
    // The site's font families: null until asked, then a list.
    var siteFamilies = null;

    // ---- measuring ----

    function context() {
        if (!measureCtx) {
            measureCtx = document.createElement('canvas').getContext('2d');
        }

        return measureCtx;
    }

    function measure(font, s) {
        var g = context();

        g.font = font;

        return g.measureText(s).width;
    }

    // The font's own ascent and descent (one for every line), where the
    // browser tells them.
    function metrics(font, size) {
        if (!metricsCache[font]) {
            var g = context(), m;

            g.font = font;
            m = g.measureText('Hg');
            metricsCache[font] = (m.fontBoundingBoxAscent > 0) ? { ascent: m.fontBoundingBoxAscent, descent: m.fontBoundingBoxDescent || 0 } : { ascent: size * 0.8, descent: size * 0.2 };
        }

        return metricsCache[font];
    }

    function layout(l) {
        return M.textLayout(l, measure, metrics);
    }

    function same(a, b) {
        return String(a || '').replace(/\s/g, '').toLowerCase() === String(b || '').replace(/\s/g, '').toLowerCase();
    }

    // ---- fonts ----

    // A site font comes from Google Fonts as on the site's pages: its
    // stylesheet is linked once, then the browser loads the face asked.
    function linkFamily(name) {
        if (!linked[name]) {
            linked[name] = new Promise(function (resolve) {
                var link = document.createElement('link');

                link.rel = 'stylesheet';
                link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(name) + ':ital,wght@0,300;0,400;0,600;0,700;0,900;1,400;1,700&display=swap';
                link.onload = resolve;
                link.onerror = resolve;
                document.head.appendChild(link);
            });
        }

        return linked[name];
    }

    // The face a layer needs, loaded once; the stage is drawn again when it
    // comes, as the letters' widths change.
    Editor.prototype.loadTextFont = function (l) {
        var self = this, font = M.textFont(l), family = String(l.font || ''), key = font + '|' + family, name;

        if (this.textFonts[key]) {
            return this.textFonts[key];
        }

        name = (family.slice(0, 2) === 'g:') ? M.textFamilyName(family.slice(2)) : '';

        this.textFonts[key] = (name ? linkFamily(name) : Promise.resolve()).then(function () {
            return (document.fonts && document.fonts.load) ? document.fonts.load(font, l.text || 'Aa') : null;
        }).catch(function () {
            // A face that does not load falls back to the next family.
            return null;
        }).then(function (faces) {
            if (faces && faces.length) {
                metricsCache = {};
                self.layersDirty = true;
                self.renderView();
            }
        });

        return this.textFonts[key];
    };

    // The site's fonts (the families its styles load), asked once a page.
    Editor.prototype.siteFonts = function () {
        var self = this;

        if (siteFamilies) {
            return siteFamilies;
        }

        siteFamilies = [];

        if (X.config().api) {
            this.request({ action: 'text_fonts' }).then(function (res) {
                siteFamilies = (res && (res.status === 'success') && Array.isArray(res.fonts)) ? res.fonts.map(M.textFamilyName).filter(Boolean) : [];

                if (siteFamilies.length && (self.tool === 'text' || self.selectedTexts().length)) {
                    self.render();
                }
            }, function () {
                // No list: the system fonts only.
            });
        }

        return siteFamilies;
    };

    // ---- drawing ----

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) {
            if ((attrs[k] !== null) && (attrs[k] !== undefined)) {
                n.setAttribute(k, String(attrs[k]));
            }
        });

        return n;
    }

    // A text on the stage. Its box keeps up with the layout (a face that
    // loaded later, another computer's widths) without an undo step; the
    // text being typed is left to the typing box.
    function textNode(l) {
        var lay = layout(l), fx = M.textEffects(l), box = M.textFit(l, lay);

        l.x = box.x;
        l.w = box.w;
        l.h = box.h;
        this.loadTextFont(l);

        if (this.textEdit && (this.textEdit.id === l.id)) {
            return null;
        }

        var outer = svg('g', { opacity: ((l.opacity !== undefined) && (l.opacity < 1)) ? l.opacity : null });
        var g = svg('g', { transform: 'translate(' + l.x + ' ' + l.y + ')' + (l.rot ? ' rotate(' + l.rot + ' ' + (l.w / 2) + ' ' + (l.h / 2) + ')' : '') });

        // The shadow falls the same way whatever the text's angle: the filter
        // sits outside the turn.
        if (fx.shadow) {
            var id = 'pgie-ts-' + (++serial), filter = svg('filter', { id: id, x: '-50%', y: '-50%', width: '200%', height: '200%', 'color-interpolation-filters': 'sRGB' });

            filter.appendChild(svg('feDropShadow', { dx: fx.shadow.dx, dy: fx.shadow.dy, stdDeviation: fx.shadow.blur / 2, 'flood-color': fx.shadow.color }));
            outer.appendChild(filter);
            outer.setAttribute('filter', 'url(#' + id + ')');
        }

        if (l.bg) {
            g.appendChild(svg('rect', { x: 0, y: 0, width: lay.w, height: lay.h, rx: fx.radius || null, fill: l.bg }));
        }

        lay.lines.forEach(function (line) {
            if (!line.text) {
                return;
            }

            var n = svg('text', {
                'font-family': M.textFamily(l.font), 'font-size': lay.size, 'font-weight': Number(l.weight) || 400,
                'font-style': l.italic ? 'italic' : null, fill: l.color || '#000000',
                stroke: fx.outline ? fx.outline.color : null, 'stroke-width': fx.outline ? fx.outline.width * 2 : null,
                'stroke-linejoin': fx.outline ? 'round' : null, 'paint-order': fx.outline ? 'stroke' : null
            });

            n.style.whiteSpace = 'pre';

            if (line.chars) {
                line.chars.forEach(function (c) {
                    var s = svg('tspan', { x: line.x + c[1], y: line.base });

                    s.textContent = c[0];
                    n.appendChild(s);
                });
            } else {
                n.setAttribute('x', line.x);
                n.setAttribute('y', line.base);
                n.textContent = line.text;
            }

            g.appendChild(n);
        });

        outer.appendChild(g);

        return outer;
    }

    // The layout drawn on a canvas in the rotated space's units, from the
    // box's top left: the background, the outline under the letters, the
    // letters.
    function drawBody(g, l, lay, fx) {
        var put = function (paint, line) {
            if (line.chars) {
                line.chars.forEach(function (c) { paint(c[0], line.x + c[1], line.base); });
            } else if (line.text) {
                paint(line.text, line.x, line.base);
            }
        };

        if (l.bg) {
            g.fillStyle = l.bg;
            g.beginPath();

            if (fx.radius && g.roundRect) {
                g.roundRect(0, 0, lay.w, lay.h, Math.min(fx.radius, lay.w / 2, lay.h / 2));
            } else {
                g.rect(0, 0, lay.w, lay.h);
            }

            g.fill();
        }

        g.font = lay.font;
        g.textBaseline = 'alphabetic';
        g.textAlign = 'left';

        if (fx.outline) {
            g.strokeStyle = fx.outline.color;
            g.lineWidth = fx.outline.width * 2;
            g.lineJoin = 'round';
            lay.lines.forEach(function (line) { put(function (s, x, y) { g.strokeText(s, x, y); }, line); });
        }

        g.fillStyle = l.color || '#000000';
        lay.lines.forEach(function (line) { put(function (s, x, y) { g.fillText(s, x, y); }, line); });
    }

    // A text on the save's canvas, drawn first on a canvas of its own so a
    // shadow falls from the whole text and the opacity covers background
    // and letters as one, as on the stage.
    function textPaint(g, l) {
        var lay = layout(l), fx = M.textEffects(l), m = g.getTransform(), k = Math.hypot(m.a, m.b) || 1;
        var margin = lay.size + (fx.outline ? fx.outline.width : 0), fw = lay.w + 2 * margin, fh = lay.h + 2 * margin;
        var c = document.createElement('canvas'), cg;

        c.width = Math.max(1, Math.min(Math.ceil(fw * k), 16000));
        c.height = Math.max(1, Math.min(Math.ceil(fh * k), 16000));
        cg = c.getContext('2d');
        cg.setTransform(c.width / fw, 0, 0, c.height / fh, 0, 0);
        cg.translate(margin, margin);
        drawBody(cg, l, lay, fx);

        // A canvas shadow is in the canvas's pixels, whatever the transform.
        if (fx.shadow) {
            g.shadowColor = fx.shadow.color;
            g.shadowBlur = fx.shadow.blur * k;
            g.shadowOffsetX = fx.shadow.dx * k;
            g.shadowOffsetY = fx.shadow.dy * k;
        }

        g.translate(l.x + lay.w / 2, l.y + lay.h / 2);
        g.rotate((l.rot || 0) * Math.PI / 180);
        g.drawImage(c, -lay.w / 2 - margin, -lay.h / 2 - margin, fw, fh);
    }

    // ---- typing in place ----

    // A new text with the style last chosen, its caret where the stage was
    // pressed; it joins the document with its first letter.
    Editor.prototype.newText = function (x, y) {
        var l = M.clone(this.textStyle), lay;

        l.size = this.textSize();
        l.type = 'text';
        l.text = '';
        l.rot = 0;
        l.opacity = 1;
        lay = layout(l);
        l.w = lay.w;
        l.h = lay.h;
        l.y = y - lay.h / 2;
        l.x = x - lay.pad - ((l.align === 'center') ? lay.w / 2 - lay.pad : ((l.align === 'right') ? lay.w - 2 * lay.pad : 0));

        return l;
    };

    // The size of a new text: the one chosen last, else a twelfth of the
    // picture's shorter side, so a small picture gets small letters.
    Editor.prototype.textSize = function () {
        var vr = this.viewRect();

        return this.textStyle.size || Math.max(12, Math.round(Math.min(vr.w, vr.h) / 12));
    };

    // Typing into layer l; a new one is l without an id.
    Editor.prototype.startTextEdit = function (l) {
        var self = this, area;

        this.endTextEdit();

        if (l.locked) {
            return;
        }

        area = el('textarea', { class: 'pgie-textedit', rows: '1', spellcheck: 'false', autocomplete: 'off', 'aria-label': t('layer_text'), placeholder: t('textPlaceholder') });
        area.value = l.text || '';
        this.textEdit = { id: l.id || null, layer: l, area: area, key: 'type-' + (++serial) };

        area.addEventListener('input', function () { self.typeText(area.value); });
        area.addEventListener('keydown', function (e) {
            // Escape and Ctrl+Enter end the typing; Enter is a new line.
            if ((e.key === 'Escape') || ((e.key === 'Enter') && (e.ctrlKey || e.metaKey))) {
                e.preventDefault();
                e.stopPropagation();
                self.endTextEdit();
                self.stage.focus({ preventScroll: true });
            }
        });
        // A press in the box places the caret, not a new text.
        area.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
        area.addEventListener('dblclick', function (e) { e.stopPropagation(); });
        // A press on the panel (a colour, a font) keeps the typing on.
        if (this.panel && !this.textPanelWatch) {
            this.textPanelWatch = true;
            this.panel.addEventListener('pointerdown', function () { self.textKeep = !!self.textEdit; }, true);
        }

        area.addEventListener('blur', function () {
            if (self.textKeep) {
                self.textKeep = false;
                return;
            }

            if (self.textEdit && (self.textEdit.area === area)) {
                self.endTextEdit();
            }
        });
        this.stage.appendChild(area);
        this.root.classList.add('pgie-typing');
        this.sel = l.id ? [l.id] : [];
        this.layersDirty = true;
        this.render();
        area.focus({ preventScroll: true });
        area.setSelectionRange(area.value.length, area.value.length);
    };

    // A letter typed: the first adds the new layer, the rest change it; the
    // whole typing is one undo step.
    Editor.prototype.typeText = function (value) {
        var ed = this.textEdit;

        if (!ed) {
            return;
        }

        if (!ed.id) {
            if (value === '') {
                return;
            }

            this.change(function (doc) {
                var l = M.clone(ed.layer);

                l.id = M.newId(doc);
                l.text = value;
                ed.id = l.id;
                doc.layers.push(l);
            }, ed.key, true);
            this.sel = [ed.id];
            this.renderPanel();
        } else {
            this.change(function (doc) {
                var l = find(doc, ed.id);

                if (l) {
                    l.text = value;
                }
            }, ed.key, true);
        }

        ed.layer = this.layer(ed.id) || ed.layer;
    };

    // The end of typing: a text left empty goes away.
    Editor.prototype.endTextEdit = function () {
        var ed = this.textEdit, l;

        if (!ed) {
            return;
        }

        this.textEdit = null;
        this.root.classList.remove('pgie-typing');

        if (ed.area.parentNode) {
            ed.area.parentNode.removeChild(ed.area);
        }

        this.history.key = null;
        this.layersDirty = true;
        l = ed.id ? this.layer(ed.id) : null;

        if (l && !String(l.text || '').trim()) {
            this.sel = this.sel.filter(function (id) { return id !== ed.id; });
            this.change(function (doc) {
                doc.layers = doc.layers.filter(function (o) { return o.id !== ed.id; });
            });
            return;
        }

        this.render();
    };

    // The typing box over the layer in the same letters at the view's zoom.
    Editor.prototype.placeTextEdit = function () {
        var ed = this.textEdit;

        if (!ed || !this.ready) {
            return;
        }

        var l = (ed.id && this.layer(ed.id)) || ed.layer, lay = layout(l), fx = M.textEffects(l), box = M.textFit(l, lay);
        var v = this.view, vr = this.viewRect(), k = v.k, s = ed.area.style;

        s.left = (v.x + (box.x - vr.x) * k) + 'px';
        s.top = (v.y + (box.y - vr.y) * k) + 'px';
        s.width = Math.ceil(lay.w * k + 2) + 'px';
        s.height = Math.ceil(lay.h * k) + 'px';
        s.padding = (lay.pad * k) + 'px';
        s.font = (l.italic ? 'italic ' : '') + (Number(l.weight) || 400) + ' ' + (lay.size * k) + 'px ' + M.textFamily(l.font);
        s.lineHeight = (lay.lineHeight * k) + 'px';
        s.letterSpacing = ((Number(l.spacing) || 0) * lay.size * k) + 'px';
        s.textAlign = l.align || 'left';
        s.color = l.color || '#000000';
        s.background = l.bg || 'transparent';
        s.borderRadius = (fx.radius * k) + 'px';
        s.transform = l.rot ? 'rotate(' + l.rot + 'deg)' : '';
        s.webkitTextStroke = fx.outline ? (fx.outline.width * k) + 'px ' + fx.outline.color : '';
        s.opacity = String((l.opacity === undefined) ? 1 : l.opacity);
    };

    // ---- the tool ----

    // A press with the Text tool: a handle or a text moves and sizes it as
    // Select does; elsewhere a new text starts. A press while typing only
    // ends the typing.
    Editor.prototype.textDown = function (e) {
        if (this.textEdit) {
            this.endTextEdit();
            return;
        }

        var p = this.toDoc(e.clientX, e.clientY), h = e.target.getAttribute && e.target.getAttribute('data-h');
        var hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k);

        if (h || (hit && (hit.type === 'text'))) {
            this.startDrag(e);
            return;
        }

        e.preventDefault();
        this.startTextEdit(this.newText(p[0], p[1]));
    };

    // A double click with the Text tool types into the text there, else
    // starts a new one; the view does not zoom.
    Editor.prototype.textDouble = function (e) {
        var p = this.toDoc(e.clientX, e.clientY);

        if (!this.editLayer(e)) {
            this.startTextEdit(this.newText(p[0], p[1]));
        }

        return true;
    };

    // A new text in the middle of the view, from the panel's button.
    Editor.prototype.addTextMiddle = function () {
        var vr = this.viewRect(), l = this.newText(vr.x + vr.w / 2, vr.y + vr.h / 2);

        l.x = vr.x + (vr.w - l.w) / 2;
        this.startTextEdit(l);
    };

    Editor.prototype.selectedTexts = function () {
        return this.selected().filter(function (l) { return l.type === 'text'; });
    };

    // A style change: to the selected texts (or the one being typed) and to
    // the next new one.
    Editor.prototype.setTextStyle = function (patch, key, viewOnly) {
        var self = this, ed = this.textEdit, ids = this.selectedTexts().filter(function (l) { return !l.locked; }).map(function (l) { return l.id; });

        Object.keys(patch).forEach(function (k) {
            self.textStyle[k] = M.clone(patch[k]);

            if (ed && !ed.id) {
                ed.layer[k] = M.clone(patch[k]);
            }
        });

        if (ids.length) {
            this.change(function (doc) {
                ids.forEach(function (id) {
                    var l = find(doc, id);

                    Object.keys(patch).forEach(function (k) { l[k] = M.clone(patch[k]); });
                });
            }, key || null, viewOnly);
        } else if (!viewOnly) {
            this.renderPanel();
        }

        this.placeTextEdit();

        // A button pressed while typing gives the caret back to the text.
        if (ed && !viewOnly && (this.textEdit === ed)) {
            ed.area.focus({ preventScroll: true });
        }
    };

    // A slider: now the value, shown(n) its label, to(n) the change.
    Editor.prototype.textSlider = function (label, range, now, shown, to, key) {
        var self = this, out = el('span', { class: 'pgie-val', text: shown(now) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(range[0]), max: String(range[1]), step: String(range[2] || 1), value: String(now), 'aria-label': label });

        slider.addEventListener('input', function () {
            var n = Number(slider.value);

            out.textContent = shown(n);
            self.setTextStyle(to(n), key, true);
        });
        // Letting go ends the undo step.
        slider.addEventListener('change', function () { self.history.key = null; });

        return section(label, slider, out);
    };

    // A row of choices [[value, label key]].
    function choices(list, now, pick) {
        var seg = el('div', { class: 'pgie-seg', role: 'group' });

        list.forEach(function (c) {
            var on = same(c[0], now);

            seg.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: t(c[1]), onclick: function () { pick(c[0]); } }));
        });

        return seg;
    }

    // The colour swatches and a colour of one's own; pick(colour, live)
    // with live true while the colour picker is being dragged.
    function colorRow(now, pick, label) {
        var row = el('div', { class: 'pgie-tcolors', role: 'group', 'aria-label': label });
        var custom = el('input', { type: 'color', class: 'pgie-tcolor pgie-tcolor-own', title: t('textOwnColor'), 'aria-label': t('textOwnColor'), value: /^#[0-9a-f]{6}$/i.test(now) ? now : '#000000' });

        COLORS.forEach(function (c) {
            var on = same(now, c);

            row.appendChild(el('button', { type: 'button', class: 'pgie-tcolor' + (on ? ' on' : ''), style: 'background: ' + c, title: c, 'aria-label': c, 'aria-pressed': on ? 'true' : 'false', onclick: function () { pick(c, false); } }));
        });

        custom.addEventListener('input', function () { pick(custom.value, true); });
        custom.addEventListener('change', function () { pick(custom.value, false); });
        row.appendChild(custom);

        return row;
    }

    function px(size) {
        return function (n) { return Math.round(n * size / 100) + ' px'; };
    }

    // The text controls: for the selected texts, else for the next new one.
    Editor.prototype.textControls = function (body, layers) {
        var self = this, l = (layers && layers[0]) || this.textStyle, size = Number(l.size) || this.textSize(), weight = Number(l.weight) || 400;
        var set = function (patch, key, viewOnly) { self.setTextStyle(patch, key, viewOnly); };
        var fonts = el('div', { class: 'pgie-tfonts', role: 'group', 'aria-label': t('textFont') });
        var style = el('div', { class: 'pgie-seg', role: 'group', 'aria-label': t('textStyle') });
        var toggle = function (on, label, iconName, patch) {
            return el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', title: label, 'aria-label': label, onclick: function () { set(patch); } }, [icon(iconName)]);
        };

        body.appendChild(section(t('textColor'), colorRow(l.color, function (c, live) { set({ color: c }, live ? 'text-color' : null, live); }, t('textColor'))));

        FONTS.map(function (f) {
            return { value: f[0], label: t(f[1]) };
        }).concat(this.siteFonts().map(function (name) {
            return { value: 'g:' + name, label: name, site: true };
        })).forEach(function (f) {
            var on = (l.font === f.value), sample = el('b', { text: 'Aa' });

            sample.style.fontFamily = M.textFamily(f.value);

            if (f.site) {
                self.loadTextFont({ font: f.value, size: 20, weight: 400, text: 'Aa' });
            }

            fonts.appendChild(el('button', { type: 'button', class: 'pgie-tfont' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', title: f.label, onclick: function () {
                set({ font: f.value });
            } }, [sample, el('span', { text: f.label })]));
        });
        body.appendChild(section(t('textFont'), fonts));

        body.appendChild(this.textSlider(t('textSize'), [4, Math.max(200, Math.round(Math.max(this.viewRect().h * 0.6, size)))], Math.round(size), function (n) { return n + ' px'; }, function (n) { return { size: n }; }, 'text-size'));

        style.appendChild(toggle(weight >= 600, t('textBold'), 'textBold', { weight: (weight >= 600) ? 400 : 700 }));
        style.appendChild(toggle(!!l.italic, t('textItalic'), 'textItalic', { italic: !l.italic }));
        style.appendChild(toggle((l.align || 'left') === 'left', t('textAlignLeft'), 'textLeft', { align: 'left' }));
        style.appendChild(toggle(l.align === 'center', t('textAlignCenter'), 'textCenter', { align: 'center' }));
        style.appendChild(toggle(l.align === 'right', t('textAlignRight'), 'textRight', { align: 'right' }));
        body.appendChild(section(t('textStyle'), style));

        body.appendChild(section(t('textBackground'), choices(BACKGROUNDS, l.bg || '', function (v) { set({ bg: v }); })));

        body.appendChild(el('button', { type: 'button', class: 'pgie-more' + (this.textMore ? ' on' : ''), 'aria-expanded': this.textMore ? 'true' : 'false', onclick: function () {
            self.textMore = !self.textMore;
            self.renderPanel();
        } }, [el('span', { text: t('details') }), icon('chev')]));

        if (this.textMore) {
            this.textDetails(body, l, set);
        }
    };

    // Under Details: weight, line height, letter spacing, the background's
    // colour, padding and corners, outline, shadow.
    Editor.prototype.textDetails = function (body, l, set) {
        var size = Number(l.size) || this.textSize(), outline = l.outline || null, shadow = '';
        var weights = el('select', { class: 'pgie-input pgie-textweight', 'aria-label': t('textWeight') });

        M.TEXT_WEIGHTS.forEach(function (w) {
            weights.appendChild(el('option', { value: String(w), text: t('textWeight' + w), selected: (Number(l.weight) || 400) === w }));
        });
        weights.addEventListener('change', function () { set({ weight: Number(weights.value) }); });
        body.appendChild(section(t('textWeight'), weights));

        body.appendChild(this.textSlider(t('textLineHeight'), [80, 300, 5], Math.round((Number(l.lineHeight) || 1.25) * 100), function (n) { return (n / 100).toFixed(2).replace('.', (X.config().decimal || '.')); }, function (n) { return { lineHeight: n / 100 }; }, 'text-line'));
        body.appendChild(this.textSlider(t('textSpacing'), [-10, 50], Math.round((Number(l.spacing) || 0) * 100), px(size), function (n) { return { spacing: n / 100 }; }, 'text-spacing'));

        if (l.bg) {
            body.appendChild(section(t('textBackgroundColor'), colorRow(l.bg, function (c, live) { set({ bg: c }, live ? 'text-bg' : null, live); }, t('textBackgroundColor'))));
            body.appendChild(this.textSlider(t('textPadding'), [0, 100], Math.round((Number(l.pad) || 0) * 100), px(size), function (n) { return { pad: n / 100 }; }, 'text-pad'));
            body.appendChild(this.textSlider(t('textRadius'), [0, 100], Math.round((Number(l.radius) || 0) * 100), px(size), function (n) { return { radius: n / 100 }; }, 'text-radius'));
        }

        body.appendChild(section(t('textOutline'), choices(OUTLINES, outline ? outline.color : '', function (v) {
            set({ outline: v ? { color: v, width: outline ? outline.width : 0.04 } : null });
        })));

        if (outline) {
            body.appendChild(this.textSlider(t('textOutlineWidth'), [1, 20], Math.round((Number(outline.width) || 0.04) * 100), px(size), function (n) {
                return { outline: { color: outline.color, width: n / 100 } };
            }, 'text-outline'));
        }

        Object.keys(SHADOWS).forEach(function (name) {
            if (l.shadow && M.sameDocument(l.shadow, SHADOWS[name])) {
                shadow = name;
            }
        });
        body.appendChild(section(t('textShadow'), choices([['', 'textNone'], ['soft', 'textSoft'], ['hard', 'textShadowHard'], ['glow', 'textShadowGlow']], shadow, function (v) {
            set({ shadow: v ? M.clone(SHADOWS[v]) : null });
        })));
    };

    // The Text tool's panel: a note, Add text, then the controls.
    Editor.prototype.textPanel = function (body) {
        var self = this, texts = this.selectedTexts();

        body.appendChild(el('p', { class: 'pgie-note', text: t(texts.length ? 'textNoteEdit' : 'textNoteAdd') }));
        body.appendChild(button('pgie-btn', t('textAdd'), 'plus', function () { self.addTextMiddle(); }, true));
        this.textControls(body, texts.length ? texts : null);
    };

    // Enter on one selected text types into it.
    Editor.prototype.textKey = function (e, mod, k) {
        var picked = this.selected(), tag = (e.target && e.target.tagName) || '';

        if (mod || (k !== 'Enter') || (Editor.selectTools.indexOf(this.tool) < 0) || /^(BUTTON|A|SUMMARY|SELECT|INPUT)$/.test(tag)) {
            return false;
        }

        if ((picked.length === 1) && (picked[0].type === 'text') && !picked[0].locked) {
            this.startTextEdit(picked[0]);
            return true;
        }

        return false;
    };

    Editor.inits.push(function () {
        this.textEdit = null;
        this.textKeep = false;
        this.textPanelWatch = false;
        this.textMore = false;
        this.textFonts = {};
        this.textStyle = M.textDefaults();

        // On a blank canvas a text starts dark; a caller's mini bar marks in
        // red on a white box.
        if (this.opts.blank) {
            this.textStyle.color = '#1a1c1e';
        } else if (this.tools.mini) {
            this.textStyle.color = '#e53935';
            this.textStyle.bg = '#ffffff';
            this.textStyle.size = 32;
        }
    });

    Editor.layerTypes.text = {
        node: textNode,
        paint: textPaint,
        ratio: true,
        ready: function (l) { return this.loadTextFont(l); },
        name: function (l) {
            var s = String(l.text || '').replace(/\s+/g, ' ').trim();

            return (s.length > 32) ? s.slice(0, 31) + '…' : s;
        },
        edit: function (l) { this.startTextEdit(l); },
        resize: function (start, box, h) { return M.textResize(start, box, h); },
        panel: function (body, layers) { this.textControls(body, layers); }
    };

    Editor.selectTools.push('text');
    Editor.panels.text = Editor.prototype.textPanel;
    Editor.hints.text = 'hintText';
    Editor.pointers.text = { down: Editor.prototype.textDown, move: Editor.prototype.moveDrag, up: Editor.prototype.endDrag, double: Editor.prototype.textDouble };
    Editor.keys.push(Editor.prototype.textKey);
    Editor.views.push(Editor.prototype.placeTextEdit);
}(window.PgImageEditor.parts));
