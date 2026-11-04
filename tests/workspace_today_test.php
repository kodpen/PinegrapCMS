<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Today page in the new dashboard's design
 * (includes/workspace/home.php, drawHome() and todayCard() in
 * assets/js/workspace.js, assets/css/ws_today.src.css): the card face taken
 * from the dashboard look, the stylesheets, the dashboard container, the
 * greeting card and the card frame every card is drawn in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// The body of one method of the channel screen in workspace.js, or ''.
function workspace_today_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        ' . preg_quote($name, '/') . ': function \([^)]*\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_today_look_from_the_dashboard()
{
	pg_assert_same(array('style' => 'aurora', 'calm' => 1), ws_today_look(array('style' => 'aurora', 'backdrop' => 'mesh', 'drawn' => 'mesh', 'dense' => true, 'calm' => true)), 'the face and calm are the dashboard\'s');
	pg_assert_same(array('style' => 'flat', 'calm' => 0), ws_today_look(array('style' => 'bogus')), 'an unknown face falls back to flat');
	pg_assert_same(array('style' => 'flat', 'calm' => 0), ws_today_look(null), 'no look, flat');
	pg_assert_false(array_key_exists('dense', ws_today_look(array('style' => 'neon', 'dense' => true))), 'the Workspace keeps its own density');
}

function test_workspace_today_stylesheets()
{
	$bundles = pg_css_bundles();

	pg_assert_same('assets/css/ws_today.src.css', $bundles['assets/css/ws_today.min.css'] ?? '', 'Today\'s stylesheet is built');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace.php');
	pg_assert_contains("'head' => ws_today_stylesheets(),", $page, 'the screen links the stylesheets');
	pg_assert_contains("'today_look'   => ws_today_look_for(\$user),", $page, 'the screen hands the look to the script');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/ws_today.src.css');

	foreach (array('Waiting for you', 'Today and overdue', 'Calendar · today', 'What happened in the channels', 'Latest decisions', 'Channels you could join', 'What is the Workspace?') as $section) {
		pg_assert_contains('── ' . $section . ' ', $css, $section . ' has its section');
	}

	pg_assert_false((bool) preg_match('/var\(--bs-/', $css), 'no Bootstrap variables');
}

function test_workspace_today_drawn_in_dashboard_cards()
{
	$draw = workspace_today_test_method('drawHome');

	pg_assert_contains("el('div', 'pg-dash ws-today'", $draw, 'the page is the dashboard container');
	pg_assert_contains("setAttribute('data-widget-theme', look.style", $draw, 'in the person\'s card face');
	pg_assert_contains("'card pg-dash-card pg-dash-greeting ws-today-greeting'", $draw, 'the greeting is the dashboard\'s greeting card');
	pg_assert_contains("'pg-dash-btn pg-dash-btn-main', t('home_new_channel')", $draw, 'New channel is the main button');
	pg_assert_contains("button('pg-dash-btn', t('home_new_private')", $draw, 'New private channel stays');
	pg_assert_contains('(CFG.guests || {}).can_host', $draw, 'a guest conversation where allowed');
	pg_assert_contains("el('div', 'pg-dash-grid ws-today-grid')", $draw, 'the cards sit in the dashboard grid');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_false(strpos($source, 'homeCard(') !== false, 'every card is drawn in the dashboard frame');

	$cards = array(
		'homeWaiting'   => "todayCard('waiting',",
		'homeDue'       => "todayCard('due',",
		'homeCalendar'  => "todayCard('calendar',",
		'homeDigest'    => "todayCard('digest',",
		'homeDecisions' => "todayCard('decisions',",
		'homeJoin'      => "todayCard('join',",
	);

	foreach ($cards as $method => $frame) {
		pg_assert_contains($frame, workspace_today_test_method($method), $method . ' draws its frame');
	}

	pg_assert_contains("'card pg-dash-card pg-dash-cardbox ws-today-card ws-home-about'", workspace_today_test_method('homeAbout'), 'What is the Workspace? is a card of the grid');
	pg_assert_contains('card.badge', workspace_today_test_method('homeCount'), 'a count goes into the frame\'s badge');
}

