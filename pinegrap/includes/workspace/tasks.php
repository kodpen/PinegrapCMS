<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - tasks: a piece of work with the people on it, a department,
 * dates, an estimate and the records it is about.
 *
 * A task can have several people on it. The estimate is each person's share
 * of the effort, not a sum to divide between them: three people spending an
 * afternoon on a stand at a fair is an afternoon on each of their boards.
 *
 * Every write goes through the functions here - the screens, the commands
 * typed into a channel and the external API alike - so the notifications, the
 * reverse index of tags and the announced events are the same whichever door
 * the change came through.
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
 * The task number people say and type: "G-42" in Turkish, "T-42" otherwise.
 *
 * @param int $task_id
 * @return string
 */
function ws_task_number($task_id)
{
    return lang('T') . '-' . (int) $task_id;
}

/**
 * @return array status => label
 */
function ws_task_statuses()
{
    return array(
        'todo'      => lang('To do'),
        'doing'     => lang('In progress'),
        'waiting'   => lang('Waiting'),
        'done'      => lang('Done'),
        'cancelled' => lang('Cancelled'),
    );
}

/**
 * @param string $status
 * @return string
 */
function ws_task_status_label($status)
{
    $statuses = ws_task_statuses();

    return $statuses[$status] ?? (string) $status;
}

/**
 * @return array priority => label
 */
function ws_task_priorities()
{
    return array(
        'low'    => lang('Low'),
        'normal' => lang('Normal'),
        'high'   => lang('High'),
        'urgent' => lang('Urgent'),
    );
}

/**
 * Is the task still on somebody's plate?
 *
 * @param string $status
 * @return bool
 */
function ws_task_is_open($status)
{
    return in_array($status, array('todo', 'doing', 'waiting'), true);
}

/**
 * One task row.
 *
 * @param int $task_id
 * @return array|null
 */
