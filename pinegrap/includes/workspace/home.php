<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the overview: the page a person lands on when they have not
 * pinned a channel, and whenever they come back to it from the sidebar. In a
 * few lines it tells them what is coming (their open tasks by due date),
 * what was written to them (replies to their messages and mentions, with the
 * channel), the latest decisions, the channels they could join, and what
 * this place is for.
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
            SUM(t.due_date > '0000-00-00' AND t.due_date < '" . e($today) . "') AS overdue_count
        FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
        WHERE t.status IN ('todo', 'doing', 'waiting')");

    $timeline = ws_timeline($viewer, ws_timeline_filters(array()), null, 5);

    // Somebody who has never written anything is shown what this place is
    // for; the others see it folded.
    $written = (int) db_value("SELECT id FROM ws_messages WHERE sender_kind = 'user' AND sender_id = '" . $me . "' LIMIT 1");

    return array(
        'name'          => (string) ($person[$me]['name'] ?? ''),
        'date'          => ws_home_date_label($today),
        'tasks'         => ws_tasks_list($viewer, array('scope' => 'mine', 'status' => 'open', 'limit' => 5)),
        'tasks_open'    => (int) ($counts['open_count'] ?? 0),
        'tasks_overdue' => (int) ($counts['overdue_count'] ?? 0),
        'talk'          => ws_home_talk($viewer, 5),
        'decisions'     => ws_timeline_items($viewer, $timeline['rows'], $timeline['channels']),
        'join'          => ws_home_joinable($viewer, 5),
        'newcomer'      => ($written === 0),
        'claude'        => function_exists('ws_claude_ready') && ws_claude_ready(),
    );
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
    $people_ids = array();
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

        if ($row['sender_kind'] === 'user') {
            $people_ids[] = (int) $row['sender_id'];
        }

        if (count($kept) >= $limit) {
            break;
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people(array_unique($people_ids));
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

        $sender = null;

        if ($row['sender_kind'] === 'user') {
            $sender = $people[(int) $row['sender_id']] ?? null;
        } elseif ($row['sender_kind'] === 'app') {
            $sender = ws_app_sender((int) $row['sender_id']);
        }

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
        'home'                => lang('Overview'),
        'home_hello'          => ws_js_template('Hello, {var:1}', 1),
        'home_tasks'          => lang('Coming up'),
        'home_tasks_count'    => ws_js_template('{var:1} open task(s)', 1),
        'home_tasks_overdue'  => ws_js_template('{var:1} overdue', 1),
        'home_tasks_empty'    => lang('No open task is yours. Tasks handed to you show here, the nearest due date first.'),
        'home_talk'           => lang('Written to you'),
        'home_talk_empty'     => lang('Nothing new. Replies to your messages and mentions of you show here.'),
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
        'home_start'          => lang('Start a conversation'),
        'home_new_channel'    => lang('New channel'),
        'home_new_private'    => lang('New private channel'),
        'nav_work'            => lang('Work and planning'),
        'nav_records'         => lang('Decisions and archive'),
        'nav_timeline'        => lang('Decision Timeline'),
        'nav_open'            => lang('Open the menu'),
    );
}
