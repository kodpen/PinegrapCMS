<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the condition box of the workspace's flows
 * (includes/workspace/flow_conditions.php): its one rule as the store keeps
 * it, what it lacks, how it settles, what a reader who may not change the
 * flow sees of it, and, read from the source, that it is checked with the
 * owner's rights at publish and as it runs.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// A function's source without its comments.
function ws_flow_conditions_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A kind of box that is no start and no step, leaving on yes or no; an
// arrow sent without an outcome leaves on yes.
function test_workspace_flow_condition_is_a_kind()
{
	$kind = ws_flow_kinds()['condition'] ?? null;

	pg_assert_true(is_array($kind));
	pg_assert_same(array('yes', 'no'), $kind['outcomes']);
	pg_assert_false($kind['trigger']);
	pg_assert_false($kind['step'], 'not run by ws_scheduled_do()');

	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'when', 'kind' => 'condition', 'config' => array('rule' => array('type' => 'workday'))),
			array('id' => 'stop', 'kind' => 'end'),
			array('id' => 'other', 'kind' => 'end'),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'when'),
			array('from' => 'when', 'to' => 'stop'),
			array('from' => 'when', 'to' => 'other', 'on' => 'no'),
		),
	));

	pg_assert_same('yes', $graph['edges'][1]['on']);
	pg_assert_same(array(), ws_flow_graph_check($graph));
}

// The rule kept in its shape; the rules that start a flow are not a
// condition's.
function test_workspace_flow_condition_normalize()
{
	pg_assert_same(array('rule' => array('type' => 'workday')), ws_flow_condition_normalize(array('rule' => array('type' => 'workday', 'x' => 1))));
	pg_assert_same(array(1, 3, 7), ws_flow_condition_normalize(array('rule' => array('type' => 'weekdays', 'days' => array('7', 3, 3, 9, 0, 1))))['rule']['days']);
	pg_assert_same(array('type' => 'hours', 'from' => '09:00', 'to' => '18:00'), ws_flow_condition_normalize(array('rule' => array('type' => 'hours', 'from' => ' 09:00', 'to' => '18:00')))['rule']);
	pg_assert_same(array('type' => 'metric', 'metric' => 'tasks_open', 'param' => 4, 'op' => 'gte', 'value' => '12.5'),
		ws_flow_condition_normalize(array('rule' => array('type' => 'metric', 'metric' => 'tasks_open', 'param' => '4', 'op' => 'gte', 'value' => 12.5)))['rule']);
	pg_assert_same(array('type' => 'record', 'record' => 'order', 'id' => 9, 'field' => 'status', 'op' => 'eq', 'value' => 'paid'),
		ws_flow_condition_normalize(array('rule' => array('type' => 'record', 'record' => 'order', 'id' => '9', 'field' => 'status', 'value' => "paid\n")))['rule']);
	pg_assert_same('', ws_flow_condition_normalize(array('rule' => array('type' => 'at')))['rule']['type']);
	pg_assert_same('', ws_flow_condition_normalize('x')['rule']['type']);
	pg_assert_same(ws_flow_condition_normalize(array('rule' => array('type' => 'workday'))), ws_flow_config_normalize('condition', array('rule' => array('type' => 'workday'))));

	$problems = function ($rule) {
		return count(ws_flow_condition_problems(ws_flow_condition_normalize(array('rule' => $rule)), 'C'));
	};

	pg_assert_same(1, $problems(array()));
	pg_assert_same(0, $problems(array('type' => 'workday')));
	pg_assert_same(1, $problems(array('type' => 'weekdays')));
	pg_assert_same(1, $problems(array('type' => 'hours', 'from' => '09:00')));
	pg_assert_same(1, $problems(array('type' => 'metric')));
	pg_assert_same(1, $problems(array('type' => 'record', 'record' => 'order', 'field' => 'status')));
	pg_assert_same(0, $problems(array('type' => 'record', 'record' => 'order', 'id' => 2, 'field' => 'status')));

	pg_assert_false(ws_flow_condition_check(array('id' => 1), ws_flow_condition_normalize(array()))['ok']);
}

// Done either way, leaving on yes or no; a refusal fails the box.
function test_workspace_flow_condition_settles()
{
	pg_assert_same(array('status' => 'done', 'outcome' => 'yes'), ws_flow_condition_settlement(true));
	pg_assert_same(array('status' => 'done', 'outcome' => 'no'), ws_flow_condition_settlement(false));

	$run = ws_flow_conditions_test_source('ws_flow_condition_run');

	pg_assert_contains('ws_flow_condition_check($owner, $node[\'config\'])', $run);
	pg_assert_contains("ws_flow_step_settle(\$step, 'running', 'failed', '', \$check['error'])", $run);
	pg_assert_contains('ws_scheduled_rules_hold($owner, array($check[\'rule\'])', $run);

	// The owner's rights at publish and as it runs.
	pg_assert_contains('ws_flow_condition_check($viewer, $node[\'config\'])', ws_flow_conditions_test_source('ws_flow_publish_check'));
	pg_assert_contains('ws_flow_condition_run($run, $step, $node, $owner)', ws_flow_conditions_test_source('ws_flow_step_run'));
}

// The record a condition looks at and the value it compares with reach only
// whoever may change the flow, on the screen, in the API and in a run; so
// does the sentence a run keeps.
function test_workspace_flow_condition_is_kept_back()
{
	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'start', 'kind' => 'manual'),
		array('id' => 'rec', 'kind' => 'condition', 'config' => array('rule' => array('type' => 'record', 'record' => 'contact', 'id' => 7, 'field' => 'email', 'value' => 'jane@example.com'))),
		array('id' => 'count', 'kind' => 'condition', 'config' => array('rule' => array('type' => 'metric', 'metric' => 'revenue_today', 'op' => 'gt', 'value' => '5000'))),
		array('id' => 'days', 'kind' => 'condition', 'config' => array('rule' => array('type' => 'weekdays', 'days' => array(1, 2)))),
	)));
	$rule = function ($present, $index) {
		return ((array) $present['nodes'][$index]['config'])['rule'];
	};

	$reader = ws_flow_graph_present($graph);
	$editor = ws_flow_graph_present($graph, true);

	pg_assert_same(array('type' => 'record', 'record' => 'contact', 'id' => 0, 'field' => 'email', 'op' => 'eq', 'value' => ''), $rule($reader, 1));
	pg_assert_same('', $rule($reader, 2)['value']);
	pg_assert_same('revenue_today', $rule($reader, 2)['metric']);
	pg_assert_same(array(1, 2), $rule($reader, 3)['days'], 'days and hours as they are');
	pg_assert_same('jane@example.com', $rule($editor, 1)['value']);
	pg_assert_same(7, $rule($editor, 1)['id']);

	$said = 'The condition did not hold: E-mail of “Jane” is “jane@example.com”.';

	pg_assert_same($said, ws_step_result_shown('condition', 'no', $said, true));
	pg_assert_same('The condition did not hold.', ws_step_result_shown('condition', 'no', $said, false));
	pg_assert_same('The condition held.', ws_step_result_shown('condition', 'yes', $said, false));
	pg_assert_same('The condition could not be checked.', ws_step_result_shown('condition', '', 'You may not see records of this kind.', false));
}
