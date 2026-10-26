<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for assets/js/pg_fields.js, the panel's field widgets behind one
 * interface (pgChips, pgCombobox, pgDatePicker, pgDateTimeField), run under
 * Node with a small fake document and jQuery stand-in: our own date and time
 * parse and format, the posted field as the store, the native change event,
 * the hooks drawn once by the loaded engine. Also the liveform side
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
    doc.all.push(this);
}
FakeElement.prototype.hasAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k); };
FakeElement.prototype.getAttribute = function (k) { return this.hasAttribute(k) ? this.attributes[k] : null; };
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
    doc.querySelector = function (sel) { return doc.all.filter(function (el) { return '#' + el.attributes.id === sel; })[0] || null; };
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

function load(opts) {
    var doc = makeDocument();
    var win = { document: doc, Event: function (t, o) { this.type = t; this.bubbles = !!(o && o.bubbles); } };
    var calls = [];
    if (opts.jquery) { win.jQuery = makeJQuery(calls); }
    if (opts.format) { win.date_picker_format = opts.format; win.datetimepicker_options = { dateFormat: opts.format }; }
    if (opts.time) { win.timepicker_options = { timeFormat: 'h:mm TT' }; }
    win.tagin = function (el, o) { calls.push(['tagin', el.attributes.id, o]); };
    (opts.fields || []).forEach(function (f) { doc.create(f[0], f[1]); });
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
        ['input', { id: 'tags', 'data-pg-chips': '', value: 'a,b,,c' }],
        ['input', { id: 'classes', 'data-pg-chips': '', 'data-pg-separator': ' ', value: 'one two' }],
        ['select', { id: 'zones', 'data-pg-combobox': '', 'data-allow-clear': 'true', multiple: '' }],
        ['input', { id: 'day', 'data-pg-date': '', value: '26/10/2026' }],
        ['input', { id: 'start', 'data-pg-datetime': '', value: '26/10/2026 5:00 PM' }],
        ['input', { id: 'cutoff', 'data-pg-time': '', value: '5:00 PM' }],
        ['input', { id: 'plain' }]
    ];
    var a = load({ jquery: true, format: 'd/m/yy', time: true, fields: fields });
    a.win.pgFields.init();
    out.engine = a.win.pgFields.engine();
    out.init_calls = a.calls.map(function (c) { return c[0] + ':' + c[1]; });
    out.select2_options = a.calls.filter(function (c) { return c[0] === 'select2'; })[0][2];
    out.time_options = a.calls.filter(function (c) { return c[0] === 'timepicker'; })[0][2];
    out.chips_options = a.calls.filter(function (c) { return c[0] === 'tagin'; }).map(function (c) { return c[2]; });

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
    var tags = get('tags'), removed = [];
    tags.nextElementSibling = { classList: { contains: function (c) { return c === 'tagin-wrapper'; } }, parentNode: { removeChild: function (n) { removed.push(n === tags.nextElementSibling); } } };
    a.calls.length = 0;
    a.win.pgChips.set('#tags', ['x', 'y']);
    out.chips_redraw = { removed: removed, calls: a.calls.map(function (c) { return c[0] + ':' + c[1]; }) };
    a.win.pgChips.set('#classes', ['x', 'y']);
    out.texts = [get('start').value, get('cutoff').value, get('tags').value, get('classes').value];

    var zones = get('zones');
    ['1', '2', '3'].forEach(function (v) { zones.options.push({ value: v, selected: false }); });
    a.calls.length = 0;
    a.win.pgCombobox.set(zones, ['1', 3]);
    out.combobox = { get: a.win.pgCombobox.get(zones), redraw: a.calls.slice() };

    // Loaded again with injected markup: the first copy draws the new field
    // and the drawn ones are left alone.
    var firstApi = a.win.pgFields;
    a.calls.length = 0;
    a.doc.create('input', { id: 'late', 'data-pg-chips': '' });
    new Function('window', 'document', PG_FIELDS_SOURCE)(a.win, a.doc);
    out.reload = { same: a.win.pgFields === firstApi, calls: a.calls.map(function (c) { return c[0] + ':' + c[1]; }) };

    // Without the library the field stays a plain one; no error.
    var b = load({ fields: [['select', { id: 's', 'data-pg-combobox': '' }]] });
    out.no_library = { kind: b.doc.querySelector('#s').pgFieldKind || null, calls: b.calls.length };

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

function test_pg_fields_hooks_drawn_by_v5_engine()
{
	$r = pg_fields_test_result();
	if (!$r) {
		return;
	}

	pg_assert_same('v5', $r['engine']);
	pg_assert_same(
		array('tagin:tags', 'tagin:classes', 'select2:zones', 'datepicker:day', 'datetimepicker:start', 'timepicker:cutoff'),
		$r['init_calls'],
		'each hook once, the plain field untouched, a second init() draws nothing'
	);
	pg_assert_same(array('theme' => 'bootstrap-5', 'allowClear' => true), $r['select2_options'], 'select2 as backend.src.js drew it');
	pg_assert_same(array('timeFormat' => 'h:mm TT'), $r['time_options'], 'time fields read timepicker_options');
	pg_assert_same(array(array('separator' => ','), array('separator' => ' ')), $r['chips_options'], 'tagin gets the data-pg-separator, a comma without one');
	pg_assert_same(array('chips' => array('a', 'b', 'c'), 'classes' => array('one', 'two'), 'date' => '2026-10-26', 'datetime' => '2026-10-26 17:00', 'time' => '17:00'), $r['get']);
	pg_assert_same(array('kind' => null, 'calls' => 0), $r['no_library'], 'no library: a plain field');
	pg_assert_same(array('same' => true, 'calls' => array('tagin:late')), $r['reload'], 'a second load draws only the new field');
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
	pg_assert_same(array('removed' => array(true), 'calls' => array('tagin:tags')), $r['chips_redraw'], 'tagin does not hear change: the old chips go and tagin draws the new value');
	pg_assert_same(array('1/2/2026 12:30 AM', '1:45 PM', 'x,y', 'x y'), $r['texts'], 'site text in the posted field');
	pg_assert_same(
		array('get' => array('1', '3'), 'redraw' => array(array('trigger', 'zones', 'change.select2'))),
		$r['combobox'],
		'the native select keeps the value, select2 only redraws'
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
