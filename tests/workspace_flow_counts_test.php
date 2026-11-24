<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the counts of a flow's boxes (includes/workspace/flow_counts.php):
 * the days read, the one grouped read over the runs and their boxes, the
 * rows folded per box, the runs list narrowed to a box and its state; and,
 * read from the source, that the rights are those of the runs list and that
 * the panel and the API reach it.
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
function workspace_flow_counts_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The days: 7 when none are sent, whole days between 1 and the days runs
// are kept.
function test_workspace_flow_counts_days()
{
	$keep = (int) (WS_FLOW_RUNS_KEEP / 86400);

	pg_assert_same(7, ws_flow_counts_days(null));
	pg_assert_same(7, ws_flow_counts_days(''));
	pg_assert_same(7, ws_flow_counts_days(0));
	pg_assert_same(1, ws_flow_counts_days(-3));
	pg_assert_same(14, ws_flow_counts_days('14'));
	pg_assert_same($keep, ws_flow_counts_days(999));
	pg_assert_same(7, ws_flow_counts_days('soon'));
}

// One grouped read: the flow's runs by idx_flow, their boxes joined, grouped
// by box; waiting counts runs, passed and failed count the boxes finished
// since the moment.
function test_workspace_flow_counts_sql()
{
	$sql = ws_flow_counts_sql(12, 1700000000);

	pg_assert_true(strpos($sql, 'FROM ws_flow_runs') !== false);
	pg_assert_true(strpos($sql, 'USE INDEX (idx_flow)') !== false, 'the runs are read by idx_flow');
	pg_assert_true(strpos($sql, 'JOIN ws_flow_run_steps') !== false);
	pg_assert_true(strpos($sql, "flow_id = '12'") !== false);
	pg_assert_true(strpos($sql, 'GROUP BY s.node_id') !== false);
	pg_assert_true(strpos($sql, 'COUNT(DISTINCT') !== false, 'waiting counts runs, not passes');
	pg_assert_same(3, substr_count($sql, "'1700000000'"), 'the moment bounds the runs read, the passed and the failed');
	pg_assert_true(strpos($sql, 'run_id IN') === false, 'no narrowing without run ids');

	$narrow = ws_flow_counts_sql(12, 1700000000, array(5, '9', 0, -1));
	pg_assert_true(strpos($narrow, "r.id IN ('5','9')") !== false, 'only the runs the person reaches');

	pg_assert_same('', ws_flow_counts_sql(12, 1700000000, array()), 'no run reached, nothing read');
	pg_assert_same('', ws_flow_counts_sql(0, 1700000000), 'no flow');
}

// The grouped rows folded per box: whole numbers, boxes with nothing left
// out, a box id that is no text dropped.
function test_workspace_flow_counts_fold()
{
	pg_assert_same(array(), ws_flow_counts_fold(array()));
	pg_assert_same(array(), ws_flow_counts_fold(null));

	pg_assert_same(
		array(
			array('node' => 'a', 'waiting' => 2, 'passed' => 10, 'failed' => 1),
			array('node' => 'b', 'waiting' => 0, 'passed' => 0, 'failed' => 3),
		),
		ws_flow_counts_fold(array(
			array('node_id' => 'a', 'waiting' => '2', 'passed' => '10', 'failed' => '1'),
			array('node_id' => 'b', 'waiting' => '0', 'passed' => null, 'failed' => '3'),
			array('node_id' => 'c', 'waiting' => '0', 'passed' => '0', 'failed' => '0'),
			array('node_id' => '', 'waiting' => '4', 'passed' => '0', 'failed' => '0'),
		))
	);
}

// The runs list narrowed to a box: waiting now, passed or failed since the
// moment; an unknown state or a box id that cannot be one narrows nothing.
function test_workspace_flow_counts_runs_where()
{
	$waiting = ws_flow_runs_node_where('t1', 'waiting', 0);
	pg_assert_true(strpos($waiting, 'EXISTS (SELECT 1 FROM ws_flow_run_steps') !== false);
	pg_assert_true(strpos($waiting, "s.node_id = 't1'") !== false);
	pg_assert_true(strpos($waiting, "s.status = 'waiting'") !== false);
	pg_assert_true(strpos($waiting, "status IN ('running', 'waiting')") !== false, 'waiting now: the run is still going');

	$passed = ws_flow_runs_node_where('t1', 'passed', 1700000000);
	pg_assert_true(strpos($passed, "s.status = 'done'") !== false);
	pg_assert_true(strpos($passed, "s.finished_at >= '1700000000'") !== false);

	$failed = ws_flow_runs_node_where('t1', 'failed', 0);
	pg_assert_true(strpos($failed, "s.status = 'failed'") !== false);
	pg_assert_true(strpos($failed, 'finished_at >=') === false, 'no moment, any time');

	pg_assert_same('', ws_flow_runs_node_where('t1', 'done', 0), 'not a state of the counts');
	pg_assert_same('', ws_flow_runs_node_where('', 'waiting', 0));
	pg_assert_same('', ws_flow_runs_node_where("t1' OR '1", 'waiting', 0), 'a box id is a token');
	pg_assert_same('', ws_flow_runs_node_where(str_repeat('a', 33), 'waiting', 0), 'longer than the column');
	pg_assert_same(array('waiting', 'passed', 'failed'), ws_flow_counts_states());
}

