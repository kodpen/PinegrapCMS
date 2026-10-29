<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for every scheduled action becoming a flow
 * (includes/workspace/scheduled_convert.php): the rule that keeps starting
 * the converted action, the drawing the flow takes without refusing it,
 * and, read from the source, how a row is taken, written and given back.
 * The conversion itself needs a database.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled_convert.php');

// A function's source without its comments.
function ws_scheduled_convert_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The "when" stays on the action; its conditions are the flow's boxes.
function test_workspace_scheduled_convert_rules()
{
	$at = array('type' => 'at', 'date' => '2026-11-02', 'time' => '09:00', 'repeat' => 'weekly');

	pg_assert_same(array($at), ws_scheduled_convert_rules(array(array('type' => 'workday'), $at, array('type' => 'hours', 'from' => '09:00'))));
	pg_assert_same(array(array('type' => 'event', 'event' => 'order.created')), ws_scheduled_convert_rules(array(array('type' => 'event', 'event' => 'order.created'))));
	pg_assert_same(array(array('type' => 'join', 'channel_id' => 0)), ws_scheduled_convert_rules(array(array('type' => 'join', 'channel_id' => 0))));
	pg_assert_same(array(array('type' => 'trigger')), ws_scheduled_convert_rules(array(array('type' => 'trigger'))));
	pg_assert_same(array(array('type' => 'trigger')), ws_scheduled_convert_rules(array(array('type' => 'workday'))), 'none: by hand and by a chain');
	pg_assert_same(array(array('type' => 'trigger')), ws_scheduled_convert_rules(null));
}

