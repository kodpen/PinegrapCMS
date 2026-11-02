<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's affiliate commissions in
 * includes/api/resources/commissions.php: the shape of a commission, the
 * affiliate looked up by its code, the list's filters, the status write, the
 * recurring commission profiles, the affiliate approval, the route table, the
 * scopes and the events that go with them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/commissions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');

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

function test_api_commissions_routes_reach_a_handler_under_their_scopes()
{
	$found = array();

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'commissions.') !== 0) {
			continue;
		}

		$found[] = $route['id'];

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		if ($route['method'] === 'GET') {
			pg_assert_same('commissions:read', $route['scope'], $route['id'] . ' scope');
		} else {
			pg_assert_same('commissions:write', $route['scope'], $route['id'] . ' scope');
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}

		if ($route['id'] === 'commissions.list') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'status') {
					pg_assert_same(api_commission_statuses(), $param['values'], 'the statuses the screen offers');
				}
			}
		}

		if ($route['id'] === 'commissions.update') {
			pg_assert_same('POST', $route['method'], 'an update is a POST');
			pg_assert_same(array('PATCH'), $route['also_accepts'], 'PATCH accepted as well');
			pg_assert_same('/commissions/{id}', $route['path']);
			pg_assert_same('Commission', $route['returns']);

			foreach ($route['params'] as $param) {
				if ($param['name'] === 'status') {
					pg_assert_same('body', $param['in']);
					pg_assert_true(!empty($param['required']), 'the status is what the screen saves');
					pg_assert_same(api_commission_statuses(), $param['values'], 'only a stored status');
				}
			}
		}

		if ($route['id'] === 'commissions.recurring_profiles.list') {
			pg_assert_same('/commissions/recurring-profiles', $route['path']);
			pg_assert_same(array('list' => 'CommissionRecurringProfile'), $route['returns']);
		}
	}

	pg_assert_same(array('commissions.list', 'commissions.recurring_profiles.list', 'commissions.recurring_profiles.get', 'commissions.get', 'commissions.update'), $found,
		'the profile routes are declared before /commissions/{id}, as a reader of the table expects');
}

// Approving an affiliate is the contact screen's right (approve_affiliate.php
// asks for contacts access), so the route sits under customers:write and the
// customers row rule, not under commissions.
function test_api_affiliates_approve_route()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'affiliates.approve') {
			$found = $route;
		}
	}

	pg_assert_true($found !== null, 'the route is declared');
	pg_assert_same('POST', $found['method']);
	pg_assert_same('/affiliates/{id}/approve', $found['path']);
	pg_assert_same('customers:write', $found['scope']);
	pg_assert_true(!empty($found['dry_run']), 'takes X-Dry-Run');
	pg_assert_same('Affiliate', $found['returns']);
	pg_assert_true(function_exists($found['handler']), $found['handler'] . '() exists');
	pg_assert_same(1, count($found['params']), 'the contact id alone: the code is generated, the welcome mail is the site\'s');
}

function test_api_commissions_scope_has_a_write_side()
{
	pg_assert_true(in_array('commissions:read', api_scopes_all(), true), 'commissions:read is a scope');
	pg_assert_true(in_array('commissions:write', api_scopes_all(), true), 'commissions:write is a scope');

	$groups = api_scope_groups();

	pg_assert_same('commissions:write', $groups['commissions']['write']);
	pg_assert_contains('commissions:read', api_scopes_expand(array('commissions:write')), 'a write implies the read');
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
	pg_assert_same($store_is_on, in_array('commissions:write', api_owner_scopes($owner), true), 'the status is moved on the same screen');

	$owner['manage_ecommerce'] = false;

	pg_assert_false(in_array('commissions:read', api_owner_scopes($owner), true), 'user without the store right');
	pg_assert_false(in_array('commissions:write', api_owner_scopes($owner), true));
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
	pg_assert_false(in_array('commissions:write', $effective, true));
}

