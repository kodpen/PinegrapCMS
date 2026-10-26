<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shop's side of a marketplace account
 * (includes/api/outbound/product_matches.php): which products each filter
 * holds, the quantity a matched product is sent with, and where its listing
 * stands.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/product_matches.php');

function test_marketplace_product_matches_filters()
{
	pg_assert_same(" AND (marketplace_product_map.id IS NOT NULL)", mp_matches_filter_sql('matched'));
	pg_assert_same(" AND (marketplace_product_map.id IS NULL)", mp_matches_filter_sql('unmatched'));
	pg_assert_same(" AND (marketplace_product_map.status = 'error')", mp_matches_filter_sql('errors'));
	pg_assert_same('', mp_matches_filter_sql('all'));
	pg_assert_same('', mp_matches_filter_sql("x' OR 1=1"), 'an unknown filter adds nothing');
}

function test_marketplace_product_matches_quantity_sent()
{
	$product = array('inventory' => 1, 'inventory_quantity' => 7, 'enabled' => 1, 'out_of_stock' => 0);

	pg_assert_same(7, mp_matches_quantity($product));
	pg_assert_same(0, mp_matches_quantity(array_merge($product, array('inventory' => 0))), 'not tracked is sent as zero');
	pg_assert_same(0, mp_matches_quantity(array_merge($product, array('enabled' => 0))), 'switched off is sent as zero');
	pg_assert_same(0, mp_matches_quantity(array_merge($product, array('out_of_stock' => 1))), 'flagged out of stock is sent as zero');
	pg_assert_same(0, mp_matches_quantity(array_merge($product, array('inventory_quantity' => -3))), 'never below zero');
}

function test_marketplace_product_matches_listing_state()
{
	$rejected = 'Rejected by Trendyol: ';

	pg_assert_same('on', mp_matches_listing_state(array('remote_id' => '123', 'map_status' => 'active', 'map_error' => ''), $rejected));
	pg_assert_same('rejected', mp_matches_listing_state(array('remote_id' => '', 'map_status' => 'error', 'map_error' => $rejected . 'blurry image'), $rejected));
	pg_assert_same('error', mp_matches_listing_state(array('remote_id' => '', 'map_status' => 'error', 'map_error' => 'stock refused'), $rejected));
	pg_assert_same('waiting', mp_matches_listing_state(array('remote_id' => '', 'map_status' => 'active', 'map_error' => ''), $rejected));
	pg_assert_same('', mp_matches_listing_state(array('remote_id' => null, 'map_status' => null, 'map_error' => null), $rejected), 'not matched');
}

function test_marketplace_product_matches_selected_ids()
{
	pg_assert_same(array(3, 5), mp_matches_ids(array('3', 'x', 5, -2, '5', array(9), '0')));
	pg_assert_same(array(), mp_matches_ids('7'), 'a single value is not a list');
}

// escape() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function product_matches_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function product_matches_test_body($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/product_matches.php');
	$start = strpos($source, "\nfunction " . $function . '(');
	$end = strpos($source, "\nfunction ", $start + 10);

	return (string) substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_marketplace_product_matches_a_deleted_holder_does_not_keep_its_code()
{
	$body = product_matches_test_body('mp_matches_set_code');
	$clear = strpos($body, 'mp_matches_clear_stale($account_id, $code, false)');
	$check = strpos($body, '$other = db_item(');

	pg_assert_true($clear !== false, 'the deleted products\' matches on the code are cleared');
	pg_assert_true(($check !== false) && ($clear < $check), 'before another holder is looked for');
	pg_assert_contains('which is in the Recycle Bin', $body, 'a binned holder is named as such');
}

function test_marketplace_product_matches_stale_sql()
{
	product_matches_test_db_stub();

	$sql = mp_matches_stale_sql(7, false);

	pg_assert_contains("(marketplace_product_map.account_id = '7')", $sql);
	pg_assert_contains('(products.id IS NULL)', $sql, 'a deleted product\'s match');
	pg_assert_false(strpos($sql, 'OR NOT') !== false, 'without $binned, a binned product\'s match is kept');
}

function test_marketplace_product_matches_send_needs_an_active_account()
{
	pg_assert_same(0, mp_matches_send(array('id' => 3, 'provider' => 'n11', 'status' => 'paused'), array(1, 2)),
		'a paused account queues nothing');

	$body = product_matches_test_body('mp_matches_send');

	pg_assert_false(strpos($body, 'mp_enqueue(') !== false, 'this account only, not every account the product is on');
	pg_assert_contains("(status = 'active')", $body, 'only an active match is sent');
}
