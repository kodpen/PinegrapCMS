<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services block family (assets/js/style_designer_
 * blocks_homeservices.js), run under Node by helpers/designer_family_run.php
 * (skipped on a machine without Node): its own palette group, a section per
 * block with one top heading and no skipped level, no fixed colour or
 * template class, every {var} slot filled, no year or month, every entrance
 * kept by the interaction normalizer; the hero's buttons, emergency line and
 * job sheet, the emergency band's number, jobs and safety steps; its rules
 * reaching the block stylesheet.
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

function test_designer_blocks_homeservices_family_registers()
{
	$got = pg_test_designer_family_run('homeservices');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('homeservices-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Home Services', $got['group'], 'its own palette group');
	pg_assert_same(array('homeservices-jobsheet', 'homeservices-emergency'), array_keys($got['blocks']), 'the two blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_homeservices_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('homeservices');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the
		// emergency band is a section under a page's own h1.
		$top = $ctype === 'homeservices-jobsheet' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(phs|hs)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['homeservices-jobsheet'];
	foreach (array('pg-homeservices-sheet', 'pg-homeservices-paper', 'pg-homeservices-facts', 'pg-homeservices-stamp', 'pg-homeservices-no', 'btn-primary', 'btn-outline-primary') as $class) {
		pg_assert_true(in_array($class, $hero['classes'], true), 'the hero writes ' . $class);
	}
	foreach (array('Your home, looked after', 'Book a Visit', 'Get a Free Estimate', '+90 555 000 00 99', 'Job sheet', '#0427', 'Leaking kitchen tap', 'Confirmed', 'Arrival time', 'Tuesday, 08:00 to 12:00', 'Price', 'Fixed, agreed before we start', 'Guarantee', 'We bring', 'Shoe covers and dust sheets', 'Mert Yılmaz') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(array('h1', 'h2', 'h3'), $hero['headings'], 'the hero\'s title, the job\'s name, what we bring');
	pg_assert_same(3, count($hero['fx']), 'the words rise, the sheet grows, the ticks follow one by one');

	// The emergency band sits on a dark band in every look and mode.
	$band = $got['blocks']['homeservices-emergency'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $band['attrs'], 'the emergency line is a dark band');
	foreach (array('pg-homeservices-call', 'pg-homeservices-safety', 'text-bg-danger', 'bg-warning-subtle') as $class) {
		pg_assert_true(in_array($class, $band['classes'], true), 'the emergency band writes ' . $class);
	}
	pg_assert_same(array(), array_values(array_intersect(array('btn-outline-primary', 'btn-outline-secondary'), $band['classes'])), 'no outline button on the dark band');
	foreach (array('Burst pipe, no power, locked out?', '+90 555 000 00 99', 'On the way within the hour', 'We come out for', 'Burst pipe', 'No heating or hot water', 'Before we arrive', 'Turn off the water', 'Make the electrics safe', 'Take photos', 'Not urgent? Book a visit instead') as $text) {
		pg_assert_true(in_array($text, $band['texts'], true), 'the emergency band says ' . $text);
	}
	pg_assert_same(2, count(array_keys($band['headings'], 'h3', true)), 'the jobs and the steps under their headings');
	pg_assert_same(7, count(array_keys($band['classes'], 'badge', true)), 'seven jobs that cannot wait');
	pg_assert_same(3, count($band['fx']), 'the words rise, the card follows, its steps one by one');
}

// The phone links, the description list and the hidden mark are read from
// the file, as the run flattens only classes and texts.
function test_designer_blocks_homeservices_markup_from_the_file()
{
	$js = pg_test_designer_family_file('homeservices');
	pg_assert_true(strpos($js, "var phone = '+90 555 000 00 99', phoneHref = 'tel:+905550000099';") !== false, 'one sample number for both blocks');
	pg_assert_same(2, substr_count($js, 'kit.link(phone, phoneHref,'), 'the number is a tel: link in both blocks');
	pg_assert_true(strpos($js, "kit.tag('dl', 'pg-homeservices-facts") !== false, 'the visit\'s facts are a description list');
	pg_assert_true(strpos($js, "kit.tag('dt', '', _sdT('Label')") !== false && strpos($js, "kit.tag('dd', '', _sdT('Value')") !== false, 'a fact is a label and a value');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-homeservices-safety") !== false, 'the safety steps are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('span', 'pg-homeservices-call text-bg-danger', _sdT('Icon'), [kit.icon('bi-telephone-fill')], [{ name: 'aria-hidden', value: 'true' }])") !== false, 'the ringing mark is hidden from a screen reader');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_homeservices_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('homeservices'));
	pg_assert_same(array('pg-homeservices-sheet', 'pg-homeservices-facts', 'pg-homeservices-call', 'pg-homeservices-safety'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-homeservices-sheet', 'pg-homeservices-facts'), $part['needs']['homeservices-jobsheet'], 'the hero needs the sheet and its facts');
	pg_assert_same(array('pg-homeservices-call', 'pg-homeservices-safety'), $part['needs']['homeservices-emergency'], 'the emergency band needs the ringing mark and the steps');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-homeservices-sheet']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
		pg_assert_same(false, strpos($css, '!important'), 'a rule never forces: ' . $name);
	}
	// The mark rings three times, then rests; never under reduced motion.
	pg_assert_true(strpos($part['rules']['pg-homeservices-call'], 'animation: pg-homeservices-ring 1.8s ease-out 3;') !== false, 'the mark rings three times');
	pg_assert_true(strpos($part['rules']['pg-homeservices-call'], '@media (prefers-reduced-motion: reduce) { .pg-homeservices-call { animation: none; } }') !== false, 'no ringing under reduced motion');
}
