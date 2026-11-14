<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: Send later. A mail written on the New e-mail form or in the
 * reply drawer may be given a time, typed in the writer's own time zone
 * (user.timezone, else the site's). The mail then waits under "Not sent"
 * with "Send now" and "Cancel" until a job hands it to the mail queue when
 * its time comes; it never goes through mail_outbox.send_after, which the
 * queue keeps for its own retries.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The send state of a mail that waits for its time. */
define('WS_MAIL_LATER_STATE', 'later');

/** The earliest a later mail may be set, in seconds from now. */
define('WS_MAIL_LATER_MIN', 300);

/** The latest a later mail may be set, in days from now. */
define('WS_MAIL_LATER_MAX_DAYS', 60);

/**
 * The moment a typed "send at" time names, read in the writer's time zone.
 * The form's datetime-local value (2026-11-20T09:30) or the same with a
 * space; seconds are dropped. A time that does not exist in that zone (the
 * hour skipped when clocks go forward) is refused rather than moved. Pure.
 *
 * @param mixed  $value    what was typed
 * @param string $timezone an IDENTIFIER (Europe/Istanbul); '' or an unknown
 *                         one reads as UTC
 * @param int    $now
 * @return array ok, error (format | missing | past | too_far), at (Unix time)
 */
function ws_mail_later_parse($value, $timezone, $now)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'at' => 0);
    };

    $value = is_scalar($value) ? trim((string) $value) : '';

    if ($value === '') {
        return $refuse('missing');
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::\d{2})?$/', $value, $part)) {
        return $refuse('format');
    }

    try {
        $zone = new DateTimeZone(($timezone !== '') ? $timezone : 'UTC');
    } catch (Exception $e) {
        $zone = new DateTimeZone('UTC');
    }

    $wanted = sprintf('%04d-%02d-%02d %02d:%02d', $part[1], $part[2], $part[3], $part[4], $part[5]);
    $time = DateTime::createFromFormat('!Y-m-d H:i', $wanted, $zone);

    // A date such as 2026-02-30 or an hour the zone skips comes back as
    // another moment; it is refused, never moved.
    if (!$time || ($time->format('Y-m-d H:i') !== $wanted)) {
        return $refuse('format');
    }

    $at = $time->getTimestamp();

    if ($at < ((int) $now + WS_MAIL_LATER_MIN)) {
        return $refuse('past');
    }

    if ($at > ((int) $now + (WS_MAIL_LATER_MAX_DAYS * 86400))) {
        return $refuse('too_far');
    }

    return array('ok' => true, 'error' => '', 'at' => $at);
}

/**
 * What a refused time is told.
 *
 * @param string $error ws_mail_later_parse() error
 * @return string
 */
function ws_mail_later_error_text($error)
{
    $texts = array(
        'missing' => lang('Choose when the e-mail is sent.'),
        'format'  => lang('That time does not exist; choose another.'),
        'past'    => lang(array('string' => 'Choose a time at least {var:1} minutes from now.', 'vars' => (int) (WS_MAIL_LATER_MIN / 60))),
        'too_far' => lang(array('string' => 'An e-mail can wait at most {var:1} days.', 'vars' => WS_MAIL_LATER_MAX_DAYS)),
    );

    return $texts[$error] ?? $texts['format'];
}
