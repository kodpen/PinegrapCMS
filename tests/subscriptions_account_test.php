<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer's subscriptions in My Account
 * (includes/fn/subscriptions_account.php): which actions a row offers,
 * another customer's row refused, each move through the status rules with
 * its event, the card update page, the card forgotten on cancel only when no
 * other line of the order uses it, a cancel that ends at the paid date, the
 * confirmation link and the section's forms.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function subscriptions_account_test_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'user_id' => '17', 'product_id' => '44', 'order_id' => '501', 'order_item_id' => '9001',
		'driver' => 'stripe', 'status' => 'active', 'period' => 'monthly', 'amount' => '17688', 'currency' => 'TRY',
		'next_charge_at' => (string) mktime(10, 0, 0, 3, 31, 2027), 'retry_count' => '0', 'card_last4' => '4242',
		'card_ref' => '', 'created_at' => (string) mktime(10, 0, 0, 1, 31, 2027), 'cancelled_at' => '0', 'ended_at' => '0',
	), $over);
}

// Stand-ins for the database and the payment service: one stored row, the
// calls in the order they were made. set_status follows the real rules
// (pg_subscription_transition_fields()) and writes the status_<to> event.
function subscriptions_account_test_io(array &$store, array &$calls, $answer = null, $shared = false)
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
		'call' => function ($driver, $operation, $driver_row) use (&$calls, $answer) {
			$calls[] = array('call', $driver, $operation, $driver_row['return_url'] ?? '');
			return ($answer !== null) ? $answer : array('success' => true);
		},
		'card_shared' => function ($row) use ($shared) {
			return $shared;
		},
		'log' => function ($message) use (&$calls) {
			$calls[] = array('log');
		},
	);
}

function test_subscription_customer_actions_by_status()
{
	pg_assert_same(array('pause', 'cancel', 'update_card'), pg_subscription_customer_actions(subscriptions_account_test_row()));
	pg_assert_same(array('cancel', 'update_card'), pg_subscription_customer_actions(subscriptions_account_test_row(array('status' => 'past_due'))), 'a past_due row is not paused: the retries go on');
	pg_assert_same(array('resume', 'cancel', 'update_card'), pg_subscription_customer_actions(subscriptions_account_test_row(array('status' => 'paused'))));
	pg_assert_same(array(), pg_subscription_customer_actions(subscriptions_account_test_row(array('status' => 'cancelled'))));
	pg_assert_same(array(), pg_subscription_customer_actions(subscriptions_account_test_row(array('status' => 'ended'))));
	pg_assert_same(array(), pg_subscription_customer_actions(array()));

	pg_assert_same('paused', pg_subscription_customer_action_status('pause'));
	pg_assert_same('active', pg_subscription_customer_action_status('resume'));
	pg_assert_same('cancelled', pg_subscription_customer_action_status('cancel'));
	pg_assert_same('', pg_subscription_customer_action_status('update_card'));
	pg_assert_same(null, pg_subscription_customer_action_status('end'));
}

function test_subscription_customer_action_check()
{
	$row = subscriptions_account_test_row();

	pg_assert_same('', pg_subscription_customer_action_check($row, 17, 'pause'));
	pg_assert_same('not_found', pg_subscription_customer_action_check($row, 18, 'pause'), 'another customer\'s row');
	pg_assert_same('not_found', pg_subscription_customer_action_check($row, 0, 'pause'), 'signed out');
	pg_assert_same('not_found', pg_subscription_customer_action_check(null, 17, 'pause'));
	pg_assert_same('refused', pg_subscription_customer_action_check($row, 17, 'resume'), 'an active row is not resumed');
	pg_assert_same('refused', pg_subscription_customer_action_check($row, 17, 'end'));
	pg_assert_same('refused', pg_subscription_customer_action_check(subscriptions_account_test_row(array('status' => 'cancelled')), 17, 'cancel'));
	pg_assert_same('refused', pg_subscription_customer_action_check(subscriptions_account_test_row(array('status' => 'ended')), 17, 'update_card'));
}

