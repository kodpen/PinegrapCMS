<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Conference block family (assets/js/style_designer_blocks_
 * conference.js), run under Node by helpers/designer_family_run.php
 * (skipped on a machine without Node): its own palette group, a section per
 * block with one top heading and no skipped level, no fixed colour or
 * template class, every {var} slot filled, no year or month, every
 * interaction kept by the normalizer; the opening hero's figures and badge,
 * the day agenda's slots, keynote and parallel sessions, the line-up's
 * groups; its rules reaching the block stylesheet.
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

function test_designer_blocks_conference_family_registers()
{
	$got = pg_test_designer_family_run('conference');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('conference-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Conference', $got['group'], 'its own palette group');
	pg_assert_same(array('conference-hero', 'conference-agenda', 'conference-lineup'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_conference_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('conference');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The opening hero is a page's way in, so its line is the h1; the
		// agenda and the line-up are sections under a page's own h1.
		$top = $ctype === 'conference-hero' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pev|ev)-/', $b['classes'])), $ctype . ' writes no template class');
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
		pg_assert_true(in_array('btn-primary', $b['classes'], true) && in_array('link-body-emphasis', $b['classes'], true), $ctype . ' has a primary and a second button');
		// A button without a colour variant has no focus ring of its own.
		pg_assert_true(in_array('pg-conference-second', $b['classes'], true), $ctype . ': the second button shows the keyboard focus');
	}

	$hero = $got['blocks']['conference-hero'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $hero['attrs'], 'the hero is a dark band');
	foreach (array('pg-conference-hero', 'bg-body', 'text-body', 'overflow-hidden', 'pg-display-xl', 'pg-conference-lanyard', 'pg-conference-badge', 'pg-conference-code', 'text-bg-primary', 'pg-conference-figure') as $class) {
		pg_assert_true(in_array($class, $hero['classes'], true), 'the hero writes ' . $class);
	}
	foreach (array('Two days in autumn', 'Harbour Congress Centre', 'The conference for people who build products', 'Register', 'See the programme',
		'Conference Pass', 'Jane Cooper', 'Day 1', 'Day 2', 'The evening reception', '2 days', '40+', '4 tracks', '1,200', 'attendees last time') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(array('h1', 'h2'), $hero['headings'], 'the line, then the badge\'s pass');
	// The words rise, the badge drops on its lanyard, four figures count.
	pg_assert_same(6, count($hero['fx']), 'the hero\'s six interactions');
	pg_assert_same(array('heading', 'heading', 'heading'), $hero['badges'], 'the badge\'s three inclusions, each a text');

	$agenda = $got['blocks']['conference-agenda'];
	foreach (array('pg-conference-agenda', 'pg-conference-slot', 'pg-conference-break', 'pg-conference-time', 'pg-conference-keynote', 'pg-conference-face', 'bg-body-tertiary') as $class) {
		pg_assert_true(in_array($class, $agenda['classes'], true), 'the agenda writes ' . $class);
	}
	pg_assert_same(7, count(array_keys($agenda['classes'], 'pg-conference-slot', true)), 'seven time slots');
	pg_assert_same(4, count(array_keys($agenda['classes'], 'pg-conference-break', true)), 'breakfast, coffee, lunch and the evening are quiet lines');
	// The template's first day, in its order, with its rooms.
	$day = array('Registration and breakfast', 'Opening keynote: Calm teams ship better products', 'Coffee break',
		'One design system, eleven languages', 'Boring infrastructure on purpose', 'Growing a newsletter without ads', 'Lunch and the sponsors\' area',
		'Workshop: Mapping a product\'s first year', 'Lightning talks from the community', 'Evening reception on the terrace');
	pg_assert_same($day, array_values(array_intersect($agenda['texts'], $day)), 'the first day in order');
	foreach (array('Building what matters', 'Day 1', 'Main Hall', 'Hall A', 'Hall B', 'Hall C', 'Workshop Room', 'Terrace', 'Foyer', 'Keynote', 'Talk', 'Workshop', 'Elif Arslan', 'Chief Product Officer, Northwind Labs') as $text) {
		pg_assert_true(in_array($text, $agenda['texts'], true), 'the agenda says ' . $text);
	}
	// A heading per keynote and session under the day's title.
	pg_assert_same(array('h2', 'h3', 'h3', 'h3', 'h3', 'h3', 'h3'), $agenda['headings'], 'the day, then the keynote and five sessions');
	// Each track pill is its icon and its name: the colour never says it
	// alone. Four in the legend, five on the sessions.
	pg_assert_same(array_fill(0, 9, 'icon+heading'), $agenda['badges'], 'nine track pills, each an icon and its name');
	foreach (array('bg-primary-subtle', 'bg-success-subtle', 'bg-warning-subtle', 'bg-danger-subtle', 'text-success-emphasis', 'text-warning-emphasis', 'text-danger-emphasis') as $class) {
		pg_assert_true(in_array($class, $agenda['classes'], true), 'a track pill takes ' . $class);
	}
	pg_assert_same(2, count($agenda['fx']), 'the header fades in, the slots rise one by one');

	$lineup = $got['blocks']['conference-lineup'];
	foreach (array('pg-conference-lineup', 'pg-conference-group', 'pg-conference-names', 'pg-conference-keynotes', 'pg-conference-slash', 'link-underline-opacity-100-hover') as $class) {
		pg_assert_true(in_array($class, $lineup['classes'], true), 'the line-up writes ' . $class);
	}
	pg_assert_same(5, count(array_keys($lineup['classes'], 'pg-conference-group', true)), 'the keynotes and four tracks');
	pg_assert_same(1, count(array_keys($lineup['classes'], 'pg-conference-keynotes', true)), 'one giant row');
	// A slash after every name but each row's last: twelve names, five rows.
	pg_assert_same(7, count(array_keys($lineup['classes'], 'pg-conference-slash', true)), 'a slash between names only');
	pg_assert_same(array('Keynotes', 'Elif Arslan', 'Zeynep Yıldız'), array_slice(array_values(array_intersect($lineup['texts'], array('Keynotes', 'Elif Arslan', 'Zeynep Yıldız'))), 0, 3), 'the keynotes come first');
	foreach (array('On stage', 'The line-up', 'Product & Design', 'Engineering', 'Growth & Marketing', 'Leadership', 'All speakers', 'Propose a talk', 'Hannah Weiss') as $text) {
		pg_assert_true(in_array($text, $lineup['texts'], true), 'the line-up says ' . $text);
	}
	pg_assert_same(array('h2', 'h3', 'h3', 'h3', 'h3', 'h3'), $lineup['headings'], 'the title, then a heading per group');
	pg_assert_same(2, count($lineup['fx']), 'the header fades in, the groups rise one by one');
}

// The times, the lists and the hidden barcode are read from the file, as the
// run flattens only classes and texts.
function test_designer_blocks_conference_markup_from_the_file()
{
	$js = pg_test_designer_family_file('conference');
	pg_assert_true(strpos($js, "return kit.time(time, time, 'pg-conference-time', _sdT('Time'));") !== false, 'each slot starts with a time');
	foreach (array("'08:30'", "'09:45'", "'10:45'", "'11:15'", "'12:30'", "'14:00'", "'18:30'") as $time) {
		pg_assert_true(strpos($js, '(' . $time . ', ') !== false, 'a slot at ' . $time);
	}
	pg_assert_true(strpos($js, "fx(kit.tag('ol', 'pg-conference-agenda', _sdT('Sessions'), [") !== false, 'the slots are an ordered list');
	pg_assert_true(strpos($js, "kit.div('pg-conference-code', _sdT('Barcode'), [], hidden)") !== false && strpos($js, "var hidden = [{ name: 'aria-hidden', value: 'true' }];") !== false, 'the barcode is hidden from a screen reader');
	pg_assert_true(strpos($js, "], [{ name: 'data-bs-theme', value: 'light' }])") !== false, 'the badge is a light card on the dark band');
	pg_assert_true(strpos($js, "{ effect: 'count', duration: 1600, delay: 300 }") !== false, 'the figures count up');
	pg_assert_true(strpos($js, "{ effect: 'slide-down', trigger: 'load', duration: 1000, delay: 250, distance: 120 }") !== false, 'the badge drops on its lanyard');
	// A figure takes half a phone's width and a quarter from md up; a col
	// node's own xs would otherwise be 12.
	pg_assert_true(strpos($js, "kit.createNode('col', { xs: '6', sm: '', md: '3',") !== false, 'two figures side by side on a phone');
	// Three sessions at once take a third each from md up, two a half.
	pg_assert_true(strpos($js, "var md = sessions.length > 2 ? '4' : '6';") !== false, 'parallel sessions share the row');
	// Text in the theme's colour stays on icons, which need 3:1.
	pg_assert_same(0, preg_match('/kit\.(p|time|link)\([^;\n]*text-primary-emphasis/', $js), 'no paragraph, time or link in the theme colour');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_conference_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('conference'));
	pg_assert_same(array('pg-conference-hero', 'pg-conference-lanyard', 'pg-conference-agenda', 'pg-conference-lineup', 'pg-conference-second'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-display-xl', 'pg-conference-hero', 'pg-conference-lanyard', 'pg-conference-second'), $part['needs']['conference-hero'], 'the hero needs the giant type, its glow, the lanyard and the second button ring');
	pg_assert_same(array('pg-conference-agenda', 'pg-conference-second'), $part['needs']['conference-agenda'], 'the agenda needs its slots and the second button ring');
	pg_assert_same(array('pg-conference-lineup', 'pg-conference-second'), $part['needs']['conference-lineup'], 'the line-up needs its rows and the second button ring');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-conference-lineup']), 'the block stylesheet holds the family\'s rules');
	pg_assert_true(isset($source['rules']['pg-display-xl']), 'the giant type is a core rule the hero takes');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
		pg_assert_same(false, strpos($css, '!important'), 'a rule never forces: ' . $name);
		// The canvas wraps every node in an .sd-wrap, so a child or sibling
		// selector would miss there what it matches on the page.
		pg_assert_same(0, preg_match('/[>+~]\s*(li|div|dt|dd|time|span|img|p|a|h[1-6]|\.)/', $css), 'no child or sibling selector: ' . $name);
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $css), 'no fixed colour: ' . $name);
	}
	// The glow mixes the theme's colour only where the browser can; an older
	// one shows the plain dark band.
	pg_assert_true(strpos($part['rules']['pg-conference-hero'], '@supports (background: color-mix(in srgb, red 50%, blue))') !== false, 'the glow is behind @supports');
	// The strap reaches the band's top only where the badge sits beside the
	// words; on a phone it would cross the words above the badge.
	pg_assert_true(strpos($part['rules']['pg-conference-lanyard'], '@media (min-width: 992px) { .pg-conference-lanyard::before { top: auto; bottom: calc(100% - 4.5rem); height: 100vh; } }') !== false, 'the long strap from lg up only');
	// The badge hangs at an angle only from md up and stops easing under
	// reduced motion.
	pg_assert_true(strpos($part['rules']['pg-conference-lanyard'], '@media (min-width: 768px) { .pg-conference-lanyard .pg-conference-badge { transform: rotate(-4deg); transition: transform .5s ease; }') !== false
		&& strpos($part['rules']['pg-conference-lanyard'], '@media (prefers-reduced-motion: reduce) { .pg-conference-lanyard .pg-conference-badge { transition: none; } }') !== false, 'the badge straightens without easing under reduced motion');
	// The slash between names is generated content a screen reader skips;
	// it follows a name, so a wrapped row ends on it.
	pg_assert_true(strpos($part['rules']['pg-conference-lineup'], '.pg-conference-slash::after { content: "/"; content: "/" / ""; margin-inline-start: .75rem;') !== false, 'the slash has empty alternative text');
	// The times are in the body's strongest colour, a break's in its second.
	pg_assert_true(strpos($part['rules']['pg-conference-agenda'], 'font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); }') !== false
		&& strpos($part['rules']['pg-conference-agenda'], '.pg-conference-break .pg-conference-time { font-size: 1rem; font-weight: 600; line-height: 1.5; color: var(--bs-secondary-color); }') !== false, 'the times are readable on a grey band in both modes');
	pg_assert_same('.pg-conference-second { --bs-btn-focus-shadow-rgb: var(--bs-emphasis-color-rgb); }', $part['rules']['pg-conference-second'], 'the second button has a focus ring colour');
}
