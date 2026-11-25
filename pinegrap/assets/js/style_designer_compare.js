/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Compare part: History's Compare window, opened
 * for any two versions of a page (the page now, the page on the site, any
 * revision). Its first tab, "Look", shows the two as pictures of the whole
 * page, one over the other with a slider handle between them, or "Side by
 * side", at a desktop's (1280 px) or a phone's (390 px) width; "Code" is
 * the line diff of their markup. Opened from History, each side's name is
 * a chooser and "Load this revision" loads the revision whose row opened
 * it, else the left side.
 *
 * The pictures are drawn by the server as a signed-out visitor is served
 * the page (designer/compare_render), one after the other, in frames that
 * run no script but share the panel's origin, so the window reads a page's
 * height; the editor draws a picture itself when the server gives none.
 * What the reader (window.PgCompareChanges) finds gives the state, the
 * foot's count and "What changed", the column beside the pictures: a row
 * chosen scrolls both pictures to its place, lines them up on it and
 * outlines it, on the window's own layer over the frames. Opened over a
 * modal dialog (the Publish sheet), the window is placed inside it, and
 * closing it gives the dialog back as it was. The rules are
 * window.PgCompareLook's.
 *
 * Not needed to open a page: the core runs this file the first time
 * Compare is opened (_sdCompareOpen(), pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('compare', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var C = window.PgCompareChanges;
    var L = window.PgCompareLook;
    var PREFS_KEY = 'sd-compare-look';
    var WAIT_MS = 12000;
    var SIDES = ['before', 'after'];
    // The window open now; a second open replaces it.
    var w = null;

    function readPrefs() {
        try { return L.prefs(window.localStorage.getItem(PREFS_KEY)); } catch (e) { return L.prefs(null); }
    }

    function keepPrefs() {
        try { window.localStorage.setItem(PREFS_KEY, L.prefsText(w.prefs)); } catch (e) { /* not kept */ }
    }

    function siteBase() {
        var base = location.protocol + '//' + location.host + (window.OUTPUT_PATH || '/');
        return (base.charAt(base.length - 1) === '/') ? base : base + '/';
    }

    function pageOf(pageId) {
        var list = K.pages || [];
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].page_id === pageId) return list[i];
        }
        return null;
    }

    // A draft page and a page never saved have nothing on the site.
    function hasLive() {
        var p = w.page;
        if (p && (!(p.page_id > 0) || (K._pgDraftsReady() && p.page_draft))) return false;
        return !!(p ? L.liveTree(p.baseline) : w.revisions.length);
    }

    function nowTree() {
        return w.page ? (K._pgTreeOf(w.page) || null) : null;
    }

    function revRow(no) {
        for (var i = 0; i < w.revisions.length; i++) {
            if (w.revisions[i].revision_no === no) return w.revisions[i];
        }
        return null;
    }

    // ── Names ──
    function sideName(s) {
        if (!s) return '';
        if (s.kind === 'now') return _sdT('Draft');
        if (s.kind === 'live') return _sdT('On the site');
        return _sdT('Revision #{var}', s.no);
    }

    function sideDetail(s) {
        if (!s) return '';
        if (s.kind === 'now') return _sdT('as it is in the designer now');
        var r = (s.kind === 'live') ? (w.revisions[0] || null) : revRow(s.no);
        if (!r) return '';
        if (s.kind === 'live') return _sdT('since {var}', K._sdRevisionWhen(r.created_at));
        return (r.user_username || _sdT('Unknown')) + ' · ' + K._sdRevisionWhen(r.created_at) + ' · ' + K._sdRevisionSourceLabel(r.source);
    }

    function optionLabel(v) {
        var s = L.side(v);
        if (s.kind === 'now') return _sdT('The page now');
        if (s.kind === 'live') return _sdT('The page on the site');
        var r = revRow(s.no);
        return _sdT('Revision #{var}', s.no) + (r ? ' · ' + K._sdRevisionWhen(r.created_at) : '');
    }

    function drawingText(s) {
        if (s.kind === 'now') return _sdT('Drawing the draft…');
        if (s.kind === 'live') return _sdT('Drawing the page on the site…');
        return _sdT('Drawing revision #{var}…', s.no);
    }

    function nextText(s) {
        if (s.kind === 'now') return _sdT('The draft is drawn next');
        if (s.kind === 'live') return _sdT('The page on the site is drawn next');
        return _sdT('Revision #{var} is drawn next', s.no);
    }

    function failedText(s) {
        if (s.kind === 'now') return _sdT('The draft could not be drawn.');
        if (s.kind === 'live') return _sdT('The page on the site could not be drawn.');
        return _sdT('Revision #{var} could not be drawn.', s.no);
    }

    function footText(read) {
        var live = w.sides.before.kind === 'live' && w.sides.after.kind === 'now';
        var count = w.rows.length;
        switch (L.foot(read.state, count, live)) {
            case 'live_one': return _sdT('1 change between the page on the site and the draft.');
            case 'live_many': return _sdT('{var} changes between the page on the site and the draft.', count);
            case 'one': return _sdT('1 change between the two versions.');
            case 'many': return _sdT('{var} changes between the two versions.', count);
            case 'same': return _sdT('Nothing has changed.');
            case 'new': return _sdT('The whole page is new.');
            default: return _sdT('The changes could not be listed. The two pictures are still the two pages.');
        }
    }

    function noteText(note) {
        if (note === 'new') return _sdT('This page is not on the site yet. This is the draft alone.');
        if (note !== 'same') return '';
        return (w.sides.before.kind === 'live' && w.sides.after.kind === 'now')
            ? _sdT('Nothing has changed. The draft is the same as the page on the site.')
            : _sdT('Nothing has changed. The two versions are the same.');
    }

    // The reader's sentences, translated (PgCompareChanges' TEXT).
    function texts() {
        if (w.texts) return w.texts;
        w.texts = {
            added: _sdT('{what} added'), added_named: _sdT('{what} added: {name}'),
            removed: _sdT('{what} removed'), removed_named: _sdT('{what} removed: {name}'),
            moved_up: _sdT('{what} moved up'), moved_down: _sdT('{what} moved down'), moved_into: _sdT('{what} moved into {where}'),
            words: _sdT('{what} text changed'), alt: _sdT('Picture description changed'), picture: _sdT('Picture replaced'),
            link: _sdT('Link changed'), look: _sdT('{what} look changed'), other: _sdT('{what} changed'),
            look_rows: _sdT('Look changed ({count} elements)'), other_rows: _sdT('Other changes ({count})'),
            place_named: _sdT('{what}: {name}'),
            kind_element: _sdT('Element'), kind_section: _sdT('Section'), kind_header: _sdT('Header'), kind_footer: _sdT('Footer'),
            kind_menu: _sdT('Menu'), kind_heading: _sdT('Heading'), kind_text: _sdT('Text'), kind_picture: _sdT('Picture'),
            kind_video: _sdT('Video'), kind_link: _sdT('Link'), kind_button: _sdT('Button'), kind_icon: _sdT('Icon'),
            kind_list: _sdT('List'), kind_list_item: _sdT('List item'), kind_form: _sdT('Form'), kind_box: _sdT('Box'),
            kind_container: _sdT('Container'), kind_row: _sdT('Row'), kind_column: _sdT('Column'), kind_component: _sdT('Component')
        };
        return w.texts;
    }

    // ── Trees ──
    function treeOf(s) {
        if (s.kind === 'now') return Promise.resolve(nowTree());
        if (s.kind === 'live') {
            if (!hasLive()) return Promise.resolve(null);
            if (w.page) return Promise.resolve(L.liveTree(w.page.baseline));
            s = { kind: 'rev', no: w.revisions[0].revision_no };
        }
        return K._sdRevisionFetch(w.pageId, s.no).then(function (rev) { return rev ? rev.tree : null; });
    }

    // Shared components a tree holds that the cache lacks are fetched first:
    // the editor's own drawing and the markup diff draw nothing for them.
    function prefetch(trees) {
        return new Promise(function (resolve) {
            var ids = {};
            trees.forEach(function (t) { if (t) K.collectSharedIds(t, ids); });
            var cache = K.sharedCache || {};
            var missing = Object.keys(ids).map(Number).filter(function (id) { return id > 0 && !cache[id]; });
            K.prefetchShared(missing, function () { resolve(); });
        });
    }

    function markupOf(t) {
        var nameEl = document.querySelector('[name="name"]');
        var bcEl = document.querySelector('[name="additional_body_classes"]');
        return K.generateHTML(nameEl ? nameEl.value.trim() : '', bcEl ? bcEl.value.trim() : '', t);
    }

    // ── The window ──
    function open(opts) {
        opts = opts || {};
        close();
        var pageId = parseInt(opts.pageId, 10) || 0;
        w = {
            pageId: pageId, page: pageOf(pageId), opts: opts, revisions: [],
            sides: { before: L.side(opts.left) || { kind: 'live', no: 0 }, after: L.side(opts.right) || { kind: 'now', no: 0 } },
            got: { before: null, after: null }, read: null, look: null,
            prefs: readPrefs(), pos: 50, pics: { before: {}, after: {} }, html: {}, code: false, seq: 0,
            narrow: window.matchMedia ? window.matchMedia('(max-width: 767.98px)') : null,
            chosen: null, list: false, back: document.activeElement, host: modalHost()
        };
        w.rows = [];
        build();
        var list = pageId > 0
            ? K._pgApiPost('revisions_list', { page_id: pageId }).then(function (res) {
                return (res && res.status === 'success' && Array.isArray(res.revisions)) ? res.revisions : [];
            }, function () { return []; })
            : Promise.resolve([]);
        list.then(function (rows) {
            if (!w) return;
            w.revisions = rows;
            load();
        });
    }

    // A modal dialog open under the window (the Publish sheet) makes the
    // rest of the page inert: the window is placed inside it.
    function modalHost() {
        var open = document.querySelectorAll('dialog[open]');
        for (var i = open.length - 1; i >= 0; i--) {
            try { if (open[i].matches(':modal')) return open[i]; } catch (e) { return open[i]; }
        }
        return null;
    }

    function build() {
        var o = w.opts;
        var overlay = document.createElement('div');
        overlay.id = 'sd-rev-overlay';
        overlay.className = 'sd-fr-overlay sd-rev-overlay';
        var title = o.history
            ? esc(_sdT('Revision #{var}', o.history)) + ' <span class="sd-rev-who sd-cmp-who"></span>'
            : esc(_sdT('Compare with the live page')) + ' <span class="sd-rev-who">' + esc((w.page && w.page.page_name) || '') + '</span>';
        var refusal = K._sdRevisionLoadRefusal();
        overlay.innerHTML =
            '<div class="sd-fr-modal sd-rev-modal" role="dialog" aria-label="' + esc(_sdT('Compare')) + '">' +
                '<div class="sd-fr-header">' +
                    '<span class="bi bi-arrow-left-right me-3"></span><span class="sd-cmp-title">' + title + '</span>' +
                    '<span class="sd-cmp-title-short">' + esc(_sdT('Compare')) + '</span>' +
                    '<div class="sd-rev-tabs" role="tablist">' +
                        '<button type="button" class="sd-rev-tab is-active" role="tab" aria-selected="true" data-view="look">' + esc(_sdT('Look')) + '</button>' +
                        '<button type="button" class="sd-rev-tab" role="tab" aria-selected="false" data-view="code">' + esc(_sdT('Code')) + '</button>' +
                    '</div>' +
                    '<button type="button" class="sd-fr-close" data-cmp="close" aria-label="' + esc(_sdT('Close')) + '"><span class="bi bi-x-lg"></span></button>' +
                '</div>' +
                '<div class="sd-rev-body">' +
                    '<div class="sd-rev-view sd-cmp-look">' +
                        '<div class="sd-cmp-line">' +
                            '<div class="sd-cmp-side" data-side="before"></div>' +
                            '<div class="sd-cmp-seg" data-seg="form" role="group" aria-label="' + esc(_sdT('Form')) + '">' +
                                '<button type="button" data-v="slider">' + esc(_sdT('Slider')) + '</button>' +
                                '<button type="button" data-v="side">' + esc(_sdT('Side by side')) + '</button>' +
                            '</div>' +
                            '<div class="sd-cmp-seg" data-seg="width" role="group" aria-label="' + esc(_sdT('Width')) + '">' +
                                '<button type="button" data-v="desktop">' + esc(_sdT('Desktop')) + '</button>' +
                                '<button type="button" data-v="phone">' + esc(_sdT('Phone')) + '</button>' +
                            '</div>' +
                            '<div class="sd-cmp-side" data-side="after"></div>' +
                        '</div>' +
                        '<div class="sd-cmp-note" hidden></div>' +
                        '<div class="sd-cmp-main"><div class="sd-cmp-stage">' +
                            '<div class="sd-cmp-scroll"><div class="sd-cmp-pics">' +
                                SIDES.map(function (k) {
                                    return '<div class="sd-cmp-pic" data-side="' + k + '"><div class="sd-cmp-cap"></div>' +
                                        '<div class="sd-cmp-box"><div class="sd-cmp-state"></div></div></div>';
                                }).join('') +
                            '</div></div>' +
                            '<div class="sd-cmp-handle" hidden><div class="sd-cmp-grip" tabindex="0" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" aria-label="' +
                                esc(_sdT('Compare handle')) + '"></div></div>' +
                        '</div>' +
                        '<aside class="sd-cmp-list" aria-label="' + esc(_sdT('What changed')) + '">' +
                            '<div class="sd-cmp-list-head"><span>' + esc(_sdT('What changed')) + '</span><em></em></div>' +
                            '<div class="sd-cmp-rows"><p class="sd-cmp-empty">' + esc(_sdT('Reading the two versions…')) + '</p></div>' +
                        '</aside></div>' +
                    '</div>' +
                    '<div class="sd-rev-view sd-rev-view-code" hidden><pre class="sd-rev-diff"></pre></div>' +
                '</div>' +
                '<div class="sd-fr-footer">' +
                    '<span class="sd-rev-summary sd-cmp-foot">' + esc(_sdT('Reading the two versions…')) + '</span>' +
                    '<button type="button" class="sd-fr-btn sd-cmp-swap" data-cmp="list" hidden></button>' +
                    '<button type="button" class="sd-fr-btn" data-cmp="close">' + esc(_sdT('Close')) + '</button>' +
                    (o.history ? '<button type="button" class="sd-fr-btn sd-fr-btn-primary" data-cmp="load"' + (refusal ? ' disabled title="' + esc(refusal) + '"' : '') + '>' +
                        esc(_sdT('Load this revision')) + '</button>' : '') +
                '</div>' +
            '</div>';
        (w.host || document.body).appendChild(overlay);
        if (w.host) w.host.classList.add('sd-cmp-host');
        w.el = overlay;
        w.q = function (sel) { return overlay.querySelector(sel); };
        wire();
    }

    function wire() {
        var el = w.el;
        el.querySelectorAll('[data-cmp="close"]').forEach(function (b) { b.addEventListener('click', close); });
        el.addEventListener('mousedown', function (ev) { if (ev.target === el) close(); });
        // Escape closes the window only, never the dialog under it.
        w.onKey = function (ev) {
            if (ev.key !== 'Escape' || ev.defaultPrevented) return;
            ev.preventDefault();
            ev.stopPropagation();
            close();
        };
        document.addEventListener('keydown', w.onKey, true);
        w.onCancel = function (ev) { ev.preventDefault(); };
        if (w.host) w.host.addEventListener('cancel', w.onCancel);
        w.q('[data-cmp="list"]').addEventListener('click', function () { w.list = !w.list; layout(); });
        w.q('.sd-cmp-rows').addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-row]');
            if (b) choose(parseInt(b.dataset.row, 10), b.dataset.node || '');
        });
        w.onResize = function () { layout(); };
        window.addEventListener('resize', w.onResize);
        if (w.narrow && w.narrow.addEventListener) w.narrow.addEventListener('change', w.onResize);

        el.querySelectorAll('.sd-rev-tab').forEach(function (t) {
            t.addEventListener('click', function () {
                el.querySelectorAll('.sd-rev-tab').forEach(function (x) {
                    x.classList.toggle('is-active', x === t);
                    x.setAttribute('aria-selected', x === t ? 'true' : 'false');
                });
                w.q('.sd-cmp-look').hidden = (t.dataset.view !== 'look');
                w.q('.sd-rev-view-code').hidden = (t.dataset.view !== 'code');
                if (t.dataset.view === 'code') drawCode();
                else layout();
            });
        });
        el.querySelectorAll('.sd-cmp-seg button').forEach(function (b) {
            b.addEventListener('click', function () {
                var seg = b.parentNode.dataset.seg;
                w.prefs[seg] = b.dataset.v;
                keepPrefs();
                layout();
            });
        });
        var loadBtn = w.q('[data-cmp="load"]');
        if (loadBtn) loadBtn.addEventListener('click', function () {
            var k = L.loadSide(w.sides, w.opts.history);
            var s = k ? w.sides[k] : null;
            if (loadBtn.disabled || !s) return;
            var pageId = w.pageId, done = w.opts.done;
            close();
            K._sdRevisionRestore(pageId, s.no, done);
        });
        wireHandle();
    }

    function close() {
        if (!w) return;
        document.removeEventListener('keydown', w.onKey, true);
        if (w.host) {
            w.host.removeEventListener('cancel', w.onCancel);
            w.host.classList.remove('sd-cmp-host');
        }
        window.removeEventListener('resize', w.onResize);
        if (w.narrow && w.narrow.removeEventListener) w.narrow.removeEventListener('change', w.onResize);
        SIDES.forEach(function (k) { clearTimeout(w.pics[k].timer); });
        if (w.el) w.el.remove();
        var back = w.back;
        w = null;
        if (back && back.isConnected && typeof back.focus === 'function') back.focus();
    }

    // The two trees, the reader, then the pictures one after the other.
    function load() {
        var seq = ++w.seq;
        drawSides();
        Promise.all([treeOf(w.sides.before), treeOf(w.sides.after)]).then(function (trees) {
            return prefetch(trees).then(function () { return trees; });
        }).then(function (trees) {
            if (!w || seq !== w.seq) return;
            w.got.before = trees[0];
            w.got.after = trees[1];
            w.html = {};
            w.code = false;
            w.read = C.read(trees[0], trees[1], texts());
            w.rows = (w.read.state === 'changed') ? C.rows(w.read.changes, texts()) : [];
            w.chosen = null;
            w.q('.sd-cmp-foot').textContent = footText(w.read);
            drawList();
            if (!w.q('.sd-rev-view-code').hidden) drawCode();
            layout();
            var first = (w.look.only || 'before');
            SIDES.forEach(function (k) { reset(k, k === first ? '' : nextText(w.sides[k])); });
            draw(first);
        });
    }

    function drawSides() {
        var o = w.opts;
        SIDES.forEach(function (k) {
            var s = w.sides[k];
            var box = w.q('.sd-cmp-side[data-side="' + k + '"]');
            var html;
            if (o.history) {
                var values = L.sideValues(w.revisions, !!nowTree(), hasLive());
                if (values.indexOf(L.sideValue(s)) === -1) values.unshift(L.sideValue(s));
                html = '<select class="sd-cmp-choose" aria-label="' + esc(k === 'before' ? _sdT('Left side') : _sdT('Right side')) + '">' +
                    values.map(function (v) {
                        return '<option value="' + esc(v) + '"' + (v === L.sideValue(s) ? ' selected' : '') + '>' + esc(optionLabel(v)) + '</option>';
                    }).join('') + '</select>';
            } else {
                html = '<strong>' + esc(sideName(s)) + '</strong>';
            }
            box.innerHTML = html + ' <span class="sd-cmp-detail">' + esc(sideDetail(s)) + '</span>';
            w.q('.sd-cmp-pic[data-side="' + k + '"] .sd-cmp-cap').textContent = sideName(s);
            var sel = box.querySelector('select');
            if (sel) sel.addEventListener('change', function () {
                var next = L.side(sel.value);
                if (!next) return;
                w.sides[k] = next;
                load();
            });
        });
        var who = w.q('.sd-cmp-who');
        if (who && o.history) who.textContent = sideDetail({ kind: 'rev', no: o.history });
        var loadBtn = w.q('[data-cmp="load"]');
        if (loadBtn && !K._sdRevisionLoadRefusal()) loadBtn.disabled = !L.loadSide(w.sides, o.history);
    }

    // ── Pictures ──
    function reset(k, waitText) {
        var pic = w.pics[k];
        clearTimeout(pic.timer);
        pic.status = waitText ? 'waiting' : '';
        pic.height = 0;
        var box = w.q('.sd-cmp-pic[data-side="' + k + '"] .sd-cmp-box');
        var old = box.querySelector('iframe');
        if (old) old.remove();
        pic.frame = null;
        state(k, waitText ? 'is-waiting' : '', waitText ? esc(waitText) : '');
    }

    function state(k, cls, html) {
        var el = w.q('.sd-cmp-pic[data-side="' + k + '"] .sd-cmp-state');
        el.className = 'sd-cmp-state' + (cls ? ' ' + cls : '');
        el.innerHTML = html;
        el.hidden = !cls;
        var b = el.querySelector('button');
        if (b) b.addEventListener('click', function () { draw(k); });
    }

    function other(k) { return k === 'before' ? 'after' : 'before'; }

    // After the first picture, loaded or failed, the second is drawn.
    function settled(k) {
        var o = other(k);
        if (!w.look.only && w.pics[o].status === 'waiting') draw(o);
    }

    function draw(k) {
        var seq = w.seq, s = w.sides[k], t = w.got[k], pic = w.pics[k];
        reset(k, '');
        pic.status = 'loading';
        state(k, 'is-loading', esc(drawingText(s)));
        pic.timer = setTimeout(function () { fail(k, seq); }, WAIT_MS);
        var key = L.sideValue(s);
        var got = w.html[key] ? Promise.resolve(w.html[key]) : serverHtml(s, t).then(function (html) {
            return html ? L.withBase(html, siteBase()) : clientHtml(t);
        });
        got.then(function (html) {
            if (!w || seq !== w.seq || pic.status !== 'loading') return;
            w.html[key] = html;
            var f = document.createElement('iframe');
            f.className = 'sd-cmp-frame';
            f.setAttribute('sandbox', 'allow-same-origin');
            f.setAttribute('tabindex', '-1');
            f.setAttribute('aria-hidden', 'true');
            f.style.width = L.width(w.look.width) + 'px';
            f.addEventListener('load', function () {
                if (!w || seq !== w.seq || pic.status !== 'loading') return;
                clearTimeout(pic.timer);
                pic.status = 'loaded';
                state(k, '', '');
                measure(k);
                layout();
                settled(k);
            });
            pic.frame = f;
            // Filled before it is placed: a frame placed empty first fires a
            // load for about:blank, which would be measured as the page.
            f.srcdoc = html;
            w.q('.sd-cmp-pic[data-side="' + k + '"] .sd-cmp-box').appendChild(f);
        }, function () { fail(k, seq); });
    }

    function fail(k, seq) {
        if (!w || seq !== w.seq || w.pics[k].status !== 'loading') return;
        var pic = w.pics[k];
        clearTimeout(pic.timer);
        if (pic.frame) pic.frame.remove();
        pic.frame = null;
        pic.status = 'failed';
        state(k, 'is-failed', '<span>' + esc(failedText(w.sides[k])) + '</span> <button type="button" class="sd-fr-btn">' + esc(_sdT('Try again')) + '</button>');
        settled(k);
    }

    function serverHtml(s, t) {
        if (!t || !(w.pageId > 0)) return Promise.resolve('');
        var body = { page_id: w.pageId, tree_json: JSON.stringify(t), widgets: [] };
        // The draft shows the system widgets as the editor holds them, as
        // the Preview does; every other side shows the stored ones.
        if (s.kind === 'now' && w.page) {
            var seo = K._sdSeoCheckBody(w.page, t);
            body.widgets = seo.widgets || [];
        }
        return K._pgApiPost('compare_render', body).then(function (res) {
            return (res && res.status === 'success' && typeof res.html === 'string') ? res.html : '';
        }, function () { return ''; });
    }

    function clientHtml(t) {
        return t ? K._sdPreviewDocHtml(null, markupOf(t)) : '';
    }

    // The page's height at the width it is drawn at.
    function measure(k) {
        var pic = w.pics[k], f = pic.frame;
        if (!f) return;
        f.style.width = L.width(w.look.width) + 'px';
        var h = 0;
        try {
            var d = f.contentDocument;
            if (d && d.documentElement) {
                f.style.height = '10px';
                h = Math.max(d.documentElement.scrollHeight, d.body ? d.body.scrollHeight : 0);
            }
        } catch (e) { h = 0; }
        pic.height = h > 0 ? h : 2000;
        pic.width = L.width(w.look.width);
        f.style.height = pic.height + 'px';
    }

    // ── Layout ──
    function layout() {
        if (!w || !w.read) return;
        var narrow = !!(w.narrow && w.narrow.matches);
        var look = w.look = L.layout(w.read.state, w.prefs, narrow);
        var el = w.el;
        el.classList.toggle('sd-cmp-is-narrow', narrow);
        if (!narrow) w.list = false;
        el.classList.toggle('sd-cmp-is-list', w.list);
        var swap = w.q('[data-cmp="list"]');
        swap.hidden = !narrow;
        swap.textContent = w.list ? _sdT('The pictures') : _sdT('What changed') + ' \u00b7 ' + w.rows.length;
        el.querySelectorAll('.sd-cmp-seg').forEach(function (seg) {
            var name = seg.dataset.seg;
            seg.hidden = (name === 'form') ? !look.forms : !look.widths;
            seg.querySelectorAll('button').forEach(function (b) {
                var on = b.dataset.v === look[name];
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        });
        var note = noteText(look.note);
        w.q('.sd-cmp-note').hidden = !note;
        w.q('.sd-cmp-note').textContent = note;
        w.q('.sd-cmp-side[data-side="before"]').hidden = (look.only === 'after');
        w.q('.sd-cmp-side[data-side="after"]').hidden = (look.only === 'before');

        var pics = w.q('.sd-cmp-pics');
        pics.classList.toggle('is-side', !look.only && look.form === 'side');
        var scroll = w.q('.sd-cmp-scroll');
        var room = scroll.clientWidth - 32;
        var width = L.width(look.width);
        var two = !look.only && look.form === 'side';
        var s = w.scale = L.scale(two ? (room - 16) / 2 : room, width);
        var tall = 0;
        SIDES.forEach(function (k) {
            var pic = w.pics[k];
            var node = w.q('.sd-cmp-pic[data-side="' + k + '"]');
            node.hidden = !!look.only && look.only !== k;
            if (pic.frame && pic.width !== width) measure(k);
            var h = (pic.status === 'loaded') ? pic.height * s : 0;
            tall = Math.max(tall, h);
            var box = node.querySelector('.sd-cmp-box');
            box.style.width = Math.round(width * s) + 'px';
            box.style.height = h ? Math.round(h) + 'px' : '';
            if (pic.frame) pic.frame.style.transform = 'scale(' + s + ')';
        });
        var picH = Math.max(Math.round(tall), 240);
        SIDES.forEach(function (k) {
            var node = w.q('.sd-cmp-pic[data-side="' + k + '"]');
            node.style.width = Math.round(width * s) + 'px';
            node.style.height = (look.form === 'slider' || look.only) ? picH + 'px' : '';
        });
        pics.style.width = two ? '' : Math.round(width * s) + 'px';
        pics.style.height = two ? '' : picH + 'px';
        w.q('.sd-cmp-handle').hidden = !look.handle;
        place();
        marks(false);
    }

    // ── The handle ──
    function place() {
        var look = w.look;
        var after = w.q('.sd-cmp-pic[data-side="after"]');
        after.style.clipPath = look.handle ? 'inset(0 0 0 ' + w.pos + '%)' : '';
        if (!look.handle) return;
        var stage = w.q('.sd-cmp-stage').getBoundingClientRect();
        var r = w.q('.sd-cmp-pics').getBoundingClientRect();
        var handle = w.q('.sd-cmp-handle');
        handle.style.left = Math.round(r.left - stage.left + r.width * w.pos / 100) + 'px';
        var grip = w.q('.sd-cmp-grip');
        grip.setAttribute('aria-valuenow', String(Math.round(w.pos)));
        if (w.sides.after.kind === 'now') grip.setAttribute('aria-valuetext', _sdT('{var}% of the draft shows', Math.round(100 - w.pos)));
        else grip.removeAttribute('aria-valuetext');
    }

    function moveTo(clientX) {
        var r = w.q('.sd-cmp-pics').getBoundingClientRect();
        if (r.width <= 0) return;
        w.pos = L.clamp((clientX - r.left) / r.width * 100);
        if (!w.frame) {
            w.frame = window.requestAnimationFrame(function () { if (w) { w.frame = 0; place(); } });
        }
    }

    function wireHandle() {
        var handle = w.q('.sd-cmp-handle');
        var grip = w.q('.sd-cmp-grip');
        var dragging = false;
        handle.addEventListener('pointerdown', function (ev) {
            dragging = true;
            handle.setPointerCapture(ev.pointerId);
            grip.focus();
            ev.preventDefault();
        });
        handle.addEventListener('pointermove', function (ev) { if (dragging) moveTo(ev.clientX); });
        var stop = function () { dragging = false; };
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
        w.q('.sd-cmp-pics').addEventListener('click', function (ev) {
            if (w.look && w.look.handle) moveTo(ev.clientX);
        });
        grip.addEventListener('keydown', function (ev) {
            var pos = L.key(w.pos, ev.key, ev.shiftKey);
            if (pos === null) return;
            ev.preventDefault();
            w.pos = pos;
            place();
        });
        w.q('.sd-cmp-scroll').addEventListener('scroll', function () { if (w.look && w.look.handle) place(); }, { passive: true });
    }

    // ── What changed ──
    function drawList() {
        var box = w.q('.sd-cmp-rows');
        w.q('.sd-cmp-list-head em').textContent = w.rows.length ? String(w.rows.length) : '';
        var st = w.read.state;
        if (st !== 'changed') {
            var msg = (st === 'new') ? _sdT('The whole page is new.')
                : (st === 'same') ? _sdT('Nothing has changed.')
                : _sdT('The changes could not be listed. The two pictures are still the two pages.');
            box.innerHTML = '<p class="sd-cmp-empty">' + esc(msg) + '</p>';
            return;
        }
        var at = w.chosen ? w.chosen.i : -1;
        box.innerHTML = w.rows.map(function (r, i) {
            var on = (i === at);
            var words = L.wordsOf(r);
            var many = r.changes.length > 1 || r.code === 'other_rows' || r.code === 'look_rows';
            var more = '';
            if (on && words) {
                more = '<div class="sd-cmp-words"><i>' + esc(_sdT('Before')) + '</i><span>' + esc(words.before) + '</span>' +
                    '<i>' + esc(_sdT('Now')) + '</i><span>' + esc(words.after) + '</span></div>';
            } else if (on && many) {
                more = '<ul class="sd-cmp-names">' + r.changes.map(function (c) {
                    return '<li><button type="button" data-row="' + i + '" data-node="' + esc(c.node) + '"' +
                        (w.chosen.node === c.node ? ' class="is-current" aria-current="true"' : '') + '>' + esc(L.nameOf(c)) + '</button></li>';
                }).join('') + '</ul>';
            }
            var chevron = (words || many) ? '<span class="bi ' + (on ? 'bi-chevron-up' : 'bi-chevron-down') + ' sd-cmp-chev" aria-hidden="true"></span>' : '';
            return '<div class="sd-cmp-row' + (on ? ' is-on' : '') + '">' +
                '<button type="button" class="sd-cmp-row-btn" data-row="' + i + '"' + ((words || many) ? ' aria-expanded="' + on + '"' : '') + '>' +
                    '<span class="bi ' + L.icon(r) + '" aria-hidden="true"></span>' +
                    '<span class="sd-cmp-row-text">' + esc(r.text) +
                        (r.place ? '<small>' + esc(_sdT('in {var}', r.place)) + '</small>' : '') + '</span>' + chevron +
                '</button>' + more + '</div>';
        }).join('');
    }

    // A row chosen: the handle shows its side, the list opens it, the
    // pictures go to its place. A name of an opened row goes to its element.
    function choose(i, node) {
        var r = w.rows[i];
        if (!r) return;
        w.chosen = { i: i, node: node || '' };
        w.list = false;
        if (w.look && w.look.handle) {
            w.pos = L.handleFor(L.sideOf(r), w.pos);
            place();
        }
        drawList();
        layout();
        marks(true);
    }

    // The element's boxes in a picture, at the picture's scale.
    function boxesIn(k, ids) {
        var f = w.pics[k].frame, out = [];
        if (!f || w.pics[k].status !== 'loaded') return out;
        var d;
        try { d = f.contentDocument; } catch (e) { d = null; }
        if (!d) return out;
        var by = {};
        d.querySelectorAll('[data-pg-node]').forEach(function (el) {
            var id = el.getAttribute('data-pg-node');
            if (!by[id]) by[id] = el;
        });
        ids.forEach(function (id) {
            var el = by[id] || d.getElementById(id);
            if (!el) return;
            var r = el.getBoundingClientRect();
            if (r.width <= 0 && r.height <= 0) return;
            out.push({ top: r.top * w.scale, left: r.left * w.scale, width: r.width * w.scale, height: r.height * w.scale });
        });
        return out;
    }

    // The outlines of the chosen row on the pictures that carry its place,
    // the two pictures lined up on it and, when asked, scrolled to it.
    function marks(scroll) {
        if (!w || !w.look) return;
        w.el.querySelectorAll('.sd-cmp-mark').forEach(function (m) { m.remove(); });
        var r = w.chosen ? w.rows[w.chosen.i] : null;
        var side = r ? L.sideOf(r) : '';
        var ids = r ? (w.chosen.node ? [w.chosen.node] : r.changes.map(function (c) { return c.node; })) : [];
        var ys = {};
        SIDES.forEach(function (k) {
            var node = w.q('.sd-cmp-pic[data-side="' + k + '"]');
            node.style.translate = '';
            if (!r || (side !== 'both' && side !== k) || (w.look.only && w.look.only !== k)) return;
            var box = node.querySelector('.sd-cmp-box');
            boxesIn(k, ids).forEach(function (b, n) {
                if (n === 0) ys[k] = b.top;
                var m = document.createElement('div');
                m.className = 'sd-cmp-mark';
                m.style.cssText = 'top:' + Math.round(b.top) + 'px;left:' + Math.round(b.left) + 'px;width:' +
                    Math.round(b.width) + 'px;height:' + Math.round(b.height) + 'px';
                if (n === 0) m.innerHTML = '<span>' + esc(r.text) + '</span>';
                box.appendChild(m);
            });
        });
        var a = L.align(ys.before, ys.after);
        if (!a) return;
        SIDES.forEach(function (k) {
            if (a[k]) w.q('.sd-cmp-pic[data-side="' + k + '"]').style.translate = '0 ' + Math.round(a[k]) + 'px';
        });
        if (scroll) w.q('.sd-cmp-scroll').scrollTop = Math.max(0, Math.round(a.top) - 48);
    }

    // ── Code ──
    function drawCode() {
        if (w.code || !w.read) return;
        w.code = true;
        var a = w.got.before ? markupOf(w.got.before) : '';
        var b = w.got.after ? markupOf(w.got.after) : '';
        var diff = K._sdLineDiff(a ? a.split('\n') : [], b ? b.split('\n') : []);
        var sum = diff.same ? _sdT('The two versions print the same HTML.')
            : _sdT('{var:1} lines added, {var:2} removed.', [diff.added, diff.removed]);
        w.q('.sd-rev-diff').innerHTML = '<span class="sd-rev-fold">' + esc(sum) + '</span>\n' + K._sdLineDiffHtml(diff);
    }

    return { open: open, close: close };
});
