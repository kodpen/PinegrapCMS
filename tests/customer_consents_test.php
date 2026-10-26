<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's consent record over the external API: GET
 * /customers/{id}/consents (includes/api/resources/customers.php) and the
 * customer.consent_changed event a consent_log row announces
 * (includes/fn/events.php). No database is used.
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

function customer_consents_test_row($changes = array())
{
	return array_merge(array(
		'created_at' => 1767225600,
		'person_token' => 'tok',
		'user_id'    => 0,
		'contact_id' => 12,
		'channel'    => 'email',
		'purpose'    => 'marketing',
		'status'     => 'refused',
		'source'     => 'unsubscribe',
		'ip'         => '192.0.2.1',
		'user_agent' => 'Browser',
	), $changes);
}

// A person's answer names the customer and the row, never the IP or browser.
function test_consent_changed_event_payload()
{
	pg_assert_same(array(
		'id'      => 12,
		'consent' => array(
			'id'         => 5,
			'channel'    => 'email',
			'purpose'    => 'marketing',
			'status'     => 'refused',
			'source'     => 'unsubscribe',
			'created_at' => '2026-01-01T00:00:00Z',
		),
	), pg_consent_changed_event(5, customer_consents_test_row(), 12));
}

// Cookie answers, pending rows, imports and rows with no customer announce nothing.
function test_consent_changed_event_quiet_rows()
{
	pg_assert_same(null, pg_consent_changed_event(5, customer_consents_test_row(array('channel' => 'cookie')), 12));
	pg_assert_same(null, pg_consent_changed_event(5, customer_consents_test_row(array('status' => 'pending')), 12));
	pg_assert_same(null, pg_consent_changed_event(5, customer_consents_test_row(array('source' => 'import')), 12));
	pg_assert_same(null, pg_consent_changed_event(5, customer_consents_test_row(), 0));
	pg_assert_true(is_array(pg_consent_changed_event(5, customer_consents_test_row(array('source' => 'iys')), 12)));
}

// The event is in the catalogue, so the queue does not drop it.
function test_consent_changed_event_is_catalogued()
{
	pg_assert_true(api_webhook_event_is_valid('customer.consent_changed'));
}

// The route is declared once, read-only, with the customers scope.
function test_customer_consents_route()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'customers.consents') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found));
	pg_assert_same('GET', $found['method']);
	pg_assert_same('/customers/{id}/consents', $found['path']);
	pg_assert_same('customers:read', $found['scope']);
	pg_assert_same('api_customers_consents', $found['handler']);
}

// The listing reads the contact's and its accounts' rows, leaves cookie answers
// out, and pages by row id.
function test_customer_consents_sql()
{
	$first = api_customer_consents_sql(12, 0, 51);

	pg_assert_contains("contact_id = '12'", $first);
	pg_assert_contains("user_contact = '12'", $first);
	pg_assert_contains("channel <> 'cookie'", $first);
	pg_assert_contains('ORDER BY id DESC', $first);
	pg_assert_contains('LIMIT 51', $first);
	pg_assert_false(strpos($first, "AND (id < '") !== false);
	pg_assert_contains("AND (id < '40')", api_customer_consents_sql(12, 40, 51));
	pg_assert_contains('SELECT id, created_at, channel, purpose, status, source', $first);
}

// The presenter gives what the schema declares and nothing of the IP or browser.
function test_customer_consents_present_matches_schema()
{
	$row = customer_consents_test_row(array('id' => 7));
	$out = api_consent_present($row);

	pg_assert_same(array_keys(api_consent_schema()), array_keys($out));
	pg_assert_same(7, $out['id']);
	pg_assert_same('2026-01-01T00:00:00Z', $out['created_at']);
	pg_assert_same(1767225600, $out['created_at_unix']);
}
