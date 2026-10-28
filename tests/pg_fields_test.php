<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for assets/js/pg_fields.js, the panel's field widgets behind one
 * interface (pgChips, pgCombobox, pgDatePicker, pgDateTimeField), run under
 * Node with a small fake document and jQuery stand-in: our own date and time
 * parse and format, the posted field as the store, the native change event,
 * the hooks drawn once by the loaded engine, chips on Bootstrap 6 Chips. Also the liveform side
 * that prints the hooks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

// Runs the scenarios below with Node and returns their JSON; null when there
// is no Node, array('error' => output) when the script fails or prints
// something that is not JSON.
function pg_fields_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	if (!function_exists('exec')) {
		return $result = null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgfields');

	if ($file === false) {
		return $result = null;
	}

	$script = 'var PG_FIELDS_SOURCE = require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_fields.js') . ', "utf8");' . "\n"
		. pg_fields_test_harness();
	file_put_contents($file, $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	if ($code !== 0 || !is_array($json)) {
		return $result = array('error' => $text);
	}

	return $result = $json;
}

// The Node side: a fake document whose elements carry attributes, values,
// options and listeners; a jQuery stand-in whose plugins record their calls.
function pg_fields_test_harness()
{
	return <<<'JS'
function FakeElement(doc, tag, attrs) {
    this.ownerDocument = doc;
    this.tagName = tag.toUpperCase();
    this.attributes = attrs || {};
    this.listeners = {};
    this.value = this.attributes.value || '';
    this.options = [];
    this.multiple = 'multiple' in this.attributes;
    this.className = this.attributes['class'] || '';
    this.children = [];
    this.parentNode = null;
    this.id = this.attributes.id || '';
    this.textContent = this.attributes.text || '';
    this.selected = 'selected' in this.attributes;
    this.disabled = 'disabled' in this.attributes;
    var self = this;
    this.classList = {
        add: function (c) { if (!this.contains(c)) { self.className = (self.className + ' ' + c).trim(); } },
        remove: function (c) { self.className = self.className.split(/\s+/).filter(function (x) { return x && x !== c; }).join(' '); },
        contains: function (c) { return (' ' + self.className + ' ').indexOf(' ' + c + ' ') >= 0; }
    };
    // A select's value follows its options, as in a browser.
    if (this.tagName === 'SELECT') {
        Object.defineProperty(this, 'value', {
            get: function () { var o = self.options.filter(function (x) { return x.selected; })[0]; return o ? o.value : ''; },
            set: function (v) { self.options.forEach(function (x) { x.selected = (x.value === String(v)); }); }
        });
    }
    doc.all.push(this);
}
FakeElement.prototype.insertAdjacentHTML = function () {};
FakeElement.prototype.click = function () { this.ownerDocument.clicked.push(this.getAttribute('data-bs-value')); };
FakeElement.prototype.focus = function () { this.ownerDocument.activeElement = this; };
FakeElement.prototype.appendChild = function (c) {
    c.parentNode = this;
    this.children.push(c);
    var select = this.tagName === 'SELECT' ? this : (this.tagName === 'OPTGROUP' && this.parentNode && this.parentNode.tagName === 'SELECT' ? this.parentNode : null);
    if (select && c.tagName === 'OPTION') { select.options.push(c); }
    return c;
};
FakeElement.prototype.insertBefore = function (c, ref) {
    var i = ref ? this.children.indexOf(ref) : -1;
    c.parentNode = this;
    if (i < 0) { this.children.push(c); } else { this.children.splice(i, 0, c); }
    return c;
};
FakeElement.prototype.removeChild = function (c) { this.children = this.children.filter(function (x) { return x !== c; }); this.options = this.options.filter(function (x) { return x !== c; }); c.parentNode = null; return c; };
Object.defineProperty(FakeElement.prototype, 'childNodes', { get: function () { return this.children; } });
// Only '.class' up the parents, as the drawer test needs.
FakeElement.prototype.closest = function (sel) {
    var cls = sel.replace(/^\./, ''), n = this;
    while (n) { if ((' ' + (n.className || '') + ' ').indexOf(' ' + cls + ' ') >= 0) { return n; } n = n.parentNode; }
    return null;
};
Object.defineProperty(FakeElement.prototype, 'nextSibling', { get: function () {
    var p = this.parentNode; return p ? (p.children[p.children.indexOf(this) + 1] || null) : null;
} });
// Descendants carrying a class: '.chip-dismiss'.
FakeElement.prototype.querySelectorAll = function (sel) {
    var cls = sel.replace(/^\./, ''), out = [];
    (function walk(n) { (n.children || []).forEach(function (c) { if ((' ' + (c.className || '') + ' ').indexOf(' ' + cls + ' ') >= 0) { out.push(c); } walk(c); }); })(this);
    return out;
};
FakeElement.prototype.hasAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k); };
FakeElement.prototype.getAttribute = function (k) { return this.hasAttribute(k) ? this.attributes[k] : null; };
FakeElement.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
FakeElement.prototype.removeAttribute = function (k) { delete this.attributes[k]; };
FakeElement.prototype.addEventListener = function (t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); };
FakeElement.prototype.removeEventListener = function (t, f) { this.listeners[t] = (this.listeners[t] || []).filter(function (x) { return x !== f; }); };
FakeElement.prototype.dispatchEvent = function (e) { var self = this; (this.listeners[e.type] || []).slice().forEach(function (f) { f.call(self, e); }); return true; };
FakeElement.prototype.matches = function (sel) {
    var self = this;
    return sel.split(',').some(function (one) { var m = /^\[([\w-]+)\]$/.exec(one.trim()); return !!m && self.hasAttribute(m[1]); });
};

function makeDocument() {
    var doc = { all: [], readyState: 'complete', clicked: [], activeElement: null };
    doc.create = function (tag, attrs) { return new FakeElement(doc, tag, attrs); };
    doc.querySelectorAll = function (sel) { return doc.all.filter(function (el) { return el.matches(sel); }); };
    doc.querySelector = function (sel) {
        var lab = /^label\[for="([^"]+)"\]$/.exec(sel);
        if (lab) { return doc.all.filter(function (el) { return el.tagName === 'LABEL' && el.getAttribute('for') === lab[1]; })[0] || null; }
        var word = /^script\[([\w-]+)\]$/.exec(sel);
        if (word) { return doc.all.filter(function (el) { return el.tagName === 'SCRIPT' && el.hasAttribute(word[1]); })[0] || null; }
        return doc.all.filter(function (el) { return '#' + el.attributes.id === sel; })[0] || null;
    };
    doc.createElement = function (tag) { return new FakeElement(doc, tag, {}); };
    doc.createTextNode = function (t) { return { nodeType: 3, textContent: t }; };
    doc.addEventListener = function () {};
    return doc;
}

