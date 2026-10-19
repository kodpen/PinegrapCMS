<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the proposals whose write died after Apply took the row
 * (ws_change_sweep_stuck() in includes/workspace/changes.php): which rows
 * the sweep takes, that the lock stamps the moment it is taken, that a row
 * handed back to pending carries no stamp, and that the sweep runs where the
 * half-done bulk changes are carried on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

// Only rows still applying, never a bulk change (carried on a slice at a
// time), and only once the timeout has passed since the row was taken - or,
// for a row taken before the moment was written, since it was proposed.
function test_workspace_change_stuck_query_takes_old_single_writes_only()
{
	$now = 1800000000;
	$sql = preg_replace('/\s+/', ' ', ws_change_stuck_sql($now, 20));

	pg_assert_contains("status = 'applying'", $sql, 'only rows taken by Apply');
	pg_assert_contains("action <> 'bulk'", $sql, 'bulk changes are left to ws_bulk_continue()');
	pg_assert_contains("IF(decided_at > 0, decided_at, created_at) < '" . ($now - WS_CHANGE_APPLYING_TIMEOUT) . "'", $sql, 'aged from the moment the row was taken');
	pg_assert_contains('LIMIT 20', $sql, 'a run is bounded');
	pg_assert_contains('LIMIT 1', preg_replace('/\s+/', ' ', ws_change_stuck_sql($now, 0)), 'at least one row');
}

// The longest write, a call to an MCP server, stops at its own time limit;
// the sweep waits well past it.
function test_workspace_change_stuck_timeout_outlasts_every_writer()
{
	pg_assert_true(WS_CHANGE_APPLYING_TIMEOUT >= 600, 'at least ten minutes');

	$mcp = PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php';

	if (is_file($mcp)) {
		require_once($mcp);
	}

	if (defined('WS_MCP_SCREEN_TIMEOUT') && defined('PG_MCP_CLIENT_MAX_PAGES')) {
		pg_assert_true(WS_CHANGE_APPLYING_TIMEOUT > WS_MCP_SCREEN_TIMEOUT * (PG_MCP_CLIENT_MAX_PAGES + 3), 'longer than an MCP call may run');
	}
}

// The lock writes who took the row and when; a row an MCP call hands back to
// pending loses both, so it is neither aged nor shown as decided.
function test_workspace_change_stuck_lock_is_stamped_and_cleared()
{
	$changes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

	pg_assert_true(preg_match("/SET status = 'applying', decided_by = '\" \. \(int\) \\\$viewer\['id'\] \. \"', decided_at = '\" \. time\(\) \. \"'\s+WHERE id = '\" \. \(int\) \\\$change\['id'\] \. \"' AND status = 'pending'/", $changes) === 1, 'the lock stamps the moment');

	foreach (array('mcp_ask.php', 'mcp_agent.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);

		pg_assert_contains("SET status = 'pending', decided_by = 0, decided_at = 0 WHERE", $source, $file . ' clears the stamp when it hands the row back');
		pg_assert_false(strpos($source, "SET status = 'pending' WHERE id = '\" . (int) \$change['id']") !== false, $file . ' has no handing back that keeps it');
	}
}

// Wherever half-done bulk changes are carried on, dead single writes are
// swept as well.
function test_workspace_change_stuck_sweep_runs_with_the_bulk_carry_on()
{
	foreach (array('includes/workspace/actions.php', 'workspace_recurring_job.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('ws_bulk_continue(', $source, $file . ' carries on bulk changes');
		pg_assert_contains('ws_change_sweep_stuck(', $source, $file . ' sweeps dead writes');
	}
}
