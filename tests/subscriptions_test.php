<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of store-run subscriptions
 * (includes/fn/subscriptions.php) and the recurring part of the payment
 * driver contract (includes/payments/driver.php): period arithmetic at month
 * ends and in leap years, the 1/3/7-day retry ladder, the status moves that
 * are allowed and refused, which events the unique key de-duplicates, and
 * that a card token is never kept unsealed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A sealed value in the shape encrypt_string_with_iv() gives: ciphertext and
// a 16-byte IV, both base64, joined by a colon.
function subscriptions_test_sealed()
{
	return base64_encode(str_repeat('c', 32)) . ':' . base64_encode(str_repeat('i', 16));
}

function subscriptions_test_line(array $over = array())
{
	return array_merge(array(
		'order' => array('id' => '501', 'user_id' => '17'),
		'item'  => array('id' => '9001', 'product_id' => '44', 'price' => '14990', 'quantity' => '1', 'tax_total' => '2698'),
	), $over);
}

function test_subscription_period_month_end_keeps_the_anchor_day()
{
	$jan31 = mktime(10, 30, 0, 1, 31, 2027);
	$feb28 = pg_subscription_next_charge_at($jan31, 'monthly', 31);

	pg_assert_same(date('Y-m-d H:i', mktime(10, 30, 0, 2, 28, 2027)), date('Y-m-d H:i', $feb28), 'January 31 renews on the last day of February');
	pg_assert_same('2027-03-31', date('Y-m-d', pg_subscription_next_charge_at($feb28, 'monthly', 31)), 'the anchor day comes back in March');
	pg_assert_same('2027-04-30', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 3, 31, 2027), 'monthly', 31)));
	pg_assert_same('2027-02-15', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 1, 15, 2027), 'monthly', 15)));
}

function test_subscription_period_leap_years()
{
	pg_assert_same('2028-02-29', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 1, 31, 2028), 'monthly', 31)), 'a leap February has 29 days');
	pg_assert_same('2029-02-28', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 2, 29, 2028), 'yearly', 29)), 'February 29 renews on the 28th in a common year');
	pg_assert_same('2032-02-29', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 2, 28, 2031), 'yearly', 29)), 'and on the 29th again in the next leap year');
	pg_assert_same('2028-08-31', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 2, 29, 2028), 'twice_yearly', 31)));
	pg_assert_same('2028-05-31', date('Y-m-d', pg_subscription_next_charge_at(mktime(9, 0, 0, 2, 29, 2028), 'quarterly', 31)));
}

function test_subscription_period_day_based_and_unknown()
{
	$from = mktime(12, 0, 0, 2, 25, 2028);

	pg_assert_same('2028-03-03', date('Y-m-d', pg_subscription_next_charge_at($from, 'weekly')));
	pg_assert_same('2028-03-10', date('Y-m-d', pg_subscription_next_charge_at($from, 'every_two_weeks')));
	pg_assert_same('2028-03-11', date('Y-m-d', pg_subscription_next_charge_at($from, 'twice_monthly')));
	pg_assert_same('2028-03-24', date('Y-m-d', pg_subscription_next_charge_at($from, 'every_four_weeks')));
	pg_assert_same(0, pg_subscription_next_charge_at($from, 'fortnightly'), 'an unknown period gives no date');
	pg_assert_same(0, pg_subscription_next_charge_at(0, 'monthly'), 'no start, no date');
}

function test_subscription_period_from_legacy_names()
{
	pg_assert_same('monthly', pg_subscription_period_from_legacy('Monthly'));
	pg_assert_same('twice_yearly', pg_subscription_period_from_legacy('Twice every Year'));
	pg_assert_same('twice_monthly', pg_subscription_period_from_legacy('Twice every Month'));
	pg_assert_same('', pg_subscription_period_from_legacy('Daily'));

	foreach (array_keys(pg_subscription_periods()) as $period) {
		pg_assert_true(strlen($period) <= 16, $period . ' fits subscriptions.period');
	}
}

function test_subscription_retry_ladder_is_one_three_seven_days()
{
	pg_assert_same(1, pg_subscription_retry_delay_days(1), 'first failure: a day later');
	pg_assert_same(3, pg_subscription_retry_delay_days(2));
	pg_assert_same(7, pg_subscription_retry_delay_days(3));
	pg_assert_same(0, pg_subscription_retry_delay_days(4), 'the ladder ends after the third retry');
	pg_assert_same(0, pg_subscription_retry_delay_days(0), 'no failure, no retry');

	$failed = mktime(3, 0, 0, 2, 27, 2028);
	pg_assert_same('2028-03-01 03:00', date('Y-m-d H:i', pg_subscription_retry_at($failed, 2)), 'three days across a leap day');
	pg_assert_same(0, pg_subscription_retry_at($failed, 4));
}

