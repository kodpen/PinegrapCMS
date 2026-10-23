<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the iyzico payment drivers (includes/payments/iyzico.php) and the
 * checkout helpers submit_order.php calls (includes/fn/payments.php).
 *
 * Each request the drivers send is compared with a reference request built
 * by the statements the gateway code has always used (submit_order.php's
 * Iyzipay and Pay With Iyzico blocks, view_order.php's refund,
 * process_order_cancellation(), iyzipay_cancel_payment()), copied below as
 * legacy_*() builders. iyzico is replaced by a stand-in HTTP client that
 * records what is posted and answers with canned JSON; no network and no
 * database is reached (the 3-D Secure row goes to a recording writer).
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

if (!class_exists('PaymentIyzicoTestHttp', false)) {
	// Stands in for iyzico: answers by the end of the address, records every post.
	class PaymentIyzicoTestHttp implements \Iyzipay\HttpClient
	{
		public $answers = array();
		public $posts = array();

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

			return isset($this->answers[$path]) ? json_encode($this->answers[$path]) : '';
		}
	}
}

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

function payment_iyzico_test_http($answers = array())
{
	$http = new PaymentIyzicoTestHttp();
	$http->answers = $answers;
	\Iyzipay\ApiResource::setHttpClient($http);

	return $http;
}

function payment_iyzico_test_settings($changes = array())
{
	return array_merge(array(
		'api_key'            => 'sandbox-key',
		'secret_key'         => 'sandbox-secret',
		'base_url'           => 'https://sandbox-api.iyzipay.com',
		'locale'             => 'TR',
		'threeds'            => false,
		'installments'       => '6',
		'currency'           => 'TRY',
		'protected_currency' => '',
		'protected_rate'     => 0.0,
	), $changes);
}

function payment_iyzico_test_order()
{
	return array(
		'id'                    => '438',
		'billing_first_name'    => 'Ayşe',
		'billing_last_name'     => 'Yılmaz',
		'billing_address_1'     => 'Atatürk Cad. 12',
		'billing_address_2'     => 'D:4',
		'billing_city'          => 'Konak',
		'billing_state'         => 'İzmir',
		'billing_zip_code'      => '35210',
		'billing_country'       => 'TR',
		'billing_phone_number'  => '+905551112233',
		'billing_email_address' => 'ayse@example.com',
		'custom_field_1'        => '12345678901',
		'transaction_id'        => '24680',
		'cancellation_reason'   => 'Müşteri vazgeçti',
	);
}

function payment_iyzico_test_lines()
{
	return array(
		array('id' => '17', 'product_name' => 'KILIF-1', 'short_description' => 'Deri Kalem Kılıfı', 'price' => '3995', 'quantity' => '2'),
		array('id' => null, 'product_name' => 'NOT-1', 'short_description' => 'Defter', 'price' => '1500', 'quantity' => '1'),
		array('id' => '19', 'product_name' => 'FREE', 'short_description' => 'Hediye', 'price' => '0', 'quantity' => '1'),
	);
}

function payment_iyzico_test_payment($changes = array())
{
	return array_merge(array(
		'card_number'              => '5528790000000008',
		'expiration_month'         => '12',
		'expiration_year'          => '2030',
		'card_verification_number' => '123',
		'installment'              => 0,
		'threeds'                  => false,
		'recurring'                => false,
		'subtotal'                 => 9490,
		'discount'                 => 500,
		'lines'                    => payment_iyzico_test_lines(),
		'ship_to'                  => null,
		'customer_id'              => 7,
		'ip'                       => '203.0.113.9',
		'basket_id'                => '1023',
		'return_url'               => 'https://shop.example/pinegrap/express_order.php?mode=iyzipay_threedsecure_return&page_id=1023',
	), $changes);
}

// The order total the checkout computed: subtotal less discount plus tax and shipping.
function payment_iyzico_test_total()
{
	return 10714;
}

function payment_iyzico_test_success($fields = array())
{
	return array_merge(array('status' => 'success', 'locale' => 'tr', 'systemTime' => 1760950000000), $fields);
}

function payment_iyzico_test_without_conversation($body)
{
	unset($body['conversationId']);

	return $body;
}

/* ---------------------------------------------------------------------------
   The gateway code's requests, as submit_order.php and the others build them
   --------------------------------------------------------------------------- */

