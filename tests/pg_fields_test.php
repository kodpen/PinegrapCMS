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
FakeElement.prototype.removeChild = function (c) { this.children = this.children.filter(function (x) { return x !== c; }); c.parentNode = null; return c; };
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
    var doc = { all: [], readyState: 'complete' };
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
        }
    };
    return ui;
}

function load(opts) {
    var doc = makeDocument();
    var win = { document: doc, Event: function (t, o) { this.type = t; this.bubbles = !!(o && o.bubbles); } };
    var calls = [];
    if (opts.jquery) { win.jQuery = makeJQuery(calls); }
    if (opts.format) { win.date_picker_format = opts.format; win.datetimepicker_options = { dateFormat: opts.format }; }
    if (opts.time) { win.timepicker_options = { timeFormat: 'h:mm TT' }; }
    if (!opts.noBootstrap) { win.pgUI = makePgUI(calls, opts.late); }
    var form = doc.create('form', {});
    (opts.fields || []).forEach(function (f) { form.appendChild(doc.create(f[0], f[1])); });
    (opts.options || []).forEach(function (o) {
        var host = doc.querySelector('#' + o[0]), group = o[2] ? host.children.filter(function (g) { return g.getAttribute('label') === o[2]; })[0] : null;
        if (o[2] && !group) { group = host.appendChild(doc.create('optgroup', { label: o[2] })); }
        (group || host).appendChild(doc.create('option', o[1]));
    });
    (opts.labels || []).forEach(function (l) { form.appendChild(doc.create('label', l)); });
    if (opts.label) { doc.create('script', { 'data-pg-remove-label': opts.label, 'data-pg-search-label': 'Ara', 'data-pg-no-results-label': 'Sonuç bulunamadı' }); }
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
    var a = load({ jquery: true, format: 'd/m/yy', time: true, fields: fields, options: [['zones', { value: '1', text: 'One' }], ['zones', { value: '2', text: 'Two' }], ['zones', { value: '3', text: 'Three' }]] });
    a.win.pgFields.init();
    var sourced = a.doc.create('select', { 'data-pg-source': 'x.php' });
    out.engine = [a.win.pgFields.engine('chips'), a.win.pgFields.engine('combobox'), a.win.pgFields.engine('combobox', sourced), a.win.pgFields.engine('date')];
    out.init_calls = a.calls.map(function (c) { return c[0] + ':' + c[1]; });
    out.time_options = a.calls.filter(function (c) { return c[0] === 'timepicker'; })[0][2];
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
        calls: a.calls.map(function (c) { return c[0] + ':' + c[1] + (typeof c[2] === 'string' ? ':' + c[2] : ''); }),
        start: [get('start').hasAttribute('data-pg-date'), get('start').hasAttribute('data-pg-datetime'), get('start').pgFieldKind],
        zones: get('zones').pgFieldKind || null
    };

    // A date in the picker's own wording gets only the date format; a
    // combobox with a source searches the server, the answer read as
    // {results: [...]}.
    var c = load({ jquery: true, format: 'm/d/yy', fields: [
        ['input', { id: 'shipped', 'data-pg-date': 'plain' }],
        ['select', { id: 'cat', 'data-pg-combobox': '', 'data-pg-source': 'tax.php?action=search', 'data-pg-min-length': '2', 'data-pg-free-text': '', 'data-placeholder': 'Category' }]
    ] });
    c.win.pgFields.init();
    var catOpts = c.calls.filter(function (x) { return x[0] === 'select2'; })[0][2];
    out.select2_theme = catOpts.theme;
    out.plain_date = c.calls.filter(function (x) { return x[0] === 'datepicker'; })[0][2];
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

    console.log(JSON.stringify(out));
}

try { main(); } catch (e) { console.log('FAILED ' + (e && e.stack || e)); process.exit(1); }
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

	pg_assert_same(array('v6', 'v6', 'v5', 'v5'), $r['engine'], 'chips and comboboxes on Bootstrap 6; a server-searched combobox and the dates still on v5');
	pg_assert_same(
		array('chips:chip-input pg-chips min-height-tagin border-subtle-danger', 'chips:chip-input pg-chips pg-chips-sm', 'combobox:form-control combobox-toggle pg-combobox', 'datepicker:day', 'datetimepicker:start', 'timepicker:cutoff'),
		$r['init_calls'],
		'each hook once, the plain field untouched, a second init() draws nothing'
	);
	pg_assert_same('bootstrap-5', $r['select2_theme'], 'a server-searched combobox is a select2 as before');
	pg_assert_same(array('timeFormat' => 'h:mm TT'), $r['time_options'], 'time fields read timepicker_options');
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
		array('datepicker:start:destroy', 'datepicker:start', 'datepicker:start:destroy', 'datetimepicker:start', 'datepicker:plain', 'combobox-dispose:form-control combobox-toggle pg-combobox'),
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

	pg_assert_same(array('dateFormat' => 'm/d/yy'), $r['plain_date'], 'a plain date gets the site format and none of the translated names');
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
			'search:Ara', 'combobox-no-results d-none:Sonuç bulunamadı', "item::\u{2014}",
			'menu-header:Assets', 'item:100:Account 100', 'item:102:Account 102', 'item:120:Account 120', 'item:153:Account 153',
			'menu-header:Liabilities', 'item:320:Account 320', 'item:335:Account 335:selected', 'item:360:Account 360',
		),
		$m['menu'],
		'translated search words, the empty option as an item, a header per group, the chosen item marked'
	);
	pg_assert_same(array('item:a:A', 'item:b:B'), $m['few'], 'no search box under eight options');
	pg_assert_same(array('Pick one', 'combobox-value combobox-placeholder'), $m['few_text'], 'nothing chosen: the placeholder');
	pg_assert_same(0, $m['heard_on_draw'], 'drawing fires no change');
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
