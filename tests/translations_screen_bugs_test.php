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
