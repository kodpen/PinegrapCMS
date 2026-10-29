<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - Stripe on the PaymentIntents API (the Payment Gateway value
 * "Stripe PaymentIntents"; the value "Stripe" stays the classic Charges code
 * in submit_order.php).
 *
 * The card is typed into Stripe's Payment Element on the checkout page, never
 * into the site's own inputs: prepare() gives the page the publishable key,
 * the amount and the currency, Stripe.js turns what the visitor entered (a
 * card, Apple Pay, Google Pay, optionally Link) into a ConfirmationToken, and
 * the checkout posts only the token's id. authorize() then creates the
 * PaymentIntent for the order's own total and confirms it with the token in
 * one call. A card that asks for 3-D Secure answers with Stripe's page to
 * send the visitor to; Stripe sends them back to the checkout's return
 * address and complete() reads the PaymentIntent again before the order is
 * placed.
 *
 * The API is called over HTTPS with the secret key (no SDK), with the API
 * version pinned, so a Stripe account whose default version is older or newer
 * reads and answers the same fields. Every operation is a thin wrapper around
 * a *_with() function that takes the settings and the HTTP caller, so the
 * whole flow can run against a stand-in for Stripe.
 *
 * Subscriptions run store-side ('recurring' => 'store'): the first payment of
 * an order with a recurring line makes a Customer and confirms the intent
 * with setup_future_usage off_session, so Stripe keeps the card, and answers
 * card_ref ("<customer id>|<payment method id>"). charge_renewal() charges it
 * off-session, update_card() opens Checkout in setup mode where the customer
 * adds a new card (the newest card of the Customer is the one charged), and
 * subscription_cancel() detaches the Customer's cards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_payment_stripe_capabilities()
{
    // Stripe charges a kept card off-session for any account: no switch.
    return array('wallets', 'partial_refund', 'threeds', 'hosted_fields', 'recurring' => 'store');
}

function pg_payment_stripe_fields()
{
    return array(
        array(
            'name'       => 'publishable_key',
            'label'      => lang('Publishable Key'),
            'help'       => lang('Starts with pk_test_ or pk_live_. Test keys take test cards only.'),
            'required'   => true,
            'max_length' => 255,
        ),
        array(
            'name'       => 'secret_key',
            'type'       => 'secret',
            'label'      => lang('Secret Key'),
            'help'       => lang('Starts with sk_ (or rk_ for a restricted key that can write PaymentIntents and Refunds), from the same mode as the publishable key.'),
            'required'   => true,
            'max_length' => 255,
        ),
        array(
            'name'       => 'webhook_secret',
            'type'       => 'secret',
            'label'      => lang('Webhook Signing Secret'),
            'help'       => lang('Starts with whsec_. Add the webhook address above in the Stripe Dashboard and copy its signing secret here; without it Stripe\'s messages are refused.'),
            'max_length' => 255,
        ),
        array(
            'name'  => 'link',
            'type'  => 'switch',
            'label' => lang('Offer Link'),
            'help'  => lang('Stripe\'s one-click checkout beside the card. Turn Link on in the Stripe Dashboard first.'),
        ),
    );
}

function pg_payment_stripe_prepare($order, $amount)
{
    return pg_payment_stripe_prepare_with($order, $amount, pg_payment_stripe_settings());
}

function pg_payment_stripe_authorize($order, $amount, $payment)
{
    return pg_payment_stripe_authorize_with($order, $amount, $payment, pg_payment_stripe_settings(), 'pg_payment_stripe_request');
}

function pg_payment_stripe_complete($order, $amount, $return, $state)
{
    return pg_payment_stripe_complete_with($order, $amount, $return, $state, pg_payment_stripe_settings(), 'pg_payment_stripe_request');
}

function pg_payment_stripe_refund($order, $amount, $lines)
{
    return pg_payment_stripe_refund_with($order, $amount, pg_payment_stripe_settings(), 'pg_payment_stripe_request');
}

function pg_payment_stripe_cancel($order, $transaction_id)
{
    return pg_payment_stripe_cancel_with($order, $transaction_id, pg_payment_stripe_settings(), 'pg_payment_stripe_request');
}

function pg_payment_stripe_notify($headers, $body)
{
    return pg_payment_stripe_notify_with($headers, $body, pg_payment_stripe_settings(), time());
}

function pg_payment_stripe_charge_renewal($subscription)
{
    return pg_payment_stripe_charge_renewal_with($subscription, pg_payment_stripe_settings(), 'pg_payment_stripe_request');
}

function pg_payment_stripe_update_card($subscription)
{
    return pg_payment_stripe_update_card_with($subscription, pg_payment_stripe_settings(), 'pg_payment_stripe_request', time());
}

function pg_payment_stripe_subscription_cancel($subscription)
{
    return pg_payment_stripe_subscription_cancel_with($subscription, pg_payment_stripe_settings(), 'pg_payment_stripe_request');
}

/* ---------------------------------------------------------------------------
   Settings, amounts and the API
   --------------------------------------------------------------------------- */

/**
 * The Stripe API version every request is made with.
 *
 * @return string
 */
function pg_payment_stripe_api_version()
{
    return '2024-06-20';
}

/**
 * The store's Stripe settings.
 *
 * @return array publishable_key, secret_key, webhook_secret, link (bool),
 *               currency (lowercase ISO code), capture ('automatic' or 'manual'), locale
 */
