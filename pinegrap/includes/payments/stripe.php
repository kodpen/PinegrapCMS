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
    return array('wallets', 'partial_refund', 'threeds', 'hosted_fields');
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
 * @return array publishable_key, secret_key, link (bool), currency (lowercase
 *               ISO code), capture ('automatic' or 'manual'), locale
 */
function pg_payment_stripe_settings()
{
    $locale = strtolower(trim((string) lang(array('info' => ''))));

    return array(
        'publishable_key' => trim(pg_payment_setting('stripe', 'publishable_key')),
        'secret_key'      => trim(pg_payment_setting('stripe', 'secret_key')),
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
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'body' => null, 'error' => 'cURL is not available on this server.');
    }

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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    }

    pg_curl_tls($ch);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!is_string($raw)) {
        $hint = function_exists('pg_curl_tls_hint') ? (string) pg_curl_tls_hint(curl_errno($ch)) : '';

        return array('status' => 0, 'body' => null, 'error' => trim(curl_error($ch) . ' ' . $hint));
    }

    $body = json_decode($raw, true);

    return array('status' => $status, 'body' => is_array($body) ? $body : null, 'error' => '');
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

    return array(
        'success' => true,
        'client'  => array(
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
        ),
    );
}

/**
 * Charges (or holds) the order's total with the ConfirmationToken the
 * Payment Element made, in one call: the PaymentIntent is created and
 * confirmed together.
 *
 * @param array    $order    orders row
 * @param int      $amount   hundredths: the order total
 * @param array    $payment  token (the ConfirmationToken's id), return_url
 * @param array    $settings pg_payment_stripe_settings()
 * @param callable $http
 * @return array an answer: paid, authorized, pending, or redirect to the
 *               card's 3-D Secure page with state for complete()
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
