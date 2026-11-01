<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for marking the controls of a designed form that failed a server
 * check (pg_form_mark_invalid_controls(), includes/fn/custom_form.php), the
 * liveform's list of failed fields (liveform::field_error_texts()) and the
 * id that ties a classic field's inline error text to the field.
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

// A semantic node with the given tag, attributes, id and children.
function _test_fic_node($tag, $attrs = array(), $id = '', $children = array())
{
	$list = array();
	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}
	$props = array('tag' => $tag, '_attrs' => $list);
	if ($id !== '') {
		$props['id'] = $id;
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

// A failed text field gets is-invalid, aria-invalid and a linked text after it.
function test_fic_marks_text_control()
{
	$tree = _test_fic_node('form', array(), '', array(
		_test_fic_node('label', array('for' => 'cf-name')),
		_test_fic_node('input', array('type' => 'text', 'name' => '12', 'aria-describedby' => 'hint'), 'cf-name'),
		_test_fic_node('div', array(), 'hint'),
	));
	$tree['children'][1]['props']['cssClass'] = 'form-control';

	pg_assert_same(1, pg_form_mark_invalid_controls($tree, array(12 => 'Name is required.')));

	$input = $tree['children'][1];
	$attrs = _pg_cf_node_attrs($input);
	pg_assert_same('form-control is-invalid', $input['props']['cssClass']);
	pg_assert_same('true', $attrs['aria-invalid']);
	pg_assert_same('hint cf-name-feedback', $attrs['aria-describedby'], 'existing hint kept');

	$feedback = $tree['children'][2];
	pg_assert_same('cf-name-feedback', $feedback['props']['id']);
	pg_assert_same('Name is required.', $feedback['props']['text']);
	pg_assert_contains('invalid-feedback', $feedback['props']['cssClass']);
	pg_assert_same('hint', $tree['children'][3]['props']['id'], 'later sibling moved, not lost');
}

// Untouched fields stay as drawn; no errors leave the tree as it was.
function test_fic_leaves_other_controls()
{
	$tree = _test_fic_node('form', array(), '', array(
		_test_fic_node('input', array('type' => 'text', 'name' => 'first_name'), 'a'),
		_test_fic_node('input', array('type' => 'hidden', 'name' => 'email')),
		_test_fic_node('input', array('type' => 'email', 'name' => 'email_ok'), 'b'),
	));
	$before = $tree;
	pg_assert_same(0, pg_form_mark_invalid_controls($tree, array()));
	pg_assert_same($before, $tree, 'no errors');

	pg_assert_same(0, pg_form_mark_invalid_controls($tree, array('email' => 'x')), 'hidden control is not marked');
	pg_assert_same($before, $tree);
}

// A check box group: every box is marked, one text after the last box's label.
function test_fic_checkbox_group_one_feedback()
{
	$box = function ($value, $id) {
		return _test_fic_node('div', array(), '', array(
			_test_fic_node('input', array('type' => 'checkbox', 'name' => '7[]', 'value' => $value), $id),
			_test_fic_node('label', array('for' => $id)),
		));
	};
	$tree = _test_fic_node('fieldset', array(), '', array($box('a', 'c1'), $box('b', 'c2')));

	pg_assert_same(1, pg_form_mark_invalid_controls($tree, array('7' => 'Choose one.')));

	$first = $tree['children'][0]['children'];
	$last  = $tree['children'][1]['children'];
	pg_assert_same(2, count($first), 'no text after the first box');
	pg_assert_same(3, count($last));
	pg_assert_same('label', $last[1]['props']['tag'], 'label stays beside its box');
	pg_assert_same('c1-feedback', $last[2]['props']['id'], 'id from the first box');
	pg_assert_same('c1-feedback', _pg_cf_node_attrs($first[0])['aria-describedby']);
	pg_assert_same('c1-feedback', _pg_cf_node_attrs($last[0])['aria-describedby']);
}

// Two fields in one parent: both texts land after their own control.
function test_fic_two_fields_same_parent()
{
	$tree = _test_fic_node('form', array(), '', array(
		_test_fic_node('input', array('name' => 'first_name'), 'fn'),
		_test_fic_node('input', array('name' => 'last_name'), 'ln'),
		_test_fic_node('textarea', array('name' => 'body'), 'bd'),
	));
	pg_assert_same(3, pg_form_mark_invalid_controls($tree, array('first_name' => 'A', 'last_name' => 'B', 'body' => '')));

	$ids = array();
	foreach ($tree['children'] as $c) $ids[] = $c['props']['id'];
	pg_assert_same(array('fn', 'fn-feedback', 'ln', 'ln-feedback', 'bd'), $ids);
	$body = _pg_cf_node_attrs($tree['children'][4]);
	pg_assert_same('true', $body['aria-invalid'], 'marked without a text');
	pg_assert_true(!isset($body['aria-describedby']), 'nothing to describe it');
}

// A control without an id gets a feedback id from its name.
function test_fic_control_without_id()
{
	$tree = _test_fic_node('form', array(), '', array(_test_fic_node('input', array('name' => 'pass word'))));
	pg_form_mark_invalid_controls($tree, array('pass word' => 'Too short.'));
	$fb_id = $tree['children'][1]['props']['id'];
	pg_assert_true((bool)preg_match('/^pg-field-pass-word-\d+-feedback$/', $fb_id), $fb_id);
	pg_assert_same($fb_id, _pg_cf_node_attrs($tree['children'][0])['aria-describedby']);
}

// The liveform lists failed fields with plain text, not the form-wide error.
function test_fic_liveform_field_error_texts()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();
	$form = new liveform('fic_test');
	$form->mark_error('email', '<b>E-mail</b> is required.');
	$form->mark_error('', 'Check your entries.');
	$form->set('first_name', 'Ann');
	$texts = $form->field_error_texts();

	$html = $form->output_field(array('type' => 'email', 'name' => 'email', 'id' => 'em'));

	if ($saved === null) unset($_SESSION); else $_SESSION = $saved;

	pg_assert_same(array('email' => 'E-mail is required.'), $texts);
	pg_assert_contains('aria-describedby="em-feedback"', $html);
	pg_assert_contains('<div id="em-feedback" class="invalid-feedback', $html);
}
