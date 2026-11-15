<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: Remind me later, and Remind me if no answer comes, on an e-mail
 * conversation. Whoever reads a
 * conversation on the E-mails screen may set it aside until a time (in an
 * hour, tomorrow morning, next week or a chosen time, in their own time
 * zone). Until then it leaves that person's list and unread count; nobody
 * else's changes. At the time, or at once when a new mail arrives in it,
 * it comes back unread with an inbox line (mail_remind) until the person
 * reads it. One row per person and conversation in ws_mail_reminders,
 * kind 'later'; the general job's reminder run wakes it.
 *
 * Remind me if no answer comes, on Reply by e-mail and New e-mail: in 1, 3
 * or 7 days from the mail's sending, kind 'no_answer'. With no incoming mail
 * by then the writer gets an inbox line (mail_no_answer) and their row and
 * pane say "No answer yet" until an answer comes; an incoming mail in the
 * conversation removes the reminder, waiting or not.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The kind of a reminder set with Remind me later. */
define('WS_MAIL_REMIND_LATER', 'later');

/** The kind of a reminder set with Remind me if no answer comes. */
define('WS_MAIL_REMIND_NO_ANSWER', 'no_answer');

/** The hour "Tomorrow morning" and "Next week" remind at, in the person's zone. */
define('WS_MAIL_REMIND_MORNING', 9);

/** How many reminders one run of the job wakes. */
define('WS_MAIL_REMIND_BATCH', 50);

/**
 * Whether reminders can be kept (the table ws_mail_reminders). Asked by
 * name only, so the general job may call it before the Workspace loads.
 *
 * @return bool
 */
function ws_mail_remind_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_mail_reminders');
    }

    return $ready;
}

/**
 * The moment a choice names, in the person's time zone. Pure.
 *
 * hour: an hour from now; morning: tomorrow at 09:00; week: Monday of the
 * next week at 09:00; at: the typed time (the form's datetime-local value),
 * read as Send later reads it (ws_mail_later_parse()): at least 5 minutes
 * ahead, at most 60 days, an hour the zone skips refused.
 *
 * @param string $choice   hour | morning | week | at
 * @param mixed  $value    the typed time, for at
 * @param string $timezone an identifier; '' or an unknown one reads as UTC
 * @param int    $now
 * @return array ok, error (choice | missing | format | past | too_far), at
 */
function ws_mail_remind_time($choice, $value, $timezone, $now)
{
    $now = (int) $now;

    try {
        $zone = new DateTimeZone(($timezone !== '') ? $timezone : 'UTC');
    } catch (Exception $e) {
        $zone = new DateTimeZone('UTC');
    }

    $morning = function ($days) use ($zone, $now) {
        $day = new DateTime('@' . $now);
        $day->setTimezone($zone);
        $day->modify('+' . (int) $days . ' day');
        $day->setTime(WS_MAIL_REMIND_MORNING, 0, 0);

        return $day->getTimestamp();
    };

    switch ((string) $choice) {
        case 'hour':
            return array('ok' => true, 'error' => '', 'at' => $now + 3600);

        case 'morning':
            return array('ok' => true, 'error' => '', 'at' => $morning(1));

        case 'week':
            // ISO day: Monday 1 ... Sunday 7; the next week's Monday is 8 - day ahead.
            $today = new DateTime('@' . $now);
            $today->setTimezone($zone);

            return array('ok' => true, 'error' => '', 'at' => $morning(8 - (int) $today->format('N')));

        case 'at':
            // The same limits as Send later's: 5 minutes to 60 days ahead.
            return ws_mail_later_parse($value, $timezone, $now);
    }

    return array('ok' => false, 'error' => 'choice', 'at' => 0);
}

/**
 * What a refused reminder time is told.
 *
 * @param string $error ws_mail_remind_time() error
 * @return string
 */
function ws_mail_remind_error_text($error)
{
    $texts = array(
        'missing' => lang('Choose when you are reminded.'),
        'too_far' => lang(array('string' => 'A reminder can be at most {var:1} days away.', 'vars' => WS_MAIL_LATER_MAX_DAYS)),
    );

    return $texts[$error] ?? ws_mail_later_error_text($error);
}

