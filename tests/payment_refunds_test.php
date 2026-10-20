<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for giving money back through the payment drivers: which driver the
 * order screen's refund and the cancellation's void go to, the name the
 * activity log uses for a service, and what a cancellation records from a
 * driver's answer. The requests are compared with the ones the gateway code
 * in view_order.php (submit_iyzico_refund) and process_order_cancellation()
 * built before they went through the drivers, copied below as legacy_*(),
 * and the log lines with the ones that code wrote. iyzico is never reached:
 * a stand-in takes the place of its HTTP client.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/iyzico.php');

pg_payment_iyzico_sdk();

if (!class_exists('PaymentRefundsTestHttp', false)) {
	// Stands in for iyzico: answers by the end of the address, records every
	// post, and throws when told to, the way a dropped connection does.
	class PaymentRefundsTestHttp implements \Iyzipay\HttpClient
	{
		public $answers = array();
		public $posts = array();
		public $throw = '';

		public function get($url)
		{
			return $this->answer($url, '');
		}

		public function getV2($url, $header)
		{
			return $this->answer($url, '');
		}

		public function post($url, $header, $content)
		{
			return $this->answer($url, $content);
		}

		public function put($url, $header, $content)
		{
			return $this->answer($url, $content);
		}

		public function delete($url, $header, $content = null)
		{
			return $this->answer($url, (string) $content);
		}

		private function answer($url, $content)
		{
			$path = (string) parse_url($url, PHP_URL_PATH);
			$this->posts[] = array('path' => $path, 'body' => json_decode($content, true));

			if ($this->throw !== '') {
				throw new \RuntimeException($this->throw);
			}

			return isset($this->answers[$path]) ? json_encode($this->answers[$path]) : '';
		}
	}
}

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

function payment_refunds_test_http($answers = array(), $throw = '')
{
	$http = new PaymentRefundsTestHttp();
	$http->answers = $answers;
	$http->throw = $throw;
	\Iyzipay\ApiResource::setHttpClient($http);

	return $http;
}

function payment_refunds_test_order($changes = array())
{
	return array_merge(array(
		'id'                  => '438',
		'order_number'        => '1172',
		'status'              => 'cancelled',
		'payment_method'      => 'Pay With Iyzico',
		'transaction_id'      => '24680',
		'total'               => '4714',
		'refunded_amount'     => '0',
		'cancellation_reason' => 'Müşteri vazgeçti',
	), $changes);
}

function payment_refunds_test_success($fields = array())
{
	return array_merge(array('status' => 'success', 'locale' => 'tr', 'systemTime' => 1760950000000), $fields);
}

function payment_refunds_test_without_conversation($body)
{
	unset($body['conversationId']);

	return $body;
}

// view_order.php, submit_iyzico_refund: the AmountBaseRefundRequest it built.
function legacy_refunds_view_order_request($transaction_id, $refund_amount)
{
	$refund_request = new \Iyzipay\Request\AmountBaseRefundRequest();
	$refund_request->setLocale(\Iyzipay\Model\Locale::TR);
	$refund_request->setConversationId((string)rand(100000000, 999999999));
	$refund_request->setPaymentId($transaction_id);
	$refund_request->setPrice($refund_amount);
	$refund_request->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

	return payment_refunds_test_without_conversation(json_decode($refund_request->toJsonString(), true));
}

// process_order_cancellation(): the CreateCancelRequest it built.
function legacy_refunds_cancel_request($order_id, $order, $reason_clean)
{
	$request = new \Iyzipay\Request\CreateCancelRequest();
	$request->setLocale(\Iyzipay\Model\Locale::TR);
	$request->setConversationId((string) $order_id . '-' . time());
	$request->setPaymentId((string) $order['transaction_id']);
	$request->setIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');

	if ($reason_clean !== '') {
		$request->setDescription(mb_substr($reason_clean, 0, 240));
	}

	return payment_refunds_test_without_conversation(json_decode($request->toJsonString(), true));
}

