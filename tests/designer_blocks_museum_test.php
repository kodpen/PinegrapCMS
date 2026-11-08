<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum block family (assets/js/style_designer_blocks_
 * museum.js), run under Node by helpers/designer_family_run.php (skipped on
 * a machine without Node): its own palette group, a section per block with
 * one top heading and no skipped level, no fixed colour or template class,
 * every {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the exhibition hero's facts and the other
 * exhibitions on, the work's wall label and its three numbered details, the
 * trail's four numbered stops with their rooms as an icon and a word; its
 * rules reaching the block stylesheet.
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

function test_designer_blocks_museum_family_registers()
{
	$got = pg_test_designer_family_run('museum');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('museum-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Museum', $got['group'], 'its own palette group');
	pg_assert_same(array('museum-exhibition', 'museum-object', 'museum-trail'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_museum_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('museum');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The exhibition hero is a page's way in, so its title is the h1;
		// the others are sections under a page's own h1.
		$top = $ctype === 'museum-exhibition' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pmz|mz)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['museum-exhibition'];
	pg_assert_true(in_array('pg-museum-poster', $hero['classes'], true), 'the poster frame');
	pg_assert_true(in_array('pg-museum-facts', $hero['classes'], true), 'the facts rule');
	foreach (array('Painting the Light', 'When', 'Where', 'Curator', 'Admission', 'Made of Clay', 'Faces of the City') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the exhibition hero says ' . $text);
	}
	pg_assert_same(2, count(array_keys($hero['headings'], 'h3', true)), 'two other exhibitions on');
	pg_assert_same(3, count($hero['fx']), 'the words rise, the poster fades, the others follow');

	$object = $got['blocks']['museum-object'];
	pg_assert_true(in_array('pg-museum-label', $object['classes'], true), 'the wall label');
	pg_assert_same(3, count(array_keys($object['classes'], 'pg-museum-no', true)), 'three numbered details');
	pg_assert_same(3, count(array_keys($object['headings'], 'h3', true)), 'each detail is named');
	foreach (array('Oil on canvas', 'Late 19th century', 'Gallery 3') as $text) {
		pg_assert_true(in_array($text, $object['texts'], true), 'the label says ' . $text);
	}

	$trail = $got['blocks']['museum-trail'];
	pg_assert_true(in_array('pg-museum-trail', $trail['classes'], true), 'the trail rule');
	pg_assert_same(4, count(array_keys($trail['classes'], 'pg-museum-no', true)), 'four numbered stops');
	pg_assert_same(array_fill(0, 4, 'icon+heading'), $trail['badges'], 'each room is an icon and a word');
	pg_assert_true(in_array('Gallery 7', $trail['texts'], true), 'a stop names its room');
}

// The stops and the details are ordered lists: read from the file, as the
// run flattens only classes and texts.
function test_designer_blocks_museum_ordered_lists()
{
	$js = pg_test_designer_family_file('museum');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-museum-trail") !== false, 'the trail is an ordered list');
	pg_assert_true(strpos($js, "kit.tag('ol', 'list-unstyled mb-4', _sdT('Details')") !== false, 'the details are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('figcaption', 'pg-museum-label") !== false, 'the wall label is the work\'s caption');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_museum_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('museum'));
	pg_assert_same(array('pg-museum-poster', 'pg-museum-facts', 'pg-museum-label', 'pg-museum-no', 'pg-museum-trail'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-museum-poster', 'pg-museum-facts'), $part['needs']['museum-exhibition'], 'the hero needs the poster and the facts');
	pg_assert_same(array('pg-museum-label', 'pg-museum-no'), $part['needs']['museum-object'], 'the work needs its label and numbers');
	pg_assert_same(array('pg-museum-no', 'pg-museum-trail'), $part['needs']['museum-trail'], 'the trail needs its numbers and route');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-museum-trail']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
