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
        'customer_type' => (($brief['customer_type'] ?? '') !== '') ? (string) $brief['customer_type'] : null,
        'customer_id'   => ((int) ($brief['customer_id'] ?? 0) > 0) ? (int) $brief['customer_id'] : null,
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
        'customer_type'   => 'string?',
        'customer_id'     => 'integer?',
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

        if (isset($params['active_since']) && ((int) $brief['last_message_at'] < (int) $params['active_since'])) {
            continue;
        }

        if (ws_api_is_claude() && !ws_claude_channel_allowed(ws_channel($brief['id']))) {
            continue;
        }

        $out[] = ws_api_channel_present($brief);
    }

    api_ok_list($out, count($out));
}

/**
 * A channel as the owner's sidebar lists it, unread count included. A channel
 * the owner no longer lists - a private one they just left - is built from
 * its row, with nothing unread.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ws_channel_brief()
 */
function ws_api_channel_brief($viewer, $channel)
{
    foreach (ws_channels_for($viewer, ((int) $channel['archived_at'] > 0)) as $brief) {
        if ((int) $brief['id'] === (int) $channel['id']) {
            return $brief;
        }
    }

    $membership = ws_channel_membership($channel['id'], $viewer['id']);

    return ws_channel_brief(array_merge($channel, array(
        'my_member' => $membership ? 1 : 0,
        'my_notify' => $membership['notify'] ?? 'all',
        'my_role'   => $membership['role'] ?? '',
    )));
}

/**
 * The user ids of a body list, once each, or a 422 naming the list when an
 * item is not an id.
 *
 * @param mixed  $list
 * @param string $field
 * @return int[]
 */
function ws_api_user_ids($list, $field)
{
    $ids = array();

    foreach ((array) $list as $item) {
        $valid = (is_int($item) || (is_string($item) && ctype_digit($item))) && ((int) $item > 0);

        if (!$valid) {
            api_fail_validation(lang(array('string' => 'Each item of {var:1} must be a user id.', 'vars' => $field)), $field);
        }

        $ids[(int) $item] = (int) $item;
    }

    return array_values($ids);
}

/**
 * Answers a refusal of the module's channel functions with the status that
 * fits it: 409 for a name another open channel holds, 403 for a missing
 * right, 422 for the rest.
 *
 * @param array $result ok, error, field
 */
function ws_api_channel_refused($result)
{
    $error = (string) $result['error'];

    if ($error === lang('There is already a channel with this name.')) {
        api_fail(409, 'name_taken', $error, 'name');
    }

    if (in_array($error, array(lang('Only the owner of the channel can change it.'), lang('Access denied.')), true)) {
        api_fail(403, 'forbidden', $error);
    }

    api_fail_validation($error, (($result['field'] ?? '') !== '') ? $result['field'] : null);
}

function ws_api_channels_create($params)
{
    $viewer = ws_api_viewer();

    // The checks ws_channel_create() makes, made first so that a dry run
    // meets the same refusals.
    $name = ws_channel_clean_name($params['name'] ?? '');

    if ($name === '') {
        api_fail_validation(lang('A channel needs a name.'), 'name');
    }

    if (ws_channel_name_taken($name)) {
        api_fail(409, 'name_taken', lang('There is already a channel with this name.'), 'name');
    }

    $department_id = (int) ($params['department_id'] ?? 0);

    // The panel offers only the departments that exist; an id that is not
    // one would be dropped without a word.
    if (($department_id > 0) && !ws_department($department_id)) {
        api_fail_validation(lang('That department could not be found.'), 'department_id');
    }

    $members = ws_api_user_ids($params['members'] ?? array(), 'members');
    $kind = ((string) ($params['kind'] ?? 'public') === 'private') ? 'private' : 'public';

    api_dry_run_stop('created', 'workspace_channel', array('name' => $name, 'kind' => $kind, 'members' => count($members)));

    $result = ws_channel_create($viewer, array(
        'name'          => (string) $params['name'],
        'kind'          => $kind,
        'topic'         => (string) ($params['topic'] ?? ''),
        'department_id' => $department_id,
        'members'       => $members,
    ));

    if (!$result['ok']) {
        ws_api_channel_refused($result);
    }

    api_ok(ws_api_channel_present(ws_api_channel_brief($viewer, ws_channel($result['channel_id']))), 201);
}

