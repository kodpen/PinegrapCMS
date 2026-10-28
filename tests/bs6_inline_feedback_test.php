<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where a field's error message goes on Bootstrap 6 panel screens:
 * pgPlaceInlineFeedback() in backend.src.js, run under Node on a small fake
 * document, moves or makes the message after an .input-group, .form-adorn,
 * .chip-input or .switch (at the end of the .form-field for a switch) and
 * never doubles it; source pins for the forms that print their own message,
 * for toasts hosted in the top dialog and for Ctrl+Q driving the bell.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_inline_feedback_test_backend()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
}

// The placement code of backend.src.js: from the wrapper list to the
// auto-injector that calls it.
function pg_inline_feedback_test_source()
{
	$source = pg_inline_feedback_test_backend();
	$start = strpos($source, 'var PG_FEEDBACK_WRAPPERS');
	$end = strpos($source, '// Auto-injectors: aria-invalid', (int) $start);

	if ($start === false || $end === false) {
		return '';
	}

	return substr($source, $start, $end - $start);
}

// Runs the scenarios with Node and returns their JSON; null without Node,
// array('error' => output) when the script fails.
function pg_inline_feedback_test_run()
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

	$file = @tempnam(sys_get_temp_dir(), 'pgfb');

	if ($file === false) {
		return $result = null;
	}

	file_put_contents($file, 'var PG_SOURCE = ' . json_encode(pg_inline_feedback_test_source()) . ";\n" . pg_inline_feedback_test_harness());
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

function pg_inline_feedback_test_harness()
{
	return <<<'JS'
function El(tag, cls, attrs) {
    var self = this;
    this.tagName = tag.toUpperCase();
    this.className = cls || '';
    this.attributes = attrs || {};
    this.children = [];
    this.parentNode = null;
    this.dataset = {};
    this.id = this.attributes.id || '';
    this.textContent = '';
    this.classList = {
        contains: function (c) { return self.className.split(/\s+/).indexOf(c) !== -1; }
    };
}
El.prototype.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k) ? this.attributes[k] : null; };
El.prototype.hasAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k); };
El.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
El.prototype.remove = function () {
    if (this.parentNode) { var s = this.parentNode.children; s.splice(s.indexOf(this), 1); this.parentNode = null; }
};
El.prototype.appendChild = function (c) { c.remove(); c.parentNode = this; this.children.push(c); return c; };
El.prototype.insertAdjacentElement = function (where, c) {
    c.remove();
    var s = this.parentNode.children;
    s.splice(s.indexOf(this) + 1, 0, c);
    c.parentNode = this.parentNode;
    return c;
};
Object.defineProperty(El.prototype, 'nextElementSibling', { get: function () {
    if (!this.parentNode) { return null; }
    var s = this.parentNode.children;
    return s[s.indexOf(this) + 1] || null;
} });
Object.defineProperty(El.prototype, 'lastElementChild', { get: function () { return this.children[this.children.length - 1] || null; } });
El.prototype.matches = function (sel) {
    var self = this;
    return sel.split(',').some(function (one) { return self.classList.contains(one.trim().replace(/^\./, '')); });
};
El.prototype.closest = function (sel) {
    for (var el = this; el; el = el.parentNode) { if (el.matches(sel)) { return el; } }
    return null;
};
function tree(el, kids) { kids.forEach(function (k) { el.appendChild(k); }); return el; }
// The parent's children as tag.class, the field as "FIELD", a message by its text.
function shape(el) {
    return el.children.map(function (c) {
        if (c.classList.contains('invalid-feedback')) { return 'MSG:' + c.textContent; }
        if (c.isField) { return 'FIELD'; }
        return c.className.split(' ')[0] + (c.children.length ? '[' + shape(c).join(' ') + ']' : '');
    });
}
function field(name) { var f = new El('input', 'form-control is-invalid', { name: name }); f.isField = true; return f; }
function server(text) { var m = new El('div', 'invalid-feedback d-block pg-inline-feedback'); m.textContent = text; return m; }

var document = { createElement: function (tag) { return new El(tag); } };
var window = {};
new Function('document', 'window', PG_SOURCE + '\nwindow.place = pgPlaceInlineFeedback;')(document, window);
var place = window.place, out = {};

// Server message inside an input-group: moved after the group.
var f1 = field('name'), box1 = tree(new El('div', 'col'), [tree(new El('div', 'input-group'), [new El('span', 'input-group-text'), f1, server('Required')])]);
place(f1, null);
out.group_server = shape(box1);

// Injected message for a form-adorn field, run twice: one message.
var f2 = field('price'), box2 = tree(new El('div', 'col'), [tree(new El('label', 'form-control form-adorn'), [new El('span', 'form-adorn-text'), f2])]);
place(f2, { price: 'Bad price' });
f2.dataset.pgFeedbackInjected = '';
place(f2, { price: 'Bad price' });
out.adorn_injected = shape(box2);
out.adorn_described = f2.getAttribute('aria-describedby');

