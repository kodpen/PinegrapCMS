/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel service worker.
 *
 * Registered by backend.src.js from a page inside the software directory, so
 * its scope is that directory and every address it builds is relative to it.
 *
 * No panel page is cached. The panel is a signed-in application whose pages
 * are generated per request and per permission; a worker serving one from a
 * cache would show one operator another operator's screen. The one file kept
 * is offline.html, a fixed notice answered when a page cannot be reached
 * because the network is gone.
 *
 * The visible strings are handed over in the registration URL rather than
 * written here, so that they stay in lang() on the PHP side. The English source
 * string is the last-resort default, which is how lang() itself falls back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

var PG_TEXT = (function () {

    var text = {
        title: 'Notification',
        body: 'There is something new in your panel.',
        lang: 'en',
        offline_title: 'You are offline',
        offline_body: 'The panel cannot be reached right now. Check the connection and try again.',
        offline_retry: 'Retry'
    };

    try {
        var given = JSON.parse(new URL(self.location.href).searchParams.get('strings') || '{}');

        if (given && typeof given === 'object') {
            Object.keys(text).forEach(function (key) {
                if (typeof given[key] === 'string' && given[key] !== '') { text[key] = given[key]; }
            });
        }
    } catch (error) {
        // A malformed query string leaves the English defaults in place.
    }

    return text;
}());

// One cache per worker version, holding offline.html alone. The version is
// the registration address's v (sw.js's own modification time), so a changed
// worker fetches the notice again and the activate step drops the old copy.
var PG_OFFLINE_CACHE = 'pg-offline-' + (new URL(self.location.href).searchParams.get('v') || '0');
var PG_OFFLINE_URL = 'offline.html';

self.addEventListener('install', function (event) {
    // Take over as soon as the file changes. A notice that could not be
    // fetched does not stop the install: push must not depend on it, and a
    // failed page then shows the browser's own error, as before.
    event.waitUntil(caches.open(PG_OFFLINE_CACHE).then(function (cache) {
        return cache.add(new Request(PG_OFFLINE_URL, {cache: 'reload'}));
    }).catch(function () {
        return null;
    }).then(function () {
        return self.skipWaiting();
    }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (names) {
        return Promise.all(names.filter(function (name) {
            return (name.indexOf('pg-offline-') === 0) && (name !== PG_OFFLINE_CACHE);
        }).map(function (name) {
            return caches.delete(name);
        }));
    }).then(function () {
        // Lets a page load start on the network while the worker wakes up.
        if (self.registration.navigationPreload) {
            return self.registration.navigationPreload.enable();
        }
        return null;
    }).catch(function () {
        return null;
    }).then(function () {
        return self.clients.claim();
    }));
});

// Only a page load that reads (GET) is answered: everything else, form posts
// included, goes to the network untouched. The page always comes from the
// network; the notice only when that fails, which is a network error and
// never an error status from the server.
self.addEventListener('fetch', function (event) {
    var request = event.request;

    if ((request.mode !== 'navigate') || (request.method !== 'GET')) {
        return;
    }

    event.respondWith(Promise.resolve(event.preloadResponse).then(function (preloaded) {
        return preloaded || fetch(request);
    }).catch(function () {
        return offlineResponse();
    }));
});

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (character) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
}

function offlineResponse() {
    return caches.open(PG_OFFLINE_CACHE).then(function (cache) {
        return cache.match(PG_OFFLINE_URL);
    }).then(function (cached) {
        if (!cached) {
            return Response.error();
        }

        return cached.text().then(function (html) {
            var fill = {
                lang: PG_TEXT.lang,
                title: PG_TEXT.offline_title,
                body: PG_TEXT.offline_body,
                retry: PG_TEXT.offline_retry
            };

            return new Response(html.replace(/\{\{(lang|title|body|retry)\}\}/g, function (mark, key) {
                return escapeHtml(fill[key]);
            }), {
                status: 503,
                headers: {'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store'}
            });
        });
    });
}

// Every push must end in a visible notification. Safari retires a subscription
// that receives one and shows nothing, so each path below - payload, fetched,
// or neither - finishes by showing something.
self.addEventListener('push', function (event) {
    event.waitUntil(handlePush(event));
});

function handlePush(event) {

    var payload = null;

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (error) {
            payload = null;
        }
    }

    if (payload && payload.title) {
        return showNotifications([payload]);
    }

    // No body: the push was only a signal to look. Asking the panel keeps the
    // text off the push service's servers entirely, which is the reason to
    // prefer this shape.
    return fetchPending()
        .then(function (items) {
            return showNotifications(items && items.length ? items : [defaultItem()]);
        })
        .catch(function () {
            return showNotifications([defaultItem()]);
        });
}

