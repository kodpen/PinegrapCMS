<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The popup calendar of the date-and-time control (assets/js/pg_datetime.js)
 * from the keyboard: the calendar hangs off <body> (or the dialog holding
 * the field), after everything else, and the library takes Tab on the field
 * into it. Tab on the field closes it and moves on, Tab from its last stop
 * goes on to the control after the field, Shift+Tab from its first stop goes
 * back to the field, Escape closes it without reaching a dialog, ArrowDown
 * opens it, and focus leaving both closes it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_datetime.js in Node against a small fake document and a stand-in
// for vanilla-calendar-pro's input mode; null without Node.
function pg_datetime_tab_order_test_run()
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

	$file = @tempnam(sys_get_temp_dir(), 'pgdttab');

	if ($file === false) {
		return $result = null;
	}

	$script = 'var SOURCE = require("fs").readFileSync(' . json_encode(dirname(__DIR__) . '/pinegrap/assets/js/pg_datetime.js') . ', "utf8");' . "\n"
		. pg_datetime_tab_order_test_harness();
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	return $result = ($code === 0 && is_array($json)) ? $json : array('error' => $text);
}

function pg_datetime_tab_order_test_harness()
{
	return <<<'JS'
var FOCUSABLE = { A: 1, BUTTON: 1, INPUT: 1, SELECT: 1, TEXTAREA: 1 };
function El(doc, tag) {
    this.ownerDocument = doc; this.tagName = tag.toUpperCase(); this.children = []; this.parentNode = null;
    this.listeners = {}; this.attributes = {}; this.className = ''; this.hidden = false; this.value = '';
    this.disabled = false; this.open = false; this.id = '';
    this.tabIndex = FOCUSABLE[this.tagName] ? 0 : -1;
    var self = this;
    this.classList = { add: function (c) { self.className = (self.className + ' ' + c).trim(); } };
}
El.prototype.addEventListener = function (t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); };
El.prototype.removeEventListener = function (t, f) { this.listeners[t] = (this.listeners[t] || []).filter(function (x) { return x !== f; }); };
El.prototype.dispatch = function (t, extra) {
    var e = { type: t, target: this, defaultPrevented: false, stopped: false, immediate: false,
        preventDefault: function () { this.defaultPrevented = true; },
        stopPropagation: function () { this.stopped = true; },
        stopImmediatePropagation: function () { this.stopped = true; this.immediate = true; } };
    for (var k in (extra || {})) { e[k] = extra[k]; }
    for (var n = this; n && !e.stopped; n = n.parentNode) {
        var list = (n.listeners[t] || []).slice();
        for (var i = 0; i < list.length && !e.immediate; i++) { list[i](e); }
    }
    if (!e.stopped) { (this.ownerDocument.listeners[t] || []).slice().forEach(function (f) { f(e); }); }
    return e;
};
El.prototype.appendChild = function (c) { if (c.parentNode) { c.parentNode.removeChild(c); } c.parentNode = this; this.children.push(c); return c; };
El.prototype.insertBefore = function (c, ref) { c.parentNode = this; var i = this.children.indexOf(ref); if (i < 0) { this.children.push(c); } else { this.children.splice(i, 0, c); } return c; };
El.prototype.removeChild = function (c) { this.children.splice(this.children.indexOf(c), 1); c.parentNode = null; return c; };
El.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
El.prototype.removeAttribute = function (k) { delete this.attributes[k]; };
El.prototype.getAttribute = function (k) { return k in this.attributes ? this.attributes[k] : null; };
El.prototype.hasAttribute = function (k) { return k in this.attributes; };
El.prototype.closest = function (sel) {
    for (var n = this; n; n = n.parentNode) {
        if (sel === 'dialog[open]' && n.tagName === 'DIALOG' && n.open) { return n; }
        if (sel === '[inert]' && n.hasAttribute && n.hasAttribute('inert')) { return n; }
    }
    return null;
};
El.prototype.getClientRects = function () { for (var n = this; n; n = n.parentNode) { if (n.hidden) { return []; } } return [{}]; };
El.prototype.querySelectorAll = function () {
    var found = [];
    (function walk(n) { n.children.forEach(function (c) { if (FOCUSABLE[c.tagName] || c.hasAttribute('tabindex')) { found.push(c); } walk(c); }); })(this);
    return found;
};
El.prototype.compareDocumentPosition = function (other) {
    var order = [];
    (function walk(n) { order.push(n); n.children.forEach(walk); })(this.ownerDocument.body);
    return order.indexOf(other) > order.indexOf(this) ? 4 : 2;
};
El.prototype.focus = function () {
    var d = this.ownerDocument, was = d.activeElement;
    if (was === this) { return; }
    if (was) { was.dispatch('focusout', { relatedTarget: this }); }
    d.activeElement = this;
    this.dispatch('focus');
};
Object.defineProperty(El.prototype, 'nextSibling', { get: function () { var p = this.parentNode; return p ? (p.children[p.children.indexOf(this) + 1] || null) : null; } });
Object.defineProperty(El.prototype, 'childNodes', { get: function () { return this.children; } });

