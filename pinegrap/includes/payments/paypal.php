<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - PayPal Checkout on the Orders v2 API (the payment method
 * "PayPal Checkout"; the method "PayPal Express Checkout" stays the classic
 * NVP code in submit_order.php).
 *
 * A redirect driver: authorize() creates a PayPal order for the store
 * order's total and sends the visitor to PayPal to approve it. PayPal sends
 * them back to the checkout's return address, where complete() reads the
 * PayPal order again and captures it; nothing is taken before the visitor is
 * back and the store order still totals what was approved. The capture's id
 * is the order's transaction id, which refund() and cancel() give money back
 * by. No webhook is needed: the money moves only in complete(), in the
 * visitor's own request.
 *
 * The API is called over HTTPS with an OAuth access token from the REST
 * app's client ID and secret (no SDK). Every operation is a thin wrapper
 * around a *_with() function that takes the settings and the HTTP caller, so
 * the whole flow can run against a stand-in for PayPal.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_payment_paypal_capabilities()
{
    return array('partial_refund');
}

function pg_payment_paypal_fields()
{
    return array(
        array(
            'name'       => 'client_id',
            'label'      => lang('Client ID'),
            'help'       => lang('From the REST API app under Apps & Credentials in the PayPal Developer Dashboard, in the same mode as below.'),
            'required'   => true,
            'max_length' => 255,
        ),
        array(
            'name'       => 'client_secret',
            'type'       => 'secret',
            'label'      => lang('Secret'),
            'help'       => lang('The secret of the same REST API app.'),
            'required'   => true,
            'max_length' => 255,
        ),
        array(
            'name'    => 'mode',
            'type'    => 'select',
            'label'   => lang('Mode'),
            'options' => array('live' => lang('Live'), 'sandbox' => lang('Sandbox (test)')),
            'default' => 'live',
        ),
    );
}

function pg_payment_paypal_authorize($order, $amount, $payment)
{
    return pg_payment_paypal_authorize_with($order, $amount, $payment, pg_payment_paypal_settings(), 'pg_payment_paypal_request');
}

function pg_payment_paypal_complete($order, $amount, $return, $state)
{
    return pg_payment_paypal_complete_with($order, $amount, $return, $state, pg_payment_paypal_settings(), 'pg_payment_paypal_request');
}

function pg_payment_paypal_refund($order, $amount, $lines)
{
    return pg_payment_paypal_refund_with($order, $amount, pg_payment_paypal_settings(), 'pg_payment_paypal_request');
}

function pg_payment_paypal_cancel($order, $transaction_id)
{
    return pg_payment_paypal_cancel_with($order, $transaction_id, pg_payment_paypal_settings(), 'pg_payment_paypal_request');
}

/* ---------------------------------------------------------------------------
   Settings, amounts and the API
   --------------------------------------------------------------------------- */

/**
 * The store's PayPal settings.
 *
 * @return array client_id, client_secret, mode ('live' or 'sandbox'),
 *               currency (uppercase ISO code), locale ('' for PayPal's own)
 */
function pg_payment_paypal_settings()
{
    $locale = strtolower(trim((string) lang(array('info' => ''))));

    return array(
        'client_id'     => trim(pg_payment_setting('paypal', 'client_id')),
        'client_secret' => trim(pg_payment_setting('paypal', 'client_secret')),
        'mode'          => (pg_payment_setting('paypal', 'mode') === 'sandbox') ? 'sandbox' : 'live',
        'currency'      => strtoupper(defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : ''),
        'locale'        => (preg_match('/^[a-z]{2}$/', $locale) === 1) ? $locale : '',
    );
}

/**
 * The API's address for a mode.
 *
 * @param string $mode
 * @return string
 */
