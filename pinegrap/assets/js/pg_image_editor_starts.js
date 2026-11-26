/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: "Start from a design" at the top of the New image dialog.
 * Opened, the row shows purpose chips (All, Post, Story, Banner,
 * Announcement, Sale, Event, Quote, Invitation) and the designs as tiles
 * drawn small by the canvas's own layer drawing, "Blank" first; a design
 * picked and Create open it as the new canvas. Its texts are label words in
 * the panel's language, its shapes drawn layers, and a place for a photo a
 * dashed box "Your picture" (an svg layer marked slot): Choose a picture in
 * its panel opens Picture from Files, and the picked picture fills the box,
 * cut to it, as a picture layer.
 *
 * The designs are files (assets/canvas/designs.json, the index, and
 * assets/canvas/designs/<id>.json, config starts) read when the row first
 * opens and checked by the model's M.startIndex() and M.startDesign(). A
 * part of window.PgImageEditor bundled after the svg layers; it hangs its
 * row on the artboard part's Editor.newImageHeads
 * (docs/memory/ui/image-editor-parts.md).
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

    // The checked designs: null until read, then a list in the index's
    // order (empty when nothing could be read); one read a page.
    var designs = null, reading = null;

    function fetchJson(url) {
        return window.fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('designs');
            }

            return r.json();
        });
    }

    function known(family) {
        return !window.PgGoogleFonts || (window.PgGoogleFonts.all().indexOf(family) >= 0);
    }

    // The index, then every design it names; a design whose file is
    // missing or refused is left out.
    function readDesigns() {
        var c = X.config().starts;

        if (!reading) {
            reading = (c && c.index && window.fetch) ? fetchJson(c.index).then(function (data) {
                var list = M.startIndex(data);

                return Promise.all(list.map(function (entry) {
                    return fetchJson(c.base + entry.id + '.json?v=' + encodeURIComponent(String(c.v || ''))).then(function (file) {
                        return M.startDesign(file, entry, known);
                    }, function () {
                        return null;
                    });
                }));
            }).then(function (all) {
                designs = all.filter(Boolean);
            }, function () {
                designs = [];
            }) : Promise.resolve().then(function () { designs = []; });
        }

        return reading;
    }

    // ---- the document a design opens as ----

    // A design's board and layers: texts in the panel's words, made smaller
    // when a translation is wider than its room, placed at their anchors;
    // "Your picture" boxes as svg slot layers; each layer its own id.
    Editor.prototype.startDocument = function (design) {
        var self = this, doc = { layers: [] };

        design.layers.forEach(function (d) {
            var l, lay, size, at;

            if (d.type === 'text') {
                l = M.startText(d, t(d.text));
                lay = self.textLayout(l);
                size = M.startTextFit(l.size, lay.w, d.maxW);

                if (size !== l.size) {
                    l.size = size;
                    lay = self.textLayout(l);
                }

                at = M.startTextPlace(d, lay.w);
                l.x = at.x;
                l.y = at.y;
                l.w = lay.w;
                l.h = lay.h;
            } else if (d.type === 'slot') {
                l = M.startSlotLayer(d, t('startPicture'));
            } else {
                l = M.clone(d);
            }

            l.id = M.newId(doc);
            doc.layers.push(l);
        });

        return { artboard: { width: design.width, height: design.height, background: design.background }, layers: doc.layers };
    };

    // A design's faces, asked for as its tile is drawn so the canvas opens
    // with them; the stage refits the texts when a face comes later.
    function designFaces(editor, design) {
        return Promise.all(design.layers.filter(function (d) {
            return d.type === 'text';
        }).map(function (d) {
            return editor.loadTextFont ? editor.loadTextFont(M.startText(d, t(d.text))) : null;
        }));
    }

    // A design drawn small by the canvas's own layer drawing.
    Editor.prototype.startThumb = function (design) {
        var self = this, svg = document.createElementNS(SVGNS, 'svg'), bg = document.createElementNS(SVGNS, 'rect');

        svg.setAttribute('viewBox', '0 0 ' + design.width + ' ' + design.height);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        bg.setAttribute('width', String(design.width));
        bg.setAttribute('height', String(design.height));
        bg.setAttribute('fill', (design.background === 'transparent') ? '#ffffff' : design.background);
        svg.appendChild(bg);

        this.startDocument(design).layers.forEach(function (l) {
            var n = self.layerNode(l);

            if (n) {
                svg.appendChild(n);
            }
        });

        return svg;
    };

    // ---- the row of the New image dialog ----

    Editor.newImageHeads.push(function (api) {
        var self = this, purpose = 'all', picked = null, open = false;
        var head = el('button', { type: 'button', class: 'pgie-starthead', 'aria-expanded': 'false' }, [X.icon('chev'), el('span', { text: t('startRow') })]);
        var chips = el('div', { class: 'pgie-seg pgie-gf-cats', role: 'group', 'aria-label': t('startRow') });
        var tiles = el('div', { class: 'pgie-sttiles', role: 'group', 'aria-label': t('startRow') });
        var body = el('div', { class: 'pgie-startbody' }, [chips, tiles]);

        if (this.tools.mini) {
            return null;
        }

        body.hidden = true;

        var mark = function () {
            Array.prototype.forEach.call(tiles.querySelectorAll('.pgie-sttile'), function (n) {
                var on = (n.getAttribute('data-design') || '') === (picked || '');

                n.classList.toggle('on', on);
                n.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        };

        var pick = function (design) {
            picked = design ? design.id : null;
            api.choose(design ? function () { return this.startDocument(design); } : null);
            mark();
        };

        var tile = function (design) {
            var thumb = el('span', { class: 'pgie-stthumb' }), size = design ? design.width + ' × ' + design.height + ' px' : '';
            var name = design ? t(design.label) : t('startBlank');
            var b = el('button', { type: 'button', class: 'pgie-sttile', 'data-design': design ? design.id : '', title: name, onclick: function () { pick(design); } }, [
                thumb,
                el('b', { text: name }),
                el('span', { class: 'pgie-stsize', text: size })
            ]);

            if (design) {
                thumb.appendChild(self.startThumb(design));
                designFaces(self, design).then(function () {
                    if (thumb.isConnected) {
                        thumb.replaceChild(self.startThumb(design), thumb.firstChild);
                    }
                });
            } else {
                thumb.classList.add('pgie-stblank');
            }

            return b;
        };

        var drawTiles = function () {
            var list = M.startPurposeList(designs, purpose);

            tiles.textContent = '';

            if (!designs.length) {
                tiles.appendChild(el('p', { class: 'pgie-note', role: 'alert', text: t('startError') }));

                return;
            }

            tiles.appendChild(tile(null));
            list.forEach(function (design) {
                tiles.appendChild(tile(design));
            });

            if (!list.length) {
                tiles.appendChild(el('p', { class: 'pgie-note', text: t('startNone') }));
            }

            mark();
        };

        M.START_PURPOSES.forEach(function (key) {
            chips.appendChild(el('button', { type: 'button', 'data-purpose': key, class: (key === purpose) ? 'on' : '', 'aria-pressed': (key === purpose) ? 'true' : 'false',
                text: t('startPurpose_' + key), onclick: function () {
                    purpose = key;
                    Array.prototype.forEach.call(chips.children, function (n) {
                        var on = n.getAttribute('data-purpose') === key;

                        n.classList.toggle('on', on);
                        n.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });

                    if (designs) {
                        drawTiles();
                    }
                } }));
        });

        head.addEventListener('click', function () {
            open = !open;
            body.hidden = !open;
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
            head.classList.toggle('on', open);

            // Closed, the dialog makes a blank board again.
            if (!open) {
                pick(null);

                return;
            }

            if (designs) {
                drawTiles();
            } else {
                tiles.textContent = '';
                tiles.appendChild(el('p', { class: 'pgie-note', role: 'status', text: t('startLoading') }));
                readDesigns().then(function () {
                    if (tiles.isConnected && open) {
                        drawTiles();
                    }
                });
            }
        });

        return el('div', { class: 'pgie-start' }, [head, body]);
    });

    // ---- the "Your picture" box ----

    function isSlot(l) {
        return !!l && (l.type === 'svg') && (l.slot === true) && !l.locked;
    }

    // The slot a picture is being picked for, while it is still there.
    Editor.prototype.slotTargetLayer = function () {
        var id = this.slotTarget;

        return id ? this.doc.layers.filter(function (l) { return (l.id === id) && isSlot(l); })[0] || null : null;
    };

    // Picture from Files for a box: the Image tool with its list open.
    Editor.prototype.slotPick = function (id) {
        this.slotTarget = id;
        this.stickerFiles.open = true;
        this.tool = (this.tools.list.indexOf('image') >= 0) ? 'image' : 'sticker';
        this.render();
    };

    // The box replaced by a picture layer in its place, its size and turn;
    // one undo step, the picture selected.
    Editor.prototype.slotPut = function (id, src, name) {
        this.change(function (doc) {
            doc.layers = doc.layers.map(function (l) {
                var n;

                if (l.id !== id) {
                    return l;
                }

                n = { id: l.id, type: 'image', src: src, fileName: name || '', x: l.x, y: l.y, w: l.w, h: l.h, rot: l.rot || 0, opacity: (l.opacity === undefined) ? 1 : l.opacity };

                if (l.grp) {
                    n.grp = l.grp;
                }

                return n;
            });
        });
        this.slotTarget = null;
        this.select([id]);
    };

    // A picture cut to cover the box (M.startCover()), kept as a new
    // picture: a PNG when it came as one, else a JPEG.
    Editor.prototype.slotFill = function (box, src, name) {
        var self = this;

        this.picture(src).then(function (img) {
            var c = M.startCover(img.naturalWidth || 1, img.naturalHeight || 1, box.w, box.h), canvas = document.createElement('canvas');
            var png = /\.png$/i.test(name || '') || /^data:image\/png/.test(src);

            canvas.width = c.w;
            canvas.height = c.h;
            canvas.getContext('2d').drawImage(img, c.sx, c.sy, c.sw, c.sh, 0, 0, c.w, c.h);
            self.slotPut(box.id, canvas.toDataURL(png ? 'image/png' : 'image/jpeg', 0.92), String(name || '').replace(/\.[a-z0-9]+$/i, '') + (png ? '.png' : '.jpg'));
        }).catch(function () {
            self.showMessage(t('startPictureError'), true);
        });
    };

    // Picture from Files, and a picture from this computer, fill the box
    // being picked for; with none they add a layer as before.
    var addFilePicture = Editor.prototype.addFilePicture;

    Editor.prototype.addFilePicture = function (f) {
        var box = this.slotTargetLayer();

        if (box) {
            this.slotFill(box, f.url, f.name || '');
        } else {
            addFilePicture.call(this, f);
        }
    };

    var addPicture = Editor.prototype.addPicture;

    Editor.prototype.addPicture = function (file) {
        var self = this, box = this.slotTargetLayer(), reader;

        if (!box) {
            addPicture.call(this, file);

            return;
        }

        reader = new FileReader();
        reader.onload = function () {
            self.slotFill(box, String(reader.result), file.name || '');
        };
        reader.readAsDataURL(file);
    };

    // Select's panel for a box: what it is for and Choose a picture, where
    // Files can be reached; its outline and fill stay as an svg layer's.
    var svgPanel = Editor.prototype.svgPanel;

    Editor.prototype.svgPanel = function (body, layers) {
        var self = this, box = (layers.length === 1) && isSlot(layers[0]) ? layers[0] : null;

        if (!box) {
            svgPanel.call(this, body, layers);

            return;
        }

        body.appendChild(section(t('startPicture'), el('div', { class: 'pgie-stslot' }, [
            el('p', { class: 'pgie-note', text: t('startPictureNote') }),
            (X.config().api && (this.saveMode !== 'callback')) ? el('button', { type: 'button', class: 'pgie-btn pgie-primary', onclick: function () { self.slotPick(box.id); } },
                [X.icon('image'), el('span', { text: t('startPictureChoose') })]) : null
        ])));
    };

    // The Image tool says which box the picture is for.
    var imagePanel = Editor.panels.image;

    Editor.panels.image = function (body) {
        if (this.slotTargetLayer()) {
            body.appendChild(el('p', { class: 'pgie-note pgie-stfor', role: 'status', text: t('startPictureHint') }));
        }

        imagePanel.call(this, body);
    };

    // Leaving the Image tool ends the picking.
    Editor.views.push(function () {
        if (this.slotTarget && (this.tool !== 'image') && (this.tool !== 'sticker')) {
            this.slotTarget = null;
        }
    });

    Editor.inits.push(function () {
        this.slotTarget = null;
    });
}(window.PgImageEditor.parts));
