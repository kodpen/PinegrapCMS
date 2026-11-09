<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym block family (assets/js/style_designer_blocks_gym.js),
 * run under Node by helpers/designer_family_run.php (skipped on a machine
 * without Node): its own palette group, a section per block with one top
 * heading and no skipped level, no fixed colour or template class, every
 * {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the hero's hours today as two times and its trial
 * pass, the class finder's three classes with their facts and a five-step
 * intensity meter named in words; its rules reaching the block stylesheet.
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

function test_designer_blocks_gym_family_registers()
{
	$got = pg_test_designer_family_run('gym');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('gym-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Gym', $got['group'], 'its own palette group');
	pg_assert_same(array('gym-hero', 'gym-classes'), array_keys($got['blocks']), 'the two blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_gym_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('gym');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the class
		// finder is a section under a page's own h1.
		$top = $ctype === 'gym-hero' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pgy|gy)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['gym-hero'];
	pg_assert_true(in_array('pg-gym-photo', $hero['classes'], true), 'the picture frame');
	pg_assert_true(in_array('pg-gym-pass', $hero['classes'], true), 'the trial pass');
	pg_assert_same(array('icon+heading+time+heading+time'), $hero['badges'], 'today\'s hours: an icon, words and two times');
	foreach (array('Free trial pass', 'Get a Free Pass', 'Class Timetable', '06:00', '23:00', 'Nothing is paid now.') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(3, count($hero['fx']), 'the words rise, the picture fades, the pass follows');

	$classes = $got['blocks']['gym-classes'];
	pg_assert_same(3, count(array_keys($classes['headings'], 'h3', true)), 'three classes');
	pg_assert_same(3, count(array_keys($classes['classes'], 'pg-gym-meter', true)), 'a meter per class');
	pg_assert_same(11, count(array_keys($classes['classes'], 'pg-gym-on', true)), 'the steps reached: four, five and two');
	foreach (array('Intensity 4 of 5', 'Intensity 5 of 5', 'Intensity 2 of 5', '60 min', '45 min', 'Some experience', 'Book this class') as $text) {
		pg_assert_true(in_array($text, $classes['texts'], true), 'the class finder says ' . $text);
	}
}

// The meter is named for a screen reader and the hours are times: read
// from the file, as the run flattens only classes and texts.
function test_designer_blocks_gym_markup_from_the_file()
{
	$js = pg_test_designer_family_file('gym');
	pg_assert_true(strpos($js, "kit.div('pg-gym-meter', label, steps, [{ name: 'role', value: 'img' }, { name: 'aria-label', value: label }])") !== false, 'the meter is an image named in words');
	pg_assert_true(strpos($js, "kit.time('06:00', '06:00'") !== false && strpos($js, "kit.time('23:00', '23:00'") !== false, 'the hours are <time> elements');
	pg_assert_true(strpos($js, "kit.tag('article', 'card h-100") !== false, 'each class is an article');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_gym_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('gym'));
	pg_assert_same(array('pg-gym-photo', 'pg-gym-pass', 'pg-gym-meter'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-gym-photo', 'pg-gym-pass'), $part['needs']['gym-hero'], 'the hero needs the frame and the pass');
	pg_assert_same(array('pg-gym-meter'), $part['needs']['gym-classes'], 'the classes need the meter');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-gym-meter']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
