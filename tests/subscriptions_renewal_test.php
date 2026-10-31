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
			// A webhook id already there is not written again (INSERT IGNORE).
			if ($remote_event_id === 'pi_dup') {
				return 0;
			}
			return 900 + count($calls);
		},
		// Kept apart from $calls, so the order of the other steps reads as before.
		'mail' => function ($id, $event_id, $event, $status_before, $status_after, $order_id = 0, $detail = '') {
			$GLOBALS['subscriptions_renewal_test_mails'][] = array(
				'event_id' => $event_id,
				'action'   => ($event_id > 0) ? pg_subscription_mail_action($event, $status_before, $status_after) : '',
				'event'    => $event,
				'order_id' => $order_id,
				'detail'   => $detail,
			);
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

function test_subscription_renewal_series_of_a_later_line_starts_on_its_start_date()
{
	// Ordered on Jan 20, started on Mar 1: the first charge on the start date
	// sets the day of the series, not the order's day.
	$row = subscriptions_renewal_test_row(array('created_at' => (string) mktime(15, 0, 0, 1, 20, 2027), 'next_charge_at' => (string) mktime(0, 0, 0, 3, 1, 2027)));
	$item = array('recurring_start_date' => '2027-03-01');

	$first = pg_subscription_renewal_plan($row, 'paid', array(), mktime(4, 0, 0, 3, 1, 2027), $item);
	pg_assert_same('2027-04-01', date('Y-m-d', $first['fields']['next_charge_at']), 'one period after the first charge');

	$late = pg_subscription_renewal_plan(array_merge($row, array('status' => 'past_due', 'retry_count' => '1')), 'paid', array(), mktime(4, 0, 0, 4, 2, 2027), $item);
	pg_assert_same('2027-05-01', date('Y-m-d', $late['fields']['next_charge_at']), 'a paid retry keeps the series');

	pg_assert_same('2027-04-20', date('Y-m-d', pg_subscription_renewal_plan($row, 'paid', array(), mktime(16, 0, 0, 3, 20, 2027), array('recurring_start_date' => '2027-01-20'))['fields']['next_charge_at']), 'a line charged on the order day: the order day');
	pg_assert_same('2027-04-20', date('Y-m-d', pg_subscription_renewal_plan($row, 'paid', array(), mktime(16, 0, 0, 3, 20, 2027))['fields']['next_charge_at']), 'no line given: the order day');
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

// Which profile action each event mails through.
function test_subscription_mail_action()
{
	pg_assert_same('subscription_renewed', pg_subscription_mail_action('renewed', 'past_due', 'active'));
	pg_assert_same('subscription_failed', pg_subscription_mail_action('renewal_failed', 'active', 'past_due'));
	pg_assert_same('subscription_failed', pg_subscription_mail_action('confirm_needed', 'active', 'past_due'), 'the confirmation link');
	pg_assert_same('subscription_cancelled', pg_subscription_mail_action('renewal_failed', 'past_due', 'ended'), 'the ladder ran out');
	pg_assert_same('subscription_cancelled', pg_subscription_mail_action('status_cancelled', 'active', 'cancelled'));
	pg_assert_same('subscription_cancelled', pg_subscription_mail_action('status_ended', 'active', 'ended'));
	pg_assert_same('', pg_subscription_mail_action('status_ended', 'cancelled', 'ended'), 'told once, at the cancel');

	foreach (array('renewal_pending', 'renewal_unclear', 'status_past_due', 'status_active', 'created') as $event) {
		pg_assert_same('', pg_subscription_mail_action($event, 'active', 'active'), $event);
	}
}

// The tokens a profile's subject and body can hold, always all of them.
function test_subscription_mail_fields()
{
	$row = subscriptions_renewal_test_row(array('next_charge_at' => (string) mktime(10, 0, 0, 4, 30, 2027)));
	$fields = pg_subscription_mail_fields($row, array(
		'product_name' => 'Coffee box', 'order_number' => '1042',
		'confirm_url' => 'https://hooks.stripe.com/x', 'card_url' => 'https://example.com/c'));
	$by_name = array();

	foreach ($fields as $field) {
		$by_name[$field['name']] = $field;
	}

	pg_assert_same(array('subscription_product', 'subscription_amount', 'subscription_next_date', 'subscription_card_url', 'subscription_confirm_url', 'order_number'), array_keys($by_name));
	pg_assert_same('Coffee box', $by_name['subscription_product']['data']);
	pg_assert_same(pg_format_number(176.88, 2) . ' TRY', $by_name['subscription_amount']['data']);
	pg_assert_same('2027-04-30', $by_name['subscription_next_date']['data']);
	pg_assert_same('date', $by_name['subscription_next_date']['type'], 'a %%format%% applies');
	pg_assert_same('https://hooks.stripe.com/x', $by_name['subscription_confirm_url']['data']);
	pg_assert_same('1042', $by_name['order_number']['data']);

	$empty = pg_subscription_mail_fields(subscriptions_renewal_test_row(array('next_charge_at' => '0')), array());
	pg_assert_same(6, count($empty), 'passed empty, so [[...||...]] takes its else part');
	pg_assert_same('', $empty[2]['data'], 'held: no next date');
	pg_assert_same('', $empty[3]['data']);

	$filled = replace_variables(array('content' => '^^subscription_product^^[[ ^^subscription_confirm_url^^||-]]', 'fields' => $empty, 'format' => 'plain_text'));
	pg_assert_same('-', $filled);
}

// The card update link: signed, for a running subscription, for 30 days.
function test_subscription_card_link_state()
{
	$key = 'k';
	$now = 1800000000;
	$row = subscriptions_renewal_test_row(array('status' => 'past_due'));
	$link = array('s' => '7', 't' => (string) $now, 'k' => pg_subscription_card_token(7, $now, $key));

	pg_assert_same(64, strlen($link['k']));
	pg_assert_same('', pg_subscription_card_token(7, $now, ''), 'no key, no link');
	pg_assert_same('open', pg_subscription_card_link_state($link, $key, $row, $now + 86400));
	pg_assert_same('expired', pg_subscription_card_link_state($link, $key, $row, $now + pg_subscription_card_link_ttl() + 1));
	pg_assert_same('closed', pg_subscription_card_link_state($link, $key, subscriptions_renewal_test_row(array('status' => 'ended')), $now));
	pg_assert_same('closed', pg_subscription_card_link_state($link, $key, null, $now), 'the row is gone');
	pg_assert_same('invalid', pg_subscription_card_link_state(array('s' => '8') + $link, $key, $row, $now), 'another subscription');
	pg_assert_same('invalid', pg_subscription_card_link_state($link, 'other', $row, $now), 'another key');
	pg_assert_same('invalid', pg_subscription_card_link_state(array('t' => (string) ($now + 600)) + $link, $key, $row, $now), 'signature of another time');
	pg_assert_same('invalid', pg_subscription_card_link_state(array('s' => '07') + $link, $key, $row, $now));
	pg_assert_same('invalid', pg_subscription_card_link_state(array('k' => array('x')) + $link, $key, $row, $now));
	pg_assert_same('invalid', pg_subscription_card_link_state(null, $key, $row, $now));
}

// Each outcome of a run mails once, through the action of its event.
function test_subscription_renewal_mails_once_per_event()
{
	$now = mktime(10, 0, 0, 4, 1, 2027);
	$cases = array(
		array(array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_1'), array(), 'subscription_renewed', 880),
		array(array('success' => false, 'error' => 'Declined'), array(), 'subscription_failed', 0),
		array(array('success' => true, 'status' => 'redirect', 'redirect_url' => 'https://hooks.stripe.com/c'), array(), 'subscription_failed', 0),
		array(array('success' => false, 'error' => 'Declined'), array('status' => 'past_due', 'retry_count' => '3'), 'subscription_cancelled', 0),
		array(array('success' => true, 'status' => 'pending'), array(), '', 0),
	);

	foreach ($cases as $i => $case) {
		$store = subscriptions_renewal_test_row($case[1]);
		$calls = array();
		$GLOBALS['subscriptions_renewal_test_mails'] = array();

		pg_subscription_renewal_run_row(7, $now, subscriptions_renewal_test_io($store, $calls, $case[0]));

		$mails = $GLOBALS['subscriptions_renewal_test_mails'];

		pg_assert_same(1, count($mails), 'case ' . $i . ': asked once');
		pg_assert_same($case[2], $mails[0]['action'], 'case ' . $i);
		pg_assert_same($case[3], $mails[0]['order_id'], 'case ' . $i . ': the renewal order');
		pg_assert_same('unlock', end($calls), 'case ' . $i . ': after the lock is let go');
	}

	pg_assert_same('https://hooks.stripe.com/c', subscription_renewal_test_confirm_detail($now), 'the confirmation link reaches the mail');

	// A second delivery of the service's paid report writes no event, so no mail.
	$paid = array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_dup', 'amount' => 17688);
	$store = subscriptions_renewal_test_row(array('status' => 'past_due', 'next_charge_at' => '0'));
	$calls = array();
	$GLOBALS['subscriptions_renewal_test_mails'] = array();
	pg_subscription_renewal_confirmed('stripe', 7, $paid, $now, subscriptions_renewal_test_io($store, $calls, $paid));
	pg_assert_same('', $GLOBALS['subscriptions_renewal_test_mails'][0]['action'], 'event not written: no mail');
	pg_assert_false(pg_subscription_mail_send(7, 0, 'renewed', 'active', 'active'), 'nothing is read for an event not written');
}

function subscription_renewal_test_confirm_detail($now)
{
	$store = subscriptions_renewal_test_row();
	$calls = array();
	$GLOBALS['subscriptions_renewal_test_mails'] = array();
	pg_subscription_renewal_run_row(7, $now, subscriptions_renewal_test_io($store, $calls, array('success' => true, 'status' => 'redirect', 'redirect_url' => 'https://hooks.stripe.com/c')));

	return $GLOBALS['subscriptions_renewal_test_mails'][0]['detail'];
}

// A price change: the line as the notice scan reads it (the first order's
// line, the product's price now, the latest notice's amount).
function subscriptions_renewal_test_price_row(array $over = array())
{
	return array_merge(subscriptions_renewal_test_row(), array(
		'price' => '14990', 'quantity' => '1', 'tax_total' => '2698', 'recurring_start_date' => '',
		'product_price' => '14990', 'notice_amount' => null,
	), $over);
}

function test_subscription_price_amount_keeps_the_quantity_and_the_tax_rate()
{
	$item = array('price' => '14990', 'quantity' => '1', 'tax_total' => '2698');

	pg_assert_same(17688, pg_subscription_price_amount($item, 14990), 'the same price: the amount charged today');
	pg_assert_same(16990 + 3058, pg_subscription_price_amount($item, 16990), 'the tax at the line\'s rate, rounded');
	pg_assert_same(2 * 1100 + 220, pg_subscription_price_amount(array('price' => '1250', 'quantity' => '2', 'tax_total' => '250'), '1100'), 'a decrease, two units');
	pg_assert_same(0, pg_subscription_price_amount($item, 0), 'no price, no notice');
	pg_assert_same(0, pg_subscription_price_amount(array('price' => '0', 'quantity' => '1'), 14990));
}

// At least 14 days ahead: the next charge itself, or one period later.
function test_subscription_price_notice_written_at_14_days()
{
	$next = mktime(10, 0, 0, 3, 31, 2027);
	$row = subscriptions_renewal_test_price_row(array('product_price' => '16990'));

	$plan = pg_subscription_price_notice_plan($row, $row, $row['product_price'], $row['notice_amount'], $next - 14 * 86400);
	pg_assert_same(array('amount' => 20048, 'detail' => '2027-03-31'), $plan, '14 days ahead: the next charge');

	$late = pg_subscription_price_notice_plan($row, $row, $row['product_price'], $row['notice_amount'], $next - 14 * 86400 + 3600);
	pg_assert_same(array('amount' => 20048, 'detail' => '2027-04-30'), $late, 'inside the 14 days: one period later');

	pg_assert_same(null, pg_subscription_price_notice_plan($row, $row, '14990', null, $next - 20 * 86400), 'no change');
	pg_assert_same(null, pg_subscription_price_notice_plan($row, $row, '16990', '20048', $next - 20 * 86400), 'the same new amount: no second notice');
	pg_assert_same(array('amount' => 20048, 'detail' => '2027-03-31'), pg_subscription_price_notice_plan(subscriptions_renewal_test_row(array('amount' => '17000')), $row, '16990', '17000', $next - 20 * 86400), 'a notice applied, then another change');
	pg_assert_same(array('amount' => 17688, 'detail' => '2027-03-31'), pg_subscription_price_notice_plan($row, $row, '14990', '20048', $next - 20 * 86400), 'the price set back before it applied: told again');

	foreach (array('paused', 'cancelled', 'ended') as $status) {
		pg_assert_same(null, pg_subscription_price_notice_plan(subscriptions_renewal_test_row(array('status' => $status)), $row, '16990', null, $next - 20 * 86400), $status);
	}

	pg_assert_same(null, pg_subscription_price_notice_plan(subscriptions_renewal_test_row(array('next_charge_at' => '0')), $row, '16990', null, $next - 20 * 86400), 'held');
}

function test_subscription_price_decrease_is_noticed_the_same_way()
{
	$next = mktime(10, 0, 0, 3, 31, 2027);
	$row = subscriptions_renewal_test_price_row();

	pg_assert_same(array('amount' => 12990 + 2338, 'detail' => '2027-03-31'), pg_subscription_price_notice_plan($row, $row, '12990', null, $next - 15 * 86400));
}

// The notice run: one event and one mail, under the lock; a second run
// writes nothing for the same amount.
function test_subscription_price_notice_run_writes_once()
{
	$now = mktime(10, 0, 0, 3, 31, 2027) - 15 * 86400;
	$store = subscriptions_renewal_test_price_row(array('product_price' => '16990'));
	$calls = array();
	$GLOBALS['subscriptions_renewal_test_mails'] = array();
	$io = subscriptions_renewal_test_io($store, $calls, array());
	$io['notice_read'] = function ($id) use (&$store) {
		return $store;
	};

	pg_assert_same('noticed', pg_subscription_price_notice_run_row($store, $now, $io));
	pg_assert_same(array('lock', array('event', 'price_notice', 0, 20048, '2027-03-31', ''), 'unlock'), $calls);
	pg_assert_same('subscription_price_changed', $GLOBALS['subscriptions_renewal_test_mails'][0]['action']);
	pg_assert_same('2027-03-31', $GLOBALS['subscriptions_renewal_test_mails'][0]['detail']);

	$store['notice_amount'] = '20048';
	$calls = array();

	pg_assert_same('nothing', pg_subscription_price_notice_run_row($store, $now + 86400, $io));
	pg_assert_same(array(), $calls, 'no second notice');
	pg_assert_same(1, count($GLOBALS['subscriptions_renewal_test_mails']));

	$store['notice_amount'] = null;
	$calls = array();
	pg_assert_same('skipped', pg_subscription_price_notice_run_row($store, $now, subscriptions_renewal_test_io($store, $calls, array(), false) + array('notice_read' => $io['notice_read'])), 'another run holds the row');
}

function test_subscription_price_due_on_or_after_its_date()
{
	$row = subscriptions_renewal_test_row();
	$notice = array('amount' => '20048', 'detail' => '2027-03-31');

	pg_assert_same(20048, pg_subscription_price_due($row, $notice, mktime(10, 0, 0, 3, 31, 2027)));
	pg_assert_same(20048, pg_subscription_price_due($row, $notice, mktime(10, 0, 0, 4, 3, 2027)), 'a late run');
	pg_assert_same(0, pg_subscription_price_due($row, $notice, mktime(10, 0, 0, 3, 30, 2027)), 'a retry before the date');
	pg_assert_same(0, pg_subscription_price_due($row, array('amount' => '17688', 'detail' => '2027-03-31'), mktime(10, 0, 0, 4, 1, 2027)), 'already the amount');
	pg_assert_same(0, pg_subscription_price_due($row, null, mktime(10, 0, 0, 4, 1, 2027)));
	pg_assert_same(0, pg_subscription_price_due($row, array('amount' => '20048', 'detail' => 'x'), mktime(10, 0, 0, 4, 1, 2027)));
}

// The renewal on the date charges the new amount, writes it to the row and
// the order's line moves with it.
function test_subscription_renewal_charges_the_noticed_amount()
{
	foreach (array(20048 => array(16990, 3058), 15328 => array(12990, 2338)) as $amount => $line) {
		$store = subscriptions_renewal_test_row();
		$calls = array();
		$io = subscriptions_renewal_test_io($store, $calls, array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_1'));
		$io['price_notice'] = function ($id) use ($amount) {
			return array('amount' => (string) $amount, 'detail' => '2027-03-31');
		};

		pg_assert_same('paid', pg_subscription_renewal_run_row(7, mktime(10, 0, 0, 4, 1, 2027), $io));
		pg_assert_same($amount, (int) $calls[2][2]['amount'], $amount . ': charged');
		pg_assert_same($amount, (int) $store['amount'], $amount . ': the row holds it');
		pg_assert_same(array('event', 'renewed', 880, $amount, '', ''), $calls[5]);

		$figures = pg_subscription_renewal_figures(array('amount' => $amount), array('price' => '14990', 'quantity' => '1', 'tax_total' => '2698'));
		pg_assert_same(array($line[0], $line[1]), array($figures['price'], $figures['tax']), $amount . ': the line at the new price');
	}

	$store = subscriptions_renewal_test_row();
	$calls = array();
	$io = subscriptions_renewal_test_io($store, $calls, array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_1'));
	$io['price_notice'] = function ($id) {
		return array('amount' => '20048', 'detail' => '2027-04-30');
	};

	pg_subscription_renewal_run_row(7, mktime(10, 0, 0, 4, 1, 2027), $io);
	pg_assert_same(17688, (int) $calls[2][2]['amount'], 'before the date: the old amount');
	pg_assert_same('17688', $store['amount']);
}

function test_subscription_price_mail()
{
	pg_assert_same('subscription_price_changed', pg_subscription_mail_action('price_notice', 'active', 'active'));

	$fields = pg_subscription_price_mail_fields(array('currency' => 'TRY'), array('amount' => '20048', 'detail' => '2027-03-31'));
	pg_assert_same(array(
		array('name' => 'subscription_new_amount', 'data' => pg_format_number(200.48, 2) . ' TRY', 'type' => ''),
		array('name' => 'subscription_new_date', 'data' => '2027-03-31', 'type' => 'date'),
	), $fields);
	pg_assert_same(2, count(pg_subscription_price_mail_fields(array(), null)), 'passed empty');
}
