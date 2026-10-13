<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the storefront shows a signed-in customer of their own ERP
 * account (includes/erp/storefront.php): how a document reads in the
 * "My Invoices" widget, from what the aging module says is still owed on it
 * and how late it is; and the price the account's agreed terms put on a
 * product beside the store's campaign (erp_storefront_price()); and whether an
 * order may go on the account against its credit limit
 * (erp_storefront_on_account()).
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

// -- prices --------------------------------------------------------------------

// The account's own price for the product stands in for the list price.
function test_erp_storefront_account_price_own_price()
{
	pg_assert_same(8000, erp_storefront_account_price(10000, array('price' => 8000, 'discount_rate' => 0.0, 'source' => 'product')));
	// An own price wins over a discount on the same row, as in the line editor.
	pg_assert_same(8000, erp_storefront_account_price(10000, array('price' => 8000, 'discount_rate' => 50.0, 'source' => 'product')));
}

// A discount, per product or account-wide, comes off the list price, rounded
// to the kurus the way the ERP rounds a rate.
function test_erp_storefront_account_price_discount()
{
	pg_assert_same(9000, erp_storefront_account_price(10000, array('price' => 0, 'discount_rate' => 10.0, 'source' => 'account')));
	pg_assert_same(8888, erp_storefront_account_price(9999, array('price' => 0, 'discount_rate' => 11.11, 'source' => 'product')));
	pg_assert_same(0, erp_storefront_account_price(10000, array('price' => 0, 'discount_rate' => 150.0, 'source' => 'account')), 'never below zero');
}

// Terms that set nothing are no price.
function test_erp_storefront_account_price_nothing()
{
	pg_assert_same(null, erp_storefront_account_price(10000, null));
	pg_assert_same(null, erp_storefront_account_price(10000, array('price' => 0, 'discount_rate' => 0.0, 'source' => 'product')));
}

// Golden: with no terms the price is the list price or the campaign's,
// exactly as given (type and fractions included), so a store with the setting
// off, or a visitor with no account, is priced bit for bit as before.
function test_erp_storefront_price_without_terms_is_unchanged()
{
	pg_assert_same(array('price' => 10000, 'source' => 'list'), erp_storefront_price(10000, null));
	pg_assert_same(array('price' => 8999.1, 'source' => 'offer'), erp_storefront_price(9999, null, 8999.1));
	pg_assert_same(array('price' => 0, 'source' => 'offer'), erp_storefront_price(9999, null, 0));
	pg_assert_same(array('price' => 10000, 'source' => 'list'), erp_storefront_price(10000, array('price' => 0, 'discount_rate' => 0.0, 'source' => 'product')));
}

// Without a campaign the account's price stands when it is lower than the
// list price, and only then.
function test_erp_storefront_price_account_against_list()
{
	pg_assert_same(array('price' => 8000, 'source' => 'account'), erp_storefront_price(10000, array('price' => 8000, 'discount_rate' => 0.0, 'source' => 'product')));
	pg_assert_same(array('price' => 10000, 'source' => 'list'), erp_storefront_price(10000, array('price' => 12000, 'discount_rate' => 0.0, 'source' => 'product')), 'a higher agreed price');
	pg_assert_same(array('price' => 10000, 'source' => 'list'), erp_storefront_price(10000, array('price' => 10000, 'discount_rate' => 0.0, 'source' => 'product')), 'an equal one');
}

// The terms do not stack on the campaign: the lower of the two wins, a tie
// leaves the campaign.
function test_erp_storefront_price_lower_of_campaign_and_account()
{
	$terms = array('price' => 0, 'discount_rate' => 10.0, 'source' => 'account');

	pg_assert_same(array('price' => 7500, 'source' => 'offer'), erp_storefront_price(10000, $terms, 7500), 'a deeper campaign');
	pg_assert_same(array('price' => 9000, 'source' => 'account'), erp_storefront_price(10000, $terms, 9500), 'a shallower one');
	pg_assert_same(array('price' => 9000, 'source' => 'offer'), erp_storefront_price(10000, $terms, 9000), 'a tie');
	pg_assert_same(array('price' => 9000, 'source' => 'account'), erp_storefront_price(10000, $terms, 9000.4), 'fractions of a kurus count');
}

// Golden for the store: with the setting off nobody has agreed prices, so
// the catalogue and the cart never reach for them.
function test_erp_storefront_member_prices_off()
{
	pg_assert_false(defined('ERP_STOREFRONT_ACCOUNT_PRICES') && ERP_STOREFRONT_ACCOUNT_PRICES, 'the runner has the setting off');
	pg_assert_same(array(), pg_erp_member_prices());
}

// -- putting an order on the account -------------------------------------------

// Inside the limit the order goes on the account; reaching the limit exactly
// is still inside it.
function test_erp_storefront_on_account_within_limit()
{
	pg_assert_same(array('allowed' => true, 'available' => 70000, 'after' => 80000), erp_storefront_on_account(100000, 30000, 0, 50000));
	pg_assert_same(array('allowed' => true, 'available' => 50000, 'after' => 100000), erp_storefront_on_account(100000, 30000, 20000, 50000), 'exactly at the limit');
}

// One kurus past the limit is refused.
function test_erp_storefront_on_account_past_limit()
{
	pg_assert_same(array('allowed' => false, 'available' => 50000, 'after' => 100001), erp_storefront_on_account(100000, 30000, 20000, 50001));
}

// The orders already on the account and not invoiced yet count: the ledger
// does not hold them, so the balance alone would let one order follow another.
function test_erp_storefront_on_account_counts_pending_orders()
{
	pg_assert_true(erp_storefront_on_account(10000, 0, 0, 3000)['allowed'], 'nothing pending');
	pg_assert_same(array('allowed' => false, 'available' => 2000, 'after' => 11000), erp_storefront_on_account(10000, 0, 8000, 3000), 'with pending orders');
}

// Without a limit nothing goes on the account; an account already past its
// limit has nothing available.
function test_erp_storefront_on_account_no_limit_or_over()
{
	pg_assert_same(array('allowed' => false, 'available' => 0, 'after' => 1000), erp_storefront_on_account(0, 0, 0, 1000), 'no limit');
	pg_assert_same(array('allowed' => false, 'available' => 0, 'after' => 12001), erp_storefront_on_account(10000, 12000, 0, 1), 'already over');
}

// What the store owes the customer (a balance below zero) widens what is
// available.
function test_erp_storefront_on_account_credit_balance()
{
	pg_assert_same(array('allowed' => true, 'available' => 15000, 'after' => 10000), erp_storefront_on_account(10000, -5000, 0, 15000));
}

// Golden for the store: with the setting off nobody can put an order on
// account, so checkout offers what it offered before.
function test_erp_storefront_open_account_off()
{
	pg_assert_false(defined('ERP_OPEN_ACCOUNT_PAYMENT') && ERP_OPEN_ACCOUNT_PAYMENT, 'the runner has the setting off');
	pg_assert_same(null, pg_erp_open_account());
	pg_assert_same(null, pg_erp_open_account_option());
}
