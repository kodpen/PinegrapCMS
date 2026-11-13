<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dashboard's flow entries on a site whose Workspace is off:
 * the Flows card, the "Flow runs (today)" figure and the Start flow shortcut
 * ask the Flows screen's gate (pg_flows_user_may()), not the Workspace's.
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

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/flows.php');

// The card, the figure and the shortcut each name a gate built on the
// Flows screen's.
function test_automation_flows_dashboard_gates()
{
	$cards = pg_dashboard_v2_cards();

	pg_assert_same('flows', $cards['flows']['gate'], 'the card\'s gate');
	pg_assert_same('Automation flows', $cards['flows']['title'], 'the card\'s title');
	pg_assert_true(in_array('flows', pg_dashboard_v2_gates(), true), 'a card gate');

	$figures = pg_dashboard_v2_figures();

	pg_assert_same('flow_runs', $figures['flows']['gate'], 'the figure\'s gate');
	pg_assert_true(in_array('flow_runs', pg_dashboard_v2_figure_gates(), true), 'a figure gate');
	pg_assert_same('flows', pg_dashboard_v2_shortcuts()['start_flow']['gate'], 'the shortcut asks the screen\'s gate');

	$gate = pg_test_function_source('pg_dashboard_v2_gate');
	$figure = pg_test_function_source('pg_dashboard_v2_figure_gate');

	pg_assert_contains("case 'flows':\n            // What the Flows screen asks, with the Workspace on or off.\n            return pg_flows_user_may(\$user);", $gate, 'the card gate is the screen\'s');
	pg_assert_contains("case 'flow_runs':\n            return (\$role < 3) && pg_dashboard_v2_gate('flows', \$user);", $figure, 'the figure: staff, as it counts every run');
	pg_assert_false(strpos($figure, "case 'flows':") !== false, 'no figure gate of its own shadows the card gate');
}

// Nobody signed in, or a site without the flow tables (the test runner), is
// shown no entry.
function test_automation_flows_dashboard_closed_without_flows()
{
	$staff = array('id' => 1, 'role' => 0);

	pg_assert_false(pg_flows_user_may('nobody'), 'no user');
	pg_assert_false(pg_flows_user_may($staff), 'no flow tables');
	pg_assert_false(pg_dashboard_v2_gate('flows', $staff), 'the card');
	pg_assert_false(pg_dashboard_v2_figure_gate('flow_runs', $staff), 'the figure');
	pg_assert_same(array('status' => 'denied'), pg_dashboard_v2_card_flows($staff, null, false), 'the card\'s data');
}

// The card reads the person's rights after the screen's gate, never through
// the Workspace's dashboard rights, which ask the switch.
function test_automation_flows_dashboard_card_reads_without_the_switch()
{
	$card = pg_test_function_source('pg_dashboard_v2_card_flows');

	pg_assert_contains('if (!pg_flows_user_may($user)) {', $card, 'the screen\'s gate');
	pg_assert_contains('$rights = ws_rights($user);', $card, 'the rights the Flows screen reads');
	pg_assert_false(strpos($card, 'pg_dashboard_workspace_rights') !== false, 'not the Workspace\'s dashboard rights');
	pg_assert_false(strpos($card, 'WORKSPACE_ENABLED') !== false, 'the switch is not asked');
}
