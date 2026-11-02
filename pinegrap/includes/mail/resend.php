<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Resend mail transport: one message is a JSON POST to /emails with the
 * API key as a Bearer token. Addresses are strings ("Name <address>"),
 * extra headers an object, attachments {filename, content, content_type}
 * with the bytes in base64 (an inline image carries content_id). Success is
 * 200 with the id; a refusal is a 4xx with {statusCode, name, message}.
 *
 * Built from Resend's public API documentation:
 * https://resend.com/docs/api-reference/emails/send-email
 * https://resend.com/docs/api-reference/errors
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_mail_resend_fields()
{
    return array(
        'key' => array('label' => lang('API key'), 'type' => 'secret'),
    );
}

function pg_mail_resend_capabilities()
{
    // 40 MB per message after base64.
    return array('max_bytes' => 41943040, 'webhook' => true, 'webhook_signed' => true);
}

/**
 * @param array $list lists of array(address, name)
 * @return array
 */
function pg_mail_resend_list($list)
{
    $out = array();

    foreach ($list as $entry) {
        $out[] = pg_mail_transport_address($entry[0], isset($entry[1]) ? $entry[1] : '');
    }

    return $out;
}

function pg_mail_resend_request($message, $settings)
{
    $body = array(
        'from'    => pg_mail_transport_address($message['from'], $message['from_name']),
        'to'      => pg_mail_resend_list($message['to']),
        'subject' => $message['subject'],
    );

    foreach (array('cc', 'bcc', 'reply_to') as $list) {
        if ($message[$list]) {
            $body[$list] = pg_mail_resend_list($message[$list]);
        }
    }

    if ($message['html'] !== '') {
        $body['html'] = $message['html'];
    }

    if ($message['text'] !== '') {
        $body['text'] = $message['text'];
    }

    if ($message['headers']) {
        $body['headers'] = $message['headers'];
    }

    foreach ($message['attachments'] as $attachment) {
        $entry = array('filename' => $attachment['name'], 'content' => base64_encode($attachment['content']));

        if ($attachment['type'] !== '') {
            $entry['content_type'] = $attachment['type'];
        }

        if ($attachment['cid'] !== '') {
            $entry['content_id'] = $attachment['cid'];
        }

        $body['attachments'][] = $entry;
    }

    return array(
        'url'     => 'https://api.resend.com/emails',
        'headers' => array('Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $settings['secret']),
        'body'    => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    );
}

function pg_mail_resend_answer($status, $body)
{
    $data = json_decode((string) $body, true);
    $data = is_array($data) ? $data : array();

    if (($status >= 200) && ($status < 300) && isset($data['id'])) {
        return array('ok' => true, 'id' => (string) $data['id'], 'error' => '');
    }

    return array('ok' => false, 'id' => '', 'error' => (isset($data['message']) && is_string($data['message'])) ? $data['message'] : '');
}

function pg_mail_resend_send($message, $settings)
{
    return pg_mail_transport_call('resend', pg_mail_resend_request($message, $settings));
}

/**
 * Whether a Resend webhook message was signed with the endpoint's signing
 * secret (Svix): HMAC-SHA256 of "id.timestamp.body" with the secret after
 * its whsec_ prefix, base64-decoded; svix-signature lists "v1,<base64>"
 * entries, one of which must match. Older than PG_MAIL_WEBHOOK_MAX_AGE is a
 * replay. Pure.
 *
 * https://resend.com/docs/dashboard/webhooks/verify-webhooks-requests
 *
 * @param string $raw
 * @param array  $headers lower-case name => value
 * @param string $signing the signing secret, whsec_...
 * @param int    $now
 * @return string ok, stale or bad
 */
function pg_mail_resend_webhook_verify($raw, $headers, $signing, $now)
{
    foreach (array('svix-id', 'svix-timestamp', 'svix-signature') as $name) {
        if (!isset($headers[$name]) || ((string) $headers[$name] === '')) {
            return 'bad';
        }
    }

    $secret = base64_decode(preg_replace('/^whsec_/', '', (string) $signing), true);

    if (($secret === false) || ($secret === '')) {
        return 'bad';
    }

    $expected = base64_encode(hash_hmac('sha256', $headers['svix-id'] . '.' . $headers['svix-timestamp'] . '.' . $raw, $secret, true));
    $matched = false;

    foreach (preg_split('/\s+/', trim((string) $headers['svix-signature'])) as $entry) {
        $parts = explode(',', $entry, 2);
        $matched = $matched || ((count($parts) === 2) && ($parts[0] === 'v1') && hash_equals($expected, $parts[1]));
    }

    if (!$matched) {
        return 'bad';
    }

    return (abs((int) $now - (int) $headers['svix-timestamp']) > PG_MAIL_WEBHOOK_MAX_AGE) ? 'stale' : 'ok';
}

/**
 * A Resend webhook message: email.bounced (Permanent is hard, the rest
 * soft), email.complained, email.delivered; one event for each address in
 * "to". The Svix message id names the event. Pure.
 *
 * https://resend.com/docs/dashboard/webhooks/event-types
 *
 * @param string $raw
 * @param array  $headers lower-case name => value
 * @return array|false list of id, kind, address, message_id
 */
function pg_mail_resend_webhook($raw, $headers)
{
    $data = json_decode((string) $raw, true);

    if (!is_array($data) || !isset($data['type']) || !is_string($data['type']) || !isset($data['data']) || !is_array($data['data'])) {
        return false;
    }

    $kind = '';

    if ($data['type'] === 'email.bounced') {
        $kind = (isset($data['data']['bounce']['type']) && ($data['data']['bounce']['type'] === 'Permanent')) ? 'hard' : 'soft';
    } elseif ($data['type'] === 'email.complained') {
        $kind = 'complaint';
    } elseif ($data['type'] === 'email.delivered') {
        $kind = 'delivered';
    }

    $message_id = isset($data['data']['email_id']) ? (string) $data['data']['email_id'] : '';
    $id = isset($headers['svix-id']) ? (string) $headers['svix-id'] : $data['type'] . ':' . $message_id;
    $events = array();

    foreach ((isset($data['data']['to']) ? (array) $data['data']['to'] : array()) as $address) {
        if (is_string($address)) {
            $events[] = array('id' => 'resend:' . $id . ':' . $address, 'kind' => $kind, 'address' => $address, 'message_id' => $message_id);
        }
    }

    return $events;
}
