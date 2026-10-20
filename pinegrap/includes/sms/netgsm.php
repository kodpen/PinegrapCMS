<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Netgsm SMS driver (REST v2): sends one message and lists the sender
 * names approved on the account.
 *
 * Netgsm takes the subscriber number or an API sub-user with its password as
 * Basic auth and answers with a JSON body whose "code" is "00" on success,
 * also when the HTTP status says otherwise. A message goes out under a sender
 * name (msgheader) approved on the account; "iysfilter" tells whether it is
 * informational ("0") or commercial and so subject to the İYS consent check.
 *
 * A message is sent once. Only an answer that shows Netgsm did not take it
 * (no connection was made, a 429 or 503, a limit or system code) says it may
 * be tried again: after a timeout or a 500/502/504 Netgsm may already have
 * it, and a message sent twice cannot be taken back. Such an answer is
 * reported as uncertain, apart from a refusal, which took nothing.
 *
 * Request builders and response readers are plain functions of their
 * arguments, so the tests check them without a Netgsm account; the two
 * functions at the end make the calls through includes/fn/sms.php.
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
 * Where the REST v2 API is.
 *
 * @return string
 */
function pg_sms_netgsm_host()
{
    return 'https://api.netgsm.com.tr';
}

/**
 * A number as Netgsm takes it: a Turkish mobile number in ten digits, without
 * the country code or the leading zero. Netgsm's international sending is a
 * separate permission on the account, so other numbers are not passed on.
 *
 * @param string $number E.164 digits without the plus (pg_sms_number())
 * @return string '' when the number is not a Turkish mobile number
 */
function pg_sms_netgsm_number($number)
{
    $number = (string) $number;

    return preg_match('/^905\d{9}$/', $number) ? substr($number, 2) : '';
}

/**
 * The encoding a text needs: "TR" for any character outside ASCII (Turkish
 * letters, ₺, typographic quotes), otherwise none. Sent in the default
 * alphabet, such characters would arrive garbled.
 *
 * @param string $text
 * @return string 'TR' or ''
 */
function pg_sms_netgsm_encoding($text)
{
    return preg_match('/[^\x00-\x7F]/', (string) $text) ? 'TR' : '';
}

/**
 * The request headers: Basic auth with the user code and password. A request
 * without a body names no content type: some gateways refuse a GET that does.
 *
 * @param array $app      pg_sms_app()
 * @param bool  $has_body whether a JSON body goes with it
 * @return array
 */
function pg_sms_netgsm_request_headers($app, $has_body)
{
    $headers = array(
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($app['user'] . ':' . $app['password']),
    );

    if ($has_body) {
        $headers[] = 'Content-Type: application/json';
    }

    return $headers;
}

/**
 * The request that sends one message to one number.
 *
 * @param array  $app        pg_sms_app()
 * @param string $number     pg_sms_netgsm_number()
 * @param string $text
 * @param bool   $commercial whether the message is a commercial one (İYS
 *                           checked) rather than informational
 * @return array url, headers, body
 */
