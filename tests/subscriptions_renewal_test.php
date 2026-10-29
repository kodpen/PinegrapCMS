<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the renewals of store-run subscriptions
 * (includes/fn/subscription_renewal.php): which rows are due, what each
 * answer of charge_renewal() writes (next date, the 1/3/7 ladder and ended,
 * the held rows), that a row is charged once under the lock, that a paid
 * renewal writes its order after the row moved and marks it through the
 * event, and that the legacy PayPal Payments Pro branch of the job stays.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function subscriptions_renewal_test_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'user_id' => '17', 'product_id' => '44', 'order_id' => '501', 'order_item_id' => '9001',
		'driver' => 'stripe', 'status' => 'active', 'period' => 'monthly', 'amount' => '17688', 'currency' => 'TRY',
		'next_charge_at' => (string) mktime(10, 0, 0, 3, 31, 2027), 'retry_count' => '0', 'card_last4' => '4242',
		'card_ref' => '', 'created_at' => (string) mktime(10, 0, 0, 1, 31, 2027), 'cancelled_at' => '0', 'ended_at' => '0',
	), $over);
}

// Stand-ins for the database and the payment service: one stored row, the
// calls in the order they were made.
function subscriptions_renewal_test_io(array &$store, array &$calls, $answer, $lock = true)
{
	return array(
		'lock' => function ($id, $wait = 0) use (&$calls, $lock) {
			$calls[] = 'lock';
			return $lock;
		},
		'unlock' => function ($id) use (&$calls) {
			$calls[] = 'unlock';
		},
		'read' => function ($id) use (&$store) {
			return $store;
		},
		'first_order' => function ($row) use (&$calls) {
			$calls[] = 'first_order';
			return array(
				'order' => array('id' => '501', 'billing_email_address' => 'a@example.com'),
				'item'  => array('id' => '9001', 'ship_to_id' => '0', 'product_id' => '44', 'product_name' => 'Box', 'quantity' => '1', 'price' => '14990'),
			);
		},
		'charge' => function ($driver, $driver_row) use (&$calls, $answer) {
			$calls[] = array('charge', $driver, $driver_row);
			return $answer;
		},
		'seal' => function ($token) {
			return 'sealed:' . $token;
		},
		'save' => function ($row, $plan) use (&$store, &$calls) {
			$calls[] = 'save';

			if (((string) $store['status'] !== (string) $row['status']) || ((int) $store['next_charge_at'] !== (int) $row['next_charge_at'])) {
				return false;
			}

			$store = array_merge($store, $plan['fields'], ($plan['status'] !== '') ? array('status' => $plan['status']) : array());

			return true;
		},
		'write_order' => function ($row, $first, $answer, $now) use (&$calls) {
			$calls[] = 'write_order';
			return 880;
		},
		'log' => function ($message) use (&$calls) {
			$calls[] = array('log', $message);
		},
		'event' => function ($id, $kind, $order_id, $amount, $detail, $remote_event_id = '') use (&$calls) {
			$calls[] = array('event', $kind, $order_id, $amount, $detail, $remote_event_id);
		},
	);
}

function subscriptions_renewal_test_charges(array $calls)
{
	return count(array_filter($calls, function ($call) {
		return is_array($call) && ($call[0] === 'charge');
	}));
}

function test_subscription_renewal_due_rows()
{
	$sql = pg_subscription_renewal_due_sql();

	pg_assert_contains("status IN ('active', 'past_due')", $sql);
	pg_assert_contains('next_charge_at > 0 AND next_charge_at <= ?', $sql, 'a held row (0) is never due');
	pg_assert_contains('LIMIT ?', $sql, 'batched');
	pg_assert_contains('ORDER BY next_charge_at ASC', $sql);

	$now = mktime(10, 0, 0, 4, 1, 2027);

	pg_assert_true(pg_subscription_renewal_is_due(subscriptions_renewal_test_row(), $now));
	pg_assert_true(pg_subscription_renewal_is_due(subscriptions_renewal_test_row(array('status' => 'past_due')), $now));
	pg_assert_true(pg_subscription_renewal_is_due(subscriptions_renewal_test_row(array('next_charge_at' => (string) $now)), $now), 'due at the second');

	foreach (array('paused', 'cancelled', 'ended') as $status) {
		pg_assert_false(pg_subscription_renewal_is_due(subscriptions_renewal_test_row(array('status' => $status)), $now), $status);
	}

	pg_assert_false(pg_subscription_renewal_is_due(subscriptions_renewal_test_row(array('next_charge_at' => '0')), $now), 'held');
	pg_assert_false(pg_subscription_renewal_is_due(subscriptions_renewal_test_row(array('next_charge_at' => (string) ($now + 1))), $now), 'not yet');
	pg_assert_false(pg_subscription_renewal_is_due(null, $now));
}

