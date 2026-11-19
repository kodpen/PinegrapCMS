<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - discussions, scheduled messages and the earlier versions of a
 * note in the external API.
 *
 * A discussion is a channel of its own about one message (threads.php): it is
 * started from the message the way the channel screen starts it, and once
 * started its id is read and written through the channel message endpoints
 * like any other channel's, by the people in it.
 *
 * A scheduled message waits as its writer's own until its time and is then
 * posted in the writer's name by scheduled_messages.php, so only the person,
 * from their team devices session, schedules one; an application key or an
 * OAuth connection may list and cancel the ones its owner wrote.
 *
 * The versions of a note are read here only, through notes.php.
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

/* ---------------------------------------------------------------------------
   Discussions (threads.php)
   --------------------------------------------------------------------------- */

/**
 * One discussion as the API shows it, from what the channel screen shows of
 * it for this reader (ws_thread_present()).
 *
 * @param array $viewer
 * @param array $thread  the ws_threads row
 * @param array $channel the discussion's channel row
 * @return array
 */
function ws_api_thread_present($viewer, $thread, $channel)
{
    $present = ws_thread_present($viewer, $thread, $channel);
    $people = array();

    foreach ((array) $present['people'] as $person) {
        $people[] = array('user_id' => (int) $person['id'], 'name' => (string) $person['name']);
    }

    return array(
        'id'              => (int) $present['id'],
        'channel_id'      => (int) $present['channel_id'],
        'message_id'      => (int) $present['message_id'],
        'title'           => (string) $present['title'],
        'people'          => $people,
        'message_count'   => (int) $present['count'],
        'unread'          => (int) $present['unread'],
        'joined'          => !empty($present['mine']),
        'can_join'        => !empty($present['can_join']),
        'closed'          => !empty($present['closed']),
        'assistants'      => array_values(array_map('strval', (array) $present['assistants'])),
    ) + ws_api_thread_follow_fields($present) + array(
        'started_by'      => ws_api_person_ref($thread['created_by']),
        'closed_by'       => ws_api_person_ref($thread['closed_by']),
        'closed_at'       => api_time($thread['closed_at']),
        'kept_until'      => api_time($thread['purge_at']),
        'last_message_at' => api_time($channel['last_message_at']),
        'created_at'      => api_time($thread['created_at']),
    );
}

/**
 * The agent a discussion follows, as the API shows it: its key (claude, ai,
 * mcp:<server id>, or a registry agent's agent:<id> / model:<id>) and handle, the one Follow again would bring back, and
 * whether the person may change it (POST /workspace/threads/{id}/follow).
 *
 * @param array $present ws_thread_present()
 * @return array
 */
function ws_api_thread_follow_fields($present)
{
    $follow = (string) ($present['follow'] ?? '');
    $again = (string) ($present['follow_again'] ?? '');

    return array(
        'follow'        => ($follow !== '') ? $follow : null,
        'follow_handle' => (($follow !== '') && ((string) ($present['follow_handle'] ?? '') !== '')) ? (string) $present['follow_handle'] : null,
        'follow_again'  => ($again !== '') ? $again : null,
        'can_follow'    => !empty($present['can_follow']),
    );
}

// What ws_api_thread_present() returns.
function ws_api_thread_schema()
{
    return array(
        'id'              => 'integer',
        'channel_id'      => 'integer',
        'message_id'      => 'integer',
        'title'           => 'string',
        'people'          => array(array('user_id' => 'integer', 'name' => 'string')),
        'message_count'   => 'integer',
        'unread'          => 'integer',
        'joined'          => 'boolean',
        'can_join'        => 'boolean',
        'closed'          => 'boolean',
        'assistants'      => array('string'),
        'follow'          => 'string?',
        'follow_handle'   => 'string?',
        'follow_again'    => 'string?',
        'can_follow'      => 'boolean',
        'started_by'      => 'WorkspacePerson?',
        'closed_by'       => 'WorkspacePerson?',
        'closed_at'       => 'datetime?',
        'kept_until'      => 'datetime?',
        'last_message_at' => 'datetime?',
        'created_at'      => 'datetime?',
    );
}

