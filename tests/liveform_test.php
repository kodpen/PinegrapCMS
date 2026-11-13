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
		$types = array('text', 'number', 'hidden', 'textarea', 'password', 'file', 'checkbox', 'radio', 'select');
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

// output_field() writes every Inputmask option it is given, so a money box
// drawn through liveform takes the panel language's separators; an empty
// thousands separator is written too (no grouping, not the alias default).
function test_liveform_forwards_inputmask_options()
{
	_test_liveform_with_session(function ($form) {
		$html = $form->output_field(array('type' => 'text', 'name' => 'balance', 'id' => 'balance', 'value' => '1234,56')
			+ pg_money_mask_attributes(array('decimal' => ',', 'thousands' => '.')));

		pg_assert_contains(' data-inputmask-alias="currency"', $html, 'the alias');
		pg_assert_contains(' data-inputmask-radixPoint=","', $html, 'the decimal comma');
		pg_assert_contains(' data-inputmask-groupSeparator="."', $html, 'the thousands dot');
		pg_assert_contains(' data-inputmask-digits="2"', $html, 'two decimals');
		pg_assert_contains(' data-inputmask-digitsOptional="false"', $html, 'always shown');
		pg_assert_same(1, substr_count($html, 'data-inputmask-alias='), 'the alias written once');

		$plain = $form->output_field(array('type' => 'text', 'name' => 'rate')
			+ pg_money_mask_attributes(array('decimal' => ',', 'thousands' => '')));

		pg_assert_contains(' data-inputmask-groupSeparator=""', $plain, 'no grouping');

		$escaped = $form->output_field(array('type' => 'text', 'name' => 'x', 'data-inputmask-mask' => '"><b>'));

		pg_assert_contains(' data-inputmask-mask="&quot;&gt;&lt;b&gt;"', $escaped, 'the value escaped');
	});
}

// A field given an aria-label prints it, escaped; one without prints none, so
// the ERP line fields that pass one get a name and site forms do not change.
function test_liveform_output_field_aria_label()
{
	_test_liveform_with_session(function ($form) {
		$types = array('text', 'number', 'textarea', 'password', 'checkbox', 'radio', 'select');
		foreach ($types as $type) {
			$field = array('type' => $type, 'name' => 'f_' . $type);
			if ($type === 'select') $field['options'] = array('A' => 'a');
			pg_assert_false(strpos($form->output_field($field), 'aria-label') !== false, $type . ' without an aria-label');

			$field['aria-label'] = 'Unit "price" <b>';
			$html = $form->output_field($field);
			pg_assert_contains(' aria-label="Unit &quot;price&quot; &lt;b&gt;"', $html, $type . ' with an aria-label');
			pg_assert_same(1, substr_count($html, 'aria-label='), $type . ': written once');
		}

		pg_assert_false(strpos($form->output_field(array('type' => 'hidden', 'name' => 'h', 'aria-label' => 'x')), 'aria-label') !== false, 'hidden prints none');
	});
}

// A field given aria-describedby (the id of a hint printed under it) prints
// it, escaped; one without prints none.
function test_liveform_output_field_aria_describedby()
{
	_test_liveform_with_session(function ($form) {
		foreach (array('text', 'checkbox', 'select', 'textarea') as $type) {
			$field = array('type' => $type, 'name' => 'd_' . $type);
			if ($type === 'select') $field['options'] = array('A' => 'a');
			pg_assert_false(strpos($form->output_field($field), 'aria-describedby') !== false, $type . ' without one');

			$field['aria-describedby'] = 'x_hint"';
			pg_assert_contains(' aria-describedby="x_hint&quot;"', $form->output_field($field), $type . ' with one');
		}
	});
}

