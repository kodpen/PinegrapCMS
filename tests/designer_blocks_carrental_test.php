<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental block family (assets/js/style_designer_blocks_
 * carrental.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every interaction kept
 * by the normalizer; the hero's trip slip with the car on its road, the
 * line-up's six classes from the lowest day rate to the highest, the cover
 * levels with their excess meters, the quote whose lines add up to its
 * total; its rules reaching the block stylesheet with no colour or motion
 * of their own.
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

// The amounts of a block's texts in the order it writes them, as numbers
// ('$36' => 36, '−$36' => -36); words are left out.
function _test_carrental_amounts($texts)
{
	$out = array();
	foreach ($texts as $text) {
		if (preg_match('/^(−?)\\$(\\d+)$/u', $text, $m)) {
			$out[] = ($m[1] !== '' ? -1 : 1) * (int) $m[2];
		}
	}
	return $out;
}

function test_designer_blocks_carrental_family_registers()
{
	$got = pg_test_designer_family_run('carrental');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('carrental-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Car Rental', $got['group'], 'its own palette group');
	pg_assert_same(array('carrental-hero', 'carrental-lineup', 'carrental-cover', 'carrental-quote'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_carrental_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('carrental');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		$top = $ctype === 'carrental-hero' ? 'h1' : 'h2';
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], $top, true)), $ctype . ' has one ' . $top);
		pg_assert_same(array(), array_values(array_diff($b['headings'], array($top, $top === 'h1' ? 'h2' : 'h3'))), $ctype . ' skips no level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pcr|cr|pinegrap)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' moves');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
	}

	$hero = $got['blocks']['carrental-hero'];
	foreach (array('pg-carrental-slip', 'pg-carrental-trip', 'pg-carrental-road', 'pg-carrental-car', 'pg-carrental-photo') as $class) {
		pg_assert_same(1, count(array_keys($hero['classes'], $class, true)), 'the hero has one ' . $class);
	}
	foreach (array('Pick-up desk', 'Airport Desk', 'Return desk', 'Coast Office', '10:00', '09:00', 'Mid-size', 'Lumo Five', 'or similar', '$36', '/day', 'Book a Car') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the trip slip says ' . $text);
	}
	pg_assert_same(5, count($hero['fx']), 'words, slip, car, picture and facts move');
	pg_assert_same(array(), $hero['attrs'], 'the hero is a light band');

	$lineup = $got['blocks']['carrental-lineup'];
	pg_assert_same(6, count(array_keys($lineup['classes'], 'pg-carrental-class', true)), 'six car classes');
	pg_assert_same(6, count(array_keys($lineup['classes'], 'pg-ratio-16x9', true)), 'each with a wide picture');
	pg_assert_same(6, count(array_keys($lineup['headings'], 'h3', true)), 'each class is a heading');
	pg_assert_same(6, count(array_keys($lineup['texts'], 'or similar', true)), 'each names its model or a similar one');
	pg_assert_same(6, count(preg_grep('/^\\d+ seats$/', $lineup['texts'])), 'its seats');
	pg_assert_same(6, count(preg_grep('/^\\d+ bags$/', $lineup['texts'])), 'its bags');
	pg_assert_same(6, count(array_keys($lineup['texts'], 'Security deposit', true)), 'its deposit');
	pg_assert_same(1, count(array_keys($lineup['texts'], 'Most chosen', true)), 'one class is the most chosen');
	$amounts = _test_carrental_amounts($lineup['texts']);
	pg_assert_same(12, count($amounts), 'a day rate and a deposit per class');
	$rates = array();
	$deposits = array();
	foreach ($amounts as $i => $amount) {
		if ($i % 2) {
			$deposits[] = $amount;
		} else {
			$rates[] = $amount;
		}
	}
	$sorted = $rates;
	sort($sorted);
	pg_assert_same($sorted, $rates, 'the classes run from the lowest day rate to the highest');
	foreach ($rates as $i => $day) {
		pg_assert_true($deposits[$i] > $day, 'each deposit is more than its day rate');
	}

	$cover = $got['blocks']['carrental-cover'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $cover['attrs'], 'the covers sit in a dark band');
	pg_assert_same(3, count(array_keys($cover['headings'], 'h3', true)), 'three covers');
	pg_assert_same(3, count(array_keys($cover['classes'], 'progress', true)), 'each with an excess meter');
	pg_assert_same(2, count(array_keys($cover['classes'], 'progress-bar', true)), 'the full cover\'s meter is empty');
	pg_assert_same(array(900, 9, 450, 15, 0), _test_carrental_amounts($cover['texts']), 'the excess falls as the price rises');
	pg_assert_same(3, count(array_keys($cover['texts'], 'Excess', true)), 'each says its excess');
	pg_assert_same(3, count(array_keys($cover['texts'], 'Not covered:', true)), 'what a cover leaves out is said, not only struck through');
	pg_assert_same(3, count(array_keys($cover['classes'], 'text-decoration-line-through', true)), 'and struck through');
	pg_assert_same(1, count(array_keys($cover['texts'], 'Most chosen', true)), 'one cover is the most chosen');
	pg_assert_same(4, count($cover['fx']), 'header, cards and the two bars move');

	$quote = $got['blocks']['carrental-quote'];
	pg_assert_same(1, count(array_keys($quote['classes'], 'pg-carrental-receipt', true)), 'the quote is a receipt');
	pg_assert_same(5, count(array_keys($quote['cells'], 'th:row', true)), 'four lines and the total are row headers');
	pg_assert_same(5, count(array_keys($quote['cells'], 'td:', true)), 'each with its amount');
	foreach (array('Your quote', 'Included', 'Total', 'Security deposit', 'One day free') as $text) {
		pg_assert_true(in_array($text, $quote['texts'], true), 'the receipt says ' . $text);
	}
	// Mid-size for seven days, Full Cover for seven days, a day off; then
	// the total and the deposit held. The figures are the line-up's and the
	// cover levels', so the blocks tell one story on one page.
	$bill = _test_carrental_amounts($quote['texts']);
	pg_assert_same(array(252, 105, -36, 321, 300), $bill, 'the receipt\'s amounts');
	pg_assert_same($bill[3], $bill[0] + $bill[1] + $bill[2], 'the lines add up to the total');
	pg_assert_same(7 * $rates[1], $bill[0], 'seven days at the mid-size day rate');
	pg_assert_same(-$rates[1], $bill[2], 'the free day is one mid-size day');
	pg_assert_same($deposits[1], $bill[4], 'the deposit is the mid-size deposit');
	$covers = _test_carrental_amounts($cover['texts']);
	pg_assert_same(7 * $covers[3], $bill[1], 'seven days of Full Cover');
	pg_assert_same(3, count($quote['fx']), 'words, receipt and its lines move');
}

