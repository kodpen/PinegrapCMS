<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's work in their own calendar: the tasks given to them,
 * the plan items about them, the days off and the approvals waiting for them,
 * read by the phone's or the desktop's calendar (Apple, Google, Outlook) from
 * an address of their own (workspace_ical.php).
 *
 * The address carries a random token, and the token is the only thing that
 * opens it: a calendar service reads it from its own servers, with no
 * session and no cookie. It is kept as its SHA-256 (token_hash), which is
 * what a request is looked up by, and once more encrypted with the site's key
 * (token_secret), so that the person can be shown their address again and
 * add it to a second device. A new address ends the old one at once; ending
 * the subscription deletes the row.
 *
 * What is read is decided again on every request, from the person's rights of
 * the moment: an account that has left the team, or a workspace switched off,
 * answers 404 as an unknown token does.
 *
 * Tasks go out as events (VEVENT), not to-dos (VTODO): the calendars a
 * subscription is added to - Google, Outlook, Apple's Calendar - read events
 * only and drop to-dos without a word. A task sits on its due day; one with a
 * due time is a moment at that time. A repeating task's coming copies are
 * there as tentative events. A plan item marked yearly carries a yearly rule
 * rather than a copy per year. Times are written in UTC, which every reader
 * places in its own time zone without a VTIMEZONE block; whole days are dates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How far back finished tasks and past plan items are kept, in days. */
define('WS_CALENDAR_FEED_PAST_DAYS', 60);

/** How far ahead tasks and plan items are read, in days. */
define('WS_CALENDAR_FEED_AHEAD_DAYS', 400);

/** How far ahead the coming copies of repeating tasks are drawn, in days. */
define('WS_CALENDAR_FEED_COPIES_DAYS', 90);

/** The most events one calendar carries. */
define('WS_CALENDAR_FEED_LIMIT', 2000);

/** The most a description carries, in characters. */
define('WS_CALENDAR_FEED_TEXT_MAX', 1000);

/**
 * Does the database carry calendar subscriptions (2026.4.10, 10.80)?
 *
 * @return bool
 */
function ws_calendar_feed_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_calendar_feeds' AND COLUMN_NAME = 'token_hash'") === 1);
    }

    return $ready;
}

/**
 * What a subscription can carry, in the order the screen offers it.
 *
 * @return array key => [label, hint]
 */
function ws_calendar_feed_kinds()
{
    return array(
        'tasks'     => array(
            'label' => lang('My tasks'),
            'hint'  => lang('Tasks given to you that have a date, on their due day, and the coming copies of repeating ones.'),
        ),
        'plan'      => array(
            'label' => lang('Plan items'),
            'hint'  => lang('Meetings, visits, leave and other items planned for you, your department or the whole company.'),
        ),
        'holidays'  => array(
            'label' => lang('Days off'),
            'hint'  => lang('Public holidays and the days the company or your department is closed.'),
        ),
        'approvals' => array(
            'label' => lang('Approval deadlines'),
            'hint'  => lang('Approval requests waiting for your answer, at the time they close.'),
        ),
    );
}

/**
 * The kinds asked for, the unknown ones left out, in the screen's order.
 *
 * @param mixed $kinds an array of keys, or the stored comma list
 * @return string[]
 */
function ws_calendar_feed_clean_kinds($kinds)
{
    if (!is_array($kinds)) {
        $kinds = explode(',', (string) $kinds);
    }

    $kinds = array_map('strval', array_filter($kinds, 'is_scalar'));

    return array_values(array_intersect(array_keys(ws_calendar_feed_kinds()), $kinds));
}

/**
 * A person's subscription.
 *
 * @param int $user_id
 * @return array|null
 */
