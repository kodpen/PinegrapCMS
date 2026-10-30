<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Add › Scheduled flow's mini window (scheduled_convert.php): the
 * two-box flow built from what the member filled in, the fields the window
 * may send, and, read from the source, the rights its writes keep (the
 * flow store's save and publish). The writes themselves need a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_draft.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled_convert.php');

function ws_scheduled_flow_window_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// What the window filled replaces the defaults: a weekly start on two days
// until a date, and the message, posted into the channel; the flow then has
// no problem left.
function test_workspace_scheduled_flow_window_graph_filled()
{
	$graph = ws_scheduled_flow_graph(14, '2026-10-31', array(
		'start' => array('date' => '2026-11-03', 'time' => '08:30', 'repeat' => 'weekly', 'every' => '2', 'days' => array('5', '1', '9'), 'until' => '2026-12-31', 'bogus' => 'x'),
		'body'  => 'Weekly report, please.',
	));
	$start = $graph['nodes'][0]['config'];

	pg_assert_same('2026-11-03', $start['date']);
	pg_assert_same('08:30', $start['time']);
	pg_assert_same('weekly', $start['repeat']);
	pg_assert_same(2, $start['every']);
	pg_assert_same(array(1, 5), $start['days'], 'days sorted, a day past Sunday dropped');
	pg_assert_same('2026-12-31', $start['until']);
	pg_assert_false(isset($start['bogus']), 'only the start settings are kept');
	pg_assert_same('Weekly report, please.', $graph['nodes'][1]['config']['body']);
	pg_assert_same(14, (int) $graph['nodes'][1]['config']['channel_id']);
	pg_assert_same(array(), array_values(array_filter(ws_flow_graph_check($graph), function ($problem) {
		return empty($problem['warning']);
	})), 'a filled window leaves no problem');

	$empty = ws_scheduled_flow_graph(14, '2026-10-31', array('start' => array('time' => '')));

	pg_assert_same('', $empty['nodes'][0]['config']['time'], 'an emptied time stays empty');
	pg_assert_true(count(ws_flow_graph_check($empty)) > 0, 'and is a problem the publish refuses');
}

// Only the window's own fields come through: scalars, the days as a list,
// a name only when it says something.
function test_workspace_scheduled_flow_window_fill()
{
	$fill = ws_scheduled_flow_fill(array(
		'start'   => array('date' => '2026-11-03', 'time' => array('09:00'), 'repeat' => 'daily', 'days' => array('1', array('x'), '3'), 'channel_id' => 9),
		'body'    => 'Hello',
		'name'    => '  ',
		'flow_id' => 7,
	));

	pg_assert_same(array('date' => '2026-11-03', 'repeat' => 'daily', 'days' => array('1', '3')), $fill['start']);
	pg_assert_same('Hello', $fill['body']);
	pg_assert_false(isset($fill['name']), 'a blank name keeps the default');
	pg_assert_false(isset($fill['flow_id']), 'the flow is not the fill\'s');
	pg_assert_same('Morning note', ws_scheduled_flow_fill(array('name' => 'Morning note'))['name']);
	pg_assert_same(array('start' => array(), 'body' => ''), ws_scheduled_flow_fill(array('start' => 'x', 'body' => array())));
}

// The writes go through the flow store with its rights: posting in the
// channel, building flows (ws_flow_save()), publishing as the owner
// (ws_flow_publish()); a second write changes only the person's own draft.
function test_workspace_scheduled_flow_window_rights()
{
	$new = ws_scheduled_flow_window_test_source('ws_scheduled_flow_new');

	pg_assert_contains('ws_can_post_channel($viewer, $channel)', $new);
	pg_assert_contains("ws_flow_for(\$viewer, (int) \$data['flow_id'], 'edit')", $new);
	pg_assert_contains("((int) \$flow['owner_id'] !== (int) \$viewer['id'])", $new, 'only the person\'s own draft');
	pg_assert_contains("((string) \$flow['status'] !== 'draft')", $new, 'never a live flow');
	pg_assert_contains('ws_flow_save($viewer, $fields, $flow)', $new);
	pg_assert_contains('ws_flow_publish($viewer, ws_flow_for($viewer, $saved[\'flow_id\'], \'edit\')', $new);
	pg_assert_contains("ws_scheduled_flow_home(\$viewer, \$channel['id'])", $new);

	$sketch = ws_scheduled_flow_window_test_source('ws_scheduled_flow_sketch');

	pg_assert_contains('ws_can_post_channel($viewer, $channel)', $sketch);
	pg_assert_contains('ws_can_schedule($viewer)', $sketch);
	pg_assert_contains('ws_flow_card_graph($graph)', $sketch);
	pg_assert_false(strpos($sketch, 'ws_flow_save') !== false, 'the sketch writes nothing');

	$route = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($route, "case 'ws_flow_scheduled_new':");
	$route = substr($route, $start, strpos($route, "case 'ws_flow_scheduled_copy':", $start) - $start);

	pg_assert_contains('ws_scheduled_flow_sketch($viewer, $channel_id, ws_scheduled_flow_fill($request))', $route);
	pg_assert_contains('ws_scheduled_flow_fill($request)', $route);
	pg_assert_contains("'publish'   => !empty(\$request['publish'])", $route);

	// The store's own checks stay the gate.
	pg_assert_contains('ws_can_schedule($viewer)', ws_scheduled_flow_window_test_source('ws_flow_save_check'));
	pg_assert_contains('ws_flow_share_manager($viewer, $flow)', ws_scheduled_flow_window_test_source('ws_flow_publish_check'));
}

