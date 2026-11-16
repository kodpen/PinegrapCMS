/**
 * Pinegrap - Enterprise Website Platform
 *
 * Clicks and scroll depth of a public page (served as pg_clicks.min.js,
 * printed by pg_page_clicks_beacon_tag() in includes/fn/page_clicks.php
 * while the "Clicks and scrolling" switch is on).
 *
 * A click on a link, a button or a submit control is named by four parts:
 * the part of the page (its nearest landmark: header, nav, main, footer,
 * aside, else page), link or button, the address and the label (the
 * visible text, else aria-label, else a picture's alt). Address and label
 * are folded here as the server folds them again (pg_page_clicks_fold_url(),
 * pg_page_clicks_fold_label()): no query string or fragment, a contact or
 * messaging link only by its kind, a code-like path part as "*", a label
 * holding an e-mail address or a long number left empty. Each element
 * counts once a page view; nothing typed is read, no position is kept, and
 * the panel bar and the cookie notice are left out.
 *
 * The deepest tenth of the page's height the bottom of the window reached
 * (0 to 10) is kept as the visitor scrolls. One request a page view, when
 * the page is hidden or left: the page id, its day signature, the width
 * class and the figures. With the speed beacon on the page they travel in
 * its request (pg_vitals.src.js asks window.pgClicks.take()). Nothing is
 * sent when the visitor refused statistics in the cookie notice
 * (software.statistics_not_refused(), frontend.src.js). No cookie, no
 * identifier, nothing written on the device.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // Elements one request names; the server takes no more.
    var MOST = 20;
    var LABEL = 60;
    var URL_MOST = 255;

    var MESSAGING_SCHEMES = {
        mailto: 'mailto:', tel: 'tel:', sms: 'sms:', smsto: 'sms:', mms: 'sms:',
        callto: 'tel:', facetime: 'facetime:', 'facetime-audio': 'facetime:',
        whatsapp: 'whatsapp:', tg: 'telegram:', viber: 'viber:', skype: 'skype:',
        sgnl: 'signal:', 'fb-messenger': 'messenger:', line: 'line:', msteams: 'teams:'
    };

    var MESSAGING_HOSTS = {
        'wa.me': 'whatsapp:', 'api.whatsapp.com': 'whatsapp:', 'web.whatsapp.com': 'whatsapp:',
        'chat.whatsapp.com': 'whatsapp:', 'whatsapp.com': 'whatsapp:', 'www.whatsapp.com': 'whatsapp:',
        't.me': 'telegram:', 'telegram.me': 'telegram:', 'm.me': 'messenger:',
        'signal.me': 'signal:', 'line.me': 'line:', 'invite.viber.com': 'viber:'
    };

    var LANDMARKS = { header: 'header', nav: 'nav', main: 'main', footer: 'footer', aside: 'aside' };
    var ROLES = { banner: 'header', navigation: 'nav', main: 'main', contentinfo: 'footer', complementary: 'aside' };

    function has(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    // A text cut to a number of characters (code points, as mb_substr()).
    function cut(text, length) {
        return Array.from ? Array.from(text).slice(0, length).join('') : text.slice(0, length);
    }

    // A path part that looks like a code (pg_page_clicks_code_like()).
    function codeLike(part) {
        return (part.length > 16) && (part.indexOf('-') < 0) && /[A-Za-z]/.test(part) && /[0-9]/.test(part);
    }

    // The twin of pg_page_clicks_fold_url().
    function foldUrl(url, siteHost) {
        if (typeof url !== 'string') {
            return '';
        }

        url = url.replace(/[\x00-\x20\x7F]+/g, '');

        var scheme = /^([a-z][a-z0-9+.\-]*):/i.exec(url);

        if (scheme) {
            var name = scheme[1].toLowerCase();

            if (has(MESSAGING_SCHEMES, name)) {
                return MESSAGING_SCHEMES[name];
            }

            if ((name !== 'http') && (name !== 'https')) {
                return '';
            }
        } else if ((url.slice(0, 2) !== '//') && (url.charAt(0) !== '/')) {
            return '';
        }

        url = url.replace(/[?#][\s\S]*$/, '');

        var host = '';
        var path = url;
        var full = /^(?:[a-z][a-z0-9+.\-]*:)?\/\/([^\/]*)([\s\S]*)$/i.exec(url);

        if (full) {
            host = full[1].replace(/^[^@]*@|:\d+$/g, '').toLowerCase();
            path = full[2];

            if (host === '') {
                return '';
            }

            if (has(MESSAGING_HOSTS, host)) {
                return MESSAGING_HOSTS[host];
            }

            if (host === String(siteHost || '').toLowerCase()) {
                host = '';
            }
        }

        path = path.split('/').map(function (part) {
            return codeLike(part) ? '*' : part;
        }).join('/');

        return cut((host === '') ? ((path === '') ? '/' : path) : '//' + host + path, URL_MOST);
    }

    // The twin of pg_page_clicks_fold_label().
    function foldLabel(label) {
        if (typeof label !== 'string') {
            return '';
        }

        label = label.replace(/[\x00-\x08\x0E-\x1F\x7F]+/g, '').replace(/\s+/g, ' ').trim();

        if (/[^\s@]+@[^\s@]+\.[^\s@]+/.test(label) || /[0-9]{7,}/.test(label)) {
            return '';
        }

        return cut(label, LABEL).trim();
    }

    function tagOf(el) {
        return (el && el.tagName) ? String(el.tagName).toLowerCase() : '';
    }

    function attr(el, name) {
        return (el && el.getAttribute) ? (el.getAttribute(name) || '') : '';
    }

    // The clickable element a click landed in, or null: a link, a button, a
    // submit or button input, or an element with the role of either.
    function targetOf(el) {
        for (var node = el; node && node.tagName; node = node.parentElement) {
            var tag = tagOf(node);
            var role = attr(node, 'role').toLowerCase();
            var type = attr(node, 'type').toLowerCase();

            if ((tag === 'a' && node.getAttribute && (node.getAttribute('href') !== null)) || (tag === 'button') || (role === 'button') || (role === 'link')
                || ((tag === 'input') && ((type === 'submit') || (type === 'button') || (type === 'image')))) {
                return node;
            }
        }

        return null;
    }

    // Whether an element sits in the panel bar or the cookie notice.
    function leftOut(el) {
        for (var node = el; node && node.tagName; node = node.parentElement) {
            var id = attr(node, 'id');

            if ((id === 'software_toolbar') || (id === 'pg-cc')) {
                return true;
            }
        }

        return false;
    }

    // The part of the page an element sits in, by its nearest landmark.
    function areaOf(el) {
        for (var node = el; node && node.tagName; node = node.parentElement) {
            var role = attr(node, 'role').toLowerCase();

            if (has(ROLES, role)) {
                return ROLES[role];
            }

            var tag = tagOf(node);

            if (has(LANDMARKS, tag)) {
                return LANDMARKS[tag];
            }
        }

        return 'page';
    }

    // What the element says: its visible text, else aria-label, else a
    // picture's alt. A submit input's own value is its text; a field's
    // typed value is never read.
    function labelOf(el) {
        var tag = tagOf(el);
        var text = '';

        if (tag === 'input') {
            text = (attr(el, 'type').toLowerCase() === 'image') ? attr(el, 'alt') : attr(el, 'value');
        } else {
            text = String(((el.innerText !== undefined) ? el.innerText : el.textContent) || '');
        }

        if (text.replace(/\s+/g, '') === '') {
            text = attr(el, 'aria-label');
        }

        if ((text.replace(/\s+/g, '') === '') && el.querySelector) {
            var picture = el.querySelector('img[alt]');
            text = picture ? attr(picture, 'alt') : '';
        }

        return text;
    }

    // The four parts of a clicked element, or null when it is no link or
    // button, sits in the panel bar or the notice, or has neither address
    // nor label left after the fold.
    function name(el, siteHost) {
        var target = targetOf(el);

        if (!target || leftOut(target)) {
            return null;
        }

        var role = ((tagOf(target) === 'a') || (attr(target, 'role').toLowerCase() === 'link')) ? 'link' : 'button';
        var url = (role === 'link') ? foldUrl(String(target.href || attr(target, 'href')), siteHost) : '';
        var label = foldLabel(labelOf(target));

        if ((url === '') && (label === '')) {
            return null;
        }

        return [areaOf(target), role, url, label];
    }

    // The deepest tenth of a page's height the bottom of the window reached.
    function tenth(scrolled, windowHeight, pageHeight) {
        if (!(pageHeight > 0) || (scrolled + windowHeight >= pageHeight)) {
            return 10;
        }

        return Math.max(0, Math.min(10, Math.floor((scrolled + windowHeight) / pageHeight * 10)));
    }

    var api = { foldUrl: foldUrl, foldLabel: foldLabel, name: name, tenth: tenth, take: function () { return null; } };

    root.pgClicks = api;

    var doc = root.document;
    var script = doc && doc.currentScript;

    if (!script || !root.navigator || !root.navigator.sendBeacon || !root.FormData || !doc.addEventListener) {
        return;
    }

    var endpoint = script.getAttribute('data-u') || '';
    var page = script.getAttribute('data-p') || '';
    var signature = script.getAttribute('data-s') || '';

    if (!endpoint || !page || !signature) {
        return;
    }

    var host = String((root.location && root.location.host) || '').replace(/:\d+$/, '');
    var elements = [];
    var seen = {};
    var deepest = 0;
    var sent = false;

    function measure() {
        var html = doc.documentElement;
        var height = Math.max(html.scrollHeight || 0, (doc.body && doc.body.scrollHeight) || 0);
        var reached = tenth(root.pageYOffset || html.scrollTop || 0, root.innerHeight || html.clientHeight || 0, height);

        if (reached > deepest) {
            deepest = reached;
        }
    }

    doc.addEventListener('click', function (event) {
        if (sent || (elements.length >= MOST)) {
            return;
        }

        var parts = name(event.target, host);

        if (!parts) {
            return;
        }

        var key = parts.join('\n');

        if (!has(seen, key)) {
            seen[key] = true;
            elements.push(parts);
        }
    }, true);

    var waiting = false;

    root.addEventListener('scroll', function () {
        if (waiting) {
            return;
        }

        waiting = true;
        root.setTimeout(function () {
            waiting = false;
            measure();
        }, 200);
    }, { passive: true });

    // The width class the server keeps the figures under (as the speed
    // beacon's).
    function device() {
        var width = root.innerWidth || doc.documentElement.clientWidth || 0;

        if (width < 768) {
            return 'mobile';
        }

        return (width < 1024) ? 'tablet' : 'desktop';
    }

    // The fields of this page view's request, once; null when they were
    // taken already or the visitor refused statistics.
    api.take = function () {
        if (sent) {
            return null;
        }

        sent = true;

        var software = root.software;

        if (!software || (typeof software.statistics_not_refused !== 'function') || !software.statistics_not_refused()) {
            return null;
        }

        measure();

        return { cp: page, cs: signature, c: JSON.stringify({ d: device(), t: deepest, e: elements }) };
    };

    function send() {
        // With the speed beacon on the page, its request carries these.
        if (root.pgVitalsSends) {
            return;
        }

        var fields = api.take();

        if (!fields) {
            return;
        }

        try {
            var data = new root.FormData();

            for (var key in fields) {
                if (has(fields, key)) {
                    data.append(key, fields[key]);
                }
            }

            root.navigator.sendBeacon(endpoint, data);
        } catch (e) {
            // A lost count is no error for the visitor.
        }
    }

    measure();

    doc.addEventListener('visibilitychange', function () {
        if (doc.visibilityState === 'hidden') {
            send();
        }
    });

    root.addEventListener('pagehide', send);

    // Back from the back-forward cache: this page view was counted.
    root.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            sent = true;
        }
    });
})(window);
