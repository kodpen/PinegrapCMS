<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * SMS answers written in the Workspace: a text to a customer's number within
 * 7 days of their last SMS, as the transactional kind 'reply' (no marketing
 * footer, no consent asked). An answer only queues (sms_outbox) and the
 * general job sends it as the queue's other messages; how it went is written
 * back on the Workspace's item, as a WhatsApp answer's outcome is. Only a
 * provider the site can receive SMS through answers: a number that cannot
 * be written to is not answered from it either.
 *
 * The length counter (pg_sms_parts()) has a twin in assets/js/workspace_mail.js
 * (smsParts()), which counts in the answer drawer as the person writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_inbox.php');

// An answer goes only within this long after the number's last SMS.
define('PG_SMS_REPLY_WINDOW', 604800);

// The longest answer, in characters: the SMS layer's own limit
// (pg_sms_max_length()), so the drawer refuses what the queue would.
define('PG_SMS_ANSWER_MAX', 1000);

/**
 * The GSM 03.38 default alphabet: one place each in a GSM-7 message.
 *
 * @return string
 */
function pg_sms_gsm_chars()
{
    return "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        . '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
}

/**
 * The GSM 03.38 extension table: two places each (escape and the character).
 *
 * @return string
 */
function pg_sms_gsm_extension_chars()
{
    return "^{}\\[~]|€\f";
}

/**
 * The length of a text as the network counts it and the messages it makes:
 * GSM-7 while every character is in the default alphabet or its extension
 * (160 in one message, 153 a part when it is split), else UCS-2 counted in
 * UTF-16 units (70, then 67 a part). The text is counted as it is sent
 * (pg_sms_text()). A provider may count its own national letters
 * differently; this is the standard count. Pure.
 *
 * @param mixed $text
 * @return array encoding (gsm | ucs2), units, parts, per_part, left (places
 *               left in the last part)
 */
function pg_sms_parts($text)
{
    $text = pg_sms_text($text);
    $chars = ($text === '') ? array() : preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $gsm = pg_sms_gsm_chars();
    $extension = pg_sms_gsm_extension_chars();
    $encoding = 'gsm';
    $units = 0;
    $wide = 0;

    foreach ($chars as $char) {
        // Characters outside the basic plane take two UTF-16 units.
        $wide += (strlen($char) === 4) ? 2 : 1;

        if ($encoding !== 'gsm') {
            continue;
        }

        if (mb_strpos($gsm, $char, 0, 'UTF-8') !== false) {
            $units++;
        } elseif (mb_strpos($extension, $char, 0, 'UTF-8') !== false) {
            $units += 2;
        } else {
            $encoding = 'ucs2';
        }
    }

    if ($encoding === 'ucs2') {
        $units = $wide;
    }

    $single = ($encoding === 'gsm') ? 160 : 70;
    $per_part = ($units > $single) ? (($encoding === 'gsm') ? 153 : 67) : $single;
    $parts = ($units === 0) ? 0 : (int) ceil($units / $per_part);

    return array(
        'encoding' => $encoding,
        'units'    => $units,
        'parts'    => $parts,
        'per_part' => $per_part,
        'left'     => max(1, $parts) * $per_part - $units,
    );
}

/**
 * Seconds left to answer a number by SMS: 7 days from its last SMS; 0 once
 * they passed or when it never wrote. Pure.
 *
 * @param int $last_in_at the time of the customer's last SMS
 * @param int $now
 * @return int
 */
function pg_sms_reply_window_left($last_in_at, $now)
{
    if ((int) $last_in_at <= 0) {
        return 0;
    }

    return max(0, (int) $last_in_at + PG_SMS_REPLY_WINDOW - (int) $now);
}

/**
 * The time left to answer as the drawer says it. Pure.
 *
 * @param int $seconds pg_sms_reply_window_left()
 * @return string
 */
function pg_sms_reply_window_text($seconds)
{
    $seconds = max(0, (int) $seconds);

    if ($seconds <= 0) {
        return lang('The 7 days after the customer\'s last SMS have passed: no SMS answer can be sent now.');
    }

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = max(1, intdiv($seconds % 3600, 60));

    if ($days > 0) {
        return lang(array('string' => 'An SMS answer can be sent for {var:1} days {var:2} h more.', 'vars' => array($days, $hours)));
    }

    return ($hours > 0)
        ? lang(array('string' => 'An SMS answer can be sent for {var:1} h {var:2} min more.', 'vars' => array($hours, $minutes)))
        : lang(array('string' => 'An SMS answer can be sent for {var:1} min more.', 'vars' => array($minutes)));
}

/**
 * Whether this site answers SMS conversations: SMS set up with a provider
 * that receives.
 *
 * @return bool
 */
function pg_sms_reply_ready()
{
    return pg_sms_ready() && pg_sms_incoming_supported(pg_sms_provider());
}

/**
 * Queues an answer for the general job, as the kind 'reply', expiring with
 * the 7 days. pg_sms_enqueue() refuses a demo site, a site before the
 * upgrade and one without SMS; a provider that does not receive is refused
 * here.
 *
 * @param string $number  E.164 digits
 * @param string $text
 * @param array  $options reference (pg_whatsapp_reply_ref()), window_ends
 * @return array ok, row, error (a sentence)
 */
function pg_sms_enqueue_reply($number, $text, $options)
{
    if (!pg_sms_incoming_supported(pg_sms_provider())) {
        return array('ok' => false, 'row' => 0, 'error' => lang('The SMS provider set up does not receive customers\' messages, so it cannot answer them.'));
    }

    $ends = (int) ($options['window_ends'] ?? 0);

    if ($ends <= time()) {
        return array('ok' => false, 'row' => 0, 'error' => pg_sms_reply_window_text(0));
    }

    $queued = pg_sms_enqueue('+' . (string) $number, $text, 'reply', array(
        'reference'  => (string) ($options['reference'] ?? ''),
        'expires_at' => $ends,
    ));

    return array('ok' => (bool) $queued['ok'], 'row' => (int) $queued['row'], 'error' => (string) $queued['error']);
}

/**
 * The queue's outcome of an answer, written on its Workspace item by the same
 * rules as a WhatsApp answer's (the reference names the item, whatever the
 * transport).
 *
 * @param int    $outbox_id
 * @param string $ref
 * @param string $status sent | failed | uncertain
 * @param string $error
 */
function pg_sms_reply_outcome($outbox_id, $ref, $status, $error)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_reply.php');

    pg_whatsapp_reply_outcome($outbox_id, $ref, $status, $error);
}
