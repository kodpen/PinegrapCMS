/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: an animation's sound in its MP4 or WebM. The document's
 * sounds (doc.sounds, pg_image_editor_sound.js) are mixed into one stereo
 * track at 48 kHz by an OfflineAudioContext from M.mixPlan(): each file
 * at its place, cut, with its volume and fades as gain ramps. The encoder
 * (pg_image_editor_encode.js) turns it into AAC or Opus through the
 * platform's AudioEncoder and interleaves it with the frames. The Save
 * animation dialog says that GIF, APNG, WebP and PDF carry no sound, and
 * a video format shows its Sound field: the codec, Add sound with the
 * sources' names, or why this browser cannot add it.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_render.js
 * and pg_image_editor_sound.js (pg_js_bundles()); it hangs its code on the
 * editor through PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon;

    // Whether this browser's AudioEncoder takes each video format's codec
    // at the mix's rate, channels and bit rate.
    function mixCaps() {
        var out = {};

        return Promise.all(['mp4', 'webm'].map(function (k) {
            var c = M.mixConfig(k);

            if (!c || (typeof AudioEncoder !== 'function') || (typeof AudioData !== 'function')) {
                out[k] = false;
                return null;
            }

            return AudioEncoder.isConfigSupported({ codec: c.codec, sampleRate: c.rate, numberOfChannels: c.channels, bitrate: c.bitrate }).then(function (r) {
                out[k] = !!(r && r.supported);
            }, function () {
                out[k] = false;
            });
        })).then(function () { return out; });
    }

    Editor.prototype.mixSounds = function () {
        return this.sounds ? this.sounds() : [];
    };

    // The sounds heard in the exported animation.
    Editor.prototype.mixPlanned = function () {
        return M.mixPlan(this.mixSounds(), M.sceneTotal(this.doc));
    };

    // Whether this browser can put the sound into the format: its frames
    // through WebCodecs (the MediaRecorder fallback records no sound) and
    // its codec through AudioEncoder. Null while the browser is asked.
    Editor.prototype.mixCan = function (format) {
        if (!this.mixVia) {
            return null;
        }

        return !!this.mixVia[format] && ((this.animVia || {})[format] === 'codecs');
    };

    // The sound an export of set carries, or null: a video format, Add
    // sound on, something heard, and the browser able to encode it.
    Editor.prototype.mixFor = function (set) {
        var plan = M.mixCarries(set.format) && (set.sound !== false) && this.mixCan(set.format) ? this.mixPlanned() : [];

        if (!plan.length) {
            return null;
        }

        return {
            config: M.mixConfig(set.format), name: M.MIX_CODECS[set.format].name, plan: plan,
            total: M.sceneTotal(this.doc), bytes: M.mixBytes(M.sceneTotal(this.doc), set.format)
        };
    };

    // The mix: every file decoded (the sound part's buffers), each sound at
    // its place with its gain's ramps, rendered offline. Answers a copy of
    // each channel's samples, ready to hand to the encoder.
    Editor.prototype.mixRender = function (sound) {
        var self = this, Ctx = window.OfflineAudioContext || window.webkitOfflineAudioContext;

        if (!Ctx || !this.soundLoad) {
            return Promise.reject(new Error(t('mixError')));
        }

        return Promise.all(M.mixSources(sound.plan).map(function (src) { return self.soundLoad(src); })).then(function () {
            var c = sound.config, ctx = new Ctx(c.channels, M.mixFrames(sound.total, c.rate), c.rate), bad = null;

            sound.plan.forEach(function (p) {
                var entry = self.soundBufs[p.src];

                if (!entry || (entry.state !== 'ready')) {
                    bad = bad || p.src;
                    return;
                }

                var node = ctx.createBufferSource(), gain = ctx.createGain();

                node.buffer = entry.buffer;
                p.gains.forEach(function (g, i) {
                    if (i) {
                        gain.gain.linearRampToValueAtTime(g[1], g[0] / 1000);
                    } else {
                        gain.gain.setValueAtTime(g[1], g[0] / 1000);
                    }
                });
                node.connect(gain);
                gain.connect(ctx.destination);
                node.start(p.when / 1000, p.offset / 1000, p.dur / 1000);
            });

            if (bad) {
                var named = self.mixSounds().filter(function (s) { return s.src === bad; })[0];

                throw new Error(t('mixFileError').replace('{name}', (named && named.name) || bad));
            }

            var done = ctx.startRendering();

            return (done && done.then) ? done : new Promise(function (resolve) {
                ctx.oncomplete = function (e) { resolve(e.renderedBuffer); };
            });
        }).then(function (buffer) {
            var planes = [];

            for (var i = 0; i < buffer.numberOfChannels; i++) {
                planes.push(new Float32Array(buffer.getChannelData(i)));
            }

            return planes;
        });
    };

    // ---- the dialog ----

    // Under the formats: which carry no sound, while the document has some.
    Editor.prototype.mixNote = function (main) {
        if (!this.mixPlanned().length) {
            return;
        }

        main.appendChild(el('p', { class: 'pgie-note pgie-mix-note' }, [icon('mute'), el('span', { text: t('mixNone') })]));
    };

    // A video format's Sound field: the codec and Add sound with the
    // sources' names, or why this browser cannot add it.
    Editor.prototype.mixField = function (main, set) {
        var self = this, names = M.mixNames(this.mixSounds(), M.sceneTotal(this.doc)), can = this.mixCan(set.format);

        if (!M.mixCarries(set.format) || !names.length) {
            return;
        }

        var spec = (can === false) ? null : el('b', { text: t('mixSpec').replace('{codec}', M.MIX_CODECS[set.format].name) });
        var field = el('div', { class: 'pgie-anim-field pgie-mix-field' }, [el('div', { class: 'pgie-anim-label' }, [el('span', { text: t('mixSound') }), spec])]);

        if (can === false) {
            field.appendChild(el('p', { class: 'pgie-note', text: t('mixCannot').replace('{format}', t('animFormat_' + set.format)) }));
        } else {
            var box = el('input', { type: 'checkbox' });

            box.checked = (set.sound !== false);
            box.disabled = (can === null);
            box.addEventListener('change', function () {
                set.sound = box.checked;
                self.renderAnimation();
            });
            field.appendChild(el('label', { class: 'pgie-check' }, [box, el('span', { text: t('mixAdd').replace('{names}', names.join(', ')) })]));
        }

        main.appendChild(field);
    };

    // The browser is asked once per editor, when the dialog first opens;
    // the settings are drawn again unless a file is being made.
    var openAnimation = Editor.prototype.openAnimation;

    Editor.prototype.openAnimation = function () {
        var self = this;

        openAnimation.call(this);

        if (this.animBox && !this.mixAsked) {
            this.mixAsked = true;
            mixCaps().then(function (via) {
                self.mixVia = via;

                if (self.animBox && !self.animBox.querySelector('.pgie-anim-steps')) {
                    self.renderAnimation();
                }
            }, function () {
                self.mixVia = {};
            });
        }
    };

    // A picture with music saves as a video too, nothing else moving.
    var animMoves = Editor.prototype.animMoves;

    Editor.prototype.animMoves = function () {
        return animMoves.call(this) || (this.mixPlanned().length > 0);
    };

    Editor.inits.push(function () {
        this.mixVia = null;
        this.mixAsked = false;
    });
})(window.PgImageEditor.parts);
