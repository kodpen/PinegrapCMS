<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the payment methods the Stripe driver offers beside the card
 * (includes/payments/stripe.php): the list of methods, the types an intent
 * and the Payment Element are built with for a currency and the store's
 * switches, the page and the intent agreeing, an order that repeats (and its
 * renewals and card update) kept to cards, and the switches drawn on the
 * Payment Methods card. Stripe is never reached: a stand-in takes the place
 * of the HTTP caller.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/stripe.php');

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

// Stands in for Stripe: answers by "METHOD path", records every call.
function payment_stripe_methods_test_http($answers = array())
{
	$GLOBALS['payment_stripe_methods_test_calls'] = array();

	return function ($secret_key, $method, $path, $params, $idempotency_key) use ($answers) {
		$GLOBALS['payment_stripe_methods_test_calls'][] = array('method' => $method, 'path' => $path, 'params' => $params);
		$name = $method . ' ' . $path;

		if (!isset($answers[$name])) {
			return array('status' => 0, 'body' => null, 'error' => 'Could not resolve host: api.stripe.com');
		}

		return array('status' => $answers[$name][0], 'body' => $answers[$name][1], 'error' => '');
	};
}

// The params of the last call made to a path.
function payment_stripe_methods_test_params($path)
{
	$found = null;

	foreach ($GLOBALS['payment_stripe_methods_test_calls'] as $call) {
		if ($call['path'] === $path) {
			$found = $call['params'];
		}
	}

	return $found;
}

function payment_stripe_methods_test_all_on()
{
	return array('ideal' => true, 'bancontact' => true, 'eps' => true, 'p24' => true, 'klarna' => true, 'afterpay_clearpay' => true, 'sepa_debit' => true, 'blik' => true);
}

function payment_stripe_methods_test_settings($changes = array())
{
	return array_merge(array(
		'publishable_key' => 'pk_test_123',
		'secret_key'      => 'sk_test_456',
		'link'            => false,
		'currency'        => 'eur',
		'capture'         => 'automatic',
		'locale'          => 'nl',
		'methods'         => array(),
	), $changes);
}

function payment_stripe_methods_test_order($changes = array())
{
	return array_merge(array(
		'id'                    => '438',
		'reference_code'        => 'AB12CD34EF',
		'billing_email_address' => 'anna@example.com',
	), $changes);
}

function payment_stripe_methods_test_intent($changes = array())
{
	return array_merge(array(
		'id'       => 'pi_3Abc',
		'object'   => 'payment_intent',
		'amount'   => 4714,
		'currency' => 'eur',
		'status'   => 'succeeded',
		'metadata' => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
	), $changes);
}

/* ---------------------------------------------------------------------------
   The list
   --------------------------------------------------------------------------- */

function test_payment_stripe_methods_list()
{
	$methods = pg_payment_stripe_methods();

	pg_assert_same(array('ideal', 'bancontact', 'eps', 'p24', 'klarna', 'afterpay_clearpay', 'sepa_debit', 'blik'), array_keys($methods));

	foreach ($methods as $type => $method) {
		pg_assert_same(array('label', 'currencies', 'immediate', 'hold', 'step'), array_keys($method), $type);
		pg_assert_true(is_string($method['label']) && ($method['label'] !== ''), $type);
		pg_assert_true($method['currencies'] !== array(), $type);

		foreach ($method['currencies'] as $currency) {
			pg_assert_same(1, preg_match('/^[A-Z]{3}$/', $currency), $type . ' ' . $currency);
		}

		// A bank debit settles days later and is given in the Payment
		// Element; BLIK has the checkout's own code field and waiting page;
		// every other method is paid on its own page.
		pg_assert_same($type !== 'sepa_debit', $method['immediate'], $type . ': Stripe answers the result when the visitor is back');
		pg_assert_same(($type === 'sepa_debit') ? 'element' : (($type === 'blik') ? 'page' : 'redirect'), $method['step'], $type);
		pg_assert_same(in_array($type, array('klarna', 'afterpay_clearpay'), true), $method['hold'], $type . ': only these can be held');
	}

	pg_assert_same(array('EUR'), $methods['ideal']['currencies']);
	pg_assert_same(array('EUR'), $methods['bancontact']['currencies']);
	pg_assert_same(array('EUR'), $methods['eps']['currencies']);
	pg_assert_same(array('EUR', 'PLN'), $methods['p24']['currencies']);
	pg_assert_same(array('AUD', 'CAD', 'EUR', 'GBP', 'NZD', 'USD'), $methods['afterpay_clearpay']['currencies']);
	pg_assert_same(array('EUR'), $methods['sepa_debit']['currencies']);
	pg_assert_same('SEPA Direct Debit', $methods['sepa_debit']['label']);
	pg_assert_same(array('PLN'), $methods['blik']['currencies']);
	pg_assert_true(in_array('SEK', $methods['klarna']['currencies'], true));
	pg_assert_false(in_array('TRY', $methods['klarna']['currencies'], true));
}