// jQuery stand-in: $(el).plugin(opts) records [plugin, id, opts]; .on/.off
// keep handlers on the element so a native dispatch reaches them, as with
// jQuery; .trigger('x.ns') records the call.
function makeJQuery(calls) {
    function wrap(el) {
        var api = { 0: el, jquery: true };
        ['select2', 'datepicker', 'datetimepicker', 'timepicker'].forEach(function (p) {
            api[p] = function (opts) { calls.push([p, el.attributes.id, opts]); return api; };
        });
        api.data = function (k) { return k === 'allowClear' ? el.getAttribute('data-allow-clear') === 'true' : undefined; };
        api.on = function (t, f) { el.addEventListener(t, f); return api; };
        api.off = function (t, f) { el.removeEventListener(t, f); return api; };
        api.trigger = function (t) { calls.push(['trigger', el.attributes.id, t]); return api; };
        return api;
    }
    var $ = function (el) { return wrap(el); };
    $.fn = { select2: 1, datepicker: 1, datetimepicker: 1, timepicker: 1 };
    return $;
}

// pgUI stand-in for Bootstrap 6: chips.get() makes an instance over the box
// that reads its chips like Bootstrap does and adds a dismiss button to each;
// add() and remove() fire the box's change listeners. Version 0 until
// release() when opts.late.
function makePgUI(calls, late) {
    var version = late ? 0 : 6, waiting = [];
    function chipsOf(box) { return box.children.filter(function (c) { return c.className === 'chip'; }); }
    function dismiss(chip) { var b = new FakeElement(chip.parentNode.ownerDocument, 'button', {}); b.className = 'chip-dismiss'; b.setAttribute('aria-label', 'Remove'); chip.appendChild(b); }
    var ui = {
        version: function () { return version; },
        ready: function (fn) { if (version) { fn(); } else { waiting.push(fn); } },
        release: function () { version = 6; waiting.splice(0).forEach(function (fn) { fn(); }); },
        chips: {
            get: function (box, opts) {
                calls.push(['chips', box.className, opts]);
                chipsOf(box).forEach(dismiss);
                var inst = {
                    getValues: function () { return chipsOf(box).map(function (c) { return c.getAttribute('data-bs-chip-value'); }); },
                    add: function (v) {
                        var c = new FakeElement(box.ownerDocument, 'span', {}); c.className = 'chip'; c.setAttribute('data-bs-chip-value', v);
                        box.insertBefore(c, box.children[box.children.length - 1]); dismiss(c);
                        (box.listeners['change.bs.chips'] || []).forEach(function (f) { f({}); });
                    },
                    remove: function (v) {
                        chipsOf(box).filter(function (c) { return c.getAttribute('data-bs-chip-value') === v; }).forEach(function (c) { box.removeChild(c); });
                        (box.listeners['change.bs.chips'] || []).forEach(function (f) { f({}); });
                    }
                };
                box.instance = inst;
                return inst;
            },
            on: function (box, name, fn) { box.addEventListener(name + '.bs.chips', fn); return function () { box.removeEventListener(name + '.bs.chips', fn); }; },
            dispose: function (box) { calls.push(['chips-dispose', box.className]); }
        },
        // Combobox over a toggle whose next sibling is the menu: pick(value)
        // marks the item as Bootstrap does (one item, or toggled when
        // multiple) and fires change.bs.combobox.
        combobox: {
            get: function (toggle, opts) {
                calls.push(['combobox', toggle.className, opts]);
                var menu = toggle.nextSibling;
                toggle.instance = {
                    toggled: 0,
                    toggle: function () { this.toggled++; },
                    pick: function (v) {
                        menu.querySelectorAll('.menu-item').forEach(function (item) {
                            var hit = item.getAttribute('data-bs-value') === v;
                            if (opts.multiple) { if (hit) { item.classList[item.classList.contains('selected') ? 'remove' : 'add']('selected'); } }
                            else { item.classList[hit ? 'add' : 'remove']('selected'); }
                        });
                        (toggle.listeners['change.bs.combobox'] || []).forEach(function (f) { f({}); });
                    }
                };
                return toggle.instance;
            },
            on: function (toggle, name, fn) { toggle.addEventListener(name + '.bs.combobox', fn); return function () { toggle.removeEventListener(name + '.bs.combobox', fn); }; },
            dispose: function (toggle) { calls.push(['combobox-dispose', toggle.className]); }
        },
        // Datepicker over an input or an inline box: records its options and
        // the input's value when it was created; pick(iso) fires
        // change.bs.datepicker with the dates as Bootstrap does.
        datepicker: {
            get: function (target, opts) {
                calls.push(['datepicker', target.attributes.id || target.className, opts]);
                var picked = opts.selectedDates.slice();
                target.valueAtCreate = target.value;
                target.instance = {
                    opts: opts,
                    getSelectedDates: function () { return picked.slice(); },
                    pick: function (iso) {
                        picked = [iso];
                        (target.listeners['change.bs.datepicker'] || []).forEach(function (f) { f({ dates: [iso] }); });
                    }
                };
                return target.instance;
            },
            on: function (t, name, fn) { t.addEventListener(name + '.bs.datepicker', fn); return function () { t.removeEventListener(name + '.bs.datepicker', fn); }; },
            dispose: function (t) { calls.push(['datepicker-dispose', t.attributes.id || t.className]); }
        }
    };
    return ui;
}

function load(opts) {
    var doc = makeDocument();
    var win = { document: doc, Event: function (t, o) { this.type = t; this.bubbles = !!(o && o.bubbles); }, addEventListener: function () {}, removeEventListener: function () {} };
    var calls = [];
    if (opts.jquery) { win.jQuery = makeJQuery(calls); }
    if (opts.format) { win.date_picker_format = opts.format; win.datetimepicker_options = { dateFormat: opts.format }; }
    if (!opts.noBootstrap) { win.pgUI = makePgUI(calls, opts.late); }
    // fetch() records the URL and answers when the test resolves it;
    // setTimeout() waits for flush(), as a typing pause would.
    if (opts.fetch) {
        win.fetched = [];
        win.fetch = function (url, init) {
            return new Promise(function (resolve) {
                win.fetched.push({ url: url, credentials: init && init.credentials, answer: function (data) { resolve({ ok: true, json: function () { return Promise.resolve(data); } }); } });
            });
        };
        win.timers = [];
        win.setTimeout = function (fn, ms) { win.timers.push({ fn: fn, ms: ms }); return win.timers.length; };
        win.clearTimeout = function (id) { if (win.timers[id - 1]) { win.timers[id - 1].fn = null; } };
        win.flush = function () { var t = win.timers.splice(0); t.forEach(function (x) { if (x.fn) { x.fn(); } }); return t.filter(function (x) { return x.fn; }).map(function (x) { return x.ms; }); };
    }
    var form = doc.create('form', {});
    (opts.fields || []).forEach(function (f) { form.appendChild(doc.create(f[0], f[1])); });
    (opts.options || []).forEach(function (o) {
        var host = doc.querySelector('#' + o[0]), group = o[2] ? host.children.filter(function (g) { return g.getAttribute('label') === o[2]; })[0] : null;
        if (o[2] && !group) { group = host.appendChild(doc.create('optgroup', { label: o[2] })); }
        (group || host).appendChild(doc.create('option', o[1]));
    });
    (opts.labels || []).forEach(function (l) { form.appendChild(doc.create('label', l)); });
    if (opts.firstDay !== undefined) { win.datetimepicker_options.firstDay = opts.firstDay; }
    if (opts.label) { doc.create('script', { 'data-pg-remove-label': opts.label, 'data-pg-search-label': 'Ara', 'data-pg-no-results-label': 'Sonuç bulunamadı', 'data-pg-locale-label': 'tr', 'data-pg-searching-label': 'Aranıyor…', 'data-pg-type-label': 'Aramak için yazın...' }); }
    new Function('window', 'document', PG_FIELDS_SOURCE)(win, doc);
    return { win: win, doc: doc, calls: calls };
}

