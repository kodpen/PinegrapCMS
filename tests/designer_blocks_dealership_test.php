<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Dealership block family (assets/js/style_designer_
 * blocks_dealership.js) and the general CTA: Sticky Bar (style_designer_
 * blocks_cta.js), run under Node by helpers/designer_family_run.php
 * (skipped on a machine without Node): the family's own palette group and
 * the bar in the core's Marketing group; a section per block with one h2
 * and no skipped level, no fixed colour or template class, every {var}
 * slot filled, no year or month, every interaction kept by the normalizer;
 * the showroom panel's photo in the style attribute, its veil read on a
 * dark surface and its three figures; the stock finder's five body styles
 * and four makes; the three ways' tiles; the bar's Sticky interaction; the
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

function test_designer_blocks_dealership_families_register()
{
	$got = pg_test_designer_family_run('dealership');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('dealership-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Car Dealership', $got['group'], 'its own palette group');
	pg_assert_same(array('dealership-showroom', 'dealership-finder', 'dealership-ways'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');

	$cta = pg_test_designer_family_run('cta');
	pg_assert_same('cta-', $cta['prefix'], 'the bar takes the core Marketing group\'s prefix');
	pg_assert_same(null, $cta['group'], 'no palette group of its own');
	pg_assert_same(array('cta-sticky'), array_keys($cta['blocks']), 'the sticky bar');
	pg_assert_same(null, $cta['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_dealership_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('dealership');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$cta = pg_test_designer_family_run('cta');
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] + $cta['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		pg_assert_same(array(), array_values(array_diff($b['headings'], array('h2', 'h3'))), $ctype . ' uses only h2 and h3');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pdl|dl)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an interaction');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
	}

	$panel = $got['blocks']['dealership-showroom'];
	pg_assert_same(array('h2'), $panel['headings'], 'the panel names one vehicle');
	pg_assert_same('style', $panel['attrs'][0]['name'], 'the photo is the section\'s background');
	pg_assert_true(strpos($panel['attrs'][0]['value'], 'background-image: url(') === 0, 'the photo is edited under Background');
	foreach (array('pg-dl-panel', 'pg-dl-veil', 'pg-dl-figures', 'pg-dl-type', 'pg-dl-kicker', 'text-body', 'btn-primary', 'btn-secondary') as $class) {
		pg_assert_true(in_array($class, $panel['classes'], true), 'the panel writes ' . $class);
	}
	pg_assert_same(3, count(array_keys($panel['classes'], 'pg-dl-figure', true)), 'three figures');
	foreach (array('Range', 'Power', 'Cash price', '$41,900', 'Book a Test Drive', 'More Like This') as $text) {
		pg_assert_true(in_array($text, $panel['texts'], true), 'the panel says ' . $text);
	}

	$finder = $got['blocks']['dealership-finder'];
	pg_assert_same(5, count(array_keys($finder['classes'], 'pg-dl-shape', true)), 'five body styles');
	foreach (array('New Cars', 'Used Cars', 'All Cars', 'Hatchback', 'SUV', 'Van', 'Aster', 'Kestrel') as $text) {
		pg_assert_true(in_array($text, $finder['texts'], true), 'the finder says ' . $text);
	}

	$ways = $got['blocks']['dealership-ways'];
	pg_assert_same(array('h2', 'h3', 'h3', 'h3'), $ways['headings'], 'a heading, then a h3 per way');
	pg_assert_same(3, count(array_keys($ways['classes'], 'pg-dl-way', true)), 'three tiles');
	pg_assert_same(3, count(array_keys($ways['classes'], 'pg-shade-bottom', true)), 'each tile\'s words on the core scrim');
	foreach (array('Book a Test Drive', 'Value My Car', 'Ways to Pay') as $text) {
		pg_assert_true(in_array($text, $ways['texts'], true), 'a tile says ' . $text);
	}

	$bar = $cta['blocks']['cta-sticky'];
	pg_assert_same(array('ok'), $bar['fx'], 'the bar\'s one interaction');
	pg_assert_true(in_array('pg-sticky-bar', $bar['classes'], true), 'the bar rule');
	pg_assert_same(array('h2'), $bar['headings'], 'the bar names itself with a h2');
}

// What the run does not flatten: the scrims' dark surface, the Sticky
// interaction on the bar's root and the figures as a description list.
function test_designer_blocks_dealership_file_shapes()
{
	$js = pg_test_designer_family_file('dealership');
	pg_assert_same(2, substr_count($js, "[{ name: 'data-bs-theme', value: 'dark' }]"), 'the panel\'s veil and the tiles\' scrims read as dark surfaces');
	pg_assert_true(strpos($js, "kit.tag('dl', 'pg-dl-figures") !== false, 'the figures are a description list');
	pg_assert_true(strpos($js, "kit.tag('ul', 'pg-dl-shapes") !== false, 'the body styles are a list');
	pg_assert_false((bool) preg_match('/\\bkit\\.img\\(/', $js), 'the photos are backgrounds, not images placed over the canvas');
	$cta = pg_test_designer_family_file('cta');
	pg_assert_true(strpos($cta, "bar.props._fx = { effect: 'sticky' }") !== false, 'the bar sticks');
}

// The rules the blocks need are read from the family files by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_dealership_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('dealership'));
	pg_assert_same(array('pg-dl-panel', 'pg-dl-veil', 'pg-dl-type', 'pg-dl-figures', 'pg-dl-shapes', 'pg-dl-ways'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-dl-panel', 'pg-dl-veil', 'pg-dl-type', 'pg-dl-figures'), $part['needs']['dealership-showroom'], 'the panel\'s rules');
	pg_assert_same(array('pg-dl-type', 'pg-dl-shapes'), $part['needs']['dealership-finder'], 'the finder\'s rules');
	pg_assert_same(array('pg-dl-type', 'pg-dl-ways', 'pg-shade-bottom'), $part['needs']['dealership-ways'], 'the tiles need the core scrim too');
	foreach ($part['rules'] as $css) {
		pg_assert_false((bool) preg_match('/#[0-9a-f]{3,8}\\b|!important/i', $css), 'a rule writes no fixed colour and no !important: ' . $css);
	}
	pg_assert_true(strpos($part['rules']['pg-dl-shapes'], 'prefers-reduced-motion') !== false, 'the body styles\' line keeps still under reduced motion');

	$cta = _pg_designer_block_css_parse(pg_test_designer_family_file('cta'));
	pg_assert_same(array('pg-sticky-bar'), array_keys($cta['rules']), 'the bar\'s rule');
	pg_assert_same(array('pg-sticky-bar'), $cta['needs']['cta-sticky'], 'the bar needs it');
	pg_assert_true(strpos($cta['rules']['pg-sticky-bar'], 'var(--bs-body-bg)') !== false, 'the bar falls back to the page colour');

	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-dl-panel'], $source['rules']['pg-sticky-bar']), 'the block stylesheet holds the rules');
	$css = pg_designer_block_css(array('dealership-ways'));
	pg_assert_true(strpos($css, '.pg-shade-bottom') !== false, 'asking for the tiles brings the core scrim');
}
