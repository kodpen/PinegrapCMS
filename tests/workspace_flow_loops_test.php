<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the For each loop of a flow (includes/workspace/flow_loops.php):
 * what the graph check refuses (an End box without its For each box and the
 * other way round, a body that never reaches its End box, arrows into or out
 * of a body, a loop inside another, values read from outside a loop), the
 * walk that runs the body once per item at the item's pass and the End box
 * once after every item, a run whose item failed, the list read and its
 * 50-item cap, the item's values, what the End box gives and its 16 KB list,
 * the cap on tasks, approvals and e-mails opened inside loops, and a flow
 * without loops walking as before at pass 0.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_loops.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// A box of the graph.
function ws_flow_loops_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// A flow: start, a webhook whose answer is the list, For each over it, a
// notify box per item, the End box gathering what each told, a notify
// after the loop. Extra boxes and arrows ([from, to, on]) are added.
function ws_flow_loops_test_graph($extra_nodes = array(), $extra_edges = array(), $end = array(), $each = array())
{
	$nodes = array_merge(array(
		ws_flow_loops_test_node('start', 'manual'),
		ws_flow_loops_test_node('webhook_1', 'webhook', array('url' => 'https://example.com/list', 'method' => 'GET')),
		ws_flow_loops_test_node('for_each_1', 'for_each', $each + array('list' => '{{node.webhook_1.body}}', 'path' => '', 'fields' => array('name'))),
		ws_flow_loops_test_node('notify_1', 'notify', array('user_ids' => array(1), 'text' => 'Hello {{node.for_each_1.name}}, {{node.for_each_1.index}} of {{node.for_each_1.count}}')),
		ws_flow_loops_test_node('for_each_end_1', 'for_each_end', $end + array('for_each' => 'for_each_1', 'collect' => '{{node.notify_1.told}}')),
		ws_flow_loops_test_node('notify_2', 'notify', array('user_ids' => array(1), 'text' => 'Done: {{node.for_each_end_1.done}} of {{node.for_each_end_1.count}}')),
	), $extra_nodes);
	$edges = array_merge(array(
		array('start', 'webhook_1', 'next'),
		array('webhook_1', 'for_each_1', 'done'),
		array('for_each_1', 'notify_1', 'each'),
		array('notify_1', 'for_each_end_1', 'always'),
		array('for_each_end_1', 'notify_2', 'done'),
	), $extra_edges);
	$graph = array('nodes' => $nodes, 'edges' => array());

	foreach ($edges as $edge) {
		$graph['edges'][] = array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
	}

	return ws_flow_graph_normalize($graph);
}

// The codes a graph check gives, without its warnings.
function ws_flow_loops_test_codes($graph)
{
	$codes = array();

	foreach (ws_flow_graph_check($graph) as $problem) {
		if (empty($problem['warning'])) {
			$codes[] = $problem['code'];
		}
	}

	return $codes;
}

// The codes of the loop check alone (ws_flow_loop_problems()), without its
// warnings: a Read a row box's cells are known only from the table's store,
// which a unit test has none of.
function ws_flow_loops_test_loop_codes($graph)
{
	$codes = array();

	foreach (ws_flow_loop_problems($graph, array_column($graph['nodes'], null, 'id')) as $problem) {
		if (empty($problem['warning'])) {
			$codes[] = $problem['code'];
		}
	}

	return $codes;
}

// A step row as the walk reads it.
function ws_flow_loops_test_row($node, $pass, $status, $outcome = '', $data = null)
{
	return array('node_id' => $node, 'pass' => $pass, 'status' => $status, 'outcome' => $outcome,
		'result' => ws_flow_result_pack('', is_array($data) ? $data : array()));
}

// The rows of a run whose For each box read $count items.
function ws_flow_loops_test_started($count)
{
	return array(
		ws_flow_loops_test_row('start', 0, 'done', 'next'),
		ws_flow_loops_test_row('webhook_1', 0, 'done', 'done'),
		ws_flow_loops_test_row('for_each_1', 0, 'done', 'each', array('count' => $count, 'items' => '[]')),
	);
}

// The source of a function.
function ws_flow_loops_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The two kinds: a For each box leaves on each into its body or on failed,
// never on always; its End box on done.
function test_workspace_flow_loops_kinds()
{
	$kinds = ws_flow_kinds();

	pg_assert_same(array('each', 'failed'), $kinds['for_each']['outcomes']);
	pg_assert_same(array('each', 'failed'), ws_flow_arrow_outcomes($kinds['for_each']), 'no arrow on always');
	pg_assert_same(array('done', 'always'), ws_flow_arrow_outcomes($kinds['for_each_end']));
	pg_assert_same(array('count', 'done', 'failed', 'list'), array_column($kinds['for_each_end']['outputs'], 'key'));
	pg_assert_same(array('count', 'index', 'item', 'name'), array_column(ws_flow_loop_outputs(array('fields' => array('name'))), 'key'));
	pg_assert_same(array('list' => '{{node.a.body}}', 'path' => 'data.items', 'fields' => array('name', 'e_mail'), 'mode' => 'list', 'count' => ''),
		ws_flow_config_normalize('for_each', array('list' => '{{node.a.body}}', 'path' => 'data.items', 'fields' => array('Name', 'name', 'e_mail'), 'x' => 1)));
	pg_assert_same(array('for_each' => 'for_each_1', 'collect' => ''), ws_flow_config_normalize('for_each_end', array('for_each' => 'for_each_1')));

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');
	pg_assert_same(array('list', 'count'), $catalogue['for_each']['texts']);
	pg_assert_same(array('collect'), $catalogue['for_each_end']['texts']);
}

// A loop drawn as decided has no problem.
function test_workspace_flow_loops_graph_check_accepts_a_loop()
{
	pg_assert_same(array(), ws_flow_loops_test_codes(ws_flow_loops_test_graph()));

	// A failed arrow out of the For each box leads outside the loop.
	pg_assert_same(array(), ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(array('for_each_1', 'notify_2', 'failed')))));

	$loops = ws_flow_loops(ws_flow_loops_test_graph());
	pg_assert_same(array('for_each_1' => array('end' => 'for_each_end_1', 'each' => array('notify_1'), 'body' => array('notify_1'))), $loops);
	pg_assert_same('for_each_1', ws_flow_loop_of($loops, 'notify_1'));
	pg_assert_same('', ws_flow_loop_of($loops, 'notify_2'));
}