// What the conversion draws is a flow the store takes: a time with
// conditions and follow-ups, by hand, an event, a join, the action
// starting itself again.
function test_workspace_scheduled_convert_drawings_have_no_problems()
{
	$rows = array(
		array('name' => 'Monday', 'rules' => array(
				array('type' => 'at', 'date' => '2026-11-02', 'time' => '09:00', 'repeat' => 'weekly', 'every' => 1, 'days' => array(1)),
				array('type' => 'workday'),
			), 'action' => array('type' => 'post', 'channel_id' => 4, 'body' => 'Hello'),
			'follow' => array(
				array('on' => 'failed', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Why: {{result}}')),
				array('on' => 'skipped', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Skipped')),
			)),
		array('name' => 'By hand', 'rules' => array(array('type' => 'trigger')),
			'action' => array('type' => 'task', 'title' => 'Call', 'description' => '', 'priority' => 'high', 'assignees' => array(3), 'channel_id' => 0, 'due_in' => 2), 'follow' => array()),
		array('name' => 'Orders', 'rules' => array(array('type' => 'event', 'event' => 'order.created', 'status' => '', 'page_id' => 0, 'channel_id' => 0)),
			'action' => array('type' => 'trigger', 'target_id' => 12, 'delay' => 5), 'follow' => array()),
		array('name' => 'Welcome', 'rules' => array(array('type' => 'join', 'channel_id' => 9)),
			'action' => array('type' => 'post', 'channel_id' => -1, 'body' => 'Welcome'),
			'follow' => array(array('on' => 'failed', 'do' => array('type' => 'trigger', 'target_id' => 30, 'delay' => 10)))),
	);

	foreach ($rows as $data) {
		$made = ws_flow_graph_from_scheduled($data, array(), 30);

		// ws_flow_problems() without the record kinds of a manual start box,
		// which need the database and are '' here.
		$problems = array_values(array_filter(ws_flow_graph_check($made['graph']), function ($problem) {
			return empty($problem['warning']);
		}));

		pg_assert_same(array(), $problems, $data['name']);
	}
}

// Taken with the run lock, owned by the creator with no home channel, the
// anchor written last on a row still without a flow, undone otherwise; the
// driver converts before it runs anything.
function test_workspace_scheduled_convert_wiring()
{
	$convert = ws_scheduled_convert_test_source('ws_scheduled_convert');

	pg_assert_contains("AND running_at < '\" . (\$now - WS_SCHEDULED_LOCK)", $convert);
	pg_assert_contains("VALUES ('\" . \$owner . \"', 0, '\" . e(\$name) . \"', 'live'", $convert);
	pg_assert_contains("anchor = 'converted'", $convert);
	pg_assert_contains("WHERE id = '\" . (int) \$row['id'] . \"' AND flow_id = 0\");", $convert);
	pg_assert_contains("DELETE FROM ws_flows WHERE id = '\" . \$flow_id . \"'", $convert);
	pg_assert_contains('$made = ws_scheduled_convert_drawing($row);', $convert);
	pg_assert_contains("!empty(\$made['problems'])", $convert);

	$drawing = ws_scheduled_convert_test_source('ws_scheduled_convert_drawing');

	pg_assert_contains('ws_change_viewer_for_id($owner)', $drawing, 'drawn as its creator sees it');
	pg_assert_contains('ws_flow_graph_from_scheduled(ws_scheduled_form_data($viewer, $row), ws_scheduled_money_metrics(), (int) $row[\'id\'])', $drawing);
	pg_assert_contains('ws_flow_problems($graph)', $drawing);

	$due = ws_scheduled_convert_test_source('ws_scheduled_convert_due');

	pg_assert_contains("kind = 'action' AND flow_id = 0 AND anchor = ''", $due);
	pg_assert_false(strpos($due, "'cancelled'") !== false, 'a cancelled action is not converted');
	pg_assert_contains('ws_scheduled_convert_due();', ws_scheduled_convert_test_source('ws_scheduled_run'));
}

// Add › Scheduled flow: tomorrow at 09:00, once, on the site's calendar,
// then a post box into the channel, its text still to write; a draft that
// holds no problem but the missing text.
function test_workspace_scheduled_flow_graph()
{
	$graph = ws_scheduled_flow_graph(14, '2026-10-31');

	pg_assert_same(array('start', 'post'), array_column($graph['nodes'], 'id'));
	pg_assert_same('schedule', $graph['nodes'][0]['kind']);
	pg_assert_same('2026-11-01', $graph['nodes'][0]['config']['date'], 'the next day, across a month');
	pg_assert_same('09:00', $graph['nodes'][0]['config']['time']);
	pg_assert_same('none', $graph['nodes'][0]['config']['repeat']);
	pg_assert_same('post', $graph['nodes'][1]['kind']);
	pg_assert_same(14, (int) $graph['nodes'][1]['config']['channel_id']);
	pg_assert_same(array(array('from' => 'start', 'to' => 'post', 'on' => 'next')), array_map(function ($edge) {
		return array('from' => $edge['from'], 'to' => $edge['to'], 'on' => $edge['on']);
	}, $graph['edges']));
	pg_assert_same(0, (int) ws_scheduled_flow_graph(-3, 'bad')['nodes'][1]['config']['channel_id'], 'no channel below 0');

	$fields = array_values(array_unique(array_map(function ($problem) {
		return (string) ($problem['code'] ?? '');
	}, array_filter(ws_flow_graph_check($graph), function ($problem) {
		return empty($problem['warning']);
	}))));

	pg_assert_true(count($fields) <= 1, 'at most the missing text: ' . implode(', ', $fields));
}

// The ways in open the canvas: Duplicate copies a converted action's flow
// and draws any other as conversion does, its own retry kept; neither
// opens the form, and a note gets no new one.
function test_workspace_scheduled_flow_copy_wiring()
{
	$copy = ws_scheduled_convert_test_source('ws_scheduled_flow_copy');

	pg_assert_contains('ws_flow_graph_of($flow)', $copy);
	pg_assert_contains("ws_flow_graph_from_scheduled(ws_scheduled_form_data(\$viewer, \$row), ws_scheduled_money_metrics(), (int) \$row['id'])", $copy);
	pg_assert_contains('ws_scheduled_flow_home(', $copy);
	pg_assert_false(strpos($copy, 'note_id') !== false, 'a note keeps no new ones');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("api('ws_flow_scheduled_new', { channel_id: channelId, sketch: 1 })", $js);
	pg_assert_contains("api('ws_flow_scheduled_copy', { id: item.id })", $js);
	pg_assert_false(strpos($js, 'scheduledForm({ note_id') !== false, 'the note toolbar has no entry');
}

// Edit on a card of a row not converted yet tries the conversion again and
// opens its flow; a row the flow still refuses answers its problems, once
// each, and Duplicate is offered. The old form is opened from nowhere.
function test_workspace_scheduled_flow_edit()
{
	pg_assert_same(array('The post box has no text.', 'No channel.'), ws_scheduled_flow_edit_problems(array(
		array('code' => 'bad_config', 'text' => 'The post box has no text.'),
		array('code' => 'bad_config', 'text' => ' The post box has no text. '),
		array('code' => 'no_start', 'text' => ''),
		array('code' => 'bad_config', 'text' => 'No channel.'),
	)));
	pg_assert_same(array(), ws_scheduled_flow_edit_problems(null));

	$edit = ws_scheduled_convert_test_source('ws_scheduled_flow_edit');

	pg_assert_contains('ws_scheduled_convert($id);', $edit, 'the conversion tried again');
	pg_assert_contains("((string) (\$row['anchor'] ?? '') === '')", $edit, 'never a hidden anchor');
	pg_assert_contains("ws_scheduled_convert_drawing(\$row)['problems']", $edit, 'the problems as the conversion found them');

	$route = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($route, "case 'ws_flow_scheduled_edit':");

	pg_assert_true($start !== false, 'the route');
	$route = substr($route, $start, strpos($route, 'return ws_action_ok(ws_scheduled_flow_edit($scheduled));', $start) - $start);
	pg_assert_contains('ws_can_schedule($viewer)', $route);
	pg_assert_contains("array('active', 'paused', 'failed')", $route, 'only a row its card lets edit');
	pg_assert_contains("'ws_flow_scheduled_edit'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'));

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("api('ws_flow_scheduled_edit', { id: id })", $js);
	pg_assert_contains("ask(result.message, t('sa_duplicate'), false, list)", $js);
	pg_assert_contains('scheduledEdit(item.id, edit);', $js, 'Edit on the card');
	pg_assert_contains('scheduledEdit(data.item.id, event && event.currentTarget);', $js, 'Edit on the read-only drawing');
	pg_assert_same(0, preg_match('/scheduledForm\((null|\{\}), /', $js), 'no card, drawing or list opens the form');
}
