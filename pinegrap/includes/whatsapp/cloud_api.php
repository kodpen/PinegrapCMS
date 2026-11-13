<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp Cloud API driver (Meta's Graph API): checks an account and
 * sends one template message.
 *
 * Every call is made with the site's own system user token as a Bearer token,
 * with appsecret_proof (HMAC-SHA256 of the token keyed with the app secret)
 * beside it. The account check is three calls that cost nothing:
 *
 *   GET /debug_token?input_token=<token>   with the app token
 *                                          "<app id>|<app secret>": the pair
 *                                          is right, the token is valid, is
 *                                          the app's own, never expires and
 *                                          holds the WhatsApp permissions
 *   GET /<phone number id>                 the sender number, its verified
 *                                          name and quality rating
 *   GET /<account id>/phone_numbers        the number is in that account
 *
 * A message is POST /<phone number id>/messages with a JSON body. A 200 with
 * a message id (wamid) means Meta accepted it, not that it was delivered:
 * delivery is reported to the webhook. An answer carrying Meta's error object
 * took nothing, so only its code says whether trying later may succeed; no
 * answer once the request was written, a 5xx without Meta's error object or a
 * 200 without a message id may come after Meta took the message, and a
 * message sent twice cannot be taken back, so those are reported uncertain.
 *
 * Built from Meta's documentation, read on 2026-11-12 (the facts are in
 * docs/memory/platform/whatsapp-cloud-api-facts.md and -webhooks.md):
 * https://developers.facebook.com/documentation/business-messaging/whatsapp/messages/send-messages
 * https://developers.facebook.com/documentation/business-messaging/whatsapp/access-tokens
 * https://developers.facebook.com/documentation/business-messaging/whatsapp/support/error-codes
 * https://developers.facebook.com/docs/graph-api/reference/debug_token/
 * https://developers.facebook.com/docs/graph-api/guides/secure-requests
 *
 * Request builders and answer readers are plain functions of their arguments,
 * so the tests check them without an account; the two functions at the end
 * make the calls through pg_whatsapp_http() (includes/fn/whatsapp.php).
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
 * The Graph API version every call names: the newest one Graph's changelog
 * lists, so it is served for two years after the next one comes out.
 *
 * @return string
 */
function pg_whatsapp_cloud_version()
{
    return 'v26.0';
}

/**
 * A Graph API address.
 *
 * @param string $path  without the version, e.g. '123/messages'
 * @param array  $query name => value
 * @return string
 */
function pg_whatsapp_cloud_url($path, $query = array())
{
    $url = 'https://graph.facebook.com/' . pg_whatsapp_cloud_version() . '/' . ltrim((string) $path, '/');

    return $query ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
}

/**
 * The proof that the caller holds the app secret, sent beside the token.
 *
 * @param string $token
 * @param string $secret the app secret
 * @return string
 */
function pg_whatsapp_cloud_proof($token, $secret)
{
    return hash_hmac('sha256', (string) $token, (string) $secret);
}

/**
 * The headers of a call made with a token.
 *
 * @param string $token
 * @param bool   $json  the call has a JSON body
 * @return array
 */
function pg_whatsapp_cloud_headers($token, $json = false)
{
    $headers = array(
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    );

    if ($json) {
        $headers[] = 'Content-Type: application/json';
    }

    return $headers;
}

/**
 * A GET with the account's token.
 *
 * @param array  $app   pg_whatsapp_app() shape
 * @param string $path
 * @param array  $query
 * @return array url, headers
 */
function pg_whatsapp_cloud_get_request($app, $path, $query = array())
{
    $query['appsecret_proof'] = pg_whatsapp_cloud_proof($app['token'], $app['app_secret']);

    return array(
        'url'     => pg_whatsapp_cloud_url($path, $query),
        'headers' => pg_whatsapp_cloud_headers($app['token']),
    );
}

