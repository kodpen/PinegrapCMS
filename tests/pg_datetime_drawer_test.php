<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The calendar of the date-and-time control (assets/js/pg_datetime.js) in a
 * drawer (options.inline): drawn inside the drawer after its field, it stays
 * hidden until the field gets focus, a click or ArrowDown, and hides again on
 * a date pick, Done, Escape and focus leaving both the field and the
 * calendar. pg_datetime.css gives it the popup's surface and keeps it inside
 * the field's column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_datetime_drawer_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

// Runs pg_datetime.js in Node against a small fake document and a
// vanilla-calendar-pro stand-in; null without Node.
function pg_datetime_drawer_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgdtdrawer');

	if ($file === false) {
		return $result = null;
	}

	$script = 'var SOURCE = require("fs").readFileSync(' . json_encode(dirname(__DIR__) . '/pinegrap/assets/js/pg_datetime.js') . ', "utf8");' . "\n"
		. pg_datetime_drawer_test_harness();
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	return $result = ($code === 0 && is_array($json)) ? $json : array('error' => $text);
}

function pg_datetime_drawer_test_harness()
{
	return <<<'JS'
function El(doc, tag) {
    this.ownerDocument = doc; this.tagName = tag.toUpperCase(); this.children = []; this.parentNode = null;
    this.listeners = {}; this.attributes = {}; this.className = ''; this.hidden = false; this.value = '';
    var self = this;
    this.classList = { add: function (c) { self.className = (self.className + ' ' + c).trim(); } };
}
El.prototype.addEventListener = function (t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); };
El.prototype.removeEventListener = function (t, f) { this.listeners[t] = (this.listeners[t] || []).filter(function (x) { return x !== f; }); };
El.prototype.dispatch = function (t, extra) {
    var e = { type: t, target: this, defaultPrevented: false, stopped: false,
        preventDefault: function () { this.defaultPrevented = true; }, stopPropagation: function () { this.stopped = true; }, stopImmediatePropagation: function () { this.stopped = true; } };
    for (var k in (extra || {})) { e[k] = extra[k]; }
    for (var n = this; n && !e.stopped; n = n.parentNode) { (n.listeners[t] || []).slice().forEach(function (f) { f(e); }); }
    return e;
};
El.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); return c; };
El.prototype.insertBefore = function (c, ref) { c.parentNode = this; var i = this.children.indexOf(ref); if (i < 0) { this.children.push(c); } else { this.children.splice(i, 0, c); } return c; };
El.prototype.removeChild = function (c) { this.children.splice(this.children.indexOf(c), 1); c.parentNode = null; return c; };
El.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
El.prototype.removeAttribute = function (k) { delete this.attributes[k]; };
El.prototype.getAttribute = function (k) { return k in this.attributes ? this.attributes[k] : null; };
El.prototype.closest = function () { return null; };
El.prototype.focus = function () { var d = this.ownerDocument, was = d.activeElement; d.activeElement = this; if (was !== this) { this.dispatch('focus'); } };
Object.defineProperty(El.prototype, 'nextSibling', { get: function () { var p = this.parentNode; return p ? (p.children[p.children.indexOf(this) + 1] || null) : null; } });
Object.defineProperty(El.prototype, 'childNodes', { get: function () { return this.children; } });

var doc = { activeElement: null, createElement: function (t) { return new El(doc, t); }, querySelector: function () { return null; } };
doc.body = new El(doc, 'body');
var timers = [];
var win = { document: doc, setTimeout: function (f) { timers.push(f); }, addEventListener: function () {}, removeEventListener: function () {} };
function tick() { timers.splice(0).forEach(function (f) { f(); }); }
win.VanillaCalendarPro = { Calendar: function (target, opts) {
    var self = this;
    this.opts = opts;
    this.context = { mainElement: target, selectedDates: (opts.selectedDates || []).slice() };
    this.init = function () { if (opts.onInit) { opts.onInit(self); } };
    this.set = function () {};
    this.destroy = function () {};
    this.pick = function (iso) { self.context.selectedDates = [iso]; opts.onClickDate(self, {}); };
    target.instance = this;
} };
new Function('window', 'document', SOURCE)(win, doc);

