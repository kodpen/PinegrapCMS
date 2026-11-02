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
        photo: ['select', 'crop', 'artboard', 'adjust', 'draw', 'text', 'redact', 'sticker', 'frame', 'vector'],
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
    function hitLayer(doc, x, y, slack, test) {
        for (var i = doc.layers.length - 1; i >= 0; i--) {
            var l = doc.layers[i], p = local(l, x, y);

            if (!l.hidden && !l.locked && (p[0] >= -slack) && (p[1] >= -slack) && (p[0] <= l.w + slack) && (p[1] <= l.h + slack)
                    && (!test || test(l, p, slack))) {
                return l;
            }
        }

        return null;
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

        if (LINE_KINDS.indexOf(l.type) >= 0) {
            return [[[0, h / 2], [w, h / 2]]];
        }

        if (l.type === 'path') {
            return [curvePoints(pathKnots(l), !!l.closed, 16)];
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
        var q = [l.mirror ? l.w - p[0] : p[0], p[1]], reach = (l.stroke || 1) / 2 + slack, lines = outline(l), i, j;

        if ((l.fill === 'solid' || l.fill === 'soft') && isClosed(l) && inside(q, lines[0])) {
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

    // A pen stroke after the eraser passed x, y with radius r: null when it
    // was not touched, else the pieces left (none when all went), each a
    // new box and trace that keeps the stroke's angle and mirror.
    function eraseTrace(l, x, y, r) {
        var c = drawnLocal(l, x, y), reach = r + (l.stroke || 1) / 2, runs = [], run = [], touched = false;

        (l.trace || []).forEach(function (t) {
            var p = [t[0] * l.w, t[1] * l.h];

            if (Math.hypot(p[0] - c[0], p[1] - c[1]) <= reach) {
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
        eraseTrace: eraseTrace, dashFor: dashFor, fillFor: fillFor
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

        // The output pixels an area covers: its corners (a brush grown by
        // half its width) carried by the crop box to w x h, whole pixels,
        // inside the output; null when it lies outside.
        redactRegion: function (l, box, w, h) {
            var sx = w / box.w, sy = h / box.h, grow = (l.shape === 'brush') ? (l.size || 0) / 2 : 0;
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
        var c = cropBox(doc), m = doc.canvas;

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

        var b = saveBox(doc), c = cropBox(doc), out = outputSize(doc), sx = out.w / c.w, sy = out.h / c.h;
        var fill = doc.canvas ? doc.canvas.background : doc.frame.color;

        return {
            w: Math.round(clamp(b.w * sx, 1, SIZE_MAX)), h: Math.round(clamp(b.h * sy, 1, SIZE_MAX)),
            x: Math.round((c.x - b.x) * sx), y: Math.round((c.y - b.y) * sy),
            fill: /^#[0-9a-f]{6}$/i.test(fill || '') ? fill : 'transparent'
        };
    }

    // The size a save writes.
    function saveSize(doc) {
        var l = saveLayout(doc);

        return doc.artboard ? { w: doc.artboard.width, h: doc.artboard.height } : (l ? { w: l.w, h: l.h } : outputSize(doc));
    }

    // doc with its picture's canvas moved to r (in the canvas box's units,
    // as boardDrag() gives it): whole units on each side, at least SIZE_MIN
    // and at most SIZE_MAX across. The layers stay: they lie in the rotated
    // space, not on the canvas. A canvas back on the crop box with a white
    // background is none.
    function applyCanvas(doc, r) {
        var d = clone(doc), c = cropBox(doc), b = canvasBox(doc);
        var x = b.x + Math.round(r.x), y = b.y + Math.round(r.y), w = boardSide(r.w), h = boardSide(r.h);
        var m = { l: c.x - x, t: c.y - y, r: x + w - c.x - c.w, b: y + h - c.y - c.h, background: doc.canvas ? doc.canvas.background : '#ffffff' };

        ['l', 't', 'r', 'b'].forEach(function (k) { m[k] = Math.round(m[k] * 1000) / 1000; });
        d.canvas = (!m.l && !m.t && !m.r && !m.b && (m.background === '#ffffff')) ? null : m;

        return d;
    }

    var ARTBOARD = {
        BOARD_PRESETS: BOARD_PRESETS, BOARD_BACKGROUNDS: BOARD_BACKGROUNDS, boardSide: boardSide, boardPreset: boardPreset,
        boardDrag: boardDrag, boardAnchored: boardAnchored, boardAround: boardAround, applyBoard: applyBoard,
        canvasBox: canvasBox, FRAME_OUT: FRAME_OUT, frameOutward: frameOutward, framePads: framePads, saveBox: saveBox,
        saveLayout: saveLayout, saveSize: saveSize, applyCanvas: applyCanvas
    };

    // -- export (pg_image_editor_export.js) --

    var EXPORT = {};

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
    function refitPath(l, knots) {
        var b = knotBounds(knots, l.closed), w = Math.max(1, b.w), h = Math.max(1, b.h);
        var x0 = b.x + b.w / 2 - w / 2, y0 = b.y + b.h / 2 - h / 2, c = drawnPoint(l, x0 + w / 2, y0 + h / 2);

        return {
            x: c[0] - w / 2, y: c[1] - h / 2, w: w, h: h,
            knots: moveKnots(knots, function (px, py) { return [frac(px - x0, w), frac(py - y0, h)]; })
        };
    }

    function pathShape(l) {
        return { d: knotsD(pathKnots(l), !!l.closed), head: null, closed: !!l.closed };
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

    var VECTOR = {
        segments: segments, cubicAt: cubicAt, knotsD: knotsD, curvePoints: curvePoints, knotBounds: knotBounds, pathLayer: pathLayer,
        pathKnots: pathKnots, refitPath: refitPath, pathShape: pathShape, smoothKnot: smoothKnot, cornerKnot: cornerKnot,
        moveKnot: moveKnot, moveHandle: moveHandle, nearestOnPath: nearestOnPath, splitAt: splitAt, shapeKnots: shapeKnots
    };

    // -- access (pg_image_editor_access.js) --

    // A letter picks a tool of the call: the first of its tools the call
    // has, so A is Adjust on the rail and Arrow on the mini bar.
    var KEY_TOOLS = {
        v: ['select'], c: ['crop'], a: ['adjust', 'arrow'], d: ['draw'], t: ['text'], r: ['redact'], u: ['rect'], s: ['sticker'],
        f: ['frame'], p: ['vector', 'pen'], b: ['artboard'], i: ['image'], e: ['export'], l: ['line'], o: ['ellipse']
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

    // The help sheet's groups for a call's tools: { title, rows: [{ keys,
    // label }] }, keys as tokens (Mod is Ctrl, or Cmd on a Mac; Space,
    // Delete, Arrows and Drag are named by the sheet), title and label as
    // label keys. Layer keys show only with a tool that makes layers, Enter
    // only with Crop.
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

    var ACCESS = { KEY_TOOLS: KEY_TOOLS, toolForKey: toolForKey, toolKey: toolKey, trapFocus: trapFocus, twistAngle: twistAngle, keyMap: keyMap };

    var api = {
        mul: mul, inv: inv, apply: apply, translate: translate, scale: scale, rotate: rotate, clamp: clamp,
        flipRot: flipRot, coverScale: coverScale, fitZoom: fitZoom, centred: centred, fitView: fitView, zoomAt: zoomAt,
        PROFILES: PROFILES, TOOLS: TOOLS, resolveTools: resolveTools,
        clone: clone, sameDocument: sameDocument, newDocument: newDocument, docSize: docSize, History: History,
        RATIOS: RATIOS, givenRatio: givenRatio, cropRatios: cropRatios, cropBox: cropBox, pictureMatrix: pictureMatrix, fitRatio: fitRatio, resizeCrop: resizeCrop,
        moveCrop: moveCrop, mapLayer: mapLayer, reorient: reorient, turnDocument: turnDocument, flipDocument: flipDocument,
        outputSize: outputSize, ADJUST: ADJUST, FILTERS: FILTERS, FILTER_NAMES: FILTER_NAMES, colorValues: colorValues,
        colorMatrix: colorMatrix, applyColor: applyColor, vignetteStops: vignetteStops, sharpenKernel: sharpenKernel,
        newId: newId, corners: corners, bounds: bounds, local: local, hitLayer: hitLayer, insideBox: insideBox, resizeBox: resizeBox, angleTo: angleTo,
        align: align, distribute: distribute, snapBox: snapBox, reorder: reorder,
        ownsKey: ownsKey, focusMatch: focusMatch, panelTitle: panelTitle
    };

    // A name taken twice would hide a function: the model refuses to load,
    // so the tests stop it.
    [DRAW, TEXT, STICKER, REDACT, VERSIONS, ARTBOARD, EXPORT, VECTOR, ACCESS].forEach(function (part) {
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
