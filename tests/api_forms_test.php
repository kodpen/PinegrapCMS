<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's forms in includes/api/resources/forms.php:
 * which forms an owner reaches - every one from the manager role up, below
 * that the ones on pages in folders it may edit, as on the submitted-form
 * screens - the condition that keeps a query to them, and that every forms
 * route goes through it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/forms.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// An owner as api_load_owner() builds it.
function api_forms_test_owner($role, $manage_forms = false)
{
	return array(
		'id' => 9, 'username' => 'editor', 'role' => $role,
		'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => $manage_forms,
	);
}

// The lines of a function, to read which rule it applies.
function api_forms_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The submitted-form screens open every form from the manager role up, so
// those queries carry no condition and stay as they were.
function test_api_forms_manager_role_up_reaches_every_form()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_same('', api_form_owner_filter(api_forms_test_owner($role, false), 'forms.page_id'), 'role ' . $role);
	}
}

// Below that the form's page has to be filed in one of the folders the
// account may edit.
function test_api_forms_folder_filter_keeps_the_forms_in_the_folders_given()
{
	pg_assert_same(
		"forms.page_id IN (SELECT page_id FROM page WHERE page_folder IN (4,12))",
		api_form_folder_filter(array('12', '4'), 'forms.page_id'),
		'ids as the folder query returns them, in order'
	);

	pg_assert_same(
		"form_fields.page_id IN (SELECT page_id FROM page WHERE page_folder IN (7))",
		api_form_folder_filter(array(7, '7', 0, -3, 'x'), 'form_fields.page_id'),
		'a repeated id once, nothing that is not a folder id'
	);
}

// An account with the forms right but edit rights on no folder opens no form
// on the screens, so no row passes.
function test_api_forms_folder_filter_without_a_folder_lets_no_row_through()
{
	pg_assert_same('0', api_form_folder_filter(array(), 'forms.page_id'), 'no folder');
	pg_assert_same('0', api_form_folder_filter(null, 'forms.page_id'), 'nothing read');
	pg_assert_same('0', api_form_folder_filter(array(0, '0'), 'forms.page_id'), 'only the empty id');
}

// The user role is offered the forms scopes only with the forms right; the
// folder rule is what narrows them after that.
function test_api_forms_scope_follows_the_forms_right()
{
	pg_assert_false(in_array('forms:read', api_owner_scopes(api_forms_test_owner(3, false)), true), 'user role without the forms right');
	pg_assert_true(in_array('forms:read', api_owner_scopes(api_forms_test_owner(3, true)), true), 'user role with the forms right reads');
	pg_assert_true(in_array('forms:write', api_owner_scopes(api_forms_test_owner(3, true)), true), 'user role with the forms right writes');
	pg_assert_true(in_array('forms:write', api_owner_scopes(api_forms_test_owner(2, false)), true), 'manager');
}

// Every forms route answers only for the forms its owner may open: the
// handler builds the condition itself, or loads its submission through
// api_form_submission_owned(), which does.
function test_api_forms_every_route_applies_the_folder_rule()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'forms.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		if (!function_exists($route['handler'])) {
			continue;
		}

		$source = api_forms_test_function_source($route['handler']);

		pg_assert_true(
			(strpos($source, 'api_form_owner_filter(') !== false) || (strpos($source, 'api_form_submission_owned(') !== false),
			$route['id'] . ': ' . $route['handler'] . '() applies the folder rule'
		);

		pg_assert_same(($route['method'] === 'GET') ? 'forms:read' : 'forms:write', $route['scope'], $route['id'] . ' scope');
	}

	pg_assert_same(5, $found, 'list, get, submissions, one submission and its update');

	pg_assert_contains('api_form_owner_filter(', api_forms_test_function_source('api_form_submission_owned'), 'the submission loader');
}
