/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Activity part: History's "Activity" tab, what
 * happened to the design and who did it, newest first, under their day.
 * Three choosers (person, page, kind) go to the reader with each request
 * (designer/activity_list), so a narrowed list still comes fifty at a time
 * from the whole design. A page's version is a button that opens Compare
 * on the version before it and this one; after the list is drawn the
 * version rows in view get their count of changes, one at a time, from
 * Compare's change reader (window.PgCompareChanges, part `compare`).
 *
 * Not needed to open a page: the core runs this file the first time the
 * tab is shown (openSnapshotsPanel(), pg_designer_parts()). The pure rules
 * are window.PgDesignerActivity (pg_designer_activity.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('activity', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var A = window.PgDesignerActivity;
    // Trees of revisions kept for the counts, by "<page>:<no>"; the oldest
    // goes first once there are more.
    var TREES_KEPT = 24;
    // The rest between two counts, so counting never holds the editor.
    var COUNT_GAP_MS = 60;

    // The tab's state while History is open; reset when History opens anew.
    var s = null;

    function reset(host, done) {
        s = {
            host: host, done: done,
            choice: { person: '', page: 0, kind: '' },
            events: [], more: false, next: '', state: 'loading', failedMore: false,
            loading: false, seq: 0, people: [],
            counts: {}, trees: {}, treeOrder: [], counting: false, countTimer: 0,
            menu: null, scrollTop: 0
        };
    }

    function full() {
        return K._sdAccess() !== 'content';
    }

    function selfId() {
        return parseInt(K.design && K.design.userId, 10) || 0;
    }

    function styleId() {
        return parseInt(K.design && K.design.styleId, 10) || 0;
    }

    function openPageId() {
        var key = K.activeKey;
        var list = K.pages || [];
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].key === key) return parseInt(list[i].page_id, 10) || 0;
        }
        return 0;
    }

    function pageName(id) {
        var list = K.pages || [];
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].page_id === id) return String(list[i].page_name || '');
        }
        return '';
    }

    // ── Words ──
    function kindLabel(kind) {
        switch (kind) {
            case 'version': return _sdT('Page versions');
            case 'page': return _sdT('Pages on and off the site');
            case 'share': return _sdT('Sharing');
            case 'design': return _sdT('The design');
            case 'component': return _sdT('Shared components');
            case 'file': return _sdT('Design files');
            default: return _sdT('Everything');
        }
    }

    function personLabel(person) {
        if (person === 'me') return _sdT('Me');
        if (person === 'customers') return _sdT('Customers');
        if (person === 'schedule') return _sdT('The schedule');
        var id = parseInt(person, 10) || 0;
        for (var i = 0; i < s.people.length; i++) {
            if (s.people[i].id === id) return s.people[i].name;
        }
        return id > 0 ? _sdT('A person') : _sdT('Everyone');
    }

    function pageLabel(id) {
        return id > 0 ? (pageName(id) || _sdT('A page')) : _sdT('Every page');
    }

    function dayHead(d) {
        if (d.code === 'today') return _sdT('Today');
        if (d.code === 'yesterday') return _sdT('Yesterday');
        var o = { weekday: 'long', day: 'numeric', month: 'long' };
        if (d.code === 'year') o.year = 'numeric';
        return new Date(d.ms).toLocaleDateString(K._sdLocale(), o);
    }

    function hour(at) {
        return new Date((Number(at) || 0) * 1000).toLocaleTimeString(K._sdLocale(), { hour: '2-digit', minute: '2-digit' });
    }

    function countText(c) {
        switch (c && c.code) {
            case 'many': return _sdT('{var} changes', c.n);
            case 'one': return _sdT('1 change');
            case 'same': return _sdT('settings only');
            case 'first': return _sdT('first version');
            default: return '';
        }
    }

    function countKey(e) {
        return e.page_id + ':' + e.revision_no;
    }

    // ── Reading ──
    function load(more) {
        if (!s) return;
        var seq = ++s.seq;
        s.loading = true;
        s.failedMore = false;
        if (!more) {
            s.state = 'loading';
            s.events = [];
            s.more = false;
            s.next = '';
            draw();
        } else {
            drawEnd();
        }
        if (styleId() <= 0) {
            s.loading = false;
            s.state = 'ready';
            draw();
            return;
        }
        var p = A.params(s.choice);
        K._pgApiPost('activity_list', {
            style_id: styleId(), before: more ? s.next : '', person: p.person, page: p.page, kind: p.kind
        }).then(function (res) {
            if (!s || seq !== s.seq) return;
            s.loading = false;
            if (!res || res.status !== 'success' || !Array.isArray(res.events)) {
                failed(more);
                return;
            }
            s.events = more ? s.events.concat(res.events) : res.events;
            s.more = !!res.more;
            s.next = String(res.next || '');
            s.state = 'ready';
            s.people = A.people(res.events, selfId(), s.people);
            if (more) {
                appendRows(res.events);
            } else {
                draw();
            }
            countSoon();
        }, function () {
            if (!s || seq !== s.seq) return;
            s.loading = false;
            failed(more);
        });
    }

    function failed(more) {
        if (more) {
            s.failedMore = true;
            drawEnd();
            return;
        }
        s.state = 'failed';
        draw();
    }

    // ── Drawing ──
    function chooserHtml(name, label, on, disabled) {
        return '<button type="button" class="sd-act-chooser' + (on ? ' is-on' : '') + '" data-chooser="' + name + '" aria-haspopup="menu" aria-expanded="false"' +
            (disabled ? ' disabled' : '') + '><span class="sd-act-chooser-lbl">' + esc(label) + '</span><span class="bi bi-chevron-down"></span></button>';
    }

    function choosersHtml(disabled) {
        var c = s.choice;
        return '<div class="sd-act-choosers">' +
            chooserHtml('person', c.person ? personLabel(c.person) : _sdT('Everyone'), !!c.person, disabled) +
            chooserHtml('page', pageLabel(c.page), c.page > 0, disabled) +
            chooserHtml('kind', kindLabel(c.kind), !!c.kind, disabled) +
            '</div>';
    }

    function rowHtml(e, i) {
        var opens = A.opens(e);
        var meta = '<span class="sd-act-hour">' + esc(hour(e.at)) + '</span>';
        if (e.editor) meta += ' · ' + esc(_sdT('change by {var}', e.editor));
        if (A.isVersion(e)) {
            var c = (Number(e.revision_no) === 1) ? { code: 'first' } : s.counts[countKey(e)];
            var t = countText(c);
            meta += '<span class="sd-act-count" data-count="' + esc(countKey(e)) + '">' + (t ? ' · ' + esc(t) : '') + '</span>';
        }
        var inner = '<span class="sd-act-mark bi bi-' + esc(A.mark(e)) + '" aria-hidden="true"></span>' +
            '<span class="sd-act-body"><span class="sd-act-text"><strong>' + esc(e.who || '') + '</strong> ' + esc(e.sentence || '') + '</span>' +
            '<span class="sd-act-meta">' + meta + '</span></span>';
        if (opens) {
            return '<button type="button" class="sd-act-row is-open" data-i="' + i + '" title="' + esc(_sdT('Compare with the version before')) + '">' + inner +
                '<span class="sd-act-cmp bi bi-arrow-left-right" aria-hidden="true"></span></button>';
        }
        return '<div class="sd-act-row" data-i="' + i + '">' + inner + '</div>';
    }

    function rowsHtml(events, from, lastKey) {
        var html = '';
        A.groups(events, Date.now()).forEach(function (g) {
            if (g.key !== lastKey) html += '<div class="sd-act-day" data-day="' + esc(g.key) + '">' + esc(dayHead(g.day)) + '</div>';
            g.events.forEach(function (e) { html += rowHtml(e, from++); });
        });
        return html;
    }

    function endHtml() {
        if (s.failedMore) {
            return '<span>' + esc(_sdT('The earlier activity could not be read.')) + '</span> <button type="button" class="sd-act-link" data-act="more">' + esc(_sdT('Try again')) + '</button>';
        }
        if (s.more) {
            return '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + esc(_sdT('Reading earlier activity…'));
        }
        return esc(A.narrowed(s.choice) ? _sdT('That is all of it.') : _sdT('This is where the design\'s activity begins.'));
    }

    function draw() {
        if (!s || !s.host) return;
        closeMenu();
        var host = s.host;
        var html;
        var narrowed = A.narrowed(s.choice);
        var blank = A.blank(s.events.length, s.more, narrowed);
        if (s.state === 'loading') {
            html = choosersHtml(true) + '<div class="sd-act-list" aria-busy="true">' +
                [1, 2, 3, 4, 5].map(function () { return '<div class="sd-act-ghost"><span></span><span></span></div>'; }).join('') + '</div>';
        } else if (s.state === 'failed') {
            html = choosersHtml(true) + '<div class="sd-act-list"><div class="sd-act-blank"><span class="bi bi-exclamation-circle"></span>' +
                '<div class="sd-act-blank-t">' + esc(_sdT('The activity could not be read.')) + '</div>' +
                '<button type="button" class="sd-act-btn" data-act="retry">' + esc(_sdT('Try again')) + '</button></div></div>';
        } else if (blank === 'narrowed') {
            html = choosersHtml(false) + '<div class="sd-act-list"><div class="sd-act-blank"><span class="bi bi-funnel"></span>' +
                '<div class="sd-act-blank-t">' + esc(_sdT('Nothing matches the three.')) + '</div>' +
                '<div class="sd-act-blank-s">' + esc(_sdT('No such event is kept for this design.')) + '</div>' +
                '<button type="button" class="sd-act-btn" data-act="all">' + esc(_sdT('Show everything')) + '</button></div></div>';
        } else if (blank === 'none') {
            html = choosersHtml(true) + '<div class="sd-act-list"><div class="sd-act-blank"><span class="bi bi-clock"></span>' +
                '<div class="sd-act-blank-t">' + esc(_sdT('Nothing has happened in this design yet.')) + '</div>' +
                '<div class="sd-act-blank-s">' + esc(_sdT('Publishing a page, importing one or sharing a link shows here, with who did it.')) + '</div></div></div>';
        } else {
            html = choosersHtml(false) + '<div class="sd-act-list">' + rowsHtml(s.events, 0, null) +
                '<div class="sd-act-end">' + endHtml() + '</div></div>';
        }
        host.innerHTML = html;
        wire();
    }

    function appendRows(events) {
        var list = s.host.querySelector('.sd-act-list');
        var end = list && list.querySelector('.sd-act-end');
        if (!list || !end) { draw(); return; }
        var days = list.querySelectorAll('.sd-act-day');
        var lastKey = days.length ? days[days.length - 1].dataset.day : null;
        end.insertAdjacentHTML('beforebegin', rowsHtml(events, s.events.length - events.length, lastKey));
        end.innerHTML = endHtml();
        // A short page that still does not fill the list cannot be
        // scrolled to the end: the next one is asked for at once.
        if (s.more && !s.loading && !s.failedMore && A.near(list.scrollTop, list.clientHeight, list.scrollHeight)) load(true);
    }

    function drawEnd() {
        var end = s && s.host && s.host.querySelector('.sd-act-end');
        if (end) end.innerHTML = endHtml();
    }

    function wire() {
        var host = s.host;
        host.querySelectorAll('.sd-act-chooser').forEach(function (b) {
            b.addEventListener('click', function () {
                if (s.menu && s.menu.name === b.dataset.chooser) { closeMenu(); return; }
                openMenu(b);
            });
        });
        var list = host.querySelector('.sd-act-list');
        if (!list) return;
        list.addEventListener('click', function (ev) {
            var act = ev.target.closest('[data-act]');
            if (act) {
                if (act.dataset.act === 'retry') load(false);
                else if (act.dataset.act === 'more') load(true);
                else if (act.dataset.act === 'all') { s.choice = { person: '', page: 0, kind: '' }; load(false); }
                return;
            }
            var row = ev.target.closest('.sd-act-row.is-open');
            if (row) openCompare(s.events[parseInt(row.dataset.i, 10)]);
        });
        list.addEventListener('scroll', function () {
            s.scrollTop = list.scrollTop;
            if (s.more && !s.loading && !s.failedMore && A.near(list.scrollTop, list.clientHeight, list.scrollHeight)) load(true);
            countSoon();
        }, { passive: true });
        if (s.scrollTop) list.scrollTop = s.scrollTop;
        // A short first page that does not fill the list asks for more.
        if (s.state === 'ready' && s.more && !s.loading && A.near(list.scrollTop, list.clientHeight, list.scrollHeight)) load(true);
    }

    // ── The choosers' menus ──
    function menuItem(value, label, on, extra) {
        return '<button type="button" class="sd-act-item' + (on ? ' is-on' : '') + '" role="menuitemradio" aria-checked="' + (on ? 'true' : 'false') + '" data-v="' + esc(value) + '">' +
            '<span class="bi bi-check2 sd-act-check" aria-hidden="true"></span><span class="sd-act-item-lbl">' + esc(label) + '</span>' +
            (extra ? '<span class="sd-act-item-x">' + esc(extra) + '</span>' : '') + '</button>';
    }

    function menuHtml(name, query) {
        var c = s.choice;
        var html = '';
        if (name === 'person') {
            html += menuItem('', _sdT('Everyone'), !c.person) + menuItem('me', _sdT('Me'), c.person === 'me');
            if (s.people.length) {
                html += '<div class="sd-act-menu-head">' + esc(_sdT('In the rows read so far')) + '</div>';
                s.people.forEach(function (p) { html += menuItem(String(p.id), p.name, c.person === String(p.id)); });
            }
            html += '<div class="sd-act-menu-sep"></div>';
            if (full()) html += menuItem('customers', _sdT('Customers'), c.person === 'customers');
            html += menuItem('schedule', _sdT('The schedule'), c.person === 'schedule');
        } else if (name === 'page') {
            html += '<div class="sd-act-menu-find"><span class="bi bi-search" aria-hidden="true"></span><input type="search" class="sd-act-find" placeholder="' +
                esc(_sdT('Find a page…')) + '" aria-label="' + esc(_sdT('Find a page…')) + '" value="' + esc(query || '') + '"></div>';
            html += '<div class="sd-act-menu-pages">' + pagesHtml(query) + '</div>';
        } else {
            html += menuItem('', _sdT('Everything'), !c.kind);
            // A content editor reads the rows of their pages: versions and
            // the moves on and off the site.
            A.kinds().forEach(function (k) {
                if (!full() && k !== 'version' && k !== 'page') return;
                html += menuItem(k, kindLabel(k), c.kind === k);
            });
        }
        return html;
    }

    function pagesHtml(query) {
        var c = s.choice;
        var html = query ? '' : menuItem('0', _sdT('Every page'), !(c.page > 0));
        A.pages(K.pages, openPageId(), query).forEach(function (p) {
            html += menuItem(String(p.id), p.name, c.page === p.id, p.open ? _sdT('open now') : '');
        });
        return html;
    }

    function openMenu(btn) {
        closeMenu();
        var name = btn.dataset.chooser;
        var menu = document.createElement('div');
        menu.className = 'sd-act-menu';
        menu.setAttribute('role', 'menu');
        menu.innerHTML = menuHtml(name, '');
        s.host.appendChild(menu);
        // Under its chooser, kept inside the panel.
        var hr = s.host.getBoundingClientRect();
        var br = btn.getBoundingClientRect();
        var left = Math.max(0, Math.min(br.left - hr.left, hr.width - menu.offsetWidth));
        menu.style.left = left + 'px';
        menu.style.top = (br.bottom - hr.top + 2) + 'px';
        btn.setAttribute('aria-expanded', 'true');
        s.menu = { name: name, el: menu, btn: btn };

        menu.addEventListener('click', function (ev) {
            var it = ev.target.closest('.sd-act-item');
            if (!it) return;
            choose(name, it.dataset.v);
        });
        menu.addEventListener('keydown', function (ev) {
            var items = Array.prototype.slice.call(menu.querySelectorAll('.sd-act-item'));
            var at = items.indexOf(document.activeElement);
            if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); var b = s.menu && s.menu.btn; closeMenu(); if (b) b.focus(); return; }
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (!items.length) return;
                var to = (ev.key === 'ArrowDown') ? (at + 1) % items.length : (at <= 0 ? items.length - 1 : at - 1);
                items[to].focus();
            }
        });
        var find = menu.querySelector('.sd-act-find');
        if (find) {
            find.addEventListener('input', function () {
                menu.querySelector('.sd-act-menu-pages').innerHTML = pagesHtml(find.value);
            });
            find.focus();
        } else {
            var first = menu.querySelector('.sd-act-item.is-on') || menu.querySelector('.sd-act-item');
            if (first) first.focus();
        }
        s.onDoc = function (ev) {
            if (s && s.menu && !s.menu.el.contains(ev.target) && !s.menu.btn.contains(ev.target)) closeMenu();
        };
        document.addEventListener('mousedown', s.onDoc, true);
    }

    function closeMenu() {
        if (!s || !s.menu) return;
        s.menu.btn.setAttribute('aria-expanded', 'false');
        s.menu.el.remove();
        s.menu = null;
        if (s.onDoc) document.removeEventListener('mousedown', s.onDoc, true);
        s.onDoc = null;
    }

    function choose(name, value) {
        if (name === 'page') s.choice.page = parseInt(value, 10) || 0;
        else s.choice[name] = value;
        closeMenu();
        s.scrollTop = 0;
        load(false);
        var b = s.host.querySelector('.sd-act-chooser[data-chooser="' + name + '"]');
        if (b) b.focus();
    }

    // ── Compare ──
    // Compare as History opens it for a revision, with the revision before
    // on the left and the row's revision on the right; closing it gives the
    // tab back as it was.
    function openCompare(e) {
        if (!A.opens(e)) return;
        var no = Number(e.revision_no);
        K._sdPartCall('compare', 'open', [{
            pageId: Number(e.page_id), left: 'rev:' + A.before(e), right: 'rev:' + no, history: no, done: s.done
        }]).catch(K._sdPartFailed);
    }

    // ── The counts ──
    // The version rows in view without a count, one at a time, after the
    // hand rests; nothing runs while the tab is hidden.
    function countSoon() {
        if (!s || s.countTimer) return;
        s.countTimer = setTimeout(function () {
            s.countTimer = 0;
            countNext();
        }, 300);
    }

    function inView() {
        var list = s.host && s.host.querySelector('.sd-act-list');
        if (!list || s.host.hidden || !s.host.isConnected) return null;
        var lr = list.getBoundingClientRect();
        var rows = list.querySelectorAll('.sd-act-row.is-open');
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i].getBoundingClientRect();
            if (r.bottom < lr.top || r.top > lr.bottom) continue;
            var e = s.events[parseInt(rows[i].dataset.i, 10)];
            if (e && !(countKey(e) in s.counts)) return e;
        }
        return null;
    }

    function countNext() {
        if (!s || s.counting) return;
        var e = inView();
        if (!e) return;
        var key = countKey(e);
        var mine = s;
        s.counting = true;
        s.counts[key] = null;
        K._sdPartLoad('compare').then(function () {
            return Promise.all([tree(e.page_id, A.before(e)), tree(e.page_id, Number(e.revision_no))]);
        }).then(function (trees) {
            var C = window.PgCompareChanges;
            if (!C || !trees[1]) return null;
            // A version before it that cannot be read leaves the place empty.
            if (!trees[0]) return { code: '' };
            var read = C.read(trees[0], trees[1]);
            return A.count(read, read.state === 'changed' ? C.rows(read.changes).length : 0);
        }).catch(function () { return null; }).then(function (c) {
            if (mine !== s) return;
            s.counts[key] = c || { code: '' };
            var t = countText(s.counts[key]);
            s.host.querySelectorAll('.sd-act-count[data-count="' + key + '"]').forEach(function (el) {
                el.textContent = t ? ' · ' + t : '';
            });
            s.counting = false;
            setTimeout(countNext, COUNT_GAP_MS);
        });
    }

    // One revision's tree, kept for the next row (its version before is
    // often the next row's version).
    function tree(pageId, no) {
        var key = pageId + ':' + no;
        if (key in s.trees) return Promise.resolve(s.trees[key]);
        var mine = s;
        return K._pgApiPost('revision_get', { page_id: pageId, revision_no: no }).then(function (res) {
            var t = null;
            if (res && res.status === 'success' && res.revision && res.revision.tree) {
                t = K._sdTreeObjects(res.revision.tree);
                K._normalizeToggleableShowState(t);
            }
            if (mine === s) {
                s.trees[key] = t;
                s.treeOrder.push(key);
                if (s.treeOrder.length > TREES_KEPT) delete s.trees[s.treeOrder.shift()];
            }
            return t;
        }, function () { return null; });
    }

    // ── The core's calls ──
    // opts.fresh: History has just opened (nothing kept); opts.done: closes
    // History after Compare loaded a revision.
    function show(host, opts) {
        opts = opts || {};
        if (opts.fresh || !s || s.host !== host) {
            if (s) closeMenu();
            reset(host, opts.done);
        }
        load(false);
    }

    return { show: show };
});
