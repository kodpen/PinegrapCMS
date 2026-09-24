<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API's handlers and presenters.
 *
 * Every handler works as the application's owner: the reader is built from
 * the owner row the API loaded, and the same access functions the panel uses
 * decide what it may see and do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The reader behind the current application: its owner.
 *
 * @return array
 */
function ws_api_viewer()
{
    static $viewer = null;

    if ($viewer === null) {
        $app = api_current_app();
        $viewer = ws_viewer((array) ($app['owner'] ?? array()));
    }

    if (!$viewer['member']) {
        api_fail(403, 'forbidden', lang('The owner of this application is not a member of the workspace.'));
    }

    return $viewer;
}

/**
 * The id of the current application.
 *
 * @return int
 */
function ws_api_app_id()
{
    $app = api_current_app();

    return (int) ($app['id'] ?? 0);
}

/**
 * The line the panel's own screens leave in the activity log for a write.
 *
 * @param string $what
 */
function ws_api_log($what)
{
    $app = api_current_app();

    if (function_exists('log_activity')) {
        log_activity($what . ' (' . lang('API') . ': ' . (string) ($app['name'] ?? '') . ')', (string) ($app['owner']['username'] ?? ''));
    }
}

/* ---------------------------------------------------------------------------
   Channels
   --------------------------------------------------------------------------- */

function ws_api_channel_present($brief)
{
    return array(
        'id'            => (int) $brief['id'],
        'name'          => (string) $brief['name'],
        'kind'          => (string) $brief['kind'],
        'topic'         => (string) $brief['topic'],
        'joined'        => (bool) $brief['joined'],
        'contact_id'    => ((int) $brief['contact_id'] > 0) ? (int) $brief['contact_id'] : null,
        'department_id' => ((int) $brief['department_id'] > 0) ? (int) $brief['department_id'] : null,
        'archived'      => (bool) $brief['archived'],
        'unread'        => (int) $brief['unread'],
        'last_message_at' => ((int) $brief['last_message_at'] > 0) ? api_time($brief['last_message_at']) : null,
    );
}

// What ws_api_channel_present() returns.
function ws_api_channel_schema()
{
    return array(
        'id'              => 'integer',
        'name'            => 'string',
        'kind'            => 'string',
        'topic'           => 'string',
        'joined'          => 'boolean',
        'contact_id'      => 'integer?',
        'department_id'   => 'integer?',
        'archived'        => 'boolean',
        'unread'          => 'integer',
        'last_message_at' => 'string?',
    );
}

function ws_api_channels_list($params)
{
    $viewer = ws_api_viewer();
    $out = array();

    foreach (ws_channels_for($viewer, !empty($params['archived'])) as $brief) {
        if (isset($params['contact_id']) && ((int) $brief['contact_id'] !== (int) $params['contact_id'])) {
            continue;
        }

        if (ws_api_is_claude() && !ws_claude_channel_allowed(ws_channel($brief['id']))) {
            continue;
        }

        $out[] = ws_api_channel_present($brief);
    }

    api_ok_list($out, count($out));
}

/* ---------------------------------------------------------------------------
   Messages
   --------------------------------------------------------------------------- */

function ws_api_message_present($viewer, $row, $refs, $reactions = null)
{
    if ($reactions === null) {
        $reactions = ws_reactions_map(array($row['id']), $viewer);
    }

    $emoji = array();

    foreach ($reactions[(int) $row['id']] ?? array() as $group) {
        $emoji[] = array('emoji' => $group['emoji'], 'count' => (int) $group['count']);
    }

    $sender = null;

    if ($row['sender_kind'] === 'user') {
        $people = ws_people(array($row['sender_id']));
        $sender = isset($people[(int) $row['sender_id']]) ? array('user_id' => (int) $row['sender_id'], 'name' => $people[(int) $row['sender_id']]['name']) : null;
    } elseif ($row['sender_kind'] === 'app') {
        $app = ws_app_sender((int) $row['sender_id']);
        $sender = array('user_id' => null, 'name' => $app['name']);
    }

    $deleted = ((int) $row['deleted_at'] > 0);
    $file = null;

    if (!$deleted && ((int) $row['file_id'] > 0)) {
        $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $row['file_id'] . "'");

        if ($stored !== '') {
            $file = array('name' => (string) $row['file_name'], 'url' => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($stored));
        }
    }

    return array(
        'id'          => (int) $row['id'],
        'channel_id'  => (int) $row['channel_id'],
        'kind'        => (string) $row['kind'],
        'sender_kind' => (string) $row['sender_kind'],
        'sender'      => $sender,
        'text'        => $deleted ? '' : ws_plain_text($viewer, $row['body'], $refs),
        'body'        => $deleted ? '' : (string) $row['body'],
        'task_id'     => ((int) $row['task_id'] > 0) ? (int) $row['task_id'] : null,
        'file'        => $file,
        'deleted'     => $deleted,
        'edited'      => ((int) $row['edited_at'] > 0),
        'reactions'   => $deleted ? array() : $emoji,
        'created_at'  => api_time($row['created_at']),
    );
}

