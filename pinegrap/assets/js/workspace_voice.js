/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - voice messages: the recording started from the microphone of
 * the writing box (a red dot, the time, Stop to send it, Discard), and the
 * player every sound file in a conversation is drawn with (play and pause,
 * the time, the speed, the download link), and the assistants' answers read
 * aloud by the browser's own speech for a person who switched it on. A
 * recording without a server transcript offers "Convert to text" by the
 * browser's own model (assets/js/pg_voice_text.js).
 *
 * assets/js/workspace.js draws the writing box and the messages and calls in
 * here with its own helpers. A recording leaves as an ordinary file through
 * the screen's upload (uploadFiles(), ws_attach), so the server checks and
 * keeps it like any other file (includes/workspace/files.php); one made from
 * a sound's own microphone answers that sound (parent_id). Every text comes
 * from the #ws-config block (includes/workspace/voice.php).
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The longest recording in seconds; it stops and is sent there, after a
    // warning this many seconds before.
    var LIMIT = 300;
    var WARN_BEFORE = 30;

    // Shorter than this (milliseconds) is a slip of the finger: dropped.
    var SHORTEST = 1000;

    // Speech needs no more: a five-minute message stays near 1.2 MB.
    var BITRATE = 32000;

    // The containers asked for, in order, and the extension each is saved
    // with. WebM audio is saved as .weba, never .webm: ws_file_kind()
    // (includes/workspace/files.php) draws a .webm as a video.
    var TYPES = [
        ['audio/webm;codecs=opus', 'weba'],
        ['audio/ogg;codecs=opus', 'ogg'],
        ['audio/mp4', 'm4a']
    ];

    // The waveform kept with a recording: at most this many levels, each a
    // digit from 0 (silence) to 9 (the loudest part of that recording).
    var PEAKS = 120;

    // A transcript this short is shown open under its player.
    var SHORT_TRANSCRIPT = 280;

    var SPEEDS = [1, 1.5, 2];
    var SPEED_KEY = 'ws-voice-speed';

    // One recording at a time, and one player sounding at a time.
    var recording = null;
    var sounding = null;

    // Reading answers aloud: switched on per site and person in this browser
    // only (nothing is kept on the server), off until switched on. An answer
    // longer than SPEAK_MAX characters is read up to a sentence end before.
    var SPEAK_KEY = 'ws-voice-speak';
    var SPEAK_MAX = 1500;

    // The choice made on this screen, by key, for a browser that keeps
    // nothing.
    var speakChosen = {};

    // Recording needs a secure context (getUserMedia is missing outside one)
    // and an engine with MediaRecorder.
    function supported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    }

    function pad(number) {
        return (number < 10 ? '0' : '') + number;
    }

    // 75 -> "1:15".
    function clock(seconds) {
        var whole = Math.max(0, Math.floor(seconds || 0));

        return Math.floor(whole / 60) + ':' + pad(whole % 60);
    }

    // A name the recording keeps once it is a file: voice-20261024-093015.weba.
    function fileName(date, extension) {
        return 'voice-' + date.getFullYear() + pad(date.getMonth() + 1) + pad(date.getDate())
            + '-' + pad(date.getHours()) + pad(date.getMinutes()) + pad(date.getSeconds()) + '.' + extension;
    }

    // A file this recorder made, by its name.
    function isVoice(name) {
        return /^voice-/i.test(String(name || ''));
    }

    // The first container the browser says it can make, or null when it
    // answers none of them and is left to choose.
    function chooseType() {
        if (window.MediaRecorder && window.MediaRecorder.isTypeSupported) {
            for (var i = 0; i < TYPES.length; i++) {
                try {
                    if (window.MediaRecorder.isTypeSupported(TYPES[i][0])) {
                        return TYPES[i];
                    }
                } catch (error) {
                    // An engine that throws here answers like one that says no.
                }
            }
        }

        return null;
    }

    // The extension for the container the recorder actually made, which may
    // differ from the one asked for.
    function extensionOf(type) {
        var text = String(type || '').toLowerCase();

        if (text.indexOf('ogg') !== -1) {
            return 'ogg';
        }

        if ((text.indexOf('mp4') !== -1) || (text.indexOf('aac') !== -1) || (text.indexOf('m4a') !== -1)) {
            return 'm4a';
        }

        return 'weba';
    }

    function stopTracks(stream) {
        if (stream && stream.getTracks) {
            stream.getTracks().forEach(function (track) {
                try {
                    track.stop();
                } catch (error) {
                    // Already ended.
                }
            });
        }
    }

    // ── Recording ──

    // Starts recording into the writing box. Called from the microphone's
    // click itself: a browser asks for the microphone only on a gesture.
    // options: box (the .ws-composer the recording row stands in), send(file)
    // and done() when the row has gone, sent or not.
    function start(options, h) {
        var t = h.t;

        if (recording || !supported()) {
            if (!supported()) {
                h.toast(t('voice_unsupported'), 'warning');
            }

            return;
        }

        // Taken while the browser asks, so a second click waits for it.
        recording = true;

        var asked;

        try {
            asked = navigator.mediaDevices.getUserMedia({
                audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true }
            });
        } catch (error) {
            asked = Promise.reject(error);
        }

        asked.then(function (stream) {
            // The box went away (another channel opened) while the browser asked.
            if (!options.box || !options.box.isConnected) {
                stopTracks(stream);
                recording = null;
                return;
            }

            try {
                record(stream, options, h);
            } catch (error) {
                // Whatever went wrong, the microphone does not stay on.
                stopTracks(stream);
                recording = null;
                h.toast(t('error_generic'), 'danger');
            }
        }, function (error) {
            var name = (error && error.name) || '';

            recording = null;
            h.toast(((name === 'NotAllowedError') || (name === 'SecurityError') || (name === 'PermissionDeniedError'))
                ? t('voice_denied') : t('error_generic'), 'warning');
        });
    }

    // Listens to the microphone while it records: the loudness of each tick
    // (root mean square of the samples), for the waveform. Without the Web
    // Audio API the recording goes without one.
    function meter(stream) {
        var Context = window.AudioContext || window.webkitAudioContext;
        var context = null;
        var analyser = null;
        var samples = null;

        if (!Context) {
            return null;
        }

        try {
            context = new Context();
            analyser = context.createAnalyser();
            analyser.fftSize = 1024;
            context.createMediaStreamSource(stream).connect(analyser);
            samples = new Uint8Array(analyser.fftSize);
        } catch (error) {
            if (context) {
                try { context.close(); } catch (inner) { /* already closed */ }
            }

            return null;
        }

        return {
            levels: [],
            read: function () {
                var sum = 0;

                analyser.getByteTimeDomainData(samples);

                for (var i = 0; i < samples.length; i++) {
                    var value = (samples[i] - 128) / 128;

                    sum += value * value;
                }

                this.levels.push(Math.sqrt(sum / samples.length));
            },
            close: function () {
                try { context.close(); } catch (error) { /* already closed */ }
            }
        };
    }

    // The levels heard, as the server keeps them: the loudest of each of at
    // most PEAKS stretches, against the loudest of the recording, on a square
    // root scale so quiet speech still shows. A silent recording keeps none.
    function peaksOf(levels) {
        var count = Math.min(PEAKS, levels.length);
        var buckets = [];
        var loudest = 0;
        var i;

        for (i = 0; i < count; i++) {
            var from = Math.floor(i * levels.length / count);
            var to = Math.max(from + 1, Math.floor((i + 1) * levels.length / count));
            var top = 0;

            for (var j = from; j < to; j++) {
                top = Math.max(top, levels[j]);
            }

            buckets.push(top);
            loudest = Math.max(loudest, top);
        }

        if (loudest < 0.005) {
            return '';
        }

        return buckets.map(function (value) {
            return String(Math.min(9, Math.round(9 * Math.sqrt(value / loudest))));
        }).join('');
    }

    function record(stream, options, h) {
        var t = h.t;
        var el = h.el;
        var box = options.box;
        var chosen = chooseType();
        var recorder = null;
        var settings = { audioBitsPerSecond: BITRATE };

        if (chosen) {
            settings.mimeType = chosen[0];
        }

        try {
            recorder = new window.MediaRecorder(stream, settings);
        } catch (error) {
            try {
                recorder = new window.MediaRecorder(stream);
            } catch (inner) {
                recorder = null;
            }
        }

        if (!recorder) {
            stopTracks(stream);
            recording = null;
            h.toast(t('voice_unsupported'), 'warning');
            return;
        }

        var chunks = [];
        var started = Date.now();
        var dropped = false;
        var stopping = false;
        var stoppedAt = 0;
        var warned = false;
        var shown = -1;
        var timer = 0;
        var listening = meter(stream);

        var row = el('div', 'ws-voice-rec');
        var time = el('span', 'ws-voice-rec-time', '0:00');
        var discard = h.button('btn btn-sm btn-text theme-secondary', '', 'bi-trash', t('voice_discard'));
        var stop = h.button('btn btn-sm btn-solid theme-primary rounded-pill px-5 ws-voice-rec-stop', '', 'bi-stop-fill', t('voice_stop'));
        var dot = el('span', 'ws-voice-rec-dot');
        var cap = el('span', 'ws-voice-rec-cap', '/ ' + clock(LIMIT));

        row.setAttribute('role', 'group');
        row.setAttribute('aria-label', t('voice_record'));
        dot.setAttribute('aria-hidden', 'true');
        cap.setAttribute('aria-hidden', 'true');
        time.setAttribute('aria-live', 'polite');
        time.setAttribute('aria-atomic', 'true');
        row.appendChild(dot);
        row.appendChild(time);
        row.appendChild(cap);
        row.appendChild(el('span', 'ws-grow'));
        row.appendChild(discard);
        row.appendChild(stop);

        function finish() {
            if (timer) {
                window.clearInterval(timer);
                timer = 0;
            }

            document.removeEventListener('keydown', onKey, true);
            stopTracks(stream);

            if (listening) {
                listening.close();
            }

            row.remove();
            box.classList.remove('ws-voice-recording');
            recording = null;
        }

        function closed() {
            finish();

            if (options.done) {
                options.done();
            }
        }

        // Stop and send (drop: Discard, or the box gone). A Discard pressed
        // before the recorder has handed its last data over still drops it.
        function end(drop) {
            dropped = dropped || !!drop;

            if (stopping) {
                return;
            }

            stopping = true;
            stoppedAt = Date.now();
            window.clearInterval(timer);
            timer = 0;

            if (recorder.state === 'inactive') {
                closed();
                return;
            }

            try {
                recorder.stop();
            } catch (error) {
                closed();
                return;
            }

            // The microphone goes off at once; the last data still arrives.
            stopTracks(stream);
        }

        function tick() {
            // Another channel opened: nobody is left to send it to.
            if (!box.isConnected) {
                end(true);
                return;
            }

            var elapsed = (Date.now() - started) / 1000;
            var whole = Math.floor(elapsed);

            if (listening) {
                try {
                    listening.read();
                } catch (error) {
                    listening = null;
                }
            }

            if (whole !== shown) {
                shown = whole;
                time.textContent = clock(whole);
            }

            if (!warned && (elapsed >= LIMIT - WARN_BEFORE)) {
                warned = true;
                row.classList.add('ws-voice-rec-ending');
                h.toast(t('voice_ending', WARN_BEFORE), 'warning');
            }

            if (elapsed >= LIMIT) {
                end(false);
            }
        }

        // Escape discards, unless it was meant for a drawer or a window open
        // over the screen.
        function onKey(event) {
            var target = event.target;

            if ((event.key !== 'Escape') || (target && (target !== document.body) && !box.contains(target))) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            end(true);
        }

        recorder.addEventListener('dataavailable', function (event) {
            if (event.data && event.data.size > 0) {
                chunks.push(event.data);
            }
        });

        recorder.addEventListener('stop', function () {
            var made = recorder.mimeType || (chosen ? chosen[0] : '') || '';
            var type = made.split(';')[0] || 'audio/webm';
            // As long as it was when Stop was pressed, not when the data came.
            var length = (stoppedAt || Date.now()) - started;
            var blob = chunks.length ? new Blob(chunks, { type: type }) : null;
            var peaks = listening ? peaksOf(listening.levels) : '';

            finish();

            if (!dropped && blob && blob.size && (length >= SHORTEST)) {
                var name = fileName(new Date(started), extensionOf(made));
                var file;

                try {
                    file = new File([blob], name, { type: type });
                } catch (error) {
                    file = blob;
                    file.name = name;
                }

                options.send(file, { duration_ms: length, peaks: peaks });
            }

            if (options.done) {
                options.done();
            }
        });

        discard.addEventListener('click', function () { end(true); });
        stop.addEventListener('click', function () { end(false); });

        try {
            recorder.start();
        } catch (error) {
            stopTracks(stream);
            recording = null;
            h.toast(t('voice_unsupported'), 'warning');
            return;
        }

        recording = recorder;
        started = Date.now();
        box.classList.add('ws-voice-recording');
        box.appendChild(row);
        document.addEventListener('keydown', onKey, true);
        timer = window.setInterval(tick, 250);
        stop.focus();
    }

    // ── Player ──

    // The speed chosen on this screen; read from the browser until then, so
    // a browser that keeps nothing still holds it while the screen is open.
    var chosenSpeed = 0;

    function storedSpeed() {
        var value = 1;

        if (chosenSpeed) {
            return chosenSpeed;
        }

        try {
            value = parseFloat(window.localStorage.getItem(SPEED_KEY) || '1');
        } catch (error) {
            value = 1;
        }

        return (SPEEDS.indexOf(value) !== -1) ? value : 1;
    }

    function storeSpeed(value) {
        chosenSpeed = value;

        try {
            window.localStorage.setItem(SPEED_KEY, String(value));
        } catch (error) {
            // Nothing to remember it in; it holds for this screen.
        }
    }

    // 1.5 as the reader writes it: "1,5×" in Turkish.
    function speedLabel(value, locale) {
        var text = String(value);

        try {
            text = value.toLocaleString(locale || undefined);
        } catch (error) {
            text = String(value);
        }

        return text + '×';
    }

    // A recording's transcript under its player: machine text, named as such
    // with the service that wrote it, apart from the person's own words. A
    // long one opens on a click; one still waiting says so.
    function transcript(voice, h) {
        var t = h.t;
        var el = h.el;

        if (voice.status === 'done' && voice.transcript) {
            var details = el('details', 'ws-voice-transcript small mt-1');
            var summary = el('summary', 'fg-secondary', t('voice_transcript'));
            var text = el('div', 'mt-1', voice.transcript);
            var by = el('div', 'fg-secondary fst-italic mt-1', t('voice_machine', voice.provider || '?'));

            details.style.maxWidth = '22rem';
            text.style.whiteSpace = 'pre-wrap';
            text.style.overflowWrap = 'anywhere';

            if (voice.language) {
                text.lang = voice.language;
            }

            details.open = voice.transcript.length <= SHORT_TRANSCRIPT;
            details.appendChild(summary);
            details.appendChild(text);
            details.appendChild(by);

            return details;
        }

        if (voice.status === 'queued' || voice.status === 'running') {
            return el('div', 'ws-voice-transcript small fg-secondary fst-italic mt-1', t('voice_transcribing'));
        }

        return null;
    }

    // The waveform in place of the seek line: one stroke per level, the part
    // played drawn in the accent colour.
    function waveform(bar, fill, peaks) {
        var strokes = [];

        bar.style.display = 'flex';
        bar.style.alignItems = 'center';
        bar.style.gap = '1px';
        bar.style.height = '1.25rem';
        bar.style.background = 'transparent';
        fill.style.display = 'none';

        peaks.split('').forEach(function (level) {
            var stroke = document.createElement('span');

            stroke.style.flex = '1 1 0';
            stroke.style.minWidth = '1px';
            stroke.style.height = (15 + Number(level) * 85 / 9) + '%';
            stroke.style.borderRadius = '1px';
            stroke.style.background = 'var(--pg-border-color)';
            bar.appendChild(stroke);
            strokes.push(stroke);
        });

        return function (share) {
            var reached = Math.round(share * strokes.length);

            strokes.forEach(function (stroke, index) {
                stroke.style.background = (index < reached) ? 'var(--ws-accent)' : 'var(--pg-border-color)';
            });
        };
    }

    // A sound file in the conversation: file {url, name, voice}. options.reply,
    // when given, draws a microphone that answers it with a recording: a
    // tap, since a phone's long press opens the message's menu. A recording
    // with a row (file.voice) draws its waveform and, below, its transcript.
    function player(file, h, options) {
        var t = h.t;
        var el = h.el;
        var locale = (h.cfg && h.cfg.locale) || '';
        var box = el('div', 'ws-voice');
        var audio = document.createElement('audio');
        var play = h.button('btn btn-solid theme-primary ws-voice-play', '', 'bi-play-fill', t('voice_play'));
        var main = el('div', 'ws-voice-main');
        var name = el('div', 'ws-voice-name', isVoice(file.name) ? t('voice_message') : file.name);
        var bar = el('div', 'ws-voice-bar');
        var fill = el('span');
        var time = el('div', 'ws-voice-time', '0:00');
        var speed = el('button', 'btn btn-sm btn-text theme-secondary ws-voice-speed');
        var save = el('a', 'btn btn-sm btn-text theme-secondary');
        var rate = storedSpeed();
        // Chrome's WebM recordings say nothing of their length: it is
        // measured by seeking to the end once (measure()).
        var measuring = false;
        var settle = null;
        var length = 0;
        var voice = file.voice || null;
        // The length the recorder measured, shown until the file states one.
        var stated = (voice && voice.duration_ms > 0) ? voice.duration_ms / 1000 : 0;
        var paint = (voice && /^[0-9]+$/.test(voice.peaks || '')) ? waveform(bar, fill, voice.peaks) : null;

        audio.preload = 'metadata';
        audio.src = file.url;
        name.title = file.name;
        bar.setAttribute('aria-hidden', 'true');
        bar.appendChild(fill);
        main.appendChild(name);
        main.appendChild(bar);
        main.appendChild(time);

        speed.type = 'button';
        speed.title = t('voice_speed');

        save.href = file.url;
        save.setAttribute('download', file.name);
        save.title = t('download');
        save.setAttribute('aria-label', t('download'));
        save.appendChild(h.icon('bi-download'));

        box.appendChild(play);
        box.appendChild(main);
        box.appendChild(speed);

        if (options && options.reply) {
            var answer = h.button('btn btn-sm btn-text theme-secondary ws-voice-reply', '', 'bi-mic', t('voice_reply'));

            answer.addEventListener('click', function () {
                options.reply();
            });
            box.appendChild(answer);
        }

        box.appendChild(save);
        box.appendChild(audio);

        function setRate(value) {
            rate = value;
            audio.defaultPlaybackRate = value;
            audio.playbackRate = value;
            speed.textContent = speedLabel(value, locale);
            speed.setAttribute('aria-label', t('voice_speed') + ' ' + speedLabel(value, locale));
        }

        function draw() {
            if (measuring) {
                return;
            }

            var now = audio.currentTime || 0;
            var shown = length || stated;

            time.textContent = shown ? (clock(now) + ' / ' + clock(shown)) : clock(now);
            fill.style.width = length ? (Math.min(100, (now / length) * 100) + '%') : '0';

            if (paint) {
                paint(length ? Math.min(1, now / length) : 0);
            }
        }

        function drawButton() {
            var paused = audio.paused;
            var label = paused ? t('voice_play') : t('voice_pause');

            play.querySelector('i').className = 'bi ' + (paused ? 'bi-play-fill' : 'bi-pause-fill');
            play.title = label;
            play.setAttribute('aria-label', label);
            box.classList.toggle('ws-voice-on', !paused);
        }

        // A length the file does not state (Infinity) is found by seeking far
        // past the end: the browser reads to the end, reports the length in
        // durationchange, and the position goes back to the start.
        function measure() {
            if (isFinite(audio.duration) && (audio.duration > 0)) {
                length = audio.duration;
                draw();
                return;
            }

            if (measuring) {
                return;
            }

            var giveUp = 0;
            var check = function () {
                if (isFinite(audio.duration)) {
                    settle();
                }
            };

            settle = function () {
                if (!measuring) {
                    return;
                }

                measuring = false;
                settle = null;
                audio.removeEventListener('durationchange', check);
                window.clearTimeout(giveUp);

                if (isFinite(audio.duration) && (audio.duration > 0)) {
                    length = audio.duration;
                }

                try {
                    audio.currentTime = 0;
                } catch (error) {
                    // Not seekable yet; it starts from the beginning anyway.
                }

                draw();
            };

            measuring = true;
            audio.addEventListener('durationchange', check);
            // A server that cannot seek leaves the length unknown until the
            // file has been played through once; the player works without it.
            giveUp = window.setTimeout(settle, 5000);

            try {
                audio.currentTime = 1e101;
            } catch (error) {
                settle();
            }
        }

        audio.addEventListener('loadedmetadata', measure);
        audio.addEventListener('durationchange', function () {
            if (!measuring && isFinite(audio.duration) && (audio.duration > 0)) {
                length = audio.duration;
                draw();
            }
        });
        audio.addEventListener('timeupdate', draw);
        audio.addEventListener('play', function () {
            speakStop();

            if (sounding && (sounding !== audio)) {
                sounding.pause();
            }

            sounding = audio;
            drawButton();
        });
        audio.addEventListener('pause', drawButton);
        audio.addEventListener('ended', function () {
            if (measuring) {
                return;
            }

            audio.currentTime = 0;
            drawButton();
            draw();
        });
        audio.addEventListener('error', function () {
            box.classList.add('ws-voice-error');
            play.disabled = true;
        });

        play.addEventListener('click', function () {
            // Pressed while the length is still being found: play from the
            // start, the length comes when the end is reached.
            if (measuring && settle) {
                settle();
            }

            if (audio.paused) {
                // The speed last chosen on any player, this screen's included.
                setRate(storedSpeed());

                var started = audio.play();

                if (started && started.catch) {
                    started.catch(function () {
                        drawButton();
                    });
                }
            } else {
                audio.pause();
            }
        });

        speed.addEventListener('click', function () {
            var next = SPEEDS[(SPEEDS.indexOf(rate) + 1) % SPEEDS.length];

            setRate(next);
            storeSpeed(next);
        });

        // A click on the line moves to that point.
        bar.addEventListener('click', function (event) {
            var place = bar.getBoundingClientRect();

            if (!length || measuring || !place.width) {
                return;
            }

            audio.currentTime = Math.max(0, Math.min(1, (event.clientX - place.left) / place.width)) * length;
            draw();
        });

        setRate(rate);
        draw();

        // The server's transcript, or one on its way; else "Convert to
        // text" where the browser's own model hears audio (pg_voice_text.js).
        var under = voice ? transcript(voice, h) : null;

        if (!under && window.PGVoiceText) {
            under = window.PGVoiceText.control(file.url, t, locale);
        }

        if (!under) {
            return box;
        }

        var wrap = el('div', 'ws-voice-wrap');

        wrap.appendChild(box);
        wrap.appendChild(under);

        return wrap;
    }

    // Whether a recording is running, or the browser is still asked for the
    // microphone.
    function busy() {
        return !!recording;
    }

    // ── Answers read aloud (speechSynthesis) ──

    function speakSupported() {
        return !!(window.speechSynthesis && window.SpeechSynthesisUtterance);
    }

    // One switch per site and person, so two people sharing a browser each
    // keep their own.
    function speakKey(site, viewer) {
        return SPEAK_KEY + ':' + String(site || '') + ':' + String(viewer || 0);
    }

    function speakOn(site, viewer) {
        var key = speakKey(site, viewer);

        if (Object.prototype.hasOwnProperty.call(speakChosen, key)) {
            return speakChosen[key];
        }

        try {
            return window.localStorage.getItem(key) === 'on';
        } catch (error) {
            return false;
        }
    }

    function speakSet(site, viewer, on) {
        var key = speakKey(site, viewer);

        speakChosen[key] = !!on;

        try {
            if (on) {
                window.localStorage.setItem(key, 'on');
            } else {
                window.localStorage.removeItem(key);
            }
        } catch (error) {
            // Nothing to remember it in; it holds for this screen.
        }

        if (!on) {
            speakStop();
        }
    }

    // The words an answer is read with: its text as shown, white space
    // folded; a long one cut at the last sentence end before SPEAK_MAX, or at
    // a word when there is none.
    function speakText(text, max) {
        var words = String(text || '').replace(/\s+/g, ' ').trim();
        var limit = max || SPEAK_MAX;

        if (words.length <= limit) {
            return words;
        }

        var cut = words.slice(0, limit);
        var end = Math.max(cut.lastIndexOf('. '), cut.lastIndexOf('! '), cut.lastIndexOf('? '), cut.lastIndexOf('… '));

        if (end > limit / 3) {
            return cut.slice(0, end + 1);
        }

        var space = cut.lastIndexOf(' ');

        return ((space > limit / 3) ? cut.slice(0, space) : cut) + '…';
    }

    // Reads the text in the screen's language, after whatever is read now;
    // false when there is nothing to read or no speech.
    function speak(text, locale) {
        var words = speakText(text);

        if (!speakSupported() || (words === '')) {
            return false;
        }

        var utterance = new window.SpeechSynthesisUtterance(words);

        if (locale) {
            utterance.lang = locale;
        }

        if (sounding && !sounding.paused) {
            sounding.pause();
        }

        window.speechSynthesis.speak(utterance);

        return true;
    }

    function speakStop() {
        if (speakSupported()) {
            window.speechSynthesis.cancel();
        }
    }

    window.PGWsVoice = {
        supported: supported,
        busy: busy,
        start: start,
        player: player,
        isVoice: isVoice,
        speakSupported: speakSupported,
        speakOn: speakOn,
        speakSet: speakSet,
        speak: speak,
        speakStop: speakStop
    };
})();
