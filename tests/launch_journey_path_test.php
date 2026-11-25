<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the launch journey after its set-up
 * (includes/fn/launch_journey_path.php): the First week card's rows and
 * pills, a new account, a team member's path by role and rights, the hint
 * catalogue, and the hint keys pg_tour_mark() accepts (includes/fn/tour.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/tour.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey_path.php');

// The state of each First week row, by key.
function launch_journey_path_test_states($facts)
{
	$states = array();
	foreach (pg_launch_journey_week_card(array('day' => 3), $facts)['rows'] as $row) {
		$states[$row['key']] = $row['state'];
	}
	return $states;
}

// Nothing yet: every row waits, and no alert counts as seen.
function test_launch_journey_week_waiting()
{
	$card = pg_launch_journey_week_card(array('day' => 3), array('shop' => true, 'orders' => 0, 'entries' => 0,
		'visits' => 0, 'mail_sent' => 0, 'mail_failed' => 0, 'alerts' => 0));

	pg_assert_same('Day 3 of 7', $card['head']);
	pg_assert_same(array('first', 'visits', 'mail', 'alerts'), array_column($card['rows'], 'key'), 'four rows');
	pg_assert_same(array('waiting', 'waiting', 'waiting', 'seen'), array_column($card['rows'], 'state'));
	pg_assert_same('Waiting', $card['rows'][0]['pill']);
	pg_assert_same('First order or form entry', $card['rows'][0]['name']);
}

// An order, visits and a sent mail are seen; failed mail with none sent and
// an alert are problems.
function test_launch_journey_week_seen_and_problem()
{
	$states = launch_journey_path_test_states(array('shop' => true, 'orders' => 1, 'entries' => 0,
		'visits' => 40, 'mail_sent' => 0, 'mail_failed' => 2, 'alerts' => 1));
	pg_assert_same(array('first' => 'seen', 'visits' => 'seen', 'mail' => 'problem', 'alerts' => 'problem'), $states);

	$states = launch_journey_path_test_states(array('shop' => false, 'orders' => 5, 'entries' => 0,
		'visits' => 0, 'mail_sent' => 1, 'mail_failed' => 3, 'alerts' => 0));
	pg_assert_same('waiting', $states['first'], 'orders not counted without the shop');
	pg_assert_same('seen', $states['mail'], 'one mail sent is enough');
}

// A source that could not be read waits with "Could not be checked".
function test_launch_journey_week_unknown()
{
	$card = pg_launch_journey_week_card(array('day' => 9), array('shop' => true, 'entries' => 0));

	pg_assert_same('Day 7 of 7', $card['head'], 'day kept to the week');
	foreach ($card['rows'] as $row) {
		pg_assert_same('waiting', $row['state'], $row['key']);
		pg_assert_same('Could not be checked', $row['line'], $row['key']);
	}
}

// An account is new for thirty days; one made before the time was kept is not.
function test_launch_journey_is_new()
{
	$now = 1800000000;
	pg_assert_true(pg_launch_journey_is_new($now - 86400, $now), 'a day old');
	pg_assert_false(pg_launch_journey_is_new($now - 30 * 86400, $now), 'thirty days');
	pg_assert_false(pg_launch_journey_is_new(0, $now), 'no time kept');
}

// A path by role: only screens the person may open, five at most, none for
// an administrator.
function test_launch_journey_path_by_role()
{
	$all = array_fill_keys(array_keys(pg_launch_journey_screens()), true);

	pg_assert_same(array(), pg_launch_journey_path(0, $all), 'administrator');
	pg_assert_same(array('view_pages', 'view_styles', 'view_menus', 'view_folders', 'view_comments'),
		array_column(pg_launch_journey_path(1, $all), 'key'), 'designer');
	pg_assert_same('view_orders', pg_launch_journey_path(2, $all)[0]['key'], 'manager starts at orders');
	pg_assert_same(5, count(pg_launch_journey_path(3, $all)), 'five at most');

	$user = pg_launch_journey_path(3, array('view_submitted_forms' => true, 'view_contacts' => true, 'view_styles' => true));
	pg_assert_same(array('view_submitted_forms', 'view_contacts'), array_column($user, 'key'), 'a user\'s own areas');
	pg_assert_same('view_submitted_forms.php', $user[0]['url']);
}

// A hint exists only for a screen of the catalogue.
function test_launch_journey_hint_catalogue()
{
	pg_assert_same('hint:view_pages', pg_launch_journey_hint_key('view_pages'));
	pg_assert_same('', pg_launch_journey_hint_key('edit_page'), 'outside the catalogue');
	pg_assert_same('', pg_launch_journey_hint_markup('edit_page'));

	$html = pg_launch_journey_hint_markup('view_orders');
	pg_assert_true(strpos($html, 'alert theme-info small') !== false, 'info alert');
	pg_assert_same(1, substr_count($html, 'data-pg-hint-off>'), 'one "Don\'t show again"');
	pg_assert_true(strpos($html, '"tour_seen"') !== false, 'marked through tour_seen');

	foreach (pg_launch_journey_screens() as $key => $screen) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $key . '.php'), $key . ' is a screen');
		pg_assert_true($screen['hint'] !== '', $key . ' hint');
	}
}

// pg_tour_mark() takes a tour key or a catalogue screen's hint key, and the
// list keeps a hint key once beside the tours.
function test_launch_journey_tour_keys()
{
	pg_assert_true(pg_tour_key_allowed('shell.6'), 'tour');
	pg_assert_true(pg_tour_key_allowed('hint:view_pages'), 'hint');
	pg_assert_false(pg_tour_key_allowed('hint:edit_page'), 'hint outside the catalogue');
	pg_assert_false(pg_tour_key_allowed('hint:view_pages.1'), 'hint with a version');
	pg_assert_false(pg_tour_key_allowed('shell'), 'tour without a version');
	pg_assert_false(pg_tour_key_allowed("hint:view_pages',x"), 'quote');

	pg_assert_same(array('hint:view_pages', 'shell.6'), pg_tour_list_marked(array('hint:view_pages', 'shell.5'), 'shell.6'), 'older tour dropped');
	pg_assert_same(array('shell.6', 'hint:view_pages'), pg_tour_list_marked(array('hint:view_pages', 'shell.6'), 'hint:view_pages'), 'hint once');
	pg_assert_same(array('hint:view_pages', 'hint:view_orders'), pg_tour_list_marked(array('hint:view_pages'), 'hint:view_orders'), 'hints side by side');
}
