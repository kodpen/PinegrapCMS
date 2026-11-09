<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel block family (assets/js/style_designer_blocks_
 * travel.js), run under Node by helpers/designer_family_run.php (skipped on
 * a machine without Node): its own palette group, a section per block with
 * one h2 and no skipped level, no fixed colour or template class, every
 * {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the itinerary's four days on the route,
 * each with its night and meals, the trip's facts and price; the
 * destination guide's region badge, four seasons with the good ones
 * marked by a word and a check, its facts; the rules reaching the block
 * stylesheet.
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

function test_designer_blocks_travel_family_registers()
{
	$got = pg_test_designer_family_run('travel');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('travel-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Travel', $got['group'], 'its own palette group');
	pg_assert_same(array('travel-itinerary', 'travel-destination'), array_keys($got['blocks']), 'the two blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_travel_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('travel');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		pg_assert_same(array(), array_values(array_diff($b['headings'], array('h2', 'h3'))), $ctype . ' uses only h2 and h3');
		pg_assert_same('h2', $b['headings'][0], $ctype . ' starts with its h2');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(ptv|tv)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$trip = $got['blocks']['travel-itinerary'];
	pg_assert_true(in_array('pg-travel-days', $trip['classes'], true), 'the route rule');
	pg_assert_same(4, count(array_keys($trip['classes'], 'pg-travel-day', true)), 'a marker for each of four days');
	pg_assert_same(array('h2', 'h3', 'h3', 'h3', 'h3', 'h3'), $trip['headings'], 'the tour, its four days, then the trip at a glance');
	foreach (array('1', '4', 'Arrival in Göreme', 'Farewell breakfast', 'Cave hotel in Göreme', 'Breakfast and dinner', 'Duration', 'Group size', 'Departures', 'Every Friday', 'Starting from', '$890', 'per traveller', 'Book a Tour') as $text) {
		pg_assert_true(in_array($text, $trip['texts'], true), 'the itinerary says ' . $text);
	}

	$dest = $got['blocks']['travel-destination'];
	pg_assert_same(array('icon+heading'), $dest['badges'], 'the region is an icon and a word');
	pg_assert_true(in_array('pg-travel-seasons', $dest['classes'], true), 'the seasons rule');
	pg_assert_same(2, count(array_keys($dest['classes'], 'pg-travel-best', true)), 'two good seasons tinted');
	pg_assert_same(2, count(array_keys($dest['texts'], 'Best time', true)), 'each good season also says so in words');
	foreach (array('Cappadocia', 'Mediterranean', 'Best time to go', 'Spring', 'Summer', 'Autumn', 'Winter', 'Best time', 'Getting there', 'Entry visa', 'See the tours here') as $text) {
		pg_assert_true(in_array($text, $dest['texts'], true), 'the destination guide says ' . $text);
	}
}

// The days and the facts are real lists: read from the file, as the run
// flattens only classes and texts.
function test_designer_blocks_travel_lists()
{
	$js = pg_test_designer_family_file('travel');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-travel-days") !== false, 'the days are an ordered list');
	pg_assert_same(2, preg_match_all("/kit\\.tag\\('dl', 'pg-travel-glance/", $js), 'the facts are description lists');
	pg_assert_true(strpos($js, "kit.tag('ul', 'pg-travel-seasons") !== false, 'the seasons are a list');
	pg_assert_true(strpos($js, "kit.tag('aside'") !== false, 'the trip at a glance is an aside');
	pg_assert_true(strpos($js, "best ? [\n                kit.icon('bi-check2-circle'") !== false, 'a good season carries a check beside its word');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_travel_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('travel'));
	pg_assert_same(array('pg-travel-days', 'pg-travel-day', 'pg-travel-glance', 'pg-travel-figure', 'pg-travel-seasons'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-travel-days', 'pg-travel-day', 'pg-travel-glance', 'pg-travel-figure'), $part['needs']['travel-itinerary'], 'the itinerary needs its route, markers, facts and figures');
	pg_assert_same(array('pg-travel-seasons', 'pg-travel-glance'), $part['needs']['travel-destination'], 'the guide needs its seasons and facts');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-travel-seasons']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
