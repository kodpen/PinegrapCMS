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
 * pack. And for what a form posts: a screen that saves hands every one-value
 * field to pg_request_single_values() before it reads the post, so title[]=x
 * is not saved as "Array" and id[]=2 does not delete till 1.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');

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

// ERP screens whose form posts have not been gone through yet. Each is
// taken off once its fields are handed to pg_request_single_values().
function erp_request_values_post_pending()
{
	return array(
		'add_erp_invoice', 'add_erp_late_fee_invoice', 'add_erp_return', 'edit_erp_expense', 'edit_erp_invoice', 'edit_erp_purchase_order',
		'edit_erp_quote', 'edit_erp_waybill', 'erp_account_prices', 'erp_account_sync', 'erp_accountant', 'erp_accounts', 'erp_accounts_import',
		'erp_bank_statement', 'erp_bank_statements', 'erp_cheque', 'erp_expense_categories', 'erp_export', 'erp_inbox', 'erp_inbox_document',
		'erp_invoices', 'erp_order_flow', 'erp_receipt', 'erp_reconciliation', 'erp_settings', 'erp_stock_count', 'get_erp_invoice_pdf',
		'get_erp_reconciliation_pdf', 'get_erp_waybill_pdf',
	);
}

// The pg_request_single_values() calls of a source: each call's offset and
// the names it hands over, with a form's own list (erp_*_single_fields())
// read from the function.
function erp_request_values_single_value_calls($source)
{
	$calls = array();
	$offset = 0;

	while (($start = strpos($source, 'pg_request_single_values(', $offset)) !== false) {
		$depth = 0;
		$end = $start + strlen('pg_request_single_values');

		for ($i = $end; $i < strlen($source); $i++) {
			if ($source[$i] === '(') {
				$depth++;
			} elseif ($source[$i] === ')') {
				$depth--;
				if ($depth === 0) {
					break;
				}
			}
		}

		$text = substr($source, $start, $i - $start + 1);
		preg_match_all("/'(\\w+)'/", $text, $names);
		$list = $names[1];

		preg_match_all('/(erp_\w+_single_fields)\(\)/', $text, $functions);
		foreach ($functions[1] as $function) {
			$list = array_merge($list, $function());
		}

		$calls[] = array('offset' => $start, 'names' => $list);
		$offset = $i;
	}

	return $calls;
}

// A screen that saves a form hands every one-value field it reads to
// pg_request_single_values() before reading the post, the liveform's copy
// (add_fields_to_session()) included. Lines, choices and other lists that a
// form posts as an array on purpose are not handed over.
function test_erp_request_values_post_fields_dropped_before_first_read()
{
	$lists = array('token', 'lines', 'choice');
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/erp_*.php'), glob(PG_FUNCTIONS_DIR . '/get_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/add_erp_*.php'), glob(PG_FUNCTIONS_DIR . '/edit_erp_*.php'));
	$checked = 0;

	foreach ($files as $file) {
		$screen = basename($file, '.php');
		$source = file_get_contents($file);

		if (in_array($screen, erp_request_values_post_pending(), true)) {
			continue;
		}

		$liveform = (strpos($source, 'add_fields_to_session()') !== false);
		preg_match_all("/\\\$_POST\\['(\\w+)'\\]/", $source, $posted, PREG_OFFSET_CAPTURE);
		preg_match_all("/get_field_value\\('(\\w+)'\\)/", $source, $read);

		if (!$liveform && empty($posted[1])) {
			continue;
		}

		$checked++;
		$calls = erp_request_values_single_value_calls($source);

		if (empty($calls)) {
			pg_assert_true(false, $screen . ': no pg_request_single_values() call');
			continue;
		}

		$names = array();
		foreach ($calls as $call) {
			$names = array_merge($names, $call['names']);
		}

		$first_call = $calls[0]['offset'];

		foreach ($posted[1] as $match) {
			if (in_array($match[0], $lists, true)) {
				continue;
			}

			pg_assert_true(in_array($match[0], $names, true), $screen . ': $_POST[\'' . $match[0] . '\'] not handed over');
			pg_assert_true($match[1] > $first_call, $screen . ': $_POST[\'' . $match[0] . '\'] read before the call');
		}

		if ($liveform) {
			pg_assert_true(strpos($source, 'add_fields_to_session()') > $first_call, $screen . ': add_fields_to_session() before the call');

			foreach (array_unique($read[1]) as $field) {
				if (!in_array($field, $lists, true)) {
					pg_assert_true(in_array($field, $names, true), $screen . ': ' . $field . ' read from the form but not handed over');
				}
			}
		}

		// A shared form is read by its own reader; the screen hands over the
		// form's list, which the next test holds to the reader.
		if (strpos($source, 'erp_invoice_form_read(') !== false) {
			pg_assert_true(empty(array_diff(erp_invoice_form_single_fields(), $names)), $screen . ': invoice form fields not handed over');
		}

		if (strpos($source, 'erp_account_form_check_formats(') !== false) {
			pg_assert_true(empty(array_diff(erp_account_form_single_fields(), $names)), $screen . ': account form fields not handed over');
		}
	}

	pg_assert_true($checked >= 15, 'screens checked: ' . $checked);

	// The quick account dialog hands $_POST to erp_account_quick_create()
	// whole; every field that reads is handed over first.
	$quick = file_get_contents(PG_FUNCTIONS_DIR . '/erp_account_quick.php');
	$calls = erp_request_values_single_value_calls($quick);
	preg_match_all("/\\\$data\\['(\\w+)'\\]/", file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/account_quick.php'), $data);
	pg_assert_true(!empty($calls) && ($calls[0]['offset'] < strpos($quick, 'erp_account_quick_create($user, $_POST)')), 'quick account: call before the create');
	pg_assert_same(array(), empty($calls) ? array('no call') : array_values(array_diff(array_unique($data[1]), $calls[0]['names'])), 'quick account fields not handed over');
}

// The lists the shared forms give the screens name every field their own
// code reads: a field added to a form and left off its list would be read
// as posted again.
function test_erp_request_values_shared_form_lists_cover_the_forms()
{
	$forms = array(
		'invoice_form' => array(erp_invoice_form_single_fields(), array('lines')),
		// The edit screen's own record number; it hands it over itself.
		'account_form' => array(erp_account_form_single_fields(), array('id')),
	);

	foreach ($forms as $form => $list) {
		preg_match_all("/get_field_value\\('(\\w+)'\\)/", file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/' . $form . '.php'), $read);
		pg_assert_same(array(), array_values(array_diff(array_unique($read[1]), $list[0], $list[1])), $form . ' fields missing from its list');
	}
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
