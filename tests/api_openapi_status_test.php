<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the OpenAPI document says a call answers when it works: a
 * create answers 201 and the document says 201, one that may hand back what
 * was already there says 201 and 200, a rehearsal of either says 200 with
 * the dry-run answer, and everything else keeps its 200 (or 204). Also the
 * people a workspace record names that may be nobody, declared so a
 * generated client takes null there.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The core table and both modules' rows, keyed by route id. The module rows
// are read directly, so the test does not depend on a module being switched
// on here.
function api_openapi_status_test_routes()
{
	$out = array();

	foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes()) as $route) {
		$out[$route['id']] = $route;
	}

	return $out;
}

function api_openapi_status_test_success($responses)
{
	// PHP turns the numeric keys back into integers.
	return array_values(array_intersect(array_map('strval', array_keys($responses)), array('200', '201', '204')));
}

function test_api_openapi_status_create_answers_201()
{
	$responses = api_openapi_responses(array('method' => 'POST', 'returns' => 'Product', 'status' => 201));

	pg_assert_same(array('201'), api_openapi_status_test_success($responses));
	pg_assert_same('#/components/schemas/Product', $responses['201']['content']['application/json']['schema']['$ref']);
}

function test_api_openapi_status_rehearsal_of_a_create_answers_200()
{
	$responses = api_openapi_responses(array('method' => 'POST', 'returns' => 'Product', 'status' => 201, 'dry_run' => true));

	pg_assert_same(array('200', '201'), api_openapi_status_test_success($responses));
	pg_assert_same('#/components/schemas/DryRun', $responses['200']['content']['application/json']['schema']['$ref']);
	pg_assert_same('#/components/schemas/Product', $responses['201']['content']['application/json']['schema']['$ref']);
}

function test_api_openapi_status_create_or_existing_answers_both()
{
	$responses = api_openapi_responses(array('method' => 'POST', 'returns' => 'WorkspaceThread', 'status' => array(201, 200), 'dry_run' => true));

	pg_assert_same(array('200', '201'), api_openapi_status_test_success($responses));
	pg_assert_same('#/components/schemas/WorkspaceThread', $responses['200']['content']['application/json']['schema']['$ref']);
	pg_assert_same('#/components/schemas/WorkspaceThread', $responses['201']['content']['application/json']['schema']['$ref']);
	pg_assert_contains('already there', $responses['200']['description']);
	pg_assert_contains('rehearsal', $responses['200']['description']);
}

function test_api_openapi_status_others_keep_200_and_204()
{
	$update = api_openapi_responses(array('method' => 'POST', 'returns' => 'Product', 'dry_run' => true));

	pg_assert_same(array('200'), api_openapi_status_test_success($update));
	pg_assert_same('#/components/schemas/Product', $update['200']['content']['application/json']['schema']['$ref']);

	$delete = api_openapi_responses(array('method' => 'DELETE', 'status' => 204, 'dry_run' => true));

	pg_assert_same(array('200', '204'), api_openapi_status_test_success($delete));
	pg_assert_false(isset($delete['204']['content']), 'a 204 has no body');
	pg_assert_same('#/components/schemas/DryRun', $delete['200']['content']['application/json']['schema']['$ref']);

	pg_assert_same(array(200), api_openapi_success_codes(array('method' => 'GET')));
	pg_assert_same(array(201, 200), api_openapi_success_codes(array('status' => array(201, 200))));
}

// The routes whose handlers answer 201 declare it; tools/check_api_schema.php
// holds every handler to its route, these are a sample from each table.
function test_api_openapi_status_creates_declare_201()
{
	$routes = api_openapi_status_test_routes();

	foreach (array('products.create', 'customers.create', 'webhooks.create', 'auth.login',
		'erp.invoices.create', 'erp.accounts.create', 'workspace.messages.create', 'workspace.tasks.create') as $id) {
		pg_assert_same(array(201), api_openapi_success_codes($routes[$id]), $id);
	}

	foreach (array('workspace.threads.start', 'erp.waybills.invoice', 'erp.accounts.late_fee_invoice') as $id) {
		pg_assert_same(array(201, 200), api_openapi_success_codes($routes[$id]), $id);
	}

	foreach (array('products.update', 'products.get', 'erp.invoices.issue', 'workspace.messages.update') as $id) {
		pg_assert_same(array(200), api_openapi_success_codes($routes[$id]), $id);
	}
}

// A person a workspace record names is null when it names nobody (ws_api_person_ref()),
// and so is the part a record does not have.
function test_api_openapi_status_workspace_people_may_be_null()
{
	$fields = array(
		array(ws_api_decision_schema(), 'sender', 'WorkspaceMessageSender'),
		array(ws_api_decision_schema(), 'marked_by', 'WorkspacePerson'),
		array(ws_api_decision_schema(), 'claude_change', 'WorkspaceDecisionChange'),
		array(ws_api_decision_schema(), 'file', 'WorkspaceDecisionFile'),
		array(ws_api_change_schema(), 'requested_by', 'WorkspacePerson'),
		array(ws_api_change_schema(), 'decided_by', 'WorkspacePerson'),
		array(ws_api_assistant_request_schema(), 'requested_by', 'WorkspacePerson'),
		array(ws_api_note_schema(), 'owner', 'WorkspacePerson'),
		array(ws_api_note_schema(), 'updated_by', 'WorkspacePerson'),
		array(ws_api_note_schema(), 'source', 'WorkspaceNoteSource'),
		array(ws_api_note_version_schema(), 'saved_by', 'WorkspacePerson'),
	);

	$objects = ws_openapi_objects();

	foreach ($fields as $field) {

		$schema = api_openapi_type_spec($field[0][$field[1]]);

		pg_assert_true(!empty($schema['nullable']), $field[1] . ' may be null');
		pg_assert_same('#/components/schemas/' . $field[2], $schema['allOf'][0]['$ref'], $field[1]);
		pg_assert_true(isset($objects[$field[2]]) && function_exists($objects[$field[2]]), $field[2] . ' is declared');

	}

	pg_assert_same(null, ws_api_person_ref(0));
	pg_assert_same(null, ws_api_person_brief_ref(null));
	pg_assert_same(null, ws_api_note_source_present(null));
}