function pg_payment_stripe_settings()
{
    $locale = strtolower(trim((string) lang(array('info' => ''))));

    return array(
        'publishable_key' => trim(pg_payment_setting('stripe', 'publishable_key')),
        'secret_key'      => trim(pg_payment_setting('stripe', 'secret_key')),
        'webhook_secret'  => trim(pg_payment_setting('stripe', 'webhook_secret')),
        'link'            => (pg_payment_setting('stripe', 'link') === '1'),
        'currency'        => strtolower(defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : ''),
        // The store's Transaction Type: Authorize holds the money, and the
        // operator takes it on Stripe's own screen, as with the classic code.
        'capture'         => (defined('ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE') && (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize')) ? 'manual' : 'automatic',
        'locale'          => (preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $locale) === 1) ? $locale : 'auto',
    );
}

/**
 * How many decimals Stripe counts a currency in: none for the zero-decimal
 * currencies, three for the three-decimal ones, two for every other.
 *
 * @param string $currency
 * @return int
 */
function pg_payment_stripe_decimals($currency)
{
    $currency = strtolower((string) $currency);

    if (in_array($currency, array('bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'), true)) {
        return 0;
    }

    if (in_array($currency, array('bhd', 'jod', 'kwd', 'omr', 'tnd'), true)) {
        return 3;
    }

    return 2;
}

/**
 * Store hundredths as the amount Stripe takes. A three-decimal amount ends in
 * 0, which Stripe requires of those currencies.
 *
 * @param int|float $hundredths
 * @param string    $currency
 * @return int
 */
function pg_payment_stripe_amount($hundredths, $currency)
{
    $hundredths = (int) round((float) $hundredths);

    switch (pg_payment_stripe_decimals($currency)) {
        case 0:
            return (int) round($hundredths / 100);

        case 3:
            return $hundredths * 10;
    }

    return $hundredths;
}

/**
 * An amount from Stripe as store hundredths.
 *
 * @param mixed  $amount
 * @param string $currency
 * @return int|null null when it is not a whole number
 */
function pg_payment_stripe_hundredths($amount, $currency)
{
    if (!is_int($amount) && !(is_string($amount) && (preg_match('/^-?[0-9]+$/', $amount) === 1))) {
        return null;
    }

    $amount = (int) $amount;

    switch (pg_payment_stripe_decimals($currency)) {
        case 0:
            return $amount * 100;

        case 3:
            return (int) round($amount / 10);
    }

    return $amount;
}

/**
 * Parameters in Stripe's form encoding: nested arrays as key[sub]=value,
 * true and false as the words.
 *
 * @param array $params
 * @return string
 */
function pg_payment_stripe_encode($params)
{
    $words = function ($value) use (&$words) {
        if (is_array($value)) {
            return array_map($words, $value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    };

    return http_build_query(array_map($words, is_array($params) ? $params : array()), '', '&');
}

/**
 * The pg_curl_run() call for one request to Stripe's API.
 *
 * @param string $secret_key
 * @param string $method          GET or POST
 * @param string $path            /v1/...
 * @param array  $params
 * @param string $idempotency_key '' for none
 * @return array method, url, options (pg_curl_run()'s)
 */
function pg_payment_stripe_request_options($secret_key, $method, $path, $params, $idempotency_key)
{
    $method = ($method === 'POST') ? 'POST' : 'GET';
    $query = pg_payment_stripe_encode($params);
    $url = 'https://api.stripe.com' . $path . ((($method === 'GET') && ($query !== '')) ? '?' . $query : '');

    $headers = array(
        'Stripe-Version: ' . pg_payment_stripe_api_version(),
        'Content-Type: application/x-www-form-urlencoded',
    );

    if ($idempotency_key !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotency_key;
    }

    // A POST always carries its body, '' included, so it goes out as a POST
    // with Content-Length: 0 rather than a bare custom request.
    return array('method' => $method, 'url' => $url, 'options' => array(
        'userpwd'         => $secret_key . ':',
        'headers'         => $headers,
        'body'            => ($method === 'POST') ? $query : null,
        'timeout'         => 60,
        'connect_timeout' => 15,
    ));
}

/**
 * One call to Stripe's API.
 *
 * @param string $secret_key
 * @param string $method          GET or POST
 * @param string $path            /v1/...
 * @param array  $params
 * @param string $idempotency_key '' for none
 * @return array status (the HTTP status, 0 when Stripe was not reached),
 *               body (the decoded answer, null when it was not JSON),
 *               error (why Stripe was not reached)
 */
function pg_payment_stripe_request($secret_key, $method, $path, $params, $idempotency_key)
{
    // pg_curl_run() adds the proxy, the user agent and the TLS settings;
    // Stripe's host is fixed, so no address check.
    $request = pg_payment_stripe_request_options($secret_key, $method, $path, $params, $idempotency_key);
    $call = pg_curl_run($request['method'], $request['url'], $request['options']);

    if (($call['status'] === 0) && ($call['error'] !== '')) {
        $hint = ($call['errno'] !== 0) ? (string) pg_curl_tls_hint($call['errno']) : '';

        return array('status' => 0, 'body' => null, 'error' => trim($call['error'] . ' ' . $hint));
    }

    $decoded = json_decode((string) $call['body'], true);

    return array('status' => (int) $call['status'], 'body' => is_array($decoded) ? $decoded : null, 'error' => '');
}

/**
 * Calls Stripe through the given HTTP caller and reads the answer into one
 * shape.
 *
 * @param callable $http     pg_payment_stripe_request() or a stand-in
 * @param array    $settings pg_payment_stripe_settings()
 * @param string   $method
 * @param string   $path
 * @param array    $params
 * @param string   $idempotency_key
 * @return array ok (bool: a 2xx answer with a body), status, body (array),
 *               message (Stripe's or the transport's words), code
 *               (error.decline_code or error.code; 'unreached' when Stripe
 *               did not answer)
 */
function pg_payment_stripe_call($http, $settings, $method, $path, $params = array(), $idempotency_key = '')
{
    $response = call_user_func($http, $settings['secret_key'], $method, $path, $params, $idempotency_key);
    $response = is_array($response) ? $response : array();

    $status = isset($response['status']) ? (int) $response['status'] : 0;
    $body = (isset($response['body']) && is_array($response['body'])) ? $response['body'] : array();
    $error = (isset($body['error']) && is_array($body['error'])) ? $body['error'] : array();

    $message = '';
    if (isset($error['message']) && is_scalar($error['message'])) {
        $message = trim((string) $error['message']);
    } elseif ($status === 0) {
        $message = isset($response['error']) ? trim((string) $response['error']) : '';
    }

    // Stripe not reached at all has a code of its own, so the visitor reads
    // the site's sentence and the log keeps the transport's words.
    $code = ($status === 0) ? 'unreached' : '';
    foreach (array('decline_code', 'code') as $code_key) {
        if (($code === '') && isset($error[$code_key]) && is_scalar($error[$code_key])) {
            $code = trim((string) $error[$code_key]);
        }
    }

    return array(
        'ok'      => ($status >= 200) && ($status < 300) && ($body !== array()) && ($error === array()),
        'status'  => $status,
        'body'    => $body,
        'message' => $message,
        'code'    => $code,
    );
}

/**
 * The sentence the visitor reads for a refused payment: one of the site's own
 * for the common reasons, Stripe's words otherwise.
 *
 * @param string $code    a decline code or error code
 * @param string $message Stripe's message
 * @return string
 */
function pg_payment_stripe_visitor_error($code, $message)
{
    switch ((string) $code) {
        case 'unreached':
            return lang('The payment service could not be reached.');

        case 'insufficient_funds':
            return lang('Your card has insufficient funds.');

        case 'expired_card':
            return lang('Your card has expired.');

        case 'incorrect_cvc':
        case 'invalid_cvc':
            return lang('Your card\'s security code is incorrect.');

        case 'incorrect_number':
        case 'invalid_number':
            return lang('Your card number is incorrect.');

        case 'processing_error':
            return lang('An error occurred while processing your card. Please try again.');

        case 'authentication_required':
        case 'payment_intent_authentication_failure':
            return lang('Your card could not be verified. Please try again or use another card.');

        case 'card_declined':
        case 'generic_decline':
        case 'do_not_honor':
            return lang('Your card was declined.');
    }

    return ($message !== '') ? $message : lang('The payment was not accepted.');
}

/**
 * A refused payment's answer, with the line the activity log keeps.
 *
 * @param array  $order
 * @param string $error   the visitor's sentence
 * @param string $message Stripe's words, for the log
 * @param string $code
 * @param array  $state   what to keep waiting with (empty ends the wait)
 * @return array
 */
function pg_payment_stripe_refused($order, $error, $message, $code, $state = array())
{
    $order_id = (int) pg_payment_stripe_order_value($order, 'id');
    $detail = ($message !== '') ? $message : $error;

    return array(
        'success' => false,
        'error'   => $error,
        'state'   => $state,
        'log'     => array('Stripe payment for order #' . $order_id . ' was not accepted: ' . $detail . (($code !== '') ? ' (' . $code . ')' : '') . '.'),
        'raw'     => array('message' => $message, 'code' => $code),
    );
}

/**
 * One value of the orders row, '' when the row does not have it.
 *
 * @param array  $order
 * @param string $name
 * @return string
 */
function pg_payment_stripe_order_value($order, $name)
{
    return (is_array($order) && isset($order[$name]) && is_scalar($order[$name])) ? (string) $order[$name] : '';
}

/**
 * A card brand as the order screens have always named it.
 *
 * @param string $brand Stripe's card.brand
 * @return string
 */
function pg_payment_stripe_card_brand($brand)
{
    $names = array(
        'visa'       => 'Visa',
        'mastercard' => 'MasterCard',
        'amex'       => 'American Express',
        'diners'     => 'Diners Club',
        'discover'   => 'Discover Card',
        'jcb'        => 'JCB',
        'unionpay'   => 'UnionPay',
        'cartes_bancaires' => 'Cartes Bancaires',
    );

    $brand = strtolower(trim((string) $brand));

    return isset($names[$brand]) ? $names[$brand] : (($brand !== '' && $brand !== 'unknown') ? ucfirst($brand) : '');
}

/**
 * The card details an order keeps, from a PaymentIntent whose payment_method
 * was expanded.
 *
 * @param array $intent
 * @return array card_brand, card_last4, card_exp_month, card_exp_year
 */
function pg_payment_stripe_card_details($intent)
{
    $card = (isset($intent['payment_method']['card']) && is_array($intent['payment_method']['card'])) ? $intent['payment_method']['card'] : array();

    return array(
        'card_brand'     => pg_payment_stripe_card_brand(isset($card['brand']) ? $card['brand'] : ''),
        'card_last4'     => isset($card['last4']) ? (string) $card['last4'] : '',
        'card_exp_month' => isset($card['exp_month']) ? (string) $card['exp_month'] : '',
        'card_exp_year'  => isset($card['exp_year']) ? (string) $card['exp_year'] : '',
    );
}

/* ---------------------------------------------------------------------------
   Operations
   --------------------------------------------------------------------------- */

/**
 * What the checkout page needs to draw the Payment Element.
 *
 * @param array $order
 * @param int   $amount   hundredths the card would be charged
 * @param array $settings pg_payment_stripe_settings()
 * @return array an answer: client = script (Stripe.js, loaded from Stripe as
 *               Stripe requires), publishable_key, amount (Stripe's units),
 *               currency, payment_method_types, locale
 */
function pg_payment_stripe_prepare_with($order, $amount, $settings)
{
    if (($settings['publishable_key'] === '') || ($settings['secret_key'] === '') || ($settings['currency'] === '')) {
        return pg_payment_failure(lang('This payment method is not available.'));
    }

    $units = pg_payment_stripe_amount($amount, $settings['currency']);

    if ($units <= 0) {
        return pg_payment_failure(lang('No payment is required for this order.'));
    }

    $client = array(
        'script'               => 'https://js.stripe.com/v3/',
        'publishable_key'      => $settings['publishable_key'],
        'amount'               => $units,
        'currency'             => $settings['currency'],
        'payment_method_types' => $settings['link'] ? array('card', 'link') : array('card'),
        // The Payment Element must be built with the capture method the
        // intent is created with (authorize()), or Stripe refuses the
        // confirmation.
        'capture_method'       => $settings['capture'],
        'locale'               => $settings['locale'],
    );

    // An order with a recurring line keeps the card for its renewals; the
    // Payment Element must say so too, or Stripe refuses the confirmation
    // token for an intent with setup_future_usage.
    if (is_array($order) && !empty($order['recurring'])) {
        $client['setup_future_usage'] = 'off_session';
    }

    return array('success' => true, 'client' => $client);
}

/**
 * Charges (or holds) the order's total with the ConfirmationToken the
 * Payment Element made, in one call: the PaymentIntent is created and
 * confirmed together.
 *
 * @param array    $order    orders row
 * @param int      $amount   hundredths: the order total
 * @param array    $payment  token (the ConfirmationToken's id), return_url,
 *                           recurring (the order has a recurring line: a
 *                           Customer is made and the card kept for it),
 *                           customer_id (the store's user id, 0 for a guest)
 * @param array    $settings pg_payment_stripe_settings()
 * @param callable $http
 * @return array an answer: paid, authorized, pending, or redirect to the
 *               card's 3-D Secure page with state for complete(); card_ref
 *               when the card was kept
 */
function pg_payment_stripe_authorize_with($order, $amount, $payment, $settings, $http)
{
    if (($settings['secret_key'] === '') || ($settings['currency'] === '')) {
        return pg_payment_failure(lang('This payment method is not available.'));
    }

    $token = (is_array($payment) && isset($payment['token']) && is_string($payment['token'])) ? trim($payment['token']) : '';

    if (preg_match('/^ctoken_[A-Za-z0-9_]{1,250}$/', $token) !== 1) {
        return pg_payment_failure(lang('Please enter your card details.'));
    }

    $order_id = pg_payment_stripe_order_value($order, 'id');
    $units = pg_payment_stripe_amount($amount, $settings['currency']);

    if (($order_id === '') || ($units <= 0)) {
        return pg_payment_failure(lang('The payment was not accepted.'));
    }

    $params = array(
        'amount'               => $units,
        'currency'             => $settings['currency'],
        'confirm'              => true,
        'confirmation_token'   => $token,
        'payment_method_types' => $settings['link'] ? array('card', 'link') : array('card'),
        'capture_method'       => $settings['capture'],
        'metadata'             => array(
            'order_id'       => $order_id,
            'reference_code' => pg_payment_stripe_order_value($order, 'reference_code'),
        ),
        'expand'               => array('payment_method'),
    );

    // The billing e-mail as the description, as the classic Stripe code sends.
    $description = pg_payment_stripe_order_value($order, 'billing_email_address');

    if ($description !== '') {
        $params['description'] = $description;
    }

    $return_url = (isset($payment['return_url']) && is_string($payment['return_url'])) ? $payment['return_url'] : '';

    if ($return_url !== '') {
        $params['return_url'] = $return_url;
    }

    if (!empty($payment['recurring'])) {
        $customer = pg_payment_stripe_customer_with($order, $payment, $token, $settings, $http);

        if (!$customer['ok']) {
            return pg_payment_stripe_refused($order, pg_payment_stripe_visitor_error($customer['code'], $customer['message']), $customer['message'], $customer['code']);
        }

        $params['customer'] = $customer['id'];
        $params['setup_future_usage'] = 'off_session';
    }

    // A second post of the same token (a double click, a retried request) is
    // the same request to Stripe, which answers it with the first result
    // instead of charging again.
    $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/payment_intents', $params, 'pg-pi-' . sha1($order_id . '|' . $token . '|' . $units));

    if (!$call['ok']) {
        return pg_payment_stripe_refused($order, pg_payment_stripe_visitor_error($call['code'], $call['message']), $call['message'], $call['code']);
    }

    return pg_payment_stripe_intent_answer($order, $amount, $call['body'], $settings, $http, true);
}

/**
 * The visitor is back from the card's 3-D Secure page. The PaymentIntent is
 * read again from Stripe: the address Stripe sent the visitor back to says
 * which one it was, but only what Stripe answers about the intent this
 * checkout started decides.
 *
 * @param array    $order
 * @param int      $amount   hundredths the order totals now
 * @param array    $return   the request (not read)
 * @param array    $state    payment_intent, order_id, amount (hundredths)
 * @param array    $settings
 * @param callable $http
 * @return array an answer
 */
function pg_payment_stripe_complete_with($order, $amount, $return, $state, $settings, $http)
{
    $state = is_array($state) ? $state : array();
    $intent_id = (isset($state['payment_intent']) && is_string($state['payment_intent'])) ? $state['payment_intent'] : '';
    $order_id = pg_payment_stripe_order_value($order, 'id');

    // Nothing is asked of Stripe for a return this checkout did not start;
    // the wait goes on, so the real return can still finish it.
    if ((preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $intent_id) !== 1) || ($order_id === '') || (!isset($state['order_id']) || ((string) $state['order_id'] !== $order_id))) {
        return array('success' => false, 'error' => lang('The payment could not be verified. Please try again.'), 'state' => $state);
    }

    $call = pg_payment_stripe_call($http, $settings, 'GET', '/v1/payment_intents/' . $intent_id, array('expand' => array('payment_method')));

    if (!$call['ok']) {
        return pg_payment_stripe_refused($order, lang('The payment could not be verified. Please try again.'), $call['message'], $call['code'], $state);
    }

    $intent = $call['body'];
    $started = isset($state['amount']) ? (int) $state['amount'] : -1;

    // The order changed while the visitor was at their bank: what was
    // authorised is not what the order now costs, so it is given back.
    if ($started !== (int) $amount) {
        $answer = pg_payment_stripe_intent_answer($order, $started, $intent, $settings, $http, false);

        if (!$answer['success']) {
            return $answer;
        }

        $void = pg_payment_stripe_void_with($intent_id, $settings, $http);

        // When the payment could not be undone, the visitor is not told it
        // was: the money is still taken or held until the store refunds it.
        return array(
            'success' => false,
            'error'   => $void['cancelled']
                ? lang('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.')
                : lang('The order total changed while the payment was being verified, and the payment could not be undone automatically. The store will refund it. Please review your order before paying again.'),
            'log'     => array_merge(
                array('Stripe payment ' . $intent_id . ' for order #' . (int) $order_id . ' was for ' . pg_payment_decimal($started) . ', the order now totals ' . pg_payment_decimal($amount) . '.'),
                $void['log']),
        );
    }

    return pg_payment_stripe_intent_answer($order, $amount, $intent, $settings, $http, false);
}

/**
 * Reads a PaymentIntent Stripe answered with into an answer, after checking
 * that it is this order's intent for this amount.
 *
 * @param array    $order
 * @param int      $amount     hundredths the intent must be for
 * @param array    $intent     the PaymentIntent
 * @param array    $settings
 * @param callable $http
 * @param bool     $can_redirect authorize(): a 3-D Secure page may follow;
 *                               complete(): the visitor has been there
 * @return array
 */
function pg_payment_stripe_intent_answer($order, $amount, $intent, $settings, $http, $can_redirect)
{
    $order_id = pg_payment_stripe_order_value($order, 'id');
    $intent_id = (isset($intent['id']) && is_string($intent['id'])) ? $intent['id'] : '';
    $status = (isset($intent['status']) && is_string($intent['status'])) ? $intent['status'] : '';
    $units = pg_payment_stripe_amount($amount, $settings['currency']);

    $belongs = (preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $intent_id) === 1)
        && (isset($intent['object']) && ($intent['object'] === 'payment_intent'))
        && (isset($intent['metadata']['order_id']) && ((string) $intent['metadata']['order_id'] === $order_id))
        && (isset($intent['amount']) && ((int) $intent['amount'] === $units))
        && (isset($intent['currency']) && (strtolower((string) $intent['currency']) === $settings['currency']));

    if (!$belongs) {
        return pg_payment_stripe_refused($order, lang('The payment service gave an answer that cannot be read. Check the payment on the service\'s own screen before trying again.'), 'PaymentIntent ' . ($intent_id !== '' ? $intent_id : '(none)') . ' does not match the order', '');
    }

    $card = pg_payment_stripe_card_details($intent);
    $paid = array_merge($card, array(
        'success'        => true,
        'transaction_id' => $intent_id,
        'amount'         => (int) $amount,
        'card_ref'       => pg_payment_stripe_intent_card_ref($intent),
    ));

    switch ($status) {
        case 'succeeded':
            return array_merge($paid, array('status' => 'paid'));

        case 'requires_capture':
            return array_merge($paid, array('status' => 'authorized'));

        // Settles later; the order is placed and the payment is the service's
        // to report.
        case 'processing':
            return array_merge($paid, array('status' => 'pending'));

        case 'requires_action':
            $next = (isset($intent['next_action']) && is_array($intent['next_action'])) ? $intent['next_action'] : array();
            $url = (isset($next['type'], $next['redirect_to_url']['url']) && ($next['type'] === 'redirect_to_url') && is_string($next['redirect_to_url']['url']))
                ? $next['redirect_to_url']['url']
                : '';

            if ($can_redirect && (preg_match('#^https://[^\s/]+#i', $url) === 1)) {
                return array(
                    'success'        => true,
                    'status'         => 'redirect',
                    'transaction_id' => $intent_id,
                    'redirect_url'   => $url,
                    'state'          => array('payment_intent' => $intent_id, 'order_id' => $order_id, 'amount' => (int) $amount),
                );
            }

            // A step this checkout cannot show (or a 3-D Secure the visitor
            // came back from without finishing): the intent is let go.
            pg_payment_stripe_void_with($intent_id, $settings, $http);

            return pg_payment_stripe_refused(
                $order,
                $can_redirect ? lang('This payment needs a step this checkout cannot show. Please choose another payment method.') : lang('Your card could not be verified. Please try again or use another card.'),
                'PaymentIntent ' . $intent_id . ' still requires action' . (isset($next['type']) && is_string($next['type']) ? ' (' . $next['type'] . ')' : ''),
                '');

        case 'requires_payment_method':
            $error = (isset($intent['last_payment_error']) && is_array($intent['last_payment_error'])) ? $intent['last_payment_error'] : array();
            $message = (isset($error['message']) && is_scalar($error['message'])) ? (string) $error['message'] : '';
            $code = '';
            foreach (array('decline_code', 'code') as $code_key) {
                if (($code === '') && isset($error[$code_key]) && is_scalar($error[$code_key])) {
                    $code = (string) $error[$code_key];
                }
            }

            return pg_payment_stripe_refused($order, pg_payment_stripe_visitor_error($code, $message), $message, $code);

        case 'canceled':
            return pg_payment_stripe_refused($order, lang('The payment was cancelled.'), 'PaymentIntent ' . $intent_id . ' was canceled', '');
    }

    return pg_payment_stripe_refused($order, lang('The payment was not accepted.'), 'PaymentIntent ' . $intent_id . ' is ' . ($status !== '' ? $status : '(no status)'), '');
}

/**
 * Lets go of a PaymentIntent the order will not be placed with: an intent
 * that has not taken money is cancelled, one that has is refunded in full.
 *
 * @param string   $intent_id
 * @param array    $settings
 * @param callable $http
 * @return array cancelled (bool), reference (the intent's or the refund's
 *               id), log (one line), message (Stripe's words on a refusal)
 */
function pg_payment_stripe_void_with($intent_id, $settings, $http)
{
    $read = pg_payment_stripe_call($http, $settings, 'GET', '/v1/payment_intents/' . $intent_id);
    $status = ($read['ok'] && isset($read['body']['status']) && is_string($read['body']['status'])) ? $read['body']['status'] : '';

    if ($status === 'canceled') {
        return array('cancelled' => true, 'reference' => $intent_id, 'message' => '', 'log' => array('Stripe payment ' . $intent_id . ' was already cancelled.'));
    }

    if ($status === 'succeeded') {
        $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/refunds', array('payment_intent' => $intent_id, 'reason' => 'requested_by_customer'));
        $reference = ($call['ok'] && isset($call['body']['id']) && is_string($call['body']['id'])) ? $call['body']['id'] : '';
        $refund_status = ($call['ok'] && isset($call['body']['status']) && is_string($call['body']['status'])) ? $call['body']['status'] : '';

        if (in_array($refund_status, array('succeeded', 'pending'), true)) {
            return array('cancelled' => true, 'reference' => $reference, 'message' => '', 'log' => array('Stripe payment ' . $intent_id . ' was refunded in full (refund ' . $reference . ').'));
        }

        $message = ($call['message'] !== '') ? $call['message'] : 'refund ' . ($refund_status !== '' ? $refund_status : 'not made');

        return array('cancelled' => false, 'reference' => '', 'message' => $message, 'log' => array('Stripe payment ' . $intent_id . ' could not be refunded. MANUAL REFUND REQUIRED. Gateway message: ' . $message));
    }

    $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/payment_intents/' . $intent_id . '/cancel', array('cancellation_reason' => 'requested_by_customer'));

    if ($call['ok'] && isset($call['body']['status']) && ($call['body']['status'] === 'canceled')) {
        return array('cancelled' => true, 'reference' => $intent_id, 'message' => '', 'log' => array('Stripe payment ' . $intent_id . ' was cancelled.'));
    }

    $message = ($call['message'] !== '') ? $call['message'] : (($read['message'] !== '') ? $read['message'] : '(no message)');

    return array('cancelled' => false, 'reference' => '', 'message' => $message, 'log' => array('Stripe payment ' . $intent_id . ' could not be cancelled. MANUAL REFUND REQUIRED. Gateway message: ' . $message));
}

/**
 * Gives back part or all of a payment. A payment the classic Stripe code took
 * (a charge, ch_…) is refunded by its charge.
 *
 * @param array    $order    orders row (transaction_id)
 * @param int      $amount   hundredths to give back
 * @param array    $settings
 * @param callable $http
 * @return array an answer: refunded (or pending), with Stripe's refund id
 */
function pg_payment_stripe_refund_with($order, $amount, $settings, $http)
{
    $transaction_id = pg_payment_stripe_order_value($order, 'transaction_id');
    $units = pg_payment_stripe_amount($amount, $settings['currency']);

    if (preg_match('/^(pi|ch)_[A-Za-z0-9]{1,250}$/', $transaction_id, $kind) !== 1) {
        return pg_payment_failure(lang('This payment cannot be refunded here. Refund it on Stripe\'s own screen.'));
    }

    if ($units <= 0) {
        return pg_payment_failure(lang('The refund amount must be greater than 0.'));
    }

    $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/refunds', array(
        (($kind[1] === 'ch') ? 'charge' : 'payment_intent') => $transaction_id,
        'amount'   => $units,
        'metadata' => array('order_id' => pg_payment_stripe_order_value($order, 'id')),
    ));

    $refund_status = ($call['ok'] && isset($call['body']['status']) && is_string($call['body']['status'])) ? $call['body']['status'] : '';
    $reference = ($call['ok'] && isset($call['body']['id']) && is_string($call['body']['id'])) ? $call['body']['id'] : '';

    if (!in_array($refund_status, array('succeeded', 'pending'), true) || ($reference === '')) {
        $message = ($call['message'] !== '') ? $call['message'] : 'refund ' . ($refund_status !== '' ? $refund_status : 'not made');

        return pg_payment_failure(($call['message'] !== '') ? $call['message'] : lang('The payment was not accepted.'), array('message' => $message));
    }

    $given = pg_payment_stripe_hundredths(isset($call['body']['amount']) ? $call['body']['amount'] : null, $settings['currency']);

    return array(
        'success'          => true,
        'status'           => ($refund_status === 'pending') ? 'pending' : 'refunded',
        'amount'           => ($given !== null) ? $given : (int) $amount,
        'refund_reference' => $reference,
    );
}