// The graph check refuses a loop that is not drawn whole.
function test_workspace_flow_loops_graph_check_refuses()
{
	// An End box naming no For each box.
	$codes = ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(), array('for_each' => 'nothing')));
	pg_assert_true(in_array('bad_loop', $codes, true), 'an End box without its For each box');

	// A For each box without an End box.
	$graph = ws_flow_loops_test_graph();
	$graph['nodes'] = array_values(array_filter($graph['nodes'], function ($node) {
		return $node['id'] !== 'for_each_end_1';
	}));
	$graph['edges'] = array_values(array_filter($graph['edges'], function ($edge) {
		return ($edge['to'] !== 'for_each_end_1') && ($edge['from'] !== 'for_each_end_1');
	}));
	$problems = ws_flow_graph_check($graph);
	pg_assert_true(in_array('The loop of For each has no End box: add one after the boxes it repeats.', array_column($problems, 'text'), true));

	// A body that never reaches its End box: the arrow leaves the loop.
	$graph = ws_flow_loops_test_graph(array(), array(array('notify_1', 'notify_2', 'done')));
	$texts = array_column(ws_flow_graph_check($graph), 'text');
	pg_assert_true(in_array('An arrow leaves the loop of For each from Let people know (1) to Let people know (2); a loop is left through its End box only.', $texts, true), implode(' | ', $texts));

	// An arrow from outside into the body.
	$codes = ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(array('webhook_1', 'notify_1', 'done'))));
	pg_assert_true(in_array('bad_loop', $codes, true), 'a body entered from outside');

	// The For each box's arrow straight to its End box: no body.
	$graph = ws_flow_loops_test_graph(array(), array(array('for_each_1', 'for_each_end_1', 'each')));
	pg_assert_true(in_array('bad_loop', ws_flow_loops_test_codes($graph), true), 'the End box reached from outside its body');

	// On always, a For each box has no outcome.
	$graph = ws_flow_loops_test_graph(array(), array(array('for_each_1', 'notify_2', 'always')));
	pg_assert_true(in_array('bad_outcome', ws_flow_loops_test_codes($graph), true));

	// A loop inside another one.
	$graph = ws_flow_loops_test_graph(array(
		ws_flow_loops_test_node('for_each_2', 'for_each', array('list' => '{{node.webhook_1.body}}')),
		ws_flow_loops_test_node('notify_3', 'notify', array('user_ids' => array(1), 'text' => 'Inner')),
		ws_flow_loops_test_node('for_each_end_2', 'for_each_end', array('for_each' => 'for_each_2')),
	), array(
		array('notify_1', 'for_each_2', 'done'),
		array('for_each_2', 'notify_3', 'each'),
		array('notify_3', 'for_each_end_2', 'done'),
		array('for_each_end_2', 'for_each_end_1', 'done'),
	));
	$texts = array_column(ws_flow_graph_check($graph), 'text');
	pg_assert_true(in_array('The box For each (2) is in the loop of For each (1) and starts, ends or is in another loop too; a loop inside or across another is not supported yet.', $texts, true), implode(' | ', $texts));

	// No list, or more than one value for it.
	$codes = ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(), array(), array('list' => '')));
	pg_assert_true(in_array('bad_config', $codes, true), 'a For each box without its list');
	$codes = ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(), array(), array('list' => 'x {{node.webhook_1.body}}')));
	pg_assert_true(in_array('bad_config', $codes, true), 'a list of one value only');

	// A field named as a value the box has.
	$codes = ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(), array(), array('fields' => array('count'))));
	pg_assert_true(in_array('bad_config', $codes, true));

	// The End box gathers a value of its body only.
	$codes = ws_flow_loops_test_codes(ws_flow_loops_test_graph(array(), array(), array('collect' => '{{node.webhook_1.status_code}}')));
	pg_assert_true(in_array('bad_config', $codes, true), 'a value of a box outside the loop');
}

// A loop whose items can pass the run's box limit is published with a
// warning: 50 items x 4 boxes in the body and the boxes outside pass 200.
function test_workspace_flow_loops_size_warning()
{
	pg_assert_same(200, WS_FLOW_MAX_BOX_RUNS);

	// Each box of the body adds 50 box runs.
	$body = function ($count) {
		$nodes = array();
		$edges = array();
		$from = 'notify_1';

		for ($i = 1; $i < $count; $i++) {
			$nodes[] = ws_flow_loops_test_node('extra_' . $i, 'notify', array('user_ids' => array(1), 'text' => 'Item ' . $i));
			$edges[] = array($from, 'extra_' . $i, 'done');
			$from = 'extra_' . $i;
		}

		$graph = ws_flow_loops_test_graph($nodes, $edges);
		$graph['edges'] = array_values(array_filter($graph['edges'], function ($edge) use ($count) {
			return ($count === 1) || !(($edge['from'] === 'notify_1') && ($edge['to'] === 'for_each_end_1'));
		}));

		if ($count > 1) {
			$graph['edges'][] = array('from' => $from, 'to' => 'for_each_end_1', 'on' => 'always');
		}

		return $graph;
	};
	$warnings = function ($graph) {
		return array_values(array_filter(ws_flow_warnings($graph), function ($problem) {
			return $problem['code'] === 'loop_size';
		}));
	};

	// 3 boxes in the body: 150 + webhook, For each, End, notify after = 154.
	pg_assert_same(array(), ws_flow_loops_test_codes($body(3)));
	pg_assert_same(array(), $warnings($body(3)));
	pg_assert_same(154, ws_flow_loop_box_runs(array_column($body(3)['nodes'], null, 'id'), ws_flow_loops($body(3)))['total']);

	// 4 boxes: 200 + 4 = 204, a warning on the For each box, still no refusal.
	$graph = $body(4);
	pg_assert_same(array(), ws_flow_loops_test_codes($graph), 'a warning, not a refusal');
	$found = $warnings($graph);
	pg_assert_same(1, count($found));
	pg_assert_same('for_each_1', $found[0]['node']);
	pg_assert_true(!empty($found[0]['warning']));
	pg_assert_same('The loop of For each can run 200 boxes (50 items × 4 boxes in it); with the rest of the flow a run can pass its limit of 200 boxes, and a long list then fails on its last items.', $found[0]['text']);

	// A value box in the body never runs and is not counted.
	$graph = $body(3);
	$graph['nodes'][] = ws_flow_loops_test_node('value_1', 'value', array('text' => '{{node.for_each_1.name}}'));
	$graph = ws_flow_graph_normalize($graph);
	pg_assert_same(154, ws_flow_loop_box_runs(array_column($graph['nodes'], null, 'id'), ws_flow_loops($graph))['total']);
}

