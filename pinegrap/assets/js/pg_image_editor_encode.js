/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the animation encoder. Frames drawn by the editor (RGBA
 * buffers) become a file: GIF89a with one palette of up to 255 colours from
 * sampled frames (median cut), optional Floyd-Steinberg dithering, a
 * transparent index when a sample has see-through pixels, only the changed
 * rectangle of each later opaque frame, its own LZW and the NETSCAPE2.0 loop
 * count.
 *
 * Runs as a worker (messages below), on the page where a site's policy
 * refuses the worker (window.PgImageEncode), and under Node for the tests
 * (module.exports): the same object in each.
 *
 * Messages in:  { type: 'begin', width, height, fps, loop, dither, count }
 *               { type: 'sample', data }   (an ArrayBuffer of RGBA)
 *               { type: 'estimate' }       -> { type: 'estimate', header, frame }
 *               { type: 'frame', data }    -> { type: 'framed', index }
 *               { type: 'end' }            -> { type: 'file', data, mime }
 * A failure answers { type: 'error', message }.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // Pixels taken from the samples to build the palette: enough for the
    // colours of a picture, few enough to sort quickly.
    var SAMPLE_PIXELS = 120000;

    // ---- palette ----

    // Median cut over the sampled pixels: up to max colours as a flat RGB
    // array; transparent true puts a see-through entry at index 0.
    function palette(samples, max, transparent) {
        var total = 0, pixels = [], i, s;

        max = Math.max(1, Math.min(transparent ? 255 : 256, max || 255));

        samples.forEach(function (rgba) { total += rgba.length / 4; });

        var step = Math.max(1, Math.floor(total / SAMPLE_PIXELS));

        samples.forEach(function (rgba) {
            for (i = 0; i < rgba.length; i += 4 * step) {
                if (rgba[i + 3] >= 128) {
                    pixels.push((rgba[i] << 16) | (rgba[i + 1] << 8) | rgba[i + 2]);
                }
            }
        });

        var boxes = pixels.length ? [pixels] : [], colours = [];

        while (boxes.length < max) {
            var best = -1, bestScore = 0, bestChannel = 0;

            for (s = 0; s < boxes.length; s++) {
                var r = range(boxes[s]), score = r.size * boxes[s].length;

                if ((boxes[s].length > 1) && (r.size > 0) && (score > bestScore)) {
                    best = s;
                    bestScore = score;
                    bestChannel = r.channel;
                }
            }

            if (best < 0) {
                break;
            }

            var box = boxes[best], shift = 16 - 8 * bestChannel;

            box.sort(function (a, b) { return ((a >> shift) & 255) - ((b >> shift) & 255); });

            var half = box.length >> 1;

            boxes.splice(best, 1, box.slice(0, half), box.slice(half));
        }

        if (transparent) {
            colours.push(0, 0, 0);
        }

        boxes.forEach(function (box) {
            var r = 0, g = 0, b = 0;

            box.forEach(function (c) {
                r += (c >> 16) & 255;
                g += (c >> 8) & 255;
                b += c & 255;
            });
            colours.push(Math.round(r / box.length), Math.round(g / box.length), Math.round(b / box.length));
        });

        if (colours.length < 6) {
            colours.push(0, 0, 0);
        }

        return { colors: colours, count: colours.length / 3, transparent: transparent ? 0 : -1 };
    }

    // The widest channel of a box (0 red, 1 green, 2 blue) and its spread.
    function range(box) {
        var lo = [255, 255, 255], hi = [0, 0, 0], c, k, v;

        for (var i = 0; i < box.length; i++) {
            c = box[i];

            for (k = 0; k < 3; k++) {
                v = (c >> (16 - 8 * k)) & 255;

                if (v < lo[k]) {
                    lo[k] = v;
                }

                if (v > hi[k]) {
                    hi[k] = v;
                }
            }
        }

        var spans = [hi[0] - lo[0], hi[1] - lo[1], hi[2] - lo[2]];
        var channel = spans.indexOf(Math.max(spans[0], spans[1], spans[2]));

        return { channel: channel, size: spans[channel] };
    }

    // The nearest palette entry of a colour, remembered per 6-bit cell.
    function nearest(pal) {
        var cache = new Int16Array(262144).fill(-1), c = pal.colors, from = (pal.transparent >= 0) ? 1 : 0;

        return function (r, g, b) {
            var key = ((r >> 2) << 12) | ((g >> 2) << 6) | (b >> 2), hit = cache[key];

            if (hit >= 0) {
                return hit;
            }

            var best = from, bestD = Infinity;

            for (var i = from; i < pal.count; i++) {
                var dr = c[3 * i] - r, dg = c[3 * i + 1] - g, db = c[3 * i + 2] - b, d = 2 * dr * dr + 4 * dg * dg + 3 * db * db;

                if (d < bestD) {
                    bestD = d;
                    best = i;
                }
            }

            cache[key] = best;

            return best;
        };
    }

    // RGBA to palette indices; dither spreads each pixel's error to its
    // neighbours (Floyd-Steinberg). A pixel under half opacity takes the
    // transparent index where the palette has one.
    function quantize(rgba, w, h, pal, dither, find) {
        var out = new Uint8Array(w * h), c = pal.colors, x, y, i, p;
        var errs = dither ? [new Float32Array((w + 2) * 3), new Float32Array((w + 2) * 3)] : null;

        find = find || nearest(pal);

        for (y = 0; y < h; y++) {
            var cur = errs ? errs[y & 1] : null, next = errs ? errs[(y + 1) & 1] : null;

            if (next) {
                next.fill(0);
            }

            for (x = 0; x < w; x++) {
                p = y * w + x;
                i = 4 * p;

                if ((pal.transparent >= 0) && (rgba[i + 3] < 128)) {
                    out[p] = pal.transparent;
                    continue;
                }

                var r = rgba[i], g = rgba[i + 1], b = rgba[i + 2], e = 3 * (x + 1);

                if (cur) {
                    r = clampByte(r + cur[e]);
                    g = clampByte(g + cur[e + 1]);
                    b = clampByte(b + cur[e + 2]);
                }

                var k = find(r, g, b);

                out[p] = k;

                if (cur) {
                    spread(cur, next, e, r - c[3 * k], g - c[3 * k + 1], b - c[3 * k + 2]);
                }
            }
        }

        return out;
    }

    function spread(cur, next, e, dr, dg, db) {
        var d = [dr, dg, db];

        for (var k = 0; k < 3; k++) {
            cur[e + 3 + k] += d[k] * 7 / 16;
            next[e - 3 + k] += d[k] * 3 / 16;
            next[e + k] += d[k] * 5 / 16;
            next[e + 3 + k] += d[k] / 16;
        }
    }

    function clampByte(v) {
        return (v < 0) ? 0 : ((v > 255) ? 255 : Math.round(v));
    }

    // ---- frames ----

    // The rectangle where two index frames differ, or null when equal.
    function changed(prev, cur, w, h) {
        var x0 = w, y0 = h, x1 = -1, y1 = -1;

        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                if (prev[y * w + x] !== cur[y * w + x]) {
                    if (x < x0) { x0 = x; }
                    if (x > x1) { x1 = x; }
                    if (y < y0) { y0 = y; }
                    y1 = y;
                }
            }
        }

        return (x1 < 0) ? null : { x: x0, y: y0, w: x1 - x0 + 1, h: y1 - y0 + 1 };
    }

    // The delay of frame i in hundredths of a second at fps, the rounding
    // carried to the next frame; never under 2 (browsers slow anything
    // shorter to a tenth).
    function delayAt(i, fps) {
        var each = 100 / fps, d = Math.round((i + 1) * each) - Math.round(i * each);

        return Math.max(2, d);
    }

    // The NETSCAPE2.0 count for plays (0 forever): the count is the repeats
    // after the first play; null when it plays once (no extension).
    function loopCount(plays) {
        plays = Math.max(0, Math.round(Number(plays) || 0));

        return (plays === 0) ? 0 : ((plays === 1) ? null : Math.min(65535, plays - 1));
    }

    // ---- LZW ----

    // GIF's LZW of the indices with the given minimum code size: the code
    // stream as bytes, before its sub-blocks.
    function lzw(indices, minSize) {
        var clear = 1 << minSize, eoi = clear + 1, out = [], acc = 0, bits = 0;
        var size, next, dict = new Map();

        function emit(code) {
            acc |= code << bits;
            bits += size;

            while (bits >= 8) {
                out.push(acc & 255);
                acc >>>= 8;
                bits -= 8;
            }
        }

        function reset() {
            dict.clear();
            size = minSize + 1;
            next = eoi + 1;
        }

        reset();
        emit(clear);

        if (!indices.length) {
            emit(eoi);

            return finish();
        }

        var prefix = indices[0];

        for (var i = 1; i < indices.length; i++) {
            var k = indices[i], key = prefix * 256 + k, hit = dict.get(key);

            if (hit !== undefined) {
                prefix = hit;
                continue;
            }

            emit(prefix);

            if (next < 4096) {
                dict.set(key, next++);

                if ((next > (1 << size)) && (size < 12)) {
                    size++;
                }
            } else {
                emit(clear);
                reset();
            }

            prefix = k;
        }

        emit(prefix);
        emit(eoi);

        return finish();

        function finish() {
            if (bits > 0) {
                out.push(acc & 255);
            }

            return out;
        }
    }

    // ---- the file ----

    function Bytes() {
        this.parts = [];
        this.list = [];
        this.length = 0;
    }

    Bytes.prototype.byte = function (b) {
        this.list.push(b & 255);
        this.length++;

        if (this.list.length >= 65536) {
            this.flush();
        }
    };

    Bytes.prototype.word = function (v) {
        this.byte(v & 255);
        this.byte((v >> 8) & 255);
    };

    Bytes.prototype.text = function (s) {
        for (var i = 0; i < s.length; i++) {
            this.byte(s.charCodeAt(i));
        }
    };

    Bytes.prototype.flush = function () {
        if (this.list.length) {
            this.parts.push(Uint8Array.from(this.list));
            this.list = [];
        }
    };

    Bytes.prototype.bytes = function () {
        this.flush();

        var all = new Uint8Array(this.length), at = 0;

        this.parts.forEach(function (p) {
            all.set(p, at);
            at += p.length;
        });

        return all;
    };

    // The table size of a palette as GIF's exponent (2^(n+1) entries).
    function tableBits(count) {
        var n = 1;

        while ((1 << n) < count) {
            n++;
        }

        return n;
    }

    // The GIF writer: begin(), frame(indices, rect, delay, disposal), end().
    function GifWriter(w, h, pal, plays) {
        this.w = w;
        this.h = h;
        this.pal = pal;
        this.plays = plays;
        this.out = new Bytes();
    }

    GifWriter.prototype.begin = function () {
        var o = this.out, bits = tableBits(this.pal.count), loop = loopCount(this.plays);

        o.text('GIF89a');
        o.word(this.w);
        o.word(this.h);
        o.byte(0x80 | (7 << 4) | (bits - 1));
        o.byte(0);
        o.byte(0);

        for (var i = 0; i < (1 << bits); i++) {
            o.byte(this.pal.colors[3 * i] || 0);
            o.byte(this.pal.colors[3 * i + 1] || 0);
            o.byte(this.pal.colors[3 * i + 2] || 0);
        }

        if (loop !== null) {
            o.byte(0x21);
            o.byte(0xFF);
            o.byte(11);
            o.text('NETSCAPE2.0');
            o.byte(3);
            o.byte(1);
            o.word(loop);
            o.byte(0);
        }
    };

    // indices: the whole frame; rect: the part written (null: all).
    GifWriter.prototype.frame = function (indices, rect, delay, disposal) {
        var o = this.out, r = rect || { x: 0, y: 0, w: this.w, h: this.h }, trans = this.pal.transparent;
        var part = indices, minSize = Math.max(2, tableBits(this.pal.count));

        if (rect) {
            part = new Uint8Array(r.w * r.h);

            for (var y = 0; y < r.h; y++) {
                part.set(indices.subarray((r.y + y) * this.w + r.x, (r.y + y) * this.w + r.x + r.w), y * r.w);
            }
        }

        o.byte(0x21);
        o.byte(0xF9);
        o.byte(4);
        o.byte(((disposal || 0) << 2) | ((trans >= 0) ? 1 : 0));
        o.word(delay);
        o.byte((trans >= 0) ? trans : 0);
        o.byte(0);

        o.byte(0x2C);
        o.word(r.x);
        o.word(r.y);
        o.word(r.w);
        o.word(r.h);
        o.byte(0);

        o.byte(minSize);

        var data = lzw(part, minSize);

        for (var i = 0; i < data.length; i += 255) {
            var n = Math.min(255, data.length - i);

            o.byte(n);

            for (var j = 0; j < n; j++) {
                o.byte(data[i + j]);
            }
        }

        o.byte(0);
    };

    GifWriter.prototype.end = function () {
        this.out.byte(0x3B);

        return this.out.bytes();
    };

    // ---- the encoder a page or the worker drives ----

    // opts: width, height, fps, loop (plays, 0 forever), dither.
    function Encoder(opts) {
        this.w = Math.max(1, opts.width | 0);
        this.h = Math.max(1, opts.height | 0);
        this.fps = Math.max(1, Number(opts.fps) || 15);
        this.loop = opts.loop || 0;
        this.dither = !!opts.dither;
        this.samples = [];
        this.pal = null;
        this.find = null;
        this.writer = null;
        this.prev = null;
        this.count = 0;
    }

    Encoder.prototype.sample = function (rgba) {
        this.samples.push(rgba);
    };

    Encoder.prototype.ensurePalette = function () {
        if (this.pal) {
            return;
        }

        var see = this.samples.some(function (rgba) {
            for (var i = 3; i < rgba.length; i += 4) {
                if (rgba[i] < 128) {
                    return true;
                }
            }

            return false;
        });

        this.pal = palette(this.samples, 255, see);
        this.find = nearest(this.pal);
        this.samples = [];
    };

    // The size the file will be about: its header and an average frame,
    // each sample encoded whole (later frames keep only what changed, so
    // this errs high).
    Encoder.prototype.estimate = function () {
        var samples = this.samples.slice(), self = this, sum = 0;

        this.ensurePalette();

        var head = new GifWriter(this.w, this.h, this.pal, this.loop);

        head.begin();

        var header = head.out.length;

        samples.forEach(function (rgba) {
            var one = new GifWriter(self.w, self.h, self.pal, self.loop);

            one.frame(quantize(rgba, self.w, self.h, self.pal, self.dither, self.find), null, 2, 0);
            sum += one.out.length;
        });

        return { header: header, frame: samples.length ? Math.round(sum / samples.length) : 0 };
    };

    Encoder.prototype.frame = function (rgba) {
        this.ensurePalette();

        if (!this.writer) {
            this.writer = new GifWriter(this.w, this.h, this.pal, this.loop);
            this.writer.begin();
        }

        var idx = quantize(rgba, this.w, this.h, this.pal, this.dither, this.find), delay = delayAt(this.count, this.fps);

        // A see-through frame is drawn whole after clearing (disposal 2):
        // what turned transparent would else keep the frame before it.
        if (this.pal.transparent >= 0) {
            this.writer.frame(idx, null, delay, 2);
        } else if (!this.prev) {
            this.writer.frame(idx, null, delay, 1);
        } else {
            this.writer.frame(idx, changed(this.prev, idx, this.w, this.h) || { x: 0, y: 0, w: 1, h: 1 }, delay, 1);
        }

        this.prev = idx;
        this.count++;
    };

    Encoder.prototype.end = function () {
        if (!this.writer) {
            this.writer = new GifWriter(this.w, this.h, this.pal || palette([], 1, false), this.loop);
            this.writer.begin();
        }

        return this.writer.end();
    };

    var api = {
        palette: palette, nearest: nearest, quantize: quantize, changed: changed, delayAt: delayAt, loopCount: loopCount,
        lzw: lzw, GifWriter: GifWriter, Encoder: Encoder,
        create: function (opts) { return new Encoder(opts); },
        MIME: { gif: 'image/gif' }
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
        return;
    }

    // A worker: the messages above.
    if ((typeof root.importScripts === 'function') && (typeof root.document === 'undefined')) {
        var enc = null;

        root.onmessage = function (e) {
            var m = e.data || {};

            try {
                if (m.type === 'begin') {
                    enc = new Encoder(m);
                } else if (m.type === 'sample') {
                    enc.sample(new Uint8ClampedArray(m.data));
                } else if (m.type === 'estimate') {
                    root.postMessage(Object.assign({ type: 'estimate' }, enc.estimate()));
                } else if (m.type === 'frame') {
                    enc.frame(new Uint8ClampedArray(m.data));
                    root.postMessage({ type: 'framed', index: enc.count - 1 });
                } else if (m.type === 'end') {
                    var bytes = enc.end();

                    root.postMessage({ type: 'file', data: bytes.buffer, mime: api.MIME.gif }, [bytes.buffer]);
                    enc = null;
                }
            } catch (err) {
                root.postMessage({ type: 'error', message: String((err && err.message) || err) });
            }
        };

        return;
    }

    root.PgImageEncode = api;
}((typeof self !== 'undefined') ? self : this));
