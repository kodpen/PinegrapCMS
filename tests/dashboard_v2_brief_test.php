<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the brief on the new dashboard: the part of the day its mark
 * follows, the tips worded for each dashboard, the markup under the
 * greeting and beside the date line, the top-section switch, and the page
 * script in the bundle.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/presets.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');

// The mark changes at the hours the greeting changes at: night until 05:00
// and from 23:00, evening from 18:00.
function test_dashboard_brief_part_hours()
{
	$cases = array(
		0  => 'late',
		4  => 'late',
		5  => 'morning',
		11 => 'morning',
		12 => 'afternoon',
		17 => 'afternoon',
		18 => 'evening',
		22 => 'evening',
		23 => 'late',
	);

	foreach ($cases as $hour => $part) {
		pg_assert_same($part, pg_dashboard_brief_part($hour), 'hour ' . $hour);
	}
}

// The old dashboard's tips speak of widgets; the new one's of Edit
// dashboard, and only to a person whose layout is not locked.
function test_dashboard_brief_tips_per_dashboard()
{
	$grip = h(lang('Did you know the widgets can be dragged by the grip at the right of their headers?'));
	$theme = h(lang('You will find the widget theme settings in Site Settings, under Appearance.'));
	$look = h(lang('Card style, backdrop and density are yours to choose: Edit dashboard, then Look.'));
	$move = h(lang('In Edit dashboard the cards move by their grip, or with the arrow keys once the grip has focus.'));
	$channel = h(lang('A message in a channel can become a task from its right-click menu.'));

	$old = pg_dashboard_brief_tips(1, false, array());
	pg_assert_true(in_array($grip, $old, true), 'old: the widget grip');
	pg_assert_true(in_array($theme, $old, true), 'old: the widget theme, for a designer');
	pg_assert_same(false, in_array($theme, pg_dashboard_brief_tips(2, false, array()), true), 'old: no theme tip for a manager');

	// The grip is drawn only where welcome.php's $widget_movable is true
	// (role < 2), so a Manager or a User is not told to drag by it (#8995).
	pg_assert_true(in_array($grip, pg_dashboard_brief_tips(0, false, array()), true), 'old: the widget grip for an administrator');
	pg_assert_same(false, in_array($grip, pg_dashboard_brief_tips(2, false, array()), true), 'old: no grip tip for a manager');
	pg_assert_same(false, in_array($grip, pg_dashboard_brief_tips(3, false, array()), true), 'old: no grip tip for a user');
	pg_assert_same(2, count(pg_dashboard_brief_tips(3, false, array())), 'old, user: only the two general tips');
	pg_assert_same(false, in_array($look, $old, true), 'old: nothing about Edit dashboard');

	$new = pg_dashboard_brief_tips(2, false, array('dashboard' => 'v2', 'edit' => true));
	pg_assert_true(in_array($look, $new, true), 'new: the look');
	pg_assert_true(in_array($move, $new, true), 'new: moving cards');

	foreach ($new as $tip) {
		pg_assert_same(false, stripos($tip, 'widget'), 'new: no widget in ' . $tip);
	}

	$locked = pg_dashboard_brief_tips(1, false, array('dashboard' => 'v2', 'edit' => false));
	pg_assert_same(2, count($locked), 'locked: only the two general tips');

	foreach ($locked as $tip) {
		pg_assert_same(false, strpos($tip, 'Edit dashboard'), 'locked: no Edit dashboard in ' . $tip);
	}

	pg_assert_true(in_array($channel, pg_dashboard_brief_tips(3, true, array('dashboard' => 'v2')), true), 'workspace tip for the team');
	pg_assert_same(false, in_array($channel, pg_dashboard_brief_tips(3, false, array('dashboard' => 'v2')), true), 'no workspace tip outside it');
}

