<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - PayTR's iFrame API (the payment method "PayTR").
 *
 * A redirect driver: authorize() asks PayTR for an iframe token for the store
 * order's total and answers a page that frames PayTR's payment page. The
 * visitor pays inside the frame; PayTR then tells the store server to server
 * (payment_callback.php?driver=paytr), and that message is the only
 * confirmation: notify() checks its hash and the callback completes the
 * incomplete order (pg_order_complete_from_payment()). The pages PayTR sends
 * the visitor to afterwards confirm nothing, so the driver has no complete()
 * and no return mode.
 *
 * Every payment gets its own merchant_oid ("PG<order id>T<time>"), kept on
 * the incomplete order as its transaction id, so PayTR's message finds the
 * order and an earlier attempt's message does not complete it.
 *
 * Every operation that calls PayTR is a thin wrapper around a *_with()
 * function that takes the settings and the HTTP caller, so the flow can run
 * against a stand-in for PayTR.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_payment_paytr_capabilities()
{
    return array('partial_refund');
}

function pg_payment_paytr_fields()
{
    return array(
        array(
            'name'       => 'merchant_id',
            'label'      => lang('Merchant ID'),
            'help'       => lang('From the Information page of the PayTR Merchant Panel.'),
            'required'   => true,
            'max_length' => 32,
        ),
        array(
            'name'       => 'merchant_key',
            'type'       => 'secret',
            'label'      => lang('Merchant Key'),
            'required'   => true,
            'max_length' => 64,
        ),
        array(
            'name'       => 'merchant_salt',
            'type'       => 'secret',
            'label'      => lang('Merchant Salt'),
            'required'   => true,
            'max_length' => 64,
        ),
        array(
            'name'    => 'mode',
            'type'    => 'select',
            'label'   => lang('Mode'),
            'help'    => lang('Add the webhook address above as the Notification URL in the PayTR Merchant Panel; PayTR confirms every payment only there.'),
            'options' => array('live' => lang('Live'), 'test' => lang('Test')),
            'default' => 'live',
        ),
    );
}

function pg_payment_paytr_authorize($order, $amount, $payment)
{
    return pg_payment_paytr_authorize_with($order, $amount, $payment, pg_payment_paytr_settings() + pg_payment_paytr_return_urls($payment), 'pg_payment_paytr_request');
}

function pg_payment_paytr_refund($order, $amount, $lines)
{
    return pg_payment_paytr_refund_with($order, $amount, pg_payment_paytr_settings() + array('time' => time()), 'pg_payment_paytr_request');
}

function pg_payment_paytr_notify($headers, $body)
{
    return pg_payment_paytr_notify_with($body, pg_payment_paytr_settings());
}

/* ---------------------------------------------------------------------------
   Settings, amounts and the API
   --------------------------------------------------------------------------- */

/**
 * The store's PayTR settings.
 *
 * @return array merchant_id, merchant_key, merchant_salt, test_mode ('1' or
 *               '0'), currency (uppercase ISO code), lang ('tr' or 'en')
 */
function pg_payment_paytr_settings()
{
    $locale = strtolower(trim((string) lang(array('info' => ''))));

    return array(
        'merchant_id'   => trim(pg_payment_setting('paytr', 'merchant_id')),
        'merchant_key'  => trim(pg_payment_setting('paytr', 'merchant_key')),
        'merchant_salt' => trim(pg_payment_setting('paytr', 'merchant_salt')),
        'test_mode'     => (pg_payment_setting('paytr', 'mode') === 'test') ? '1' : '0',
        'currency'      => strtoupper(defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : ''),
        'lang'          => ($locale === 'tr') ? 'tr' : 'en',
    );
}

/**
 * Where PayTR sends the visitor after the frame: the checkout page's next
 * page after a payment, the checkout page itself after a refusal, so the
 * visitor can choose again. Neither address confirms anything.
 *
 * @param array $payment the checkout's values (basket_id: the checkout page)
 * @return array ok_url, fail_url ('' when the page is not known)
 */