function ws_api_channels_update($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_manage_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('Only the owner of the channel can change it.'));
    }

    $data = array();

    foreach (array('name', 'topic', 'department_id') as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = $params[$field];
        }
    }

    $archive = array_key_exists('archived', $params) ? !empty($params['archived']) : null;
    $archived = ((int) $channel['archived_at'] > 0);
    $name = array_key_exists('name', $data) ? ws_channel_clean_name($data['name']) : (string) $channel['name'];

    if (array_key_exists('name', $data) && ($name === '')) {
        api_fail_validation(lang('A channel needs a name.'), 'name');
    }

    // A rename (ws_channel_update()) and a channel brought back
    // (ws_channel_archive()) both need the name free among the open channels.
    if ((array_key_exists('name', $data) || (($archive === false) && $archived)) && ws_channel_name_taken($name, $channel['id'])) {
        api_fail(409, 'name_taken', lang('There is already a channel with this name.'), 'name');
    }

    if (!empty($data['department_id']) && !ws_department((int) $data['department_id'])) {
        api_fail_validation(lang('That department could not be found.'), 'department_id');
    }

    $changes = array_keys($data);

    if (($archive !== null) && ($archive !== $archived)) {
        $changes[] = 'archived';
    }

    api_dry_run_stop('updated', 'workspace_channel', array('id' => (int) $channel['id'], 'fields' => $changes));

    if (!empty($data)) {
        $result = ws_channel_update($viewer, $channel, $data);

        if (!$result['ok']) {
            ws_api_channel_refused($result);
        }
    }

    // Archiving writes a line in the channel, so it is only done when the
    // state really changes.
    if (($archive !== null) && ($archive !== $archived)) {
        $result = ws_channel_archive($viewer, ws_channel($channel['id']), $archive);

        if (!$result['ok']) {
            ws_api_channel_refused($result);
        }
    }

    api_ok(ws_api_channel_present(ws_api_channel_brief($viewer, ws_channel($channel['id']))));
}

/* ---------------------------------------------------------------------------
   Channel members
   --------------------------------------------------------------------------- */

function ws_api_channel_member_present($row, $people)
{
    $person = $people[(int) $row['user_id']] ?? null;

    return array(
        'user_id'   => (int) $row['user_id'],
        'name'      => $person ? (string) $person['name'] : '',
        'username'  => $person ? (string) $person['username'] : '',
        'role'      => ((string) $row['role'] === 'owner') ? 'owner' : 'member',
        'joined_at' => api_time($row['joined_at']),
    );
}

// What ws_api_channel_member_present() returns.
function ws_api_channel_member_schema()
{
    return array(
        'user_id'   => 'integer',
        'name'      => 'string',
        'username'  => 'string',
        'role'      => 'string',
        'joined_at' => 'string?',
    );
}

/**
 * The people in a channel, its owner first, the way the channel's settings
 * list them (ws_channel_detail()).
 *
 * @param int $channel_id
 * @return array[]
 */
function ws_api_channel_members_of($channel_id)
{
    $rows = (array) db_items("SELECT user_id, role, joined_at FROM ws_channel_members
        WHERE channel_id = '" . (int) $channel_id . "' ORDER BY role = 'owner' DESC, joined_at, user_id");

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['user_id'];
    }

    $people = ws_people($ids);
    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_api_channel_member_present($row, $people);
    }

    return $out;
}

/**
 * What a change to a channel's people answers with. The members are listed
 * only while the owner may still read the channel.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @param int[] $added
 * @param int[] $removed
 * @return array
 */
function ws_api_channel_members_present($viewer, $channel_id, $added, $removed)
{
    $channel = ws_channel($channel_id);

    return array(
        'channel_id' => (int) $channel_id,
        'added'      => array_values(array_map('intval', (array) $added)),
        'removed'    => array_values(array_map('intval', (array) $removed)),
        'members'    => ($channel && ws_can_read_channel($viewer, $channel)) ? ws_api_channel_members_of($channel['id']) : array(),
    );
}

// What ws_api_channel_members_present() returns.
function ws_api_channel_members_schema()
{
    return array(
        'channel_id' => 'integer',
        'added'      => 'integer[]',
        'removed'    => 'integer[]',
        'members'    => 'WorkspaceChannelMember[]',
    );
}

function ws_api_channel_members_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $out = ws_api_channel_members_of($channel['id']);

    api_ok_list($out, count($out));
}

function ws_api_channel_members_add($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    // As in the panel: anyone in a private channel may bring a colleague in,
    // nobody outside it can.
    if (($channel['kind'] !== 'public') && !ws_channel_membership($channel['id'], $viewer['id'])) {
        api_fail(403, 'forbidden', lang('Only a member of a private channel can add people to it.'));
    }

    $user_ids = ws_api_user_ids($params['user_ids'] ?? array(), 'user_ids');

    api_dry_run_stop('added', 'workspace_channel_member', array('channel_id' => (int) $channel['id'], 'user_ids' => $user_ids));

    $added = ws_channel_add_members($viewer, $channel, $user_ids);

    api_ok(ws_api_channel_members_present($viewer, $channel['id'], $added, array()));
}

