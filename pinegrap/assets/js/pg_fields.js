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
 * screens. Every kind runs on Bootstrap 6 ('v6'); only a page without it that
 * has select2 (a site page) draws a combobox that searches the server as a
 * select2 ('v5'). A page without either keeps plain fields that still post.
 *
 *   <input data-pg-chips>                 Bootstrap 6 Chips drawn after the hidden
 *                                         input, which keeps the joined list: comma
 *                                         or data-pg-separator (" " for classes)
 *   <select data-pg-combobox>             Bootstrap 6 Combobox drawn after the hidden
 *                                         <select>, which keeps the value; data-placeholder
 *     data-pg-source="url"                options searched on the server as you type:
 *                                         GET url?q=<text> answers {results: [{id, text}]}
 *                                         or {error: text};
 *                                         data-pg-min-length, data-pg-free-text (a typed
 *                                         value is kept), data-placeholder
 *   <input data-pg-date>                  Bootstrap 6 Datepicker on the field, site date
 *                                         format, names in the panel language
 *   <input data-pg-date="plain">          the same with English names
 *   <input data-pg-datetime>              date and time: the field hidden, a date box with
 *                                         a Datepicker and an <input type="time"> after it
 *   <input data-pg-time>                  time only: an <input type="time"> after the
 *                                         hidden field
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
 * jQuery .on('change'): select2 (site pages) announces a pick only through
 * jQuery.
 * Bootstrap 6 Datepicker reads an input's date with Date(), which takes
 * 5/3/2026 as May; it is given our own parse instead and writes our own
 * format.
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
    // widget and leaves the field. A kind the v6 engine has runs on it. A v6 kind
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
            },
            combobox: {
                ready: function () { return !!(window.pgUI && window.pgUI.version() === 6); },
                later: function () { return !!(window.pgUI && window.pgUI.version() === 0); },
                init: drawCombobox,
                // Combobox reads its items only when it is created.
                redraw: function (el) {
                    removeCombobox(el);
                    drawCombobox(el);
                },
                destroy: removeCombobox
            },
            date: {
                ready: function () { return !!(window.pgUI && window.pgUI.version() === 6); },
                later: function () { return !!(window.pgUI && window.pgUI.version() === 0); },
                init: drawDate,
                // The field's own change listener already moved the calendar
                // to the written date.
                redraw: function () {},
                destroy: removeDate
            },
            datetime: {
                ready: function () { return !!(window.pgUI && window.pgUI.version() === 6); },
                later: function () { return !!(window.pgUI && window.pgUI.version() === 0); },
                init: drawDateTime,
                redraw: showDateTime,
                destroy: removeParts
            },
            time: {
                ready: function () { return !!(window.pgUI && window.pgUI.version() === 6); },
                later: function () { return !!(window.pgUI && window.pgUI.version() === 0); },
                init: drawTime,
                redraw: showTime,
                destroy: removeParts
            }
        },
        v5: {
            combobox: {
                ready: function () { var $ = jq(); return !!($ && $.fn.select2); },
                init: function (el) {
                    var $ = jq(), opts = { theme: 'bootstrap-5' };
                    // select2's own texts are English; the tag's labels are
                    // in the page language.
                    opts.language = {
                        noResults: function () { return label('no-results', 'No results'); },
                        searching: function () { return label('searching', 'Searching\u2026'); },
                        search: function () { return label('search', 'Search'); }
                    };
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

    // A combobox offers a search box from this many options on.
    var COMBOBOX_SEARCH_FROM = 8;

    var CARET = '<svg class="combobox-caret" width="10" height="16" viewBox="0 0 10 16" fill="none" aria-hidden="true"><path d="M.47 5.47a.75.75 0 0 1 1.06 0L5 8.94l3.47-3.47a.75.75 0 1 1 1.06 1.06l-4 4a.75.75 0 0 1-1.06 0l-4-4a.75.75 0 0 1 0-1.06Z" fill="currentcolor"/></svg>';

    var CHECK = '<svg class="menu-item-check" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentcolor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    // The select's own classes the toggle takes over: sizes, validation,
    // margins; not the control class, which the toggle has anyway.
    var COMBOBOX_SKIP = { 'form-control': 1, 'select2': 1, 'pg-combobox-native': 1 };

    function comboboxItem(option, multiple, placeholder) {
        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'menu-item';
        item.setAttribute('data-bs-value', option.value);
        // An empty value is the "nothing chosen" item, which takes the
        // place of select2's clear button: it reads as the placeholder and is
        // never shown as the choice.
        if (option.selected && (multiple || option.value !== '')) {
            item.className += ' selected';
            item.setAttribute('aria-selected', 'true');
        }
        if (option.disabled) {
            item.disabled = true;
        }
        item.textContent = option.textContent.trim() || (option.value === '' ? (placeholder || '\u2014') : option.value);
        // Several can be chosen: a check mark shows which (Bootstrap shows it
        // on .selected).
        if (multiple) {
            item.insertAdjacentHTML('beforeend', CHECK);
        }
        return item;
    }

    // The chosen options' texts, as the toggle shows them: every one for a
    // multiple select (Bootstrap's own count is English), the placeholder
    // when nothing is chosen.
    function comboboxText(el) {
        var out = [];
        for (var i = 0; i < el.options.length; i++) {
            var o = el.options[i];
            if (o.selected && o.value !== '') {
                out.push(o.textContent.trim());
            }
        }
        return out.join(', ');
    }

    function showComboboxText(el) {
        var value = el.pgComboboxToggle.querySelectorAll('.combobox-value')[0], text = comboboxText(el);
        value.textContent = text || el.getAttribute('data-placeholder') || '';
        value.className = 'combobox-value' + (text ? '' : ' combobox-placeholder');
    }

    // A toggle and a .menu after the hidden <select>, an item per option and
    // a header per option group; the chosen items are marked in the markup,
    // so drawing announces nothing. A choice writes the <select> and fires its
    // native change, which the ERP account fields re-rate taxes on.
    function drawCombobox(el) {
        var multiple = !!el.multiple, toggle = document.createElement('button'), menu = document.createElement('div');
        var classes = ['form-control', 'combobox-toggle', 'pg-combobox'], own = String(el.className || '').split(/\s+/);
        for (var i = 0; i < own.length; i++) {
            if (own[i] && !COMBOBOX_SKIP[own[i]]) {
                classes.push(own[i]);
            }
        }
        toggle.type = 'button';
        toggle.className = classes.join(' ');
        toggle.disabled = !!el.disabled;
        var shown = document.createElement('span');
        shown.className = 'combobox-value';
        toggle.appendChild(shown);
        toggle.insertAdjacentHTML('beforeend', CARET);
        menu.className = 'menu pg-combobox-menu';
        var source = el.getAttribute('data-pg-source'), query = null, status = null;
        var count = el.options.length, search = !source && count >= COMBOBOX_SEARCH_FROM;
        if (source) {
            menu.className += ' pg-combobox-remote';
            // Our own search box: Bootstrap's would hide the server's answers
            // that do not contain the typed text word for word.
            var qbox = document.createElement('div');
            query = document.createElement('input');
            status = document.createElement('div');
            qbox.className = 'combobox-search';
            query.type = 'text';
            query.className = 'form-control pg-combobox-query';
            query.setAttribute('autocomplete', 'off');
            query.setAttribute('placeholder', label('search', 'Search'));
            query.setAttribute('aria-label', label('search', 'Search'));
            query.setAttribute('aria-autocomplete', 'list');
            qbox.appendChild(query);
            menu.appendChild(qbox);
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            menu.appendChild(status);
            if (el.id) {
                menu.id = el.id + '_menu';
                query.setAttribute('aria-controls', menu.id);
            }
        }
        if (search) {
            var box = document.createElement('div'), input = document.createElement('input'), none = document.createElement('div');
            box.className = 'combobox-search';
            input.type = 'text';
            input.className = 'form-control combobox-search-input';
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('placeholder', label('search', 'Search'));
            input.setAttribute('aria-label', label('search', 'Search'));
            box.appendChild(input);
            menu.appendChild(box);
            none.className = 'combobox-no-results d-none';
            none.textContent = label('no-results', 'No results');
            menu.appendChild(none);
        }
        var children = el.children;
        for (var j = 0; j < children.length; j++) {
            var child = children[j];
            if (child.tagName === 'OPTGROUP') {
                var header = document.createElement('div');
                header.className = 'menu-header';
                header.textContent = child.getAttribute('label') || '';
                menu.appendChild(header);
                for (var k = 0; k < child.children.length; k++) {
                    menu.appendChild(comboboxItem(child.children[k], multiple, el.getAttribute('data-placeholder')));
                }
            } else if (child.tagName === 'OPTION') {
                menu.appendChild(comboboxItem(child, multiple, el.getAttribute('data-placeholder')));
            }
        }
        el.classList.add('pg-combobox-native');
        el.parentNode.insertBefore(menu, el.nextSibling);
        el.parentNode.insertBefore(toggle, menu);
        el.pgComboboxToggle = toggle;
        el.pgComboboxMenu = menu;
        // The field's label names the toggle, which is what people reach.
        var named = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
        if (named) {
            named.id = named.id || (el.id + '_label');
            toggle.setAttribute('aria-labelledby', named.id);
        }
        // Bootstrap 6's .collapse clips overflow-y, so a menu placed inside a
        // switch panel would be cut off at the panel's edge: place it fixed.
        if (el.closest && el.closest('.collapse')) {
            toggle.setAttribute('data-bs-strategy', 'fixed');
        }
        var combobox = window.pgUI.combobox.get(toggle, {
            multiple: multiple,
            search: search,
            placeholder: el.getAttribute('data-placeholder') || ''
        });
        showComboboxText(el);
        var offs = [
            window.pgUI.combobox.on(toggle, 'change', function () {
                var items = menu.querySelectorAll('.menu-item'), chosen = {};
                for (var n = 0; n < items.length; n++) {
                    if ((' ' + items[n].className + ' ').indexOf(' selected ') >= 0) {
                        chosen[items[n].getAttribute('data-bs-value')] = items[n].textContent.trim();
                    }
                }
                // A server's answer is not among the options: the pick
                // becomes the select's option, the others it found go.
                if (source) {
                    keepChosenOptions(el, chosen);
                }
                for (var m = 0; m < el.options.length; m++) {
                    el.options[m].selected = Object.prototype.hasOwnProperty.call(chosen, el.options[m].value);
                }
                if (!multiple && !Object.keys(chosen).length) {
                    el.value = '';
                }
                showComboboxText(el);
                fireChange(el);
            }),
            // The menu is at least as wide as its field.
            window.pgUI.combobox.on(toggle, 'show', function () {
                menu.style.minWidth = toggle.offsetWidth + 'px';
            })
        ];
        // Bootstrap opens a combobox from its click data API, which needs
        // data-bs-toggle="combobox"; that attribute would also let its
        // DOMContentLoaded pass create an instance with the default options,
        // so the toggle opens its own instance here.
        var open = function (event) {
            event.preventDefault();
            combobox.toggle();
        };
        toggle.addEventListener('click', open);
        offs.push(function () { toggle.removeEventListener('click', open); });
        if (source) {
            offs = offs.concat(serverSearch(el, menu, query, status, toggle));
        }
        el.pgComboboxOff = function () {
            offs.forEach(function (off) { off(); });
        };
        return combobox;
    }

    // The select keeps the empty option and the chosen ones, each with the
    // text of the item it was picked from.
    function keepChosenOptions(el, chosen) {
        var have = {};
        for (var i = el.options.length - 1; i >= 0; i--) {
            var o = el.options[i];
            if (o.value === '' || Object.prototype.hasOwnProperty.call(chosen, o.value)) {
                have[o.value] = true;
            } else {
                o.parentNode.removeChild(o);
            }
        }
        Object.keys(chosen).forEach(function (value) {
            if (!have[value]) {
                var option = document.createElement('option');
                option.value = value;
                option.textContent = chosen[value];
                el.appendChild(option);
            }
        });
    }

    // Wait after the last key before asking the server.
    var SEARCH_PAUSE = 250;

    // Server search for a data-pg-source combobox: the typed text is sent
    // as q after a pause, the answer is drawn as the menu's items (the
    // stored choice marked, the typed text last when data-pg-free-text), an
    // older answer arriving late is dropped. Opening shows the stored
    // choice and the empty item. Returns the listeners' off functions.
    function serverSearch(el, menu, query, status, toggle) {
        var source = el.getAttribute('data-pg-source'), timer = null, asked = 0, aborter = null;
        var min = parseInt(el.getAttribute('data-pg-min-length'), 10) || 0, free = el.hasAttribute('data-pg-free-text');
        var placeholder = el.getAttribute('data-placeholder');

        var say = function (text) {
            status.textContent = text || '';
            status.className = 'pg-combobox-status' + (text ? '' : ' d-none');
        };
        var items = function (list) {
            var old = menu.querySelectorAll('.menu-item');
            for (var i = 0; i < old.length; i++) {
                menu.removeChild(old[i]);
            }
            list.forEach(function (item) { menu.appendChild(item); });
        };
        // The select's own options: the empty item and the stored choice.
        var stored = function () {
            var list = [];
            for (var i = 0; i < el.options.length; i++) {
                list.push(comboboxItem(el.options[i], !!el.multiple, placeholder));
            }
            items(list);
            say(label('type', 'Type to search...'));
        };
        var answer = function (text, data) {
            var results = (data && data.results) ? data.results : [], list = [], seen = {};
            if (data && data.error) {
                items([]);
                say(String(data.error));
                return;
            }
            results.forEach(function (r) {
                var id = String(r.id);
                if (seen[id]) {
                    return;
                }
                seen[id] = true;
                list.push(comboboxItem({ value: id, textContent: String(r.text === undefined ? id : r.text), selected: el.value === id }, false, placeholder));
            });
            if (free && !seen[text]) {
                list.push(comboboxItem({ value: text, textContent: text, selected: el.value === text }, false, placeholder));
            }
            items(list);
            say(list.length ? '' : label('no-results', 'No results'));
        };
        var ask = function () {
            timer = null;
            var text = String(query.value).trim(), mine = ++asked;
            if (aborter) {
                aborter.abort();
                aborter = null;
            }
            if (text.length < min || text === '') {
                stored();
                return;
            }
            say(label('searching', 'Searching\u2026'));
            var init = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
            if (typeof window.AbortController === 'function') {
                aborter = new window.AbortController();
                init.signal = aborter.signal;
            }
            window.fetch(source + (source.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(text), init)
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (mine === asked) {
                        answer(text, data);
                    }
                }, function (error) {
                    if (mine === asked && !(error && error.name === 'AbortError')) {
                        answer(text, null);
                    }
                });
        };
        var typed = function () {
            if (timer) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(ask, SEARCH_PAUSE);
        };
        // Enter picks the first item instead of submitting the form; arrows
        // and Escape are Bootstrap's own menu keys.
        var keys = function (event) {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            var first = menu.querySelectorAll('.menu-item')[0];
            if (first && !first.disabled) {
                first.click();
            }
        };
        var opened = function () {
            if (timer) {
                window.clearTimeout(timer);
                timer = null;
            }
            asked++;
            query.value = '';
            stored();
            query.focus();
        };
        stored();
        query.addEventListener('input', typed);
        query.addEventListener('keydown', keys);
        return [
            window.pgUI.combobox.on(toggle, 'shown', opened),
            function () {
                if (timer) {
                    window.clearTimeout(timer);
                }
                if (aborter) {
                    aborter.abort();
                }
                asked++;
                query.removeEventListener('input', typed);
                query.removeEventListener('keydown', keys);
            }
        ];
    }

    function removeCombobox(el) {
        if (el.pgComboboxOff) {
            el.pgComboboxOff();
        }
        if (el.pgComboboxToggle) {
            window.pgUI.combobox.dispose(el.pgComboboxToggle);
            [el.pgComboboxToggle, el.pgComboboxMenu].forEach(function (node) {
                if (node && node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            });
        }
        el.classList.remove('pg-combobox-native');
        el.pgComboboxToggle = null;
        el.pgComboboxMenu = null;
        el.pgComboboxOff = null;
    }

    // The posted input's own classes the chip box takes over: its borders,
    // sizes and margins, not the control and hook classes.
    var CHIPS_SKIP = { 'tagin': 1, 'form-control': 1, 'form-control-sm': 1, 'form-control-lg': 1 };

    // Translated words the widgets show, printed by pg_fields_script() on its
    // tag as data-pg-<name>-label: 'remove' (a chip's button, which Bootstrap
    // hard-codes as "Remove"), 'search' and 'no-results' (a combobox),
    // 'searching' and 'type' (a combobox searching the server),
    // 'locale' (the panel language a calendar names months and days in).
    function label(name, fallback) {
        var attr = 'data-pg-' + name + '-label', tag = document.querySelector('script[' + attr + ']');
        return (tag && tag.getAttribute(attr)) || fallback;
    }

    function labelDismissButtons(box) {
        var buttons = box.querySelectorAll('.chip-dismiss'), text = label('remove', 'Remove');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('aria-label', text);
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
        // The field's <label> names the hidden input; the box typed in gets
        // its text, or a screen reader announces only the placeholder.
        if (el.labels && el.labels.length && el.labels[0].textContent.trim()) {
            ghost.setAttribute('aria-label', el.labels[0].textContent.replace(/\s+/g, ' ').trim());
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

    // ------------------------------------------------------------ dates (v6)

    // The panel language ('tr', 'en'), printed on the pg_fields_script() tag:
    // month and day names come from it.
    function locale() {
        return label('locale', 'en');
    }

    // Monday first where the screen's picker options say so (Turkish), else
    // Sunday as the jQuery UI picker had it.
    function firstWeekday(lang) {
        var o = window.datetimepicker_options;
        if (o && o.firstDay !== undefined) {
            return +o.firstDay || 0;
        }
        return lang === 'tr' ? 1 : 0;
    }

    // Datepicker options for a field; the date it shows is our own parse of
    // the site text and what it writes is our own format, so the order of
    // day and month never goes through Date() parsing.
    function datepickerOptions(el, iso, inline) {
        // "plain" keeps the screens that never passed the translated names:
        // English, Sunday first.
        var plain = el.getAttribute(HOOKS.date) === 'plain', lang = plain ? 'en' : locale();
        return {
            locale: lang,
            firstWeekday: plain ? 0 : firstWeekday(lang),
            inline: inline,
            placement: 'left',
            selectedDates: iso ? [iso] : [],
            dateFormat: function (date) {
                return formatDate(date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()));
            },
            // A second click on the chosen day keeps it, as before.
            vcpOptions: { enableDateToggle: false }
        };
    }

    // In a drawer the calendar is drawn open under the field: a popup there
    // would hang off the drawer's scrolling body.
    function inlineIn(el) {
        return !!(el.closest && el.closest('.drawer'));
    }

    // A Datepicker over the typed input `box` (the posted field itself for a
    // date, the date part of a date and time). Bootstrap reads an input's
    // value with Date() when it is created, so the box is empty for that
    // moment and gets its text back after. In a drawer the calendar is a
    // block after the box instead.
    function makeDatepicker(el, box, onPick) {
        var text = box.value, iso = parseDate(text), inline = inlineIn(el), target = box, wrap = null, offs = [];
        // The calendar takes over the element it is drawn in, classes
        // included, so it gets a box of its own inside ours.
        if (inline) {
            wrap = document.createElement('div');
            wrap.className = 'pg-date-inline';
            target = document.createElement('div');
            wrap.appendChild(target);
            box.parentNode.insertBefore(wrap, box.nextSibling);
        }
        box.value = '';
        var picker = window.pgUI.datepicker.get(target, datepickerOptions(el, iso, inline));
        box.value = text;
        offs.push(window.pgUI.datepicker.on(target, 'change', function (event) {
            var dates = (event && event.dates) || picker.getSelectedDates();
            box.value = dates.length ? formatDate(dates[0]) : '';
            onPick();
        }));
        if (!inline) {
            offs.push(placeInOverlayHost(box, picker));
        }
        // A typed date moves the calendar to it when the box is left.
        var typed = function () {
            if (!box.pgDatePicking) {
                showDate(el, box);
            }
        };
        box.addEventListener('change', typed);
        offs.push(function () { box.removeEventListener('change', typed); });
        box.pgDatepicker = { picker: picker, target: target, wrap: wrap, offs: offs };
        return picker;
    }

    function disposeDatepicker(box) {
        var d = box && box.pgDatepicker;
        if (!d) {
            return;
        }
        d.offs.forEach(function (off) { off(); });
        // Disposing puts a copy of the input as it was at creation in its
        // place; the field itself, with its value and listeners, goes back.
        var parent = box.parentNode, index = parent ? Array.prototype.indexOf.call(parent.childNodes, box) : -1;
        window.pgUI.datepicker.dispose(d.target);
        if (parent && box.parentNode !== parent && parent.childNodes[index]) {
            parent.replaceChild(box, parent.childNodes[index]);
        }
        if (d.wrap && d.wrap.parentNode) {
            d.wrap.parentNode.removeChild(d.wrap);
        }
        box.pgDatepicker = null;
    }

    // The calendar shows the date in `box`: Datepicker moves its month only
    // when it is created, so it is created again. A box whose text is not a
    // date keeps that text for the server to refuse.
    function showDate(el, box) {
        if (!box.pgDatepicker) {
            return;
        }
        var onPick = box.pgDatePick;
        disposeDatepicker(box);
        makeDatepicker(el, box, onPick);
    }

    // Bootstrap hangs the popup calendar off <body>, under an open dialog in
    // the top layer; it goes into the dialog holding the field and is placed
    // under the field there (the dialog is its containing block).
    function placeInOverlayHost(box, picker) {
        var place = function () {
            window.setTimeout(function () {
                // Datepicker gives no way to its calendar box but its own field.
        var cal = picker._calendar && picker._calendar.context && picker._calendar.context.mainElement;
                var host = window.pgUI.overlayHost(box);
                if (!cal || cal === box || host === document.body || cal.hasAttribute('data-vc-calendar-hidden')) {
                    return;
                }
                if (cal.parentNode !== host) {
                    host.appendChild(cal);
                }
                var f = box.getBoundingClientRect(), h = host.getBoundingClientRect();
                cal.style.top = (f.bottom - h.top - host.clientTop + host.scrollTop) + 'px';
                cal.style.left = Math.max(0, f.left - h.left - host.clientLeft + host.scrollLeft) + 'px';
            }, 0);
        };
        box.addEventListener('click', place);
        box.addEventListener('focus', place);
        window.addEventListener('resize', place);
        return function () {
            box.removeEventListener('click', place);
            box.removeEventListener('focus', place);
            window.removeEventListener('resize', place);
        };
    }

    // A pick writes the box and fires its native change once; the box's own
    // listener skips it, the calendar already shows that date.
    function announce(box) {
        box.pgDatePicking = true;
        try {
            fireChange(box);
        } finally {
            box.pgDatePicking = false;
        }
    }

    function drawDate(el) {
        el.pgDatePick = function () { announce(el); };
        makeDatepicker(el, el, el.pgDatePick);
    }

    function removeDate(el) {
        disposeDatepicker(el);
        el.pgDatePick = null;
    }

    // The posted field's classes a part takes over: sizes, validation,
    // margins.
    function partClasses(el, extra) {
        var own = String(el.className || '').split(/\s+/), out = ['form-control'].concat(extra);
        for (var i = 0; i < own.length; i++) {
            if (own[i] && own[i] !== 'form-control' && own[i] !== 'pg-field-native' && out.indexOf(own[i]) < 0) {
                out.push(own[i]);
            }
        }
        return out.join(' ');
    }

    function timeInput(el) {
        var t = document.createElement('input');
        t.type = 'time';
        t.className = partClasses(el, ['pg-time']);
        t.disabled = !!el.disabled;
        return t;
    }

    // The posted field stays the store and is hidden; the parts people use
    // come after it, named by the field's label.
    function hideNative(el, parts) {
        el.classList.add('pg-field-native');
        el.pgTabindex = el.getAttribute('tabindex');
        el.setAttribute('tabindex', '-1');
        var named = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
        if (named) {
            named.id = named.id || (el.id + '_label');
            parts.forEach(function (p) { p.setAttribute('aria-labelledby', named.id); });
        }
    }

    // Date and time: a date box with a Datepicker and an <input type="time">
    // in a group after the hidden field, which gets the two composed in the
    // site text ('26/10/2026 5:00 PM') on every change.
    function drawDateTime(el) {
        var group = document.createElement('div'), date = document.createElement('input'), time = timeInput(el);
        group.className = 'pg-datetime';
        date.type = 'text';
        date.className = partClasses(el, ['pg-datetime-date']);
        date.setAttribute('autocomplete', 'off');
        date.disabled = !!el.disabled;
        group.appendChild(date);
        group.appendChild(time);
        el.parentNode.insertBefore(group, el.nextSibling);
        hideNative(el, [date, time]);
        el.pgParts = { group: group, date: date, time: time };
        fillDateTime(el);
        var compose = function () {
            var day = String(date.value).trim(), iso = parseDate(day);
            if (day === '') {
                el.value = '';
            } else {
                // A box that is not a date posts as typed, for the server to
                // refuse as it always did.
                el.value = (iso ? formatDate(iso) : day) + ' ' + formatTime(time.value || '00:00');
            }
            fireChange(el);
        };
        date.pgDatePick = compose;
        makeDatepicker(el, date, compose);
        date.addEventListener('change', function () {
            if (!date.pgDatePicking) {
                compose();
            }
        });
        time.addEventListener('change', compose);
    }

    function fillDateTime(el) {
        var p = el.pgParts, m = /^(\S+)(?:\s+(.+))?$/.exec(String(el.value || '').trim());
        p.date.value = m ? m[1] : '';
        p.time.value = m && m[2] ? parseTime(m[2]) : '';
    }

    function showDateTime(el) {
        if (el.pgParts) {
            fillDateTime(el);
            showDate(el, el.pgParts.date);
        }
    }

    // Time only: an <input type="time"> after the hidden field, which keeps
    // the text the server reads ('5:00 PM').
    function drawTime(el) {
        var time = timeInput(el);
        el.parentNode.insertBefore(time, el.nextSibling);
        hideNative(el, [time]);
        el.pgParts = { group: time, time: time };
        showTime(el);
        time.addEventListener('change', function () {
            el.value = time.value ? formatTime(time.value) : '';
            fireChange(el);
        });
    }

    function showTime(el) {
        if (el.pgParts) {
            el.pgParts.time.value = parseTime(el.value);
        }
    }

    function removeParts(el) {
        var p = el.pgParts;
        if (!p) {
            return;
        }
        if (p.date) {
            disposeDatepicker(p.date);
        }
        if (p.group.parentNode) {
            p.group.parentNode.removeChild(p.group);
        }
        el.classList.remove('pg-field-native');
        if (el.pgTabindex === null || el.pgTabindex === undefined) {
            el.removeAttribute('tabindex');
        } else {
            el.setAttribute('tabindex', el.pgTabindex);
        }
        el.pgParts = null;
    }

    // The engine of a kind. A combobox that searches the server
    // (data-pg-source) is a select2 only on a page without Bootstrap 6 (a
    // site page on 5.3.8); the panel draws it as a Combobox.
    function engineName(kind, el) {
        if (kind === 'combobox' && el && el.hasAttribute && el.hasAttribute('data-pg-source')) {
            var v = window.pgUI ? window.pgUI.version() : -1;
            return (v === 6 || v === 0) ? 'v6' : 'v5';
        }
        return ENGINES.v6[kind] ? 'v6' : 'v5';
    }

    // The engine that drew the field, else the one that would draw it.
    function engine(kind, el) {
        return ENGINES[(el && el.pgFieldEngine) || engineName(kind, el)][kind];
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
        var e = engine(kind, el);
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
        el.pgFieldEngine = engineName(kind, el);
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
            engine(el.pgFieldKind, el).destroy(el);
            el.pgFieldKind = '';
            el.pgFieldEngine = '';
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
            engine(kind, el).redraw(el);
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
                engine('combobox', el).redraw(el);
            }
        },
        // After options were added or removed by a script.
        refresh: function (el) {
            el = element(el);
            if (el && el.pgFieldKind) {
                engine('combobox', el).redraw(el);
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
        // 'v6' or 'v5': the engine that draws a kind (a field's, if given).
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
