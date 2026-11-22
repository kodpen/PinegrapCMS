<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow's draft tried from its start to its end without running
 * it (includes/workspace/flow_trial.php): the outcomes a person chooses and
 * how they are read, how each box settles, the walk with its values carried
 * on, the failed and skipped branches, the Wait passed; and, read from the
 * source, that nothing is sent or written, that only the owner tries it, and
 * that the panel and the API reach it as they reach a box's try.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// A function's source without its comment lines.
function workspace_flow_trial_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A graph of boxes (id => kind) and arrows (from, to, on).
function workspace_flow_trial_test_graph($nodes, $edges)
{
	$graph = array('nodes' => array(), 'edges' => array());

	foreach ($nodes as $id => $kind) {
		$graph['nodes'][] = array('id' => $id, 'kind' => $kind, 'config' => array());
	}

	foreach ($edges as $edge) {
		$graph['edges'][] = array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
	}

	return ws_flow_graph_normalize($graph);
}

// A try as ws_flow_box_try() answers one that went well.
function workspace_flow_trial_test_tried($outcome, $extra = array())
{
	return $extra + array('ok' => true, 'error' => '', 'outcome' => $outcome, 'refusal' => '', 'said' => 'Said.', 'texts' => array(), 'empty' => array(), 'next' => array());
}

// A box a person or an answer decides offers its outcomes; one the trial
// decides offers none.
function test_workspace_flow_trial_choices()
{
	pg_assert_same(array('done', 'cancelled', 'timeout'), ws_flow_trial_choices(array('kind' => 'task', 'config' => array())));
	pg_assert_same(array('approved', 'rejected', 'expired', 'withdrawn', 'failed'), ws_flow_trial_choices(array('kind' => 'approval', 'config' => array())));
	pg_assert_same(array('done', 'failed'), ws_flow_trial_choices(array('kind' => 'agent', 'config' => array())));

	foreach (array('post', 'email', 'condition', 'delay', 'manual', 'end', 'for_each', 'nothing') as $kind) {
		pg_assert_same(array(), ws_flow_trial_choices(array('kind' => $kind, 'config' => array())), $kind);
	}
}

// The outcomes chosen come as an object, its JSON or id:outcome pairs.
function test_workspace_flow_trial_outcomes_read()
{
	pg_assert_same(array(), ws_flow_trial_outcomes_read(null));
	pg_assert_same(array(), ws_flow_trial_outcomes_read(''));
	pg_assert_same(array('t1' => 'cancelled'), ws_flow_trial_outcomes_read(array('t1' => 'cancelled')));
	pg_assert_same(array('t1' => 'cancelled', 'ask' => 'rejected'), ws_flow_trial_outcomes_read('{"t1":"cancelled","ask":"rejected"}'));
	pg_assert_same(array('t1' => 'cancelled', 'ask' => 'rejected'), ws_flow_trial_outcomes_read('t1:cancelled, ask:rejected'));
	pg_assert_same(null, ws_flow_trial_outcomes_read('t1:cancelled:x'), 'a pair of three parts');
	pg_assert_same(null, ws_flow_trial_outcomes_read('t1'), 'no outcome');
	pg_assert_same(null, ws_flow_trial_outcomes_read(array('t1' => array('done'))), 'an outcome that is no text');
	pg_assert_same(null, ws_flow_trial_outcomes_read(array('done')), 'a list without box ids');
	pg_assert_same(null, ws_flow_trial_outcomes_read(5));
}

// An outcome is chosen only for a box of the graph a person decides, and
// only one of its own.
function test_workspace_flow_trial_outcomes_problem()
{
	$graph = workspace_flow_trial_test_graph(array('go' => 'manual', 't1' => 'task', 'say' => 'post'), array(array('go', 't1', 'next'), array('t1', 'say', 'done')));

	pg_assert_same('', ws_flow_trial_outcomes_problem($graph, array()));
	pg_assert_same('', ws_flow_trial_outcomes_problem($graph, array('t1' => 'cancelled')));
	pg_assert_true(ws_flow_trial_outcomes_problem($graph, array('t1' => 'approved')) !== '', 'not a task\'s outcome');
	pg_assert_true(ws_flow_trial_outcomes_problem($graph, array('say' => 'done')) !== '', 'a box the trial decides');
	pg_assert_true(ws_flow_trial_outcomes_problem($graph, array('gone' => 'done')) !== '', 'a box not in the graph');
}

