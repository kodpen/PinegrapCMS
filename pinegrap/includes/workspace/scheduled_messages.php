<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - scheduled messages: a message written in the writing box now
 * and posted at a time chosen beside the send button.
 *
 * Everybody in the team may schedule a message, the user role included: it
 * is one message, in a channel the writer may post in, and it goes out with
 * the writer's own rights as they are when it is due - nothing a scheduled
 * action can do (e-mails, record changes, webhooks) and nothing that needs
 * staff. A scheduled message is a row of ws_scheduled_actions of kind
 * "message" (scheduled.php runs it with the rest): one time rule that does
 * not repeat and one "post" action.
 *
 * Until it is due a scheduled message is its writer's alone: the channel
 * does not see it, the writer sees it above the writing box and on the
 * scheduled screen, and changes, sends or deletes it there. Once posted it
 * leaves a line in the activity log and the row is deleted. One that cannot
 * be posted (the writer left the channel, the channel was archived) stays,
 * marked failed, and the writer is told in the inbox.
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
 * The most messages one person may have waiting.
 */
define('WS_SCHEDULED_MESSAGES_MAX', 50);

/**
 * How far ahead a message may be scheduled, in days.
 */
define('WS_SCHEDULED_MESSAGES_AHEAD', 366);

/**
 * May this person schedule messages? Anybody in the team, once the schema
 * knows the kind of a scheduled row.
 *
 * @param array $viewer
 * @return bool
 */
function ws_can_schedule_messages($viewer)
{
    return !empty($viewer['member']) && ws_scheduled_messages_ready();
}

/**
 * One of this person's own scheduled messages.
 *
 * @param array $viewer
 * @param int   $id
 * @return array|null decoded
 */
function ws_scheduled_message_mine($viewer, $id)
{
    $row = ws_can_schedule_messages($viewer) ? ws_scheduled((int) $id) : null;

    if (!$row || ((string) ($row['kind'] ?? '') !== 'message') || ((int) $row['created_by'] !== (int) $viewer['id'])) {
        return null;
    }

    return $row;
}

/**
 * Creates or changes a scheduled message.
 *
 * @param array $viewer
 * @param array $data id (0 for a new one), channel_id, body, parent_id, date (Y-m-d) and
 *                    time (H:i), or in_minutes
 * @return array ok, error, field, id
 */
