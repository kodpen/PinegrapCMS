<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/object_storage_health.php: which
 * answers count as the store being unreachable, the hour's window of
 * failures, the stored test result and the move job's failure counts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/object_storage_health.php');

function test_object_storage_health_unreachable()
{
	pg_assert_same(false, pg_os_health_unreachable(array('ok' => true, 'status' => 200, 'errno' => 0)));
	pg_assert_same(true, pg_os_health_unreachable(array('ok' => false, 'status' => 0, 'errno' => 7)), 'no connection');
	pg_assert_same(true, pg_os_health_unreachable(array('ok' => false, 'status' => 0, 'errno' => 28)), 'timed out');
	pg_assert_same(false, pg_os_health_unreachable(array('ok' => false, 'status' => 200, 'errno' => 23)), 'the sink stopped it');
	pg_assert_same(true, pg_os_health_unreachable(array('ok' => false, 'status' => 503, 'errno' => 0)));
	pg_assert_same(true, pg_os_health_unreachable(array('ok' => false, 'status' => 403, 'errno' => 0)), 'credentials refused');
	pg_assert_same(false, pg_os_health_unreachable(array('ok' => false, 'status' => 404, 'errno' => 0)), 'a missing object');
	pg_assert_same(false, pg_os_health_unreachable(array('ok' => false, 'code' => 'not_configured', 'message' => 'x')), 'never sent');
}

function test_object_storage_health_window()
{
	$now = 100000;
	$kept = pg_os_health_window(array(array($now - 4000, 'old'), array($now - 10, 'new'), 'junk', array($now + 50, 'future')), $now, array($now, 'added'));

	pg_assert_same(array(array($now - 10, 'new'), array($now, 'added')), $kept);
	pg_assert_same(array(), pg_os_health_window(null, $now));

	$many = array();

	for ($i = 0; $i < 80; $i++) {
		$many[] = array($now - 100 + $i, 'e' . $i);
	}

	$kept = pg_os_health_window($many, $now);
	pg_assert_same(50, count($kept), 'the newest 50');
	pg_assert_same('e79', $kept[49][1]);
}

function test_object_storage_health_test_normalize()
{
	pg_assert_same(null, pg_os_health_test_normalize(null), 'never tested');
	pg_assert_same(null, pg_os_health_test_normalize(array('ok' => true)), 'no time');
	pg_assert_same(array('at' => 5, 'ok' => false, 'message' => 'Access Denied'), pg_os_health_test_normalize(array('at' => '5', 'ok' => 0, 'message' => 'Access Denied')));
	pg_assert_same(array('at' => 9, 'ok' => true, 'message' => ''), pg_os_health_test_normalize(array('at' => 9, 'ok' => true)));
}

function test_object_storage_health_move_failures()
{
	$state = array('cursor' => 0, 'failures' => array(4 => 1, 7 => 3, 9 => 2, 12 => 3), 'rested_at' => 0);

	pg_assert_same(array('failing' => 2, 'given_up' => 2, 'given_up_ids' => array(7, 12)), pg_os_health_move_failures($state, 3));
	pg_assert_same(array('failing' => 0, 'given_up' => 0, 'given_up_ids' => array()), pg_os_health_move_failures(array('failures' => array()), 3));
}
