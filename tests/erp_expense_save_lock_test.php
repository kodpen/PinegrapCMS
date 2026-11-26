<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for changing an expense that was paid or cancelled while the change
 * form was open (includes/erp/expenses.php, erp_expense_save()). The status
 * was read before the transaction only: a change sent with "Paid now" from
 * one tab while the expense was paid from another wrote a second movement
 * out of the till, and a change of the figures rewrote a paid expense's
 * amount behind its till movement. The expense row is now locked inside the
 * transaction and its status asked again. Run in a PHP process of its own
 * with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Changes unpaid expense 7 while another request has just paid it (or
// cancelled it): reads before the transaction see it unpaid, a locking read
// inside it sees $status_now. Returns the answer, the writes attempted and
// whether the transaction was rolled back; null when PHP cannot be started.
function erp_expense_save_lock_test_run($pay, $status_now)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgexpsave');

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
function waf_table_has_column($table, $column) { return true; }
function erp_lock_refusal($date) { return ''; }
function erp_base_currency() { return 'TRY'; }
function erp_fx_enabled() { return false; }
function erp_fx_currency_allowed($currency) { return true; }
function erp_tax_number_check($value) { return array('error' => '', 'value' => (string) $value); }
function erp_cash_payment_methods() { return array('cash', 'card', 'transfer'); }
function erp_till_future_date_refusal($date) { return ''; }
function erp_till_outflow_refusal($till, $amount) { return ''; }
function erp_expense_recurrence_follow($id) { return true; }
function erp_event_expense($id, $event) { $GLOBALS['writes'][] = $event; }
function erp_db_error() { return ''; }
function erp_cash_refresh_balance($id) { return true; }
function erp_cash_post($movement) { $GLOBALS['writes'][] = 'erp_cash_post'; return 99; }

function erp_tx_begin() { $GLOBALS['in_tx'] = true; return true; }
function erp_tx_commit() { $GLOBALS['in_tx'] = false; return true; }
function erp_tx_rollback() { $GLOBALS['in_tx'] = false; $GLOBALS['rolled_back'] = true; return true; }

function erp_query($sql)
{
	$GLOBALS['writes'][] = trim(substr(trim($sql), 0, 26));
	return true;
}

function erp_expense_save_lock_row($status)
{
	return array('id' => 7, 'status' => $status, 'expense_date' => '2026-11-02', 'category_id' => 2, 'currency' => 'TRY',
		'exchange_rate' => 1, 'exchange_rate_date' => '2026-11-02', 'exchange_rate_source' => 'base',
		'supplier' => 'Landlord', 'description' => 'Rent', 'total_amount' => 10000, 'total_base' => 10000);
}

function db_item($sql)
{
	if (strpos($sql, 'FROM erp_expense_categories') !== false) {
		return array('id' => 2, 'is_active' => 1);
	}
	if (strpos($sql, 'FROM erp_expenses') !== false) {
		// Another request paid (or cancelled) it after this one read it.
		$seen = ($GLOBALS['in_tx'] && (strpos($sql, 'FOR UPDATE') !== false)) ? $GLOBALS['status_now'] : 'unpaid';
		return erp_expense_save_lock_row($seen);
	}
	if (strpos($sql, 'FROM erp_cash_accounts') !== false) {
		return array('id' => 3, 'currency' => 'TRY', 'is_active' => 1);
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db_value($sql) { throw new Exception('unexpected db_value: ' . $sql); }
function db_items($sql) { throw new Exception('unexpected db_items: ' . $sql); }

class db { public static $con = null; }

require $pg_dir . '/includes/erp/money.php';
require $pg_dir . '/includes/erp/expenses.php';

$result = erp_expense_save(array(
	'expense_date' => '2026-11-02',
	'category_id' => 2,
	'supplier' => 'Landlord',
	'description' => 'Rent',
	'amount' => 12000,
	'includes_tax' => true,
	'tax_rate' => 20,
	'pay' => $pay,
	'cash_account_id' => 3,
	'payment_method' => 'cash',
	'paid_date' => '2026-11-02',
), 1, 7);

echo json_encode(array('result' => $result, 'writes' => $GLOBALS['writes'], 'rolled_back' => $GLOBALS['rolled_back']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$pay = ' . var_export((bool) $pay, true) . ";\n"
		. '$GLOBALS[\'status_now\'] = ' . var_export((string) $status_now, true) . ";\n"
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

// A change sent with "Paid now" after the expense was paid elsewhere takes
// no second payment out of the till.
function test_erp_expense_save_lock_paid_elsewhere_is_not_paid_again()
{
	$run = erp_expense_save_lock_test_run(true, 'paid');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'the change is refused');
	pg_assert_same(array(), $run['writes'], 'neither the expense nor a till movement is written');
	pg_assert_true($run['rolled_back'], 'the transaction is rolled back');
}

// A change of the figures after the expense was paid elsewhere does not
// rewrite what left the till.
function test_erp_expense_save_lock_figures_of_a_paid_one_stay()
{
	$run = erp_expense_save_lock_test_run(false, 'paid');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'the change is refused');
	pg_assert_same(array(), $run['writes'], 'the paid expense is not rewritten');
}

// An expense cancelled while the form was open is not changed or paid.
function test_erp_expense_save_lock_cancelled_elsewhere_is_not_changed()
{
	$run = erp_expense_save_lock_test_run(true, 'cancelled');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'the change is refused');
	pg_assert_same(array(), $run['writes'], 'nothing is written');
}

// Still unpaid under the lock: the change and the payment go through.
function test_erp_expense_save_lock_unchanged_status_saves_and_pays()
{
	$run = erp_expense_save_lock_test_run(true, 'unpaid');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_true($run['result']['success'], 'the change is saved');
	pg_assert_true(in_array('erp_cash_post', $run['writes'], true), 'the payment is written once');
}