// submit_order.php, case 'Iyzipay': the CreatePaymentRequest.
function legacy_iyzico_payment_request($order, $products, $subtotal, $total, $payment_installment, $threeds, $callbackurl, $ship_to, $protected_currency, $protected_currency_exchange_rate, $register_card, $page_id, $user_id)
{
	$billing_first_name = $order['billing_first_name'];
	$billing_last_name = $order['billing_last_name'];
	$billing_address_1 = $order['billing_address_1'];
	$billing_address_2 = $order['billing_address_2'];
	$billing_city = $order['billing_city'];
	$billing_state = $order['billing_state'];
	$billing_zip_code = $order['billing_zip_code'];
	$billing_country = $order['billing_country'];
	$billing_phone_number = $order['billing_phone_number'];
	$billing_email_address = $order['billing_email_address'];
	$custom_field_1 = $order['custom_field_1'];
	$card_number = '5528790000000008';
	$expiration_month = '12';
	$expiration_year = '2030';
	$conversationid = 0;
	$user_id_output = $user_id ? $user_id : 'Guest';
	$ip = h('203.0.113.9');

	if ($custom_field_1 && is_numeric($custom_field_1)) {
		$identitynumber = substr($custom_field_1, 0, 11);
	} else {
		$identitynumber = '11111111111';
	}

	$Iyzipay_ship_name = '';
	$Iyzipay_ship_address = '';
	$Iyzipay_ship_state = '';
	$Iyzipay_ship_zip = '';
	$Iyzipay_ship_country = '';

	if ($ship_to) {
		$row = $ship_to;
		$Iyzipay_ship_name = $row['first_name'] . ' ' . $row['last_name'];
		$Iyzipay_ship_address = $row['address_1'] . ' ' . $row['address_2'] . ' ' . $row['city'];
		$Iyzipay_ship_state = $row['state'];
		$Iyzipay_ship_zip = $row['zip_code'];
		$Iyzipay_ship_country = $row['country'];
	}

	if ($Iyzipay_ship_name === '' || $Iyzipay_ship_address === '') {
		$Iyzipay_ship_name    = trim($billing_first_name . ' ' . $billing_last_name);
		$Iyzipay_ship_address = trim($billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city);
		$Iyzipay_ship_state   = $billing_state;
		$Iyzipay_ship_zip     = $billing_zip_code;
		$Iyzipay_ship_country = $billing_country;
	}

	$currency_code = 'TRY';
	$c_subtotal = $subtotal;
	$c_total = $total;

	if ($protected_currency !== '') {
		$currency_code = $protected_currency;

		if ($subtotal > 0) {
			$c_subtotal = (int)round(($subtotal / 100.0) * $protected_currency_exchange_rate * 100);
		}
		if ($total > 0) {
			$c_total = (int)round(($total / 100.0) * $protected_currency_exchange_rate * 100);
		}
	}

	$request = new \Iyzipay\Request\CreatePaymentRequest();
	$request->setConversationId($conversationid);
	$request->setLocale('TR');
	$request->setPrice(sprintf("%01.2lf", $c_subtotal / 100));
	$request->setPaidPrice(sprintf("%01.2lf", $c_total / 100));
	$request->setCurrency($currency_code);
	$request->setInstallment($payment_installment);
	$request->setBasketId((string)$page_id);
	$request->setPaymentChannel(\Iyzipay\Model\PaymentChannel::WEB);
	$request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);

	if ($threeds) {
		$request->setCallbackUrl($callbackurl);
	}

	$paymentCard = new \Iyzipay\Model\PaymentCard();
	$paymentCard->setCardHolderName(trim($billing_first_name . ' ' . $billing_last_name));
	$paymentCard->setCardNumber($card_number);
	$paymentCard->setExpireMonth($expiration_month);
	$paymentCard->setExpireYear($expiration_year);
	$paymentCard->setCvc('123');
	$paymentCard->setRegisterCard($register_card);
	$request->setPaymentCard($paymentCard);

	$buyer = new \Iyzipay\Model\Buyer();
	$buyer->setId($user_id_output);
	$buyer->setName($billing_first_name);
	$buyer->setSurname($billing_last_name);
	$buyer->setGsmNumber($billing_phone_number);
	$buyer->setEmail($billing_email_address);
	$buyer->setIdentityNumber($identitynumber);
	$buyer->setRegistrationAddress(trim($billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city));
	$buyer->setIp($ip);
	$buyer->setCity($billing_state);
	$buyer->setCountry($billing_country);
	$buyer->setZipCode($billing_zip_code);
	$request->setBuyer($buyer);

	$shippingAddress = new \Iyzipay\Model\Address();
	$shippingAddress->setContactName($Iyzipay_ship_name);
	$shippingAddress->setCity($Iyzipay_ship_state);
	$shippingAddress->setCountry($Iyzipay_ship_country);
	$shippingAddress->setAddress($Iyzipay_ship_address);
	$shippingAddress->setZipCode($Iyzipay_ship_zip);
	$request->setShippingAddress($shippingAddress);

	$billingAddressObj = new \Iyzipay\Model\Address();
	$billingAddressObj->setContactName(trim($billing_first_name . ' ' . $billing_last_name));
	$billingAddressObj->setCity($billing_state);
	$billingAddressObj->setCountry($billing_country);
	$billingAddressObj->setAddress(trim($billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city));
	$billingAddressObj->setZipCode($billing_zip_code);
	$request->setBillingAddress($billingAddressObj);

	$basketItems = array();
	foreach ($products as $i => $product) {
		$priceCents = isset($product['price']) && is_numeric($product['price']) ? (int)$product['price'] : 0;
		$qty = isset($product['quantity']) ? (int)$product['quantity'] : 1;

		if ($priceCents <= 0) { continue; }

		if ($protected_currency_exchange_rate > 0) {
			$priceCents = (int)round(($priceCents / 100.0) * $protected_currency_exchange_rate * 100);
		}
		$lineCents = $priceCents * $qty;

		$item = new \Iyzipay\Model\BasketItem();
		$item->setId((string)(isset($product['id']) ? $product['id'] : $product['product_name']));
		$item->setName(($qty > 1 ? $qty . ' Qty/Adet - ' : '') . $product['short_description']);
		$item->setPrice(sprintf("%01.2lf", $lineCents / 100));
		$item->setItemType(!empty($product['shippable']) ? \Iyzipay\Model\BasketItemType::PHYSICAL : \Iyzipay\Model\BasketItemType::VIRTUAL);
		$item->setCategory1($product['short_description']);

		$basketItems[] = $item;
	}
	$request->setBasketItems($basketItems);

	return payment_iyzico_test_without_conversation(json_decode($request->toJsonString(), true));
}

// submit_order.php, case 'Iyzipay': the RetrieveInstallmentInfoRequest.
function legacy_iyzico_installment_request($total)
{
	$reqInst = new \Iyzipay\Request\RetrieveInstallmentInfoRequest();
	$reqInst->setLocale('TR');
	$reqInst->setConversationId(0);
	$reqInst->setBinNumber(substr('5528790000000008', 0, 6));
	$reqInst->setPrice(sprintf("%01.2lf", $total / 100));

	return payment_iyzico_test_without_conversation(json_decode($reqInst->toJsonString(), true));
}

