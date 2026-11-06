<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's store credit over the external API (GET and POST
 * /customers/{id}/credit, POST /customers/{id}/credit/{card_id}/void in
 * includes/api/resources/customer_credit.php), the credit.* webhook events
 * (includes/fn/store_credit_events.php) and the erasure of store credit. No
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/customer_credit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function api_customer_credit_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function api_customer_credit_test_card(array $over = array())
{
	return array_merge(array(
		'id' => '31', 'user_id' => '17', 'code' => '1234567812345678', 'amount' => '5000', 'balance' => '3200',
		'kind' => 'manual', 'reason' => 'Late delivery', 'issued_by' => '4', 'expires_at' => '0',
		'expiration_date' => '0000-00-00', 'created_timestamp' => '1801213200',
	), $over);
}

// Which account a new card goes to.
function test_api_customer_credit_pick_account()
{
	pg_assert_same('none', api_customer_credit_pick_account(array(), 0), 'no account');
	pg_assert_same('none', api_customer_credit_pick_account(array(), 17), 'no account, one sent');
	pg_assert_same(17, api_customer_credit_pick_account(array(17), 0), 'the only one');
	pg_assert_same(17, api_customer_credit_pick_account(array(17), 17), 'the only one, sent');
	pg_assert_same('other', api_customer_credit_pick_account(array(17), 18), 'another customer\'s account');
	pg_assert_same('choose', api_customer_credit_pick_account(array(17, 21), 0), 'several, none sent');
	pg_assert_same(21, api_customer_credit_pick_account(array(17, 21), '21'), 'several, one sent');
}

// A card as answered: no code, money in minor units, state and last day as
// My Account reads them.
function test_api_store_credit_card_present()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);
	$card = api_store_credit_card_present(api_customer_credit_test_card(), $now);

	pg_assert_same(31, $card['id'], 'id');
	pg_assert_same(17, $card['account_id'], 'account');
	pg_assert_same('manual', $card['kind'], 'kind');
	pg_assert_same(5000, $card['amount'], 'amount');
	pg_assert_same(3200, $card['balance'], 'balance');
	pg_assert_same('open', $card['status'], 'open');
	pg_assert_same(null, $card['last_day'], 'never ends');
	pg_assert_same(null, $card['expires_at'], 'no end moment');
	pg_assert_same(4, $card['issued_by'], 'issued by');
	pg_assert_true(!array_key_exists('code', $card), 'the code is never answered');
	pg_assert_same(array_keys(api_store_credit_card_schema()), array_keys($card), 'every declared field, in order');

	$used = api_store_credit_card_present(api_customer_credit_test_card(array('balance' => '0', 'issued_by' => '0')), $now);
	pg_assert_same('used', $used['status'], 'spent or voided');
	pg_assert_same(null, $used['issued_by'], 'the shop itself');

	$ends = mktime(0, 0, 0, 12, 1, 2026);
	$dated = api_store_credit_card_present(api_customer_credit_test_card(array('expires_at' => (string) $ends, 'expiration_date' => '2026-11-30')), $now);
	pg_assert_same('2026-11-30', $dated['last_day'], 'the day before the end moment');

	$gone = api_store_credit_card_present(api_customer_credit_test_card(array('expires_at' => (string) ($now - 10))), $now);
	pg_assert_same('expired', $gone['status'], 'past its end');
}

// The last day a card is given: the start of the next day.
function test_store_credit_last_day_expires()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	pg_assert_same(0, pg_credit_last_day_expires('', $now), 'never');
	pg_assert_same(0, pg_credit_last_day_expires(' ', $now), 'blank is never');
	pg_assert_same(mktime(0, 0, 0, 11, 5, 2026), pg_credit_last_day_expires('2026-11-04', $now), 'today runs to its end');
	pg_assert_same(-1, pg_credit_last_day_expires('2026-11-03', $now), 'yesterday');
	pg_assert_same(-1, pg_credit_last_day_expires('2026-02-30', $now), 'impossible day');
	pg_assert_same(-1, pg_credit_last_day_expires('04.11.2026', $now), 'other format');
	pg_assert_same(0, pg_credit_last_day_expires(array('2026-12-01'), $now), 'an array reads as no day, as the staff form did');
}

