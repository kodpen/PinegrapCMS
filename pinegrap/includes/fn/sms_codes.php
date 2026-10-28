<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One-time codes sent by text message: the cash-on-delivery confirmation at
 * checkout and a member's sign-in code. A code is kept in the session as a
 * hash, bound to what it confirms (a purpose and a subject such as an order)
 * and to the number it was sent to, so a code texted to one number never
 * confirms another. It works for ten minutes, once, and the fifth wrong
 * guess ends it, so six digits cannot be walked through.
 *
 * The message goes through the SMS queue (pg_sms_enqueue()) with the code's
 * own end as its expires_at: a code the queue could not send in time is
 * dropped instead of arriving after it stopped working.
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
 * The purposes a code is sent for.
 *
 * @return array
 */
function pg_sms_code_purposes()
{
    return array('cod', 'signin');
}

/**
 * How long a texted code works, in seconds.
 *
 * @return int
 */
function pg_sms_code_lifetime()
{
    return 600;
}

/**
 * A new six-digit code.
 *
 * @return string
 */
function pg_sms_code_new()
{
    return sprintf('%06d', random_int(0, 999999));
}

/**
 * The hash a code is kept as.
 *
 * @param string $purpose
 * @param string $subject
 * @param string $number  E.164 digits
 * @param string $code
 * @return string
 */
function pg_sms_code_hash($purpose, $subject, $number, $code)
{
    return hash('sha256', 'sms-code:' . $purpose . ':' . $subject . ':' . $number . ':' . $code);
}

/**
 * Keeps a texted code: its hash, never the code, so a session store that is
 * read cannot hand it out. One code per purpose; a newer one replaces the
 * older.
 *
 * @param mixed  $store   the session's value for the purpose, by reference
 * @param string $purpose pg_sms_code_purposes()
 * @param string $subject what it confirms ('order:12', 'user:5')
 * @param string $number  E.164 digits it was sent to (pg_sms_number())
 * @param string $code
 * @param int    $now
 * @return bool false for an unknown purpose or an empty subject or number
 */
function pg_sms_code_keep(&$store, $purpose, $subject, $number, $code, $now)
{
    $purpose = (string) $purpose;
    $subject = (string) $subject;
    $number = (string) $number;

    if (!in_array($purpose, pg_sms_code_purposes(), true) || ($subject === '') || ($number === '')) {
        return false;
    }

    $store = array(
        'purpose' => $purpose,
        'subject' => $subject,
        'number'  => $number,
        'hash'    => pg_sms_code_hash($purpose, $subject, $number, (string) $code),
        'time'    => (int) $now,
        'misses'  => 0,
    );

    return true;
}

/**
 * Checks a typed code. A right one is used up; a wrong one counts, and the
 * fifth wrong guess ends the code. A code kept for another purpose, subject
 * or number is left as it is.
 *
 * @param mixed  $store   by reference
 * @param string $purpose
 * @param string $subject
 * @param string $number  E.164 digits the code must have gone to
 * @param string $code    white space is ignored
 * @param int    $now
 * @return string '' when accepted, 'code_wrong' when wrong, 'code_expired'
 *                when there is none to check (never sent, too old, used, too
 *                many wrong guesses, sent for something else or to another
 *                number)
 */
function pg_sms_code_check(&$store, $purpose, $subject, $number, $code, $now)
{
    if (!is_array($store) || !isset($store['purpose'], $store['subject'], $store['number'], $store['hash'], $store['time'], $store['misses'])
        || ((string) $store['purpose'] !== (string) $purpose)
        || ((string) $store['subject'] !== (string) $subject)
        || ((string) $store['number'] !== (string) $number)) {
        return 'code_expired';
    }

    if ((((int) $now - (int) $store['time']) > pg_sms_code_lifetime())
        || ((int) $store['time'] > (int) $now) || ((int) $store['misses'] >= 5)) {
        $store = null;

        return 'code_expired';
    }

    $code = (string) preg_replace('/\s+/', '', (string) $code);

    if (($code !== '') && hash_equals((string) $store['hash'], pg_sms_code_hash((string) $purpose, (string) $subject, (string) $number, $code))) {
        $store = null;

        return '';
    }

    $store['misses'] = (int) $store['misses'] + 1;

    if ($store['misses'] >= 5) {
        $store = null;

        return 'code_expired';
    }

    return 'code_wrong';
}

/**
 * The queue options of a code's message: a reference of its own per send
 * (the queue's reference is unique, and a code sent again is a new message)
 * and an expiry at the code's end.
 *
 * @param string $purpose
 * @param string $subject
 * @param int    $now
 * @param string $nonce   a few characters telling two sends in one second
 *                        apart
 * @return array reference, expires_at
 */
function pg_sms_code_queue_options($purpose, $subject, $now, $nonce)
{
    $reference = 'code:' . $purpose . ':' . $subject . ':' . (int) $now . ':' . $nonce;
    $reference = substr((string) preg_replace('/[^A-Za-z0-9:._\/-]/', '', $reference), 0, 64);

    return array(
        'reference'  => $reference,
        'expires_at' => (int) $now + pg_sms_code_lifetime(),
    );
}