// submit_order.php, case 'Pay With Iyzico': the initialize request.
function legacy_pay_with_iyzico_request($order, $products, $subtotal, $total, $callbackUrl, $page_id, $user_id)
{
	$billing_first_name = $order['billing_first_name'];
	$billing_last_name = $order['billing_last_name'];
	$billing_address_1 = $order['billing_address_1'];
	$billing_address_2 = $order['billing_address_2'];
	$billing_city = $order['billing_city'];
	$billing_state = $order['billing_state'];
	$billing_zip_code = $order['billing_zip_code'];
	$billing_country = $order['billing_country'];
	$billing_phone_number = $order['billing_phone_number'];
	$billing_email_address = $order['billing_email_address'];
	$custom_field_1 = $order['custom_field_1'];
	$conversationid = 0;
	$user_id_output = $user_id ? $user_id : 'Guest';

	if ($custom_field_1 && is_numeric($custom_field_1)) {
		$identitynumber = substr($custom_field_1, 0, 11);
	} else {
		$identitynumber = '11111111111';
	}

	$request = new \Iyzipay\Request\CreatePayWithIyzicoInitializeRequest();
	$request->setLocale('TR');
	$request->setConversationId($conversationid);
	$request->setPrice(sprintf("%01.2lf", $subtotal / 100));
	$request->setPaidPrice(sprintf("%01.2lf", $total / 100));
	$request->setCurrency('TRY');
	$request->setBasketId((string)$page_id);
	$request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);

	$buyer = new \Iyzipay\Model\Buyer();
	$buyer->setId($user_id_output);
	$buyer->setName($billing_first_name);
	$buyer->setSurname($billing_last_name);
	$buyer->setGsmNumber($billing_phone_number);
	$buyer->setEmail($billing_email_address);
	$buyer->setIdentityNumber($identitynumber);
	$buyer->setRegistrationAddress(trim($billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city));
	$buyer->setIp(h('203.0.113.9'));
	$buyer->setCity($billing_state);
	$buyer->setCountry($billing_country);
	$buyer->setZipCode($billing_zip_code);
	$request->setBuyer($buyer);

	$billingAddressObj = new \Iyzipay\Model\Address();
	$billingAddressObj->setContactName(trim($billing_first_name . ' ' . $billing_last_name));
	$billingAddressObj->setCity($billing_state);
	$billingAddressObj->setCountry($billing_country);
	$billingAddressObj->setAddress(trim($billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city));
	$billingAddressObj->setZipCode($billing_zip_code);
	$request->setBillingAddress($billingAddressObj);

	$shippingAddress = new \Iyzipay\Model\Address();
	$shippingAddress->setContactName(trim($billing_first_name . ' ' . $billing_last_name));
	$shippingAddress->setCity($billing_state);
	$shippingAddress->setCountry($billing_country);
	$shippingAddress->setAddress(trim($billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city));
	$shippingAddress->setZipCode($billing_zip_code);
	$request->setShippingAddress($shippingAddress);

	$basketItems = array();
	foreach ($products as $product) {
		$priceCents = isset($product['price']) && is_numeric($product['price']) ? (int)$product['price'] : 0;
		$qty        = isset($product['quantity']) ? (int)$product['quantity'] : 1;
		if ($priceCents <= 0) { continue; }
		$lineCents = $priceCents * $qty;

		$item = new \Iyzipay\Model\BasketItem();
		$item->setId((string)(isset($product['id']) ? $product['id'] : $product['product_name']));
		$item->setName(($qty > 1 ? $qty . ' Qty/Adet - ' : '') . $product['short_description']);
		$item->setPrice(sprintf("%01.2lf", $lineCents / 100));
		$item->setItemType(!empty($product['shippable']) ? \Iyzipay\Model\BasketItemType::PHYSICAL : \Iyzipay\Model\BasketItemType::VIRTUAL);
		$item->setCategory1($product['short_description']);
		$basketItems[] = $item;
	}
	$request->setBasketItems($basketItems);
	$request->setCallbackUrl($callbackUrl);

	return payment_iyzico_test_without_conversation(json_decode($request->toJsonString(), true));
}

// submit_order.php, Pay With Iyzico return: the retrieve request.
function legacy_pay_with_iyzico_retrieve_request($conversationid, $pwi_token)
{
	$retrieveRequest = new \Iyzipay\Request\RetrievePayWithIyzicoRequest();
	$retrieveRequest->setLocale('TR');
	$retrieveRequest->setConversationId($conversationid);
	$retrieveRequest->setToken($pwi_token);

	return json_decode($retrieveRequest->toJsonString(), true);
}

// view_order.php, submit_iyzico_refund: the AmountBaseRefundRequest.
function legacy_iyzico_refund_request($transaction_id, $refund_amount)
{
	$refund_request = new \Iyzipay\Request\AmountBaseRefundRequest();
	$refund_request->setLocale(\Iyzipay\Model\Locale::TR);
	$refund_request->setConversationId((string)0);
	$refund_request->setPaymentId($transaction_id);
	$refund_request->setPrice($refund_amount);
	$refund_request->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

	return payment_iyzico_test_without_conversation(json_decode($refund_request->toJsonString(), true));
}

// process_order_cancellation(): the CreateCancelRequest.
function legacy_iyzico_cancel_request($order, $reason_clean)
{
	$request = new \Iyzipay\Request\CreateCancelRequest();
	$request->setLocale(\Iyzipay\Model\Locale::TR);
	$request->setConversationId('x');
	$request->setPaymentId((string) $order['transaction_id']);
	$request->setIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');

	if ($reason_clean !== '') {
		$request->setDescription(mb_substr($reason_clean, 0, 240));
	}

	return payment_iyzico_test_without_conversation(json_decode($request->toJsonString(), true));
}

// iyzipay_cancel_payment(): the CreateCancelRequest.
function legacy_iyzico_void_request($payment_id, $conversation_id, $reason)
{
	$request = new \Iyzipay\Request\CreateCancelRequest();
	$request->setLocale(\Iyzipay\Model\Locale::TR);
	$request->setConversationId((string) $conversation_id);
	$request->setPaymentId($payment_id);
	$request->setIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
	$request->setDescription(mb_substr($reason, 0, 240));

	return json_decode($request->toJsonString(), true);
}

function payment_iyzico_test_authorize($settings, $payment, &$kept = null, $total = null)
{
	$kept = array();
	$keep = function ($row) use (&$kept) {
		$kept[] = $row;
	};

	return pg_payment_answer('authorize', pg_payment_iyzico_authorize_with(
		payment_iyzico_test_order(),
		($total === null) ? payment_iyzico_test_total() : $total,
		$payment,
		$settings,
		$keep));
}

/* ---------------------------------------------------------------------------
   Card payment
   --------------------------------------------------------------------------- */