// A row as api_commission_recurring_profile_select() reads it.
function api_commissions_test_profile_row($changes = array())
{
	return array_merge(array(
		'id' => '7', 'affiliate_code' => 'Ayse', 'order_id' => '305', 'order_item_id' => '9001', 'amount' => '500',
		'enabled' => '1', 'start_date' => '2026-03-15', 'period' => 'monthly', 'number_of_commissions' => '0',
		'product_name' => 'Hosting', 'product_short_description' => 'Basic plan', 'disabled_by_cancel' => '0',
		'created_user_id' => '0', 'created_timestamp' => '1760000000',
		'last_modified_user_id' => '1', 'last_modified_timestamp' => '1760086400',
		'order_number' => '100305', 'created_username' => null, 'last_modified_username' => 'erdal',
	), $changes);
}

function test_api_commissions_recurring_profile_present()
{
	$affiliates = array('ayse' => array('id' => '41', 'affiliate_code' => 'ayse', 'affiliate_name' => 'Ayşe Kaya'));

	$profile = api_commission_recurring_profile_present(api_commissions_test_profile_row(), $affiliates);

	pg_assert_same(7, $profile['id']);
	pg_assert_same('Ayse', $profile['affiliate_code']);
	pg_assert_same('Ayşe Kaya', $profile['affiliate_name']);
	pg_assert_same(41, $profile['affiliate_contact_id']);
	pg_assert_same(true, $profile['enabled']);
	pg_assert_same(500, $profile['amount'], 'minor units');
	pg_assert_same('2026-03-15', $profile['start_date']);
	pg_assert_same('monthly', $profile['period']);
	pg_assert_same(null, $profile['commission_limit'], '0 on the screen is Unlimited');
	pg_assert_same(305, $profile['order_id']);
	pg_assert_same('100305', $profile['order_number']);
	pg_assert_same(9001, $profile['order_item_id']);
	pg_assert_same('Hosting', $profile['product_name']);
	pg_assert_same('Basic plan', $profile['product_description']);
	pg_assert_same(false, $profile['disabled_by_cancel']);
	pg_assert_same(null, $profile['created_by']);
	pg_assert_same('erdal', $profile['updated_by']);
	pg_assert_same(1760086400, $profile['updated_at_unix']);
	pg_assert_same(array_keys(api_commission_recurring_profile_schema()), array_keys($profile), 'every field declared, in order');

	$profile = api_commission_recurring_profile_present(api_commissions_test_profile_row(array(
		'enabled' => '0', 'number_of_commissions' => '12', 'disabled_by_cancel' => '1', 'product_short_description' => '', 'order_number' => null,
	)), array());

	pg_assert_same(false, $profile['enabled']);
	pg_assert_same(12, $profile['commission_limit']);
	pg_assert_same(true, $profile['disabled_by_cancel'], 'stopped by the order\'s cancellation');
	pg_assert_same(null, $profile['product_description']);
	pg_assert_same(null, $profile['order_number']);
	pg_assert_same(null, $profile['affiliate_name']);
	pg_assert_same(null, $profile['affiliate_contact_id']);

	$profile = api_commission_recurring_profile_present(api_commissions_test_profile_row(array('disabled_by_cancel' => null)));

	pg_assert_same(false, $profile['disabled_by_cancel'], 'a site whose database update has not run yet');
}

function test_api_commissions_recurring_profiles_list_filters()
{
	pg_assert_same(array(), api_commission_recurring_profiles_where(array('limit' => 50)), 'no filter');

	pg_assert_same(array(
		"recurring_commission_profiles.affiliate_code = 'Ayse'",
		"recurring_commission_profiles.enabled = '1'",
		"recurring_commission_profiles.order_id = '305'",
		"recurring_commission_profiles.last_modified_timestamp >= '1760086400'",
	), api_commission_recurring_profiles_where(array(
		'affiliate_code' => 'Ayse', 'enabled' => true, 'order_id' => 305, 'updated_since' => 1760086400,
	)));

	pg_assert_same(array("recurring_commission_profiles.enabled = '0'"), api_commission_recurring_profiles_where(array('enabled' => false)), 'disabled ones');
}

