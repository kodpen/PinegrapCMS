<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the owner check of erp_settlement_remove()
 * (includes/erp/settlement.php): the invoice and receipt screens name the
 * record an allocation must hang on, and an allocation of another record is
 * refused before anything is written. Run in a PHP process of its own with a
 * fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Calls erp_settlement_remove() for allocation 9 (invoice 5, ledger row 70)
// with $owner and returns its answer and whether a transaction was begun.
// The fake transaction refuses to start, so a call that gets that far writes
// nothing either. Null when PHP cannot be started again.
function erp_settlement_remove_owner_test_run($owner)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgunsettle');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);

$GLOBALS['began'] = false;

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }

function db_item($sql)
{
	if (strpos($sql, 'FROM erp_settlements s') !== false) {
		return array('id' => 9, 'invoice_id' => 5, 'account_txn_id' => 70, 'amount' => 1000,
			'source_type' => 'collection', 'currency' => 'TRY', 'status' => 'issued');
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function erp_tx_begin() { $GLOBALS['began'] = true; return false; }

require $pg_dir . '/includes/erp/settlement.php';

$result = erp_settlement_remove(9, 1, $GLOBALS['owner']);

echo json_encode(array('result' => $result, 'began' => $GLOBALS['began']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'owner\'] = ' . var_export($owner, true) . ";\n"
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

// An allocation of another invoice or another receipt is refused before a
// transaction starts: the screen used to remove it, then report it missing.
function test_erp_settlement_remove_refuses_another_owner()
{
	foreach (array(array('invoice_id' => 6), array('account_txn_id' => 71), array('invoice_id' => 5, 'account_txn_id' => 71)) as $owner) {
		$run = erp_settlement_remove_owner_test_run($owner);

		if ($run === null) {
			pg_assert_true(true, 'PHP cannot be started again here');
			return;
		}

		pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
		pg_assert_false($run['result']['success'], 'refused: ' . json_encode($owner));
		pg_assert_same('The allocation could not be found.', $run['result']['error']);
		pg_assert_false($run['began'], 'nothing written: ' . json_encode($owner));
	}
}

// The record's own allocation, or no owner named (the order flow), goes on
// to the removal.
function test_erp_settlement_remove_goes_on_for_its_owner()
{
	foreach (array(array('invoice_id' => 5), array('account_txn_id' => 70), array()) as $owner) {
		$run = erp_settlement_remove_owner_test_run($owner);

		if ($run === null) {
			pg_assert_true(true, 'PHP cannot be started again here');
			return;
		}

		pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
		pg_assert_true($run['began'], 'reaches the transaction: ' . json_encode($owner));
	}
}

// Both screens name their record.
function test_erp_settlement_remove_screens_name_the_owner()
{
	pg_assert_contains("array('invoice_id' => \$invoice_id)", (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php'));
	pg_assert_contains("array('account_txn_id' => \$ledger_id)", (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_receipt.php'));
}
