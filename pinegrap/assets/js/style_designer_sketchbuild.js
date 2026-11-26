/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - "Build the page" part of the Prototype view's Sketch
 * tool. It reads a sketch page (window.PGSketchReader) and marks the boxes
 * the reading is not sure of: an amber question mark on the chip and the
 * "n to check" pill in the page's head. "Build the page" asks those boxes
 * in one list, then builds a draft page of the design from the starter
 * blocks (window.PGSketchBuilder): the header and footer as the design's
 * shared components, catalog, blog and booking as system widgets. The
 * sketch is kept in the draft's root (props._sketch) with what each
 * section was built from, so "Build again" rebuilds only the sections whose
 * box changed and were not edited in Design, asking first when a section
 * would be removed or an edited one had its box changed. A History entry of
 * the page is written before that build runs. Not needed to open a page:
 * the core runs this file when the Sketch switch first goes on
 * (_sdPartLoad(), pg_designer_parts()) and hands it the editor through a
 * host (_sdSketchBuildHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('sketchbuild', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // The first-use line was closed in this browser.
    var SEEN_KEY = 'pg_sketch_build_seen';
    // Kinds kept as shared components: never built again, only placed or
    // taken out.
    var SHARED = { header: true, footer: true };

    var host = null;
    // The frame being built (null: none), the unsure list and the "Build
    // again" question while open, the line under each frame, the readings
    // by frame.
    var busy = null;
    var check = null;
    var ask = null;
    var results = {};
    var reads = {};

    // ── Pure ──

    // A short, stable hash of a string (FNV-1a, 32 bits, base 36).
    function hash(text) {
        var h = 0x811c9dc5;
        var s = String(text);
        for (var i = 0; i < s.length; i++) {
            h ^= s.charCodeAt(i);
            h = (h + ((h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24))) >>> 0;
        }
        return h.toString(36);
    }

    // What a sketch page's boxes are: "Build again" is offered when it
    // differs from the one the page was built from.
    function boxSig(page) {
        var boxes = (page && Array.isArray(page.boxes)) ? page.boxes.slice() : [];
        boxes.sort(function (a, b) { return a.id - b.id; });
        return hash(JSON.stringify(boxes.map(function (b) { return [b.id, b.x, b.y, b.w, b.h, b.kind || '', b.text || '']; })) + '|' + ((page && page.details && page.details.type) || ''));
    }

    // What a section is built from; its box moved alone does not change it.
    function secSig(s) {
        return hash(JSON.stringify([s.kind, s.widget || '', s.columns || 0, s.buttons || 0, !!s.picture, s.heading || '']));
    }

    // A built node as it stands, without its ids and the Layers panel's
    // open marks: an edit in Design changes it.
    function nodeFp(node) {
        return hash(JSON.stringify(node, function (k, v) { return (k === '_id' || k === '_expanded') ? undefined : v; }));
    }

    function sketchIdOf(node) {
        var id = node && node.props ? node.props._sketchId : '';
        return (id === undefined || id === null || id === '') ? '' : String(id);
    }

    // What "Build again" does with each section: `built` is what the page
    // was built from (id => {sig, fp}), `now` the fingerprint of each
    // section's node still on the page (id => fp). A section whose node was
    // edited stays; one edited whose box changed too is a clash (it stays
    // unless ticked); one not edited is built again when its box changed;
    // a new section is added and one gone from the sketch removed. A shared
    // header or footer is never built again. Asked only when a section is
    // removed or clashes.
    function plan(sections, built, now) {
        var out = { stay: [], rebuild: [], add: [], remove: [], clash: [], ask: false };
        var seen = {};
        built = built || {};
        now = now || {};
        sections.forEach(function (s) {
            var id = String(s.id);
            seen[id] = true;
            var was = built[id];
            if (!was || !Object.prototype.hasOwnProperty.call(now, id)) { out.add.push(id); return; }
            var edited = now[id] !== was.fp;
            var changed = secSig(s) !== was.sig;
            if (!changed || SHARED[s.kind]) out.stay.push(id);
            else if (edited) out.clash.push(id);
            else out.rebuild.push(id);
        });
        Object.keys(now).forEach(function (id) {
            if (!seen[id]) out.remove.push(id);
        });
        out.ask = out.remove.length > 0 || out.clash.length > 0;
        return out;
    }

    // A section kept on the page by "Build again" keeps the fingerprint it
    // was built with (`was`, id => {sig, fp}), so an edit made in Design
    // still counts as one: `secs` are the fingerprints of the nodes as they
    // stand, `keep` the kept sections (id => node). Its sig is the new one.
    function keptFp(secs, was, keep) {
        Object.keys(keep || {}).forEach(function (id) {
            if (secs[id] && was && was[id] && was[id].fp) secs[id].fp = was[id].fp;
        });
        return secs;
    }

    // The sketch the built draft was built from: the one kept in its root,
    // which an undo in Design brings back with the page; else the browser's
    // mark on the sketch page.
    function keptSig(page, root) {
        var kept = root && root.props && root.props._sketch;
        if (kept && kept.sig) return String(kept.sig);
        return (page && page.built && page.built.sig) || '';
    }

    // The page's children in the sketch's order: `ids` the sections in
    // order, `nodeFor(id)` the node of each. A node added in Design (no
    // _sketchId, or a copy of a section carrying its _sketchId) stays after
    // the section it followed; after a removed section, after the one
    // before that.
    function arrange(children, ids, nodeFor) {
        var extras = { '': [] };
        var order = [];
        var anchor = '';
        (children || []).forEach(function (c) {
            var id = sketchIdOf(c);
            if (id && !extras[id]) {
                anchor = id;
                order.push(id);
                extras[id] = extras[id] || [];
            } else {
                extras[anchor].push(c);
            }
        });
        var keep = {};
        ids.forEach(function (id) { keep[String(id)] = true; });
        var prev = '';
        order.forEach(function (id) {
            if (keep[id]) { prev = id; return; }
            extras[prev] = extras[prev].concat(extras[id]);
            extras[id] = [];
        });
        var out = extras[''].slice();
        ids.forEach(function (id) {
            var node = nodeFor(String(id));
            if (node) out.push(node);
            out = out.concat(extras[String(id)] || []);
        });
        return out;
    }

    // ── Reading ──

    // The reader's answer for a sketch page, kept while its boxes are the
    // same; a page type with a widget no section has adds one at the end.
    function read(page) {
        var sig = boxSig(page);
        var was = reads[page.id];
        if (was && was.sig === sig) return was.r;
        // The page is as tall as its lowest box: a band ending there is at
        // the page's foot.
        var low = page.boxes.reduce(function (most, b) { return Math.max(most, b.y + b.h); }, 0);
        var r = window.PGSketchReader.read({ x: 0, y: 0, w: host.limits().w, h: low }, page.boxes.map(function (b) {
            return { id: b.id, x: b.x, y: b.y, w: b.w, h: b.h, kind: b.kind || undefined, text: b.text || '' };
        }));
        var type = page.details && page.details.type;
        if (type && type !== 'standard' && !r.sections.some(function (s) { return s.widget === type; })) {
            r.sections.push({ id: 'type', kind: type, widget: type, boxes: [], buttons: 0, columns: 1, heading: '', picture: false, unsure: false, reason: null, options: [] });
        }
        reads[page.id] = { sig: sig, r: r };
        return r;
    }

    // The section a box belongs to: listed in it, else the one its middle
    // lies in.
    function sectionOfBox(r, b) {
        var hit = null;
        r.sections.forEach(function (s) { if (!hit && s.boxes.indexOf(String(b.id)) !== -1) hit = s; });
        if (hit) return hit;
        var mid = b.y + b.h / 2;
        r.sections.forEach(function (s) { if (!hit && mid >= s.top && mid <= s.top + s.height) hit = s; });
        return hit;
    }

    // The reading a box's chip menu offers: null when the box's kind was
    // chosen or the reader has nothing on it.
    function reading(frameId, boxId) {
        if (!host || !window.PGSketchReader) return null;
        var page = host.page(frameId);
        var b = page ? page.boxes.filter(function (one) { return one.id === Number(boxId); })[0] : null;
        if (!b || b.kind) return null;
        var s = sectionOfBox(read(page), b);
        return s ? { kind: s.kind, unsure: !!s.unsure, options: s.unsure ? s.options.slice() : [] } : null;
    }

    function unsureOf(r) {
        return r.sections.filter(function (s) { return s.unsure && s.boxes.length; });
    }

    // ── Drawing on the frame ──

    function seen() {
        if (K._sdTipsOff()) return true;
        try { return window.localStorage.getItem(SEEN_KEY) === '1'; } catch (e) { return false; }
    }

    function setSeen() {
        try { window.localStorage.setItem(SEEN_KEY, '1'); } catch (e) { /* shown again next visit */ }
    }

    function button(cls, text, run) {
        var b = host.el('button', 'btn btn-sm sd-sketch-act ' + cls, text);
        b.type = 'button';
        b.addEventListener('click', function (event) {
            event.stopPropagation();
            run();
        });
        return b;
    }

    // The line under a frame: { tone, html, act, run, sig }.
    function say(frameId, tone, html, act, run) {
        var page = host.page(frameId);
        results[frameId] = { tone: tone, html: html, act: act || '', run: run || null, sig: page ? boxSig(page) : '' };
    }

    function bold(text, name) {
        return esc(text).replace('\u0001', '<strong>' + esc(name) + '</strong>');
    }

    function fill(frameId, node, head, body) {
        if (!host) return;
        var page = host.page(frameId);
        if (!page || !window.PGSketchReader) return;
        var on = host.on();
        var r = read(page);
        var more = head.querySelector('.sd-sketch-more');
        var unsure = unsureOf(r);
        // The chips: the reader's kind for a box not chosen, marked when
        // it is not sure.
        page.boxes.forEach(function (b) {
            if (b.kind) return;
            var s = sectionOfBox(r, b);
            var el = body.querySelector('.sd-sketch-box[data-box="' + b.id + '"]');
            var chip = el ? el.querySelector('.sd-sketch-chip') : null;
            if (!s || !chip) return;
            var text = chip.querySelector('.sd-sketch-chip-text');
            if (text) text.textContent = host.kindLabel(s.kind);
            el.classList.remove('sd-sketch-unread');
            el.classList.add('sd-sketch-kind-' + s.kind);
            // The box's name as its chip reads, no longer "Not read yet".
            el.setAttribute('aria-label', host.kindLabel(s.kind) + (b.text ? ': ' + b.text : ''));
            if (s.unsure) {
                var mark = host.el('span', 'bi bi-question-circle-fill sd-skb-mark');
                mark.setAttribute('aria-hidden', 'true');
                chip.insertBefore(mark, chip.firstChild);
                el.setAttribute('aria-label', el.getAttribute('aria-label') + ', ' + _sdT('to check'));
            }
            if (check && check.frame === frameId && check.lit !== null && String(check.lit) === String(s.id)) el.classList.add('sd-skb-lit');
        });
        if (unsure.length) {
            var pill = host.el('span', 'sd-skb-pill', _sdT('{var:1} to check', unsure.length));
            head.insertBefore(pill, more);
        }
        var built = page.built ? host.pageOf(page.built.uid) : null;
        if (built) head.insertBefore(host.el('span', 'sd-skb-draft', _sdT('Draft')), more);
        if (on && busy === frameId) {
            node.classList.add('sd-skb-busy');
            var spin = host.el('span', 'sd-sketch-read-busy');
            spin.setAttribute('role', 'status');
            spin.appendChild(host.el('span', 'bi bi-arrow-repeat sd-sketch-spin'));
            spin.lastChild.setAttribute('aria-hidden', 'true');
            spin.appendChild(host.el('span', '', _sdT('Building the page…')));
            head.insertBefore(spin, more);
        } else if (on && more) {
            var again = !!built;
            var go = button(again ? 'btn-outline theme-secondary sd-skb-build' : 'btn-solid theme-primary sd-skb-build', again ? _sdT('Build again') : _sdT('Build the page'), function () { start(host, frameId); });
            if (!page.boxes.length) {
                go.disabled = true;
                go.title = _sdT('Draw a box first');
            } else if (again && keptSig(page, host.treeOf(built)) === boxSig(page)) {
                go.disabled = true;
                go.title = _sdT('The sketch has not changed since the page was built.');
            } else if (check && check.frame === frameId) {
                go.disabled = true;
            }
            head.insertBefore(go, more);
        }
        if (!on || node.querySelector('.sd-sketch-foot')) return;
        var res = results[frameId];
        if (res && res.sig !== boxSig(page)) { delete results[frameId]; res = null; }
        var foot = null;
        if (res) {
            foot = host.el('div', 'sd-sketch-foot sd-skb-result');
            foot.setAttribute('role', 'status');
            var tile = host.el('span', 'sd-skb-tile sd-skb-' + res.tone);
            tile.innerHTML = '<span class="bi ' + ({ teal: 'bi-check-lg', blue: 'bi-info-lg', amber: 'bi-exclamation-lg', rose: 'bi-x-lg' }[res.tone]) + '" aria-hidden="true"></span>';
            foot.appendChild(tile);
            var line = host.el('span', 'sd-sketch-foot-text');
            line.innerHTML = res.html;
            foot.appendChild(line);
            if (res.act) foot.appendChild(button('btn-outline theme-secondary', res.act, res.run));
        } else if (page.boxes.length && !page.built && !seen()) {
            foot = host.el('div', 'sd-sketch-foot');
            foot.appendChild(host.el('span', 'sd-sketch-foot-text', _sdT('“Build the page” turns these boxes into real sections of a draft page, with sample content you then edit.')));
            foot.appendChild(button('btn-outline theme-secondary', _sdT('Got it'), function () { setSeen(); host.redraw(); }));
        }
        if (foot) node.appendChild(foot);
    }

    // ── The unsure boxes, asked in one list ──

    function closeCheck(refocus) {
        if (!check) return;
        var was = check;
        check = null;
        document.removeEventListener('pointerdown', was.outside, true);
        if (was.el.parentNode) was.el.parentNode.removeChild(was.el);
        if (was.settled) host.changed(_sdT('Boxes settled'));
        else host.redraw();
        if (refocus) host.focusFrame(was.frame);
    }

    // The row's choice made the box's kind: the chosen kind binds, so it is
    // no longer asked.
    function settle(row, kind) {
        var page = host.page(check.frame);
        if (!page) return;
        row.kind = kind;
        page.boxes.forEach(function (b) {
            if (row.section.boxes.indexOf(String(b.id)) !== -1) b.kind = kind;
        });
        check.settled = true;
    }

    function light(id) {
        if (!check) return;
        check.lit = id;
        var frame = host.frameEl(check.frame);
        if (!frame) return;
        Array.prototype.forEach.call(frame.querySelectorAll('.sd-sketch-box'), function (el) {
            var b = String(el.getAttribute('data-box'));
            var row = check.rows.filter(function (one) { return String(one.section.id) === String(id); })[0];
            el.classList.toggle('sd-skb-lit', !!row && row.section.boxes.indexOf(b) !== -1);
        });
    }

    var REASONS = {
        words: function () { return _sdT('The words name both.'); },
        empty: function () { return _sdT('The box is empty.'); },
        mixed: function () { return _sdT('Unlike boxes side by side.'); }
    };

    function openCheck(frameId, list) {
        var page = host.page(frameId);
        check = { frame: frameId, rows: [], el: null, lit: null, settled: false, outside: null };
        var el = host.el('div', 'sd-skb-check');
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-labelledby', 'sd-skb-check-title');
        el.appendChild(host.el('div', 'sd-skb-check-title', list.length === 1 ? _sdT('1 box to check') : _sdT('{var:1} boxes to check', list.length)));
        el.firstChild.id = 'sd-skb-check-title';
        el.appendChild(host.el('div', 'sd-skb-check-sub', _sdT('The best reading is chosen. Change what is wrong.')));
        var rowsEl = host.el('div', 'sd-skb-check-rows');
        list.forEach(function (s, i) {
            var row = { section: s, kind: s.kind, el: null };
            check.rows.push(row);
            var r = host.el('div', 'sd-skb-row');
            r.tabIndex = i === 0 ? 0 : -1;
            r.setAttribute('role', 'group');
            var title = host.el('div', 'sd-skb-row-title');
            if (s.heading) title.textContent = '“' + s.heading + '”';
            else title.appendChild(host.el('span', 'sd-skb-none', _sdT('No heading')));
            r.appendChild(title);
            r.appendChild(host.el('div', 'sd-skb-row-line', REASONS[s.reason] ? REASONS[s.reason]() : ''));
            r.setAttribute('aria-label', title.textContent);
            var seg = host.el('div', 'sd-skb-seg');
            seg.setAttribute('role', 'radiogroup');
            seg.setAttribute('aria-label', _sdT('What this box is'));
            var options = s.options.indexOf(s.kind) === -1 ? [s.kind].concat(s.options) : s.options.slice();
            options.forEach(function (k) {
                var opt = host.el('button', 'sd-skb-opt', host.kindLabel(k));
                opt.type = 'button';
                opt.tabIndex = -1;
                opt.setAttribute('role', 'radio');
                opt.setAttribute('data-kind', k);
                opt.addEventListener('click', function () { pick(row, k); });
                seg.appendChild(opt);
            });
            var other = document.createElement('select');
            other.className = 'form-control form-control-sm sd-skb-other';
            other.tabIndex = -1;
            other.setAttribute('aria-label', _sdT('Something else'));
            other.innerHTML = '<option value="">' + esc(_sdT('Something else')) + '</option>' + host.kinds().filter(function (k) { return options.indexOf(k.key) === -1; }).map(function (k) {
                return '<option value="' + esc(k.key) + '">' + esc(k.text()) + '</option>';
            }).join('');
            other.addEventListener('change', function () { if (other.value) pick(row, other.value); });
            seg.appendChild(other);
            r.appendChild(seg);
            r.addEventListener('pointerenter', function () { light(s.id); });
            r.addEventListener('focusin', function () { light(s.id); });
            row.el = r;
            rowsEl.appendChild(r);
            mark(row);
        });
        el.appendChild(rowsEl);
        var foot = host.el('div', 'sd-skb-check-foot');
        foot.appendChild(button('btn-outline theme-secondary', _sdT('Cancel'), function () { closeCheck(true); }));
        foot.appendChild(button('btn-solid theme-primary', _sdT('Build the page'), function () { var f = check.frame; closeCheck(false); go(f); }));
        el.appendChild(foot);
        el.addEventListener('keydown', checkKey);
        check.el = el;
        host.root().appendChild(el);
        host.place(el, frameId);
        check.outside = function (event) { if (check && !check.el.contains(event.target)) closeCheck(false); };
        document.addEventListener('pointerdown', check.outside, true);
        host.redraw();
        check.rows[0].el.focus({ preventScroll: true });
        host.announce(_sdT('{var:1} boxes to check', list.length) + ' (' + (page ? host.name(page) : '') + ')');
    }

    function mark(row) {
        Array.prototype.forEach.call(row.el.querySelectorAll('.sd-skb-opt'), function (o) {
            var on = o.getAttribute('data-kind') === row.kind;
            o.classList.toggle('active', on);
            o.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        var other = row.el.querySelector('.sd-skb-other');
        if (other) other.value = other.querySelector('option[value="' + row.kind + '"]') ? row.kind : '';
    }

    function pick(row, kind) {
        settle(row, kind);
        mark(row);
        host.announce(host.kindLabel(kind));
    }

    function checkKey(event) {
        event.stopPropagation();
        if (!check) return;
        var rows = check.rows;
        var at = -1;
        rows.forEach(function (row, i) { if (row.el.contains(document.activeElement)) at = i; });
        var inSelect = document.activeElement && document.activeElement.tagName === 'SELECT';
        if (event.key === 'Escape') { event.preventDefault(); closeCheck(true); return; }
        if (event.key === 'Enter' && !inSelect && !(document.activeElement && document.activeElement.tagName === 'BUTTON')) {
            event.preventDefault();
            var f = check.frame;
            closeCheck(false);
            go(f);
            return;
        }
        if (at === -1 || inSelect) return;
        var row = rows[at];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            var next = rows[(at + (event.key === 'ArrowDown' ? 1 : rows.length - 1)) % rows.length];
            rows.forEach(function (one) { one.el.tabIndex = one === next ? 0 : -1; });
            next.el.focus();
            return;
        }
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            var kinds = Array.prototype.map.call(row.el.querySelectorAll('.sd-skb-opt'), function (o) { return o.getAttribute('data-kind'); });
            var i = kinds.indexOf(row.kind);
            var to = kinds[(i + (event.key === 'ArrowRight' ? 1 : kinds.length - 1) + (i === -1 ? 1 : 0)) % kinds.length];
            if (to) pick(row, to);
        }
    }

    // ── Building ──

    // "Build the page" or "Build again" of a sketch page.
    function start(h, frameId) {
        host = h;
        var page = host.page(frameId);
        if (!page || busy || !page.boxes.length) return;
        if (!window.PGSketchReader || !window.PGSketchBuilder) return;
        closeCheck(false);
        if (!host.mayBuild()) {
            say(frameId, 'rose', esc(_sdT('You may not add pages to this design. The sketch is kept; someone who may can build it.')));
            host.redraw();
            return;
        }
        var list = unsureOf(read(page));
        if (list.length) { openCheck(frameId, list); return; }
        go(frameId);
    }

    function go(frameId) {
        var page = host.page(frameId);
        if (!page) return;
        var p = page.built ? host.pageOf(page.built.uid) : null;
        if (p) again(frameId, page, p);
        else first(frameId, page);
    }

    // The section's node, from the builder's part: placed shared
    // components and widgets come from the host.
    function nodeOf(part, made) {
        if (part.node && !(SHARED[part.kind] && !part.kept)) return Promise.resolve(part.node);
        if (part.widget) return host.widget(part.widget, part.section);
        return host.shared(part.kind, made, part.section);
    }

    // What the site knows, for the builder: its name, the year, the site
    // path and the Prototype view's pages; the page's "Page details"; the
    // kinds' names as headings of sections with no typed word.
    function known(page) {
        var site = host.site ? host.site() : null;
        var d = (page && page.details) || {};
        var names = {};
        host.kinds().forEach(function (k) { names[k.key] = host.kindLabel(k.key); });
        names.form = _sdT('Contact');
        return {
            site: site ? { name: site.name || _sdT('Your site'), year: new Date().getFullYear(), path: site.path, pages: site.pages, toggle: _sdT('Toggle navigation') } : null,
            page: { title: d.title || '', description: d.description || '', name: page ? host.name(page) : '' },
            names: names
        };
    }

    // The parts in page order; list.blocks the starters made, whose block
    // styles join the design only once the page is built.
    function parts(r, kept, page) {
        var k = known(page);
        var blocks = [];
        var make = function (type) {
            var node = host.make(type);
            if (node) blocks.push(type);
            return node;
        };
        var out = window.PGSketchBuilder.build(r.sections, { make: make, node: host.node, words: host.words(), kept: kept || null, site: k.site, page: k.page, names: k.names });
        var list = [];
        if (out.header) list.push(out.header);
        list = list.concat(out.body);
        if (out.footer) list.push(out.footer);
        list.blocks = blocks;
        return list;
    }

    // Every part's node in order, or a rejection when one could not be
    // made.
    function nodes(list) {
        return Promise.all(list.map(function (part) {
            return nodeOf(part, part.node).then(function (node) {
                if (!node) throw new Error('no node');
                node.props = node.props || {};
                node.props._sketchId = part.section;
                return node;
            });
        }));
    }

    function failed(frameId, name) {
        busy = null;
        say(frameId, 'rose', bold(_sdT('{var:1} could not be built. The sketch is kept as it is.', '\u0001'), name), _sdT('Try again'), function () { start(host, frameId); });
        host.redraw();
        host.announce(_sdT('The page could not be built.'));
    }

    // What the draft's root keeps: the sketch page and what each section
    // was built from.
    function meta(uid, page, r, made) {
        var secs = {};
        r.sections.forEach(function (s, i) {
            if (made[i]) secs[String(s.id)] = { sig: secSig(s), fp: nodeFp(made[i]) };
        });
        var copy = JSON.parse(JSON.stringify(page));
        delete copy.built;
        return { uid: uid, sig: boxSig(page), secs: secs, page: copy };
    }

    function first(frameId, page) {
        var name = host.name(page);
        var r = read(page);
        var list = parts(r, null, page);
        busy = frameId;
        setSeen();
        host.redraw();
        host.announce(_sdT('Building the page…'));
        nodes(list).then(function (made) {
            var uid = 'sk' + Date.now().toString(36) + Math.floor(Math.random() * 1e6).toString(36);
            var byId = {};
            list.forEach(function (part, i) { byId[String(part.section)] = made[i]; });
            var key = host.addPage(page, name, made, meta(uid, page, r, r.sections.map(function (s) { return byId[String(s.id)]; })));
            busy = null;
            if (!key) { failed(frameId, name); return; }
            host.blockStyles(list.blocks);
            page.built = { uid: uid, sig: boxSig(page) };
            var count = made.length;
            var html = bold(count === 1 ? _sdT('{var:1} is built: 1 section, as a draft.', '\u0001') : _sdT('{var:1} is built: {var:2} sections, as a draft.', ['\u0001', count]), name);
            if (r.skipped.length) {
                html += ' ' + esc(r.skipped.length === 1 ? _sdT('1 box was left out: too small, or half outside the page.') : _sdT('{var:1} boxes were left out: too small, or half outside the page.', r.skipped.length));
            }
            host.changed(_sdT('The page is built as a draft.'));
            if (r.skipped.length) say(frameId, 'amber', html, _sdT('Show them'), function () { host.pick(frameId, r.skipped[0]); });
            else say(frameId, 'teal', html, _sdT('Open the page'), function () { host.open(uid); });
            host.redraw();
        }, function () { failed(frameId, name); });
    }

    function again(frameId, page, p) {
        var name = host.name(page);
        var r = read(page);
        var root = host.treeOf(p);
        var kept = (root && root.props && root.props._sketch) || {};
        var now = {};
        var onPage = {};
        (root.children || []).forEach(function (c) {
            var id = sketchIdOf(c);
            if (id && !onPage[id]) { onPage[id] = c; now[id] = nodeFp(c); }
        });
        var pl = plan(r.sections, kept.secs, now);
        if (pl.ask) question(frameId, page, p, r, pl, onPage);
        else rebuild(frameId, page, p, r, pl, onPage, {});
    }

    function rebuild(frameId, page, p, r, pl, onPage, ticked) {
        var name = host.name(page);
        if (!host.history(p, name)) { failed(frameId, name); return; }
        var keep = {};
        pl.stay.concat(pl.clash).forEach(function (id) { if (!ticked[id]) keep[id] = onPage[id]; });
        var list = parts(r, keep, page);
        busy = frameId;
        host.redraw();
        host.announce(_sdT('Building the page…'));
        nodes(list).then(function (made) {
            var byId = {};
            list.forEach(function (part, i) { byId[String(part.section)] = made[i]; });
            var root = host.treeOf(p);
            var children = arrange(root.children, r.sections.map(function (s) { return String(s.id); }), function (id) { return byId[id]; });
            var m = meta(root.props._sketch.uid, page, r, r.sections.map(function (s) { return byId[String(s.id)]; }));
            keptFp(m.secs, root.props._sketch.secs, keep);
            busy = null;
            if (!host.setChildren(p, children, m)) { failed(frameId, name); return; }
            host.blockStyles(list.blocks);
            page.built = { uid: m.uid, sig: m.sig };
            var changed = pl.rebuild.length + pl.add.length + pl.remove.length + Object.keys(ticked).filter(function (id) { return ticked[id]; }).length;
            host.changed(_sdT('The page is built again.'));
            if (changed) say(frameId, 'teal', bold(_sdT('{var:1} is built again: {var:2} sections changed, the others stay as they were.', ['\u0001', changed]), name), _sdT('Open the page'), function () { host.open(m.uid); });
            else say(frameId, 'blue', bold(_sdT('{var:1} already matches the sketch: no section had to change.', '\u0001'), name));
            host.redraw();
        }, function () { failed(frameId, name); });
    }

    // ── The "Build again" question ──

    function closeAsk() {
        if (!ask) return;
        var was = ask;
        ask = null;
        if (was.back.parentNode) was.back.parentNode.removeChild(was.back);
        host.focusFrame(was.frame);
    }

    function effect(kind, icon, title, line) {
        var li = host.el('li', 'sd-skb-effect sd-skb-effect-' + kind);
        li.innerHTML = '<span class="bi ' + icon + '" aria-hidden="true"></span>';
        var div = host.el('div', '', title);
        if (line) div.appendChild(host.el('span', 'sd-skb-effect-line', line));
        li.appendChild(div);
        return li;
    }

    function question(frameId, page, p, r, pl, onPage) {
        var name = host.name(page);
        var byId = {};
        r.sections.forEach(function (s) { byId[String(s.id)] = s; });
        var label = function (id) {
            var s = byId[id];
            if (s) return host.kindLabel(s.kind);
            var n = onPage[id];
            return (n && n.props && (n.props.customName || n.props.sharedName)) || _sdT('Section');
        };
        var back = host.el('div', 'sd-skd-back');
        var win = host.el('div', 'sd-skd sd-skb-ask');
        win.setAttribute('role', 'dialog');
        win.setAttribute('aria-modal', 'true');
        win.setAttribute('aria-labelledby', 'sd-skb-ask-title');
        var head = host.el('div', 'sd-skb-ask-head');
        head.innerHTML = '<span class="sd-skb-tile sd-skb-blue sd-skb-tile-lg" aria-hidden="true"><span class="bi bi-arrow-repeat"></span></span>';
        var titles = host.el('div', 'sd-skb-ask-titles');
        titles.appendChild(host.el('div', 'sd-skd-title', _sdT('Build “{var:1}” again?', name)));
        titles.firstChild.id = 'sd-skb-ask-title';
        titles.appendChild(host.el('div', 'sd-skd-sub', _sdT('The sketch changed since the page was built.')));
        head.appendChild(titles);
        win.appendChild(head);
        var ul = host.el('ul', 'sd-skb-effects');
        if (pl.stay.length) {
            ul.appendChild(effect('keep', 'bi-check-circle-fill', pl.stay.length === 1 ? _sdT('1 section stays as it is') : _sdT('{var:1} sections stay as they are', pl.stay.length), pl.stay.map(label).join(', ')));
        }
        pl.rebuild.forEach(function (id) {
            var s = byId[id];
            var line = (s.columns > 1) ? _sdT('It has {var:1} columns now and was not edited.', s.columns) : _sdT('Its box changed and it was not edited.');
            ul.appendChild(effect('info', 'bi-info-circle-fill', _sdT('{var:1} is built again', label(id)), line));
        });
        pl.add.forEach(function (id) {
            var at = -1;
            r.sections.forEach(function (s, i) { if (String(s.id) === id) at = i; });
            var line = at > 0 ? _sdT('It is placed after {var:1}.', host.kindLabel(r.sections[at - 1].kind)) : _sdT('It is placed first.');
            ul.appendChild(effect('info', 'bi-plus-circle-fill', _sdT('{var:1} is new', label(id)), line));
        });
        pl.remove.forEach(function (id) {
            ul.appendChild(effect('go', 'bi-x-circle-fill', _sdT('{var:1} is removed', label(id)), _sdT('Its box was deleted in the sketch; what was written in it goes with it.')));
        });
        var body = host.el('div', 'sd-skd-body sd-skb-ask-body');
        body.appendChild(ul);
        var ticked = {};
        if (pl.clash.length) {
            var det = host.el('details', 'sd-skb-more');
            det.appendChild(host.el('summary', '', _sdT('More')));
            pl.clash.forEach(function (id) {
                var row = host.el('div', 'sd-skb-clash');
                var p1 = host.el('p', '');
                p1.innerHTML = bold(_sdT('{var:1} was edited in Design and its box changed in the sketch. It keeps your edits.', '\u0001'), label(id));
                row.appendChild(p1);
                var lab = host.el('label', 'sd-skb-tick');
                var box = document.createElement('input');
                box.type = 'checkbox';
                box.className = 'check';
                box.addEventListener('change', function () { ticked[id] = box.checked; });
                lab.appendChild(box);
                lab.appendChild(host.el('span', '', _sdT('Build {var:1} again from the sketch', label(id))));
                row.appendChild(lab);
                det.appendChild(row);
            });
            body.appendChild(det);
        }
        win.appendChild(body);
        var foot = host.el('div', 'sd-skd-foot');
        foot.appendChild(host.el('span', 'sd-skd-note', _sdT('History keeps the page as it is now.')));
        var cancel = button('btn-outline theme-secondary', _sdT('Cancel'), closeAsk);
        var goBtn = button('btn-solid theme-primary', _sdT('Build again'), function () {
            closeAsk();
            rebuild(frameId, page, p, r, pl, onPage, ticked);
        });
        foot.appendChild(cancel);
        foot.appendChild(goBtn);
        win.appendChild(foot);
        back.appendChild(win);
        back.addEventListener('keydown', function (event) {
            event.stopPropagation();
            if (event.key === 'Escape') { event.preventDefault(); closeAsk(); return; }
            if (event.key === 'Enter' && !(event.target && /^(BUTTON|INPUT|SUMMARY)$/.test(event.target.tagName))) { event.preventDefault(); goBtn.click(); return; }
            if (event.key === 'Tab') {
                var all = Array.prototype.filter.call(win.querySelectorAll('button, summary, input'), function (el) { return el.offsetParent !== null; });
                var i = all.indexOf(document.activeElement);
                if (event.shiftKey && i <= 0) { event.preventDefault(); all[all.length - 1].focus(); }
                else if (!event.shiftKey && i === all.length - 1) { event.preventDefault(); all[0].focus(); }
            }
        });
        ask = { frame: frameId, back: back };
        document.body.appendChild(back);
        goBtn.focus({ preventScroll: true });
    }

    // Escape closes the list or the question; false when neither is open.
    function escape() {
        if (ask) { closeAsk(); return true; }
        if (check) { closeCheck(true); return true; }
        return false;
    }

    return {
        start: start,
        fill: function (h, frameId, node, head, body) { host = h; fill(frameId, node, head, body); },
        reading: function (h, frameId, boxId) { host = h; return reading(frameId, boxId); },
        escape: escape,
        pure: { hash: hash, boxSig: boxSig, secSig: secSig, nodeFp: nodeFp, plan: plan, arrange: arrange, keptFp: keptFp, keptSig: keptSig }
    };
});