/**
 * Voids a cancelled order's payment: cancelled while it has not taken money,
 * refunded in full once it has.
 *
 * @param array    $order
 * @param string   $transaction_id
 * @param array    $settings
 * @param callable $http
 * @return array an answer: cancelled, with the intent's or the refund's id
 */
function pg_payment_stripe_cancel_with($order, $transaction_id, $settings, $http)
{
    $transaction_id = (string) $transaction_id;

    if (preg_match('/^ch_[A-Za-z0-9]{1,250}$/', $transaction_id) === 1) {
        $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/refunds', array('charge' => $transaction_id, 'reason' => 'requested_by_customer'));
        $refund_status = ($call['ok'] && isset($call['body']['status']) && is_string($call['body']['status'])) ? $call['body']['status'] : '';

        if (in_array($refund_status, array('succeeded', 'pending'), true)) {
            return array('success' => true, 'status' => 'cancelled', 'refund_reference' => (string) $call['body']['id']);
        }

        return pg_payment_failure(($call['message'] !== '') ? $call['message'] : lang('The payment was not accepted.'), array('message' => $call['message']));
    }

    if (preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $transaction_id) !== 1) {
        return pg_payment_failure(lang('This payment cannot be refunded here. Refund it on Stripe\'s own screen.'), array('message' => 'not a Stripe payment id: ' . $transaction_id));
    }

    $void = pg_payment_stripe_void_with($transaction_id, $settings, $http);

    if (!$void['cancelled']) {
        return pg_payment_failure($void['message'], array('message' => $void['message']));
    }

    return array('success' => true, 'status' => 'cancelled', 'refund_reference' => $void['reference']);
}

