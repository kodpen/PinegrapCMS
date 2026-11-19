/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: reading a video. The file's bytes go through the model's
 * own MP4 and WebM readers (M.videoDemux(), the VIDEO section); its video
 * track feeds the browser's VideoDecoder (WebCodecs). A source answers
 * frameAt(ms) with the frame shown at that time: from the cache when it
 * was decoded, else decoded on from the decoder's place when it lies
 * ahead, else from the nearest key frame before it; the decoder is kept
 * fed past the asked frame so playing forward decodes each sample once
 * (the model's videoFeedPlan()). A browser without
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

    // How long a wait for a frame lasts before the source looks again at
    // the decoder (M.videoOnWait()).
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

    function now() {
        return (window.performance && window.performance.now) ? window.performance.now() : Date.now();
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
        // The decoder's place for M.videoFeedPlan(): the sample fed next
        // and the ones fed that have not come out yet.
        this.ahead = M.videoFeedAhead(M.videoCacheSize(tr.width, tr.height));
        this.next = -1;
        this.pending = {};
        this.lastOut = 0;
        this.byTs = {};
        this.waiters = {};
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
                self.lost();
                self.wake();
            }
        });
        this.dec.configure(this.config);
        this.lost();
    };

    // The decoder holds nothing fed and takes a key frame next.
    Source.prototype.lost = function () {
        this.next = -1;
        this.pending = {};
    };

    // A copy of a decoded frame that holds none of the decoder's buffers:
    // a hardware decoder has a small pool of output frames (Chrome's H.264
    // stops after nine held) and stalls while the cache keeps them, so the
    // decoder's own frame is closed at once. Where a frame cannot be made
    // from a canvas the decoder's frame is kept.
    function own(f) {
        var w = f.displayWidth, h = f.displayHeight, c, copy;

        try {
            c = (typeof window.OffscreenCanvas === 'function') ? new window.OffscreenCanvas(w, h) : document.createElement('canvas');
            c.width = w;
            c.height = h;
            c.getContext('2d').drawImage(f, 0, 0, w, h);
            copy = new window.VideoFrame(c, { timestamp: f.timestamp, duration: f.duration || undefined });
        } catch (err) {
            return f;
        }

        f.close();

        return copy;
    }

    // A decoded frame into the cache by its sample, waking who waits.
    Source.prototype.got = function (f) {
        var i = this.byTs[f.timestamp];

        if ((i === undefined) || this.closed) {
            f.close();
            return;
        }

        delete this.pending[i];
        this.lastOut = now();
        this.cache.put(i, own(f));
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
            this.pending[i] = true;
        }

        if (from <= to) {
            this.next = to + 1;
        }
    };

    // Gives out every frame fed; the decoder takes a key frame next.
    Source.prototype.flush = function () {
        var self = this;

        this.next = -1;

        return this.dec.flush().then(function () {
            self.lost();
        }, function () {
            self.lost();
        });
    };

    // Decodes the seek point's frame unless it is cached, keeping the
    // decoder fed past it (M.videoFeedPlan()): on from the decoder's place,
    // or from the key frame after a reset. A wait that runs out feeds on
    // or waits again while the decoder has work (M.videoOnWait()); a flush
    // only at the end of the stream or when the decoder gives nothing.
    Source.prototype.load = function (p) {
        var self = this, n = this.track.samples.length, plan, since;

        if (this.dec.state === 'closed') {
            this.make();
        }

        plan = M.videoFeedPlan(this, p, n, this.cache.has(p.frame));

        if (plan.reset) {
            this.dec.reset();
            this.dec.configure(this.config);
            this.lost();
        }

        this.feed(plan.from, plan.to);

        if (!plan.wait) {
            return Promise.resolve();
        }

        if (plan.flush) {
            return this.flush();
        }

        since = now();

        function round() {
            return self.wait(p.frame, WAIT_MS).then(function () {
                var what;

                if (self.cache.has(p.frame) || (self.dec.state !== 'configured') || self.closed) {
                    return null;
                }

                what = M.videoOnWait(self, n, self.dec.decodeQueueSize || 0, now() - Math.max(self.lastOut, since));

                if (what === 'flush') {
                    return self.flush();
                }

                if (what === 'feed') {
                    self.feed(self.next, Math.min(n - 1, self.next + self.ahead - 1));

                    if (self.next >= n) {
                        return self.flush();
                    }
                }

                return round();
            });
        }

        return round();
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
            self.lost();
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
