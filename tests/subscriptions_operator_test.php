<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the operator's Subscriptions screen
 * (includes/fn/subscriptions_operator.php, view_subscriptions.php): the
 * list's filters, the screen's gate and form token, which moves an operator
 * may make, each allowed move written as an event naming the operator, each
 * refused move with its reason, and no path that cancels at once or ends a
 * row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function subscriptions_operator_test_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'user_id' => '17', 'product_id' => '44', 'order_id' => '501', 'order_item_id' => '9001',
		'driver' => 'iyzico', 'status' => 'active', 'period' => 'monthly', 'amount' => '17688', 'currency' => 'TRY',
		'next_charge_at' => (string) mktime(10, 0, 0, 3, 31, 2027), 'retry_count' => '0', 'card_last4' => '4242',
		'card_ref' => '', 'created_at' => (string) mktime(10, 0, 0, 1, 31, 2027), 'cancelled_at' => '0', 'ended_at' => '0',
	), $over);
}

// Stand-ins for the database and the payment service, as in
// subscriptions_account_test.php: set_status follows the real rules and
// records the status_<to> event with its detail.
function subscriptions_operator_test_io(array &$store, array &$calls, $shared = false)
{
	return array(
		'read' => function ($id) use (&$store) {
			return ((int) $id === (int) $store['id']) ? $store : null;
		},
		'set_status' => function ($id, $to, $detail) use (&$store, &$calls) {
			$fields = pg_subscription_transition_fields($store, $to, 1000);
			if ($fields === null) {
				return false;
			}
			$store = array_merge($store, $fields);
			$calls[] = array('event', 'status_' . $to, $detail);
			return true;
		},
		'event' => function ($id, $kind, $detail) use (&$calls) {
			$calls[] = array('event', $kind, $detail);
			return 1;
		},
		'call' => function ($driver, $operation, $driver_row) use (&$calls) {
			$calls[] = array('call', $driver, $operation);
			return array('success' => true);
		},
		'card_shared' => function ($row) use ($shared) {
			return $shared;
		},
		'log' => function ($message) use (&$calls) {
			$calls[] = array('log');
		},
	);
}

function subscriptions_operator_test_user()
{
	return array('id' => 3, 'username' => 'ayse', 'email_address' => 'ayse@shop.example', 'role' => 2);
}

function test_subscription_operator_actions_by_status()
{
	pg_assert_same(array('pause', 'cancel'), pg_subscription_operator_actions(subscriptions_operator_test_row()));
	pg_assert_same(array('cancel'), pg_subscription_operator_actions(subscriptions_operator_test_row(array('status' => 'past_due'))), 'a past_due row is not paused: the retries go on');
	pg_assert_same(array('resume', 'cancel'), pg_subscription_operator_actions(subscriptions_operator_test_row(array('status' => 'paused'))));
	pg_assert_same(array(), pg_subscription_operator_actions(subscriptions_operator_test_row(array('status' => 'cancelled'))));
	pg_assert_same(array(), pg_subscription_operator_actions(subscriptions_operator_test_row(array('status' => 'ended'))));
}

function test_subscription_operator_detail_names_the_operator()
{
	pg_assert_same('operator: ayse (#3)', pg_subscription_operator_detail(subscriptions_operator_test_user()));
	pg_assert_same('operator: ayse@shop.example (#4)', pg_subscription_operator_detail(array('id' => 4, 'username' => '', 'email_address' => 'ayse@shop.example')));
	pg_assert_same(255, mb_strlen(pg_subscription_operator_detail(array('id' => 5, 'username' => str_repeat('ş', 400)))), 'cut to the column');
}

function test_subscription_operator_pause_resume_cancel_write_events_with_the_operator()
{
	$store = subscriptions_operator_test_row();
	$calls = array();
	$io = subscriptions_operator_test_io($store, $calls);
	$user = subscriptions_operator_test_user();

	pg_assert_same('done', pg_subscription_operator_action($user, 7, 'pause', $io)['result']);
	pg_assert_same('paused', $store['status']);
	pg_assert_same(array(array('event', 'status_paused', 'operator: ayse (#3)')), $calls);

	$calls = array();
	pg_assert_same('done', pg_subscription_operator_action($user, 7, 'resume', $io)['result']);
	pg_assert_same('active', $store['status']);
	pg_assert_same(array(array('event', 'status_active', 'operator: ayse (#3)')), $calls);

	$calls = array();
	$next = $store['next_charge_at'];
	pg_assert_same('done', pg_subscription_operator_action($user, 7, 'cancel', $io)['result']);
	pg_assert_same('cancelled', $store['status']);
	pg_assert_same($next, $store['next_charge_at'], 'runs until the end of the paid period');
	pg_assert_same(array(array('event', 'status_cancelled', 'operator: ayse (#3)'), array('call', 'iyzico', 'subscription_cancel')), $calls,
		'the stored card is forgotten as on the customer\'s cancel');
}

function test_subscription_operator_cancel_keeps_a_shared_card()
{
	$store = subscriptions_operator_test_row();
	$calls = array();

	pg_assert_same('done', pg_subscription_operator_action(subscriptions_operator_test_user(), 7, 'cancel', subscriptions_operator_test_io($store, $calls, true))['result']);
	pg_assert_same(array(array('event', 'status_cancelled', 'operator: ayse (#3)')), $calls);
}