function ws_api_channel_members_remove($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $user_id = (int) $params['user_id'];

    // Leaving is anybody's own; taking somebody else out needs the right to
    // manage the channel (ws_channel_leave()).
    if (($user_id !== (int) $viewer['id']) && !ws_can_manage_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('Only the owner of the channel can change it.'));
    }

    $member = (bool) ws_channel_membership($channel['id'], $user_id);

    api_dry_run_stop('removed', 'workspace_channel_member', array('channel_id' => (int) $channel['id'], 'user_id' => $user_id, 'member' => $member));

    $result = ws_channel_leave($viewer, $channel, $user_id);

    if (!$result['ok']) {
        ws_api_channel_refused($result);
    }

    api_ok(ws_api_channel_members_present($viewer, $channel['id'], array(), $member ? array($user_id) : array()));
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
    } elseif (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        $sender = array('user_id' => null, 'name' => ws_guest_sender((int) $row['sender_id'])['name']);
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
        // The tasks it cannot start before, and the ones that wait for it
        // (task_links.php); the minutes spent on it (task_time.php).
        'blocked_by'       => ws_task_link_ids($task['id']),
        'blocks'           => ws_task_link_waiting_ids($task['id']),
        'time_minutes'     => (int) (ws_task_time_totals(array($task['id']))[(int) $task['id']] ?? 0),
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
        'blocked_by'       => array('integer'),
        'blocks'           => array('integer'),
        'time_minutes'     => 'integer',
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

    // The tasks it waits for are checked before anything is written.
    $blocked_by = array_key_exists('blocked_by', $params) ? (array) $params['blocked_by'] : null;

    if (($blocked_by !== null) && (($refused = ws_task_links_check($viewer, 0, $blocked_by)) !== '')) {
        api_fail_validation($refused, 'blocked_by');
    }

    $result = ws_task_create($viewer, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    if ($blocked_by !== null) {
        ws_task_links_set($viewer, ws_task($result['task_id']), $blocked_by);
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

    $blocked_by = array_key_exists('blocked_by', $params) ? (array) $params['blocked_by'] : null;

    if (($blocked_by !== null) && (($refused = ws_task_links_check($viewer, $task['id'], $blocked_by)) !== '')) {
        api_fail_validation($refused, 'blocked_by');
    }

    $result = ws_task_update($viewer, $task, $data);

    if (!$result['ok']) {
        if ($result['error'] === lang('You cannot change this task.')) {
            api_fail(403, 'forbidden', $result['error']);
        }

        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    if ($blocked_by !== null) {
        $linked = ws_task_links_set($viewer, ws_task($task['id']), $blocked_by);

        if (!$linked['ok']) {
            api_fail_validation($linked['error'], 'blocked_by');
        }
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
    } elseif (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        $sender = array('user_id' => null, 'name' => ws_guest_sender((int) $row['sender_id'])['name']);
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

/* ---------------------------------------------------------------------------
   What the assistants were asked and proposed (claude.php, ai.php, changes.php)
   --------------------------------------------------------------------------- */

/**
 * The cursor a listing was given, or a 400 for one that cannot be read.
 *
 * @param array $params
 * @return array|null v, i
 */
function ws_api_cursor($params)
{
    if (!isset($params['cursor']) || ($params['cursor'] === '')) {
        return null;
    }

    $cursor = api_cursor_decode($params['cursor']);

    if ($cursor === null) {
        api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
    }

    return $cursor;
}

/**
 * The ids of the channels the owner may read, archived ones included, as the
 * timeline counts them; for the application Claude works through, only those
 * Claude may be asked in.
 *
 * @param array $viewer
 * @return int[]
 */
function ws_api_readable_channel_ids($viewer)
{
    $claude = ws_api_is_claude();
    $ids = array();

    foreach (ws_timeline_channels($viewer) as $id => $row) {
        if ($claude && !ws_claude_channel_allowed(ws_channel($id))) {
            continue;
        }

        $ids[] = (int) $id;
    }

    return $ids;
}

/**
 * A person a row names, as {user_id, name}, or null when it names nobody.
 *
 * @param int   $user_id
 * @param array $people ws_people()
 * @return array|null
 */
function ws_api_person_ref($user_id, $people = array())
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return null;
    }

    return array('user_id' => $user_id, 'name' => (string) ($people[$user_id]['name'] ?? ws_person_name($user_id)));
}

/**
 * A person as ws_people() briefs them, as {user_id, name}, or null.
 *
 * @param array|null $person
 * @return array|null
 */
function ws_api_person_brief_ref($person)
{
    if (!is_array($person)) {
        return null;
    }

    return ws_api_person_ref($person['id'] ?? 0, array((int) ($person['id'] ?? 0) => $person));
}

/**
 * One field of a proposed change, as the card prints it.
 *
 * @param string $type
 * @param string $name
 * @param array  $field ws_change_field()
 * @param string $action
 * @param array  $item  the stored {name, from, to}
 * @return array
 */
function ws_api_change_field_present($type, $name, $field, $action, $item)
{
    // Only an update of a field with a column, or a deletion, has a value
    // the record held; a deletion has nothing it would get.
    $from = ((($action === 'update') && ($field[3] !== '')) || ($action === 'delete'));

    return array(
        'name'       => $name,
        'label'      => (string) $field[2],
        'from_shown' => $from ? ws_change_show($type, $name, $item['from'] ?? '') : null,
        'to_shown'   => ($action === 'delete') ? null : ws_change_show($type, $name, $item['to'] ?? ''),
    );
}

/**
 * The fields of a proposed change the card shows: a field the kind of record
 * no longer has is left out, and so are the empty fields of a deleted record.
 *
 * @param array  $row ws_ai_changes
 * @param string $action
 * @return array[]
 */
function ws_api_change_fields_present($row, $action)
{
    $types = ws_change_types();
    $type = (string) $row['record_type'];
    $fields = json_decode((string) $row['fields'], true);
    $fields = is_array($fields) ? $fields : array();
    $out = array();

    foreach ($fields as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = (string) ($item['name'] ?? '');
        $field = ws_change_field($types[$type], $name);

        if (($field === null) || (($action === 'delete') && in_array($item['from'] ?? null, array('', null), true))) {
            continue;
        }

        $out[] = ws_api_change_field_present($type, $name, $field, $action, $item);
    }

    return $out;
}

/**
 * The record a proposed change is about.
 *
 * @param array $row   ws_ai_changes
 * @param array $entry ws_changes_map()
 * @return array
 */
function ws_api_change_record_present($row, $entry)
{
    $types = ws_change_types();
    $type = (string) $row['record_type'];
    $id = (int) $row['record_id'];

    // A record deleted by the change is gone: no tag points at it any more.
    $tagged = ($id > 0) && !(($entry['action'] === 'delete') && ($row['status'] === 'applied'));

    return array(
        'type'       => $type,
        'type_label' => (string) $entry['type_label'],
        'id'         => ($id > 0) ? $id : null,
        'tag'        => $tagged ? '<#' . $types[$type]['tag'] . ':' . $id . '>' : null,
        'name'       => (string) $entry['record_label'],
    );
}

/**
 * One proposed record change as the API shows it. What the reader may see of
 * it - its values and its reason - is what the card in the channel shows them
 * (ws_changes_map()).
 *
 * @param array $row    ws_ai_changes, with agent when the requests carry it
 * @param array $entry  ws_changes_map() for this change
 * @param array $people ws_people()
 * @return array
 */
function ws_api_change_present($row, $entry, $people)
{
    return array(
        'id'                  => (int) $row['id'],
        'status'              => (string) $row['status'],
        'agent'               => ((string) ($row['agent'] ?? '') === 'ai') ? 'ai' : 'claude',
        'request_id'          => ((int) $row['request_id'] > 0) ? (int) $row['request_id'] : null,
        'channel_id'          => (int) $row['channel_id'],
        'message_id'          => (int) $row['message_id'],
        'action'              => (string) $entry['action'],
        'record'              => ws_api_change_record_present($row, $entry),
        'hidden'              => !empty($entry['hidden']),
        'fields'              => !empty($entry['hidden']) ? array() : ws_api_change_fields_present($row, (string) $entry['action']),
        'reason'              => (string) $entry['reason'],
        'error'               => (string) $row['error'],
        'requested_by'        => ws_api_person_ref($row['requested_by'], $people),
        'decided_by'          => ws_api_person_ref($row['decided_by'], $people),
        'decided_at'          => api_time($row['decided_at']),
        'decision_message_id' => ((int) $row['decision_message_id'] > 0) ? (int) $row['decision_message_id'] : null,
        'created_at'          => api_time($row['created_at']),
    );
}

// What ws_api_change_present() returns.
function ws_api_change_schema()
{
    return array(
        'id'                  => 'integer',
        'status'              => 'string',
        'agent'               => 'string',
        'request_id'          => 'integer?',
        'channel_id'          => 'integer',
        'message_id'          => 'integer',
        'action'              => 'string',
        'record'              => array('type' => 'string', 'type_label' => 'string', 'id' => 'integer?', 'tag' => 'string?', 'name' => 'string'),
        'hidden'              => 'boolean',
        'fields'              => array(array('name' => 'string', 'label' => 'string', 'from_shown' => 'string?', 'to_shown' => 'string?')),
        'reason'              => 'string',
        'error'               => 'string',
        'requested_by'        => array('user_id' => 'integer', 'name' => 'string'),
        'decided_by'          => array('user_id' => 'integer', 'name' => 'string'),
        'decided_at'          => 'string?',
        'decision_message_id' => 'integer?',
        'created_at'          => 'string?',
    );
}

function ws_api_changes_list($params)
{
    $viewer = ws_api_viewer();
    $limit = (int) ($params['limit'] ?? 50);
    $cursor = ws_api_cursor($params);

    if (!function_exists('ws_changes_ready') || !ws_changes_ready()) {
        api_ok_list(array(), $limit);
    }

    $types = ws_change_types();
    $type = (string) ($params['type'] ?? '');

    if (($type !== '') && !isset($types[$type])) {
        api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('type', implode(', ', array_keys($types))))), 'type');
    }

    $channel_ids = !empty($params['channel_id'])
        ? array((int) ws_api_channel_or_404($viewer, $params['channel_id'])['id'])
        : ws_api_readable_channel_ids($viewer);

    if (empty($channel_ids)) {
        api_ok_list(array(), $limit);
    }

    $where = array("c.channel_id IN (" . implode(',', $channel_ids) . ")");

    if (!empty($params['status'])) {
        $where[] = "c.status = '" . e($params['status']) . "'";
    }

    if ($type !== '') {
        $where[] = "c.record_type = '" . e($type) . "'";
    }

    if ($cursor !== null) {
        $where[] = "c.id < '" . (int) $cursor['i'] . "'";
    }

    // Which assistant proposed it, when the requests say (ai.php).
    $agent = function_exists('ws_ai_schema_ready') && ws_ai_schema_ready();

    $rows = (array) db_items("SELECT c.*" . ($agent ? ", r.agent" : '') . "
        FROM ws_ai_changes c
        " . ($agent ? "LEFT JOIN ws_ai_requests r ON r.id = c.request_id" : '') . "
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.id DESC
        LIMIT " . ($limit + 1));

    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = end($rows);
        $next = api_cursor_encode($last['id'], $last['id']);
    }

    $message_ids = array();
    $people_ids = array();

    foreach ($rows as $row) {
        $message_ids[] = (int) $row['message_id'];
        $people_ids[] = (int) $row['requested_by'];
        $people_ids[] = (int) $row['decided_by'];
    }

    // The cards of those answers, drawn for this reader.
    $entries = array();

    foreach (ws_changes_map($viewer, array_unique($message_ids)) as $list) {
        foreach ($list as $entry) {
            $entries[(int) $entry['id']] = $entry;
        }
    }

    $people = ws_people(array_unique(array_filter($people_ids)));
    $out = array();

    foreach ($rows as $row) {
        // A kind of record the site no longer runs has no card either.
        if (isset($entries[(int) $row['id']])) {
            $out[] = ws_api_change_present($row, $entries[(int) $row['id']], $people);
        }
    }

    api_ok_list($out, $limit, $next);
}

