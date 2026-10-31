<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_csv_import() in includes/translate/jobs.php on the paths
 * that are decided before a row reaches the database: the header, blank
 * rows, a bad hash and a file without the hash or translation column. A row
 * with a valid hash is matched against translation_string, which a unit test
 * cannot reach.
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
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engines.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/jobs.php');

// A file without the hash or the translation column is refused with a
// reason, not answered with all-zero counts.
function test_translation_csv_import_refuses_missing_columns()
{
	$summary = pg_tr_csv_import('tr', "id;text;translation\n1;Hello;Merhaba\n");

	pg_assert_true($summary['error'] !== '', 'no hash column');
	pg_assert_same(0, $summary['rejected'], 'nothing counted');
	pg_assert_same(array(), $summary['problems'], 'no row problems');

	$summary = pg_tr_csv_import('tr', "hash,source\n" . str_repeat('a', 40) . ",Hello\n");

	pg_assert_true($summary['error'] !== '', 'no translation column, comma file');

	$summary = pg_tr_csv_import('tr', '');

	pg_assert_true($summary['error'] !== '', 'an empty file');
}

// Blank lines and rows of empty cells (";;" from a spreadsheet) are skipped,
// not rejected.
function test_translation_csv_import_skips_blank_rows()
{
	$csv = "\xEF\xBB\xBFhash;source;translation\n\n;;\n  ;  ; \n";
	$summary = pg_tr_csv_import('tr', $csv);

	pg_assert_same('', $summary['error'], 'header found');
	pg_assert_same(3, $summary['skipped'], 'three blank rows');
	pg_assert_same(0, $summary['rejected'], 'none rejected');
	pg_assert_same(array(), $summary['problems'], 'no problems');
}

// A bad hash is rejected and named with its file line; a line break inside
// a quoted cell moves the later lines down.
function test_translation_csv_import_names_bad_hash_line()
{
	$csv = "hash;source;translation\n"
		. "nothex;Hello;Merhaba\n"
		. ";\"Two\nlines\";\"İki\nsatır\"\n"
		. "\n"
		. "abc123;Bye;Hoşça kal\n";
	$summary = pg_tr_csv_import('tr', $csv);

	pg_assert_same(3, $summary['rejected'], 'three bad hashes');
	pg_assert_same(1, $summary['skipped'], 'one blank line');
	pg_assert_same(3, count($summary['problems']), 'three problem lines');
	pg_assert_contains('Line 2:', $summary['problems'][0], 'first data line');
	pg_assert_contains('Line 3:', $summary['problems'][1], 'the row of two broken cells starts on line 3');
	pg_assert_contains('Line 7:', $summary['problems'][2], 'after the row on lines 3-5 and the blank line 6');
}

// The reasons the result check gives are told in words.
function test_translation_csv_import_check_reasons()
{
	pg_assert_true(pg_tr_csv_import_reason('placeholders') !== pg_tr_csv_import_reason('markup'), 'distinct reasons');
	pg_assert_true(pg_tr_csv_import_reason('empty') !== '', 'empty');
	pg_assert_true(pg_tr_csv_import_reason('something else') !== '', 'a fallback');
}
