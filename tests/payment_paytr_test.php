<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the PayTR driver (includes/payments/paytr.php): the get-token
 * hash, the amount in kuruş, the iframe page, the callback's hash check and
 * its "OK" answer, the refund request and how its answers are read, and what payment_callback.php does with a PayTR message
 * (includes/payments/callback.php). PayTR is never called: a stand-in
 * answers the get-token request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/paytr.php');
require_once(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');

function payment_paytr_test_settings($changes = array())
{
	return array_merge(array(
		'merchant_id'   => '123456',
		'merchant_key'  => 'KEYkey',
		'merchant_salt' => 'SALTsalt',
		'test_mode'     => '0',
		'currency'      => 'TRY',
		'lang'          => 'tr',
		'ok_url'        => 'https://shop.example.com/receipt',
		'fail_url'      => 'https://shop.example.com/checkout',
		'time'          => 1761730000,
	), $changes);
}

function payment_paytr_test_order()
{
	return array(
		'id'                    => '42',
		'billing_first_name'    => 'Ayşe',
		'billing_last_name'     => 'Yılmaz',
		'billing_address_1'     => 'Atatürk Cad. 1',
		'billing_address_2'     => '',
		'billing_city'          => 'Kadıköy',
		'billing_state'         => 'İstanbul',
		'billing_country'       => 'TR',
		'billing_phone_number'  => '05551234567',
		'billing_email_address' => 'ayse@example.com',
	);
}

function payment_paytr_test_payment()
{
	return array(
		'ip'    => '88.230.1.2',
		'lines' => array(
			array('short_description' => 'Mug', 'price' => '5000', 'quantity' => '2'),
			array('short_description' => 'Free sample', 'price' => '0', 'quantity' => '1'),
			array('short_description' => '', 'product_name' => 'SKU-9', 'price' => '2350', 'quantity' => '1'),
		),
	);
}

// Stands in for PayTR's get-token API and keeps what it was sent.
function payment_paytr_test_http($status, $body)
{
	$GLOBALS['payment_paytr_test_sent'] = null;

	return function ($fields) use ($status, $body) {
		$GLOBALS['payment_paytr_test_sent'] = $fields;

		return array('status' => $status, 'body' => $body, 'error' => ($status === 0) ? 'Connection timed out' : '');
	};
}

// Stands in for PayTR's refund API and keeps the fields and the endpoint.
function payment_paytr_test_refund_http($response)
{
	$GLOBALS['payment_paytr_test_sent'] = null;

	return function ($fields, $endpoint) use ($response) {
		$GLOBALS['payment_paytr_test_sent'] = array('fields' => $fields, 'endpoint' => $endpoint);

		return $response + array('status' => 200, 'body' => null, 'error' => '', 'errno' => 0);
	};
}

function payment_paytr_test_refund_order($changes = array())
{
	return array_merge(array('id' => '42', 'transaction_id' => 'PG42T1761730000', 'payment_method' => 'PayTR'), $changes);
}

function payment_paytr_test_callback($changes = array(), $key = 'KEYkey', $salt = 'SALTsalt')
{
	$post = array_merge(array(
		'merchant_oid'   => 'PG42T1761730000',
		'status'         => 'success',
		'total_amount'   => '12350',
		'payment_amount' => '12350',
		'currency'       => 'TL',
		'test_mode'      => '0',
		'payment_type'   => 'card',
	), $changes);

	if (!isset($changes['hash'])) {
		$post['hash'] = pg_payment_paytr_callback_hash($post, $key, $salt);
	}

	return http_build_query($post);
}

function test_payment_paytr_amount_in_kurus()
{
	pg_assert_same('12350', pg_payment_paytr_amount(12350));
	pg_assert_same('12350', pg_payment_paytr_amount(12349.6), 'rounded, not truncated');
	pg_assert_same('', pg_payment_paytr_amount(0));
	pg_assert_same('', pg_payment_paytr_amount(-100));
}

function test_payment_paytr_currencies()
{
	pg_assert_same('TL', pg_payment_paytr_currency('try'));
	pg_assert_same('EUR', pg_payment_paytr_currency('EUR'));
	pg_assert_same('', pg_payment_paytr_currency('JPY'));
	pg_assert_same('TRY', pg_payment_paytr_currency_iso('TL'));
	pg_assert_same('USD', pg_payment_paytr_currency_iso('usd'));
	pg_assert_same('', pg_payment_paytr_currency_iso('XYZ'));
}

function test_payment_paytr_merchant_oid()
{
	pg_assert_same('PG42T1761730000', pg_payment_paytr_merchant_oid(42, 1761730000));
	pg_assert_true(pg_payment_paytr_oid_valid('PG42T1761730000'));
	pg_assert_false(pg_payment_paytr_oid_valid('42'), 'an order id is not a merchant_oid');
	pg_assert_false(pg_payment_paytr_oid_valid('PG42T17-61'));
	pg_assert_false(pg_payment_paytr_oid_valid(array()));
}

// PayTR's formula, fixed values: merchant_id, user_ip, merchant_oid, email,
// payment_amount, user_basket, no_installment, max_installment, currency,
// test_mode, then the salt; HMAC-SHA256 with the key, base64.
function test_payment_paytr_token_hash()
{
	$fields = array(
		'merchant_id'     => '123456',
		'user_ip'         => '88.230.1.2',
		'merchant_oid'    => 'PG42T1761730000',
		'email'           => 'ayse@example.com',
		'payment_amount'  => '12350',
		'user_basket'     => 'BASKET',
		'no_installment'  => '1',
		'max_installment' => '0',
		'currency'        => 'TL',
		'test_mode'       => '0',
	);

	pg_assert_same('t0lmI2Kq84zDwiknGfMiixihjklqeFxRafq0SUMtl+0=', pg_payment_paytr_token_hash($fields, 'KEYkey', 'SALTsalt'));
}

function test_payment_paytr_callback_hash()
{
	$post = array('merchant_oid' => 'PG42T1761730000', 'status' => 'success', 'total_amount' => '12350');

	pg_assert_same('tl8yyhHbrxadXXM5YoFRjWSEwnbM7BOJTEOjHvhp+rg=', pg_payment_paytr_callback_hash($post, 'KEYkey', 'SALTsalt'));
}

function test_payment_paytr_basket()
{
	$basket = json_decode(base64_decode(pg_payment_paytr_basket(payment_paytr_test_payment()['lines'])), true);

	pg_assert_same(array(array('Mug', '50.00', 2), array('SKU-9', '23.50', 1)), $basket, 'lines without a price are left out');
}

function test_payment_paytr_authorize_frames_the_payment_page()
{
	$http = payment_paytr_test_http(200, array('status' => 'success', 'token' => 'abc123TOKEN'));
	$answer = pg_payment_answer('authorize', pg_payment_paytr_authorize_with(payment_paytr_test_order(), 12350, payment_paytr_test_payment(), payment_paytr_test_settings(), $http));
	$sent = $GLOBALS['payment_paytr_test_sent'];

	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('PG42T1761730000', $answer['transaction_id'], 'the order keeps the merchant_oid');
	pg_assert_same(12350, $answer['amount']);
	pg_assert_contains('src="https://www.paytr.com/odeme/guvenli/abc123TOKEN"', $answer['html']);
	pg_assert_same(array('PG42T1761730000', 12350), array(pg_payment_started_order_values($answer, 12350)['transaction_id'], pg_payment_started_order_values($answer, 12350)['total']));

	pg_assert_same('12350', $sent['payment_amount']);
	pg_assert_same('TL', $sent['currency']);
	pg_assert_same('1', $sent['no_installment']);
	pg_assert_same('0', $sent['max_installment']);
	pg_assert_same('Ayşe Yılmaz', $sent['user_name']);
	pg_assert_same('https://shop.example.com/receipt', $sent['merchant_ok_url']);
	pg_assert_same('https://shop.example.com/checkout', $sent['merchant_fail_url']);
	pg_assert_same(pg_payment_paytr_token_hash($sent, 'KEYkey', 'SALTsalt'), $sent['paytr_token']);
}

function test_payment_paytr_authorize_refusals()
{
	$http = payment_paytr_test_http(200, array('status' => 'success', 'token' => 'abc'));

	$answer = pg_payment_paytr_authorize_with(payment_paytr_test_order(), 12350, payment_paytr_test_payment(), payment_paytr_test_settings(array('merchant_salt' => '')), $http);
	pg_assert_false($answer['success']);
	pg_assert_same(null, $GLOBALS['payment_paytr_test_sent'], 'no call without the keys');

	$answer = pg_payment_paytr_authorize_with(payment_paytr_test_order(), 12350, payment_paytr_test_payment(), payment_paytr_test_settings(array('currency' => 'JPY')), $http);
	pg_assert_false($answer['success'], 'a currency PayTR does not take');

	$answer = pg_payment_answer('authorize', pg_payment_paytr_authorize_with(payment_paytr_test_order(), 12350, payment_paytr_test_payment(), payment_paytr_test_settings(), payment_paytr_test_http(200, array('status' => 'failed', 'reason' => 'paytr_token gonderilmedi veya gecersiz'))));
	pg_assert_false($answer['success']);
	pg_assert_contains('paytr_token gonderilmedi', $answer['log'][0]);

	$answer = pg_payment_answer('authorize', pg_payment_paytr_authorize_with(payment_paytr_test_order(), 12350, payment_paytr_test_payment(), payment_paytr_test_settings(), payment_paytr_test_http(0, null)));
	pg_assert_false($answer['success']);
	pg_assert_contains('could not be reached', $answer['log'][0]);
}

function test_payment_paytr_notify_success()
{
	$answer = pg_payment_answer('notify', pg_payment_paytr_notify_with(payment_paytr_test_callback(), payment_paytr_test_settings()));

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('PG42T1761730000', $answer['transaction_id']);
	pg_assert_same(array('id' => 0, 'transaction_id' => 'PG42T1761730000'), pg_payment_notify_order_terms($answer));
	pg_assert_same(12350, $answer['amount']);
	pg_assert_same('TRY', $answer['raw']['currency']);
	pg_assert_same('OK', $answer['reply']);
}

// A payment PayTR confirms for the incomplete order that keeps its
// merchant_oid, for its total, in the store's currency, completes the order;
// the answer is the plain "OK" PayTR needs.
function test_payment_paytr_notify_completes_the_incomplete_order()
{
	$answer = pg_payment_answer('notify', pg_payment_paytr_notify_with(payment_paytr_test_callback(), payment_paytr_test_settings()));
	$order = array('id' => '42', 'status' => 'incomplete', 'transaction_id' => 'PG42T1761730000', 'payment_method' => 'PayTR', 'total' => '12350', 'paid_at' => 0);

	$outcome = pg_payment_notify_outcome('paytr', $answer, $order, 'TRY');
	pg_assert_same('complete', $outcome['status']);
	pg_assert_true($outcome['complete']);
	pg_assert_same('OK', pg_payment_callback_reply($answer, $outcome));

	$outcome = pg_payment_notify_outcome('paytr', $answer, array('total' => '12000') + $order, 'TRY');
	pg_assert_same('alerted', $outcome['status'], 'another amount');
	pg_assert_same('OK', pg_payment_callback_reply($answer, $outcome), 'taken: PayTR need not send it again');

	$outcome = pg_payment_notify_outcome('paytr', $answer, array('transaction_id' => 'PG42T1761720000') + $order, 'TRY');
	pg_assert_same('unknown_order', $outcome['status'], 'an earlier attempt of the same order');

	$outcome = pg_payment_notify_outcome('paytr', $answer, array('status' => 'shipped') + $order, 'TRY');
	pg_assert_same('nothing', $outcome['status']);
}

function test_payment_paytr_notify_failed_payment_is_ignored()
{
	$answer = pg_payment_answer('notify', pg_payment_paytr_notify_with(payment_paytr_test_callback(array('status' => 'failed', 'total_amount' => '12350', 'failed_reason_msg' => 'Yetersiz bakiye')), payment_paytr_test_settings()));
	$outcome = pg_payment_notify_outcome('paytr', $answer, null, 'TRY');

	pg_assert_false($answer['success']);
	pg_assert_same('ignored', $outcome['status']);
	pg_assert_same(200, $outcome['http']);
	pg_assert_same('OK', pg_payment_callback_reply($answer, $outcome));
}

function test_payment_paytr_notify_refuses_what_is_not_signed()
{
	$settings = payment_paytr_test_settings();

	$answer = pg_payment_answer('notify', pg_payment_paytr_notify_with(payment_paytr_test_callback(array(), 'KEYkey', 'other'), $settings));
	$outcome = pg_payment_notify_outcome('paytr', $answer, null, 'TRY');
	pg_assert_false($answer['success']);
	pg_assert_same(400, $outcome['http']);
	pg_assert_same('', pg_payment_callback_reply($answer, $outcome), 'no OK for a wrong hash');

	$tampered = payment_paytr_test_callback();
	$tampered = str_replace('total_amount=12350', 'total_amount=100', $tampered);
	pg_assert_false(pg_payment_answer('notify', pg_payment_paytr_notify_with($tampered, $settings))['success'], 'amount changed after hashing');

	pg_assert_same(400, pg_payment_notify_outcome('paytr', pg_payment_answer('notify', pg_payment_paytr_notify_with('status=success', $settings)), null)['http'], 'fields missing');
	pg_assert_same('no_secret', pg_payment_answer('notify', pg_payment_paytr_notify_with(payment_paytr_test_callback(), payment_paytr_test_settings(array('merchant_key' => ''))))['raw']['signature']);
}

function test_payment_paytr_callback_reply_shape()
{
	pg_assert_same('', pg_payment_callback_reply(array('reply' => 'OK'), array('http' => 500)), 'a failure is sent again');
	pg_assert_same('', pg_payment_callback_reply(array('reply' => ''), array('http' => 200)), 'no reply: JSON');
	pg_assert_same('', pg_payment_callback_reply(array('reply' => "<b>OK</b>"), array('http' => 200)), 'only a plain word');
}

function test_payment_paytr_definition_and_csp()
{
	$definitions = pg_payment_builtin_drivers();

	pg_assert_same('redirect', $definitions['paytr']['kind']);
	pg_assert_same('PayTR', $definitions['paytr']['method']);
	pg_assert_same(array('https://www.paytr.com'), $definitions['paytr']['csp']['frame-src']);

	pg_assert_same(
		array('script-src' => array('https://js.stripe.com', 'https://www.paytr.com'), 'frame-src' => array('https://js.stripe.com', 'https://www.paytr.com')),
		pg_payment_csp_merge(array(
			array('script-src' => array('https://js.stripe.com'), 'frame-src' => array('https://js.stripe.com')),
			array('script-src' => array('https://www.paytr.com'), 'frame-src' => array('https://www.paytr.com', 'https://js.stripe.com')),
		)));
	pg_assert_same(array(), pg_payment_csp_merge(array()));
}

function test_payment_paytr_migration_appends_the_method()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_paytr();                  // 11.0.122', $source);
	pg_assert_contains("\"ENUM(\" . \$enum_match[1] . \",'PayTR') \"", $source);
}

