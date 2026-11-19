/**
 * Pinegrap - Enterprise Website Platform
 *
 * The screen "Send a notification" (messages_notifications.php):
 * - "Pick a page or record" searches the site search index (?pick=, JSON)
 *   and fills the title, text and link from the chosen item;
 * - "Send a test to this device" is turned on only when this browser is
 *   itself subscribed to the site's notifications: the site's worker
 *   (scope: the site's base path) holds a push subscription, whose endpoint
 *   goes with the form so the server can find that subscription.
 * Texts come from the markup (lang() on the server); nothing here is shown
 * that the server did not write.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-pg-site-push]');

    if (!form) {
        return;
    }

    var scope = form.getAttribute('data-scope') || '/';
    var test = form.querySelector('[data-pg-site-push-test]');
    var device = form.querySelector('[data-pg-site-push-device]');
    var endpoint = form.querySelector('input[name="endpoint"]');
    var title = form.querySelector('#site-push-title');
    var body = form.querySelector('#site-push-body');
    var url = form.querySelector('#site-push-url');
    var pick = form.querySelector('#site-push-pick');
    var results = form.querySelector('[data-pg-site-push-results]');

    // This browser's own subscription, if the site's worker has one. The
    // panel's worker has the software directory as its scope, so only the
    // site's registration answers for the base path.
    if (('serviceWorker' in navigator) && ('PushManager' in window) && navigator.serviceWorker.getRegistration) {
        navigator.serviceWorker.getRegistration(scope).then(function (registration) {
            if (!registration || !registration.pushManager || (new URL(registration.scope).pathname !== scope)) {
                return null;
            }

            return registration.pushManager.getSubscription();
        }).then(function (subscription) {
            if (!subscription || !subscription.endpoint) {
                return;
            }

            endpoint.value = subscription.endpoint;
            test.disabled = title.disabled;
            device.textContent = device.getAttribute('data-on');
        }).catch(function () {});
    }

    // A send to every subscriber cannot be called back: it asks first, with
    // what goes and to how many, whether a button or Enter in a field sent
    // the form. A test goes without asking.
    //
    // The post writes every subscriber's queue row before it answers, so the
    // page may wait a while: the first send that goes through (the confirmed
    // news or a test) disables both buttons and turns every later submit
    // away, or a second press would send the news again without asking. The
    // chosen action travels in a hidden "send" input, because a disabled
    // submit button drops its own name and value from the request.
    var buttons = form.querySelectorAll('button[name="send"]');
    var sending = false;
    var before = [];

    function sendOnce(action) {
        var input = form.querySelector('input[type="hidden"][name="send"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'send';
            form.appendChild(input);
        }

        input.value = action;
        sending = true;
        before = [];
        form.setAttribute('aria-busy', 'true');

        Array.prototype.forEach.call(buttons, function (button) {
            before.push(button.disabled);
            button.disabled = true;
        });
    }

    // A page restored from the back/forward cache is a fresh screen again.
    window.addEventListener('pageshow', function (event) {
        var input = form.querySelector('input[type="hidden"][name="send"]');

        if (!event.persisted || !sending) {
            return;
        }

        sending = false;
        form.removeAttribute('aria-busy');

        if (input) {
            input.parentNode.removeChild(input);
        }

        Array.prototype.forEach.call(buttons, function (button, index) {
            button.disabled = before[index];
        });
    });

    form.addEventListener('submit', function (event) {
        var submitter = event.submitter || null;
        var isTest = !!(submitter && submitter.value === 'test');

        if (sending) {
            event.preventDefault();
            return;
        }

        if (isTest || (typeof window.pgConfirm !== 'function')) {
            sendOnce(isTest ? 'test' : 'news');
            return;
        }

        event.preventDefault();

        var what = [title.value.trim(), body.value.trim(), url.value.trim() || '/'].filter(function (part) {
            return part !== '';
        }).join(' · ');

        window.pgConfirm({
            title: form.getAttribute('data-confirm-title'),
            message: what + ' — ' + form.getAttribute('data-confirm-text'),
            confirmText: form.getAttribute('data-confirm-button'),
            variant: 'primary'
        }).then(function (ok) {
            if (!ok || sending) {
                return;
            }

            sendOnce('news');
            form.submit();
        });
    });

    if (!pick || !results) {
        return;
    }

    var timer = null;
    var asked = 0;

    function clear() {
        while (results.firstChild) {
            results.removeChild(results.firstChild);
        }
    }

    function choose(item) {
        title.value = item.title;
        body.value = item.body;
        url.value = item.url;
        pick.value = '';
        clear();
        title.focus();
    }

    function show(items) {
        clear();

        items.forEach(function (item) {
            var button = document.createElement('button');
            var name = document.createElement('span');
            var where = document.createElement('span');

            button.type = 'button';
            button.className = 'list-group-item list-group-item-action d-flex flex-column align-items-start';
            name.className = 'pg-list-row-title';
            name.textContent = item.title;
            where.className = 'pg-text-meta text-break';
            where.textContent = item.url;
            button.appendChild(name);
            button.appendChild(where);
            button.addEventListener('click', function () {
                choose(item);
            });
            results.appendChild(button);
        });
    }

    pick.addEventListener('input', function () {
        var term = pick.value.trim();

        clearTimeout(timer);

        if (term.length < 2) {
            clear();
            return;
        }

        timer = setTimeout(function () {
            var mine = ++asked;

            fetch(form.getAttribute('data-pick') + encodeURIComponent(term), {credentials: 'same-origin'})
                .then(function (response) {
                    return response.ok ? response.json() : {items: []};
                })
                .then(function (answer) {
                    if (mine === asked) {
                        show(Array.isArray(answer.items) ? answer.items : []);
                    }
                })
                .catch(function () {});
        }, 250);
    });
})();
