/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: Bootstrap Icons and emoji in the Sticker tool, as glyph
 * layers.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, config = X.config, swatches = X.swatches;

    // The icon font under a name of its own, so the editor draws icons the
    // same on a site page that does not load the panel's icon stylesheet.
    var ICON_FAMILY = 'pgie-bi';
    var EMOJI_FAMILY = '"Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", "Segoe UI Symbol", sans-serif';
    var STORE = 'pgie-recent-emoji';
    var SHOWN = 120;

    var iconFont = null, iconMap = null;

    function icons() {
        return config().icons || {};
    }

    // The icon font, loaded once per page; a failed load is tried again on
    // the next call.
    function loadIconFont() {
        if (!iconFont) {
            iconFont = (window.FontFace && icons().font)
                ? new window.FontFace(ICON_FAMILY, 'url("' + icons().font + '") format("woff2")').load().then(function (face) {
                    document.fonts.add(face);
                    return face;
                })
                : Promise.reject(new Error('icon font'));
            iconFont.catch(function () { iconFont = null; });
        }

        return iconFont;
    }

    // The icons' name => code point map (the font's own JSON), once per page.
    function loadIconMap() {
        if (!iconMap) {
            iconMap = icons().map ? fetch(icons().map, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) {
                    throw new Error('icon map');
                }

                return r.json();
            }) : Promise.reject(new Error('icon map'));
            iconMap.catch(function () { iconMap = null; });
        }

        return iconMap;
    }

    function family(l) {
        return (l.set === 'bi') ? ICON_FAMILY : EMOJI_FAMILY;
    }

    // A glyph as SVG in the rotated space: its character in the middle of
    // its box, turned around its centre.
    function glyphNode(l) {
        var cx = l.x + l.w / 2, cy = l.y + l.h / 2, g = document.createElementNS(SVGNS, 'g'), text = document.createElementNS(SVGNS, 'text');

        if (l.rot) {
            g.setAttribute('transform', 'rotate(' + l.rot + ' ' + cx + ' ' + cy + ')');
        }

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            g.setAttribute('opacity', String(l.opacity));
        }

        text.setAttribute('x', String(cx));
        text.setAttribute('y', String(cy));
        text.setAttribute('font-family', family(l));
        text.setAttribute('font-size', String(M.glyphSize(l)));
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('dominant-baseline', 'central');

        if (l.set === 'bi') {
            text.setAttribute('fill', l.color || '#000000');
        }

        text.textContent = M.glyphText(l);
        g.appendChild(text);

        return g;
    }

    // The same glyph onto the save's canvas, in rotated-space units.
    function glyphPaint(g, l) {
        var cx = l.x + l.w / 2, cy = l.y + l.h / 2;

        g.translate(cx, cy);
        g.rotate((l.rot || 0) * Math.PI / 180);
        g.font = M.glyphSize(l) + 'px ' + family(l);
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.fillStyle = (l.set === 'bi') ? (l.color || '#000000') : '#000000';
        g.fillText(M.glyphText(l), 0, 0);
    }

    // ---- adding ----

    // A glyph in the middle of what the stage shows, a fifth of the
    // picture's shorter side, selected.
    Editor.prototype.addGlyph = function (set, code) {
        var vr = this.viewRect(), size = Math.min(vr.w, vr.h) / 5, c = this.stickerCentre();
        var layer = { type: 'glyph', set: set, code: code, x: c[0] - size / 2, y: c[1] - size / 2, w: size, h: size, rot: 0, opacity: 1 };

        if (this.swapGlyph({ type: 'glyph', set: set, code: code })) {
            if (set === 'emoji') {
                this.rememberEmoji(code);
            }

            return;
        }

        if (set === 'bi') {
            layer.color = '#212529';
        } else {
            this.rememberEmoji(code);
        }

        this.addLayers([layer], 0);
    };

    Editor.prototype.recentEmoji = function () {
        var list = [];

        try {
            list = JSON.parse(window.localStorage.getItem(STORE) || '[]');
        } catch (e) {
            list = [];
        }

        return M.emojiRecent(list, '');
    };

    Editor.prototype.rememberEmoji = function (emoji) {
        try {
            window.localStorage.setItem(STORE, JSON.stringify(M.emojiRecent(this.recentEmoji(), emoji)));
        } catch (e) {
            // Browser storage refused: the recent row stays as it was.
        }
    };

    // ---- the Sticker tool's sections ----

    // An open/close button for a section of the Sticker panel.
    function opener(open, label, toggle) {
        return el('button', { type: 'button', class: 'pgie-btn pgie-stkopen', 'aria-expanded': open ? 'true' : 'false', onclick: toggle },
            [X.icon(open ? 'minus' : 'plus'), el('span', { text: label })]);
    }

    function glyphButton(text, label, set, onclick) {
        return el('button', { type: 'button', class: 'pgie-glyph' + ((set === 'bi') ? ' pgie-glyph-bi' : ''), title: label, 'aria-label': label, onclick: onclick }, [
            el('span', { 'aria-hidden': 'true', text: text })
        ]);
    }

    // "Icons": a search box over the Bootstrap Icons names and the first
    // matches; the font and the map load when the section opens.
    Editor.prototype.glyphIconSection = function (body) {
        var self = this, st = this.glyphs, timer = null;
        var toggle = opener(st.icons, t('glyphIcons'), function () {
            st.icons = !st.icons;
            self.renderPanel();
        });

        if (!st.icons) {
            body.appendChild(toggle);
            return;
        }

        var grid = el('div', { class: 'pgie-glyphs', role: 'group', 'aria-label': t('glyphIcons') });
        var search = el('input', { type: 'search', class: 'pgie-input pgie-glyphfind', value: st.query, placeholder: t('glyphIconFind'), 'aria-label': t('glyphIconFind'), autocomplete: 'off', spellcheck: 'false' });
        var note = el('p', { class: 'pgie-note' });

        var fill = function () {
            grid.textContent = '';
            note.textContent = '';

            if (st.error) {
                note.textContent = t('glyphIconError');
                return;
            }

            if (!st.map) {
                return;
            }

            var r = M.iconSearch(st.map, st.query, SHOWN);

            r.icons.forEach(function (icon) {
                grid.appendChild(glyphButton(String.fromCodePoint(icon.code), icon.name, 'bi', function () { self.addGlyph('bi', icon.code); }));
            });

            // The search reads the Bootstrap Icons names, which are English:
            // a search in the panel's language finds nothing, so say so.
            if (!r.found) {
                note.textContent = t('glyphIconNone') + ' ' + t('glyphIconEnglish');
            } else if (r.found > SHOWN) {
                note.textContent = t('glyphIconMore');
            }
        };

        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                st.query = search.value;
                fill();
            }, 200);
        });

        if (!st.map && !st.error) {
            Promise.all([loadIconMap(), loadIconFont()]).then(function (r) {
                st.map = r[0];
                fill();
            }, function () {
                st.error = true;
                fill();
            });
        }

        fill();
        body.appendChild(el('div', { class: 'pgie-stkfilebox' }, [toggle, search, grid, note]));
    };

    // "Emoji": a recent row and the set by category.
    Editor.prototype.glyphEmojiSection = function (body) {
        var self = this, st = this.glyphs;
        var toggle = opener(st.emoji, t('glyphEmoji'), function () {
            st.emoji = !st.emoji;
            self.renderPanel();
        });

        if (!st.emoji) {
            body.appendChild(toggle);
            return;
        }

        var box = el('div', { class: 'pgie-stkfilebox' }, [toggle]);
        var recent = this.recentEmoji();
        var add = function (e) { return function () { self.addGlyph('emoji', e); }; };

        if (recent.length) {
            box.appendChild(el('div', { class: 'pgie-glyphs pgie-glyph-recent', role: 'group', 'aria-label': t('glyphRecent') },
                recent.map(function (e) { return glyphButton(e, e, 'emoji', add(e)); })));
        }

        var cats = el('div', { class: 'pgie-seg pgie-gf-cats', role: 'group', 'aria-label': t('glyphEmojiCategory') });

        M.EMOJI.order.forEach(function (key) {
            var on = (key === st.category);

            cats.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: t('emojiCat_' + key), onclick: function () {
                st.category = key;
                self.renderPanel();
            } }));
        });

        box.appendChild(cats);
        box.appendChild(el('div', { class: 'pgie-glyphs pgie-glyph-emoji', role: 'group', 'aria-label': t('emojiCat_' + st.category) },
            (M.EMOJI.categories[st.category] || []).map(function (e) { return glyphButton(e, e, 'emoji', add(e)); })));
        body.appendChild(box);
    };

    // ---- changing a placed one ----

    // The one selected sticker or glyph that a pick in the Sticker panel
    // replaces while Change is on; null otherwise.
    Editor.prototype.swapTarget = function () {
        var l = (this.swapId && (this.tool === 'sticker') && (this.sel.length === 1) && (this.sel[0] === this.swapId)) ? this.layer(this.swapId) : null;

        return (l && !l.locked && ((l.type === 'sticker') || (l.type === 'glyph'))) ? l : null;
    };

    // A pick while Change is on: that layer's sticker, icon or emoji is
    // replaced in one undo step (M.swapGlyph()); false when nothing is
    // being changed, so the pick adds a layer.
    Editor.prototype.swapGlyph = function (pick) {
        var l = this.swapTarget(), next = M.swapGlyph(l, pick, (l && (l.type === 'sticker')) ? this.stickerKindText(l.kind) : '');

        if (!next) {
            return false;
        }

        this.swapId = null;
        this.change(function (doc) {
            doc.layers = doc.layers.map(function (o) { return (o.id === next.id) ? next : o; });
        });

        return true;
    };

    // Change for one selected sticker or glyph: the Sticker tool's panel
    // with that layer's icon or emoji section open; a pick replaces it.
    Editor.prototype.swapButton = function (body, picked) {
        var self = this, l = picked[0];

        if ((picked.length !== 1) || l.locked || this.swapTarget()) {
            return;
        }

        body.appendChild(el('div', { class: 'pgie-btnrow' }, [X.button('pgie-btn pgie-swap', t('glyphChange'), 'sticker', function () {
            self.swapId = l.id;

            if (l.type === 'glyph') {
                self.glyphs[(l.set === 'bi') ? 'icons' : 'emoji'] = true;
            }

            if (self.tool !== 'sticker') {
                self.pickTool('sticker');
            } else {
                self.renderPanel();
            }

            if (self.panel) {
                self.panel.scrollTop = 0;
            }
        }, true)]));
    };

    // While Change is on, the Sticker panel says so first, with Cancel.
    function swapNote(body) {
        var self = this;

        body.insertBefore(el('div', { class: 'pgie-swapnote', role: 'status' }, [
            el('p', { class: 'pgie-note', text: t('glyphChangeHint') }),
            el('button', { type: 'button', class: 'pgie-btn', text: t('cancel'), onclick: function () {
                self.swapId = null;
                self.renderPanel();
            } })
        ]), body.firstChild);
    }

    // A sticker's own panel starts with Change.
    var stickerOwn = Editor.prototype.stickerPanel;

    Editor.prototype.stickerPanel = function (body, picked) {
        this.swapButton(body, picked);
        stickerOwn.call(this, body, picked);
    };

    // The colour of selected icons (an emoji keeps its own colours): Select's
    // panel and the Sticker tool's.
    Editor.prototype.glyphPanel = function (body, picked) {
        var self = this, tinted = picked.filter(function (l) { return l.set === 'bi'; });

        this.swapButton(body, picked);

        if (!tinted.length) {
            return;
        }

        body.appendChild(swatches(t('glyphColor'), tinted[0].color, function (c, live) {
            if (!live && tinted.every(function (o) { return self.layer(o.id) && (self.layer(o.id).color === c); })) {
                self.history.key = null;
                self.render();
                return;
            }

            self.change(function (doc) {
                tinted.forEach(function (o) { find(doc, o.id).color = c; });
            }, live ? 'glyph-color' : null, !!live);
        }));
    };

    // The Sticker tool's panel, then the icons' colour and the two sections.
    var stickerPanel = Editor.panels.sticker;

    Editor.panels.sticker = function (body) {
        var picked = this.selected();

        stickerPanel.call(this, body);

        if (picked.length && picked.every(function (l) { return l.type === 'glyph'; })) {
            this.glyphPanel(body, picked);
        }

        this.glyphIconSection(body);
        this.glyphEmojiSection(body);

        if (this.swapTarget()) {
            swapNote.call(this, body);
        }
    };

    Editor.inits.push(function () {
        this.glyphs = { icons: false, emoji: false, query: '', map: null, error: false, category: M.EMOJI.order[0] };
        this.swapId = null;
    });

    // Change ends when its layer is no longer the one selected or another
    // tool is chosen.
    Editor.views.push(function () {
        if (this.swapId && !this.swapTarget()) {
            this.swapId = null;
        }
    });

    Editor.layerTypes.glyph = {
        node: glyphNode,
        paint: glyphPaint,
        ratio: true,
        // The stage draws the icon again once its font is there.
        ready: function (l) {
            return (l.set === 'bi') ? loadIconFont() : null;
        },
        name: function (l) {
            return (l.set === 'bi') ? t('glyphIcon') : t('glyphEmoji') + ' · ' + M.glyphText(l);
        },
        panel: function (body, layers) { this.glyphPanel(body, layers); }
    };

    // A document opened with icons draws them as soon as the font loads.
    Editor.views.push(function () {
        var self = this;

        if (!this.glyphFontAsked && this.doc.layers.some(function (l) { return l.type === 'glyph' && l.set === 'bi'; })) {
            this.glyphFontAsked = true;
            loadIconFont().then(function () {
                if (!self.finished) {
                    self.render();
                }
            }, function () {
                self.glyphFontAsked = false;
            });
        }
    });
}(window.PgImageEditor.parts));