// What ws_api_message_present() returns.
function ws_api_message_schema()
{
    return array(
        'id'          => 'integer',
        'channel_id'  => 'integer',
        'kind'        => 'string',
        'sender_kind' => 'string',
        'sender'      => array('user_id' => 'integer?', 'name' => 'string'),
        'text'        => 'string',
        'body'        => 'string',
        'task_id'     => 'integer?',
        'file'        => array('name' => 'string', 'url' => 'string'),
        'deleted'     => 'boolean',
        'edited'      => 'boolean',
        'reactions'   => array(array('emoji' => 'string', 'count' => 'integer')),
        'created_at'  => 'string',
    );
}

function ws_api_channel_or_404($viewer, $id)
{
    $channel = ws_channel((int) $id);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        api_fail_not_found(lang('Channel'));
    }

    // The application Claude works through reads only where Claude may be
    // asked: a private channel's words leave the site only when its people
    // allowed it.
    if (ws_api_is_claude() && !ws_claude_channel_allowed($channel)) {
        api_fail(403, 'forbidden', lang('Claude may not be asked in this channel.'));
    }

    return $channel;
}

function ws_api_messages_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $limit = (int) ($params['limit'] ?? 50);

    if (!empty($params['since_id'])) {
        $rows = array_slice(ws_messages_since($channel['id'], (int) $params['since_id']), 0, $limit);
    } else {
        $rows = ws_messages_page($channel['id'], (int) ($params['before_id'] ?? 0), $limit);
    }

    $tokens = array();

    foreach ($rows as $row) {
        $tokens = array_merge($tokens, ws_tokens($row['body']));
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $reactions = ws_reactions_map($ids, $viewer);
    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_api_message_present($viewer, $row, $refs, $reactions);
    }

    api_ok_list($out, $limit);
}

function ws_api_messages_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $result = ws_message_send($viewer, $channel, (string) $params['text'], array(
        'kind'   => (string) ($params['kind'] ?? 'message'),
        'app_id' => ws_api_app_id(),
    ));

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'text');
    }

    $row = ws_message($result['message_id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), 201);
}

function ws_api_messages_react($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    $on = !array_key_exists('on', $params) || !empty($params['on']);
    $result = ws_reaction_set($viewer, $message, (string) $params['emoji'], ws_api_app_id(), $on);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'emoji');
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

/* ---------------------------------------------------------------------------
   Tasks
   --------------------------------------------------------------------------- */

function ws_api_task_present($viewer, $task, $warnings = array())
{
    $assignees = ws_task_assignee_ids($task['id']);
    $refs = array();

    foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'task' AND source_id = '" . (int) $task['id'] . "'") as $ref) {
        if (!in_array($ref['ref_type'], array('user', 'dept'), true)) {
            $refs[] = array('type' => (string) $ref['ref_type'], 'id' => (int) $ref['ref_id']);
        }
    }

    return array(
        'id'               => (int) $task['id'],
        'number'           => ws_task_number($task['id']),
        'title'            => (string) $task['title'],
        'description'      => (string) $task['description'],
        'status'           => (string) $task['status'],
        'priority'         => (string) $task['priority'],
        'start_date'       => $task['start_date'] ?: null,
        'due_date'         => $task['due_date'] ?: null,
        'estimate_minutes' => (int) $task['estimate_minutes'],
        'department_id'    => ((int) $task['department_id'] > 0) ? (int) $task['department_id'] : null,
        'channel_id'       => ((int) $task['channel_id'] > 0) ? (int) $task['channel_id'] : null,
        'creator_id'       => (int) $task['creator_id'],
        'assignees'        => $assignees,
        'refs'             => $refs,
        'completed_at'     => ((int) $task['completed_at'] > 0) ? api_time($task['completed_at']) : null,
        'recurrence_id'    => ((int) ($task['recurrence_id'] ?? 0) > 0) ? (int) $task['recurrence_id'] : null,
        'checklist_done'   => (int) ($task['items_done'] ?? 0),
        'checklist_total'  => (int) ($task['items_total'] ?? 0),
        'notes_count'      => (int) ($task['notes_count'] ?? 0),
        'created_at'       => api_time($task['created_at']),
        'updated_at'       => api_time($task['updated_at']),
        'warnings'         => array_values($warnings),
    );
}

