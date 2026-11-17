/**
 * Pinegrap - Enterprise Website Platform
 *
 * Fakes for tests/site_push_block_test.php, loaded after
 * tests/fixtures/listen/dom.js (el(), fakeDoc()): the "Notify me" block as
 * the family file draws it, an IndexedDB holding plain values, a browser's
 * service worker and push manager that record what they are asked, a door
 * that answers as site_push.php does, and the worker's global scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

function pushBlock() {
    return el('section', { 'data-pg-push': '', hidden: '' }, [
        el('div', { 'data-pg-push-ask': '' }, [el('button', { type: 'button', 'data-pg-push-on': '' }, ['Notify me'])]),
        el('div', { 'data-pg-push-done': '', hidden: '' }, [
            el('p', {}, ['Notifications are on.']),
            el('button', { type: 'button', 'data-pg-push-stop': '' }, ['Stop'])
        ]),
        el('p', { 'data-pg-push-blocked': '', hidden: '' }, ['Blocked']),
        el('p', { 'data-pg-push-failed': '', hidden: '' }, ['Failed'])
    ]);
}

// Each request answers after the caller has set its handlers, as the real
// one does; on a microtask, so one timer turn lets every answer land.
function __later(fn) { Promise.resolve().then(fn); }

function fakeIdb(data) {
    data = data || {};
    var req = function (work) {
        var r = {};
        __later(function () { r.result = work(); if (r.onsuccess) r.onsuccess(); });
        return r;
    };
    return {
        data: data,
        open: function () {
            var db = {
                close: function () {},
                createObjectStore: function () {},
                transaction: function () {
                    return { objectStore: function () {
                        return {
                            get: function (k) { return req(function () { return data[k]; }); },
                            put: function (v, k) { return req(function () { data[k] = v; return k; }); },
                            'delete': function (k) { return req(function () { delete data[k]; }); }
                        };
                    } };
                }
            };
            return req(function () { return db; });
        }
    };
}

function __bytes(n, v) {
    var a = new Uint8Array(n);
    for (var i = 0; i < n; i++) a[i] = v;
    return a.buffer;
}

// A browser that can subscribe. reply(fields) is the door's JSON answer.
function fakeBrowser(permission, answer, reply) {
    var rec = { registered: null, sub: null, unregistered: 0, unsubscribed: 0, subscribedWith: null, posts: [] };
    var newSub = function () {
        return {
            endpoint: 'https://fcm.googleapis.com/fcm/send/abc',
            options: { applicationServerKey: null },
            getKey: function (n) { return (n === 'p256dh') ? __bytes(65, 4) : __bytes(16, 7); },
            unsubscribe: function () { rec.sub = null; rec.unsubscribed++; return Promise.resolve(true); }
        };
    };
    var reg = {
        active: {},
        pushManager: {
            getSubscription: function () { return Promise.resolve(rec.sub); },
            subscribe: function (o) { rec.subscribedWith = o; rec.sub = newSub(); return Promise.resolve(rec.sub); }
        },
        unregister: function () { rec.registered = null; rec.unregistered++; return Promise.resolve(true); }
    };
    var Notification = {
        permission: permission,
        requestPermission: function () { Notification.permission = answer; return Promise.resolve(answer); }
    };
    var win = {
        isSecureContext: true,
        PushManager: function () {},
        Notification: Notification,
        URLSearchParams: URLSearchParams,
        indexedDB: fakeIdb(),
        navigator: { serviceWorker: {
            register: function (url, o) { rec.registered = { url: url, scope: o.scope }; return Promise.resolve(reg); },
            getRegistration: function () { return Promise.resolve(rec.registered ? reg : null); }
        } },
        fetch: function (url, o) {
            var fields = {};
            o.body.forEach(function (v, k) { fields[k] = v; });
            rec.posts.push({ url: url, fields: fields });
            return Promise.resolve({ ok: true, json: function () { return Promise.resolve(reply(fields)); } });
        },
        pgSitePushConfig: { door: '/pg/site_push.php', worker: '/pg/site_push_sw.php', scope: '/',
            key: Buffer.from(__bytes(65, 4)).toString('base64url') }
    };
    win.rec = rec;
    return win;
}

// Lets every queued answer land: the fakes answer on microtasks, all done
// before the next timer.
function settle() { return new Promise(function (r) { setTimeout(r, 0); }); }

// The parts of the block that show.
function shown(block) {
    var out = { root: !block.hidden };
    ['ask', 'done', 'blocked', 'failed'].forEach(function (n) { out[n] = !block.querySelector('[data-pg-push-' + n + ']').hidden; });
    out.state = block.getAttribute('data-pg-push-state');
    return out;
}

// The worker's global scope: handlers by event, notifications shown.
function fakeWorkerScope(secret, reply) {
    var on = {}, shownList = [], posts = [];
    var sw = {
        registration: {
            scope: 'https://shop.example/',
            showNotification: function (title, options) { shownList.push({ title: title, options: options }); return Promise.resolve(); }
        },
        indexedDB: fakeIdb(secret ? { secret: secret } : {}),
        pgSitePushWorker: { door: '/pg/site_push.php', name: 'Shop', text: 'Open the site to see what is new.' },
        addEventListener: function (t, fn) { on[t] = fn; },
        skipWaiting: function () {},
        fetch: function (url, o) {
            var fields = {};
            o.body.forEach(function (v, k) { fields[k] = v; });
            posts.push({ url: url, fields: fields });
            return Promise.resolve({ ok: true, json: function () { return Promise.resolve(reply); } });
        },
        clients: { matchAll: function () { return Promise.resolve([]); }, openWindow: function () { return Promise.resolve(null); } }
    };
    sw.on = on;
    sw.shown = shownList;
    sw.posts = posts;
    sw.push = function () {
        var waits = [];
        on.push({ waitUntil: function (p) { waits.push(p); } });
        return Promise.all(waits);
    };
    return sw;
}
