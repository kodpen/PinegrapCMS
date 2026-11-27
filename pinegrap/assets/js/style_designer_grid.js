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
 * On a grid's child the part draws Span columns, Span rows and Start at
 * (column and row) the same way, as `grid-column` and `grid-row`; an empty
 * field leaves the browser's own placement.
 * A grid box with a set number of columns and rows gets "Areas": a small
 * grid where cells are dragged into named rectangles, written as
 * `grid-template-areas` at the size (window.PGGridAreas); its children get
 * "Area", written as `grid-area`. While one of the two is open the areas
 * are tinted over the box on the canvas (never saved).
 *
 * Not needed to open a page: the core runs this file the first time
 * Layout is shown on a grid box or its child (_sdSpBoxBind(), pg_designer_parts()) and
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

    // Each element's value of `css` in force at the size, and whether it
    // is its own.
    function _readsCss(host, text, css) {
        return host.nodes.filter(function (n) { return n && n.props; }).map(function (n) {
            var cls = _cls(n);
            return Z.at(cls ? Z.read(text, cls, css) : {}, host.size);
        });
    }

    function _reads(host, text, axis) {
        return _readsCss(host, text, G.CSS[axis]);
    }

    // Writes `value` ('' clears the size) of `css` on every element as one
    // change; an element without a custom style class gets one.
    function _write(host, axis, value) {
        _writeCss(host, G.CSS[axis], value);
    }

    function _writeCss(host, css, value) {
        _writeMany(host, [[css, value]]);
    }

    // Several properties as one change: `pairs` is [[css, value], …].
    function _writeMany(host, pairs) {
        var value = pairs.some(function (p) { return !!p[1]; });
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
            pairs.forEach(function (p) { text = Z.write(text, cls, p[0], host.size, p[1]); });
            return ch;
        });
        host.commit(text, changes);
    }

    // `props`: the CSS properties whose sizes are marked with a dot.
    function _sizeRowHtml(host, text, props) {
        var has = {};
        host.nodes.forEach(function (n) {
            if (!n || !n.props) return;
            var cls = _cls(n);
            if (cls) Z.held(text, cls, props).forEach(function (s) { has[s] = true; });
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
        el.innerHTML = _sizeRowHtml(host, text, [G.CSS.columns, G.CSS.rows]) + html + _axisHtml(host, text, 'rows') +
            '<div data-sd-ga-box></div>';
        el.hidden = false;
        _drawAreas(host, el.querySelector('[data-sd-ga-box]'));

        var size = el.querySelector('[data-sd-grid-size]');
        size.addEventListener('change', function () { host.setSize(size.value); });

        el.querySelectorAll('[data-sd-grid-mode]').forEach(function (menu) {
            menu.addEventListener('change', function () {
                var axis = menu.getAttribute('data-sd-grid-mode');
                if (menu.value === '=') return;
                var cur = _reads(host, host.text(), axis)[0] || { value: '' };
                var v = G.pick(G.parse(cur.value, axis), menu.value, axis);
                if (v !== null) _writeTracks(host, axis, v);
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
                _writeTracks(host, axis, v);
            });
        });
    }

    // ── A grid child's place: Span columns, Span rows, Start at.

    // One axis's value as the elements hold it at the size: { place: the
    // parsed value, own: set at this size, mixed, from: the size it comes
    // from when not own }.
    function _placeOf(host, text, axis) {
        var reads = _readsCss(host, text, G.PLACE[axis]);
        if (!reads.length) return null;
        var first = reads[0];
        var mixed = reads.some(function (r) { return r.value !== first.value || r.here !== first.here; });
        return { place: G.parsePlace(mixed ? '' : first.value), own: !mixed && first.here, mixed: mixed,
            from: !mixed && !first.here && first.value ? first.size : '' };
    }

    // A number field of the place: its value when set at this size, the
    // value in force from a smaller size as its placeholder.
    function _numField(host, axis, k, p, label) {
        var v = p.mixed ? '' : p.place[k];
        var ph = p.mixed ? _sdT('— Multiple —') : (!p.own && v ? v + ' (' + _sdT('from {var}', p.from.toUpperCase()) + ')' : _sdT('Auto'));
        return '<input class="form-control form-control-sm" type="number" min="1" max="' + G.MAX + '" step="1" data-sd-grid-place="' + axis +
            '" data-k="' + k + '" value="' + esc(p.own ? v : '') + '" placeholder="' + esc(ph) + '" aria-label="' + esc(label) + '"' +
            (host.locked ? ' disabled' : '') + '>';
    }

    // A value typed another way ("main", "-1", "a / b"): one text field
    // keeps it as it is.
    function _typedRow(host, axis, p, label) {
        return '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(label) + '</span>' +
            '<input class="form-control form-control-sm" type="text" data-sd-grid-place="' + axis + '" data-k="typed" value="' +
            esc(p.own ? p.place.value : '') + '" placeholder="' + esc(p.own ? '' : p.place.value) + '" aria-label="' + esc(label) + '"' +
            (host.locked ? ' disabled' : '') + '></div>';
    }

    // The row "Area" of a grid's child, drawn when the grid box has areas
    // at any size: "None" and the areas at the chosen size.
    function _areaRow(host, text) {
        if (!A || !host.parentOf) return null;
        var pn = host.parentOf(host.nodes[0]);
        if (!pn || !pn.props || host.nodes.some(function (n) { return host.parentOf(n) !== pn; })) return null;
        var st = _gridState(host, pn, text);
        if (!st.any || !st.ok) return null;
        var reads = _readsCss(host, text, A.ITEM);
        if (!reads.length) return null;
        var first = reads[0];
        var mixed = reads.some(function (r) { return r.value !== first.value || r.here !== first.here; });
        var name = mixed ? '' : A.item(first.value);
        var own = !mixed && first.here;
        var held = _held(host, pn, text);
        var label = _sdT('Area');
        var names = st.areas.map(function (a) { return a.name; });
        var opts = (mixed ? '<option value="=" selected>' + esc(_sdT('— Multiple —')) + '</option>' : '') +
            '<option value=""' + (!mixed && !own ? ' selected' : '') + '>' +
            esc(!own && name ? name + ' (' + _sdT('from {var}', first.size.toUpperCase()) + ')' : _sdT('None')) + '</option>' +
            (!own && name ? '<option value="auto">' + esc(_sdT('None')) + '</option>' : '') +
            (own && !name ? '<option value="auto" selected>' + esc(_sdT('None')) + '</option>' : '') +
            st.areas.map(function (a) {
                return '<option value="' + esc(a.name) + '"' + (own && name === a.name ? ' selected' : '') + '>' +
                    esc(a.name + ' (' + (held[a.name] ? _elements(held[a.name]) : _sdT('empty')) + ')') + '</option>';
            }).join('') +
            (own && name && names.indexOf(name) === -1 ? '<option value="' + esc(name) + '" selected>' + esc(name) + '</option>' : '');
        return { name: name, parent: pn, areas: st.areas,
            html: '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(label) + '</span><select class="form-control form-control-sm" data-sd-ga-pick aria-label="' +
                esc(label) + '"' + (host.locked ? ' disabled' : '') + '>' + opts + '</select></div>' };
    }

    // Draws the place rows into host.el, on a grid's child.
    function drawItem(host) {
        var el = host.el;
        if (!el || !el.isConnected || host.off || !Z || !G || !window.PgCustomStyle) return;
        var text = host.text();
        var c = _placeOf(host, text, 'columns');
        var r = _placeOf(host, text, 'rows');
        if (!c || !r) return;
        // An element that is a grid box too has the Screen size row there.
        var sect = el.closest('.sd-sp-section');
        var html = sect && sect.querySelector('[data-sd-gridbox]') ? '' : _sizeRowHtml(host, text, [G.PLACE.columns, G.PLACE.rows]);
        var area = _areaRow(host, text);
        if (area) html += area.html;
        // A child in an area keeps its place rows, dimmed.
        var by = area && area.name;
        var ph = host;
        if (by) { ph = Object.create(host); ph.locked = true; html += '<div class="sd-ga-dim">'; }
        var spanC = _sdT('Span columns'), spanR = _sdT('Span rows');
        html += c.place.typed ? _typedRow(ph, 'columns', c, _sdT('Column') + ' (' + _sdT('Custom') + ')')
            : '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(spanC) + '</span>' + _numField(ph, 'columns', 'span', c, spanC) + '</div>';
        html += r.place.typed ? _typedRow(ph, 'rows', r, _sdT('Row') + ' (' + _sdT('Custom') + ')')
            : '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(spanR) + '</span>' + _numField(ph, 'rows', 'span', r, spanR) + '</div>';
        if (!c.place.typed || !r.place.typed) {
            var start = _sdT('Start at');
            html += '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(start) + '</span><span class="d-flex gap-1">' +
                (c.place.typed ? '' : _numField(ph, 'columns', 'start', c, start + ': ' + _sdT('Column'))) +
                (r.place.typed ? '' : _numField(ph, 'rows', 'start', r, start + ': ' + _sdT('Row'))) + '</span></div>';
        }
        if (by) html += '</div><div class="sd-ga-line">' + esc(_sdT('Placed by its area. Choose “None” to place it by column and row.')) + '</div>';
        el.innerHTML = html;
        el.hidden = false;

        var size = el.querySelector('[data-sd-grid-size]');
        if (size) size.addEventListener('change', function () { host.setSize(size.value); });

        var pick = el.querySelector('[data-sd-ga-pick]');
        if (pick) pick.addEventListener('change', function () {
            var v = pick.value;
            if (v === '=') return;
            // An area places the element: its own column and row go at this size.
            _writeMany(host, A.item(v) ? [[A.ITEM, v], [G.PLACE.columns, ''], [G.PLACE.rows, '']] : [[A.ITEM, v]]);
        });
        if (area) { _ov.hot = area.name; _overlay(host, area.parent, area.areas, el); }

        el.querySelectorAll('[data-sd-grid-place]').forEach(function (f) {
            var was = f.value;
            f.addEventListener('change', function () {
                var axis = f.getAttribute('data-sd-grid-place');
                // A field changed at a size starts from the value in force
                // there, so a span set at XS is kept when MD sets a start.
                var cur = _readsCss(host, host.text(), G.PLACE[axis])[0];
                var v = G.editPlace(G.parsePlace(cur ? cur.value : ''), f.getAttribute('data-k'), f.value);
                f.classList.toggle('is-invalid', v === null);
                if (v === null || f.value.trim() === was.trim()) return;
                _writeCss(host, G.PLACE[axis], v);
            });
        });
    }

    // ── A grid box's named areas: the row "Areas" and its small grid.

    var A = window.PGGridAreas;
    // Per grid box (node id): folded by hand, the chosen area, the name
    // being typed, the line left by the last change, a refusal.
    var _fold = {};
    var _ui = { id: '', sel: '', edit: '', fresh: false, note: '', err: '', key: null };
    var _ov = { timer: 0, box: null, hot: '', host: null, node: null };

    function _tint(i) { return 'sd-ga-t' + (i % 6 + 1); }

    function _elements(n) { return n === 1 ? _sdT('1 element') : _sdT('{var} elements', n); }

    // The grid box's areas at the chosen size and what they stand on.
    function _gridState(host, node, text) {
        var cls = _cls(node);
        var vals = cls ? Z.read(text, cls, A.CSS) : {};
        var cur = Z.at(vals, host.size);
        var trk = function (axis) {
            return A.count(G.parse(cls ? Z.at(Z.read(text, cls, G.CSS[axis]), host.size).value : '', axis));
        };
        var p = A.parse(cur.value);
        var st = { node: node, cls: cls, vals: vals, own: cur.here, from: cur.here ? '' : cur.size, value: cur.value,
            cols: trk('columns'), 'rows': trk('rows'), ok: p.ok, drawnCols: p.cols, drawnRows: p.rows, areas: p.areas };
        // Areas drawn for another count are shown where they fall.
        if (st.cols && st.rows) st.areas = A.fit(p.areas, st.cols, st.rows).areas;
        st.smaller = Z.SIZES.slice(0, Z.SIZES.indexOf(host.size)).some(function (s) { return !!vals[s]; });
        st.any = Z.SIZES.some(function (s) { return !!vals[s]; });
        return st;
    }

    // How many children stand in each area at the size.
    function _held(host, node, text) {
        var out = {};
        (node.children || []).forEach(function (ch) {
            var c = ch && ch.props ? _cls(ch) : '';
            if (!c) return;
            var name = A.item(Z.at(Z.read(text, c, A.ITEM), host.size).value);
            if (name) out[name] = (out[name] || 0) + 1;
        });
        return out;
    }

    function _placeText(a, cols) {
        var c = a.w === 1 ? _sdT('column {var}', a.c) : _sdT('columns {var:1} to {var:2}', [a.c, a.c + a.w - 1]);
        var r = a.h === 1 ? _sdT('row {var}', a.r) : _sdT('rows {var:1} to {var:2}', [a.r, a.r + a.h - 1]);
        return cols === 1 ? r : c + ' · ' + r;
    }

    // The children's "Area" from the size up, while the grid's areas in
    // force there are this size's: `fn(name)` gives the new name, '' to
    // take it away, or null to leave it. Returns { text, count }.
    function _childWalk(host, st, text, fn) {
        var sizes = Z.SIZES.slice(Z.SIZES.indexOf(host.size));
        var count = 0;
        (st.node.children || []).forEach(function (ch) {
            var c = ch && ch.props ? _cls(ch) : '';
            if (!c) return;
            var hit = false;
            for (var i = 0; i < sizes.length; i++) {
                if (i > 0 && st.vals[sizes[i]]) break;
                var cur = Z.at(Z.read(text, c, A.ITEM), sizes[i]);
                var name = A.item(cur.value);
                var next = name ? fn(name) : null;
                if (next === null) continue;
                if (!cur.here && i > 0) continue;
                hit = true;
                text = Z.write(text, c, A.ITEM, sizes[i], cur.here ? next : (next || 'auto'));
                if (!next && A.item(Z.at(Z.read(text, c, A.ITEM), sizes[i]).value) === name) {
                    text = Z.write(text, c, A.ITEM, sizes[i], 'auto');
                }
            }
            if (hit) count++;
        });
        return { text: text, count: count };
    }

    // No area over a smaller size's areas is `none`, so they do not come back.
    function _areasValue(st, areas, cols, rows) {
        return A.build(areas, cols, rows) || (st.smaller ? 'none' : '');
    }

    // Writes the areas at the size as one undo step: `walk(name)` gives a
    // child's new area name ('' none, null kept), `note(count)` the line
    // left under the grid, set before the panel is drawn again.
    function _commitAreas(host, st, areas, walk, note) {
        var text = Z.write(host.text(), st.cls, A.CSS, host.size, _areasValue(st, areas, st.cols, st.rows));
        var moved = 0;
        if (walk) {
            var w = _childWalk(host, st, text, walk);
            text = w.text;
            moved = w.count;
        }
        _setNote(st.node.id, note ? note(moved) : '');
        host.commit(text, [null]);
    }

    function _setNote(id, text) {
        _ui.id = id;
        _ui.note = text;
        _ui.err = '';
    }

    function _remove(host, st, i) {
        var name = st.areas[i].name;
        _ui.sel = '';
        _ui.edit = '';
        _commitAreas(host, st, st.areas.filter(function (a, k) { return k !== i; }),
            function (n) { return n === name ? '' : null; },
            function (moved) {
                return _sdT('“{var}” is removed.', name) + (moved ? ' ' + (moved === 1 ? _sdT('1 element is placed by the grid again.')
                    : _sdT('{var} elements are placed by the grid again.', moved)) : '');
            });
        _announce(host, _ui.note);
    }

    function _rename(host, st, i, name) {
        var old = st.areas[i].name;
        _ui.edit = '';
        _ui.sel = name;
        _commitAreas(host, st, st.areas.map(function (a, k) { return k === i ? { name: name, c: a.c, r: a.r, w: a.w, h: a.h } : a; }),
            function (n) { return n === old ? name : null; },
            function (moved) {
                return _sdT('“{var:1}” is now “{var:2}”.', [old, name]) + (moved ? ' ' + (moved === 1 ? _sdT('1 element follows it.')
                    : _sdT('{var} elements follow it.', moved)) : '');
            });
        _announce(host, _ui.note);
    }

    // Columns or Rows changed on a grid box with areas of its own at the
    // size: the areas are cut to the new count in the same step.
    function _writeTracks(host, axis, value) {
        var node = host.nodes.length === 1 ? host.nodes[0] : null;
        var st = node && A ? _gridState(host, node, host.text()) : null;
        var n = A ? A.count(G.parse(value, axis)) : 0;
        if (!st || !st.own || !st.areas.length || !n || n === (axis === 'columns' ? st.cols : st.rows)) {
            _write(host, axis, value);
            return;
        }
        var was = axis === 'columns' ? st.cols : st.rows;
        var cols = axis === 'columns' ? n : st.cols, rows = axis === 'rows' ? n : st.rows;
        var f = A.fit(st.areas, cols, rows);
        var text = Z.write(host.text(), st.cls, G.CSS[axis], host.size, value);
        text = Z.write(text, st.cls, A.CSS, host.size, _areasValue(st, f.areas, cols, rows));
        text = _childWalk(host, st, text, function (name) { return f.gone.indexOf(name) !== -1 ? '' : null; }).text;
        var q = function (l) { return l.map(function (s) { return '“' + s + '”'; }).join(', '); };
        var line = (axis === 'columns' ? _sdT('Columns went from {var:1} to {var:2}.', [was, n]) : _sdT('Rows went from {var:1} to {var:2}.', [was, n]));
        if (f.cut.length) line += ' ' + _sdT('Made smaller: {var}.', q(f.cut));
        if (f.gone.length) line += ' ' + _sdT('Removed: {var}.', q(f.gone));
        _setNote(node.id, line);
        host.commit(text, [null]);
    }

    function _announce(host, text) {
        var live = host.el.querySelector('[data-sd-ga-live]');
        if (live) live.textContent = text;
    }

    function _foldHtml(st, open, off) {
        var sq = st.areas.map(function (a, i) { return '<i class="' + _tint(i) + '"></i>'; }).join('');
        var label = !st.areas.length ? _sdT('None') : (st.areas.length === 1 ? _sdT('1 area') : _sdT('{var} areas', st.areas.length));
        if (st.areas.length && !st.own) label += ' (' + _sdT('from {var}', st.from.toUpperCase()) + ')';
        return '<div class="sd-sp-row"><span class="sd-sp-label">' + esc(_sdT('Areas')) + '</span>' +
            '<button type="button" class="sd-ga-fold" data-sd-ga-fold aria-expanded="' + (open ? 'true' : 'false') + '"' + (off ? ' disabled' : '') + '>' +
            (sq ? '<span class="sd-ga-sqs">' + sq + '</span>' : '') + '<span>' + esc(label) + '</span></button></div>';
    }

    function _gridHtml(host, st, held) {
        var cols = st.cols, rows = st.rows;
        var nums = function (n) { var h = ''; for (var i = 1; i <= n; i++) h += '<span>' + i + '</span>'; return h; };
        var h = '<div class="sd-ga-wrap"><div class="sd-ga-num is-c" style="grid-template-columns:repeat(' + cols + ',1fr)">' + nums(cols) + '</div>' +
            '<div class="sd-ga-num is-r" style="grid-template-rows:repeat(' + rows + ',' + (cols >= 7 ? 24 : 28) + 'px)">' + nums(rows) + '</div>' +
            '<div class="sd-ga-grid" data-sd-ga-grid tabindex="0" role="application" aria-label="' +
            esc(_sdT('Areas, {var:1} columns, {var:2} rows', [cols, rows])) + '" style="grid-template-columns:repeat(' + cols +
            ',minmax(0,1fr));grid-template-rows:repeat(' + rows + ',' + (cols >= 7 ? 24 : 28) + 'px)">';
        for (var r = 1; r <= rows; r++) {
            for (var c = 1; c <= cols; c++) h += '<i data-c="' + c + '" data-r="' + r + '" style="grid-area:' + r + '/' + c + '"></i>';
        }
        st.areas.forEach(function (a, i) {
            var on = st.own && _ui.sel === a.name;
            h += '<div class="sd-ga-area ' + _tint(i) + (on ? ' is-on' : '') + (st.own ? '' : ' is-inh') + '" data-i="' + i + '" style="grid-area:' +
                a.r + '/' + a.c + '/span ' + a.h + '/span ' + a.w + '" title="' + esc(a.name) + '"><b>' + esc(a.name) + '</b>' +
                (on && !host.locked ? '<span class="sd-ga-handle" data-sd-ga-handle></span>' : '') + '</div>';
        });
        h += '<div class="sd-ga-mark" data-sd-ga-mark hidden></div></div></div>';
        if (!st.areas.length) h += '<div class="sd-ga-line">' + esc(_sdT('Drag across the cells to make an area.')) + '</div>';
        h += '<div class="sd-ga-list">' + st.areas.map(function (a, i) {
            var on = st.own && _ui.sel === a.name;
            var name = _ui.edit === a.name && st.own
                ? '<input type="text" data-sd-ga-input value="' + esc(a.name) + '" aria-label="' + esc(_sdT('Area name')) + '">'
                : '<button type="button" class="sd-ga-name" data-sd-ga-name="' + i + '"' + (host.locked ? ' disabled' : '') + '>' + esc(a.name) + '</button>';
            var n = held[a.name] || 0;
            return '<div class="sd-ga-li' + (on ? ' is-on' : '') + '" data-i="' + i + '"><i class="sd-ga-sq ' + _tint(i) + '"></i>' + name +
                (_ui.edit === a.name ? '' : '<small>' + esc(_placeText(a, st.cols) + ' · ' + (n ? _elements(n) : _sdT('empty'))) + '</small>') +
                (on && !host.locked ? '<button type="button" class="sd-ga-bin" data-sd-ga-bin="' + i + '" title="' + esc(_sdT('Remove area')) +
                    '" aria-label="' + esc(_sdT('Remove area')) + '"><i class="bi bi-trash3"></i></button>' : '') + '</div>';
        }).join('') + '</div>';
        if (_ui.id === st.node.id && _ui.err) h += '<div class="sd-ga-line is-bad" role="alert">' + esc(_ui.err) + '</div>';
        else if (_ui.id === st.node.id && _ui.note) h += '<div class="sd-ga-line">' + esc(_ui.note) + '</div>';
        if (st.areas.length && (st.drawnCols !== st.cols || st.drawnRows !== st.rows) && st.ok) {
            var sz = host.size.toUpperCase();
            h += '<div class="sd-ga-line is-warn">' + esc(_sdT('These areas were drawn for {var:1} columns and {var:2} rows at {var:3}. {var:4} has {var:5} by {var:6}: draw them here for {var:4} and up.',
                [st.drawnCols, st.drawnRows, (st.from || host.size).toUpperCase(), sz, st.cols, st.rows])) + '</div>';
        }
        if (st.areas.length) {
            var foot = st.own ? _sdT('Kept for {var} and up.', host.size.toUpperCase())
                : _sdT('Taken from {var:1}. A change here is kept for {var:2} and up.', [st.from.toUpperCase(), host.size.toUpperCase()]);
            h += '<div class="sd-ga-foot"><span>' + esc(foot) + '</span>' + (st.own && st.smaller && !host.locked
                ? '<button type="button" data-sd-ga-reset>' + esc(_sdT('Reset this size')) + '</button>' : '') + '</div>';
        }
        return h + '<div class="visually-hidden" role="status" aria-live="polite" data-sd-ga-live></div>';
    }

    // Draws the row "Areas" (and its grid when open) into `box`.
    function _drawAreas(host, box) {
        if (!A || host.nodes.length !== 1 || !box) return;
        var node = host.nodes[0];
        var text = host.text();
        var st = _gridState(host, node, text);
        if (!st.ok) { box.innerHTML = ''; return; }
        if (_ui.id !== node.id) { _ui.id = node.id; _ui.sel = ''; _ui.edit = ''; _ui.note = ''; _ui.err = ''; _ui.key = null; }
        var off = !st.cols || !st.rows;
        var open = !off && (Object.prototype.hasOwnProperty.call(_fold, node.id) ? !_fold[node.id] : st.areas.length > 0);
        var h = _foldHtml(st, open, off);
        if (off) h += '<div class="sd-ga-line">' + esc(_sdT('Areas need a set number of columns and rows. Choose “Equal” or “Fixed sizes” for both.')) + '</div>';
        else if (open) h += '<div class="sd-ga">' + _gridHtml(host, st, _held(host, node, text)) + '</div>';
        box.innerHTML = h;
        var redraw = function () { _drawAreas(host, box); };
        box.querySelector('[data-sd-ga-fold]').addEventListener('click', function () {
            _fold[node.id] = open;
            redraw();
        });
        if (open && !off) _bindGrid(host, box, st, redraw);
        _overlay(open && !off ? host : null, node, st.areas, box);
    }

    function _bindGrid(host, box, st, redraw) {
        var grid = box.querySelector('[data-sd-ga-grid]');
        var mark = box.querySelector('[data-sd-ga-mark]');
        var setErr = function (t) { _ui.err = t; _ui.note = ''; _announce(host, t); };
        var choose = function (name) { _ui.sel = name; _ui.err = ''; redraw(); };
        var cell = function (ev) {
            var r = grid.getBoundingClientRect();
            return { c: Math.max(1, Math.min(st.cols, Math.floor((ev.clientX - r.left) / (r.width / st.cols)) + 1)),
                r: Math.max(1, Math.min(st.rows, Math.floor((ev.clientY - r.top) / (r.height / st.rows)) + 1)) };
        };
        var show = function (rc, bad, label) {
            mark.hidden = !rc;
            if (!rc) return;
            mark.style.gridArea = rc.r + '/' + rc.c + '/span ' + rc.h + '/span ' + rc.w;
            mark.classList.toggle('is-bad', !!bad);
            mark.textContent = label || '';
        };
        // Makes, moves or resizes as one write; a taken cell refuses.
        var put = function (rc, i) {
            if (!A.free(st.areas, rc, st.cols, st.rows, i)) {
                setErr(_sdT('Areas cannot overlap. An area is one rectangle of free cells.'));
                redraw();
                return;
            }
            var areas = st.areas.slice();
            if (i === -1) {
                var name = A.nextName(areas);
                areas.push({ name: name, c: rc.c, r: rc.r, w: rc.w, h: rc.h });
                _ui.sel = name;
                _ui.edit = name;
                _ui.fresh = true;
            } else {
                areas[i] = { name: areas[i].name, c: rc.c, r: rc.r, w: rc.w, h: rc.h };
            }
            _commitAreas(host, st, areas);
        };

        if (!host.locked) {
            var drag = null;
            grid.addEventListener('pointerdown', function (ev) {
                if (ev.button !== 0) return;
                var at = cell(ev);
                var handle = ev.target.closest('[data-sd-ga-handle]');
                var i = A.at(st.areas, at.c, at.r);
                drag = handle ? { kind: 'size', i: st.areas.map(function (a) { return a.name; }).indexOf(_ui.sel), from: at }
                    : i !== -1 ? { kind: 'move', i: i, from: at } : { kind: 'make', from: at };
                drag.rc = null;
                grid.setPointerCapture(ev.pointerId);
                ev.preventDefault();
            });
            grid.addEventListener('pointermove', function (ev) {
                if (!drag) return;
                var at = cell(ev), a = drag.i >= 0 ? st.areas[drag.i] : null;
                if (drag.kind === 'make') drag.rc = A.rect(drag.from, at);
                else if (drag.kind === 'move' && (at.c !== drag.from.c || at.r !== drag.from.r || drag.rc)) {
                    drag.rc = { c: a.c + at.c - drag.from.c, r: a.r + at.r - drag.from.r, w: a.w, h: a.h };
                } else if (drag.kind === 'size' && a) drag.rc = { c: a.c, r: a.r, w: Math.max(1, at.c - a.c + 1), h: Math.max(1, at.r - a.r + 1) };
                if (drag.rc) show(drag.rc, !A.free(st.areas, drag.rc, st.cols, st.rows, drag.kind === 'make' ? -1 : drag.i), drag.rc.w + ' × ' + drag.rc.h);
            });
            var end = function () {
                if (!drag) return;
                var d = drag;
                drag = null;
                show(null);
                if (d.kind === 'make') put(d.rc || { c: d.from.c, r: d.from.r, w: 1, h: 1 }, -1);
                else if (!d.rc) choose(st.areas[d.i] ? st.areas[d.i].name : '');
                else if (d.i >= 0) put(d.rc, d.i);
            };
            grid.addEventListener('pointerup', end);
            grid.addEventListener('pointercancel', function () { drag = null; show(null); });
            grid.addEventListener('keydown', function (ev) { _gridKey(ev, host, st, show, put, choose, redraw); });
        }

        box.querySelectorAll('.sd-ga-li').forEach(function (li) {
            var a = st.areas[+li.getAttribute('data-i')];
            li.addEventListener('pointerenter', function () { _ov.hot = a.name; _overlayDraw(); });
            li.addEventListener('pointerleave', function () { _ov.hot = ''; _overlayDraw(); });
        });
        box.querySelectorAll('[data-sd-ga-name]').forEach(function (b) {
            b.addEventListener('click', function () {
                var a = st.areas[+b.getAttribute('data-sd-ga-name')];
                if (!st.own) { choose(a.name); return; }
                if (_ui.sel === a.name) { _ui.edit = a.name; _ui.fresh = false; redraw(); } else choose(a.name);
            });
        });
        box.querySelectorAll('[data-sd-ga-bin]').forEach(function (b) {
            b.addEventListener('click', function () { _remove(host, st, +b.getAttribute('data-sd-ga-bin')); });
        });
        var reset = box.querySelector('[data-sd-ga-reset]');
        if (reset) reset.addEventListener('click', function () {
            var text = Z.write(host.text(), st.cls, A.CSS, host.size, '');
            (st.node.children || []).forEach(function (ch) {
                var c = ch && ch.props ? _cls(ch) : '';
                if (c) text = Z.write(text, c, A.ITEM, host.size, '');
            });
            _ui.sel = '';
            _setNote(st.node.id, '');
            host.commit(text, [null]);
        });
        var input = box.querySelector('[data-sd-ga-input]');
        if (input) {
            var i = st.areas.map(function (a) { return a.name; }).indexOf(_ui.edit);
            var done = false;
            var keep = function () {
                if (done) return;
                var v = input.value.trim();
                if (v === st.areas[i].name) { done = true; _ui.edit = ''; _ui.fresh = false; redraw(); return; }
                var e = A.nameError(v, st.areas, i);
                if (e) {
                    setErr(e === 'taken' ? _sdT('This name is taken.') : _sdT('Use letters, digits, - and _, starting with a letter.'));
                    var line = box.querySelector('.sd-ga-line.is-bad');
                    if (!line) { line = document.createElement('div'); line.className = 'sd-ga-line is-bad'; line.setAttribute('role', 'alert'); input.closest('.sd-ga-list').after(line); }
                    line.textContent = _ui.err;
                    input.classList.add('is-invalid');
                    return;
                }
                done = true;
                _rename(host, st, i, v);
            };
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); keep(); }
                if (ev.key === 'Escape') {
                    ev.preventDefault();
                    ev.stopPropagation();
                    done = true;
                    if (_ui.fresh) { _ui.fresh = false; _remove(host, st, i); return; }
                    _ui.edit = '';
                    _ui.err = '';
                    redraw();
                }
            });
            input.addEventListener('blur', function () { if (!input.classList.contains('is-invalid')) keep(); });
            input.focus();
            input.select();
        }
    }

    // The keys of the small grid: arrows move (an area counts as one),
    // Shift with arrows marks or resizes, Enter makes or names, Delete
    // removes, Escape gives up the mark, then leaves.
    function _gridKey(ev, host, st, show, put, choose, redraw) {
        var grid = ev.currentTarget;
        // The editor's own keys (Delete removes the element) stay out.
        if (/^(Arrow|Enter$|Delete$|Backspace$|Escape$| $)/.test(ev.key)) ev.stopPropagation();
        var k = _ui.key || { c: 1, r: 1, mark: null };
        var dir = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[ev.key];
        var i = A.at(st.areas, k.c, k.r);
        var say = function () {
            var j = A.at(st.areas, k.c, k.r);
            var held = _held(host, st.node, host.text());
            _announce(host, j === -1 ? _sdT('column {var:1}, row {var:2}, free', [k.c, k.r])
                : st.areas[j].name + ', ' + _placeText(st.areas[j], st.cols) + ', ' + (held[st.areas[j].name] ? _elements(held[st.areas[j].name]) : _sdT('empty')));
            grid.querySelectorAll('i.is-key').forEach(function (c) { c.classList.remove('is-key'); });
            var c = grid.querySelector('i[data-c="' + k.c + '"][data-r="' + k.r + '"]');
            if (c) c.classList.add('is-key');
        };
        if (dir) {
            ev.preventDefault();
            if (ev.shiftKey && i !== -1 && st.areas[i].name === _ui.sel && st.own) {
                var a = st.areas[i];
                put({ c: a.c, r: a.r, w: Math.max(1, a.w + dir[0]), h: Math.max(1, a.h + dir[1]) }, i);
                return;
            }
            if (ev.shiftKey && !k.mark && i === -1) k.mark = { c: k.c, r: k.r };
            if (!ev.shiftKey) k.mark = null;
            var cur = i !== -1 && !ev.shiftKey ? st.areas[i] : null;
            var nc = k.c + dir[0], nr = k.r + dir[1];
            if (cur) {
                nc = dir[0] > 0 ? cur.c + cur.w : dir[0] < 0 ? cur.c - 1 : k.c;
                nr = dir[1] > 0 ? cur.r + cur.h : dir[1] < 0 ? cur.r - 1 : k.r;
            }
            k.c = Math.max(1, Math.min(st.cols, nc));
            k.r = Math.max(1, Math.min(st.rows, nr));
            _ui.key = k;
            if (k.mark) {
                var rc = A.rect(k.mark, k);
                show(rc, !A.free(st.areas, rc, st.cols, st.rows, -1), rc.w + ' × ' + rc.h);
            } else show(null);
            say();
            return;
        }
        if (ev.key === 'Enter') {
            ev.preventDefault();
            if (k.mark) { var m = A.rect(k.mark, k); k.mark = null; _ui.key = k; put(m, -1); return; }
            if (i === -1) { put({ c: k.c, r: k.r, w: 1, h: 1 }, -1); return; }
            if (st.own) { _ui.sel = st.areas[i].name; _ui.edit = st.areas[i].name; _ui.fresh = false; redraw(); }
            return;
        }
        if ((ev.key === 'Delete' || ev.key === 'Backspace') && i !== -1 && st.own) {
            ev.preventDefault();
            _remove(host, st, i);
            return;
        }
        if (ev.key === 'Escape') {
            ev.stopPropagation();
            if (k.mark) { k.mark = null; _ui.key = k; show(null); return; }
            var fold = host.el.querySelector('[data-sd-ga-fold]');
            if (fold) fold.focus();
        }
        if (ev.key === ' ' && i !== -1) { ev.preventDefault(); choose(st.areas[i].name); }
    }

    // ── The areas over the canvas, while "Areas" is open on the grid box
    // or a child's "Area" is drawn: never saved, never on the site. A slow
    // check takes the overlay away once the row is gone.
    function _overlay(host, node, areas, box) {
        if (!host || !areas.length) {
            if (_ov.box === box || !_ov.box || !_ov.box.isConnected) _overlayOff();
            return;
        }
        _ov.host = host;
        _ov.node = node;
        _ov.areas = areas;
        _ov.box = box;
        _overlayDraw();
        if (!_ov.timer) {
            _ov.timer = setInterval(function () {
                if (!_ov.box || !_ov.box.isConnected) { _overlayOff(); return; }
                _overlayDraw();
            }, 500);
        }
    }

    function _overlayOff() {
        if (_ov.timer) clearInterval(_ov.timer);
        _ov.timer = 0;
        _ov.box = null;
        _ov.host = null;
        var doc = K.canvasDoc;
        var old = doc && doc.getElementById('sd-ga-overlay');
        if (old) old.remove();
    }

    var _OV_TINTS = ['#6aa5ff', '#3fd0a8', '#f2b84b', '#b19cff', '#f58aa0', '#9aa0a8'];

    function _overlayDraw() {
        var doc = K.canvasDoc;
        if (!doc || !_ov.node || !_ov.box) return;
        var old = doc.getElementById('sd-ga-overlay');
        var el = doc.querySelector('[data-sd-id="' + String(_ov.node.id).replace(/["\\]/g, '') + '"]');
        var win = doc.defaultView;
        if (!el || !win) { if (old) old.remove(); return; }
        var cs = win.getComputedStyle(el);
        var px = function (s) { return String(s || '').split(/\s+/).map(parseFloat).filter(function (n) { return !isNaN(n); }); };
        var colsW = px(cs.gridTemplateColumns), rowsH = px(cs.gridTemplateRows);
        if (!colsW.length || !rowsH.length) { if (old) old.remove(); return; }
        var b = el.getBoundingClientRect();
        var x0 = b.left + win.scrollX + parseFloat(cs.borderLeftWidth) + parseFloat(cs.paddingLeft);
        var y0 = b.top + win.scrollY + parseFloat(cs.borderTopWidth) + parseFloat(cs.paddingTop);
        var gx = parseFloat(cs.columnGap) || 0, gy = parseFloat(cs.rowGap) || 0;
        var edge = function (list, gap, i) { var s = 0; for (var k = 0; k < i && k < list.length; k++) s += list[k] + gap; return s; };
        var h = '';
        _ov.areas.forEach(function (a, i) {
            var t = _OV_TINTS[i % 6], hot = a.name === _ov.hot;
            var x = x0 + edge(colsW, gx, a.c - 1), y = y0 + edge(rowsH, gy, a.r - 1);
            var w = edge(colsW, gx, a.c - 1 + a.w) - gx - edge(colsW, gx, a.c - 1);
            var hh = edge(rowsH, gy, a.r - 1 + a.h) - gy - edge(rowsH, gy, a.r - 1);
            h += '<div style="position:absolute;left:' + x + 'px;top:' + y + 'px;width:' + Math.max(0, w) + 'px;height:' + Math.max(0, hh) +
                'px;border-radius:8px;background:' + t + (hot ? '57' : '33') + ';outline:' + (hot ? '2px solid ' : '1.5px dashed ') + t +
                ';outline-offset:-1px"><span style="position:absolute;right:6px;top:6px;height:19px;padding:0 7px;border-radius:5px;background:' + t +
                ';color:#0b1220;font:600 11px/19px system-ui,sans-serif;white-space:nowrap;max-width:calc(100% - 12px);overflow:hidden;text-overflow:ellipsis">' +
                esc(a.name) + '</span></div>';
        });
        if (!old) {
            old = doc.createElement('div');
            old.id = 'sd-ga-overlay';
            // The editor's chrome: a Side by side mirror leaves it out.
            old.className = 'sd-ga-overlay';
            old.setAttribute('aria-hidden', 'true');
            old.style.cssText = 'position:absolute;left:0;top:0;width:0;height:0;pointer-events:none;z-index:2147483000';
        }
        if (old.innerHTML !== h) old.innerHTML = h;
        if (!old.isConnected) doc.body.appendChild(old);
    }

    return { draw: draw, drawItem: drawItem };
});
