<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A password manager took a user's Username on Create User and Edit User for
 * a sign-in name and wrote the saved one ("admin") into it: the save was
 * refused as "already in use", or would have renamed the account. The
 * fields carry the autofill opt-outs, and the Username keeps only what the
 * person writes (data-pg-own-value, assets/js/backend.src.js), checked here
 * in Node with a stand-in document.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');

function test_user_fields_autofill_attributes()
{
	$plain = pg_user_form_no_autofill();
	$own = pg_user_form_no_autofill(true);

	foreach (array(' autocomplete="pg-no-autofill"', ' data-lpignore="true"', ' data-1p-ignore', ' data-bwignore', ' data-form-type="other"') as $attribute) {
		pg_assert_contains($attribute, $plain, 'the e-mail field carries' . $attribute);
		pg_assert_contains($attribute, $own, 'the username field carries' . $attribute);
	}

	pg_assert_false(strpos($plain, 'data-pg-own-value') !== false, 'the e-mail field is left to its input mask');
	pg_assert_contains(' data-pg-own-value', $own, 'the username keeps only typed values');
}

function test_user_fields_autofill_on_both_screens()
{
	foreach (array('add_user.php', 'edit_user.php') as $screen) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);

		pg_assert_same(1, preg_match('/<input[^\n]*name="username"[^\n]*pg_user_form_no_autofill\(true\)/', $source), $screen . ': the Username field');
		pg_assert_same(1, preg_match('/<input[^\n]*name="email"[^\n]*pg_user_form_no_autofill\(\)/', $source), $screen . ': the User Email field');
	}
}

/**
 * Runs the own-value guard of backend.src.js against a stand-in document and
 * the script, which prints JSON.
 *
 * @param string $script
 * @return array|null null where Node cannot run
 */
function user_fields_autofill_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($source, ' * A field that keeps only what the person writes in it.');
	$end = ($start === false) ? false : strpos($source, '/* ----', $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the guard is in backend.src.js');

	if (($start === false) || ($end === false)) {
		return null;
	}

	$guard = '/*' . substr($source, $start, $end - $start);

	$stand_in = <<<'JS'
var listeners = {};
var document = { addEventListener: function (type, fn, capture) { (listeners[type] = listeners[type] || []).push(fn); } };
function fire(type, target, trusted) { (listeners[type] || []).forEach(function (fn) { fn({ type: type, target: target, isTrusted: !!trusted }); }); }
function field(value, own) { return { defaultValue: value, value: value, matches: function () { return !!own; } }; }
function form(fields) { return { querySelectorAll: function () { return fields.filter(function (f) { return f.matches(); }); } }; }
JS;

	$file = @tempnam(sys_get_temp_dir(), 'pgown');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stand_in . "\n" . $guard . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_user_fields_autofill_username_keeps_only_typed_values()
{
	$result = user_fields_autofill_node(<<<'JS'
var out = {};

// Typed: a trusted beforeinput, then the input.
var a = field('elif', true);
fire('beforeinput', a, true); a.value = 'elif2'; fire('input', a, true);
out.typed = a.value;

// The browser's autofill: a trusted input with no beforeinput.
a.value = 'admin'; fire('input', a, true);
out.autofill = a.value;

// An extension: its own (untrusted) events.
fire('beforeinput', a, false); a.value = 'admin'; fire('input', a, false); fire('change', a, false);
out.extension = a.value;

// A fill that raises nothing, put back when the form is sent.
var b = field('zeynep', true);
b.value = 'admin';
fire('submit', form([b]), true);
out.silent = b.value;

// A field without the mark is left alone.
var c = field('x@example.com', false);
c.value = 'admin@example.com'; fire('input', c, true); fire('submit', form([c]), true);
out.unmarked = c.value;

// Typing after a fill was taken back is kept.
var d = field('', true);
d.value = 'admin'; fire('input', d, true);
fire('beforeinput', d, true); d.value = 'bh-test-1'; fire('input', d, true);
fire('change', d, true); fire('submit', form([d]), true);
out.afterFill = d.value;

console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same('elif2', $result['typed'], 'what the person types stays');
	pg_assert_same('elif2', $result['autofill'], 'an autofill goes back to the last typed value');
	pg_assert_same('elif2', $result['extension'], 'a manager\'s own events do not count as typing');
	pg_assert_same('zeynep', $result['silent'], 'a silent fill is put back on submit: the stored name is saved');
	pg_assert_same('admin@example.com', $result['unmarked'], 'only marked fields are guarded');
	pg_assert_same('bh-test-1', $result['afterFill'], 'the person can still type after a fill');
}

function test_user_fields_autofill_guard_is_built()
{
	pg_assert_contains('data-pg-own-value', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.min.js'), 'backend.min.js carries the guard');
}
