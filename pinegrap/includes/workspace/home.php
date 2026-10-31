<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the overview: the page a person lands on when they have not
 * pinned a channel, and whenever they come back to it from the sidebar. It
 * greets them by the hour and tells them what waits for them (replies,
 * mentions, approvals, read receipts), their tasks due today or before,
 * today's calendar, what happened in their channels, the latest decisions,
 * the channels they could join, and what this place is for.
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
 * How far back replies are looked for, in messages: the overview is about
 * what is recent, and a reply is found by joining a message to the one it
 * answers.
 */
define('WS_HOME_REPLY_WINDOW', 20000);

/**
 * Everything the overview shows.
 *
 * @param array $viewer
 * @return array
 */
function ws_home($viewer)
{
    $me = (int) $viewer['id'];
    $person = ws_people(array($me));
    $today = date('Y-m-d');

    $counts = db_item("SELECT COUNT(*) AS open_count,
            SUM(t.due_date > '0000-00-00' AND t.due_date < '" . e($today) . "') AS overdue_count,
            SUM(t.due_date > '0000-00-00' AND t.due_date <= '" . e($today) . "') AS due_count
        FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
        WHERE t.status IN ('todo', 'doing', 'waiting')");

    $timeline = ws_timeline($viewer, ws_timeline_filters(array()), null, 5);

    // Somebody who has never written anything is shown what this place is
    // for; the others see it folded.
    $written = (int) db_value("SELECT id FROM ws_messages WHERE sender_kind = 'user' AND sender_id = '" . $me . "' LIMIT 1");

    // Coming up, the pressing first: urgent and high tasks due within a week
    // (or overdue) lead, the rest follow by their due date.
    $hot = ws_tasks_list($viewer, array('scope' => 'mine', 'status' => 'open', 'priority' => 'hot', 'soon' => 7, 'limit' => 5));
    $tasks = $hot;
    $shown = array();

    foreach ($hot as $task) {
        $shown[(int) $task['id']] = true;
    }

    foreach (ws_tasks_list($viewer, array('scope' => 'mine', 'status' => 'open', 'limit' => 10)) as $task) {
        if ((count($tasks) < 5) && !isset($shown[(int) $task['id']])) {
            $tasks[] = $task;
        }
    }

    // Due today or before, the earliest first, as the my-day route reads
    // them (api_day.php).
    $due = array();

    foreach (ws_tasks_list($viewer, array('scope' => 'mine', 'status' => 'open', 'to' => $today, 'limit' => 20)) as $brief) {
        if (($brief['due_date'] !== null) && (count($due) < 6)) {
            $due[] = $brief;
        }
    }

    // The unread messages and mentions of the joined channels, as the
    // sidebar counts them.
    $channels = ws_channels_for($viewer);
    $unread = 0;
    $mentions = 0;

    foreach ($channels as $channel) {
        if ($channel['joined']) {
            $unread += (int) $channel['unread'];
            $mentions += (int) $channel['mentions'];
        }
    }

    // Approval requests and read receipts waiting for the person
    // (approvals.php, acks.php): all of them counted, five of each shown.
    $approvals = function_exists('ws_approvals_waiting') ? ws_approvals_waiting($viewer, 50) : array();
    $acks = function_exists('ws_acks_waiting') ? ws_acks_waiting($viewer, 20) : array();

    return array(
        'name'          => (string) ($person[$me]['name'] ?? ''),
        'date'          => ws_home_date_label($today),
        'greeting'      => ws_home_greeting((int) date('G')),
        'lead'          => ws_home_lead(array(
            'messages'  => $unread,
            'mentions'  => $mentions,
            'approvals' => count($approvals),
            'acks'      => count($acks),
            'due'       => (int) ($counts['due_count'] ?? 0),
        )),
        'due'           => $due,
        'tasks_due'     => (int) ($counts['due_count'] ?? 0),
        'calendar'      => ws_home_calendar($viewer, $today),
        'digest'        => ws_home_digest($viewer, $channels, 5),
        'tasks'         => $tasks,
        'tasks_hot'     => count($hot),
        'tasks_open'    => (int) ($counts['open_count'] ?? 0),
        'tasks_overdue' => (int) ($counts['overdue_count'] ?? 0),
        'talk'          => ws_home_talk($viewer, 5),
        'approvals'     => array_slice($approvals, 0, 5),
        'acks'          => array_slice($acks, 0, 5),
        'decisions'     => ws_timeline_items($viewer, $timeline['rows'], $timeline['channels']),
        'join'          => ws_home_joinable($viewer, 5),
        'newcomer'      => ($written === 0),
        'claude'        => function_exists('ws_claude_ready') && ws_claude_ready(),
        'ai'            => function_exists('ws_ai_ready') && ws_ai_ready(),
    );
}

/**
 * The words the overview greets with, by the hour: the plain ones of the
 * channel's greeting (ws_channel_briefing()).
 *
 * @param int $hour 0-23
 * @return string
 */
function ws_home_greeting($hour)
{
    $hour = (int) $hour;

    if (($hour < 5) || ($hour >= 23)) {
        return lang('Good night');
    }

    if ($hour < 12) {
        return lang('Good morning');
    }

    return ($hour < 18) ? lang('Good afternoon') : lang('Good evening');
}

/**
 * The line under the greeting: what is new and what waits, each count a part
 * the page joins with dots ("3 new messages · 1 mention · 1 approval
 * request"), or the one sentence that nothing does.
 *
 * @param array $counts messages, mentions, approvals, acks, due
 * @return string[]
 */
function ws_home_lead($counts)
{
    $count = function ($key) use ($counts) {
        return max(0, (int) ($counts[$key] ?? 0));
    };
    $messages = $count('messages');
    $mentions = $count('mentions');
    $approvals = $count('approvals');
    $acks = $count('acks');
    $due = $count('due');
    $parts = array();

    if ($messages > 0) {
        $parts[] = lang(array('string' => '{var:1} new message{suffix:1}', 'vars' => $messages, 'suffix' => array(($messages === 1) ? '' : 's')));
    }

    if ($mentions > 0) {
        $parts[] = lang(array('string' => '{var:1} mention{suffix:1}', 'vars' => $mentions, 'suffix' => array(($mentions === 1) ? '' : 's')));
    }

    if ($approvals > 0) {
        $parts[] = lang(array('string' => '{var:1} approval request{suffix:1}', 'vars' => $approvals, 'suffix' => array(($approvals === 1) ? '' : 's')));
    }

    if ($acks > 0) {
        $parts[] = lang(array('string' => '{var:1} read receipt{suffix:1} asked of you', 'vars' => $acks, 'suffix' => array(($acks === 1) ? '' : 's')));
    }

    if ($due > 0) {
        $parts[] = lang(array('string' => '{var:1} task{suffix:1} due today or overdue', 'vars' => $due, 'suffix' => array(($due === 1) ? '' : 's')));
    }

    return $parts ? $parts : array(lang('Everything is read and nothing is due today.'));
}

/**
 * Today's plan items that are about the person (schedule.php) and the events
 * of their own calendars (personal_ical.php), as the calendar card lists them.
 *
 * @param array  $viewer
 * @param string $today Y-m-d
 * @return array[] ws_home_calendar_rows()
 */
function ws_home_calendar($viewer, $today)
{
    $me = (int) $viewer['id'];
    $start = strtotime($today . ' 00:00:00');
    $end = strtotime($today . ' 23:59:59');
    $departments = ws_user_department_ids($me);
    $items = array();

    foreach (ws_events_between($today, $today) as $event) {
        if (ws_event_applies($event, $me, $departments)) {
            $items[] = $event;
        }
    }

    if (function_exists('ws_personal_ical_layer')) {
        foreach (ws_personal_ical_layer($me) as $event) {
            if (((int) $event['starts_at'] <= $end) && ((int) $event['ends_at'] >= $start)) {
                $items[] = $event;
            }
        }
    }

    return ws_home_calendar_rows($items, $start, $end, time());
}

/**
 * The calendar card's rows: the all-day items first, then by the hour, at
 * most eight; the one going on now and the ones already over marked.
 *
 * @param array[] $items ws_events rows or personal calendar events
 * @param int     $start today's first second
 * @param int     $end   today's last second
 * @param int     $now
 * @return array[] id, title, kind, all_day, time, until, now, past, meeting_url, layer
 */
function ws_home_calendar_rows($items, $start, $end, $now)
{
    $rows = array();

    foreach ($items as $event) {
        $all_day = !empty($event['all_day']);
        $from = max((int) $start, (int) $event['starts_at']);
        $to = min((int) $end, (int) $event['ends_at']);

        $rows[] = array(
            'id'          => (int) ($event['id'] ?? 0),
            'title'       => (string) ($event['title'] ?? ''),
            'kind'        => (string) ($event['kind'] ?? ''),
            'all_day'     => $all_day,
            'time'        => $all_day ? '' : date('H:i', $from),
            'until'       => $all_day ? '' : date('H:i', $to),
            'now'         => !$all_day && ($from <= $now) && ($now < $to),
            'past'        => !$all_day && ($to <= $now),
            'meeting_url' => (string) ($event['meeting_url'] ?? ''),
            'layer'       => (string) ($event['layer'] ?? ''),
            'at'          => $all_day ? 0 : $from,
        );
    }

    usort($rows, function ($a, $b) {
        return ($a['at'] - $b['at']) ?: ($a['id'] - $b['id']);
    });

    return array_map(function ($row) {
        unset($row['at']);

        return $row;
    }, array_slice($rows, 0, 8));
}

/**
 * What happened in the person's channels: the joined ones with messages they
 * have not read, each with its unread count and its last message, who wrote
 * it and its first words; the channel whose last message is the latest
 * first. System lines are not counted as unread, so they neither show nor
 * order the list.
 *
 * @param array   $viewer
 * @param array[] $channels ws_channels_for() of the person
 * @param int     $limit
 * @return array[] id, name, kind, unread, mentions, message_id, sender, text, time
 */
function ws_home_digest($viewer, $channels, $limit = 5)
{
    $unread = array();

    foreach ($channels as $channel) {
        if ($channel['joined'] && ($channel['unread'] > 0)) {
            $unread[(int) $channel['id']] = $channel;
        }
    }

    if (empty($unread)) {
        return array();
    }

    // Each channel's last message, in one grouped read.
    $last = array();

    foreach ((array) db_items("SELECT channel_id, MAX(id) AS id FROM ws_messages
        WHERE channel_id IN (" . implode(',', array_keys($unread)) . ") AND deleted_at = 0 AND sender_kind <> 'system'
        GROUP BY channel_id") as $row) {
        $last[(int) $row['channel_id']] = (int) $row['id'];
    }

    $ids = array_keys($unread);

    usort($ids, function ($a, $b) use ($last) {
        return (($last[$b] ?? 0) - ($last[$a] ?? 0)) ?: ($b - $a);
    });

    $ids = array_slice($ids, 0, (int) $limit);
    $messages = array();
    $wanted = array();

    foreach ($ids as $id) {
        if (!empty($last[$id])) {
            $wanted[] = (int) $last[$id];
        }
    }

    if ($wanted) {
        foreach ((array) db_items("SELECT * FROM ws_messages WHERE id IN (" . implode(',', $wanted) . ")") as $row) {
            $messages[(int) $row['channel_id']] = $row;
        }
    }

    list($people, $mail) = ws_message_rows_senders(array_values($messages));
    $out = array();

    foreach ($ids as $id) {
        $channel = $unread[$id];
        $row = $messages[$id] ?? null;
        $sender = null;
        $text = '';

        if (is_array($row)) {
            $sender = ws_message_row_sender($row, $people, $mail);
            $text = ws_plain_line($viewer, $row['body']);

            if (mb_strlen($text) > 120) {
                $text = rtrim(mb_substr($text, 0, 119)) . '…';
            }
        }

        $out[] = array(
            'id'         => (int) $channel['id'],
            'name'       => (string) $channel['name'],
            'kind'       => (string) $channel['kind'],
            'unread'     => (int) $channel['unread'],
            'mentions'   => (int) $channel['mentions'],
            'message_id' => is_array($row) ? (int) $row['id'] : 0,
            'sender'     => is_array($sender) ? (string) ($sender['name'] ?? '') : '',
            'text'       => $text,
            'time'       => is_array($row) ? ws_time_label((int) $row['created_at']) : '',
        );
    }

    return $out;
}

/**
 * Today as the overview heads it: "24 September, Thursday".
 *
 * @param string $date Y-m-d
 * @return string
 */
function ws_home_date_label($date)
{
    $time = strtotime($date . ' 12:00:00');

    $weekdays = array(
        1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'),
        5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'),
    );

    return (int) date('j', $time) . ' ' . ws_recurrence_month_names()[(int) date('n', $time)] . ', ' . $weekdays[(int) date('N', $time)];
}

/**
 * What was written to the person lately: replies to their messages (Claude's
 * answers to their requests among them) and messages that mention them,
 * newest first, from the channels they may still read.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_home_talk($viewer, $limit = 5)
{
    $me = (int) $viewer['id'];
    $found = array();

    // Replies: messages whose parent the person wrote, among the latest ones.
    $floor = max(0, (int) db_value("SELECT MAX(id) FROM ws_messages") - WS_HOME_REPLY_WINDOW);

    foreach ((array) db_items("SELECT m.id FROM ws_messages m
        INNER JOIN ws_messages p ON p.id = m.parent_id
        WHERE m.id > '" . $floor . "' AND m.parent_id > 0 AND m.deleted_at = 0
        AND p.sender_kind = 'user' AND p.sender_id = '" . $me . "'
        AND NOT (m.sender_kind = 'user' AND m.sender_id = '" . $me . "')
        ORDER BY m.id DESC LIMIT 20") as $row) {
        $found[(int) $row['id']] = array('kind' => 'reply', 'unread' => null);
    }

    // Mentions: the inbox keeps them per person, read or not. A reply that
    // also mentions the person stays a reply, with the inbox's read mark.
    foreach ((array) db_items("SELECT message_id, read_at FROM ws_inbox
        WHERE user_id = '" . $me . "' AND kind = 'mention' AND message_id > 0
        ORDER BY id DESC LIMIT 20") as $row) {
        $id = (int) $row['message_id'];
        $unread = ((int) $row['read_at'] === 0);

        if (isset($found[$id])) {
            $found[$id]['unread'] = $unread;
        } else {
            $found[$id] = array('kind' => 'mention', 'unread' => $unread);
        }
    }

    if (empty($found)) {
        return array();
    }

    krsort($found);

    $rows = array();

    foreach ((array) db_items("SELECT * FROM ws_messages
        WHERE id IN (" . implode(',', array_keys($found)) . ") AND deleted_at = 0
        ORDER BY id DESC") as $row) {
        $rows[] = $row;
    }

    $channels = array();
    $tokens = array();
    $kept = array();

    foreach ($rows as $row) {
        $channel_id = (int) $row['channel_id'];

        if (!array_key_exists($channel_id, $channels)) {
            $channel = ws_channel($channel_id);
            $channels[$channel_id] = ($channel && ws_can_read_channel($viewer, $channel)) ? $channel : null;
        }

        if ($channels[$channel_id] === null) {
            continue;
        }

        $kept[] = $row;
        $tokens = array_merge($tokens, ws_tokens($row['body']));

        if (count($kept) >= $limit) {
            break;
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    list($people, $mail) = ws_message_rows_senders($kept);
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php';
    $out = array();

    foreach ($kept as $row) {
        $id = (int) $row['id'];
        $channel = $channels[(int) $row['channel_id']];
        $unread = $found[$id]['unread'];

        // A reply is new when the person has not read that far in the channel.
        if ($unread === null) {
            $membership = ws_channel_membership($channel['id'], $me);
            $unread = $membership ? ($id > (int) $membership['last_read_id']) : false;
        }

        $sender = ws_message_row_sender($row, $people, $mail);

        $text = ws_plain_text($viewer, $row['body'], $refs);

        if (mb_strlen($text) > 160) {
            $text = rtrim(mb_substr($text, 0, 159)) . '…';
        }

        $out[] = array(
            'id'      => $id,
            'kind'    => $found[$id]['kind'],
            'unread'  => (bool) $unread,
            'sender'  => $sender,
            'text'    => $text,
            'time'    => ws_time_label($row['created_at']),
            'channel' => array(
                'id'      => (int) $channel['id'],
                'name'    => (string) $channel['name'],
                'private' => ($channel['kind'] === 'private'),
            ),
            'url'     => $base . '?channel=' . (int) $channel['id'] . '&message=' . $id,
        );
    }

    return $out;
}

/**
 * Public channels the person is not in yet, the busiest first.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_home_joinable($viewer, $limit = 5)
{
    $out = array();

    foreach ((array) db_items("SELECT c.id, c.name, c.topic,
            (SELECT COUNT(*) FROM ws_channel_members m2 WHERE m2.channel_id = c.id) AS member_count
        FROM ws_channels c
        LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . (int) $viewer['id'] . "'
        WHERE c.kind = 'public' AND c.archived_at = 0 AND m.user_id IS NULL
        ORDER BY c.last_message_at DESC
        LIMIT " . max(1, (int) $limit)) as $row) {

        $out[] = array(
            'id'      => (int) $row['id'],
            'name'    => (string) $row['name'],
            'topic'   => (string) $row['topic'],
            'members' => (int) $row['member_count'],
        );
    }

    return $out;
}

/**
 * The texts of the overview.
 *
 * @return array key => text
 */
function ws_home_js_strings()
{
    return array(
        // The page is called Today; its address stays ?view=home.
        'home'                => lang('Today'),
        'home_greeting'       => ws_js_template('{var:1}, {var:2}', 2),
        'home_waiting'        => lang('Waiting for you'),
        'home_waiting_empty'  => lang('Nothing waits for you. Approvals, read receipts, replies to your messages and mentions of you show here.'),
        'home_due'            => lang('Today and overdue'),
        'home_calendar'       => lang('Calendar · today'),
        'home_calendar_more'  => lang('Work Calendar'),
        'home_calendar_empty' => lang('Nothing is planned for you today.'),
        'home_all_day'        => lang('All day'),
        'home_now'            => lang('Now'),
        'home_digest'         => lang('What happened in the channels'),
        'home_digest_empty'   => lang('You have read everything in your channels.'),
        'home_digest_new'     => ws_js_template('{var:1} new', 1),
        'density'             => lang('Density'),
        'density_comfortable' => lang('Comfortable'),
        'density_compact'     => lang('Compact'),
        'home_tasks'          => lang('Coming up'),
        'home_tasks_count'    => ws_js_template('{var:1} open task(s)', 1),
        'home_tasks_overdue'  => ws_js_template('{var:1} overdue', 1),
        'home_tasks_empty'    => lang('No open task is yours. Tasks handed to you show here, the nearest due date first.'),
        'home_reply'          => lang('replied'),
        'home_mention'        => lang('mentioned you'),
        'home_decisions'      => lang('Latest decisions'),
        'home_decisions_empty' => lang('No decision yet. A message marked as a decision shows here.'),
        'home_join'           => lang('Channels you could join'),
        'home_members'        => ws_js_template('{var:1} member(s)', 1),
        'home_about'          => lang('What is the Workspace?'),
        'home_about_text'     => lang('The team\'s conversations, decisions and work in one place. Open a channel for a customer or a piece of work, turn what is agreed into tasks and mark the decisions, so nothing is lost in notebooks and other apps.'),
        'home_about_channels' => lang('A public channel is open to the whole team, a private one only to the people invited. Mention people with @ and tag orders, products or customers with #.'),
        'home_about_tasks'    => lang('Turn a message into a task with /gorev. The Planning Board shows who does what on which day and warns about clashes.'),
        'home_about_decisions' => lang('Mark a message as a decision; every decision of every channel you can read gathers in the Decision Timeline.'),
        'home_about_claude'   => lang('Write @Claude in a channel: it reads the conversation, answers, and proposes tasks and record changes that you approve.'),
        'home_new_channel'    => lang('New channel'),
        'home_new_private'    => lang('New private channel'),
        'nav_work'            => lang('Work and planning'),
        'nav_records'         => lang('Decisions and archive'),
        'nav_timeline'        => lang('Decision Timeline'),
        'nav_open'            => lang('Open the menu'),
    );
}
