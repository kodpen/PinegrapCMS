<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Brevo mail transport (transactional e-mail API v3): one message is a
 * JSON POST to /v3/smtp/email with the API key in the api-key header.
 * Addresses are objects {email, name}, Reply-To takes one address, extra
 * headers are an object. Success is 201 with the messageId; a refusal is a
 * 4xx with {code, message}.
 *
 * Built from Brevo's public API documentation:
 * https://developers.brevo.com/reference/sendtransacemail
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_mail_brevo_fields()
{
    return array(
        'key' => array('label' => lang('API key'), 'type' => 'secret'),
    );
}

function pg_mail_brevo_capabilities()
{
    return array('max_bytes' => 20971520, 'webhook' => true);
}

/**
 * @param array $entry array(address, name)
 * @return array
 */
function pg_mail_brevo_address($entry)
{
    $address = array('email' => (string) $entry[0]);

    if (isset($entry[1]) && (trim((string) $entry[1]) !== '')) {
        $address['name'] = (string) $entry[1];
    }

    return $address;
}

function pg_mail_brevo_request($message, $settings)
{
    $body = array(
        'sender'  => pg_mail_brevo_address(array($message['from'], $message['from_name'])),
        'to'      => array_map('pg_mail_brevo_address', $message['to']),
        'subject' => $message['subject'],
    );

    foreach (array('cc', 'bcc') as $list) {
        if ($message[$list]) {
            $body[$list] = array_map('pg_mail_brevo_address', $message[$list]);
        }
    }

    // Brevo takes one Reply-To address.
    if ($message['reply_to']) {
        $body['replyTo'] = pg_mail_brevo_address($message['reply_to'][0]);
    }

    if ($message['html'] !== '') {
        $body['htmlContent'] = $message['html'];
    }

    if ($message['text'] !== '') {
        $body['textContent'] = $message['text'];
    }

    if ($message['headers']) {
        $body['headers'] = $message['headers'];
    }

    foreach ($message['attachments'] as $attachment) {
        $body['attachment'][] = array('name' => $attachment['name'], 'content' => base64_encode($attachment['content']));
    }

    return array(
        'url'     => 'https://api.brevo.com/v3/smtp/email',
        'headers' => array('Accept: application/json', 'Content-Type: application/json', 'api-key: ' . $settings['secret']),
        'body'    => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    );
}

function pg_mail_brevo_answer($status, $body)
{
    $data = json_decode((string) $body, true);
    $data = is_array($data) ? $data : array();

    if (($status >= 200) && ($status < 300) && isset($data['messageId'])) {
        return array('ok' => true, 'id' => (string) $data['messageId'], 'error' => '');
    }

    return array('ok' => false, 'id' => '', 'error' => (isset($data['message']) && is_string($data['message'])) ? $data['message'] : '');
}

function pg_mail_brevo_send($message, $settings)
{
    return pg_mail_transport_call('brevo', pg_mail_brevo_request($message, $settings));
}

/**
 * A Brevo transactional webhook message: one event a message. "spam" is
 * the recipient's complaint; "blocked", "deferred" and the rest are read as
 * '' (Brevo's own block list, a delay, an open). Pure.
 *
 * https://developers.brevo.com/docs/transactional-webhooks
 *
 * @param string $raw
 * @param array  $headers lower-case name => value
 * @return array|false list of id, kind, address, message_id
 */
function pg_mail_brevo_webhook($raw, $headers)
{
    $data = json_decode((string) $raw, true);

    if (!is_array($data) || !isset($data['event']) || !is_string($data['event'])) {
        return false;
    }

    $kinds = array('hard_bounce' => 'hard', 'invalid_email' => 'hard', 'soft_bounce' => 'soft', 'spam' => 'complaint', 'complaint' => 'complaint', 'delivered' => 'delivered');
    $address = isset($data['email']) ? (string) $data['email'] : '';
    $message_id = isset($data['message-id']) ? (string) $data['message-id'] : '';
    $at = isset($data['ts_event']) ? (string) $data['ts_event'] : (isset($data['date']) ? (string) $data['date'] : '');

    return array(array(
        'id'         => 'brevo:' . $data['event'] . ':' . $message_id . ':' . $address . ':' . $at,
        'kind'       => isset($kinds[$data['event']]) ? $kinds[$data['event']] : '',
        'address'    => $address,
        'message_id' => $message_id,
    ));
}
