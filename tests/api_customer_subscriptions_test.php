<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's subscriptions over the external API: GET
 * /customers/{id}/subscriptions and the pause, resume and cancel moves
 * (includes/api/resources/customers.php), and the subscription.* events a
 * subscription event announces (includes/fn/subscription_renewal.php). No
 * database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/customers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function api_customer_subscriptions_test_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'user_id' => '17', 'product_id' => '44', 'order_id' => '501', 'order_item_id' => '9001',
		'driver' => 'stripe', 'remote_id' => '', 'status' => 'active', 'period' => 'monthly', 'amount' => '17688',
		'currency' => 'TRY', 'next_charge_at' => '1806476400', 'retry_count' => '0', 'card_last4' => '4242',
		'card_ref' => 'sealed:iv', 'created_at' => '1801213200', 'updated_at' => '1801213200',
		'cancelled_at' => '0', 'ended_at' => '0', 'product_name' => 'Coffee box',
	), $over);
}

function api_customer_subscriptions_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The rows of the customer's accounts, newest first, paged by id.
function test_api_customer_subscriptions_sql_reads_the_accounts_of_the_customer()
{
	$sql = api_customer_subscriptions_sql(12, 0, 51);

	pg_assert_contains("s.user_id IN (SELECT user_id FROM user WHERE user_contact = '12')", $sql, 'the accounts linked to the record');
	pg_assert_contains('ORDER BY s.id DESC', $sql, 'newest first');
	pg_assert_contains('LIMIT 51', $sql, 'one more than the page');
	pg_assert_true(strpos($sql, 's.id <') === false, 'the first page has no bound');
	pg_assert_contains("(s.id < '90')", api_customer_subscriptions_sql(12, 90, 51), 'a later page');
	pg_assert_true(strpos($sql, 'card_ref') === false, 'the card token is never read');
	pg_assert_contains("user_contact = '0'", api_customer_subscriptions_sql('x', 0, 5), 'a non-number reaches nobody');
}

// The presenter: money in minor units, moments as UTC, the moves the
// customer could make now, never the card token or the driver's ids.
function test_api_customer_subscription_present()
{
	$out = api_subscription_present(api_customer_subscriptions_test_row());

	pg_assert_same(7, $out['id'], 'id');
	pg_assert_same('Coffee box', $out['product_name'], 'product name');
	pg_assert_same(17688, $out['amount'], 'minor units');
	pg_assert_same('2027-03-31T07:00:00Z', $out['next_charge_at'], 'next charge as UTC');
	pg_assert_same(null, $out['cancelled_at'], 'not cancelled');
	pg_assert_same(array('pause', 'cancel'), $out['actions'], 'moves of an active row, no card page');
	pg_assert_true(!array_key_exists('card_ref', $out) && !array_key_exists('remote_id', $out), 'no token, no remote id');
	pg_assert_same(array_keys(api_subscription_schema()), array_keys($out), 'the declared fields in order');

	pg_assert_same(array('resume', 'cancel'), api_subscription_present(api_customer_subscriptions_test_row(array('status' => 'paused')))['actions'], 'paused');
	pg_assert_same(array('cancel'), api_subscription_present(api_customer_subscriptions_test_row(array('status' => 'past_due')))['actions'], 'past due cannot pause');
	pg_assert_same(array(), api_subscription_present(api_customer_subscriptions_test_row(array('status' => 'cancelled')))['actions'], 'cancelled');
}

// The history names the application that made the move, within the column.
function test_api_subscription_move_detail_names_the_application()
{
	pg_assert_same('api: Shop app (#5)', api_subscription_move_detail(array('id' => 5, 'name' => 'Shop app')), 'name and id');
	pg_assert_same('api: (#0)', api_subscription_move_detail(array()), 'no application');
	pg_assert_true(mb_strlen(api_subscription_move_detail(array('id' => 5, 'name' => str_repeat('ş', 400)))) <= 255, 'fits the detail column');
}

