<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Twilio SMS driver (Programmable Messaging, REST API 2010-04-01): sends
 * one message and lists the phone numbers of the account.
 *
 * Every call signs in with HTTP Basic auth, the Account SID as the user and
 * the Auth Token as the password. A message is a form-encoded POST to the
 * account's Messages resource with To (E.164 with its plus), Body and either
 * From (one of the account's phone numbers) or MessagingServiceSid (MG…).
 * Success is 201 with the message's SID (SM…, 34 characters); a refusal is a
 * 4xx with Twilio's JSON error {code, message, more_info, status}.
 *
 * The account's IncomingPhoneNumbers list costs no credit, so it is how the
 * settings check the Account SID and Auth Token and the sender number. A
 * Messaging Service SID is taken as it is: the services live on another API,
 * and a wrong one is refused by the first message (21705).
 *
 * A message is sent once. Only an answer that shows Twilio did not take it
 * (no connection was made, 20429 "Too Many Requests", a bare 429 or 503)
 * says it may be tried again: after a timeout or another 5xx it may already
 * have it, and a message sent twice cannot be taken back. Such an answer is
 * reported as uncertain, apart from a refusal, which took nothing.
 *
 * Built from Twilio's public API documentation:
 * https://www.twilio.com/docs/usage/requests-to-twilio
 * https://www.twilio.com/docs/messaging/api/message-resource
 * https://www.twilio.com/docs/phone-numbers/api/incomingphonenumber-resource
 * https://www.twilio.com/docs/usage/twilios-response
 * https://www.twilio.com/docs/api/errors
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
 * Where the REST API is.
 *
 * @return string
 */
function pg_sms_twilio_host()
{
    return 'https://api.twilio.com';
}

/**
 * A number as Twilio takes it: E.164 with its plus. Twilio sends to every
 * country the account's geographic permissions allow, so every number
 * pg_sms_number() reads is passed on.
 *
 * @param string $number E.164 digits without the plus (pg_sms_number())
 * @return string '' when the digits are no E.164 number
 */
function pg_sms_twilio_number($number)
{
    $number = (string) $number;

    return preg_match('/^[1-9]\d{6,14}$/D', $number) ? '+' . $number : '';
}

/**
 * What a sender is: one of the account's phone numbers in E.164, or a
 * Messaging Service SID, which picks the number from the service's pool.
 *
 * @param string $sender
 * @return string 'number', 'service' or '' for neither
 */
function pg_sms_twilio_sender_kind($sender)
{
    $sender = (string) $sender;

    if (preg_match('/^\+[1-9]\d{6,14}$/D', $sender)) {
        return 'number';
    }

    return preg_match('/^MG[0-9a-fA-F]{32}$/D', $sender) ? 'service' : '';
}

/**
 * The headers of a call: JSON back, Basic auth, and the form type when a
 * body goes with it.
 *
 * @param array $app      pg_sms_app(): user is the Account SID, password the
 *                        Auth Token
 * @param bool  $has_body
 * @return array
 */
function pg_sms_twilio_headers($app, $has_body)
{
    $headers = array(
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($app['user'] . ':' . $app['password']),
    );

    if ($has_body) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    return $headers;
}

/**
 * The address of a resource under the account.
 *
 * @param array  $app
 * @param string $resource e.g. 'Messages.json'
 * @return string
 */
function pg_sms_twilio_url($app, $resource)
{
    return pg_sms_twilio_host() . '/2010-04-01/Accounts/' . rawurlencode((string) $app['user']) . '/' . $resource;
}

/**
 * The request that sends one message to one number.
 *
 * @param array  $app    pg_sms_app()
 * @param string $number pg_sms_twilio_number()
 * @param string $text
 * @return array url, headers, body
 */
function pg_sms_twilio_send_request($app, $number, $text)
{
    $fields = array('To' => (string) $number);

    if (pg_sms_twilio_sender_kind($app['sender']) === 'service') {
        $fields['MessagingServiceSid'] = (string) $app['sender'];
    } else {
        $fields['From'] = (string) $app['sender'];
    }

    $fields['Body'] = (string) $text;

    return array(
        'url'     => pg_sms_twilio_url($app, 'Messages.json'),
        'headers' => pg_sms_twilio_headers($app, true),
        'body'    => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
    );
}

/**
 * The request that lists the account's phone numbers. It costs no credit,
 * so it is how the settings check the account. A thousand numbers, the
 * largest page, is more than a site sends from.
 *
 * @param array $app pg_sms_app()
 * @return array url, headers
 */
function pg_sms_twilio_senders_request($app)
{
    return array(
        'url'     => pg_sms_twilio_url($app, 'IncomingPhoneNumbers.json?PageSize=1000'),
        'headers' => pg_sms_twilio_headers($app, false),
    );
}

/**
 * What a Twilio error code means, in a sentence for the operator.
 *
 * @param string $code
 * @param string $message the message Twilio sent with it
 * @return string
 */
function pg_sms_twilio_error_text($code, $message = '')
{
    switch ((string) $code) {
        case '20003':
            return lang('Twilio refused the Account SID or Auth Token.');

        case '20008':
            return lang('Twilio test credentials cannot send messages: enter the live Account SID and Auth Token.');

        case '20404':
            return lang('Twilio found no account with this Account SID.');

        case '20429':
            return lang('Twilio is receiving too many requests from this account. Try again later.');

        case '21211':
        case '21614':
            return lang('Twilio refused the recipient number.');

        case '21212':
        case '21606':
        case '21659':
            return lang('The sender is not an SMS-capable phone number of this Twilio account.');

        case '21408':
            return lang('Sending to this country is not enabled on the Twilio account (Messaging › Settings › Geo permissions).');

        case '21602':
        case '21617':
            return lang('Twilio refused the message text: it is empty or too long.');

        case '21608':
            return lang('A Twilio trial account sends only to verified numbers: verify the number in the Twilio Console or upgrade the account.');

        case '21610':
            return lang('The recipient has unsubscribed from this sender (replied STOP).');

        case '21612':
            return lang('Twilio cannot deliver a message from this sender to this number.');

        case '21703':
        case '21704':
        case '21705':
            return lang('The Messaging Service SID is not valid on this Twilio account, or the service has no sender.');
    }

    $message = trim(preg_replace('/\s+/', ' ', strip_tags((string) $message)));

    if (function_exists('mb_substr')) {
        $message = mb_substr($message, 0, 200);
    }

    return trim(lang(array(
        'string' => 'Twilio answered with code {var:1}. {var:2}',
        'vars'   => array((string) $code, $message))));
}

/**
 * Whether a code says Twilio did not take the request and may later: 20429
 * is its "Too Many Requests". Every other code is a refusal that stays one.
 *
 * @param string $code
 * @return bool
 */
function pg_sms_twilio_code_retries($code)
{
    return (string) $code === '20429';
}

/**
 * Reads a Twilio answer. A 2xx carries the resource; anything else carries
 * the error's code. A bare 401 or 403, with no JSON, is the sign-in refused
 * and reads as 20003.
 *
 * @param int    $status HTTP status, 0 when nothing came back
 * @param string $body
 * @param string $error  the transport error when nothing came back
 * @return array data (the decoded body, array() when it was not JSON), code
 *               ('' for a success or a body without one), error ('' on
 *               success)
 */
function pg_sms_twilio_answer($status, $body, $error = '')
{
    $status = (int) $status;

    if ($status === 0) {
        return array(
            'data'  => array(),
            'code'  => '',
            'error' => lang(array('string' => 'Twilio could not be reached: {var:1}', 'vars' => array((string) $error))),
        );
    }

    $data = json_decode((string) $body, true, 512, JSON_BIGINT_AS_STRING);
    $data = is_array($data) ? $data : array();

    if (($status >= 200) && ($status < 300) && $data) {
        return array('data' => $data, 'code' => '', 'error' => '');
    }

    $code = (($status >= 300) && isset($data['code']) && is_scalar($data['code'])) ? trim((string) $data['code']) : '';

    if (($code === '') && in_array($status, array(401, 403), true)) {
        $code = '20003';
    }

    if ($code === '') {
        return array(
            'data'  => $data,
            'code'  => '',
            'error' => lang(array('string' => 'Twilio sent an answer that could not be read (HTTP {var:1}).', 'vars' => array($status))),
        );
    }

    $message = (isset($data['message']) && is_scalar($data['message'])) ? (string) $data['message'] : '';

    return array('data' => $data, 'code' => $code, 'error' => pg_sms_twilio_error_text($code, $message));
}

/**
 * Reads the answer to a send.
 *
 * @param int    $status
 * @param string $body
 * @param string $error      transport error
 * @param bool   $never_sent the transport failed before anything reached
 *                           Twilio (pg_sms_http_never_sent())
 * @return array ok, id (the Message SID), code, error, retry (whether
 *               sending the same message again cannot deliver it twice),
 *               uncertain (Twilio may have taken it although no success came
 *               back)
 */
function pg_sms_twilio_send_parse($status, $body, $error = '', $never_sent = false)
{
    $answer = pg_sms_twilio_answer($status, $body, $error);

    if ($answer['error'] !== '') {
        $uncertain = false;

        if ((int) $status === 0) {
            $retry = (bool) $never_sent;
            $uncertain = !$retry;
        } elseif ($answer['code'] !== '') {
            // A coded refusal, or turned away for now; nothing taken.
            $retry = pg_sms_twilio_code_retries($answer['code']);
        } else {
            // Not Twilio's JSON: only a 429 or a 503 says the request was
            // turned away before it was taken. Any other 4xx took nothing
            // either; a success status or a server error may come after it
            // took the message.
            $retry = in_array((int) $status, array(429, 503), true);
            $uncertain = !$retry && (((int) $status < 400) || ((int) $status >= 500));
        }

        return array('ok' => false, 'id' => '', 'code' => $answer['code'], 'error' => $answer['error'], 'retry' => $retry, 'uncertain' => $uncertain);
    }

    $data = $answer['data'];
    $id = (isset($data['sid']) && is_scalar($data['sid'])) ? trim((string) $data['sid']) : '';

    // A success without a Message SID names nothing to ask about. Not
    // retried: it may have been taken.
    if ($id === '') {
        return array(
            'ok'        => false,
            'id'        => '',
            'code'      => '',
            'error'     => lang('Twilio accepted the request but returned no message SID.'),
            'retry'     => false,
            'uncertain' => true,
        );
    }

    // Taken and failed at once: a refusal that sent nothing.
    $state = (isset($data['status']) && is_scalar($data['status'])) ? (string) $data['status'] : '';

    if (in_array($state, array('failed', 'undelivered'), true)) {
        $code = (isset($data['error_code']) && is_scalar($data['error_code'])) ? trim((string) $data['error_code']) : '';
        $message = (isset($data['error_message']) && is_scalar($data['error_message'])) ? (string) $data['error_message'] : '';

        return array('ok' => false, 'id' => '', 'code' => $code, 'error' => pg_sms_twilio_error_text($code, $message), 'retry' => false, 'uncertain' => false);
    }

    return array('ok' => true, 'id' => $id, 'code' => '', 'error' => '', 'retry' => false, 'uncertain' => false);
}

/**
 * Reads the list of the account's phone numbers that can send SMS
 * (incoming_phone_numbers[].phone_number; a number whose sms capability is
 * false is left out).
 *
 * @param int    $status
 * @param string $body
 * @param string $error transport error
 * @return array ok, senders (E.164 numbers), code, error, refused (Twilio
 *               answered and refused the account; false when it could not be
 *               asked: no answer, an unreadable one, a limit)
 */
function pg_sms_twilio_senders_parse($status, $body, $error = '')
{
    $answer = pg_sms_twilio_answer($status, $body, $error);

    if (($answer['error'] === '') && !(isset($answer['data']['incoming_phone_numbers']) && is_array($answer['data']['incoming_phone_numbers']))) {
        $answer['error'] = lang(array('string' => 'Twilio sent an answer that could not be read (HTTP {var:1}).', 'vars' => array((int) $status)));
    }

    if ($answer['error'] !== '') {
        return array(
            'ok'      => false,
            'senders' => array(),
            'code'    => $answer['code'],
            'error'   => $answer['error'],
            'refused' => ($answer['code'] !== '') && !pg_sms_twilio_code_retries($answer['code']),
        );
    }

    $senders = array();

    foreach ($answer['data']['incoming_phone_numbers'] as $number) {
        if (!is_array($number) || !isset($number['phone_number']) || !is_scalar($number['phone_number'])) {
            continue;
        }

        if (isset($number['capabilities']['sms']) && ($number['capabilities']['sms'] === false)) {
            continue;
        }

        $senders[] = trim((string) $number['phone_number']);
    }

    return array('ok' => true, 'senders' => array_values(array_unique(array_filter($senders, 'strlen'))), 'code' => '', 'error' => '', 'refused' => false);
}

/**
 * The account's numbers, and the Messaging Service SID when the sender is
 * one: the services are not listed with the numbers, and a wrong one is
 * refused by the first message.
 *
 * @param array $app   pg_sms_app()
 * @param array $check pg_sms_twilio_senders_parse()
 * @return array the check, with the service among the senders
 */
function pg_sms_twilio_senders_with_service($app, $check)
{
    if ($check['ok'] && (pg_sms_twilio_sender_kind($app['sender']) === 'service')) {
        $check['senders'][] = (string) $app['sender'];
    }

    return $check;
}

/**
 * Sends one message.
 *
 * @param array  $app        pg_sms_app()
 * @param string $number     E.164 digits (pg_sms_number())
 * @param string $text
 * @param bool   $commercial unused: Twilio has no commercial flag
 * @return array ok, id, code, error, retry, uncertain
 */
function pg_sms_twilio_send($app, $number, $text, $commercial)
{
    $twilio_number = pg_sms_twilio_number($number);

    if ($twilio_number === '') {
        return pg_sms_failure(lang('Twilio could not read the recipient number.'));
    }

    $response = pg_sms_http('POST', pg_sms_twilio_send_request($app, $twilio_number, $text));

    return pg_sms_twilio_send_parse($response['status'], $response['body'], $response['error'], pg_sms_http_never_sent(
        $response['errno'],
        isset($response['connect']) ? $response['connect'] : 0,
        isset($response['request_size']) ? $response['request_size'] : -1));
}

/**
 * The phone numbers of the account, and its Messaging Service when the
 * sender is one.
 *
 * @param array $app pg_sms_app()
 * @return array ok, senders, code, error, refused
 */
function pg_sms_twilio_senders($app)
{
    $response = pg_sms_http('GET', pg_sms_twilio_senders_request($app));

    return pg_sms_twilio_senders_with_service($app, pg_sms_twilio_senders_parse($response['status'], $response['body'], $response['error']));
}
