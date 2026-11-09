<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Translations screen's helpers in
 * includes/translate/screen.php: what a page answered the search engines as
 * the page list shows it, the places the texts that keep a page out come
 * from and their order, which records count toward a page's verdict (a
 * record view's, never a list's), and the text ids "keep as in the source
 * and approve" accepts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/screen.php');

// A stored answer of 1 or 0; no row means the page was not drawn since.
function test_translations_screen_verdict_state()
{
	$verdicts = array(4 => 1, 7 => 0);

	pg_assert_same('indexed', pg_tr_screen_verdict_state($verdicts, 4), 'indexable');
	pg_assert_same('noindex', pg_tr_screen_verdict_state($verdicts, 7), 'kept out');
	pg_assert_same('unknown', pg_tr_screen_verdict_state($verdicts, 9), 'not drawn since');
	pg_assert_same('noindex', pg_tr_screen_verdict_state($verdicts, '7'), 'a page id as a string');
	pg_assert_same('unknown', pg_tr_screen_verdict_state(null, 4), 'no answers read');
	pg_assert_same('indexed', pg_tr_screen_verdict_state($verdicts, 4, false), 'own setting open');
}

// A page its own SEO setting closes is kept out in every language, whatever
// its translations: the stored answer is not read.
function test_translations_screen_verdict_state_own_noindex()
{
	$verdicts = array(4 => 1, 7 => 0);

	pg_assert_same('own_noindex', pg_tr_screen_verdict_state($verdicts, 4, true), 'stored 1');
	pg_assert_same('own_noindex', pg_tr_screen_verdict_state($verdicts, 7, true), 'stored 0');
	pg_assert_same('own_noindex', pg_tr_screen_verdict_state($verdicts, 9, true), 'no row');
	pg_assert_same('own_noindex', pg_tr_screen_verdict_state(null, 4, true), 'no answers read');
}

// "Keep as in the source" writes the source only when the incoming-text
// sanitizer would keep it as it is; otherwise nothing ('').
function test_translations_screen_accept_source_text()
{
	pg_assert_same('Plain name', pg_tr_accept_source_text('Plain name', 'text'), 'a plain name');
	pg_assert_same('Plain name', pg_tr_accept_source_text('  Plain   name ', 'text'), 'whitespace is normalised, not a change');
	pg_assert_same('', pg_tr_accept_source_text('Size <M>', 'text'), 'angle brackets would be stripped');
	pg_assert_same('', pg_tr_accept_source_text('Ali <ali@example.com>', 'text'), 'an address in brackets');
	pg_assert_same('', pg_tr_accept_source_text('A &amp; B', 'text'), 'an entity would be decoded');
	pg_assert_same('', pg_tr_accept_source_text('Note <!--pg-x-->', 'text'), 'a pipeline marker would be defused');
	pg_assert_same('A & B', pg_tr_accept_source_text('A & B', 'text'), 'a bare ampersand stays');
	pg_assert_same('<strong>Ok</strong>', pg_tr_accept_source_text('<strong>Ok</strong>', 'inline'), 'inline markup the editor allows');
	pg_assert_same('Fish & chips <em>now</em>', pg_tr_accept_source_text('Fish & chips <em>now</em>', 'inline'), 'an inline source in its stored form');
	pg_assert_same('', pg_tr_accept_source_text('   ', 'text'), 'an empty source');
}

// The page itself first, its title and description next, then what it
// places with the most texts first; equal counts by kind and name.
function test_translations_screen_reason_lines_order()
{
	$rows = array(
		array('owner_type' => 'menu', 'owner_id' => '3', 'count' => '2'),
		array('owner_type' => 'form_records', 'owner_id' => '44', 'count' => '112'),
		array('owner_type' => 'page_seo', 'owner_id' => '1', 'count' => '1'),
		array('owner_type' => 'shared', 'owner_id' => '12', 'count' => '2'),
		array('owner_type' => 'page', 'owner_id' => '1', 'count' => '3'),
	);
	$names = array('menu:3' => 'Main', 'form_records:44' => 'ARAÇLAR', 'shared:12' => 'Header');

	$lines = pg_tr_screen_reason_lines($rows, $names);

	pg_assert_same(array('page', 'page_seo', 'form_records', 'menu', 'shared'), array_column($lines, 'kind'), 'order');
	pg_assert_same(array('kind' => 'form_records', 'id' => 44, 'name' => 'ARAÇLAR', 'count' => 112), $lines[2], 'a line');
	pg_assert_same('', $lines[0]['name'], 'the page needs no name');
}

