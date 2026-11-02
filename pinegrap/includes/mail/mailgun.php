<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Mailgun mail transport (Messages API v3): one message is a
 * multipart/form-data POST to /v3/<domain>/messages, signed in with HTTP
 * Basic auth ("api" and the API key), on the US or the EU host the domain
 * was created in. Extra headers travel as h:<Name> fields, attachments as
 * file parts (an inline image named by its Content-ID). Success is 200 with
 * {id, message}; a refusal is a 4xx with {message}.
 *
 * The form body is built here, not by cURL: a file part from bytes in memory
 * needs CURLStringFile (PHP 8.1), and the tests compare the body itself.
 *
 * Built from Mailgun's public API documentation:
 * https://documentation.mailgun.com/docs/mailgun/api-reference/openapi-final/tag/Messages/
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_mail_mailgun_fields()
{
    return array(
        'key'    => array('label' => lang('API key'), 'type' => 'secret'),
        'domain' => array('label' => lang('Sending domain'), 'type' => 'text', 'required' => true, 'help' => lang('The domain as Mailgun lists it, for example mg.example.com.')),
        'region' => array('label' => lang('Region'), 'type' => 'select', 'options' => array('us' => lang('United States'), 'eu' => lang('Europe')), 'default' => 'us'),
    );
}

function pg_mail_mailgun_capabilities()
{
    return array('max_bytes' => 26214400, 'webhook' => true);
}

/**
 * One part of a multipart/form-data body.
 *
 * @param string      $boundary
 * @param string      $name
 * @param string      $value
 * @param string|null $filename a file part when given
 * @param string      $type     the file's media type
 * @return string
 */
function pg_mail_mailgun_part($boundary, $name, $value, $filename = null, $type = '')
{
    $quote = function ($text) {
        return str_replace(array('"', "\r", "\n"), array('%22', '', ''), (string) $text);
    };

    $part = '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="' . $quote($name) . '"';

    if ($filename !== null) {
        $part .= '; filename="' . $quote($filename) . '"' . "\r\n" . 'Content-Type: ' . (($type !== '') ? $type : 'application/octet-stream');
    }

    return $part . "\r\n\r\n" . $value . "\r\n";
}

function pg_mail_mailgun_request($message, $settings, $boundary = null)
{
    $boundary = ($boundary !== null) ? (string) $boundary : 'pg' . bin2hex(random_bytes(12));
    $body = pg_mail_mailgun_part($boundary, 'from', pg_mail_transport_address($message['from'], $message['from_name']));

    foreach (array('to', 'cc', 'bcc') as $list) {
        foreach ($message[$list] as $entry) {
            $body .= pg_mail_mailgun_part($boundary, $list, pg_mail_transport_address($entry[0], isset($entry[1]) ? $entry[1] : ''));
        }
    }

    $body .= pg_mail_mailgun_part($boundary, 'subject', $message['subject']);

    if ($message['text'] !== '') {
        $body .= pg_mail_mailgun_part($boundary, 'text', $message['text']);
    }

    if ($message['html'] !== '') {
        $body .= pg_mail_mailgun_part($boundary, 'html', $message['html']);
    }

    $headers = $message['headers'];

    if ($message['reply_to']) {
        $reply_to = array();

        foreach ($message['reply_to'] as $entry) {
            $reply_to[] = pg_mail_transport_address($entry[0], isset($entry[1]) ? $entry[1] : '');
        }

        $headers = array('Reply-To' => implode(', ', $reply_to)) + $headers;
    }

    foreach ($headers as $name => $value) {
        $body .= pg_mail_mailgun_part($boundary, 'h:' . $name, $value);
    }

    foreach ($message['attachments'] as $attachment) {
        $inline = ($attachment['cid'] !== '');
        $body .= pg_mail_mailgun_part($boundary, $inline ? 'inline' : 'attachment', $attachment['content'], $inline ? $attachment['cid'] : $attachment['name'], $attachment['type']);
    }

    $host = ($settings['values']['region'] === 'eu') ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';

    return array(
        'url'     => $host . '/v3/' . rawurlencode(strtolower($settings['values']['domain'])) . '/messages',
        'headers' => array('Accept: application/json', 'Content-Type: multipart/form-data; boundary=' . $boundary),
        'body'    => $body . '--' . $boundary . "--\r\n",
        'userpwd' => 'api:' . $settings['secret'],
    );
}

function pg_mail_mailgun_answer($status, $body)
{
    $data = json_decode((string) $body, true);
    $data = is_array($data) ? $data : array();

    if (($status === 200) && isset($data['id'])) {
        return array('ok' => true, 'id' => (string) $data['id'], 'error' => '');
    }

    return array('ok' => false, 'id' => '', 'error' => (isset($data['message']) && is_string($data['message'])) ? $data['message'] : trim(strip_tags(substr((string) $body, 0, 200))));
}

function pg_mail_mailgun_send($message, $settings)
{
    return pg_mail_transport_call('mailgun', pg_mail_mailgun_request($message, $settings));
}