function ws_scheduled_message_save($viewer, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'id' => 0);
    };

    if (!ws_can_schedule_messages($viewer)) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $id = (int) ($data['id'] ?? 0);
    $existing = ($id > 0) ? ws_scheduled_message_mine($viewer, $id) : null;

    if (($id > 0) && (!$existing || !in_array($existing['status'], array('active', 'failed'), true))) {
        return $fail(lang('That scheduled message could not be found.'));
    }

    $channel = ws_channel($existing ? (int) $existing['channel_id'] : (int) ($data['channel_id'] ?? 0));

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    // "/ai ..." and "/claude ..." are messages to the assistants; any other
    // command does something at once and cannot wait.
    $body = (string) ($data['body'] ?? '');

    if (function_exists('ws_claude_command_body')) {
        $body = ws_claude_command_body($body);
    }

    if (function_exists('ws_ai_command_body')) {
        $body = ws_ai_command_body($body);
    }

    $body = ws_tokens_normalise(trim($body));

    if ($body === '') {
        return $fail(lang('The message is empty.'), 'body');
    }

    if (preg_match('#^/[a-z]#i', $body)) {
        return $fail(lang('A command cannot be scheduled. Write it as a message, or send it now.'), 'body');
    }

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        return $fail(lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)), 'body');
    }

    // "In an hour" is counted on the server's clock, which the date and the
    // time are read in: the browser's may be in another zone.
    if ((int) ($data['in_minutes'] ?? 0) > 0) {
        $later = time() + min(1440, (int) $data['in_minutes']) * 60;
        $data['date'] = date('Y-m-d', $later);
        $data['time'] = date('H:i', $later);
    }

    $date = (string) ($data['date'] ?? '');
    $clock = (string) ($data['time'] ?? '');
    $at = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && ws_scheduled_clock_ok($clock)) ? strtotime($date . ' ' . $clock . ':00') : false;

    if ($at === false) {
        return $fail(lang('The date or the time is not valid.'), 'time');
    }

    if ($at <= time()) {
        return $fail(lang('That time has already gone by.'), 'time');
    }

    if ($at > strtotime('+' . WS_SCHEDULED_MESSAGES_AHEAD . ' days')) {
        return $fail(lang('A message can be scheduled at most a year ahead.'), 'time');
    }

    // An answer to a message of the same channel, or to none.
    $parent_id = (int) ($data['parent_id'] ?? ($existing['action']['parent_id'] ?? 0));

    if (($parent_id > 0) && ((int) db_value("SELECT channel_id FROM ws_messages WHERE id = '" . $parent_id . "' AND deleted_at = 0") !== (int) $channel['id'])) {
        $parent_id = 0;
    }

    $rules = array(array('type' => 'at', 'date' => $date, 'time' => $clock, 'repeat' => 'none'));
    $action = array('type' => 'post', 'channel_id' => (int) $channel['id'], 'body' => $body, 'parent_id' => $parent_id);
    $name = mb_substr(ws_plain_excerpt($viewer, $body, 150), 0, 160);
    $json = function ($value) {
        return "'" . e(json_encode($value, JSON_UNESCAPED_UNICODE)) . "'";
    };
    $now = time();

    if ($existing) {
        db("UPDATE ws_scheduled_actions SET
                name = '" . e($name) . "',
                rules = " . $json($rules) . ",
                action = " . $json($action) . ",
                status = 'active',
                next_run_at = '" . (int) $at . "',
                updated_by = '" . (int) $viewer['id'] . "',
                updated_at = '" . $now . "'
            WHERE id = '" . (int) $existing['id'] . "' AND running_at = 0");

        return array('ok' => true, 'error' => '', 'field' => '', 'id' => (int) $existing['id']);
    }

    $waiting = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_actions
        WHERE kind = 'message' AND created_by = '" . (int) $viewer['id'] . "' AND status IN ('active', 'failed')");

    if ($waiting >= WS_SCHEDULED_MESSAGES_MAX) {
        return $fail(lang(array('string' => 'You already have {var:1} messages waiting to be posted.', 'vars' => WS_SCHEDULED_MESSAGES_MAX)));
    }

    db("INSERT INTO ws_scheduled_actions (name, kind, channel_id, note_id, created_by, rules, action, status, next_run_at, updated_by, created_at, updated_at)
        VALUES ('" . e($name) . "', 'message', '" . (int) $channel['id'] . "', 0, '" . (int) $viewer['id'] . "',
            " . $json($rules) . ", " . $json($action) . ", 'active', '" . (int) $at . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    $id = (int) mysqli_insert_id(db::$con);

    return ($id > 0)
        ? array('ok' => true, 'error' => '', 'field' => '', 'id' => $id)
        : $fail(lang('The message could not be saved.'));
}

/**
 * A scheduled message as its writer's screens show it.
 *
 * @param array $viewer
 * @param array $row decoded
 * @return array
 */
function ws_scheduled_message_present($viewer, $row)
{
    $action = is_array($row['action']) ? $row['action'] : array();
    $body = (string) ($action['body'] ?? '');
    $tokens = ws_tokens($body);
    $refs = ws_refs_resolve($viewer, $tokens);
    $channel = ws_channel((int) $row['channel_id']);
    $rule = ws_scheduled_time_rule($row);
    $labels = array();

    foreach ($tokens as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
        }
    }

    $error = '';

    if ((string) $row['status'] === 'failed') {
        $run = db_item("SELECT status, detail FROM ws_scheduled_runs WHERE action_id = '" . (int) $row['id'] . "' ORDER BY id DESC LIMIT 1");
        $error = is_array($run) ? ws_scheduled_run_summary($run) : '';
    }

    return array(
        'id'        => (int) $row['id'],
        'channel'   => $channel ? array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'kind' => (string) $channel['kind']) : null,
        'html'      => ws_render_body($body, $refs),
        'raw'       => $body,
        'labels'    => $labels,
        'parent_id' => (int) ($action['parent_id'] ?? 0),
        'date'      => (string) ($rule['date'] ?? ''),
        'time'      => (string) ($rule['time'] ?? ''),
        'when'      => ws_scheduled_moment((int) $row['next_run_at']),
        'timestamp' => (int) $row['next_run_at'],
        'status'    => (string) $row['status'],
        'error'     => $error,
    );
}