function test_subscription_renewal_outcome()
{
	pg_assert_same('paid', pg_subscription_renewal_outcome(array('success' => true, 'status' => 'paid')));
	pg_assert_same('pending', pg_subscription_renewal_outcome(array('success' => true, 'status' => 'pending')));
	pg_assert_same('confirm', pg_subscription_renewal_outcome(array('success' => true, 'status' => 'redirect', 'redirect_url' => 'https://hooks.stripe.com/x')));
	pg_assert_same('unclear', pg_subscription_renewal_outcome(array('success' => true, 'status' => 'redirect', 'redirect_url' => '')));
	pg_assert_same('unclear', pg_subscription_renewal_outcome(array('success' => false, 'unclear' => true)));
	pg_assert_same('failed', pg_subscription_renewal_outcome(array('success' => false, 'error' => 'Declined')));
	pg_assert_same('unclear', pg_subscription_renewal_outcome(null));
}

function test_subscription_renewal_paid_moves_one_period_and_resets_the_ladder()
{
	$now = mktime(10, 0, 0, 3, 31, 2027);
	$plan = pg_subscription_renewal_plan(subscriptions_renewal_test_row(), 'paid', array(), $now);

	pg_assert_same('2027-04-30', date('Y-m-d', $plan['fields']['next_charge_at']), 'the month end of the anchor day');
	pg_assert_same(0, $plan['fields']['retry_count']);
	pg_assert_same('', $plan['status'], 'active stays active');
	pg_assert_same('renewed', $plan['event']);

	// A retry paid three days late keeps the day of the first charge.
	$late = pg_subscription_renewal_plan(subscriptions_renewal_test_row(array('status' => 'past_due', 'retry_count' => '2', 'created_at' => (string) mktime(9, 0, 0, 1, 15, 2027))), 'paid', array(), mktime(9, 0, 0, 3, 18, 2027));

	pg_assert_same('2027-04-15', date('Y-m-d', $late['fields']['next_charge_at']));
	pg_assert_same('active', $late['status']);
	pg_assert_same(0, $late['fields']['retry_count']);
}

function test_subscription_renewal_next_date_follows_the_series()
{
	$created = mktime(10, 0, 0, 1, 31, 2027);

	pg_assert_same('2027-04-30 10:00', date('Y-m-d H:i', pg_subscription_renewal_next_at($created, 'monthly', mktime(10, 0, 0, 3, 31, 2027))), 'paid on time');
	pg_assert_same('2027-04-30 10:00', date('Y-m-d H:i', pg_subscription_renewal_next_at($created, 'monthly', mktime(23, 0, 0, 4, 1, 2027))), 'a day late');
	pg_assert_same('2027-08-31 10:00', date('Y-m-d H:i', pg_subscription_renewal_next_at($created, 'monthly', mktime(9, 0, 0, 8, 2, 2027))), 'held for months: the missed periods are skipped');
	pg_assert_same('2027-02-14', date('Y-m-d', pg_subscription_renewal_next_at($created, 'weekly', mktime(10, 0, 0, 2, 9, 2027))));
	pg_assert_same('2027-05-02', date('Y-m-d', pg_subscription_renewal_next_at(0, 'monthly', mktime(10, 0, 0, 4, 2, 2027))), 'no first charge: from now');
	pg_assert_same(0, pg_subscription_renewal_next_at($created, 'nope', mktime(10, 0, 0, 4, 2, 2027)));
}

