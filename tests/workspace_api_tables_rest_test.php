<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace API routes of a table's bin, a flow made from a
 * table, titled blocks and a customer channel's follow-up: who may call
 * each (the panel's own check), the person-only purge with no MCP tool, and
 * every write's dry run stopping after its checks.
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

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_tables_rest_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function ws_tables_rest_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// $a appears in $source before $b.
function ws_tables_rest_test_before($source, $a, $b, $message)
{
	$pa = strpos($source, $a);
	$pb = strpos($source, $b);
	pg_assert_true(($pa !== false) && ($pb !== false) && ($pa < $pb), $message);
}

// G31: a binned row deleted for good, by the person only, no MCP tool.
function test_ws_api_table_bin_row_purge()
{
	$route = ws_tables_rest_test_route('workspace.table_rows.purge');
	pg_assert_true(is_array($route), 'the purge route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/tables/{id}/deleted-rows/{bin_id}/delete', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');
	pg_assert_same(false, $route['mcp'] ?? null, 'carries \'mcp\' => false');
	pg_assert_false(pg_mcp_route_is_offered($route), 'no MCP tool deletes a row for good');

	$source = ws_tables_rest_test_source($route['handler']);
	ws_tables_rest_test_before($source, 'ws_api_person_only(', 'api_dry_run_stop(', 'an application key is refused before a dry run too');
	ws_tables_rest_test_before($source, 'ws_wtable_bin_row_delete_check(', 'api_dry_run_stop(', 'the panel\'s check before the dry run');
	ws_tables_rest_test_before($source, 'api_dry_run_stop(', 'ws_wtable_bin_row_delete(', 'the write after the dry run');
}

// G32: a draft flow from a table's rows, a tool, rehearsed with every check.
function test_ws_api_table_flow()
{
	$route = ws_tables_rest_test_route('workspace.tables.flow');
	pg_assert_true(is_array($route), 'the flow route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/tables/{id}/flow', $route['path']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');
	pg_assert_true(pg_mcp_route_is_offered($route), 'a draft never runs: a tool');
	pg_assert_same(array('id', 'step', 'person', 'days', 'after'), array_column($route['params'], 'name'), 'one column id per part');

	$source = ws_tables_rest_test_source($route['handler']);
	pg_assert_contains('ws_api_wtable_or_404(', $source, 'a table the caller reaches');
	ws_tables_rest_test_before($source, 'ws_wtable_flow_create($viewer, $table, $chosen, true)', 'api_dry_run_stop(', 'the checks run before the dry run stops');

	$create = ws_tables_rest_test_source('ws_wtable_flow_create');
	ws_tables_rest_test_before($create, 'ws_flow_save_check(', 'ws_flow_save($viewer', 'a rehearsal meets the save\'s refusals');

	pg_assert_same('not_found', ws_wtable_flow_refusal('', true), 'a table the person does not read');
	pg_assert_same('forbidden', ws_wtable_flow_refusal('view', false), 'somebody who does not build flows');
	pg_assert_same('', ws_wtable_flow_refusal('view', true));
}

// G5: titled blocks searched and read, within what the caller reaches.
function test_ws_api_blocks()
{
	$search = ws_tables_rest_test_route('workspace.blocks.list');
	$get = ws_tables_rest_test_route('workspace.blocks.get');
	pg_assert_true(is_array($search) && is_array($get), 'both block routes are declared');
	pg_assert_same('GET', $search['method']);
	pg_assert_same('/workspace/blocks', $search['path']);
	pg_assert_same('GET', $get['method']);
	pg_assert_same('/workspace/blocks/{id}', $get['path']);
	pg_assert_same('workspace:read', $get['scope']);

	pg_assert_contains('ws_api_block_reachable(', ws_tables_rest_test_source($search['handler']), 'the search keeps an assistant to where it may be asked');
	pg_assert_contains('ws_api_block_reachable(', ws_tables_rest_test_source($get['handler']), 'and so does the read');
	pg_assert_contains('ws_claude_channel_allowed(', ws_tables_rest_test_source('ws_api_block_reachable'));
	pg_assert_contains('ws_api_note_reachable(', ws_tables_rest_test_source('ws_api_block_reachable'));
}

// G22: a follow-up on a customer channel, for anybody who may write there.
function test_ws_api_customer_followup()
{
	$route = ws_tables_rest_test_route('workspace.channels.customer_followup');
	pg_assert_true(is_array($route), 'the follow-up route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/customer/followup', $route['path']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');

	$source = ws_tables_rest_test_source($route['handler']);
	ws_tables_rest_test_before($source, 'ws_can_post_channel(', 'api_dry_run_stop(', 'the panel\'s check first');
	ws_tables_rest_test_before($source, 'ws_customer_followup_open($viewer, $channel, $params, true)', 'api_dry_run_stop(', 'the follow-up\'s checks before the dry run');

	$open = ws_tables_rest_test_source('ws_customer_followup_open');
	ws_tables_rest_test_before($open, 'This channel is not about a customer.', 'ws_followup_task_open(', 'refused where the channel is about nobody');

	$task = ws_tables_rest_test_source('ws_followup_task_open');
	ws_tables_rest_test_before($task, 'ws_task_create_check(', 'ws_task_create($viewer', 'a rehearsal meets the task\'s refusals');
}