function pg_payment_paytr_return_urls($payment)
{
    $page_id = (is_array($payment) && isset($payment['basket_id'])) ? (int) $payment['basket_id'] : 0;
    $page_name = ($page_id > 0) ? (string) get_page_name($page_id) : '';

    if ($page_name === '') {
        return array('ok_url' => '', 'fail_url' => '');
    }

    $base = URL_SCHEME . HOSTNAME . PATH;
    $row = function_exists('pg_order_checkout_page_row') ? pg_order_checkout_page_row($page_id) : null;
    $next_name = (is_array($row) && !empty($row['next_page_id'])) ? (string) get_page_name((int) $row['next_page_id']) : '';

    return array(
        'ok_url'   => $base . (($next_name !== '') ? $next_name : $page_name),
        'fail_url' => $base . $page_name,
    );
}

/**
 * The currency PayTR's API names for an ISO code ('' for one PayTR does not
 * take): the lira is TL there.
 *
 * @param string $iso
 * @return string
 */
function pg_payment_paytr_currency($iso)
{
    $map = array('TRY' => 'TL', 'EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP', 'RUB' => 'RUB');
    $iso = strtoupper((string) $iso);

    return isset($map[$iso]) ? $map[$iso] : '';
}

/**
 * The ISO code of a currency PayTR names in its message ('' for none).
 *
 * @param string $paytr
 * @return string
 */
function pg_payment_paytr_currency_iso($paytr)
{
    $paytr = strtoupper(trim((string) $paytr));

    if (($paytr === 'TL') || ($paytr === 'TRY')) {
        return 'TRY';
    }

    return in_array($paytr, array('EUR', 'USD', 'GBP', 'RUB'), true) ? $paytr : '';
}

/**
 * The amount PayTR is sent: whole kuruş (hundredths) as digits.
 *
 * @param int|float $hundredths
 * @return string '' for an amount that is not above zero
 */
function pg_payment_paytr_amount($hundredths)
{
    $amount = (int) round((float) $hundredths);

    return ($amount > 0) ? (string) $amount : '';
}

/**
 * The payment's own id at PayTR: letters and digits only, at most 64.
 *
 * @param int $order_id
 * @param int $time
 * @return string
 */
function pg_payment_paytr_merchant_oid($order_id, $time)
{
    return 'PG' . (int) $order_id . 'T' . (int) $time;
}

/**
 * Whether a merchant_oid is one this driver makes.
 *
 * @param mixed $oid
 * @return bool
 */
function pg_payment_paytr_oid_valid($oid)
{
    return is_string($oid) && (preg_match('/^PG[1-9][0-9]{0,18}T[0-9]{1,12}$/', $oid) === 1);
}

/**
 * The basket PayTR shows: [name, unit price as decimal text, quantity] per
 * order line with a price, base64 of the JSON.
 *
 * @param array $lines order lines as submit_order.php reads them
 * @return string
 */
function pg_payment_paytr_basket($lines)
{
    $items = array();

    foreach ((is_array($lines) ? $lines : array()) as $line) {
        $price = (isset($line['price']) && is_numeric($line['price'])) ? (int) round((float) $line['price']) : 0;
        $quantity = isset($line['quantity']) ? max(1, (int) $line['quantity']) : 1;

        if ($price <= 0) {
            continue;
        }

        $name = isset($line['short_description']) ? trim((string) $line['short_description']) : '';

        if ($name === '') {
            $name = isset($line['product_name']) ? trim((string) $line['product_name']) : '';
        }

        $items[] = array(($name !== '') ? $name : '-', pg_payment_decimal($price), $quantity);
    }

    return base64_encode((string) json_encode($items));
}

/**
 * The paytr_token of a get-token request: HMAC-SHA256, keyed with the
 * merchant key, of the fields in PayTR's order and the salt, base64.
 *
 * @param array  $fields merchant_id, user_ip, merchant_oid, email,
 *                       payment_amount, user_basket, no_installment,
 *                       max_installment, currency, test_mode
 * @param string $key
 * @param string $salt
 * @return string
 */
function pg_payment_paytr_token_hash($fields, $key, $salt)
{
    $text = '';

    foreach (array('merchant_id', 'user_ip', 'merchant_oid', 'email', 'payment_amount', 'user_basket', 'no_installment', 'max_installment', 'currency', 'test_mode') as $name) {
        $text .= isset($fields[$name]) ? (string) $fields[$name] : '';
    }

    return base64_encode(hash_hmac('sha256', $text . $salt, $key, true));
}

