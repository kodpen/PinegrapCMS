<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marks of the texts written with AI (products.ai_fields,
 * page.ai_fields; pg_ai_fields_*() in includes/fn/ai_write.php): which
 * columns can carry one, the fingerprint a mark keeps of its text, how the
 * stored marks and a posted list are read, what a save keeps (a text edited
 * by hand loses its mark), the variants of a set, the marker and the input
 * the screens draw, and the event the draft panel sends when a draft is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every "Write with AI" button of a product or a page writes a column that
// can carry the mark, and no column carries one without a button.
function test_ai_fields_columns_match_the_buttons()
{
	foreach (array('product', 'page') as $record) {
		$targets = array();

		foreach (pg_ai_write_targets() as $target => $row) {
			if (isset($row['records'][$record])) {
				$targets[] = $target;
			}
		}

		$columns = pg_ai_fields_columns($record);

		sort($targets);
		$mapped = array_keys($columns);
		sort($mapped);

		pg_assert_same($targets, $mapped, $record . ' targets');
		pg_assert_same(array_values($columns), pg_ai_fields_known($record), $record . ' known columns');
	}

	pg_assert_same(array('short_description', 'full_description', 'title', 'meta_description'), pg_ai_fields_known('product'), 'product columns');
	pg_assert_same(array('page_title', 'page_meta_description'), pg_ai_fields_known('page'), 'page columns');
	pg_assert_same(array(), pg_ai_fields_known('form'), 'form records keep no marks');
	pg_assert_same('products', pg_ai_fields_table('product'), 'product table');
	pg_assert_same('page', pg_ai_fields_table('page'), 'page table');
	pg_assert_same('', pg_ai_fields_table('form'), 'no table');
}

// A posted list keeps the record's own columns, each once, in their own
// order; anything that is not a list is no columns.
function test_ai_fields_normalize()
{
	pg_assert_same(array('full_description', 'title'), pg_ai_fields_normalize('product', '["title","nope","full_description","title"]'), 'json');
	pg_assert_same(array('short_description', 'meta_description'), pg_ai_fields_normalize('product', array('meta_description', 'short_description')), 'array');
	pg_assert_same(array('page_title'), pg_ai_fields_normalize('page', array('title', 'page_title', 'Page_Title', ' page_title')), 'page names, exact');
	pg_assert_same(array(), pg_ai_fields_normalize('product', array(array('title'), 1, true, null)), 'not names');
	pg_assert_same(array(), pg_ai_fields_normalize('product', null), 'null');
	pg_assert_same(array(), pg_ai_fields_normalize('product', ''), 'empty');
	pg_assert_same(array(), pg_ai_fields_normalize('product', 'title'), 'not json');
	pg_assert_same(array(), pg_ai_fields_normalize('product', '"title"'), 'json string');
	pg_assert_same(array(), pg_ai_fields_normalize('product', 7), 'number');
	pg_assert_same(array(), pg_ai_fields_normalize('form', '["title"]'), 'unknown record');
}

// A field has text when words are left without the markup; an emptied rich
// text field and a "0" are told apart.
function test_ai_fields_has_text()
{
	pg_assert_false(pg_ai_fields_has_text(''), 'empty');
	pg_assert_false(pg_ai_fields_has_text("  \n\t "), 'spaces');
	pg_assert_false(pg_ai_fields_has_text('<p>&nbsp;</p>'), 'empty paragraph');
	pg_assert_false(pg_ai_fields_has_text("<p>\xC2\xA0</p><p><br /></p>"), 'no-break space');
	pg_assert_false(pg_ai_fields_has_text(null), 'null');
	pg_assert_false(pg_ai_fields_has_text(array('a')), 'array');
	pg_assert_true(pg_ai_fields_has_text('0'), 'zero is text');
	pg_assert_true(pg_ai_fields_has_text('<p>Soft <b>cotton</b></p>'), 'rich text');
	pg_assert_true(pg_ai_fields_has_text('<10 cm'), 'a "<" that is a word');
}

