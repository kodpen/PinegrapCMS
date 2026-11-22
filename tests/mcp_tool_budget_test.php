<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the size budget of the MCP tool list: a tool's description at
 * most 600 characters, a whole tool at most 4,096 bytes as JSON. The tools
 * over a budget today are listed here; the lists may only shrink, so a
 * listed tool that fits fails until it is taken off, and a new tool over a
 * budget fails at once. Reference detail goes into a route's 'details' key,
 * which only the OpenAPI document reads.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// Tools whose description passes 600 characters today. Take a tool off when
// its description fits; never add one.
function mcp_tool_budget_test_long_descriptions()
{
	return array();
}

// Tools larger than 4,096 bytes as JSON today. Take a tool off when it fits;
// never add one.
function mcp_tool_budget_test_large_tools()
{
	return array();
}

// Every route of the core and of the modules, as tools for a caller holding
// every scope they use, on a site with every module on; an application
// agent's own routes too, which only its own key is offered.
function mcp_tool_budget_test_tools()
{
	static $tools = null;

	if ($tools === null) {
		$all = array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes());

		$scopes = array();

		foreach ($all as $route) {
			if (isset($route['scope']) && ($route['scope'] !== '')) {
				$scopes[$route['scope']] = true;
			}
		}

		$registry = pg_agent_guide_registry();

		$modules = array('store', 'workspace', 'erp');

		$tools = array();

		foreach (pg_mcp_routes_for($all, array_keys($scopes), true) as $route) {
			$tool = pg_mcp_tool_from_route($route);

			// The guide line depends on the modules a site runs, read once a
			// request: measure every tool as a site with every module on sends it.
			$topic = pg_agent_guide_topic_of_route($registry, pg_agent_guide_listed($registry, array($route['id']), $modules), $route['id']);

			$tool['description'] = trim((string)preg_replace('/ ?Guide: [a-z_]+\.$/', '', $tool['description'])
				. (($topic !== '') ? ' Guide: ' . $topic . '.' : ''));

			$tools[] = $tool;
		}
	}

	return $tools;
}

// The tools over the named budget, by name.
function mcp_tool_budget_test_over($budget)
{
	$over = array();

	foreach (mcp_tool_budget_test_tools() as $tool) {
		if (in_array($budget, pg_mcp_tool_over_budget($tool), true)) {
			$over[] = $tool['name'];
		}
	}

	return $over;
}

function mcp_tool_budget_test_check($budget, $listed, $what)
{
	$over = mcp_tool_budget_test_over($budget);

	pg_assert_same(count($listed), count(array_unique($listed)), 'the ' . $what . ' list names each tool once');

	$new = array_values(array_diff($over, $listed));

	pg_assert_same(array(), $new, 'tools over the ' . $what . ' budget that are not on its list: fit them, do not list them');

	$fits = array_values(array_diff($listed, $over));

	pg_assert_same(array(), $fits, 'listed tools that fit the ' . $what . ' budget now: take them off the list');
}

function test_mcp_tool_budget_values()
{
	pg_assert_same(array('description' => 600, 'tool' => 4096), pg_mcp_tool_budgets());

	$short = array('name' => 'a', 'description' => str_repeat('é', 600));

	pg_assert_same(array(), pg_mcp_tool_over_budget($short), '600 characters fit, counted as characters');

	$short['description'] .= 'x';

	pg_assert_same(array('description'), pg_mcp_tool_over_budget($short));

	$large = array('name' => 'a', 'description' => 'b', 'inputSchema' => array('x' => str_repeat('y', 4096)));

	pg_assert_same(array('tool'), pg_mcp_tool_over_budget($large));
}

function test_mcp_tool_budget_descriptions_only_shrink()
{
	mcp_tool_budget_test_check('description', mcp_tool_budget_test_long_descriptions(), 'description');
}

function test_mcp_tool_budget_tools_only_shrink()
{
	mcp_tool_budget_test_check('tool', mcp_tool_budget_test_large_tools(), 'tool size');
}

// A route's details reach the OpenAPI operation, after its description and
// scope, and never the MCP tool.
function test_mcp_tool_budget_details_are_for_the_document_only()
{
	$route = array(
		'id'          => 'products.budget_probe',
		'method'      => 'GET',
		'path'        => '/products/budget-probe',
		'scope'       => 'products:read',
		'summary'     => 'Probe',
		'description' => 'Short text a caller needs.',
		'details'     => 'Reference detail looked up rather than needed.',
		'params'      => array()
	);

	pg_assert_false(strpos(json_encode(pg_mcp_tool_from_route($route)), 'Reference detail') !== false, 'the tool does not carry the details');

	pg_assert_same(
		"Short text a caller needs. Requires the products:read scope.\n\nReference detail looked up rather than needed.",
		api_openapi_operation_description($route)
	);

	unset($route['description']);

	pg_assert_same("Requires the products:read scope.\n\nReference detail looked up rather than needed.", api_openapi_operation_description($route));

	$route['scope'] = '';
	unset($route['details']);

	pg_assert_same('', api_openapi_operation_description($route));
}