// A switch inside a .form-field: at the end of the .form-field.
var f3 = field('on'), box3 = tree(new El('div', 'form-field'), [tree(new El('div', 'switch'), [f3, server('Pick one')]), new El('label', 'form-label')]);
place(f3, null);
out.switch_field = shape(box3);

// A message already after the wrapper (the output_errors() call ran first):
// the server one inside goes, nothing is added.
var f4 = field('tags'), already = server('Too many'), inner = server('Too many');
var box4 = tree(new El('div', 'col'), [tree(new El('div', 'chip-input'), [f4, inner]), already]);
place(f4, { tags: 'Too many' });
out.chips_no_double = shape(box4);

// A plain field keeps its message right after it.
var f5 = field('title'), box5 = tree(new El('div', 'col'), [f5]);
place(f5, { 'title': 'Empty' });
out.plain = shape(box5);

// A page's own static message inside a group stays.
var f6 = field('x'), stat = new El('div', 'invalid-feedback'); stat.textContent = 'Static';
var box6 = tree(new El('div', 'col'), [tree(new El('div', 'input-group'), [f6, stat])]);
place(f6, { x: 'Other' });
out.static_kept = shape(box6);

console.log(JSON.stringify(out));
JS;
}

function test_bs6_inline_feedback_placement()
{
	pg_assert_true(pg_inline_feedback_test_source() !== '', 'placement code found in backend.src.js');

	$r = pg_inline_feedback_test_run();

	if ($r === null) {
		return;
	}

	if (isset($r['error'])) {
		pg_assert_same('', $r['error'], 'placement code under Node');
		return;
	}

	pg_assert_same(array('input-group[input-group-text FIELD]', 'MSG:Required'), $r['group_server'], 'server message moved after the input-group');
	pg_assert_same(array('form-control[form-adorn-text FIELD]', 'MSG:Bad price'), $r['adorn_injected'], 'injected after the form-adorn, once');
	pg_assert_true(is_string($r['adorn_described']) && substr($r['adorn_described'], -9) === '-feedback', 'aria-describedby names the message');
	pg_assert_same(array('switch[FIELD]', 'form-label', 'MSG:Pick one'), $r['switch_field'], 'switch: end of the .form-field');
	pg_assert_same(array('chip-input[FIELD]', 'MSG:Too many'), $r['chips_no_double'], 'no second message');
	pg_assert_same(array('FIELD', 'MSG:Empty'), $r['plain'], 'plain field: right after it');
	pg_assert_same(array('input-group[FIELD MSG:Static]'), $r['static_kept'], 'a static message of the page stays');
}

function test_bs6_inline_feedback_forms()
{
	$short = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');
	$at = strpos($short, "'placeholder'=>lang('Short Link Name')");
	$group_end = strpos($short, '</div>', (int) $at);
	pg_assert_true($at !== false && strpos(substr($short, $at, $group_end - $at), 'invalid-feedback') === false, 'short link name: no message inside its group');

	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/account_quick.php');
	$lookup_end = strpos($quick, "(\$lookup ? '</div>' : '')");
	$message = strpos($quick, 'data-erp-quick-error="tax_number"');
	pg_assert_true($lookup_end !== false && $message > $lookup_end, 'tax number message after the lookup group');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/erp_account_quick.js');
	pg_assert_contains("feedback.classList.add('d-block')", $js);
	pg_assert_contains("node.classList.remove('d-block')", $js);
}

// Toasts go into the top open dialog; Ctrl+Q clicks the bell and the menu
// events are heard on the toggle.
function test_bs6_toasts_and_notifications_menu()
{
	$js = pg_inline_feedback_test_backend();
	$toast = strpos($js, 'window.pgToast = function');
	pg_assert_true($toast !== false && strpos($js, 'pgUI.hostLayer(container)', $toast) !== false, 'pgToast hosts its container in the top dialog');

	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("pgUI.hostLayer(container);\n        container.appendChild(element);", $folders, 'explorer toast hosted in the top dialog');

	pg_assert_contains('notification_toggle.click();', $js, 'Ctrl+Q clicks the bell');
	pg_assert_false(strpos($js, 'pgUI.menu.toggle(notifications)') !== false, 'no second Menu on the .menu box');
	pg_assert_false(strpos($js, "pgUI.menu.on('#notifications'") !== false, 'menu events heard on the toggle');
	pg_assert_false(strpos($js, "pgUI.menu.isOpen('#notifications')") !== false, 'open state read from the toggle');
	pg_assert_same(2, substr_count($js, 'get_notifications(true);'), 'only the bell click and the shown handler refresh the list');
}
