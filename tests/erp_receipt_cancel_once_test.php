<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the double cancel of an ERP receipt (includes/erp/cash.php,
 * erp_receipt_cancel()): two presses of Cancel (two tabs, a resent form, two
 * API calls) both passed the "already cancelled" check made before the
 * transaction, and each posted its own reversal pair, so the till and the
 * account moved back twice. The receipt row is now locked inside the
 * transaction and the reversal asked for again with a locking read. Run in a
 * PHP process of its own with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Cancels receipt 40 (ledger row 70) while another request has just
// committed its own cancel: reads made before the transaction see no
// reversal, a locking read inside it sees reversal 41. Returns the answer,
// the writes attempted and whether the transaction was rolled back. Null
// when PHP cannot be started again.
function erp_receipt_cancel_once_test_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgrcancel');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);

$GLOBALS['in_tx'] = false;
$GLOBALS['writes'] = array();
$GLOBALS['rolled_back'] = false;

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function escape($value) { return addslashes((string) $value); }
function prepare_form_data_for_output($value, $type) { return (string) $value; }
function erp_lock_refusal($date) { return ''; }
function erp_lock_refused($refusal) {}
function erp_base_currency() { return 'TRY'; }
function erp_txn_settlements($ledger_id) { return array(); }
function erp_event_receipt($cash_id, $event) {}
function erp_db_error() { return ''; }

function erp_tx_begin() { $GLOBALS['in_tx'] = true; return true; }
function erp_tx_commit() { $GLOBALS['in_tx'] = false; return true; }
function erp_tx_rollback() { $GLOBALS['in_tx'] = false; $GLOBALS['rolled_back'] = true; return true; }

function erp_query($sql)
{
	$GLOBALS['writes'][] = trim(substr(trim($sql), 0, 40));
	return false;
}

function erp_account_post($movement)
{
	$GLOBALS['writes'][] = 'erp_account_post';
	return false;
}

// The other request's reversal is only visible to a locking read made
// inside the transaction (it committed after this request's first read).
function erp_receipt_cancel_once_reversal_seen($sql)
{
	return $GLOBALS['in_tx'] && (strpos($sql, 'FOR UPDATE') !== false);
}

function db_item($sql)
{
	if (strpos($sql, 'FROM erp_cash_transactions c') !== false) {
		return array('id' => 40, 'doc_date' => '2026-11-01', 'direction' => 'in', 'amount' => 5000,
			'currency' => 'TRY', 'exchange_rate' => 1, 'exchange_rate_date' => '2026-11-01',
			'exchange_rate_source' => 'base', 'amount_base' => 5000, 'account_id' => 3,
			'cash_account_id' => 2, 'payment_method' => 'cash', 'ledger_id' => 70, 'reversal_id' => null);
	}
	if (strpos($sql, 'FROM erp_account_transactions WHERE id') !== false) {
		return array('id' => 70, 'direction' => 'credit', 'amount_base' => 5000);
	}
	if (strpos($sql, "doc_type = 'cancel'") !== false) {
		return erp_receipt_cancel_once_reversal_seen($sql) ? array('id' => 41) : null;
	}
	if (strpos($sql, 'FROM erp_cash_transactions') !== false) {
		return array('id' => 40);
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db_value($sql)
{
	if (strpos($sql, "doc_type = 'cancel'") !== false) {
		return erp_receipt_cancel_once_reversal_seen($sql) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_items($sql) { throw new Exception('unexpected db_items: ' . $sql); }

class db { public static $con = null; }

require $pg_dir . '/includes/erp/cash.php';

$result = erp_receipt_cancel(40, 'Entered twice', 1);

echo json_encode(array('result' => $result, 'writes' => $GLOBALS['writes'], 'rolled_back' => $GLOBALS['rolled_back']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
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

// The second cancel meets the first one's reversal under the lock and is
// refused before a till or ledger movement is written.
function test_erp_receipt_cancel_once_second_press_writes_nothing()
{
	$run = erp_receipt_cancel_once_test_run();

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'the second cancel is refused');
	pg_assert_same('That receipt has already been cancelled.', $run['result']['error']);
	pg_assert_same(array(), $run['writes'], 'no reversal movement is attempted');
	pg_assert_true($run['rolled_back'], 'the transaction is rolled back');
}

// The lock is taken inside the transaction, before the allocations are
// taken off and before the first reversal is written.
function test_erp_receipt_cancel_once_lock_comes_before_the_writes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
	$start = strpos($source, 'function erp_receipt_cancel($cash_id');
	$end = strpos($source, "\nfunction ", $start + 1);
	$body = ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);

	$begin = strpos($body, 'erp_tx_begin()');
	$lock = strpos($body, 'FOR UPDATE');
	$unsettle = strpos($body, 'erp_txn_unsettle_all(');

	pg_assert_true(($begin !== false) && ($lock !== false) && ($lock > $begin), 'locked inside the transaction');
	pg_assert_true(($unsettle !== false) && ($lock < $unsettle), 'before anything is written');
}
