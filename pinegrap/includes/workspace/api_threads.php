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
 * signed in on a device, schedules one; an application may list and cancel
 * the ones its owner wrote.
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
        'started_by'      => ws_api_person_ref($thread['created_by']),
        'closed_by'       => ws_api_person_ref($thread['closed_by']),
        'closed_at'       => api_time($thread['closed_at']),
        'kept_until'      => api_time($thread['purge_at']),
        'last_message_at' => api_time($channel['last_message_at']),
        'created_at'      => api_time($thread['created_at']),
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
        'started_by'      => array('user_id' => 'integer', 'name' => 'string'),
        'closed_by'       => array('user_id' => 'integer', 'name' => 'string'),
        'closed_at'       => 'string?',
        'kept_until'      => 'string?',
        'last_message_at' => 'string?',
        'created_at'      => 'string?',
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
        'send_at'    => 'string?',
        'status'     => 'string',
        'error'      => 'string?',
        'created_at' => 'string?',
        'updated_at' => 'string?',
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

    // Posted later in the writer's own name, so the writer is the one who
    // schedules it.
    ws_api_person_only(lang('Only the person, signed in on a device, can schedule a message: it is posted later in their own name, not as the application.'));

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    // The moment, read on the server's clock to the minute, as the date and
    // the time of the writing box are.
    $at = (int) $params['send_at'];
    $date = date('Y-m-d', $at);
    $time = date('H:i', $at);

    // The checks ws_scheduled_message_save() makes, made first so that a dry
    // run meets the same refusals.
    $body = (string) $params['text'];

    if (function_exists('ws_claude_command_body')) {
        $body = ws_claude_command_body($body);
    }

    if (function_exists('ws_ai_command_body')) {
        $body = ws_ai_command_body($body);
    }

    $body = ws_tokens_normalise(trim($body));

    if ($body === '') {
        api_fail_validation(lang('The message is empty.'), 'text');
    }

    if (preg_match('#^/[a-z]#i', $body)) {
        api_fail_validation(lang('A command cannot be scheduled. Write it as a message, or send it now.'), 'text');
    }

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        api_fail_validation(lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)), 'text');
    }

    $moment = strtotime($date . ' ' . $time . ':00');

    if ($moment <= time()) {
        api_fail_validation(lang('That time has already gone by.'), 'send_at');
    }

    if ($moment > strtotime('+' . WS_SCHEDULED_MESSAGES_AHEAD . ' days')) {
        api_fail_validation(lang('A message can be scheduled at most a year ahead.'), 'send_at');
    }

    $waiting = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_actions
        WHERE kind = 'message' AND created_by = '" . (int) $viewer['id'] . "' AND status IN ('active', 'failed')");

    if ($waiting >= WS_SCHEDULED_MESSAGES_MAX) {
        api_fail_validation(lang(array('string' => 'You already have {var:1} messages waiting to be posted.', 'vars' => WS_SCHEDULED_MESSAGES_MAX)));
    }

    api_dry_run_stop('created', 'workspace_scheduled_message', array('channel_id' => (int) $channel['id'], 'send_at' => api_time($moment)));

    $result = ws_scheduled_message_save($viewer, array(
        'id'         => 0,
        'channel_id' => (int) $channel['id'],
        'body'       => (string) $params['text'],
        'date'       => $date,
        'time'       => $time,
    ));

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

function ws_api_scheduled_messages_delete($params)
{
    $viewer = ws_api_viewer();

    ws_api_scheduled_messages_ready();

    // The owner's own, still waiting or failed; somebody else's, or one
    // already posted, is not there for them.
    $row = ws_scheduled_message_mine($viewer, (int) $params['id']);

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
        'saved_by' => array('user_id' => 'integer', 'name' => 'string'),
        'saved_at' => 'string?',
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

    return array($viewer, ws_api_note_or_404($viewer, $params['id']));
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
