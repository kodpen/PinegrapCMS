<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The theme of the calendar of assets/js/pg_datetime.js: it comes only from
 * the calendar's own ancestors (the nearest data-bs-theme), light with none,
 * never from the first element of the page carrying the attribute; the
 * inline month calendar follows a theme change made while it is shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_datetime.js in Node against a small fake document and a
// vanilla-calendar-pro stand-in; null without Node.
function pg_datetime_theme_test_run()
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

	$file = @tempnam(sys_get_temp_dir(), 'pgdttheme');

	if ($file === false) {
		return $result = null;
	}

	$script = 'var SOURCE = require("fs").readFileSync(' . json_encode(dirname(__DIR__) . '/pinegrap/assets/js/pg_datetime.js') . ', "utf8");' . "\n"
		. pg_datetime_theme_test_harness();
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	return $result = ($code === 0 && is_array($json)) ? $json : array('error' => $text);
}

function pg_datetime_theme_test_harness()
{
	return <<<'JS'
function El(doc, tag) {
    this.ownerDocument = doc; this.tagName = tag.toUpperCase(); this.children = []; this.parentNode = null;
    this.listeners = {}; this.attributes = {}; this.className = ''; this.hidden = false; this.value = ''; this.dataset = {};
    var self = this;
    this.classList = { add: function (c) { self.className = (self.className + ' ' + c).trim(); } };
}
El.prototype.addEventListener = function (t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); };
El.prototype.removeEventListener = function () {};
El.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); return c; };
El.prototype.insertBefore = function (c, ref) { c.parentNode = this; var i = this.children.indexOf(ref); if (i < 0) { this.children.push(c); } else { this.children.splice(i, 0, c); } return c; };
El.prototype.removeChild = function (c) { this.children.splice(this.children.indexOf(c), 1); c.parentNode = null; return c; };
El.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); observers.forEach(function (o) { o.fire(k); }); };
El.prototype.removeAttribute = function (k) { delete this.attributes[k]; observers.forEach(function (o) { o.fire(k); }); };
El.prototype.getAttribute = function (k) { return k in this.attributes ? this.attributes[k] : null; };
El.prototype.closest = function (sel) {
    for (var n = this; n; n = n.parentNode) { if (sel === '[data-bs-theme]' && n.getAttribute && n.getAttribute('data-bs-theme') !== null) { return n; } }
    return null;
};

var observers = [];
function MutationObserver(fn) { this.fn = fn; this.on = false; }
MutationObserver.prototype.observe = function () { this.on = true; observers.push(this); };
MutationObserver.prototype.disconnect = function () { this.on = false; };
MutationObserver.prototype.fire = function (k) { if (this.on && k === 'data-bs-theme') { this.fn([{ attributeName: k }]); } };

var doc = { activeElement: null, createElement: function (t) { return new El(doc, t); } };
doc.documentElement = new El(doc, 'html');
doc.body = doc.documentElement.appendChild(new El(doc, 'body'));
// The first element of the page with the attribute is a dark footer that
// is no ancestor of any calendar.
var footer = new El(doc, 'footer');
footer.attributes['data-bs-theme'] = 'dark';
doc.querySelector = function (sel) { return sel === '[data-bs-theme]' ? footer : null; };
var win = { document: doc, MutationObserver: MutationObserver, setTimeout: function (f) { f(); }, addEventListener: function () {}, removeEventListener: function () {} };
win.VanillaCalendarPro = { Calendar: function (target, opts) {
    var self = this;
    this.opts = opts; this.sets = [];
    this.selectedTheme = opts.selectedTheme;
    this.context = { mainElement: target, selectedDates: [], selectedYear: 2026, selectedMonth: 10 };
    this.init = function () { if (opts.onInit) { opts.onInit(self); } };
    this.set = function (o) { self.sets.push(o); if (o.selectedTheme) { self.selectedTheme = o.selectedTheme; } };
    this.show = function () { if (opts.onShow) { opts.onShow(self); } };
    this.destroy = function () {};
    target.instance = this;
} };
new Function('window', 'document', 'MutationObserver', SOURCE)(win, doc, MutationObserver);