// The fingerprint names the text: the same words in the same markup give the
// same one however the lines are broken, any other change another one, and a
// field without text none.
function test_ai_fields_fingerprint()
{
	$fingerprint = pg_ai_fields_fingerprint('Soft cotton shirt');

	pg_assert_true(preg_match('/^[0-9a-f]{16}$/', $fingerprint) === 1, '16 hex digits');
	pg_assert_same(substr(sha1('Soft cotton shirt'), 0, 16), $fingerprint, 'sha1 of the text');
	pg_assert_same($fingerprint, pg_ai_fields_fingerprint("  Soft  cotton\r\nshirt \n"), 'spaces, line breaks and ends');
	pg_assert_same(pg_ai_fields_fingerprint("<p>A</p>\n<p>B</p>"), pg_ai_fields_fingerprint("<p>A</p>\r\n<p>B</p>"), 'line breaks the browser sends as CRLF');
	pg_assert_false($fingerprint === pg_ai_fields_fingerprint('Soft cotton shirts'), 'a word changed');
	pg_assert_false($fingerprint === pg_ai_fields_fingerprint('soft cotton shirt'), 'a letter case changed');
	pg_assert_false(pg_ai_fields_fingerprint('<p>Soft cotton</p>') === pg_ai_fields_fingerprint('<p>Soft <b>cotton</b></p>'), 'markup changed');
	pg_assert_same('', pg_ai_fields_fingerprint(''), 'empty');
	pg_assert_same('', pg_ai_fields_fingerprint('<p>&nbsp;</p>'), 'emptied editor');
	pg_assert_same('', pg_ai_fields_fingerprint(null), 'null');
	pg_assert_same('', pg_ai_fields_fingerprint(array('a')), 'array');
	pg_assert_same(substr(sha1('0'), 0, 16), pg_ai_fields_fingerprint('0'), 'zero is text');
}

// The stored marks: the record's own columns with a well-formed fingerprint,
// in column order; anything else holds none.
function test_ai_fields_stored()
{
	$title = pg_ai_fields_fingerprint('Pen');
	$meta = pg_ai_fields_fingerprint('A pen');

	pg_assert_same(array('title' => $title, 'meta_description' => $meta), pg_ai_fields_stored('product', '{"meta_description":"' . $meta . '","title":"' . $title . '"}'), 'json, column order');
	pg_assert_same(array('title' => $title), pg_ai_fields_stored('product', array('title' => $title, 'nope' => $meta)), 'array, own columns only');
	pg_assert_same(array(), pg_ai_fields_stored('product', array('title' => 'abc', 'meta_description' => strtoupper($meta), 'full_description' => 7)), 'malformed fingerprints');
	pg_assert_same(array(), pg_ai_fields_stored('product', '["title"]'), 'a list names no text');
	pg_assert_same(array(), pg_ai_fields_stored('product', null), 'null');
	pg_assert_same(array(), pg_ai_fields_stored('product', ''), 'empty');
	pg_assert_same(array(), pg_ai_fields_stored('product', '{broken'), 'broken json');
	pg_assert_same(array('page_title' => $title), pg_ai_fields_stored('page', array('page_title' => $title, 'title' => $title)), 'page');
	pg_assert_same(array(), pg_ai_fields_stored('form', array('title' => $title)), 'unknown record');
}

// A mark holds while the column has the text it was put on: a text edited
// since (on a screen or any other way), emptied or not named has lost it.
function test_ai_fields_marked()
{
	$stored = '{"full_description":"' . pg_ai_fields_fingerprint('<p>A pen.</p>') . '","title":"' . pg_ai_fields_fingerprint('Pen') . '"}';

	pg_assert_same(array('full_description', 'title'), pg_ai_fields_marked('product', $stored, array('full_description' => '<p>A pen.</p>', 'title' => 'Pen')), 'unchanged');
	pg_assert_same(array('full_description', 'title'), pg_ai_fields_marked('product', $stored, array('full_description' => "<p>A pen.</p>\r\n", 'title' => ' Pen ')), 'spaces only');
	pg_assert_same(array('full_description'), pg_ai_fields_marked('product', $stored, array('full_description' => '<p>A pen.</p>', 'title' => 'Blue pen')), 'edited by hand');
	pg_assert_same(array('title'), pg_ai_fields_marked('product', $stored, array('full_description' => '<p>&nbsp;</p>', 'title' => 'Pen')), 'emptied');
	pg_assert_same(array('title'), pg_ai_fields_marked('product', $stored, array('title' => 'Pen')), 'not named');
	pg_assert_same(array(), pg_ai_fields_marked('product', $stored, 'nope'), 'no values');
	pg_assert_same(array(), pg_ai_fields_marked('product', null, array('title' => 'Pen')), 'nothing stored');
	pg_assert_same(array(), pg_ai_fields_marked('product', '["title"]', array('title' => 'Pen')), 'a list names no text');
}