/* ---------------------------------------------------------------------------
   The types for a currency and the switches
   --------------------------------------------------------------------------- */

function test_payment_stripe_method_types_by_currency_and_switches()
{
	$all = payment_stripe_methods_test_all_on();

	pg_assert_same(array('card'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings()), 'all off at first');
	pg_assert_same(array('card', 'link'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings(array('link' => true))));

	$settings = payment_stripe_methods_test_settings();
	unset($settings['methods']);
	pg_assert_same(array('card'), pg_payment_stripe_method_types('eur', $settings), 'settings without switches: none');

	$on = payment_stripe_methods_test_settings(array('methods' => $all));
	pg_assert_same(array('card', 'ideal', 'bancontact', 'eps', 'p24', 'klarna', 'afterpay_clearpay', 'sepa_debit'), pg_payment_stripe_method_types('eur', $on));
	pg_assert_same(array('card', 'ideal', 'bancontact', 'eps', 'p24', 'klarna', 'afterpay_clearpay', 'sepa_debit'), pg_payment_stripe_method_types('EUR', $on), 'any case');
	pg_assert_same(array('card', 'p24', 'klarna'), pg_payment_stripe_method_types('pln', $on), 'BLIK is never in the Payment Element');
	pg_assert_same(array('card'), pg_payment_stripe_method_types('pln', payment_stripe_methods_test_settings(array('methods' => array('blik' => true)))));
	pg_assert_same(array('card', 'klarna', 'afterpay_clearpay'), pg_payment_stripe_method_types('usd', $on));
	pg_assert_same(array('card', 'klarna'), pg_payment_stripe_method_types('sek', $on));
	pg_assert_same(array('card'), pg_payment_stripe_method_types('try', $on), 'no method takes the currency');
	pg_assert_same(array('card'), pg_payment_stripe_method_types('', $on));

	pg_assert_same(array('card', 'ideal'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings(array('methods' => array('ideal' => true, 'eps' => false)))));
	pg_assert_same(array('card', 'sepa_debit'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings(array('methods' => array('sepa_debit' => true)))));
	pg_assert_same(array('card'), pg_payment_stripe_method_types('usd', payment_stripe_methods_test_settings(array('methods' => array('sepa_debit' => true)))), 'a SEPA debit is in euros only');
	pg_assert_same(array('card', 'link', 'ideal'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings(array('link' => true, 'methods' => array('ideal' => true)))));
	pg_assert_same(array('card'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings(array('methods' => array('sofort' => true, 'card' => true)))), 'a type outside the list is never sent');
	pg_assert_same(array('card'), pg_payment_stripe_method_types('eur', payment_stripe_methods_test_settings(array('methods' => 'ideal'))));
}

// Transaction type Authorize holds the money: only a method that can be held
// is offered beside the card.
function test_payment_stripe_method_types_when_the_payment_is_held()
{
	$held = payment_stripe_methods_test_settings(array('capture' => 'manual', 'methods' => payment_stripe_methods_test_all_on()));

	pg_assert_same(array('card', 'klarna', 'afterpay_clearpay'), pg_payment_stripe_method_types('eur', $held), 'a SEPA debit cannot be held');
	pg_assert_same(array('card', 'klarna'), pg_payment_stripe_method_types('pln', $held));
}

// An order with a recurring line keeps the card for its renewals.
function test_payment_stripe_method_types_for_a_repeating_order()
{
	$on = payment_stripe_methods_test_settings(array('methods' => payment_stripe_methods_test_all_on()));

	pg_assert_same(array('card'), pg_payment_stripe_method_types('eur', $on, true), 'no SEPA debit either');
	pg_assert_same(array('card', 'link'), pg_payment_stripe_method_types('eur', array_merge($on, array('link' => true)), true));
}

/* ---------------------------------------------------------------------------
   The page and the intent
   --------------------------------------------------------------------------- */

// Stripe refuses a confirmation when the Payment Element was built with other
// payment method types than the intent.
function test_payment_stripe_methods_page_and_intent_agree()
{
	foreach (array('eur', 'pln', 'usd', 'try') as $currency) {
		foreach (array('automatic', 'manual') as $capture) {
			foreach (array(false, true) as $recurring) {
				$settings = payment_stripe_methods_test_settings(array('currency' => $currency, 'capture' => $capture, 'methods' => payment_stripe_methods_test_all_on()));
				$label = $currency . ' ' . $capture . ($recurring ? ' repeating' : '');
				$client = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_methods_test_order(array('recurring' => $recurring)), 4714, $settings))['client'];

				pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, array(
					'token'      => 'ctoken_1Xyz',
					'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
					'recurring'  => $recurring,
				), $settings, payment_stripe_methods_test_http(array(
					'POST /v1/customers'       => array(200, array('id' => 'cus_Q1a', 'object' => 'customer')),
					'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent(array('currency' => $currency))),
				)));
				$params = payment_stripe_methods_test_params('/v1/payment_intents');

				pg_assert_same(pg_payment_stripe_method_types($currency, $settings, $recurring), $client['payment_method_types'], $label);
				pg_assert_same($params['payment_method_types'], $client['payment_method_types'], $label);
				pg_assert_same($params['capture_method'], $client['capture_method'], $label);
				pg_assert_same(isset($params['setup_future_usage']), isset($client['setup_future_usage']), $label);
			}
		}
	}
}

