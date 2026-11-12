<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the general booking family (assets/js/style_designer_blocks_
 * booking.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): it joins the core's Services & Booking group
 * with no group of its own; the weekly timetable is a section with one h2,
 * a day heading per day, no fixed colour class, every {var} slot filled, no
 * year, entrances kept by the normalizer; each class starts at a <time>
 * and has a book link; the week scrolls as a named region; the arrival
 * windows are a section with one h2 and a heading per window, each window's
 * start and end a <time> and its hours drawn on the working day; their
 * rules reach the block stylesheet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/designer_family_run.php');

function test_designer_blocks_booking_week()
{
	$got = pg_test_designer_family_run('booking');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('booking-', $got['prefix'], 'a core group\'s prefix');
	pg_assert_same(null, $got['group'], 'no palette group of its own');
	pg_assert_same(array('booking-week', 'booking-windows'), array_keys($got['blocks']), 'the weekly timetable and the arrival windows');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');

	$b = $got['blocks']['booking-week'];
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-light', 'text-dark', 'text-muted');
	pg_assert_same(array('semantic', 'section'), $b['root'], 'a section');
	pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), 'one h2');
	pg_assert_same(7, count(array_keys($b['headings'], 'h3', true)), 'a heading per day');
	pg_assert_same(array('h2', 'h3'), array_values(array_unique($b['headings'])), 'no skipped level');
	pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), 'no fixed colour class');
	pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), 'every {var} slot filled');
	pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), 'no year');
	pg_assert_same(array('ok', 'ok'), $b['fx'], 'the header fades, the days rise');
	pg_assert_same(15, count(array_keys($b['classes'], 'pg-week-slot', true)), 'fifteen classes in the week');
	foreach (array('Monday', 'Sunday', 'HIIT Burn', '07:00', 'Marcus Reed · Main Studio · 45 min', 'Book a Class') as $text) {
		pg_assert_true(in_array($text, $b['texts'], true), 'the week says ' . $text);
	}

	$js = pg_test_designer_family_file('booking');
	pg_assert_true(strpos($js, "kit.time(time, time, 'small'") !== false, 'each start is a <time>');
	pg_assert_true(strpos($js, "], kit.region(_sdT('Class Timetable')))") !== false, 'the scrolling week is a named region');
	pg_assert_true(strpos($js, "kit.tag('ol', '', _sdT('Days')") !== false, 'the days are an ordered list');
}

function test_designer_blocks_booking_windows()
{
	$got = pg_test_designer_family_run('booking');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$b = $got['blocks']['booking-windows'];
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-light', 'text-dark', 'text-muted');
	pg_assert_same(array('semantic', 'section'), $b['root'], 'a section');
	pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), 'one h2');
	pg_assert_same(3, count(array_keys($b['headings'], 'h3', true)), 'a heading per window');
	pg_assert_same(array('h2', 'h3'), array_values(array_unique($b['headings'])), 'no skipped level');
	pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), 'no fixed colour class');
	pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), 'every {var} slot filled');
	pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), 'no year');
	pg_assert_same(array('ok', 'ok', 'ok'), $b['fx'], 'the header fades, the windows rise, the note follows');
	pg_assert_same(3, count(array_keys($b['classes'], 'pg-window', true)), 'three windows');
	pg_assert_same(3, count(array_keys($b['classes'], 'pg-window-day', true)), 'each drawn on the working day');
	foreach (array('pg-window-am', 'pg-window-pm', 'pg-window-eve', 'pg-window-tag') as $class) {
		pg_assert_true(in_array($class, $b['classes'], true), 'the windows write ' . $class);
	}
	foreach (array('Choose when we come', 'Morning', 'Afternoon', 'Evening', '08:00', '12:00', '19:00', 'Most booked', 'Monday to Friday; an evening rate applies', 'Book a Visit') as $text) {
		pg_assert_true(in_array($text, $b['texts'], true), 'the windows say ' . $text);
	}

	$js = pg_test_designer_family_file('booking');
	pg_assert_true(strpos($js, "kit.time(from, from, '', _sdT('Start Time'))") !== false && strpos($js, "kit.time(to, to, '', _sdT('End Time'))") !== false, 'a window starts and ends at a <time>');
	pg_assert_true(strpos($js, "kit.div('pg-window-day mb-4', _sdT('Drawing'), [") !== false
		&& strpos($js, "], [{ name: 'aria-hidden', value: 'true' }]),") !== false, 'the drawing is hidden from a screen reader');
}

function test_designer_blocks_booking_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('booking'));
	pg_assert_same(array('pg-week', 'pg-week-slot', 'pg-window', 'pg-window-day'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-week', 'pg-week-slot'), $part['needs']['booking-week'], 'the week needs both');
	pg_assert_same(array('pg-window', 'pg-window-day'), $part['needs']['booking-windows'], 'the windows need the card and the day');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-week-slot']), 'the block stylesheet holds the rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
	// The canvas wraps every node in an .sd-wrap, so the windows' rules use
	// no child or sibling selector, which would miss there.
	foreach (array('pg-window', 'pg-window-day') as $name) {
		pg_assert_same(0, preg_match('/[>+~]\s*(li|div|span|p)\b/', $part['rules'][$name]), 'no child or sibling selector: ' . $name);
	}
}