/**
 * Where a request was asked: in a channel, in a note, or in the Visual Page
 * Editor.
 *
 * @param array $row ws_ai_requests
 * @return string
 */
function ws_api_assistant_request_origin($row)
{
    if ((int) $row['channel_id'] > 0) {
        return 'channel';
    }

    if ((int) ($row['note_id'] ?? 0) > 0) {
        return 'note';
    }

    return ((int) ($row['page_id'] ?? 0) > 0) ? 'editor' : '';
}

function ws_api_assistant_request_present($row, $people)
{
    $note_id = (int) ($row['note_id'] ?? 0);
    $page_id = (int) ($row['page_id'] ?? 0);

    return array(
        'id'               => (int) $row['id'],
        'agent'            => ((string) ($row['agent'] ?? '') === 'ai') ? 'ai' : 'claude',
        'status'           => (string) $row['status'],
        'origin'           => ws_api_assistant_request_origin($row),
        'channel_id'       => ((int) $row['channel_id'] > 0) ? (int) $row['channel_id'] : null,
        'message_id'       => ((int) $row['message_id'] > 0) ? (int) $row['message_id'] : null,
        'note_id'          => ($note_id > 0) ? $note_id : null,
        'page_id'          => ($page_id > 0) ? $page_id : null,
        'node_id'          => (($page_id > 0) && ((string) ($row['node_id'] ?? '') !== '')) ? (string) $row['node_id'] : null,
        'requested_by'     => ws_api_person_ref($row['requested_by'], $people),
        'reply_message_id' => ((int) $row['reply_message_id'] > 0) ? (int) $row['reply_message_id'] : null,
        'error'            => (string) $row['error'],
        'created_at'       => api_time($row['created_at']),
        'sent_at'          => api_time($row['sent_at']),
        'claimed_at'       => api_time($row['claimed_at']),
        'answered_at'      => api_time($row['answered_at']),
    );
}

