<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP screens' filter, date and search values in the address.
 * (string) turns an array into "Array" and writes "Array to string
 * conversion" into the log, so a screen opened with ?status[]=x or
 * ?from[]=x warned on every request. A value is read only when it is one
 * value; an array counts as not given. The same goes for the numbers that
 * pick a record: (int) turns an array into 1, so ?account_id[]=x opened the
 * first account's invoices, prices or cards without a word, ?id[]=x the
 * first account, till or document, and ?download[]=x the first accountant's
 * pack.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/aging.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cashflow.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/reconciliation.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');

// No ERP screen casts a value from the address without checking it is one
// value.
function test_erp_request_values_no_bare_string_cast()
{
	$found = array();
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/erp_*.php'), glob(PG_FUNCTIONS_DIR . '/get_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/add_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/edit_erp_*.php'));

	foreach ($files as $file) {
		$source = file_get_contents($file);

		if (preg_match('/\(string\)\s*\(\s*\$_(GET|REQUEST)\[/', $source)
			|| preg_match('/isset\(\$_(GET|REQUEST)\[([^\]]+)\]\)\s*\?\s*\(string\)\s*\$_\1\[\2\]/', $source)) {
			$found[] = basename($file);
		}
	}

	pg_assert_same(array(), $found, 'screens casting $_GET / $_REQUEST with (string)');
}

// The record a screen opens, is filtered to, filled in from or copied, and
// every other number in the address (a page cursor, a line to open, the days
// to reply), comes as one number; one sent as an array is not given rather
// than 1.
function test_erp_request_values_no_bare_int_cast_of_record_params()
{
	$found = array();
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/erp_*.php'), glob(PG_FUNCTIONS_DIR . '/get_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/add_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/edit_erp_*.php'));

	foreach ($files as $file) {
		// The checked read, is_scalar($_GET['x'] ?? null) ? (int) $_GET['x'],
		// is taken out first; any cast left over is a bare one.
		$source = preg_replace('/is_scalar\(\$_(GET|REQUEST)\[\'(\w+)\'\] \?\? null\)\)? \? \(int\) \$_\1\[\'\2\'\]/', '', file_get_contents($file));

		if (preg_match_all('/\((?:int|float)\)\s*\(?\s*\$_(GET|REQUEST)\[\'(\w+)\'\]/', $source, $matches)) {
			foreach ($matches[2] as $name) {
				$found[] = basename($file) . ' ' . $name;
			}
		}
	}

	pg_assert_same(array(), $found, 'screens casting a number from $_GET / $_REQUEST with a bare (int)');
}

// A list taken from the address (the products picked for a purchase order,
// the quantities refunded on a return) is read entry by entry; an entry that
// is itself an array (?qty[5][]=x) is not cast to 1.
function test_erp_request_values_no_bare_cast_of_list_entries()
{
	$found = array();
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/erp_*.php'), glob(PG_FUNCTIONS_DIR . '/get_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/add_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/edit_erp_*.php'));

	foreach ($files as $file) {
		$source = file_get_contents($file);

		preg_match_all('/(\$\w+) = \(isset\(\$_(GET|REQUEST)\[\'\w+\'\]\) && is_array\(\$_\2\[/', $source, $matches);

		foreach ($matches[1] as $list) {
			if (preg_match('/\((?:int|float)\)\s*\(?\s*' . preg_quote($list, '/') . '\[/', $source)) {
				$found[] = basename($file) . ' ' . $list;
			}
		}
	}

	pg_assert_same(array(), $found, 'screens casting an entry of a list from $_GET / $_REQUEST with a bare (int) or (float)');
}

// The days the counterparty is given to object: an array is not given, so
// the letter keeps the usual 7 days instead of one.
function test_erp_request_values_reply_days_ignores_arrays()
{
	$options = erp_reconciliation_options(array('reply_days' => array('30')));
	pg_assert_same(7, $options['reply_days'], 'reconciliation reply days, array');

	$options = erp_reconciliation_options(array('reply_days' => '30'));
	pg_assert_same(30, $options['reply_days'], 'reconciliation reply days, one value');
}

// The products picked on the minimum stock screen: an entry that is itself an
// array is no product, rather than product 1.
function test_erp_request_values_purchase_order_products_skip_arrays()
{
	pg_assert_same(array(), erp_purchase_order_rows_for_products(array(array('7'), array())), 'purchase order rows from array entries');
}

// The till the cash flow screen narrows to: an array is no till.
function test_erp_request_values_cashflow_till_ignores_arrays()
{
	$options = erp_cashflow_options(array('till' => array('1')));
	pg_assert_same(0, $options['till'], 'cash flow till');
}

// The dates the aging, cash flow and reconciliation screens hand in from the
// address: an array is no date and falls back to the default.
function test_erp_request_values_dates_ignore_arrays()
{
	$today = date('Y-m-d');

	pg_assert_same($today, erp_aging_as_of(array('2026-01-01')), 'aging as of');
	pg_assert_same('2026-01-01', erp_aging_as_of('2026-01-01'), 'aging as of, one value');

	$options = erp_cashflow_options(array('from' => array('x'), 'to' => array('x'), 'group' => array('day')));
	pg_assert_same($today, $options['to'], 'cash flow to');
	pg_assert_same(substr($today, 0, 7) . '-01', $options['from'], 'cash flow from');
	pg_assert_false($options['group_chosen'], 'cash flow group');

	$options = erp_cashflow_options(array('from' => '2026-01-01', 'to' => '2026-01-31', 'group' => 'week'));
	pg_assert_same(array('2026-01-01', '2026-01-31', 'week'), array($options['from'], $options['to'], $options['group']), 'cash flow, one value each');

	$options = erp_reconciliation_options(array('as_of' => array('x'), 'from' => array('x')));
	pg_assert_same($today, $options['as_of'], 'reconciliation as of');
	pg_assert_same(substr($today, 0, 4) . '-01-01', $options['from'], 'reconciliation from');
}
