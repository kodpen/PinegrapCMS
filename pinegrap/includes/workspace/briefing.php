<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the few lines a channel greets its reader with.
 *
 * Like the greeting on the dashboard, it is a read and not a readout: one or
 * two sentences somebody who had already looked at the channel would say
 * (what happened since you were last here, what is waiting, what was
 * decided), and one thing worth doing, with a way straight to it.
 *
 * The readings are all true at the moment they are chosen; which of the true
 * ones is said rotates, so the greeting does not read the same every time.
 * The advice does not rotate: it is the most useful thing to do, and picking
 * it at random would make it worth ignoring. Nothing is invented: a quiet
 * channel is called quiet.
 *
 * And the few facts the dashboard greeting weighs: a person's own tasks, what
 * was handed to them or asked of them, and what is on their calendar today.
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
 * The greeting for one reader entering one channel.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array|null $membership the reader's membership as it was before this
 *                               visit marked anything read
 * @return array icon, greeting, lines (sentences), advice (label, icon, action, target)
 */
function ws_channel_briefing($viewer, $channel, $membership)
{
    $channel_id = (int) $channel['id'];
    $me = (int) $viewer['id'];
    $now = time();
    $today = date('Y-m-d');
    $name = '#' . $channel['name'];

    // ── the part of the day ──

    $hour = (int) date('H');
    $part = ($hour < 5) ? 'late' : (($hour < 12) ? 'morning' : (($hour < 18) ? 'afternoon' : (($hour < 23) ? 'evening' : 'late')));

    $icons = array('late' => 'bi-moon-stars', 'morning' => 'bi-sunrise', 'afternoon' => 'bi-sun', 'evening' => 'bi-brightness-alt-high');
    $plain = array('late' => lang('Good night'), 'morning' => lang('Good morning'), 'afternoon' => lang('Good afternoon'), 'evening' => lang('Good evening'));
    $playful = array(
        'late'      => array(lang('Night knight'), lang('Still up'), lang('The quiet shift')),
        'morning'   => array(lang('First coffee'), lang('Bright and early'), lang('A fresh page')),
        'afternoon' => array(lang('Peak hours'), lang('Halfway there'), lang('Full swing')),
        'evening'   => array(lang('Golden hour'), lang('Winding down'), lang('The long light')),
    );

    $label = (mt_rand(1, 4) === 1) ? $playful[$part][array_rand($playful[$part])] : $plain[$part];
    $me_name = ws_person_name($me);
    $greeting = $label . ', ' . $me_name . '.';

    // ── signals ──

    $last_read = $membership ? (int) $membership['last_read_id'] : 0;

    // A first visit: not in the channel yet, or in it for a few days without
    // having written a word.
    $first_visit = !$membership
        || (((int) $membership['joined_at'] > $now - 7 * 86400)
            && ((int) db_value("SELECT COUNT(*) FROM ws_messages WHERE channel_id = '" . $channel_id . "' AND sender_kind = 'user' AND sender_id = '" . $me . "'") === 0));

    $new_rows = $membership ? (array) db_items("SELECT sender_id, sender_kind FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND id > '" . $last_read . "' AND deleted_at = 0
        AND kind <> 'system' AND NOT (sender_kind = 'user' AND sender_id = '" . $me . "')
        LIMIT 500") : array();

    $new_count = count($new_rows);
    $new_by = array();

    foreach ($new_rows as $row) {
        if ($row['sender_kind'] === 'user') {
            $new_by[(int) $row['sender_id']] = ($new_by[(int) $row['sender_id']] ?? 0) + 1;
        }
    }

    arsort($new_by);
    $loudest = empty($new_by) ? '' : ws_person_name(key($new_by));

    $first_unread = $membership ? (int) db_value("SELECT MIN(id) FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND id > '" . $last_read . "' AND deleted_at = 0 AND kind <> 'system'") : 0;

    $mention_ids = array_map('intval', (array) db_values("SELECT message_id FROM ws_inbox
        WHERE user_id = '" . $me . "' AND channel_id = '" . $channel_id . "' AND kind = 'mention' AND read_at = 0
        ORDER BY id"));

    $last_message = db_item("SELECT sender_id, sender_kind, created_at FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND deleted_at = 0 AND kind <> 'system'
        ORDER BY id DESC LIMIT 1");

    $open = (array) db_items("SELECT t.id, t.due_date, t.status FROM ws_tasks t
        WHERE t.channel_id = '" . $channel_id . "' AND t.status IN ('todo', 'doing', 'waiting')");

    $open_ids = array();

    foreach ($open as $task) {
        $open_ids[] = (int) $task['id'];
    }

    $people = ws_task_assignees_map($open_ids);
    $overdue = 0;
    $due_today = 0;
    $mine = 0;
    $waiting = 0;

    foreach ($open as $task) {
        $due = (string) ($task['due_date'] ?? '');

        if (($due !== '') && ($due < $today)) {
            $overdue++;
        } elseif ($due === $today) {
            $due_today++;
        }

        if ($task['status'] === 'waiting') {
            $waiting++;
        }

        if (in_array($me, $people[(int) $task['id']] ?? array(), true)) {
            $mine++;
        }
    }

    $done_week = (int) db_value("SELECT COUNT(*) FROM ws_tasks
        WHERE channel_id = '" . $channel_id . "' AND status = 'done' AND completed_at >= '" . ($now - 7 * 86400) . "'");

    $decision = db_item("SELECT id, body, marked_at FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND kind = 'decision' AND deleted_at = 0
        ORDER BY marked_at DESC, id DESC LIMIT 1");

    $decisions_week = (int) db_value("SELECT COUNT(*) FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND kind = 'decision' AND deleted_at = 0 AND marked_at >= '" . ($now - 7 * 86400) . "'");

    $newcomer = db_item("SELECT user_id, joined_at FROM ws_channel_members
        WHERE channel_id = '" . $channel_id . "' AND user_id <> '" . $me . "' AND joined_at >= '" . ($now - 3 * 86400) . "'
        ORDER BY joined_at DESC LIMIT 1");

    $members = (int) db_value("SELECT COUNT(*) FROM ws_channel_members WHERE channel_id = '" . $channel_id . "'");

    // ── the readings that are true now ──

    $readings = array();

    if ($first_visit) {
        $readings[] = ((string) $channel['topic'] !== '')
            ? lang(array('string' => 'Welcome to {var:1}: {var:2}. {var:3} people talk here.', 'vars' => array($name, $channel['topic'], $members)))
            : lang(array('string' => 'Welcome to {var:1}. {var:2} people talk here; the summary tab says what the channel is for.', 'vars' => array($name, $members)));
    }

    if (!empty($mention_ids)) {
        $readings[] = (count($mention_ids) === 1)
            ? lang('Somebody mentioned you here since you last looked.')
            : lang(array('string' => 'You were mentioned {var:1} times here since you last looked.', 'vars' => count($mention_ids)));
    }

    if (!$first_visit && ($new_count > 0)) {
        $readings[] = ($loudest !== '')
            ? lang(array('string' => '{var:1} new messages since your last visit, most of them from {var:2}.', 'vars' => array($new_count, $loudest)))
            : lang(array('string' => '{var:1} new messages since your last visit.', 'vars' => $new_count));
    }

    if (!$first_visit && ($new_count === 0) && is_array($last_message) && ((int) $last_message['created_at'] < $now - 2 * 86400)) {
        $readings[] = lang(array('string' => 'It has been quiet here since {var:1}.', 'vars' => ws_time_label($last_message['created_at'])));
    }

    if ($overdue > 0) {
        $readings[] = lang(array('string' => '{var:1} of the {var:2} open tasks here are past their due date.', 'vars' => array($overdue, count($open))));
    } elseif ($due_today > 0) {
        $readings[] = lang(array('string' => '{var:1} tasks in this channel are due today.', 'vars' => $due_today));
    } elseif (count($open) > 0) {
        $readings[] = lang(array('string' => '{var:1} tasks are open in this channel, none of them late.', 'vars' => count($open)));
    }

    if ($mine > 0) {
        $readings[] = lang(array('string' => '{var:1} of the open tasks here are yours.', 'vars' => $mine));
    }

    if ($waiting > 0) {
        $readings[] = lang(array('string' => '{var:1} tasks here are waiting on something.', 'vars' => $waiting));
    }

    if ($done_week > 0) {
        $readings[] = lang(array('string' => '{var:1} tasks were finished here in the last seven days.', 'vars' => $done_week));
    }

    if (is_array($decision) && ($decisions_week > 0)) {
        $excerpt = ws_plain_excerpt($viewer, $decision['body'], 90);
        $readings[] = ($decisions_week === 1)
            ? lang(array('string' => 'A decision was written down this week: “{var:1}”', 'vars' => $excerpt))
            : lang(array('string' => '{var:1} decisions were written down this week; the latest: “{var:2}”', 'vars' => array($decisions_week, $excerpt)));
    }

    if (is_array($newcomer)) {
        $readings[] = lang(array('string' => '{var:1} joined the channel {var:2}.', 'vars' => array(ws_person_name($newcomer['user_id']), ws_time_label($newcomer['joined_at']))));
    }

    if (empty($readings) || ((count($open) === 0) && ($new_count === 0) && empty($mention_ids) && !$first_visit)) {
        $readings[] = lang('Nothing is waiting for you here: nothing unread, no open tasks.');
    }

    // Two readings at most, the first one always the most pressing when there
    // is one (a mention, then something late), the other drawn at random.
    $lines = array();
    $pressing = array();

    if ($first_visit) {
        $pressing[] = array_shift($readings);
    } elseif (!empty($mention_ids)) {
        $pressing[] = array_shift($readings);
    }

    shuffle($readings);
    $lines = array_slice(array_merge($pressing, $readings), 0, 2);

    // ── the one thing worth doing ──

    $advice = null;

    if (!empty($mention_ids)) {
        $advice = array('label' => lang('Go to where you were mentioned'), 'icon' => 'bi-at', 'action' => 'message', 'target' => $mention_ids[0]);
    } elseif ($overdue > 0) {
        $advice = array('label' => lang('Look at the late tasks'), 'icon' => 'bi-alarm', 'action' => 'tab', 'target' => 'tasks');
    } elseif (!$first_visit && ($new_count >= 5) && ($first_unread > 0)) {
        $advice = array('label' => lang('Read from where you left off'), 'icon' => 'bi-arrow-down-circle', 'action' => 'message', 'target' => $first_unread);
    } elseif (trim((string) $channel['summary']) === '') {
        $advice = array('label' => lang('Write down what the channel is for'), 'icon' => 'bi-journal-text', 'action' => 'tab', 'target' => 'summary');
    } elseif ($first_visit) {
        $advice = array('label' => lang('Read the summary'), 'icon' => 'bi-journal-text', 'action' => 'tab', 'target' => 'summary');
    } elseif ((count($open) === 0) && ($new_count > 0)) {
        $advice = array('label' => lang('Anything to do? Right-click a message and make a task of it.'), 'icon' => 'bi-check2-square', 'action' => 'none', 'target' => '');
    }

    return array(
        'icon'     => $icons[$part],
        'greeting' => $greeting,
        'lines'    => array_values($lines),
        'advice'   => $advice,
        'key'      => $channel_id . ':' . $today,
    );
}

/**
 * What the dashboard greeting knows about one person's workspace.
 *
 * Counts and one or two rows, nothing rendered: the dashboard decides what is
 * worth a sentence. Every key is there; a count is 0 and a row is null when
 * there is nothing to say about it.
 *
 * @param array $viewer ws_rights() of the person
 * @return array open, overdue, due_today, waiting, doing (id, title, progress),
 *               done_week, team_done_week, new_task (id, title, by, due_label),
 *               new_tasks, mentions, mention_url, inbox, events_today,
 *               event_first (kind, title, starts_at, time, all_day)
 */
function ws_dashboard_signals($viewer)
{
    $me = (int) $viewer['id'];
    $now = time();
    $today = date('Y-m-d');
    $week_ago = $now - 7 * 86400;

    $out = array(
        'open'           => 0,
        'overdue'        => 0,
        'due_today'      => 0,
        'waiting'        => 0,
        'doing'          => null,
        'done_week'      => 0,
        'team_done_week' => 0,
        'new_task'       => null,
        'new_tasks'      => 0,
        'mentions'       => 0,
        'mention_url'    => null,
        'inbox'          => 0,
        'events_today'   => 0,
        'event_first'    => null,
    );

    // The open list in one pass, the same shape ws_home() reads. A due date of
    // NULL or 0000-00-00 is no due date and is neither late nor due today.
    $counts = db_item("SELECT COUNT(*) AS open_count,
            SUM(CASE WHEN t.due_date > '0000-00-00' AND t.due_date < '" . e($today) . "' THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN t.due_date = '" . e($today) . "' THEN 1 ELSE 0 END) AS today_count,
            SUM(CASE WHEN t.status = 'waiting' THEN 1 ELSE 0 END) AS waiting_count
        FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
        WHERE t.status IN ('todo', 'doing', 'waiting')");

    $out['open'] = (int) ($counts['open_count'] ?? 0);
    $out['overdue'] = (int) ($counts['overdue_count'] ?? 0);
    $out['due_today'] = (int) ($counts['today_count'] ?? 0);
    $out['waiting'] = (int) ($counts['waiting_count'] ?? 0);

    // Finished work is a different set of rows - done, by whoever ticked it
    // off, assigned or not - so it is a second pass rather than a branch of
    // the join above. The team's figure rides along and is kept only for
    // whoever holds the board, which is where everybody's work is visible.
    $done = db_item("SELECT COUNT(*) AS team_count,
            SUM(CASE WHEN completed_by = '" . $me . "' THEN 1 ELSE 0 END) AS mine_count
        FROM ws_tasks
        WHERE status = 'done' AND completed_at >= '" . (int) $week_ago . "'");

    $out['done_week'] = (int) ($done['mine_count'] ?? 0);

    if (!empty($viewer['board'])) {
        $out['team_done_week'] = (int) ($done['team_count'] ?? 0);
    }

    // The task being worked on. One with a checklist is preferred because it
    // has a progress to show; the checklist columns come from a later schema
    // step than the tables themselves, so they are asked about first.
    $doing = null;

    if (ws_task_work_ready()) {
        $doing = db_item("SELECT t.id, t.title, t.items_total, t.items_done FROM ws_tasks t
            INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
            WHERE t.status = 'doing' AND t.items_total > 0
            ORDER BY t.updated_at DESC LIMIT 1");
    }

    if (!is_array($doing)) {
        $doing = db_item("SELECT t.id, t.title FROM ws_tasks t
            INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
            WHERE t.status = 'doing'
            ORDER BY t.updated_at DESC LIMIT 1");
    }

    if (is_array($doing)) {
        $out['doing'] = array(
            'id'       => (int) $doing['id'],
            'title'    => (string) $doing['title'],
            'progress' => ws_task_progress($doing),
        );
    }

    // The newest task handed over and not looked at yet. Checked against the
    // same rule as the task screen, and dropped once it is no longer open: a
    // task finished before its assignee saw the notice is not news to them.
    $out['new_tasks'] = (int) db_value("SELECT COUNT(*) FROM ws_inbox
        WHERE user_id = '" . $me . "' AND kind = 'assigned' AND read_at = 0");

    if ($out['new_tasks'] > 0) {
        $assigned = db_item("SELECT task_id, actor_id FROM ws_inbox
            WHERE user_id = '" . $me . "' AND kind = 'assigned' AND read_at = 0
            ORDER BY id DESC LIMIT 1");

        $task = is_array($assigned) ? ws_task((int) $assigned['task_id']) : null;

        if ($task && ws_task_is_open($task['status']) && ws_can_see_task($viewer, $task)) {
            $due = (string) ($task['due_date'] ?? '');

            $out['new_task'] = array(
                'id'        => (int) $task['id'],
                'title'     => (string) $task['title'],
                'by'        => ws_person_name((int) $assigned['actor_id']),
                // Null rather than "No due date", so a sentence can leave the
                // date out instead of saying "due no due date".
                'due_label' => (($due !== '') && ($due !== '0000-00-00')) ? ws_task_due_label($task) : null,
            );
        }
    }

    // Mentions not answered yet. The address of the newest one is offered only
    // while its channel can still be read; the count stays, as the menu badge
    // counts it.
    $out['mentions'] = (int) db_value("SELECT COUNT(*) FROM ws_inbox
        WHERE user_id = '" . $me . "' AND kind = 'mention' AND read_at = 0");

    if ($out['mentions'] > 0) {
        $mention = db_item("SELECT channel_id, message_id FROM ws_inbox
            WHERE user_id = '" . $me . "' AND kind = 'mention' AND read_at = 0
            ORDER BY id DESC LIMIT 1");

        if (is_array($mention) && ((int) $mention['channel_id'] > 0)
            && ws_can_read_channel($viewer, ws_channel($mention['channel_id']))
        ) {
            $out['mention_url'] = 'workspace.php?channel=' . (int) $mention['channel_id']
                . (((int) $mention['message_id'] > 0) ? '&message=' . (int) $mention['message_id'] : '');
        }
    }

    $out['inbox'] = ws_inbox_unread_count($me);

    // Meetings and visits on today's calendar that are about this person and
    // have not finished yet: one that ended at ten is not worth mentioning at
    // three. The first of them is the one a sentence names.
    $departments = ws_user_department_ids($me);

    foreach (ws_events_between($today, $today) as $event) {
        if (!in_array($event['kind'], array('meeting', 'visit'), true)
            || ((int) $event['ends_at'] < $now)
            || !ws_event_applies($event, $me, $departments)
        ) {
            continue;
        }

        $out['events_today']++;

        if ($out['event_first'] === null) {
            // A visit that began on an earlier day has no start time today.
            $all_day = ((int) $event['all_day'] === 1) || ((int) $event['starts_at'] < strtotime($today));

            $out['event_first'] = array(
                'kind'      => (string) $event['kind'],
                'title'     => (string) $event['title'],
                'starts_at' => (int) $event['starts_at'],
                'time'      => $all_day ? null : date('H:i', (int) $event['starts_at']),
                'all_day'   => $all_day,
            );
        }
    }

    return $out;
}
