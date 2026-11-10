/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the animation encoder. Frames drawn by the editor (RGBA
 * buffers) become a file: GIF89a with one palette of up to 255 colours from
 * sampled frames (median cut), optional Floyd-Steinberg dithering, a
 * transparent index when a sample has see-through pixels, only the changed
 * rectangle of each later opaque frame, its own LZW and the NETSCAPE2.0 loop
 * count; APNG from its own filtered rows through the platform's deflate
 * stream (CompressionStream) in acTL, fcTL and fdAT; animated WebP from the
 * browser's still WebP frames (OffscreenCanvas) in a VP8X, ANIM and ANMF
 * RIFF. APNG and WebP keep each later frame's changed rectangle and merge a
 * frame that repeats the one before into its delay. Video through the
 * platform's VideoEncoder (WebCodecs): H.264 in an MP4 with its index first,
 * or VP9 in a WebM with its cues first, both from their own muxers. A
 * video's sound, mixed on the page, goes through the platform's
 * AudioEncoder (AAC for MP4, Opus for WebM) and is interleaved with the
 * frames in the same muxers.
 *
 * Runs as a worker (messages below), on the page where a site's policy
 * refuses the worker (window.PgImageEncode), and under Node for the tests
 * (module.exports): the same object in each.
 *
 * Messages in:  { type: 'begin', format, width, height, fps, loop, dither, count,
 *                 bitrate, codecs, audio }   (the last three for video;
 *                 audio { codec, kind, rate, channels, bitrate } or none)
 *               { type: 'sample', data }   (an ArrayBuffer of RGBA)
 *               { type: 'estimate' }       -> { type: 'estimate', header, frame }
 *               { type: 'frame', data }    -> { type: 'framed', index }
 *               { type: 'audio', data }    (a Float32 ArrayBuffer per channel)
 *                                          -> { type: 'audioed' }
 *               { type: 'end' }            -> { type: 'file', data, mime }
 * A failure answers { type: 'error', message }.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (root) {
    'use strict';

    // Pixels taken from the samples to build the palette: enough for the
    // colours of a picture, few enough to sort quickly.
    var SAMPLE_PIXELS = 120000;
    var MIME = { gif: 'image/gif', apng: 'image/png', webp: 'image/webp', mp4: 'video/mp4', webm: 'video/webm' };

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

    // ---- APNG ----

    var CRC_TABLE = null;

    function crc32(bytes, crc) {
        if (!CRC_TABLE) {
            CRC_TABLE = new Uint32Array(256);

            for (var n = 0; n < 256; n++) {
                var c = n;

                for (var k = 0; k < 8; k++) {
                    c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
                }

                CRC_TABLE[n] = c >>> 0;
            }
        }

        crc = (crc === undefined) ? 0xFFFFFFFF : crc;

        for (var i = 0; i < bytes.length; i++) {
            crc = CRC_TABLE[(crc ^ bytes[i]) & 255] ^ (crc >>> 8);
        }

        return crc;
    }

    function u32(v) {
        return [(v >>> 24) & 255, (v >>> 16) & 255, (v >>> 8) & 255, v & 255];
    }

    function u16(v) {
        return [(v >> 8) & 255, v & 255];
    }

    // One PNG chunk: length, type, data and the CRC of type and data.
    function pngChunk(type, data) {
        var head = new Uint8Array(8), out;

        head.set(u32(data.length), 0);

        for (var i = 0; i < 4; i++) {
            head[4 + i] = type.charCodeAt(i);
        }

        out = new Uint8Array(12 + data.length);
        out.set(head, 0);
        out.set(data, 8);
        out.set(u32((crc32(data, crc32(head.subarray(4))) ^ 0xFFFFFFFF) >>> 0), 8 + data.length);

        return out;
    }

    // The zlib stream of bytes through the platform's deflate.
    function deflate(bytes) {
        var cs = new CompressionStream('deflate'), w = cs.writable.getWriter();

        w.write(bytes);
        w.close();

        return new Response(cs.readable).arrayBuffer().then(function (b) { return new Uint8Array(b); });
    }

    // The rows of rect r of an RGBA frame with ch channels (3 or 4), each
    // behind the filter (none, sub, up, average, Paeth) whose output has the
    // smallest sum of magnitudes.
    function pngRows(rgba, w, r, ch) {
        var line = r.w * ch, out = new Uint8Array((line + 1) * r.h), prev = new Uint8Array(line), cur = new Uint8Array(line);
        var tries = [0, 1, 2, 3, 4].map(function () { return new Uint8Array(line); });

        for (var y = 0; y < r.h; y++) {
            var src = 4 * ((r.y + y) * w + r.x), x, k, best = 0, bestSum = Infinity;

            for (x = 0; x < r.w; x++) {
                for (k = 0; k < ch; k++) {
                    cur[x * ch + k] = rgba[src + 4 * x + k];
                }
            }

            for (var f = 0; f < 5; f++) {
                var t = tries[f], sum = 0;

                for (x = 0; x < line; x++) {
                    var a = (x >= ch) ? cur[x - ch] : 0, b = prev[x], c = (x >= ch) ? prev[x - ch] : 0, v = cur[x];

                    if (f === 1) {
                        v -= a;
                    } else if (f === 2) {
                        v -= b;
                    } else if (f === 3) {
                        v -= (a + b) >> 1;
                    } else if (f === 4) {
                        var p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c);

                        v -= (pa <= pb && pa <= pc) ? a : ((pb <= pc) ? b : c);
                    }

                    t[x] = v & 255;
                    sum += (t[x] < 128) ? t[x] : 256 - t[x];
                }

                if (sum < bestSum) {
                    bestSum = sum;
                    best = f;
                }
            }

            out[y * (line + 1)] = best;
            out.set(tries[best], y * (line + 1) + 1);

            var swap = prev;

            prev = cur;
            cur = swap;
        }

        return out;
    }

    // The APNG writer: frame(rgba, rect) answers a promise; more(delay)
    // lengthens the last frame; end() the file. Frames replace their
    // rectangle (blend source) and stay (dispose none).
    function ApngWriter(w, h, alpha, fps, plays) {
        this.w = w;
        this.h = h;
        this.ch = alpha ? 4 : 3;
        this.fps = fps;
        this.plays = Math.max(0, Math.round(Number(plays) || 0));
        this.frames = [];
    }

    ApngWriter.prototype.frame = function (rgba, rect) {
        var self = this, r = rect || { x: 0, y: 0, w: this.w, h: this.h }, item = { r: r, delay: 1, data: null };

        this.frames.push(item);

        return deflate(pngRows(rgba, this.w, r, this.ch)).then(function (z) {
            item.data = z;

            return 38 + 12 + z.length + ((self.frames.length > 1) ? 4 : 0);
        });
    };

    ApngWriter.prototype.more = function () {
        this.frames[this.frames.length - 1].delay++;
    };

    ApngWriter.prototype.head = function (count) {
        var ihdr = [].concat(u32(this.w), u32(this.h), [8, (this.ch === 4) ? 6 : 2, 0, 0, 0]);

        return [
            Uint8Array.from([0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A]),
            pngChunk('IHDR', Uint8Array.from(ihdr)),
            pngChunk('acTL', Uint8Array.from([].concat(u32(count), u32(this.plays))))
        ];
    };

    ApngWriter.prototype.end = function () {
        var self = this, parts = this.head(Math.max(1, this.frames.length)), seq = 0;

        this.frames.forEach(function (f, i) {
            var fc = [].concat(u32(seq++), u32(f.r.w), u32(f.r.h), u32(f.r.x), u32(f.r.y), u16(f.delay), u16(self.fps), [0, 0]);

            parts.push(pngChunk('fcTL', Uint8Array.from(fc)));

            if (i === 0) {
                parts.push(pngChunk('IDAT', f.data));
            } else {
                var d = new Uint8Array(4 + f.data.length);

                d.set(u32(seq++), 0);
                d.set(f.data, 4);
                parts.push(pngChunk('fdAT', d));
            }
        });

        parts.push(pngChunk('IEND', new Uint8Array(0)));

        return join(parts);
    };

    function join(parts) {
        var n = 0, at = 0, all;

        parts.forEach(function (p) { n += p.length; });
        all = new Uint8Array(n);
        parts.forEach(function (p) {
            all.set(p, at);
            at += p.length;
        });

        return all;
    }

    // ---- animated WebP ----

    var FRAME_CHUNKS = ['ALPH', 'VP8 ', 'VP8L'];

    // The chunks of a RIFF WEBP file as { type, data }.
    function riffChunks(bytes) {
        var list = [], at = 12, tag = function (o) { return String.fromCharCode(bytes[o], bytes[o + 1], bytes[o + 2], bytes[o + 3]); };

        if ((bytes.length < 12) || (tag(0) !== 'RIFF') || (tag(8) !== 'WEBP')) {
            throw new Error('not a WebP file');
        }

        while (at + 8 <= bytes.length) {
            var n = (bytes[at + 4] | (bytes[at + 5] << 8) | (bytes[at + 6] << 16) | (bytes[at + 7] << 24)) >>> 0;

            list.push({ type: tag(at), data: bytes.subarray(at + 8, at + 8 + n) });
            at += 8 + n + (n & 1);
        }

        return list;
    }

    function le(v, n) {
        var out = [];

        for (var i = 0; i < n; i++) {
            out.push((v >>> (8 * i)) & 255);
        }

        return out;
    }

    // A RIFF chunk, padded to an even length.
    function riffChunk(type, parts) {
        var n = 0, list = [];

        parts.forEach(function (p) { n += p.length; });

        for (var i = 0; i < 4; i++) {
            list.push(type.charCodeAt(i));
        }

        return join([Uint8Array.from(list.concat(le(n, 4)))].concat(parts, (n & 1) ? [new Uint8Array(1)] : []));
    }

    // The milliseconds of frame i at fps, the rounding carried.
    function msAt(i, fps) {
        return Math.round((i + 1) * 1000 / fps) - Math.round(i * 1000 / fps);
    }

    // The browser's still WebP of an RGBA picture, or a refusal where it
    // has none (Safari answers PNG).
    function canvasWebp(rgba, w, h) {
        if (typeof OffscreenCanvas !== 'function') {
            return Promise.reject(new Error('no WebP encoder'));
        }

        var c = new OffscreenCanvas(w, h);

        c.getContext('2d').putImageData(new ImageData(new Uint8ClampedArray(rgba), w, h), 0, 0);

        return c.convertToBlob({ type: 'image/webp', quality: 0.9 }).then(function (blob) {
            if (blob.type !== 'image/webp') {
                throw new Error('no WebP encoder');
            }

            return blob.arrayBuffer();
        }).then(function (b) { return new Uint8Array(b); });
    }

    // The WebP writer: frame(rgba, rect, ms) answers a promise; more(ms)
    // lengthens the last frame; end() the file. Frames replace their
    // rectangle (no blending) and stay (no disposal).
    function WebpWriter(w, h, plays, still) {
        this.w = w;
        this.h = h;
        this.plays = Math.min(65535, Math.max(0, Math.round(Number(plays) || 0)));
        this.still = still || canvasWebp;
        this.frames = [];
        this.alpha = false;
    }

    WebpWriter.prototype.frame = function (rgba, rect, ms) {
        var self = this, r = rect || { x: 0, y: 0, w: this.w, h: this.h }, item = { r: r, ms: ms, data: null };

        this.frames.push(item);

        return this.still(crop(rgba, this.w, r), r.w, r.h).then(function (bytes) {
            // A frame holds only its picture: the still's VP8X, ICCP (Chrome
            // adds one) or EXIF would make the file unreadable.
            item.data = riffChunks(bytes).filter(function (c) { return FRAME_CHUNKS.indexOf(c.type) >= 0; }).map(function (c) {
                if (c.type === 'ALPH' || c.type === 'VP8L') {
                    self.alpha = true;
                }

                return riffChunk(c.type, [c.data]);
            });

            return 24 + item.data.reduce(function (n, p) { return n + p.length; }, 0);
        });
    };

    WebpWriter.prototype.more = function (ms) {
        this.frames[this.frames.length - 1].ms += ms;
    };

    WebpWriter.prototype.end = function () {
        var self = this, flags = 0x02 | (this.alpha ? 0x10 : 0);
        var parts = [
            riffChunk('VP8X', [Uint8Array.from([flags, 0, 0, 0].concat(le(this.w - 1, 3), le(this.h - 1, 3)))]),
            riffChunk('ANIM', [Uint8Array.from(le(0, 4).concat(le(this.plays, 2)))])
        ];

        this.frames.forEach(function (f) {
            var head = le(f.r.x >> 1, 3).concat(le(f.r.y >> 1, 3), le(f.r.w - 1, 3), le(f.r.h - 1, 3), le(Math.min(0xFFFFFF, f.ms), 3), [0x02]);

            parts.push(riffChunk('ANMF', [Uint8Array.from(head)].concat(f.data)));
        });

        var body = join(parts), out = new Uint8Array(12 + body.length);

        out.set([0x52, 0x49, 0x46, 0x46].concat(le(4 + body.length, 4), [0x57, 0x45, 0x42, 0x50]), 0);
        out.set(body, 12);

        return out;
    };

    // Rect r of an RGBA frame w wide as its own RGBA.
    function crop(rgba, w, r) {
        if ((r.x === 0) && (r.y === 0) && (r.w === w) && (r.w * r.h * 4 === rgba.length)) {
            return rgba;
        }

        var out = new Uint8ClampedArray(r.w * r.h * 4);

        for (var y = 0; y < r.h; y++) {
            out.set(rgba.subarray(4 * ((r.y + y) * w + r.x), 4 * ((r.y + y) * w + r.x + r.w)), 4 * y * r.w);
        }

        return out;
    }

    // The pixels of an RGBA frame as one number each, to compare.
    function words(rgba) {
        return new Uint32Array(rgba.buffer, rgba.byteOffset, rgba.length >> 2);
    }

    function hasSeeThrough(samples) {
        return samples.some(function (rgba) {
            for (var i = 3; i < rgba.length; i += 4) {
                if (rgba[i] < 128) {
                    return true;
                }
            }

            return false;
        });
    }

    // APNG or WebP from full-colour frames: the first whole, each later one
    // its changed rectangle (WebP's at even offsets), a repeat merged.
    function TrueEncoder(opts, format) {
        this.format = format;
        this.mime = MIME[format];
        this.w = Math.max(1, opts.width | 0);
        this.h = Math.max(1, opts.height | 0);
        this.fps = Math.max(1, Math.round(Number(opts.fps) || 15));
        this.loop = opts.loop || 0;
        this.still = opts.webp || null;
        this.samples = [];
        this.writer = null;
        this.prev = null;
        this.count = 0;
    }

    TrueEncoder.prototype.sample = function (rgba) {
        this.samples.push(rgba);
    };

    TrueEncoder.prototype.make = function () {
        return (this.format === 'webp') ? new WebpWriter(this.w, this.h, this.loop, this.still) : new ApngWriter(this.w, this.h, hasSeeThrough(this.samples), this.fps, this.loop);
    };

    // The header and an average sample written whole.
    TrueEncoder.prototype.estimate = function () {
        var self = this, w = this.make(), sum = 0, header = (this.format === 'webp') ? 12 + 18 + 14 : 8 + 25 + 20 + 12, p = Promise.resolve();

        this.samples.forEach(function (rgba) {
            p = p.then(function () { return w.frame(rgba, null, 0); }).then(function (n) { sum += n; });
        });

        return p.then(function () { return { header: header, frame: self.samples.length ? Math.round(sum / self.samples.length) : 0 }; });
    };

    TrueEncoder.prototype.frame = function (rgba) {
        var i = this.count, ms = msAt(i, this.fps), r = null, cur = words(rgba);

        if (!this.writer) {
            this.writer = this.make();
            this.samples = [];
        }

        this.count++;

        if (this.prev) {
            r = changed(this.prev, cur, this.w, this.h);

            if (!r) {
                this.writer.more(ms);
                return Promise.resolve();
            }

            if (this.format === 'webp') {
                r.w += r.x & 1;
                r.h += r.y & 1;
                r.x -= r.x & 1;
                r.y -= r.y & 1;
            }
        }

        this.prev = new Uint32Array(cur);

        return this.writer.frame(rgba, r, ms);
    };

    TrueEncoder.prototype.end = function () {
        if (!this.writer) {
            this.writer = this.make();
        }

        return this.writer.end();
    };

    // ---- video: MP4 and WebM muxers, the WebCodecs encoder ----

    // An ISO box: size, type, then the parts (byte lists or Uint8Arrays).
    function box(type, parts) {
        var body = join(parts), out = new Uint8Array(8 + body.length);

        out.set(u32(8 + body.length), 0);
        out.set([type.charCodeAt(0), type.charCodeAt(1), type.charCodeAt(2), type.charCodeAt(3)], 4);
        out.set(body, 8);

        return out;
    }

    // A full box: version 0 and flags before the parts.
    function fullBox(type, flags, parts) {
        return box(type, [[0, (flags >> 16) & 255, (flags >> 8) & 255, flags & 255]].concat(parts));
    }

    function zeros(n) {
        return new Uint8Array(n);
    }

    // The unity matrix of mvhd and tkhd.
    var MATRIX = [].concat(u32(0x10000), u32(0), u32(0), u32(0), u32(0x10000), u32(0), u32(0), u32(0), u32(0x40000000));

    function ascii(s) {
        return s.split('').map(function (c) { return c.charCodeAt(0); });
    }

    // Equal neighbours as [count, value] runs (stts, stsc).
    function runs(list) {
        var out = [];

        list.forEach(function (v) {
            var last = out[out.length - 1];

            if (last && (last[1] === v)) {
                last[0]++;
            } else {
                out.push([1, v]);
            }
        });

        return out;
    }

    // The order of the chunks in mdat: each track's samples (times in
    // seconds) cut every span seconds, the tracks' chunks of one span side
    // by side, so a player reading on finds picture and sound together.
    // Answers [{ track, from, count }] and fills each track's chunks.
    function interleave(tracks, span) {
        var order = [], next = tracks.map(function () { return 0; }), k;

        tracks.forEach(function (tr) { tr.chunks = []; });

        for (k = span; ; k += span) {
            tracks.forEach(function (tr, n) {
                var first = next[n];

                while ((next[n] < tr.times.length) && (tr.times[next[n]] < k)) {
                    next[n]++;
                }

                if (next[n] > first) {
                    tr.chunks.push(next[n] - first);
                    order.push({ track: n, from: first, count: next[n] - first });
                }
            });

            if (tracks.every(function (tr, n) { return next[n] >= tr.times.length; })) {
                return order;
            }
        }
    }

    // An MPEG-4 descriptor: tag, a one-byte size, the body.
    function descriptor(tag, parts) {
        var body = join(parts);

        return join([[tag, body.length], body]);
    }

    // AAC-LC's AudioSpecificConfig for a rate and channel count, where the
    // encoder hands none.
    function aacConfig(rate, channels) {
        var idx = [96000, 88200, 64000, 48000, 44100, 32000, 24000, 22050, 16000, 12000, 11025, 8000].indexOf(rate), bits;

        bits = (2 << 11) | ((idx < 0 ? 3 : idx) << 7) | ((channels & 15) << 3);

        return [bits >> 8, bits & 255];
    }

    // An MP4 of H.264 samples and, with audio, its AAC track: the index
    // (moov) before the data so a player starts before the whole file is
    // in, a constant frame duration. Without sound one chunk; with it a
    // chunk of each track per second, side by side. samples: { data, key };
    // avcc: the decoder configuration record WebCodecs hands with the first
    // chunk; audio: { rate, channels, bitrate, chunks: { data, ts, dur }
    // (microseconds), desc } or none.
    function mp4File(w, h, fps, samples, avcc, audio) {
        var scale = 90000, delta = Math.round(scale / fps), name = ascii('Pinegrap');
        var sound = (audio && audio.chunks && audio.chunks.length) ? audio : null;
        var avc1 = box('avc1', [zeros(6), u16(1), zeros(16), u16(w), u16(h), u32(0x480000), u32(0x480000), u32(0), u16(1),
            [name.length].concat(name), zeros(31 - name.length), u16(0x18), [255, 255], box('avcC', [avcc])]);
        var tracks = [{
            id: 1, scale: scale, sound: false, entry: avc1,
            samples: samples, times: samples.map(function (s, i) { return i / fps; }), durs: samples.map(function () { return delta; })
        }];

        if (sound) {
            var asc = (sound.desc && sound.desc.length) ? sound.desc : aacConfig(sound.rate, sound.channels);
            var esds = fullBox('esds', 0, [descriptor(3, [u16(0), [0],
                descriptor(4, [[0x40, 0x15, 0, 0, 0], u32(sound.bitrate), u32(sound.bitrate), descriptor(5, [asc])]),
                descriptor(6, [[2]])])]);

            tracks.push({
                id: 2, scale: sound.rate, sound: true,
                entry: box('mp4a', [zeros(6), u16(1), zeros(8), u16(sound.channels), u16(16), zeros(4), u32(sound.rate * 65536), esds]),
                samples: sound.chunks, times: sound.chunks.map(function (c) { return c.ts / 1e6; }),
                durs: sound.chunks.map(function (c) { return Math.max(1, Math.round(c.dur * sound.rate / 1e6)); })
            });
        }

        var order = interleave(tracks, sound ? 1 : Infinity), total = 0;

        tracks.forEach(function (tr) {
            tr.dur = tr.durs.reduce(function (a, b) { return a + b; }, 0);
            tr.ms = Math.round(tr.dur * 1000 / tr.scale);
            total = Math.max(total, tr.ms);
        });

        function trak(tr, offsets) {
            var sizes = [], keys = [], stts = runs(tr.durs), stsc = [], at = 0;

            tr.samples.forEach(function (s, i) {
                sizes = sizes.concat(u32(s.data.length));

                if (!tr.sound && s.key) {
                    keys = keys.concat(u32(i + 1));
                }
            });

            // A row per run of chunks with the same sample count, from its
            // first chunk.
            runs(tr.chunks).forEach(function (r) {
                stsc = stsc.concat(u32(at + 1), u32(r[1]), u32(1));
                at += r[0];
            });

            var stbl = box('stbl', [
                fullBox('stsd', 0, [u32(1), tr.entry]),
                fullBox('stts', 0, [u32(stts.length)].concat(stts.map(function (r) { return join([u32(r[0]), u32(r[1])]); }))),
                tr.sound ? [] : fullBox('stss', 0, [u32(keys.length / 4), keys]),
                fullBox('stsc', 0, [u32(stsc.length / 12), stsc]),
                fullBox('stsz', 0, [u32(0), u32(tr.samples.length), sizes]),
                fullBox('stco', 0, [u32(offsets.length)].concat(offsets.map(u32)))
            ]);
            var minf = box('minf', [
                tr.sound ? fullBox('smhd', 0, [u16(0), u16(0)]) : fullBox('vmhd', 1, [zeros(8)]),
                box('dinf', [fullBox('dref', 0, [u32(1), fullBox('url ', 1, [])])]),
                stbl
            ]);
            var mdia = box('mdia', [
                fullBox('mdhd', 0, [u32(0), u32(0), u32(tr.scale), u32(tr.dur), u16(0x55c4), u16(0)]),
                fullBox('hdlr', 0, [u32(0), ascii(tr.sound ? 'soun' : 'vide'), zeros(12), name, [0]]),
                minf
            ]);
            var tkhd = fullBox('tkhd', 3, [u32(0), u32(0), u32(tr.id), u32(0), u32(tr.ms), zeros(8), u16(0), u16(0), u16(tr.sound ? 0x100 : 0), u16(0), MATRIX,
                u32(tr.sound ? 0 : w * 65536), u32(tr.sound ? 0 : h * 65536)]);

            return box('trak', [tkhd, mdia]);
        }

        function moov(offsets) {
            return box('moov', [
                fullBox('mvhd', 0, [u32(0), u32(0), u32(1000), u32(total), u32(0x10000), u16(0x100), zeros(10), MATRIX, zeros(24), u32(tracks.length + 1)])
            ].concat(tracks.map(function (tr, n) { return trak(tr, offsets[n]); })));
        }

        // Each chunk's place in the file once the index's size is known (it
        // does not depend on the offsets' values).
        function offsets(start) {
            var out = tracks.map(function () { return []; }), at = start;

            order.forEach(function (c) {
                out[c.track].push(at);
                tracks[c.track].samples.slice(c.from, c.from + c.count).forEach(function (s) { at += s.data.length; });
            });

            return out;
        }

        var ftyp = box('ftyp', [ascii('isom'), u32(0x200), ascii('isomiso2avc1mp41')]);
        var head = moov(offsets(0)).length, data = [];

        order.forEach(function (c) {
            tracks[c.track].samples.slice(c.from, c.from + c.count).forEach(function (s) { data.push(s.data); });
        });
        data = join(data);

        return join([ftyp, moov(offsets(ftyp.length + head + 8)), u32(8 + data.length), ascii('mdat'), data]);
    }

    // An EBML number as bytes: an element ID as written, a size as a
    // variable-length integer (len bytes, else the fewest).
    function ebmlSize(n, len) {
        len = len || 1;

        while ((len < 8) && (n >= Math.pow(2, 7 * len) - 1)) {
            len++;
        }

        var out = [];

        for (var i = len - 1; i >= 0; i--) {
            out.push(Math.floor(n / Math.pow(2, 8 * i)) & 255);
        }

        out[0] |= 1 << (8 - len);

        return out;
    }

    function ebmlId(id) {
        var out = [];

        while (id > 0) {
            out.unshift(id & 255);
            id = Math.floor(id / 256);
        }

        return out;
    }

    // An element: ID, size, payload (parts, a number as an unsigned integer
    // of the fewest bytes or of len, or a string).
    function ebml(id, value, len) {
        var body;

        if (typeof value === 'number') {
            body = [];

            do {
                body.unshift(value & 255);
                value = Math.floor(value / 256);
            } while ((value > 0) || (body.length < (len || 1)));
        } else if (typeof value === 'string') {
            body = value.split('').map(function (c) { return c.charCodeAt(0); });
        } else {
            body = join(value);
        }

        return join([ebmlId(id), ebmlSize(body.length), body]);
    }

    function float64(v) {
        var b = new Uint8Array(8);

        new DataView(b.buffer).setFloat64(0, v);

        return b;
    }

    // The Opus identification header a WebM's audio track carries: the
    // encoder's own when it hands one, else one with libopus's usual
    // pre-skip at 48 kHz.
    function opusHead(desc, rate, channels) {
        if (desc && (desc.length >= 19) && (String.fromCharCode.apply(null, Array.prototype.slice.call(desc, 0, 8)) === 'OpusHead')) {
            return new Uint8Array(desc);
        }

        return new Uint8Array(ascii('OpusHead').concat([1, channels, 312 & 255, 312 >> 8, rate & 255, (rate >> 8) & 255, (rate >> 16) & 255, (rate >>> 24) & 255, 0, 0, 0]));
    }

    // A WebM of VP9 frames and, with audio, its Opus track: the header,
    // Info, Tracks, the Cues before the Clusters (each starts at a key
    // frame, at most every 30 s by its 16-bit block times) so a player
    // seeks without reading to the end. Sound blocks sit among the frames
    // in time order. audio as for mp4File().
    function webmFile(w, h, fps, frames, audio) {
        var clusters = [], cur = null, sound = (audio && audio.chunks && audio.chunks.length) ? audio : null, end = frames.length * 1000 / fps;
        var blocks = frames.map(function (f, i) { return { track: 1, ms: Math.round(i * 1000 / fps), key: f.key, data: f.data }; });

        if (sound) {
            sound.chunks.forEach(function (c) {
                blocks.push({ track: 2, ms: Math.round(c.ts / 1000), key: true, data: c.data });
                end = Math.max(end, (c.ts + c.dur) / 1000);
            });
            // By time, a frame before the sound of the same millisecond.
            blocks.sort(function (a, b) { return (a.ms - b.ms) || (a.track - b.track); });
        }

        blocks.forEach(function (b) {
            if (!cur || ((b.track === 1) && b.key && (b.ms - cur.ms > 0)) || (b.ms - cur.ms > 30000)) {
                cur = { ms: b.ms, blocks: [] };
                clusters.push(cur);
            }

            cur.blocks.push(ebml(0xa3, [[0x80 | b.track], [((b.ms - cur.ms) >> 8) & 255, (b.ms - cur.ms) & 255, b.key ? 0x80 : 0], b.data]));
        });

        clusters = clusters.map(function (c) {
            return { ms: c.ms, bytes: ebml(0x1f43b675, [ebml(0xe7, c.ms)].concat(c.blocks)) };
        });

        var header = ebml(0x1a45dfa3, [ebml(0x4286, 1), ebml(0x42f7, 1), ebml(0x42f2, 4), ebml(0x42f3, 8), ebml(0x4282, 'webm'), ebml(0x4287, 4), ebml(0x4285, 2)]);
        var info = ebml(0x1549a966, [ebml(0x2ad7b1, 1000000), ebml(0x4d80, 'Pinegrap'), ebml(0x5741, 'Pinegrap'), ebml(0x4489, [float64(end)])]);
        var entries = [ebml(0xae, [ebml(0xd7, 1), ebml(0x73c5, 1), ebml(0x83, 1), ebml(0x86, 'V_VP9'), ebml(0x23e383, Math.round(1e9 / fps)),
            ebml(0xe0, [ebml(0xb0, w), ebml(0xba, h)])])];

        if (sound) {
            var head = opusHead(sound.desc, sound.rate, sound.channels), skip = head[10] | (head[11] << 8);

            // CodecDelay is the pre-skip in ns; SeekPreRoll Opus's 80 ms.
            entries.push(ebml(0xae, [ebml(0xd7, 2), ebml(0x73c5, 2), ebml(0x83, 2), ebml(0x86, 'A_OPUS'), ebml(0x63a2, [head]),
                ebml(0x56aa, Math.round(skip * 1e9 / 48000)), ebml(0x56bb, 80000000),
                ebml(0xe1, [ebml(0xb5, [float64(sound.rate)]), ebml(0x9f, sound.channels)])]));
        }

        var tracks = ebml(0x1654ae6b, entries);

        // Positions from the Segment's data; written at 8 bytes each, the
        // Cues' size does not depend on them.
        function cues(at) {
            return ebml(0x1c53bb6b, clusters.map(function (c) {
                var pos = at;

                at += c.bytes.length;

                return ebml(0xbb, [ebml(0xb3, c.ms), ebml(0xb7, [ebml(0xf7, 1), ebml(0xf1, pos, 8)])]);
            }));
        }

        var before = info.length + tracks.length, body = join([info, tracks, cues(before + cues(0).length)].concat(clusters.map(function (c) { return c.bytes; })));

        return join([header, ebmlId(0x18538067), ebmlSize(body.length, 8), body]);
    }

    // Transparent pixels over white: a video has no alpha.
    function flatten(rgba) {
        for (var i = 0; i < rgba.length; i += 4) {
            var a = rgba[i + 3];

            if (a < 255) {
                rgba[i] = 255 - (((255 - rgba[i]) * a + 127) / 255 | 0);
                rgba[i + 1] = 255 - (((255 - rgba[i + 1]) * a + 127) / 255 | 0);
                rgba[i + 2] = 255 - (((255 - rgba[i + 2]) * a + 127) / 255 | 0);
                rgba[i + 3] = 255;
            }
        }

        return rgba;
    }

    // MP4 (H.264) or WebM (VP9) through WebCodecs: the first of opts.codecs
    // the browser's VideoEncoder takes at this size, a key frame every two
    // seconds, at most a few frames queued. opts.codec ({ VideoEncoder,
    // VideoFrame }) stands in for the platform in a test.
    function VideoEnc(opts, format) {
        var api = opts.codec || root;

        this.format = format;
        this.mime = MIME[format];
        this.w = Math.max(2, opts.width | 0);
        this.h = Math.max(2, opts.height | 0);
        this.fps = Math.max(1, Math.round(Number(opts.fps) || 30));
        this.bitrate = Math.max(1, Number(opts.bitrate) || 2000000);
        this.codecs = opts.codecs || [];
        this.Encoder = api.VideoEncoder;
        this.Frame = api.VideoFrame;
        this.chunks = [];
        this.desc = null;
        this.failure = null;
        this.enc = null;
        this.count = 0;
        // The sound's settings ({ codec, kind, rate, channels, bitrate }),
        // its encoder and packets ({ data, ts, dur } in microseconds).
        this.audio = (opts.audio && opts.audio.codec) ? opts.audio : null;
        this.AudioEncoder = api.AudioEncoder;
        this.AudioData = api.AudioData;
        this.sounds = [];
        this.soundDesc = null;
    }

    VideoEnc.prototype.sample = function () {};

    // From the bit rate: a container's few hundred bytes and a frame's share.
    VideoEnc.prototype.estimate = function () {
        return { header: 1000, frame: Math.round(this.bitrate / 8 / this.fps) };
    };

    VideoEnc.prototype.config = function (codec) {
        var c = { codec: codec, width: this.w, height: this.h, bitrate: this.bitrate, framerate: this.fps };

        if (this.format === 'mp4') {
            c.avc = { format: 'avc' };
        }

        return c;
    };

    VideoEnc.prototype.open = function () {
        var self = this, list = this.codecs.slice();

        if (typeof this.Encoder !== 'function') {
            return Promise.reject(new Error('no video encoder'));
        }

        function next() {
            var codec = list.shift();

            if (!codec) {
                throw new Error('no video codec for ' + self.w + 'x' + self.h);
            }

            return self.Encoder.isConfigSupported(self.config(codec)).then(function (r) {
                return (r && r.supported) ? codec : next();
            }, next);
        }

        return next().then(function (codec) {
            self.enc = new self.Encoder({
                output: function (chunk, meta) {
                    var data = new Uint8Array(chunk.byteLength);

                    chunk.copyTo(data);
                    self.chunks.push({ data: data, key: chunk.type === 'key' });

                    if (meta && meta.decoderConfig && meta.decoderConfig.description && !self.desc) {
                        var d = meta.decoderConfig.description;

                        self.desc = new Uint8Array(d.buffer ? d.buffer.slice(d.byteOffset, d.byteOffset + d.byteLength) : d.slice(0));
                    }
                },
                error: function (err) { self.failure = err; }
            });
            self.enc.configure(self.config(codec));
        });
    };

    VideoEnc.prototype.frame = function (rgba) {
        var self = this, i = this.count++;

        return (this.enc ? Promise.resolve() : this.open()).then(function () {
            if (self.failure) {
                throw self.failure;
            }

            var f = new self.Frame(flatten(rgba), { format: 'RGBX', codedWidth: self.w, codedHeight: self.h, timestamp: Math.round(i * 1e6 / self.fps), duration: Math.round(1e6 / self.fps) });

            self.enc.encode(f, { keyFrame: i % (2 * self.fps) === 0 });
            f.close();

            return self.drain();
        });
    };

    // Waits while more than a few frames are queued in the encoder.
    VideoEnc.prototype.drain = function () {
        var self = this;

        if (this.enc.encodeQueueSize <= 3) {
            return Promise.resolve();
        }

        return new Promise(function (go) { setTimeout(go, 5); }).then(function () { return self.drain(); });
    };

    // The mixed sound (a Float32Array per channel at audio.rate) through
    // the platform's AudioEncoder, a second at a time, at most a few
    // pieces queued. Its packets wait for end().
    VideoEnc.prototype.sound = function (planes) {
        var self = this, a = this.audio, frames = (planes && planes.length) ? planes[0].length : 0, enc = null, failure = null;
        var config = a ? { codec: a.codec, sampleRate: a.rate, numberOfChannels: planes.length, bitrate: a.bitrate } : null;

        if (!a || !frames) {
            return Promise.resolve();
        }

        if ((typeof this.AudioEncoder !== 'function') || (typeof this.AudioData !== 'function')) {
            return Promise.reject(new Error('no audio encoder'));
        }

        function settle() {
            if (failure) {
                throw failure;
            }

            if (enc.encodeQueueSize <= 3) {
                return Promise.resolve();
            }

            return new Promise(function (go) { setTimeout(go, 5); }).then(settle);
        }

        function piece(at) {
            if (at >= frames) {
                return enc.flush();
            }

            var n = Math.min(a.rate, frames - at), data = new Float32Array(n * planes.length);

            planes.forEach(function (p, c) { data.set(p.subarray(at, at + n), c * n); });

            var d = new self.AudioData({ format: 'f32-planar', sampleRate: a.rate, numberOfFrames: n, numberOfChannels: planes.length, timestamp: Math.round(at * 1e6 / a.rate), data: data });

            enc.encode(d);
            d.close();

            return settle().then(function () { return piece(at + n); });
        }

        return this.AudioEncoder.isConfigSupported(config).then(function (r) {
            if (!r || !r.supported) {
                throw new Error('no audio codec ' + a.codec);
            }

            // A packet without a duration lasts AAC's 1024 or Opus's 960
            // samples.
            var each = Math.round(((a.kind === 'opus') ? 960 : 1024) * 1e6 / a.rate);

            enc = new self.AudioEncoder({
                output: function (chunk, meta) {
                    var data = new Uint8Array(chunk.byteLength);

                    chunk.copyTo(data);
                    self.sounds.push({ data: data, ts: Math.max(0, chunk.timestamp || 0), dur: chunk.duration || each });

                    if (meta && meta.decoderConfig && meta.decoderConfig.description && !self.soundDesc) {
                        var d = meta.decoderConfig.description;

                        self.soundDesc = new Uint8Array(d.buffer ? d.buffer.slice(d.byteOffset, d.byteOffset + d.byteLength) : d.slice(0));
                    }
                },
                error: function (err) { failure = err; }
            });
            enc.configure(config);

            return piece(0);
        }).then(function () {
            enc.close();

            if (failure) {
                throw failure;
            }
        });
    };

    VideoEnc.prototype.end = function () {
        var self = this;

        if (!this.enc) {
            return Promise.reject(new Error('no frames'));
        }

        return this.enc.flush().then(function () {
            self.enc.close();

            if (self.failure) {
                throw self.failure;
            }

            var a = self.audio, audio = (a && self.sounds.length) ? { rate: a.rate, channels: a.channels, bitrate: a.bitrate, chunks: self.sounds, desc: self.soundDesc } : null;

            return (self.format === 'mp4') ? mp4File(self.w, self.h, self.fps, self.chunks, self.desc || new Uint8Array(0), audio) : webmFile(self.w, self.h, self.fps, self.chunks, audio);
        });
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
        this.mime = MIME.gif;
    }

    Encoder.prototype.sample = function (rgba) {
        this.samples.push(rgba);
    };

    Encoder.prototype.ensurePalette = function () {
        if (this.pal) {
            return;
        }

        this.pal = palette(this.samples, 255, hasSeeThrough(this.samples));
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

    // The messages above, answered in order through post(message, transfer)
    // whether the encoder's work is immediate (GIF) or a promise; after a
    // failure only its one error is posted.
    function Session(post) {
        this.post = post;
        this.enc = null;
        this.failed = false;
        this.queue = Promise.resolve();
    }

    Session.prototype.receive = function (m) {
        var self = this;

        this.queue = this.queue.then(function () {
            return self.failed ? null : self.step(m || {});
        }).then(null, function (err) {
            if (!self.failed) {
                self.failed = true;
                self.enc = null;
                self.post({ type: 'error', message: String((err && err.message) || err) });
            }
        });
    };

    Session.prototype.step = function (m) {
        var self = this, enc = this.enc;

        if (m.type === 'begin') {
            this.enc = create(m);
            return null;
        }

        if (!enc) {
            throw new Error('no animation begun');
        }

        if (m.type === 'sample') {
            enc.sample(new Uint8ClampedArray(m.data));
        } else if (m.type === 'estimate') {
            return Promise.resolve(enc.estimate()).then(function (e) { self.post(Object.assign({ type: 'estimate' }, e)); });
        } else if (m.type === 'frame') {
            return Promise.resolve(enc.frame(new Uint8ClampedArray(m.data))).then(function () { self.post({ type: 'framed', index: enc.count - 1 }); });
        } else if (m.type === 'audio') {
            return Promise.resolve(enc.sound ? enc.sound((m.data || []).map(function (b) { return new Float32Array(b); })) : null).then(function () { self.post({ type: 'audioed' }); });
        } else if (m.type === 'end') {
            return Promise.resolve(enc.end()).then(function (bytes) {
                self.enc = null;
                self.post({ type: 'file', data: bytes.buffer, mime: enc.mime }, [bytes.buffer]);
            });
        }

        return null;
    };

    Session.prototype.stop = function () {
        this.failed = true;
        this.enc = null;
        this.post = function () {};
    };

    // An encoder for opts.format (gif, apng, webp, mp4, webm; GIF by
    // default).
    function create(opts) {
        if ((opts.format === 'mp4') || (opts.format === 'webm')) {
            return new VideoEnc(opts, opts.format);
        }

        return ((opts.format === 'apng') || (opts.format === 'webp')) ? new TrueEncoder(opts, opts.format) : new Encoder(opts);
    }

    var api = {
        palette: palette, nearest: nearest, quantize: quantize, changed: changed, delayAt: delayAt, loopCount: loopCount,
        lzw: lzw, GifWriter: GifWriter, Encoder: Encoder, crc32: crc32, pngRows: pngRows, ApngWriter: ApngWriter,
        riffChunks: riffChunks, WebpWriter: WebpWriter, msAt: msAt, TrueEncoder: TrueEncoder,
        mp4File: mp4File, webmFile: webmFile, flatten: flatten, VideoEnc: VideoEnc, interleave: interleave, aacConfig: aacConfig, opusHead: opusHead,
        create: create,
        session: function (post) { return new Session(post); },
        MIME: MIME
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
        return;
    }

    // A worker: the messages above.
    if ((typeof root.importScripts === 'function') && (typeof root.document === 'undefined')) {
        var session = new Session(function (m, transfer) { root.postMessage(m, transfer || []); });

        root.onmessage = function (e) {
            var m = e.data || {};

            // A new animation starts a clean session.
            if (m.type === 'begin') {
                session.stop();
                session = new Session(function (a, transfer) { root.postMessage(a, transfer || []); });
            }

            session.receive(m);
        };

        return;
    }

    root.PgImageEncode = api;
}((typeof self !== 'undefined') ? self : this));
