<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for store credit on the customer's screen
 * (includes/fn/store_credit_staff.php): the issue form's checks, where a
 * card stands and who may change credit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_store_credit_staff_issue_reads_the_amount_in_minor_units()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	$in = pg_credit_staff_issue_input('25', '', 'Late delivery', $now);
	pg_assert_same('', $in['error'], 'whole amount');
	pg_assert_same(2500, $in['amount'], 'minor units');
	pg_assert_same(0, $in['expires'], 'no last day never expires');
	pg_assert_same('Late delivery', $in['reason'], 'reason');

	pg_assert_same(1250, pg_credit_staff_issue_input('12,50', '', 'x', $now)['amount'], 'decimal comma');
	pg_assert_same(1, pg_credit_staff_issue_input('0.005', '', 'x', $now)['amount'], 'half a minor unit rounds up');
	pg_assert_same('amount', pg_credit_staff_issue_input('0', '', 'x', $now)['error'], 'zero');
	pg_assert_same('amount', pg_credit_staff_issue_input('-5', '', 'x', $now)['error'], 'negative');
	pg_assert_same('amount', pg_credit_staff_issue_input('abc', '', 'x', $now)['error'], 'not a number');
	pg_assert_same('amount', pg_credit_staff_issue_input(array('5'), '', 'x', $now)['error'], 'an array');
	pg_assert_same('amount', pg_credit_staff_issue_input('2000000', '', 'x', $now)['error'], 'above the most at once');
}

function test_store_credit_staff_issue_last_day_runs_to_its_end()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	$in = pg_credit_staff_issue_input('5', '2026-12-31', 'x', $now);
	pg_assert_same('', $in['error'], 'a future day');
	pg_assert_same(mktime(0, 0, 0, 1, 1, 2027), $in['expires'], 'stops at the start of the next day');
	pg_assert_same('2026-12-31', pg_credit_expiration_date($in['expires']), 'the gift card date is that day');

	pg_assert_same('', pg_credit_staff_issue_input('5', '2026-11-04', 'x', $now)['error'], 'today is still valid');
	pg_assert_same('date', pg_credit_staff_issue_input('5', '2026-11-03', 'x', $now)['error'], 'yesterday');
	pg_assert_same('date', pg_credit_staff_issue_input('5', '2026-02-30', 'x', $now)['error'], 'no such day');
	pg_assert_same('date', pg_credit_staff_issue_input('5', '31.12.2026', 'x', $now)['error'], 'another format');
}

function test_store_credit_staff_issue_needs_a_reason()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	pg_assert_same('reason', pg_credit_staff_issue_input('5', '', '   ', $now)['error'], 'blank');
	pg_assert_same('reason', pg_credit_staff_issue_input('5', '', array('x'), $now)['error'], 'an array');
	pg_assert_same(255, mb_strlen(pg_credit_staff_issue_input('5', '', str_repeat('é', 300), $now)['reason']), 'cut to the column');
}

function test_store_credit_staff_card_state()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	pg_assert_same('open', pg_credit_staff_card_state(array('balance' => 500, 'expires_at' => 0, 'expiration_date' => '0000-00-00'), $now), 'never expiring');
	pg_assert_same('used', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => 0, 'expiration_date' => '0000-00-00'), $now), 'spent or voided');
	pg_assert_same('expired', pg_credit_staff_card_state(array('balance' => 500, 'expires_at' => $now - 1, 'expiration_date' => '2026-11-03'), $now), 'past its end');
	pg_assert_same('expired', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => $now - 1, 'expiration_date' => '2026-11-03'), $now), 'closed by the expiry pass');
}

function test_store_credit_staff_rights_are_the_commerce_ones()
{
	pg_assert_true(pg_credit_staff_allowed(array('role' => 2)), 'manager');
	pg_assert_true(pg_credit_staff_allowed(array('role' => 3, 'manage_ecommerce' => 1)), 'user with commerce rights');
	pg_assert_false(pg_credit_staff_allowed(array('role' => 3, 'manage_ecommerce' => 0)), 'user without');
	pg_assert_false(pg_credit_staff_allowed(array()), 'nobody');
}