// What ws_api_task_present() returns.
function ws_api_task_schema()
{
    return array(
        'id'               => 'integer',
        'number'           => 'string',
        'title'            => 'string',
        'description'      => 'string',
        'status'           => 'string',
        'priority'         => 'string',
        'start_date'       => 'string?',
        'due_date'         => 'string?',
        'estimate_minutes' => 'integer',
        'department_id'    => 'integer?',
        'channel_id'       => 'integer?',
        'creator_id'       => 'integer',
        'assignees'        => array('integer'),
        'refs'             => array(array('type' => 'string', 'id' => 'integer')),
        'completed_at'     => 'string?',
        'recurrence_id'    => 'integer?',
        'checklist_done'   => 'integer',
        'checklist_total'  => 'integer',
        'notes_count'      => 'integer',
        'created_at'       => 'string',
        'updated_at'       => 'string',
        'warnings'         => array('string'),
    );
}

function ws_api_task_or_404($viewer, $id)
{
    $task = ws_task((int) $id);

    if (!$task || !ws_can_see_task($viewer, $task)) {
        api_fail_not_found(lang('Task'));
    }

    return $task;
}

function ws_api_tasks_list($params)
{
    $viewer = ws_api_viewer();
    $scope = (string) ($params['scope'] ?? 'mine');

    if (($scope === 'all') && !$viewer['board']) {
        api_fail(403, 'forbidden', lang('Only an owner who holds the team board can list every task.'));
    }

    $filters = array(
        'scope'         => $scope,
        'status'        => (string) ($params['status'] ?? 'open'),
        'department_id' => (int) ($params['department_id'] ?? 0),
        'channel_id'    => (int) ($params['channel_id'] ?? 0),
        'limit'         => (int) ($params['limit'] ?? 100),
    );

    if (!empty($params['ref_type']) && !empty($params['ref_id'])) {
        $filters['ref_type'] = $params['ref_type'];
        $filters['ref_id'] = (int) $params['ref_id'];
    }

    if (!empty($params['updated_since'])) {
        $filters['updated_since'] = (int) $params['updated_since'];
    }

    $out = array();

    foreach (ws_tasks_list($viewer, $filters) as $brief) {
        $out[] = ws_api_task_present($viewer, ws_task($brief['id']));
    }

    api_ok_list($out, $filters['limit']);
}

function ws_api_tasks_get($params)
{
    $viewer = ws_api_viewer();

    api_ok(ws_api_task_present($viewer, ws_api_task_or_404($viewer, $params['id'])));
}

/**
 * The body of a task write, in the shape the module's own functions read.
 *
 * @param array $params
 * @return array
 */
function ws_api_task_input($params)
{
    $data = array();

    foreach (array('title', 'description', 'status', 'priority', 'start_date', 'due_date', 'estimate_minutes', 'department_id', 'channel_id') as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = $params[$field];
        }
    }

    if (array_key_exists('assignees', $params)) {
        $data['assignees'] = array_values(array_filter(array_map('intval', (array) $params['assignees'])));
    }

    if (array_key_exists('refs', $params)) {
        $refs = array();

        foreach ((array) $params['refs'] as $ref) {
            if (is_array($ref) && in_array(($ref['type'] ?? ''), ws_record_type_keys(), true)) {
                $refs[] = array('type' => $ref['type'], 'id' => (int) ($ref['id'] ?? 0));
            }
        }

        $data['refs'] = $refs;
    }

    return $data;
}

/**
 * The board check of a write: its warnings as sentences, or a 422 for a clash
 * with leave the owner may not override.
 *
 * @return string[]
 */
