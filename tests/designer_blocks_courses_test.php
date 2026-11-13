<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Online Courses block family (assets/js/style_designer_
 * blocks_courses.js), run under Node by helpers/designer_family_run.php
 * (skipped on a machine without Node): its own palette group, a section per
 * block with one top heading and no skipped level, no fixed colour or
 * template class, every {var} slot filled, no year or month, every
 * interaction kept by the normalizer; the classroom hero's player and the
 * lessons up next, the shelf's subjects, cards and level meters, the
 * curriculum's modules, lessons and project; its rules reaching the block
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

function test_designer_blocks_courses_family_registers()
{
	$got = pg_test_designer_family_run('courses');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('courses-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Online Courses', $got['group'], 'its own palette group');
	pg_assert_same(array('courses-hero', 'courses-shelf', 'courses-curriculum'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_courses_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('courses');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'bg-black', 'bg-primary', 'bg-secondary', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted',
		'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The classroom hero is a page's way in, so its line is the h1; the
		// shelf and the curriculum are sections under a page's own h1.
		$top = $ctype === 'courses-hero' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pco|co)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' moves');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
		// Small text in the theme's colour is under 4.5:1 on a grey band or
		// a dark page with some palettes: the eyebrows take the body's second
		// colour, the second buttons the body's colour.
		pg_assert_same(array(), array_values(array_intersect(array('text-primary', 'link-primary', 'btn-outline-primary', 'btn-outline-secondary'), $b['classes'])), $ctype . ' writes no light primary text or outline button');
		pg_assert_true(in_array('text-body-secondary', $b['classes'], true), $ctype . ' has text in the body\'s second colour');
		pg_assert_true(in_array('link-body-emphasis', $b['classes'], true) && in_array('pg-courses-second', $b['classes'], true), $ctype . ' has a second button that shows the keyboard focus');
	}

	$hero = $got['blocks']['courses-hero'];
	foreach (array('pg-courses-hero', 'overflow-hidden', 'pg-courses-desk', 'pg-courses-player', 'pg-courses-screen', 'pg-courses-play', 'pg-courses-scrub', 'pg-courses-face', 'btn-primary', 'ratio-16x9') as $class) {
		pg_assert_true(in_array($class, $hero['classes'], true), 'the hero writes ' . $class);
	}
	foreach (array('Learn online, at your own pace', 'Learn a skill you will use on Monday', 'Browse courses', 'Watch a free lesson', 'First lesson free', 'No end date',
		'Build Your First Website', 'Free preview', 'How a web page works', 'Selin Aydın', 'Senior Front-End Engineer', 'Up next', '12 min',
		'Your first page in HTML', 'Colours, fonts and spacing with CSS', 'A layout that fits every screen', 'Enrolled students only') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(array('h1', 'h2', 'h3'), $hero['headings'], 'the line, the lesson playing, then what is up next');
	pg_assert_same(3, count(array_keys($hero['texts'], 'Enrolled students only', true)), 'each lesson up next is locked for a screen reader too');
	pg_assert_same(2, count($hero['fx']), 'the words rise and the player grows, on load');

	$shelf = $got['blocks']['courses-shelf'];
	foreach (array('pg-courses-shelf', 'pg-courses-card', 'pg-courses-level', 'pg-courses-bar', 'pg-courses-mid', 'pg-courses-tall', 'pg-courses-on', 'pg-courses-face', 'pg-courses-price', 'stretched-link') as $class) {
		pg_assert_true(in_array($class, $shelf['classes'], true), 'the shelf writes ' . $class);
	}
	pg_assert_same(3, count(array_keys($shelf['classes'], 'pg-courses-card', true)), 'three courses');
	pg_assert_same(3, count(array_keys($shelf['classes'], 'pg-courses-level', true)), 'a level meter on each');
	// Beginner fills one bar, intermediate two: two beginners and one
	// intermediate course.
	pg_assert_same(4, count(array_keys($shelf['classes'], 'pg-courses-on', true)), 'each meter filled up to its level');
	foreach (array('Start here', 'Our courses', 'Web Development', 'Data & Analytics', 'Design', 'Build Your First Website', 'Data Analysis with Spreadsheets', 'UX Design Fundamentals',
		'Beginner', 'Intermediate', '6 hours of video', 'Selin Aydın', 'Jonas Becker', 'Maya Collins', '$79', '$89', '$99', 'All courses') as $text) {
		pg_assert_true(in_array($text, $shelf['texts'], true), 'the shelf says ' . $text);
	}
	pg_assert_same(array('h2', 'h3', 'h3', 'h3'), $shelf['headings'], 'the title, then a heading per course');
	// Each subject pill is its icon and its name: the colour never says it
	// alone. The length on each picture is an icon and its text too.
	pg_assert_same(array_fill(0, 3, 'icon+heading'), $shelf['badges'], 'the length on each picture, an icon and its text');
	foreach (array('bg-primary-subtle', 'bg-success-subtle', 'bg-danger-subtle', 'text-success-emphasis', 'text-danger-emphasis') as $class) {
		pg_assert_true(in_array($class, $shelf['classes'], true), 'a subject pill takes ' . $class);
	}
	pg_assert_same(2, count($shelf['fx']), 'the header fades in, the cards rise one by one');

	$curriculum = $got['blocks']['courses-curriculum'];
	foreach (array('pg-courses-modules', 'pg-courses-module', 'pg-courses-project', 'pg-courses-marker', 'pg-courses-facts', 'pg-courses-level', 'text-bg-primary', 'visually-hidden') as $class) {
		pg_assert_true(in_array($class, $curriculum['classes'], true), 'the curriculum writes ' . $class);
	}
	pg_assert_same(3, count(array_keys($curriculum['classes'], 'pg-courses-module', true)), 'two modules and the project on the path');
	pg_assert_same(1, count(array_keys($curriculum['classes'], 'pg-courses-project', true)), 'the project ends the path');
	// The course's lessons in their order, the first the free preview.
	$lessons = array('How a web page works', 'Your first page in HTML', 'Colours, fonts and spacing with CSS', 'A layout that fits every screen');
	pg_assert_same($lessons, array_values(array_intersect($curriculum['texts'], $lessons)), 'the lessons in order');
	pg_assert_same(1, count(array_keys($curriculum['texts'], 'Free preview', true)), 'one free preview');
	pg_assert_same(3, count(array_keys($curriculum['texts'], 'Enrolled students only', true)), 'the other three are locked');
	foreach (array('Inside a course', 'Module 1: Getting started', 'Module 2: Layout and style', '2 lessons', '30 min', '47 min', '12 min', '25 min',
		'Level', 'Instructor', 'Length', 'Access', 'No end date', 'Watch the free lesson', 'Enrol', 'Your project', 'Written feedback from your instructor') as $text) {
		pg_assert_true(in_array($text, $curriculum['texts'], true), 'the curriculum says ' . $text);
	}
	pg_assert_same(array('h2', 'h3', 'h3', 'h3'), $curriculum['headings'], 'the course, then its two modules and the project');
	pg_assert_same(2, count($curriculum['fx']), 'the course fades in, the modules rise one by one');
}

