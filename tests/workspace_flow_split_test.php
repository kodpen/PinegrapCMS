<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Split box of flows (includes/workspace/flow_split.php): its
 * arms as kept, what it still lacks, the arm picked by the shares, its
 * outcomes and arrows, and, read from the source, that the run keeps the
 * arm on the step and the trial lets the person choose one.
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

// The arms as kept: a name of one line (the arm's letter when empty), a
// whole share from 0 to 100, at most five arms.
function test_workspace_flow_split_normalize()
{
	pg_assert_same(array('arms' => array()), ws_flow_split_normalize(array()));
	pg_assert_same(array('arms' => array()), ws_flow_split_normalize(array('arms' => 'A')));

	$kept = ws_flow_split_normalize(array('arms' => array(
		array('name' => ' Short subject ', 'share' => '30'),
		array('name' => '', 'share' => 70.6),
		array('share' => -4),
		array('name' => str_repeat('x', 80), 'share' => 250),
		'not an arm',
		array('name' => 'F', 'share' => 'many'),
		array('name' => 'G', 'share' => 1),
	)));

	pg_assert_same(5, count($kept['arms']));
	pg_assert_same(array('name' => 'Short subject', 'share' => 30), $kept['arms'][0]);
	pg_assert_same(array('name' => 'B', 'share' => 70), $kept['arms'][1]);
	pg_assert_same(array('name' => 'C', 'share' => 0), $kept['arms'][2]);
	pg_assert_same(60, strlen($kept['arms'][3]['name']));
	pg_assert_same(100, $kept['arms'][3]['share']);
	pg_assert_same(array('name' => 'F', 'share' => 0), $kept['arms'][4]);
}

// What it lacks: two arms at least, every share above 0, shares adding up to
// 100, every arm its own name.
function test_workspace_flow_split_problems()
{
	$problems = function ($arms) {
		return ws_flow_split_problems(ws_flow_split_normalize(array('arms' => $arms)), '“Split”');
	};

	pg_assert_same(array(), $problems(array(array('name' => 'A', 'share' => 50), array('name' => 'B', 'share' => 50))));
	pg_assert_same(array(), $problems(array(array('name' => 'A', 'share' => 20), array('name' => 'B', 'share' => 30), array('name' => 'C', 'share' => 50))));
	pg_assert_same(1, count($problems(array(array('name' => 'A', 'share' => 100)))));
	pg_assert_same(1, count($problems(array(array('name' => 'A', 'share' => 60), array('name' => 'B', 'share' => 50)))));
	pg_assert_same(1, count($problems(array(array('name' => 'A', 'share' => 100), array('name' => 'B', 'share' => 0)))));
	pg_assert_same(1, count($problems(array(array('name' => 'Red', 'share' => 50), array('name' => 'red', 'share' => 50)))));
	pg_assert_true(strpos(implode(' ', $problems(array())), '“Split”') !== false, 'the sentence names the box');
}

// The arm picked: a roll from 0 below the shares' total falls in the arm
// whose share covers it, in order.
function test_workspace_flow_split_pick()
{
	$arms = array(array('name' => 'A', 'share' => 20), array('name' => 'B', 'share' => 30), array('name' => 'C', 'share' => 50));

	pg_assert_same(0, ws_flow_split_pick($arms, 0));
	pg_assert_same(0, ws_flow_split_pick($arms, 19));
	pg_assert_same(1, ws_flow_split_pick($arms, 20));
	pg_assert_same(1, ws_flow_split_pick($arms, 49));
	pg_assert_same(2, ws_flow_split_pick($arms, 50));
	pg_assert_same(2, ws_flow_split_pick($arms, 99));
	// A roll out of range is brought into it; an arm of 0 is never taken.
	pg_assert_same(2, ws_flow_split_pick($arms, 150));
	pg_assert_same(0, ws_flow_split_pick($arms, -5));
	pg_assert_same(1, ws_flow_split_pick(array(array('name' => 'A', 'share' => 0), array('name' => 'B', 'share' => 100)), 0));
	// No share at all: the first arm; no arm: none.
	pg_assert_same(0, ws_flow_split_pick(array(array('name' => 'A', 'share' => 0), array('name' => 'B', 'share' => 0)), 7));
	pg_assert_same(-1, ws_flow_split_pick(array(), 7));

	// Over many rolls each arm is taken by its share.
	$taken = array(0, 0, 0);

	for ($roll = 0; $roll < 100; $roll++) {
		$taken[ws_flow_split_pick($arms, $roll)]++;
	}

	pg_assert_same(array(20, 30, 50), $taken);
}