function test_payment_iyzico_direct_payment_sends_the_legacy_request()
{
	$http = payment_iyzico_test_http(array('/payment/auth' => payment_iyzico_test_success(array(
		'paymentId' => '24680', 'fraudStatus' => 1, 'cardAssociation' => 'MASTER_CARD', 'installment' => 1, 'paidPrice' => 107.14))));

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment(), $kept);

	pg_assert_same(1, count($http->posts), 'no instalment lookup without a choice');
	pg_assert_same('/payment/auth', $http->posts[0]['path']);
	pg_assert_same(
		legacy_iyzico_payment_request(payment_iyzico_test_order(), payment_iyzico_test_lines(), 9490, 10714, 1, false, '', null, '', 0.0, 0, '1023', 7),
		payment_iyzico_test_without_conversation($http->posts[0]['body']));
	pg_assert_true(preg_match('/^[0-9]{9}$/', (string) $http->posts[0]['body']['conversationId']) === 1, 'nine digit conversation id');

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('24680', $answer['transaction_id']);
	pg_assert_same(10714, $answer['amount']);
	pg_assert_same(1, $answer['installments']);
	pg_assert_same('MASTER_CARD', $answer['card_brand']);
	pg_assert_same(array(), $answer['log']);
	pg_assert_same(array(), $kept, 'no 3-D Secure row');

	pg_assert_same(
		array('total' => 10714, 'installment_charge' => 0, 'total_without_installment_charge' => 0, 'payment_installment' => 1),
		pg_payment_checkout_totals($answer, 10714));
}

function test_payment_iyzico_guest_recurring_and_placeholder_identity()
{
	$http = payment_iyzico_test_http(array('/payment/auth' => payment_iyzico_test_success(array('paymentId' => '1', 'fraudStatus' => 1))));
	$order = payment_iyzico_test_order();
	$order['custom_field_1'] = 'not a number';

	pg_payment_iyzico_authorize_with($order, 10714, payment_iyzico_test_payment(array('customer_id' => '', 'recurring' => true)), payment_iyzico_test_settings(), function () {});

	pg_assert_same(
		legacy_iyzico_payment_request($order, payment_iyzico_test_lines(), 9490, 10714, 1, false, '', null, '', 0.0, 1, '1023', ''),
		payment_iyzico_test_without_conversation($http->posts[0]['body']));
	pg_assert_same('Guest', $http->posts[0]['body']['buyer']['id']);
	pg_assert_same('11111111111', $http->posts[0]['body']['buyer']['identityNumber']);
}

function test_payment_iyzico_single_delivery_address_is_sent()
{
	$ship_to = array('first_name' => 'Mehmet', 'last_name' => 'Kaya', 'address_1' => 'Kordon 5', 'address_2' => '', 'city' => 'Alsancak', 'state' => 'İzmir', 'zip_code' => '35220', 'country' => 'TR');
	$http = payment_iyzico_test_http(array('/payment/auth' => payment_iyzico_test_success(array('paymentId' => '1', 'fraudStatus' => 1))));

	pg_payment_iyzico_authorize_with(payment_iyzico_test_order(), 10714, payment_iyzico_test_payment(array('ship_to' => $ship_to)), payment_iyzico_test_settings(), function () {});

	pg_assert_same(
		legacy_iyzico_payment_request(payment_iyzico_test_order(), payment_iyzico_test_lines(), 9490, 10714, 1, false, '', $ship_to, '', 0.0, 0, '1023', 7),
		payment_iyzico_test_without_conversation($http->posts[0]['body']));
	pg_assert_same('Kordon 5  Alsancak', $http->posts[0]['body']['shippingAddress']['address']);
}

function test_payment_iyzico_installment_charge_is_added()
{
	$installment_prices = array('installmentDetails' => array(array('installmentPrices' => array(
		array('installmentNumber' => 1, 'totalPrice' => '107.14'),
		array('installmentNumber' => 3, 'totalPrice' => '110.35'),
	))));
	$http = payment_iyzico_test_http(array(
		'/payment/iyzipos/installment' => payment_iyzico_test_success($installment_prices),
		'/payment/auth'                => payment_iyzico_test_success(array('paymentId' => '555', 'fraudStatus' => 1, 'installment' => 3)),
	));

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment(array('installment' => 3)));

	pg_assert_same('/payment/iyzipos/installment', $http->posts[0]['path']);
	pg_assert_same(legacy_iyzico_installment_request(10714), payment_iyzico_test_without_conversation($http->posts[0]['body']), 'quoted at the order total');
	pg_assert_same($http->posts[0]['body']['conversationId'], $http->posts[1]['body']['conversationId'], 'one conversation');
	pg_assert_same(
		legacy_iyzico_payment_request(payment_iyzico_test_order(), payment_iyzico_test_lines(), 9490, 11035, 3, false, '', null, '', 0.0, 0, '1023', 7),
		payment_iyzico_test_without_conversation($http->posts[1]['body']));

	pg_assert_same(11035, $answer['amount']);
	pg_assert_same(3, $answer['installments']);
	pg_assert_same(
		array('total' => 11035, 'installment_charge' => 321, 'total_without_installment_charge' => 10714, 'payment_installment' => 3),
		pg_payment_checkout_totals($answer, 10714));
}

function test_payment_iyzico_installment_without_a_higher_price_adds_nothing()
{
	$http = payment_iyzico_test_http(array(
		'/payment/iyzipos/installment' => payment_iyzico_test_success(array('installmentDetails' => array(array('installmentPrices' => array(
			array('installmentNumber' => 2, 'totalPrice' => '107.14')))))),
		'/payment/auth'                => payment_iyzico_test_success(array('paymentId' => '1', 'fraudStatus' => 1)),
	));

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment(array('installment' => 2)));

	pg_assert_same(10714, $answer['amount']);
	pg_assert_same(2, $answer['installments']);
	pg_assert_same('2', (string) $http->posts[1]['body']['installment']);
	pg_assert_same(
		array('total' => 10714, 'installment_charge' => 0, 'total_without_installment_charge' => 0, 'payment_installment' => 2),
		pg_payment_checkout_totals($answer, 10714));
}