function ws_api_task_check($viewer, $after, $people, $force)
{
    if (!ws_task_is_open($after['status'] ?? 'todo')) {
        return array();
    }

    $check = ws_assignment_check($viewer, $after, $people);

    if ($check['hard'] && (!$force || !ws_may_override_api($viewer, (int) ($after['department_id'] ?? 0)))) {
        $messages = array();

        foreach ($check['items'] as $item) {
            if ($item['level'] === 'hard') {
                $messages[] = $item['message'];
            }
        }

        api_fail_validation(implode(' ', $messages), 'assignees');
    }

    $warnings = array();

    foreach ($check['items'] as $item) {
        $warnings[] = $item['message'];
    }

    return $warnings;
}

/**
 * ws_may_override() lives with the panel's actions; the API asks the same.
 *
 * @return bool
 */
function ws_may_override_api($viewer, $department_id)
{
    return $viewer['board'] || ($viewer['role'] < 3)
        || (($department_id > 0) && in_array($department_id, ws_lead_departments($viewer['id']), true));
}

function ws_api_tasks_create($params)
{
    $viewer = ws_api_viewer();
    $data = ws_api_task_input($params);

    if (empty($data['assignees'])) {
        $data['assignees'] = empty($data['department_id']) ? array((int) $viewer['id']) : array();
    }

    $after = array(
        'id'               => 0,
        'title'            => (string) ($data['title'] ?? ''),
        'status'           => (string) ($data['status'] ?? 'todo'),
        'start_date'       => ws_date_or_null($data['start_date'] ?? '') ?: null,
        'due_date'         => ws_date_or_null($data['due_date'] ?? '') ?: null,
        'estimate_minutes' => (int) ($data['estimate_minutes'] ?? 0),
        'department_id'    => (int) ($data['department_id'] ?? 0),
    );

    $warnings = ws_api_task_check($viewer, $after, $data['assignees'], !empty($params['force']));

    $result = ws_task_create($viewer, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    if (!empty($data['channel_id'])) {
        $sent = ws_message_send($viewer, ws_channel($data['channel_id']), '', array('kind' => 'task', 'task_id' => $result['task_id'], 'app_id' => ws_api_app_id()));

        if ($sent['ok']) {
            db("UPDATE ws_tasks SET source_message_id = '" . (int) $sent['message_id'] . "' WHERE id = '" . (int) $result['task_id'] . "'");
        }
    }

    ws_api_log(lang(array('string' => 'workspace task ({var:1}) was created', 'vars' => ws_task_number($result['task_id']))));

    api_ok(ws_api_task_present($viewer, ws_task($result['task_id']), $warnings), 201);
}

function ws_api_tasks_update($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);
    $data = ws_api_task_input($params);

    $after = array_merge($task, $data);
    $after['start_date'] = array_key_exists('start_date', $data) ? (ws_date_or_null($data['start_date']) ?: null) : $task['start_date'];
    $after['due_date'] = array_key_exists('due_date', $data) ? (ws_date_or_null($data['due_date']) ?: null) : $task['due_date'];

    $people = array_key_exists('assignees', $data) ? $data['assignees'] : ws_task_assignee_ids($task['id']);
    $warnings = ws_api_task_check($viewer, $after, $people, !empty($params['force']));

    $result = ws_task_update($viewer, $task, $data);

    if (!$result['ok']) {
        if ($result['error'] === lang('You cannot change this task.')) {
            api_fail(403, 'forbidden', $result['error']);
        }

        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    api_ok(ws_api_task_present($viewer, ws_task($task['id']), $warnings));
}

function ws_api_tasks_complete($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);

    $result = ws_task_set_status($viewer, $task, 'done');

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_task_present($viewer, ws_task($task['id'])));
}

function ws_api_task_note_present($viewer, $row)
{
    $author = ($row['sender_kind'] === 'user')
        ? array('kind' => 'user', 'id' => (int) $row['sender_id'], 'name' => ws_person_name($row['sender_id']))
        : array('kind' => 'app', 'id' => (int) $row['sender_id'], 'name' => (string) ws_app_sender((int) $row['sender_id'])['name']);

    return array(
        'id'         => (int) $row['id'],
        'task_id'    => (int) $row['task_id'],
        'author'     => $author,
        'text'       => ws_plain_text($viewer, (string) $row['body']),
        'edited'     => ((int) $row['edited_at'] > 0),
        'created_at' => api_time($row['created_at']),
    );
}

// What ws_api_task_note_present() returns.
function ws_api_task_note_schema()
{
    return array(
        'id'         => 'integer',
        'task_id'    => 'integer',
        'author'     => array('kind' => 'string', 'id' => 'integer', 'name' => 'string'),
        'text'       => 'string',
        'edited'     => 'boolean',
        'created_at' => 'string',
    );
}

