/**
 * Pinegrap - Enterprise Website Platform
 *
 * The cookie notice of a visitor page (markup: pg_consent_markup() in
 * includes/fn/consent.php). Shows the notice until the visitor answers every
 * optional category the page has, keeps the answer in the pg_consent cookie,
 * starts the scripts held for an allowed category and, when a category is
 * turned off again, deletes its cookies and reloads so nothing of it keeps
 * running.
 *
 * Cookie value: "1" followed by ".<letter><0|1>" per answered category, the
 * same format pg_consent_parse() reads on the server. Answers for categories
 * this page does not show are carried over untouched.
 *
 * Plain ES5 and no jQuery: the notice must work on every page whatever the
 * design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var root = document.getElementById('pg-cc');

    if (!root || root.getAttribute('data-ready')) {
        return;
    }

    root.setAttribute('data-ready', '1');

    var COOKIE = 'pg_consent';
    var LETTERS = { analytics: 'a', marketing: 'm' };
    var MAX_AGE = 180 * 86400;

    var path = root.getAttribute('data-path') || '/';
    var categories = (root.getAttribute('data-categories') || '').split(',').filter(function (c) {
        return LETTERS.hasOwnProperty(c);
    });

    var fab = root.querySelector('.pg-cc-fab');
    var card = root.querySelector('.pg-cc-card');
    var views = {
        intro: root.querySelector('[data-pg-cc-view="intro"]'),
        prefs: root.querySelector('[data-pg-cc-view="prefs"]')
    };
    var switches = root.querySelectorAll('.pg-cc-switch');

    // ── The stored answer ───────────────────────────────────────────────

    function read() {
        var match = document.cookie.match(/(?:^|;\s*)pg_consent=([^;]*)/);

        if (!match) {
            return null;
        }

        var parts = match[1].split('.');

        if (parts.shift() !== '1') {
            return null;
        }

        var choices = {};

        parts.forEach(function (part) {
            for (var category in LETTERS) {
                if (LETTERS.hasOwnProperty(category) && part.length === 2 && part.charAt(0) === LETTERS[category]) {
                    if (part.charAt(1) === '1' || part.charAt(1) === '0') {
                        choices[category] = (part.charAt(1) === '1');
                    }
                }
            }
        });

        return choices;
    }

    function write(choices) {
        var value = '1';

        for (var category in LETTERS) {
            if (LETTERS.hasOwnProperty(category) && choices.hasOwnProperty(category)) {
                value += '.' + LETTERS[category] + (choices[category] ? '1' : '0');
            }
        }

        document.cookie = COOKIE + '=' + value + '; Max-Age=' + MAX_AGE + '; Path=' + path + '; SameSite=Lax'
            + (location.protocol === 'https:' ? '; Secure' : '');
    }

    // Answered when the cookie exists and covers every category on this page.
    function answered(stored) {
        if (!stored) {
            return false;
        }

        for (var i = 0; i < categories.length; i++) {
            if (!stored.hasOwnProperty(categories[i])) {
                return false;
            }
        }

        return true;
    }

    // ── Held scripts ────────────────────────────────────────────────────

    // A held script is re-created as a live one in the same place. External
    // scripts keep their relative order (async off unless the original asked
    // for it), the way they would have run had they not been held.
    function start(choices) {
        var held = document.querySelectorAll('script[type="text/plain"][data-pg-consent]');

        Array.prototype.forEach.call(held, function (node) {
            if (!choices[node.getAttribute('data-pg-consent')]) {
                return;
            }

            var live = document.createElement('script');

            Array.prototype.forEach.call(node.attributes, function (attribute) {
                var name = attribute.name.toLowerCase();

                if (name !== 'type' && name !== 'src' && name.indexOf('data-pg-') !== 0) {
                    live.setAttribute(attribute.name, attribute.value);
                }
            });

            var src = node.getAttribute('data-pg-src') || node.getAttribute('src');

            if (src) {
                live.async = node.hasAttribute('async');
                live.src = src;
            } else {
                live.text = node.text;
            }

            node.parentNode.replaceChild(live, node);
        });

        // A tag manager or gtag the site set up with Consent Mode defaults
        // hears the answer too.
        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', {
                analytics_storage: choices.analytics ? 'granted' : 'denied',
                ad_storage: choices.marketing ? 'granted' : 'denied',
                ad_user_data: choices.marketing ? 'granted' : 'denied',
                ad_personalization: choices.marketing ? 'granted' : 'denied'
            });
        }

        var event;

        try {
            event = new CustomEvent('pg:consent', { detail: choices });
        } catch (e) {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('pg:consent', false, false, choices);
        }

        document.dispatchEvent(event);
    }

    // ── Revoking ────────────────────────────────────────────────────────

    // A cookie may have been set for the host or for any parent domain
    // (Google Analytics uses the registrable one), and at / or the site path.
    function remove(name) {
        var expired = '=; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT';
        var host = location.hostname.split('.');
        var domains = [''];

        for (var i = 0; i < host.length - 1; i++) {
            domains.push('; Domain=.' + host.slice(i).join('.'));
        }

        [path, '/'].forEach(function (p) {
            domains.forEach(function (domain) {
                document.cookie = name + expired + '; Path=' + p + domain;
            });
        });
    }

    function revoke(patterns) {
        var names = document.cookie.split(';').map(function (pair) {
            return pair.split('=')[0].replace(/^\s+|\s+$/g, '');
        });

        patterns.forEach(function (pattern) {
            if (pattern.charAt(pattern.length - 1) === '*') {
                var prefix = pattern.slice(0, -1);

                names.forEach(function (name) {
                    if (name.indexOf(prefix) === 0) {
                        remove(name);
                    }
                });
            } else if (names.indexOf(pattern) !== -1) {
                remove(pattern);
            }
        });
    }

    // ── Deciding ────────────────────────────────────────────────────────

    function decide(choices) {
        var before = read() || {};
        var merged = {};
        var patterns = [];
        var category;

        for (category in before) {
            if (before.hasOwnProperty(category)) {
                merged[category] = before[category];
            }
        }

        categories.forEach(function (c) {
            merged[c] = !!choices[c];
        });

        write(merged);

        // What was on and is off now: its cookies go, and the page reloads,
        // because a script that already ran cannot be stopped in place.
        Array.prototype.forEach.call(switches, function (input) {
            category = input.getAttribute('data-pg-cc-category');

            if (before[category] === true && merged[category] === false) {
                patterns = patterns.concat((input.getAttribute('data-pg-cc-revoke') || '').split(' ').filter(Boolean));
            }
        });

        if (patterns.length) {
            revoke(patterns);
            location.reload();
            return;
        }

        start(merged);
        hide();
    }

    function all(value) {
        var choices = {};

        categories.forEach(function (c) {
            choices[c] = value;
        });

        return choices;
    }

    // ── Showing ─────────────────────────────────────────────────────────

    function view(name) {
        views.intro.hidden = (name !== 'intro');
        views.prefs.hidden = (name !== 'prefs');
    }

    function sync() {
        var stored = read() || {};

        Array.prototype.forEach.call(switches, function (input) {
            var category = input.getAttribute('data-pg-cc-category');

            // An unanswered category stays as drawn: switched on.
            if (stored.hasOwnProperty(category)) {
                input.checked = stored[category];
            }
        });
    }

    function show(focus) {
        sync();
        view('intro');
        card.hidden = false;
        fab.hidden = true;
        fab.setAttribute('aria-expanded', 'true');

        if (focus) {
            var first = card.querySelector('.pg-cc-btn');

            if (first) {
                first.focus();
            }
        }
    }

    function hide() {
        var had_focus = card.contains(document.activeElement);

        card.hidden = true;
        fab.hidden = false;
        fab.setAttribute('aria-expanded', 'false');

        if (had_focus) {
            fab.focus();
        }
    }

    root.addEventListener('click', function (event) {
        var target = event.target.closest ? event.target.closest('[data-pg-cc], .pg-cc-fab') : null;

        if (!target) {
            return;
        }

        if (target === fab) {
            show(true);
            return;
        }

        switch (target.getAttribute('data-pg-cc')) {
            case 'settings':
                sync();
                view('prefs');
                break;

            case 'accept':
                decide(all(true));
                break;

            case 'reject':
                decide(all(false));
                break;

            case 'save':
                var choices = {};

                Array.prototype.forEach.call(switches, function (input) {
                    choices[input.getAttribute('data-pg-cc-category')] = input.checked;
                });

                decide(choices);
                break;

            case 'close':
                // Closing before answering keeps the necessary cookies only;
                // after an answer it just closes.
                if (answered(read())) {
                    hide();
                } else {
                    decide(all(false));
                }
                break;
        }
    });

    root.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' && event.key !== 'Esc') {
            return;
        }

        if (!views.prefs.hidden) {
            view('intro');
            var settings = card.querySelector('[data-pg-cc="settings"]');

            if (settings) {
                settings.focus();
            }
        } else if (!card.hidden && answered(read())) {
            hide();
        }
    });

    var stored = read();

    if (answered(stored)) {
        start(stored);
        fab.hidden = false;
    } else {
        show(false);
    }
})();
