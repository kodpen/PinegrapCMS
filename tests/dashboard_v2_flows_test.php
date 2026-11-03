<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Flows card
 * (includes/dashboard/v2/cards/flows.php): box states, the path of the run
 * going on now and the last runs' rows with their buttons.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_DASHBOARD_V2')) {
	define('PG_DASHBOARD_V2', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/flows.php');

function test_dashboard_v2_flow_node_states()
{
	pg_assert_same('todo', pg_dashboard_v2_flow_node_state(null), 'not reached');
	pg_assert_same('done', pg_dashboard_v2_flow_node_state('done'), 'done');
	pg_assert_same('done', pg_dashboard_v2_flow_node_state('skipped'), 'a skipped box is behind the run');
	pg_assert_same('failed', pg_dashboard_v2_flow_node_state('failed'), 'failed');
	pg_assert_same('waiting', pg_dashboard_v2_flow_node_state('ready'), 'ready waits');
	pg_assert_same('waiting', pg_dashboard_v2_flow_node_state('waiting'), 'waiting');
}

function test_dashboard_v2_flow_path_follows_the_arrow_taken()
{
	$graph = array(
		'nodes' => array(
			array('id' => 's', 'label' => 'Form arrived', 'kind' => 'form'),
			array('id' => 'c', 'label' => '', 'kind' => 'condition'),
			array('id' => 'x', 'label' => 'Other way', 'kind' => 'post'),
			array('id' => 'a', 'label' => 'Approval', 'kind' => 'approval'),
			array('id' => 't', 'label' => 'Open task', 'kind' => 'task'),
		),
		'edges' => array(
			array('from' => 's', 'to' => 'c'),
			array('from' => 'c', 'to' => 'x'),
			array('from' => 'c', 'to' => 'a'),
			array('from' => 'a', 'to' => 't'),
			array('from' => 't', 'to' => 's'),
		),
	);

	$path = pg_dashboard_v2_flow_path($graph, 's', array('s' => 'done', 'c' => 'done', 'a' => 'waiting'));

	pg_assert_same(array('s', 'c', 'a', 't'), array_column($path, 'id'), 'the taken arrow, a loop stops');
	pg_assert_same(array('done', 'done', 'waiting', 'todo'), array_column($path, 'state'), 'states');
	pg_assert_same('c', $path[1]['name'], 'a box without a label shows its id');

	pg_assert_same(array('s', 'c', 'x'), array_column(pg_dashboard_v2_flow_path($graph, 's', array('s' => 'done')), 'id'), 'not reached: the first arrow drawn');
	pg_assert_same(array(), pg_dashboard_v2_flow_path($graph, 'missing', array()), 'no start box');
}

function test_dashboard_v2_flow_path_window_keeps_the_box_before()
{
	$nodes = array();
	$edges = array();
	$steps = array();
	for ($i = 1; $i <= 8; $i++) {
		$nodes[] = array('id' => 'n' . $i, 'label' => 'Box ' . $i, 'kind' => 'post');
		if ($i > 1) {
			$edges[] = array('from' => 'n' . ($i - 1), 'to' => 'n' . $i);
		}
		if ($i <= 5) {
			$steps['n' . $i] = ($i === 5) ? 'running' : 'done';
		}
	}

	$path = pg_dashboard_v2_flow_path(array('nodes' => $nodes, 'edges' => $edges), 'n1', $steps, 5);

	pg_assert_same(array('n4', 'n5', 'n6', 'n7', 'n8'), array_column($path, 'id'), 'five boxes from the one before the current');
}

function test_dashboard_v2_flow_run_rows_offer_one_button()
{
	$run = array('id' => 7, 'flow_id' => 3, 'status' => 'failed');

	$row = pg_dashboard_v2_flow_run_row($run, 'Stock alert', 0, array('node_id' => 'm1', 'name' => 'Linear'), true, '1 hour ago');
	pg_assert_same('bad', $row['tone'], 'failed is bad');
	pg_assert_same('Failed at Linear', $row['note'], 'where it failed');
	pg_assert_same(array('kind' => 'retry', 'label' => 'Try again', 'run_id' => 7, 'node_id' => 'm1'), $row['action'], 'the owner tries again');
	pg_assert_same('workspace_flows.php?flow=3&run=7', $row['url'], 'opens the run');

	pg_assert_same(null, pg_dashboard_v2_flow_run_row($run, 'Stock alert', 0, array('node_id' => 'm1', 'name' => 'Linear'), false, '')['action'], 'only the owner may try again');

	$waiting = pg_dashboard_v2_flow_run_row(array('id' => 8, 'flow_id' => 3, 'status' => 'waiting'), 'Newsletter', 12, array(), false, '');
	pg_assert_same(array('kind' => 'approve', 'label' => 'Approve', 'approval_id' => 12), $waiting['action'], 'an approval asked of the person');
	pg_assert_same('warn', $waiting['tone'], 'waiting on the person');

	pg_assert_same(null, pg_dashboard_v2_flow_run_row(array('id' => 9, 'flow_id' => 3, 'status' => 'waiting'), 'X', 0, array(), true, '')['action'], 'waiting on someone else');
	pg_assert_same('good', pg_dashboard_v2_flow_run_row(array('id' => 10, 'flow_id' => 3, 'status' => 'done'), 'X', 0, array(), true, '')['tone'], 'done');
}
