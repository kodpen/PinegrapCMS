<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order history ownership check (pg_order_view_owner_matches()): a
 * visitor who is not signed in finds no account row, and a guest order
 * belongs to account 0, so the loose comparison the endpoints made
 * ("0" != null is false) let any visitor delete, retrieve, reorder or view
 * a guest order by its number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_history_owner_guest_order_without_account()
{
	pg_assert_same(false, pg_order_view_owner_matches('0', null), 'not signed in: a guest order is not theirs');
	pg_assert_same(false, pg_order_view_owner_matches('0', ''), 'empty account id');
	pg_assert_same(false, pg_order_view_owner_matches('0', '0'), 'account 0 owns nothing');
	pg_assert_same(false, pg_order_view_owner_matches(0, 0), 'account 0 owns nothing, as integers');
}

function test_order_history_owner_own_order()
{
	pg_assert_same(true, pg_order_view_owner_matches('12', '12'), 'the account that placed the order');
	pg_assert_same(true, pg_order_view_owner_matches(12, '12'), 'integer and string');
}

function test_order_history_owner_other_account()
{
	pg_assert_same(false, pg_order_view_owner_matches('12', '13'), 'another account');
	pg_assert_same(false, pg_order_view_owner_matches('12', null), 'not signed in');
	pg_assert_same(false, pg_order_view_owner_matches(null, '12'), 'no order row');
}

function test_order_history_owner_endpoints_use_it()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	foreach (array('order_history_delete_order.php', 'order_history_retrieve_order.php', 'order_history_reorder.php', 'get_view_order_screen_content.php') as $file) {
		$source = (string) file_get_contents($root . $file);
		pg_assert_contains('pg_order_view_owner_matches($order_user_id, $user_id)', $source, $file . ' uses the strict check');
		pg_assert_same(false, strpos($source, '$order_user_id != $user_id') !== false, $file . ' has no loose comparison left');
	}
}
