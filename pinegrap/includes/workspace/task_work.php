<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what happens inside a task while it is worked on: its
 * checklist, how far that has got, and the notes people add along the way.
 *
 * A task's checklist has two sources and they count together. The "- [ ]"
 * lines of the task's own description are ticked on the task (ws_task_checks).
 * A checklist written in a channel and turned into the task stays in its
 * message (ws_tasks.checklist_message_id): it is ticked in the channel or on
 * the task, and both show the same ticks, because there is only one set -
 * ws_checks, the message's. items_total / items_done on the task row keep the
 * count, so lists, cards and the board show the progress without reading a
 * list. A full list does not close the task; the screens offer to.
 *
 * Notes are dated lines on a task: what was tried, what the customer said.
 * Tags work in them; a person mentioned in one is told.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest note, in characters. */
define('WS_TASK_NOTE_MAX', 4000);

/**
 * Are the columns and tables there? The 2026.4.4 step 4.86 adds them; until it
 * has run a task has neither progress nor notes.
 *
 * @return bool
 */
function ws_task_work_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_task_checks', 'ws_task_notes')") === 2)
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_tasks' AND COLUMN_NAME = 'notes_count'") > 0);
    }

    return $ready;
}

/* ---------------------------------------------------------------------------
   Checklist and progress
   --------------------------------------------------------------------------- */

/**
 * Who ticked what on a set of tasks' own checklists.
 *
 * @param int[] $task_ids
 * @return array task id => item => [checked, user_id, name, at]
 */