/* ---------------------------------------------------------------------------
   Store-run subscriptions
   --------------------------------------------------------------------------- */

/**
 * A kept card as one card_ref: Stripe charges it by the Customer and the
 * payment method together.
 *
 * @param string $customer       cus_...
 * @param string $payment_method pm_... (card_... for a card of the Charges era)
 * @return string '' when either is not Stripe's id, or the pair would not fit
 *                pg_payment_answer()'s card_ref
 */
function pg_payment_stripe_card_ref($customer, $payment_method)
{
    if ((preg_match('/^cus_[A-Za-z0-9]{1,100}$/', (string) $customer) !== 1)
        || (preg_match('/^(pm|card)_[A-Za-z0-9]{1,100}$/', (string) $payment_method) !== 1)) {
        return '';
    }

    $card_ref = $customer . '|' . $payment_method;

    return (strlen($card_ref) <= 150) ? $card_ref : '';
}

/**
 * The Customer and the payment method of a card_ref.
 *
 * @param mixed $card_ref
 * @return array|null array(customer, payment method), null when it is not one
 */
function pg_payment_stripe_card_ref_parts($card_ref)
{
    $parts = explode('|', is_scalar($card_ref) ? (string) $card_ref : '', 2);

    if ((count($parts) !== 2) || (pg_payment_stripe_card_ref($parts[0], $parts[1]) !== (string) $card_ref)) {
        return null;
    }

    return $parts;
}

