<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for taking back the private folder access an order granted
 * (includes/fn/order_completion.php): what a cancellation or a refunded
 * return does to the buyer's access row, the units a line still holds after
 * refunded returns, the calls in the cancel, restore and return refund
 * paths, and the bundle rule that refuses a product granting private access.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_folder_access_revoked_limited_access_loses_its_days()
{
	pg_assert_same(array('action' => 'update', 'expiration_date' => '2026-12-01'),
		pg_order_folder_access_revoked('1', '2027-01-30', 30, 2, '2026-11-06'), 'sixty days off the end');
	pg_assert_same(array('action' => 'update', 'expiration_date' => '2026-11-06'),
		pg_order_folder_access_revoked('1', '2026-12-06', 30, 1, '2026-11-06'), 'an end that is today stays');
	pg_assert_same(array('action' => 'delete', 'expiration_date' => ''),
		pg_order_folder_access_revoked('1', '2026-12-05', 30, 1, '2026-11-06'), 'an end in the past goes');
}

function test_order_folder_access_revoked_unlimited_access_goes()
{
	pg_assert_same(array('action' => 'delete', 'expiration_date' => ''),
		pg_order_folder_access_revoked('1', '', 0, 1, '2026-11-06'), 'access that never ends');
	pg_assert_same(array('action' => 'delete', 'expiration_date' => ''),
		pg_order_folder_access_revoked('1', '0000-00-00', 0, 3, '2026-11-06'), 'stored with the zero date');
}

function test_order_folder_access_revoked_keeps_what_the_purchase_did_not_write()
{
	$keep = array('action' => 'keep', 'expiration_date' => '');

	pg_assert_same($keep, pg_order_folder_access_revoked('', '', 30, 1, '2026-11-06'), 'no row');
	pg_assert_same($keep, pg_order_folder_access_revoked('2', '2026-12-06', 0, 1, '2026-11-06'), 'edit rights');
	pg_assert_same($keep, pg_order_folder_access_revoked('0', '', 0, 1, '2026-11-06'), 'no rights');
	pg_assert_same($keep, pg_order_folder_access_revoked('1', '0000-00-00', 30, 1, '2026-11-06'), 'never ends, limited purchase');
	pg_assert_same($keep, pg_order_folder_access_revoked('1', '2026-12-06', 30, 0, '2026-11-06'), 'no units left');
	pg_assert_same($keep, pg_order_folder_access_revoked('1', 'soon', 30, 1, '2026-11-06'), 'unreadable date');
}

function test_order_folder_access_held_takes_off_the_returned_units()
{
	$lines = array(
		array('order_item_id' => '7', 'quantity' => '3'),
		array('order_item_id' => '8', 'quantity' => '1'),
		array('order_item_id' => '9', 'quantity' => '2'),
	);

	$held = pg_order_folder_access_held($lines, array(7 => 1, 8 => 1, 9 => 5));

	pg_assert_same(2, $held[0]['quantity'], 'two of three kept');
	pg_assert_same(0, $held[1]['quantity'], 'all returned');
	pg_assert_same(0, $held[2]['quantity'], 'never below 0');
	pg_assert_same('7', $held[0]['order_item_id'], 'other fields kept');
}

function test_order_folder_access_called_on_cancel_restore_and_return_refund()
{
	$ecommerce = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	$returns = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/returns.php');

	pg_assert_same(1, substr_count($ecommerce, 'pg_order_folder_access_cancelled($order);'), 'cancellation takes it back');
	pg_assert_same(1, substr_count($ecommerce, 'pg_order_folder_access_restored($cancelled_order);'), 'restore grants it again');
	pg_assert_true(strpos($ecommerce, '"SELECT id, user_id, order_number"') !== false, 'restore reads the customer');
	pg_assert_same(1, substr_count($returns, 'pg_order_folder_access_lines_refunded($return[\'order_id\'], $returned_quantities);'), 'a refunded return takes it back');
}

function test_order_folder_access_bundle_refuses_a_private_access_component()
{
	pg_assert_true(pg_bundle_component_problem(array('grant_private_access' => '1')) !== '', 'private access refused');
	pg_assert_same('', pg_bundle_component_problem(array('grant_private_access' => '0')), 'no private access');
}

function test_order_folder_access_never_ending_stays_while_another_purchase_holds_it()
{
	$lines = array(
		array('order_id' => 1, 'order_item_id' => 10, 'quantity' => 1),
		array('order_id' => 2, 'order_item_id' => 20, 'quantity' => 2),
	);

	pg_assert_true(pg_order_folder_access_still_held($lines, array()), 'another order still holds the folder');
	pg_assert_true(pg_order_folder_access_still_held($lines, array(2 => array(20 => 2))), 'order 1 holds it though order 2 was returned');
	pg_assert_false(pg_order_folder_access_still_held($lines, array(1 => array(10 => 1), 2 => array(20 => 2))), 'every unit returned');
	pg_assert_false(pg_order_folder_access_still_held(array(), array()), 'no other purchase');
}

function test_order_folder_access_return_lines_count_the_order_s_other_lines_on_the_folder()
{
	// The order's lines with the units each still holds after the return.
	$lines = array(
		array('order_item_id' => 1, 'quantity' => 0, 'grant_private_access' => '1', 'private_folder' => '5', 'private_days' => '0'),
		array('order_item_id' => 2, 'quantity' => 1, 'grant_private_access' => '1', 'private_folder' => '5', 'private_days' => '0'),
		array('order_item_id' => 3, 'quantity' => 0, 'grant_private_access' => '1', 'private_folder' => '6', 'private_days' => '0'),
		array('order_item_id' => 4, 'quantity' => 2, 'grant_private_access' => '1', 'private_folder' => '7', 'private_days' => '30'),
	);

	$back = pg_order_folder_access_return_lines($lines, array(1 => 1, 3 => 1));

	pg_assert_same(2, count($back), 'only the returned lines');
	pg_assert_same(1, $back[0]['quantity'], 'the units taken back');
	pg_assert_same(1, $back[0]['held'], 'another line of the order still grants folder 5');
	pg_assert_same('5', $back[0]['private_folder'], 'line fields kept');
	pg_assert_same(0, $back[1]['held'], 'nothing else grants folder 6');

	$back = pg_order_folder_access_return_lines($lines, array(4 => 1));

	pg_assert_same(2, $back[0]['held'], 'a limited line counts its own held units');
	pg_assert_same(array(), pg_order_folder_access_return_lines($lines, array(9 => 1)), 'no line of the order');
}