function test_subscription_transitions_allowed_and_refused()
{
	$allowed = array(
		array('active', 'past_due'), array('active', 'paused'), array('active', 'cancelled'), array('active', 'ended'),
		array('past_due', 'active'), array('past_due', 'paused'), array('past_due', 'cancelled'), array('past_due', 'ended'),
		array('paused', 'active'), array('paused', 'cancelled'), array('paused', 'ended'),
		array('cancelled', 'ended'),
	);

	foreach ($allowed as $move) {
		pg_assert_true(pg_subscription_transition_allowed($move[0], $move[1]), $move[0] . ' -> ' . $move[1]);
	}

	$refused = array(
		array('paused', 'past_due'), array('cancelled', 'active'), array('cancelled', 'past_due'),
		array('ended', 'active'), array('ended', 'cancelled'), array('active', 'active'),
		array('active', 'deleted'), array('', 'active'),
	);

	foreach ($refused as $move) {
		pg_assert_false(pg_subscription_transition_allowed($move[0], $move[1]), $move[0] . ' -> ' . $move[1]);
	}
}

function test_subscription_transition_fields()
{
	$now = 1900000000;
	$row = array('status' => 'past_due', 'retry_count' => '2', 'cancelled_at' => '0', 'ended_at' => '0');

	$back = pg_subscription_transition_fields($row, 'active', $now);
	pg_assert_same(array('status' => 'active', 'updated_at' => $now, 'retry_count' => 0), $back, 'paid again: the ladder starts over');

	$cancel = pg_subscription_transition_fields($row, 'cancelled', $now);
	pg_assert_same($now, $cancel['cancelled_at']);

	$end = pg_subscription_transition_fields(array('status' => 'cancelled', 'cancelled_at' => '1800000000'), 'ended', $now);
	pg_assert_same(array('status' => 'ended', 'updated_at' => $now, 'ended_at' => $now), $end, 'the cancel time stays');

	pg_assert_same(null, pg_subscription_transition_fields(array('status' => 'ended'), 'active', $now), 'a refused move writes nothing');
}

function test_subscription_event_dedupe_only_by_webhook_id()
{
	$store = pg_subscription_event_row(5, 'renewed', 900, 17688, '', 'Charged', 1900000000);
	pg_assert_same(null, $store['remote_event_id'], 'a store-side event keeps NULL, so the unique key never refuses it');
	pg_assert_same(null, pg_subscription_event_row(5, 'renewed', 901, 17688, '   ', '', 1900000000)['remote_event_id']);

	$hook = pg_subscription_event_row(5, 'renewed', 0, 0, ' evt_1AbC ', '', 1900000000);
	pg_assert_same('evt_1AbC', $hook['remote_event_id'], 'a webhook id is kept as the service sent it');
	pg_assert_same(100, strlen(pg_subscription_event_row(5, 'x', 0, 0, str_repeat('e', 140), '', 1)['remote_event_id']));

	pg_assert_same(255, mb_strlen(pg_subscription_event_row(5, 'x', 0, 0, '', str_repeat('ş', 300), 1)['detail']), 'detail fits its column');
	pg_assert_same(null, pg_subscription_event_row(5, 'Not A Kind', 0, 0, '', '', 1), 'a kind is a short lowercase name');
	pg_assert_same(null, pg_subscription_event_row(0, 'renewed', 0, 0, '', '', 1), 'an event belongs to a subscription');
	pg_assert_same(0, pg_subscription_event_row(5, 'failed', 0, -5, '', '', 1)['amount'], 'no negative amount in an unsigned column');
}

