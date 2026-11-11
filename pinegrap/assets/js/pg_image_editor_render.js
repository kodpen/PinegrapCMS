/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: frames of an animation and the Save animation dialog.
 *
 * renderFrame(t, w, h) draws one moment on a canvas: the base (the picture
 * as the server draws it without its layers, action base, fetched once per
 * dialog; an artboard's background), the picture's move, then each layer
 * through paintLayers() with the time (M.motionAt(): transform, opacity,
 * wipe, typed letters, a stroke drawn on; a video layer's own frame of
 * that moment, clipFramesAt() of the clip part) and the frame on top.
 * Frames are drawn here (text needs the page's fonts) and encoded in the
 * worker pg_image_editor_encode.js; where a site's policy refuses the
 * worker the same encoder runs on the page.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, icon = X.icon, button = X.button, pct = X.pct;

    X.icons.film = 'M4 4.5h16v15H4zM8 4.5v15M16 4.5v15M4 9h4M4 15h4M16 9h4M16 15h4';
    X.icons.download = 'M12 4v11M7.5 10.5L12 15l4.5-4.5M5 19.5h14';
    X.icons.check = 'M5 12.5l4.5 4.5L19 7.5';

    // Layers that never move (a redaction would show what it hides).
    var STILL = ['redact'];
    // Frames sampled for the palette and the estimate.
    var SAMPLES = 8;
    // Frames sent to the encoder before waiting for its answer.
    var AHEAD = 2;
    var ESTIMATE_DELAY = 400;

    function config() {
        return window.pgImageEditorConfig || {};
    }

    function bytesText(n) {
        var sep = config().decimal || '.';

        if (n < 1024 * 1024) {
            return Math.max(1, Math.round(n / 1024)) + ' KB';
        }

        return (Math.round(n / 104857.6) / 10).toFixed(1).replace('.', sep) + ' MB';
    }

    // ---- painting a moment ----

    // A layer at time t onto g, already in the layer space with its opacity
    // (paintLayers()): its motion's transform, opacity and wipe; the letters
    // typed so far; a stroke drawn on from its prepared picture.
    Editor.prototype.paintMoving = function (g, l, time, type) {
        if (STILL.indexOf(l.type) >= 0) {
            type.paint.call(this, g, l);
            return;
        }

        var st = M.motionAt(l, time, M.docMotion(this.doc).duration);

        if (st.opacity <= 0) {
            return;
        }

        var cx = (Number(l.x) || 0) + (Number(l.w) || 0) / 2, cy = (Number(l.y) || 0) + (Number(l.h) || 0) / 2;

        g.globalAlpha *= Math.min(1, st.opacity);

        if (st.x || st.y || st.rot || (st.scale !== 1)) {
            g.translate(cx + st.x, cy + st.y);
            g.rotate(st.rot * Math.PI / 180);
            g.scale(st.scale, st.scale);
            g.translate(-cx, -cy);
        }

        if (st.clip) {
            var r = wipeRect(l, st.clip);

            g.beginPath();
            g.rect(r.x, r.y, r.w, r.h);
            g.clip();
        }

        if (st.draw < 1) {
            var cut = this.frameCuts && this.frameCuts[l.id];

            if (cut) {
                g.drawImage(cut.img, cut.x, cut.y, cut.w, cut.h);
            } else {
                g.globalAlpha *= st.draw;
                type.paint.call(this, g, l);
            }

            return;
        }

        type.paint.call(this, g, (st.chars !== null) ? Object.assign({}, l, { text: Array.from(String(l.text || '')).slice(0, st.chars).join('') }) : l);
    };

    // The part of a layer's box a wipe shows (the stage's motionClip()).
    function wipeRect(l, clip) {
        var b = M.bounds([l]), f = Math.max(0, clip.f), r = { x: b.x, y: b.y, w: b.w, h: b.h };

        if ((clip.side === 'left') || (clip.side === 'right')) {
            r.w = b.w * f;
            r.x = (clip.side === 'right') ? b.x + b.w * (1 - f) : b.x;
        } else {
            r.h = b.h * f;
            r.y = (clip.side === 'bottom') ? b.y + b.h * (1 - f) : b.y;
        }

        return r;
    }

    // A stroke being drawn on cannot be cut on a canvas (it has no path
    // length): its node with the stage's dash, as a picture, at scale k.
    Editor.prototype.drawCut = function (l, draw, k) {
        var node = this.layerNode(l), b = M.bounds([l]), pad = Math.max(b.w, b.h) * 0.1 + 20;

        if (!node) {
            return Promise.resolve(null);
        }

        var box = { x: b.x - pad, y: b.y - pad, w: b.w + 2 * pad, h: b.h + 2 * pad };
        var svg = document.createElementNS(SVGNS, 'svg');

        svg.setAttribute('xmlns', SVGNS);
        svg.setAttribute('viewBox', [box.x, box.y, box.w, box.h].join(' '));
        svg.setAttribute('width', String(Math.max(1, Math.ceil(box.w * k))));
        svg.setAttribute('height', String(Math.max(1, Math.ceil(box.h * k))));
        svg.appendChild(node);

        Array.prototype.forEach.call(svg.querySelectorAll('path, line, polyline, polygon, rect, ellipse, circle'), function (s) {
            // The eraser's mask (pg_image_editor_draw.js) is not drawn on.
            if (s.closest('mask')) {
                return;
            }

            s.setAttribute('pathLength', '1');
            s.setAttribute('stroke-dasharray', '1 1');
            s.setAttribute('stroke-dashoffset', String(1 - draw));
            s.setAttribute('fill-opacity', String(draw));
        });

        return new Promise(function (resolve) {
            var img = new Image();

            img.onload = function () { resolve({ img: img, x: box.x, y: box.y, w: box.w, h: box.h }); };
            img.onerror = function () { resolve(null); };
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
        });
    };

    // The pictures of the strokes being drawn on at time, by layer id.
    Editor.prototype.frameCutsAt = function (time, k) {
        var self = this, end = M.docMotion(this.doc).duration, cuts = {}, waits = [];

        this.doc.layers.forEach(function (l) {
            var st = (!l.hidden && (STILL.indexOf(l.type) < 0)) ? M.motionAt(l, time, end) : null;

            if (st && (st.draw < 1) && (st.opacity > 0)) {
                waits.push(self.drawCut(l, st.draw, k).then(function (cut) { cuts[l.id] = cut; }));
            }
        });

        return Promise.all(waits).then(function () { return cuts; });
    };

    // One moment of the animation drawn at w x h on canvas (a new one when
    // none is given); answers the canvas. doc, when given, is drawn in place
    // of the editor's (a scene, M.sceneView()). It waits for the strokes
    // being drawn on and for each video layer's frame of that moment.
    Editor.prototype.renderFrame = function (time, w, h, canvas, doc) {
        var self = this, live = this.doc, cutting;

        doc = doc || live;

        var out = M.saveSize(doc), box = M.saveBox(doc), k = w / box.w;

        canvas = canvas || document.createElement('canvas');
        this.doc = doc;
        cutting = Promise.all([this.frameCutsAt(time, k), this.clipFramesAt ? this.clipFramesAt(time) : null]);
        this.doc = live;

        return cutting.then(function (got) {
            var g = canvas.getContext('2d'), keep = self.doc, cuts = got[0], frames = got[1];

            if ((canvas.width !== w) || (canvas.height !== h)) {
                canvas.width = w;
                canvas.height = h;
            } else {
                g.setTransform(1, 0, 0, 1, 0, 0);
                g.clearRect(0, 0, w, h);
            }

            self.doc = doc;

            try {
                self.paintBase(g, time, w / out.w, h / out.h);
                self.frameCuts = cuts;
                self.clipFrames = frames;
                self.paintLayers(g, box, w, h, time);

                if (self.paintFrame) {
                    self.paintFrame(g, box, w, h);
                }
            } finally {
                self.frameCuts = null;
                self.clipFrames = null;
                self.doc = keep;

                Object.keys(frames || {}).forEach(function (id) {
                    if (frames[id]) {
                        frames[id].close();
                    }
                });
            }

            return canvas;
        });
    };

    // The base at time: an artboard's fill, else the server's picture on
    // its canvas (M.saveLayout()) with the document's move.
    Editor.prototype.paintBase = function (g, time, sx, sy) {
        var doc = this.doc, a = doc.artboard;

        g.save();
        g.setTransform(sx, 0, 0, sy, 0, 0);

        if (a) {
            if (a.background && (a.background !== 'transparent')) {
                g.fillStyle = a.background;
                g.fillRect(0, 0, a.width, a.height);
            }

            g.restore();
            return;
        }

        var layout = M.saveLayout(doc), size = M.outputSize(doc), m = M.docMotion(doc);
        var r = layout ? { x: layout.x, y: layout.y, w: layout.pw || size.w, h: layout.ph || size.h } : { x: 0, y: 0, w: size.w, h: size.h };

        if (layout && (layout.fill !== 'transparent')) {
            g.fillStyle = layout.fill;
            g.fillRect(0, 0, layout.w, layout.h);
        }

        if (this.animBase) {
            g.beginPath();
            g.rect(r.x, r.y, r.w, r.h);
            g.clip();

            if (m.base !== 'none') {
                var st = M.baseAt(m.base, time, m.duration), cx = r.x + r.w / 2, cy = r.y + r.h / 2;

                g.translate(cx + st.x * r.w, cy + st.y * r.h);
                g.scale(st.scale, st.scale);
                g.translate(-cx, -cy);
            }

            g.drawImage(this.animBase, r.x, r.y, r.w, r.h);
        }

        g.restore();
    };

    // The picture without its layers from the server (colour, crop,
    // redaction), once per dialog; an artboard has none.
    Editor.prototype.loadBase = function () {
        var self = this, cfg = config();

        if (this.doc.artboard || this.animBase) {
            return Promise.resolve();
        }

        var body = { action: 'base', file_id: this.opts.fileId, doc: Object.assign({}, this.doc, { layers: [] }), base: this.versionBase || 0, token: cfg.token || '' };

        if (!cfg.serverColor && M.colorMatrix(M.colorValues(this.doc))) {
            body.colour = this.drawColour();
        }

        if (this.redactMasks) {
            var masks = this.redactMasks();

            if (masks.length) {
                body.redact = masks;
            }
        }

        // The selection's areas (Erase, Adjust inside), as a save sends them.
        if (this.areaMasks) {
            var areas = this.areaMasks();

            if (areas.length) {
                body.masks = areas;
            }
        }

        return fetch(cfg.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (res) {
            if (!res.ok || !/^image\//.test(res.headers.get('Content-Type') || '')) {
                return res.json().catch(function () { return null; }).then(function (r) {
                    throw new Error((r && r.message) || t('animError'));
                });
            }

            return res.blob();
        }).then(function (blob) {
            return new Promise(function (resolve, reject) {
                var img = new Image(), url = URL.createObjectURL(blob);

                img.onload = function () {
                    URL.revokeObjectURL(url);
                    self.animBase = img;
                    resolve();
                };
                img.onerror = function () {
                    URL.revokeObjectURL(url);
                    reject(new Error(t('animError')));
                };
                img.src = url;
            });
        });
    };

    // ---- the encoder ----

    // The worker, or the same encoder on the page where it is refused. Its
    // send(message, transfer) and on(handler) hide which.
    function encoder() {
        var url = (config().encoder || [])[0], handler = null, worker = null;

        try {
            worker = url ? new Worker(url) : null;
        } catch (err) {
            worker = null;
        }

        if (worker) {
            var api = {
                send: function (m, transfer) { worker.postMessage(m, transfer || []); },
                on: function (fn) { handler = fn; },
                stop: function () { worker.terminate(); }
            };

            worker.onmessage = function (e) { handler(e.data); };
            worker.onerror = function (e) {
                e.preventDefault();
                handler({ type: 'error', message: t('animError') });
            };

            return Promise.resolve(api);
        }

        return pageEncoder(url).then(function (E) {
            var session = E.session(function (m) { handler(m); });

            return {
                send: function (m) { setTimeout(function () { session.receive(m); }, 0); },
                on: function (fn) { handler = fn; },
                stop: function () { session.stop(); }
            };
        });
    }

    // What this browser can encode beyond GIF (M.animFormats()): a deflate
    // stream for APNG; a WebP canvas encoder, off the page too, for WebP.
    function animCaps() {
        var webp = false;

        try {
            var c = document.createElement('canvas');

            c.width = c.height = 1;
            webp = (typeof OffscreenCanvas === 'function') && (c.toDataURL('image/webp').indexOf('data:image/webp') === 0);
        } catch (err) {
            webp = false;
        }

        return { deflate: (typeof CompressionStream === 'function') && (typeof Response === 'function'), webp: webp };
    }

    // How this browser makes each video format: 'codecs' where its
    // VideoEncoder takes one of the format's codecs, else 'recorder' where
    // MediaRecorder records one of its types off a canvas, else false.
    function animVideoCaps() {
        var out = {};

        return Promise.all(['mp4', 'webm'].map(function (k) {
            var f = M.ANIM_FORMATS[k], list = f.codecs.slice();

            function next() {
                var codec = list.shift();

                if (!codec || (typeof VideoEncoder !== 'function')) {
                    out[k] = recorderType(f) ? 'recorder' : false;
                    return null;
                }

                return VideoEncoder.isConfigSupported({ codec: codec, width: 640, height: 360, bitrate: 1000000, framerate: f.rate }).then(function (r) {
                    if (r && r.supported) {
                        out[k] = 'codecs';
                        return null;
                    }

                    return next();
                }, next);
            }

            return next();
        })).then(function () { return out; });
    }

    // The first of a video format's MediaRecorder types the browser records
    // from a canvas, or ''.
    function recorderType(f) {
        if ((typeof MediaRecorder !== 'function') || (typeof HTMLCanvasElement.prototype.captureStream !== 'function')) {
            return '';
        }

        return f.recorder.filter(function (type) { return MediaRecorder.isTypeSupported(type); })[0] || '';
    }

    // A video in real time where the browser has no encoder for it: each
    // frame drawn on canvas is handed to MediaRecorder at its time, so a
    // frame that takes longer to draw holds the one before longer. Resolves
    // the file; job.enc.stop() ends it unsaved.
    function recordVideo(editor, set, size, count, canvas, job, progress) {
        var f = M.ANIM_FORMATS[set.format], type = recorderType(f), stream, track, rec, parts = [], start = 0;

        return editor.renderFrame(0, size.w, size.h, canvas).then(function () {
            stream = canvas.captureStream(0);
            track = stream.getVideoTracks()[0];
            rec = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: M.animBitrate(size, set.fps) });

            return new Promise(function (resolve, reject) {
                job.enc = { stop: function () { if (rec.state !== 'inactive') { rec.stop(); } } };
                rec.ondataavailable = function (e) {
                    if (e.data && e.data.size) {
                        parts.push(e.data);
                    }
                };
                rec.onerror = function () { reject(new Error(t('animError'))); };
                rec.onstop = function () {
                    stream.getTracks().forEach(function (tr) { tr.stop(); });
                    job.cancelled ? reject(null) : resolve(new Blob(parts, { type: f.mime }));
                };

                // Waits for the frame's time from the start.
                function at(i) {
                    var wait = start + i * 1000 / set.fps - performance.now();

                    return new Promise(function (go) { setTimeout(go, Math.max(0, wait)); });
                }

                function step(i) {
                    if (job.cancelled) {
                        return null;
                    }

                    if (i >= count) {
                        progress('encode', count, count);
                        return at(count).then(function () { rec.stop(); });
                    }

                    return ((i === 0) ? Promise.resolve(canvas) : editor.renderFrame(i * 1000 / set.fps, size.w, size.h, canvas)).then(function () {
                        return at(i);
                    }).then(function () {
                        if (job.cancelled) {
                            return null;
                        }

                        track.requestFrame();
                        editor.animPreview(canvas);
                        progress('frames', i + 1, count);

                        return step(i + 1);
                    });
                }

                rec.start(1000);
                start = performance.now();
                Promise.resolve(step(0)).then(null, reject);
            });
        });
    }

    // The encoder's script on the page (window.PgImageEncode).
    function pageEncoder(url) {
        if (window.PgImageEncode) {
            return Promise.resolve(window.PgImageEncode);
        }

        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');

            s.src = url;
            s.onload = function () { window.PgImageEncode ? resolve(window.PgImageEncode) : reject(new Error(t('animError'))); };
            s.onerror = function () { reject(new Error(t('animError'))); };
            document.head.appendChild(s);
        });
    }

    // ---- a job: the base, samples, then frames or an estimate ----

    // set: the dialog's settings; estimate true answers the size, else the
    // file as a Blob. progress(stage, done, count) on the way; job.cancel().
    Editor.prototype.animJob = function (set, estimate, progress) {
        var self = this, f = M.ANIM_FORMATS[set.format], out = M.saveSize(this.doc), size = M.animSize(out, set.edge, f.even);
        var ms = M.sceneTotal(this.doc), count = M.animFrames(ms, set.fps), job = { cancelled: false, enc: null };
        var bitrate = M.animBitrate(size, set.fps);
        var canvas = document.createElement('canvas');
        // A video's sound (pg_image_editor_mix.js): mixed before the
        // frames, encoded after them.
        var sound = this.mixFor ? this.mixFor(set) : null, pcm = null;

        // A PDF is a page per scene, made by pg_image_editor_present.js.
        if (f.pdf && this.pdfJob) {
            return this.pdfJob(set, estimate, progress);
        }

        job.cancel = function () {
            job.cancelled = true;

            if (job.enc) {
                job.enc.stop();
            }

            if (job.reject) {
                job.reject(null);
            }
        };

        function pixels(i) {
            return self.renderFrame(i * 1000 / set.fps, size.w, size.h, canvas).then(function (c) {
                return c.getContext('2d').getImageData(0, 0, size.w, size.h).data.buffer;
            });
        }

        job.promise = new Promise(function (resolve, reject) {
            job.reject = reject;

            // A video's size follows its bit rate.
            if (f.video && estimate) {
                resolve({ bytes: M.animEstimate(1000, bitrate / 8 / set.fps, count) + (sound ? sound.bytes : 0), count: count, size: size, sound: sound ? sound.bytes : 0 });
                return;
            }

            progress('base', 0, count);

            self.layersReady().then(function () { return self.loadBase(); }).then(function () {
                if (f.video && ((self.animVia || {})[set.format] === 'recorder')) {
                    progress('frames', 0, count);

                    return recordVideo(self, set, size, count, canvas, job, progress).then(resolve, reject);
                }

                if (!sound) {
                    return encoder();
                }

                progress('mix', 0, count);

                return self.mixRender(sound).then(function (planes) {
                    pcm = planes;

                    return job.cancelled ? null : encoder();
                });
            }).then(function (enc) {
                var drawn = 0, framed = 0, waiting = null, sent = false;

                if (!enc) {
                    return null;
                }

                if (job.cancelled) {
                    enc.stop();
                    return;
                }

                job.enc = enc;
                enc.on(function (m) {
                    if (m.type === 'error') {
                        enc.stop();
                        reject(new Error(m.message));
                    } else if (m.type === 'estimate') {
                        enc.stop();
                        resolve({ bytes: M.animEstimate(m.header, m.frame, count), count: count, size: size });
                    } else if (m.type === 'framed') {
                        framed++;
                        // Once every frame is sent the steps stay past the frames.
                        progress((sent || (framed === count)) ? (sound ? 'audio' : 'encode') : 'frames', drawn, count);

                        if (waiting) {
                            var go = waiting;

                            waiting = null;
                            go();
                        }
                    } else if (m.type === 'audioed') {
                        progress('encode', count, count);
                    } else if (m.type === 'file') {
                        enc.stop();
                        resolve(new Blob([m.data], { type: m.mime }));
                    }
                });
                enc.send({ type: 'begin', format: set.format, width: size.w, height: size.h, fps: set.fps, loop: set.loop, dither: set.dither, count: count, bitrate: bitrate, codecs: f.codecs, audio: sound ? sound.config : null });

                var samples = f.video ? [] : M.animSamples(count, SAMPLES);

                // The samples one after the other, then the frames, never
                // more than AHEAD ahead of the encoder.
                function sample(k) {
                    if (job.cancelled) {
                        return null;
                    }

                    if (k >= samples.length) {
                        if (estimate) {
                            enc.send({ type: 'estimate' });
                            return null;
                        }

                        progress('frames', 0, count);

                        return frame(0);
                    }

                    return pixels(samples[k]).then(function (buf) {
                        enc.send({ type: 'sample', data: buf }, [buf]);

                        return sample(k + 1);
                    });
                }

                function frame(i) {
                    if (job.cancelled) {
                        return null;
                    }

                    if (i >= count) {
                        sent = true;

                        if (pcm) {
                            var planes = pcm.map(function (p) { return p.buffer; });

                            pcm = null;
                            progress('audio', count, count);
                            enc.send({ type: 'audio', data: planes }, planes);
                        } else {
                            progress('encode', count, count);
                        }

                        enc.send({ type: 'end' });
                        return null;
                    }

                    if (i - framed >= AHEAD) {
                        return new Promise(function (go) { waiting = go; }).then(function () { return frame(i); });
                    }

                    return pixels(i).then(function (buf) {
                        if (job.cancelled) {
                            return null;
                        }

                        drawn = i + 1;
                        self.animPreview(canvas);
                        enc.send({ type: 'frame', data: buf }, [buf]);
                        progress('frames', drawn, count);

                        return frame(i + 1);
                    });
                }

                return sample(0);
            }).then(null, reject);
        });

        return job;
    };

    // ---- the dialog ----

    // Offered where the editor saves into Files; a caller with its own
    // endpoint or a callback gets no animation export.
    Editor.prototype.animOffered = function () {
        return ['replace', 'copy', 'new'].indexOf(this.saveMode) >= 0 && (!!this.doc.artboard || !!this.opts.fileId);
    };

    Editor.prototype.animMoves = function () {
        return M.hasMotion(this.doc) || (M.sceneCount(this.doc) > 1) || (!this.doc.artboard && (M.docMotion(this.doc).base !== 'none'));
    };

    Editor.prototype.openAnimation = function () {
        var self = this, small = (navigator.deviceMemory !== undefined) && (navigator.deviceMemory <= 4);

        if (!this.ready || this.saving || this.animBox) {
            return;
        }

        if (this.playing) {
            this.pause();
        }

        this.animSet = this.animSet || M.animDefaults(this.doc, 'gif', small);
        // The formats again: PDF only while there are scenes.
        this.animKinds = null;
        this.animBase = null;
        this.animReturn = document.activeElement;
        this.animBox = el('div', { class: 'pgie-confirm pgie-anim', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-anim-title' });
        this.animBox.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                self.closeAnimation();
            }

            e.stopPropagation();
        });
        this.root.appendChild(this.animBox);
        this.renderAnimation();

        // The video formats join once the browser has said how it makes
        // them; the settings are drawn again unless a file is being made.
        if (!this.animVia) {
            this.animVia = {};
            animVideoCaps().then(function (via) {
                self.animVia = via;
                self.animKinds = null;

                if (self.animBox && !self.animBox.querySelector('.pgie-anim-steps') && (via.mp4 || via.webm)) {
                    self.renderAnimation();
                }
            }, function () {});
        }
    };

    Editor.prototype.closeAnimation = function () {
        // A file being sent is written whatever happens here: the dialog
        // waits for the answer.
        if (!this.animBox || this.animUploading) {
            return;
        }

        if (this.animTask) {
            this.animTask.cancel();
            this.animTask = null;
        }

        clearTimeout(this.animTimer);

        var back = this.animReturn;

        this.animBox.remove();
        this.animBox = null;
        this.animBase = null;
        this.animReturn = null;
        ((back && !back.disabled && this.dialog.contains(back)) ? back : this.stage).focus({ preventScroll: true });
    };

    // The settings view: preview, format, size, frame rate, repeat,
    // dithering, the estimate, Download.
    Editor.prototype.renderAnimation = function () {
        var self = this, set = this.animSet, f = M.ANIM_FORMATS[set.format], out = M.saveSize(this.doc);
        var size = M.animSize(out, set.edge, f.even), ms = M.sceneTotal(this.doc), long = ms > f.maxMs;
        var box = this.animBox;

        this.animKinds = this.animKinds || M.animFormats(Object.assign(animCaps(), this.animVia, { scenes: M.sceneCount(this.doc) > 1 }));

        if (this.animKinds.indexOf(set.format) < 0) {
            this.animSet = set = M.animSwitch(set, 'gif');
            f = M.ANIM_FORMATS.gif;
            size = M.animSize(out, set.edge, f.even);
            long = ms > f.maxMs;
        }

        box.textContent = '';
        this.animEls = {};

        var preview = el('canvas', { class: 'pgie-anim-preview', 'aria-hidden': 'true' });
        var main = el('div', { class: 'pgie-anim-main' });

        this.animEls.preview = preview;
        this.animEls.caption = el('p', { class: 'pgie-anim-cap', text: t('animPreview') + ' · ' + size.w + ' × ' + size.h + ' px' });

        function chips(label, items, value, pick, extra) {
            var row = el('div', { class: 'pgie-chips', role: 'radiogroup', 'aria-label': label });

            items.forEach(function (item) {
                var on = (item[0] === value);

                row.appendChild(el('button', { type: 'button', role: 'radio', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-checked': on ? 'true' : 'false', text: item[1], onclick: function () { pick(item[0]); } }));
            });

            return el('div', { class: 'pgie-anim-field' }, [el('div', { class: 'pgie-anim-label' }, [el('span', { text: label }), extra ? el('b', { text: extra }) : null]), row]);
        }

        function change(patch) {
            Object.keys(patch).forEach(function (key) { set[key] = patch[key]; });
            self.renderAnimation();
        }

        main.appendChild(chips(t('animFormat'), this.animKinds.map(function (k) { return [k, t('animFormat_' + k)]; }), set.format, function (v) { change(M.animSwitch(set, v)); }));

        // Which formats carry the document's sound, and a video's Sound
        // field (pg_image_editor_mix.js).
        if (this.mixNote) {
            this.mixNote(main);
        }

        main.appendChild(chips(t('animSize'), M.animEdges(out, set.format).map(function (e) { return [e, String(e)]; }), Math.min(set.edge, Math.max(out.w, out.h)), function (v) { change({ edge: v }); }, size.w + ' × ' + size.h + ' px'));

        if (this.mixField) {
            this.mixField(main, set);
        }
        // A PDF's pages are still: no rate, no repeat.
        if (!f.pdf) {
            main.appendChild(chips(t('animRate'), f.rates.map(function (r) { return [r, String(r)]; }), set.fps, function (v) { change({ fps: v }); }, set.fps + ' ' + t('animFps')));
        }

        // A video's repeat is its player's.
        if (!f.video && !f.pdf) {
            main.appendChild(chips(t('animRepeat'), M.animLoops(set.loop).map(function (n) { return [n, n ? t('animTimes').replace('{n}', n) : t('animForever')]; }), set.loop, function (v) { change({ loop: v }); }));
        }

        if (f.dither) {
            var dither = el('input', { type: 'checkbox' });

            dither.checked = !!set.dither;
            dither.addEventListener('change', function () { change({ dither: dither.checked }); });
            main.appendChild(el('label', { class: 'pgie-check' }, [dither, el('span', { text: t('animDither') })]));
        }

        this.animEls.estimate = el('div', { class: 'pgie-anim-estimate', 'aria-live': 'polite' }, [icon('clock'), el('b', { text: t('animEstimating') }), el('span')]);
        main.appendChild(this.animEls.estimate);

        if (long) {
            main.appendChild(el('p', { class: 'pgie-note pgie-anim-warn', role: 'alert', text: t('animTooLong').replace('{n}', X.decimal(f.maxMs / 1000)) }));
        }

        this.animEls.limit = el('div', { class: 'pgie-anim-limit', 'aria-live': 'polite' });
        main.appendChild(this.animEls.limit);

        var mode = M.animSaveMode(this.animFile, set.format), replace = null;

        if (mode === 'replace') {
            replace = el('input', { type: 'checkbox' });
            replace.checked = (set.replace !== false);
            replace.addEventListener('change', function () { set.replace = replace.checked; });
            main.appendChild(el('label', { class: 'pgie-check' }, [replace, el('span', { text: t('animReplace').replace('{name}', this.animFile.name) })]));
        }

        var download = button('pgie-btn', t('animDownload'), 'download', function () { self.startAnimation('download'); }, true);
        var files = button('pgie-btn pgie-primary', t('animSaveFiles'), 'check', function () { self.startAnimation((replace && replace.checked) ? 'replace' : 'new'); }, true);

        download.disabled = long;
        files.disabled = long;
        this.animEls.files = files;

        box.appendChild(el('div', { class: 'pgie-confirm-box pgie-anim-box' }, [
            el('div', { class: 'pgie-anim-head' }, [
                el('h2', { id: 'pgie-anim-title', text: t('animTitle') }),
                button('pgie-ibtn', t('close'), 'close', function () { self.closeAnimation(); })
            ]),
            el('div', { class: 'pgie-anim-body' }, [el('div', { class: 'pgie-anim-side' }, [preview, this.animEls.caption]), main]),
            el('div', { class: 'pgie-anim-foot' }, [el('p', { class: 'pgie-note', text: (this.mixFor && this.mixFor(set)) ? t('mixLocal') : t('animLocal') }), download, files])
        ]));

        this.animPreviewAt(size);

        if (!long) {
            this.estimateAnimation();
        }

        (box.querySelector('.pgie-chip.on') || files).focus({ preventScroll: true });
    };

    // The preview shows the middle of the animation.
    Editor.prototype.animPreviewAt = function (size) {
        var self = this, k = Math.min(1, 240 / Math.max(size.w, size.h));

        Promise.resolve(this.animBase || this.doc.artboard ? null : this.loadBase()).then(function () {
            return self.renderFrame(M.sceneTotal(self.doc) / 2, Math.max(1, Math.round(size.w * k)), Math.max(1, Math.round(size.h * k)));
        }).then(function (c) {
            self.animPreview(c);
        }, function () {});
    };

    Editor.prototype.animPreview = function (source) {
        var c = this.animEls && this.animEls.preview;

        if (!c || !source.width) {
            return;
        }

        var k = Math.min(1, 240 / Math.max(source.width, source.height));

        c.width = Math.max(1, Math.round(source.width * k));
        c.height = Math.max(1, Math.round(source.height * k));
        c.getContext('2d').drawImage(source, 0, 0, c.width, c.height);
    };

    // The size from a few encoded frames, a moment after the last change;
    // an older answer is dropped.
    Editor.prototype.estimateAnimation = function () {
        var self = this, set = Object.assign({}, this.animSet);

        clearTimeout(this.animTimer);

        if (this.animTask) {
            this.animTask.cancel();
            this.animTask = null;
        }

        this.animTimer = setTimeout(function () {
            var task = self.animJob(set, true, function () {});

            self.animTask = task;
            task.promise.then(function (r) {
                if (self.animTask !== task) {
                    return;
                }

                self.animTask = null;

                var e = self.animEls.estimate;

                e.children[1].textContent = '≈ ' + bytesText(r.bytes);
                e.children[2].textContent = M.ANIM_FORMATS[set.format].pdf ? t('animPages').replace('{n}', r.count) : t('animFrames').replace('{n}', r.count) + ' · ' + X.decimal(M.sceneTotal(self.doc) / 1000) + ' ' + t('motionSecondsUnit') + (r.sound ? ' · ' + t('mixEstimate').replace('{size}', bytesText(r.sound)) : '');
                self.animLimit(r.bytes);
            }, function (err) {
                if ((self.animTask === task) && err) {
                    self.animTask = null;
                    self.animEls.estimate.children[1].textContent = err.message || t('animError');
                }
            });
        }, ESTIMATE_DELAY);
    };

    // The server's upload limit beside the estimate; over it Save to Files
    // waits and a smaller size or rate that fits is offered (Download still
    // works).
    Editor.prototype.animLimit = function (bytes) {
        var self = this, limit = Number(config().animationLimit) || 0, box = this.animEls.limit, fits = M.animFits(bytes, limit);
        var smaller = fits ? null : M.animSmaller(M.saveSize(this.doc), this.animSet, bytes, limit);

        box.textContent = '';
        this.animEls.files.disabled = !fits;

        if (!limit) {
            return;
        }

        box.classList.toggle('pgie-anim-warn', !fits);
        box.appendChild(el('p', { class: 'pgie-note', role: fits ? null : 'alert', text: (fits ? t('animLimit') : t('animOverLimit')).replace('{size}', bytesText(limit)) }));

        if (smaller) {
            var size = M.animSize(M.saveSize(this.doc), smaller.edge, M.ANIM_FORMATS[this.animSet.format].even);

            box.appendChild(el('button', { type: 'button', class: 'pgie-btn pgie-anim-smaller', text: t('animUseSmaller').replace('{size}', size.w + ' × ' + size.h + ' px').replace('{fps}', smaller.fps + ' ' + t('animFps')).replace('{bytes}', '≈ ' + bytesText(smaller.bytes)), onclick: function () {
                self.animSet.edge = smaller.edge;
                self.animSet.fps = smaller.fps;
                self.renderAnimation();
            } }));
        }
    };

    // The finished file into Files, after the request's line or in parts
    // when it is larger than one (uploadSend(), pg_image_editor_upload.js):
    // beside the picture, in the canvas's folder, or over the animation the
    // editor opened.
    Editor.prototype.saveAnimationFile = function (blob, mode, name) {
        var self = this, head = {
            action: 'animation', mode: mode, doc: this.doc, base: this.versionBase || 0,
            file_id: this.opts.fileId || 0, folder_id: (this.opts.save && this.opts.save.folderId) || 0,
            name: (this.opts.save && this.opts.save.name) || name,
            target: (mode === 'replace') ? this.animFile.id : 0
        };

        return this.uploadSend(blob, 'animation', head, { error: t('animSaveError'), progress: this.uploadInto(this.animEls && this.animEls.caption) }).then(function (r) {
            // The next save replaces this file, as when it was opened.
            self.animFile = { id: r.id, name: r.name, format: r.format, replace: true };

            return r;
        });
    };

    // The working view: the file, a bar, the steps and Cancel; at the end
    // the file is handed to the browser to keep (target download) or saved
    // into Files (new, replace).
    Editor.prototype.startAnimation = function (target) {
        var self = this, set = Object.assign({}, this.animSet), f = M.ANIM_FORMATS[set.format];
        var name = (target === 'replace') ? this.animFile.name : M.animName((this.file && this.file.name) || this.opts.fileName || '', f.ext);
        var bar = el('span'), percent = el('b'), steps = el('ul', { class: 'pgie-anim-steps' });
        var size = M.animSize(M.saveSize(this.doc), set.edge, f.even), main, side, sound = this.mixFor ? this.mixFor(set) : null;

        clearTimeout(this.animTimer);

        if (this.animTask) {
            this.animTask.cancel();
        }

        side = this.animBox.querySelector('.pgie-anim-side');
        main = el('div', { class: 'pgie-anim-main' }, [
            el('div', { class: 'pgie-anim-label' }, [el('span', { text: name }), percent]),
            el('div', { class: 'pgie-exbar', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-label': t('animTitle') }, [bar]),
            steps,
            el('p', { class: 'pgie-note', text: t('animKeepOpen') })
        ]);

        var body = this.animBox.querySelector('.pgie-anim-body'), foot = this.animBox.querySelector('.pgie-anim-foot');

        body.textContent = '';
        body.appendChild(side);
        body.appendChild(main);
        foot.textContent = '';
        foot.appendChild(el('p', { class: 'pgie-note', text: [t('animFormat_' + set.format), size.w + ' × ' + size.h + ' px', f.pdf ? t('animPages').replace('{n}', M.sceneCount(this.doc)) : set.fps + ' ' + t('animFps')].concat(sound ? [t('mixRate').replace('{codec}', sound.name)] : []).join(' · ') }));

        var cancel = el('button', { type: 'button', class: 'pgie-btn', text: t('cancel'), onclick: function () { self.closeAnimation(); } });

        foot.appendChild(cancel);
        cancel.focus({ preventScroll: true });

        var order = (sound ? ['base', 'mix', 'frames', 'audio', 'encode'] : ['base', 'frames', 'encode']).concat((target === 'download') ? [] : ['upload']);
        var share = { base: 0, mix: 0.03, audio: 0.92, encode: 0.96, upload: 0.98 };

        function progress(stage, done, count) {
            var at = order.indexOf(stage), p = Math.round(100 * ((stage === 'frames') ? 0.05 + (sound ? 0.85 : 0.9) * done / count : share[stage]));

            bar.style.width = p + '%';
            bar.parentNode.setAttribute('aria-valuenow', String(p));
            percent.textContent = pct(p);
            steps.textContent = '';
            order.forEach(function (s, i) {
                var text = t('animStep_' + s).replace('{codec}', sound ? sound.name : '') + ((s === 'frames') && (i === at) ? ' · ' + done + ' / ' + count : '');

                steps.appendChild(el('li', { class: (i < at) ? 'done' : ((i === at) ? 'on' : '') }, [i < at ? icon('check') : el('span', { class: 'pgie-anim-dot' }), el('span', { text: text })]));
            });
            self.animEls.caption.textContent = (stage === 'frames') ? t('animFrameOf').replace('{n}', done).replace('{count}', count) : '';
        }

        var task = this.animJob(set, false, progress);

        this.animTask = task;
        task.promise.then(function (blob) {
            if (self.animTask !== task) {
                return;
            }

            if (target !== 'download') {
                // Once sent the file is written: Cancel no longer stops it.
                progress('upload', 0, 1);
                cancel.disabled = true;
                self.animUploading = true;

                self.saveAnimationFile(blob, target, name).then(function (r) {
                    self.animUploading = false;

                    if (self.animTask === task) {
                        self.animTask = null;
                        self.closeAnimation();
                        self.showMessage(t('animSaved').replace('{name}', r.name));
                    }
                }, function (err) {
                    self.animUploading = false;

                    if (self.animTask === task) {
                        self.animTask = null;
                        self.closeAnimation();
                        self.showMessage(err.message || t('animSaveError'), true);
                    }
                });

                return;
            }

            self.animTask = null;

            var a = el('a', { href: URL.createObjectURL(blob), download: name });

            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 60000);
            self.closeAnimation();
            self.showMessage(t('animDone').replace('{name}', name));
        }, function (err) {
            if ((self.animTask === task) && err) {
                self.animTask = null;
                self.closeAnimation();
                self.showMessage(err.message || t('animError'), true);
            }
        });
    };

    // An animation the editor made opens as its document; the open answer
    // names the file, which Save to Files replaces.
    var openFile = Editor.prototype.openFile;

    Editor.prototype.openFile = function (r) {
        this.animFile = (r && r.animation && r.animation.id) ? r.animation : null;

        if (r && r.doc && (typeof r.doc === 'object')) {
            delete r.doc.animation;
        }

        openFile.call(this, r);
    };

    // "Save animation" in the top bar, while something moves.
    Editor.topItems.push(function () {
        var self = this;

        if (!this.animOffered()) {
            return null;
        }

        this.animBtn = el('button', { type: 'button', class: 'pgie-btn pgie-animbtn', title: t('animTitle'), onclick: function () { self.openAnimation(); } }, [
            icon('film'), el('span', { class: 'pgie-vlabel', text: t('animSave') })
        ]);
        this.animBtn.hidden = true;

        return this.animBtn;
    });

    Editor.views.push(function () {
        if (this.animBtn) {
            this.animBtn.hidden = !this.animMoves();
        }
    });

    Editor.inits.push(function () {
        this.animBox = null;
        this.animSet = null;
        this.animFile = null;
        this.animUploading = false;
        this.animBase = null;
        this.animTask = null;
        this.animTimer = 0;
        this.animEls = null;
        this.animKinds = null;
        this.animVia = null;
        this.frameCuts = null;
    });
}(window.PgImageEditor.parts));