// The three moves are declared with dry_run, the read without; every
// handler goes through the customer's row rule and the shared move.
function test_api_customer_subscriptions_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same('GET', $routes['customers.subscriptions']['method'], 'the read');
	pg_assert_same('customers:read', $routes['customers.subscriptions']['scope'], 'read scope');

	foreach (array('pause', 'resume', 'cancel') as $action) {
		$route = $routes['customers.subscriptions.' . $action];
		pg_assert_same('POST', $route['method'], $action);
		pg_assert_same('/customers/{id}/subscriptions/{subscription_id}/' . $action, $route['path'], $action . ' path');
		pg_assert_same('customers:write', $route['scope'], $action . ' scope');
		pg_assert_true(!empty($route['dry_run']), $action . ' rehearsable');
		pg_assert_same('Subscription', $route['returns'], $action . ' returns');
	}

	pg_assert_contains('api_customer_reachable_or_fail(', api_customer_subscriptions_test_source('api_customer_subscription_move_check'), 'row rule');
	pg_assert_contains('pg_subscription_operator_refusal(', api_customer_subscriptions_test_source('api_customer_subscription_move_check'), 'the status rules');
	pg_assert_contains('pg_subscription_store_move(', api_customer_subscriptions_test_source('api_customer_subscription_move'), 'shared move');

	foreach (array('pause', 'resume', 'cancel') as $action) {
		$source = api_customer_subscriptions_test_source('api_customers_subscriptions_' . $action);
		$check = strpos($source, 'api_customer_subscription_move_check(');
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, 'api_customer_subscription_move(');
		pg_assert_true(($check !== false) && ($stop > $check) && ($write > $stop), $action . ': checks, then the rehearsal stop, then the write');
	}
}

// Which subscription event is announced as which webhook.
function test_subscription_webhook_event_names()
{
	pg_assert_same('subscription.renewed', pg_subscription_webhook_event('renewed', 'active', 'active'), 'renewed');
	pg_assert_same('subscription.renewed', pg_subscription_webhook_event('renewed', 'past_due', 'active'), 'a retry paid');
	pg_assert_same('subscription.failed', pg_subscription_webhook_event('renewal_failed', 'active', 'past_due'), 'failed');
	pg_assert_same('subscription.failed', pg_subscription_webhook_event('renewal_failed', 'past_due', 'ended'), 'the ladder ran out');
	pg_assert_same('subscription.failed', pg_subscription_webhook_event('confirm_needed', 'active', 'past_due'), 'waits for the customer');
	pg_assert_same('subscription.paused', pg_subscription_webhook_event('status_paused', 'active', 'paused'), 'paused');
	pg_assert_same('subscription.resumed', pg_subscription_webhook_event('status_active', 'paused', 'active'), 'resumed');
	pg_assert_same('subscription.cancelled', pg_subscription_webhook_event('status_cancelled', 'active', 'cancelled'), 'cancelled');
	pg_assert_same('', pg_subscription_webhook_event('status_active', 'past_due', 'active'), 'paid after a failure is the renewed event');
	pg_assert_same('', pg_subscription_webhook_event('status_ended', 'cancelled', 'ended'), 'the end is not announced');
	pg_assert_same('', pg_subscription_webhook_event('price_notice', 'active', 'active'), 'price notice');
	pg_assert_same('', pg_subscription_webhook_event('card_update_opened', 'active', 'active'), 'card page');

	foreach (array('renewed', 'failed', 'cancelled', 'paused', 'resumed') as $name) {
		pg_assert_true(array_key_exists('subscription.' . $name, api_webhook_events()), 'subscription.' . $name . ' is in the catalogue');
	}
}

// The payload: the identifiers, the move and the money, never the card.
function test_subscription_webhook_data()
{
	$data = pg_subscription_webhook_data(api_customer_subscriptions_test_row(array('status' => 'past_due', 'retry_count' => '1')), 12, 'renewal_failed', 'active', 0);

	pg_assert_same(7, $data['id'], 'subscription id');
	pg_assert_same(12, $data['customer_id'], 'customer');
	pg_assert_same(44, $data['product_id'], 'product');
	pg_assert_same('past_due', $data['status'], 'status after');
	pg_assert_same('active', $data['previous_status'], 'status before');
	pg_assert_same('renewal_failed', $data['kind'], 'event kind');
	pg_assert_same(null, $data['order_id'], 'no renewal order');
	pg_assert_same(17688, $data['amount'], 'amount');
	pg_assert_same('2027-03-31T07:00:00Z', $data['next_charge_at'], 'next charge');
	pg_assert_true(!array_key_exists('card_ref', $data), 'no card token');

	$paid = pg_subscription_webhook_data(api_customer_subscriptions_test_row(), 0, 'renewed', 'active', 812);
	pg_assert_same(812, $paid['order_id'], 'the renewal order');
	pg_assert_same(null, $paid['customer_id'], 'an account with no customer record');
}
