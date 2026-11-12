<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment block family (assets/js/style_designer_blocks_
 * recruitment.js), run under Node by helpers/designer_family_run.php
 * (skipped on a machine without Node): its own palette group, a section
 * per block with one top heading and no skipped level, no fixed colour or
 * template class, every {var} slot filled, no year or month, every
 * interaction kept by the normalizer; the hero's search by keyword and
 * city and its newest jobs; the board's contract filters and its jobs,
 * each with its facts and salary; the salary guide's table, a row head per
 * job and a bar of ten steps per range; its rules reaching the block
 * stylesheet with no colour or motion of their own.
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

function test_designer_blocks_recruitment_family_registers()
{
	$got = pg_test_designer_family_run('recruitment');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('recruitment-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Recruitment', $got['group'], 'its own palette group');
	pg_assert_same(array('recruitment-search', 'recruitment-board', 'recruitment-salaries'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_recruitment_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('recruitment');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	$levels = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
	foreach ($got['blocks'] as $ctype => $b) {
		$top = $ctype === 'recruitment-search' ? 'h1' : 'h2';
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], $top, true)), $ctype . ' has one ' . $top);
		$used = array_values(array_unique(array_map(function ($h) use ($levels) { return array_search($h, $levels, true); }, $b['headings'])));
		sort($used);
		pg_assert_same(range($used[0], $used[0] + count($used) - 1), $used, $ctype . ' skips no level');
		pg_assert_same(array_search($top, $levels, true), $used[0], $ctype . '\'s top heading comes first in rank');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(prc|rc|pinegrap)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
		pg_assert_same(array(), $b['attrs'], $ctype . ' sets no colour mode of its own');
	}

	$hero = $got['blocks']['recruitment-search'];
	pg_assert_same(array('h1', 'h2', 'h3', 'h3', 'h3'), $hero['headings'], 'the page title, the newest jobs and a heading per job');
	pg_assert_same(1, count(array_keys($hero['classes'], 'pg-recruitment-search', true)), 'one search bar');
	foreach (array('Job title or keyword', 'City', 'All cities', 'Istanbul', 'London', 'Berlin', 'Search Jobs') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the search says ' . $text);
	}
	pg_assert_same(4, count(array_keys($hero['classes'], 'rounded-pill', true)), 'four popular searches as pills');
	pg_assert_same(3, count(array_keys($hero['classes'], 'pg-recruitment-job', true)), 'three newest jobs');
	pg_assert_same(3, count(array_keys($hero['classes'], 'stretched-link', true)), 'each opened by its title');
	pg_assert_same(3, count(array_keys($hero['texts'], '/mo', true)), 'each with its monthly salary');
	pg_assert_same(3, count($hero['fx']), 'words, search bar and jobs move');

	$board = $got['blocks']['recruitment-board'];
	pg_assert_same(array('h2', 'h3', 'h3', 'h3', 'h3'), $board['headings'], 'the board\'s title and four jobs');
	foreach (array('All', 'Permanent', 'Fixed-term contract', 'Temporary', 'Part-time') as $contract) {
		pg_assert_true(in_array($contract, $board['texts'], true), 'the contract ' . $contract . ' filters the jobs');
	}
	pg_assert_same(1, count(array_keys($board['classes'], 'active', true)), 'one filter is the current one');
	pg_assert_same(4, count(array_keys($board['classes'], 'pg-recruitment-job', true)), 'four jobs');
	pg_assert_same(4, count(array_keys($board['classes'], 'pg-recruitment-icon', true)), 'each with its sector\'s icon');
	pg_assert_same(4, count(array_keys($board['texts'], '/mo', true)), 'and its monthly salary');
	pg_assert_same(array('icon+heading'), $board['badges'], 'the featured job is marked with an icon and a word, never colour alone');
	pg_assert_true(in_array('Send an Open Application', $board['texts'], true), 'a way in when no job fits');
	pg_assert_same(2, count($board['fx']), 'header and jobs move');

	$guide = $got['blocks']['recruitment-salaries'];
	pg_assert_same(array('th:col', 'th:col', 'th:col'), array_slice($guide['cells'], 0, 3), 'a head per column');
	pg_assert_same(6, count(array_keys($guide['cells'], 'th:row', true)), 'a row head per job');
	pg_assert_same(12, count(array_keys($guide['cells'], 'td:', true)), 'its sector and its range for each');
	pg_assert_same(6, count(array_keys($guide['classes'], 'pg-recruitment-scale', true)), 'a bar per range');
	pg_assert_same(60, count(array_keys($guide['classes'], 'pg-recruitment-step', true)), 'of ten steps each');
	pg_assert_same(11, count(array_keys($guide['classes'], 'bg-primary', true)), 'the steps the ranges cover take the primary colour');
	pg_assert_true(in_array('table-responsive', $guide['classes'], true) && in_array('position-relative', $guide['classes'], true), 'the table scrolls sideways inside a positioned box');
	pg_assert_same(2, count($guide['fx']), 'words and rows move');
}