// The block under the title carries what the page script reads: its id, the
// part the edit mode hides, the server's time and the countdown words.
function test_dashboard_v2_brief_markup()
{
	$brief = array(
		'icon'  => 'bi-sunrise',
		'line'  => '<span class="pg-brief-line">Quiet so far. <a class="pg-brief-link" href="view_comments.php">2 waiting comments</a></span>',
		'strip' => '<div class="pg-brief-ws" role="group" aria-label="Workspace"><a class="pg-brief-ws-item" href="workspace_tasks.php"></a></div>',
		'next'  => '',
	);

	$html = pg_dashboard_v2_brief_markup($brief, 1700000000);

	pg_assert_contains('id="pg_dash_brief"', $html, 'the id the script reads');
	pg_assert_contains('data-top-part="brief"', $html, 'the part the edit mode hides');
	pg_assert_contains('data-pg-now="1700000000"', $html, 'the server time');
	pg_assert_contains('<i class="bi bi-sunrise"></i>', $html, 'the hour mark');
	pg_assert_contains($brief['line'], $html, 'the sentence as brief.php built it');
	pg_assert_contains($brief['strip'], $html, 'the strip');
	pg_assert_contains('id="pg_dash_brief_again"', $html, 'the button the script listens for');
	pg_assert_contains('aria-label="Say something else"', $html, 'the button is named');
	pg_assert_contains('aria-label="Today&#039;s brief"', $html, 'the block is named');
	pg_assert_contains('<span class="pg-dash-brief-kicker" aria-hidden="true">Today&#039;s brief</span>', $html, 'the name a narrow box shows, not read twice');

	preg_match('/data-pg-until="([^"]*)"/', $html, $found);
	$until = json_decode(html_entity_decode($found[1], ENT_QUOTES, 'UTF-8'), true);
	pg_assert_same('Now', $until['now'], 'now');
	pg_assert_same('in %1 min', $until['min'], 'minutes');
	pg_assert_same('in %1 h', $until['h'], 'hours');
	pg_assert_same('in %1 h %2 min', $until['hmin'], 'hours and minutes');

	$html = pg_dashboard_v2_brief_markup(array('icon' => 'bi-x" onclick="alert(1)') + $brief, 1);
	pg_assert_same(false, strpos($html, '" onclick="'), 'the icon class is escaped');

	pg_assert_same('<span class="pg-dash-brief-next is-head" data-top-part="brief"></span>', pg_dashboard_v2_brief_next_markup($brief), 'an empty box when nothing is left today');
	pg_assert_same('<span class="pg-dash-brief-next is-head" data-top-part="brief"><div class="pg-clockbar-next"></div></span>', pg_dashboard_v2_brief_next_markup(array('next' => '<div class="pg-clockbar-next"></div>') + $brief), 'the next meeting inside it');

	// A narrow dashboard shows the copy in the brief's first row, beside the name.
	$html = pg_dashboard_v2_brief_markup(array('next' => '<div class="pg-clockbar-next"></div>') + $brief, 1);
	pg_assert_contains('<span class="pg-dash-brief-head"><span class="pg-dash-brief-kicker" aria-hidden="true">Today&#039;s brief</span><span class="pg-dash-brief-next is-inside" data-top-part="brief"><div class="pg-clockbar-next"></div></span></span>', $html, 'the narrow copy');
}

// The switch is stored and read back, on until switched off; the parts come
// in the order the edit script writes them, which its compare relies on.
function test_dashboard_v2_brief_switch()
{
	$clean = pg_dashboard_v2_layout_clean(array('cards' => array(), 'top' => array('brief' => 0)), null, array(), array(), array(), false);
	pg_assert_same(0, $clean['top']['brief'], 'stored off');
	pg_assert_same(1, $clean['top']['since'], 'the rest stay on');

	$order = array('since', 'actions', 'tiles', 'status', 'brief');
	pg_assert_same($order, array_keys(pg_dashboard_v2_greeting_settings('', false)['top']), 'greeting settings');
	pg_assert_same($order, array_keys($clean['top']), 'clean-up');
	pg_assert_same($order, array_keys(pg_dashboard_v2_preset_layout('corporate', false)['top']), 'preset');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_edit.js');
	pg_assert_contains('{since: 1, actions: 1, tiles: 1, status: 1, brief: 1}', $edit, 'Back to default turns it on, in the same order');
	pg_assert_contains('[data-top-part=\"brief\"]', $edit, 'the edit mode hides both parts');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_panel.js');
	pg_assert_contains('["brief", "since", "actions", "tiles", "status"]', $panel, 'the side panel offers it');
}

// The page script is in the dashboard's bundle and asks for this
// dashboard's wording; the endpoint answers it.
function test_dashboard_v2_brief_script()
{
	$bundle = pg_js_bundles()['assets/js/pg_dash.min.js'];
	pg_assert_true(array_search('assets/js/pg_dash_brief.js', $bundle, true) > array_search('assets/js/pg_dash.js', $bundle, true), 'in the bundle');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_brief.js');
	pg_assert_contains('action: "get_dashboard_brief"', $script, 'the same endpoint as the old dashboard');
	pg_assert_contains('dashboard: "v2"', $script, 'asks for the new wording');
	pg_assert_contains('closest("#pg_dash_brief_again")', $script, 'delegated, so a restored greeting still answers');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/dashboard.php');
	pg_assert_contains("\$request['dashboard'] === 'v2'", $endpoint, 'the endpoint tells the dashboards apart');
	pg_assert_contains('pg_dashboard_v2_brief_data($user, time())', $endpoint, 'and draws the new one as the page does');
}
