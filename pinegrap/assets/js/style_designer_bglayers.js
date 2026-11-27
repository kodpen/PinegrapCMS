/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'bglayers': the background layers of Style › Color and
 * background. A line a layer (the first in front), its rows opened under
 * it, a gradient's strip with a handle a stop, the order by drag or keys.
 * The model is PGBgLayers (pg_bg_layers.js); the values are the
 * background properties of the element's style attribute.
 *
 * Memory: docs/memory/designer/designer-options-bg-layers.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

StyleDesigner.definePart('bglayers', function (K) {
    'use strict';
    var esc = K.esc;
    var _sdT = K._sdT;
    var L = window.PGBgLayers;
    // What is open, per element: the layer, its chosen stop, "More".
    var ui = { id: null, open: -1, cur: 0, stop: 0, more: false, menu: false, pal: '', gone: {}, tried: {}, said: '' };
    var BLENDS = ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity'];

    function palName(p) {
        return ({ primary: _sdT('Primary'), secondary: _sdT('Secondary'), success: _sdT('Success'), danger: _sdT('Danger'),
            light: _sdT('Light'), dark: _sdT('Dark'), 'body-bg': _sdT('Body background') })[p] || p;
    }

    function kindName(l) {
        return l.kind === 'picture' ? _sdT('Picture') : l.kind === 'gradient' ? _sdT('Gradient') : _sdT('Colour');
    }

    // A theme variable as the canvas page has it ('' outside the canvas).
    function siteVar(name) {
        var doc = K.canvasDoc;
        try { return doc ? doc.defaultView.getComputedStyle(doc.documentElement).getPropertyValue(name).trim() : ''; } catch (e) { return ''; }
    }

    // CSS with the palette's variables replaced by the canvas's values, for
    // the small pictures drawn in the panel.
    function shown(css) {
        return String(css).replace(/rgba\(var\(--bs-([a-z-]+)-rgb\), ([\d.]+)\)/g, function (m, p, a) {
            var rgb = siteVar('--bs-' + p + '-rgb');
            return rgb ? 'rgba(' + rgb + ', ' + a + ')' : m;
        }).replace(/var\(--bs-([a-z-]+)\)/g, function (m, p) { return siteVar('--bs-' + p) || m; });
    }

    function rgbOf(c) {
        var rgb = c.p ? siteVar('--bs-' + c.p + '-rgb').split(',').map(Number) : L.hexRgb(c.v);
        if (!rgb || rgb.length !== 3 || rgb.some(isNaN)) return null;
        return rgb.concat([c.a == null ? 100 : c.a]);
    }

    function colorName(c) {
        return (c.p ? palName(c.p) : c.v) + ((c.a == null ? 100 : c.a) < 100 ? ' · ' + c.a + '%' : '');
    }

    function fileName(url) {
        return String(url || '').split(/[?#]/)[0].split('/').pop() || url || '';
    }

    function layerCss(l) {
        var one = L.write([l]);
        return shown(one['background-image']);
    }

    function sizeName(v) {
        return !v || v === 'auto' ? _sdT('Original') : v === 'cover' ? _sdT('Cover') : v === 'contain' ? _sdT('Contain') : v;
    }

    function summary(l) {
        if (l.kind === 'color') return colorName(l.c);
        if (l.kind === 'picture') return fileName(l.url) + ' · ' + sizeName(l.size) + (ui.gone[l.url] ? ' · ' + _sdT('not found') : '');
        var n = _sdT('{var} stops', l.stops.length);
        if (l.type === 'radial') return _sdT('Radial') + ' · ' + (l.shape === 'circle' ? _sdT('Circle') : _sdT('Ellipse')) + ' · ' + n;
        if (l.type === 'conic') return _sdT('Conic') + ' · ' + n;
        return _sdT('Linear') + ' ' + Math.round(l.angle) + '° · ' + n;
    }

    function said(l) {
        if (l.kind !== 'gradient') return kindName(l) + ', ' + summary(l);
        return _sdT('Gradient') + ', ' + (l.type === 'linear' ? _sdT('linear {var} degrees', Math.round(l.angle)) : l.type === 'radial' ? _sdT('radial') : _sdT('conic')) +
            ', ' + _sdT('{var} stops', l.stops.length);
    }

    function styleOf(node) {
        var st = {};
        ((node.props && node.props._attrs) || []).forEach(function (a) {
            if (a && a.name === 'style') K._parseInlineStyle(a.value).forEach(function (d) { st[d.name.toLowerCase()] = d.value; });
        });
        return st;
    }

    // ── rows ──
    function row(label, ctrl, cls) {
        return '<div class="sd-prop-row' + (cls ? ' ' + cls : '') + '"><label class="sd-prop-label">' + esc(label) + '</label><div class="sd-prop-control">' + ctrl + '</div></div>';
    }

    function select(key, opts, val) {
        return '<select class="form-control form-control-sm" data-bgl="' + key + '">' + opts.map(function (o) {
            return '<option value="' + esc(o[0]) + '"' + (o[0] === val ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
        }).join('') + '</select>';
    }

    function slider(key, lo, hi, val, unit, name) {
        return '<div class="sd-trans-ms"><input type="range" class="form-range-input" min="' + lo + '" max="' + hi + '" step="1" value="' + val + '" data-bgl-range="' + key + '" aria-label="' + esc(name) + '">' +
            '<div class="input-group input-group-sm"><input type="text" inputmode="decimal" class="form-control form-control-sm" data-bgl="' + key + '" value="' + val + '" aria-label="' + esc(name) + '">' +
            '<span class="input-group-text">' + unit + '</span></div></div>';
    }

    function places(key, val, name) {
        var names = K._sdTfOrigins(), at = L.PLACES.indexOf(val);
        if (at < 0) at = 4;
        return '<div class="sd-tf-og"><div class="sd-tf-grid" role="radiogroup" aria-label="' + esc(name) + '">' + names.map(function (nm, i) {
            return '<button type="button" role="radio" class="sd-tf-o' + (i === at ? ' sd-tf-o-on' : '') + '" data-bgl-place="' + key + '" data-i="' + i + '" aria-checked="' + (i === at) + '"' +
                ' tabindex="' + (i === at ? 0 : -1) + '" aria-label="' + esc(nm) + '"></button>';
        }).join('') + '</div><small><b>' + esc(names[at]) + '</b>' + (at % 3) * 50 + '% · ' + Math.floor(at / 3) * 50 + '%</small></div>';
    }

    function colorCtrl(key, c) {
        var open = ui.pal === key;
        var h = '<div class="sd-bgl-col"><button type="button" class="sd-bgl-pick" data-bgl-pal="' + key + '" aria-expanded="' + open + '">' +
            '<span class="sd-bgl-sw sd-bgl-chk"><span class="sd-bgl-sw" style="display:block;background:' + esc(shown(L.color({ p: c.p, v: c.v }))) + '"></span></span>' +
            '<span>' + esc(c.p ? palName(c.p) : c.v) + '</span></button>' +
            (c.p ? '<i class="bi bi-link-45deg sd-bgl-lk" title="' + esc(_sdT('A palette colour: a palette change follows')) + '"></i>' : '');
        if (open) {
            h += '<div class="sd-bgl-pal" role="listbox">' + L.PALETTE.map(function (p) {
                return '<button type="button" role="option" data-bgl-color="' + key + '" data-p="' + p + '"' + (c.p === p ? ' class="sd-bgl-pal-on" aria-selected="true"' : '') + '>' +
                    '<span class="sd-bgl-dot" style="background:' + esc(siteVar('--bs-' + p) || 'transparent') + '"></span>' + esc(palName(p)) + '</button>';
            }).join('') + '<label class="d-flex align-items-center gap-2 px-2 py-1 m-0"><input type="color" data-bgl-custom="' + key + '" value="' +
                esc(/^#[\da-f]{6}$/i.test(c.v || '') ? c.v : '#ffffff') + '" style="width:22px;height:18px;padding:0;border:0;background:none"><span>' + esc(_sdT('Custom…')) + '</span></label></div>';
        }
        return h + '</div>';
    }

    function moreRows(l) {
        if (!ui.more) return '<button type="button" class="sd-tf-more" data-bgl-more aria-expanded="false"><i class="bi bi-chevron-down" aria-hidden="true"></i>' + esc(_sdT('More')) + '</button>';
        return row(_sdT('Repeat'), select('repeat', [['', _sdT('No repeat')], ['repeat', _sdT('Repeat')], ['repeat-x', _sdT('Across')], ['repeat-y', _sdT('Down')]],
                l.repeat === 'no-repeat' ? '' : (l.repeat || (l.kind === 'picture' ? '' : 'repeat')))) +
            row(_sdT('Attach'), select('attach', [['', _sdT('Scroll')], ['fixed', _sdT('Fixed')]], l.attach === 'fixed' ? 'fixed' : '')) +
            row(_sdT('Blend'), select('blend', BLENDS.map(function (b) { return [b === 'normal' ? '' : b, b.charAt(0).toUpperCase() + b.slice(1).replace('-', ' ')]; }), l.blend || '')) +
            '<button type="button" class="sd-tf-more" data-bgl-more aria-expanded="true"><i class="bi bi-chevron-up" aria-hidden="true"></i>' + esc(_sdT('Less')) + '</button>';
    }

    function bodyHtml(node, layers, i) {
        var l = layers[i], h = '';
        if (l.kind === 'color') {
            return row(_sdT('Color'), colorCtrl('c', l.c)) + row(_sdT('Opacity'), slider('ca', 0, 100, l.c.a == null ? 100 : l.c.a, '%', _sdT('Opacity')));
        }
        if (l.kind === 'picture') {
            if (ui.gone[l.url]) h += '<div class="sd-bgl-note sd-bgl-warn"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>' + esc(_sdT('This picture is no longer in the files. The layer draws nothing until it is replaced.')) + '</span></div>';
            var custom = l.size && ['cover', 'contain', 'auto'].indexOf(l.size) === -1;
            var wh = custom ? l.size.split(/\s+/) : [];
            h += row(_sdT('Picture'), '<div class="sd-bgl-pic"><span class="sd-bgl-sw" style="background-image:url(&quot;' + esc(String(l.url).replace(/["\\]/g, '')) + '&quot;)"></span>' +
                    '<span title="' + esc(l.url) + '">' + esc(fileName(l.url)) + '</span>' +
                    '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-bgl-file title="' + esc(_sdT('Choose a picture')) + '" aria-label="' + esc(_sdT('Choose a picture')) + '"><i class="bi bi-folder2-open"></i></button></div>') +
                row(_sdT('Size'), select('size', [['cover', _sdT('Cover')], ['contain', _sdT('Contain')], ['', _sdT('Original')], ['custom', _sdT('Custom')]], custom ? 'custom' : (l.size === 'auto' ? '' : l.size || '')) +
                    (custom ? '<div class="sd-bgl-wh"><input type="text" class="form-control form-control-sm" data-bgl="sw" value="' + esc(wh[0] || 'auto') + '" aria-label="' + esc(_sdT('Width')) + '">' +
                        '<input type="text" class="form-control form-control-sm" data-bgl="sh" value="' + esc(wh[1] || 'auto') + '" aria-label="' + esc(_sdT('Height')) + '"></div>' : ''));
            // One picture alone that its box cuts takes the Focus control.
            h += layers.length === 1 && K._sdFocusTakes(node, 'background') ? K._sdFocusPlace('background') : row(_sdT('Position'), places('position', L.shownPlace(l.position), _sdT('Position')));
            return h + moreRows(l);
        }
        var s = l.stops[Math.min(ui.stop, l.stops.length - 1)], n = l.stops.length;
        h += row(_sdT('Type'), select('type', [['linear', _sdT('Linear')], ['radial', _sdT('Radial')], ['conic', _sdT('Conic')]], l.type));
        if (l.type === 'linear') h += row(_sdT('Angle'), slider('angle', 0, 360, Math.round(l.angle), '°', _sdT('Angle')));
        if (l.type === 'radial') h += row(_sdT('Shape'), select('shape', [['circle', _sdT('Circle')], ['ellipse', _sdT('Ellipse')]], l.shape));
        if (l.type === 'conic') h += row(_sdT('Start'), slider('from', 0, 360, Math.round(l.from || 0), '°', _sdT('Start')));
        if (l.type !== 'linear') h += row(_sdT('Center'), places('at', l.at, _sdT('Center')));
        h += '<div class="sd-bgl-strip" tabindex="0" role="group" aria-label="' + esc(stopSaid(l)) + '"><div class="sd-bgl-gst sd-bgl-chk" data-bgl-strip><i style="background:' +
            esc(shown(L.gradient({ type: 'linear', angle: 90, stops: l.stops }))) + '"></i></div><div class="sd-bgl-gsh">' + l.stops.map(function (st, k) {
                return '<u data-bgl-stop="' + k + '" class="' + (st === s ? 'sd-bgl-on' : '') + '" style="left:' + st.pos + '%;background:' + esc(shown(L.color(st.c))) + '"></u>';
            }).join('') + '</div></div>' +
            '<div class="sd-bgl-gsn"><span>' + esc(n < L.MAX_STOPS ? _sdT('Press the strip to add a stop.') : _sdT('Sixteen stops is the most.')) + '</span></div>' +
            '<div class="sd-bgl-gsn"><span>' + esc(_sdT('Stop {var:1} of {var:2}', [l.stops.indexOf(s) + 1, n])) + '</span>' +
            '<button type="button" class="sd-bgl-bin" data-bgl-stopdel' + (n <= L.MIN_STOPS ? ' disabled' : '') + ' aria-label="' + esc(_sdT('Remove the stop')) + '"><i class="bi bi-trash3"></i></button></div>' +
            row(_sdT('Color'), colorCtrl('s', s.c)) +
            row(_sdT('Opacity'), slider('sa', 0, 100, s.c.a == null ? 100 : s.c.a, '%', _sdT('Opacity'))) +
            row(_sdT('Position'), slider('sp', 0, 100, Math.round(s.pos), '%', _sdT('Position')));
        return h + moreRows(l);
    }

    function stopSaid(l) {
        var s = l.stops[Math.min(ui.stop, l.stops.length - 1)];
        return _sdT('Stop {var:1} of {var:2}', [l.stops.indexOf(s) + 1, l.stops.length]) + ', ' + (s.c.p ? palName(s.c.p) : s.c.v) + ', ' +
            _sdT('{var} per cent opacity', s.c.a == null ? 100 : s.c.a) + ', ' + _sdT('at {var} per cent', Math.round(s.pos));
    }

    function listHtml(node, layers, off) {
        var n = layers.length, h = '';
        if (!n) h += '<p class="sd-bgl-none">' + esc(_sdT('No layer. The background colour above shows.')) + '</p>';
        else {
            h += '<div class="sd-bgl-list" role="listbox" tabindex="' + (off ? -1 : 0) + '" aria-label="' + esc(_sdT('Background layers, {var}', n)) + '" aria-activedescendant="sd-bgl-l' + ui.cur + '">';
            layers.forEach(function (l, i) {
                var on = i === ui.open;
                h += '<div class="sd-bgl-ly' + (on ? ' sd-bgl-on' : '') + '" id="sd-bgl-l' + i + '" role="option" aria-selected="' + (i === ui.cur) + '" data-bgl-line="' + i + '"' +
                    ' aria-label="' + esc(said(l) + ', ' + _sdT('layer {var:1} of {var:2}', [i + 1, n])) + '">' +
                    '<i class="bi bi-grip-vertical sd-bgl-grip" data-bgl-grip="' + i + '" aria-hidden="true"></i>' +
                    '<span class="sd-bgl-th sd-bgl-chk"><span class="sd-bgl-th" style="display:block;background:' + esc(layerCss(l)) + ';background-size:cover"></span></span>' +
                    '<b>' + esc(kindName(l)) + '</b><small>' + esc(summary(l)) + '</small>' +
                    (on ? '<button type="button" class="sd-bgl-bin" data-bgl-del="' + i + '" aria-label="' + esc(_sdT('Remove the layer')) + '"><i class="bi bi-trash3"></i></button>' : '') +
                    '<i class="bi bi-chevron-' + (on ? 'up' : 'down') + '" aria-hidden="true"></i></div>';
                if (on) h += '<div class="sd-bgl-body">' + bodyHtml(node, layers, i) + '</div>';
            });
            h += '</div>';
        }
        var full = n >= L.MAX_LAYERS;
        h += '<div class="sd-bgl-foot"><span>' + esc(full ? _sdT('8 layers, the most an element takes') : n ? _sdT('{var} layers · the first is in front', n) : '') + '</span>' +
            '<div class="sd-bgl-add"><button type="button" class="btn btn-sm btn-outline theme-secondary" data-bgl-add aria-expanded="' + ui.menu + '"' + (full ? ' disabled' : '') + '>' +
            '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' + esc(_sdT('Add layer')) + '</button>' +
            '<div class="sd-bgl-menu"' + (ui.menu && !full ? '' : ' hidden') + '>' +
            '<button type="button" data-bgl-new="color"><i class="bi bi-square-fill"></i>' + esc(_sdT('Colour')) + '</button>' +
            '<button type="button" data-bgl-new="gradient"><i class="bi bi-circle-half"></i>' + esc(_sdT('Gradient')) + '</button>' +
            '<button type="button" data-bgl-new="picture"><i class="bi bi-image"></i>' + esc(_sdT('Picture')) + '</button></div></div></div>';
        return h + '<div class="visually-hidden" aria-live="polite" data-bgl-said>' + esc(ui.said) + '</div>';
    }

    // ── draw and write ──
    function draw(host, node, add) {
        if (!host || !host.isConnected || !node) return;
        if (ui.id !== node._id) ui = { id: node._id, open: -1, cur: 0, stop: 0, more: false, menu: false, pal: '', gone: ui.gone, tried: ui.tried, said: '' };
        if (add) ui.menu = true;
        var r = L.read(styleOf(node)), off = !!K.optState;
        host._bgl = { node: node, layers: r ? r.layers : null };
        if (!r) {
            host.innerHTML = '<div class="sd-bgl-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>' + esc(_sdT('This background is written by hand and is kept as it is.')) + '</span></div>' +
                '<div class="sd-bgl-foot"><span></span><button type="button" class="btn btn-sm btn-outline theme-secondary" data-bgl-start' + (off ? ' disabled' : '') + '>' + esc(_sdT('Start a list')) + '</button></div>';
        } else {
            ui.open = Math.min(ui.open, r.layers.length - 1);
            ui.cur = Math.max(0, Math.min(ui.cur, r.layers.length - 1));
            host.innerHTML = listHtml(node, r.layers, off);
            checkPictures(host, r.layers);
        }
        if (off) host.querySelectorAll('button, input, select').forEach(function (c) { c.disabled = true; });
        if (r && r.layers.length === 1 && host.querySelector('[data-sd-focus]')) K._sdFocusBind(node);
        if (!host._bglBound) bind(host);
    }

    // A picture no longer in the files says so on its line.
    function checkPictures(host, layers) {
        layers.forEach(function (l) {
            if (l.kind !== 'picture' || !l.url || ui.tried[l.url]) return;
            ui.tried[l.url] = true;
            var img = new Image();
            img.onerror = function () { ui.gone[l.url] = true; if (host.isConnected && host._bgl) draw(host, host._bgl.node); };
            img.src = l.url;
        });
    }

    function commit(host, layers, say) {
        var node = host._bgl.node;
        if (K.selectedNode !== node) return;
        K.saveState();
        var vals = L.write(layers);
        Object.keys(vals).forEach(function (k) { K.setAttrStyleProp(node, k, vals[k]); });
        if (node.props._bgGrad) node.props._bgGrad = null;
        if (say) ui.said = say;
        K._sdRenderNode(node, K.renderCanvas);
        K.renderAttrsPanel();
        K.renderHtmlTree();
        draw(host, node);
        if (say) {
            var live = host.querySelector('[data-bgl-said]');
            if (live) live.textContent = say;
        }
    }

    // While a slider or a stop is dragged the canvas shows the value at
    // once; it is written when let go.
    function preview(host, layers) {
        var el = K._sdCanvasInnerOf(host._bgl.node);
        if (el && el.style) el.style.setProperty('background-image', L.write(layers)['background-image']);
        var strip = host.querySelector('[data-bgl-strip] > i');
        var l = layers[ui.open];
        if (strip && l && l.kind === 'gradient') strip.style.background = shown(L.gradient({ type: 'linear', angle: 90, stops: l.stops }));
    }

    function newLayer(kind) {
        if (kind === 'color') return { kind: 'color', c: { p: 'primary', a: 20 } };
        if (kind === 'picture') return { kind: 'picture', url: '', size: 'cover', position: 'center center', repeat: 'no-repeat', attach: '', blend: '' };
        return { kind: 'gradient', type: 'linear', angle: 180, shape: 'ellipse', at: 'center center', from: 0, size: '', position: '', repeat: '', attach: '', blend: '',
            stops: [{ c: { p: 'dark', a: 0 }, pos: 0 }, { c: { p: 'dark', a: 70 }, pos: 100 }] };
    }

    function chosen(l) {
        return l.stops[Math.min(ui.stop, l.stops.length - 1)];
    }

    // Stops in order, the chosen one kept; hints go with a change of stops.
    function tidy(l, keep) {
        l.stops.forEach(function (s) { s.hint = null; });
        l.stops.sort(function (a, b) { return a.pos - b.pos; });
        ui.stop = Math.max(0, l.stops.indexOf(keep));
    }

    function setVal(l, key, v) {
        var num = Math.round(parseFloat(v));
        if (key === 'ca') l.c.a = isNaN(num) ? 100 : Math.max(0, Math.min(100, num));
        else if (key === 'sa') chosen(l).c.a = isNaN(num) ? 100 : Math.max(0, Math.min(100, num));
        else if (key === 'sp') { var s = chosen(l); s.pos = isNaN(num) ? s.pos : Math.max(0, Math.min(100, num)); tidy(l, s); }
        else if (key === 'angle' || key === 'from') l[key] = isNaN(num) ? 0 : ((num % 360) + 360) % 360;
        else if (key === 'size') l.size = v === 'custom' ? 'auto auto' : v;
        else if (key === 'sw' || key === 'sh') {
            var wh = String(l.size || 'auto auto').split(/\s+/);
            var t = String(v).trim() || 'auto';
            if (/^[\d.]+$/.test(t)) t += 'px';
            wh[key === 'sw' ? 0 : 1] = t.replace(/[^\w.%-]/g, '');
            l.size = (wh[0] || 'auto') + ' ' + (wh[1] || 'auto');
        } else if (key === 'repeat') l.repeat = v || (l.kind === 'picture' ? 'no-repeat' : '');
        else l[key] = v;
    }

    function bind(host) {
        host._bglBound = true;
        var copy = function () { return JSON.parse(JSON.stringify(host._bgl.layers || [])); };
        var cur = function (layers) { return layers[ui.open]; };

        host.addEventListener('click', function (e) {
            var t = e.target.closest('button, [data-bgl-line], [data-bgl-strip]');
            if (!t || t.disabled || !host._bgl || K.optState) return;
            var layers = copy(), l = cur(layers), a = function (n) { return t.getAttribute(n); };
            if (t.hasAttribute('data-bgl-start')) {
                var go = function (ok) { if (ok) commit(host, [], ''); };
                var q = { title: _sdT('Start a list'), message: _sdT('Replace this background with a list of layers?'), confirmText: _sdT('Replace'), cancelText: _sdT('Cancel') };
                if (typeof window.pgConfirm === 'function') window.pgConfirm(q).then(go); else go(window.confirm(q.message));
            } else if (t.hasAttribute('data-bgl-add')) {
                ui.menu = !ui.menu; draw(host, host._bgl.node);
            } else if (a('data-bgl-new')) {
                ui.menu = false; ui.open = 0; ui.cur = 0; ui.stop = 0; ui.more = false;
                layers.unshift(newLayer(a('data-bgl-new')));
                commit(host, layers, _sdT('“{var:1}” is now layer {var:2} of {var:3}.', [kindName(layers[0]), 1, layers.length]));
                if (layers[0].kind === 'picture') pick(host);
            } else if (a('data-bgl-del')) {
                var gone = layers.splice(+a('data-bgl-del'), 1)[0];
                ui.open = -1;
                commit(host, layers, _sdT('“{var}” is removed.', kindName(gone)));
            } else if (a('data-bgl-line') && !e.target.closest('.sd-bgl-bin')) {
                var i = +a('data-bgl-line');
                ui.open = ui.open === i ? -1 : i; ui.cur = i; ui.stop = 0; ui.pal = '';
                draw(host, host._bgl.node);
                var list = host.querySelector('.sd-bgl-list');
                if (list) list.focus();
            } else if (t.hasAttribute('data-bgl-more')) {
                ui.more = !ui.more; draw(host, host._bgl.node);
            } else if (a('data-bgl-place')) {
                setVal(l, a('data-bgl-place'), L.PLACES[+a('data-i')]); commit(host, layers);
            } else if (a('data-bgl-pal')) {
                ui.pal = ui.pal === a('data-bgl-pal') ? '' : a('data-bgl-pal'); draw(host, host._bgl.node);
            } else if (a('data-bgl-color')) {
                var tc = a('data-bgl-color') === 'c' ? l.c : chosen(l).c;
                tc.p = a('data-p'); delete tc.v; ui.pal = '';
                commit(host, layers);
            } else if (a('data-bgl-stop') !== null && a('data-bgl-stop') !== undefined) {
                ui.stop = +a('data-bgl-stop'); draw(host, host._bgl.node);
            } else if (t.hasAttribute('data-bgl-stopdel')) {
                l.stops.splice(ui.stop, 1); ui.stop = Math.max(0, ui.stop - 1); tidy(l, l.stops[ui.stop]);
                commit(host, layers, _sdT('Stop removed.'));
            } else if (t.hasAttribute('data-bgl-strip') && l.stops.length < L.MAX_STOPS) {
                var box = t.getBoundingClientRect();
                addStop(host, layers, l, Math.round(Math.max(0, Math.min(1, (e.clientX - box.left) / (box.width || 1))) * 100));
            } else if (t.hasAttribute('data-bgl-file')) {
                pick(host);
            }
        });

        host.addEventListener('change', function (e) {
            var t = e.target, key = t.getAttribute('data-bgl') || t.getAttribute('data-bgl-range');
            var custom = t.getAttribute('data-bgl-custom');
            if (!host._bgl || K.optState || (!key && !custom)) return;
            var layers = copy(), l = cur(layers);
            if (!l) return;
            if (custom) {
                var c = custom === 'c' ? l.c : chosen(l).c;
                delete c.p; c.v = t.value; ui.pal = '';
            } else setVal(l, key, t.value);
            if (key === 'type') ui.stop = Math.min(ui.stop, l.stops.length - 1);
            commit(host, layers);
        });

        host.addEventListener('input', function (e) {
            var t = e.target, key = t.getAttribute('data-bgl-range');
            if (!key || !host._bgl) return;
            var layers = copy(), l = cur(layers);
            if (!l) return;
            setVal(l, key, t.value);
            var num = t.parentNode.querySelector('[data-bgl="' + key + '"]');
            if (num) num.value = t.value;
            preview(host, layers);
        });

        // A stop's handle dragged along the strip; a layer's grip dragged
        // through the list. Each is one undo step, written when let go.
        host.addEventListener('pointerdown', function (e) {
            var h = e.target.closest('[data-bgl-stop], [data-bgl-grip]');
            if (!h || !host._bgl || K.optState || e.button !== 0) return;
            e.preventDefault();
            var layers = copy(), grip = h.hasAttribute('data-bgl-grip');
            var from = +(grip ? h.getAttribute('data-bgl-grip') : h.getAttribute('data-bgl-stop'));
            var l = cur(layers), to = from, ins = null, moved = false;
            if (!grip) { ui.stop = from; h.classList.add('sd-bgl-on'); }
            var lines = Array.prototype.slice.call(host.querySelectorAll('[data-bgl-line]'));
            if (grip) lines[from].classList.add('sd-bgl-lift');
            var move = function (ev) {
                moved = true;
                if (!grip) {
                    var box = host.querySelector('[data-bgl-strip]').getBoundingClientRect();
                    l.stops[from].pos = Math.round(Math.max(0, Math.min(1, (ev.clientX - box.left) / (box.width || 1))) * 100);
                    h.style.left = l.stops[from].pos + '%';
                    preview(host, layers);
                    return;
                }
                to = lines.length - 1;
                for (var i = 0; i < lines.length; i++) {
                    var r = lines[i].getBoundingClientRect();
                    if (ev.clientY < r.top + r.height / 2) { to = i > from ? i - 1 : i; break; }
                }
                if (!ins) { ins = document.createElement('div'); ins.className = 'sd-bgl-ins'; }
                var at = lines[to > from ? to : to];
                at.parentNode.insertBefore(ins, to > from ? at.nextSibling : at);
            };
            var up = function () {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
                if (ins && ins.parentNode) ins.parentNode.removeChild(ins);
                if (!moved) { if (grip) lines[from].classList.remove('sd-bgl-lift'); else draw(host, host._bgl.node); return; }
                if (!grip) { tidy(l, l.stops[from]); commit(host, layers); return; }
                if (to === from) { lines[from].classList.remove('sd-bgl-lift'); return; }
                reorder(host, from, to);
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
        });

        host.addEventListener('keydown', function (e) {
            if (!host._bgl || K.optState) return;
            var layers = copy(), list = e.target.classList.contains('sd-bgl-list'), strip = e.target.classList.contains('sd-bgl-strip');
            var k = e.key, n = layers.length;
            if (list && (k === 'ArrowUp' || k === 'ArrowDown')) {
                e.preventDefault();
                var to = Math.max(0, Math.min(n - 1, ui.cur + (k === 'ArrowUp' ? -1 : 1)));
                if (e.altKey) { if (to !== ui.cur) reorder(host, ui.cur, to); return; }
                ui.cur = to; draw(host, host._bgl.node); refocus(host, '.sd-bgl-list');
            } else if (list && k === 'Enter') {
                e.preventDefault(); ui.open = ui.open === ui.cur ? -1 : ui.cur; ui.stop = 0;
                draw(host, host._bgl.node); refocus(host, '.sd-bgl-list');
            } else if (list && (k === 'Delete' || k === 'Backspace') && n) {
                e.preventDefault();
                var gone = layers.splice(ui.cur, 1)[0];
                ui.open = -1;
                commit(host, layers, _sdT('“{var}” is removed.', kindName(gone)));
                refocus(host, '.sd-bgl-list');
            } else if (strip) {
                var l = layers[ui.open], s = chosen(l);
                if (k === 'ArrowLeft' || k === 'ArrowRight') {
                    e.preventDefault();
                    var d = k === 'ArrowLeft' ? -1 : 1;
                    if (e.shiftKey) { s.pos = Math.max(0, Math.min(100, Math.round(s.pos) + d)); tidy(l, s); commit(host, layers, stopSaid(l)); }
                    else { ui.stop = Math.max(0, Math.min(l.stops.length - 1, ui.stop + d)); draw(host, host._bgl.node); say(host, stopSaid(l)); }
                } else if (k === 'Enter' && l.stops.length < L.MAX_STOPS) {
                    e.preventDefault(); addStop(host, layers, l, L.widest(l.stops));
                } else if ((k === 'Delete' || k === 'Backspace') && l.stops.length > L.MIN_STOPS) {
                    e.preventDefault(); l.stops.splice(ui.stop, 1); ui.stop = Math.max(0, ui.stop - 1); tidy(l, l.stops[ui.stop]);
                    commit(host, layers, _sdT('Stop removed.'));
                } else return;
                refocus(host, '.sd-bgl-strip');
            }
        });
    }

    function say(host, text) {
        var live = host.querySelector('[data-bgl-said]');
        if (live) live.textContent = text;
    }

    function refocus(host, what) {
        var el = host.querySelector(what);
        if (el) el.focus();
    }

    function addStop(host, layers, l, pos) {
        var s = { c: L.mixAt(l.stops, pos, rgbOf), pos: pos };
        l.stops.push(s);
        tidy(l, s);
        commit(host, layers, _sdT('Stop added.') + ' ' + stopSaid(l));
    }

    function reorder(host, from, to) {
        var layers = L.move(host._bgl.layers, from, to);
        var wasOpen = ui.open;
        ui.open = wasOpen === from ? to : wasOpen === -1 ? -1 : L.move(host._bgl.layers.map(function (x, i) { return i; }), from, to).indexOf(wasOpen);
        ui.cur = to;
        commit(host, JSON.parse(JSON.stringify(layers)), _sdT('“{var:1}” is now layer {var:2} of {var:3}.', [kindName(layers[to]), to + 1, layers.length]));
        refocus(host, '.sd-bgl-list');
    }

    // The file picker the old "Bg Image" row opened; the open picture
    // layer takes the chosen file.
    function pick(host) {
        if (!K._sdPickerReady() || !window.pgEditorPicker) return;
        var at = ui.open, l = (host._bgl.layers || [])[at];
        window.pgEditorPicker.image(l && l.url || '').then(function (url) {
            if (!url || !host._bgl) return;
            var layers = JSON.parse(JSON.stringify(host._bgl.layers || []));
            if (!layers[at] || layers[at].kind !== 'picture') return;
            layers[at].url = String(url);
            delete ui.gone[url];
            commit(host, layers);
        });
    }

    return { draw: draw };
});