// A box outside a loop reads what its items gave through the End box, never
// a box of its body nor the current item.
function test_workspace_flow_loops_graph_check_reads()
{
	$graph = ws_flow_loops_test_graph();
	$graph['nodes'][5]['config']['text'] = 'Last: {{node.notify_1.told}}';
	pg_assert_true(in_array('bad_reference', ws_flow_loops_test_codes($graph), true), 'a box of the body read after the loop');

	$graph = ws_flow_loops_test_graph();
	$graph['nodes'][5]['config']['text'] = 'Last: {{node.for_each_1.item}}';
	pg_assert_true(in_array('bad_reference', ws_flow_loops_test_codes($graph), true), 'the item read after the loop');

	// The count of the For each box is a value after the loop too.
	$graph = ws_flow_loops_test_graph();
	$graph['nodes'][5]['config']['text'] = 'Items: {{node.for_each_1.count}}';
	pg_assert_same(array(), ws_flow_loops_test_codes($graph));

	// A field the For each box does not name.
	$graph = ws_flow_loops_test_graph();
	$graph['nodes'][3]['config']['text'] = '{{node.for_each_1.email}}';
	pg_assert_true(in_array('bad_reference', ws_flow_loops_test_codes($graph), true));
}

// A flow without loops walks as before, every box at pass 0.
function test_workspace_flow_loops_walk_without_loops()
{
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(ws_flow_loops_test_node('start', 'manual'), ws_flow_loops_test_node('a', 'task', array('title' => 'A')),
			ws_flow_loops_test_node('b', 'task', array('title' => 'B')), ws_flow_loops_test_node('c', 'end')),
		'edges' => array(array('from' => 'start', 'to' => 'a', 'on' => 'next'), array('from' => 'a', 'to' => 'b', 'on' => 'done'),
			array('from' => 'a', 'to' => 'c', 'on' => 'cancelled')),
	));
	$rows = array(ws_flow_loops_test_row('start', 0, 'done', 'next'), ws_flow_loops_test_row('a', 0, 'done', 'done'));

	pg_assert_same(array('ready' => array(array('b', 0)), 'skipped' => array(array('c', 0))), ws_flow_loop_walk($graph, $rows));
	pg_assert_same(ws_flow_run_state($graph, ws_flow_step_states($rows)), ws_flow_loop_run_state($graph, $rows));

	$rows[] = ws_flow_loops_test_row('b', 0, 'failed');
	$rows[] = ws_flow_loops_test_row('c', 0, 'skipped');
	pg_assert_same('failed', ws_flow_loop_run_state($graph, $rows));
}

// The body runs once per item, one after another, at the item's pass; the
// End box once every item settled; the flow goes on from it once.
function test_workspace_flow_loops_walk_per_item()
{
	$graph = ws_flow_loops_test_graph();
	$rows = ws_flow_loops_test_started(2);

	pg_assert_same(array('ready' => array(array('notify_1', 1)), 'skipped' => array()), ws_flow_loop_walk($graph, $rows), 'item 1 first');
	pg_assert_same('running', ws_flow_loop_run_state($graph, $rows));

	$rows[] = ws_flow_loops_test_row('notify_1', 1, 'running');
	pg_assert_same(array('ready' => array(), 'skipped' => array()), ws_flow_loop_walk($graph, $rows), 'item 2 waits for item 1');

	$rows[3] = ws_flow_loops_test_row('notify_1', 1, 'done', 'done');
	pg_assert_same(array('ready' => array(array('notify_1', 2)), 'skipped' => array()), ws_flow_loop_walk($graph, $rows));

	// Item 2 fails: its End box still comes, and the run does not fail.
	$rows[] = ws_flow_loops_test_row('notify_1', 2, 'failed');
	pg_assert_same(array('ready' => array(array('for_each_end_1', 0)), 'skipped' => array()), ws_flow_loop_walk($graph, $rows));

	$rows[] = ws_flow_loops_test_row('for_each_end_1', 0, 'done', 'done');
	pg_assert_same(array('ready' => array(array('notify_2', 0)), 'skipped' => array()), ws_flow_loop_walk($graph, $rows), 'once from the End box');

	$rows[] = ws_flow_loops_test_row('notify_2', 0, 'done', 'done');
	pg_assert_same('done', ws_flow_loop_run_state($graph, $rows), 'a failed item fails no run');
}

// An empty list goes straight to the End box; a For each box that failed
// skips its End box and what follows it.
function test_workspace_flow_loops_walk_empty_and_failed()
{
	$graph = ws_flow_loops_test_graph();

	pg_assert_same(array('ready' => array(array('for_each_end_1', 0)), 'skipped' => array()), ws_flow_loop_walk($graph, ws_flow_loops_test_started(0)));

	$rows = ws_flow_loops_test_started(0);
	$rows[2] = ws_flow_loops_test_row('for_each_1', 0, 'failed', 'failed');
	pg_assert_same(array('ready' => array(), 'skipped' => array(array('for_each_end_1', 0), array('notify_2', 0))), ws_flow_loop_walk($graph, $rows));

	$rows[] = ws_flow_loops_test_row('for_each_end_1', 0, 'skipped');
	$rows[] = ws_flow_loops_test_row('notify_2', 0, 'skipped');
	pg_assert_same('failed', ws_flow_loop_run_state($graph, $rows));
}

