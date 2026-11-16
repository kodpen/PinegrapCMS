/**
 * Pinegrap - Enterprise Website Platform
 *
 * The comment mode of a prototype opened by its share link
 * (window.PGProtoComments), in the viewer around the frame (prototype.php,
 * pg_proto_shell_html()). Only a link with "Allow comments" loads it.
 *
 * - The Comment button turns the mode on. The frame (pg_proto_frame_inject())
 *   then follows no link: a click there comes back as a place, the nearest
 *   marked node and the click inside it as two percentages. The viewer opens
 *   a box there: Name (kept in this browser) and Comment, and sends them
 *   itself to prototype_comment.php with the link, the page and the form
 *   token it holds. The frame never names a page, a link, a name or a text.
 * - The page's comments come with the viewer (one page of one link); their
 *   threads are numbered pins drawn by the frame over their nodes. A pin
 *   opens its thread: the comments and the answers with names and times,
 *   and an answer box while the thread is open.
 * - A pin whose node the frame does not find on the drawn page is listed
 *   under "Not on the page any more".
 *
 * place(), pinId(), missing() and threads() are pure (tests/
 * prototype_comment_mode_test.php); init() runs the mode in a browser.
 * Served minified as assets/js/pg_proto_comments.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (window) {
    'use strict';

    // The node id shape the door takes (pg_proto_comment_node_shape()).
    var NODE = /^[A-Za-z0-9_-]{1,64}$/;

    function percent(v) {
        return (typeof v === 'number' && isFinite(v) && v >= 0 && v <= 100) ? Math.round(v * 100) / 100 : null;
    }

    function pixel(v) {
        return (typeof v === 'number' && isFinite(v)) ? Math.round(v) : 0;
    }

    // A `place` message from the frame as the viewer takes it: the node and
    // the click inside it, and where in the frame the click was (to set the
    // box beside it). Every other key is dropped; null when it is no place.
    function place(data) {
        if (!data || typeof data !== 'object' || data.pgPrototype !== 'place') return null;
        if (typeof data.node !== 'string' || !NODE.test(data.node)) return null;
        var x = percent(data.x), y = percent(data.y);
        if (x === null || y === null) return null;
        return { node: data.node, x: x, y: y, vx: pixel(data.vx), vy: pixel(data.vy) };
    }

    // A `pin` message: the id of a thread this viewer holds, or null.
    function pinId(data, known) {
        if (!data || typeof data !== 'object' || data.pgPrototype !== 'pin') return null;
        var id = data.id;
        return (typeof id === 'number' && id > 0 && known && Object.prototype.hasOwnProperty.call(known, id)) ? id : null;
    }

    // A `missing` message: the ids of threads this viewer holds whose node is
    // not drawn, once each, in order.
    function missing(data, known) {
        if (!data || typeof data !== 'object' || data.pgPrototype !== 'missing' || !Array.isArray(data.ids)) return [];
        var seen = {}, out = [];
        data.ids.forEach(function (id) {
            if (typeof id === 'number' && id > 0 && known && Object.prototype.hasOwnProperty.call(known, id) && !seen[id]) {
                seen[id] = true;
                out.push(id);
            }
        });
        return out.sort(function (a, b) { return a - b; });
    }

    // The page's comments as threads, numbered in the order they were
    // opened: the first comment, then its answers oldest first. An answer
    // whose first comment is not here is left out.
    function threads(comments) {
        var byId = {}, list = [];
        (Array.isArray(comments) ? comments : []).slice().sort(function (a, b) { return a.id - b.id; }).forEach(function (c) {
            if (!c || !(c.id > 0)) return;
            if (!c.parent) {
                var t = { id: c.id, n: list.length + 1, node: c.node, x: c.x, y: c.y, resolved: c.resolved_at > 0, items: [c] };
                byId[c.id] = t;
                list.push(t);
            } else if (byId[c.parent]) {
                byId[c.parent].items.push(c);
            }
        });
        return list;
    }

    /* ---------------------------------------------------------------------
       The mode in the viewer
       --------------------------------------------------------------------- */

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function init(cfg) {
        var frame = document.getElementById('pg-proto-frame');
        var button = document.getElementById('pg-proto-comment');
        var bar = document.getElementById('pg-proto-cbar');
        if (!frame || !button || !bar || !cfg) return;

        var S = cfg.strings || {};
        var KEY = 'pg-proto-comment-on';
        var NAME_KEY = 'pg-proto-comment-name';
        var comments = Array.isArray(cfg.comments) ? cfg.comments.slice() : [];
        var list = [], known = {}, gone = [];
        var on = false, box = null, open = null, sending = false;

        try { on = window.sessionStorage.getItem(KEY) === '1'; } catch (e) {}

        function rememberedName() {
            try { return window.localStorage.getItem(NAME_KEY) || ''; } catch (e) { return ''; }
        }

        function keepName(name) {
            try { window.localStorage.setItem(NAME_KEY, name); } catch (e) {}
        }

        function when(unix) {
            if (!(unix > 0)) return '';
            var d = new Date(unix * 1000);
            try { return d.toLocaleString(cfg.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return d.toLocaleString(); }
        }

        function rebuild() {
            list = threads(comments);
            known = {};
            list.forEach(function (t) { known[t.id] = t; });
            gone = gone.filter(function (id) { return known[id]; });
        }

        // The pins go to the frame as numbers and places only.
        function send() {
            if (!frame.contentWindow) return;
            var pins = list.map(function (t) { return { id: t.id, n: t.n, node: t.node, x: t.x, y: t.y, done: t.resolved }; });
            var draft = (open && open.place) ? { node: open.place.node, x: open.place.x, y: open.place.y } : null;
            frame.contentWindow.postMessage({ pgPrototype: 'comment', on: on, pins: pins, draft: draft, active: (open && open.thread) ? open.thread : 0 }, '*');
        }

        function openCount() {
            return list.filter(function (t) { return !t.resolved; }).length;
        }

        function paintButton() {
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.classList.toggle('active', on);
            var count = button.querySelector('[data-pc-count]');
            var n = openCount();
            if (count) {
                count.textContent = n ? String(n) : '';
                count.classList.toggle('d-none', !n);
            }
        }

        function paintBar() {
            bar.textContent = '';
            bar.classList.toggle('d-none', !on);
            if (!on) return;
            bar.appendChild(el('span', 'pg-proto-cbar-hint', S.hint));
            if (!gone.length) return;
            var wrap = el('div', 'pg-proto-gone');
            var title = el('span', 'pg-proto-gone-title fw-semibold', S.gone);
            wrap.appendChild(title);
            gone.forEach(function (id) {
                var t = known[id];
                var first = t.items[0];
                var b = el('button', 'btn btn-sm btn-outline theme-secondary pg-proto-gone-item');
                b.type = 'button';
                b.setAttribute('data-pc-thread', String(id));
                b.appendChild(el('span', 'badge ' + (t.resolved ? 'theme-secondary' : 'theme-primary') + ' me-1', String(t.n)));
                var text = String(first.text || '');
                b.appendChild(el('span', 'pg-proto-gone-text', (text.length > 40) ? text.slice(0, 40) + '…' : text));
                wrap.appendChild(b);
            });
            bar.appendChild(wrap);
        }

        function paint() {
            paintButton();
            paintBar();
            send();
        }

        function close() {
            if (box) box.remove();
            box = null;
            open = null;
            send();
        }

        // The box sits beside the spot (vx, vy in the frame), clear of the
        // pin drawn there (28 px wide, its point at the spot) and kept inside
        // the window; on a phone it is a sheet at the bottom (CSS).
        function position(vx, vy) {
            var r = frame.getBoundingClientRect();
            var w = box.offsetWidth, h = box.offsetHeight;
            var left = r.left + vx + 40, top = r.top + vy - 40;
            if (left + w > window.innerWidth - 8) left = r.left + vx - w - 12;
            left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
            top = Math.max(r.top + 8, Math.min(top, window.innerHeight - h - 8));
            box.style.left = left + 'px';
            box.style.top = top + 'px';
        }

        function field(id, label, control) {
            var wrap = el('div', 'mb-2');
            var l = el('label', 'form-label small mb-1', label);
            l.setAttribute('for', id);
            control.id = id;
            wrap.appendChild(l);
            wrap.appendChild(control);
            return wrap;
        }

        function item(c) {
            var li = el('li', 'pg-proto-item');
            var head = el('div', 'pg-proto-item-head');
            head.appendChild(el('span', 'fw-semibold', c.name));
            if (c.staff) head.appendChild(el('span', 'badge theme-info', S.team));
            head.appendChild(el('span', 'pg-proto-item-time fg-secondary', when(c.created_at)));
            li.appendChild(head);
            li.appendChild(el('div', 'pg-proto-item-text', c.text));
            return li;
        }

        // The form: Name and Comment (or Answer), Send. `parent` is the
        // thread answered, 0 for a new comment at `spot`.
        function form(parent, spot) {
            var f = el('form', 'pg-proto-form');
            f.noValidate = true;
            var name = el('input', 'form-control form-control-sm');
            name.type = 'text';
            name.maxLength = cfg.nameMax || 60;
            name.autocomplete = 'name';
            name.required = true;
            name.value = rememberedName();
            var text = el('textarea', 'form-control form-control-sm');
            text.rows = 3;
            text.maxLength = cfg.textMax || 1000;
            text.required = true;
            var error = el('div', 'fg-danger small mb-2 d-none');
            error.setAttribute('role', 'alert');
            var go = el('button', 'btn btn-sm btn-solid theme-primary');
            go.type = 'submit';
            go.appendChild(el('span', 'bi bi-send me-1'));
            go.firstChild.setAttribute('aria-hidden', 'true');
            go.appendChild(el('span', 'pg-btn-label', parent ? S.answerSend : S.send));
            f.appendChild(field('pg-pc-name', S.name, name));
            f.appendChild(field('pg-pc-text', parent ? S.answer : S.comment, text));
            f.appendChild(error);
            var foot = el('div', 'd-flex justify-content-end');
            foot.appendChild(go);
            f.appendChild(foot);

            function fail(message) {
                error.textContent = message;
                error.classList.remove('d-none');
            }

            f.addEventListener('submit', function (e) {
                e.preventDefault();
                if (sending) return;
                var n = name.value.trim(), t = text.value.trim();
                if (!n) { fail(S.nameNeeded); name.focus(); return; }
                if (!t) { fail(S.textNeeded); text.focus(); return; }
                error.classList.add('d-none');
                var body = new FormData();
                body.append('l', String(cfg.link));
                body.append('page_id', String(cfg.page));
                body.append('token', String(cfg.token));
                body.append('name', n);
                body.append('text', t);
                if (parent) {
                    body.append('parent', String(parent));
                } else {
                    body.append('node', spot.node);
                    body.append('x', String(spot.x));
                    body.append('y', String(spot.y));
                }
                sending = true;
                go.disabled = true;
                fetch(cfg.door, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
                    .then(function (res) { return res.json().catch(function () { return {}; }); })
                    .then(function (data) {
                        sending = false;
                        go.disabled = false;
                        if (!data || !data.comment) { fail((data && data.message) || S.failed); return; }
                        keepName(n);
                        comments.push(data.comment);
                        rebuild();
                        var thread = parent || data.comment.id;
                        var at = open ? { vx: open.vx, vy: open.vy } : { vx: 0, vy: 0 };
                        paint();
                        showThread(thread, at.vx, at.vy);
                    })
                    .catch(function () {
                        sending = false;
                        go.disabled = false;
                        fail(S.network);
                    });
            });
            return { form: f, first: name.value ? text : name };
        }

        function frameBox(title) {
            if (box) box.remove();
            box = el('div', 'pg-proto-box');
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-label', title);
            var head = el('div', 'pg-proto-box-head');
            head.appendChild(el('span', 'fw-semibold', title));
            var x = el('button', 'btn-close');
            x.type = 'button';
            x.setAttribute('aria-label', S.close);
            x.addEventListener('click', close);
            head.appendChild(x);
            box.appendChild(head);
            var body = el('div', 'pg-proto-box-body');
            box.appendChild(body);
            document.body.appendChild(box);
            return body;
        }

        function showNew(spot) {
            open = { place: spot, thread: 0, vx: spot.vx, vy: spot.vy };
            var body = frameBox(S.newComment);
            var f = form(0, spot);
            body.appendChild(f.form);
            position(spot.vx, spot.vy);
            send();
            f.first.focus();
        }

        function showThread(id, vx, vy) {
            var t = known[id];
            if (!t) return;
            open = { place: null, thread: id, vx: vx, vy: vy };
            var body = frameBox(S.thread.replace('{n}', String(t.n)));
            if (gone.indexOf(id) !== -1) body.appendChild(el('div', 'pg-proto-box-note fg-secondary small', S.goneNote));
            var ul = el('ul', 'pg-proto-items');
            t.items.forEach(function (c) { ul.appendChild(item(c)); });
            body.appendChild(ul);
            if (t.resolved) {
                body.appendChild(el('div', 'pg-proto-box-note fg-secondary small', S.resolved));
            } else {
                body.appendChild(form(id, null).form);
            }
            if (gone.indexOf(id) !== -1) {
                var b = bar.getBoundingClientRect(), r = frame.getBoundingClientRect();
                position(Math.max(0, b.left - r.left), 0);
            } else {
                position(vx, vy);
            }
            send();
        }

        window.addEventListener('message', function (e) {
            if (e.source !== frame.contentWindow || !e.data) return;
            var kind = e.data.pgPrototype;
            if (kind === 'ready') { send(); return; }
            if (!on) return;
            if (kind === 'place') {
                var spot = place(e.data);
                if (spot) showNew(spot);
            } else if (kind === 'pin') {
                var id = pinId(e.data, known);
                if (id) showThread(id, pixel(e.data.vx), pixel(e.data.vy));
            } else if (kind === 'missing') {
                gone = missing(e.data, known);
                paintBar();
            }
        });

        // "missing" is sent whatever the mode: the list waits for it.
        window.addEventListener('message', function (e) {
            if (on || e.source !== frame.contentWindow || !e.data || e.data.pgPrototype !== 'missing') return;
            gone = missing(e.data, known);
        });

        button.addEventListener('click', function () {
            on = !on;
            try { window.sessionStorage.setItem(KEY, on ? '1' : '0'); } catch (e) {}
            if (!on) close();
            paint();
        });

        bar.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-pc-thread]') : null;
            if (b) showThread(parseInt(b.getAttribute('data-pc-thread'), 10), 0, 0);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && box) close();
        });

        frame.addEventListener('load', send);
        rebuild();
        paint();
    }

    window.PGProtoComments = { place: place, pinId: pinId, missing: missing, threads: threads, init: init };

    // The viewer prints its settings as JSON beside the script.
    if (typeof document !== 'undefined') {
        var start = function () {
            var data = document.getElementById('pg-proto-comment-data');
            if (!data) return;
            try { init(JSON.parse(data.textContent || '{}')); } catch (e) {}
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
        else start();
    }
})(window);
