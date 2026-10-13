<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's e-mail campaigns in
 * includes/api/resources/campaigns.php: the send time read from and written
 * to the column, the sending totals, a recipient's status, who reaches which
 * campaigns, the route table and the scope that goes with it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/campaigns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// An owner as api_load_owner() builds it, with the e-mail right read raw from
// its column.
function api_campaigns_test_owner($role, $manage_emails = 'no')
{
	return array(
		'id' => 7, 'username' => 'editor', 'role' => $role,
		'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false,
		'manage_emails' => $manage_emails,
	);
}

function test_api_campaigns_no_send_time_means_now()
{
	pg_assert_same(0, api_campaign_start_unix('1000-01-01 00:00:00'), 'column default');
	pg_assert_same(0, api_campaign_start_unix('0000-00-00 00:00:00'), 'zero date of older rows');
	pg_assert_same(0, api_campaign_start_unix(''), 'empty');
	pg_assert_same(0, api_campaign_start_unix(null), 'null');
}

function test_api_campaigns_send_time_round_trips_through_the_column()
{
	$when = mktime(9, 30, 0, 11, 2, 2026);

	$column = api_campaign_start_time($when);

	pg_assert_same('2026-11-02 09:30:00', $column, 'written as the site\'s wall-clock time');
	pg_assert_same($when, api_campaign_start_unix($column), 'read back as the same moment');
}

function test_api_campaigns_clearing_the_send_time_writes_the_column_default()
{
	pg_assert_same('1000-01-01 00:00:00', api_campaign_start_time(''), 'empty string');
	pg_assert_same('1000-01-01 00:00:00', api_campaign_start_time(null), 'null');
}

function test_api_campaigns_a_given_up_recipient_counts_as_failed_not_sent()
{
	$counts = api_campaign_counts_from_row(array('total' => '10', 'sent' => '6', 'failed' => '1'));

	pg_assert_same(array('total' => 10, 'sent' => 6, 'failed' => 1, 'pending' => 3), $counts);
}

function test_api_campaigns_pending_never_goes_below_zero()
{
	$counts = api_campaign_counts_from_row(array('total' => '2', 'sent' => '2', 'failed' => '1'));

	pg_assert_same(0, $counts['pending']);
}

function test_api_campaigns_recipient_status_from_its_columns()
{
	$row = array('id' => '5', 'email_address' => 'a@example.com', 'contact_id' => '0', 'complete' => '0');

	$pending = api_campaign_recipient_present($row);

	pg_assert_same('pending', $pending['status'], 'not yet sent');
	pg_assert_same(null, $pending['contact_id'], 'no contact');
	pg_assert_same(0, $pending['attempts'], 'no retry columns, no attempts');
	pg_assert_same(null, $pending['last_error'], 'no retry columns, no error');

	$sent = api_campaign_recipient_present(array_merge($row, array('complete' => '1', 'failed' => '0', 'attempts' => '1', 'last_error' => '')));

	pg_assert_same('sent', $sent['status'], 'sent');
	pg_assert_same(null, $sent['last_error'], 'an empty error is no error');

	$failed = api_campaign_recipient_present(array_merge($row, array('complete' => '1', 'failed' => '1', 'attempts' => '5', 'last_error' => 'Mailbox full')));

	pg_assert_same('failed', $failed['status'], 'complete and failed is failed');
	pg_assert_same(5, $failed['attempts']);
	pg_assert_same('Mailbox full', $failed['last_error']);
}

function test_api_campaigns_recipient_status_filter()
{
	pg_assert_same('complete = 1 AND failed = 0', api_campaign_recipient_status_where('sent', true), 'sent leaves the failed out');
	pg_assert_same('complete = 1', api_campaign_recipient_status_where('sent', false), 'before the retry columns');
	pg_assert_same('failed = 1', api_campaign_recipient_status_where('failed', true));
	pg_assert_same('0 = 1', api_campaign_recipient_status_where('failed', false), 'nothing failed before the retry columns');
	pg_assert_same('complete = 0', api_campaign_recipient_status_where('pending', true));
	pg_assert_same('', api_campaign_recipient_status_where('other', true), 'unknown status adds nothing');
}

function test_api_campaigns_user_role_reaches_only_its_own()
{
	pg_assert_same('', api_campaign_owner_filter(api_campaigns_test_owner(0)), 'administrator');
	pg_assert_same('', api_campaign_owner_filter(api_campaigns_test_owner(2)), 'manager');
	pg_assert_same("email_campaigns.created_user_id = '7'", api_campaign_owner_filter(api_campaigns_test_owner(3, 'yes')), 'user with the e-mail right');
}

function test_api_campaigns_routes_reach_a_handler_under_the_campaigns_scope()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'campaigns.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		$write = ($route['method'] !== 'GET');

		pg_assert_same($write ? 'campaigns:write' : 'campaigns:read', $route['scope'], $route['id'] . ' scope');

		if ($write) {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}
	}

	pg_assert_same(6, $found, 'list, get, recipients, update, delete and its POST alias');
}

// The router takes the first route that matches.
function test_api_campaigns_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (api_schema() as $route) {
		$methods = array_merge(array($route['method']), isset($route['also_accepts']) ? (array) $route['also_accepts'] : array());

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			if (strpos($route['id'], 'campaigns.') === 0) {
				pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			}

			$seen[$key] = $route['id'];
		}
	}
}

function test_api_campaigns_scope_follows_the_campaign_screens()
{
	pg_assert_true(in_array('campaigns:read', api_scopes_all(), true), 'campaigns:read is a scope');
	pg_assert_true(in_array('campaigns:write', api_scopes_all(), true), 'campaigns:write is a scope');

	$manager = api_owner_scopes(api_campaigns_test_owner(2));

	pg_assert_true(in_array('campaigns:write', $manager, true), 'manager');

	$with_right = api_owner_scopes(api_campaigns_test_owner(3, 'yes'));

	pg_assert_true(in_array('campaigns:read', $with_right, true), 'user with the e-mail right reads');
	pg_assert_true(in_array('campaigns:write', $with_right, true), 'user with the e-mail right writes');

	$without_right = api_owner_scopes(api_campaigns_test_owner(3, 'no'));

	pg_assert_false(in_array('campaigns:read', $without_right, true), 'user without the e-mail right');
}

// A key made before this release holds what its operator ticked then; the new
// scope is not added to it by the upgrade.
function test_api_campaigns_scope_is_not_given_to_existing_keys()
{
	$granted = array('customers:write', 'orders:read', 'pages:write', 'meta:read');

	$effective = api_effective_scopes($granted, api_campaigns_test_owner(0));

	pg_assert_false(in_array('campaigns:read', $effective, true), 'not read');
	pg_assert_false(in_array('campaigns:write', $effective, true), 'not written');

	$effective = api_effective_scopes(array('campaigns:write'), api_campaigns_test_owner(0));

	pg_assert_true(in_array('campaigns:read', $effective, true), 'write implies read once ticked');
}
