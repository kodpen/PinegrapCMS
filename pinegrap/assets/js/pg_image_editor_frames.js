/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: photo frames. In the Sticker tool's Elements a chip
 * "Frames" lists the shapes (circle, rounded square, arch, blob and the
 * element set's filled badges); a press puts an empty frame, a shape saying
 * "Your picture". A picture dropped on it, or picked through its panel from
 * Files or this computer, fills it, cut to the shape. A double press on a
 * filled frame moves the photo inside it (Zoom in its panel scales it);
 * Escape or a press outside the frame ends that.
 *
 * The frame is a layer type of its own (type 'frame'): the shape's path is
 * kept in the layer, the photo as a picture layer keeps it (src, fileId or
 * a fileName until a save makes it a file), its place as shares of the
 * frame (the model's FRAMES section). A part of window.PgImageEditor
 * bundled after the elements and the "Your picture" box
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

    // Each stage drawing of a filled frame takes a clip path id of its own.
    var clips = 0;

    function svg(name, attrs) {
        var n = document.createElementNS(SVGNS, name);

        Object.keys(attrs || {}).forEach(function (k) {
            n.setAttribute(k, String(attrs[k]));
        });

        return n;
    }

    function isFrame(l) {
        return !!l && (l.type === 'frame') && !l.locked;
    }

    // Files can be listed: the editor's API, not a caller's own save.
    function filesReachable(editor) {
        return !!X.config().api && (editor.saveMode !== 'callback');
    }

    // ---- the layer type ----

    // The shape's path in the frame's box.
    function shapeNode(l) {
        return svg('path', { d: l.d, transform: 'translate(' + l.x + ' ' + l.y + ') scale(' + (l.w / l.vw) + ' ' + (l.h / l.vh) + ')' });
    }

    // On the stage: the photo cut to the shape (while it is being moved, the
    // rest of it faint around the frame), or the empty shape with its words.
    function frameNode(l) {
        var g = svg('g'), fit = M.frameFit(l), id, clip, img, shape, words;

        if (l.rot) {
            g.setAttribute('transform', 'rotate(' + l.rot + ' ' + (l.x + l.w / 2) + ' ' + (l.y + l.h / 2) + ')');
        }

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            g.setAttribute('opacity', l.opacity);
        }

        if (fit) {
            if (this.frameEdit === l.id) {
                g.appendChild(svg('image', { href: l.src, x: fit.x, y: fit.y, width: fit.w, height: fit.h, preserveAspectRatio: 'none', opacity: 0.35 }));
            }

            id = 'pgie-frame-' + (++clips);
            clip = svg('clipPath', { id: id });
            clip.appendChild(shapeNode(l));
            img = svg('image', { href: l.src, x: fit.x, y: fit.y, width: fit.w, height: fit.h, preserveAspectRatio: 'none', 'clip-path': 'url(#' + id + ')' });
            g.appendChild(clip);
            g.appendChild(img);

            return g;
        }

        shape = shapeNode(l);
        shape.setAttribute('fill', '#e9ecef');
        shape.setAttribute('stroke', '#868e96');
        shape.setAttribute('stroke-width', '1.5');
        shape.setAttribute('stroke-dasharray', '6 4');
        shape.setAttribute('vector-effect', 'non-scaling-stroke');
        words = svg('text', { x: l.x + l.w / 2, y: l.y + l.h / 2, 'text-anchor': 'middle', 'dominant-baseline': 'middle',
            'font-family': 'system-ui, sans-serif', 'font-size': Math.max(1, Math.min(l.w, l.h) * 0.1), fill: '#495057' });
        words.textContent = t('framePicture');
        g.appendChild(shape);
        g.appendChild(words);

        return g;
    }

    // On the save's canvas, from the same numbers: the photo clipped to the
    // shape, or the empty shape with its words.
    function framePaint(g, l) {
        var fit = M.frameFit(l), img = fit ? this.pictures[l.src] : null, sx = l.w / l.vw, sy = l.h / l.vh, path = new Path2D(l.d);

        g.translate(l.x + l.w / 2, l.y + l.h / 2);
        g.rotate((l.rot || 0) * Math.PI / 180);
        g.translate(-l.w / 2, -l.h / 2);
        g.scale(sx, sy);

        if (fit) {
            g.clip(path);
            g.scale(1 / sx, 1 / sy);

            if (img && img.complete) {
                g.drawImage(img, fit.x - l.x, fit.y - l.y, fit.w, fit.h);
            }

            return;
        }

        g.fillStyle = '#e9ecef';
        g.fill(path);
        g.scale(1 / sx, 1 / sy);
        g.fillStyle = '#495057';
        g.font = Math.max(1, Math.min(l.w, l.h) * 0.1) + 'px system-ui, sans-serif';
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.fillText(t('framePicture'), l.w / 2, l.h / 2);
    }

    Editor.layerTypes.frame = {
        node: frameNode,
        paint: framePaint,
        ready: function (l) { return l.src ? this.picture(l.src) : null; },
        name: function () { return t('framePicture'); },
        // A double press: the photo's moving, or picking one for an empty
        // frame.
        edit: function (l) {
            if (l.src) {
                this.frameEditStart(l.id);
            } else if (filesReachable(this)) {
                this.framePick(l.id);
            }
        },
        panel: function (body, layers) {
            if ((layers.length === 1) && isFrame(layers[0])) {
                this.framePanel(body, layers[0]);
            }
        }
    };

    // ---- placing and filling ----

    // An empty frame in the middle of what the stage shows, selected; one
    // undo step.
    Editor.prototype.addFrame = function (shape) {
        var l = M.frameLayer(shape, this.viewRect(), this.stickerCentre());

        l.name = t(shape.label);
        this.frameEditEnd();
        this.addLayers([l], 0);
    };

    // Picture from Files for a frame: the Image tool with its list open.
    Editor.prototype.framePick = function (id) {
        this.frameTarget = id;
        this.frameEditEnd();
        this.stickerFiles.open = true;
        this.tool = (this.tools.list.indexOf('image') >= 0) ? 'image' : 'sticker';
        this.render();
    };

    // The frame a picture goes into (picked for, or dropped on), taken once.
    Editor.prototype.frameTaken = function () {
        var id = this.frameDrop || this.frameTarget, l = id ? this.layer(id) : null;

        this.frameDrop = null;
        this.frameTarget = null;

        return isFrame(l) ? l : null;
    };

    // The frame under a point of the rotated space a picture is dropped on.
    Editor.prototype.frameDropAt = function (x, y) {
        var hit = M.hitLayer(this.doc, x, y, 0, function (l) { return l.type === 'frame'; });

        this.frameDrop = hit ? hit.id : null;
    };

    // A picture put in the frame, covering it; one undo step, the frame
    // selected. One from this computer (local) larger than 2000 px is kept
    // at that size, one in another type than PNG or JPEG as a PNG.
    Editor.prototype.frameFillFrom = function (id, src, extra) {
        var self = this;

        this.picture(src).then(function (img) {
            var w = img.naturalWidth || 1, h = img.naturalHeight || 1, k = Math.min(1, 2000 / Math.max(w, h)), name = extra.fileName || '', canvas, png;

            if (extra.local && ((k < 1) || !/^data:image\/(png|jpeg);/.test(src))) {
                png = !/^data:image\/jpeg;/.test(src);
                canvas = document.createElement('canvas');
                canvas.width = w = Math.max(1, Math.round(w * k));
                canvas.height = h = Math.max(1, Math.round(h * k));
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                src = canvas.toDataURL(png ? 'image/png' : 'image/jpeg', 0.92);
                name = name.replace(/\.[a-z0-9]+$/i, '') + (png ? '.png' : '.jpg');
            }

            self.change(function (doc) {
                doc.layers = doc.layers.map(function (l) {
                    return (l.id === id) ? M.frameFill(l, { src: src, w: w, h: h, fileId: extra.fileId, fileName: name }) : l;
                });
            });
            self.select([id]);
        }).catch(function () {
            self.showMessage(t('frameError'), true);
        });
    };

    // Picture from Files, and a picture from this computer or dropped, fill
    // the frame they are for; with none they go on as before.
    var addFilePicture = Editor.prototype.addFilePicture;

    Editor.prototype.addFilePicture = function (f) {
        var l = this.frameTaken();

        if (l) {
            this.frameFillFrom(l.id, f.url, { fileId: f.id });
        } else {
            addFilePicture.call(this, f);
        }
    };

    var addPicture = Editor.prototype.addPicture;

    Editor.prototype.addPicture = function (file) {
        var self = this, l = this.frameTaken(), reader;

        if (!l) {
            addPicture.call(this, file);

            return;
        }

        reader = new FileReader();
        reader.onload = function () {
            self.frameFillFrom(l.id, String(reader.result), { local: true, fileName: file.name || '' });
        };
        reader.readAsDataURL(file);
    };

    // A drop notes the frame under it before the stage's own drop adds the
    // picture, and forgets it after.
    var bindDrop = Editor.prototype.bindDrop;

    Editor.prototype.bindDrop = function () {
        var self = this;

        this.stage.addEventListener('drop', function (e) {
            var p = self.toDoc(e.clientX, e.clientY);

            self.frameDropAt(p[0], p[1]);
        }, true);
        bindDrop.call(this);
        this.stage.addEventListener('drop', function () {
            self.frameDrop = null;
        });
    };

    // ---- the photo moved and zoomed inside ----

    Editor.prototype.frameEditStart = function (id) {
        this.frameEdit = id;
        this.frameDrag = null;
        this.select([id]);
        this.render();
    };

    // Whether a moving ended.
    Editor.prototype.frameEditEnd = function () {
        if (!this.frameEdit) {
            return false;
        }

        this.frameEdit = null;
        this.frameDrag = null;
        this.history.key = null;
        this.render();

        return true;
    };

    // A new zoom; one undo step, or one per slider drag when live.
    Editor.prototype.frameZoomTo = function (id, zoom, live) {
        var z = M.frameZoom(null, zoom);

        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (l.id === id) {
                    l.zoom = z.zoom;
                }
            });
        }, live ? 'frame-zoom' : null, !!live);
    };

    Editor.prototype.frameRemove = function (id) {
        this.frameEditEnd();
        this.change(function (doc) {
            doc.layers = doc.layers.map(function (l) {
                return (l.id === id) ? M.frameClear(l) : l;
            });
        });
    };

    // While the photo moves, a press inside the frame drags it (one undo
    // step a drag); a press outside ends the moving and goes on as Select's.
    // The Sticker tool shares Select's pointers.
    var P = Editor.pointers.select, down = P.down, move = P.move, up = P.up;

    P.down = function (e) {
        var l = this.frameEdit ? this.layer(this.frameEdit) : null, p;

        if (l && l.src) {
            p = this.toDoc(e.clientX, e.clientY);

            if (M.insideBox(l, p[0], p[1], 0)) {
                this.frameDrag = { id: l.id, at: p, start: M.clone(l) };
                e.preventDefault();

                try {
                    this.stage.setPointerCapture(e.pointerId);
                } catch (err) {
                    // The pointer is already gone.
                }

                return;
            }
        }

        this.frameEditEnd();

        return down.call(this, e);
    };

    P.move = function (e) {
        var d = this.frameDrag, p, place;

        if (!d) {
            return move.call(this, e);
        }

        p = this.toDoc(e.clientX, e.clientY);
        place = M.frameMove(d.start, p[0] - d.at[0], p[1] - d.at[1]);
        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (l.id === d.id) {
                    l.fx = place.fx;
                    l.fy = place.fy;
                }
            });
        }, 'frame-move', true);
    };

    P.up = function (e) {
        if (!this.frameDrag) {
            return up.call(this, e);
        }

        this.frameDrag = null;
        this.history.key = null;
    };

    Editor.escapes.unshift(function () {
        return this.frameEditEnd();
    });

    // ---- the panels ----

    // A frame's own controls: Choose a picture for an empty one; the
    // photo's moving, Zoom, Replace and Remove for a filled one.
    Editor.prototype.framePanel = function (body, l) {
        var self = this, files = filesReachable(this), rows = [], editing = (this.frameEdit === l.id);
        var choose = function (key) {
            return el('button', { type: 'button', class: 'pgie-btn' + (l.src ? '' : ' pgie-primary'), onclick: function () { self.framePick(l.id); } },
                [X.icon('image'), el('span', { text: t(key) })]);
        };

        if (!l.src) {
            rows.push(el('p', { class: 'pgie-note', text: t('frameNote') }));

            if (files) {
                rows.push(choose('frameChoose'));
            }

            body.appendChild(section(t('framePicture'), el('div', { class: 'pgie-elbox' }, rows)));

            return;
        }

        rows.push(el('button', { type: 'button', class: 'pgie-btn' + (editing ? ' pgie-primary' : ''), 'aria-pressed': editing ? 'true' : 'false', onclick: function () {
            if (editing) {
                self.frameEditEnd();
            } else {
                self.frameEditStart(l.id);
            }
        } }, [el('span', { text: t(editing ? 'frameDone' : 'frameMove') })]));

        if (editing) {
            rows.push(el('p', { class: 'pgie-note', role: 'status', text: t('frameMoving') }));
        }

        if (files) {
            rows.push(choose('frameReplace'));
        }

        rows.push(el('button', { type: 'button', class: 'pgie-btn', onclick: function () { self.frameRemove(l.id); } }, [el('span', { text: t('frameRemove') })]));
        body.appendChild(section(t('framePicture'), el('div', { class: 'pgie-elbox' }, rows)));

        var zoom = (l.zoom || 1), value = el('span', { class: 'pgie-val', text: Math.round(zoom * 100) + '%' });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(M.FRAME_ZOOM[0]), max: String(M.FRAME_ZOOM[1]), step: '0.05', value: String(zoom), 'aria-label': t('frameZoom') });

        slider.addEventListener('input', function () {
            value.textContent = Math.round(Number(slider.value) * 100) + '%';
            self.frameZoomTo(l.id, Number(slider.value), true);
        });
        slider.addEventListener('change', function () { self.history.key = null; });
        body.appendChild(section(t('frameZoom'), slider, value));
    };

    // The frame shapes as tiles, drawn in the panel's ink.
    function preview(s) {
        var n = svg('svg', { viewBox: '0 0 ' + s.w + ' ' + s.h, 'aria-hidden': 'true', focusable: 'false' });

        n.appendChild(svg('path', { d: s.d, fill: 'currentColor' }));

        return n;
    }

    // The chip "Frames" after the element sets', its shapes as the tiles.
    var elementTiles = Editor.prototype.elementTiles;

    Editor.prototype.elementTiles = function (holder) {
        var self = this;

        if (this.elementSet !== 'frames') {
            elementTiles.call(this, holder);

            return;
        }

        holder.textContent = '';
        M.frameShapes(X.elementList ? X.elementList() : []).forEach(function (s) {
            holder.appendChild(el('button', { type: 'button', class: 'pgie-elb', title: t(s.label), 'aria-label': t(s.label), onclick: function () {
                self.addFrame(s);
            } }, [preview(s)]));
        });
    };

    var elementsSection = Editor.prototype.elementsSection;

    Editor.prototype.elementsSection = function (body) {
        var self = this, on = (this.elementSet === 'frames'), chips;

        elementsSection.call(this, body);
        chips = body.querySelectorAll('.pgie-elbox .pgie-gf-cats');
        chips = chips[chips.length - 1];

        if (chips) {
            chips.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: t('elementSet_frames'), onclick: function () {
                self.elementSet = 'frames';
                self.renderPanel();
            } }));
        }
    };

    // The Sticker tool shows a selected frame's controls after Elements.
    var stickerPanel = Editor.panels.sticker;

    Editor.panels.sticker = function (body) {
        var picked = this.selected(), own = el('div'), at;

        stickerPanel.call(this, body);

        if ((picked.length === 1) && isFrame(picked[0])) {
            this.framePanel(own, picked[0]);
            at = body.querySelector('.pgie-elbox');
            at = at ? at.closest('.pgie-sec') : null;

            while (own.lastChild) {
                body.insertBefore(own.lastChild, at ? at.nextSibling : body.firstChild);
            }
        }
    };

    // The Image tool says a picture is being picked for a frame.
    var imagePanel = Editor.panels.image;

    Editor.panels.image = function (body) {
        if (isFrame(this.frameTarget ? this.layer(this.frameTarget) : null)) {
            body.appendChild(el('p', { class: 'pgie-note', role: 'status', text: t('frameFor') }));
        }

        imagePanel.call(this, body);
    };

    // Leaving Image and Sticker ends the picking; another tool, another
    // selection or an emptied frame ends the moving, before the stage draws.
    Editor.views.unshift(function () {
        var l = this.frameEdit ? this.layer(this.frameEdit) : null;

        if (this.frameTarget && (this.tool !== 'image') && (this.tool !== 'sticker')) {
            this.frameTarget = null;
        }

        if (this.frameEdit && (!l || !l.src || ((this.tool !== 'select') && (this.tool !== 'sticker')) || (this.sel.length !== 1) || (this.sel[0] !== l.id))) {
            this.frameEdit = null;
            this.frameDrag = null;
        }
    });

    Editor.inits.push(function () {
        this.frameTarget = null;
        this.frameDrop = null;
        this.frameEdit = null;
        this.frameDrag = null;
    });
}(window.PgImageEditor.parts));