function pg_sms_netgsm_send_request($app, $number, $text, $commercial)
{
    $body = array(
        'msgheader' => (string) $app['sender'],
        'messages'  => array(
            array('msg' => (string) $text, 'no' => (string) $number),
        ),
        // "0" is an informational message; "11" a commercial one to a
        // person, which Netgsm sends only where İYS holds the consent.
        'iysfilter' => $commercial ? '11' : '0',
    );

    $encoding = pg_sms_netgsm_encoding($text);

    if ($encoding !== '') {
        $body['encoding'] = $encoding;
    }

    return array(
        'url'     => pg_sms_netgsm_host() . '/sms/rest/v2/send',
        'headers' => pg_sms_netgsm_request_headers($app, true),
        'body'    => (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * The request that lists the sender names approved on the account. It costs
 * no credit, so it is how the settings check the user code and password.
 *
 * @param array $app pg_sms_app()
 * @return array url, headers
 */
function pg_sms_netgsm_senders_request($app)
{
    return array(
        'url'     => pg_sms_netgsm_host() . '/sms/rest/v2/msgheader',
        'headers' => pg_sms_netgsm_request_headers($app, false),
    );
}

/**
 * What a Netgsm result code means, in a sentence for the operator.
 *
 * @param string $code
 * @param string $description the description Netgsm sent with it
 * @return string
 */
function pg_sms_netgsm_error_text($code, $description = '')
{
    switch ((string) $code) {
        case '20':
            return lang('Netgsm refused the message text: it is empty or too long.');

        case '30':
            return lang('Netgsm refused the user code or password, or this user has no API access. An API sub-user may also be limited to certain IP addresses.');

        case '40':
        case '41':
            return lang('The sender name is not defined on this Netgsm account.');

        case '50':
        case '51':
            return lang('Netgsm refused to send the message under the İYS rules of the account.');

        case '70':
            return lang('Netgsm refused a parameter of the request.');

        case '80':
        case '85':
            return lang('Netgsm limits how often it is asked; the same message to the same number is also held back for a while. Try again later.');

        case '100':
        case '101':
            return lang('Netgsm reported a system error. Try again later.');
    }

    $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) $description)));

    if (function_exists('mb_substr')) {
        $description = mb_substr($description, 0, 200);
    }

    return trim(lang(array(
        'string' => 'Netgsm answered with code {var:1}. {var:2}',
        'vars'   => array((string) $code, $description))));
}

/**
 * Whether a code says Netgsm did not take the request and may later: a limit
 * or its own system error. A refused text, number, sender or password stays
 * refused.
 *
 * @param string $code
 * @return bool
 */
function pg_sms_netgsm_code_retries($code)
{
    return in_array((string) $code, array('80', '85', '100', '101'), true);
}

/**
 * Reads the code out of a Netgsm answer. The body is read whatever the HTTP
 * status: Netgsm sends a refusal as a JSON body with a 4xx status. A bare 401
 * or 403 is the sign-in refused in front of the API and reads as code 30.
 *
 * @param int    $status HTTP status, 0 when nothing came back
 * @param string $body
 * @param string $error  the transport error when nothing came back
 * @return array data (the decoded body, array() when it was not JSON), code
 *               ('' when the body carries none), error ('' when the code is
 *               "00")
 */
function pg_sms_netgsm_answer($status, $body, $error = '')
{
    if ((int) $status === 0) {
        return array(
            'data'  => array(),
            'code'  => '',
            'error' => lang(array('string' => 'Netgsm could not be reached: {var:1}', 'vars' => array((string) $error))),
        );
    }

    // The job id may come as a JSON number longer than an integer holds.
    $data = json_decode((string) $body, true, 512, JSON_BIGINT_AS_STRING);
    $data = is_array($data) ? $data : array();
    $code = (isset($data['code']) && is_scalar($data['code'])) ? trim((string) $data['code']) : '';

    if (($code === '') && in_array((int) $status, array(401, 403), true)) {
        $code = '30';
    }

    if ($code === '') {
        return array(
            'data'  => $data,
            'code'  => '',
            'error' => lang(array('string' => 'Netgsm sent an answer that could not be read (HTTP {var:1}).', 'vars' => array((int) $status))),
        );
    }

    if ($code === '00') {
        return array('data' => $data, 'code' => $code, 'error' => '');
    }

    $description = (isset($data['description']) && is_scalar($data['description'])) ? (string) $data['description'] : '';

    return array('data' => $data, 'code' => $code, 'error' => pg_sms_netgsm_error_text($code, $description));
}

/**
 * Reads the answer to a send.
 *
 * @param int    $status
 * @param string $body
 * @param string $error transport error
 * @param bool   $never_sent the transport failed before anything reached
 *                           Netgsm (pg_sms_http_never_sent())
 * @return array ok, id (Netgsm's job id), code, error, retry (whether sending
 *               the same message again cannot deliver it twice), uncertain
 *               (Netgsm may have taken it although no success came back)
 */
