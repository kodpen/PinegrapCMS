/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel field widgets behind one interface: pgChips, pgCombobox,
 * pgDatePicker and pgDateTimeField.
 *
 * The panel moves to Bootstrap 6 (docs/memory/plans/bootstrap-6-panel.md),
 * whose Chips, Combobox and Datepicker replace tagin, select2 and the jQuery
 * UI pickers. Screens mark a field with a data-pg-* hook and read or write it
 * through these objects, so the swap changes the engine here, not the
 * screens. Chips run on Bootstrap 6 ('v6'); the other kinds still on the
 * engine the panel always used ('v5').
 *
 *   <input data-pg-chips>                 Bootstrap 6 Chips drawn after the hidden
 *                                         input, which keeps the joined list: comma
 *                                         or data-pg-separator (" " for classes)
 *   <select data-pg-combobox>             select2; the native <select> keeps the value
 *     data-pg-source="url"                options searched on the server as you type:
 *                                         GET url?q=<text> answers {results: [{id, text}]};
 *                                         data-pg-min-length, data-pg-free-text (a typed
 *                                         value is kept), data-placeholder
 *   <input data-pg-date>                  jQuery UI datepicker, site date format
 *   <input data-pg-date="plain">          the same in the picker's own English wording
 *   <input data-pg-datetime>              date and time in one field
 *   <input data-pg-time>                  time only
 *
 * The named input or the native <select> is always what the form posts, in
 * the text the server reads today. Values cross this interface as ISO text
 * ('2026-10-26', '17:00', '2026-10-26 17:00'), parsed and formatted here from
 * the site date format, never by Date() parsing.
 *
 *   pgFields.init(scope)                  every hooked field in scope, once
 *   pgChips.get(el) / set(el, list)       array of strings
 *   pgCombobox.get(el) / set(el, value)   string, array for a multiple select
 *   pgDatePicker.get(el) / set(el, iso)
 *   pgDateTimeField.get(el) / set(el, iso), the same for a time-only field
 *   pgFields.onChange(el, fn)             every change, typed or picked; returns off()
 *   pgFields.destroy(el)                  back to a plain field, value kept
 *   pgFields.switchKind(el, kind)         the same field as another kind ('date' <-> 'datetime')
 *
 * set() fires a native change event that bubbles; listen with onChange() or
 * jQuery .on('change'): select2 announces a pick only through jQuery.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (window, document) {
    'use strict';

    // Loaded again with markup injected later (a settings card brings its
    // own tag): the first copy stays and draws the new fields.
    if (window.pgFields) {
        window.pgFields.init(document);
        return;
    }

    var HOOKS = {
        chips: 'data-pg-chips',
        combobox: 'data-pg-combobox',
        date: 'data-pg-date',
        datetime: 'data-pg-datetime',
        time: 'data-pg-time'
    };

    function jq() {
        return window.jQuery || null;
    }

    function element(target) {
        if (!target) {
            return null;
        }
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (target.jquery) {
            return target[0] || null;
        }
        return target;
    }

    function pad(n) {
        return ('0' + n).slice(-2);
    }

    // ------------------------------------------------------------ formats

    // 'month_day' or 'day_month', from the format the screen printed with
    // get_date_picker_format() ('m/d/yy' or 'd/m/yy').
    function dateOrder() {
        var f = window.date_picker_format
            || (window.datetimepicker_options && window.datetimepicker_options.dateFormat)
            || 'd/m/yy';
        return /^m/i.test(String(f)) ? 'month_day' : 'day_month';
    }

    function validDate(y, m, d) {
        if (m < 1 || m > 12 || d < 1) {
            return false;
        }
        var days = [31, (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        return d <= days[m - 1];
    }

    // Site text ('26/10/2026', '10/26/2026'; '-' and '.' are read as '/') to
    // '2026-10-26'; '' when it is not a whole, real date.
    function parseDate(text, order) {
        var p = String(text || '').trim().split(/[\/\-.]/);
        if (p.length !== 3 || !/^\d{4}$/.test(p[2]) || !/^\d{1,2}$/.test(p[0]) || !/^\d{1,2}$/.test(p[1])) {
            return '';
        }
        var y = parseInt(p[2], 10);
        var m = parseInt((order || dateOrder()) === 'month_day' ? p[0] : p[1], 10);
        var d = parseInt((order || dateOrder()) === 'month_day' ? p[1] : p[0], 10);
        return validDate(y, m, d) ? (p[2] + '-' + pad(m) + '-' + pad(d)) : '';
    }

    // '2026-10-26' to the site text without leading zeros, as the picker
    // writes it ('26/10/2026'); '' for anything else.
    function formatDate(iso, order) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
        if (!m || !validDate(+m[1], +m[2], +m[3])) {
            return '';
        }
        var d = String(+m[3]), mo = String(+m[2]);
        return ((order || dateOrder()) === 'month_day' ? (mo + '/' + d) : (d + '/' + mo)) + '/' + m[1];
    }

    // '5:00 PM', '5 pm', '17:00' or '17:00:00' to '17:00'; '' otherwise.
    // 12 AM is midnight, 12 PM noon.
    function parseTime(text) {
        var m = /^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*([ap])?\.?\s*m?\.?$/i.exec(String(text || '').trim());
        if (!m || (!m[2] && !m[3])) {
            return '';
        }
        var h = parseInt(m[1], 10), min = m[2] ? parseInt(m[2], 10) : 0;
        if (min > 59) {
            return '';
        }
        if (m[3]) {
            if (h < 1 || h > 12) {
                return '';
            }
            h = (h % 12) + (m[3].toLowerCase() === 'p' ? 12 : 0);
        } else if (h > 23) {
            return '';
        }
        return pad(h) + ':' + pad(min);
    }

    // '17:00' to '5:00 PM', the picker's "h:mm TT".
    function formatTime(hhmm) {
        var m = /^(\d{1,2}):(\d{2})$/.exec(String(hhmm || ''));
        if (!m || +m[1] > 23 || +m[2] > 59) {
            return '';
        }
        var h = +m[1];
        return ((h % 12) || 12) + ':' + m[2] + ' ' + (h < 12 ? 'AM' : 'PM');
    }

    // '26/10/2026 5:00 PM' to '2026-10-26 17:00'; a date alone reads as
    // midnight. '' when either part is wrong.
    function parseDateTime(text, order) {
        var m = /^(\S+)(?:\s+(.+))?$/.exec(String(text || '').trim());
        if (!m) {
            return '';
        }
        var date = parseDate(m[1], order);
        var time = m[2] ? parseTime(m[2]) : '00:00';
        return (date && time) ? (date + ' ' + time) : '';
    }

    function formatDateTime(value, order) {
        var m = /^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})/.exec(String(value || ''));
        if (!m) {
            return '';
        }
        var date = formatDate(m[1], order), time = formatTime(m[2]);
        return (date && time) ? (date + ' ' + time) : '';
    }

    // ------------------------------------------------------------ engines

    // One engine per Bootstrap generation; each kind has init(el), which
    // draws the widget over the posted field, redraw(el), which shows a
    // value written into that field, and destroy(el), which removes the
    // widget and leaves the field. A kind the v6 engine has runs on it; the
    // rest (Combobox, Datepicker) move with their component swaps. A v6 kind
    // has later(): true while Bootstrap's module has not run yet, so the
    // field is drawn once pgUI.ready() fires.
    var ENGINES = {
        v6: {
            chips: {
                ready: function () { return !!(window.pgUI && window.pgUI.version() === 6); },
                later: function () { return !!(window.pgUI && window.pgUI.version() === 0); },
                init: drawChips,
                // Chips reads its markup only when it is created, so a value
                // written into the input is drawn again from scratch.
                redraw: function (el) {
                    removeChips(el);
                    drawChips(el);
                },
                destroy: removeChips
            }
        },
        v5: {
            combobox: {
                ready: function () { var $ = jq(); return !!($ && $.fn.select2); },
                init: function (el) {
                    var $ = jq(), opts = { theme: 'bootstrap-5' };
                    if ($(el).data('allowClear')) {
                        opts.allowClear = true;
                    }
                    if (el.getAttribute('data-pg-source')) {
                        remoteOptions(el, opts);
                    }
                    if (window.pgUI && window.pgUI.overlayHost) {
                        opts.dropdownParent = $(window.pgUI.overlayHost(el));
                    }
                    $(el).select2(opts);
                },
                // Only select2's own listener: the native change already ran.
                redraw: function (el) { jq()(el).trigger('change.select2'); },
                destroy: function (el) { jq()(el).select2('destroy'); }
            },
            date: {
                ready: function () { var $ = jq(); return !!($ && $.fn.datepicker); },
                init: function (el) {
                    // "plain" keeps the screens that never passed the translated
                    // names: only the site date format.
                    var opts = el.getAttribute(HOOKS.date) === 'plain'
                        ? { dateFormat: (window.datetimepicker_options && window.datetimepicker_options.dateFormat) || window.date_picker_format }
                        : (window.datetimepicker_options || {});
                    jq()(el).datepicker(inOverlayHost(opts));
                },
                redraw: function () {},
                destroy: destroyPicker
            },
            datetime: {
                ready: function () { var $ = jq(); return !!($ && $.fn.datetimepicker); },
                init: function (el) { jq()(el).datetimepicker(inOverlayHost(window.datetimepicker_options || {})); },
                redraw: function () {},
                destroy: destroyPicker
            },
            time: {
                ready: function () { var $ = jq(); return !!($ && $.fn.timepicker); },
                init: function (el) { jq()(el).timepicker(inOverlayHost(window.timepicker_options || window.datetimepicker_options || {})); },
                redraw: function () {},
                destroy: destroyPicker
            }
        }
    };

    // select2 asks data-pg-source for the options matching the typed text.
    // A value the field already holds is an <option> in the markup.
    function remoteOptions(el, opts) {
        opts.ajax = {
            url: el.getAttribute('data-pg-source'),
            dataType: 'json',
            delay: 200,
            cache: true,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: (data && data.results) ? data.results : [] }; }
        };
        opts.minimumInputLength = parseInt(el.getAttribute('data-pg-min-length'), 10) || 0;
        if (el.hasAttribute('data-pg-free-text')) {
            opts.tags = true;
        }
        if (el.getAttribute('data-placeholder')) {
            opts.placeholder = el.getAttribute('data-placeholder');
        }
    }

    // The jQuery UI picker's one box moves into the field's overlay host each
    // time it opens (the open dialog holding the field, else <body>); a
    // field's own beforeShow still runs.
    function inOverlayHost(opts) {
        var copy = {}, own = opts.beforeShow;
        for (var key in opts) {
            if (Object.prototype.hasOwnProperty.call(opts, key)) {
                copy[key] = opts[key];
            }
        }
        copy.beforeShow = function (input, inst) {
            if (window.pgUI && window.pgUI.overlayHost && inst && inst.dpDiv) {
                var host = window.pgUI.overlayHost(input);
                if (host && inst.dpDiv[0].parentNode !== host) {
                    host.appendChild(inst.dpDiv[0]);
                }
            }
            return own ? own.apply(this, arguments) : {};
        };
        return copy;
    }

    // The posted input's own classes the chip box takes over: its borders,
    // sizes and margins, not the control and hook classes.
    var CHIPS_SKIP = { 'tagin': 1, 'form-control': 1, 'form-control-sm': 1, 'form-control-lg': 1 };

    // The translated label of a chip's remove button, which Bootstrap
    // hard-codes as "Remove"; pg_fields_script() prints it on its tag.
    function removeLabel() {
        var tag = document.querySelector('script[data-pg-remove-label]');
        return (tag && tag.getAttribute('data-pg-remove-label')) || 'Remove';
    }

    function labelDismissButtons(box) {
        var buttons = box.querySelectorAll('.chip-dismiss'), label = removeLabel();
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('aria-label', label);
        }
    }

    // A .chip-input box after the hidden input, holding a chip per stored
    // value and a ghost input to type in. The chips are in the markup before
    // Chips is created, so drawing a stored value announces nothing and
    // leaves the form clean. Every add or remove writes the joined list back
    // into the input and fires its native change.
    function drawChips(el) {
        var sep = separator(el), box = document.createElement('div'), ghost = document.createElement('input');
        var classes = ['chip-input', 'pg-chips'], own = String(el.className || '').split(/\s+/), list = pgChips.get(el);
        for (var i = 0; i < own.length; i++) {
            if (own[i] && !CHIPS_SKIP[own[i]]) {
                classes.push(own[i]);
            }
        }
        if (/(^|\s)form-control-sm(\s|$)/.test(el.className || '')) {
            classes.push('pg-chips-sm');
        }
        box.className = classes.join(' ');
        for (var j = 0; j < list.length; j++) {
            var chip = document.createElement('span');
            chip.className = 'chip';
            chip.setAttribute('data-bs-chip-value', list[j].trim());
            chip.appendChild(document.createTextNode(list[j].trim()));
            box.appendChild(chip);
        }
        ghost.type = 'text';
        ghost.className = 'form-ghost';
        ghost.setAttribute('autocomplete', 'off');
        if (el.getAttribute('data-placeholder')) {
            ghost.setAttribute('placeholder', el.getAttribute('data-placeholder'));
        }
        // Not readOnly: the settings dialog shuts every text field read-only
        // until it is reached, the hidden input too.
        if (el.disabled) {
            ghost.disabled = true;
        }
        box.appendChild(ghost);
        el.parentNode.insertBefore(box, el.nextSibling);
        el.pgChipsBox = box;
        var chips = window.pgUI.chips.get(box, { separator: sep });
        labelDismissButtons(box);
        el.pgChipsOff = window.pgUI.chips.on(box, 'change', function () {
            labelDismissButtons(box);
            el.value = chips.getValues().join(sep);
            fireChange(el);
        });
    }

    function removeChips(el) {
        var box = el.pgChipsBox;
        if (el.pgChipsOff) {
            el.pgChipsOff();
        }
        if (box) {
            window.pgUI.chips.dispose(box);
            if (box.parentNode) {
                box.parentNode.removeChild(box);
            }
        }
        el.pgChipsBox = null;
        el.pgChipsOff = null;
    }

    // The datetimepicker and timepicker addon build on the datepicker, whose
    // destroy removes any of the three.
    function destroyPicker(el) {
        jq()(el).datepicker('destroy');
    }

    function engineName(kind) {
        return ENGINES.v6[kind] ? 'v6' : 'v5';
    }

    function engine(kind) {
        return ENGINES[engineName(kind)][kind];
    }

    // ------------------------------------------------------------ fields

    function kindOf(el) {
        for (var kind in HOOKS) {
            if (el.hasAttribute && el.hasAttribute(HOOKS[kind])) {
                return kind;
            }
        }
        return '';
    }

    // Draws the widget once; a field whose library is not on the screen stays
    // a plain field that still posts.
    function initField(el, kind) {
        el = element(el);
        kind = kind || (el ? kindOf(el) : '');
        if (!el || !kind || el.pgFieldKind) {
            return el;
        }
        var e = engine(kind);
        if (!e.ready()) {
            if (e.later && e.later() && !el.pgFieldWaiting) {
                el.pgFieldWaiting = true;
                window.pgUI.ready(function () {
                    el.pgFieldWaiting = false;
                    initField(el, kind);
                });
            }
            return el;
        }
        el.pgFieldKind = kind;
        e.init(el);
        return el;
    }

    function init(scope) {
        var root = element(scope) || document;
        var selector = Object.keys(HOOKS).map(function (k) { return '[' + HOOKS[k] + ']'; }).join(',');
        var list = root.querySelectorAll(selector);
        for (var i = 0; i < list.length; i++) {
            initField(list[i]);
        }
        if (root !== document && root.matches && root.matches(selector)) {
            initField(root);
        }
    }

    // Removes the widget; the field keeps its value and hook and posts as
    // before. init() draws it again.
    function destroy(el) {
        el = element(el);
        if (el && el.pgFieldKind) {
            engine(el.pgFieldKind).destroy(el);
            el.pgFieldKind = '';
        }
        return el;
    }

    // The same field as another kind, as the calendar's all-day switch
    // turns a date and time into a date: one hook, drawn once.
    function switchKind(el, kind) {
        el = element(el);
        if (!el || !HOOKS[kind] || el.pgFieldKind === kind) {
            return el;
        }
        destroy(el);
        for (var k in HOOKS) {
            if (k !== kind && el.hasAttribute(HOOKS[k])) {
                el.removeAttribute(HOOKS[k]);
            }
        }
        el.setAttribute(HOOKS[kind], '');
        return initField(el, kind);
    }

    function fireChange(el) {
        var ev;
        if (typeof window.Event === 'function') {
            ev = new window.Event('change', { bubbles: true });
        } else {
            ev = document.createEvent('Event');
            ev.initEvent('change', true, false);
        }
        el.dispatchEvent(ev);
    }

    // Writes the posted text, shows it in the widget and announces it.
    function write(el, kind, text) {
        el = element(el);
        if (!el) {
            return;
        }
        el.value = text;
        fireChange(el);
        if (el.pgFieldKind) {
            engine(kind).redraw(el);
        }
    }

    function onChange(el, fn) {
        el = element(el);
        var $ = jq();
        if (!el) {
            return function () {};
        }
        if ($) {
            var handler = function (e) { fn.call(el, e); };
            $(el).on('change', handler);
            return function () { $(el).off('change', handler); };
        }
        el.addEventListener('change', fn);
        return function () { el.removeEventListener('change', fn); };
    }

    // data-pg-separator, as liveform prints it; data-separator as older
    // hand-written markup has it.
    function separator(el) {
        return el.getAttribute('data-pg-separator') || el.getAttribute('data-separator') || ',';
    }

    var pgChips = {
        init: function (el) { return initField(el, 'chips'); },
        get: function (el) {
            el = element(el);
            if (!el || String(el.value).trim() === '') {
                return [];
            }
            return String(el.value).split(separator(el)).filter(function (v) { return v !== ''; });
        },
        set: function (el, list) {
            el = element(el);
            if (el) {
                write(el, 'chips', (list || []).join(separator(el)));
            }
        }
    };

    var pgCombobox = {
        init: function (el) { return initField(el, 'combobox'); },
        get: function (el) {
            el = element(el);
            if (!el) {
                return null;
            }
            if (!el.multiple) {
                return el.value;
            }
            var out = [];
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].selected) {
                    out.push(el.options[i].value);
                }
            }
            return out;
        },
        set: function (el, value) {
            el = element(el);
            if (!el) {
                return;
            }
            var wanted = [].concat(value === null || value === undefined ? [] : value).map(String);
            if (el.multiple) {
                for (var i = 0; i < el.options.length; i++) {
                    el.options[i].selected = wanted.indexOf(el.options[i].value) !== -1;
                }
            } else {
                el.value = wanted.length ? wanted[0] : '';
            }
            fireChange(el);
            if (el.pgFieldKind) {
                engine('combobox').redraw(el);
            }
        },
        // After options were added or removed by a script.
        refresh: function (el) {
            el = element(el);
            if (el && el.pgFieldKind) {
                engine('combobox').redraw(el);
            }
        }
    };

    var pgDatePicker = {
        init: function (el) { return initField(el, 'date'); },
        get: function (el) { el = element(el); return el ? parseDate(el.value) : ''; },
        set: function (el, iso) { write(el, 'date', formatDate(iso)); },
        parse: parseDate,
        format: formatDate,
        order: dateOrder
    };

    // Date and time in one field (data-pg-datetime) or time only
    // (data-pg-time); get() and set() follow the field's hook.
    var pgDateTimeField = {
        init: function (el) {
            el = element(el);
            return initField(el, el && el.hasAttribute(HOOKS.time) ? 'time' : 'datetime');
        },
        get: function (el) {
            el = element(el);
            if (!el) {
                return '';
            }
            return el.hasAttribute(HOOKS.time) ? parseTime(el.value) : parseDateTime(el.value);
        },
        set: function (el, iso) {
            el = element(el);
            if (!el) {
                return;
            }
            var time = el.hasAttribute(HOOKS.time);
            write(el, time ? 'time' : 'datetime', time ? formatTime(iso) : formatDateTime(iso));
        },
        parse: parseDateTime,
        format: formatDateTime,
        parseTime: parseTime,
        formatTime: formatTime
    };

    window.pgFields = {
        init: init,
        onChange: onChange,
        destroy: destroy,
        switchKind: switchKind,
        // 'v6' or 'v5': the engine that draws a kind.
        engine: engineName,
        hooks: HOOKS
    };
    window.pgChips = pgChips;
    window.pgCombobox = pgCombobox;
    window.pgDatePicker = pgDatePicker;
    window.pgDateTimeField = pgDateTimeField;

    // Fields in the page as it was sent; markup added later is drawn by
    // pgFields.init(scope) from the code that adds it.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }
})(window, document);