/**
 * The hash a callback carries: HMAC-SHA256, keyed with the merchant key, of
 * merchant_oid, salt, status and total_amount, base64.
 *
 * @param array  $post the callback's fields
 * @param string $key
 * @param string $salt
 * @return string
 */
function pg_payment_paytr_callback_hash($post, $key, $salt)
{
    $text = '';

    foreach (array('merchant_oid', '', 'status', 'total_amount') as $name) {
        $text .= ($name === '') ? $salt : (isset($post[$name]) ? (string) $post[$name] : '');
    }

    return base64_encode(hash_hmac('sha256', $text, $key, true));
}

/**
 * The paytr_token of a refund request: HMAC-SHA256, keyed with the merchant
 * key, of merchant_id, merchant_oid, return_amount and the salt, base64.
 *
 * @param array  $fields merchant_id, merchant_oid, return_amount
 * @param string $key
 * @param string $salt
 * @return string
 */
function pg_payment_paytr_refund_hash($fields, $key, $salt)
{
    $text = '';

    foreach (array('merchant_id', 'merchant_oid', 'return_amount') as $name) {
        $text .= isset($fields[$name]) ? (string) $fields[$name] : '';
    }

    return base64_encode(hash_hmac('sha256', $text . $salt, $key, true));
}

/**
 * The cURL error numbers raised before a request leaves the server (the
 * same list as the iyzico refund's): the protocol or URL is refused, the
 * proxy or host name does not resolve, no connection is made, or the TLS
 * session is never set up. A timeout, an empty reply and a send or receive
 * error are not in it: the request may have gone out.
 *
 * @return int[]
 */
function pg_payment_paytr_curl_unsent_codes()
{
    return array(1, 2, 3, 5, 6, 7, 35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 83, 90, 91);
}

/**
 * The fields of a get-token request.
 *
 * @param array  $order    the orders row
 * @param int    $amount   hundredths
 * @param array  $payment  the checkout's values (ip, lines)
 * @param array  $settings pg_payment_paytr_settings() + return URLs
 * @param string $oid      pg_payment_paytr_merchant_oid()
 * @return array
 */
function pg_payment_paytr_token_fields($order, $amount, $payment, $settings, $oid)
{
    $value = function ($name) use ($order) {
        return (is_array($order) && isset($order[$name]) && is_scalar($order[$name])) ? trim((string) $order[$name]) : '';
    };

    $fields = array(
        'merchant_id'       => $settings['merchant_id'],
        'user_ip'           => (is_array($payment) && isset($payment['ip'])) ? (string) $payment['ip'] : '',
        'merchant_oid'      => $oid,
        'email'             => $value('billing_email_address'),
        'payment_amount'    => pg_payment_paytr_amount($amount),
        'user_basket'       => pg_payment_paytr_basket(is_array($payment) && isset($payment['lines']) ? $payment['lines'] : array()),
        // One payment, no instalments: the amount PayTR takes is the total.
        'no_installment'    => '1',
        'max_installment'   => '0',
        'currency'          => pg_payment_paytr_currency($settings['currency']),
        'test_mode'         => $settings['test_mode'],
        'debug_on'          => '0',
        'user_name'         => substr(trim($value('billing_first_name') . ' ' . $value('billing_last_name')), 0, 60),
        'user_address'      => substr(trim($value('billing_address_1') . ' ' . $value('billing_address_2') . ' ' . $value('billing_city') . ' ' . $value('billing_state') . ' ' . $value('billing_country')), 0, 400),
        'user_phone'        => substr($value('billing_phone_number'), 0, 20),
        'merchant_ok_url'   => $settings['ok_url'],
        'merchant_fail_url' => $settings['fail_url'],
        'timeout_limit'     => '30',
        'lang'              => $settings['lang'],
    );

    $fields['paytr_token'] = pg_payment_paytr_token_hash($fields, $settings['merchant_key'], $settings['merchant_salt']);

    return $fields;
}

/**
 * One request to PayTR's API: the get-token API, or the refund API.
 *
 * @param array  $fields
 * @param string $endpoint 'get-token' or 'refund'
 * @return array status (the HTTP status, 0 when PayTR was not reached), body
 *               (the decoded answer, null when it was not JSON), error,
 *               errno (cURL's error number, 0 when none)
 */