// PayTR's refund formula, fixed values: merchant_id, merchant_oid,
// return_amount, then the salt; HMAC-SHA256 with the key, base64.
function test_payment_paytr_refund_hash()
{
	$fields = array('merchant_id' => '123456', 'merchant_oid' => 'PG42T1761730000', 'return_amount' => '11.97', 'reference_no' => 'not hashed');

	pg_assert_same('Ho4tDOXk6CCFc3nAXfh91TAhEHMkBPuEA0hbnBPe/AA=', pg_payment_paytr_refund_hash($fields, 'KEYkey', 'SALTsalt'));
}

function test_payment_paytr_refund_capability()
{
	pg_assert_same(array('partial_refund'), pg_payment_paytr_capabilities());
	pg_assert_true(function_exists('pg_payment_paytr_refund'));
}

function test_payment_paytr_refund_request_and_success()
{
	$http = payment_paytr_test_refund_http(array('body' => array('status' => 'success', 'is_test' => '0', 'merchant_oid' => 'PG42T1761730000', 'return_amount' => '11.97', 'reference_no' => 'PG42R1761730000')));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 1196.6, payment_paytr_test_settings(), $http));
	$sent = $GLOBALS['payment_paytr_test_sent'];

	pg_assert_same('refund', $sent['endpoint']);
	pg_assert_same('123456', $sent['fields']['merchant_id']);
	pg_assert_same('PG42T1761730000', $sent['fields']['merchant_oid']);
	pg_assert_same('11.97', $sent['fields']['return_amount'], 'hundredths rounded, sent as decimal text');
	pg_assert_same('PG42R1761730000', $sent['fields']['reference_no']);
	pg_assert_same('Ho4tDOXk6CCFc3nAXfh91TAhEHMkBPuEA0hbnBPe/AA=', $sent['fields']['paytr_token']);

	pg_assert_true($answer['success']);
	pg_assert_same('refunded', $answer['status']);
	pg_assert_same(1197, $answer['amount']);
	pg_assert_same('PG42R1761730000', $answer['refund_reference']);
	pg_assert_contains('PayTR refunded 11.97 of payment PG42T1761730000 for order #42.', $answer['log'][0]);
}