function test_subscription_renewal_failure_ladder_then_ended()
{
	$now = mktime(10, 0, 0, 4, 1, 2027);
	$steps = array(array('active', 0, 1, 'past_due'), array('past_due', 1, 3, ''), array('past_due', 2, 7, ''));

	foreach ($steps as $step) {
		$plan = pg_subscription_renewal_plan(subscriptions_renewal_test_row(array('status' => $step[0], 'retry_count' => (string) $step[1])), 'failed', array('error' => 'Declined'), $now);

		pg_assert_same($step[1] + 1, $plan['fields']['retry_count']);
		pg_assert_same(date('Y-m-d H:i', mktime(10, 0, 0, 4, 1 + $step[2], 2027)), date('Y-m-d H:i', $plan['fields']['next_charge_at']), 'retry after ' . $step[2] . ' days');
		pg_assert_same($step[3], $plan['status']);
		pg_assert_same('renewal_failed', $plan['event']);
		pg_assert_same('Declined', $plan['detail']);
	}

	$over = pg_subscription_renewal_plan(subscriptions_renewal_test_row(array('status' => 'past_due', 'retry_count' => '3')), 'failed', array('error' => 'Declined'), $now);

	pg_assert_same('ended', $over['status'], 'after the ladder');
	pg_assert_same(0, $over['fields']['next_charge_at']);
	pg_assert_same($now, $over['fields']['ended_at']);
}

function test_subscription_renewal_confirm_and_unclear_hold_the_row()
{
	$now = mktime(10, 0, 0, 4, 1, 2027);
	$confirm = pg_subscription_renewal_plan(subscriptions_renewal_test_row(), 'confirm', array('redirect_url' => 'https://hooks.stripe.com/redirect/authenticate/x'), $now);

	pg_assert_same('past_due', $confirm['status'], 'authentication_required: past_due');
	pg_assert_same(0, $confirm['fields']['next_charge_at'], 'no ladder charge while the customer may still confirm');
	pg_assert_same('https://hooks.stripe.com/redirect/authenticate/x', $confirm['detail']);
	pg_assert_same('confirm_needed', $confirm['event']);

	$long = pg_subscription_renewal_plan(subscriptions_renewal_test_row(), 'confirm', array('redirect_url' => 'https://x.test/' . str_repeat('a', 260)), $now);
	pg_assert_same('', $long['detail'], 'a cut link is not kept');

	foreach (array('unclear' => 'renewal_unclear', 'pending' => 'renewal_pending') as $outcome => $event) {
		$plan = pg_subscription_renewal_plan(subscriptions_renewal_test_row(), $outcome, array('error' => 'x'), $now);

		pg_assert_same(0, $plan['fields']['next_charge_at'], $outcome . ': no second charge');
		pg_assert_same('', $plan['status'], $outcome . ': status stays');
		pg_assert_same($event, $plan['event']);
		pg_assert_false(isset($plan['fields']['retry_count']), $outcome . ': no ladder step');
	}
}

function test_subscription_renewal_figures()
{
	$figures = pg_subscription_renewal_figures(array('amount' => '17688'), array('price' => '14990', 'quantity' => '1'));

	pg_assert_same(array('price' => 14990, 'quantity' => 1, 'subtotal' => 14990, 'tax' => 2698, 'total' => 17688), $figures);
	pg_assert_same(2, pg_subscription_renewal_figures(array('amount' => '3000'), array('price' => '1250', 'quantity' => '2'))['quantity']);
	pg_assert_same(null, pg_subscription_renewal_figures(array('amount' => '100'), array('price' => '14990', 'quantity' => '1')), 'the line exceeds the amount');
	pg_assert_same(null, pg_subscription_renewal_figures(array('amount' => '100'), array('price' => '0', 'quantity' => '1')));
}

