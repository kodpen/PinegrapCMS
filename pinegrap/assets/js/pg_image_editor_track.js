/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design Canvas: the tracker behind Follow. Template matching on grey
 * frames with no library and no trained model: the box's patch in the
 * first frame is the template; in each next frame the best place is
 * searched round the predicted one (the last place plus the last move) by
 * zero-mean normalised cross-correlation, within the larger of 16 px and
 * half the box's longer side, and the peak refined below a pixel by a
 * parabola through its neighbours. The template takes a small share of a
 * well matched patch, so a slow change of light or angle is kept up with;
 * the first template is the check against drift. Lost: the score under the
 * floor for five frames in a row, or the box mostly outside the frame.
 *
 * A frame may come as a region of the whole: a small thing is searched at a
 * scale where it is a few dozen pixels, and only the part round its last
 * place is read at that scale. Places stay in the whole frame's pixels; each
 * answer names the region the next frame is wanted in (want).
 *
 * Runs as a worker (messages below), on the page in slices where a site's
 * policy refuses the worker (window.PgImageTrack.pageSession()), and under
 * Node for the tests (module.exports): the same object in each.
 *
 * Messages in:  { type: 'start', frame, width, height, box, t, full, at }
 *               (frame an ArrayBuffer of grey bytes, a byte a pixel, width x
 *               height; full [w, h] the whole frame, at [x, y] the frame's
 *               top left in it, both left out for a whole frame; box [x0,
 *               y0, x1, y1] in the whole frame's pixels; t the frame's time,
 *               kept as given)
 *               { type: 'frame', frame, t, width, height, at }  the next
 *               frame; width, height and at left out for a whole frame
 *               { type: 'stop' }
 *          out: { type: 'ready', t, x, y, want }  the box's centre; want
 *               [x0, y0, x1, y1] the region to send the next frame in
 *               { type: 'point', t, x, y, score, want }  a good frame's centre
 *               { type: 'weak', t, score, want }  a frame under the floor
 *               { type: 'lost', t, last, why }  t the first bad frame, last
 *               the last good one, why 'edge' (it left the frame) or
 *               'match' (it no longer matched); later frames are not
 *               answered
 * A failure answers { type: 'error', code, message } (code 'flat': the box
 * holds nothing to follow; 'size': the pixels do not match; 'order': a
 * frame before start; 'box': the box is too small or outside the frame).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (root) {
    'use strict';

    // The search reaches at least this far (px) from the predicted place.
    var MIN_RADIUS = 16;

    // A frame scoring under this is weak; this many weak frames in a row
    // are lost.
    var FLOOR = 0.6;
    var WEAK_RUN = 5;

    // A found patch scoring at least this is mixed into the template, by
    // this share.
    var RENEW_MIN = 0.85;
    var RENEW_SHARE = 0.05;

    // Where the first template scores under this at the found place the
    // template has drifted and starts again from the first.
    var DRIFT_MIN = 0.5;

    // The smallest box side (px) and the share of the box that must stay
    // inside the frame.
    var MIN_SIDE = 4;
    var INSIDE_MIN = 0.5;

    function failure(code, message) {
        var e = new Error(message);

        e.code = code;

        return e;
    }

    // The offset (-0.5..0.5) of a parabola's top through three equally
    // spaced scores a (left), b (centre), c (right).
    function parabola(a, b, c) {
        var d = a - 2 * b + c;

        if (!(d < 0)) {
            return 0;
        }

        return Math.max(-0.5, Math.min(0.5, (a - c) / (2 * d)));
    }

    // The sums and squared sums of a w x h grey frame, (w + 1) x (h + 1).
    function integrals(f, w, h) {
        var s = new Float64Array((w + 1) * (h + 1)), q = new Float64Array((w + 1) * (h + 1)), x, y, W = w + 1;

        for (y = 0; y < h; y++) {
            var rs = 0, rq = 0;

            for (x = 0; x < w; x++) {
                var v = f[y * w + x];

                rs += v;
                rq += v * v;
                s[(y + 1) * W + x + 1] = s[y * W + x + 1] + rs;
                q[(y + 1) * W + x + 1] = q[y * W + x + 1] + rq;
            }
        }

        return { s: s, q: q, W: W };
    }

    // The patch of frame f (w wide) at x, y of size tw x th, as floats.
    function patch(f, w, x, y, tw, th) {
        var p = new Float32Array(tw * th), i, j;

        for (j = 0; j < th; j++) {
            for (i = 0; i < tw; i++) {
                p[j * tw + i] = f[(y + j) * w + x + i];
            }
        }

        return p;
    }

    // A template made zero-mean, and its norm.
    function centred(p) {
        var m = 0, n = 0, i, z = new Float32Array(p.length);

        for (i = 0; i < p.length; i++) {
            m += p[i];
        }

        m /= p.length;

        for (i = 0; i < p.length; i++) {
            z[i] = p[i] - m;
            n += z[i] * z[i];
        }

        return { z: z, norm: Math.sqrt(n) };
    }

    // A region [x0, y0, x1, y1] of a w x h frame in whole pixels, the whole
    // frame where none is given.
    function regionOf(region, w, h) {
        if (!Array.isArray(region) || (region.length !== 4)) {
            return [0, 0, w, h];
        }

        return [Math.max(0, Math.round(region[0])), Math.max(0, Math.round(region[1])), Math.min(w, Math.round(region[2])), Math.min(h, Math.round(region[3]))];
    }

    // A tracker of the box [x0, y0, x1, y1] in a w x h grey frame; first
    // holds the frame's region (the whole frame where none is given).
    function Tracker(first, w, h, box, region) {
        var g = regionOf(region, w, h), gw = g[2] - g[0], gh = g[3] - g[1];

        if (!first || (gw < 1) || (gh < 1) || (first.length !== gw * gh)) {
            throw failure('size', 'The pixels do not match the size.');
        }

        var x0 = Math.round(Math.max(0, Math.min(box[0], box[2]))), y0 = Math.round(Math.max(0, Math.min(box[1], box[3])));
        var x1 = Math.round(Math.min(w, Math.max(box[0], box[2]))), y1 = Math.round(Math.min(h, Math.max(box[1], box[3])));

        if ((x1 - x0 < MIN_SIDE) || (y1 - y0 < MIN_SIDE)) {
            throw failure('box', 'The box is too small.');
        }

        if ((x0 < g[0]) || (y0 < g[1]) || (x1 > g[2]) || (y1 > g[3])) {
            throw failure('box', 'The box is outside the pixels sent.');
        }

        this.w = w;
        this.h = h;
        this.tw = x1 - x0;
        this.th = y1 - y0;
        this.radius = Math.max(MIN_RADIUS, Math.ceil(Math.max(this.tw, this.th) / 2));
        this.first = centred(patch(first, gw, x0 - g[0], y0 - g[1], this.tw, this.th));

        if (this.first.norm < 1e-3 * Math.sqrt(this.tw * this.th) * 255 / 100) {
            throw failure('flat', 'The box holds nothing to follow.');
        }

        this.raw = patch(first, gw, x0 - g[0], y0 - g[1], this.tw, this.th);
        this.tpl = this.first;
        // The patch's top left (sub-pixel), its start, the last move a frame
        // and the frames since the last good one.
        this.x = x0;
        this.y = y0;
        this.x0 = x0;
        this.y0 = y0;
        this.vx = 0;
        this.vy = 0;
        this.gap = 1;
        this.weak = 0;
        this.lost = false;
        // Why it was lost: 'edge' or 'match'.
        this.why = null;
        // The centre of the box as drawn, which the points move from.
        this.cx = (box[0] + box[2]) / 2;
        this.cy = (box[1] + box[3]) / 2;
    }

    // The box in whole pixels.
    Tracker.prototype.box = function () {
        return [this.x0, this.y0, this.x0 + this.tw, this.y0 + this.th];
    };

    // The score of template t (centred) at whole pixel x, y of frame f with
    // its integrals I: -1..1, 0 where the window is flat.
    function zncc(t, tw, th, f, w, I, x, y) {
        var n = tw * th, W = I.W;
        var a = y * W + x, b = y * W + x + tw, c = (y + th) * W + x, d = (y + th) * W + x + tw;
        var sum = I.s[d] - I.s[b] - I.s[c] + I.s[a], sq = I.q[d] - I.q[b] - I.q[c] + I.q[a];
        var v = sq - sum * sum / n;

        if ((v <= 1e-6) || (t.norm <= 0)) {
            return 0;
        }

        var cross = 0, z = t.z, i, j, row, k;

        for (j = 0; j < th; j++) {
            row = (y + j) * w + x;
            k = j * tw;

            for (i = 0; i < tw; i++) {
                cross += z[k + i] * f[row + i];
            }
        }

        return cross / (t.norm * Math.sqrt(v));
    }

    // The current template's score at whole pixel x, y of frame f.
    Tracker.prototype.score = function (f, x, y) {
        return zncc(this.tpl, this.tw, this.th, f, this.w, integrals(f, this.w, this.h), x, y);
    };

    // The region [x0, y0, x1, y1] the next frame is searched in: the search
    // round the predicted place with the box, inside the frame. Reading only
    // this is enough, so a small thing can be read at a larger scale.
    Tracker.prototype.want = function () {
        var px = Math.round(this.x + this.vx * this.gap), py = Math.round(this.y + this.vy * this.gap), r = this.radius;

        return regionOf([px - r, py - r, px + r + this.tw, py + r + this.th], this.w, this.h);
    };

    // The next frame, or its region [x0, y0, x1, y1] (the whole frame where
    // none is given): { state: 'ok', x, y, score } (the centre), { state:
    // 'weak', score } or { state: 'lost', why }.
    Tracker.prototype.next = function (f, region) {
        if (this.lost) {
            return { state: 'lost', why: this.why };
        }

        var w = this.w, h = this.h, tw = this.tw, th = this.th, g = regionOf(region, w, h), gw = g[2] - g[0], gh = g[3] - g[1];

        if (!f || (gw < 1) || (gh < 1) || (f.length !== gw * gh)) {
            throw failure('size', 'The pixels do not match the size.');
        }

        var px = this.x + this.vx * this.gap, py = this.y + this.vy * this.gap;
        // The share of the predicted box inside the frame.
        var ix = Math.max(0, Math.min(w, px + tw) - Math.max(0, px)), iy = Math.max(0, Math.min(h, py + th) - Math.max(0, py));

        if ((ix * iy) / (tw * th) < INSIDE_MIN) {
            this.lost = true;
            this.why = 'edge';

            return { state: 'lost', why: 'edge' };
        }

        // The search, in the whole frame's pixels, kept inside the region.
        var r = this.radius, I = integrals(f, gw, gh);
        var lx = Math.max(g[0], Math.round(px) - r), hx = Math.min(g[2] - tw, Math.round(px) + r);
        var ly = Math.max(g[1], Math.round(py) - r), hy = Math.min(g[3] - th, Math.round(py) + r);
        var sw = hx - lx + 1, sh = hy - ly + 1;

        // The predicted box partly past the frame's edge: a miss there is
        // the thing leaving the picture.
        var leaving = (ix * iy) < (tw * th);

        if ((sw < 1) || (sh < 1)) {
            return this.miss(0, leaving);
        }

        var map = new Float32Array(sw * sh), best = -2, bx = 0, by = 0, x, y, s;

        for (y = ly; y <= hy; y++) {
            for (x = lx; x <= hx; x++) {
                s = zncc(this.tpl, tw, th, f, gw, I, x - g[0], y - g[1]);
                map[(y - ly) * sw + x - lx] = s;

                if (s > best) {
                    best = s;
                    bx = x;
                    by = y;
                }
            }
        }

        // A peak pressed against the frame's edge while the thing lies
        // beyond it is not trusted: the patch is no longer wholly seen.
        var pressed = ((bx === 0) && (px < 0)) || ((by === 0) && (py < 0)) || ((bx === w - tw) && (px > w - tw)) || ((by === h - th) && (py > h - th));

        if ((best < FLOOR) || pressed) {
            return this.miss(best, pressed || leaving);
        }

        var at = function (i, j) {
            return ((i < lx) || (i > hx) || (j < ly) || (j > hy)) ? null : map[(j - ly) * sw + i - lx];
        };
        var l = at(bx - 1, by), rr = at(bx + 1, by), u = at(bx, by - 1), d = at(bx, by + 1);
        var fx = bx + (((l !== null) && (rr !== null)) ? parabola(l, best, rr) : 0);
        var fy = by + (((u !== null) && (d !== null)) ? parabola(u, best, d) : 0);

        this.vx = (fx - this.x) / this.gap;
        this.vy = (fy - this.y) / this.gap;
        this.x = fx;
        this.y = fy;
        this.gap = 1;
        this.weak = 0;
        this.renew(f, gw, I, bx - g[0], by - g[1], best);

        return { state: 'ok', x: this.cx + fx - this.x0, y: this.cy + fy - this.y0, score: best };
    };

    // A weak frame: the search widens by the gap; five in a row are lost,
    // at the edge where the last was missed there.
    Tracker.prototype.miss = function (score, edge) {
        this.weak++;
        this.gap++;

        if (this.weak >= WEAK_RUN) {
            this.lost = true;
            this.why = edge ? 'edge' : 'match';

            return { state: 'lost', score: score, why: this.why };
        }

        return { state: 'weak', score: score };
    };

    // The template after a good frame: a small share of the found patch
    // mixed in, or the first template again where it has drifted. f is gw
    // wide, bx, by the patch's top left in it.
    Tracker.prototype.renew = function (f, gw, I, bx, by, best) {
        if (best < RENEW_MIN) {
            return;
        }

        if (zncc(this.first, this.tw, this.th, f, gw, I, bx, by) < DRIFT_MIN) {
            this.tpl = this.first;

            return;
        }

        var p = patch(f, gw, bx, by, this.tw, this.th), raw = this.raw, i;

        for (i = 0; i < raw.length; i++) {
            raw[i] = raw[i] * (1 - RENEW_SHARE) + p[i] * RENEW_SHARE;
        }

        this.tpl = centred(raw);
    };

    // Tracks the grey frames (the first holds the box) in one go: points
    // { i, x, y, score } for the good frames, the first at the box's centre,
    // and lost null or { at, last, why } (frame numbers).
    function track(frames, w, h, box) {
        var t = new Tracker(frames[0], w, h, box), points = [{ i: 0, x: t.cx, y: t.cy, score: 1 }], last = 0, i, r;

        for (i = 1; i < frames.length; i++) {
            r = t.next(frames[i]);

            if (r.state === 'ok') {
                points.push({ i: i, x: r.x, y: r.y, score: r.score });
                last = i;
            } else if (r.state === 'lost') {
                return { points: points, lost: { at: last + 1, last: last, why: r.why } };
            }
        }

        return { points: points, lost: null };
    }

    // The grey of an RGBA picture (Rec. 601 weights), a byte a pixel.
    function grey(rgba, w, h) {
        var g = new Uint8Array(w * h), i;

        for (i = 0; i < w * h; i++) {
            g[i] = (rgba[i * 4] * 77 + rgba[i * 4 + 1] * 150 + rgba[i * 4 + 2] * 29 + 128) >> 8;
        }

        return g;
    }

    // The region a message's frame covers: w x h from its at, [0, 0] where
    // it names none.
    function placed(m, w, h) {
        var at = Array.isArray(m.at) ? m.at : [0, 0], x = Math.round(Number(at[0]) || 0), y = Math.round(Number(at[1]) || 0);

        return [x, y, x + w, y + h];
    }

    // A message session (see the head of the file); post(answer).
    function Session(post) {
        this.post = post;
        this.tracker = null;
        this.lastT = null;
        this.firstBad = null;
    }

    Session.prototype.receive = function (m) {
        if (!m || ((m.type !== 'start') && (m.type !== 'frame') && (m.type !== 'stop'))) {
            return;
        }

        try {
            if (m.type === 'stop') {
                this.tracker = null;

                return;
            }

            if (m.type === 'start') {
                var w = m.width | 0, h = m.height | 0, f = new Uint8Array(m.frame || 0), full = Array.isArray(m.full) ? m.full : [w, h];

                this.tracker = null;

                if ((w < 1) || (h < 1) || (f.length !== w * h)) {
                    throw failure('size', 'The pixels do not match the size.');
                }

                if (!Array.isArray(m.box) || (m.box.length !== 4)) {
                    throw failure('box', 'There is no box.');
                }

                this.tracker = new Tracker(f, full[0] | 0, full[1] | 0, m.box, placed(m, w, h));
                this.lastT = m.t;
                this.firstBad = null;
                this.post({ type: 'ready', t: m.t, x: this.tracker.cx, y: this.tracker.cy, want: this.tracker.want() });

                return;
            }

            if (!this.tracker) {
                throw failure('order', 'Follow has not started.');
            }

            if (this.tracker.lost) {
                return;
            }

            var r = this.tracker.next(new Uint8Array(m.frame || 0), (m.width && m.height) ? placed(m, m.width | 0, m.height | 0) : null);

            if (r.state === 'ok') {
                this.lastT = m.t;
                this.firstBad = null;
                this.post({ type: 'point', t: m.t, x: r.x, y: r.y, score: r.score, want: this.tracker.want() });
            } else {
                if (this.firstBad === null) {
                    this.firstBad = m.t;
                }

                this.post((r.state === 'lost') ? { type: 'lost', t: this.firstBad, last: this.lastT, why: r.why } : { type: 'weak', t: m.t, score: r.score, want: this.tracker.want() });
            }
        } catch (err) {
            this.post({ type: 'error', code: (err && err.code) || 'error', message: String((err && err.message) || err) });
        }
    };

    Session.prototype.stop = function () {
        this.tracker = null;
    };

    // The session on the page: each message handled in a slice of its own
    // after the call returns, so the page keeps answering between frames.
    function PageSession(post) {
        this.inner = new Session(post);
        this.queue = [];
        this.timer = null;
    }

    PageSession.prototype.receive = function (m) {
        var self = this;

        this.queue.push(m);

        if (this.timer === null) {
            this.timer = setTimeout(function run() {
                self.timer = null;

                if (self.queue.length) {
                    self.inner.receive(self.queue.shift());
                }

                if (self.queue.length) {
                    self.timer = setTimeout(run, 0);
                }
            }, 0);
        }
    };

    PageSession.prototype.stop = function () {
        this.queue = [];

        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }

        this.inner.stop();
    };

    var api = {
        Tracker: Tracker,
        track: track,
        parabola: parabola,
        grey: grey,
        session: function (post) { return new Session(post); },
        pageSession: function (post) { return new PageSession(post); },
        FLOOR: FLOOR,
        WEAK_RUN: WEAK_RUN
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
        return;
    }

    // A worker: the messages above.
    if ((typeof root.importScripts === 'function') && (typeof root.document === 'undefined')) {
        var session = new Session(function (a) { root.postMessage(a); });

        root.onmessage = function (e) { session.receive(e.data || {}); };

        return;
    }

    root.PgImageTrack = api;
}((typeof self !== 'undefined') ? self : this));
