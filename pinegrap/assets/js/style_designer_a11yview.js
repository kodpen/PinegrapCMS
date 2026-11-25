/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the a11yview part: the canvas's Accessibility view.
 * While the View menu's "Accessibility view" switch is on, a card stands in
 * the canvas's bottom right corner and the canvas carries one kind of mark:
 * the tab order (a number on every stop, joined by a line), the headings (a
 * level tag on each) or the names (regions, pictures' texts, controls with
 * no name). What is counted and named comes from window.PGA11yReader
 * (assets/js/pg_a11y_reader.js, the first file of this part); a fault is
 * named with the accessibility audit's own sentence for its rule.
 *
 * The card and the marks live in layers of the editor's own over the canvas
 * frame, never in the canvas's page, so nothing enters the tree, the saved
 * markup or the Preview. The page is read when the view goes on and again
 * when the hand rests after a change (300 ms); scrolling only moves the
 * marks. While the view is off the part holds no listener, timer or
 * observer.
 *
 * Not needed to open a page: the core runs this part when the switch goes
 * on (_sdA11ySync()), and reaches it through show(host) and hide().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
StyleDesigner.definePart('a11yview', function (K) {
    'use strict';

    var R = window.PGA11yReader;
    var esc = K.esc;
    var _sdT = K._sdT;

    // The kinds of mark, in the card's order.
    var KINDS = ['order', 'headings', 'names'];

    // How long the hand rests after a change before the page is read again.
    var REST_MS = 300;

    // The view while it is shown: the host handed by the editor, the last
    // reading, the layers and what is listened to.
    var V = null;

    // ── What the card says (pure) ───────────────────────────────────────

    // A kind's name, as the card and the folded head show it.
    function _avKindName(kind) {
        if (kind === 'order') return _sdT('Tab order');
        if (kind === 'headings') return _sdT('Headings');
        if (kind === 'names') return _sdT('Names');
        return '';
    }

    // The heading that carries the audit's mark: the first that skips a
    // level, as the audit counts it.
    function _avFirstSkip(headings) {
        for (var i = 0; i < (headings || []).length; i++) {
            if (headings[i].fault) return i;
        }
        return -1;
    }

    // What one kind found: `count` beside its name, `marked` in amber, and
    // the card's foot as { bold, rest } (bold may be empty). `r` is the
    // reading: the reader's lists, `jumps` per stop.
    function _avSummary(kind, r) {
        var order = r.order || [], heads = r.headings || [], regions = r.regions || [], pics = r.pictures || [], controls = r.controls || [];
        if (kind === 'order') {
            var jumps = (r.jumps || []).filter(Boolean).length;
            if (!order.length) return { count: 0, marked: 0, bold: '', rest: _sdT('The keyboard reaches nothing on this page.') };
            return {
                count: order.length,
                marked: jumps,
                bold: order.length === 1 ? _sdT('One stop.') : _sdT('{var:1} stops.', order.length),
                rest: !jumps ? '' : (jumps === 1 ? _sdT('One step jumps back on the page.') : _sdT('{var:1} steps jump back on the page.', jumps))
            };
        }
        if (kind === 'headings') {
            return {
                count: heads.length,
                marked: _avFirstSkip(heads) === -1 ? 0 : 1,
                bold: '',
                rest: heads.length ? '' : _sdT('This page has no heading.')
            };
        }
        var unnamed = pics.filter(function (p) { return p.fault; }).length + controls.filter(function (c) { return c.fault; }).length;
        if (!regions.length && !pics.length && !unnamed) return { count: 0, marked: 0, bold: '', rest: _sdT('This page has no region, picture or unnamed control.') };
        return {
            count: regions.length + pics.length,
            marked: unnamed,
            bold: (regions.length === 1 ? _sdT('One region,') : _sdT('{var:1} regions,', regions.length)) + ' ' +
                (pics.length === 1 ? _sdT('one picture.') : _sdT('{var:1} pictures.', pics.length)),
            rest: !unnamed ? '' : (unnamed === 1 ? _sdT('One place has no name.') : _sdT('{var:1} places have no name.', unnamed))
        };
    }

    // A stop's number, with why it stands where it does.
    function _avStopText(stop, jump) {
        if (jump) return _sdT('{var:1} · jumps back', stop.index);
        if (stop.tabindex > 0) return _sdT('{var:1} · tabindex {var:2}', stop.index, stop.tabindex);
        return String(stop.index);
    }

    // A role as a screen reader says it, for the tips and tags.
    function _avRoleName(role) {
        switch (role) {
            case 'link': return _sdT('Link');
            case 'button': return _sdT('Button');
            case 'checkbox': return _sdT('Checkbox');
            case 'radio': return _sdT('Radio button');
            case 'combobox': case 'listbox': return _sdT('List');
            case 'slider': return _sdT('Slider');
            case 'textbox': case 'searchbox': case 'spinbutton': case 'field': return _sdT('Field');
            case 'frame': return _sdT('Frame');
            case 'banner': return _sdT('Banner');
            case 'navigation': return _sdT('Navigation');
            case 'main': return _sdT('Main');
            case 'complementary': return _sdT('Complementary');
            case 'search': return _sdT('Search');
            case 'contentinfo': return _sdT('Content info');
            case 'form': return _sdT('Form');
            case 'region': return _sdT('Region');
        }
        return role ? role.charAt(0).toUpperCase() + role.slice(1) : _sdT('Element');
    }

    // A role and, where it has one, a name in quotes.
    function _avNamed(role, name) {
        return name ? _sdT('{var:1} “{var:2}”', _avRoleName(role), name) : _avRoleName(role);
    }

    // A heading's tag: its level, why it is assumed, or the level it skips.
    function _avHeadingTag(h, marked) {
        if (h.assumed) return _sdT('H{var:1} · assumed', h.level);
        if (marked) return _sdT('H{var:1} · skips H{var:2}', h.level, h.from + 1);
        return 'H' + h.level;
    }

    // The mark of a control with no name.
    function _avNoName(kind) {
        if (kind === 'link') return _sdT('Link · no name');
        if (kind === 'field') return _sdT('Field · no label');
        if (kind === 'frame') return _sdT('Frame · no title');
        return _sdT('Button · no name');
    }

    // The audit's own sentence for a rule (pg_a11y_issue_label()).
    function _avFaultSentence(code) {
        switch (code) {
            case 'a11y_heading_order': return _sdT('A heading level is skipped');
            case 'a11y_img_alt': return _sdT('Image without alternative text');
            case 'a11y_form_label': return _sdT('Form field without a label');
            case 'a11y_link_name': return _sdT('Link without text');
            case 'a11y_button_name': return _sdT('Button without a name');
            case 'a11y_frame_title': return _sdT('Frame without a title');
        }
        return '';
    }

    // Where a region's tag stands, so tags never cover each other: the
    // navigation's above its frame, the main's inside its top right corner,
    // the others inside their top left corner.
    function _avRegionTagPlace(role) {
        if (role === 'navigation') return 'above';
        if (role === 'main') return 'right';
        return 'left';
    }

    // ── Reading the canvas ──────────────────────────────────────────────

    // The editor's own elements in the canvas (issue badges, placeholders):
    // no node of the page, a class of the editor's.
    function chrome(el) {
        return !el.hasAttribute('data-sd-id') && /(^|\s)sd-/.test(el.getAttribute('class') || '');
    }

    function read() {
        var doc = V.doc;
        var win = doc.defaultView;
        if (!doc.body || !win) return { order: [], headings: [], regions: [], pictures: [], controls: [], jumps: [] };
        var tree = R.fromDom(doc.body, {
            skip: chrome,
            hidden: function (el) {
                var cs = win.getComputedStyle(el);
                return !!cs && (cs.display === 'none' || cs.visibility === 'hidden');
            }
        });
        var r = R.read(tree);
        var rtl = (doc.documentElement.getAttribute('dir') || doc.body.getAttribute('dir') || '').toLowerCase() === 'rtl';
        r.jumps = R.jumps(r.order.map(function (s) {
            var b = s.ref && s.ref.getBoundingClientRect ? s.ref.getBoundingClientRect() : null;
            return (b && (b.width || b.height)) ? { left: b.left, top: b.top + win.scrollY, width: b.width, height: b.height } : null;
        }), rtl);
        r.skip = _avFirstSkip(r.headings);
        return r;
    }

    // ── Where the canvas is ─────────────────────────────────────────────

    // The part of the canvas frame in view, in the canvas wrapper's
    // coordinates, and how an element's rectangle inside the frame becomes
    // the layer's pixels (the zoom on the canvas's <html> and the Fit scale).
    function view(doc) {
        var win = doc && doc.defaultView;
        var frame = win ? win.frameElement : null;
        var area = frame && frame.closest ? frame.closest('.sd-iframe-area') : null;
        var host = area ? area.closest('.sd-canvas-wrapper') : null;
        if (!host || !doc.documentElement) return null;
        var fr = frame.getBoundingClientRect();
        var ar = area.getBoundingClientRect();
        var hr = host.getBoundingClientRect();
        var left = Math.max(fr.left, ar.left + area.clientLeft);
        var top = Math.max(fr.top, ar.top + area.clientTop);
        var w = Math.min(fr.right, ar.left + area.clientLeft + area.clientWidth) - left;
        var h = Math.min(fr.bottom, ar.top + area.clientTop + area.clientHeight) - top;
        var docEl = doc.documentElement;
        var cssZoom = parseFloat(docEl.style.zoom) || 1;
        var inPage = Math.abs(docEl.getBoundingClientRect().width - docEl.clientWidth * cssZoom) >= 2;
        var k = inPage ? cssZoom : 1;
        if (frame.offsetWidth > 0) k *= fr.width / frame.offsetWidth;
        return {
            host: host,
            left: left - hr.left,
            top: top - hr.top,
            width: Math.max(0, w),
            height: Math.max(0, h),
            areaRight: hr.right - (ar.left + area.clientLeft + area.clientWidth),
            areaBottom: hr.bottom - (ar.top + area.clientTop + area.clientHeight),
            ox: fr.left - left,
            oy: fr.top - top,
            k: k
        };
    }

    function place(el, v) {
        if (!el || !el.isConnected || !el.getBoundingClientRect) return null;
        var r = el.getBoundingClientRect();
        if (!r.width && !r.height) return null;
        return { left: v.ox + r.left * v.k, top: v.oy + r.top * v.k, width: r.width * v.k, height: r.height * v.k };
    }

    function px(n) {
        return Math.round(n * 10) / 10 + 'px';
    }

    function box(cls, b, extra) {
        var d = document.createElement('div');
        d.className = cls;
        d.style.cssText = 'left:' + px(b.left) + ';top:' + px(b.top) + ';width:' + px(b.width) + ';height:' + px(b.height) + ';' + (extra || '');
        return d;
    }

    // A tag or number: a hit target that chooses its element (and opens the
    // fault's card when it carries one).
    function tag(cls, x, y, text, warn, at) {
        var t = document.createElement('button');
        t.type = 'button';
        t.className = cls + (warn ? ' sd-av-w' : '');
        t.style.left = px(x);
        t.style.top = px(y);
        t.innerHTML = (warn ? '<span class="bi bi-exclamation-triangle-fill" aria-hidden="true"></span>' : '') + esc(text);
        t._avAt = at;
        return t;
    }

    // ── Drawing the marks ───────────────────────────────────────────────

    function drawOrder(out, v) {
        var r = V.r;
        var pts = [];
        r.order.forEach(function (s, i) {
            var b = place(s.ref, v);
            if (!b) { pts.push(null); return; }
            var jump = !!r.jumps[i];
            pts.push({ x: b.left, y: b.top, jump: jump });
            out.push(tag('sd-av-n', b.left, b.top, _avStopText(s, jump), jump, { ref: s.ref, tip: _sdT('{var:1} · {var:2}', s.index, _avNamed(s.role, s.name)) }));
        });
        var svg = '';
        var last = null;
        pts.forEach(function (p) {
            if (!p) return;
            if (last) svg += '<line x1="' + last.x + '" y1="' + last.y + '" x2="' + p.x + '" y2="' + p.y + '"' + (p.jump ? ' class="sd-av-j"' : '') + '/>';
            last = p;
        });
        if (svg) {
            var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            s.setAttribute('class', 'sd-av-lines');
            s.setAttribute('width', String(v.width));
            s.setAttribute('height', String(v.height));
            s.innerHTML = svg;
            out.unshift(s);
        }
    }

    function drawHeadings(out, v) {
        V.r.headings.forEach(function (h, i) {
            var b = place(h.ref, v);
            if (!b) return;
            var marked = i === V.r.skip;
            out.push(box('sd-av-frame' + (marked ? ' sd-av-w' : ''), b));
            out.push(tag('sd-av-t sd-av-above', b.left, b.top, _avHeadingTag(h, marked), marked, { ref: h.ref, fault: marked ? h.fault : '' }));
        });
    }

    function drawNames(out, v) {
        var r = V.r;
        r.regions.forEach(function (g) {
            var b = place(g.ref, v);
            if (!b) return;
            out.push(box('sd-av-region', b));
            var where = _avRegionTagPlace(g.role);
            var t = tag('sd-av-t sd-av-' + where, where === 'right' ? b.left + b.width : b.left, b.top, _avNamed(g.role, g.name), false, { ref: g.ref });
            out.push(t);
        });
        r.pictures.forEach(function (p) {
            var b = place(p.ref, v);
            if (!b) return;
            var text = p.kind === 'text' ? '“' + p.text + '”' : (p.kind === 'decor' ? _sdT('decoration') : _sdT('no text'));
            var c = tag('sd-av-chip' + (p.kind === 'decor' ? ' sd-av-decor' : ''), b.left, b.top + b.height, text, !!p.fault, { ref: p.ref, fault: p.fault || '' });
            c.style.maxWidth = px(Math.max(40, b.width - 14));
            out.push(c);
        });
        r.controls.forEach(function (c) {
            if (!c.fault) return;
            var b = place(c.ref, v);
            if (!b) return;
            out.push(box('sd-av-frame sd-av-w', b));
            out.push(tag('sd-av-t sd-av-under', b.left, b.top + b.height, _avNoName(c.kind), true, { ref: c.ref, fault: c.fault }));
        });
    }

    function drawMarks() {
        V.frame = 0;
        var v = view(V.doc);
        if (!V.layer) {
            V.layer = document.createElement('div');
            V.layer.className = 'sd-av-layer';
            V.layer.addEventListener('click', onMarkClick);
            V.layer.addEventListener('mouseover', onMarkOver);
            V.layer.addEventListener('mouseout', onMarkOut);
        }
        var layer = V.layer;
        if (!v || !V.r || !V.kind) {
            layer.hidden = true;
            placeCard(v);
            return;
        }
        if (layer.parentNode !== v.host) v.host.appendChild(layer);
        layer.style.left = px(v.left);
        layer.style.top = px(v.top);
        layer.style.width = px(v.width);
        layer.style.height = px(v.height);
        var out = [];
        if (V.kind === 'order') drawOrder(out, v);
        else if (V.kind === 'headings') drawHeadings(out, v);
        else drawNames(out, v);
        layer.textContent = '';
        out.forEach(function (n) { layer.appendChild(n); });
        layer.hidden = false;
        placeCard(v);
        if (V.pop) placePop();
    }

    // One draw per frame however fast the canvas scrolls.
    function soon() {
        if (V && !V.frame) V.frame = requestAnimationFrame(drawMarks);
    }

    // ── The card ────────────────────────────────────────────────────────

    function narrow() {
        return window.innerWidth < 768;
    }

    function cardHTML() {
        var r = V.r;
        var kind = V.kind;
        var fold = !!V.host.state.fold;
        var chip = function (n) {
            return n ? '<span class="sd-av-fc" title="' + esc(_sdT('{var:1} marked', n)) + '"><span class="bi bi-exclamation-triangle-fill" aria-hidden="true"></span>' + n + '</span>' : '';
        };
        var close = '<button type="button" class="sd-av-xb" data-av="off" title="' + esc(_sdT('Turn off the accessibility view')) + '" aria-label="' + esc(_sdT('Turn off the accessibility view')) + '"><span class="bi bi-x-lg"></span></button>';
        if (narrow()) {
            var sel = '<select class="sd-av-sel" data-av="kind" aria-label="' + esc(_sdT('Marks')) + '"><option value="">' + esc(_sdT('No marks')) + '</option>' +
                KINDS.map(function (k) { return '<option value="' + k + '"' + (k === kind ? ' selected' : '') + '>' + esc(_avKindName(k)) + '</option>'; }).join('') + '</select>';
            return '<span class="bi bi-universal-access sd-av-ic" aria-hidden="true"></span>' + sel + '<span class="sd-av-sp"></span>' +
                (r && kind ? chip(_avSummary(kind, r).marked) : '') + close;
        }
        var h = '<div class="sd-av-head"><span class="bi bi-universal-access sd-av-ic" aria-hidden="true"></span><span class="sd-av-title">' + esc(_sdT('Accessibility view')) +
            (fold && kind ? '<small>' + esc(_avKindName(kind)) + '</small>' : '') + '</span>' +
            '<button type="button" class="sd-av-xb" data-av="fold" aria-expanded="' + (fold ? 'false' : 'true') + '" title="' + esc(fold ? _sdT('Unfold') : _sdT('Fold')) + '" aria-label="' + esc(fold ? _sdT('Unfold') : _sdT('Fold')) + '"><span class="bi ' + (fold ? 'bi-chevron-up' : 'bi-chevron-down') + '"></span></button>' +
            close + '</div>';
        if (fold) return h;
        h += '<div class="sd-av-rows" role="group" aria-label="' + esc(_sdT('Marks')) + '">';
        KINDS.forEach(function (k) {
            var s = r ? _avSummary(k, r) : null;
            h += '<button type="button" class="sd-av-row' + (k === kind ? ' sd-av-on' : '') + '" data-av="kind" data-kind="' + k + '" aria-pressed="' + (k === kind ? 'true' : 'false') + '">' +
                '<span class="sd-av-rd" aria-hidden="true"></span><span class="sd-av-name">' + esc(_avKindName(k)) + '</span>' +
                (s ? '<small>' + s.count + '</small>' + chip(s.marked) : '') + '</button>';
        });
        h += '</div>';
        if (!r || !kind) return h;
        var sum = _avSummary(kind, r);
        if (kind === 'headings' && r.headings.length) {
            h += '<div class="sd-av-outline">';
            r.headings.forEach(function (x, i) {
                h += '<button type="button" class="sd-av-ol" data-av="heading" data-i="' + i + '" style="padding-left:' + (8 + (Math.max(1, x.level) - 1) * 14) + 'px">' +
                    '<code>H' + x.level + '</code><span>' + esc(x.text || '—') + '</span>' +
                    (i === r.skip ? '<span class="sd-av-fc"><span class="bi bi-exclamation-triangle-fill" aria-hidden="true"></span>' + esc(_sdT('skips H{var:1}', x.from + 1)) + '</span>' : '') + '</button>';
            });
            h += '</div>';
        } else if (sum.bold || sum.rest) {
            h += '<div class="sd-av-foot" role="status">' + (sum.bold ? '<b>' + esc(sum.bold) + '</b> ' : '') + esc(sum.rest) + '</div>';
        }
        return h;
    }

    function drawCard() {
        if (!V.card) {
            V.card = document.createElement('div');
            V.card.setAttribute('role', 'region');
            V.card.setAttribute('aria-label', _sdT('Accessibility view'));
            V.card.addEventListener('click', onCardClick);
            V.card.addEventListener('change', onCardChange);
            V.card.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { e.stopPropagation(); V.host.off(); }
            });
        }
        V.card.className = 'sd-av-card' + (narrow() ? ' sd-av-bar' : '') + (V.host.state.fold ? ' sd-av-fold' : '');
        var keep = document.activeElement && V.card.contains(document.activeElement) ? document.activeElement.getAttribute('data-av') + '|' + (document.activeElement.getAttribute('data-kind') || '') : '';
        V.card.innerHTML = cardHTML();
        if (keep) {
            var p = keep.split('|');
            var back = V.card.querySelector('[data-av="' + p[0] + '"]' + (p[1] ? '[data-kind="' + p[1] + '"]' : ''));
            if (back) back.focus();
        }
        placeCard(view(V.doc));
    }

    function placeCard(v) {
        var c = V.card;
        if (!c) return;
        if (!v) { c.hidden = true; return; }
        if (c.parentNode !== v.host) v.host.appendChild(c);
        c.hidden = false;
        c.style.right = px(v.areaRight + (narrow() ? 0 : 12));
        c.style.bottom = px(v.areaBottom + (narrow() ? 0 : 12));
        c.style.left = narrow() ? px(v.left) : '';
    }

    function setKind(kind) {
        V.kind = (kind === V.kind) ? '' : kind;
        V.host.keep({ kind: V.kind });
        closePop();
        drawCard();
        drawMarks();
    }

    function onCardClick(e) {
        var b = e.target.closest('[data-av]');
        if (!b || b.tagName === 'SELECT') return;
        var what = b.getAttribute('data-av');
        if (what === 'off') V.host.off();
        else if (what === 'fold') {
            V.host.keep({ fold: !V.host.state.fold });
            drawCard();
        } else if (what === 'kind') setKind(b.getAttribute('data-kind'));
        else if (what === 'heading') {
            var h = V.r && V.r.headings[parseInt(b.getAttribute('data-i'), 10)];
            if (h) V.host.select(h.ref, true);
        }
    }

    function onCardChange(e) {
        if (e.target.getAttribute('data-av') !== 'kind') return;
        var k = e.target.value;
        V.kind = KINDS.indexOf(k) === -1 ? '' : k;
        V.host.keep({ kind: V.kind });
        closePop();
        drawCard();
        drawMarks();
    }

    // ── A mark under the pointer and a mark's click ─────────────────────

    function showTip(text, x, y) {
        if (!V.tip) {
            V.tip = document.createElement('div');
            V.tip.className = 'sd-av-tip';
            V.tip.setAttribute('role', 'tooltip');
        }
        var v = view(V.doc);
        if (!v || !text) { hideTip(); return; }
        if (V.tip.parentNode !== v.host) v.host.appendChild(V.tip);
        V.tip.textContent = text;
        V.tip.style.left = px(v.left + x);
        V.tip.style.top = px(v.top + y);
        V.tip.hidden = false;
    }

    function hideTip() {
        if (V && V.tip) V.tip.hidden = true;
    }

    function onMarkOver(e) {
        var t = e.target.closest('button');
        if (!t || !t._avAt || !t._avAt.tip) return;
        showTip(t._avAt.tip, parseFloat(t.style.left) || 0, (parseFloat(t.style.top) || 0) + 14);
    }

    function onMarkOut(e) {
        var t = e.target.closest('button');
        if (t && t._avAt && t._avAt.tip && !(e.relatedTarget && t.contains(e.relatedTarget))) hideTip();
    }

    function onMarkClick(e) {
        var t = e.target.closest('button');
        if (!t || !t._avAt) return;
        e.stopPropagation();
        V.host.select(t._avAt.ref, false);
        if (t._avAt.fault) openPop(t._avAt.ref, t._avAt.fault);
        else closePop();
    }

    // A named control's name, under the pointer on the canvas (Names).
    function onCanvasMove(e) {
        if (V.kind !== 'names' || !V.named) return;
        var el = e.target;
        while (el && el.nodeType === 1 && !V.named.has(el)) el = el.parentElement;
        var c = (el && el.nodeType === 1) ? V.named.get(el) : null;
        if (!c) { if (V.tipFor) { V.tipFor = null; hideTip(); } return; }
        if (V.tipFor === el) return;
        V.tipFor = el;
        var v = view(V.doc);
        var b = v ? place(el, v) : null;
        if (b) showTip(_avNamed(c.role, c.name), b.left, b.top + b.height + 4);
    }

    // The fault's card beside its element: the audit's sentence and "Show
    // in the audit". The audit has no "Fix" for the rules the view can
    // show, so none is offered.
    function openPop(ref, code) {
        closePop();
        var p = document.createElement('div');
        p.className = 'sd-av-pop';
        p.setAttribute('role', 'dialog');
        p.setAttribute('aria-label', _avFaultSentence(code));
        p.innerHTML = '<div class="sd-av-pop-t"><span class="bi bi-exclamation-triangle-fill" aria-hidden="true"></span><span>' + esc(_avFaultSentence(code)) + '</span></div>' +
            '<div class="sd-av-pop-b"><button type="button" class="sd-av-b" data-av="audit">' + esc(_sdT('Show in the audit')) + '</button></div>';
        p.addEventListener('click', function (e) {
            if (!e.target.closest('[data-av="audit"]')) return;
            closePop();
            V.host.audit();
        });
        V.pop = p;
        V.popRef = ref;
        placePop();
    }

    function placePop() {
        var v = view(V.doc);
        var b = v ? place(V.popRef, v) : null;
        if (!b) { closePop(); return; }
        if (V.pop.parentNode !== v.host) v.host.appendChild(V.pop);
        var w = 286;
        var x = b.left + b.width + 10;
        if (x + w > v.width) x = Math.max(8, b.left - w - 10);
        V.pop.style.left = px(v.left + x);
        V.pop.style.top = px(v.top + Math.max(8, Math.min(b.top, v.height - 110)));
    }

    function closePop() {
        if (V && V.pop) {
            if (V.pop.parentNode) V.pop.parentNode.removeChild(V.pop);
            V.pop = null;
            V.popRef = null;
        }
    }

    function onKey(e) {
        if (e.key === 'Escape' && V && V.pop) closePop();
    }

    function onDown(e) {
        if (V && V.pop && !V.pop.contains(e.target) && !(V.layer && V.layer.contains(e.target))) closePop();
    }

    // ── Reading again when the hand rests ───────────────────────────────

    function rest() {
        if (!V) return;
        clearTimeout(V.rest);
        V.rest = setTimeout(refresh, REST_MS);
    }

    function refresh() {
        if (!V) return;
        V.rest = 0;
        V.r = read();
        V.named = new Map();
        V.r.controls.forEach(function (c) { if (!c.fault && c.name && c.ref) V.named.set(c.ref, c); });
        if (V.pop && !(V.popRef && V.popRef.isConnected)) closePop();
        drawCard();
        drawMarks();
    }

    function listen(on) {
        var doc = V.doc;
        var win = doc.defaultView;
        var fn = on ? 'addEventListener' : 'removeEventListener';
        if (on) {
            V.mo = new MutationObserver(rest);
            if (doc.body) V.mo.observe(doc.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['alt', 'aria-label', 'aria-labelledby', 'aria-hidden', 'aria-level', 'hidden', 'href', 'role', 'style', 'tabindex', 'title', 'disabled', 'inert', 'open', 'for', 'id'] });
        } else if (V.mo) {
            V.mo.disconnect();
            V.mo = null;
        }
        if (win) {
            win[fn]('scroll', soon, { passive: true, capture: true });
            win[fn]('resize', soon);
            win[fn]('keydown', onKey, true);
        }
        doc[fn]('mousemove', onCanvasMove, true);
        doc[fn]('mousedown', onDown, true);
        window[fn]('resize', onResize);
        window[fn]('keydown', onKey, true);
        document[fn]('mousedown', onDown, true);
    }

    function onResize() {
        if (!V) return;
        var n = narrow();
        if (n !== V.narrow) { V.narrow = n; drawCard(); }
        soon();
    }

    // Shows the view over the canvas `host.doc`, or reads it again (after a
    // draw) when it is shown already. `host` is { doc, state: { kind, fold },
    // keep(patch), off(), select(el, scroll), audit() }.
    function show(host) {
        if (!R || !host || !host.doc || !host.doc.defaultView) return;
        if (V && V.doc === host.doc) {
            V.host = host;
            rest();
            return;
        }
        hide();
        var kind = host.state.kind;
        V = { host: host, doc: host.doc, kind: KINDS.indexOf(kind) === -1 ? '' : kind, r: null, named: null, narrow: narrow(), layer: null, card: null, tip: null, pop: null, popRef: null, tipFor: null, frame: 0, rest: 0, mo: null };
        listen(true);
        refresh();
    }

    function hide() {
        if (!V) return;
        listen(false);
        clearTimeout(V.rest);
        if (V.frame) cancelAnimationFrame(V.frame);
        closePop();
        [V.layer, V.card, V.tip].forEach(function (n) { if (n && n.parentNode) n.parentNode.removeChild(n); });
        V = null;
    }

    return { show: show, hide: hide };
});
