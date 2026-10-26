<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the iyzico 3-D Secure return, which the card driver's complete()
 * finishes (includes/payments/iyzico.php): the iyzipay_3ds_state row the
 * start of the payment wrote is found and claimed, the bank's payment is
 * confirmed with the request, sentences and log lines the gateway code in
 * submit_order.php's Iyzipay block used, and the order records the row's
 * totals (pg_payment_threeds_state_totals()), so its instalment charge is
 * the one the bank took and its invoice meets the payment (erp_order_lines()).
 *
 * iyzico is the stand-in HTTP client of payment_iyzico_test.php (loaded
 * before this file, with its fixtures); the row is read and claimed through
 * recording functions, so no database is reached.
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
require_once(PG_FUNCTIONS_DIR . '/includes/payments/iyzico.php');

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

// The row the start of test_payment_iyzico_threeds_start_keeps_the_row_the_return_reads()
// keeps (three instalments: iyzico's 110.35 on 107.14), as mysqli reads it.
function payment_iyzico_threeds_return_test_row($changes = array())
{
	return array_merge(array(
		'id'                       => '91',
		'conversation_id'          => '123456789',
		'order_id'                 => '438',
		'subtotal_cents'           => '9490',
		'discount_cents'           => '500',
		'installment_charge_cents' => '321',
		'base_total_cents'         => '10714',
		'payment_id'               => null,
	), $changes);
}

// What the bank posts back to the return address.
function payment_iyzico_threeds_return_test_post($changes = array())
{
	return array_merge(array(
		'status'           => 'success',
		'paymentId'        => '24680',
		'conversationData' => 'cd-1',
		'conversationId'   => '123456789',
		'mdStatus'         => '1',
	), $changes);
}

// iyzico's answer to the payment the bank confirmed.
function payment_iyzico_threeds_return_test_paid($changes = array())
{
	return payment_iyzico_test_success(array_merge(array(
		'paymentId'       => '24680',
		'fraudStatus'     => 1,
		'cardAssociation' => 'MASTER_CARD',
		'installment'     => 3,
		'paidPrice'       => 110.35,
		'basketId'        => '1023',
	), $changes));
}

/**
 * The card driver's return against recording row functions.
 *
 * @param array $post    what the bank posted
 * @param array $state   what the start kept in the session
 * @param array $store   filled: found (conversation ids looked up), claimed
 *                       (row id and payment id of each claim)
 * @param array $changes rows (by conversation id), claimable (the claim finds
 *                       the row unused), settings, total, order
 * @return array the answer, through pg_payment_answer()
 */
function payment_iyzico_threeds_return_test_complete($post, $state, &$store = null, $changes = array())
{
	$changes = array_merge(array(
		'rows'      => array('123456789' => payment_iyzico_threeds_return_test_row()),
		'claimable' => true,
		'settings'  => payment_iyzico_test_settings(),
		'total'     => payment_iyzico_test_total(),
		'order'     => payment_iyzico_test_order(),
	), $changes);

	$store = array('found' => array(), 'claimed' => array());

	$find = function ($conversation_id) use (&$store, $changes) {
		$store['found'][] = $conversation_id;

		return isset($changes['rows'][$conversation_id]) ? $changes['rows'][$conversation_id] : null;
	};

	$claim = function ($row, $payment_id) use (&$store, $changes) {
		$store['claimed'][] = array($row['id'], $payment_id);

		return $changes['claimable'];
	};

	return pg_payment_answer('complete', pg_payment_iyzico_complete_with(
		$changes['order'],
		$changes['total'],
		$post,
		$state,
		$changes['settings'],
		$find,
		$claim));
}

// submit_order.php, case 'Iyzipay', the 3-D Secure return: the CreateThreedsPaymentRequest.
function legacy_iyzico_threeds_payment_request($conversationId, $paymentId, $conversationData)
{
	$req3ds = new \Iyzipay\Request\CreateThreedsPaymentRequest();
	$req3ds->setLocale('TR');
	$req3ds->setConversationId($conversationId);
	$req3ds->setPaymentId($paymentId);
	$req3ds->setConversationData($conversationData);

	return json_decode($req3ds->toJsonString(), true);
}

function payment_iyzico_threeds_return_test_completed_line()
{
	return lang(array(
		'string' => 'The 3D Security process has been completed by {var:1} {var:2} ({var:3}).',
		'vars'   => array('Ayşe', 'Yılmaz', 'ayse@example.com')));
}

/* ---------------------------------------------------------------------------
   The order's figures
   --------------------------------------------------------------------------- */

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

