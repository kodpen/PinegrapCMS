<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bank statements: a cancel's reversing row and a cancelled movement are no
 * match candidates, a matched line can be undone, only bank, card terminal
 * and credit card tills take a statement, and a combobox inside a scrolling
 * table stays inside it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

function erp_bank_statement_bugs_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_bank_statement_kinds()
{
	pg_assert_same(array('bank', 'pos', 'credit_card'), erp_bank_statement_kinds(), 'kinds with a statement');
	pg_assert_same("kind IN ('bank', 'pos', 'credit_card')", erp_bank_statement_kinds_sql(), 'condition');
	pg_assert_same("c.kind IN ('bank', 'pos', 'credit_card')", erp_bank_statement_kinds_sql('c.'), 'condition with an alias');
}

function test_erp_bank_statement_kinds_used()
{
	$list = erp_bank_statement_bugs_source('erp_bank_statements.php');
	pg_assert_contains('WHERE is_active = 1 AND " . erp_bank_statement_kinds_sql() . " ORDER BY', $list, 'bank account list');

	$import = erp_bank_statement_bugs_source('includes/erp/bank_import.php');
	pg_assert_contains("AND is_active = 1 AND \" . erp_bank_statement_kinds_sql() . \" LIMIT 1", $import, 'upload check');
}

function test_erp_bank_statement_match_skips_cancels()
{
	$import = erp_bank_statement_bugs_source('includes/erp/bank_import.php');
	$match = substr($import, strpos($import, 'function erp_bank_statement_match('), 2000);

	pg_assert_contains("AND t.doc_type <> 'cancel'", $match, 'reversing row is no candidate');
	pg_assert_contains("NOT EXISTS (SELECT 1 FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = t.id)", $match, 'cancelled movement is no candidate');
}

function test_erp_bank_statement_unmatch()
{
	$import = erp_bank_statement_bugs_source('includes/erp/bank_import.php');
	pg_assert_contains("SET status = 'new', cash_id = 0\n        WHERE id = '\" . (int) \$line_id . \"' AND status = 'matched'", $import, 'only a matched line is undone');

	$screen = erp_bank_statement_bugs_source('erp_bank_statement.php');
	pg_assert_contains("if (\$action === 'unmatch') {", $screen, 'unmatch action');
	pg_assert_contains("if (((string) \$line['status'] === 'matched') && !\$readonly) {", $screen, 'button on a matched line only');
}

function test_erp_bank_statement_combobox_in_scrolling_table()
{
	$css = erp_bank_statement_bugs_source('assets/css/backend.src.css');
	pg_assert_contains(".table-responsive {\n  position: relative;\n}", $css, 'scroll box holds the hidden select');
}