// process_order_cancellation(): what it recorded and logged from iyzico's
// cancel. $cancel is null when the call threw $exception_message.
function legacy_refunds_cancel_outcome($order_id, $order, $cancel, $exception_message = '')
{
	$log = array();
	$refund_status_outcome = '';
	$refund_reference = '';

	if ($cancel === null) {
		$refund_status_outcome = 'manual_required';
		$log[] = 'Auto-refund EXCEPTION — order #' . $order_id . '. MANUAL REFUND REQUIRED. ' . $exception_message;
	} elseif ($cancel->getStatus() == 'success') {
		$refund_status_outcome = 'refunded';
		$refund_reference      = (string) ($cancel->getPaymentId() ?: $order['transaction_id']);
		$log[] = 'Auto-refund SUCCESS via Iyzipay — order #' . $order_id . ' (payment ' . $refund_reference . ').';
	} else {
		$err_msg = method_exists($cancel, 'getErrorMessage') ? (string) $cancel->getErrorMessage() : '';
		$log[] =
			'Auto-refund FAILED via Iyzipay — order #' . $order_id .
			'. MANUAL REFUND REQUIRED. Gateway message: ' . ($err_msg !== '' ? $err_msg : '(no message)');
		$refund_status_outcome = 'manual_required';
	}

	return array('refund_status' => $refund_status_outcome, 'refund_reference' => $refund_reference, 'log' => $log);
}

// iyzico's answer as the gateway code read it.
function payment_refunds_test_legacy_cancel($answer)
{
	$cancel = new \Iyzipay\Model\Cancel();
	$cancel->setStatus(isset($answer['status']) ? $answer['status'] : null);
	$cancel->setErrorMessage(isset($answer['errorMessage']) ? $answer['errorMessage'] : null);
	$cancel->setPaymentId(isset($answer['paymentId']) ? $answer['paymentId'] : null);

	return $cancel;
}

// The cancellation's void through the driver, the way
// process_order_cancellation() runs it.
function payment_refunds_test_cancel($order, $gateway = 'Iyzipay')
{
	$key = pg_payment_cancel_driver($order['payment_method'], $gateway);
	$answer = pg_payment_call($key, 'cancel', array($order, (string) $order['transaction_id']));

	return pg_payment_cancel_outcome((int) $order['id'], (string) $order['transaction_id'], $answer, pg_payment_log_name($key));
}

/* ---------------------------------------------------------------------------
   Which driver
   --------------------------------------------------------------------------- */

// Card orders go to the driver of the Payment Gateway value, every other
// method to its own driver, whichever gateway the store has.
function payment_refunds_test_driver_cases()
{
	return array(
		'card, iyzico gateway'            => array('Credit/Debit Card', 'Iyzipay', 'iyzico'),
		'card, legacy gateway'            => array('Credit/Debit Card', 'Stripe', ''),
		'card, no gateway'                => array('Credit/Debit Card', '', ''),
		'Pay With Iyzico, iyzico gateway' => array('Pay With Iyzico', 'Iyzipay', 'pay_with_iyzico'),
		'Pay With Iyzico, legacy gateway' => array('Pay With Iyzico', 'Stripe', 'pay_with_iyzico'),
		'Pay With Iyzico, no gateway'     => array('Pay With Iyzico', '', 'pay_with_iyzico'),
		'PayPal Express Checkout'         => array('PayPal Express Checkout', 'Iyzipay', ''),
		'Offline Payment'                 => array('Offline Payment', 'Iyzipay', ''),
		'paid with a gift card'           => array('', 'Iyzipay', ''),
		'a gateway value as the method'   => array('Iyzipay', 'Iyzipay', ''),
	);
}

function test_payment_refunds_refund_driver_is_the_orders()
{
	foreach (payment_refunds_test_driver_cases() as $label => $case) {
		pg_assert_same($case[2], pg_payment_refund_driver($case[0], $case[1]), $label);
	}
}

function test_payment_refunds_cancel_driver_is_the_orders()
{
	foreach (payment_refunds_test_driver_cases() as $label => $case) {
		pg_assert_same($case[2], pg_payment_cancel_driver($case[0], $case[1]), $label);
	}
}

