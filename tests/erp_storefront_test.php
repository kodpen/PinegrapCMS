<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the storefront shows a signed-in customer of their own ERP
 * account (includes/erp/storefront.php): how a document reads in the
 * "My Invoices" widget, from what the aging module says is still owed on it
 * and how late it is.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/storefront.php');

// A return is a return whatever is owed on it.
function test_erp_storefront_document_state_return()
{
	pg_assert_same('return', erp_storefront_document_state('return', 0, 0, 0));
	pg_assert_same('return', erp_storefront_document_state('return', 5000, 12, 0));
}

// Nothing owed is paid, late or not, and whether or not anything was
// collected on it (an invoice closed by a return collected nothing).
function test_erp_storefront_document_state_paid()
{
	pg_assert_same('paid', erp_storefront_document_state('invoice', 0, 0, 10000));
	pg_assert_same('paid', erp_storefront_document_state('invoice', 0, 30, 0));
	pg_assert_same('paid', erp_storefront_document_state('invoice', -1, 0, 0));
}

// Owed and past its due date is overdue, part paid or not.
function test_erp_storefront_document_state_overdue()
{
	pg_assert_same('overdue', erp_storefront_document_state('invoice', 10000, 1, 0));
	pg_assert_same('overdue', erp_storefront_document_state('invoice', 4000, 45, 6000));
}

// Owed and not yet due: partial when something was collected, else open.
// The due day itself is not late.
function test_erp_storefront_document_state_not_yet_due()
{
	pg_assert_same('open', erp_storefront_document_state('invoice', 10000, 0, 0));
	pg_assert_same('open', erp_storefront_document_state('invoice', 10000, -10, 0));
	pg_assert_same('partial', erp_storefront_document_state('invoice', 4000, 0, 6000));
	pg_assert_same('partial', erp_storefront_document_state('invoice', 4000, -3, 6000));
}

// The values arrive from the database as strings.
function test_erp_storefront_document_state_reads_strings()
{
	pg_assert_same('overdue', erp_storefront_document_state('invoice', '2500', '7', '0'));
	pg_assert_same('paid', erp_storefront_document_state('invoice', '0', '7', '2500'));
}
