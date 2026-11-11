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

// The source of one function in the card file, up to the next one.
function dashboard_v2_engagement_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/engagement.php');
	$start = strpos($source, "\nfunction " . $name . '(');
	pg_assert_true($start !== false, 'found: ' . $name);
	$end = strpos($source, "\nfunction ", (int) $start + 10);
	return (string) substr($source, (int) $start, ($end === false) ? null : ($end - $start));
}

function test_dashboard_v2_engagement_last_visits_read_is_bounded_from_the_newest_ids()
{
	$read = dashboard_v2_engagement_function('pg_dashboard_v2_last_visits');

	pg_assert_contains('SELECT MAX(id) FROM visitors', $read, 'the newest id from the primary key');
	pg_assert_contains('WHERE id > " . max(0, $top - PG_DASHBOARD_V2_LAST_VISITS_WINDOW) . " AND id <= " . $top', $read, 'a range of ids below it');
	pg_assert_false(stripos($read, 'COUNT(') !== false, 'nothing is counted');
	pg_assert_false(stripos($read, 'ORDER BY') !== false, 'no sort over the table; the window is sorted in PHP');
	pg_assert_contains("'type' => 'time', 'timezone_type' => 'site'", $read, 'the clock in the site\'s time');
	pg_assert_contains("'format' => 'plain_text'", $read, 'how long ago, as plain text');
	pg_assert_true(PG_DASHBOARD_V2_LAST_VISITS_WINDOW <= 500, 'a small window');
}

function test_dashboard_v2_engagement_empty_only_without_last_visits()
{
	$card = dashboard_v2_engagement_function('pg_dashboard_v2_card_engagement');

	pg_assert_contains('$last = ($active === 0) ? pg_dashboard_v2_last_visits() : array();', $card, 'the last visits only while nobody is on');
	pg_assert_contains("'empty'      => ((\$active === 0) && (\$recent === 0) && !\$last) ? lang('Nobody is on the site right now.') : '',", $card, 'empty is sent only when there is nothing to draw');
	pg_assert_contains("'nobody'     => (\$active === 0) ? lang('Nobody is on the site right now.') : '',", $card, 'the line stays as the heading');
	pg_assert_contains("'last'       => \$last,", $card, 'the rows go to the renderer');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_visitors.js');
	pg_assert_contains('if (data.nobody) {', $js, 'the renderer draws the heading');
	pg_assert_contains('el("h3", "pg-dash-rows-title", data.last_title)', $js, 'the list is titled');
	pg_assert_contains('row.ago', $js, 'how long ago');
	pg_assert_contains('row.clock', $js, 'the clock');
	pg_assert_contains('row.page', $js, 'the landing page');

}
