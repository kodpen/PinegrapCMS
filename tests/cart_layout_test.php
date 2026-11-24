<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic cart layout drawn by the shopping cart widget: the
 * row flags that put a row in today's or the recurring table and pick its
 * controls, the flags of the tables and totals around the rows, the totals
 * per payment period, the row's own visibility pass, the second place of
 * the rows, and the converted layout's buttons and boxes wired by the
 * widget's binding pass.
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

function _test_cart_item($over = array())
{
	return $over + array('selection_type' => 'quantity', 'added_by_offer' => 0, 'recurring' => 0, 'is_recurring' => 0,
		'image' => '', 'line_cents' => 1000, 'payment_period' => '', 'recurring_payment_period' => '');
}

// The flags a row reads, true ones only.
function _test_cart_on($flags)
{
	return array_keys(array_filter($flags));
}

function test_cart_row_flags_kinds()
{
	pg_assert_same(array('cart_item_today', 'cart_item_not_donation', 'cart_item_quantity_editable', 'cart_item_recurring_quantity_fixed', 'cart_item_recurring_amount'),
		_test_cart_on(pg_cart_row_flags(_test_cart_item())), 'a product charged today');
	pg_assert_same(array('cart_item_today', 'cart_item_recurring', 'cart_item_not_donation', 'cart_item_quantity_editable', 'cart_item_recurring_quantity_fixed', 'cart_item_recurring_amount'),
		_test_cart_on(pg_cart_row_flags(_test_cart_item(array('recurring' => 1)))), 'a subscription starting today: both tables, edited in the first');
	pg_assert_same(array('cart_item_recurring', 'cart_item_recurring_only', 'cart_item_donation', 'cart_item_quantity_editable', 'cart_item_recurring_quantity_editable', 'cart_item_recurring_donation'),
		_test_cart_on(pg_cart_row_flags(_test_cart_item(array('recurring' => 1, 'is_recurring' => 1, 'selection_type' => 'donation')))), 'a recurring donation charged later');
	pg_assert_same(array('cart_item_today', 'cart_item_not_donation', 'cart_item_added_by_offer', 'cart_item_recurring_quantity_fixed', 'cart_item_recurring_amount', 'cart_item_has_image', 'cart_item_out_of_stock', 'cart_item_has_calendar_event'),
		_test_cart_on(pg_cart_row_flags(_test_cart_item(array('added_by_offer' => 1, 'image' => '/a.jpg')),
			array('__item_out_of_stock_message' => 'Sold out', '__item_calendar_event_name' => 'Yoga', '__item_calendar_event_dates' => ''))),
		'an offer\'s product with a picture, out of stock, a reservation');
}

function test_cart_layout_flags()
{
	$items = array(_test_cart_item(array('selection_type' => 'donation')), _test_cart_item(array('recurring' => 1, 'is_recurring' => 1)));
	pg_assert_same(array('has_today_items', 'has_recurring_products', 'has_recurring_non_donations', 'has_discount', 'has_currency_code'),
		_test_cart_on(pg_cart_layout_flags($items, 250, false, 'USD')));
	pg_assert_same(array('has_special_offer_code'), _test_cart_on(pg_cart_layout_flags(array(), 0, true, ' ')), 'an empty cart');
}

function test_cart_payment_periods()
{
	$items = array(
		_test_cart_item(),
		_test_cart_item(array('recurring' => 1, 'payment_period' => 'Weekly', 'line_cents' => 500)),
		_test_cart_item(array('recurring' => 1, 'payment_period' => 'Weekly', 'recurring_payment_period' => 'Yearly', 'line_cents' => 700)),
		_test_cart_item(array('recurring' => 1, 'line_cents' => 300)),
		_test_cart_item(array('recurring' => 1, 'payment_period' => 'Weekly', 'line_cents' => 200)),
	);
	pg_assert_same(array('Weekly' => 700, 'Yearly' => 700, 'Monthly' => 300), pg_cart_payment_periods($items));
	pg_assert_same('', pg_cart_payment_periods_html(array()), 'no recurring product: nothing');
	$html = pg_cart_payment_periods_html(array('Weekly' => '&#8378;7,00'));
	pg_assert_contains('Weekly Subtotal:</th>', $html);
	pg_assert_contains('<td class="text-end text-nowrap">&#8378;7,00</td>', $html, 'the amount as formatted');
}