// Where the quoted instalment charge would leave a rate's total one the VAT,
// taken on the whole, never reaches, the card is charged the fewest kurus
// less (pg_order_installment_charge()): 57.92 at 20% with the bank's 59.97 is
// charged 59.96, and the 3-D Secure row and the order keep 2.04
// (pg_payment_threeds_state_totals() on the bank's return).
function test_payment_iyzico_installment_charge_keeps_the_rate_reachable()
{
	$quote = array('installmentDetails' => array(array('installmentPrices' => array(
		array('installmentNumber' => 3, 'totalPrice' => '59.97'),
	))));
	$settle = array(
		'subtotal' => 3995,
		'discount' => 0,
		'installment' => 3,
		'settle_rates' => true,
		'charged_lines' => array(442 => array('amount' => 3995, 'rate' => '20.000', 'tax' => 799)),
		'charges' => array('shipping' => 998, 'surcharge' => 0),
	);

	$http = payment_iyzico_test_http(array(
		'/payment/iyzipos/installment' => payment_iyzico_test_success($quote),
		'/payment/auth'                => payment_iyzico_test_success(array('paymentId' => '777', 'fraudStatus' => 1, 'installment' => 3)),
	));
	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment($settle), $kept, 5792);

	pg_assert_same('59.96', $http->posts[1]['body']['paidPrice']);
	pg_assert_same(5996, $answer['amount']);
	pg_assert_same(
		array('total' => 5996, 'installment_charge' => 204, 'total_without_installment_charge' => 5792, 'payment_installment' => 3),
		pg_payment_checkout_totals($answer, 5792));

	// The 3-D Secure start keeps the same figure for the return to check.
	$http = payment_iyzico_test_http(array(
		'/payment/iyzipos/installment'  => payment_iyzico_test_success($quote),
		'/payment/3dsecure/initialize' => payment_iyzico_test_success(array('threeDSHtmlContent' => base64_encode('<form></form>'))),
	));
	payment_iyzico_test_authorize(payment_iyzico_test_settings(array('threeds' => true)), payment_iyzico_test_payment($settle), $kept, 5792);

	pg_assert_same('59.96', $http->posts[1]['body']['paidPrice']);
	pg_assert_same(204, $kept[0]['installment_charge_cents']);
	pg_assert_same(5792, $kept[0]['base_total_cents']);
	pg_assert_same(
		array('total' => 5996, 'installment_charge' => 204, 'total_without_installment_charge' => 5792),
		pg_payment_threeds_state_totals($kept[0]),
		'the bank\'s return records the same figures');

	// Where the checkout does not settle its rates, the bank's figure stands.
	$http = payment_iyzico_test_http(array(
		'/payment/iyzipos/installment' => payment_iyzico_test_success($quote),
		'/payment/auth'                => payment_iyzico_test_success(array('paymentId' => '778', 'fraudStatus' => 1, 'installment' => 3)),
	));
	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment(array_merge($settle, array('settle_rates' => false))), $kept, 5792);

	pg_assert_same('59.97', $http->posts[1]['body']['paidPrice']);
	pg_assert_same(5997, $answer['amount']);
}

function test_payment_iyzico_installment_above_the_limit_is_refused_before_iyzico()
{
	$http = payment_iyzico_test_http();

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(array('installments' => '6')), payment_iyzico_test_payment(array('installment' => 9)));

	pg_assert_false($answer['success']);
	pg_assert_same(lang('Payment Installment cannot be bigger than accepted Installment!'), $answer['error']);
	pg_assert_same(array(), $http->posts);
}

function test_payment_iyzico_protected_currency_converts_every_amount()
{
	$http = payment_iyzico_test_http(array('/payment/auth' => payment_iyzico_test_success(array('paymentId' => '1', 'fraudStatus' => 1))));

	payment_iyzico_test_authorize(payment_iyzico_test_settings(array('protected_currency' => 'USD', 'protected_rate' => 0.0297)), payment_iyzico_test_payment());

	pg_assert_same(
		legacy_iyzico_payment_request(payment_iyzico_test_order(), payment_iyzico_test_lines(), 9490, 10714, 1, false, '', null, 'USD', 0.0297, 0, '1023', 7),
		payment_iyzico_test_without_conversation($http->posts[0]['body']));
	pg_assert_same('USD', $http->posts[0]['body']['currency']);
	pg_assert_same('3.18', $http->posts[0]['body']['paidPrice']);
}

function test_payment_iyzico_threeds_start_keeps_the_row_the_return_reads()
{
	$http = payment_iyzico_test_http(array(
		'/payment/iyzipos/installment'  => payment_iyzico_test_success(array('installmentDetails' => array(array('installmentPrices' => array(
			array('installmentNumber' => 3, 'totalPrice' => '110.35')))))),
		'/payment/3dsecure/initialize' => payment_iyzico_test_success(array('threeDSHtmlContent' => base64_encode('<form id="bank"></form>'))),
	));

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(array('threeds' => true)), payment_iyzico_test_payment(array('installment' => 3)), $kept);

	pg_assert_same('/payment/3dsecure/initialize', $http->posts[1]['path']);
	pg_assert_same(
		legacy_iyzico_payment_request(payment_iyzico_test_order(), payment_iyzico_test_lines(), 9490, 11035, 3, true, payment_iyzico_test_payment()['return_url'], null, '', 0.0, 0, '1023', 7),
		payment_iyzico_test_without_conversation($http->posts[1]['body']));

	pg_assert_same(1, count($kept));
	pg_assert_same(array(
		'conversation_id'          => $http->posts[1]['body']['conversationId'],
		'order_id'                 => 438,
		'subtotal_cents'           => 9490,
		'discount_cents'           => 500,
		'installment_charge_cents' => 321,
		'base_total_cents'         => 10714,
	), $kept[0]);

	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('<form id="bank"></form>', $answer['html']);
	pg_assert_same('', $answer['transaction_id']);
	pg_assert_same(array(lang(array('string' => 'The 3D Security process has been started by {var:1} {var:2} ({var:3}).', 'vars' => array('Ayşe', 'Yılmaz', 'ayse@example.com')))), $answer['log']);
}

function test_payment_iyzico_threeds_asked_by_the_visitor()
{
	$http = payment_iyzico_test_http(array('/payment/3dsecure/initialize' => payment_iyzico_test_success(array('threeDSHtmlContent' => base64_encode('<form></form>')))));

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(array('threeds' => false)), payment_iyzico_test_payment(array('threeds' => true)), $kept);

	pg_assert_same('/payment/3dsecure/initialize', $http->posts[0]['path']);
	pg_assert_same(10714, $kept[0]['base_total_cents']);
	pg_assert_same(0, $kept[0]['installment_charge_cents']);
	pg_assert_same('redirect', $answer['status']);
}

