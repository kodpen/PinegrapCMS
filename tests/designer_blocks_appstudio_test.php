<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the App Studio block family (assets/js/style_designer_blocks_
 * appstudio.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every interaction kept
 * by the normalizer; the hero's three phones, the switch's three choices
 * with a screen and words each, the release's three tagged changes, the
 * two platforms' tools; its rules reaching the block stylesheet with no
 * colour of their own.
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

function test_designer_blocks_appstudio_family_registers()
{
	$got = pg_test_designer_family_run('appstudio');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('appstudio-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('App Studio', $got['group'], 'its own palette group');
	pg_assert_same(array('appstudio-hero', 'appstudio-switch', 'appstudio-release', 'appstudio-platforms'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_appstudio_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('appstudio');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		$top = $ctype === 'appstudio-hero' ? 'h1' : 'h2';
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], $top, true)), $ctype . ' has one ' . $top);
		pg_assert_same(array(), array_values(array_diff($b['headings'], array($top, $top === 'h1' ? 'h2' : 'h3'))), $ctype . ' skips no level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pma|pinegrap)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' moves');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
		pg_assert_true(in_array('pg-appstudio-phone', $b['classes'], true), $ctype . ' shows an app on a phone');
		pg_assert_true(in_array('pg-appstudio-tight', $b['classes'], true), $ctype . ' has the large tight title');
	}

	$hero = $got['blocks']['appstudio-hero'];
	pg_assert_same(3, count(array_keys($hero['classes'], 'pg-appstudio-phone', true)), 'the hero fans three phones');
	pg_assert_same(1, count(array_keys($hero['classes'], 'pg-appstudio-lead', true)), 'the middle one larger');

	$switch = $got['blocks']['appstudio-switch'];
	foreach (array(1, 2, 3) as $n) {
		pg_assert_same(1, count(array_keys($switch['classes'], 'pg-appstudio-pick-' . $n, true)), 'choice ' . $n);
		pg_assert_same(2, count(array_keys($switch['classes'], 'pg-appstudio-shows-' . $n, true)), 'choice ' . $n . ' shows a screen and its words');
	}
	pg_assert_same(3, count(array_keys($switch['classes'], 'btn-check', true)), 'the choices are radio buttons drawn as buttons');

	$release = $got['blocks']['appstudio-release'];
	foreach (array('New', 'Improved', 'Fixed', 'What changed', 'Update now') as $text) {
		pg_assert_true(in_array($text, $release['texts'], true), 'the release says ' . $text);
	}

	$platforms = $got['blocks']['appstudio-platforms'];
	pg_assert_same(2, count(array_keys($platforms['classes'], 'pg-appstudio-peek', true)), 'a phone peeks out of each platform');
	foreach (array('Swift', 'SwiftUI', 'Kotlin', 'Jetpack Compose') as $text) {
		pg_assert_true(in_array($text, $platforms['texts'], true), 'the platforms name ' . $text);
	}
}

// What the run does not flatten, read from the file: the switch is
// grouped and labelled, the radio inputs share one name and each label
// points at its input; no script and no autoplay in the family.
function test_designer_blocks_appstudio_switch_is_a_labelled_group()
{
	$js = pg_test_designer_family_file('appstudio');
	pg_assert_true(strpos($js, "kit.tag('fieldset'") !== false, 'the choices are a fieldset');
	pg_assert_true(strpos($js, "kit.tag('legend', 'visually-hidden'") !== false, 'with a legend for screen readers');
	pg_assert_true(strpos($js, "{ name: 'name', value: _asName }") !== false, 'one radio group');
	pg_assert_true(strpos($js, "[{ name: 'for', value: id }]") !== false, 'each label names its input');
	pg_assert_same(0, preg_match('/autoplay|<script|setInterval/i', $js), 'nothing plays by itself');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them; they take every colour
// from the palette.
function test_designer_blocks_appstudio_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('appstudio'));
	pg_assert_same(array('pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-fan', 'pg-appstudio-switch', 'pg-appstudio-figure', 'pg-appstudio-peek'),
		array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-fan'), $part['needs']['appstudio-hero'], 'the hero\'s rules');
	pg_assert_same(array('pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-switch'), $part['needs']['appstudio-switch'], 'the switch\'s rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-appstudio-switch']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\\b|\\brgba?\\(|\\bhsla?\\(|!important/i', $css), $key . ' has no colour of its own and no !important');
	}
	pg_assert_true(strpos($part['rules']['pg-appstudio-switch'], 'prefers-reduced-motion: reduce') !== false, 'the switch fades only when motion is welcome');
}
