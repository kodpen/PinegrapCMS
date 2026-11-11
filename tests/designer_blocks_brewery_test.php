<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Brewery block family (assets/js/style_designer_blocks_
 * brewery.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every interaction kept
 * by the normalizer; the hero's glass that fills on load, the tap board's
 * six taps with their strength and bitterness, the flight's four tasters
 * from light to dark, the release's facts and notes; its rules reaching the
 * block stylesheet with no colour or motion of their own.
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

function test_designer_blocks_brewery_family_registers()
{
	$got = pg_test_designer_family_run('brewery');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('brewery-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Brewery', $got['group'], 'its own palette group');
	pg_assert_same(array('brewery-hero', 'brewery-taps', 'brewery-flight', 'brewery-release'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_brewery_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('brewery');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		$top = $ctype === 'brewery-hero' ? 'h1' : 'h2';
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], $top, true)), $ctype . ' has one ' . $top);
		pg_assert_same(array(), array_values(array_diff($b['headings'], array($top, $top === 'h1' ? 'h2' : 'h3'))), $ctype . ' skips no level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pbw|bw|pinegrap)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' moves');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
	}

	$hero = $got['blocks']['brewery-hero'];
	pg_assert_same(1, count(array_keys($hero['classes'], 'pg-brewery-glass', true)), 'the hero pours one glass');
	pg_assert_same(1, count(array_keys($hero['classes'], 'pg-brewery-beer', true)), 'with the beer in it');
	pg_assert_same(1, count(array_keys($hero['classes'], 'pg-brewery-photo', true)), 'over a photograph');
	pg_assert_same(4, count($hero['fx']), 'words, photograph, beer and facts move');

	$taps = $got['blocks']['brewery-taps'];
	pg_assert_same(6, count(array_keys($taps['classes'], 'pg-brewery-tap', true)), 'six taps');
	pg_assert_same(6, count(array_keys($taps['classes'], 'pg-brewery-mini', true)), 'a small glass on each tap');
	pg_assert_same(6, count(array_keys($taps['texts'], 'ABV', true)), 'each tap says its strength');
	pg_assert_same(6, count(preg_grep('/^\\d+ IBU$/', $taps['texts'])), 'and its bitterness');
	pg_assert_same(6, count(array_keys($taps['headings'], 'h3', true)), 'each beer is a heading');
	foreach (array('On tap now', 'Fresh keg', 'Last keg', 'Seasonal') as $text) {
		pg_assert_true(in_array($text, $taps['texts'], true), 'the board says ' . $text);
	}

	$flight = $got['blocks']['brewery-flight'];
	pg_assert_same(4, count(array_keys($flight['classes'], 'pg-brewery-taster', true)), 'four tasters on the paddle');
	pg_assert_same(4, count(array_keys($flight['classes'], 'pg-brewery-beer', true)), 'each with its beer');
	foreach (array(1, 2, 3, 4) as $n) {
		pg_assert_same(1, count(array_keys($flight['classes'], 'pg-brewery-tone-' . $n, true)), 'tone ' . $n . ' poured once, light to dark');
	}
	pg_assert_same(4, count(array_keys($flight['headings'], 'h3', true)), 'each taster is named');
	pg_assert_same(4, count(array_keys($flight['texts'], 'ABV', true)), 'with its strength');

	$release = $got['blocks']['brewery-release'];
	foreach (array('Limited release', 'Alcohol', 'Bitterness', 'Batch', 'Tasting notes', 'Buy a pack', 'Find it on tap') as $text) {
		pg_assert_true(in_array($text, $release['texts'], true), 'the release says ' . $text);
	}
	pg_assert_same(1, count(array_keys($release['classes'], 'pg-brewery-sticker', true)), 'a sticker on the picture');
}

// What the run does not flatten, read from the file: the glasses are
// decoration hidden from screen readers (the words say the style), the
// flight pours in order, the release's facts are a description list;
// nothing plays by itself.
function test_designer_blocks_brewery_markup_from_the_file()
{
	$js = pg_test_designer_family_file('brewery');
	pg_assert_true(strpos($js, "var hidden = [{ name: 'aria-hidden', value: 'true' }];") !== false, 'one hidden attribute for the glasses');
	pg_assert_same(3, substr_count($js, ', hidden)'), 'the hero glass, the tap glass and the taster are hidden from screen readers');
	pg_assert_true(strpos($js, "kit.tag('ol', 'list-unstyled row row-cols-2 row-cols-md-4 g-0 mb-0 pg-brewery-paddle'") !== false, 'the flight is an ordered list');
	pg_assert_true(strpos($js, "kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-lg-2 gx-lg-5 mb-0'") !== false, 'the taps are numbered');
	pg_assert_true(strpos($js, "kit.tag('dl', ") !== false && strpos($js, "kit.tag('dt', ") !== false && strpos($js, "kit.tag('dd', ") !== false, 'the release\'s facts are a description list');
	pg_assert_same(0, preg_match('/autoplay|<script|setInterval|data-bs-ride/i', $js), 'nothing plays by itself');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them; they take every colour
// from the palette and leave all motion to the interactions.
function test_designer_blocks_brewery_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('brewery'));
	pg_assert_same(array('pg-brewery-glass', 'pg-brewery-pour', 'pg-brewery-photo', 'pg-brewery-tap', 'pg-brewery-figure', 'pg-brewery-paddle', 'pg-brewery-sticker'),
		array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-brewery-glass', 'pg-brewery-pour', 'pg-brewery-photo'), $part['needs']['brewery-hero'], 'the hero\'s rules');
	pg_assert_same(array('pg-brewery-glass', 'pg-brewery-tap', 'pg-brewery-figure'), $part['needs']['brewery-taps'], 'the tap board\'s rules');
	pg_assert_same(array('pg-brewery-glass', 'pg-brewery-paddle', 'pg-brewery-figure'), $part['needs']['brewery-flight'], 'the flight\'s rules');
	pg_assert_same(array('pg-brewery-photo', 'pg-brewery-sticker', 'pg-brewery-figure'), $part['needs']['brewery-release'], 'the release\'s rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-brewery-glass']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\\b|\\brgba?\\(|\\bhsla?\\(|!important/i', $css), $key . ' has no colour of its own and no !important');
		pg_assert_same(0, preg_match('/transition|animation|@keyframes/i', $css), $key . ' leaves motion to the interactions');
	}
	pg_assert_true(strpos($part['rules']['pg-brewery-glass'], '@supports (background: color-mix(in srgb, red 50%, blue))') !== false, 'the tones are mixed from the palette where the browser can');
	foreach (array(1, 2, 3, 4) as $n) {
		pg_assert_true(strpos($part['rules']['pg-brewery-glass'], '.pg-brewery-tone-' . $n) !== false, 'tone ' . $n . ' is drawn');
	}
}