function ws_api_task_notes_list($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);
    $out = array();

    if (ws_task_work_ready()) {
        foreach ((array) db_items("SELECT * FROM ws_task_notes WHERE task_id = '" . (int) $task['id'] . "' AND deleted_at = 0 ORDER BY created_at, id LIMIT 500") as $row) {
            $out[] = ws_api_task_note_present($viewer, $row);
        }
    }

    api_ok_list($out, 500);
}

function ws_api_task_notes_create($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);
    $result = ws_task_note_add($viewer, $task, (string) $params['text'], ws_api_app_id());

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'text');
    }

    api_ok(ws_api_task_note_present($viewer, ws_task_note($result['note_id'])), 201);
}

/* ---------------------------------------------------------------------------
   Repeating tasks (includes/workspace/recurrence.php)
   --------------------------------------------------------------------------- */

function ws_api_recurrence_present($series)
{
    $shape = ws_recurrence_shape($series);

    return array(
        'id'            => $shape['id'],
        'first_task_id' => $shape['first_task_id'],
        'last_task_id'  => $shape['last_task_id'],
        'frequency'     => $shape['frequency'],
        'interval'      => $shape['interval'],
        'weekdays'      => $shape['weekdays'],
        'end_date'      => $shape['end_date'],
        'next_date'     => $shape['next_date'],
        'opens_date'    => $shape['opens_date'] ?? $shape['next_date'],
        'occurrences'   => $shape['occurrences'],
        'status'        => $shape['status'],
        'ended_reason'  => ($shape['ended_reason'] !== '') ? $shape['ended_reason'] : null,
        'label'         => $shape['label'],
    );
}

// What ws_api_recurrence_present() returns, declared for the OpenAPI document.
function ws_api_recurrence_schema()
{
    return array(
        'id'            => 'integer',
        'first_task_id' => 'integer',
        'last_task_id'  => 'integer',
        'frequency'     => 'string',
        'interval'      => 'integer',
        'weekdays'      => array('integer'),
        'end_date'      => 'string?',
        'next_date'     => 'string?',
        'opens_date'    => 'string?',
        'occurrences'   => 'integer',
        'status'        => 'string',
        'ended_reason'  => 'string?',
        'label'         => 'string',
    );
}

/**
 * The series of a task the owner may see, or a 404.
 *
 * @param array $task
 * @return array
 */
function ws_api_recurrence_or_404($task)
{
    $series = function_exists('ws_recurrence') ? ws_recurrence($task['recurrence_id'] ?? 0) : null;

    if (!$series) {
        api_fail_not_found(lang('Repeat'));
    }

    return $series;
}

function ws_api_task_recurrence_get($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);

    api_ok(ws_api_recurrence_present(ws_api_recurrence_or_404($task)));
}

function ws_api_task_recurrence_set($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);

    $result = ws_recurrence_save($viewer, $task, array(
        'frequency' => (string) ($params['frequency'] ?? 'none'),
        'interval'  => (int) ($params['interval'] ?? 1),
        'weekdays'  => array_map('intval', (array) ($params['weekdays'] ?? array())),
        'end_date'  => (string) ($params['end_date'] ?? ''),
    ));

    if (!$result['ok']) {
        if ($result['error'] === lang('You cannot change this task.')) {
            api_fail(403, 'forbidden', $result['error']);
        }

        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    api_ok(ws_api_recurrence_present(ws_api_recurrence_or_404(ws_task($task['id']))));
}

function ws_api_task_recurrence_end($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);
    ws_api_recurrence_or_404($task);

    $result = ws_recurrence_end($viewer, $task, ((string) ($params['mode'] ?? 'complete') === 'stop') ? 'stop' : 'complete');

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_recurrence_present(ws_api_recurrence_or_404(ws_task($task['id']))));
}

/* ---------------------------------------------------------------------------
   Departments, references, the board
   --------------------------------------------------------------------------- */

function ws_api_department_present($department)
{
    return array(
        'id'      => (int) $department['id'],
        'name'    => (string) $department['name'],
        'color'   => (string) $department['color'],
        'members' => array_values(array_map('intval', $department['members'])),
        'leads'   => array_values(array_map('intval', $department['leads'])),
    );
}