function ws_task($task_id)
{
    $row = db_item("SELECT * FROM ws_tasks WHERE id = '" . (int) $task_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The people on a set of tasks, in one query.
 *
 * @param int[] $task_ids
 * @return array task id => int[] user ids
 */
function ws_task_assignees_map($task_ids)
{
    $ids = array();

    foreach ((array) $task_ids as $task_id) {
        if ((int) $task_id > 0) {
            $ids[(int) $task_id] = (int) $task_id;
        }
    }

    $out = array();

    if (empty($ids)) {
        return $out;
    }

    foreach ((array) db_items("SELECT task_id, user_id FROM ws_task_assignees
        WHERE task_id IN (" . implode(',', $ids) . ") ORDER BY assigned_at, user_id") as $row) {
        $out[(int) $row['task_id']][] = (int) $row['user_id'];
    }

    return $out;
}

/**
 * A due date as a person reads it: "Today", "Tomorrow", "Fri 26.09", and
 * "overdue" when it has passed.
 *
 * @param array $task
 * @return string
 */
function ws_task_due_label($task)
{
    $due = (string) ($task['due_date'] ?? '');

    if (($due === '') || ($due === '0000-00-00')) {
        return lang('No due date');
    }

    $today = date('Y-m-d');

    if ($due === $today) {
        $label = lang('Today');
    } elseif ($due === date('Y-m-d', strtotime('+1 day'))) {
        $label = lang('Tomorrow');
    } else {
        $label = ws_day_label($due);
    }

    if (($due < $today) && ws_task_is_open($task['status'] ?? 'todo')) {
        $label = lang(array('string' => '{var:1} (overdue)', 'vars' => $label));
    }

    return $label;
}

/**
 * The shape a task has in lists, on the board and in the API.
 *
 * @param array $task
 * @param int[] $assignees
 * @return array
 */
function ws_task_brief($task, $assignees)
{
    $people = ws_people($assignees);
    $due = (string) ($task['due_date'] ?? '');
    $start = (string) ($task['start_date'] ?? '');

    return array(
        'id'               => (int) $task['id'],
        'number'           => ws_task_number($task['id']),
        'title'            => (string) $task['title'],
        'status'           => (string) $task['status'],
        'status_label'     => ws_task_status_label($task['status']),
        'priority'         => (string) $task['priority'],
        'open'             => ws_task_is_open($task['status']),
        'start_date'       => ($start !== '' && $start !== '0000-00-00') ? $start : null,
        'due_date'         => ($due !== '' && $due !== '0000-00-00') ? $due : null,
        'due_label'        => ws_task_due_label($task),
        'overdue'          => ($due !== '') && ($due < date('Y-m-d')) && ws_task_is_open($task['status']),
        'estimate_minutes' => (int) $task['estimate_minutes'],
        'estimate_label'   => ((int) $task['estimate_minutes'] > 0) ? ws_minutes_label($task['estimate_minutes']) : '',
        'department_id'    => (int) $task['department_id'],
        'channel_id'       => (int) $task['channel_id'],
        'creator_id'       => (int) $task['creator_id'],
        // A copy of a repeating task (includes/workspace/recurrence.php).
        'recurring'        => ((int) ($task['recurrence_id'] ?? 0) > 0),
        'assignees'        => array_values($people),
        'url'              => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_tasks.php?task=' . (int) $task['id'],
        'updated_at'       => (int) $task['updated_at'],
        'progress'         => ws_task_progress($task),
        'notes_count'      => (int) ($task['notes_count'] ?? 0),
    );
}

/**
 * The full shape of one task, for its drawer: the brief plus the text, the
 * tags, the channel and what the reader may do with it.
 *
 * @param array $viewer
 * @param array $task
 * @return array
 */
function ws_task_detail($viewer, $task)
{
    $assignees = ws_task_assignee_ids($task['id']);
    $detail = ws_task_brief($task, $assignees);

    $tokens = array();

    foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'task' AND source_id = '" . (int) $task['id'] . "'") as $ref) {
        $tokens[] = array('sigil' => in_array($ref['ref_type'], array('user', 'dept'), true) ? '@' : '#', 'type' => $ref['ref_type'], 'id' => (int) $ref['ref_id']);
    }

    $description = (string) $task['description'];
    $refs = ws_refs_resolve($viewer, array_merge($tokens, ws_tokens($description)));

    $detail['description'] = $description;
    $detail['description_html'] = ws_render_body($description, $refs);

    // The names of the tags in the description, for the drawer's formatted
    // box to show them as chips again.
    $detail['description_labels'] = array();

    foreach (ws_tokens($description) as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $detail['description_labels']['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
        }
    }
    $detail['refs'] = array();

    foreach ($tokens as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $detail['refs'][] = $refs[$key] + array('token' => '<' . $token['sigil'] . $key . '>', 'html' => ws_chip_html($refs[$key]));
        }
    }

    $channel = ((int) $task['channel_id'] > 0) ? ws_channel($task['channel_id']) : null;

    $detail['channel'] = ($channel && ws_can_read_channel($viewer, $channel))
        ? array('id' => (int) $channel['id'], 'name' => $channel['name'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel['id'] . '&message=' . (int) $task['source_message_id'])
        : null;

    $department = ((int) $task['department_id'] > 0) ? ws_department($task['department_id']) : null;

    $detail['department'] = $department ? array('id' => (int) $department['id'], 'name' => $department['name'], 'color' => $department['color']) : null;
    $detail['creator'] = ws_person_name($task['creator_id']);
    $detail['created_label'] = ws_time_label($task['created_at']);
    $detail['completed_label'] = ((int) $task['completed_at'] > 0) ? ws_time_label($task['completed_at']) : '';
    $detail['can_edit'] = ws_can_edit_task($viewer, $task, $assignees);
    $detail['work'] = ws_task_work_ready();
    $detail['checklist'] = ws_task_checklist_detail($viewer, $task, $detail['can_edit']);
    $detail['notes'] = ws_task_notes($viewer, $task);
    $detail['recurrence'] = function_exists('ws_recurrence_detail') ? ws_recurrence_detail($task) : null;

    return $detail;
}

/**
 * A date the caller typed, as Y-m-d, or null.
 *
 * @param mixed $value
 * @return string|null
 */
function ws_date_or_null($value)
{
    $value = trim((string) $value);

    if (($value === '') || ($value === '0000-00-00')) {
        return null;
    }

    if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $match) && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
        return $value;
    }

    if (preg_match('/^([0-9]{1,2})[.\/]([0-9]{1,2})[.\/]([0-9]{4})$/', $value, $match) && checkdate((int) $match[2], (int) $match[1], (int) $match[3])) {
        return sprintf('%04d-%02d-%02d', $match[3], $match[2], $match[1]);
    }

    return false;
}