// The list read: a JSON array, or the one at path; at most 50 items, a
// longer list failing the box with its reason, nothing cut.
function test_workspace_flow_loops_items()
{
	$list = ws_flow_loop_items('[{"name":"Ada"},{"name":"Bo"}]');
	pg_assert_true($list['ok']);
	pg_assert_same(2, count($list['items']));
	pg_assert_same('[{"name":"Ada"},{"name":"Bo"}]', $list['json']);

	pg_assert_same(array(1, 2), ws_flow_loop_items('{"data":{"items":[1,2]}}', 'data.items')['items']);
	pg_assert_same(array(), ws_flow_loop_items('[]')['items']);
	pg_assert_same('The value has nothing at data.rows.', ws_flow_loop_items('{"data":{}}', 'data.rows')['error']);
	pg_assert_same('The value the box reads is not a list.', ws_flow_loop_items('{"a":1}')['error']);
	pg_assert_same('The value the box reads is not a list.', ws_flow_loop_items(null)['error']);
	pg_assert_same('The value the box reads is not a list.', ws_flow_loop_items('nope')['error']);

	pg_assert_same(50, WS_FLOW_LOOP_ITEMS_MAX);
	pg_assert_true(ws_flow_loop_items(json_encode(range(1, 50)))['ok'], '50 items are gone through');
	$long = ws_flow_loop_items(json_encode(range(1, 51)));
	pg_assert_false($long['ok']);
	pg_assert_same('The list holds 51 items; a loop goes through at most 50.', $long['error']);
	pg_assert_same(array(), $long['items'], 'no item of a longer list');
}

// Inside its body, the For each box gives the current item and its fields.
function test_workspace_flow_loops_item_values()
{
	$config = array('fields' => array('name', 'tags'));
	$items = '[{"name":"Ada","tags":["a"],"ok":true},"plain",7]';

	pg_assert_same(array('index' => '1', 'item' => '{"name":"Ada","tags":["a"],"ok":true}', 'name' => 'Ada', 'tags' => '["a"]'), ws_flow_loop_item_values($config, $items, 1));
	pg_assert_same(array('index' => '2', 'item' => 'plain', 'name' => '', 'tags' => ''), ws_flow_loop_item_values($config, $items, 2));
	pg_assert_same('7', ws_flow_loop_item_values($config, $items, 3)['item']);
	pg_assert_same('', ws_flow_loop_item_values($config, $items, 4)['item'], 'no such item');
	pg_assert_same(2, ws_flow_loop_count(ws_flow_result_pack('', array('count' => 2, 'items' => '[1,2]'))));
}

// The End box counts the items done and failed and gathers its value of
// each; the list keeps whole values up to 16 KB.
function test_workspace_flow_loops_end_outputs()
{
	$loop = ws_flow_loops(ws_flow_loops_test_graph())['for_each_1'];
	$rows = array_merge(ws_flow_loops_test_started(3), array(
		ws_flow_loops_test_row('notify_1', 1, 'done', 'done', array('told' => 2)),
		ws_flow_loops_test_row('notify_1', 2, 'failed', 'failed', array('told' => null)),
		ws_flow_loops_test_row('notify_1', 3, 'done', 'done', array('told' => 1)),
	));

	pg_assert_same(array('count' => 3, 'done' => 2, 'failed' => 1, 'list' => '[2,null,1]', 'kept' => 3),
		ws_flow_loop_end_outputs($loop, 3, $rows, '{{node.notify_1.told}}'));
	pg_assert_same('["done","failed","done"]', ws_flow_loop_end_outputs($loop, 3, $rows, '{{node.notify_1.outcome}}')['list']);
	pg_assert_same('[]', ws_flow_loop_end_outputs($loop, 3, $rows, '')['list'], 'nothing gathered');
	pg_assert_same(array('count' => 0, 'done' => 0, 'failed' => 0, 'list' => '[]', 'kept' => 0), ws_flow_loop_end_outputs($loop, 0, array(), '{{node.notify_1.told}}'));

	pg_assert_same(16384, WS_FLOW_LOOP_LIST_BYTES);
	$big = array();

	for ($pass = 1; $pass <= 3; $pass++) {
		$big[] = ws_flow_loops_test_row('notify_1', $pass, 'done', 'done', array('told' => str_repeat('x', 7000)));
	}

	$out = ws_flow_loop_end_outputs($loop, 3, $big, '{{node.notify_1.told}}');
	pg_assert_same(2, $out['kept'], 'the third value passes 16 KB');
	pg_assert_true(strlen($out['list']) <= WS_FLOW_LOOP_LIST_BYTES);
	pg_assert_same(2, count(json_decode($out['list'], true)), 'whole values, valid JSON');
}

// Tasks, approvals and e-mails opened inside loops: at most 20 a run.
function test_workspace_flow_loops_effects_cap()
{
	pg_assert_same(20, WS_FLOW_LOOP_EFFECTS_MAX);
	pg_assert_same('', ws_flow_loop_effects_refusal(19));
	pg_assert_same('The boxes inside loops opened 20 tasks, approvals or e-mails in this run, its limit; this one was not opened.', ws_flow_loop_effects_refusal(20));
	pg_assert_same(array('task', 'task_open', 'approval', 'email'), ws_flow_loop_effect_kinds());

	$check = ws_flow_loops_test_source('ws_flow_loop_effects_check');
	pg_assert_contains("pass > 0", $check, 'counted inside loops only');
	pg_assert_contains("effect <> ''", $check, 'boxes that began their work');
	pg_assert_contains("(int) (\$step['pass'] ?? 0) === 0", $check, 'a box outside the loops is not capped');

	$run = ws_flow_loops_test_source('ws_flow_step_run');
	pg_assert_true(strpos($run, 'ws_flow_loop_effects_check(') > strpos($run, 'WS_FLOW_MAX_BOX_RUNS'), 'after the run\'s box limit');
}