function pg_payment_paytr_request($fields, $endpoint = 'get-token')
{
    $url = ($endpoint === 'refund') ? 'https://www.paytr.com/odeme/iade' : 'https://www.paytr.com/odeme/api/get-token';

    // pg_curl_run() adds the proxy, the user agent and the TLS settings;
    // PayTR's host is fixed, so no address check.
    $call = pg_curl_run('POST', $url, array(
        'headers'         => array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'),
        'body'            => http_build_query($fields, '', '&'),
        'timeout'         => 30,
        'connect_timeout' => 10,
    ));

    if (($call['status'] === 0) && ($call['error'] !== '')) {
        $hint = ($call['errno'] !== 0) ? (string) pg_curl_tls_hint($call['errno']) : '';

        return array('status' => 0, 'body' => null, 'error' => trim($call['error'] . ' ' . $hint), 'errno' => (int) $call['errno']);
    }

    $decoded = json_decode((string) $call['body'], true);

    return array('status' => (int) $call['status'], 'body' => is_array($decoded) ? $decoded : null, 'error' => '', 'errno' => 0);
}

/**
 * The page the visitor pays on: PayTR's payment page in a frame, which
 * PayTR's resizer script fits to its content.
 *
 * @param string $token PayTR's iframe token
 * @return string
 */
function pg_payment_paytr_page($token)
{
    $src = 'https://www.paytr.com/odeme/guvenli/' . rawurlencode($token);

    return '<!DOCTYPE html>'
        . '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h(lang('Payment')) . '</title>'
        . '<style>body{margin:0;padding:16px;background:#fff}iframe{display:block;width:100%;max-width:960px;min-height:600px;margin:0 auto;border:0}</style>'
        . '<script src="https://www.paytr.com/js/iframeResizer.min.js"></script>'
        . '</head><body>'
        . '<iframe src="' . h($src) . '" id="paytriframe" title="' . h(lang('Payment')) . '" scrolling="no"></iframe>'
        . '<script>if (window.iFrameResize) { iFrameResize({}, "#paytriframe"); }</script>'
        . '</body></html>';
}

/* ---------------------------------------------------------------------------
   Operations
   --------------------------------------------------------------------------- */

/**
 * Asks PayTR for an iframe token and answers the page that frames its
 * payment page. The merchant_oid becomes the order's transaction id.
 *
 * @param array    $order
 * @param int      $amount   hundredths
 * @param array    $payment  the checkout's values
 * @param array    $settings pg_payment_paytr_settings() + ok_url, fail_url;
 *                           time (optional, for the merchant_oid)
 * @param callable $http     pg_payment_paytr_request() or a stand-in
 * @return array
 */
function pg_payment_paytr_authorize_with($order, $amount, $payment, $settings, $http)
{
    if (($settings['merchant_id'] === '') || ($settings['merchant_key'] === '') || ($settings['merchant_salt'] === '')
        || ($settings['ok_url'] === '') || ($settings['fail_url'] === '')) {
        return pg_payment_failure(lang('This payment method is not available.'));
    }

    if (pg_payment_paytr_currency($settings['currency']) === '') {
        return pg_payment_failure(lang('PayTR does not take payments in this currency.'), array('message' => 'the store currency ' . (($settings['currency'] !== '') ? $settings['currency'] : '(none)') . ' is not one PayTR takes'));
    }

    $order_id = (is_array($order) && isset($order['id'])) ? (int) $order['id'] : 0;

    if (($order_id <= 0) || (pg_payment_paytr_amount($amount) === '')) {
        return pg_payment_failure(lang('The payment was not accepted.'));
    }

    $oid = pg_payment_paytr_merchant_oid($order_id, isset($settings['time']) ? (int) $settings['time'] : time());
    $response = call_user_func($http, pg_payment_paytr_token_fields($order, $amount, $payment, $settings, $oid));
    $status = (is_array($response) && isset($response['status'])) ? (int) $response['status'] : 0;
    $body = (is_array($response) && isset($response['body']) && is_array($response['body'])) ? $response['body'] : array();

    if ($status === 0) {
        $message = (is_array($response) && isset($response['error'])) ? (string) $response['error'] : '';

        return pg_payment_failure_logged(lang('The payment service could not be reached.'), array('PayTR could not be reached for order #' . $order_id . ': ' . $message));
    }

    $token = (isset($body['token']) && is_string($body['token'])) ? trim($body['token']) : '';

    if ((($body['status'] ?? '') !== 'success') || (preg_match('/^[A-Za-z0-9]{1,128}$/', $token) !== 1)) {
        $reason = (isset($body['reason']) && is_scalar($body['reason'])) ? trim((string) $body['reason']) : 'HTTP ' . $status;

        return pg_payment_failure_logged(lang('This payment method is not available.'), array('PayTR refused the payment page for order #' . $order_id . ': ' . $reason));
    }

    return array(
        'success'        => true,
        'status'         => 'redirect',
        'transaction_id' => $oid,
        'amount'         => (int) round((float) $amount),
        'html'           => pg_payment_paytr_page($token),
        'log'            => array('PayTR payment ' . $oid . ' started for order #' . $order_id . (($settings['test_mode'] === '1') ? ' (test mode)' : '') . '.'),
        'raw'            => array('merchant_oid' => $oid),
    );
}

