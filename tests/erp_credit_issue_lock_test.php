<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two sales invoices of one account issued at the same moment
 * while the store blocks sales past the credit limit
 * (includes/erp/credit.php, erp_credit_refusal_locked(); erp_invoice_issue()
 * in includes/erp/invoice_manual.php). The account was read without a lock,
 * so two invoices that each fitted the limit alone were both issued and
 * together passed it. The account row is now read with a locking read once
 * the series is locked, so the second issue sees the first one's balance.
 * Run in a PHP process of its own with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Weighs an invoice of $adding on account 7 (limit 10000) in credit limit
// mode $mode while another invoice of 8000 has just been issued to it: a
// plain read sees the balance before it (0), a locking read sees it (8000).
// Returns the refusal and the reads of the account, or null when PHP cannot
// be started again.
function erp_credit_issue_lock_test_run($adding, $mode)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcreditlock');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('PG_ERP_ENTRY', true);

$GLOBALS['reads'] = array();

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function e($value) { return addslashes((string) $value); }
function erp_money_out($kurus) { return (string) $kurus; }
function waf_table_has_column($table, $column) { return true; }

function db_value($sql)
{
	if (strpos($sql, 'erp_credit_limit_mode') !== false) {
		return $GLOBALS['mode'];
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (strpos($sql, 'FROM erp_accounts') !== false) {
		$locked = (strpos($sql, 'FOR UPDATE') !== false);
		$GLOBALS['reads'][] = $locked ? 'locked' : 'plain';
		return array('id' => 7, 'credit_limit' => 10000, 'balance' => $locked ? 8000 : 0);
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

require $pg_dir . '/includes/erp/credit.php';

$refusal = erp_credit_refusal_locked(7, $GLOBALS['adding']);

echo json_encode(array('refusal' => $refusal, 'reads' => $GLOBALS['reads']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'adding\'] = ' . var_export((int) $adding, true) . ";\n"
		. '$GLOBALS[\'mode\'] = ' . var_export((string) $mode, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !array_key_exists('refusal', $result)) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// 3000 fitted the limit before the other invoice; with its 8000 on the
// account the second invoice is refused.
function test_erp_credit_issue_lock_sees_the_other_invoice()
{
	$run = erp_credit_issue_lock_test_run(3000, 'block');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_contains('credit limit', (string) $run['refusal']);
	pg_assert_same(array('locked'), $run['reads']);
}

// What still fits is issued.
function test_erp_credit_issue_lock_fits()
{
	$run = erp_credit_issue_lock_test_run(2000, 'block');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('', $run['refusal']);
}

// In warn mode nothing is refused and the account row is not locked.
function test_erp_credit_issue_lock_warn_mode()
{
	$run = erp_credit_issue_lock_test_run(3000, 'warn');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('', $run['refusal']);
	pg_assert_same(array(), $run['reads']);
}

// erp_invoice_issue() weighs the limit with the locking read, after the
// series is locked: the order an order's invoice takes the two locks in
// (erp_next_number(), then the balance), so the two never wait on each other.
function test_erp_credit_issue_lock_order_in_issue()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
	$start = strpos($source, 'function erp_invoice_issue(');
	$end = strpos($source, "\nfunction ", $start + 10);
	$body = substr($source, $start, $end - $start);

	$numbered = strpos($body, 'erp_next_number(');
	$weighed = strpos($body, 'erp_credit_refusal_locked(');

	pg_assert_true($weighed !== false, 'the limit is weighed with the locking read');
	pg_assert_true(($numbered !== false) && ($weighed !== false) && ($weighed > $numbered), 'after the series is locked');
	pg_assert_false(strpos($body, 'erp_credit_refusal($account') !== false, 'not against the account read without a lock');
}
