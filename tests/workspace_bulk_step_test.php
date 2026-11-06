<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the slice runner of Workspace bulk changes (ws_bulk_step() in
 * includes/workspace/bulk.php): a change finished or failed by another
 * worker is not finished again, a change whose start has not written its
 * records yet is waited for, and an update's place is kept before its write.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');

function pg_test_ws_bulk_step_code($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' exists');

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

function test_ws_bulk_step_verdict_stops_a_change_no_longer_applying()
{
	$state = array('ids' => array(1, 2), 'pos' => 2);

	pg_assert_same('stop', ws_bulk_step_verdict('applied', $state, 1000, 1001), 'finished by another worker: no second decision');
	pg_assert_same('stop', ws_bulk_step_verdict('failed', $state, 1000, 1001), 'failed meanwhile: nothing more written');
	pg_assert_same('run', ws_bulk_step_verdict('applying', $state, 1000, 1001), 'being applied with its records');
}

function test_ws_bulk_step_verdict_waits_for_a_start_still_matching()
{
	pg_assert_same('wait', ws_bulk_step_verdict('applying', array(), 1000, 1010), 'taken a moment ago, records not written yet');
	pg_assert_same('wait', ws_bulk_step_verdict('applying', null, 1000, 1010), 'no snapshot yet');
	pg_assert_same('invalid', ws_bulk_step_verdict('applying', array(), 1000, 1000 + WS_CHANGE_APPLYING_TIMEOUT), 'a start that died long ago');
	pg_assert_same('invalid', ws_bulk_step_verdict('applying', array(), 0, 5000), 'never stamped');
}

function test_ws_bulk_step_reads_the_verdict_under_the_lock()
{
	$code = pg_test_ws_bulk_step_code('ws_bulk_step');
	$lock = strpos($code, 'GET_LOCK(');
	$verdict = strpos($code, 'ws_bulk_step_verdict(');

	pg_assert_true(($lock !== false) && ($verdict !== false) && ($lock < $verdict), 'the status is read again after the lock is taken');
}

function test_ws_bulk_step_keeps_an_update_place_before_its_write()
{
	$code = pg_test_ws_bulk_step_code('ws_bulk_step');
	$save = strpos($code, 'ws_bulk_save_state((int) $change[\'id\'], $state);');
	$write = strpos($code, 'ws_change_write($viewer, $type, $spec[\'action\']');

	pg_assert_true(($save !== false) && ($write !== false) && ($save < $write), 'a percent or add change is not applied twice on resume');
}