/**
 * The person's time zone, else the site's.
 *
 * @return string
 */
function ws_mail_remind_zone()
{
    return (defined('USER_TIMEZONE') && (USER_TIMEZONE !== '')) ? USER_TIMEZONE : (string) date_default_timezone_get();
}

/**
 * The condition on ws_mail_conversations rows that leaves out the ones the
 * person set aside and that have not come back yet. Pure but for whether
 * the table is there; '' without it.
 *
 * @param int  $user_id
 * @param bool $ready ws_mail_remind_ready()
 * @return string SQL
 */
function ws_mail_remind_hidden_sql($user_id, $ready)
{
    if (!$ready || ((int) $user_id <= 0)) {
        return '';
    }

    return "NOT EXISTS (SELECT 1 FROM ws_mail_reminders mr WHERE mr.conversation_id = ws_mail_conversations.id"
        . " AND mr.user_id = '" . (int) $user_id . "' AND mr.kind = '" . WS_MAIL_REMIND_LATER . "' AND mr.woken_at = 0)";
}

/**
 * The condition that keeps a conversation whose reminder came back and
 * the person has not read since: it reads unread. Pure but for whether the
 * table is there; '' without it.
 *
 * @param int  $user_id
 * @param bool $ready ws_mail_remind_ready()
 * @return string SQL
 */
function ws_mail_remind_woken_sql($user_id, $ready)
{
    if (!$ready || ((int) $user_id <= 0)) {
        return '';
    }

    return "EXISTS (SELECT 1 FROM ws_mail_reminders mr WHERE mr.conversation_id = ws_mail_conversations.id"
        . " AND mr.user_id = '" . (int) $user_id . "' AND mr.kind = '" . WS_MAIL_REMIND_LATER . "' AND mr.woken_at > 0)";
}

/**
 * Which of some conversations came back from a reminder for the person
 * and are not read since.
 *
 * @param int   $user_id
 * @param int[] $conversation_ids
 * @return array conversation id => true
 */
