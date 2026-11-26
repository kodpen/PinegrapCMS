<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two orders put on one customer account at the same moment
 * (includes/erp/storefront.php, erp_storefront_on_account_hold()). Checkout
 * weighed the order against figures read once, without a lock, long before
 * the order was written, so two checkouts that each fitted the credit limit
 * alone both went on the account and together passed it. The account is now
 * held under a named lock and its figures read again after the lock. Run in
 * a PHP process of its own with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Weighs an order of $total on account 7 (limit 10000, owes 0) while another
// checkout has just put 8000 on it. Reads made before this request holds the
// lock see nothing of it; reads made after see the other order pending.
// $lock_answer is what GET_LOCK answers. Returns the answer and the order of
// the lock and the reads, or null when PHP cannot be started again.
function erp_on_account_hold_test_run($total, $lock_answer = 1)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgonaccount');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);
define('DB_DATABASE', 'shop');
define('DB_HOST', 'localhost');

$GLOBALS['locked'] = false;
$GLOBALS['steps'] = array();

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function e($value) { return addslashes((string) $value); }
function escape($value) { return addslashes((string) $value); }
function erp_money_out($kurus) { return (string) $kurus; }
function waf_table_has_column($table, $column) { return true; }

function db_value($sql)
{
	if (strpos($sql, 'GET_LOCK') !== false) {
		$GLOBALS['steps'][] = 'lock';
		$GLOBALS['locked'] = ($GLOBALS['lock_answer'] === 1);
		return $GLOBALS['lock_answer'];
	}
	if (strpos($sql, 'RELEASE_LOCK') !== false) {
		$GLOBALS['steps'][] = 'release';
		return 1;
	}
	if (strpos($sql, 'FROM orders') !== false) {
		$GLOBALS['steps'][] = 'pending';
		return $GLOBALS['locked'] ? 8000 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (strpos($sql, 'FROM erp_accounts') !== false) {
		$GLOBALS['steps'][] = 'account';
		return array('credit_limit' => 10000, 'balance' => 0);
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db_items($sql) { throw new Exception('unexpected db_items: ' . $sql); }

require $pg_dir . '/includes/erp/credit.php';
require $pg_dir . '/includes/erp/storefront.php';

$result = erp_storefront_on_account_hold(7, $GLOBALS['total']);

echo json_encode(array('result' => $result, 'steps' => $GLOBALS['steps']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'total\'] = ' . var_export((int) $total, true) . ";\n"
		. '$GLOBALS[\'lock_answer\'] = ' . var_export((int) $lock_answer, true) . ";\n"
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

// 3000 fitted the limit before the other order; with its 8000 pending only
// 2000 is left, so the second order is refused.
function test_erp_on_account_hold_sees_the_order_in_flight()
{
	$run = erp_on_account_hold_test_run(3000);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['allowed'], 'the second order is refused');
	pg_assert_same(2000, $run['result']['available']);
	pg_assert_false($run['result']['busy']);
}

// The lock comes first, the figures after it, and the lock is kept for the
// order to be written under it.
function test_erp_on_account_hold_locks_before_reading()
{
	$run = erp_on_account_hold_test_run(2000);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_true($run['result']['allowed'], 'reaching the limit exactly is allowed');
	pg_assert_same('lock', $run['steps'][0] ?? '');
	pg_assert_false(in_array('release', $run['steps'], true), 'the lock is kept while the order is written');
}

// A lock not got in time refuses the order and reads nothing.
function test_erp_on_account_hold_busy()
{
	$run = erp_on_account_hold_test_run(100, 0);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['allowed']);
	pg_assert_true($run['result']['busy']);
	pg_assert_same(array('lock'), $run['steps']);
}