function test_subscription_customer_action_refuses_another_customer()
{
	$store = subscriptions_account_test_row();
	$calls = array();

	$result = pg_subscription_customer_action(18, 7, 'cancel', 'https://shop.example/account', subscriptions_account_test_io($store, $calls));

	pg_assert_same('not_found', $result['result']);
	pg_assert_same('active', $store['status'], 'the row stays');
	pg_assert_same(array(), $calls, 'nothing written, no service called');
}

function test_subscription_customer_pause_and_resume()
{
	$store = subscriptions_account_test_row(array('retry_count' => '0'));
	$calls = array();
	$io = subscriptions_account_test_io($store, $calls);

	pg_assert_same('done', pg_subscription_customer_action(17, 7, 'pause', '', $io)['result']);
	pg_assert_same('paused', $store['status']);
	pg_assert_same(array(array('event', 'status_paused', 'customer')), $calls);

	pg_assert_same('refused', pg_subscription_customer_action(17, 7, 'pause', '', $io)['result'], 'paused twice');

	$calls = array();
	pg_assert_same('done', pg_subscription_customer_action(17, 7, 'resume', '', $io)['result']);
	pg_assert_same('active', $store['status']);
	pg_assert_same(array(array('event', 'status_active', 'customer')), $calls);
}

function test_subscription_customer_move_lost_to_a_concurrent_one()
{
	$store = subscriptions_account_test_row();
	$calls = array();
	$io = subscriptions_account_test_io($store, $calls);
	$io['set_status'] = function () {
		return false;
	};

	pg_assert_same('refused', pg_subscription_customer_action(17, 7, 'cancel', '', $io)['result']);
	pg_assert_same(array(), $calls, 'no card forgotten for a cancel that was not written');
}

function test_subscription_customer_cancel_keeps_the_paid_period()
{
	$next = mktime(10, 0, 0, 3, 31, 2027);
	$store = subscriptions_account_test_row(array('next_charge_at' => (string) $next));
	$calls = array();

	pg_assert_same('done', pg_subscription_customer_action(17, 7, 'cancel', '', subscriptions_account_test_io($store, $calls))['result']);
	pg_assert_same('cancelled', $store['status']);
	pg_assert_same((string) $next, (string) $store['next_charge_at'], 'runs until the date already paid');
	pg_assert_same(1000, $store['cancelled_at']);
	pg_assert_same(array(array('event', 'status_cancelled', 'customer'), array('call', 'stripe', 'subscription_cancel', '')), $calls,
		'the card is forgotten after the move');

	// Never charged again: a cancelled row is not due.
	pg_assert_false(pg_subscription_renewal_is_due($store, $next + 60));

	// Ended by the job on the paid date, not before.
	pg_assert_false(pg_subscription_cancelled_is_over($store, $next - 1));
	pg_assert_true(pg_subscription_cancelled_is_over($store, $next));
	pg_assert_true(pg_subscription_transition_allowed('cancelled', 'ended'));
	pg_assert_same('', pg_subscription_mail_action('status_ended', 'cancelled', 'ended'), 'the cancel was mailed already');
}

function test_subscription_customer_cancel_past_due_ends_at_once()
{
	$store = subscriptions_account_test_row(array('status' => 'past_due', 'retry_count' => '1'));
	$calls = array();

	pg_assert_same('done', pg_subscription_customer_action(17, 7, 'cancel', '', subscriptions_account_test_io($store, $calls))['result']);
	pg_assert_true(pg_subscription_cancelled_is_over($store, (int) $store['next_charge_at'] - 86400), 'no paid period left');
	pg_assert_true(pg_subscription_cancelled_is_over(subscriptions_account_test_row(array('status' => 'cancelled', 'next_charge_at' => '0')), 1), 'held');
	pg_assert_false(pg_subscription_cancelled_is_over(subscriptions_account_test_row(), PHP_INT_MAX), 'only a cancelled row');
}