// Rows of the same place are added up; a place with nothing pending and a
// malformed row are left out.
function test_translations_screen_reason_lines_merge_and_skip()
{
	$lines = pg_tr_screen_reason_lines(array(
		array('owner_type' => 'shared', 'owner_id' => 5, 'count' => 2),
		array('owner_type' => 'shared', 'owner_id' => 5, 'count' => 3),
		array('owner_type' => 'menu', 'owner_id' => 1, 'count' => 0),
		array('count' => 4),
		'junk',
	));

	pg_assert_same(1, count($lines), 'one line');
	pg_assert_same(5, $lines[0]['count'], 'added up');
	pg_assert_same(array(), pg_tr_screen_reason_lines(null), 'nothing given');
}

// Positive whole numbers, each once; anything else is dropped, and more
// than the limit is refused as a whole.
function test_translations_screen_string_ids()
{
	pg_assert_same(array(3, 9, 12), pg_tr_screen_string_ids(array(3, '9', 3, '12')), 'numbers and numeric strings, once');
	pg_assert_same(array(5), pg_tr_screen_string_ids(array(0, -2, '1.5', 'x', '07a', null, array(1), 5.0, 5)), 'malformed ids dropped');
	pg_assert_same(array(), pg_tr_screen_string_ids('3,4'), 'not a list');
	pg_assert_same(array(), pg_tr_screen_string_ids(null), 'nothing given');
	pg_assert_same(array(1, 2), pg_tr_screen_string_ids(array(1, 2), 2), 'at the limit');
	pg_assert_same(array(), pg_tr_screen_string_ids(array(1, 2, 3), 2), 'over the limit');
	pg_assert_same(array(1), pg_tr_screen_string_ids(array(1, 1, 1), 1), 'repeats do not count against the limit');
}

// The forms whose records count toward a page's verdict: those a record
// view includes in the translation. A list's records never keep a page out.
function test_translations_screen_record_view_forms()
{
	$configs = array(
		'{"regionType":"form_item_view","custom_form_page_id":14,"translate_records":true}',
		'{"regionType":"form_list_view","custom_form_page_id":21,"translate_records":true}',
		'{"regionType":"blog_list","form_page_id":22,"translate_records":true}',
		'{"regionType":"form_item_view","custom_form_page_id":30}',
		'{"regionType":"form_item_view","form_page_id":14,"translate_records":1}',
		'not json',
		null,
	);

	pg_assert_same(array(14), pg_tr_record_view_forms($configs), 'record views that include their records, once');
	pg_assert_same(array(), pg_tr_record_view_forms(array()), 'no widgets');
}

// The texts of a list's records are left out of "Keeping pages out of
// search"; a record view's stay, as they keep the record's own page out.
function test_translations_screen_blocking_records_sql()
{
	pg_assert_same("u.owner_type <> 'form_records'", pg_tr_screen_blocking_records_sql(array()), 'no record view');
	pg_assert_same("(u.owner_type <> 'form_records' OR u.owner_id IN (14,30))", pg_tr_screen_blocking_records_sql(array(14, '30', 14, 0, -2)), 'record views');
}

// The owners a page's "Why?" counts: a list page loses the records its list
// includes, a record view page keeps its form's.
function test_translations_screen_verdict_owners()
{
	$owners = array(array('page', 5), array('page_seo', 5), array('shared', 12), array('form_records', 21), array('form_records', 14), array('menu', 3));

	pg_assert_same(
		array(array('page', 5), array('page_seo', 5), array('shared', 12), array('form_records', 14), array('menu', 3)),
		pg_tr_verdict_owners($owners, array(14)),
		'the record view\'s form stays'
	);
	pg_assert_same(4, count(pg_tr_verdict_owners($owners, array())), 'a list page');
	pg_assert_same(array(), pg_tr_verdict_owners(null, array(14)), 'nothing given');
}
