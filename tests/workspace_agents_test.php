<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent registry's checks (includes/workspace/agents.php)
 * that need no database: an agent's handle and its door.
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

function test_workspace_agent_handle_problem()
{
	pg_assert_same('', ws_agent_handle_problem('koordinator-a', array('claude', 'ai'), array('erdal')));
	pg_assert_true(ws_agent_handle_problem('Koordinator', array(), array()) !== '', 'upper case is not the shape');
	pg_assert_true(ws_agent_handle_problem('1bot', array(), array()) !== '', 'starts with a letter');
	pg_assert_true(ws_agent_handle_problem('ko ordinator', array(), array()) !== '', 'no spaces');
	pg_assert_true(ws_agent_handle_problem('ci', array('CI'), array()) !== '', 'held by another agent, whatever its case');
	pg_assert_true(ws_agent_handle_problem('erdal', array(), array('Erdal')) !== '', 'a person\'s user name');
	pg_assert_true(ws_agent_handle_problem('all', array(), array()) !== '', 'a word the Workspace keeps');
}

function test_workspace_agent_door_problem()
{
	pg_assert_same('', ws_agent_door_problem('poll', ''));
	pg_assert_true(ws_agent_door_problem('poll', 'https://example.com/hook') !== '', 'a poll door has no address');
	pg_assert_same('', ws_agent_door_problem('push', 'https://api.anthropic.com/v1/claude_code/routines/trig_01abc/fire'), 'a routine fire address');
	pg_assert_same('', ws_agent_door_problem('push', ' https://hooks.example.com/pinegrap '), 'any https receiver');
	pg_assert_true(ws_agent_door_problem('push', 'http://hooks.example.com/pinegrap') !== '', 'https only');
	pg_assert_true(ws_agent_door_problem('push', '') !== '', 'a push door needs an address');
	pg_assert_true(ws_agent_door_problem('push', 'https://api.anthropic.com/integrations/v1/code/webhook-triggers/abc/fire') !== '', 'a session webhook nobody but Anthropic can sign');
	pg_assert_true(ws_agent_door_problem('ring', '') !== '', 'push or poll');
}