function test_payment_stripe_methods_prepare_gives_the_page_the_methods()
{
	$answer = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_methods_test_order(), 4714, payment_stripe_methods_test_settings(array('methods' => array('ideal' => true, 'klarna' => true)))));

	pg_assert_same(array(
		'script'               => 'https://js.stripe.com/v3/',
		'publishable_key'      => 'pk_test_123',
		'amount'               => 4714,
		'currency'             => 'eur',
		'payment_method_types' => array('card', 'ideal', 'klarna'),
		'capture_method'       => 'automatic',
		'locale'               => 'nl',
		'blik'                 => false,
	), $answer['client']);
}

function test_payment_stripe_methods_authorize_request()
{
	pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, array(
		'token'      => 'ctoken_1Xyz',
		'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), payment_stripe_methods_test_settings(array('methods' => array('ideal' => true, 'klarna' => true))), payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent()),
	)));

	pg_assert_same(array(
		'amount'               => 4714,
		'currency'             => 'eur',
		'confirm'              => true,
		'confirmation_token'   => 'ctoken_1Xyz',
		'payment_method_types' => array('card', 'ideal', 'klarna'),
		'capture_method'       => 'automatic',
		'metadata'             => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
		'expand'               => array('payment_method', 'latest_charge.balance_transaction'),
		'description'          => 'anna@example.com',
		'return_url'           => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), payment_stripe_methods_test_params('/v1/payment_intents'));
}

// A SEPA debit: the page's ConfirmationToken carries the IBAN and the
// accepted mandate, so the intent's request is the same one; Stripe answers
// it processing, and the order is placed awaiting its payment, the visitor
// never sent away.
function test_payment_stripe_methods_sepa_debit_is_placed_pending()
{
	$settings = payment_stripe_methods_test_settings(array('methods' => array('sepa_debit' => true)));
	$client = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_methods_test_order(), 4714, $settings))['client'];
	pg_assert_same(array('card', 'sepa_debit'), $client['payment_method_types']);

	$answer = pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, array(
		'token'      => 'ctoken_1Xyz',
		'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), $settings, payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent(array(
			'status'         => 'processing',
			'payment_method' => array('id' => 'pm_1Sepa', 'object' => 'payment_method', 'type' => 'sepa_debit', 'sepa_debit' => array('last4' => '3000', 'country' => 'DE')),
		))),
	))));

	pg_assert_same(array(
		'amount'               => 4714,
		'currency'             => 'eur',
		'confirm'              => true,
		'confirmation_token'   => 'ctoken_1Xyz',
		'payment_method_types' => array('card', 'sepa_debit'),
		'capture_method'       => 'automatic',
		'metadata'             => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
		'expand'               => array('payment_method', 'latest_charge.balance_transaction'),
		'description'          => 'anna@example.com',
		'return_url'           => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), payment_stripe_methods_test_params('/v1/payment_intents'), 'no mandate_data: the ConfirmationToken has it');

	pg_assert_true($answer['success']);
	pg_assert_same('pending', $answer['status']);
	pg_assert_same('pi_3Abc', $answer['transaction_id']);
	pg_assert_same('', $answer['card_last4'], 'no card');
	pg_assert_same(0, pg_order_checkout_paid_at('Credit/Debit Card', $answer['transaction_id'], $answer['status'], 1761600000), 'placed unpaid');
}

// The bank's page sends the visitor back to the return address; the intent
// that waits for it is answered as a redirect, as a 3-D Secure page is.
function test_payment_stripe_methods_bank_page_is_a_redirect()
{
	$answer = pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, array(
		'token'      => 'ctoken_1Xyz',
		'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), payment_stripe_methods_test_settings(array('methods' => array('ideal' => true))), payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent(array(
			'status'      => 'requires_action',
			'next_action' => array('type' => 'redirect_to_url', 'redirect_to_url' => array('url' => 'https://pm-redirects.stripe.com/authorize/acct_1/pa_nonce_1', 'return_url' => 'https://shop.example/r')),
		))),
	))));

	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('https://pm-redirects.stripe.com/authorize/acct_1/pa_nonce_1', $answer['redirect_url']);
	pg_assert_same(array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714), $answer['state']);
}

