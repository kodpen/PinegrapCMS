<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the expenses the VAT report reads (erp_vat_report(),
 * includes/erp/vat_report.php, through erp_expenses_period()): every expense
 * of the period counts, however many there are. The list screen's reader
 * stops at 5000 rows, newest first, so a busy period lost its oldest
 * expenses and their deductible tax. Run in a PHP process of its own with a
 * fake db layer that honours LIMIT.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Builds the VAT report of 2026 over $count deductible expenses of 100 net
// and 20 tax each, and returns its expense figures. Null when PHP cannot be
// started again.
function erp_vat_report_expense_cap_test_run($count)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgvatcap');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
date_default_timezone_set('UTC');
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function escape($value) { return addslashes((string) $value); }
function waf_table_has_column($table, $column) { return $table !== 'orders'; }
function db_value($sql) { return ''; }
function db_item($sql) { return false; }

// Expenses of the period, newest first or oldest first as the query asks,
// cut at its LIMIT; no other table holds anything.
function db_items($sql)
{
	if (strpos($sql, 'FROM erp_expenses e') === false) {
		return array();
	}

	$rows = array();
	for ($id = 1; $id <= $GLOBALS['count']; $id++) {
		$rows[] = array(
			'id' => $id, 'status' => 'paid', 'tax_deductible' => 1, 'tax_rate' => '20.000',
			'expense_date' => sprintf('2026-%02d-%02d', 1 + ($id % 12), 1 + ($id % 28)),
			'net_base' => 100, 'tax_base' => 20, 'total_base' => 120,
			'document_no' => '', 'currency' => 'TRY', 'supplier' => 'S', 'supplier_tax_number' => '',
			'category_name' => 'C', 'category_code' => '', 'till_name' => '', 'description' => '',
		);
	}

	if (preg_match('/ORDER BY e\.expense_date DESC/', $sql)) {
		$rows = array_reverse($rows);
	}
	if (preg_match('/LIMIT (\d+)/', $sql, $limit)) {
		$rows = array_slice($rows, 0, (int) $limit[1]);
	}

	return $rows;
}

require $pg_dir . '/includes/erp/money.php';
require $pg_dir . '/includes/erp/fx.php';
require $pg_dir . '/includes/erp/withholding.php';
require $pg_dir . '/includes/erp/expenses.php';
require $pg_dir . '/includes/erp/accountant.php';
require $pg_dir . '/includes/erp/profit.php';
require $pg_dir . '/includes/erp/rules.php';
require $pg_dir . '/includes/erp/vat_report.php';

$report = erp_vat_report('2026-01-01', '2026-12-31');

echo json_encode(array(
	'count' => $report['kinds']['expense']['count'],
	'deductible' => $report['totals']['deductible_expenses'],
	'documents' => count($report['documents']),
));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'count\'] = ' . (int) $count . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['count'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// 6000 expenses in a year: all of them and all their tax are in the report,
// not the newest 5000.
function test_erp_vat_report_counts_every_expense_of_the_period()
{
	$run = erp_vat_report_expense_cap_test_run(6000);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same(6000, $run['count'], 'every expense counted');
	pg_assert_same(6000 * 20, $run['deductible'], 'every expense\'s tax deducted');
	pg_assert_same(6000, $run['documents'], 'every expense listed');
}
