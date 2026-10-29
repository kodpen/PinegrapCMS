/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel field widgets behind one interface: pgChips, pgCombobox,
 * pgDatePicker and pgDateTimeField.
 *
 * The panel moves to Bootstrap 6 (docs/memory/plans/bootstrap-6-panel.md),
 * whose Chips and Combobox replace tagin and select2; dates and times run on
 * the one date-and-time control of pg_datetime.js, which replaced the jQuery
 * UI pickers and Bootstrap 6 Datepicker. Screens mark a field with a data-pg-* hook and read or write it
 * through these objects, so the swap changes the engine here, not the
 * screens. Every kind runs on Bootstrap 6 ('v6'); only a page without it that
 * has select2 (a site page) draws a combobox that searches the server as a
 * select2 ('v5'). Dates and times also run on a site page whose tags
 * pg_site_datetime_inject() printed. A page without either keeps plain
 * fields that still post.
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
 *   <input data-pg-date>                  the calendar of pg_datetime.js on the field, site date
 *                                         format, names in the panel language
 *   <input data-pg-date="plain">          the same with English names
 *   <input data-pg-datetime>              date and time: the field hidden, one box after it
 *                                         in the site's date order and clock; its calendar
 *                                         has the time row under the dates
 *   <input data-pg-time>                  time only: the same box with the time row alone
 *     data-pg-time="hh:mm"                a text time kept on the 24-hour clock ('17:05')
 *   <input type="date|datetime-local|time" data-pg-date|data-pg-datetime|data-pg-time>
 *                                         a native input stays the hidden store and keeps
 *                                         posting ISO ('2026-10-26', '2026-10-26T17:05',
 *                                         '17:05'); its min and max bound the calendar;
 *                                         without JavaScript it is the native input
 *   <div data-pg-date-selects>            a wrapper of month, day and year selects
 *                                         (start_month, start_day, start_year): the selects
 *                                         stay posted and are hidden, one box writes them,
 *                                         the calendar bounded by the year list
 *
 * The named input or the native <select> is always what the form posts, in
 * the text the server reads today. Values cross this interface as ISO text
 * ('2026-10-26', '17:00', '2026-10-26 17:00'), parsed and formatted here from
 * the site date format, never by Date() parsing.
 *
 *   pgFields.init(scope)                  every hooked field in scope, once; a field a
 *                                         script builds is drawn by passing it, or a
 *                                         node around it, after it is in the document
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
 * Dates never go through Date() parsing, which takes 5/3/2026 as May: the
 * calendar is given our own parse and writes our own format.
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
        time: 'data-pg-time',
        dateselects: 'data-pg-date-selects'
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

    // ------------------------------------------------------------ formats

    // Parse and format live in pg_datetime.js, which pg_fields_script()
    // prints before this file.
    var DT = window.pgDateTime;
    var dateOrder = DT.order, parseDate = DT.parseDate, formatDate = DT.formatDate,
        parseTime = DT.parseTime, formatTime = DT.formatTime,
        parseDateTime = DT.parseDateTime, formatDateTime = DT.formatDateTime;

    // ------------------------------------------------------------ engines

    // One engine per Bootstrap generation; each kind has init(el), which
    // draws the widget over the posted field, redraw(el), which shows a
    // value written into that field, and destroy(el), which removes the
    // widget and leaves the field. A kind the v6 engine has runs on it. A v6 kind
    // has later(): true while Bootstrap's module has not run yet, so the
    // field is drawn once pgUI.ready() fires; a date kind has wait(), which
    // gives how it waits for its library too.

    // Dates and times need Bootstrap 6 (the calendar's look and the panel's
    // overlay host) and the calendar library, which pg_datetime.js loads. A
    // site page whose tags pg_site_datetime_inject() printed (DT.site())
    // needs only the library: its stylesheet comes with the tags.
    function calendarReady() {
        return !!(DT.site() || (window.pgUI && window.pgUI.version() === 6)) && DT.ready();
    }

    // How a date field waits for them; null on a panel-less page without
    // Bootstrap 6 and without the site tags, where it stays a plain field.
    function calendarWait() {
        if (DT.site()) {
            return DT.whenReady;
        }
        var v = window.pgUI ? window.pgUI.version() : -1;
        if (v !== 6 && v !== 0) {
            return null;
        }
        return function (fn) {
            window.pgUI.ready(function () { DT.whenReady(fn); });
        };
    }

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
                ready: calendarReady,
                wait: calendarWait,
                init: drawDate,
                // A typed date box: its own change listener already moved the
                // calendar to the written date.
                redraw: function (el) {
                    if (el.pgParts) {
                        showParts(el);
                    }
                },
                destroy: removeDate
            },
            dateselects: {
                ready: calendarReady,
                wait: calendarWait,
                init: drawSelects,
                redraw: showSelects,
                destroy: removeSelects
            },
            datetime: {
                ready: calendarReady,
                wait: calendarWait,
                init: drawDateTime,
                redraw: showParts,
                destroy: removeParts
            },
            time: {
                ready: calendarReady,
                wait: calendarWait,
                init: drawTime,
                redraw: showParts,
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

    // Bootstrap 6.0.0-alpha.1 binds the Combobox's Menu to the combobox's
    // own toggle, which Data.set() refuses with a console error ("doesn't
    // allow more than one instance per element") while the combobox keeps
    // working with its own Menu. The one known complaint is dropped for the
    // call that builds the combobox; anything else console.error gets passes.
    var INSTANCE_NOISE = "doesn't allow more than one instance per element";

    function withoutInstanceNoise(fn) {
        var original = (window.console && window.console.error) || null;

        if (typeof original !== 'function') {
            return fn();
        }

        window.console.error = function () {
            if (arguments.length && (typeof arguments[0] === 'string') && (arguments[0].indexOf(INSTANCE_NOISE) !== -1)) {
                return undefined;
            }

            return original.apply(window.console, arguments);
        };

        try {
            return fn();
        } finally {
            window.console.error = original;
        }
    }

    // The keys the combobox handles on its own menu (arrows, Home, End) stop
    // at the menu: Bootstrap's document keydown handler for .menu would ask
    // Menu.getOrCreateInstance() on the toggle for each of them and, since
    // the combobox's Menu is not the bound instance there, build an unbound
    // Menu with the same console error on every key.
    var MENU_KEYS = { ArrowUp: 1, ArrowDown: 1, Home: 1, End: 1 };

    function keepMenuKeysInCombobox(combobox, toggle) {
        var menu = (combobox && combobox._menu) || toggle.nextElementSibling;

        if (!menu || !menu.classList || !menu.classList.contains('menu')) {
            return;
        }

        menu.addEventListener('keydown', function (event) {
            if (MENU_KEYS[event.key]) {
                event.stopPropagation();
            }
        });
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
        // An option of the listbox, so a screen reader says whether it is
        // picked (a plain button has no selected state).
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', 'false');
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
        value.textContent = text || comboboxPlaceholder(el);
        value.className = 'combobox-value' + (text ? '' : ' combobox-placeholder');
    }

    // What the toggle reads while nothing is chosen: data-placeholder, else
    // the empty option's own text ("Choose an account"), not a blank box.
    function comboboxPlaceholder(el) {
        if (el.getAttribute('data-placeholder')) {
            return el.getAttribute('data-placeholder');
        }
        for (var i = 0; i < el.options.length; i++) {
            if (el.options[i].value === '' && el.options[i].textContent.trim()) {
                return el.options[i].textContent.trim();
            }
        }
        return '';
    }

    // The picked state of every option follows its .selected class after a
    // pick: Bootstrap writes aria-selected on the item it toggles only.
    function syncComboboxSelected(list) {
        var items = list.querySelectorAll('.menu-item');
        for (var i = 0; i < items.length; i++) {
            items[i].setAttribute('aria-selected', items[i].classList.contains('selected') ? 'true' : 'false');
        }
    }

    // The options sit in a listbox (Bootstrap's own items are buttons with
    // no role); it lays them out as the menu does, the gap included.
    function comboboxList(className, role) {
        var list = document.createElement('div');
        list.className = className;
        list.setAttribute('role', role);
        list.style.display = 'flex';
        list.style.flexDirection = 'column';
        list.style.gap = 'var(--bs-menu-gap)';
        return list;
    }

    var comboboxCount = 0;

    // A toggle and a .menu after the hidden <select>, an item per option and
    // a header per option group; the chosen items are marked in the markup,
    // so drawing announces nothing. A choice writes the <select> and fires its
    // native change, which the ERP account fields re-rate taxes on.
    function drawCombobox(el) {
        var multiple = !!el.multiple, toggle = document.createElement('button'), menu = document.createElement('div');
        var base = (el.id || ('pg_combobox_' + (++comboboxCount))) + '_combobox', placeholder = comboboxPlaceholder(el);
        var classes = ['form-control', 'combobox-toggle', 'pg-combobox'], own = String(el.className || '').split(/\s+/);
        for (var i = 0; i < own.length; i++) {
            if (own[i] && !COMBOBOX_SKIP[own[i]]) {
                classes.push(own[i]);
            }
        }
        toggle.type = 'button';
        toggle.className = classes.join(' ');
        toggle.disabled = !!el.disabled;
        toggle.id = base;
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-controls', base + '_list');
        var shown = document.createElement('span');
        shown.className = 'combobox-value';
        shown.id = base + '_value';
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
            }
            query.setAttribute('aria-controls', base + '_list');
        }
        var said = null;
        if (search) {
            var box = document.createElement('div'), input = document.createElement('input'), none = document.createElement('div');
            box.className = 'combobox-search';
            input.type = 'text';
            input.className = 'form-control combobox-search-input';
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('placeholder', label('search', 'Search'));
            input.setAttribute('aria-label', label('search', 'Search'));
            input.setAttribute('aria-controls', base + '_list');
            box.appendChild(input);
            menu.appendChild(box);
            none.className = 'combobox-no-results d-none';
            none.textContent = label('no-results', 'No results');
            menu.appendChild(none);
            // Bootstrap only shows the line above; a search that matches
            // nothing is said here.
            said = document.createElement('div');
            said.className = 'visually-hidden';
            said.setAttribute('role', 'status');
            said.setAttribute('aria-live', 'polite');
            menu.appendChild(said);
        }
        var list = comboboxList('pg-combobox-list', 'listbox');
        list.id = base + '_list';
        if (multiple) {
            list.setAttribute('aria-multiselectable', 'true');
        }
        var children = el.children;
        for (var j = 0; j < children.length; j++) {
            var child = children[j];
            if (child.tagName === 'OPTGROUP') {
                var group = comboboxList('pg-combobox-group', 'group'), header = document.createElement('div');
                header.className = 'menu-header';
                header.id = base + '_group' + j;
                header.textContent = child.getAttribute('label') || '';
                group.setAttribute('aria-labelledby', header.id);
                group.appendChild(header);
                for (var k = 0; k < child.children.length; k++) {
                    group.appendChild(comboboxItem(child.children[k], multiple, placeholder));
                }
                list.appendChild(group);
            } else if (child.tagName === 'OPTION') {
                list.appendChild(comboboxItem(child, multiple, placeholder));
            }
        }
        menu.appendChild(list);
        el.classList.add('pg-combobox-native');
        // The posted select is out of the Tab order: people reach the toggle.
        el.pgComboboxTabindex = el.getAttribute('tabindex');
        el.setAttribute('tabindex', '-1');
        el.parentNode.insertBefore(menu, el.nextSibling);
        el.parentNode.insertBefore(toggle, menu);
        el.pgComboboxToggle = toggle;
        el.pgComboboxMenu = menu;
        el.pgComboboxList = list;
        // The toggle, which is what people reach, is named by the field's
        // label, else by the select's own aria-labelledby or aria-label,
        // followed by the chosen value it shows; the listbox by the same name.
        var named = el.id ? document.querySelector('label[for="' + el.id + '"]') : null, names = '';
        if (named) {
            named.id = named.id || (el.id + '_label');
            names = named.id;
        } else if (el.getAttribute('aria-labelledby')) {
            names = el.getAttribute('aria-labelledby');
        } else if (el.getAttribute('aria-label')) {
            toggle.setAttribute('aria-label', el.getAttribute('aria-label'));
            names = toggle.id;
        }
        if (names) {
            toggle.setAttribute('aria-labelledby', names + ' ' + shown.id);
            list.setAttribute('aria-labelledby', names);
        }
        if (el.getAttribute('aria-describedby')) {
            toggle.setAttribute('aria-describedby', el.getAttribute('aria-describedby'));
        }
        // Bootstrap 6's .collapse clips overflow-y, so a menu placed inside a
        // switch panel would be cut off at the panel's edge: place it fixed.
        if (el.closest && el.closest('.collapse')) {
            toggle.setAttribute('data-bs-strategy', 'fixed');
        }
        var combobox = withoutInstanceNoise(function () {
            return window.pgUI.combobox.get(toggle, {
                multiple: multiple,
                search: search,
                placeholder: placeholder
            });
        });
        keepMenuKeysInCombobox(combobox, toggle);
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
                syncComboboxSelected(list);
                showComboboxText(el);
                fireChange(el);
            }),
            // The menu is at least as wide as its field; Bootstrap empties
            // the search box on opening, so nothing is left to say.
            window.pgUI.combobox.on(toggle, 'show', function () {
                menu.style.minWidth = toggle.offsetWidth + 'px';
                if (said) {
                    said.textContent = '';
                }
            })
        ];
        // Bootstrap filters the items on input before this listener runs.
        if (said) {
            var filtered = function () {
                said.textContent = none.classList.contains('d-none') ? '' : none.textContent;
            };
            input.addEventListener('input', filtered);
            offs.push(function () { input.removeEventListener('input', filtered); });
        }
        // A click on the label would focus the hidden select (label[for]
        // stays on it: the required marker reads it); it goes to the toggle.
        if (named) {
            var toToggle = function (event) {
                event.preventDefault();
                if (!toggle.disabled) {
                    toggle.focus();
                }
            };
            named.addEventListener('click', toToggle);
            offs.push(function () { named.removeEventListener('click', toToggle); });
        }
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
            offs = offs.concat(serverSearch(el, list, query, status, toggle));
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
    function serverSearch(el, list, query, status, toggle) {
        var source = el.getAttribute('data-pg-source'), timer = null, asked = 0, aborter = null;
        var min = parseInt(el.getAttribute('data-pg-min-length'), 10) || 0, free = el.hasAttribute('data-pg-free-text');
        var placeholder = comboboxPlaceholder(el);

        var say = function (text) {
            status.textContent = text || '';
            status.className = 'pg-combobox-status' + (text ? '' : ' d-none');
        };
        var items = function (found) {
            var old = list.querySelectorAll('.menu-item');
            for (var i = 0; i < old.length; i++) {
                list.removeChild(old[i]);
            }
            found.forEach(function (item) { list.appendChild(item); });
        };
        // The select's own options: the empty item and the stored choice.
        var stored = function () {
            var found = [];
            for (var i = 0; i < el.options.length; i++) {
                found.push(comboboxItem(el.options[i], !!el.multiple, placeholder));
            }
            items(found);
            say(label('type', 'Type to search...'));
        };
        var answer = function (text, data) {
            var results = (data && data.results) ? data.results : [], found = [], seen = {};
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
                found.push(comboboxItem({ value: id, textContent: String(r.text === undefined ? id : r.text), selected: el.value === id }, false, placeholder));
            });
            if (free && !seen[text]) {
                found.push(comboboxItem({ value: text, textContent: text, selected: el.value === text }, false, placeholder));
            }
            items(found);
            say(found.length ? '' : label('no-results', 'No results'));
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
            var first = list.querySelectorAll('.menu-item')[0];
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
            if (el.pgComboboxTabindex === null || el.pgComboboxTabindex === undefined) {
                el.removeAttribute('tabindex');
            } else {
                el.setAttribute('tabindex', el.pgComboboxTabindex);
            }
        }
        el.classList.remove('pg-combobox-native');
        el.pgComboboxToggle = null;
        el.pgComboboxMenu = null;
        el.pgComboboxList = null;
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

    // Each remove button says which chip it removes ("Remove: red"), or a
    // screen reader's browse mode reads "red Remove, blue Remove".
    function labelDismissButtons(box) {
        var buttons = box.querySelectorAll('.chip-dismiss'), text = label('remove', 'Remove');
        for (var i = 0; i < buttons.length; i++) {
            var value = buttons[i].parentNode ? buttons[i].parentNode.getAttribute('data-bs-chip-value') : '';
            buttons[i].setAttribute('aria-label', value ? text + ': ' + value : text);
        }
    }

    // Chips gives every chip a Tab stop but deletes only the selected ones:
    // a chip reached from the keyboard becomes the selection, so Delete
    // removes the chip that has the focus. A press is left to Chips' own
    // click, which selects with Ctrl and Shift as well.
    function selectFocusedChip(box, chips) {
        var pressed = false;
        var press = function () { pressed = true; };
        var release = function () { pressed = false; };
        var focused = function (event) {
            var chip = event.target && event.target.closest ? event.target.closest('.chip') : null, byPress = pressed;
            pressed = false;
            if (byPress || !chip || chip.classList.contains('active')) {
                return;
            }
            chips.selectChip(chip);
        };
        box.addEventListener('mousedown', press);
        box.addEventListener('click', release);
        box.addEventListener('focusin', focused);
        return function () {
            box.removeEventListener('mousedown', press);
            box.removeEventListener('click', release);
            box.removeEventListener('focusin', focused);
        };
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
        var offFocus = selectFocusedChip(box, chips);
        var offChange = window.pgUI.chips.on(box, 'change', function () {
            labelDismissButtons(box);
            el.value = chips.getValues().join(sep);
            fireChange(el);
        });
        el.pgChipsOff = function () {
            offFocus();
            offChange();
        };
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

    // In a drawer the calendar is drawn open under the field: a popup there
    // would hang off the drawer's scrolling body.
    function inlineIn(el) {
        return !!(el.closest && el.closest('.drawer'));
    }

    // The calendar of pg_datetime.js over the typed input `box`: the posted
    // field itself for a date, the one box after the hidden field for a
    // date and time or a time. read() gives what the box shows as
    // {iso, time}, our own parse of its text; onPick(iso, time) writes a
    // pick. In a drawer the calendar is drawn open after the box.
    function makeCalendar(el, box, mode, read, onPick, bounds) {
        // "plain" keeps the screens that never passed the translated names:
        // English, Sunday first.
        var plain = el.getAttribute(HOOKS.date) === 'plain', lang = plain ? 'en' : locale(), now = read();
        var control = DT.attach(box, {
            mode: mode,
            iso: now.iso,
            time: now.time,
            locale: lang,
            firstWeekday: plain ? 0 : firstWeekday(lang),
            dateMin: bounds ? bounds.min : '',
            dateMax: bounds ? bounds.max : '',
            inline: inlineIn(el),
            host: function (b) {
                return window.pgUI && window.pgUI.overlayHost ? window.pgUI.overlayHost(b) : null;
            },
            onPick: onPick
        });
        // Typed text moves the calendar to it when the box is left; text
        // that is not a date or a time is kept for the server to refuse.
        var typed = function () {
            if (!box.pgDatePicking) {
                var shown = read();
                control.set(shown.iso, shown.time);
            }
        };
        box.addEventListener('change', typed);
        box.pgCalendar = {
            control: control,
            off: function () { box.removeEventListener('change', typed); }
        };
    }

    function disposeCalendar(box) {
        var c = box && box.pgCalendar;
        if (!c) {
            return;
        }
        c.off();
        c.control.destroy();
        box.pgCalendar = null;
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

    // A date in the site's text is typed in the posted field itself; a
    // native <input type="date"> is the hidden store of a box (drawParts).
    function drawDate(el) {
        if (storeOf(el) === 'iso') {
            drawParts(el, 'date');
            return;
        }
        makeCalendar(el, el, 'date', function () {
            return { iso: parseDate(el.value), time: '' };
        }, function (iso) {
            el.value = formatDate(iso);
            announce(el);
        });
    }

    function removeDate(el) {
        if (el.pgParts) {
            removeParts(el);
            return;
        }
        disposeCalendar(el);
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

    // ------------------------------------------------------------ stores

    // How a field keeps its value, which never changes: 'site', the text the
    // server reads today ('26/10/2026', '26/10/2026 5:00 PM', '5:00 PM');
    // 'iso', a native <input type="date">, "datetime-local" or "time"
    // ('2026-10-26', '2026-10-26T17:00', '17:00'), which also stays the field
    // a page without JavaScript shows; 'hhmm', a text time on the 24-hour
    // clock (data-pg-time="hh:mm", '17:00'). ISO never reaches a field that
    // keeps the site's text: '2026-10-26 17:00' is stored as
    // '0026-10-2026 17:00' there.
    function storeOf(el) {
        var type = String(el.getAttribute('type') || '').toLowerCase();
        if (el.tagName === 'INPUT' && (type === 'date' || type === 'datetime-local' || type === 'time')) {
            return 'iso';
        }
        return el.getAttribute(HOOKS.time) === 'hh:mm' ? 'hhmm' : 'site';
    }

    // A field's value as {iso, time} ('2026-10-26', '17:00'), read in its
    // own store; empty parts when it holds nothing we can read.
    function readStore(el, mode) {
        var v = String(el.value || '').trim(), store = storeOf(el), m;
        if (store === 'iso') {
            m = /^(\d{4}-\d{2}-\d{2})?(?:[T ]?(\d{2}:\d{2}))?/.exec(v) || [];
            return {
                iso: (mode !== 'time' && m[1] && formatDate(m[1])) ? m[1] : '',
                time: (mode !== 'date' && m[2] && parseTime(m[2])) ? m[2] : (mode === 'datetime' && m[1] ? '00:00' : '')
            };
        }
        return readParts(v, mode);
    }

    // {iso, time} in a field's own store; '' without a date (or a time).
    function storeText(store, mode, iso, time) {
        if (mode === 'time') {
            return !time ? '' : (store === 'site' ? formatTime(time) : time);
        }
        if (!iso) {
            return '';
        }
        if (mode === 'date') {
            return store === 'iso' ? iso : formatDate(iso);
        }
        return store === 'iso' ? (iso + 'T' + (time || '00:00')) : (formatDate(iso) + ' ' + formatTime(time || '00:00'));
    }

    // What a typed box says ('26/10/2026', '26/10/2026 17:05', '26/10/2026
    // 5:05 PM', '17:05', '5:05 PM'), as {iso, time}; empty parts when it says
    // nothing we can read.
    function readParts(text, mode) {
        if (mode === 'time') {
            return { iso: '', time: parseTime(text) };
        }
        if (mode === 'date') {
            return { iso: parseDate(text), time: '' };
        }
        var v = parseDateTime(text);
        return v ? { iso: v.slice(0, 10), time: v.slice(11) } : { iso: '', time: '' };
    }

    // The box's text: the site's date order and the site's clock.
    function shownParts(iso, time, mode) {
        if (mode === 'time') {
            return DT.clockTime(time);
        }
        return mode === 'date' ? formatDate(iso) : (formatDate(iso) + ' ' + DT.clockTime(time));
    }

    // The hidden field gets the box in its own store, whatever the clock.
    // An empty box empties it, a date without a time is midnight. Text that
    // says no date or time posts as typed, for the server to refuse as it
    // always did; a native input cannot hold it and is left empty.
    function writeParts(el, box, mode) {
        var text = String(box.value).trim(), r = readParts(text, mode), store = storeOf(el);
        var ok = mode === 'time' ? r.time : r.iso;
        if (text === '') {
            el.value = '';
        } else if (ok) {
            el.value = storeText(store, mode, r.iso, r.time);
        } else {
            el.value = store === 'iso' ? '' : text;
        }
        el.pgPartsWriting = true;
        try {
            fireChange(el);
        } finally {
            el.pgPartsWriting = false;
        }
    }

    // The box shows the hidden field: our own reading of it, or its text
    // when it is none we can read.
    function fillParts(el) {
        var p = el.pgParts, r = readStore(el, p.mode), ok = p.mode === 'time' ? r.time : r.iso;
        p.box.value = ok ? shownParts(r.iso, r.time, p.mode) : (storeOf(el) === 'iso' ? '' : String(el.value || ''));
    }

    // A native input's min and max as the calendar's first and last day.
    function boundsOf(el) {
        var day = function (v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; };
        return storeOf(el) === 'iso' ? { min: day(el.getAttribute('min')), max: day(el.getAttribute('max')) } : null;
    }

    // Date and time (mode 'datetime'), time only ('time') or a native date
    // input ('date'): one box after the hidden field, typed in, whose
    // calendar holds the time row under the dates, the time row alone, or
    // the dates. A time moved before any date is picked waits for the date.
    function drawParts(el, mode) {
        var box = document.createElement('input');
        box.type = 'text';
        box.className = partClasses(el, [mode === 'time' ? 'pg-time-box' : (mode === 'date' ? 'pg-date-box' : 'pg-datetime-box')]);
        box.setAttribute('autocomplete', 'off');
        box.disabled = !!el.disabled;
        // A field named only by its own attributes (a toolbar date) passes
        // them to the box people use.
        ['aria-label', 'placeholder', 'title'].forEach(function (name) {
            if (el.hasAttribute(name)) {
                box.setAttribute(name, el.getAttribute(name));
            }
        });
        el.parentNode.insertBefore(box, el.nextSibling);
        hideNative(el, [box]);
        // A script that writes the store and announces it with a change
        // gets it shown; the box's own writes are skipped.
        var stored = function () {
            if (!el.pgPartsWriting) {
                showParts(el);
            }
        };
        el.addEventListener('change', stored);
        el.pgParts = { box: box, mode: mode, off: function () { el.removeEventListener('change', stored); } };
        fillParts(el);
        makeCalendar(el, box, mode, function () {
            return readParts(box.value, mode);
        }, function (iso, time) {
            if (mode !== 'time' && !iso) {
                return;
            }
            box.value = shownParts(iso, time || '00:00', mode);
            writeParts(el, box, mode);
        }, boundsOf(el));
        box.addEventListener('change', function () { writeParts(el, box, mode); });
    }

    function drawDateTime(el) {
        drawParts(el, 'datetime');
    }

    function drawTime(el) {
        drawParts(el, 'time');
    }

    // A value written into the hidden field: the box and the calendar show it,
    // and the box follows the field's disabled state.
    function showParts(el) {
        var p = el.pgParts;
        if (p) {
            p.box.disabled = !!el.disabled;
            fillParts(el);
            var r = readParts(p.box.value, p.mode);
            if (p.box.pgCalendar) {
                p.box.pgCalendar.control.set(r.iso, r.time);
            }
        }
    }

    function removeParts(el) {
        var p = el.pgParts;
        if (!p) {
            return;
        }
        p.off();
        disposeCalendar(p.box);
        if (p.box.parentNode) {
            p.box.parentNode.removeChild(p.box);
        }
        el.classList.remove('pg-field-native');
        if (el.pgTabindex === null || el.pgTabindex === undefined) {
            el.removeAttribute('tabindex');
        } else {
            el.setAttribute('tabindex', el.pgTabindex);
        }
        el.pgParts = null;
    }

    // ------------------------------------------------------------ select triplets

    function pad2(n) {
        return ('0' + n).slice(-2);
    }

    // The month, day and year selects inside a data-pg-date-selects wrapper,
    // found by their names as select_month(), select_day() and
    // select_year() are printed (start_month, stop_day...) or by
    // data-pg-part="month|day|year"; also its first <label>, or the
    // <legend> of a fieldset wrapper (a list screen's range end).
    function triplet(el) {
        var found = { label: null };
        (function walk(n) {
            for (var i = 0; i < (n.children || []).length; i++) {
                var c = n.children[i], part;
                if (c.tagName === 'SELECT') {
                    part = c.getAttribute('data-pg-part') || ((/(?:^|_)(month|day|year)$/.exec(c.getAttribute('name') || '') || [])[1]);
                    if (part && !found[part]) {
                        found[part] = c;
                    }
                } else if ((c.tagName === 'LABEL' || c.tagName === 'LEGEND') && !found.label) {
                    found.label = c;
                }
                walk(c);
            }
        })(el);
        return (found.month && found.day && found.year) ? found : null;
    }

    function optionNumber(select, n) {
        for (var i = 0; i < select.options.length; i++) {
            if (parseInt(select.options[i].value, 10) === n) {
                return select.options[i];
            }
        }
        return null;
    }

    // The date the three selects hold, '' when they hold no real date.
    function readSelects(t) {
        var iso = parseInt(t.year.value, 10) + '-' + pad2(parseInt(t.month.value, 10)) + '-' + pad2(parseInt(t.day.value, 10));
        return /^\d{4}-\d{2}-\d{2}$/.test(iso) && formatDate(iso) ? iso : '';
    }

    // Writes a date into the selects, which stay what the form posts, and
    // fires a change on each that moved; false when a select has no option
    // for it (a year outside its list), and nothing is written then.
    function writeSelects(t, iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
        var parts = m ? [[t.year, +m[1]], [t.month, +m[2]], [t.day, +m[3]]] : [];
        var options = parts.map(function (p) { return optionNumber(p[0], p[1]); });
        if (!m || options.indexOf(null) !== -1) {
            return false;
        }
        parts.forEach(function (p, i) {
            if (!options[i].selected) {
                p[0].value = options[i].value;
                fireChange(p[0]);
            }
        });
        return true;
    }

    // The calendar's first and last day: from 1 January of the year list's
    // first year to 31 December of its last.
    function yearBounds(t) {
        var years = [];
        for (var i = 0; i < t.year.options.length; i++) {
            var y = parseInt(t.year.options[i].value, 10);
            if (y) {
                years.push(y);
            }
        }
        if (!years.length) {
            return null;
        }
        return { min: Math.min.apply(null, years) + '-01-01', max: Math.max.apply(null, years) + '-12-31' };
    }

    // A date range's month, day and year selects (data-pg-date-selects on
    // the wrapper around them): the selects stay what the form posts
    // (start_month, start_day, start_year) and are hidden; one typed box
    // before them shows the date in the site's order and opens the
    // calendar, which writes the three selects. A typed date the selects
    // cannot hold is taken back.
    function drawSelects(el) {
        var t = triplet(el);
        // A wrapper whose selects a script has not put in yet stays undrawn:
        // pgFields.init() draws it once they are there.
        if (!t) {
            el.pgFieldKind = '';
            el.pgFieldEngine = '';
            return;
        }
        var box = document.createElement('input');
        box.type = 'text';
        box.className = partClasses(t.month, ['pg-date-box']);
        box.setAttribute('autocomplete', 'off');
        box.disabled = !!t.month.disabled;
        t.month.parentNode.insertBefore(box, t.month);
        [t.month, t.day, t.year].forEach(function (select) {
            select.classList.add('pg-field-native');
            select.pgTabindex = select.getAttribute('tabindex');
            select.setAttribute('tabindex', '-1');
        });
        if (t.label) {
            t.label.id = t.label.id || ((t.month.getAttribute('name') || 'pg_date') + '_label');
            box.setAttribute('aria-labelledby', t.label.id);
        }
        box.value = formatDate(readSelects(t));
        el.pgSelects = { box: box, t: t };
        makeCalendar(el, box, 'date', function () {
            return { iso: parseDate(box.value), time: '' };
        }, function (iso) {
            if (writeSelects(t, iso)) {
                box.value = formatDate(iso);
            }
        }, yearBounds(t));
        box.addEventListener('change', function () {
            var iso = parseDate(box.value);
            if (!iso || !writeSelects(t, iso)) {
                showSelects(el);
            }
        });
    }

    // The box and the calendar show what the selects hold.
    function showSelects(el) {
        var s = el.pgSelects;
        if (s) {
            var iso = readSelects(s.t);
            s.box.value = formatDate(iso);
            if (s.box.pgCalendar) {
                s.box.pgCalendar.control.set(iso, '');
            }
        }
    }

    function removeSelects(el) {
        var s = el.pgSelects;
        if (!s) {
            return;
        }
        disposeCalendar(s.box);
        if (s.box.parentNode) {
            s.box.parentNode.removeChild(s.box);
        }
        [s.t.month, s.t.day, s.t.year].forEach(function (select) {
            select.classList.remove('pg-field-native');
            if (select.pgTabindex === null || select.pgTabindex === undefined) {
                select.removeAttribute('tabindex');
            } else {
                select.setAttribute('tabindex', select.pgTabindex);
            }
        });
        el.pgSelects = null;
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
            var wait = e.wait ? e.wait() : ((e.later && e.later()) ? function (fn) { window.pgUI.ready(fn); } : null);
            if (wait && !el.pgFieldWaiting) {
                el.pgFieldWaiting = true;
                wait(function () {
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

    // A bind marker (pg_site_datetime_bind(): a site screen whose inputs come
    // from a stored layout) names inputs by id per kind: each text input
    // found gets the hook of its kind, once, and is drawn with the rest.
    var BIND_KINDS = ['date', 'datetime', 'time'];

    function byId(id) {
        return document.getElementById ? document.getElementById(id) : document.querySelector('#' + id);
    }

    function bindIds(root) {
        var marks = Array.prototype.slice.call(root.querySelectorAll('[data-pg-date-bind]'));
        if (root !== document && root.matches && root.matches('[data-pg-date-bind]')) {
            marks.push(root);
        }
        marks.forEach(function (mark) {
            var ids = null;
            if (mark.pgBound) {
                return;
            }
            mark.pgBound = true;
            try {
                ids = JSON.parse(mark.getAttribute('data-pg-date-bind') || '{}');
            } catch (e) {
                ids = null;
            }
            BIND_KINDS.forEach(function (kind) {
                ((ids && ids[kind]) || []).forEach(function (id) {
                    var el = byId(String(id));
                    if (el && el.tagName === 'INPUT' && !kindOf(el)) {
                        el.setAttribute(HOOKS[kind], '');
                    }
                });
            });
        });
    }

    function init(scope) {
        var root = element(scope) || document;
        bindIds(root);
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

    // Puts the focus where people type: the box after a hidden store or a
    // select triplet (its calendar opens on focus), else the field itself.
    function focus(el) {
        el = element(el);
        var target = el && el.pgParts ? el.pgParts.box : (el && el.pgSelects ? el.pgSelects.box : el);
        if (target && target.focus) {
            target.focus();
        }
    }

    // Shows what a script wrote into a drawn date field (its value, its
    // disabled state) without announcing it.
    function refresh(el) {
        el = element(el);
        if (el && el.pgFieldKind) {
            engine(el.pgFieldKind, el).redraw(el);
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

    // A date field (data-pg-date) or a wrapper of month, day and year
    // selects (data-pg-date-selects); get() and set() exchange ISO dates
    // whatever the field keeps.
    var pgDatePicker = {
        init: function (el) {
            el = element(el);
            return initField(el, el && el.hasAttribute && el.hasAttribute(HOOKS.dateselects) ? 'dateselects' : 'date');
        },
        get: function (el) {
            el = element(el);
            if (!el) {
                return '';
            }
            if (el.hasAttribute(HOOKS.dateselects)) {
                var t = triplet(el);
                return t ? readSelects(t) : '';
            }
            return readStore(el, 'date').iso;
        },
        set: function (el, iso) {
            el = element(el);
            if (!el) {
                return;
            }
            if (el.hasAttribute(HOOKS.dateselects)) {
                var t = triplet(el);
                if (t && writeSelects(t, iso) && el.pgFieldKind) {
                    showSelects(el);
                }
                return;
            }
            write(el, 'date', storeText(storeOf(el), 'date', /^\d{4}-\d{2}-\d{2}$/.test(String(iso || '')) && formatDate(iso) ? iso : '', ''));
        },
        // After a script wrote the field's value or disabled state itself:
        // the widget shows them, no change is fired.
        refresh: refresh,
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
            if (el.hasAttribute(HOOKS.time)) {
                return readStore(el, 'time').time;
            }
            var r = readStore(el, 'datetime');
            return r.iso ? (r.iso + ' ' + r.time) : '';
        },
        set: function (el, iso) {
            el = element(el);
            if (!el) {
                return;
            }
            var store = storeOf(el);
            if (el.hasAttribute(HOOKS.time)) {
                var t = parseTime(iso);
                write(el, 'time', storeText(store, 'time', '', /^\d{1,2}:\d{2}$/.test(String(iso || '')) ? t : ''));
                return;
            }
            var m = /^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})/.exec(String(iso || ''));
            var day = m && formatDate(m[1]) ? m[1] : '', time = m ? parseTime(m[2]) : '';
            write(el, 'datetime', (day && time) ? storeText(store, 'datetime', day, time) : '');
        },
        refresh: refresh,
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
        focus: focus,
        refresh: refresh,
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
