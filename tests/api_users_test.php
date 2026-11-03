<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's users reads in includes/api/resources/users.php:
 * the row rule (an administrator reaches every account, a designer only the
 * accounts below it, as view_users.php), the filters, the presenter that
 * never carries a secret, the two-step state, the route table and the
 * users:read scope at the designer ceiling.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/users.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// escape() falls back to addslashes() without a connection.
if (!class_exists('db')) {
	class db
	{
		public static $con;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// A row as api_users_select_sql() reads it, with every right column there.
function api_users_test_row(array $over = array())
{
	return array_merge(array(
		'user_id' => '17', 'user_username' => 'ayse', 'user_email' => 'ayse@example.com', 'user_role' => '3',
		'user_home' => '0', 'user_contact' => '12', 'timezone' => '', 'user_timestamp' => '1801213200',
		'user_online_timestamp' => '1801299600', 'user_created' => '1790000000', 'google_linked' => '0',
		'user_manage_contacts' => 'yes', 'user_manage_emails' => 'no', 'user_manage_ecommerce' => 'yes',
		'manage_ecommerce_reports' => '1', 'user_view_card_data' => '0', 'user_set_offline_payment' => '0',
		'user_manage_forms' => 'no', 'user_manage_calendars' => 'yes', 'user_publish_calendar_events' => 'no',
		'user_manage_visitors' => 'no', 'user_create_pages' => '1', 'user_delete_pages' => '0',
		'user_set_page_type_catalog' => '1', 'user_set_page_type_photo_gallery' => '0',
		'user_set_page_type_custom_form' => '1',
		'manage_erp' => '1', 'manage_erp_cash' => '0', 'manage_erp_settings' => '0', 'manage_erp_readonly' => '0',
		'manage_workspace' => '1', 'manage_workspace_assign' => '0', 'manage_workspace_board' => '1', 'manage_workspace_settings' => '0',
	), $over);
}

// view_users.php: an administrator sees every account, anyone else only the
// accounts with less power; the API answers 404 for the rest.
function test_api_users_owner_filter_follows_the_users_screen()
{
	pg_assert_same('', api_user_owner_filter(array('id' => 1, 'role' => 0)), 'an administrator reaches every account');
	pg_assert_same('user.user_role > 1', api_user_owner_filter(array('id' => 4, 'role' => 1)), 'a designer only the managers and users');
	pg_assert_same('user.user_role > 2', api_user_owner_filter(array('id' => 5, 'role' => 2)));
}

function test_api_users_list_filters()
{
	$admin = array('id' => 1, 'role' => 0);

	pg_assert_same(array(), api_users_where(array('limit' => 50), $admin), 'no filter');

	pg_assert_same(array(
		"user.user_role = '2'",
		"user.user_email = 'ayse@example.com'",
		"user.user_contact = '12'",
	), api_users_where(array('role' => 'manager', 'email' => 'ayse@example.com', 'customer_id' => 12), $admin));

	$where = api_users_where(array('role' => 'user'), array('id' => 4, 'role' => 1));

	pg_assert_same(array("user.user_role = '3'", 'user.user_role > 1'), $where, 'the row rule is one more condition');
}

function test_api_users_role_codes()
{
	pg_assert_same(array('administrator', 'designer', 'manager', 'user'), array_values(api_user_roles()));
	pg_assert_same('designer', api_user_role_code(1));
	pg_assert_same('user', api_user_role_code(3));
	pg_assert_same('user', api_user_role_code(9), 'an unknown role reads as the least power');
}

// No password, hint, token, PIN, key or MFA secret is read, so none can be
// presented.
function test_api_users_select_reads_no_secret()
{
	$sql = api_users_select_sql(api_user_optional_columns(array()));

	foreach (array('user_password', 'user_password_hint', 'token', 'user_devpasspin', 'secret_key', 'secret_key_iv', 'secret_key_hash', 'totp_secret', 'pending_secret') as $secret) {
		pg_assert_true(preg_match('/\b' . $secret . '\b/', $sql) === 0, $secret . ' is never read');
	}

	pg_assert_true(strpos($sql, 'user_google_id') === false, 'the Google id is only asked whether it is set');
	pg_assert_true(strpos($sql, 'manage_erp') === false, 'a column the upgrade has not added is not named');

	$sql = api_users_select_sql(api_user_optional_columns(array('created' => true, 'google' => true, 'erp' => true, 'erp_readonly' => true, 'workspace' => true)));

	pg_assert_contains('user.user_created', $sql);
	pg_assert_contains('(user.user_google_id IS NOT NULL AND user.user_google_id <> \'\') AS google_linked', $sql);
	pg_assert_contains('user.manage_erp_readonly', $sql);
	pg_assert_contains('user.manage_workspace_settings', $sql);
}

function test_api_users_present()
{
	$out = api_user_present(api_users_test_row(), array('enabled' => true, 'authenticator' => true, 'passkeys' => 2, 'required' => false));

	pg_assert_same(array_keys(api_user_schema()), array_keys($out), 'every field declared, in order');
	pg_assert_same(17, $out['id']);
	pg_assert_same('ayse', $out['username']);
	pg_assert_same('ayse@example.com', $out['email']);
	pg_assert_same('user', $out['role']);
	pg_assert_same(12, $out['customer_id']);
	pg_assert_same(null, $out['start_page_id'], 'no start page');
	pg_assert_same(null, $out['timezone'], 'the site\'s zone');
	pg_assert_same(api_time(1801299600), $out['last_online_at']);
	pg_assert_same(api_time(1790000000), $out['created_at']);
	pg_assert_same(api_time(1801213200), $out['updated_at']);
	pg_assert_same(false, $out['google_linked']);
	pg_assert_same(array('enabled' => true, 'authenticator' => true, 'passkeys' => 2, 'required' => false), $out['two_step']);

	$rights = $out['permissions'];

	pg_assert_same(array_keys(api_user_permissions_schema()), array_keys($rights), 'every right declared, in order');
	pg_assert_same(true, $rights['manage_contacts'], "an ENUM right reads 'yes'");
	pg_assert_same(false, $rights['manage_emails']);
	pg_assert_same(true, $rights['manage_ecommerce_reports'], 'a TINYINT right reads 1');
	pg_assert_same(false, $rights['publish_calendar_events']);
	pg_assert_same(true, $rights['manage_erp']);
	pg_assert_same(true, $rights['manage_workspace_board']);
	pg_assert_same(array('custom_form', 'catalog'), $rights['page_types'], 'the page types the account may set');

	foreach (array_keys($out) as $field) {
		pg_assert_true(preg_match('/password|secret|token|pin|hint/', $field) === 0, $field . ' is no secret');
	}
}

// Before the upgrade adds a right's column, or for an account that never
// had a factor, the answer still has every field.
function test_api_users_present_without_the_optional_columns()
{
	$row = api_users_test_row(array('user_contact' => null, 'user_home' => '31', 'timezone' => 'Europe/Istanbul', 'user_online_timestamp' => '0'));

	foreach (array('user_created', 'google_linked', 'manage_erp', 'manage_erp_cash', 'manage_erp_settings', 'manage_erp_readonly', 'manage_workspace', 'manage_workspace_assign', 'manage_workspace_board', 'manage_workspace_settings') as $column) {
		unset($row[$column]);
	}

	$out = api_user_present($row, null);

	pg_assert_same(null, $out['customer_id']);
	pg_assert_same(31, $out['start_page_id']);
	pg_assert_same('Europe/Istanbul', $out['timezone']);
	pg_assert_same(null, $out['last_online_at'], 'never online');
	pg_assert_same(null, $out['created_at'], 'made before the stamp existed');
	pg_assert_same(false, $out['google_linked']);
	pg_assert_same(false, $out['permissions']['manage_erp']);
	pg_assert_same(false, $out['permissions']['manage_workspace']);
	pg_assert_same(array('enabled' => false, 'authenticator' => false, 'passkeys' => 0, 'required' => false), $out['two_step']);
}

// The second factor of each account, for a page of rows at once.
function test_api_users_two_step_of_a_page()
{
	$state = api_user_two_step_state(array(17, 18, 19), array(17 => true), array(18 => 1, 19 => 0), array(17 => 3, 18 => 2, 19 => 0), 1);

	pg_assert_same(array('enabled' => true, 'authenticator' => true, 'passkeys' => 0, 'required' => false), $state[17], 'a designer above the required role is not obliged');
	pg_assert_same(array('enabled' => true, 'authenticator' => false, 'passkeys' => 1, 'required' => false), $state[18]);
	pg_assert_same(array('enabled' => false, 'authenticator' => false, 'passkeys' => 0, 'required' => true), $state[19], 'an administrator must have one');

	$state = api_user_two_step_state(array(19), array(), array(), array(19 => 0), 99);

	pg_assert_same(false, $state[19]['required'], 'no role is required to');
}

function test_api_users_scope_sits_at_the_designer_ceiling()
{
	$groups = api_scope_groups();

	pg_assert_same('users:read', $groups['users']['read']);
	pg_assert_same('', $groups['users']['write'], 'the writes are a later step');
	pg_assert_true(in_array('users:read', api_scopes_all(), true));

	foreach (array(0 => true, 1 => true, 2 => false, 3 => false) as $role => $holds) {
		$owner = array('id' => 9, 'role' => $role, 'manage_ecommerce' => true, 'manage_contacts' => true, 'manage_forms' => true);

		pg_assert_same($holds, in_array('users:read', api_owner_scopes($owner), true), 'role ' . $role);
	}
}

function test_api_users_routes_reach_a_handler_under_the_users_scope()
{
	$found = array();

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'users.') !== 0) {
			continue;
		}

		$found[] = $route['id'];

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_same('GET', $route['method'], $route['id'] . ' only reads');
		pg_assert_same('users:read', $route['scope'], $route['id'] . ' scope');

		if ($route['id'] === 'users.list') {
			pg_assert_same(array('list' => 'User'), $route['returns']);

			$names = array();

			foreach ($route['params'] as $param) {
				$names[] = $param['name'];

				if ($param['name'] === 'role') {
					pg_assert_same(array_values(api_user_roles()), $param['values']);
				}
			}

			pg_assert_same(array('role', 'email', 'customer_id', 'limit', 'cursor'), $names);
		}
	}

	pg_assert_same(array('users.list', 'users.get'), $found);
}
