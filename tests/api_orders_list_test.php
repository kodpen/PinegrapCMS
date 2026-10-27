<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /orders (includes/api/resources/orders.php): the status
 * filter of several values, the newest-first sort, the modified_since
 * listing, the cursor mark that keeps a cursor to the listing it was made
 * for, the page SQL of each listing and modified_at on the Order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

// orders.php pulls in the outbound webhook module, whose gate asks for
// init.php; behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(__DIR__ . '/helpers/source_reader.php');

if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

function api_orders_list_test_route()
{
	foreach (api_schema() as $route) {
		if ($route['id'] === 'orders.list') {
			return $route;
		}
	}

	return null;
}

function api_orders_list_test_param($name)
{
	foreach (api_orders_list_test_route()['params'] as $param) {
		if ($param['name'] === $name) {
			return $param;
		}
	}

	return null;
}

function test_api_orders_status_filter_takes_a_comma_list()
{
	pg_assert_same(array('complete'), api_orders_status_filter('complete'), 'one status works as before');
	pg_assert_same(array('complete', 'exported'), api_orders_status_filter('complete,exported'), 'two statuses');
	pg_assert_same(array('complete', 'exported'), api_orders_status_filter(' complete , exported '), 'spaces around the values are dropped');
	pg_assert_same(array('complete', 'exported'), api_orders_status_filter('complete,exported,complete'), 'a status named twice is asked once');
	pg_assert_same(array('incomplete', 'complete', 'exported', 'cancelled'), api_orders_status_filter('incomplete,complete,exported,cancelled'), 'all four');
}

function test_api_orders_status_filter_refuses_an_unknown_value()
{
	pg_assert_same(null, api_orders_status_filter('complete,shipped'), 'a value that is no status refuses the whole filter');
	pg_assert_same(null, api_orders_status_filter('Complete'), 'the spelling is the column\'s, case included');
	pg_assert_same(null, api_orders_status_filter('canceled'), 'one L is not the column\'s spelling');
	pg_assert_same(null, api_orders_status_filter('complete,,exported'), 'an empty value is refused, not skipped');
	pg_assert_same(null, api_orders_status_filter(','), 'only commas');
}

function test_api_orders_list_order_follows_sort_and_modified_since()
{
	pg_assert_same('placed', api_orders_list_order(array()), 'oldest placed first by default');
	pg_assert_same('placed', api_orders_list_order(array('sort' => 'placed')), 'placed asked for');
	pg_assert_same('placed_desc', api_orders_list_order(array('sort' => 'placed_desc')), 'newest first');
	pg_assert_same('modified', api_orders_list_order(array('modified_since' => 1793088000)), 'modified_since walks the last change');
}

function test_api_orders_cursor_carries_its_listing()
{
	$marks = array(api_orders_cursor_mark('placed'), api_orders_cursor_mark('placed_desc'), api_orders_cursor_mark('modified'));

	pg_assert_same(3, count(array_unique($marks)), 'each listing has its own mark');

	foreach (array('placed', 'placed_desc', 'modified') as $order) {
		pg_assert_same('eyJ2IjoxLCJpIjoyfQ', api_orders_cursor_parts(api_orders_cursor_mark($order) . 'eyJ2IjoxLCJpIjoyfQ', $order), $order . ': its own cursor reads back');
	}

	pg_assert_same(null, api_orders_cursor_parts(api_orders_cursor_mark('placed_desc') . 'eyJ2IjoxLCJpIjoyfQ', 'placed'), 'a newest-first cursor under the oldest-first sort');
	pg_assert_same(null, api_orders_cursor_parts(api_orders_cursor_mark('placed') . 'eyJ2IjoxLCJpIjoyfQ', 'placed_desc'), 'an oldest-first cursor under the newest-first sort');
	pg_assert_same(null, api_orders_cursor_parts(api_orders_cursor_mark('modified') . 'eyJ2IjoxLCJpIjoyfQ', 'placed'), 'a modified_since cursor without the filter');
	pg_assert_same(null, api_orders_cursor_parts(api_orders_cursor_mark('placed') . 'eyJ2IjoxLCJpIjoyfQ', 'modified'), 'a placed cursor under modified_since');

	// A cursor handed out before the mark existed is an oldest-first one.
	pg_assert_same('eyJ2IjoxLCJpIjoyfQ', api_orders_cursor_parts('eyJ2IjoxLCJpIjoyfQ', 'placed'), 'an unmarked cursor still walks the default sort');
	pg_assert_same(null, api_orders_cursor_parts('eyJ2IjoxLCJpIjoyfQ', 'placed_desc'), 'an unmarked cursor is no newest-first one');
	pg_assert_same(null, api_orders_cursor_parts('eyJ2IjoxLCJpIjoyfQ', 'modified'), 'an unmarked cursor is no modified_since one');
}

function test_api_orders_list_sql_oldest_first()
{
	$sql = api_orders_list_sql(array(), 'placed', null, 50, array());

	pg_assert_true(strpos($sql, 'ORDER BY orders.order_date ASC, orders.id ASC') !== false, 'oldest first');
	pg_assert_true(strpos($sql, 'LIMIT 51') !== false, 'one row past the page tells whether there is more');
	pg_assert_true(strpos($sql, 'WHERE') === false, 'no filter, no WHERE');

	$sql = api_orders_list_sql(array(), 'placed', array('v' => 100, 'i' => 7), 50, array());

	pg_assert_true(strpos($sql, "(orders.order_date > '100' OR (orders.order_date = '100' AND orders.id > '7'))") !== false, 'the cursor walks forward');
}

