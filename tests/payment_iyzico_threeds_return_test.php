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

// The Iyzipay block of submit_order.php, from its case in the gateway switch
// to the direct payment. An earlier `case 'Iyzipay':` (the cURL check) comes
// first in the file, so the block starts at the last one before its end.
function payment_iyzico_threeds_return_test_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$end = strpos($source, '// Direct (non-3DS) payment');
	$start = ($end !== false) ? strrpos(substr($source, 0, $end), "case 'Iyzipay':") : false;

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
// Its charges_taxed (null: the store's setting) is true wherever the charge is
// lowered: pg_order_charges_settle_rates() needs pg_order_charges_taxed().
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

// The return posts no card: the card number and expiry are set before they
// are read, the instalment limit is still checked but iyzico is not asked for
// a new instalment quote, and after the amount check the order takes the
// state row's totals.
function test_payment_iyzico_threeds_return_takes_the_kept_figures()
{
	$block = payment_iyzico_threeds_return_test_block();
	pg_assert_true($block !== '', 'the Iyzipay block is found');
	pg_assert_contains('IyzipayBootstrap::init();', $block, 'the block is the gateway case');

	pg_assert_contains("\$iyzipay_threeds_return = ((\$_GET['mode'] ?? '') == 'iyzipay_threedsecure_return');", $block);
	pg_assert_true((bool) preg_match("/if \\(\\\$iyzipay_threeds_return\\) \\{\\s*\\\$card_number = '';/", $block), 'no card number on the return');

	$card = strpos($block, "\$card_number = '';");
	$bin = strpos($block, '$card_binNumber = substr($card_number, 0, 6);');
	pg_assert_true(($card !== false) && ($bin !== false) && ($card < $bin), 'the card number is set before the BIN is read');

	$flat = (string) preg_replace('/\s+/', ' ', $block);
	pg_assert_contains("if (\$iyzipay_threeds_return) { \$card_number = ''; \$expiration_month = ''; \$expiration_year = ''; }", $flat, 'no card expiry on the return');

	$expiry = strpos($block, "\$expiration_year = '';");
	$card_expiry = strpos($block, '$paymentCard->setExpireMonth($expiration_month);');
	pg_assert_true(($expiry !== false) && ($card_expiry !== false) && ($expiry < $card_expiry), 'the expiry is set before the card in the request reads it');

	// The limit check runs on the return as before; only the quote is skipped.
	$selection = strpos($block, 'if ($get_buyer_payment_installment_selection >= 1) {');
	$limit = ($selection !== false) ? strpos($block, "lang('Payment Installment cannot be bigger than accepted Installment!')", $selection) : false;
	$skip = ($limit !== false) ? strpos($block, '} elseif (!$iyzipay_threeds_return) {', $limit) : false;
	$quote = strpos($block, 'new \Iyzipay\Request\RetrieveInstallmentInfoRequest()');
	pg_assert_true(($selection !== false) && ($limit !== false), 'the instalment limit is checked on the return too');
	pg_assert_true(($skip !== false) && ($quote !== false) && ($skip < $quote), 'no instalment quote on the return');

	$return = strpos($block, "} elseif ((\$_GET['mode'] ?? '') == 'iyzipay_threedsecure_return') {");
	$check = ($return !== false) ? strpos($block, 'iyzipay_cancel_payment(', $return) : false;
	$after = ($check !== false) ? (string) substr($block, $check) : '';

	pg_assert_contains('$threeds_totals = pg_payment_threeds_state_totals($state);', $after, 'the state row\'s totals after the amount check');
	pg_assert_contains('$total = $threeds_totals[\'total\'];', $after);
	pg_assert_contains('$installment_charge = $threeds_totals[\'installment_charge\'];', $after, 'the charge the bank took');
	pg_assert_contains('$total_witout_installment_charge = $threeds_totals[\'total_without_installment_charge\'];', $after);
}
