<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow's tell choice: the start box keeps when a run tells the
 * flow's owner it ended (every run, only a failed run, never), and which
 * run ends write or update the owner's inbox line, alone and in a chain.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_chain_notice.php');

// A graph of one start box of this kind, its config as sent.
function ws_flow_run_tell_choice_test_graph($kind, $config)
{
	return ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'start', 'kind' => $kind, 'config' => $config),
		array('id' => 't1', 'kind' => 'task', 'config' => array('title' => 'Check', 'tell' => 'never')),
	), 'edges' => array()));
}

// The tell choice of a start box as sent.
function ws_flow_run_tell_choice_test_kept($kind, $config)
{
	return ws_flow_tell_of(ws_flow_run_tell_choice_test_graph($kind, $config));
}

// A start box keeps a known choice; anything else, or none, is every run,
// kept as no key so a graph written before the choice reads the same.
function test_workspace_flow_run_tell_choice_normalize_keeps_or_defaults()
{
	foreach (array('every', 'failures', 'never') as $tell) {
		pg_assert_same($tell, ws_flow_run_tell_choice_test_kept('manual', array('tell' => $tell)));
	}

	pg_assert_same(array('subject' => 'order', 'tell' => 'failures'), ws_flow_run_tell_choice_test_graph('manual', array('subject' => 'order', 'tell' => 'failures'))['nodes'][0]['config']);
	pg_assert_same(array('subject' => 'order'), ws_flow_run_tell_choice_test_graph('manual', array('subject' => 'order', 'tell' => 'every'))['nodes'][0]['config']);
	pg_assert_same(array('subject' => ''), ws_flow_run_tell_choice_test_graph('manual', array())['nodes'][0]['config']);
	pg_assert_same('every', ws_flow_run_tell_choice_test_kept('manual', array('tell' => 'sometimes')));
	pg_assert_same('every', ws_flow_run_tell_choice_test_kept('manual', array('tell' => array('never'))));
	pg_assert_same('every', ws_flow_run_tell_choice_test_kept('manual', array('tell' => true)));
}

// Every start kind keeps it; a box that does not start the flow has none.
function test_workspace_flow_run_tell_choice_every_start_kind()
{
	foreach (ws_flow_kinds() as $kind => $info) {
		if (!empty($info['trigger'])) {
			pg_assert_same('never', ws_flow_run_tell_choice_test_kept($kind, array('tell' => 'never')), $kind);
		}
	}

	pg_assert_false(array_key_exists('tell', ws_flow_run_tell_choice_test_graph('manual', array('tell' => 'never'))['nodes'][1]['config']));
}

// A graph's choice is its start box's; a graph without one tells every run.
function test_workspace_flow_run_tell_choice_of_a_graph()
{
	pg_assert_same('never', ws_flow_tell_of(ws_flow_run_tell_choice_test_graph('schedule', array('tell' => 'never'))));
	pg_assert_same('every', ws_flow_tell_of(ws_flow_run_tell_choice_test_graph('event', array())));
	pg_assert_same('every', ws_flow_tell_of(ws_flow_graph_empty()));
	// A graph kept before the choice existed (not normalized again).
	pg_assert_same('every', ws_flow_tell_of(array('nodes' => array(array('id' => 's', 'kind' => 'manual', 'config' => array())))));
}

// A run alone is told by its own flow's choice.
function test_workspace_flow_run_tell_choice_alone()
{
	pg_assert_true(ws_flow_run_tell_wanted('every', 'every', 'done', false));
	pg_assert_true(ws_flow_run_tell_wanted('every', 'every', 'failed', false));
	pg_assert_false(ws_flow_run_tell_wanted('failures', 'failures', 'done', false));
	pg_assert_true(ws_flow_run_tell_wanted('failures', 'failures', 'failed', false));
	pg_assert_false(ws_flow_run_tell_wanted('never', 'never', 'done', false));
	pg_assert_false(ws_flow_run_tell_wanted('never', 'never', 'failed', false));
}

// A chain's root is a run alone on its line: its own choice.
function test_workspace_flow_run_tell_choice_chain_root()
{
	pg_assert_true(ws_flow_run_tell_wanted('every', 'every', 'done', false));
	pg_assert_false(ws_flow_run_tell_wanted('failures', 'failures', 'done', false));
	pg_assert_false(ws_flow_run_tell_wanted('never', 'never', 'failed', false));
}

// A chained run that goes well lands on the line by the root's choice: only
// every run writes or updates it, so a root set to failures has a line only
// when something in the chain failed.
function test_workspace_flow_run_tell_choice_chained_success_follows_the_root()
{
	foreach (array('every', 'failures') as $own) {
		pg_assert_true(ws_flow_run_tell_wanted($own, 'every', 'done', true), $own);
		pg_assert_false(ws_flow_run_tell_wanted($own, 'failures', 'done', true), $own);
		pg_assert_false(ws_flow_run_tell_wanted($own, 'never', 'done', true), $own);
	}
}

// A failure turns the chain's line whatever the root chose.
function test_workspace_flow_run_tell_choice_chained_failure_always_told()
{
	foreach (array('every', 'failures', 'never') as $root) {
		pg_assert_true(ws_flow_run_tell_wanted('every', $root, 'failed', true), $root);
		pg_assert_true(ws_flow_run_tell_wanted('failures', $root, 'failed', true), $root);
	}
}

// A chained flow set to never stays out of the line, failed or not.
function test_workspace_flow_run_tell_choice_chained_never_stays_out()
{
	foreach (array('every', 'failures', 'never') as $root) {
		pg_assert_false(ws_flow_run_tell_wanted('never', $root, 'done', true), $root);
		pg_assert_false(ws_flow_run_tell_wanted('never', $root, 'failed', true), $root);
	}
}

// The choices are read from each run's own version, and asked before a
// line is written alone or in a chain; a flow set to never is not counted.
function test_workspace_flow_run_tell_choice_wired_into_the_notice()
{
	$source = function ($name) {
		$function = new ReflectionFunction($name);
		$lines = file($function->getFileName());

		return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
	};

	pg_assert_contains('ws_flow_tell_of(ws_flow_run_graph($run))', $source('ws_flow_run_tell_owner'));
	pg_assert_contains('ws_flow_run_tell_wanted($tell, $tell, $state, false)', $source('ws_flow_run_tell_owner'));
	pg_assert_contains('ws_flow_tell_of(ws_flow_run_graph($root_run))', $source('ws_flow_run_tell_chain'));
	pg_assert_contains('ws_flow_run_tell_wanted($own_tell, $root_tell, $state, $root_id !== $run_id)', $source('ws_flow_run_tell_chain'));
	pg_assert_contains("!== 'never'", $source('ws_flow_chain_notice_count'));
}