function test_payment_iyzico_threeds_start_failure_names_iyzicos_reason()
{
	payment_iyzico_test_http(array('/payment/3dsecure/initialize' => array('status' => 'failure', 'errorCode' => '12', 'errorMessage' => 'Kart numarası geçersizdir')));

	$answer = payment_iyzico_test_authorize(payment_iyzico_test_settings(array('threeds' => true)), payment_iyzico_test_payment(), $kept);

	pg_assert_false($answer['success']);
	pg_assert_same(lang('Payment could not be initiated. Please try again.') . ' (Kart numarası geçersizdir)', $answer['error']);
	pg_assert_same(1, count($answer['log']));
	pg_assert_contains('Iyzipay 3DS init error: status=failure, code=12, message=Kart numarası geçersizdir, raw={', $answer['log'][0]);
	pg_assert_same(1, count($kept), 'the row is written before iyzico is asked, as before');
}

function test_payment_iyzico_payment_failure_and_fraud()
{
	payment_iyzico_test_http(array('/payment/auth' => array('status' => 'failure', 'errorCode' => '10051', 'errorMessage' => 'Yetersiz bakiye')));
	$failed = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment());

	pg_assert_false($failed['success']);
	pg_assert_same(lang('Payment failed. Please try again.'), $failed['error']);
	pg_assert_same(array('Iyzipay payment error: code=10051, message=Yetersiz bakiye'), $failed['log']);

	payment_iyzico_test_http(array('/payment/auth' => payment_iyzico_test_success(array('paymentId' => '77', 'fraudStatus' => -1))));
	$rejected = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment());

	pg_assert_false($rejected['success']);
	pg_assert_same(lang('Payment was found to be risky and rejected.'), $rejected['error']);
	pg_assert_same(array('Iyzipay fraud rejected: paymentId=77'), $rejected['log']);

	payment_iyzico_test_http(array('/payment/auth' => payment_iyzico_test_success(array('paymentId' => '78', 'fraudStatus' => 0))));
	$review = payment_iyzico_test_authorize(payment_iyzico_test_settings(), payment_iyzico_test_payment());

	pg_assert_true($review['success'], 'a payment under review completes the order, as before');
	pg_assert_same('paid', $review['status']);
	pg_assert_same(array('Iyzipay fraud pending review: paymentId=78'), $review['log']);
}

/* ---------------------------------------------------------------------------
   Pay With Iyzico
   --------------------------------------------------------------------------- */

function payment_iyzico_test_pwi_state($changes = array())
{
	return array_merge(array('token' => 'tok-1', 'conversation_id' => 123456789, 'order_id' => 438, 'base_total_cents' => 10714), $changes);
}

function test_payment_iyzico_pay_with_iyzico_start_sends_the_legacy_request()
{
	$http = payment_iyzico_test_http(array('/payment/pay-with-iyzico/initialize' => payment_iyzico_test_success(array(
		'token' => 'tok-1', 'payWithIyzicoPageUrl' => 'https://sandbox-ode.iyzico.com/?token=tok-1&lang=tr'))));
	$payment = payment_iyzico_test_payment(array('return_url' => 'https://shop.example/pinegrap/express_order.php?mode=pay_with_iyzico_return&page_id=1023'));

	$answer = pg_payment_answer('authorize', pg_payment_pay_with_iyzico_authorize_with(payment_iyzico_test_order(), 10714, $payment, payment_iyzico_test_settings(array('protected_currency' => 'USD', 'protected_rate' => 0.03))));

	pg_assert_same(
		legacy_pay_with_iyzico_request(payment_iyzico_test_order(), payment_iyzico_test_lines(), 9490, 10714, $payment['return_url'], '1023', 7),
		payment_iyzico_test_without_conversation($http->posts[0]['body']),
		'base currency and unconverted prices, as before');

	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('https://sandbox-ode.iyzico.com/?token=tok-1&lang=tr', $answer['redirect_url']);
	pg_assert_same(array('token' => 'tok-1', 'conversation_id' => $http->posts[0]['body']['conversationId'], 'order_id' => 438, 'base_total_cents' => 10714), $answer['state']);
	pg_assert_same(array(lang(array('string' => 'Pay with Iyzico has been started by {var:1} {var:2} ({var:3}).', 'vars' => array('Ayşe', 'Yılmaz', 'ayse@example.com')))), $answer['log']);
}

function test_payment_iyzico_pay_with_iyzico_start_failure()
{
	payment_iyzico_test_http(array('/payment/pay-with-iyzico/initialize' => array('status' => 'failure', 'errorCode' => '1', 'errorMessage' => '')));

	$answer = pg_payment_answer('authorize', pg_payment_pay_with_iyzico_authorize_with(payment_iyzico_test_order(), 10714, payment_iyzico_test_payment(), payment_iyzico_test_settings()));

	pg_assert_false($answer['success']);
	pg_assert_same(lang('Payment could not be initiated. Please try again.'), $answer['error'], 'an empty message adds nothing');
	pg_assert_same(array(), $answer['state']);
	pg_assert_contains('Pay with Iyzico init error: code=1, message=, raw=', $answer['log'][0]);
}

function test_payment_iyzico_pay_with_iyzico_return_needs_its_token_and_order()
{
	$cases = array(
		'no token'      => array(array(), payment_iyzico_test_pwi_state(), payment_iyzico_test_order()),
		'another token' => array(array('token' => 'tok-2'), payment_iyzico_test_pwi_state(), payment_iyzico_test_order()),
		'a token array' => array(array('token' => array('tok-1')), payment_iyzico_test_pwi_state(), payment_iyzico_test_order()),
		'no state'      => array(array('token' => 'tok-1'), array(), payment_iyzico_test_order()),
		'another order' => array(array('token' => 'tok-1'), payment_iyzico_test_pwi_state(array('order_id' => 437)), payment_iyzico_test_order()),
		'no order'      => array(array('token' => 'tok-1'), payment_iyzico_test_pwi_state(), array()),
	);

	foreach ($cases as $label => $case) {
		$http = payment_iyzico_test_http();
		$answer = pg_payment_answer('complete', pg_payment_pay_with_iyzico_complete_with($case[2], 10714, $case[0], $case[1], payment_iyzico_test_settings()));

		pg_assert_false($answer['success'], $label);
		pg_assert_same(lang('Session could not be verified. Please try again.'), $answer['error'], $label);
		pg_assert_same($case[1], $answer['state'], $label . ': the wait goes on');
		pg_assert_same(array(), $http->posts, $label . ': iyzico is not asked');
		pg_assert_contains('Pay with Iyzico callback: token or order mismatch (order_id=', $answer['log'][0], $label);
	}
}

