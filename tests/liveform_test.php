<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for liveform field values that arrive as arrays (?name[]=x): a field
 * that holds one value keeps its markup instead of stopping the page.
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