// What the run does not flatten, read from the file: the search is a GET
// search form whose fields have labels and whose button submits, the
// filters are a named nav, the bars are hidden from screen readers (the
// figures above them say the range), the table has a caption and a named
// region, and nothing plays by itself.
function test_designer_blocks_recruitment_markup_from_the_file()
{
	$js = pg_test_designer_family_file('recruitment');
	pg_assert_true(strpos($js, "], [{ name: 'role', value: 'search' }]), {") !== false, 'the search bar is a search form');
	pg_assert_same(0, preg_match("/name: 'method'/", $js), 'it is sent as GET, so the search stays in the address');
	pg_assert_true(strpos($js, "[{ name: 'for', value: rsId + '_q' }]") !== false && strpos($js, "{ name: 'id',          value: rsId + '_q' }") !== false, 'the keyword field has a label');
	pg_assert_true(strpos($js, "[{ name: 'for', value: rsId + '_city' }]") !== false && strpos($js, "{ name: 'id',   value: rsId + '_city' }") !== false, 'the city has a label');
	pg_assert_true(strpos($js, "], [{ name: 'type', value: 'submit' }])") !== false, 'the search button submits');
	pg_assert_true(strpos($js, "[{ name: 'aria-label', value: _sdT('Contract type') }]") !== false, 'the filters are a named nav');
	pg_assert_true(strpos($js, "[{ name: 'aria-current', value: 'true' }]") !== false, 'the current filter is announced');
	pg_assert_true(strpos($js, "kit.div('pg-recruitment-scale', _sdT('Salary Bar'), steps, hidden)") !== false, 'the bars are hidden from screen readers');
	pg_assert_true(strpos($js, "kit.tag('caption', 'visually-hidden'") !== false, 'the salary table has a caption');
	pg_assert_true(strpos($js, "kit.region(_sdT('Monthly salary ranges'))") !== false, 'its scrolling wrap is a named region a keyboard reaches');
	// Small primary text measured under 4.5:1 on a tinted surface: the
	// salary guide's eyebrow takes the body's secondary colour.
	pg_assert_true(strpos($js, "eyebrow(_sdT('Salary guide'), 'text-body-secondary')") !== false, 'the guide\'s eyebrow is not primary text on a tinted surface');
	pg_assert_same(0, preg_match('/autoplay|<script|setInterval|data-bs-ride/i', $js), 'nothing plays by itself');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them; they take every colour
// from the palette and leave all motion to the interactions.
function test_designer_blocks_recruitment_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('recruitment'));
	pg_assert_same(array('pg-recruitment-search', 'pg-recruitment-job', 'pg-recruitment-icon', 'pg-recruitment-figure', 'pg-recruitment-scale'),
		array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-recruitment-search', 'pg-recruitment-job', 'pg-recruitment-icon', 'pg-recruitment-figure'), $part['needs']['recruitment-search'], 'the hero\'s rules');
	pg_assert_same(array('pg-recruitment-job', 'pg-recruitment-icon', 'pg-recruitment-figure'), $part['needs']['recruitment-board'], 'the board\'s rules');
	pg_assert_same(array('pg-recruitment-scale', 'pg-recruitment-figure'), $part['needs']['recruitment-salaries'], 'the guide\'s rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-recruitment-job'], $source['rules']['pg-recruitment-scale']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\\b|\\brgba?\\(|\\bhsla?\\(|!important/i', $css), $key . ' has no colour of its own and no !important');
		pg_assert_same(0, preg_match('/transition|animation|@keyframes/i', $css), $key . ' leaves motion to the interactions');
		pg_assert_same(0, preg_match('/[>+~]\\s*\\.|:first-child|:last-child|:nth-/', $css), $key . ' uses descendant selectors only, which hold inside the canvas\'s wrappers');
	}
	// Ten steps of one width, so a bar fits a phone's table cell; a job's
	// border follows the pointer and the keyboard alike.
	pg_assert_true(strpos($part['rules']['pg-recruitment-scale'], 'grid-template-columns: repeat(10, minmax(0, 1fr));') !== false, 'ten steps share the bar\'s width');
	pg_assert_true(strpos($part['rules']['pg-recruitment-job'], '.pg-recruitment-job:hover, .pg-recruitment-job:focus-within') !== false, 'hover and keyboard focus mark a job alike');
	pg_assert_true(strpos($part['rules']['pg-recruitment-figure'], 'white-space: nowrap') !== false, 'a salary never breaks');
}
