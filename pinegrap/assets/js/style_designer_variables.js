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
 * Not needed to open a page: the core runs this file the first time the tab
 * is shown, or when the look or palette of a design with a saved variables
 * file changes (_sdVarsFollowTheme()), and hands it a host: the pane, the
 * look and palette, the hidden input the save posts and the try-on.
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

    function customRow(c, i) {
        var value = c.kind === 'color'
            ? '<span class="sd-vars-pair">' +
                '<input type="color" class="sd-vars-color" data-custom="' + i + '" data-mode="light" value="' + attr(c.light) + '" aria-label="' + attr(c.name) + ', ' + attr(_sdT('Light')) + '" title="' + attr(_sdT('Light')) + '">' +
                '<input type="color" class="sd-vars-color" data-custom="' + i + '" data-mode="dark" value="' + attr(c.dark) + '" aria-label="' + attr(c.name) + ', ' + attr(_sdT('Dark')) + '" title="' + attr(_sdT('Dark')) + '">' +
              '</span>'
            : '<input type="text" class="form-control form-control-sm sd-vars-input" data-custom="' + i + '" value="' + attr(c.value) + '" aria-label="' + attr(c.name) + '" spellcheck="false">';
        return '<div class="sd-vars-row">' +
            (c.kind === 'color' ? '<span class="sd-vars-swatch" style="background:' + attr(c.light) + '" aria-hidden="true"></span>' : '<span class="sd-vars-sample" aria-hidden="true"></span>') +
            '<input type="text" class="form-control form-control-sm sd-vars-name" data-custom-name="' + i + '" value="' + attr(c.name) + '" maxlength="40" aria-label="' + attr(_sdT('Name')) + '">' +
            value +
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
        var h = '<div class="sd-th-head"><span class="sd-th-title">' + esc(_sdT('Variables')) + '</span>' +
                '<span class="sd-th-sub">' + esc(_sdT('The named colours, type, spacing and shape of the design. Change one here and every page that uses it follows.')) + '</span></div>';
        (S.list.groups || []).forEach(function (g) {
            h += '<details class="sd-vars-group" data-group="' + attr(g.key) + '"' + (open[g.key] ? ' open' : '') + '>' +
                 '<summary class="sd-vars-summary">' + esc(g.label) + '</summary>';
            S.list.rows.forEach(function (vr) {
                if (vr.group !== g.key) return;
                var v = m.values[vr.key];
                h += '<div class="sd-vars-row">' + sample(vr, v) +
                     '<span class="sd-vars-label">' + esc(vr.label) + '</span>' + field(vr, v) + '</div>';
            });
            h += '</details>';
        });
        h += '<details class="sd-vars-group" data-group="custom"' + (open.custom || (m.custom && m.custom.length) ? ' open' : '') + '>' +
             '<summary class="sd-vars-summary">' + esc(_sdT('Your own')) + '</summary>';
        (m.custom || []).forEach(function (c, i) { h += customRow(c, i); });
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

    return { render: render, refresh: refresh };
});
