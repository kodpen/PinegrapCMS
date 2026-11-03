<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the store credit model (2026.11.0, 11.0.155,
 * includes/fn/store_credit.php): which cards cover an amount, what points
 * are worth, the expiry date the checkout reads, the issue checks and the
 * upgrade step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function store_credit_test_card($id, $balance, $expires_at)
{
	return array('id' => $id, 'code' => 'C' . $id, 'balance' => $balance, 'expires_at' => $expires_at);
}

function test_store_credit_pick_spends_the_soonest_expiry_first()
{
	$now = 1000000;
	$cards = array(
		store_credit_test_card(1, 500, 0),
		store_credit_test_card(2, 300, $now + 900),
		store_credit_test_card(3, 200, $now + 100),
	);

	pg_assert_same(array(
		array('id' => 3, 'code' => 'C3', 'amount' => 200),
		array('id' => 2, 'code' => 'C2', 'amount' => 300),
		array('id' => 1, 'code' => 'C1', 'amount' => 250),
	), pg_credit_pick($cards, 750, $now), 'dated cards first, the never-expiring one last and only in part');

	pg_assert_same(array(
		array('id' => 3, 'code' => 'C3', 'amount' => 150),
	), pg_credit_pick($cards, 150, $now), 'one card is enough');
}

function test_store_credit_pick_orders_equal_expiry_by_id()
{
	$cards = array(store_credit_test_card(9, 100, 0), store_credit_test_card(4, 100, 0));

	pg_assert_same(array(4, 9), array_column(pg_credit_pick($cards, 200, 10), 'id'), 'the older card first');
}

function test_store_credit_pick_never_takes_a_voided_or_expired_card()
{
	$now = 5000;
	$cards = array(
		store_credit_test_card(1, 0, 0),           // voided or spent
		store_credit_test_card(2, 400, $now),      // expired this second
		store_credit_test_card(3, 400, $now - 1),  // expired
		store_credit_test_card(4, 100, $now + 1),
	);

	pg_assert_same(array(
		array('id' => 4, 'code' => 'C4', 'amount' => 100),
	), pg_credit_pick($cards, 1000, $now), 'only the open card, taken in full when short');

	pg_assert_same(array(), pg_credit_pick($cards, 0, $now), 'nothing to cover, nothing picked');
	pg_assert_same(array(), pg_credit_pick(array(), 500, $now), 'no cards');
}

function test_store_credit_points_value_rounds_at_the_smallest_unit()
{
	pg_assert_same(100, pg_credit_points_value(100, 100), '100 points at 100 a unit are 1,00');
	pg_assert_same(33, pg_credit_points_value(1, 3), 'a third of a unit is 0,33');
	pg_assert_same(67, pg_credit_points_value(2, 3), 'two thirds round up to 0,67');
	pg_assert_same(1, pg_credit_points_value(1, 200), 'half a minor unit rounds up');
	pg_assert_same(0, pg_credit_points_value(1, 201), 'under half a minor unit is nothing');
	pg_assert_same(250, pg_credit_points_value(5, 2), 'two points a unit');
}

function test_store_credit_points_value_without_a_rate_or_under_the_smallest()
{
	pg_assert_same(0, pg_credit_points_value(1000, 0), 'no rate set: nothing converts');
	pg_assert_same(0, pg_credit_points_value(1000, -5), 'a negative rate converts nothing');
	pg_assert_same(0, pg_credit_points_value(-10, 100), 'negative points are worth nothing');
	pg_assert_same(0, pg_credit_points_value(499, 100, 500), 'under the smallest conversion');
	pg_assert_same(500, pg_credit_points_value(500, 100, 500), 'the smallest conversion itself');
}

function test_store_credit_expiration_date_is_the_last_valid_day()
{
	$saved = date_default_timezone_get();
	date_default_timezone_set('UTC');

	pg_assert_same('0000-00-00', pg_credit_expiration_date(0), 'never expires');
	pg_assert_same('2026-11-30', pg_credit_expiration_date(gmmktime(0, 0, 0, 12, 1, 2026)), 'midnight belongs to the day before');
	pg_assert_same('2026-12-01', pg_credit_expiration_date(gmmktime(15, 0, 0, 12, 1, 2026)), 'a time in the day keeps that day');

	date_default_timezone_set($saved);
}