/* ---------------------------------------------------------------------------
   Renewals and the card update keep cards
   --------------------------------------------------------------------------- */

function test_payment_stripe_methods_renewal_and_card_update_keep_cards()
{
	$settings = payment_stripe_methods_test_settings(array('methods' => payment_stripe_methods_test_all_on()));
	$subscription = array(
		'id'             => 7,
		'user_id'        => 31,
		'order_id'       => 438,
		'driver'         => 'stripe',
		'status'         => 'active',
		'period'         => 'monthly',
		'amount'         => 9900,
		'currency'       => 'EUR',
		'next_charge_at' => 1793000000,
		'retry_count'    => 0,
		'card_last4'     => '4242',
		'card_ref'       => 'cus_Q1a|pm_1Old',
		'return_url'     => 'https://shop.example/account',
	);

	pg_payment_stripe_charge_renewal_with($subscription, $settings, payment_stripe_methods_test_http(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => array(200, array('object' => 'list', 'data' => array(array('id' => 'pm_1Old', 'object' => 'payment_method', 'customer' => 'cus_Q1a', 'card' => array('last4' => '4242'))))),
		'POST /v1/payment_intents'                  => array(200, payment_stripe_methods_test_intent(array('id' => 'pi_3Ren', 'amount' => 9900, 'customer' => 'cus_Q1a', 'metadata' => array('subscription_id' => '7')))),
	)));
	pg_assert_same(array('card'), payment_stripe_methods_test_params('/v1/payment_intents')['payment_method_types'], 'a renewal is charged to the kept card');

	pg_payment_stripe_update_card_with($subscription, $settings, payment_stripe_methods_test_http(array(
		'POST /v1/checkout/sessions' => array(200, array('id' => 'cs_test_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1')),
	)), 1793000000);
	pg_assert_same(array('card'), payment_stripe_methods_test_params('/v1/checkout/sessions')['payment_method_types'], 'a new card only');
}

/* ---------------------------------------------------------------------------
   The switches on the Payment Methods card
   --------------------------------------------------------------------------- */

function test_payment_stripe_method_fields()
{
	$fields = pg_payment_fields_normalize(pg_payment_stripe_method_fields('EUR'));

	pg_assert_same(array('method_ideal', 'method_bancontact', 'method_eps', 'method_p24', 'method_klarna', 'method_afterpay_clearpay', 'method_sepa_debit', 'method_blik'), array_column($fields, 'name'));
	$blik = array_pop($fields);
	pg_assert_same('BLIK', $blik['label']);
	pg_assert_contains('PLN', $blik['help']);
	pg_assert_contains(lang('Not offered while Transaction type is Authorize.'), $blik['help'], 'BLIK cannot be held');

	foreach ($fields as $index => $field) {
		pg_assert_same('switch', $field['type'], $field['name']);
		pg_assert_same('', $field['default'], $field['name'] . ' is off at first');
		pg_assert_same('', $field['unavailable'], $field['name'] . ' takes EUR');
		pg_assert_contains('EUR', $field['help'], $field['name']);
		pg_assert_same(($index === 0) ? lang('Payment methods') : '', $field['group'], $field['name']);
	}

	pg_assert_same('iDEAL', $fields[0]['label']);
	pg_assert_contains('EUR, PLN', $fields[3]['help']);
	pg_assert_contains(lang('Not offered while Transaction type is Authorize.'), $fields[0]['help'], 'a bank payment cannot be held');
	pg_assert_false(strpos($fields[4]['help'], lang('Not offered while Transaction type is Authorize.')) !== false, 'Klarna can be held');
	$later = lang('The payment arrives days later: the order waits for it and is marked paid when Stripe reports it.');
	pg_assert_contains($later, $fields[6]['help'], 'a SEPA debit settles later');
	pg_assert_contains(lang('Not offered while Transaction type is Authorize.'), $fields[6]['help']);
	pg_assert_false(strpos($fields[0]['help'], $later) !== false, 'iDEAL answers at once');

	$usd = pg_payment_fields_normalize(pg_payment_stripe_method_fields('usd'));
	pg_assert_contains('USD', $usd[0]['unavailable'], 'iDEAL takes no USD: the reason names the base currency');
	pg_assert_contains('EUR', $usd[0]['unavailable']);
	pg_assert_same('', $usd[4]['unavailable'], 'Klarna takes USD');
	pg_assert_same('', $usd[5]['unavailable'], 'Afterpay takes USD');
	pg_assert_contains('USD', $usd[6]['unavailable'], 'a SEPA debit takes no USD');

	foreach (pg_payment_fields_normalize(pg_payment_stripe_method_fields('')) as $field) {
		pg_assert_same('', $field['unavailable'], 'no reason without a known currency');
	}
}

