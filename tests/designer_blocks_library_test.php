<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Library block family (assets/js/style_designer_blocks_
 * library.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every interaction kept
 * by the normalizer; the hero's shelf of spines, today's hours and the six
 * formats; the shelf mark guide's label taken apart and its ten subject
 * classes with the example's class marked in words; the card and its loans
 * table with a head per column and per format; its rules reaching the block
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

function test_designer_blocks_library_family_registers()
{
	$got = pg_test_designer_family_run('library');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('library-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Library', $got['group'], 'its own palette group');
	pg_assert_same(array('library-hero', 'library-shelfmark', 'library-card'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_library_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('library');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		$top = $ctype === 'library-hero' ? 'h1' : 'h2';
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], $top, true)), $ctype . ' has one ' . $top);
		pg_assert_same(array(), array_values(array_diff($b['headings'], array($top, $top === 'h1' ? 'h2' : 'h3'))), $ctype . ' skips no level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(plb|lib|pinegrap)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/^opacity-/', $b['classes'])), $ctype . ' fades no text with opacity');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
		pg_assert_same(array(), $b['attrs'], $ctype . ' is a light band, so it follows the colour mode');
	}

	$hero = $got['blocks']['library-hero'];
	pg_assert_same(1, count(array_keys($hero['classes'], 'pg-library-shelf', true)), 'the hero draws one shelf');
	pg_assert_same(11, count(array_keys($hero['classes'], 'pg-library-spine', true)), 'eleven books on it');
	pg_assert_same(11, count(array_keys($hero['classes'], 'pg-library-title', true)), 'each with its title on the spine');
	pg_assert_same(3, count(array_keys($hero['classes'], 'pg-library-mid', true)), 'some books shorter');
	pg_assert_same(3, count(array_keys($hero['classes'], 'pg-library-low', true)), 'and some shorter still');
	foreach (array('Open today', '09:00', '20:00', 'Central Library', 'Search the Catalogue', 'Get a Library Card') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	foreach (array('Books', 'eBooks', 'Audiobooks', 'Films', 'Magazines', 'Music') as $format) {
		pg_assert_same(1, count(array_keys($hero['texts'], $format, true)), 'the format ' . $format . ' is a way in');
	}
	pg_assert_same(6, count(array_keys($hero['classes'], 'rounded-pill', true)), 'six formats as pills');
	pg_assert_same(4, count($hero['fx']), 'words, books, hours and formats move');

	$guide = $got['blocks']['library-shelfmark'];
	pg_assert_same(2, count(array_keys($guide['headings'], 'h3', true)), 'how to read the label, and the ten subjects');
	pg_assert_same(array('000', '100', '200', '300', '400', '500', '600', '700', '800', '900'), array_values(preg_grep('/^\\d00$/', $guide['texts'])), 'the ten subject classes in shelf order');
	pg_assert_same(2, count(array_keys($guide['texts'], '641.5', true)), 'the example is drawn on the spine and read beside it');
	pg_assert_same(2, count(array_keys($guide['texts'], 'OLI', true)), 'and so are the author\'s letters');
	pg_assert_same(1, count(array_keys($guide['texts'], 'Example', true)), 'the example\'s class is marked in words');
	pg_assert_same(1, count(array_keys($guide['classes'], 'bg-primary-subtle', true)), 'and in colour, once');
	pg_assert_same(3, count($guide['fx']), 'header, label and subjects move');

	$card = $got['blocks']['library-card'];
	pg_assert_same(1, count(array_keys($card['classes'], 'pg-library-card', true)), 'the card is drawn once');
	pg_assert_same(1, count(array_keys($card['classes'], 'pg-library-barcode', true)), 'with its barcode');
	pg_assert_same(array('th:col', 'th:col', 'th:col', 'th:col'), array_slice($card['cells'], 0, 4), 'a head per column');
	pg_assert_same(5, count(array_keys($card['cells'], 'th:row', true)), 'a row head per format');
	pg_assert_same(15, count(array_keys($card['cells'], 'td:', true)), 'how many, how long and how often for each');
	foreach (array('Books', 'Films', 'Magazines', 'Not renewed', 'Join the Library') as $text) {
		pg_assert_true(in_array($text, $card['texts'], true), 'the card band says ' . $text);
	}
	pg_assert_true(in_array('table-responsive', $card['classes'], true), 'the table scrolls sideways on a narrow phone');
	pg_assert_same(2, count($card['fx']), 'card and words move');
}

