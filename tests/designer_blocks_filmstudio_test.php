<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Film Studio block family (assets/js/style_designer_blocks_
 * filmstudio.js), run under Node by helpers/designer_family_run.php
 * (skipped on a machine without Node): its own palette group, a section per
 * block with one top heading and no skipped level, no fixed colour or
 * template class, every {var} slot filled, no year or month, every
 * entrance kept by the interaction normalizer; the showreel's player, slate
 * and chapters, the title card's still, facts and credits roll, the four
 * delivery frames with their lengths; its rules reaching the block
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

function test_designer_blocks_filmstudio_family_registers()
{
	$got = pg_test_designer_family_run('filmstudio');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('filmstudio-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Film Studio', $got['group'], 'its own palette group');
	pg_assert_same(array('filmstudio-reel', 'filmstudio-credits', 'filmstudio-formats'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_filmstudio_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('filmstudio');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The showreel is a page's way in, so its title is the h1; the
		// others are sections under a page's own h1.
		$top = $ctype === 'filmstudio-reel' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pfm|fm)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	// The showreel sits on a dark band in every look and mode.
	$reel = $got['blocks']['filmstudio-reel'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $reel['attrs'], 'the showreel is a dark band');
	foreach (array('pg-filmstudio-screen', 'pg-filmstudio-chapters', 'ratio-16x9') as $class) {
		pg_assert_true(in_array($class, $reel['classes'], true), 'the showreel writes ' . $class);
	}
	pg_assert_same(5, count(array_keys($reel['headings'], 'h3', true)), 'five films in the reel');
	foreach (array('Stories worth watching', 'Showreel', '02:30', 'In this reel', '00:00', '01:55', 'Night Drive', 'Build Day', 'Start a Project', 'See Our Work', 'Your browser cannot play this video.') as $text) {
		pg_assert_true(in_array($text, $reel['texts'], true), 'the showreel says ' . $text);
	}
	pg_assert_same(array(), array_values(array_intersect(array('btn-outline-primary', 'btn-outline-secondary'), $reel['classes'])), 'no outline button on the dark band');
	pg_assert_same(3, count($reel['fx']), 'the words rise, the screen grows, the films follow one by one');

	$credits = $got['blocks']['filmstudio-credits'];
	foreach (array('pg-filmstudio-scope', 'pg-filmstudio-play', 'pg-filmstudio-roll', 'blockquote') as $class) {
		pg_assert_true(in_array($class, $credits['classes'], true), 'the title card writes ' . $class);
	}
	foreach (array('Featured film', 'Night Drive', '60 seconds', 'Credits', 'Directed by', 'Ece Kaya', 'Director of Photography', 'Sound Design', 'Baran Öz', 'Meridian Motors') as $text) {
		pg_assert_true(in_array($text, $credits['texts'], true), 'the title card says ' . $text);
	}
	pg_assert_same(1, count(array_keys($credits['headings'], 'h3', true)), 'the credits under one heading');
	pg_assert_same(4, count($credits['fx']), 'the title fades, the still grows, the credits roll up, the client\'s words fade');

	$formats = $got['blocks']['filmstudio-formats'];
	pg_assert_same(4, count(array_keys($formats['headings'], 'h3', true)), 'four delivery formats');
	pg_assert_same(4, count(array_keys($formats['classes'], 'pg-filmstudio-frame', true)), 'a drawing per format');
	foreach (array('pg-filmstudio-r239', 'pg-filmstudio-r169', 'pg-filmstudio-r45', 'pg-filmstudio-r916') as $class) {
		pg_assert_true(in_array($class, $formats['classes'], true), 'a frame drawn at its ratio: ' . $class);
	}
	foreach (array('A cut for every screen and length', '2.39:1', '16:9', '4:5', '9:16', 'Television and the web', 'Vertical video on phones', '60 s', '6 s') as $text) {
		pg_assert_true(in_array($text, $formats['texts'], true), 'the formats say ' . $text);
	}
	pg_assert_same(10, count(array_keys($formats['classes'], 'badge', true)), 'ten lengths across the four formats');
	pg_assert_same(3, count($formats['fx']), 'the header fades, the frames grow one by one, the note follows');
}

// The player, the timecodes, the description list and the hidden
// drawings are read from the file, as the run flattens only classes and
// texts.
function test_designer_blocks_filmstudio_markup_from_the_file()
{
	$js = pg_test_designer_family_file('filmstudio');
	pg_assert_true(strpos($js, "kit.tag('video', 'w-100 h-100 object-fit-cover'") !== false, 'the reel is a video element');
	foreach (array("{ name: 'controls', value: '' }", "{ name: 'preload', value: 'none' }", "{ name: 'playsinline', value: '' }", "{ name: 'poster', value: ") as $attr) {
		pg_assert_true(strpos($js, $attr) !== false, 'the player has ' . $attr);
	}
	pg_assert_same(false, strpos($js, "'autoplay'"), 'the reel never plays by itself');
	pg_assert_true(strpos($js, "kit.time('PT2M30S', '02:30'") !== false, 'the running time is a <time> duration');
	pg_assert_true(strpos($js, "kit.time('PT' + seconds + 'S', at,") !== false, 'a film\'s timecode is a <time> duration');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-filmstudio-chapters") !== false, 'the films are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('dl', 'pg-filmstudio-roll") !== false, 'the credits are a description list');
	pg_assert_true(strpos($js, "kit.tag('dt', '', _sdT('Role')") !== false && strpos($js, "kit.tag('dd', '', _sdT('Name')") !== false, 'a credit is a role and a name');
	pg_assert_true(strpos($js, "kit.iconLink('bi-play-fill', _sdT('Watch the film')") !== false, 'the play mark is named by its label');
	pg_assert_true(strpos($js, "kit.div('pg-filmstudio-frame mb-3', _sdT('Drawing'), [") !== false
		&& strpos($js, "], [{ name: 'aria-hidden', value: 'true' }]),") !== false, 'the drawing is hidden from a screen reader');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_filmstudio_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('filmstudio'));
	pg_assert_same(array('pg-filmstudio-screen', 'pg-filmstudio-chapters', 'pg-filmstudio-scope', 'pg-filmstudio-roll', 'pg-filmstudio-frame'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-filmstudio-screen', 'pg-filmstudio-chapters'), $part['needs']['filmstudio-reel'], 'the showreel needs the screen and the chapters');
	pg_assert_same(array('pg-filmstudio-scope', 'pg-filmstudio-roll'), $part['needs']['filmstudio-credits'], 'the title card needs the wide still and the roll');
	pg_assert_same(array('pg-filmstudio-frame'), $part['needs']['filmstudio-formats'], 'the formats need the frames');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-filmstudio-roll']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
	}
	pg_assert_true(strpos($part['rules']['pg-filmstudio-scope'], 'prefers-reduced-motion') !== false, 'the play mark stops growing under reduced motion');
	pg_assert_true(strpos($part['rules']['pg-filmstudio-scope'], '--bs-aspect-ratio: 41.84%') !== false, 'the still is at the cinema\'s 2.39:1');
}
