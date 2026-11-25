<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic express order layout drawn by the express order
 * widget: the flags of its tables, totals and payment part, a row's values
 * the classic page printed, the item's parts each in its own value, the
 * empty pick lists filled, and the type row's settings the converter reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function _test_eol_item($over = array())
{
	return $over + array('selection_type' => 'quantity', 'added_by_offer' => 0, 'recurring' => 0, 'is_recurring' => 0, 'image' => '');
}

function test_eol_layout_flags_totals()
{
	$items = array(_test_eol_item());
	$f = pg_eo_layout_flags($items, array('total' => 1000));
	pg_assert_false($f['has_subtotal_line'], 'nothing between subtotal and total');
	pg_assert_true($f['has_payment']);
	pg_assert_true($f['has_no_recurring_products']);
	pg_assert_true($f['has_today_items']);
	pg_assert_false($f['has_special_offer_code']);
	foreach (array('discount', 'tax', 'shipping', 'gift_card') as $line) {
		pg_assert_true(pg_eo_layout_flags($items, array($line => 1))['has_subtotal_line'], $line . ' comes between');
	}
	pg_assert_false(pg_eo_layout_flags($items, array('total' => 0))['has_payment'], 'nothing to pay');
	$f = pg_eo_layout_flags(array(_test_eol_item(array('recurring' => 1, 'is_recurring' => 1))), array('offer_code' => true, 'currency_code' => 'EUR'));
	pg_assert_false($f['has_no_recurring_products']);
	pg_assert_false($f['has_today_items'], 'first charged later');
	pg_assert_true($f['has_special_offer_code']);
	pg_assert_true($f['has_currency_code']);
}

function test_eol_item_layout_values()
{
	$v = pg_eo_item_layout_values(array('recurring' => 1, 'payment_period' => 'Weekly', 'recurring_payment_period' => ''));
	pg_assert_same(h(lang('Weekly')), $v['^^__item_recurring_period^^'], 'the product\'s period');
	$v = pg_eo_item_layout_values(array('recurring' => 1, 'payment_period' => 'Weekly', 'recurring_payment_period' => 'Yearly'));
	pg_assert_same(h(lang('Yearly')), $v['^^__item_recurring_period^^'], 'the customer\'s period wins');
	pg_assert_same(h(lang('Monthly')), pg_eo_item_layout_values(array('recurring' => 1))['^^__item_recurring_period^^'], 'Monthly when none is set');
	pg_assert_same('', pg_eo_item_layout_values(array('recurring' => 0, 'payment_period' => 'Weekly'))['^^__item_recurring_period^^'], 'not recurring');

	$stock = array('inventory' => 1, 'backorder' => 0, 'inventory_quantity' => 0, 'out_of_stock_message' => '<p>Sold out</p>');
	pg_assert_contains('Sold out', pg_eo_item_layout_values($stock)['^^__item_out_of_stock_message^^']);
	pg_assert_same('', pg_eo_item_layout_values(array('backorder' => 1) + $stock)['^^__item_out_of_stock_message^^'], 'a backorder is taken');
	pg_assert_same('', pg_eo_item_layout_values(array('inventory_quantity' => 2) + $stock)['^^__item_out_of_stock_message^^'], 'still in stock');

	$v = pg_eo_item_layout_values(array(), array('name' => 'Yoga & tea', 'date_and_time_range' => '<b>12 May</b>'));
	pg_assert_same('Yoga &amp; tea', $v['^^__item_calendar_event_name^^']);
	pg_assert_same('<b>12 May</b>', $v['^^__item_calendar_event_dates^^']);
	$flags = pg_cart_row_flags(_test_eol_item(), array('__item_calendar_event_name' => $v['^^__item_calendar_event_name^^']));
	pg_assert_true($flags['cart_item_has_calendar_event']);
}

function test_eol_fill_empty_selects()
{
	$sel = function ($attrs, $bindings = array(), $kids = array()) {
		$props = array('tag' => 'select', '_attrs' => $attrs);
		if ($bindings) $props['_bindings'] = $bindings;
		return array('type' => 'semantic', 'props' => $props, 'children' => $kids);
	};
	$opt = array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Kept'), 'children' => array());
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		$sel(array(), array('eo_field' => 'billing_salutation')),
		$sel(array(array('name' => 'name', 'value' => 'referral_source'))),
		$sel(array(array('name' => 'name', 'value' => 'referral_source')), array(), array($opt)),
		$sel(array(array('name' => 'name', 'value' => 'other'))),
	));
	pg_eo_fill_empty_selects($tree, array('billing_salutation' => array('Mr.' => 'Mr.', 'Ms.' => 'Ms.'), 'referral_source' => array('' => '', 'Radio' => 'r1')), array('billing_salutation' => 'Ms.'));
	$texts = function ($n) { return array_map(function ($o) { return $o['props']['text']; }, $n['children']); };
	pg_assert_same(array('Mr.', 'Ms.'), $texts($tree['children'][0]), 'by its binding');
	pg_assert_same(array(array('name' => 'value', 'value' => 'Ms.'), array('name' => 'selected', 'value' => '')), $tree['children'][0]['children'][1]['props']['_attrs'], 'the visitor\'s value selected');
	pg_assert_same(array('', 'Radio'), $texts($tree['children'][1]), 'by its name');
	pg_assert_same(array('Kept'), $texts($tree['children'][2]), 'a list with options is left as it is');
	pg_assert_same(array(), $tree['children'][3]['children'], 'a list nothing is given for');
}

function test_eol_convert_state()
{
	$s = pg_layout_convert_state('express order', array('product_description_type' => 'short_description', 'quick_add_product_group_id' => 3,
		'form' => 1, 'po_number' => 1, 'custom_field_1_label' => ' Tax ID ', 'custom_field_1_required' => 1, 'multi_recipient' => true));
	pg_assert_false($s['full_description']);
	pg_assert_true($s['quick_add']);
	pg_assert_true($s['billing_form']);
	pg_assert_true($s['po_number']);
	pg_assert_true($s['custom_field_1']);
	pg_assert_same('Tax ID', $s['custom_field_1_label']);
	pg_assert_true($s['custom_field_1_required']);
	pg_assert_false($s['custom_field_2']);
	pg_assert_true($s['multi_recipient']);
	$none = pg_layout_convert_state('express order', array());
	pg_assert_true($none['full_description'], 'the full description by default');
	pg_assert_false($none['quick_add'] || $none['billing_form'] || $none['po_number']);
}
