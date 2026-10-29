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
	pg_assert_same(array('list' => '{{node.a.body}}', 'path' => 'data.items', 'fields' => array('name', 'e_mail')),
		ws_flow_config_normalize('for_each', array('list' => '{{node.a.body}}', 'path' => 'data.items', 'fields' => array('Name', 'name', 'e_mail'), 'x' => 1)));
	pg_assert_same(array('for_each' => 'for_each_1', 'collect' => ''), ws_flow_config_normalize('for_each_end', array('for_each' => 'for_each_1')));

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');
	pg_assert_same(array('list'), $catalogue['for_each']['texts']);
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
	pg_assert_true(in_array('The For each box for_each_1 has no End for each box: its loop needs one.', array_column($problems, 'text'), true));

	// A body that never reaches its End box: the arrow leaves the loop.
	$graph = ws_flow_loops_test_graph(array(), array(array('notify_1', 'notify_2', 'done')));
	$texts = array_column(ws_flow_graph_check($graph), 'text');
	pg_assert_true(in_array('An arrow leaves the loop of for_each_1 from notify_1 to notify_2; a loop is left through its End box only.', $texts, true), implode(' | ', $texts));

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
	pg_assert_true(in_array('The box for_each_2 is in the loop of for_each_1 and starts, ends or is in another loop too; a loop inside or across another is not supported yet.', $texts, true), implode(' | ', $texts));

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
	pg_assert_same('The list holds 51 items; a For each box goes through at most 50.', $long['error']);
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
	pg_assert_contains('ws_flow_loop_item_values(', $values);
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
