/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor model: window.PgImageEditorModel.
 *
 * The pure part of the image editor (assets/js/pg_image_editor.js): 2D affine
 * matrices in SVG order [a, b, c, d, e, f], the picture's quarter turns and
 * flips, the cover scale of a straightened crop, the view's fit and zoom, the
 * tool list of a call, the document and its undo history, colour, layers, and
 * at the end each tool's own section. Nothing here touches the page, so
 * tests/image_editor_model_test.php runs it under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // ---- matrices ----

    function mul(a, b) {
        return [
            a[0] * b[0] + a[2] * b[1], a[1] * b[0] + a[3] * b[1],
            a[0] * b[2] + a[2] * b[3], a[1] * b[2] + a[3] * b[3],
            a[0] * b[4] + a[2] * b[5] + a[4], a[1] * b[4] + a[3] * b[5] + a[5]
        ];
    }

    function inv(m) {
        var det = (m[0] * m[3] - m[1] * m[2]) || 1e-9;

        return [
            m[3] / det, -m[1] / det, -m[2] / det, m[0] / det,
            (m[2] * m[5] - m[3] * m[4]) / det, (m[1] * m[4] - m[0] * m[5]) / det
        ];
    }

    function apply(m, x, y) {
        return [m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]];
    }

    function translate(x, y) {
        return [1, 0, 0, 1, x, y];
    }

    function scale(x, y) {
        return [x, 0, 0, (y === undefined) ? x : y, 0, 0];
    }

    function rotate(deg) {
        var r = deg * Math.PI / 180, c = Math.cos(r), s = Math.sin(r);

        return [c, s, -s, c, 0, 0];
    }

    function clamp(v, a, b) {
        return Math.max(a, Math.min(b, v));
    }

    // ---- picture geometry ----

    // The quarter turn (0, 90, 180 or 270 degrees clockwise) and the flips of
    // a w x h picture. r turns the picture into the rotated space, f flips it
    // there, fr does both; rw x rh is the rotated size. Layers live in this
    // rotated space (after turns and flips, before straightening and
    // cropping), so a text stays upright whatever the picture does.
    function flipRot(w, h, rot, flipX, flipY) {
        rot = ((Math.round(rot / 90) * 90) % 360 + 360) % 360;

        var rw = (rot % 180 === 0) ? w : h, rh = (rot % 180 === 0) ? h : w;
        var r = (rot === 90) ? [0, 1, -1, 0, h, 0]
            : (rot === 180) ? [-1, 0, 0, -1, w, h]
            : (rot === 270) ? [0, -1, 1, 0, 0, w]
            : [1, 0, 0, 1, 0, 0];
        var f = [1, 0, 0, 1, 0, 0];

        if (flipX) {
            f = mul([-1, 0, 0, 1, rw, 0], f);
        }

        if (flipY) {
            f = mul([1, 0, 0, -1, 0, rh], f);
        }

        return { rot: rot, r: r, f: f, fr: mul(f, r), rw: rw, rh: rh };
    }

    // The smallest scale of a picture turned by deg degrees around the centre
    // of the rotated space (rw x rh) that still covers the crop box, so a
    // straightened picture never shows its corners.
    function coverScale(crop, rw, rh, deg) {
        if (!deg) {
            return 1;
        }

        var a = -deg * Math.PI / 180, co = Math.cos(a), si = Math.sin(a), cx = rw / 2, cy = rh / 2, s = 1;
        var corners = [[crop.x, crop.y], [crop.x + crop.w, crop.y], [crop.x, crop.y + crop.h], [crop.x + crop.w, crop.y + crop.h]];

        corners.forEach(function (p) {
            var dx = p[0] - cx, dy = p[1] - cy;
            var qx = dx * co - dy * si, qy = dx * si + dy * co;

            s = Math.max(s, 2 * Math.abs(qx) / rw, 2 * Math.abs(qy) / rh);
        });

        return s;
    }

    // ---- view ----

    // The zoom that fits a w x h box into aw x ah, pad pixels from each edge.
    function fitZoom(w, h, aw, ah, pad) {
        pad = pad || 0;

        return Math.max(0.01, Math.min((aw - 2 * pad) / Math.max(1, w), (ah - 2 * pad) / Math.max(1, h)));
    }

    // A w x h document at zoom k in the middle of a sw x sh stage. A view is
    // { k, x, y }: the zoom and where the document's top-left corner sits.
    function centred(w, h, sw, sh, k) {
        return { k: k, x: (sw - w * k) / 2, y: (sh - h * k) / 2 };
    }

    // The fitted view of a w x h document on a sw x sh stage whose right
    // cover pixels lie under an open panel sheet: centred in the part left
    // uncovered, never zoomed past the picture's own size. A panel that
    // leaves less than 40 % of the stage is not counted.
    function fitView(w, h, sw, sh, cover, pad) {
        var aw = sw - Math.max(0, cover || 0);

        if (aw < sw * 0.4) {
            aw = sw;
        }

        return centred(w, h, aw, sh, Math.min(1, fitZoom(w, h, aw, sh, pad)));
    }

    // Zoom by factor around the stage point (px, py): the document point under
    // the pointer stays under it.
    function zoomAt(view, factor, px, py, min, max) {
        var k = clamp(view.k * factor, min || 0.02, max || 32);
        var f = k / view.k;

        return { k: k, x: px - (px - view.x) * f, y: py - (py - view.y) * f };
    }

    // ---- tools ----

    var PROFILES = {
        photo: ['select', 'crop', 'artboard', 'adjust', 'draw', 'text', 'redact', 'area', 'sticker', 'frame', 'vector', 'scissors', 'warp', 'puppet', 'builder', 'motion'],
        canvas: ['select', 'artboard', 'draw', 'text', 'sticker', 'vector', 'scissors', 'warp', 'puppet', 'builder', 'frame', 'motion'],
        annotate: ['arrow', 'rect', 'text', 'pen', 'redact'],
        optimise: ['export']
    };

    // Tools a caller may name: the rail's and the marking tools of the mini
    // bar (one draw kind each).
    var TOOLS = ['select', 'crop', 'adjust', 'draw', 'text', 'redact', 'area', 'sticker', 'frame', 'vector', 'scissors', 'warp', 'puppet', 'builder', 'artboard', 'image', 'export', 'motion',
        'arrow', 'line', 'rect', 'ellipse', 'pen'];

    // The tools of one call: the caller's list (known names, first mention
    // kept) or the profile's. A caller's list and the annotate profile draw the
    // floating mini bar instead of the rail.
    function resolveTools(profile, tools) {
        var list = [];

        if (Object.prototype.toString.call(tools) === '[object Array]') {
            tools.forEach(function (t) {
                if ((TOOLS.indexOf(t) >= 0) && (list.indexOf(t) < 0)) {
                    list.push(t);
                }
            });
        }

        profile = PROFILES.hasOwnProperty(profile) ? profile : 'photo';

        var mini = (list.length > 0) || (profile === 'annotate');

        if (!list.length) {
            list = PROFILES[profile].slice();
        }

        return { profile: profile, list: list, mini: mini };
    }

    // Rail items that hold several tools, each a mode of the item's switch
    // at the top of its panel, in the switch's order.
    var RAIL_GROUPS = {
        photo: ['crop', 'adjust', 'redact', 'area', 'frame'],
        vectors: ['vector', 'scissors', 'builder', 'warp', 'puppet']
    };

    // The group a tool belongs to, or ''.
    function railGroup(tool) {
        var keys = Object.keys(RAIL_GROUPS);

        for (var i = 0; i < keys.length; i++) {
            if (RAIL_GROUPS[keys[i]].indexOf(tool) >= 0) {
                return keys[i];
            }
        }

        return '';
    }

    // The rail's items for a call's tools, each { id, modes }: a group
    // stands where the first of its tools stands in the list and opens the
    // ones the list has; a group with one of them is that tool's own item.
    function railItems(list) {
        var items = [], seen = {};

        (list || []).forEach(function (tool) {
            var g = railGroup(tool), modes;

            if (!g) {
                items.push({ id: tool, modes: [tool] });
                return;
            }

            if (seen[g]) {
                return;
            }

            seen[g] = true;
            modes = RAIL_GROUPS[g].filter(function (m) { return list.indexOf(m) >= 0; });
            items.push({ id: (modes.length > 1) ? g : modes[0], modes: modes });
        });

        return items;
    }

    // The rail item whose modes hold tool, or null.
    function railItemOf(items, tool) {
        for (var i = 0; i < (items || []).length; i++) {
            if (items[i].modes.indexOf(tool) >= 0) {
                return items[i];
            }
        }

        return null;
    }

    // The tool a press on item opens: its mode used last (last: item id to
    // tool), else its first.
    function railOpen(item, last) {
        var m = last && last[item.id];

        return (m && (item.modes.indexOf(m) >= 0)) ? m : item.modes[0];
    }

    // ---- document ----

    var SIZE_MIN = 16, SIZE_MAX = 8000;

    function clone(v) {
        return JSON.parse(JSON.stringify(v));
    }

    // Whether two documents hold the same data, whatever the order of their
    // keys: a document brought back by Cancel or undo is not a change.
    function sameDocument(a, b) {
        if (a === b) {
            return true;
        }

        if (!a || !b || (typeof a !== 'object') || (typeof b !== 'object') || (Array.isArray(a) !== Array.isArray(b))) {
            return false;
        }

        var ka = Object.keys(a), kb = Object.keys(b);

        if (ka.length !== kb.length) {
            return false;
        }

        for (var i = 0; i < ka.length; i++) {
            if (!Object.prototype.hasOwnProperty.call(b, ka[i]) || !sameDocument(a[ka[i]], b[ka[i]])) {
                return false;
            }
        }

        return true;
    }

    // A new document: a picture (its size comes when it has loaded) or, with
    // blank, an empty artboard. Version 1 of the format; a later step that
    // changes it raises v and reads the older one.
    function newDocument(opts) {
        opts = opts || {};

        var blank = null;

        if (opts.blank) {
            blank = {
                width: Math.round(clamp(Number(opts.blank.width) || 1080, SIZE_MIN, SIZE_MAX)),
                height: Math.round(clamp(Number(opts.blank.height) || 1080, SIZE_MIN, SIZE_MAX)),
                background: (typeof opts.blank.background === 'string') ? opts.blank.background : '#ffffff'
            };
        }

        return {
            v: 1,
            source: blank ? null : { fileId: Number(opts.fileId) || 0, width: 0, height: 0 },
            artboard: blank,
            geometry: { rot: 0, flipX: false, flipY: false, straighten: 0, crop: null, cropShape: 'rect', output: null },
            adjust: {},
            filter: 'none',
            frame: null,
            layers: []
        };
    }

    // The size the document is edited at: the artboard, or the picture after
    // its quarter turn and crop.
    function docSize(doc) {
        if (doc.artboard) {
            return { w: doc.artboard.width, h: doc.artboard.height };
        }

        var g = doc.geometry, src = doc.source || { width: 0, height: 0 };

        if (g.crop) {
            return { w: g.crop.w, h: g.crop.h };
        }

        var fr = flipRot(src.width, src.height, g.rot, false, false);

        return { w: fr.rw, h: fr.rh };
    }

    // ---- undo history ----

    // Snapshots of the document. Changes pushed with the same key one after
    // another (the moves of one slider drag) make a single step.
    function History(limit) {
        this.limit = limit || 100;
        this.past = [];
        this.future = [];
        this.key = null;
    }

    History.prototype.push = function (snapshot, key) {
        if (key && (key === this.key) && this.past.length) {
            this.future = [];
            return false;
        }

        this.past.push(snapshot);

        if (this.past.length > this.limit) {
            this.past.shift();
        }

        this.future = [];
        this.key = key || null;

        return true;
    };

    History.prototype.undo = function (current) {
        if (!this.past.length) {
            return null;
        }

        this.future.push(current);
        this.key = null;

        return this.past.pop();
    };

    History.prototype.redo = function (current) {
        if (!this.future.length) {
            return null;
        }

        this.past.push(current);
        this.key = null;

        return this.future.pop();
    };

    History.prototype.canUndo = function () {
        return this.past.length > 0;
    };

    History.prototype.canRedo = function () {
        return this.future.length > 0;
    };

    // ---- crop, turns and flips ----

    // Crop ratios (width / height): 0 is free, -1 the picture's own.
    var RATIOS = [['free', 0], ['original', -1], ['1:1', 1], ['4:3', 4 / 3], ['3:2', 3 / 2], ['16:9', 16 / 9], ['4:5', 4 / 5], ['9:16', 9 / 16]];

    // The ratio a caller gives (options.ratio: { value: width / height,
    // label }, the shape a page shows the picture in) as a number; 0 when
    // there is none or it is out of reason.
    function givenRatio(given) {
        var v = (given && (typeof given === 'object')) ? Number(given.value) : Number(given);

        return (isFinite(v) && (v >= 0.05) && (v <= 20)) ? v : 0;
    }

    // The crop panel's ratios: [label, width / height, given]. The caller's
    // ratio marks the list's own entry of that shape, else it is added after
    // the picture's own.
    function cropRatios(given) {
        var v = givenRatio(given), found = false;
        var list = RATIOS.map(function (r) {
            var mine = !!v && (r[1] > 0) && !found && (Math.abs(r[1] - v) / v < 0.005);

            found = found || mine;

            return [r[0], r[1], mine];
        });

        if (v && !found) {
            var label = (given && given.label) ? String(given.label) : (Math.round(v * 100) / 100) + ':1';

            list.splice(2, 0, [label, v, true]);
        }

        return list;
    }

    // Layers whose content a turn or a flip of the picture does not turn:
    // only their place follows it.
    var UPRIGHT = ['text', 'sticker', 'image', 'glyph'];

    function sourceTurns(doc) {
        var g = doc.geometry, src = doc.source || { width: 0, height: 0 };

        return flipRot(src.width, src.height, g.rot, g.flipX, g.flipY);
    }

    // The crop box in the rotated space; the whole picture without a crop.
    function cropBox(doc) {
        var c = doc.geometry.crop, fr = sourceTurns(doc);

        return c ? { x: c.x, y: c.y, w: c.w, h: c.h } : { x: 0, y: 0, w: fr.rw, h: fr.rh };
    }

    // From the picture's pixels to the rotated space: the quarter turn and
    // flips, then the straightening around the centre, scaled so that crop
    // (the document's own box by default) stays covered.
    function pictureMatrix(doc, crop) {
        var fr = sourceTurns(doc), deg = doc.geometry.straighten || 0;
        var s = coverScale(crop || cropBox(doc), fr.rw, fr.rh, deg);
        var around = mul(translate(fr.rw / 2, fr.rh / 2), mul(rotate(deg), mul(scale(s), translate(-fr.rw / 2, -fr.rh / 2))));

        return mul(around, fr.fr);
    }

    // The largest box of ratio inside rw x rh, centred where box was and
    // kept inside. ratio 0 leaves the box as it is.
    function fitRatio(box, ratio, rw, rh) {
        if (!ratio) {
            return { x: box.x, y: box.y, w: box.w, h: box.h };
        }

        var w = rw, h = rw / ratio;

        if (h > rh) {
            h = rh;
            w = rh * ratio;
        }

        return {
            x: clamp(box.x + box.w / 2 - w / 2, 0, rw - w),
            y: clamp(box.y + box.h / 2 - h / 2, 0, rh - h),
            w: w,
            h: h
        };
    }

    // The box after dragging handle (n, s, e, w or a corner) to the point
    // (px, py), inside rw x rh. With a ratio the opposite corner stays (an
    // edge handle keeps the other axis centred) and the box shrinks rather
    // than leave the picture.
    function resizeCrop(box, handle, px, py, ratio, rw, rh, min) {
        min = min || 16;
        px = clamp(px, 0, rw);
        py = clamp(py, 0, rh);

        var x0 = box.x, y0 = box.y, x1 = box.x + box.w, y1 = box.y + box.h;
        var west = handle.indexOf('w') >= 0, north = handle.indexOf('n') >= 0;

        if (west) {
            x0 = Math.min(px, x1 - min);
        }

        if (handle.indexOf('e') >= 0) {
            x1 = Math.max(px, x0 + min);
        }

        if (north) {
            y0 = Math.min(py, y1 - min);
        }

        if (handle.indexOf('s') >= 0) {
            y1 = Math.max(py, y0 + min);
        }

        if (!ratio) {
            return { x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
        }

        var vertical = (handle === 'n' || handle === 's'), horizontal = (handle === 'e' || handle === 'w');
        var w = x1 - x0, h = y1 - y0;
        var ax = west ? box.x + box.w : box.x, ay = north ? box.y + box.h : box.y;
        var cx = box.x + box.w / 2, cy = box.y + box.h / 2;

        if (vertical) {
            w = h * ratio;
        } else if (horizontal) {
            h = w / ratio;
        } else if (w / h > ratio) {
            h = w / ratio;
        } else {
            w = h * ratio;
        }

        var roomW = vertical ? 2 * Math.min(cx, rw - cx) : (west ? ax : rw - ax);
        var roomH = horizontal ? 2 * Math.min(cy, rh - cy) : (north ? ay : rh - ay);
        var f = Math.min(1, roomW / w, roomH / h);

        w *= f;
        h *= f;

        return {
            x: vertical ? cx - w / 2 : (west ? ax - w : ax),
            y: horizontal ? cy - h / 2 : (north ? ay - h : ay),
            w: w,
            h: h
        };
    }

    function moveCrop(box, dx, dy, rw, rh) {
        return { x: clamp(box.x + dx, 0, rw - box.w), y: clamp(box.y + dy, 0, rh - box.h), w: box.w, h: box.h };
    }

    // A layer's geometry through fn(x, y) -> [x, y]: line ends, point lists,
    // path nodes and boxes; an upright layer keeps its size.
    function mapLayer(layer, fn) {
        var l = clone(layer), p, q;

        if (typeof l.x1 === 'number') {
            p = fn(l.x1, l.y1);
            q = fn(l.x2, l.y2);
            l.x1 = p[0];
            l.y1 = p[1];
            l.x2 = q[0];
            l.y2 = q[1];
        }

        if (Object.prototype.toString.call(l.pts) === '[object Array]') {
            l.pts = l.pts.map(function (pt) { return fn(pt[0], pt[1]); });
        }

        if (Object.prototype.toString.call(l.nodes) === '[object Array]') {
            l.nodes.forEach(function (n) {
                var a = fn(n.x, n.y), i = fn(n.ix, n.iy), o = fn(n.ox, n.oy);

                n.x = a[0];
                n.y = a[1];
                n.ix = i[0];
                n.iy = i[1];
                n.ox = o[0];
                n.oy = o[1];
            });
        }

        if ((typeof l.x === 'number') && (typeof l.w === 'number')) {
            if (UPRIGHT.indexOf(l.type) >= 0) {
                p = fn(l.x + l.w / 2, l.y + l.h / 2);
                l.x = p[0] - l.w / 2;
                l.y = p[1] - l.h / 2;
            } else {
                // Any other box keeps its size around its moved centre and
                // takes the turn into its angle; a mirror also mirrors its
                // drawing (mirror) and turns its angle the other way.
                var o = fn(0, 0), ex = fn(1, 0), ey = fn(0, 1), ax = ex[0] - o[0], ay = ex[1] - o[1];
                var mirrored = (ax * (ey[1] - o[1]) - ay * (ey[0] - o[0])) < 0;
                var turn = (mirrored ? Math.atan2(-ay, -ax) : Math.atan2(ay, ax)) * 180 / Math.PI;
                var rot = Math.round((mirrored ? turn - (l.rot || 0) : turn + (l.rot || 0)) * 1e6) / 1e6;

                rot = ((rot % 360) + 360) % 360;
                rot = (rot > 180) ? rot - 360 : rot;
                p = fn(l.x + l.w / 2, l.y + l.h / 2);
                l.x = p[0] - l.w / 2;
                l.y = p[1] - l.h / 2;

                if (rot || (l.rot !== undefined)) {
                    l.rot = rot;
                }

                if (mirrored) {
                    if (l.mirror) {
                        delete l.mirror;
                    } else {
                        l.mirror = true;
                    }
                }
            }
        }

        return l;
    }

    // A copy of doc in a new orientation (geometry patch: rot, flipX, flipY):
    // the crop box and the layers move with the picture.
    function reorient(doc, patch) {
        var d = clone(doc), before = sourceTurns(doc);

        Object.keys(patch).forEach(function (k) { d.geometry[k] = patch[k]; });

        var after = sourceTurns(d), map = mul(after.fr, inv(before.fr));
        var fn = function (x, y) { return apply(map, x, y); };

        if (d.geometry.crop) {
            var c = d.geometry.crop, p = fn(c.x, c.y), q = fn(c.x + c.w, c.y + c.h);

            d.geometry.crop = { x: Math.min(p[0], q[0]), y: Math.min(p[1], q[1]), w: Math.abs(q[0] - p[0]), h: Math.abs(q[1] - p[1]) };
        }

        if (d.geometry.output && ((before.rot % 180) !== (after.rot % 180))) {
            d.geometry.output = { w: d.geometry.output.h, h: d.geometry.output.w };
        }

        // A picture placed on its canvas turns alone around its middle: the
        // canvas and the layers stay, the redaction areas go with it.
        if (doc.canvas && doc.canvas.picture) {
            var p0 = pictureBox(doc), c1 = cropBox(d), k = d.canvas.picture.k || 1, a0 = placeMatrix(doc);

            d.canvas.picture.x = p0.x + p0.w / 2 - (c1.x + c1.w / 2) * k;
            d.canvas.picture.y = p0.y + p0.h / 2 - (c1.y + c1.h / 2) * k;

            var mv = mul(placeMatrix(d), mul(map, inv(a0)));

            d.layers = d.layers.map(function (l) { return (l.type === 'redact') ? mapLayer(l, function (x, y) { return apply(mv, x, y); }) : l; });
            keepCanvas(doc, d);

            return d;
        }

        d.layers = d.layers.map(function (l) { return mapLayer(l, fn); });

        return d;
    }

    // A quarter turn of what is on screen, clockwise for dir 1: under one
    // flip the picture's own turn goes the other way.
    function turnDocument(doc, dir) {
        var g = doc.geometry, sign = (g.flipX !== g.flipY) ? -1 : 1;

        return reorient(doc, { rot: ((g.rot + 90 * dir * sign) % 360 + 360) % 360 });
    }

    // A mirror of what is on screen: axis 'x' left to right, 'y' top to bottom.
    function flipDocument(doc, axis) {
        var g = doc.geometry;

        return reorient(doc, (axis === 'y') ? { flipY: !g.flipY } : { flipX: !g.flipX });
    }

    // The size the picture is saved at: the output size, else the crop box.
    function outputSize(doc) {
        var o = doc.geometry.output, c = cropBox(doc);

        return o ? { w: o.w, h: o.h } : { w: Math.round(c.w), h: Math.round(c.h) };
    }

    // ---- colour ----

    // The adjustments: whole numbers in [min, max], 0 changes nothing. The
    // first five are the panel's sliders, the rest sit under Details.
    var ADJUST = [
        ['brightness', -100, 100], ['contrast', -100, 100], ['saturation', -100, 100], ['exposure', -100, 100],
        ['temperature', -100, 100], ['gamma', -100, 100], ['vignette', 0, 100], ['sharpen', 0, 100]
    ];

    // The filters are fixed values of the same numbers, added to the
    // sliders' (includes/fn/image_editor.php keeps the same list).
    var FILTERS = {
        none: {},
        mono: { saturation: -100 },
        sepia: { saturation: -100, temperature: 60, contrast: -8, brightness: 4 },
        cool: { temperature: -35, saturation: 8, brightness: 2 },
        warm: { temperature: 35, saturation: 15 },
        fade: { contrast: -28, brightness: 8, saturation: -30 },
        vivid: { contrast: 15, saturation: 45 },
        noir: { saturation: -100, contrast: 50, brightness: -6 },
        chrome: { contrast: 22, saturation: 22, exposure: 6 }
    };
    var FILTER_NAMES = ['none', 'mono', 'sepia', 'cool', 'warm', 'fade', 'vivid', 'noir', 'chrome'];

    // The numbers the colour step of a document uses: its adjustments plus
    // its filter's, each kept in range.
    function colorValues(doc) {
        var a = doc.adjust || {}, f = FILTERS[doc.filter] || {}, out = {};

        ADJUST.forEach(function (d) {
            out[d[0]] = clamp(Math.round((Number(a[d[0]]) || 0) + (f[d[0]] || 0)), d[1], d[2]);
        });

        return out;
    }

    // An affine colour map as rows R, G, B of [r, g, b, offset]; a after b.
    function affine(a, b) {
        return a.map(function (row) {
            return [0, 1, 2, 3].map(function (c) {
                return row[0] * b[0][c] + row[1] * b[1][c] + row[2] * b[2][c] + ((c === 3) ? row[3] : 0);
            });
        });
    }

    function gains(r, g, b, offset) {
        return [[r, 0, 0, offset], [0, g, 0, offset], [0, 0, b, offset]];
    }

    // The colour step of values v on sRGB values from 0 to 1, in this order:
    // exposure scales, brightness lifts, contrast spreads around the middle,
    // saturation moves from or towards grey (Rec. 709 luma), temperature
    // warms or cools (after saturation, so a grey picture takes a tint: the
    // sepia filter); then gamma. Answers feColorMatrix's 20 numbers (rows
    // R, G, B, A; offsets from 0 to 1) and the gamma exponent, or null when
    // nothing changes. The server reads the same numbers (pg_image_edit_color()).
    function colorMatrix(v) {
        if (!v.exposure && !v.temperature && !v.brightness && !v.contrast && !v.saturation && !v.gamma) {
            return null;
        }

        var e = Math.pow(2, v.exposure / 100), t = v.temperature / 100, b = 0.25 * v.brightness / 100;
        var c = Math.pow(2, v.contrast / 100), s = 1 + v.saturation / 100, lr = 0.2126, lg = 0.7152, lb = 0.0722;
        var m = gains(e, e, e, 0);

        m = affine(gains(1, 1, 1, b), m);
        m = affine(gains(c, c, c, 0.5 * (1 - c)), m);
        m = affine([
            [lr + (1 - lr) * s, lg - lg * s, lb - lb * s, 0],
            [lr - lr * s, lg + (1 - lg) * s, lb - lb * s, 0],
            [lr - lr * s, lg - lg * s, lb + (1 - lb) * s, 0]
        ], m);
        m = affine(gains(1 + 0.2 * t, 1 + 0.04 * t, 1 - 0.2 * t, 0), m);

        var round = function (x) { return Math.round(x * 1e6) / 1e6; };

        return {
            matrix: [m[0][0], m[0][1], m[0][2], 0, m[0][3], m[1][0], m[1][1], m[1][2], 0, m[1][3], m[2][0], m[2][1], m[2][2], 0, m[2][3], 0, 0, 0, 1, 0].map(round),
            gamma: round(Math.pow(2, -v.gamma / 100))
        };
    }

    // The colour step on RGBA bytes (ImageData's), in place: the matrix,
    // cut to 0-255, then gamma through a table. Draws the filter tiles and
    // the colour layer a server without Imagick saves from.
    function applyColor(data, color) {
        var m = color.matrix, lut = [], i, r, g, b;
        var at = function (v) { return lut[(v < 0) ? 0 : ((v > 255) ? 255 : Math.round(v))]; };

        for (i = 0; i < 256; i++) {
            lut[i] = Math.round(255 * Math.pow(i / 255, color.gamma));
        }

        for (i = 0; i < data.length; i += 4) {
            r = data[i];
            g = data[i + 1];
            b = data[i + 2];
            data[i] = at(m[0] * r + m[1] * g + m[2] * b + m[4] * 255);
            data[i + 1] = at(m[5] * r + m[6] * g + m[7] * b + m[9] * 255);
            data[i + 2] = at(m[10] * r + m[11] * g + m[12] * b + m[14] * 255);
        }

        return data;
    }

    // The vignette: black over the picture whose opacity grows from 40 % of
    // the way to the corners (r, 1 at a corner) to 80 % at full strength;
    // [r, opacity] stops for a CSS radial gradient and the server's mask.
    function vignetteStops(value) {
        var a = 0.8 * clamp(value || 0, 0, 100) / 100;

        return a ? [[0.4, 0], [0.7, Math.round(0.3 * a * 1e4) / 1e4], [1, Math.round(a * 1e4) / 1e4]] : null;
    }

    // Sharpening: an unsharp mask over a 3 x 3 binomial blur, as a kernel
    // (row by row, sum 1); null for 0.
    function sharpenKernel(value) {
        var a = 1.5 * clamp(value || 0, 0, 100) / 100;

        if (!a) {
            return null;
        }

        var corner = -a / 16, edge = -a / 8;

        return [corner, edge, corner, edge, 1 + 0.75 * a, edge, corner, edge, corner].map(function (x) { return Math.round(x * 1e6) / 1e6; });
    }

    // ---- layers ----

    // A layer id not yet in the document.
    function newId(doc) {
        var n = doc.layers.length;

        while (doc.layers.some(function (l) { return l.id === 'l' + n; })) {
            n++;
        }

        return 'l' + n;
    }

    // The corners of a box layer, turned by its angle (rot, degrees
    // clockwise) around its centre.
    function corners(l) {
        var m = mul(translate(l.x + l.w / 2, l.y + l.h / 2), mul(rotate(l.rot || 0), translate(-l.w / 2, -l.h / 2)));

        return [[0, 0], [l.w, 0], [l.w, l.h], [0, l.h]].map(function (p) { return apply(m, p[0], p[1]); });
    }

    // The upright box around layers.
    function bounds(list) {
        var xs = [], ys = [];

        list.forEach(function (l) {
            corners(l).forEach(function (p) {
                xs.push(p[0]);
                ys.push(p[1]);
            });
        });

        if (!xs.length) {
            return null;
        }

        var x = Math.min.apply(null, xs), y = Math.min.apply(null, ys);

        return { x: x, y: y, w: Math.max.apply(null, xs) - x, h: Math.max.apply(null, ys) - y };
    }

    // A point in a layer's own frame: 0, 0 its top left before its angle.
    function local(l, x, y) {
        return apply(mul(translate(l.w / 2, l.h / 2), mul(rotate(-(l.rot || 0)), translate(-l.x - l.w / 2, -l.y - l.h / 2))), x, y);
    }

    // The topmost visible, unlocked layer under x, y (slack in document units).
    // A press inside a layer's box hits it unless test(l, p, slack), given
    // the point in the layer's own frame, says no: a line is thinner than
    // its box.
    // A warped or pinned layer is tested on its drawn extent (see
    // drawnExtent()), so a press on a part bent out of the box still hits.
    function hitLayer(doc, x, y, slack, test) {
        for (var i = doc.layers.length - 1; i >= 0; i--) {
            var l = doc.layers[i], p, e;

            if (l.hidden || l.locked) {
                continue;
            }

            p = local(l, x, y);
            e = drawnExtent(l);

            if ((p[0] >= e[0] - slack) && (p[1] >= e[1] - slack) && (p[0] <= e[2] + slack) && (p[1] <= e[3] + slack)
                    && (!test || test(l, p, slack))) {
                return l;
            }
        }

        return null;
    }

    // [x0, y0, x1, y1] in the layer's own frame (as local() gives it, the
    // mirror applied): its box, joined for a warped or pinned layer with the
    // extent of the outline it draws, which can reach out of the box.
    function drawnExtent(l) {
        var e = [0, 0, l.w, l.h];

        if (warpOn(l) || puppetOn(l)) {
            warpContours(l).forEach(function (c) {
                c.pts.forEach(function (q) {
                    var u = l.mirror ? l.w - q[0] : q[0];

                    e[0] = Math.min(e[0], u);
                    e[1] = Math.min(e[1], q[1]);
                    e[2] = Math.max(e[2], u);
                    e[3] = Math.max(e[3], q[1]);
                });
            });
        }

        return e;
    }

    // The ids of the visible, unlocked layers whose turned box (its drawn
    // extent when warped or pinned) meets box { x, y, w, h } (a selection
    // box dragged over the stage), bottom to top.
    function layersInBox(doc, box) {
        return doc.layers.filter(function (l) {
            var b = (!l.hidden && !l.locked) ? drawnBounds(l) : null;

            return !!b && (b.x <= box.x + box.w) && (b.x + b.w >= box.x) && (b.y <= box.y + box.h) && (b.y + b.h >= box.y);
        }).map(function (l) { return l.id; });
    }

    // The upright box around a layer's drawn extent in the rotated space.
    function drawnBounds(l) {
        if (!warpOn(l) && !puppetOn(l)) {
            return bounds([l]);
        }

        var e = drawnExtent(l), m = mul(translate(l.x + l.w / 2, l.y + l.h / 2), mul(rotate(l.rot || 0), translate(-l.w / 2, -l.h / 2)));
        var ps = [[e[0], e[1]], [e[2], e[1]], [e[2], e[3]], [e[0], e[3]]].map(function (p) { return apply(m, p[0], p[1]); });
        var xs = ps.map(function (p) { return p[0]; }), ys = ps.map(function (p) { return p[1]; });
        var x = Math.min.apply(null, xs), y = Math.min.apply(null, ys);

        return { x: x, y: y, w: Math.max.apply(null, xs) - x, h: Math.max.apply(null, ys) - y };
    }

    // Whether x, y lies inside a layer's box by more than margin on every
    // side, in the layer's own turned frame.
    function insideBox(l, x, y, margin) {
        var p = local(l, x, y);

        return (p[0] > margin) && (p[1] > margin) && (p[0] < l.w - margin) && (p[1] < l.h - margin);
    }

    // A box layer resized by dragging handle h (nw, n, ne, e, se, s, sw, w)
    // to x, y: the opposite edge or corner stays where it is in the layer's
    // own turned frame; keep holds the ratio; no side under min.
    function resizeBox(l, h, x, y, keep, min) {
        var p = local(l, x, y), x0 = 0, y0 = 0, x1 = l.w, y1 = l.h;

        if (h.indexOf('w') >= 0) { x0 = Math.min(p[0], l.w - min); }
        if (h.indexOf('e') >= 0) { x1 = Math.max(p[0], min); }
        if (h.indexOf('n') >= 0) { y0 = Math.min(p[1], l.h - min); }
        if (h.indexOf('s') >= 0) { y1 = Math.max(p[1], min); }

        if (keep && (h.length === 2)) {
            var k = Math.max((x1 - x0) / l.w, (y1 - y0) / l.h);

            if (h.indexOf('w') >= 0) { x0 = l.w - l.w * k; } else { x1 = l.w * k; }
            if (h.indexOf('n') >= 0) { y0 = l.h - l.h * k; } else { y1 = l.h * k; }
        }

        // The new box's centre, from the layer's frame back to the document.
        var m = mul(translate(l.x + l.w / 2, l.y + l.h / 2), mul(rotate(l.rot || 0), translate(-l.w / 2, -l.h / 2)));
        var c = apply(m, (x0 + x1) / 2, (y0 + y1) / 2), w = x1 - x0, hh = y1 - y0;

        return { x: c[0] - w / 2, y: c[1] - hh / 2, w: w, h: hh };
    }

    // The angle that turns l's top towards x, y (degrees clockwise, in
    // (-180, 180]); snap gives steps of 15.
    function angleTo(l, x, y, snap) {
        var a = Math.atan2(x - (l.x + l.w / 2), (l.y + l.h / 2) - y) * 180 / Math.PI;

        if (snap) {
            a = Math.round(a / 15) * 15;
        }

        return (a <= -180) ? a + 360 : ((a > 180) ? a - 360 : a);
    }

    // Moves that line boxes up to target: left, center, right, top, middle
    // or bottom.
    function align(boxes, how, target) {
        return boxes.map(function (b) {
            var dx = 0, dy = 0;

            if (how === 'left') { dx = target.x - b.x; }
            if (how === 'center') { dx = target.x + target.w / 2 - (b.x + b.w / 2); }
            if (how === 'right') { dx = target.x + target.w - (b.x + b.w); }
            if (how === 'top') { dy = target.y - b.y; }
            if (how === 'middle') { dy = target.y + target.h / 2 - (b.y + b.h / 2); }
            if (how === 'bottom') { dy = target.y + target.h - (b.y + b.h); }

            return [dx, dy];
        });
    }

    // Moves that space three or more boxes evenly along axis x or y, the
    // first and the last staying.
    function distribute(boxes, axis) {
        var pos = (axis === 'y') ? 'y' : 'x', size = (axis === 'y') ? 'h' : 'w';
        var order = boxes.map(function (b, i) { return i; }).sort(function (a, b) { return boxes[a][pos] - boxes[b][pos]; });
        var first = boxes[order[0]], last = boxes[order[order.length - 1]], total = 0, out = boxes.map(function () { return [0, 0]; });

        order.forEach(function (i) { total += boxes[i][size]; });

        var gap = (last[pos] + last[size] - first[pos] - total) / Math.max(1, boxes.length - 1), at = first[pos];

        order.forEach(function (i) {
            out[i][(pos === 'x') ? 0 : 1] = at - boxes[i][pos];
            at += boxes[i][size] + gap;
        });

        return out;
    }

    // Snapping a moved box: the shortest pull within tol that lines one of
    // its edges or its centre up with a line of xs or ys; the lines it meets
    // are the guides.
    function snapBox(box, xs, ys, tol) {
        var pull = function (mine, lines) {
            var best = null;

            mine.forEach(function (m) {
                lines.forEach(function (line) {
                    var d = line - m;

                    if ((Math.abs(d) <= tol) && ((best === null) || (Math.abs(d) < Math.abs(best)))) {
                        best = d;
                    }
                });
            });

            return best || 0;
        };
        var dx = pull([box.x, box.x + box.w / 2, box.x + box.w], xs), dy = pull([box.y, box.y + box.h / 2, box.y + box.h], ys);
        var near = function (v, lines) { return lines.filter(function (line) { return Math.abs(line - v) < 0.01; }); };

        return {
            dx: dx,
            dy: dy,
            gx: near(box.x + dx, xs).concat(near(box.x + dx + box.w / 2, xs), near(box.x + dx + box.w, xs)),
            gy: near(box.y + dy, ys).concat(near(box.y + dy + box.h / 2, ys), near(box.y + dy + box.h, ys))
        };
    }

    // The layers in a new order: ids moved up or down one place, or to the
    // top or the bottom.
    function reorder(layers, ids, how) {
        var list = layers.slice(), picked = function (l) { return ids.indexOf(l.id) >= 0; };

        if ((how === 'top') || (how === 'bottom')) {
            var moved = list.filter(picked), rest = list.filter(function (l) { return !picked(l); });

            return (how === 'top') ? rest.concat(moved) : moved.concat(rest);
        }

        var step = (how === 'up') ? 1 : -1, i = (step > 0) ? list.length - 2 : 1;

        for (; (i >= 0) && (i < list.length); i -= step) {
            if (picked(list[i]) && !picked(list[i + step])) {
                var swap = list[i];

                list[i] = list[i + step];
                list[i + step] = swap;
            }
        }

        return list;
    }

    // Whether the focused element uses the key itself, so the editor leaves
    // it alone. Enter and Space (key ' '): a button, a link, a text field, a
    // checkbox's Space get pressed; on the stage, a slider or the dialog
    // they stay the editor's (apply the crop, pan). The stepping keys
    // (arrows, Home, End, Page Up, Page Down) belong to a slider or a radio
    // button, which change their value with them, instead of nudging the
    // selected layers.
    // info: { tag, type, role, editable } of the focused element.
    var KEY_ROLES = /^(button|link|menuitem|menuitemradio|menuitemcheckbox|tab|option|checkbox|radio|switch|textbox|combobox)$/;
    var STEP_KEYS = /^(ArrowLeft|ArrowRight|ArrowUp|ArrowDown|Home|End|PageUp|PageDown)$/;

    function ownsKey(info, key) {
        if (info && STEP_KEYS.test(String(key))) {
            return (/^(slider|spinbutton|radio)$/.test(String(info.role || '')))
                || ((String(info.tag || '').toUpperCase() === 'INPUT') && /^(range|radio)$/.test(String(info.type || '').toLowerCase()));
        }

        if (!info || ((key !== 'Enter') && (key !== ' '))) {
            return false;
        }

        var tag = String(info.tag || '').toUpperCase(), type = String(info.type || '').toLowerCase();

        if (info.editable || /^(BUTTON|A|SUMMARY|SELECT|TEXTAREA)$/.test(tag) || KEY_ROLES.test(String(info.role || ''))) {
            return true;
        }

        if (tag !== 'INPUT') {
            return false;
        }

        if (type === 'range') {
            return false;
        }

        if (/^(checkbox|radio)$/.test(type)) {
            return key === ' ';
        }

        return true;
    }

    // Where the focus goes back after the panel is drawn again: the index in
    // the new panel's focusable controls of the one that had it, or -1 when
    // it is gone (the stage takes the focus then). before and after list
    // the controls as { name, cls }: name is the tag with its label, cls its
    // tag with its classes. The same name at the same occurrence wins (the
    // layer list moved); else the control at the old place with the same
    // tag and classes (a Hide button that now reads Show).
    function focusMatch(before, index, after) {
        var was = before && before[index];

        if (!was || !after) {
            return -1;
        }

        var nth = 0, i;

        for (i = 0; i < index; i++) {
            if (before[i].name === was.name) {
                nth++;
            }
        }

        for (i = 0; i < after.length; i++) {
            if ((after[i].name === was.name) && (nth-- === 0)) {
                return i;
            }
        }

        return (after[index] && (after[index].cls === was.cls)) ? index : -1;
    }

    // The label key of the panel's title: the layers list when it is open;
    // Select names what is selected (the layers' one type, else "Selection")
    // and, with nothing selected, the list (a canvas shows the canvas first);
    // any other tool its own name. types: the selected layers' types.
    function panelTitle(tool, types, artboard, layersOpen) {
        if (layersOpen) {
            return 'layers';
        }

        if (tool !== 'select') {
            return 'tool_' + tool;
        }

        if (!types || !types.length) {
            return artboard ? 'tool_artboard' : 'layers';
        }

        return types.every(function (type) { return type === types[0]; }) ? 'layer_' + types[0] : 'selection';
    }

    // ---- the tools' own maths ----
    // Each tool keeps its pure functions in its section here, so the tests
    // reach them under Node (tests/image_editor_<tool>_test.php), and names
    // them in its section's object; the objects join the model's names
    // below. A tool changes only its own section, so tools built side by
    // side do not touch the same lines.

    // -- drawing (pg_image_editor_draw.js) --

    // Every drawn layer is a box (x, y, w, h, rot) with its drawing in the
    // box's own frame, mirrored by mirror, so selecting, moving, resizing,
    // turning and the picture's turns treat it like any box. A line runs
    // along its box's middle from the left end to the right one (the head);
    // a pen stroke keeps its points as parts of the box (trace).
    // Style: color, stroke (width in picture pixels), fill (none, solid,
    // soft), dash (solid, dashed, dotted), radius (rect, bubble), sides
    // (polygon), points and inner (star), heads (end, both) and head (fill,
    // open, dot) of an arrow, smooth (pen, 0-10).

    var DRAW_KINDS = ['arrow', 'line', 'rect', 'ellipse', 'triangle', 'polygon', 'star', 'bubble', 'pen'];
    var LINE_KINDS = ['arrow', 'line'];
    var CLOSED_KINDS = ['rect', 'ellipse', 'triangle', 'polygon', 'star', 'bubble'];

    // Stroke widths in hundredths of the picture's longer side.
    var STROKES = { thin: 0.22, medium: 0.42, thick: 0.85 };

    function rnd(v) {
        return Math.round(v * 100) / 100;
    }

    function strokeFor(preset, w, h) {
        return Math.max(1, Math.round((STROKES[preset] || STROKES.medium) * Math.max(w, h) / 10) / 10);
    }

    // The preset nearest to a width.
    function strokePreset(width, w, h) {
        var best = 'medium', gap = Infinity;

        Object.keys(STROKES).forEach(function (name) {
            var d = Math.abs(strokeFor(name, w, h) - width);

            if (d < gap) {
                gap = d;
                best = name;
            }
        });

        return best;
    }

    // The box a drag from x0, y0 to x1, y1 draws; square keeps the sides
    // equal from the corner the drag started at.
    function shapeBox(x0, y0, x1, y1, square) {
        var dx = x1 - x0, dy = y1 - y0;

        if (square) {
            var side = Math.max(Math.abs(dx), Math.abs(dy));

            dx = (dx < 0) ? -side : side;
            dy = (dy < 0) ? -side : side;
        }

        return { x: Math.min(x0, x0 + dx), y: Math.min(y0, y0 + dy), w: Math.abs(dx), h: Math.abs(dy), rot: 0 };
    }

    // An arrow head's length and width for a line of length len.
    function arrowHead(len, stroke, head) {
        if (head === 'dot') {
            return { l: stroke * 1.6, w: stroke * 3.2 };
        }

        var hl = Math.min(len * 0.6, Math.max(stroke * 3.4, 6));

        return { l: hl, w: hl * 0.62 };
    }

    // How thick a line's box is: its stroke, or its arrow head.
    function lineThickness(kind, len, stroke, head) {
        return (kind === 'arrow') ? Math.max(stroke, arrowHead(len, stroke, head).w) : stroke;
    }

    // The box of a line from a to b ([x, y]); snap turns it to steps of 45
    // degrees around a. style: { kind, stroke, head }.
    function lineBox(a, b, snap, style) {
        var dx = b[0] - a[0], dy = b[1] - a[1], len = Math.hypot(dx, dy), ang = Math.atan2(dy, dx);

        if (snap) {
            ang = Math.round(ang / (Math.PI / 4)) * (Math.PI / 4);
        }

        var deg = Math.round(ang * 180 / Math.PI * 1e6) / 1e6, h = lineThickness(style.kind, len, style.stroke || 1, style.head);
        var cx = a[0] + Math.cos(ang) * len / 2, cy = a[1] + Math.sin(ang) * len / 2;

        return { x: cx - len / 2, y: cy - h / 2, w: len, h: h, rot: (deg === -180) ? 180 : deg };
    }

    // A point of a drawn layer's own drawing (u, v from its box's corner)
    // in the picture's rotated space.
    function drawnPoint(l, u, v) {
        var m = mul(translate(l.x + l.w / 2, l.y + l.h / 2), mul(rotate(l.rot || 0), translate(-l.w / 2, -l.h / 2)));

        return apply(m, l.mirror ? l.w - u : u, v);
    }

    // A point of the rotated space in a drawn layer's drawing.
    function drawnLocal(l, x, y) {
        var p = local(l, x, y);

        return [l.mirror ? l.w - p[0] : p[0], p[1]];
    }

    // A line's start and end (the head's end).
    function lineEnds(l) {
        return [drawnPoint(l, 0, l.h / 2), drawnPoint(l, l.w, l.h / 2)];
    }

    // The corners of a regular polygon (inner 0) or a star (inner: the
    // inner points' share of the radius), the first at the top, stretched to
    // fill a w x h box.
    function regularPoints(w, h, n, inner) {
        var steps = inner ? n * 2 : n, pts = [], i;

        for (i = 0; i < steps; i++) {
            var a = -Math.PI / 2 + i * 2 * Math.PI / steps, r = (inner && (i % 2)) ? inner : 1;

            pts.push([Math.cos(a) * r, Math.sin(a) * r]);
        }

        var xs = pts.map(function (q) { return q[0]; }), ys = pts.map(function (q) { return q[1]; });
        var x0 = Math.min.apply(null, xs), y0 = Math.min.apply(null, ys);
        var sx = w / ((Math.max.apply(null, xs) - x0) || 1), sy = h / ((Math.max.apply(null, ys) - y0) || 1);

        return pts.map(function (q) { return [Math.round((q[0] - x0) * sx * 1e6) / 1e6, Math.round((q[1] - y0) * sy * 1e6) / 1e6]; });
    }

    // The largest corner radius: half the shorter side; a bubble keeps room
    // for its tail.
    function maxRadius(l) {
        return (l.type === 'bubble') ? Math.min(l.w * 0.14, l.h * 0.4) : Math.min(l.w, l.h) / 2;
    }

    function polyD(pts) {
        return pts.map(function (p, i) { return (i ? 'L' : 'M') + rnd(p[0]) + ' ' + rnd(p[1]); }).join('') + 'Z';
    }

    function arcTo(r, x, y) {
        return r ? 'A' + rnd(r) + ' ' + rnd(r) + ' 0 0 1 ' + rnd(x) + ' ' + rnd(y) : '';
    }

    function roundedD(w, h, r) {
        if (!r) {
            return 'M0 0H' + rnd(w) + 'V' + rnd(h) + 'H0Z';
        }

        return 'M' + rnd(r) + ' 0H' + rnd(w - r) + arcTo(r, w, r) + 'V' + rnd(h - r) + arcTo(r, w - r, h)
            + 'H' + rnd(r) + arcTo(r, 0, h - r) + 'V' + rnd(r) + arcTo(r, r, 0) + 'Z';
    }

    function ellipseD(w, h) {
        var rx = Math.max(w / 2, 0.1), ry = Math.max(h / 2, 0.1);

        return 'M0 ' + rnd(ry) + 'A' + rnd(rx) + ' ' + rnd(ry) + ' 0 1 0 ' + rnd(2 * rx) + ' ' + rnd(ry)
            + 'A' + rnd(rx) + ' ' + rnd(ry) + ' 0 1 0 0 ' + rnd(ry) + 'Z';
    }

    // A speech bubble: a rounded body on the upper 80 % and a tail to the
    // lower left.
    function bubbleD(w, h, r) {
        var b = h * 0.8, t2 = Math.max(r, w * 0.16), t1 = Math.max(t2 + w * 0.12, w * 0.34);

        return 'M' + rnd(r) + ' 0H' + rnd(w - r) + arcTo(r, w, r) + 'V' + rnd(b - r) + arcTo(r, w - r, b)
            + 'H' + rnd(t1) + 'L' + rnd(w * 0.1) + ' ' + rnd(h) + 'L' + rnd(t2) + ' ' + rnd(b)
            + 'H' + rnd(r) + arcTo(r, 0, b - r) + 'V' + rnd(r) + arcTo(r, r, 0) + 'Z';
    }

    // Moving averages over a point list, window 2 * amount + 1; the ends stay.
    function smoothTrace(pts, amount) {
        var n = Math.round(clamp(Number(amount) || 0, 0, 10));

        if (!n || (pts.length < 3)) {
            return pts.slice();
        }

        return pts.map(function (p, i) {
            if (!i || (i === pts.length - 1)) {
                return p;
            }

            var k = Math.min(n, i, pts.length - 1 - i), x = 0, y = 0, j;

            for (j = i - k; j <= i + k; j++) {
                x += pts[j][0];
                y += pts[j][1];
            }

            return [x / (2 * k + 1), y / (2 * k + 1)];
        });
    }

    // A pen stroke's points in its box's frame, smoothed.
    function tracePoints(l) {
        return smoothTrace((l.trace || []).map(function (p) { return [p[0] * l.w, p[1] * l.h]; }), l.smooth);
    }

    // A point list as a curve through the middles of its segments.
    function traceD(pts) {
        if (!pts.length) {
            return '';
        }

        if (pts.length < 3) {
            return 'M' + pts.map(function (p) { return rnd(p[0]) + ' ' + rnd(p[1]); }).join('L') + ((pts.length === 1) ? 'h0.01' : '');
        }

        var d = 'M' + rnd(pts[0][0]) + ' ' + rnd(pts[0][1]), i;

        for (i = 1; i < pts.length - 1; i++) {
            d += 'Q' + rnd(pts[i][0]) + ' ' + rnd(pts[i][1]) + ' ' + rnd((pts[i][0] + pts[i + 1][0]) / 2) + ' ' + rnd((pts[i][1] + pts[i + 1][1]) / 2);
        }

        return d + 'L' + rnd(pts[pts.length - 1][0]) + ' ' + rnd(pts[pts.length - 1][1]);
    }

    // A line and its heads along the box's middle.
    function lineParts(l) {
        var w = l.w, y = l.h / 2, s = l.stroke || 1, both = (l.heads === 'both'), head = l.head || 'fill';
        var hd = arrowHead(w, s, head), cut = (head === 'fill') ? hd.l * 0.7 : ((head === 'dot') ? hd.l : 0);
        var tip = function (x, dir) {
            var bx = x - dir * hd.l;

            if (head === 'dot') {
                return 'M' + rnd(bx - dir * hd.l) + ' ' + rnd(y) + 'A' + rnd(hd.l) + ' ' + rnd(hd.l) + ' 0 1 0 ' + rnd(x) + ' ' + rnd(y)
                    + 'A' + rnd(hd.l) + ' ' + rnd(hd.l) + ' 0 1 0 ' + rnd(bx - dir * hd.l) + ' ' + rnd(y) + 'Z';
            }

            return 'M' + rnd(bx) + ' ' + rnd(y - hd.w / 2) + 'L' + rnd(x) + ' ' + rnd(y) + 'L' + rnd(bx) + ' ' + rnd(y + hd.w / 2) + ((head === 'fill') ? 'Z' : '');
        };

        if (l.type !== 'arrow') {
            return { d: 'M0 ' + rnd(y) + 'H' + rnd(w), head: null };
        }

        return {
            d: 'M' + rnd(both ? cut : 0) + ' ' + rnd(y) + 'H' + rnd(w - cut),
            head: { d: tip(w, 1) + (both ? tip(0, -1) : ''), fill: head !== 'open', stroke: (head === 'open') ? s : ((head === 'fill') ? s * 0.6 : 0) }
        };
    }

    // The one path a drawn layer has in its box's frame (0, 0 its corner),
    // for the stage's SVG and the save's canvas alike: d, the head's (an
    // arrow) and whether a fill applies.
    function shapePath(l) {
        var w = l.w, h = l.h, r = clamp(Number(l.radius) || 0, 0, maxRadius(l)), d = '';

        if (warpOn(l) || puppetOn(l)) {
            return warpShape(l);
        }

        if (l.type === 'path') {
            return pathShape(l);
        }

        if (LINE_KINDS.indexOf(l.type) >= 0) {
            var parts = lineParts(l);

            return { d: parts.d, head: parts.head, closed: false };
        }

        if (l.type === 'rect') { d = roundedD(w, h, r); }
        if (l.type === 'ellipse') { d = ellipseD(w, h); }
        if (l.type === 'triangle') { d = polyD([[w / 2, 0], [w, h], [0, h]]); }
        if (l.type === 'polygon') { d = polyD(regularPoints(w, h, clamp(Math.round(l.sides || 6), 3, 12), 0)); }
        if (l.type === 'star') { d = polyD(regularPoints(w, h, clamp(Math.round(l.points || 5), 4, 12), clamp(Number(l.inner) || 0.48, 0.2, 0.8))); }
        if (l.type === 'bubble') { d = bubbleD(w, h, r); }
        if (l.type === 'pen') { d = traceD(tracePoints(l)); }

        return { d: d, head: null, closed: CLOSED_KINDS.indexOf(l.type) >= 0 };
    }

    // The outline as point lists in the box's frame, for hits: curves are
    // walked in short steps.
    function outline(l) {
        var w = l.w, h = l.h, out = [], i, n = 48;

        if (warpOn(l) || puppetOn(l)) {
            return warpOutline(l);
        }

        if (LINE_KINDS.indexOf(l.type) >= 0) {
            return [[[0, h / 2], [w, h / 2]]];
        }

        if (l.type === 'path') {
            return [curvePoints(pathKnots(l), !!l.closed, 16)].concat(subKnots(l).map(function (sub) { return curvePoints(sub.knots, sub.closed, 16); }));
        }

        if (l.type === 'pen') {
            return [tracePoints(l)];
        }

        if (l.type === 'ellipse') {
            for (i = 0; i <= n; i++) {
                out.push([w / 2 + Math.cos(i * 2 * Math.PI / n) * w / 2, h / 2 + Math.sin(i * 2 * Math.PI / n) * h / 2]);
            }

            return [out];
        }

        if (l.type === 'triangle') { out = [[w / 2, 0], [w, h], [0, h]]; }
        if (l.type === 'polygon') { out = regularPoints(w, h, clamp(Math.round(l.sides || 6), 3, 12), 0); }
        if (l.type === 'star') { out = regularPoints(w, h, clamp(Math.round(l.points || 5), 4, 12), clamp(Number(l.inner) || 0.48, 0.2, 0.8)); }
        if (l.type === 'bubble') { out = [[0, 0], [w, 0], [w, h * 0.8], [w * 0.34, h * 0.8], [w * 0.1, h], [w * 0.16, h * 0.8], [0, h * 0.8]]; }
        if (!out.length) { out = [[0, 0], [w, 0], [w, h], [0, h]]; }

        return [out.concat([out[0]])];
    }

    function segmentDistance(p, a, b) {
        var dx = b[0] - a[0], dy = b[1] - a[1], len = dx * dx + dy * dy;
        var t = len ? clamp(((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / len, 0, 1) : 0;

        return Math.hypot(p[0] - (a[0] + t * dx), p[1] - (a[1] + t * dy));
    }

    function inside(p, pts) {
        var hit = false, i, j;

        for (i = 0, j = pts.length - 1; i < pts.length; j = i++) {
            if (((pts[i][1] > p[1]) !== (pts[j][1] > p[1]))
                    && (p[0] < (pts[j][0] - pts[i][0]) * (p[1] - pts[i][1]) / ((pts[j][1] - pts[i][1]) || 1e-9) + pts[i][0])) {
                hit = !hit;
            }
        }

        return hit;
    }

    // Whether p (in the layer's frame, as hitLayer() gives it) touches the
    // drawing: its outline within half its stroke and slack, or inside it
    // when it is filled.
    function hitShape(l, p, slack) {
        return hitLines(l, outline(l), p, slack);
    }

    // hitShape() on the outline already drawn (lines), so a caller testing
    // many points draws it once.
    function hitLines(l, lines, p, slack) {
        var q = [l.mirror ? l.w - p[0] : p[0], p[1]], reach = (l.stroke || 1) / 2 + slack, i, j;

        // A path with more contours (outlined letters) is inside any of them.
        if ((l.fill === 'solid' || l.fill === 'soft') && isClosed(l) && (l.subs ? lines : [lines[0]]).some(function (line) { return inside(q, line); })) {
            return true;
        }

        if ((l.type === 'arrow') && (q[0] >= l.w - arrowHead(l.w, l.stroke || 1, l.head).l - slack)) {
            return true;
        }

        for (i = 0; i < lines.length; i++) {
            if (lines[i].length === 1) {
                if (Math.hypot(q[0] - lines[i][0][0], q[1] - lines[i][0][1]) <= reach) {
                    return true;
                }
            }

            for (j = 1; j < lines[i].length; j++) {
                if (segmentDistance(q, lines[i][j - 1], lines[i][j]) <= reach) {
                    return true;
                }
            }
        }

        return false;
    }

    // A pen stroke's box and trace from points in the rotated space: the
    // box around them, at least a pixel each way.
    function penLayer(pts) {
        var xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });
        var x0 = Math.min.apply(null, xs), y0 = Math.min.apply(null, ys), x1 = Math.max.apply(null, xs), y1 = Math.max.apply(null, ys);
        var w = Math.max(1, x1 - x0), h = Math.max(1, y1 - y0);

        x0 = (x0 + x1) / 2 - w / 2;
        y0 = (y0 + y1) / 2 - h / 2;

        return {
            x: x0,
            y: y0,
            w: w,
            h: h,
            rot: 0,
            trace: pts.map(function (p) { return [Math.round((p[0] - x0) / w * 1e4) / 1e4, Math.round((p[1] - y0) / h * 1e4) / 1e4]; })
        };
    }

    // A pen stroke after the eraser passed x, y with radius r (coming from
    // from, a point of the document, so the points it swept over between
    // two moves go too): null when it was not touched, else the pieces left
    // (none when all went), each a new box and trace that keeps the stroke's
    // angle and mirror.
    function eraseTrace(l, x, y, r, from) {
        var c = drawnLocal(l, x, y), c0 = from ? drawnLocal(l, from[0], from[1]) : c, reach = r + (l.stroke || 1) / 2, runs = [], run = [], touched = false;

        (l.trace || []).forEach(function (t) {
            var p = [t[0] * l.w, t[1] * l.h];

            if (segmentDistance(p, c0, c) <= reach) {
                touched = true;

                if (run.length) {
                    runs.push(run);
                }

                run = [];
            } else {
                run.push(p);
            }
        });

        if (run.length) {
            runs.push(run);
        }

        if (!touched) {
            return null;
        }

        return runs.map(function (pts) {
            var piece = penLayer(pts), centre = drawnPoint(l, piece.x + piece.w / 2, piece.y + piece.h / 2);

            return {
                x: centre[0] - piece.w / 2,
                y: centre[1] - piece.h / 2,
                w: piece.w,
                h: piece.h,
                rot: l.rot || 0,
                mirror: !!l.mirror,
                trace: piece.trace
            };
        });
    }

    // -- the eraser on shapes and paths --

    // A drawn shape or a path loses what the eraser passes over as a pen
    // stroke does, but through a mask: the layer keeps its type, style and
    // size. l.erase holds a stroke per drag over the layer, { w, h, r, pts }:
    // the eraser's path in the layer's frame before the mirror, in pixels of
    // the w x h box the layer had then (a later resize stretches the mask
    // with the drawing), r its radius in those pixels.
    var ERASE_POINTS = 4000;

    // Whether the eraser masks l: a drawn shape (a pen stroke it cuts,
    // eraseTrace()) or a path.
    function erasable(l) {
        return !!l && ((l.type === 'path') || ((DRAW_KINDS.indexOf(l.type) >= 0) && (l.type !== 'pen')));
    }

    function eraseValid(s) {
        var num = function (n) { return (typeof n === 'number') && isFinite(n); };

        return !!s && num(s.w) && num(s.h) && num(s.r) && (s.w > 0) && (s.h > 0) && (s.r > 0) && Array.isArray(s.pts) && (s.pts.length > 0)
            && s.pts.every(function (p) { return Array.isArray(p) && (p.length === 2) && num(p[0]) && num(p[1]); });
    }

    // The mask's strokes a drawing may use: none on a layer the eraser does
    // not mask, a broken one left out.
    function eraseStrokes(l) {
        return (erasable(l) && Array.isArray(l.erase)) ? l.erase.filter(eraseValid) : [];
    }

    function eraseOn(l) {
        return eraseStrokes(l).length > 0;
    }

    // Whether an eraser of radius r moving from a to b (points of the
    // document) touches l's drawing as hitShape() tests it, in steps of half
    // the radius. A layer without a warp is passed over when the move stays
    // away from its box.
    function eraseTouches(l, a, b, r) {
        var reach = r + (l.stroke || 1), pa = local(l, a[0], a[1]), pb = local(l, b[0], b[1]), lines, n, i;

        if (!l.warp && !l.puppet && ((Math.max(pa[0], pb[0]) < -reach) || (Math.min(pa[0], pb[0]) > l.w + reach)
                || (Math.max(pa[1], pb[1]) < -reach) || (Math.min(pa[1], pb[1]) > l.h + reach))) {
            return false;
        }

        lines = outline(l);
        n = Math.min(200, Math.max(1, Math.ceil(Math.hypot(b[0] - a[0], b[1] - a[1]) / Math.max(1, r / 2))));

        for (i = 0; i <= n; i++) {
            if (hitLines(l, lines, [pa[0] + (pb[0] - pa[0]) * i / n, pa[1] + (pb[1] - pa[1]) * i / n], r)) {
                return true;
            }
        }

        return false;
    }

    // The point x, y of the document in l's frame before the mirror.
    function eraseLocal(l, x, y) {
        var p = drawnLocal(l, x, y);

        return [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10];
    }

    // l's mask after an eraser of radius r moved from a to b: b goes onto
    // the stroke at index i (the drag's own) unless it lies within a quarter
    // of the radius of its last point; without one, a new stroke from a to
    // b. Answers { erase, i }, i the stroke the drag goes on with.
    function eraseAdd(l, a, b, r, i) {
        var list = Array.isArray(l.erase) ? l.erase.slice() : [], s = ((i !== null) && (i !== undefined) && eraseValid(list[i])) ? list[i] : null;
        var p = eraseLocal(l, b[0], b[1]), last;

        if (s) {
            last = s.pts[s.pts.length - 1];

            if (Math.hypot(p[0] - last[0], p[1] - last[1]) < s.r / 4) {
                return { erase: list, i: i };
            }

            if (s.pts.length < ERASE_POINTS) {
                list[i] = { w: s.w, h: s.h, r: s.r, pts: s.pts.concat([p]) };

                return { erase: list, i: i };
            }

            list.push({ w: l.w, h: l.h, r: s.r, pts: [last, p] });

            return { erase: list, i: list.length - 1 };
        }

        list.push({ w: l.w, h: l.h, r: Math.round(r * 10) / 10, pts: [eraseLocal(l, a[0], a[1]), p] });

        return { erase: list, i: list.length - 1 };
    }

    // l's mask in a new box of the same angle and mirror (x0, y0, w, h in
    // the old box's frame before the mirror, as refitPath() and warpExpand()
    // fit one): the strokes stay where they were on the document.
    function eraseShift(l, x0, y0, w, h) {
        return eraseStrokes(l).map(function (s) {
            var sx = l.w / s.w, sy = l.h / s.h;

            return {
                w: w,
                h: h,
                r: Math.round(s.r * Math.sqrt(sx * sy) * 10) / 10,
                pts: s.pts.map(function (p) { return [Math.round((p[0] * sx - x0) * 10) / 10, Math.round((p[1] * sy - y0) * 10) / 10]; })
            };
        });
    }

    // Whether a fill applies: a closed kind, or a closed vector path.
    function isClosed(l) {
        return (CLOSED_KINDS.indexOf(l.type) >= 0) || ((l.type === 'path') && !!l.closed);
    }

    // The stroke's dash pattern, or null for a solid line.
    function dashFor(l) {
        var s = l.stroke || 1;

        if (l.dash === 'dashed') {
            return [rnd(s * 2.4), rnd(s * 1.9)];
        }

        return (l.dash === 'dotted') ? [0.01, rnd(s * 2)] : null;
    }

    // The fill of a closed shape: its colour, a tint of it, or none.
    function fillFor(l) {
        var hex = /^#([0-9a-f]{6})$/i.exec(l.color || ''), n = hex ? parseInt(hex[1], 16) : 0;

        if (!isClosed(l) || ((l.fill !== 'solid') && (l.fill !== 'soft'))) {
            return 'none';
        }

        return (l.fill === 'solid') ? (l.color || '#000000') : 'rgba(' + ((n >> 16) & 255) + ', ' + ((n >> 8) & 255) + ', ' + (n & 255) + ', 0.28)';
    }

    var DRAW = {
        DRAW_KINDS: DRAW_KINDS, LINE_KINDS: LINE_KINDS, CLOSED_KINDS: CLOSED_KINDS, STROKES: STROKES, strokeFor: strokeFor,
        strokePreset: strokePreset, shapeBox: shapeBox, arrowHead: arrowHead, lineThickness: lineThickness, lineBox: lineBox,
        drawnPoint: drawnPoint, drawnLocal: drawnLocal, lineEnds: lineEnds, regularPoints: regularPoints, maxRadius: maxRadius,
        smoothTrace: smoothTrace, shapePath: shapePath, outline: outline, hitShape: hitShape, penLayer: penLayer,
        eraseTrace: eraseTrace, dashFor: dashFor, fillFor: fillFor, ERASE_POINTS: ERASE_POINTS, erasable: erasable, eraseStrokes: eraseStrokes,
        eraseOn: eraseOn, eraseTouches: eraseTouches, eraseAdd: eraseAdd, eraseShift: eraseShift
    };

    // -- text (pg_image_editor_text.js) --

    // A text layer: its box (x, y, w, h, rot), text, font (a key of
    // TEXT_FONTS or 'g:' and a site font's family), size (px of the rotated
    // space), weight, italic, align, lineHeight (times the size), spacing,
    // pad, radius, outline and shadow sizes (em: times the size, so a resize
    // scales them with the letters), colour, bg (a colour or ''), outline
    // ({ color, width } or null), shadow ({ color, blur, dx, dy } or null).
    var TEXT_FONTS = {
        sans: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        serif: 'Georgia, "Times New Roman", Times, serif',
        hand: '"Segoe Print", "Bradley Hand", "Comic Sans MS", "Chalkboard SE", cursive',
        display: 'Impact, Haettenschweiler, "Arial Narrow Bold", "Franklin Gothic Bold", sans-serif',
        mono: 'ui-monospace, Menlo, Consolas, "Liberation Mono", monospace'
    };

    var TEXT_WEIGHTS = [300, 400, 600, 700, 900];

    // size 0: the editor picks one from the picture's size.
    function textDefaults() {
        return {
            text: '', font: 'sans', size: 0, weight: 700, italic: false, align: 'left', lineHeight: 1.25, spacing: 0,
            color: '#ffffff', bg: '', pad: 0.3, radius: 0.16, outline: null, shadow: null
        };
    }

    // A site font's family as typed in a style: letters, digits, spaces and
    // a few marks, so it can sit quoted in a font string.
    function textFamilyName(name) {
        var s = String(name || '').replace(/[^A-Za-z0-9 _\-.]/g, '').replace(/\s+/g, ' ').trim();

        return s.slice(0, 60);
    }

    // The CSS family list of a layer's font.
    function textFamily(font) {
        var f = String(font || '');

        if (f.slice(0, 2) === 'g:' && textFamilyName(f.slice(2))) {
            return '"' + textFamilyName(f.slice(2)) + '", ' + TEXT_FONTS.sans;
        }

        return TEXT_FONTS[f] || TEXT_FONTS.sans;
    }

    // The canvas font string of a layer (also what document.fonts loads).
    function textFont(l) {
        var w = Number(l.weight) || 400;

        return (l.italic ? 'italic ' : '') + w + ' ' + textRound(Math.max(0.5, Number(l.size) || 1)) + 'px ' + textFamily(l.font);
    }

    function textRound(n) {
        return Math.round(n * 100) / 100;
    }

    // The one line layout the stage and the saved overlay both draw:
    // measure(font, string) is the width of a string, metrics(font, size)
    // the font's { ascent, descent } in px (else 0.8 and 0.2 of the size).
    // Lines break only at a new line. Letter spacing goes after each
    // character but the last; with it every character gets its own x,
    // measured over the line up to it so kerning stays.
    // Answers the box size (w, h), the padding and, per line, its text, x
    // from the box's left, baseline y and width, and chars [[c, x]] when
    // spaced.
    function textLayout(l, measure, metrics) {
        var size = Math.max(0.5, Number(l.size) || 1), font = textFont(l);
        var lh = size * (Number(l.lineHeight) || 1.25), spacing = (Number(l.spacing) || 0) * size;
        var pad = l.bg ? Math.max(0, Number(l.pad) || 0) * size : 0;
        var m = metrics ? metrics(font, size) : null;
        var asc = (m && m.ascent > 0) ? m.ascent : size * 0.8, desc = (m && m.descent >= 0) ? m.descent : size * 0.2;
        var lines = String(l.text || '').split('\n'), widest = 0;

        var out = lines.map(function (text, i) {
            var chars = null, width;

            if (spacing && text.length) {
                var list = Array.from(text), at = '';

                chars = list.map(function (c, j) {
                    var x = measure(font, at) + j * spacing;

                    at += c;

                    return [c, textRound(x)];
                });
                width = measure(font, text) + (list.length - 1) * spacing;
            } else {
                width = text.length ? measure(font, text) : 0;
            }

            width = Math.max(0, width);
            widest = Math.max(widest, width);

            return { text: text, width: width, chars: chars, base: textRound(pad + i * lh + (lh - asc - desc) / 2 + asc) };
        });

        // An empty text keeps room for the caret.
        var inner = Math.max(widest, size * 0.6);

        out.forEach(function (line) {
            var free = inner - line.width;

            line.x = textRound(pad + ((l.align === 'center') ? free / 2 : ((l.align === 'right') ? free : 0)));
            line.width = textRound(line.width);
        });

        return { w: textRound(inner + 2 * pad), h: textRound(lines.length * lh + 2 * pad), pad: textRound(pad), lineHeight: textRound(lh), size: size, font: font, lines: out };
    }

    // The layer's box for a new layout, kept where it was: the side the
    // text is aligned to stays (the middle for centred text), the top too.
    function textFit(l, layout) {
        var w0 = Number(l.w) || 0, x = Number(l.x) || 0;

        if (w0 && (l.align === 'center')) {
            x += (w0 - layout.w) / 2;
        } else if (w0 && (l.align === 'right')) {
            x += w0 - layout.w;
        }

        return { x: textRound(x), y: Number(l.y) || 0, w: layout.w, h: layout.h };
    }

    // A handle of a text's box dragged to box (M.resizeBox()): the letters
    // scale, by the height from a corner or a top or bottom side, by the
    // width from a left or right side; the opposite corner or side stays
    // put in the layer's turned frame. Answers the fields that change.
    function textResize(start, box, h) {
        var w0 = Number(start.w) || 1, h0 = Number(start.h) || 1;
        var k = /^[ew]$/.test(h) ? box.w / w0 : box.h / h0;
        var size = clamp((Number(start.size) || 1) * k, 2, 4000);

        k = size / (Number(start.size) || 1);

        var fx = (h.indexOf('e') >= 0) ? -1 : ((h.indexOf('w') >= 0) ? 1 : 0);
        var fy = (h.indexOf('s') >= 0) ? -1 : ((h.indexOf('n') >= 0) ? 1 : 0);
        var a = (Number(start.rot) || 0) * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
        var turn = function (x, y) { return [x * cos - y * sin, x * sin + y * cos]; };
        var w1 = w0 * k, h1 = h0 * k;
        var o = turn(fx * w0 / 2, fy * h0 / 2), n = turn(fx * w1 / 2, fy * h1 / 2);
        var cx = start.x + w0 / 2 + o[0] - n[0], cy = start.y + h0 / 2 + o[1] - n[1];

        return { x: textRound(cx - w1 / 2), y: textRound(cy - h1 / 2), w: textRound(w1), h: textRound(h1), size: textRound(size) };
    }

    // A shadow or an outline of a layer in px of the rotated space.
    function textEffects(l) {
        var size = Math.max(0.5, Number(l.size) || 1), o = l.outline, s = l.shadow;

        return {
            outline: (o && o.color && (Number(o.width) > 0)) ? { color: o.color, width: textRound(Number(o.width) * size) } : null,
            shadow: (s && s.color) ? { color: s.color, blur: textRound(Math.max(0, Number(s.blur) || 0) * size), dx: textRound((Number(s.dx) || 0) * size), dy: textRound((Number(s.dy) || 0) * size) } : null,
            radius: l.bg ? textRound(Math.max(0, Number(l.radius) || 0) * size) : 0
        };
    }

    var TEXT = {
        TEXT_FONTS: TEXT_FONTS, TEXT_WEIGHTS: TEXT_WEIGHTS, textDefaults: textDefaults, textFamilyName: textFamilyName,
        textFamily: textFamily, textFont: textFont, textLayout: textLayout, textFit: textFit, textResize: textResize,
        textEffects: textEffects
    };

    // -- stickers and frames (pg_image_editor_sticker.js) --

    var STICKER = (function () {
        // Two decimals are enough for a path in document pixels.
        function n2(v) {
            return Math.round(v * 100) / 100;
        }

        function pt(x, y) {
            return n2(x) + ' ' + n2(y);
        }

        function rectD(x, y, w, h, r) {
            r = Math.max(0, Math.min(r, w / 2, h / 2));

            if (!r) {
                return 'M' + pt(x, y) + 'H' + n2(x + w) + 'V' + n2(y + h) + 'H' + n2(x) + 'Z';
            }

            return 'M' + pt(x + r, y) + 'H' + n2(x + w - r) + 'A' + n2(r) + ' ' + n2(r) + ' 0 0 1 ' + pt(x + w, y + r)
                + 'V' + n2(y + h - r) + 'A' + n2(r) + ' ' + n2(r) + ' 0 0 1 ' + pt(x + w - r, y + h)
                + 'H' + n2(x + r) + 'A' + n2(r) + ' ' + n2(r) + ' 0 0 1 ' + pt(x, y + h - r)
                + 'V' + n2(y + r) + 'A' + n2(r) + ' ' + n2(r) + ' 0 0 1 ' + pt(x + r, y) + 'Z';
        }

        function ellipseD(cx, cy, rx, ry) {
            return 'M' + pt(cx - rx, cy) + 'A' + n2(rx) + ' ' + n2(ry) + ' 0 1 0 ' + pt(cx + rx, cy)
                + 'A' + n2(rx) + ' ' + n2(ry) + ' 0 1 0 ' + pt(cx - rx, cy) + 'Z';
        }

        // A star of n points between two radii, its first point at angle a0.
        function starD(cx, cy, ro, ri, n, a0) {
            var d = '', i, a, r;

            for (i = 0; i < n * 2; i++) {
                a = a0 + i * Math.PI / n;
                r = (i % 2) ? ri : ro;
                d += (i ? 'L' : 'M') + pt(cx + r * Math.cos(a), cy + r * Math.sin(a));
            }

            return d + 'Z';
        }

        // The built-in stickers, in the panel's order: label is the string
        // key of a translated label, a fixed text, or '' for none; fill and
        // ink are the sticker's and its label's colours; w and h its size in
        // hundredths of the picture's shorter side.
        var STICKERS = [
            { kind: 'burst', label: '%20', fill: '#e53935', ink: '#ffffff', w: 16, h: 16 },
            { kind: 'pill', label: 'stickerNew', fill: '#1e88e5', ink: '#ffffff', w: 18, h: 7 },
            { kind: 'tag', label: 'stickerSale', fill: '#fb8c00', ink: '#1a1c1e', w: 22, h: 7.5 },
            { kind: 'stamp', label: 'stickerSold', fill: '#c62828', ink: '#c62828', w: 24, h: 8.5 },
            { kind: 'bubble', label: 'stickerHello', fill: '#ffffff', ink: '#1a1c1e', w: 24, h: 13 },
            { kind: 'check', label: '', fill: '#43a047', ink: '#ffffff', w: 9, h: 9 },
            { kind: 'star', label: '', fill: '#fdd835', ink: '#1a1c1e', w: 10, h: 10 },
            { kind: 'heart', label: '', fill: '#e53935', ink: '#ffffff', w: 10, h: 9 }
        ];

        // A sticker layer's drawing in its own box (before its angle):
        //   d        the filled outline (evenodd: a hole, the tag's eyelet)
        //   hd       a second path: the stamp's inner ring, the check's tick
        //   outline  true: d and hd are stroked in the sticker's colour, not
        //            filled (a stamp)
        //   tick     the stroke width of the check's tick, in hd
        //   lb       the box the label is written in, or null
        function stickerShape(l) {
            var x = l.x, y = l.y, w = l.w, h = l.h, cx = x + w / 2, cy = y + h / 2, m = Math.min(w, h), bh;

            switch (l.kind) {
            case 'burst':
                return { d: starD(cx, cy, m / 2, m / 2 * 0.84, 16, -Math.PI / 2), lb: { x: cx - m / 2, y: cy - m / 2, w: m, h: m } };
            case 'pill':
                return { d: rectD(x, y, w, h, h / 2), lb: { x: x, y: y, w: w, h: h } };
            case 'tag':
                return {
                    d: 'M' + pt(x, cy) + 'L' + pt(x + w * 0.2, y) + 'H' + n2(x + w) + 'V' + n2(y + h) + 'H' + n2(x + w * 0.2) + 'Z'
                        + ellipseD(x + w * 0.16, cy, h * 0.11, h * 0.11),
                    evenodd: true,
                    lb: { x: x + w * 0.24, y: y, w: w * 0.74, h: h }
                };
            case 'stamp':
                return {
                    d: rectD(x + h * 0.03, y + h * 0.03, w - h * 0.06, h - h * 0.06, h * 0.14),
                    hd: rectD(x + h * 0.12, y + h * 0.12, w - h * 0.24, h - h * 0.24, h * 0.08),
                    outline: true,
                    lb: { x: x, y: y, w: w, h: h }
                };
            case 'bubble':
                bh = h * 0.8;

                return {
                    d: rectD(x, y, w, bh, bh * 0.22) + 'M' + pt(x + w * 0.18, y + bh - 1) + 'L' + pt(x + w * 0.12, y + h) + 'L' + pt(x + w * 0.36, y + bh - 1) + 'Z',
                    lb: { x: x, y: y, w: w, h: bh }
                };
            case 'check':
                return {
                    d: ellipseD(cx, cy, w / 2, h / 2),
                    hd: 'M' + pt(x + w * 0.28, y + h * 0.53) + 'L' + pt(x + w * 0.44, y + h * 0.68) + 'L' + pt(x + w * 0.73, y + h * 0.36),
                    tick: m * 0.1,
                    lb: null
                };
            case 'star':
                return { d: starD(cx, cy + m * 0.04, m / 2, m / 2 * 0.45, 5, -Math.PI / 2), lb: null };
            case 'heart':
                return {
                    d: 'M' + pt(cx, y + h * 0.92)
                        + 'C' + pt(x + w * 0.08, y + h * 0.62) + ' ' + pt(x, y + h * 0.22) + ' ' + pt(x + w * 0.27, y + h * 0.1)
                        + 'C' + pt(x + w * 0.42, y + h * 0.05) + ' ' + pt(cx, y + h * 0.18) + ' ' + pt(cx, y + h * 0.3)
                        + 'C' + pt(cx, y + h * 0.18) + ' ' + pt(x + w * 0.58, y + h * 0.05) + ' ' + pt(x + w * 0.73, y + h * 0.1)
                        + 'C' + pt(x + w, y + h * 0.22) + ' ' + pt(x + w * 0.92, y + h * 0.62) + ' ' + pt(cx, y + h * 0.92) + 'Z',
                    lb: null
                };
            default:
                return { d: rectD(x, y, w, h, 0), lb: null };
            }
        }

        // The label's font in its box: em is the font's width of one
        // character in ems (measured by the browser), chars the label's
        // length. The height bounds a short label, the width a long one.
        function stickerFont(kind, lb, em, chars) {
            var len = Math.max(1, chars || 0), per = Math.max(0.2, em || 0.5);

            if (kind === 'bubble') {
                return { weight: 700, size: Math.max(1, Math.min(lb.h * 0.36, (lb.w * 0.86) / (len * per))) };
            }

            var f = (kind === 'burst') ? 0.46 : 0.56, room = (kind === 'burst') ? 0.66 : 0.84;

            return { weight: 400, size: Math.max(1, Math.min(lb.h * f, (lb.w * room) / (len * per))) };
        }

        var FRAMES = ['none', 'line', 'thick', 'polaroid', 'corners'];

        // A frame over the box of the saved picture (the crop box in the
        // rotated space): an even-odd ring filled in the frame's colour, on
        // the picture's own edge, so the saved size stays. width (1-10) sets
        // how wide it is, in parts of the shorter side. band: the ring's
        // width; pads: a polaroid's sides; null for no frame.
        function frameShape(frame, box) {
            if (!frame || !frame.kind || (frame.kind === 'none') || (FRAMES.indexOf(frame.kind) < 0)) {
                return null;
            }

            var x = box.x, y = box.y, w = box.w, h = box.h, m = Math.min(w, h), k = clamp(Number(frame.width) || 4, 1, 10);
            var outer = rectD(x, y, w, h, 0), b, p, ins, len, d, s;

            if (frame.kind === 'line' || frame.kind === 'thick') {
                b = Math.min(m / 4, Math.max(1, m * k * ((frame.kind === 'line') ? 0.002 : 0.008)));

                return { d: outer + rectD(x + b, y + b, w - 2 * b, h - 2 * b, 0), band: b };
            }

            if (frame.kind === 'polaroid') {
                s = m * (0.03 + k * 0.004);
                p = { l: s, r: s, t: s, b: Math.min(h / 3, s * 3.8) };

                return { d: outer + rectD(x + p.l, y + p.t, w - p.l - p.r, h - p.t - p.b, 0), band: s, pads: p };
            }

            // Corners: four L marks inset from the edge.
            ins = m * 0.04;
            len = m * 0.12;
            b = Math.max(1, m * k * 0.0035);
            d = '';
            [[x + ins, y + ins, 1, 1], [x + w - ins, y + ins, -1, 1], [x + w - ins, y + h - ins, -1, -1], [x + ins, y + h - ins, 1, -1]].forEach(function (c) {
                var cx = c[0], cy = c[1], sx = c[2], sy = c[3];

                d += 'M' + pt(cx, cy) + 'H' + n2(cx + sx * len) + 'V' + n2(cy + sy * b) + 'H' + n2(cx + sx * b)
                    + 'V' + n2(cy + sy * len) + 'H' + n2(cx) + 'Z';
            });

            return { d: d, band: b };
        }

        return { STICKERS: STICKERS, stickerShape: stickerShape, stickerFont: stickerFont, FRAMES: FRAMES, frameShape: frameShape };
    }());

    // -- redaction (pg_image_editor_redact.js) --

    var REDACT = {
        REDACT_MODES: ['pixel', 'blur', 'solid'],

        // The block of a pixelation and the radius (sigma) of a blur in the
        // turned picture's units: shares of its longer side
        // (pg_image_edit_redact_sizes() scales them to the output).
        redactSizes: function (strength, spaceMax) {
            return {
                block: Math.max(4, Math.round(strength / 100 * 0.035 * spaceMax)),
                sigma: Math.max(2, strength / 100 * 0.02 * spaceMax)
            };
        },

        // A brush area from the points it passed: the pen's box and trace,
        // with its width.
        brushLayer: function (pts, size) {
            var geo = penLayer(pts);

            geo.shape = 'brush';
            geo.size = size;

            return geo;
        },

        // The brush's points in the turned space, its box turned and mirrored
        // as a pen stroke's is.
        brushPoints: function (l) {
            var m = mul(translate(l.x + l.w / 2, l.y + l.h / 2), mul(rotate(l.rot || 0), translate(-l.w / 2, -l.h / 2)));

            return (l.trace || []).map(function (p) { return apply(m, p[0] * l.w, p[1] * l.h); });
        },

        // How far a feathered area's soft edge reaches past its outline:
        // three sigma of a blur of half the feather.
        redactGrow: function (l) {
            return ((l.shape === 'area') && l.feather) ? 1.5 * l.feather : 0;
        },

        // The output pixels an area covers: its corners (a brush grown by
        // half its width) carried by the crop box to w x h, whole pixels,
        // inside the output; null when it lies outside.
        redactRegion: function (l, box, w, h) {
            var sx = w / box.w, sy = h / box.h, grow = (l.shape === 'brush') ? (l.size || 0) / 2 : REDACT.redactGrow(l);
            var pts = (l.shape === 'brush') ? REDACT.brushPoints(l) : corners(l);
            var xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });
            var x0 = Math.max(0, Math.floor((Math.min.apply(null, xs) - grow - box.x) * sx));
            var y0 = Math.max(0, Math.floor((Math.min.apply(null, ys) - grow - box.y) * sy));
            var x1 = Math.min(w, Math.ceil((Math.max.apply(null, xs) + grow - box.x) * sx));
            var y1 = Math.min(h, Math.ceil((Math.max.apply(null, ys) + grow - box.y) * sy));

            return ((x1 > x0) && (y1 > y0)) ? { x: x0, y: y0, w: x1 - x0, h: y1 - y0 } : null;
        }
    };

    // -- versions (pg_image_editor_versions.js) --

    var VERSIONS = {
        // A label with its number: 'Version {n}'.
        versionText: function (label, n) {
            return String(label).replace('{n}', String(n));
        },

        // The string key of a version's kind (the server's entry.kind).
        versionKindKey: function (kind) {
            return { original: 'versionOriginal', restored: 'versionRestored', redacted: 'versionRedacted' }[kind] || 'versionSaved';
        },

        // When and by whom: the server leaves user '' for the one asking.
        versionLine: function (entry, you) {
            return entry.when + ' · ' + (entry.user || you);
        },

        // A canvas's saved document (an artboard, no picture) as the editor
        // opens it again, or null when it is none.
        canvasOpenDoc: function (doc) {
            if (!doc || (typeof doc !== 'object') || (doc.v !== 1) || doc.source || !doc.artboard || (typeof doc.artboard !== 'object')
                || !doc.geometry || (typeof doc.geometry !== 'object')) {
                return null;
            }

            var next = clone(doc);

            next.source = null;
            next.artboard.width = boardSide(next.artboard.width);
            next.artboard.height = boardSide(next.artboard.height);
            next.artboard.background = (typeof next.artboard.background === 'string') ? next.artboard.background : '#ffffff';
            next.adjust = (next.adjust && (typeof next.adjust === 'object') && !Array.isArray(next.adjust)) ? next.adjust : {};
            next.layers = Array.isArray(next.layers) ? next.layers : [];
            next.filter = next.filter || 'none';
            next.frame = (next.frame === undefined) ? null : next.frame;

            // Scenes only with the open one among them (sceneList()).
            if (!Array.isArray(next.scenes) || (next.scenes.length < 2) || (sceneIndex(next) < 0)) {
                delete next.scenes;
                delete next.scene;
            }

            return next;
        },

        // The newest save's document as the editor opens it on the version
        // it was drawn on: this file's id, no base (the save request names
        // it), or null when it is no picture's document.
        versionOpenDoc: function (doc, fileId) {
            if (!doc || (typeof doc !== 'object') || (doc.v !== 1) || !doc.source || (typeof doc.source !== 'object') || !doc.geometry || doc.artboard) {
                return null;
            }

            var next = clone(doc);

            delete next.source.base;
            next.source.fileId = Number(fileId) || 0;
            next.adjust = (next.adjust && (typeof next.adjust === 'object') && !Array.isArray(next.adjust)) ? next.adjust : {};
            next.layers = Array.isArray(next.layers) ? next.layers : [];
            next.filter = next.filter || 'none';
            next.frame = (next.frame === undefined) ? null : next.frame;

            return next;
        }
    };

    // -- canvas and artboard (pg_image_editor_artboard.js) --

    // The New image dialog's sizes and the artboard tool's preset chips;
    // custom takes the width and height typed in.
    var BOARD_PRESETS = [['square', 1080, 1080], ['portrait', 1080, 1350], ['story', 1080, 1920], ['share', 1200, 630], ['banner', 1920, 600]];

    // The New image dialog's backgrounds.
    var BOARD_BACKGROUNDS = [['#ffffff', 'colorWhite'], ['transparent', 'colorTransparent'], ['#000000', 'colorBlack'], ['#e3f2fd', 'boardLightBlue']];

    function boardSide(v) {
        return Math.round(clamp(Number(v) || 0, SIZE_MIN, SIZE_MAX));
    }

    // The preset of a size, else 'custom'.
    function boardPreset(w, h) {
        for (var i = 0; i < BOARD_PRESETS.length; i++) {
            if ((BOARD_PRESETS[i][1] === w) && (BOARD_PRESETS[i][2] === h)) {
                return BOARD_PRESETS[i][0];
            }
        }

        return 'custom';
    }

    // The board of size w, h dragged by handle h (nw, n, ne, e, se, s, sw, w)
    // by dx, dy document units: the new board in the old one's units (x, y
    // its top left). keep holds the ratio, from a corner or an edge (the
    // other side grows around its middle); centre moves the opposite side the
    // other way. No side under SIZE_MIN or over SIZE_MAX.
    function boardDrag(w, h, handle, dx, dy, keep, centre) {
        var sx = 0, sy = 0, f = centre ? 2 : 1;

        if (handle.indexOf('w') >= 0) { sx = -1; }
        if (handle.indexOf('e') >= 0) { sx = 1; }
        if (handle.indexOf('n') >= 0) { sy = -1; }
        if (handle.indexOf('s') >= 0) { sy = 1; }

        var nw = sx ? clamp(w + sx * dx * f, SIZE_MIN, SIZE_MAX) : w, nh = sy ? clamp(h + sy * dy * f, SIZE_MIN, SIZE_MAX) : h;

        if (keep) {
            var k = (sx && sy) ? Math.max(nw / w, nh / h) : (sx ? nw / w : nh / h);

            k = clamp(k, Math.max(SIZE_MIN / w, SIZE_MIN / h), Math.min(SIZE_MAX / w, SIZE_MAX / h));
            nw = w * k;
            nh = h * k;
        }

        // The side that stays: the opposite one; with centre, or for a side
        // the handle does not move (it changes only to keep the ratio), the
        // middle.
        return {
            x: (centre || !sx) ? (w - nw) / 2 : ((sx < 0) ? w - nw : 0),
            y: (centre || !sy) ? (h - nh) / 2 : ((sy < 0) ? h - nh : 0),
            w: nw,
            h: nh
        };
    }

    // A board of size w, h placed on the old one (w0, h0) by an anchor (ax,
    // ay: 0 left or top, 0.5 the middle, 1 right or bottom).
    function boardAnchored(w0, h0, w, h, ax, ay) {
        w = boardSide(w);
        h = boardSide(h);

        return { x: (w0 - w) * ax, y: (h0 - h) * ay, w: w, h: h };
    }

    // The board around layers (the visible ones), in whole pixels; null
    // when there are none.
    function boardAround(layers) {
        var b = bounds(layers.filter(function (l) { return !l.hidden; }));

        if (!b) {
            return null;
        }

        var x = Math.floor(b.x), y = Math.floor(b.y);

        return { x: x, y: y, w: Math.ceil(b.x + b.w) - x, h: Math.ceil(b.y + b.h) - y };
    }

    // doc with its artboard moved to r (in the old board's units): the size
    // in whole pixels and every layer moved the other way, so the artwork
    // stays where it is on the board's old frame.
    function applyBoard(doc, r) {
        var d = clone(doc), dx = Math.round(r.x), dy = Math.round(r.y);

        d.artboard.width = boardSide(r.w);
        d.artboard.height = boardSide(r.h);

        if (dx || dy) {
            d.layers = d.layers.map(function (l) { return mapLayer(l, function (x, y) { return [x - dx, y - dy]; }); });
        }

        return d;
    }

    // -- a picture's canvas --

    // doc.canvas on a picture: { l, t, r, b, background }, how far the
    // canvas reaches past each side of the crop box (below 0 trims it), in
    // the rotated space's units; null when the canvas is the crop box. What
    // the picture does not cover takes the background.
    function canvasBox(doc) {
        var c = pictureBox(doc), m = doc.canvas;

        return m ? { x: c.x - m.l, y: c.y - m.t, w: c.w + m.l + m.r, h: c.h + m.t + m.b } : c;
    }

    // Frames that may lie outside the picture, growing the canvas:
    // [band in parts of the shorter side (per width step, plus a base), left,
    // right, top and bottom in bands] as frameShape() draws them inward.
    var FRAME_OUT = { line: [0.002, 0, 1, 1, 1, 1], thick: [0.008, 0, 1, 1, 1, 1], polaroid: [0.004, 0.03, 1, 1, 1, 3.8] };

    function frameOutward(frame) {
        return !!frame && !!frame.outside && FRAME_OUT.hasOwnProperty(frame.kind);
    }

    // The sides an outward frame adds around box: the grown box is one whose
    // inward frame (frameShape()) has box as its hole. The band is a part c of
    // the grown box's shorter side, so with W = w + (l + r) * band it is
    // c * w / (1 - c * (l + r)) for the width, the same for the height, and
    // the smaller of the two.
    function framePads(frame, box) {
        var f = FRAME_OUT[frame.kind], c = f[1] + f[0] * clamp(Number(frame.width) || 4, 1, 10);
        var band = Math.min(c * box.w / (1 - c * (f[2] + f[3])), c * box.h / (1 - c * (f[4] + f[5])));

        return { l: band * f[2], r: band * f[3], t: band * f[4], b: band * f[5] };
    }

    // The box a save draws, in the rotated space: an artboard's board, else
    // the picture's canvas grown by an outward frame.
    function saveBox(doc) {
        if (doc.artboard) {
            return { x: 0, y: 0, w: doc.artboard.width, h: doc.artboard.height };
        }

        var box = canvasBox(doc), p;

        if (frameOutward(doc.frame)) {
            p = framePads(doc.frame, box);
            box = { x: box.x - p.l, y: box.y - p.t, w: box.w + p.l + p.r, h: box.h + p.t + p.b };
        }

        return box;
    }

    // Where a picture's save lays it: the canvas in whole pixels of the
    // output (w, h), the picture's top left on it (x, y) and the fill; null
    // when the canvas is the picture's own. The server's extend step
    // (pg_image_edit_extend()).
    function saveLayout(doc) {
        if (doc.artboard || (!doc.canvas && !frameOutward(doc.frame))) {
            return null;
        }

        var b = saveBox(doc), c = cropBox(doc), p = pictureBox(doc), out = outputSize(doc), sx = out.w / c.w, sy = out.h / c.h;
        var fill = doc.canvas ? doc.canvas.background : doc.frame.color;
        var r = {
            w: Math.round(clamp(b.w * sx, 1, SIZE_MAX)), h: Math.round(clamp(b.h * sy, 1, SIZE_MAX)),
            x: Math.round((p.x - b.x) * sx), y: Math.round((p.y - b.y) * sy),
            fill: /^#[0-9a-f]{6}$/i.test(fill || '') ? fill : 'transparent'
        };

        // A picture resized on its canvas is laid at that size (pw, ph).
        if (picturePlace(doc).k !== 1) {
            r.pw = Math.round(clamp(p.w * sx, 1, SIZE_MAX));
            r.ph = Math.round(clamp(p.h * sy, 1, SIZE_MAX));
        }

        return r;
    }

    // The crop shape over box as an SVG path: the box, the box with rounded
    // corners (12 % of the shorter side, as the save cuts them) or the
    // ellipse in it. On a canvas that reaches past the picture the shape
    // cuts the picture only: the stage's mat leaves this hole.
    function cropShapePath(box, shape) {
        var x = box.x, y = box.y, w = box.w, h = box.h, rx = w / 2, ry = h / 2, r;

        if (shape === 'circle') {
            return 'M' + x + ' ' + (y + ry) + 'a' + rx + ' ' + ry + ' 0 1 0 ' + w + ' 0a' + rx + ' ' + ry + ' 0 1 0 ' + (-w) + ' 0Z';
        }

        if (shape === 'rounded') {
            r = Math.min(w, h) * 0.12;

            return 'M' + (x + r) + ' ' + y + 'H' + (x + w - r) + 'A' + r + ' ' + r + ' 0 0 1 ' + (x + w) + ' ' + (y + r)
                + 'V' + (y + h - r) + 'A' + r + ' ' + r + ' 0 0 1 ' + (x + w - r) + ' ' + (y + h)
                + 'H' + (x + r) + 'A' + r + ' ' + r + ' 0 0 1 ' + x + ' ' + (y + h - r)
                + 'V' + (y + r) + 'A' + r + ' ' + r + ' 0 0 1 ' + (x + r) + ' ' + y + 'Z';
        }

        return 'M' + x + ' ' + y + 'H' + (x + w) + 'V' + (y + h) + 'H' + x + 'Z';
    }

    // The size a save writes.
    function saveSize(doc) {
        var l = saveLayout(doc);

        return doc.artboard ? { w: doc.artboard.width, h: doc.artboard.height } : (l ? { w: l.w, h: l.h } : outputSize(doc));
    }

    // doc with its picture's canvas moved to r (in the canvas box's units,
    // as boardDrag() gives it): whole units on each side, at least SIZE_MIN
    // and at most SIZE_MAX across. The layers stay: they lie in the rotated
    // space, not on the canvas. A canvas back on the picture with a white
    // background and no place of the picture is none.
    function applyCanvas(doc, r) {
        var d = clone(doc), b = canvasBox(doc);
        var x = b.x + Math.round(r.x), y = b.y + Math.round(r.y), w = boardSide(r.w), h = boardSide(r.h);

        d.canvas = canvasAround(doc, { x: x, y: y, w: w, h: h });

        return d;
    }

    // doc.canvas holding the canvas box b around the picture's place.
    function canvasAround(doc, b) {
        var c = pictureBox(doc), m = { l: c.x - b.x, t: c.y - b.y, r: b.x + b.w - c.x - c.w, b: b.y + b.h - c.y - c.h, background: doc.canvas ? doc.canvas.background : '#ffffff' };

        ['l', 't', 'r', 'b'].forEach(function (k) { m[k] = Math.round(m[k] * 1000) / 1000; });

        if (doc.canvas && doc.canvas.picture) {
            m.picture = clone(doc.canvas.picture);
        }

        return (!m.l && !m.t && !m.r && !m.b && (m.background === '#ffffff') && !m.picture) ? null : m;
    }

    // -- the picture as the bottom layer --

    // doc.canvas.picture = { x, y, k, locked }: the picture on its canvas,
    // the crop box scaled by k and moved by x, y in the rotated space's
    // units (the layers'); without it the picture lies on its crop box.
    // Unlocked (locked false) it is moved, resized and deleted like a layer.
    function picturePlace(doc) {
        var p = doc.canvas && doc.canvas.picture;

        return p ? { x: Number(p.x) || 0, y: Number(p.y) || 0, k: Number(p.k) || 1, locked: p.locked !== false } : { x: 0, y: 0, k: 1, locked: true };
    }

    // From the crop box's space to the canvas: the picture's place.
    function placeMatrix(doc) {
        var p = picturePlace(doc);

        return mul(translate(p.x, p.y), scale(p.k));
    }

    // The picture's box on its canvas, in the layers' units.
    function pictureBox(doc) {
        var c = cropBox(doc), p = picturePlace(doc);

        return { x: c.x * p.k + p.x, y: c.y * p.k + p.y, w: c.w * p.k, h: c.h * p.k };
    }

    // From the picture's pixels to the canvas.
    function placedMatrix(doc) {
        return mul(placeMatrix(doc), pictureMatrix(doc));
    }

    // Whether the picture is unlocked: a photo whose canvas lets it move.
    function pictureFree(doc) {
        return !!doc.source && !doc.artboard && !picturePlace(doc).locked;
    }

    // The canvas of before kept around the picture of doc, in place: after a
    // crop, a turn or a move of a placed picture the canvas stays.
    function keepCanvas(before, doc) {
        if (!doc.canvas || !doc.canvas.picture || doc.artboard || !before.source || sameDocument(pictureBox(before), pictureBox(doc))) {
            return doc;
        }

        doc.canvas = canvasAround(doc, canvasBox(before));

        return doc;
    }

    // doc with its picture unlocked (a canvas made on the picture when it
    // had none) or locked again where it is.
    function lockPicture(doc, locked) {
        var d = clone(doc);

        d.canvas = d.canvas || { l: 0, t: 0, r: 0, b: 0, background: '#ffffff' };
        d.canvas.picture = d.canvas.picture || { x: 0, y: 0, k: 1 };
        d.canvas.picture.locked = !!locked;

        return d;
    }

    // doc with its picture placed on box r (the layers' units, its width
    // sets the scale, the ratio kept): the canvas and the layers stay, the
    // redaction areas, which belong to the picture's pixels, go with it.
    function placePicture(doc, r) {
        var d = clone(doc), c = cropBox(doc), p0 = picturePlace(doc), b = canvasBox(doc);
        var k = clamp(r.w / c.w, 1 / c.w, SIZE_MAX / c.w), w = c.w * k, h = c.h * k;

        // Some of it stays on the canvas, as the save's extend step asks.
        var x = clamp(r.x, b.x + 1 - w, b.x + b.w - 1), y = clamp(r.y, b.y + 1 - h, b.y + b.h - 1);
        var place = { x: Math.round((x - c.x * k) * 1000) / 1000, y: Math.round((y - c.y * k) * 1000) / 1000, k: Math.round(k * 1e6) / 1e6, locked: p0.locked };

        d.canvas = d.canvas || { l: 0, t: 0, r: 0, b: 0, background: '#ffffff' };
        d.canvas.picture = place;

        var mv = mul(placeMatrix(d), inv(placeMatrix(doc))), s = place.k / p0.k;

        d.layers = d.layers.map(function (l) {
            if (l.type !== 'redact') {
                return l;
            }

            var n = mapLayer(l, function (x, y) { return apply(mv, x, y); }), cx = n.x + n.w / 2, cy = n.y + n.h / 2;

            n.w *= s;
            n.h *= s;
            n.x = cx - n.w / 2;
            n.y = cy - n.h / 2;

            if (n.size) {
                n.size *= s;
            }

            return n;
        });

        return keepCanvas(doc, d);
    }

    // doc without its picture: the canvas with its fill and the layers
    // become an artboard of the canvas's size (in the layers' units), the
    // layers moved onto it; the picture's own (redaction areas,
    // adjustments, an outward frame) go with the picture.
    function dropPicture(doc) {
        var d = clone(doc), b = canvasBox(doc), dx = b.x, dy = b.y, bg = doc.canvas ? doc.canvas.background : '#ffffff';

        d.artboard = { width: boardSide(b.w), height: boardSide(b.h), background: (typeof bg === 'string') ? bg : '#ffffff' };
        d.source = null;
        d.canvas = null;
        d.geometry = { rot: 0, flipX: false, flipY: false, straighten: 0, crop: null, cropShape: 'rect', output: null };
        d.adjust = {};
        d.filter = 'none';

        if (d.frame) {
            delete d.frame.outside;
        }

        d.layers = d.layers.filter(function (l) { return l.type !== 'redact'; }).map(function (l) {
            return mapLayer(l, function (x, y) { return [x - dx, y - dy]; });
        });

        return d;
    }

    var ARTBOARD = {
        BOARD_PRESETS: BOARD_PRESETS, BOARD_BACKGROUNDS: BOARD_BACKGROUNDS, boardSide: boardSide, boardPreset: boardPreset,
        boardDrag: boardDrag, boardAnchored: boardAnchored, boardAround: boardAround, applyBoard: applyBoard,
        canvasBox: canvasBox, FRAME_OUT: FRAME_OUT, frameOutward: frameOutward, framePads: framePads, saveBox: saveBox,
        saveLayout: saveLayout, cropShapePath: cropShapePath, saveSize: saveSize, applyCanvas: applyCanvas,
        picturePlace: picturePlace, placeMatrix: placeMatrix, pictureBox: pictureBox, placedMatrix: placedMatrix, pictureFree: pictureFree,
        keepCanvas: keepCanvas, lockPicture: lockPicture, placePicture: placePicture, dropPicture: dropPicture
    };

    // -- export (pg_image_editor_export.js) --

    // The export panel's formats: keep writes the file's own; another is
    // saved as a copy, so a file's bytes always match its name's extension.
    var EXPORT_FORMATS = ['keep', 'jpeg', 'webp', 'avif', 'png'];

    // Longest edges offered; 0 keeps the size.
    var EXPORT_EDGES = [0, 2560, 1920, 1280, 1080];

    // A file's own format from its name: jpeg, png, webp, avif, gif or ''.
    function fileFormat(name) {
        var m = /\.([a-z0-9]+)(?:[?#].*)?$/i.exec(String(name || '')), ext = m ? m[1].toLowerCase() : '';

        return { jpg: 'jpeg', jpeg: 'jpeg', png: 'png', webp: 'webp', avif: 'avif', gif: 'gif' }[ext] || '';
    }

    // The format a save writes: the chosen one, or the file's own for keep.
    function exportFormat(choice, own) {
        return ((choice === 'keep') || (EXPORT_FORMATS.indexOf(choice) < 0)) ? (own || '') : choice;
    }

    // The settings as a save starts: the file's own format at the editor's
    // quality and size, as before there was a panel.
    function exportDefaults() {
        return { format: 'keep', quality: 92, edge: 0, colors: false, progressive: true, camera: false, srgb: false };
    }

    // w x h brought within a longest edge; never enlarged.
    function exportSize(w, h, edge) {
        var k = (edge > 0) ? Math.min(1, edge / Math.max(w, h, 1)) : 1;

        return { w: Math.max(1, Math.round(w * k)), h: Math.max(1, Math.round(h * k)) };
    }

    // The mode a save takes: a replace that would change the format is a
    // copy (the file keeps its format and its address).
    function exportMode(mode, choice, own) {
        return ((mode === 'replace') && (exportFormat(choice, own) !== own)) ? 'copy' : mode;
    }

    // What the save request carries: null when the settings change nothing.
    function exportRequest(ex, own) {
        var format = exportFormat(ex.format, own), out = {};

        if (format !== own) {
            out.format = format;
        }

        if (Math.round(ex.quality) !== 92) {
            out.quality = Math.round(clamp(Number(ex.quality) || 92, 40, 100));
        }

        if (ex.edge > 0) {
            out.edge = ex.edge;
        }

        if (ex.colors && (format === 'png')) {
            out.colors = 256;
        }

        // A JPEG is written progressive unless asked otherwise.
        if ((ex.progressive === false) && (format === 'jpeg')) {
            out.progressive = false;
        }

        // Camera details (never the location) go from a JPEG into a JPEG.
        if (ex.camera && exportCamera(format, own)) {
            out.camera = true;
        }

        if (ex.srgb) {
            out.srgb = true;
        }

        return Object.keys(out).length ? out : null;
    }

    // Whether camera details can be kept: a JPEG saved as a JPEG.
    function exportCamera(format, own) {
        return (own === 'jpeg') && (format === 'jpeg');
    }

    // The panel's result card: after against before as a signed percent
    // (negative is lighter) and the bar's width in percent (3-100).
    function exportChange(before, after) {
        if (!(before > 0) || !(after > 0)) {
            return null;
        }

        return { percent: Math.round((after / before - 1) * 100), bar: Math.max(3, Math.min(100, Math.round(after / before * 1000) / 10)) };
    }

    // A file size for the result card: B, KB with no decimals, MB with one;
    // sep is the panel's decimal separator.
    function exportBytes(n, sep) {
        n = Math.max(0, Math.round(Number(n) || 0));

        if (n < 1024) {
            return n + ' B';
        }

        if (n < 1024 * 1024) {
            return Math.round(n / 1024) + ' KB';
        }

        return String(Math.round(n / 104857.6) / 10).replace('.', sep || '.') + ' MB';
    }

    var EXPORT = {
        EXPORT_FORMATS: EXPORT_FORMATS, EXPORT_EDGES: EXPORT_EDGES, fileFormat: fileFormat, exportFormat: exportFormat,
        exportDefaults: exportDefaults, exportSize: exportSize, exportMode: exportMode, exportRequest: exportRequest,
        exportChange: exportChange, exportBytes: exportBytes, exportCamera: exportCamera
    };

    // -- vector pen and nodes (pg_image_editor_vector.js) --

    // A vector path is a box layer like a drawn one (type path): its knots
    // are kept as parts of the box (knots: [{ x, y, ix, iy, ox, oy, s }],
    // the point, the handle in, the handle out, s for a smooth knot), so
    // moving, resizing, turning and the picture's turns carry it; closed
    // joins the last knot to the first. Its style is a drawn layer's.

    var KAPPA = 0.5523;

    function knot(x, y, s) {
        return { x: x, y: y, ix: x, iy: y, ox: x, oy: y, s: !!s };
    }

    function segments(knots, closed) {
        var out = [], i;

        for (i = 1; i < knots.length; i++) {
            out.push([knots[i - 1], knots[i]]);
        }

        if (closed && (knots.length > 2)) {
            out.push([knots[knots.length - 1], knots[0]]);
        }

        return out;
    }

    // The point at t of the curve from knot a to knot b.
    function cubicAt(a, b, t) {
        var m = 1 - t;

        return [m * m * m * a.x + 3 * m * m * t * a.ox + 3 * m * t * t * b.ix + t * t * t * b.x,
            m * m * m * a.y + 3 * m * m * t * a.oy + 3 * m * t * t * b.iy + t * t * t * b.y];
    }

    function knotsD(knots, closed) {
        if (!knots.length) {
            return '';
        }

        var d = 'M' + rnd(knots[0].x) + ' ' + rnd(knots[0].y);

        segments(knots, closed).forEach(function (s) {
            d += 'C' + rnd(s[0].ox) + ' ' + rnd(s[0].oy) + ' ' + rnd(s[1].ix) + ' ' + rnd(s[1].iy) + ' ' + rnd(s[1].x) + ' ' + rnd(s[1].y);
        });

        return d + ((closed && (knots.length > 2)) ? 'Z' : ((knots.length === 1) ? 'h0.01' : ''));
    }

    // Points along the curve, n per segment.
    function curvePoints(knots, closed, n) {
        var pts = knots.length ? [[knots[0].x, knots[0].y]] : [];

        segments(knots, closed).forEach(function (s) {
            for (var k = 1; k <= n; k++) {
                pts.push(cubicAt(s[0], s[1], k / n));
            }
        });

        return pts;
    }

    function knotBounds(knots, closed) {
        var pts = curvePoints(knots, closed, 24), xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });
        var r6 = function (v) { return Math.round(v * 1e6) / 1e6; }, x = r6(Math.min.apply(null, xs)), y = r6(Math.min.apply(null, ys));

        return { x: x, y: y, w: r6(Math.max.apply(null, xs)) - x, h: r6(Math.max.apply(null, ys)) - y };
    }

    function moveKnots(knots, fx, fy) {
        return knots.map(function (k) {
            var a = fx(k.x, k.y), i = fx(k.ix, k.iy), o = fx(k.ox, k.oy);

            return { x: a[0], y: a[1], ix: i[0], iy: i[1], ox: o[0], oy: o[1], s: !!k.s };
        });
    }

    function frac(v, size) {
        return Math.round(v / size * 1e5) / 1e5;
    }

    // A new path's box from knots in the rotated space: the box around its
    // curve, at least a pixel each way.
    function pathLayer(knots, closed) {
        var b = knotBounds(knots, closed), w = Math.max(1, b.w), h = Math.max(1, b.h), x = b.x + b.w / 2 - w / 2, y = b.y + b.h / 2 - h / 2;

        return {
            x: x, y: y, w: w, h: h, rot: 0, closed: !!closed,
            knots: moveKnots(knots, function (px, py) { return [frac(px - x, w), frac(py - y, h)]; })
        };
    }

    // A path's knots in its box's frame (pixels, 0, 0 its corner).
    function pathKnots(l) {
        return moveKnots(l.knots || [], function (u, v) { return [u * l.w, v * l.h]; });
    }

    // A path's box and knots after its knots (in its box's frame) changed:
    // the box fits the curve again, its angle and mirror kept.
    // subs: the path's other contours ({ knots, closed }, knots in its
    // box's frame), else the layer's own; the box fits them all and they
    // come back as parts of it too.
    function refitPath(l, knots, subs) {
        var more = subs || subKnots(l), b = knotBounds(knots, l.closed);

        more.forEach(function (sub) {
            var o = knotBounds(sub.knots, sub.closed), x1 = Math.max(b.x + b.w, o.x + o.w), y1 = Math.max(b.y + b.h, o.y + o.h);

            b.x = Math.min(b.x, o.x);
            b.y = Math.min(b.y, o.y);
            b.w = x1 - b.x;
            b.h = y1 - b.y;
        });

        var w = Math.max(1, b.w), h = Math.max(1, b.h), x0 = b.x + b.w / 2 - w / 2, y0 = b.y + b.h / 2 - h / 2, c = drawnPoint(l, x0 + w / 2, y0 + h / 2);
        var part = function (px, py) { return [frac(px - x0, w), frac(py - y0, h)]; };
        var out = { x: c[0] - w / 2, y: c[1] - h / 2, w: w, h: h, knots: moveKnots(knots, part) };

        if (more.length) {
            out.subs = more.map(function (sub) { return { knots: moveKnots(sub.knots, part), closed: !!sub.closed }; });
        }

        // The eraser's mask stays where it was on the document.
        if (eraseOn(l)) {
            out.erase = eraseShift(l, x0, y0, w, h);
        }

        return out;
    }

    // A path's other contours (outlined letters, their holes) in its box's
    // frame, as pathKnots() gives the first.
    function subKnots(l) {
        return (l.subs || []).map(function (sub) {
            return { knots: moveKnots(sub.knots || [], function (u, v) { return [u * l.w, v * l.h]; }), closed: !!sub.closed };
        });
    }

    function pathShape(l) {
        return {
            d: knotsD(pathKnots(l), !!l.closed) + subKnots(l).map(function (sub) { return knotsD(sub.knots, sub.closed); }).join(''),
            head: null, closed: !!l.closed
        };
    }

    // Knot i made smooth (handles along its neighbours' direction, a sixth
    // of their distance each way) or a corner (handles on the point).
    function smoothKnot(knots, i, closed) {
        var n = knots.length, k = knots[i], prev = knots[i - 1] || (closed ? knots[n - 1] : k), next = knots[i + 1] || (closed ? knots[0] : k);
        var tx = (next.x - prev.x) / 6, ty = (next.y - prev.y) / 6, out = knots.slice();

        out[i] = { x: k.x, y: k.y, ix: k.x - tx, iy: k.y - ty, ox: k.x + tx, oy: k.y + ty, s: true };

        return out;
    }

    function cornerKnot(knots, i) {
        var out = knots.slice();

        out[i] = knot(knots[i].x, knots[i].y, false);

        return out;
    }

    // A knot's point moved to x, y, its handles with it.
    function moveKnot(k, x, y) {
        var dx = x - k.x, dy = y - k.y;

        return { x: x, y: y, ix: k.ix + dx, iy: k.iy + dy, ox: k.ox + dx, oy: k.oy + dy, s: !!k.s };
    }

    // A knot's handle (which: 'i' or 'o') moved to x, y; a smooth knot turns
    // the other handle to stay in line, keeping its length.
    function moveHandle(k, which, x, y) {
        var out = { x: k.x, y: k.y, ix: k.ix, iy: k.iy, ox: k.ox, oy: k.oy, s: !!k.s }, other = (which === 'i') ? 'o' : 'i';

        out[which + 'x'] = x;
        out[which + 'y'] = y;

        if (k.s) {
            var len = Math.hypot(k[other + 'x'] - k.x, k[other + 'y'] - k.y), dx = k.x - x, dy = k.y - y, d = Math.hypot(dx, dy);

            if (d > 1e-9) {
                out[other + 'x'] = k.x + dx / d * len;
                out[other + 'y'] = k.y + dy / d * len;
            }
        }

        return out;
    }

    // The nearest point of the curve to p: its segment, t and distance.
    function nearestOnPath(knots, closed, p) {
        var best = { seg: -1, t: 0, d: Infinity };

        segments(knots, closed).forEach(function (s, i) {
            for (var k = 0; k <= 32; k++) {
                var q = cubicAt(s[0], s[1], k / 32), d = Math.hypot(q[0] - p[0], q[1] - p[1]);

                if (d < best.d) {
                    best = { seg: i, t: k / 32, d: d };
                }
            }
        });

        return best;
    }

    // A knot added at t of segment seg without changing the curve.
    function splitAt(knots, closed, seg, t) {
        var a = knots[seg], j = (seg + 1) % knots.length, b = knots[j];
        var lerp = function (p, q) { return [p[0] + (q[0] - p[0]) * t, p[1] + (q[1] - p[1]) * t]; };
        var p0 = [a.x, a.y], p1 = [a.ox, a.oy], p2 = [b.ix, b.iy], p3 = [b.x, b.y];
        var q0 = lerp(p0, p1), q1 = lerp(p1, p2), q2 = lerp(p2, p3), r0 = lerp(q0, q1), r1 = lerp(q1, q2), s = lerp(r0, r1);
        var out = knots.slice();

        out[seg] = { x: a.x, y: a.y, ix: a.ix, iy: a.iy, ox: q0[0], oy: q0[1], s: !!a.s };
        out[j] = { x: b.x, y: b.y, ix: q2[0], iy: q2[1], ox: b.ox, oy: b.oy, s: !!b.s };
        out.splice(seg + 1, 0, { x: s[0], y: s[1], ix: r0[0], iy: r0[1], ox: r1[0], oy: r1[1], s: true });

        return out;
    }

    // A drawn layer's outline as knots in its box's frame, and whether it
    // closes: "convert to path". Curves keep their shape (an ellipse, a
    // rounded box); a line keeps its ends, a pen stroke its smoothed points.
    function shapeKnots(l) {
        var w = l.w, h = l.h, r = clamp(Number(l.radius) || 0, 0, Math.min(w, h) / 2), c = KAPPA, pts, ks, i;

        if (l.type === 'ellipse') {
            var rx = w / 2, ry = h / 2;

            return { closed: true, knots: [
                { x: rx, y: 0, ix: rx - rx * c, iy: 0, ox: rx + rx * c, oy: 0, s: true },
                { x: w, y: ry, ix: w, iy: ry - ry * c, ox: w, oy: ry + ry * c, s: true },
                { x: rx, y: h, ix: rx + rx * c, iy: h, ox: rx - rx * c, oy: h, s: true },
                { x: 0, y: ry, ix: 0, iy: ry + ry * c, ox: 0, oy: ry - ry * c, s: true }
            ] };
        }

        if ((l.type === 'rect') && r) {
            var corner = function (ax, ay, bx, by, cx, cy) {
                // From (ax, ay) round the corner (bx, by) to (cx, cy).
                return [
                    { x: ax, y: ay, ix: ax, iy: ay, ox: ax + (bx - ax) * c, oy: ay + (by - ay) * c, s: false },
                    { x: cx, y: cy, ix: cx + (bx - cx) * c, iy: cy + (by - cy) * c, ox: cx, oy: cy, s: false }
                ];
            };

            return { closed: true, knots: [].concat(
                corner(w - r, 0, w, 0, w, r), corner(w, h - r, w, h, w - r, h), corner(r, h, 0, h, 0, h - r), corner(0, r, 0, 0, r, 0)) };
        }

        if (LINE_KINDS.indexOf(l.type) >= 0) {
            return { closed: false, knots: [knot(0, h / 2), knot(w, h / 2)] };
        }

        if (l.type === 'pen') {
            pts = tracePoints(l);
            ks = pts.map(function (p) { return knot(p[0], p[1]); });

            for (i = 0; i < ks.length; i++) {
                ks = smoothKnot(ks, i, false);
            }

            return { closed: false, knots: ks };
        }

        pts = outline(l)[0].slice(0, -1);

        return { closed: true, knots: pts.map(function (p) { return knot(p[0], p[1]); }) };
    }

    // -- scissors --

    // A cut this close to a knot (in t) falls on the knot.
    var CUT_EPS = 1e-4;

    // The nearest point of the curve to p as nearestOnPath(), its t refined
    // between the sampled steps around it: the place a click cuts.
    function nearestCut(knots, closed, p) {
        var best = nearestOnPath(knots, closed, p), segs = segments(knots, closed), s = segs[best.seg];

        if (!s) {
            return best;
        }

        var dist = function (t) { var q = cubicAt(s[0], s[1], t); return Math.hypot(q[0] - p[0], q[1] - p[1]); };
        var lo = Math.max(0, best.t - 1 / 32), hi = Math.min(1, best.t + 1 / 32);

        for (var i = 0; i < 48; i++) {
            var a = lo + (hi - lo) / 3, b = hi - (hi - lo) / 3;

            if (dist(a) < dist(b)) {
                hi = b;
            } else {
                lo = a;
            }
        }

        var t = (lo + hi) / 2, d = dist(t);

        return (d <= best.d) ? { seg: best.seg, t: t, d: d } : best;
    }

    // Where the line from a to b crosses the curve: [{ seg, t }] in the
    // curve's order, a crossing on a knot counted once.
    function lineCuts(knots, closed, a, b) {
        var dx = b[0] - a[0], dy = b[1] - a[1], N = 64, out = [], n = knots.length;
        var side = function (q) { return dx * (q[1] - a[1]) - dy * (q[0] - a[0]); };

        if (Math.hypot(dx, dy) < 1e-9) {
            return out;
        }

        segments(knots, closed).forEach(function (s, seg) {
            var at = function (t) { return cubicAt(s[0], s[1], t); }, p = at(0);

            for (var i = 0; i < N; i++) {
                var q = at((i + 1) / N), fp = side(p), fq = side(q);

                // A sign change (or a sample on the line) between the two
                // samples, half-open so a shared sample counts once.
                if (((fp <= 0) && (fq > 0)) || ((fp >= 0) && (fq < 0))) {
                    var lo = i / N, hi = (i + 1) / N, flo = fp, t = (fp === 0) ? lo : -1;

                    for (var j = 0; (t < 0) && (j < 40); j++) {
                        var mid = (lo + hi) / 2, fm = side(at(mid));

                        if (fm === 0) {
                            t = mid;
                        } else if ((fm < 0) === (flo < 0)) {
                            lo = mid;
                            flo = fm;
                        } else {
                            hi = mid;
                        }
                    }

                    t = (t < 0) ? (lo + hi) / 2 : t;

                    var c = at(t);
                    var u = ((c[0] - a[0]) * dx + (c[1] - a[1]) * dy) / (dx * dx + dy * dy);

                    if ((u >= -1e-9) && (u <= 1 + 1e-9)) {
                        if (t > 1 - CUT_EPS) {
                            // On the next knot: as the next segment's start.
                            out.push((seg + 1 < n) || !closed ? { seg: seg + 1, t: 0 } : { seg: 0, t: 0 });
                        } else {
                            out.push({ seg: seg, t: (t < CUT_EPS) ? 0 : t });
                        }
                    }
                }

                p = q;
            }
        });

        out.sort(function (x, y) { return (x.seg - y.seg) || (x.t - y.t); });

        return out.filter(function (c, i) {
            var o = out[i - 1];

            return !o || (o.seg !== c.seg) || (c.t - o.t > CUT_EPS);
        });
    }

    // The knots cut at cuts ([{ seg, t }]): open pieces [{ knots, closed }]
    // in the curve's order, each cut end a corner without the handle past
    // it. A closed path opens at its first cut; a cut on an end of an open
    // path does nothing. Null when nothing is cut.
    function cutKnots(knots, closed, cuts) {
        var n = knots.length, ks = knots.slice(), marks = knots.map(function () { return false; }), bySeg = {};

        if (n < 2) {
            return null;
        }

        (cuts || []).forEach(function (c) {
            var seg = Number(c.seg), t = Number(c.t);

            if (!(seg >= 0) || (seg >= (closed ? n : n - 1)) || !(t >= 0) || !(t <= 1)) {
                return;
            }

            if (t < CUT_EPS) {
                marks[seg] = true;
            } else if (t > 1 - CUT_EPS) {
                marks[(seg + 1) % n] = true;
            } else {
                (bySeg[seg] = bySeg[seg] || []).push(t);
            }
        });

        // Later segments first, the larger t first in a segment: the knots
        // before stay where they are, and the rest of a split segment runs
        // from 0 to the last split's t.
        Object.keys(bySeg).map(Number).sort(function (x, y) { return y - x; }).forEach(function (seg) {
            var ts = bySeg[seg].sort(function (x, y) { return y - x; }), span = 1;

            ts.forEach(function (t) {
                if (span - t < CUT_EPS * span) {
                    return;
                }

                ks = splitAt(ks, closed, seg, t / span);
                marks.splice(seg + 1, 0, true);
                span = t;
            });
        });

        var at = [];

        marks.forEach(function (m, i) {
            if (m && (closed || ((i > 0) && (i < ks.length - 1)))) {
                at.push(i);
            }
        });

        if (!at.length) {
            return null;
        }

        var seq = ks, bounds;

        if (closed) {
            var first = at[0];

            seq = ks.slice(first).concat(ks.slice(0, first), [ks[first]]);
            bounds = at.map(function (i) { return i - first; }).concat([seq.length - 1]);
        } else {
            bounds = [0].concat(at, [ks.length - 1]);
        }

        var pieces = [];

        for (var i = 0; i < bounds.length - 1; i++) {
            var part = seq.slice(bounds[i], bounds[i + 1] + 1).map(function (q) {
                return { x: q.x, y: q.y, ix: q.ix, iy: q.iy, ox: q.ox, oy: q.oy, s: !!q.s };
            });
            var a = part[0], b = part[part.length - 1];

            a.ix = a.x;
            a.iy = a.y;
            a.s = false;
            b.ox = b.x;
            b.oy = b.y;
            b.s = false;
            pieces.push({ knots: part, closed: false });
        }

        return pieces;
    }

    var VECTOR = {
        segments: segments, cubicAt: cubicAt, knotsD: knotsD, curvePoints: curvePoints, knotBounds: knotBounds, pathLayer: pathLayer,
        pathKnots: pathKnots, subKnots: subKnots, refitPath: refitPath, pathShape: pathShape, smoothKnot: smoothKnot, cornerKnot: cornerKnot,
        moveKnot: moveKnot, moveHandle: moveHandle, nearestOnPath: nearestOnPath, splitAt: splitAt, shapeKnots: shapeKnots,
        nearestCut: nearestCut, lineCuts: lineCuts, cutKnots: cutKnots
    };

    // -- SVG open and save (pg_image_editor_svg.js) --

    // A length of an SVG root (px, unitless or pt/in/cm/mm), or 0 for a
    // percentage or nothing.
    function svgLength(value) {
        var m = /^\s*([0-9]*\.?[0-9]+(?:e[-+]?[0-9]+)?)\s*(px|pt|pc|in|cm|mm)?\s*$/i.exec(String(value || ''));
        var unit = { px: 1, pt: 4 / 3, pc: 16, 'in': 96, cm: 96 / 2.54, mm: 96 / 25.4 };

        return m ? Number(m[1]) * (m[2] ? unit[m[2].toLowerCase()] : 1) : 0;
    }

    // The numbers of a list attribute (viewBox, points, a transform's).
    function svgNumbers(value) {
        return (String(value || '').match(/[-+]?(?:[0-9]*\.[0-9]+|[0-9]+\.?)(?:e[-+]?[0-9]+)?/gi) || []).map(Number);
    }

    // An SVG root's artboard (w, h, whole pixels within the editor's sizes)
    // and the matrix from its user space (the view box) to the artboard;
    // the view box fits centred, keeping its ratio.
    function svgRoot(width, height, viewBox) {
        var vb = svgNumbers(viewBox), w = svgLength(width), h = svgLength(height);

        vb = ((vb.length === 4) && (vb[2] > 0) && (vb[3] > 0)) ? vb : null;

        if (!w && !h) {
            w = vb ? vb[2] : 300;
            h = vb ? vb[3] : 150;
        } else if (!w) {
            w = vb ? h * vb[2] / vb[3] : h;
        } else if (!h) {
            h = vb ? w * vb[3] / vb[2] : w;
        }

        var aw = Math.round(clamp(w, SIZE_MIN, SIZE_MAX)), ah = Math.round(clamp(h, SIZE_MIN, SIZE_MAX));

        if (!vb) {
            return { w: aw, h: ah, m: scale(aw / w, ah / h) };
        }

        var k = Math.min(aw / vb[2], ah / vb[3]);

        return { w: aw, h: ah, m: mul(translate((aw - vb[2] * k) / 2, (ah - vb[3] * k) / 2), mul(scale(k), translate(-vb[0], -vb[1]))) };
    }

    // A transform attribute as a matrix.
    function svgTransform(value) {
        var m = [1, 0, 0, 1, 0, 0], re = /(matrix|translate|scale|rotate|skewX|skewY)\s*\(([^)]*)\)/g, f;

        while ((f = re.exec(String(value || ''))) !== null) {
            var n = svgNumbers(f[2]), step = null;

            if ((f[1] === 'matrix') && (n.length === 6)) {
                step = n;
            } else if (f[1] === 'translate') {
                step = translate(n[0] || 0, n[1] || 0);
            } else if (f[1] === 'scale') {
                step = scale((n[0] === undefined) ? 1 : n[0], (n[1] === undefined) ? n[0] : n[1]);
            } else if (f[1] === 'rotate') {
                step = (n.length >= 3) ? mul(translate(n[1], n[2]), mul(rotate(n[0] || 0), translate(-n[1], -n[2]))) : rotate(n[0] || 0);
            } else if (f[1] === 'skewX') {
                step = [1, 0, Math.tan((n[0] || 0) * Math.PI / 180), 1, 0, 0];
            } else if (f[1] === 'skewY') {
                step = [1, Math.tan((n[0] || 0) * Math.PI / 180), 0, 1, 0, 0];
            }

            if (step) {
                m = mul(m, step);
            }
        }

        return m;
    }

    // A box of the element's own space at least a unit wide and high, so a
    // straight line still has a box to scale.
    function svgOwnBox(b) {
        var box = { x: b.x, y: b.y, w: b.w, h: b.h };

        if (!(box.w >= 1)) {
            box.x -= (1 - (box.w || 0)) / 2;
            box.w = 1;
        }

        if (!(box.h >= 1)) {
            box.y -= (1 - (box.h || 0)) / 2;
            box.h = 1;
        }

        return box;
    }

    // A layer's box (x, y, w, h, rot, mirror) for an element whose own box
    // vb is drawn through matrix m; null when m skews, as a box cannot.
    function svgPlace(vb, m) {
        var a = m[0], b = m[1], c = m[2], d = m[3], sx = Math.hypot(a, b), det = a * d - b * c;

        if ((sx < 1e-9) || (Math.abs(det) < 1e-12) || (Math.abs(a * c + b * d) > 1e-6 * sx * Math.hypot(c, d))) {
            return null;
        }

        var rot = Math.atan2(b, a) * 180 / Math.PI, mirror = det < 0, sy = Math.abs(det) / sx;

        if (mirror) {
            rot += 180;
        }

        rot = ((rot + 540) % 360) - 180;

        var w = vb.w * sx, h = vb.h * sy, centre = apply(m, vb.x + vb.w / 2, vb.y + vb.h / 2);
        var box = { x: centre[0] - w / 2, y: centre[1] - h / 2, w: w, h: h, rot: Math.round(rot * 1e6) / 1e6 };

        if (mirror) {
            box.mirror = true;
        }

        return box;
    }

    // The matrix that draws an SVG layer's element (its own box vb) into the
    // layer's box: turned around the box's centre, mirrored inside it.
    function svgMatrix(l) {
        var vb = l.vb, m = translate(l.x, l.y);

        if (l.rot) {
            m = mul(m, mul(translate(l.w / 2, l.h / 2), mul(rotate(l.rot), translate(-l.w / 2, -l.h / 2))));
        }

        if (l.mirror) {
            m = mul(m, [-1, 0, 0, 1, l.w, 0]);
        }

        return mul(m, mul(scale(l.w / vb.w, l.h / vb.h), translate(-vb.x, -vb.y)));
    }

    // The same as a transform attribute.
    function svgMatrixText(m) {
        return 'matrix(' + m.map(function (n) { return Math.round(n * 1e6) / 1e6; }).join(' ') + ')';
    }

    // Text for an attribute value or a text node.
    function svgEscape(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // A whole SVG file: its size, the root's id (which its style rules are
    // scoped to), the artboard's fill, the kept definitions and the layers'
    // markup, in order.
    function svgFile(w, h, background, defs, bodies, id) {
        var fill = (background && (background !== 'transparent')) ? '<rect width="' + w + '" height="' + h + '" fill="' + svgEscape(background) + '"/>' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"' + (id ? ' id="' + svgEscape(id) + '"' : '') + ' width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '">'
            + (defs || '') + fill + bodies.join('') + '</svg>';
    }

    // -- SVG import (Import in the top bar, pg_image_editor_svg.js) --

    // New ids for an imported SVG's ids, none of them already in the
    // document (taken): each id gets the prefix i<n>- with the smallest n
    // that clashes with nothing, so a file imported twice keeps apart.
    function svgIdMap(ids, taken) {
        var seen = {}, list = [], map = {}, n = 1;

        (ids || []).forEach(function (id) {
            id = String(id);

            if (id && !seen[id]) {
                seen[id] = true;
                list.push(id);
            }
        });

        var used = {};

        (taken || []).forEach(function (id) { used[String(id)] = true; });

        var clash = function (k) {
            return list.some(function (id) { return used['i' + k + '-' + id]; });
        };

        while (clash(n)) {
            n++;
        }

        list.forEach(function (id) { map[id] = 'i' + n + '-' + id; });

        return map;
    }

    // An attribute value with its references renamed: a whole "#id" (href)
    // and every url(#id) (fill, clip-path, filter, a style attribute).
    function svgRenameValue(value, map) {
        var s = String(value === undefined || value === null ? '' : value), whole = /^\s*#([^\s]+)\s*$/.exec(s);

        if (whole && Object.prototype.hasOwnProperty.call(map, whole[1])) {
            return '#' + map[whole[1]];
        }

        return s.replace(/url\(\s*(['"]?)#([^'")\s]+)\1\s*\)/g, function (all, q, id) {
            return Object.prototype.hasOwnProperty.call(map, id) ? 'url(' + q + '#' + map[id] + q + ')' : all;
        });
    }

    // A style sheet with its references renamed: #id in the selectors (the
    // sanitizer's scope among them) and url(#id) in the declarations; a
    // colour such as #fff inside a rule is left alone.
    function svgRenameCss(text, map) {
        var s = String(text || ''), out = '', depth = 0, from = 0, i;
        var selectors = function (part) {
            return part.replace(/#(-?[_a-zA-Z][-_a-zA-Z0-9]*)/g, function (all, id) {
                return Object.prototype.hasOwnProperty.call(map, id) ? '#' + map[id] : all;
            });
        };

        for (i = 0; i <= s.length; i++) {
            var c = s.charAt(i);

            if ((i === s.length) || (c === '{') || (c === '}')) {
                var part = s.slice(from, i);

                out += (depth > 0) ? svgRenameValue(part, map) : selectors(part);
                out += c;
                depth += (c === '{') ? 1 : ((c === '}') ? -1 : 0);
                depth = Math.max(0, depth);
                from = i + 1;
            }
        }

        return out;
    }

    // An imported picture's box: w x h scaled to share of the visible rect
    // vr and centred in it; a picture is never made larger than its size,
    // a drawing (grow) is.
    function svgFit(w, h, vr, share, grow) {
        w = Math.max(1, Number(w) || 1);
        h = Math.max(1, Number(h) || 1);

        var k = Math.min(share * vr.w / w, share * vr.h / h);

        if (!grow) {
            k = Math.min(1, k);
        }

        return { x: vr.x + (vr.w - w * k) / 2, y: vr.y + (vr.h - h * k) / 2, w: w * k, h: h * k };
    }

    var SVG = {
        svgLength: svgLength, svgNumbers: svgNumbers, svgRoot: svgRoot, svgTransform: svgTransform, svgOwnBox: svgOwnBox,
        svgPlace: svgPlace, svgMatrix: svgMatrix, svgMatrixText: svgMatrixText, svgEscape: svgEscape, svgFile: svgFile,
        svgIdMap: svgIdMap, svgRenameValue: svgRenameValue, svgRenameCss: svgRenameCss, svgFit: svgFit
    };

    // ---- motion (pg_image_editor_motion.js) ----
    //
    // A layer's anim is { in, loop, out }, each absent or { kind, ms, delay,
    // ease, dir }; the document's motion is { duration, loop, base }. Times
    // are ms; offsets are rotated-space units; a null playhead is the rest
    // state, which is what editing and a still save always see.

    var MOTION_IN = ['fade', 'slide', 'rise', 'pop', 'zoom', 'wipe', 'spin', 'type', 'draw'];
    var MOTION_LOOP = ['pulse', 'float', 'spin', 'blink', 'wiggle', 'swing'];
    var MOTION_DIRS = ['left', 'up', 'right', 'down'];
    var MOTION_EASES = ['linear', 'out', 'in', 'in-out', 'back', 'bounce'];
    var MOTION_BASES = ['none', 'zoomin', 'zoomout', 'panleft', 'panright'];
    var MOTION_MIN = 1000, MOTION_MAX = 60000, MOTION_DEFAULT = 5000;

    // A direction as the unit step it moves by.
    var MOTION_STEP = { left: [-1, 0], right: [1, 0], up: [0, -1], down: [0, 1] };
    // The side a wipe moving that way starts its reveal from.
    var MOTION_SIDE = { left: 'right', right: 'left', up: 'bottom', down: 'top' };
    var MOTION_BACK = { left: 'right', right: 'left', up: 'down', down: 'up' };

    function easeAt(name, p) {
        p = clamp(p, 0, 1);

        switch (name) {
            case 'out':
                return 1 - Math.pow(1 - p, 3);
            case 'in':
                return p * p * p;
            case 'in-out':
                return (p < 0.5) ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
            case 'back':
                return (p >= 1) ? 1 : 1 + 2.70158 * Math.pow(p - 1, 3) + 1.70158 * Math.pow(p - 1, 2);
            case 'bounce':
                return bounceOut(p);
        }

        return p;
    }

    function bounceOut(p) {
        var n = 7.5625, d = 2.75;

        if (p < 1 / d) {
            return n * p * p;
        }

        if (p < 2 / d) {
            p -= 1.5 / d;
            return n * p * p + 0.75;
        }

        if (p < 2.5 / d) {
            p -= 2.25 / d;
            return n * p * p + 0.9375;
        }

        p -= 2.625 / d;

        return n * p * p + 0.984375;
    }

    function motionRest() {
        return { opacity: 1, x: 0, y: 0, scale: 1, rot: 0, clip: null, draw: 1, chars: null };
    }

    // A fresh phase of a kind, as the panel adds it.
    function newMotion(phase, kind) {
        return {
            kind: kind,
            ms: (phase === 'loop') ? 1500 : 800,
            delay: 0,
            ease: (kind === 'pop') ? 'back' : ((phase === 'loop') ? 'in-out' : 'out'),
            dir: 'up'
        };
    }

    function motionMs(a, fallback) {
        var ms = Number(a && a.ms);

        return (ms > 0) ? ms : fallback;
    }

    function motionDelay(a) {
        var d = Number(a && a.delay);

        return (d > 0) ? d : 0;
    }

    // When each phase runs on a timeline of end ms; null for a phase the
    // layer does not have.
    function motionSpans(l, end) {
        var a = (l && l.anim) || {}, sp = { inStart: null, inEnd: null, loopStart: null, loopEnd: null, outStart: null, outEnd: null };

        if (a['in'] && a['in'].kind) {
            sp.inStart = motionDelay(a['in']);
            sp.inEnd = sp.inStart + motionMs(a['in'], 800);
        }

        if (a.out && a.out.kind) {
            sp.outEnd = Math.max(0, end - motionDelay(a.out));
            sp.outStart = Math.max(0, sp.outEnd - motionMs(a.out, 800));
        }

        if (a.loop && a.loop.kind) {
            sp.loopStart = ((sp.inEnd === null) ? 0 : sp.inEnd) + motionDelay(a.loop);
            sp.loopEnd = (sp.outStart === null) ? end : sp.outStart;
        }

        return sp;
    }

    // An in kind at eased progress e (0 hidden or away, 1 at rest).
    function motionEnter(l, kind, e, dir, started) {
        var st = motionRest(), step = MOTION_STEP[dir] || MOTION_STEP.up;
        var w = Math.abs(Number(l.w) || 0), h = Math.abs(Number(l.h) || 0);
        var along = step[0] ? w : h, sign = (dir === 'left') ? -1 : 1;
        var n;

        switch (kind) {
            case 'fade':
                st.opacity = e;
                break;
            case 'slide':
                st.x = -step[0] * along * (1 - e);
                st.y = -step[1] * along * (1 - e);
                st.opacity = started ? 1 : 0;
                break;
            case 'rise':
                st.x = -step[0] * along * 0.25 * (1 - e);
                st.y = -step[1] * along * 0.25 * (1 - e);
                st.opacity = e;
                break;
            case 'pop':
                st.scale = 0.3 + 0.7 * e;
                st.opacity = clamp(e * 2, 0, 1);
                break;
            case 'zoom':
                st.scale = 1.5 - 0.5 * e;
                st.opacity = e;
                break;
            case 'wipe':
                st.clip = (e < 1) ? { side: MOTION_SIDE[dir] || 'bottom', f: e } : null;
                break;
            case 'spin':
                st.rot = -sign * 180 * (1 - e);
                st.scale = 0.5 + 0.5 * e;
                st.opacity = e;
                break;
            case 'draw':
                st.draw = e;
                break;
            case 'type':
                n = Array.from(String(l.text || '')).length;
                st.chars = (e >= 1) ? null : Math.floor(e * n + 1e-9);
                break;
        }

        return st;
    }

    // A loop kind at phase f of its period (0 and 1 at rest).
    function motionLoop(l, kind, f, dir) {
        var st = motionRest(), c = (1 - Math.cos(2 * Math.PI * f)) / 2, step = MOTION_STEP[dir] || MOTION_STEP.up;
        var amp = (step[0] ? Math.abs(Number(l.w) || 0) : Math.abs(Number(l.h) || 0)) * 0.08;

        switch (kind) {
            case 'pulse':
                st.scale = 1 + 0.08 * c;
                break;
            case 'float':
                st.x = step[0] * amp * c;
                st.y = step[1] * amp * c;
                break;
            case 'spin':
                st.rot = ((dir === 'left') ? -1 : 1) * 360 * f;
                break;
            case 'blink':
                st.opacity = 1 - 0.8 * c;
                break;
            case 'wiggle':
                st.rot = 6 * Math.sin(4 * Math.PI * f);
                break;
            case 'swing':
                st.rot = 12 * Math.sin(2 * Math.PI * f);
                break;
        }

        return st;
    }

    function motionCompose(a, b) {
        return {
            opacity: a.opacity * b.opacity,
            x: a.x + b.x,
            y: a.y + b.y,
            scale: a.scale * b.scale,
            rot: a.rot + b.rot,
            clip: b.clip || a.clip,
            draw: Math.min(a.draw, b.draw),
            chars: (a.chars === null) ? b.chars : ((b.chars === null) ? a.chars : Math.min(a.chars, b.chars))
        };
    }

    // The layer at playhead t (ms) on a timeline of end ms: in, then the
    // loop between the end of in and the start of out, then out; composed.
    function motionAt(l, t, end) {
        var st = motionRest(), a = l && l.anim, sp, p;

        if ((t === null) || (t === undefined) || !a) {
            return st;
        }

        sp = motionSpans(l, end);

        if ((sp.inStart !== null) && (t < sp.inEnd)) {
            p = (t - sp.inStart) / (sp.inEnd - sp.inStart);
            st = motionCompose(st, motionEnter(l, a['in'].kind, easeAt(a['in'].ease, p), a['in'].dir, t > sp.inStart));
        }

        if ((sp.loopStart !== null) && (t >= sp.loopStart) && (t < sp.loopEnd)) {
            p = ((t - sp.loopStart) % motionMs(a.loop, 1500)) / motionMs(a.loop, 1500);
            st = motionCompose(st, motionLoop(l, a.loop.kind, p, a.loop.dir));
        }

        if ((sp.outStart !== null) && (t >= sp.outStart)) {
            p = (sp.outEnd > sp.outStart) ? (t - sp.outStart) / (sp.outEnd - sp.outStart) : 1;
            st = motionCompose(st, motionEnter(l, a.out.kind, 1 - easeAt(a.out.ease, p), MOTION_BACK[a.out.dir] || 'down', p < 1));
        }

        // The layer's own keyframes on top of its presets (KEYS below).
        var k = keyAt(l, t);

        if (k) {
            st = motionCompose(st, Object.assign(motionRest(), { opacity: k.opacity, x: k.x, y: k.y, scale: k.scale, rot: k.rot }));
        }

        return st;
    }

    // The document's motion with its defaults: 5 s, repeat forever, no base move.
    function docMotion(doc) {
        var m = (doc && doc.motion) || {}, d = Math.round(Number(m.duration) || MOTION_DEFAULT), loop = Math.round(Number(m.loop) || 0);

        return {
            duration: clamp(d, MOTION_MIN, MOTION_MAX),
            loop: (loop > 0) ? loop : 0,
            base: (MOTION_BASES.indexOf(m.base) >= 0) ? m.base : 'none'
        };
    }

    // The base move of the picture under the layers at t: a scale around the
    // centre and a shift as fractions of the document's size.
    function baseAt(base, t, end) {
        var p = (end > 0) ? clamp(t / end, 0, 1) : 0;

        switch (base) {
            case 'zoomin':
                return { scale: 1 + 0.15 * p, x: 0, y: 0 };
            case 'zoomout':
                return { scale: 1.15 - 0.15 * p, x: 0, y: 0 };
            case 'panleft':
                return { scale: 1.12, x: 0.05 - 0.1 * p, y: 0 };
            case 'panright':
                return { scale: 1.12, x: -0.05 + 0.1 * p, y: 0 };
        }

        return { scale: 1, x: 0, y: 0 };
    }

    // Whether any layer moves.
    function hasMotion(doc) {
        return ((doc && doc.layers) || []).some(function (l) {
            var a = l.anim;

            return !!(a && ((a['in'] && a['in'].kind) || (a.loop && a.loop.kind) || (a.out && a.out.kind) || keyList(l).length));
        });
    }

    var MOTION = {
        MOTION_IN: MOTION_IN, MOTION_LOOP: MOTION_LOOP, MOTION_DIRS: MOTION_DIRS, MOTION_EASES: MOTION_EASES, MOTION_BASES: MOTION_BASES,
        MOTION_MIN: MOTION_MIN, MOTION_MAX: MOTION_MAX, easeAt: easeAt, newMotion: newMotion, motionSpans: motionSpans, motionAt: motionAt,
        docMotion: docMotion, baseAt: baseAt, hasMotion: hasMotion
    };

    // -- keyframes (pg_image_editor_keyframes.js) --
    //
    // A layer's anim.keys: [{ t, x, y, scale, rot, opacity, ease }] in time
    // order; x and y move the layer (document units), scale and opacity
    // multiply, rot adds degrees, as M.motionAt()'s state does. Between two
    // keys the later key's ease runs; before the first and after the last
    // the state holds. Times are ms, rounded to KEY_STEP.

    var KEY_STEP = 10, KEY_NEAR = 5, KEY_SCALE = [0.05, 10];

    function keyRest(t) {
        return { t: t, x: 0, y: 0, scale: 1, rot: 0, opacity: 1, ease: 'in-out' };
    }

    function keyNumber(v, fallback) {
        var n = Number(v);

        return isFinite(n) ? n : fallback;
    }

    // The layer's keys, cleaned: objects only, numbers, limits, defaults,
    // in time order.
    function keyList(l) {
        var list = (l && l.anim && Array.isArray(l.anim.keys)) ? l.anim.keys : [];

        return list.filter(function (k) { return k && (typeof k === 'object'); }).map(function (k) {
            return {
                t: Math.max(0, Math.round(keyNumber(k.t, 0))),
                x: keyNumber(k.x, 0),
                y: keyNumber(k.y, 0),
                scale: clamp(keyNumber(k.scale, 1), KEY_SCALE[0], KEY_SCALE[1]),
                rot: keyNumber(k.rot, 0),
                opacity: clamp(keyNumber(k.opacity, 1), 0, 1),
                ease: (MOTION_EASES.indexOf(k.ease) >= 0) ? k.ease : 'in-out'
            };
        }).sort(function (a, b) { return a.t - b.t; });
    }

    // The keyed state at t, or null for a layer without keys.
    function keyAt(l, t) {
        var keys = keyList(l), i, a, b, e;

        if (!keys.length || (t === null) || (t === undefined)) {
            return null;
        }

        if (t <= keys[0].t) {
            return keys[0];
        }

        for (i = 1; i < keys.length; i++) {
            if (t < keys[i].t) {
                a = keys[i - 1];
                b = keys[i];
                e = easeAt(b.ease, (t - a.t) / (b.t - a.t));

                return {
                    t: t,
                    x: a.x + (b.x - a.x) * e,
                    y: a.y + (b.y - a.y) * e,
                    scale: Math.max(KEY_SCALE[0], a.scale + (b.scale - a.scale) * e),
                    rot: a.rot + (b.rot - a.rot) * e,
                    opacity: clamp(a.opacity + (b.opacity - a.opacity) * e, 0, 1),
                    ease: b.ease
                };
            }
        }

        return keys[keys.length - 1];
    }

    // The index of the key within KEY_NEAR ms of t, or -1.
    function keyIndex(keys, t) {
        for (var i = 0; i < keys.length; i++) {
            if (Math.abs(keys[i].t - t) <= KEY_NEAR) {
                return i;
            }
        }

        return -1;
    }

    function keyTime(t, end) {
        return clamp(Math.round(keyNumber(t, 0) / KEY_STEP) * KEY_STEP, 0, Math.max(0, end));
    }

    function keyClean(k) {
        return { t: k.t, x: k.x, y: k.y, scale: k.scale, rot: k.rot, opacity: k.opacity, ease: k.ease };
    }

    // The keys with patch at t (on a timeline of end ms): the key there
    // changed, or a new one from the state the layer has there. The first
    // key away from 0 brings a key at rest at 0, so the layer moves from
    // where it was put.
    function keySet(l, t, patch, end) {
        var keys = keyList(l), i, k;

        t = keyTime(t, end);
        i = keyIndex(keys, t);

        if (!keys.length && (t > 0)) {
            keys.push(keyRest(0));
        }

        if (i >= 0) {
            keys[i] = keyClean(Object.assign({}, keys[i], patch, { t: keys[i].t }));
        } else {
            k = keyAt({ anim: { keys: keys } }, t);
            keys.push(keyClean(Object.assign(k ? Object.assign({}, k) : keyRest(t), { ease: 'in-out' }, patch, { t: t })));
        }

        return keyList({ anim: { keys: keys } });
    }

    // The key at from moved to to; one already there gives way.
    function keyMove(keys, from, to, end) {
        var i = keyIndex(keys, from), moved;

        if (i < 0) {
            return keys.slice();
        }

        to = keyTime(to, end);
        moved = Object.assign({}, keys[i], { t: to });

        return keyList({ anim: { keys: keys.filter(function (k, n) { return (n !== i) && (Math.abs(k.t - to) > KEY_NEAR); }).concat([moved]) } });
    }

    function keyDrop(keys, t) {
        var i = keyIndex(keys, t);

        return keys.filter(function (k, n) { return n !== i; });
    }

    // The path the layer's centre takes: a dot [x, y, t] at each key and a
    // line sampled at n steps from the first key to the last; null without
    // keys.
    function keyPath(l, n) {
        var keys = keyList(l), cx = (Number(l.x) || 0) + (Number(l.w) || 0) / 2, cy = (Number(l.y) || 0) + (Number(l.h) || 0) / 2;
        var line = [], from, span, i, k;

        if (!keys.length) {
            return null;
        }

        n = Math.max(1, Math.round(n || 60));
        from = keys[0].t;
        span = keys[keys.length - 1].t - from;

        for (i = 0; i <= n; i++) {
            k = keyAt(l, from + span * i / n);
            line.push([cx + k.x, cy + k.y]);
        }

        return { line: line, dots: keys.map(function (q) { return [cx + q.x, cy + q.y, q.t]; }) };
    }

    var KEYS = { KEY_STEP: KEY_STEP, keyList: keyList, keyAt: keyAt, keyIndex: keyIndex, keySet: keySet, keyMove: keyMove, keyDrop: keyDrop, keyPath: keyPath };

    // -- tracks (pg_image_editor_tracks.js) --
    //
    // A row per layer under the play bar: a bar per phase from M.motionSpans(),
    // dragged whole (its delay) or by an end (its length). Times are ms.

    var TRACK_STEP = 100, TRACK_FINE = 10, TRACK_MIN = 100;

    // A layer's bars in time order: { phase, from, to }; the loop has no end grip.
    function trackBars(l, end) {
        var sp = motionSpans(l, end), bars = [];

        if (sp.inStart !== null) {
            bars.push({ phase: 'in', from: sp.inStart, to: sp.inEnd });
        }

        if ((sp.loopStart !== null) && (sp.loopEnd > sp.loopStart)) {
            bars.push({ phase: 'loop', from: sp.loopStart, to: sp.loopEnd });
        }

        if (sp.outStart !== null) {
            bars.push({ phase: 'out', from: sp.outStart, to: sp.outEnd });
        }

        return bars;
    }

    // The times a dragged end snaps to: the start, the end and every bar's
    // ends of the other layers, sorted, once each.
    function trackTargets(layers, end, skipId) {
        var seen = [0, end];

        (layers || []).forEach(function (l) {
            if (l.id === skipId) {
                return;
            }

            trackBars(l, end).forEach(function (b) {
                seen.push(b.from, b.to);
            });
        });

        return seen.filter(function (n, i) { return seen.indexOf(n) === i; }).sort(function (a, b) { return a - b; });
    }

    // The nearest target within slack ms, or null.
    function trackNear(ms, targets, slack) {
        var best = null;

        (targets || []).forEach(function (n) {
            if ((Math.abs(n - ms) <= slack) && ((best === null) || (Math.abs(n - ms) < Math.abs(best - ms)))) {
                best = n;
            }
        });

        return best;
    }

    // A time on the grid: a tenth of a second, a hundredth with fine (Shift).
    function trackRound(ms, fine) {
        var step = fine ? TRACK_FINE : TRACK_STEP;

        return Math.round(ms / step) * step;
    }

    // One end moved to ms: another bar's end within slack, else the grid.
    function trackSnap(ms, targets, slack, fine) {
        var near = fine ? null : trackNear(ms, targets, slack);

        return (near === null) ? trackRound(ms, fine) : near;
    }

    // The phase patch ({ delay, ms } or { delay }) for a drag of dt ms on
    // layer l as it was at the press: grip 'move' shifts the bar, 'start'
    // and 'end' move one end. Clamped to the timeline and a 0.1 s bar; null
    // for a phase the layer lacks. opts: { targets, slack, fine }.
    function trackEdit(l, phase, grip, dt, end, opts) {
        var o = opts || {}, sp = motionSpans(l, end), a = (l && l.anim && l.anim[phase]) || null;
        var from = sp[phase + 'Start'], to = sp[phase + 'End'], len, nf, nt, s1, s2, base;

        if (!a || (from === null)) {
            return null;
        }

        // A loop runs to the exit: only its start moves.
        if (phase === 'loop') {
            base = (sp.inEnd === null) ? 0 : sp.inEnd;
            nf = clamp(trackSnap(from + dt, o.targets, o.slack || 0, o.fine), base, Math.max(base, to - TRACK_MIN));

            return { delay: Math.max(0, nf - base) };
        }

        len = to - from;

        if (grip === 'move') {
            nf = from + dt;
            nt = to + dt;
            s1 = o.fine ? null : trackNear(nf, o.targets, o.slack || 0);
            s2 = o.fine ? null : trackNear(nt, o.targets, o.slack || 0);

            if ((s1 !== null) && ((s2 === null) || (Math.abs(s1 - nf) <= Math.abs(s2 - nt)))) {
                nf = s1;
            } else if (s2 !== null) {
                nf = s2 - len;
            } else {
                nf = trackRound(nf, o.fine);
            }

            nf = clamp(nf, 0, Math.max(0, end - len));
            nt = nf + len;
        } else if (grip === 'start') {
            nt = to;
            nf = clamp(trackSnap(from + dt, o.targets, o.slack || 0, o.fine), 0, Math.max(0, to - TRACK_MIN));
        } else {
            nf = from;
            nt = clamp(trackSnap(to + dt, o.targets, o.slack || 0, o.fine), Math.min(end, from + TRACK_MIN), end);
        }

        if (phase === 'in') {
            return { delay: Math.round(nf), ms: Math.round(nt - nf) };
        }

        return { delay: Math.round(end - nt), ms: Math.round(nt - nf) };
    }

    var TRACKS = { TRACK_STEP: TRACK_STEP, trackBars: trackBars, trackTargets: trackTargets, trackSnap: trackSnap, trackEdit: trackEdit };

    // -- frames and animation files (pg_image_editor_render.js) --

    // The formats the Save animation dialog offers, with their limits:
    // longest edges (never past the picture's), the default edge, the
    // longest animation in ms and the frame rates; needs names what the
    // browser must have (animFormats()), dither whether it is offered.
    var ANIM_FORMATS = {
        gif: { mime: 'image/gif', ext: 'gif', edges: [240, 320, 480, 640, 800], edge: 480, maxMs: 15000, rates: [10, 15, 20, 25], rate: 15, dither: true },
        apng: { mime: 'image/png', ext: 'png', edges: [240, 320, 480, 640, 800, 1080], edge: 480, maxMs: 15000, rates: [10, 15, 20, 25], rate: 15, needs: 'deflate' },
        webp: { mime: 'image/webp', ext: 'webp', edges: [240, 320, 480, 640, 800, 1080], edge: 480, maxMs: 15000, rates: [10, 15, 20, 25], rate: 15, needs: 'webp' },
        // Video: even sides; codecs are tried in order by WebCodecs (the
        // baseline H.264 profile first: no reordered frames for the muxer),
        // recorder the MediaRecorder types where WebCodecs has none.
        mp4: {
            mime: 'video/mp4', ext: 'mp4', video: true, even: true, edges: [480, 640, 720, 960, 1280, 1920], edge: 1280, maxMs: 60000, rates: [24, 30], rate: 30, needs: 'mp4',
            codecs: ['avc1.42E028', 'avc1.4D0028', 'avc1.640028'], recorder: ['video/mp4;codecs=avc1.42E028', 'video/mp4']
        },
        webm: {
            mime: 'video/webm', ext: 'webm', video: true, even: true, edges: [480, 640, 720, 960, 1280, 1920], edge: 1280, maxMs: 60000, rates: [24, 30], rate: 30, needs: 'webm',
            codecs: ['vp09.00.40.08'], recorder: ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm']
        },
        // A page per scene, each still (pg_image_editor_present.js): offered
        // with two scenes or more; no rate or length limit of its own.
        pdf: { mime: 'application/pdf', ext: 'pdf', pdf: true, edges: [640, 960, 1280, 1920, 2560], edge: 1920, maxMs: Infinity, rates: [1], rate: 1, needs: 'scenes' }
    };

    // Every edge some format offers.
    var ANIM_EDGES = Object.keys(ANIM_FORMATS).reduce(function (all, k) {
        return all.concat(ANIM_FORMATS[k].edges.filter(function (e) { return all.indexOf(e) < 0; }));
    }, []);

    // The formats a browser with caps ({ deflate, webp, mp4, webm }) can make.
    function animFormats(caps) {
        return Object.keys(ANIM_FORMATS).filter(function (k) { return !ANIM_FORMATS[k].needs || !!(caps && caps[ANIM_FORMATS[k].needs]); });
    }

    // The settings on switching to format: the edge and the rate kept
    // inside its limits; an edge another format lists becomes the largest
    // of this one's under it (the picture's own edge is kept).
    function animSwitch(set, format) {
        var f = ANIM_FORMATS[format] || ANIM_FORMATS.gif, edge = Math.min(Number(set.edge) || f.edge, f.edges[f.edges.length - 1]);

        if ((f.edges.indexOf(edge) < 0) && (ANIM_EDGES.indexOf(edge) >= 0)) {
            var under = f.edges.filter(function (e) { return e <= edge; });

            edge = under.length ? under[under.length - 1] : f.edges[0];
        }

        return Object.assign({}, set, {
            format: ANIM_FORMATS[format] ? format : 'gif',
            edge: edge,
            fps: (f.rates.indexOf(set.fps) >= 0) ? set.fps : f.rate
        });
    }

    // Repeat choices: forever (0), once, three times, and the document's own.
    function animLoops(loop) {
        var list = [0, 1, 3];

        loop = Math.max(0, Math.round(Number(loop) || 0));

        if (list.indexOf(loop) < 0) {
            list.push(loop);
        }

        return list.sort(function (a, b) { return (a === 0) ? -1 : ((b === 0) ? 1 : a - b); });
    }

    // The settings the dialog starts from; small (a phone with little
    // memory) starts at the smallest edge.
    function animDefaults(doc, format, small) {
        var f = ANIM_FORMATS[format] || ANIM_FORMATS.gif;

        return { format: ANIM_FORMATS[format] ? format : 'gif', edge: small ? f.edges[0] : f.edge, fps: f.rate, loop: docMotion(doc).loop, dither: true };
    }

    // The frame size for out (the save's size) at a longest edge, never
    // larger than out; whole pixels, at least 1; even sides (a video's
    // chroma is halved) rounded down, at least 2.
    function animSize(out, edge, even) {
        var long = Math.max(out.w, out.h), k = Math.min(1, Math.max(1, Number(edge) || long) / long);
        var w = Math.max(1, Math.round(out.w * k)), h = Math.max(1, Math.round(out.h * k));

        return even ? { w: Math.max(2, w - (w & 1)), h: Math.max(2, h - (h & 1)) } : { w: w, h: h };
    }

    // A video's bit rate for frames of size at fps: a tenth of a bit per
    // pixel, at least 250 kbit/s.
    function animBitrate(size, fps) {
        return Math.max(250000, Math.round(size.w * size.h * fps / 10));
    }

    // The edges the dialog offers for out: those under its longest edge,
    // and that edge itself when it is shorter than the largest.
    function animEdges(out, format) {
        var f = ANIM_FORMATS[format] || ANIM_FORMATS.gif, long = Math.max(out.w, out.h);
        var list = f.edges.filter(function (e) { return e < long; });

        if (long <= f.edges[f.edges.length - 1]) {
            list.push(long);
        }

        return list.length ? list : [long];
    }

    // How many frames one play of ms holds at fps.
    function animFrames(ms, fps) {
        return Math.max(1, Math.round(Math.max(0, Number(ms) || 0) * fps / 1000));
    }

    // n frame numbers spread evenly over count, each once: the samples the
    // palette and the estimate come from.
    function animSamples(count, n) {
        var out = [];

        for (var k = 0; k < Math.min(n, count); k++) {
            var i = Math.floor((k + 0.5) * count / Math.min(n, count));

            if (out.indexOf(i) < 0) {
                out.push(i);
            }
        }

        return out;
    }

    // The file's size from the encoder's header and average frame.
    function animEstimate(header, frame, count) {
        return Math.round((Number(header) || 0) + (Number(frame) || 0) * Math.max(0, count) + 1);
    }

    // The file name: the picture's name with the format's extension.
    function animName(name, ext) {
        var base = String(name || '').replace(/^.*[\\/]/, '').replace(/\.[^.]*$/, '');

        return (base || 'animation') + '.' + ext;
    }

    var RENDER = {
        ANIM_FORMATS: ANIM_FORMATS, animFormats: animFormats, animSwitch: animSwitch, animLoops: animLoops, animDefaults: animDefaults, animSize: animSize, animEdges: animEdges,
        animBitrate: animBitrate, animFrames: animFrames, animSamples: animSamples, animEstimate: animEstimate, animName: animName
    };

    // -- saving an animation into Files (pg_image_editor_render.js) --

    // Whether a file of bytes fits the server's limit (0: none known).
    function animFits(bytes, limit) {
        return !(Number(limit) > 0) || (Number(bytes) || 0) <= Number(limit);
    }

    // A smaller longest edge or frame rate whose file fits limit, from the
    // estimate at set: the size scales with the frame's area and the frame
    // count. The largest such file (the least lost), with a tenth spare;
    // null when the settings fit or nothing offered does.
    function animSmaller(out, set, bytes, limit) {
        var f = ANIM_FORMATS[set.format] || ANIM_FORMATS.gif, now = animSize(out, set.edge), best = null;

        if (animFits(bytes, limit)) {
            return null;
        }

        animEdges(out, set.format).forEach(function (edge) {
            var size = animSize(out, edge), area = (size.w * size.h) / (now.w * now.h);

            f.rates.forEach(function (fps) {
                var est = Math.round(bytes * area * fps / set.fps);

                if ((size.w <= now.w) && (fps <= set.fps) && ((size.w < now.w) || (fps < set.fps)) && (est * 1.1 <= limit)
                    && (!best || (est > best.bytes) || ((est === best.bytes) && (edge > best.edge)))) {
                    best = { edge: edge, fps: fps, bytes: est };
                }
            });
        });

        return best;
    }

    // What a save into Files does: replace the animation the editor opened
    // when it is of the same format and may be written over, else a new
    // file.
    function animSaveMode(made, format) {
        return (made && made.replace && (made.format === (ANIM_FORMATS[format] || ANIM_FORMATS.gif).ext)) ? 'replace' : 'new';
    }

    var ANIMSAVE = { animFits: animFits, animSmaller: animSmaller, animSaveMode: animSaveMode };

    // -- motion in a saved SVG (pg_image_editor_svg.js) --

    // Samples over one play: enough for an ease to read as a curve.
    var SVG_MOTION_STEPS = 50;

    // The motion of an SVG file's layers, as numbers the server writes its
    // own @keyframes from (pg_image_editor_svg_motion_css()): for each of
    // layers (in the file's order) that moves, its index n (its group's
    // class pgm-n), the centre it turns about and frames [percent, opacity,
    // x, y, scale, rot]. A wipe, a drawn stroke or typed letters show as the
    // share revealed, faded: a style sheet cannot cut them. null when
    // nothing moves.
    function svgMotion(doc, layers) {
        var m = docMotion(doc), out = [];

        (layers || []).forEach(function (l, n) {
            if (!hasMotion({ layers: [l] }) || (l.type === 'redact')) {
                return;
            }

            var frames = [], len = Array.from(String(l.text || '')).length;

            for (var i = 0; i <= SVG_MOTION_STEPS; i++) {
                var t = Math.min(m.duration - 1, m.duration * i / SVG_MOTION_STEPS), st = motionAt(l, t, m.duration);
                var shown = (st.clip ? clamp(Number(st.clip.f) || 0, 0, 1) : 1) * clamp(st.draw, 0, 1) * ((st.chars !== null) && len ? clamp(st.chars / len, 0, 1) : 1);

                frames.push([Math.round(10000 * i / SVG_MOTION_STEPS) / 100].concat([clamp(st.opacity, 0, 1) * shown, st.x, st.y, st.scale, st.rot].map(function (v) {
                    return Math.round(v * 1000) / 1000;
                })));
            }

            // A frame equal to both neighbours adds nothing.
            frames = frames.filter(function (f, i) {
                var a = frames[i - 1], b = frames[i + 1];

                return !a || !b || (f.slice(1).join() !== a.slice(1).join()) || (f.slice(1).join() !== b.slice(1).join());
            });

            out.push({ n: n, cx: Math.round(((Number(l.x) || 0) + (Number(l.w) || 0) / 2) * 1000) / 1000, cy: Math.round(((Number(l.y) || 0) + (Number(l.h) || 0) / 2) * 1000) / 1000, frames: frames });
        });

        return out.length ? { duration: m.duration, loop: m.loop, layers: out } : null;
    }

    // Whether a top-level element of a saved SVG is the group a save put
    // around one moving layer: a g whose only class is pgm-n, with no other
    // attribute than that and a transform, holding one element. It opens as
    // that element (svgMotionOpen()), so a text stays a text.
    function svgMotionGroup(name, cls, attrs, children) {
        return (name === 'g') && /^\s*pgm-\d+\s*$/.test(String(cls || '')) && (children === 1)
            && (attrs || []).every(function (a) { return (a === 'class') || (a === 'transform'); });
    }

    // A class list without the pgm-n names a save gives its groups, so a
    // stale one never matches a later save's rules.
    function svgMotionClass(cls) {
        return String(cls || '').split(/\s+/).filter(function (c) { return c && !/^pgm-\d+$/.test(c); }).join(' ');
    }

    var SVGMOTION = { svgMotion: svgMotion, svgMotionGroup: svgMotionGroup, svgMotionClass: svgMotionClass };

    // -- access (pg_image_editor_access.js) --

    // A letter picks a tool of the call: the first of its tools the call
    // has, so A is Adjust on the rail and Arrow on the mini bar.
    var KEY_TOOLS = {
        v: ['select'], c: ['crop'], a: ['adjust', 'arrow'], d: ['draw'], t: ['text'], r: ['redact'], u: ['rect'], s: ['sticker'],
        f: ['frame'], p: ['vector', 'pen'], b: ['artboard'], i: ['image'], e: ['export'], l: ['line'], o: ['ellipse'],
        m: ['motion'], q: ['area'], x: ['scissors'], g: ['builder'], w: ['warp'], n: ['puppet']
    };

    function toolForKey(key, list) {
        var k = String(key || '').toLowerCase(), i;

        if (!list || !KEY_TOOLS.hasOwnProperty(k)) {
            return null;
        }

        for (i = 0; i < KEY_TOOLS[k].length; i++) {
            if (list.indexOf(KEY_TOOLS[k][i]) >= 0) {
                return KEY_TOOLS[k][i];
            }
        }

        return null;
    }

    // The letter that picks tool id in a call with these tools, upper case,
    // or '' when another tool of the call takes it first.
    function toolKey(id, list) {
        var keys = Object.keys(KEY_TOOLS), i;

        for (i = 0; i < keys.length; i++) {
            if ((KEY_TOOLS[keys[i]].indexOf(id) >= 0) && (toolForKey(keys[i], list || [id]) === id)) {
                return keys[i].toUpperCase();
            }
        }

        return '';
    }

    // Tab in a box that keeps the focus: the next control (back: the one
    // before), round from the last to the first; -1 with none. From outside
    // the list Tab starts at the first, Shift+Tab at the last.
    function trapFocus(count, index, back) {
        if (!(count > 0)) {
            return -1;
        }

        if ((index < 0) || (index >= count)) {
            return back ? count - 1 : 0;
        }

        return (index + (back ? count - 1 : 1)) % count;
    }

    // A layer's angle under a two-finger twist: its angle at the start plus
    // the turn of the line between the fingers (radians, from and to),
    // within -180 to 180, held on a right angle or a half one within 4
    // degrees, to a tenth of a degree.
    function twistAngle(rot, from, to) {
        var a = (Number(rot) || 0) + ((to - from) * 180 / Math.PI), near;

        a = ((a % 360) + 360) % 360;
        a = (a > 180) ? a - 360 : a;
        near = Math.round(a / 45) * 45;

        if (Math.abs(a - near) <= 4) {
            a = near;
        }

        a = Math.round(a * 10) / 10;

        return (a <= -180) ? a + 360 : (a === 0 ? 0 : a);
    }

    // How near, in screen pixels, a press must come to a point or an edge
    // a tool picks up (pointer: the PointerEvent's pointerType): a finger
    // gets half of a 44 px target, a mouse or a pen the tool's own reach.
    var TOUCH_REACH = 22;

    function touchReach(pointer, px) {
        return (pointer === 'touch') ? Math.max(TOUCH_REACH, Number(px) || 0) : (Number(px) || 0);
    }

    // The keys and touches of the tools that have their own, one group per
    // tool of the call, titled with its name.
    var KEY_MODES = {
        area: [
            { keys: ['Shift', 'Drag'], label: 'keyAreaAdd' },
            { keys: ['Alt', 'Drag'], label: 'keyAreaSubtract' },
            { keys: ['DoubleClick'], label: 'keyAreaClose' },
            { keys: [], label: 'touchAreaClose' }
        ],
        builder: [
            { keys: ['Alt', 'Drag'], label: 'keyBuilderRemove' }
        ],
        puppet: [
            { keys: ['Click'], label: 'keyPinAdd' },
            { keys: ['Alt', 'Click'], label: 'keyPinRemove' },
            { keys: ['Delete'], label: 'keyPinDelete' }
        ]
    };

    // The help sheet's groups for a call's tools: { title, rows: [{ keys,
    // label }] }, keys as tokens (Mod is Ctrl, or Cmd on a Mac; Space,
    // Delete, Arrows, Drag, Click and DoubleClick are named by the sheet),
    // title and label as label keys. Layer keys show only with a tool that
    // makes layers, Enter only with Crop, a tool's own group (KEY_MODES)
    // only with that tool.
    var KEY_NOLAYERS = ['crop', 'adjust', 'export'];

    function keyMap(list) {
        list = list || [];

        var tools = [], layers = list.some(function (id) { return KEY_NOLAYERS.indexOf(id) < 0; });
        var groups = [];

        list.forEach(function (id) {
            var k = toolKey(id, list);

            if (k) {
                tools.push({ keys: [k], label: 'tool_' + id });
            }
        });

        if (tools.length) {
            groups.push({ title: 'keysTools', rows: tools });
        }

        groups.push({ title: 'keysEdit', rows: [
            { keys: ['Mod', 'Z'], label: 'undo' },
            { keys: ['Mod', 'Shift', 'Z'], label: 'redo' },
            { keys: ['Mod', 'S'], label: 'save' }
        ].concat((list.indexOf('crop') >= 0) ? [{ keys: ['Enter'], label: 'keyApply' }] : [], [
            { keys: ['Esc'], label: 'keyEscape' },
            { keys: ['?'], label: 'keyHelp' }
        ]) });

        if (layers) {
            groups.push({ title: 'keysLayers', rows: [
                { keys: ['Arrows'], label: 'keyNudge' },
                { keys: ['Shift', 'Arrows'], label: 'keyNudgeMore' },
                { keys: ['Delete'], label: 'keyDelete' },
                { keys: ['Mod', 'D'], label: 'keyDuplicate' },
                { keys: ['Mod', 'C'], label: 'keyCopy' },
                { keys: ['Mod', 'V'], label: 'keyPaste' },
                { keys: ['Mod', 'A'], label: 'keySelectAll' }
            ] });
        }

        list.forEach(function (id) {
            if (KEY_MODES.hasOwnProperty(id)) {
                groups.push({ title: 'tool_' + id, rows: KEY_MODES[id].slice() });
            }
        });

        groups.push({ title: 'keysView', rows: [
            { keys: ['+'], label: 'zoomIn' },
            { keys: ['-'], label: 'zoomOut' },
            { keys: ['0'], label: 'zoomFit' },
            { keys: ['1'], label: 'zoomActual' },
            { keys: ['Space', 'Drag'], label: 'keyPan' }
        ] });

        groups.push({ title: 'keysTouch', rows: [
            { keys: [], label: 'touchPan' },
            { keys: [], label: 'touchFit' }
        ].concat(layers ? [{ keys: [], label: 'touchTwist' }] : []) });

        return groups;
    }

    var ACCESS = { KEY_TOOLS: KEY_TOOLS, toolForKey: toolForKey, toolKey: toolKey, trapFocus: trapFocus, twistAngle: twistAngle, keyMap: keyMap,
        TOUCH_REACH: TOUCH_REACH, touchReach: touchReach, KEY_MODES: KEY_MODES };

    // -- The selection's commands (pg_image_editor_actions.js) --

    // The commands the bar over a small screen's stage offers for what is
    // selected, in order, as [{ name, off }]. s.layers and s.free: the
    // selected layers (with a tool that selects layers) and how many of them
    // are unlocked; s.picture: the picture selected; s.area: an area
    // selected with Select area; s.sheet: a closed panel sheet that Options
    // opens; s.can: which of the area's commands the editor has. Options
    // comes first; the rest keep the panel's order. Short enough for a
    // phone's width: what does not fit (arrange, align) is in the panel.
    function actionList(s) {
        var can = s.can || {}, out = [];
        var add = function (name, off) { out.push({ name: name, off: !!off }); };

        if (!s.layers && !s.picture && !s.area) {
            return out;
        }

        if (s.sheet) {
            add('options');
        }

        if (s.layers) {
            add('duplicate');
            add('remove', !s.free);
        } else if (s.picture) {
            add('lockPicture');
            add('removePicture');
        } else {
            ['erase', 'keep', 'copy', 'cutToLayer', 'adjust'].forEach(function (name) {
                if (can[name]) {
                    add(name);
                }
            });
            add('redact', !can.redact);
            add('invert');
            add('deselect');
        }

        return out;
    }

    // Whether a corner keeps a layer's proportions while it resizes it
    // (pointer: the PointerEvent's pointerType): with a finger or a pen
    // always, a touch screen having no Shift; with the mouse when the type
    // keeps them (ratio: a picture), Shift doing the other.
    function keepRatio(pointer, shift, ratio) {
        return (pointer === 'touch') || (pointer === 'pen') || (!!shift !== !!ratio);
    }

    var ACTIONS = { actionList: actionList, keepRatio: keepRatio };

    // -- The context menu (pg_image_editor_menu.js) --

    // The layers' order and alignment and the shapes' combinations, as the
    // panel offers them.
    var MENU_ORDER = ['front', 'forward', 'backward', 'back'];
    var MENU_ALIGN = ['alignLeft', 'alignCenter', 'alignRight', 'alignTop', 'alignMiddle', 'alignBottom', 'distX', 'distY'];
    var MENU_COMBINE = ['unite', 'minusFront', 'intersect', 'exclude', 'divide'];

    // The items of the menu a right click, a long press or the menu key
    // opens, in order: [{ name, off, sub, mode, on }], a separator as
    // { sep: true }. s.at: 'stage', 'rail' or 'scene'.
    // On the stage the selection's commands start from actionList() (s.layers,
    // s.free, s.picture, s.area, s.can as there) and the menu adds the rest:
    // s.clip (layers to paste), s.locked and s.hidden (every selected layer
    // is), s.areas (selected unlocked shapes with an area), s.texts, s.warp
    // (one warped layer), s.svg (one imported SVG), s.count (the layers),
    // s.imports (the Import button is there) and s.site (Import from the
    // site), s.canvas (the Canvas tool is offered).
    // On a rail item: s.modes, its tools, and s.tool; one mode has no menu.
    // On a scene: s.index, s.count and s.max of the scenes.
    function menuList(s) {
        var out = [], acts;
        var item = function (name, off, extra) {
            var it = { name: name, off: !!off };

            Object.keys(extra || {}).forEach(function (k) { it[k] = extra[k]; });
            out.push(it);
        };
        var sep = function () {
            if (out.length && !out[out.length - 1].sep) {
                out.push({ sep: true });
            }
        };
        var sub = function (names, off) {
            return names.map(function (n) { return { name: n, off: !!(off && off(n)) }; });
        };

        if (s.at === 'rail') {
            return ((s.modes || []).length > 1) ? s.modes.map(function (m) { return { name: 'mode', mode: m, on: m === s.tool, off: false }; }) : out;
        }

        if (s.at === 'scene') {
            item('sceneDuplicate', s.count >= s.max);
            item('sceneDelete', s.count < 2);
            item('sceneEarlier', s.index <= 0);
            item('sceneLater', s.index >= s.count - 1);

            return out;
        }

        acts = actionList({ layers: s.layers, free: s.free, picture: s.picture, area: s.area, sheet: false, can: s.can });

        if (s.layers) {
            item('cut', !s.free);
            item('copyLayers');
            item('paste', !s.clip);
            acts.forEach(function (a) { item(a.name, a.off); });
            sep();
            item('order', false, { sub: sub(MENU_ORDER) });
            item('align', !s.free, { sub: sub(MENU_ALIGN, function (n) { return (n === 'distX' || n === 'distY') && (s.free < 3); }) });
            item(s.locked ? 'unlock' : 'lock');
            item(s.hidden ? 'show' : 'hide');
            sep();

            if (s.areas >= 2) {
                item('combine', false, { sub: sub(MENU_COMBINE) });
            }

            if (s.texts) {
                item('outlines');
            }

            if (s.warp) {
                item('expandWarp');
            }

            if (s.svg) {
                item('breakApart');
            }
        } else if (s.picture || s.area) {
            acts.forEach(function (a) { item(a.name, a.off); });

            if (s.picture) {
                sep();
                item('paste', !s.clip);
            }
        } else {
            item('paste', !s.clip);
            item('selectAll', !s.count);
            sep();

            if (s.imports && s.site) {
                item('import', false, { sub: sub(['importComputer', 'importSite']) });
            } else if (s.imports) {
                item('importFile');
            }

            if (s.canvas) {
                item('canvasSize');
            }

            item('fit');
        }

        if (out.length && out[out.length - 1].sep) {
            out.pop();
        }

        return out;
    }

    var MENU = { menuList: menuList };

    // -- Google Fonts choice of the Text tool (pg_image_editor_fonts.js) --

    // The catalogue's families (window.PgGoogleFonts) whose name holds the
    // query, any letter case, in one category or all, in catalogue order:
    // [{ family, category }].
    function fontSearch(catalogue, query, category) {
        var q = String(query || '').trim().toLowerCase(), out = [];

        ((catalogue && catalogue.order) || []).forEach(function (key) {
            if (category && (key !== category)) {
                return;
            }

            (catalogue.categories[key] || []).forEach(function (family) {
                if (!q || (family.toLowerCase().indexOf(q) >= 0)) {
                    out.push({ family: family, category: key });
                }
            });
        });

        return out;
    }

    // The last five families used, newest first, each once; a name that is
    // not a plain family leaves the list as it was.
    function fontRecent(list, family) {
        var name = textFamilyName(family), keep = Array.isArray(list) ? list.filter(function (f) {
            return (typeof f === 'string') && (textFamilyName(f) === f) && (f !== '');
        }) : [];

        if (!name || (name !== family)) {
            return keep.slice(0, 5);
        }

        return [name].concat(keep.filter(function (f) { return f !== name; })).slice(0, 5);
    }

    var GFONTS = { fontSearch: fontSearch, fontRecent: fontRecent };

    // -- Icons and emoji of the Sticker tool (pg_image_editor_glyph.js) --

    // A glyph layer: { type: 'glyph', set: 'bi'|'emoji', code, color, x, y,
    // w, h, rot, opacity }. An icon's code is its Bootstrap Icons code point,
    // an emoji's code the emoji itself.
    var EMOJI = {
        order: ['smileys', 'people', 'nature', 'food', 'travel', 'activities', 'objects', 'symbols'],
        categories: {
            smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '😉', '😍', '🥰', '😘', '😋', '😜', '🤪', '😎', '🤩', '🥳', '😏', '😢', '😭', '😡', '😱', '🤔', '🤗', '😴', '🙄', '😬', '🤯'],
            people: ['👋', '👍', '👎', '👏', '🙌', '🙏', '🤝', '💪', '✌️', '🤞', '👌', '🤙', '👉', '👈', '👆', '👇', '✋', '👀', '👶', '🧒', '👩', '👨', '🧓', '👵', '👴', '👮', '👷', '🧑‍🍳', '🧑‍🎓', '🧑‍💻', '🧑‍🎨', '🏃'],
            nature: ['🐶', '🐱', '🐭', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦋', '🐝', '🐞', '🐢', '🐟', '🐬', '🐳', '🌵', '🌲', '🌳', '🌴', '🌱', '🍀', '🌸', '🌻', '🌹', '🌷', '🍁', '🌈', '☀️', '🌙', '⭐', '❄️'],
            food: ['🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍒', '🍑', '🥝', '🥑', '🍅', '🥕', '🌽', '🥐', '🍞', '🧀', '🥚', '🍳', '🍔', '🍟', '🍕', '🌭', '🥙', '🌮', '🍝', '🍣', '🍰', '🎂', '🍩', '🍪', '🍫', '🍦', '☕', '🍵', '🥤', '🍷', '🍺', '🥂'],
            travel: ['🚗', '🚕', '🚌', '🚑', '🚒', '🚓', '🚚', '🚲', '🛵', '🏍️', '🚆', '✈️', '🚀', '🚢', '⛵', '🚦', '🗺️', '🧭', '🏔️', '🏕️', '🏖️', '🏝️', '🏠', '🏡', '🏢', '🏥', '🏫', '🏛️', '🕌', '⛪', '🗼', '🌉'],
            activities: ['⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏓', '🏸', '🥊', '🎯', '⛳', '🎳', '🚴', '🏊', '🧘', '🏆', '🥇', '🥈', '🥉', '🏅', '🎉', '🎊', '🎈', '🎁', '🎄', '🎃', '🎨', '🎬', '🎤', '🎧', '🎸', '🎮'],
            objects: ['📱', '💻', '⌨️', '🖨️', '📷', '🎥', '📺', '📻', '⏰', '⌛', '💡', '🔦', '🔋', '🔌', '💰', '💳', '💎', '🔧', '🔨', '⚙️', '🔑', '🔒', '📦', '✉️', '📅', '📌', '📎', '✏️', '📝', '📚', '🛒', '🎀'],
            symbols: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '💯', '✅', '☑️', '✔️', '❌', '❗', '❓', '⚠️', '🚫', '⛔', '♻️', '🔥', '✨', '⚡', '💥', '💫', '🆕', '🆓', '🆗', '🔝', '🔴', '🟢', '🔵']
        }
    };

    // The icons (a Bootstrap Icons name => code point map) whose name holds
    // the query, any case, spaces read as dashes; names starting with it
    // first, then the rest, each group by name: [{ name, code }], at most
    // limit of them and the total in found.
    function iconSearch(map, query, limit) {
        var q = String(query || '').trim().toLowerCase().replace(/\s+/g, '-'), first = [], rest = [];

        Object.keys(map || {}).sort().forEach(function (name) {
            var at = q ? name.indexOf(q) : 0, code = Number(map[name]);

            if ((at < 0) || !(code > 0)) {
                return;
            }

            (at === 0 ? first : rest).push({ name: name, code: code });
        });

        var all = first.concat(rest);

        return { icons: all.slice(0, (limit > 0) ? limit : all.length), found: all.length };
    }

    // The last eight emoji used, newest first, each once; only short
    // strings count.
    function emojiRecent(list, emoji) {
        var ok = function (e) { return (typeof e === 'string') && (e !== '') && (e.length <= 16); };
        var keep = Array.isArray(list) ? list.filter(ok) : [];

        if (!ok(emoji)) {
            return keep.slice(0, 8);
        }

        return [emoji].concat(keep.filter(function (e) { return e !== emoji; })).slice(0, 8);
    }

    // The characters a glyph layer draws: an icon's code point or the emoji;
    // '' for anything else.
    function glyphText(l) {
        if (!l) {
            return '';
        }

        if (l.set === 'bi') {
            var code = Number(l.code);

            return ((code > 0) && (code <= 0x10ffff)) ? String.fromCodePoint(code) : '';
        }

        return ((l.set === 'emoji') && (typeof l.code === 'string')) ? l.code.slice(0, 16) : '';
    }

    // A glyph's font size in its box: the box's shorter side, so a stretched
    // box keeps the glyph's own shape.
    function glyphSize(l) {
        return Math.max(1, Math.min(Number(l.w) || 0, Number(l.h) || 0));
    }

    // A placed sticker or glyph with another one in its place: pick is a
    // built-in sticker { type: 'sticker', kind, label, fill, ink, w, h } (its
    // label already translated) or a glyph { type: 'glyph', set: 'bi'|'emoji',
    // code }; oldText is the old sticker kind's own label text (translated).
    // Turn, opacity, motion and every other field stay. What the person
    // changed on the old one stays too: a label other than its kind's text, a
    // colour or ink other than its kind's (an icon's other than #212529);
    // else the pick brings its own. The pick keeps its own proportions,
    // fitted inside the old box about its centre; a swap between the same
    // proportions keeps the box. The old type's own fields go (a sticker's
    // kind, label and ink, a glyph's set and code); the colour stays on an
    // emoji, so an emoji turned back into an icon gets it again. null when
    // the layer or the pick is not one of these.
    function swapGlyph(l, pick, oldText) {
        if (!l || ((l.type !== 'sticker') && (l.type !== 'glyph')) || !pick) {
            return null;
        }

        var toSticker = (pick.type === 'sticker');

        if (toSticker ? !pick.kind : ((pick.type !== 'glyph') || !glyphText({ set: pick.set, code: pick.code }))) {
            return null;
        }

        var next = clone(l), was = l.type, def = null, i;

        for (i = 0; (was === 'sticker') && (i < STICKER.STICKERS.length); i++) {
            if (STICKER.STICKERS[i].kind === l.kind) {
                def = STICKER.STICKERS[i];
            }
        }

        // The old colour when the person chose it, else null.
        var own = function (value, given) {
            return (value && (String(value).toLowerCase() !== String(given || '').toLowerCase())) ? value : null;
        };
        var color = (was === 'sticker') ? own(l.color, def && def.fill) : ((l.set === 'bi') ? own(l.color, '#212529') : null);

        ['kind', 'label', 'ink', 'set', 'code'].forEach(function (k) { delete next[k]; });

        if (toSticker) {
            next.type = 'sticker';
            next.kind = pick.kind;
            next.label = ((was === 'sticker') && (String(l.label || '') !== String(oldText || ''))) ? String(l.label || '') : String(pick.label || '');
            next.color = color || pick.fill || '#000000';
            next.ink = ((was === 'sticker') && own(l.ink, def && def.ink)) || pick.ink || '#ffffff';
        } else {
            next.type = 'glyph';
            next.set = pick.set;
            next.code = pick.code;

            if (was === 'sticker') {
                next.color = color || '#212529';
            } else if ((pick.set === 'bi') && !next.color) {
                next.color = '#212529';
            }
        }

        // The pick's own proportions inside the old box, same centre.
        var ratio = (toSticker && (pick.w > 0) && (pick.h > 0)) ? pick.w / pick.h : 1;
        var w = Number(l.w) || 0, h = Number(l.h) || 0;

        if ((w > 0) && (h > 0) && (Math.abs(w / h - ratio) > 1e-6)) {
            var nw = (w / h > ratio) ? h * ratio : w, nh = nw / ratio;

            next.x = l.x + (w - nw) / 2;
            next.y = l.y + (h - nh) / 2;
            next.w = nw;
            next.h = nh;
        }

        return next;
    }

    var GLYPH = { EMOJI: EMOJI, iconSearch: iconSearch, emojiRecent: emojiRecent, glyphText: glyphText, glyphSize: glyphSize, swapGlyph: swapGlyph };

    // ---- warp ----

    // A path or a drawn shape bent as a whole, kept as l.warp and drawn by
    // mapping its flattened outline: a preset ({ style, bend, h, v }: bend,
    // horizontal and vertical distortion, each -1 to 1) or an envelope mesh
    // ({ style: 'mesh', cols, rows, pts }: cols x rows points, row by row,
    // as parts of the box; the curve runs through them). The mapping works in
    // the box's frame before the mirror, so turns and flips carry it.

    var WARP_STYLES = ['arc', 'arch', 'bulge', 'flag', 'wave', 'fish', 'rise', 'squeeze', 'twist'];
    var MESH_MIN = 3;
    var MESH_MAX = 6;

    // A curve's flattening step in pixels and the most points one segment gets.
    var WARP_STEP = 3;
    var WARP_MAX = 64;

    // Whether a layer can be warped: a path, or a drawn shape that is not a
    // line or an arrow (their heads do not bend).
    function warpable(l) {
        if (!l) {
            return false;
        }

        if (l.type === 'path') {
            return !!(l.knots && (l.knots.length > 1));
        }

        return (DRAW_KINDS.indexOf(l.type) >= 0) && (LINE_KINDS.indexOf(l.type) < 0);
    }

    function meshValid(wp) {
        var c = Number(wp.cols), r = Number(wp.rows);

        return (c >= MESH_MIN) && (c <= MESH_MAX) && (r >= MESH_MIN) && (r <= MESH_MAX)
            && Array.isArray(wp.pts) && (wp.pts.length === c * r);
    }

    // Whether the layer's drawing goes through its warp.
    function warpOn(l) {
        var wp = l && l.warp;

        if (!wp || !warpable(l)) {
            return false;
        }

        return (wp.style === 'mesh') ? meshValid(wp) : (WARP_STYLES.indexOf(wp.style) >= 0);
    }

    // The even mesh that changes nothing.
    function meshGrid(cols, rows) {
        var c = clamp(Math.round(cols) || MESH_MIN, MESH_MIN, MESH_MAX), r = clamp(Math.round(rows) || MESH_MIN, MESH_MIN, MESH_MAX), pts = [], i, j;

        for (j = 0; j < r; j++) {
            for (i = 0; i < c; i++) {
                pts.push([frac(i, c - 1), frac(j, r - 1)]);
            }
        }

        return { style: 'mesh', cols: c, rows: r, pts: pts };
    }

    // One Catmull-Rom step between p1 and p2: an even row stays straight.
    function catmull(p0, p1, p2, p3, t) {
        var t2 = t * t, t3 = t2 * t;

        return [0, 1].map(function (k) {
            return 0.5 * (2 * p1[k] + (p2[k] - p0[k]) * t + (2 * p0[k] - 5 * p1[k] + 4 * p2[k] - p3[k]) * t2 + (3 * p1[k] - p0[k] - 3 * p2[k] + p3[k]) * t3);
        });
    }

    // A mesh point; one past an edge is mirrored through the edge point.
    function meshPoint(wp, i, j) {
        var c = wp.cols, r = wp.rows;
        var ext = function (a, b) { return [2 * a[0] - b[0], 2 * a[1] - b[1]]; };

        if (i < 0) { return ext(meshPoint(wp, 0, j), meshPoint(wp, 1, j)); }
        if (i >= c) { return ext(meshPoint(wp, c - 1, j), meshPoint(wp, c - 2, j)); }
        if (j < 0) { return ext(meshPoint(wp, i, 0), meshPoint(wp, i, 1)); }
        if (j >= r) { return ext(meshPoint(wp, i, r - 1), meshPoint(wp, i, r - 2)); }

        return wp.pts[j * c + i];
    }

    // Where the mesh takes the box's point u, v (parts of the box).
    function meshAt(wp, u, v) {
        var s = clamp(u, 0, 1) * (wp.cols - 1), q = clamp(v, 0, 1) * (wp.rows - 1);
        var i = Math.min(Math.floor(s), wp.cols - 2), j = Math.min(Math.floor(q), wp.rows - 2), rows = [], n;

        for (n = j - 1; n <= j + 2; n++) {
            rows.push(catmull(meshPoint(wp, i - 1, n), meshPoint(wp, i, n), meshPoint(wp, i + 1, n), meshPoint(wp, i + 2, n), s - i));
        }

        return catmull(rows[0], rows[1], rows[2], rows[3], q - j);
    }

    // The mesh with cols x rows points, each where the old mesh took it.
    function meshResize(wp, cols, rows) {
        var grid = meshGrid(cols, rows), old = (wp && (wp.style === 'mesh') && meshValid(wp)) ? wp : null;

        if (old) {
            grid.pts = grid.pts.map(function (p) {
                var q = meshAt(old, p[0], p[1]);

                return [frac(q[0], 1), frac(q[1], 1)];
            });
        }

        return grid;
    }

    // Where the warp takes the point u, v of a w x h box, in the box's
    // pixels. The presets bend around the box's middle.
    function warpPoint(wp, w, h, u, v) {
        if (wp.style === 'mesh') {
            var m = meshAt(wp, u, v);

            return [m[0] * w, m[1] * h];
        }

        var b = clamp(Number(wp.bend) || 0, -1, 1), X = u - 0.5, Y = v - 0.5;
        var x = X * w * (1 + clamp(Number(wp.v) || 0, -1, 1) * Y), y = Y * h * (1 + clamp(Number(wp.h) || 0, -1, 1) * X);
        var dome = 1 - 4 * X * X, a, r, phi, R;

        if (wp.style === 'arc') {
            a = b * Math.PI;

            if (Math.abs(a) > 1e-9) {
                // The middle line becomes an arc of angle a; the box's top
                // lies outside it, its bottom inside.
                R = w / a;
                phi = x / R;
                r = R - y;
                x = r * Math.sin(phi);
                y = R - r * Math.cos(phi);
            }
        } else if (wp.style === 'arch') {
            y -= b * h / 2 * dome;
        } else if (wp.style === 'bulge') {
            y *= 1 + b * dome;
        } else if (wp.style === 'flag') {
            y -= b * h / 4 * Math.sin(2 * Math.PI * X);
        } else if (wp.style === 'wave') {
            y -= b * h / 4 * Math.sin(2 * Math.PI * (X + Y));
        } else if (wp.style === 'fish') {
            y *= 1 - b / 2 * Math.sin(2 * Math.PI * X);
        } else if (wp.style === 'rise') {
            y -= b * h / 2 * Math.sin(Math.PI * X);
        } else if (wp.style === 'squeeze') {
            x *= 1 - b / 2 * (1 - 4 * Y * Y);
        } else if (wp.style === 'twist') {
            // Turns most in the middle, not at all at the corners.
            a = b * Math.PI * (1 - Math.min(1, Math.hypot(2 * X, 2 * Y) / Math.SQRT2));
            r = x;
            x = r * Math.cos(a) - y * Math.sin(a);
            y = r * Math.sin(a) + y * Math.cos(a);
        }

        return [x + w / 2, y + h / 2];
    }

    // Knots as a point list in short steps, straight segments included
    // (a warp bends them).
    function flattenKnots(knots, closed) {
        var pts = knots.length ? [[knots[0].x, knots[0].y]] : [];

        segments(knots, closed).forEach(function (s) {
            var a = s[0], b = s[1];
            var len = Math.hypot(a.ox - a.x, a.oy - a.y) + Math.hypot(b.ix - a.ox, b.iy - a.oy) + Math.hypot(b.x - b.ix, b.y - b.iy);
            var n = clamp(Math.ceil(len / WARP_STEP), 2, WARP_MAX), k;

            for (k = 1; k <= n; k++) {
                pts.push(cubicAt(a, b, k / n));
            }
        });

        return pts;
    }

    // The layer's contours before the warp, as knots in its box's frame: a
    // path's own (and the other contours of outlined letters), a shape's as
    // convert to path gives them.
    function warpSource(l) {
        var bare = Object.assign({}, l), out;

        delete bare.warp;
        delete bare.puppet;

        if (l.type !== 'path') {
            return [shapeKnots(bare)];
        }

        out = [{ knots: pathKnots(bare), closed: !!l.closed }];

        (Array.isArray(l.subs) ? l.subs : []).forEach(function (sub) {
            out.push({ knots: moveKnots(sub.knots || [], function (u, v) { return [u * l.w, v * l.h]; }), closed: !!sub.closed });
        });

        return out;
    }

    // The warped contours: [{ pts, closed }] in the box's frame; a closed
    // one does not repeat its first point. The free deformation's pins
    // (l.puppet) move the points after the warp.
    function warpContours(l) {
        var w = l.w, h = l.h, warped = warpOn(l), pins = puppetOn(l) ? puppetPins(l) : null;

        return warpSource(l).map(function (c) {
            var pts = flattenKnots(c.knots, c.closed && (c.knots.length > 2)), closed = c.closed && (c.knots.length > 2);

            if (closed && (pts.length > 1)) {
                pts.pop();
            }

            return {
                closed: closed,
                pts: pts.map(function (p) {
                    var q = warped ? warpPoint(l.warp, w, h, w ? p[0] / w : 0, h ? p[1] / h : 0) : [p[0], p[1]];

                    return pins ? mlsRigid(pins, q) : q;
                })
            };
        });
    }

    // The warped drawing as shapePath() answers it.
    function warpShape(l) {
        var cs = warpContours(l);

        return {
            d: cs.map(function (c) {
                return c.pts.map(function (p, i) { return (i ? 'L' : 'M') + rnd(p[0]) + ' ' + rnd(p[1]); }).join('') + (c.closed ? 'Z' : '');
            }).join(''),
            head: null,
            closed: cs.length ? cs[0].closed : false
        };
    }

    // The warped outline as outline() answers it: a closed list ends on its
    // first point.
    function warpOutline(l) {
        return warpContours(l).map(function (c) {
            return c.closed ? c.pts.concat([c.pts[0]]) : c.pts;
        });
    }

    // Expand: the drawn outline baked into a path of corner points, its box
    // fitted to it, angle and mirror kept. The fields to set; the caller
    // drops the warp and a shape's own fields.
    function warpExpand(l) {
        var cs = warpContours(l).filter(function (c) { return c.pts.length > 1; }), all = [];

        cs.forEach(function (c) { all = all.concat(c.pts); });

        if (!all.length) {
            return null;
        }

        var xs = all.map(function (p) { return p[0]; }), ys = all.map(function (p) { return p[1]; });
        var x0 = Math.min.apply(null, xs), y0 = Math.min.apply(null, ys), bw = Math.max.apply(null, xs) - x0, bh = Math.max.apply(null, ys) - y0;
        var w = Math.max(1, bw), h = Math.max(1, bh), left = x0 + bw / 2 - w / 2, top = y0 + bh / 2 - h / 2, c = drawnPoint(l, left + w / 2, top + h / 2);
        var part = function (pts) {
            return pts.map(function (p) { return knot(frac(p[0] - left, w), frac(p[1] - top, h)); });
        };
        var out = { type: 'path', x: c[0] - w / 2, y: c[1] - h / 2, w: w, h: h, knots: part(cs[0].pts), closed: cs[0].closed };

        if (cs.length > 1) {
            out.subs = cs.slice(1).map(function (o) { return { knots: part(o.pts), closed: o.closed }; });
        }

        if (eraseOn(l)) {
            out.erase = eraseShift(l, left, top, w, h);
        }

        return out;
    }

    var WARP = {
        WARP_STYLES: WARP_STYLES, MESH_MIN: MESH_MIN, MESH_MAX: MESH_MAX, warpable: warpable, warpOn: warpOn, meshGrid: meshGrid,
        meshAt: meshAt, meshResize: meshResize, warpPoint: warpPoint, flattenKnots: flattenKnots, warpContours: warpContours,
        warpShape: warpShape, warpOutline: warpOutline, warpExpand: warpExpand
    };

    // ---- free deformation ----

    // Pins on a path or a drawn shape, kept as l.puppet { pins: [{ rest,
    // at }] } (each [u, v] as parts of the box, before the mirror): a pin
    // dragged from rest to at bends the outline around the others by rigid
    // moving least squares (Schaefer, McPhail, Warren 2006). It is drawn on
    // the outline the warp gives, through warpContours().

    var PUPPET_MAX = 32;

    function pinValid(p) {
        var ok = function (a) { return Array.isArray(a) && (a.length === 2) && isFinite(a[0]) && isFinite(a[1]); };

        return !!p && ok(p.rest) && ok(p.at);
    }

    // Whether the layer's drawing goes through its pins.
    function puppetOn(l) {
        var pp = l && l.puppet;

        return !!pp && warpable(l) && Array.isArray(pp.pins) && (pp.pins.length > 0) && (pp.pins.length <= PUPPET_MAX) && pp.pins.every(pinValid);
    }

    // The pins in the box's pixels: [{ p: rest, q: moved }].
    function puppetPins(l) {
        return l.puppet.pins.map(function (pin) {
            return { p: [pin.rest[0] * l.w, pin.rest[1] * l.h], q: [pin.at[0] * l.w, pin.at[1] * l.h] };
        });
    }

    // Where the pins take the point v: the rotation and shift that best
    // carry the pins near v (weights 1 / distance squared) to their moved
    // places. One pin shifts everything; on a pin, its moved place.
    function mlsRigid(pins, v) {
        var sw = 0, px = 0, py = 0, qx = 0, qy = 0, dot = 0, crs = 0, spread = 0, i, w, dx, dy, a, b, c, d;
        var ws = [];

        if (!pins.length) {
            return [v[0], v[1]];
        }

        for (i = 0; i < pins.length; i++) {
            dx = pins[i].p[0] - v[0];
            dy = pins[i].p[1] - v[1];
            w = dx * dx + dy * dy;

            if (w < 1e-12) {
                return [pins[i].q[0], pins[i].q[1]];
            }

            w = 1 / w;
            ws.push(w);
            sw += w;
            px += w * pins[i].p[0];
            py += w * pins[i].p[1];
            qx += w * pins[i].q[0];
            qy += w * pins[i].q[1];
        }

        px /= sw;
        py /= sw;
        qx /= sw;
        qy /= sw;

        for (i = 0; i < pins.length; i++) {
            a = pins[i].p[0] - px;
            b = pins[i].p[1] - py;
            c = pins[i].q[0] - qx;
            d = pins[i].q[1] - qy;
            dot += ws[i] * (a * c + b * d);
            crs += ws[i] * (a * d - b * c);
            spread += ws[i] * (a * a + b * b);
        }

        // Pins on one spot (or one pin) give no turn, only the shift:
        // rounding would otherwise pick a random angle.
        var len = (spread / sw > 1e-8) ? Math.hypot(dot, crs) : 0, cos = len ? dot / len : 1, sin = len ? crs / len : 0;

        dx = v[0] - px;
        dy = v[1] - py;

        return [cos * dx - sin * dy + qx, sin * dx + cos * dy + qy];
    }

    // The rest place (parts of the box) of the drawn point u, v of a w x h
    // box under its pins: the deformation undone by a few fixed-point
    // steps, so a pin added to a bent shape sits where it was pressed.
    function puppetRest(l, u, v) {
        var pins = puppetOn(l) ? puppetPins(l) : [], w = l.w || 1, h = l.h || 1, goal = [u * w, v * h], r = goal.slice(), i, f;

        for (i = 0; pins.length && (i < 24); i++) {
            f = mlsRigid(pins, r);
            r = [r[0] + goal[0] - f[0], r[1] + goal[1] - f[1]];

            if (Math.hypot(goal[0] - f[0], goal[1] - f[1]) < 0.01) {
                break;
            }
        }

        return [frac(r[0], w), frac(r[1], h)];
    }

    // The puppet with a pin added where the drawn point u, v is (nothing
    // moves), or the same puppet when it holds the most pins.
    function puppetAdd(l, u, v) {
        var pp = (l.puppet && Array.isArray(l.puppet.pins)) ? clone(l.puppet) : { pins: [] };

        if (pp.pins.length >= PUPPET_MAX) {
            return pp;
        }

        pp.pins.push({ rest: puppetRest(l, u, v), at: [frac(u, 1), frac(v, 1)] });

        return pp;
    }

    // The pin nearest to the drawn point x, y (the box's pixels) within
    // slack pixels, or -1.
    function puppetHit(l, x, y, slack) {
        var best = -1, near = slack, pins = (l.puppet && Array.isArray(l.puppet.pins)) ? l.puppet.pins : [];

        pins.forEach(function (pin, i) {
            var dd = pinValid(pin) ? Math.hypot(pin.at[0] * l.w - x, pin.at[1] * l.h - y) : Infinity;

            if (dd <= near) {
                near = dd;
                best = i;
            }
        });

        return best;
    }

    var PUPPET = {
        PUPPET_MAX: PUPPET_MAX, puppetOn: puppetOn, puppetPins: puppetPins, mlsRigid: mlsRigid, puppetRest: puppetRest,
        puppetAdd: puppetAdd, puppetHit: puppetHit
    };

    // -- scenes (pg_image_editor_scenes.js) --

    // A canvas may hold scenes played one after another: doc.scenes lists
    // them as { id, name, transition } and doc.scene names the open one,
    // whose layers, background and duration are the document's own
    // (doc.layers, doc.artboard.background, doc.motion.duration), so the
    // tools need no change. A closed scene keeps them in its entry: layers,
    // background, duration. A document without doc.scenes is one scene.
    var SCENE_MAX = 50;
    var SCENE_LAYERS_MAX = 500;
    var SCENE_TRANSITIONS = ['none', 'fade', 'slide', 'zoom', 'wipe'];
    var SCENE_TRANSITION_MIN = 100, SCENE_TRANSITION_MAX = 2000, SCENE_TRANSITION_MS = 600;
    var SCENE_DEFAULT = 3000;

    function sceneCount(doc) {
        return (doc && Array.isArray(doc.scenes)) ? doc.scenes.length : 0;
    }

    // The open scene's place in doc.scenes; -1 without scenes.
    function sceneIndex(doc) {
        var list = (doc && Array.isArray(doc.scenes)) ? doc.scenes : [];

        for (var i = 0; i < list.length; i++) {
            if (list[i] && (list[i].id === doc.scene)) {
                return i;
            }
        }

        return -1;
    }

    // A transition to the next scene with its defaults: a kind from
    // SCENE_TRANSITIONS and its time in ms, in steps of 100.
    function sceneTransition(tr) {
        tr = (tr && (typeof tr === 'object')) ? tr : {};

        return {
            kind: (SCENE_TRANSITIONS.indexOf(tr.kind) >= 0) ? tr.kind : 'none',
            ms: clamp(Math.round((Number(tr.ms) || SCENE_TRANSITION_MS) / 100) * 100, SCENE_TRANSITION_MIN, SCENE_TRANSITION_MAX)
        };
    }

    function sceneDuration(ms) {
        return clamp(Math.round(Number(ms) || SCENE_DEFAULT), MOTION_MIN, MOTION_MAX);
    }

    // The scenes as they play, the open one filled from the document:
    // [{ id, name, layers, background, duration, transition, open }]. One
    // entry for a document without scenes. The layers are the document's
    // own arrays, not copies.
    function sceneList(doc) {
        var bg = (doc.artboard && (typeof doc.artboard.background === 'string')) ? doc.artboard.background : '#ffffff';
        var live = { layers: doc.layers || [], background: bg, duration: docMotion(doc).duration };

        if (!sceneCount(doc)) {
            return [{ id: '', name: '', layers: live.layers, background: bg, duration: live.duration, transition: sceneTransition(null), open: true }];
        }

        return doc.scenes.map(function (s) {
            var open = (s.id === doc.scene);

            return {
                id: s.id,
                name: (typeof s.name === 'string') ? s.name : '',
                layers: open ? live.layers : (Array.isArray(s.layers) ? s.layers : []),
                background: open ? bg : ((typeof s.background === 'string') ? s.background : '#ffffff'),
                duration: open ? live.duration : sceneDuration(s.duration),
                transition: sceneTransition(s.transition),
                open: open
            };
        });
    }

    // A scene id not yet in the document.
    function sceneNewId(doc) {
        var n = sceneCount(doc) + 1;

        while ((doc.scenes || []).some(function (s) { return s.id === 's' + n; })) {
            n++;
        }

        return 's' + n;
    }

    // The document's own content as its first scene (changes doc).
    function sceneStart(doc) {
        if (!sceneCount(doc)) {
            doc.scenes = [{ id: 's1', name: '', transition: sceneTransition(null) }];
            doc.scene = 's1';
            doc.motion = docMotion(doc);
        }

        return doc;
    }

    // Opens the scene id: the open one's layers, background and duration go
    // into its entry, the other's come out of it (changes doc).
    function sceneOpen(doc, id) {
        var from = sceneIndex(doc), to = -1;

        (doc.scenes || []).forEach(function (s, i) {
            if (s.id === id) {
                to = i;
            }
        });

        if ((to < 0) || (to === from)) {
            return doc;
        }

        var motion = docMotion(doc), board = doc.artboard || {}, next = doc.scenes[to];

        if (from >= 0) {
            Object.assign(doc.scenes[from], {
                layers: doc.layers || [],
                background: (typeof board.background === 'string') ? board.background : '#ffffff',
                duration: motion.duration
            });
        }

        doc.layers = Array.isArray(next.layers) ? next.layers : [];

        if (doc.artboard) {
            doc.artboard.background = (typeof next.background === 'string') ? next.background : '#ffffff';
        }

        doc.motion = Object.assign({}, doc.motion || motion, { duration: sceneDuration(next.duration) });
        delete next.layers;
        delete next.background;
        delete next.duration;
        doc.scene = id;

        return doc;
    }

    // A new scene after the open one, opened: empty, or a copy of the open
    // one (copy true) with its duration and transition (changes doc).
    function sceneAdd(doc, copy) {
        sceneStart(doc);

        if (sceneCount(doc) >= SCENE_MAX) {
            return doc;
        }

        var at = sceneIndex(doc), open = doc.scenes[at], id = sceneNewId(doc), board = doc.artboard || {};

        doc.scenes.splice(at + 1, 0, {
            id: id,
            name: '',
            transition: copy ? sceneTransition(open.transition) : sceneTransition(null),
            layers: copy ? clone(doc.layers || []) : [],
            background: (typeof board.background === 'string') ? board.background : '#ffffff',
            duration: copy ? docMotion(doc).duration : SCENE_DEFAULT
        });

        return sceneOpen(doc, id);
    }

    // Deletes the scene id, opening its neighbour when it was open; with one
    // scene left the document has none (changes doc).
    function sceneDelete(doc, id) {
        var at = -1;

        (doc.scenes || []).forEach(function (s, i) {
            if (s.id === id) {
                at = i;
            }
        });

        if ((at < 0) || (sceneCount(doc) < 2)) {
            return doc;
        }

        if (doc.scene === id) {
            sceneOpen(doc, doc.scenes[(at + 1 < doc.scenes.length) ? at + 1 : at - 1].id);
        }

        doc.scenes.splice(at, 1);

        if (doc.scenes.length === 1) {
            delete doc.scenes;
            delete doc.scene;
        }

        return doc;
    }

    // Moves the scene id by delta places, within the list (changes doc).
    function sceneMove(doc, id, delta) {
        var list = doc.scenes || [], at = -1;

        list.forEach(function (s, i) {
            if (s.id === id) {
                at = i;
            }
        });

        var to = clamp(at + delta, 0, list.length - 1);

        if ((at >= 0) && (to !== at)) {
            list.splice(to, 0, list.splice(at, 1)[0]);
        }

        return doc;
    }

    // Sets a scene's duration or its transition to the next ({ kind, ms }
    // merged) (changes doc). The open scene's duration is the document's.
    function sceneSet(doc, id, patch) {
        var s = (doc.scenes || []).filter(function (e) { return e.id === id; })[0];

        if (patch.duration !== undefined) {
            if (!s || (s.id === doc.scene)) {
                doc.motion = Object.assign(docMotion(doc), { duration: sceneDuration(patch.duration) });
            } else {
                s.duration = sceneDuration(patch.duration);
            }
        }

        if (s && patch.transition) {
            s.transition = sceneTransition(Object.assign({}, sceneTransition(s.transition), patch.transition));
        }

        return doc;
    }

    // The whole length: every scene back to back.
    function sceneTotal(doc) {
        return sceneList(doc).reduce(function (sum, s) { return sum + s.duration; }, 0);
    }

    // Where scene i starts on the whole timeline (ms): the scenes before it
    // back to back; 0 for the first scene or a document without scenes.
    function sceneStartAt(doc, i) {
        return sceneList(doc).slice(0, Math.max(0, i)).reduce(function (sum, s) { return sum + s.duration; }, 0);
    }

    // The place a dragged card lands at: mids are the middles of the other
    // cards (in order, without the dragged one), x the pointer's; answers
    // how many of them stay before it.
    function sceneSlot(mids, x) {
        var n = 0;

        (mids || []).forEach(function (m) {
            if (x > m) {
                n++;
            }
        });

        return n;
    }

    // The moment t of the whole animation: the scene playing (index) at its
    // own time (local) and, while the previous scene's transition runs over
    // the start of this one, that scene (from, shown at its end), the kind
    // and its progress p (0-1, eased). A transition is never longer than the
    // scene it leads into.
    function sceneAt(doc, t) {
        var list = sceneList(doc), start = 0, i;

        t = Math.max(0, Number(t) || 0);

        for (i = 0; i < list.length - 1; i++) {
            if (t < start + list[i].duration) {
                break;
            }

            start += list[i].duration;
        }

        var local = Math.min(t - start, list[i].duration), at = { index: i, local: local, from: null, kind: 'none', p: 1 };
        var tr = (i > 0) ? list[i - 1].transition : null, ms = tr ? Math.min(tr.ms, list[i].duration) : 0;

        if (tr && (tr.kind !== 'none') && (local < ms)) {
            at.from = i - 1;
            at.kind = tr.kind;
            at.p = easeAt('in-out', local / ms);
        }

        return at;
    }

    // Scene i as a document of its own for drawing a frame: its layers,
    // background and duration in place of the open scene's.
    function sceneView(doc, i) {
        var s = sceneList(doc)[i], view = Object.assign({}, doc, { layers: s.layers, motion: Object.assign(docMotion(doc), { duration: s.duration }) });

        if (doc.artboard) {
            view.artboard = Object.assign({}, doc.artboard, { background: s.background });
        }

        delete view.scenes;
        delete view.scene;

        return view;
    }

    // How the two scenes are drawn at a transition's progress p on a w x h
    // frame: each { alpha, x, y, scale } (scale about the centre), the new
    // one with clip, the part of the frame it shows (a wipe), or null.
    function sceneTransitionAt(kind, p, w, h) {
        var from = { alpha: 1, x: 0, y: 0, scale: 1 }, to = { alpha: 1, x: 0, y: 0, scale: 1, clip: null };

        p = clamp(p, 0, 1);

        switch (kind) {
            case 'fade':
                to.alpha = p;
                break;
            case 'slide':
                from.x = -w * p;
                to.x = w * (1 - p);
                break;
            case 'zoom':
                to.scale = 0.6 + 0.4 * p;
                to.alpha = p;
                from.scale = 1 + 0.15 * p;
                break;
            case 'wipe':
                to.clip = { x: 0, y: 0, w: w * p, h: h };
                break;
            default:
                from = null;
        }

        return { from: from, to: to };
    }

    var SCENES = {
        SCENE_MAX: SCENE_MAX, SCENE_LAYERS_MAX: SCENE_LAYERS_MAX, SCENE_TRANSITIONS: SCENE_TRANSITIONS, SCENE_TRANSITION_MIN: SCENE_TRANSITION_MIN,
        SCENE_TRANSITION_MAX: SCENE_TRANSITION_MAX, sceneCount: sceneCount, sceneIndex: sceneIndex, sceneTransition: sceneTransition, sceneList: sceneList,
        sceneStart: sceneStart, sceneOpen: sceneOpen, sceneAdd: sceneAdd, sceneDelete: sceneDelete, sceneMove: sceneMove, sceneSet: sceneSet,
        sceneTotal: sceneTotal, sceneStartAt: sceneStartAt, sceneSlot: sceneSlot, sceneAt: sceneAt, sceneView: sceneView, sceneTransitionAt: sceneTransitionAt
    };

    // -- presenting the scenes and their PDF (pg_image_editor_present.js) --

    // A swipe moves on once it is this long and mostly sideways; a press
    // that moved less than PRESENT_TAP is a tap.
    var PRESENT_SWIPE = 40, PRESENT_TAP = 10;
    // A PDF page's points per pixel: a pixel is 1/96 inch, a point 1/72.
    var PDF_PT = 0.75;

    // A sw x sh scene inside a vw x vh screen at its largest, centred
    // (letterboxed): whole pixels, at least 1.
    function presentFit(sw, sh, vw, vh) {
        var k = Math.min(vw / Math.max(1, sw), vh / Math.max(1, sh));
        var w = Math.max(1, Math.round(sw * k)), h = Math.max(1, Math.round(sh * k));

        return { x: Math.round((vw - w) / 2), y: Math.round((vh - h) / 2), w: w, h: h };
    }

    // What a key does while presenting: next, prev, first, last, close or
    // null (the key is not Present's).
    function presentKey(key) {
        var map = {
            ArrowRight: 'next', ArrowDown: 'next', PageDown: 'next', ' ': 'next', Spacebar: 'next', Enter: 'next',
            ArrowLeft: 'prev', ArrowUp: 'prev', PageUp: 'prev', Backspace: 'prev',
            Home: 'first', End: 'last', Escape: 'close', Esc: 'close'
        };

        return map.hasOwnProperty(key) ? map[key] : null;
    }

    // A press that moved dx, dy: a swipe to the left is next, to the right
    // prev, a short press a tap; anything else (mostly up or down) null.
    function presentGesture(dx, dy) {
        var ax = Math.abs(dx), ay = Math.abs(dy);

        if (Math.max(ax, ay) < PRESENT_TAP) {
            return 'tap';
        }

        if ((ax >= PRESENT_SWIPE) && (ax > 1.5 * ay)) {
            return (dx < 0) ? 'next' : 'prev';
        }

        return null;
    }

    // The scene an action leads to from index of count; -1 closes. Next on
    // the last scene and prev on the first stay where they are.
    function presentGo(index, count, action) {
        var last = Math.max(0, count - 1);

        switch (action) {
            case 'next':
                return Math.min(last, index + 1);
            case 'prev':
                return Math.max(0, index - 1);
            case 'first':
                return 0;
            case 'last':
                return last;
            case 'close':
                return -1;
            default:
                return index;
        }
    }

    // How scene index is drawn at its own time local: arriving forward
    // over the previous scene's transition the whole animation's moment
    // (whole, the transition shown), else the scene alone at its time,
    // held at its end (local).
    function presentTime(doc, index, local, forward) {
        var list = sceneList(doc), s = list[index], prev = list[index - 1], ms = 0;

        local = Math.max(0, Number(local) || 0);

        if (forward && prev && (prev.transition.kind !== 'none')) {
            ms = Math.min(prev.transition.ms, s.duration);
        }

        if (local < ms) {
            return { whole: sceneStartAt(doc, index) + local, local: local };
        }

        return { whole: null, local: Math.min(local, s.duration) };
    }

    // ASCII text as bytes.
    function pdfText(s) {
        var out = new Uint8Array(s.length);

        for (var i = 0; i < s.length; i++) {
            out[i] = s.charCodeAt(i) & 255;
        }

        return out;
    }

    // A PDF with a page per picture: pages [{ jpeg (Uint8Array, a baseline
    // RGB JPEG), w, h (its pixels) }], each page the picture's size at 96
    // pixels an inch, the picture filling it (DCTDecode, no re-encoding).
    // Answers the file's bytes.
    function pdfFile(pages) {
        var parts = [], offsets = [], at = 0, kids = [], n = pages.length;

        function put(bytes) {
            parts.push(bytes);
            at += bytes.length;
        }

        function obj(num, text, stream) {
            offsets[num] = at;
            put(pdfText(num + ' 0 obj\n' + text + (stream ? '\nstream\n' : '\nendobj\n')));

            if (stream) {
                put(stream);
                put(pdfText('\nendstream\nendobj\n'));
            }
        }

        function pt(px) {
            return String(Math.round(px * PDF_PT * 100) / 100);
        }

        put(pdfText('%PDF-1.4\n'));
        put(new Uint8Array([37, 226, 227, 207, 211, 10]));

        for (var i = 0; i < n; i++) {
            kids.push((3 + 3 * i) + ' 0 R');
        }

        obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
        obj(2, '<< /Type /Pages /Kids [' + kids.join(' ') + '] /Count ' + n + ' >>');

        pages.forEach(function (p, i) {
            var page = 3 + 3 * i, w = pt(p.w), h = pt(p.h), draw = pdfText('q ' + w + ' 0 0 ' + h + ' 0 0 cm /Im0 Do Q');

            obj(page, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + w + ' ' + h + '] /Resources << /XObject << /Im0 ' + (page + 2) + ' 0 R >> >> /Contents ' + (page + 1) + ' 0 R >>');
            obj(page + 1, '<< /Length ' + draw.length + ' >>', draw);
            obj(page + 2, '<< /Type /XObject /Subtype /Image /Width ' + p.w + ' /Height ' + p.h + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + p.jpeg.length + ' >>', p.jpeg);
        });

        var count = 3 + 3 * n, xref = at, table = 'xref\n0 ' + count + '\n0000000000 65535 f \n';

        for (var k = 1; k < count; k++) {
            table += ('0000000000' + offsets[k]).slice(-10) + ' 00000 n \n';
        }

        put(pdfText(table + 'trailer\n<< /Size ' + count + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n'));

        var out = new Uint8Array(at), o = 0;

        parts.forEach(function (b) {
            out.set(b, o);
            o += b.length;
        });

        return out;
    }

    var PRESENT = {
        presentFit: presentFit, presentKey: presentKey, presentGesture: presentGesture, presentGo: presentGo, presentTime: presentTime, pdfFile: pdfFile
    };

    // -- outlines (pg_image_editor_outline.js) --

    // A font file read for its outlines (TrueType glyf or CFF), its
    // character map (formats 4 and 12), advances and pair kerning (GPOS
    // pair adjustments of the kern feature, else the kern table). Throws on
    // what it cannot read: WOFF, WOFF2, a collection, a CID-keyed CFF.
    function fontRead(buf) {
        var b = (buf instanceof Uint8Array) ? buf : new Uint8Array(buf), v = new DataView(b.buffer, b.byteOffset, b.byteLength);
        var tag = function (o) { return String.fromCharCode(b[o], b[o + 1], b[o + 2], b[o + 3]); };
        var t = {}, n, i, f;

        if (['\u0000\u0001\u0000\u0000', 'OTTO', 'true'].indexOf(tag(0)) < 0) {
            throw new Error('font');
        }

        n = v.getUint16(4);

        for (i = 0; i < n; i++) {
            t[tag(12 + 16 * i)] = v.getUint32(12 + 16 * i + 8);
        }

        if (!t.head || !t.maxp || !t.hhea || !t.hmtx || !t.cmap || !(t['CFF '] || (t.glyf && t.loca))) {
            throw new Error('font');
        }

        f = {
            v: v, t: t, upm: v.getUint16(t.head + 18) || 1000, longLoca: v.getInt16(t.head + 50) === 1, count: v.getUint16(t.maxp + 4),
            metrics: v.getUint16(t.hhea + 34), ascent: v.getInt16(t.hhea + 4), descent: v.getInt16(t.hhea + 6)
        };
        f.cmap = fontCmap(v, t.cmap);
        f.cff = t['CFF '] ? fontCff(v, t['CFF ']) : null;
        f.pairs = t.GPOS ? fontGposPairs(v, t.GPOS) : [];
        f.kern = (!f.pairs.length && t.kern) ? fontKernTable(v, t.kern) : {};

        return f;
    }

    // The character map's lookup: the best Unicode subtable, format 12
    // before format 4.
    function fontCmap(v, at) {
        var n = v.getUint16(at + 2), best = null, i, o, pid, eid, fmt, rank;

        for (i = 0; i < n; i++) {
            pid = v.getUint16(at + 4 + 8 * i);
            eid = v.getUint16(at + 6 + 8 * i);
            o = at + v.getUint32(at + 8 + 8 * i);
            fmt = v.getUint16(o);
            rank = ((pid === 0) || ((pid === 3) && ((eid === 1) || (eid === 10)))) ? ((fmt === 12) ? 2 : ((fmt === 4) ? 1 : 0)) : 0;

            if (rank && (!best || (rank > best.rank))) {
                best = { o: o, rank: rank };
            }
        }

        if (!best) {
            throw new Error('font');
        }

        o = best.o;

        if (best.rank === 2) {
            return function (cp) {
                for (var g = 0, count = v.getUint32(o + 12), p; g < count; g++) {
                    p = o + 16 + 12 * g;

                    if ((cp >= v.getUint32(p)) && (cp <= v.getUint32(p + 4))) {
                        return v.getUint32(p + 8) + cp - v.getUint32(p);
                    }
                }

                return 0;
            };
        }

        return function (cp) {
            var seg = v.getUint16(o + 6), ends = o + 14, starts = ends + seg + 2, deltas = starts + seg, ranges = deltas + seg, s, start, ro, g;

            for (s = 0; (s < seg) && (cp <= 0xffff); s += 2) {
                if (cp <= v.getUint16(ends + s)) {
                    start = v.getUint16(starts + s);

                    if (cp < start) {
                        return 0;
                    }

                    ro = v.getUint16(ranges + s);

                    if (!ro) {
                        return (cp + v.getInt16(deltas + s)) & 0xffff;
                    }

                    g = v.getUint16(ranges + s + ro + 2 * (cp - start));

                    return g ? ((g + v.getInt16(deltas + s)) & 0xffff) : 0;
                }
            }

            return 0;
        };
    }

    // The kern table's horizontal format 0 pairs as { 'left,right': value }.
    function fontKernTable(v, at) {
        var out = {}, n = (v.getUint16(at) === 0) ? v.getUint16(at + 2) : 0, o = at + 4, i, p, count;

        for (i = 0; i < n; i++) {
            if (((v.getUint16(o + 4) >> 8) === 0) && (v.getUint16(o + 4) & 1)) {
                for (p = 0, count = v.getUint16(o + 6); p < count; p++) {
                    out[v.getUint16(o + 14 + 6 * p) + ',' + v.getUint16(o + 16 + 6 * p)] = v.getInt16(o + 18 + 6 * p);
                }
            }

            o += v.getUint16(o + 2);
        }

        return out;
    }

    // The pair adjustment subtables (lookup type 2, or 9 holding one) that
    // the kern feature uses, in lookup order.
    function fontGposPairs(v, at) {
        var feats = at + v.getUint16(at + 6), looks = at + v.getUint16(at + 8), used = [], out = [], i, j, fo, l, type, sub, so;

        for (i = 0; i < v.getUint16(feats); i++) {
            if (String.fromCharCode(v.getUint8(feats + 2 + 6 * i), v.getUint8(feats + 3 + 6 * i), v.getUint8(feats + 4 + 6 * i), v.getUint8(feats + 5 + 6 * i)) === 'kern') {
                fo = feats + v.getUint16(feats + 6 + 6 * i);

                for (j = 0; j < v.getUint16(fo + 2); j++) {
                    if (used.indexOf(v.getUint16(fo + 4 + 2 * j)) < 0) {
                        used.push(v.getUint16(fo + 4 + 2 * j));
                    }
                }
            }
        }

        used.sort(function (a, c) { return a - c; }).forEach(function (index) {
            l = looks + v.getUint16(looks + 2 + 2 * index);
            type = v.getUint16(l);

            for (sub = 0; sub < v.getUint16(l + 4); sub++) {
                so = l + v.getUint16(l + 6 + 2 * sub);

                if ((type === 9) && (v.getUint16(so + 2) === 2)) {
                    out.push(so + v.getUint32(so + 4));
                } else if (type === 2) {
                    out.push(so);
                }
            }
        });

        return out;
    }

    // A glyph's index in a coverage table, or -1.
    function fontCoverage(v, at, g) {
        var n = v.getUint16(at + 2), i, o;

        for (i = 0; i < n; i++) {
            if (v.getUint16(at) === 1) {
                if (v.getUint16(at + 4 + 2 * i) === g) {
                    return i;
                }
            } else {
                o = at + 4 + 6 * i;

                if ((g >= v.getUint16(o)) && (g <= v.getUint16(o + 2))) {
                    return v.getUint16(o + 4) + g - v.getUint16(o);
                }
            }
        }

        return -1;
    }

    // A glyph's class in a class definition table (0 when not listed).
    function fontClass(v, at, g) {
        var i, o;

        if (v.getUint16(at) === 1) {
            i = g - v.getUint16(at + 2);

            return ((i >= 0) && (i < v.getUint16(at + 4))) ? v.getUint16(at + 6 + 2 * i) : 0;
        }

        for (i = 0; i < v.getUint16(at + 2); i++) {
            o = at + 4 + 6 * i;

            if ((g >= v.getUint16(o)) && (g <= v.getUint16(o + 2))) {
                return v.getUint16(o + 4);
            }
        }

        return 0;
    }

    function bitCount(n) {
        for (var c = 0; n; n >>= 1) {
            c += n & 1;
        }

        return c;
    }

    // The advance change between glyphs a and b in font units: the first
    // pair subtable that holds the pair, else the kern table.
    function fontKerning(f, a, c) {
        var v = f.v, i, s, cov, vf1, size, adv, o, k, n;

        for (i = 0; i < f.pairs.length; i++) {
            s = f.pairs[i];
            cov = fontCoverage(v, s + v.getUint16(s + 2), a);

            if (cov < 0) {
                continue;
            }

            vf1 = v.getUint16(s + 4);
            size = 2 * (bitCount(vf1) + bitCount(v.getUint16(s + 6)));
            adv = (vf1 & 4) ? 2 * bitCount(vf1 & 3) : -1;

            if (v.getUint16(s) === 1) {
                o = s + v.getUint16(s + 10 + 2 * cov);

                for (k = 0, n = v.getUint16(o); k < n; k++) {
                    if (v.getUint16(o + 2 + k * (2 + size)) === c) {
                        return (adv < 0) ? 0 : v.getInt16(o + 4 + k * (2 + size) + adv);
                    }
                }
            } else if (v.getUint16(s) === 2) {
                k = fontClass(v, s + v.getUint16(s + 10), c);

                if (k < v.getUint16(s + 14)) {
                    o = s + 16 + (fontClass(v, s + v.getUint16(s + 8), a) * v.getUint16(s + 14) + k) * size;

                    return (adv < 0) ? 0 : v.getInt16(o + adv);
                }
            }
        }

        return f.kern[a + ',' + c] || 0;
    }

    function fontAdvance(f, g) {
        return f.v.getUint16(f.t.hmtx + 4 * Math.min(g, f.metrics - 1));
    }

    function fontGlyph(f, cp) {
        var g = f.cmap(cp);

        return (g > 0) && (g < f.count) ? g : 0;
    }

    // Contours as cubic knots in font units (y up): move, line, curve and
    // close, each closed contour ending on its first point.
    function knotPen() {
        var list = [], cur = null;
        var close = function () {
            if (cur && (cur.length > 1)) {
                var a = cur[0], z = cur[cur.length - 1];

                if ((Math.abs(a.x - z.x) < 1e-9) && (Math.abs(a.y - z.y) < 1e-9)) {
                    a.ix = z.ix;
                    a.iy = z.iy;
                    cur.pop();
                }

                if (cur.length > 1) {
                    list.push(cur);
                }
            }

            cur = null;
        };

        return {
            move: function (x, y) { close(); cur = [knot(x, y)]; },
            line: function (x, y) { cur.push(knot(x, y)); },
            curve: function (x1, y1, x2, y2, x, y) {
                var z = cur[cur.length - 1];

                z.ox = x1;
                z.oy = y1;
                cur.push({ x: x, y: y, ix: x2, iy: y2, ox: x, oy: y, s: false });
            },
            done: function () { close(); return list; }
        };
    }

    // A TrueType glyph's points per contour ({ x, y, on }), components
    // placed by their offsets and matrices.
    function ttPoints(f, g, depth) {
        var v = f.v, loca = f.t.loca, at = f.longLoca ? v.getUint32(loca + 4 * g) : 2 * v.getUint16(loca + 2 * g);
        var end = f.longLoca ? v.getUint32(loca + 4 * g + 4) : 2 * v.getUint16(loca + 2 * g + 2), o = f.t.glyf + at, nc, out = [];

        if ((end <= at) || (depth > 8)) {
            return out;
        }

        nc = v.getInt16(o);

        if (nc < 0) {
            var flags = 0x20, p = o + 10, comp, dx, dy, a, bb, c, d;

            while (flags & 0x20) {
                flags = v.getUint16(p);
                comp = v.getUint16(p + 2);
                p += 4;
                dx = (flags & 1) ? v.getInt16(p) : v.getInt8(p);
                dy = (flags & 1) ? v.getInt16(p + 2) : v.getInt8(p + 1);
                p += (flags & 1) ? 4 : 2;
                a = d = 1;
                bb = c = 0;

                if (flags & 8) {
                    a = d = v.getInt16(p) / 16384;
                    p += 2;
                } else if (flags & 0x40) {
                    a = v.getInt16(p) / 16384;
                    d = v.getInt16(p + 2) / 16384;
                    p += 4;
                } else if (flags & 0x80) {
                    a = v.getInt16(p) / 16384;
                    bb = v.getInt16(p + 2) / 16384;
                    c = v.getInt16(p + 4) / 16384;
                    d = v.getInt16(p + 6) / 16384;
                    p += 8;
                }

                // Point-matched placement (no x, y values) is read as no shift.
                if (!(flags & 2)) {
                    dx = dy = 0;
                }

                ttPoints(f, comp, depth + 1).forEach(function (contour) {
                    out.push(contour.map(function (q) { return { x: a * q.x + c * q.y + dx, y: bb * q.x + d * q.y + dy, on: q.on }; }));
                });
            }

            return out;
        }

        var ends = [], i, total, flag = [], fl, rep, x = 0, y = 0, pts = [], q = o + 10;

        for (i = 0; i < nc; i++) {
            ends.push(v.getUint16(q));
            q += 2;
        }

        total = nc ? ends[nc - 1] + 1 : 0;
        q += 2 + v.getUint16(q);

        while (flag.length < total) {
            fl = v.getUint8(q++);
            flag.push(fl);

            if (fl & 8) {
                for (rep = v.getUint8(q++); rep > 0; rep--) {
                    flag.push(fl);
                }
            }
        }

        for (i = 0; i < total; i++) {
            if (flag[i] & 2) {
                x += (flag[i] & 16) ? v.getUint8(q) : -v.getUint8(q);
                q += 1;
            } else if (!(flag[i] & 16)) {
                x += v.getInt16(q);
                q += 2;
            }

            pts.push({ x: x, on: !!(flag[i] & 1) });
        }

        for (i = 0; i < total; i++) {
            if (flag[i] & 4) {
                y += (flag[i] & 32) ? v.getUint8(q) : -v.getUint8(q);
                q += 1;
            } else if (!(flag[i] & 32)) {
                y += v.getInt16(q);
                q += 2;
            }

            pts[i].y = y;
        }

        for (i = 0, q = 0; i < nc; i++) {
            out.push(pts.slice(q, ends[i] + 1));
            q = ends[i] + 1;
        }

        return out;
    }

    // Quadratic contours as cubic knots: two curve points in a row have an
    // on-curve point between them.
    function ttContours(f, g) {
        var pen = knotPen();

        ttPoints(f, g, 0).forEach(function (pts) {
            var n = pts.length, s = -1, i, at, p, q, e, mid = function (a, c) { return { x: (a.x + c.x) / 2, y: (a.y + c.y) / 2, on: true }; };

            if (n < 2) {
                return;
            }

            for (i = 0; (i < n) && (s < 0); i++) {
                s = pts[i].on ? i : -1;
            }

            var ring = (s < 0) ? [mid(pts[0], pts[1])].concat(pts.slice(1), pts.slice(0, 1)) : pts.slice(s).concat(pts.slice(0, s));

            ring.push(ring[0]);
            at = ring[0];
            pen.move(at.x, at.y);

            for (i = 1; i < ring.length; i++) {
                p = ring[i];

                if (p.on) {
                    pen.line(p.x, p.y);
                    at = p;
                    continue;
                }

                q = ring[i + 1] || ring[0];
                e = q.on ? q : mid(p, q);

                if (q.on) {
                    i++;
                }

                pen.curve(at.x + 2 / 3 * (p.x - at.x), at.y + 2 / 3 * (p.y - at.y), e.x + 2 / 3 * (p.x - e.x), e.y + 2 / 3 * (p.y - e.y), e.x, e.y);
                at = e;
            }
        });

        return pen.done();
    }

    // A CFF INDEX: its items as [start, end] offsets and where it ends.
    function cffIndex(v, at) {
        var n = v.getUint16(at), size, base, items = [], i, off = function (k) {
            for (var r = 0, j = 0; j < size; j++) {
                r = r * 256 + v.getUint8(at + 3 + k * size + j);
            }

            return r;
        };

        if (!n) {
            return { items: items, end: at + 2 };
        }

        size = v.getUint8(at + 2);
        base = at + 2 + (n + 1) * size;

        for (i = 0; i < n; i++) {
            items.push([base + off(i), base + off(i + 1)]);
        }

        return { items: items, end: base + off(n) };
    }

    // A CFF DICT as { operator: [operands] } (12 x as 1200 + x).
    function cffDict(v, at, end) {
        var out = {}, ops = [], b, n;

        while (at < end) {
            b = v.getUint8(at++);

            if (b <= 21) {
                out[(b === 12) ? 1200 + v.getUint8(at++) : b] = ops;
                ops = [];
            } else if (b === 28) {
                ops.push(v.getInt16(at));
                at += 2;
            } else if (b === 29) {
                ops.push(v.getInt32(at));
                at += 4;
            } else if (b === 30) {
                // A real number: read past its nibbles.
                do {
                    n = v.getUint8(at++);
                } while (((n & 15) !== 15) && ((n >> 4) !== 15));
                ops.push(0);
            } else if (b >= 32 && b <= 246) {
                ops.push(b - 139);
            } else if (b >= 247 && b <= 250) {
                ops.push((b - 247) * 256 + v.getUint8(at++) + 108);
            } else if (b >= 251 && b <= 254) {
                ops.push(-(b - 251) * 256 - v.getUint8(at++) - 108);
            }
        }

        return out;
    }

    function cffBias(n) {
        return (n < 1240) ? 107 : ((n < 33900) ? 1131 : 32768);
    }

    function fontCff(v, at) {
        var names = cffIndex(v, at + v.getUint8(at + 2)), tops = cffIndex(v, names.end), strings = cffIndex(v, tops.end);
        var top = tops.items.length ? cffDict(v, tops.items[0][0], tops.items[0][1]) : {}, priv = top[18] || [0, 0], pd, subrs;

        if (top[1230] || !top[17]) {
            throw new Error('font');
        }

        pd = cffDict(v, at + priv[1], at + priv[1] + priv[0]);
        subrs = pd[19] ? cffIndex(v, at + priv[1] + pd[19][0]).items : [];

        return { glyphs: cffIndex(v, at + top[17][0]).items, gsubrs: cffIndex(v, strings.end).items, subrs: subrs };
    }

    // A Type 2 charstring run into knots; hints are skipped.
    function cffContours(f, g) {
        var v = f.v, c = f.cff, pen = knotPen(), s = [], stems = 0, width = false, x = 0, y = 0, open = false, done = false;
        var shift = function (odd) {
            if (!width) {
                width = true;

                if (odd) {
                    s.shift();
                }
            }
        };
        var moveTo = function (dx, dy) {
            x += dx;
            y += dy;
            pen.move(x, y);
            open = true;
        };
        var lineTo = function (dx, dy) {
            x += dx;
            y += dy;
            pen.line(x, y);
        };
        var curveTo = function (a, b, cc, d, e, h) {
            var x1 = x + a, y1 = y + b, x2 = x1 + cc, y2 = y1 + d;

            x = x2 + e;
            y = y2 + h;
            pen.curve(x1, y1, x2, y2, x, y);
        };
        var run = function (range, depth) {
            var p = range[0], b, i, op, horiz, five, last, list, bias, dx, dy, y0, x0;

            if (depth > 10) {
                return;
            }

            while ((p < range[1]) && !done) {
                b = v.getUint8(p++);

                if ((b >= 32) || (b === 28)) {
                    if (b === 28) {
                        s.push(v.getInt16(p));
                        p += 2;
                    } else if (b <= 246) {
                        s.push(b - 139);
                    } else if (b <= 250) {
                        s.push((b - 247) * 256 + v.getUint8(p++) + 108);
                    } else if (b <= 254) {
                        s.push(-(b - 251) * 256 - v.getUint8(p++) - 108);
                    } else {
                        s.push(v.getInt32(p) / 65536);
                        p += 4;
                    }
                    continue;
                }

                op = (b === 12) ? 1200 + v.getUint8(p++) : b;

                switch (op) {
                case 1: case 3: case 18: case 23:
                    shift(s.length % 2);
                    stems += s.length >> 1;
                    break;
                case 19: case 20:
                    shift(s.length % 2);
                    stems += s.length >> 1;
                    p += (stems + 7) >> 3;
                    break;
                case 21:
                    shift(s.length > 2);
                    moveTo(s[s.length - 2], s[s.length - 1]);
                    break;
                case 22:
                    shift(s.length > 1);
                    moveTo(s[s.length - 1], 0);
                    break;
                case 4:
                    shift(s.length > 1);
                    moveTo(0, s[s.length - 1]);
                    break;
                case 5:
                    for (i = 0; i + 1 < s.length; i += 2) {
                        lineTo(s[i], s[i + 1]);
                    }
                    break;
                case 6: case 7:
                    for (i = 0, horiz = (op === 6); i < s.length; i++, horiz = !horiz) {
                        lineTo(horiz ? s[i] : 0, horiz ? 0 : s[i]);
                    }
                    break;
                case 8: case 24:
                    for (i = 0; i + 6 <= s.length - ((op === 24) ? 2 : 0); i += 6) {
                        curveTo(s[i], s[i + 1], s[i + 2], s[i + 3], s[i + 4], s[i + 5]);
                    }

                    if (op === 24) {
                        lineTo(s[i], s[i + 1]);
                    }
                    break;
                case 25:
                    for (i = 0; i + 2 <= s.length - 6; i += 2) {
                        lineTo(s[i], s[i + 1]);
                    }
                    curveTo(s[i], s[i + 1], s[i + 2], s[i + 3], s[i + 4], s[i + 5]);
                    break;
                case 26: case 27:
                    i = 0;
                    dy = 0;

                    if (s.length % 2) {
                        dy = s[0];
                        i = 1;
                    }

                    for (; i + 3 < s.length; i += 4, dy = 0) {
                        if (op === 27) {
                            curveTo(s[i], dy, s[i + 1], s[i + 2], s[i + 3], 0);
                        } else {
                            curveTo(dy, s[i], s[i + 1], s[i + 2], 0, s[i + 3]);
                        }
                    }
                    break;
                case 30: case 31:
                    for (i = 0, horiz = (op === 31); i + 3 < s.length; horiz = !horiz) {
                        five = (s.length - i === 5);
                        last = five ? s[i + 4] : 0;

                        if (horiz) {
                            curveTo(s[i], 0, s[i + 1], s[i + 2], last, s[i + 3]);
                        } else {
                            curveTo(0, s[i], s[i + 1], s[i + 2], s[i + 3], last);
                        }

                        i += five ? 5 : 4;
                    }
                    break;
                case 10: case 29:
                    list = (op === 10) ? c.subrs : c.gsubrs;
                    bias = s.pop() + cffBias(list.length);

                    if (list[bias]) {
                        run(list[bias], depth + 1);
                    }
                    continue;
                case 11:
                    return;
                case 14:
                    shift(s.length % 2);
                    done = true;
                    break;
                case 1235:
                    curveTo(s[0], s[1], s[2], s[3], s[4], s[5]);
                    curveTo(s[6], s[7], s[8], s[9], s[10], s[11]);
                    break;
                case 1234:
                    y0 = y;
                    curveTo(s[0], 0, s[1], s[2], s[3], 0);
                    curveTo(s[4], 0, s[5], y0 - y, s[6], 0);
                    break;
                case 1236:
                    y0 = y;
                    curveTo(s[0], s[1], s[2], s[3], s[4], 0);
                    curveTo(s[5], 0, s[6], s[7], s[8], y0 - y - s[7]);
                    break;
                case 1237:
                    x0 = x;
                    y0 = y;
                    dx = s[0] + s[2] + s[4] + s[6] + s[8];
                    dy = s[1] + s[3] + s[5] + s[7] + s[9];
                    curveTo(s[0], s[1], s[2], s[3], s[4], s[5]);

                    if (Math.abs(dx) > Math.abs(dy)) {
                        curveTo(s[6], s[7], s[8], s[9], s[10], y0 - (y + s[7] + s[9]));
                    } else {
                        curveTo(s[6], s[7], s[8], s[9], x0 - (x + s[6] + s[8]), s[10]);
                    }
                    break;
                }

                s = [];
            }
        };

        if (c.glyphs[g]) {
            run(c.glyphs[g], 0);
        }

        return open ? pen.done() : [];
    }

    // A glyph's contours as knots in font units (y up).
    function fontContours(f, g) {
        return f.cff ? cffContours(f, g) : ttContours(f, g);
    }

    // Letters drawn by shaping their neighbours (Arabic, Syriac, Thaana,
    // N'Ko, the Indic scripts, Tibetan, Myanmar, Khmer, Mongolian): their
    // outlines need a shaper this reader does not have.
    function outlineNeedsShaping(text) {
        return /[؀-߿ऀ-෿ༀ-࿿က-႟ក-᢯ꡀ-꡿ﭐ-﷿ﹰ-﻿]/.test(String(text || ''));
    }

    // A text layer's letters as contours of knots in its box's frame (px,
    // 0, 0 its corner, y down) from font f, on the text tool's layout lay
    // (textLayout()): each line's baseline, its spaced characters' x as
    // laid out, else the font's advances and pair kerning from the aligned
    // side. missing lists the characters the font does not have.
    function outlineText(f, l, lay) {
        var k = lay.size / f.upm, contours = [], missing = [], inner = lay.w - 2 * lay.pad;

        lay.lines.forEach(function (line) {
            var chars = Array.from(line.text || ''), gids = chars.map(function (ch) { return fontGlyph(f, ch.codePointAt(0)); });
            var xs = [], at = 0, free;

            gids.forEach(function (g, i) {
                xs.push(at);
                at += fontAdvance(f, g) * k + ((i + 1 < gids.length) ? fontKerning(f, g, gids[i + 1]) * k : 0);
            });
            free = inner - at;

            gids.forEach(function (g, i) {
                var x0 = line.chars ? line.x + line.chars[i][1] : lay.pad + ((l.align === 'center') ? free / 2 : ((l.align === 'right') ? free : 0)) + xs[i];

                if (!g && /\S/.test(chars[i])) {
                    missing.push(chars[i]);
                    return;
                }

                fontContours(f, g).forEach(function (ks) {
                    contours.push(moveKnots(ks, function (px, py) { return [x0 + px * k, line.base - py * k]; }));
                });
            });
        });

        return { contours: contours, missing: missing };
    }

    // The fields of the path layer that takes a text's place: its box
    // fitted to the letters (the text's angle kept), the first contour as
    // its knots and the rest as subs; closed and filled in the text's
    // colour; the outline as a stroke under the fill (ink), the shadow as
    // the text's in px.
    function outlineLayer(l, contours) {
        var fx = textEffects(l), stub = { x: l.x, y: l.y, w: l.w, h: l.h, rot: l.rot || 0, closed: true };
        var fit = refitPath(stub, contours[0], contours.slice(1).map(function (ks) { return { knots: ks, closed: true }; }));
        var out = {
            type: 'path', x: fit.x, y: fit.y, w: fit.w, h: fit.h, rot: l.rot || 0, opacity: (l.opacity === undefined) ? 1 : l.opacity,
            knots: fit.knots, subs: fit.subs || [], closed: true, fill: 'solid', color: l.color || '#000000', stroke: 0, dash: 'solid'
        };

        if (fx.outline) {
            out.stroke = fx.outline.width * 2;
            out.ink = fx.outline.color;
        }

        if (fx.shadow && !l.bg) {
            out.shadow = fx.shadow;
        }

        return out;
    }

    var OUTLINE = {
        fontRead: fontRead, fontGlyph: fontGlyph, fontAdvance: fontAdvance, fontKerning: fontKerning, fontContours: fontContours,
        outlineNeedsShaping: outlineNeedsShaping, outlineText: outlineText, outlineLayer: outlineLayer
    };

    // -- shape builder (pg_image_editor_builder.js) --

    // Closed shapes and paths combined as areas: their outlines are
    // flattened to polygons (FLAT_TOL px from the curve), the polygons
    // clipped with the Martinez-Rueda sweep, and the rings that come out
    // fitted to curves again (FIT_TOL px). A result's holes wind against
    // its outer rings, so the nonzero and even-odd rules fill it alike.

    var FLAT_TOL = 0.25, FIT_TOL = 0.5, GRID = 1024;

    // The polygon of one cubic segment from a to b (its end point onward),
    // split until each piece lies within tol of its chord.
    function flattenSegment(a, b, tol, out) {
        var walk = function (p0, p1, p2, p3, depth) {
            var dx = p3[0] - p0[0], dy = p3[1] - p0[1], len = Math.hypot(dx, dy) || 1e-9;
            var d1 = Math.abs((p1[0] - p3[0]) * dy - (p1[1] - p3[1]) * dx) / len, d2 = Math.abs((p2[0] - p3[0]) * dy - (p2[1] - p3[1]) * dx) / len;

            if ((depth > 16) || (d1 + d2 <= tol)) {
                out.push(p3);
                return;
            }

            var m = function (u, v) { return [(u[0] + v[0]) / 2, (u[1] + v[1]) / 2]; };
            var q0 = m(p0, p1), q1 = m(p1, p2), q2 = m(p2, p3), r0 = m(q0, q1), r1 = m(q1, q2), s = m(r0, r1);

            walk(p0, q0, r0, s, depth + 1);
            walk(s, r1, q2, p3, depth + 1);
        };

        walk([a.x, a.y], [a.ox, a.oy], [b.ix, b.iy], [b.x, b.y], 0);
    }

    // A closed contour's polygon (knots in any frame), on the GRID, its
    // repeated points dropped.
    function flattenRing(knots, tol) {
        var pts = knots.length ? [[knots[0].x, knots[0].y]] : [], out = [];

        segments(knots, true).forEach(function (s) { flattenSegment(s[0], s[1], tol || FLAT_TOL, pts); });
        pts.forEach(function (p) {
            var q = [Math.round(p[0] * GRID) / GRID, Math.round(p[1] * GRID) / GRID], last = out[out.length - 1];

            if (!last || (last[0] !== q[0]) || (last[1] !== q[1])) {
                out.push(q);
            }
        });

        if ((out.length > 1) && (out[0][0] === out[out.length - 1][0]) && (out[0][1] === out[out.length - 1][1])) {
            out.pop();
        }

        return out;
    }

    // A closed shape's or path's contours as polygons in the rotated space,
    // or null for a layer that is not a closed area.
    function layerRings(l) {
        if (!l || !isClosed(l) || (l.type === 'pen')) {
            return null;
        }

        // A warped or pinned layer: the contours it draws, not its knots.
        if (warpOn(l) || puppetOn(l)) {
            var bent = warpContours(l).filter(function (c) { return c.closed && (c.pts.length > 2); }).map(function (c) {
                return c.pts.map(function (q) { return drawnPoint(l, q[0], q[1]); });
            });

            return bent.length ? bent : null;
        }

        var kc = (l.type === 'path') ? [{ knots: pathKnots(l), closed: true }].concat(subKnots(l)) : [shapeKnots(l)];
        var rings = kc.filter(function (c) { return c.closed && (c.knots.length > 2); }).map(function (c) {
            return flattenRing(moveKnots(c.knots, function (u, v) { return drawnPoint(l, u, v); }), FLAT_TOL);
        }).filter(function (r) { return r.length > 2; });

        return rings.length ? rings : null;
    }

    function ringArea(r) {
        var a = 0, i, j;

        for (i = 0, j = r.length - 1; i < r.length; j = i++) {
            a += (r[j][0] - r[i][0]) * (r[j][1] + r[i][1]);
        }

        return a / 2;
    }

    // The area of rings wound as clipRings() gives them.
    function ringsArea(rings) {
        return rings.reduce(function (s, r) { return s + ringArea(r); }, 0);
    }

    // ---- the Martinez-Rueda sweep ----

    var EV_NORMAL = 0, EV_NONE = 1, EV_SAME = 2, EV_DIFF = 3, evId = 0;

    function sweepEvent(p, left, other, subject) {
        return { p: p, left: left, other: other, subject: subject, type: EV_NORMAL, inOut: false, otherInOut: false, inResult: false, id: ++evId, ring: 0 };
    }

    function samePoint(a, b) {
        return (a[0] === b[0]) && (a[1] === b[1]);
    }

    function signedArea(p0, p1, p2) {
        return (p0[0] - p2[0]) * (p1[1] - p2[1]) - (p1[0] - p2[0]) * (p0[1] - p2[1]);
    }

    function evBelow(e, p) {
        return e.left ? (signedArea(e.p, e.other.p, p) > 0) : (signedArea(e.other.p, e.p, p) > 0);
    }

    function evVertical(e) {
        return e.p[0] === e.other.p[0];
    }

    // The queue's order: left to right, bottom to top, a right end before a
    // left one, the lower segment first.
    function compareEvents(a, b) {
        var p = a.p, q = b.p;

        if (p[0] !== q[0]) {
            return (p[0] > q[0]) ? 1 : -1;
        }

        if (p[1] !== q[1]) {
            return (p[1] > q[1]) ? 1 : -1;
        }

        if (a.left !== b.left) {
            return a.left ? 1 : -1;
        }

        if (signedArea(p, a.other.p, b.other.p) !== 0) {
            return evBelow(a, b.other.p) ? -1 : 1;
        }

        if (a.subject !== b.subject) {
            return (!a.subject && b.subject) ? 1 : -1;
        }

        return (a.id > b.id) ? 1 : -1;
    }

    // The sweep line's order of two left events: the lower segment first.
    function compareSegments(a, b) {
        if (a === b) {
            return 0;
        }

        if ((signedArea(a.p, a.other.p, b.p) !== 0) || (signedArea(a.p, a.other.p, b.other.p) !== 0)) {
            if (samePoint(a.p, b.p)) {
                return evBelow(a, b.other.p) ? -1 : 1;
            }

            if (a.p[0] === b.p[0]) {
                return (a.p[1] < b.p[1]) ? -1 : 1;
            }

            if (compareEvents(a, b) === 1) {
                return evBelow(b, a.p) ? 1 : -1;
            }

            return evBelow(a, b.p) ? -1 : 1;
        }

        if (a.subject !== b.subject) {
            return a.subject ? -1 : 1;
        }

        if (samePoint(a.p, b.p) && samePoint(a.other.p, b.other.p)) {
            return (a.id > b.id) ? 1 : -1;
        }

        return (compareEvents(a, b) === 1) ? 1 : -1;
    }

    // A binary heap of events, the first by compareEvents() on top.
    function eventQueue() {
        var h = [];

        return {
            size: function () { return h.length; },
            push: function (e) {
                var i = h.push(e) - 1, up;

                while (i > 0 && compareEvents(h[i], h[up = (i - 1) >> 1]) < 0) {
                    h[i] = h[up];
                    h[up] = e;
                    i = up;
                }
            },
            pop: function () {
                var top = h[0], last = h.pop(), i = 0, n = h.length, l, r, m;

                if (n) {
                    h[0] = last;

                    for (;;) {
                        l = 2 * i + 1;
                        r = l + 1;
                        m = i;

                        if ((l < n) && (compareEvents(h[l], h[m]) < 0)) { m = l; }
                        if ((r < n) && (compareEvents(h[r], h[m]) < 0)) { m = r; }
                        if (m === i) { break; }

                        h[i] = h[m];
                        h[m] = last;
                        i = m;
                    }
                }

                return top;
            }
        };
    }

    // Points closer than WELD px are one point: the same corner reached by
    // two cuts in other runs differs in its last digits.
    var WELD = 1e-5;

    // b's points moved onto a's within WELD.
    function weldRings(a, b) {
        var cells = {}, cell = function (v) { return Math.floor(v / WELD); };

        a.forEach(function (r) {
            r.forEach(function (p) { cells[cell(p[0]) + ',' + cell(p[1])] = p; });
        });

        return b.map(function (r) {
            return r.map(function (p) {
                for (var dx = -1; dx <= 1; dx++) {
                    for (var dy = -1; dy <= 1; dy++) {
                        var q = cells[(cell(p[0]) + dx) + ',' + (cell(p[1]) + dy)];

                        if (q && (Math.abs(q[0] - p[0]) <= WELD) && (Math.abs(q[1] - p[1]) <= WELD)) {
                            return q;
                        }
                    }
                }

                return p;
            });
        });
    }

    // A crossing on a fine binary grid, so the same crossing found again
    // from other pieces of the same edges is the same point.
    function onGrid(p) {
        return [Math.round(p[0] * 1048576) / 1048576, Math.round(p[1] * 1048576) / 1048576];
    }

    // Where segments a1-a2 and b1-b2 meet: no point, one, or the two ends
    // of their overlap. A point near an end is that end.
    function segmentCross(a1, a2, b1, b2) {
        var va = [a2[0] - a1[0], a2[1] - a1[1]], vb = [b2[0] - b1[0], b2[1] - b1[1]], e = [b1[0] - a1[0], b1[1] - a1[1]];
        var kross = va[0] * vb[1] - va[1] * vb[0], la = va[0] * va[0] + va[1] * va[1], lb = vb[0] * vb[0] + vb[1] * vb[1], s, t, at;
        var snap = function (p) {
            var ends = [a1, a2, b1, b2], i;

            for (i = 0; i < 4; i++) {
                if (Math.abs(p[0] - ends[i][0]) < WELD && Math.abs(p[1] - ends[i][1]) < WELD) {
                    return ends[i];
                }
            }

            return p;
        };

        if (kross * kross > 1e-10 * la * lb) {
            s = (e[0] * vb[1] - e[1] * vb[0]) / kross;
            t = (e[0] * va[1] - e[1] * va[0]) / kross;

            if ((s < 0) || (s > 1) || (t < 0) || (t > 1)) {
                return [];
            }

            return [snap((s === 0) ? a1 : ((s === 1) ? a2 : ((t === 0) ? b1 : ((t === 1) ? b2 : onGrid([a1[0] + s * va[0], a1[1] + s * va[1]])))))];
        }

        kross = e[0] * va[1] - e[1] * va[0];

        if (kross * kross > 1e-10 * la * (e[0] * e[0] + e[1] * e[1])) {
            return [];
        }

        s = (va[0] * e[0] + va[1] * e[1]) / la;
        t = s + (va[0] * vb[0] + va[1] * vb[1]) / la;
        at = function (v) { return snap((v <= 0) ? a1 : ((v >= 1) ? a2 : onGrid([a1[0] + v * va[0], a1[1] + v * va[1]]))); };

        if ((Math.min(s, t) > 1) || (Math.max(s, t) < 0)) {
            return [];
        }

        if ((Math.min(s, t) === 1) || (Math.max(s, t) === 0)) {
            return [at(Math.min(s, t))];
        }

        return [at(Math.min(s, t)), at(Math.max(s, t))];
    }

    // Splits the segment of left event e at p: two segments, four events.
    function divideSegment(e, p, queue) {
        var r = sweepEvent(p, false, e, e.subject), l = sweepEvent(p, true, e.other, e.subject);

        r.ring = l.ring = e.ring;

        if (compareEvents(l, e.other) > 0) {
            e.other.left = true;
            l.left = false;
        }

        e.other.other = l;
        e.other = r;
        queue.push(l);
        queue.push(r);
    }

    // Splits two neighbours on the sweep line where they cross; 2 when
    // they share their left part (their types are set).
    function crossEvents(a, b, queue) {
        var x = segmentCross(a.p, a.other.p, b.p, b.other.p), ev = [], leftSame = false, rightSame = false;

        if (!x.length || ((x.length === 1) && (samePoint(a.p, b.p) || samePoint(a.other.p, b.other.p)))) {
            return 0;
        }

        if ((x.length === 2) && (a.subject === b.subject)) {
            return 0;
        }

        if (x.length === 1) {
            if (!samePoint(a.p, x[0]) && !samePoint(a.other.p, x[0])) {
                divideSegment(a, x[0], queue);
            }

            if (!samePoint(b.p, x[0]) && !samePoint(b.other.p, x[0])) {
                divideSegment(b, x[0], queue);
            }

            return 1;
        }

        if (samePoint(a.p, b.p)) {
            leftSame = true;
        } else {
            ev.push.apply(ev, (compareEvents(a, b) === 1) ? [b, a] : [a, b]);
        }

        if (samePoint(a.other.p, b.other.p)) {
            rightSame = true;
        } else {
            ev.push.apply(ev, (compareEvents(a.other, b.other) === 1) ? [b.other, a.other] : [a.other, b.other]);
        }

        if (leftSame) {
            b.type = EV_NONE;
            a.type = (b.inOut === a.inOut) ? EV_SAME : EV_DIFF;

            if (!rightSame) {
                divideSegment(ev[1].other, ev[0].p, queue);
            }

            return 2;
        }

        if (rightSame) {
            divideSegment(ev[0], ev[1].p, queue);
            return 3;
        }

        if (ev[0] !== ev[3].other) {
            divideSegment(ev[0], ev[1].p, queue);
            divideSegment(ev[1], ev[2].p, queue);
            return 3;
        }

        divideSegment(ev[0], ev[1].p, queue);
        divideSegment(ev[3].other, ev[2].p, queue);

        return 3;
    }

    function evInResult(e, op) {
        if (e.type === EV_NONE) {
            return false;
        }

        if (e.type === EV_SAME) {
            return (op === 'intersect') || (op === 'union');
        }

        if (e.type === EV_DIFF) {
            return op === 'difference';
        }

        if (op === 'intersect') {
            return !e.otherInOut;
        }

        if (op === 'union') {
            return e.otherInOut;
        }

        return (op === 'difference') ? (e.subject ? e.otherInOut : !e.otherInOut) : true;
    }

    // Whether the sweep enters or leaves each polygon along e, from the
    // segment below it.
    function evFields(e, prev, op) {
        if (!prev) {
            e.inOut = false;
            e.otherInOut = true;
        } else if (e.subject === prev.subject) {
            e.inOut = !prev.inOut;
            e.otherInOut = prev.otherInOut;
        } else {
            e.inOut = !prev.otherInOut;
            e.otherInOut = evVertical(prev) ? !prev.inOut : prev.inOut;
        }

        e.inResult = evInResult(e, op);
    }

    function ringsBox(rings) {
        var b = [Infinity, Infinity, -Infinity, -Infinity];

        rings.forEach(function (r) {
            r.forEach(function (p) {
                b = [Math.min(b[0], p[0]), Math.min(b[1], p[1]), Math.max(b[2], p[0]), Math.max(b[3], p[1])];
            });
        });

        return b;
    }

    // The rings (polygons in one frame, even-odd) of a and b combined: op is
    // union, intersect, difference (a without b) or xor. The result's rings
    // are simple, outer ones of positive ringArea(), holes negative.
    function clipRings(a, b, op) {
        var ba = ringsBox(a), bb = ringsBox(b), queue = eventQueue(), line = [], done = [], e, i, prev, next;

        b = weldRings(a, b);

        if (!a.length || !b.length || (ba[0] > bb[2]) || (bb[0] > ba[2]) || (ba[1] > bb[3]) || (bb[1] > ba[3])) {
            return nestRings((op === 'intersect') ? [] : ((op === 'difference') ? a : a.concat(b)));
        }

        [a, b].forEach(function (rings, which) {
            rings.forEach(function (r, ri) {
                r.forEach(function (p, pi) {
                    var q = r[(pi + 1) % r.length], e1, e2;

                    if (samePoint(p, q)) {
                        return;
                    }

                    e1 = sweepEvent(p, false, null, !which);
                    e2 = sweepEvent(q, false, e1, !which);
                    e1.other = e2;
                    e1.ring = e2.ring = which * 1e6 + ri + 1;
                    (compareEvents(e1, e2) > 0 ? e2 : e1).left = true;
                    queue.push(e1);
                    queue.push(e2);
                });
            });
        });

        var find = function (ev) {
            var lo = 0, hi = line.length;

            while (lo < hi) {
                var mid = (lo + hi) >> 1;

                if (compareSegments(line[mid], ev) < 0) {
                    lo = mid + 1;
                } else {
                    hi = mid;
                }
            }

            return lo;
        };

        while (queue.size()) {
            e = queue.pop();
            done.push(e);

            if (e.left) {
                i = find(e);
                line.splice(i, 0, e);
                prev = line[i - 1] || null;
                next = line[i + 1] || null;
                evFields(e, prev, op);

                if (next && (crossEvents(e, next, queue) === 2)) {
                    evFields(e, prev, op);
                    evFields(next, e, op);
                }

                if (prev && (crossEvents(prev, e, queue) === 2)) {
                    evFields(prev, line[line.indexOf(prev) - 1] || null, op);
                    evFields(e, prev, op);
                }
            } else {
                e = e.other;
                i = line.indexOf(e);

                if (i >= 0) {
                    prev = line[i - 1];
                    next = line[i + 1];
                    line.splice(i, 1);

                    if (prev && next) {
                        crossEvents(prev, next, queue);
                    }
                }
            }
        }

        return nestRings(joinEdges(done.filter(function (ev) { return ev.left && ev.inResult; })));
    }

    // The result's edges (left events) joined into rings, split where a
    // ring meets itself.
    function joinEdges(edges) {
        var at = {}, used = [], rings = [], key = function (p) { return p[0] + ',' + p[1]; };

        edges.forEach(function (ev, n) {
            [ev.p, ev.other.p].forEach(function (p) { (at[key(p)] = at[key(p)] || []).push(n); });
        });

        edges.forEach(function (ev, n) {
            if (used[n]) {
                return;
            }

            var ring = [ev.p], from = ev.p, to = ev.other.p, cur = n, guard = edges.length + 1;

            used[n] = true;

            while (!samePoint(to, ring[0]) && guard--) {
                var din = Math.atan2(to[1] - from[1], to[0] - from[0]), best = -1, bestTurn = Infinity;

                at[key(to)].forEach(function (m) {
                    if (used[m] || (m === cur)) {
                        return;
                    }

                    var o = samePoint(edges[m].p, to) ? edges[m].other.p : edges[m].p, turn = Math.atan2(o[1] - to[1], o[0] - to[0]) - din;

                    while (turn <= -Math.PI) { turn += 2 * Math.PI; }
                    while (turn > Math.PI) { turn -= 2 * Math.PI; }

                    if (turn < bestTurn) {
                        bestTurn = turn;
                        best = m;
                    }
                });

                if (best < 0) {
                    break;
                }

                used[best] = true;
                ring.push(to);
                from = to;
                to = samePoint(edges[best].p, to) ? edges[best].other.p : edges[best].p;
                cur = best;
            }

            splitRing(ring).forEach(function (r) { rings.push(r); });
        });

        return rings;
    }

    // A ring that passes a point twice (rings touching there) as simple
    // rings, one per loop.
    function splitRing(ring) {
        var out = [], seen = {}, stack = [], i;

        for (i = 0; i < ring.length; i++) {
            var key = ring[i][0] + ',' + ring[i][1];

            if (seen.hasOwnProperty(key)) {
                var loop = stack.splice(seen[key]);

                loop.forEach(function (p) { delete seen[p[0] + ',' + p[1]]; });

                if (loop.length > 2) {
                    out.push(loop);
                }
            }

            seen[key] = stack.length;
            stack.push(ring[i]);
        }

        if (stack.length > 2) {
            out.push(stack);
        }

        return out;
    }

    // Rings wound by depth: one inside an even number of others winds
    // positive (an outer ring), the rest negative (holes). Slivers under
    // half a pixel go.
    function nestRings(rings) {
        rings = rings.filter(function (r) { return (r.length > 2) && (Math.abs(ringArea(r)) >= 0.5); });

        return rings.map(function (r) {
            var p = [(r[0][0] + r[1][0]) / 2, (r[0][1] + r[1][1]) / 2], depth = 0, a = ringArea(r);

            rings.forEach(function (o) {
                if ((o !== r) && inside(p, o)) {
                    depth++;
                }
            });

            return ((a > 0) === !(depth % 2)) ? r : r.slice().reverse();
        });
    }

    // Rings split into islands: each outer ring with the holes straight
    // inside it.
    function ringIslands(rings) {
        var outers = rings.filter(function (r) { return ringArea(r) > 0; }), out = outers.map(function (r) { return [r]; });

        rings.forEach(function (h) {
            if (ringArea(h) > 0) {
                return;
            }

            var best = -1, bestArea = Infinity, p = [(h[0][0] + h[1][0]) / 2, (h[0][1] + h[1][1]) / 2];

            outers.forEach(function (o, i) {
                if (inside(p, o) && (ringArea(o) < bestArea)) {
                    best = i;
                    bestArea = ringArea(o);
                }
            });

            if (best >= 0) {
                out[best].push(h);
            }
        });

        return out;
    }

    // Whether p lies in the area of rings (even-odd).
    function insideRings(p, rings) {
        return rings.reduce(function (n, r) { return n + (inside(p, r) ? 1 : 0); }, 0) % 2 === 1;
    }

    // ---- the operations ----

    // items [{ id, rings }] bottom to top combined by op: unite, minusFront
    // (the bottom one without the rest), intersect, exclude, divide. Gives
    // [{ id, rings }]: the layer whose style each result takes (the top
    // one; the bottom one for minusFront; for divide each piece's top
    // cover) and its rings; [] when nothing is left.
    function pathfinder(op, items) {
        var top = items[items.length - 1], fold = function (kind, list) {
            return list.slice(1).reduce(function (acc, it) { return clipRings(acc, it.rings, kind); }, nestRings(list[0].rings));
        };
        var rings;

        if (items.length < 2) {
            return [];
        }

        if (op === 'divide') {
            return builderRegions(items).map(function (r) { return { id: r.id, rings: r.rings }; });
        }

        if (op === 'minusFront') {
            rings = clipRings(nestRings(items[0].rings), fold('union', items.slice(1)), 'difference');
            return rings.length ? [{ id: items[0].id, rings: rings }] : [];
        }

        rings = fold({ unite: 'union', intersect: 'intersect', exclude: 'xor' }[op] || 'union', items);

        return rings.length ? [{ id: top.id, rings: rings }] : [];
    }

    // The pieces the items' outlines cut each other into: [{ rings,
    // members (ids covering it, bottom to top), id (the top member) }], one
    // per island. Each piece is the overlap of its members less the other
    // items, cut from the items' own outlines (pieces cut from pieces share
    // edges, which the sweep takes less exactly).
    function builderRegions(items) {
        var out = [], own = items.map(function (it) { return nestRings(it.rings); });
        var visit = function (last, members, area) {
            var rest = area;

            items.forEach(function (it, j) {
                if (rest.length && (members.indexOf(j) < 0)) {
                    rest = clipRings(rest, own[j], 'difference');
                }
            });

            ringIslands(rest).forEach(function (rings) {
                var ids = members.map(function (j) { return items[j].id; });

                out.push({ rings: rings, members: ids, id: ids[ids.length - 1] });
            });

            for (var j = last + 1; j < items.length; j++) {
                var both = clipRings(area, own[j], 'intersect');

                if (both.length) {
                    visit(j, members.concat([j]), both);
                }
            }
        };

        own.forEach(function (rings, i) { visit(i, [i], rings); });

        return out;
    }

    // The index of the region under p, or -1.
    function regionAt(regions, p) {
        for (var i = 0; i < regions.length; i++) {
            if (insideRings(p, regions[i].rings)) {
                return i;
            }
        }

        return -1;
    }

    // A shape builder drag over regions: the regions touched (indexes)
    // merged into one piece in the style of the first, or dropped with
    // remove. Gives the ids of the layers it changes (every member of a
    // touched region) and their pieces [{ id, rings }]: the merged one, and
    // the untouched regions that one of them covers, each in the style of
    // its top member among them.
    function builderMerge(regions, touched, remove) {
        var hit = {}, ids = [], pieces = [], merged = [];

        if (!touched.length) {
            return { ids: [], pieces: [] };
        }

        touched.forEach(function (i) {
            hit[i] = true;
            regions[i].members.forEach(function (id) {
                if (ids.indexOf(id) < 0) {
                    ids.push(id);
                }
            });
        });

        if (!remove) {
            merged = touched.reduce(function (acc, i) { return acc.length ? clipRings(acc, regions[i].rings, 'union') : nestRings(regions[i].rings); }, []);
        }

        regions.forEach(function (r, i) {
            var own = r.members.filter(function (id) { return ids.indexOf(id) >= 0; });

            if (!hit[i] && own.length) {
                pieces.push({ id: own[own.length - 1], rings: r.rings });
            }
        });

        if (merged.length) {
            pieces.unshift({ id: regions[touched[0]].id, rings: merged });
        }

        return { ids: ids, pieces: pieces };
    }

    // A shape builder click, merging (one region touched): the shapes
    // stacked over that region (items [{ id, rings }] bottom to top) united
    // whole in the style of the top one, as builderMerge() gives it; nothing
    // for a region of one shape, which a click leaves as it is.
    function builderStack(items, region) {
        var stack = region ? items.filter(function (it) { return region.members.indexOf(it.id) >= 0; }) : [];

        if (stack.length < 2) {
            return { ids: [], pieces: [] };
        }

        return { ids: stack.map(function (it) { return it.id; }), pieces: pathfinder('unite', stack) };
    }

    // ---- back to curves ----

    // A closed polygon's knots: straight runs between corners (turns over
    // CORNER radians) stay lines, curved runs are fitted with cubic
    // segments (within FIT_TOL); only the corners are sharp knots.
    var CORNER = 0.6;

    function ringKnots(ring) {
        var n = ring.length, starts = [], beziers = [], sharp = {}, dir = function (a, b) { return Math.atan2(b[1] - a[1], b[0] - a[0]); }, i, k;

        for (i = 0; i < n; i++) {
            var turn = dir(ring[i], ring[(i + 1) % n]) - dir(ring[(i + n - 1) % n], ring[i]);

            while (turn <= -Math.PI) { turn += 2 * Math.PI; }
            while (turn > Math.PI) { turn -= 2 * Math.PI; }

            if (Math.abs(turn) > CORNER) {
                starts.push(i);
            }
        }

        var round = !starts.length;

        for (k = 0; k < (round ? 1 : starts.length); k++) {
            var from = round ? 0 : starts[k], to = round ? 0 : starts[(k + 1) % starts.length], run = [ring[from]];

            for (i = (from + 1) % n; ; i = (i + 1) % n) {
                run.push(ring[i]);

                if (i === to) {
                    break;
                }
            }

            sharp[beziers.length] = !round;

            if (straightRun(run)) {
                beziers.push([run[0], run[0], run[run.length - 1], run[run.length - 1]]);
            } else if (round) {
                fitCubic(run, 0, run.length - 1, unit(ring[1], ring[n - 1]), unit(ring[n - 1], ring[1]), beziers);
            } else {
                fitCubic(run, 0, run.length - 1, unit(run[1], run[0]), unit(run[run.length - 2], run[run.length - 1]), beziers);
            }
        }

        return beziers.map(function (bz, j) {
            var before = beziers[(j + beziers.length - 1) % beziers.length];

            return { x: bz[0][0], y: bz[0][1], ix: before[2][0], iy: before[2][1], ox: bz[1][0], oy: bz[1][1], s: !sharp[j] };
        });
    }

    function straightRun(run) {
        var a = run[0], b = run[run.length - 1];

        return run.every(function (p) { return segmentDistance(p, a, b) <= FLAT_TOL; });
    }

    function unit(a, b) {
        var dx = a[0] - b[0], dy = a[1] - b[1], d = Math.hypot(dx, dy) || 1;

        return [dx / d, dy / d];
    }

    // Schneider's least-squares fit of cubic segments to pts[first..last]
    // with the end tangents t1 (out of the first point) and t2 (into the
    // last, pointing back); pushes [p0, c1, c2, p3] onto out.
    function fitCubic(pts, first, last, t1, t2, out) {
        var count = last - first + 1, u, bz, err, i, it;

        if (count === 2) {
            var d = Math.hypot(pts[last][0] - pts[first][0], pts[last][1] - pts[first][1]) / 3;

            out.push([pts[first], [pts[first][0] + t1[0] * d, pts[first][1] + t1[1] * d], [pts[last][0] + t2[0] * d, pts[last][1] + t2[1] * d], pts[last]]);
            return;
        }

        u = [0];

        for (i = first + 1; i <= last; i++) {
            u.push(u[u.length - 1] + Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]));
        }

        u = u.map(function (v) { return v / (u[u.length - 1] || 1); });

        for (it = 0; it < 6; it++) {
            bz = fitBezier(pts, first, last, u, t1, t2);
            err = fitError(pts, first, last, bz, u);

            if (err.max <= FIT_TOL) {
                out.push(bz);
                return;
            }

            if (err.max > FIT_TOL * 16) {
                break;
            }

            u = u.map(function (v, j) { return newtonRoot(bz, pts[first + j], v); });
        }

        var split = Math.min(Math.max(err.at, first + 1), last - 1), tc = unit(pts[split - 1], pts[split + 1]);

        fitCubic(pts, first, split, t1, tc, out);
        fitCubic(pts, split, last, [-tc[0], -tc[1]], t2, out);
    }

    function bezierAt(bz, t) {
        var m = 1 - t;

        return [m * m * m * bz[0][0] + 3 * m * m * t * bz[1][0] + 3 * m * t * t * bz[2][0] + t * t * t * bz[3][0],
            m * m * m * bz[0][1] + 3 * m * m * t * bz[1][1] + 3 * m * t * t * bz[2][1] + t * t * t * bz[3][1]];
    }

    function fitBezier(pts, first, last, u, t1, t2) {
        var p0 = pts[first], p3 = pts[last], c = [[0, 0], [0, 0]], x = [0, 0], i;

        for (i = 0; i < u.length; i++) {
            var t = u[i], m = 1 - t, b1 = 3 * t * m * m, b2 = 3 * t * t * m, a1 = [t1[0] * b1, t1[1] * b1], a2 = [t2[0] * b2, t2[1] * b2];
            var b0 = m * m * m + b1, b3 = b2 + t * t * t, q = pts[first + i];
            var tmp = [q[0] - (p0[0] * b0 + p3[0] * b3), q[1] - (p0[1] * b0 + p3[1] * b3)];

            c[0][0] += a1[0] * a1[0] + a1[1] * a1[1];
            c[0][1] += a1[0] * a2[0] + a1[1] * a2[1];
            c[1][1] += a2[0] * a2[0] + a2[1] * a2[1];
            x[0] += a1[0] * tmp[0] + a1[1] * tmp[1];
            x[1] += a2[0] * tmp[0] + a2[1] * tmp[1];
        }

        c[1][0] = c[0][1];

        var det = c[0][0] * c[1][1] - c[1][0] * c[0][1], seg = Math.hypot(p3[0] - p0[0], p3[1] - p0[1]);
        var al = det ? (x[0] * c[1][1] - x[1] * c[0][1]) / det : 0, ar = det ? (c[0][0] * x[1] - c[1][0] * x[0]) / det : 0;

        if ((al < seg * 1e-6) || (ar < seg * 1e-6)) {
            al = ar = seg / 3;
        }

        return [p0, [p0[0] + t1[0] * al, p0[1] + t1[1] * al], [p3[0] + t2[0] * ar, p3[1] + t2[1] * ar], p3];
    }

    function fitError(pts, first, last, bz, u) {
        var max = 0, at = Math.floor((first + last) / 2), i;

        for (i = first + 1; i < last; i++) {
            var p = bezierAt(bz, u[i - first]), d = Math.hypot(p[0] - pts[i][0], p[1] - pts[i][1]);

            if (d > max) {
                max = d;
                at = i;
            }
        }

        return { max: max, at: at };
    }

    // One Newton step of t towards the point of the curve nearest p.
    function newtonRoot(bz, p, t) {
        var q = bezierAt(bz, t), m = 1 - t, d1 = [], d2 = [], j;

        for (j = 0; j < 2; j++) {
            d1[j] = 3 * (m * m * (bz[1][j] - bz[0][j]) + 2 * m * t * (bz[2][j] - bz[1][j]) + t * t * (bz[3][j] - bz[2][j]));
            d2[j] = 6 * (m * (bz[2][j] - 2 * bz[1][j] + bz[0][j]) + t * (bz[3][j] - 2 * bz[2][j] + bz[1][j]));
        }

        var num = (q[0] - p[0]) * d1[0] + (q[1] - p[1]) * d1[1], den = d1[0] * d1[0] + d1[1] * d1[1] + (q[0] - p[0]) * d2[0] + (q[1] - p[1]) * d2[1];

        return den ? Math.min(1, Math.max(0, t - num / den)) : t;
    }

    // A path layer's box, knots and subs (rot 0) for rings in the rotated
    // space, the largest outer ring first.
    function ringsPath(rings) {
        var order = rings.slice().sort(function (a, b) { return ringArea(b) - ringArea(a); });
        var contours = order.map(function (r) { return { knots: ringKnots(r), closed: true }; });
        var fit = refitPath({ x: 0, y: 0, w: 1, h: 1, rot: 0, closed: true }, contours[0].knots, contours.slice(1));

        return { x: fit.x, y: fit.y, w: fit.w, h: fit.h, rot: 0, closed: true, knots: fit.knots, subs: fit.subs || [] };
    }

    var BUILDER = {
        flattenRing: flattenRing, layerRings: layerRings, ringArea: ringArea, ringsArea: ringsArea, clipRings: clipRings,
        ringIslands: ringIslands, insideRings: insideRings, pathfinder: pathfinder, builderRegions: builderRegions, regionAt: regionAt,
        builderMerge: builderMerge, builderStack: builderStack, ringKnots: ringKnots, ringsPath: ringsPath
    };

    // -- area selection (pg_image_editor_area.js) --

    // A selection is kept in the document as doc.selection: { ops, invert,
    // feather }. Each op adds (op 'add') or takes away ('sub') a shape:
    // kind rect, ellipse, lasso or polygon with its ring in the turned
    // space, or kind wand with the seed point (at, turned space),
    // tolerance (0-100) and contiguous. A wand's outline is worked out from
    // the picture each time the document opens, so only its seed is kept.
    // Kind subject (pg_image_editor_subject.js) keeps its rings (rings) and
    // the box the cut started from, since the cut takes too long to redo;
    // its Keep and Remove brushes (strokes: [{ keep, r, pts }], turned
    // space) the cut ran again from, and its soft edge (edge, units, 0 or
    // none for hard) that layers made from the selection take as matte.

    var AREA_MAX_POINTS = 4000, AREA_MAX_OPS = 40;

    // A subject op keeps at most this many brush strokes; its soft edge is
    // at most SUBJECT_EDGE_MAX units, the guided filter's eps MATTE_EPS.
    var SUBJECT_MAX_STROKES = 60, SUBJECT_EDGE_MAX = 20, MATTE_EPS = 1e-4;

    function areaRound(v) {
        return Math.round(v * 100) / 100;
    }

    // A ring as clipRings() gives one: wound as an outer ring, null when it
    // has no area.
    function areaRing(r) {
        var out = nestRings([r.map(function (p) { return [areaRound(p[0]), areaRound(p[1])]; })]);

        return out.length ? out[0] : null;
    }

    // The rectangle between two corners.
    function areaRect(a, b) {
        var x0 = Math.min(a[0], b[0]), y0 = Math.min(a[1], b[1]), x1 = Math.max(a[0], b[0]), y1 = Math.max(a[1], b[1]);

        return ((x1 - x0 < 0.5) || (y1 - y0 < 0.5)) ? null : areaRing([[x0, y0], [x1, y0], [x1, y1], [x0, y1]]);
    }

    // The ellipse in the rectangle between two corners, as 96 points.
    function areaEllipse(a, b) {
        var x0 = Math.min(a[0], b[0]), y0 = Math.min(a[1], b[1]), rx = Math.abs(b[0] - a[0]) / 2, ry = Math.abs(b[1] - a[1]) / 2, pts = [], i;

        if ((rx < 0.25) || (ry < 0.25)) {
            return null;
        }

        for (i = 0; i < 96; i++) {
            pts.push([x0 + rx + rx * Math.cos(i * Math.PI / 48), y0 + ry + ry * Math.sin(i * Math.PI / 48)]);
        }

        return areaRing(pts);
    }

    // The ring a lasso or a polygon went round: points closer than step to
    // the last one kept are dropped, a long trace is thinned to
    // AREA_MAX_POINTS; null below three points or without area.
    function areaLasso(pts, step) {
        var out = [];

        (pts || []).forEach(function (p) {
            var last = out[out.length - 1];

            if (!last || (Math.hypot(p[0] - last[0], p[1] - last[1]) >= (step || 0))) {
                out.push([p[0], p[1]]);
            }
        });

        if ((out.length > 2) && (Math.hypot(out[0][0] - out[out.length - 1][0], out[0][1] - out[out.length - 1][1]) < (step || 0))) {
            out.pop();
        }

        if (out.length > AREA_MAX_POINTS) {
            var every = out.length / AREA_MAX_POINTS;

            out = out.filter(function (p, i) { return Math.floor(i / every) !== Math.floor((i - 1) / every); });
        }

        return (out.length > 2) ? areaRing(out) : null;
    }

    // Whether a drag went at least slack from its press at any point of its
    // path (pts), in the same units: a lasso closed on its first point ends
    // where it began and is still a shape.
    function areaDragged(at, pts, slack) {
        return (pts || []).some(function (p) { return Math.hypot(p[0] - at[0], p[1] - at[1]) >= slack; });
    }

    // The magic wand: 1 for each pixel of data (RGBA, w x h) whose channels
    // are all within the tolerance (0-100 of 255) of the seed pixel's at x,
    // y; with contiguous only those reached from the seed through such
    // pixels (four neighbours). A seed off the picture takes nothing.
    function wandMask(data, w, h, x, y, tolerance, contiguous) {
        var mask = new Uint8Array(w * h), sx = Math.floor(x), sy = Math.floor(y), limit = clamp(Number(tolerance) || 0, 0, 100) * 2.55;

        if ((sx < 0) || (sy < 0) || (sx >= w) || (sy >= h)) {
            return mask;
        }

        var o = (sy * w + sx) * 4, r = data[o], g = data[o + 1], b = data[o + 2], a = data[o + 3];
        var like = function (i) {
            var q = i * 4;

            return (Math.abs(data[q] - r) <= limit) && (Math.abs(data[q + 1] - g) <= limit)
                && (Math.abs(data[q + 2] - b) <= limit) && (Math.abs(data[q + 3] - a) <= limit);
        };
        var i;

        if (!contiguous) {
            for (i = 0; i < w * h; i++) {
                mask[i] = like(i) ? 1 : 0;
            }

            return mask;
        }

        var seen = new Uint8Array(w * h), stack = new Int32Array(w * h), top = 0;

        stack[top++] = sy * w + sx;
        seen[sy * w + sx] = 1;

        while (top) {
            i = stack[--top];

            if (!like(i)) {
                continue;
            }

            mask[i] = 1;

            var px = i % w, py = (i - px) / w;

            [[px > 0, i - 1], [px < w - 1, i + 1], [py > 0, i - w], [py < h - 1, i + w]].forEach(function (n) {
                if (n[0] && !seen[n[1]]) {
                    seen[n[1]] = 1;
                    stack[top++] = n[1];
                }
            });
        }

        return mask;
    }

    // The outline of a mask's pixels (w x h, 1 in) as rings in pixel units
    // on the pixels' edges, holes included, wound as clipRings() gives them.
    // Each edge between a pixel in and one out is walked with the pixel on
    // its right; where two pixels touch only at a corner the walk turns
    // right, so every ring is simple. Straight runs are one edge.
    function maskRings(mask, w, h) {
        var W = w + 1, outA = new Int32Array(W * (h + 1)).fill(-1), outB = new Int32Array(W * (h + 1)).fill(-1), rings = [], x, y;
        var on = function (px, py) { return (px >= 0) && (py >= 0) && (px < w) && (py < h) && !!mask[py * w + px]; };
        var add = function (from, to) {
            if (outA[from] < 0) {
                outA[from] = to;
            } else {
                outB[from] = to;
            }
        };

        for (y = 0; y < h; y++) {
            for (x = 0; x < w; x++) {
                if (!on(x, y)) {
                    continue;
                }

                if (!on(x, y - 1)) {
                    add(y * W + x, y * W + x + 1);
                }

                if (!on(x + 1, y)) {
                    add(y * W + x + 1, (y + 1) * W + x + 1);
                }

                if (!on(x, y + 1)) {
                    add((y + 1) * W + x + 1, (y + 1) * W + x);
                }

                if (!on(x - 1, y)) {
                    add((y + 1) * W + x, y * W + x);
                }
            }
        }

        var dir = function (a, b) { return [(b % W) - (a % W), Math.floor(b / W) - Math.floor(a / W)]; };
        var take = function (v, d) {
            var a = outA[v], b = outB[v], pick;

            if (b < 0) {
                pick = a;
                outA[v] = -1;
            } else if (a < 0) {
                pick = b;
                outB[v] = -1;
            } else {
                // Two ways on at a corner: the right turn (screen axes).
                var da = dir(v, a), turnA = d[0] * da[1] - d[1] * da[0];

                if (turnA > 0) {
                    pick = a;
                    outA[v] = -1;
                } else {
                    pick = b;
                    outB[v] = -1;
                }
            }

            return pick;
        };
        var v0;

        for (v0 = 0; v0 < W * (h + 1); v0++) {
            while (outA[v0] >= 0 || outB[v0] >= 0) {
                var pts = [], v = v0, d = null, start = null, next, nd, guard = 0;

                do {
                    next = take(v, d || [0, 0]);

                    if (next < 0) {
                        break;
                    }

                    nd = dir(v, next);
                    start = start || nd;

                    // A corner only where the way turns.
                    if (!d || (nd[0] !== d[0]) || (nd[1] !== d[1])) {
                        pts.push([v % W, Math.floor(v / W)]);
                    }

                    d = nd;
                    v = next;
                } while ((v !== v0) && (++guard < 4 * W * (h + 1)));

                // Back at the start on the way it left: no corner there.
                if (d && start && (d[0] === start[0]) && (d[1] === start[1])) {
                    pts.shift();
                }

                if (pts.length > 2) {
                    rings.push(pts);
                }
            }
        }

        return nestRings(rings);
    }

    // Rings carried by the matrix m.
    function mapRings(rings, m) {
        return rings.map(function (r) { return r.map(function (p) { return apply(m, p[0], p[1]); }); });
    }

    // The selection's area as rings in the turned space: its shapes folded
    // in order (a wand's rings from wands[i], skipped until worked out),
    // inverted within frame { x, y, w, h } when sel.invert.
    function selectionRings(sel, wands, frame) {
        var acc = [];

        if (!sel) {
            return acc;
        }

        (sel.ops || []).slice(0, AREA_MAX_OPS).forEach(function (o, i) {
            var rings = (o.kind === 'wand') ? (wands && wands[i]) : (o.rings || (o.ring ? [o.ring] : null));

            if (!rings || !rings.length) {
                return;
            }

            if (o.op === 'sub') {
                acc = acc.length ? clipRings(acc, rings, 'difference') : [];
            } else {
                acc = acc.length ? clipRings(acc, rings, 'union') : nestRings(rings);
            }
        });

        if (sel.invert && frame) {
            var all = [[frame.x, frame.y], [frame.x + frame.w, frame.y], [frame.x + frame.w, frame.y + frame.h], [frame.x, frame.y + frame.h]];

            acc = acc.length ? clipRings(nestRings([all]), acc, 'difference') : nestRings([all]);
        }

        return acc;
    }

    // A redaction area of the selection's rings: their box (rot 0) and the
    // rings in parts of it, so the area moves, stretches and turns with its
    // box; null for no rings.
    function areaLayer(rings) {
        if (!rings || !rings.length) {
            return null;
        }

        var b = ringsBox(rings), w = Math.max(1, b[2] - b[0]), h = Math.max(1, b[3] - b[1]);
        var part = function (v) { return Math.round(v * 1e5) / 1e5; };

        return {
            x: b[0], y: b[1], w: w, h: h, rot: 0, shape: 'area',
            area: rings.map(function (r) { return r.map(function (p) { return [part((p[0] - b[0]) / w), part((p[1] - b[1]) / h)]; }); })
        };
    }

    // An area redaction's rings in the turned space, its box turned as a
    // brush's is.
    function areaPoints(l) {
        var m = mul(translate(l.x + l.w / 2, l.y + l.h / 2), mul(rotate(l.rot || 0), translate(-l.w / 2, -l.h / 2)));

        return (l.area || []).map(function (r) { return r.map(function (p) { return apply(m, p[0] * l.w, p[1] * l.h); }); });
    }

    // Whether p, in an area layer's own frame (as M.hitLayer() passes it),
    // lies inside its outline. An erased background's hole is not the
    // layer's, so a press there reaches what is under it. Other shapes are
    // hit anywhere in their box.
    function areaHit(l, p) {
        if ((l.shape !== 'area') || !l.area || !l.w || !l.h) {
            return true;
        }

        return insideRings([p[0] / l.w, p[1] / l.h], l.area);
    }

    // What lies in frame { x, y, w, h } outside the selection's rings: the
    // part Keep only erases.
    function areaRest(rings, frame) {
        var all = [[frame.x, frame.y], [frame.x + frame.w, frame.y], [frame.x + frame.w, frame.y + frame.h], [frame.x, frame.y + frame.h]];

        return (rings && rings.length) ? clipRings(nestRings([all]), rings, 'difference') : nestRings([all]);
    }

    // A closed ring with the points that lie within tol of the line between
    // their neighbours kept dropped (Douglas-Peucker from its two farthest
    // points apart).
    function simplifyRing(r, tol) {
        if (r.length < 5) {
            return r.slice();
        }

        var far = 0, best = -1, keep = new Uint8Array(r.length);

        r.forEach(function (p, i) {
            var d = Math.hypot(p[0] - r[0][0], p[1] - r[0][1]);

            if (d > best) {
                best = d;
                far = i;
            }
        });

        var run = function (a, b) {
            var stack = [[a, b]];

            while (stack.length) {
                var s = stack.pop(), i0 = s[0], i1 = s[1], p0 = r[i0 % r.length], p1 = r[i1 % r.length];
                var dx = p1[0] - p0[0], dy = p1[1] - p0[1], len = Math.hypot(dx, dy), top = -1, at = -1;

                for (var i = i0 + 1; i < i1; i++) {
                    var p = r[i % r.length];
                    var d = len ? Math.abs(dx * (p[1] - p0[1]) - dy * (p[0] - p0[0])) / len : Math.hypot(p[0] - p0[0], p[1] - p0[1]);

                    if (d > top) {
                        top = d;
                        at = i;
                    }
                }

                if (top > tol) {
                    keep[at % r.length] = 1;
                    stack.push([i0, at], [at, i1]);
                }
            }
        };

        keep[0] = keep[far] = 1;
        run(0, far);
        run(far, r.length);

        return r.filter(function (p, i) { return keep[i]; });
    }

    // The rings of a subject's mask (a byte a pixel, w x h) in the turned
    // space through the matrix m: specks and holes under 0.05 % of the
    // picture go, the pixel steps are smoothed within 0.75 px.
    function subjectRings(mask, w, h, m) {
        var least = Math.max(4, w * h * 0.0005);
        var rings = maskRings(mask, w, h).filter(function (r) { return Math.abs(ringArea(r)) >= least; }).map(function (r) {
            return simplifyRing(r, 0.75);
        }).filter(function (r) { return r.length > 2; });

        return nestRings(mapRings(rings, m).map(function (r) {
            return r.map(function (p) { return [areaRound(p[0]), areaRound(p[1])]; });
        }));
    }

    // The subject op whose brushes and soft edge the panel shows: the last
    // op of kind subject in the selection; -1 for none.
    function subjectIndex(sel) {
        var ops = (sel && sel.ops) || [], i;

        for (i = Math.min(ops.length, AREA_MAX_OPS) - 1; i >= 0; i--) {
            if (ops[i] && (ops[i].kind === 'subject')) {
                return i;
            }
        }

        return -1;
    }

    // The soft edge a layer of the selection takes (its subject's edge, in
    // the turned space's units); 0 for a hard one.
    function selectionMatte(sel) {
        var i = subjectIndex(sel), e = (i >= 0) ? Number(sel.ops[i].edge) : 0;

        return (isFinite(e) && (e > 0)) ? Math.min(SUBJECT_EDGE_MAX, e) : 0;
    }

    // A brush stroke kept on a subject op: its path thinned to points at
    // least a quarter of its radius apart (at most AREA_MAX_POINTS), in the
    // turned space; null without a point.
    function subjectStroke(keep, r, pts) {
        var out = [], step = Math.max(0.25, r / 4);

        (pts || []).forEach(function (p) {
            var last = out[out.length - 1];

            if (!last || (Math.hypot(p[0] - last[0], p[1] - last[1]) >= step)) {
                out.push([areaRound(p[0]), areaRound(p[1])]);
            }
        });

        if (!out.length || !(r > 0)) {
            return null;
        }

        if (out.length > AREA_MAX_POINTS) {
            var every = out.length / AREA_MAX_POINTS;

            out = out.filter(function (p, i) { return Math.floor(i / every) !== Math.floor((i - 1) / every); });
        }

        return { keep: keep ? 1 : 0, r: areaRound(r), pts: out };
    }

    // The mean of src (w x h) over a (2r + 1) square round each pixel, the
    // square cut at the edges, into out.
    function boxMean(src, w, h, r, out, tmp) {
        var x, y, s, a, b;

        for (y = 0; y < h; y++) {
            var row = y * w;

            s = 0;

            for (x = 0; x < Math.min(r, w); x++) {
                s += src[row + x];
            }

            for (x = 0; x < w; x++) {
                a = x + r;
                b = x - r - 1;

                if (a < w) {
                    s += src[row + a];
                }

                if (b >= 0) {
                    s -= src[row + b];
                }

                tmp[row + x] = s / (Math.min(w - 1, a) - Math.max(0, b + 1) + 1);
            }
        }

        for (x = 0; x < w; x++) {
            s = 0;

            for (y = 0; y < Math.min(r, h); y++) {
                s += tmp[y * w + x];
            }

            for (y = 0; y < h; y++) {
                a = y + r;
                b = y - r - 1;

                if (a < h) {
                    s += tmp[a * w + x];
                }

                if (b >= 0) {
                    s -= tmp[b * w + x];
                }

                out[y * w + x] = s / (Math.min(h - 1, a) - Math.max(0, b + 1) + 1);
            }
        }

        return out;
    }

    // The guided filter (He, Sun and Tang) of the alpha p (0-1, w x h) with
    // the colour picture guide (RGBA bytes) as its guide, radius r, eps
    // the smoothing in (0-1)^2 colour units: the alpha follows the
    // picture's edges within r of its own.
    function guidedFilter(guide, p, w, h, r, eps) {
        var n = w * h, I = [new Float32Array(n), new Float32Array(n), new Float32Array(n)], tmp = new Float32Array(n), i, c;
        var mean = function (src) { return boxMean(src, w, h, r, new Float32Array(n), tmp); };
        var prod = function (a, b) { var o = new Float32Array(n); for (var k = 0; k < n; k++) { o[k] = a[k] * b[k]; } return o; };

        for (i = 0; i < n; i++) {
            for (c = 0; c < 3; c++) {
                I[c][i] = guide[i * 4 + c] / 255;
            }
        }

        var mI = I.map(mean), mp = mean(p), cIp = I.map(function (ch) { return mean(prod(ch, p)); });
        var vRR = mean(prod(I[0], I[0])), vRG = mean(prod(I[0], I[1])), vRB = mean(prod(I[0], I[2]));
        var vGG = mean(prod(I[1], I[1])), vGB = mean(prod(I[1], I[2])), vBB = mean(prod(I[2], I[2]));
        var A = [new Float32Array(n), new Float32Array(n), new Float32Array(n)], B = new Float32Array(n);

        for (i = 0; i < n; i++) {
            var m0 = mI[0][i], m1 = mI[1][i], m2 = mI[2][i], mpi = mp[i];
            var c0 = cIp[0][i] - m0 * mpi, c1 = cIp[1][i] - m1 * mpi, c2 = cIp[2][i] - m2 * mpi;
            var s00 = vRR[i] - m0 * m0 + eps, s01 = vRG[i] - m0 * m1, s02 = vRB[i] - m0 * m2;
            var s11 = vGG[i] - m1 * m1 + eps, s12 = vGB[i] - m1 * m2, s22 = vBB[i] - m2 * m2 + eps;
            var i00 = s11 * s22 - s12 * s12, i01 = s02 * s12 - s01 * s22, i02 = s01 * s12 - s02 * s11;
            var i11 = s00 * s22 - s02 * s02, i12 = s01 * s02 - s00 * s12, i22 = s00 * s11 - s01 * s01;
            var det = s00 * i00 + s01 * i01 + s02 * i02;
            var a0 = (c0 * i00 + c1 * i01 + c2 * i02) / det, a1 = (c0 * i01 + c1 * i11 + c2 * i12) / det, a2 = (c0 * i02 + c1 * i12 + c2 * i22) / det;

            A[0][i] = a0;
            A[1][i] = a1;
            A[2][i] = a2;
            B[i] = mpi - a0 * m0 - a1 * m1 - a2 * m2;
        }

        var mA = A.map(mean), mB = mean(B), q = new Float32Array(n);

        for (i = 0; i < n; i++) {
            q[i] = Math.max(0, Math.min(1, mA[0][i] * I[0][i] + mA[1][i] * I[1][i] + mA[2][i] * I[2][i] + mB[i]));
        }

        return q;
    }

    // The soft edge of a mask: its alpha (RGBA bytes, w x h, the alpha
    // byte) guided by the picture under it (guide, RGBA bytes of the same
    // size) with radius r pixels, in place. The work goes tile by tile and
    // skips a tile whose alpha is one value 2r round it, where the filter
    // changes nothing, so a large mask costs only its edge.
    function matteMask(mask, guide, w, h, r) {
        r = Math.max(1, Math.round(r));

        var T = 256, halo = 2 * r, tx, ty;

        for (ty = 0; ty < h; ty += T) {
            for (tx = 0; tx < w; tx += T) {
                var x0 = Math.max(0, tx - halo), y0 = Math.max(0, ty - halo), x1 = Math.min(w, tx + T + halo), y1 = Math.min(h, ty + T + halo);
                var tw = x1 - x0, th = y1 - y0, first = mask[(y0 * w + x0) * 4 + 3], flat = true, x, y;

                for (y = y0; (y < y1) && flat; y++) {
                    for (x = x0; x < x1; x++) {
                        if (mask[(y * w + x) * 4 + 3] !== first) {
                            flat = false;
                            break;
                        }
                    }
                }

                if (flat) {
                    continue;
                }

                var p = new Float32Array(tw * th), g = new Uint8ClampedArray(tw * th * 4);

                for (y = 0; y < th; y++) {
                    for (x = 0; x < tw; x++) {
                        var at = ((y0 + y) * w + x0 + x) * 4, k = y * tw + x;

                        p[k] = mask[at + 3] / 255;
                        g[k * 4] = guide[at];
                        g[k * 4 + 1] = guide[at + 1];
                        g[k * 4 + 2] = guide[at + 2];
                    }
                }

                var q = guidedFilter(g, p, tw, th, r, MATTE_EPS);

                for (y = ty; y < Math.min(h, ty + T); y++) {
                    for (x = tx; x < Math.min(w, tx + T); x++) {
                        mask[(y * w + x) * 4 + 3] = Math.round(q[(y - y0) * tw + x - x0] * 255);
                    }
                }
            }
        }

        return mask;
    }

    var SELECTION = {
        AREA_MAX_OPS: AREA_MAX_OPS, areaDragged: areaDragged, areaRect: areaRect, areaEllipse: areaEllipse, areaLasso: areaLasso, wandMask: wandMask,
        maskRings: maskRings, mapRings: mapRings, selectionRings: selectionRings, areaLayer: areaLayer, areaPoints: areaPoints, areaRest: areaRest, areaHit: areaHit,
        simplifyRing: simplifyRing, subjectRings: subjectRings, subjectIndex: subjectIndex, selectionMatte: selectionMatte, subjectStroke: subjectStroke,
        guidedFilter: guidedFilter, matteMask: matteMask, SUBJECT_MAX_STROKES: SUBJECT_MAX_STROKES, SUBJECT_EDGE_MAX: SUBJECT_EDGE_MAX
    };

    var api = {
        mul: mul, inv: inv, apply: apply, translate: translate, scale: scale, rotate: rotate, clamp: clamp,
        flipRot: flipRot, coverScale: coverScale, fitZoom: fitZoom, centred: centred, fitView: fitView, zoomAt: zoomAt,
        PROFILES: PROFILES, TOOLS: TOOLS, resolveTools: resolveTools,
        RAIL_GROUPS: RAIL_GROUPS, railGroup: railGroup, railItems: railItems, railItemOf: railItemOf, railOpen: railOpen,
        clone: clone, sameDocument: sameDocument, newDocument: newDocument, docSize: docSize, History: History,
        RATIOS: RATIOS, givenRatio: givenRatio, cropRatios: cropRatios, cropBox: cropBox, pictureMatrix: pictureMatrix, fitRatio: fitRatio, resizeCrop: resizeCrop,
        moveCrop: moveCrop, mapLayer: mapLayer, reorient: reorient, turnDocument: turnDocument, flipDocument: flipDocument,
        outputSize: outputSize, ADJUST: ADJUST, FILTERS: FILTERS, FILTER_NAMES: FILTER_NAMES, colorValues: colorValues,
        colorMatrix: colorMatrix, applyColor: applyColor, vignetteStops: vignetteStops, sharpenKernel: sharpenKernel,
        newId: newId, corners: corners, bounds: bounds, local: local, hitLayer: hitLayer, layersInBox: layersInBox, insideBox: insideBox, resizeBox: resizeBox, angleTo: angleTo,
        align: align, distribute: distribute, snapBox: snapBox, reorder: reorder,
        ownsKey: ownsKey, focusMatch: focusMatch, panelTitle: panelTitle
    };

    // A name taken twice would hide a function: the model refuses to load,
    // so the tests stop it.
    [DRAW, TEXT, STICKER, REDACT, VERSIONS, ARTBOARD, EXPORT, VECTOR, SVG, MOTION, KEYS, TRACKS, RENDER, ANIMSAVE, SVGMOTION, ACCESS, GFONTS, GLYPH, WARP, PUPPET, SCENES, PRESENT, OUTLINE, BUILDER, SELECTION, ACTIONS, MENU].forEach(function (part) {
        Object.keys(part).forEach(function (name) {
            if (api.hasOwnProperty(name)) {
                throw new Error('PgImageEditorModel: ' + name + ' is named twice');
            }

            api[name] = part[name];
        });
    });

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.PgImageEditorModel = api;
    }
}((typeof window !== 'undefined') ? window : null));
