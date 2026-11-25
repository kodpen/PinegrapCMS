/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Variables part: the Variables tab of Settings ›
 * Theme. The design's named colours (a light and a dark value each), type,
 * spacing and shape, a fold per group and a row per variable, the
 * designer's own colours and sizes under "Add a variable", and the contrast
 * of text on the surface. The server works every model out
 * (designer/variables_state, includes/fn/design_variables.php): it answers
 * with the model made safe, the defaults of the look and palette and the
 * stylesheet, which the canvas and the Preview try on at once. The design's
 * save writes the file.
 *
 * The same part draws the list a colour or size control of Options opens
 * (_sdVarCtl() in the core) or a side of the spacing box and the corners
 * (part 'spacing'): the variables of the control's kind, the designer's
 * own, the type scale's steps or the base unit's multiples, then the way
 * out to a typed value;
 * and Detach, which puts the value of that moment in the control's place.
 *
 * Not needed to open a page: the core runs this file the first time the tab
 * is shown, a control's list or Detach is asked for, or when the look or
 * palette of a design with a saved variables file changes
 * (_sdVarsFollowTheme()), and hands it a host: the pane, the look and
 * palette, the hidden input the save posts and the try-on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('variables', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;
    var _pgApiPost = K._pgApiPost;

    // The last answer of the server: model, defaults, css, contrast, list,
    // and the look and palette it was worked out for.
    var S = null;
    var H = null;
    var seq = 0;
    var timer = 0;
    // Folds the designer opened or closed, kept for the editor's life.
    var open = { color: true };

    function attr(v) {
        return esc(String(v == null ? '' : v)).replace(/"/g, '&quot;');
    }

    // Asks the server for the state of a model (null: the stored one, or
    // the defaults) on the look and palette of the moment.
    function ask(model) {
        var my = ++seq;
        var body = { look: H.look(), palette: H.palette() };
        if (model) body.model = model;
        else if (H.styleId) body.style_id = H.styleId;
        return _pgApiPost('variables_state', body).then(function (res) {
            if (my !== seq) return null;
            if (!res || res.status !== 'success') {
                sdToast(esc((res && res.message) ? res.message : _sdT('Network error.')), 'error', 5000);
                return null;
            }
            res.look = body.look;
            res.palette = body.palette;
            if (!res.list && S) res.list = S.list;
            S = res;
            return res;
        });
    }

    function current() {
        return H.model() || (S ? S.model : null);
    }

    function shown() {
        return !!(H && H.el && H.el.isConnected);
    }

    // ── Drawing ──────────────────────────────────────────────────────────

    function field(vr, v) {
        var k = attr(vr.key);
        var label = attr(vr.label);
        if (vr.kind === 'color') {
            return '<span class="sd-vars-pair">' +
                '<input type="color" class="sd-vars-color" data-var="' + k + '" data-mode="light" value="' + attr(v.light) + '" aria-label="' + label + ', ' + attr(_sdT('Light')) + '" title="' + attr(_sdT('Light')) + '">' +
                '<input type="color" class="sd-vars-color" data-var="' + k + '" data-mode="dark" value="' + attr(v.dark) + '" aria-label="' + label + ', ' + attr(_sdT('Dark')) + '" title="' + attr(_sdT('Dark')) + '">' +
                '</span>';
        }
        if (vr.kind === 'weight') {
            var w = '';
            for (var i = 1; i <= 9; i++) w += '<option' + (String(i * 100) === v ? ' selected' : '') + '>' + (i * 100) + '</option>';
            return '<select class="form-control form-control-sm sd-vars-input" data-var="' + k + '" aria-label="' + label + '">' + w + '</select>';
        }
        if (vr.kind === 'choice') {
            var o = '';
            (vr.options || []).forEach(function (op) {
                o += '<option value="' + attr(op.value) + '"' + (op.value === v ? ' selected' : '') + '>' + esc(op.label) + '</option>';
            });
            return '<select class="form-control form-control-sm sd-vars-input" data-var="' + k + '" aria-label="' + label + '">' + o + '</select>';
        }
        return '<input type="text" class="form-control form-control-sm sd-vars-input" data-var="' + k + '" value="' + attr(v) + '" aria-label="' + label + '" spellcheck="false">';
    }

    function sample(vr, v) {
        if (vr.kind === 'color') {
            return '<span class="sd-vars-swatch" style="background:' + attr(v.light) + '" aria-hidden="true"></span>';
        }
        if (vr.kind === 'font') {
            return '<span class="sd-vars-sample" style="font-family:' + attr(v) + '" aria-hidden="true">Aa</span>';
        }
        return '<span class="sd-vars-sample" aria-hidden="true"></span>';
    }

    // The token a variable writes (pg_design_variables_token()).
    function tokenOf(key, custom) {
        return (custom ? 'c-' : '') + String(key).replace(/_/g, '-');
    }

    // "Used in n places", words and never a button.
    function usedHtml(used, token) {
        var n = used[token] || 0;
        var t = n === 0 ? _sdT('Not used yet') : (n === 1 ? _sdT('Used in 1 place') : _sdT('Used in {var} places', n));
        return '<span class="sd-vars-used' + (n ? '' : ' is-none') + '">' + esc(t) + '</span>';
    }

    function customRow(c, i, used) {
        var value = c.kind === 'color'
            ? '<span class="sd-vars-pair">' +
                '<input type="color" class="sd-vars-color" data-custom="' + i + '" data-mode="light" value="' + attr(c.light) + '" aria-label="' + attr(c.name) + ', ' + attr(_sdT('Light')) + '" title="' + attr(_sdT('Light')) + '">' +
                '<input type="color" class="sd-vars-color" data-custom="' + i + '" data-mode="dark" value="' + attr(c.dark) + '" aria-label="' + attr(c.name) + ', ' + attr(_sdT('Dark')) + '" title="' + attr(_sdT('Dark')) + '">' +
              '</span>'
            : '<input type="text" class="form-control form-control-sm sd-vars-input" data-custom="' + i + '" value="' + attr(c.value) + '" aria-label="' + attr(c.name) + '" spellcheck="false">';
        return '<div class="sd-vars-row">' +
            (c.kind === 'color' ? '<span class="sd-vars-swatch" style="background:' + attr(c.light) + '" aria-hidden="true"></span>' : '<span class="sd-vars-sample" aria-hidden="true"></span>') +
            '<input type="text" class="form-control form-control-sm sd-vars-name" data-custom-name="' + i + '" value="' + attr(c.name) + '" maxlength="40" aria-label="' + attr(_sdT('Name')) + '">' +
            value + usedHtml(used || {}, tokenOf(c.key, true)) +
            '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-vars-act="remove" data-custom="' + i + '" title="' + attr(_sdT('Remove')) + '" aria-label="' + attr(_sdT('Remove')) + '"><span class="bi bi-x-lg"></span></button>' +
            '</div>';
    }

    function contrastHtml() {
        var rows = (S && S.contrast) || [];
        if (!rows.length) return '';
        var h = '<div class="sd-vars-contrast" data-vars-contrast>';
        rows.forEach(function (r) {
            var what = r.text === 'text' ? _sdT('Text on surface') : _sdT('Muted text on surface');
            h += '<div class="sd-vars-contrast-row' + (r.ok ? '' : ' is-low') + '">' +
                '<span class="bi ' + (r.ok ? 'bi-check-circle' : 'bi-exclamation-triangle') + ' me-1" aria-hidden="true"></span>' +
                esc(what) + ' (' + esc(r.mode === 'dark' ? _sdT('Dark') : _sdT('Light')) + '): ' + esc(String(r.ratio)) + ':1' +
                (r.ok ? '' : ' · ' + esc(_sdT('hard to read, aim for 4.5:1'))) +
                '</div>';
        });
        return h + '</div>';
    }

    function draw() {
        if (!shown() || !S) return;
        var m = current() || S.model;
        var used = H.usage ? H.usage() : {};
        var h = '<div class="sd-th-head"><span class="sd-th-title">' + esc(_sdT('Variables')) + '</span>' +
                '<span class="sd-th-sub">' + esc(_sdT('The named colours, type, spacing and shape of the design. Change one here and every page that uses it follows.')) + '</span></div>';
        (S.list.groups || []).forEach(function (g) {
            h += '<details class="sd-vars-group" data-group="' + attr(g.key) + '"' + (open[g.key] ? ' open' : '') + '>' +
                 '<summary class="sd-vars-summary">' + esc(g.label) + '</summary>';
            S.list.rows.forEach(function (vr) {
                if (vr.group !== g.key) return;
                var v = m.values[vr.key];
                h += '<div class="sd-vars-row">' + sample(vr, v) +
                     '<span class="sd-vars-label">' + esc(vr.label) + '</span>' + field(vr, v) + usedHtml(used, tokenOf(vr.key, false)) + '</div>';
            });
            h += '</details>';
        });
        h += '<details class="sd-vars-group" data-group="custom"' + (open.custom || (m.custom && m.custom.length) ? ' open' : '') + '>' +
             '<summary class="sd-vars-summary">' + esc(_sdT('Your own')) + '</summary>';
        (m.custom || []).forEach(function (c, i) { h += customRow(c, i, used); });
        h += '<div class="sd-vars-add">' +
             '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-vars-act="add-color"><span class="bi bi-plus-lg me-1"></span>' + esc(_sdT('Add a colour')) + '</button>' +
             '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-vars-act="add-size"><span class="bi bi-plus-lg me-1"></span>' + esc(_sdT('Add a size')) + '</button>' +
             '</div></details>';
        h += contrastHtml();
        h += '<div class="sd-vars-foot"><button type="button" class="btn btn-sm btn-outline theme-secondary" data-vars-act="reset">' +
             '<span class="bi bi-arrow-counterclockwise me-1"></span>' + esc(_sdT('Back to the look and palette')) + '</button></div>' +
             '<p class="sd-th-hint">' + esc(_sdT('The canvas shows a change at once; Save keeps it with the design and the site shows it after you publish.')) + '</p>';
        H.el.innerHTML = h;
        bind();
    }

    function redrawContrast() {
        if (!shown()) return;
        var old = H.el.querySelector('[data-vars-contrast]');
        if (!old) return;
        var tmp = document.createElement('div');
        tmp.innerHTML = contrastHtml();
        if (tmp.firstChild) old.parentNode.replaceChild(tmp.firstChild, old);
    }

    // ── Changing ─────────────────────────────────────────────────────────

    // A change: the model goes into the hidden input at once (Save posts
    // it), the server's stylesheet follows when the hand rests.
    function change(m, redraw) {
        H.setModel(m);
        clearTimeout(timer);
        timer = setTimeout(function () {
            ask(m).then(function (res) {
                if (!res) return;
                H.setModel(res.model);
                H.tryCss(res.css);
                if (redraw) draw(); else redrawContrast();
            });
        }, 300);
    }

    function copyModel() {
        return JSON.parse(JSON.stringify(current() || S.model));
    }

    function bind() {
        var el = H.el;
        el.querySelectorAll('details.sd-vars-group').forEach(function (d) {
            d.addEventListener('toggle', function () { open[d.getAttribute('data-group')] = d.open; });
        });
        el.querySelectorAll('.sd-vars-color').forEach(function (inp) {
            inp.addEventListener('input', function () {
                var m = copyModel(), mode = inp.getAttribute('data-mode');
                if (inp.hasAttribute('data-var')) {
                    m.values[inp.getAttribute('data-var')][mode] = inp.value;
                } else {
                    var c = m.custom[+inp.getAttribute('data-custom')];
                    if (!c) return;
                    c[mode] = inp.value;
                }
                if (mode === 'light') {
                    var sw = inp.closest('.sd-vars-row').querySelector('.sd-vars-swatch');
                    if (sw) sw.style.background = inp.value;
                }
                change(m, false);
            });
        });
        el.querySelectorAll('.sd-vars-input').forEach(function (inp) {
            // Text waits for the field to be left; a list answers at once.
            inp.addEventListener('change', function () {
                var m = copyModel();
                if (inp.hasAttribute('data-var')) {
                    m.values[inp.getAttribute('data-var')] = inp.value.trim();
                } else {
                    var c = m.custom[+inp.getAttribute('data-custom')];
                    if (!c) return;
                    c.value = inp.value.trim();
                }
                change(m, true);
            });
        });
        el.querySelectorAll('.sd-vars-name').forEach(function (inp) {
            inp.addEventListener('change', function () {
                var m = copyModel(), c = m.custom[+inp.getAttribute('data-custom-name')];
                if (!c) return;
                c.name = inp.value.trim();
                change(m, false);
            });
        });
        el.querySelectorAll('[data-vars-act]').forEach(function (b) {
            b.addEventListener('click', function () { act(b.getAttribute('data-vars-act'), b); });
        });
    }

    function act(what, b) {
        var m = copyModel();
        if (what === 'reset') {
            var keep = m.custom;
            m = JSON.parse(JSON.stringify(S.defaults));
            m.custom = keep;
        } else if (what === 'add-color' || what === 'add-size') {
            m.custom = m.custom || [];
            if (m.custom.length >= 24) {
                sdToast(esc(_sdT('A design keeps at most 24 of its own variables.')), 'warning', 4000);
                return;
            }
            var n = m.custom.length + 1, key;
            do { key = (what === 'add-color' ? 'colour-' : 'size-') + n++; } while (m.custom.some(function (c) { return c.key === key; }));
            m.custom.push(what === 'add-color'
                ? { key: key, name: _sdT('Colour {var}', n - 1), kind: 'color', light: m.values.brand.light, dark: m.values.brand.dark }
                : { key: key, name: _sdT('Size {var}', n - 1), kind: 'size', value: '1rem' });
            open.custom = true;
        } else if (what === 'remove') {
            m.custom.splice(+b.getAttribute('data-custom'), 1);
        } else {
            return;
        }
        H.setModel(m);
        S.model = m;
        draw();
        change(m, true);
    }

    // ── Entry points ─────────────────────────────────────────────────────

    function render(host) {
        H = host;
        if (S && S.look === H.look() && S.palette === H.palette()) {
            draw();
            return;
        }
        return ask(current()).then(function (res) { if (res) draw(); });
    }

    // The look or palette changed: the stylesheet is worked out again for
    // the new choice. The hidden input is written only when the tab changed
    // the model already: the save carries a stored file over by itself.
    function refresh(host) {
        H = host;
        if (S && S.look === H.look() && S.palette === H.palette()) return;
        var had = !!H.model();
        return ask(current()).then(function (res) {
            if (!res) return;
            if (had) H.setModel(res.model);
            if (had || H.stored) H.tryCss(res.css);
            draw();
        });
    }

    // ── The list of an Options control ───────────────────────────────────

    // The list open under a control: { el, host, off }.
    var L = null;
    var TIP = 'sd_vars_tip';

    // The state of the look and palette of the moment, asked once.
    function ready(host) {
        if (!H) H = host;
        if (S && S.look === host.look() && S.palette === host.palette()) return Promise.resolve(S);
        return ask(current());
    }

    // token => { name, color } for the core's chips.
    function names(m) {
        var out = {};
        S.list.rows.forEach(function (vr) {
            var v = m.values[vr.key];
            out[tokenOf(vr.key, false)] = { name: vr.label, color: vr.kind === 'color' && v ? v.light : '' };
        });
        (m.custom || []).forEach(function (c) {
            out[tokenOf(c.key, true)] = { name: c.name, color: c.kind === 'color' ? c.light : '' };
        });
        return out;
    }

    function pxText(px) {
        return px == null ? '' : Math.round(px) + ' px';
    }

    // The rows a control of `kind` offers, under their headings:
    // [{ head, items: [{ expr, name, value, color }] }]; a heading with no
    // rows is left out.
    function offer(host, m) {
        var vars = [], own = [], computed = [];
        var px = host.px;
        if (host.kind === 'color') {
            S.list.rows.forEach(function (vr) {
                if (vr.kind !== 'color') return;
                var v = m.values[vr.key];
                vars.push({ expr: host.expr({ token: tokenOf(vr.key, false) }), name: vr.label, value: v.light, color: v.light });
            });
        } else if (host.kind === 'space') {
            ['space_unit', 'space_section'].forEach(function (k) {
                vars.push({ expr: host.expr({ token: tokenOf(k, false) }), name: labelOf(k), value: pxText(px(m.values[k])) });
            });
            var unit = px(m.values.space_unit);
            if (unit != null) {
                (host.units || []).forEach(function (n) {
                    computed.push({ expr: host.expr({ units: n }), name: _sdT('{var} units', n), value: pxText(unit * n) });
                });
            }
        } else if (host.kind === 'radius') {
            vars.push({ expr: host.expr({ token: 'radius' }), name: labelOf('radius'), value: pxText(px(m.values.radius)) });
        } else {
            vars.push({ expr: host.expr({ token: 'size-base' }), name: labelOf('size_base'), value: pxText(px(m.values.size_base)) });
            var base = px(m.values.size_base), scale = parseFloat(m.values.scale);
            if (base != null && scale > 0) {
                host.steps.forEach(function (st) {
                    computed.push({ expr: host.expr({ step: st[1] }), name: st[0], value: pxText(base * Math.pow(scale, st[1])) });
                });
            }
        }
        (m.custom || []).forEach(function (c) {
            if ((host.kind === 'color') !== (c.kind === 'color')) return;
            own.push({ expr: host.expr({ token: tokenOf(c.key, true) }), name: c.name,
                value: c.kind === 'color' ? c.light : pxText(px(c.value)) || c.value, color: c.kind === 'color' ? c.light : '' });
        });
        return [
            { head: _sdT('Variables'), items: vars },
            { head: _sdT('Your own'), items: own },
            { head: host.kind === 'space' ? _sdT('Units') : _sdT('Type scale'), items: computed }
        ].filter(function (g) { return g.items.length; });
    }

    function labelOf(key) {
        var r = S.list.rows.filter(function (vr) { return vr.key === key; })[0];
        return r ? r.label : key;
    }

    function tipShown() {
        try { return !localStorage.getItem(TIP); } catch (e) { return false; }
    }
    function tipDone() {
        try { localStorage.setItem(TIP, '1'); } catch (e) { /* the line comes back next time */ }
    }

    function close() {
        if (!L) return;
        document.removeEventListener('pointerdown', L.off, true);
        document.removeEventListener('keydown', L.off, true);
        if (L.el.parentNode) L.el.parentNode.removeChild(L.el);
        L = null;
    }

    function listHtml(host, m) {
        var now = host.value ? String(host.value).trim() : '';
        var h = '';
        if (tipShown()) {
            h += '<div class="sd-var-tip">' + esc(_sdT('A variable is a value the whole design shares. Pick one and this control follows it when it changes.')) +
                 '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-vl-tip>' + esc(_sdT('Got it')) + '</button></div>';
        }
        h += '<input type="search" class="form-control form-control-sm sd-var-search" placeholder="' + attr(_sdT('Search variables')) + '" aria-label="' + attr(_sdT('Search variables')) + '">';
        h += '<div class="sd-var-rows" role="listbox" aria-label="' + attr(_sdT('Variables')) + '">';
        offer(host, m).forEach(function (g) {
            h += '<div class="sd-var-group"><div class="sd-var-head">' + esc(g.head) + '</div>';
            g.items.forEach(function (r) {
                var on = r.expr === now;
                h += '<button type="button" class="sd-var-row' + (on ? ' is-on' : '') + '" role="option" aria-selected="' + on + '" data-vl-expr="' + attr(r.expr) + '">' +
                     (r.color ? '<span class="sd-var-sw" style="background:' + attr(r.color) + '" aria-hidden="true"></span>'
                              : '<span class="bi ' + (host.icon || (host.kind === 'color' ? 'bi-palette' : 'bi-fonts')) + ' sd-var-ic" aria-hidden="true"></span>') +
                     '<span class="sd-var-name">' + esc(r.name) + '</span><span class="sd-var-val">' + esc(r.value) + '</span>' +
                     '<span class="bi bi-check2 sd-var-tick" aria-hidden="true"></span></button>';
            });
            h += '</div>';
        });
        h += '</div><div class="sd-var-sep"></div>' +
             '<button type="button" class="sd-var-row sd-var-typed" data-vl-typed>' +
             esc(host.kind === 'color' ? _sdT('Custom colour…') : _sdT('Custom value…')) + '</button>';
        return h;
    }

    // The search narrows every heading; one with nothing left goes.
    function filter(q) {
        q = q.trim().toLowerCase();
        L.el.querySelectorAll('.sd-var-group').forEach(function (g) {
            var any = false;
            g.querySelectorAll('.sd-var-row').forEach(function (r) {
                var hit = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
                r.hidden = !hit;
                if (hit) any = true;
            });
            g.hidden = !any;
        });
    }

    // A variable chosen: the design gets a variables stylesheet if it has
    // none yet (its model goes into the hidden input, so Save writes the
    // file), then the control takes the expression.
    function choose(host, expr) {
        tipDone();
        close();
        if (host.locked) { host.set(expr); return; }
        if (!host.hasCss()) {
            host.setModel(current() || S.model);
            host.tryCss(S.css);
        }
        host.set(expr);
    }

    function pick(host) {
        if (L && L.host.anchor === host.anchor) { close(); return; }
        close();
        var el = document.createElement('div');
        el.className = 'sd-var-list';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', _sdT('Variables'));
        el.innerHTML = '<p class="sd-var-busy">' + esc(_sdT('Loading…')) + '</p>';
        host.anchor.appendChild(el);
        var off = function (e) {
            if (e.type === 'keydown') {
                if (e.key === 'Escape') { e.stopPropagation(); close(); }
                return;
            }
            if (!el.contains(e.target) && !host.anchor.contains(e.target)) close();
        };
        L = { el: el, host: host, off: off };
        document.addEventListener('pointerdown', off, true);
        document.addEventListener('keydown', off, true);
        return ready(host).then(function (res) {
            if (!res || !L || L.el !== el) { if (!res) close(); return; }
            var m = current() || S.model;
            host.names(names(m));
            el.innerHTML = listHtml(host, m);
            var q = el.querySelector('.sd-var-search');
            q.addEventListener('input', function () { filter(q.value); });
            el.addEventListener('click', function (e) {
                var t = e.target.closest('[data-vl-expr], [data-vl-typed], [data-vl-tip]');
                if (!t) return;
                if (t.hasAttribute('data-vl-tip')) { tipDone(); t.parentNode.remove(); return; }
                if (t.hasAttribute('data-vl-typed')) { close(); host.typed(); return; }
                choose(host, t.getAttribute('data-vl-expr'));
            });
            q.focus();
        });
    }

    // The value the bound expression gives now, in the control's place.
    function valueNow(host, m) {
        var ref = host.parse(host.value);
        if (!ref) return '';
        var base = host.px(m.values.size_base);
        if (ref.step != null) return base == null ? '' : Math.round(base * Math.pow(parseFloat(m.values.scale), ref.step)) + 'px';
        if (ref.units != null) {
            var u = host.px(m.values.space_unit);
            return u == null ? '' : Math.round(u * ref.units) + 'px';
        }
        var key = ref.token.replace(/-/g, '_');
        var v = m.values[key];
        if (v === undefined) {
            var c = (m.custom || []).filter(function (x) { return tokenOf(x.key, true) === ref.token; })[0];
            if (!c) return '';
            v = c.kind === 'color' ? c.light : c.value;
        }
        return (v && typeof v === 'object') ? v.light : String(v);
    }

    function detach(host) {
        close();
        return ready(host).then(function (res) {
            if (!res) return;
            var v = valueNow(host, current() || S.model);
            if (v) host.set(v);
        });
    }

    return { render: render, refresh: refresh, pick: pick, detach: detach };
});
