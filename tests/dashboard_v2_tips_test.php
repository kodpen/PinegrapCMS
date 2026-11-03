<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts new dashboard charts show on hover and keyboard focus
 * (includes/dashboard/v2/tips.php).
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

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/tips.php');

function test_dashboard_v2_tips_label_and_figure()
{
	$format = function ($n) {
		return $n . ' visits';
	};

	pg_assert_same(array('00:00: 3 visits', '01:00: 0 visits'), pg_dashboard_v2_tips(array('00:00', '01:00'), array(3, 0), $format), 'one text per point');
	pg_assert_same(array('5 visits'), pg_dashboard_v2_tips(array(), array(5), $format), 'no label: the figure alone');
}

function test_dashboard_v2_tips_with_comparison()
{
	$format = function ($n) {
		return (string) $n;
	};

	$tips = pg_dashboard_v2_tips(array('09:00', '10:00', '11:00'), array(4, 6), $format, array(2, 3, 9), 'Yesterday');

	pg_assert_same('09:00: 4 · Yesterday: 2', $tips[0], 'both figures');
	pg_assert_same('11:00: Yesterday: 9', $tips[2], 'today has not reached the hour: the comparison alone');
	pg_assert_same(3, count($tips), 'as long as the longer line');
	pg_assert_same(array('a: 1'), pg_dashboard_v2_tips(array('a'), array(1), $format, null, 'Yesterday'), 'no comparison line');
}

function test_dashboard_v2_slot_labels_hourly()
{
	$from = mktime(13, 0, 0, 11, 2, 2026);

	pg_assert_same(array('13:00', '14:00', '15:00'), pg_dashboard_v2_slot_labels($from, 3600, 3), 'hours');
	pg_assert_same(7, count(pg_dashboard_v2_slot_labels($from, 86400, 7)), 'one label per day');
}
