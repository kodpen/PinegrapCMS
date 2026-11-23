/**
 * Pinegrap - Enterprise Website Platform
 *
 * Orders › Pack (pack.php): the scan field kept in focus, read from a
 * scanner (it types the code and Enter), by hand, or through the browser's
 * own barcode reader (BarcodeDetector) on a phone's camera. Without an
 * address open a code finds its order; with one each code counts one unit
 * of a line. A refused scan turns the field's row red for two seconds with
 * the reason and a low tone; a checked pack turns the screen to "Packed"
 * with what followed and "Next order" in focus. Texts come from the page
 * (data-texts) and are written with textContent only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var root = document.getElementById('pack');

    if (!root) {
        return;
    }

    var texts = {};

    try {
        texts = JSON.parse(root.getAttribute('data-texts') || '{}');
    } catch (e) {
        texts = {};
    }

    var token = root.getAttribute('data-token') || '';
    var shipTo = root.getAttribute('data-ship-to') || '0';
    var form = root.querySelector('[data-pg-pack-scan]');
    var input = document.getElementById('pack_code');
    var answer = document.getElementById('pack_answer');
    var busy = false;
    var refusedTimer = null;
    var audio = null;

    function post(data) {
        var body = new URLSearchParams();

        body.append('token', token);
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch('pack.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    // A short low tone for a refused scan; silent where audio is blocked.
    function tone() {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();

            var oscillator = audio.createOscillator();
            var gain = audio.createGain();

            oscillator.type = 'square';
            oscillator.frequency.value = 220;
            gain.gain.value = 0.08;
            oscillator.connect(gain);
            gain.connect(audio.destination);
            oscillator.start();
            oscillator.stop(audio.currentTime + 0.25);
        } catch (e) {
            // No sound; the red row still says it.
        }
    }

    function say(text, refused) {
        if (!answer) {
            return;
        }

        answer.textContent = text;
        form.classList.toggle('is-refused', !!refused);
        clearTimeout(refusedTimer);

        if (refused) {
            tone();
            refusedTimer = setTimeout(function () {
                form.classList.remove('is-refused');
            }, 2000);
        }
    }

    function focusField() {
        if (input && document.activeElement !== input) {
            input.focus();
        }
    }

    // A full line turns green and sinks below the lines still waiting.
    function countLine(index, scanned) {
        var list = root.querySelector('[data-pg-pack-lines]');
        var line = list ? list.querySelector('[data-pg-pack-line="' + index + '"]') : null;

        if (!line) {
            return;
        }

        line.querySelector('[data-pg-pack-scanned]').textContent = String(scanned);

        if (scanned >= parseInt(line.getAttribute('data-pg-pack-want'), 10)) {
            line.classList.add('is-full');
            list.appendChild(line);
        }
    }

    function drawDone(rows) {
        var card = document.createElement('div');
        var pill = document.createElement('span');
        var list = document.createElement('ul');
        var next = document.createElement('a');

        stopCamera();
        card.className = 'pg-card pg-look-pad';
        pill.className = 'pg-pill pg-tone-teal';
        pill.textContent = texts.packed;
        card.appendChild(pill);
        list.className = 'pg-pack-done';

        rows.forEach(function (row) {
            var item = document.createElement('li');
            var mark = document.createElement('span');
            var text = document.createElement('span');

            mark.className = 'bi ' + (row.tone === 'teal' ? 'bi-check-circle pg-pack-ok' : 'bi-exclamation-triangle pg-pack-warn');
            mark.setAttribute('aria-hidden', 'true');
            text.textContent = row.text;
            item.appendChild(mark);
            item.appendChild(text);

            if (row.url) {
                var link = document.createElement('a');

                link.href = row.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = texts.print;
                item.appendChild(link);
                // Opened for printing; a blocked pop-up leaves the link.
                window.open(row.url, '_blank', 'noopener');
            }

            list.appendChild(item);
        });

        card.appendChild(list);
        next.className = 'pg-button pg-button-fill';
        next.href = 'pack.php';
        next.textContent = texts.next;
        card.appendChild(next);

        root.textContent = '';
        root.appendChild(card);
        next.focus();
    }

    function drawChoices(choices) {
        var box = root.querySelector('[data-pg-pack-choices]');

        if (!box) {
            return;
        }

        box.textContent = '';

        choices.forEach(function (choice) {
            var link = document.createElement('a');

            link.className = 'pg-button pg-button-quiet';
            link.href = choice.url;
            link.textContent = choice.label;
            box.appendChild(link);
        });
    }

    function read(code) {
        code = String(code || '').trim();

        if (!code || busy) {
            return;
        }

        busy = true;
        input.value = '';

        var scanning = shipTo !== '0';

        post(scanning ? { request: 'scan', ship_to: shipTo, code: code } : { request: 'find', code: code }).then(function (data) {
            busy = false;

            if (!data || !data.ok) {
                if (data && data.reload) {
                    window.location.reload();

                    return;
                }

                say((data && data.message) || texts.failed, true);

                return;
            }

            if (!scanning) {
                if (data.url) {
                    window.location.href = data.url;

                    return;
                }

                drawChoices(data.choices || []);

                return;
            }

            if (data.good) {
                countLine(data.line, data.scanned);
            }

            var count = root.querySelector('[data-pg-pack-count]');

            if (count) {
                count.textContent = data.count;
            }

            if (data.done) {
                drawDone(data.done);

                return;
            }

            say(data.text, !data.good);
        }).catch(function () {
            busy = false;
            say(texts.failed, true);
        });
    }

    // The camera: the browser's own barcode reader, read a frame at a time.
    var cameraButton = root.querySelector('[data-pg-pack-camera]');
    var video = root.querySelector('.pg-pack-video');
    var detector = null;
    var stream = null;
    var reading = false;
    var lastCode = '';
    var lastAt = 0;

    function stopCamera() {
        reading = false;

        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }

        if (video) {
            video.classList.add('d-none');
        }

        if (cameraButton) {
            cameraButton.setAttribute('aria-label', texts.scan);
            cameraButton.classList.remove('is-active');
        }
    }

    function readFrame() {
        if (!reading) {
            return;
        }

        detector.detect(video).then(function (codes) {
            var now = Date.now();

            // One code is read once while it stays in front of the camera.
            if (codes.length && (codes[0].rawValue !== lastCode || now - lastAt > 1500)) {
                lastCode = codes[0].rawValue;
                lastAt = now;
                read(lastCode);
            }
        }).catch(function () {
            // A frame the reader could not take; the next one is tried.
        }).then(function () {
            if (reading) {
                setTimeout(readFrame, 250);
            }
        });
    }

    function startCamera() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            say(texts.no_reader, false);
            focusField();

            return;
        }

        var wanted = ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_39', 'qr_code'];

        Promise.resolve(window.BarcodeDetector.getSupportedFormats ? window.BarcodeDetector.getSupportedFormats() : wanted).then(function (formats) {
            var usable = wanted.filter(function (format) {
                return formats.indexOf(format) !== -1;
            });

            if (!usable.length) {
                throw new Error('reader');
            }

            detector = new window.BarcodeDetector({ formats: usable });

            return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        }).then(function (media) {
            stream = media;
            video.srcObject = media;
            video.classList.remove('d-none');
            cameraButton.setAttribute('aria-label', texts.stop);
            cameraButton.classList.add('is-active');
            say(texts.aim, false);

            return video.play();
        }).then(function () {
            reading = true;
            readFrame();
        }).catch(function (error) {
            stopCamera();
            say(error && error.message === 'reader' ? texts.no_reader : texts.no_camera, false);
        });
    }

    if (form && input) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            read(input.value);
        });

        // The field keeps the focus, so a scanner's keys always land in it;
        // the address list, a link and the buttons may take it for a moment.
        input.addEventListener('blur', function () {
            setTimeout(function () {
                var active = document.activeElement;

                if (!active || active === document.body) {
                    focusField();
                }
            }, 0);
        });

        focusField();
    }

    if (cameraButton && video) {
        cameraButton.addEventListener('click', function () {
            if (stream) {
                stopCamera();
                focusField();
            } else {
                startCamera();
            }
        });
    }

    var pick = root.querySelector('[data-pg-pack-pick]');

    if (pick) {
        pick.addEventListener('change', function () {
            if (/^\d+$/.test(pick.value)) {
                window.location.href = 'pack.php?ship_to=' + pick.value;
            }
        });
    }
}());