function pg_payment_paypal_host($mode)
{
    return ($mode === 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
}

/**
 * The currencies PayPal takes payments in (its currency codes reference).
 * The Turkish lira is not among them.
 *
 * @return array
 */
function pg_payment_paypal_currencies()
{
    return array('AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD');
}

/**
 * Store hundredths as the decimal text PayPal takes: two decimals, none for
 * the currencies PayPal counts in whole units (HUF, JPY, TWD).
 *
 * @param int|float $hundredths
 * @param string    $currency
 * @return string '' when the amount cannot be written in the currency (a
 *                fraction of a whole-unit currency)
 */
function pg_payment_paypal_value($hundredths, $currency)
{
    $hundredths = (int) round((float) $hundredths);

    if (in_array((string) $currency, array('HUF', 'JPY', 'TWD'), true)) {
        return (($hundredths % 100) === 0) ? (string) intdiv($hundredths, 100) : '';
    }

    return pg_payment_decimal($hundredths);
}

/**
 * One request to PayPal.
 *
 * @param string $method  GET or POST
 * @param string $url
 * @param array  $headers header lines
 * @param string $body    '' for none
 * @return array status (the HTTP status, 0 when PayPal was not reached),
 *               body (the decoded answer, null when it was not JSON),
 *               error (why PayPal was not reached)
 */
function pg_payment_paypal_request($method, $url, $headers, $body)
{
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'body' => null, 'error' => 'cURL is not available on this server.');
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    pg_curl_tls($ch);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!is_string($raw)) {
        $hint = function_exists('pg_curl_tls_hint') ? (string) pg_curl_tls_hint(curl_errno($ch)) : '';

        return array('status' => 0, 'body' => null, 'error' => trim(curl_error($ch) . ' ' . $hint));
    }

    $decoded = json_decode($raw, true);

    return array('status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'error' => '');
}

/**
 * Reads what the HTTP caller answered into one shape.
 *
 * @param mixed $response
 * @return array ok (bool: a 2xx answer with a body), status, body (array),
 *               message (PayPal's or the transport's words), code (the
 *               first detail's issue or the error's name; 'unreached' when
 *               PayPal did not answer)
 */
function pg_payment_paypal_read($response)
{
    $response = is_array($response) ? $response : array();
    $status = isset($response['status']) ? (int) $response['status'] : 0;
    $body = (isset($response['body']) && is_array($response['body'])) ? $response['body'] : array();
    $ok = ($status >= 200) && ($status < 300) && ($body !== array());

    $message = '';
    $code = ($status === 0) ? 'unreached' : '';

    if ($status === 0) {
        $message = isset($response['error']) ? trim((string) $response['error']) : '';
    } elseif (!$ok) {
        $detail = (isset($body['details'][0]) && is_array($body['details'][0])) ? $body['details'][0] : array();

        foreach (array($detail, $body) as $source) {
            foreach (array('issue', 'name', 'error') as $code_key) {
                if (($code === '') && isset($source[$code_key]) && is_scalar($source[$code_key])) {
                    $code = trim((string) $source[$code_key]);
                }
            }

            foreach (array('description', 'message', 'error_description') as $message_key) {
                if (($message === '') && isset($source[$message_key]) && is_scalar($source[$message_key])) {
                    $message = trim((string) $source[$message_key]);
                }
            }
        }

        if ($message === '') {
            $message = 'HTTP ' . $status;
        }
    }

    return array('ok' => $ok, 'status' => $status, 'body' => $body, 'message' => $message, 'code' => $code);
}

/**
 * An access token for the REST app.
 *
 * @param array    $settings pg_payment_paypal_settings()
 * @param callable $http
 * @return array token ('' when none), and the read answer's message and code
 */
function pg_payment_paypal_token($settings, $http)
{
    $read = pg_payment_paypal_read(call_user_func(
        $http,
        'POST',
        pg_payment_paypal_host($settings['mode']) . '/v1/oauth2/token',
        array(
            'Authorization: Basic ' . base64_encode($settings['client_id'] . ':' . $settings['client_secret']),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ),
        'grant_type=client_credentials'));

    $token = ($read['ok'] && isset($read['body']['access_token']) && is_string($read['body']['access_token'])) ? $read['body']['access_token'] : '';

    return array('token' => $token, 'message' => $read['message'], 'code' => ($token === '' && $read['code'] === '') ? 'no_token' : $read['code']);
}

