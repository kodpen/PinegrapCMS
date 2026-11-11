<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery block family (assets/js/style_designer_blocks_
 * bakery.js), run under Node by helpers/designer_family_run.php (skipped on
 * a machine without Node): its own palette group, a section per block with
 * one top heading and no skipped level, no fixed colour or template class,
 * every {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the hero's oven times and its order tag, the cake
 * guide's four sizes drawn to scale with their servings and prices, and the
 * sponges and fillings; its rules reaching the block stylesheet.
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

function test_designer_blocks_bakery_family_registers()
{
	$got = pg_test_designer_family_run('bakery');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('bakery-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Bakery', $got['group'], 'its own palette group');
	pg_assert_same(array('bakery-hero', 'bakery-cakes'), array_keys($got['blocks']), 'the two blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_bakery_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('bakery');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the cake
		// guide is a section under a page's own h1.
		$top = $ctype === 'bakery-hero' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pbk|bky)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['bakery-hero'];
	foreach (array('pg-bakery-photo', 'pg-bakery-hang', 'pg-bakery-tag', 'pg-bakery-oven') as $class) {
		pg_assert_true(in_array($class, $hero['classes'], true), 'the hero writes ' . $class);
	}
	pg_assert_same(array('icon+heading+time'), $hero['badges'], 'when the ovens are lit: an icon, words and a time');
	pg_assert_same(3, count(array_keys($hero['headings'], 'h3', true)), 'three batches out of the oven');
	foreach (array('Fresh from the oven, every morning', 'Out of the oven today', '07:00', '08:30', '11:00', 'Order ahead', '14:00', 'Last orders for the next day', 'See the daily bake', 'Order a Cake') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(4, count($hero['fx']), 'the words rise, the picture grows, the batches follow, the tag swings in');

	$cakes = $got['blocks']['bakery-cakes'];
	pg_assert_same(6, count(array_keys($cakes['headings'], 'h3', true)), 'four sizes, the sponges and the fillings');
	pg_assert_same(4, count(array_keys($cakes['classes'], 'pg-bakery-cake', true)), 'a drawing per size');
	pg_assert_same(5, count(array_keys($cakes['classes'], 'pg-bakery-layer', true)), 'one layer a cake, two for the tiers');
	foreach (array('pg-bakery-d15', 'pg-bakery-d20', 'pg-bakery-d25') as $class) {
		pg_assert_true(in_array($class, $cakes['classes'], true), 'a layer drawn to scale: ' . $class);
	}
	foreach (array('15 cm round', '20 cm round', '25 cm round', 'Two tiers', '8 to 10 slices', '40 to 60 slices', 'Starting from', '$45', '$240', 'Sponges', 'Fillings', 'Red velvet', 'Lemon curd', 'Order a Cake') as $text) {
		pg_assert_true(in_array($text, $cakes['texts'], true), 'the cake guide says ' . $text);
	}
	pg_assert_same(4, count(array_keys($cakes['texts'], 'Starting from', true)), 'a price per size');
	pg_assert_same(3, count($cakes['fx']), 'the header fades, the cakes grow one by one, the choices follow');
}

// The batches are an ordered list of times and the drawings are hidden
// from a screen reader, which reads the size in words: read from the file,
// as the run flattens only classes and texts.
function test_designer_blocks_bakery_markup_from_the_file()
{
	$js = pg_test_designer_family_file('bakery');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-bakery-oven") !== false, 'the batches are an ordered list');
	foreach (array('04:00', '14:00') as $time) {
		pg_assert_true(strpos($js, "kit.time('" . $time . "', '" . $time . "'") !== false, $time . ' is a <time> element');
	}
	pg_assert_true(strpos($js, "kit.time(time, time, 'd-block fs-4") !== false, 'a batch\'s time is a <time> element');
	foreach (array('07:00', '08:30', '11:00') as $time) {
		pg_assert_true(strpos($js, "batch('" . $time . "', ") !== false, 'a batch at ' . $time);
	}
	pg_assert_true(strpos($js, "kit.div('pg-bakery-cake mb-3', _sdT('Drawing'), layers, [{ name: 'aria-hidden', value: 'true' }])") !== false, 'the drawing is hidden from a screen reader');
	pg_assert_true(strpos($js, "kit.tag('ol', 'list-unstyled row row-cols-2 row-cols-lg-4") !== false, 'the sizes are an ordered list, smallest first');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_bakery_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('bakery'));
	pg_assert_same(array('pg-bakery-photo', 'pg-bakery-tag', 'pg-bakery-oven', 'pg-bakery-cake'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-bakery-photo', 'pg-bakery-tag', 'pg-bakery-oven'), $part['needs']['bakery-hero'], 'the hero needs the frame, the tag and the oven times');
	pg_assert_same(array('pg-bakery-cake'), $part['needs']['bakery-cakes'], 'the cake guide needs the drawing');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-bakery-cake']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
	}
	pg_assert_true(strpos($part['rules']['pg-bakery-tag'], 'prefers-reduced-motion') !== false, 'the tag stops swinging under reduced motion');
}
