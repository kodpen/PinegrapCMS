<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the line under the dashboard clock in includes/dashboard/brief.php:
 * how far off an event is, and the markup the page script counts down from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('TIME_FORMAT')) {
	define('TIME_FORMAT', 'twenty_four_hours');
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/brief.php');

// Minutes round up, the same way the page script counts, and an hour on the
// dot drops its minutes.
function test_dashboard_brief_until_counts_down()
{
	$now = 1700000000;

	$cases = array(
		'one second'     => array(1, 'in 1 min'),
		'one minute'     => array(60, 'in 1 min'),
		'just past'      => array(61, 'in 2 min'),
		'under the hour' => array(3599, 'in 1 h'),
		'one hour'       => array(3600, 'in 1 h'),
		'hour and one'   => array(3660, 'in 1 h 1 min'),
		'two and a half' => array(9000, 'in 2 h 30 min'),
	);

	foreach ($cases as $name => $case) {
		$until = pg_dashboard_brief_until($now + $case[0], $now + $case[0] + 1800, false, $now);

		pg_assert_same('soon', $until['state'], $name . ' state');
		pg_assert_same($case[1], $until['text'], $name . ' text');
	}
}

// Under way, all day and over each say so; an all-day event has nothing to
// count down to, and one that has ended is not shown at all.
function test_dashboard_brief_until_states()
{
	$now = 1700000000;

	pg_assert_same(array('state' => 'now', 'text' => 'Now'), pg_dashboard_brief_until($now - 600, $now + 600, false, $now), 'under way');
	pg_assert_same(array('state' => 'now', 'text' => 'Now'), pg_dashboard_brief_until($now, $now + 600, false, $now), 'starting this second');
	pg_assert_same(array('state' => 'day', 'text' => ''), pg_dashboard_brief_until($now - 86400, $now + 3600, true, $now), 'all day');
	pg_assert_same(array('state' => 'over', 'text' => ''), pg_dashboard_brief_until($now - 600, $now, false, $now), 'ended this second');
	pg_assert_same(array('state' => 'over', 'text' => ''), pg_dashboard_brief_until($now - 86400, $now - 60, true, $now), 'all day, ended');
}

// Nothing left today: no markup, so the clock carries no empty box.
function test_dashboard_brief_next_empty()
{
	$now = 1700000000;

	pg_assert_same('', pg_dashboard_brief_next(array(), $now), 'no events');
	pg_assert_same('', pg_dashboard_brief_next(array(
		array('kind' => 'meeting', 'title' => 'Stand-up', 'starts_at' => $now - 3600, 'ends_at' => $now - 1800, 'all_day' => false),
	), $now), 'only ended events');
}

// Ended events are left out, timed ones come before all-day ones, and each
// item carries what the page script needs to keep counting.
function test_dashboard_brief_next_markup()
{
	$now = 1700000000;
	$starts = $now + 1500;

	$html = pg_dashboard_brief_next(array(
		array('kind' => 'visit', 'title' => 'Site <survey>', 'starts_at' => $now - 86400, 'ends_at' => $now + 7200, 'all_day' => true),
		array('kind' => 'meeting', 'title' => 'Ended', 'starts_at' => $now - 3600, 'ends_at' => $now - 60, 'all_day' => false),
		array('kind' => 'meeting', 'title' => 'Weekly review', 'starts_at' => $starts, 'ends_at' => $starts + 3600, 'all_day' => false),
	), $now);

	pg_assert_same(1, substr_count($html, 'class="pg-clockbar-next"'), 'one container');
	pg_assert_same(2, substr_count($html, '<a class="pg-next'), 'ended event left out');
	pg_assert_false(strpos($html, 'Ended'), 'ended title absent');

	$meeting = strpos($html, 'Weekly review');
	$visit = strpos($html, 'Site &lt;survey&gt;');

	pg_assert_true(($meeting !== false) && ($visit !== false) && ($meeting < $visit), 'timed before all day');
	pg_assert_false(strpos($html, '<survey>'), 'title escaped');

	pg_assert_true(strpos($html, 'data-pg-starts="' . $starts . '" data-pg-ends="' . ($starts + 3600) . '" data-pg-all-day="0"') !== false, 'timed data attributes');
	pg_assert_true(strpos($html, 'data-pg-all-day="1"') !== false, 'all-day flag');
	pg_assert_true(strpos($html, '<span class="pg-next-time">' . date('H:i', $starts) . '</span>') !== false, 'start time');
	pg_assert_true(strpos($html, '<span class="pg-next-until">in 25 min</span>') !== false, 'countdown');
	pg_assert_true(strpos($html, 'bi-people') !== false, 'meeting icon');
	pg_assert_true(strpos($html, 'bi-geo-alt') !== false, 'visit icon');
}

// An event already under way is marked, so it reads as now from the start.
function test_dashboard_brief_next_under_way()
{
	$now = 1700000000;

	$html = pg_dashboard_brief_next(array(
		array('kind' => 'meeting', 'title' => 'Planning', 'starts_at' => $now - 300, 'ends_at' => $now + 1500, 'all_day' => false),
	), $now);

	pg_assert_true(strpos($html, 'class="pg-next hover-lift is-now"') !== false, 'marked as now');
	pg_assert_true(strpos($html, '<span class="pg-next-until">Now</span>') !== false, 'says now');
}

// Card ids of each settings category, read from the registry source so the
// check needs no language layer.
function dashboard_brief_settings_cards()
{
	$registry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
	preg_match_all("/^        '([a-z_]+)' => array\(/m", $registry, $starts, PREG_OFFSET_CAPTURE);
	$cards = array();

	foreach ($starts[1] as $i => $start) {
		$end = isset($starts[1][$i + 1]) ? $starts[1][$i + 1][1] : strlen($registry);
		$block = substr($registry, $start[1], $end - $start[1]);

		if (preg_match("/'sections' => array\((.*?)\n            \),/s", $block, $sections)) {
			preg_match_all("/'(pgset-[\w-]+)'\s*=>/", $sections[1], $ids);
			$cards[$start[0]] = $ids[1];
		}
	}

	return $cards;
}

// Every settings link in the brief opens a card its category draws, and the
// device hint opens the Device card.
function test_dashboard_brief_settings_links()
{
	$brief = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/brief.php');
	$cards = dashboard_brief_settings_cards();
	pg_assert_true(isset($cards['security']) && in_array('pgset-device', $cards['security'], true), 'registry read');

	preg_match_all("/settings_([a-z_]+)\.php#(pgset-[\w-]+)/", $brief, $links, PREG_SET_ORDER);
	pg_assert_true(count($links) > 0, 'brief has settings links');

	foreach ($links as $link) {
		pg_assert_true(isset($cards[$link[1]]) && in_array($link[2], $cards[$link[1]], true), $link[0] . ' names a registry card');
	}

	pg_assert_true(preg_match("/cap how many devices.*?\n\s*\. \\\$greeting_ask\('settings_security\.php#pgset-device'/", $brief) === 1, 'device hint opens the Device card');
}
