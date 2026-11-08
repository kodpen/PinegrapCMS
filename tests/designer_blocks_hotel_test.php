<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Hotel block family (assets/js/style_designer_blocks_
 * hotel.js), run under Node by helpers/designer_family_run.php (skipped on
 * a machine without Node): its own palette group, a section per block with
 * one h2 and no skipped level, no fixed colour or template class, every
 * {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the room showcase's three pictures, view badge,
 * facts, rate in separate nodes and amenities; the stay clock's arrival
 * and departure times and the stay between them; its rules reaching the
 * block stylesheet.
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

function test_designer_blocks_hotel_family_registers()
{
	$got = pg_test_designer_family_run('hotel');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('hotel-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Hotel', $got['group'], 'its own palette group');
	pg_assert_same(array('hotel-room', 'hotel-stay'), array_keys($got['blocks']), 'the two blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_hotel_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('hotel');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		pg_assert_same(array(), array_values(array_diff($b['headings'], array('h2', 'h3'))), $ctype . ' uses only h2 and h3');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pht|ht)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$room = $got['blocks']['hotel-room'];
	pg_assert_true(in_array('pg-hotel-gallery', $room['classes'], true), 'the gallery rule');
	pg_assert_same(3, count(array_keys($room['classes'], 'pg-hotel-shot', true)), 'three pictures of the room');
	pg_assert_same(1, count(array_keys($room['classes'], 'pg-hotel-main', true)), 'one of them the large one');
	pg_assert_same(array('icon+heading'), $room['badges'], 'the view is an icon and a word');
	foreach (array('Guests', 'Floor Area', 'Beds', 'Room view', 'Starting from', '$240', '/ night', 'Private terrace', 'Ask for This Room') as $text) {
		pg_assert_true(in_array($text, $room['texts'], true), 'the room showcase says ' . $text);
	}
	pg_assert_same(array('h2', 'h3'), $room['headings'], 'the room\'s name, then what is in it');

	$stay = $got['blocks']['hotel-stay'];
	pg_assert_true(in_array('pg-hotel-clock', $stay['classes'], true), 'the clock rule');
	pg_assert_same(3, count(array_keys($stay['classes'], 'pg-hotel-end', true)), 'arrival, the stay and departure');
	pg_assert_same(3, count(array_keys($stay['headings'], 'h3', true)), 'each a h3');
	foreach (array('15:00', '11:00', 'Arrival', 'Departure', 'During your stay', 'Breakfast from 7:30 to 10:30') as $text) {
		pg_assert_true(in_array($text, $stay['texts'], true), 'the stay clock says ' . $text);
	}
}

// The times are machine-readable and the reception can be dialled: read
// from the file, as the run flattens only classes and texts.
function test_designer_blocks_hotel_times_and_links()
{
	$js = pg_test_designer_family_file('hotel');
	pg_assert_true(strpos($js, 'kit.time(time, time') !== false, 'arrival and departure are <time datetime> nodes');
	pg_assert_same(1, preg_match_all("/'tel:\\+90555\\d+'/", $js), 'the reception can be dialled');
	pg_assert_true(strpos($js, "kit.tag('figure', 'pg-hotel-gallery") !== false, 'the pictures are one figure');
	pg_assert_true(strpos($js, "kit.tag('dl', 'pg-hotel-facts") !== false, 'the facts are a description list');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_hotel_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('hotel'));
	pg_assert_same(array('pg-hotel-gallery', 'pg-hotel-facts', 'pg-hotel-figure', 'pg-hotel-clock'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-hotel-gallery', 'pg-hotel-facts', 'pg-hotel-figure'), $part['needs']['hotel-room'], 'the room needs its gallery, facts and figures');
	pg_assert_same(array('pg-hotel-clock', 'pg-hotel-figure'), $part['needs']['hotel-stay'], 'the stay needs its clock and figures');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-hotel-clock']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
