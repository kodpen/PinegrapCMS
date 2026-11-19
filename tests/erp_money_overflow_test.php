<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ERP amounts too large for an integer: a typed figure past
 * PHP_INT_MAX, an exchange rate the columns cannot store, and a document
 * total that would leave the integers once converted into the home currency.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/withholding.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');

// Runs $callback and returns the PHP warnings and notices it raised.
function pg_erp_overflow_warnings($callback)
{
	$raised = array();
	set_error_handler(function ($number, $message) use (&$raised) {
		$raised[] = $message;
		return true;
	});
	try {
		$callback();
	} finally {
		restore_error_handler();
	}
	return $raised;
}

// A figure of more digits than an int of kurus holds is read as the float it
// is, not cast down to PHP_INT_MAX first.
function test_erp_kurus_reads_a_figure_past_php_int_max_as_a_float()
{
	$kurus = null;
	$raised = pg_erp_overflow_warnings(function () use (&$kurus) {
		$kurus = erp_kurus('99999999999999999999');
	});

	pg_assert_same(array(), $raised);
	pg_assert_same(true, is_float($kurus));
	pg_assert_same(true, abs($kurus - 9999999999999999999900.0) < 1e7);
	pg_assert_same(true, erp_kurus('-99999999999999999999,50') < 0);
}

// Everyday and cap-sized figures stay whole kurus in an int.
function test_erp_kurus_keeps_normal_amounts()
{
	pg_assert_same(123456, erp_kurus('1.234,56'));
	pg_assert_same(-1250, erp_kurus('-12,50'));
	pg_assert_same(0, erp_kurus('0'));
	pg_assert_same(1000000000000000, erp_kurus('10.000.000.000.000,00'));
	pg_assert_same(999999999999999999, erp_kurus('9999999999999999,99'));
	pg_assert_same(1234, erp_kurus('00012,34'));
}

// A typed price past PHP_INT_MAX is refused by the line cap with no warning.
function test_erp_manual_line_refuses_a_typed_price_past_php_int_max()
{
	$built = null;
	$raised = pg_erp_overflow_warnings(function () use (&$built) {
		$line = array('description' => 'pg-test', 'quantity' => 1, 'unit_price' => erp_kurus('99999999999999999999'), 'tax_rate' => 20);
		$built = erp_manual_lines_build(array($line));
	});

	pg_assert_same(array(), $raised);
	pg_assert_same('lines[0][unit_price]', $built['field']);
}

// A rate the DECIMAL(15,6) columns cannot store is refused; real rates pass.
function test_erp_exchange_rate_refusal_bounds_the_rate()
{
	pg_assert_same('', erp_exchange_rate_refusal(0));
	pg_assert_same('', erp_exchange_rate_refusal(1));
	pg_assert_same('', erp_exchange_rate_refusal(38.4521));
	pg_assert_same('', erp_exchange_rate_refusal(16250.5));
	pg_assert_same('', erp_exchange_rate_refusal(ERP_MAX_EXCHANGE_RATE));
	pg_assert_same(true, erp_exchange_rate_refusal(-1) !== '');
	pg_assert_same(true, erp_exchange_rate_refusal(1e10) !== '');
	pg_assert_same(true, erp_exchange_rate_refusal((float) '1e999') !== '');
	pg_assert_same(true, erp_exchange_rate_refusal(NAN) !== '');
}

// The largest total the lines allow (200 lines at the line cap with both
// taxes at 100%) converts at an everyday rate; past the bound it is refused.
function test_erp_base_amount_fits_bounds_the_conversion()
{
	$largest_document = ERP_MANUAL_MAX_LINES * ERP_MAX_LINE_AMOUNT * 3;

	pg_assert_same(true, erp_base_amount_fits(123456, 38.4521));
	pg_assert_same(true, erp_base_amount_fits($largest_document, 1.5));
	pg_assert_same(true, erp_base_amount_fits(-ERP_MAX_BASE_AMOUNT, 1));
	pg_assert_same(false, erp_base_amount_fits($largest_document, 40));
	pg_assert_same(false, erp_base_amount_fits(ERP_MAX_LINE_AMOUNT, ERP_MAX_EXCHANGE_RATE));
	pg_assert_same(true, ERP_MAX_BASE_AMOUNT < PHP_INT_MAX);

	// Within the bound the conversion is a true integer, with no warning.
	$base = null;
	$raised = pg_erp_overflow_warnings(function () use (&$base) {
		$base = erp_to_base(ERP_MAX_BASE_AMOUNT / 2, 2);
	});
	pg_assert_same(array(), $raised);
	pg_assert_same(true, is_int($base));
}

// Normal conversions give the same kurus as before.
function test_erp_to_base_keeps_normal_amounts()
{
	pg_assert_same(3845210, erp_to_base(100000, 38.4521));
	pg_assert_same(-150, erp_to_base(-100, 1.5));
	pg_assert_same(0, erp_to_base(0, 38.4521));
}

// Every place a foreign-currency document is converted checks the bound
// first: the draft save, the issue and the quote save, and the header takes
// its rate through erp_exchange_rate_refusal().
function test_erp_foreign_documents_check_the_base_bound_before_converting()
{
	$manual = file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
	$quotes = file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');

	pg_assert_same(true, strpos($manual, "erp_exchange_rate_refusal(\$exchange_rate)) !== '') {\n        return \$fail(\$refusal, 'exchange_rate');") !== false);

	foreach (array('manual' => $manual, 'quotes' => $quotes) as $name => $source) {
		$offset = 0;
		$converts = 0;
		while (($at = strpos($source, 'erp_to_base($totals', $offset)) !== false) {
			$before = substr($source, max(0, $at - 900), 900);
			pg_assert_same(true, strpos($before, 'erp_base_amount_fits($totals[\'grand_total\']') !== false, $name . ' converts at ' . $at . ' without the bound');
			$offset = $at + 1;
			$converts++;
		}
		pg_assert_same($name === 'manual' ? 2 : 1, $converts);
	}
}