function test_subscription_operator_refused_moves_say_why()
{
	$user = subscriptions_operator_test_user();
	$cases = array(
		array('past_due', 'pause'),
		array('active', 'resume'),
		array('paused', 'pause'),
		array('cancelled', 'cancel'),
		array('ended', 'resume'),
		array('active', 'end'),
		array('active', 'cancel_now'),
		array('active', 'update_card'),
	);

	foreach ($cases as $case) {
		$store = subscriptions_operator_test_row(array('status' => $case[0]));
		$calls = array();
		$result = pg_subscription_operator_action($user, 7, $case[1], subscriptions_operator_test_io($store, $calls));

		pg_assert_same('refused', $result['result'], $case[0] . ' ' . $case[1]);
		pg_assert_true($result['reason'] !== '', 'a reason for ' . $case[0] . ' ' . $case[1]);
		pg_assert_same($case[0], $store['status'], 'the row stays');
		pg_assert_same(array(), $calls, 'nothing written, no service called');
	}

	$store = subscriptions_operator_test_row();
	$calls = array();
	$result = pg_subscription_operator_action($user, 8, 'pause', subscriptions_operator_test_io($store, $calls));
	pg_assert_same('not_found', $result['result']);
	pg_assert_true($result['reason'] !== '');
}

function test_subscription_operator_move_lost_to_a_concurrent_one()
{
	$store = subscriptions_operator_test_row();
	$calls = array();
	$io = subscriptions_operator_test_io($store, $calls);
	$io['set_status'] = function () {
		return false;
	};

	$result = pg_subscription_operator_action(subscriptions_operator_test_user(), 7, 'cancel', $io);
	pg_assert_same('refused', $result['result']);
	pg_assert_true($result['reason'] !== '');
	pg_assert_same(array(), $calls, 'no card forgotten for a cancel that was not written');
}

function test_subscription_operator_has_no_cancel_now_path()
{
	foreach (array('active', 'past_due', 'paused', 'cancelled') as $status) {
		$actions = pg_subscription_operator_actions(subscriptions_operator_test_row(array('status' => $status)));
		pg_assert_same(false, in_array('end', $actions, true), $status);
		pg_assert_same(false, in_array('update_card', $actions, true), $status);
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_subscriptions.php');
	pg_assert_same(false, strpos($screen, "'ended'") !== false && strpos($screen, 'pg_subscription_set_status') !== false, 'the screen moves no row itself');
	pg_assert_same(false, strpos($screen, 'pg_subscription_set_status') !== false, 'moves go through pg_subscription_operator_action()');
	pg_assert_same(false, strpos($screen, 'refund') !== false, 'no refund on this screen');
}

function test_subscription_operator_screen_gate_and_token()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_subscriptions.php');

	pg_assert_true(preg_match('/\$user = validate_user\(\);\s*validate_ecommerce_access\(\$user\);/', $screen) === 1, 'gated like view_orders.php');
	pg_assert_true(strpos($screen, 'validate_token_field()') !== false, 'every POST checks the form token');
	pg_assert_true(strpos($screen, 'get_token_field()') !== false, 'the forms carry it');
	pg_assert_true(strpos($screen, 'pg_subscription_operator_action(') !== false);

	$menu = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	pg_assert_true(strpos($menu, "/view_subscriptions.php\\'") !== false, 'an entry in the orders menu');
	pg_assert_true(strpos($menu, "case 'view_subscriptions.php':") !== false, 'the orders row lights');
}

function test_subscription_operator_filter_reads_the_request()
{
	pg_assert_same(array('status' => '', 'next_from' => '', 'next_to' => '', 'q' => ''), pg_subscription_operator_filter(array()));
	pg_assert_same(array('status' => 'past_due', 'next_from' => '2027-03-01', 'next_to' => '2027-03-31', 'q' => 'Ayşe'),
		pg_subscription_operator_filter(array('status' => 'past_due', 'next_from' => '2027-03-01', 'next_to' => '2027-03-31', 'q' => '  Ayşe ')));
	pg_assert_same(array('status' => '', 'next_from' => '', 'next_to' => '', 'q' => ''),
		pg_subscription_operator_filter(array('status' => 'deleted', 'next_from' => '2027-02-30', 'next_to' => 'x', 'q' => array('a'))), 'unknown values are dropped');
	pg_assert_same(100, mb_strlen(pg_subscription_operator_filter(array('q' => str_repeat('a', 300)))['q']));
}

// e() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function subscriptions_operator_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function test_subscription_operator_where()
{
	subscriptions_operator_test_db_stub();
	pg_assert_same('1 = 1', pg_subscription_operator_where(pg_subscription_operator_filter(array())));

	$where = pg_subscription_operator_where(pg_subscription_operator_filter(array('status' => 'paused')));
	pg_assert_same("(s.status = 'paused')", $where);

	$from = mktime(0, 0, 0, 3, 1, 2027);
	$to = mktime(0, 0, 0, 4, 1, 2027);
	$where = pg_subscription_operator_where(pg_subscription_operator_filter(array('next_from' => '2027-03-01', 'next_to' => '2027-03-31')));
	pg_assert_same("(s.next_charge_at >= '" . $from . "') AND (s.next_charge_at < '" . $to . "')", $where, 'the last day is whole');

	$where = pg_subscription_operator_where(pg_subscription_operator_filter(array('q' => "o'Neil_5%")));
	pg_assert_true(strpos($where, "LIKE '%o\\'Neil\\\\_5\\\\%%'") !== false, 'quoted and LIKE-escaped: ' . $where);
	pg_assert_true(strpos($where, 'o.billing_email_address LIKE') !== false);
	pg_assert_true(strpos($where, 'u.user_username LIKE') !== false);
}
