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

// The Site Log line of a payment iyzico refused on the return: the order and
// the payment it is found by, with the mdStatus the bank posted.
function payment_iyzico_threeds_return_test_refused_line($code, $group, $message, $md_status = '1')
{
	return lang(array(
		'string' => 'Iyzipay 3D Secure payment for order #{var:1} was not accepted: code {var:2}, group {var:3}, message {var:4} (payment {var:5}, conversation {var:6}, mdStatus {var:7}).',
		'vars'   => array(438, $code, $group, $message, '24680', '123456789', $md_status)));
}

// The second line of a refusal whose code names no reason on the card's side.
function payment_iyzico_threeds_return_test_bank_answer_line()
{
	return lang('iyzico gave no card-side reason for this refusal. The bank\'s own answer is shown on the payment\'s detail in the iyzico panel; iyzico support can say what it means.');
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
		pg_assert_same(array(lang(array('string' => 'Iyzipay 3D Secure answer matches no payment started for order #{var:1} (conversation {var:2}).', 'vars' => array($order_id, $conversation_id)))), $answer['log'], $label);
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
	pg_assert_same(array(lang(array('string' => 'Iyzipay 3D Secure answer was already used (conversation {var:1}).', 'vars' => array('123456789')))), $answer['log']);
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
			array(lang(array('string' => 'Iyzipay 3D Secure verification failed (error 5002) for payment {var:1}.', 'vars' => array('24680'))))),
		'not paid' => array(
			array('status' => 'failure', 'errorCode' => '10051', 'errorGroup' => 'NOT_SUFFICIENT_FUNDS', 'errorMessage' => 'Kart limiti yetersiz, yetersiz bakiye'),
			lang(array('string' => 'Your bank did not approve the payment ({var:1}). Please check your card with your bank or try another card.', 'vars' => array('Kart limiti yetersiz, yetersiz bakiye'))),
			array(payment_iyzico_threeds_return_test_refused_line('10051', 'NOT_SUFFICIENT_FUNDS', 'Kart limiti yetersiz, yetersiz bakiye'))),
		'fraud' => array(
			payment_iyzico_threeds_return_test_paid(array('fraudStatus' => -1)),
			lang('Payment was found to be risky and rejected.'),
			array(lang(array('string' => 'Iyzipay rejected payment {var:1} as fraud.', 'vars' => array('24680'))))),
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
	pg_assert_same(array(lang(array('string' => 'Iyzipay holds payment {var:1} for a fraud review.', 'vars' => array('24680'))), payment_iyzico_threeds_return_test_completed_line()), $review['log']);
}

// What iyzico refuses is told by its code. A reason on the card's side is
// given in the bank's words. Any other bank code (an answer iyzico does not
// know, the terminal) does not send the visitor to their bank, and the log
// says where the bank's own answer is read. A lost card and a code that is
// not a bank's keep the sentence that names no reason.
function test_payment_iyzico_threeds_return_refusal_sentence_follows_the_code()
{
	$plain = lang('Payment could not be verified. Please try again.');
	$other_card = lang('The payment could not be taken with this card right now. Please try another card or contact us.');

	$cases = array(
		'an answer iyzico does not know' => array('10202', 'UNKNOWN', 'Ödeme işlemi esnasında genel bir hata oluştu', $other_card, true),
		'the terminal'                   => array('10058', 'NOT_PERMITTED_TO_TERMINAL', 'Terminalin bu işlemi yapmaya yetkisi yok', $other_card, true),
		'a lost card'                    => array('10041', 'LOST_CARD', 'Kayıp kart, karta el koyunuz', $plain, false),
		'not a bank code'                => array('5087', '', 'Ödeme bulunamadı', $plain, false),
		'a card-side code without words' => array('10005', 'DO_NOT_HONOUR', '', lang('Your bank did not approve the payment. Please check your card with your bank or try another card.'), false),
	);

	foreach ($cases as $label => $case) {
		list($code, $group, $message, $error, $bank_answer) = $case;

		$http = payment_iyzico_test_http(array('/payment/3dsecure/auth' => array('status' => 'failure', 'errorCode' => $code, 'errorGroup' => $group, 'errorMessage' => $message)));
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

		$log = array(payment_iyzico_threeds_return_test_refused_line($code, ($group !== '') ? $group : '-', ($message !== '') ? $message : '-'));

		if ($bank_answer) {
			$log[] = payment_iyzico_threeds_return_test_bank_answer_line();
		}

		pg_assert_false($answer['success'], $label);
		pg_assert_same($error, $answer['error'], $label);
		pg_assert_same($log, $answer['log'], $label);
		pg_assert_same(1, count($http->posts), $label . ': no cancel');
		pg_assert_same(array(), $answer['state'], $label . ': the wait ends');
	}
}

