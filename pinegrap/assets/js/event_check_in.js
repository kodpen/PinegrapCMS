/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ticket check-in screen (event_check_in.php): the pick, the camera read
 * through the browser's own barcode reader (BarcodeDetector), the typed
 * code, the name search and the undo. Every action is a POST to the same
 * page, answered in JSON; texts come from the page (data-texts) and are
 * written with textContent only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var root = document.getElementById('check_in');
    var pick = document.getElementById('check_in_pick');

    if (!root || !pick) {
        return;
    }

    var texts = {};

    try {
        texts = JSON.parse(root.getAttribute('data-texts') || '{}');
    } catch (e) {
        texts = {};
    }

    var token = root.getAttribute('data-token') || '';
    var work = document.getElementById('check_in_work');
    var counts = document.getElementById('check_in_counts');
    var video = document.getElementById('check_in_video');
    var cameraButton = document.getElementById('check_in_camera');
    var codeForm = document.getElementById('check_in_code_form');
    var codeInput = document.getElementById('check_in_code');
    var hint = document.getElementById('check_in_hint');
    var answer = document.getElementById('check_in_answer');
    var undoButton = document.getElementById('check_in_undo');
    var search = document.getElementById('check_in_search');
    var results = document.getElementById('check_in_results');

    var stream = null;
    var detector = null;
    var reading = false;
    var busy = false;
    var lastCode = '';
    var lastCodeAt = 0;
    var lastTicketId = 0;
    var searchTimer = 0;

    // A QR code may carry the code alone or inside a longer text: the first
    // group of twelve code letters, with or without its dashes, is the code.
    function codeFrom(raw) {
        var match = String(raw || '').toUpperCase().match(/[0-9A-Z]{4}-?[0-9A-Z]{4}-?[0-9A-Z]{4}/);

        return match ? match[0] : String(raw || '').trim();
    }

    function post(request, values) {
        var body = new URLSearchParams();

        body.append('request', request);
        body.append('token', token);
        body.append('pick', pick.value);

        Object.keys(values || {}).forEach(function (name) {
            body.append(name, values[name]);
        });

        return fetch(window.location.pathname, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (response) {
            return response.json().catch(function () {
                return { ok: false, message: texts.failed };
            });
        });
    }

    function showCounts(data) {
        if (data && data.counts && counts) {
            counts.textContent = data.counts.text;
        }
    }

    function showHint(text) {
        if (hint) {
            hint.textContent = text || '';
        }
    }

    function showAnswer(data) {
        var pill = document.getElementById('check_in_answer_pill');
        var title = document.getElementById('check_in_answer_title');
        var detail = document.getElementById('check_in_answer_detail');
        var text = data.text || { pill: '!', title: data.message || texts.failed, detail: '', tone: 'rose' };

        pill.className = 'pg-pill pg-tone-' + (text.tone || 'gray');
        pill.textContent = text.pill || '';
        title.textContent = text.title || '';
        detail.textContent = text.detail || '';
        answer.classList.remove('d-none');

        lastTicketId = (data.ticket && data.ticket.state === 'used') ? data.ticket.id : 0;

        if (undoButton) {
            undoButton.classList.toggle('d-none', lastTicketId === 0);
        }

        if (navigator.vibrate) {
            navigator.vibrate(data.answer === 'valid' ? 120 : [80, 60, 80]);
        }

        showCounts(data);
    }

    function check(request, values) {
        if (busy) {
            return Promise.resolve();
        }

        busy = true;

        return post(request, values).then(function (data) {
            showAnswer(data);
        }, function () {
            showAnswer({ message: texts.failed });
        }).then(function () {
            busy = false;
        });
    }

    function stopCamera() {
        reading = false;

        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }

        video.classList.add('d-none');
        cameraButton.querySelector('.pg-btn-label').textContent = texts.scan;
        showHint('');
    }

    function readFrame() {
        if (!reading) {
            return;
        }

        detector.detect(video).then(function (codes) {
            var now = Date.now();

            if (codes.length && !busy) {
                var code = codeFrom(codes[0].rawValue);

                // The same ticket held in front of the camera is read once.
                if ((code !== lastCode) || (now - lastCodeAt > 4000)) {
                    lastCode = code;
                    lastCodeAt = now;
                    check('scan', { code: code });
                }
            }
        }).catch(function () {
            // A frame the reader cannot take yet (the video still starting) is skipped.
        }).then(function () {
            window.setTimeout(readFrame, 250);
        });
    }

    function startCamera() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showHint(texts.no_reader);
            codeInput.focus();

            return;
        }

        Promise.resolve(window.BarcodeDetector.getSupportedFormats ? window.BarcodeDetector.getSupportedFormats() : ['qr_code']).then(function (formats) {
            if (formats.indexOf('qr_code') === -1) {
                throw new Error('qr');
            }

            detector = detector || new window.BarcodeDetector({ formats: ['qr_code'] });

            return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        }).then(function (media) {
            stream = media;
            video.srcObject = media;
            video.classList.remove('d-none');
            cameraButton.querySelector('.pg-btn-label').textContent = texts.stop;
            showHint(texts.aim);

            return video.play();
        }).then(function () {
            reading = true;
            readFrame();
        }).catch(function (error) {
            stopCamera();
            showHint(error && error.message === 'qr' ? texts.no_reader : texts.no_camera);
        });
    }

    function drawResults(list) {
        results.textContent = '';

        if (!list.length) {
            var none = document.createElement('p');

            none.className = 'pg-text-2 mb-0';
            none.textContent = texts.none;
            results.appendChild(none);

            return;
        }

        list.forEach(function (ticket) {
            var row = document.createElement('div');
            var main = document.createElement('div');
            var name = document.createElement('div');
            var meta = document.createElement('div');

            row.className = 'pg-list-row';
            main.className = 'pg-list-row-main';
            name.className = 'pg-list-row-title';
            meta.className = 'pg-text-meta';
            name.textContent = ticket.name;
            meta.textContent = ticket.of + ' · ' + ticket.order;
            main.appendChild(name);
            main.appendChild(meta);
            row.appendChild(main);

            var side = document.createElement('div');

            side.className = 'pg-list-row-side';

            if (ticket.state === 'used') {
                var pill = document.createElement('span');

                pill.className = 'pg-pill pg-tone-amber';
                pill.textContent = texts.checked_in;
                side.appendChild(pill);
            } else {
                var button = document.createElement('button');

                button.type = 'button';
                button.className = 'pg-button pg-button-quiet pg-button-sm';
                button.textContent = texts.check_in;
                button.addEventListener('click', function () {
                    button.disabled = true;
                    check('check_in', { ticket_id: String(ticket.id) }).then(function () {
                        button.disabled = false;
                        runSearch();
                    });
                });
                side.appendChild(button);
            }

            row.appendChild(side);
            results.appendChild(row);
        });
    }

    function runSearch() {
        var q = search.value.trim();

        if (q.length < 2) {
            results.textContent = '';

            return;
        }

        post('search', { q: q }).then(function (data) {
            if (data && data.ok) {
                drawResults(data.results || []);
            }
        }, function () {
            showHint(texts.failed);
        });
    }

    function choose() {
        var chosen = pick.value !== '';

        work.classList.toggle('d-none', !chosen);
        answer.classList.add('d-none');
        results.textContent = '';
        search.value = '';
        counts.textContent = '';

        if (!chosen) {
            stopCamera();

            return;
        }

        try {
            window.history.replaceState(null, '', window.location.pathname + '?pick=' + encodeURIComponent(pick.value));
        } catch (e) {
            // The address keeps its old pick.
        }

        post('counts', {}).then(showCounts, function () {});
    }

    pick.addEventListener('change', choose);

    cameraButton.addEventListener('click', function () {
        if (stream) {
            stopCamera();
        } else {
            startCamera();
        }
    });

    codeForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var code = codeFrom(codeInput.value);

        if (code === '') {
            codeInput.focus();

            return;
        }

        check('scan', { code: code }).then(function () {
            codeInput.value = '';
            codeInput.focus();
        });
    });

    if (undoButton) {
        undoButton.addEventListener('click', function () {
            if (!lastTicketId) {
                return;
            }

            undoButton.disabled = true;
            post('undo', { ticket_id: String(lastTicketId) }).then(function (data) {
                document.getElementById('check_in_answer_title').textContent = data.message || texts.failed;
                document.getElementById('check_in_answer_detail').textContent = '';
                undoButton.classList.add('d-none');
                lastTicketId = 0;
                lastCode = '';
                showCounts(data);
            }, function () {
                showHint(texts.failed);
            }).then(function () {
                undoButton.disabled = false;
            });
        });
    }

    search.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(runSearch, 300);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden && stream) {
            stopCamera();
        }
    });

    if (pick.value !== '') {
        choose();
    }
}());
