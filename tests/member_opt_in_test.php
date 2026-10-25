<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_member_tree_asks_opt_in() in includes/fn/auth.php: whether a
 * registration or membership widget's tree shows the marketing question,
 * which decides whether a sign-up from that widget records the answer in
 * consent_log.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A designer control: a semantic node with its HTML attributes and, when
// given, the address-book column it is bound to.
function member_opt_in_test_control($tag, $attrs, $contact_field = '')
{
	$list = array();

	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}

	$props = array('tag' => $tag, '_attrs' => $list);

	if ($contact_field !== '') {
		$props['_cf'] = array('contact_field' => $contact_field);
	}

	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

// A form tree with the given controls nested one wrapper deep, the way the
// shipped templates put a control and its label in a div.
function member_opt_in_test_tree($controls)
{
	$wrappers = array();

	foreach ($controls as $control) {
		$wrappers[] = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($control));
	}

	return array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => $wrappers);
}

// The shipped templates' consent checkbox, among the other controls.
function test_member_opt_in_checkbox_asks()
{
	$tree = member_opt_in_test_tree(array(
		member_opt_in_test_control('input', array('type' => 'email', 'name' => 'email')),
		member_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'opt_in', 'value' => '1')),
		member_opt_in_test_control('button', array('type' => 'submit', 'name' => 'submit_register')),
	));

	pg_assert_true(pg_member_tree_asks_opt_in($tree));
}

// A select or a radio named opt_in is a question too.
function test_member_opt_in_select_and_radio_ask()
{
	pg_assert_true(pg_member_tree_asks_opt_in(member_opt_in_test_tree(array(
		member_opt_in_test_control('select', array('name' => 'opt_in')),
	))), 'select');
	pg_assert_true(pg_member_tree_asks_opt_in(member_opt_in_test_tree(array(
		member_opt_in_test_control('input', array('type' => 'radio', 'name' => 'opt_in', 'value' => '1')),
	))), 'radio');
}

// A tree without the control: the box was removed in the designer.
function test_member_opt_in_missing_control_does_not_ask()
{
	$tree = member_opt_in_test_tree(array(
		member_opt_in_test_control('input', array('type' => 'email', 'name' => 'email')),
		member_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'register_remember_me', 'value' => '1')),
	));

	pg_assert_false(pg_member_tree_asks_opt_in($tree), 'other controls only');
	pg_assert_false(pg_member_tree_asks_opt_in(array()), 'empty tree');
}

// Not a question shown to the visitor: a hidden input, a button or a submit
// input of that name, or a node the renderer does not treat as a control.
function test_member_opt_in_hidden_or_button_does_not_ask()
{
	pg_assert_false(pg_member_tree_asks_opt_in(member_opt_in_test_tree(array(
		member_opt_in_test_control('input', array('type' => 'hidden', 'name' => 'opt_in', 'value' => '1')),
	))), 'hidden input');
	pg_assert_false(pg_member_tree_asks_opt_in(member_opt_in_test_tree(array(
		member_opt_in_test_control('button', array('name' => 'opt_in', 'value' => '1')),
	))), 'button');
	pg_assert_false(pg_member_tree_asks_opt_in(member_opt_in_test_tree(array(
		member_opt_in_test_control('input', array('type' => 'submit', 'name' => 'opt_in', 'value' => '1')),
	))), 'submit input');

	$text_node = member_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'opt_in'));
	$text_node['type'] = 'text';
	pg_assert_false(pg_member_tree_asks_opt_in(member_opt_in_test_tree(array($text_node))), 'not a semantic node');
}

// The sign-up reads the answer from the name opt_in only, so a control bound
// to the opt_in column under another name asks nothing it records.
function test_member_opt_in_bound_under_other_name_does_not_ask()
{
	$tree = member_opt_in_test_tree(array(
		member_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'newsletter', 'value' => '1'), 'opt_in'),
	));

	pg_assert_false(pg_member_tree_asks_opt_in($tree));
}