/**
 * One call to PayPal's REST API with a token.
 *
 * @param callable $http
 * @param array    $settings
 * @param string   $token
 * @param string   $method
 * @param string   $path       /v2/...
 * @param array|null $body     null for none; an empty array is sent as {}
 * @param string   $request_id PayPal-Request-Id ('' for none): PayPal answers
 *                             a repeat of the same id with the first result
 * @return array pg_payment_paypal_read()
 */
function pg_payment_paypal_call($http, $settings, $token, $method, $path, $body = null, $request_id = '')
{
    $headers = array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    );

    if ($request_id !== '') {
        $headers[] = 'PayPal-Request-Id: ' . $request_id;
    }

    $json = ($body === null) ? '' : (($body === array()) ? '{}' : (string) json_encode($body));

    return pg_payment_paypal_read(call_user_func($http, ($method === 'POST') ? 'POST' : 'GET', pg_payment_paypal_host($settings['mode']) . $path, $headers, $json));
}

/**
 * One value of the orders row, '' when the row does not have it.
 *
 * @param array  $order
 * @param string $name
 * @return string
 */
function pg_payment_paypal_order_value($order, $name)
{
    return (is_array($order) && isset($order[$name]) && is_scalar($order[$name])) ? (string) $order[$name] : '';
}

/**
 * A refused payment's answer, with the line the activity log keeps.
 *
 * @param array  $order
 * @param string $error   the visitor's sentence
 * @param string $message PayPal's words, for the log
 * @param string $code
 * @param array  $state   what to keep waiting with (empty ends the wait)
 * @return array
 */
function pg_payment_paypal_refused($order, $error, $message, $code, $state = array())
{
    $detail = ($message !== '') ? $message : $error;

    return array(
        'success' => false,
        'error'   => $error,
        'state'   => $state,
        'log'     => array('PayPal Checkout payment for order #' . (int) pg_payment_paypal_order_value($order, 'id') . ' was not accepted: ' . $detail . (($code !== '') ? ' (' . $code . ')' : '') . '.'),
        'raw'     => array('message' => $message, 'code' => $code),
    );
}

/**
 * The sentence the visitor reads for a refusal.
 *
 * @param string $code
 * @return string
 */
function pg_payment_paypal_visitor_error($code)
{
    switch ((string) $code) {
        case 'unreached':
            return lang('The payment service could not be reached.');

        case 'INSTRUMENT_DECLINED':
        case 'PAYER_CANNOT_PAY':
        case 'TRANSACTION_REFUSED':
        case 'PAYEE_BLOCKED_TRANSACTION':
        case 'DECLINED':
            return lang('PayPal declined the payment. Please choose another way to pay.');
    }

    return lang('The payment was not accepted.');
}

/**
 * The first purchase unit of a PayPal order.
 *
 * @param array $paypal_order
 * @return array
 */
function pg_payment_paypal_unit($paypal_order)
{
    return (isset($paypal_order['purchase_units'][0]) && is_array($paypal_order['purchase_units'][0])) ? $paypal_order['purchase_units'][0] : array();
}

/**
 * The first capture of a PayPal order.
 *
 * @param array $paypal_order
 * @return array
 */
function pg_payment_paypal_capture_of($paypal_order)
{
    $unit = pg_payment_paypal_unit($paypal_order);

    return (isset($unit['payments']['captures'][0]) && is_array($unit['payments']['captures'][0])) ? $unit['payments']['captures'][0] : array();
}

/**
 * Whether an amount object of PayPal's is the given hundredths in the store's
 * currency.
 *
 * @param mixed  $money      array with currency_code and value
 * @param int    $hundredths
 * @param string $currency
 * @return bool
 */
