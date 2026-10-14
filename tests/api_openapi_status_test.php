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
 * declared so a generated client takes null there, and the DEFAULT NULL
 * columns every create path writes, declared without it. Last, the two
 * security schemes and the three kinds of caller they describe.
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

// The DEFAULT NULL columns every create path writes, which the objects declare
// without a question mark (api_openapi_type_spec()), as table => columns.
function api_openapi_status_test_written_columns()
{
	return array(
		'products'       => array('name'),
		'product_groups' => array('name'),
		'page'           => array('page_name', 'page_title', 'page_meta_description'),
		'files'          => array('name', 'type'),
	);
}

// The create paths that build their column list at run time, with the line
// that keeps the columns above written there.
function api_openapi_status_test_built_inserts()
{
	return array(
		// A copy of every column of the original page, under a new name.
		'duplicate_style.php|page' => "if (\$col === 'page_name')",
		// Only a row whose name cell is filled in is inserted, and a cell that
		// is filled in is always one of the columns.
		'import_products.php|products' => 'if ($row[$name_key]) {',
		'includes/api/resources/files.php|files' => '$columns = "name, folder, description, type, size, user, timestamp";',
	);
}

// Every INSERT into those tables in the product's own code, as
// file|table => the text between the table name and VALUES / SELECT / SET,
// keyed with the line so two in one file stay apart.
function api_openapi_status_test_inserts()
{
	$root = rtrim(str_replace('\\', '/', PG_FUNCTIONS_DIR), '/');

	$tables = implode('|', array_keys(api_openapi_status_test_written_columns()));

	// Bundled libraries never write the site's tables.
	$skip = '#^(assets/lib|data|includes/(dompdf|phpexcel|phpmailer|stripe|iyzipay-php|boxpacker|qrcode))/#';

	$out = array();

	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {

		$path = str_replace('\\', '/', (string) $file);

		if (substr($path, -4) !== '.php') {
			continue;
		}

		$relative = substr($path, strlen($root) + 1);

		if (preg_match($skip, $relative)) {
			continue;
		}

		$source = (string) file_get_contents($path);

		if (strpos($source, 'INSERT') === false) {
			continue;
		}

		if (!preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(' . $tables . ')`?(?![\w`])(.{0,8000}?)\b(?:VALUES|SELECT|SET)\b/is', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($matches as $match) {
			$line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
			$out[$relative . ':' . $line . '|' . strtolower($match[1][0])] = $match[2][0];
		}
	}

	return $out;
}

// The spelling a declaration gives one top-level field, read from the source:
// the resource files load only behind the API entry, which a test does not
// define (tools/check_api_schema.php reads them the same way).
function api_openapi_status_test_declared($file, $function, $field)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/' . $file);

	if (!preg_match('/function ' . $function . '\(\)\s*\{(.*?)\n\}/s', $source, $body)) {
		return null;
	}

	// The first line naming the field is the top-level one: the nested
	// objects of these declarations come after it.
	return preg_match('/\'' . $field . '\'\s*=>\s*\'([^\']*)\'/', $body[1], $spec) ? $spec[1] : null;
}

// A product's and a group's name, a page's name, title and meta description,
// a file's name and type, a form's name: DEFAULT NULL columns, declared without
// a question mark because every create path writes them.
function test_api_openapi_status_written_columns_are_not_null()
{
	$fields = array(
		array('products.php', 'api_product_schema', 'name'),
		array('product_groups.php', 'api_product_group_schema', 'name'),
		array('pages.php', 'api_page_schema', 'name'),
		array('pages.php', 'api_page_schema', 'title'),
		array('pages.php', 'api_page_schema', 'meta_description'),
		array('files.php', 'api_file_schema', 'name'),
		array('files.php', 'api_file_schema', 'type'),
		array('forms.php', 'api_form_schema', 'name'),
	);

	foreach ($fields as $field) {
		$spec = api_openapi_status_test_declared($field[0], $field[1], $field[2]);
		pg_assert_same('string', $spec, $field[1] . ' ' . $field[2]);
		pg_assert_false(!empty(api_openapi_type_spec((string) $spec)['nullable']), $field[1] . ' ' . $field[2] . ' is never null');
	}

	// The column a create path leaves out is the one that is nullable.
	pg_assert_same('string?', api_openapi_status_test_declared('products.php', 'api_product_schema', 'short_description'), 'Product.short_description may be null');
}

// What the declarations above rest on: every create path writes those columns.
// A new INSERT that leaves one out fails here, and the field it feeds takes
// the question mark in the same change.
function test_api_openapi_status_create_paths_write_those_columns()
{
	$written = api_openapi_status_test_written_columns();

	$built = api_openapi_status_test_built_inserts();

	$inserts = api_openapi_status_test_inserts();

	$seen_built = array();

	foreach ($inserts as $where => $columns) {

		list($place, $table) = explode('|', $where);

		$file = substr($place, 0, strrpos($place, ':'));

		$missing = array();

		foreach ($written[$table] as $column) {
			if (!preg_match('/(?<![\w$\'])' . $column . '(?![\w\'])/', $columns)) {
				$missing[] = $column;
			}
		}

		if (empty($missing)) {
			continue;
		}

		// A column list built at run time names no column itself.
		if (isset($built[$file . '|' . $table])) {
			$seen_built[] = $file . '|' . $table;
			continue;
		}

		pg_assert_same(array(), $missing, $place . ' writes ' . $table);
	}

	pg_assert_true(count($inserts) > 50, 'the create paths were found');

	sort($seen_built);
	$expected = array_keys($built);
	sort($expected);

	pg_assert_same($expected, $seen_built, 'the column lists built at run time');

	foreach ($built as $where => $line) {
		$file = substr($where, 0, strpos($where, '|'));
		pg_assert_contains($line, (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}

	// The panel's and POST /products' own create path writes through a helper
	// that takes the table as an argument.
	$builder = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');

	pg_assert_contains("\$product['name'] = get_unique_name(", $builder, 'pg_pb_create_product() writes the name');
	pg_assert_contains("'name'              => \$name,", $builder, 'pg_pb_create_group() writes the name');
}

// The Bearer scheme names both kinds of token it carries and what each may
// do, the way the person-only refusals name them; Basic says what an
// application key may not.
function test_api_openapi_status_security_schemes_name_the_three_callers()
{
	$schemes = api_openapi_security_schemes();

	pg_assert_same(array('applicationKey', 'deviceToken'), array_keys($schemes));
	pg_assert_same('basic', $schemes['applicationKey']['scheme']);
	pg_assert_same('bearer', $schemes['deviceToken']['scheme']);

	$bearer = $schemes['deviceToken']['description'];

	pg_assert_contains('team devices session (POST /auth/login)', $bearer);
	pg_assert_contains('OAuth connection', $bearer);
	pg_assert_contains('POST /auth/refresh', $bearer);
	pg_assert_contains('only credential that can change the person\'s own words', $bearer);
	pg_assert_contains('403 forbidden', $bearer);
	pg_assert_contains('403 forbidden', $schemes['applicationKey']['description']);
	pg_assert_contains('webhook subscriptions', $schemes['applicationKey']['description']);

	foreach ($schemes as $name => $scheme) {
		foreach (array('signed in on a device', 'mobile', 'phone') as $old) {
			pg_assert_false(stripos($scheme['description'], $old) !== false, $name . ' does not say ' . $old);
		}
	}
}