function main() {
    var out = {};

    // Formats, day first and month first.
    var p = load({ format: 'd/m/yy' }).win;
    var D = p.pgDatePicker, T = p.pgDateTimeField;
    out.order = [D.order(), load({ format: 'm/d/yy' }).win.pgDatePicker.order(), load({}).win.pgDatePicker.order()];
    out.parse_day_month = ['26/10/2026', '1.2.2026', '01-02-2026', '31/2/2026', '29/2/2024', '29/2/2023', '26/10/26', '', 'x/y/z', '13/13/2026'].map(function (t) { return D.parse(t); });
    out.parse_month_day = ['10/26/2026', '26/10/2026'].map(function (t) { return D.parse(t, 'month_day'); });
    out.format = [D.format('2026-02-01'), D.format('2026-02-01', 'month_day'), D.format('2026-02-30'), D.format('26/10/2026')];
    out.parse_time = ['5:00 PM', '5 pm', '12:00 AM', '12:30 PM', '17:00', '07:05:00', '0:00', '13:00 PM', '24:00', '5', '5:60', 'noon'].map(T.parseTime);
    out.format_time = ['17:00', '00:00', '12:30', '09:05', '24:00', 'x'].map(T.formatTime);
    out.parse_datetime = ['26/10/2026 5:00 PM', '26/10/2026', '26/10/2026 25:00', 'x 5:00 PM'].map(function (t) { return T.parse(t); });
    out.format_datetime = [T.format('2026-10-26 17:00'), T.format('2026-10-26T00:05:00'), T.format('2026-10-26', 'month_day')];

    // Hooks drawn once by the v5 engine, with the screen's options.
    var fields = [
        ['input', { id: 'tags', 'data-pg-chips': '', value: 'a,b,,c', 'class': 'form-control tagin min-height-tagin border-subtle-danger', 'data-placeholder': 'Add tags' }],
        ['input', { id: 'classes', 'data-pg-chips': '', 'data-pg-separator': ' ', value: 'one two', 'class': 'form-control form-control-sm tagin' }],
        ['select', { id: 'zones', 'data-pg-combobox': '', 'data-allow-clear': 'true', multiple: '' }],
        ['input', { id: 'day', 'data-pg-date': '', value: '26/10/2026' }],
        ['input', { id: 'start', 'data-pg-datetime': '', value: '26/10/2026 5:00 PM' }],
        ['input', { id: 'cutoff', 'data-pg-time': '', value: '5:00 PM' }],
        ['input', { id: 'plain' }]
    ];
    var a = load({ jquery: true, format: 'd/m/yy', fields: fields, options: [['zones', { value: '1', text: 'One' }], ['zones', { value: '2', text: 'Two' }], ['zones', { value: '3', text: 'Three' }]] });
    a.win.pgFields.init();
    var sourced = a.doc.create('select', { 'data-pg-source': 'x.php' });
    out.engine = [a.win.pgFields.engine('chips'), a.win.pgFields.engine('combobox'), a.win.pgFields.engine('combobox', sourced), a.win.pgFields.engine('date')];
    var site = load({ jquery: true, noBootstrap: true });
    out.engine_site = [site.win.pgFields.engine('combobox', site.doc.create('select', { 'data-pg-source': 'x.php' })), site.win.pgFields.engine('combobox')];
    out.init_calls = a.calls.map(function (c) { return c[0] + ':' + c[1]; });
    var dayOpts = a.calls.filter(function (c) { return c[0] === 'datepicker'; })[0][2];
    out.date_options = [dayOpts.locale, dayOpts.firstWeekday, dayOpts.inline, dayOpts.selectedDates, dayOpts.vcpOptions];
    out.chips_options = a.calls.filter(function (c) { return c[0] === 'chips'; }).map(function (c) { return c[2]; });

    var get = function (id) { return a.doc.querySelector('#' + id); };
    out.get = {
        chips: a.win.pgChips.get('#tags'),
        classes: a.win.pgChips.get('#classes'),
        date: a.win.pgDatePicker.get('#day'),
        datetime: a.win.pgDateTimeField.get('#start'),
        time: a.win.pgDateTimeField.get('#cutoff')
    };

    // set() writes the posted text and fires one bubbling change, which a
    // jQuery listener and a native one both hear.
    var heard = [];
    var off = a.win.pgFields.onChange('#day', function (e) { heard.push('onChange:' + e.type + ':' + e.bubbles); });
    get('day').addEventListener('change', function () { heard.push('native'); });
    a.win.pgDatePicker.set('#day', '2026-02-01');
    off();
    a.win.pgDatePicker.set(get('day'), '2026-03-01');
    out.date_set = { value: get('day').value, heard: heard };
    a.win.pgDateTimeField.set('#start', '2026-02-01 00:30');
    a.win.pgDateTimeField.set('#cutoff', '13:45');
    var tags = get('tags'), box = tags.nextSibling;
    var shape = function (b) { return b.children.map(function (c) { return c.className + (c.className === 'chip' ? ':' + c.getAttribute('data-bs-chip-value') + ':' + c.querySelectorAll('.chip-dismiss').map(function (d) { return d.getAttribute('aria-label'); }).join('') : ':' + (c.getAttribute('placeholder') || '')); }); };
    out.chips_markup = { box: box.className, children: shape(box), classes: get('classes').nextSibling.className };

    // Typing a chip and removing one write the joined list into the posted
    // input and fire its change; the new chip's button gets the label.
    var chipsHeard = [];
    a.win.pgFields.onChange(tags, function (e) { chipsHeard.push(tags.value + ':' + e.bubbles); });
    box.instance.add('d');
    box.instance.remove('a');
    out.chips_typed = { heard: chipsHeard, children: shape(box) };

    a.calls.length = 0;
    a.win.pgChips.set('#tags', ['x', 'y']);
    out.chips_redraw = {
        calls: a.calls.map(function (c) { return c[0] + ':' + c[1]; }),
        old_box_gone: box.parentNode === null,
        children: shape(tags.nextSibling),
        heard: chipsHeard.length
    };
    a.win.pgChips.set('#classes', ['x', 'y']);
    out.texts = [get('start').value, get('cutoff').value, get('tags').value, get('classes').value];

    var zones = get('zones');
    a.calls.length = 0;
    a.win.pgCombobox.set(zones, ['1', 3]);
    out.combobox = {
        get: a.win.pgCombobox.get(zones),
        redraw: a.calls.map(function (c) { return c[0]; }),
        text: zones.nextSibling.children[0].textContent,
        selected: zones.nextSibling.nextSibling.querySelectorAll('.selected').map(function (i) { return i.getAttribute('data-bs-value'); })
    };

    // Loaded again with injected markup: the first copy draws the new field
    // and the drawn ones are left alone.
    var firstApi = a.win.pgFields;
    a.calls.length = 0;
    a.doc.all.filter(function (n) { return n.tagName === 'FORM'; })[0].appendChild(a.doc.create('input', { id: 'late', 'data-pg-chips': '' }));
    new Function('window', 'document', PG_FIELDS_SOURCE)(a.win, a.doc);
    out.reload = { same: a.win.pgFields === firstApi, calls: a.calls.map(function (c) { return c[0] + ':' + c[1]; }) };

    // A drawn field switched to another kind: the old widget goes, the new
    // hook replaces the old one and is drawn once; a plain field is only
    // marked and drawn.
    a.calls.length = 0;
    a.win.pgFields.switchKind('#start', 'date');
    a.win.pgFields.switchKind('#start', 'date');
    a.win.pgFields.switchKind('#start', 'datetime');
    a.win.pgFields.switchKind('#plain', 'date');
    a.win.pgFields.destroy('#zones');
    out.switch_kind = {
        calls: a.calls.map(function (c) { return c[0] + ':' + c[1]; }),
        start: [get('start').hasAttribute('data-pg-date'), get('start').hasAttribute('data-pg-datetime'), get('start').pgFieldKind],
        zones: get('zones').pgFieldKind || null
    };

    // A date in the picker's own wording gets only the date format; a
    // combobox with a source searches the server, the answer read as
    // {results: [...]}.
    var c = load({ jquery: true, label: 'Kaldır', format: 'm/d/yy', firstDay: 1, fields: [
        ['input', { id: 'shipped', 'data-pg-date': 'plain' }]
    ] });
    c.win.pgFields.init();
    // A site page (no Bootstrap 6) keeps select2 for a server-searched field.
    var c5 = load({ jquery: true, noBootstrap: true, fields: [
        ['select', { id: 'cat', 'data-pg-combobox': '', 'data-pg-source': 'tax.php?action=search', 'data-pg-min-length': '2', 'data-pg-free-text': '', 'data-placeholder': 'Category' }]
    ] });
    c5.win.pgFields.init();
    var catOpts = c5.calls.filter(function (x) { return x[0] === 'select2'; })[0][2];
    out.select2_theme = catOpts.theme;
    var plainOpts = c.calls.filter(function (x) { return x[0] === 'datepicker'; })[0][2];
    out.plain_date = [plainOpts.locale, plainOpts.firstWeekday];
    out.remote = {
        url: catOpts.ajax.url,
        min: catOpts.minimumInputLength,
        tags: catOpts.tags,
        placeholder: catOpts.placeholder,
        data: catOpts.ajax.data({ term: 'shoe' }),
        results: [catOpts.ajax.processResults({ results: [{ id: 'a', text: 'a' }] }), catOpts.ajax.processResults(null)]
    };

    // Combobox: a toggle and a menu after the hidden select; an item per
    // option, a header per group, a search box from eight options on, the
    // translated words; a pick writes the select and fires its change.
    var opts = [['acct', { value: '', text: '' }]];
    ['100', '102', '120', '153'].forEach(function (v) { opts.push(['acct', { value: v, text: 'Account ' + v }, 'Assets']); });
    ['320', '335', '360'].forEach(function (v) { opts.push(['acct', { value: v, text: 'Account ' + v, selected: v === '335' ? '' : undefined }, 'Liabilities']); });
    opts.forEach(function (o) { if (o[1].selected === undefined) { delete o[1].selected; } });
    opts.push(['few', { value: 'a', text: 'A' }], ['few', { value: 'b', text: 'B' }]);
    var cb = load({ label: 'Kaldır', labels: [{ 'for': 'acct', text: 'Account' }], fields: [
        ['select', { id: 'acct', name: 'account_id', 'data-pg-combobox': '', 'class': 'form-control form-control-sm is-invalid', 'data-placeholder': 'Choose an account' }],
        ['select', { id: 'few', 'data-pg-combobox': '', 'data-placeholder': 'Pick one' }]
    ], options: opts });
    cb.win.pgFields.init();
    var acct = cb.doc.querySelector('#acct'), toggle = acct.nextSibling, menu = toggle.nextSibling;
    var menuShape = function (m) { return m.children.map(function (c) {
        if (c.className === 'combobox-search') { return 'search:' + c.children[0].getAttribute('placeholder'); }
        if (c.tagName === 'BUTTON') { return 'item:' + c.getAttribute('data-bs-value') + ':' + c.textContent + (c.classList.contains('selected') ? ':selected' : ''); }
        return c.className + ':' + c.textContent;
    }); };
    var acctHeard = [];
    acct.addEventListener('change', function (e) { acctHeard.push(acct.value + ':' + e.bubbles); });
    out.combobox_markup = {
        calls: cb.calls.map(function (c) { return c[0] + ':' + c[1]; }),
        options: cb.calls.map(function (c) { return c[2]; }),
        native: acct.className,
        toggle: [toggle.tagName, toggle.type, toggle.getAttribute('aria-labelledby'), toggle.children[0].textContent, toggle.children[0].className],
        menu: menuShape(menu),
        few: menuShape(cb.doc.querySelector('#few').nextSibling.nextSibling),
        few_text: [cb.doc.querySelector('#few').nextSibling.children[0].textContent, cb.doc.querySelector('#few').nextSibling.children[0].className],
        heard_on_draw: acctHeard.length
    };
    (toggle.listeners.click || []).forEach(function (f) { f({ preventDefault: function () {} }); });
    out.combobox_click = toggle.instance.toggled;
    toggle.instance.pick('120');
    out.combobox_pick = { value: acct.value, heard: acctHeard.slice(), text: toggle.children[0].textContent, get: cb.win.pgCombobox.get(acct) };
    cb.win.pgFields.destroy(acct);
    out.combobox_destroy = { native: acct.className, siblings: acct.parentNode.children.length, value: acct.value, calls: cb.calls.slice(-1).map(function (c) { return c[0]; }) };

    // Without the library the field stays a plain one; no error.
    var b = load({ noBootstrap: true, fields: [['select', { id: 's', 'data-pg-combobox': '' }], ['input', { id: 'c', 'data-pg-chips': '' }]] });
    out.no_library = { kind: [b.doc.querySelector('#s').pgFieldKind || null, b.doc.querySelector('#c').pgFieldKind || null], calls: b.calls.length };

    // Bootstrap's module has not run yet: the chips are drawn once it has,
    // with the translated remove label; destroy() takes the box away.
    var l = load({ late: true, label: 'Kaldır', fields: [['input', { id: 'k', 'data-pg-chips': '', value: 'a' }]] });
    l.win.pgFields.init();
    l.win.pgFields.init();
    var before = l.calls.length;
    l.win.pgUI.release();
    var k = l.doc.querySelector('#k');
    out.late = { before: before, after: l.calls.map(function (c) { return c[0]; }), kind: k.pgFieldKind, label: k.nextSibling.querySelectorAll('.chip-dismiss')[0].getAttribute('aria-label') };
    var parent = k.parentNode;
    l.win.pgFields.destroy(k);
    out.late.destroyed = { children: parent.children.length, calls: l.calls.map(function (c) { return c[0]; }), value: k.value };

    // Dates on Bootstrap 6: our own parse and format around Datepicker, the
    // posted field the store; date and time, and time only, as boxes after
    // the hidden field; a drawer's calendar open under the field.
    var dp = load({ label: 'Kaldır', format: 'd/m/yy', firstDay: 1, labels: [{ 'for': 'dt', text: 'Start' }], fields: [
        ['input', { id: 'd', 'data-pg-date': '', value: '5/3/2026', 'class': 'form-control' }],
        ['input', { id: 'dt', 'data-pg-datetime': '', value: '26/10/2026 5:00 PM', 'class': 'form-control form-control-sm' }],
        ['input', { id: 't', 'data-pg-time': '', value: '5:30 PM', tabindex: '3' }],
        ['div', { id: 'drw', 'class': 'drawer' }]
    ] });
    var drawerField = dp.doc.create('input', { id: 'ad', 'data-pg-date': '', value: '1/2/2027' });
    dp.doc.querySelector('#drw').appendChild(drawerField);
    dp.win.pgFields.init();
    var q = function (id) { return dp.doc.querySelector('#' + id); }, dHeard = [], dtHeard = [], tHeard = [];
    q('d').addEventListener('change', function () { dHeard.push(q('d').value); });
    q('dt').addEventListener('change', function () { dtHeard.push(q('dt').value); });
    q('t').addEventListener('change', function () { tHeard.push(q('t').value); });
    var dOpts = q('d').instance.opts;
    out.dp_draw = {
        calls: dp.calls.map(function (c) { return c[0] + ':' + c[1]; }),
        options: [dOpts.locale, dOpts.firstWeekday, dOpts.inline, dOpts.selectedDates],
        // Bootstrap's own Date() reading of the input is kept out.
        value_at_create: q('d').valueAtCreate,
        value: q('d').value,
        format: dOpts.dateFormat(new Date(2026, 2, 5))
    };
    dp.calls.length = 0;
    q('d').instance.pick('2026-03-12');
    out.dp_pick = { value: q('d').value, heard: dHeard.slice(), redrawn: dp.calls.length };
    q('d').value = '7/4/2026';
    q('d').dispatchEvent({ type: 'change', bubbles: true });
    q('d').value = 'tomorrow';
    q('d').dispatchEvent({ type: 'change', bubbles: true });
    out.dp_typed = {
        calls: dp.calls.map(function (c) { return c[0] + ':' + c[1] + ':' + JSON.stringify(c[2] ? c[2].selectedDates : null); }),
        value: q('d').value
    };
    var dt = q('dt'), group = dt.nextSibling, datePart = group.children[0], timePart = group.children[1];
    out.dp_datetime = {
        native: dt.className,
        group: group.className,
        parts: [datePart.className, datePart.value, timePart.type, timePart.className, timePart.value],
        named: [datePart.getAttribute('aria-labelledby'), timePart.getAttribute('aria-labelledby')]
    };
    timePart.value = '08:15';
    timePart.dispatchEvent({ type: 'change' });
    datePart.instance.pick('2026-10-03');
    datePart.value = '';
    datePart.dispatchEvent({ type: 'change' });
    dp.win.pgDateTimeField.set(dt, '2027-01-02 23:45');
    out.dp_datetime.heard = dtHeard;
    out.dp_datetime.set = [dt.value, group.children[0].value, group.children[1].value];
    var t = q('t'), tPart = t.nextSibling;
    out.dp_time = { native: [t.className, t.getAttribute('tabindex')], part: [tPart.type, tPart.className, tPart.value] };
    tPart.value = '17:05';
    tPart.dispatchEvent({ type: 'change' });
    tPart.value = '';
    tPart.dispatchEvent({ type: 'change' });
    dp.win.pgDateTimeField.set(t, '09:00');
    out.dp_time.heard = tHeard;
    out.dp_time.set = [t.value, tPart.value];
    dp.win.pgFields.destroy(t);
    dp.win.pgFields.destroy(dt);
    out.dp_destroy = { t: [t.className, t.getAttribute('tabindex'), t.nextSibling === tPart], dt: [dt.className, dt.nextSibling === group, dt.value] };
    var wrap = drawerField.nextSibling;
    out.dp_drawer = { wrap: wrap.className, inline: wrap.children[0].instance.opts.inline };
    wrap.children[0].instance.pick('2027-02-09');
    out.dp_drawer.value = drawerField.value;
    dp.win.pgFields.destroy(drawerField);
    out.dp_drawer.after = drawerField.nextSibling === null;

    return out;
}

