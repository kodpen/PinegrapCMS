/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'shape': "Clip shape" and "Mask" under Style › Shadow and
 * effect › More › Shape. The two lists with the rows of the chosen shape
 * or mask, the canvas while a slider is dragged, and the dashed outline
 * of the element's whole box while it has a shape or a mask. The model
 * is PGClipShape (pg_clip_shape.js); the values are written on the
 * element's custom style class, or the chosen state's rule, through the
 * core's state host (_sdStateHost()).
 *
 * Memory: docs/memory/designer/designer-options-shape.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

StyleDesigner.definePart('shape', function (K) {
    'use strict';
    var esc = K.esc;
    var _sdT = K._sdT;
    var C = window.PGClipShape;
    var OUTLINE_ID = 'sd-shape-outline';
    var HINT_KEY = 'pgSdShapeHint';
    // Per element: a Picture mask chosen with no picture yet (nothing is
    // written until one is), and the element the one-time line is for.
    var ui = { pending: {}, hint: '' };
    var dragged = null;

    function names() {
        return {
            clip: { none: _sdT('None'), circle: _sdT('Circle'), ellipse: _sdT('Ellipse'), inset: _sdT('Rounded inset'), slant: _sdT('Slant'), arrow: _sdT('Arrow') },
            mask: { none: _sdT('None'), fade: _sdT('Fade'), picture: _sdT('Picture') }
        };
    }

    // The small sign of a shape or a mask, beside its list.
    function sign(kind) {
        var d = {
            circle: '<circle cx="8" cy="8" r="6"/>',
            ellipse: '<ellipse cx="8" cy="8" rx="7" ry="4.5"/>',
            inset: '<rect x="2.5" y="2.5" width="11" height="11" rx="3"/>',
            slant: '<path d="M2 2h12v9L2 14z"/>',
            arrow: '<path d="M2 3h8l4 5-4 5H2z"/>',
            fade: '<rect x="2" y="2" width="12" height="12" rx="1" fill="url(#sd-sh-fade)" stroke="none"/><defs><linearGradient id="sd-sh-fade" x1="0" y1="0" x2="0" y2="1"><stop offset=".3" stop-color="currentColor"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>',
            picture: '<rect x="2" y="2" width="12" height="12" rx="1"/><path d="M3 12l3.5-4 2.5 3 1.5-1.5L13 12"/>',
            own: '<path d="M3 13c2-8 8-2 10-10"/>'
        }[kind] || '<path d="M3 13L13 3" stroke-dasharray="2 2"/>';
        return '<span class="input-group-text" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4">' + d + '</svg></span>';
    }

    function row(key, label, ctrl) {
        return '<div class="sd-prop-row"' + (key ? ' data-sd-sh-keys="' + key + '"' : '') + '><label class="sd-prop-label">' + esc(label) + '</label><div class="sd-prop-control">' + ctrl + '</div></div>';
    }

    function list(key, name, opts, cur, inh) {
        return '<select class="form-control form-control-sm' + (inh ? ' sd-trans-inh' : '') + '" data-sd-shp="' + key + '" aria-label="' + esc(name) + '">' +
            opts.map(function (o) { return '<option value="' + o[0] + '"' + (String(o[0]) === String(cur) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select>';
    }

    function slider(key, name, lo, hi, unit, val, inh) {
        return '<div class="sd-trans-ms' + (inh ? ' sd-trans-inh' : '') + '">' +
            '<input type="range" class="form-range-input" min="' + lo + '" max="' + hi + '" step="1" value="' + val + '" data-sd-shr="' + key + '" aria-label="' + esc(name) + '">' +
            '<div class="input-group input-group-sm"><input type="text" inputmode="decimal" class="form-control form-control-sm" data-sd-shp="' + key + '" value="' + esc(String(val)) + '" aria-label="' + esc(name) + '">' +
            '<span class="input-group-text">' + unit + '</span></div></div>';
    }

    // The nine places, as Transform's Origin draws them.
    function places(key, name, at, inh) {
        var all = K._sdTfOrigins();
        var cells = all.map(function (nm, i) {
            return '<button type="button" role="radio" class="sd-tf-o' + (i === at ? ' sd-tf-o-on' : '') + '" data-sd-sh-o="' + key + '" data-v="' + i + '" aria-checked="' + (i === at ? 'true' : 'false') + '"' +
                ' tabindex="' + (i === at ? '0' : '-1') + '" aria-label="' + esc(nm) + '"></button>';
        }).join('');
        return '<div class="sd-tf-og' + (inh ? ' sd-trans-inh' : '') + '"><div class="sd-tf-grid" role="radiogroup" aria-label="' + esc(name) + '">' + cells + '</div>' +
            '<small><b>' + esc(all[at]) + '</b>' + C.PLACES[at].replace(' ', ' · ') + '</small></div>';
    }

    function info(text) {
        return '<div class="sd-tf-fx"><i class="bi bi-info-circle" aria-hidden="true"></i><span>' + esc(text) + '</span></div>';
    }

    // { own, base, shown, inh } of one key ('clip' or 'mask') in the state.
    function values(host, key) {
        var cls = host.cls() || '';
        var text = host.text();
        var own = cls ? C.read(text, cls, host.state)[key] : null;
        var base = host.state && cls ? C.read(text, cls, '')[key] : null;
        var shown = own || base;
        var pending = key === 'mask' && !own && !!ui.pending[host.node._id];
        if (pending) shown = { kind: 'picture', url: '', size: 'contain', at: 4 };
        return { own: own, base: base, shown: shown, pending: pending, inh: !!host.state && !own && !pending && !!base };
    }

    function kindList(host, key, v) {
        var n = names()[key];
        var cur = v.own ? v.own.kind : v.pending ? 'picture' : '';
        var opts = host.state ? [['', _sdT('As Normal')], ['none', n.none]] : [['', n.none]];
        Object.keys(n).forEach(function (k) { if (k !== 'none') opts.push([k, n[k]]); });
        var label = key === 'clip' ? _sdT('Clip shape') : _sdT('Mask');
        var own = cur === 'own' ? '<option value="own" selected disabled>' + esc(_sdT('Custom')) + '</option>' : '';
        var sel = list(key + '.kind', label, opts, cur, v.inh).replace('>', '>' + own);
        return row(key, label, '<div class="input-group input-group-sm">' + sign(v.shown ? v.shown.kind : '') + sel + '</div>');
    }

    function clipRows(c, inh) {
        if (!c) return '';
        if (c.kind === 'circle') return row('', _sdT('Size'), slider('clip.size', _sdT('Size'), 1, 100, '%', c.size, inh)) + row('', _sdT('Center'), places('clip.at', _sdT('Center'), c.at, inh));
        if (c.kind === 'ellipse') {
            return row('', _sdT('Width'), slider('clip.w', _sdT('Width'), 1, 100, '%', c.w, inh)) + row('', _sdT('Height'), slider('clip.h', _sdT('Height'), 1, 100, '%', c.h, inh)) +
                row('', _sdT('Center'), places('clip.at', _sdT('Center'), c.at, inh));
        }
        if (c.kind === 'inset') return row('', _sdT('Inset'), slider('clip.inset', _sdT('Inset'), 0, 45, '%', c.inset, inh)) + row('', _sdT('Corner'), slider('clip.corner', _sdT('Corner'), 0, 100, 'px', c.corner, inh));
        if (c.kind === 'slant') {
            return row('', _sdT('Edge'), list('clip.edge', _sdT('Edge'), [['top', _sdT('Top')], ['bottom', _sdT('Bottom')], ['both', _sdT('Both')]], c.edge, inh)) +
                row('', _sdT('Amount'), slider('clip.amount', _sdT('Amount'), 1, 45, '%', c.amount, inh)) +
                row('', _sdT('Direction'), list('clip.dir', _sdT('Direction'), [['right', _sdT('Up to the right')], ['left', _sdT('Up to the left')]], c.dir, inh));
        }
        if (c.kind === 'arrow') {
            return row('', _sdT('Points'), list('clip.points', _sdT('Points'), [['right', _sdT('Right')], ['left', _sdT('Left')], ['down', _sdT('Down')], ['up', _sdT('Up')]], c.points, inh)) +
                row('', _sdT('Point'), slider('clip.point', _sdT('Point'), 1, 50, '%', c.point, inh));
        }
        return '';
    }

    function fileName(url) {
        return String(url || '').split(/[?#]/)[0].split('/').pop() || '';
    }

    function maskRows(m, inh) {
        if (!m) return '';
        if (m.kind === 'fade') {
            return row('', _sdT('Edge'), list('mask.edge', _sdT('Edge'), [['top', _sdT('Top')], ['right', _sdT('Right')], ['bottom', _sdT('Bottom')], ['left', _sdT('Left')]], m.edge, inh)) +
                row('', _sdT('Length'), slider('mask.length', _sdT('Length'), 1, 100, '%', m.length, inh));
        }
        if (m.kind !== 'picture') return '';
        var h = row('', _sdT('Picture'), '<div class="input-group input-group-sm' + (inh ? ' sd-trans-inh' : '') + '"><input type="text" class="form-control form-control-sm" readonly value="' + esc(fileName(m.url)) + '"' +
            ' placeholder="' + esc(_sdT('None')) + '" aria-label="' + esc(_sdT('Picture')) + '"><button type="button" class="btn btn-sm btn-outline theme-secondary" data-sd-sh-pick title="' + esc(_sdT('Choose a picture')) + '"' +
            ' aria-label="' + esc(_sdT('Choose a picture')) + '"><i class="bi bi-folder2-open" aria-hidden="true"></i></button></div>');
        if (!m.url) return h + info(_sdT('Nothing is hidden until a picture is chosen.'));
        return h + row('', _sdT('Size'), list('mask.size', _sdT('Size'), [['contain', _sdT('Contain')], ['cover', _sdT('Cover')], ['auto', _sdT('Original')]], m.size, inh)) +
            row('', _sdT('Position'), places('mask.at', _sdT('Position'), m.at, inh)) +
            info(_sdT('The picture\'s solid parts show the element; its clear parts hide it.'));
    }

    function boxOf(host) {
        return host.panel ? host.panel.querySelector('[data-sd-shape-box]') : null;
    }

    // Draws the two rows and what the chosen ones bring into the place the
    // core left under "More".
    function draw(host) {
        var box = boxOf(host);
        if (!box || !host.node) return;
        var clip = values(host, 'clip'), mask = values(host, 'mask');
        var h = '<div class="sd-trans-sub">' + esc(_sdT('Shape')) + '</div>' + kindList(host, 'clip', clip) + clipRows(clip.shown, clip.inh);
        if (ui.hint === host.node._id && clip.shown && clip.shown.kind !== 'none') {
            h += info(_sdT('What the shape cuts away cannot be clicked, and a shadow is cut with it.'));
        }
        h += kindList(host, 'mask', mask) + maskRows(mask.shown, mask.inh);
        box.innerHTML = h;
        ['clip', 'mask'].forEach(function (k) {
            var v = k === 'clip' ? clip : mask;
            var lbl = box.querySelector('[data-sd-sh-keys="' + k + '"] .sd-prop-label');
            if (lbl && v.own && (host.state || v.own.kind !== 'none')) {
                lbl.classList.add('sd-prop-modified');
                lbl.dataset.resetKind = 'shape';
            }
        });
        if (host.locked()) box.querySelectorAll('input, select, button').forEach(function (c) { c.disabled = true; });
        host.resetButtons(box);
        bind(host, box);
    }

    // Writes one key's new value (null takes it out) as one undo step; a
    // write that changes nothing draws the rows again.
    function put(host, key, val) {
        var before = host.text();
        host.write(function (text, cls) {
            return key === 'clip' ? C.writeClip(text, cls, host.state, val) : C.writeMask(text, cls, host.state, val);
        });
        if (host.text() === before) draw(host);
    }

    // The value shown with one of its rows changed.
    function changed(host, key, prop, v) {
        var cur = values(host, key).shown;
        if (!cur || cur.kind === 'own' || cur.kind === 'none') return null;
        var next = JSON.parse(JSON.stringify(cur));
        next[prop] = (prop === 'edge' || prop === 'dir' || prop === 'points' || prop === 'size' || prop === 'url') ? v : Number(v);
        return next;
    }

    function choose(host, key, kind) {
        var id = host.node._id;
        if (key === 'mask') delete ui.pending[id];
        if (!kind) { put(host, key, null); return; }
        if (kind === 'none') { put(host, key, { kind: 'none' }); return; }
        if (key === 'clip') {
            var seen = true;
            try { seen = !!window.localStorage.getItem(HINT_KEY); window.localStorage.setItem(HINT_KEY, '1'); } catch (e) { seen = true; }
            if (!seen) ui.hint = id;
        }
        var d = JSON.parse(JSON.stringify(C.DEFAULTS[kind]));
        d.kind = kind;
        if (kind === 'picture') {
            ui.pending[id] = true;
            put(host, 'mask', null);
            return;
        }
        put(host, key, d);
    }

    // While a slider is dragged the canvas element shows the value at once
    // (inline, !important); dropped when it is let go.
    function preview(host, key, val) {
        var el = host.canvasEl();
        if (!el || !el.style || !val) return;
        drop();
        if (key === 'clip') {
            el.style.setProperty('clip-path', C.clipCss(val), 'important');
            dragged = { el: el, props: ['clip-path'] };
            return;
        }
        var css = C.maskCss(val);
        var props = [];
        Object.keys(css).forEach(function (p) {
            el.style.setProperty(p, css[p], 'important');
            el.style.setProperty('-webkit-' + p, css[p], 'important');
            props.push(p, '-webkit-' + p);
        });
        dragged = { el: el, props: props };
    }

    function drop() {
        if (!dragged) return;
        dragged.props.forEach(function (p) { dragged.el.style.removeProperty(p); });
        dragged = null;
    }

    function pick(host) {
        if (!K._sdPickerReady() || !window.pgEditorPicker) return;
        var cur = values(host, 'mask').shown;
        window.pgEditorPicker.image(cur && cur.url || '').then(function (url) {
            if (!url || K.selectedNode !== host.node) return;
            var m = cur && cur.kind === 'picture' ? JSON.parse(JSON.stringify(cur)) : { kind: 'picture', size: 'contain', at: 4 };
            m.url = String(url);
            delete ui.pending[host.node._id];
            put(host, 'mask', m);
        });
    }

    function bind(host, box) {
        var split = function (a) { var p = String(a || '').split('.'); return { key: p[0], prop: p[1] }; };
        box.addEventListener('input', function (e) {
            var a = e.target.getAttribute && e.target.getAttribute('data-sd-shr');
            if (!a) return;
            var w = e.target.closest('.sd-trans-ms');
            var numEl = w ? w.querySelector('[data-sd-shp="' + a + '"]') : null;
            if (numEl) numEl.value = e.target.value;
            if (w) w.classList.remove('sd-trans-inh');
            var s = split(a);
            preview(host, s.key, changed(host, s.key, s.prop, e.target.value));
        });
        box.addEventListener('change', function (e) {
            var a = e.target.getAttribute && (e.target.getAttribute('data-sd-shp') || e.target.getAttribute('data-sd-shr'));
            if (!a) return;
            drop();
            var s = split(a);
            if (s.prop === 'kind') { choose(host, s.key, e.target.value); return; }
            var next = changed(host, s.key, s.prop, e.target.value.trim());
            if (next) put(host, s.key, next);
        });
        box.addEventListener('focusout', function (e) {
            if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-sd-shr')) drop();
        });
        box.addEventListener('click', function (e) {
            var t = e.target && e.target.closest ? e.target.closest('[data-sd-sh-o], [data-sd-sh-pick]') : null;
            if (!t || t.disabled) return;
            if (t.hasAttribute('data-sd-sh-pick')) { pick(host); return; }
            var s = split(t.getAttribute('data-sd-sh-o'));
            var next = changed(host, s.key, 'at', t.getAttribute('data-v'));
            if (next) put(host, s.key, next);
        });
        box.addEventListener('keydown', function (e) {
            var t = e.target;
            if (!t.hasAttribute || !t.hasAttribute('data-sd-sh-o')) return;
            var i = Number(t.getAttribute('data-v'));
            var to = { ArrowLeft: i % 3 ? i - 1 : i, ArrowRight: i % 3 < 2 ? i + 1 : i, ArrowUp: i > 2 ? i - 3 : i, ArrowDown: i < 6 ? i + 3 : i }[e.key];
            if (to === undefined) return;
            e.preventDefault();
            var c = t.parentNode.querySelector('[data-v="' + to + '"]');
            if (c && c !== t) c.focus();
        });
    }

    // The changed dot's arrow: the key's value out of the chosen state.
    function reset(host, key) {
        if (key !== 'clip' && key !== 'mask') return;
        if (key === 'mask') delete ui.pending[host.node._id];
        put(host, key, null);
    }

    // The dashed outline of the element's whole box while it has a shape
    // or a mask, so what is cut away shows as the gap between the two.
    // host null takes it away.
    function outline(host, doc) {
        doc = host ? host.canvasDoc : doc;
        var g = doc ? doc.getElementById(OUTLINE_ID) : null;
        var el = host ? host.canvasEl() : null;
        if (!el || !el.getBoundingClientRect || !doc || !doc.body) {
            if (g && g.parentNode) g.parentNode.removeChild(g);
            return;
        }
        var win = doc.defaultView || window;
        var r = el.getBoundingClientRect();
        if (!g) {
            g = doc.createElement('div');
            g.id = OUTLINE_ID;
            g.setAttribute('aria-hidden', 'true');
            g.style.cssText = 'position:absolute;z-index:2147482990;pointer-events:none;border:1px dashed rgba(13,110,253,.85);box-sizing:border-box;';
            doc.body.appendChild(g);
        }
        g.style.left = (r.left + win.pageXOffset) + 'px';
        g.style.top = (r.top + win.pageYOffset) + 'px';
        g.style.width = r.width + 'px';
        g.style.height = r.height + 'px';
    }

    return { draw: draw, reset: reset, outline: outline };
});