// What ws_api_department_present() returns.
function ws_api_department_schema()
{
    return array(
        'id'      => 'integer',
        'name'    => 'string',
        'color'   => 'string',
        'members' => array('integer'),
        'leads'   => array('integer'),
    );
}

function ws_api_departments_list($params)
{
    ws_api_viewer();

    $out = array();

    foreach (ws_departments(true) as $department) {
        $out[] = ws_api_department_present($department);
    }

    api_ok_list($out, count($out));
}

// What ws_api_refs_get() returns.
function ws_api_refs_schema()
{
    return array(
        'messages' => array(array(
            'id'         => 'integer',
            'channel_id' => 'integer',
            'channel'    => 'string',
            'sender'     => 'string',
            'kind'       => 'string',
            'excerpt'    => 'string',
            'created'    => 'string',
        )),
        'tasks'    => array('integer'),
        'channels' => array(array('id' => 'integer', 'name' => 'string')),
    );
}

function ws_api_refs_get($params)
{
    $viewer = ws_api_viewer();
    $refs = ws_record_refs($viewer, (string) $params['type'], (int) $params['id'], 100);

    $messages = array();

    foreach ($refs['messages'] as $message) {
        $messages[] = array(
            'id'         => (int) $message['id'],
            'channel_id' => (int) $message['channel_id'],
            'channel'    => (string) $message['channel'],
            'sender'     => (string) $message['sender'],
            'kind'       => (string) $message['kind'],
            'excerpt'    => (string) $message['excerpt'],
            'created'    => (string) $message['time'],
        );
    }

    $tasks = array();

    foreach ($refs['tasks'] as $task) {
        $tasks[] = (int) $task['id'];
    }

    $channels = array();

    foreach ($refs['channels'] as $channel) {
        $channels[] = array('id' => (int) $channel['id'], 'name' => (string) $channel['name']);
    }

    api_ok(array('messages' => $messages, 'tasks' => $tasks, 'channels' => $channels));
}

// What ws_api_plan_get() returns, one row per person.
function ws_api_plan_row_schema()
{
    return array(
        'user_id' => 'integer',
        'name'    => 'string',
        'days'    => array(array(
            'date'     => 'string',
            'workday'  => 'boolean',
            'leave'    => 'boolean',
            'holiday'  => 'boolean',
            'capacity' => 'integer',
            'minutes'  => 'integer',
            'load'     => 'integer',
            'tasks'    => array('integer'),
        )),
    );
}

function ws_api_plan_get($params)
{
    $viewer = ws_api_viewer();
    $board = ws_board($viewer, (string) ($params['from'] ?? ''), (int) ($params['days'] ?? 7), (int) ($params['department_id'] ?? 0));
    $out = array();

    foreach ($board['rows'] as $row) {
        $days = array();

        foreach ($row['cells'] as $cell) {
            $task_ids = array();

            foreach ($cell['tasks'] as $task) {
                $task_ids[] = (int) $task['id'];
            }

            $days[] = array(
                'date'     => (string) $cell['date'],
                'workday'  => (bool) $cell['workday'],
                'leave'    => (bool) $cell['leave'],
                'holiday'  => (bool) $cell['holiday'],
                'capacity' => (int) $cell['capacity'],
                'minutes'  => (int) $cell['minutes'],
                'load'     => (int) $cell['load'],
                'tasks'    => $task_ids,
            );
        }

        $out[] = array(
            'user_id' => (int) $row['person']['id'],
            'name'    => (string) $row['person']['name'],
            'days'    => $days,
        );
    }

    api_ok_list($out, count($out));
}

/* ---------------------------------------------------------------------------
   Decisions (the timeline, includes/workspace/timeline.php)
   --------------------------------------------------------------------------- */

