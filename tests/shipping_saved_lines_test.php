<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the checkout's recipient shipping screens: the line
 * queries of shipping_method.php (the cost of the picked method) and
 * shipping_address_and_arrival.php (the forced methods when none fits every
 * line) leave a saved-for-later line out with pg_order_item_active_sql(),
 * as update_shipping_cost_for_ship_to() in shipping.php does. The queries
 * need a database, so the source is read. Then the public API's list values
 * reaching get_shipping_methods() in shipping.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every "FROM order_items" query of a file, up to the end of its db_items() call.
function shipping_saved_lines_test_queries($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	preg_match_all('/db_items\(\s*"SELECT.*?FROM order_items.*?"\);/s', $source, $matches);

	return $matches[0];
}

function test_shipping_method_cost_leaves_saved_lines_out()
{
	$queries = shipping_saved_lines_test_queries('shipping_method.php');

	pg_assert_same(1, count($queries), 'one line query');
	pg_assert_true(strpos($queries[0], 'pg_order_item_active_sql(') !== false, 'a saved line is charged no shipping');
}

function test_shipping_address_forced_methods_leave_saved_lines_out()
{
	$queries = shipping_saved_lines_test_queries('shipping_address_and_arrival.php');

	pg_assert_same(1, count($queries), 'one line query');
	pg_assert_true(strpos($queries[0], 'pg_order_item_active_sql(') !== false, 'a saved line does not choose the forced methods');
}

// The public API hands get_shipping_methods() its request body as it came: a
// field sent as a list is empty, not a TypeError from trim(). Without an
// order in the session the call ends before any query.
function test_shipping_methods_api_list_values_are_empty()
{
	require_once PG_FUNCTIONS_DIR . '/shipping.php';
	$session = $_SESSION ?? null;
	$_SESSION = array();

	try {
		$response = get_shipping_methods(array('ship_to_id' => array(1), 'address_1' => array('x'), 'zip_code' => array(1), 'country' => array('TR'), 'state' => array(), 'arrival_date' => array()));
	} catch (TypeError $error) {
		$response = array('status' => 'TypeError');
	}

	$_SESSION = $session;
	pg_assert_same('error', $response['status'], 'the missing order is answered, no TypeError');
}
