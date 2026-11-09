<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Music block family (assets/js/style_designer_blocks_
 * music.js), run under Node by helpers/designer_family_run.php (skipped on
 * a machine without Node): its own palette group, a section per block with
 * one top heading and no skipped level, no fixed colour or template class,
 * every {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the release's sleeve, disc and eleven numbered
 * tracks with their lengths, the tour's five shows with their ticket states
 * as an icon and a word, the press kit's one-sheet, photos and files to
 * download; its rules reaching the block stylesheet.
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

function test_designer_blocks_music_family_registers()
{
	$got = pg_test_designer_family_run('music');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('music-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Music', $got['group'], 'its own palette group');
	pg_assert_same(array('music-release', 'music-tour', 'music-presskit'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_music_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('music');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The release hero is a page's way in, so its title is the h1; the
		// others are sections under a page's own h1.
		$top = $ctype === 'music-release' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pmu|mu)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$release = $got['blocks']['music-release'];
	foreach (array('pg-music-sleeve', 'pg-music-disc', 'pg-music-cover', 'pg-music-tracks') as $cls) {
		pg_assert_true(in_array($cls, $release['classes'], true), 'the release has ' . $cls);
	}
	foreach (array('Night Bus Hymns', 'Track Listing', 'Last Ferry', 'Morning Shift', '6:10', 'Listen Now') as $text) {
		pg_assert_true(in_array($text, $release['texts'], true), 'the release says ' . $text);
	}
	pg_assert_same(4, count($release['fx']), 'the sleeve fades, the disc slides out, the words rise, the tracks follow');

	$tour = $got['blocks']['music-tour'];
	pg_assert_true(in_array('pg-music-day', $tour['classes'], true), 'the day rule');
	pg_assert_same(5, count(array_keys($tour['classes'], 'pg-music-day', true)), 'five shows');
	pg_assert_same(5, count(array_keys($tour['headings'], 'h3', true)), 'each show names its city');
	pg_assert_same(array_fill(0, 5, 'icon+heading'), $tour['badges'], 'each ticket state is an icon and a word');
	foreach (array('Sold out', 'Few tickets left', 'Tickets on sale', 'Waiting List', 'Harbour Hall') as $text) {
		pg_assert_true(in_array($text, $tour['texts'], true), 'the tour says ' . $text);
	}

	$press = $got['blocks']['music-presskit'];
	pg_assert_true(in_array('pg-music-sheet', $press['classes'], true), 'the one-sheet rule');
	pg_assert_same(3, count(array_keys($press['classes'], 'ratio-4x3', true)), 'three press photos');
	pg_assert_same(2, count(array_keys($press['headings'], 'h3', true)), 'photos and files are named');
	pg_assert_same(3, count(array_keys($press['headings'], 'h4', true)), 'three files');
	foreach (array('Genre', 'Record Label', 'Stage plot and technical rider', 'Book the Band') as $text) {
		pg_assert_true(in_array($text, $press['texts'], true), 'the press kit says ' . $text);
	}
}

// Read from the file, as the run flattens only classes and texts: the
// ordered lists, the lengths as durations, the downloads, the hidden disc.
function test_designer_blocks_music_markup()
{
	$js = pg_test_designer_family_file('music');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-music-tracks'") !== false, 'the tracks are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('ol', 'list-unstyled border-top mb-0', _sdT('Shows')") !== false, 'the shows are an ordered list');
	pg_assert_true(strpos($js, "kit.time('PT' + parts[0] + 'M' + parts[1] + 'S'") !== false, 'a length is a duration');
	pg_assert_true(strpos($js, "{ name: 'download', value: '' }") !== false, 'a file link downloads');
	pg_assert_true(strpos($js, "_sdT('Disc'), [], [{ name: 'aria-hidden', value: 'true' }]") !== false, 'the disc is decoration');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_music_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('music'));
	pg_assert_same(array('pg-music-sleeve', 'pg-music-tracks', 'pg-music-day', 'pg-music-sheet'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-music-sleeve', 'pg-music-tracks'), $part['needs']['music-release'], 'the release needs its sleeve and tracks');
	pg_assert_same(array('pg-music-day'), $part['needs']['music-tour'], 'the tour needs its days');
	pg_assert_same(array('pg-music-sheet'), $part['needs']['music-presskit'], 'the press kit needs its one-sheet');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-music-tracks']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
