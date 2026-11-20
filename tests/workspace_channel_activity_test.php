<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the read behind the Activity tab of Channel info
 * (channel_activity.php): the summary line, the agents' order, a request's
 * age, the people's waiting items, the plan windows joined by the agents
 * that reported them, the project, the panel action and the API route.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

function test_workspace_channel_activity_summary()
{
	$summary = ws_channel_activity_summary(array('working', 'late', 'working', 'waiting', 'working', 'idle', 'paused'));

	pg_assert_same(3, $summary['working']);
	pg_assert_same(1, $summary['waiting']);
	pg_assert_same(1, $summary['late']);
	pg_assert_same(1, $summary['idle']);
	pg_assert_same(1, $summary['paused']);
	pg_assert_same(
		lang(array('string' => '{var:1} working', 'vars' => 3)) . ' · ' . lang(array('string' => '{var:1} waiting', 'vars' => 1)) . ' · '
			. lang(array('string' => '{var:1} not seen', 'vars' => 1)) . ' · ' . lang(array('string' => '{var:1} ready', 'vars' => 1)) . ' · '
			. lang(array('string' => '{var:1} paused', 'vars' => 1)),
		$summary['words'],
		'the states in the order of the rows'
	);

	$quiet = ws_channel_activity_summary(array('idle'));

	pg_assert_same(lang(array('string' => '{var:1} ready', 'vars' => 1)), $quiet['words'], 'a state with none is left out');
	pg_assert_same('', ws_channel_activity_summary(array())['words'], 'no agent, no words');
}

function test_workspace_channel_activity_order()
{
	$rows = array(
		array('id' => 1, 'name' => 'Zed', 'state' => 'paused'),
		array('id' => 2, 'name' => 'Bea', 'state' => 'idle'),
		array('id' => 3, 'name' => 'Cem', 'state' => 'late'),
		array('id' => 4, 'name' => 'Ada', 'state' => 'waiting'),
		array('id' => 5, 'name' => 'Dan', 'state' => 'working'),
		array('id' => 6, 'name' => 'Ali', 'state' => 'working'),
	);

	$ids = array_map(function ($row) { return $row['id']; }, ws_channel_activity_order($rows));

	pg_assert_same(array(6, 5, 4, 3, 2, 1), $ids, 'working, waiting, not seen, ready, paused; by name within a state');
}

function test_workspace_channel_activity_age()
{
	$now = 1700000000;

	pg_assert_same(lang('under a minute'), ws_channel_activity_age($now - 30, $now));
	pg_assert_same(lang(array('string' => '{var:1} min', 'vars' => 12)), ws_channel_activity_age($now - 12 * 60 - 5, $now));
	pg_assert_same(lang(array('string' => '{var:1} h {var:2} min', 'vars' => array(2, 5))), ws_channel_activity_age($now - 2 * 3600 - 5 * 60, $now));
	pg_assert_same(lang(array('string' => '{var:1} d', 'vars' => 3)), ws_channel_activity_age($now - 3 * 86400 - 10, $now));
	pg_assert_same(lang('under a minute'), ws_channel_activity_age($now + 100, $now), 'a clock ahead is no age');
	pg_assert_same('', ws_channel_activity_age(0, $now), 'no moment, no age');
}

function test_workspace_channel_activity_waiting_people()
{
	$now = 1700000000;
	$items = array();

	for ($i = 1; $i <= 7; $i++) {
		$items[] = array('kind' => ($i % 2) ? 'poll' : 'approval', 'message_id' => 100 + $i, 'text' => 'Item ' . $i, 'created_at' => $now - $i * 60);
	}

	$out = ws_channel_activity_waiting_people($items, $now);

	pg_assert_same(5, count($out['items']), 'at most five');
	pg_assert_same(2, $out['more'], 'the rest counted');
	pg_assert_same(107, $out['items'][0]['message_id'], 'oldest first');
	pg_assert_same(103, $out['items'][4]['message_id']);
	pg_assert_same(lang(array('string' => '{var:1} min', 'vars' => 7)), $out['items'][0]['age']);
	pg_assert_same('poll', $out['items'][0]['kind']);
	pg_assert_same(array('kind', 'message_id', 'text', 'age', 'created_at'), array_keys($out['items'][0]));

	$none = ws_channel_activity_waiting_people(array(), $now);

	pg_assert_same(array(), $none['items']);
	pg_assert_same(0, $none['more']);
}

