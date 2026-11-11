<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist block family (assets/js/style_designer_blocks_
 * florist.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every interaction kept
 * by the normalizer; the hero's arched bouquet with the gift card tucked
 * into it, the last order time and the six occasions; the card message
 * band with its four samples; the subscription's three plans, whose week
 * strips show as many bouquets as the words say and whose price per
 * delivery comes to the twelve weeks' total; its rules reaching the block
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

// The numbers a pattern finds in a block's texts, in the order it writes
// them.
function _test_florist_numbers($texts, $pattern)
{
	$out = array();
	foreach ($texts as $text) {
		if (preg_match($pattern, $text, $m)) {
			$out[] = (int) $m[1];
		}
	}
	return $out;
}

function test_designer_blocks_florist_family_registers()
{
	$got = pg_test_designer_family_run('florist');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('florist-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Florist', $got['group'], 'its own palette group');
	pg_assert_same(array('florist-hero', 'florist-card', 'florist-subscription'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_florist_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('florist');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		$top = $ctype === 'florist-hero' ? 'h1' : 'h2';
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], $top, true)), $ctype . ' has one ' . $top);
		pg_assert_same(array(), array_values(array_diff($b['headings'], array($top, $top === 'h1' ? 'h2' : 'h3'))), $ctype . ' skips no level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pfl|fl|pinegrap)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
		pg_assert_same(array(), $b['attrs'], $ctype . ' is a light band, so it follows the colour mode');
	}

	$hero = $got['blocks']['florist-hero'];
	foreach (array('pg-florist-frame', 'pg-florist-arch', 'pg-florist-tuck', 'pg-florist-note') as $class) {
		pg_assert_same(1, count(array_keys($hero['classes'], $class, true)), 'the hero has one ' . $class);
	}
	foreach (array('Last order for delivery today', '14:00', 'Send Flowers', 'Dear Mira,', 'With love, Sam', 'A hand-tied bouquet of sunflowers and white roses wrapped in paper') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	foreach (array('Birthday', 'Love and Romance', 'Thank You', 'New Baby', 'Get Well', 'Sympathy') as $occasion) {
		pg_assert_same(1, count(array_keys($hero['texts'], $occasion, true)), 'the occasion ' . $occasion . ' is a way in');
	}
	pg_assert_same(6, count(array_keys($hero['classes'], 'rounded-pill', true)), 'six occasions as pills');
	pg_assert_same(4, count($hero['fx']), 'words, bouquet, card and occasions move');

	$card = $got['blocks']['florist-card'];
	pg_assert_same(1, count(array_keys($card['classes'], 'pg-florist-note', true)), 'the card is drawn once, large');
	pg_assert_same(4, count(array_keys($card['headings'], 'h3', true)), 'four sample messages, each under its occasion');
	foreach (array('Written in ink by the florist', 'Up to 200 characters', 'Unsigned, if it is a surprise', 'Send Flowers') as $text) {
		pg_assert_true(in_array($text, $card['texts'], true), 'the card band says ' . $text);
	}
	pg_assert_same(3, count($card['fx']), 'card, words and samples move');

	$plans = $got['blocks']['florist-subscription'];
	pg_assert_same(3, count(array_keys($plans['headings'], 'h3', true)), 'three plans');
	pg_assert_same(3, count(array_keys($plans['classes'], 'pg-florist-weeks', true)), 'each with its week strip');
	pg_assert_same(36, count(array_keys($plans['classes'], 'pg-florist-week', true)), 'twelve weeks in each strip');
	pg_assert_same(1, count(array_keys($plans['texts'], 'Most chosen', true)), 'one plan is the most chosen');
	pg_assert_same(1, count(array_keys($plans['classes'], 'border-primary', true)), 'and framed in the theme colour');
	$counts = _test_florist_numbers($plans['texts'], '/^(\\d+) deliveries in twelve weeks$/');
	$prices = _test_florist_numbers($plans['texts'], '/^\\$(\\d+)$/');
	$totals = _test_florist_numbers($plans['texts'], '/^\\$(\\d+) for the twelve weeks$/');
	pg_assert_same(array(12, 6, 3), $counts, 'every week, every two weeks, every month');
	pg_assert_same(3, count($prices), 'a price per delivery for each plan');
	foreach ($counts as $i => $count) {
		pg_assert_same($totals[$i], $count * $prices[$i], 'the twelve weeks of plan ' . ($i + 1) . ' cost its deliveries times its price');
	}
	// An empty week is a grey square, a delivery week a coloured one with a
	// flower: the strips show the deliveries the words count.
	$empty = count(array_keys($plans['classes'], 'bg-body-secondary', true));
	pg_assert_same(array_sum($counts), 36 - $empty, 'the strips show as many bouquets as the plans promise');
	pg_assert_same(5, count($plans['fx']), 'header, plans and the three strips move');
}

