<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell of a site whose Workspace is off: it shows the
 * Workspace inbox lines flows write (a run of one's flow that ended or
 * failed, Let people know) and no other Workspace line, and Let people know
 * is offered again on the flow canvas.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// The source of a PHP function of a product file, up to its closing brace.
function automation_flows_bell_test_php($file, $name)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
	$start = strpos($source, "\nfunction " . $name . '(');
	$end = ($start !== false) ? strpos($source, "\n}\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The lines a flow writes, and nothing the Workspace alone writes.
function test_automation_flows_bell_kinds()
{
	pg_assert_same(array('flow_finished', 'scheduled_notice'), pg_notification_flow_kinds(), 'a run that ended or failed, Let people know');
}

// Without the flow tables (the test runner has no database) no Workspace
// row passes the ladder while the Workspace is off.
function test_automation_flows_bell_ladder_without_flows()
{
	pg_assert_false(defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED, 'the Workspace is off in the test runner');
	pg_assert_false(pg_notification_flow_line(5, 9), 'no flow tables, no flow line');
	pg_assert_false(pg_notification_flow_line(0, 9), 'nobody addressed');
	pg_assert_false(pg_notification_flow_line(5, 0), 'no inbox line');

	$row = array('action' => 'workspace', 'target_user_id' => 5, 'reference_id' => 9);
	$rights = array('id' => 5, 'role' => 3, 'manage_ecommerce' => false, 'manage_forms' => false, 'manage_erp' => false, 'manage_erp_cash' => false);

	pg_assert_false(pg_notification_visible($row, $rights), 'a Workspace row with the Workspace off and no flows');
	pg_assert_false(pg_notification_visible(array('action' => 'workspace', 'target_user_id' => 0, 'reference_id' => 9), $rights), 'never unaddressed');
}

// The ladder asks the inbox line's kind with the Workspace off; the columns
// the endpoints read carry the inbox line; describing, reading and the row's
// button follow.
function test_automation_flows_bell_wiring()
{
	pg_assert_contains("return pg_notification_flow_line(\$target, (int) (\$notification['reference_id'] ?? 0));", automation_flows_bell_test_php('includes/notifications.php', 'pg_notification_visible'), 'the ladder asks the line');
	pg_assert_contains(', target_user_id, reference_id', automation_flows_bell_test_php('includes/notifications.php', 'pg_notification_ladder_columns'), 'the columns carry the line');
	pg_assert_contains("(\$workspace_on || (function_exists('pg_flows_enabled') && pg_flows_enabled()))", automation_flows_bell_test_php('includes/notifications.php', 'pg_notification_display'), 'described while flows run');
	pg_assert_contains("(function_exists('pg_flows_enabled') && pg_flows_enabled())", automation_flows_bell_test_php('includes/notifications.php', 'pg_notification_mark_read'), 'read in the inbox too');
	pg_assert_contains("'url'   => !ws_enabled() ? '' :", automation_flows_bell_test_php('includes/workspace/notify.php', 'ws_inbox_describe'), 'a notice opens nothing with the Workspace off');
	pg_assert_contains("((\$display['action'] === 'workspace') && ((string) \$display['url'] === '')) ? ''", automation_flows_bell_test_php('includes/panel/notifications.php', 'pg_panel_get_notifications'), 'a row with nowhere to go has no button');
}

// Let people know is offered again with the Workspace off.
function test_automation_flows_bell_notify_runs()
{
	pg_assert_false(in_array('notify', ws_flow_kinds_workspace(), true), 'notify runs without the Workspace');
	pg_assert_false(ws_flow_node_needs_workspace(array('id' => 'n2', 'kind' => 'notify', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('user_ids' => array(1), 'text' => 'An order came'))), 'whatever its settings');
}