// What ws_api_assistant_request_present() returns.
function ws_api_assistant_request_schema()
{
    return array(
        'id'               => 'integer',
        'agent'            => 'string',
        'status'           => 'string',
        'origin'           => 'string',
        'channel_id'       => 'integer?',
        'message_id'       => 'integer?',
        'note_id'          => 'integer?',
        'page_id'          => 'integer?',
        'node_id'          => 'string?',
        'requested_by'     => array('user_id' => 'integer', 'name' => 'string'),
        'reply_message_id' => 'integer?',
        'error'            => 'string',
        'created_at'       => 'string?',
        'sent_at'          => 'string?',
        'claimed_at'       => 'string?',
        'answered_at'      => 'string?',
    );
}

function ws_api_assistant_requests_list($params)
{
    $viewer = ws_api_viewer();
    $limit = (int) ($params['limit'] ?? 50);
    $cursor = ws_api_cursor($params);

    if (!function_exists('ws_claude_schema_ready') || !ws_claude_schema_ready()) {
        api_ok_list(array(), $limit);
    }

    // Asked in a channel the owner reads, or by the owner where no channel
    // is: in a note or in the page editor.
    $reach = array("(channel_id = 0 AND requested_by = '" . (int) $viewer['id'] . "')");
    $channel_ids = ws_api_readable_channel_ids($viewer);

    if (!empty($channel_ids)) {
        $reach[] = "channel_id IN (" . implode(',', $channel_ids) . ")";
    }

    $where = "(" . implode(' OR ', $reach) . ")";

    if (!empty($params['status'])) {
        $where .= " AND status = '" . e($params['status']) . "'";
    }

    if (!empty($params['agent']) && function_exists('ws_ai_agent_where')) {
        $where .= ws_ai_agent_where((string) $params['agent']);
    }

    if ($cursor !== null) {
        $where .= " AND id < '" . (int) $cursor['i'] . "'";
    }

    // The columns later steps added are read only where they exist; the
    // conversation and page trees kept on the row are never read here.
    $columns = array('id', 'channel_id', 'message_id', 'requested_by', 'status', 'reply_message_id', 'error', 'created_at', 'sent_at', 'claimed_at', 'answered_at');

    foreach (array('agent', 'note_id', 'page_id', 'node_id') as $column) {
        if (function_exists('waf_table_has_column') && waf_table_has_column('ws_ai_requests', $column)) {
            $columns[] = $column;
        }
    }

    $rows = (array) db_items("SELECT " . implode(', ', $columns) . " FROM ws_ai_requests
        WHERE " . $where . "
        ORDER BY id DESC
        LIMIT " . ($limit + 1));

    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = end($rows);
        $next = api_cursor_encode($last['id'], $last['id']);
    }

    $people_ids = array();

    foreach ($rows as $row) {
        $people_ids[] = (int) $row['requested_by'];
    }

    $people = ws_people(array_unique(array_filter($people_ids)));
    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_api_assistant_request_present($row, $people);
    }

    api_ok_list($out, $limit, $next);
}

