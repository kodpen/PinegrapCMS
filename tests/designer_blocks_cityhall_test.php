<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the City Hall block family (assets/js/style_designer_blocks_
 * cityhall.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every entrance kept by
 * the interaction normalizer; the hero's six task tiles and citizen line,
 * the service guide's facts and documents, the agenda's numbered items
 * with a status in an icon and a word; its rules reaching the block
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

function test_designer_blocks_cityhall_family_registers()
{
	$got = pg_test_designer_family_run('cityhall');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('cityhall-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('City Hall', $got['group'], 'its own palette group');
	pg_assert_same(array('cityhall-hero', 'cityhall-service', 'cityhall-council'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_cityhall_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('cityhall');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the
		// others are sections under a page's own h1.
		$top = $ctype === 'cityhall-hero' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pcy|cy|pai|ai)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['cityhall-hero'];
	pg_assert_same(array('h1'), $hero['headings'], 'the hero\'s only heading is the page title');
	pg_assert_same(6, count(array_keys($hero['classes'], 'pg-cityhall-task', true)), 'six task tiles');
	pg_assert_same(6, count(array_keys($hero['classes'], 'stretched-link', true)), 'each tile is one link');
	pg_assert_true(in_array('Report a problem', $hero['texts'], true), 'a task is named');
	pg_assert_true(in_array('Citizen line', $hero['texts'], true), 'the citizen line under the tasks');
	pg_assert_same(2, count($hero['fx']), 'the words rise, then the tiles');

	$service = $got['blocks']['cityhall-service'];
	pg_assert_true(in_array('pg-cityhall-guide', $service['classes'], true), 'the facts rule');
	foreach (array('Who applies', 'Where to apply', 'How long it takes', 'Fee', 'Title deed', 'Responsible Department') as $text) {
		pg_assert_true(in_array($text, $service['texts'], true), 'the service guide says ' . $text);
	}
	pg_assert_same(2, count(array_keys($service['headings'], 'h3', true)), 'what to bring and the department are h3');

	$council = $got['blocks']['cityhall-council'];
	pg_assert_true(in_array('pg-cityhall-agenda', $council['classes'], true), 'the agenda rule');
	pg_assert_same(4, count(array_keys($council['classes'], 'pg-cityhall-no', true)), 'four numbered items');
	pg_assert_same(array_fill(0, 4, 'icon+heading'), $council['badges'], 'each status is an icon and a word, never colour alone');
	pg_assert_true(in_array('Planning and Zoning Committee', $council['texts'], true), 'an item names its committee');
}

// The nav names itself and the numbers can be dialled: read from the file,
// as the run flattens only classes and texts.
function test_designer_blocks_cityhall_names_and_links()
{
	$js = pg_test_designer_family_file('cityhall');
	pg_assert_true(strpos($js, "kit.tag('nav'") !== false, 'the tasks are a nav');
	pg_assert_true(strpos($js, "{ name: 'aria-label', value: _sdT('Common tasks') }") !== false, 'the nav is named');
	pg_assert_same(2, preg_match_all("/'tel:\\+90555\\d+'/", $js), 'the citizen line and the department can be dialled');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_cityhall_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('cityhall'));
	pg_assert_same(array('pg-cityhall-task', 'pg-cityhall-guide', 'pg-cityhall-agenda'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-cityhall-task'), $part['needs']['cityhall-hero'], 'the hero needs the tile');
	pg_assert_same(array('pg-cityhall-guide'), $part['needs']['cityhall-service'], 'the guide needs the facts');
	pg_assert_same(array('pg-cityhall-agenda'), $part['needs']['cityhall-council'], 'the agenda needs its rule');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-cityhall-agenda']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