// The sketch the window draws: two boxes side by side, the thread between,
// nothing to try in a flow not written yet.
function test_workspace_scheduled_flow_window_sketch_shape()
{
	$card = ws_flow_card_graph(ws_scheduled_flow_graph(3, '2026-10-31'));

	pg_assert_same(array('start', 'post'), array_column($card['nodes'], 'id'));
	pg_assert_same(array(array('from' => 'start', 'to' => 'post', 'on' => 'next')), $card['edges']);
	pg_assert_false($card['nodes'][1]['outbound'], 'a post in the channel is not outbound');
	pg_assert_true($card['width'] > $card['height']);
}

// The window in workspace.js: the Add entry opens it, the drawing is D8b's
// with the pick handed to the form, Publish and Open on the canvas send the
// draft the window made before.
function test_workspace_scheduled_flow_window_js_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("label: t('sa_flow_new'), action: function () {\n                        self.scheduledFlowWindow();", $js);
	pg_assert_contains('self.flowWindowDraw(drawing, graph, function (picked) {', $js);
	pg_assert_contains('flow_id: saved.flow_id,', $js);
	pg_assert_contains('draft_rev: saved.draft_rev,', $js);
	pg_assert_contains('publish: publish ? 1 : 0', $js);
	pg_assert_false(strpos($js, "label: t('sa_new')") !== false, 'no Scheduled action entry');
}

// Runs a script under Node and answers what it printed as JSON; null when
// Node is not installed.
function ws_scheduled_flow_window_test_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgwssf');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// Which start settings each repeat shows, and only those are sent.
function test_workspace_scheduled_flow_window_helpers_node()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$script = "'use strict';\n";

	foreach (array('scheduledFlowShows', 'scheduledFlowStart') as $name) {
		$start = strpos($source, "\n    function " . $name . '(');
		pg_assert_true($start !== false, $name . ' is found');
		$end = strpos($source, "\n    }\n", (int) $start);
		$script .= substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
	}

	$script .= "console.log(JSON.stringify([scheduledFlowShows('none'), scheduledFlowShows('workdays'), scheduledFlowShows('weekly'), scheduledFlowShows('daily'),"
		. " scheduledFlowStart({ date: '2026-11-03', time: '09:00', repeat: 'none', every: '3', days: ['1'], until: '2026-12-01' }),"
		. " scheduledFlowStart({ date: '2026-11-03', time: '09:00', repeat: 'weekly', every: 'x', days: ['5', '1', '8'], until: '' }),"
		. " scheduledFlowStart({ date: '2026-11-03', time: '09:00', repeat: 'daily', every: '2', days: ['1'], until: '2026-12-01' })]));\n";
	$answer = ws_scheduled_flow_window_test_node($script);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		array('every' => false, 'days' => false, 'until' => false),
		array('every' => false, 'days' => false, 'until' => true),
		array('every' => true, 'days' => true, 'until' => true),
		array('every' => true, 'days' => false, 'until' => true),
		array('date' => '2026-11-03', 'time' => '09:00', 'repeat' => 'none'),
		array('date' => '2026-11-03', 'time' => '09:00', 'repeat' => 'weekly', 'every' => 1, 'days' => array(1, 5)),
		array('date' => '2026-11-03', 'time' => '09:00', 'repeat' => 'daily', 'every' => 2, 'until' => '2026-12-01'),
	), $answer);
}