/* ---------------------------------------------------------------------------
   BLIK: the checkout's code field and waiting page
   --------------------------------------------------------------------------- */

function payment_stripe_methods_test_blik_settings($changes = array())
{
	return payment_stripe_methods_test_settings(array_merge(array('currency' => 'pln', 'locale' => 'pl', 'methods' => array('blik' => true)), $changes));
}

function payment_stripe_methods_test_blik_payment($changes = array())
{
	return array_merge(array(
		'blik_code'  => '123 456',
		'token'      => '',
		'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), $changes);
}

function test_payment_stripe_blik_offered()
{
	$on = payment_stripe_methods_test_blik_settings();

	pg_assert_true(pg_payment_stripe_blik_offered('pln', $on));
	pg_assert_true(pg_payment_stripe_blik_offered('PLN', $on), 'any case');
	pg_assert_false(pg_payment_stripe_blik_offered('eur', $on), 'zloty only');
	pg_assert_false(pg_payment_stripe_blik_offered('pln', $on, true), 'a repeating order keeps cards only');
	pg_assert_false(pg_payment_stripe_blik_offered('pln', payment_stripe_methods_test_blik_settings(array('capture' => 'manual'))), 'BLIK cannot be held');
	pg_assert_false(pg_payment_stripe_blik_offered('pln', payment_stripe_methods_test_blik_settings(array('methods' => array()))), 'off at first');
	pg_assert_false(pg_payment_stripe_blik_offered('pln', payment_stripe_methods_test_blik_settings(array('methods' => array('p24' => true)))));
}

function test_payment_stripe_blik_code()
{
	pg_assert_same('123456', pg_payment_stripe_blik_code('123456'));
	pg_assert_same('123456', pg_payment_stripe_blik_code(' 123 456 '));
	pg_assert_same('123456', pg_payment_stripe_blik_code('123-456'));
	pg_assert_same('', pg_payment_stripe_blik_code('12345'));
	pg_assert_same('', pg_payment_stripe_blik_code('1234567'));
	pg_assert_same('', pg_payment_stripe_blik_code('12a456'));
	pg_assert_same('', pg_payment_stripe_blik_code(array('123456')));
	pg_assert_same('', pg_payment_stripe_blik_code(null));
}

// The page is told to draw BLIK only where it can show the waiting page.
function test_payment_stripe_blik_prepare()
{
	$settings = payment_stripe_methods_test_blik_settings();
	$client = function ($order, $settings) {
		return pg_payment_answer('prepare', pg_payment_stripe_prepare_with($order, 4714, $settings))['client'];
	};

	pg_assert_true($client(payment_stripe_methods_test_order(array('page_step' => true)), $settings)['blik']);
	pg_assert_same(array('card'), $client(payment_stripe_methods_test_order(array('page_step' => true)), $settings)['payment_method_types']);
	pg_assert_false($client(payment_stripe_methods_test_order(), $settings)['blik'], 'a form payment shows no waiting page');
	pg_assert_false($client(payment_stripe_methods_test_order(array('page_step' => true, 'recurring' => true)), $settings)['blik']);
	pg_assert_false($client(payment_stripe_methods_test_order(array('page_step' => true)), payment_stripe_methods_test_blik_settings(array('currency' => 'eur')))['blik']);

	$box = pg_payment_box('stripe', pg_payment_stripe_prepare_with(payment_stripe_methods_test_order(array('page_step' => true)), 4714, $settings), array('csp' => array('script-src' => array('https://js.stripe.com'))), _eo_hosted_blik_choice_html(array('card')));
	pg_assert_contains('name="payment_blik_code"', $box);
	pg_assert_contains('inputmode="numeric"', $box);
	pg_assert_contains('autocomplete="one-time-code"', $box);
	pg_assert_contains('value="blik"', $box);
	pg_assert_contains('disabled>', $box, 'not posted until BLIK is chosen');
	pg_assert_true(strpos($box, 'payment_blik_code') < strpos($box, 'pg-eo-hosted-card-element'), 'above the Payment Element');
	pg_assert_contains(h(lang('Card')) . '</label>', $box);
	pg_assert_contains(h(lang('Card and other methods')), _eo_hosted_blik_choice_html(array('card', 'p24')));

	$plain = pg_payment_box('stripe', pg_payment_stripe_prepare_with(payment_stripe_methods_test_order(), 4714, $settings), array('csp' => array('script-src' => array('https://js.stripe.com'))));
	pg_assert_false(strpos($plain, 'payment_blik_code') !== false);
}

// The request body: the code goes in the confirm call only, BLIK alone, no
// mandate, no hold, nothing kept.
function test_payment_stripe_blik_authorize_request()
{
	$answer = pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, payment_stripe_methods_test_blik_payment(), payment_stripe_methods_test_blik_settings(), payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent(array(
			'currency'      => 'pln',
			'status'        => 'requires_action',
			'client_secret' => 'pi_3Abc_secret_Zz9',
			'next_action'   => array('type' => 'blik_authorize', 'blik_authorize' => array()),
		))),
	))));

	pg_assert_same(array(
		'amount'                 => 4714,
		'currency'               => 'pln',
		'confirm'                => true,
		'payment_method_types'   => array('blik'),
		'payment_method_data'    => array('type' => 'blik', 'billing_details' => array('email' => 'anna@example.com')),
		'payment_method_options' => array('blik' => array('code' => '123456')),
		'metadata'               => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
		'expand'                 => array('payment_method', 'latest_charge.balance_transaction'),
		'description'            => 'anna@example.com',
	), payment_stripe_methods_test_params('/v1/payment_intents'));

	// The waiting page: the order is not placed, the state is the return's.
	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('', $answer['redirect_url'], 'never an address with the secret');
	pg_assert_same(array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714), $answer['state']);
	pg_assert_contains('data-pg-payment-step=', $answer['html']);
	pg_assert_contains('pi_3Abc_secret_Zz9', $answer['html']);
	pg_assert_contains('https://js.stripe.com/v3/', $answer['html']);
	pg_assert_contains('href="https://shop.example/pinegrap/express_order.php?mode=stripe_return&amp;page_id=1023"', $answer['html']);
	pg_assert_false(strpos($answer['html'], '123456') !== false, 'the code is not in the page');
	pg_assert_false(strpos(json_encode($answer['log']), '123456') !== false, 'nor in the log');
}

