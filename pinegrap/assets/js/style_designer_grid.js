/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the grid part: a grid box's Columns and Rows under
 * Advanced › Layout. Each is Default (the browser's single track), Equal
 * (a count), Fixed sizes (typed with spaces) or, for columns, "As many as
 * fit" with a smallest width; a value typed another way in "This element"
 * reads as Custom and is kept. A value is written at the chosen screen
 * size (the size the Spacing box chooses, _sdSpSize) as an @media rule on
 * the element's custom style class through window.PGSizeStyle; Gap stays
 * Layout's own row. What a mode or a field writes is window.PGGridTracks's.
 *
 * Not needed to open a page: the core runs this file the first time
 * Layout is shown on a grid box (_sdSpBoxBind(), pg_designer_parts()) and
 * hands it the size host (_sdSzHost()): the elements, the chosen size, the
 * User Styles text, a new class and the commit as one undo step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('grid', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var Z = window.PGSizeStyle;
    var G = window.PGGridTracks;

    function _modes(axis) {
        var out = [['equal', _sdT('Equal')], ['fixed', _sdT('Fixed sizes')]];
        if (axis === 'columns') out.push(['fit', _sdT('As many as fit')]);
        return out;
    }

    function _modeName(axis, mode) {
        if (mode === 'typed') return _sdT('Custom');
        var m = _modes(axis).filter(function (p) { return p[0] === mode; })[0];
        return m ? m[1] : _sdT('Default');
    }

    // A value as the Default option names it when a smaller size holds it.
    function _describe(axis, t) {
        var d = t.mode === 'equal' ? String(t.count) : t.mode === 'fixed' ? t.sizes.join(' ') : t.mode === 'fit' ? t.min : t.value;
        return _modeName(axis, t.mode) + (d ? ': ' + d : '');
    }

    function _cls(n) {
        return window.PgCustomStyle.classOf(String((n.props || {}).cssClass || '').split(/\s+/).filter(Boolean));
    }

    // Each element's value in force at the size, and whether it is its own.
    function _reads(host, text, axis) {
        return host.nodes.filter(function (n) { return n && n.props; }).map(function (n) {
            var cls = _cls(n);
            return Z.at(cls ? Z.read(text, cls, G.CSS[axis]) : {}, host.size);
        });
    }

    // Writes `value` ('' clears the size) on every element as one change;
    // an element without a custom style class gets one.
    function _write(host, axis, value) {
        var css = G.CSS[axis];
        var text = host.text();
        var changes = host.nodes.map(function (n) {
            if (!n || !n.props) return null;
            var list = String(n.props.cssClass || '').split(/\s+/).filter(Boolean);
            var cls = window.PgCustomStyle.classOf(list);
            var ch = null;
            if (!cls) {
                if (!value) return null;
                cls = host.newClass(text);
                if (!cls) return null;
                list.push(cls);
                ch = { cssClass: list.join(' ') };
            }
            text = Z.write(text, cls, css, host.size, value);
            return ch;
        });
        host.commit(text, changes);
    }

    function _sizeRowHtml(host, text) {
        var has = {};
        host.nodes.forEach(function (n) {
            if (!n || !n.props) return;
            var cls = _cls(n);
            if (cls) Z.held(text, cls, [G.CSS.columns, G.CSS.rows]).forEach(function (s) { has[s] = true; });
        });
        return '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(_sdT('Screen size')) + '</span>' +
            '<select class="form-control form-control-sm" data-sd-grid-size aria-label="' + esc(_sdT('Screen size')) + '">' +
            Z.SIZES.map(function (s) {
                var name = s === 'xs' ? _sdT('Every screen size') : _sdT('From {var} up', s.toUpperCase());
                return '<option value="' + s + '"' + (s === host.size ? ' selected' : '') + '>' + esc(name + (has[s] ? ' •' : '')) + '</option>';
            }).join('') + '</select></div>';
    }

    // One axis: its mode and, when the value is the element's own at this
    // size, the field of that mode.
    function _axisHtml(host, text, axis) {
        var reads = _reads(host, text, axis);
        if (!reads.length) return '';
        var first = reads[0];
        var mixed = reads.some(function (r) { return r.value !== first.value || r.here !== first.here; });
        var t = G.parse(first.value, axis);
        var own = !mixed && first.here ? t.mode : '';
        var label = axis === 'columns' ? _sdT('Columns') : _sdT('Rows');
        var dis = host.locked ? ' disabled' : '';
        var def = !mixed && !first.here && first.value ? _describe(axis, t) + ' (' + _sdT('from {var}', first.size.toUpperCase()) + ')' : _sdT('Default');
        var opts = (mixed ? '<option value="=" selected>' + esc(_sdT('— Multiple —')) + '</option>' : '') +
            '<option value=""' + (!mixed && !own ? ' selected' : '') + '>' + esc(def) + '</option>' +
            _modes(axis).map(function (m) {
                return '<option value="' + m[0] + '"' + (own === m[0] ? ' selected' : '') + '>' + esc(m[1]) + '</option>';
            }).join('') +
            (own === 'typed' ? '<option value="typed" selected>' + esc(_sdT('Custom')) + '</option>' : '');
        var h = '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(label) + '</span>' +
            '<select class="form-control form-control-sm" data-sd-grid-mode="' + axis + '" aria-label="' + esc(label) + '"' + dis + '>' + opts + '</select></div>';
        var field = null;
        if (own === 'equal') field = ['count', _sdT('Count'), String(t.count), ' type="number" min="1" max="' + G.MAX + '" step="1"'];
        else if (own === 'fixed') field = ['sizes', _sdT('Sizes'), t.sizes.join(' '), ' type="text" placeholder="200px 1fr"'];
        else if (own === 'fit') field = ['min', _sdT('Smallest width'), t.min, ' type="text" placeholder="200px"'];
        else if (own === 'typed') field = ['typed', _sdT('Custom'), t.value, ' type="text"'];
        if (field) {
            h += '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(field[1]) + '</span>' +
                '<input class="form-control form-control-sm" data-sd-grid-field="' + axis + '" data-k="' + field[0] + '"' + field[3] +
                ' value="' + esc(field[2]) + '" aria-label="' + esc(label + ': ' + field[1]) + '"' + dis + '></div>';
        }
        return h;
    }

    // Draws the rows into host.el. Where a custom style is off (a system
    // widget, a shared component) there is no class to write to, and the
    // rows are not drawn.
    function draw(host) {
        var el = host.el;
        if (!el || !el.isConnected || host.off || !Z || !G || !window.PgCustomStyle) return;
        var text = host.text();
        var html = _axisHtml(host, text, 'columns');
        if (!html) return;
        el.innerHTML = _sizeRowHtml(host, text) + html + _axisHtml(host, text, 'rows');
        el.hidden = false;

        var size = el.querySelector('[data-sd-grid-size]');
        size.addEventListener('change', function () { host.setSize(size.value); });

        el.querySelectorAll('[data-sd-grid-mode]').forEach(function (menu) {
            menu.addEventListener('change', function () {
                var axis = menu.getAttribute('data-sd-grid-mode');
                if (menu.value === '=') return;
                var cur = _reads(host, host.text(), axis)[0] || { value: '' };
                var v = G.pick(G.parse(cur.value, axis), menu.value, axis);
                if (v !== null) _write(host, axis, v);
            });
        });

        el.querySelectorAll('[data-sd-grid-field]').forEach(function (f) {
            var was = f.value;
            f.addEventListener('change', function () {
                var axis = f.getAttribute('data-sd-grid-field');
                var cur = _reads(host, host.text(), axis)[0];
                var v = G.edit(G.parse(cur ? cur.value : '', axis), f.getAttribute('data-k'), f.value, axis);
                f.classList.toggle('is-invalid', v === null);
                if (v === null) return;
                if (f.value.trim() === was.trim()) return;
                _write(host, axis, v);
            });
        });
    }

    return { draw: draw };
});