// What the run does not flatten, read from the file: the road and the
// meters are decoration hidden from screen readers (the words say it), the
// rail is a named region keyboard users reach, the classes are an ordered
// list, the prices and the excess are description lists, the receipt is a
// table with row headers and a foot; nothing plays by itself.
function test_designer_blocks_carrental_markup_from_the_file()
{
	$js = pg_test_designer_family_file('carrental');
	pg_assert_true(strpos($js, "var hidden = [{ name: 'aria-hidden', value: 'true' }];") !== false, 'one hidden attribute for the decoration');
	pg_assert_same(2, substr_count($js, ', hidden)'), 'the road and the excess meter are hidden from screen readers');
	pg_assert_true(strpos($js, "], kit.region(_sdT('Car classes')))") !== false, 'the rail is a named region');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-carrental-lineup list-unstyled mb-0'") !== false, 'the classes are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('dl', ") !== false && strpos($js, "kit.tag('dt', ") !== false && strpos($js, "kit.tag('dd', ") !== false, 'prices and excess are description lists');
	pg_assert_true(strpos($js, "kit.tag('table', 'table table-borderless align-middle mb-0'") !== false && strpos($js, "kit.tag('tfoot', ") !== false, 'the receipt is a table with a foot');
	pg_assert_true(strpos($js, 'kit.time(datetime, datetime, ') !== false, 'pick-up and return hours are <time> elements');
	pg_assert_same(0, preg_match('/autoplay|<script|setInterval|data-bs-ride/i', $js), 'nothing plays by itself');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them; they take every colour
// from the palette and leave all motion to the interactions.
function test_designer_blocks_carrental_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('carrental'));
	pg_assert_same(array('pg-carrental-slip', 'pg-carrental-photo', 'pg-carrental-trip', 'pg-carrental-figure', 'pg-carrental-lineup', 'pg-carrental-receipt'),
		array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-carrental-slip', 'pg-carrental-photo', 'pg-carrental-trip', 'pg-carrental-figure'), $part['needs']['carrental-hero'], 'the hero\'s rules');
	pg_assert_same(array('pg-carrental-lineup', 'pg-carrental-figure', 'pg-ratio-16x9'), $part['needs']['carrental-lineup'], 'the line-up\'s rules');
	pg_assert_same(array('pg-carrental-figure'), $part['needs']['carrental-cover'], 'the cover levels\' rules');
	pg_assert_same(array('pg-carrental-receipt', 'pg-carrental-figure'), $part['needs']['carrental-quote'], 'the quote\'s rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-carrental-trip']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\\b|\\brgba?\\(|\\bhsla?\\(|!important/i', $css), $key . ' has no colour of its own and no !important');
		pg_assert_same(0, preg_match('/transition|animation|@keyframes/i', $css), $key . ' leaves motion to the interactions');
		pg_assert_same(0, preg_match('/[>+~]\\s*\\.|:first-child|:last-child|:nth-/', $css), $key . ' uses descendant selectors only, which hold inside the canvas\'s wrappers');
	}
	pg_assert_true(strpos($part['rules']['pg-carrental-trip'], 'overflow: hidden') !== false, 'the road clips the car, so sliding it in drives it along');
	pg_assert_true(strpos($part['rules']['pg-carrental-lineup'], 'scroll-snap-type: x mandatory') !== false, 'the rail stops on each card');
}