function test_subscription_card_ref_never_stored_unsealed()
{
	pg_assert_false(defined('ENCRYPTION_KEY'), 'no site key in the test runner');
	pg_assert_same('', pg_subscription_card_ref_seal('card_tok_123'), 'without a key nothing is sealed');

	$line = subscriptions_test_line();
	$refused = pg_subscription_row_from_order_line($line['order'], $line['item'], 'iyzico', 'monthly', 'TRY', 'card_tok_123', '4242', 1900000000);
	pg_assert_same(null, $refused, 'a plain token is refused, never written');
	pg_assert_same(null, pg_subscription_row_from_order_line($line['order'], $line['item'], 'iyzico', 'monthly', 'TRY', '', '4242', 1900000000), 'store mode needs a token');

	pg_assert_true(pg_subscription_card_ref_is_sealed(subscriptions_test_sealed()));
	pg_assert_false(pg_subscription_card_ref_is_sealed('pm_1N2b3C:abc'));
	pg_assert_false(pg_subscription_card_ref_is_sealed(str_repeat('A', 240) . ':' . base64_encode(str_repeat('i', 16))), 'longer than the column');
}

function test_subscription_row_from_order_line()
{
	$line = subscriptions_test_line(array('item' => array('id' => '9001', 'product_id' => '44', 'price' => '14990', 'quantity' => '2', 'tax_total' => '5396')));
	$sealed = subscriptions_test_sealed();
	$now = mktime(14, 0, 0, 1, 31, 2027);

	$row = pg_subscription_row_from_order_line($line['order'], $line['item'], 'stripe', 'monthly', 'try', $sealed, '4242', $now);

	pg_assert_same(17, $row['user_id']);
	pg_assert_same(44, $row['product_id']);
	pg_assert_same(501, $row['order_id']);
	pg_assert_same(9001, $row['order_item_id']);
	pg_assert_same('stripe', $row['driver']);
	pg_assert_same('', $row['remote_id'], 'store mode keeps no remote id');
	pg_assert_same('active', $row['status']);
	pg_assert_same(35376, $row['amount'], 'the line: unit price times quantity plus the line tax');
	pg_assert_same('TRY', $row['currency']);
	pg_assert_same('2027-02-28', date('Y-m-d', $row['next_charge_at']));
	pg_assert_same($sealed, $row['card_ref']);
	pg_assert_same('4242', $row['card_last4']);
	pg_assert_same(0, $row['retry_count']);

	pg_assert_same('', pg_subscription_row_from_order_line($line['order'], $line['item'], 'stripe', 'monthly', 'TRY', $sealed, '42x', $now)['card_last4'], 'only four digits are kept');
	pg_assert_same(null, pg_subscription_row_from_order_line($line['order'], $line['item'], 'stripe', 'daily', 'TRY', $sealed, '4242', $now), 'unknown period');
	pg_assert_same(null, pg_subscription_row_from_order_line($line['order'], $line['item'], 'stripe', 'monthly', 'TL', $sealed, '4242', $now), 'currency is three letters');
	pg_assert_same(null, pg_subscription_row_from_order_line(array('id' => '0'), $line['item'], 'stripe', 'monthly', 'TRY', $sealed, '4242', $now), 'a paid order is needed');
}

function test_subscription_driver_recurring_mode()
{
	pg_assert_same(array('store', 'psp'), pg_payment_recurring_modes());
	pg_assert_same('store', pg_payment_recurring_mode_declared(array('installments', 'recurring' => 'store')));
	pg_assert_same('psp', pg_payment_recurring_mode_declared(array('recurring' => array('mode' => 'psp'))));
	pg_assert_same('', pg_payment_recurring_mode_declared(array('recurring')), 'recurring without a mode is not offered');
	pg_assert_same('', pg_payment_recurring_mode_declared(array('recurring' => 'cron')));
	pg_assert_same('', pg_payment_recurring_mode_declared(null));

	pg_assert_same(array('installments', 'recurring'), pg_payment_capabilities_normalize(array('installments', 'recurring' => 'store')), 'a mode declares the capability');
	pg_assert_same(array('installments'), pg_payment_capabilities_normalize(array('installments', 'recurring')), 'without a mode it does not');
	pg_assert_same(array('wallets'), pg_payment_capabilities_normalize(array('wallets', 'teleport' => 'store')));
}

