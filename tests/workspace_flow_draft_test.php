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