var doc = { activeElement: null, listeners: {}, createElement: function (t) { return new El(doc, t); }, querySelector: function () { return null; },
    addEventListener: function (t, f) { (doc.listeners[t] = doc.listeners[t] || []).push(f); } };
doc.body = new El(doc, 'body');
var timers = [];
var win = { document: doc, setTimeout: function (f) { timers.push(f); }, addEventListener: function () {}, removeEventListener: function () {},
    getComputedStyle: function () { return { visibility: 'visible' }; } };
function tick() { timers.splice(0).forEach(function (f) { f(); }); }

// The library's input mode as far as the keyboard goes: the calendar box is
// made on the first show and appended to <body>; while it is shown, Tab
// (without Shift) and the arrow keys on the field move focus to its first
// stop; Escape anywhere hides it; hiding with focus inside puts focus back
// on the field.
win.VanillaCalendarPro = { Calendar: function (input, opts) {
    var self = this;
    this.opts = opts;
    this.context = { mainElement: input, inputElement: input, selectedDates: (opts.selectedDates || []).slice() };
    this.shown = false;
    var draw = function () {
        var main = doc.createElement('div');
        ['prev', 'month', 'year', 'next', 'day'].forEach(function (name) {
            var b = doc.createElement('button');
            b.id = name;
            main.appendChild(b);
        });
        main.hidden = true;
        doc.body.appendChild(main);
        self.context.mainElement = main;
        if (opts.onInit) { opts.onInit(self); }
    };
    this.show = function () {
        if (self.context.mainElement === input) { draw(); }
        if (self.shown) { return; }
        self.shown = true;
        self.context.mainElement.hidden = false;
        if (opts.onShow) { opts.onShow(self); }
    };
    this.hide = function () {
        if (!self.shown) { return; }
        var main = self.context.mainElement, inside = false;
        for (var n = doc.activeElement; n; n = n.parentNode) { if (n === main) { inside = true; } }
        self.shown = false;
        main.hidden = true;
        if (inside) { input.focus(); }
        if (opts.onHide) { opts.onHide(self); }
    };
    this.init = function () {
        input.addEventListener('keydown', function (event) {
            var arrow = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(event.key) !== -1;
            if (self.shown && doc.activeElement === input && ((event.key === 'Tab' && !event.shiftKey) || arrow)) {
                event.preventDefault();
                self.context.mainElement.children[0].focus();
            }
        });
        doc.addEventListener('keydown', function (event) { if (event.key === 'Escape') { self.hide(); } });
    };
    this.set = function () {};
    this.destroy = function () {};
} };
new Function('window', 'document', SOURCE)(win, doc);

var form = doc.body.appendChild(doc.createElement('form'));
var before = form.appendChild(doc.createElement('button'));
var field = form.appendChild(doc.createElement('input'));
var hiddenNext = form.appendChild(doc.createElement('input'));
hiddenNext.type = 'hidden';
var next = form.appendChild(doc.createElement('button'));
next.id = 'next';
var control = win.pgDateTime.attach(field, {});
var cal = control.calendar;
var out = {};
var opened = function () { return cal.shown; };
var at = function () { return doc.activeElement ? (doc.activeElement.id || doc.activeElement.tagName) : null; };

field.focus();
cal.show();
tick();
var tab = field.dispatch('keydown', { key: 'Tab' });
out.tabOnField = [opened(), at(), tab.defaultPrevented];

cal.show();
field.dispatch('keydown', { key: 'ArrowDown' });
out.arrowIn = at();
var shiftFirst = doc.activeElement.dispatch('keydown', { key: 'Tab', shiftKey: true });
out.shiftTabFirst = [at(), opened(), shiftFirst.defaultPrevented];

