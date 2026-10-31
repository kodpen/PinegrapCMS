<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell as the one inbox: which workspace lines fold into one
 * (ws_bell_fold_key(), includes/workspace/notify.php) and how the list folds
 * them under the newest (pg_notification_fold(), includes/notifications.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module files hold functions and constants only; loading them runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_notification_bell_fold_key_is_the_flow_and_how_its_run_ended()
{
	pg_assert_same('flow:12:ended', ws_bell_fold_key('flow_finished', array('id' => 40, 'flow_id' => 12, 'status' => 'done')), 'a run that went well');
	pg_assert_same('flow:12:failed', ws_bell_fold_key('flow_finished', array('id' => 41, 'flow_id' => 12, 'status' => 'failed')), 'a failed run folds only with failed ones');
	pg_assert_same('flow:0:ended', ws_bell_fold_key('flow_finished', null), 'lines whose run is not known read alike and fold together');
	pg_assert_same('flow:0:ended', ws_bell_fold_key('flow_finished', array('id' => 42, 'flow_id' => 0, 'status' => 'done')), 'a run without its flow folds with them');
	pg_assert_same('', ws_bell_fold_key('mention', array('id' => 40, 'flow_id' => 12, 'status' => 'done')), 'every other kind stands alone');
}

function test_notification_bell_fold_puts_the_older_runs_under_the_newest()
{
	$items = pg_notification_fold(array(
		array('id' => 9, 'fold' => 'flow:12:ended'),
		array('id' => 8, 'fold' => ''),
		array('id' => 7, 'fold' => 'flow:12:ended'),
		array('id' => 6, 'fold' => 'flow:12:failed'),
		array('id' => 5, 'fold' => 'flow:12:ended'),
		array('id' => 4)
	));

	pg_assert_same(array(9, 7, 5, 8, 6, 4), array_column($items, 'id'), 'the older runs follow the newest; the rest keep their place');
	pg_assert_same(array(3, 1, 1, 1, 1, 1), array_column($items, 'fold_count'), 'the newest run counts its flow\'s runs');
	pg_assert_same(array(0, 9, 9, 0, 0, 0), array_column($items, 'fold_head'), 'the older runs fold under it; the failed one heads its own');
}

function test_notification_bell_fold_leaves_a_list_without_keys_as_it_is()
{
	$items = pg_notification_fold(array(array('id' => 3, 'fold' => ''), array('id' => 2, 'fold' => '')));

	pg_assert_same(array(1, 1), array_column($items, 'fold_count'));
	pg_assert_same(array(0, 0), array_column($items, 'fold_head'));
	pg_assert_same(array(), pg_notification_fold(array()), 'an empty list');
}
