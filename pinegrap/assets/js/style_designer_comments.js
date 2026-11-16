/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the comments part: what the customers wrote on the
 * design's prototype through its share links (prototype_comments). The pins
 * of the open tab's page, numbered, over the canvas at the place each
 * comment names inside its node, and the Comments list by page with its open
 * and resolved threads: Answer, Resolve and Reopen, Delete. A thread whose
 * node is no longer on its page stays in the list and says so.
 *
 * Designers only (sdDesign.parts.comments). Not needed to open a page: the
 * presence heartbeat runs it once the design has a comment, handing it the
 * newest comment's id (beat()); the list is fetched only when that id moves,
 * so there is no second loop. It reaches the editor only through the kit
 * (_sdPartKit()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('comments', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;
    var _pgApiPost = K._pgApiPost;

    var S = {
        stamp: -1,          // the newest comment's id the list was read at
        seen: 0,            // the newest id the designer has looked at
        pages: [],          // the server's list: page_id, name, open, resolved, threads
        filter: 'open',     // the list's tab: open | resolved
        panel: null,
        btn: null,
        layer: null,        // the pins, over the canvas frame
        doc: null,          // the canvas document the pins are bound to
        observer: null,
        sizer: null,
        raf: 0,
        fetching: null,
        focus: 0,           // the thread the list shows marked
        answering: 0,       // the thread whose answer box is open
        deleting: 0         // the thread whose delete asks to be confirmed
    };

    function styleId() {
        var d = K.design;
        return (d && d.styleId) ? (parseInt(d.styleId, 10) || 0) : 0;
    }

    function activePage() {
        var key = K.activeKey;
        var list = K.pages || [];
        for (var i = 0; i < list.length; i++) if (list[i].key === key) return list[i];
        return null;
    }

    function pageById(pageId) {
        var list = K.pages || [];
        for (var i = 0; i < list.length; i++) if (parseInt(list[i].page_id, 10) === pageId) return list[i];
        return null;
    }

    function when(unix) {
        if (!(unix > 0)) return '';
        try {
            return new Date(unix * 1000).toLocaleString(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' });
        } catch (e) {
            return new Date(unix * 1000).toLocaleString();
        }
    }

    // ── What the server holds ─────────────────────────────────────────────

    function take(res) {
        if (!res || res.status !== 'success') return false;
        S.pages = Array.isArray(res.pages) ? res.pages : [];
        S.stamp = parseInt(res.stamp, 10) || 0;
        return true;
    }

    function fetchList() {
        if (S.fetching) return S.fetching;
        S.fetching = _pgApiPost('proto_comments', { style_id: styleId() }).then(function (res) {
            S.fetching = null;
            if (take(res)) drawAll();
        }, function () {
            S.fetching = null;
        });
        return S.fetching;
    }

    function act(sub, threadId, extra) {
        var body = { style_id: styleId(), comment_id: threadId };
        if (extra) Object.keys(extra).forEach(function (k) { body[k] = extra[k]; });
        return _pgApiPost(sub, body).then(function (res) {
            if (!take(res)) {
                sdToast(esc((res && res.message) || _sdT('Sorry, we could not accept your request.')), 'error');
                return false;
            }
            S.seen = S.stamp;
            drawAll();
            return true;
        }, function () {
            sdToast(esc(_sdT('Sorry, we could not accept your request.')), 'error');
            return false;
        });
    }

    // ── The threads ───────────────────────────────────────────────────────

    // A page's threads numbered as the customer saw them: by the first
    // comment, oldest first, open and resolved alike.
    function numbers(page) {
        var ids = page.threads.map(function (t) { return t.id; }).sort(function (a, b) { return a - b; });
        var out = {};
        ids.forEach(function (id, i) { out[id] = i + 1; });
        return out;
    }

    function openCount() {
        return S.pages.reduce(function (n, p) { return n + (parseInt(p.open, 10) || 0); }, 0);
    }

    // Whether a node is still on its page: looked for on the canvas when the
    // page is the open tab, else in the page's tree when the editor holds
    // it; null when it cannot be told (a page not open as a tab).
    function nodeThere(pageId, nodeId) {
        var act = activePage();
        if (act && parseInt(act.page_id, 10) === pageId && S.doc) return !!canvasEl(nodeId);
        var page = pageById(pageId);
        var tree = page ? K._pgTreeOf(page) : null;
        if (!tree) return null;
        var found = false;
        (function walk(n) {
            if (found || !n) return;
            if (n._id === nodeId) { found = true; return; }
            (n.children || []).forEach(walk);
        })(tree);
        return found;
    }

    function canvasEl(nodeId) {
        if (!S.doc || !/^[A-Za-z0-9_-]{1,64}$/.test(nodeId)) return null;
        return S.doc.querySelector('[data-sd-id="' + nodeId + '"]:not([data-sw-sid])');
    }

    // ── The toolbar button ────────────────────────────────────────────────

    function drawButton() {
        if (!S.btn) {
            var undo = document.getElementById('sd-main-undo');
            if (!undo || !undo.parentNode) return;
            S.btn = document.createElement('button');
            S.btn.type = 'button';
            S.btn.id = 'sd-pc-btn';
            S.btn.className = 'sd-icon-btn sd-pc-btn';
            S.btn.setAttribute('aria-expanded', 'false');
            S.btn.addEventListener('click', function () { toggle(); });
            undo.parentNode.insertBefore(S.btn, undo);
        }
        var open = openCount();
        S.btn.title = _sdT('Prototype comments');
        S.btn.innerHTML = '<span class="bi bi-chat-square-text" aria-hidden="true"></span>' +
            '<span class="sd-pc-btn-txt">' + esc(_sdT('Comments')) + '</span>' +
            (open > 0 ? '<span class="sd-pc-btn-count">' + esc(String(open)) + '</span>' : '') +
            (S.stamp > S.seen ? '<span class="sd-pc-btn-dot" aria-hidden="true"></span>' : '');
        S.btn.setAttribute('aria-label', _sdT('Prototype comments') + (open > 0 ? ' (' + open + ')' : ''));
    }

    // ── The list ──────────────────────────────────────────────────────────

    function buildPanel() {
        if (S.panel) return;
        S.panel = document.createElement('aside');
        S.panel.className = 'sd-pc-panel';
        S.panel.setAttribute('data-bs-theme', 'dark');
        S.panel.id = 'sd-pc-panel';
        S.panel.hidden = true;
        S.panel.setAttribute('aria-label', _sdT('Prototype comments'));
        document.body.appendChild(S.panel);

        S.panel.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target.closest('[data-pc]') : null;
            if (!t) return;
            var what = t.getAttribute('data-pc');
            var id = parseInt(t.getAttribute('data-id'), 10) || 0;
            if (what === 'close') { toggle(false); return; }
            if (what === 'tab') { S.filter = t.getAttribute('data-tab') === 'resolved' ? 'resolved' : 'open'; drawPanel(); return; }
            if (what === 'go') { goTo(id); return; }
            if (what === 'answer') { S.answering = (S.answering === id) ? 0 : id; S.deleting = 0; drawPanel(); focusAnswer(id); return; }
            if (what === 'resolve' || what === 'reopen') { busy(t, act('proto_comment_resolve', id, { resolved: what === 'resolve' ? 1 : 0 })); return; }
            if (what === 'delete') { S.deleting = id; S.answering = 0; drawPanel(); return; }
            if (what === 'delete-no') { S.deleting = 0; drawPanel(); return; }
            if (what === 'delete-yes') { S.deleting = 0; busy(t, act('proto_comment_delete', id)); return; }
        });

        S.panel.addEventListener('submit', function (e) {
            var form = e.target.closest ? e.target.closest('form[data-pc-answer]') : null;
            if (!form) return;
            e.preventDefault();
            var id = parseInt(form.getAttribute('data-pc-answer'), 10) || 0;
            var text = form.querySelector('textarea').value;
            if (!text.trim()) return;
            busy(form.querySelector('button[type="submit"]'), act('proto_comment_answer', id, { text: text }).then(function (ok) {
                if (ok) S.answering = 0;
                drawPanel();
            }));
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && S.panel && !S.panel.hidden && S.panel.contains(document.activeElement)) toggle(false);
        });
    }

    // A button stays as it is labelled while its request runs.
    function busy(btn, promise) {
        if (btn) btn.disabled = true;
        promise.then(function () { if (btn) btn.disabled = false; });
    }

    function focusAnswer(id) {
        if (!S.answering) return;
        var box = S.panel.querySelector('form[data-pc-answer="' + id + '"] textarea');
        if (box) box.focus();
    }

    function threadHtml(t, num, pageId) {
        var resolved = t.resolved_at > 0;
        var there = nodeThere(pageId, t.node);
        var head = function (c) {
            return '<div class="sd-pc-meta"><span class="sd-pc-name">' + esc(c.name) + '</span>' +
                (c.staff ? '<span class="badge theme-secondary">' + esc(_sdT('Designer')) + '</span>' : '') +
                '<time class="sd-pc-time">' + esc(when(c.created_at)) + '</time></div>';
        };
        var answers = t.answers.map(function (a) {
            return '<li class="sd-pc-answer">' + head(a) + '<p class="sd-pc-text">' + esc(a.text) + '</p></li>';
        }).join('');
        var actions;
        if (S.deleting === t.id) {
            actions = '<div class="sd-pc-confirm" role="group">' +
                '<span>' + esc(t.answers.length ? _sdT('Delete this comment and its answers?') : _sdT('Delete this comment?')) + '</span>' +
                '<button type="button" class="btn btn-sm btn-solid theme-danger" data-pc="delete-yes" data-id="' + t.id + '">' + esc(_sdT('Delete')) + '</button>' +
                '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pc="delete-no">' + esc(_sdT('Cancel')) + '</button></div>';
        } else {
            actions = '<div class="sd-pc-actions">' +
                (resolved ? '' : '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pc="answer" data-id="' + t.id + '"><span class="bi bi-reply me-1" aria-hidden="true"></span>' + esc(_sdT('Answer')) + '</button>') +
                (resolved
                    ? '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pc="reopen" data-id="' + t.id + '"><span class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></span>' + esc(_sdT('Reopen')) + '</button>'
                    : '<button type="button" class="btn btn-sm btn-text theme-success" data-pc="resolve" data-id="' + t.id + '"><span class="bi bi-check2 me-1" aria-hidden="true"></span>' + esc(_sdT('Resolve')) + '</button>') +
                '<button type="button" class="btn btn-sm btn-text theme-danger ms-auto" data-pc="delete" data-id="' + t.id + '" aria-label="' + esc(_sdT('Delete')) + '" title="' + esc(_sdT('Delete')) + '"><span class="bi bi-trash" aria-hidden="true"></span></button>' +
                '</div>';
        }
        var answerBox = (S.answering === t.id && !resolved)
            ? '<form class="sd-pc-answer-form" data-pc-answer="' + t.id + '">' +
                '<label class="visually-hidden" for="sd-pc-a-' + t.id + '">' + esc(_sdT('Your answer')) + '</label>' +
                '<textarea class="form-control form-control-sm" id="sd-pc-a-' + t.id + '" rows="3" maxlength="1000" placeholder="' + esc(_sdT('Write an answer…')) + '"></textarea>' +
                '<div class="sd-pc-answer-foot"><span class="form-text">' + esc(_sdT('The customer sees it in the prototype, with your name.')) + '</span>' +
                '<button type="submit" class="btn btn-sm btn-solid theme-primary">' + esc(_sdT('Send')) + '</button></div></form>'
            : '';
        return '<li class="sd-pc-thread' + (S.focus === t.id ? ' is-focus' : '') + (resolved ? ' is-resolved' : '') + '" data-thread="' + t.id + '">' +
            '<button type="button" class="sd-pc-num" data-pc="go" data-id="' + t.id + '" title="' + esc(_sdT('Show on the canvas')) + '">' + num + '</button>' +
            '<div class="sd-pc-body">' +
                head(t) +
                (there === false ? '<div class="sd-pc-gone"><span class="bi bi-exclamation-triangle me-1" aria-hidden="true"></span>' + esc(_sdT('Not on the page any more')) + '</div>' : '') +
                '<p class="sd-pc-text">' + esc(t.text) + '</p>' +
                (answers ? '<ul class="sd-pc-answers">' + answers + '</ul>' : '') +
                (resolved ? '<div class="sd-pc-resolved"><span class="bi bi-check2-circle me-1" aria-hidden="true"></span>' + esc(_sdT('Resolved {var}', when(t.resolved_at))) + '</div>' : '') +
                actions + answerBox +
            '</div></li>';
    }

    function drawPanel() {
        if (!S.panel || S.panel.hidden) return;
        var open = 0, resolved = 0;
        S.pages.forEach(function (p) { open += p.open; resolved += p.resolved; });
        var wantResolved = S.filter === 'resolved';
        var groups = S.pages.map(function (p) {
            var nums = numbers(p);
            var list = p.threads.filter(function (t) { return (t.resolved_at > 0) === wantResolved; });
            if (!list.length) return '';
            var tab = pageById(p.page_id);
            return '<section class="sd-pc-page">' +
                '<h3 class="sd-pc-page-name">' + (p.name !== '' ? esc(p.name) : esc(_sdT('A deleted page'))) +
                    (tab ? '' : '<span class="sd-pc-page-note">' + esc(_sdT('not open as a tab')) + '</span>') + '</h3>' +
                '<ol class="sd-pc-threads">' + list.map(function (t) { return threadHtml(t, nums[t.id], p.page_id); }).join('') + '</ol>' +
            '</section>';
        }).join('');
        var scroll = S.panel.querySelector('.sd-pc-list');
        var top = scroll ? scroll.scrollTop : 0;
        S.panel.innerHTML =
            '<header class="sd-pc-head"><span class="bi bi-chat-square-text" aria-hidden="true"></span>' +
                '<h2 class="sd-pc-title">' + esc(_sdT('Prototype comments')) + '</h2>' +
                '<button type="button" class="sd-icon-btn" data-pc="close" aria-label="' + esc(_sdT('Close')) + '" title="' + esc(_sdT('Close')) + '"><span class="bi bi-x-lg" aria-hidden="true"></span></button></header>' +
            '<div class="sd-pc-tabs" role="tablist">' +
                '<button type="button" role="tab" class="sd-pc-tab' + (wantResolved ? '' : ' is-active') + '" aria-selected="' + (!wantResolved) + '" data-pc="tab" data-tab="open">' + esc(_sdT('Open')) + ' <span class="sd-pc-tab-count">' + open + '</span></button>' +
                '<button type="button" role="tab" class="sd-pc-tab' + (wantResolved ? ' is-active' : '') + '" aria-selected="' + wantResolved + '" data-pc="tab" data-tab="resolved">' + esc(_sdT('Resolved')) + ' <span class="sd-pc-tab-count">' + resolved + '</span></button>' +
            '</div>' +
            '<div class="sd-pc-list">' + (groups || '<p class="sd-pc-empty">' + esc(wantResolved ? _sdT('No resolved comments yet.') : _sdT('No open comments. Comments written on a share link of this design are listed here.')) + '</p>') + '</div>';
        var list = S.panel.querySelector('.sd-pc-list');
        if (list) list.scrollTop = top;
        focusAnswer(S.answering);
    }

    function toggle(show) {
        buildPanel();
        var on = (show === undefined) ? S.panel.hidden : !!show;
        S.panel.hidden = !on;
        document.body.classList.toggle('sd-pc-on', on);
        if (S.btn) S.btn.setAttribute('aria-expanded', on ? 'true' : 'false');
        if (on) {
            S.seen = S.stamp;
            drawPanel();
            drawButton();
        }
        place();
    }

    // A thread asked for from the list: its page becomes the open tab, the
    // canvas scrolls to its node and the pin is marked.
    function goTo(id) {
        var found = null;
        S.pages.forEach(function (p) { p.threads.forEach(function (t) { if (t.id === id) found = { page: p, thread: t }; }); });
        if (!found) return;
        S.focus = id;
        var tab = pageById(found.page.page_id);
        var act = activePage();
        if (tab && (!act || act.key !== tab.key)) K._pgTabsSwitch(tab.key);
        drawPanel();
        setTimeout(function () {
            var el = canvasEl(found.thread.node);
            if (el && el.scrollIntoView) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            place();
        }, 60);
    }

    // ── The pins ──────────────────────────────────────────────────────────

    function bind() {
        var doc = K.canvasDoc;
        if (doc === S.doc) return;
        if (S.observer) { S.observer.disconnect(); S.observer = null; }
        S.doc = doc;
        if (!doc || !doc.body) return;
        // The canvas is drawn again on every structural edit and tab switch:
        // the pins follow on the next frame.
        S.observer = new MutationObserver(function () { place(); });
        S.observer.observe(doc.body, { childList: true, subtree: true });
        var win = doc.defaultView;
        if (win) {
            win.addEventListener('scroll', place, { passive: true });
            win.addEventListener('resize', place);
        }
        var frame = win ? win.frameElement : null;
        if (frame && window.ResizeObserver) {
            if (S.sizer) S.sizer.disconnect();
            S.sizer = new ResizeObserver(function () { place(); });
            S.sizer.observe(frame);
        }
    }

    function place() {
        if (S.raf) return;
        S.raf = requestAnimationFrame(function () {
            S.raf = 0;
            drawPins();
        });
    }

    function drawPins() {
        bind();
        var doc = S.doc;
        var frame = (doc && doc.defaultView) ? doc.defaultView.frameElement : null;
        var page = activePage();
        var data = null;
        if (page) S.pages.forEach(function (p) { if (p.page_id === parseInt(page.page_id, 10)) data = p; });
        if (!frame || !data) { if (S.layer) S.layer.hidden = true; return; }
        if (!S.layer) {
            S.layer = document.createElement('div');
            S.layer.className = 'sd-pc-layer';
            S.layer.addEventListener('click', function (e) {
                var pin = e.target.closest ? e.target.closest('[data-pin]') : null;
                if (!pin) return;
                var id = parseInt(pin.getAttribute('data-pin'), 10) || 0;
                S.focus = id;
                S.filter = pin.classList.contains('is-resolved') ? 'resolved' : 'open';
                toggle(true);
                var row = S.panel.querySelector('[data-thread="' + id + '"]');
                if (row && row.scrollIntoView) row.scrollIntoView({ block: 'nearest' });
                drawPins();
            });
            document.body.appendChild(S.layer);
        }
        var fr = frame.getBoundingClientRect();
        S.layer.hidden = false;
        S.layer.style.left = fr.left + 'px';
        S.layer.style.top = fr.top + 'px';
        S.layer.style.width = fr.width + 'px';
        S.layer.style.height = fr.height + 'px';
        // getBoundingClientRect() inside the frame answers in the frame's
        // CSS pixels; a canvas zoomed with zoom on <html> is drawn scaled.
        var zoom = parseFloat(doc.documentElement.style.zoom) || 1;
        var scale = (frame.clientWidth > 0 && doc.documentElement.clientWidth > 0 && Math.abs(doc.documentElement.clientWidth * zoom - frame.clientWidth) < 2) ? zoom : 1;
        var nums = numbers(data);
        var showResolved = !!(S.panel && !S.panel.hidden && S.filter === 'resolved');
        var html = '';
        data.threads.forEach(function (t) {
            var resolved = t.resolved_at > 0;
            if (resolved !== showResolved) return;
            var el = canvasEl(t.node);
            if (!el) return;
            var r = el.getBoundingClientRect();
            if (!r.width && !r.height) return;
            var x = (r.left + r.width * t.x / 100) * scale;
            var y = (r.top + r.height * t.y / 100) * scale;
            if (x < -8 || y < -8 || x > fr.width + 8 || y > fr.height + 8) return;
            var title = t.name + ': ' + t.text;
            html += '<button type="button" class="sd-pc-pin' + (resolved ? ' is-resolved' : '') + (S.focus === t.id ? ' is-focus' : '') + '" data-pin="' + t.id + '"' +
                ' style="left:' + Math.round(x) + 'px;top:' + Math.round(y) + 'px"' +
                ' title="' + esc(title.length > 140 ? title.slice(0, 139) + '…' : title) + '"' +
                ' aria-label="' + esc(_sdT('Comment {var}', String(nums[t.id]))) + '">' + nums[t.id] + '</button>';
        });
        if (S.layer.innerHTML !== html) S.layer.innerHTML = html;
    }

    function drawAll() {
        drawButton();
        drawPanel();
        place();
    }

    // ── Entry points ──────────────────────────────────────────────────────

    var started = false;

    // The presence heartbeat's answer: the newest comment's id of the design.
    // The list is read again only when it moved.
    function beat(stamp) {
        stamp = parseInt(stamp, 10) || 0;
        if (!started) {
            started = true;
            S.seen = stamp;
            window.addEventListener('resize', place);
            // The bell line opens the design with the list (…&comments=1).
            var askOpen = /[?&]comments=1(?:&|$)/.test(window.location.search);
            return fetchList().then(function () {
                drawButton();
                if (askOpen) toggle(true);
            });
        }
        if (stamp === S.stamp) {
            place();
            return Promise.resolve();
        }
        return fetchList().then(function () {
            if (S.panel && !S.panel.hidden) S.seen = S.stamp;
            drawButton();
        });
    }

    return { beat: beat, open: function () { toggle(true); } };
});