// The column keeps each marked column with the fingerprint of its text, NULL
// when none is left.
function test_ai_fields_encode()
{
	$values = array('short_description' => 'Pen', 'full_description' => '<p>A pen.</p>', 'title' => 'Pen', 'meta_description' => '');

	pg_assert_same(null, pg_ai_fields_encode('product', array(), $values), 'none');
	pg_assert_same(null, pg_ai_fields_encode('product', '["nope"]', $values), 'only unknown');
	pg_assert_same(null, pg_ai_fields_encode('product', array('meta_description'), $values), 'only an empty field');
	pg_assert_same(null, pg_ai_fields_encode('product', array('title'), array()), 'text not given');
	pg_assert_same(
		'{"full_description":"' . pg_ai_fields_fingerprint('<p>A pen.</p>') . '","title":"' . pg_ai_fields_fingerprint('Pen') . '"}',
		pg_ai_fields_encode('product', array('title', 'meta_description', 'full_description', 'title'), $values),
		'ordered, once, empty one left out');
	pg_assert_same('{"page_meta_description":"' . pg_ai_fields_fingerprint('Home') . '"}', pg_ai_fields_encode('page', '["page_meta_description"]', array('page_meta_description' => 'Home')), 'page');
}

// The texts of a row a save is about to write, by column: the columns that
// can carry a mark and that the row has.
function test_ai_fields_values()
{
	pg_assert_same(
		array('short_description' => 'Pen', 'title' => '', 'meta_description' => 'A pen'),
		pg_ai_fields_values('product', array('name' => 'P1', 'meta_description' => 'A pen', 'title' => '', 'short_description' => 'Pen', 'price' => 100)),
		'product row');
	pg_assert_same(array(), pg_ai_fields_values('product', 'nope'), 'not a row');
	pg_assert_same(array(), pg_ai_fields_values('form', array('title' => 'T')), 'unknown record');
}

// What the screen posted: a list, or null for nothing or a value that is not
// one.
function test_ai_fields_posted()
{
	pg_assert_same(null, pg_ai_fields_posted(null), 'not posted');
	pg_assert_same(null, pg_ai_fields_posted(''), 'empty');
	pg_assert_same(null, pg_ai_fields_posted('title'), 'not json');
	pg_assert_same(null, pg_ai_fields_posted('"title"'), 'json string');
	pg_assert_same(null, pg_ai_fields_posted(5), 'number');
	pg_assert_same(array(), pg_ai_fields_posted('[]'), 'empty list');
	pg_assert_same(array('title'), pg_ai_fields_posted('["title"]'), 'list');
	pg_assert_same(array('title', 'x'), pg_ai_fields_posted(array('title', 'x')), 'array');
}