// Its outcomes: arm_1 to arm_5 for the kind; per box one for each arm, by its
// place, and always.
function test_workspace_flow_split_outcomes()
{
	pg_assert_same(array('arm_1', 'arm_2', 'arm_3', 'arm_4', 'arm_5'), ws_flow_split_outcomes());

	$config = ws_flow_split_normalize(array('arms' => array(array('name' => 'A', 'share' => 50), array('name' => 'B', 'share' => 50))));
	$kinds = ws_flow_kinds();

	pg_assert_same(array('arm_1', 'arm_2', 'always'), ws_flow_split_arrow_outcomes($config));
	pg_assert_same(array('arm_1', 'arm_2', 'always'), ws_flow_node_arrow_outcomes($kinds['split'], array('kind' => 'split', 'config' => $config)));
	pg_assert_same(ws_flow_split_outcomes(), $kinds['split']['outcomes']);
	pg_assert_true(empty($kinds['split']['step']), 'a split is no step of a scheduled action');
	pg_assert_same(1, ws_flow_split_arm_index('arm_2'));
	pg_assert_same(-1, ws_flow_split_arm_index('arm_9'));
	pg_assert_same(-1, ws_flow_split_arm_index('done'));
	pg_assert_same('B', ws_flow_split_arm_name($config, 'arm_2'));
	pg_assert_same('', ws_flow_split_arm_name($config, 'arm_3'));
}

// A graph: an arrow on an arm the box has not is a problem, as are shares
// that do not add up; the trial offers the arms.
function test_workspace_flow_split_graph()
{
	$graph = array('nodes' => array(
		array('id' => 'start', 'kind' => 'manual', 'x' => 0, 'y' => 0, 'config' => array()),
		array('id' => 'ab', 'kind' => 'split', 'x' => 200, 'y' => 0, 'config' => array('arms' => array(array('name' => 'A', 'share' => 50), array('name' => 'B', 'share' => 40)))),
		array('id' => 'tail', 'kind' => 'end', 'x' => 400, 'y' => 0, 'config' => array()),
	), 'edges' => array(
		array('from' => 'start', 'to' => 'ab', 'on' => 'next'),
		array('from' => 'ab', 'to' => 'tail', 'on' => 'arm_3'),
	));
	$codes = array_column(ws_flow_graph_check($graph), 'code');

	pg_assert_true(in_array('bad_outcome', $codes, true), 'an arrow on an arm the box has not');
	pg_assert_true(in_array('bad_config', $codes, true), 'shares that do not add up to 100');
	pg_assert_true(in_array('split', ws_flow_trial_person_kinds(), true), 'the trial lets the person choose the arm');
	pg_assert_same(array('arm_1', 'arm_2'), ws_flow_trial_choices(ws_flow_graph_normalize($graph)['nodes'][1]));
}

// The run picks the arm once and keeps it as the step's outcome with its
// name; it is a box of its own in the runner's switch.
function test_workspace_flow_split_run_keeps_the_arm()
{
	$function = new ReflectionFunction('ws_flow_split_run');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_true(strpos($source, 'random_int(') !== false, 'the arm is picked at random');
	pg_assert_true(strpos($source, 'ws_flow_step_settle(') !== false, 'the arm is kept on the step');
	pg_assert_true(strpos($source, "'arm'") !== false, 'the arm\'s name is a value');

	$runner = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');

	pg_assert_true(strpos($runner, "case 'split':") !== false, 'the runner runs a split');
}