// Combobox searching the server (data-pg-source): the typed text fetched
// after a pause, the answer drawn as items, a pick written into the select.
function remote(out) {
    var r = load({ label: 'Kaldır', fetch: true, labels: [{ 'for': 'gpc', text: 'Google Product Category' }], fields: [
        ['select', { id: 'gpc', name: 'google_product_category', 'class': 'form-control', 'data-pg-combobox': '', 'data-pg-source': 'tax.php?action=search', 'data-pg-min-length': '2', 'data-pg-free-text': '', 'data-placeholder': 'Category' }]
    ], options: [['gpc', { value: '', text: '' }], ['gpc', { value: '187', text: 'Apparel > Shoes  (187)', selected: '' }]] });
    r.win.pgFields.init();
    var el = r.doc.querySelector('#gpc'), toggle = el.nextSibling, menu = toggle.nextSibling;
    var input = menu.children[0].children[0], heard = [];
    el.addEventListener('change', function (e) { heard.push(el.value + ':' + e.bubbles); });
    var shape = function () { return menu.children.map(function (c) {
        if (c.className === 'combobox-search') { return 'search:' + c.children[0].className + ':' + c.children[0].getAttribute('placeholder') + ':' + c.children[0].getAttribute('aria-controls'); }
        if (c.tagName === 'BUTTON') { return 'item:' + c.getAttribute('data-bs-value') + ':' + c.textContent + (c.classList.contains('selected') ? ':selected' : ''); }
        return c.className + ':' + c.textContent;
    }); };
    var type = function (text) { input.value = text; input.dispatchEvent({ type: 'input' }); };
    var tick = function () { return new Promise(function (resolve) { setImmediate(resolve); }); };
    out.remote6 = {
        calls: r.calls.map(function (c) { return c[0] + ':' + c[1]; }),
        options: r.calls.map(function (c) { return c[2]; }),
        native: el.className,
        toggle: [toggle.getAttribute('aria-labelledby'), toggle.children[0].textContent],
        menu_id: menu.id,
        drawn: shape()
    };
    (toggle.listeners['shown.bs.combobox'] || []).forEach(function (f) { f({}); });
    out.remote6.focused = r.doc.activeElement === input;
    type('a');
    out.remote6.short = { waits: r.win.flush(), fetched: r.win.fetched.length, menu: shape() };
    type('sh');
    type('shoe');
    out.remote6.waits = r.win.flush();
    out.remote6.searching = shape();
    var first = r.win.fetched[0];
    out.remote6.fetched = r.win.fetched.map(function (f) { return f.url + ':' + f.credentials; });
    first.answer({ results: [{ id: '187', text: 'Apparel > Shoes  (187)' }, { id: '188', text: 'Apparel > Shoe Laces  (188)' }] });
    return tick().then(function () {
        out.remote6.results = shape();
        // An older answer arriving after a newer one is dropped.
        type('bo');
        r.win.flush();
        type('boot');
        r.win.flush();
        r.win.fetched[2].answer({ results: [{ id: '187', text: 'Boots' }] });
        return tick();
    }).then(function () {
        r.win.fetched[1].answer({ results: [{ id: '1', text: 'Books' }] });
        return tick();
    }).then(function () {
        out.remote6.stale = shape();
        // Enter in the search box picks the first result, never submits.
        var prevented = 0, ev = { type: 'keydown', key: 'Enter', preventDefault: function () { prevented++; } };
        input.dispatchEvent(ev);
        out.remote6.enter = { prevented: prevented, clicked: r.doc.clicked.slice() };
        type('shoe');
        r.win.flush();
        r.win.fetched[3].answer({ results: [{ id: '187', text: 'Apparel > Shoes  (187)' }, { id: '188', text: 'Apparel > Shoe Laces  (188)' }] });
        return tick();
    }).then(function () {
        toggle.instance.pick('188');
        out.remote6.pick = {
            value: el.value,
            options: el.options.map(function (o) { return o.value + ':' + o.textContent + (o.selected ? ':selected' : ''); }),
            heard: heard.slice(),
            text: toggle.children[0].textContent,
            get: r.win.pgCombobox.get(el)
        };
        // Free text: the typed words are an item of their own.
        type('My own category');
        r.win.flush();
        r.win.fetched[4].answer({ results: [] });
        return tick();
    }).then(function () {
        out.remote6.free = shape();
        toggle.instance.pick('My own category');
        out.remote6.free_pick = [el.value, toggle.children[0].textContent];
        // The server refuses: its words are shown, nothing to pick.
        type('xx');
        r.win.flush();
        r.win.fetched[5].answer({ error: 'Access denied' });
        return tick();
    }).then(function () {
        out.remote6.error = shape();
        // Opening again starts from the stored choice.
        (toggle.listeners['shown.bs.combobox'] || []).forEach(function (f) { f({}); });
        out.remote6.reopened = [input.value, shape()];
        r.win.pgFields.destroy(el);
        out.remote6.destroyed = [el.className, el.value, el.parentNode.children.length];
        return out;
    });
}