function test_workspace_today_waiting_and_due_cards_in_the_dashboard_rows()
{
	$waiting = workspace_today_test_method('homeWaiting');
	$due = workspace_today_test_method('homeDue');

	pg_assert_contains("el('div', 'ws-today-rows')", $waiting, 'waiting rows sit in one list');
	pg_assert_contains("'pg-dash-card-empty', t('home_waiting_empty')", $waiting, 'an empty card says so in the dashboard\'s words');
	pg_assert_false(strpos($waiting . $due, 'ws-home-') !== false, 'no old Overview classes left in the two cards');

	pg_assert_contains("'pg-dash-chips ws-today-chips'", $due, 'the counts are the dashboard\'s chips');
	pg_assert_contains("hotLink.href = CFG.urls.tasks + '?urgent=1';", $due, 'pressing links to the pressing list');
	pg_assert_contains("hotLink.setAttribute('data-tone', 'warn');", $due);
	pg_assert_contains("late.setAttribute('data-tone', 'bad');", $due, 'overdue in the bad tone');

	foreach (array('workspace_approvals.js', 'workspace_acks.js') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
		$rows = substr($source, (int) strpos($source, 'function homeRows'));

		pg_assert_contains("h.button('ws-today-row ws-today-unread')", $rows, $file . ' draws Today\'s rows');
		pg_assert_contains("el('span', 'ws-today-sign')", $rows, $file . ' puts its icon in a face-sized box');
		pg_assert_false(strpos($rows, 'ws-home-') !== false, $file . ' uses no old Overview classes');
	}

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/ws_today.src.css');

	foreach (array('.ws-today .ws-today-row {', '.ws-today .ws-today-sign {', '.ws-today .ws-today-task-head {', '.ws-today .ws-today-task-done .ws-today-row-title {') as $rule) {
		pg_assert_contains($rule, $css, $rule . ' is styled');
	}
}

function test_workspace_today_calendar_rows()
{
	$calendar = workspace_today_test_method('homeCalendar');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/ws_today.src.css');

	pg_assert_contains("el('ul', 'pg-dash-items ws-today-events')", $calendar, 'the events are the dashboard\'s list');
	pg_assert_contains("'pg-dash-item ws-today-event' + (item.now ? ' is-now' : '') + (item.past ? ' is-past' : '')", $calendar, 'a row marks the one going on now and the ones over');
	pg_assert_contains("el('p', 'pg-dash-item-none', t('home_calendar_empty'))", $calendar, 'an empty day says so in the dashboard\'s words');
	pg_assert_contains('this.homeCount(card, items.length);', $calendar, 'the badge counts the events');
	pg_assert_contains("'pg-dash-btn pg-dash-item-action ws-today-event-join'", $calendar, 'the call to join is a dashboard button');
	pg_assert_false(strpos($calendar, 'ws-home-') !== false, 'no old overview class is drawn');

	foreach (array('.ws-today-event-time', '.ws-today-event.is-now', '.ws-today-event.is-past', '.ws-today-event-join') as $rule) {
		pg_assert_contains($rule, $css, $rule . ' is styled');
	}
}

// Every helper the feature files get (featureHelpers()) is defined in the
// screen's script: building the list threw "homeCard is not defined" when a
// channel opened, once the old home card was gone and the list still named it.
function test_workspace_today_feature_helpers_defined()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($source, "\n    function featureHelpers() {");
	pg_assert_true($start !== false, 'the helpers');
	$block = (string) substr($source, (int) $start, (int) strpos($source, "\n    }\n", (int) $start) - (int) $start);
	preg_match_all('~(\w+): (\w+)[,\s]~', $block, $pairs, PREG_SET_ORDER);
	pg_assert_true(count($pairs) > 15, 'read');

	foreach ($pairs as $pair) {
		$name = $pair[2];

		if ($name === 'function') {
			continue;
		}

		$defined = preg_match('~(function ' . $name . '\(|var ' . $name . '\b|, ' . $name . ' = )~', $source) === 1;
		pg_assert_true($defined, $pair[1] . ': ' . $name . ' is defined');
	}
}