function ws_mail_remind_woken_ids($user_id, $conversation_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $conversation_ids)));

    if (!$ids || !ws_mail_remind_ready()) {
        return array();
    }

    return array_fill_keys(array_map('intval', (array) db_values("SELECT conversation_id FROM ws_mail_reminders
        WHERE user_id = '" . (int) $user_id . "' AND kind = '" . WS_MAIL_REMIND_LATER . "' AND woken_at > 0
        AND conversation_id IN (" . implode(',', $ids) . ")")), true);
}

/**
 * The person read the conversation (its pane, its card in a channel): a
 * reminder that came back is done. One waiting for its time stays.
 *
 * @param int $user_id
 * @param int $conversation_id 0 for every conversation (Mark all as read)
 * @return void
 */
function ws_mail_remind_read($user_id, $conversation_id)
{
    if (((int) $user_id <= 0) || !ws_mail_remind_ready()) {
        return;
    }

    db("DELETE FROM ws_mail_reminders WHERE user_id = '" . (int) $user_id . "' AND kind = '" . WS_MAIL_REMIND_LATER . "' AND woken_at > 0"
        . (((int) $conversation_id > 0) ? " AND conversation_id = '" . (int) $conversation_id . "'" : ''));
}

/**
 * The person's reminder waiting on a conversation.
 *
 * @param int $user_id
 * @param int $conversation_id
 * @return array|null ws_mail_reminders row
 */
function ws_mail_remind_waiting($user_id, $conversation_id)
{
    if (!ws_mail_remind_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_mail_reminders WHERE conversation_id = '" . (int) $conversation_id . "'
        AND user_id = '" . (int) $user_id . "' AND kind = '" . WS_MAIL_REMIND_LATER . "' AND woken_at = 0");

    return is_array($row) ? $row : null;
}

/**
 * Remind me later, or Cancel reminder, from the reading pane: for any team
 * member who may read the conversation's channel.
 *
 * @param array  $viewer
 * @param int    $conversation_id
 * @param string $choice hour | morning | week | at | cancel
 * @param mixed  $value  the typed time, for at
 * @param int    $now
 * @return array ok, error, at (0 after a cancel)
 */
function ws_mail_remind_set($viewer, $conversation_id, $choice, $value, $now)
{
    if (!ws_mail_remind_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'), 'at' => 0);
    }

    $pane = !empty($viewer['member']) ? ws_mail_pane($viewer, (int) $conversation_id) : null;

    if (!$pane) {
        return array('ok' => false, 'error' => lang('This conversation cannot be opened.'), 'at' => 0);
    }

    $conversation_id = (int) $pane['conversation']['id'];
    $user_id = (int) $viewer['id'];

    if ((string) $choice === 'cancel') {
        db("DELETE FROM ws_mail_reminders WHERE conversation_id = '" . $conversation_id . "' AND user_id = '" . $user_id . "'
            AND kind = '" . WS_MAIL_REMIND_LATER . "'");

        return array('ok' => true, 'error' => '', 'at' => 0);
    }

    $time = ws_mail_remind_time($choice, $value, ws_mail_remind_zone(), $now);

    if (!$time['ok']) {
        return array('ok' => false, 'error' => ws_mail_remind_error_text($time['error']), 'at' => 0);
    }

    // A second choice moves the time; one that came back waits again.
    db("INSERT INTO ws_mail_reminders (conversation_id, user_id, kind, item_id, due_at, woken_at, created_at)
        VALUES ('" . $conversation_id . "', '" . $user_id . "', '" . WS_MAIL_REMIND_LATER . "', '" . (int) $pane['last_id'] . "',
            '" . (int) $time['at'] . "', 0, '" . (int) $now . "')
        ON DUPLICATE KEY UPDATE item_id = VALUES(item_id), due_at = VALUES(due_at), woken_at = 0, created_at = VALUES(created_at)");

    return array('ok' => true, 'error' => '', 'at' => (int) $time['at']);
}

/**
 * Brings one reminder back: claimed first (woken_at set where it is 0), so
 * two runs, or the job and a new mail, never tell twice; then the inbox
 * line, while the person may still read the conversation's channel.
 *
 * @param array $row ws_mail_reminders row
 * @param int   $now
 * @return bool whether the person was told
 */
function ws_mail_remind_wake($row, $now)
{
    db("UPDATE ws_mail_reminders SET woken_at = '" . max(1, (int) $now) . "' WHERE id = '" . (int) $row['id'] . "' AND woken_at = 0");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    $conversation = db_item("SELECT id, channel_id, message_id FROM ws_mail_conversations WHERE id = '" . (int) $row['conversation_id'] . "'");
    $channel = $conversation ? ws_channel((int) $conversation['channel_id']) : null;
    $user = function_exists('pg_load_user_row') ? pg_load_user_row((int) $row['user_id']) : null;
    $viewer = is_array($user) ? ws_viewer($user) : null;

    $no_answer = ((string) $row['kind'] === WS_MAIL_REMIND_NO_ANSWER);
    // The mail it waits on an answer to was undone into a draft, cancelled,
    // or its row is gone (the undone draft deleted or sent again as a new item).
    $item = $no_answer ? db_item("SELECT send_state FROM ws_mail_items
        WHERE id = '" . (int) $row['item_id'] . "' AND direction = 'out'") : null;
    $left = !$no_answer || ws_mail_no_answer_left($item ? (string) $item['send_state'] : null);

    // Gone, or no longer the person's to read: the row goes with nothing told.
    if (!$left || !$channel || !$viewer || empty($viewer['member']) || !ws_can_read_channel($viewer, $channel)) {
        db("DELETE FROM ws_mail_reminders WHERE id = '" . (int) $row['id'] . "'");

        return false;
    }

    ws_notify((int) $row['user_id'], $no_answer ? 'mail_no_answer' : 'mail_remind', array('channel_id' => (int) $channel['id'], 'message_id' => (int) $conversation['message_id'], 'actor_id' => 0, 'batch' => true));

    return true;
}

/**
 * Whether a reminder's time has come (the job's first look, one read by
 * idx_due). Callable before the Workspace loads.
 *
 * @return bool
 */
function ws_mail_reminders_waiting()
{
    return ws_mail_remind_ready() && (bool) db_value("SELECT id FROM ws_mail_reminders
        WHERE woken_at = 0 AND due_at <= '" . time() . "' LIMIT 1");
}

/**
 * The general job's part (ws_task_reminders_run()): the reminders whose
 * time has come, oldest first.
 *
 * @param int $limit
 * @return int how many people were told
 */
function ws_mail_reminders_run($limit = WS_MAIL_REMIND_BATCH)
{
    if (!ws_mail_remind_ready() || !function_exists('ws_mail_pane')) {
        return 0;
    }

    $now = time();
    $told = 0;

    foreach ((array) db_items("SELECT * FROM ws_mail_reminders WHERE woken_at = 0 AND due_at <= '" . $now . "'
        ORDER BY due_at, id LIMIT " . max(1, (int) $limit)) as $row) {
        $told += ws_mail_remind_wake($row, $now) ? 1 : 0;
    }

    if (($told > 0) && function_exists('ws_push_batch_done')) {
        ws_push_batch_done(true);
    }

    return $told;
}

/**
 * A new mail arrived in a conversation (ws_mail_ingest()): every Remind me
 * later still waiting on it comes back at once, and every Remind me if no
 * answer comes on it goes.
 *
 * @param int $conversation_id
 * @param int $now
 * @return int how many people were told
 */
function ws_mail_remind_new_mail($conversation_id, $now)
{
    if (((int) $conversation_id <= 0) || !ws_mail_remind_ready()) {
        return 0;
    }

    // An answer came: nobody waits on one any more, whether told or not.
    db("DELETE FROM ws_mail_reminders WHERE conversation_id = '" . (int) $conversation_id . "' AND kind = '" . WS_MAIL_REMIND_NO_ANSWER . "'");

    $told = 0;

    foreach ((array) db_items("SELECT * FROM ws_mail_reminders WHERE conversation_id = '" . (int) $conversation_id . "'
        AND kind = '" . WS_MAIL_REMIND_LATER . "' AND woken_at = 0 LIMIT 100") as $row) {
        $told += ws_mail_remind_wake($row, $now) ? 1 : 0;
    }

    if (($told > 0) && function_exists('ws_push_batch_done')) {
        ws_push_batch_done(true);
    }

    return $told;
}

/**
 * The days Remind me if no answer comes offers: 0 for no reminder and
 * anything else. Pure.
 *
 * @param mixed $value the form's choice
 * @return int 0, 1, 3 or 7
 */
function ws_mail_no_answer_days($value)
{
    $days = is_scalar($value) ? (int) $value : 0;

    return in_array($days, array(1, 3, 7), true) ? $days : 0;
}

/**
 * When a no-answer reminder is due: the days counted from the mail's
 * sending, which for a mail sent later (or waiting for Undo) is its send
 * time. Pure.
 *
 * @param int $days    ws_mail_no_answer_days()
 * @param int $send_at the item's send_at, 0 for at once
 * @param int $now
 * @return int
 */
function ws_mail_no_answer_due($days, $send_at, $now)
{
    return max((int) $now, (int) $send_at) + ((int) $days * 86400);
}

/**
 * Whether the mail a no-answer reminder waits on still left or is leaving:
 * one undone into a draft, cancelled before its time, or whose row is gone
 * (null: the undone draft was deleted or sent again) is not. '' is a mail
 * written before the send state was kept. Pure.
 *
 * @param string|null $state ws_mail_items.send_state, null for no row
 * @return bool
 */
function ws_mail_no_answer_left($state)
{
    if ($state === null) {
        return false;
    }

    return in_array((string) $state, array('', 'sent', 'queued', 'later', 'external'), true);
}

/**
 * Remind me if no answer comes, after a mail was written from the panel's
 * Reply by e-mail or New e-mail (ws_mail_send()'s result): one reminder per
 * writer and conversation; a later mail with the choice moves it.
 *
 * @param array $viewer
 * @param array $result ws_mail_send()'s, ok
 * @param mixed $days   the form's remind_days
 * @param int   $now
 * @return int the due time, 0 when none was set
 */
function ws_mail_no_answer_set($viewer, $result, $days, $now)
{
    $days = ws_mail_no_answer_days($days);
    $item_id = (int) ($result['item_id'] ?? 0);
    $conversation_id = (int) ($result['conversation_id'] ?? 0);

    if (($days === 0) || ($item_id <= 0) || ($conversation_id <= 0) || empty($viewer['member']) || !ws_mail_remind_ready()) {
        return 0;
    }

    $send_at = function_exists('ws_mail_later_ready') && ws_mail_later_ready()
        ? (int) db_value("SELECT send_at FROM ws_mail_items WHERE id = '" . $item_id . "'") : 0;
    $due = ws_mail_no_answer_due($days, $send_at, $now);

    db("INSERT INTO ws_mail_reminders (conversation_id, user_id, kind, item_id, due_at, woken_at, created_at)
        VALUES ('" . $conversation_id . "', '" . (int) $viewer['id'] . "', '" . WS_MAIL_REMIND_NO_ANSWER . "', '" . $item_id . "',
            '" . $due . "', 0, '" . (int) $now . "')
        ON DUPLICATE KEY UPDATE item_id = VALUES(item_id), due_at = VALUES(due_at), woken_at = 0, created_at = VALUES(created_at)");

    return $due;
}

/**
 * Which of some conversations the person was told have no answer yet (the
 * reminder came due and no mail came since).
 *
 * @param int   $user_id
 * @param int[] $conversation_ids
 * @return array conversation id => true
 */
function ws_mail_no_answer_ids($user_id, $conversation_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $conversation_ids)));

    if (!$ids || !ws_mail_remind_ready()) {
        return array();
    }

    return array_fill_keys(array_map('intval', (array) db_values("SELECT conversation_id FROM ws_mail_reminders
        WHERE user_id = '" . (int) $user_id . "' AND kind = '" . WS_MAIL_REMIND_NO_ANSWER . "' AND woken_at > 0
        AND conversation_id IN (" . implode(',', $ids) . ")")), true);
}

/**
 * "No answer yet" on the writer's list row and in the pane. Pure but for
 * the words.
 *
 * @return string HTML
 */
function ws_mail_no_answer_badge_html()
{
    return '<span class="badge theme-warning"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>' . h(lang('No answer yet')) . '</span>';
}

/**
 * What a reminder line says (ws_inbox_describe()): "Reminder: subject", or
 * "No answer yet: subject" for mail_no_answer, opening the conversation on
 * the E-mails screen.
 *
 * @param array  $row  ws_inbox row
 * @param string $base the panel's address, ending in /
 * @return array title, body, url, icon
 */
function ws_mail_remind_describe($row, $base)
{
    $conversation = ((int) $row['message_id'] > 0)
        ? db_item("SELECT id, subject, customer_name, customer_email FROM ws_mail_conversations WHERE message_id = '" . (int) $row['message_id'] . "' LIMIT 1")
        : null;
    $subject = is_array($conversation) ? (string) $conversation['subject'] : '';
    $customer = is_array($conversation) ? ((trim((string) $conversation['customer_name']) !== '') ? (string) $conversation['customer_name'] : (string) $conversation['customer_email']) : '';

    $subject = ($subject !== '') ? $subject : lang('(no subject)');

    return array(
        'title' => ((string) ($row['kind'] ?? '') === 'mail_no_answer')
            ? lang(array('string' => 'No answer yet: {var:1}', 'vars' => $subject))
            : lang(array('string' => 'Reminder: {var:1}', 'vars' => $subject)),
        'body'  => $customer,
        'url'   => $base . 'messages_mail.php' . (is_array($conversation) ? '?conversation=' . (int) $conversation['id'] : ''),
        'icon'  => ((string) ($row['kind'] ?? '') === 'mail_no_answer') ? 'bi-hourglass-split' : 'bi-alarm',
    );
}

/**
 * A reminder's time, short enough for a pane tool: day and month with the
 * hour in the person's zone, the year only when it is not this year, no
 * zone. The month is the English short name, translated by the caller.
 * Pure.
 *
 * @param int    $at
 * @param int    $now
 * @param string $timezone  an identifier; '' or an unknown one reads as UTC
 * @param bool   $month_first DATE_FORMAT month_day
 * @param bool   $twelve      TIME_FORMAT twelve_hours
 * @return string
 */
function ws_mail_remind_short_time($at, $now, $timezone, $month_first, $twelve)
{
    try {
        $zone = new DateTimeZone(($timezone !== '') ? $timezone : 'UTC');
    } catch (Exception $e) {
        $zone = new DateTimeZone('UTC');
    }

    $time = new DateTime('@' . (int) $at);
    $time->setTimezone($zone);
    $today = new DateTime('@' . (int) $now);
    $today->setTimezone($zone);

    $year = ($time->format('Y') !== $today->format('Y')) ? ' Y' : '';

    return $time->format(($month_first ? 'M j' : 'j M') . $year . ' ' . ($twelve ? 'g:i A' : 'H:i'));
}

/**
 * ws_mail_remind_short_time() for the person, its month in their language.
 *
 * @param int $at
 * @return string
 */
function ws_mail_remind_short_text($at)
{
    $text = ws_mail_remind_short_time($at, time(), ws_mail_remind_zone(),
        defined('DATE_FORMAT') && (DATE_FORMAT === 'month_day'), defined('TIME_FORMAT') && (TIME_FORMAT === 'twelve_hours'));
    $months = array();

    foreach (array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec') as $month) {
        $months[$month] = lang($month);
    }

    // strtr(): one pass, so a translated name is never translated again.
    return strtr($text, $months);
}

/**
 * Remind me later as the pane's script draws it: offered to a team member,
 * with the waiting reminder's time when one is set. Pure but for the words.
 *
 * @param bool       $member  the reader is a team member
 * @param bool       $ready   ws_mail_remind_ready()
 * @param array|null $waiting ws_mail_remind_waiting()
 * @return array|null on, at, short (the button's label), text (its title
 *                    and accessible name); null when it is not offered
 */
function ws_mail_remind_tool($member, $ready, $waiting)
{
    if (!$member || !$ready) {
        return null;
    }

    $at = is_array($waiting) ? (int) $waiting['due_at'] : 0;

    return array(
        'on'   => $at > 0,
        'at'   => $at,
        'short' => ($at > 0) ? ws_mail_remind_short_text($at) : '',
        'text' => ($at > 0) ? lang(array('string' => 'Reminds you at {var:1}', 'vars' => ws_mail_later_time_text($at))) : '',
    );
}

/**
 * The words of Remind me later (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_remind_js_strings()
{
    return array(
        'mail_remind'         => lang('Remind me later'),
        'mail_remind_hour'    => lang('In an hour'),
        'mail_remind_morning' => lang('Tomorrow morning'),
        'mail_remind_week'    => lang('Next week'),
        'mail_remind_at'      => lang('Choose a time'),
        'mail_remind_when'    => lang('Remind me at'),
        'mail_remind_hint'    => lang('In your own time zone. The conversation leaves your list until then, or until a new e-mail arrives in it.'),
        'mail_remind_save'    => lang('Set reminder'),
        'mail_remind_cancel'  => lang('Cancel reminder'),
        'mail_remind_set'     => lang('The conversation comes back at the chosen time.'),
        'mail_remind_off'     => lang('The reminder was cancelled.'),
        'mail_no_answer'      => lang('Remind me if no answer comes'),
        'mail_no_answer_none' => lang('No'),
        'mail_no_answer_1'    => lang('In 1 day'),
        'mail_no_answer_3'    => lang('In 3 days'),
        'mail_no_answer_7'    => lang('In 7 days'),
    );
}
