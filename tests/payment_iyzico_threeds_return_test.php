<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the iyzico 3-D Secure return that submit_order.php's Iyzipay
 * block finishes: the order records the totals kept in the iyzipay_3ds_state
 * row when the payment started (pg_payment_threeds_state_totals()), so its
 * instalment charge is the one the bank took and its invoice meets the
 * payment (erp_order_lines()).
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

// The Iyzipay block of submit_order.php, from its case to the direct payment.
function payment_iyzico_threeds_return_test_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$start = strpos($source, "case 'Iyzipay':");
	$end = ($start !== false) ? strpos($source, '// Direct (non-3DS) payment', $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The row's values come from mysqli as strings.
function test_payment_iyzico_threeds_return_records_the_kept_charge()
{
	// 57.92 and the 2.04 the card was charged (iyzico quoted 2.05).
	pg_assert_same(
		array('total' => 5996, 'installment_charge' => 204, 'total_without_installment_charge' => 5792),
		pg_payment_threeds_state_totals(array('base_total_cents' => '5792', 'installment_charge_cents' => '204')));

	pg_assert_same(
		array('total' => 10714, 'installment_charge' => 0, 'total_without_installment_charge' => 0),
		pg_payment_threeds_state_totals(array('base_total_cents' => '10714', 'installment_charge_cents' => '0')),
		'no instalment charge');
}

// 39.95 at 20% (VAT 7.99) with shipping 9.99, which the checkout charges 9.98:
// the base is 57.92, iyzico quotes a 2.05 charge and the card is charged 2.04.
// Only the kept 2.04 in orders.installment_charges gives the invoice total
// erp_invoice_from_order() accepts; a new quote (2.05) or none (0) is refused.
function test_payment_iyzico_threeds_return_order_is_invoiced()
{
	$lines = array(11 => array('amount' => 3995, 'rate' => '20.000', 'tax' => 799));
	$items = array(array('product_id' => 7, 'product_name' => 'Item', 'short_description' => '', 'quantity' => 1, 'price' => 3995, 'tax_total' => 799, 'tax_rate' => '20.000'));

	$charges = pg_order_charges_reachable($lines, 0, array('shipping' => 999));
	pg_assert_same(998, $charges['shipping']);

	$kept = pg_order_installment_charge($lines, 0, array('shipping' => 998, 'surcharge' => 0), 205);
	pg_assert_same(204, $kept);

	$totals = pg_payment_threeds_state_totals(array('base_total_cents' => 3995 + 799 + 998, 'installment_charge_cents' => $kept));
	pg_assert_same(5996, $totals['total']);

	foreach (array(array($totals['installment_charge'], true), array(205, false), array(0, false)) as $case) {
		list($column, $accepted) = $case;

		$order = array('discount' => 0, 'tax' => 799, 'shipping' => 998, 'surcharge' => 0, 'installment_charges' => $column, 'total' => $totals['total'], 'gift_card_discount' => 0);
		$built = erp_order_lines($order, $items, true, true);
		$expected = $order['total'] + $order['gift_card_discount'] - $built['totals']['charges_left'];

		pg_assert_same($accepted, $built['totals']['grand_total'] === $expected, 'installment_charges ' . $column);
	}
}

// The return posts no card: the card number is set before the BIN is read,
// iyzico is not asked for a new instalment quote, and after the amount check
// the order takes the state row's totals.
function test_payment_iyzico_threeds_return_takes_the_kept_figures()
{
	$block = payment_iyzico_threeds_return_test_block();
	pg_assert_true($block !== '', 'the Iyzipay block is found');

	pg_assert_contains("\$iyzipay_threeds_return = ((\$_GET['mode'] ?? '') == 'iyzipay_threedsecure_return');", $block);
	pg_assert_true((bool) preg_match("/if \\(\\\$iyzipay_threeds_return\\) \\{\\s*\\\$card_number = '';/", $block), 'no card number on the return');

	$card = strpos($block, "\$card_number = '';");
	$bin = strpos($block, '$card_binNumber = substr($card_number, 0, 6);');
	pg_assert_true(($card !== false) && ($bin !== false) && ($card < $bin), 'the card number is set before the BIN is read');

	pg_assert_contains('if (!$iyzipay_threeds_return && ($get_buyer_payment_installment_selection >= 1)) {', $block, 'no instalment quote on the return');

	$return = strpos($block, "} elseif ((\$_GET['mode'] ?? '') == 'iyzipay_threedsecure_return') {");
	$check = ($return !== false) ? strpos($block, 'iyzipay_cancel_payment(', $return) : false;
	$after = ($check !== false) ? (string) substr($block, $check) : '';

	pg_assert_contains('$threeds_totals = pg_payment_threeds_state_totals($state);', $after, 'the state row\'s totals after the amount check');
	pg_assert_contains('$total = $threeds_totals[\'total\'];', $after);
	pg_assert_contains('$installment_charge = $threeds_totals[\'installment_charge\'];', $after, 'the charge the bank took');
	pg_assert_contains('$total_witout_installment_charge = $threeds_totals[\'total_without_installment_charge\'];', $after);
}
