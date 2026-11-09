<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Clinic block family (assets/js/style_designer_blocks_
 * clinic.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one h2 and no skipped level, no fixed colour or template class,
 * every {var} slot filled, no year or month, every entrance kept by the
 * interaction normalizer; the doctors' week as a table with a head cell
 * per day and a row head per doctor; the care guide's three levels, each
 * with its examples and one action; its rules reaching the block
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

function test_designer_blocks_clinic_family_registers()
{
	$got = pg_test_designer_family_run('clinic');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('clinic-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Clinic', $got['group'], 'its own palette group');
	pg_assert_same(array('clinic-week', 'clinic-care'), array_keys($got['blocks']), 'the two blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_clinic_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('clinic');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		pg_assert_same(array(), array_values(array_diff($b['headings'], array('h2', 'h3'))), $ctype . ' uses only h2 and h3');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(cl|pcl)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$week = $got['blocks']['clinic-week'];
	pg_assert_true(in_array('pg-clinic-rota', $week['classes'], true), 'the rota rule');
	pg_assert_same(7, count(array_keys($week['cells'], 'th:col', true)), 'a column head for the doctor and each of six days');
	pg_assert_same(4, count(array_keys($week['cells'], 'th:row', true)), 'a row head per doctor');
	pg_assert_same(24, count(array_keys($week['cells'], 'td:', true)), 'a cell per doctor and day');
	pg_assert_true(count(array_keys($week['classes'], 'pg-clinic-session', true)) >= 1, 'sessions as pills');
	pg_assert_true(count(array_keys($week['classes'], 'pg-clinic-off', true)) >= 1, 'a day off in a word');
	foreach (array('Monday', 'Saturday', 'Morning', 'Afternoon', 'Day off', 'Dr. Jane Cooper', 'Cardiology') as $text) {
		pg_assert_true(in_array($text, $week['texts'], true), 'the doctors\' week says ' . $text);
	}
	pg_assert_same(array('h2'), $week['headings'], 'only the section title is a heading');

	$care = $got['blocks']['clinic-care'];
	pg_assert_same(3, count(array_keys($care['classes'], 'pg-clinic-care', true)), 'three levels of care');
	pg_assert_same(3, count(array_keys($care['classes'], 'pg-clinic-level', true)), 'each with its icon');
	pg_assert_same(1, count(array_keys($care['classes'], 'pg-clinic-now', true)), 'one emergency level');
	pg_assert_same(1, count(array_keys($care['classes'], 'pg-clinic-today', true)), 'one same-day level');
	pg_assert_same(3, count(array_keys($care['headings'], 'h3', true)), 'each a h3');
	foreach (array('Call 112 now', 'See a doctor today', 'Book a visit', 'Chest pain or pressure', 'Repeat prescriptions') as $text) {
		pg_assert_true(in_array($text, $care['texts'], true), 'the care guide says ' . $text);
	}
}

// The rota is a named, focusable region and the numbers can be dialled:
// read from the file, as the run flattens only classes and texts.
function test_designer_blocks_clinic_table_and_links()
{
	$js = pg_test_designer_family_file('clinic');
	pg_assert_true(strpos($js, "kit.region(_sdT('Doctors\\' Week'))") !== false, 'the rota scrolls in a named region');
	pg_assert_same(1, preg_match_all("/'tel:112'/", $js), 'the emergency number can be dialled');
	pg_assert_same(1, preg_match_all("/'tel:\\+90555\\d+'/", $js), 'the practice can be dialled');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_clinic_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('clinic'));
	pg_assert_same(array('pg-clinic-rota', 'pg-clinic-care'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-clinic-rota'), $part['needs']['clinic-week'], 'the week needs its rota');
	pg_assert_same(array('pg-clinic-care'), $part['needs']['clinic-care'], 'the guide needs its cards');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-clinic-care']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
