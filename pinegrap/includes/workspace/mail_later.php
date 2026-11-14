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

/**
 * Whether a later time can be kept (step 11.0.207: ws_mail_items.send_at).
 *
 * @return bool
 */
function ws_mail_later_ready()
{
    return function_exists('ws_mail_send_ready') && ws_mail_send_ready() && pg_schema_has('ws_mail_items', 'send_at');
}

/**
 * Send later as a form posted it: no time asked (at 0), or the time read in
 * the writer's own time zone, else the site's.
 *
 * @param array $request send_later (the form's datetime-local value)
 * @param int   $now
 * @return array ok, error (a sentence), at (0 for at once)
 */
function ws_mail_later_request($request, $now)
{
    $value = $request['send_later'] ?? '';

    if (!is_scalar($value) || (trim((string) $value) === '')) {
        return array('ok' => true, 'error' => '', 'at' => 0);
    }

    if (!ws_mail_later_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'), 'at' => 0);
    }

    $zone = (defined('USER_TIMEZONE') && (USER_TIMEZONE !== '')) ? USER_TIMEZONE : (string) date_default_timezone_get();
    $parsed = ws_mail_later_parse($value, $zone, $now);

    return $parsed['ok'] ? $parsed : array('ok' => false, 'error' => ws_mail_later_error_text($parsed['error']), 'at' => 0);
}

/**
 * Hands one waiting mail to the mail queue: it is claimed first (later to
 * queued, so a second runner or a second Send now leaves it alone), then
 * dispatched as a mail sent at once is (ws_mail_send_dispatch()).
 *
 * @param array $item ws_mail_items row
 * @return string queued | sent | failed, '' when another runner took it
 */
function ws_mail_later_release($item)
{
    db("UPDATE ws_mail_items SET send_state = 'queued', send_at = 0
        WHERE id = '" . (int) $item['id'] . "' AND send_state = '" . WS_MAIL_LATER_STATE . "'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return '';
    }

    $item['send_state'] = 'queued';
    $item['send_at'] = 0;

    return ws_mail_send_dispatch($item, 0);
}

/**
 * The general job's part: the mails whose time has come, oldest first, read
 * by idx_later (send_state, send_at), each handed to the queue.
 *
 * @param int $now
 * @param int $limit
 * @return int how many were handed on
 */
function ws_mail_later_run($now, $limit = 50)
{
    if (!ws_mail_later_ready()) {
        return 0;
    }

    $done = 0;

    foreach ((array) db_items("SELECT * FROM ws_mail_items WHERE send_state = '" . WS_MAIL_LATER_STATE . "' AND send_at <= '" . (int) $now . "'
        ORDER BY send_at, id LIMIT " . max(1, (int) $limit)) as $item) {
        if (ws_mail_later_release($item) !== '') {
            $done++;
        }
    }

    return $done;
}

/**
 * A waiting mail the person may send now or cancel: whoever may answer in
 * its conversation's channel. Null for any other row.
 *
 * @param array $viewer
 * @param int   $item_id
 * @return array|null ws_mail_items row
 */
function ws_mail_later_reached($viewer, $item_id)
{
    if (empty($viewer['member']) || !ws_mail_later_ready()) {
        return null;
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $item_id . "' AND direction = 'out' AND send_state = '" . WS_MAIL_LATER_STATE . "'");
    $conversation = $item ? db_item("SELECT channel_id FROM ws_mail_conversations WHERE id = '" . (int) $item['conversation_id'] . "'") : null;
    $channel = $conversation ? ws_channel((int) $conversation['channel_id']) : null;

    return ($channel && ws_can_read_channel($viewer, $channel) && ws_mail_can_answer($viewer, $channel)) ? $item : null;
}

/**
 * Send now, under a waiting mail.
 *
 * @param array $viewer
 * @param int   $item_id
 * @return array ok, error, state
 */
function ws_mail_later_send_now($viewer, $item_id)
{
    $item = ws_mail_later_reached($viewer, $item_id);

    if (!$item) {
        return array('ok' => false, 'error' => lang('This e-mail no longer waits to be sent.'), 'state' => '');
    }

    $state = ws_mail_later_release($item);

    return ($state === '')
        ? array('ok' => false, 'error' => lang('This e-mail no longer waits to be sent.'), 'state' => '')
        : array('ok' => true, 'error' => '', 'state' => $state);
}

/**
 * Cancel, under a waiting mail: it is not sent and stays under Not sent,
 * where Send again sends it as any mail that did not leave.
 *
 * @param array $viewer
 * @param int   $item_id
 * @return array ok, error
 */
function ws_mail_later_cancel($viewer, $item_id)
{
    $item = ws_mail_later_reached($viewer, $item_id);

    if ($item) {
        db("UPDATE ws_mail_items SET send_state = 'failed', send_at = 0, send_error = '" . e(lang('Cancelled before its time; not sent.')) . "'
            WHERE id = '" . (int) $item['id'] . "' AND send_state = '" . WS_MAIL_LATER_STATE . "'");

        if (mysqli_affected_rows(db::$con) === 1) {
            return array('ok' => true, 'error' => '');
        }
    }

    return array('ok' => false, 'error' => lang('This e-mail no longer waits to be sent.'));
}

/**
 * The badge of a waiting mail: when it leaves, in the reader's time. Pure
 * but for the words and the time label.
 *
 * @param array $item ws_mail_items row with send_at
 * @return string HTML
 */
function ws_mail_later_badge_html($item)
{
    return '<span class="badge theme-info"><i class="bi bi-clock me-1" aria-hidden="true"></i>'
        . h(lang(array('string' => 'Sends at {var:1}', 'vars' => date('d.m.Y H:i', (int) ($item['send_at'] ?? 0))))) . '</span>';
}

/**
 * Send now and Cancel under a waiting mail of the pane, for whoever may
 * answer there. Pure but for the words.
 *
 * @param array $item     ws_mail_items row
 * @param bool  $can_post the pane's can_post
 * @return string HTML, '' for any other mail
 */
function ws_mail_later_tools_html($item, $can_post)
{
    if (!$can_post || ((string) ($item['send_state'] ?? '') !== WS_MAIL_LATER_STATE)) {
        return '';
    }

    $id = (int) $item['id'];

    return '<div class="ws-mail-item-tools mt-2 d-flex flex-wrap gap-2">'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-later-send="' . $id . '"><i class="bi bi-send me-1" aria-hidden="true"></i>' . h(lang('Send now')) . '</button>'
        . '<button type="button" class="pg-button pg-button-ghost pg-button-sm" data-ws-mail-later-cancel="' . $id . '">' . h(lang('Cancel sending')) . '</button>'
        . '</div>';
}

/**
 * The words of Send later in the New e-mail form, the reply drawer and the
 * pane (workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_later_js_strings()
{
    return array(
        'mail_later'        => lang('Send later'),
        'mail_later_at'     => lang('Send at'),
        'mail_later_hint'   => lang('In your own time zone. The e-mail waits under Not sent until then.'),
        'mail_later_none'   => lang('Send at once'),
        'mail_later_set'    => lang('The e-mail will be sent at the chosen time.'),
        'mail_later_now'    => lang('The e-mail is on its way.'),
        'mail_later_cancel' => lang('The e-mail will not be sent. It stays under Not sent, where Send again sends it.'),
    );
}