// The runs list honours node and state.
function test_workspace_flow_counts_runs_list_filters()
{
	$source = workspace_flow_counts_test_source('ws_flow_runs_list');

	pg_assert_true(strpos($source, 'ws_flow_runs_node_where(') !== false);
	pg_assert_true(strpos($source, "\$filters['node']") !== false);
	pg_assert_true(strpos($source, "\$filters['state']") !== false);
}

// Rights as the runs list: an editor's counts are one read over every run,
// anybody else's over the runs ws_flow_run_access() lets them read.
function test_workspace_flow_counts_rights()
{
	$source = workspace_flow_counts_test_source('ws_flow_counts');

	pg_assert_true(strpos($source, 'ws_flow_access(') !== false);
	pg_assert_true(strpos($source, 'ws_flow_run_access(') !== false);
	pg_assert_true(strpos($source, 'ws_flow_counts_sql(') !== false);
	pg_assert_true(strpos($source, 'WS_FLOW_COUNTS_SCAN') !== false, 'the runs looked at one by one are bounded');
	pg_assert_true(strpos($source, 'INSERT') === false && strpos($source, 'UPDATE') === false, 'nothing written');
}

// The panel action and the API route.
function test_workspace_flow_counts_reached()
{
	pg_assert_true(in_array('ws_flow_counts', ws_flow_actions_without_workspace(), true), 'flows run with the Workspace off');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_true(strpos($panel, "'ws_flow_counts'") !== false, 'in the panel\'s action list');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_true(strpos($actions, "case 'ws_flow_counts':") !== false);

	$route = null;

	foreach (ws_api_routes() as $row) {
		if (($row['path'] ?? '') === '/workspace/flows/{id}/counts') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'GET /workspace/flows/{id}/counts');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('workspace:read', $route['scope']);
	pg_assert_same('ws_api_flow_counts', $route['handler']);

	$api = workspace_flow_counts_test_source('ws_api_flow_counts');
	pg_assert_true(strpos($api, 'ws_api_flow_or_404(') !== false, 'a flow the application reaches');
	pg_assert_true(strpos($api, 'ws_api_flow_run_reachable(') !== false, 'runs as the runs list reaches them');
}

// The runs behind a count, each with the box's pass it is listed for: the
// last pass in the count's state (a loop passes a box more than once), its
// times as the API writes them; a run with no such pass has no line.
function test_workspace_flow_counts_at_fold()
{
	$rows = array(
		array('run_id' => 4, 'pass' => 0, 'status' => 'done', 'ready_at' => 100, 'wake_at' => 0, 'finished_at' => 160),
		array('run_id' => 4, 'pass' => 1, 'status' => 'done', 'ready_at' => 200, 'wake_at' => 0, 'finished_at' => 260),
		array('run_id' => 4, 'pass' => 2, 'status' => 'waiting', 'ready_at' => 300, 'wake_at' => 900, 'finished_at' => 0),
		array('run_id' => 7, 'pass' => 0, 'status' => 'failed', 'ready_at' => 50, 'wake_at' => 0, 'finished_at' => 70),
	);

	pg_assert_same(array(4 => array('status' => 'done', 'since' => ws_flow_time(200), 'wake_at' => null, 'finished_at' => ws_flow_time(260))), ws_flow_counts_at_fold($rows, 'passed'), 'the last pass done');
	pg_assert_same(array(4 => array('status' => 'waiting', 'since' => ws_flow_time(300), 'wake_at' => ws_flow_time(900), 'finished_at' => null)), ws_flow_counts_at_fold($rows, 'waiting'), 'the pass waiting now');
	pg_assert_same(array(7), array_keys(ws_flow_counts_at_fold($rows, 'failed')), 'only the run that failed there');
	pg_assert_same(array(), ws_flow_counts_at_fold($rows, 'other'), 'another state: none');
	pg_assert_same(array(), ws_flow_counts_at_fold(null, 'passed'));
}

// A contact's name as a run about them is listed: the name, else the
// company, else the e-mail address.
function test_workspace_flow_counts_contact_name()
{
	pg_assert_same('Ayşe Demir', ws_flow_contact_name(array('first_name' => 'Ayşe', 'last_name' => 'Demir', 'company' => 'Kodpen', 'email_address' => 'a@example.com')));
	pg_assert_same('Kodpen', ws_flow_contact_name(array('first_name' => '', 'last_name' => '', 'company' => ' Kodpen ', 'email_address' => 'a@example.com')));
	pg_assert_same('a@example.com', ws_flow_contact_name(array('first_name' => '', 'last_name' => '', 'company' => '', 'email_address' => 'a@example.com')));
	pg_assert_same('', ws_flow_contact_name(array()));
}

// The runs list of a count carries, per run, the box's pass and the name
// of the contact it is about, read only for the contacts the person reads.
function test_workspace_flow_counts_runs_list_lines()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_true(strpos($actions, 'ws_flow_runs_at_node(') !== false, 'the box\'s pass of each run listed');
	pg_assert_true(strpos($actions, 'ws_flow_runs_about(') !== false, 'the record\'s name of each run listed');

	$about = workspace_flow_counts_test_source('ws_flow_runs_about');
	pg_assert_true(strpos($about, 'ws_viewer_contacts_open(') !== false, 'only the contacts the person may open');
	pg_assert_true(strpos($about, 'ws_flow_run_contact_readable(') !== false, 'and the contacts right');
}