/**
 * The token's own check, asked with the app token, which proves the app id
 * and secret belong together.
 *
 * @param array $app
 * @return array url, headers
 */
function pg_whatsapp_cloud_debug_request($app)
{
    return array(
        'url'     => pg_whatsapp_cloud_url('debug_token', array('input_token' => $app['token'])),
        'headers' => pg_whatsapp_cloud_headers($app['app_id'] . '|' . $app['app_secret']),
    );
}

/**
 * The sender number.
 *
 * @param array $app
 * @return array url, headers
 */
function pg_whatsapp_cloud_phone_request($app)
{
    return pg_whatsapp_cloud_get_request($app, rawurlencode($app['phone_number_id']), array('fields' => 'display_phone_number,verified_name,quality_rating'));
}

/**
 * The numbers of the WhatsApp Business account.
 *
 * @param array $app
 * @return array url, headers
 */
function pg_whatsapp_cloud_numbers_request($app)
{
    return pg_whatsapp_cloud_get_request($app, rawurlencode($app['account_id']) . '/phone_numbers', array('fields' => 'id', 'limit' => '100'));
}

/**
 * A template message to one person.
 *
 * @param array  $app
 * @param string $number     E.164 digits without the plus
 * @param string $name       the template's name
 * @param string $language   its language code (en_US, tr)
 * @param array  $components the filled components, as Meta reads them
 * @return array url, headers, body
 */