// How a tried box settles, as the run would settle it.
function test_workspace_flow_trial_settle()
{
	$graph = workspace_flow_trial_test_graph(
		array('go' => 'manual', 't1' => 'task', 'say' => 'post', 'mail' => 'email', 'if' => 'condition', 'wait' => 'delay', 'loop' => 'for_each', 'stop' => 'end', 'ask' => 'approval', 'fix' => 'post'),
		array(array('go', 't1', 'next'), array('say', 'fix', 'failed'))
	);
	$node = function ($id) use ($graph) {
		return ws_flow_graph_node($graph, $id);
	};
	$settle = function ($id, $tried, $chosen = null) use ($graph, $node) {
		$settled = ws_flow_trial_settle($graph, $node($id), $tried, $chosen);

		return array($settled['status'], $settled['outcome']);
	};

	pg_assert_same(array('done', 'next'), $settle('go', workspace_flow_trial_test_tried('next')), 'a start goes on');
	pg_assert_same(array('done', 'done'), $settle('t1', workspace_flow_trial_test_tried('')), 'a task takes its first outcome');
	pg_assert_same(array('done', 'cancelled'), $settle('t1', workspace_flow_trial_test_tried(''), 'cancelled'), 'or the one chosen');
	pg_assert_same(array('done', 'done'), $settle('t1', workspace_flow_trial_test_tried(''), 'nothing'), 'an outcome it cannot take is not taken');
	pg_assert_same(array('failed', 'failed'), $settle('ask', workspace_flow_trial_test_tried(''), 'failed'), 'an approval chosen to fail, on no failed arrow, fails');
	pg_assert_same(array('done', 'yes'), $settle('if', workspace_flow_trial_test_tried('yes')), 'a condition as it was looked at');
	pg_assert_same(array('done', 'done'), $settle('mail', workspace_flow_trial_test_tried('done')), 'a send that would go');
	pg_assert_same(array('failed', 'failed'), $settle('mail', workspace_flow_trial_test_tried('failed', array('refusal' => 'No address.'))), 'one that would fail');
	pg_assert_same(array('done', 'failed'), $settle('say', workspace_flow_trial_test_tried('failed', array('refusal' => 'No channel.'))), 'on its failed arrow');
	pg_assert_same(array('failed', 'failed'), $settle('mail', array('ok' => false, 'error' => 'Refused.')), 'a box the try refuses');
	pg_assert_same(array('done', ''), $settle('stop', workspace_flow_trial_test_tried('')), 'an End');
	pg_assert_same(array('done', ''), $settle('wait', workspace_flow_trial_test_tried('')), 'a Wait whose moment passed and ends the run');
	pg_assert_same(array('done', 'done'), $settle('wait', workspace_flow_trial_test_tried('done')), 'a Wait is passed');
	pg_assert_same(array('failed', 'failed'), $settle('loop', workspace_flow_trial_test_tried('')), 'a loop without its list');

	$settled = ws_flow_trial_settle($graph, $node('t1'), workspace_flow_trial_test_tried(''), null);
	pg_assert_same(array('done', 'cancelled', 'timeout'), $settled['choices'], 'the task names the outcomes to choose');
	pg_assert_same(array(), ws_flow_trial_settle($graph, $node('t1'), workspace_flow_trial_test_tried('', array('refusal' => 'No title.')), null)['choices'], 'none when it would fail');
}