/**
 * Reads PayTR's callback: the hash must be PayTR's, then a successful
 * payment is "paid" for the order that keeps its merchant_oid. A failed
 * payment changes nothing: the visitor may still pay in the frame. Every
 * message whose hash is PayTR's is answered "OK", or PayTR sends it again.
 *
 * @param string $body     the form-encoded body
 * @param array  $settings pg_payment_paytr_settings()
 * @return array
 */
function pg_payment_paytr_notify_with($body, $settings)
{
    if (($settings['merchant_key'] === '') || ($settings['merchant_salt'] === '')) {
        return pg_payment_failure(lang('No merchant key or salt is stored for PayTR.'), array('signature' => 'no_secret'));
    }

    $post = array();
    parse_str((string) $body, $post);

    foreach (array('merchant_oid', 'status', 'total_amount', 'hash') as $name) {
        if (!isset($post[$name]) || !is_string($post[$name]) || ($post[$name] === '')) {
            return pg_payment_failure(lang('The message cannot be read.'), array('signature' => 'unchecked'));
        }
    }

    if (!hash_equals(pg_payment_paytr_callback_hash($post, $settings['merchant_key'], $settings['merchant_salt']), $post['hash'])) {
        return pg_payment_failure(lang('The message is not signed by PayTR.'), array('signature' => 'invalid'));
    }

    $oid = $post['merchant_oid'];
    $currency = pg_payment_paytr_currency_iso(isset($post['currency']) && is_string($post['currency']) ? $post['currency'] : 'TL');
    $test = (isset($post['test_mode']) && ((string) $post['test_mode'] === '1')) ? ' (test mode)' : '';
    $raw = array('signature' => 'valid', 'status' => $post['status'], 'currency' => $currency);

    if (($post['status'] !== 'success') || !pg_payment_paytr_oid_valid($oid) || (preg_match('/^[0-9]{1,15}$/', $post['total_amount']) !== 1)) {
        return array('success' => false, 'error' => lang('This message is not used.'), 'reply' => 'OK', 'raw' => array('ignored' => true) + $raw);
    }

    return array(
        'success'         => true,
        'status'          => 'paid',
        'transaction_id'  => $oid,
        'order_reference' => $oid,
        'amount'          => (int) $post['total_amount'],
        'reply'           => 'OK',
        'log'             => array('PayTR reports that payment ' . $oid . ' of ' . pg_payment_decimal((int) $post['total_amount']) . ' ' . $currency . ' was taken' . $test),
        'raw'             => $raw,
    );
}

/**
 * Gives back all or part of a PayTR payment through PayTR's refund API. The
 * payment is the order's merchant_oid (its transaction id); the amount goes
 * as decimal text with a dot ("11.97"), as PayTR's API takes it. PayTR has no
 * id of its own for a refund, so every refund carries a reference_no of ours
 * ("PG<order id>R<time>"), which becomes the refund's reference.
 *
 * A refusal is PayTR saying no (status error or failed, with err_msg) or a
 * request that never left the server: the refund may be asked again. No
 * answer in time, or an answer that is not PayTR's JSON, is unclear: the
 * money may have gone back.
 *
 * @param array    $order    the orders row
 * @param int      $amount   hundredths to give back
 * @param array    $settings pg_payment_paytr_settings(); time (for the reference)
 * @param callable $http     pg_payment_paytr_request() or a stand-in, called
 *                           with the fields and 'refund'
 * @return array
 */
