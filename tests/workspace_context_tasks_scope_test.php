<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the Workspace reads an MCP client takes as context - a channel's
 * context (the channel_context resource and the channel_summary prompt) and
 * where a record was talked about (the record_refs resource) - hand out tasks
 * only to a key or connection holding tasks:read, as GET /workspace/my-day,
 * GET /workspace/search and the customer history do.
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

function workspace_context_tasks_scope_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// Without tasks:read the open tasks are not even read; with it each brief
// is laid out with its assignees.
function test_workspace_context_tasks_need_tasks_read()
{
	$read = 0;

	$load = function () use (&$read) {
		$read++;

		return array(array(
			'id'        => '31',
			'number'    => 'T-31',
			'title'     => 'Call the supplier',
			'status'    => 'open',
			'due_date'  => '2026-11-06',
			'assignees' => array(array('id' => '4', 'name' => 'Ayşe')),
		));
	};

	pg_assert_same(array(), ws_api_channel_context_tasks(false, $load), 'no tasks without tasks:read');
	pg_assert_same(0, $read, 'nor are they read');

	pg_assert_same(array(array(
		'id'        => 31,
		'number'    => 'T-31',
		'title'     => 'Call the supplier',
		'status'    => 'open',
		'due_date'  => '2026-11-06',
		'assignees' => array(array('user_id' => 4, 'name' => 'Ayşe')),
	)), ws_api_channel_context_tasks(true, $load), 'the open tasks with tasks:read');

	$schema = ws_api_channel_context_schema();

	pg_assert_same(array_keys($schema['open_tasks'][0]), array_keys(ws_api_channel_context_tasks(true, $load)[0]), 'the declaration matches the presenter');
	pg_assert_same(array('string'), $schema['left_out'] ?? null, 'left_out is declared');
}

// The context route asks the key's scopes and names what it left out; the
// refs route lists task ids only with tasks:read.
function test_workspace_context_tasks_routes_ask_the_scope()
{
	$context = workspace_context_tasks_scope_test_source('ws_api_channel_context');

	pg_assert_contains("api_has_scope(api_current_scopes(), 'tasks:read')", $context, 'the context asks for tasks:read');
	pg_assert_contains('ws_api_channel_context_tasks($tasks_read', $context, 'and reads the tasks through the gate');
	pg_assert_true(strpos($context, 'ws_tasks_list(') === false || strpos($context, 'ws_tasks_list(') > strpos($context, 'function () use'), 'no task is read outside the gate');
	pg_assert_contains("'left_out'", $context, 'what was left out is named');

	$refs = workspace_context_tasks_scope_test_source('ws_api_refs_get');

	pg_assert_contains("api_has_scope(api_current_scopes(), 'tasks:read')", $refs, 'the refs route asks for tasks:read');
}