function pg_payment_paypal_money_is($money, $hundredths, $currency)
{
    return is_array($money)
        && isset($money['currency_code'], $money['value'])
        && (strtoupper((string) $money['currency_code']) === $currency)
        && (pg_payment_hundredths(is_scalar($money['value']) ? (string) $money['value'] : null) === (int) $hundredths);
}

/**
 * Whether a string can be a PayPal order, capture or refund id.
 *
 * @param mixed $id
 * @return bool
 */
function pg_payment_paypal_id_valid($id)
{
    return is_string($id) && (preg_match('/^[A-Z0-9]{1,36}$/', $id) === 1);
}

/* ---------------------------------------------------------------------------
   Operations
   --------------------------------------------------------------------------- */

/**
 * Creates the PayPal order for the store order's total and sends the visitor
 * to PayPal to approve it. Nothing is taken yet.
 *
 * @param array    $order    orders row
 * @param int      $amount   hundredths: the order total
 * @param array    $payment  return_url (where PayPal sends the visitor back,
 *                           approved or not)
 * @param array    $settings pg_payment_paypal_settings()
 * @param callable $http
 * @return array an answer: redirect to PayPal, with the state complete()
 *               checks the return against
 */
function pg_payment_paypal_authorize_with($order, $amount, $payment, $settings, $http)
{
    $return_url = (is_array($payment) && isset($payment['return_url']) && is_string($payment['return_url'])) ? $payment['return_url'] : '';

    if (($settings['client_id'] === '') || ($settings['client_secret'] === '') || (preg_match('#^https?://[^\s/]+#i', $return_url) !== 1)) {
        return pg_payment_failure(lang('This payment method is not available.'));
    }

    if (!in_array($settings['currency'], pg_payment_paypal_currencies(), true)) {
        return pg_payment_paypal_refused($order, lang('PayPal does not take payments in this currency.'), 'the store currency ' . ($settings['currency'] !== '' ? $settings['currency'] : '(none)') . ' is not one PayPal takes', '');
    }

    $order_id = pg_payment_paypal_order_value($order, 'id');
    $value = pg_payment_paypal_value($amount, $settings['currency']);

    if (($order_id === '') || ((int) $amount <= 0) || ($value === '')) {
        return pg_payment_failure(lang('The payment was not accepted.'));
    }

    $token = pg_payment_paypal_token($settings, $http);

    if ($token['token'] === '') {
        return pg_payment_paypal_refused($order, ($token['code'] === 'unreached') ? lang('The payment service could not be reached.') : lang('This payment method is not available.'), $token['message'], $token['code']);
    }

    $unit = array(
        'custom_id' => $order_id,
        'amount'    => array('currency_code' => $settings['currency'], 'value' => $value),
    );

    $reference_code = pg_payment_paypal_order_value($order, 'reference_code');

    if ($reference_code !== '') {
        $unit['description'] = substr('Order ' . $reference_code, 0, 127);
    }

    // The visitor comes back to the same address whether they approved or
    // cancelled: complete() reads which from PayPal.
    $context = array(
        'return_url'          => $return_url,
        'cancel_url'          => $return_url,
        'user_action'         => 'PAY_NOW',
        'shipping_preference' => 'NO_SHIPPING',
    );

    if ($settings['locale'] !== '') {
        $context['locale'] = $settings['locale'];
    }

    $source = array('experience_context' => $context);
    $email = pg_payment_paypal_order_value($order, 'billing_email_address');

    if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
        $source['email_address'] = $email;
    }

    $call = pg_payment_paypal_call($http, $settings, $token['token'], 'POST', '/v2/checkout/orders', array(
        'intent'         => 'CAPTURE',
        'purchase_units' => array($unit),
        'payment_source' => array('paypal' => $source),
    ), 'pg-create-' . $order_id . '-' . bin2hex(random_bytes(8)));

    if (!$call['ok']) {
        return pg_payment_paypal_refused($order, pg_payment_paypal_visitor_error($call['code']), $call['message'], $call['code']);
    }

    $paypal_id = isset($call['body']['id']) ? $call['body']['id'] : '';
    $approve = '';

    foreach ((isset($call['body']['links']) && is_array($call['body']['links'])) ? $call['body']['links'] : array() as $link) {
        if (($approve === '') && is_array($link) && isset($link['rel'], $link['href']) && in_array($link['rel'], array('payer-action', 'approve'), true) && is_string($link['href'])) {
            $approve = $link['href'];
        }
    }

    if (!pg_payment_paypal_id_valid($paypal_id) || (preg_match('#^https://[^\s/]+#i', $approve) !== 1)) {
        return pg_payment_paypal_refused($order, lang('The payment service gave an answer that cannot be read. Check the payment on the service\'s own screen before trying again.'), 'PayPal order ' . (is_string($paypal_id) && ($paypal_id !== '') ? $paypal_id : '(none)') . ' came without an approval address', '');
    }

    return array(
        'success'        => true,
        'status'         => 'redirect',
        'transaction_id' => $paypal_id,
        'redirect_url'   => $approve,
        'amount'         => (int) $amount,
        'state'          => array('paypal_order' => $paypal_id, 'order_id' => $order_id, 'amount' => (int) $amount),
        'log'            => array('PayPal Checkout payment for order #' . (int) $order_id . ' was started (PayPal order ' . $paypal_id . ').'),
    );
}