function ws_api_decision_present($viewer, $row, $refs, $channels, $people, $changes)
{
    $id = (int) $row['id'];
    $sender = null;

    if ($row['sender_kind'] === 'user') {
        $sender = array('user_id' => (int) $row['sender_id'], 'name' => (string) ($people[(int) $row['sender_id']]['name'] ?? ''));
    } elseif ($row['sender_kind'] === 'app') {
        $app = ws_app_sender((int) $row['sender_id']);
        $sender = array('user_id' => null, 'name' => $app['name']);
    }

    $marked_by = null;

    if ((int) $row['marked_by'] > 0) {
        $marked_by = array('user_id' => (int) $row['marked_by'], 'name' => (string) ($people[(int) $row['marked_by']]['name'] ?? ''));
    }

    $file = null;

    if ((int) $row['file_id'] > 0) {
        $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $row['file_id'] . "'");

        if ($stored !== '') {
            $file = array('name' => (string) $row['file_name'], 'url' => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($stored));
        }
    }

    $change = null;

    if (isset($changes[$id])) {
        $change = array(
            'type'      => $changes[$id]['type'],
            'action'    => $changes[$id]['action'],
            'record_id' => $changes[$id]['record_id'],
        );
    }

    return array(
        'id'            => $id,
        'channel_id'    => (int) $row['channel_id'],
        'channel_name'  => (string) ($channels[(int) $row['channel_id']]['name'] ?? ''),
        'kind'          => (string) $row['kind'],
        'text'          => ws_plain_text($viewer, $row['body'], $refs),
        'body'          => (string) $row['body'],
        'sender_kind'   => (string) $row['sender_kind'],
        'sender'        => $sender,
        'marked_by'     => $marked_by,
        'locked'        => !empty($row['locked']),
        'claude_change' => $change,
        'file'          => $file,
        'decided_at'    => api_time($row['tl_at']),
        'created_at'    => api_time($row['created_at']),
    );
}

// What ws_api_decision_present() returns.
function ws_api_decision_schema()
{
    return array(
        'id'            => 'integer',
        'channel_id'    => 'integer',
        'channel_name'  => 'string',
        'kind'          => 'string',
        'text'          => 'string',
        'body'          => 'string',
        'sender_kind'   => 'string',
        'sender'        => array('user_id' => 'integer?', 'name' => 'string'),
        'marked_by'     => array('user_id' => 'integer', 'name' => 'string'),
        'locked'        => 'boolean',
        'claude_change' => array('type' => 'string', 'action' => 'string', 'record_id' => 'integer?'),
        'file'          => array('name' => 'string', 'url' => 'string'),
        'decided_at'    => 'string',
        'created_at'    => 'string',
    );
}

function ws_api_decisions_list($params)
{
    $viewer = ws_api_viewer();
    $days = array();

    // A day may come as a full ISO moment; its date is what counts.
    foreach (array('from', 'to') as $key) {
        $value = trim((string) ($params[$key] ?? ''));

        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T/', $value)) {
            $value = substr($value, 0, 10);
        }

        if (($value !== '') && (ws_timeline_filters(array($key => $value))[$key] === '')) {
            api_fail(422, 'validation_failed', lang(array('string' => '{var:1} must be a date, in ISO-8601 or YYYY-MM-DD form.', 'vars' => $key)), $key);
        }

        $days[$key] = $value;
    }

    $cursor = null;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }
    }

    $filters = ws_timeline_filters(array(
        'notes'         => !empty($params['notes']),
        'channel_id'    => (int) ($params['channel_id'] ?? 0),
        'department_id' => (int) ($params['department_id'] ?? 0),
        'person_id'     => (int) ($params['user_id'] ?? 0),
        'from'          => $days['from'],
        'to'            => $days['to'],
        'search'        => (string) ($params['search'] ?? ''),
    ));

    // The application Claude works through reads only where Claude may be
    // asked, as it does everywhere else.
    $channel_filter = null;

    if (ws_api_is_claude()) {
        $channel_filter = function ($channel) {
            return ws_claude_channel_allowed(ws_channel($channel['id']));
        };
    }

    $limit = (int) ($params['limit'] ?? 50);
    $result = ws_timeline($viewer, $filters, $cursor, $limit, $channel_filter);

    $tokens = array();
    $people_ids = array();
    $ids = array();

    foreach ($result['rows'] as $row) {
        $tokens = array_merge($tokens, ws_tokens($row['body']));
        $ids[] = (int) $row['id'];

        if ($row['sender_kind'] === 'user') {
            $people_ids[] = (int) $row['sender_id'];
        }

        if ((int) $row['marked_by'] > 0) {
            $people_ids[] = (int) $row['marked_by'];
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people(array_unique($people_ids));
    $changes = ws_timeline_claude_changes($ids);
    $out = array();

    foreach ($result['rows'] as $row) {
        $out[] = ws_api_decision_present($viewer, $row, $refs, $result['channels'], $people, $changes);
    }

    api_ok_list($out, $limit, ($result['next'] !== null) ? api_cursor_encode($result['next']['v'], $result['next']['i']) : null);
}
