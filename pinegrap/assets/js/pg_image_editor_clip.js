/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the video layer (type 'video', the model's CLIP section).
 * A clip shows its source between the source in and out, from its start on
 * the document's timeline, at its speed; the stage draws the frame at the
 * playhead (the video part's frameAt()) into a canvas inside the layer's
 * box, under or over the other layers, and its sound plays with the sound
 * part's in step with the play bar. Videos come from Files or this computer
 * through Import (a file from this computer is uploaded into Files first,
 * action video_upload). The clip's panel holds the source in and out, the
 * start, the speed, the volume and mute, Detach sound and Replace.
 *
 * At rest (outside the Animate tool, and in every still save) the clip
 * shows its frame at the source in; each frame of an animation's export
 * shows the clip's own frame of that moment (clipFramesAt()), from a
 * decoder of its own.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_video.js
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon, button = X.button, section = X.section, tile = X.tile, SVGNS = X.SVGNS, find = X.find;

    var PHONE = '(max-width: 599.98px)';
    // The stage's canvas of a clip is at most this long on its long edge:
    // the preview needs no more, a save draws the full-size frame.
    var PREVIEW_EDGE = 1280;
    // A filmstrip's frame is kept at most this long on its long edge.
    var THUMB_EDGE = 160;

    X.icons.video = 'M3.5 6.5h12v11h-12zM15.5 10.5l5-3v9l-5-3';
    X.icons.replace = 'M4 12a8 8 0 0 1 13.7-5.6L20 8.5M20 4.5v4h-4M20 12a8 8 0 0 1-13.7 5.6L4 15.5M4 19.5v-4h4';

    function origin() {
        return (window.location && window.location.origin) || '';
    }

    // m:ss.d, as the play bar's clock.
    function clock(ms) {
        var tenths = Math.max(0, Math.round(ms / 100)), m = Math.floor(tenths / 600), s = (tenths % 600) / 10;
        var cfg = window.pgImageEditorConfig, sep = (cfg && cfg.decimal) || '.';

        return m + ':' + ((s < 10) ? '0' : '') + s.toFixed(1).replace('.', sep);
    }

    function seconds(ms) {
        return X.decimal(Math.round(ms / 100) / 10) + ' ' + t('motionSecondsUnit');
    }

    function speedText(s) {
        return String(s).replace('.', (window.pgImageEditorConfig && window.pgImageEditorConfig.decimal) || '.') + '×';
    }

    // A video file of this computer: by its type, or its name when the
    // browser gives none.
    X.isVideoFile = function (f) {
        return /^video\/(mp4|webm|x-m4v)$/i.test((f && f.type) || '') || !!M.clipType(f && f.name);
    };

    Editor.prototype.clipLayers = function () {
        return this.doc.layers.filter(function (l) { return l.type === 'video'; });
    };

    // ---- sources and frames ----

    // The source of a clip's address, opened once (a promise; an upload
    // puts the one it opened here). Only the site's own addresses are
    // fetched; a failure is said once.
    Editor.prototype.clipSource = function (src) {
        var self = this, entry = this.clipSources[src];

        if (entry) {
            return entry;
        }

        if (!M.soundSrcOk(src, origin())) {
            return Promise.reject(new Error('video-format'));
        }

        entry = this.clipSources[src] = fetch(src, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('video-broken');
            }

            return r.arrayBuffer();
        }).then(function (bytes) {
            return X.video.open(bytes);
        });

        entry.then(null, function (e) {
            if (!self.clipFailed[src] && !self.finished) {
                self.clipFailed[src] = true;
                self.showMessage(X.video.message(e), true);
                self.renderPanel();
            }
        });

        return entry;
    };

    // What the stage keeps of a clip: the last frame drawn (buf, at most
    // PREVIEW_EDGE), the canvases showing it, the time asked for and the
    // one shown.
    Editor.prototype.clipState = function (l) {
        var st = this.clipStates[l.id];

        if (!st || (st.src !== l.src)) {
            var k = Math.min(1, PREVIEW_EDGE / Math.max(1, l.width || 0, l.height || 0)), buf = document.createElement('canvas');

            buf.width = Math.max(2, Math.round((l.width || 640) * k));
            buf.height = Math.max(2, Math.round((l.height || 360) * k));
            st = this.clipStates[l.id] = { src: l.src, buf: buf, views: [], nodes: [], strips: [], want: null, asked: null, shown: null, busy: false };
        }

        return st;
    };

    // The frame at source time ms into the clip's canvases. One request at
    // a time per clip: while one decodes, the newest time waits and is
    // asked for next, so playing and scrubbing never queue stale frames.
    Editor.prototype.clipDraw = function (l, ms) {
        var self = this, st = this.clipState(l);

        st.want = ms;

        if (st.busy || (ms === st.shown)) {
            return;
        }

        st.busy = true;
        st.asked = ms;
        this.clipSource(l.src).then(function (source) {
            return source.frameAt(ms);
        }).then(function (frame) {
            if (!frame) {
                return;
            }

            if (!self.finished) {
                st.buf.getContext('2d').drawImage(frame, 0, 0, st.buf.width, st.buf.height);
                st.shown = ms;
                self.clipShow(st);
            }

            frame.close();
        }).then(null, function () {
            // Said once by clipSource(); the clip stays blank.
        }).then(function () {
            st.busy = false;

            if (!self.finished && (st.want !== null) && (st.want !== st.asked)) {
                self.clipDraw(l, st.want);
            }
        });
    };

    Editor.prototype.clipShow = function (st) {
        st.views = st.views.filter(function (c) { return c.isConnected; });
        st.views.forEach(function (c) {
            c.getContext('2d').drawImage(st.buf, 0, 0, c.width, c.height);
        });
        // A bar's frames not decoded yet show the stage's.
        st.strips = (st.strips || []).filter(function (c) { return c.isConnected; });
        st.strips.forEach(function (c) {
            if (!c.pgieDone) {
                clipStripPaint(c);
            }
        });
    };

    // A clip on the stage: a canvas in the layer's box (a foreignObject, so
    // it stacks among the other layers), turned around its centre, showing
    // the frame at the playhead with the clip's effects; nothing while the
    // clip is off the timeline there, its last frame while it leads into
    // the next clip.
    function clipNode(l) {
        var st = this.clipState(l), fo = document.createElementNS(SVGNS, 'foreignObject'), c = document.createElement('canvas');
        var show = M.clipShowAt(this.doc.layers, l, (this.time === null) ? null : this.motionNow());
        var box = el('div', { class: 'pgie-clipbox' }, [c]);

        fo.setAttribute('x', l.x);
        fo.setAttribute('y', l.y);
        fo.setAttribute('width', Math.max(1, l.w));
        fo.setAttribute('height', Math.max(1, l.h));
        fo.setAttribute('class', 'pgie-clipview');

        c.width = st.buf.width;
        c.height = st.buf.height;
        fo.appendChild(box);
        this.clipFxStage(box, l);
        st.views.push(c);
        st.nodes = st.nodes.filter(function (n) { return n.isConnected; });
        st.nodes.push(fo);

        if (st.shown !== null) {
            c.getContext('2d').drawImage(st.buf, 0, 0);
        }

        clipShowNode(fo, l, show);

        if (show.at !== null) {
            this.clipDraw(l, show.at);
        }

        return fo;
    }

    // The effects on the stage, the numbers a save paints with: the colour
    // as an SVG feColorMatrix, the blur as CSS (a standard deviation in the
    // box's units; the box cuts it off, as the save's clip does) and the
    // vignette as a gradient over the frame.
    Editor.prototype.clipFxStage = function (box, l) {
        var color = M.clipFxColor(l), blur = M.clipFxBlur(l), stops = M.clipFxVignette(l), filters = [], c = box.firstChild;

        if (color && this.layersSvg) {
            var id = this.fxId + '-clipfx-' + l.id, svg = this.layersSvg, f = svg.querySelector('[id="' + id + '"]');
            var defs = svg.querySelector('defs') || svg.insertBefore(document.createElementNS(SVGNS, 'defs'), svg.firstChild);

            if (!f) {
                f = document.createElementNS(SVGNS, 'filter');
                f.setAttribute('id', id);
                f.setAttribute('color-interpolation-filters', 'sRGB');
                f.appendChild(document.createElementNS(SVGNS, 'feColorMatrix'));
                defs.appendChild(f);
            }

            f.firstChild.setAttribute('type', 'matrix');
            f.firstChild.setAttribute('values', color.matrix.join(' '));
            filters.push('url(#' + id + ')');
        }

        if (blur) {
            filters.push('blur(' + blur + 'px)');
        }

        c.style.filter = filters.join(' ');

        if (stops) {
            box.appendChild(el('div', { class: 'pgie-clipvig', style: 'background: radial-gradient(ellipse farthest-corner at 50% 50%, ' + stops.map(function (s) {
                return 'rgba(0, 0, 0, ' + s[1] + ') ' + (s[0] * 100) + '%';
            }).join(', ') + ')' }));
        }
    };

    // Where a stage node stands at a moment (M.clipShowAt()): hidden with
    // nothing to show; turned around its centre; while a transition runs
    // moved, scaled, faded and cut as the save paints it.
    function clipShowNode(fo, l, show) {
        var st = show.state, cx = l.x + l.w / 2, cy = l.y + l.h / 2, alpha = ((l.opacity === undefined) ? 1 : l.opacity) * (st ? st.alpha : 1);
        var box = fo.firstChild, clip = st && st.clip;

        fo.style.visibility = (show.at === null) ? 'hidden' : '';

        if (st && (st.x || st.y || (st.scale !== 1))) {
            fo.setAttribute('transform', 'translate(' + (cx + st.x) + ' ' + (cy + st.y) + ') rotate(' + (l.rot || 0) + ') scale(' + st.scale + ') translate(' + (-cx) + ' ' + (-cy) + ')');
        } else if (l.rot) {
            fo.setAttribute('transform', 'rotate(' + l.rot + ' ' + cx + ' ' + cy + ')');
        } else {
            fo.removeAttribute('transform');
        }

        if (alpha < 1) {
            fo.setAttribute('opacity', String(Math.max(0, alpha)));
        } else {
            fo.removeAttribute('opacity');
        }

        if (box) {
            box.style.clipPath = clip ? 'inset(' + clip.y + 'px ' + Math.max(0, Math.abs(l.w) - clip.x - clip.w) + 'px ' + Math.max(0, Math.abs(l.h) - clip.y - clip.h) + 'px ' + clip.x + 'px)' : '';
        }
    }

    // A save's frame: the source in at the source's own size.
    function restKey(l) {
        return l.src + '#' + M.clipLayer(l).from;
    }

    function clipReady(l) {
        var self = this, key = restKey(l), entry = this.clipRests[key];

        if (entry) {
            return entry.promise;
        }

        entry = this.clipRests[key] = { canvas: null };
        entry.promise = this.clipSource(l.src).then(function (source) {
            // A request overtaken by the stage's (the same source) answers
            // nothing: asked again, a few times.
            var ask = function (n) {
                return source.frameAt(M.clipLayer(l).from).then(function (f) {
                    return (f || (n <= 0)) ? f : ask(n - 1);
                });
            };

            return ask(3);
        }).then(function (frame) {
            if (frame) {
                var c = document.createElement('canvas');

                c.width = frame.displayWidth;
                c.height = frame.displayHeight;
                c.getContext('2d').drawImage(frame, 0, 0);
                frame.close();
                entry.canvas = c;
            } else {
                // The next save tries again.
                delete self.clipRests[key];
            }
        }, function () {
            // A source that cannot be read draws nothing.
        });

        return entry.promise;
    }

    // In an animation's frame (renderFrame(), this.clipFrames) the clip's
    // frame of that moment, nothing off its span; else, and where that
    // frame could not be decoded, the source in.
    // While a transition runs (the moment renderFrame() draws, frameTime),
    // its state is joined to the layer's motion: moved and scaled, its
    // opacity multiplied, cut to the part shown.
    function clipPaint(g, l) {
        var frames = this.clipFrames, own = (frames && Object.prototype.hasOwnProperty.call(frames, l.id)) ? frames[l.id] : undefined;
        var entry = this.clipRests[restKey(l)], img = own || (entry && entry.canvas);
        var st = ((this.frameTime === null) || (this.frameTime === undefined)) ? null : M.clipShowAt(this.doc.layers, l, this.frameTime).state;

        if ((own === null) || !img) {
            return;
        }

        g.translate(l.x + l.w / 2 + (st ? st.x : 0), l.y + l.h / 2 + (st ? st.y : 0));
        g.rotate((l.rot || 0) * Math.PI / 180);

        if (st) {
            g.scale(st.scale, st.scale);
            g.globalAlpha *= st.alpha;

            if (st.clip) {
                g.beginPath();
                g.rect(-Math.abs(l.w) / 2 + st.clip.x, -Math.abs(l.h) / 2 + st.clip.y, st.clip.w, st.clip.h);
                g.clip();
            }
        }

        clipFxPaint(g, l, img);
    }

    // The frame with the clip's effects, centred on the origin: the colour
    // through applyColor() at the pixels it lands on, the blur through the
    // canvas's filter (in device pixels, so scaled by the transform) cut to
    // the box as the stage's is, then the vignette as a gradient through
    // the box's corners (CSS's ellipse farthest-corner). A browser without
    // the canvas's filter saves the clip unblurred.
    function clipFxPaint(g, l, img) {
        var w = l.w, h = l.h, color = M.clipFxColor(l), blur = M.clipFxBlur(l), stops = M.clipFxVignette(l), src = img, m, k, c, cg, data, grad;

        if (!color && !blur && !stops) {
            g.drawImage(img, -w / 2, -h / 2, w, h);
            return;
        }

        m = g.getTransform ? g.getTransform() : { a: 1, b: 0, c: 0, d: 1 };
        k = Math.sqrt(Math.abs(m.a * m.d - m.b * m.c)) || 1;

        if (color) {
            c = document.createElement('canvas');
            c.width = Math.max(1, Math.min(8192, Math.round(Math.abs(w) * k)));
            c.height = Math.max(1, Math.min(8192, Math.round(Math.abs(h) * k)));
            cg = c.getContext('2d');
            cg.drawImage(img, 0, 0, c.width, c.height);
            data = cg.getImageData(0, 0, c.width, c.height);
            M.applyColor(data.data, color);
            cg.putImageData(data, 0, 0);
            src = c;
        }

        g.save();
        g.beginPath();
        g.rect(-w / 2, -h / 2, w, h);
        g.clip();

        if (blur && ('filter' in g)) {
            g.filter = 'blur(' + (blur * k) + 'px)';
        }

        g.drawImage(src, -w / 2, -h / 2, w, h);
        g.restore();

        if (stops) {
            g.save();
            g.scale(w / Math.SQRT2, h / Math.SQRT2);
            grad = g.createRadialGradient(0, 0, 0, 0, 0, 1);
            stops.forEach(function (s) { grad.addColorStop(s[0], 'rgba(0, 0, 0, ' + s[1] + ')'); });
            g.fillStyle = grad;
            g.fillRect(-Math.SQRT1_2, -Math.SQRT1_2, Math.SQRT2, Math.SQRT2);
            g.restore();
        }
    }

    // ---- the frames of an animation's export ----

    // The export's frames of a source: a decoder of its own on the same
    // bytes (a source answers only its newest request, and the stage and
    // the bars ask too), asked one frame after another so none overtakes
    // the one before.
    Editor.prototype.clipFrameSource = function (src) {
        var fs = this.clipFrameSets[src];

        if (!fs) {
            fs = this.clipFrameSets[src] = { source: null, queue: Promise.resolve() };
            fs.source = this.clipSource(src).then(function (s) { return X.video.open(s.bytes); });
            fs.source.then(null, function () {});
        }

        return fs;
    };

    // Each visible clip's frame at document time (M.clipFramesAt()) in the
    // document being drawn, by layer id: a VideoFrame the caller closes, or
    // null off the clip's span. A clip whose frame cannot be decoded is left
    // out and paints its source in; a still asks for none.
    Editor.prototype.clipFramesAt = function (time) {
        var self = this, times = M.clipFramesAt(this.doc, time), frames = {}, waits = [];

        this.doc.layers.forEach(function (l) {
            var fs, ms;

            if (!Object.prototype.hasOwnProperty.call(times, l.id)) {
                return;
            }

            if (times[l.id] === null) {
                frames[l.id] = null;
                return;
            }

            fs = self.clipFrameSource(l.src);
            ms = times[l.id];
            fs.queue = fs.queue.then(function () {
                return fs.source;
            }).then(function (source) {
                return source.frameAt(ms);
            }).then(function (f) {
                if (f) {
                    frames[l.id] = f;
                }
            }, function () {});
            waits.push(fs.queue);
        });

        return Promise.all(waits).then(function () { return frames; });
    };

    // With the playhead (a motion dock with no rows): each clip shows the
    // frame at that moment, or nothing off its span.
    Editor.prototype.clipsUpdate = function () {
        var self = this, now = (this.time === null) ? null : this.motionNow();

        this.clipLayers().forEach(function (l) {
            var st = self.clipStates[l.id], show = M.clipShowAt(self.doc.layers, l, now);

            if (l.hidden) {
                return;
            }

            // Every node of the clip: the stage's and a scene's thumbnail.
            (st ? st.nodes : []).forEach(function (n) {
                clipShowNode(n, l, show);
            });

            if (show.at !== null) {
                self.clipDraw(l, show.at);
            }
        });
    };

    // The clips' own sound for the sound part (soundStart()); a silent clip
    // is left out, so its file is never fetched for sound.
    Editor.prototype.soundsMore = function () {
        var offset = this.motionStart(), o = origin();

        return this.clipLayers().filter(function (l) { return M.soundSrcOk(l.src, o); }).map(function (l) {
            return M.clipSound(l, offset);
        }).filter(function (s) { return !s.muted; });
    };

    // ---- adding ----

    // The source opened, then one change: the clip at the bottom of the
    // stack, filling the canvas, from the playhead; the document long
    // enough for it (up to its 60 s).
    Editor.prototype.clipAdd = function (f) {
        var self = this;

        this.showMessage(t('clipLoading'));

        return this.clipSource(f.url).then(function (source) {
            var start = (self.time === null) ? 0 : Math.max(0, self.motionNow()), id = null;
            var l = M.clipNew(f, { length: source.duration, width: source.width, height: source.height, sound: source.sound }, self.viewRect(), start);

            self.hideMessage();

            if (self.tool !== 'motion') {
                self.toSelect();
            }

            self.change(function (doc) {
                var end = M.clipEnd({ layers: [l] }), dm = M.docMotion(doc);

                l.id = id = M.newId(doc);
                doc.layers.unshift(l);

                if (end > dm.duration) {
                    doc.motion = Object.assign({}, doc.motion || {}, { duration: Math.ceil(end / 100) * 100 });
                }
            });
            self.select([id]);
        }, function () {
            // Said by clipSource().
        });
    };

    // A video of this computer: opened here first (a codec this browser
    // cannot decode is said before anything is sent), then uploaded into
    // Files beside the picture or in the canvas's folder, then a layer.
    Editor.prototype.videoImport = function (file) {
        var self = this;

        if (!this.importApi()) {
            this.showMessage(t('clipNeedsFiles'), true);
            return;
        }

        X.video.open(file).then(function (local) {
            self.showMessage(t('clipUploading'));

            return self.clipUpload(file).then(function (r) {
                // The source opened here is the uploaded file's.
                self.clipSources[r.url] = Promise.resolve(local);

                return self.clipAdd({ id: r.id, name: r.name, url: r.url });
            }, function (e) {
                local.close();
                self.showMessage(e.message || t('clipUploadError'), true);
            });
        }, function (e) {
            self.showMessage(X.video.message(e), true);
        });
    };

    // The file after the request's line, or in parts when it is larger than
    // one (uploadSend(), pg_image_editor_upload.js).
    Editor.prototype.clipUpload = function (file) {
        var head = {
            action: 'video_upload', file_id: this.opts.fileId || 0,
            folder_id: (this.opts.save && this.opts.save.folderId) || 0, name: file.name || ''
        };

        return this.uploadSend(file, 'video', head, { error: t('clipUploadError') });
    };

    // Videos from Files (action video_files) over the editor; a press adds
    // one, or hands it to pick(file) (Replace).
    Editor.prototype.openClipFiles = function (pick) {
        var self = this, timer = null, st = this.clipFiles;
        var find = el('input', { type: 'search', class: 'pgie-input pgie-stkfind', value: st.search, placeholder: t('clipFind'), 'aria-label': t('clipFind') });

        st.pick = (typeof pick === 'function') ? pick : null;
        st.list = el('div', { class: 'pgie-sndfiles' });
        find.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { self.loadClipFiles(find.value); }, 350);
        });

        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-clip-title' }, [
            el('div', { class: 'pgie-confirm-box pgie-importbox' }, [
                el('div', { class: 'pgie-importhead' }, [el('h2', { id: 'pgie-clip-title', text: t('clipFiles') }), button('pgie-ibtn', t('close'), 'close', function () { self.closeConfirm(); })]),
                find,
                st.list
            ])
        ]);
        this.confirmReturn = st.pick ? document.activeElement : (this.importBtn || document.activeElement);
        this.root.appendChild(this.confirmBox);
        this.loadClipFiles(st.search);
        find.focus();
    };

    Editor.prototype.loadClipFiles = function (search) {
        var self = this, st = this.clipFiles, ask = ++st.serial;

        st.search = search || '';
        this.request({ action: 'video_files', search: st.search }).then(function (r) {
            if (ask === st.serial) {
                self.showClipFiles((r && (r.status === 'success')) ? (r.files || []) : null);
            }
        }, function () {
            if (ask === st.serial) {
                self.showClipFiles(null);
            }
        });
    };

    Editor.prototype.showClipFiles = function (files) {
        var self = this, list = this.clipFiles.list;

        if (!list || !this.confirmBox || !this.confirmBox.contains(list)) {
            return;
        }

        list.textContent = '';

        if (!files || !files.length) {
            list.appendChild(el('p', { class: 'pgie-note', text: t(files ? 'clipFilesNone' : 'clipFilesError') }));
            return;
        }

        files.forEach(function (f) {
            list.appendChild(el('button', { type: 'button', class: 'pgie-sndfile', onclick: function () {
                var pick = self.clipFiles.pick;

                self.closeConfirm();

                if (pick) {
                    pick(f);
                } else if (M.soundSrcOk(f.url, origin())) {
                    self.clipAdd(f);
                }
            } }, [icon('video'), el('span', { text: f.name }), el('em', { text: f.size || '' })]));
        });
    };

    // ---- changes ----

    // A timing field (the source in or out, the speed) pulls the clips after
    // the clip on its row and the document's length with it.
    var TIMING = ['from', 'to', 'speed', 'start'];

    Editor.prototype.clipSet = function (id, patch, key, viewOnly) {
        if (Object.keys(patch).some(function (k) { return TIMING.indexOf(k) >= 0; })) {
            this.clipCommit(M.clipRipple(this.doc.layers, id, patch), key, viewOnly);
            return;
        }

        this.change(function (doc) {
            var l = find(doc, id);

            if (l) {
                Object.assign(l, patch);
            }
        }, key, viewOnly);
    };

    // Detach sound: the clip's sound as a row of its own (doc.sounds) from
    // the same source and times; the clip muted. One undo step.
    Editor.prototype.clipDetach = function (l) {
        var o = origin(), snd = M.clipDetach(l, M.soundId(this.doc), this.motionStart());

        if (!snd || (M.soundList(this.doc, o).length >= M.SOUND_MAX)) {
            return;
        }

        this.change(function (doc) {
            var list = M.soundList(doc, o);

            list.push(snd);
            doc.sounds = list;
            find(doc, l.id).muted = true;
        });
    };

    // Replace: another video from Files in the same box and on the same
    // timeline place.
    Editor.prototype.clipReplace = function (l) {
        var self = this;

        this.openClipFiles(function (f) {
            if (!M.soundSrcOk(f.url, origin())) {
                return;
            }

            self.clipSource(f.url).then(function (source) {
                self.clipSet(l.id, M.clipReplace(l, f, { length: source.duration, width: source.width, height: source.height, sound: source.sound }));
            }, function () {
                // Said by clipSource().
            });
        });
    };

    // ---- the clip rows on the tracks ----

    // A dragged end snaps to another end within this many pixels.
    var SLACK_PX = 6;

    // A clip's row: the clips split from one another on one row, drawn
    // where the topmost of them is in the stack; the others give none.
    Editor.prototype.clipTrackRow = function (l, end) {
        var self = this, row = M.clipRow(this.doc.layers, l).filter(function (o) { return !o.hidden; });
        var top = row.reduce(function (best, o) { return (self.doc.layers.indexOf(o) > self.doc.layers.indexOf(best)) ? o : best; }, row[0]);
        var on = row.some(function (o) { return self.sel.indexOf(o.id) >= 0; }), name = this.layerName(top);
        var lane, pick, bars = {};

        if (top !== l) {
            return false;
        }

        lane = el('div', { class: 'pgie-trlane' }, [el('span', { class: 'pgie-trline' })]);
        pick = el('button', { type: 'button', class: 'pgie-trname', title: name, 'aria-pressed': on ? 'true' : 'false', onclick: function (e) {
            self.trackSelect(top.id, e.shiftKey);
        } }, [icon('video'), el('span', { text: name })]);

        row.forEach(function (o) {
            bars[o.id] = self.clipBar(o, end, lane, bars);
            lane.appendChild(bars[o.id]);
        });

        // The cuts a split left: a dashed line with scissors.
        M.clipJoins(row).forEach(function (at) {
            lane.appendChild(el('span', { class: 'pgie-clipcut', style: 'left: ' + pct(at, end), 'aria-hidden': 'true' }, [icon('scissors')]));
        });

        // Between two clips that meet, the button of the transition from
        // the first: it selects that clip and opens its Effects.
        row.forEach(function (o) {
            var next = M.clipTouching(self.doc.layers, o, 1), tr = M.clipTransition(o), label;

            if (!next) {
                return;
            }

            label = t('clipTransitionOpen').replace('{name}', self.layerName(o));
            lane.appendChild(el('button', { type: 'button', class: 'pgie-cliptr' + (tr ? ' on' : ''), style: 'left: ' + pct(M.clipSpan(next).from, end), title: label, 'aria-label': label, onpointerdown: function (e) {
                e.stopPropagation();
            }, onclick: function (e) {
                e.stopPropagation();
                self.clipTab = 'fx';
                self.select([o.id]);
            } }, [icon(tr ? 'm_' + tr.kind : 'm_none')]));
        });

        lane.addEventListener('pointerdown', function (e) {
            if ((e.target === lane) || e.target.classList.contains('pgie-trline')) {
                self.trackSelect(top.id, e.shiftKey);
            }
        });

        return el('div', { class: 'pgie-trrow pgie-cliprow' + (on ? ' on' : ''), role: 'group', 'aria-label': name }, [pick, lane]);
    };

    function pct(ms, end) {
        return (100 * Math.max(0, ms) / end) + '%';
    }

    // The clip's label on its bar and in its tip: its name ("(2)" for the
    // second part of a source) and its source in and out.
    Editor.prototype.clipLabel = function (l, c) {
        var part = M.clipPart(this.doc.layers, l), name = (l.name || t('layer_video')) + ((part.n > 1) ? ' (' + part.n + ')' : '');

        return name + ' · ' + clock(c.from) + ' → ' + clock(c.to);
    };

    // One clip's bar: a filmstrip of its cut, its sound as a waveform under
    // the frames, its label, grips at both ends when it is the one selected.
    Editor.prototype.clipBar = function (l, end, lane, bars) {
        var self = this, c = M.clipLayer(l), sp = M.clipSpan(l), on = (this.sel.length === 1) && (this.sel[0] === l.id), st = this.clipState(l);
        var strip = el('canvas', { class: 'pgie-clipstrip', 'aria-hidden': 'true' }), label = this.clipLabel(l, c), wave = this.clipWave(l);
        var marks = [];

        // Off 1× a badge with the speed; an effects mark on a clip with any.
        if (c.speed !== 1) {
            marks.push(el('b', { class: 'pgie-clipspeed', text: speedText(c.speed) }));
            label += ' · ' + speedText(c.speed);
        }

        if (M.clipFxOn(l)) {
            marks.push(el('span', { class: 'pgie-clipfxmark' }, [icon('adjust')]));
            label += ' · ' + t('clipTabFx');
        }

        var bar = el('span', { class: 'pgie-trbar pgie-clipbar' + (on ? ' on' : '') + ((c.muted || !c.sound) ? ' pgie-clipquiet' : '') + (wave ? ' pgie-clipsounded' : ''), style: 'left: ' + pct(sp.from, end) + '; width: ' + pct(sp.to - sp.from, end), title: label, role: 'img', 'aria-label': label }, [
            strip, wave, el('span', { class: 'pgie-clipname' }, marks.concat([this.clipLabel(l, c)]))
        ]);

        strip.width = 240;
        strip.height = 30;
        strip.pgieState = st;
        st.strips = (st.strips || []).filter(function (s) { return s.isConnected; });
        st.strips.push(strip);

        if (st.shown !== null) {
            clipStripPaint(strip);
        } else if (this.time === null) {
            // At rest nothing asked for a frame yet: the source in, shown
            // until the strip's own frames are decoded.
            this.clipDraw(l, c.from);
        }

        // Sized once the bar is laid out.
        window.requestAnimationFrame(function () {
            if (strip.isConnected && !self.finished) {
                self.clipStripFill(l, strip);
            }
        });

        bar.addEventListener('pointerdown', function (e) { self.clipDown(e, l, 'move', bar, lane, bars); });

        if (on) {
            ['start', 'end'].forEach(function (grip) {
                var g = el('span', { class: 'pgie-trgrip pgie-trgrip-' + grip, 'aria-hidden': 'true' });

                g.addEventListener('pointerdown', function (e) {
                    e.stopPropagation();
                    self.clipDown(e, l, grip, bar, lane, bars);
                });
                bar.appendChild(g);
            });
        }

        return bar;
    };

    // ---- the filmstrip and the waveform ----

    // The bars' frames of a source: a second source on the same bytes, so
    // their seeks never overtake the stage's (a source answers only its
    // newest request), asked one at a time in time order.
    Editor.prototype.clipThumbs = function (src) {
        var th = this.clipThumbSets[src];

        if (!th) {
            th = this.clipThumbSets[src] = { frames: {}, want: [], busy: false, strips: [], source: null };
            th.source = this.clipSource(src).then(function (s) { return X.video.open(s.bytes); });
            th.source.then(null, function () {});
        }

        return th;
    };

    // A bar's filmstrip: as many frames as fit its width side by side, each
    // the source at the middle of its part of the cut (M.clipStripTimes()).
    Editor.prototype.clipStripFill = function (l, strip) {
        var th = this.clipThumbs(l.src), k = Math.min(2, window.devicePixelRatio || 1), w = strip.clientWidth, h = strip.clientHeight, ratio;

        if (!w || !h) {
            return;
        }

        strip.width = Math.round(w * k);
        strip.height = Math.round(h * k);
        ratio = (Number(l.width) || 16) / Math.max(1, Number(l.height) || 9);
        strip.pgieThumbs = th;
        strip.pgieTimes = M.clipStripTimes(l, Math.ceil(strip.width / Math.max(1, strip.height * ratio)));
        strip.pgieTimes.forEach(function (ms) {
            if (!(ms in th.frames) && (th.want.indexOf(ms) < 0)) {
                th.want.push(ms);
            }
        });
        th.want.sort(function (a, b) { return a - b; });
        th.strips = th.strips.filter(function (s) { return s.isConnected; });
        th.strips.push(strip);
        clipStripPaint(strip);
        this.clipThumbNext(th);
    };

    Editor.prototype.clipThumbNext = function (th) {
        var self = this, ms;

        if (th.busy || !th.want.length || this.finished) {
            return;
        }

        ms = th.want.shift();
        th.busy = true;
        th.source.then(function (source) {
            return source.frameAt(ms);
        }).then(function (frame) {
            var c = null, k;

            if (frame) {
                k = Math.min(1, THUMB_EDGE / Math.max(1, frame.displayWidth, frame.displayHeight));
                c = document.createElement('canvas');
                c.width = Math.max(1, Math.round(frame.displayWidth * k));
                c.height = Math.max(1, Math.round(frame.displayHeight * k));
                c.getContext('2d').drawImage(frame, 0, 0, c.width, c.height);
                frame.close();
            }

            // null: not asked for again.
            th.frames[ms] = c;
            th.strips = th.strips.filter(function (s) { return s.isConnected; });
            th.strips.forEach(function (s) {
                if (s.pgieTimes.indexOf(ms) >= 0) {
                    clipStripPaint(s);
                }
            });
        }).then(null, function () {
            // A source that cannot be opened twice keeps the stage's frame.
            th.want = [];
        }).then(function () {
            th.busy = false;
            self.clipThumbNext(th);
        });
    };

    // The strip's frames side by side, each cropped to its part; a frame
    // not decoded yet shows the stage's last one.
    function clipStripPaint(strip) {
        var th = strip.pgieThumbs, st = strip.pgieState, times = strip.pgieTimes || [], g = strip.getContext('2d'), tw, done = true;

        if (!times.length) {
            if (st && (st.shown !== null)) {
                clipCover(g, st.buf, 0, 0, strip.width, strip.height);
            }

            return;
        }

        tw = strip.width / times.length;
        g.clearRect(0, 0, strip.width, strip.height);
        times.forEach(function (ms, i) {
            var f = th.frames[ms] || null;

            if (!f) {
                done = false;
                f = (st && (st.shown !== null)) ? st.buf : null;
            }

            if (f) {
                clipCover(g, f, Math.round(i * tw), 0, Math.round((i + 1) * tw) - Math.round(i * tw), strip.height);
            }
        });
        strip.pgieDone = done;
    }

    function clipCover(g, img, x, y, w, h) {
        var s = Math.max(w / Math.max(1, img.width), h / Math.max(1, img.height)), sw = w / s, sh = h / s;

        g.drawImage(img, (img.width - sw) / 2, (img.height - sh) / 2, sw, sh, x, y, w, h);
    }

    // The clip's sound under its frames, as a sound row draws it (the sound
    // part's soundWave() over the cut); faded while it does not play. The
    // sound part decodes the file once and draws the rows again then.
    // null for a clip without a sound track.
    Editor.prototype.clipWave = function (l) {
        var snd, box;

        if (!M.clipLayer(l).sound || !this.soundWave || !this.soundBufs) {
            return null;
        }

        if (!this.soundBufs[l.src]) {
            this.soundLoad(l.src);
        }

        snd = M.clipSound(l, 0);
        box = this.soundWave(snd);
        box.setAttribute('class', 'pgie-sndwave pgie-clipwave' + (snd.muted ? ' pgie-clipwave-off' : ''));

        return box;
    };

    // The times a dragged clip snaps to: the other layers' bars, the other
    // clips' ends, the playhead.
    Editor.prototype.clipTargets = function (l, end) {
        var list = M.trackTargets(this.doc.layers, end, l.id), now = this.motionNow();

        this.clipLayers().forEach(function (o) {
            var sp = M.clipSpan(o);

            if (o.id !== l.id) {
                list.push(sp.from, sp.to);
            }
        });

        if ((now !== null) && (now >= 0)) {
            list.push(now);
        }

        return list.filter(function (n, i) { return list.indexOf(n) === i; });
    };

    // A press on a clip's bar or grip: the clip is selected; the drag shows
    // on the bars (the clips after a trimmed end follow), an amber line
    // where it snaps, and for an end that end's frame on the stage with a
    // pill naming its source time. One change on release; Escape cancels.
    Editor.prototype.clipDown = function (e, l, grip, bar, lane, bars) {
        var self = this, end = this.motionEnd(), width = lane.getBoundingClientRect().width;
        var x0 = e.clientX, result = null, moved = false, targets = this.clipTargets(l, end), layers = M.clone(this.doc.layers);
        var snapLine = el('span', { class: 'pgie-clipsnap', 'aria-hidden': 'true', hidden: 'hidden' }), pill = null;

        if ((e.button !== 0) || !(width > 0)) {
            return;
        }

        e.preventDefault();

        if (this.playing) {
            this.pause();
            return;
        }

        if ((this.sel.length !== 1) || (this.sel[0] !== l.id)) {
            this.sel = [l.id];
            this.renderPanel();
        }

        lane.appendChild(snapLine);
        this.trackDragging = true;
        this.clipDragging = true;
        this.hint.textContent = t((grip === 'move') ? 'hintClipMove' : 'hintClipTrim');

        try {
            bar.setPointerCapture(e.pointerId);
        } catch (err) {
            // A pointer the browser no longer tracks: the drag still follows moves.
        }

        var show = function (patches) {
            patches.forEach(function (p) {
                var o = layers.filter(function (n) { return n.id === p.id; })[0], b = bars[p.id], sp;

                if (o && b) {
                    sp = M.clipSpan(Object.assign({}, o, p.patch));
                    b.style.left = pct(sp.from, end);
                    b.style.width = pct(sp.to - sp.from, end);
                }
            });
        };

        var framePill = function (r) {
            var mine = Object.assign({}, l, r.patches[0].patch), sp = M.clipSpan(mine), text;

            // The end's own frame: the last one before the source out.
            self.clipDraw(l, (grip === 'end') ? Math.max(0, r.source - 1) : r.source);
            // Only this clip on the stage: another one over it would hide the frame.
            self.clipLayers().forEach(function (o) {
                var st = self.clipStates[o.id];

                (st ? st.nodes : []).forEach(function (n) { n.style.visibility = (o.id === l.id) ? '' : 'hidden'; });
            });

            text = t((grip === 'end') ? 'clipPillEnd' : 'clipPillStart').replace('{source}', clock(r.source)).replace('{length}', seconds(sp.to - sp.from));

            if (!pill) {
                pill = el('div', { class: 'pgie-clippill', role: 'status' });
                self.stage.appendChild(pill);
            }

            pill.textContent = text;
        };

        var move = function (ev) {
            var dt = (ev.clientX - x0) / width * end, o = { targets: targets, slack: SLACK_PX / width * end, fine: ev.shiftKey };

            moved = moved || (Math.abs(ev.clientX - x0) > 2);

            if (!moved) {
                return;
            }

            result = (grip === 'move') ? M.clipMove(layers, l.id, dt, o) : M.clipTrim(layers, l.id, grip, dt, o);

            if (!result) {
                return;
            }

            show(result.patches);
            snapLine.hidden = !result.snapped;

            if (result.snapped) {
                var sp = M.clipSpan(Object.assign({}, l, result.patches[0].patch));
                var at = (grip === 'move') ? ((targets.indexOf(sp.from) >= 0) ? sp.from : sp.to) : result.at;

                snapLine.style.left = pct(at, end);
            }

            if (grip !== 'move') {
                framePill(result);
            }
        };

        var done = function (keep) {
            bar.removeEventListener('pointermove', move);
            bar.removeEventListener('pointerup', up);
            bar.removeEventListener('pointercancel', cancel);
            document.removeEventListener('keydown', key, true);
            self.trackDragging = false;
            self.clipDragging = false;

            if (pill) {
                pill.remove();
            }

            if (keep && moved && result) {
                self.clipCommit(result.patches);
            } else {
                self.render();
            }

            self.clipsUpdate();
        };

        var up = function () { done(true); };
        var cancel = function () { done(false); };

        var key = function (ev) {
            if (ev.key === 'Escape') {
                ev.preventDefault();
                ev.stopPropagation();
                done(false);
            }
        };

        bar.addEventListener('pointermove', move);
        bar.addEventListener('pointerup', up);
        bar.addEventListener('pointercancel', cancel);
        document.addEventListener('keydown', key, true);
    };

    // Patches of the clips ([{ id, patch }]) as one undo step; the length
    // follows the clips.
    Editor.prototype.clipCommit = function (patches, key, viewOnly) {
        this.change(function (doc) {
            M.clipApply(doc, patches);
        }, key, viewOnly);
    };

    // Split at the playhead: the second part a new layer right above the
    // first, on the same source and row, selected. One undo step.
    Editor.prototype.clipSplitAt = function (l) {
        var now = (this.time === null) ? null : this.motionNow(), id = M.newId(this.doc), r = M.clipSplit(l, now, id);

        if (l.locked) {
            return;
        }

        // The playhead must be inside the clip, 0.1 s from either end.
        if (!r) {
            this.showMessage(t('clipSplitNote'), true);
            return;
        }

        this.change(function (doc) {
            var i = doc.layers.findIndex(function (o) { return o.id === l.id; });

            if (i < 0) {
                return;
            }

            Object.assign(doc.layers[i], r.left);
            doc.layers.splice(i + 1, 0, r.right);
            M.clipApply(doc, []);
        });
        this.select([id]);
    };

    // Delete in the clip's panel: the length follows the clips left, in
    // the same undo step.
    Editor.prototype.clipRemove = function (l) {
        if (l.locked) {
            return;
        }

        this.change(function (doc) {
            doc.layers = doc.layers.filter(function (o) { return o.id !== l.id; });
            M.clipApply(doc, []);
        });
        this.select(this.sel.filter(function (id) { return id !== l.id; }));
    };

    // What the clip rows show: a drag keeps its bars till release.
    Editor.prototype.clipsKey = function () {
        var self = this;

        if (this.clipDragging) {
            return this.clipKeyNow;
        }

        this.clipKeyNow = JSON.stringify(this.clipLayers().map(function (l) {
            var snd = self.soundBufs && self.soundBufs[l.src];

            return [l.id, l.from, l.to, l.start, l.speed, l.row || '', l.name, l.muted ? 1 : 0, self.sel.indexOf(l.id) >= 0 ? 1 : 0, snd ? snd.state : ''];
        }));

        return this.clipKeyNow;
    };

    Editor.trackRowMakers.push(function (l, end) {
        return (l.type === 'video') ? this.clipTrackRow(l, end) : undefined;
    });

    // ---- the panel ----

    Editor.prototype.clipSlider = function (l, field, label, min, max, value, show) {
        var self = this, shown = el('span', { class: 'pgie-val', text: show(value) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(min), max: String(Math.max(min, max)), step: (field === 'volume') ? '1' : '100', value: String(value), 'aria-label': label });

        slider.addEventListener('input', function () {
            var n = Number(slider.value), c = M.clipLayer(l), patch = {};

            shown.textContent = show(n);

            if (field === 'volume') {
                patch.volume = n / 100;
            } else if (field === 'from') {
                patch.from = Math.min(n, c.to - M.CLIP_MIN);
            } else if (field === 'to') {
                patch.to = Math.max(n, c.from + M.CLIP_MIN);
            } else {
                patch[field] = n;
            }

            self.clipSet(l.id, patch, 'clip-' + l.id + '-' + field, true);
        });
        slider.addEventListener('change', function () {
            self.history.key = null;
            self.render();
        });

        return section(label, slider, shown);
    };

    // The clip's controls: its file, source in and out, start, speed,
    // volume and mute, then Detach sound and Replace; whole adds Duplicate
    // and Delete (the Animate tool's panel, which has no arrange rows).
    Editor.prototype.clipPanel = function (body, l, whole) {
        var self = this, c = M.clipLayer(l), failed = this.clipFailed[l.src];
        var part = M.clipPart(this.doc.layers, l), sp = M.clipSpan(l);
        var meta = [(part.of > 1) ? t('clipSameSource') : '', (l.width && l.height) ? l.width + ' × ' + l.height : '', clock(c.length)].filter(Boolean).join(' · ');
        var name = (l.name || t('layer_video')) + ((part.n > 1) ? ' (' + part.n + ')' : '');

        body.appendChild(el('div', { class: 'pgie-sndfile pgie-sndhead pgie-cliphead' }, [icon('video'), el('span', { text: name }), el('em', { text: meta })]));

        // The part's source and timeline times, as the bar shows them.
        body.appendChild(section(t('clipFromSource'), el('div', { class: 'pgie-cliptimes' }, [el('span', { text: clock(c.from) }), el('span', { class: 'pgie-cliparrow', 'aria-hidden': 'true', text: '→' }), el('span', { text: clock(c.to) })]), el('span', { class: 'pgie-val', text: seconds(c.to - c.from) })));
        body.appendChild(section(t('clipOnTimeline'), el('div', { class: 'pgie-cliptimes' }, [el('span', { text: clock(sp.from) }), el('span', { class: 'pgie-cliparrow', 'aria-hidden': 'true', text: '→' }), el('span', { text: clock(sp.to) })]), el('span', { class: 'pgie-val', text: seconds(sp.to - sp.from) })));

        if (failed) {
            body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('clipBroken') }));
        }

        body.appendChild(this.clipSlider(l, 'from', t('clipFrom'), 0, c.length, c.from, clock));
        body.appendChild(this.clipSlider(l, 'to', t('clipTo'), 0, c.length, c.to, clock));
        body.appendChild(this.clipSlider(l, 'start', t('soundStart'), 0, Math.max(this.motionEnd(), c.start), c.start, seconds));

        var speeds = el('div', { class: 'pgie-seg pgie-clipspeeds', role: 'group', 'aria-label': t('clipSpeed') });

        M.CLIP_SPEEDS.forEach(function (s) {
            var on = (s === c.speed);

            speeds.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: speedText(s), onclick: function () {
                self.clipSet(l.id, { speed: s });
            } }));
        });
        body.appendChild(section(t('clipSpeed'), speeds, el('span', { class: 'pgie-val', text: seconds((c.to - c.from) / c.speed) })));

        if (c.sound) {
            var mute = button('pgie-ibtn pgie-sndmute' + (c.muted ? ' on' : ''), t(c.muted ? 'soundUnmute' : 'soundMute'), c.muted ? 'mute' : 'volume', function () {
                self.clipSet(l.id, { muted: !c.muted });
            });
            var vol = this.clipSlider(l, 'volume', t('soundVolume'), 0, 100, Math.round(c.volume * 100), function (n) { return X.pct(Math.round(n)); });

            mute.setAttribute('aria-pressed', c.muted ? 'true' : 'false');
            vol.classList.add('pgie-sndvol');
            vol.appendChild(el('div', { class: 'pgie-sndvolrow' }, [mute, vol.lastChild]));
            body.appendChild(vol);

            if (c.speed !== 1) {
                body.appendChild(el('p', { class: 'pgie-note', text: t('clipSpeedMuted') }));
            }
        } else {
            body.appendChild(el('p', { class: 'pgie-note', text: t('clipNoSound') }));
        }

        var split = tile('scissors', t('clipSplit'), function () { self.clipSplitAt(l); });
        var detach = tile('music', t('clipDetach'), function () { self.clipDetach(l); });
        var tiles = [split, detach];

        split.disabled = !!l.locked;
        split.title = t('clipSplitNote');

        detach.disabled = !c.sound || (c.speed !== 1) || l.locked;
        detach.title = t('clipDetachNote');

        if (this.importApi()) {
            var swap = tile('replace', t('clipReplace'), function () { self.clipReplace(l); });

            swap.disabled = !!l.locked;
            tiles.push(swap);
        }

        if (whole) {
            tiles.push(tile('duplicate', t('duplicate'), function () { self.addLayers([l], 10); }));
            tiles.push(tile('trash', t('delete'), function () { self.clipRemove(l); }));
        }

        body.appendChild(el('div', { class: 'pgie-tiles pgie-sndtiles' }, tiles));
        body.appendChild(el('p', { class: 'pgie-note', text: t('clipNote') }));

        if (whole && this.tracksShown()) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('clipTracksNote') }));
        }
    };

    // The Animate tool with one clip selected: tabs Clip and Motion (the
    // entrance, loop and exit as for any layer).
    var motionPanel = Editor.panels.motion;

    Editor.panels.motion = function (body) {
        var self = this, picked = this.selected(), l = picked[0], phone = window.matchMedia && window.matchMedia(PHONE).matches;

        if (!l || (l.type !== 'video') || (picked.length > 1)) {
            motionPanel.call(this, body);
            return;
        }

        var tabs = el('div', { class: 'pgie-seg pgie-cliptabs', role: 'tablist', 'aria-label': t('layer_video') });

        [['clip', t('clipTab')], ['fx', t('clipTabFx')], ['motion', t('clipTabMotion')]].forEach(function (tab) {
            var on = (self.clipTab === tab[0]);

            tabs.appendChild(el('button', { type: 'button', role: 'tab', class: on ? 'on' : '', 'aria-selected': on ? 'true' : 'false', text: tab[1], onclick: function () {
                self.clipTab = tab[0];
                self.render();
            } }));
        });

        if (this.clipTab === 'motion') {
            motionPanel.call(this, body);
            // Right after the play bar a phone puts in the panel.
            var bar = phone ? body.querySelector('.pgie-mbar') : null;

            body.insertBefore(tabs, bar ? bar.nextSibling : body.firstChild);
            return;
        }

        if (phone) {
            body.appendChild(this.motionBar());
        }

        body.appendChild(tabs);

        if (this.clipTab === 'fx') {
            this.clipFxPanel(body, l);
        } else {
            this.clipPanel(body, l, true);
        }

        this.motionWhole(body);
    };

    // The Effects tab: the six sliders and Reset, then the transition into
    // the next clip on the row (or a note when no clip meets this one).
    Editor.prototype.clipFxPanel = function (body, l) {
        var self = this, fx = M.clipFx(l), grid = el('div', { class: 'pgie-clipfx' }), next = M.clipTouching(this.doc.layers, l, 1);
        var labels = { brightness: t('adj_brightness'), contrast: t('adj_contrast'), saturation: t('adj_saturation'), temperature: t('adj_temperature'), blur: t('clipFxBlur'), vignette: t('adj_vignette') };
        var reset = el('button', { type: 'button', class: 'pgie-link', text: t('clipFxReset'), onclick: function () { self.clipSet(l.id, { fx: null }); } });

        reset.disabled = !M.clipFxOn(l) || !!l.locked;
        body.appendChild(el('div', { class: 'pgie-clipfxhead' }, [el('h3', { text: t('clipTabFx') }), reset]));

        M.CLIP_FX.forEach(function (d) {
            var key = d[0], shown = el('span', { class: 'pgie-val', text: signed(fx[key], d[1] < 0) });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: String(d[1]), max: String(d[2]), step: '1', value: String(fx[key]), 'aria-label': labels[key] });

            slider.disabled = !!l.locked;
            slider.addEventListener('input', function () {
                var now = find(self.doc, l.id), patch = {};

                shown.textContent = signed(Number(slider.value), d[1] < 0);
                patch[key] = Number(slider.value);
                self.clipSet(l.id, { fx: Object.assign({}, (now && now.fx) || {}, patch) }, 'clip-' + l.id + '-fx-' + key, true);
            });
            slider.addEventListener('change', function () {
                self.history.key = null;
                self.render();
            });
            grid.appendChild(section(labels[key], slider, shown));
        });

        body.appendChild(grid);

        if (!next) {
            body.appendChild(section(t('clipTransition'), el('p', { class: 'pgie-note', text: t('clipTransitionNone') })));
        } else {
            var tr = M.sceneTransition(l.transition), kind = M.clipTransition(l) ? tr.kind : 'none';
            var kinds = el('div', { class: 'pgie-tiles pgie-skinds', role: 'radiogroup', 'aria-label': t('clipTransition') });

            M.SCENE_TRANSITIONS.forEach(function (k) {
                var on = (kind === k), b = tile('m_' + k, t('motion_' + k), function () {
                    self.clipSet(l.id, { transition: (k === 'none') ? null : { kind: k, ms: tr.ms } });
                });

                b.classList.toggle('on', on);
                b.setAttribute('role', 'radio');
                b.setAttribute('aria-checked', on ? 'true' : 'false');
                b.disabled = !!l.locked;
                kinds.appendChild(b);
            });

            body.appendChild(section(t('clipTransition'), kinds));

            if (kind !== 'none') {
                var time = el('input', { type: 'range', class: 'pgie-range', min: String(M.SCENE_TRANSITION_MIN), max: String(M.SCENE_TRANSITION_MAX), step: '100', value: String(tr.ms), 'aria-label': t('sceneTransitionTime') });
                var timeShown = el('span', { class: 'pgie-val', text: seconds(tr.ms) });

                time.addEventListener('input', function () {
                    timeShown.textContent = seconds(Number(time.value));
                    self.clipSet(l.id, { transition: { kind: kind, ms: Number(time.value) } }, 'clip-' + l.id + '-transition', true);
                });
                time.addEventListener('change', function () {
                    self.history.key = null;
                    self.render();
                });
                body.appendChild(section(t('sceneTransitionTime'), time, timeShown));
            }
        }

        body.appendChild(el('p', { class: 'pgie-note', text: t('clipFxNote') }));
    };

    function signed(n, sign) {
        return (sign && (n > 0)) ? '+' + n : String(n);
    }

    // The footer's hint while the tracks show a clip.
    Editor.views.push(function () {
        if ((this.tool === 'motion') && !this.clipDragging && this.hint && this.tracksShown && this.tracksShown() && this.clipLayers().length) {
            this.hint.textContent = t('hintClips');
        }
    });

    Editor.layerTypes.video = {
        node: clipNode,
        paint: clipPaint,
        ready: clipReady,
        ratio: true,
        name: function (l) { return l.name || ''; },
        panel: function (body, layers) {
            if (layers.length === 1) {
                this.clipPanel(body, layers[0], false);
            }
        }
    };

    // Import: Video from the site.
    Editor.importItems.push(function () {
        var self = this;

        return this.importApi() ? [t('clipImportSite'), function () { self.openClipFiles(); }] : null;
    });

    // The editor closed: every source and its decoder let go.
    var finish = Editor.prototype.finish;

    Editor.prototype.finish = function () {
        var sources = this.clipSources, thumbs = this.clipThumbSets, framed = this.clipFrameSets;

        this.clipSources = {};
        this.clipThumbSets = {};
        this.clipFrameSets = {};
        Object.keys(sources).forEach(function (src) {
            sources[src].then(function (s) { s.close(); }, function () {});
        });
        Object.keys(thumbs).forEach(function (src) {
            thumbs[src].source.then(function (s) { s.close(); }, function () {});
        });
        Object.keys(framed).forEach(function (src) {
            framed[src].source.then(function (s) { s.close(); }, function () {});
        });

        return finish.apply(this, arguments);
    };

    Editor.inits.push(function () {
        this.clipSources = {};
        this.clipFailed = {};
        this.clipStates = {};
        this.clipRests = {};
        this.clipThumbSets = {};
        this.clipFrameSets = {};
        this.clipTab = 'clip';
        this.clipDragging = false;
        this.clipKeyNow = '';
        this.clipFiles = { search: '', serial: 0, list: null, pick: null };
    });

    Editor.motionDocks.push({ key: Editor.prototype.clipsKey, node: function () { return null; }, update: Editor.prototype.clipsUpdate, note: function () { return null; } });
}(window.PgImageEditor.parts));
