<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the text a designed form (custom form, member widgets) shows
 * beside a field the browser refuses before sending
 * (pg_cf_validation_script(), includes/fn/custom_form.php): the wording in
 * the page's language (pg_cf_validation_messages()), the markup the script
 * writes (the server's: invalid-feedback with an id named in
 * aria-describedby, aria-invalid), a stepped form's Next asking for the same
 * text, and the script's syntax under Node. Before (#9054) a refused field
 * only turned red.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('pg_cf_steps_script')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/custom_form_steps.php');
}

// The script as a page gets it. pg_cf_validation_script() gives it once a
// run, so after another test drew a form the function's source stands in.
function _test_cf_field_errors_script()
{
	static $script = null;

	if ($script === null) {
		$script = pg_cf_validation_script();
		if ($script === '') {
			// Another test drew a form first; the source holds the same text.
			$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
			$start = strpos($source, 'function pg_cf_validation_script()');
			$end = strpos($source, "\n}\n", (int) $start);
			$script = (string) substr($source, (int) $start, $end - (int) $start);
		}
	}

	return $script;
}

// The server's sentences, with {label} where the field's label goes.
function test_cf_field_errors_messages()
{
	$messages = pg_cf_validation_messages();

	pg_assert_same(array('required', 'box', 'email', 'emailBare', 'fill', 'choose'), array_keys($messages));
	pg_assert_same('{label} is required.', $messages['required'], 'as pg_cf_required_message()');
	pg_assert_same(pg_cf_required_message('check box', 'I agree', 1), $messages['box'], 'a lone consent box');
	pg_assert_same('Please enter a valid e-mail address for {label}', $messages['email'], 'as the custom form\'s e-mail check');

	foreach ($messages as $key => $text) {
		pg_assert_true(is_string($text) && (trim($text) !== ''), 'worded: ' . $key);
	}
}

// What the script writes on a refused field is what the server writes.
function test_cf_field_errors_script_markup()
{
	$script = _test_cf_field_errors_script();

	pg_assert_contains('invalid-feedback d-block pg-inline-feedback', $script, 'the server\'s classes');
	pg_assert_contains('setAttribute("aria-invalid","true")', $script);
	pg_assert_contains('aria-describedby', $script, 'the text named by the field');
	pg_assert_contains('"-feedback"', $script, 'the id the server gives: <control id>-feedback');
	pg_assert_contains('classList.add("has-validation")', $script, 'last in an input group');
	pg_assert_contains('classList.contains("form-check")', $script, 'last in a check box or radio row');
	pg_assert_contains('form.pgFieldErrors=', $script, 'what a stepped form\'s Next asks');
	pg_assert_contains('c.validationMessage', $script, 'the browser\'s message for the other checks');
	pg_assert_contains('{label}', $script, 'the label goes into the wording');
}

// The text is in place before the focus moves to the first refused field.
function test_cf_field_errors_before_focus()
{
	$script = _test_cf_field_errors_script();
	$check = strpos($script, 'e.preventDefault();e.stopPropagation();check(null,false);');
	$focus = strpos($script, 'if(bad&&bad.focus)bad.focus();');

	pg_assert_true(($check !== false) && ($focus !== false) && ($check < $focus));
}

// Next on a step gives the step's fields their text, the browser's bubble
// only where the validation script is missing.
function test_cf_field_errors_step_next()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form_steps.php');

	pg_assert_contains('if(form.pgFieldErrors){form.pgFieldErrors(steps[k]);}else{bad.reportValidity();}', $source);
}

// The emitted script parses (skipped without Node, as other script tests).
function test_cf_field_errors_script_parses()
{
	$script = pg_cf_validation_script();

	if ($script === '') {
		pg_assert_true(true, 'emitted earlier in this run; the markup test reads the source');
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed');
		return;
	}

	pg_assert_true((bool) preg_match('~^<script>(.*)</script>$~s', $script, $body), 'one script element');

	$file = tempnam(sys_get_temp_dir(), 'pgcf') . '.js';
	file_put_contents($file, $body[1]);
	$out = array();
	exec('node --check ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));
}
