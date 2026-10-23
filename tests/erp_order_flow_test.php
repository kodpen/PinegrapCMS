<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's documents card (includes/erp/order_flow.php):
 * the account the invoice step holds against what the e-document provider
 * would refuse (erp_order_flow_invoice_account_id()), and the card its gaps
 * are put right on (erp_order_flow_buyer_card()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_flow.php');

// Before the invoice is written, the account it will be written to: a
// returning customer's web order names none (orders.erp_account_id is 0 until
// it is invoiced), and its invoice goes to the contact's account.
function test_erp_order_flow_invoice_account_before_the_invoice()
{
	pg_assert_same(9, erp_order_flow_invoice_account_id(null, 9), 'the contact\'s account');
	pg_assert_same(9, erp_order_flow_invoice_account_id(null, '9'), 'as the database hands it back');
	pg_assert_same(0, erp_order_flow_invoice_account_id(null, 0), 'no account yet: the first invoice opens it');
}

// Once the invoice is written, its own account, whichever account the order
// or the contact names by now.
function test_erp_order_flow_invoice_account_after_the_invoice()
{
	pg_assert_same(7, erp_order_flow_invoice_account_id(array('id' => '31', 'account_id' => '7', 'status' => 'issued'), 9));
	pg_assert_same(7, erp_order_flow_invoice_account_id(array('id' => '31', 'account_id' => '7', 'status' => 'draft'), 0), 'a draft');
}

// The buyer's gaps are put right on the account card when there is an account.
function test_erp_order_flow_buyer_card_of_an_account()
{
	pg_assert_same(array('card' => 'account', 'url' => 'edit_erp_account.php?id=9'),
		erp_order_flow_buyer_card(array('id' => '9', 'contact_id' => '140', 'title' => 'Acme'), 448), 'an account linked to a contact');
	pg_assert_same(array('card' => 'account', 'url' => 'edit_erp_account.php?id=9'),
		erp_order_flow_buyer_card(array('id' => '9', 'contact_id' => '0'), 448), 'an account without a contact');
}

// A new customer's first order has no account yet: the invoice opens it from
// the contact card and the order's billing block (erp_account_data_for_contact(),
// unsaved and without an id). The account form, filled in the same way, holds
// every field the provider asks for and is behind the ERP's own gate; the
// contact card has no district and its street brings the whole address.
function test_erp_order_flow_buyer_card_of_the_account_to_open()
{
	pg_assert_same(array('card' => 'new_account', 'url' => 'add_erp_account.php?contact_id=139&order_id=448'),
		erp_order_flow_buyer_card(array('kind' => 'customer', 'title' => 'Acme Hotels', 'contact_id' => 139), 448));
	pg_assert_same(array('card' => 'new_account', 'url' => 'add_erp_account.php?contact_id=139&order_id=448'),
		erp_order_flow_buyer_card(array('id' => 0, 'contact_id' => '139'), '448'), 'an id of 0 is no account');
	pg_assert_same(array('card' => 'new_account', 'url' => 'add_erp_account.php?contact_id=139'),
		erp_order_flow_buyer_card(array('contact_id' => 139), 0), 'without an order the form is filled from the card');
	pg_assert_same(array('card' => '', 'url' => ''), erp_order_flow_buyer_card(array('title' => 'Acme Hotels'), 448), 'neither');
}
