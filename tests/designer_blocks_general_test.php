<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the general block families that extend the core's Hours &
 * Locations group (assets/js/style_designer_blocks_hours.js and
 * style_designer_blocks_locations.js), run under Node by
 * helpers/designer_family_run.php (skipped on a machine without Node). A
 * general family opens no palette group of its own; each block draws a
 * section with one h2 and no skipped heading level, no fixed colour class,
 * every {var} slot filled, no year, an entrance the interaction normalizer
 * keeps, and the structure its name promises.
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

function test_designer_blocks_general_families_join_core_groups()
{
	$expect = array('hours' => array('hours-', array('hours-seasons')), 'locations' => array('locations-', array('locations-spaces', 'locations-floors')));
	foreach ($expect as $name => $want) {
		$got = pg_test_designer_family_run($name);
		if ($got === null) {
			pg_assert_true(true, 'Node is not installed: the run is skipped');
			return;
		}
		pg_assert_same($want[0], $got['prefix'], $name . ': a core group\'s prefix');
		pg_assert_same(null, $got['group'], $name . ': no palette group of its own');
		pg_assert_same($want[1], array_keys($got['blocks']), $name . ': its blocks');
		pg_assert_same(null, $got['unknown'], $name . ': an unknown type draws nothing');
	}
}

function test_designer_blocks_general_blocks_draw_sound_trees()
{
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	$blocks = array();
	foreach (array('hours', 'locations') as $name) {
		$got = pg_test_designer_family_run($name);
		if ($got === null) {
			pg_assert_true(true, 'Node is not installed: the run is skipped');
			return;
		}
		$blocks += $got['blocks'];
	}
	foreach ($blocks as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range(2, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array('ok'), $b['fx'], $ctype . '\'s one entrance survives the normalizer');
	}

	$hours = $blocks['hours-seasons'];
	pg_assert_same(array('th:col', 'th:col', 'th:col'), array_slice($hours['cells'], 0, 3), 'a column head per season after the day column');
	pg_assert_same(5, count(array_keys($hours['cells'], 'th:row', true)), 'a row head per day');
	pg_assert_same(10, count(array_keys($hours['cells'], 'td:', true)), 'an opening time per day and season');
	pg_assert_true(in_array('Closed', $hours['texts'], true), 'a closed day says so in a word');
	pg_assert_true(in_array('table-responsive', $hours['classes'], true), 'the table scrolls sideways on a phone');

	$spaces = $blocks['locations-spaces'];
	pg_assert_same(3, count(array_keys($spaces['headings'], 'h3', true)), 'three space cards');
	pg_assert_same(9, count(array_keys($spaces['classes'], 'badge', true)), 'three amenities on each card');
	pg_assert_true(in_array('Up to 120 guests', $spaces['texts'], true) && in_array('180 m²', $spaces['texts'], true), 'a space says how many it holds and its size');
	pg_assert_true(in_array('pg-ratio-16x9', $spaces['classes'], true) && in_array('object-fit-cover', $spaces['classes'], true), 'pictures keep one shape');

	$floors = $blocks['locations-floors'];
	pg_assert_same(5, count(array_keys($floors['headings'], 'h3', true)), 'a heading per floor');
	pg_assert_same(5, count(array_keys($floors['classes'], 'pg-floor-level', true)), 'a level square per floor');
	pg_assert_same(15, count(array_keys($floors['classes'], 'badge', true)), 'what is on each floor, as pills');
	pg_assert_true(in_array('Everyone, 7 am to 10 pm', $floors['texts'], true) && in_array('Members, around the clock', $floors['texts'], true), 'who may go up, in words');
	pg_assert_true(in_array('Roof', $floors['texts'], true) && in_array('0', $floors['texts'], true), 'from the roof down to the ground floor');
}

// The rules the blocks need are read from the family files by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_general_rules_reach_the_stylesheet()
{
	$hours = _pg_designer_block_css_parse(pg_test_designer_family_file('hours'));
	pg_assert_same(array('pg-hours-table'), array_keys($hours['rules']), 'the hours family\'s rule');
	pg_assert_same(array('pg-hours-table'), $hours['needs']['hours-seasons'], 'seasonal hours need it');
	$spaces = _pg_designer_block_css_parse(pg_test_designer_family_file('locations'));
	pg_assert_same(array('pg-space-facts', 'pg-floor-level'), array_keys($spaces['rules']), 'the locations family\'s rules');
	pg_assert_same(array('pg-floor-level'), $spaces['needs']['locations-floors'], 'the floor directory needs its level square');
	pg_assert_same(array('pg-ratio-16x9', 'pg-space-facts'), $spaces['needs']['locations-spaces'], 'the space cards need the core picture ratio and their own rule');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-hours-table'], $source['rules']['pg-space-facts'], $source['rules']['pg-floor-level']), 'the block stylesheet holds the rules');
}