// The runner writes each box at its pass; a step's webhook key and a
// started flow's key carry the pass; the values a box reads are its item's.
function test_workspace_flow_loops_runner_wiring()
{
	$refresh = ws_flow_loops_test_source('ws_flow_run_refresh');
	pg_assert_contains('ws_flow_loop_walk($graph, $steps)', $refresh);
	pg_assert_contains("(int) \$box[1]", $refresh, 'the pass of each box written');
	pg_assert_contains('ws_flow_loop_run_state($graph, $steps)', $refresh);

	pg_assert_same('pg-flow-7-notify_1-3', ws_step_idempotency_key(7, 'notify_1', 3));
	pg_assert_same('flow:7-start_flow_1-3', ws_flow_chain_key(7, 'start_flow_1', 3));

	$values = ws_flow_loops_test_source('ws_step_run_values');
	pg_assert_contains('pass IN (0, " . $pass . ")', $values, 'its own item and the boxes outside the loops');
	pg_assert_contains('ws_flow_loop_pass_values(', $values);
	pg_assert_contains('ws_step_run_values($run, $graph, $owner, $context, $step)', ws_flow_loops_test_source('ws_step_run'));
	pg_assert_contains('ws_step_run_values($run, $graph, $owner, $source_context, $step)', ws_flow_loops_test_source('ws_flow_chain_start'));

	$mend = ws_flow_loops_test_source('ws_flow_step_mend');
	pg_assert_contains("pass = '\" . (int) \$step['pass'] . \"'", $mend, 'only the boxes skipped in the same item are taken back');
}

// The API hands out a box's pass; a reader who may not change the flow does
// not see the items read from a webhook's answer, nor the list gathered.
function test_workspace_flow_loops_api()
{
	$graph = ws_flow_loops_test_graph();
	$step = array('node_id' => 'for_each_end_1', 'pass' => 0, 'status' => 'done', 'outcome' => 'done', 'effect' => '', 'attempt' => 1,
		'ready_at' => 0, 'claimed_at' => 0, 'finished_at' => 0,
		'result' => ws_flow_result_pack('Went through 1 items.', array('count' => 1, 'done' => 1, 'failed' => 0, 'list' => '["secret"]')));

	$editor = ws_api_flow_run_step_present($step, $graph, true);
	pg_assert_same(0, $editor['pass']);
	pg_assert_same('["secret"]', $editor['outputs']->list);

	$reader = (array) ws_api_flow_run_step_present($step, $graph, false)['outputs'];
	pg_assert_false(array_key_exists('list', $reader), 'the list is held back');
	pg_assert_same(1, $reader['count']);

	$start = array('node_id' => 'for_each_1', 'pass' => 0) + $step;
	$start['result'] = ws_flow_result_pack('', array('count' => 1, 'items' => '["secret"]'));
	pg_assert_false(array_key_exists('items', (array) ws_api_flow_run_step_present($start, $graph, false)['outputs']), 'the items are held back');

	$body = array('node_id' => 'notify_1', 'pass' => 4) + $step;
	pg_assert_same(4, ws_api_flow_run_step_present($body, $graph, true)['pass']);
	pg_assert_same('integer', ws_api_flow_run_step_schema()['pass']);
}

// A list or a gathered value is read as it is: one with a "When empty,
// write" text gets a problem that says so, not the "one value only" text,
// which named no reason when the chip still showed one value.
function test_workspace_flow_loops_fallback_is_named()
{
	$texts = array_column(ws_flow_graph_check(ws_flow_loops_test_graph(array(), array(), array(), array('list' => '{{node.webhook_1.body|[]}}'))), 'text');
	pg_assert_true(in_array('The list of For each cannot have a “When empty, write” text: remove it, the list is read as it is.', $texts, true), implode(' | ', $texts));

	$texts = array_column(ws_flow_graph_check(ws_flow_loops_test_graph(array(), array(), array('collect' => '{{node.notify_1.told|none}}'))), 'text');
	pg_assert_true(in_array('The value End for each gathers cannot have a “When empty, write” text: remove it, the value is gathered as it is.', $texts, true), implode(' | ', $texts));

	pg_assert_true(ws_step_value_has_fallback('{{node.a.b|x}}'));
	pg_assert_false(ws_step_value_has_fallback('{{node.a.b}}'));
	pg_assert_false(ws_step_value_has_fallback('x {{node.a.b|x}}'));
}

// "Try this box" on a For each box reads its list from the values given (a
// chosen run's, the constants' always) and tells how many items it holds,
// or why it cannot be read; on an End box it tells what it gathers. Never
// "it would fail" (a refusal) or "cannot run yet".
function test_workspace_flow_loops_box_try()
{
	$graph = ws_flow_loops_test_graph();
	$nodes = array_column($graph['nodes'], null, 'id');
	$each = $nodes['for_each_1'];

	$tried = ws_flow_loop_try($graph, $each, array('webhook_1' => array('body' => '[{"name":"A"},{"name":"B"},{"name":"C"}]')), true);
	pg_assert_same(array('said' => 'The list holds 3 items.', 'outcome' => 'each'), $tried);

	$tried = ws_flow_loop_try($graph, $each, array('webhook_1' => array('body' => '{"a":1}')), true);
	pg_assert_same(array('said' => 'The list cannot be read: The value the box reads is not a list.', 'outcome' => 'failed'), $tried);

	pg_assert_same('The run chosen gave no value for the list.', ws_flow_loop_try($graph, $each, array(), true)['said']);
	pg_assert_same('Choose a run to read the list with the values it gave.', ws_flow_loop_try($graph, $each, array(), false)['said']);

	$each['config']['list'] = '{{node.webhook_1.body|[]}}';
	pg_assert_same('The list of for_each_1 cannot have a “When empty, write” text: remove it, the list is read as it is.', ws_flow_loop_try($graph, $each, array(), true)['said']);

	$tried = ws_flow_loop_try($graph, $nodes['for_each_end_1'], array(), false);
	pg_assert_same('For each item of the loop of for_each_1, gathers Let people know › People told into its list and counts the items done and failed.', $tried['said']);
	pg_assert_same('done', $tried['outcome']);

	$end = $nodes['for_each_end_1'];
	$end['config']['collect'] = '';
	pg_assert_same('Counts the items of the loop of for_each_1 done and failed; it gathers no value.', ws_flow_loop_try($graph, $end, array(), false)['said']);
	$end['config']['for_each'] = 'nothing';
	pg_assert_same('The box for_each_end_1 ends no loop: choose the loop it ends.', ws_flow_loop_try($graph, $end, array(), false)['said']);

	pg_assert_same(null, ws_flow_loop_try($graph, $nodes['notify_1'], array(), false), 'other kinds are not answered here');

	foreach (array($each, $end) as $node) {
		pg_assert_false(array_key_exists('refusal', ws_flow_loop_try($graph, $node, array(), true)), 'never a refusal');
	}
}