function test_payment_paytr_refund_full_amount()
{
	$http = payment_paytr_test_refund_http(array('body' => array('status' => 'success', 'merchant_oid' => 'PG42T1761730000', 'return_amount' => '123.50')));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 12350, payment_paytr_test_settings(), $http));

	pg_assert_same('123.50', $GLOBALS['payment_paytr_test_sent']['fields']['return_amount']);
	pg_assert_same(12350, $answer['amount']);
}

function test_payment_paytr_refund_refusals_carry_paytrs_message()
{
	$http = payment_paytr_test_refund_http(array('body' => array('status' => 'error', 'err_no' => '006', 'err_msg' => 'Toplam iade tutarı odeme tutarindan fazla olamaz')));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 99999, payment_paytr_test_settings(), $http));

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear'], 'a refusal is not unclear');
	pg_assert_same('Toplam iade tutarı odeme tutarindan fazla olamaz', $answer['error']);
	pg_assert_same('Toplam iade tutarı odeme tutarindan fazla olamaz', $answer['raw']['message']);
	pg_assert_same('006', $answer['raw']['code']);

	$http = payment_paytr_test_refund_http(array('body' => array('status' => 'failed')));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 100, payment_paytr_test_settings(), $http));

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_same('no payment PG42T1761730000 at PayTR', $answer['raw']['message']);
}

