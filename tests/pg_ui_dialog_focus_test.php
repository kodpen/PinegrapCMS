<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Focus going into a Bootstrap 6 dialog or drawer once it is shown
 * (assets/js/pg_ui.js): Bootstrap opens it with showModal() while it is
 * still hidden, which leaves focus on <body> behind the inert page. A modal
 * one without focus inside gets it: its [autofocus] element, else its first
 * field, else the dialog itself; never a button; the dialog itself on a
 * touch screen. Run under Node against a small fake document.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The scenarios' JSON; null without Node, array('error' => output) when the
// script fails.
function pg_ui_dialog_focus_test_run()
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

	$file = @tempnam(sys_get_temp_dir(), 'pguifocus');

	if ($file === false) {
		return $result = null;
	}

	$script = 'var SOURCE = require("fs").readFileSync(' . json_encode(dirname(__DIR__) . '/pinegrap/assets/js/pg_ui.js') . ', "utf8");' . "\n"
		. pg_ui_dialog_focus_test_harness();
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	return $result = ($code === 0 && is_array($json)) ? $json : array('error' => $text);
}

function pg_ui_dialog_focus_test_harness()
{
	return <<<'JS'
var FOCUSABLE = { BUTTON: 1, INPUT: 1, SELECT: 1, TEXTAREA: 1, A: 1 };

// One page per scenario: `bootstrap` is the window.bootstrap it has, `touch`
// whether the screen is touch only.
function page(bootstrap, touch) {
    var doc = { activeElement: null, listeners: {} };
    function El(tag, attrs) {
        this.tagName = tag.toUpperCase(); this.children = []; this.parentNode = null; this.attributes = {};
        this.disabled = false; this.open = false; this.modal = false; this.hidden = false; this.id = ''; this.type = '';
        this.tabIndex = FOCUSABLE[this.tagName] ? 0 : -1;
        var self = this;
        this.classList = { contains: function () { return false; } };
        for (var k in (attrs || {})) {
            if (k === 'id' || k === 'type' || k === 'disabled' || k === 'hidden') { self[k] = attrs[k]; } else { self.setAttribute(k, attrs[k]); }
        }
    }
    El.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); if (k === 'tabindex') { this.tabIndex = +v; } };
    El.prototype.getAttribute = function (k) { return k in this.attributes ? this.attributes[k] : null; };
    El.prototype.hasAttribute = function (k) { return k in this.attributes; };
    El.prototype.add = function (c) { c.parentNode = this; this.children.push(c); return c; };
    El.prototype.contains = function (n) { for (; n; n = n.parentNode) { if (n === this) { return true; } } return false; };
    El.prototype.closest = function (sel) {
        for (var n = this; n; n = n.parentNode) { if (sel === '[inert]' && n.hasAttribute('inert')) { return n; } }
        return null;
    };
    El.prototype.matches = function (sel) {
        if (sel === ':modal') { return this.tagName === 'DIALOG' && this.open && this.modal; }
        return false;
    };
    El.prototype.getClientRects = function () { for (var n = this; n; n = n.parentNode) { if (n.hidden) { return []; } } return [{}]; };
    El.prototype.querySelectorAll = function (sel) {
        var found = [], auto = sel === '[autofocus]';
        (function walk(n) {
            n.children.forEach(function (c) {
                var field = (c.tagName === 'INPUT' && c.type !== 'hidden') || c.tagName === 'SELECT' || c.tagName === 'TEXTAREA';
                if (auto ? c.hasAttribute('autofocus') : field) { found.push(c); }
                walk(c);
            });
        })(this);
        return found;
    };
    El.prototype.focus = function (opts) { doc.activeElement = this; this.focusOptions = opts || null; };
    doc.createElement = function (tag) { return new El(tag); };
    doc.body = new El('body');
    doc.activeElement = doc.body;
    doc.addEventListener = function (t, f) { (doc.listeners[t] = doc.listeners[t] || []).push(f); };
    doc.fire = function (t, target) { (doc.listeners[t] || []).slice().forEach(function (f) { f({ type: t, target: target }); }); };
    var win = { document: doc, bootstrap: bootstrap,
        getComputedStyle: function () { return { visibility: 'visible' }; },
        matchMedia: function (q) { return { matches: !!touch && q === '(hover: none) and (pointer: coarse)' }; } };
    new Function('window', 'document', SOURCE)(win, doc);
    return { doc: doc, el: function (tag, attrs) { return new El(tag, attrs); } };
}

