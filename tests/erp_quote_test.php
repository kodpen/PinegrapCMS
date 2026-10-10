<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for erp_quote_state() (includes/erp/quotes.php): what a quote reads
 * as on the screen, from its stored status, its date and its invoice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');

// An open quote still running stays open.
function test_erp_quote_state_open_running()
{
	pg_assert_same('open', erp_quote_state(array('status' => 'open', 'valid_until' => date('Y-m-d'), 'invoice_status' => '')));
}

// An open quote past its date reads as expired.
function test_erp_quote_state_open_past_its_date_is_expired()
{
	pg_assert_same('expired', erp_quote_state(array('status' => 'open', 'valid_until' => date('Y-m-d', strtotime('-1 day')), 'invoice_status' => '')));
}

// An open quote without a date never expires.
function test_erp_quote_state_open_without_date_stays_open()
{
	pg_assert_same('open', erp_quote_state(array('status' => 'open', 'valid_until' => '0000-00-00', 'invoice_status' => '')));
}

// An invoiced quote with its draft or invoice in place stays invoiced.
function test_erp_quote_state_invoiced_with_invoice()
{
	pg_assert_same('invoiced', erp_quote_state(array('status' => 'invoiced', 'valid_until' => '2000-01-01', 'invoice_status' => 'draft')));
	pg_assert_same('invoiced', erp_quote_state(array('status' => 'invoiced', 'valid_until' => '2000-01-01', 'invoice_status' => 'issued')));
	pg_assert_same('invoiced', erp_quote_state(array('status' => 'invoiced', 'valid_until' => '2000-01-01', 'invoice_status' => 'paid')));
}

// The draft was deleted: the quote is accepted again.
function test_erp_quote_state_invoiced_draft_deleted_is_accepted()
{
	pg_assert_same('accepted', erp_quote_state(array('status' => 'invoiced', 'valid_until' => '2000-01-01', 'invoice_status' => '')));
	pg_assert_same('accepted', erp_quote_state(array('status' => 'invoiced', 'valid_until' => '2000-01-01')));
}

// The invoice was cancelled: the quote is accepted again and can be invoiced once more.
function test_erp_quote_state_invoice_cancelled_is_accepted()
{
	pg_assert_same('accepted', erp_quote_state(array('status' => 'invoiced', 'valid_until' => '2000-01-01', 'invoice_status' => 'cancelled')));
}

// A decided quote keeps its decision whatever its date.
function test_erp_quote_state_decided_keeps_status()
{
	foreach (array('accepted', 'rejected', 'cancelled') as $status) {
		pg_assert_same($status, erp_quote_state(array('status' => $status, 'valid_until' => '2000-01-01', 'invoice_status' => '')));
	}
}