/**
 * The visitor is back from PayPal. The PayPal order this checkout started is
 * read again; an approved one is captured only while the store order still
 * totals what the visitor approved. What PayPal appended to the return
 * address is not read: only what PayPal answers about the order decides.
 *
 * @param array    $order
 * @param int      $amount   hundredths the order totals now
 * @param array    $return   the request (not read)
 * @param array    $state    paypal_order, order_id, amount (hundredths)
 * @param array    $settings
 * @param callable $http
 * @return array an answer: paid or pending with the capture's id
 */
function pg_payment_paypal_complete_with($order, $amount, $return, $state, $settings, $http)
{
    $state = is_array($state) ? $state : array();
    $paypal_id = isset($state['paypal_order']) ? $state['paypal_order'] : '';
    $order_id = pg_payment_paypal_order_value($order, 'id');
    $started = isset($state['amount']) ? (int) $state['amount'] : -1;

    // Nothing is asked of PayPal for a return this checkout did not start;
    // the wait goes on, so the real return can still finish it.
    if (!pg_payment_paypal_id_valid($paypal_id) || ($order_id === '') || !isset($state['order_id']) || ((string) $state['order_id'] !== $order_id)) {
        return array('success' => false, 'error' => lang('The payment could not be verified. Please try again.'), 'state' => $state);
    }

    $token = pg_payment_paypal_token($settings, $http);

    if ($token['token'] === '') {
        return pg_payment_paypal_refused($order, lang('The payment could not be verified. Please try again.'), $token['message'], $token['code'], $state);
    }

    $read = pg_payment_paypal_call($http, $settings, $token['token'], 'GET', '/v2/checkout/orders/' . $paypal_id);

    if (!$read['ok']) {
        return pg_payment_paypal_refused($order, lang('The payment could not be verified. Please try again.'), $read['message'], $read['code'], $state);
    }

    $paypal_order = $read['body'];
    $unit = pg_payment_paypal_unit($paypal_order);

    $belongs = (isset($paypal_order['id']) && ($paypal_order['id'] === $paypal_id))
        && (isset($unit['custom_id']) && ((string) $unit['custom_id'] === $order_id))
        && pg_payment_paypal_money_is(isset($unit['amount']) ? $unit['amount'] : null, $started, $settings['currency']);

    if (!$belongs) {
        return pg_payment_paypal_refused($order, lang('The payment service gave an answer that cannot be read. Check the payment on the service\'s own screen before trying again.'), 'PayPal order ' . $paypal_id . ' does not match the order', '');
    }

    $status = (isset($paypal_order['status']) && is_string($paypal_order['status'])) ? $paypal_order['status'] : '';

    switch ($status) {
        case 'APPROVED':
            // The order changed while the visitor was at PayPal: the approved
            // payment is left uncaptured, so nothing is taken.
            if ($started !== (int) $amount) {
                return array(
                    'success' => false,
                    'error'   => lang('The order total changed while you were at PayPal. Nothing was charged. Please review your order and try again.'),
                    'log'     => array('PayPal order ' . $paypal_id . ' for order #' . (int) $order_id . ' was approved for ' . pg_payment_decimal($started) . ', the order now totals ' . pg_payment_decimal($amount) . '; it was not captured.'),
                );
            }

            // A repeat of the same capture (a reload of the return) is
            // answered by PayPal with the first result.
            $capture = pg_payment_paypal_call($http, $settings, $token['token'], 'POST', '/v2/checkout/orders/' . $paypal_id . '/capture', array(), 'pg-capture-' . $paypal_id);

            if (!$capture['ok']) {
                // Not reached, a server error or an answer that cannot be
                // read: the capture may have been made, so the wait goes on
                // and the next return reads the order again (and repeats the
                // capture under the same request id). Only PayPal's own
                // refusal ends it.
                if (pg_payment_paypal_capture_unclear($capture)) {
                    return pg_payment_paypal_refused($order, lang('The payment could not be verified. Please try again.'), $capture['message'], $capture['code'], $state);
                }

                return pg_payment_paypal_refused($order, pg_payment_paypal_visitor_error($capture['code']), $capture['message'], $capture['code']);
            }

            return pg_payment_paypal_capture_answer($order, $amount, $started, $capture['body'], $settings, $http, $token['token'], $state);

        // Captured already (the first return's answer was lost): what was
        // taken is read from the order.
        case 'COMPLETED':
            return pg_payment_paypal_capture_answer($order, $amount, $started, $paypal_order, $settings, $http, $token['token'], $state);

        case 'CREATED':
        case 'PAYER_ACTION_REQUIRED':
        case 'VOIDED':
            return pg_payment_paypal_refused($order, lang('The payment was cancelled.'), 'PayPal order ' . $paypal_id . ' is ' . $status, '');
    }

    return pg_payment_paypal_refused($order, lang('The payment was not accepted.'), 'PayPal order ' . $paypal_id . ' is ' . ($status !== '' ? $status : '(no status)'), '');
}

