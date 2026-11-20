<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP server's compact list (mcp_compact.php,
 * includes/mcp/tools.php): its size, how tools_find, tools_describe,
 * tools_read and tools_write reach a route, the money rule, and the second
 * address being the same OAuth resource.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

// The site's address parts the OAuth server builds its addresses from;
// software is the folder other tests take when none is defined.
if (!defined('PATH')) {
	define('PATH', '/');
}

if (!defined('HOSTNAME')) {
	define('HOSTNAME', 'shop.example.com');
}

if (!defined('SOFTWARE_DIRECTORY')) {
	define('SOFTWARE_DIRECTORY', 'software');
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// Every route of the core and of the modules, as tools for a caller holding
// every scope they use.
function mcp_compact_test_routes()
{
	static $routes = null;

	if ($routes === null) {
		$all = array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes());

		$scopes = array();

		foreach ($all as $route) {
			if (isset($route['scope']) && ($route['scope'] !== '')) {
				$scopes[$route['scope']] = true;
			}
		}

		$routes = pg_mcp_routes_for($all, array_keys($scopes));
	}

	return $routes;
}

function mcp_compact_test_names($tools)
{
	$names = array();

	foreach ($tools as $tool) {
		$names[] = $tool['name'];
	}

	return $names;
}

// The list without its money tools stays under 8 KB, whatever the caller
// holds: it is what an assistant reads before its first question.
function test_mcp_compact_list_is_small()
{
	$routes = mcp_compact_test_routes();

	$tools = pg_mcp_compact_tools_list($routes);

	$without_money = array();

	$money = 0;

	foreach ($tools as $tool) {
		if (isset($routes[$tool['name']]) && pg_mcp_route_moves_money($routes[$tool['name']])) {
			$money++;
			continue;
		}

		$without_money[] = $tool;
	}

	$bytes = strlen((string)pg_mcp_json_encode($without_money));

	pg_assert_true($bytes < 8192, 'the compact list without money tools is ' . $bytes . ' bytes');
	pg_assert_same(array('guide_read', 'tools_find', 'tools_describe', 'tools_read', 'tools_write'), mcp_compact_test_names($without_money));
	pg_assert_true($money > 0, 'the money tools are listed by their own names');

	$full = strlen((string)pg_mcp_json_encode(pg_mcp_tools_list($routes)));

	pg_assert_true($full > 10 * $bytes, 'the full list is many times larger (' . $full . ' bytes)');
}

// Every money route the caller holds is on the compact list, and no other
// route is.
function test_mcp_compact_list_names_every_money_tool()
{
	$routes = mcp_compact_test_routes();

	$listed = mcp_compact_test_names(pg_mcp_compact_tools_list($routes));

	foreach ($routes as $name => $route) {
		if (pg_mcp_route_moves_money($route) || ($name === 'guide_read')) {
			pg_assert_true(in_array($name, $listed, true), $name . ' is listed');
		} else {
			pg_assert_false(in_array($name, $listed, true), $name . ' is not listed');
		}
	}

	$without = pg_mcp_compact_tools_list(array());

	pg_assert_same(array('tools_find', 'tools_describe', 'tools_read', 'tools_write'), mcp_compact_test_names($without), 'a caller with no tool');
}

function test_mcp_compact_write_refuses_money_and_reads()
{
	$routes = mcp_compact_test_routes();

	pg_assert_true(isset($routes['orders_create']) && pg_mcp_route_moves_money($routes['orders_create']), 'orders.create moves money');

	$money = pg_mcp_compact_target($routes, 'tools_write', array('name' => 'orders_create', 'arguments' => array()));

	pg_assert_same(400, $money['refuse'][0]);
	pg_assert_same('tool_moves_money', $money['refuse'][1]);

	$read = pg_mcp_compact_target($routes, 'tools_write', array('name' => 'products_list', 'arguments' => array()));

	pg_assert_same('tool_reads', $read['refuse'][1], 'a GET through tools_write');

	$write = pg_mcp_compact_target($routes, 'tools_read', array('name' => 'products_update', 'arguments' => array('id' => 3)));

	pg_assert_same('tool_writes', $write['refuse'][1], 'a write through tools_read');

	$money_read = pg_mcp_compact_target($routes, 'tools_read', array('name' => 'orders_create'));

	pg_assert_same('tool_writes', $money_read['refuse'][1], 'a money route through tools_read');
}