// iyzico posts the result of the bank's verification with the payment. A
// status other than success is a cardholder the bank did not verify: iyzico
// is not asked for the payment, the row stays unclaimed, and the log keeps
// the bank's mdStatus with what it means.
function test_payment_iyzico_threeds_return_stops_at_a_failed_verification()
{
	$cases = array(
		'the signature' => array(array('status' => 'failure', 'mdStatus' => '0'), '0', '24680'),
		'no payment id' => array(array('status' => 'failure', 'mdStatus' => '5', 'paymentId' => ''), '5', '-'),
		'no mdStatus'   => array(array('status' => 'failure', 'mdStatus' => ''), '', '24680'),
	);

	foreach ($cases as $label => $case) {
		list($changes, $md_status, $payment_id) = $case;

		$http = payment_iyzico_test_http();
		$answer = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post($changes), array('installment' => 3), $store);

		pg_assert_false($answer['success'], $label);
		pg_assert_same(lang('3D Secure verification failed. Please try again.'), $answer['error'], $label);
		pg_assert_same(array(lang(array(
			'string' => 'Iyzipay 3D Secure verification was not passed for order #{var:1}: status {var:2}, mdStatus {var:3} ({var:4}), payment {var:5}, conversation {var:6}.',
			'vars'   => array(438, 'failure', ($md_status !== '') ? $md_status : '-', pg_payment_iyzico_md_status_meaning($md_status), $payment_id, '123456789')))), $answer['log'], $label);
		pg_assert_same(array(), $http->posts, $label . ': iyzico is not asked');
		pg_assert_same(array(), $store['claimed'], $label . ': the row is not claimed');
		pg_assert_same(array(), $answer['state'], $label . ': the wait ends');
	}

	// Another order's row is no payment of this session, whatever it says.
	$http = payment_iyzico_test_http();
	$answer = payment_iyzico_threeds_return_test_complete(
		payment_iyzico_threeds_return_test_post(array('status' => 'failure', 'mdStatus' => '0')),
		array('installment' => 3),
		$store,
		array('rows' => array('123456789' => payment_iyzico_threeds_return_test_row(array('order_id' => '437')))));

	pg_assert_same(lang('Session could not be verified. Please try again.'), $answer['error']);
	pg_assert_same(array('installment' => 3), $answer['state'], 'the wait goes on');
	pg_assert_same(array(), $http->posts);

	// iyzico's own word decides: success in any case, an answer with no status
	// (read as before the status was) and a success with another mdStatus all
	// go on to iyzico.
	$without_status = payment_iyzico_threeds_return_test_post();
	unset($without_status['status']);

	$asked = array(
		'upper case'    => payment_iyzico_threeds_return_test_post(array('status' => 'SUCCESS')),
		'no status'     => $without_status,
		'a status list' => payment_iyzico_threeds_return_test_post(array('status' => array('failure'))),
		'mdStatus 4'    => payment_iyzico_threeds_return_test_post(array('mdStatus' => '4')),
	);

	foreach ($asked as $label => $post) {
		$http = payment_iyzico_test_http(array('/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid()));
		$answer = payment_iyzico_threeds_return_test_complete($post, array('installment' => 3), $store);

		pg_assert_true($answer['success'], $label);
		pg_assert_same(1, count($http->posts), $label . ': iyzico is asked');
	}
}

// Each mdStatus iyzico lists has its own words for the log.
function test_payment_iyzico_md_status_meanings()
{
	$meanings = array();

	foreach (array('0', '1', '2', '3', '4', '5', '6', '7', '8') as $md_status) {
		$meanings[$md_status] = pg_payment_iyzico_md_status_meaning($md_status);
		pg_assert_true($meanings[$md_status] !== '', 'mdStatus ' . $md_status);
	}

	pg_assert_same(9, count(array_unique($meanings)), 'each its own');
	pg_assert_same($meanings['0'], pg_payment_iyzico_md_status_meaning('-1'), 'one bank posts -1 for 0');
	pg_assert_same($meanings['4'], pg_payment_iyzico_md_status_meaning(4), 'a number');

	$unlisted = pg_payment_iyzico_md_status_meaning('9');
	$missing = pg_payment_iyzico_md_status_meaning('');

	pg_assert_false(in_array($unlisted, $meanings, true), 'a value iyzico does not list');
	pg_assert_false(in_array($missing, $meanings, true), 'no mdStatus');
	pg_assert_true($unlisted !== $missing);
	pg_assert_same($missing, pg_payment_iyzico_md_status_meaning(null));
	pg_assert_same($missing, pg_payment_iyzico_md_status_meaning(array('0')), 'a list is no mdStatus');
}

// A bank error code is five digits from 10000. The codes of a reason on the
// card's side, and the three never told to the visitor, are named one by one.
function test_payment_iyzico_refusal_kind()
{
	foreach (array('10005', '10012', '10051', '10054', '10057', '10084', '10093', '10201', '10206', '10207', '10209', '10212', '10215', '10217', '10218', '10224', '10225', '10226', '10227', '10229', 10051, ' 10051 ') as $code) {
		pg_assert_same('card', pg_payment_iyzico_refusal_kind($code), 'card ' . trim((string) $code));
	}

	foreach (array('10202', '10204', '10058', '10208', '10210', '10214', '10219', '10222', '10228', '10000', '10999') as $code) {
		pg_assert_same('other', pg_payment_iyzico_refusal_kind($code), 'other ' . $code);
	}

	foreach (array('10034', '10041', '10043', '5087', '5002', '1001', '12', '', 'curl_28', '9999', '100510', '20000', 'x10051', null, array('10051')) as $code) {
		pg_assert_same('plain', pg_payment_iyzico_refusal_kind($code), 'plain ' . (is_scalar($code) ? (string) $code : gettype($code)));
	}
}

// The order must still total what the payment was started with, and iyzico
// must have taken exactly that plus the instalment charge; else the payment
// is cancelled with the gateway code's request and reason.
function test_payment_iyzico_threeds_return_cancels_a_payment_for_another_total()
{
	$cases = array(
		'cart changed'  => array(10914, 110.35, payment_iyzico_threeds_return_test_row(), array('109.14', '107.14', '110.35', '110.35')),
		'paid less'     => array(10714, 107.14, payment_iyzico_threeds_return_test_row(), array('107.14', '107.14', '107.14', '110.35')),
		'paid more'     => array(10714, 110.36, payment_iyzico_threeds_return_test_row(), array('107.14', '107.14', '110.36', '110.35')),
		'no base total' => array(0, 3.21, payment_iyzico_threeds_return_test_row(array('base_total_cents' => '0')), array('0.00', '0.00', '3.21', '3.21')),
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
			lang(array('string' => 'Iyzipay payment {var:1} does not match order #{var:2}: the order totals {var:3}, the payment was started for {var:4}, iyzico took {var:5} (expected {var:6}).', 'vars' => array('24680', 438, $figures[0], $figures[1], $figures[2], $figures[3]))),
			lang(array('string' => 'Iyzipay payment {var:1} was cancelled: {var:2}.', 'vars' => array('24680', 'Order total changed during 3D Secure verification'))),
		), $answer['log'], $label);
		pg_assert_same(array(), $answer['state'], $label);
	}

	payment_iyzico_test_http(array(
		'/payment/3dsecure/auth' => payment_iyzico_threeds_return_test_paid(array('paidPrice' => 99.00)),
		'/payment/cancel'        => array('status' => 'failure', 'errorMessage' => 'İptal süresi geçti'),
	));

	$refused = payment_iyzico_threeds_return_test_complete(payment_iyzico_threeds_return_test_post(), array('installment' => 3), $store);

	pg_assert_same(lang(array('string' => 'Iyzipay payment {var:1} could not be cancelled ({var:2}). MANUAL REFUND REQUIRED. Gateway message: {var:3}', 'vars' => array('24680', 'Order total changed during 3D Secure verification', 'İptal süresi geçti'))), $refused['log'][1]);
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
		pg_assert_same(lang(array('string' => 'Iyzipay payment {var:1} does not match order #{var:2}: the order totals {var:3}, the payment was started for {var:4}, iyzico took {var:5} (expected {var:6}).', 'vars' => array('24680', 438, '107.14', '107.14', pg_payment_decimal($cents), '3.28'))), $answer['log'][0], 'paid ' . $paid);
	}
}
