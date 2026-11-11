<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: writing a new e-mail. A mailbox's Signature (plain text kept in
 * its settings) put under the text, the mailboxes and channels the New
 * e-mail form offers, and the form's request turned into what
 * ws_mail_send() takes. Sending itself is mail_send.php's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most characters of a mailbox's Signature. */
define('WS_MAIL_SIGNATURE_MAX', 1000);

/** The most lines of a mailbox's Signature. */
define('WS_MAIL_SIGNATURE_LINES', 12);

/**
 * A Signature as typed, cleaned: line breaks kept, other control characters
 * dropped, spaces at line ends and empty lines at both ends removed. Pure.
 *
 * @param mixed $value
 * @return array ok, error, signature
 */
function ws_mail_signature_input($value)
{
    $text = is_scalar($value) ? str_replace(array("\r\n", "\r"), "\n", (string) $value) : '';
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', str_replace("\t", ' ', $text));
    $lines = array_map('rtrim', explode("\n", (string) $text));
    $text = trim(implode("\n", $lines), "\n");

    // A typed "-- " line is the separator the mail adds itself.
    $text = trim(preg_replace('/\A--\s*(\n|\z)/', '', $text), "\n");

    if (mb_strlen($text) > WS_MAIL_SIGNATURE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A signature is at most {var:1} characters.', 'vars' => WS_MAIL_SIGNATURE_MAX)), 'signature' => $text);
    }

    if (($text !== '') && (count(explode("\n", $text)) > WS_MAIL_SIGNATURE_LINES)) {
        return array('ok' => false, 'error' => lang(array('string' => 'A signature is at most {var:1} lines.', 'vars' => WS_MAIL_SIGNATURE_LINES)), 'signature' => $text);
    }

    return array('ok' => true, 'error' => '', 'signature' => $text);
}

/**
 * A mailbox's Signature, '' when it has none.
 *
 * @param array|null $box ws_mail_boxes row
 * @return string
 */
function ws_mail_box_signature($box)
{
    $signature = ws_mail_box_settings($box)['signature'] ?? '';

    return is_string($signature) ? $signature : '';
}

/**
 * The text of a new mail: what was written, then after the "-- " separator
 * the writer's name and the mailbox's Signature, or the mailbox's name when
 * it has none (the answer's signature, ws_mail_reply_body()). Pure.
 *
 * @param string $text
 * @param string $writer
 * @param string $box_name
 * @param string $signature
 * @return string
 */
function ws_mail_send_body($text, $writer, $box_name, $signature)
{
    $signature = trim((string) $signature, "\n ");

    return ws_mail_reply_body($text, $writer, ($signature !== '') ? $signature : $box_name, null);
}