function test_api_orders_list_sql_newest_first()
{
	$sql = api_orders_list_sql(array("orders.status IN ('complete', 'exported')"), 'placed_desc', array('v' => 100, 'i' => 7), 20, array());

	pg_assert_true(strpos($sql, 'ORDER BY orders.order_date DESC, orders.id DESC') !== false, 'newest first');
	pg_assert_true(strpos($sql, "(orders.order_date < '100' OR (orders.order_date = '100' AND orders.id < '7'))") !== false, 'the cursor walks backward in time');
	pg_assert_true(strpos($sql, "WHERE orders.status IN ('complete', 'exported') AND (orders.order_date <") !== false, 'the filter and the cursor together');
	pg_assert_true(strpos($sql, 'LIMIT 21') !== false, 'the page size');
}

function test_api_orders_list_sql_by_last_change()
{
	$sql = api_orders_list_sql(array(), 'modified', array('v' => 1793088845, 'i' => 812), 50, array());

	pg_assert_true(strpos($sql, 'ORDER BY orders.last_modified_timestamp ASC, orders.id ASC') !== false, 'oldest change first');
	pg_assert_true(strpos($sql, "(orders.last_modified_timestamp > '1793088845' OR (orders.last_modified_timestamp = '1793088845' AND orders.id > '812'))") !== false, 'the cursor walks the stamp and the id');
	pg_assert_same('last_modified_timestamp', api_orders_cursor_column('modified'), 'the next cursor is taken from the stamp');
	pg_assert_same('order_date', api_orders_cursor_column('placed_desc'), 'and from the order date otherwise');
}

function test_api_orders_list_where()
{
	pg_assert_same(array(), api_orders_list_where(array(), null), 'nothing asked, nothing filtered');

	pg_assert_same(
		array("orders.status IN ('complete', 'exported')"),
		api_orders_list_where(array(), array('complete', 'exported')),
		'the status set');

	pg_assert_same(
		array("orders.status IN ('cancelled')"),
		api_orders_list_where(array(), array('cancelled')),
		'one status');

	pg_assert_same(
		array("orders.last_modified_timestamp >= '1793088000'"),
		api_orders_list_where(array('modified_since' => 1793088000), null),
		'changed at or after the moment');

	pg_assert_same(
		array("orders.order_date >= '10'", "orders.order_date <= '20'"),
		api_orders_list_where(array('updated_since' => 10, 'date_to' => 20), null),
		'updated_since and date_to still compare the order date');
}

function test_api_orders_list_handler_counts_and_reads_marked_cursors()
{
	$source = pg_test_function_source('api_orders_list');

	pg_assert_true(strpos($source, 'api_orders_cursor_parts(') !== false, 'the handler reads the cursor through its mark');
	pg_assert_true(strpos($source, 'api_orders_cursor_mark(') !== false, 'and marks the next one');
	pg_assert_true(strpos($source, 'api_orders_status_filter(') !== false, 'the status is a set');
	pg_assert_true(preg_match('/COUNT\(\*\) FROM orders"\s*\.\s*\(empty\(\$where\)/', $source) === 1, 'the count takes the same filters, without the cursor');
}

function test_api_orders_list_route_declares_the_new_parameters()
{
	$status = api_orders_list_test_param('status');

	pg_assert_same('string', $status['type'], 'status is a comma list, checked by the handler');
	pg_assert_true(!empty($status['description']), 'status says it takes several values');

	$sort = api_orders_list_test_param('sort');

	pg_assert_same('enum', $sort['type'], 'sort is an enum');
	pg_assert_same(array('placed', 'placed_desc'), $sort['values'], 'the two sorts');
	pg_assert_true(!isset($sort['default']), 'no default, so sort sent beside modified_since is seen');

	$since = api_orders_list_test_param('modified_since');

	pg_assert_same('datetime', $since['type'], 'modified_since is a moment');
}

function test_api_order_present_carries_the_last_change()
{
	$row = array(
		'id' => '812', 'order_number' => '10812', 'order_date' => '1793023380', 'status' => 'cancelled',
		'last_modified_timestamp' => '1793088845',
		'contact_id' => '0', 'member_id' => null,
		'billing_first_name' => '', 'billing_last_name' => '', 'billing_email_address' => '',
		'billing_company' => '', 'billing_address_1' => '', 'billing_address_2' => '',
		'billing_city' => '', 'billing_state' => '', 'billing_zip_code' => '', 'billing_country' => '',
		'billing_phone_number' => '', 'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'shipping' => 0,
		'surcharge' => 0, 'total' => 0, 'payment_method' => '', 'transaction_id' => '', 'special_offer_code' => ''
	);

	$order = api_order_present($row);

	pg_assert_same('2026-10-27T08:14:05Z', $order['modified_at'], 'ISO-8601 UTC');
	pg_assert_same(1793088845, $order['modified_at_unix'], 'and unix');

	$row['last_modified_timestamp'] = '0';
	$order = api_order_present($row);

	pg_assert_same(null, $order['modified_at'], 'null for a zero stamp');
	pg_assert_same(0, $order['modified_at_unix'], 'zero stays zero');

	$schema = api_order_schema();

	pg_assert_same('string?', $schema['modified_at'], 'declared nullable');
	pg_assert_same('integer', $schema['modified_at_unix'], 'declared integer');
	pg_assert_true(strpos(api_order_select(array()), 'orders.last_modified_timestamp') !== false, 'the select reads the stamp');
}
