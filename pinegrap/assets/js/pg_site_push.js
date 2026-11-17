/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Notify me" block of visitor notifications. The block is markup only:
 * a root marked data-pg-push and hidden, holding the part shown before the
 * visitor subscribes (data-pg-push-ask, with the button data-pg-push-on), the
 * part shown once notifications are on (data-pg-push-done, with the button
 * data-pg-push-stop), the hint shown when the browser has them blocked
 * (data-pg-push-blocked) and the line shown after a try that failed
 * (data-pg-push-failed). The server adds this file, with
 * window.pgSitePushConfig (the door, the worker, its scope and the public
 * VAPID key), to a page whose HTML holds the mark while the "Visitor
 * notifications" switch is on (pg_site_push_block_inject()); the editor's
 * Preview loads it from its twin row in _SD_PAGE_SCRIPTS.
 *
 * The root shows only where the browser can subscribe (a secure page with a
 * service worker, push and notifications, and a key): never a button that
 * does nothing. The worker is registered only when the visitor presses the
 * button. The press asks the browser's own question; on yes the browser's
 * subscription goes to site_push.php with the page's language and the secret
 * it answers is kept in IndexedDB, where the worker reads it. "Stop" removes
 * the subscription on the site and in the browser and the worker with it.
 *
 * window.pgSitePush.secret() answers the stored secret ('' without one), for
 * the forms that name this device (back in stock, an order).
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads. The
 * pure parts are exported for the tests under Node
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
    } else if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        var run = function () { api.start(window, document); };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
        else run();
    }
})(function () {
    'use strict';

    // The store the worker reads the secret from (pg_site_push_sw.js).
    var DB = 'pg-site-push';
    var STORE = 'kv';
    var KEY = 'secret';

    // ── Pure parts ──────────────────────────────────────────────────────

    // The block's state: 'none' (the browser cannot subscribe: nothing is
    // shown), 'blocked', 'on' (asked yes, subscribed and the secret kept) or
    // 'off'.
    function stateOf(f) {
        if (!f || !f.supported) return 'none';
        if (f.permission === 'denied') return 'blocked';
        if (f.permission === 'granted' && f.secret && f.subscribed) return 'on';
        return 'off';
    }

    // Whether this browser can subscribe on this page.
    function canSubscribe(win, conf) {
        return !!(win && conf && conf.key && conf.door && conf.worker
            && win.isSecureContext && win.navigator && ('serviceWorker' in win.navigator)
            && ('PushManager' in win) && ('Notification' in win) && win.indexedDB);
    }

    // The page language as the door takes it: <html lang> without a
    // machine translation's "-x-" tail, or '' when it is not a language tag.
    function langOf(value) {
        var v = String(value || '').split(/-x-/i)[0];
        return /^[a-z]{2,3}(-[A-Za-z0-9]{2,4})?$/.test(v) ? v : '';
    }

    // base64url (the VAPID key) to bytes, and bytes (a subscription's keys)
    // to base64url without padding.
    function keyBytes(text) {
        var b64 = String(text || '').replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) b64 += '=';
        var raw = atob(b64), out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function b64url(buffer) {
        var bytes = new Uint8Array(buffer || []), raw = '';
        for (var i = 0; i < bytes.length; i++) raw += String.fromCharCode(bytes[i]);
        return btoa(raw).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    // Whether a subscription was made with this key (a site whose key
    // changed needs a new one).
    function sameKey(buffer, key) {
        if (!buffer) return true;
        var a = new Uint8Array(buffer), b = keyBytes(key);
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
        return true;
    }

    // Which parts of the block show in a state.
    function partsFor(state, failed) {
        return {
            root: state !== 'none',
            ask: state === 'off',
            done: state === 'on',
            blocked: state === 'blocked',
            failed: state === 'off' && !!failed
        };
    }

    // ── The secret ──────────────────────────────────────────────────────

    function store(idb, mode, work) {
        return new Promise(function (resolve, reject) {
            var open;
            try { open = idb.open(DB, 1); } catch (e) { reject(e); return; }
            open.onupgradeneeded = function () { open.result.createObjectStore(STORE); };
            open.onerror = function () { reject(open.error); };
            open.onsuccess = function () {
                var db = open.result, req;
                try {
                    req = work(db.transaction(STORE, mode).objectStore(STORE));
                } catch (e) {
                    db.close();
                    reject(e);
                    return;
                }
                req.onsuccess = function () { db.close(); resolve(req.result); };
                req.onerror = function () { db.close(); reject(req.error); };
            };
        });
    }

    function secretGet(idb) {
        if (!idb) return Promise.resolve('');
        return store(idb, 'readonly', function (s) { return s.get(KEY); })
            .then(function (v) { return (typeof v === 'string') ? v : ''; }, function () { return ''; });
    }

    function secretSet(idb, value) {
        return store(idb, 'readwrite', function (s) { return s.put(value, KEY); });
    }

    function secretDrop(idb) {
        return store(idb, 'readwrite', function (s) { return s['delete'](KEY); }).catch(function () {});
    }

    // ── On the page ─────────────────────────────────────────────────────

    function start(win, doc) {
        var conf = win.pgSitePushConfig || {};
        var idb = win.indexedDB;

        win.pgSitePush = { secret: function () { return secretGet(idb); } };

        var roots = Array.prototype.slice.call(doc.querySelectorAll('[data-pg-push]'));
        if (!roots.length || !canSubscribe(win, conf)) return;

        var sw = win.navigator.serviceWorker;
        var busy = false, failed = false;

        var part = function (root, name) { return root.querySelector('[data-pg-push-' + name + ']'); };

        var render = function (state) {
            var show = partsFor(state, failed);
            roots.forEach(function (root) {
                root.hidden = !show.root;
                root.setAttribute('data-pg-push-state', state);
                ['ask', 'done', 'blocked', 'failed'].forEach(function (name) {
                    var el = part(root, name);
                    if (el) el.hidden = !show[name];
                });
            });
        };

        var registration = function () {
            return sw.getRegistration(conf.scope).catch(function () { return null; });
        };

        var refresh = function () {
            return Promise.all([secretGet(idb), registration().then(function (reg) {
                return reg ? reg.pushManager.getSubscription() : null;
            }).catch(function () { return null; })]).then(function (got) {
                var state = stateOf({
                    supported: true,
                    permission: win.Notification.permission,
                    secret: got[0],
                    subscribed: !!got[1]
                });
                render(state);
                return state;
            });
        };

        var post = function (fields) {
            var body = new win.URLSearchParams();
            Object.keys(fields).forEach(function (k) { body.set(k, fields[k]); });
            return win.fetch(conf.door, { method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        };

        var ask = function () {
            return new Promise(function (resolve) {
                var p = win.Notification.requestPermission(resolve);
                if (p && typeof p.then === 'function') p.then(resolve, function () { resolve('default'); });
            });
        };

        var active = function (reg) {
            if (reg.active) return Promise.resolve(reg);
            var w = reg.installing || reg.waiting;
            if (!w) return Promise.reject(new Error('worker'));
            return new Promise(function (resolve, reject) {
                w.addEventListener('statechange', function () {
                    if (w.state === 'activated') resolve(reg);
                    else if (w.state === 'redundant') reject(new Error('worker'));
                });
            });
        };

        var subscription = function (reg) {
            var options = { userVisibleOnly: true, applicationServerKey: keyBytes(conf.key) };
            return reg.pushManager.getSubscription().then(function (old) {
                if (old && sameKey(old.options && old.options.applicationServerKey, conf.key)) return old;
                return (old ? old.unsubscribe() : Promise.resolve(true)).then(function () {
                    return reg.pushManager.subscribe(options);
                });
            });
        };

        var focus = function (name) {
            var el = part(roots[0], name);
            if (el && typeof el.focus === 'function') el.focus();
        };

        var turnOn = function () {
            failed = false;
            return ask().then(function (answer) {
                if (answer !== 'granted') return refresh();
                return sw.register(conf.worker, { scope: conf.scope }).then(active).then(subscription).then(function (sub) {
                    return post({
                        action: 'subscribe',
                        endpoint: sub.endpoint,
                        p256dh: b64url(sub.getKey('p256dh')),
                        auth: b64url(sub.getKey('auth')),
                        language: langOf(doc.documentElement.getAttribute('lang'))
                    }).then(function (j) {
                        if (j && typeof j.secret === 'string' && j.secret !== '') return secretSet(idb, j.secret);
                        // Refused by the site: the browser keeps no subscription
                        // nobody will send to.
                        return sub.unsubscribe().then(function () { throw new Error('door'); });
                    });
                }).then(refresh).then(function (state) {
                    if (state === 'on') focus('stop');
                });
            });
        };

        var turnOff = function () {
            failed = false;
            return secretGet(idb).then(function (secret) {
                var told = secret ? post({ action: 'stop', secret: secret }).catch(function () {}) : Promise.resolve();
                return told.then(registration).then(function (reg) {
                    if (!reg) return null;
                    return reg.pushManager.getSubscription().then(function (sub) {
                        return sub ? sub.unsubscribe() : true;
                    }).then(function () { return reg.unregister(); });
                });
            }).then(function () { return secretDrop(idb); }).then(refresh).then(function () { focus('on'); });
        };

        var run = function (work) {
            if (busy) return;
            busy = true;
            roots.forEach(function (root) { root.setAttribute('aria-busy', 'true'); });
            work().catch(function () {
                failed = true;
                return refresh();
            }).then(function () {
                busy = false;
                roots.forEach(function (root) { root.removeAttribute('aria-busy'); });
            });
        };

        roots.forEach(function (root) {
            [['on', turnOn], ['stop', turnOff]].forEach(function (pair) {
                var btn = part(root, pair[0]);
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    run(pair[1]);
                });
            });
        });

        return refresh();
    }

    return {
        stateOf: stateOf,
        canSubscribe: canSubscribe,
        langOf: langOf,
        keyBytes: keyBytes,
        b64url: b64url,
        sameKey: sameKey,
        partsFor: partsFor,
        start: start
    };
});
