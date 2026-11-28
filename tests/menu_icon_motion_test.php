<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the moves of the main menu's row icons: every moving part a
 * drawing names (pg_menu_icon(), data-m) has its move in backend.src.css,
 * played only where more motion is welcome; the Automation flows dot runs
 * only while a flow runs; the Calendars day keeps the site's time for its
 * script, and the Dashboard's clock is gone; Design examples draws the
 * pattern "Menu row icon".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/look_examples.php');

function menu_icon_motion_test_css()
{
	return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css'));
}

// Whether the rule at $offset sits in a prefers-reduced-motion: no-preference
// block: the headers of the blocks open there, read from the start.
function menu_icon_motion_test_in_motion_block($css, $offset)
{
	$stack = array();
	$head = 0;

	for ($i = 0; $i < $offset; $i++) {
		$char = $css[$i];

		if ($char === '{') {
			$stack[] = substr($css, $head, $i - $head);
			$head = $i + 1;
		} elseif ($char === '}') {
			array_pop($stack);
			$head = $i + 1;
		} elseif ($char === ';') {
			$head = $i + 1;
		}
	}

	foreach ($stack as $header) {
		if (strpos($header, 'prefers-reduced-motion: no-preference') !== false) {
			return true;
		}
	}

	return false;
}

function test_menu_icon_every_moving_part_has_its_move()
{
	$css = menu_icon_motion_test_css();
	$menu = pg_test_function_source('output_menu');

	preg_match_all("/\['menu_icon'\] = '([a-z_]+)';/", $menu, $match);
	$moves = array();

	foreach (array_unique($match[1]) as $name) {
		preg_match_all('/ data-m="([a-z0-9]+)"/', pg_menu_icon($name, '', ''), $parts);
		pg_assert_true(count($parts[1]) > 0, $name . ' has a move');

		foreach ($parts[1] as $move) {
			$moves[$move] = true;
		}
	}

	foreach (array_keys($moves) as $move) {
		$at = strpos($css, '.pg-mi [data-m="' . $move . '"] { --pg-mi-k: pg-mi-' . $move . ';');

		pg_assert_true($at !== false, $move . ' names its keyframes');
		pg_assert_true(menu_icon_motion_test_in_motion_block($css, (int) $at), $move . ' only where more motion is welcome');
		pg_assert_contains('@keyframes pg-mi-' . $move . ' {', $css, $move . ' is drawn');
	}

	$play = strpos($css, ':is(#menu .list-group-item, .pg-look-mi-item):is(:hover, :focus-visible) .pg-mi [data-m] {');

	pg_assert_true($play !== false, 'played on the row\'s hover and keyboard focus');
	pg_assert_true(menu_icon_motion_test_in_motion_block($css, (int) $play), 'and stopped by reduced motion');
	pg_assert_contains('animation: var(--pg-mi-k) 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;', substr($css, (int) $play, 200), 'half a second, one curve, once');
}

function test_menu_icon_flows_dot_runs_only_while_a_flow_runs()
{
	$css = menu_icon_motion_test_css();
	$at = strpos($css, '[data-pg-flows-running] .pg-mi [data-l="run"] {');

	pg_assert_true($at !== false, 'the dot runs on a running row');
	pg_assert_true(menu_icon_motion_test_in_motion_block($css, (int) $at), 'only where more motion is welcome');
	pg_assert_contains('animation: pg-mi-run 1.5s', substr($css, (int) $at, 120), 'the live loop');
	pg_assert_contains('0% { transform: translate(10.5px, 6.75px); opacity: 0; }', $css, 'from the connector\'s start');
	pg_assert_contains('70% { transform: translate(17.5px, 14px); opacity: 1; }', $css, 'to its end, by translate');
	pg_assert_false(strpos($css, 'offset-path') !== false, 'no offset-path');
}

function test_menu_icon_calendars_day_keeps_the_site_time()
{
	$svg = pg_menu_icon('calendars', '', '');

	pg_assert_true((bool) preg_match('/ data-pg-day="\d+" data-pg-day-date="\d{4}-\d{2}-\d{2}"/', $svg), 'the time of day and the date');
	pg_assert_contains('>' . date('j') . '</text>', $svg, 'today\'s day');
	pg_assert_false(strpos(pg_menu_icon('dashboard', '', ''), 'data-pg-') !== false, 'the Dashboard keeps no time');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains('function initMenuDay() {', $js, 'the day\'s script');
	pg_assert_false(strpos($js, 'data-pg-clock') !== false, 'the clock\'s script went');
	pg_assert_false(strpos($js, 'initMenuClock') !== false, 'and its call');
}

function test_menu_icon_pattern_in_design_examples()
{
	$names = array();

	foreach (pg_look_examples_patterns('light') as $pattern) {
		$names[$pattern['id']] = $pattern;
	}

	pg_assert_true(isset($names['pattern-menu-icon']), 'the pattern is listed');
	$html = $names['pattern-menu-icon']['html'];

	foreach (array('top', 'content', 'design', 'ecommerce', 'accounting', 'marketing', 'system') as $tone) {
		pg_assert_contains('<span class="pg-mi-tile" data-pg-menu-tone="' . $tone . '">', $html, $tone . ' has a tile');
	}

	pg_assert_same(7, substr_count($html, 'class="pg-look-tile pg-look-mi-item" tabindex="0"'), 'one item a category, reached by the keyboard');
	pg_assert_same(1, substr_count($html, ' data-pg-flows-running'), 'one icon runs');
	pg_assert_same(1, substr_count($html, '<span class="pg-look-mi-row pg-look-mi-open">'), 'the open row');
	pg_assert_same(1, substr_count($html, '<span class="pg-look-mi-row">'), 'beside a row at rest');
}