// Three routes under gift_cards:*, the writes rehearsable, the customers
// row rule before anything, the stop above the write.
function test_api_customer_credit_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same(array('GET', '/customers/{id}/credit', 'gift_cards:read', 'CustomerCredit'),
		array($routes['customers.credit']['method'], $routes['customers.credit']['path'], $routes['customers.credit']['scope'], $routes['customers.credit']['returns']), 'the read');
	pg_assert_same(array('POST', '/customers/{id}/credit', 'gift_cards:write', 'StoreCreditCard', 201),
		array($routes['customers.credit.issue']['method'], $routes['customers.credit.issue']['path'], $routes['customers.credit.issue']['scope'], $routes['customers.credit.issue']['returns'], $routes['customers.credit.issue']['status']), 'the issue');
	pg_assert_same(array('POST', '/customers/{id}/credit/{card_id}/void', 'gift_cards:write', 'StoreCreditCard'),
		array($routes['customers.credit.void']['method'], $routes['customers.credit.void']['path'], $routes['customers.credit.void']['scope'], $routes['customers.credit.void']['returns']), 'the void');

	foreach (array('issue' => 'pg_credit_issue(', 'void' => 'pg_credit_void(') as $action => $write) {
		pg_assert_true(!empty($routes['customers.credit.' . $action]['dry_run']), $action . ' rehearsable');

		$source = api_customer_credit_test_source('api_customers_credit_' . $action);
		$rule = strpos($source, 'api_customer_reachable_or_fail(');
		$reason = strpos($source, 'api_customer_credit_reason_or_fail(');
		$stop = strpos($source, 'api_dry_run_stop(');
		$at = strpos($source, $write);
		pg_assert_true(($rule !== false) && ($reason > $rule) && ($stop > $reason) && ($at > $stop), $action . ': row rule, checks, the rehearsal stop, then the write');
	}

	pg_assert_contains('api_customer_reachable_or_fail(', api_customer_credit_test_source('api_customers_credit'), 'the read keeps the row rule');
	pg_assert_contains("'manual'", api_customer_credit_test_source('api_customers_credit_issue'), 'issued as staff credit');
}

// The events are in the catalogue and announced by their writers.
function test_store_credit_webhook_events()
{
	foreach (array('issued', 'used', 'expired') as $name) {
		pg_assert_true(array_key_exists('credit.' . $name, api_webhook_events()), 'credit.' . $name . ' is in the catalogue');
	}

	pg_assert_contains("pg_credit_announce('credit.issued'", api_customer_credit_test_source('pg_credit_issue'), 'issue announces');
	pg_assert_contains("pg_credit_announce('credit.expired'", api_customer_credit_test_source('pg_credit_expire_card'), 'the expiry pass announces');
	pg_assert_contains("pg_credit_announce('credit.used'", api_customer_credit_test_source('pg_order_complete_gift_cards'), 'the redemption announces');
	pg_assert_contains('pg_credit_owner_select_sql()', api_customer_credit_test_source('pg_order_complete_gift_cards'), 'the redemption reads the owner');
}

// The payload: the card and its customer, never the code.
function test_store_credit_webhook_data()
{
	$card = api_customer_credit_test_card(array('customer_id' => '12', 'expires_at' => '1806476400'));
	$data = pg_credit_event_data($card, array('order_id' => '501', 'amount_used' => 1800));

	pg_assert_same(31, $data['id'], 'card id');
	pg_assert_same(17, $data['account_id'], 'account');
	pg_assert_same(12, $data['customer_id'], 'customer record');
	pg_assert_same('manual', $data['kind'], 'kind');
	pg_assert_same(3200, $data['balance'], 'balance');
	pg_assert_same('2027-03-31T07:00:00Z', $data['expires_at'], 'end moment');
	pg_assert_same(501, $data['order_id'], 'order');
	pg_assert_same(1800, $data['amount_used'], 'amount used');
	pg_assert_true(!array_key_exists('code', $data), 'no code');

	$plain = pg_credit_event_data(api_customer_credit_test_card(array('customer_id' => '0')));
	pg_assert_same(null, $plain['customer_id'], 'an account that is no contact');
	pg_assert_same(null, $plain['expires_at'], 'never ends');
}

// Erasure voids what is left before the cards stop naming the account.
function test_store_credit_erasure_voids_then_clears()
{
	$tables = pg_privacy_erase_tables();

	pg_assert_same('gift_cards', $tables['store_credit']['table'], 'the gift_cards table');
	pg_assert_same(array('user_id' => 'user_ids'), $tables['store_credit']['find'], 'found by the person\'s accounts');
	pg_assert_same(array('user_id' => 0), $tables['store_credit']['fields'], 'the account is cleared');

	$source = api_customer_credit_test_source('_pg_privacy_erase_apply_store_credit');
	$void = strpos($source, 'pg_credit_void(');
	$write = strpos($source, '_pg_privacy_erase_write(');
	pg_assert_true(($void !== false) && ($write > $void), 'voided first, then cleared');

	$order = array_keys($tables);
	pg_assert_true(array_search('store_credit', $order, true) < array_search('user', $order, true), 'before the account goes');
}