function test_payment_stripe_blik_authorize_refused_before_stripe()
{
	$http = payment_stripe_methods_test_http();
	$try = function ($payment, $settings) use ($http) {
		return pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, $payment, $settings, $http));
	};

	$answer = $try(payment_stripe_methods_test_blik_payment(array('blik_code' => '12345')), payment_stripe_methods_test_blik_settings());
	pg_assert_false($answer['success']);
	pg_assert_same(lang('Please enter the six-digit BLIK code from your bank\'s app.'), $answer['error']);

	foreach (array(
		'euro'      => array(payment_stripe_methods_test_blik_payment(), payment_stripe_methods_test_blik_settings(array('currency' => 'eur'))),
		'off'       => array(payment_stripe_methods_test_blik_payment(), payment_stripe_methods_test_blik_settings(array('methods' => array()))),
		'held'      => array(payment_stripe_methods_test_blik_payment(), payment_stripe_methods_test_blik_settings(array('capture' => 'manual'))),
		'repeating' => array(payment_stripe_methods_test_blik_payment(array('recurring' => true)), payment_stripe_methods_test_blik_settings()),
		'no return' => array(payment_stripe_methods_test_blik_payment(array('return_url' => '')), payment_stripe_methods_test_blik_settings()),
	) as $label => $case) {
		$answer = $try($case[0], $case[1]);
		pg_assert_false($answer['success'], $label);
		pg_assert_same(lang('This payment method is not available.'), $answer['error'], $label);
	}

	pg_assert_same(array(), $GLOBALS['payment_stripe_methods_test_calls'], 'Stripe is not asked');
}

function test_payment_stripe_blik_wrong_code_at_once()
{
	$answer = pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, payment_stripe_methods_test_blik_payment(), payment_stripe_methods_test_blik_settings(), payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(402, array('error' => array('type' => 'card_error', 'code' => 'payment_method_invalid_parameter', 'decline_code' => 'code_invalid', 'message' => 'The BLIK code is invalid.'))),
	))));

	pg_assert_false($answer['success']);
	pg_assert_contains('BLIK', $answer['error']);
	pg_assert_false(strpos(json_encode($answer['log']), '123456') !== false);
}

