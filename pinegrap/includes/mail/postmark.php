<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Postmark mail transport (Email API): one message is a JSON POST to
 * /email with the server's API token in X-Postmark-Server-Token. Success is
 * 200 with ErrorCode 0 and the MessageID; a refusal is a 4xx with
 * {ErrorCode, Message}. Transactional mail goes to the server's message
 * stream (outbound by default), campaigns to the broadcast stream when one is
 * named: Postmark keeps the two apart for the sender's reputation.
 *
 * Built from Postmark's public API documentation:
 * https://postmarkapp.com/developer/api/email-api
 * https://postmarkapp.com/developer/api/overview#error-codes
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_mail_postmark_fields()
{
    return array(
        'token'           => array('label' => lang('Server API token'), 'type' => 'secret'),
        'stream'          => array('label' => lang('Message stream'), 'type' => 'text', 'required' => true, 'default' => 'outbound'),
        'campaign_stream' => array('label' => lang('Campaign message stream'), 'type' => 'text', 'help' => lang('A broadcast stream for e-mail campaigns; empty sends them through the stream above.')),
    );
}

function pg_mail_postmark_capabilities()
{
    // 10 MB per message, attachments counted in base64.
    return array('max_bytes' => 10485760, 'webhook' => true);
}

/**
 * @param array $list lists of array(address, name)
 * @return string comma-separated header form
 */
function pg_mail_postmark_list($list)
{
    $out = array();

    foreach ($list as $entry) {
        $out[] = pg_mail_transport_address($entry[0], isset($entry[1]) ? $entry[1] : '');
    }

    return implode(', ', $out);
}

function pg_mail_postmark_request($message, $settings)
{
    $stream = $settings['values']['stream'];

    if (($message['type'] === 'campaign') && ($settings['values']['campaign_stream'] !== '')) {
        $stream = $settings['values']['campaign_stream'];
    }

    $body = array(
        'From'          => pg_mail_transport_address($message['from'], $message['from_name']),
        'To'            => pg_mail_postmark_list($message['to']),
        'Subject'       => $message['subject'],
        'MessageStream' => $stream,
    );

    foreach (array('cc' => 'Cc', 'bcc' => 'Bcc', 'reply_to' => 'ReplyTo') as $key => $name) {
        if ($message[$key]) {
            $body[$name] = pg_mail_postmark_list($message[$key]);
        }
    }

    if ($message['html'] !== '') {
        $body['HtmlBody'] = $message['html'];
    }

    if ($message['text'] !== '') {
        $body['TextBody'] = $message['text'];
    }

    foreach ($message['headers'] as $name => $value) {
        $body['Headers'][] = array('Name' => $name, 'Value' => $value);
    }

    foreach ($message['attachments'] as $attachment) {
        $entry = array(
            'Name'        => $attachment['name'],
            'Content'     => base64_encode($attachment['content']),
            'ContentType' => ($attachment['type'] !== '') ? $attachment['type'] : 'application/octet-stream',
        );

        if ($attachment['cid'] !== '') {
            $entry['ContentID'] = 'cid:' . $attachment['cid'];
        }

        $body['Attachments'][] = $entry;
    }

    return array(
        'url'     => 'https://api.postmarkapp.com/email',
        'headers' => array('Accept: application/json', 'Content-Type: application/json', 'X-Postmark-Server-Token: ' . $settings['secret']),
        'body'    => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    );
}

function pg_mail_postmark_answer($status, $body)
{
    $data = json_decode((string) $body, true);
    $data = is_array($data) ? $data : array();

    if (($status === 200) && isset($data['ErrorCode']) && ((int) $data['ErrorCode'] === 0)) {
        return array('ok' => true, 'id' => isset($data['MessageID']) ? (string) $data['MessageID'] : '', 'error' => '');
    }

    return array('ok' => false, 'id' => '', 'error' => (isset($data['Message']) && is_string($data['Message'])) ? $data['Message'] : '');
}

function pg_mail_postmark_send($message, $settings)
{
    return pg_mail_transport_call('postmark', pg_mail_postmark_request($message, $settings));
}
