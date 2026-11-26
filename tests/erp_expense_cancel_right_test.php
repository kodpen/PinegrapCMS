<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the till right of a paid expense's cancel (includes/erp/
 * expenses.php, erp_expense_cancel()). The panel and the API judged the
 * right on the expense as they read it before the cancel's own check, so an
 * expense paid in that moment was cancelled, and its money put back into the
 * till, by a user without the right to the tills. The cancel now takes the
 * right and refuses a paid expense under its own row lock. Run in a PHP
 * process of its own with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Cancels paid expense 7 with or without the till right. Returns the
// answer and the writes attempted; null when PHP cannot be started again.
function erp_expense_cancel_right_test_run($may_refund)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgexpcancel');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);

$GLOBALS['writes'] = array();

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function escape($value) { return addslashes((string) $value); }
function waf_table_has_column($table, $column) { return true; }
function erp_lock_refusal($date) { return ''; }
function erp_event_expense($id, $event) { $GLOBALS['writes'][] = $event; }
function erp_db_error() { return ''; }
function erp_cash_refresh_balance($id) { return true; }
function erp_cash_post($movement) { $GLOBALS['writes'][] = 'erp_cash_post'; return 99; }
function erp_tx_begin() { return true; }
function erp_tx_commit() { return true; }
function erp_tx_rollback() { return true; }

function erp_query($sql)
{
	$GLOBALS['writes'][] = trim(substr(trim($sql), 0, 22));
	return true;
}

function db_item($sql)
{
	if (strpos($sql, 'FROM erp_cash_transactions') !== false) {
		return array('id' => 50, 'doc_date' => '2026-11-02', 'cash_account_id' => 3, 'direction' => 'out', 'amount' => 10000,
			'amount_base' => 10000, 'currency' => 'TRY', 'exchange_rate' => 1, 'exchange_rate_date' => '2026-11-02',
			'exchange_rate_source' => 'base', 'payment_method' => 'cash');
	}
	if (strpos($sql, 'FROM erp_expenses') !== false) {
		return array('id' => 7, 'status' => 'paid', 'expense_date' => '2026-11-02', 'cash_id' => 50, 'total_amount' => 10000);
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db_value($sql) { throw new Exception('unexpected db_value: ' . $sql); }
function db_items($sql) { throw new Exception('unexpected db_items: ' . $sql); }

class db { public static $con = null; }

require $pg_dir . '/includes/erp/expenses.php';

$result = erp_expense_cancel(7, 'Entered twice', 1, $may_refund);

echo json_encode(array('result' => $result, 'writes' => $GLOBALS['writes']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$may_refund = ' . var_export((bool) $may_refund, true) . ";\n"
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

function test_erp_expense_cancel_right_paid_needs_the_till_right()
{
	$run = erp_expense_cancel_right_test_run(false);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['success'], 'a paid expense is not cancelled without the till right');
	pg_assert_same('Cancelling a paid expense needs the right to the tills.', $run['result']['error']);
	pg_assert_same(array(), $run['writes'], 'no money goes back into the till');
}

function test_erp_expense_cancel_right_paid_with_the_right_is_cancelled()
{
	$run = erp_expense_cancel_right_test_run(true);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_true($run['result']['success'], 'cancelled with the right');
	pg_assert_true(in_array('erp_cash_post', $run['writes'], true), 'the money goes back into the till');
}

// The panel and the API hand the right to the cancel instead of judging it
// on a status read before it.
function test_erp_expense_cancel_right_callers_pass_it()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/api_resources.php');

	pg_assert_contains("erp_expense_cancel(\$id, (string) (\$_POST['reason'] ?? ''), (int) \$user['id'], \$can_cash)", $screen, 'the screen passes the till right');
	pg_assert_contains("erp_expense_cancel((int) \$row['id'], \$reason, \$owner['id'], erp_api_expense_may_refund())", $api, 'the API passes the till right');
}
