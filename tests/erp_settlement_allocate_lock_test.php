<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two allocations made at the same moment
 * (includes/erp/settlement.php, erp_settlement_allocate()): the open amount
 * of the invoice and the unallocated amount of the receipt were read without
 * a lock, before the transaction, so two allocations that each fit alone
 * both passed and together over-allocated the invoice or the receipt. The
 * receipt's ledger row and the invoice are now locked inside the
 * transaction and both amounts read again before anything is written. Run
 * in a PHP process of its own with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Allocates 5000 of receipt ledger row 70 (10000) to invoice 5 (10000) while
// another request has just committed its own allocation of $other_side
// ('invoice': 6000 more on invoice 5 from another receipt; 'receipt': 6000
// of receipt 70 on another invoice). Reads before this request holds a lock
// see neither; reads after a locking read inside the transaction see it.
// Returns the answer, the writes attempted, the order of the locks and
// whether the transaction was rolled back. Null when PHP cannot be started
// again.
function erp_settlement_allocate_lock_test_run($other_side)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgallocate');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);

$GLOBALS['in_tx'] = false;
$GLOBALS['locked'] = false;
$GLOBALS['locks'] = array();
$GLOBALS['writes'] = array();
$GLOBALS['rolled_back'] = false;

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function escape($value) { return addslashes((string) $value); }
function erp_base_currency() { return 'TRY'; }
function erp_fx_enabled() { return false; }
function erp_fx_auto_diff() { return false; }
function erp_to_base($amount, $rate) { return $amount; }
function erp_invoice_returned_total($invoice_id) { return 0; }
function erp_db_error() { return ''; }

function erp_tx_begin() { $GLOBALS['in_tx'] = true; return true; }
function erp_tx_commit() { $GLOBALS['in_tx'] = false; return true; }
function erp_tx_rollback() { $GLOBALS['in_tx'] = false; $GLOBALS['rolled_back'] = true; return true; }

function erp_query($sql)
{
	$GLOBALS['writes'][] = trim(substr(trim($sql), 0, 40));
	return false;
}

// A locking read inside the transaction waits for the other request and
// sees its commit; so does every read made after it.
function erp_allocate_lock_test_read($sql)
{
	if ($GLOBALS['in_tx'] && (strpos($sql, 'FOR UPDATE') !== false)) {
		$GLOBALS['locked'] = true;
		$GLOBALS['locks'][] = (strpos($sql, 'FROM erp_invoices') !== false) ? 'invoice' : 'receipt';
	}

	return $GLOBALS['locked'];
}

function db_item($sql)
{
	$fresh = erp_allocate_lock_test_read($sql);

	if (strpos($sql, 'FROM erp_account_transactions t') !== false) {
		return array('id' => 70, 'doc_type' => 'collection', 'doc_id' => 40, 'account_id' => 3,
			'amount' => 10000, 'currency' => 'TRY', 'exchange_rate' => 1, 'doc_date' => '2026-11-01',
			'reversal_id' => null);
	}
	if (strpos($sql, 'FROM erp_account_transactions') !== false) {
		return array('id' => 70, 'amount' => 10000);
	}
	if (strpos($sql, 'FROM erp_invoices') !== false) {
		$paid = ($fresh && ($GLOBALS['other_side'] === 'invoice')) ? 6000 : 0;
		return array('id' => 5, 'account_id' => 3, 'direction' => 'sales', 'doc_type' => 'invoice',
			'currency' => 'TRY', 'full_number' => 'A-1', 'grand_total' => 10000, 'grand_total_base' => 10000,
			'paid_total' => $paid, 'status' => ($paid > 0) ? 'partially_paid' : 'issued');
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db_value($sql)
{
	$fresh = erp_allocate_lock_test_read($sql);

	if (strpos($sql, 'SELECT amount FROM erp_account_transactions') !== false) {
		return 10000;
	}
	if (strpos($sql, 'FROM erp_settlements') !== false) {
		if (strpos($sql, "AND invoice_id") !== false) {
			return 0;
		}
		return ($fresh && ($GLOBALS['other_side'] === 'receipt')) ? 6000 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_items($sql) { throw new Exception('unexpected db_items: ' . $sql); }

class db { public static $con = null; }

require $pg_dir . '/includes/erp/settlement.php';

$result = erp_settlement_allocate(array('invoice_id' => 5, 'account_txn_id' => 70, 'amount' => 5000, 'created_by' => 1));

echo json_encode(array('result' => $result, 'writes' => $GLOBALS['writes'], 'locks' => $GLOBALS['locks'],
	'rolled_back' => $GLOBALS['rolled_back']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'other_side\'] = ' . var_export($other_side, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['result'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The invoice was part paid by another receipt in between: 5000 is more
// than the 4000 left, refused before the allocation is written.
function test_erp_settlement_allocate_lock_invoice_side()
{
	$run = erp_settlement_allocate_lock_test_run('invoice');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'the second allocation is refused');
	pg_assert_same('That amount is more than the invoice is short of.', $run['result']['error']);
	pg_assert_same(array(), $run['writes'], 'no allocation is written');
	pg_assert_true($run['rolled_back'], 'the transaction is rolled back');
}

// The receipt was part allocated to another invoice in between: 5000 is
// more than the 4000 it has left, refused before the allocation is written.
function test_erp_settlement_allocate_lock_receipt_side()
{
	$run = erp_settlement_allocate_lock_test_run('receipt');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'the second allocation is refused');
	pg_assert_same('That amount is more than is unallocated on this receipt.', $run['result']['error']);
	pg_assert_same(array(), $run['writes'], 'no allocation is written');
	pg_assert_true($run['rolled_back'], 'the transaction is rolled back');
}

// The receipt's ledger row is locked before the invoice, the order
// erp_post_receipt() writes them in, so two requests never hold one each.
function test_erp_settlement_allocate_lock_order()
{
	$run = erp_settlement_allocate_lock_test_run('invoice');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_same(array('receipt', 'invoice'), $run['locks']);
}
