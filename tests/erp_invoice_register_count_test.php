<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the count of the ERP invoice register: the list reads at most
 * 500 rows, so a view with more matching invoices names both numbers
 * ("500 of 1,240") and says under the table that only the latest rows are
 * shown, as the quotes list does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

function _erp_invoice_register_count_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_invoices.php');
}

// The list and the count share the cap and the conditions; the count runs
// only when the list came back full, so a short register costs no query.
function test_erp_invoice_register_counts_the_whole_view()
{
	$source = _erp_invoice_register_count_source();

	pg_assert_contains('$invoice_limit = 500;', $source);
	pg_assert_contains("LIMIT ' . \$invoice_limit);", str_replace('"', "'", $source));
	pg_assert_false(strpos($source, 'LIMIT 500') !== false, 'the cap is written once');
	pg_assert_contains('if (count($invoices) >= $invoice_limit) {', $source);
	pg_assert_contains('$invoice_total = (int) db_value("SELECT COUNT(*) FROM erp_invoices i" . $where);', $source);
}

// The filter note names both numbers in the panel number format when the
// cap cut the list, and no longer prints the bare count of rows read.
function test_erp_invoice_register_note_names_both_numbers()
{
	$source = _erp_invoice_register_count_source();

	pg_assert_contains("lang(array('string' => '{var:1} of {var:2}', 'vars' => array(pg_format_number(count(\$invoices), 0), pg_format_number(\$invoice_total, 0))))", $source);
	pg_assert_false(strpos($source, "'(' . count(\$invoices) . ')'") !== false, 'the bare row count is gone');
}

// Under the table, a cut list says it shows only the latest rows and how to
// narrow it or reach the rest; the text exists in Turkish.
function test_erp_invoice_register_cap_line()
{
	$source = _erp_invoice_register_count_source();
	$key = 'Only the latest {var:1} of the {var:2} matching invoices are shown; pick a due-date or e-document view to narrow the list.';

	pg_assert_contains("'string' => '" . $key . "'", $source);
	pg_assert_contains('if ($invoice_total > count($invoices)) {', $source);
	pg_assert_contains('<div class="form-text px-5 pb-5">', $source);

	$tr = pg_test_language_map('tr');
	pg_assert_true(isset($tr[$key]), 'the cap line has a Turkish value');
	pg_assert_true(isset($tr['{var:1} of {var:2}']), 'the count note has a Turkish value');
}