/**
 * The card_ref of a PaymentIntent that keeps its card (setup_future_usage
 * off_session), '' for one that does not.
 *
 * @param array $intent payment_method expanded or not
 * @return string
 */
function pg_payment_stripe_intent_card_ref($intent)
{
    if (!isset($intent['setup_future_usage']) || ($intent['setup_future_usage'] !== 'off_session')) {
        return '';
    }

    $id = function ($value) {
        if (is_array($value)) {
            $value = isset($value['id']) ? $value['id'] : '';
        }

        return is_string($value) ? $value : '';
    };

    return pg_payment_stripe_card_ref($id(isset($intent['customer']) ? $intent['customer'] : ''), $id(isset($intent['payment_method']) ? $intent['payment_method'] : ''));
}

/**
 * Makes the Customer the first payment of a recurring order keeps its card
 * for. The same token asked again is the same request to Stripe, so a retried
 * post makes no second Customer.
 *
 * @param array    $order
 * @param array    $payment  customer_id
 * @param string   $token    the ConfirmationToken's id
 * @param array    $settings
 * @param callable $http
 * @return array ok, id (cus_...), message, code
 */
function pg_payment_stripe_customer_with($order, $payment, $token, $settings, $http)
{
    $order_id = pg_payment_stripe_order_value($order, 'id');
    $params = array('metadata' => array('order_id' => $order_id));

    $user_id = (int) (isset($payment['customer_id']) ? $payment['customer_id'] : 0);

    if ($user_id > 0) {
        $params['metadata']['user_id'] = (string) $user_id;
    }

    $email = pg_payment_stripe_order_value($order, 'billing_email_address');

    if ($email !== '') {
        $params['email'] = $email;
    }

    $name = trim(pg_payment_stripe_order_value($order, 'billing_first_name') . ' ' . pg_payment_stripe_order_value($order, 'billing_last_name'));

    if ($name !== '') {
        $params['name'] = $name;
    }

    $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/customers', $params, 'pg-cus-' . sha1($order_id . '|' . $token));
    $id = (isset($call['body']['id']) && is_string($call['body']['id'])) ? $call['body']['id'] : '';

    if ($call['ok'] && (preg_match('/^cus_[A-Za-z0-9]{1,100}$/', $id) !== 1)) {
        return array('ok' => false, 'id' => '', 'message' => 'Customer ' . ($id !== '' ? $id : '(none)') . ' cannot be read', 'code' => '');
    }

    return array('ok' => $call['ok'], 'id' => $id, 'message' => $call['message'], 'code' => $call['code']);
}