// Tried with a run, a box inside a loop reads its last pass as the run view
// shows it: the For each box's place, item and fields at that pass, the
// boxes of that item and those outside the loop. A box outside the loop
// reads every settled box, as before.
function test_workspace_flow_loops_box_try_run_values()
{
	$graph = ws_flow_loops_test_graph();
	$rows = array(
		ws_flow_loops_test_row('start', 0, 'done', 'next'),
		ws_flow_loops_test_row('webhook_1', 0, 'done', 'done'),
		ws_flow_loops_test_row('for_each_1', 0, 'done', 'each', array('count' => 3, 'items' => '[{"name":"Bir"},{"name":""},{"name":"Üç"}]')),
		ws_flow_loops_test_row('notify_1', 1, 'done', 'done', array('told' => 1)),
		ws_flow_loops_test_row('notify_1', 2, 'done', 'done', array('told' => 2)),
		ws_flow_loops_test_row('notify_1', 3, 'done', 'done', array('told' => 3)),
		ws_flow_loops_test_row('for_each_end_1', 0, 'done', 'done', array('count' => 3, 'done' => 3)),
	);

	$values = ws_flow_loop_try_values($graph, 'notify_1', $rows);
	pg_assert_same('3', $values['for_each_1']['index']);
	pg_assert_same('Üç', $values['for_each_1']['name']);
	pg_assert_same('{"name":"Üç"}', $values['for_each_1']['item']);
	pg_assert_same('3', $values['for_each_1']['count']);
	pg_assert_same('3', $values['notify_1']['told'], 'its own last pass');

	$tried = ws_step_try_texts('notify', array('type' => 'notify', 'user_ids' => array(1), 'text' => 'pg-test öğe {{node.for_each_1.index}}: {{node.for_each_1.name|adsız}}'), $values);
	pg_assert_same('pg-test öğe 3: Üç', $tried['action']['text']);
	pg_assert_same(array(), $tried['empty'], 'nothing left empty');

	// A run stopped in item 2: that item is read.
	$stopped = array_slice($rows, 0, 4);
	$stopped[] = ws_flow_loops_test_row('notify_1', 2, 'failed', 'failed');
	$values = ws_flow_loop_try_values($graph, 'notify_1', $stopped);
	pg_assert_same('2', $values['for_each_1']['index']);
	pg_assert_same('', $values['for_each_1']['name']);
	pg_assert_same('failed', $values['notify_1']['status']);

	// Outside the loop, and a loop box the run never reached: no item values.
	$values = ws_flow_loop_try_values($graph, 'notify_2', $rows);
	pg_assert_false(isset($values['for_each_1']['index']));
	pg_assert_same('3', $values['notify_1']['told']);
	$values = ws_flow_loop_try_values($graph, 'notify_1', array_slice($rows, 0, 3));
	pg_assert_false(isset($values['for_each_1']['index']));

	pg_assert_contains('ws_flow_loop_try_values(', ws_flow_loops_test_source('ws_flow_box_try'));
	pg_assert_contains('ws_flow_loop_pass_values(', ws_flow_loops_test_source('ws_step_run_values'));
}

// Repeat N times: a For each box in count mode reads count, a whole number
// from 1 to 50 or one value; its items are 1 to the count, so index and
// item are alike; it gives no fields.
function test_workspace_flow_loops_count_mode()
{
	$config = ws_flow_config_normalize('for_each', array('mode' => 'count', 'count' => 5, 'fields' => array('name'), 'list' => '{{node.webhook_1.body}}'));

	pg_assert_same('count', $config['mode']);
	pg_assert_same('5', $config['count'], 'a number given as a number is kept as its text');
	pg_assert_same(array(), $config['fields'], 'an item of a count has no fields');
	pg_assert_same('list', ws_flow_config_normalize('for_each', array('mode' => 'odd'))['mode']);
	pg_assert_same('count', ws_flow_loop_source_key($config));
	pg_assert_same(array('{{node.get.cell_4}}'), ws_flow_node_texts(array('kind' => 'for_each', 'config' => array('mode' => 'count', 'count' => '{{node.get.cell_4}}', 'list' => '{{node.x.body}}'))), 'only the count is read');

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');
	$settings = array_column($catalogue['for_each']['settings'], null, 'key');

	pg_assert_same(array('list', 'count'), $settings['mode']['values']);
	pg_assert_same('text', $settings['count']['type']);

	$items = ws_flow_loop_count_items(' 3 ');

	pg_assert_true($items['ok']);
	pg_assert_same(array(1, 2, 3), $items['items']);
	pg_assert_same('[1,2,3]', $items['json']);
	pg_assert_same(array('index' => '2', 'item' => '2'), ws_flow_loop_item_values($config, $items['json'], 2), 'index = item');
	pg_assert_same(50, count(ws_flow_loop_count_items('50')['items']));

	foreach (array('0', '51', 'abc', '2.5', '', '-1') as $bad) {
		pg_assert_false(ws_flow_loop_count_items($bad)['ok'], 'count ' . $bad);
	}

	pg_assert_contains('from 1 to 50', ws_flow_loop_count_items('51')['error']);
	pg_assert_true(strpos(ws_flow_loop_count_items('{"token":"s3cret"}')['error'], 's3cret') === false, 'the value read stays out of the reason');

	pg_assert_same('', ws_flow_loop_count_problem('5', 'Repeat'));
	pg_assert_same('', ws_flow_loop_count_problem('{{node.get.cell_4}}', 'Repeat'), 'one value');
	pg_assert_contains('needs the number of times', ws_flow_loop_count_problem('', 'Repeat'));
	pg_assert_contains('from 1 to 50', ws_flow_loop_count_problem('51', 'Repeat'));
	pg_assert_contains('from 1 to 50', ws_flow_loop_count_problem('five', 'Repeat'));
	pg_assert_contains('“When empty, write”', ws_flow_loop_count_problem('{{node.get.cell_4|3}}', 'Repeat'));
}

