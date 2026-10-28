<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the value boxes of a flow (includes/workspace/flow_values.php):
 * the constants box keeps rows of a name, a type and a value, gives one
 * value per named row, has no arrows and no run row, and is read by any box
 * through {{node.<id>.<key>}} without a reference problem or a warning; a
 * run fills those values as it fills a settled box's.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A box of the graph.
function ws_flow_values_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// A manual start, a post box reading $body, and a constants box with $rows.
function ws_flow_values_test_graph($body, $rows, $edges = null)
{
	return array(
		'nodes' => array(
			ws_flow_values_test_node('manual_1', 'manual'),
			ws_flow_values_test_node('post_1', 'post', array('channel_id' => 5, 'body' => $body)),
			ws_flow_values_test_node('constants_1', 'constants', array('rows' => $rows)),
		),
		'edges' => $edges ?? array(array('from' => 'manual_1', 'to' => 'post_1', 'on' => 'next')),
	);
}

// The graph's problems as code and node, warnings marked.
function ws_flow_values_test_found($graph)
{
	return array_map(function ($problem) {
		return $problem['code'] . ' ' . $problem['node'] . (!empty($problem['warning']) ? ' warning' : '');
	}, ws_flow_graph_check($graph));
}

// A pure kind with no outcomes, and rows kept as a key, a type and one line.
function test_workspace_flow_constants_kind_and_rows()
{
	$kind = ws_flow_kinds()['constants'];

	pg_assert_true(!empty($kind['pure']) && !$kind['trigger'] && !$kind['step']);
	pg_assert_same(array(), $kind['outcomes']);

	$config = ws_flow_config_normalize('constants', array('rows' => array(
		array('key' => 'VAT Rate', 'type' => 'number', 'value' => '18'),
		array('key' => 'sign', 'type' => 'date', 'value' => "Best,\nThe team"),
		'not a row',
	)));

	pg_assert_same(array(
		array('key' => 'vatrate', 'type' => 'number', 'value' => '18'),
		array('key' => 'sign', 'type' => 'text', 'value' => 'Best, The team'),
	), $config['rows']);
	pg_assert_same(array('rows' => array()), ws_flow_config_normalize('constants', array()));

	$many = ws_flow_constants_normalize(array('rows' => array_fill(0, WS_FLOW_CONSTANTS_MAX + 5, array('key' => 'a', 'type' => 'text', 'value' => ''))));
	pg_assert_same(WS_FLOW_CONSTANTS_MAX, count($many['rows']), 'at most WS_FLOW_CONSTANTS_MAX rows');
}

// One output per row with a name, the first of a name twice; the fields a
// reference may read are those only (no text, outcome, status: it never runs).
function test_workspace_flow_constants_outputs()
{
	$config = ws_flow_constants_normalize(array('rows' => array(
		array('key' => 'rate', 'type' => 'number', 'value' => '18'),
		array('key' => '', 'type' => 'text', 'value' => 'x'),
		array('key' => '9lives', 'type' => 'text', 'value' => 'x'),
		array('key' => 'rate', 'type' => 'text', 'value' => '20'),
	)));

	pg_assert_same(array(array('key' => 'rate', 'type' => 'number', 'label' => 'rate')), ws_flow_constants_outputs($config));
	pg_assert_same(array('rate'), ws_flow_node_fields(ws_flow_values_test_node('constants_1', 'constants', $config), ws_flow_kinds()));
	pg_assert_same(array('constants_1' => array('rate' => '18')), ws_flow_pure_values(array('nodes' => array(
		ws_flow_values_test_node('post_1', 'post', array()),
		ws_flow_values_test_node('constants_1', 'constants', $config),
	))));
}

// The box needs no arrow: no "unreachable"; a reader of it has no reference
// problem nor a warning, wherever it stands; a field it does not give is
// still bad; an arrow into it is a problem; its rows are checked.
function test_workspace_flow_constants_in_the_graph_check()
{
	$rows = array(array('key' => 'rate', 'type' => 'number', 'value' => '18'));

	pg_assert_same(array(), ws_flow_values_test_found(ws_flow_values_test_graph('VAT {{node.constants_1.rate}} %', $rows)), 'a clean graph');
	pg_assert_same(array('bad_reference post_1'), ws_flow_values_test_found(ws_flow_values_test_graph('{{node.constants_1.text}}', $rows)), 'no text: it never runs');

	$into = ws_flow_values_test_found(ws_flow_values_test_graph('{{node.constants_1.rate}}', $rows, array(
		array('from' => 'manual_1', 'to' => 'post_1', 'on' => 'next'),
		array('from' => 'post_1', 'to' => 'constants_1', 'on' => 'done'),
	)));

	pg_assert_same(array('bad_edge constants_1'), $into, 'an arrow into it');

	$from = ws_flow_values_test_found(ws_flow_values_test_graph('Hello', $rows, array(
		array('from' => 'manual_1', 'to' => 'post_1', 'on' => 'next'),
		array('from' => 'constants_1', 'to' => 'post_1', 'on' => 'done'),
	)));

	pg_assert_same(array('bad_outcome constants_1'), $from, 'an arrow out of it has no outcome to leave on');

	pg_assert_same(array('bad_config constants_1', 'bad_config constants_1', 'bad_config constants_1'), ws_flow_values_test_found(ws_flow_values_test_graph('Hello', array(
		array('key' => 'rate', 'type' => 'number', 'value' => 'abc'),
		array('key' => 'rate', 'type' => 'text', 'value' => ''),
		array('key' => '', 'type' => 'text', 'value' => 'x'),
	))), 'not a number, a name twice, no name');
	pg_assert_same(array('bad_config constants_1'), ws_flow_values_test_found(ws_flow_values_test_graph('Hello', array())), 'no row');
}

// A run fills a constant into a text the way it fills a settled box's value.
function test_workspace_flow_constants_fill_a_text()
{
	$graph = ws_flow_graph_normalize(ws_flow_values_test_graph('Hello', array(array('key' => 'rate', 'type' => 'number', 'value' => '18'))));
	$values = ws_flow_pure_values($graph) + array('post_1' => array('text' => 'Posted'));

	pg_assert_same('VAT 18 %, Posted', ws_step_fill_nodes('VAT {{node.constants_1.rate}} %, {{node.post_1.text}}', $values));
}
