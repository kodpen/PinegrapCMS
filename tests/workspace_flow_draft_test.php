<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow an application proposes as a draft card
 * (includes/workspace/flow_draft.php): the shape of {type: flow} read before
 * the person's check, the flow its card opens as, where the proposal route
 * takes it, and that it is never applied. Nothing here reads the database.
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
require_once(__DIR__ . '/helpers/source_reader.php');

function workspace_flow_draft_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

function workspace_flow_draft_change($data, $extra = array())
{
	return $extra + array('type' => 'flow', 'action' => 'draft', 'data' => $data, 'reason' => "Weekly  report\nfor the team");
}

// Boxes as sent become the graph; the reason is one line of notes; the home
// is the channel_id sent, 0 when left out.
function test_ws_flow_draft_input_boxes()
{
	$nodes = array(array('id' => 'start', 'kind' => 'manual'), array('id' => 'done', 'kind' => 'end', 'label' => 'Done'));
	$edges = array(array('from' => 'start', 'to' => 'done', 'on' => 'next'));
	$input = ws_flow_draft_proposal_input(workspace_flow_draft_change(array('name' => 'Report', 'nodes' => $nodes, 'edges' => $edges, 'channel_id' => 7)));

	pg_assert_true($input['ok'], 'taken');
	pg_assert_same('Report', $input['data']['name'], 'name');
	pg_assert_same($nodes, $input['data']['graph']['nodes'], 'nodes');
	pg_assert_same($edges, $input['data']['graph']['edges'], 'edges');
	pg_assert_same(array(), $input['data']['graph']['notes'], 'notes left out stay empty');
	pg_assert_same(7, $input['home'], 'home sent');
	pg_assert_same(array('Weekly report for the team'), $input['notes'], 'reason on one line');

	$bare = ws_flow_draft_proposal_input(array('type' => 'flow', 'data' => array('name' => 'X')));
	pg_assert_true($bare['ok'], 'action left out is draft');
	pg_assert_same(0, $bare['home'], 'no home sent');
	pg_assert_same(array(), $bare['notes'], 'no reason');
	pg_assert_false(array_key_exists('template', $bare['data']), 'no template');
}

// A template with its answers is passed on for the person's fill; no graph
// is made here.
function test_ws_flow_draft_input_template()
{
	$input = ws_flow_draft_proposal_input(workspace_flow_draft_change(array('template' => 'daily_sales', 'answers' => array('channel' => 3))));

	pg_assert_true($input['ok'], 'taken');
	pg_assert_same('daily_sales', $input['data']['template'], 'template');
	pg_assert_same(array('channel' => 3), $input['data']['answers'], 'answers');
	pg_assert_false(isset($input['data']['graph']), 'the template makes the graph');
	pg_assert_false(isset($input['data']['name']), 'name left to the template');
}

function test_ws_flow_draft_input_refusals()
{
	$refused = array(
		'action'          => array('type' => 'flow', 'action' => 'update', 'data' => array('name' => 'X')),
		'data missing'    => array('type' => 'flow'),
		'data a list'     => array('type' => 'flow', 'data' => array(1, 2)),
		'nodes an object' => workspace_flow_draft_change(array('nodes' => array('a' => array()))),
		'edges a string'  => workspace_flow_draft_change(array('edges' => 'x')),
		'template, boxes' => workspace_flow_draft_change(array('template' => 'daily_sales', 'nodes' => array())),
		'template empty'  => workspace_flow_draft_change(array('template' => '')),
		'template number' => workspace_flow_draft_change(array('template' => 5)),
		'answers a list'  => workspace_flow_draft_change(array('template' => 'daily_sales', 'answers' => array(1))),
	);

	foreach ($refused as $case => $change) {
		$input = ws_flow_draft_proposal_input($change);
		pg_assert_false($input['ok'], $case);
		pg_assert_true($input['error'] !== '', $case . ' says why');
	}
}

