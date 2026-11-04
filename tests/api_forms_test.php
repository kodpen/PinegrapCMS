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

	pg_assert_same(9, $found, 'list, get, fields, submissions, one submission, its create, update and both deletes');

	pg_assert_contains('api_form_owner_filter(', api_forms_test_function_source('api_form_submission_owned'), 'the submission loader');
}

// A record with a fee says whether it is paid and the order's number; one
// without a fee answers null for both, and a pre-upgrade row has neither key.
function test_api_forms_submission_payment_fields()
{
	$row = array(
		'id' => 5, 'page_id' => 3, 'reference_code' => 'R', 'complete' => 1, 'contact_id' => 0,
		'quiz_score' => 0, 'submitted_timestamp' => 0, 'last_modified_timestamp' => 0,
	);

	$none = api_form_submission_present($row, array());
	pg_assert_same(null, $none['order_number'], 'no fee: no order number');
	pg_assert_same(null, $none['paid'], 'no fee: paid is null');

	$unpaid = api_form_submission_present($row + array('order_id' => 9, 'paid_at' => 0, 'order_number' => '0'), array());
	pg_assert_same(false, $unpaid['paid'], 'a fee order not yet paid');
	pg_assert_same(null, $unpaid['order_number'], 'an incomplete order has no number');

	$paid = api_form_submission_present($row + array('order_id' => 9, 'paid_at' => 1700000000, 'order_number' => '1009'), array());
	pg_assert_same(true, $paid['paid'], 'paid');
	pg_assert_same('1009', $paid['order_number'], 'the order number');

	$schema = api_form_submission_schema();
	pg_assert_same('string?', $schema['order_number'], 'declared');
	pg_assert_same('boolean?', $schema['paid'], 'declared');
}

// The fields of a choice form, as pg_submitted_form_fields() reads them.
function api_forms_test_fields()
{
	return array(
		array('id' => 1, 'name' => 'email', 'type' => 'email address', 'multiple' => 0),
		array('id' => 2, 'name' => 'topic', 'type' => 'pick list', 'multiple' => 0),
		array('id' => 3, 'name' => 'tags', 'type' => 'check box', 'multiple' => 0),
		array('id' => 4, 'name' => 'cv', 'type' => 'file upload', 'multiple' => 0),
		array('id' => 5, 'name' => 'sizes', 'type' => 'pick list', 'multiple' => 1),
		array('id' => 6, 'name' => 'sign', 'type' => 'signature', 'multiple' => 0),
	);
}

// Answers are keyed by field id: one string a field, a list for a field that
// takes several, and a choice must be one the field offers.
function test_api_forms_submission_answers_by_field()
{
	$choices = array(2 => array('sales', 'support'), 3 => array('a', 'b'), 5 => array('s', 'm'));

	$answers = api_form_submission_answers(array(
		array('name' => 'email', 'value' => " ada@example.com \r\n"),
		array('name' => 'topic', 'value' => 'support'),
		array('name' => 'tags', 'value' => 'a'),
		array('name' => 'tags', 'value' => 'b'),
		array('name' => 'sizes', 'value' => 'm'),
	), api_forms_test_fields(), $choices);

	pg_assert_same(array(1 => 'ada@example.com', 2 => 'support', 3 => array('a', 'b'), 5 => array('m')), $answers, 'answers');
	pg_assert_same(array(), api_form_submission_answers(array(), api_forms_test_fields(), $choices), 'nothing sent');
	pg_assert_same(array(2 => ''), api_form_submission_answers(array(array('name' => 'topic', 'value' => null)), api_forms_test_fields(), $choices), 'an empty choice is no answer');
}

// What a new submission cannot carry is refused with a sentence, not stored.
function test_api_forms_submission_answers_refusals()
{
	$choices = array(2 => array('sales', 'support'), 3 => array('a', 'b'));
	$fields = api_forms_test_fields();

	$refused = array(
		'unknown field' => array(array('name' => 'phone', 'value' => '1')),
		'file upload'   => array(array('name' => 'cv', 'value' => 'x.pdf')),
		'signature'     => array(array('name' => 'sign', 'value' => 'data:image/png;base64,AA')),
		'not a choice'  => array(array('name' => 'topic', 'value' => 'billing')),
		'twice'         => array(array('name' => 'email', 'value' => 'a@b.co'), array('name' => 'email', 'value' => 'c@d.co')),
		'not an object' => array('email'),
		'array value'   => array(array('name' => 'email', 'value' => array('a'))),
		'too long'      => array(array('name' => 'email', 'value' => str_repeat('a', 10001))),
	);

	foreach ($refused as $label => $items) {
		pg_assert_true(is_string(api_form_submission_answers($items, $fields, $choices)), $label);
	}
}

// The create and delete handlers go through the write path the screens use,
// stop a rehearsal above it, and announce through pg_announce().
function test_api_forms_submission_writes_share_the_screen_path()
{
	$create = api_forms_test_function_source('api_form_submission_create');

	pg_assert_contains('pg_submitted_form_create_check(', $create, 'the screen\'s checks');
	pg_assert_contains('pg_submitted_form_create(', $create, 'the screen\'s write');
	pg_assert_contains("pg_announce('form.submitted'", $create, 'announced');
	pg_assert_true(strpos($create, 'api_dry_run_stop(') < strpos($create, 'pg_submitted_form_create($liveform'), 'rehearsal stops above the write');
	pg_assert_true(strpos($create, 'pg_submitted_form_create_check(') < strpos($create, 'api_dry_run_stop('), 'checks run in a rehearsal');

	$delete = api_forms_test_function_source('api_form_submission_delete');

	pg_assert_contains('pg_submitted_form_delete(', $delete, 'the screen\'s delete');
	pg_assert_true(strpos($delete, 'api_dry_run_stop(') < strpos($delete, 'pg_submitted_form_delete('), 'rehearsal stops above the delete');

	$writes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');
	pg_assert_contains("pg_announce('form.submission.deleted'", $writes, 'the delete announces');

	require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
	$events = api_webhook_events();
	pg_assert_true(isset($events['form.submission.deleted']), 'the event is in the catalogue');
	pg_assert_true(isset($events['form.submitted']), 'form.submitted stays');
}

// A check box group and a multiple pick list take several answers.
function test_api_forms_field_takes_many()
{
	pg_assert_true(api_form_field_takes_many(array('type' => 'check box', 'multiple' => 0)), 'check box');
	pg_assert_true(api_form_field_takes_many(array('type' => 'pick list', 'multiple' => 1)), 'multiple pick list');
	pg_assert_false(api_form_field_takes_many(array('type' => 'pick list', 'multiple' => 0)), 'pick list');
	pg_assert_false(api_form_field_takes_many(array('type' => 'radio button', 'multiple' => 1)), 'radio button');
}