// The status write the screen and the API share: what the event carries.
function test_commission_status_event_payload()
{
	$row = array('id' => '12', 'reference_code' => 'K7Q2M9', 'affiliate_code' => 'Ayse', 'order_id' => '305', 'amount' => '1250', 'status' => 'pending');

	pg_assert_same(array(
		'id' => 12, 'reference_code' => 'K7Q2M9', 'status' => 'payable', 'previous_status' => 'pending',
		'affiliate_code' => 'Ayse', 'order_id' => 305, 'amount' => 1250,
	), pg_commission_status_event($row, 'payable'));

	pg_assert_same(null, pg_commission_status_event(array('order_id' => '0') + $row, 'payable')['order_id'], 'a recurring commission without an order');
}

function test_commission_status_save_is_what_the_screen_calls()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/edit_commission.php');

	pg_assert_contains('pg_commission_status_save(', $screen, 'the screen writes through the shared function');
	pg_assert_true(strpos($screen, 'UPDATE commissions') === false, 'no inline write left');
	pg_assert_true(function_exists('pg_commission_status_save'));
}

// The approval the e-mail link's screen and the API share.
function test_affiliate_approval_code_keeps_an_existing_code()
{
	$generate = function () {
		return 'NEW01';
	};

	pg_assert_same('AYSE', pg_affiliate_approval_code('AYSE', $generate), 'a code the affiliate chose stays');
	pg_assert_same('NEW01', pg_affiliate_approval_code('', $generate), 'none yet: one is made');
	pg_assert_same('NEW01', pg_affiliate_approval_code(null, $generate));
}

function test_affiliate_approved_event_payload()
{
	pg_assert_same(array('id' => 41, 'affiliate_code' => 'AYSE', 'affiliate_name' => 'Ayşe Kaya'), pg_affiliate_approved_event('41', 'AYSE', 'Ayşe Kaya'));
	pg_assert_same(null, pg_affiliate_approved_event(41, 'AYSE', '')['affiliate_name'], 'an affiliate without a name');
}

function test_affiliate_approve_is_what_the_screen_calls()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/approve_affiliate.php');

	pg_assert_contains('pg_affiliate_approve(', $screen, 'the screen approves through the shared function');
	pg_assert_true(strpos($screen, 'UPDATE contacts') === false, 'no inline write left');
	pg_assert_true(function_exists('pg_affiliate_approve'));
}

function test_api_affiliate_present()
{
	$row = array('id' => '41', 'email_address' => 'ayse@example.com', 'affiliate_name' => 'Ayşe Kaya', 'affiliate_code' => 'AYSE', 'affiliate_approved' => '1', 'affiliate_commission_rate' => '12.50');

	$out = api_affiliate_present($row);

	pg_assert_same(41, $out['id']);
	pg_assert_same('ayse@example.com', $out['email']);
	pg_assert_same('Ayşe Kaya', $out['affiliate_name']);
	pg_assert_same('AYSE', $out['affiliate_code']);
	pg_assert_same(true, $out['approved']);
	pg_assert_same(12.5, $out['commission_rate'], 'per cent, as the contact screen shows it');
	pg_assert_same(array_keys(api_affiliate_schema()), array_keys($out), 'every field declared, in order');

	$out = api_affiliate_present(array('affiliate_name' => '', 'affiliate_approved' => '0', 'affiliate_code' => '') + $row);

	pg_assert_same(null, $out['affiliate_name']);
	pg_assert_same(null, $out['affiliate_code'], 'no code before the approval');
	pg_assert_same(false, $out['approved']);
}

function test_api_commissions_events_are_in_the_catalogue()
{
	$events = api_webhook_events();

	pg_assert_true(isset($events['commission.status_changed']), 'commission.status_changed');
	pg_assert_true(isset($events['affiliate.approved']), 'affiliate.approved');
	pg_assert_true(api_webhook_event_is_valid('commission.status_changed'));
}