function test_payment_iyzico_pay_with_iyzico_return_takes_the_payment()
{
	$http = payment_iyzico_test_http(array('/payment/iyzipos/checkoutform/auth/ecom/detail' => payment_iyzico_test_success(array(
		'paymentId' => '9001', 'installment' => 1, 'paidPrice' => 107.14))));

	$answer = pg_payment_answer('complete', pg_payment_pay_with_iyzico_complete_with(payment_iyzico_test_order(), 10714, array('token' => 'tok-1'), payment_iyzico_test_pwi_state(), payment_iyzico_test_settings()));

	pg_assert_same(legacy_pay_with_iyzico_retrieve_request(123456789, 'tok-1'), $http->posts[0]['body']);
	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('9001', $answer['transaction_id']);
	pg_assert_same(array(), $answer['state'], 'the wait ends');
	pg_assert_same(
		array('total' => 10714, 'installment_charge' => 0, 'total_without_installment_charge' => 0, 'payment_installment' => 1),
		pg_payment_checkout_totals($answer, 10714));
	pg_assert_same(array(lang(array('string' => 'Pay with Iyzico has been completed by {var:1} {var:2} ({var:3}).', 'vars' => array('Ayşe', 'Yılmaz', 'ayse@example.com')))), $answer['log']);
}

function test_payment_iyzico_pay_with_iyzico_installment_charge()
{
	payment_iyzico_test_http(array('/payment/iyzipos/checkoutform/auth/ecom/detail' => payment_iyzico_test_success(array(
		'paymentId' => '9002', 'installment' => 6, 'paidPrice' => 115.99))));

	$answer = pg_payment_answer('complete', pg_payment_pay_with_iyzico_complete_with(payment_iyzico_test_order(), 10714, array('token' => 'tok-1'), payment_iyzico_test_pwi_state(), payment_iyzico_test_settings()));

	pg_assert_true($answer['success']);
	pg_assert_same(
		array('total' => 11599, 'installment_charge' => 885, 'total_without_installment_charge' => 10714, 'payment_installment' => 6),
		pg_payment_checkout_totals($answer, 10714));
}

function test_payment_iyzico_pay_with_iyzico_return_cancels_a_payment_for_another_total()
{
	$cases = array(
		'cart changed'             => array(10914, 107.14, 1),
		'paid less'                => array(10714, 100.00, 1),
		'single payment, paid more' => array(10714, 110.00, 1),
		'instalments, paid less'   => array(10714, 100.00, 3),
	);

	foreach ($cases as $label => $case) {
		$http = payment_iyzico_test_http(array(
			'/payment/iyzipos/checkoutform/auth/ecom/detail' => payment_iyzico_test_success(array('paymentId' => '9003', 'installment' => $case[2], 'paidPrice' => $case[1])),
			'/payment/cancel'                                => payment_iyzico_test_success(array('paymentId' => '9003')),
		));

		$answer = pg_payment_answer('complete', pg_payment_pay_with_iyzico_complete_with(payment_iyzico_test_order(), $case[0], array('token' => 'tok-1'), payment_iyzico_test_pwi_state(), payment_iyzico_test_settings()));

		pg_assert_false($answer['success'], $label);
		pg_assert_same(lang('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.'), $answer['error'], $label);
		pg_assert_same('/payment/cancel', $http->posts[1]['path'], $label);
		pg_assert_same(legacy_iyzico_void_request('9003', 123456789, 'Order total changed during Pay with Iyzico checkout'), $http->posts[1]['body'], $label);
		pg_assert_contains('Pay with Iyzico amount mismatch: paymentId=9003, order_id=438', $answer['log'][0], $label);
		pg_assert_same('Iyzipay payment 9003 was cancelled: Order total changed during Pay with Iyzico checkout.', $answer['log'][1], $label);
	}
}

function test_payment_iyzico_pay_with_iyzico_refused_cancel_asks_for_a_manual_refund()
{
	payment_iyzico_test_http(array(
		'/payment/iyzipos/checkoutform/auth/ecom/detail' => payment_iyzico_test_success(array('paymentId' => '9004', 'installment' => 1, 'paidPrice' => 99.00)),
		'/payment/cancel'                                => array('status' => 'failure', 'errorMessage' => 'İptal süresi geçti'),
	));

	$answer = pg_payment_answer('complete', pg_payment_pay_with_iyzico_complete_with(payment_iyzico_test_order(), 10714, array('token' => 'tok-1'), payment_iyzico_test_pwi_state(), payment_iyzico_test_settings()));

	pg_assert_same('Iyzipay payment 9004 could not be cancelled (Order total changed during Pay with Iyzico checkout). MANUAL REFUND REQUIRED. Gateway message: İptal süresi geçti', $answer['log'][1]);
}

function test_payment_iyzico_pay_with_iyzico_unverified_payment()
{
	payment_iyzico_test_http(array('/payment/iyzipos/checkoutform/auth/ecom/detail' => array('status' => 'failure', 'errorCode' => '5', 'errorMessage' => 'Token bulunamadı')));

	$answer = pg_payment_answer('complete', pg_payment_pay_with_iyzico_complete_with(payment_iyzico_test_order(), 10714, array('token' => 'tok-1'), payment_iyzico_test_pwi_state(), payment_iyzico_test_settings()));

	pg_assert_false($answer['success']);
	pg_assert_same(lang('Payment could not be verified. Please try again.'), $answer['error']);
	pg_assert_same(array(), $answer['state'], 'asked once, the wait ends as before');
	pg_assert_same(array('Pay with Iyzico status!=success: code=5, message=Token bulunamadı'), $answer['log']);
}

/* ---------------------------------------------------------------------------
   Refund and cancel
   --------------------------------------------------------------------------- */