function test_payment_refunds_log_name()
{
	pg_assert_same('Iyzipay', pg_payment_log_name('iyzico'));
	pg_assert_same('Iyzipay', pg_payment_log_name('pay_with_iyzico'), 'the same service, as the log has always named it');
	pg_assert_same('', pg_payment_log_name('nosuchdriver'));

	$plain = pg_payment_driver_definition_normalize('examplepay', array('label' => 'Example Pay', 'kind' => 'card', 'gateway' => 'Example Pay', 'file' => '/srv/example.php'), '');
	pg_assert_same('Example Pay', $plain['log_name'], 'the label when none is given');

	$named = pg_payment_driver_definition_normalize('examplepay', array('label' => 'Example Pay', 'log_name' => ' ExamplePay ', 'kind' => 'card', 'gateway' => 'Example Pay', 'file' => '/srv/example.php'), '');
	pg_assert_same('ExamplePay', $named['log_name']);
}

/* ---------------------------------------------------------------------------
   The order screen's refund
   --------------------------------------------------------------------------- */

function test_payment_refunds_refund_sends_the_order_screens_request()
{
	foreach (array(1250 => 12.5, 4714 => 47.14, 1999 => 19.99, 1 => 0.01) as $hundredths => $refund_amount) {
		$http = payment_refunds_test_http(array('/v2/payment/refund' => payment_refunds_test_success(array('paymentId' => 'r-' . $hundredths))));

		$answer = pg_payment_call(pg_payment_refund_driver('Credit/Debit Card', 'Iyzipay'), 'refund', array(payment_refunds_test_order(array('payment_method' => 'Credit/Debit Card')), $hundredths, array()));

		pg_assert_same(1, count($http->posts), (string) $hundredths);
		pg_assert_same('/v2/payment/refund', $http->posts[0]['path']);
		pg_assert_same(legacy_refunds_view_order_request('24680', $refund_amount), payment_refunds_test_without_conversation($http->posts[0]['body']), (string) $hundredths);
		pg_assert_true(preg_match('/^[0-9]{9}$/', (string) $http->posts[0]['body']['conversationId']) === 1, 'nine random digits');
		pg_assert_true($answer['success']);
		pg_assert_same('r-' . $hundredths, $answer['refund_reference'], 'kept as order_refunds.transaction_id');
		pg_assert_same(array(), $answer['log']);
	}

	payment_refunds_test_http(array('/v2/payment/refund' => array('status' => 'failure', 'errorMessage' => 'İade tutarı fazla')));
	$refused = pg_payment_call('iyzico', 'refund', array(payment_refunds_test_order(), 99999, array()));

	pg_assert_false($refused['success']);
	pg_assert_same('İade tutarı fazla', $refused['error'], 'iyzico\'s sentence, shown to the operator');

	payment_refunds_test_http(array(), 'connection reset');
	$unreached = pg_payment_call('iyzico', 'refund', array(payment_refunds_test_order(), 1250, array()));

	pg_assert_false($unreached['success'], 'a dropped connection is a refusal, not an error page');
	pg_assert_same(lang('The payment service could not be reached.'), $unreached['error']);
}

/* ---------------------------------------------------------------------------
   The cancellation's void
   --------------------------------------------------------------------------- */

function test_payment_refunds_cancel_sends_the_cancellations_request()
{
	$order = payment_refunds_test_order();
	$http = payment_refunds_test_http(array('/payment/cancel' => payment_refunds_test_success(array('paymentId' => '24680'))));

	payment_refunds_test_cancel($order);

	pg_assert_same(1, count($http->posts));
	pg_assert_same('/payment/cancel', $http->posts[0]['path']);
	pg_assert_same(legacy_refunds_cancel_request(438, $order, 'Müşteri vazgeçti'), payment_refunds_test_without_conversation($http->posts[0]['body']));
	pg_assert_true(preg_match('/^438-[0-9]+$/', (string) $http->posts[0]['body']['conversationId']) === 1, 'order id and time');

	$http = payment_refunds_test_http(array('/payment/cancel' => payment_refunds_test_success()));
	payment_refunds_test_cancel(payment_refunds_test_order(array('cancellation_reason' => '')));

	pg_assert_same(legacy_refunds_cancel_request(438, $order, ''), payment_refunds_test_without_conversation($http->posts[0]['body']), 'no reason, no description');
}