// The flow a card opens as: the name, home and graph the check kept; a
// draft with no name is named Flow.
function test_ws_flow_draft_flow_input()
{
	$graph = array('nodes' => array(array('id' => 'start', 'kind' => 'manual')), 'edges' => array(), 'notes' => array());
	$made = ws_flow_draft_flow_input(array('data' => array('name' => ' Report ', 'channel_id' => 4, 'graph' => $graph)));

	pg_assert_same('Report', $made['name'], 'name');
	pg_assert_same(4, $made['channel_id'], 'home');
	pg_assert_same('start', $made['graph']['nodes'][0]['id'], 'graph');
	pg_assert_same(array(), $made['left'], 'nothing left out');

	$empty = ws_flow_draft_flow_input(array());
	pg_assert_same('Flow', $empty['name'], 'named for what it is');
	pg_assert_same(0, $empty['channel_id'], 'no home');
	pg_assert_same(array(), $empty['graph']['nodes'], 'no boxes');
}

// Taken only from an application's proposal, before the record kinds are
// read, in a channel and never on a task; applying it stays refused, the
// card opens both kinds of draft, the route describes it.
function test_ws_flow_draft_wiring()
{
	$code = workspace_flow_draft_code('ws_changes_input');
	$branch = strpos($code, 'ws_flow_draft_proposal_row(');
	$kinds = strpos($code, 'isset($types[$type])');

	pg_assert_true($branch !== false, 'a flow draft is checked');
	pg_assert_true(($kinds !== false) && ($branch < $kinds), 'before the record kinds refuse it');

	$part = substr($code, strpos($code, "'flow'"), $branch - strpos($code, "'flow'"));
	pg_assert_contains('$for_proposal', $part, 'only in an application\'s proposal');
	pg_assert_contains('$task_id', $part, 'not on a task');

	pg_assert_contains("'flow'", workspace_flow_draft_code('ws_change_apply_check'), 'applying a flow draft stays refused');
	pg_assert_contains('ws_flow_draft_change_entry(', workspace_flow_draft_code('ws_changes_map'), 'the card draws it');
	pg_assert_contains('ws_scheduled_draft_flow_open(', workspace_flow_draft_code('ws_flow_draft_open'), 'a scheduled draft still opens');
	pg_assert_contains('ws_flow_draft_opened(', workspace_flow_draft_code('ws_scheduled_draft_flow_open'), 'and is written the same way');

	$param = ws_api_changes_param('the person', true, true);
	pg_assert_contains('{type: flow, action: draft, data, reason}', $param['description'], 'the flow draft is described');
	pg_assert_contains('{type: scheduled', $param['description'], 'the older kind still is');
}

// The settings each kind of box takes, from the catalogue.
function workspace_flow_draft_keys()
{
	return ws_flow_ai_setting_keys(ws_flow_kind_catalogue());
}

// The tool takes only the kinds of the catalogue and the outcomes an arrow
// leaves on, and names what each kind takes.
function test_ws_flow_ai_tool_from_catalogue()
{
	$catalogue = ws_flow_kind_catalogue();
	$tool = ws_flow_ai_tool($catalogue);
	$params = $tool['function']['parameters']['properties'];
	$box = $params->boxes['items']['properties'];
	$arrow = $params->arrows['items']['properties'];
	$kinds = array_map(function ($kind) {
		return $kind['kind'];
	}, $catalogue);

	pg_assert_same('propose_flow', $tool['function']['name'], 'name');
	pg_assert_same($kinds, $box->kind['enum'], 'kinds from the catalogue');
	pg_assert_true(in_array('always', $arrow->on['enum'], true), 'arrows on always');
	pg_assert_true(in_array('approved', $arrow->on['enum'], true), 'and on the outcomes of the kinds');
	pg_assert_same(array('add', 'connect', 'remove', 'set'), $params->changes['items']['properties']->op['enum'], 'patch operations');

	foreach ($catalogue as $kind) {
		pg_assert_contains('- ' . $kind['kind'] . ' "', $tool['function']['description'], $kind['kind'] . ' described');
	}

	pg_assert_contains('priority (normal|low|high|urgent)', ws_flow_ai_catalogue_text($catalogue), 'fixed words listed');
	pg_assert_contains('manual "', ws_flow_ai_catalogue_text($catalogue), 'the start box');
}