// Started by hand, a condition, a message on yes and one on no, a Wait,
// then an e-mail: the walk takes the yes branch, passes the Wait and hands
// each box the values of those before it.
function test_workspace_flow_trial_walk_carries_values()
{
	$graph = workspace_flow_trial_test_graph(
		array('go' => 'manual', 'read' => 'record_get', 'if' => 'condition', 'yes' => 'post', 'no' => 'post', 'wait' => 'delay', 'mail' => 'email'),
		array(array('go', 'read', 'next'), array('read', 'if', 'done'), array('if', 'yes', 'yes'), array('if', 'no', 'no'), array('yes', 'wait', 'done'), array('wait', 'mail', 'done'))
	);
	$seen = array();

	$walk = ws_flow_trial_walk($graph, ws_flow_graph_node($graph, 'go'), array(WS_STEP_TRIGGER => array('record_id' => '42')), array(), function ($node, $values) use (&$seen) {
		$seen[$node['id']] = $values;

		switch ($node['kind']) {
			case 'manual':
				return workspace_flow_trial_test_tried('next');
			case 'record_get':
				return workspace_flow_trial_test_tried('done', array('data' => array('found' => 1, 'record_id' => 42, 'name' => null)));
			case 'condition':
				return workspace_flow_trial_test_tried('yes');
			case 'delay':
				return workspace_flow_trial_test_tried('done', array('data' => array('waited_until' => '2026-11-22T10:00:00Z')));
		}

		return workspace_flow_trial_test_tried('done');
	});

	pg_assert_same(array('go', 'read', 'if', 'yes', 'wait', 'mail'), array_column($walk['steps'], 'node_id'), 'the boxes in the order walked');
	pg_assert_same(array('no'), $walk['skipped'], 'the branch not taken');
	pg_assert_same('done', $walk['state']);
	pg_assert_same('42', $seen['go'][WS_STEP_TRIGGER]['record_id'], 'what started the run reaches the start');
	pg_assert_same('42', $seen['mail'][WS_STEP_TRIGGER]['record_id'], 'and every box after it');
	pg_assert_same('1', $seen['if']['read']['found'], 'a read value is handed on');
	pg_assert_false(isset($seen['if']['read']['name']), 'a value the read did not give is left out');
	pg_assert_same('yes', $seen['yes']['if']['outcome'], 'the outcome a box left on');
	pg_assert_same('Said.', $seen['yes']['if']['text'], 'and its sentence');
	pg_assert_same('2026-11-22T10:00:00Z', $seen['mail']['wait']['waited_until'], 'the Wait is passed with its moment');
	pg_assert_false(isset($seen['wait']['mail']), 'a box reads only the boxes before it');
	pg_assert_same(array('found' => 1, 'record_id' => 42, 'name' => null), $walk['steps'][1]['data'], 'a step keeps what it handed on');
}

// A task takes the outcome chosen for it; a box that would fail, on no
// failed arrow, stops its branch and the run would fail.
function test_workspace_flow_trial_walk_chosen_and_failed()
{
	$graph = workspace_flow_trial_test_graph(
		array('go' => 'manual', 't1' => 'task', 'thanks' => 'post', 'sorry' => 'email', 'after' => 'post', 'stop' => 'end'),
		array(array('go', 't1', 'next'), array('t1', 'thanks', 'done'), array('t1', 'sorry', 'cancelled'), array('sorry', 'after', 'done'), array('after', 'stop', 'done'))
	);
	$answer = function ($node) {
		if ($node['kind'] === 'email') {
			return workspace_flow_trial_test_tried('failed', array('refusal' => 'No address.'));
		}

		return workspace_flow_trial_test_tried(($node['kind'] === 'manual') ? 'next' : (($node['kind'] === 'task') ? '' : 'done'));
	};

	$first = ws_flow_trial_walk($graph, ws_flow_graph_node($graph, 'go'), array(), array(), $answer);

	pg_assert_same(array('go', 't1', 'thanks'), array_column($first['steps'], 'node_id'), 'the task is done at first');
	pg_assert_same(array('done', 'cancelled', 'timeout'), $first['steps'][1]['choices']);
	pg_assert_same('done', $first['state']);

	$chosen = ws_flow_trial_walk($graph, ws_flow_graph_node($graph, 'go'), array(), array('t1' => 'cancelled'), $answer);

	pg_assert_same(array('go', 't1', 'sorry'), array_column($chosen['steps'], 'node_id'), 'cancelled: the e-mail, which would fail');
	pg_assert_same('failed', $chosen['steps'][2]['status']);
	pg_assert_same(array('thanks', 'after', 'stop'), $chosen['skipped'], 'nothing after the failure is reached');
	pg_assert_same('failed', $chosen['state'], 'the run would fail');
}