/**
 * The payment methods kept for a Customer, newest first, as Stripe lists them.
 *
 * @param string   $customer
 * @param array    $settings
 * @param callable $http
 * @return array|null ids; null when Stripe did not give the list
 */
function pg_payment_stripe_customer_cards_with($customer, $settings, $http)
{
    $call = pg_payment_stripe_call($http, $settings, 'GET', '/v1/customers/' . $customer . '/payment_methods', array('limit' => 100));

    if (!$call['ok'] || !isset($call['body']['data']) || !is_array($call['body']['data'])) {
        return null;
    }

    $ids = array();

    foreach ($call['body']['data'] as $method) {
        $id = (isset($method['id']) && is_string($method['id'])) ? $method['id'] : '';

        if (pg_payment_stripe_card_ref($customer, $id) !== '') {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * Where the customer comes back to from Stripe's pages: the address the
 * caller gives, else the site's home page.
 *
 * @param array $subscription
 * @return string
 */
function pg_payment_stripe_subscription_return_url($subscription)
{
    $return_url = (isset($subscription['return_url']) && is_string($subscription['return_url'])) ? $subscription['return_url'] : '';

    if (($return_url === '') && defined('URL_SCHEME') && defined('HOSTNAME_SETTING') && defined('PATH')) {
        $return_url = URL_SCHEME . HOSTNAME_SETTING . PATH;
    }

    return $return_url;
}

/**
 * A renewal Stripe did not clearly answer: it may have been charged.
 *
 * @param int    $id      the subscription's id
 * @param string $message Stripe's or the transport's words
 * @param string $code
 * @return array
 */
function pg_payment_stripe_renewal_unclear($id, $message, $code)
{
    $answer = pg_payment_unclear(lang(array(
        'string' => 'No clear answer came from the payment service ({var:1}). Check the payment on the service\'s own screen before trying again.',
        'vars'   => array(($message !== '') ? $message : lang('no answer')))),
        array('message' => $message, 'code' => $code));
    $answer['log'] = array('Stripe renewal of subscription #' . (int) $id . ' got no clear answer: ' . (($message !== '') ? $message : '(no message)') . (($code !== '') ? ' (' . $code . ')' : '') . '.');

    return $answer;
}

/**
 * A renewal Stripe refused.
 *
 * @param int    $id
 * @param string $message
 * @param string $code
 * @return array
 */
function pg_payment_stripe_renewal_refused($id, $message, $code)
{
    return array(
        'success' => false,
        'error'   => pg_payment_stripe_visitor_error($code, $message),
        'log'     => array('Stripe renewal of subscription #' . (int) $id . ' was not accepted: ' . (($message !== '') ? $message : '(no message)') . (($code !== '') ? ' (' . $code . ')' : '') . '.'),
        'raw'     => array('message' => $message, 'code' => $code),
    );
}

/**
 * Charges one period of a subscription to the kept card, off-session: the
 * customer is not there. The Customer's newest card is charged, so a card
 * added on Stripe's page (pg_payment_stripe_update_card_with()) takes over;
 * it is answered as the new card_ref for the caller to keep, and the card it
 * replaced is detached once the new one paid.
 *
 * A card whose bank asks the holder to confirm (authentication_required) is
 * confirmed again with a return address: the answer is a redirect to Stripe's
 * confirmation page, for the customer to open.
 *
 * The idempotency key is the subscription, its charge date, its retry count,
 * the card and the amount: the same attempt asked again (a cron run cut
 * short) is answered with its first result, the next step of the retry
 * ladder is a new charge.
 *
 * @param array    $subscription pg_subscription_driver_row(), card_ref opened
 * @param array    $settings     pg_payment_stripe_settings()
 * @param callable $http
 * @return array an answer: paid, pending, redirect, a refusal, or unclear
 */
function pg_payment_stripe_charge_renewal_with($subscription, $settings, $http)
{
    $subscription = is_array($subscription) ? $subscription : array();
    $card = pg_payment_stripe_card_ref_parts(isset($subscription['card_ref']) ? $subscription['card_ref'] : '');
    $id = (int) (isset($subscription['id']) ? $subscription['id'] : 0);
    $amount = (int) (isset($subscription['amount']) ? $subscription['amount'] : 0);
    $currency = strtolower(trim((string) (isset($subscription['currency']) ? $subscription['currency'] : '')));
    $currency = ($currency !== '') ? $currency : $settings['currency'];

    if ($card === null) {
        return pg_payment_failure(lang('The subscription has no stored card.'));
    }

    $units = pg_payment_stripe_amount($amount, $currency);

    if (($settings['secret_key'] === '') || ($currency === '') || ($id <= 0) || ($units <= 0)) {
        return pg_payment_failure(lang('This payment method is not available.'));
    }

    // The wallet not read leaves the stored card to be charged.
    $payment_method = $card[1];
    $cards = pg_payment_stripe_customer_cards_with($card[0], $settings, $http);

    if ($cards === array()) {
        return pg_payment_failure_logged(lang('The subscription has no stored card.'), array('Stripe renewal of subscription #' . $id . ' was not made: the customer has no card left on Stripe.'));
    }

    if ($cards !== null) {
        $payment_method = $cards[0];
    }

    $params = array(
        'amount'               => $units,
        'currency'             => $currency,
        'customer'             => $card[0],
        'payment_method'       => $payment_method,
        'off_session'          => true,
        'confirm'              => true,
        'payment_method_types' => $settings['link'] ? array('card', 'link') : array('card'),
        // Nobody is there to take a held renewal on Stripe's screen.
        'capture_method'       => 'automatic',
        // No order_id: the webhook would take the renewal for the first order.
        'metadata'             => array('subscription_id' => (string) $id),
        'description'          => 'Subscription #' . $id,
        'expand'               => array('payment_method'),
    );

    $key = 'pg-renew-' . sha1($id . '|' . (isset($subscription['next_charge_at']) ? (string) $subscription['next_charge_at'] : '') . '|'
        . (int) (isset($subscription['retry_count']) ? $subscription['retry_count'] : 0) . '|' . $payment_method . '|' . $units . '|' . $currency);
    $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/payment_intents', $params, $key);

    if (!$call['ok']) {
        if (($call['status'] === 0) || ($call['status'] >= 500)) {
            return pg_payment_stripe_renewal_unclear($id, $call['message'], $call['code']);
        }

        if ($call['code'] === 'authentication_required') {
            return pg_payment_stripe_renewal_confirmation($id, $call, $payment_method, $subscription, $settings, $http);
        }

        return pg_payment_stripe_renewal_refused($id, $call['message'], $call['code']);
    }

    $intent = $call['body'];
    $intent_id = (isset($intent['id']) && is_string($intent['id'])) ? $intent['id'] : '';
    $belongs = (preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $intent_id) === 1)
        && (isset($intent['object']) && ($intent['object'] === 'payment_intent'))
        && (isset($intent['metadata']['subscription_id']) && ((string) $intent['metadata']['subscription_id'] === (string) $id))
        && (isset($intent['amount']) && ((int) $intent['amount'] === $units))
        && (isset($intent['currency']) && (strtolower((string) $intent['currency']) === $currency));

    if (!$belongs) {
        return pg_payment_stripe_renewal_unclear($id, 'PaymentIntent ' . ($intent_id !== '' ? $intent_id : '(none)') . ' does not match the subscription', '');
    }

    $status = (isset($intent['status']) && is_string($intent['status'])) ? $intent['status'] : '';

    if (!in_array($status, array('succeeded', 'processing'), true)) {
        $error = (isset($intent['last_payment_error']) && is_array($intent['last_payment_error'])) ? $intent['last_payment_error'] : array();
        $code = isset($error['decline_code']) && is_scalar($error['decline_code']) ? (string) $error['decline_code'] : (isset($error['code']) && is_scalar($error['code']) ? (string) $error['code'] : '');
        $message = (isset($error['message']) && is_scalar($error['message'])) ? (string) $error['message'] : 'PaymentIntent ' . $intent_id . ' is ' . ($status !== '' ? $status : '(no status)');

        return pg_payment_stripe_renewal_refused($id, $message, $code);
    }

    $answer = array_merge(pg_payment_stripe_card_details($intent), array(
        'success'        => true,
        'status'         => ($status === 'succeeded') ? 'paid' : 'pending',
        'transaction_id' => $intent_id,
        'amount'         => $amount,
        'log'            => array('Stripe renewal of subscription #' . $id . ' ' . (($status === 'succeeded') ? 'was paid' : 'is processing') . ' (' . $intent_id . ').'),
    ));

    if ($payment_method !== $card[1]) {
        $answer['card_ref'] = pg_payment_stripe_card_ref($card[0], $payment_method);

        // The card it replaced is let go once the new one paid.
        $detach = pg_payment_stripe_call($http, $settings, 'POST', '/v1/payment_methods/' . $card[1] . '/detach');

        if (!$detach['ok']) {
            $answer['log'][] = 'Stripe kept the replaced card of subscription #' . $id . ': ' . (($detach['message'] !== '') ? $detach['message'] : '(no message)') . '.';
        }
    }

    return $answer;
}

/**
 * A renewal the card holder must confirm: the intent Stripe refused off-session
 * is confirmed again with a return address, which gives the confirmation page.
 *
 * @param int      $id
 * @param array    $call           pg_payment_stripe_call() of the refused charge
 * @param string   $payment_method
 * @param array    $subscription
 * @param array    $settings
 * @param callable $http
 * @return array an answer: redirect, or a refusal
 */
function pg_payment_stripe_renewal_confirmation($id, $call, $payment_method, $subscription, $settings, $http)
{
    $intent_id = (isset($call['body']['error']['payment_intent']['id']) && is_string($call['body']['error']['payment_intent']['id'])) ? $call['body']['error']['payment_intent']['id'] : '';
    $return_url = pg_payment_stripe_subscription_return_url($subscription);

    if ((preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $intent_id) !== 1) || ($return_url === '')) {
        return pg_payment_stripe_renewal_refused($id, $call['message'], 'authentication_required');
    }

    $confirm = pg_payment_stripe_call($http, $settings, 'POST', '/v1/payment_intents/' . $intent_id . '/confirm',
        array('payment_method' => $payment_method, 'return_url' => $return_url), 'pg-renew-confirm-' . sha1($intent_id . '|' . $payment_method));
    $next = (isset($confirm['body']['next_action']) && is_array($confirm['body']['next_action'])) ? $confirm['body']['next_action'] : array();
    $url = (isset($next['type'], $next['redirect_to_url']['url']) && ($next['type'] === 'redirect_to_url') && is_string($next['redirect_to_url']['url'])) ? $next['redirect_to_url']['url'] : '';

    if (!$confirm['ok'] || !isset($confirm['body']['status']) || ($confirm['body']['status'] !== 'requires_action') || (preg_match('#^https://[^\s/]+#i', $url) !== 1)) {
        return pg_payment_stripe_renewal_refused($id, ($confirm['message'] !== '') ? $confirm['message'] : $call['message'], 'authentication_required');
    }

    return array(
        'success'        => true,
        'status'         => 'redirect',
        'transaction_id' => $intent_id,
        'redirect_url'   => $url,
        'log'            => array('Stripe renewal of subscription #' . $id . ' waits for the card holder to confirm it (' . $intent_id . ').'),
        'raw'            => array('code' => 'authentication_required'),
    );
}

/**
 * Stripe's page where the customer adds a new card: Checkout in setup mode
 * for the subscription's Customer. The card added there is the Customer's
 * newest, which the next renewal charges.
 *
 * @param array    $subscription pg_subscription_driver_row(); return_url
 *                               where Stripe sends the customer back
 * @param array    $settings
 * @param callable $http
 * @param int      $now          unix time: one page a day for the same request
 * @return array an answer: redirect to Stripe's page
 */
function pg_payment_stripe_update_card_with($subscription, $settings, $http, $now)
{
    $subscription = is_array($subscription) ? $subscription : array();
    $card = pg_payment_stripe_card_ref_parts(isset($subscription['card_ref']) ? $subscription['card_ref'] : '');

    if ($card === null) {
        return pg_payment_failure(lang('The subscription has no stored card.'));
    }

    $id = (string) (int) (isset($subscription['id']) ? $subscription['id'] : 0);
    $return_url = pg_payment_stripe_subscription_return_url($subscription);

    $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/checkout/sessions', array(
        'mode'                 => 'setup',
        'customer'             => $card[0],
        'payment_method_types' => $settings['link'] ? array('card', 'link') : array('card'),
        'success_url'          => $return_url,
        'cancel_url'           => $return_url,
        'locale'               => $settings['locale'],
        'metadata'             => array('subscription_id' => $id),
        'setup_intent_data'    => array('metadata' => array('subscription_id' => $id)),
    ), 'pg-setup-' . sha1($id . '|' . $card[0] . '|' . $return_url . '|' . gmdate('Y-m-d', (int) $now)));

    $url = (isset($call['body']['url']) && is_string($call['body']['url'])) ? $call['body']['url'] : '';

    // Asking for a page changes nothing at Stripe: never unclear.
    if (!$call['ok'] || (preg_match('#^https://[^\s/]+#i', $url) !== 1)) {
        return pg_payment_failure(($call['message'] !== '') ? $call['message'] : lang('The payment service could not be reached.'), array('message' => $call['message'], 'code' => $call['code']));
    }

    return array('success' => true, 'status' => 'redirect', 'redirect_url' => $url);
}