// A count out of range or not a number is bad_config at publish; a count
// box needs no list.
function test_workspace_flow_loops_count_graph_check()
{
	$count = function ($value) {
		return ws_flow_loops_test_graph(array(), array(), array('collect' => ''), array('mode' => 'count', 'count' => $value, 'list' => ''));
	};

	pg_assert_same(array('bad_reference'), ws_flow_loops_test_codes($count('5')), 'a count box gives no field: reading one is refused');

	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			ws_flow_loops_test_node('start', 'manual'),
			ws_flow_loops_test_node('for_each_1', 'for_each', array('mode' => 'count', 'count' => '3')),
			ws_flow_loops_test_node('notify_1', 'notify', array('user_ids' => array(1), 'text' => 'Round {{node.for_each_1.index}} of {{node.for_each_1.count}}')),
			ws_flow_loops_test_node('for_each_end_1', 'for_each_end', array('for_each' => 'for_each_1')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'for_each_1', 'on' => 'next'),
			array('from' => 'for_each_1', 'to' => 'notify_1', 'on' => 'each'),
			array('from' => 'notify_1', 'to' => 'for_each_end_1', 'on' => 'done'),
		),
	));

	pg_assert_same(array(), ws_flow_loops_test_codes($graph), 'Repeat 3 times');

	foreach (array('0', '51', 'many', '') as $bad) {
		$graph['nodes'][1]['config']['count'] = $bad;
		pg_assert_same(array('bad_config'), ws_flow_loops_test_codes($graph), 'count ' . $bad);
	}

	$graph['nodes'][1]['config']['count'] = '{{node.start.status}}';
	pg_assert_same(array(), ws_flow_loops_test_codes($graph), 'one value');
}

// The box tried and the box run read the count, a literal or one value.
function test_workspace_flow_loops_count_try_and_run()
{
	$graph = ws_flow_loops_test_graph();
	$node = ws_flow_loops_test_node('for_each_1', 'for_each', ws_flow_loop_normalize('for_each', array('mode' => 'count', 'count' => '4')));

	pg_assert_same(array('said' => 'Repeats 4 times.', 'outcome' => 'each'), ws_flow_loop_try($graph, $node, array(), false));

	$node['config']['count'] = '{{node.get.cell_4}}';
	pg_assert_same('each', ws_flow_loop_try($graph, $node, array('get' => array('cell_4' => '2')), true)['outcome']);
	pg_assert_same('failed', ws_flow_loop_try($graph, $node, array('get' => array('cell_4' => '80')), true)['outcome']);
	pg_assert_same('', ws_flow_loop_try($graph, $node, array(), false)['outcome'], 'no run chosen: nothing to read');

	$run = ws_flow_loops_test_source('ws_flow_loop_start_run');

	pg_assert_contains('ws_flow_loop_count_items($text)', $run);
	pg_assert_contains("ws_flow_loop_source_key(\$node['config']) === 'count'", $run);
	pg_assert_contains("'items' => \$list['json']", $run, 'the items kept as a list\'s are');
}

// A value box reads a body box when every box reading it is in that body:
// it is worked out at the reader's pass. Read inside and outside, it is
// refused as today.
function test_workspace_flow_loops_pure_box_in_a_body()
{
	$build = function ($outside) {
		$nodes = array(
			ws_flow_loops_test_node('start', 'manual'),
			ws_flow_loops_test_node('for_each_1', 'for_each', array('mode' => 'count', 'count' => '5')),
			ws_flow_loops_test_node('get', 'row_get', array('table_id' => 4, 'row' => '7')),
			ws_flow_loops_test_node('plus', 'value', array('rule' => 'add', 'a' => '{{node.get.cell_9}}', 'b' => '1')),
			ws_flow_loops_test_node('twice', 'value', array('rule' => 'add', 'a' => '{{node.plus.value}}', 'b' => '{{node.plus.value}}')),
			ws_flow_loops_test_node('notify_1', 'notify', array('user_ids' => array(1), 'text' => 'Now {{node.twice.value}}')),
			ws_flow_loops_test_node('for_each_end_1', 'for_each_end', array('for_each' => 'for_each_1')),
			ws_flow_loops_test_node('notify_2', 'notify', array('user_ids' => array(1), 'text' => $outside ? 'After {{node.twice.value}}' : 'After')),
		);
		$edges = array(
			array('start', 'for_each_1', 'next'), array('for_each_1', 'get', 'each'), array('get', 'notify_1', 'done'),
			array('notify_1', 'for_each_end_1', 'done'), array('for_each_end_1', 'notify_2', 'done'),
		);

		return ws_flow_graph_normalize(array('nodes' => $nodes, 'edges' => array_map(function ($edge) {
			return array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
		}, $edges)));
	};

	$inside = $build(false);
	$nodes = array_column($inside['nodes'], null, 'id');
	$loops = ws_flow_loops($inside);

	pg_assert_same(array('plus' => 'for_each_1', 'twice' => 'for_each_1'), ws_flow_loop_pure_of($nodes, $loops), 'a chain of value boxes takes the loop of its reader');
	pg_assert_same(array(), ws_flow_loops_test_loop_codes($inside));

	$both = $build(true);

	pg_assert_same(array('plus' => '', 'twice' => ''), ws_flow_loop_pure_of(array_column($both['nodes'], null, 'id'), ws_flow_loops($both)), 'read inside and outside: in no loop');
	pg_assert_same(array('bad_reference'), ws_flow_loops_test_loop_codes($both), 'a body box read from outside through a value box');

	// Worked out at the reader's pass: the runner gives the pure boxes the
	// values of that pass (ws_step_run_values()).
	$rows = array(
		ws_flow_loops_test_row('start', 0, 'done', 'next'),
		ws_flow_loops_test_row('for_each_1', 0, 'done', 'each', array('count' => 2, 'items' => '[1,2]')),
		ws_flow_loops_test_row('get', 1, 'done', 'done', array('row_id' => 7, 'found' => 1, 'cell_9' => '4')),
		ws_flow_loops_test_row('notify_1', 1, 'done', 'done'),
		ws_flow_loops_test_row('get', 2, 'done', 'done', array('row_id' => 7, 'found' => 1, 'cell_9' => '5')),
	);
	$pass = function ($n) use ($inside, $rows) {
		$values = ws_flow_loop_pass_values($inside, 'notify_1', array_values(array_filter($rows, function ($row) use ($n) {
			return in_array((int) $row['pass'], array(0, $n), true);
		})), $n);

		return ws_flow_pure_values($inside, $values)['twice']['value'];
	};

	pg_assert_same('10', $pass(1), 'pass 1 reads 4: (4 + 1) * 2');
	pg_assert_same('12', $pass(2), 'pass 2 reads 5');
	pg_assert_contains('return ws_flow_pure_values($graph, $values) + $values;', ws_flow_loops_test_source('ws_step_run_values'));
}

