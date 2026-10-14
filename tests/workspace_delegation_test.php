<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of a task handed to an assistant
 * (includes/workspace/delegation.php: the instruction as it is kept, the
 * request statuses a run is still under way in, the keys of an MCP server's
 * alias and the queue its runs go by). Which assistants are connected is
 * read from the database: a sandbox check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/delegation.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');

function test_delegation_instruction_is_one_trimmed_block()
{
	pg_assert_same("Check the stock\nand tell me", ws_delegation_instruction_clean("  Check the stock\r\nand tell me \r\n"));
	pg_assert_same('', ws_delegation_instruction_clean('   '));
	pg_assert_same('', ws_delegation_instruction_clean(null));
}

function test_delegation_instruction_is_cut_to_the_limit()
{
	$long = str_repeat('ş', WS_DELEGATION_INSTRUCTION_MAX + 50);
	$kept = ws_delegation_instruction_clean($long);

	pg_assert_same(WS_DELEGATION_INSTRUCTION_MAX, mb_strlen($kept), 'counted in characters, not bytes');
}

function test_delegation_open_statuses()
{
	pg_assert_true(ws_delegation_status_open('queued'));
	pg_assert_true(ws_delegation_status_open('sent'));
	pg_assert_true(ws_delegation_status_open('running'));
	pg_assert_false(ws_delegation_status_open('answered'));
	pg_assert_false(ws_delegation_status_open('failed'));
	pg_assert_false(ws_delegation_status_open('cancelled'));
	pg_assert_false(ws_delegation_status_open(''));
}

function test_delegation_alias_key_names_its_server()
{
	pg_assert_same(7, ws_assistant_mcp_id('mcp:7'));
	pg_assert_same(1234, ws_assistant_mcp_id('mcp:1234'));
	pg_assert_same(0, ws_assistant_mcp_id('ai'));
	pg_assert_same(0, ws_assistant_mcp_id('claude'));
	pg_assert_same(0, ws_assistant_mcp_id('mcp:0'), 'no server 0');
	pg_assert_same(0, ws_assistant_mcp_id('mcp:07'), 'one spelling per server');
	pg_assert_same(0, ws_assistant_mcp_id('mcp:github'), 'the id, not the alias');
	pg_assert_same(0, ws_assistant_mcp_id(' mcp:7'));
	pg_assert_same(0, ws_assistant_mcp_id('mcp:7 '));
	pg_assert_same(0, ws_assistant_mcp_id(null));
}

function test_delegation_alias_runs_go_by_pinegrap_ai()
{
	pg_assert_same('ai', ws_assistant_agent_of('mcp:7', array('id' => 7, 'agent_tool' => '')), 'its queue and limits are Pinegrap AI\'s');
	pg_assert_same('ai', ws_assistant_agent_of('mcp:7', null), 'a server that is gone');
	pg_assert_same('ai', ws_assistant_agent_of('ai', null));
	pg_assert_same('claude', ws_assistant_agent_of('claude', null));
	pg_assert_same('ai', ws_assistant_agent('ai'));
	pg_assert_same('claude', ws_assistant_agent('claude'));
}

function test_delegation_alias_of_an_agent_goes_by_its_own_queue()
{
	pg_assert_same('mcp', ws_assistant_agent_of('mcp:7', array('id' => 7, 'agent_tool' => 'ask')), 'a server that is an agent itself answers on its own');
	pg_assert_same('ai', ws_assistant_agent_of('mcp:7', array('id' => 7, 'agent_tool' => '  ')), 'no tool chosen');
	pg_assert_same('claude', ws_assistant_agent_of('claude', array('id' => 7, 'agent_tool' => 'ask')), 'only an alias\'s key');
}

function test_delegation_run_key_of_a_request()
{
	pg_assert_same('mcp:3', ws_task_run_key(array('agent' => 'ai', 'mcp_server_id' => '3')));
	pg_assert_same('ai', ws_task_run_key(array('agent' => 'ai', 'mcp_server_id' => '0')));
	pg_assert_same('ai', ws_task_run_key(array('agent' => 'ai')), 'before the MCP step there is no column');
	pg_assert_same('mcp:3', ws_task_run_key(array('agent' => 'mcp', 'mcp_server_id' => '3')), 'a run of the server\'s own agent');
	pg_assert_same('claude', ws_task_run_key(array('agent' => 'claude', 'mcp_server_id' => '3')), 'only Pinegrap AI or the server\'s agent answers with a server');
	pg_assert_same('claude', ws_task_run_key(array()), 'before the agent column every request is Claude\'s');
}
