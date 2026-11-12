<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast block family (assets/js/style_designer_blocks_
 * podcast.js), run under Node by helpers/designer_family_run.php (skipped on
 * a machine without Node): its own palette group, a section per block with
 * one top heading and no skipped level, no fixed colour or template class,
 * every {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the episode hero's cover, chapters, apps and
 * guest, the sponsor placements' drawing and their three ways to sponsor,
 * the transcript's turns; its rules reaching the block stylesheet.
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

function test_designer_blocks_podcast_family_registers()
{
	$got = pg_test_designer_family_run('podcast');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('podcast-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Podcast', $got['group'], 'its own palette group');
	pg_assert_same(array('podcast-episode', 'podcast-sponsor', 'podcast-transcript'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_podcast_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('podcast');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The episode hero is a page's way in, so its title is the h1; the
		// placements and the transcript are sections under a page's own h1.
		$top = $ctype === 'podcast-episode' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(ppd|pd)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
		// Small text in the theme's colour is under 4.5:1 on a tinted
		// surface; the blocks write its darker shade.
		pg_assert_same(array(), array_values(array_intersect(array('text-primary', 'link-primary', 'btn-outline-primary', 'btn-outline-secondary'), $b['classes'])), $ctype . ' writes no light primary text or outline button');
	}

	$hero = $got['blocks']['podcast-episode'];
	foreach (array('pg-podcast-cover', 'pg-podcast-wave', 'pg-podcast-chapters', 'btn-primary', 'text-primary-emphasis') as $class) {
		pg_assert_true(in_array($class, $hero['classes'], true), 'the hero writes ' . $class);
	}
	foreach (array('New episode', 'Kitchen Table Talks', 'Season 2, Episode 12', '58 min', 'A Chef Who Cooks Only for Neighbours', 'Listen Now', 'Episode Notes', 'Also in your podcast app', 'Chapters', '0:00', '4:10', 'Closing the restaurant', '47:30', 'Our guest', 'Derya Aksoy', 'I know every plate\'s name before I cook it.', 'Hosted by Selin Arda') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(array('h1', 'h2', 'h2'), $hero['headings'], 'the episode\'s title, then its chapters and its guest');
	pg_assert_same(5, count($hero['fx']), 'the cover grows, the wave slides in, the words rise, the chapters follow, the guest fades in');

	$sponsor = $got['blocks']['podcast-sponsor'];
	foreach (array('pg-podcast-track', 'pg-podcast-bar', 'pg-podcast-season', 'pg-podcast-at', 'pg-podcast-at-mid', 'btn-primary') as $class) {
		pg_assert_true(in_array($class, $sponsor['classes'], true), 'the placements write ' . $class);
	}
	// A number under the bars and the same number on each placement's card.
	pg_assert_same(6, count(array_keys($sponsor['classes'], 'pg-podcast-no', true)), 'three numbers under the bars, three on the cards');
	foreach (array('Where your mention plays', 'One episode', 'A season', 'Opening Mention', 'Mid-episode Story', 'Season Partner', '30 seconds, before the episode starts', 'Starting from', '$250', '$600', '$6,000', 'per episode', 'per season', 'Send a Sponsorship Enquiry') as $text) {
		pg_assert_true(in_array($text, $sponsor['texts'], true), 'the placements say ' . $text);
	}
	pg_assert_same(array('h2', 'h3', 'h3', 'h3'), $sponsor['headings'], 'the title, then a heading per placement');
	pg_assert_same(3, count($sponsor['fx']), 'the header fades, the drawing slides in, the placements rise one by one');

	$script = $got['blocks']['podcast-transcript'];
	foreach (array('pg-podcast-script', 'text-primary-emphasis', 'text-body-emphasis') as $class) {
		pg_assert_true(in_array($class, $script['classes'], true), 'the transcript writes ' . $class);
	}
	// The chapter's line and six turns, each in the same two columns.
	pg_assert_same(7, count(array_keys($script['classes'], 'pg-podcast-turn', true)), 'a chapter and six turns');
	foreach (array('Transcript', 'Selin Arda, host', 'Derya Aksoy, guest', 'Closing the restaurant', '4:12', 'Selin Arda', 'Derya Aksoy', 'And now?', 'Read the Full Transcript') as $text) {
		pg_assert_true(in_array($text, $script['texts'], true), 'the transcript says ' . $text);
	}
	pg_assert_same(array('h2', 'h3'), $script['headings'], 'the title, then the chapter the part is from');
	pg_assert_same(2, count($script['fx']), 'the words rise, the turns follow one by one');
}

// The times, the lists and the hidden drawings are read from the file, as
// the run flattens only classes and texts.
function test_designer_blocks_podcast_markup_from_the_file()
{
	$js = pg_test_designer_family_file('podcast');
	pg_assert_true(strpos($js, "return kit.time('PT' + parseInt(parts[0], 10) + 'M' + parseInt(parts[1], 10) + 'S', time, '', name);") !== false, 'a time in the episode carries its duration from the start');
	pg_assert_true(strpos($js, "kit.time('PT58M', _sdT('58 min')") !== false, 'the length is a time');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-podcast-chapters'") !== false, 'the chapters are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('ol', 'row g-4 list-unstyled mb-4', _sdT('Placements')") !== false, 'the placements are an ordered list');
	pg_assert_true(strpos($js, "kit.div('pg-podcast-wave', _sdT('Sound Wave'), [], [{ name: 'aria-hidden', value: 'true' }])") !== false, 'the sound wave is hidden from a screen reader');
	pg_assert_true(strpos($js, "mark('3', _sdT('In every episode of the season'), '')\n        ], [{ name: 'aria-hidden', value: 'true' }])") !== false, 'the drawing is hidden: its cards say the same in words');
	pg_assert_same(4, substr_count($js, "    app('bi-"), 'four podcast apps');
	pg_assert_true(strpos($js, "return kit.iconLink(icon, name, ") !== false, 'an app is an icon link named for a screen reader');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_podcast_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('podcast'));
	pg_assert_same(array('pg-podcast-cover', 'pg-podcast-chapters', 'pg-podcast-track', 'pg-podcast-no', 'pg-podcast-script'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-podcast-cover', 'pg-podcast-chapters'), $part['needs']['podcast-episode'], 'the hero needs the cover and the chapters');
	pg_assert_same(array('pg-podcast-track', 'pg-podcast-no'), $part['needs']['podcast-sponsor'], 'the placements need the drawing and the numbers');
	pg_assert_same(array('pg-podcast-script'), $part['needs']['podcast-transcript'], 'the transcript needs its turns');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-podcast-track']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
		pg_assert_same(false, strpos($css, '!important'), 'a rule never forces: ' . $name);
		// The canvas wraps every node in an .sd-wrap, so a child or sibling
		// selector would miss there what it matches on the page.
		pg_assert_same(0, preg_match('/[>+~]\s*(li|div|dt|dd|time|span|img)\b/', $css), 'no child or sibling selector: ' . $name);
	}
	// The wave's outline is a mask, with the prefix the older browsers read.
	pg_assert_true(strpos($part['rules']['pg-podcast-cover'], '-webkit-mask-image: radial-gradient(') !== false && strpos($part['rules']['pg-podcast-cover'], '; mask-image: radial-gradient(') !== false, 'the wave is masked in every browser');
	// The middle mention's label starts where its part of the bar does.
	pg_assert_true(strpos($part['rules']['pg-podcast-track'], 'var(--bs-primary) 46% 56%') !== false && strpos($part['rules']['pg-podcast-track'], '.pg-podcast-at-mid { margin-left: 46%; }') !== false, 'the label under the middle mention lines up with it');
}
