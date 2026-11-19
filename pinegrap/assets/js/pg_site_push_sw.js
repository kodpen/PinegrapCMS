/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's service worker for visitor notifications, served by
 * site_push_sw.php with the site's root as its scope (Service-Worker-Allowed)
 * and self.pgSitePushWorker set before it: the door's address (site_push.php),
 * the site's name, a short fixed text and the site icon.
 *
 * It handles a push and a click on a notification, nothing else: no fetch
 * handler, so no request of the site or the panel passes through it. The
 * push carries no text. Woken, the worker posts its subscription's secret
 * (kept in IndexedDB by assets/js/pg_site_push.js) to the door and shows the
 * waiting message; when there is none or it cannot be read, it still shows
 * the fixed notice with the site's name, because a browser punishes a push
 * that shows nothing. A click opens the message's link, a path on this site,
 * in the site's open tab or a new one.
 *
 * Plain ES5. The pure parts are exported for the tests under Node
 * (tests/site_push_block_test.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else if (typeof self !== 'undefined' && self.registration) {
        api.start(self);
    }
})(function () {
    'use strict';

    // The store the page script writes the secret to (pg_site_push.js).
    var DB = 'pg-site-push';
    var STORE = 'kv';
    var KEY = 'secret';

    // The notification for a message from the door, or the fixed notice
    // with the site's name when there is none. Pure.
    function notice(message, conf) {
        conf = conf || {};
        var ok = message && typeof message === 'object' && typeof message.title === 'string' && message.title !== '';
        var options = {
            body: ok ? String(message.body || '') : String(conf.text || ''),
            data: { url: ok ? String(message.url || '/') : '/' }
        };
        // Fixed notices replace one another; each message stays its own.
        if (!ok) options.tag = 'pg-site-push';
        if (conf.icon) options.icon = String(conf.icon);
        return { title: ok ? message.title : String(conf.name || ''), options: options };
    }

    // The address a click opens: a path on this site, resolved on the
    // worker's origin. Anything else (another origin, outside the scope, not
    // an address) opens the scope itself: the link travels through the push
    // service and the door, so it is checked here too. Pure.
    function target(url, scope) {
        var home = String(scope || '/');
        if (typeof url !== 'string' || url === '') return home;
        try {
            var resolved = new URL(url, home).href;
            return (resolved.indexOf(home) === 0) ? resolved : home;
        } catch (e) {
            return home;
        }
    }

    // The secret the page script stored, or '' (no store, no row, an error).
    function readSecret(idb) {
        return new Promise(function (resolve) {
            if (!idb) { resolve(''); return; }
            var open;
            try { open = idb.open(DB, 1); } catch (e) { resolve(''); return; }
            open.onupgradeneeded = function () { open.result.createObjectStore(STORE); };
            open.onerror = function () { resolve(''); };
            open.onsuccess = function () {
                var db = open.result;
                try {
                    var get = db.transaction(STORE, 'readonly').objectStore(STORE).get(KEY);
                    get.onsuccess = function () { db.close(); resolve(typeof get.result === 'string' ? get.result : ''); };
                    get.onerror = function () { db.close(); resolve(''); };
                } catch (e) {
                    db.close();
                    resolve('');
                }
            };
        });
    }

    // The waiting message from the door, or null.
    function fetchMessage(sw, conf) {
        return readSecret(sw.indexedDB).then(function (secret) {
            if (!secret || !conf.door) return null;
            var body = new URLSearchParams();
            body.set('action', 'message');
            body.set('secret', secret);
            return sw.fetch(conf.door, { method: 'POST', body: body, credentials: 'omit', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) { return (j && j.message) ? j.message : null; });
        }).catch(function () { return null; });
    }

    function start(sw) {
        var conf = sw.pgSitePushWorker || {};

        sw.addEventListener('install', function () { sw.skipWaiting(); });

        sw.addEventListener('push', function (event) {
            event.waitUntil(fetchMessage(sw, conf).then(function (message) {
                var n = notice(message, conf);
                return sw.registration.showNotification(n.title, n.options);
            }));
        });

        sw.addEventListener('notificationclick', function (event) {
            event.notification.close();
            var url = target(event.notification.data && event.notification.data.url, sw.registration.scope);

            event.waitUntil(sw.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
                for (var i = 0; i < list.length; i++) {
                    var c = list[i];
                    if (c.url.indexOf(sw.registration.scope) !== 0 || !('focus' in c)) continue;
                    if (c.url === url) return c.focus();
                    if (c.navigate) {
                        return c.navigate(url).then(function (got) {
                            return got ? got.focus() : sw.clients.openWindow(url);
                        }, function () { return sw.clients.openWindow(url); });
                    }
                }
                return sw.clients.openWindow(url);
            }));
        });
    }

    return { notice: notice, target: target, start: start };
});