function test_payment_paytr_refund_refused_before_asking()
{
	$http = payment_paytr_test_refund_http(array());

	foreach (array(
		array(payment_paytr_test_refund_order(array('transaction_id' => '')), 100, payment_paytr_test_settings()),
		array(payment_paytr_test_refund_order(array('transaction_id' => 'ch_123')), 100, payment_paytr_test_settings()),
		array(payment_paytr_test_refund_order(), 0, payment_paytr_test_settings()),
		array(payment_paytr_test_refund_order(), 100, payment_paytr_test_settings(array('merchant_salt' => ''))),
	) as $i => $case) {
		$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with($case[0], $case[1], $case[2], $http));

		pg_assert_false($answer['success'], 'case ' . $i);
		pg_assert_false($answer['unclear'], 'case ' . $i);
	}

	pg_assert_same(null, $GLOBALS['payment_paytr_test_sent'], 'PayTR is not asked');
}

function test_payment_paytr_refund_unclear_answers()
{
	// No connection: nothing was sent, the refund may be asked again.
	$http = payment_paytr_test_refund_http(array('status' => 0, 'error' => 'Could not resolve host', 'errno' => 6));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 100, payment_paytr_test_settings(), $http));

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_same('Could not resolve host', $answer['error']);

	// A timeout: the request may have gone out.
	$http = payment_paytr_test_refund_http(array('status' => 0, 'error' => 'Operation timed out', 'errno' => 28));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 100, payment_paytr_test_settings(), $http));

	pg_assert_true($answer['unclear']);
	pg_assert_contains('Operation timed out', $answer['error']);

	// A page that is not PayTR's JSON.
	$http = payment_paytr_test_refund_http(array('status' => 502, 'body' => null));
	$answer = pg_payment_answer('refund', pg_payment_paytr_refund_with(payment_paytr_test_refund_order(), 100, payment_paytr_test_settings(), $http));

	pg_assert_true($answer['unclear']);
	pg_assert_contains('HTTP 502', $answer['error']);
}
