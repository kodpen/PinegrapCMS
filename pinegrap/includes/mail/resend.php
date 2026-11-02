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
    return array('max_bytes' => 41943040, 'webhook' => true);
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