// A Wait box inside the body waits at its item's pass and is woken by its
// own row: the look for waiting boxes asks no pass, and the next item waits
// for it.
function test_workspace_flow_loops_wait_in_the_body()
{
	$recheck = ws_flow_loops_test_source('ws_flow_steps_recheck');

	pg_assert_contains("WHERE status = 'waiting' AND wake_at > 0 AND wake_at <= '\" . \$now . \"'\" . \$only . \"", $recheck);
	pg_assert_false(strpos($recheck, 'pass') !== false, 'any pass is looked at');
	pg_assert_contains("WHERE id = '\" . (int) \$step['id'] . \"' AND status = 'waiting'", ws_flow_loops_test_source('ws_flow_delay_recheck'), 'it settles its own row');
	pg_assert_same('done', ws_flow_delay_settlement(ws_flow_delay_key(100), 160)['status'], 'its moment come');

	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			ws_flow_loops_test_node('start', 'manual'),
			ws_flow_loops_test_node('for_each_1', 'for_each', array('mode' => 'count', 'count' => '2')),
			ws_flow_loops_test_node('delay_1', 'delay', array('amount' => 1, 'unit' => 'minutes')),
			ws_flow_loops_test_node('for_each_end_1', 'for_each_end', array('for_each' => 'for_each_1')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'for_each_1', 'on' => 'next'),
			array('from' => 'for_each_1', 'to' => 'delay_1', 'on' => 'each'),
			array('from' => 'delay_1', 'to' => 'for_each_end_1', 'on' => 'done'),
		),
	));
	$rows = array(
		ws_flow_loops_test_row('start', 0, 'done', 'next'),
		ws_flow_loops_test_row('for_each_1', 0, 'done', 'each', array('count' => 2, 'items' => '[1,2]')),
		ws_flow_loops_test_row('delay_1', 1, 'done', 'done'),
		ws_flow_loops_test_row('delay_1', 2, 'waiting'),
	);

	pg_assert_same(array('ready' => array(), 'skipped' => array()), ws_flow_loop_walk($graph, $rows), 'pass 2 waits: the End box too');

	$rows[3] = ws_flow_loops_test_row('delay_1', 2, 'done', 'done');
	pg_assert_same(array('ready' => array(array('for_each_end_1', 0)), 'skipped' => array()), ws_flow_loop_walk($graph, $rows), 'woken, the loop ends');
}

// The counter line of the decision as one flow: every 5 minutes, repeat 5
// times: read the row, raise its count, write it back when it was found,
// wait a minute; then the End box.
function test_workspace_flow_loops_counter_line_as_one_flow()
{
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			ws_flow_loops_test_node('start', 'schedule', array('date' => '2026-11-05', 'time' => '09:00', 'repeat' => 'minutes', 'every' => 5)),
			ws_flow_loops_test_node('repeat', 'for_each', array('mode' => 'count', 'count' => '5')),
			ws_flow_loops_test_node('get', 'row_get', array('table_id' => 4, 'row' => '7')),
			ws_flow_loops_test_node('raised', 'value', array('rule' => 'add', 'a' => '{{node.get.cell_9}}', 'b' => '1')),
			ws_flow_loops_test_node('found', 'condition', array('rule' => array('type' => 'value', 'ref' => '{{node.get.found}}', 'op' => 'eq', 'value' => '1'))),
			ws_flow_loops_test_node('set', 'row_set', array('table_id' => 4, 'row' => '{{node.get.row_id}}', 'cells' => '{"9": "{{node.raised.value}}"}')),
			ws_flow_loops_test_node('wait', 'delay', array('amount' => 1, 'unit' => 'minutes')),
			ws_flow_loops_test_node('done', 'for_each_end', array('for_each' => 'repeat')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'repeat', 'on' => 'next'),
			array('from' => 'repeat', 'to' => 'get', 'on' => 'each'),
			array('from' => 'get', 'to' => 'found', 'on' => 'done'),
			array('from' => 'found', 'to' => 'set', 'on' => 'yes'),
			array('from' => 'found', 'to' => 'wait', 'on' => 'no'),
			array('from' => 'set', 'to' => 'wait', 'on' => 'done'),
			array('from' => 'wait', 'to' => 'done', 'on' => 'done'),
		),
	));

	pg_assert_same(array(), ws_flow_loops_test_loop_codes($graph), 'one flow, no loop problem');

	// The whole check, but for the cell only the table's store names.
	$codes = array();

	foreach (ws_flow_graph_check($graph) as $problem) {
		if (empty($problem['warning']) && (strpos((string) $problem['text'], 'cell_9') === false)) {
			$codes[] = $problem['code'];
		}
	}

	pg_assert_same(array(), $codes, 'one flow, no other problem');
	pg_assert_same('repeat', ws_flow_loop_pure_of(array_column($graph['nodes'], null, 'id'), ws_flow_loops($graph))['raised'], 'the raised value is worked out per round');
	pg_assert_same(array('get', 'found', 'set', 'wait'), array_values(array_intersect(array('get', 'found', 'set', 'wait'), ws_flow_loops($graph)['repeat']['body'])));
}
