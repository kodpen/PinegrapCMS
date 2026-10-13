<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's affiliate commissions in
 * includes/api/resources/commissions.php: the shape of a commission, the
 * affiliate looked up by its code, the list's filters, the route table and the
 * read-only scope that goes with it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/commissions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// The presenter's money and time helpers live in includes/api/response.php,
// which loads only behind the API's own entry. Stand-ins of the same shape, so
// the presenter can be read without it.
if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// A row as api_commission_select() reads it.
function api_commissions_test_row($changes = array())
{
	return array_merge(array(
		'id' => '12', 'reference_code' => 'K7Q2M9', 'affiliate_code' => 'Ayse', 'order_id' => '305',
		'amount' => '1250', 'status' => 'payable', 'recurring_commission_profile_id' => '0',
		'created_user_id' => '0', 'created_timestamp' => '1760000000',
		'last_modified_user_id' => '1', 'last_modified_timestamp' => '1760086400',
		'order_number' => '100305', 'period' => null, 'product_name' => null, 'product_short_description' => null,
		'created_username' => null, 'last_modified_username' => 'erdal',
	), $changes);
}

function test_api_commissions_commission_on_an_order()
{
	$affiliates = array('ayse' => array('id' => '41', 'affiliate_code' => 'ayse', 'affiliate_name' => 'Ayşe Kaya'));

	$commission = api_commission_present(api_commissions_test_row(), $affiliates);

	pg_assert_same(12, $commission['id']);
	pg_assert_same('K7Q2M9', $commission['reference_code']);
	pg_assert_same('payable', $commission['status']);
	pg_assert_same(1250, $commission['amount'], 'minor units');
	pg_assert_same('Ayşe Kaya', $commission['affiliate_name'], 'the code is matched without regard to case, as the column compares');
	pg_assert_same(41, $commission['affiliate_contact_id']);
	pg_assert_same(305, $commission['order_id']);
	pg_assert_same('100305', $commission['order_number']);
	pg_assert_same(false, $commission['recurring']);
	pg_assert_same(null, $commission['recurring_profile_id']);
	pg_assert_same(null, $commission['period']);
	pg_assert_same(null, $commission['created_by'], 'written by the checkout, not a person');
	pg_assert_same('erdal', $commission['updated_by']);
	pg_assert_same(1760086400, $commission['updated_at_unix']);

	pg_assert_same(array_keys(api_commission_schema()), array_keys($commission), 'every field declared, in order');
}

function test_api_commissions_recurring_commission_carries_its_profile()
{
	$commission = api_commission_present(api_commissions_test_row(array(
		'recurring_commission_profile_id' => '7', 'period' => 'monthly',
		'product_name' => 'Hosting', 'product_short_description' => 'Basic plan',
	)));

	pg_assert_same(true, $commission['recurring']);
	pg_assert_same(7, $commission['recurring_profile_id']);
	pg_assert_same('monthly', $commission['period']);
	pg_assert_same('Hosting', $commission['product_name']);
	pg_assert_same('Basic plan', $commission['product_description']);
}

function test_api_commissions_missing_affiliate_and_order_are_null()
{
	$commission = api_commission_present(api_commissions_test_row(array('order_id' => '0', 'order_number' => null)), array());

	pg_assert_same(null, $commission['affiliate_name'], 'no contact carries the code any more');
	pg_assert_same(null, $commission['affiliate_contact_id']);
	pg_assert_same('Ayse', $commission['affiliate_code'], 'the code itself is kept');
	pg_assert_same(null, $commission['order_id']);
	pg_assert_same(null, $commission['order_number']);

	$affiliates = array('ayse' => array('id' => '41', 'affiliate_code' => 'Ayse', 'affiliate_name' => ''));

	$commission = api_commission_present(api_commissions_test_row(), $affiliates);

	pg_assert_same(null, $commission['affiliate_name'], 'an affiliate without a name');
	pg_assert_same(41, $commission['affiliate_contact_id']);
}

function test_api_commissions_affiliate_code_key_ignores_case()
{
	pg_assert_same(api_commission_code_key('AYŞE'), api_commission_code_key('ayşe'));
	pg_assert_false(api_commission_code_key('ayse') === api_commission_code_key('ayşe'), 'different letters stay different');
}

function test_api_commissions_list_filters()
{
	pg_assert_same(array(), api_commissions_where(array('limit' => 50)), 'no filter');

	pg_assert_same(array(
		"commissions.order_id = '305'",
		"commissions.created_timestamp >= '1760000000'",
		"commissions.created_timestamp < '1762678400'",
		"commissions.last_modified_timestamp >= '1760086400'",
	), api_commissions_where(array(
		'order_id' => 305, 'created_since' => 1760000000, 'created_before' => 1762678400, 'updated_since' => 1760086400,
	)));
}

function test_api_commissions_routes_reach_a_handler_under_the_read_scope()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'commissions.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_same('GET', $route['method'], $route['id'] . ' only reads');
		pg_assert_same('commissions:read', $route['scope'], $route['id'] . ' scope');
	}

	pg_assert_same(2, $found, 'list and get');

	foreach (api_schema() as $route) {
		if ($route['id'] === 'commissions.list') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'status') {
					pg_assert_same(api_commission_statuses(), $param['values'], 'the statuses the screen offers');
				}
			}
		}
	}
}

function test_api_commissions_scope_is_read_only()
{
	pg_assert_true(in_array('commissions:read', api_scopes_all(), true), 'commissions:read is a scope');
	pg_assert_false(in_array('commissions:write', api_scopes_all(), true), 'there is nothing to write');

	$groups = api_scope_groups();

	pg_assert_same('', $groups['commissions']['write']);
}

// The commissions screen opens to whoever manages the store, and only while
// the store is running.
function test_api_commissions_scope_follows_the_commissions_screen()
{
	$owner = array(
		'id' => 9, 'username' => 'editor', 'role' => 3,
		'manage_ecommerce' => true, 'manage_contacts' => false, 'manage_forms' => false,
	);

	$store_is_on = defined('ECOMMERCE') && ECOMMERCE === true;

	pg_assert_same($store_is_on, in_array('commissions:read', api_owner_scopes($owner), true), 'user with the store right');

	$owner['manage_ecommerce'] = false;

	pg_assert_false(in_array('commissions:read', api_owner_scopes($owner), true), 'user without the store right');
}

// A key made before this release holds what its operator ticked then.
function test_api_commissions_scope_is_not_given_to_existing_keys()
{
	$owner = array(
		'id' => 1, 'username' => 'admin', 'role' => 0,
		'manage_ecommerce' => true, 'manage_contacts' => true, 'manage_forms' => true,
	);

	$effective = api_effective_scopes(array('orders:write', 'gift_cards:write', 'meta:read'), $owner);

	pg_assert_false(in_array('commissions:read', $effective, true));
}
