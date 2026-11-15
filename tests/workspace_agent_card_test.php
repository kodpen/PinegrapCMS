<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a channel shows about one of its agents (agent_card.php):
 * the state its dot draws, the usage mark, the request counts, the light
 * brief on a member row, the panel action's name and its route, and the same
 * facts in the API's channel-agent row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_agents.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

function test_workspace_agent_card_state_order()
{
	pg_assert_same('paused', ws_agent_card_state(false, 'on', 1, 1), 'paused goes first');
	pg_assert_same('working', ws_agent_card_state(true, 'late', 1, 2), 'a request in hand reads working');
	pg_assert_same('late', ws_agent_card_state(true, 'late', 0, 2), 'not seen goes before waiting');
	pg_assert_same('waiting', ws_agent_card_state(true, 'on', 0, 2));
	pg_assert_same('idle', ws_agent_card_state(true, 'ready', 0, 0));
	pg_assert_same(lang('Not seen'), ws_agent_card_state_words('late'));
	pg_assert_same(lang('Ready'), ws_agent_card_state_words('idle'));
}

function test_workspace_agent_card_usage_high()
{
	$now = 1700000000;
	$windows = array(array('name' => 'five_hour', 'percent' => 34, 'resets_at' => $now + 3600), array('name' => 'weekly', 'percent' => 90, 'resets_at' => $now + 86400));

	pg_assert_true(ws_agent_card_usage_high($windows, $now - 60, $now), '90% is marked');
	pg_assert_false(ws_agent_card_usage_high(array($windows[0]), $now - 60, $now), '34% is not');
	pg_assert_false(ws_agent_card_usage_high($windows, $now - WS_AGENT_REPORT_STALE_AFTER, $now), 'a stale report gives no mark');
	pg_assert_false(ws_agent_card_usage_high($windows, 0, $now), 'no report, no mark');

	$drawn = ws_agent_card_windows($windows);

	pg_assert_same(2, count($drawn));
	pg_assert_false($drawn[0]['high']);
	pg_assert_true($drawn[1]['high']);
	pg_assert_same(90, $drawn[1]['percent']);
}

function test_workspace_agent_request_counts_of()
{
	$day = 1700000000;
	$rows = array(
		array('agent' => 'agent:7', 'status' => 'queued', 'answered_at' => 0),
		array('agent' => 'agent:7', 'status' => 'queued', 'answered_at' => 0),
		array('agent' => 'agent:7', 'status' => 'running', 'answered_at' => 0),
		array('agent' => 'agent:7', 'status' => 'sent', 'answered_at' => 0),
		array('agent' => 'agent:7', 'status' => 'answered', 'answered_at' => $day + 5),
		array('agent' => 'agent:7', 'status' => 'answered', 'answered_at' => $day - 5),
		array('agent' => 'model:3', 'status' => 'failed', 'answered_at' => $day + 9),
	);
	$counts = ws_agent_request_counts_of($rows, $day);

	pg_assert_same(array('queued' => 2, 'working' => 2, 'answered' => 1, 'failed' => 0), $counts['agent:7'], 'yesterday\'s answer is not counted');
	pg_assert_same(array('queued' => 0, 'working' => 0, 'answered' => 0, 'failed' => 1), $counts['model:3']);
	pg_assert_same(array(), ws_agent_request_counts(array(array('kind' => 'claude', 'id' => 1)), $day), 'no value, no query');
}

function test_workspace_agent_card_brief_on_member_row()
{
	$now = 1700000000;
	$row = array(
		'agent'       => array('id' => 7, 'kind' => 'app', 'handle' => 'mimar', 'name' => 'Mimar', 'role' => 'architect', 'status' => 'active', 'door_kind' => 'poll', 'seen_at' => $now - 30, 'door_last_knock' => 0, 'door_error' => '',
			'report_at' => $now - 60, 'report_model' => 'model-x', 'report_usage' => json_encode(array(array('name' => 'weekly', 'percent' => 92, 'resets_at' => $now + 3600)))),
		'wake_rule'   => 'prefix',
		'wake_prefix' => '[ci]',
		'tier'        => 2,
	);

	$plain = ws_channel_member_agent($row, $now);

	pg_assert_false(isset($plain['state']), 'without counts the brief is left out');

	$member = ws_channel_member_agent($row, $now, array('agent:7' => array('queued' => 3, 'working' => 0, 'answered' => 0, 'failed' => 0)));

	pg_assert_same('architect', $member['role_key']);
	pg_assert_same('waiting', $member['state']);
	pg_assert_same(3, $member['waiting']);
	pg_assert_true($member['usage_high']);
	pg_assert_same(lang(array('string' => 'wakes on {var:1}', 'vars' => '[ci]')), $member['wake']);

	$api = ws_api_channel_agent_present(25, $row + array('loop_guard' => true, 'added_by' => 1, 'added_at' => $now), array(), $now);

	pg_assert_same('idle', $api['state']);
	pg_assert_same('model-x', $api['model']);
	pg_assert_same(92, $api['usage'][0]['percent']);
	pg_assert_true($api['usage_high']);

	foreach (array_keys($api) as $field) {
		pg_assert_true(array_key_exists($field, ws_api_channel_agent_schema()), 'declared: ' . $field);
	}
}

function test_workspace_agent_card_action()
{
	pg_assert_true(in_array('ws_agent_card', ws_channel_agents_actions(), true), 'the card is a panel action of the channel');

	// An action api.php's table does not list is answered "Invalid action."
	// before the module sees it: the card then never got its facts.
	foreach (ws_channel_agents_actions() as $action) {
		$entry = pg_panel_action($action);

		pg_assert_true(is_array($entry), 'routed by the panel: ' . $action);
		pg_assert_same('pg_panel_ws', is_array($entry) ? $entry['handler'] : '', 'handled by the workspace: ' . $action);
	}
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent.
function workspace_agent_card_test_js($name)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', $js, $match) ? $match[0] : '';
}

// Runs a script with Node; null without Node.
function workspace_agent_card_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcard');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_agent_card_js_place_and_lookup()
{
	$place = workspace_agent_card_test_js('agentCardPlace');
	$lookup = workspace_agent_card_test_js('agentOfApp');

	pg_assert_true($place !== '', 'agentCardPlace() is in workspace.js');
	pg_assert_true($lookup !== '', 'agentOfApp() is in workspace.js');

	$result = workspace_agent_card_test_node($place . $lookup . '
var view = { width: 1440, height: 900 }, size = { width: 320, height: 400 };
console.log(JSON.stringify({
	below: agentCardPlace({ left: 100, top: 100, bottom: 120 }, size, view),
	above: agentCardPlace({ left: 100, top: 700, bottom: 720 }, size, view),
	right: agentCardPlace({ left: 1400, top: 100, bottom: 120 }, size, view),
	phone: agentCardPlace({ left: 20, top: 300, bottom: 320 }, { width: 374, height: 400 }, { width: 390, height: 844 }),
	found: agentOfApp({ agents: [{ id: 3, app_id: 9 }, { id: 4, app_id: 12 }] }, 12),
	none: agentOfApp({ agents: [] }, 12)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('left' => 100, 'top' => 126), $result['below'], 'under the anchor');
	pg_assert_same(294, $result['above']['top'], 'over the anchor when the room below is short');
	pg_assert_same(1112, $result['right']['left'], 'kept inside the right edge');
	pg_assert_same(8, $result['phone']['left'], 'a phone: 8px from the edge');
	pg_assert_same(4, $result['found']['id']);
	pg_assert_same(null, $result['none']);
}