function test_subscription_driver_operations_declared_not_supported_by_default()
{
	$operations = pg_payment_operations();

	foreach (array('charge_renewal', 'update_card', 'subscription_cancel') as $operation) {
		pg_assert_same(1, $operations[$operation] ?? null, $operation . ' takes the subscription');
	}

	pg_assert_same('This payment method is not available.', pg_payment_call('no_such_driver', 'charge_renewal', array(array()))['error']);

	pg_assert_true(pg_payment_answer('charge_renewal', array('success' => true, 'status' => 'paid', 'transaction_id' => 'x'))['success']);
	pg_assert_false(pg_payment_answer('charge_renewal', array('success' => true, 'status' => 'refunded'))['success'], 'a renewal is a charge');
	pg_assert_true(pg_payment_answer('update_card', array('success' => true, 'status' => 'redirect', 'redirect_url' => 'https://pay.example/card'))['success'], 'the card is updated on the service\'s page');
	pg_assert_true(pg_payment_answer('subscription_cancel', array('success' => true, 'status' => 'cancelled'))['success']);
	pg_assert_same('tok_9', pg_payment_answer('authorize', array('success' => true, 'status' => 'paid', 'card_ref' => ' tok_9 '))['card_ref'], 'the stored card\'s token comes back as card_ref');
	pg_assert_same('', pg_payment_answer('authorize', array('success' => true, 'status' => 'paid', 'card_ref' => str_repeat('t', 200)))['card_ref'], 'a token too long to seal is not kept');
}

function subscriptions_test_checkout_lines()
{
	return array(
		array('order_item_id' => '9001', 'product_id' => '44', 'recurring' => '1', 'price' => '14990', 'quantity' => '1', 'tax_total' => '2698', 'recurring_payment_period' => 'Monthly', 'recurring_start_date' => '2027-01-31'),
		array('order_item_id' => '9002', 'product_id' => '45', 'recurring' => '0', 'price' => '5000', 'quantity' => '1', 'tax_total' => '0', 'recurring_payment_period' => '', 'recurring_start_date' => '0000-00-00'),
		array('order_item_id' => '9003', 'product_id' => '46', 'recurring' => '1', 'price' => '9900', 'quantity' => '2', 'tax_total' => '0', 'recurring_payment_period' => 'Yearly', 'recurring_start_date' => '0000-00-00'),
		array('order_item_id' => '9004', 'product_id' => '47', 'recurring' => '1', 'price' => '0', 'quantity' => '1', 'tax_total' => '0', 'recurring_payment_period' => 'Monthly', 'recurring_start_date' => '2027-01-31'),
		array('order_item_id' => '9005', 'product_id' => '48', 'recurring' => '1', 'price' => '1000', 'quantity' => '1', 'tax_total' => '0', 'recurring_payment_period' => 'Monthly', 'recurring_start_date' => '2027-03-01'),
		array('order_item_id' => '9001', 'product_id' => '44', 'recurring' => '1', 'price' => '14990', 'quantity' => '1', 'tax_total' => '2698', 'recurring_payment_period' => 'Monthly', 'recurring_start_date' => '2027-01-31'),
	);
}

function test_subscription_checkout_lines_one_per_paid_recurring_line()
{
	$planned = pg_subscription_checkout_lines(subscriptions_test_checkout_lines(), 'store', 'ukey-1|ctok-1', '2027-01-31');

	pg_assert_same(array(9001, 9003), array_map(function ($line) {
		return $line['item']['id'];
	}, $planned), 'recurring, priced, charged today; each line once');
	pg_assert_same(array('monthly', 'yearly'), array_column($planned, 'period'));
	pg_assert_same(array('id' => 9003, 'product_id' => 46, 'price' => '9900', 'quantity' => '2', 'tax_total' => '0'), $planned[1]['item']);

	$line = subscriptions_test_line();
	pg_assert_same(17688, pg_subscription_row_from_order_line($line['order'], $planned[0]['item'], 'iyzico', $planned[0]['period'], 'TRY', subscriptions_test_sealed(), '0008', time())['amount'], 'the row takes the line as planned');
}

function test_subscription_checkout_lines_none_for_other_drivers()
{
	$lines = subscriptions_test_checkout_lines();

	pg_assert_same(array(), pg_subscription_checkout_lines($lines, '', 'ukey-1|ctok-1', '2027-01-31'), 'a driver without a recurring mode');
	pg_assert_same(array(), pg_subscription_checkout_lines($lines, 'psp', 'ukey-1|ctok-1', '2027-01-31'), 'the service runs its own');
	pg_assert_same(array(), pg_subscription_checkout_lines($lines, 'store', '', '2027-01-31'), 'no stored card');
	pg_assert_same(array(), pg_subscription_checkout_lines(null, 'store', 'ukey-1|ctok-1', '2027-01-31'));

	// No database in the runner: the writer makes nothing and never throws.
	pg_assert_same(array(), pg_subscription_checkout_create(501, 'iyzico', array('card_ref' => 'ukey-1|ctok-1'), $lines));
	pg_assert_same(array(), pg_subscription_checkout_create(501, '', array(), $lines), 'a legacy gateway has no driver');
}

