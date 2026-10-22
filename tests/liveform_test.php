<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for liveform field values that arrive as arrays (?name[]=x): a field
 * that holds one value keeps its markup instead of stopping the page; a field
 * without an id prints none, and the CAPTCHA answer box has an id and a label.
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

// Runs $callback with an empty session and puts the previous session back.
function _test_liveform_with_session($callback)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$callback(new liveform('liveform_test'));

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// A text input and a textarea keep the value they were written with when the
// session value is an array; a string value still replaces it.
function test_liveform_prepare_ignores_array_value()
{
	_test_liveform_with_session(function ($form) {
		$form->set('first_name', array('x'));
		$form->set('body', array('y'));
		$form->set('email', 'a@example.com');

		$html = $form->prepare(
			'<input type="text" name="first_name" value="kept">'
			. '<textarea name="body">kept text</textarea>'
			. '<input type="email" name="email" value="">');

		pg_assert_contains('<input type="text" name="first_name" value="kept">', $html, 'text input');
		pg_assert_contains('<textarea name="body">kept text</textarea>', $html, 'textarea');
		pg_assert_contains('value="a@example.com"', $html, 'string value');
	});
}

// Check boxes still read an array value.
function test_liveform_prepare_checks_boxes_from_array()
{
	_test_liveform_with_session(function ($form) {
		$form->set('colors', array('red'));

		$html = $form->prepare(
			'<input type="checkbox" name="colors[]" value="red">'
			. '<input type="checkbox" name="colors[]" value="blue">');

		pg_assert_contains('value="red" checked>', $html, 'matching box');
		pg_assert_contains('value="blue">', $html, 'other box');
	});
}

// output_field() shows the first value of an array and nothing for an array
// without a plain first value.
function test_liveform_output_field_first_value()
{
	_test_liveform_with_session(function ($form) {
		$form->set('first_name', array('Ann', 'Bob'));
		pg_assert_contains('value="Ann"', $form->output_field(array('type' => 'text', 'name' => 'first_name')), 'first value');

		$form->set('first_name', array(array('Ann')));
		pg_assert_contains('value=""', $form->output_field(array('type' => 'text', 'name' => 'first_name')), 'nested array');

		$form->set('first_name', array('a' => 'Ann'));
		pg_assert_contains('value=""', $form->output_field(array('type' => 'hidden', 'name' => 'first_name')), 'no first key');
	});
}

// An input written without a type and a check box written without a value are
// prepared as before (a text field, a box that compares as '') without PHP
// notices.
function test_liveform_prepare_tag_without_type_or_value()
{
	_test_liveform_with_session(function ($form) {
		$form->set('nickname', 'Ann');
		$form->set('agree', '1');

		$html = $form->prepare(
			'<input name="nickname" value="">'
			. '<input type="checkbox" name="agree">');

		pg_assert_contains('<input name="nickname" value="Ann">', $html, 'input without type');
		pg_assert_contains('<input type="checkbox" name="agree">', $html, 'box without value');
	});
}

// A field given no id prints no id attribute (an empty id="" on several
// fields is a duplicate id); a field given one prints it.
function test_liveform_output_field_without_id()
{
	_test_liveform_with_session(function ($form) {
		$types = array('text', 'number', 'hidden', 'textarea', 'password', 'checkbox', 'radio', 'select');
		foreach ($types as $type) {
			$field = array('type' => $type, 'name' => 'f_' . $type);
			if ($type === 'select') $field['options'] = array('A' => 'a');
			$html = $form->output_field($field);
			pg_assert_false(strpos($html, ' id=') !== false, $type . ' without an id');

			$field['id'] = 'my_' . $type;
			pg_assert_contains(' id="my_' . $type . '"', $form->output_field($field), $type . ' with an id');
		}
	});
}

// The CAPTCHA answer box keeps the id the classic themes style; a second
// CAPTCHA on the page numbers its own, and the question is its label.
function test_liveform_captcha_answer_id()
{
	pg_assert_same('captcha_submitted_answer', pg_captcha_answer_id(1));
	pg_assert_same('captcha_submitted_answer_2', pg_captcha_answer_id(2));
	pg_assert_same('captcha_submitted_answer', pg_captcha_answer_id(0));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	pg_assert_contains('<label class="software_captcha_question" for="\' . h($answer_id) . \'">', $source, 'the question labels the box');
	pg_assert_contains("'id' => \$answer_id,", $source, 'the box carries the id');
}
