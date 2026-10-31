<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The İleti Merkezi SMS driver (REST v1, JSON): sends one message and lists
 * the sender names approved on the account.
 *
 * Every call is a POST of a JSON body that starts with
 * request.authentication {key, hash}: the API key and the API hash as the
 * İleti Merkezi panel issues them (Settings › Security › API Access). The hash
 * is not computed here; the documentation says it is passed through
 * unchanged. The answer carries response.status.code, 200 on success, which
 * mirrors the HTTP status, so the body is read whatever the status says.
 * "Allow API access" must be switched on in the panel, otherwise every call
 * answers 401.
 *
 * A message is sent once. Only an answer that shows İleti Merkezi did not
 * take it (no connection was made, a 429, a 503 "temporarily out of service")
 * says it may be tried again: after a timeout or another 5xx it may already
 * have it, and a message sent twice cannot be taken back. Such an answer is
 * reported as uncertain, apart from a refusal, which took nothing.
 *
 * Built from İleti Merkezi's public API documentation:
 * https://www.iletimerkezi.com/en/docs/api/authentication
 * https://www.iletimerkezi.com/en/docs/api/send-sms
 * https://www.iletimerkezi.com/en/docs/api/get-sender
 * https://www.iletimerkezi.com/en/docs/api/error-codes
 * https://www.toplusmsapi.com/durum-kodlari-ve-mesajlar
 *
 * Request builders and response readers are plain functions of their
 * arguments, so the tests check them without an account; the two functions
 * at the end make the calls through includes/fn/sms.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Where the REST v1 API is.
 *
 * @return string
 */
function pg_sms_iletimerkezi_host()
{
    return 'https://api.iletimerkezi.com';
}

/**
 * A number as İleti Merkezi takes it: a Turkish mobile number in twelve
 * digits (905XXXXXXXXX). Sending abroad is a separate mode switched on in the
 * account's panel, so other numbers are not passed on.
 *
 * @param string $number E.164 digits without the plus (pg_sms_number())
 * @return string '' when the number is not a Turkish mobile number
 */
function pg_sms_iletimerkezi_number($number)
{
    $number = (string) $number;

    return preg_match('/^905\d{9}$/', $number) ? $number : '';
}

/**
 * A request: the JSON body with the authentication block first.
 *
 * @param string $endpoint e.g. 'send-sms'
 * @param array  $app      pg_sms_app(): user is the API key, password the
 *                         API hash
 * @param array  $fields   what the endpoint adds next to the block
 * @return array url, headers, body
 */
