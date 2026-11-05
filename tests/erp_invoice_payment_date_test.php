<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the invoice's payment date as erp_invoice_refresh_paid()
 * (includes/erp/settlement.php) keeps it (erp_invoice_payment_date_next()):
 * the closing receipt fills it, and an invoice that is no longer paid loses a
 * date that a receipt gave it, so the next closing receipt fills it again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');

// The closing receipt dates an invoice that states no date yet.
function test_erp_invoice_payment_date_filled_when_paid()
{
	pg_assert_same('2026-11-10', erp_invoice_payment_date_next('paid', 100000, '0000-00-00', 0, '2026-11-10'));
	pg_assert_same('2026-11-10', erp_invoice_payment_date_next('paid', 100000, '', 0, '2026-11-10'));
	pg_assert_same('', erp_invoice_payment_date_next('paid', 100000, '2026-11-01', 0, '2026-11-10'));
	pg_assert_same('', erp_invoice_payment_date_next('paid', 100000, '0000-00-00', 0, ''));
}

// The closing receipt was cancelled and another one is left: the invoice is
// partially paid and no longer says when it was paid.
function test_erp_invoice_payment_date_cleared_when_partially_paid()
{
	pg_assert_same('0000-00-00', erp_invoice_payment_date_next('partially_paid', 40000, '2026-11-10', 0, ''));
	pg_assert_same('0000-00-00', erp_invoice_payment_date_next('issued', 0, '2026-11-10', 0, ''));
	pg_assert_same('', erp_invoice_payment_date_next('partially_paid', 40000, '0000-00-00', 0, ''));
}

// Cancel the closing receipt, then close the invoice with a new one: the new
// receipt's date is the payment date, not the cancelled one's.
function test_erp_invoice_payment_date_follows_the_new_closing_receipt()
{
	$stored = '2026-11-10';
	$next = erp_invoice_payment_date_next('partially_paid', 40000, $stored, 0, '');
	if ($next !== '') {
		$stored = $next;
	}
	$next = erp_invoice_payment_date_next('paid', 100000, $stored, 0, '2026-11-20');
	if ($next !== '') {
		$stored = $next;
	}
	pg_assert_same('2026-11-20', $stored);
}

// An internet sale keeps the gateway's date whatever its receipts do.
function test_erp_invoice_payment_date_internet_sale_kept()
{
	pg_assert_same('', erp_invoice_payment_date_next('partially_paid', 40000, '2026-11-10', 1, ''));
	pg_assert_same('', erp_invoice_payment_date_next('issued', 0, '2026-11-10', 1, ''));
}

// A cancelled or draft invoice keeps its status, so only an invoice left with
// no money at all loses its date, as before.
function test_erp_invoice_payment_date_cancelled_and_draft()
{
	pg_assert_same('', erp_invoice_payment_date_next('cancelled', 40000, '2026-11-10', 0, ''));
	pg_assert_same('0000-00-00', erp_invoice_payment_date_next('cancelled', 0, '2026-11-10', 0, ''));
	pg_assert_same('0000-00-00', erp_invoice_payment_date_next('draft', 0, '2026-11-10', 0, ''));
}