/* ---------------------------------------------------------------------------
   Notes (includes/workspace/notes.php)
   --------------------------------------------------------------------------- */

/**
 * A note the owner may read, or a 404. The application Claude works through
 * reads a note that reached the owner only through a channel just where
 * Claude may be asked, as it reads the channel itself.
 *
 * @param array $viewer
 * @param int   $note_id
 * @return array the row, with access
 */
function ws_api_note_or_404($viewer, $note_id)
{
    $note = ws_note_for($viewer, (int) $note_id, 'view');

    if (!$note) {
        api_fail_not_found(lang('Note'));
    }

    if (ws_api_is_claude() && ($note['access'] === 'view')) {
        $reached = false;

        foreach ((array) db_items("SELECT user_id, channel_id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'") as $share) {
            if ((int) $share['user_id'] === (int) $viewer['id']) {
                $reached = true;
            } elseif ((int) $share['channel_id'] > 0) {
                $channel = ws_channel($share['channel_id']);
                $reached = $reached || ($channel && ws_can_read_channel($viewer, $channel) && ws_claude_channel_allowed($channel));
            }
        }

        if (!$reached) {
            api_fail_not_found(lang('Note'));
        }
    }

    return $note;
}

/**
 * Where a note was taken from, when it was taken from a message.
 *
 * @param array|null $source ws_notes_present()
 * @return array|null
 */