function ws_task_checks_map($task_ids)
{
    $task_ids = array_values(array_filter(array_map('intval', (array) $task_ids)));

    if (empty($task_ids) || !ws_task_work_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_task_checks WHERE task_id IN (" . implode(',', $task_ids) . ")");
    $user_ids = array();

    foreach ($rows as $row) {
        $user_ids[] = (int) $row['user_id'];
    }

    $people = ws_people($user_ids);
    $out = array();

    foreach ($rows as $row) {
        $out[(int) $row['task_id']][(int) $row['item']] = array(
            'checked' => ((int) $row['checked'] === 1),
            'user_id' => (int) $row['user_id'],
            'name'    => $people[(int) $row['user_id']]['name'] ?? ws_person_name($row['user_id']),
            'at'      => ws_time_label($row['updated_at']),
        );
    }

    return $out;
}

/**
 * The channel message whose checklist a task carries, while it is there.
 *
 * @param array $task
 * @return array|null
 */
function ws_task_checklist_message($task)
{
    if ((int) ($task['checklist_message_id'] ?? 0) <= 0) {
        return null;
    }

    $message = ws_message($task['checklist_message_id']);

    return ($message && ((int) $message['deleted_at'] === 0)) ? $message : null;
}

/**
 * How many items of a list are done: a tick given since overrides how the
 * item was written.
 *
 * @param array[] $items  ws_checklist_items()
 * @param array   $ticks  item => checked (bool)
 * @return int
 */
function ws_checklist_done_count($items, $ticks)
{
    $done = 0;

    foreach ($items as $item) {
        if (array_key_exists($item['index'], $ticks) ? $ticks[$item['index']] : $item['checked']) {
            $done++;
        }
    }

    return $done;
}

/**
 * Counts a task's checklist again and keeps the count on the row. The row's
 * updated_at moves only when the count did, which is what redraws the task's
 * card in an open channel.
 *
 * @param int $task_id
 */
function ws_task_progress_refresh($task_id)
{
    if (!ws_task_work_ready()) {
        return;
    }

    $task = ws_task($task_id);

    if (!$task) {
        return;
    }

    $total = 0;
    $done = 0;

    $items = ws_checklist_items($task['description']);

    if (!empty($items)) {
        $ticks = array();

        foreach ((array) db_items("SELECT item, checked FROM ws_task_checks WHERE task_id = '" . (int) $task['id'] . "'") as $row) {
            $ticks[(int) $row['item']] = ((int) $row['checked'] === 1);
        }

        $total += count($items);
        $done += ws_checklist_done_count($items, $ticks);
    }

    $message = ws_task_checklist_message($task);

    if ($message) {
        $items = ws_checklist_items($message['body']);
        $ticks = array();

        if (ws_interact_ready()) {
            foreach ((array) db_items("SELECT item, checked FROM ws_checks WHERE message_id = '" . (int) $message['id'] . "'") as $row) {
                $ticks[(int) $row['item']] = ((int) $row['checked'] === 1);
            }
        }

        $total += count($items);
        $done += ws_checklist_done_count($items, $ticks);
    }

    $total = min(65535, $total);
    $done = min($total, $done);

    if (($total !== (int) $task['items_total']) || ($done !== (int) $task['items_done'])) {
        db("UPDATE ws_tasks SET items_total = '" . $total . "', items_done = '" . $done . "', updated_at = '" . time() . "'
            WHERE id = '" . (int) $task['id'] . "'");

        // The badge under the channel list shows the new share.
        if ($message) {
            ws_message_touch($message['id']);
        }
    }
}

/**
 * The tasks that carry a message's checklist, counted again after the list
 * changed in the channel: a tick, an edit, the message deleted.
 *
 * @param int $message_id
 */
function ws_checklist_tasks_refresh($message_id)
{
    if (!ws_task_work_ready()) {
        return;
    }

    foreach ((array) db_values("SELECT id FROM ws_tasks WHERE checklist_message_id = '" . (int) $message_id . "'") as $task_id) {
        ws_task_progress_refresh((int) $task_id);
    }
}

/**
 * A task's progress as the screens and the API show it, or null when the
 * task has no checklist.
 *
 * @param array $task
 * @return array|null done, total, percent, complete
 */
function ws_task_progress($task)
{
    $total = (int) ($task['items_total'] ?? 0);

    if ($total <= 0) {
        return null;
    }

    $done = min($total, (int) ($task['items_done'] ?? 0));

    return array(
        'done'     => $done,
        'total'    => $total,
        'percent'  => (int) floor(($done * 100) / $total),
        'complete' => ($done === $total),
    );
}

/**
 * Ticks one item of a task's own checklist (a line of its description), or
 * clears it. Whoever may change the task may.
 *
 * @param array $viewer
 * @param array $task
 * @param int   $item
 * @param bool  $checked
 * @return array ok, error
 */
function ws_task_check_set($viewer, $task, $item, $checked)
{
    if (!ws_task_work_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (!ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'));
    }

    $items = ws_checklist_items($task['description']);
    $item = (int) $item;

    if (!isset($items[$item])) {
        return array('ok' => false, 'error' => lang('That item is no longer in the list.'));
    }

    db("INSERT INTO ws_task_checks (task_id, item, checked, user_id, updated_at)
        VALUES ('" . (int) $task['id'] . "', '" . $item . "', '" . ($checked ? 1 : 0) . "', '" . (int) $viewer['id'] . "', '" . time() . "')
        ON DUPLICATE KEY UPDATE checked = VALUES(checked), user_id = VALUES(user_id), updated_at = VALUES(updated_at)");

    ws_task_progress_refresh($task['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The checklist lines of a text alone, drawn as one list with its ticks.
 *
 * @param array  $viewer
 * @param string $body
 * @param array  $checks item => [checked, name, at]
 * @param bool   $can_check
 * @return string
 */
function ws_checklist_only_html($viewer, $body, $checks, $can_check)
{
    $lines = array();

    foreach (ws_checklist_items($body) as $item) {
        $lines[] = '- [' . ($item['checked'] ? 'x' : ' ') . '] ' . $item['text'];
    }

    if (empty($lines)) {
        return '';
    }

    $text = implode("\n", $lines);

    return ws_render_body($text, ws_refs_resolve($viewer, ws_tokens($text)), $checks, $can_check);
}

/**
 * Everything the task drawer shows about the checklist: the count, the
 * task's own items, and the channel list it carries.
 *
 * @param array $viewer
 * @param array $task
 * @param bool  $can_edit
 * @return array
 */
function ws_task_checklist_detail($viewer, $task, $can_edit)
{
    $own = ws_checklist_items($task['description']);
    $detail = array(
        'progress'  => ws_task_progress($task),
        'own_count' => count($own),
        'own_html'  => '',
        'message'   => null,
    );

    if (!empty($own)) {
        $checks = ws_task_checks_map(array($task['id']));
        $detail['own_html'] = ws_checklist_only_html($viewer, $task['description'], $checks[(int) $task['id']] ?? array(), $can_edit && ws_task_work_ready());
    }

    $message = ws_task_checklist_message($task);

    if ($message) {
        $channel = ws_channel($message['channel_id']);

        if (ws_can_read_channel($viewer, $channel)) {
            $checks = ws_checks_map(array($message['id']));
            $author = ($message['sender_kind'] === 'user') ? ws_person_name($message['sender_id']) : ws_app_sender((int) $message['sender_id'])['name'];

            $detail['message'] = array(
                'id'      => (int) $message['id'],
                'channel' => (string) $channel['name'],
                'author'  => $author,
                'count'   => count(ws_checklist_items($message['body'])),
                'url'     => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel['id'] . '&message=' . (int) $message['id'],
                'html'    => ws_checklist_only_html($viewer, $message['body'], $checks[(int) $message['id']] ?? array(), ws_can_post_channel($viewer, $channel) && ws_interact_ready()),
            );
        } else {
            // The count still includes it; the reader only cannot open it.
            $detail['message'] = array('id' => 0, 'channel' => '', 'author' => '', 'count' => count(ws_checklist_items($message['body'])), 'url' => '', 'html' => '');
        }
    }

    return $detail;
}

/**
 * The tasks that carry the checklists of a page of messages, for the badge
 * under each list.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message id => [ [id, number, title, progress, open, url] ]
 */
function ws_checklist_tasks_map($viewer, $message_ids)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_task_work_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_tasks WHERE checklist_message_id IN (" . implode(',', $message_ids) . ") ORDER BY id");
    $assignees = ws_task_assignees_map(array_map(function ($row) { return (int) $row['id']; }, $rows));
    $out = array();

    foreach ($rows as $task) {
        if (!ws_can_see_task($viewer, $task, $assignees[(int) $task['id']] ?? array())) {
            continue;
        }

        $out[(int) $task['checklist_message_id']][] = array(
            'id'       => (int) $task['id'],
            'number'   => ws_task_number($task['id']),
            'title'    => (string) $task['title'],
            'progress' => ws_task_progress($task),
            'open'     => ws_task_is_open($task['status']),
            'status'   => ws_task_status_label($task['status']),
        );
    }

    return $out;
}

/**
 * Checks the message a new task is to carry the checklist of.
 *
 * @param array $viewer
 * @param int   $message_id
 * @return array|string the message, or why not
 */
function ws_checklist_message_for_task($viewer, $message_id)
{
    $message = ws_message((int) $message_id);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        return lang('That message could not be found.');
    }

    if (empty(ws_checklist_items($message['body']))) {
        return lang('That message has no checklist.');
    }

    return $message;
}

/* ---------------------------------------------------------------------------
   Notes
   --------------------------------------------------------------------------- */

/**
 * @param int $note_id
 * @return array|null
 */
function ws_task_note($note_id)
{
    if (!ws_task_work_ready()) {
        return null;
    }

    $note = db_item("SELECT * FROM ws_task_notes WHERE id = '" . (int) $note_id . "'");

    return is_array($note) ? $note : null;
}

/**
 * A task's notes, oldest first, as the drawer draws them.
 *
 * @param array $viewer
 * @param array $task
 * @return array[]
 */
function ws_task_notes($viewer, $task)
{
    if (!ws_task_work_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_task_notes
        WHERE task_id = '" . (int) $task['id'] . "' AND deleted_at = 0
        ORDER BY created_at, id
        LIMIT 500");

    $tokens = array();
    $user_ids = array();

    foreach ($rows as $row) {
        $tokens = array_merge($tokens, ws_tokens($row['body']));

        if ($row['sender_kind'] === 'user') {
            $user_ids[] = (int) $row['sender_id'];
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people($user_ids);
    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_task_note_payload($viewer, $row, $refs, $people);
    }

    return $out;
}

/**
 * One note as the drawer draws it.
 *
 * @param array $viewer
 * @param array $row
 * @param array $refs
 * @param array $people
 * @return array
 */
function ws_task_note_payload($viewer, $row, $refs, $people)
{
    $mine = ($row['sender_kind'] === 'user') && ((int) $row['sender_id'] === (int) $viewer['id']);
    $sender = ($row['sender_kind'] === 'user')
        ? ($people[(int) $row['sender_id']] ?? array('id' => (int) $row['sender_id'], 'name' => ws_person_name($row['sender_id']), 'avatar' => ''))
        : ws_app_sender((int) $row['sender_id']);

    $labels = array();

    foreach (ws_tokens($row['body']) as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
        }
    }

    return array(
        'id'         => (int) $row['id'],
        'sender'     => $sender,
        'html'       => ws_render_body($row['body'], $refs),
        'raw'        => $mine ? (string) $row['body'] : '',
        'labels'     => $mine ? $labels : array(),
        'time'       => ws_time_label($row['created_at']),
        'timestamp'  => (int) $row['created_at'],
        'edited'     => ((int) $row['edited_at'] > 0),
        'can_edit'   => $mine,
        'can_delete' => $mine || ((int) $viewer['role'] < 3),
    );
}

/**
 * Keeps a task's note count, and moves updated_at so an open channel draws
 * the task's card again.
 *
 * @param int $task_id
 */
function ws_task_notes_recount($task_id)
{
    db("UPDATE ws_tasks SET
            notes_count = (SELECT COUNT(*) FROM ws_task_notes WHERE task_id = '" . (int) $task_id . "' AND deleted_at = 0),
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $task_id . "'");
}

/**
 * The records a note tags join the task's own tags, so the record's
 * Workspace drawer finds the task. Tags already there stay as they are.
 *
 * @param array $task
 * @param array $tokens
 */
function ws_task_refs_add($task, $tokens)
{
    $have = array();

    foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'task' AND source_id = '" . (int) $task['id'] . "'") as $ref) {
        $have[$ref['ref_type'] . ':' . (int) $ref['ref_id']] = true;
    }

    $values = array();

    foreach ($tokens as $token) {
        $key = $token['type'] . ':' . (int) $token['id'];

        if (($token['sigil'] !== '#') || isset($have[$key]) || ((int) $token['id'] <= 0)) {
            continue;
        }

        $have[$key] = true;
        $values[] = "('task', '" . (int) $task['id'] . "', '" . (int) $task['channel_id'] . "', '" . e($token['type']) . "', '" . (int) $token['id'] . "', '" . time() . "')";
    }

    if (!empty($values)) {
        db("INSERT INTO ws_refs (source_type, source_id, channel_id, ref_type, ref_id, created_at) VALUES " . implode(', ', $values));
    }
}

/**
 * Tells the people a note mentions, when they can see the task.
 *
 * @param array $viewer
 * @param array $task
 * @param array $tokens
 */
function ws_task_note_notify($viewer, $task, $tokens)
{
    $people = array();

    foreach ($tokens as $token) {
        if ($token['type'] === 'user') {
            $people[(int) $token['id']] = true;
        } elseif ($token['type'] === 'dept') {
            foreach (ws_department_member_ids($token['id']) as $user_id) {
                $people[(int) $user_id] = true;
            }
        }
    }

    unset($people[(int) $viewer['id']]);

    foreach (array_keys($people) as $user_id) {
        if (ws_is_team_member($user_id) && ws_can_see_task(ws_rights_for_id($user_id), $task)) {
            ws_notify($user_id, 'note', array('task_id' => $task['id'], 'actor_id' => $viewer['id']));
        }
    }
}

/**
 * Checks the text of a note.
 *
 * @param string $body
 * @return array ok, error, body
 */
function ws_task_note_clean($body)
{
    $body = ws_tokens_normalise(trim((string) $body));

    if ($body === '') {
        return array('ok' => false, 'error' => lang('The note is empty.'), 'body' => '');
    }

    if (mb_strlen($body) > WS_TASK_NOTE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A note can be at most {var:1} characters long.', 'vars' => WS_TASK_NOTE_MAX)), 'body' => '');
    }

    return array('ok' => true, 'error' => '', 'body' => $body);
}

/**
 * Adds an item to a task's own checklist, from the list in its drawer: a
 * "- [ ]" line after the last item of the description, or at its end when it
 * has none yet. The description is saved the usual way (ws_task_update()),
 * so the ticks keep their items and the progress is counted again.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $text
 * @return array ok, error
 */
function ws_task_item_add($viewer, $task, $text)
{
    if (!ws_task_work_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (!ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'));
    }

    $text = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $text)), 0, 500);

    if ($text === '') {
        return array('ok' => false, 'error' => lang('Write the item first.'));
    }

    $lines = preg_split('/\r\n|\r|\n/', (string) $task['description']);
    $last = -1;
    $code = false;

    foreach ($lines as $index => $line) {
        if ($code) {
            $code = !ws_code_fence_close($line);
            continue;
        }

        if (ws_code_fence_open($line) !== null) {
            $code = true;
            continue;
        }

        if (preg_match('/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u', $line)) {
            $last = $index;
        }
    }

    $item = '- [ ] ' . $text;

    if ($last >= 0) {
        array_splice($lines, $last + 1, 0, array($item));
    } else {
        $body = rtrim(implode("\n", $lines));
        $lines = ($body === '') ? array($item) : array($body, '', $item);
    }

    $description = implode("\n", $lines);

    if (mb_strlen($description) > 8000) {
        return array('ok' => false, 'error' => lang('The description is full: the item does not fit.'));
    }

    $result = ws_task_update($viewer, $task, array('description' => $description));

    return array('ok' => (bool) $result['ok'], 'error' => (string) ($result['error'] ?? ''));
}

/**
 * Adds a note to a task. Whoever can see the task may.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $body
 * @param int    $app_id an application writing for its owner (0 = the person)
 * @return array ok, error, note_id
 */
function ws_task_note_add($viewer, $task, $body, $app_id = 0)
{
    if (!ws_task_work_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'note_id' => 0);
    }

    if (!ws_can_see_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('That task could not be found.'), 'note_id' => 0);
    }

    $clean = ws_task_note_clean($body);

    if (!$clean['ok']) {
        return $clean + array('note_id' => 0);
    }

    db("INSERT INTO ws_task_notes (task_id, sender_kind, sender_id, body, created_at)
        VALUES ('" . (int) $task['id'] . "', '" . (($app_id > 0) ? 'app' : 'user') . "', '" . (($app_id > 0) ? (int) $app_id : (int) $viewer['id']) . "',
            '" . e($clean['body']) . "', '" . time() . "')");

    $note_id = (int) mysqli_insert_id(db::$con);
    $tokens = ws_tokens($clean['body']);

    ws_task_notes_recount($task['id']);
    ws_task_refs_add($task, $tokens);
    ws_task_note_notify($viewer, $task, $tokens);

    // The words go along only where a message's would: for a task of a
    // private channel the receiver learns that a note came, not what it says.
    if (function_exists('pg_announce')) {
        $channel = ((int) $task['channel_id'] > 0) ? ws_channel($task['channel_id']) : null;
        $public = !$channel || ((string) $channel['kind'] === 'public');

        pg_announce('workspace.task.note_added', array(
            'id'        => $note_id,
            'task_id'   => (int) $task['id'],
            'task'      => ws_task_event_payload($task, ws_task_assignee_ids($task['id'])),
            'author_id' => ($app_id > 0) ? 0 : (int) $viewer['id'],
            'app_id'    => (int) $app_id,
            'text'      => $public ? ws_plain_text($viewer, $clean['body']) : null,
        ));
    }

    return array('ok' => true, 'error' => '', 'note_id' => $note_id);
}

/**
 * Changes the text of one's own note. Only the people the edit newly mentions
 * are told.
 *
 * @param array  $viewer
 * @param array  $task
 * @param array  $note
 * @param string $body
 * @return array ok, error
 */
function ws_task_note_edit($viewer, $task, $note, $body)
{
    if (($note['sender_kind'] !== 'user') || ((int) $note['sender_id'] !== (int) $viewer['id']) || ((int) $note['deleted_at'] > 0)) {
        return array('ok' => false, 'error' => lang('You can only change your own notes.'));
    }

    $clean = ws_task_note_clean($body);

    if (!$clean['ok']) {
        return $clean;
    }

    $before = array();

    foreach (ws_tokens($note['body']) as $token) {
        $before[$token['type'] . ':' . $token['id']] = true;
    }

    $tokens = ws_tokens($clean['body']);
    $new = array_values(array_filter($tokens, function ($token) use ($before) {
        return !isset($before[$token['type'] . ':' . $token['id']]);
    }));

    db("UPDATE ws_task_notes SET body = '" . e($clean['body']) . "', edited_at = '" . time() . "' WHERE id = '" . (int) $note['id'] . "'");

    ws_task_notes_recount($task['id']);
    ws_task_refs_add($task, $tokens);
    ws_task_note_notify($viewer, $task, $new);

    return array('ok' => true, 'error' => '');
}

/**
 * Deletes a note: its writer may, and so may a manager.
 *
 * @param array $viewer
 * @param array $task
 * @param array $note
 * @return array ok, error
 */
function ws_task_note_delete($viewer, $task, $note)
{
    $mine = ($note['sender_kind'] === 'user') && ((int) $note['sender_id'] === (int) $viewer['id']);

    if (!$mine && ((int) $viewer['role'] >= 3)) {
        return array('ok' => false, 'error' => lang('You can only change your own notes.'));
    }

    db("UPDATE ws_task_notes SET deleted_at = '" . time() . "' WHERE id = '" . (int) $note['id'] . "' AND deleted_at = 0");

    ws_task_notes_recount($task['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * A message whose checklist tasks carry is being deleted: the list, ticked as
 * it is now, is written into the end of each task's description, so the
 * tasks keep their items and their progress.
 *
 * @param array $message
 */
function ws_checklist_tasks_detach($message)
{
    if (!ws_task_work_ready()) {
        return;
    }

    $task_ids = array_map('intval', (array) db_values("SELECT id FROM ws_tasks WHERE checklist_message_id = '" . (int) $message['id'] . "'"));

    if (empty($task_ids)) {
        return;
    }

    $items = ws_checklist_items($message['body']);
    $ticks = array();

    if (ws_interact_ready()) {
        foreach ((array) db_items("SELECT item, checked FROM ws_checks WHERE message_id = '" . (int) $message['id'] . "'") as $row) {
            $ticks[(int) $row['item']] = ((int) $row['checked'] === 1);
        }
    }

    $lines = array();

    foreach ($items as $item) {
        $done = array_key_exists($item['index'], $ticks) ? $ticks[$item['index']] : $item['checked'];
        $lines[] = '- [' . ($done ? 'x' : ' ') . '] ' . $item['text'];
    }

    foreach ($task_ids as $task_id) {
        $task = ws_task($task_id);

        if (!$task) {
            continue;
        }

        $description = rtrim((string) $task['description']);
        $description = mb_substr((($description !== '') ? $description . "\n\n" : '') . implode("\n", $lines), 0, 8000);

        db("UPDATE ws_tasks SET description = '" . e($description) . "', checklist_message_id = 0 WHERE id = '" . (int) $task_id . "'");
        ws_task_progress_refresh($task_id);
    }
}