function ws_calendar_feed_of_user($user_id)
{
    $row = db_item("SELECT * FROM ws_calendar_feeds WHERE user_id = '" . (int) $user_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Is this the shape of a token? Checked before the database is asked.
 *
 * @param string $token
 * @return bool
 */
function ws_calendar_feed_token_shape($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1);
}

/**
 * The subscription a token opens.
 *
 * @param string $token
 * @return array|null
 */
function ws_calendar_feed_for_token($token)
{
    if (!ws_calendar_feed_token_shape($token)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_calendar_feeds WHERE token_hash = '" . hash('sha256', $token) . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The token as it is kept for showing again: encrypted with the site's key,
 * "<ciphertext>:<iv>". Empty where the site has no key, and the address is
 * then shown once, when it is made.
 *
 * @param string $token
 * @return string
 */
function ws_calendar_feed_seal($token)
{
    if (!defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '') || !function_exists('openssl_encrypt')) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv((string) $token);

    return $cipher . ':' . $iv;
}

/**
 * The token of a subscription, read back; empty when it cannot be.
 *
 * @param array $feed
 * @return string
 */
function ws_calendar_feed_token($feed)
{
    $sealed = (string) ($feed['token_secret'] ?? '');

    if (($sealed === '') || (strpos($sealed, ':') === false) || !defined('ENCRYPTION_KEY') || !function_exists('openssl_decrypt')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);
    $token = decode_ssl_keys($cipher, $iv);

    // A token that no longer matches its hash (the site's key was changed)
    // would show an address that opens nothing.
    if (!ws_calendar_feed_token_shape($token) || !hash_equals((string) $feed['token_hash'], hash('sha256', $token))) {
        return '';
    }

    return $token;
}

/**
 * Makes a person's address, or a new one in place of the old: the old token
 * stops working at once. The kinds and the details choice are set on a new
 * subscription and kept on a renewed one.
 *
 * @param int      $user_id
 * @param string[] $kinds
 * @param bool     $details
 * @return string the new token
 */
function ws_calendar_feed_issue($user_id, $kinds, $details)
{
    $token = pg_short_link_new_token();
    $now = time();

    // One statement, so that a form sent twice keeps one row per person.
    db("INSERT INTO ws_calendar_feeds (user_id, token_hash, token_secret, kinds, details, created_at, updated_at)
        VALUES ('" . (int) $user_id . "', '" . hash('sha256', $token) . "', '" . e(ws_calendar_feed_seal($token)) . "',
            '" . e(implode(',', ws_calendar_feed_clean_kinds($kinds))) . "', '" . ($details ? 1 : 0) . "', '" . $now . "', '" . $now . "')
        ON DUPLICATE KEY UPDATE
            token_hash = VALUES(token_hash),
            token_secret = VALUES(token_secret),
            updated_at = VALUES(updated_at),
            fetched_at = 0,
            fetched_ip = '',
            fetched_agent = ''");

    return $token;
}

/**
 * Changes what a subscription carries.
 *
 * @param int      $user_id
 * @param string[] $kinds
 * @param bool     $details
 */
function ws_calendar_feed_save($user_id, $kinds, $details)
{
    db("UPDATE ws_calendar_feeds SET
            kinds = '" . e(implode(',', ws_calendar_feed_clean_kinds($kinds))) . "',
            details = '" . ($details ? 1 : 0) . "',
            updated_at = '" . time() . "'
        WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * Ends a person's subscription: the address opens nothing any more.
 *
 * @param int $user_id
 */
function ws_calendar_feed_end($user_id)
{
    db("DELETE FROM ws_calendar_feeds WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * The address a calendar reads.
 *
 * @param string $token
 * @return string
 */
function ws_calendar_feed_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_ical.php?token=' . $token;
}

/**
 * The same address for a link that opens the calendar application: Apple's
 * Calendar and the desktop Outlook take webcal:// as "subscribe to this".
 *
 * @param string $token
 * @return string
 */
function ws_calendar_feed_webcal_url($token)
{
    return 'webcal://' . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_ical.php?token=' . $token;
}

/**
 * A link that opens Google Calendar on its "add this calendar" question.
 * Google reads such an address over https only, so there is none for a site
 * that is not served over https.
 *
 * @param string $token
 * @return string
 */
function ws_calendar_feed_google_url($token)
{
    if (strtolower((string) URL_SCHEME) !== 'https://') {
        return '';
    }

    return 'https://calendar.google.com/calendar/render?cid=' . rawurlencode(ws_calendar_feed_webcal_url($token));
}

/**
 * Which application read the calendar, by its user agent, for the screen.
 *
 * @param string $agent
 * @return string a name, or '' when it does not say
 */
function ws_calendar_feed_reader_name($agent)
{
    $agent = strtolower((string) $agent);

    if ($agent === '') {
        return '';
    }

    $readers = array(
        'google'      => 'Google Calendar',
        'outlook'     => 'Microsoft Outlook',
        'microsoft'   => 'Microsoft Outlook',
        'exchange'    => 'Microsoft Outlook',
        'dataaccessd' => 'Apple Calendar',
        'calendaragent' => 'Apple Calendar',
        'icalendar'   => 'Apple Calendar',
        'thunderbird' => 'Thunderbird',
        'davx5'       => 'DAVx⁵',
        'icsx5'       => 'ICSx⁵',
    );

    foreach ($readers as $needle => $name) {
        if (strpos($agent, $needle) !== false) {
            return $name;
        }
    }

    return '';
}

/**
 * Notes who read the calendar and when: written once a minute at most.
 *
 * @param array $feed
 */
function ws_calendar_feed_seen($feed)
{
    $now = time();

    if ((int) $feed['fetched_at'] >= ($now - 60)) {
        return;
    }

    $ip = function_exists('waf_client_ip') ? (string) waf_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    db("UPDATE ws_calendar_feeds SET
            fetched_at = '" . $now . "',
            fetched_ip = '" . e(substr($ip, 0, 45)) . "',
            fetched_agent = '" . e($agent) . "'
        WHERE id = '" . (int) $feed['id'] . "'");
}

/* ---------------------------------------------------------------------------
   What the calendar carries
   --------------------------------------------------------------------------- */

/**
 * The events of one person's calendar.
 *
 * Every item is [uid, summary, description, url, all_day, start, end, rrule,
 * categories, status, transparent, stamp]: start and end are Y-m-d days
 * (end the last day, inclusive) for a whole-day item and timestamps for a
 * timed one; a timed item with no end is a moment, a deadline.
 *
 * @param array $viewer ws_viewer() of the person the subscription belongs to
 * @param array $feed
 * @return array[]
 */
function ws_calendar_feed_items($viewer, $feed)
{
    $kinds = ws_calendar_feed_clean_kinds($feed['kinds']);
    $details = ((int) $feed['details'] === 1);
    $today = date('Y-m-d');
    $since = date('Y-m-d', strtotime($today . ' 12:00:00 -' . WS_CALENDAR_FEED_PAST_DAYS . ' days'));
    $until = date('Y-m-d', strtotime($today . ' 12:00:00 +' . WS_CALENDAR_FEED_AHEAD_DAYS . ' days'));
    $items = array();

    if (in_array('tasks', $kinds, true)) {
        $items = array_merge($items, ws_calendar_feed_task_items($viewer, $details, $since, $until));
        $items = array_merge($items, ws_calendar_feed_copy_items($viewer, $today));
    }

    if (in_array('plan', $kinds, true) || in_array('holidays', $kinds, true)) {
        $items = array_merge($items, ws_calendar_feed_plan_items($viewer, $details, $since, $until, in_array('plan', $kinds, true), in_array('holidays', $kinds, true)));
    }

    if (in_array('approvals', $kinds, true)) {
        $items = array_merge($items, ws_calendar_feed_approval_items($viewer));
    }

    // The same answer for the same data, whatever order the reads came in:
    // the address answers with an ETag of its body.
    usort($items, function ($a, $b) {
        $first = is_int($a['start']) ? date('Y-m-d H:i', $a['start']) : $a['start'];
        $second = is_int($b['start']) ? date('Y-m-d H:i', $b['start']) : $b['start'];

        return strcmp($first, $second) ?: strcmp($a['uid'], $b['uid']);
    });

    return array_slice($items, 0, WS_CALENDAR_FEED_LIMIT);
}

/**
 * An address on the panel, whole, for a calendar that is read elsewhere.
 *
 * @param string $path after the software directory
 * @return string
 */
function ws_calendar_feed_panel_url($path)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . $path;
}

/**
 * The tasks given to the person that have a date: the open ones, however
 * late, and the ones done in the last WS_CALENDAR_FEED_PAST_DAYS days.
 *
 * @param array  $viewer
 * @param bool   $details with the task's description
 * @param string $since   Y-m-d
 * @param string $until   Y-m-d
 * @return array[]
 */
function ws_calendar_feed_task_items($viewer, $details, $since, $until)
{
    $rows = (array) db_items("SELECT t.* FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . (int) $viewer['id'] . "'
        WHERE (t.status IN ('todo', 'doing', 'waiting')
            OR (t.status = 'done' AND t.completed_at >= '" . (int) strtotime($since . ' 00:00:00') . "'))
        AND ((t.due_date IS NOT NULL AND t.due_date > '0000-00-00') OR (t.start_date IS NOT NULL AND t.start_date > '0000-00-00'))
        ORDER BY t.id DESC
        LIMIT " . WS_CALENDAR_FEED_LIMIT);

    $channels = array();
    $items = array();

    foreach ($rows as $row) {
        $window = ws_task_window($row);

        if (!$window || ($window[1] > $until)) {
            continue;
        }

        // The due day; the start day for a task that has only that.
        $date = $window[1];
        $due = (string) ($row['due_date'] ?? '');
        $time = (string) ($row['due_time'] ?? '');
        $timed = ($due === $date) && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]/', $time);
        $done = ((string) $row['status'] === 'done');
        $channel_id = (int) $row['channel_id'];

        if (($channel_id > 0) && !array_key_exists($channel_id, $channels)) {
            $channels[$channel_id] = ws_channel($channel_id);
        }

        $lines = array(
            ws_task_number($row['id']) . ' · ' . ws_task_status_label((string) $row['status'])
                . ' · ' . lang(array('string' => 'Priority: {var:1}', 'vars' => (ws_task_priorities()[(string) $row['priority']] ?? (string) $row['priority']))),
        );

        if (($channel_id > 0) && !empty($channels[$channel_id])) {
            $lines[] = lang(array('string' => 'Channel: #{var:1}', 'vars' => (string) $channels[$channel_id]['name']));
        }

        if ($details && (trim((string) $row['description']) !== '')) {
            $lines[] = '';
            $lines[] = ws_plain_excerpt($viewer, (string) $row['description'], WS_CALENDAR_FEED_TEXT_MAX);
        }

        $url = ws_calendar_feed_panel_url('workspace_tasks.php?task=' . (int) $row['id']);
        $lines[] = '';
        $lines[] = $url;

        $items[] = array(
            'uid'         => 'ws-task-' . (int) $row['id'],
            'summary'     => ($done ? '✓ ' : '') . (string) $row['title'],
            'description' => implode("\n", $lines),
            'url'         => $url,
            'all_day'     => !$timed,
            'start'       => $timed ? (int) strtotime($date . ' ' . substr($time, 0, 5) . ':00') : $date,
            'end'         => $timed ? null : $date,
            'rrule'       => '',
            'categories'  => array(lang('Task')),
            'status'      => 'CONFIRMED',
            'transparent' => true,
            'stamp'       => max((int) $row['updated_at'], (int) $row['created_at']),
        );
    }

    return $items;
}

/**
 * The coming copies of the person's repeating tasks, for the next
 * WS_CALENDAR_FEED_COPIES_DAYS days: tentative, since a copy is made only
 * when its day comes.
 *
 * @param array  $viewer
 * @param string $today Y-m-d
 * @return array[]
 */
function ws_calendar_feed_copy_items($viewer, $today)
{
    if (!function_exists('ws_recurrence_projections')) {
        return array();
    }

    $until = date('Y-m-d', strtotime($today . ' 12:00:00 +' . WS_CALENDAR_FEED_COPIES_DAYS . ' days'));
    $items = array();

    foreach (ws_recurrence_projections($today, $until) as $projection) {
        if (!in_array((int) $viewer['id'], array_map('intval', $projection['people']), true)) {
            continue;
        }

        $task = $projection['task'];
        $time = (string) ($task['due_time'] ?? '');
        $timed = (bool) preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]/', $time);
        $url = ws_calendar_feed_panel_url('workspace_tasks.php?task=' . (int) $task['id']);
        $repeat = function_exists('ws_recurrence_label') ? ws_recurrence_label($projection['series']) : '';

        foreach ($projection['copies'] as $copy) {
            $date = (string) $copy['due'];

            $lines = array(lang('A coming copy of a repeating task: it is made on its start day.'));

            if ($repeat !== '') {
                $lines[] = $repeat;
            }

            $lines[] = '';
            $lines[] = $url;

            $items[] = array(
                'uid'         => 'ws-series-' . (int) $projection['series']['id'] . '-' . str_replace('-', '', (string) $copy['date']),
                'summary'     => (string) $task['title'],
                'description' => implode("\n", $lines),
                'url'         => $url,
                'all_day'     => !$timed,
                'start'       => $timed ? (int) strtotime($date . ' ' . substr($time, 0, 5) . ':00') : $date,
                'end'         => $timed ? null : $date,
                'rrule'       => '',
                'categories'  => array(lang('Task')),
                'status'      => 'TENTATIVE',
                'transparent' => true,
                'stamp'       => max((int) $task['updated_at'], (int) $task['created_at']),
            );
        }
    }

    return $items;
}

/**
 * The plan items about the person - their own, their departments', the
 * company's - and the days off.
 *
 * @param array  $viewer
 * @param bool   $details  with the item's note
 * @param string $since    Y-m-d
 * @param string $until    Y-m-d
 * @param bool   $plan     meetings, visits, leave and the rest
 * @param bool   $holidays the days off
 * @return array[]
 */
function ws_calendar_feed_plan_items($viewer, $details, $since, $until, $plan, $holidays)
{
    $from = (int) strtotime($since . ' 00:00:00');
    $to = (int) strtotime($until . ' 23:59:59');

    // A yearly item is read once, however long ago it began, and carries a
    // yearly rule; a day read from a holiday calendar and left out is no day
    // off anywhere.
    $rows = (array) db_items("SELECT * FROM ws_events
        WHERE skipped = 0
        AND ((yearly = 0 AND starts_at <= '" . $to . "' AND ends_at >= '" . $from . "')
            OR (yearly = 1 AND starts_at <= '" . $to . "'))
        ORDER BY starts_at, id
        LIMIT " . WS_CALENDAR_FEED_LIMIT);

    if (empty($rows)) {
        return array();
    }

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $people = array();

    foreach ((array) db_items("SELECT event_id, user_id FROM ws_event_people WHERE event_id IN (" . implode(',', $ids) . ")") as $row) {
        $people[(int) $row['event_id']][] = (int) $row['user_id'];
    }

    $departments = ws_user_department_ids((int) $viewer['id']);
    $kinds = ws_event_kinds();
    $url = ws_calendar_feed_panel_url('workspace_calendar.php');
    $items = array();

    foreach ($rows as $row) {
        $row['people'] = $people[(int) $row['id']] ?? array();
        $holiday = ((string) $row['kind'] === 'holiday');

        if (($holiday && !$holidays) || (!$holiday && !$plan)) {
            continue;
        }

        if (!ws_event_applies($row, (int) $viewer['id'], $departments)) {
            continue;
        }

        $kind = $kinds[(string) $row['kind']] ?? (string) $row['kind'];
        $all_day = ((int) $row['all_day'] === 1);
        $starts = (int) $row['starts_at'];
        $ends = max($starts, (int) $row['ends_at']);
        $title = trim((string) $row['title']);

        $lines = array($kind);

        if ($details && (trim((string) $row['note']) !== '')) {
            $lines[] = '';
            $lines[] = trim((string) $row['note']);
        }

        // The video call (10.86): in the text, and as the place, where a
        // calendar app puts its join link.
        $meeting = (string) ($row['meeting_url'] ?? '');

        if ($meeting !== '') {
            $lines[] = '';
            $lines[] = lang('Join') . ': ' . $meeting;
        }

        $lines[] = '';
        $lines[] = $url;

        $rule = '';

        if ((int) $row['yearly'] === 1) {
            // A 29 February comes back on the last day of February, as the
            // workspace itself brings it into a year without one.
            $rule = (date('m-d', $starts) === '02-29') ? 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=-1' : 'FREQ=YEARLY';
        }

        $items[] = array(
            'uid'         => 'ws-event-' . (int) $row['id'],
            'summary'     => ($title !== '') ? $title : $kind,
            'description' => implode("\n", $lines),
            'url'         => $url,
            'location'    => $meeting,
            'all_day'     => $all_day,
            'start'       => $all_day ? date('Y-m-d', $starts) : $starts,
            'end'         => $all_day ? date('Y-m-d', $ends) : (($ends > $starts) ? $ends : null),
            'rrule'       => $rule,
            'categories'  => array($kind),
            'status'      => 'CONFIRMED',
            // Leave, a meeting and a visit take the time; a day off is a day
            // nobody works.
            'transparent' => $holiday,
            'stamp'       => (int) $row['created_at'],
        );
    }

    return $items;
}

/**
 * The approval requests waiting for the person's answer that close at a set
 * time, as a moment at that time.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_calendar_feed_approval_items($viewer)
{
    if (!function_exists('ws_approvals_ready') || !ws_approvals_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT a.* FROM ws_approval_people p
        INNER JOIN ws_approvals a ON a.id = p.approval_id AND a.closed_at = 0 AND a.closes_at > 0
        INNER JOIN ws_messages m ON m.id = a.message_id AND m.deleted_at = 0
        WHERE p.user_id = '" . (int) $viewer['id'] . "' AND p.decision = 'pending'
        ORDER BY a.closes_at, a.id
        LIMIT 500");

    $channels = array();
    $items = array();

    foreach ($rows as $row) {
        $channel_id = (int) $row['channel_id'];

        if (!array_key_exists($channel_id, $channels)) {
            $channels[$channel_id] = ws_channel($channel_id);
        }

        $channel = $channels[$channel_id];

        if (!$channel || !ws_can_post_channel($viewer, $channel)) {
            continue;
        }

        $url = ws_calendar_feed_panel_url('workspace.php?channel=' . $channel_id . '&message=' . (int) $row['message_id']);

        $lines = array(
            lang(array('string' => 'Asked by {var:1}', 'vars' => ws_person_name($row['created_by']))),
            lang(array('string' => 'Channel: #{var:1}', 'vars' => (string) $channel['name'])),
            '',
            $url,
        );

        $items[] = array(
            'uid'         => 'ws-approval-' . (int) $row['id'],
            'summary'     => lang(array('string' => 'Approval: {var:1}', 'vars' => (string) $row['title'])),
            'description' => implode("\n", $lines),
            'url'         => $url,
            'all_day'     => false,
            'start'       => (int) $row['closes_at'],
            'end'         => null,
            'rrule'       => '',
            'categories'  => array(lang('Approval')),
            'status'      => 'CONFIRMED',
            'transparent' => true,
            'stamp'       => (int) $row['created_at'],
        );
    }

    return $items;
}

/**
 * Answers a calendar's request for the address: the calendar, a 304 when it
 * holds this version already, a 404 for anything that opens nothing.
 *
 * @param string $token
 */
function ws_calendar_feed_serve($token)
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if (($method !== 'GET') && ($method !== 'HEAD')) {
        ws_calendar_feed_refuse(405);
    }

    // One answer for every address that opens nothing - an unknown token, one
    // that was renewed or ended, an account no longer in the team, a module
    // switched off - so the answer says nothing about which. No offence is
    // counted: a calendar keeps asking for an ended address from the same
    // servers that read the new one, and the token cannot be guessed.
    if (!ws_enabled() || !ws_calendar_feed_ready()) {
        ws_calendar_feed_refuse(404);
    }

    $feed = ws_calendar_feed_for_token((string) $token);

    if (!$feed) {
        ws_calendar_feed_refuse(404);
    }

    $user = pg_load_user_row((int) $feed['user_id']);

    if (!is_array($user)) {
        // The account was deleted: its address goes with it.
        ws_calendar_feed_end((int) $feed['user_id']);
        ws_calendar_feed_refuse(404);
    }

    $viewer = ws_viewer($user);

    if (!$viewer['member']) {
        ws_calendar_feed_refuse(404);
    }

    ws_calendar_feed_seen($feed);

    $person = ws_people(array((int) $user['id']));
    $name = (string) ($person[(int) $user['id']]['name'] ?? $user['username']);

    $body = ws_ics_calendar(ws_calendar_feed_items($viewer, $feed), array(
        'host'     => HOSTNAME_SETTING,
        'name'     => lang(array('string' => '{var:1} · Workspace ({var:2})', 'vars' => array($name, HOSTNAME_SETTING))),
        'about'    => lang('Your tasks, plan items, days off and approval deadlines from the workspace. Read only: changes are made on the site.'),
        'timezone' => date_default_timezone_get(),
    ));

    $etag = '"' . sha1($body) . '"';

    ws_calendar_feed_discard_output();

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="workspace.ics"');
    // The address is one person's: no shared cache may keep it.
    header('Cache-Control: private, max-age=300');
    header('ETag: ' . $etag);
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');

    $match = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');

    if (($match !== '') && in_array($etag, array_map(function ($tag) {
        return preg_replace('/^W\//', '', trim($tag));
    }, explode(',', $match)), true)) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . strlen($body));

    if ($method !== 'HEAD') {
        echo $body;
    }

    exit;
}

/**
 * Ends the request with a status and nothing else.
 *
 * @param int $status
 */
function ws_calendar_feed_refuse($status)
{
    ws_calendar_feed_discard_output();

    http_response_code((int) $status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');

    if ((int) $status === 405) {
        header('Allow: GET, HEAD');
    }

    exit;
}

/**
 * Drops whatever the shared code printed on the way here, which would sit in
 * front of the calendar and break it.
 */
function ws_calendar_feed_discard_output()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/* ---------------------------------------------------------------------------
   Writing iCalendar (RFC 5545)
   --------------------------------------------------------------------------- */

/**
 * A text value: backslash, semicolon, comma and line break escaped, other
 * control characters dropped, a broken UTF-8 sequence replaced.
 *
 * @param string $text
 * @return string
 */
function ws_ics_text($text)
{
    $text = mb_convert_encoding((string) $text, 'UTF-8', 'UTF-8');
    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    $text = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);

    return str_replace(array('\\', ';', ',', "\n"), array('\\\\', '\\;', '\\,', '\\n'), $text);
}

/**
 * A content line folded at 75 octets, never inside a UTF-8 character; each
 * continuation starts with a space, which counts towards its 75.
 *
 * @param string $line
 * @return string
 */
function ws_ics_fold($line)
{
    $line = (string) $line;

    if (strlen($line) <= 75) {
        return $line;
    }

    if (!preg_match_all('/./us', $line, $characters)) {
        return implode("\r\n ", str_split($line, 74));
    }

    $out = '';
    $width = 0;

    foreach ($characters[0] as $character) {
        $bytes = strlen($character);

        if (($width + $bytes) > 75) {
            $out .= "\r\n ";
            $width = 1;
        }

        $out .= $character;
        $width += $bytes;
    }

    return $out;
}

/**
 * A day as a DATE value.
 *
 * @param string $date Y-m-d
 * @return string
 */
function ws_ics_date($date)
{
    return str_replace('-', '', substr((string) $date, 0, 10));
}

/**
 * A moment as a DATE-TIME value in UTC.
 *
 * @param int $timestamp
 * @return string
 */
function ws_ics_utc($timestamp)
{
    return gmdate('Ymd\THis\Z', (int) $timestamp);
}

/**
 * The lines of one event (see ws_calendar_feed_items() for the item).
 *
 * @param array  $item
 * @param string $host the site, which makes the UID unique beyond it
 * @return string[] unfolded content lines
 */
function ws_ics_event($item, $host)
{
    // A record with no time of its own still needs a stamp, and a fixed one
    // keeps the calendar's ETag from changing on every request.
    $stamp = ws_ics_utc(((int) $item['stamp'] > 0) ? (int) $item['stamp'] : 946684800);

    $lines = array(
        'BEGIN:VEVENT',
        'UID:' . ws_ics_text($item['uid'] . '@' . $host),
        'DTSTAMP:' . $stamp,
    );

    if (!empty($item['all_day'])) {
        $last = (string) ($item['end'] ?? $item['start']);
        $last = ($last < (string) $item['start']) ? (string) $item['start'] : $last;

        // DTEND of a whole-day event is the day after its last.
        $lines[] = 'DTSTART;VALUE=DATE:' . ws_ics_date($item['start']);
        $lines[] = 'DTEND;VALUE=DATE:' . ws_ics_date(date('Y-m-d', strtotime($last . ' 12:00:00 +1 day')));
    } else {
        $lines[] = 'DTSTART:' . ws_ics_utc($item['start']);

        // With no end the event is a moment (RFC 5545 3.6.1): a deadline.
        if (!empty($item['end']) && ((int) $item['end'] > (int) $item['start'])) {
            $lines[] = 'DTEND:' . ws_ics_utc($item['end']);
        }
    }

    if ((string) ($item['rrule'] ?? '') !== '') {
        $lines[] = 'RRULE:' . $item['rrule'];
    }

    $lines[] = 'SUMMARY:' . ws_ics_text($item['summary']);

    if ((string) ($item['description'] ?? '') !== '') {
        $lines[] = 'DESCRIPTION:' . ws_ics_text($item['description']);
    }

    if ((string) ($item['location'] ?? '') !== '') {
        $lines[] = 'LOCATION:' . ws_ics_text($item['location']);
    }

    if ((string) ($item['url'] ?? '') !== '') {
        $lines[] = 'URL:' . preg_replace('/[\x00-\x20\x7F]/', '', (string) $item['url']);
    }

    if (!empty($item['categories'])) {
        $lines[] = 'CATEGORIES:' . implode(',', array_map('ws_ics_text', (array) $item['categories']));
    }

    if ((string) ($item['status'] ?? '') !== '') {
        $lines[] = 'STATUS:' . $item['status'];
    }

    $lines[] = 'TRANSP:' . (!empty($item['transparent']) ? 'TRANSPARENT' : 'OPAQUE');
    $lines[] = 'LAST-MODIFIED:' . $stamp;
    $lines[] = 'END:VEVENT';

    return $lines;
}

/**
 * A whole calendar, folded, with CRLF line ends.
 *
 * @param array[] $items
 * @param array   $meta host, name, about, timezone
 * @return string
 */
function ws_ics_calendar($items, $meta)
{
    $lines = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Kodpen//Pinegrap//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    );

    if ((string) ($meta['name'] ?? '') !== '') {
        $lines[] = 'NAME:' . ws_ics_text($meta['name']);
        $lines[] = 'X-WR-CALNAME:' . ws_ics_text($meta['name']);
    }

    if ((string) ($meta['about'] ?? '') !== '') {
        $lines[] = 'DESCRIPTION:' . ws_ics_text($meta['about']);
        $lines[] = 'X-WR-CALDESC:' . ws_ics_text($meta['about']);
    }

    if ((string) ($meta['timezone'] ?? '') !== '') {
        $lines[] = 'X-WR-TIMEZONE:' . ws_ics_text($meta['timezone']);
    }

    // How often a reader should come back (RFC 7986, and the older name
    // Outlook reads). Most readers keep their own pace; Google reads a few
    // times a day whatever is asked.
    $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT1H';
    $lines[] = 'X-PUBLISHED-TTL:PT1H';

    foreach ((array) $items as $item) {
        $lines = array_merge($lines, ws_ics_event($item, (string) ($meta['host'] ?? 'localhost')));
    }

    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('ws_ics_fold', $lines)) . "\r\n";
}
