<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Iyzipay installment table of the Order Preview and Express
 * Order (pg_payment_iyzico_installment_table(), includes/payments/iyzico.php):
 * no request without installments or keys, a failed card family left out
 * without warnings, the family name escaped, the amounts as iyzico sends
 * them and a repeated render answered from the cache. iyzico is replaced by
 * a stand-in HTTP client; no network is reached.
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

if (!class_exists('IyzipayInstallmentTestHttp', false)) {
	// Stands in for iyzico: answers each bin number with its canned reply.
	class IyzipayInstallmentTestHttp implements \Iyzipay\HttpClient
	{
		public $answers = array();
		public $bins = array();

		public function get($url) { return ''; }
		public function getV2($url, $header) { return ''; }
		public function put($url, $header, $content) { return ''; }
		public function delete($url, $header, $content = null) { return ''; }

		public function post($url, $header, $content)
		{
			$body = json_decode($content, true);
			$bin = (string) $body['binNumber'];
			$this->bins[] = $bin;

			return isset($this->answers[$bin]) ? $this->answers[$bin] : '';
		}
	}
}

function iyzipay_installment_test_http($answers)
{
	$http = new IyzipayInstallmentTestHttp();
	$http->answers = $answers;
	\Iyzipay\ApiResource::setHttpClient($http);

	return $http;
}

function iyzipay_installment_test_settings($changes = array())
{
	return array_merge(array(
		'api_key' => 'sandbox-key', 'secret_key' => 'sandbox-secret',
		'base_url' => 'https://sandbox-api.iyzipay.com', 'locale' => 'TR', 'installments' => '3',
	), $changes);
}

function iyzipay_installment_test_reply($family, $prices)
{
	$rows = array();
	foreach ($prices as $number => $price) {
		$rows[] = array('installmentNumber' => $number, 'installmentPrice' => $price, 'totalPrice' => $price * $number);
	}

	return json_encode(array('status' => 'success', 'installmentDetails' => array(array(
		'cardFamilyName' => $family, 'installmentPrices' => $rows,
	))));
}

function test_iyzipay_installments_no_request_when_off()
{
	$http = iyzipay_installment_test_http(array());
	$cache = array();
	$t = pg_payment_iyzico_installment_table(100, iyzipay_installment_test_settings(array('installments' => '1')), $cache);
	pg_assert_same(array('html' => '', 'rows' => 0), $t, 'one installment: no table');
	$t = pg_payment_iyzico_installment_table(100, iyzipay_installment_test_settings(array('api_key' => '')), $cache);
	pg_assert_same(0, $t['rows'], 'no API key: no table');
	$t = pg_payment_iyzico_installment_table(100, iyzipay_installment_test_settings(array('secret_key' => '')), $cache);
	pg_assert_same(0, $t['rows'], 'no secret key: no table');
	$t = pg_payment_iyzico_installment_table(0, iyzipay_installment_test_settings(), $cache);
	pg_assert_same(0, $t['rows'], 'nothing to pay: no table');
	pg_assert_same(array(), $http->bins, 'iyzico was never asked');
}

function test_iyzipay_installments_table()
{
	$http = iyzipay_installment_test_http(array(
		'454359' => iyzipay_installment_test_reply('Maximum <b>', array(1 => 100, 2 => 51, 3 => 34.5, 6 => 18)),
		'550472' => json_encode(array('status' => 'failure', 'errorMessage' => 'Invalid')),
		'979203' => 'not json',
		'552879' => json_encode(array('status' => 'success', 'installmentDetails' => array())),
		'545103' => iyzipay_installment_test_reply('World', array(1 => 100, 2 => 52)),
	));
	$cache = array();
	$errors = array();
	// Only warnings and notices come from a failed reply. Anything else goes to the
	// runner's handler: PHP 8.4+ raises E_DEPRECATED while the iyzipay-php classes
	// load (implicitly nullable parameters), which the runner lets through for
	// vendored code and still fails for product code.
	$previous = null;
	$previous = set_error_handler(function ($no, $str, $file = '', $line = 0) use (&$errors, &$previous) {
		if (($no & (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE)) !== 0) {
			$errors[] = $str;
			return true;
		}
		return $previous ? call_user_func($previous, $no, $str, $file, $line) : false;
	});
	$t = pg_payment_iyzico_installment_table(100, iyzipay_installment_test_settings(), $cache);
	restore_error_handler();

	pg_assert_same(array(), $errors, 'no warnings from failed replies');
	pg_assert_same(7, count($http->bins), 'one request per card family');
	pg_assert_same(2, $t['rows'], 'failed families are left out');
	pg_assert_true(strpos($t['html'], 'Maximum &lt;b&gt;') !== false && strpos($t['html'], 'Maximum <b>') === false, 'the family name is escaped');
	pg_assert_true(strpos($t['html'], pg_format_money(51, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : null)) !== false, 'the 2-installment amount as iyzico sends it');
	pg_assert_true(strpos($t['html'], pg_format_money(103.5, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : null)) !== false, 'the 3-installment total');
	pg_assert_same(false, strpos($t['html'], pg_format_money(18, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : null)), 'no column past the configured limit');
	pg_assert_same(3, substr_count($t['html'], '<th>'), 'card type, 2 and 3 installments');
	pg_assert_same(1, substr_count($t['html'], '<td></td>'), 'World has no 3-installment price');

	$http->bins = array();
	$again = pg_payment_iyzico_installment_table(100, iyzipay_installment_test_settings(), $cache);
	pg_assert_same($t, $again, 'the same table from the cache');
	pg_assert_same(5, count($http->bins), 'only the failed families are asked again');
	$http->bins = array();
	pg_payment_iyzico_installment_table(120, iyzipay_installment_test_settings(), $cache);
	pg_assert_same(7, count($http->bins), 'another amount asks again');
}

function test_iyzipay_installments_one_block()
{
	foreach (array('get_order_preview.php', 'get_express_order.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(strpos($src, 'InstallmentInfo::retrieve') === false, $file . ' asks through pg_payment_iyzico_installment_table()');
		pg_assert_true(strpos($src, 'pg_payment_iyzico_installment_table(') !== false, $file . ' draws the shared table');
	}
}
