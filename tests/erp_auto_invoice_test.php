<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the automatic invoice of an order (ERP settings: Invoices for
 * orders, config.erp_auto_invoice_on): whether an order is invoiced on its
 * own at the moment that just happened (erp_auto_invoice_decision()), and how
 * a refusal reads on the panel bell.
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

// A paid web order as the database hands it back: every value a string.
function erp_auto_invoice_test_order($overrides = array())
{
	return array_merge(array(
		'status' => 'complete',
		'type' => 'online',
		'paid_at' => '1760000000',
		'erp_on_account' => '0',
		'total' => '12000',
		'erp_invoice_id' => '0',
	), $overrides);
}

// Off, empty or anything the column was never meant to hold invoices nothing.
function test_erp_auto_invoice_off()
{
	$order = erp_auto_invoice_test_order();

	pg_assert_same('off', erp_auto_invoice_decision('off', 'order_paid', $order, ''));
	pg_assert_same('off', erp_auto_invoice_decision('', 'order_paid', $order, ''));
	pg_assert_same('off', erp_auto_invoice_decision('order_created', 'order_created', $order, ''));
	pg_assert_same('off', erp_auto_invoice_decision(null, 'order_shipped', $order, ''));
}

// The store picked one moment; the other one passes the order by.
function test_erp_auto_invoice_waits_for_its_own_moment()
{
	$order = erp_auto_invoice_test_order();

	pg_assert_same('trigger', erp_auto_invoice_decision('order_paid', 'order_shipped', $order, ''));
	pg_assert_same('trigger', erp_auto_invoice_decision('order_shipped', 'order_paid', $order, ''));
	pg_assert_same('trigger', erp_auto_invoice_decision('order_paid', 'Order_Paid', $order, ''));
}

// A paid web order is invoiced when the store invoices on payment, and a
// shipped one when it invoices on shipment.
function test_erp_auto_invoice_invoices_at_the_chosen_moment()
{
	$order = erp_auto_invoice_test_order();

	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, ''));
	pg_assert_same('', erp_auto_invoice_decision('order_shipped', 'order_shipped', $order, ''));
}

// Paid is a confirmed payment, a sale put on the customer's account or an
// order with nothing left to pay (a gift card or a discount covered it). A
// bank transfer still on its way is not.
function test_erp_auto_invoice_what_paid_means()
{
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('paid_at' => '0', 'erp_on_account' => '1')), ''), 'on account');

	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('paid_at' => '0', 'total' => '0')), ''), 'nothing to pay');

	pg_assert_same('unpaid', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('paid_at' => '0')), ''), 'transfer on its way');
}

// A row from before the on-account column reads as not on account.
function test_erp_auto_invoice_older_row()
{
	$order = erp_auto_invoice_test_order(array('paid_at' => '0'));
	unset($order['erp_on_account']);

	pg_assert_same('unpaid', erp_auto_invoice_decision('order_paid', 'order_paid', $order, ''));

	$order['paid_at'] = '1760000000';

	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, ''));
}

// The invoice is due once the goods leave, paid or not.
function test_erp_auto_invoice_shipment_does_not_wait_for_the_money()
{
	pg_assert_same('', erp_auto_invoice_decision('order_shipped', 'order_shipped',
		erp_auto_invoice_test_order(array('paid_at' => '0')), ''));
}

// Only a finished sale: a checkout that never finished and a cancelled order
// are not invoiced; an exported order is the same sale as a complete one.
function test_erp_auto_invoice_only_finished_sales()
{
	pg_assert_same('status', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('status' => 'incomplete')), ''));

	pg_assert_same('status', erp_auto_invoice_decision('order_shipped', 'order_shipped',
		erp_auto_invoice_test_order(array('status' => 'cancelled')), ''));

	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('status' => 'exported')), ''));
}

// A counter sale is invoiced at the till as it is made, and a marketplace
// order stays a click on its screen.
function test_erp_auto_invoice_web_orders_only()
{
	pg_assert_same('counter_sale', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('type' => 'local')), ''));

	pg_assert_same('marketplace', erp_auto_invoice_decision('order_shipped', 'order_shipped',
		erp_auto_invoice_test_order(array('type' => 'marketplace')), ''));

	// The column's default, and a row older than it.
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('type' => '')), ''));
}

// One invoice per order: a live invoice stops it whatever its state, and so
// does the order's own link to one. An invoice that was cancelled leaves the
// order to the operator rather than issuing a second one on its own.
function test_erp_auto_invoice_one_invoice_per_order()
{
	$order = erp_auto_invoice_test_order();

	foreach (array('issued', 'paid', 'partial', 'draft') as $status) {
		pg_assert_same('invoiced', erp_auto_invoice_decision('order_paid', 'order_paid', $order, $status), $status);
	}

	pg_assert_same('invoiced', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('erp_invoice_id' => '42')), ''), 'linked');

	pg_assert_same('cancelled_before', erp_auto_invoice_decision('order_shipped', 'order_shipped', $order, 'cancelled'));
}