function pg_payment_paytr_refund_with($order, $amount, $settings, $http)
{
    if (($settings['merchant_id'] === '') || ($settings['merchant_key'] === '') || ($settings['merchant_salt'] === '')) {
        return pg_payment_failure(lang('No merchant key or salt is stored for PayTR.'));
    }

    $oid = (is_array($order) && isset($order['transaction_id']) && is_scalar($order['transaction_id'])) ? trim((string) $order['transaction_id']) : '';

    if (!pg_payment_paytr_oid_valid($oid)) {
        return pg_payment_failure(lang('This payment cannot be refunded here. Refund it on PayTR\'s own screen.'));
    }

    if (pg_payment_paytr_amount($amount) === '') {
        return pg_payment_failure(lang('The refund amount must be greater than 0.'));
    }

    $hundredths = (int) round((float) $amount);
    $order_id = isset($order['id']) ? (int) $order['id'] : 0;
    $reference = 'PG' . $order_id . 'R' . (isset($settings['time']) ? (int) $settings['time'] : time());

    $fields = array(
        'merchant_id'   => $settings['merchant_id'],
        'merchant_oid'  => $oid,
        'return_amount' => pg_payment_decimal($hundredths),
        'reference_no'  => $reference,
    );
    $fields['paytr_token'] = pg_payment_paytr_refund_hash($fields, $settings['merchant_key'], $settings['merchant_salt']);

    $response = call_user_func($http, $fields, 'refund');
    $status = (is_array($response) && isset($response['status'])) ? (int) $response['status'] : 0;
    $body = (is_array($response) && isset($response['body']) && is_array($response['body'])) ? $response['body'] : null;

    if ($status === 0) {
        $message = (is_array($response) && isset($response['error'])) ? trim((string) $response['error']) : '';
        $errno = (is_array($response) && isset($response['errno'])) ? (int) $response['errno'] : 0;
        $raw = array('message' => $message, 'errno' => $errno);

        if (($errno !== 0) && in_array($errno, pg_payment_paytr_curl_unsent_codes(), true)) {
            return pg_payment_failure(($message !== '') ? $message : lang('The payment service could not be reached.'), $raw);
        }

        return pg_payment_paytr_refund_unclear($message, $raw);
    }

    $result = (is_array($body) && isset($body['status']) && is_string($body['status'])) ? $body['status'] : '';

    if ($result === 'success') {
        $given = (isset($body['return_amount']) && is_scalar($body['return_amount']) && is_numeric($body['return_amount'])) ? pg_payment_hundredths($body['return_amount']) : null;
        $test = (isset($body['is_test']) && ((string) $body['is_test'] === '1')) ? ' (test mode)' : '';

        return array(
            'success'          => true,
            'status'           => 'refunded',
            'transaction_id'   => $oid,
            'amount'           => ($given !== null) ? (int) $given : $hundredths,
            'refund_reference' => $reference,
            'log'              => array('PayTR refunded ' . $fields['return_amount'] . ' of payment ' . $oid . ' for order #' . $order_id . $test . '.'),
            'raw'              => array('merchant_oid' => $oid, 'reference_no' => $reference),
        );
    }

    if (($result === 'error') || ($result === 'failed')) {
        $code = (isset($body['err_no']) && is_scalar($body['err_no'])) ? trim((string) $body['err_no']) : '';
        $message = (isset($body['err_msg']) && is_scalar($body['err_msg'])) ? trim((string) $body['err_msg']) : '';

        if (($message === '') && ($result === 'failed')) {
            $message = 'no payment ' . $oid . ' at PayTR';
        }

        return pg_payment_failure(($message !== '') ? $message : lang('The payment was not accepted.'), array('message' => $message, 'code' => $code));
    }

    return pg_payment_paytr_refund_unclear(is_array($body) ? 'HTTP ' . $status : 'HTTP ' . $status . ', not JSON', array('message' => 'HTTP ' . $status));
}

/**
 * An unclear refund answer: the money may have gone back.
 *
 * @param string $message what came back, '' for nothing
 * @param array  $raw
 * @return array
 */
function pg_payment_paytr_refund_unclear($message, $raw)
{
    return pg_payment_unclear(lang(array(
        'string' => 'No clear answer came from the payment service ({var:1}). Check the payment on the service\'s own screen before trying again.',
        'vars'   => array(($message !== '') ? $message : lang('no answer')))), $raw);
}