/**
 * Checks and cleans the fields of a task being written. What is not in $data
 * is not touched - the same function serves a create and a partial update.
 *
 * @param array $viewer
 * @param array $data
 * @param array|null $current the task as it is, for an update
 * @return array ok, error, field, clean
 */
function ws_task_validate($viewer, $data, $current = null)
{
    $clean = array();
    $fail = function ($message, $field) {
        return array('ok' => false, 'error' => $message, 'field' => $field, 'clean' => array());
    };

    if (array_key_exists('title', $data) || ($current === null)) {
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($data['title'] ?? '')));

        if ($title === '') {
            return $fail(lang('A task needs a title.'), 'title');
        }

        $clean['title'] = mb_substr($title, 0, 255);
    }

    if (array_key_exists('description', $data)) {
        $clean['description'] = mb_substr(ws_tokens_normalise(trim((string) $data['description'])), 0, 8000);
    }

    if (array_key_exists('status', $data)) {
        if (!isset(ws_task_statuses()[$data['status']])) {
            return $fail(lang('That is not a task status.'), 'status');
        }

        $clean['status'] = $data['status'];
    }

    if (array_key_exists('priority', $data)) {
        if (!isset(ws_task_priorities()[$data['priority']])) {
            return $fail(lang('That is not a priority.'), 'priority');
        }

        $clean['priority'] = $data['priority'];
    }

    foreach (array('start_date', 'due_date') as $field) {
        if (array_key_exists($field, $data)) {
            $date = ws_date_or_null($data[$field]);

            if ($date === false) {
                return $fail(lang('Write the date as day.month.year.'), $field);
            }

            $clean[$field] = $date;
        }
    }

    $start = array_key_exists('start_date', $clean) ? $clean['start_date'] : (($current !== null) ? $current['start_date'] : null);
    $due = array_key_exists('due_date', $clean) ? $clean['due_date'] : (($current !== null) ? $current['due_date'] : null);

    if (($start !== null) && ($due !== null) && ($start > $due)) {
        return $fail(lang('The task cannot start after it is due.'), 'start_date');
    }

    if (array_key_exists('estimate_minutes', $data)) {
        $clean['estimate_minutes'] = max(0, min(100000, (int) $data['estimate_minutes']));
    }

    if (array_key_exists('department_id', $data)) {
        $department_id = (int) $data['department_id'];

        if (($department_id > 0) && !ws_department($department_id)) {
            return $fail(lang('That department could not be found.'), 'department_id');
        }

        $clean['department_id'] = $department_id;
    }

    if (array_key_exists('channel_id', $data)) {
        $channel_id = (int) $data['channel_id'];

        if ($channel_id > 0) {
            $channel = ws_channel($channel_id);

            if (!ws_can_post_channel($viewer, $channel)) {
                return $fail(lang('You cannot post in that channel.'), 'channel_id');
            }
        }

        $clean['channel_id'] = $channel_id;
    }

    if (array_key_exists('assignees', $data)) {
        $assignees = array();

        foreach ((array) $data['assignees'] as $user_id) {
            $user_id = (int) $user_id;

            if ($user_id <= 0) {
                continue;
            }

            if (!ws_is_team_member($user_id)) {
                return $fail(lang('Tasks can only be given to members of the team.'), 'assignees');
            }

            $assignees[$user_id] = $user_id;
        }

        $clean['assignees'] = array_values($assignees);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'clean' => $clean);
}