/**
 * This person's scheduled messages: in one channel, or everywhere; the next
 * one first, the ones that could not be posted after.
 *
 * @param array $viewer
 * @param int   $channel_id 0 for every channel
 * @return array[]
 */
function ws_scheduled_messages_list($viewer, $channel_id = 0)
{
    if (!ws_can_schedule_messages($viewer)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_scheduled_actions
        WHERE kind = 'message' AND created_by = '" . (int) $viewer['id'] . "' AND status IN ('active', 'failed')"
        . (((int) $channel_id > 0) ? " AND channel_id = '" . (int) $channel_id . "'" : '') . "
        ORDER BY (status = 'failed'), next_run_at, id
        LIMIT " . WS_SCHEDULED_MESSAGES_MAX) as $row) {
        $out[] = ws_scheduled_message_present($viewer, ws_scheduled_decode($row));
    }

    return $out;
}

/**
 * Deletes one of this person's scheduled messages before it is posted.
 *
 * @param array $viewer
 * @param array $row
 * @return array ok, error
 */
function ws_scheduled_message_delete($viewer, $row)
{
    db("DELETE FROM ws_scheduled_actions WHERE id = '" . (int) $row['id'] . "' AND kind = 'message'
        AND created_by = '" . (int) $viewer['id'] . "' AND running_at = 0");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return array('ok' => false, 'error' => lang('It is being posted right now.'));
    }

    db("DELETE FROM ws_scheduled_runs WHERE action_id = '" . (int) $row['id'] . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * Posts a scheduled message whose time has come (or that its writer sends
 * now), already claimed by ws_scheduled_execute(). Posted, it is written in
 * the log and deleted; one that cannot be posted stays as failed and its
 * writer is told.
 *
 * @param array $row     decoded
 * @param bool  $by_hand sent by its writer before its time
 * @return string ran | failed | '' (put off: the writer is writing too fast)
 */
function ws_scheduled_message_run($row, $by_hand = false)
{
    $started = time();
    $action = is_array($row['action']) ? $row['action'] : array();
    $viewer = ws_change_viewer_for_id((int) $row['created_by']);
    $channel = ws_channel((int) ($action['channel_id'] ?? $row['channel_id']));
    $error = '';

    // A burst of messages due in the same minute waits for the rate the
    // writing box keeps, rather than failing.
    if (is_array($viewer) && ws_rate_limited((int) $viewer['id'])) {
        db("UPDATE ws_scheduled_actions SET running_at = 0, next_run_at = '" . ($started + 60) . "' WHERE id = '" . (int) $row['id'] . "'");

        return '';
    }

    if (!is_array($viewer) || empty($viewer['member'])) {
        $error = lang('The one who wrote it is no longer in the team.');
    } elseif (!$channel) {
        $error = lang('The channel to write in is gone.');
    } else {
        $sent = ws_message_send($viewer, $channel, (string) ($action['body'] ?? ''), array('parent_id' => (int) ($action['parent_id'] ?? 0)));

        if (!$sent['ok']) {
            $error = (string) $sent['error'];
        }
    }

    if ($error !== '') {
        db("UPDATE ws_scheduled_actions SET status = 'failed', running_at = 0, last_run_at = '" . time() . "', run_count = run_count + 1
            WHERE id = '" . (int) $row['id'] . "'");

        ws_scheduled_record_run($row, 'failed', array(array('step' => 'action', 'ok' => false, 'text' => $error)), array('by_hand' => $by_hand), 0, $started);
        ws_notify((int) $row['created_by'], 'scheduled_message', array('channel_id' => $channel ? (int) $channel['id'] : 0, 'message_id' => 0, 'actor_id' => 0));
        log_activity(lang(array('string' => 'a scheduled workspace message of {var:1} could not be posted: {var:2}', 'vars' => array(ws_person_name($row['created_by']), $error))), ws_person_name($row['created_by']));

        return 'failed';
    }

    // Asked an assistant: queued as if it had been written now.
    $body = ws_tokens_normalise(trim((string) ($action['body'] ?? '')));

    if (function_exists('ws_claude_after_send')) {
        ws_claude_after_send($viewer, $channel, $sent['message_id'], $body);
    }

    if (function_exists('ws_ai_after_send')) {
        ws_ai_after_send($viewer, $channel, $sent['message_id'], $body);
    }

    log_activity(lang(array('string' => 'a scheduled workspace message of {var:1} was posted in #{var:2}', 'vars' => array(ws_person_name($row['created_by']), (string) $channel['name']))), ws_person_name($row['created_by']));

    db("DELETE FROM ws_scheduled_actions WHERE id = '" . (int) $row['id'] . "'");
    db("DELETE FROM ws_scheduled_runs WHERE action_id = '" . (int) $row['id'] . "'");

    return 'ran';
}

/**
 * The texts of the scheduled messages, on the channel screen.
 *
 * @return array key => text
 */
function ws_scheduled_messages_js_strings()
{
    return array(
        'sm_schedule'         => lang('Schedule the message'),
        'sm_send_later'       => lang('Send later'),
        'sm_tomorrow_morning' => ws_js_template('Tomorrow at {var:1}', 1),
        'sm_next_monday'      => ws_js_template('Monday at {var:1}', 1),
        'sm_in_hour'          => lang('In an hour'),
        'sm_custom'           => lang('Choose a time…'),
        'sm_custom_title'     => lang('When should it be posted?'),
        'sm_date'             => lang('Date'),
        'sm_time'             => lang('Time'),
        'sm_save'             => lang('Schedule'),
        'sm_saved'            => ws_js_template('Scheduled for {var:1}. Only you see it until then.', 1),
        'sm_bar'              => ws_js_template('{var:1} scheduled messages in this channel', 1),
        'sm_bar_one'          => ws_js_template('A scheduled message in this channel: {var:1}', 1),
        'sm_show'             => lang('See them'),
        'sm_title'            => lang('Scheduled messages'),
        'sm_mine_help'        => lang('Messages you wrote to be posted later. Nobody else sees them until they are posted.'),
        'sm_empty'            => lang('You have no scheduled messages.'),
        'sm_send_now'         => lang('Send now'),
        'sm_reschedule'       => lang('Change the time'),
        'sm_edit_text'        => lang('Edit the text'),
        'sm_delete'           => lang('Delete'),
        'sm_delete_confirm'   => lang('Delete this scheduled message? It will not be posted.'),
        'sm_sent'             => lang('The message was posted.'),
        'sm_deleted'          => lang('The scheduled message was deleted.'),
        'sm_failed'           => lang('Could not be posted'),
        'sm_at'               => ws_js_template('Posts {var:1}', 1),
        'sm_in'               => ws_js_template('in #{var:1}', 1),
        'sm_editing'          => lang('Editing a scheduled message. Esc to stop.'),
        'sm_reply'            => lang('An answer to a message'),
    );
}