function pg_sms_iletimerkezi_request($endpoint, $app, $fields = array())
{
    $request = array(
        'authentication' => array(
            'key'  => (string) $app['user'],
            'hash' => (string) $app['password'],
        ),
    ) + $fields;

    return array(
        'url'     => pg_sms_iletimerkezi_host() . '/v1/' . $endpoint . '/json',
        'headers' => array('Accept: application/json', 'Content-Type: application/json'),
        'body'    => (string) json_encode(array('request' => $request), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * The request that sends one message to one number.
 *
 * @param array  $app        pg_sms_app()
 * @param string $number     pg_sms_iletimerkezi_number()
 * @param string $text
 * @param bool   $commercial whether the message is a commercial one (İYS
 *                           checked) rather than informational
 * @return array url, headers, body
 */
function pg_sms_iletimerkezi_send_request($app, $number, $text, $commercial)
{
    // "iys" is required: "0" for an informational message, "1" for a
    // commercial one, which then names the İYS list of its recipients.
    $order = array('sender' => (string) $app['sender'], 'iys' => $commercial ? '1' : '0');

    if ($commercial) {
        $order['iysList'] = 'BIREYSEL';
    }

    // "receipents" is İleti Merkezi's own spelling; "recipients" is ignored.
    $order['message'] = array(
        'text'       => (string) $text,
        'receipents' => array('number' => array((string) $number)),
    );

    return pg_sms_iletimerkezi_request('send-sms', $app, array('order' => $order));
}

/**
 * The request that lists the sender names approved on the account. It costs
 * no credit, so it is how the settings check the key and hash.
 *
 * @param array $app pg_sms_app()
 * @return array url, headers, body
 */
function pg_sms_iletimerkezi_senders_request($app)
{
    return pg_sms_iletimerkezi_request('get-sender', $app);
}

/**
 * What an İleti Merkezi status code means, in a sentence for the operator.
 *
 * @param string $code
 * @param string $message the message İleti Merkezi sent with it
 * @return string
 */
function pg_sms_iletimerkezi_error_text($code, $message = '')
{
    switch ((string) $code) {
        case '400':
        case '404':
        case '422':
            return lang('İleti Merkezi could not read the request.');

        case '401':
            return lang('İleti Merkezi refused the API key or API hash. Check also that API access is allowed in its panel (Settings › Security › Access Permissions) and, if the account is limited to certain IP addresses, that this server\'s address is among them.');

        case '402':
            return lang('The İleti Merkezi account has no balance left.');

        case '450':
        case '459':
        case '460':
            return lang('The sender name is not approved on this İleti Merkezi account.');

        case '451':
            return lang('İleti Merkezi refused the message as a repeat: the same text went to the same number a few minutes ago.');

        case '452':
        case '466':
            return lang('İleti Merkezi refused the recipient number.');

        case '453':
        case '454':
            return lang('İleti Merkezi refused the message text: it is empty or too long.');

        case '467':
        case '468':
        case '469':
        case '470':
            return lang('İleti Merkezi refused to send the message under the İYS rules of the account.');

        case '503':
            return lang('İleti Merkezi is temporarily out of service. Try again later.');
    }

    $message = trim(preg_replace('/\s+/', ' ', strip_tags((string) $message)));

    if (function_exists('mb_substr')) {
        $message = mb_substr($message, 0, 200);
    }

    return trim(lang(array(
        'string' => 'İleti Merkezi answered with code {var:1}. {var:2}',
        'vars'   => array((string) $code, $message))));
}

/**
 * Whether a code says İleti Merkezi did not take the request and may later:
 * 503 is its only "temporarily out of service". Every other code is a
 * refusal that stays one.
 *
 * @param string $code
 * @return bool
 */
function pg_sms_iletimerkezi_code_retries($code)
{
    return (string) $code === '503';
}

/**
 * Reads the status out of an İleti Merkezi answer. A bare 401 or 403, with no
 * JSON, is the sign-in refused in front of the API and reads as code 401.
 *
 * @param int    $status HTTP status, 0 when nothing came back
 * @param string $body
 * @param string $error  the transport error when nothing came back
 * @return array data (response, array() when the body was not İleti
 *               Merkezi's JSON), code ('' when the body carries none), error
 *               ('' when the code is 200)
 */
function pg_sms_iletimerkezi_answer($status, $body, $error = '')
{
    if ((int) $status === 0) {
        return array(
            'data'  => array(),
            'code'  => '',
            'error' => lang(array('string' => 'İleti Merkezi could not be reached: {var:1}', 'vars' => array((string) $error))),
        );
    }

    // The order id may come as a JSON number longer than an integer holds.
    $data = json_decode((string) $body, true, 512, JSON_BIGINT_AS_STRING);
    $data = (is_array($data) && isset($data['response']) && is_array($data['response'])) ? $data['response'] : array();
    $code = (isset($data['status']['code']) && is_scalar($data['status']['code'])) ? trim((string) $data['status']['code']) : '';

    if (($code === '') && in_array((int) $status, array(401, 403), true)) {
        $code = '401';
    }

    if ($code === '') {
        return array(
            'data'  => $data,
            'code'  => '',
            'error' => lang(array('string' => 'İleti Merkezi sent an answer that could not be read (HTTP {var:1}).', 'vars' => array((int) $status))),
        );
    }

    if ($code === '200') {
        return array('data' => $data, 'code' => $code, 'error' => '');
    }

    $message = (isset($data['status']['message']) && is_scalar($data['status']['message'])) ? (string) $data['status']['message'] : '';

    return array('data' => $data, 'code' => $code, 'error' => pg_sms_iletimerkezi_error_text($code, $message));
}

/**
 * Reads the answer to a send.
 *
 * @param int    $status
 * @param string $body
 * @param string $error      transport error
 * @param bool   $never_sent the transport failed before anything reached
 *                           İleti Merkezi (pg_sms_http_never_sent())
 * @return array ok, id (the order id), code, error, retry (whether sending
 *               the same message again cannot deliver it twice), uncertain
 *               (İleti Merkezi may have taken it although no success came
 *               back)
 */
function pg_sms_iletimerkezi_send_parse($status, $body, $error = '', $never_sent = false)
{
    $answer = pg_sms_iletimerkezi_answer($status, $body, $error);

    if ($answer['error'] !== '') {
        $uncertain = false;

        if ((int) $status === 0) {
            $retry = (bool) $never_sent;
            $uncertain = !$retry;
        } elseif ($answer['code'] !== '') {
            // A coded answer: refused, or turned away for now; nothing taken.
            $retry = pg_sms_iletimerkezi_code_retries($answer['code']);
        } else {
            // Not İleti Merkezi's JSON: only a 429 or a 503 says the request
            // was turned away before it was taken. Any other 4xx took nothing
            // either; a success status or a server error in front of the API
            // may come after it took the message.
            $retry = in_array((int) $status, array(429, 503), true);
            $uncertain = !$retry && (((int) $status < 400) || ((int) $status >= 500));
        }

        return array('ok' => false, 'id' => '', 'code' => $answer['code'], 'error' => $answer['error'], 'retry' => $retry, 'uncertain' => $uncertain);
    }

    $id = (isset($answer['data']['order']['id']) && is_scalar($answer['data']['order']['id'])) ? trim((string) $answer['data']['order']['id']) : '';

    // 200 without an order id names nothing to ask a delivery report about.
    // Not retried: it may have been taken.
    if ($id === '') {
        return array(
            'ok'        => false,
            'id'        => '',
            'code'      => $answer['code'],
            'error'     => lang('İleti Merkezi accepted the request but returned no order id.'),
            'retry'     => false,
            'uncertain' => true,
        );
    }

    return array('ok' => true, 'id' => $id, 'code' => $answer['code'], 'error' => '', 'retry' => false, 'uncertain' => false);
}

/**
 * Reads the list of sender names, response.senders.sender: a list, or a
 * single name.
 *
 * @param int    $status
 * @param string $body
 * @param string $error transport error
 * @return array ok, senders (list of names), code, error, refused (İleti
 *               Merkezi answered and refused the account; false when it could
 *               not be asked: no answer, an unreadable one, an outage)
 */
function pg_sms_iletimerkezi_senders_parse($status, $body, $error = '')
{
    $answer = pg_sms_iletimerkezi_answer($status, $body, $error);

    if ($answer['error'] !== '') {
        return array(
            'ok'      => false,
            'senders' => array(),
            'code'    => $answer['code'],
            'error'   => $answer['error'],
            'refused' => ($answer['code'] !== '') && !pg_sms_iletimerkezi_code_retries($answer['code']),
        );
    }

    $list = isset($answer['data']['senders']['sender']) ? $answer['data']['senders']['sender'] : array();
    $list = is_array($list) ? $list : array($list);
    $senders = array();

    foreach ($list as $sender) {
        if (is_scalar($sender) && (trim((string) $sender) !== '')) {
            $senders[] = trim((string) $sender);
        }
    }

    return array('ok' => true, 'senders' => array_values(array_unique($senders)), 'code' => $answer['code'], 'error' => '', 'refused' => false);
}

/**
 * Sends one message.
 *
 * @param array  $app        pg_sms_app()
 * @param string $number     E.164 digits (pg_sms_number())
 * @param string $text
 * @param bool   $commercial
 * @return array ok, id, code, error, retry, uncertain
 */
function pg_sms_iletimerkezi_send($app, $number, $text, $commercial)
{
    $iletimerkezi_number = pg_sms_iletimerkezi_number($number);

    if ($iletimerkezi_number === '') {
        return pg_sms_failure(lang('İleti Merkezi sends to Turkish mobile numbers only.'));
    }

    $request = pg_sms_iletimerkezi_send_request($app, $iletimerkezi_number, $text, $commercial);
    $response = pg_sms_http('POST', $request);

    return pg_sms_iletimerkezi_send_parse($response['status'], $response['body'], $response['error'], pg_sms_http_never_sent(
        $response['errno'],
        isset($response['connect']) ? $response['connect'] : 0,
        isset($response['request_size']) ? $response['request_size'] : -1));
}

/**
 * The sender names approved on the account.
 *
 * @param array $app pg_sms_app()
 * @return array ok, senders, code, error, refused
 */
function pg_sms_iletimerkezi_senders($app)
{
    $response = pg_sms_http('POST', pg_sms_iletimerkezi_senders_request($app));

    return pg_sms_iletimerkezi_senders_parse($response['status'], $response['body'], $response['error']);
}
