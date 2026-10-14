<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the OpenAPI document says a call answers when it works: a
 * create answers 201 and the document says 201, one that may hand back what
 * was already there says 201 and 200, a rehearsal of either says 200 with
 * the dry-run answer, a rehearsal of a write that answers 200 anyway shares
 * that 200 with the record, and everything else keeps its 200 (or 204).
 * Also the fields that may be null - the people a workspace record names
 * that may be nobody, and the columns and parts a record may not have -
 * declared so a generated client takes null there.
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
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/customers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

// The core table and the modules' rows, keyed by route id. The module rows
// are read directly, so the test does not depend on a module being switched
// on here.
function api_openapi_status_test_routes()
{
	$out = array();

	foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes()) as $route) {
		$out[$route['id']] = $route;
	}

	return $out;
}

function api_openapi_status_test_success($responses)
{
	// PHP turns the numeric keys back into integers.
	return array_values(array_intersect(array_map('strval', array_keys($responses)), array('200', '201', '204')));
}

// The schema an answer's JSON content is declared with.
function api_openapi_status_test_schema($response)
{
	return $response['content']['application/json']['schema'];
}

// The two shapes a 200 shared by a record and a rehearsal is declared with,
// as $ref names: DryRun first, then the record.
function api_openapi_status_test_shapes($response)
{
	$schema = api_openapi_status_test_schema($response);

	if (!isset($schema['anyOf'])) {
		return array();
	}

	$out = array();

	foreach ($schema['anyOf'] as $member) {
		$out[] = isset($member['$ref']) ? $member['$ref'] : 'inline';
	}

	return $out;
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
	pg_assert_same(array('#/components/schemas/DryRun', '#/components/schemas/WorkspaceThread'), api_openapi_status_test_shapes($responses['200']));
	pg_assert_same('#/components/schemas/WorkspaceThread', $responses['201']['content']['application/json']['schema']['$ref']);
	pg_assert_contains('already there', $responses['200']['description']);
	pg_assert_contains('rehearsal', $responses['200']['description']);

	// Without a rehearsal the 200 is the record alone.
	$plain = api_openapi_responses(array('method' => 'POST', 'returns' => 'WorkspaceThread', 'status' => array(201, 200)));

	pg_assert_same('#/components/schemas/WorkspaceThread', $plain['200']['content']['application/json']['schema']['$ref']);
	pg_assert_false(strpos($plain['200']['description'], 'rehearsal') !== false, 'no rehearsal to mention');
}

function test_api_openapi_status_others_keep_200_and_204()
{
	$update = api_openapi_responses(array('method' => 'POST', 'returns' => 'Product'));

	pg_assert_same(array('200'), api_openapi_status_test_success($update));
	pg_assert_same('#/components/schemas/Product', $update['200']['content']['application/json']['schema']['$ref']);
	pg_assert_same('Success', $update['200']['description']);

	$read = api_openapi_responses(array('method' => 'GET', 'returns' => array('list' => 'Product')));

	pg_assert_false(isset(api_openapi_status_test_schema($read['200'])['anyOf']), 'a read has no rehearsal');

	$delete = api_openapi_responses(array('method' => 'DELETE', 'status' => 204, 'dry_run' => true));

	pg_assert_same(array('200', '204'), api_openapi_status_test_success($delete));
	pg_assert_false(isset($delete['204']['content']), 'a 204 has no body');
	pg_assert_same('#/components/schemas/DryRun', $delete['200']['content']['application/json']['schema']['$ref']);

	pg_assert_same(array(200), api_openapi_success_codes(array('method' => 'GET')));
	pg_assert_same(array(201, 200), api_openapi_success_codes(array('status' => array(201, 200))));
}

// A write that answers 200 and can be rehearsed answers its rehearsal with
// the same 200 (api_dry_run_stop()), so the 200 carries both shapes. anyOf,
// not oneOf: a record schema names no required field, so a dry-run answer
// fits it too and oneOf would refuse every rehearsal.
function test_api_openapi_status_rehearsal_of_a_200_write_shares_the_200()
{
	$update = api_openapi_responses(array('method' => 'POST', 'returns' => 'Product', 'dry_run' => true));

	pg_assert_same(array('200'), api_openapi_status_test_success($update));
	pg_assert_same(array('#/components/schemas/DryRun', '#/components/schemas/Product'), api_openapi_status_test_shapes($update['200']));
	pg_assert_false(isset(api_openapi_status_test_schema($update['200'])['oneOf']), 'anyOf, not oneOf');
	pg_assert_contains('rehearsal', $update['200']['description']);

	// An answer declared inline is carried the same way.
	$delete = api_openapi_responses(array('method' => 'POST', 'returns' => array('id' => 'integer', 'deleted' => 'boolean'), 'dry_run' => true));

	$shapes = api_openapi_status_test_schema($delete['200'])['anyOf'];

	pg_assert_same('#/components/schemas/DryRun', $shapes[0]['$ref']);
	pg_assert_same(array('id', 'deleted'), array_keys($shapes[1]['properties']));

	// A file has no record to share the 200 with.
	$file = api_openapi_responses(array('method' => 'GET', 'binary' => true));

	pg_assert_true(isset($file['200']['content']['application/octet-stream']), 'a file is its own bytes');
}

