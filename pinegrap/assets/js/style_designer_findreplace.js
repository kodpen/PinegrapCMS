/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Find and Replace" part: the window Ctrl+F and
 * the canvas bar's Find open (openFindReplaceModal()). Three scopes, "This
 * page" first: the words of the open page; "All pages": the words of every
 * page of the design, listed per page with its count before "Replace All",
 * a page someone else has open for editing (or one without content rights
 * for its folder) left out and named; "Code files": the files open in the
 * code view, written into their editors unsaved. The matching is
 * window.PGFindReplace (pg_find_replace.js).
 *
 * Each page changed is one undo step of its own, kept in that page's
 * history: Undo on the page takes back what was replaced there. A code
 * file takes the change as one step of its editor's undo.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('findreplace', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var M = window.PGFindReplace;

    // The most undo steps a page keeps, as saveState() does.
    var UNDO_MAX = 50;
    var SCOPES = ['page', 'pages', 'files'];

    // The window open now: { el, scope, timer, found }; null when closed.
    var win = null;

    function scopeLabel(s) {
        if (s === 'pages') return _sdT('All pages');
        if (s === 'files') return _sdT('Code files');
        return _sdT('This page');
    }

    function query() {
        var q = win.el.querySelector('#sd-fr-find').value;
        return {
            search: q,
            caseSensitive: win.el.querySelector('#sd-fr-case').checked,
            wholeWord: win.el.querySelector('#sd-fr-word').checked,
            regex: win.el.querySelector('#sd-fr-regex').checked,
            // The code view's own search reads ^ and $ per line.
            lines: win.scope === 'files'
        };
    }

    function pageName(p) {
        return (p.page_title || p.page_name || '').trim() || _sdT('New page');
    }

    // Who else has page `p` open for editing; '' when nobody. The open
    // page in view mode is held by the one the presence beat named.
    function holderOf(p) {
        var c = K._collab;
        if (p.key === K.activeKey && c && c.mayEdit === false) {
            return (c.holder && c.holder.name) ? c.holder.name : _sdT('another user');
        }
        return K._pgTabHolder(p);
    }

    function pageInputs(only) {
        return (K.pages || []).filter(function (p) {
            return p && (!only || p.key === K.activeKey);
        }).map(function (p) {
            return { key: p.key, name: pageName(p), tree: K._pgTreeOf(p), locked: p.access === 'locked', page: p };
        });
    }

    // The code view's file tabs that are read: loaded, not the page.
    function fileTabs() {
        var code = K._sdCode;
        return (code && Array.isArray(code.tabs) ? code.tabs : []).filter(function (t) {
            return t && t.key !== 'page' && t.loaded && t.ed;
        });
    }

    function filePlan(re) {
        var found = [], out = [];
        fileTabs().forEach(function (t) {
            var n = M.count(t.ed.getValue(), re);
            if (!n) return;
            var ro = !!(t.spec && t.spec.readOnly);
            (ro ? out : found).push({ key: t.key, name: t.title || t.key, count: n, skip: ro ? 'readonly' : '', who: '', tab: t });
        });
        return found.concat(out);
    }

    function plan() {
        var pat = M.pattern(query());
        if (!pat.re) return { error: pat.error, found: [] };
        if (win.scope === 'files') return { error: '', found: filePlan(pat.re), re: pat.re };
        var inputs = pageInputs(win.scope === 'page');
        return { error: '', found: M.plan(inputs, pat.re, function (it) { return holderOf(it.page); }), re: pat.re };
    }

    function skipText(r) {
        if (r.skip === 'held') return _sdT('Open for editing by {var}', r.who);
        if (r.skip === 'rights') return _sdT('No content rights for its folder');
        if (r.skip === 'readonly') return _sdT('Read only');
        return '';
    }

    function say(text, bad) {
        var st = win.el.querySelector('#sd-fr-status');
        st.textContent = text;
        st.classList.toggle('is-bad', !!bad);
    }

    function draw() {
        if (!win) return;
        var list = win.el.querySelector('#sd-fr-list');
        var res = plan();
        win.found = res.found;
        var q = win.el.querySelector('#sd-fr-find').value;
        var btn = win.el.querySelector('#sd-fr-replace-all');
        list.innerHTML = '';
        list.hidden = true;
        btn.disabled = true;
        if (!q) { say(win.scope === 'files' && !fileTabs().length ? _sdT('No code file is open. The files open in the code view are searched.') : '—'); return; }
        if (res.error === 'invalid') { say(_sdT('Invalid regular expression'), true); return; }
        if (res.error === 'empty') { say(_sdT('The pattern matches empty text.'), true); return; }
        if (win.scope === 'files' && !fileTabs().length) { say(_sdT('No code file is open. The files open in the code view are searched.')); return; }
        var t = M.totals(res.found);
        btn.disabled = t.count === 0;
        if (win.scope === 'page') {
            var held = res.found.length && res.found[0].skip ? res.found[0] : null;
            if (held) { say(skipText(held) + ' — ' + _sdT('{var} matches', held.count), true); return; }
            say(_sdT('{var} matches', t.count));
            return;
        }
        if (!res.found.length) { say(_sdT('Nothing found.')); return; }
        say((win.scope === 'files'
            ? _sdT('{var:1} matches in {var:2} files', [t.count, t.pages])
            : _sdT('{var:1} matches on {var:2} pages', [t.count, t.pages])) +
            (t.left ? ' · ' + _sdT('{var} left out', t.left) : ''));
        list.innerHTML = res.found.map(function (r, i) {
            return '<li class="sd-fr-row' + (r.skip ? ' is-left' : '') + '">' +
                '<button type="button" class="sd-fr-row-name" data-row="' + i + '" title="' + esc(_sdT('Open')) + '">' +
                    '<span class="bi ' + (win.scope === 'files' ? 'bi-file-earmark-code' : 'bi-file-earmark') + '" aria-hidden="true"></span>' + esc(r.name) +
                '</button>' +
                (r.skip ? '<span class="sd-fr-row-why">' + esc(skipText(r)) + '</span>' : '') +
                '<span class="sd-fr-row-n">' + r.count + '</span>' +
            '</li>';
        }).join('');
        list.hidden = false;
    }

    function soon() {
        clearTimeout(win.timer);
        win.timer = setTimeout(draw, 150);
    }

    // One undo step on page `p`: the active page through saveState(), any
    // other in its own history, which the tab switch takes up.
    function stepOn(p) {
        if (p.key === K.activeKey) { K.saveState(); return; }
        if (!Array.isArray(p.undoStack)) p.undoStack = [];
        p.undoStack.push({ tree: JSON.stringify(p.tree), shared: {}, assets: null });
        if (p.undoStack.length > UNDO_MAX) p.undoStack.shift();
        p.redoStack = [];
    }

    function replaceAll() {
        var res = plan();
        if (!res.re) return;
        var rep = win.el.querySelector('#sd-fr-replace').value;
        var regex = win.el.querySelector('#sd-fr-regex').checked;
        var total = 0, changed = 0, active = false;
        res.found.forEach(function (r) {
            if (r.skip) return;
            if (win.scope === 'files') {
                var out = M.replace(r.tab.ed.getValue(), res.re, rep, regex);
                if (!out.count) return;
                // Kept in the editor's history: one Undo there takes it back.
                r.tab.ed.setValue(out.text, true);
                total += out.count; changed++;
                return;
            }
            var p = K._pgPageByKey(r.key);
            if (!p) return;
            stepOn(p);
            var n = M.treeReplace(K._pgTreeOf(p), res.re, rep, regex);
            if (!n) return;
            total += n; changed++;
            if (p.key === K.activeKey) active = true;
        });
        if (win.scope === 'files') K._sdCodeTabsRender();
        else {
            if (active) K.render();
            K._pgTabsRender();
        }
        draw();
        if (!total) return;
        var msg = win.scope === 'page' ? _sdT('{var} matches were replaced.', total)
            : win.scope === 'files' ? _sdT('{var:1} matches replaced in {var:2} files. Save each file to keep them.', [total, changed])
            : _sdT('{var:1} matches replaced on {var:2} pages. Undo on a page takes back what was replaced there.', [total, changed]);
        say(msg);
        K.sdToast(esc(msg), 'success');
    }

    function openRow(i) {
        var r = win.found[i];
        if (!r) return;
        if (win.scope === 'files') {
            var tab = r.tab;
            var re = M.pattern(query()).re;
            if (!tab || !re) return;
            var text = tab.ed.getValue();
            re.lastIndex = 0;
            var m = re.exec(text);
            re.lastIndex = 0;
            // The file tabs live in the part 'code', loaded since a tab is open.
            K._sdPartCall('code', 'tabActivate', [r.key]).then(function () {
                if (m) tab.ed.goTo(m.index, m.index + m[0].length);
            }).catch(K._sdPartFailed);
            return;
        }
        if (r.key !== K.activeKey) K._pgTabsSwitch(r.key);
        draw();
    }

    function close() {
        if (!win) return;
        clearTimeout(win.timer);
        win.el.remove();
        win = null;
    }

    function open() {
        if (win) { close(); return; }
        var el = document.createElement('div');
        el.id = 'sd-fr-overlay';
        el.className = 'sd-fr-overlay';
        el.innerHTML =
            '<div class="sd-fr-modal" role="dialog" aria-label="' + esc(_sdT('Find and Replace')) + '">' +
                '<div class="sd-fr-header">' +
                    '<span class="bi bi-search me-3"></span>' + esc(_sdT('Find and Replace')) +
                    '<button type="button" class="sd-fr-close" id="sd-fr-close" aria-label="' + esc(_sdT('Close')) + '"><span class="bi bi-x-lg"></span></button>' +
                '</div>' +
                '<div class="sd-fr-body">' +
                    '<div class="sd-fr-scope" role="group" aria-label="' + esc(_sdT('Where to look')) + '">' +
                        SCOPES.map(function (s) {
                            return '<button type="button" data-scope="' + s + '" aria-pressed="' + (s === 'page') + '"' + (s === 'page' ? ' class="is-active"' : '') + '>' + esc(scopeLabel(s)) + '</button>';
                        }).join('') +
                    '</div>' +
                    '<label class="sd-fr-label" for="sd-fr-find">' + esc(_sdT('Find')) + '</label>' +
                    '<input type="text" class="sd-fr-input" id="sd-fr-find" placeholder="' + esc(_sdT('The text to look for…')) + '" autocomplete="off">' +
                    '<label class="sd-fr-label" for="sd-fr-replace">' + esc(_sdT('Replace')) + '</label>' +
                    '<input type="text" class="sd-fr-input" id="sd-fr-replace" placeholder="' + esc(_sdT('The new text…')) + '" autocomplete="off">' +
                    '<div class="sd-fr-opts">' +
                        '<label><input type="checkbox" id="sd-fr-case"> ' + esc(_sdT('Case sensitive')) + '</label>' +
                        '<label><input type="checkbox" id="sd-fr-word"> ' + esc(_sdT('Whole word')) + '</label>' +
                        '<label><input type="checkbox" id="sd-fr-regex">' + esc(_sdT(' Regex')) + '</label>' +
                    '</div>' +
                    '<div class="sd-fr-status" id="sd-fr-status" aria-live="polite">—</div>' +
                    '<ul class="sd-fr-list" id="sd-fr-list" hidden></ul>' +
                '</div>' +
                '<div class="sd-fr-footer">' +
                    '<button type="button" class="sd-fr-btn" id="sd-fr-count">' + esc(_sdT('Count matches')) + '</button>' +
                    '<button type="button" class="sd-fr-btn sd-fr-btn-primary" id="sd-fr-replace-all" disabled>' + esc(_sdT('Replace All')) + '</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(el);
        win = { el: el, scope: 'page', timer: 0, found: [] };

        var find = el.querySelector('#sd-fr-find');
        find.addEventListener('input', soon);
        ['#sd-fr-case', '#sd-fr-word', '#sd-fr-regex'].forEach(function (id) {
            el.querySelector(id).addEventListener('change', draw);
        });
        el.querySelectorAll('.sd-fr-scope button').forEach(function (b) {
            b.addEventListener('click', function () {
                win.scope = b.dataset.scope;
                el.querySelectorAll('.sd-fr-scope button').forEach(function (o) {
                    var on = o === b;
                    o.classList.toggle('is-active', on);
                    o.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                draw();
            });
        });
        el.querySelector('#sd-fr-count').addEventListener('click', draw);
        el.querySelector('#sd-fr-replace-all').addEventListener('click', replaceAll);
        el.querySelector('#sd-fr-list').addEventListener('click', function (e) {
            var b = e.target.closest('[data-row]');
            if (b) openRow(parseInt(b.dataset.row, 10));
        });
        el.querySelector('#sd-fr-close').addEventListener('click', close);
        el.addEventListener('click', function (e) { if (e.target === el) close(); });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
            else if (e.key === 'Enter' && !e.shiftKey && document.activeElement === find) draw();
        });
        setTimeout(function () { find.focus(); }, 30);
    }

    return { open: open, close: close };
});