// What the run does not flatten, read from the file: the week strips are
// decoration hidden from screen readers (the words count the deliveries),
// the occasions are links, the last order time is a <time> element, and
// nothing plays by itself.
function test_designer_blocks_florist_markup_from_the_file()
{
	$js = pg_test_designer_family_file('florist');
	pg_assert_true(strpos($js, "var hidden = [{ name: 'aria-hidden', value: 'true' }];") !== false, 'one hidden attribute for the decoration');
	pg_assert_same(1, substr_count($js, ', hidden)'), 'the week strip is hidden from screen readers');
	pg_assert_true(strpos($js, "kit.createNode('semantic', { tag: 'a', cssClass: 'btn btn-outline-primary rounded-pill") !== false, 'an occasion is a link');
	pg_assert_true(strpos($js, "kit.time('14:00', '14:00', ") !== false, 'the last order time is a <time> element');
	pg_assert_true(strpos($js, "kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-lg-3 g-4 mb-0'") !== false, 'the plans are a list');
	pg_assert_true(strpos($js, "{ name: 'aria-label', value: _sdT('Start this plan: {var}', name) }") !== false, 'each plan\'s button names its plan, so the three are told apart in a list of links');
	// Small primary text measured under 4.5:1 on bg-body-tertiary, the card
	// band's surface: its eyebrow takes the body's secondary colour.
	pg_assert_true(strpos($js, "eyebrow(_sdT('The card'), 'text-body-secondary')") !== false, 'the card band\'s eyebrow is not primary text on a tinted surface');
	pg_assert_same(0, preg_match('/autoplay|<script|setInterval|data-bs-ride/i', $js), 'nothing plays by itself');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them; they take every colour
// from the palette and leave all motion to the interactions.
function test_designer_blocks_florist_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('florist'));
	pg_assert_same(array('pg-florist-frame', 'pg-florist-arch', 'pg-florist-note', 'pg-florist-weeks', 'pg-florist-figure'),
		array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-florist-frame', 'pg-florist-arch', 'pg-florist-note', 'pg-florist-figure'), $part['needs']['florist-hero'], 'the hero\'s rules');
	pg_assert_same(array('pg-florist-note'), $part['needs']['florist-card'], 'the card band\'s rule');
	pg_assert_same(array('pg-florist-weeks', 'pg-florist-figure'), $part['needs']['florist-subscription'], 'the subscription\'s rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-florist-arch'], $source['rules']['pg-florist-weeks']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\\b|\\brgba?\\(|\\bhsla?\\(|!important/i', $css), $key . ' has no colour of its own and no !important');
		pg_assert_same(0, preg_match('/transition|animation|@keyframes/i', $css), $key . ' leaves motion to the interactions');
		pg_assert_same(0, preg_match('/[>+~]\\s*\\.|:first-child|:last-child|:nth-/', $css), $key . ' uses descendant selectors only, which hold inside the canvas\'s wrappers');
	}
	pg_assert_true(strpos($part['rules']['pg-florist-weeks'], 'repeat(12, minmax(0, 1fr))') !== false, 'the twelve weeks share the card\'s width, so the strip fits a phone');
	pg_assert_true(strpos($part['rules']['pg-florist-arch'], 'aspect-ratio: 4 / 5') !== false, 'the arch keeps its shape at any width');
}