try {
    var result = main();
    remote(result).then(function (o) { console.log(JSON.stringify(o)); }, function (e) { console.log('FAILED ' + (e && e.stack || e)); process.exit(1); });
} catch (e) { console.log('FAILED ' + (e && e.stack || e)); process.exit(1); }
JS;
}

// Skip only without Node; anything else that is not JSON is a failure.
function pg_fields_test_result()
{
	$r = pg_fields_test_run();

	if (is_array($r) && isset($r['error'])) {
		pg_assert_same('', $r['error'], 'pg_fields.js under Node');
	}

	return (is_array($r) && !isset($r['error'])) ? $r : null;
}

function test_pg_fields_date_parse_and_format()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(array('day_month', 'month_day', 'day_month'), $r['order'], 'from date_picker_format, day first without one');
	pg_assert_same(
		array('2026-10-26', '2026-02-01', '2026-02-01', '', '2024-02-29', '', '', '', '', ''),
		$r['parse_day_month'],
		'whole real dates only, four-digit years'
	);
	pg_assert_same(array('2026-10-26', ''), $r['parse_month_day']);
	pg_assert_same(array('1/2/2026', '2/1/2026', '', ''), $r['format'], 'no leading zeros, as the picker writes');
}

function test_pg_fields_time_parse_and_format()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(
		array('17:00', '17:00', '00:00', '12:30', '17:00', '07:05', '00:00', '', '', '', '', ''),
		$r['parse_time'],
		'12 AM is midnight, 12 PM noon; 12-hour and 24-hour text'
	);
	pg_assert_same(array('5:00 PM', '12:00 AM', '12:30 PM', '9:05 AM', '', ''), $r['format_time'], 'the picker\'s h:mm TT');
	pg_assert_same(array('2026-10-26 17:00', '2026-10-26 00:00', '', ''), $r['parse_datetime'], 'a date alone is midnight');
	pg_assert_same(array('26/10/2026 5:00 PM', '26/10/2026 12:05 AM', ''), $r['format_datetime']);
}