function test_subscription_customer_cancel_keeps_a_shared_card()
{
	$store = subscriptions_account_test_row();
	$calls = array();

	pg_subscription_customer_action(17, 7, 'cancel', '', subscriptions_account_test_io($store, $calls, null, true));

	pg_assert_same(array(array('event', 'status_cancelled', 'customer')), $calls, 'another line of the order still runs on the card');

	$store = subscriptions_account_test_row();
	$calls = array();

	pg_assert_same('done', pg_subscription_customer_action(17, 7, 'cancel', '', subscriptions_account_test_io($store, $calls, array('success' => false)))['result'],
		'the cancel stands when the service did not forget the card');
	pg_assert_same(array('log'), end($calls));
}

function test_subscription_customer_update_card()
{
	$store = subscriptions_account_test_row(array('status' => 'past_due'));
	$calls = array();
	$io = subscriptions_account_test_io($store, $calls, array('success' => true, 'status' => 'redirect', 'redirect_url' => 'https://checkout.stripe.com/c/pay/cs_1'));

	$result = pg_subscription_customer_action(17, 7, 'update_card', 'https://shop.example/account', $io);

	pg_assert_same(array('result' => 'redirect', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'), $result);
	pg_assert_same('past_due', $store['status'], 'no move');
	pg_assert_same(array(array('call', 'stripe', 'update_card', 'https://shop.example/account'), array('event', 'card_update_opened', 'customer')), $calls);

	foreach (array(array('success' => false), array('success' => true, 'redirect_url' => 'http://plain.example/'), array('success' => true, 'redirect_url' => 'javascript:x')) as $answer) {
		$calls = array();
		$result = pg_subscription_customer_action(17, 7, 'update_card', '', subscriptions_account_test_io($store, $calls, $answer));
		pg_assert_same('failed', $result['result']);
		pg_assert_same(1, count($calls), 'no event for a page that did not open');
	}
}

function test_subscription_customer_confirm_url()
{
	$row = subscriptions_account_test_row(array('status' => 'past_due'));
	$event = array('kind' => 'confirm_needed', 'detail' => 'https://hooks.stripe.com/redirect/authenticate/src_1');

	pg_assert_same('https://hooks.stripe.com/redirect/authenticate/src_1', pg_subscription_customer_confirm_url($row, $event));
	pg_assert_same('', pg_subscription_customer_confirm_url(subscriptions_account_test_row(), $event), 'only while past_due');
	pg_assert_same('', pg_subscription_customer_confirm_url($row, array('kind' => 'renewed', 'detail' => '')), 'paid since');
	pg_assert_same('', pg_subscription_customer_confirm_url($row, array('kind' => 'confirm_needed', 'detail' => '')), 'a link too long to keep');
	pg_assert_same('', pg_subscription_customer_confirm_url($row, array('kind' => 'confirm_needed', 'detail' => 'javascript:alert(1)')));
	pg_assert_same('', pg_subscription_customer_confirm_url($row, null));
}

function test_subscription_customer_section_html()
{
	pg_assert_same('', pg_subscription_customer_section_html(array(), '/s/subscription_action.php', '/account', 'tok'));

	$row = subscriptions_account_test_row(array('status' => 'past_due', 'retry_count' => '1', 'product_name' => 'Coffee <box>',
		'last_kind' => 'confirm_needed', 'last_at' => 0, 'renewal_kind' => 'confirm_needed', 'renewal_detail' => 'https://hooks.stripe.com/a'));
	$html = pg_subscription_customer_section_html(array($row), '/s/subscription_action.php', '/account', 'tok"x');

	pg_assert_contains('Coffee &lt;box&gt;', $html, 'escaped');
	pg_assert_contains('href="https://hooks.stripe.com/a"', $html);
	pg_assert_contains('name="token" value="tok&quot;x"', $html);
	pg_assert_contains('name="action" value="cancel"', $html);
	pg_assert_contains('name="action" value="update_card"', $html);
	pg_assert_false(strpos($html, 'value="pause"') !== false, 'not offered while past_due');
	pg_assert_same(2, substr_count($html, 'method="post" action="/s/subscription_action.php"'));
}