function pg_sms_netgsm_send_parse($status, $body, $error = '', $never_sent = false)
{
    $answer = pg_sms_netgsm_answer($status, $body, $error);

    if ($answer['error'] !== '') {
        $uncertain = false;

        if ((int) $status === 0) {
            $retry = (bool) $never_sent;
            $uncertain = !$retry;
        } elseif ($answer['code'] !== '') {
            // Netgsm answered with a code: it refused the message or turned
            // it away for now, and took nothing.
            $retry = pg_sms_netgsm_code_retries($answer['code']);
        } else {
            // Not Netgsm's JSON: only a 429 or a 503 says the request was
            // turned away before Netgsm took it. Any other refusal (4xx) took
            // nothing either; a success status or a server error in front of
            // Netgsm may come after it took the message.
            $retry = in_array((int) $status, array(429, 503), true);
            $uncertain = !$retry && (((int) $status < 400) || ((int) $status >= 500));
        }

        return array('ok' => false, 'id' => '', 'code' => $answer['code'], 'error' => $answer['error'], 'retry' => $retry, 'uncertain' => $uncertain);
    }

    $id = '';

    foreach (array('jobid', 'jobId', 'bulkid') as $key) {
        if (isset($answer['data'][$key]) && is_scalar($answer['data'][$key]) && ((string) $answer['data'][$key] !== '')) {
            $id = (string) $answer['data'][$key];
            break;
        }
    }

    // "00" without a job id is not a message Netgsm took on: there is nothing
    // to ask its delivery report about. Not retried: it may have been taken.
    if ($id === '') {
        return array(
            'ok'        => false,
            'id'        => '',
            'code'      => $answer['code'],
            'error'     => lang('Netgsm accepted the request but returned no job id.'),
            'retry'     => false,
            'uncertain' => true,
        );
    }

    return array('ok' => true, 'id' => $id, 'code' => $answer['code'], 'error' => '', 'retry' => false, 'uncertain' => false);
}

/**
 * Reads the list of sender names. Netgsm's answer has carried the list as
 * "msgheaders" and as "msgheader"; either is taken.
 *
 * @param int    $status
 * @param string $body
 * @param string $error transport error
 * @return array ok, senders (list of names), code, error, refused (Netgsm
 *               answered and refused the account; false when it could not be
 *               asked: no answer, an unreadable one, a limit or an outage)
 */
function pg_sms_netgsm_senders_parse($status, $body, $error = '')
{
    $answer = pg_sms_netgsm_answer($status, $body, $error);

    if ($answer['error'] !== '') {
        return array(
            'ok'      => false,
            'senders' => array(),
            'code'    => $answer['code'],
            'error'   => $answer['error'],
            'refused' => ($answer['code'] !== '') && !pg_sms_netgsm_code_retries($answer['code']),
        );
    }

    $list = array();

    foreach (array('msgheaders', 'msgheader') as $key) {
        if (isset($answer['data'][$key])) {
            $list = is_array($answer['data'][$key]) ? $answer['data'][$key] : array($answer['data'][$key]);
            break;
        }
    }

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
function pg_sms_netgsm_send($app, $number, $text, $commercial)
{
    $netgsm_number = pg_sms_netgsm_number($number);

    if ($netgsm_number === '') {
        return pg_sms_failure(lang('Netgsm sends to Turkish mobile numbers only.'));
    }

    $request = pg_sms_netgsm_send_request($app, $netgsm_number, $text, $commercial);
    $response = pg_sms_http('POST', $request);

    return pg_sms_netgsm_send_parse($response['status'], $response['body'], $response['error'], pg_sms_http_never_sent($response['errno'], isset($response['connect']) ? $response['connect'] : 0));
}

/**
 * The sender names approved on the account.
 *
 * @param array $app pg_sms_app()
 * @return array ok, senders, code, error, refused
 */
function pg_sms_netgsm_senders($app)
{
    $response = pg_sms_http('GET', pg_sms_netgsm_senders_request($app));

    return pg_sms_netgsm_senders_parse($response['status'], $response['body'], $response['error']);
}
