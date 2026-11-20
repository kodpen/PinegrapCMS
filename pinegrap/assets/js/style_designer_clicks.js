/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the clicks part: the "Clicks" layer over the canvas.
 * Each link and button of the page on the canvas is named by the same four
 * parts the visitors' script names a click by (assets/js/pg_clicks.src.js:
 * the part of the page, link or button, the address and the label, folded
 * as pg_page_clicks_fold_url() and pg_page_clicks_fold_label() fold them)
 * and matched to the elements counted for the page over the last 30 days:
 * a badge with its share of the page's views, lines at the tenths of the
 * page's height with the share of views reaching each, a device choice.
 * The elements counted for the page that the canvas does not hold are
 * listed beside it, never dropped. While the site's "Clicks and scrolling"
 * switch is off the layer says what would be kept and what never is, and
 * offers whoever may change Features the button that turns it on (the
 * Visitors section's form post, visitors_clicks_switch.php, which saves
 * through pg_page_clicks_switch_save()). The figures count every variant
 * of an experiment together. Nothing is kept about a visitor.
 *
 * Not needed to open a page: the editor runs this file the first time the
 * layer is asked for (_sdPartLoad(), pg_designer_parts()). It reaches the
 * editor only through the kit (_sdPartKit()) and the host handed to open()
 * (the element beside the canvas the panel is drawn in).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('clicks', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    var LABEL = 60;
    var URL_MOST = 255;
    var DEVICES = ['', 'desktop', 'mobile', 'tablet'];

    // The twins of the visitors' script's maps (pg_clicks.src.js).
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

    // What the visitors' script counts as a link or a button.
    var CLICKABLE = 'a[href], button, [role="button"], [role="link"], input[type="submit"], input[type="button"], input[type="image"]';

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

    // The twin of pg_page_clicks_fold_url() and the script's foldUrl().
    function foldUrl(url, siteHost) {
        if (typeof url !== 'string') {
            return '';
        }

        url = url.replace(/[\x00-\x20\x7F]+/g, '');

        var scheme = /^([a-z][a-z0-9+.\-]*):/i.exec(url);

        if (scheme) {
            var kind = scheme[1].toLowerCase();

            if (has(MESSAGING_SCHEMES, kind)) {
                return MESSAGING_SCHEMES[kind];
            }

            if ((kind !== 'http') && (kind !== 'https')) {
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

    // The twin of pg_page_clicks_fold_label() and the script's foldLabel().
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

    function attr(el, key) {
        return (el && el.getAttribute) ? (el.getAttribute(key) || '') : '';
    }

    // Whether an element sits in the panel bar or the cookie notice, which
    // the script leaves out.
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

    // The visible text, else aria-label, else a picture's alt; a submit
    // input's own value.
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

    // A link's address as the live page's browser resolves it: against the
    // page's own address, since the canvas is not served from it.
    function addressOf(el, base) {
        var raw = attr(el, 'href');

        try {
            return base ? String(new URL(raw, base).href) : raw;
        } catch (e) {
            return raw;
        }
    }

    // The four parts of a link or button of the canvas, as the visitors'
    // script names it when clicked (its name()), or null.
    function nameOf(el, siteHost, base) {
        if (!el || leftOut(el)) {
            return null;
        }

        var role = ((tagOf(el) === 'a') || (attr(el, 'role').toLowerCase() === 'link')) ? 'link' : 'button';
        var url = (role === 'link') ? foldUrl(addressOf(el, base), siteHost) : '';
        var label = foldLabel(labelOf(el));

        if ((url === '') && (label === '')) {
            return null;
        }

        return [areaOf(el), role, url, label];
    }

    function keyOf(parts) {
        return parts.join('\n');
    }

    // The canvas' links and buttons paired with the counted elements by
    // their four parts (the server keeps them by binary comparison, so the
    // match is exact): found lists each canvas element with its row (a row
    // can badge several), missing the rows no canvas element holds, in the
    // report's order (most clicked first).
    function match(nodes, elements, siteHost, base) {
        var rows = {};
        var used = {};
        var found = [];

        (elements || []).forEach(function (entry) {
            rows[keyOf([String(entry.area), String(entry.role), String(entry.url), String(entry.label)])] = entry;
        });

        (nodes || []).forEach(function (el) {
            var parts = nameOf(el, siteHost, base);
            var key = parts ? keyOf(parts) : '';

            if (parts && has(rows, key)) {
                used[key] = true;
                found.push({ el: el, row: rows[key] });
            }
        });

        var missing = (elements || []).filter(function (entry) {
            return !has(used, keyOf([String(entry.area), String(entry.role), String(entry.url), String(entry.label)]));
        });

        return { found: found, missing: missing };
    }

    // A share (0-1) as a percent with one decimal, as the Visitors section.
    function pct(share) {
        if ((share === null) || (share === undefined) || isNaN(share)) {
            return '–';
        }

        var value = Math.round(Number(share) * 1000) / 10;

        return _sdT('{var:1}%', (value % 1 === 0) ? String(value) : value.toFixed(1));
    }

    // The lines at the tenths of a page's height: where each sits (px from
    // the top) and the share of views that reached it. Tenths without a
    // share are left out; tenth 0 (every view) has no line.
    function lines(reach, height) {
        var out = [];

        (reach || []).forEach(function (r) {
            var tenth = parseInt(r && r.tenth, 10);

            if (!(tenth >= 1 && tenth <= 10) || (r.share === null) || (r.share === undefined)) {
                return;
            }

            out.push({ tenth: tenth, top: Math.max(0, Math.round(height * tenth / 10) - 1), share: r.share, text: pct(r.share) });
        });

        return out;
    }

    // A device the choice may hold; anything else is all devices.
    function deviceOf(value) {
        return (DEVICES.indexOf(String(value || '')) > 0) ? String(value) : '';
    }

    // What the designer's read of the figures is asked for one page over
    // the last 30 days, for one device or all; null for a page not saved.
    function ask(pageId, device) {
        if (!(pageId > 0)) {
            return null;
        }

        var d = deviceOf(device);

        return d ? { page_id: pageId, device: d } : { page_id: pageId };
    }

    var AREAS = null;

    function areaName(area) {
        if (!AREAS) {
            AREAS = { header: _sdT('Header'), nav: _sdT('Navigation'), main: _sdT('Main content'), footer: _sdT('Footer'), aside: _sdT('Side column'), page: _sdT('Page') };
        }

        return has(AREAS, area) ? AREAS[area] : _sdT('Page');
    }

    // The layer while the switch is off: what would be kept, what never
    // is, and the button for whoever may change Features (others are told
    // who can). HTML.
    function empty(may) {
        return '<div class="sd-clicks-off">'
            + '<p class="small mb-3">' + esc(_sdT('When it is on, the site counts which links and buttons visitors click on each page and how far down they scroll, per page, day and device.')) + '</p>'
            + '<p class="small mb-5">' + esc(_sdT('No cookie, no identifier and nothing about a visitor is kept; signed-in members and visitors who refused statistics are never counted.')) + '</p>'
            + (may
                ? '<button type="button" class="btn btn-sm btn-solid theme-primary" data-sd-clicks-on><i class="bi bi-toggle-on me-1" aria-hidden="true"></i>' + esc(_sdT('Turn on')) + '</button>'
                : '<p class="small fg-secondary mb-0">' + esc(_sdT('A manager can turn it on in Site Settings, Features.')) + '</p>')
            + '</div>';
    }

    // The counted elements the canvas does not hold, beside it: the label
    // (else the address) as text, the part and kind as a quiet line, the
    // share. HTML; visitors' words only through esc().
    function list(missing, other) {
        var html = '';

        if (missing && missing.length) {
            html += '<h6 class="small fw-semibold mt-5 mb-1">' + esc(_sdT('Counted, not on this canvas')) + '</h6>'
                + '<ul class="list-unstyled small mb-0 sd-clicks-missing">';

            missing.forEach(function (entry) {
                var first = String(entry.label || '') || String(entry.url || '') || ((entry.role === 'button') ? _sdT('Button') : _sdT('Link'));
                var second = areaName(String(entry.area)) + ' · ' + ((entry.role === 'button') ? _sdT('Button') : _sdT('Link'))
                    + ((entry.label && entry.url) ? ' · ' + String(entry.url) : '');

                html += '<li class="d-flex justify-content-between gap-3 py-1 border-bottom">'
                    + '<span class="text-break"><span class="d-block">' + esc(first) + '</span>'
                    + '<span class="d-block fg-secondary">' + esc(second) + '</span></span>'
                    + '<span class="text-nowrap">' + esc(pct(entry.share)) + '</span></li>';
            });

            html += '</ul>';
        }

        if (other > 0) {
            html += '<p class="small fg-secondary mt-3 mb-0">' + esc(_sdT('Other elements: {var:1} clicks', String(other))) + '</p>';
        }

        return html;
    }

    // ── The layer on the canvas ─────────────────────────────────────────

    // The editor's own settings for the layer (sdDesign.clicks, handed by
    // the designer screen): visitors (may open Visitors), features (may
    // change Features), on, ready, turnOn.
    function settings() {
        var d = K.design;

        return (d && d.clicks && typeof d.clicks === 'object') ? d.clicks : {};
    }

    function activePage() {
        var pages = K.pages || [];

        for (var i = 0; i < pages.length; i++) {
            if (pages[i] && pages[i].key === K.activeKey) {
                return pages[i];
            }
        }

        return null;
    }

    function siteHost() {
        return String((window.location && window.location.host) || '').replace(/:\d+$/, '');
    }

    // The page's own address, which links on it are resolved against.
    function pageBase(page) {
        var origin = (window.location && window.location.origin) ? window.location.origin : '';
        var path = (window.OUTPUT_PATH || '/') + ((page && page.page_name) ? encodeURI(String(page.page_name)) : '');

        return origin ? origin + path : '';
    }

    var H = null;
    var state = { device: '', report: null, seq: 0 };

    function clearCanvas() {
        var doc = K.canvasDoc;
        var old = doc && doc.getElementById ? doc.getElementById('sd-clicks-layer') : null;

        if (old && old.parentNode) {
            old.parentNode.removeChild(old);
        }
    }

    // Badges and tenth lines in one layer over the canvas' page, which
    // takes no pointer, so the canvas is edited as before.
    function drawCanvas() {
        clearCanvas();

        var doc = K.canvasDoc;
        var page = state.report;

        if (!doc || !doc.body || !page) {
            return { found: [], missing: page ? page.elements || [] : [] };
        }

        var nodes = Array.prototype.slice.call(doc.querySelectorAll(CLICKABLE));
        var paired = match(nodes, page.elements, siteHost(), pageBase(activePage()));
        var view = doc.defaultView || window;
        var x = view.pageXOffset || 0;
        var y = view.pageYOffset || 0;
        var height = Math.max(doc.documentElement.scrollHeight || 0, doc.body.scrollHeight || 0);
        var layer = doc.createElement('div');

        layer.id = 'sd-clicks-layer';
        layer.setAttribute('aria-hidden', 'true');
        layer.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:0;z-index:2147483000;pointer-events:none;';

        paired.found.forEach(function (pair) {
            var rect = pair.el.getBoundingClientRect();
            var badge = doc.createElement('span');

            badge.className = 'sd-clicks-badge';
            badge.textContent = pct(pair.row.share);
            badge.title = String(pair.row.label || pair.row.url || '');
            badge.style.cssText = 'position:absolute;left:' + Math.round(rect.left + x) + 'px;top:' + Math.max(0, Math.round(rect.top + y) - 10) + 'px;';
            layer.appendChild(badge);
        });

        lines(page.reach, height).forEach(function (line) {
            var mark = doc.createElement('div');

            mark.className = 'sd-clicks-line';
            mark.textContent = _sdT('{var:1} reach here', line.text);
            mark.style.cssText = 'position:absolute;left:0;right:0;top:' + line.top + 'px;';
            layer.appendChild(mark);
        });

        doc.body.appendChild(layer);

        return paired;
    }

    function drawPanel(html) {
        if (H && H.panel) {
            H.panel.innerHTML = html;
        }
    }

    function deviceChoice() {
        var names = { '': _sdT('All devices'), desktop: _sdT('Desktop'), mobile: _sdT('Mobile'), tablet: _sdT('Tablet') };

        return '<select class="form-control form-control-sm mb-3" data-sd-clicks-device aria-label="' + esc(_sdT('Device')) + '">'
            + DEVICES.map(function (d) {
                return '<option value="' + esc(d) + '"' + ((d === state.device) ? ' selected' : '') + '>' + esc(names[d]) + '</option>';
            }).join('') + '</select>';
    }

    // Draws the panel and the canvas from the figures in hand.
    function refresh() {
        if (!H) {
            return;
        }

        var s = settings();

        if (!s.on) {
            clearCanvas();
            drawPanel(empty(!!s.features));
            return;
        }

        if (s.ready === false) {
            clearCanvas();
            drawPanel('<p class="small mb-0">' + esc(_sdT('The database has not been upgraded yet.')) + '</p>');
            return;
        }

        var page = state.report;
        var head = deviceChoice() + '<p class="small fg-secondary mb-3">' + esc(_sdT('Last 30 days. Every variant of an experiment on this page is counted together.')) + '</p>';

        if (!page) {
            clearCanvas();
            drawPanel(head + '<p class="small mb-0">' + esc(_sdT('Loading...')) + '</p>');
            return;
        }

        if (!(page.views > 0)) {
            clearCanvas();
            drawPanel(head + '<p class="small mb-0">' + esc(_sdT('No page views were counted on this page in this period.')) + '</p>');
            return;
        }

        var paired = drawCanvas();

        drawPanel(head + '<p class="small mb-0">' + esc(_sdT('{var:1} views', String(page.views))) + '</p>' + list(paired.missing, page.other));
    }

    // Asks the report for the active page and device; an answer to an
    // earlier ask is dropped.
    function load() {
        var s = settings();
        var page = activePage();
        var asked = ask(page ? parseInt(page.page_id, 10) : 0, state.device);
        var seq = ++state.seq;

        state.report = null;

        if (!s.on || (s.ready === false)) {
            refresh();
            return Promise.resolve(null);
        }

        if (!asked) {
            clearCanvas();
            drawPanel('<p class="small mb-0">' + esc(_sdT('Save the page first to see its clicks.')) + '</p>');
            return Promise.resolve(null);
        }

        refresh();

        // The editor's own read (sub-action page_clicks of the designer's
        // panel endpoint): the page's figures as GET /reports/page-clicks
        // gives them, under the session and the Visitors right.
        return K._pgApiPost('page_clicks', asked)
            .then(function (body) {
                if (seq !== state.seq || !H) {
                    return null;
                }

                if (!body || body.status !== 'success') {
                    throw new Error('refused');
                }

                state.report = body.page ? body.page : { views: 0, reach: [], elements: [], other: 0 };
                refresh();

                return state.report;
            })
            .catch(function () {
                if (seq === state.seq && H) {
                    drawPanel('<p class="small mb-0">' + esc(_sdT('The figures could not be loaded. Check the connection and try again.')) + '</p>');
                }

                return null;
            });
    }

    // The empty state's button: the Visitors section's form post, which
    // checks the Features right and the token and saves through the one
    // saver. Its answer is a redirect, not followed.
    function turnOn(button) {
        var s = settings();
        var body = new FormData();

        body.append('token', K._pgToken());
        body.append('back', '');

        if (button) {
            button.disabled = true;
        }

        return fetch(s.turnOn, { method: 'POST', body: body, credentials: 'same-origin', redirect: 'manual' })
            .then(function (r) {
                if (!(r.ok || r.type === 'opaqueredirect')) {
                    throw new Error('refused');
                }

                s.on = true;
                return load();
            })
            .catch(function () {
                if (button) {
                    button.disabled = false;
                }

                K.sdToast(esc(_sdT('Counting could not be turned on. Try again.')), 'error');
            });
    }

    function onPanelClick(e) {
        var button = e.target && e.target.closest ? e.target.closest('[data-sd-clicks-on]') : null;

        if (button && settings().features) {
            turnOn(button);
        }
    }

    function onPanelChange(e) {
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-sd-clicks-device')) {
            state.device = deviceOf(e.target.value);
            load();
        }
    }

    // Opens the layer: host.panel is the element beside the canvas the
    // panel is drawn in. Refused for whoever may not open Visitors.
    function open(host) {
        if (!settings().visitors || !host || !host.panel) {
            return false;
        }

        close();
        H = host;
        H.panel.addEventListener('click', onPanelClick);
        H.panel.addEventListener('change', onPanelChange);
        load();

        return true;
    }

    function close() {
        clearCanvas();

        if (H && H.panel) {
            H.panel.removeEventListener('click', onPanelClick);
            H.panel.removeEventListener('change', onPanelChange);
            H.panel.innerHTML = '';
        }

        H = null;
        state.seq++;
    }

    return {
        open: open,
        close: close,
        refresh: refresh,
        reload: load,
        foldUrl: foldUrl,
        foldLabel: foldLabel,
        name: nameOf,
        match: match,
        lines: lines,
        ask: ask,
        deviceOf: deviceOf,
        empty: empty,
        list: list,
        pct: pct
    };
});
