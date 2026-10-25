<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for _eo_tree_asks_opt_in() in includes/fn/widgets_express_order.php:
 * whether a designed express order still draws the e-mail marketing box,
 * which decides whether its form posts opt_in_displayed=true and so whether
 * the checkout answer is recorded in consent_log.
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
// given, the express order field it is bound to.
function express_order_opt_in_test_control($tag, $attrs, $eo_field = '')
{
	$list = array();

	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}

	$props = array('tag' => $tag, '_attrs' => $list);

	if ($eo_field !== '') {
		$props['_bindings'] = array('eo_field' => $eo_field);
	}

	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

// A tree with the given children inside a wrapper, which carries a
// visibility condition when one is given.
function express_order_opt_in_test_tree(array $children, $visible_if = '')
{
	$wrapper = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => $children);

	if ($visible_if !== '') {
		$wrapper['props']['_bindings'] = array('eo_visible_if' => $visible_if);
	}

	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($wrapper));
}

// The starter tree draws the box, bound through eo_field.
function test_express_order_opt_in_starter_tree_asks()
{
	pg_assert_true(_eo_tree_asks_opt_in(_eo_default_designer_tree()), 'starter tree');
}

// A tree without the box, or no tree at all, asks nothing.
function test_express_order_opt_in_tree_without_the_box_does_not_ask()
{
	$tree = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('input', array('type' => 'checkbox', 'value' => '1'), 'tax_exempt'),
		express_order_opt_in_test_control('input', array('type' => 'email'), 'billing_email_address'),
	));

	pg_assert_false(_eo_tree_asks_opt_in($tree), 'other fields only');
	pg_assert_false(_eo_tree_asks_opt_in(null), 'no tree');
	pg_assert_false(_eo_tree_asks_opt_in(array()), 'empty tree');
}

// A control named opt_in by its own attribute counts when it has no binding.
function test_express_order_opt_in_raw_name_asks()
{
	$tree = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'opt_in', 'value' => '1')),
	));

	pg_assert_true(_eo_tree_asks_opt_in($tree), 'raw name');
}

// The binding names the control at render, so it wins over the attribute.
function test_express_order_opt_in_binding_wins_over_the_name_attribute()
{
	$bound = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'newsletter'), 'opt_in'),
	));
	$renamed = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('input', array('type' => 'checkbox', 'name' => 'opt_in'), 'tax_exempt'),
	));

	pg_assert_true(_eo_tree_asks_opt_in($bound), 'bound to opt_in');
	pg_assert_false(_eo_tree_asks_opt_in($renamed), 'bound to another field');
}

// A hidden input or a button named opt_in shows no question.
function test_express_order_opt_in_hidden_input_and_button_do_not_ask()
{
	$hidden = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('input', array('type' => 'hidden', 'name' => 'opt_in', 'value' => '1')),
	));
	$button = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('button', array('type' => 'submit', 'name' => 'opt_in', 'value' => '1')),
	));
	$submit = express_order_opt_in_test_tree(array(
		express_order_opt_in_test_control('input', array('type' => 'submit'), 'opt_in'),
	));

	pg_assert_false(_eo_tree_asks_opt_in($hidden), 'hidden input');
	pg_assert_false(_eo_tree_asks_opt_in($button), 'button');
	pg_assert_false(_eo_tree_asks_opt_in($submit), 'submit input');
}

// A box in a container the visibility flags drop is not drawn.
function test_express_order_opt_in_dropped_container_does_not_ask()
{
	$box = express_order_opt_in_test_control('input', array('type' => 'checkbox', 'value' => '1'), 'opt_in');

	$dropped = express_order_opt_in_test_tree(array($box), 'has_shipping');
	_eo_apply_visibility_bindings($dropped, array('has_shipping' => false));
	pg_assert_false(_eo_tree_asks_opt_in($dropped), 'container dropped');

	$kept = express_order_opt_in_test_tree(array($box), 'has_shipping');
	_eo_apply_visibility_bindings($kept, array('has_shipping' => true));
	pg_assert_true(_eo_tree_asks_opt_in($kept), 'container kept');
}