function defaultItem() {
    return {
        title: PG_TEXT.title,
        body: PG_TEXT.body,
        url: 'welcome.php',
        icon: 'assets/images/notification-general.png',
        badge: 'assets/images/notification-general-badge.png'
    };
}

function fetchPending() {

    // A push handler is given a budget and a panel that never answers must not
    // spend all of it, so there is a deadline - but it is a last resort, not a
    // target. A worker woken from cold on a mobile connection has to open a TLS
    // connection and wait for PHP to start before a single byte comes back;
    // five seconds turned out to be inside that, and every notification arrived
    // with the generic wording instead of the order it was about. Twelve is
    // comfortably past the slow case and comfortably inside what the browser
    // allows.
    var deadline = new Promise(function (resolve) {
        setTimeout(function () { resolve([]); }, 12000);
    });

    var asked = fetch('api.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'push_pending' })
    })
        .then(function (response) {
            return response.ok ? response.json() : null;
        })
        .then(function (data) {
            return (data && data.status === 'success' && data.data) ? data.data : [];
        });

    return Promise.race([asked, deadline]);
}

function showNotifications(items) {

    // At most a handful: a phone that has been asleep for a day should not be
    // handed fifty separate banners. The tag collapses repeats of the same row.
    var shown = items.slice(0, 3).map(function (item) {

        // Three pictures, three jobs. Android draws the installed application's
        // own icon - the one chosen in Settings - at the start of the row,
        // which answers "which site". `icon` fills the slot at the other end
        // and answers "what happened": an order, a form, a comment, a
        // conversation. `badge` is the mark in the status bar.
        //
        // The two are different files on purpose. A badge is flattened to its
        // transparency and painted in one colour, so a picture with a solid
        // background becomes a solid white block - which is exactly what the
        // status bar showed while both names pointed at the same file. The
        // badge artwork is the glyph alone on transparency; the icon keeps its
        // coloured tile.
        //
        // Both come from the server, so changing what a notification looks like
        // never means shipping a new worker.
        var options = {
            body: item.body || '',
            tag: item.tag || ('pg-' + (item.id || 'panel')),
            renotify: true,
            timestamp: item.timestamp ? (item.timestamp * 1000) : Date.now(),
            data: { url: item.url || 'welcome.php' }
        };

        if (item.icon) {
            options.icon = item.icon;
        }

        if (item.badge) {
            options.badge = item.badge;
        }

        return self.registration.showNotification(item.title || PG_TEXT.title, options);
    });

    return Promise.all(shown);
}

// Clicking one raises a panel window that is already open on the way to the
// address, rather than leaving a second copy of the application running.
self.addEventListener('notificationclick', function (event) {

    event.notification.close();

    var target = safeTarget(event.notification.data && event.notification.data.url);

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {

            for (var i = 0; i < clients.length; i++) {
                if (clients[i].url.indexOf(self.registration.scope) === 0 && 'focus' in clients[i]) {
                    return clients[i].navigate ? clients[i].navigate(target).then(function (client) {
                        return client ? client.focus() : null;
                    }) : clients[i].focus();
                }
            }

            return self.clients.openWindow(target);
        })
    );
});

// The address a notification carries comes from the server, but it travels
// through the push service, so it is treated as untrusted here: anything that
// resolves outside this worker's own scope is replaced by the panel's home.
function safeTarget(url) {

    var fallback = new URL('welcome.php', self.registration.scope).href;

    if (typeof url !== 'string' || url === '') {
        return fallback;
    }

    try {
        var resolved = new URL(url, self.registration.scope).href;

        return (resolved.indexOf(self.registration.scope) === 0) ? resolved : fallback;
    } catch (error) {
        return fallback;
    }
}

// The panel hands over a fresh subscription when the browser rotates one behind
// its back, which happens without any page being open.
self.addEventListener('pushsubscriptionchange', function (event) {

    event.waitUntil(
        self.registration.pushManager.getSubscription().then(function (subscription) {

            if (!subscription) {
                return null;
            }

            return fetch('api.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'push_subscribe',
                    subscription: subscription.toJSON(),
                    old_endpoint: (event.oldSubscription ? event.oldSubscription.endpoint : '')
                })
            });
        })
    );
});