function test_subscription_renewal_card_fields_reseal_a_new_card()
{
	$seal = function ($token) {
		return 'sealed:' . $token;
	};

	pg_assert_same(array(), pg_subscription_renewal_card_fields(array('card_ref' => 'cus|pm_1'), 'cus|pm_1', $seal), 'same card');
	pg_assert_same(array(), pg_subscription_renewal_card_fields(array('card_ref' => ''), 'cus|pm_1', $seal));
	pg_assert_same(array('card_ref' => 'sealed:cus|pm_2', 'card_last4' => '1881'), pg_subscription_renewal_card_fields(array('card_ref' => 'cus|pm_2', 'card_last4' => '1881'), 'cus|pm_1', $seal));
	pg_assert_same(array(), pg_subscription_renewal_card_fields(array('card_ref' => 'cus|pm_2'), 'cus|pm_1', function () {
		return '';
	}), 'no key: the stored card stays');
}

function test_subscription_renewal_paid_writes_order_after_the_row_moved()
{
	$now = mktime(10, 0, 0, 4, 1, 2027);
	$store = subscriptions_renewal_test_row();
	$calls = array();
	$answer = array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_1', 'card_ref' => 'cus|pm_2', 'card_last4' => '1881');

	pg_assert_same('paid', pg_subscription_renewal_run_row(7, $now, subscriptions_renewal_test_io($store, $calls, $answer)));

	$names = array_map(function ($call) {
		return is_array($call) ? $call[0] : $call;
	}, $calls);

	pg_assert_same(array('lock', 'first_order', 'charge', 'save', 'write_order', 'event', 'unlock'), $names);
	pg_assert_same(array('event', 'renewed', 880, 17688, '', ''), $calls[5], 'the order is marked through the event');
	pg_assert_same('stripe', $calls[2][1]);
	pg_assert_same('501', $calls[2][2]['order']['id'], 'the first order goes to charge_renewal');
	pg_assert_same('2027-04-30', date('Y-m-d', (int) $store['next_charge_at']), 'a run a day late keeps the series');
	pg_assert_same('sealed:cus|pm_2', $store['card_ref'], 'a new card is sealed again');
	pg_assert_same('1881', $store['card_last4']);
}

