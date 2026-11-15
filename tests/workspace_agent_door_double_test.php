<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warning of an agent knocked on a routine while another
 * session reads its queue with its key (ws_agent_door_double(),
 * includes/workspace/agents.php): when it holds, and that the Agents card's
 * row, the agent's drawer and the push address's help draw it
 * (includes/workspace/agents_settings.php).
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
require_once(__DIR__ . '/helpers/language_map.php');

// The source of one function of agents_settings.php, up to the next one.
function workspace_agent_door_double_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agents_settings.php');
	$from = strpos($source, 'function ' . $name . '(');
	$to = ($from === false) ? false : strpos($source, "\nfunction ", $from + 10);

	return ($from === false) ? '' : (($to === false) ? substr($source, $from) : substr($source, $from, $to - $from));
}

function test_workspace_agent_door_double()
{
	$knock = 1700000000;
	$agent = array(
		'kind' => 'app', 'status' => 'active', 'door_kind' => 'push',
		'door_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_01abc/fire', 'door_last_knock' => $knock,
	);

	pg_assert_true(ws_agent_door_double($agent + array('seen_at' => $knock + 601)), 'push on a routine, a call long after the knock: another session polls');
	pg_assert_true(ws_agent_door_double(array('door_last_knock' => 0, 'seen_at' => $knock) + $agent), 'seen with no knock yet');
	pg_assert_false(ws_agent_door_double($agent + array('seen_at' => $knock + 30)), 'a call right after the knock is the run it started');
	pg_assert_false(ws_agent_door_double($agent + array('seen_at' => $knock + 600)), 'ten minutes is still the knock\'s run');
	pg_assert_false(ws_agent_door_double(array('seen_at' => 0) + $agent), 'never seen');
	pg_assert_false(ws_agent_door_double(array('door_url' => 'https://hooks.example.com/pinegrap', 'seen_at' => $knock + 3600) + $agent), 'a webhook address');
	pg_assert_false(ws_agent_door_double(array('door_kind' => 'poll', 'door_url' => '', 'seen_at' => $knock + 3600) + $agent), 'a poll door');
	pg_assert_false(ws_agent_door_double(array('status' => 'paused', 'seen_at' => $knock + 3600) + $agent), 'a paused agent');
	pg_assert_false(ws_agent_door_double(array('kind' => 'model', 'seen_at' => $knock + 3600) + $agent), 'a model agent is run by the site');
}

function test_workspace_agent_door_double_drawn()
{
	$card = workspace_agent_door_double_test_function('ws_agents_settings_card');
	$drawer = workspace_agent_door_double_test_function('ws_settings_agent_drawer');
	$form = workspace_agent_door_double_test_function('ws_agents_settings_form');

	pg_assert_contains('$error .= ws_agent_door_double($agent) ? \'<div class="small fg-danger" data-ws-agent-door-double>\' . h(ws_agent_door_double_words())', $card, 'the row on the card, where the door error stands');
	pg_assert_contains('$error .= ws_agent_door_double($agent) ? \'<div class="alert theme-danger small py-3" data-ws-agent-door-double>\' . h(ws_agent_door_double_words())', $drawer, 'the drawer, in the door error\'s style');
	pg_assert_contains('data-ws-agent-routine-help', $form, 'the push address\'s help for a routine');
	pg_assert_contains('[data-ws-agent-routine-help]', ws_agents_settings_script(), 'shown while a routine\'s address is typed');

	$turkish = pg_test_language_map('tr');

	pg_assert_true(isset($turkish[ws_agent_door_double_words()]), 'the warning has its Turkish');
	pg_assert_true(isset($turkish['A routine\'s address: every knock starts a new run of that routine and never wakes a session that is already open. An agent that keeps one session open uses the poll door.']), 'the help has its Turkish');
}