function test_cart_row_visibility_leaves_other_flags()
{
	$html = 'a<!--pg-vis:cart_item_today:4-->T<!--pg-vis:has_discount:5-->D<!--/pg-vis:5--><!--/pg-vis:4-->'
		. '<!--pg-vis:cart_item_donation:6-->X<!--/pg-vis:6-->b';
	pg_assert_same('aT<!--pg-vis:has_discount:5-->D<!--/pg-vis:5-->b',
		pg_cart_row_visibility($html, array('cart_item_today' => true, 'cart_item_donation' => false)));
	pg_assert_same($html, pg_cart_row_visibility($html, array()), 'no row flags: untouched');
}

function test_cart_extra_loops()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'table'), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'tr'), 'children' => array()))),
		)),
		array('type' => 'loop_area', 'props' => array('source' => 'cart.items'), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'table'), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'tr', 'cssClass' => 'r'), 'children' => array()))),
		)),
	));
	$extra = pg_cart_extra_loops($tree);
	pg_assert_same(1, count($extra), 'the second place; a list is not one');
	pg_assert_same('r', $extra[0][0]['props']['cssClass']);
	pg_assert_same('loop_area', $tree['children'][0]['children'][0]['type'], 'the first stays');
	pg_assert_same('<!--pg-cart-loop-0-->', $tree['children'][2]['children'][0]['props']['html']);
}

// The converted English cart through the widget's binding pass: its own
// buttons submit the cart form, the boxes are the widget's, the recurring
// totals its section.
function test_cart_converted_layout_bindings()
{
	$code = (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/layouts/248.php');
	$r = pg_layout_convert('shopping cart', $code, array('state' => pg_layout_convert_state('shopping cart', array())));
	$tree = $r['tree'];
	_apply_shopping_cart_bindings($tree, array('cart_form_id' => 'pg-cart-form-w9'));
	$html = '';
	foreach ($tree['children'] as $child) $html .= _render_tree_node($child, 0, 0);
	pg_assert_true((bool)preg_match('~<button[^>]*name="submit_update_cart"[^>]*form="pg-cart-form-w9"~', $html), 'update submits the cart form');
	pg_assert_true((bool)preg_match('~<button[^>]*name="submit_checkout"[^>]*form="pg-cart-form-w9"~', $html), 'checkout submits the cart form');
	pg_assert_same(4, substr_count($html, 'name="quantities[^^__item_id^^]"'), 'the quantity and amount boxes, both tables');
	pg_assert_contains('^^__cart_payment_periods_html^^', $html);
	pg_assert_contains('^^__pending_offers^^', $html);
	pg_assert_contains('^^__quick_add_form^^', $html);
	pg_assert_contains('^^__applied_offers^^', $html);
}

function test_cart_type_row_into_the_move()
{
	pg_assert_same(array('full_description' => true, 'multi_recipient' => false), pg_layout_convert_state('shopping cart', array()));
	pg_assert_same(array('full_description' => false, 'multi_recipient' => true),
		pg_layout_convert_state('shopping cart', array('product_description_type' => 'short_description', 'multi_recipient' => true)));
	$row = array('update_button_label' => 'Update Cart', 'checkout_button_label' => 'Checkout', 'next_page_id_with_shipping' => '144',
		'next_page_id_without_shipping' => '179', 'shopping_cart_label' => 'Cart', 'quick_add_label' => 'Quick Add Items',
		'quick_add_product_group_id' => '0', 'special_offer_code_label' => 'Offer Code', 'special_offer_code_message' => '');
	pg_assert_same(array('update_button_label' => 'Update Cart', 'checkout_button_label' => 'Checkout', 'shopping_cart_label' => 'Cart',
		'next_page_id_with_shipping' => 144, 'next_page_id_without_shipping' => 179,
		'special_offer_code_label' => 'Offer Code', 'special_offer_code_message' => ''), pg_layout_widget_seed('shopping cart', $row));
	$cfg = pg_layout_widget_seed('shopping cart', array('update_button_label' => '', 'quick_add_product_group_id' => '6', 'quick_add_label' => ''));
	pg_assert_same(array('quick_add_product_group_id' => 6, 'quick_add_label' => '', 'special_offer_code_label' => '', 'special_offer_code_message' => ''), $cfg,
		'empty words keep the widget\'s own; a quick add group brings its label');
}
