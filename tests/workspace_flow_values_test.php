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

// A derived value box's set-up kept: an unknown rule is join, the
// comparison, places and date shape within their lists, inputs one line.
function test_workspace_flow_derived_kind_and_config()
{
	$kind = ws_flow_kinds()['value'];

	pg_assert_true(!empty($kind['pure']), 'a value box');
	pg_assert_same(array(), $kind['outcomes'], 'no outcomes');
	pg_assert_same(array('rule' => 'join', 'a' => '', 'b' => '', 'op' => 'eq', 'places' => 0, 'format' => 'Y-m-d'), ws_flow_config_normalize('value', array()), 'empty');
	pg_assert_same(
		array('rule' => 'round', 'a' => '{{node.web_1.ms}} ms', 'b' => 'x', 'op' => 'eq', 'places' => 6, 'format' => 'Y-m-d'),
		ws_flow_config_normalize('value', array('rule' => 'round', 'a' => "{{node.web_1.ms}}\nms", 'b' => 'x', 'op' => 'like', 'places' => 9, 'format' => 'l')),
		'kept within the lists'
	);
	pg_assert_same('join', ws_flow_derived_normalize(array('rule' => 'record_field'))['rule'], 'reading a record is not a rule: it is not pure');
	pg_assert_same(array('value'), ws_flow_node_fields(ws_flow_values_test_node('value_1', 'value', ws_flow_derived_normalize(array('rule' => 'add'))), ws_flow_kinds()));
	pg_assert_same('number', ws_flow_derived_outputs(ws_flow_derived_normalize(array('rule' => 'compare')))[0]['type']);
	pg_assert_same('text', ws_flow_derived_outputs(ws_flow_derived_normalize(array('rule' => 'add_days')))[0]['type']);
}

// Each rule on its inputs as filled; an input the rule cannot read gives an
// empty value, never an error.
function test_workspace_flow_derived_rules()
{
	$value = function ($rule, $a, $b = '', $more = array()) {
		return ws_flow_derived_value(ws_flow_derived_normalize(array('rule' => $rule) + $more), $a, $b);
	};

	pg_assert_same('1', $value('compare', '5', '5.0'), 'numbers equal by value');
	pg_assert_same('1', $value('compare', 'abc', 'ABC'), 'texts as a value condition compares them');
	pg_assert_same('0', $value('compare', 'abc', 'abd'), 'two texts');
	pg_assert_same('1', $value('compare', '12', '9', array('op' => 'gt')), 'more than');
	pg_assert_same('0', $value('compare', 'x', '9', array('op' => 'gt')), 'a word is never more');
	pg_assert_same('1', $value('compare', 'Order shipped', 'SHIP', array('op' => 'contains')), 'contains');
	pg_assert_same('Ada Lovelace', $value('join', ' Ada ', 'Lovelace'), 'join');
	pg_assert_same('Ada', $value('join', 'Ada', ''), 'join with an empty one');
	pg_assert_same('3.5', $value('add', '1.25', '2.25'), 'add');
	pg_assert_same('-7', $value('subtract', '3', '10'), 'subtract');
	pg_assert_same('', $value('add', '3', 'ten'), 'a word is not added');
	pg_assert_same('3.14', $value('round', '3.14159', '', array('places' => 2)), 'round');
	pg_assert_same('3', $value('round', '2.5'), 'round half up');
	pg_assert_same('31.12.2026', $value('date_format', '2026-12-31', '', array('format' => 'd.m.Y')), 'date shape');
	pg_assert_same('12/31/2026 09:05', ws_flow_derived_value(array('rule' => 'date_format', 'format' => 'm/d/Y H:i'), '2026-12-31 09:05', ''), 'a time kept');
	pg_assert_same('', $value('date_format', 'next monday'), 'words are not a date');
	pg_assert_same('', $value('date_format', '2026-02-30'), 'no such day');
	pg_assert_same('2027-01-02', $value('add_days', '2026-12-31', '2'), 'add days across a year');
	pg_assert_same('2026-12-29 08:00', $value('add_days', '2026-12-31 08:00', '-2'), 'take days away, the time kept');
	pg_assert_same('', $value('add_days', '2026-12-31', 'two'), 'days must be a whole number');
}