// What a save stores: the posted columns on the text the save writes, or,
// when no list is posted, the stored marks whose text the save leaves as it
// was; a text edited by hand loses its mark, and so does a field saved
// empty.
function test_ai_fields_after_save()
{
	$values = array('short_description' => 'Pen', 'full_description' => '<p>A pen.</p>', 'title' => 'Pen', 'meta_description' => '');
	$title = pg_ai_fields_fingerprint('Pen');
	$description = pg_ai_fields_fingerprint('<p>A pen.</p>');
	$stored = '{"title":"' . $title . '"}';

	pg_assert_same('{"full_description":"' . $description . '","title":"' . $title . '"}', pg_ai_fields_after_save('product', '["title","full_description","meta_description"]', null, $values), 'drafts used: marked on their text, the emptied one left out');
	pg_assert_same($stored, pg_ai_fields_after_save('product', '["title"]', '{"title":"' . pg_ai_fields_fingerprint('Old pen') . '"}', $values), 'a new draft over an older one');
	pg_assert_same(null, pg_ai_fields_after_save('product', '[]', $stored, $values), 'removed, or edited by hand on the screen');
	pg_assert_same($stored, pg_ai_fields_after_save('product', null, $stored, $values), 'no list posted, text unchanged: kept');
	pg_assert_same($stored, pg_ai_fields_after_save('product', 'garbage', $stored, $values), 'not a list, text unchanged: kept');
	pg_assert_same(null, pg_ai_fields_after_save('product', null, $stored, array('title' => 'Blue pen') + $values), 'no list posted, edited by hand: lost');
	pg_assert_same(null, pg_ai_fields_after_save('product', null, $stored, array('title' => ' ') + $values), 'saved empty: lost');
	pg_assert_same(null, pg_ai_fields_after_save('product', null, '["title"]', $values), 'a list stored names no text');
	pg_assert_same(null, pg_ai_fields_after_save('product', null, null, $values), 'nothing');
	pg_assert_same('{"page_title":"' . pg_ai_fields_fingerprint('Home') . '"}', pg_ai_fields_after_save('page', '["title","page_title"]', null, array('page_title' => 'Home', 'page_meta_description' => '')), 'page');
}

// A draft used, saved, then edited by hand: the screen keeps the mark only
// while the text is the draft's, and a text changed any other way (the API,
// an import) is not shown as marked either.
function test_ai_fields_hand_edit_loses_the_mark()
{
	$draft = 'Soft cotton shirt for every day';
	$stored = pg_ai_fields_after_save('product', '["title"]', null, array('title' => $draft));

	pg_assert_same(array('title'), pg_ai_fields_marked('product', $stored, array('title' => $draft)), 'saved draft is marked');

	$edited = $draft . ', now in blue';

	pg_assert_same(array(), pg_ai_fields_marked('product', $stored, array('title' => $edited)), 'changed elsewhere: not shown');
	pg_assert_same(null, pg_ai_fields_after_save('product', '[]', $stored, array('title' => $edited)), 'edited on the screen: the script posts it off the list');
	pg_assert_same(null, pg_ai_fields_after_save('product', null, $stored, array('title' => $edited)), 'edited without the script: the server takes it off');
}

// A variant keeps the mark of the set's short description only while its own
// short description holds the set's text.
function test_ai_fields_for_variant()
{
	$marks = array('short_description', 'title');

	pg_assert_same($marks, pg_ai_fields_for_variant($marks, 'Cotton shirt', 'Cotton shirt Red / S'), 'built from the set');
	pg_assert_same(array('title'), pg_ai_fields_for_variant($marks, 'Cotton shirt', 'Shirt Red-S'), 'from a template');
	pg_assert_same(array('title'), pg_ai_fields_for_variant($marks, '  ', 'Red / S'), 'set has none');
	pg_assert_same(array('title'), pg_ai_fields_for_variant(array('title'), 'Cotton shirt', 'Cotton shirt Red'), 'nothing to carry');
	pg_assert_same(array(), pg_ai_fields_for_variant(null, 'A', 'A'), 'no list');
}