// No invented kind and no invented setting.
function test_ws_flow_ai_node_refusals()
{
	$keys = workspace_flow_draft_keys();
	$ok = ws_flow_ai_node(array('id' => 'task_1', 'kind' => 'task', 'label' => 'Call', 'settings' => array('title' => 'Call back')), $keys, 'boxes[0]: ');

	pg_assert_true($ok['ok'], 'a known kind and setting');
	pg_assert_same(array('title' => 'Call back'), $ok['node']['config'], 'settings kept as config');

	$kind = ws_flow_ai_node(array('id' => 'x', 'kind' => 'sms'), $keys, 'boxes[0]: ');
	pg_assert_false($kind['ok'], 'unknown kind');
	pg_assert_contains('boxes[0]: ', $kind['error'], 'says where');

	$setting = ws_flow_ai_node(array('id' => 'x', 'kind' => 'task', 'settings' => array('colour' => 'red')), $keys, '');
	pg_assert_false($setting['ok'], 'unknown setting');
	pg_assert_contains('colour', $setting['error'], 'names it');

	pg_assert_false(ws_flow_ai_node(array('id' => 'x', 'kind' => 'task', 'settings' => 'title'), $keys, '')['ok'], 'settings an object');
}

// A new flow is laid out from its start, a column per step, a branch one
// row lower; nothing the model sends is a place.
function test_ws_flow_ai_graph_layout()
{
	$made = ws_flow_ai_graph(array(
		'boxes'  => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'ask', 'kind' => 'approval', 'settings' => array('title' => 'OK?', 'channel_id' => 3, 'approvers' => array(5))),
			array('id' => 'yes', 'kind' => 'task', 'settings' => array('title' => 'Go')),
			array('id' => 'no', 'kind' => 'task', 'settings' => array('title' => 'Stop')),
		),
		'arrows' => array(
			array('from' => 'start', 'to' => 'ask'),
			array('from' => 'ask', 'to' => 'yes', 'on' => 'approved'),
			array('from' => 'ask', 'to' => 'no', 'on' => 'rejected'),
		),
	), workspace_flow_draft_keys());

	pg_assert_true($made['ok'], 'made');

	$at = array();

	foreach ($made['graph']['nodes'] as $node) {
		$at[$node['id']] = array($node['x'], $node['y']);
	}

	pg_assert_same(array(WS_FLOW_LAYOUT_ORIGIN, WS_FLOW_LAYOUT_ORIGIN), $at['start'], 'start first');
	pg_assert_same(array(WS_FLOW_LAYOUT_ORIGIN + WS_FLOW_LAYOUT_STEP_X, WS_FLOW_LAYOUT_ORIGIN), $at['ask'], 'next column');
	pg_assert_same($at['yes'][0], $at['no'][0], 'both branches in one column');
	pg_assert_same(WS_FLOW_LAYOUT_STEP_Y, $at['no'][1] - $at['yes'][1], 'one row apart');
	pg_assert_same(array(), array_values(array_filter(ws_flow_graph_check($made['graph']), function ($problem) {
		return empty($problem['warning']);
	})), 'publishable as drafted');

	pg_assert_false(ws_flow_ai_graph(array('boxes' => array()), workspace_flow_draft_keys())['ok'], 'no boxes');
	pg_assert_false(ws_flow_ai_graph(array('boxes' => array(array('id' => 'a', 'kind' => 'nope'))), workspace_flow_draft_keys())['ok'], 'invented kind');
}

