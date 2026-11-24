<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the late payment fee of one invoice: erp_late_fee_for_invoice()
 * (includes/erp/late_fee.php), which charges a monthly rate on what was owed
 * past the due date, day by day, with payments and returns taking their part
 * off on the day they came.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/late_fee.php');

// An invoice still open: the whole amount, every day from the due date.
function test_erp_late_fee_open_invoice()
{
	// 1,000.00 due 31 January, 30 days to 2 March, 3% a month.
	$fee = erp_late_fee_for_invoice(100000, '2026-01-31', array(), '', '2026-03-02', 3);

	pg_assert_same(array('fee' => 3000, 'days' => 30, 'average' => 100000), $fee);
}

// A part payment lowers what the days after it are charged on.
function test_erp_late_fee_part_payment()
{
	// 10 days on 1,000.00, then 20 days on 600.00.
	$fee = erp_late_fee_for_invoice(100000, '2026-01-01', array(
		array('date' => '2026-01-11', 'amount' => 40000),
	), '', '2026-01-31', 3);

	pg_assert_same(2200, $fee['fee'], 'fee');
	pg_assert_same(30, $fee['days'], 'days');
	pg_assert_same(73333, $fee['average'], 'average owed');
}

// An invoice paid late earns the days it was late, and nothing after.
function test_erp_late_fee_paid_late()
{
	$fee = erp_late_fee_for_invoice(100000, '2026-01-01', array(
		array('date' => '2026-01-16', 'amount' => 100000),
	), '', '2026-03-01', 2);

	pg_assert_same(array('fee' => 1000, 'days' => 15, 'average' => 100000), $fee);
}

// Paid on or before the due date: no fee.
function test_erp_late_fee_paid_on_time()
{
	$none = array('fee' => 0, 'days' => 0, 'average' => 0);

	pg_assert_same($none, erp_late_fee_for_invoice(100000, '2026-01-01', array(
		array('date' => '2025-12-20', 'amount' => 100000),
	), '', '2026-02-01', 3), 'before');

	pg_assert_same($none, erp_late_fee_for_invoice(100000, '2026-01-01', array(
		array('date' => '2026-01-01', 'amount' => 100000),
	), '', '2026-02-01', 3), 'on the day');
}

// A return counts like a payment, and the order they come in does not matter.
function test_erp_late_fee_returns_and_order()
{
	$fee = erp_late_fee_for_invoice(100000, '2026-01-01', array(
		array('date' => '2026-01-21', 'amount' => 50000),
		array('date' => '2026-01-11', 'amount' => 50000),
	), '', '2026-02-28', 3);

	// 10 days on 1,000.00, 10 on 500.00: 15,000,000 kurus-days.
	pg_assert_same(1500, $fee['fee'], 'fee');
	pg_assert_same(20, $fee['days'], 'days');
}

// The days already charged by an issued fee invoice are not charged again.
function test_erp_late_fee_starts_after_the_last_charged_day()
{
	$fee = erp_late_fee_for_invoice(90000, '2026-01-01', array(), '2026-01-21', '2026-01-31', 3);
	pg_assert_same(array('fee' => 900, 'days' => 10, 'average' => 90000), $fee, 'after the charged day');

	$fee = erp_late_fee_for_invoice(90000, '2026-01-21', array(), '2026-01-01', '2026-01-31', 3);
	pg_assert_same(10, $fee['days'], 'a charged day before the due date changes nothing');

	$fee = erp_late_fee_for_invoice(90000, '2026-01-01', array(
		array('date' => '2026-01-15', 'amount' => 90000),
	), '2026-01-21', '2026-01-31', 3);
	pg_assert_same(0, $fee['fee'], 'paid before the charged day: nothing left');
}

// A payment after the as-of date is not known yet.
function test_erp_late_fee_ignores_later_payments()
{
	$fee = erp_late_fee_for_invoice(100000, '2026-01-01', array(
		array('date' => '2026-02-15', 'amount' => 100000),
	), '', '2026-01-31', 3);

	pg_assert_same(array('fee' => 3000, 'days' => 30, 'average' => 100000), $fee);
}

// No rate, or an as-of date not past the due date: no fee.
function test_erp_late_fee_no_rate_or_not_due()
{
	$none = array('fee' => 0, 'days' => 0, 'average' => 0);

	pg_assert_same($none, erp_late_fee_for_invoice(100000, '2026-01-01', array(), '', '2026-03-01', 0), 'rate 0');
	pg_assert_same($none, erp_late_fee_for_invoice(100000, '2026-01-01', array(), '', '2026-01-01', 3), 'due today');
	pg_assert_same($none, erp_late_fee_for_invoice(100000, '2026-02-01', array(), '', '2026-01-15', 3), 'not due yet');
}

// A part of a percent and a rounding half way.
function test_erp_late_fee_fractional_rate()
{
	// 1,000.00 for 1 day at 1.5% a month: 100000 * 1.5 / 100 / 30 = 50.
	$fee = erp_late_fee_for_invoice(100000, '2026-01-01', array(), '', '2026-01-02', 1.5);
	pg_assert_same(50, $fee['fee'], '1.5%');

	// 1.00 at 3%: 0.4 kurus over 4 days rounds down, 0.5 over 5 days up.
	pg_assert_same(0, erp_late_fee_for_invoice(100, '2026-01-01', array(), '', '2026-01-05', 3)['fee'], 'four days');
	pg_assert_same(1, erp_late_fee_for_invoice(100, '2026-01-01', array(), '', '2026-01-06', 3)['fee'], 'five days');
}

// The period a fee starts from by default: the first day of the as-of year.
// The fee is the exact product rounded half away from zero on every PHP
// version: a float round($kurus_days * $rate / 100 / 30) gave 42.43 for
// 1035.00 owed thirty days at 4.1% on PHP 8.4 and later, where round() no
// longer pre-rounds 4243.4999...
function test_erp_late_fee_exact_on_every_php_version()
{
	pg_assert_same(4244, erp_late_fee_for_invoice(103500, '2026-01-01', array(), '', '2026-01-31', 4.1)['fee'], '4.1% of 1035.00 a month');
	pg_assert_same(8306, erp_late_fee_for_invoice(367500, '2026-01-01', array(), '', '2026-01-31', 2.26)['fee'], '2.26% of 3675.00 a month');

	for ($i = 0; $i < 3000; $i++) {
		$owed = mt_rand(1, 2000000);
		$days = mt_rand(1, 90);
		$hundredths = mt_rand(1, 500);
		$product = $owed * $days * $hundredths * 10;
		$expected = intdiv($product, 3000000) + ((($product % 3000000) * 2 >= 3000000) ? 1 : 0);
		$as_of = date('Y-m-d', strtotime('2026-01-01 +' . $days . ' days'));

		pg_assert_same($expected, erp_late_fee_for_invoice($owed, '2026-01-01', array(), '', $as_of, $hundredths / 100)['fee'], $owed . ' for ' . $days . ' days at ' . ($hundredths / 100) . '%');
	}

	$function = new ReflectionFunction('erp_late_fee_for_invoice');
	$lines = array_slice(file($function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1);
	pg_assert_true(strpos(implode('', $lines), 'erp_multiply_units(') !== false, 'the fee goes through erp_multiply_units()');
}

function test_erp_late_fee_default_from()
{
	pg_assert_same('2026-01-01', erp_late_fee_default_from('2026-10-13'));
	pg_assert_same('2025-01-01', erp_late_fee_default_from('2025-12-31'));
}