/**
 * The discussions are there, or a 503.
 */
function ws_api_threads_ready()
{
    if (!function_exists('ws_threads_ready') || !ws_threads_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * Answers a refusal of ws_thread_start_refusal() with the status that fits
 * it: 403 for a channel the owner may not write in, 503 for a schema that is
 * not there, 404 for a line nobody said (a system line, a poll), 422 for the
 * rest.
 *
 * @param string $error
 */
function ws_api_thread_refused($error)
{
    if ($error === lang('You cannot post in that channel.')) {
        api_fail(403, 'forbidden', $error);
    }

    if ($error === lang('The workspace is not installed yet: the database has to be updated first.')) {
        api_fail(503, 'service_unavailable', $error);
    }

    if ($error === lang('That message could not be found.')) {
        api_fail_not_found(lang('Message'));
    }

    api_fail_validation($error);
}

function ws_api_threads_list($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $limit = (int) ($params['limit'] ?? 50);
    $state = (string) ($params['state'] ?? 'all');
    $where = array("t.parent_channel_id = '" . (int) $channel['id'] . "'");

    if ($state === 'open') {
        $where[] = "t.closed_at = 0";
    } elseif ($state === 'closed') {
        $where[] = "t.closed_at > 0";
    }

    // Only the discussions the owner is in can be read by them; each is
    // checked again below with the panel's own rule.
    $threads = (array) db_items("SELECT t.* FROM ws_threads t
        INNER JOIN ws_channels c ON c.id = t.channel_id AND c.kind = 'thread'
        INNER JOIN ws_channel_members m ON m.channel_id = t.channel_id AND m.user_id = '" . (int) $viewer['id'] . "'
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.last_message_at DESC, c.id DESC
        LIMIT " . max(1, $limit));

    $channels = array();

    if (!empty($threads)) {
        $ids = array();

        foreach ($threads as $thread) {
            $ids[] = (int) $thread['channel_id'];
        }

        foreach ((array) db_items("SELECT * FROM ws_channels WHERE id IN (" . implode(',', $ids) . ")") as $row) {
            $channels[(int) $row['id']] = $row;
        }
    }

    $out = array();

    foreach ($threads as $thread) {
        $room = $channels[(int) $thread['channel_id']] ?? null;

        if ($room && ws_can_read_channel($viewer, $room)) {
            $out[] = ws_api_thread_present($viewer, $thread, $room);
        }
    }

    api_ok_list($out, $limit);
}

function ws_api_thread_start($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    // The checks ws_thread_start() makes, made first so that a dry run
    // meets the same refusals.
    $refusal = ws_thread_start_refusal($viewer, $message);

    if ($refusal !== '') {
        ws_api_thread_refused($refusal);
    }

    // A message has one discussion: the one there is answers.
    $existing = ws_thread_for_message($message['id']);
    $room = $existing ? ws_channel((int) $existing['channel_id']) : null;

    if ($existing && $room) {
        api_ok(ws_api_thread_present($viewer, $existing, $room));
    }

    api_dry_run_stop('created', 'workspace_thread', array('message_id' => (int) $message['id'], 'channel_id' => (int) $message['channel_id']));

    $result = ws_thread_start($viewer, $message, (string) ($params['title'] ?? ''));

    if (!$result['ok']) {
        ws_api_thread_refused($result['error']);
    }

    $room = ws_channel($result['thread_id']);
    $thread = ws_thread($result['thread_id']);

    if (!$room || !$thread) {
        api_fail_not_found(lang('Discussion'));
    }

    if (!$result['existing']) {
        ws_api_log(lang(array('string' => 'started a discussion in #{var:1}', 'vars' => (string) ws_channel($message['channel_id'])['name'])));
    }

    api_ok(ws_api_thread_present($viewer, $thread, $room), $result['existing'] ? 200 : 201);
}

/**
 * A discussion as joining and leaving find it: one started in a channel the
 * owner may read, whether or not they are in it, as the bar under the
 * message shows it. Anything else is a 404.
 *
 * @param array $viewer
 * @param int   $id the discussion's id
 * @return array the discussion's channel row, its ws_threads row
 */
function ws_api_thread_room_or_404($viewer, $id)
{
    $room = ws_channel((int) $id);
    $parent = ws_thread_parent_channel($room);
    $thread = $parent ? ws_thread($room['id']) : null;

    if (!$thread || !ws_can_read_channel($viewer, $parent)) {
        api_fail_not_found(lang('Discussion'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $parent['id']);

    return array($room, $thread);
}

/**
 * Joins a discussion the way Join on the bar under its message does: staff
 * who read its channel, while it and its channel are open. The discussion
 * gets a line saying who joined.
 */
function ws_api_threads_join($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    list($room, $thread) = ws_api_thread_room_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can join or leave a discussion; an application cannot do it for them.'));

    // Already in it: nothing to do.
    if (ws_channel_membership($room['id'], $viewer['id'])) {
        api_ok(ws_api_thread_present($viewer, $thread, $room));
    }

    // The checks ws_thread_join() makes, said one by one.
    if (!ws_thread_can_join($viewer, $room)) {
        if (((int) $thread['closed_at'] > 0) || ((int) $room['archived_at'] > 0)) {
            api_fail_validation(lang('The discussion was concluded.'));
        }

        if ((int) ws_thread_parent_channel($room)['archived_at'] > 0) {
            api_fail_validation(lang('This channel is archived. It can be read but not written in.'));
        }

        api_fail(403, 'forbidden', lang('Only staff who read its channel can join a discussion.'));
    }

    api_dry_run_stop('joined', 'workspace_thread', array('id' => (int) $room['id'], 'channel_id' => (int) $thread['parent_channel_id']));

    $result = ws_thread_join($viewer, $room);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_thread_present($viewer, $thread, ws_channel($room['id'])));
}

/**
 * Leaves a discussion the way Leave in its menu does. The last one out
 * concludes it. Somebody who was not in it is not an error.
 */
function ws_api_threads_leave($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    list($room, $thread) = ws_api_thread_room_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can join or leave a discussion; an application cannot do it for them.'));

    if (!ws_channel_membership($room['id'], $viewer['id'])) {
        api_ok(ws_api_thread_present($viewer, $thread, $room));
    }

    $last = ((int) db_value("SELECT COUNT(*) FROM ws_channel_members WHERE channel_id = '" . (int) $room['id'] . "'") === 1);

    api_dry_run_stop('left', 'workspace_thread', array('id' => (int) $room['id'], 'channel_id' => (int) $thread['parent_channel_id'],
        'concludes' => $last && ((int) $thread['closed_at'] === 0)));

    $result = ws_thread_leave($viewer, $room);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    // Read again: the last one out concluded it.
    $after = db_item("SELECT * FROM ws_threads WHERE channel_id = '" . (int) $room['id'] . "'");

    api_ok(ws_api_thread_present($viewer, is_array($after) ? $after : $thread, ws_channel($room['id'])));
}

/**
 * Stops the assistant a discussion follows, or brings the last one back, the
 * way Stop and Follow again in its header do. Anybody in the discussion may;
 * the discussion gets a line saying who did it.
 */
function ws_api_threads_follow($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    list($room, $thread) = ws_api_thread_room_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can stop or start the following of a discussion; an application cannot do it for them.'));

    $follow = !empty($params['follow']);
    $refusal = ws_thread_follow_refusal($viewer, $room, $follow);

    if ($refusal === lang('Only the people in a discussion can change it.')) {
        api_fail(403, 'forbidden', $refusal);
    }

    if ($refusal === lang('The workspace is not installed yet: the database has to be updated first.')) {
        api_fail(503, 'service_unavailable', $refusal);
    }

    if ($refusal !== '') {
        api_fail_validation($refusal, 'follow');
    }

    api_dry_run_stop('updated', 'workspace_thread', array('id' => (int) $room['id'], 'follow' => $follow));

    $result = ws_thread_follow_change($viewer, $room, $follow);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'follow');
    }

    $after = ws_thread($room['id']);

    api_ok(ws_api_thread_present($viewer, is_array($after) ? $after : $thread, ws_channel($room['id'])));
}

/* ---------------------------------------------------------------------------
   Scheduled messages (scheduled_messages.php)
   --------------------------------------------------------------------------- */

/**
 * One scheduled message as the API shows it, from what its writer's screens
 * show of it (ws_scheduled_message_present()).
 *
 * @param array $viewer
 * @param array $row decoded (ws_scheduled_message_mine())
 * @return array
 */
function ws_api_scheduled_message_present($viewer, $row)
{
    return ws_api_scheduled_message_shape($viewer, ws_scheduled_message_present($viewer, $row), $row);
}

/**
 * The API's shape of a scheduled message.
 *
 * @param array $viewer
 * @param array $present ws_scheduled_message_present()
 * @param array $row     the row, for when it was written
 * @return array
 */
function ws_api_scheduled_message_shape($viewer, $present, $row)
{
    $body = (string) $present['raw'];

    return array(
        'id'         => (int) $present['id'],
        'channel_id' => is_array($present['channel']) ? (int) $present['channel']['id'] : (int) $row['channel_id'],
        'text'       => ws_plain_text($viewer, $body),
        'body'       => $body,
        'parent_id'  => ((int) $present['parent_id'] > 0) ? (int) $present['parent_id'] : null,
        'send_at'    => api_time($present['timestamp']),
        'status'     => (string) $present['status'],
        'error'      => ((string) $present['error'] !== '') ? (string) $present['error'] : null,
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What ws_api_scheduled_message_present() returns.
function ws_api_scheduled_message_schema()
{
    return array(
        'id'         => 'integer',
        'channel_id' => 'integer',
        'text'       => 'string',
        'body'       => 'string',
        'parent_id'  => 'integer?',
        'send_at'    => 'datetime?',
        'status'     => 'string',
        'error'      => 'string?',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    );
}

/**
 * Scheduled messages are there, or a 503.
 */
function ws_api_scheduled_messages_ready()
{
    if (!function_exists('ws_scheduled_messages_ready') || !ws_scheduled_messages_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * The field of the API a refusal of ws_scheduled_message_save() is about.
 *
 * @param string $field body | time | ''
 * @return string|null
 */
function ws_api_scheduled_message_field($field)
{
    if ($field === 'body') {
        return 'text';
    }

    return ($field === 'time') ? 'send_at' : null;
}

function ws_api_scheduled_messages_list($params)
{
    $viewer = ws_api_viewer();

    ws_api_scheduled_messages_ready();

    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $items = ws_scheduled_messages_list($viewer, $channel['id']);
    $rows = array();

    // When each was written, one read for the list.
    if (!empty($items)) {
        $ids = array();

        foreach ($items as $item) {
            $ids[] = (int) $item['id'];
        }

        foreach ((array) db_items("SELECT id, channel_id, created_at, updated_at FROM ws_scheduled_actions WHERE id IN (" . implode(',', $ids) . ")") as $row) {
            $rows[(int) $row['id']] = $row;
        }
    }

    $out = array();

    foreach ($items as $item) {
        if (isset($rows[(int) $item['id']])) {
            $out[] = ws_api_scheduled_message_shape($viewer, $item, $rows[(int) $item['id']]);
        }
    }

    api_ok_list($out, WS_SCHEDULED_MESSAGES_MAX);
}

function ws_api_scheduled_messages_create($params)
{
    $viewer = ws_api_viewer();

    ws_api_scheduled_messages_ready();

    $channel = ws_api_channel_or_404($viewer, $params['id']);

    // Posted later in the writer's own name (scheduled_messages.php posts
    // it with no application on it), so only the team devices session,
    // which writes as the person now too, schedules one: an application key
    // and an OAuth connection write as the application, and a message they
    // scheduled would turn up later as the person's own words.
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can schedule a message: it is posted later in their own name, while an application key or an OAuth connection writes as the application.'));

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    // The moment, read on the server's clock to the minute, as the date and
    // the time of the writing box are.
    $at = (int) $params['send_at'];
    $parent_id = ws_api_scheduled_message_parent($channel, $params);
    $data = array(
        'id'         => 0,
        'channel_id' => (int) $channel['id'],
        'body'       => (string) $params['text'],
        'parent_id'  => $parent_id,
        'date'       => date('Y-m-d', $at),
        'time'       => date('H:i', $at),
    );

    // The checks ws_scheduled_message_save() makes, made first so that a dry
    // run meets the same refusals.
    $checked = ws_scheduled_message_save_check($viewer, $data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], ws_api_scheduled_message_field((string) $checked['field']));
    }

    api_dry_run_stop('created', 'workspace_scheduled_message', array(
        'channel_id' => (int) $channel['id'],
        'send_at'    => api_time($checked['at']),
        'parent_id'  => ((int) $checked['parent_id'] > 0) ? (int) $checked['parent_id'] : null,
    ));

    $result = ws_scheduled_message_save($viewer, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error'], ws_api_scheduled_message_field((string) $result['field']));
    }

    $row = ws_scheduled_message_mine($viewer, $result['id']);

    if (!$row) {
        api_fail_not_found(lang('Scheduled message'));
    }

    ws_api_log(lang(array('string' => 'a message was scheduled in #{var:1}', 'vars' => (string) $channel['name'])));

    api_ok(ws_api_scheduled_message_present($viewer, $row), 201);
}

/**
 * The message a scheduled message answers, from parent_id: null when it is
 * left out (a changed one keeps its own), 0 for none, else a message of the
 * channel that may be answered - refused with 422 on parent_id otherwise,
 * as POST /workspace/channels/{id}/messages refuses it.
 *
 * @param array $channel
 * @param array $params
 * @return int|null
 */
function ws_api_scheduled_message_parent($channel, $params)
{
    if (!array_key_exists('parent_id', $params) || ($params['parent_id'] === null)) {
        return null;
    }

    $parent_id = (int) $params['parent_id'];
    $refusal = ws_message_parent_check($channel, $parent_id);

    if ($refusal !== '') {
        api_fail_validation($refusal, 'parent_id');
    }

    return $parent_id;
}

/**
 * One of the owner's own scheduled messages still waiting or failed, in a
 * channel the application reaches, or a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array decoded
 */
function ws_api_scheduled_message_mine_or_404($viewer, $id)
{
    // Somebody else's, or one already posted, is not there for them.
    $row = ws_scheduled_message_mine($viewer, (int) $id);

    if (!$row || !in_array((string) $row['status'], array('active', 'failed'), true)) {
        api_fail_not_found(lang('Scheduled message'));
    }

    // The application Claude works through reaches only the channels Claude
    // may be asked in.
    if (ws_api_is_claude()) {
        $channel = ws_channel((int) $row['channel_id']);

        if (!$channel || !ws_claude_channel_allowed($channel)) {
            api_fail_not_found(lang('Scheduled message'));
        }
    }

    return $row;
}

/**
 * POST /workspace/scheduled-messages/{id}/update: changes the words, the
 * moment or the message it answers of one of the person's own scheduled
 * messages, as the clock's list does; a failed one waits again.
 */
function ws_api_scheduled_messages_update($params)
{
    $viewer = ws_api_viewer();

    ws_api_scheduled_messages_ready();

    // Posted later in the writer's own name, as when it was scheduled.
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can change a scheduled message: it is posted later in their own name, while an application key or an OAuth connection writes as the application.'));

    $row = ws_api_scheduled_message_mine_or_404($viewer, $params['id']);
    $action = is_array($row['action']) ? $row['action'] : array();
    $channel = ws_channel((int) $row['channel_id']);

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    // What is left out stays as it is.
    $rule = ws_scheduled_time_rule($row);
    $at = isset($params['send_at']) ? (int) $params['send_at'] : 0;
    $data = array(
        'id'        => (int) $row['id'],
        'body'      => array_key_exists('text', $params) ? (string) $params['text'] : (string) ($action['body'] ?? ''),
        'parent_id' => ws_api_scheduled_message_parent($channel, $params),
        'date'      => ($at > 0) ? date('Y-m-d', $at) : (string) ($rule['date'] ?? ''),
        'time'      => ($at > 0) ? date('H:i', $at) : (string) ($rule['time'] ?? ''),
    );

    // The checks ws_scheduled_message_save() makes, made first so that a dry
    // run meets the same refusals.
    $checked = ws_scheduled_message_save_check($viewer, $data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], ws_api_scheduled_message_field((string) $checked['field']));
    }

    api_dry_run_stop('updated', 'workspace_scheduled_message', array(
        'id'        => (int) $row['id'],
        'send_at'   => api_time($checked['at']),
        'parent_id' => ((int) $checked['parent_id'] > 0) ? (int) $checked['parent_id'] : null,
    ));

    $result = ws_scheduled_message_save($viewer, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error'], ws_api_scheduled_message_field((string) $result['field']));
    }

    $saved = ws_scheduled_message_mine($viewer, $result['id']);

    if (!$saved) {
        api_fail_not_found(lang('Scheduled message'));
    }

    ws_api_log(lang(array('string' => 'a scheduled message was changed in #{var:1}', 'vars' => (string) $channel['name'])));

    api_ok(ws_api_scheduled_message_present($viewer, $saved));
}

/**
 * POST /workspace/scheduled-messages/{id}/send: posts one of the person's
 * own scheduled messages now, as Send now does. The answer is the posted
 * message.
 */
function ws_api_scheduled_messages_send($params)
{
    $viewer = ws_api_viewer();

    ws_api_scheduled_messages_ready();

    // Posted in the writer's own name.
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can send a scheduled message: it is posted in their own name, while an application key or an OAuth connection writes as the application.'));

    $row = ws_api_scheduled_message_mine_or_404($viewer, $params['id']);

    // The checks the posting meets, made first so that a dry run meets the
    // same refusals.
    $checked = ws_scheduled_message_send_check($viewer, $row);

    if (!$checked['ok']) {
        api_fail_validation($checked['error']);
    }

    api_dry_run_stop('sent', 'workspace_scheduled_message', array('id' => (int) $row['id'], 'channel_id' => (int) $checked['channel']['id']));

    $last = (int) db_value("SELECT MAX(id) FROM ws_messages WHERE channel_id = '" . (int) $checked['channel']['id'] . "'");
    $result = ws_scheduled_message_send_now($viewer, $row);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    // The message it became: the person's newest in the channel since the
    // posting started.
    $posted = db_item("SELECT * FROM ws_messages WHERE channel_id = '" . (int) $checked['channel']['id'] . "' AND id > '" . $last . "'
        AND sender_kind = 'user' AND sender_id = '" . (int) $viewer['id'] . "' ORDER BY id LIMIT 1");

    if (!is_array($posted)) {
        api_fail_not_found(lang('Message'));
    }

    api_ok(ws_api_message_present($viewer, $posted, ws_refs_resolve($viewer, ws_tokens($posted['body']))));
}

function ws_api_scheduled_messages_delete($params)
{
    $viewer = ws_api_viewer();

    ws_api_scheduled_messages_ready();

    $row = ws_api_scheduled_message_mine_or_404($viewer, $params['id']);
    $before = ws_api_scheduled_message_present($viewer, $row);

    api_dry_run_stop('deleted', 'workspace_scheduled_message', array('id' => (int) $row['id']));

    $result = ws_scheduled_message_delete($viewer, $row);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    $channel = ws_channel((int) $row['channel_id']);

    ws_api_log(lang(array('string' => 'a scheduled message was deleted in #{var:1}', 'vars' => $channel ? (string) $channel['name'] : '')));

    api_ok($before);
}

/* ---------------------------------------------------------------------------
   Status updates and check-ins (status.php)
   --------------------------------------------------------------------------- */

// What ws_api_checkin_shape() returns.
function ws_api_checkin_schema()
{
    return array(
        'id'         => 'integer',
        'message_id' => 'integer',
        'channel_id' => 'integer',
        'question'   => 'string',
        'answers'    => 'WorkspaceCheckinAnswer[]',
        'waiting'    => 'string[]',
        'count'      => 'integer',
        'total'      => 'integer',
        'mine'       => 'string?',
        'can_answer' => 'boolean',
    );
}

// One answer to a check-in.
function ws_api_checkin_answer_schema()
{
    return array('user_id' => 'integer', 'name' => 'string', 'text' => 'string');
}

/**
 * A check-in as the API shows it, from the card the channel shows under
 * its message (ws_status_cards_map()). Pure.
 *
 * @param array $card       kind checkin: id, question, answers, waiting, count, total, mine, can_answer
 * @param int   $message_id
 * @param int   $channel_id
 * @return array
 */
function ws_api_checkin_shape($card, $message_id, $channel_id)
{
    return array(
        'id'         => (int) $card['id'],
        'message_id' => (int) $message_id,
        'channel_id' => (int) $channel_id,
        'question'   => (string) $card['question'],
        'answers'    => array_map(function ($answer) {
            return array('user_id' => (int) $answer['id'], 'name' => (string) $answer['name'], 'text' => (string) $answer['text']);
        }, (array) $card['answers']),
        'waiting'    => array_values(array_map('strval', (array) $card['waiting'])),
        'count'      => (int) $card['count'],
        'total'      => (int) $card['total'],
        'mine'       => ((string) $card['mine'] !== '') ? (string) $card['mine'] : null,
        'can_answer' => !empty($card['can_answer']),
    );
}

/**
 * The check-in a message carries, as the API shows it, or a 404.
 *
 * @param array $viewer
 * @param int   $message_id
 * @return array
 */
function ws_api_checkin_present($viewer, $message_id)
{
    $card = ws_status_cards_map($viewer, array((int) $message_id))[(int) $message_id] ?? null;
    $message = ws_message((int) $message_id);

    if (!is_array($card) || ($card['kind'] !== 'checkin') || !$message) {
        api_fail_not_found(lang('Check-in'));
    }

    return ws_api_checkin_shape($card, (int) $message_id, (int) $message['channel_id']);
}

/**
 * Status updates and check-ins are there, or a 503.
 */
function ws_api_status_ready()
{
    if (!function_exists('ws_status_ready') || !ws_status_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * POST /workspace/channels/{id}/status: a status update, as Status update
 * in the channel menu writes it - the message, then the state.
 */
function ws_api_channel_status_post($params)
{
    $viewer = ws_api_viewer();

    ws_api_status_ready();

    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $writer = ws_api_message_writer();
    $health = (string) $params['health'];
    $text = (string) ($params['text'] ?? '');

    // The checks ws_status_post() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_status_post_check($viewer, $channel, $health, $text, $writer);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], in_array($checked['field'], array('health', 'text'), true) ? $checked['field'] : null);
    }

    api_dry_run_stop('created', 'workspace_message', array('channel_id' => (int) $channel['id'], 'health' => $health));

    $result = ws_status_post($viewer, $channel, $health, $text, $writer);

    if (!$result['ok']) {
        api_fail_validation($result['error'], in_array($result['field'], array('health', 'text'), true) ? $result['field'] : null);
    }

    $row = ws_message($result['message_id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), 201);
}

/**
 * POST /workspace/channels/{id}/checkins: asks the channel's people a
 * question, as Check-in in the channel menu does; the panel's question when
 * none is written.
 */
function ws_api_channel_checkins_start($params)
{
    $viewer = ws_api_viewer();

    ws_api_status_ready();

    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $writer = ws_api_message_writer();
    $question = ws_checkin_question((string) ($params['question'] ?? ''));

    if ($question === '') {
        $question = ws_checkin_default_question();
    }

    // The checks ws_checkin_start() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_checkin_start_check($viewer, $channel, $question, $writer);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], ($checked['field'] === 'question') ? 'question' : null);
    }

    api_dry_run_stop('created', 'workspace_checkin', array('channel_id' => (int) $channel['id'], 'question' => $checked['question']));

    $result = ws_checkin_start($viewer, $channel, $question, $writer['app_id'], $writer['via_client_id']);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'question');
    }

    api_ok(ws_api_checkin_present($viewer, $result['message_id']), 201);
}

/**
 * POST /workspace/checkins/{id}/answer: the person's own answer to a
 * check-in, as the card under its message keeps it; an empty text takes
 * it back.
 */
function ws_api_checkins_answer($params)
{
    $viewer = ws_api_viewer();

    ws_api_status_ready();

    // An answer is the person's own, never an application's for them.
    ws_api_person_only();

    $text = (string) ($params['text'] ?? '');

    // The checks ws_checkin_answer() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_checkin_answer_check($viewer, (int) $params['id'], $text);

    if (!$checked['ok']) {
        if ($checked['reason'] === 'missing') {
            api_fail_not_found(lang('Check-in'));
        }

        if ($checked['reason'] === 'forbidden') {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error'], 'text');
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $checked['channel']['id']);

    api_dry_run_stop(($checked['text'] === '') ? 'deleted' : 'updated', 'workspace_checkin_answer', array(
        'checkin_id' => (int) $checked['checkin']['id'],
        'changes'    => ($checked['text'] !== $checked['before']),
    ));

    $result = ws_checkin_answer($viewer, (int) $params['id'], $text);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'text');
    }

    api_ok(ws_api_checkin_present($viewer, $result['message_id']));
}

/* ---------------------------------------------------------------------------
   Earlier versions of a note (notes.php)
   --------------------------------------------------------------------------- */

/**
 * The moment a version's state was saved, from the full date notes.php gives
 * (day.month.year hour:minute, in the site's time zone).
 *
 * @param string $saved_full
 * @return string|null
 */
function ws_api_note_version_time($saved_full)
{
    $parsed = ((string) $saved_full !== '') ? DateTime::createFromFormat('!d.m.Y H:i', (string) $saved_full) : false;

    return ($parsed instanceof DateTime) ? api_time($parsed->getTimestamp()) : null;
}

/**
 * One version as the API shows it: as ws_note_versions() lists it, and with
 * its text when ws_note_version() gave it.
 *
 * @param array      $viewer
 * @param int        $note_id
 * @param array      $entry ws_note_versions()
 * @param array|null $full  ws_note_version()
 * @return array
 */
function ws_api_note_version_present($viewer, $note_id, $entry, $full = null)
{
    return array(
        'id'       => (int) $entry['id'],
        'note_id'  => (int) $note_id,
        'title'    => (string) $entry['title'],
        'saved_by' => ws_api_person_brief_ref($entry['by'] ?? null),
        'saved_at' => ws_api_note_version_time($entry['saved_full'] ?? ''),
        'size'     => (int) $entry['size'],
        'text'     => is_array($full) ? ws_plain_text($viewer, (string) $full['body']) : null,
        'body'     => is_array($full) ? (string) $full['body'] : null,
    );
}

// What ws_api_note_version_present() returns.
function ws_api_note_version_schema()
{
    return array(
        'id'       => 'integer',
        'note_id'  => 'integer',
        'title'    => 'string',
        'saved_by' => 'WorkspacePerson?',
        'saved_at' => 'datetime?',
        'size'     => 'integer',
        'text'     => 'string?',
        'body'     => 'string?',
    );
}

/**
 * The note of a versions request, with the versions there.
 *
 * @return array viewer, note
 */
function ws_api_note_versions_note($params)
{
    $viewer = ws_api_viewer();

    if (!function_exists('ws_note_versions_ready') || !ws_note_versions_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $note = ws_api_note_or_404($viewer, $params['id']);
    ws_api_note_locked_stop($viewer, $note);

    return array($viewer, $note);
}

function ws_api_note_versions_list($params)
{
    list($viewer, $note) = ws_api_note_versions_note($params);
    $out = array();

    foreach (ws_note_versions($viewer, $note) as $entry) {
        $out[] = ws_api_note_version_present($viewer, $note['id'], $entry);
    }

    api_ok_list($out, count($out));
}

function ws_api_note_versions_get($params)
{
    list($viewer, $note) = ws_api_note_versions_note($params);
    $full = ws_note_version($viewer, $note, (int) $params['version_id']);

    if (!$full) {
        api_fail_not_found(lang('Version'));
    }

    // Who saved it and when, as the list gives it.
    foreach (ws_note_versions($viewer, $note) as $entry) {
        if ((int) $entry['id'] === (int) $full['id']) {
            api_ok(ws_api_note_version_present($viewer, $note['id'], $entry, $full));
        }
    }

    api_ok(ws_api_note_version_present($viewer, $note['id'], array('id' => $full['id'], 'title' => $full['title'], 'size' => mb_strlen((string) $full['body'])), $full));
}
