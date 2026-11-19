/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's background draw of translated pictures. While a Design Canvas
 * picture waits to be drawn in a language, the panel's footer prints this
 * script with the rows of the queue this user may draw
 * (pg_picture_tr_auto_tag()). Once the tab is visible and the browser idle,
 * one tab takes the lock (navigator.locks, else a lease in localStorage),
 * loads the Design Canvas bundle and draws the rows one at a time, in the
 * queue's order, with the queue's own drawing
 * (PgImageEditor.parts.drawWaiting(), as "Draw the waiting pictures" does).
 * When the tab is hidden it stops after the row being drawn and lets the
 * lock go; shown again, it goes on from the next row. A row is tried once
 * in a page view: one that failed waits for the next page. Nothing is
 * shown; the queue view draws its own rows.
 *
 * Standalone, not bundled; tests/picture_auto_test.php runs its pure part
 * under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // One name for every tab of the site's panel.
    var LOCK = 'pg-picture-draw';
    var LEASE = 'pg_picture_draw_lease';

    // A lease's life, renewed every third of it while its tab draws, so a
    // closed tab's lease runs out within a minute.
    var LEASE_TTL = 60000;

    // Another tab draws: look again after this long while visible.
    var RETRY = 30000;

    // The page's own work first.
    var START = 3000;

    function yes() {
        return true;
    }

    function rowKey(row) {
        return row.file_id + ':' + row.language;
    }

    // The next row to draw: the first in the queue's order (picture name,
    // language) not tried in this page view.
    function nextRow(rows, tried) {
        for (var i = 0; i < (rows || []).length; i++) {
            if (!tried[rowKey(rows[i])]) {
                return rows[i];
            }
        }

        return null;
    }

    function leaseRead(store) {
        try {
            var held = JSON.parse(store.getItem(LEASE) || 'null');

            return (held && held.id && held.until) ? held : null;
        } catch (err) {
            return null;
        }
    }

    // Takes or renews the lease for tab id unless another tab's lease still
    // runs. Read back after the write: of two tabs writing at once, the last
    // one holds it.
    function leaseTake(store, id, now, ttl) {
        var held = leaseRead(store);

        if (held && (held.id !== id) && (Number(held.until) > now)) {
            return false;
        }

        try {
            store.setItem(LEASE, JSON.stringify({ id: id, until: now + (ttl || LEASE_TTL) }));
        } catch (err) {
            return false;
        }

        held = leaseRead(store);

        return !!held && (held.id === id);
    }

    // Lets the lease go, only when this tab holds it.
    function leaseDrop(store, id) {
        var held = leaseRead(store);

        if (held && (held.id === id)) {
            try {
                store.removeItem(LEASE);
            } catch (err) {
                // Expires by itself.
            }
        }
    }

    // Runs work() while this tab holds the lock, which goes when its promise
    // settles: navigator.locks where the browser has it (env.locks), else
    // the lease in env.storage, renewed while work runs. Answers false at
    // once, without running work, when another tab holds it; else true once
    // work is done. With neither, the tab draws on its own.
    function withLock(env, work) {
        var run = function () {
            return Promise.resolve().then(work).then(yes, yes);
        };

        if (env.locks && env.locks.request) {
            return env.locks.request(LOCK, { ifAvailable: true }, function (lock) {
                return lock ? run() : false;
            });
        }

        if (!env.storage) {
            return run();
        }

        if (!leaseTake(env.storage, env.id, env.now(), LEASE_TTL)) {
            return Promise.resolve(false);
        }

        var timer = env.every(function () {
            leaseTake(env.storage, env.id, env.now(), LEASE_TTL);
        }, LEASE_TTL / 3);

        return run().then(function () {
            env.stop(timer);
            leaseDrop(env.storage, env.id);

            return true;
        });
    }

    // Draws the rows one at a time in their order while visible(): draw(row)
    // answers { status } (drawn, look, skipped, failed). Each row's end is
    // kept in tried, so no row is drawn twice in the page view, a failed one
    // included. The browser's idle moment is awaited before each row; hidden
    // meanwhile, the row is left. Stops after the row being drawn once the
    // tab is hidden; answers how many rows it drew.
    function run(rows, tried, draw, visible, idle) {
        var count = 0;

        function step() {
            var row = nextRow(rows, tried);

            if (!row || !visible()) {
                return Promise.resolve(count);
            }

            return idle().then(function () {
                if (!visible()) {
                    return count;
                }

                tried[rowKey(row)] = 'drawing';

                return Promise.resolve().then(function () {
                    return draw(row);
                }).then(function (r) {
                    return (r && r.status) || 'failed';
                }, function () {
                    return 'failed';
                }).then(function (status) {
                    tried[rowKey(row)] = status;
                    count++;

                    return step();
                });
            });
        }

        return step();
    }

    var api = { LOCK: LOCK, LEASE: LEASE, rowKey: rowKey, nextRow: nextRow, leaseTake: leaseTake, leaseDrop: leaseDrop, withLock: withLock, run: run };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (!root || !root.document) {
        return;
    }

    root.PgPictureAuto = api;

    var tag = document.getElementById('pg_picture_auto');

    if (!tag || document.getElementById('pg_pq') || !root.pgImageEditor || !root.Promise) {
        return;
    }

    var rows = [];

    try {
        rows = JSON.parse(tag.getAttribute('data-rows') || '[]') || [];
    } catch (err) {
        rows = [];
    }

    // localStorage where it can be written (a private window may refuse).
    function storage() {
        try {
            root.localStorage.setItem(LEASE + '_probe', '1');
            root.localStorage.removeItem(LEASE + '_probe');

            return root.localStorage;
        } catch (err) {
            return null;
        }
    }

    var tried = {}, busy = false, retry = 0, cache = {};
    var env = {
        locks: root.navigator && root.navigator.locks,
        storage: storage(),
        id: String(Math.random()).slice(2) + '.' + Date.now(),
        now: function () { return Date.now(); },
        every: function (fn, ms) { return root.setInterval(fn, ms); },
        stop: function (timer) { root.clearInterval(timer); }
    };

    function visible() {
        return document.visibilityState === 'visible';
    }

    function idle() {
        return new Promise(function (resolve) {
            if (root.requestIdleCallback) {
                root.requestIdleCallback(function () { resolve(); }, { timeout: 5000 });
            } else {
                root.setTimeout(resolve, 1000);
            }
        });
    }

    function draw(row) {
        return root.PgImageEditor.parts.drawWaiting(row.file_id, row.language, cache);
    }

    function attempt() {
        if (busy || !visible() || !nextRow(rows, tried)) {
            return;
        }

        busy = true;
        root.clearTimeout(retry);
        idle().then(function () {
            if (!visible()) {
                return true;
            }

            return withLock(env, function () {
                // A picture's languages are read again in every run: another
                // tab may have drawn some meanwhile.
                cache = {};

                return root.pgImageEditor.load().then(function () {
                    return run(rows, tried, draw, visible, idle);
                }, function () {
                    // The bundle did not load: nothing more in this page view.
                    rows = [];
                });
            });
        }).then(function (ran) {
            busy = false;

            if (!ran) {
                retry = root.setTimeout(attempt, RETRY);
            }
        }, function () {
            busy = false;
        });
    }

    document.addEventListener('visibilitychange', attempt);

    if (document.readyState === 'complete') {
        root.setTimeout(attempt, START);
    } else {
        root.addEventListener('load', function () {
            root.setTimeout(attempt, START);
        });
    }
}((typeof window !== 'undefined') ? window : null));
