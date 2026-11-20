<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a label's price as an order's shipping cost
 * (includes/erp/order_profit.php, includes/fn/shipment_labels.php): the
 * service's decimal read into minor units, a price in another currency
 * converted, the fixed reference, a label's row replacing the shipping rate,
 * and nothing done while Accounting is off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');

// The reference names the label, so each label is its own row.
function test_erp_order_profit_label_ref()
{
	pg_assert_same('label:42', erp_order_profit_label_ref(42));
	pg_assert_same('label:42', erp_order_profit_label_ref('42'));
}

// The decimal is read at two places, half away from zero, never cut.
function test_erp_order_profit_label_amount_in_the_order_currency()
{
	pg_assert_same(2410, erp_order_profit_label_amount(array('value' => '24.10', 'currency' => 'TRY'), 'TRY', null));
	pg_assert_same(2410, erp_order_profit_label_amount(array('value' => '24.1', 'currency' => 'try'), 'TRY', null));
	pg_assert_same(1, erp_order_profit_label_amount(array('value' => '0.005', 'currency' => 'EUR'), 'EUR', null), 'half a cent goes up');
	pg_assert_same(0, erp_order_profit_label_amount(array('value' => '0.004', 'currency' => 'EUR'), 'EUR', null));
	pg_assert_same(1029, erp_order_profit_label_amount(array('value' => '10.29', 'currency' => 'EUR'), 'EUR', null), '10.29 x 100 is 1028.999... in binary');
	pg_assert_same(1200, erp_order_profit_label_amount(array('value' => '12', 'currency' => 'EUR'), 'EUR', null));
}

// A price in another currency is converted at the rate of the label's day;
// without a rate there is no amount (the shipping rate stays).
function test_erp_order_profit_label_amount_converted()
{
	pg_assert_same(22265, erp_order_profit_label_amount(array('value' => '6.10', 'currency' => 'EUR'), 'TRY', 36.5), '610 x 36.5');
	pg_assert_same(erp_to_base(1190, 1.0731), erp_order_profit_label_amount(array('value' => '11.90', 'currency' => 'EUR'), 'USD', 1.0731));
	pg_assert_same(null, erp_order_profit_label_amount(array('value' => '6.10', 'currency' => 'EUR'), 'TRY', null));
	pg_assert_same(null, erp_order_profit_label_amount(array('value' => '6.10', 'currency' => 'EUR'), 'TRY', 0.0));
	pg_assert_same(null, erp_order_profit_label_amount(array('value' => '6,10', 'currency' => 'EUR'), 'EUR', null));
	pg_assert_same(null, erp_order_profit_label_amount(array(), 'EUR', null));
}

// Two labels of an order (two addresses) add up and replace the shipping
// rate; the order is final with them.
function test_erp_order_profit_labels_replace_the_shipping_rate()
{
	$input = array(
		'order' => array('id' => 7, 'status' => 'complete', 'total' => 10000, 'gift_card_discount' => 0, 'tax' => 0,
			'refunded_amount' => 0, 'shipping' => 800, 'payment_method' => '', 'erp_invoice_id' => 0),
		'items' => array(array('id' => 1, 'product_id' => 3, 'quantity' => 1, 'price' => 10000, 'tax_total' => 0)),
		'invoice_moves' => array(),
		'kept' => array(),
		'averages' => array(3 => 4000),
		'components' => array(),
		'rates' => array('shipping' => array('2' => array('percent' => 0, 'fixed' => 900))),
		'shipping_methods' => array(2),
		'marketplace' => null,
		'recorded' => array(),
	);

	$rated = erp_order_profit_compute($input);
	pg_assert_same(900, $rated['profit']['cost_shipping']);
	pg_assert_same('estimate', $rated['profit']['state']);

	$input['recorded'] = array(
		array('kind' => 'shipping', 'source' => 'label', 'ref' => erp_order_profit_label_ref(11), 'amount' => 2410),
		array('kind' => 'shipping', 'source' => 'label', 'ref' => erp_order_profit_label_ref(12), 'amount' => 2290),
	);
	$labelled = erp_order_profit_compute($input);

	pg_assert_same(4700, $labelled['profit']['cost_shipping']);
	pg_assert_same('final', $labelled['profit']['state']);
	pg_assert_same(10000 - 4000 - 4700, $labelled['profit']['net']);

	foreach ($labelled['costs'] as $row) {
		pg_assert_false($row['kind'] === 'shipping', 'the pass writes no shipping rate beside a label');
	}
}

// Accounting off: nothing is loaded, read or written (no database is
// reached in the test runner, so any query would fail the run).
function test_erp_order_profit_label_off()
{
	pg_assert_false(defined('ERP_ENABLED') && ERP_ENABLED, 'the runner has Accounting off');
	pg_assert_false(erp_order_profit_label_record(5, 9, array('value' => '24.10', 'currency' => 'TRY'), 1763600000));
	pg_assert_false(erp_order_profit_label_forget(5, 9));
	pg_assert_false(pg_shipment_label_profit(5, 9, array('value' => '24.10', 'currency' => 'TRY'), 1763600000));
	pg_assert_false(pg_shipment_label_profit(5, 9, array(), 1763600000));
}

// The label path tells Accounting after the row is written and after the
// cancel is marked, with the label's own id.
function test_erp_order_profit_label_path_calls()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/shipment_labels.php');

	pg_assert_contains("\$out['id'] = (int) mysqli_insert_id(db::\$con);\n\n    // What the service charged becomes the order's shipping cost in\n    // Accounting.\n    pg_shipment_label_profit(\$order['id'], \$out['id'], \$answer['price'], (int) \$row['created_at']);", $source);
	pg_assert_contains("pg_shipment_label_profit((int) \$row['order_id'], (int) \$row['id'], array(), (int) \$update['cancelled_at']);", $source);
}
