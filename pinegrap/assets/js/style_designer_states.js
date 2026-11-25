/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the states part: the Options panel under the state
 * chosen in the Style tab's "State" row (Hover, Focus, Pressed). Color and
 * background, Border and corner and Shadow and effect show and write the
 * state's rule on the element's custom style class in User Styles
 * (window.PGOptionStates); Text, Spacing and Size and display stay closed
 * and dimmed, the same in every state. The canvas shows the state on the
 * selected element. On Normal it rings the section heads a state changes.
 * Not needed to open a page: the core runs this file the first time a
 * state is chosen or an element holding a state's rule is shown
 * (_sdPartLoad(), pg_designer_parts()) and hands it the panel through a
 * host (_sdStateHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('states', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var S = window.PGOptionStates;

    // The sections a state writes, and the ones that keep one value for
    // every state.
    var WRITE = { colors: true, border: true, styling: true };
    var SAME = { text: true, spacing: true, display: true };
    var HINT_KEY = 'sd_state_hint';
    var VIEW_ID = 'sd-state-view';

    // The host of the last drawing: the panel's listeners read the state
    // and write through it.
    var cur = null;

    function names(list) {
        if (list.length < 2) return list[0] || '';
        return _sdT('{var:1} and {var:2}', [list.slice(0, -1).join(', '), list[list.length - 1]]);
    }

    function hintSeen() {
        try { return localStorage.getItem(HINT_KEY) === '1'; } catch (e) { return true; }
    }

    // A row's control a state writes: a class group's select or a class
    // switch. Returns { el, group, value } or null.
    function ctrlOf(row) {
        var sel = row.querySelector('select[data-stylegroup]');
        if (sel) {
            return { el: sel, group: String(sel.getAttribute('data-stylegroup')).split(','), value: sel.value };
        }
        var cb = row.querySelector('input[type="checkbox"][data-util-toggle]');
        if (cb) {
            var c = cb.getAttribute('data-util-toggle');
            return { el: cb, group: [c], value: cb.checked ? c : '' };
        }
        return null;
    }

    function writable(ctrl) {
        return !!ctrl && ctrl.group.some(function (c) { return S.decls(c) !== null; });
    }

    // The visible trigger of a colour picker follows its hidden select.
    function cpSync(sel) {
        var wrap = sel.closest('.sd-cp');
        if (!wrap) return;
        var opt = wrap.querySelector('.sd-cp-option[data-val="' + sel.value + '"]');
        var trig = wrap.querySelector('.sd-cp-trigger');
        if (!opt || !trig) return;
        var dot = opt.querySelector('.sd-color-preview');
        var lbl = opt.querySelector('.sd-cp-opt-lbl');
        var tDot = trig.querySelector('.sd-color-preview');
        var tLbl = trig.querySelector('.sd-cp-label');
        if (dot && tDot) tDot.className = dot.className;
        if (lbl && tLbl) tLbl.textContent = lbl.textContent;
        wrap.querySelectorAll('.sd-cp-option').forEach(function (o) { o.classList.toggle('sd-cp-opt-active', o === opt); });
    }

    function unmark(root) {
        root.querySelectorAll('.sd-prop-modified').forEach(function (l) {
            l.classList.remove('sd-prop-modified');
            delete l.dataset.resetVal;
            delete l.dataset.resetKind;
            delete l.dataset.resetSizes;
        });
    }

    // A row no state writes: dimmed in place, its controls off.
    function dimRow(row) {
        row.classList.add('sd-state-off');
        unmark(row);
        row.querySelectorAll('input, select, textarea, button').forEach(function (c) { c.disabled = true; });
    }

    // A section the same in every state: closed, dimmed, not opened.
    function dimCard(card) {
        card.classList.add('sd-state-dim');
        card.setAttribute('data-sd-sum', _sdT('same in every state'));
        var hdr = card.querySelector(':scope > .sd-sect-hdr');
        var body = card.querySelector(':scope > .sd-sect-body');
        if (hdr) {
            hdr.classList.remove('sd-sect-open');
            hdr.setAttribute('aria-expanded', 'false');
            hdr.setAttribute('aria-disabled', 'true');
        }
        if (body) body.style.display = 'none';
        unmark(card);
    }

    // The line under the State row: how many options the state sets, with
    // "Reset this state"; the first time, what a state is for.
    function lineHtml(host, n) {
        var name = host.name(host.state);
        var h = '';
        if (!hintSeen()) {
            h += '<div class="sd-state-hint" role="note">' +
                '<span>' + esc(_sdT('What you set under a state holds only while the element is hovered, focused or pressed. The canvas shows the chosen state on this element.')) + '</span>' +
                '<button type="button" class="sd-state-got">' + esc(_sdT('Got it')) + '</button>' +
            '</div>';
        }
        h += '<div class="sd-state-line">';
        if (!n) {
            h += '<span>' + esc(_sdT('Nothing is set for {var}.', name)) + '</span>';
        } else {
            h += '<span>' + esc(n === 1 ? _sdT('1 option set for {var}', name) : _sdT('{var:1} options set for {var:2}', [n, name])) + '</span>' +
                '<button type="button" class="sd-state-reset"' + (host.locked() ? ' disabled' : '') + '>' + esc(_sdT('Reset this state')) + '</button>';
        }
        return h + '</div>';
    }

    // Normal: a ring on the head of each section a state changes, its tip
    // naming the states.
    function rings(host) {
        var cls = host.cls();
        if (!cls) return;
        var by = S.sections(host.text(), cls);
        Object.keys(by).forEach(function (key) {
            if (!by[key].length) return;
            var card = host.panel.querySelector('[data-sd-otab="style"] [data-sd-key="' + key + '"]');
            var title = card ? card.querySelector(':scope > .sd-sect-hdr .sd-sect-title') : null;
            if (!title) return;
            var tip = _sdT('{var} change this section', names(by[key].map(host.name)));
            if (by[key].length === 1) tip = _sdT('{var} changes this section', host.name(by[key][0]));
            title.insertAdjacentHTML('beforeend', '<span class="sd-state-ring" title="' + esc(tip) + '" role="img" aria-label="' + esc(tip) + '"></span>');
        });
    }

    // The panel under the chosen state.
    function underState(host) {
        var pane = host.panel.querySelector('[data-sd-otab="style"]');
        var cls = host.cls();
        var map = cls ? S.read(host.text(), cls, host.state) : {};
        if (!pane) return;
        host.panel.classList.add('sd-state-on');
        var row = pane.querySelector('[data-sd-state-row]');
        if (row) row.insertAdjacentHTML('afterend', lineHtml(host, S.count(map)));
        pane.querySelectorAll('[data-sd-key]').forEach(function (card) {
            var key = card.getAttribute('data-sd-key');
            if (SAME[key]) { dimCard(card); return; }
            if (!WRITE[key]) return;
            var set = 0;
            // The corners as five rows (the corner box writes classes).
            var rd = card.querySelector('.sd-rd-rows');
            if (rd) rd.hidden = false;
            card.querySelectorAll('.sd-bg-img-grp, #sd-bcp-row').forEach(dimRow);
            card.querySelectorAll('.sd-prop-row').forEach(function (r) {
                if (r.closest('.sd-state-off')) return;
                var c = ctrlOf(r);
                if (!writable(c)) { dimRow(r); return; }
                var lbl = r.querySelector('.sd-prop-label');
                unmark(r);
                if (S.holds(map, c.group)) {
                    var v = S.match(map, c.group);
                    if (c.el.type === 'checkbox') c.el.checked = v !== '';
                    else c.el.value = v;
                    if (c.el.classList.contains('sd-cp-select')) cpSync(c.el);
                    if (lbl) { lbl.classList.add('sd-prop-modified'); lbl.dataset.resetKind = 'state'; }
                    set++;
                } else {
                    r.classList.add('sd-state-normal');
                }
            });
            if (!set) card.setAttribute('data-sd-sum', _sdT('as Normal'));
        });
        host.segSync(pane);
        host.resetButtons(pane);
        pane.querySelectorAll('.sd-prop-label[data-reset-kind="state"]').forEach(function (lbl) {
            var b = lbl.previousElementSibling;
            if (!b || !b.classList.contains('sd-chg-reset')) return;
            var name = (lbl.textContent || '').trim();
            b.title = _sdT('Reset to Normal');
            b.setAttribute('aria-label', _sdT('Reset {var} to Normal', name));
        });
        view(host, map);
    }

    // The canvas: the selected element drawn in the state, its toolbar
    // naming it. Canvas only; nothing of it is saved.
    function view(host, map) {
        var doc = host.canvasDoc;
        if (!doc || !doc.head) return;
        var el = doc.getElementById(VIEW_ID);
        var id = String(host.node._id || '').replace(/["\\]/g, '');
        var cls = host.cls();
        if (!el) {
            el = doc.createElement('style');
            el.id = VIEW_ID;
            doc.head.appendChild(el);
        }
        var sel = '[data-sd-id="' + id + '"]';
        var css = '';
        var decl = Object.keys(map).map(function (p) { return p + ': ' + map[p] + ' !important;'; }).join(' ');
        if (cls && decl) css += sel + ' > .' + cls + ', .' + cls + sel + ' { ' + decl + ' }\n';
        css += sel + '.sd-selected > .sd-tb::before { content: "' + String(host.name(host.state)).replace(/["\\\n]/g, '') + '";' +
            ' display: flex; align-items: center; padding: 0 6px; height: 18px; font-size: 9px; font-weight: 700;' +
            ' text-transform: uppercase; letter-spacing: .04em; color: #fff; background: rgba(0,0,0,.25); }\n';
        el.textContent = css;
    }

    function leave(doc) {
        var el = doc ? doc.getElementById(VIEW_ID) : null;
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    // Once per panel: under a state a change of a writable row writes the
    // state's rule instead of the element's class; a dimmed section's head
    // does not open; the line's buttons.
    function bind(panel) {
        if (panel._sdStates) return;
        panel._sdStates = true;
        panel.addEventListener('change', function (e) {
            if (!cur || !cur.state || !panel.classList.contains('sd-state-on')) return;
            var t = e.target;
            var card = t && t.closest ? t.closest('[data-sd-key]') : null;
            if (!card || !WRITE[card.getAttribute('data-sd-key')]) return;
            var row = t.closest('.sd-prop-row');
            var c = row ? ctrlOf(row) : null;
            if (!c || c.el !== t || !writable(c)) return;
            e.stopImmediatePropagation();
            var st = cur.state;
            cur.write(function (text, cls) { return S.write(text, cls, st, c.group, c.value); });
        }, true);
        var block = function (e) {
            if (!panel.classList.contains('sd-state-on')) return;
            var hdr = e.target && e.target.closest ? e.target.closest('.sd-state-dim > .sd-sect-hdr') : null;
            if (!hdr) return;
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            e.stopImmediatePropagation();
        };
        panel.addEventListener('click', block, true);
        panel.addEventListener('keydown', block, true);
        panel.addEventListener('click', function (e) {
            var b = e.target && e.target.closest ? e.target.closest('.sd-state-reset, .sd-state-got') : null;
            if (!b || b.disabled || !cur) return;
            if (b.classList.contains('sd-state-got')) {
                try { localStorage.setItem(HINT_KEY, '1'); } catch (ex) {}
                var hint = b.closest('.sd-state-hint');
                if (hint) hint.remove();
                return;
            }
            var st = cur.state;
            cur.write(function (text, cls) { return S.clear(text, cls, st); });
        });
    }

    function after(host) {
        if (!S || !host.panel || !host.node) return;
        cur = host;
        bind(host.panel);
        host.panel.classList.remove('sd-state-on');
        if (!host.state) { leave(host.canvasDoc); rings(host); return; }
        underState(host);
    }

    // The dot's arrow on a state's row: that one value out of the state.
    function resetRow(host, row) {
        var c = row ? ctrlOf(row) : null;
        if (!S || !host.state || !c) return;
        var st = host.state;
        host.write(function (text, cls) { return S.write(text, cls, st, c.group, ''); });
    }

    // The states holding values, for "Only what I changed".
    function review(host) {
        var cls = host.node ? host.cls() : '';
        if (!S || !cls || !host.held().length) return [];
        var text = host.text();
        return S.STATES.map(function (st) {
            return { state: st, name: host.name(st), count: S.count(S.read(text, cls, st)) };
        }).filter(function (e) { return e.count > 0; });
    }

    // "Reset all" of "Only what I changed": every state's rule out. Called
    // inside the core's one undo step; returns how many values went.
    function clearAll(host) {
        var n = review(host).reduce(function (s, e) { return s + e.count; }, 0);
        if (!n) return 0;
        host.write(function (text, cls) {
            S.STATES.forEach(function (st) { text = S.clear(text, cls, st); });
            return text;
        });
        return n;
    }

    return { after: after, leave: leave, resetRow: resetRow, review: review, clearAll: clearAll };
});
