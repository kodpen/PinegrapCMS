/**
 * Pinegrap - Enterprise Website Platform
 *
 * Translations - the queue of waiting pictures. The view
 * translations.php?group=pictures&draw=1 lists each language row of a
 * Design Canvas picture that is waiting or out of date and whose every text
 * is translated. This script draws them one after another in this browser
 * with the Design Canvas's own drawing (Editor.prototype.drawLanguage() of
 * the editor's bundle, as its Languages list does): the picture's live
 * document is read once per picture (action picture_languages), each
 * language drawn and posted (action picture_language_draw). The count left
 * is read out; Stop ends the run after the picture being drawn, Continue
 * takes it up again.
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

    // A row's state from a draw's answer: drawn, look (a text did not fit),
    // skipped (not every text is translated any more) or failed.
    function statusOf(r) {
        if (r === 'skipped') {
            return 'skipped';
        }

        if (r && (r.status === 'success')) {
            return (r.state === 'look') ? 'look' : 'drawn';
        }

        return 'failed';
    }

    var api = { left: left, statusOf: statusOf };

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

        items[i].status = status;

        if (pill) {
            pill.className = 'pg-pill ' + TONES[status];
            pill.textContent = s[status] || status;
        }

        if (text) {
            text.textContent = note || '';
            text.hidden = !note;
        }
    }

    function count() {
        var n = left(items), failed = items.filter(function (it) { return it.status === 'failed'; }).length;

        if (n) {
            leftEl.textContent = (stopped && !running) ? s.stopped.replace('{n}', String(n)) : s.left.replace('{n}', String(n));
        } else {
            leftEl.textContent = failed ? s.doneFailed.replace('{n}', String(failed)) : s.done;
        }

        stopBtn.hidden = !n;

        // The button comes back once the picture being drawn is done.
        if (!running || !stopped) {
            stopBtn.disabled = false;
            stopBtn.textContent = running ? s.stop : s.resume;
        }
    }

    // A painter on the editor's prototype for one picture: what
    // drawLanguage() reads from an open editor.
    function drawer(fileId) {
        var d = Object.create(root.PgImageEditor.parts.Editor.prototype);

        d.langFileId = fileId;

        return d;
    }

    function languages(fileId) {
        if (!docs[fileId]) {
            docs[fileId] = drawer(fileId).request({ action: 'picture_languages', file_id: fileId }).then(function (r) {
                return (r && (r.status === 'success') && r.words) ? r : null;
            });
        }

        return docs[fileId];
    }

    function one(i) {
        var it = items[i], M = root.PgImageEditor.parts.M;

        show(i, 'drawing');
        count();

        return languages(it.file_id).then(function (data) {
            var lang = data ? data.languages.filter(function (l) { return l.code === it.language; })[0] : null;

            if (!data || !lang) {
                return null;
            }

            if (!M.languageDrawable(lang)) {
                return 'skipped';
            }

            return drawer(it.file_id).drawLanguage(data, lang);
        }).then(function (r) {
            var status = statusOf(r);

            show(i, status, (status === 'failed') ? ((r && r.message) || s.failed) : '');
        }, function () {
            show(i, 'failed', s.failed);
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