function test_pg_fields_hooks_drawn_by_their_engine()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(array('v6', 'v6', 'v6', 'v6'), $r['engine'], 'every kind on Bootstrap 6, a server-searched combobox too');
	pg_assert_same(array('v5', 'v6'), $r['engine_site'], 'a site page without Bootstrap 6 keeps select2 for a server-searched combobox');
	pg_assert_same(
		array('chips:chip-input pg-chips min-height-tagin border-subtle-danger', 'chips:chip-input pg-chips pg-chips-sm', 'combobox:form-control combobox-toggle pg-combobox', 'datepicker:day', 'datepicker:form-control pg-datetime-date'),
		$r['init_calls'],
		'each hook once (a time field has no calendar), the plain field untouched, a second init() draws nothing'
	);
	pg_assert_same('bootstrap-5', $r['select2_theme'], 'on a site page a server-searched combobox is a select2 as before');
	pg_assert_same(array('en', 0, false, array('2026-10-26'), array('enableDateToggle' => false)), $r['date_options'], 'no language on the tag: English, Sunday first; the stored date parsed by us');
	pg_assert_same(array(array('separator' => ','), array('separator' => ' ')), $r['chips_options'], 'Chips gets the data-pg-separator, a comma without one');
	pg_assert_same(array('chips' => array('a', 'b', 'c'), 'classes' => array('one', 'two'), 'date' => '2026-10-26', 'datetime' => '2026-10-26 17:00', 'time' => '17:00'), $r['get']);
	pg_assert_same(array('kind' => array(null, null), 'calls' => 0), $r['no_library'], 'no library: a plain field');
	pg_assert_same(array('same' => true, 'calls' => array('chips:chip-input pg-chips')), $r['reload'], 'a second load draws only the new field');
}