var drawer = new El(doc, 'div'), field = new El(doc, 'input'), next = new El(doc, 'button');
drawer.appendChild(field);
drawer.appendChild(next);
var picked = [];
var control = win.pgDateTime.attach(field, { inline: true, onPick: function (iso) { picked.push(iso); } });
var wrap = field.nextSibling, cal = wrap.children[0], out = {};
var state = function () { return wrap.hidden ? 'hidden' : 'open'; };
out.wrap = wrap.className;
out.inputMode = cal.instance.opts.inputMode;
out.start = state();
field.focus();
out.focus = state();
var esc = field.dispatch('keydown', { key: 'Escape' });
out.escape = [state(), esc.stopped, esc.defaultPrevented];
field.dispatch('click');
out.click = state();
field.dispatch('keydown', { key: 'Escape' });
field.dispatch('keydown', { key: 'ArrowDown' });
out.arrowDown = state();
// Focus moving from the field into the calendar keeps it open.
field.dispatch('focusout', { relatedTarget: cal });
out.intoCalendar = state();
cal.instance.pick('2027-02-09');
out.pick = [state(), picked.join(','), doc.activeElement === field];
field.dispatch('click');
// Escape inside the calendar closes it and puts focus back on the field.
doc.activeElement = cal;
var inner = cal.dispatch('keydown', { key: 'Escape' });
out.innerEscape = [state(), doc.activeElement === field, inner.stopped];
field.dispatch('click');
field.dispatch('focusout', { relatedTarget: next });
out.focusOut = state();
field.dispatch('click');
// A press inside the calendar that leaves no element focused keeps it.
cal.dispatch('pointerdown');
field.dispatch('focusout', { relatedTarget: null });
out.pressInside = state();
tick();
field.dispatch('focusout', { relatedTarget: null });
out.blurToNothing = state();
control.destroy();
out.destroyed = field.nextSibling === next;
console.log(JSON.stringify(out));
JS;
}

function test_pg_datetime_drawer_calendar_opens_only_when_asked()
{
	$r = pg_datetime_drawer_test_run();

	if ($r === null) {
		pg_assert_true(true, 'node is not installed');

		return;
	}

	pg_assert_same(null, isset($r['error']) ? $r['error'] : null, 'the harness runs');
	pg_assert_same('pg-date-inline', $r['wrap'], 'the calendar sits in its wrapper after the field');
	pg_assert_same(false, $r['inputMode'], 'drawn in the drawer, not hung off <body>');
	pg_assert_same('hidden', $r['start'], 'the wrapper starts hidden');
	pg_assert_same('open', $r['focus'], 'focus on the field opens it');
	pg_assert_same(array('hidden', true, true), $r['escape'], 'Escape closes it and does not reach the drawer');
	pg_assert_same('open', $r['click'], 'a click on the field opens it');
	pg_assert_same('open', $r['arrowDown'], 'ArrowDown opens it');
	pg_assert_same('open', $r['intoCalendar'], 'focus moving into the calendar keeps it open');
	pg_assert_same(array('hidden', '2027-02-09', true), $r['pick'], 'a date pick writes the date, closes it, focus back on the field');
	pg_assert_same(array('hidden', true, true), $r['innerEscape'], 'Escape in the calendar closes it, focus back on the field');
	pg_assert_same('hidden', $r['focusOut'], 'focus leaving the field and the calendar closes it');
	pg_assert_same('open', $r['pressInside'], 'a press inside the calendar keeps it open');
	pg_assert_same('hidden', $r['blurToNothing'], 'focus leaving to nothing closes it');
	pg_assert_true($r['destroyed'], 'destroy() takes the wrapper away');
}

function test_pg_datetime_drawer_calendar_has_a_surface_inside_its_column()
{
	$css = pg_datetime_drawer_test_source('assets/css/pg_datetime.css');
	$start = strpos($css, '.pg-date-inline > [data-vc=calendar]');

	pg_assert_true($start !== false, 'pg_datetime.css styles the calendar in its drawer wrapper');
	$rule = $start === false ? '' : substr($css, $start, strpos($css, '}', $start) - $start);

	foreach (array('background-color: var(--pg-bg-body', 'border: var(--pg-border-width', 'border-radius: var(--pg-radius', 'box-shadow: var(--pg-shadow', 'max-width: 100%', 'min-width: 0', 'overflow-x: auto') as $part) {
		pg_assert_contains($part, $rule, 'the drawer calendar rule has ' . $part);
	}

	pg_assert_contains('.pg-date-inline[hidden]', $css, 'a hidden wrapper takes no room');
	pg_assert_true(strpos($rule, '--bs-') === false, 'the rule reads only --pg-* tokens');
}
