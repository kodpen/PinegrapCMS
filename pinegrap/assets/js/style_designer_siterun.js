/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the run of the New design window's "Describe it"
 * start. The plan of pages approved there waits in this browser
 * (localStorage pg_site_plan, assets/js/designer_describe.js) with one empty
 * sketch page each; the editor opens on them (?start=describe) and this part
 * makes them one after the other: the page's boxes drawn from the plan's
 * words ("Describe the page"), the page built as a draft tab ("Build the
 * page"), then its texts written section by section ("Write the texts").
 * The header and footer are made once, from the plan's menu, and placed on
 * every page; the theme came with the address (?answers=). A line at the
 * foot of the screen says how far the run is, with Stop, which keeps what
 * is made; a refused request ends the run with its reason. Nothing is
 * saved: Save keeps the drafts, as after "Build the page". Not needed to
 * open a page: the core runs this file when such a design opens
 * (_sdSiteRun(), pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('siterun', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    var KEY = 'pg_site_plan';
    // What "Describe the page" takes (pg_sketch_ai_input() in
    // includes/fn/sketch_ai.php).
    var MAX_WORDS = 2000;
    var STEPS = ['draw', 'build', 'write'];

    var host = null;
    var run = null;
    var card = null;

    // ── Pure ──

    // The words a page is drawn from: the business in a line, the page's
    // name and purpose, its sections in order between the header and the
    // footer. `t` holds the labels in the editor's language. At most
    // MAX_WORDS characters; the business's words are cut first.
    function words(page, brief, t) {
        var clean = function (v) { return String(v == null ? '' : v).replace(/\s+/g, ' ').trim(); };
        var secs = (Array.isArray(page && page.sections) ? page.sections : []).map(clean).filter(Boolean);
        var lines = [];
        lines.push(t.page + ': ' + clean(page && page.name) + (clean(page && page.purpose) ? '. ' + clean(page.purpose) : ''));
        lines.push(t.sections + ': ' + [t.header].concat(secs.map(function (s, i) { return (i + 1) + '. ' + s; })).concat([t.footer]).join('; '));
        var tail = lines.join('\n');
        var name = clean(brief && brief.name);
        var what = clean(brief && brief.what);
        var room = MAX_WORDS - tail.length - name.length - 4;
        if (room < 0) return tail.slice(0, MAX_WORDS);
        if (what.length > room) what = what.slice(0, Math.max(0, room - 1)).replace(/\s+\S*$/, '') + '…';
        var head = name + (what ? ': ' + what : '');
        return (head ? head + '\n' : '') + tail;
    }

    // The header's and the footer's pages from the plan: the main menu's
    // pages above, the main and the footer's below, in the plan's order.
    function menus(pages) {
        var link = function (p) { return { name: String(p.name || ''), address: String(p.address || '') }; };
        var list = Array.isArray(pages) ? pages : [];
        return {
            header: list.filter(function (p) { return p && p.menu === 'main'; }).map(link),
            footer: list.filter(function (p) { return p && (p.menu === 'main' || p.menu === 'footer'); }).map(link)
        };
    }

    // Each plan page with the sketch page it waits as: by address, else
    // the one at the same place; sketch pages in their stored order.
    function pair(planPages, sketchPages) {
        var plan = Array.isArray(planPages) ? planPages : [];
        var used = {};
        var out = [];
        (Array.isArray(sketchPages) ? sketchPages : []).forEach(function (sp, i) {
            var address = sp && sp.details ? String(sp.details.address || '') : '';
            var hit = -1;
            plan.forEach(function (p, k) { if (hit === -1 && !used[k] && address && p && p.address === address) hit = k; });
            if (hit === -1 && plan[i] && !used[i] && !(sp.details && sp.details.address)) hit = i;
            if (hit === -1) return;
            used[hit] = true;
            out.push({ plan: plan[hit], id: sp.id });
        });
        return out;
    }

    // Where a page's run starts again: no box yet, drawn but not built,
    // or built (its texts).
    function stepOf(page, built) {
        if (!page || !page.boxes || !page.boxes.length) return 'draw';
        return built ? 'write' : 'build';
    }

    // How far the run is, 0 to 100: done pages, then the page's step.
    function percent(index, total, step) {
        if (!total) return 0;
        var at = Math.max(0, STEPS.indexOf(step));
        return Math.max(0, Math.min(100, Math.round((index + at / STEPS.length) * 100 / total)));
    }

    // ── The run ──

    // The approved plan, taken once: a reload does not make the site
    // again (and spend the day's drafts a second time).
    function take() {
        var plan = null;
        try {
            plan = JSON.parse(window.localStorage.getItem(KEY) || 'null');
            window.localStorage.removeItem(KEY);
        } catch (e) { plan = null; }
        return (plan && Array.isArray(plan.pages) && plan.pages.length) ? plan : null;
    }

    function labels() {
        return { page: _sdT('Page'), sections: _sdT('Sections from top to bottom'), header: _sdT('Header'), footer: _sdT('Footer') };
    }

    function start(h) {
        host = h;
        if (run) return;
        var plan = take();
        if (!plan) return;
        var list = pair(plan.pages, host.pages());
        if (!list.length) return;
        var m = menus(plan.pages);
        run = { plan: plan, list: list, i: 0, made: 0, step: '', tok: 0, end: null, shared: {}, site: { name: String((plan.brief && plan.brief.name) || ''), header: m.header, footer: m.footer } };
        if (!host.aiOn()) { finish('rose', _sdT('Pinegrap AI is off.'), false); return; }
        next();
    }

    // The build host with the run's header and footer: the plan's menu,
    // made once and placed on every page after the first.
    function buildHost() {
        var b = host.build();
        var site = b.site;
        var shared = b.shared;
        b.site = function () {
            var s = site();
            return { name: run.site.name || s.name, path: s.path, pages: run.site.header, footer: run.site.footer };
        };
        b.shared = function (kind, built) {
            var have = run.shared[kind];
            if (have) return Promise.resolve(host.ref(have.id, have.name));
            return shared(kind, built).then(function (ref) {
                if (ref && ref.props) run.shared[kind] = { id: ref.props.sharedId, name: ref.props.sharedName };
                return ref;
            });
        };
        return b;
    }

    function current() {
        var it = run ? run.list[run.i] : null;
        var page = it ? host.page('k:' + it.id) : null;
        return page ? { it: it, page: page, frame: 'k:' + it.id } : null;
    }

    function next() {
        if (!run || run.end) return;
        var c = current();
        while (!c && run.i < run.list.length) { run.i++; c = current(); }
        if (!c) { finish('teal', '', false); return; }
        var tok = ++run.tok;
        var mine = function () { return run && !run.end && run.tok === tok; };
        // The plan's name and purpose as the page's title and description,
        // unless "Page details" already holds them.
        var d = c.page.details || (c.page.details = {});
        if (!d.title) d.title = String(c.it.plan.name || '').slice(0, 255);
        if (!d.description) d.description = String(c.it.plan.purpose || '').slice(0, 255);
        host.zoom(c.frame);
        var built = !!(c.page.built && host.ai().pageOf(c.page.built.uid));
        run.step = stepOf(c.page, built);
        draw();
        var settle = function (r, why) {
            if (!mine()) return false;
            if (r && r.off) { finish('rose', _sdT('Pinegrap AI is off.'), false); return false; }
            if (r && r.stopped) { finish('blue', '', true); return false; }
            if (!r || !r.ok) { finish('rose', (r && r.message) || why, true); return false; }
            return true;
        };
        var write = function () {
            run.step = 'write';
            draw();
            host.part('sketchai').then(function (api) { return api.write(host.ai(), c.frame); }).then(function (r) {
                if (!settle(r, _sdT('The texts of {var:1} could not be written.', host.name(c.page)))) return;
                run.i++;
                next();
            }, function () { settle(null, _sdT('This part of the editor could not be loaded. Check the connection and try again.')); });
        };
        var build = function () {
            run.step = 'build';
            draw();
            host.part('sketchbuild').then(function (api) { return api.auto(buildHost(), c.frame); }).then(function (r) {
                if (r && r.refused) r.message = _sdT('You may not add pages to this design. The sketch is kept; someone who may can build it.');
                if (!settle(r, _sdT('{var:1} could not be built. The sketch is kept as it is.', host.name(c.page)))) return;
                run.made++;
                write();
            }, function () { settle(null, _sdT('This part of the editor could not be loaded. Check the connection and try again.')); });
        };
        if (run.step === 'write') { write(); return; }
        if (run.step === 'build') { build(); return; }
        host.part('sketchai').then(function (api) { return api.draw(host.ai(), c.frame, words(c.it.plan, run.plan.brief, labels())); }).then(function (r) {
            if (!settle(r, _sdT('The sketch could not be drawn. Nothing was changed.'))) return;
            build();
        }, function () { settle(null, _sdT('This part of the editor could not be loaded. Check the connection and try again.')); });
    }

    // Stop: what is under way stops as its own Stop does (the drawing
    // drops its answer, the writing keeps the texts written); the pages
    // made stay.
    function stop() {
        if (!run || run.end) return;
        if (run.step === 'build') { run.tok++; finish('blue', '', true); return; }
        host.part('sketchai').then(function (api) { api.stop(); });
    }

    function finish(tone, why, more) {
        if (!run) return;
        run.end = { tone: tone, why: why || '', more: !!more && host.aiOn() };
        draw();
        host.announce(text().title);
    }

    // "Go on" after Stop or a refusal: the run starts again where it was.
    function resume() {
        if (!run || !run.end) return;
        run.end = null;
        next();
    }

    function close() {
        if (card && card.parentNode) card.parentNode.removeChild(card);
        card = null;
        if (run && run.end) run = null;
    }

    // ── The line ──

    function text() {
        var total = run.list.length;
        var made = run.made;
        if (!run.end) {
            var c = current();
            var name = c ? host.name(c.page) : '';
            var doing = { draw: _sdT('Drawing the sketch of {var:1}', name), build: _sdT('Building {var:1} as a draft', name), write: _sdT('Writing the texts of {var:1}', name) }[run.step] || '';
            return { title: _sdT('Making your site: page {var:1} of {var:2}', [Math.min(run.i + 1, total), total]), line: doing };
        }
        if (run.end.tone === 'teal') {
            return { title: made === 1 ? _sdT('Your site is made: 1 page, as a draft.') : _sdT('Your site is made: {var:1} pages, as drafts.', made), line: _sdT('Save keeps them; Publish puts them on the site.') };
        }
        var kept = made === 1 ? _sdT('1 page is made; the others wait as sketches.') : _sdT('{var:1} pages are made; the others wait as sketches.', made);
        if (run.end.tone === 'blue') return { title: _sdT('Stopped.'), line: kept };
        return { title: run.end.why, line: kept };
    }

    function button(cls, label, act) {
        var b = host.el('button', 'btn btn-sm ' + cls, label);
        b.type = 'button';
        b.addEventListener('click', function (event) { event.stopPropagation(); act(); });
        return b;
    }

    function draw() {
        if (!run) return;
        if (!card) {
            card = host.el('div', 'sd-sr');
            card.setAttribute('role', 'status');
            card.setAttribute('aria-live', 'polite');
        }
        if (!card.parentNode) document.body.appendChild(card);
        var t = text();
        var end = run.end;
        var tone = end ? end.tone : 'blue';
        var icon = end ? { teal: 'bi-check-lg', blue: 'bi-pause-fill', rose: 'bi-x-lg' }[tone] : 'bi-arrow-repeat sd-sketch-spin';
        card.className = 'sd-sr' + (end ? ' sd-sr-end' : '');
        card.innerHTML = '<span class="sd-skb-tile sd-skb-' + tone + '" aria-hidden="true"><span class="bi ' + icon + '"></span></span>'
            + '<div class="sd-sr-text"><div class="sd-sr-title">' + esc(t.title) + '</div>'
            + (t.line ? '<div class="sd-sr-line">' + esc(t.line) + '</div>' : '')
            + (end ? '' : '<div class="sd-sr-bar"><span></span></div>') + '</div>';
        if (!end) {
            card.querySelector('.sd-sr-bar span').style.width = percent(run.i, run.list.length, run.step) + '%';
            card.appendChild(button('btn-outline theme-secondary', _sdT('Stop'), stop));
            return;
        }
        var acts = host.el('div', 'sd-sr-acts');
        if (end.more) acts.appendChild(button('btn-outline theme-secondary', end.tone === 'rose' ? _sdT('Try again') : _sdT('Go on'), resume));
        acts.appendChild(button(end.tone === 'teal' ? 'btn-solid theme-primary' : 'btn-outline theme-secondary', end.tone === 'teal' ? _sdT('Got it') : _sdT('Close'), close));
        card.appendChild(acts);
    }

    return {
        start: start,
        stop: stop,
        running: function () { return !!(run && !run.end); },
        pure: { words: words, menus: menus, pair: pair, stepOf: stepOf, percent: percent }
    };
});