function test_pg_fields_switch_kind_and_destroy()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(
		array('datepicker-dispose:form-control pg-datetime-date', 'datepicker:start', 'datepicker-dispose:start', 'datepicker:form-control pg-datetime-date', 'datepicker:plain', 'combobox-dispose:form-control combobox-toggle pg-combobox'),
		$r['switch_kind']['calls'],
		'the old widget destroyed, the new one drawn, the same kind again a no-op'
	);
	pg_assert_same(array(false, true, 'datetime'), $r['switch_kind']['start'], 'one hook on the field, its kind recorded');
	pg_assert_same(null, $r['switch_kind']['zones'], 'a destroyed field is plain and can be drawn again');
}

function test_pg_fields_set_writes_the_posted_field()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(
		array('value' => '1/3/2026', 'heard' => array('onChange:change:true', 'native', 'native')),
		$r['date_set'],
		'one bubbling change per set(), none to a listener after off()'
	);
	pg_assert_same(
		array(
			'calls' => array('chips-dispose:chip-input pg-chips min-height-tagin border-subtle-danger', 'chips:chip-input pg-chips min-height-tagin border-subtle-danger'),
			'old_box_gone' => true,
			'children' => array('chip:x:Remove', 'chip:y:Remove', 'form-ghost:Add tags'),
			'heard' => 3,
		),
		$r['chips_redraw'],
		'Chips reads its markup once: the old box goes, the new value is drawn without a second change'
	);
	pg_assert_same(array('1/2/2026 12:30 AM', '1:45 PM', 'x,y', 'x y'), $r['texts'], 'site text in the posted field');
	pg_assert_same(
		array('get' => array('1', '3'), 'redraw' => array('combobox-dispose', 'combobox'), 'text' => 'One, Three', 'selected' => array('1', '3')),
		$r['combobox'],
		'the native select keeps the value; Combobox is drawn again with the chosen items and their texts'
	);
}

function test_pg_fields_liveform_prints_hooks()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();
	$form = new liveform('pg_fields_test');

	$text = $form->output_field(array('type' => 'text', 'id' => 'd', 'name' => 'd', 'data-pg-date' => ''));
	$select = $form->output_field(array('type' => 'select', 'id' => 's', 'name' => 's', 'data-pg-combobox' => '', 'options' => array('A' => 'a')));
	$valued = $form->output_field(array('type' => 'text', 'name' => 'x', 'data-pg-chips' => '"><b>', 'data-pg-Bad' => '1', 'data-other' => '1'));

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	pg_assert_contains(' data-pg-date>', $text, 'a bare hook on an input');
	pg_assert_contains(' data-pg-combobox>', $select, 'a bare hook on a select');
	pg_assert_contains(' data-pg-chips="&quot;&gt;&lt;b&gt;"', $valued, 'a value escaped');
	pg_assert_same(false, strpos($valued, 'data-pg-Bad'), 'only lower-case hook names');
	pg_assert_same(false, strpos($valued, 'data-other'), 'other data attributes are not forwarded');
}

function test_pg_fields_plain_date_and_remote_combobox()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(array('en', 0), $r['plain_date'], 'a plain date names months in English, Sunday first, whatever the panel language');
	pg_assert_same(
		array(
			'url' => 'tax.php?action=search',
			'min' => 2,
			'tags' => true,
			'placeholder' => 'Category',
			'data' => array('q' => 'shoe'),
			'results' => array(array('results' => array(array('id' => 'a', 'text' => 'a'))), array('results' => array())),
		),
		$r['remote'],
		'data-pg-source searched with q, results read from the answer, free text kept'
	);
}

function test_pg_fields_chips_on_bootstrap_6()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(
		array(
			'box' => 'chip-input pg-chips min-height-tagin border-subtle-danger',
			'children' => array('chip:a:Remove', 'chip:b:Remove', 'chip:c:Remove', 'form-ghost:Add tags'),
			'classes' => 'chip-input pg-chips pg-chips-sm',
		),
		$r['chips_markup'],
		'a chip per stored value in the markup, the input\'s own classes but the control ones, the placeholder on the ghost input'
	);
	pg_assert_same(
		array('heard' => array('a,b,c,d:true', 'b,c,d:true', 'x,y:true'), 'children' => array('chip:b:Remove', 'chip:c:Remove', 'chip:d:Remove', 'form-ghost:Add tags')),
		$r['chips_typed'],
		'an added or removed chip writes the posted input and fires one bubbling change; drawing fires none'
	);
	pg_assert_same(
		array('before' => 0, 'after' => array('chips'), 'kind' => 'chips', 'label' => 'Kaldır', 'destroyed' => array('children' => 1, 'calls' => array('chips', 'chips-dispose'), 'value' => 'a')),
		$r['late'],
		'drawn once after Bootstrap\'s module ran, the remove label from pg_fields_script(), destroy() leaves the input with its value'
	);
}

function test_pg_fields_combobox_on_bootstrap_6()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	$m = $r['combobox_markup'];
	pg_assert_same(
		array('combobox:form-control combobox-toggle pg-combobox form-control-sm is-invalid', 'combobox:form-control combobox-toggle pg-combobox'),
		$m['calls'],
		'the toggle takes the select\'s sizes and validation state'
	);
	pg_assert_same(
		array(
			array('multiple' => false, 'search' => true, 'placeholder' => 'Choose an account'),
			array('multiple' => false, 'search' => false, 'placeholder' => 'Pick one'),
		),
		$m['options'],
		'search from eight options on, the placeholder from data-placeholder'
	);
	pg_assert_same('form-control form-control-sm is-invalid pg-combobox-native', $m['native'], 'the select stays, hidden by its class');
	pg_assert_same(array('BUTTON', 'button', 'acct_label', 'Account 335', 'combobox-value'), $m['toggle'], 'a button named by the field\'s label, showing the chosen option');
	pg_assert_same(
		array(
			'search:Ara', 'combobox-no-results d-none:Sonuç bulunamadı', 'item::Choose an account',
			'menu-header:Assets', 'item:100:Account 100', 'item:102:Account 102', 'item:120:Account 120', 'item:153:Account 153',
			'menu-header:Liabilities', 'item:320:Account 320', 'item:335:Account 335:selected', 'item:360:Account 360',
		),
		$m['menu'],
		'translated search words, the empty option as an item reading as the placeholder, a header per group, the chosen item marked'
	);
	pg_assert_same(array('item:a:A', 'item:b:B'), $m['few'], 'no search box under eight options');
	pg_assert_same(array('Pick one', 'combobox-value combobox-placeholder'), $m['few_text'], 'nothing chosen: the placeholder');
	pg_assert_same(0, $m['heard_on_draw'], 'drawing fires no change');
	pg_assert_same(1, $r['combobox_click'], 'a click on the toggle opens its own instance');
	pg_assert_same(
		array('value' => '120', 'heard' => array('120:true'), 'text' => 'Account 120', 'get' => '120'),
		$r['combobox_pick'],
		'a pick writes the select and fires one bubbling change'
	);
	pg_assert_same(
		array('native' => 'form-control form-control-sm is-invalid', 'siblings' => 5, 'value' => '120', 'calls' => array('combobox-dispose')),
		$r['combobox_destroy'],
		'destroy() leaves the plain select with its value'
	);
}