// The lengths, the lists and the hidden drawings are read from the file, as
// the run flattens only classes and texts.
function test_designer_blocks_courses_markup_from_the_file()
{
	$js = pg_test_designer_family_file('courses');
	// A length is a duration a machine can read.
	pg_assert_true(strpos($js, "return kit.time('PT' + n + 'M', _sdT('{var} min', String(n))") !== false, 'lengths are <time> durations');
	pg_assert_true(strpos($js, "fx(kit.tag('ol', 'pg-courses-modules', _sdT('Modules'), [") !== false && strpos($js, "kit.tag('ol', 'list-unstyled mb-0', _sdT('Lessons'), lessons)") !== false, 'modules and lessons are ordered lists');
	// The play button is a link named for a screen reader; the bar of how
	// far the lesson has played, the level meters and the path's markers are
	// pictures only.
	pg_assert_true(strpos($js, "kit.iconLink('bi-play-fill', _sdT('Play the free lesson'), 'pg-courses-play btn btn-primary rounded-circle', '#')") !== false, 'the play button has a name');
	pg_assert_true(strpos($js, "kit.div('pg-courses-scrub', _sdT('Progress'), [], hidden)") !== false, 'the played bar is hidden');
	pg_assert_true(strpos($js, "kit.tag('span', 'pg-courses-level', _sdT('Level Meter'), [bar(1, ''), bar(2, 'pg-courses-mid'), bar(3, 'pg-courses-tall')], hidden)") !== false, 'the meter is hidden, its level a word beside it');
	pg_assert_same(2, substr_count($js, "_sdT('Marker'), ["), 'a marker per module and for the project');
	pg_assert_same(2, preg_match_all("/_sdT\\('Marker'\\), \\[[^\\n]*\\], hidden\\),/", $js), 'the markers are hidden, the headings say the module');
	pg_assert_true(strpos($js, "kit.tag('nav', '', _sdT('Subjects'), [") !== false && strpos($js, "[{ name: 'aria-label', value: _sdT('Subjects') }]") !== false, 'the subjects are a named navigation');
	pg_assert_true(strpos($js, "{ effect: 'scale', trigger: 'load', duration: 900, delay: 200 }") !== false, 'the player grows on load');
	// Text in the theme's colour stays on icons, which need 3:1.
	pg_assert_same(0, preg_match('/kit\.(p|time|link)\([^;\n]*text-primary/', $js), 'no paragraph, time or link in the theme colour');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_courses_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('courses'));
	pg_assert_same(array('pg-courses-hero', 'pg-courses-player', 'pg-courses-shelf', 'pg-courses-level', 'pg-courses-modules', 'pg-courses-second'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-courses-hero', 'pg-courses-player', 'pg-courses-second'), $part['needs']['courses-hero'], 'the hero needs its glow, the player and the second button ring');
	pg_assert_same(array('pg-courses-shelf', 'pg-courses-level', 'pg-courses-second'), $part['needs']['courses-shelf'], 'the shelf needs its cards, the meter and the second button ring');
	pg_assert_same(array('pg-courses-modules', 'pg-courses-level', 'pg-courses-second'), $part['needs']['courses-curriculum'], 'the curriculum needs its path, the meter and the second button ring');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-courses-modules']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
		pg_assert_same(false, strpos($css, '!important'), 'a rule never forces: ' . $name);
		// The canvas wraps every node in an .sd-wrap, so a child or sibling
		// selector would miss there what it matches on the page, and so
		// would :nth-child.
		pg_assert_same(0, preg_match('/[>+~]\s*(li|div|dt|dd|time|span|img|p|a|h[1-6]|\.)/', $css), 'no child or sibling selector: ' . $name);
		pg_assert_same(false, strpos($css, 'nth-'), 'no position selector: ' . $name);
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $css), 'no fixed colour: ' . $name);
	}
	pg_assert_true(strpos($part['rules']['pg-courses-hero'], '@supports (background: color-mix(in srgb, red 50%, blue))') !== false, 'the glow is behind @supports');
	// The sheet behind the player keeps inside the page on a phone and sits
	// behind the card without leaving the section's stacking order.
	pg_assert_true(strpos($part['rules']['pg-courses-player'], '.pg-courses-desk { position: relative; isolation: isolate; }') !== false
		&& strpos($part['rules']['pg-courses-player'], 'inset-block: 1rem -.75rem; inset-inline: 1rem -.75rem;') !== false, 'the second sheet stays close on a phone');
	pg_assert_true(strpos($part['rules']['pg-courses-player'], '@media (prefers-reduced-motion: reduce) { .pg-courses-player .pg-courses-play { transition: none; } }') !== false
		&& strpos($part['rules']['pg-courses-shelf'], '@media (prefers-reduced-motion: reduce) { .pg-courses-shelf .pg-courses-card { transition: none; }') !== false, 'nothing eases under reduced motion');
	pg_assert_true(strpos($part['rules']['pg-courses-player'], '.pg-courses-player .pg-courses-play:focus-visible { outline: 0; box-shadow: 0 0 0 .25rem var(--bs-body-bg), 0 0 0 .5rem var(--bs-primary); }') !== false, 'the play button draws its own focus ring');
	// The path runs on the starting side in both directions and stops at the
	// project.
	pg_assert_true(strpos($part['rules']['pg-courses-modules'], 'inset-inline-start: 1.3125rem; border-inline-start: 2px dashed var(--bs-border-color); }') !== false
		&& strpos($part['rules']['pg-courses-modules'], '.pg-courses-modules .pg-courses-project::before { content: none; }') !== false, 'the dashed path ends at the project');
	pg_assert_same('.pg-courses-second { --bs-btn-focus-shadow-rgb: var(--bs-emphasis-color-rgb); }', $part['rules']['pg-courses-second'], 'the second button has a focus ring colour');
}