// A route runs as itself: the target is the route, so pg_mcp_route_run()
// applies its own scope, validation and dry run.
function test_mcp_compact_runs_the_route_itself()
{
	$routes = mcp_compact_test_routes();

	$read = pg_mcp_compact_target($routes, 'tools_read', array('name' => 'products_list', 'arguments' => array('limit' => 5)));

	pg_assert_same($routes['products_list'], $read['route']);
	pg_assert_same(array('limit' => 5), $read['arguments']);

	$write = pg_mcp_compact_target($routes, 'tools_write', array('name' => 'products_update', 'arguments' => array('id' => 3, 'price' => 1999), 'dry_run' => true));

	pg_assert_same($routes['products_update'], $write['route']);
	pg_assert_same(array('id' => 3, 'price' => 1999, 'dry_run' => true), $write['arguments'], 'dry_run is passed on as the tool takes it');

	$split = pg_mcp_arguments($write['route'], $write['arguments']);

	pg_assert_same(true, $split['dry_run']);

	$none = pg_mcp_compact_target(array(), 'tools_read', array('name' => 'products_list'));

	pg_assert_same(404, $none['refuse'][0], 'a tool the caller does not hold is no tool here either');

	$bad = pg_mcp_compact_target($routes, 'tools_write', array('name' => 'products_update', 'arguments' => 'id=3'));

	pg_assert_same('arguments', $bad['refuse'][3]);

	$nameless = pg_mcp_compact_target($routes, 'tools_read', array());

	pg_assert_same('name', $nameless['refuse'][3]);
}

function test_mcp_compact_find()
{
	$routes = mcp_compact_test_routes();

	$found = pg_mcp_compact_target($routes, 'tools_find', array('words' => 'update products'));

	$names = mcp_compact_test_names($found['answer']['tools']);

	pg_assert_true(in_array('products_update', $names, true), 'products_update is found');
	pg_assert_true(count($names) <= 20, 'at most 20');

	foreach ($found['answer']['tools'] as $tool) {
		pg_assert_true(mb_strlen($tool['description']) <= 200, $tool['name'] . ': one short sentence');
	}

	$area = pg_mcp_compact_find($routes, '', 'erp');

	pg_assert_true(!empty($area['tools']), 'an area alone lists its tools');

	foreach ($area['tools'] as $tool) {
		pg_assert_same('erp', pg_mcp_tool_area($routes[$tool['name']]['id']), $tool['name']);
	}

	pg_assert_same('validation_failed', pg_mcp_compact_target($routes, 'tools_find', array())['refuse'][1], 'neither words nor area');
	pg_assert_same('area', pg_mcp_compact_target($routes, 'tools_find', array('area' => 'nowhere'))['refuse'][3]);
	pg_assert_same(array('product', 'price', 'address'), pg_mcp_find_words('Products, PRICE address a'));
}

function test_mcp_compact_describe()
{
	$routes = mcp_compact_test_routes();

	$described = pg_mcp_compact_target($routes, 'tools_describe', array('names' => array('products_update', 'products_list', 'orders_create', 'nope')));

	pg_assert_same(array('nope'), $described['answer']['unknown']);
	pg_assert_same(array('tools_write', 'tools_read', 'own_name'), array_map(function ($tool) {
		return $tool['run_with'];
	}, $described['answer']['tools']));
	pg_assert_same(pg_mcp_json_encode(pg_mcp_tool_from_route($routes['products_update'])['inputSchema']), pg_mcp_json_encode($described['answer']['tools'][0]['inputSchema']), 'the tool\'s own input schema');

	$too_many = pg_mcp_compact_target($routes, 'tools_describe', array('names' => array('a', 'b', 'c', 'd', 'e', 'f')));

	pg_assert_same('names', $too_many['refuse'][3], 'at most five');
}

// The compact address is the same OAuth resource, with its own
// protected-resource document.
function test_mcp_compact_address_is_the_mcp_resource()
{
	$addresses = pg_oauth_resource_addresses();

	pg_assert_same(4, count($addresses['mcp']));

	foreach (pg_oauth_mcp_compact_addresses() as $url) {
		pg_assert_same('mcp', pg_oauth_resource_key($url), $url);
		pg_assert_true(pg_oauth_resource_is_compact($url), $url);

		$path = (string)parse_url($url, PHP_URL_PATH);

		pg_assert_same($url, pg_oauth_resource_for_suffix($path), 'its own document: ' . $path);
	}

	pg_assert_false(pg_oauth_resource_is_compact($addresses['mcp'][0]), 'mcp.php is the full list');

	$resources = pg_oauth_resources();

	pg_assert_same($addresses['mcp'][0], $resources['mcp'], 'the address given out stays mcp.php');
}

// The opening instructions on the compact address say how the tools are
// reached, and stay within the guide's size for them.
function test_mcp_compact_instructions()
{
	$sizes = pg_agent_guide_sizes();

	$full = pg_mcp_instructions();

	pg_assert_contains(pg_mcp_instructions_tools(false), $full, 'the full address keeps its sentence');

	$compact = str_replace(pg_mcp_instructions_tools(false), pg_mcp_instructions_tools(true), $full);

	foreach (pg_mcp_compact_tool_names() as $name) {
		pg_assert_contains($name, $compact);
	}

	pg_assert_true(strlen($compact) <= $sizes['instructions'], 'the compact instructions are ' . strlen($compact) . ' bytes');
}