// The order records what submit_order.php's Iyzipay block recorded: the row's
// total and instalment charge (the lowered 2.04 of the invoice test above
// stays 2.04), the bank's instalment count, no new quote from iyzico.
function test_payment_iyzico_threeds_return_order_takes_the_row_totals()
{
	$cases = array(
		'three instalments' => array(payment_iyzico_threeds_return_test_row(), 110.35, 3, 10714, 11035, 321),
		'a lowered charge'  => array(payment_iyzico_threeds_return_test_row(array('base_total_cents' => '5792', 'installment_charge_cents' => '204')), 59.96, 2, 5792, 5996, 204),
		'one payment'       => array(payment_iyzico_threeds_return_test_row(array('installment_charge_cents' => '0')), 107.14, 1, 10714, 10714, 0),
	);

	foreach ($cases as $label => $case) {
		list($row, $paid, $installment, $total, $order_total, $charge) = $case;

		$http = payment_iyzico_test_http(array('/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('paidPrice' => $paid, 'installment' => $installment))));
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => $installment), $store, array('rows' => array('123456789' => $row), 'total' => $total));

		pg_assert_true($answer['success'], $label);
		pg_assert_same(array('/payment/3dsecure/auth'), array_column($http->posts, 'path'), $label . ': no instalment quote');
		pg_assert_same(
			array('total' => $order_total, 'installment_charge' => $charge, 'total_without_installment_charge' => ($charge > 0) ? $total : 0, 'payment_installment' => $installment),
			pg_payment_checkout_totals($answer, $total),
			$label);
		pg_assert_same(
			pg_payment_threeds_state_totals($row) + array('payment_installment' => $installment),
			pg_payment_checkout_totals($answer, $total),
			$label . ': the gateway code\'s figures');
	}
}

// The return posts no card and the card checks do not run for it, so the
// driver branch of submit_order.php gives the order an empty card number and
// expiry, as the Iyzipay block did; a card typed into the service's own
// fields keeps what the service says of it.
function test_payment_iyzico_threeds_return_order_keeps_no_card()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$start = strpos($source, '$payment_driver = ($nonrecurring_transaction == TRUE)');
	$end = ($start !== false) ? strpos($source, '// if a non-recurring transaction is required, then process transaction through payment gateway', $start) : false;
	pg_assert_true(($start !== false) && ($end !== false), 'the driver branch is found');

	$flat = (string) preg_replace('/\s+/', ' ', substr($source, (int) $start, (int) $end - (int) $start));

	pg_assert_contains("\$payment_return = (\$payment_return_mode !== '') && ((\$_GET['mode'] ?? '') === \$payment_return_mode);", $flat);
	pg_assert_contains("if (\$payment_return) { \$payment_answer = pg_payment_call(\$payment_driver, 'complete',", $flat);
	pg_assert_contains("} elseif (\$payment_return) { \$card_number = ''; \$expiration_month = ''; \$expiration_year = ''; }", $flat);

	$hosted = strpos($flat, "if (\$payment_hosted_card_driver !== '') {");
	$blank = strpos($flat, "} elseif (\$payment_return) { \$card_number = '';");
	pg_assert_true(($hosted !== false) && ($blank !== false) && ($hosted < $blank), 'a hosted card keeps the service\'s last four digits');
}

/* ---------------------------------------------------------------------------
   The card driver's complete()
   --------------------------------------------------------------------------- */

function test_payment_iyzico_threeds_return_sends_the_legacy_request()
{
	$http = payment_iyzico_test_http(array('/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid()));

	$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

	pg_assert_same(array('123456789'), $store['found']);
	pg_assert_same(array(array('91', '24680')), $store['claimed'], 'the row is claimed with the posted payment id');
	pg_assert_same(1, count($http->posts));
	pg_assert_same('/payment/3dsecure/auth', $http->posts[0]['path']);
	pg_assert_same(legacy_iyzico_threeds_payment_request('123456789', '24680', 'cd-1'), $http->posts[0]['body']);

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('24680', $answer['transaction_id']);
	pg_assert_same(11035, $answer['amount']);
	pg_assert_same(3, $answer['installments']);
	pg_assert_same('MASTER_CARD', $answer['card_brand']);
	pg_assert_same('', $answer['card_last4'], 'the order keeps no card digits, as before');
	pg_assert_same(array(), $answer['state'], 'the wait ends');
	pg_assert_same(array(payment_iyzico_threeds_return_test_completed_line()), $answer['log']);
}

// The payment iyzico confirmed without an id keeps the one the bank posted.
function test_payment_iyzico_threeds_return_transaction_id_falls_back_to_the_posted_one()
{
	$paid = payment_iyzico_threeds_return_test_paid();
	unset($paid['paymentId']);
	payment_iyzico_test_http(array('/payment/3dsecure/auth' => $paid));

	$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

	pg_assert_true($answer['success']);
	pg_assert_same('24680', $answer['transaction_id']);
}

// Before anything else the visitor's choice is checked against the store's
// limit, which may have been lowered while the visitor was at the bank; a
// start that kept no choice (or none) is not checked.
function test_payment_iyzico_threeds_return_checks_the_instalment_limit()
{
	$http = payment_iyzico_test_http();
	$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 7), $store);

	pg_assert_false($answer['success']);
	pg_assert_same(lang('Payment Installment cannot be bigger than accepted Installment!'), $answer['error']);
	pg_assert_same(array(), $answer['log']);
	pg_assert_same(array(), $store['found'], 'the row is not read');
	pg_assert_same(array(), $http->posts, 'iyzico is not asked');
	pg_assert_same(array('installment' => 7), $answer['state'], 'the wait goes on');

	foreach (array('at the limit' => array('installment' => 6), 'no choice' => array('installment' => 0), 'nothing kept' => array()) as $label => $state) {
		payment_iyzico_test_http(array('/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid()));
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), $state, $store);

		pg_assert_true($answer['success'], $label);
	}
}