/**
 * Creates a task.
 *
 * @param array $viewer
 * @param array $data    title, description, status, priority, start_date, due_date,
 *                       estimate_minutes, department_id, channel_id, source_message_id,
 *                       assignees (ids), refs (tokens)
 * @return array ok, error, field, task_id
 */
function ws_task_create($viewer, $data)
{
    $check = ws_task_validate($viewer, $data);

    if (!$check['ok']) {
        return $check + array('task_id' => 0);
    }

    // A checklist written in a channel that becomes this task.
    $list_message_id = 0;

    if (!empty($data['checklist_message_id']) && ws_task_work_ready()) {
        $list = ws_checklist_message_for_task($viewer, (int) $data['checklist_message_id']);

        if (!is_array($list)) {
            return array('ok' => false, 'error' => $list, 'field' => '', 'task_id' => 0);
        }

        $list_message_id = (int) $list['id'];
    }

    $clean = $check['clean'];
    $assignees = $clean['assignees'] ?? array();

    if (!ws_can_assign_to($viewer, $assignees)) {
        return array('ok' => false, 'error' => lang('You may only give tasks to yourself or to the people in the departments you lead.'), 'field' => 'assignees', 'task_id' => 0);
    }

    $now = time();
    $date = function ($value) {
        return ($value === null) ? 'NULL' : "'" . e($value) . "'";
    };

    db("INSERT INTO ws_tasks (title, description, channel_id, source_message_id, creator_id, department_id,
            status, priority, start_date, due_date, estimate_minutes, created_at, updated_at)
        VALUES (
            '" . e($clean['title']) . "',
            '" . e($clean['description'] ?? '') . "',
            '" . (int) ($clean['channel_id'] ?? 0) . "',
            '" . (int) ($data['source_message_id'] ?? 0) . "',
            '" . (int) $viewer['id'] . "',
            '" . (int) ($clean['department_id'] ?? 0) . "',
            '" . e($clean['status'] ?? 'todo') . "',
            '" . e($clean['priority'] ?? 'normal') . "',
            " . $date($clean['start_date'] ?? null) . ",
            " . $date($clean['due_date'] ?? null) . ",
            '" . (int) ($clean['estimate_minutes'] ?? 0) . "',
            '" . $now . "',
            '" . $now . "')");

    $task_id = (int) mysqli_insert_id(db::$con);

    if ($task_id <= 0) {
        return array('ok' => false, 'error' => lang('The task could not be saved.'), 'field' => '', 'task_id' => 0);
    }

    ws_task_set_assignees($viewer, $task_id, $assignees, true);

    $tokens = array_merge(ws_tokens($clean['description'] ?? ''), ws_task_ref_tokens($data['refs'] ?? array()));
    ws_refs_store('task', $task_id, (int) ($clean['channel_id'] ?? 0), $tokens);

    if ($list_message_id > 0) {
        db("UPDATE ws_tasks SET checklist_message_id = '" . $list_message_id . "' WHERE id = '" . $task_id . "'");
        ws_message_touch($list_message_id);
    }

    ws_task_progress_refresh($task_id);

    $task = ws_task($task_id);

    pg_announce('workspace.task.created', ws_task_event_payload($task, $assignees));

    return array('ok' => true, 'error' => '', 'field' => '', 'task_id' => $task_id);
}

/**
 * Record tokens handed to a task by a caller that picked them separately
 * (the task drawer, the API): only the # kinds, each once.
 *
 * @param array $refs tokens as strings, or [type, id] rows
 * @return array[]
 */
function ws_task_ref_tokens($refs)
{
    $tokens = array();

    foreach ((array) $refs as $ref) {
        if (is_array($ref) && isset($ref['type'], $ref['id'])) {
            $ref = '<#' . $ref['type'] . ':' . (int) $ref['id'] . '>';
        }

        foreach (ws_tokens((string) $ref) as $token) {
            if ($token['sigil'] === '#') {
                $tokens[] = $token;
            }
        }
    }

    return $tokens;
}

