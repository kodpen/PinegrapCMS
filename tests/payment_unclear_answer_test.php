<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for payment answers that do not say whether the service did it
 * (includes/payments/driver.php 'unclear', the iyzico refund in
 * includes/payments/iyzico.php) and what a return's refund makes of them
 * (includes/fn/returns.php): no answer in time or a body that is not the
 * service's JSON is not a refusal, so the return is not opened for a second
 * refund.
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

if (!class_exists('PaymentUnclearTestHttp', false)) {
	// Stands in for iyzico's HTTP client with the body as it comes off the
	// wire: JSON, a gateway's HTML page, or nothing. Throws when told to.
	class PaymentUnclearTestHttp implements \Iyzipay\HttpClient
	{
		public $body = '';
		public $throw = '';
		public $posts = 0;

		public function get($url)
		{
			return $this->answer();
		}

		public function getV2($url, $header)
		{
			return $this->answer();
		}

		public function post($url, $header, $content)
		{
			return $this->answer();
		}

		public function put($url, $header, $content)
		{
			return $this->answer();
		}

		public function delete($url, $header, $content = null)
		{
			return $this->answer();
		}

		private function answer()
		{
			$this->posts++;

			if ($this->throw !== '') {
				throw new \RuntimeException($this->throw);
			}

			return $this->body;
		}
	}
}

function payment_unclear_test_refund($body, $throw = '')
{
	$http = new PaymentUnclearTestHttp();
	$http->body = $body;
	$http->throw = $throw;
	\Iyzipay\ApiResource::setHttpClient($http);

	$order = array('id' => '5', 'order_number' => '77000001', 'payment_method' => 'Pay With Iyzico', 'transaction_id' => 'TXN-24680', 'total' => '10000');

	return pg_payment_call('pay_with_iyzico', 'refund', array($order, 5000, array()));
}

// The body the SDK's own cURL client (Iyzipay\Curl::exec()) makes of a
// timeout or a dropped connection.
function payment_unclear_test_curl_body()
{
	return json_encode(array('status' => 'failure', 'errorCode' => 'curl_28', 'errorMessage' => 'cURL: Operation timed out after 30001 milliseconds with 0 bytes received'));
}

function test_payment_unclear_iyzico_refund_answer_reads_the_four_cases()
{
	$refunded = pg_payment_iyzico_refund_answer(5000, true, 'success', '', '', 'R-1');
	pg_assert_true($refunded['success'] === true, 'success is a refund');
	pg_assert_same('R-1', $refunded['refund_reference']);
	pg_assert_true(empty($refunded['unclear']), 'a success is not unclear');

	$refused = pg_payment_iyzico_refund_answer(5000, true, 'failure', '10000', 'İade tutarı fazla', '');
	pg_assert_true($refused['success'] === false, 'iyzico said no');
	pg_assert_true(empty($refused['unclear']), 'a refusal with iyzico\'s own code is clear');
	pg_assert_same('İade tutarı fazla', $refused['error']);

	$timed_out = pg_payment_iyzico_refund_answer(5000, true, 'failure', 'curl_28', 'cURL: Operation timed out', '');
	pg_assert_true($timed_out['success'] === false);
	pg_assert_true($timed_out['unclear'] === true, 'no answer in time: the refund may have gone through');
	pg_assert_same('cURL: Operation timed out', $timed_out['raw']['message'], 'the client\'s words kept for the log');

	$without_status = pg_payment_iyzico_refund_answer(5000, true, '', '', '', '');
	pg_assert_true($without_status['unclear'] === true, 'a body with no status says nothing');

	$unreadable = pg_payment_iyzico_refund_answer(5000, false, '', '', 'Argument #2 must be of type object, null given', '');
	pg_assert_true($unreadable['unclear'] === true, 'a body that is not iyzico\'s JSON says nothing');
}

function test_payment_unclear_answer_shape_keeps_the_flag_on_failures_only()
{
	pg_assert_same(false, pg_payment_answer('refund', array('success' => false, 'error' => 'no'))['unclear'], 'a plain failure is clear');
	pg_assert_same(true, pg_payment_answer('refund', array('success' => false, 'unclear' => true, 'error' => 'timeout'))['unclear'], 'the driver\'s flag is kept');
	pg_assert_same(false, pg_payment_answer('refund', array('success' => true, 'status' => 'refunded', 'unclear' => true))['unclear'], 'a success is never unclear');
	pg_assert_same(false, pg_payment_answer('refund', array('success' => false, 'unclear' => 'yes'))['unclear'], 'only true counts');

	$unreadable_success = pg_payment_answer('refund', array('success' => true, 'status' => 'paid'));
	pg_assert_same(false, $unreadable_success['success'], 'a status the operation cannot have is not a success');
	pg_assert_same(true, $unreadable_success['unclear'], 'nor a refusal: the service did answer success');
}

function test_payment_unclear_iyzico_refund_through_the_driver()
{
	$timed_out = payment_unclear_test_refund(payment_unclear_test_curl_body());
	pg_assert_same(false, $timed_out['success']);
	pg_assert_same(true, $timed_out['unclear'], 'the SDK\'s timeout body is not a refusal');

	$gateway_page = payment_unclear_test_refund('<html><body><h1>502 Bad Gateway</h1></body></html>');
	pg_assert_same(false, $gateway_page['success']);
	pg_assert_same(true, $gateway_page['unclear'], 'a gateway\'s page is not a refusal');
	pg_assert_true(strpos($gateway_page['error'], lang('The payment service could not be reached.')) === false, 'iyzico (or what stands before it) did answer');

	$empty = payment_unclear_test_refund('');
	pg_assert_same(true, $empty['unclear'], 'an empty body');

	$refused = payment_unclear_test_refund(json_encode(array('status' => 'failure', 'errorCode' => '10000', 'errorMessage' => 'İade tutarı fazla')));
	pg_assert_same(false, $refused['success']);
	pg_assert_same(false, $refused['unclear'], 'iyzico\'s refusal stays a refusal');
	pg_assert_same('İade tutarı fazla', $refused['error']);

	$refunded = payment_unclear_test_refund(json_encode(array('status' => 'success', 'paymentId' => 'R-9')));
	pg_assert_same(true, $refunded['success']);
	pg_assert_same(false, $refunded['unclear']);
	pg_assert_same('R-9', $refunded['refund_reference']);

	$thrown = payment_unclear_test_refund('', 'connection reset');
	pg_assert_same(false, $thrown['success']);
	pg_assert_same(true, $thrown['unclear'], 'a driver that throws has not said what the service did');
}

function test_payment_unclear_return_refund_lines()
{
	$lines = pg_return_refund_unclear_lines(5, '77000001', 5000, 'Pay With Iyzico', 'cURL: Operation timed out');

	pg_assert_true(strpos($lines['log'], '77000001') !== false, 'the log line names the order');
	pg_assert_true(strpos($lines['log'], 'Pay With Iyzico') !== false, 'and the service');
	pg_assert_true(strpos($lines['log'], 'cURL: Operation timed out') !== false, 'and what came back');
	pg_assert_true(strpos($lines['log'], pg_return_money_text(5000)) !== false, 'and the amount');
	pg_assert_true(strpos($lines['error'], 'Pay With Iyzico') !== false, 'the screen names the service to check');

	$refused = pg_return_refund_refused_line(5, '77000001', 5000, 'Pay With Iyzico', 'İade tutarı fazla');
	pg_assert_true(strpos($refused, 'İade tutarı fazla') !== false, 'a refusal is logged with the service\'s words');
}