// A card order is voided through the gateway's card driver with the same
// request and the same lines as a Pay With Iyzico order.
function test_payment_refunds_card_order_is_voided_through_the_gateways_driver()
{
	$order = payment_refunds_test_order(array('payment_method' => 'Credit/Debit Card'));
	$http = payment_refunds_test_http(array('/payment/cancel' => payment_refunds_test_success(array('paymentId' => '24680'))));

	$outcome = payment_refunds_test_cancel($order);

	pg_assert_same(1, count($http->posts));
	pg_assert_same(legacy_refunds_cancel_request(438, $order, 'Müşteri vazgeçti'), payment_refunds_test_without_conversation($http->posts[0]['body']));
	pg_assert_same(legacy_refunds_cancel_outcome(438, $order, payment_refunds_test_legacy_cancel(payment_refunds_test_success(array('paymentId' => '24680')))), $outcome);

	payment_refunds_test_http(array('/payment/cancel' => array('status' => 'failure', 'errorMessage' => 'İptal süresi geçti')));

	pg_assert_same('manual_required', payment_refunds_test_cancel($order)['refund_status']);
	pg_assert_same('', pg_payment_cancel_driver('Credit/Debit Card', 'Stripe'), 'a legacy gateway is left to the operator');
}

function test_payment_refunds_cancel_outcome_keeps_the_cancellations_log()
{
	$order = payment_refunds_test_order();

	$answers = array(
		'success, iyzico names the payment' => payment_refunds_test_success(array('paymentId' => '97531')),
		'success, iyzico names none'        => payment_refunds_test_success(array('paymentId' => '')),
		'refused with a reason'             => array('status' => 'failure', 'errorMessage' => 'İptal süresi geçti'),
		'refused without a reason'          => array('status' => 'failure'),
		'no answer at all'                  => null,
	);

	foreach ($answers as $label => $answer) {
		payment_refunds_test_http(($answer === null) ? array() : array('/payment/cancel' => $answer));

		pg_assert_same(
			legacy_refunds_cancel_outcome(438, $order, payment_refunds_test_legacy_cancel(($answer === null) ? array() : $answer)),
			payment_refunds_test_cancel($order),
			$label);
	}

	payment_refunds_test_http(array(), 'connection reset');

	pg_assert_same(
		legacy_refunds_cancel_outcome(438, $order, null, 'connection reset'),
		payment_refunds_test_cancel($order),
		'the call throws');
}

function test_payment_refunds_cancel_outcome_of_any_driver()
{
	$refunded = pg_payment_cancel_outcome(9, 'tx-9', pg_payment_answer('cancel', array('success' => true, 'status' => 'cancelled', 'refund_reference' => 'void-1', 'log' => array('Example voided tx-9.'))), 'ExamplePay');

	pg_assert_same('refunded', $refunded['refund_status']);
	pg_assert_same('void-1', $refunded['refund_reference']);
	pg_assert_same(array('Example voided tx-9.', 'Auto-refund SUCCESS via ExamplePay — order #9 (payment void-1).'), $refunded['log'], 'the driver\'s own lines first');

	$unreadable = pg_payment_cancel_outcome(9, 'tx-9', pg_payment_answer('cancel', array('success' => true, 'status' => 'paid')), 'ExamplePay');

	pg_assert_same('manual_required', $unreadable['refund_status'], 'a success that says nothing about the money is not a refund');
	pg_assert_same('', $unreadable['refund_reference']);
	pg_assert_same(array('Auto-refund FAILED via ExamplePay — order #9. MANUAL REFUND REQUIRED. Gateway message: (no message)'), $unreadable['log']);
}
