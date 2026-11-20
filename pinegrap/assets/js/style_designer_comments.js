/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the comments part: what the customers wrote on the
 * design's prototype through its share links (prototype_comments). The pins
 * of the open tab's page, numbered, over the canvas at the place each
 * comment names inside its node, and the Comments list by page with its open
 * and resolved threads: Answer, Resolve and Reopen, Make a task (with the
 * Workspace on), Delete. A thread whose node is no longer on its page stays
 * in the list and says so. The list is the Comments tab of the Prototype
 * view's side panel; its button sits in the canvas bar's Prototype mode
 * (the core draws both, _sdProtoCommentsMark()).
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
        panel: null,        // the Prototype view's Comments tab, once the view is built
        seen_tab: false,    // whether that tab is in sight
        view: false,        // whether the Prototype view is open (the pins hide)
        layer: null,        // the pins, over the canvas frame
        doc: null,          // the canvas document the pins are bound to
        observer: null,
        sizer: null,
        raf: 0,
        fetching: null,
        focus: 0,           // the thread the list shows marked
        answering: 0,       // the thread whose answer box is open
        deleting: 0,        // the thread whose delete asks to be confirmed
        task: false,        // whether "Make a task" is offered (the Workspace on, a member)
        made: {}            // thread id => the task made of it while the editor is open
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
        // Only the list's own answer says it; an action's answer keeps it.
        if (typeof res.task === 'boolean') S.task = res.task;
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

    // The Workspace task a thread became, in a new browser tab so the
    // editor's unsaved work stays open.
    function taskLink(taskId, cls) {
        return '<a class="' + cls + '" href="workspace_tasks.php?task=' + encodeURIComponent(String(taskId)) + '" target="_blank" rel="noopener">' + esc(_sdT('Open task')) + '</a>';
    }

    // "Make a task": the designer's own task with the page tagged and the
    // comment quoted. Every press opens one of its own; once made, the thread
    // offers the task instead of a second press.
    function makeTask(threadId) {
        return _pgApiPost('proto_comment_task', { style_id: styleId(), comment_id: threadId }).then(function (res) {
            var taskId = (res && res.status === 'success') ? (parseInt(res.task_id, 10) || 0) : 0;
            if (!taskId) {
                sdToast(esc((res && res.message) || _sdT('Sorry, we could not accept your request.')), 'error');
                return false;
            }
            S.made[threadId] = taskId;
            drawPanel();
            sdToast(esc(_sdT('A task was made for this comment.')) + ' ' + taskLink(taskId, 'alert-link'), 'success');
            return true;
        }, function () {
            sdToast(esc(_sdT('Sorry, we could not accept your request.')), 'error');
            return false;
        });
    }

    // ── The threads ───────────────────────────────────────────────────────

    // A thread's number is the one the customer sees on its pin: the server
    // numbers a page's threads per link, by the first comment, oldest first,
    // open and resolved alike (pg_proto_comment_list_pages()).
    function num(t) {
        return parseInt(t.n, 10) || 0;
    }

    // The link a thread came through, named as the Share prototype dialog
    // lists it; told only where a page has comments of more than one link,
    // as the numbers then repeat.
    function linkTag(t, page) {
        if (!page || !(page.links > 1) || !t.link_hint) return '';
        return '<code class="sd-pc-link" title="' + esc(_sdT('Share link')) + '">' + esc(t.link_hint) + '…</code>';
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

    // ── The canvas bar's button and the tab's count ──────────────────────

    // The core draws the Prototype mode's Comments button, its badge and
    // the tab's count from these two facts.
    function mark() {
        K._sdProtoCommentsMark(openCount(), S.stamp > S.seen);
    }

    // ── The list ──────────────────────────────────────────────────────────

    // The list lives in the Prototype view's Comments tab: bound once the
    // view has built it.
    function buildPanel() {
        if (S.panel) return true;
        var pane = K._sdProtoCommentsPane();
        if (!pane) return false;
        S.panel = pane;
        S.panel.setAttribute('data-bs-theme', 'dark');

        S.panel.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target.closest('[data-pc]') : null;
            if (!t) {
                // A thread's row selects its page's frame on the map, as a
                // links panel row does; its controls keep their own clicks.
                var row = (e.target.closest && !e.target.closest('button, a, form')) ? e.target.closest('[data-thread]') : null;
                if (row) frameOf(parseInt(row.getAttribute('data-thread'), 10) || 0);
                return;
            }
            var what = t.getAttribute('data-pc');
            var id = parseInt(t.getAttribute('data-id'), 10) || 0;
            if (what === 'tab') { S.filter = t.getAttribute('data-tab') === 'resolved' ? 'resolved' : 'open'; drawPanel(); place(); return; }
            if (what === 'frame') { frameOf(id); return; }
            if (what === 'go') { goTo(id); return; }
            if (what === 'answer') { S.answering = (S.answering === id) ? 0 : id; S.deleting = 0; drawPanel(); focusAnswer(id); return; }
            if (what === 'resolve' || what === 'reopen') { busy(t, act('proto_comment_resolve', id, { resolved: what === 'resolve' ? 1 : 0 })); return; }
            if (what === 'task') { busy(t, makeTask(id)); return; }
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
        return true;
    }

    function threadOf(id) {
        var found = null;
        S.pages.forEach(function (p) { p.threads.forEach(function (t) { if (t.id === id) found = { page: p, thread: t }; }); });
        return found;
    }

    // A thread's page selected on the map (the core's _sdProtoCommentsFrame()).
    function frameOf(id) {
        var found = threadOf(id);
        if (!found) return;
        if (S.focus !== id) {
            S.focus = id;
            drawPanel();
        }
        K._sdProtoCommentsFrame(parseInt(found.page.page_id, 10) || 0);
    }

    // A button stays as it is labelled while its request runs.
    function busy(btn, promise) {
        if (btn) btn.disabled = true;
        promise.then(function () { if (btn) btn.disabled = false; });
    }

    function focusAnswer(id) {
        if (!S.answering) return;
        var box = S.panel ? S.panel.querySelector('form[data-pc-answer="' + id + '"] textarea') : null;
        if (box) box.focus();
    }

    function threadHtml(t, page) {
        var resolved = t.resolved_at > 0;
        var there = nodeThere(page.page_id, t.node);
        // The links panel's own "Open in the editor" icon.
        var openIcon = '<button type="button" class="sd-proto-li-act sd-pc-open" data-pc="go" data-id="' + t.id + '" title="' + esc(_sdT('Open in the editor')) + '" aria-label="' + esc(_sdT('Open in the editor')) + '"><span class="bi bi-box-arrow-in-up-left" aria-hidden="true"></span></button>';
        var head = function (c, tag) {
            return '<div class="sd-pc-meta"><span class="sd-pc-name">' + esc(c.name) + '</span>' +
                // A staff answer carries the badge the customer sees on it.
                (c.staff ? '<span class="badge theme-secondary">' + esc(_sdT('Team')) + '</span>' : '') + (tag || '') +
                '<time class="sd-pc-time">' + esc(when(c.created_at)) + '</time>' + (c === t ? openIcon : '') + '</div>';
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
                // "Make a task" only with the Workspace on, for a member of
                // its team, on an open thread whose page is still there.
                ((S.task && !resolved && page.name !== '')
                    ? (S.made[t.id]
                        ? taskLink(S.made[t.id], 'btn btn-sm btn-text theme-secondary')
                        : '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pc="task" data-id="' + t.id + '"><span class="bi bi-check2-square me-1" aria-hidden="true"></span>' + esc(_sdT('Make a task')) + '</button>')
                    : '') +
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
            '<button type="button" class="sd-pc-num" data-pc="frame" data-id="' + t.id + '" title="' + esc(_sdT('Show its page on the map')) + '" aria-label="' + esc(_sdT('Comment {var}', String(num(t)))) + '">' + num(t) + '</button>' +
            '<div class="sd-pc-body">' +
                head(t, linkTag(t, page)) +
                (there === false ? '<div class="sd-pc-gone"><span class="bi bi-exclamation-triangle me-1" aria-hidden="true"></span>' + esc(_sdT('Not on the page any more')) + '</div>' : '') +
                '<p class="sd-pc-text">' + esc(t.text) + '</p>' +
                (answers ? '<ul class="sd-pc-answers">' + answers + '</ul>' : '') +
                (resolved ? '<div class="sd-pc-resolved"><span class="bi bi-check2-circle me-1" aria-hidden="true"></span>' + esc(_sdT('Resolved {var}', when(t.resolved_at))) + '</div>' : '') +
                actions + answerBox +
            '</div></li>';
    }

    function drawPanel() {
        if (!S.panel || !S.seen_tab) return;
        var open = 0, resolved = 0;
        S.pages.forEach(function (p) { open += p.open; resolved += p.resolved; });
        var wantResolved = S.filter === 'resolved';
        var groups = S.pages.map(function (p) {
            var list = p.threads.filter(function (t) { return (t.resolved_at > 0) === wantResolved; });
            if (!list.length) return '';
            var tab = pageById(p.page_id);
            return '<section class="sd-pc-page">' +
                '<h3 class="sd-pc-page-name">' + (p.name !== '' ? esc(p.name) : esc(_sdT('A deleted page'))) +
                    (tab ? '' : '<span class="sd-pc-page-note">' + esc(_sdT('not open as a tab')) + '</span>') + '</h3>' +
                '<ol class="sd-pc-threads">' + list.map(function (t) { return threadHtml(t, p); }).join('') + '</ol>' +
            '</section>';
        }).join('');
        var scroll = S.panel.querySelector('.sd-pc-list');
        var top = scroll ? scroll.scrollTop : 0;
        S.panel.innerHTML =
            '<div class="sd-pc-tabs" role="tablist" aria-label="' + esc(_sdT('Prototype comments')) + '">' +
                '<button type="button" role="tab" class="sd-pc-tab' + (wantResolved ? '' : ' is-active') + '" aria-selected="' + (!wantResolved) + '" data-pc="tab" data-tab="open">' + esc(_sdT('Open')) + ' <span class="sd-pc-tab-count">' + open + '</span></button>' +
                '<button type="button" role="tab" class="sd-pc-tab' + (wantResolved ? ' is-active' : '') + '" aria-selected="' + wantResolved + '" data-pc="tab" data-tab="resolved">' + esc(_sdT('Resolved')) + ' <span class="sd-pc-tab-count">' + resolved + '</span></button>' +
            '</div>' +
            '<div class="sd-pc-list">' + (groups || '<p class="sd-pc-empty">' + esc(wantResolved ? _sdT('No resolved comments yet.') : _sdT('No open comments. Comments written on a share link of this design are listed here.')) + '</p>') + '</div>';
        var list = S.panel.querySelector('.sd-pc-list');
        if (list) list.scrollTop = top;
        focusAnswer(S.answering);
    }

    // The core says whether the Comments tab is in sight (seen) and whether
    // the Prototype view is open (view): a list in sight counts as read.
    function side(seen, view) {
        S.view = !!view;
        S.seen_tab = !!seen && buildPanel();
        if (S.seen_tab) {
            S.seen = S.stamp;
            drawPanel();
        }
        mark();
        place();
    }

    // The Comments tab opened from outside Prototype mode (a pin, the bell
    // line's address, the part's open()), at a thread when one is given.
    function show(id, resolved) {
        if (id) {
            S.focus = id;
            S.filter = resolved ? 'resolved' : 'open';
        }
        if (!K._sdProtoCommentsOpen()) return;
        side(true, true);
        var row = (id && S.panel) ? S.panel.querySelector('[data-thread="' + id + '"]') : null;
        if (row && row.scrollIntoView) row.scrollIntoView({ block: 'nearest' });
    }

    // "Open in the editor": the view closes, the thread's page becomes the
    // open tab, the canvas scrolls to its node and the pin is marked.
    function goTo(id) {
        var found = threadOf(id);
        if (!found) return;
        S.focus = id;
        K._sdProtoClose();
        var tab = pageById(found.page.page_id);
        var act = activePage();
        if (tab && (!act || act.key !== tab.key)) K._pgTabsSwitch(tab.key);
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
        // The Prototype view covers the canvas: no pin over its map.
        if (!frame || !data || S.view) { if (S.layer) S.layer.hidden = true; return; }
        if (!S.layer) {
            S.layer = document.createElement('div');
            S.layer.className = 'sd-pc-layer';
            S.layer.addEventListener('click', function (e) {
                var pin = e.target.closest ? e.target.closest('[data-pin]') : null;
                if (!pin) return;
                show(parseInt(pin.getAttribute('data-pin'), 10) || 0, pin.classList.contains('is-resolved'));
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
        // The open threads; a resolved one only when it is the thread last
        // opened in the editor from the list.
        var html = '';
        data.threads.forEach(function (t) {
            var resolved = t.resolved_at > 0;
            if (resolved && S.focus !== t.id) return;
            var el = canvasEl(t.node);
            if (!el) return;
            var r = el.getBoundingClientRect();
            if (!r.width && !r.height) return;
            var x = (r.left + r.width * t.x / 100) * scale;
            var y = (r.top + r.height * t.y / 100) * scale;
            // A pin is drawn above its point: one whose point is out of the
            // frame's view is left out rather than cut at the edge.
            if (x < 0 || y < 0 || x > fr.width || y > fr.height) return;
            // Two links' threads on one page can share a number: the title
            // names the link then, as the list does.
            var title = (data.links > 1 && t.link_hint ? t.link_hint + '… · ' : '') + t.name + ': ' + t.text;
            html += '<button type="button" class="sd-pc-pin' + (resolved ? ' is-resolved' : '') + (S.focus === t.id ? ' is-focus' : '') + '" data-pin="' + t.id + '"' +
                ' style="left:' + Math.round(x) + 'px;top:' + Math.round(y) + 'px"' +
                ' title="' + esc(title.length > 140 ? title.slice(0, 139) + '…' : title) + '"' +
                ' aria-label="' + esc(_sdT('Comment {var}', String(num(t)))) + '">' + num(t) + '</button>';
        });
        if (S.layer.innerHTML !== html) S.layer.innerHTML = html;
    }

    function drawAll() {
        mark();
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
                mark();
                if (askOpen) show(0);
            });
        }
        if (stamp === S.stamp) {
            place();
            return Promise.resolve();
        }
        return fetchList().then(function () {
            if (S.seen_tab) S.seen = S.stamp;
            mark();
        });
    }

    // reload(): the list read again whatever the stamp says, after comments
    // went elsewhere (the Share prototype dialog's "Delete comments"); a
    // deletion need not move the newest id.
    return {
        beat: beat,
        side: side,
        open: function () { show(0); },
        reload: function () { return started ? fetchList() : Promise.resolve(); }
    };
});