function ws_api_note_source_present($source)
{
    if (!is_array($source)) {
        return null;
    }

    $channel = $source['channel'] ?? null;

    return array(
        'channel_id'   => is_array($channel) ? (int) $channel['id'] : null,
        'channel_name' => is_array($channel) ? (string) $channel['name'] : '',
        'author'       => is_array($source['sender'] ?? null) ? (string) ($source['sender']['name'] ?? '') : '',
    );
}

/**
 * The people a note is shared with.
 *
 * @param array[] $with ws_notes_present()
 * @return array[]
 */
function ws_api_note_people_present($with)
{
    $out = array();

    foreach ((array) $with as $share) {
        $out[] = ws_api_note_person_share_present($share);
    }

    return $out;
}

function ws_api_note_person_share_present($share)
{
    return array(
        'user_id'  => (int) $share['person']['id'],
        'name'     => (string) $share['person']['name'],
        'can_edit' => !empty($share['can_edit']),
    );
}

/**
 * The channels a note is shared in.
 *
 * @param array[] $in ws_notes_present()
 * @return array[]
 */
function ws_api_note_channels_present($in)
{
    $out = array();

    foreach ((array) $in as $share) {
        $out[] = ws_api_note_channel_share_present($share);
    }

    return $out;
}

function ws_api_note_channel_share_present($share)
{
    return array(
        'channel_id' => (int) $share['id'],
        'name'       => (string) $share['name'],
    );
}

/**
 * One note as the API shows it, from what the notes screen makes of it for
 * this reader (ws_notes_present()); text and body only when it was made with
 * them.
 *
 * @param array $viewer
 * @param array $note ws_notes_present()
 * @return array
 */
function ws_api_note_present($viewer, $note)
{
    $full = array_key_exists('body', $note);

    return array(
        'id'          => (int) $note['id'],
        'title'       => (string) $note['title'],
        'name'        => (string) $note['name'],
        'excerpt'     => (string) $note['excerpt'],
        'access'      => (string) $note['access'],
        'pinned'      => !empty($note['pinned']),
        'unread'      => !empty($note['unread']),
        'owner'       => ws_api_person_brief_ref($note['owner'] ?? null),
        'updated_by'  => ws_api_person_brief_ref($note['updated_by'] ?? null),
        'source'      => ws_api_note_source_present($note['source'] ?? null),
        'shared_with' => ws_api_note_people_present($note['with'] ?? array()),
        'shared_in'   => ws_api_note_channels_present($note['in'] ?? array()),
        'channel_id'  => is_array($note['channel'] ?? null) ? (int) $note['channel']['id'] : null,
        'text'        => $full ? ws_plain_text($viewer, (string) $note['body']) : null,
        'body'        => $full ? (string) $note['body'] : null,
        'updated_at'  => api_time($note['updated_at']),
    );
}

// What ws_api_note_present() returns.
function ws_api_note_schema()
{
    return array(
        'id'          => 'integer',
        'title'       => 'string',
        'name'        => 'string',
        'excerpt'     => 'string',
        'access'      => 'string',
        'pinned'      => 'boolean',
        'unread'      => 'boolean',
        'owner'       => array('user_id' => 'integer', 'name' => 'string'),
        'updated_by'  => array('user_id' => 'integer', 'name' => 'string'),
        'source'      => array('channel_id' => 'integer?', 'channel_name' => 'string', 'author' => 'string'),
        'shared_with' => array(array('user_id' => 'integer', 'name' => 'string', 'can_edit' => 'boolean')),
        'shared_in'   => array(array('channel_id' => 'integer', 'name' => 'string')),
        'channel_id'  => 'integer?',
        'text'        => 'string?',
        'body'        => 'string?',
        'updated_at'  => 'string?',
    );
}

function ws_api_notes_list($params)
{
    $viewer = ws_api_viewer();
    $scope = (string) ($params['scope'] ?? 'all');
    $lists = ws_notes_list($viewer, (string) ($params['search'] ?? ''));
    $notes = array();

    if ($scope !== 'shared') {
        $notes = array_merge($notes, $lists['mine']);
    }

    if ($scope !== 'mine') {
        $notes = array_merge($notes, $lists['shared']);
    }

    $out = array();

    foreach ($notes as $note) {
        if (isset($params['updated_since']) && ((int) $note['updated_at'] < (int) $params['updated_since'])) {
            continue;
        }

        $out[] = ws_api_note_present($viewer, $note);
    }

    api_ok_list($out, count($out));
}