// The row must be the one the payment was started with, for the order in the
// visitor's session, and the bank must name the payment; iyzico is not asked
// and the row is not claimed before.
function test_payment_iyzico_threeds_return_needs_its_row_and_order()
{
	$cases = array(
		'no row'                 => array(payment_iyzico_threeds_return_test_post(array('conversationId' => '987654321')), array(), '987654321', 438),
		'another order'          => array(payment_iyzico_threeds_return_test_post(), array('rows' => array('123456789' => payment_iyzico_threeds_return_test_row(array('order_id' => '437')))), '123456789', 438),
		'no order'               => array(payment_iyzico_threeds_return_test_post(), array('order' => array()), '123456789', 0),
		'no payment id'          => array(payment_iyzico_threeds_return_test_post(array('paymentId' => '')), array(), '123456789', 438),
		'a payment id array'     => array(payment_iyzico_threeds_return_test_post(array('paymentId' => array('24680'))), array(), '123456789', 438),
		'a conversation id array' => array(payment_iyzico_threeds_return_test_post(array('conversationId' => array('123456789'))), array(), '', 438),
	);

	foreach ($cases as $label => $case) {
		list($post, $changes, $conversation_id, $order_id) = $case;

		$http = payment_iyzico_test_http();
		$answer = payment_iyzico_threeds_return_test_complete($post, array('installment' => 3), $store, $changes);

		pg_assert_false($answer['success'], $label);
		pg_assert_same(lang('Session could not be verified. Please try again.'), $answer['error'], $label);
		pg_assert_same(array("Iyzipay 3DS callback: state not found or order mismatch for conversationId=$conversation_id, order_id=$order_id"), $answer['log'], $label);
		pg_assert_same(array(), $store['claimed'], $label . ': the row is not claimed');
		pg_assert_same(array(), $http->posts, $label . ': iyzico is not asked');
		pg_assert_same(array('installment' => 3), $answer['state'], $label . ': the wait goes on');
	}
}

// A row is claimed once: a replayed return finds it used.
function test_payment_iyzico_threeds_return_is_taken_once()
{
	$http = payment_iyzico_test_http();
	$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store, array('claimable' => false));

	pg_assert_false($answer['success']);
	pg_assert_same(lang('Session could not be verified. Please try again.'), $answer['error']);
	pg_assert_same(array('Iyzipay 3DS callback: state already used for conversationId=123456789'), $answer['log']);
	pg_assert_same(array(array('91', '24680')), $store['claimed']);
	pg_assert_same(array(), $http->posts, 'iyzico is not asked');
	pg_assert_same(array('installment' => 3), $answer['state']);
}

