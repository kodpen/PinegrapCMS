<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Mail transports - the contract every API transport driver keeps.
 *
 * By default a message leaves through the SMTP server of SMTP Settings (or
 * PHP mail() without one), and that stays the fallback. A transport is a
 * provider's HTTP API instead (Postmark, Mailgun, Brevo, Resend, Amazon
 * SES), a file under includes/mail/<key>.php listed in pg_mail_transports()
 * (includes/fn/mail_transport.php), with functions of one prefix,
 * pg_mail_<key>_, the way the SMS and payment layers work:
 *
 *   pg_mail_<key>_fields()          required: the settings the card asks for,
 *                                   name => array(label, type 'text', 'secret'
 *                                   or 'select', options for a select, help,
 *                                   required, default); exactly one 'secret'
 *   pg_mail_<key>_capabilities()    required: max_bytes (the largest request
 *                                   the API takes), webhook (whether the
 *                                   provider reports bounces to a webhook),
 *                                   raw (it sends the finished message whole,
 *                                   $message['mime']), webhook_signed (its
 *                                   reports carry a signature)
 *   pg_mail_<key>_request($message, $settings)
 *                                   required, pure: the HTTP request as
 *                                   array(url, headers, body, userpwd)
 *   pg_mail_<key>_answer($status, $body)
 *                                   required, pure: what the provider said, as
 *                                   array(ok, id, error)
 *   pg_mail_<key>_send($message, $settings)
 *                                   required: sends one message and answers
 *                                   pg_mail_transport_result()
 *   pg_mail_<key>_webhook($raw, $headers)
 *                                   optional, pure: the provider's bounce,
 *                                   complaint and delivery reports, and
 *   pg_mail_<key>_webhook_verify($raw, $headers, $signing, $now)
 *                                   for a signed one (includes/fn/mail_webhook.php)
 *
 * $message is the built message, pg_mail_transport_message(): from,
 * from_name, to, cc, bcc and reply_to as lists of array(address, name),
 * subject, text, html ('' when plain text only), headers (name => value,
 * threading and the site's own headers), attachments as array(name, type,
 * content, cid) with the bytes in content, and type ('system' or
 * 'campaign'), mime (the finished message, only for a driver that declares
 * raw, else ''). $settings is the account as the card stored it,
 * pg_mail_transport_settings(): the field values by name, the secret
 * opened.
 *
 * A driver never reads the database, the session or the request; it builds
 * and reads the provider's own shapes, so the tests check them without an
 * account. The provider signs the message itself (DKIM of the domain set up
 * with it); the site's own DKIM key is used only on the SMTP path.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
