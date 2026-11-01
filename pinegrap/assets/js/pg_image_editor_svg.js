/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: SVG files. Opening one gives a transparent artboard of its
 * size with one layer per drawing element (path, rect, circle, ellipse,
 * line, polyline, polygon, text, g, image); definitions (gradients,
 * patterns, clip paths, styles) are kept for the file; anything else is a
 * locked layer kept as written. A save writes SVG again: every layer as the
 * stage draws it, pictures embedded, fonts by name. The server passes it
 * through pg_svg_sanitize() before it touches the disk.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, section = X.section, swatches = X.swatches;

    // Elements that become editable layers; definitions kept for the file;
    // presentation attributes a root passes down to its children.
    var EDITABLE = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'g', 'image'];
    var PAINTED = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'g'];
    var DEFS = ['defs', 'style', 'linearGradient', 'radialGradient', 'pattern', 'clipPath', 'mask', 'marker', 'filter', 'symbol'];
    var SKIP = ['title', 'desc', 'metadata'];
    var INHERITED = ['fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'color'];

    // Parsed markup by its text: one parse per change, not per redraw.
    var parsed = {};

    function serialize(node) {
        return new XMLSerializer().serializeToString(node);
    }

    // A layer's markup as an element of this page, ready to be placed.
    function markupNode(markup) {
        if (!parsed[markup]) {
            var doc = new DOMParser().parseFromString('<svg xmlns="' + SVGNS + '" xmlns:xlink="http://www.w3.org/1999/xlink">' + markup + '</svg>', 'image/svg+xml');

            parsed[markup] = doc.documentElement.firstElementChild;
        }

        return parsed[markup] ? document.importNode(parsed[markup], true) : null;
    }

    // ---- opening ----

    // The layers of an SVG's text: its artboard size, its definitions as one
    // <defs> string, and a layer per drawing element; null when it does not
    // parse as an SVG.
    Editor.prototype.svgLayers = function (text) {
        var doc = new DOMParser().parseFromString(String(text || ''), 'image/svg+xml'), root = doc.documentElement;

        if (!root || (root.localName !== 'svg') || doc.getElementsByTagName('parsererror').length) {
            return null;
        }

        var size = M.svgRoot(root.getAttribute('width'), root.getAttribute('height'), root.getAttribute('viewBox'));
        var probe = document.createElementNS(SVGNS, 'svg'), defs = [], items = [], layers = [], out = { w: size.w, h: size.h, defs: '', layers: layers };

        // Measured in the page (getBBox), out of sight.
        probe.setAttribute('style', 'position:absolute;left:-10000px;top:0;width:10px;height:10px;visibility:hidden');
        document.body.appendChild(probe);

        Array.prototype.forEach.call(root.children, function (child) {
            var name = child.localName;

            if (SKIP.indexOf(name) >= 0) {
                return;
            }

            if (DEFS.indexOf(name) >= 0) {
                defs.push(name === 'defs' ? Array.prototype.map.call(child.children, serialize).join('') : serialize(child));
                return;
            }

            var node = document.importNode(child, true), own = M.svgTransform(node.getAttribute('transform'));

            INHERITED.forEach(function (a) {
                if (root.hasAttribute(a) && !node.hasAttribute(a)) {
                    node.setAttribute(a, root.getAttribute(a));
                }
            });

            node.removeAttribute('transform');
            items.push({ name: name, node: node, own: own, editable: EDITABLE.indexOf(name) >= 0 });
        });

        out.defs = defs.length ? '<defs>' + defs.join('') + '</defs>' : '';
        probe.appendChild(markupNode(out.defs || '<defs/>'));

        items.forEach(function (item) {
            var node = item.node, m = M.mul(size.m, item.own), vb = null, box = null, wrap = null;

            probe.appendChild(node);

            try {
                var b = node.getBBox();

                vb = M.svgOwnBox({ x: b.x, y: b.y, w: b.width, h: b.height });
                box = M.svgPlace(vb, m);
            } catch (err) {
                // Not drawn (display: none): measured as nothing below.
            }

            // A skewed element keeps its own transform inside a group, which
            // the box then moves.
            if (vb && !box) {
                wrap = document.createElementNS(SVGNS, 'g');
                node.setAttribute('transform', M.svgMatrixText(item.own));
                probe.appendChild(wrap);
                wrap.appendChild(node);

                var gb = wrap.getBBox();

                vb = M.svgOwnBox({ x: gb.x, y: gb.y, w: gb.width, h: gb.height });
                box = M.svgPlace(vb, size.m);
            }

            if (!box) {
                vb = { x: 0, y: 0, w: 1, h: 1 };
                box = { x: 0, y: 0, w: 1, h: 1, rot: 0 };
            }

            var l = Object.assign({ id: 'l' + layers.length, type: 'svg', kind: item.editable ? item.name : 'kept', markup: serialize(wrap || node), vb: vb }, box);

            if (!item.editable) {
                l.locked = true;
            }

            layers.push(l);
        });

        document.body.removeChild(probe);

        return out;
    };

    // The open answer of an SVG file: the artboard and its layers instead of
    // a picture; the canvas tools, as on a new image.
    var openFile = Editor.prototype.openFile;

    Editor.prototype.openFile = function (r) {
        if (typeof r.svg !== 'string') {
            openFile.call(this, r);
            return;
        }

        var got = this.svgLayers(r.svg), fileId = this.doc.source ? this.doc.source.fileId : 0;

        if (!got) {
            this.fail(t('svgOpenError'));
            return;
        }

        this.doc = M.newDocument({ blank: { width: got.w, height: got.h, background: 'transparent' } });
        this.doc.layers = got.layers;
        this.doc.svgDefs = got.defs;
        this.svgFileId = fileId;

        // Versions keep pictures: an SVG file has none to show.
        if (this.versionsBtn) {
            this.versionsBtn.hidden = true;
        }
        this.tools = M.resolveTools('canvas', this.opts.tools);
        this.tool = this.tools.list[0];
        this.sheetOpen = (this.tool !== 'select');
        this.frame.style.background = '';
        this.frame.classList.add('pgie-checker');
        this.layersDirty = true;
        this.renderTools();
        this.setReady();
        this.render();
    };

    // ---- the layer type ----

    function svgNode(l) {
        var g = document.createElementNS(SVGNS, 'g'), child = markupNode(l.markup);

        g.setAttribute('transform', M.svgMatrixText(M.svgMatrix(l)));

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            g.setAttribute('opacity', l.opacity);
        }

        if (child) {
            g.appendChild(child);
        }

        return g;
    }

    // An attribute of a layer's top element, read or written through its
    // markup (one undo step per change).
    function topAttribute(l, name) {
        var n = markupNode(l.markup);

        return n ? (n.getAttribute(name) || '') : '';
    }

    function withTop(l, fn) {
        var n = markupNode(l.markup);

        if (n) {
            fn(n);
            l.markup = serialize(n);
        }
    }

    // A text the panel can edit: a text element with no tspan inside.
    function plainText(l) {
        var n = (l.kind === 'text') ? markupNode(l.markup) : null;

        return (n && !n.firstElementChild) ? n : null;
    }

    // A layer's own box measured again after its markup changed (a text
    // got longer); its scale stays, so the letters keep their size.
    Editor.prototype.svgRemeasure = function (l) {
        var probe = document.createElementNS(SVGNS, 'svg'), n = markupNode(l.markup), kx = l.w / l.vb.w, ky = l.h / l.vb.h;

        probe.setAttribute('style', 'position:absolute;left:-10000px;top:0;width:10px;height:10px;visibility:hidden');
        probe.appendChild(markupNode(this.doc.svgDefs || '<defs/>'));
        probe.appendChild(n);
        document.body.appendChild(probe);

        try {
            var b = n.getBBox(), vb = M.svgOwnBox({ x: b.x, y: b.y, w: b.width, h: b.height });

            l.x += (vb.x - l.vb.x) * kx;
            l.y += (vb.y - l.vb.y) * ky;
            l.vb = vb;
            l.w = vb.w * kx;
            l.h = vb.h * ky;
        } catch (err) {
            // Not measurable: the box stays.
        }

        document.body.removeChild(probe);
    };

    Editor.prototype.svgPanel = function (body, layers) {
        var self = this, open = layers.filter(function (l) { return !l.locked; });

        if (open.length < layers.length) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('svgKeptNote') }));
        }

        var painted = open.filter(function (l) { return PAINTED.indexOf(l.kind) >= 0; });

        if (!painted.length) {
            return;
        }

        var ids = painted.map(function (l) { return l.id; });
        var paint = function (attr, c, live) {
            self.change(function (doc) {
                doc.layers.forEach(function (l) {
                    if (ids.indexOf(l.id) >= 0) {
                        withTop(l, function (n) {
                            n.setAttribute(attr, (c === 'transparent') ? 'none' : c);

                            // A painted group's children take its colour.
                            if (l.kind === 'g') {
                                Array.prototype.forEach.call(n.querySelectorAll('*'), function (k) { k.removeAttribute(attr); });
                            }
                        });
                    }
                });
            }, live ? 'svg-' + attr : null);

            if (!live) {
                self.history.key = null;
            }
        };

        body.appendChild(swatches(t('svgFill'), topAttribute(painted[0], 'fill'), function (c, live) { paint('fill', c, live); }, { transparent: true }));
        body.appendChild(swatches(t('svgStroke'), topAttribute(painted[0], 'stroke'), function (c, live) { paint('stroke', c, live); }, { transparent: true }));

        var text = (painted.length === 1) ? plainText(painted[0]) : null;

        if (text) {
            var area = el('textarea', { class: 'pgie-svgtext', rows: '3', 'aria-label': t('svgText') });

            area.value = text.textContent;
            area.addEventListener('input', function () {
                var id = painted[0].id;

                self.change(function (doc) {
                    doc.layers.forEach(function (l) {
                        if (l.id === id) {
                            withTop(l, function (n) { n.textContent = area.value; });
                            self.svgRemeasure(l);
                        }
                    });
                }, 'svg-text', true);
            });
            area.addEventListener('change', function () { self.history.key = null; });
            body.appendChild(section(t('svgText'), area));
        }
    };

    Editor.layerTypes.svg = {
        node: svgNode,
        name: function (l) { return t('svgKind_' + l.kind); },
        panel: function (body, layers) { this.svgPanel(body, layers); }
    };

    // The kept definitions on the stage, so gradients and clip paths draw.
    Editor.views.push(function () {
        var host = this.layersSvg, defs = this.doc.svgDefs || '', now = host.querySelector('[data-pgie-svgdefs]');

        if (now && (now.getAttribute('data-src') === defs) && (now === host.firstChild)) {
            return;
        }

        if (now) {
            host.removeChild(now);
        }

        if (!defs) {
            return;
        }

        var node = markupNode(defs);

        if (node) {
            node.setAttribute('data-pgie-svgdefs', '');
            node.setAttribute('data-src', defs);
            host.insertBefore(node, host.firstChild);
        }
    });

    // ---- saving ----

    // An SVG layer as written to the file: its element with the layer's
    // matrix as its own transform, so it opens again as the same kind.
    function svgSaveNode(l) {
        var n = markupNode(l.markup);

        if (!n) {
            return null;
        }

        n.setAttribute('transform', M.svgMatrixText(M.svgMatrix(l)));

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            var own = n.hasAttribute('opacity') ? Number(n.getAttribute('opacity')) : 1;

            n.setAttribute('opacity', String(Math.round(l.opacity * (isNaN(own) ? 1 : own) * 1000) / 1000));
        }

        return n;
    }

    // A picture's address as a data: URL; '' when it cannot be read.
    function embed(src) {
        if (/^data:/i.test(src)) {
            return Promise.resolve(src);
        }

        return fetch(src, { credentials: 'same-origin' }).then(function (res) {
            return res.ok ? res.blob() : null;
        }).then(function (blob) {
            if (!blob || !/^image\/(png|jpeg|gif|webp|avif)$/.test(blob.type)) {
                return '';
            }

            return new Promise(function (resolve) {
                var reader = new FileReader();

                reader.onload = function () { resolve(String(reader.result)); };
                reader.onerror = function () { resolve(''); };
                reader.readAsDataURL(blob);
            });
        }, function () {
            return '';
        });
    }

    // The document as an SVG file: the artboard's fill, the kept
    // definitions and every visible layer as the stage draws it, with its
    // pictures embedded.
    Editor.prototype.svgText = function () {
        var self = this, a = this.doc.artboard, nodes = [];

        this.doc.layers.forEach(function (l) {
            var n = l.hidden ? null : ((l.type === 'svg') ? svgSaveNode(l) : self.layerNode(l));

            if (n) {
                nodes.push(n);
            }
        });

        var links = [];

        nodes.forEach(function (n) {
            [n].concat(Array.prototype.slice.call(n.querySelectorAll('image'))).forEach(function (img) {
                if (img.localName === 'image') {
                    links.push(img);
                }
            });
        });

        return Promise.all(links.map(function (img) {
            var src = img.getAttribute('href') || img.getAttributeNS('http://www.w3.org/1999/xlink', 'href') || '';

            return embed(src).then(function (data) {
                img.removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                img.setAttribute('href', data);
            });
        })).then(function () {
            return M.svgFile(a.width, a.height, a.background, self.doc.svgDefs || '', nodes.map(serialize));
        });
    };

    // An SVG file is saved as SVG (over the file, or as a copy beside it).
    var send = Editor.prototype.send;

    Editor.prototype.send = function (mode) {
        var self = this;

        if (!this.svgFileId || ((mode !== 'replace') && (mode !== 'copy'))) {
            send.call(this, mode);
            return;
        }

        this.svgText().then(function (text) {
            return self.request({ action: 'svg_save', mode: mode, file_id: self.svgFileId, svg: text });
        }).then(function (r) {
            if (!r || (r.status !== 'success')) {
                self.setSaving(false);
                self.showMessage((r && r.message) || t('saveError'), true);
                return;
            }

            var result = { file: r, doc: M.clone(self.doc), fileId: r.id };

            if (typeof self.opts.onSave === 'function') {
                self.opts.onSave(result);
            }

            self.finish(result);
        }, function () {
            self.setSaving(false);
            self.showMessage(t('saveError'), true);
        });
    };
}(window.PgImageEditor.parts));
