<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Online Engagement card while nobody is on the site:
 * the last visits it lists (when each was last seen, the clock in the
 * site's time, the landing page), how many, and the read that finds them,
 * bounded from the newest visit ids.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/engagement.php');

function dashboard_v2_engagement_rows($visits, $limit = 5)
{
	return pg_dashboard_v2_last_visit_rows($visits, $limit, function ($ts) {
		return 'ago:' . $ts;
	}, function ($ts) {
		return 'clock:' . $ts;
	});
}

function test_dashboard_v2_engagement_last_visit_row_shape()
{
	$rows = dashboard_v2_engagement_rows(array(
		array('id' => 7, 'landing_page_name' => ' shop ', 'start_timestamp' => 1000, 'stop_timestamp' => 1300),
	));

	pg_assert_same(array(array('when' => 1300, 'ago' => 'ago:1300', 'clock' => 'clock:1300', 'page' => 'shop')), $rows, 'how long ago, the clock, the landing page');
}

function test_dashboard_v2_engagement_last_visits_latest_first_and_capped()
{
	$visits = array();
	for ($i = 1; $i <= 9; $i++) {
		$visits[] = array('id' => $i, 'landing_page_name' => 'p' . $i, 'start_timestamp' => 100 * $i, 'stop_timestamp' => ($i === 3) ? 5000 : 100 * $i + 10);
	}
	$visits[] = array('id' => 10, 'landing_page_name' => 'never', 'start_timestamp' => 0, 'stop_timestamp' => 0);
	$visits[] = array('id' => 11, 'landing_page_name' => '', 'start_timestamp' => 950, 'stop_timestamp' => 0);

	$rows = dashboard_v2_engagement_rows($visits);

	pg_assert_same(5, count($rows), 'at most the limit');
	pg_assert_same(array('p3', '', 'p9', 'p8', 'p7'), array_column($rows, 'page'), 'the latest seen first; a visit with no stop counts from its start');
	pg_assert_same(950, $rows[1]['when'], 'the start stands in for a missing stop');
	pg_assert_false(in_array('never', array_column($rows, 'page'), true), 'a visit with no time is left out');
	pg_assert_same(array(), dashboard_v2_engagement_rows(array()), 'no visits, no rows');
	pg_assert_same(5, PG_DASHBOARD_V2_LAST_VISITS, 'five rows on the card');
}