/**
 * Reads the capture of a PayPal order into an answer. A capture for another
 * amount than the order totals now is refunded in full.
 *
 * @param array    $order
 * @param int      $amount       hundredths the order totals now
 * @param int      $started      hundredths the PayPal order was made for
 * @param array    $paypal_order the order as the capture or a read answered
 * @param array    $settings
 * @param callable $http
 * @param string   $token
 * @param array    $state        kept when the answer does not say what was
 *                               taken
 * @return array
 */
function pg_payment_paypal_capture_answer($order, $amount, $started, $paypal_order, $settings, $http, $token, $state = array())
{
    $order_id = (int) pg_payment_paypal_order_value($order, 'id');
    $capture = pg_payment_paypal_capture_of($paypal_order);
    $capture_id = isset($capture['id']) ? $capture['id'] : '';
    $status = (isset($capture['status']) && is_string($capture['status'])) ? $capture['status'] : '';

    if (!pg_payment_paypal_id_valid($capture_id)) {
        return pg_payment_paypal_refused($order, lang('The payment service gave an answer that cannot be read. Check the payment on the service\'s own screen before trying again.'), 'PayPal order ' . (isset($paypal_order['id']) && is_string($paypal_order['id']) ? $paypal_order['id'] : '(none)') . ' has no capture', '', $state);
    }

    if (in_array($status, array('DECLINED', 'FAILED'), true)) {
        return pg_payment_paypal_refused($order, pg_payment_paypal_visitor_error('DECLINED'), 'capture ' . $capture_id . ' is ' . $status, $status);
    }

    if (!in_array($status, array('COMPLETED', 'PENDING'), true)) {
        return pg_payment_paypal_refused($order, lang('The payment was not accepted.'), 'capture ' . $capture_id . ' is ' . ($status !== '' ? $status : '(no status)'), '');
    }

    // Money was taken for another total than the order has now: it is given
    // back, and the visitor is told only what really happened.
    if (($started !== (int) $amount) || !pg_payment_paypal_money_is(isset($capture['amount']) ? $capture['amount'] : null, $amount, $settings['currency'])) {
        $void = pg_payment_paypal_refund_call($http, $settings, $token, $capture_id, null, 'pg-void-' . $capture_id);
        $given = $void['ok'] && isset($void['body']['status']) && in_array($void['body']['status'], array('COMPLETED', 'PENDING'), true);

        return array(
            'success' => false,
            'error'   => $given
                ? lang('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.')
                : lang('The order total changed while the payment was being verified, and the payment could not be undone automatically. The store will refund it. Please review your order before paying again.'),
            'log'     => array(
                'PayPal capture ' . $capture_id . ' for order #' . $order_id . ' does not match the order total ' . pg_payment_decimal($amount) . '.',
                $given
                    ? 'PayPal capture ' . $capture_id . ' was refunded in full.'
                    : 'PayPal capture ' . $capture_id . ' could not be refunded. MANUAL REFUND REQUIRED. Gateway message: ' . ($void['message'] !== '' ? $void['message'] : '(no message)'),
            ),
        );
    }

    $reason = (isset($capture['status_details']['reason']) && is_scalar($capture['status_details']['reason'])) ? (string) $capture['status_details']['reason'] : 'not given';

    return array(
        'success'        => true,
        // A pending capture (an eCheck, a review on PayPal's side) settles
        // later; the order is placed and the payment is PayPal's to finish.
        'status'         => ($status === 'PENDING') ? 'pending' : 'paid',
        'transaction_id' => $capture_id,
        'amount'         => (int) $amount,
        'log'            => ($status === 'PENDING')
            ? array('PayPal Checkout payment for order #' . $order_id . ' is PENDING on PayPal (capture ' . $capture_id . ', reason ' . $reason . '). Check it on PayPal before shipping: PayPal may still deny it.')
            : array('PayPal Checkout payment for order #' . $order_id . ' was taken (capture ' . $capture_id . ').'),
    );
}

