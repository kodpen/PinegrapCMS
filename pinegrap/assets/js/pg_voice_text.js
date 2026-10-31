/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Convert to text" under a voice message: the browser's own built-in model
 * (the Prompt API, LanguageModel with audio input) writes out a recording
 * the person can already play. The text is shown to that person only, under
 * the player; it is never sent to the server nor kept, so it is gone when the
 * screen is left. A browser without a model that hears audio shows nothing.
 *
 * Used by the Workspace player (assets/js/workspace_voice.js) and by the
 * panel's chat window (assets/js/chat_backend.src.js). Both pass their own texts
 * (pg_voice_text_strings(), includes/fn/voice_text.php).
 *
 * Served minified as assets/js/pg_voice_text.min.js (pg_js_bundles(),
 * includes/fn/output.php), before workspace.min.js on the workspace screens
 * (includes/workspace/screen.php) and beside the chat window's script;
 * after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // A workspace screen loads this file for itself and for the chat window.
    if (window.PGVoiceText) {
        return;
    }

    // The recording is written out in pieces of this many seconds: the
    // model's context holds a short piece well, and the text grows as each
    // piece is done.
    var PIECE = 30;

    // Languages the model is asked to write, tried in order: the screen's,
    // then English, which the model writes everywhere it hears audio.
    var plan = null;

    function api() {
        return (typeof window.LanguageModel !== 'undefined')
            && (typeof window.LanguageModel.create === 'function')
            && (typeof window.LanguageModel.availability === 'function');
    }

    function audioContext() {
        return window.AudioContext || window.webkitAudioContext || null;
    }

    // "tr-TR" -> "tr".
    function language(locale) {
        return String(locale || '').toLowerCase().split(/[-_]/)[0].replace(/[^a-z]/g, '');
    }

    // The options the model is created with for these output languages.
    function options(output) {
        return {
            expectedInputs: [{ type: 'text', languages: ['en'] }, { type: 'audio' }],
            expectedOutputs: [{ type: 'text', languages: [output] }]
        };
    }

    // Resolves to the options of the first language the model can write
    // with audio input ('available', or one still to download), or null.
    // Asked once per screen.
    function ready(locale) {
        if (plan) {
            return plan;
        }

        if (!api() || !audioContext() || !window.fetch) {
            plan = Promise.resolve(null);
            return plan;
        }

        var wanted = [];
        var own = language(locale);

        if (own && (own !== 'en')) {
            wanted.push(own);
        }

        wanted.push('en');

        plan = wanted.reduce(function (found, output) {
            return found.then(function (chosen) {
                if (chosen) {
                    return chosen;
                }

                var asked = options(output);

                return window.LanguageModel.availability(asked).then(function (answer) {
                    return (answer && (answer !== 'unavailable')) ? asked : null;
                }, function () {
                    return null;
                });
            });
        }, Promise.resolve(null));

        return plan;
    }

    // The recording as sound the model takes: decoded by the browser's own
    // audio engine (WebM, Ogg or MP4, whatever it plays), one channel.
    function decode(url, signal) {
        return window.fetch(url, { credentials: 'same-origin', signal: signal }).then(function (response) {
            if (!response.ok) {
                throw new Error('fetch');
            }

            return response.arrayBuffer();
        }).then(function (bytes) {
            var Ctx = audioContext();
            var context = new Ctx();

            return new Promise(function (resolve, reject) {
                var done = function (buffer) {
                    context.close();
                    resolve(buffer);
                };
                var failed = function (error) {
                    context.close();
                    reject(error || new Error('decode'));
                };
                var promised = context.decodeAudioData(bytes, done, failed);

                if (promised && promised.then) {
                    promised.then(null, function () {});
                }
            });
        });
    }

    // The sound cut into pieces of PIECE seconds, the first channel of each.
    function pieces(buffer) {
        var rate = buffer.sampleRate;
        var size = PIECE * rate;
        var samples = buffer.getChannelData(0);
        var list = [];

        for (var start = 0; start < buffer.length; start += size) {
            var part = samples.subarray(start, Math.min(buffer.length, start + size));
            var piece = new window.AudioBuffer({ length: part.length, numberOfChannels: 1, sampleRate: rate });

            piece.copyToChannel(part, 0);
            list.push(piece);
        }

        return list;
    }

    // The words of one piece, from a copy of the session so the pieces before
    // it do not fill the model's context.
    function hear(session, piece, signal) {
        return session.clone({ signal: signal }).then(function (copy) {
            return copy.prompt([{
                role: 'user',
                content: [
                    { type: 'text', value: 'Write out the speech in this recording word for word, in the language it is spoken. Answer with the spoken words only; answer with nothing when nobody speaks.' },
                    { type: 'audio', value: piece }
                ]
            }], { signal: signal }).then(function (text) {
                copy.destroy();
                return String(text || '').trim();
            }, function (error) {
                copy.destroy();
                throw error;
            });
        });
    }

    // The control under a player: hidden until the browser says it can, a
    // button, then the progress and the text. texts(key) gives the screen's
    // words: voice_text_convert, voice_text_working, voice_text_download,
    // voice_text_by, voice_text_failed.
    function control(url, texts, locale) {
        var box = document.createElement('div');
        var button = document.createElement('button');
        var status = document.createElement('div');
        var meter = document.createElement('progress');
        var result = document.createElement('div');
        var by = document.createElement('div');
        // The options the browser said it can work with; set before the
        // button shows.
        var chosen = null;

        box.className = 'pg-voice-text small mt-1';
        box.hidden = true;
        box.style.maxWidth = '22rem';

        button.type = 'button';
        button.className = 'btn btn-sm btn-text theme-secondary px-1';
        button.textContent = texts('voice_text_convert');

        status.className = 'fg-secondary fst-italic';
        status.hidden = true;
        status.setAttribute('role', 'status');
        // Holds the focus while the button is hidden, so a keyboard user
        // stays in the control and hears each step.
        status.setAttribute('tabindex', '-1');

        meter.max = 1;
        meter.hidden = true;
        meter.style.width = '100%';
        meter.setAttribute('aria-label', texts('voice_text_working'));

        result.hidden = true;
        result.style.whiteSpace = 'pre-wrap';
        result.style.overflowWrap = 'anywhere';
        // Takes the focus once written, so the text is read out.
        result.setAttribute('tabindex', '-1');

        by.className = 'fg-secondary fst-italic mt-1';
        by.hidden = true;
        by.textContent = texts('voice_text_by');

        box.appendChild(button);
        box.appendChild(status);
        box.appendChild(meter);
        box.appendChild(result);
        box.appendChild(by);

        ready(locale).then(function (found) {
            chosen = found;
            box.hidden = !found;
        });

        function say(text) {
            status.textContent = text;
            status.hidden = !text;
        }

        // True while the focus is still where the click left it (the
        // status) or was lost to the page; focus the person moved elsewhere
        // is not taken back.
        function held() {
            var active = document.activeElement;

            return !active || (active === status) || (active === document.body);
        }

        // Done: the text with its mark, or, after an error or a recording
        // in which nothing was heard, the failure and the button again.
        function finish(error) {
            var keep = held();

            meter.hidden = true;

            if (error || (result.textContent === '')) {
                say(texts('voice_text_failed'));
                button.hidden = false;

                if (keep) {
                    button.focus();
                }

                return;
            }

            say('');
            by.hidden = false;

            if (keep) {
                result.focus();
            }
        }

        button.addEventListener('click', function () {
            if (!chosen) {
                return;
            }

            // A model still to download needs the click itself, so it is
            // created before anything else is awaited.
            var abort = new window.AbortController();
            var created = window.LanguageModel.create({
                expectedInputs: chosen.expectedInputs,
                expectedOutputs: chosen.expectedOutputs,
                signal: abort.signal,
                monitor: function (monitor) {
                    monitor.addEventListener('downloadprogress', function (event) {
                        say(texts('voice_text_download'));
                        meter.setAttribute('aria-label', texts('voice_text_download'));
                        meter.hidden = false;
                        meter.value = event.loaded || 0;
                    });
                }
            });

            var focused = (document.activeElement === button);

            result.textContent = '';
            result.hidden = true;
            by.hidden = true;
            say(texts('voice_text_working'));

            // The focus moves to the status before the button hides, or it
            // falls to <body>.
            if (focused) {
                status.focus();
            }

            button.hidden = true;

            Promise.all([created, decode(url, abort.signal)]).then(function (both) {
                var session = both[0];
                var list = pieces(both[1]);
                var index = 0;

                meter.hidden = list.length < 2;
                meter.value = 0;
                meter.setAttribute('aria-label', texts('voice_text_working'));
                say(texts('voice_text_working'));

                var next = function () {
                    if (index >= list.length) {
                        return null;
                    }

                    return hear(session, list[index], abort.signal).then(function (words) {
                        index++;
                        meter.value = index / list.length;

                        if (words) {
                            result.textContent += (result.textContent ? ' ' : '') + words;
                            result.hidden = false;
                        }

                        return next();
                    });
                };

                return next();
            }).then(function () {
                finish(null);
            }, function (error) {
                abort.abort();
                finish(error || new Error('failed'));
            }).then(function () {
                created.then(function (session) {
                    session.destroy();
                }, function () {});
            });
        });

        return box;
    }

    window.PGVoiceText = {
        ready: ready,
        control: control,
        language: language
    };
})();