// A patch is made in order on the flow; the boxes there keep their places,
// a box added goes after the one it follows, and the changes kept make the
// same flow again.
function test_ws_flow_patch_apply()
{
	$keys = workspace_flow_draft_keys();
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'x' => 400, 'y' => 32),
			array('id' => 'task_1', 'kind' => 'task', 'x' => 700, 'y' => 32, 'config' => array('title' => 'One', 'priority' => 'low')),
			array('id' => 'old', 'kind' => 'task', 'x' => 700, 'y' => 300, 'config' => array('title' => 'Old one')),
		),
		'edges' => array(array('from' => 'start', 'to' => 'task_1', 'on' => 'next'), array('from' => 'start', 'to' => 'old', 'on' => 'next')),
	));
	$ops = array(
		array('op' => 'add', 'id' => 'tell', 'kind' => 'notify', 'label' => 'Tell', 'settings' => array('text' => 'Done: {{node.task_1.task_id}}')),
		array('op' => 'connect', 'from' => 'task_1', 'to' => 'tell', 'on' => 'done'),
		array('op' => 'set', 'id' => 'task_1', 'label' => 'First', 'settings' => array('priority' => 'high')),
		array('op' => 'remove', 'id' => 'old'),
	);
	$made = ws_flow_patch_apply($graph, $ops, $keys);

	pg_assert_true($made['ok'], 'made');

	$by = array();

	foreach ($made['graph']['nodes'] as $node) {
		$by[$node['id']] = $node;
	}

	pg_assert_same(array('start', 'task_1', 'tell'), array_keys($by), 'added and removed');
	pg_assert_same(array(400, 32), array($by['start']['x'], $by['start']['y']), 'kept its place');
	pg_assert_same(array(700 + WS_FLOW_LAYOUT_STEP_X, 32), array($by['tell']['x'], $by['tell']['y']), 'after the box it follows');
	pg_assert_same('First', $by['task_1']['label'], 'label set');
	pg_assert_same('high', $by['task_1']['config']['priority'], 'setting set');
	pg_assert_same('One', $by['task_1']['config']['title'], 'other settings kept');
	pg_assert_same(2, count($made['graph']['edges']), 'the removed box\'s arrow went with it');
	pg_assert_same(array('tell', 'task_1'), $made['touched'], 'what it added or changed');
	pg_assert_same('old', $made['ops'][3]['name'], 'a removed box keeps its name for the card');

	$again = ws_flow_patch_apply($graph, $made['ops'], $keys);
	pg_assert_same($made['graph'], $again['graph'], 'the changes kept make the same flow');

	$lines = ws_flow_patch_lines($made['ops'], $made['graph']);
	pg_assert_same(4, count($lines), 'a line per change');
	pg_assert_contains('First → Tell (done)', $lines[1]['text'], 'arrow by names');
	pg_assert_contains('priority', $lines[2]['text'], 'the settings changed');
	pg_assert_same('old', $lines[3]['text'], 'the box removed');

	$refused = array(
		'empty'          => array(),
		'unknown op'     => array(array('op' => 'move', 'id' => 'start')),
		'id taken'       => array(array('op' => 'add', 'id' => 'start', 'kind' => 'task')),
		'bad id'         => array(array('op' => 'add', 'id' => 'Tell me', 'kind' => 'task')),
		'no such box'    => array(array('op' => 'set', 'id' => 'ghost', 'settings' => array())),
		'invented field' => array(array('op' => 'set', 'id' => 'task_1', 'settings' => array('colour' => 'red'))),
		'arrow to none'  => array(array('op' => 'connect', 'from' => 'start', 'to' => 'ghost')),
		'no such arrow'  => array(array('op' => 'remove', 'from' => 'task_1', 'to' => 'start')),
	);

	foreach ($refused as $case => $bad) {
		$out = ws_flow_patch_apply($graph, $bad, $keys);
		pg_assert_false($out['ok'], $case);
		pg_assert_true($out['error'] !== '', $case . ' says why');
	}
}