/**
 * Whether a capture call that did not succeed may still have taken the
 * money: PayPal not reached, a server error, or an answer without PayPal's
 * error code (not JSON, an empty body).
 *
 * @param array $call pg_payment_paypal_read()
 * @return bool
 */
function pg_payment_paypal_capture_unclear($call)
{
    return ($call['code'] === 'unreached') || ($call['code'] === '') || ($call['status'] >= 500);
}

/**
 * Asks PayPal to refund a capture.
 *
 * @param callable    $http
 * @param array       $settings
 * @param string      $token
 * @param string      $capture_id
 * @param string|null $value      the amount as PayPal's text; null refunds
 *                                what is left of the capture
 * @param string      $request_id
 * @return array pg_payment_paypal_read()
 */
function pg_payment_paypal_refund_call($http, $settings, $token, $capture_id, $value, $request_id)
{
    $body = ($value === null) ? array() : array('amount' => array('currency_code' => $settings['currency'], 'value' => $value));

    return pg_payment_paypal_call($http, $settings, $token, 'POST', '/v2/payments/captures/' . $capture_id . '/refund', $body, $request_id);
}

/**
 * Gives back part or all of a payment, by its capture id.
 *
 * @param array    $order    orders row (id, transaction_id, refunded_amount)
 * @param int      $amount   hundredths to give back
 * @param array    $settings
 * @param callable $http
 * @return array an answer: refunded (or pending), with PayPal's refund id
 */