// A start the try refuses walks nothing further.
function test_workspace_flow_trial_walk_start_refused()
{
	$graph = workspace_flow_trial_test_graph(array('go' => 'event', 'say' => 'post'), array(array('go', 'say', 'next')));

	$walk = ws_flow_trial_walk($graph, ws_flow_graph_node($graph, 'go'), array(), array(), function () {
		return workspace_flow_trial_test_tried('', array('refusal' => 'Choose the event.'));
	});

	pg_assert_same(array('go'), array_column($walk['steps'], 'node_id'));
	pg_assert_same(array('say'), $walk['skipped']);
	pg_assert_same('failed', $walk['state']);
}

// A trial never sends, writes or calls: each box is answered by its try, a
// box that reads reads, and the run it stands in for is never written.
function test_workspace_flow_trial_writes_nothing()
{
	$sources = array();

	foreach (array('ws_flow_trial', 'ws_flow_trial_walk', 'ws_flow_trial_data', 'ws_flow_trial_record', 'ws_flow_trial_settle') as $name) {
		$sources[$name] = workspace_flow_trial_test_source($name);

		foreach (array('db(', 'db_q(', 'INSERT', 'UPDATE', 'DELETE', 'ws_scheduled_do(', 'ws_scheduled_execute(', 'ws_flow_step_settle(', 'ws_step_run(', 'ws_message_send(', 'log_activity(', 'ws_flow_run_start(') as $call) {
			pg_assert_true(strpos($sources[$name], $call) === false, $name . ' has no ' . $call);
		}
	}

	pg_assert_same(3, preg_match_all('/ws_step_[a-z_]+_do\(/', $sources['ws_flow_trial_data'], $done), 'only the reading boxes are carried out');
	pg_assert_same(array('ws_step_record_get_do(', 'ws_step_row_get_do(', 'ws_step_rows_get_do('), $done[0]);
	pg_assert_contains('ws_flow_records_window_try($flow)', $sources['ws_flow_trial_data'], 'a list page read moves no window');
	pg_assert_contains('ws_flow_box_try($viewer, $flow', $sources['ws_flow_trial'], 'each box answered as its try');
	pg_assert_contains("'trial'   => array('run' => \$record['run'], 'values' => \$values, 'fill' => \$fill)", $sources['ws_flow_trial']);
	pg_assert_contains('ws_flow_graph_of($flow)', $sources['ws_flow_trial'], 'the draft');
	pg_assert_contains('ws_flow_walk($graph, $state)', $sources['ws_flow_trial_walk'], 'walked as a run walks');
	pg_assert_contains('ws_flow_run_for($viewer', $sources['ws_flow_trial_record'], 'an earlier run the person reaches');
	pg_assert_contains('ws_flow_run_contact_check($viewer', $sources['ws_flow_trial_record'], 'a customer the person reaches');
}

// Only the owner tries a flow, refused before anything is read.
function test_workspace_flow_trial_is_the_owners()
{
	$trial = workspace_flow_trial_test_source('ws_flow_trial');

	pg_assert_contains("((string) (\$flow['access'] ?? '') !== 'edit') || !ws_flow_share_manager(\$viewer, \$flow)", $trial);
	pg_assert_true(strpos($trial, 'ws_flow_share_manager(') < strpos($trial, 'ws_change_viewer_for_id('), 'refused before the owner is read');
	pg_assert_true(strpos($trial, 'ws_flow_share_manager(') < strpos($trial, 'ws_flow_trial_record('), 'refused before a run or a customer is read');
	pg_assert_contains('ws_flow_builder($owner)', $trial, 'an owner still on the staff');
	pg_assert_contains("ws_flow_refusal(\$problem, 422, 'outcomes')", $trial, 'outcomes that do not fit are refused');
}