function test_subscription_renewal_paid_without_an_order_is_logged()
{
	$store = subscriptions_renewal_test_row();
	$calls = array();
	$io = subscriptions_renewal_test_io($store, $calls, array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_9'));
	$io['write_order'] = function () {
		return 0;
	};

	pg_assert_same('paid', pg_subscription_renewal_run_row(7, mktime(10, 0, 0, 4, 1, 2027), $io));

	$logs = array_values(array_filter($calls, function ($call) {
		return is_array($call) && ($call[0] === 'log');
	}));

	pg_assert_same(1, count($logs), 'staff are told');
	pg_assert_contains('pi_9', $logs[0][1]);
	pg_assert_same('2027-04-30', date('Y-m-d', (int) $store['next_charge_at']), 'the row moved: never charged again');
}

function test_subscription_renewal_never_charges_twice()
{
	$now = mktime(10, 0, 0, 4, 1, 2027);
	$store = subscriptions_renewal_test_row();
	$calls = array();
	$answer = array('success' => true, 'status' => 'paid');

	pg_assert_same('skipped', pg_subscription_renewal_run_row(7, $now, subscriptions_renewal_test_io($store, $calls, $answer, false)), 'another run holds the lock');
	pg_assert_same(0, subscriptions_renewal_test_charges($calls));

	$io = subscriptions_renewal_test_io($store, $calls, $answer);

	pg_assert_same('paid', pg_subscription_renewal_run_row(7, $now, $io));
	pg_assert_same('skipped', pg_subscription_renewal_run_row(7, $now, $io), 'the second run reads the moved row');
	pg_assert_same(1, subscriptions_renewal_test_charges($calls));

	$held = subscriptions_renewal_test_row();
	$calls = array();
	$io = subscriptions_renewal_test_io($held, $calls, array('success' => false, 'unclear' => true, 'error' => 'timeout'));

	pg_assert_same('unclear', pg_subscription_renewal_run_row(7, $now, $io));
	pg_assert_same('skipped', pg_subscription_renewal_run_row(7, $now + 86400 * 30, $io), 'held until resolved');
	pg_assert_same(1, subscriptions_renewal_test_charges($calls));
	pg_assert_false(in_array('write_order', $calls, true), 'no order without a payment');
}

function test_subscription_renewal_missing_first_order_is_not_charged()
{
	$now = mktime(10, 0, 0, 4, 1, 2027);
	$store = subscriptions_renewal_test_row();
	$calls = array();
	$io = subscriptions_renewal_test_io($store, $calls, array('success' => true, 'status' => 'paid'));
	$io['first_order'] = function () {
		return null;
	};

	pg_assert_same('refused', pg_subscription_renewal_run_row(7, $now, $io));
	pg_assert_same(0, subscriptions_renewal_test_charges($calls));
	pg_assert_same('past_due', $store['status']);
	pg_assert_same('1', (string) $store['retry_count']);
}

function test_subscription_renewal_confirmed_takes_only_a_held_row()
{
	$now = mktime(12, 0, 0, 4, 2, 2027);
	$paid = array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_3', 'amount' => 17688);

	// Held by a confirmation link: the customer's confirmation is the renewal.
	$store = subscriptions_renewal_test_row(array('status' => 'past_due', 'next_charge_at' => '0', 'retry_count' => '0'));
	$calls = array();
	$io = subscriptions_renewal_test_io($store, $calls, $paid);

	pg_assert_same('paid', pg_subscription_renewal_confirmed('stripe', 7, $paid, $now, $io));
	pg_assert_same('active', $store['status']);
	pg_assert_same('2027-04-30', date('Y-m-d', (int) $store['next_charge_at']));
	pg_assert_same(array('event', 'renewed', 880, 17688, '', 'pi_3'), $calls[count($calls) - 2], 'the payment id de-duplicates a second delivery');
	pg_assert_same(0, subscriptions_renewal_test_charges($calls), 'nothing is charged');

	// The second delivery finds the row moved on.
	pg_assert_same('nothing', pg_subscription_renewal_confirmed('stripe', 7, $paid, $now, $io));

	// The cron's own off-session renewal reports back too: the row is not held.
	$active = subscriptions_renewal_test_row();
	$calls = array();
	pg_assert_same('nothing', pg_subscription_renewal_confirmed('stripe', 7, $paid, $now, subscriptions_renewal_test_io($active, $calls, $paid)));
	pg_assert_false(in_array('write_order', $calls, true));

	$held = subscriptions_renewal_test_row(array('next_charge_at' => '0'));
	$calls = array();
	pg_assert_same('refused', pg_subscription_renewal_confirmed('stripe', 7, array('amount' => 100) + $paid, $now, subscriptions_renewal_test_io($held, $calls, $paid)), 'another amount');
	pg_assert_same('nothing', pg_subscription_renewal_confirmed('iyzico', 7, $paid, $now, subscriptions_renewal_test_io($held, $calls, $paid)), 'another driver');
	pg_assert_same('busy', pg_subscription_renewal_confirmed('stripe', 7, $paid, $now, subscriptions_renewal_test_io($held, $calls, $paid, false)), 'a run holds the row');
	pg_assert_false(in_array('write_order', $calls, true));

	$ended = subscriptions_renewal_test_row(array('status' => 'ended', 'next_charge_at' => '0'));
	pg_assert_same('nothing', pg_subscription_renewal_confirmed('stripe', 7, $paid, $now, subscriptions_renewal_test_io($ended, $calls, $paid)));
}

function test_subscription_renewal_job_keeps_the_legacy_branch()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/recurring_payment_job.php');
	$legacy = strpos($source, "if ((ECOMMERCE_CREDIT_DEBIT_CARD == true) && (ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro')) {");
	$renewals = strpos($source, 'pg_subscription_renewals_run(time());');

	pg_assert_true($legacy !== false, 'the PayPal Payments Pro branch is there');
	pg_assert_true(($renewals !== false) && ($renewals < $legacy), 'the driver branch runs first, outside the legacy one');
	pg_assert_same(1, substr_count($source, 'pg_subscription_renewals_run('));
	pg_assert_contains("'METHOD' => 'GetRecurringPaymentsProfileDetails'", $source);
	pg_assert_contains("pg_cron_ran('recurring_payment_job');", $source);
}