function ws_api_notes_get($params)
{
    $viewer = ws_api_viewer();
    $note = ws_api_note_or_404($viewer, $params['id']);

    api_ok(ws_api_note_present($viewer, ws_note_present($viewer, $note)));
}

/* ---------------------------------------------------------------------------
   Time spent on tasks (includes/workspace/task_time.php)
   --------------------------------------------------------------------------- */

function ws_api_task_time_present($row)
{
    $running = ((int) $row['minutes'] === 0) && ((int) $row['started_at'] > 0);

    return array(
        'id'         => (int) $row['id'],
        'task_id'    => (int) $row['task_id'],
        'user_id'    => (int) $row['user_id'],
        'minutes'    => (int) $row['minutes'],
        'worked_on'  => (string) $row['worked_on'],
        'note'       => (string) $row['note'],
        'billable'   => ((int) $row['billable'] === 1),
        'invoice_id' => ((int) $row['invoice_id'] > 0) ? (int) $row['invoice_id'] : null,
        'running'    => $running,
        'started_at' => ((int) $row['started_at'] > 0) ? api_time($row['started_at']) : null,
        'created_at' => api_time($row['created_at']),
    );
}

// What ws_api_task_time_present() returns.
function ws_api_task_time_schema()
{
    return array(
        'id'         => 'integer',
        'task_id'    => 'integer',
        'user_id'    => 'integer',
        'minutes'    => 'integer',
        'worked_on'  => 'string',
        'note'       => 'string',
        'billable'   => 'boolean',
        'invoice_id' => 'integer?',
        'running'    => 'boolean',
        'started_at' => 'string?',
        'created_at' => 'string',
    );
}

/**
 * The task of a time request, with the schema there.
 *
 * @return array viewer, task
 */
function ws_api_task_time_task($params)
{
    $viewer = ws_api_viewer();

    if (!ws_task_time_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    return array($viewer, ws_api_task_or_404($viewer, $params['id']));
}

function ws_api_task_time_list($params)
{
    list(, $task) = ws_api_task_time_task($params);
    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_task_time WHERE task_id = '" . (int) $task['id'] . "' ORDER BY worked_on DESC, id DESC LIMIT 500") as $row) {
        $out[] = ws_api_task_time_present($row);
    }

    api_ok_list($out, 500);
}

function ws_api_task_time_create($params)
{
    list($viewer, $task) = ws_api_task_time_task($params);

    if (!ws_task_time_may_log($viewer, $task, ws_task_assignee_ids($task['id']))) {
        api_fail(403, 'forbidden', lang('Only the people on a task, the one who created it and staff can write time on it.'));
    }

    $data = array(
        'minutes'   => (int) $params['minutes'],
        'worked_on' => (string) ($params['worked_on'] ?? ''),
        'note'      => (string) ($params['note'] ?? ''),
        'billable'  => array_key_exists('billable', $params) ? !empty($params['billable']) : true,
    );

    // The checks ws_task_time_add() makes, before the rehearsal stops.
    $day = ws_date_or_null($data['worked_on']);

    if ($day === false) {
        api_fail_validation(lang('Write the date as day.month.year.'), 'worked_on');
    }

    if (($day !== null) && ($day > date('Y-m-d'))) {
        api_fail_validation(lang('Time cannot be written for a day that has not come yet.'), 'worked_on');
    }

    api_dry_run_stop('created', 'workspace_task_time', array('task_id' => (int) $task['id'], 'minutes' => $data['minutes']));

    $result = ws_task_time_add($viewer, $task, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    ws_api_log(lang(array('string' => 'time was written on workspace task ({var:1})', 'vars' => ws_task_number($task['id']))));

    api_ok(ws_api_task_time_present(ws_task_time_entry($result['id'])), 201);
}

function ws_api_task_time_delete($params)
{
    list($viewer, $task) = ws_api_task_time_task($params);
    $entry = ws_task_time_entry((int) $params['entry_id']);

    if (!$entry || ((int) $entry['task_id'] !== (int) $task['id'])) {
        api_fail_not_found(lang('Time entry'));
    }

    if (((int) $entry['user_id'] !== (int) $viewer['id']) && !ws_task_time_is_manager($viewer)) {
        api_fail(403, 'forbidden', lang('Only the one who wrote the time, or staff, can delete it.'));
    }

    if ((int) $entry['invoice_id'] > 0) {
        api_fail_validation(lang('This time is on an invoice draft. Undo the tie on the channel\'s Summary tab first.'), 'entry_id');
    }

    api_dry_run_stop('deleted', 'workspace_task_time', array('id' => (int) $entry['id']));

    $result = ws_task_time_delete($viewer, $entry);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    api_ok(ws_api_task_time_present($entry));
}
