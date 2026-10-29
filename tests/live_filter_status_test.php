<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for live filters that say what they found. The API applications
 * search and the Workspace MCP tool filter hide rows as one types; each
 * keeps a hidden status region that is told the number of rows left, and a
 * visible line when none is left. Enter in the MCP tool filter does not
 * submit the tools form. The marketplace product picker and category search
 * write their Loading, Nothing found and error lines into status regions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function live_filter_status_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_live_filter_status_api_applications()
{
	$source = live_filter_status_test_source('api_settings.php');

	pg_assert_true((bool) preg_match('/id="api_filter_status"[^>]*role="status"|role="status"[^>]*id="api_filter_status"/', $source), 'the API applications search has a status region');
	pg_assert_contains('id="api_filter_empty"', $source, 'the API applications list has a line for a search that matches nothing');
	pg_assert_contains("'{var:1} found'", $source, 'the status region says how many applications were found');
	pg_assert_contains("lang('Nothing found.')", $source, 'the status region and the empty line say nothing was found');
}

function test_live_filter_status_mcp_tools()
{
	$source = live_filter_status_test_source('includes/workspace/mcp.php');

	pg_assert_contains('data-ws-mcp-filter-status', $source, 'the MCP tool filter has a status region');
	pg_assert_true((bool) preg_match('/data-ws-mcp-filter-status[^>]*role="status"|role="status"[^>]*data-ws-mcp-filter-status/', $source), 'the MCP tool filter status is role=status');
	pg_assert_contains('data-ws-mcp-empty', $source, 'the MCP tool list has a line for a filter that matches nothing');
	pg_assert_contains("'{var:1} found'", $source, 'the MCP tool filter says how many tools were found');
	pg_assert_true((bool) preg_match("/addEventListener\(\\\\'keydown\\\\'.*?Enter.*?isComposing.*?data-ws-mcp-filter.*?preventDefault/s", $source), 'Enter in the MCP tool filter does not submit the tools form');
}

function test_live_filter_status_marketplace_pickers()
{
	$source = live_filter_status_test_source('assets/js/marketplace_products.js');

	pg_assert_true((bool) preg_match("/caption\.setAttribute\('role', 'status'\)/", $source), 'the product picker caption is a status region');
	pg_assert_true((bool) preg_match("/status\.setAttribute\('role', 'status'\)/", $source), 'the category search has a status region');
	pg_assert_contains("categories.status.textContent = label('loading')", $source, 'the category search says it is loading in its status region');
	pg_assert_contains("categories.status.textContent = answer.ok ? label('nothing')", $source, 'the category search says nothing was found in its status region');
}