field.dispatch('keydown', { key: 'ArrowDown' });
cal.context.mainElement.children[4].focus();
var lastTab = doc.activeElement.dispatch('keydown', { key: 'Tab' });
out.tabLast = [at(), opened(), lastTab.defaultPrevented];

field.focus();
cal.show();
field.dispatch('keydown', { key: 'ArrowDown' });
cal.context.mainElement.children[2].focus();
var middle = doc.activeElement.dispatch('keydown', { key: 'Tab' });
out.tabMiddle = [opened(), middle.defaultPrevented];

var escInside = doc.activeElement.dispatch('keydown', { key: 'Escape' });
out.escapeInside = [opened(), at(), escInside.defaultPrevented, escInside.stopped];

var downShut = field.dispatch('keydown', { key: 'ArrowDown' });
out.arrowDownShut = [opened(), at(), downShut.defaultPrevented];

var escField = field.dispatch('keydown', { key: 'Escape' });
out.escapeField = [opened(), escField.defaultPrevented, escField.stopped];

var left = field.dispatch('keydown', { key: 'ArrowLeft' });
out.arrowLeft = [at(), left.defaultPrevented];

cal.show();
before.focus();
out.focusAway = opened();

field.focus();
cal.show();
field.dispatch('focusout', { relatedTarget: null });
tick();
out.focusToNothing = opened();

before.focus();
cal.hide();
cal.show();
tick();
out.lateOpen = opened();

// In an open dialog the control after the field is looked for inside it.
var dialog = doc.body.appendChild(doc.createElement('dialog'));
dialog.open = true;
var inDialog = dialog.appendChild(doc.createElement('input'));
var dialogNext = dialog.appendChild(doc.createElement('button'));
dialogNext.id = 'dialogNext';
var c2 = win.pgDateTime.attach(inDialog, {}).calendar;
inDialog.focus();
c2.show();
inDialog.dispatch('keydown', { key: 'ArrowDown' });
c2.context.mainElement.children[4].focus();
c2.context.mainElement.children[4].dispatch('keydown', { key: 'Tab' });
out.dialogTab = [at(), c2.shown];

control.destroy();
console.log(JSON.stringify(out));
JS;
}

function test_pg_datetime_popup_keeps_its_place_in_the_tab_order()
{
	$r = pg_datetime_tab_order_test_run();

	if ($r === null) {
		pg_assert_true(true, 'node is not installed');

		return;
	}

	pg_assert_same(null, isset($r['error']) ? $r['error'] : null, 'the harness runs');
	pg_assert_same(array(false, 'INPUT', false), $r['tabOnField'], 'Tab on the field closes the calendar and leaves the move to the browser');
	pg_assert_same('prev', $r['arrowIn'], 'ArrowDown on the field goes into the open calendar');
	pg_assert_same(array('INPUT', true, true), $r['shiftTabFirst'], 'Shift+Tab from the first stop goes back to the field, calendar open');
	pg_assert_same(array('next', false, true), $r['tabLast'], 'Tab from the last stop closes it and goes to the control after the field');
	pg_assert_same(array(true, false), $r['tabMiddle'], 'Tab inside the calendar moves on inside it');
	pg_assert_same(array(false, 'INPUT', true, true), $r['escapeInside'], 'Escape in the calendar closes it, focus on the field, goes no further');
	pg_assert_same(array(true, 'INPUT', true), $r['arrowDownShut'], 'ArrowDown on the field opens a shut calendar');
	pg_assert_same(array(false, true, true), $r['escapeField'], 'Escape on the field closes it and goes no further');
	pg_assert_same(array('INPUT', false), $r['arrowLeft'], 'Left keeps moving the text cursor');
	pg_assert_same(false, $r['focusAway'], 'focus moving to another control closes it');
	pg_assert_same(true, $r['focusToNothing'], 'focus lost to nothing (the window, a redrawn day) keeps it');
	pg_assert_same(false, $r['lateOpen'], 'an opening after focus moved on closes again');
	pg_assert_same(array('dialogNext', false), $r['dialogTab'], 'in a dialog, Tab from the last stop goes to the next control of the dialog');
}

function test_pg_datetime_popup_tab_order_is_bound_before_the_library()
{
	$js = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/pg_datetime.js');
	$popup = strpos($js, 'popup = popupKeys(box');
	$library = strpos($js, 'calendar = new window.VanillaCalendarPro.Calendar(target');

	pg_assert_true($popup !== false && $library !== false && $popup < $library, 'the box keys are bound before the library adds its own listener');
}