function test_payment_iyzico_refund_sends_the_legacy_amount_base_refund()
{
	foreach (array(1250 => 12.5, 1200 => 12.0, 1999 => 19.99, 10714 => 107.14) as $hundredths => $legacy_amount) {
		$http = payment_iyzico_test_http(array('/v2/payment/refund' => payment_iyzico_test_success(array('paymentId' => 'r-' . $hundredths))));

		$answer = pg_payment_answer('refund', pg_payment_iyzico_refund_with(payment_iyzico_test_order(), $hundredths, payment_iyzico_test_settings()));

		pg_assert_same('/v2/payment/refund', $http->posts[0]['path']);
		pg_assert_same(legacy_iyzico_refund_request('24680', $legacy_amount), payment_iyzico_test_without_conversation($http->posts[0]['body']), (string) $hundredths);
		pg_assert_true($answer['success']);
		pg_assert_same('refunded', $answer['status']);
		pg_assert_same($hundredths, $answer['amount']);
		pg_assert_same('r-' . $hundredths, $answer['refund_reference']);
	}

	payment_iyzico_test_http(array('/v2/payment/refund' => array('status' => 'failure', 'errorMessage' => 'İade tutarı fazla')));
	$refused = pg_payment_answer('refund', pg_payment_iyzico_refund_with(payment_iyzico_test_order(), 99999, payment_iyzico_test_settings()));

	pg_assert_false($refused['success']);
	pg_assert_same('İade tutarı fazla', $refused['error']);
}

function test_payment_iyzico_cancel_sends_the_legacy_cancel()
{
	$http = payment_iyzico_test_http(array('/payment/cancel' => payment_iyzico_test_success(array('paymentId' => ''))));

	$answer = pg_payment_answer('cancel', pg_payment_iyzico_cancel_with(payment_iyzico_test_order(), '24680', payment_iyzico_test_settings()));

	pg_assert_same(legacy_iyzico_cancel_request(payment_iyzico_test_order(), 'Müşteri vazgeçti'), payment_iyzico_test_without_conversation($http->posts[0]['body']));
	pg_assert_true(preg_match('/^438-[0-9]+$/', $http->posts[0]['body']['conversationId']) === 1, 'order id and time');
	pg_assert_true($answer['success']);
	pg_assert_same('cancelled', $answer['status']);
	pg_assert_same('24680', $answer['refund_reference'], 'the payment id when iyzico gives none back');

	$order = payment_iyzico_test_order();
	$order['cancellation_reason'] = '';
	$http = payment_iyzico_test_http(array('/payment/cancel' => array('status' => 'failure')));
	$refused = pg_payment_answer('cancel', pg_payment_iyzico_cancel_with($order, '24680', payment_iyzico_test_settings()));

	pg_assert_false(isset($http->posts[0]['body']['description']), 'no reason, no description');
	pg_assert_false($refused['success']);
	pg_assert_same(lang('The payment was not accepted.'), $refused['error']);
}

/* ---------------------------------------------------------------------------
   Checkout helpers
   --------------------------------------------------------------------------- */

function test_payment_iyzico_drivers_are_shipped()
{
	pg_assert_same('iyzico', pg_payment_driver_for_gateway('Iyzipay'));
	pg_assert_same('pay_with_iyzico', pg_payment_driver_for_method('Pay With Iyzico'));
	pg_assert_same('config', pg_payment_driver('iyzico')['settings']);
	pg_assert_same('config', pg_payment_driver('pay_with_iyzico')['settings']);
	pg_assert_same(array(), pg_payment_fields('iyzico'), 'the config columns stay the settings');
	pg_assert_false(strpos(pg_payment_settings_gateway_options('Iyzipay'), 'Iyzipay') !== false, 'the gateway list already has Iyzipay');

	pg_assert_true(pg_payment_can('iyzico', 'authorize'));
	pg_assert_false(pg_payment_can('iyzico', 'complete'), 'the 3-D Secure return stays in submit_order.php');
	pg_assert_true(pg_payment_can('pay_with_iyzico', 'complete'));
	pg_assert_true(pg_payment_can('iyzico', 'installments'));
	pg_assert_true(pg_payment_can('iyzico', 'refund'));
	pg_assert_true(pg_payment_can('pay_with_iyzico', 'cancel'));

	$addon = pg_payment_driver_definition_normalize('addonpay', array('label' => 'Add-on', 'kind' => 'card', 'gateway' => 'Add-on', 'file' => '/srv/addon.php', 'settings' => 'config'), '');
	pg_assert_same('connect', $addon['settings'], 'an add-on keeps its settings in connect_apps');
}

function test_payment_iyzico_checkout_driver_choice()
{
	pg_assert_same('iyzico', pg_payment_checkout_driver('Credit/Debit Card', 'Iyzipay', ''));
	pg_assert_same('', pg_payment_checkout_driver('Credit/Debit Card', 'Iyzipay', 'iyzipay_threedsecure_return'), 'the bank\'s return is finished as before');
	pg_assert_same('pay_with_iyzico', pg_payment_checkout_driver('Pay With Iyzico', 'Iyzipay', ''));
	pg_assert_same('pay_with_iyzico', pg_payment_checkout_driver('Pay With Iyzico', 'Stripe', 'pay_with_iyzico_return'));
	pg_assert_same('', pg_payment_checkout_driver('Credit/Debit Card', 'Stripe', ''), 'a legacy gateway');
	pg_assert_same('', pg_payment_checkout_driver('Credit/Debit Card', '', ''), 'no gateway');
	pg_assert_same('', pg_payment_checkout_driver('Offline Payment', 'Iyzipay', ''));
	pg_assert_same('', pg_payment_checkout_driver('PayPal Express Checkout', 'Iyzipay', 'paypal_express_checkout_return'));

	pg_assert_same('iyzipay_threedsecure_return', pg_payment_return_mode('iyzico'));
	pg_assert_same('pay_with_iyzico_return', pg_payment_return_mode('pay_with_iyzico'));
	pg_assert_same('', pg_payment_return_mode('nosuchdriver'));
}

function test_payment_iyzico_checkout_totals_without_an_amount()
{
	pg_assert_same(
		array('total' => 5000, 'installment_charge' => 0, 'total_without_installment_charge' => 0, 'payment_installment' => 1),
		pg_payment_checkout_totals(pg_payment_answer('authorize', array('success' => true, 'status' => 'paid')), 5000));
	pg_assert_same(
		array('total' => 5000, 'installment_charge' => 0, 'total_without_installment_charge' => 0, 'payment_installment' => 1),
		pg_payment_checkout_totals(pg_payment_answer('authorize', array('success' => true, 'status' => 'paid', 'amount' => 4000, 'installments' => -2)), 5000),
		'less than the total charges nothing extra');
}
