<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order widget's accessible names: when the field
 * walker renames a recipient's shipping controls (shipping_first_name to
 * shipping_<rid>_first_name), the labels pointing at them follow; the
 * quantity box, the offer code box and the custom arrival date box get a
 * name when no label points at them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _eo_labels_test_node($tag, $attrs, $bindings = array(), $children = array())
{
	$props = array('tag' => $tag, '_attrs' => array());
	foreach ($attrs as $name => $value) {
		$props['_attrs'][] = array('name' => $name, 'value' => $value);
	}
	if ($bindings) $props['_bindings'] = $bindings;
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

function _eo_labels_test_attr($node, $name)
{
	foreach ($node['props']['_attrs'] as $a) {
		if ($a['name'] === $name) return $a['value'];
	}
	return null;
}

function _eo_labels_test_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		_eo_labels_test_node('label', array('for' => 'shipping_first_name')),
		_eo_labels_test_node('input', array('type' => 'text'), array('eo_field' => 'shipping_first_name')),
		_eo_labels_test_node('div', array(), array(), array(
			_eo_labels_test_node('label', array('for' => 'shipping_zip_code')),
		)),
		_eo_labels_test_node('input', array('type' => 'text'), array('eo_field' => 'shipping_zip_code')),
		_eo_labels_test_node('label', array('for' => 'billing_first_name')),
		_eo_labels_test_node('input', array('type' => 'text'), array('eo_field' => 'billing_first_name')),
		_eo_labels_test_node('label', array('for' => 'shipping_note')),
		_eo_labels_test_node('input', array('type' => 'text', 'id' => 'shipping_note', 'name' => 'shipping_note')),
		_eo_labels_test_node('input', array('type' => 'number', 'name' => 'quantity[^^__item_id^^]')),
		_eo_labels_test_node('input', array('type' => 'text', 'placeholder' => 'Enter your code if you have one'), array('eo_field' => 'special_offer_code')),
	));
}

function test_express_order_shipping_labels_follow_the_renamed_fields()
{
	$tree = _eo_labels_test_tree();
	_eo_apply_field_bindings($tree, null, 412);
	$kids = $tree['children'];

	pg_assert_same('shipping_412_first_name', $kids[1]['props']['id'], 'the field is renamed');
	pg_assert_same('shipping_412_first_name', _eo_labels_test_attr($kids[0], 'for'), 'its label follows');
	pg_assert_same('shipping_412_zip_code', _eo_labels_test_attr($kids[2]['children'][0], 'for'), 'a label before its field, nested');
	pg_assert_same('billing_first_name', _eo_labels_test_attr($kids[4], 'for'), 'a billing label stays');
	pg_assert_same('shipping_note', _eo_labels_test_attr($kids[6], 'for'), 'a label of an unbound control stays');
}

function test_express_order_labels_stay_without_a_recipient()
{
	$tree = _eo_labels_test_tree();
	_eo_apply_field_bindings($tree, null, 0);

	pg_assert_same('shipping_first_name', _eo_labels_test_attr($tree['children'][0], 'for'), 'no recipient, no rename');
	pg_assert_same('shipping_first_name', $tree['children'][1]['props']['id'], 'the field keeps its name');
}

function test_express_order_unlabelled_controls_get_a_name()
{
	$tree = _eo_labels_test_tree();
	_eo_apply_field_bindings($tree, null, 412);
	$kids = $tree['children'];

	pg_assert_same(lang('Quantity'), _eo_labels_test_attr($kids[8], 'aria-label'), 'quantity box');
	pg_assert_same(lang('Special Offer Code'), _eo_labels_test_attr($kids[9], 'aria-label'), 'offer code box');
	pg_assert_same(null, _eo_labels_test_attr($kids[1], 'aria-label'), 'a labelled field gets none');
}

function test_express_order_named_controls_keep_their_name()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_eo_labels_test_node('input', array('type' => 'number', 'name' => 'quantity[^^__item_id^^]', 'aria-label' => 'Adet')),
		_eo_labels_test_node('label', array('for' => 'special_offer_code')),
		_eo_labels_test_node('input', array('type' => 'text'), array('eo_field' => 'special_offer_code')),
		_eo_labels_test_node('input', array('type' => 'number', 'name' => 'quantity[^^__item_id^^]', 'aria-labelledby' => 'qty_head')),
	));
	_eo_apply_field_bindings($tree, null, 412);
	$kids = $tree['children'];

	pg_assert_same('Adet', _eo_labels_test_attr($kids[0], 'aria-label'), 'own aria-label kept');
	pg_assert_same(null, _eo_labels_test_attr($kids[2], 'aria-label'), 'a label points at it');
	pg_assert_same(null, _eo_labels_test_attr($kids[3], 'aria-label'), 'aria-labelledby');
}

function test_express_order_custom_arrival_box_is_named()
{
	$html = _eo_custom_arrival_input('shipping_412_custom_arrival_date_2', '2026-11-20', '', '2026-11-01', true, 'Talep Edilen Varış Tarihi');

	pg_assert_contains('aria-label="Talep Edilen Varış Tarihi"', $html, 'named after its arrival choice');
	pg_assert_contains('name="shipping_412_custom_arrival_date_2" value="2026-11-20" min="2026-11-01"', $html, 'name, value and bounds');
	pg_assert_true(strpos($html, ' disabled') === false, 'the chosen box is enabled');

	$hidden = _eo_custom_arrival_input('shipping_412_custom_arrival_date_3', '', '2026-12-31', '2026-11-01', false, '');
	pg_assert_contains('aria-label="' . h(lang('Requested Arrival Date')) . '"', $hidden, 'a choice without a name');
	pg_assert_contains(' max="2026-12-31" disabled', $hidden, 'a hidden box is disabled');
}

function test_express_order_cart_rows_name_the_quantity_box()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets_express_order.php');

	pg_assert_contains("' name=\"quantity[' . \$iid . ']\" value=\"' . \$qty . '\" form=\"' . h(\$form_id) . '\" aria-label=\"' . h(lang('Quantity')) . '\">'", $source, 'built-in cart rows');
}