// The dry-run answer requires what api_dry_run_stop() always sends; dry_run
// is what tells it from a record, so no record may declare a field of that name.
function test_api_openapi_status_dry_run_answer_requires_its_fields()
{
	$schema = api_openapi_dry_run_schema();

	pg_assert_same(array('dry_run', 'would', 'resource'), $schema['required']);
	pg_assert_same(array('dry_run', 'would', 'resource', 'detail'), array_keys($schema['properties']));

	$objects = api_openapi_objects() + ws_openapi_objects() + erp_openapi_objects() + translate_openapi_objects();

	foreach ($objects as $name => $declare) {
		if (function_exists($declare)) {
			pg_assert_false(array_key_exists('dry_run', call_user_func($declare)), $name . ' has no dry_run field');
		}
	}
}

// Every rehearsable route that answers 200 shares it with the rehearsal, and
// every other rehearsable route answers the rehearsal with a 200 of its own.
function test_api_openapi_status_every_rehearsal_is_declared()
{
	$checked = 0;

	foreach (api_openapi_status_test_routes() as $id => $route) {

		if (empty($route['dry_run'])) {
			continue;
		}

		$checked++;

		$responses = api_openapi_responses($route);

		pg_assert_true(isset($responses['200']), $id . ' declares a 200');

		if (in_array(200, api_openapi_success_codes($route), true)) {
			pg_assert_same('#/components/schemas/DryRun', api_openapi_status_test_shapes($responses['200'])[0] ?? null, $id);
		} else {
			pg_assert_same('#/components/schemas/DryRun', api_openapi_status_test_schema($responses['200'])['$ref'] ?? null, $id);
		}
	}

	pg_assert_true($checked > 100, 'the rehearsable routes were found');
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

// Fields that are not about a person and may still be null, as the code that
// builds them answers.
function test_api_openapi_status_other_fields_may_be_null()
{
	$nullable = function ($spec, $label) {
		$schema = api_openapi_type_spec($spec);
		pg_assert_true(!empty($schema['nullable']), $label . ' may be null');
	};

	// The site's country sits in no tax zone.
	$meta = api_meta_schema();
	$nullable($meta['ecommerce']['default_tax_rate'], 'Meta.ecommerce.default_tax_rate');

	// A contact keeps NULL in every column nobody wrote, and has no phone
	// when none of the three is filled in.
	$customer = api_customer_schema();

	foreach (array('first_name', 'last_name', 'email', 'company', 'phone') as $field) {
		$nullable($customer[$field], 'Customer.' . $field);
	}

	foreach ($customer['address'] as $field => $spec) {
		$nullable($spec, 'Customer.address.' . $field);
	}

	pg_assert_same(null, api_customer_phone(array('mobile_phone' => null, 'business_phone' => '', 'home_phone' => null)));

	$present = api_customer_present(array('id' => '3', 'member_id' => null, 'first_name' => null, 'last_name' => null,
		'email_address' => 'a@example.com', 'company' => null, 'business_address_1' => null, 'business_city' => null,
		'business_state' => null, 'business_zip_code' => null, 'business_country' => null));

	pg_assert_same(null, $present['first_name']);
	pg_assert_same(null, $present['phone']);

	// Only an order is read with its lines.
	$record = ws_api_claude_record_schema();
	$lines = api_openapi_type_spec($record['lines']);

	pg_assert_true(!empty($lines['nullable']), 'WorkspaceRecord.lines may be null');
	pg_assert_same('#/components/schemas/WorkspaceRecordLine', $lines['items']['$ref']);
	pg_assert_true(function_exists(ws_openapi_objects()['WorkspaceRecordLine']), 'WorkspaceRecordLine is declared');
	pg_assert_same(null, ws_api_claude_record_present(array('tag' => '<#task:4>', 'name' => 'A task'))['lines']);
}

// The groups of channels: the parent, colour, access count, destination and
// the access holder's grant are null when there is none (ws_api_channel_group_present()
// and the presenters beside it); everything else is always there.
function test_api_openapi_status_channel_groups_declare_their_nulls()
{
	$expect = array(
		'WorkspaceChannelGroup'       => array('parent_id', 'color', 'access'),
		'WorkspaceChannelGroupMove'   => array('group_id', 'group_path', 'previous_group_id'),
		'WorkspaceChannelGroupRemoval' => array('parent_id'),
		'WorkspaceChannelGroupMemberships' => array(),
		'WorkspaceChannelGroupChange' => array(),
		'WorkspaceChannelGroupAccessChange' => array(),
	);

	$objects = ws_openapi_objects();

	foreach ($expect as $name => $fields) {

		$declared = call_user_func($objects[$name]);

		foreach ($declared as $field => $spec) {
			$schema = api_openapi_type_spec($spec);
			pg_assert_same(in_array($field, $fields, true), !empty($schema['nullable']), $name . '.' . $field);
		}
	}

	$access = ws_api_channel_group_access_schema();

	foreach (array('people', 'above') as $list) {
		pg_assert_same('string?', $access[$list][0]['granted_by'], $list . '.granted_by');
		pg_assert_same('string?', $access[$list][0]['granted_at'], $list . '.granted_at');
	}

	$group = ws_api_channel_group_present(array('id' => '2', 'name' => 'Sales', 'parent_id' => '0', 'hex' => ''), false);

	pg_assert_same(null, $group['parent_id']);
	pg_assert_same(null, $group['color']);
	pg_assert_same(null, $group['access']);

	$move = ws_api_channel_group_move_present(array('id' => '9', 'group_id' => '0'), 0, array('path' => ''), false);

	pg_assert_same(null, $move['group_id']);
	pg_assert_same(null, $move['group_path']);
	pg_assert_same(null, $move['previous_group_id']);
}
