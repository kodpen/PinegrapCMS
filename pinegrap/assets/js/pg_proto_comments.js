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
 * - On a link asking approval, "Review this page" opens a box in the comment
 *   box's place: Approve this page or Request changes, a note (needed to
 *   request changes) and the name, sent to the same door with a verdict.
 *   The bar then names the verdict that stands and its day.
 * - Round two: when the draft moved since that verdict, "Show what changed"
 *   has the frame outline the nodes changed or added since (their ids come
 *   with the viewer) and lists in the bar, under "Not on the page any
 *   more", the threads left on removed nodes; pins and their states stay.
 *
 * place(), pinId(), missing(), threads(), unsent(), reviewLine(), changes()
 * and removedThreads() are pure (tests/prototype_comment_mode_test.php,
 * prototype_approval_round_test.php); init() runs the mode in a browser.
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

    // Whether a box's field holds text the guest typed and did not send: the
    // box is then not replaced by another click.
    function unsent(value) {
        return typeof value === 'string' && value.trim() !== '';
    }

    // The bar's line for the verdict that stands on the page drawn: '' for
    // none, "Changed since your last review" when the draft moved since,
    // else the verdict with its day ({date}).
    function reviewLine(verdict, strings, day) {
        if (!verdict || typeof verdict !== 'object' || !strings) return '';
        if (verdict.older) return String(strings.changed || '');
        if (verdict.verdict === 'approved') return String(strings.approved || '').replace('{date}', day);
        if (verdict.verdict === 'changes_requested') return String(strings.requested || '').replace('{date}', day);
        return '';
    }

    // Node ids of the node shape, once each, at most 500.
    function nodeIds(value) {
        var seen = {}, out = [];
        (Array.isArray(value) ? value : []).forEach(function (id) {
            if (typeof id === 'string' && NODE.test(id) && !seen[id] && out.length < 500) {
                seen[id] = true;
                out.push(id);
            }
        });
        return out;
    }

    // What "Show what changed" draws, as the viewer takes it from the
    // server: the nodes to outline and the nodes removed; null for none.
    function changes(data) {
        if (!data || typeof data !== 'object') return null;
        return { marks: nodeIds(data.marks), removed: nodeIds(data.removed) };
    }

    // The threads left on nodes removed since the verdict, in their order.
    function removedThreads(removed, list) {
        var gone = {};
        (Array.isArray(removed) ? removed : []).forEach(function (id) { gone[id] = true; });
        return (Array.isArray(list) ? list : []).filter(function (t) { return t && Object.prototype.hasOwnProperty.call(gone, t.node); });
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
        // "Show what changed" while on: { marks, removed } (changes()).
        var shown = null;

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
            frame.contentWindow.postMessage({ pgPrototype: 'comment', on: on, pins: pins, draft: draft, active: (open && open.thread) ? open.thread : 0, marks: shown ? shown.marks : [] }, '*');
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
            bar.classList.toggle('d-none', !on && !shown);
            if (!on && !shown) return;
            if (on) bar.appendChild(el('span', 'pg-proto-cbar-hint', S.hint));
            if (shown) bar.appendChild(el('span', 'pg-proto-cbar-hint pg-proto-cbar-changes', S.changesHint));
            // The lost threads; while "Show what changed" is on, also the
            // threads on removed nodes, and the count is of removed nodes.
            var lost = on ? gone.slice() : [];
            if (shown) {
                removedThreads(shown.removed, list).forEach(function (t) {
                    if (lost.indexOf(t.id) === -1) lost.push(t.id);
                });
            }
            var count = shown ? Math.max(shown.removed.length, lost.length) : lost.length;
            if (!count) return;
            // The title, then one row of the lost threads that scrolls
            // sideways: never more than one row, however many there are.
            var wrap = el('div', 'pg-proto-gone');
            wrap.appendChild(el('span', 'pg-proto-gone-title fw-semibold', S.gone + ' (' + count + ')'));
            var row = el('div', 'pg-proto-gone-row');
            wrap.appendChild(row);
            lost.forEach(function (id) {
                var t = known[id];
                var first = t.items[0];
                var b = el('button', 'btn btn-sm btn-outline theme-secondary pg-proto-gone-item');
                b.type = 'button';
                b.setAttribute('data-pc-thread', String(id));
                b.appendChild(el('span', 'badge ' + (t.resolved ? 'theme-secondary' : 'theme-primary') + ' me-1', String(t.n)));
                var text = String(first.text || '');
                b.appendChild(el('span', 'pg-proto-gone-text', (text.length > 40) ? text.slice(0, 40) + '…' : text));
                row.appendChild(b);
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
            // Never taller than the frame's part of the window: the entries
            // scroll inside and the form stays in reach. The phone's sheet
            // keeps its own height (CSS).
            if (window.matchMedia && !window.matchMedia('(max-width: 575.98px)').matches) {
                box.style.maxHeight = Math.max(200, window.innerHeight - Math.max(0, r.top) - 16) + 'px';
            }
            var w = box.offsetWidth, h = box.offsetHeight;
            var left = r.left + vx + 40, top = r.top + vy - 40;
            if (left + w > window.innerWidth - 8) left = r.left + vx - w - 12;
            left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
            top = Math.max(r.top + 8, Math.min(top, window.innerHeight - h - 8));
            box.style.left = left + 'px';
            box.style.top = top + 'px';
        }

        // A field with its label and the line its refusal is written in.
        function field(id, label, control) {
            var wrap = el('div', 'mb-2');
            var l = el('label', 'form-label small mb-1', label);
            l.setAttribute('for', id);
            control.id = id;
            var note = el('div', 'fg-danger small mt-1 d-none');
            note.id = id + '-note';
            wrap.appendChild(l);
            wrap.appendChild(control);
            wrap.appendChild(note);
            return { wrap: wrap, note: note };
        }

        function refuse(control, note, message) {
            note.textContent = message;
            note.classList.remove('d-none');
            control.classList.add('is-invalid');
            control.setAttribute('aria-invalid', 'true');
            control.setAttribute('aria-describedby', note.id);
        }

        function clear(control, note) {
            note.classList.add('d-none');
            control.classList.remove('is-invalid');
            control.removeAttribute('aria-invalid');
            control.removeAttribute('aria-describedby');
        }

        function item(c) {
            var li = el('li', 'pg-proto-item');
            var head = el('div', 'pg-proto-item-head');
            var who = el('span', 'pg-proto-item-name fw-semibold', c.name);
            who.title = String(c.name || '');
            head.appendChild(who);
            if (c.staff) head.appendChild(el('span', 'badge theme-info', S.team));
            head.appendChild(el('span', 'pg-proto-item-time fg-secondary', when(c.created_at)));
            li.appendChild(head);
            li.appendChild(el('div', 'pg-proto-item-text', c.text));
            return li;
        }

        // A form's Send button.
        function sendButton(label) {
            var go = el('button', 'btn btn-sm btn-solid theme-primary');
            go.type = 'submit';
            go.appendChild(el('span', 'bi bi-send me-1'));
            go.firstChild.setAttribute('aria-hidden', 'true');
            go.appendChild(el('span', 'pg-btn-label', label));
            return go;
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
            var go = sendButton(parent ? S.answerSend : S.send);
            var nameField = field('pg-pc-name', S.name, name);
            var textField = field('pg-pc-text', parent ? S.answer : S.comment, text);
            f.appendChild(nameField.wrap);
            f.appendChild(textField.wrap);
            f.appendChild(error);
            var foot = el('div', 'd-flex justify-content-end');
            foot.appendChild(go);
            f.appendChild(foot);

            // A refusal makes the box taller: it is placed again so it stays
            // inside the window.
            function again() {
                if (box && open) position(open.vx, open.vy);
            }

            function fail(message) {
                error.textContent = message;
                error.classList.remove('d-none');
                again();
            }

            f.addEventListener('submit', function (e) {
                e.preventDefault();
                if (sending) return;
                var n = name.value.trim(), t = text.value.trim();
                clear(name, nameField.note);
                clear(text, textField.note);
                error.classList.add('d-none');
                if (!n) refuse(name, nameField.note, S.nameNeeded);
                if (!t) refuse(text, textField.note, S.textNeeded);
                if (!n || !t) { again(); (n ? text : name).focus(); return; }
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
            // The entries scroll; the note or the answer form under them
            // stays in reach however long the thread is.
            var ul = el('ul', 'pg-proto-items');
            t.items.forEach(function (c) { ul.appendChild(item(c)); });
            body.appendChild(ul);
            if (t.resolved) {
                var done = el('div', 'pg-proto-box-foot pg-proto-resolved small');
                done.appendChild(el('span', 'bi bi-check-circle-fill me-1'));
                done.firstChild.setAttribute('aria-hidden', 'true');
                done.appendChild(document.createTextNode(S.resolved));
                body.appendChild(done);
            } else {
                var foot = form(id, null).form;
                foot.classList.add('pg-proto-box-foot');
                body.appendChild(foot);
            }
            if (gone.indexOf(id) !== -1) {
                var b = bar.getBoundingClientRect(), r = frame.getBoundingClientRect();
                position(Math.max(0, b.left - r.left), 0);
            } else {
                position(vx, vy);
            }
            send();
        }

        // A click elsewhere while the open box holds unsent text keeps the
        // box: the text field takes the focus and says to send or close it
        // first (the close button and Escape still drop it).
        function holding() {
            var t = box ? box.querySelector('textarea') : null;
            if (!t || !unsent(t.value)) return false;
            var alert = box.querySelector('.pg-proto-form [role="alert"]');
            if (alert) {
                alert.textContent = S.held;
                alert.classList.remove('d-none');
                if (open) position(open.vx, open.vy);
            }
            t.focus();
            return true;
        }

        window.addEventListener('message', function (e) {
            if (e.source !== frame.contentWindow || !e.data) return;
            var kind = e.data.pgPrototype;
            if (kind === 'ready') { send(); return; }
            if (!on) return;
            if (kind === 'place') {
                var spot = place(e.data);
                if (spot && !holding()) showNew(spot);
            } else if (kind === 'pin') {
                var id = pinId(e.data, known);
                if (id && !(open && open.thread === id) && !holding()) showThread(id, pixel(e.data.vx), pixel(e.data.vy));
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
            if (b && !holding()) showThread(parseInt(b.getAttribute('data-pc-thread'), 10), 0, 0);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && box) close();
        });

        // The door reads the session this viewer was opened in, which the
        // server drops when it is left idle: a guest reading a page or
        // writing a long comment makes no request, so the viewer pings.
        if (cfg.ping > 0 && window.fetch && window.FormData) {
            window.setInterval(function () {
                var body = new FormData();
                body.append('l', String(cfg.link));
                body.append('page_id', String(cfg.page));
                body.append('token', String(cfg.token));
                body.append('ping', '1');
                fetch(cfg.door, { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
            }, cfg.ping * 1000);
        }

        frame.addEventListener('load', send);
        rebuild();
        paint();
        if (cfg.review) review(cfg.review);

        // "Review this page" on a link asking approval: a box in the comment
        // box's place with the two choices, a note (needed to request
        // changes), the name kept in this browser and Send.
        function review(R) {
            var opener = document.getElementById('pg-proto-review');
            var state = document.getElementById('pg-proto-review-state');
            var RS = R.strings || {};
            if (!opener) return;
            S.changesHint = RS.changesHint;
            var changed = null;

            // "Show what changed" beside the line, only while the verdict
            // that stands was given to an older version of the draft.
            function paintChanges() {
                var marks = (R.verdict && R.verdict.older) ? changes(R.changes) : null;
                if (!marks) {
                    if (changed) changed.remove();
                    changed = null;
                    if (shown) { shown = null; paint(); }
                    return;
                }
                if (!changed) {
                    changed = el('button', opener.className);
                    changed.type = 'button';
                    changed.id = 'pg-proto-changes';
                    changed.appendChild(el('span', 'bi bi-bounding-box-circles'));
                    changed.firstChild.setAttribute('aria-hidden', 'true');
                    changed.appendChild(document.createTextNode(' '));
                    changed.appendChild(el('span', 'pg-proto-label'));
                    opener.parentNode.insertBefore(changed, opener);
                    changed.addEventListener('click', function () {
                        shown = shown ? null : changes(R.changes);
                        paintChanges();
                        paint();
                    });
                }
                var label = shown ? RS.hide : RS.show;
                changed.setAttribute('aria-pressed', shown ? 'true' : 'false');
                changed.classList.toggle('active', !!shown);
                changed.title = label;
                changed.querySelector('.pg-proto-label').textContent = label;
            }

            function day(unix) {
                var d = new Date(unix * 1000);
                try { return d.toLocaleDateString(cfg.lang || undefined, { dateStyle: 'medium' }); } catch (e) { return d.toLocaleDateString(); }
            }

            function paintState() {
                var v = R.verdict;
                var line = reviewLine(v, RS, (v && v.created_at > 0) ? day(v.created_at) : '');
                if (state) {
                    state.textContent = line;
                    state.title = line;
                }
            }

            function show() {
                var r = opener.getBoundingClientRect(), fr = frame.getBoundingClientRect();
                open = { place: null, thread: 0, vx: Math.max(0, r.left - fr.left), vy: 0, review: true };
                var body = frameBox(RS.review + (R.page ? ': ' + R.page : ''));
                var f = el('form', 'pg-proto-form');
                f.noValidate = true;
                var group = el('div', 'pg-proto-choices');
                group.setAttribute('role', 'radiogroup');
                group.setAttribute('aria-label', RS.review);
                var picked = '';
                var choices = [['approved', RS.approve, 'bi-check-lg'], ['changes_requested', RS.changes, 'bi-pencil']];
                var buttons = choices.map(function (c) {
                    // A choice looks as the bar's button that opened the box.
                    var b = el('button', opener.className);
                    b.type = 'button';
                    b.setAttribute('role', 'radio');
                    b.setAttribute('aria-checked', 'false');
                    b.setAttribute('data-verdict', c[0]);
                    b.appendChild(el('span', 'bi ' + c[2] + ' me-1'));
                    b.firstChild.setAttribute('aria-hidden', 'true');
                    b.appendChild(document.createTextNode(c[1]));
                    group.appendChild(b);
                    return b;
                });
                var choiceNote = el('div', 'fg-danger small mb-2 d-none');
                choiceNote.id = 'pg-pr-choice-note';
                var note = el('textarea', 'form-control form-control-sm');
                note.rows = 3;
                note.maxLength = R.noteMax || 1000;
                var name = el('input', 'form-control form-control-sm');
                name.type = 'text';
                name.maxLength = R.nameMax || 60;
                name.autocomplete = 'name';
                name.required = true;
                name.value = rememberedName();
                var noteField = field('pg-pr-note', RS.noteOptional, note);
                var nameField = field('pg-pr-name', S.name, name);
                var error = el('div', 'fg-danger small mb-2 d-none');
                error.setAttribute('role', 'alert');
                var go = sendButton(S.send);
                f.appendChild(group);
                f.appendChild(choiceNote);
                f.appendChild(noteField.wrap);
                f.appendChild(nameField.wrap);
                f.appendChild(error);
                var foot = el('div', 'd-flex justify-content-end');
                foot.appendChild(go);
                f.appendChild(foot);
                body.appendChild(f);

                function again() {
                    if (box && open) position(open.vx, open.vy);
                }

                buttons.forEach(function (b) {
                    b.addEventListener('click', function () {
                        picked = b.getAttribute('data-verdict');
                        buttons.forEach(function (o) {
                            var on = (o === b);
                            o.setAttribute('aria-checked', on ? 'true' : 'false');
                            o.classList.toggle('btn-solid', on);
                            o.classList.toggle('btn-outline', !on);
                        });
                        choiceNote.classList.add('d-none');
                        noteField.wrap.querySelector('label').textContent = (picked === 'changes_requested') ? RS.note : RS.noteOptional;
                        note.required = (picked === 'changes_requested');
                        again();
                    });
                });

                f.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (sending) return;
                    var n = name.value.trim(), t = note.value.trim();
                    clear(name, nameField.note);
                    clear(note, noteField.note);
                    choiceNote.classList.add('d-none');
                    error.classList.add('d-none');
                    if (!picked) { choiceNote.textContent = RS.choose; choiceNote.classList.remove('d-none'); }
                    if (picked === 'changes_requested' && !t) refuse(note, noteField.note, RS.noteNeeded);
                    if (!n) refuse(name, nameField.note, S.nameNeeded);
                    if (!picked || !n || (picked === 'changes_requested' && !t)) { again(); return; }
                    var data = new FormData();
                    data.append('l', String(cfg.link));
                    data.append('page_id', String(cfg.page));
                    data.append('token', String(cfg.token));
                    data.append('verdict', picked);
                    data.append('name', n);
                    data.append('note', t);
                    data.append('seen', String(R.fingerprint || ''));
                    sending = true;
                    go.disabled = true;
                    fetch(cfg.door, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
                        .then(function (res) { return res.json().catch(function () { return {}; }); })
                        .then(function (answer) {
                            sending = false;
                            go.disabled = false;
                            if (!answer || !answer.verdict) {
                                error.textContent = (answer && answer.message) || S.failed;
                                error.classList.remove('d-none');
                                again();
                                return;
                            }
                            keepName(n);
                            R.verdict = answer.verdict;
                            R.changes = null;
                            paintState();
                            paintChanges();
                            close();
                        })
                        .catch(function () {
                            sending = false;
                            go.disabled = false;
                            error.textContent = S.network;
                            error.classList.remove('d-none');
                            again();
                        });
                });

                position(open.vx, open.vy);
                send();
                buttons[0].focus();
            }

            opener.addEventListener('click', function () {
                if (open && open.review) { close(); return; }
                if (!holding()) show();
            });
            paintState();
            paintChanges();
        }
    }

    window.PGProtoComments = { place: place, pinId: pinId, missing: missing, threads: threads, unsent: unsent, reviewLine: reviewLine, changes: changes, removedThreads: removedThreads, init: init };

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
