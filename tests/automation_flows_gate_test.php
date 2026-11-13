<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for automation flows on a site whose Workspace is off: the one gate
 * (pg_flows_enabled()), the site events kept only while a live flow waits
 * for them, the scheduled run kept to the anchors of flows, the boxes that
 * need the Workspace and the refusal that stops them at run time, and the
 * two jobs that run the flows' pass.
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

// A box of a graph.
function automation_flows_gate_test_node($kind, $config = array())
{
	return array('id' => 'n2', 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// Without a database the flow tables are not there: no gate, nothing in use,
// and an event is dropped without a query.
function test_automation_flows_gate_without_the_tables()
{
	pg_assert_false(pg_flows_enabled(), 'no tables, no flows');
	pg_assert_false(pg_flows_in_use(), 'nothing in use');
	pg_assert_false(defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED, 'the Workspace is off in the test runner');
	pg_assert_same(null, pg_event_record('order.created', array('id' => 1)), 'nothing kept');
}

// With the Workspace on every site event is kept but the on-demand ones;
// with it off only the events an event rule of a live flow names.
function test_automation_flows_gate_events_kept()
{
	$names_order = array('[{"type":"event","event":"order.created"}]');
	$names_stock = array('[{"type":"event","event":"stock.low"}]');
	$timed = array('[{"type":"at","at":"09:00"}]');

	pg_assert_true(pg_event_workspace_kept('order.created', true, array()), 'Workspace on: kept for its channels');
	pg_assert_false(pg_event_workspace_kept('inventory.changed', true, array()), 'Workspace on: an on-demand event nobody names');
	pg_assert_true(pg_event_workspace_kept('inventory.changed', true, array('[{"type":"event","event":"inventory.changed"}]')), 'Workspace on: an on-demand event named');

	pg_assert_false(pg_event_workspace_kept('order.created', false, array()), 'Workspace off: no flow waits for it');
	pg_assert_true(pg_event_workspace_kept('order.created', false, $names_order), 'Workspace off: a live flow starts on it');
	pg_assert_false(pg_event_workspace_kept('order.created', false, $names_stock), 'Workspace off: a flow waits for another event');
	pg_assert_false(pg_event_workspace_kept('order.created', false, $timed), 'Workspace off: a flow started at a time');
	pg_assert_false(pg_event_workspace_kept('order.created', false, array('not json')), 'a broken rules row names nothing');
}

// The rows of ws_scheduled_actions and the starts of its queue a run takes.
function test_automation_flows_gate_scheduled_run_rows()
{
	pg_assert_same('', ws_scheduled_running_where(true, true), 'Workspace on: every row');
	pg_assert_same('', ws_scheduled_running_where(true, false), 'Workspace on, before the flow tables');
	pg_assert_same(' AND flow_id > 0', ws_scheduled_running_where(false, true), 'Workspace off: the anchors of flows only');
	pg_assert_same(' AND a.flow_id > 0', ws_scheduled_running_where(false, true, 'a'), 'with an alias');
	pg_assert_same(' AND 1 = 0', ws_scheduled_running_where(false, false), 'Workspace off, no flow tables: nothing');

	pg_assert_same('', ws_scheduled_queue_running_where(true, true), 'Workspace on: every start');
	pg_assert_same(' AND action_id IN (SELECT id FROM ws_scheduled_actions WHERE flow_id > 0)', ws_scheduled_queue_running_where(false, true), 'Workspace off: starts of flows');
	pg_assert_same(' AND 1 = 0', ws_scheduled_queue_running_where(false, false), 'Workspace off, no flow tables: nothing');
}

// Every kind on the list is a kind of box a flow knows, and the boxes that
// act only on the site, the mail or the web stay off it.
function test_automation_flows_gate_workspace_kinds()
{
	$kinds = ws_flow_kinds();

	foreach (ws_flow_kinds_workspace() as $kind) {
		pg_assert_true(isset($kinds[$kind]), $kind . ' is a kind of box');
	}

	foreach (array('join', 'mention', 'post', 'checkin', 'approval', 'lead', 'task', 'task_open', 'task_digest', 'agent') as $kind) {
		pg_assert_true(in_array($kind, ws_flow_kinds_workspace(), true), $kind . ' needs the Workspace');
	}

	foreach (array('manual', 'schedule', 'event', 'no_order', 'web', 'end', 'condition', 'delay', 'start_flow', 'for_each', 'for_each_end', 'constants', 'value',
		'email', 'webhook', 'web_check', 'report', 'change', 'record_get') as $kind) {
		pg_assert_true(isset($kinds[$kind]), $kind . ' is a kind of box');
		pg_assert_false(in_array($kind, ws_flow_kinds_workspace(), true), $kind . ' runs without the Workspace');
	}
}

// A summary of counts is decided by where it goes.
function test_automation_flows_gate_node_needs_workspace()
{
	pg_assert_true(ws_flow_node_needs_workspace(automation_flows_gate_test_node('post', array('channel_id' => 4))), 'a message in a channel');
	pg_assert_true(ws_flow_node_needs_workspace(automation_flows_gate_test_node('task')), 'a task');
	pg_assert_true(ws_flow_node_needs_workspace(automation_flows_gate_test_node('mention')), 'a mention start');

	pg_assert_true(ws_flow_node_needs_workspace(automation_flows_gate_test_node('report', array('channel_id' => 7, 'to' => ''))), 'a summary posted into a channel');
	pg_assert_true(ws_flow_node_needs_workspace(automation_flows_gate_test_node('report', array('channel_id' => 7, 'to' => 'a@example.com'))), 'a summary into a channel and by e-mail');
	pg_assert_false(ws_flow_node_needs_workspace(automation_flows_gate_test_node('report', array('channel_id' => 0, 'to' => 'a@example.com'))), 'a summary by e-mail only');

	pg_assert_false(ws_flow_node_needs_workspace(automation_flows_gate_test_node('event', array('event' => 'order.created'))), 'a site event');
	pg_assert_false(ws_flow_node_needs_workspace(automation_flows_gate_test_node('email', array('to' => 'a@example.com'))), 'an e-mail');
	pg_assert_false(ws_flow_node_needs_workspace(automation_flows_gate_test_node('webhook')), 'a webhook');
	pg_assert_false(ws_flow_node_needs_workspace(array('kind' => 'report', 'config' => 'broken')), 'settings that are no list');
}

// The run's refusal: none with the Workspace on, one for its boxes with it off.
function test_automation_flows_gate_box_refusal()
{
	pg_assert_same('', ws_flow_workspace_box_refusal(true, automation_flows_gate_test_node('post')), 'Workspace on: the message is posted');
	pg_assert_same('', ws_flow_workspace_box_refusal(false, automation_flows_gate_test_node('email')), 'Workspace off: the e-mail is sent');
	pg_assert_same('', ws_flow_workspace_box_refusal(false, automation_flows_gate_test_node('delay')), 'Workspace off: a wait waits');

	$refusal = ws_flow_workspace_box_refusal(false, automation_flows_gate_test_node('post'));

	pg_assert_true($refusal !== '', 'Workspace off: the message is not posted');
	pg_assert_true(strpos($refusal, 'Workspace') !== false, 'the refusal names the Workspace');
}

// The runner asks the refusal of every box, after the member's own.
function test_automation_flows_gate_runner_asks_the_refusal()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
	$member = strpos($source, 'ws_flow_member_box_refusal(ws_flow_builder_kind(');
	$workspace = strpos($source, 'ws_flow_workspace_box_refusal(ws_enabled(), $node)');
	$switch = strpos($source, "switch (\$node['kind']) {\n        case 'end':");

	pg_assert_true(($member !== false) && ($workspace !== false) && ($switch !== false), 'the three are there');
	pg_assert_true(($member < $workspace) && ($workspace < $switch), 'the box is refused before it runs');
}

// Both jobs run the flows' pass with the Workspace off; the general job
// loads the module only where flows are in use.
function test_automation_flows_gate_jobs()
{
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	pg_assert_true(strpos($job, "((defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) || pg_flows_in_use())") !== false, 'job.php loads the module for flows in use');
	pg_assert_true(strpos($job, '(ws_enabled() || pg_flows_enabled()) && function_exists(\'ws_scheduled_due\') && ws_scheduled_due()') !== false, 'job.php runs the pass for flows');

	$recurring = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_recurring_job.php');

	pg_assert_true(strpos($recurring, "(ws_enabled() || pg_flows_enabled()) && function_exists('ws_scheduled_run')") !== false, 'the recurring job runs the pass');
	pg_assert_true(strpos($recurring, "(ws_enabled() || pg_flows_enabled()) && function_exists('ws_flow_runs_sweep')") !== false, 'the recurring job sweeps old runs');
}
