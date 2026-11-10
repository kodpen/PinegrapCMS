/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: reading a video. The file's bytes go through the model's
 * own MP4 and WebM readers (M.videoDemux(), the VIDEO section); its video
 * track feeds the browser's VideoDecoder (WebCodecs). A source answers
 * frameAt(ms) with the frame shown at that time: from the cache when it
 * was decoded, else decoded on from the frames already fed when it lies
 * ahead, else from the nearest key frame before it. A browser without
 * WebCodecs, or without a decoder for the codec (H.265 in some), gets a
 * message instead.
 *
 * X.video.open(data) -> Promise of a source: { duration (ms), width,
 * height, codec, track, frameAt(ms) -> Promise of a VideoFrame (a copy
 * the caller closes) or null, close() }. Its error's message is a code
 * that X.video.message(e) turns into a label.
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

    var Editor = X.Editor, M = X.M, t = X.t;

    // Samples fed past the one asked for, so a decoder that reorders
    // (B-frames) gives it out without a flush.
    var LOOKAHEAD = 4;
    // Decoding on from where the decoder stands is chosen over a seek
    // while it is at most this many samples before the key frame.
    var NEAR = 30;
    // How long a frame fed with its lookahead may take before the decoder
    // is flushed for it.
    var WAIT_MS = 250;

    var MESSAGES = {
        'video-format': 'videoFormat',
        'video-broken': 'videoBroken',
        'video-fragmented': 'videoFragmented',
        'video-webcodecs': 'videoNoCodecs',
        'video-notrack': 'videoNoTrack',
        'video-codec': 'videoCodec'
    };

    function fail(code, codec) {
        var e = new Error(code);

        e.codec = codec || '';

        return e;
    }

    function supported() {
        return (typeof window.VideoDecoder === 'function') && (typeof window.EncodedVideoChunk === 'function');
    }

    function bytesOf(data) {
        if (data instanceof Uint8Array) {
            return Promise.resolve(data);
        }

        if (data instanceof ArrayBuffer) {
            return Promise.resolve(new Uint8Array(data));
        }

        return (data && data.arrayBuffer) ? data.arrayBuffer().then(function (b) { return new Uint8Array(b); }) : Promise.reject(fail('video-format'));
    }

    // ---- a source ----

    function Source(bytes, d, tr) {
        var self = this;

        this.bytes = bytes;
        this.track = tr;
        this.codec = tr.codec;
        this.duration = d.duration;
        this.width = tr.width;
        this.height = tr.height;
        // Whether the file has a sound track too (the video layer's mute
        // and Detach sound).
        this.sound = !!M.videoTrack(d, 'audio');
        this.config = M.videoConfig(tr);
        this.cache = M.videoCache(M.videoCacheSize(tr.width, tr.height));
        this.byTs = {};
        this.waiters = {};
        this.next = -1;
        this.ticket = 0;
        this.queue = Promise.resolve();
        this.closed = false;

        tr.samples.forEach(function (s, i) {
            self.byTs[s.ts] = i;
        });
        this.make();
    }

    Source.prototype.make = function () {
        var self = this;

        this.dec = new window.VideoDecoder({
            output: function (f) { self.got(f); },
            error: function () {
                self.next = -1;
                self.wake();
            }
        });
        this.dec.configure(this.config);
        this.next = -1;
    };

    // A decoded frame into the cache by its sample, waking who waits.
    Source.prototype.got = function (f) {
        var i = this.byTs[f.timestamp];

        if ((i === undefined) || this.closed) {
            f.close();
            return;
        }

        this.cache.put(i, f);
        this.wake(i);
    };

    Source.prototype.wake = function (i) {
        var self = this;

        Object.keys(this.waiters).forEach(function (k) {
            if ((i === undefined) || (Number(k) === i)) {
                self.waiters[k].forEach(function (done) { done(); });
                delete self.waiters[k];
            }
        });
    };

    // Resolves when sample i is decoded, or after ms.
    Source.prototype.wait = function (i, ms) {
        var self = this;

        return this.cache.has(i) ? Promise.resolve() : new Promise(function (done) {
            var timer = setTimeout(done, ms);

            (self.waiters[i] = self.waiters[i] || []).push(function () {
                clearTimeout(timer);
                done();
            });
        });
    };

    Source.prototype.feed = function (from, to) {
        var s = this.track.samples, i;

        for (i = from; i <= to; i++) {
            this.dec.decode(new window.EncodedVideoChunk({
                type: s[i].key ? 'key' : 'delta',
                timestamp: s[i].ts,
                duration: s[i].dur,
                data: this.bytes.subarray(s[i].at, s[i].at + s[i].size)
            }));
        }

        this.next = to + 1;
    };

    // Decodes the seek point's frame unless it is cached: on from the
    // decoder's place when that is near enough before it, else from its
    // key frame after a reset; a flush when the lookahead was not enough.
    Source.prototype.load = function (p) {
        var self = this, last = this.track.samples.length - 1, start = p.from;

        if (this.cache.has(p.frame)) {
            return Promise.resolve();
        }

        if (this.dec.state === 'closed') {
            this.make();
        }

        if ((this.next > p.frame) && (this.next <= p.frame + LOOKAHEAD + 1)) {
            // Fed already, still inside the decoder: wait for it.
            start = -1;
        } else if ((this.next >= 0) && (this.next <= p.frame) && (this.next >= p.from - NEAR)) {
            start = this.next;
        } else {
            this.dec.reset();
            this.dec.configure(this.config);
        }

        if (start >= 0) {
            this.feed(start, Math.min(last, p.frame + LOOKAHEAD));
        }

        return this.wait(p.frame, WAIT_MS).then(function () {
            if (self.cache.has(p.frame) || (self.dec.state !== 'configured')) {
                return null;
            }

            // After a flush the decoder takes a key frame first.
            self.next = -1;

            return self.dec.flush();
        });
    };

    // The frame shown at ms, as a copy the caller closes; a request
    // overtaken by a newer one before its turn answers from the cache
    // only, so scrubbing decodes only where the playhead stops.
    Source.prototype.frameAt = function (ms) {
        var self = this, p = M.videoSeek(this.track, Math.round(ms * 1000)), ticket = ++this.ticket;

        if (!p || this.closed) {
            return Promise.resolve(null);
        }

        var job = this.queue.then(function () {
            return ((ticket === self.ticket) && !self.closed) ? self.load(p) : null;
        }).then(null, function () {
            self.next = -1;
        }).then(function () {
            var f = self.closed ? null : self.cache.get(p.frame);

            return f ? f.clone() : null;
        });

        this.queue = job.then(null, function () {});

        return job;
    };

    Source.prototype.close = function () {
        this.closed = true;
        this.cache.clear();
        this.wake();

        if (this.dec.state !== 'closed') {
            this.dec.close();
        }
    };

    // ---- opening ----

    function open(data) {
        if (!supported()) {
            return Promise.reject(fail('video-webcodecs'));
        }

        return bytesOf(data).then(function (bytes) {
            var d = M.videoDemux(bytes), tr = M.videoTrack(d, 'video');

            if (!tr) {
                throw fail('video-notrack');
            }

            return window.VideoDecoder.isConfigSupported(M.videoConfig(tr)).then(function (r) {
                if (!r || !r.supported) {
                    throw fail('video-codec', tr.codec);
                }

                return new Source(bytes, d, tr);
            }, function () {
                throw fail('video-codec', tr.codec);
            });
        });
    }

    // The label for a failure of open(): the codec named when the browser
    // has no decoder for it.
    function message(e) {
        var key = MESSAGES[e && e.message] || 'videoBroken';

        return String(t(key)).replace('{var:1}', M.videoCodecName(e && e.codec));
    }

    X.video = { supported: supported, open: open, message: message };

    // Opens a video for the editor; a failure is said in an error toast
    // on the stage and passed on.
    Editor.prototype.videoOpen = function (data) {
        var self = this;

        return open(data).then(null, function (e) {
            self.showMessage(message(e), true);

            throw e;
        });
    };
}(window.PgImageEditor.parts));
