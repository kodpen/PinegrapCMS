<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the orders list's Customer, Payment, Shipping and Items columns
 * (includes/fn/order_list.php) and its default column set
 * (pg_order_list_columns()): the customer's name or company, the first line
 * with "+N", the addresses handed to View Order's pill function, the queries
 * that read the page's rows once each.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_list_columns_default_set()
{
	$specs = pg_order_list_columns(true, 'Member ID', true);
	$shown = array();

	foreach ($specs as $key => $spec) {
		if (empty($spec['hidden'])) {
			$shown[] = $key;
		}
	}

	pg_assert_same(array('order_number', 'order_date', 'customer', 'status', 'payment', 'shipping', 'items', 'total', 'actions'), $shown, 'the default set, in its order');

	foreach (array('first_name', 'last_name', 'type', 'user', 'tracking_code', 'member_id', 'affiliate_code', 'card_data', 'profit') as $key) {
		pg_assert_true(!empty($specs[$key]['hidden']), $key . ' stays in the chooser, off by default');
	}

	$keys = array_keys($specs);
	pg_assert_same('actions', end($keys), 'Action stays the last column');
}

function test_order_list_customer_text()
{
	pg_assert_same('Elif Kara', pg_order_list_customer_text(' Elif ', 'Kara', 'Kodpen Ltd.'), 'the name wins over the company');
	pg_assert_same('Elif', pg_order_list_customer_text('Elif', '', ''), 'a first name alone');
	pg_assert_same('Kodpen Ltd.', pg_order_list_customer_text('', ' ', ' Kodpen Ltd. '), 'the company when there is no name');
	pg_assert_same('', pg_order_list_customer_text(null, null, null), 'nothing when nothing is known');
}

function test_order_list_items_html()
{
	pg_assert_same('', pg_order_list_items_html(null), 'no line, an empty cell');
	pg_assert_same('Mocha &amp; Chair', pg_order_list_items_html(array('product_name' => '<p>Mocha & <b>Chair</b></p>', 'line_count' => 1)), 'one line: its product, tags stripped, escaped');

	$html = pg_order_list_items_html(array('product_name' => 'Green Chair', 'line_count' => '3'));
	pg_assert_contains('Green Chair', $html, 'the first line\'s product');
	pg_assert_contains(h(lang(array('string' => '+{var:1} more', 'vars' => array(2)))), $html, 'the other lines counted');
}

function test_order_list_addresses_feed_the_pill_function()
{
	$lines = array(
		array('order_id' => '5', 'ship_to_id' => '11', 'ship_date' => '2026-11-01', 'delivery_date' => '0000-00-00', 'quantity' => '1', 'show_shipped_quantity' => '0', 'shipped_quantity' => '0'),
		array('order_id' => '5', 'ship_to_id' => '12', 'ship_date' => null, 'delivery_date' => null, 'quantity' => '2', 'show_shipped_quantity' => '1', 'shipped_quantity' => '1'),
		array('order_id' => '6', 'ship_to_id' => '13', 'ship_date' => '0000-00-00', 'delivery_date' => '0000-00-00', 'quantity' => '1', 'show_shipped_quantity' => '1', 'shipped_quantity' => '0'),
		array('order_id' => '6', 'ship_to_id' => '0', 'ship_date' => null, 'delivery_date' => null, 'quantity' => '1', 'show_shipped_quantity' => '0', 'shipped_quantity' => '0'),
	);
	$parcels = array(
		array('ship_to_id' => '13', 'number' => '', 'status' => 'delivered'),
		array('ship_to_id' => '99', 'number' => 'X1', 'status' => 'delivered'),
	);

	$addresses = pg_order_list_addresses($lines, $parcels);

	pg_assert_same(array(5, 6), array_keys($addresses), 'one entry per order with an address');
	pg_assert_same(array(11, 12), array_keys($addresses[5]), 'each address of the order');
	pg_assert_same(array(13), array_keys($addresses[6]), 'a line with no address is left out');
	pg_assert_same(array(), $addresses[6][13]['parcels'], 'a parcel with no number is left out, as View Order leaves it out');
	pg_assert_same('', $addresses[5][12]['ship_date'], 'an address row that is gone reads as no date');

	$today = '2026-11-22';
	$states = pg_order_view_states(array('status' => 'complete', 'total' => 1000), $addresses[5], $today);
	pg_assert_same(lang('Partially shipped'), $states['shipping'][0], 'one address shipped, one begun');

	$parcels[] = array('ship_to_id' => '13', 'number' => 'Y2', 'status' => 'delivered');
	$addresses = pg_order_list_addresses($lines, $parcels);
	$states = pg_order_view_states(array('status' => 'complete', 'total' => 1000), $addresses[6], $today);
	pg_assert_same(lang('Delivered'), $states['shipping'][0], 'every parcel delivered');
}

function test_order_list_pill_html()
{
	pg_assert_same('', pg_order_list_pill_html(null), 'no pill, an empty cell');
	pg_assert_same(pg_pill('Paid', 'teal'), pg_order_list_pill_html(array('Paid', 'teal')), 'the head\'s pill');
}

function test_order_list_column_queries()
{
	pg_assert_same('', pg_order_list_items_sql(array()), 'no ids, no items query');
	pg_assert_same('', pg_order_list_ship_lines_sql(array(0)), 'no ids, no lines query');
	pg_assert_same('', pg_order_list_parcels_sql(array('x'), true), 'no ids, no parcels query');

	$sql = pg_order_list_items_sql(array(4, '4', 9), ' AND (order_items.saved_for_later = 0)');
	pg_assert_contains('IN (4, 9)', $sql, 'the ids once each');
	pg_assert_contains('MIN(order_items.id)', $sql, 'the first line');
	pg_assert_contains('saved_for_later = 0', $sql, 'lines saved for later stay out');
	pg_assert_false(stripos($sql, ' AS lines') !== false, 'no alias MySQL 8 reserves');
	pg_assert_contains('LEFT JOIN products ON products.id = order_items.product_id', $sql, 'the product of the first line, kept when it is deleted');
	pg_assert_contains("COALESCE(NULLIF(products.short_description, ''), order_items.product_name) AS product_name", $sql, 'the name people know, the SKU when there is none');

	$sql = pg_order_list_ship_lines_sql(array(4));
	pg_assert_contains('LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id', $sql, 'the address from the lines, as View Order reads it');
	pg_assert_contains('order_items.ship_to_id > 0', $sql, 'only lines with an address');

	pg_assert_contains('status', pg_order_list_parcels_sql(array(4), true), 'the parcel status when its column exists');
	pg_assert_contains("'' AS status", pg_order_list_parcels_sql(array(4), false), 'no status column before its step');
}
