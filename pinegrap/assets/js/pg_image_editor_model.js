/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor model: window.PgImageEditorModel.
 *
 * The pure part of the image editor (assets/js/pg_image_editor.js): 2D affine
 * matrices in SVG order [a, b, c, d, e, f], the picture's quarter turns and
 * flips, the cover scale of a straightened crop, the view's fit and zoom, the
 * tool list of a call, the document and its undo history. Nothing here
 * touches the page, so tests/image_editor_model_test.php runs it under Node.
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
        photo: ['select', 'crop', 'adjust', 'draw', 'text', 'redact', 'sticker', 'frame', 'vector'],
        canvas: ['select', 'artboard', 'draw', 'text', 'sticker', 'image', 'vector', 'frame'],
        annotate: ['arrow', 'rect', 'text', 'pen', 'redact'],
        optimise: ['export']
    };

    // Tools a caller may name: the rail's and the marking tools of the mini
    // bar (one draw kind each).
    var TOOLS = ['select', 'crop', 'adjust', 'draw', 'text', 'redact', 'sticker', 'frame', 'vector', 'artboard', 'image', 'export',
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

    // Layers that keep their size and stay upright when the picture turns
    // or flips: only their centre moves.
    // Layers whose content a turn or a flip of the picture does not turn:
    // only their place follows it.
    var UPRIGHT = ['text', 'sticker', 'image'];

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

        // A mirror turns a box's own angle the other way.
        var o = fn(0, 0), ex = fn(1, 0), ey = fn(0, 1);

        if (l.rot && (UPRIGHT.indexOf(l.type) < 0) && ((ex[0] - o[0]) * (ey[1] - o[1]) - (ex[1] - o[1]) * (ey[0] - o[0]) < 0)) {
            l.rot = -l.rot;
        }

        if ((typeof l.x === 'number') && (typeof l.w === 'number')) {
            if (UPRIGHT.indexOf(l.type) >= 0) {
                p = fn(l.x + l.w / 2, l.y + l.h / 2);
                l.x = p[0] - l.w / 2;
                l.y = p[1] - l.h / 2;
            } else {
                p = fn(l.x, l.y);
                q = fn(l.x + l.w, l.y + l.h);
                l.x = Math.min(p[0], q[0]);
                l.y = Math.min(p[1], q[1]);
                l.w = Math.abs(q[0] - p[0]);
                l.h = Math.abs(q[1] - p[1]);
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
    function hitLayer(doc, x, y, slack) {
        for (var i = doc.layers.length - 1; i >= 0; i--) {
            var l = doc.layers[i], p = local(l, x, y);

            if (!l.hidden && !l.locked && (p[0] >= -slack) && (p[1] >= -slack) && (p[0] <= l.w + slack) && (p[1] <= l.h + slack)) {
                return l;
            }
        }

        return null;
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

    // Whether the focused element uses Enter or Space itself (key ' '), so
    // the editor leaves the key alone and the element gets pressed: a
    // button, a link, a text field, a checkbox's Space. On the stage, a
    // slider or the dialog the keys stay the editor's (apply the crop, pan).
    // info: { tag, type, role, editable } of the focused element.
    var KEY_ROLES = /^(button|link|menuitem|menuitemradio|menuitemcheckbox|tab|option|checkbox|radio|switch|textbox|combobox)$/;

    function ownsKey(info, key) {
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

    var api = {
        mul: mul, inv: inv, apply: apply, translate: translate, scale: scale, rotate: rotate, clamp: clamp,
        flipRot: flipRot, coverScale: coverScale, fitZoom: fitZoom, centred: centred, fitView: fitView, zoomAt: zoomAt,
        PROFILES: PROFILES, TOOLS: TOOLS, resolveTools: resolveTools,
        clone: clone, sameDocument: sameDocument, newDocument: newDocument, docSize: docSize, History: History,
        RATIOS: RATIOS, cropBox: cropBox, pictureMatrix: pictureMatrix, fitRatio: fitRatio, resizeCrop: resizeCrop,
        moveCrop: moveCrop, mapLayer: mapLayer, reorient: reorient, turnDocument: turnDocument, flipDocument: flipDocument,
        outputSize: outputSize, ADJUST: ADJUST, FILTERS: FILTERS, FILTER_NAMES: FILTER_NAMES, colorValues: colorValues,
        colorMatrix: colorMatrix, applyColor: applyColor, vignetteStops: vignetteStops, sharpenKernel: sharpenKernel,
        newId: newId, corners: corners, bounds: bounds, local: local, hitLayer: hitLayer, resizeBox: resizeBox, angleTo: angleTo,
        align: align, distribute: distribute, snapBox: snapBox, reorder: reorder,
        ownsKey: ownsKey
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.PgImageEditorModel = api;
    }
}((typeof window !== 'undefined') ? window : null));