// The run's values reach a derived box, which may read another one; one that
// reads itself gives an empty value.
function test_workspace_flow_derived_values_in_a_run()
{
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			ws_flow_values_test_node('manual_1', 'manual'),
			ws_flow_values_test_node('post_1', 'post', array('channel_id' => 5, 'body' => '{{node.value_2.value}}')),
			ws_flow_values_test_node('constants_1', 'constants', array('rows' => array(array('key' => 'rate', 'type' => 'number', 'value' => '18')))),
			ws_flow_values_test_node('value_2', 'value', array('rule' => 'round', 'a' => '{{node.value_1.value}}', 'places' => 1)),
			ws_flow_values_test_node('value_1', 'value', array('rule' => 'add', 'a' => '{{node.web_1.ms}}', 'b' => '{{node.constants_1.rate}}')),
			ws_flow_values_test_node('value_3', 'value', array('rule' => 'join', 'a' => '{{trigger.name}}', 'b' => '{{node.value_4.value}}')),
			ws_flow_values_test_node('value_4', 'value', array('rule' => 'join', 'a' => '{{node.value_3.value}}', 'b' => 'x')),
		),
		'edges' => array(),
	));
	$values = ws_flow_pure_values($graph, array('web_1' => array('ms' => '0.06'), WS_STEP_TRIGGER => array('name' => 'Ada')));

	pg_assert_same('18.06', $values['value_1']['value'], 'from a box and a constant');
	pg_assert_same('18.1', $values['value_2']['value'], 'from another derived box, whatever their order');
	pg_assert_same('', $values['value_3']['value'], 'a loop gives nothing');
	pg_assert_same('18', $values['constants_1']['rate'], 'the constants too');
	pg_assert_true(!isset($values['web_1']), 'the run values are not given back');
	pg_assert_same('', ws_flow_pure_values($graph)['value_1']['value'], 'no run: nothing to add');
	pg_assert_same('VAT 18.1', ws_step_fill_nodes('VAT {{node.value_2.value}}', $values));
}

// A derived box has no place among the arrows: its own reads are never "not
// before"; the box that reads it is checked against what it reads, at any
// depth. A loop and a missing input are its own problems.
function test_workspace_flow_derived_in_the_graph_check()
{
	$graph = function ($body, $a, $edges, $more = array()) {
		return array(
			'nodes' => array_merge(array(
				ws_flow_values_test_node('manual_1', 'manual'),
				ws_flow_values_test_node('notify_1', 'notify', array('user_ids' => array(2), 'text' => 'Hi')),
				ws_flow_values_test_node('post_1', 'post', array('channel_id' => 5, 'body' => $body)),
				ws_flow_values_test_node('value_1', 'value', array('rule' => 'join', 'a' => $a, 'b' => 'x')),
			), $more),
			'edges' => $edges,
		);
	};
	$line = array(
		array('from' => 'manual_1', 'to' => 'notify_1', 'on' => 'next'),
		array('from' => 'notify_1', 'to' => 'post_1', 'on' => 'done'),
	);
	$fork = array(
		array('from' => 'manual_1', 'to' => 'notify_1', 'on' => 'next'),
		array('from' => 'manual_1', 'to' => 'post_1', 'on' => 'next'),
	);
	// notify_1 runs only when notify_2 is done; post_1 after either way.
	$either = array(
		array('from' => 'manual_1', 'to' => 'notify_2', 'on' => 'next'),
		array('from' => 'notify_2', 'to' => 'notify_1', 'on' => 'done'),
		array('from' => 'notify_2', 'to' => 'post_1', 'on' => 'always'),
		array('from' => 'notify_1', 'to' => 'post_1', 'on' => 'always'),
	);

	pg_assert_same(array(), ws_flow_values_test_found($graph('{{node.value_1.value}}', '{{node.notify_1.text}}', $line)), 'its input comes before the reader');
	pg_assert_same(array('bad_reference post_1'), ws_flow_values_test_found($graph('{{node.value_1.value}}', '{{node.notify_1.text}}', $fork)), 'its input on another branch');
	pg_assert_same(array(), ws_flow_values_test_found($graph('Hello', '{{node.notify_1.text}}', $fork)), 'nobody reads it: nothing to check');
	pg_assert_same(array('skippable_reference post_1 warning'), ws_flow_values_test_found($graph('{{node.value_1.value}}', '{{node.notify_1.text}}', $either, array(
		ws_flow_values_test_node('notify_2', 'notify', array('user_ids' => array(2), 'text' => 'Hi')),
	))), 'its input may be skipped');
	pg_assert_same(array('bad_reference value_1'), ws_flow_values_test_found($graph('Hello', '{{node.notify_1.nothing}}', $line)), 'a field the box does not give');
	pg_assert_same(array('bad_reference post_1'), ws_flow_values_test_found($graph('{{node.value_1.value}}', '{{node.value_2.value}}', $fork, array(
		ws_flow_values_test_node('value_2', 'value', array('rule' => 'round', 'a' => '{{node.notify_1.text}}')),
	))), 'through a second derived box');
	pg_assert_same(array('bad_config value_1', 'bad_config value_2'), ws_flow_values_test_found($graph('Hello', '{{node.value_2.value}}', $line, array(
		ws_flow_values_test_node('value_2', 'value', array('rule' => 'round', 'a' => '{{node.value_1.value}}')),
	))), 'a loop of derived boxes');
	pg_assert_same(array('bad_config value_1', 'bad_config value_1'), ws_flow_values_test_found(array(
		'nodes' => array(ws_flow_values_test_node('manual_1', 'manual'), ws_flow_values_test_node('value_1', 'value', array('rule' => 'add_days', 'a' => '', 'b' => 'soon'))),
		'edges' => array(),
	)), 'no input, days not a number');
}

