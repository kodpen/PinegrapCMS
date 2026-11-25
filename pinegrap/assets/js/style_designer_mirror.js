/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the mirror part: a picture of the draft at another
 * width, in a frame beside the canvas. A mirror holds the canvas's own
 * markup and styles with the editor's marks, toolbars and every script
 * taken out, so it draws what the canvas draws for its width: the same
 * samples and the same colour mode, with no visitor script and no call to
 * the site. It takes no typing, no drag and no navigation.
 *
 * The mirrors are drawn again when the hand rests on the canvas, never
 * during a drag or while a text is typed: the canvas's changes, keys and
 * pointer are watched only while a mirror is open, and when they are drawn
 * is window.PGMirrorSchedule's (the first source of this part). With the
 * last mirror closed nothing of the part is left listening.
 *
 * side() lays out "Side by side" (the View menu's switch, _sdSideSync() in
 * the core): the canvas column holds the canvas and, right of it, one box
 * per mirror under a head with the width and its list of sizes. The core
 * says how wide each box is and whether the row has room; the boxes
 * follow the column's height here.
 *
 * Not needed to open a page: the core runs this file the first time a
 * mirror is asked for (pg_designer_parts()); when the canvas document is
 * written anew the core calls rebind().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('mirror', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var S = window.PGMirrorSchedule;

    // The open mirrors: { id, width, host, frame, head }.
    var mirrors = [];
    var seq = 0;
    var plan = S.initial();
    var timer = null;
    // What watches the canvas while a mirror is open: its document, the
    // observer and the listeners to take off again.
    var watch = null;

    function clock() {
        return (window.performance && performance.now) ? performance.now() : Date.now();
    }

    function find(id) {
        for (var i = 0; i < mirrors.length; i++) if (mirrors[i].id === id) return mirrors[i];
        return null;
    }

    // ── When to draw ─────────────────────────────────────────────────────
    function note(kind) {
        plan = S.step(plan, kind, clock());
        schedule();
    }

    function schedule() {
        if (timer) { clearTimeout(timer); timer = null; }
        var at = S.due(plan);
        if (at === null || !mirrors.length) return;
        timer = setTimeout(tick, Math.max(0, Math.ceil(at - clock())));
    }

    function tick() {
        timer = null;
        var at = S.due(plan);
        if (at === null) return;
        if (clock() + 1 < at) { schedule(); return; }
        drawAll();
    }

    // ── What the canvas does ─────────────────────────────────────────────
    function classOf(el) {
        if (!el || el.nodeType !== 1) return '';
        return el.getAttribute('class') || '';
    }

    // The class list a change is judged by: that of the editor's chrome
    // (a toolbar, a badge) the element sits in, else its own.
    function ownerClass(el) {
        if (!el || el.nodeType !== 1) return '';
        var chrome = el.closest ? el.closest(S.CHROME_PARTS) : null;
        return classOf(chrome || el);
    }

    // A mutation record in the shape PGMirrorSchedule.matters() reads.
    function shape(r) {
        if (r.type === 'attributes') {
            return { type: r.type, name: r.attributeName, cls: ownerClass(r.target), old: r.oldValue, now: r.target.getAttribute(r.attributeName) };
        }
        if (r.type === 'characterData') return { type: r.type, cls: ownerClass(r.target.parentNode) };
        var nodes = [];
        var add = function (list) {
            for (var i = 0; i < list.length; i++) nodes.push(list[i].nodeType === 1 ? classOf(list[i]) : null);
        };
        add(r.addedNodes);
        add(r.removedNodes);
        return { type: r.type, cls: ownerClass(r.target), nodes: nodes };
    }

    function observed(records) {
        for (var i = 0; i < records.length; i++) {
            if (S.matters(shape(records[i]))) { note('change'); return; }
        }
    }

    function bind() {
        var doc = K.canvasDoc;
        if (watch && watch.doc === doc) return;
        unbind();
        if (!doc || !doc.documentElement) return;
        var offs = [];
        var on = function (target, type, kind) {
            var fn = function () { note(kind); };
            target.addEventListener(type, fn, true);
            offs.push(function () { target.removeEventListener(type, fn, true); });
        };
        // A drag or press may start on the canvas or in the palette and end
        // in either; a pointer let go outside the window ends with the blur.
        [doc, document].forEach(function (d) {
            on(d, 'pointerdown', 'down');
            on(d, 'pointerup', 'up');
            on(d, 'pointercancel', 'up');
            on(d, 'dragstart', 'down');
            on(d, 'dragend', 'up');
            on(d, 'drop', 'up');
        });
        on(doc, 'keydown', 'key');
        on(window, 'blur', 'up');
        var obs = new MutationObserver(observed);
        obs.observe(doc.documentElement, { subtree: true, childList: true, attributes: true, attributeOldValue: true, characterData: true });
        offs.push(function () { obs.disconnect(); });
        watch = { doc: doc, offs: offs };
    }

    function unbind() {
        if (!watch) return;
        watch.offs.forEach(function (off) { off(); });
        watch = null;
    }

    // ── The picture ──────────────────────────────────────────────────────
    // The canvas's markup without the editor: no script, toolbar, badge or
    // state class, nothing editable or draggable, no handler attribute, no
    // embedded page loaded and no media playing by itself.
    function strip(root) {
        var gone = root.querySelectorAll(S.CHROME_SELECTOR);
        for (var i = 0; i < gone.length; i++) if (gone[i].parentNode) gone[i].parentNode.removeChild(gone[i]);
        var all = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));
        all.forEach(function (el) {
            if (el.hasAttribute('class')) el.setAttribute('class', S.clean(el.getAttribute('class')));
            S.EDIT_ATTRS.forEach(function (a) { if (el.hasAttribute(a)) el.removeAttribute(a); });
            for (var j = el.attributes.length - 1; j >= 0; j--) {
                if (/^on/i.test(el.attributes[j].name)) el.removeAttribute(el.attributes[j].name);
            }
            var tag = el.tagName ? el.tagName.toLowerCase() : '';
            if (tag === 'iframe' || tag === 'object' || tag === 'embed') {
                el.removeAttribute('srcdoc');
                el.removeAttribute('data');
                el.setAttribute('src', 'about:blank');
            } else if (tag === 'video' || tag === 'audio') {
                el.removeAttribute('autoplay');
                el.setAttribute('preload', 'none');
            }
        });
        return root;
    }

    // The frame's document, written once as a standards-mode page; clicks,
    // forms and drags in it go nowhere.
    function frameDoc(m) {
        var d = m.frame.contentDocument;
        if (!d) return null;
        if (!d._sdMirror) {
            d.open();
            d.write('<!DOCTYPE html><html><head></head><body></body></html>');
            d.close();
            var stop = function (e) { e.preventDefault(); };
            d.addEventListener('click', stop, true);
            d.addEventListener('submit', stop, true);
            d.addEventListener('dragstart', stop, true);
            d._sdMirror = true;
            m.head = null;
        }
        return d;
    }

    function copyAttrs(from, to) {
        var i;
        for (i = to.attributes.length - 1; i >= 0; i--) to.removeAttribute(to.attributes[i].name);
        for (i = 0; i < from.attributes.length; i++) to.setAttribute(from.attributes[i].name, from.attributes[i].value);
    }

    function fill(m, src, body) {
        var d = frameDoc(m);
        if (!d || !d.documentElement) return;
        // The canvas's <html> carries the colour mode and the language; its
        // zoom is the canvas's own.
        copyAttrs(src.documentElement, d.documentElement);
        d.documentElement.style.zoom = '';
        if (!d.documentElement.getAttribute('style')) d.documentElement.removeAttribute('style');
        var head = src.head ? src.head.innerHTML : '';
        if (m.head !== head) {
            var h = strip(src.head.cloneNode(true));
            d.head.innerHTML = '';
            Array.prototype.slice.call(h.childNodes).forEach(function (n) { d.head.appendChild(d.importNode(n, true)); });
            m.head = head;
        }
        var y = d.scrollingElement ? d.scrollingElement.scrollTop : 0;
        var nb = d.importNode(body, true);
        if (d.body) d.documentElement.replaceChild(nb, d.body); else d.documentElement.appendChild(nb);
        if (y && d.scrollingElement) d.scrollingElement.scrollTop = y;
    }

    function drawAll() {
        plan = S.step(plan, 'drawn', clock());
        var src = K.canvasDoc;
        if (!src || !src.body || !mirrors.length) return;
        var body = strip(src.body.cloneNode(true));
        mirrors.forEach(function (m) { fill(m, src, body); });
    }

    // ── Size ─────────────────────────────────────────────────────────────
    function size(m) {
        var f = S.fit(m.width, m.host.clientWidth, m.host.clientHeight);
        var st = m.frame.style;
        st.width = m.width + 'px';
        st.height = f.height + 'px';
        st.transform = f.scale === 1 ? '' : 'scale(' + f.scale + ')';
        m.scale = f.scale;
    }

    // ── Entry points ─────────────────────────────────────────────────────
    // A mirror `width` px wide in `host`, drawn at once; answers its id.
    function open(host, width) {
        if (!host || !host.appendChild) return null;
        var frame = document.createElement('iframe');
        frame.className = 'sd-mirror-frame';
        // Same origin, so the editor may fill it; no script, form or
        // navigation of its own.
        frame.setAttribute('sandbox', 'allow-same-origin');
        frame.setAttribute('tabindex', '-1');
        frame.setAttribute('title', _sdT('Preview at another screen width'));
        frame.style.cssText = 'display:block;border:0;transform-origin:0 0;background:#fff;';
        host.appendChild(frame);
        var m = { id: ++seq, width: Math.max(1, parseInt(width, 10) || 375), host: host, frame: frame, head: null, scale: 1 };
        mirrors.push(m);
        size(m);
        bind();
        var src = K.canvasDoc;
        if (src && src.body) fill(m, src, strip(src.body.cloneNode(true)));
        return m.id;
    }

    // Another width for a mirror, drawn at once.
    function width(id, w) {
        var m = find(id);
        if (!m) return false;
        m.width = Math.max(1, parseInt(w, 10) || m.width);
        size(m);
        return true;
    }

    // The mirrors fitted to their hosts again (the window or the row changed).
    function resize() {
        mirrors.forEach(size);
    }

    // One mirror closed, or all of them with no id. With none left the
    // canvas is no longer watched and no timer waits.
    function close(id) {
        mirrors = mirrors.filter(function (m) {
            if (id !== undefined && id !== null && m.id !== id) return true;
            if (m.frame.parentNode) m.frame.parentNode.removeChild(m.frame);
            return false;
        });
        if (!mirrors.length) {
            unbind();
            if (timer) { clearTimeout(timer); timer = null; }
            plan = S.initial();
        }
    }

    // The canvas document was written anew: watch the new one and draw.
    function rebind() {
        if (!mirrors.length) return;
        bind();
        mirrors.forEach(function (m) { m.head = null; });
        note('change');
    }

    // ── Side by side ─────────────────────────────────────────────────────
    // The bar's icon and name of each width the list offers.
    var SIZE_LOOK = {
        375: { icon: 'bi-phone', name: _sdT('Mobile (XS)') },
        576: { icon: 'bi-phone-landscape', name: _sdT('Small (SM)') },
        768: { icon: 'bi-tablet', name: _sdT('Tablet (MD)') },
        992: { icon: 'bi-laptop', name: _sdT('Laptop (LG)') },
        1200: { icon: 'bi-display', name: _sdT('Desktop (XL)') }
    };
    var TIP = 'side-by-side';

    // The row while it is shown: { cfg, el, area, cols: [{ id, w, col,
    // head, btn, host }], ro, menu }.
    var row = null;

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }

    function icon(name) {
        var i = el('span', 'bi ' + name);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    // The strip of mirrors sits at the column's right edge, as high as the
    // canvas area with 12 px above and below; the area keeps the rest
    // (style_designer.css, .sd-side-on), so Fit and the canvas's own
    // measures read the canvas alone.
    function place() {
        if (!row || !row.area) return;
        var a = row.area, col = row.cfg.column, st = row.el.style;
        var parent = row.el.offsetParent;
        var right = parent ? parent.clientWidth - (col.offsetLeft + col.offsetWidth) : 0;
        var set = function (k, v) { if (st[k] !== v) st[k] = v; };
        set('top', Math.max(0, a.offsetTop - 12) + 'px');
        set('height', (a.offsetHeight + 24) + 'px');
        set('right', Math.max(0, right) + 'px');
        resize();
    }

    function head(c, index) {
        var h = c.head;
        h.textContent = '';
        var look = SIZE_LOOK[c.w] || { icon: 'bi-aspect-ratio', name: '' };
        var label = _sdT('{var:1} px', c.w);
        if (row.cfg.email) {
            // An e-mail design's one mirror has no list.
            var name = el('span', 'sd-side-w');
            name.appendChild(icon(look.icon));
            name.appendChild(el('span', '', label));
            h.appendChild(name);
            c.btn = null;
            return;
        }
        var btn = el('button', 'sd-side-w');
        btn.type = 'button';
        btn.setAttribute('aria-haspopup', 'menu');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('title', look.name);
        btn.appendChild(icon(look.icon));
        btn.appendChild(el('span', '', label));
        btn.appendChild(icon('bi-chevron-down sd-side-caret'));
        btn.addEventListener('click', function () { list(index); });
        h.appendChild(btn);
        c.btn = btn;
    }

    function build(cfg) {
        var strip = el('div', 'sd-side');
        strip.setAttribute('role', 'group');
        strip.setAttribute('aria-label', _sdT('Side by side'));
        cfg.column.appendChild(strip);
        row = { cfg: cfg, el: strip, area: cfg.column.querySelector('.sd-iframe-area'), cols: [], ro: null, menu: null };
        if (window.ResizeObserver && row.area) {
            row.ro = new ResizeObserver(place);
            row.ro.observe(row.area);
        }
        tip(cfg);
    }

    // Lays the row out as `cfg` says: the area's room, a box per width.
    function apply(cfg) {
        row.cfg = cfg;
        var column = cfg.column;
        column.classList.add('sd-side-on');
        column.style.setProperty('--sd-side-w', cfg.layout.side + 'px');
        cfg.widths.forEach(function (w, i) {
            var c = row.cols[i];
            if (!c) {
                c = { id: null, w: w, col: el('div', 'sd-side-col'), head: el('div', 'sd-side-head'), host: el('div', 'sd-side-box') };
                c.col.appendChild(c.head);
                c.col.appendChild(c.host);
                row.el.appendChild(c.col);
                row.cols.push(c);
            }
            c.col.style.width = cfg.layout.boxes[i] + 'px';
            c.w = w;
            head(c, i);
            if (c.id === null) c.id = open(c.host, w);
            else width(c.id, w);
        });
        row.cols.splice(cfg.widths.length).forEach(function (c) {
            if (c.id !== null) close(c.id);
            if (c.col.parentNode) c.col.parentNode.removeChild(c.col);
        });
        place();
    }

    function side(cfg) {
        if (row && (!cfg || row.cfg.column !== cfg.column || !row.el.isConnected)) sideClose();
        if (!cfg || !cfg.column) return;
        if (!row) build(cfg);
        apply(cfg);
    }

    function sideClose() {
        if (!row) return;
        listClose(false);
        row.cols.forEach(function (c) { if (c.id !== null) close(c.id); });
        if (row.ro) row.ro.disconnect();
        if (row.el.parentNode) row.el.parentNode.removeChild(row.el);
        row.cfg.column.classList.remove('sd-side-on');
        row.cfg.column.style.removeProperty('--sd-side-w');
        if (row.tip && row.tip.parentNode) row.tip.parentNode.removeChild(row.tip);
        row = null;
    }

    // ── A head's list of sizes ───────────────────────────────────────────
    // A menu under the head's width: the bar's five widths, the one shown
    // ticked, those the canvas or the other mirror show dimmed with where
    // they are. Arrows move, Enter chooses, Escape closes.
    function list(index) {
        if (row.menu && row.menu.index === index) { listClose(true); return; }
        listClose(false);
        var c = row.cols[index];
        if (!c || !c.btn) return;
        var menu = el('div', 'sd-vb-menu sd-side-menu');
        menu.setAttribute('role', 'menu');
        menu.setAttribute('aria-label', _sdT('Screen size'));
        var canvasW = row.cfg.canvasWidth ? row.cfg.canvasWidth() : 0;
        S.sizes(row.cfg.widths, index, canvasW).forEach(function (r) {
            var look = SIZE_LOOK[r.w];
            var b = el('button', 'sd-vb-row');
            b.type = 'button';
            b.setAttribute('role', 'menuitemradio');
            b.setAttribute('aria-checked', r.on ? 'true' : 'false');
            b.appendChild(icon(look.icon));
            b.appendChild(el('span', 'sd-vb-row-lbl', look.name));
            var note = r.where === 'canvas' ? _sdT('on the canvas') : (r.where === 'other' ? _sdT('in the other mirror') : String(r.w));
            b.appendChild(el('span', 'sd-side-note', note));
            b.appendChild(icon(r.on ? 'bi-check2 sd-side-tick' : 'sd-side-tick'));
            if (r.where) {
                b.setAttribute('aria-disabled', 'true');
                b.classList.add('sd-vb-row-off');
            }
            b.addEventListener('click', function () {
                if (r.where) return;
                listClose(true);
                if (!r.on) row.cfg.choose(S.pick(row.cfg.widths, index, r.w, canvasW));
            });
            menu.appendChild(b);
        });
        menu.addEventListener('keydown', listKey);
        document.body.appendChild(menu);
        var at = c.btn.getBoundingClientRect();
        menu.style.top = Math.round(at.bottom + 4) + 'px';
        menu.style.left = Math.round(Math.max(8, Math.min(at.left, window.innerWidth - menu.offsetWidth - 8))) + 'px';
        c.btn.setAttribute('aria-expanded', 'true');
        row.menu = { el: menu, index: index, btn: c.btn };
        document.addEventListener('mousedown', listOutside, true);
        var first = menu.querySelector('[aria-checked="true"]') || menu.querySelector('button');
        if (first) first.focus();
    }

    function listKey(e) {
        var rows = Array.prototype.slice.call(row.menu.el.querySelectorAll('button'));
        var at = rows.indexOf(document.activeElement);
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            listClose(true);
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var n = rows.length;
            rows[((at === -1 ? 0 : at) + (e.key === 'ArrowDown' ? 1 : n - 1)) % n].focus();
        } else if (e.key === 'Tab') {
            listClose(false);
        }
    }

    function listOutside(e) {
        if (!row || !row.menu) return;
        if (row.menu.el.contains(e.target) || row.menu.btn.contains(e.target)) return;
        listClose(false);
    }

    function listClose(focusBack) {
        if (!row || !row.menu) return;
        var m = row.menu;
        row.menu = null;
        document.removeEventListener('mousedown', listOutside, true);
        if (m.el.parentNode) m.el.parentNode.removeChild(m.el);
        m.btn.setAttribute('aria-expanded', 'false');
        if (focusBack && m.btn.isConnected) m.btn.focus();
    }

    // ── The first-use line ───────────────────────────────────────────────
    // Once per person, under the canvas bar; "Got it" ends it for good
    // (the tour's tip state, PGDesignerTour, which comes with the part
    // 'bands': cfg.tour() runs it when it has not run yet).
    function tip(cfg) {
        var T = window.PGDesignerTour;
        if (!row) return;
        if (!T) {
            if (cfg.tour && !tip.asked) {
                tip.asked = true;
                cfg.tour().then(function () { if (row && !row.tip) tip(row.cfg); }, function () {});
            }
            return;
        }
        var key = T.key(cfg.userId), state = null;
        if (!key) return;
        try { state = T.read(window.localStorage.getItem(key)); } catch (e) { return; }
        if (!T.tipDue(state, TIP)) return;
        var bar = cfg.column.querySelector('#sd-bp-bar');
        if (!bar) return;
        var line = el('div', 'sd-cgb-first');
        line.appendChild(icon('bi-info-circle'));
        line.appendChild(el('span', '', _sdT('Side by side shows this page on a phone and a tablet while you edit. Click anything there to select it here.')));
        var got = el('button', 'sd-cgb-got', _sdT('Got it'));
        got.type = 'button';
        got.addEventListener('click', function () {
            try { window.localStorage.setItem(key, T.write(T.tipSeen(T.read(window.localStorage.getItem(key)), TIP))); } catch (e) { /* shown again next time */ }
            if (line.parentNode) line.parentNode.removeChild(line);
            if (row) row.tip = null;
        });
        line.appendChild(got);
        bar.insertAdjacentElement('afterend', line);
        row.tip = line;
    }

    return {
        side: side,
        open: open,
        width: width,
        resize: resize,
        close: close,
        rebind: rebind,
        draw: drawAll,
        count: function () { return mirrors.length; }
    };
});