function pg_whatsapp_cloud_template_request($app, $number, $name, $language, $components = array())
{
    $template = array(
        'name'     => (string) $name,
        'language' => array('code' => (string) $language),
    );

    if ($components) {
        $template['components'] = array_values($components);
    }

    // The plus is sent: without it Meta puts the business number's own
    // country code in front of the number.
    $body = array(
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => '+' . $number,
        'type'              => 'template',
        'template'          => $template,
    );

    return array(
        'url'     => pg_whatsapp_cloud_url(rawurlencode($app['phone_number_id']) . '/messages', array('appsecret_proof' => pg_whatsapp_cloud_proof($app['token'], $app['app_secret']))),
        'headers' => pg_whatsapp_cloud_headers($app['token'], true),
        'body'    => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * Meta's error codes that say the same request may succeed later: a
 * temporary fault, a rate or throughput limit, maintenance.
 *
 * @param string $code
 * @return bool
 */
function pg_whatsapp_cloud_code_retries($code)
{
    return in_array((string) $code, array('2', '4', '80007', '130429', '131000', '131016', '131056', '131057'), true);
}

/**
 * The sentence an operator reads for one of Meta's error codes; Meta's own
 * text is kept beside it.
 *
 * @param string $code
 * @param string $message Meta's message
 * @return string
 */
function pg_whatsapp_cloud_error_text($code, $message = '')
{
    $code = (string) $code;
    $message = trim((string) $message);

    $known = array(
        '190'    => lang('Meta did not accept the access token: it is wrong, expired or was revoked.'),
        '10'     => lang('The access token lacks a permission this needs.'),
        '200'    => lang('The access token\'s system user has no access to this WhatsApp Business account or number.'),
        '131026' => lang('The number has no WhatsApp account, or cannot receive this message.'),
        '131047' => lang('Outside the 24 hours after the person\'s last message, only an approved template can be sent.'),
        '131050' => lang('The person stopped marketing messages from this business on WhatsApp.'),
        '131042' => lang('The WhatsApp Business account has a payment problem. Check its payment method in Meta Business Suite.'),
        '131045' => lang('The sender number is not registered with the Cloud API.'),
        '132001' => lang('The template is not in this WhatsApp Business account in that language, or is not approved.'),
        '130429' => lang('Meta\'s sending rate for this number was reached. Try again shortly.'),
    );

    if (isset($known[$code])) {
        $text = $known[$code];
    } elseif (($code !== '') && (strncmp($code, '2', 1) === 0) && (strlen($code) === 3)) {
        $text = $known['200'];
    } else {
        $text = lang(array('string' => 'Meta refused the request (code {var:1}).', 'vars' => array(h($code))));
    }

    return ($message !== '') ? $text . ' ' . lang(array('string' => 'Meta says: {var:1}', 'vars' => array(h($message)))) : $text;
}

/**
 * Reads one Graph API answer.
 *
 * @param int    $status the HTTP status, 0 when nothing came back
 * @param string $body
 * @param string $error  the transport's error text
 * @return array data (the decoded answer), code (Meta's error code, '' for
 *               none), meta (whether the answer carried Meta's error object
 *               or data), error (a sentence for the operator, '' on success)
 */
function pg_whatsapp_cloud_answer($status, $body, $error = '')
{
    $status = (int) $status;

    if ($status === 0) {
        return array(
            'data'  => array(),
            'code'  => '',
            'meta'  => false,
            'error' => lang(array('string' => 'Meta could not be reached: {var:1}', 'vars' => array(h((string) $error)))),
        );
    }

    // Ids are long numbers: read as text, never as floats.
    $data = json_decode((string) $body, true, 512, JSON_BIGINT_AS_STRING);
    $data = is_array($data) ? $data : array();

    if (isset($data['error']) && is_array($data['error'])) {
        $code = (isset($data['error']['code']) && is_scalar($data['error']['code'])) ? trim((string) $data['error']['code']) : '';
        $message = (isset($data['error']['message']) && is_scalar($data['error']['message'])) ? (string) $data['error']['message'] : '';

        if (isset($data['error']['error_data']['details']) && is_scalar($data['error']['error_data']['details'])) {
            $message = trim($message . ' ' . (string) $data['error']['error_data']['details']);
        }

        return array('data' => $data, 'code' => $code, 'meta' => true, 'error' => pg_whatsapp_cloud_error_text($code, $message));
    }

    if (($status >= 200) && ($status < 300) && $data) {
        return array('data' => $data, 'code' => '', 'meta' => true, 'error' => '');
    }

    return array(
        'data'  => $data,
        'code'  => '',
        'meta'  => false,
        'error' => lang(array('string' => 'Meta sent an answer that could not be read (HTTP {var:1}).', 'vars' => array($status))),
    );
}

/**
 * Reads the token's own check.
 *
 * @param array $answer pg_whatsapp_cloud_answer() of the debug_token call
 * @param array $app
 * @param int   $now
 * @return array ok, refused (Meta answered and something is wrong), field
 *               ('app_secret' or 'token': the one to mark), error
 */
function pg_whatsapp_cloud_debug_parse($answer, $app, $now)
{
    if ($answer['error'] !== '') {
        if (!$answer['meta']) {
            return array('ok' => false, 'refused' => false, 'field' => 'app_secret', 'error' => $answer['error']);
        }

        // Meta's refusal of the app token: the app id and secret do not
        // belong together. Its code (190) is the one an access token gets,
        // so the token's own sentence would point at the wrong field.
        $message = (isset($answer['data']['error']['message']) && is_scalar($answer['data']['error']['message']))
            ? trim((string) $answer['data']['error']['message']) : '';
        $text = lang('Meta did not accept this App ID and App secret.');

        return array(
            'ok'      => false,
            'refused' => true,
            'field'   => 'app_secret',
            'error'   => ($message !== '') ? $text . ' ' . lang(array('string' => 'Meta says: {var:1}', 'vars' => array(h($message)))) : $text,
        );
    }

    $data = (isset($answer['data']['data']) && is_array($answer['data']['data'])) ? $answer['data']['data'] : array();
    $refuse = function ($sentence) {
        return array('ok' => false, 'refused' => true, 'field' => 'token', 'error' => $sentence);
    };

    if (empty($data['is_valid'])) {
        $message = (isset($data['error']['message']) && is_scalar($data['error']['message'])) ? (string) $data['error']['message'] : '';

        return $refuse(pg_whatsapp_cloud_error_text('190', $message));
    }

    if (!isset($data['app_id']) || ((string) $data['app_id'] !== (string) $app['app_id'])) {
        return $refuse(lang('This access token belongs to another Meta app. Generate it for the app whose App ID is entered above.'));
    }

    // 0 is "never"; a token made in the app's API Setup lasts a day.
    $expires = isset($data['expires_at']) ? (int) $data['expires_at'] : 0;

    if ($expires > 0) {
        return $refuse(lang(array(
            'string' => 'This access token expires on {var:1}. Generate a system user token that never expires.',
            'vars'   => array(gmdate('Y-m-d H:i', $expires) . ' UTC'))));
    }

    $scopes = (isset($data['scopes']) && is_array($data['scopes'])) ? array_map('strval', $data['scopes']) : array();
    $missing = array_values(array_diff(array('whatsapp_business_messaging', 'whatsapp_business_management'), $scopes));

    if ($missing) {
        return $refuse(lang(array(
            'string' => 'The access token lacks the permission {var:1}. Generate it again with whatsapp_business_messaging and whatsapp_business_management.',
            'vars'   => array(h(implode(', ', $missing))))));
    }

    return array('ok' => true, 'refused' => false, 'field' => '', 'error' => '');
}

/**
 * The field a refused call of the account check marks.
 *
 * @param string $code  Meta's error code
 * @param string $field the field the call is about
 * @return string
 */
function pg_whatsapp_cloud_refused_field($code, $field)
{
    $code = (string) $code;

    // The token itself, or what it may reach.
    if (in_array($code, array('0', '3', '10', '190'), true) || ((strlen($code) === 3) && (strncmp($code, '2', 1) === 0))) {
        return 'token';
    }

    return $field;
}

/**
 * Reads the sender number's answer.
 *
 * @param array $answer pg_whatsapp_cloud_answer()
 * @return array ok, refused, field, error, info (display_phone_number,
 *               verified_name, quality_rating)
 */
function pg_whatsapp_cloud_phone_parse($answer)
{
    if ($answer['error'] !== '') {
        return array(
            'ok'      => false,
            'refused' => $answer['meta'],
            'field'   => pg_whatsapp_cloud_refused_field($answer['code'], 'phone_number_id'),
            'error'   => $answer['error'],
            'info'    => array(),
        );
    }

    $info = array();

    foreach (array('display_phone_number', 'verified_name', 'quality_rating') as $name) {
        $info[$name] = (isset($answer['data'][$name]) && is_scalar($answer['data'][$name])) ? trim((string) $answer['data'][$name]) : '';
    }

    return array('ok' => true, 'refused' => false, 'field' => '', 'error' => '', 'info' => $info);
}

/**
 * Reads the account's number list: the sender must be among them.
 *
 * @param array  $answer          pg_whatsapp_cloud_answer()
 * @param string $phone_number_id
 * @return array ok, refused, field, error
 */
function pg_whatsapp_cloud_numbers_parse($answer, $phone_number_id)
{
    if ($answer['error'] !== '') {
        return array(
            'ok'      => false,
            'refused' => $answer['meta'],
            'field'   => pg_whatsapp_cloud_refused_field($answer['code'], 'account_id'),
            'error'   => $answer['error'],
        );
    }

    $rows = (isset($answer['data']['data']) && is_array($answer['data']['data'])) ? $answer['data']['data'] : array();

    foreach ($rows as $row) {
        if (is_array($row) && isset($row['id']) && ((string) $row['id'] === (string) $phone_number_id)) {
            return array('ok' => true, 'refused' => false, 'field' => '', 'error' => '');
        }
    }

    return array(
        'ok'      => false,
        'refused' => true,
        'field'   => 'account_id',
        'error'   => lang('The phone number ID is not one of this WhatsApp Business account\'s numbers.'),
    );
}

/**
 * Reads the answer to a message.
 *
 * @param int    $status
 * @param string $body
 * @param string $error
 * @param bool   $never_sent the transport says the request reached nobody
 *                           (pg_sms_http_never_sent())
 * @return array ok, id (the wamid), code, error, retry (sending it again
 *               cannot deliver it twice and may succeed later), uncertain
 *               (Meta may have taken it)
 */
function pg_whatsapp_cloud_send_parse($status, $body, $error = '', $never_sent = false)
{
    $answer = pg_whatsapp_cloud_answer($status, $body, $error);

    if ($answer['error'] !== '') {
        if ((int) $status === 0) {
            $retry = (bool) $never_sent;

            return array('ok' => false, 'id' => '', 'code' => '', 'error' => $answer['error'], 'retry' => $retry, 'uncertain' => !$retry);
        }

        if ($answer['meta']) {
            // Meta's error object: the message was not taken.
            return array('ok' => false, 'id' => '', 'code' => $answer['code'], 'error' => $answer['error'], 'retry' => pg_whatsapp_cloud_code_retries($answer['code']), 'uncertain' => false);
        }

        // Not Meta's JSON: a 4xx took nothing; a 5xx may come after Meta
        // took the message.
        return array('ok' => false, 'id' => '', 'code' => '', 'error' => $answer['error'], 'retry' => false, 'uncertain' => ((int) $status >= 500) || ((int) $status < 400));
    }

    $id = (isset($answer['data']['messages'][0]['id']) && is_scalar($answer['data']['messages'][0]['id'])) ? trim((string) $answer['data']['messages'][0]['id']) : '';

    if ($id === '') {
        return array('ok' => false, 'id' => '', 'code' => '', 'error' => lang('Meta accepted the request but returned no message id.'), 'retry' => false, 'uncertain' => true);
    }

    return array('ok' => true, 'id' => $id, 'code' => '', 'error' => '', 'retry' => false, 'uncertain' => false);
}

/**
 * Checks an account with Meta: the three calls of the file's head, in turn.
 *
 * @param array $app pg_whatsapp_app() shape with every field filled
 * @return array ok, refused (Meta answered and refused: mark field), field
 *               (app_secret, token, phone_number_id or account_id), error,
 *               info (pg_whatsapp_cloud_phone_parse()); refused false with
 *               ok false: Meta could not be asked
 */
function pg_whatsapp_cloud_check($app)
{
    $response = pg_whatsapp_http('GET', pg_whatsapp_cloud_debug_request($app));
    $check = pg_whatsapp_cloud_debug_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']), $app, time());

    if (!$check['ok']) {
        return $check + array('info' => array());
    }

    $response = pg_whatsapp_http('GET', pg_whatsapp_cloud_phone_request($app));
    $phone = pg_whatsapp_cloud_phone_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']));

    if (!$phone['ok']) {
        return $phone;
    }

    $response = pg_whatsapp_http('GET', pg_whatsapp_cloud_numbers_request($app));
    $numbers = pg_whatsapp_cloud_numbers_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']), $app['phone_number_id']);

    return $numbers + array('info' => $phone['info']);
}

/**
 * Sends one template message.
 *
 * @param array  $app
 * @param string $number     E.164 digits
 * @param string $name
 * @param string $language
 * @param array  $components
 * @return array pg_whatsapp_cloud_send_parse()
 */
function pg_whatsapp_cloud_send($app, $number, $name, $language, $components = array())
{
    $response = pg_whatsapp_http('POST', pg_whatsapp_cloud_template_request($app, $number, $name, $language, $components));

    return pg_whatsapp_cloud_send_parse($response['status'], $response['body'], $response['error'], pg_sms_http_never_sent(
        $response['errno'],
        $response['connect'],
        $response['request_size']));
}