// Back at the return address: the intent is read again and decides.
function test_payment_stripe_blik_return()
{
	$state = array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714);
	$complete = function ($intent) use ($state) {
		return pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_methods_test_order(), 4714, array(), $state, payment_stripe_methods_test_blik_settings(), payment_stripe_methods_test_http(array(
			'GET /v1/payment_intents/pi_3Abc'  => array(200, payment_stripe_methods_test_intent(array_merge(array('currency' => 'pln'), $intent))),
			'POST /v1/payment_intents/pi_3Abc/cancel' => array(200, payment_stripe_methods_test_intent(array('currency' => 'pln', 'status' => 'canceled'))),
		))));
	};

	$paid = $complete(array('status' => 'succeeded', 'payment_method' => array('id' => 'pm_1', 'object' => 'payment_method', 'type' => 'blik', 'blik' => array())));
	pg_assert_true($paid['success']);
	pg_assert_same('paid', $paid['status']);

	$timeout = $complete(array('status' => 'requires_payment_method', 'last_payment_error' => array('code' => 'payment_method_provider_timeout', 'decline_code' => 'generic_decline', 'payment_method' => array('type' => 'blik'))));
	pg_assert_false($timeout['success']);
	pg_assert_same(lang('The BLIK payment was not approved in time. Please get a new code and try again.'), $timeout['error']);

	$declined = $complete(array('status' => 'requires_payment_method', 'last_payment_error' => array('code' => 'payment_method_provider_decline', 'decline_code' => 'generic_decline', 'payment_method' => array('type' => 'blik'))));
	pg_assert_same(lang('The BLIK payment was not approved. Please get a new code and try again, or choose another payment method.'), $declined['error'], 'not a card sentence');

	$waiting = $complete(array('status' => 'requires_action', 'client_secret' => 'pi_3Abc_secret_Zz9', 'next_action' => array('type' => 'blik_authorize')));
	pg_assert_false($waiting['success'], 'no waiting page twice');
	pg_assert_same(lang('The BLIK payment was not approved in time. Please get a new code and try again.'), $waiting['error']);
	pg_assert_same('', $waiting['html']);
}

// Another driver's answer or another method's next step never gets the page.
function test_payment_stripe_blik_page_only_for_blik_authorize()
{
	$answer = pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, payment_stripe_methods_test_blik_payment(), payment_stripe_methods_test_blik_settings(), payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent(array(
			'currency'      => 'pln',
			'status'        => 'requires_action',
			'client_secret' => 'pi_9Other_secret_Zz9',
			'next_action'   => array('type' => 'blik_authorize'),
		))),
	))));

	pg_assert_false($answer['success'], 'a client secret of another intent');

	$answer = pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_methods_test_order(), 4714, array(
		'token'      => 'ctoken_1Xyz',
		'return_url' => 'https://shop.example/r',
	), payment_stripe_methods_test_settings(), payment_stripe_methods_test_http(array(
		'POST /v1/payment_intents' => array(200, payment_stripe_methods_test_intent(array(
			'status'        => 'requires_action',
			'client_secret' => 'pi_3Abc_secret_Zz9',
			'next_action'   => array('type' => 'blik_authorize'),
		))),
	))));

	pg_assert_false($answer['success'], 'the Payment Element path still refuses a step it cannot show');
	pg_assert_same('', $answer['html']);
}

// The waiting page's script (assets/js/payment_hosted.js, the source): Stripe.js
// waits with the client secret, then the page goes to the return address;
// without Stripe.js it goes there at once. Skipped without Node.
function test_payment_stripe_blik_waiting_page_script()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$script = <<<'JS'
var fs = require('fs');
var source = fs.readFileSync(process.argv[2], 'utf8');
var config = process.argv[3];

function run(withStripe) {
    var seen = { assigned: [], secret: null };
    var node = {
        getAttribute: function () { return config; },
        querySelector: function () { return { textContent: '' }; }
    };
    var sandbox = {
        setInterval: function () { return 1; }, clearInterval: function () {}, setTimeout: function () { return 1; },
        location: { assign: function (url) { seen.assigned.push(url); } },
        Promise: Promise
    };
    sandbox.window = sandbox;
    sandbox.document = {
        readyState: 'complete',
        addEventListener: function () {},
        querySelectorAll: function (selector) { return (selector === '[data-pg-payment-step]') ? [node] : []; }
    };

    if (withStripe) {
        sandbox.Stripe = function (key) {
            seen.key = key;
            return { handleNextAction: function (options) { seen.secret = options.clientSecret; return Promise.resolve({ paymentIntent: { status: 'succeeded' } }); } };
        };
    }

    new Function('window', 'document', 'setInterval', 'clearInterval', 'setTimeout', source)(sandbox, sandbox.document, sandbox.setInterval, sandbox.clearInterval, sandbox.setTimeout);

    return seen;
}

var a = run(true);
var b = run(false);

setTimeout(function () { console.log(JSON.stringify({ stripe: a, none: b })); }, 10);
JS;

	$file = @tempnam(sys_get_temp_dir(), 'pgblik');

	if ($file === false) {
		return;
	}

	$html = pg_payment_stripe_blik_step_html(array('publishable_key' => 'pk_test_123', 'client_secret' => 'pi_3Abc_secret_Zz9', 'locale' => 'pl', 'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023', 'script' => 'https://js.stripe.com/v3/', 'bundle' => ''));
	pg_assert_same(1, preg_match('/data-pg-payment-step="([^"]*)"/', $html, $match));

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' ' . escapeshellarg(PG_FUNCTIONS_DIR . '/assets/js/payment_hosted.js') . ' ' . escapeshellarg(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8')) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);
	pg_assert_true(($code === 0) && is_array($result), 'node ran: ' . implode("\n", $out));

	pg_assert_same('pi_3Abc_secret_Zz9', $result['stripe']['secret']);
	pg_assert_same('pk_test_123', $result['stripe']['key']);
	pg_assert_same(array('https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023'), $result['stripe']['assigned'], 'once, after Stripe.js answered');
	pg_assert_same(array('https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023'), $result['none']['assigned'], 'without Stripe.js: at once');
}

