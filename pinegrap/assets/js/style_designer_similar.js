/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Select similar" part: chooses every element
 * like the selected one (window.PGSelectSimilar, pg_select_similar.js) and
 * draws the line above the Options panel: the count, the two lists "Alike
 * by" and "Where", what was left out and why, the next wider place when
 * nothing was found, and the first-use line. Not needed to open a page:
 * the core runs it on the first press of the menu row, the palette row or
 * the S key (_sdSimOpen()) and hands it the page through a host
 * (_sdSimHost()). Nothing is remembered between presses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('similar', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var M = window.PGSelectSimilar;

    var TIP_KEY = 'sd_similar_tip_seen';
    // The widest a list may be, in px.
    var LIST_MAX = 252;

    // The press now standing: { host, by, where, cls, res }; the word to
    // focus after the next draw ('by', 'where' or '').
    var line = null;
    var focusNext = '';
    var tipWatch = false;
    var menu = null;

    // The ways of alike as the lists name them, and as the line's words.
    function byLabel(by) {
        if (by === 'kind') return _sdT('Same kind');
        if (by === 'oneClass') return _sdT('One of its classes');
        if (by === 'component') return _sdT('The same shared component');
        return _sdT('Same kind and classes');
    }

    function byWord(by, cls) {
        if (by === 'kind') return _sdT('same kind');
        if (by === 'oneClass') return _sdT('class {var}', cls);
        if (by === 'component') return _sdT('same shared component');
        return _sdT('same kind and classes');
    }

    function whereLabel(where) {
        if (where === 'siblings') return _sdT('Beside it');
        if (where === 'page') return _sdT('On this page');
        return _sdT('In this section');
    }

    function whereWord(where) {
        if (where === 'siblings') return _sdT('beside it');
        if (where === 'page') return _sdT('on this page');
        return _sdT('in this section');
    }

    function tipSeen() {
        try { return localStorage.getItem(TIP_KEY) === '1'; } catch (e) { return true; }
    }

    function tipDone() {
        try { localStorage.setItem(TIP_KEY, '1'); } catch (e) { /* private mode: shown again next time */ }
        var tip = document.querySelector('#sd-props-head .sd-sim-tip');
        if (tip) tip.remove();
    }

    function find(host, by, where, cls) {
        return M.find(host.tree, host.node, { by: by, where: where, cls: cls, mayEdit: host.mayEdit });
    }

    // Chooses what way `by` in place `where` finds, the pressed one first,
    // and says so. No undo step: the selection only.
    function choose(host, by, where, cls) {
        var res = find(host, by, where, cls);
        if (!res) return;
        line = { host: host, by: by, where: where, cls: cls || '', res: res };
        host.select([host.node].concat(res.nodes));
        host.say(res.ids.length
            ? _sdT('{var:1} similar elements selected: {var:2}, {var:3}.', [res.ids.length + 1, byWord(by, cls), whereWord(where)])
            : noneText(res));
    }

    function noneText(res) {
        return res.wider ? _sdT('No other element like this one here') : _sdT('No other element like this one on this page');
    }

    // "3 left out: 1 you may not edit, 2 inside a shared component.", the
    // reasons in that order, those at zero not named; '' when none.
    function leftOutText(res) {
        if (!res.leftOutTotal) return '';
        var parts = [];
        if (res.leftOut.locked) parts.push(_sdT('{var} you may not edit', res.leftOut.locked));
        if (res.leftOut.shared) parts.push(_sdT('{var} inside a shared component', res.leftOut.shared));
        if (res.leftOut.kept) parts.push(_sdT('{var} a system widget’s own parts', res.leftOut.kept));
        return _sdT('{var:1} left out: {var:2}.', [res.leftOutTotal, parts.join(', ')]);
    }

    function wordBtn(list, text) {
        return '<button type="button" class="sd-sim-sl" data-sim-list="' + list + '" aria-haspopup="listbox" aria-expanded="false" aria-label="' +
            esc((list === 'by' ? _sdT('Alike by') : _sdT('Where')) + ': ' + text) + '">' + esc(text) + ' <i class="bi bi-chevron-down" aria-hidden="true"></i></button>';
    }

    // The line's markup for the press standing, `count` elements selected.
    function lineHtml(count) {
        var res = line.res, total = res.ids.length + 1;
        var words = wordBtn('by', byWord(line.by, line.cls)) + '<span class="sd-sim-dt" aria-hidden="true">·</span>' + wordBtn('where', whereWord(line.where));
        var h;
        if (!res.ids.length) {
            h = '<div class="sd-sim sd-sim-none" role="group" aria-label="' + esc(_sdT('Select similar')) + '">' +
                '<b class="sd-sim-count">' + esc(noneText(res)) + '</b><span class="sd-sim-brk"></span>' + words;
            if (res.wider) {
                h += '<div class="sd-sim-act"><button type="button" class="sd-sim-wider" data-sim-wider="' + esc(res.wider) + '">' +
                    esc(res.wider === 'page' ? _sdT('Look on this page') : _sdT('Look in this section')) + '</button></div>';
            }
        } else {
            h = '<div class="sd-sim" role="group" aria-label="' + esc(_sdT('Select similar')) + '">' +
                '<b class="sd-sim-count">' + esc(count < total ? _sdT('{var:1} of {var:2} similar', [count, total]) : _sdT('{var} similar', total)) + '</b>' +
                '<span class="sd-sim-dt sd-sim-dt1" aria-hidden="true">·</span>' + words;
        }
        var why = leftOutText(res);
        if (why) h += '<div class="sd-sim-why">' + esc(why) + '</div>';
        h += '</div>';
        if (res.ids.length && !tipSeen()) {
            h += '<div class="sd-sim-tip" role="note"><span>' +
                esc(_sdT('Every element like the one you chose is selected. Change something once and all of them change.')) +
                '</span><button type="button" class="sd-sim-got">' + esc(_sdT('Got it')) + '</button></div>';
        }
        return '<div class="sd-sim-wrap">' + h + '</div>';
    }

    // The core calls this each time the Options panel's head is drawn
    // while the selection is what the press found.
    function head(el, count) {
        if (!line || !el) return;
        closeMenu(false);
        var old = el.querySelector('.sd-sim-wrap');
        if (old) old.remove();
        var tpl = document.createElement('template');
        tpl.innerHTML = lineHtml(count);
        var wrap = tpl.content.firstElementChild;
        el.insertBefore(wrap, el.firstChild);
        wrap.addEventListener('click', onClick);
        wrap.addEventListener('keydown', onWordKey);
        // The first-use line also goes with the first change.
        if (wrap.querySelector('.sd-sim-tip') && !tipWatch) {
            var panel = document.getElementById('sd-properties');
            if (panel) {
                tipWatch = true;
                panel.addEventListener('change', function () { tipWatch = false; tipDone(); }, { once: true, capture: true });
            }
        }
        if (focusNext) {
            var w = wrap.querySelector('[data-sim-list="' + focusNext + '"]');
            focusNext = '';
            if (w) w.focus();
        }
    }

    function onClick(e) {
        var t = e.target.closest ? e.target.closest('button') : null;
        if (!t || !line) return;
        if (t.classList.contains('sd-sim-got')) { tipDone(); return; }
        if (t.hasAttribute('data-sim-wider')) {
            focusNext = 'where';
            choose(line.host, line.by, t.getAttribute('data-sim-wider'), line.cls);
            return;
        }
        var list = t.getAttribute('data-sim-list');
        if (list) {
            if (menu && menu.word === t) closeMenu(true);
            else openMenu(t, list, false);
        }
    }

    function onWordKey(e) {
        var t = e.target;
        if (!t.hasAttribute || !t.hasAttribute('data-sim-list')) return;
        if (e.key === 'ArrowDown' || ((e.key === 'Enter' || e.key === ' ') && !(menu && menu.word === t))) {
            e.preventDefault();
            e.stopPropagation();
            openMenu(t, t.getAttribute('data-sim-list'), true);
        }
    }

    // The rows of a list, each { label, count, by, where, cls, sub, on }:
    // counted now, the other list left as it is.
    function rows(list) {
        var host = line.host, out = [];
        var add = function (label, by, where, cls, on) {
            var r = find(host, by, where, cls);
            out.push({ label: label, n: r && r.ids.length ? r.ids.length + 1 : 0, by: by, where: where, cls: cls, on: on });
        };
        if (list === 'where') {
            M.WHERE.forEach(function (w) { add(whereLabel(w), line.by, w, line.cls, w === line.where); });
            return out;
        }
        M.ways(host.node).forEach(function (by) {
            if (by !== 'oneClass') { add(byLabel(by), by, line.where, '', by === line.by); return; }
            var cls = M.classes(host.node);
            // One class: the row chooses it; several: a second list.
            add(byLabel(by), by, line.where, cls.length === 1 ? cls[0] : '', by === line.by);
            if (cls.length > 1) out[out.length - 1].sub = cls;
        });
        return out;
    }

    function classRows(cls) {
        return cls.map(function (c) {
            var r = find(line.host, 'oneClass', line.where, c);
            return { label: c, n: r && r.ids.length ? r.ids.length + 1 : 0, by: 'oneClass', where: line.where, cls: c, on: line.by === 'oneClass' && line.cls === c, mono: true };
        });
    }

    function listHtml(title, list, idp) {
        return '<div class="sd-sim-head" aria-hidden="true">' + esc(title) + '</div>' + list.map(function (r, i) {
            var none = !r.n && !r.sub;
            var cnt = r.n ? String(r.n) : _sdT('none');
            return '<div class="sd-sim-opt' + (r.on ? ' on' : '') + (none ? ' off' : '') + (r.mono ? ' mono' : '') + '" role="option" id="' + idp + i + '" data-i="' + i + '"' +
                ' aria-selected="' + (r.on ? 'true' : 'false') + '"' + (none ? ' aria-disabled="true"' : '') +
                (r.sub ? ' aria-haspopup="listbox"' : '') +
                ' aria-label="' + esc(r.label + ', ' + cnt) + '">' +
                '<span class="sd-sim-ol">' + esc(r.label) + '</span>' +
                (r.sub ? '<i class="bi bi-chevron-right" aria-hidden="true"></i>' : '<span class="sd-sim-n">' + esc(cnt) + '</span>') +
                '</div>';
        }).join('');
    }

    // A list under its word, or a second list beside row `beside`.
    function place(box, anchor, beside) {
        document.body.appendChild(box);
        var r = anchor.getBoundingClientRect(), w = Math.min(box.offsetWidth, LIST_MAX);
        box.style.width = w + 'px';
        var left = beside ? r.right + 2 : r.left;
        if (left + w > window.innerWidth - 8) left = beside ? r.left - w - 2 : window.innerWidth - w - 8;
        var top = beside ? r.top - 4 : r.bottom + 4;
        if (top + box.offsetHeight > window.innerHeight - 8) top = Math.max(8, window.innerHeight - box.offsetHeight - 8);
        box.style.left = Math.max(8, left) + 'px';
        box.style.top = top + 'px';
    }

    function makeBox(id, label) {
        var box = document.createElement('div');
        box.className = 'sd-sim-menu';
        box.id = id;
        box.setAttribute('role', 'listbox');
        box.setAttribute('aria-label', label);
        box.tabIndex = -1;
        return box;
    }

    function openMenu(word, list, keyboard) {
        closeMenu(false);
        var title = list === 'by' ? _sdT('Alike by') : _sdT('Where');
        var data = rows(list);
        var box = makeBox('sd-sim-menu', title);
        box.innerHTML = listHtml(title, data, 'sd-sim-o');
        place(box, word, false);
        menu = { word: word, list: list, box: box, data: data, active: -1, sub: null, idp: 'sd-sim-o' };
        word.setAttribute('aria-expanded', 'true');
        word.classList.add('on');
        box.addEventListener('click', function (e) { pickFrom(menu, e); });
        box.addEventListener('mouseover', function (e) {
            var o = e.target.closest ? e.target.closest('.sd-sim-opt') : null;
            if (!o || !menu) return;
            var i = parseInt(o.getAttribute('data-i'), 10);
            if (menu.data[i] && menu.data[i].sub) openSub(i, false);
            else closeSub();
        });
        box.addEventListener('keydown', onMenuKey);
        document.addEventListener('mousedown', onOutside, true);
        var start = 0;
        data.forEach(function (r, i) { if (r.on) start = i; });
        move(menu, start, keyboard);
        if (keyboard) box.focus();
    }

    function openSub(i, keyboard) {
        closeSub();
        var row = menu.box.querySelector('[data-i="' + i + '"]');
        var data = classRows(menu.data[i].sub);
        var box = makeBox('sd-sim-sub', _sdT('One of its classes'));
        box.innerHTML = listHtml(_sdT('One of its classes'), data, 'sd-sim-s');
        place(box, row, true);
        menu.sub = { box: box, data: data, active: -1, idp: 'sd-sim-s' };
        box.addEventListener('click', function (e) { pickFrom(menu.sub, e); });
        box.addEventListener('keydown', onMenuKey);
        if (keyboard) { move(menu.sub, 0, true); box.focus(); }
    }

    function closeSub() {
        if (menu && menu.sub) { menu.sub.box.remove(); menu.sub = null; }
    }

    function closeMenu(refocus) {
        if (!menu) return;
        var word = menu.word;
        closeSub();
        menu.box.remove();
        menu = null;
        document.removeEventListener('mousedown', onOutside, true);
        if (word && word.isConnected) {
            word.setAttribute('aria-expanded', 'false');
            word.classList.remove('on');
            if (refocus) word.focus();
        }
    }

    function onOutside(e) {
        if (!menu) return;
        if (menu.box.contains(e.target) || (menu.sub && menu.sub.box.contains(e.target)) || menu.word.contains(e.target)) return;
        closeMenu(false);
    }

    // The active row of list `m`, moved to `i`, skipping none.
    function move(m, i, show) {
        var n = m.data.length;
        if (!n) return;
        for (var k = 0; k < n; k++) {
            var j = ((i % n) + n) % n;
            var r = m.data[j];
            if (r.n || r.sub) { i = j; break; }
            i = j + 1;
        }
        m.active = i;
        m.box.querySelectorAll('.sd-sim-opt').forEach(function (o) {
            var on = parseInt(o.getAttribute('data-i'), 10) === i;
            o.classList.toggle('foc', on && !!show);
            if (on) m.box.setAttribute('aria-activedescendant', m.idp + i);
        });
    }

    function pick(m, i) {
        var r = m.data[i];
        if (!r || !menu) return;
        if (r.sub) { openSub(i, true); return; }
        if (!r.n) return;
        var list = menu.list;
        closeMenu(false);
        focusNext = list;
        choose(line.host, r.by, r.where, r.cls);
    }

    function pickFrom(m, e) {
        var o = e.target.closest ? e.target.closest('.sd-sim-opt') : null;
        if (o && m) pick(m, parseInt(o.getAttribute('data-i'), 10));
    }

    function onMenuKey(e) {
        if (!menu) return;
        var inSub = !!(menu.sub && menu.sub.box.contains(e.target));
        var m = inSub ? menu.sub : menu;
        var k = e.key;
        if (k === 'ArrowDown' || k === 'ArrowUp') {
            e.preventDefault();
            var step = k === 'ArrowDown' ? 1 : -1, i = m.active;
            for (var t = 0; t < m.data.length; t++) {
                i = (i + step + m.data.length) % m.data.length;
                if (m.data[i].n || m.data[i].sub) break;
            }
            move(m, i, true);
        } else if (k === 'Enter' || k === ' ') {
            e.preventDefault();
            pick(m, m.active);
        } else if (k === 'ArrowRight' && !inSub && m.data[m.active] && m.data[m.active].sub) {
            e.preventDefault();
            openSub(m.active, true);
        } else if ((k === 'ArrowLeft' || k === 'Escape') && inSub) {
            e.preventDefault();
            closeSub();
            menu.box.focus();
        } else if (k === 'Escape' || k === 'Tab') {
            // Escape closes the list and keeps the selection.
            if (k === 'Escape') e.preventDefault();
            closeMenu(k === 'Escape');
        }
        e.stopPropagation();
    }

    return {
        run: function (host) {
            if (!M || !host || !host.node) return;
            choose(host, 'kindClasses', 'section', '');
        },
        head: head,
        // For the tests: the line's markup for a press, without a page.
        _lineHtml: function (state, count) { if (state) line = state; return lineHtml(count); }
    };
});
