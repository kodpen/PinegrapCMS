<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's subscriptions listing in
 * includes/api/resources/subscriptions.php: the list's filters, the row rule
 * of an owner with the user role, the customer fields on a subscription, the
 * route table and the customers scope that goes with it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/subscriptions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// A row as api_customer_subscriptions_select_sql() reads it.
function api_subscriptions_test_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'user_id' => '17', 'product_id' => '44', 'order_id' => '501', 'status' => 'active',
		'period' => 'monthly', 'amount' => '17688', 'currency' => 'TRY', 'next_charge_at' => '1806476400',
		'retry_count' => '0', 'card_last4' => '4242', 'created_at' => '1801213200', 'cancelled_at' => '0',
		'ended_at' => '0', 'product_name' => 'Coffee box', 'customer_id' => '12',
		'customer_name' => 'Ayşe Kaya', 'customer_email' => 'ayse@example.com',
	), $over);
}

// From the manager role up every subscription is listed; the user role
// reaches the subscriptions of the customers in its contact groups, as the
// customers routes do.
function test_api_subscriptions_owner_filter_follows_the_customers_rule()
{
	pg_assert_same('', api_subscription_owner_filter(array('id' => 9, 'role' => 2)), 'a manager sees every row');
	pg_assert_same('', api_subscription_owner_filter(array('id' => 9, 'role' => 0)), 'an administrator too');

	$filter = api_subscription_owner_filter(array('id' => 9, 'role' => 3));

	pg_assert_contains('u.user_contact IN (SELECT contacts_contact_groups_xref.contact_id', $filter, 'the account\'s contact');
	pg_assert_contains("users_contact_groups_xref.user_id = '9'", $filter, 'the owner\'s groups');
}

function test_api_subscriptions_list_filters()
{
	$manager = array('id' => 2, 'role' => 1);

	pg_assert_same(array(), api_subscriptions_where(array('limit' => 50), $manager), 'no filter');

	pg_assert_same(array(
		"s.status = 'past_due'",
		"u.user_contact = '12'",
		"s.product_id = '44'",
		"s.order_id = '501'",
		"s.updated_at >= '1760086400'",
	), api_subscriptions_where(array(
		'status' => 'past_due', 'customer_id' => 12, 'product_id' => 44, 'order_id' => 501, 'updated_since' => 1760086400,
	), $manager));

	$where = api_subscriptions_where(array('status' => 'active'), array('id' => 9, 'role' => 3));

	pg_assert_same(2, count($where), 'the row rule is one more condition');
	pg_assert_contains("users_contact_groups_xref.user_id = '9'", $where[1]);
}

// A subscription names its customer record, so a client can open it under
// GET /customers/{id}; an account without one gives null.
function test_api_subscriptions_present_carries_the_customer()
{
	$out = api_subscription_present(api_subscriptions_test_row(), true);

	pg_assert_same(12, $out['customer_id']);
	pg_assert_same('Ayşe Kaya', $out['customer_name']);
	pg_assert_same('ayse@example.com', $out['customer_email']);
	pg_assert_same(array_keys(api_subscription_schema()), array_keys($out), 'every field declared, in order');

	$out = api_subscription_present(api_subscriptions_test_row(array('customer_id' => '0', 'customer_name' => '', 'customer_email' => '')), true);

	pg_assert_same(null, $out['customer_id'], 'an account that is no contact');
	pg_assert_same(null, $out['customer_name']);
	pg_assert_same(null, $out['customer_email']);

	$schema = api_subscription_schema();

	pg_assert_same('integer?', $schema['customer_id']);
	pg_assert_same('string?', $schema['customer_name']);
	pg_assert_same('string?', $schema['customer_email']);
}

// The buyer's name and e-mail come from the first order and the card digits
// are payment data: customers:read alone never hands them out, the key must
// hold orders:read as well (the panel's subscriptions screen is an e-commerce
// screen).
function test_api_subscription_order_data_needs_orders_read()
{
	pg_assert_same(false, api_subscription_order_data_shown(array('customers:read')), 'customers:read alone');
	pg_assert_same(true, api_subscription_order_data_shown(array('customers:read', 'orders:read')), 'with orders:read');
	pg_assert_same(false, api_subscription_order_data_shown(null), 'no scopes');

	$out = api_subscription_present(api_subscriptions_test_row(), false);

	pg_assert_same(12, $out['customer_id'], 'the customer record stays');
	pg_assert_same(null, $out['customer_name'], 'no billing name');
	pg_assert_same(null, $out['customer_email'], 'no billing e-mail');
	pg_assert_same(null, $out['card_last4'], 'no card digits');
	pg_assert_same(17688, $out['amount'], 'the subscription itself stays');
	pg_assert_same(array_keys(api_subscription_schema()), array_keys($out), 'the same fields');

	$out = api_subscription_present(api_subscriptions_test_row(), true);

	pg_assert_same('Ayşe Kaya', $out['customer_name']);
	pg_assert_same('4242', $out['card_last4']);

	pg_assert_same('string?', api_subscription_schema()['card_last4']);
}

function test_api_subscriptions_select_reads_the_customer_but_never_the_card()
{
	$sql = api_customer_subscriptions_select_sql();

	pg_assert_contains('COALESCE(u.user_contact, 0) AS customer_id', $sql, 'the account\'s contact record');
	pg_assert_contains('AS customer_email', $sql);
	pg_assert_true(strpos($sql, 'card_ref') === false, 'the card token is never read');
	pg_assert_true(strpos($sql, 'remote_id') === false, 'nor the service\'s id');
}

function test_api_subscriptions_routes_reach_a_handler_under_the_customers_scope()
{
	$found = array();

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'subscriptions.') !== 0) {
			continue;
		}

		$found[] = $route['id'];

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_same('GET', $route['method'], $route['id'] . ' only reads');
		pg_assert_same('customers:read', $route['scope'], $route['id'] . ' scope');

		if ($route['id'] === 'subscriptions.list') {
			pg_assert_same(array('list' => 'Subscription'), $route['returns']);

			$names = array();

			foreach ($route['params'] as $param) {
				$names[] = $param['name'];

				if ($param['name'] === 'status') {
					pg_assert_same(pg_subscription_statuses(), $param['values'], 'the statuses the screen filters by');
				}
			}

			pg_assert_same(array('status', 'customer_id', 'product_id', 'order_id', 'updated_since', 'limit', 'cursor'), $names);
		}
	}

	pg_assert_same(array('subscriptions.list', 'subscriptions.get'), $found);
}
