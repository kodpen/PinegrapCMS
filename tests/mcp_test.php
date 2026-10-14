<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP server's rules that need no database
 * (includes/mcp/protocol.php, includes/mcp/tools.php): protocol versions,
 * the header check of the current generation, and how an API route becomes
 * a tool and its arguments a request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

function mcp_test_route()
{
	return array(
		'id'      => 'products.update',
		'method'  => 'POST',
		'path'    => '/products/{id}',
		'scope'   => 'products:write',
		'dry_run' => true,
		'summary' => 'Change a product',
		'description' => 'Only the fields present are written.',
		'params'  => array(
			array('name' => 'id',       'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
			array('name' => 'verbose',  'in' => 'query', 'type' => 'bool'),
			array('name' => 'price',    'in' => 'body',  'type' => 'money'),
			array('name' => 'tax_rate', 'in' => 'body',  'type' => 'decimal', 'nullable' => true, 'description' => 'Percentage.')
		)
	);
}

function test_mcp_versions()
{
	pg_assert_same('2025-06-18', pg_mcp_legacy_version('2025-06-18'), 'a supported older version is kept');
	pg_assert_same('2024-11-05', pg_mcp_legacy_version('2024-11-05'), 'the oldest that older SDKs still ask for');
	pg_assert_same('2025-11-25', pg_mcp_legacy_version('2024-10-07'), 'an unknown one gets the newest that opens with initialize');
	pg_assert_same('2025-11-25', pg_mcp_legacy_version('2026-07-28'), 'initialize never answers the sessionless generation');
	pg_assert_same('2026-07-28', pg_mcp_request_version('2026-07-28', array()), 'header');
	pg_assert_same('2026-07-28', pg_mcp_request_version('', array('_meta' => array('io.modelcontextprotocol/protocolVersion' => '2026-07-28'))), '_meta');
	pg_assert_same('2025-03-26', pg_mcp_request_version('', array()), 'neither');
	pg_assert_true(pg_mcp_is_modern('2026-07-28'));
	pg_assert_false(pg_mcp_is_modern('2025-11-25'));
}

function test_mcp_header_mismatch()
{
	$headers = array('method' => 'tools/call', 'name' => 'products_list', 'version' => '2026-07-28');
	$params = array('name' => 'products_list', '_meta' => array('io.modelcontextprotocol/protocolVersion' => '2026-07-28'));

	pg_assert_same('', pg_mcp_header_mismatch('tools/call', $params, $headers), 'all agree');
	pg_assert_true(pg_mcp_header_mismatch('tools/list', $params, $headers) !== '', 'method differs');
	pg_assert_true(pg_mcp_header_mismatch('tools/call', array('name' => 'orders_list'), $headers) !== '', 'name differs');
	pg_assert_true(pg_mcp_header_mismatch('tools/call', array('name' => 'products_list', '_meta' => array('io.modelcontextprotocol/protocolVersion' => '2025-11-25')), $headers) !== '', 'version differs');
	pg_assert_same('', pg_mcp_header_mismatch('tools/list', array(), array('method' => 'tools/list', 'name' => '', 'version' => '2026-07-28')), 'no name needed');
}

function test_mcp_origin()
{
	pg_assert_true(pg_mcp_origin_is_valid('https://claude.ai'));
	pg_assert_true(pg_mcp_origin_is_valid('http://localhost:6274'));
	pg_assert_true(pg_mcp_origin_is_valid('null'));
	pg_assert_false(pg_mcp_origin_is_valid('https://claude.ai/path'));
	pg_assert_false(pg_mcp_origin_is_valid('javascript:alert(1)'));
}

function test_mcp_routes_offered()
{
	pg_assert_true(pg_mcp_route_is_offered(mcp_test_route()), 'an ordinary route');
	pg_assert_false(pg_mcp_route_is_offered(array('id' => 'auth.login', 'scope' => '', 'public' => true)), 'public sign in');
	pg_assert_false(pg_mcp_route_is_offered(array('id' => 'auth.me', 'scope' => 'meta:read')), 'connection itself');
	pg_assert_false(pg_mcp_route_is_offered(array('id' => 'push.config', 'scope' => 'account:read')), 'push');
	pg_assert_false(pg_mcp_route_is_offered(array('id' => 'products.delete_post', 'scope' => 'products:write')), 'POST alias of a DELETE');
	pg_assert_false(pg_mcp_route_is_offered(array('id' => 'openapi', 'scope' => '')), 'no scope');
	pg_assert_same('erp_accounts_list', pg_mcp_tool_name('erp.accounts.list'));
}

function test_mcp_tool_from_route()
{
	$tool = pg_mcp_tool_from_route(mcp_test_route());

	pg_assert_same('products_update', $tool['name']);
	pg_assert_same('Change a product. Only the fields present are written.', $tool['description']);
	pg_assert_same(array('id'), $tool['inputSchema']['required']);

	$properties = (array)$tool['inputSchema']['properties'];

	pg_assert_same('integer', $properties['id']['type']);
	pg_assert_same(1, $properties['id']['minimum']);
	pg_assert_same('integer', $properties['price']['type'], 'money is an integer');
	pg_assert_same(array('number', 'null'), $properties['tax_rate']['type'], 'nullable');
	pg_assert_same('Percentage.', $properties['tax_rate']['description']);
	pg_assert_same('boolean', $properties['dry_run']['type'], 'a rehearsable write takes dry_run');
	pg_assert_false($tool['annotations']['readOnlyHint']);
	pg_assert_true($tool['annotations']['destructiveHint'], 'an update may overwrite');

	$read = pg_mcp_tool_from_route(array('id' => 'products.list', 'method' => 'GET', 'path' => '/products', 'scope' => 'products:read', 'summary' => 'List products', 'params' => array()));

	pg_assert_true($read['annotations']['readOnlyHint']);
	pg_assert_false($read['annotations']['destructiveHint']);
	pg_assert_false(isset(((array)$read['inputSchema']['properties'])['dry_run']), 'a read takes no dry_run');

	$create = pg_mcp_tool_from_route(array('id' => 'products.create', 'method' => 'POST', 'path' => '/products', 'scope' => 'products:write', 'params' => array()));

	pg_assert_false($create['annotations']['destructiveHint'], 'a creation only adds');
	pg_assert_same('{}', json_encode($create['inputSchema']['properties']), 'no parameters is an empty object, not a list');
}

// A nullable enum takes null as well: its list says so, or a client that
// checks the list refuses the clear the router accepts.
function test_mcp_nullable_enum_lists_null()
{
	$schema = pg_mcp_param_schema(array('name' => 'color', 'in' => 'body', 'type' => 'enum', 'values' => array('#b5534b', '#c9774a'), 'nullable' => true));

	pg_assert_same(array('string', 'null'), $schema['type']);
	pg_assert_same(array('#b5534b', '#c9774a', null), $schema['enum']);

	$plain = pg_mcp_param_schema(array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('public', 'private')));

	pg_assert_same(array('public', 'private'), $plain['enum'], 'a required choice stays as it is');
}

function test_mcp_arguments_are_sorted_by_where_the_route_reads_them()
{
	$split = pg_mcp_arguments(mcp_test_route(), array(
		'id'       => 12,
		'verbose'  => true,
		'price'    => 1999,
		'tax_rate' => null,
		'colour'   => 'red',
		'dry_run'  => true
	));

	pg_assert_same(array('id' => '12'), $split['path']);
	pg_assert_same(array('verbose' => 'true', 'price' => 1999, 'tax_rate' => null, 'colour' => 'red'), $split['input']);
	pg_assert_same(array('price', 'tax_rate', 'colour'), $split['body_keys'], 'an undeclared argument stays a body key, so the route refuses it');
	pg_assert_true($split['dry_run']);
	pg_assert_false(pg_mcp_arguments(mcp_test_route(), array('dry_run' => false))['dry_run']);
	pg_assert_same('/products/12', pg_mcp_route_path(mcp_test_route(), $split['path']));
}