// The marker: hidden while the field has no mark, its words through lang(),
// the button that removes it only where the screen can save.
function test_ai_fields_marker()
{
	$html = pg_ai_fields_marker('title', 'title', true);

	pg_assert_contains('data-pg-ai-mark="title"', $html, 'column');
	pg_assert_contains('data-pg-ai-field="title"', $html, 'element');
	pg_assert_contains('class="pg-ai-mark badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-normal d-inline-flex align-items-center gap-1"', $html, 'classes');
	pg_assert_contains('data-bs-toggle="tooltip" title="' . h(lang('Written with AI')) . '"', $html, 'tooltip');
	pg_assert_contains('no-popover', $html, 'no popover');
	pg_assert_contains('<span class="visually-hidden">' . h(lang('Written with AI')) . '</span>', $html, 'screen readers');
	pg_assert_contains('<i class="bi bi-stars" aria-hidden="true"></i> <span aria-hidden="true">' . h(lang('AI')) . '</span>', $html, 'icon and the short word');
	pg_assert_contains('class="btn btn-link btn-sm p-0 border-0 lh-1 text-reset pg-ai-mark-remove no-popover"', $html, 'remove button');
	pg_assert_contains('aria-label="' . h(lang('Remove the AI mark')) . '"', $html, 'remove label');
	pg_assert_contains('type="button"', $html, 'not a submit');
	pg_assert_false(strpos($html, 'd-none') !== false, 'shown');

	pg_assert_contains(' d-none"', pg_ai_fields_marker('title', 'title', false), 'hidden');
	pg_assert_false(strpos(pg_ai_fields_marker('title', 'title', true, false), 'pg-ai-mark-remove') !== false, 'no button');
	pg_assert_contains('data-pg-ai-field="x&quot;1"', pg_ai_fields_marker('page_title', 'x"1', true), 'escaped');
}

// The input carries the marked columns with the form, '[]' for none.
function test_ai_fields_input()
{
	pg_assert_same('<input type="hidden" name="ai_fields" value="[&quot;title&quot;]" data-pg-ai-fields="product" />', pg_ai_fields_input('product', array('title', 'nope')), 'list');
	pg_assert_same('<input type="hidden" name="ai_fields" value="[]" data-pg-ai-fields="page" />', pg_ai_fields_input('page', null), 'none');
}

// The marker sits beside the label, with or without the button.
function test_ai_fields_label_row_with_marker()
{
	pg_assert_same('<div class="d-flex flex-wrap align-items-baseline column-gap-2"><label>A</label><span>M</span></div>', pg_ai_write_label_row('<label>A</label>', '', '<span>M</span>'), 'no button');
	pg_assert_same('<div class="d-flex flex-wrap align-items-baseline justify-content-between column-gap-2"><div class="d-flex flex-wrap align-items-baseline column-gap-2"><label>A</label><span>M</span></div><button></button></div>', pg_ai_write_label_row('<label>A</label>', '<button></button>', '<span>M</span>'), 'with button');
	pg_assert_same('<label>A</label>', pg_ai_write_label_row('<label>A</label>', '', ''), 'neither');
}

// The panel announces a used draft once the draft is in the field; the marks
// script listens for it, reads what the PHP side draws, and follows the
// field's text: typing, the rich text editor's changes and the form's submit.
function test_ai_fields_scripts_agree()
{
	$panel = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ai_write.js');
	$marks = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ai_fields.js');

	pg_assert_contains("'pg:ai-write-used'", $panel, 'panel event');
	pg_assert_true(substr_count($panel, 'announce(settings') >= 2, 'both ways of using a draft announce it');
	pg_assert_contains('editor.setData(value, function () {', $panel, 'the editor announces once the draft is in');
	pg_assert_contains("'pg:ai-write-used'", $marks, 'marks event');
	pg_assert_contains("'input[data-pg-ai-fields]'", $marks, 'input');
	pg_assert_contains("'data-pg-ai-mark'", $marks, 'marker column');
	pg_assert_contains("'data-pg-ai-field'", $marks, 'marker element');
	pg_assert_contains("'.pg-ai-mark-remove'", $marks, 'remove button');
	pg_assert_contains("'d-none'", $marks, 'hidden marker');
	pg_assert_contains("addEventListener('input'", $marks, 'typing');
	pg_assert_contains("addEventListener('change'", $marks, 'a changed field');
	pg_assert_contains("addEventListener('submit'", $marks, 'the last look before the save');
	pg_assert_contains("'instanceReady'", $marks, 'the rich text editor once it runs');
	pg_assert_contains("on('change'", $marks, 'the rich text editor changes');
}