/* ---------------------------------------------------------------------------
   The method named on the order
   --------------------------------------------------------------------------- */

// The name of the method that paid comes from the intent's expanded
// payment_method; a card (or a type the driver does not list) names none.
function test_payment_stripe_paid_method_name()
{
	foreach (pg_payment_stripe_methods() as $type => $method) {
		pg_assert_same($method['label'], pg_payment_stripe_paid_method_name(array('type' => $type)), $type);
	}

	pg_assert_same('iDEAL', pg_payment_stripe_paid_method_name(array('type' => 'ideal')));
	pg_assert_same('SEPA Direct Debit', pg_payment_stripe_paid_method_name(array('type' => 'sepa_debit')));
	pg_assert_same('Link', pg_payment_stripe_paid_method_name(array('type' => 'link')));
	pg_assert_same('', pg_payment_stripe_paid_method_name(array('type' => 'card', 'card' => array('brand' => 'visa'))), 'a card is named by its brand');
	pg_assert_same('', pg_payment_stripe_paid_method_name(array('type' => 'cashapp')), 'a type not listed');
	pg_assert_same('', pg_payment_stripe_paid_method_name('pm_1Abc'), 'not expanded');
	pg_assert_same('', pg_payment_stripe_paid_method_name(null));

	$names = pg_payment_stripe_paid_method_names();
	pg_assert_true(in_array('Link', $names, true));
	pg_assert_true(in_array('BLIK', $names, true));
	pg_assert_same(count(pg_payment_stripe_methods()) + 1, count($names));

	foreach ($names as $name) {
		pg_assert_true(strlen($name) <= 50, $name . ' fits orders.card_type');
	}
}

// The order keeps the method's name where it keeps a card's brand, with no
// card number or expiry; a card still gives its brand and digits.
function test_payment_stripe_card_details_name_the_method()
{
	pg_assert_same(
		array('card_brand' => 'Klarna', 'card_last4' => '', 'card_exp_month' => '', 'card_exp_year' => ''),
		pg_payment_stripe_card_details(array('payment_method' => array('id' => 'pm_1K', 'type' => 'klarna', 'klarna' => array())))
	);
	pg_assert_same(
		array('card_brand' => 'SEPA Direct Debit', 'card_last4' => '', 'card_exp_month' => '', 'card_exp_year' => ''),
		pg_payment_stripe_card_details(array('payment_method' => array('type' => 'sepa_debit', 'sepa_debit' => array('last4' => '3000')))),
		'the IBAN digits are not a card number'
	);
	pg_assert_same(
		array('card_brand' => 'Visa', 'card_last4' => '4242', 'card_exp_month' => '12', 'card_exp_year' => '2030'),
		pg_payment_stripe_card_details(array('payment_method' => array('type' => 'card', 'card' => array('brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030))))
	);
	pg_assert_same('', pg_payment_stripe_card_details(array())['card_brand'], 'no payment_method');
}

// A redirect method's return reads the intent again: the answer the order is
// written from names the method.
function test_payment_stripe_return_names_the_method()
{
	$answer = pg_payment_answer('complete', pg_payment_stripe_intent_answer(payment_stripe_methods_test_order(), 4714, payment_stripe_methods_test_intent(array(
		'payment_method' => array('id' => 'pm_1Ideal', 'object' => 'payment_method', 'type' => 'ideal', 'ideal' => array('bank' => 'ing')),
	)), payment_stripe_methods_test_settings(), payment_stripe_methods_test_http(), false));

	pg_assert_same('paid', $answer['status']);
	pg_assert_same('iDEAL', $answer['card_brand']);
	pg_assert_same('', $answer['card_last4']);
}

// The order screens say "Paid with" only for a name the driver writes, and
// only on a card order; a brand stays a Card Type.
function test_payment_stripe_order_paid_with_method()
{
	pg_assert_true(pg_order_paid_with_method('Credit/Debit Card', 'iDEAL'));
	pg_assert_true(pg_order_paid_with_method('Credit/Debit Card', 'BLIK'));
	pg_assert_true(pg_order_paid_with_method('Credit/Debit Card', 'Link'));
	pg_assert_false(pg_order_paid_with_method('Credit/Debit Card', 'Visa'));
	pg_assert_false(pg_order_paid_with_method('Credit/Debit Card', 'ideal'), 'only the name as written');
	pg_assert_false(pg_order_paid_with_method('Credit/Debit Card', ''));
	pg_assert_false(pg_order_paid_with_method('PayPal Express Checkout', 'Klarna'), 'not a card order');
}