/**
 * Lets go of the subscription's cards at Stripe: every card of its Customer
 * is detached (the one a card update added too); the subscription itself ends
 * store-side.
 *
 * @param array    $subscription pg_subscription_driver_row()
 * @param array    $settings
 * @param callable $http
 * @return array an answer: cancelled
 */
function pg_payment_stripe_subscription_cancel_with($subscription, $settings, $http)
{
    $subscription = is_array($subscription) ? $subscription : array();
    $card = pg_payment_stripe_card_ref_parts(isset($subscription['card_ref']) ? $subscription['card_ref'] : '');

    if ($card === null) {
        return pg_payment_failure(lang('The subscription has no stored card.'));
    }

    $cards = pg_payment_stripe_customer_cards_with($card[0], $settings, $http);

    foreach (($cards === null) ? array($card[1]) : $cards as $payment_method) {
        $call = pg_payment_stripe_call($http, $settings, 'POST', '/v1/payment_methods/' . $payment_method . '/detach');

        if (!$call['ok']) {
            return pg_payment_failure(($call['message'] !== '') ? $call['message'] : lang('The payment service could not be reached.'), array('message' => $call['message'], 'code' => $call['code']));
        }
    }

    return array('success' => true, 'status' => 'cancelled');
}

/* ---------------------------------------------------------------------------
   Webhook: the messages Stripe sends on its own
   --------------------------------------------------------------------------- */

/**
 * How old a signed message may be, in seconds: Stripe's own libraries use
 * five minutes, so a message caught and posted again later is refused.
 *
 * @return int
 */
function pg_payment_stripe_signature_tolerance()
{
    return 300;
}

/**
 * Checks a Stripe-Signature header: t=<unix time>,v1=<hex>[,v1=...][,v0=...].
 * v1 is HMAC-SHA256 of "<t>.<body>" with the endpoint's signing secret;
 * while a secret is rolled Stripe signs with both and sends one v1 each, so
 * any of them may match. v0 (the test-mode scheme of old) is never read.
 *
 * @param string $header
 * @param string $body     the raw request body, byte for byte
 * @param string $secret   whsec_...
 * @param int    $now      unix time
 * @return string '' when it is signed in time, else missing, invalid or stale
 */