// The moment that was not chosen is said before anything is read about the
// order: a cancelled or invoiced order under the other trigger is "trigger".
function test_erp_auto_invoice_reasons_in_order()
{
	pg_assert_same('trigger', erp_auto_invoice_decision('order_paid', 'order_shipped',
		erp_auto_invoice_test_order(array('status' => 'cancelled')), 'issued'));

	pg_assert_same('status', erp_auto_invoice_decision('order_paid', 'order_paid',
		erp_auto_invoice_test_order(array('status' => 'cancelled', 'type' => 'local')), 'issued'));
}

// The bell row names the order and says why, escaped: the reason can quote
// an account's name. Nobody sees it while the ERP is off.
function test_erp_auto_invoice_notification()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

	$row = array('action' => 'erp_auto_invoice', 'title' => 'Card <b>missing</b> a VKN', 'order_id' => '17', 'order_total' => '1172');
	$display = pg_notification_display($row);

	pg_assert_same('view_order.php?id=17#erp_order_documents', $display['url']);
	pg_assert_contains('1172', $display['description']);
	pg_assert_contains('&lt;b&gt;missing&lt;/b&gt;', $display['description']);
	pg_assert_same('erp_auto_invoice', $display['action']);

	$rights = array('id' => 5, 'role' => 0, 'manage_ecommerce' => true, 'manage_forms' => true, 'manage_erp' => true, 'manage_erp_cash' => true);

	pg_assert_same(defined('ERP_ENABLED') && ERP_ENABLED, pg_notification_visible($row, $rights));
}

// The smallest order the store invoices on its own: the amount is the whole
// sale the invoice states, a gift card included; the minimum itself is
// invoiced and 0 means no minimum.
function test_erp_auto_invoice_minimum_amount()
{
	$order = erp_auto_invoice_test_order(array('total' => '9999', 'gift_card_discount' => '0'));

	pg_assert_same('below_minimum', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array('min_total' => 10000)));
	pg_assert_same('below_minimum', erp_auto_invoice_decision('order_shipped', 'order_shipped', $order, '', array('min_total' => '10000')), 'as stored');
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array('min_total' => 9999)), 'the minimum itself');
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array('min_total' => 0)), 'no minimum');
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array()), 'no filters');

	// 40.00 paid, 80.00 by gift card: a sale of 120.00.
	$gift = erp_auto_invoice_test_order(array('total' => '4000', 'gift_card_discount' => '8000'));

	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $gift, '', array('min_total' => 10000)), 'gift card counts');
	pg_assert_same('below_minimum', erp_auto_invoice_decision('order_paid', 'order_paid', $gift, '', array('min_total' => 12001)));
}

// Only buyers with a tax number: a VKN or a TCKN counts, spaces aside; no
// number, or the final consumer's 11111111111, does not.
function test_erp_auto_invoice_tax_number_only()
{
	$order = erp_auto_invoice_test_order();
	$only = array('tax_number_only' => true);

	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', $only, '1234567890'), 'VKN');
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', $only, '10000000146'), 'TCKN');
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', $only, ' 123 456 7890 '), 'spaces');
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array('tax_number_only' => '1'), 'DE123456789'), 'as stored, abroad');

	pg_assert_same('no_tax_number', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', $only, ''));
	pg_assert_same('no_tax_number', erp_auto_invoice_decision('order_shipped', 'order_shipped', $order, '', $only, '   '));
	pg_assert_same('no_tax_number', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', $only, '11111111111'), 'final consumer');
	pg_assert_same('no_tax_number', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', $only), 'not given');

	// Off, a buyer without a number is invoiced like any other.
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array('tax_number_only' => false), ''));
	pg_assert_same('', erp_auto_invoice_decision('order_paid', 'order_paid', $order, '', array('tax_number_only' => '0'), ''));
}

// The filters come after what is known of the order's invoice and before the
// payment: an invoiced order is "invoiced" whatever its amount, and an order
// the store never invoices on its own says so before it says "unpaid".
function test_erp_auto_invoice_filter_reasons_in_order()
{
	$small = erp_auto_invoice_test_order(array('total' => '500', 'gift_card_discount' => '0', 'paid_at' => '0'));
	$both = array('tax_number_only' => true, 'min_total' => 1000);

	pg_assert_same('invoiced', erp_auto_invoice_decision('order_paid', 'order_paid', $small, 'issued', $both, ''));
	pg_assert_same('cancelled_before', erp_auto_invoice_decision('order_paid', 'order_paid', $small, 'cancelled', $both, ''));
	pg_assert_same('below_minimum', erp_auto_invoice_decision('order_paid', 'order_paid', $small, '', $both, ''));
	pg_assert_same('no_tax_number', erp_auto_invoice_decision('order_paid', 'order_paid', $small, '', array('tax_number_only' => true), ''));
	pg_assert_same('unpaid', erp_auto_invoice_decision('order_paid', 'order_paid', $small, '', array('tax_number_only' => true), '1234567890'));
	pg_assert_same('counter_sale', erp_auto_invoice_decision('order_paid', 'order_paid', array_merge($small, array('type' => 'local')), '', $both, ''));
}

// What the two settings read before the step that adds them has run: off.
function test_erp_auto_invoice_filters_before_the_step()
{
	if (defined('ERP_AUTO_INVOICE_TAX_NUMBER_ONLY') || defined('ERP_AUTO_INVOICE_MIN_TOTAL')) {
		return;
	}

	pg_assert_same(array('tax_number_only' => false, 'min_total' => 0), erp_auto_invoice_filters());
}