// What the run does not flatten, read from the file: the drawings are
// hidden from screen readers (the words beside them say it), the formats
// are links, today's hours are <time> elements, the subjects an ordered
// list, the table has a caption and a named region, and nothing plays by
// itself.
function test_designer_blocks_library_markup_from_the_file()
{
	$js = pg_test_designer_family_file('library');
	pg_assert_true(strpos($js, "var hidden = [{ name: 'aria-hidden', value: 'true' }];") !== false, 'one hidden attribute for the drawings');
	pg_assert_same(3, substr_count($js, ', hidden)'), 'the shelf, the spine label and the card are hidden from screen readers');
	pg_assert_true(strpos($js, "kit.createNode('semantic', { tag: 'a', cssClass: 'btn btn-outline-primary rounded-pill") !== false, 'a format is a link');
	pg_assert_true(strpos($js, "kit.time('09:00', '09:00', ") !== false && strpos($js, "kit.time('20:00', '20:00', ") !== false, 'today\'s hours are <time> elements');
	pg_assert_true(strpos($js, "kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-sm-2 g-2 mb-4', _sdT('Subjects')") !== false, 'the subjects are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('dl', 'mb-0', _sdT('Label Parts')") !== false, 'the label\'s parts are a description list');
	pg_assert_true(strpos($js, "kit.tag('caption', 'visually-hidden'") !== false, 'the loans table has a caption');
	pg_assert_true(strpos($js, "kit.region(_sdT('What a library card lends'))") !== false, 'its scrolling wrap is a named region a keyboard reaches');
	// Small primary text measured under 4.5:1 on a tinted surface: the
	// card band's eyebrow takes the body's secondary colour.
	pg_assert_true(strpos($js, "eyebrow(_sdT('Library card'), 'text-body-secondary')") !== false, 'the card band\'s eyebrow is not primary text on a tinted surface');
	pg_assert_same(0, preg_match('/autoplay|<script|setInterval|data-bs-ride/i', $js), 'nothing plays by itself');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them; they take every colour
// from the palette and leave all motion to the interactions.
function test_designer_blocks_library_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('library'));
	pg_assert_same(array('pg-library-shelf', 'pg-library-label', 'pg-library-card', 'pg-library-barcode', 'pg-library-figure'),
		array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-library-shelf', 'pg-library-figure'), $part['needs']['library-hero'], 'the hero\'s rules');
	pg_assert_same(array('pg-library-label', 'pg-library-figure'), $part['needs']['library-shelfmark'], 'the guide\'s rules');
	pg_assert_same(array('pg-library-card', 'pg-library-barcode', 'pg-library-figure'), $part['needs']['library-card'], 'the card band\'s rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-library-shelf'], $source['rules']['pg-library-card']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\\b|\\brgba?\\(|\\bhsla?\\(|!important/i', $css), $key . ' has no colour of its own and no !important');
		pg_assert_same(0, preg_match('/transition|animation|@keyframes/i', $css), $key . ' leaves motion to the interactions');
		pg_assert_same(0, preg_match('/[>+~]\\s*\\.|:first-child|:last-child|:nth-/', $css), $key . ' uses descendant selectors only, which hold inside the canvas\'s wrappers');
	}
	// Every book gets a column of the same width, so the shelf fits a phone
	// whatever the number of books; a book's height is fixed, since a share
	// of the shelf's height has nothing to resolve against inside the
	// canvas's wrappers.
	pg_assert_true(strpos($part['rules']['pg-library-shelf'], 'grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr);') !== false, 'the books share the shelf\'s width');
	pg_assert_same(0, preg_match('/height:\\s*\\d+%/', $part['rules']['pg-library-shelf']), 'no book height in per cent');
	pg_assert_true(strpos($part['rules']['pg-library-barcode'], 'currentColor') !== false, 'the barcode is drawn in the text colour');
	pg_assert_true(strpos($part['rules']['pg-library-card'], 'aspect-ratio: 856 / 540') !== false, 'the card keeps a bank card\'s shape');
}