function pg_payment_stripe_signature_check($header, $body, $secret, $now)
{
    $header = trim((string) $header);

    if ($header === '') {
        return 'missing';
    }

    $time = null;
    $signatures = array();

    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);

        if (count($pair) !== 2) {
            continue;
        }

        if (($pair[0] === 't') && (preg_match('/^[0-9]{1,12}$/', $pair[1]) === 1)) {
            $time = (int) $pair[1];
        } elseif (($pair[0] === 'v1') && (preg_match('/^[0-9a-f]{64}$/', $pair[1]) === 1)) {
            $signatures[] = $pair[1];
        }
    }

    if (($time === null) || !$signatures || ((string) $secret === '')) {
        return 'invalid';
    }

    $expected = hash_hmac('sha256', $time . '.' . (string) $body, (string) $secret);
    $matched = false;

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            $matched = true;
        }
    }

    if (!$matched) {
        return 'invalid';
    }

    // Checked after the signature, so a wrong secret is never reported as a
    // clock problem.
    if (abs((int) $now - $time) > pg_payment_stripe_signature_tolerance()) {
        return 'stale';
    }

    return '';
}

/**
 * An amount of a Stripe object as text for a log line: "47.14 TRY".
 *
 * @param mixed  $amount   Stripe's units
 * @param string $currency
 * @return string
 */
function pg_payment_stripe_log_amount($amount, $currency)
{
    $hundredths = pg_payment_stripe_hundredths($amount, $currency);

    return ($hundredths === null) ? '' : pg_payment_decimal($hundredths) . ' ' . strtoupper((string) $currency);
}

/**
 * A message Stripe sent to payment_callback.php?driver=stripe. Only a
 * message signed with the endpoint's secret is read. Three events are
 * answered; the callback decides what the store does with each (it never
 * changes the order):
 *
 *   payment_intent.succeeded  paid: the payment of an order (metadata
 *                             order_id) went through, perhaps after the
 *                             visitor left the 3-D Secure page; or a
 *                             subscription's renewal (metadata
 *                             subscription_id, order_reference
 *                             'subscription-<id>', raw.subscription_id)
 *   charge.refunded           refunded: money was given back on Stripe's
 *                             screen (amount: everything refunded so far)
 *   charge.dispute.created    pending: the card holder disputes the payment
 *
 * The log line names the event without its end; the callback finishes it
 * with the order it found and what it did (pg_payment_notify_outcome()).
 *
 * Every other event, and a payment that names no order of this store, is a
 * failure with raw.ignored set: the callback acknowledges it, so Stripe does
 * not send it again.
 *
 * @param array  $headers  lowercase name => value (pg_payment_request_headers())
 * @param string $body     the raw body
 * @param array  $settings pg_payment_stripe_settings()
 * @param int    $now      unix time
 * @return array an answer; order_reference is the order id from the
 *               metadata, or the PaymentIntent's id the order keeps as its
 *               transaction id
 */
function pg_payment_stripe_notify_with($headers, $body, $settings, $now)
{
    $header = (is_array($headers) && isset($headers['stripe-signature']) && is_string($headers['stripe-signature'])) ? $headers['stripe-signature'] : '';
    $secret = isset($settings['webhook_secret']) ? (string) $settings['webhook_secret'] : '';

    if ($secret === '') {
        return pg_payment_failure(lang('No webhook signing secret is stored for Stripe.'), array('signature' => 'no_secret'));
    }

    $check = pg_payment_stripe_signature_check($header, $body, $secret, $now);

    if ($check !== '') {
        return pg_payment_failure(lang('The message is not signed by Stripe.'), array('signature' => $check));
    }

    $event = json_decode((string) $body, true);
    $type = (is_array($event) && isset($event['type']) && is_string($event['type'])) ? $event['type'] : '';
    $object = (is_array($event) && isset($event['data']['object']) && is_array($event['data']['object'])) ? $event['data']['object'] : null;

    if (($type === '') || ($object === null)) {
        return pg_payment_failure(lang('The message cannot be read.'), array('signature' => 'valid'));
    }

    $ignored = array('success' => false, 'error' => lang('This message is not used.'), 'raw' => array('signature' => 'valid', 'ignored' => true, 'event' => $type));
    $text = function ($name) use ($object) {
        return (isset($object[$name]) && is_string($object[$name])) ? $object[$name] : '';
    };
    $currency = $text('currency');
    $raw = array('signature' => 'valid', 'event' => $type, 'id' => $text('id'), 'currency' => strtoupper($currency));

    switch ($type) {
        case 'payment_intent.succeeded':
            $order_id = (isset($object['metadata']['order_id']) && is_scalar($object['metadata']['order_id'])) ? trim((string) $object['metadata']['order_id']) : '';
            $subscription_id = (isset($object['metadata']['subscription_id']) && is_scalar($object['metadata']['subscription_id'])) ? trim((string) $object['metadata']['subscription_id']) : '';
            $amount = isset($object['amount_received']) ? $object['amount_received'] : null;

            // A renewal's payment names its subscription, never an order:
            // the callback hands it to the renewal writer, which takes it only
            // when the cron left the row waiting (a confirmation link).
            if (($order_id === '') && (preg_match('/^[1-9][0-9]{0,9}$/', $subscription_id) === 1) && (preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $text('id')) === 1)) {
                return array(
                    'success'         => true,
                    'status'          => 'paid',
                    'transaction_id'  => $text('id'),
                    'order_reference' => 'subscription-' . $subscription_id,
                    'amount'          => pg_payment_stripe_hundredths($amount, $currency),
                    'log'             => array('Stripe reports that payment ' . $text('id') . ' of ' . pg_payment_stripe_log_amount($amount, $currency) . ' for subscription ' . $subscription_id . ' succeeded'),
                    'raw'             => $raw + array('subscription_id' => $subscription_id),
                );
            }

            if ((preg_match('/^[1-9][0-9]{0,18}$/', $order_id) !== 1) || (preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $text('id')) !== 1)) {
                return $ignored;
            }

            return array(
                'success'         => true,
                'status'          => 'paid',
                'transaction_id'  => $text('id'),
                'order_reference' => $order_id,
                'amount'          => pg_payment_stripe_hundredths($amount, $currency),
                'log'             => array('Stripe reports that payment ' . $text('id') . ' of ' . pg_payment_stripe_log_amount($amount, $currency) . ' succeeded'),
                'raw'             => $raw,
            );

        case 'charge.refunded':
        case 'charge.dispute.created':
            $intent = $text('payment_intent');

            if (preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $intent) !== 1) {
                return $ignored;
            }

            $order_id = (isset($object['metadata']['order_id']) && is_scalar($object['metadata']['order_id'])) ? trim((string) $object['metadata']['order_id']) : '';

            if ($type === 'charge.refunded') {
                $amount = isset($object['amount_refunded']) ? $object['amount_refunded'] : null;
                $line = 'Stripe reports that ' . pg_payment_stripe_log_amount($amount, $currency) . ' of payment ' . $intent . ' was refunded on Stripe';
                $status = 'refunded';
            } else {
                $amount = isset($object['amount']) ? $object['amount'] : null;
                $line = 'Stripe reports a dispute of ' . pg_payment_stripe_log_amount($amount, $currency) . ' on payment ' . $intent . ' (reason: ' . (($text('reason') !== '') ? $text('reason') : 'none given') . ')';
                $status = 'pending';
            }

            return array(
                'success'         => true,
                'status'          => $status,
                'transaction_id'  => $intent,
                'order_reference' => (preg_match('/^[1-9][0-9]{0,18}$/', $order_id) === 1) ? $order_id : $intent,
                'amount'          => pg_payment_stripe_hundredths($amount, $currency),
                'log'             => array($line),
                'raw'             => $raw,
            );
    }

    return $ignored;
}
