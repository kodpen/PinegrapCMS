<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's customers in includes/api/resources/customers.php:
 * which contacts an owner reaches - every one from the manager role up, below
 * that the ones filed in the contact groups given to it, as on the contact
 * screens - the group a new customer is filed in, and that every customers
 * route and the contact group member write go through the rule.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/contact_groups.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// An owner as api_load_owner() builds it.
function api_customers_test_owner($role, $id = 9)
{
	return array(
		'id' => $id, 'username' => 'editor', 'role' => $role,
		'manage_ecommerce' => false, 'manage_contacts' => true, 'manage_forms' => false,
	);
}

// The lines of a function, to read which rule it applies.
function api_customers_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The contact screens open every contact from the manager role up, so those
// queries carry no condition.
function test_api_customers_manager_role_up_reaches_every_contact()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_same('', api_customer_owner_filter(api_customers_test_owner($role)), 'role ' . $role);
	}
}

// Below that a contact has to be filed in a group given to the account.
function test_api_customers_user_role_reaches_the_contacts_of_its_groups()
{
	pg_assert_same(
		"contacts.id IN (SELECT contacts_contact_groups_xref.contact_id FROM contacts_contact_groups_xref"
		. " INNER JOIN users_contact_groups_xref ON users_contact_groups_xref.contact_group_id = contacts_contact_groups_xref.contact_group_id"
		. " WHERE users_contact_groups_xref.user_id = '119')",
		api_customer_owner_filter(api_customers_test_owner(3, '119')),
		'the account\'s own groups'
	);

	pg_assert_contains("user_id = '0'", api_customer_owner_filter(api_customers_test_owner(3, 'x')), 'an id that is not a number reaches nobody\'s groups');
}

// From the manager role up the group sent is taken, and none is none.
function test_api_customers_create_group_from_the_manager_role_up()
{
	pg_assert_same(0, api_customer_create_group(2, array(), 0), 'none sent');
	pg_assert_same(14, api_customer_create_group(1, array(), '14'), 'the one sent');
	pg_assert_same(0, api_customer_create_group(0, array(), -5), 'nothing that is a group id');
}

// Below that the customer is always filed in one of the account's groups.
function test_api_customers_create_group_for_the_user_role()
{
	pg_assert_same(8, api_customer_create_group(3, array('8', '11'), 8), 'one of its groups');
	pg_assert_same('not_found', api_customer_create_group(3, array('8', '11'), 1), 'a group given to others');
	pg_assert_same(8, api_customer_create_group(3, array('8'), 0), 'none sent, its only group');
	pg_assert_same(8, api_customer_create_group(3, array('8', '8', 0), null), 'a repeated id is one group');
	pg_assert_same('choose', api_customer_create_group(3, array('8', '11'), 0), 'none sent, several groups');
	pg_assert_same('no_group', api_customer_create_group(3, array(), 0), 'no group at all');
	pg_assert_same('not_found', api_customer_create_group(3, array(), 4), 'a group sent with no group of its own');
}

// Every customers route answers only for the contacts its owner may open, and
// the create files what it makes where the owner reaches it.
function test_api_customers_every_route_applies_the_group_rule()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'customers.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		if (!function_exists($route['handler'])) {
			continue;
		}

		$source = api_customers_test_function_source($route['handler']);

		pg_assert_true(
			(strpos($source, 'api_customer_owner_filter(') !== false)
				|| (strpos($source, 'api_customer_reachable_or_fail(') !== false)
				|| (strpos($source, 'api_customer_create_group_or_fail(') !== false),
			$route['id'] . ': ' . $route['handler'] . '() applies the group rule'
		);
	}

	pg_assert_same(6, $found, 'list, get, create, update and both deletes');

	pg_assert_contains('api_customer_owner_filter(', api_customers_test_function_source('api_customer_reachable_or_fail'), 'the record loader');
	pg_assert_contains('api_customer_create_group(', api_customers_test_function_source('api_customer_create_group_or_fail'), 'the create\'s group');
}

// Putting a contact in a group reaches only contacts the owner already reaches,
// or a member write would bring any contact into its groups and so into reach.
function test_api_customers_member_write_cannot_pull_a_contact_into_reach()
{
	pg_assert_contains('api_customer_owner_filter(', api_customers_test_function_source('api_contact_group_contacts_or_fail'), 'the member write');
}
