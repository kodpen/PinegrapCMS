<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the commerce lists printed whole on the list table driver
 * (includes/fn/commerce_list.php): orders, gift cards, key codes and
 * shipping methods. Their columns carry the screens' sort codes, their
 * quick edit columns are keyed by their edit fields, and each is one of the
 * printed lists whose sort the session keeps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_method_list_edit.php');

function commerce_list_driver_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_commerce_list_specs_follow_the_sort_menu_and_quick_edit()
{
	$specs = pg_commerce_list_specs(
		array('name' => array('heading' => 'Name'), 'zones' => array('heading' => 'Zones'), 'rate' => array('heading' => 'Rate', 'kind' => 'money')),
		array('name' => 'Name', 'rate' => 'Rate'),
		array('rate' => array('type' => 'money'), 'name' => array('type' => 'textarea')));

	pg_assert_false(isset($specs['name']['sortable']), 'a sort menu column keeps its sort');
	pg_assert_same(false, $specs['zones']['sortable'], 'a column the menu does not offer does not sort');
	pg_assert_same('money', $specs['rate']['editable'], 'a quick edit field gets its input');
	pg_assert_false(isset($specs['name']['editable']), 'an input the driver lacks is left out');
}

function test_commerce_lists_columns_name_their_record_first()
{
	$lists = array(
		'orders' => pg_order_list_columns(true, 'Member ID'),
		'gift cards' => pg_gift_card_list_columns(),
		'key codes' => pg_key_code_list_columns(),
		'shipping methods' => pg_shipping_method_list_columns(true),
	);

	foreach ($lists as $name => $specs) {
		$first = reset($specs);
		pg_assert_same('link', $first['kind'], $name . ': the first column is the record\'s link');
		pg_assert_same(count($specs), count(pg_list_table_columns($specs)), $name . ': every key is a valid column key');
	}

	pg_assert_false(isset(pg_order_list_columns(false, 'Member ID')['affiliate_code']), 'no affiliate code without the program');
	pg_assert_same('Member ID', pg_order_list_columns(false, 'Member ID')['member_id']['heading'], 'the member id keeps the site\'s label');
	$shipping = pg_shipping_method_list_columns(false);
	pg_assert_false(isset($shipping['service']) || isset($shipping['realtime_rate']), 'no service columns without a service');
}

function test_commerce_lists_quick_edit_columns_are_columns()
{
	$pairs = array(
		array(pg_order_list_columns(true, 'Member ID'), pg_order_quick_edit_columns(), 'select'),
		array(pg_gift_card_list_columns(), pg_gift_card_edit_columns(), 'text'),
		array(pg_key_code_list_columns(), pg_key_code_edit_columns(), 'select'),
		array(pg_shipping_method_list_columns(true), pg_shipping_method_edit_columns(), null),
	);

	foreach ($pairs as $pair) {
		$specs = pg_commerce_list_specs($pair[0], array(), $pair[1]);

		foreach (array_keys($pair[1]) as $field) {
			pg_assert_true(isset($specs[$field]), $field . ' is a column');
			// null: each field's own type, an integer drawn as a number box.
			$type = ($pair[2] !== null) ? $pair[2] : str_replace('integer', 'number', $pair[1][$field]['type']);
			pg_assert_same($type, $specs[$field]['editable'], $field . ' changes in place');
		}
	}
}

function test_commerce_lists_sort_codes_are_columns()
{
	// Every sort code a screen's menu offers is a column, so a heading
	// click and the menu keep the same sort (pg_list_table_printed_sort()).
	$screens = array(
		'view_orders.php' => pg_order_list_columns(true, 'Member ID'),
		'view_gift_cards.php' => pg_gift_card_list_columns(),
		'view_key_codes.php' => pg_key_code_list_columns(),
		'view_shipping_methods.php' => pg_shipping_method_list_columns(true),
	);

	foreach ($screens as $file => $specs) {
		$source = commerce_list_driver_test_source($file);
		$start = strpos($source, '$sort_columns = array(');
		$block = substr($source, $start, strpos($source, ');', $start) - $start);
		preg_match_all("/'([a-z_]+)'\s*=>/", $block, $matches);

		pg_assert_true(count($matches[1]) > 3, $file . ': the sort menu is read');

		foreach ($matches[1] as $code) {
			pg_assert_true(isset($specs[$code]), $file . ': sort code ' . $code . ' is a column');
		}

		pg_assert_contains('pg_list_table_printed(array(', $source, $file . ' prints its list whole');
		pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $source, $file . ' loads the driver');
	}

	foreach (array('view_orders', 'view_gift_cards', 'view_key_codes', 'view_shipping_methods') as $list) {
		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list . ' keeps a heading\'s sort');
	}
}

function test_orders_bulk_actions_post_the_selected_ids()
{
	$source = commerce_list_driver_test_source('view_orders.php');

	pg_assert_contains('input.name = "orders[]";', $source, 'the selection is posted as orders[]');
	pg_assert_contains('<span id="pg_orders_selected" hidden></span>', $source, 'inside the bulk form');
	pg_assert_false(strpos($source, 'name="orders[]" value=') !== false, 'no row checkbox is left');
	pg_assert_false(strpos($source, 'class="chart ') !== false, 'no DataTable is left');
}

function test_erp_lists_on_the_driver()
{
	$specs = pg_commerce_list_specs(array('min_quantity' => array('heading' => 'Minimum')), array('min_quantity' => 1), array('min_quantity' => array('type' => 'integer')));
	pg_assert_same('number', $specs['min_quantity']['editable'], 'a whole-number field gets a number input');

	foreach (array('erp_account_prices.php', 'erp_stock_minimums.php', 'erp_stock.php') as $file) {
		$source = commerce_list_driver_test_source($file);
		pg_assert_contains('pg_list_table_printed(array(', $source, $file . ' prints its list whole');
		pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $source, $file . ' loads the driver');
		pg_assert_false(strpos($source, 'class="chart ') !== false || strpos($source, 'data-pg-sort' . "'") !== false, $file . ': no DataTable or plain sorted table is left for the list');
	}

	// The quick edit answers name the product and stock cells by these keys.
	pg_assert_contains("'product' => pg_list_cell(\$cells['product']['html']", commerce_list_driver_test_source('erp_account_prices.php'));
	pg_assert_contains("'stock' => pg_list_cell(\$cells['stock']['html']", commerce_list_driver_test_source('erp_stock_minimums.php'));
	pg_assert_contains('input.name = "products[]";', commerce_list_driver_test_source('erp_stock_minimums.php'), 'the selection opens a purchase order');
}
