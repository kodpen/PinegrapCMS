<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent filter of GET /workspace/assistant-requests that need
 * no database: agent:<id> and model:<id> match every value the agent's rows
 * carry, a proposal's app:<id> row stays out, a value of any other shape is
 * refused, and the filter narrows the reader's reach instead of widening it
 * (a request of a channel the reader cannot read stays out).
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_assistant_requests_registry_agent()
{
	pg_assert_same(" AND (agent = 'agent:4' OR (agent = 'app:4' AND message_id > 0))", ws_api_assistant_requests_registry_where('agent:4'), 'both shapes of an app agent, the older one only on a message');
}

function test_workspace_assistant_requests_model_assistant()
{
	pg_assert_same(" AND (agent = 'model:9')", ws_api_assistant_requests_registry_where('model:9'));
}

function test_workspace_assistant_requests_registry_refused()
{
	foreach (array('', 'claude', 'ai', 'mcp', 'app:4', 'agent:0', 'agent:', 'model:x', "agent:4' OR 1=1", 'agent:04', 'oauth:7') as $value) {
		pg_assert_same('', ws_api_assistant_requests_registry_where($value), 'no registry agent: ' . $value);
	}
}

function test_workspace_assistant_requests_reach()
{
	pg_assert_same("((channel_id = 0 AND requested_by = '5') OR channel_id IN (3,8))", ws_api_assistant_requests_reach(5, array(3, 8), ''));
	pg_assert_same("((channel_id = 0 AND requested_by = '5' AND task_id = 0) OR (channel_id = 0 AND task_id IN (SELECT t.id FROM ws_tasks t WHERE t.x = 1)))", ws_api_assistant_requests_reach('5', array(), 't.x = 1'), 'no channel read: none listed');
}

function test_workspace_assistant_requests_registry_narrowed()
{
	// The filter is added to the reach: a request of channel 9, which the
	// reader may not read, matches the agent but not the reach.
	$where = ws_api_assistant_requests_reach(5, array(3), '') . ws_api_assistant_requests_registry_where('agent:4');

	pg_assert_same("((channel_id = 0 AND requested_by = '5') OR channel_id IN (3)) AND (agent = 'agent:4' OR (agent = 'app:4' AND message_id > 0))", $where);
	pg_assert_false(strpos($where, '9') !== false, 'channel 9 is never reached');
	pg_assert_same(0, strpos($where, '('), 'the reach comes first, ANDed with the filter');
}

function test_workspace_assistant_requests_route_takes_agents()
{
	$route = null;

	foreach (ws_api_routes() as $row) {
		if ($row['id'] === 'workspace.assistant_requests.list') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'the route is declared');
	$agent = $route['params'][0];
	pg_assert_same('agent', $agent['name']);
	pg_assert_same('string', $agent['type'], 'agent:<id> and model:<id> pass the validator');
	pg_assert_contains('model:<id>', $agent['description']);
	pg_assert_same('string?', ws_api_assistant_request_schema()['label']);
}
