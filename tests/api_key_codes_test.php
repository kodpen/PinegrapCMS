<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's key codes in
 * includes/api/resources/key_codes.php: what a code does at checkout today,
 * the same states as a filter, the codes it makes, the route table and the
 * scope that goes with it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/key_codes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// A key_codes row as api_key_code_select() reads it.
function api_key_codes_test_row($changes = array())
{
	return array_merge(array(
		'id' => '7', 'code' => 'K7Q2M9X4TA', 'offer_code' => 'SUMMER', 'enabled' => '1',
		'expiration_date' => '0000-00-00', 'single_use' => '1', 'report' => 'key_code', 'notes' => '',
		'timestamp' => '1760000000', 'last_modified_username' => null, 'offer_running' => '1',
	), $changes);
}

function test_api_key_codes_status_says_what_the_code_does_today()
{
	$today = '2026-10-13';

	pg_assert_same('active', api_key_code_status(api_key_codes_test_row(), $today), 'on, no expiry, offer running');
	pg_assert_same('active', api_key_code_status(api_key_codes_test_row(array('expiration_date' => '2026-10-13')), $today), 'works through its last day');
	pg_assert_same('expired', api_key_code_status(api_key_codes_test_row(array('expiration_date' => '2026-10-12')), $today), 'past its last day');
	pg_assert_same('disabled', api_key_code_status(api_key_codes_test_row(array('enabled' => '0')), $today), 'switched off, or a used single-use code');
	pg_assert_same('offer_inactive', api_key_code_status(api_key_codes_test_row(array('offer_running' => '0')), $today), 'its offer is not running');
	pg_assert_same('active', api_key_code_status(api_key_codes_test_row(array('offer_running' => '2')), $today), 'two offers share the code');
}

function test_api_key_codes_status_precedence()
{
	$today = '2026-10-13';

	pg_assert_same('disabled', api_key_code_status(api_key_codes_test_row(array('enabled' => '0', 'expiration_date' => '2026-01-01', 'offer_running' => '0')), $today), 'off before expired');
	pg_assert_same('expired', api_key_code_status(api_key_codes_test_row(array('expiration_date' => '2026-01-01', 'offer_running' => '0')), $today), 'expired before offer_inactive');
}

function test_api_key_codes_status_filter_matches_the_four_states()
{
	$today = '2026-10-13';

	pg_assert_same("key_codes.enabled <> '1'", api_key_code_status_clause('disabled', $today));

	$expired = api_key_code_status_clause('expired', $today);
	pg_assert_contains("key_codes.expiration_date < '2026-10-13'", $expired);
	pg_assert_false(strpos($expired, 'offers') !== false, 'expired does not look at the offers');

	pg_assert_contains("NOT EXISTS (SELECT 1 FROM offers", api_key_code_status_clause('offer_inactive', $today));
	pg_assert_contains("AND NOT (key_codes.expiration_date", api_key_code_status_clause('offer_inactive', $today));

	$active = api_key_code_status_clause('active', $today);
	pg_assert_contains("AND EXISTS (SELECT 1 FROM offers", $active);
	pg_assert_contains("offers.end_date >= '2026-10-13'", $active);

	pg_assert_same('', api_key_code_status_clause('used', $today), 'no such state');
}

function test_api_key_codes_expiry_reads_the_empty_date_as_none()
{
	pg_assert_same(null, api_key_code_date('0000-00-00'));
	pg_assert_same(null, api_key_code_date(''));
	pg_assert_same('2026-12-31', api_key_code_date('2026-12-31'));
}

function test_api_key_codes_made_codes_are_ten_capitals_and_digits()
{
	$first = api_key_code_random();

	pg_assert_same(1, preg_match('/^[0-9A-Z]{10}$/', $first), $first);
	pg_assert_true($first !== api_key_code_random(), 'two codes differ');
}

function test_api_key_codes_routes_reach_a_handler_under_the_key_codes_scope()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'key_codes.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		$write = ($route['method'] !== 'GET');

		pg_assert_same($write ? 'key_codes:write' : 'key_codes:read', $route['scope'], $route['id'] . ' scope');

		if ($write) {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}
	}

	pg_assert_same(6, $found, 'list, get, create, update, delete and its POST address');
}

// The router takes the first route that matches.
function test_api_key_codes_and_attribute_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (api_schema() as $route) {
		$methods = array_merge(array($route['method']), isset($route['also_accepts']) ? (array) $route['also_accepts'] : array());

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			if ((strpos($route['id'], 'key_codes.') === 0) || (strpos($route['id'], 'product_attributes.') === 0)) {
				pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			}

			$seen[$key] = $route['id'];
		}
	}
}

// Minting a code is handing out a discount, so the scope is its own and is
// not implied by the offers or gift cards a key already holds.
function test_api_key_codes_scope_is_its_own()
{
	pg_assert_true(in_array('key_codes:read', api_scopes_all(), true), 'key_codes:read is a scope');
	pg_assert_true(in_array('key_codes:write', api_scopes_all(), true), 'key_codes:write is a scope');

	$expanded = api_scopes_expand(array('offers:write', 'gift_cards:write'));

	pg_assert_false(in_array('key_codes:read', $expanded, true), 'not read through offers or gift cards');
	pg_assert_false(in_array('key_codes:write', $expanded, true), 'not written through offers or gift cards');

	pg_assert_true(in_array('key_codes:read', api_scopes_expand(array('key_codes:write')), true), 'write implies read once ticked');
}

// Offered with the store's other scopes, so not on a site that runs no store.
function test_api_key_codes_scope_needs_the_store()
{
	if (defined('ECOMMERCE') && ECOMMERCE === true) {
		return;
	}

	$owner = array(
		'id' => 1, 'username' => 'admin', 'role' => 0,
		'manage_ecommerce' => true, 'manage_contacts' => false, 'manage_forms' => false,
	);

	pg_assert_false(in_array('key_codes:read', api_owner_scopes($owner), true), 'no store, no key codes');
}
