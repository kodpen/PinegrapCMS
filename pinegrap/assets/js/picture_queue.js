/**
 * Pinegrap - Enterprise Website Platform
 *
 * Translations - the queue of waiting pictures. The view
 * translations.php?group=pictures&draw=1 lists each language row of a
 * Design Canvas picture that is waiting or out of date and whose every text
 * is translated. This script draws them one after another in this browser
 * with the queue's drawing in the Design Canvas's bundle
 * (PgImageEditor.parts.drawWaiting(), shared with the panel's background
 * draw and the Design Canvas after a Save): the picture's live document is
 * read once per picture (action picture_languages), each language drawn as
 * the Languages list draws it and posted (action picture_language_draw). The
 * count left is read out; Stop ends the run after the picture being drawn,
 * Continue takes it up again.
 *
 * Standalone, not bundled; tests/picture_translation_test.php runs its pure
 * part under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // How many rows are still to be drawn: waiting or being drawn.
    function left(items) {
        return (items || []).filter(function (it) {
            return (it.status === 'waiting') || (it.status === 'drawing');
        }).length;
    }

    // Whether a row still says why it waits (Not drawn yet, Out of date):
    // only until its draw ends; then the pill alone says the result.
    function reasonShown(status) {
        return (status === 'waiting') || (status === 'drawing');
    }

    // Whether a row offers the Design Canvas: a picture to look at by hand,
    // once the run has ended or stopped (never while a draw is running).
    function editShown(status, running) {
        return (status === 'look') && !running;
    }

    // Stop is quiet while the run goes; Continue after a stop is the view's
    // one fill.
    function buttonTone(running) {
        return running ? 'pg-button-quiet' : 'pg-button-fill';
    }

    // The line once nothing is left: the failed rows first, else the rows
    // drawn but still to look at by hand, else all drawn.
    function endText(items, strings) {
        var failed = 0, look = 0;

        (items || []).forEach(function (it) {
            failed += (it.status === 'failed') ? 1 : 0;
            look += (it.status === 'look') ? 1 : 0;
        });

        if (failed) {
            return strings.doneFailed.replace('{n}', String(failed));
        }

        return look ? strings.doneLook.replace('{n}', String(look)) : strings.done;
    }

    var api = { left: left, reasonShown: reasonShown, editShown: editShown, buttonTone: buttonTone, endText: endText };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (!root || !root.document) {
        return;
    }

    root.PgPictureQueue = api;

    var app = document.getElementById('pg_pq');

    if (!app) {
        return;
    }

    var cfg = JSON.parse(app.getAttribute('data-config') || '{}'), s = cfg.strings || {};
    var items = (cfg.items || []).map(function (it) { return Object.assign({ status: 'waiting' }, it); });
    var rows = app.querySelectorAll('[data-pq-row]'), leftEl = document.getElementById('pg_pq_left');
    var stopBtn = document.getElementById('pg_pq_stop'), docs = {}, running = false, stopped = false;
    var TONES = { waiting: 'pg-tone-gray', drawing: 'pg-tone-blue', drawn: 'pg-tone-teal', look: 'pg-tone-amber', skipped: 'pg-tone-gray', failed: 'pg-tone-rose' };

    function show(i, status, note) {
        var row = rows[i], pill = row && row.querySelector('.pg-pill'), text = row && row.querySelector('[data-pq-note]');
        var reason = row && row.querySelector('[data-pq-reason]');

        items[i].status = status;

        if (pill) {
            pill.className = 'pg-pill text-nowrap ' + TONES[status];
            pill.textContent = s[status] || status;
        }

        if (reason) {
            reason.hidden = !reasonShown(status);
        }

        if (text) {
            text.textContent = note || '';
            text.hidden = !note;
        }
    }

    function count() {
        var n = left(items);

        if (n) {
            leftEl.textContent = (stopped && !running) ? s.stopped.replace('{n}', String(n)) : s.left.replace('{n}', String(n));
        } else {
            leftEl.textContent = endText(items, s);
        }

        stopBtn.hidden = !n;
        stopBtn.classList.remove('pg-button-quiet', 'pg-button-fill');
        stopBtn.classList.add(buttonTone(running));

        items.forEach(function (it, i) {
            var edit = rows[i] && rows[i].querySelector('[data-pq-edit]');

            if (edit) {
                edit.hidden = !editShown(it.status, running);
            }
        });

        // The button comes back once the picture being drawn is done.
        if (!running || !stopped) {
            stopBtn.disabled = false;
            stopBtn.textContent = running ? s.stop : s.resume;
        }
    }

    // One row with the queue's drawing; a picture's languages are read once
    // in the page (docs).
    function one(i) {
        var it = items[i];

        show(i, 'drawing');
        count();

        return root.PgImageEditor.parts.drawWaiting(it.file_id, it.language, docs).then(function (r) {
            show(i, r.status, (r.status === 'failed') ? (r.message || s.failed) : '');
        });
    }

    function run() {
        var next = -1;

        for (var i = 0; i < items.length; i++) {
            if (items[i].status === 'waiting') {
                next = i;
                break;
            }
        }

        if (stopped || (next < 0)) {
            running = false;
            count();
            return;
        }

        one(next).then(run);
    }

    function start() {
        stopped = false;
        running = true;
        count();
        root.pgImageEditor.load().then(run, function () {
            running = false;
            leftEl.textContent = s.failed;
        });
    }

    stopBtn.addEventListener('click', function () {
        if (running) {
            stopped = true;
            stopBtn.textContent = s.stopping;
            stopBtn.disabled = true;
            return;
        }

        start();
    });

    if (items.length && root.pgImageEditor) {
        start();
    } else {
        count();
    }
}((typeof window !== 'undefined') ? window : null));