/**
 * Replaces the people on a task and tells the ones who were added.
 *
 * @param array $viewer
 * @param int   $task_id
 * @param int[] $user_ids
 * @param bool  $is_new   the task was created in the same request
 * @return int[] the people who were added
 */
function ws_task_set_assignees($viewer, $task_id, $user_ids, $is_new = false)
{
    $task_id = (int) $task_id;
    $before = $is_new ? array() : ws_task_assignee_ids($task_id);
    $after = array_values(array_unique(array_map('intval', (array) $user_ids)));

    $added = array_values(array_diff($after, $before));
    $removed = array_values(array_diff($before, $after));

    if (!empty($removed)) {
        db("DELETE FROM ws_task_assignees WHERE task_id = '" . $task_id . "' AND user_id IN (" . implode(',', $removed) . ")");
    }

    $now = time();

    foreach ($added as $user_id) {
        db("INSERT IGNORE INTO ws_task_assignees (task_id, user_id, assigned_by, assigned_at)
            VALUES ('" . $task_id . "', '" . (int) $user_id . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

        if ((int) $user_id !== (int) $viewer['id']) {
            ws_notify($user_id, 'assigned', array('task_id' => $task_id, 'actor_id' => $viewer['id']));
        }
    }

    if (!empty($added) && !$is_new) {
        $task = ws_task($task_id);

        pg_announce('workspace.task.assigned', ws_task_event_payload($task, $after) + array('added' => $added));
    }

    return $added;
}

/**
 * Changes a task. Only the fields in $data are written.
 *
 * @param array $viewer
 * @param array $task
 * @param array $data
 * @return array ok, error, field, added (people newly on it)
 */
function ws_task_update($viewer, $task, $data)
{
    $assignees = ws_task_assignee_ids($task['id']);

    if (!ws_can_edit_task($viewer, $task, $assignees)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'), 'field' => '', 'added' => array());
    }

    $check = ws_task_validate($viewer, $data, $task);

    if (!$check['ok']) {
        return $check + array('added' => array());
    }

    $clean = $check['clean'];
    $set = array();

    foreach (array('title', 'description', 'priority', 'estimate_minutes', 'department_id', 'channel_id') as $field) {
        if (array_key_exists($field, $clean)) {
            $set[] = $field . " = '" . e($clean[$field]) . "'";
        }
    }

    foreach (array('start_date', 'due_date') as $field) {
        if (array_key_exists($field, $clean)) {
            $set[] = $field . ' = ' . (($clean[$field] === null) ? 'NULL' : "'" . e($clean[$field]) . "'");
        }
    }

    $added = array();

    if (array_key_exists('assignees', $clean)) {
        $new_people = array_values(array_diff($clean['assignees'], $assignees));

        if (!ws_can_assign_to($viewer, $new_people)) {
            return array('ok' => false, 'error' => lang('You may only give tasks to yourself or to the people in the departments you lead.'), 'field' => 'assignees', 'added' => array());
        }

        $added = ws_task_set_assignees($viewer, $task['id'], $clean['assignees']);
    }

    if (!empty($set)) {
        db("UPDATE ws_tasks SET " . implode(', ', $set) . ", updated_at = '" . time() . "' WHERE id = '" . (int) $task['id'] . "'");
    } elseif (!empty($added) || array_key_exists('assignees', $clean)) {
        db("UPDATE ws_tasks SET updated_at = '" . time() . "' WHERE id = '" . (int) $task['id'] . "'");
    }

    // The ticks of the description's checklist follow their items.
    if (array_key_exists('description', $clean) && ($clean['description'] !== (string) $task['description']) && ws_task_work_ready()) {
        ws_checks_remap_in('ws_task_checks', 'task_id', $task['id'], (string) $task['description'], $clean['description']);
        ws_task_progress_refresh($task['id']);
    }

    if (array_key_exists('description', $clean) || array_key_exists('refs', $data)) {
        $tokens = ws_tokens($clean['description'] ?? $task['description']);

        if (array_key_exists('refs', $data)) {
            $tokens = array_merge($tokens, ws_task_ref_tokens($data['refs']));
        } else {
            foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'task' AND source_id = '" . (int) $task['id'] . "'") as $ref) {
                if (!in_array($ref['ref_type'], array('user', 'dept'), true)) {
                    $tokens[] = array('sigil' => '#', 'type' => $ref['ref_type'], 'id' => (int) $ref['ref_id']);
                }
            }
        }

        ws_refs_store('task', $task['id'], (int) ($clean['channel_id'] ?? $task['channel_id']), $tokens);
    }

    if (array_key_exists('status', $clean) && ($clean['status'] !== $task['status'])) {
        ws_task_set_status($viewer, ws_task($task['id']), $clean['status']);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'added' => $added);
}

/**
 * Moves a task to another status and says so where it matters: the person
 * who created it hears that it is done, and the channel it came from gets a
 * line when it is finished.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $status
 * @return array ok, error
 */
function ws_task_set_status($viewer, $task, $status)
{
    if (!isset(ws_task_statuses()[$status])) {
        return array('ok' => false, 'error' => lang('That is not a task status.'));
    }

    if (!ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'));
    }

    if ($status === $task['status']) {
        return array('ok' => true, 'error' => '');
    }

    $now = time();
    $done = ($status === 'done');

    db("UPDATE ws_tasks SET
            status = '" . e($status) . "',
            completed_at = '" . ($done ? $now : 0) . "',
            completed_by = '" . ($done ? (int) $viewer['id'] : 0) . "',
            updated_at = '" . $now . "'
        WHERE id = '" . (int) $task['id'] . "'");

    $assignees = ws_task_assignee_ids($task['id']);
    $updated = ws_task($task['id']);

    // The badge under the channel list the task carries shows the new state.
    if ((int) ($task['checklist_message_id'] ?? 0) > 0) {
        ws_message_touch($task['checklist_message_id']);
    }

    pg_announce('workspace.task.status_changed', ws_task_event_payload($updated, $assignees) + array('previous_status' => $task['status']));

    if ($done) {
        pg_announce('workspace.task.completed', ws_task_event_payload($updated, $assignees));

        if ((int) $task['creator_id'] !== (int) $viewer['id']) {
            ws_notify($task['creator_id'], 'completed', array('task_id' => $task['id'], 'actor_id' => $viewer['id']));
        }

        if ((int) $task['channel_id'] > 0) {
            ws_message_system($task['channel_id'], lang(array(
                'string' => '{var:1} completed {var:2}',
                'vars'   => array('<@user:' . (int) $viewer['id'] . '>', '<#task:' . (int) $task['id'] . '>'),
            )), $task['id']);
        }
    }

    return array('ok' => true, 'error' => '');
}

/**
 * What a task event carries to a webhook receiver.
 *
 * @param array $task
 * @param int[] $assignees
 * @return array
 */
function ws_task_event_payload($task, $assignees)
{
    return array(
        'id'            => (int) $task['id'],
        'title'         => (string) $task['title'],
        'status'        => (string) $task['status'],
        'priority'      => (string) $task['priority'],
        'due_date'      => $task['due_date'] ?: null,
        'department_id' => (int) $task['department_id'],
        'channel_id'    => (int) $task['channel_id'],
        'assignees'     => array_map('intval', (array) $assignees),
        'creator_id'    => (int) $task['creator_id'],
    );
}

/**
 * Tasks for a list, filtered, with only what the reader may see.
 *
 * @param array $viewer
 * @param array $filters scope (mine | created | department | channel | all),
 *                       status (open | done | all | a status), department_id,
 *                       channel_id, from, to (due date range), ref_type, ref_id,
 *                       updated_since, limit
 * @return array[] briefs
 */
function ws_tasks_list($viewer, $filters)
{
    $where = array();
    $join = '';
    $me = (int) $viewer['id'];
    $scope = (string) ($filters['scope'] ?? 'mine');

    switch ($scope) {
        case 'created':
            $where[] = "t.creator_id = '" . $me . "'";
            break;

        case 'department':
            $department_id = (int) ($filters['department_id'] ?? 0);

            if ($department_id > 0) {
                $where[] = "t.department_id = '" . $department_id . "'";
            } else {
                $mine = ws_user_department_ids($me);
                $where[] = empty($mine) ? '0 = 1' : "t.department_id IN (" . implode(',', $mine) . ")";
            }
            break;

        case 'channel':
            $where[] = "t.channel_id = '" . (int) ($filters['channel_id'] ?? 0) . "'";
            break;

        case 'all':
            break;

        default:
            $join = " INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'";
            break;
    }

    $status = (string) ($filters['status'] ?? 'open');

    if ($status === 'open') {
        $where[] = "t.status IN ('todo', 'doing', 'waiting')";
    } elseif ($status === 'closed') {
        $where[] = "t.status IN ('done', 'cancelled')";
    } elseif (isset(ws_task_statuses()[$status])) {
        $where[] = "t.status = '" . e($status) . "'";
    }

    if (!empty($filters['from']) && (ws_date_or_null($filters['from']) !== false)) {
        $where[] = "t.due_date >= '" . e(ws_date_or_null($filters['from'])) . "'";
    }

    if (!empty($filters['to']) && (ws_date_or_null($filters['to']) !== false)) {
        $where[] = "t.due_date <= '" . e(ws_date_or_null($filters['to'])) . "'";
    }

    if (!empty($filters['ref_type']) && !empty($filters['ref_id'])) {
        $join .= " INNER JOIN ws_refs r ON r.source_type = 'task' AND r.source_id = t.id
            AND r.ref_type = '" . e($filters['ref_type']) . "' AND r.ref_id = '" . (int) $filters['ref_id'] . "'";
    }

    if (!empty($filters['updated_since'])) {
        $where[] = "t.updated_at >= '" . (int) $filters['updated_since'] . "'";
    }

    if (!empty($filters['search'])) {
        $where[] = "t.title LIKE '%" . e(escape_like((string) $filters['search'])) . "%'";
    }

    $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));

    $rows = (array) db_items("SELECT DISTINCT t.* FROM ws_tasks t" . $join
        . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where))
        . " ORDER BY (t.status IN ('done', 'cancelled')), t.due_date IS NULL, t.due_date, FIELD(t.priority, 'urgent', 'high', 'normal', 'low'), t.id DESC
        LIMIT " . ($limit * 2));

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $assignees = ws_task_assignees_map($ids);
    $out = array();

    foreach ($rows as $row) {
        $task_id = (int) $row['id'];

        if (!ws_can_see_task($viewer, $row, $assignees[$task_id] ?? array())) {
            continue;
        }

        $brief = ws_task_brief($row, $assignees[$task_id] ?? array());
        $brief['can_edit'] = ws_can_edit_task($viewer, $row, $assignees[$task_id] ?? array());
        $out[] = $brief;

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * A task as a card inside a channel: title, people, due date and the buttons
 * that move it along, drawn for this reader.
 *
 * @param array $viewer
 * @param array $task
 * @param int[] $assignees
 * @return string
 */
function ws_task_card_html($viewer, $task, $assignees)
{
    if (!ws_can_see_task($viewer, $task, $assignees)) {
        return '<div class="ws-task-card ws-task-card-hidden">' . h(ws_task_number($task['id'])) . '</div>';
    }

    $brief = ws_task_brief($task, $assignees);
    $can_edit = ws_can_edit_task($viewer, $task, $assignees);

    $people = '';

    foreach ($brief['assignees'] as $person) {
        $people .= '<span class="ws-task-person" title="' . h($person['name']) . '"><img src="' . h($person['avatar']) . '" alt="" loading="lazy" />' . h($person['name']) . '</span>';
    }

    if ($people === '') {
        $people = '<span class="text-body-secondary">' . h(lang('Nobody yet')) . '</span>';
    }

    $progress = '';

    if ($brief['progress'] !== null) {
        $share = $brief['progress'];

        $progress = '<div class="ws-task-progress' . ($share['complete'] ? ' ws-task-progress-full' : '') . '" title="' . h(lang(array('string' => '{var:1} of {var:2} items done', 'vars' => array($share['done'], $share['total'])))) . '">'
            . '<div class="progress" role="progressbar" aria-valuenow="' . (int) $share['percent'] . '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: ' . (int) $share['percent'] . '%"></div></div>'
            . '<span>' . h($share['done'] . '/' . $share['total'] . ' · ' . lang(array('string' => '{var:1}%', 'vars' => $share['percent']))) . '</span>'
            . '</div>';

        if ($share['complete'] && $brief['open']) {
            $progress .= '<div class="ws-task-progress-hint"><i class="bi bi-check2-all me-1"></i>' . h(lang('Every item is done.')) . '</div>';
        }
    }

    if ((int) ($task['notes_count'] ?? 0) > 0) {
        $progress .= '<div class="ws-task-card-notes"><i class="bi bi-journal-text me-1"></i>' . h(lang(array('string' => '{var:1} notes', 'vars' => (int) $task['notes_count']))) . '</div>';
    }

    $buttons = '';

    if ($can_edit && $brief['open']) {
        if ($task['status'] === 'todo') {
            $buttons .= '<button type="button" class="btn btn-sm btn-ghost" data-ws-task-status="doing" data-ws-task="' . (int) $task['id'] . '"><i class="bi bi-play-circle me-1"></i>' . h(lang('Start work')) . '</button>';
        }

        $buttons .= '<button type="button" class="btn btn-sm btn-outline-success" data-ws-task-status="done" data-ws-task="' . (int) $task['id'] . '"><i class="bi bi-check2-circle me-1"></i>' . h(lang('Mark done')) . '</button>';
    }

    $buttons .= '<button type="button" class="btn btn-sm btn-ghost" data-ws-task-open="' . (int) $task['id'] . '"><i class="bi bi-box-arrow-up-right me-1"></i>' . h(lang('Open task')) . '</button>';

    return '<div class="ws-task-card ws-task-' . h($task['status']) . ' ws-priority-' . h($task['priority']) . '" data-ws-task-card="' . (int) $task['id'] . '" data-ws-status="' . h($task['status']) . '" data-ws-due="' . h((string) $brief['due_date']) . '" data-ws-updated="' . (int) $task['updated_at'] . '">
            <div class="ws-task-card-head">
                <span class="ws-task-number">' . h($brief['number']) . '</span>
                <span class="ws-task-status badge">' . h($brief['status_label']) . '</span>
                ' . (($task['priority'] === 'urgent' || $task['priority'] === 'high') ? '<span class="ws-task-priority badge">' . h(ws_task_priorities()[$task['priority']]) . '</span>' : '') . '
            </div>
            <div class="ws-task-card-title">' . h($brief['title']) . '</div>
            <div class="ws-task-card-meta">
                <span class="' . ($brief['overdue'] ? 'text-danger' : '') . '"><i class="bi bi-calendar-event me-1"></i>' . h($brief['due_label']) . '</span>
                ' . (($brief['estimate_label'] !== '') ? '<span><i class="bi bi-hourglass-split me-1"></i>' . h($brief['estimate_label']) . '</span>' : '') . '
                ' . ($brief['recurring'] ? '<span title="' . h(lang('Repeating task')) . '"><i class="bi bi-arrow-repeat"></i></span>' : '') . '
            </div>
            ' . $progress . '
            <div class="ws-task-card-people">' . $people . '</div>
            <div class="ws-task-card-actions">' . $buttons . '</div>
        </div>';
}