var out = {}, p = win.pgDateTime;
out.options = typeof p.themeOptions === 'function'
    ? ['', 'light', 'dark', 'auto', 'other'].map(function (t) { var o = p.themeOptions(t); return o.selectedTheme + '|' + o.themeAttrDetect; })
    : null;

doc.body.appendChild(footer);
var holder = doc.body.appendChild(new El(doc, 'div'));
var m = p.month(holder, {});
var cal = m.calendar;
out.monthPlain = cal.opts.selectedTheme + '|' + cal.opts.themeAttrDetect;
out.monthPlainBox = cal.context.mainElement.getAttribute('data-bs-theme');

var section = doc.body.appendChild(new El(doc, 'section'));
section.attributes['data-bs-theme'] = 'dark';
var inner = section.appendChild(new El(doc, 'div'));
out.monthDark = p.month(inner, {}).calendar.opts.selectedTheme;

var field = doc.body.appendChild(new El(doc, 'input'));
var popup = p.attach(field, {});
var pcal = field.parentNode.children.filter(function (c) { return c.instance; })[0];
out.popupPlain = popup && popup.calendar ? popup.calendar.opts.selectedTheme + '|' + popup.calendar.opts.themeAttrDetect : null;

// The theme switcher sets dark on <html> while the month calendar is shown.
doc.documentElement.setAttribute('data-bs-theme', 'dark');
var last = cal.sets[cal.sets.length - 1] || {};
out.followed = [last.selectedTheme || null, last.themeAttrDetect, last.selectedYear, last.selectedMonth, cal.context.mainElement.getAttribute('data-bs-theme')];
var count = cal.sets.length;
doc.documentElement.setAttribute('data-bs-theme', 'dark');
out.sameThemeNoSet = cal.sets.length === count;
m.destroy();
doc.documentElement.setAttribute('data-bs-theme', 'light');
out.destroyedNoSet = cal.sets.length === count;
console.log(JSON.stringify(out));
JS;
}

function test_pg_datetime_theme_options_from_the_ancestor_theme()
{
	$r = pg_datetime_theme_test_run();

	if ($r === null) {
		pg_assert_true(true, 'node is not installed');

		return;
	}

	pg_assert_same(null, isset($r['error']) ? $r['error'] : null, 'the harness runs');
	pg_assert_same(array('light|', 'light|', 'dark|', 'system|', 'light|'), $r['options'], 'no ancestor theme is light; auto follows the system; no element of the page is read');
}

function test_pg_datetime_theme_month_and_popup_ignore_a_dark_footer()
{
	$r = pg_datetime_theme_test_run();

	if ($r === null) {
		pg_assert_true(true, 'node is not installed');

		return;
	}

	pg_assert_same(null, isset($r['error']) ? $r['error'] : null, 'the harness runs');
	pg_assert_same('light|', $r['monthPlain'], 'the month calendar on a page without a theme is light, whatever the footer says');
	pg_assert_same(null, $r['monthPlainBox'], 'its box gets no data-bs-theme of its own');
	pg_assert_same('dark', $r['monthDark'], 'inside a dark section it is dark');
	pg_assert_same('light|', $r['popupPlain'], 'the popup calendar of a date field is light too');
}

function test_pg_datetime_theme_month_follows_a_theme_change()
{
	$r = pg_datetime_theme_test_run();

	if ($r === null) {
		pg_assert_true(true, 'node is not installed');

		return;
	}

	pg_assert_same(null, isset($r['error']) ? $r['error'] : null, 'the harness runs');
	pg_assert_same(array('dark', '', 2026, 10, 'dark'), $r['followed'], 'dark on <html> turns the shown month dark and keeps the month');
	pg_assert_true($r['sameThemeNoSet'], 'the same theme again redraws nothing');
	pg_assert_true($r['destroyedNoSet'], 'after destroy() it no longer follows');
}
