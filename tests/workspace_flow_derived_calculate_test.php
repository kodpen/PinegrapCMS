<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Calculate rule of a flow's derived value box
 * (includes/workspace/flow_values.php): one expression worked out by the
 * workspace calculator (ws_calc_evaluate()), each {{node.*}} or
 * {{trigger.*}} value read as a number with ws_calc_number() and bound as a
 * name, never pasted into the formula, so a value cannot add an operator;
 * places rounds the result; an empty or unreadable value or a calculator
 * error gives an empty value.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/recurrence.php');

// A calculate box's value from $formula over the run's $values.
function ws_flow_calculate_test_value($formula, $values = array(), $places = 2)
{
	$config = ws_flow_derived_normalize(array('rule' => 'calculate', 'a' => $formula, 'places' => $places));

	return ws_flow_derived_value($config, $formula, '', $values);
}

// The rule is listed as a number of one input, with the places to round to.
function test_workspace_flow_calculate_rule_listed()
{
	$rules = ws_flow_derived_rules();
	pg_assert_same('number', $rules['calculate'] ?? null, 'calculate gives a number');
	pg_assert_true(!in_array('calculate', ws_flow_derived_two_inputs(), true), 'one input');

	$config = ws_flow_derived_normalize(array('rule' => 'calculate', 'a' => '{{trigger.qty}} * 2', 'places' => 3));
	pg_assert_same('calculate', $config['rule']);
	pg_assert_same(3, $config['places']);
	pg_assert_same('number', ws_flow_derived_outputs($config)[0]['type']);

	$lists = ws_flow_derived_lists();
	$keys = array_map(function ($rule) { return $rule['key']; }, $lists['rules']);
	pg_assert_true(in_array('calculate', $keys, true), 'the canvas lists the rule');
	pg_assert_true(!empty($lists['functions']), 'the canvas lists the calculator functions');
	pg_assert_true(isset($lists['functions'][0]['name'], $lists['functions'][0]['help']), 'each with its help');
}

// Operators, percent and functions over the run's values.
function test_workspace_flow_calculate_values()
{
	ws_calc_decimal_comma(false);
	$values = array(
		'form_1'        => array('qty' => '12', 'price' => '1,250.50', 'rate' => '18%'),
		WS_STEP_TRIGGER => array('count' => '3'),
	);

	pg_assert_same('15006', ws_flow_calculate_test_value('{{node.form_1.qty}} * {{node.form_1.price}}', $values), 'a times b');
	pg_assert_same('1475.59', ws_flow_calculate_test_value('{{node.form_1.price}} * (1 + {{node.form_1.rate}})', $values), 'a percent value read as a share');
	pg_assert_same('2.4', ws_flow_calculate_test_value('20% * {{node.form_1.qty}}', $values), 'a typed percent');
	pg_assert_same('15', ws_flow_calculate_test_value('MAX({{node.form_1.qty}}; {{trigger.count}}) + {{trigger.count}}', $values), 'a function and the trigger');
	pg_assert_same('4', ws_flow_calculate_test_value('{{node.form_1.qty}} / {{trigger.count}}', $values, 0), 'a whole number');
	pg_assert_same('3.333', ws_flow_calculate_test_value('10 / 3', array(), 3), 'places round the result');
	pg_assert_same('3', ws_flow_calculate_test_value('10 / 3', array(), 0), 'no places');
	pg_assert_same('1', ws_flow_calculate_test_value('{{node.form_1.qty}} > 10', $values), 'a comparison gives 1');
	pg_assert_same('7', ws_flow_calculate_test_value('{{node.form_1.missing|7}}', $values), 'a fallback value is read too');
}

// A value is a number or nothing: an operator or a formula inside it is never
// worked out, and an unreadable value, an error or a bad formula is empty.
function test_workspace_flow_calculate_values_are_bound_not_pasted()
{
	ws_calc_decimal_comma(false);
	$values = array(WS_STEP_TRIGGER => array('qty' => '2 + 3', 'word' => 'ten', 'zero' => '0', 'five' => '5', 'cell' => 'A1', 'name' => '@L1'));

	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.qty}} * 2', $values), 'an operator in a value is not run');
	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.word}} + 1', $values), 'a word is no number');
	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.cell}} + 1', $values), 'a cell name in a value is no number');
	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.name}} + {{trigger.five}}', $values), 'a slot name in a value is no number');
	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.none}} + 1', $values), 'an empty value');
	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.five}} / {{trigger.zero}}', $values), 'division by zero');
	pg_assert_same('5', ws_flow_calculate_test_value('IFERROR({{trigger.five}} / {{trigger.zero}}; 5)', $values), 'IFERROR steps around it');
	pg_assert_same('', ws_flow_calculate_test_value('{{trigger.five}} +* 2', $values), 'a formula it cannot read');
	pg_assert_same('', ws_flow_calculate_test_value('A1 + 1', $values), 'no cells in a flow');
	pg_assert_same('', ws_flow_calculate_test_value('@L0 + 1', $values), 'no slot written by hand');
	pg_assert_same('', ws_flow_calculate_test_value('', $values), 'no formula');
}

// In a run the box reads other boxes' values, constants and derived boxes,
// and a box reading it gets the number.
function test_workspace_flow_calculate_in_a_run()
{
	ws_calc_decimal_comma(false);
	$node = function ($id, $kind, $config = array()) {
		return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
	};
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			$node('manual_1', 'manual'),
			$node('constants_1', 'constants', array('rows' => array(array('key' => 'rate', 'type' => 'number', 'value' => '18')))),
			$node('value_1', 'value', array('rule' => 'calculate', 'a' => '{{trigger.qty}} * {{trigger.price}} * (1 + {{node.constants_1.rate}} / 100)', 'places' => 2)),
			$node('value_2', 'value', array('rule' => 'calculate', 'a' => 'ROUND({{node.value_1.value}}; 0)', 'places' => 0)),
		),
		'edges' => array(),
	));
	$values = ws_flow_pure_values($graph, array(WS_STEP_TRIGGER => array('qty' => '3', 'price' => '9.99')));

	pg_assert_same('35.36', $values['value_1']['value']);
	pg_assert_same('35', $values['value_2']['value'], 'from another derived box');
	pg_assert_same('', ws_flow_pure_values($graph)['value_1']['value'], 'no run: nothing to read');
}

// The check names a formula the calculator cannot read.
function test_workspace_flow_calculate_problems()
{
	$problems = function ($formula) {
		return ws_flow_derived_problems(ws_flow_derived_normalize(array('rule' => 'calculate', 'a' => $formula)), 'Total');
	};

	pg_assert_same(array(), $problems('{{trigger.qty}} * 2'), 'a good formula');
	pg_assert_same(array(), $problems('MAX({{node.form_1.qty}}; 3)'), 'with a function');
	pg_assert_same(1, count($problems('{{trigger.qty}} +* 2')), 'cannot be read');
	pg_assert_same(1, count($problems('qty * 2')), 'a name nobody gave');
	pg_assert_same(1, count($problems('')), 'no formula');
}