function pg_payment_paypal_refund_with($order, $amount, $settings, $http)
{
    $capture_id = pg_payment_paypal_order_value($order, 'transaction_id');

    if (!pg_payment_paypal_id_valid($capture_id)) {
        return pg_payment_failure(lang('This payment cannot be refunded here. Refund it on PayPal\'s own screen.'));
    }

    $value = pg_payment_paypal_value($amount, $settings['currency']);

    if (((int) $amount <= 0) || ($value === '')) {
        return pg_payment_failure(lang('The refund amount must be greater than 0.'));
    }

    $token = pg_payment_paypal_token($settings, $http);

    if ($token['token'] === '') {
        return pg_payment_failure(($token['message'] !== '') ? $token['message'] : lang('The payment service could not be reached.'), array('message' => $token['message']));
    }

    // The same refund asked twice before the first was recorded (a double
    // click) carries the same id, and PayPal makes it once.
    $call = pg_payment_paypal_refund_call($http, $settings, $token['token'], $capture_id, $value,
        'pg-refund-' . sha1(pg_payment_paypal_order_value($order, 'id') . '|' . $capture_id . '|' . (int) $amount . '|' . pg_payment_paypal_order_value($order, 'refunded_amount')));

    if ($call['code'] === 'unreached') {
        return pg_payment_unclear(lang('The payment service could not be reached.'), array('message' => $call['message']));
    }

    $refund_status = ($call['ok'] && isset($call['body']['status']) && is_string($call['body']['status'])) ? $call['body']['status'] : '';
    $reference = ($call['ok'] && isset($call['body']['id']) && is_string($call['body']['id'])) ? $call['body']['id'] : '';

    if (!in_array($refund_status, array('COMPLETED', 'PENDING'), true) || ($reference === '')) {
        $message = ($call['message'] !== '') ? $call['message'] : 'refund ' . ($refund_status !== '' ? $refund_status : 'not made');

        return pg_payment_failure(($call['message'] !== '') ? $call['message'] : lang('The payment was not accepted.'), array('message' => $message));
    }

    $given = pg_payment_hundredths(isset($call['body']['amount']['value']) && is_scalar($call['body']['amount']['value']) ? (string) $call['body']['amount']['value'] : null);

    return array(
        'success'          => true,
        'status'           => ($refund_status === 'PENDING') ? 'pending' : 'refunded',
        'amount'           => ($given !== null) ? $given : (int) $amount,
        'refund_reference' => $reference,
    );
}

/**
 * Voids a cancelled order's payment: a capture is refunded for what is left
 * of it.
 *
 * @param array    $order
 * @param string   $transaction_id the capture id
 * @param array    $settings
 * @param callable $http
 * @return array an answer: cancelled, with the refund's id
 */
function pg_payment_paypal_cancel_with($order, $transaction_id, $settings, $http)
{
    $capture_id = (string) $transaction_id;

    if (!pg_payment_paypal_id_valid($capture_id)) {
        return pg_payment_failure(lang('This payment cannot be refunded here. Refund it on PayPal\'s own screen.'), array('message' => 'not a PayPal capture id: ' . $capture_id));
    }

    $token = pg_payment_paypal_token($settings, $http);

    if ($token['token'] === '') {
        return pg_payment_failure(($token['message'] !== '') ? $token['message'] : lang('The payment service could not be reached.'), array('message' => $token['message']));
    }

    $call = pg_payment_paypal_refund_call($http, $settings, $token['token'], $capture_id, null, 'pg-cancel-' . $capture_id);
    $refund_status = ($call['ok'] && isset($call['body']['status']) && is_string($call['body']['status'])) ? $call['body']['status'] : '';

    if (in_array($refund_status, array('COMPLETED', 'PENDING'), true) && isset($call['body']['id']) && is_string($call['body']['id'])) {
        return array('success' => true, 'status' => 'cancelled', 'refund_reference' => $call['body']['id']);
    }

    $message = ($call['message'] !== '') ? $call['message'] : 'refund ' . ($refund_status !== '' ? $refund_status : 'not made');

    return pg_payment_failure($message, array('message' => $message));
}