// A field given autocapitalize prints it, escaped, beside spellcheck; one
// without prints none, so site forms do not change.
function test_liveform_output_field_autocapitalize()
{
	_test_liveform_with_session(function ($form) {
		foreach (array('text', 'textarea') as $type) {
			$field = array('type' => $type, 'name' => 'c_' . $type);
			pg_assert_false(strpos($form->output_field($field), 'autocapitalize') !== false, $type . ' without one');

			$field['autocapitalize'] = 'characters"';
			$field['spellcheck'] = 'false';
			$html = $form->output_field($field);
			pg_assert_contains(' autocapitalize="characters&quot;"', $html, $type . ' with one');
			pg_assert_contains(' spellcheck="false"', $html, $type . ' keeps spellcheck');
			pg_assert_same(1, substr_count($html, 'autocapitalize='), $type . ': written once');
		}
	});
}

// A refused radio button or check box group prints its message once, after
// its last option label (field_feedback()); each option names it and prints
// none: printed after the box, it stood between a lone consent box and its
// label, and after every radio button. A field without feedback_id keeps
// its message right after the control.
function test_liveform_group_feedback_after_the_options()
{
	_test_liveform_with_session(function ($form) {
		$form->mark_error('7', 'Check this box to continue.');
		$form->mark_error('8', 'Size is required.');
		$form->mark_error('9', 'Colours is required.');

		$box = $form->output_field(array('type' => 'checkbox', 'name' => '7', 'id' => 'software_option_1', 'value' => 'Yes', 'feedback_id' => 'f7'));
		pg_assert_false(strpos($box, 'invalid-feedback') !== false, 'a lone box prints no message of its own');
		pg_assert_contains(' aria-invalid="true"', $box);
		pg_assert_contains(' aria-describedby="f7"', $box, 'it names the group message');
		pg_assert_same('<div id="f7" class="invalid-feedback d-block pg-inline-feedback" data-pg-source="liveform">Check this box to continue.</div>', $form->field_feedback('7', 'f7'), 'the message, printed once by the caller');

		foreach (array('a', 'b', 'c') as $value) {
			$radio = $form->output_field(array('type' => 'radio', 'name' => '8', 'id' => 'r_' . $value, 'value' => $value, 'feedback_id' => 'f8'));
			pg_assert_false(strpos($radio, 'invalid-feedback') !== false, 'radio ' . $value . ' prints no message');
			pg_assert_contains(' aria-describedby="f8"', $radio, 'radio ' . $value . ' names the one message');
		}

		$group = $form->output_field(array('type' => 'checkbox', 'name' => '9[]', 'id' => 'g1', 'value' => 'Red', 'feedback_id' => 'f9'));
		pg_assert_contains(' aria-invalid="true"', $group, 'a check box group is read by its name without []');
		pg_assert_contains(' aria-describedby="f9"', $group);
		pg_assert_contains('Colours is required.', $form->field_feedback('9', 'f9'));

		pg_assert_same('', $form->field_feedback('10', 'f10'), 'a field that passed has no message');

		$plain = $form->output_field(array('type' => 'checkbox', 'name' => '7', 'id' => 'software_option_1', 'value' => 'Yes'));
		pg_assert_contains('<div id="software_option_1-feedback" class="invalid-feedback d-block pg-inline-feedback" data-pg-source="liveform">Check this box to continue.</div>', $plain, 'without feedback_id the message follows the control as before');
		pg_assert_contains(' aria-describedby="software_option_1-feedback"', $plain);
	});
}

// The classic form rows (get_form_info(): custom forms, billing, shipping and
// product forms) print a radio button or check box group's message once,
// after the last option label.
function test_liveform_form_info_groups_print_one_message()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content.php');

	pg_assert_same(2, substr_count($source, "'feedback_id' => \$feedback_id"), 'radio buttons and check boxes name it');
	pg_assert_contains("\$output_options .= \$liveform->field_feedback(\$html_field_name, \$feedback_id);", $source, 'radio buttons print it after the options');
	pg_assert_contains("\$output_feedback = \$liveform->field_feedback(\$html_field_name, \$feedback_id);", $source, 'check boxes read it before the name takes []');
	pg_assert_contains("\$output_options .= \$output_feedback;", $source, 'and print it after the options');
}