function test_workspace_channel_activity_windows()
{
	$now = 1700000000;
	$weekly = array('name' => 'weekly', 'percent' => 61, 'resets_at' => $now + 86400);
	$agents = array(
		array('id' => 1, 'handle' => 'ada', 'name' => 'Ada', 'kind' => 'app', 'report_at' => $now - 60, 'report_usage' => json_encode(array($weekly, array('name' => 'five_hour', 'percent' => 20, 'resets_at' => $now + 3600)))),
		array('id' => 2, 'handle' => 'bea', 'name' => 'Bea', 'kind' => 'app', 'report_at' => $now - 120, 'report_usage' => json_encode(array($weekly))),
		array('id' => 3, 'handle' => 'cem', 'name' => 'Cem', 'kind' => 'model', 'report_at' => $now - 60, 'report_usage' => json_encode(array($weekly))),
		array('id' => 4, 'handle' => 'dan', 'name' => 'Dan', 'kind' => 'app', 'report_at' => 0, 'report_usage' => ''),
		array('id' => 5, 'handle' => 'eve', 'name' => 'Eve', 'kind' => 'app', 'report_at' => $now - 60, 'report_usage' => json_encode(array(array('name' => 'weekly', 'percent' => 95, 'resets_at' => $now + 86400)))),
	);

	$windows = ws_channel_activity_windows($agents);

	pg_assert_same(3, count($windows), 'one window per name, percent and reset time');
	pg_assert_same('five_hour', $windows[0]['name'], 'in the order first met, a report\'s windows in the list\'s order');
	pg_assert_same(array('ada'), array_map(function ($agent) { return $agent['handle']; }, $windows[0]['agents']));
	pg_assert_same('weekly', $windows[1]['name']);
	pg_assert_same(array('ada', 'bea'), array_map(function ($agent) { return $agent['handle']; }, $windows[1]['agents']), 'joined by the agents that reported it; a model assistant reports none');
	pg_assert_same(95, $windows[2]['percent']);
	pg_assert_same(array('eve'), array_map(function ($agent) { return $agent['handle']; }, $windows[2]['agents']));
	pg_assert_true($windows[2]['high'], 'the 90% mark');
	pg_assert_false($windows[1]['high']);
	pg_assert_same(lang(array('string' => '{var:1} {var:2}%, resets {var:3}', 'vars' => array(ws_agent_report_window_labels()['weekly'], 61, date('d.m H:i', $now + 86400)))), $windows[1]['words']);
	pg_assert_same(array(), ws_channel_activity_windows(array()));
}

function test_workspace_channel_activity_project()
{
	$now = 1700000000;

	pg_assert_same(array('name' => 'kodpen/pinegrapcms', 'from' => 'repository'), ws_channel_activity_project(array(), 'kodpen/pinegrapcms'), 'the linked repository when no agent reported one');
	pg_assert_same(null, ws_channel_activity_project(array(), ''), 'neither: none');
	pg_assert_same(
		array('name' => 'Newer', 'from' => 'report'),
		ws_channel_activity_project(array(array('project' => 'Older', 'report_at' => $now - 600), array('project' => 'Newer', 'report_at' => $now - 60), array('project' => null, 'report_at' => $now)), 'kodpen/pinegrapcms'),
		'the newest reported project goes first'
	);
}

function test_workspace_channel_activity_action()
{
	pg_assert_true(in_array('ws_channel_activity', ws_channel_agents_actions(), true), 'a panel action of the channel');

	$entry = pg_panel_action('ws_channel_activity');

	pg_assert_true(is_array($entry), 'routed by the panel');
	pg_assert_same('pg_panel_ws', is_array($entry) ? $entry['handler'] : '', 'handled by the workspace');
}

function test_workspace_channel_activity_route()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.channels.activity.get') {
			$route = $candidate;
		}
	}

	pg_assert_true(is_array($route), 'the route is there');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/workspace/channels/{id}/activity', $route['path']);
	pg_assert_same('workspace:read', $route['scope']);
	pg_assert_same('WorkspaceChannelActivity', $route['returns']);
	pg_assert_false(isset($route['mcp']) && ($route['mcp'] === false), 'an MCP tool too');

	$schema = ws_api_channel_activity_schema();

	foreach (array('channel_id', 'summary', 'project', 'agents', 'requests', 'waiting', 'decisions', 'usage', 'usage_tip', 'repository') as $field) {
		pg_assert_true(array_key_exists($field, $schema), 'declared: ' . $field);
	}

	foreach (array('context', 'project', 'branch') as $field) {
		pg_assert_true(array_key_exists($field, $schema['agents'][0]), 'an agent row declares ' . $field);
	}
}

function test_workspace_channel_activity_null_parts()
{
	// Until the report carries them and the repository is counted, the
	// parts are there and null.
	$row = ws_channel_activity_agent_extra(array('id' => 1, 'kind' => 'app', 'report_usage' => '[]'));

	pg_assert_same(array('context', 'project', 'branch'), array_keys($row));

	if (!function_exists('ws_agent_report_context')) {
		pg_assert_same(null, $row['context']);
	}

	if (!function_exists('ws_agent_report_project')) {
		pg_assert_same(null, $row['project']);
	}
}
