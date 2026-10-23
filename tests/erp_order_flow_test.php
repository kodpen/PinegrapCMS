<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's documents card (includes/erp/order_flow.php):
 * the account the invoice step holds against what the e-document provider
 * would refuse (erp_order_flow_invoice_account_id()).
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
