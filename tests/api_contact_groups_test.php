<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's contact groups in
 * includes/api/resources/contact_groups.php: who reaches which groups, a
 * member's choice for the group's e-mail, the ids a members change takes and
 * what it actually changes, the route table and the scope that goes with it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/contact_groups.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// An owner as api_load_owner() builds it.
function api_contact_groups_test_owner($role, $manage_contacts = false)
{
	return array(
		'id' => 9, 'username' => 'editor', 'role' => $role,
		'manage_ecommerce' => false, 'manage_contacts' => $manage_contacts, 'manage_forms' => false,
	);
}

function test_api_contact_groups_user_role_reaches_only_the_groups_given_to_it()
{
	pg_assert_same('', api_contact_group_owner_filter(api_contact_groups_test_owner(0)), 'administrator');
	pg_assert_same('', api_contact_group_owner_filter(api_contact_groups_test_owner(2)), 'manager');
	pg_assert_same(
		"contact_groups.id IN (SELECT contact_group_id FROM users_contact_groups_xref WHERE user_id = '9')",
		api_contact_group_owner_filter(api_contact_groups_test_owner(3, true)),
		'user with the contacts right'
	);
}

function test_api_contact_groups_a_member_who_never_chose_is_opted_in()
{
	pg_assert_same(true, api_contact_group_member_opt_in(null, true), 'no opt_in row');
	pg_assert_same(true, api_contact_group_member_opt_in('1', true), 'opted in');
	pg_assert_same(false, api_contact_group_member_opt_in('0', true), 'opted out');
	pg_assert_same(null, api_contact_group_member_opt_in('0', false), 'a group without subscription has no choice');
	pg_assert_same(null, api_contact_group_member_opt_in(null, false), 'nor without a row');
}

function test_api_contact_groups_member_from_its_columns()
{
	$row = array(
		'id' => '41', 'first_name' => 'Ayşe', 'last_name' => 'Kaya', 'email_address' => 'ayse@example.com',
		'company' => '', 'contact_opt_in' => '1', 'group_opt_in' => '0',
	);

	$member = api_contact_group_member_present($row, true);

	pg_assert_same(41, $member['contact_id']);
	pg_assert_same('ayse@example.com', $member['email']);
	pg_assert_same(true, $member['email_opt_in'], 'all campaigns on');
	pg_assert_same(false, $member['opt_in'], 'this group\'s e-mail off');

	$member = api_contact_group_member_present(array_merge($row, array('contact_opt_in' => '0', 'group_opt_in' => null)), true);

	pg_assert_same(false, $member['email_opt_in'], 'all campaigns off');
	pg_assert_same(true, $member['opt_in'], 'never chose');

	$member = api_contact_group_member_present($row, false);

	pg_assert_same(null, $member['opt_in'], 'group takes no subscriptions');

	pg_assert_same(array_keys(api_contact_group_member_schema()), array_keys($member), 'every field declared, in order');
}

function test_api_contact_groups_ids_are_positive_and_distinct()
{
	pg_assert_same(array(3, 5, 8), api_contact_group_ids_from(array('3', 5, 3, 0, -2, 'x', 8)));
	pg_assert_same(array(), api_contact_group_ids_from(null), 'nothing sent');
	pg_assert_same(array(), api_contact_group_ids_from(array()), 'empty list');
}

function test_api_contact_groups_members_change_counts_only_what_changes()
{
	$changes = api_contact_group_member_changes(array(1, 2, 3), array(7, 8), array(2, 7));

	pg_assert_same(array(1, 3), $changes['add'], 'a member already there is not added again');
	pg_assert_same(array(7), $changes['remove'], 'a contact not in the group is not removed');

	$changes = api_contact_group_member_changes(array(), array(4), array());

	pg_assert_same(array(), $changes['add']);
	pg_assert_same(array(), $changes['remove'], 'removing a non-member is not an error and changes nothing');
}

function test_api_contact_groups_routes_reach_a_handler_under_the_contact_groups_scope()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'contact_groups.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		$write = ($route['method'] !== 'GET');

		pg_assert_same($write ? 'contact_groups:write' : 'contact_groups:read', $route['scope'], $route['id'] . ' scope');

		if ($write) {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}
	}

	pg_assert_same(4, $found, 'list, get, members and the members change');
}

// The router takes the first route that matches.
function test_api_contact_groups_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (api_schema() as $route) {
		$methods = array_merge(array($route['method']), isset($route['also_accepts']) ? (array) $route['also_accepts'] : array());

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			if (strpos($route['id'], 'contact_groups.') === 0) {
				pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			}

			$seen[$key] = $route['id'];
		}
	}
}

function test_api_contact_groups_scope_follows_the_contact_screens()
{
	pg_assert_true(in_array('contact_groups:read', api_scopes_all(), true), 'contact_groups:read is a scope');
	pg_assert_true(in_array('contact_groups:write', api_scopes_all(), true), 'contact_groups:write is a scope');

	$manager = api_owner_scopes(api_contact_groups_test_owner(2));

	pg_assert_true(in_array('contact_groups:write', $manager, true), 'manager');

	$with_right = api_owner_scopes(api_contact_groups_test_owner(3, true));

	pg_assert_true(in_array('contact_groups:read', $with_right, true), 'user with the contacts right reads');
	pg_assert_true(in_array('contact_groups:write', $with_right, true), 'user with the contacts right writes');

	$without_right = api_owner_scopes(api_contact_groups_test_owner(3, false));

	pg_assert_false(in_array('contact_groups:read', $without_right, true), 'user without the contacts right');
}

// A key made before this release holds what its operator ticked then; the new
// scope is not added to it by the upgrade, customers:write included.
function test_api_contact_groups_scope_is_not_given_to_existing_keys()
{
	$granted = array('customers:write', 'campaigns:write', 'meta:read');

	$effective = api_effective_scopes($granted, api_contact_groups_test_owner(0));

	pg_assert_false(in_array('contact_groups:read', $effective, true), 'not read');
	pg_assert_false(in_array('contact_groups:write', $effective, true), 'not written');

	$effective = api_effective_scopes(array('contact_groups:write'), api_contact_groups_test_owner(0));

	pg_assert_true(in_array('contact_groups:read', $effective, true), 'write implies read once ticked');
}