function test_store_credit_issue_check()
{
	$now = 1000;

	pg_assert_same('', pg_credit_issue_check(7, 1250, 'manual', 0, $now), 'a staff credit with no expiry');
	pg_assert_same('', pg_credit_issue_check(7, 1, 'points', $now + 1, $now), 'a points credit with a future expiry');
	pg_assert_same('user', pg_credit_issue_check(0, 1250, 'refund', 0, $now), 'credit needs a customer');
	pg_assert_same('amount', pg_credit_issue_check(7, 0, 'refund', 0, $now), 'nothing to give');
	pg_assert_same('amount', pg_credit_issue_check(7, 12.5, 'refund', 0, $now), 'a float is not minor units');
	pg_assert_same('amount', pg_credit_issue_check(7, '1250', 'refund', 0, $now), 'a string is not minor units');
	pg_assert_same('kind', pg_credit_issue_check(7, 1250, 'gift', 0, $now), 'gift is a plain card, not credit');
	pg_assert_same('kind', pg_credit_issue_check(7, 1250, 'bonus', 0, $now), 'an unknown kind');
	pg_assert_same('expires', pg_credit_issue_check(7, 1250, 'manual', $now, $now), 'already expired');
}

function test_store_credit_kinds_match_the_column()
{
	pg_assert_same(array('gift', 'points', 'refund', 'manual'), array_keys(pg_credit_kinds()), 'the ENUM values in order');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($source, "ENUM('gift','points','refund','manual') NOT NULL DEFAULT 'gift'") !== false, 'the step adds the same kinds');
}

function test_store_credit_audit_label_hides_the_code()
{
	pg_assert_same('************3456', pg_credit_audit_label('1234567890123456'), 'only the last four digits');
	pg_assert_same('12', pg_credit_audit_label('12'), 'a short code is kept');
}

function test_store_credit_open_sql_reads_only_open_cards()
{
	pg_assert_true(strpos(pg_credit_open_sql('5abc', 99), 'user_id = 5 AND balance > 0 AND (expires_at = 0 OR expires_at > 99)') === 0, 'integers only');
}

function test_store_credit_upgrade_step_is_called_and_idempotent()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($source, "\tupgrade_2026_11_0_store_credit();") !== false, 'the open version calls the step');

	$start = strpos($source, "\nfunction upgrade_2026_11_0_store_credit()");
	pg_assert_true($start !== false, 'the step exists');
	$body = substr($source, $start, 1500);

	foreach (array('user_id', 'kind', 'reason', 'issued_by', 'expires_at') as $column) {
		pg_assert_true(strpos($body, "install_add_column('gift_cards', '" . $column . "'") !== false, $column . ' through install_add_column()');
	}

	pg_assert_true(strpos($body, "install_add_index('gift_cards', 'idx_user'") !== false, 'the user index');
	pg_assert_true(strpos($body, 'db(') === false, 'no direct schema SQL');
}

function test_store_credit_card_usable_only_by_its_customer()
{
	pg_assert_true(pg_credit_card_usable_by(0, 0), 'a plain gift card for a guest');
	pg_assert_true(pg_credit_card_usable_by(0, 12), 'a plain gift card for anyone signed in');
	pg_assert_true(pg_credit_card_usable_by(12, 12), 'credit for its own customer');
	pg_assert_true(!pg_credit_card_usable_by(12, 0), 'credit never for a guest or a ghost order');
	pg_assert_true(!pg_credit_card_usable_by(12, 13), 'credit never for another customer');
	pg_assert_true(pg_credit_card_usable_by('12', '12'), 'database strings compare as ids');
}

function test_store_credit_typed_code_paths_check_the_owner()
{
	foreach (array('express_order.php', 'order_preview.php', 'submit_order.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, 'pg_credit_owner_select_sql()') !== false, $file . ' reads the owner');
		pg_assert_true(strpos($source, "pg_credit_card_usable_by(\$gift_card['user_id'], pg_credit_checkout_user())") !== false, $file . ' refuses another customer\'s credit');
	}
}

function test_store_credit_pick_honours_an_edited_expiration_date()
{
	$saved = date_default_timezone_get();
	date_default_timezone_set('UTC');
	$now = gmmktime(12, 0, 0, 11, 3, 2026);

	$cards = array(
		array('id' => 1, 'code' => 'C1', 'balance' => 100, 'expires_at' => 0, 'expiration_date' => '2026-11-02'),
		array('id' => 2, 'code' => 'C2', 'balance' => 100, 'expires_at' => 0, 'expiration_date' => '2026-11-03'),
		array('id' => 3, 'code' => 'C3', 'balance' => 100, 'expires_at' => 0, 'expiration_date' => '0000-00-00'),
	);

	pg_assert_same(array(2, 3), array_column(pg_credit_pick($cards, 500, $now), 'id'), 'a card the checkout refuses is not picked; its last day still counts');
	pg_assert_true(strpos(pg_credit_open_sql(1, $now), "expiration_date >= '2026-11-03'") !== false, 'the balance query reads the same date');

	date_default_timezone_set($saved);
}
