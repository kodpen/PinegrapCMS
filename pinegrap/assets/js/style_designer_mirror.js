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

    // A mutation record in the shape PGMirrorSchedule.matters() reads.
    function shape(r) {
        if (r.type === 'attributes') {
            return { type: r.type, name: r.attributeName, cls: classOf(r.target), old: r.oldValue, now: r.target.getAttribute(r.attributeName) };
        }
        if (r.type === 'characterData') return { type: r.type, cls: classOf(r.target.parentNode) };
        var nodes = [];
        var add = function (list) {
            for (var i = 0; i < list.length; i++) nodes.push(list[i].nodeType === 1 ? classOf(list[i]) : null);
        };
        add(r.addedNodes);
        add(r.removedNodes);
        return { type: r.type, cls: classOf(r.target), nodes: nodes };
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

    return {
        open: open,
        width: width,
        resize: resize,
        close: close,
        rebind: rebind,
        draw: drawAll,
        count: function () { return mirrors.length; }
    };
});
