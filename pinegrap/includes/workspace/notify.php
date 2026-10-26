<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what a person is told: a mention, a task handed to them, a task
 * of theirs finished, a note written on it, its reminder or its passing its
 * due time, an invitation to a channel.
 *
 * Kept in the module's own inbox (ws_inbox), one row per person, rather than
 * in the panel's notification table: that table holds things everyone of a
 * role may see and decides per reader, while "Ayşe mentioned you" belongs to
 * one person only. The inbox is the badge on the menu item, the list on the
 * workspace screen, and - through the panel's push queue - the banner on a
 * phone.
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
 * Tells one person about one thing.
 *
 * A mention waits the chat's delay before it wakes a device: somebody reading
 * the channel as the message arrives reads the mention too, the inbox row is
 * marked read by that, and the queued banner is dropped unsent.
 *
 * @param int    $user_id
 * @param string $kind     mention | assigned | completed | invited | note | note_shared | note_updated | note_answer
 *                         | unblocked (a task waited for is done: task_links.php)
 *                         | agent_answer (an MCP agent answered what one asked it: mcp_agent.php)
 *                         | approval_requested | approval_decided | approval_reminder | ack_reminder
 *                         | approval_answered (somebody outside the team answered: approval_guests.php)
 *                         | task_note (a note on a task one is on: task_work.php)
 *                         | task_reminder | task_overdue | meeting_soon (reminders.php)
 *                         | flow_finished (a run of one's flow ended: flow_runs.php)
 *                         | flow_shared (a flow was shared with one: flow_shares.php)
 * @param array  $data     channel_id, message_id, task_id, note_id, event_id, actor_id; quiet: no device banner;
 *                         batch: the device is queued but not woken here - the caller
 *                         tells many people in a row and calls ws_push_batch_done();
 *                         separate: always a row of its own, never folded into an
 *                         unread row of the same kind and ids (a run of a flow that
 *                         ended, a flow shared, whose ids are all 0: flow_runs.php,
 *                         flow_shares.php)
 * @return int the inbox row
 */
function ws_notify($user_id, $kind, $data)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return 0;
    }

    $channel_id = (int) ($data['channel_id'] ?? 0);
    $message_id = (int) ($data['message_id'] ?? 0);
    $task_id = (int) ($data['task_id'] ?? 0);

    // A note handed over is named by the copy it became (4.110).
    $note_id = (function_exists('ws_notes_inbox_ready') && ws_notes_inbox_ready()) ? (int) ($data['note_id'] ?? 0) : 0;

    // A plan item, by its own id (2026.4.10, 10.86).
    $event_id = (function_exists('ws_meetings_ready') && ws_meetings_ready()) ? (int) ($data['event_id'] ?? 0) : 0;

    // The same thing twice is one row: an edit that mentions somebody again,
    // a task given to somebody who already had it.
    $existing = empty($data['separate']) ? (int) db_value("SELECT id FROM ws_inbox
        WHERE user_id = '" . $user_id . "' AND kind = '" . e($kind) . "' AND read_at = 0
        AND channel_id = '" . $channel_id . "' AND message_id = '" . $message_id . "' AND task_id = '" . $task_id . "'"
        . (($note_id > 0) ? " AND note_id = '" . $note_id . "'" : '')
        . (($event_id > 0) ? " AND event_id = '" . $event_id . "'" : '') . "
        LIMIT 1") : 0;

    if ($existing > 0) {
        return $existing;
    }

    db("INSERT INTO ws_inbox (user_id, kind, channel_id, message_id, task_id, " . (($note_id > 0) ? 'note_id, ' : '') . (($event_id > 0) ? 'event_id, ' : '') . "actor_id, created_at)
        VALUES ('" . $user_id . "', '" . e($kind) . "', '" . $channel_id . "', '" . $message_id . "', '" . $task_id . "',
            " . (($note_id > 0) ? "'" . $note_id . "', " : '') . (($event_id > 0) ? "'" . $event_id . "', " : '') . "'" . (int) ($data['actor_id'] ?? 0) . "', '" . time() . "')");

    $inbox_id = (int) mysqli_insert_id(db::$con);

    // The same thing in the person's bell, addressed to them alone (4.83).
    if ($inbox_id > 0) {
        ws_bell_add($user_id, $inbox_id);
    }

    if (($inbox_id > 0) && empty($data['quiet'])) {
        include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

        if (function_exists('pg_push_enqueue')) {
            // Somebody with the workspace open reads a mention on the screen,
            // so their device waits a minute and is not woken when they have.
            // Somebody who is not looking is woken now: the screen polls every
            // few seconds while it is open (ws_touch_seen()), so a person not
            // seen for WS_PUSH_SEEN seconds has closed it or looked away, and
            // the queue is worked right here instead of waiting for the
            // scheduled job, which not every site runs every minute.
            $looking = ws_seen_recently($user_id);
            $delay = (in_array($kind, array('mention', 'guest_message', 'agent_answer'), true) && $looking) ? pg_push_chat_delay() : 0;

            pg_push_enqueue($user_id, 'ws', $inbox_id, $delay);

            if (($delay === 0) && empty($data['batch']) && function_exists('pg_push_queue_run')) {
                pg_push_queue_run(5);
            }
        }
    }

    return $inbox_id;
}

/**
 * Wakes the devices a batch of ws_notify(..., batch) calls queued: the queue
 * is worked once for all of them rather than once per person told.
 *
 * @param bool $queued something was queued
 */
function ws_push_batch_done($queued)
{
    if (!$queued) {
        return;
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    if (function_exists('pg_push_queue_run')) {
        pg_push_queue_run(10);
    }
}

/**
 * Seconds without a look at the workspace after which a person counts as not
 * looking: the screen asks for news every 2 to 4 seconds while it is open and
 * in front, and stops when it is hidden or closed.
 */
if (!defined('WS_PUSH_SEEN')) {
    define('WS_PUSH_SEEN', 15);
}

/**
 * The workspace screen was just in front of this person. Kept in the panel's
 * own presence column, written at most every few seconds.
 *
 * @param int $user_id
 */
function ws_touch_seen($user_id)
{
    db("UPDATE user SET user_online_timestamp = UNIX_TIMESTAMP()
        WHERE user_id = '" . (int) $user_id . "' AND (UNIX_TIMESTAMP() - user_online_timestamp) > 5");
}

/**
 * A look from a workspace screen in front of the person - the panel's, or an
 * app's on their own device: they count as looking (ws_notify() holds a
 * mention's banner back while they are), and banners whose wait is over go
 * out on the way.
 *
 * @param int $user_id
 */
function ws_sync_seen($user_id)
{
    ws_touch_seen($user_id);
    ws_push_due_run();
}

/**
 * Has this person had the workspace in front of them in the last moments?
 *
 * @param int $user_id
 * @return bool
 */
function ws_seen_recently($user_id)
{
    $seen = (int) db_value("SELECT user_online_timestamp FROM user WHERE user_id = '" . (int) $user_id . "'");

    return ($seen > 0) && ((time() - $seen) <= WS_PUSH_SEEN);
}

/**
 * Wakes the devices whose wait is over, from a screen that is asking for
 * news anyway: a site whose scheduled job runs rarely (or not at all, as on
 * many development machines) still gets its banners. One indexed read when
 * nothing is due.
 */
function ws_push_due_run()
{
    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    if (!function_exists('pg_push_queue_available') || !pg_push_queue_available()) {
        return;
    }

    if ((int) db_value("SELECT COUNT(*) FROM push_queue WHERE send_after <= UNIX_TIMESTAMP()") > 0) {
        pg_push_queue_run(3);
    }
}

/**
 * How many things wait for a person, for the menu badge.
 *
 * @param int $user_id
 * @return int
 */
function ws_inbox_unread_count($user_id)
{
    static $cache = array();

    $user_id = (int) $user_id;

    if (!isset($cache[$user_id])) {
        $cache[$user_id] = ws_ready()
            ? (int) db_value("SELECT COUNT(*) FROM ws_inbox WHERE user_id = '" . $user_id . "' AND read_at = 0")
            : 0;
    }

    return $cache[$user_id];
}

/**
 * One inbox row as a sentence, a place to go and a picture.
 *
 * @param array $viewer
 * @param array $row
 * @return array title, body, url, icon
 */
function ws_inbox_describe($viewer, $row)
{
    $actor = ws_person_name($row['actor_id']);
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $channel = ((int) $row['channel_id'] > 0) ? ws_channel($row['channel_id']) : null;
    $task = ((int) $row['task_id'] > 0) ? ws_task($row['task_id']) : null;
    $channel_name = $channel ? '#' . $channel['name'] : '';
    $task_title = $task ? ws_task_number($task['id']) . ' · ' . $task['title'] : '';

    switch ($row['kind']) {

        case 'mention':
            $message = ((int) $row['message_id'] > 0) ? ws_message($row['message_id']) : null;

            // Written by an application (Claude): its name, not a person's.
            // A proposal an OAuth connection made (proposals.php) is named by
            // the client, as the channel screen draws its message: every
            // client writes through the one OAuth application.
            if ($message && ($message['sender_kind'] === 'app')) {
                $sender = ws_app_sender((int) $message['sender_id']);

                if (function_exists('ws_proposals_by_reply') && function_exists('ws_proposal_sender')) {
                    $agent = ws_proposals_by_reply(array((int) $message['id']))[(int) $message['id']] ?? '';

                    if ($agent !== '') {
                        $sender = ws_proposal_sender($sender, $agent);
                    }
                }

                $actor = (string) ($sender['name'] ?? $actor);
            }

            return array(
                'title' => lang(array('string' => '{var:1} mentioned you in {var:2}', 'vars' => array($actor, $channel_name))),
                'body'  => $message ? ws_plain_excerpt($viewer, $message['body'], 140) : '',
                'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
                'icon'  => 'bi-at',
            );

        case 'assigned':
            return array(
                'title' => lang(array('string' => '{var:1} gave you a task', 'vars' => $actor)),
                'body'  => $task_title . ($task ? ' — ' . ws_task_due_label($task) : ''),
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-check2-square',
            );

        case 'completed':
            return array(
                'title' => lang(array('string' => '{var:1} finished a task you created', 'vars' => $actor)),
                'body'  => $task_title,
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-check2-circle',
            );

        case 'note':
            return array(
                'title' => lang(array('string' => '{var:1} mentioned you in a note on {var:2}', 'vars' => array($actor, $task ? ws_task_number($task['id']) : ''))),
                'body'  => $task ? (string) $task['title'] : '',
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-journal-text',
            );

        // The last task in front of this one is done (task_links.php).
        case 'unblocked':
            $done_id = ($task && function_exists('ws_task_links_last_done')) ? ws_task_links_last_done($task['id']) : 0;

            return array(
                'title' => lang(array('string' => '{var:1} is done; {var:2} can start', 'vars' => array(($done_id > 0) ? ws_task_number($done_id) : lang('A task'), $task ? ws_task_number($task['id']) : ''))),
                'body'  => $task ? (string) $task['title'] : '',
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-unlock',
            );

        case 'invited':
            return array(
                'title' => lang(array('string' => '{var:1} added you to {var:2}', 'vars' => array($actor, $channel_name))),
                'body'  => $channel ? (string) $channel['topic'] : '',
                'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'],
                'icon'  => 'bi-hash',
            );

        case 'note_shared':
            $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

            return array(
                'title' => lang(array('string' => '{var:1} shared a note with you', 'vars' => $actor)),
                'body'  => $note ? ws_note_title($viewer, $note) : '',
                'url'   => $base . 'workspace_notes.php' . ($note ? '?note=' . (int) $note['id'] : ''),
                'icon'  => 'bi-journal-arrow-down',
            );

        case 'note_updated':
            $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

            return array(
                'title' => lang(array('string' => '{var:1} changed a note', 'vars' => $actor)),
                'body'  => $note ? ws_note_title($viewer, $note) : '',
                'url'   => $base . 'workspace_notes.php' . ($note ? '?note=' . (int) $note['id'] : ''),
                'icon'  => 'bi-journal-text',
            );

        // A scheduled action letting people know (scheduled.php): its words sit
        // beside the inbox row.
        case 'scheduled_notice':
            $notice = function_exists('ws_scheduled_notice') ? ws_scheduled_notice((int) ($row['id'] ?? 0)) : null;
            $readable = $notice && ((int) $notice['channel_id'] > 0) && ((int) $notice['message_id'] > 0) && ws_can_read_channel($viewer, ws_channel((int) $notice['channel_id']));

            return array(
                // A flow's box writes a notice of no action (its text names the
                // flow).
                'title' => $notice ? (($notice['name'] !== null) ? (string) $notice['name'] : lang('Flow')) : lang('Scheduled action'),
                'body'  => $notice ? (string) $notice['text'] : '',
                'url'   => $base . ($readable ? 'workspace.php?channel=' . (int) $notice['channel_id'] . '&message=' . (int) $notice['message_id'] : 'workspace.php'),
                'icon'  => 'bi-alarm',
            );

        // A run of one of the person's flows ended (flow_runs.php): the
        // sentence that names the flow and the run sits beside the inbox row,
        // as a scheduled action's notice does.
        case 'flow_finished':
            $notice = function_exists('ws_scheduled_notice') ? ws_scheduled_notice((int) ($row['id'] ?? 0)) : null;

            return array(
                'title' => lang('A run of your flow ended'),
                'body'  => $notice ? (string) $notice['text'] : '',
                'url'   => $base . 'workspace_flows.php',
                'icon'  => 'bi-diagram-3',
            );

        // A flow was shared with the person (flow_shares.php): the sentence
        // that names the flow and what they may do with it sits beside the
        // inbox row; the flows screen lists it under the heading
        // "Shared with me and my channels".
        case 'flow_shared':
            $notice = function_exists('ws_scheduled_notice') ? ws_scheduled_notice((int) ($row['id'] ?? 0)) : null;

            return array(
                'title' => lang(array('string' => '{var:1} shared a flow with you', 'vars' => $actor)),
                'body'  => $notice ? (string) $notice['text'] : '',
                'url'   => $base . 'workspace_flows.php',
                'icon'  => 'bi-diagram-3',
            );

        // Without a card in a channel, the list of scheduled actions: in
        // Flows when it lists them (ws_flows_scheduled()).
        case 'scheduled_failed':
            $list = (function_exists('ws_flows_scheduled') && ws_flows_scheduled($viewer)) ? 'workspace_flows.php?view=scheduled' : 'workspace.php?view=scheduled';

            return array(
                'title' => lang('A scheduled action you wrote failed'),
                'body'  => $channel_name,
                'url'   => $base . (((int) $row['message_id'] > 0) ? 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'] : $list),
                'icon'  => 'bi-alarm',
            );

        // A discussion was started about one's message (threads.php).
        case 'thread':
            $thread = function_exists('ws_thread') ? ws_thread((int) $row['channel_id']) : null;

            return array(
                'title' => lang(array('string' => '{var:1} started a discussion about your message', 'vars' => $actor)),
                'body'  => $channel ? (string) $channel['name'] : '',
                'url'   => $base . 'workspace.php?channel=' . ($thread ? (int) $thread['parent_channel_id'] . '&thread=' . (int) $row['channel_id'] : (int) $row['channel_id']),
                'icon'  => 'bi-chat-square-dots',
            );

        // A message scheduled from the writing box that could not be posted
        // (scheduled_messages.php): it waits on the scheduled screen, on the
        // Messages tab for staff, whose list there may open on their actions.
        case 'scheduled_message':
            return array(
                'title' => lang('A message you scheduled could not be posted'),
                'body'  => $channel_name,
                'url'   => $base . 'workspace.php?view=scheduled&part=messages',
                'icon'  => 'bi-send-exclamation',
            );

        case 'note_answer':
            $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

            return array(
                'title' => lang('Claude answered in your note'),
                'body'  => $note ? ws_note_title($viewer, $note) : '',
                'url'   => $base . 'workspace_notes.php' . ($note ? '?note=' . (int) $note['id'] : ''),
                'icon'  => 'bi-stars',
            );

        // The guest of a room wrote (guests.php): one line until the room is
        // read, with the last of what they wrote.
        case 'guest_message':
            $last = (int) db_value("SELECT id FROM ws_messages
                WHERE channel_id = '" . (int) $row['channel_id'] . "' AND sender_kind = 'guest' AND deleted_at = 0
                ORDER BY id DESC LIMIT 1");
            $message = ($last > 0) ? ws_message($last) : null;

            // The guest who wrote it: a shared channel may have more than one.
            $guest = ($message && function_exists('ws_guest')) ? ws_guest((int) $message['sender_id']) : null;

            return array(
                'title' => lang(array('string' => '{var:1} wrote in {var:2}', 'vars' => array($guest ? (string) $guest['name'] : lang('Guest'), $channel_name))),
                'body'  => $message ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $message['body'])), 0, 140) : '',
                'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . (($last > 0) ? '&message=' . $last : ''),
                'icon'  => 'bi-door-open',
            );

        // Approval requests (approvals.php): asked, settled, a day before
        // the deadline, answered from outside the team.
        case 'approval_requested':
        case 'approval_decided':
        case 'approval_reminder':
        case 'approval_answered':
            if (function_exists('ws_approval_inbox_describe')) {
                return ws_approval_inbox_describe($viewer, $row, $actor, $base);
            }
            break;

        // An MCP agent answered what the person asked it (mcp_agent.php).
        case 'agent_answer':
            if (function_exists('ws_mcp_agent_inbox_describe')) {
                return ws_mcp_agent_inbox_describe($viewer, $row, $channel_name, $base);
            }
            break;

        // A message a day old whose read receipt the person has not given
        // (acks.php).
        case 'ack_reminder':
            $message = ((int) $row['message_id'] > 0) ? ws_message($row['message_id']) : null;

            return array(
                'title' => lang(array('string' => '{var:1} asked you to say you read a message in {var:2}', 'vars' => array($actor, $channel_name))),
                'body'  => $message ? ws_plain_excerpt($viewer, $message['body'], 140) : '',
                'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
                'icon'  => 'bi-eye',
            );

        case 'note_answer_ai':
            $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

            return array(
                'title' => lang('Pinegrap AI answered in your note'),
                'body'  => $note ? ws_note_title($viewer, $note) : '',
                'url'   => $base . 'workspace_notes.php' . ($note ? '?note=' . (int) $note['id'] : ''),
                'icon'  => 'bi-cpu',
            );

        // An assistant finished a run one asked for on a task (delegation.php),
        // or an application proposed changes on it: the latest of them names
        // the assistant or the application and how it ended.
        case 'task_assistant':
            $run = (($task !== null) && function_exists('ws_delegation_ready') && ws_delegation_ready())
                ? db_item("SELECT agent, status" . ws_delegation_server_column() . " FROM ws_ai_requests
                    WHERE task_id = '" . (int) $task['id'] . "' AND requested_by = '" . (int) $row['user_id'] . "' AND status IN ('answered', 'failed')
                    ORDER BY id DESC LIMIT 1")
                : null;
            // An MCP server's alias is named by its alias.
            $agent = is_array($run) ? ws_task_run_key($run) : 'claude';
            $name = function_exists('ws_assistant_name') ? ws_assistant_name($agent) : 'Claude';
            $number = $task ? ws_task_number($task['id']) : '';

            // What it proposed and still waits for the person to apply
            // (changes.php), counted when the banner is drawn.
            $waiting = ($task && function_exists('ws_task_delegation_waiting_map')) ? (int) (ws_task_delegation_waiting_map(array($task['id']))[(int) $task['id']] ?? 0) : 0;

            // An application's proposal on the task (proposals.php) asked
            // nothing, so it is not an answer.
            $proposal = function_exists('ws_proposal_agent_parse') && ws_proposal_agent_parse($agent);

            if ($proposal) {
                $title = lang(array('string' => '{var:1} proposed changes on {var:2}', 'vars' => array($name, $number)));
            } elseif (is_array($run) && ($run['status'] === 'failed')) {
                $title = lang(array('string' => '{var:1} could not do what you asked on {var:2}', 'vars' => array($name, $number)));
            } else {
                $title = lang(array('string' => '{var:1} answered on {var:2}', 'vars' => array($name, $number)));
            }

            return array(
                'title' => $title,
                'body'  => ($task ? (string) $task['title'] : '')
                    . (($waiting > 0) ? ' — ' . lang(array('string' => '{var:1} proposed change(s) wait for approval', 'vars' => $waiting)) : ''),
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => $proposal ? 'bi-plug' : (($agent === 'ai') ? 'bi-cpu' : 'bi-stars'),
            );

        // A note on a task one is on, written by somebody else
        // (task_work.php). The newest note not of the reader's own names who
        // wrote and what: notes that came while the line was unread share it.
        case 'task_note':
            $last = ($task && function_exists('ws_task_work_ready') && ws_task_work_ready())
                ? db_item("SELECT sender_kind, sender_id, body FROM ws_task_notes
                    WHERE task_id = '" . (int) $task['id'] . "' AND deleted_at = 0
                    AND NOT (sender_kind = 'user' AND sender_id = '" . (int) $row['user_id'] . "')
                    ORDER BY id DESC LIMIT 1")
                : null;
            $writer = $actor;

            if (is_array($last)) {
                $writer = ($last['sender_kind'] === 'app')
                    ? (string) (ws_app_sender((int) $last['sender_id'])['name'] ?? $actor)
                    : ws_person_name((int) $last['sender_id']);
            }

            return array(
                'title' => lang(array('string' => '{var:1} wrote a note on {var:2}', 'vars' => array($writer, $task ? ws_task_number($task['id']) : ''))),
                'body'  => is_array($last) ? ws_plain_excerpt($viewer, (string) $last['body'], 140) : ($task ? (string) $task['title'] : ''),
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-chat-left-text',
            );

        // The reminder set on a task, and a task that passed its due time
        // (reminders.php).
        case 'task_reminder':
            return array(
                'title' => lang(array('string' => 'Reminder: {var:1}', 'vars' => $task ? mb_substr((string) $task['title'], 0, 150) : '')),
                'body'  => $task ? ws_task_number($task['id']) . ' · ' . lang(array('string' => 'Due: {var:1}', 'vars' => ws_task_due_label($task))) : '',
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-alarm',
            );

        case 'task_overdue':
            return array(
                'title' => lang(array('string' => '{var:1} is overdue', 'vars' => $task ? ws_task_number($task['id']) : '')),
                'body'  => $task ? (string) $task['title'] . ' — ' . lang(array('string' => 'Due: {var:1}', 'vars' => ws_task_due_label($task))) : '',
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-calendar-x',
            );

        // A meeting with a video call is about to start (reminders.php). The
        // line opens the item on the calendar, where Join is; the inbox list
        // also offers the link itself (join).
        case 'meeting_soon':
            $event = ((int) ($row['event_id'] ?? 0) > 0) ? db_item("SELECT * FROM ws_events WHERE id = '" . (int) $row['event_id'] . "'") : null;

            return array(
                'title' => lang(array('string' => 'Starting soon: {var:1}', 'vars' => is_array($event) ? mb_substr((string) $event['title'], 0, 150) : '')),
                'body'  => is_array($event) ? lang(array('string' => 'At {var:1}. Tap to join.', 'vars' => date('H:i', (int) $event['starts_at']))) : '',
                'url'   => $base . 'workspace_calendar.php' . (is_array($event) ? '?month=' . date('Y-m', (int) $event['starts_at']) . '&event=' . (int) $event['id'] : ''),
                'icon'  => 'bi-camera-video',
                'join'  => is_array($event) ? (string) $event['meeting_url'] : '',
            );
    }

    return array('title' => lang('Workspace'), 'body' => '', 'url' => $base . 'workspace.php', 'icon' => 'bi-bell');
}

/**
 * A person's inbox, newest first. Rows about a channel or a task they can no
 * longer see are left out, and marked read so the badge does not count them.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_inbox_list($viewer, $limit = 50)
{
    $rows = (array) db_items("SELECT * FROM ws_inbox WHERE user_id = '" . (int) $viewer['id'] . "'
        ORDER BY read_at = 0 DESC, id DESC LIMIT " . max(1, min(200, (int) $limit)));

    $out = array();

    foreach (ws_inbox_visible_rows($viewer, $rows) as $row) {
        $describe = ws_inbox_describe($viewer, $row);

        $out[] = $describe + array(
            'id'     => (int) $row['id'],
            'kind'   => (string) $row['kind'],
            'unread' => ((int) $row['read_at'] === 0),
            'time'   => ws_time_label($row['created_at']),
            'actor'  => current(ws_people(array($row['actor_id']))) ?: null,
        );
    }

    return $out;
}

/**
 * The rows of a person's inbox they may still see, in the order given - for
 * the workspace screen and for the API alike. The others are about a
 * channel, a task, a message or a note gone from their reach, and are marked
 * read so the badge does not count them; the changed lines of the API carry
 * a row so marked as taken away (ws_api_inbox_changed_rows()).
 *
 * @param array   $viewer
 * @param array[] $rows ws_inbox rows of the viewer
 * @return array[]
 */
function ws_inbox_visible_rows($viewer, $rows)
{
    $out = array();
    $hidden = array();

    foreach ((array) $rows as $row) {
        if (!ws_inbox_row_visible($viewer, $row)) {
            $hidden[] = (int) $row['id'];
            continue;
        }

        $out[] = $row;
    }

    if (!empty($hidden)) {
        db("UPDATE ws_inbox SET read_at = '" . time() . "' WHERE read_at = 0 AND id IN (" . implode(',', $hidden) . ")");
    }

    return $out;
}

/**
 * May the person still see what the row is about?
 *
 * @param array $viewer
 * @param array $row
 * @return bool
 */
function ws_inbox_row_visible($viewer, $row)
{
    if (!$viewer['member']) {
        return false;
    }

    if (((int) $row['channel_id'] > 0) && !ws_can_read_channel($viewer, ws_channel($row['channel_id']))) {
        return false;
    }

    if (((int) $row['task_id'] > 0)) {
        $task = ws_task($row['task_id']);

        if (!$task || !ws_can_see_task($viewer, $task)) {
            return false;
        }
    }

    if (((int) $row['message_id'] > 0)) {
        $message = ws_message($row['message_id']);

        if (!$message || ((int) $message['deleted_at'] > 0)) {
            return false;
        }
    }

    // A meeting's notice is there as long as the meeting is.
    if (($row['kind'] === 'meeting_soon') && !db_value("SELECT id FROM ws_events WHERE id = '" . (int) ($row['event_id'] ?? 0) . "'")) {
        return false;
    }

    // A line about a note is there as long as the person may read the note.
    if (in_array($row['kind'], array('note_shared', 'note_updated', 'note_answer', 'note_answer_ai'), true)) {
        $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

        if (!$note || (ws_note_access($viewer, $note) === '')) {
            return false;
        }
    }

    return true;
}

/**
 * Marks inbox rows read: some, or all of them.
 *
 * @param int         $user_id
 * @param int[]|string $ids 'all' for every one
 */
function ws_inbox_mark_read($user_id, $ids)
{
    $where = "user_id = '" . (int) $user_id . "' AND read_at = 0";

    if ($ids !== 'all') {
        $clean = array_filter(array_map('intval', (array) $ids));

        if (empty($clean)) {
            return;
        }

        $where .= " AND id IN (" . implode(',', $clean) . ")";
    }

    db("UPDATE ws_inbox SET read_at = '" . time() . "' WHERE " . $where);

    ws_bell_sync_read($user_id);
}

/**
 * The person opened a task: what their inbox said about it is read, in the
 * bell as well, and a banner still queued for it is not sent.
 *
 * @param int $user_id
 * @param int $task_id
 */
function ws_inbox_mark_task_read($user_id, $task_id)
{
    $user_id = (int) $user_id;
    $task_id = (int) $task_id;

    if (($user_id <= 0) || ($task_id <= 0)) {
        return;
    }

    // The unread rows of one person are few, and idx_user finds them.
    db("UPDATE ws_inbox SET read_at = '" . time() . "'
        WHERE user_id = '" . $user_id . "' AND read_at = 0 AND task_id = '" . $task_id . "'");

    if (mysqli_affected_rows(db::$con) > 0) {
        ws_bell_sync_read($user_id);
    }
}

/**
 * The most lines about a lost channel's tasks one person's check reads. A
 * line about a task names no channel, and the task's creator and its people
 * keep seeing it, so each is looked at on its own, the newest first.
 */
if (!defined('WS_INBOX_REACH_TASK_READ')) {
    define('WS_INBOX_REACH_TASK_READ', 500);
}

/**
 * People went out of a channel's reach: they left it, its owner took them
 * out, a group stopped bringing them in, or it was made private without them.
 * Their lines about it, about the discussions started in it and about its
 * tasks they no longer see are marked read now, the ones read long before as
 * well, as the inbox marks a line it finds out of reach (ws_inbox_visible_rows()).
 * The badge stops counting them at once, and that moment is what the changed
 * lines of GET /workspace/inbox find them by and carry them as taken away
 * from, and what moves the sync's inbox_changed_at. Somebody who still reads
 * the channel - anybody in a public one, an administrator with an inspection
 * open - loses nothing.
 *
 * @param array      $channel
 * @param int[]|null $user_ids null: everybody told something about it
 */
function ws_inbox_reach_lost($channel, $user_ids = null)
{
    $channel_id = is_array($channel) ? (int) $channel['id'] : 0;

    if ($channel_id <= 0) {
        return;
    }

    // A discussion is read only while the channel it was started in is.
    $channels = array($channel_id);

    if (function_exists('ws_threads_ready') && ws_threads_ready()) {
        foreach ((array) db_items("SELECT channel_id FROM ws_threads WHERE parent_channel_id = '" . $channel_id . "'") as $row) {
            $channels[] = (int) $row['channel_id'];
        }
    }

    $in = implode(',', $channels);

    // Made private: whoever was told something there. A rare step, so one
    // pass over the inbox is fine.
    if ($user_ids === null) {
        $user_ids = array();

        foreach ((array) db_items("SELECT DISTINCT user_id FROM ws_inbox WHERE channel_id IN (" . $in . ")") as $row) {
            $user_ids[] = (int) $row['user_id'];
        }
    }

    // As it is now, after the step that called.
    $channel = ws_channel($channel_id);
    $now = time();

    foreach (array_unique(array_map('intval', (array) $user_ids)) as $user_id) {
        if ($user_id <= 0) {
            continue;
        }

        $rights = ws_rights_for_id($user_id);

        if (ws_can_read_channel($rights, $channel)) {
            continue;
        }

        db("UPDATE ws_inbox SET read_at = '" . $now . "' WHERE user_id = '" . $user_id . "' AND channel_id IN (" . $in . ")");

        $moved = mysqli_affected_rows(db::$con);
        $gone = array();

        foreach ((array) db_items("SELECT * FROM ws_inbox
            WHERE user_id = '" . $user_id . "' AND channel_id NOT IN (" . $in . ")
            AND task_id IN (SELECT id FROM ws_tasks WHERE channel_id = '" . $channel_id . "')
            ORDER BY id DESC
            LIMIT " . WS_INBOX_REACH_TASK_READ) as $row) {
            if (!ws_inbox_row_visible($rights, $row)) {
                $gone[] = (int) $row['id'];
            }
        }

        if (!empty($gone)) {
            db("UPDATE ws_inbox SET read_at = '" . $now . "' WHERE id IN (" . implode(',', $gone) . ")");

            $moved += mysqli_affected_rows(db::$con);
        }

        if ($moved > 0) {
            ws_bell_sync_read($user_id);
        }
    }
}

/**
 * Does the person see the task only through its channel - neither its
 * creator nor one of its people? Those are the tasks whose lines went out of
 * their reach with the channel (ws_inbox_reach_lost()) and come back with it.
 *
 * @param int   $user_id
 * @param array $task
 * @param int[] $assignees
 * @return bool
 */
function ws_inbox_task_through_channel($user_id, $task, $assignees)
{
    return ((int) $task['channel_id'] > 0)
        && ((int) $task['creator_id'] !== (int) $user_id)
        && !in_array((int) $user_id, array_map('intval', (array) $assignees), true);
}

/**
 * People came back into a channel's reach: they were added to it again, a
 * group brought them back, it was made public, or they joined one of its
 * discussions again. Their lines about it, about the discussions they read
 * again and about its tasks they see through it again were marked read when
 * they went out of reach (ws_inbox_reach_lost()), so the changed lines of
 * GET /workspace/inbox carried them as taken away; their read_at moves to now,
 * and the changed lines carry them again as they are from that moment, and
 * the sync's inbox_changed_at moves. They stay read, so the badge is as it
 * was. A line never read is not touched: one written while out of reach
 * comes among the changes by when it was written.
 *
 * Only for people who could not read the channel before the step that
 * called: somebody who read it all along would have every line about it
 * sent again for nothing.
 *
 * @param array      $channel
 * @param int[]|null $user_ids null: everybody told something about it who
 *                             is not in it - it was made public
 */
function ws_inbox_reach_regained($channel, $user_ids = null)
{
    $channel_id = is_array($channel) ? (int) $channel['id'] : 0;

    if ($channel_id <= 0) {
        return;
    }

    // The discussions started in it, read again by those still in them.
    $threads = array();

    if (function_exists('ws_threads_ready') && ws_threads_ready()) {
        foreach ((array) db_items("SELECT channel_id FROM ws_threads WHERE parent_channel_id = '" . $channel_id . "'") as $row) {
            $threads[] = (int) $row['channel_id'];
        }
    }

    $in = implode(',', array_merge(array($channel_id), $threads));

    // Made public: whoever was told something there and is not in it. A
    // rare step, so one pass over the inbox is fine.
    if ($user_ids === null) {
        $user_ids = array();

        foreach ((array) db_items("SELECT DISTINCT user_id FROM ws_inbox
            WHERE channel_id IN (" . $in . ")
            AND user_id NOT IN (SELECT user_id FROM ws_channel_members WHERE channel_id = '" . $channel_id . "')") as $row) {
            $user_ids[] = (int) $row['user_id'];
        }
    }

    // As it is now, after the step that called.
    $channel = ws_channel($channel_id);
    $now = time();
    $tasks = array();

    foreach (array_unique(array_map('intval', (array) $user_ids)) as $user_id) {
        if ($user_id <= 0) {
            continue;
        }

        $rights = ws_rights_for_id($user_id);

        if (!ws_can_read_channel($rights, $channel)) {
            continue;
        }

        // A discussion is read by the people in it, while they read the
        // channel it was started in - as they do now.
        $back = array($channel_id);

        if (!empty($threads)) {
            $back = array_merge($back, array_map('intval', (array) db_values("SELECT channel_id FROM ws_channel_members
                WHERE user_id = '" . $user_id . "' AND channel_id IN (" . implode(',', $threads) . ")")));
        }

        db("UPDATE ws_inbox SET read_at = '" . $now . "'
            WHERE user_id = '" . $user_id . "' AND read_at > 0 AND channel_id IN (" . implode(',', $back) . ")");

        $again = array();

        foreach ((array) db_items("SELECT * FROM ws_inbox
            WHERE user_id = '" . $user_id . "' AND read_at > 0 AND channel_id NOT IN (" . $in . ")
            AND task_id IN (SELECT id FROM ws_tasks WHERE channel_id = '" . $channel_id . "')
            ORDER BY id DESC
            LIMIT " . WS_INBOX_REACH_TASK_READ) as $row) {
            $task_id = (int) $row['task_id'];

            if (!isset($tasks[$task_id])) {
                $task = ws_task($task_id);
                $tasks[$task_id] = $task ? array($task, ws_task_assignee_ids($task_id)) : null;
            }

            if ($tasks[$task_id] && ws_inbox_task_through_channel($user_id, $tasks[$task_id][0], $tasks[$task_id][1])
                && ws_inbox_row_visible($rights, $row)) {
                $again[] = (int) $row['id'];
            }
        }

        if (!empty($again)) {
            db("UPDATE ws_inbox SET read_at = '" . $now . "' WHERE id IN (" . implode(',', $again) . ")");
        }
    }
}

/**
 * How long the inbox remembers a line it took away: an app that last asked
 * for changes longer ago than this reads the inbox again.
 */
if (!defined('WS_INBOX_DELETED_KEEP')) {
    define('WS_INBOX_DELETED_KEEP', 30 * 86400);
}

/**
 * Does the inbox remember the lines it took away (2026.4.10, 10.88)?
 *
 * @return bool
 */
function ws_inbox_deleted_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_inbox_deleted'") > 0);
    }

    return $ready;
}

/**
 * Takes inbox rows away for good, because what they said went: a mention in
 * a message deleted, a note deleted or no longer shared, a discussion purged.
 * Which ones went is kept for WS_INBOX_DELETED_KEEP, so an app holding them
 * learns it from the changed lines of GET /workspace/inbox, as it learns of a
 * deleted message; older such marks go at the same time.
 *
 * @param string $where condition on ws_inbox
 */
function ws_inbox_delete($where)
{
    if (ws_inbox_deleted_ready()) {
        $now = time();

        // An id is written once, but a server that reuses the ids of its
        // last deleted rows (MySQL before 8.0, after a restart) can take a
        // row away twice: the newer mark replaces the older.
        db("REPLACE INTO ws_inbox_deleted (id, user_id, kind, channel_id, created_at, deleted_at)
            SELECT id, user_id, kind, channel_id, created_at, '" . $now . "' FROM ws_inbox WHERE " . $where);

        db("DELETE FROM ws_inbox_deleted WHERE deleted_at < '" . ($now - WS_INBOX_DELETED_KEEP) . "' LIMIT 500");
    }

    db("DELETE FROM ws_inbox WHERE " . $where);
}

/**
 * Can the bell carry rows addressed to one person (4.83)?
 *
 * @return bool
 */
function ws_bell_ready()
{
    static $ready = null;

    if ($ready === null) {
        include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

        $ready = function_exists('pg_notification_targets_available') && pg_notification_targets_available();
    }

    return $ready;
}

/**
 * Puts an inbox row into its person's bell. The sentence is written into the
 * row as it reads now, for the day the inbox row is no longer there; the bell
 * describes it afresh from the inbox row while it is.
 *
 * @param int $user_id
 * @param int $inbox_id
 */
function ws_bell_add($user_id, $inbox_id)
{
    if (!ws_bell_ready()) {
        return;
    }

    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $inbox_id . "'");

    if (!is_array($row)) {
        return;
    }

    $described = ws_inbox_describe(ws_rights_for_id($user_id) + array('ecommerce' => false, 'contacts' => false, 'erp' => false), $row);

    $by = ws_bell_user($_SESSION['sessionusername'] ?? '', function_exists('api_current_app') ? api_current_app() : null);

    db("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
        VALUES ('workspace', 'info', '" . e(mb_substr($described['title'], 0, 250)) . "', '" . (int) $user_id . "', '" . (int) $inbox_id . "',
            '" . e($by) . "', '0', UNIX_TIMESTAMP())");
}

/**
 * Who a bell row says set it off: the person signed in on the panel. A call
 * through the external API or the MCP server keeps no session, so there it is
 * the application's owner - the person signed in on a device or through
 * OAuth, the owner of an application key - as in the activity log
 * (ws_change_log(), ws_api_as_owner()). A scheduled job has neither.
 *
 * @param string     $session the panel session's user name
 * @param array|null $app     api_current_app()
 * @return string
 */
function ws_bell_user($session, $app)
{
    $session = (string) $session;

    if ($session !== '') {
        return $session;
    }

    return (string) ($app['owner']['username'] ?? '');
}

/**
 * What a bell row about an inbox row says, for the person it is addressed
 * to. Null when the row is gone or no longer theirs to see.
 *
 * @param int $inbox_id
 * @param int $user_id
 * @return array|null title, body, url
 */
function ws_bell_describe($inbox_id, $user_id)
{
    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $inbox_id . "' AND user_id = '" . (int) $user_id . "'");

    if (!is_array($row)) {
        return null;
    }

    $user = function_exists('pg_load_user_row') ? pg_load_user_row((int) $user_id) : null;
    $viewer = is_array($user) ? ws_viewer($user) : ws_rights_for_id($user_id) + array('ecommerce' => false, 'contacts' => false, 'erp' => false);

    if (!ws_inbox_row_visible($viewer, $row)) {
        return null;
    }

    $described = ws_inbox_describe($viewer, $row);

    return array('title' => $described['title'], 'body' => $described['body'], 'url' => $described['url']);
}

/**
 * Whatever the workspace marked read is read in the bell as well.
 *
 * @param int $user_id
 */
function ws_bell_sync_read($user_id)
{
    if (!ws_bell_ready() || !function_exists('pg_notification_reads_available') || !pg_notification_reads_available()) {
        return;
    }

    $user_id = (int) $user_id;

    db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
        SELECT n.id, '" . $user_id . "', '" . time() . "'
        FROM notifications n
        INNER JOIN ws_inbox i ON i.id = n.reference_id AND i.read_at > 0
        WHERE n.action = 'workspace' AND n.target_user_id = '" . $user_id . "'");
}

/**
 * Is a queued device banner still worth sending? Asked by the push queue at
 * send time, after the wait.
 *
 * @param int $user_id
 * @param int $inbox_id
 * @return bool
 */
function ws_push_still_relevant($user_id, $inbox_id)
{
    if (!ws_ready()) {
        return false;
    }

    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $inbox_id . "' AND user_id = '" . (int) $user_id . "'");

    if (!is_array($row) || ((int) $row['read_at'] > 0)) {
        return false;
    }

    return ws_inbox_row_visible(ws_rights_for_id($user_id), $row);
}

/**
 * What waits for a person, as the service worker draws banners.
 *
 * @param array $user validate_user() array
 * @param int   $limit
 * @return array[]
 */
function ws_push_pending($user, $limit = 3)
{
    if (!ws_enabled()) {
        return array();
    }

    $viewer = ws_viewer($user);

    if (!$viewer['member']) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_inbox WHERE user_id = '" . (int) $viewer['id'] . "' AND read_at = 0
        ORDER BY id ASC LIMIT 20");

    $out = array();

    foreach ($rows as $row) {
        if (!ws_inbox_row_visible($viewer, $row)) {
            continue;
        }

        $describe = ws_inbox_describe($viewer, $row);

        $out[] = array(
            'id'        => 'ws-' . (int) $row['id'],
            'title'     => $describe['title'],
            'body'      => $describe['body'],
            'url'       => $describe['url'],
            'icon'      => 'assets/images/notification-chat.png',
            'badge'     => 'assets/images/notification-chat-badge.png',
            'tag'       => 'pg-ws-' . (int) $row['id'],
            'timestamp' => (int) $row['created_at'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}
