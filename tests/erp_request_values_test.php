<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP screens' filter, date and search values in the address.
 * (string) turns an array into "Array" and writes "Array to string
 * conversion" into the log, so a screen opened with ?status[]=x or
 * ?from[]=x warned on every request. A value is read only when it is one
 * value; an array counts as not given.
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