// Pinegrap AI offers the tool in a channel to whoever builds flows, keeps
// its draft as the card an application's proposal makes, never saves or
// publishes a flow while drafting, and a card that changes a flow opens onto
// that flow's draft.
function test_ws_flow_ai_wiring()
{
	pg_assert_contains('ws_flow_ai_tool(ws_flow_kind_catalogue())', workspace_flow_draft_code('ws_ai_tools'), 'schema from the catalogue');
	pg_assert_contains('ws_flow_ai_offered(', workspace_flow_draft_code('ws_ai_tools'), 'only where offered');
	pg_assert_contains('ws_flow_ai_propose(', workspace_flow_draft_code('ws_ai_tool_run'), 'handled');
	pg_assert_true(in_array('propose_flow', ws_ai_tool_names(), true), 'named');

	$propose = workspace_flow_draft_code('ws_flow_ai_propose');
	pg_assert_contains('ws_flow_draft_proposal_row(', $propose, 'the A8 check and card');
	pg_assert_contains('ws_flow_save_check(', $propose, 'a patch rehearsed as a draft save');
	pg_assert_contains('ws_flow_ai_problem_texts(', $propose, 'the publish problems handed back');

	foreach (array('ws_flow_save(', 'ws_flow_publish', 'ws_changes_store(') as $write) {
		pg_assert_false(strpos($propose, $write) !== false, 'drafting writes nothing: ' . $write);
	}

	pg_assert_contains("\$proposals['flows']", workspace_flow_draft_code('ws_ai_deliver'), 'stored with the answer');
	pg_assert_contains('ws_flow_draft_patch_open(', workspace_flow_draft_code('ws_flow_draft_open'), 'a patch card opens onto its flow');

	$open = workspace_flow_draft_code('ws_flow_draft_patch_open');
	pg_assert_contains("'edit'", $open, 'only for an editor of the flow');
	pg_assert_contains("'draft_rev'", $open, 'saved against the draft it was made on');
	pg_assert_false(strpos($open, 'ws_flow_publish') !== false, 'never published');

	$row = array('channel_id' => 3, 'note_id' => 0, 'task_id' => 0, 'mcp_server_id' => 0);
	pg_assert_false(ws_flow_ai_offered($row, null), 'nobody asking');
	pg_assert_false(ws_flow_ai_offered(array('task_id' => 9) + $row, array('id' => 1, 'role' => 0)), 'not on a task');
}

// A patch opened on the canvas marks the boxes it added or changed, in its
// order; one it removed, or that is gone from the draft, is not marked; an
// added box it then sets stays added. The flow's detail carries the marks to
// whoever may change it, read from that person's opened cards since the last
// publish.
function test_ws_flow_patch_marks()
{
	$graph = array('nodes' => array(array('id' => 'start'), array('id' => 'task_1'), array('id' => 'tell'), array('id' => 'ask')));
	$ops = array(
		array('op' => 'add', 'id' => 'tell', 'kind' => 'notify'),
		array('op' => 'connect', 'from' => 'task_1', 'to' => 'tell', 'on' => 'done'),
		array('op' => 'set', 'id' => 'task_1', 'settings' => array()),
		array('op' => 'set', 'id' => 'tell', 'label' => 'Tell'),
		array('op' => 'add', 'id' => 'gone', 'kind' => 'task'),
		array('op' => 'set', 'id' => 'old', 'settings' => array()),
		array('op' => 'remove', 'id' => 'old'),
		array('op' => 'remove', 'from' => 'start', 'to' => 'ask'),
		array('op' => 'set', 'id' => 'ask', 'settings' => array()),
		'not a change',
	);

	pg_assert_same(array(
		array('id' => 'tell', 'mark' => 'added'),
		array('id' => 'task_1', 'mark' => 'changed'),
		array('id' => 'ask', 'mark' => 'changed'),
	), ws_flow_patch_marks($ops, $graph), 'added, changed, in order; removed and gone left out');
	pg_assert_same(array(), ws_flow_patch_marks(null, $graph), 'no patch');

	$detail = workspace_flow_draft_code('ws_flow_detail');
	pg_assert_contains("'ai_marks'", $detail, 'the detail carries the marks');
	pg_assert_contains('$edit', substr($detail, strpos($detail, "'ai_marks'")), 'only to an editor');

	$marks = workspace_flow_draft_code('ws_flow_ai_marks');
	pg_assert_contains("decided_by", $marks, 'the person\'s own opened cards');
	pg_assert_contains("'published_at'", $marks, 'until the flow is published');
	pg_assert_contains("['base']['flow_id']", $marks, 'of this flow');
}