var six = { Dialog: function () {} }, five = { Modal: function () {} };
function dialog(p, kids, modal) {
    var d = p.doc.body.add(p.el('dialog'));
    d.open = true;
    d.modal = modal !== false;
    kids.forEach(function (k) { d.add(k); });
    return d;
}
function focused(p) { var a = p.doc.activeElement; return a ? (a.id || a.tagName) : null; }
var out = {};

// The first field, past a button, a hidden input, a disabled one, an
// undrawn one and one in an inert part.
var p = page(six);
var inert = p.el('div', { inert: '' });
inert.add(p.el('input', { id: 'inert' }));
var undrawn = p.el('div', { hidden: true });
undrawn.add(p.el('input', { id: 'undrawn' }));
var body = p.el('div');
[p.el('button', { id: 'close' }), p.el('input', { id: 'store', type: 'hidden' }), p.el('input', { id: 'off', disabled: true }),
    p.el('input', { id: 'minus', tabindex: '-1' }), undrawn, inert, p.el('input', { id: 'name' }), p.el('select', { id: 'kind' })].forEach(function (k) { body.add(k); });
var d = dialog(p, [body]);
p.doc.fire('shown.bs.dialog', d);
out.firstField = [focused(p), !!(p.doc.activeElement.focusOptions && p.doc.activeElement.focusOptions.preventScroll)];

// [autofocus] comes before the first field.
p = page(six);
d = dialog(p, [p.el('input', { id: 'name' }), p.el('textarea', { id: 'note', autofocus: '' })]);
p.doc.fire('shown.bs.dialog', d);
out.autofocus = focused(p);

// A drawer.
p = page(six);
d = dialog(p, [p.el('button', { id: 'close' }), p.el('textarea', { id: 'note' })]);
p.doc.fire('shown.bs.drawer', d);
out.drawer = focused(p);

// No field: the dialog itself, never its buttons.
p = page(six);
d = dialog(p, [p.el('button', { id: 'close' }), p.el('button', { id: 'yes' })]);
d.id = 'confirm';
p.doc.fire('shown.bs.dialog', d);
out.noField = [focused(p), d.getAttribute('tabindex')];

// Focus a screen's own code already put inside stays.
p = page(six);
var own = p.el('button', { id: 'own' });
d = dialog(p, [p.el('input', { id: 'name' }), own]);
p.doc.activeElement = own;
p.doc.fire('shown.bs.dialog', d);
out.ownFocus = focused(p);

// A non-modal one is left alone: the page behind it is not inert.
p = page(six);
d = dialog(p, [p.el('input', { id: 'name' })], false);
p.doc.fire('shown.bs.drawer', d);
out.nonModal = focused(p);

// A touch screen: the dialog itself, no on-screen keyboard over it.
p = page(six, true);
d = dialog(p, [p.el('input', { id: 'name' })]);
d.id = 'dlg';
p.doc.fire('shown.bs.dialog', d);
out.touch = focused(p);

// Bootstrap 5 moves focus itself.
p = page(five);
d = dialog(p, [p.el('input', { id: 'name' })]);
p.doc.fire('shown.bs.dialog', d);
out.five = focused(p);

console.log(JSON.stringify(out));
JS;
}

function test_pg_ui_focus_goes_into_a_shown_dialog()
{
	$r = pg_ui_dialog_focus_test_run();

	if ($r === null) {
		pg_assert_true(true, 'node is not installed');

		return;
	}

	pg_assert_same(null, isset($r['error']) ? $r['error'] : null, 'the harness runs');
	pg_assert_same(array('name', true), $r['firstField'], 'the first drawn, enabled field in the tab order takes focus, without scrolling');
	pg_assert_same('note', $r['autofocus'], 'an [autofocus] element comes first');
	pg_assert_same('note', $r['drawer'], 'a drawer gets focus too');
	pg_assert_same(array('confirm', '-1'), $r['noField'], 'with no field the dialog itself takes focus, never a button');
	pg_assert_same('own', $r['ownFocus'], 'focus already inside the dialog stays');
	pg_assert_same('BODY', $r['nonModal'], 'a non-modal dialog is left alone');
	pg_assert_same('dlg', $r['touch'], 'on a touch screen the dialog itself takes focus');
	pg_assert_same('BODY', $r['five'], 'under Bootstrap 5 nothing is moved');
}