// A derived value keeps a length limit: an input citing a value many times,
// through a chain of joins, never grows past WS_FLOW_DERIVED_VALUE_LENGTH.
function test_workspace_flow_derived_value_length_limit()
{
	pg_assert_true(defined('WS_FLOW_DERIVED_VALUE_LENGTH'), 'the limit is a constant');
	$cite = function ($ref, $times) {
		return str_repeat('{{' . $ref . '}}', $times);
	};
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			ws_flow_values_test_node('manual_1', 'manual'),
			ws_flow_values_test_node('c', 'constants', array('rows' => array(array('key' => 'v', 'type' => 'text', 'value' => str_repeat('a', 500))))),
			ws_flow_values_test_node('j1', 'value', array('rule' => 'join', 'a' => $cite('node.c.v', 41), 'b' => $cite('node.c.v', 41))),
			ws_flow_values_test_node('j2', 'value', array('rule' => 'join', 'a' => $cite('node.j1.value', 29), 'b' => $cite('node.j1.value', 29))),
			ws_flow_values_test_node('j3', 'value', array('rule' => 'join', 'a' => $cite('node.j2.value', 29), 'b' => $cite('node.j2.value', 29))),
		),
		'edges' => array(),
	));
	$values = ws_flow_pure_values($graph);

	foreach (array('j1', 'j2', 'j3') as $id) {
		pg_assert_same(WS_FLOW_DERIVED_VALUE_LENGTH, mb_strlen($values[$id]['value']), $id . ' is cut at the limit');
	}

	$rule = function ($rule, $a, $b, $more = array()) {
		return ws_flow_derived_value(ws_flow_derived_normalize(array('rule' => $rule) + $more), $a, $b);
	};
	$half = str_repeat('x', (int) (WS_FLOW_DERIVED_VALUE_LENGTH / 2) - 1);

	pg_assert_same($half . ' ' . $half, $rule('join', $half, $half), 'a join below the limit is whole');
	pg_assert_same('1', $rule('compare', 'abc', 'ABC'), 'compare below the limit');
	pg_assert_same('3.5', $rule('add', '1.25', '2.25'), 'add below the limit');

	$long = str_repeat('ğ', WS_FLOW_DERIVED_VALUE_LENGTH);
	$joined = $rule('join', $long, $long);
	pg_assert_same(WS_FLOW_DERIVED_VALUE_LENGTH, mb_strlen($joined), 'a multibyte join is cut by character');
	pg_assert_true(mb_check_encoding($joined, 'UTF-8'), 'never in the middle of a character');

	$filled = ws_flow_derived_fill('é' . $cite('node.c.v', 3), array('c' => array('v' => $long)));
	pg_assert_same(WS_FLOW_DERIVED_VALUE_LENGTH, mb_strlen($filled), 'a filled input is cut at the limit');
	pg_assert_true(mb_check_encoding($filled, 'UTF-8'), 'the filled input is valid UTF-8');
	pg_assert_same('Hi Ada.', ws_flow_derived_fill('Hi {{trigger.name}}.', array(WS_STEP_TRIGGER => array('name' => 'Ada'))), 'a short input is filled whole');
}
