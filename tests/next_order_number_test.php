<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Next Order Number of the Commerce settings screen: which
 * typed values are refused (pg_next_order_number_refusal() in
 * includes/fn/order_completion.php), and that the save writes the counter
 * only when the field was changed, so a save of another field never sets
 * back a counter that orders moved since the screen was opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');

// Above the last order number: accepted, leading zeros and spaces included.
function test_next_order_number_above_last_is_accepted()
{
	pg_assert_same('', pg_next_order_number_refusal('1213', 1212));
	pg_assert_same('', pg_next_order_number_refusal(' 5000 ', 1212));
	pg_assert_same('', pg_next_order_number_refusal('1', 0), 'a store without orders may start at 1');
	pg_assert_same('', pg_next_order_number_refusal('000001300', 1212));
}

// Empty, zero, at or below the last order, a non-number, a sign, a decimal
// or more digits than the column and PHP's integer hold: refused.
function test_next_order_number_refused_values()
{
	foreach (array('', '0', '1212', '1000', 'abc', '-5', '+1300', '1300.5', '1,300', '1e5', '1234567890123456789') as $posted) {
		pg_assert_true(pg_next_order_number_refusal($posted, 1212) !== '', 'refused: ' . $posted);
	}

	pg_assert_contains('1212', pg_next_order_number_refusal('5', 1212), 'the message names the last order number');
}

// The save compares the posted value with the one the screen was opened
// with, refuses before the config UPDATE, and no longer deletes the row.
function test_next_order_number_save_writes_only_a_change()
{
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.save.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');

	pg_assert_contains('name="ecommerce_next_order_number_was"', $screen, 'the opened value comes back');
	pg_assert_contains('min="1" step="1"', $screen);
	pg_assert_contains("\$_POST['ecommerce_next_order_number_was']", $save);
	pg_assert_contains('if ($next_order_number_posted !== $next_order_number_was)', $save);
	pg_assert_true(strpos($save, 'pg_next_order_number_refusal(') < strpos($save, 'if ($liveform->check_form_errors())'), 'checked before the write is stopped');
	pg_assert_same(false, strpos($save, 'DELETE FROM next_order_number'), 'the counter row is updated in place');
}

// Under the lock: a counter still where the screen found it is the operator's
// to set; one that orders moved meanwhile is never set back below itself.
function test_next_order_number_not_set_back_under_the_lock()
{
	pg_assert_same(false, pg_next_order_number_set_back('101', '100', 100), 'unchanged counter: raised');
	pg_assert_same(false, pg_next_order_number_set_back('90', '100', 100), 'unchanged counter: the operator may lower it');
	pg_assert_same(true, pg_next_order_number_set_back('101', '100', 102), 'orders took 100 and 101 meanwhile: 101 would be given twice');
	pg_assert_same(false, pg_next_order_number_set_back('102', '100', 102), 'at the moved counter: nothing given twice');
	pg_assert_same(false, pg_next_order_number_set_back('500', '100', 102));
	pg_assert_same(true, pg_next_order_number_set_back('101', null, 102), 'no opened value: never below the counter');
	pg_assert_same(false, pg_next_order_number_set_back('5', '', null), 'no counter row yet');
}

// The save reads the counter again between the lock and the write, and only
// warns after the unlock (no other table may be queried under the lock).
function test_next_order_number_save_rechecks_under_the_lock()
{
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.save.php');
	$lock = strpos($save, 'LOCK TABLES next_order_number WRITE');
	$read = strpos($save, 'SELECT next_order_number FROM next_order_number', (int) $lock);
	$check = strpos($save, 'pg_next_order_number_set_back(', (int) $lock);
	$write = strpos($save, 'UPDATE next_order_number SET', (int) $lock);
	$unlock = strpos($save, 'UNLOCK TABLES', (int) $lock);
	$warn = strpos($save, 'add_warning(', (int) $lock);

	pg_assert_true(($lock !== false) && ($read !== false) && ($check !== false) && ($write !== false) && ($unlock !== false) && ($warn !== false));
	pg_assert_true(($lock < $read) && ($read < $check) && ($check < $write) && ($write < $unlock) && ($unlock < $warn), 'lock, read, check, write, unlock, then the warning');
}
