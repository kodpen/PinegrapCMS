<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Translations screen: CSV export/import guards, the glossary duplicate
 * check, the return address after a form post, row markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of function $name in $file, up to the next top-level function.
function translations_bugs_test_body($file, $name)
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' exists in ' . $file);
	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_translations_csv_export_guards_the_text_cells()
{
	$body = translations_bugs_test_body('includes/translate/jobs.php', 'pg_tr_csv_export');

	pg_assert_true(strpos($body, "pg_csv_formula_guard(\$row['source_text'])") !== false, 'the source cell is guarded');
	pg_assert_true(strpos($body, "pg_csv_formula_guard(\$row['text'])") !== false, 'the translation cell is guarded');
	pg_assert_true(strpos($body, "pg_csv_formula_guard(\$row['hash'])") === false, 'the hash stays as it is');
}

function test_translations_csv_import_unguards_the_translation()
{
	$body = translations_bugs_test_body('includes/translate/jobs.php', 'pg_tr_csv_import');

	pg_assert_true(strpos($body, "pg_csv_formula_unguard(\$row[\$columns['translation']])") !== false, 'the translation cell loses the export guard');
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/screen.php');

function test_translations_view_query_keeps_the_view()
{
	pg_assert_same('language=en&page_id=1714&filter=blocking&q=a%20b', pg_tr_screen_view_query('en', array('page_id' => '1714', 'filter' => 'blocking', 'q' => ' a b ')), 'page, filter and search');
	pg_assert_same('language=en&group=ui', pg_tr_screen_view_query('en', array('page_id' => '5', 'group' => 'ui', 'filter' => 'all')), 'a group drops the page; "all" is the default');
	pg_assert_same('language=en', pg_tr_screen_view_query('en', array('group' => 'x"y', 'filter' => 'nope', 'page_id' => '-3', 'q' => array('z'))), 'malformed values are left out');
	pg_assert_same('language=pt-BR', pg_tr_screen_view_query('pt-BR', array()), 'only the language');
	pg_assert_same('language=en&page_id=4&tab=glossary', pg_tr_screen_view_query('en', array('page_id' => '4', 'tab' => 'glossary')), 'the Glossary tab is kept');
	pg_assert_same('language=en&tab=activity', pg_tr_screen_view_query('en', array('tab' => 'activity')), 'the Activity tab is kept');
	pg_assert_same('language=en', pg_tr_screen_view_query('en', array('tab' => 'texts')), '"texts" is the default tab');
	pg_assert_same('language=en', pg_tr_screen_view_query('en', array('tab' => 'nope')), 'an unknown tab is left out');
}

function test_translations_screen_share()
{
	pg_assert_same(26, pg_tr_screen_share(316, 1204), 'rounded percent');
	pg_assert_same(0, pg_tr_screen_share(0, 0), 'nothing scanned');
	pg_assert_same(99, pg_tr_screen_share(999, 1000), 'not 100 while a text is left');
	pg_assert_same(1, pg_tr_screen_share(1, 1000), 'not 0 once a text is translated');
	pg_assert_same(100, pg_tr_screen_share(5, 5), 'all translated');
	pg_assert_same(100, pg_tr_screen_share(7, 5), 'never above 100');
}

function test_translations_glossary_duplicate()
{
	$rows = array(
		array('id' => 1, 'term' => 'pg-test dup', 'case_sensitive' => 0),
		array('id' => 2, 'term' => 'Apple', 'case_sensitive' => 1),
	);

	pg_assert_same(1, pg_tr_glossary_duplicate($rows, 0, 'PG-TEST DUP', false)['id'], 'case-insensitive repeat');
	pg_assert_same(1, pg_tr_glossary_duplicate($rows, 0, 'PG-TEST DUP', true)['id'], 'a case-sensitive term repeats a case-insensitive one');
	pg_assert_same(null, pg_tr_glossary_duplicate($rows, 0, 'apple', true), 'two case-sensitive terms differing in case');
	pg_assert_same(2, pg_tr_glossary_duplicate($rows, 0, 'apple', false)['id'], 'a case-insensitive term repeats a case-sensitive one');
	pg_assert_same(null, pg_tr_glossary_duplicate($rows, 1, 'PG-TEST DUP', false), 'the edited row is not its own repeat');
	pg_assert_same(1, pg_tr_glossary_duplicate($rows, 0, 'pg-test dup', false)['id'], 'exact repeat');
	pg_assert_same(null, pg_tr_glossary_duplicate(array(), 0, 'x', false), 'empty glossary');
}

function test_translations_target_rows()
{
	pg_assert_same(1, pg_tr_screen_target_rows(''), 'empty box: one line');
	pg_assert_same(1, pg_tr_screen_target_rows('short'), 'one short line');
	pg_assert_same(4, pg_tr_screen_target_rows("a\nb\nc\nd"), 'one row a line');
	pg_assert_same(3, pg_tr_screen_target_rows(str_repeat('ğ', 150)), 'a long line wraps by characters, 50 a row');
	pg_assert_same(2, pg_tr_screen_target_rows(str_repeat('ğ', 51)), 'one character past a row');
	pg_assert_same(12, pg_tr_screen_target_rows(str_repeat('x', 3000)), 'capped');
}

function test_translations_rows_name_their_source()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/translations.php');

	pg_assert_true(strpos($source, 'id="pg_tr_source_\' . $row_id . \'"') !== false, 'the source text has an id');
	pg_assert_same(2, substr_count($source, 'aria-describedby="pg_tr_source_\' . $row_id . \''), 'box and Save point at it');
	pg_assert_contains('aria-describedby="pg_tr_source_\' . $row_id . \' pg_tr_why_\' . $row_id . \'"', $source, 'the box also points at its refusal');
	pg_assert_same(3, substr_count($source, '$view_fields') - substr_count($source, '$view_fields .= ') - substr_count($source, '$view_fields = '), 'glossary, delete and import forms carry the view');
}

// The row (TS2): a refusal under the box, the keys, "Details" read on its
// first opening from the read-only "details" action.
function test_translations_row_refusal_keys_and_details()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$js = file_get_contents($root . 'assets/js/translations.js');
	$screen = file_get_contents($root . 'translations.php');
	$action = file_get_contents($root . 'translations_action.php');

	pg_assert_contains("refuse(row, error.message)", $js, 'a refused save is shown under its box');
	pg_assert_contains("box.setAttribute('aria-invalid', 'true')", $js, 'the box takes the rose ring');
	pg_assert_contains("saveRow(row, button, 'reviewed', true)", $js, 'Ctrl+Enter saves and moves on');
	pg_assert_contains("(event.key === 'ArrowDown') || (event.key === 'ArrowUp')", $js, 'Alt+Down and Alt+Up');
	pg_assert_contains("action: 'details'", $js, 'Details asks the server');
	pg_assert_contains("action: 'accept_source',\n            string_ids: [parseInt(row.getAttribute('data-string-id'), 10)]", $js, 'a row keeps its own source');
	pg_assert_contains('aria-controls="pg_tr_details_\' . $row_id . \'"', $screen, 'the caret names its panel');
	pg_assert_contains('<div class="pg-tr-details" id="pg_tr_details_\' . $row_id . \'" hidden></div>', $screen, 'the panel starts closed and empty');
	pg_assert_contains("case 'details':", $action, 'the details action');
	pg_assert_contains("pg_tr_screen_details(\$string_id, \$language)", $action, 'reads the text\'s details');
	pg_assert_false(strpos($action, "in_array(\$editor_action, array('node', 'save', 'status', 'details')") !== false, 'not offered to a user limited to a page');
}