function test_payment_iyzico_threeds_return_bank_and_fraud_refusals()
{
	$cases = array(
		'3-D Secure failed' => array(
			array('status' => 'failure', 'errorCode' => '5002', 'errorMessage' => '3D doğrulaması başarısız', 'paymentId' => '24680'),
			lang('3D Secure verification failed. Please try again.'),
			array('Iyzipay 3DS error 5002: paymentId=24680')),
		'not paid' => array(
			array('status' => 'failure', 'errorCode' => '10051', 'errorMessage' => 'Yetersiz bakiye'),
			lang('Payment could not be verified. Please try again.'),
			array('Iyzipay 3DS status!=success: code=10051, message=Yetersiz bakiye')),
		'fraud' => array(
			payment_iyzico_threeds_return_test_paid(array('fraudStatus' => -1)),
			lang('Payment was found to be risky and rejected.'),
			array('Iyzipay 3DS fraud rejected: paymentId=24680')),
	);

	foreach ($cases as $label => $case) {
		list($body, $error, $log) = $case;

		$http = payment_iyzico_test_http(array('/payment/3dsecure/auth' => $body));
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

		pg_assert_false($answer['success'], $label);
		pg_assert_same($error, $answer['error'], $label);
		pg_assert_same($log, $answer['log'], $label);
		pg_assert_same(1, count($http->posts), $label . ': no cancel');
		pg_assert_same(array(), $answer['state'], $label . ': the wait ends');
	}

	payment_iyzico_test_http(array('/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('fraudStatus' => 0))));
	$review = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

	pg_assert_true($review['success'], 'a payment under review completes the order, as before');
	pg_assert_same(array('Iyzipay 3DS fraud pending review: paymentId=24680', payment_iyzico_threeds_return_test_completed_line()), $review['log']);
}

// The order must still total what the payment was started with, and iyzico
// must have taken exactly that plus the instalment charge; else the payment
// is cancelled with the gateway code's request and reason.
function test_payment_iyzico_threeds_return_cancels_a_payment_for_another_total()
{
	$cases = array(
		'cart changed'  => array(10914, 110.35, payment_iyzico_threeds_return_test_row(), 'current_total=10914, stored_total=10714, gateway_paid=11035, expected_paid=11035'),
		'paid less'     => array(10714, 107.14, payment_iyzico_threeds_return_test_row(), 'current_total=10714, stored_total=10714, gateway_paid=10714, expected_paid=11035'),
		'paid more'     => array(10714, 110.36, payment_iyzico_threeds_return_test_row(), 'current_total=10714, stored_total=10714, gateway_paid=11036, expected_paid=11035'),
		'no base total' => array(0, 3.21, payment_iyzico_threeds_return_test_row(array('base_total_cents' => '0')), 'current_total=0, stored_total=0, gateway_paid=321, expected_paid=321'),
	);

	foreach ($cases as $label => $case) {
		list($total, $paid, $row, $figures) = $case;

		$http = payment_iyzico_test_http(array(
			'/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('paidPrice' => $paid)),
			'/payment/cancel'        => payment_iyzico_test_success(array('paymentId' => '24680')),
		));

		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store, array('rows' => array('123456789' => $row), 'total' => $total));

		pg_assert_false($answer['success'], $label);
		pg_assert_same(lang('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.'), $answer['error'], $label);
		pg_assert_same('/payment/cancel', $http->posts[1]['path'], $label);
		pg_assert_same(legacy_iyzico_void_request('24680', '123456789', 'Order total changed during 3D Secure verification'), $http->posts[1]['body'], $label);
		pg_assert_same(array(
			'Iyzipay 3DS amount mismatch: paymentId=24680, order_id=438, ' . $figures,
			'Iyzipay payment 24680 was cancelled: Order total changed during 3D Secure verification.',
		), $answer['log'], $label);
		pg_assert_same(array(), $answer['state'], $label);
	}

	payment_iyzico_test_http(array(
		'/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('paidPrice' => 99.00)),
		'/payment/cancel'        => array('status' => 'failure', 'errorMessage' => 'İptal süresi geçti'),
	));

	$refused = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

	pg_assert_same('Iyzipay payment 24680 could not be cancelled (Order total changed during 3D Secure verification). MANUAL REFUND REQUIRED. Gateway message: İptal süresi geçti', $refused['log'][1]);
}

// In the protected currency iyzico's amount is converted at today's rate,
// so it may have moved by 1% since the start; the order keeps the base
// currency's figures.
function test_payment_iyzico_threeds_return_in_the_protected_currency()
{
	$settings = payment_iyzico_test_settings(array('protected_currency' => 'USD', 'protected_rate' => 0.0297));

	// 110.35 at 0.0297 is 3.28; 1% of it rounds up to 0.04.
	foreach (array(3.28, 3.24, 3.32) as $paid) {
		payment_iyzico_test_http(array('/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('paidPrice' => $paid))));
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store, array('settings' => $settings));

		pg_assert_true($answer['success'], 'paid ' . $paid);
		pg_assert_same(
			array('total' => 11035, 'installment_charge' => 321, 'total_without_installment_charge' => 10714, 'payment_installment' => 3),
			pg_payment_checkout_totals($answer, 10714),
			'paid ' . $paid);
	}

	foreach (array(array(3.23, 323), array(3.33, 333)) as $case) {
		list($paid, $cents) = $case;


		payment_iyzico_test_http(array(
			'/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('paidPrice' => $paid)),
			'/payment/cancel'        => payment_iyzico_test_success(array('paymentId' => '24680')),
		));
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store, array('settings' => $settings));

		pg_assert_false($answer['success'], 'paid ' . $paid);
		pg_assert_same('Iyzipay 3DS amount mismatch: paymentId=24680, order_id=438, current_total=10714, stored_total=10714, gateway_paid=' . $cents . ', expected_paid=328', $answer['log'][0], 'paid ' . $paid);
	}
}