// A box's try takes the trial's values only from the trial: neither the
// panel action nor the API route lets a request send them.
function test_workspace_flow_trial_box_try_takes_the_trial()
{
	$try = workspace_flow_trial_test_source('ws_flow_box_try');

	pg_assert_contains("\$trial = is_array(\$data['trial'] ?? null) ? \$data['trial'] : null;", $try);
	pg_assert_contains('$known = ($run_id > 0) || ($trial !== null);', $try);
	pg_assert_false(strpos($try, '$run_id <= 0') !== false, 'a trial reads as a chosen run does');
	pg_assert_contains('ws_flow_condition_value_holds($check[\'rule\'], $values, $graph)', $try, 'a rule on a value reads the values known');
	pg_assert_contains("case 'web':", $try, 'a web address start answers');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$case = substr($actions, strpos($actions, "case 'ws_flow_box_try':"), 900);

	pg_assert_false(strpos(substr($case, 0, strpos($case, 'return ws_action_ok')), 'trial') !== false, 'the panel sends no trial');
	pg_assert_false(strpos(workspace_flow_trial_test_source('ws_api_flows_box_try'), 'trial') !== false, 'nor does the API');
}

// The canvas and the API ask the same function; the route is a read, no
// MCP tool, and refused to the application an assistant works through.
function test_workspace_flow_trial_offered()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_contains("case 'ws_flow_trial':", $actions);
	pg_assert_contains("'ws_flow_box_try', 'ws_flow_trial',", $actions, 'it answers with the Workspace off, as a box\'s try does');
	pg_assert_contains("'ws_flow_box_try', 'ws_flow_trial',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'), 'the panel lets it through');

	$api = workspace_flow_trial_test_source('ws_api_flows_trial');

	pg_assert_true(strpos($api, 'ws_api_is_assistant()') < strpos($api, 'ws_api_viewer()'), 'an assistant is refused first');
	pg_assert_contains("api_fail(403, 'forbidden'", $api);
	pg_assert_true(strpos($api, 'ws_api_flow_run_reachable(') < strpos($api, 'ws_flow_trial('), 'the run is checked before it is read');
	pg_assert_contains('ws_api_flow_editable($viewer', $api);

	$routes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
	$route = substr($routes, strpos($routes, "'id'          => 'workspace.flows.trial'"), 600);

	pg_assert_contains("'method'      => 'GET'", $route);
	pg_assert_contains("'path'        => '/workspace/flows/{id}/trial'", $route);
	pg_assert_contains("'mcp'         => false", $route);
	pg_assert_contains("'returns'     => 'WorkspaceFlowTrial'", $route);
	pg_assert_contains("'WorkspaceFlowTrial'        => 'ws_api_flow_trial_schema'", $routes);
}

// The API's step hands out what the trial says.
function test_workspace_flow_trial_api_present()
{
	$step = array('node_id' => 't1', 'kind' => 'task', 'label' => 'Task', 'name' => 'Call back', 'status' => 'done', 'outcome' => '', 'choices' => array('done', 'cancelled'),
		'said' => 'Opens the task.', 'refusal' => '', 'texts' => array(array('key' => 'title', 'text' => 'Call')), 'empty' => array(), 'next' => array(), 'values' => array('found' => 1));
	$shown = ws_api_flow_trial_present(array('record' => array('type' => 'contact', 'id' => 7), 'state' => 'done', 'steps' => array($step), 'skipped' => array('no')));

	pg_assert_same(array_keys(ws_api_flow_trial_schema()), array_keys($shown));
	pg_assert_same(array_keys(ws_api_flow_trial_step_schema()), array_keys($shown['steps'][0]));
	pg_assert_same('contact', $shown['record_type']);
	pg_assert_same(7, $shown['record_id']);
	pg_assert_same(null, $shown['steps'][0]['outcome'], 'no outcome is null');
	pg_assert_same(null, $shown['steps'][0]['refusal']);
	pg_assert_same(array('found' => '1'), (array) $shown['steps'][0]['values']);
	pg_assert_same(null, ws_api_flow_trial_present(array('record' => null, 'state' => 'failed', 'steps' => array(), 'skipped' => array()))['record_id']);
}