function test_pg_fields_dates_on_bootstrap_6()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same(
		array(
			'calls' => array('datepicker:d', 'datepicker:form-control pg-datetime-date form-control-sm', 'datepicker:'),
			'options' => array('tr', 1, false, array('2026-03-05')),
			'value_at_create' => '',
			'value' => '5/3/2026',
			'format' => '5/3/2026',
		),
		$r['dp_draw'],
		'5/3/2026 is 5 March in a day-first site, read by our parse; the panel language, Monday first; the input empty while Bootstrap is created'
	);
	pg_assert_same(array('value' => '12/3/2026', 'heard' => array('12/3/2026'), 'redrawn' => 0), $r['dp_pick'], 'a pick writes the site text and fires one change');
	pg_assert_same(
		array('calls' => array('datepicker-dispose:d:null', 'datepicker:d:["2026-04-07"]', 'datepicker-dispose:d:null', 'datepicker:d:[]'), 'value' => 'tomorrow'),
		$r['dp_typed'],
		'a typed date moves the calendar; text that is no date is kept for the server to refuse'
	);
	pg_assert_same(
		array(
			'native' => 'form-control form-control-sm pg-field-native',
			'group' => 'pg-datetime',
			'parts' => array('form-control pg-datetime-date form-control-sm', '26/10/2026', 'time', 'form-control pg-time form-control-sm', '17:00'),
			'named' => array('dt_label', 'dt_label'),
			'heard' => array('26/10/2026 8:15 AM', '3/10/2026 8:15 AM', '', '2/1/2027 11:45 PM'),
			'set' => array('2/1/2027 11:45 PM', '2/1/2027', '23:45'),
		),
		$r['dp_datetime'],
		'date and time composed into the hidden field in the text the server reads; an empty date empties it'
	);
	pg_assert_same(
		array(
			'native' => array('pg-field-native', '-1'),
			'part' => array('time', 'form-control pg-time', '17:30'),
			'heard' => array('5:05 PM', '', '9:00 AM'),
			'set' => array('9:00 AM', '09:00'),
		),
		$r['dp_time'],
		'time only: an <input type="time"> writing h:mm AM into the hidden field'
	);
	pg_assert_same(
		array('t' => array('', '3', false), 'dt' => array('form-control form-control-sm', false, '2/1/2027 11:45 PM')),
		$r['dp_destroy'],
		'destroy() leaves the field visible with its own tab order and value'
	);
	pg_assert_same(array('wrap' => 'pg-date-inline', 'inline' => true, 'value' => '9/2/2027', 'after' => true), $r['dp_drawer'], 'in a drawer the calendar is drawn open after the field');
}

function test_pg_fields_combobox_server_search()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	$m = $r['remote6'];
	pg_assert_same(array('combobox:form-control combobox-toggle pg-combobox'), $m['calls'], 'a server-searched field is a Bootstrap 6 Combobox on the panel');
	pg_assert_same(array(array('multiple' => false, 'search' => false, 'placeholder' => 'Category')), $m['options'], 'its search box is ours: Bootstrap does not filter the server\'s answer');
	pg_assert_same('form-control pg-combobox-native', $m['native'], 'the select stays as the posted store');
	pg_assert_same(array('gpc_label', 'Apparel > Shoes  (187)'), $m['toggle'], 'named by its label, showing the stored choice');
	pg_assert_same('gpc_menu', $m['menu_id']);
	pg_assert_same(
		array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status:Aramak için yazın...', 'item::Category', 'item:187:Apparel > Shoes  (187):selected'),
		$m['drawn'],
		'a search box, a status line, the empty item and the stored choice'
	);
	pg_assert_same(true, $m['focused'], 'opening puts the cursor in the search box');
	pg_assert_same(
		array('waits' => array(250), 'fetched' => 0, 'menu' => array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status:Aramak için yazın...', 'item::Category', 'item:187:Apparel > Shoes  (187):selected')),
		$m['short'],
		'under data-pg-min-length nothing is asked'
	);
	pg_assert_same(array(250), $m['waits'], 'one request after the typing pause');
	pg_assert_same(array('tax.php?action=search&q=shoe:same-origin'), $m['fetched'], 'GET url&q=<text> with the session cookie');
	pg_assert_same('pg-combobox-status:Aranıyor…', $m['searching'][1], 'searching is announced');
	pg_assert_same(
		array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status d-none:', 'item:187:Apparel > Shoes  (187):selected', 'item:188:Apparel > Shoe Laces  (188)', 'item:shoe:shoe'),
		$m['results'],
		'the answer as items, the stored one marked, the typed text last as free text'
	);
	pg_assert_same(
		array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status d-none:', 'item:187:Boots:selected', 'item:boot:boot'),
		$m['stale'],
		'an older answer arriving late is dropped'
	);
	pg_assert_same(array('prevented' => 1, 'clicked' => array('187')), $m['enter'], 'Enter picks the first item and does not submit the form');
	pg_assert_same(
		array('value' => '188', 'options' => array(':', '188:Apparel > Shoe Laces  (188):selected'), 'heard' => array('188:true'), 'text' => 'Apparel > Shoe Laces  (188)', 'get' => '188'),
		$m['pick'],
		'a pick becomes the select\'s option and fires one bubbling change'
	);
	pg_assert_same(
		array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status d-none:', 'item:My own category:My own category'),
		$m['free'],
		'data-pg-free-text: the typed text can be picked'
	);
	pg_assert_same(array('My own category', 'My own category'), $m['free_pick']);
	pg_assert_same(
		array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status:Access denied'),
		$m['error'],
		'the server\'s refusal is shown, nothing to pick'
	);
	pg_assert_same(
		array('', array('search:form-control pg-combobox-query:Ara:gpc_menu', 'pg-combobox-status:Aramak için yazın...', 'item::Category', 'item:My own category:My own category:selected')),
		$m['reopened'],
		'opening again starts from the stored choice'
	);
	pg_assert_same(array('form-control', 'My own category', 2), $m['destroyed'], 'destroy() leaves the plain select with its value');
}
