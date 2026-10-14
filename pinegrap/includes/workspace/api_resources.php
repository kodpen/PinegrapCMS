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
 * Does a call made from this device row write as the person? A team devices
 * session does: the phone is the person's own, not an integration, so their
 * words carry their name the way the panel's do. An OAuth connection is a
 * device row too, and keeps writing as the application until it is settled
 * how a connection's words are shown.
 *
 * @param array|null $device api_devices row, null for an application key
 * @return bool
 */
function ws_api_device_writes_as_person($device)
{
    return is_array($device) && ((int) ($device['oauth_client_id'] ?? 0) <= 0);
}

/**
 * The application a message, reaction or note written now is shown under:
 * 0 when the person writes from their own device, so the rate limit, joining
 * a public channel and the read mark apply to them as in the panel.
 *
 * @return int
 */
function ws_api_writer_app_id()
{
    $device = function_exists('api_current_device') ? api_current_device() : null;

    return ws_api_device_writes_as_person($device) ? 0 : ws_api_app_id();
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
        'notify'        => (string) ($brief['notify'] ?? 'all'),
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
        'notify'          => 'string',
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

/**
 * The discussions started from a set of messages, as the message object
 * shows them, and the discussions the copies among them were decided in
 * (threads.php): one read each for the whole set, and one more for the
 * moment each discussion was last written in.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array threads (message id => array), copies (message id => thread id)
 */
function ws_api_message_threads($viewer, $message_ids)
{
    $out = array('threads' => array(), 'copies' => array());

    if (!function_exists('ws_threads_ready') || !ws_threads_ready()) {
        return $out;
    }

    $map = ws_threads_map($viewer, $message_ids);
    $last = array();

    if (!empty($map)) {
        $thread_ids = array();

        foreach ($map as $present) {
            $thread_ids[] = (int) $present['id'];
        }

        foreach ((array) db_items("SELECT id, last_message_at FROM ws_channels WHERE id IN (" . implode(',', $thread_ids) . ")") as $channel) {
            $last[(int) $channel['id']] = (int) $channel['last_message_at'];
        }
    }

    foreach ($map as $message_id => $present) {
        $out['threads'][(int) $message_id] = array(
            'id'              => (int) $present['id'],
            'title'           => (string) $present['title'],
            'message_count'   => (int) $present['count'],
            'last_message_at' => api_time($last[(int) $present['id']] ?? 0),
            'closed'          => !empty($present['closed']),
        );
    }

    // A copy whose discussion is gone says so with thread id 0.
    foreach (ws_thread_copies_map($message_ids) as $message_id => $copy) {
        if ((int) $copy['thread_id'] > 0) {
            $out['copies'][(int) $message_id] = (int) $copy['thread_id'];
        }
    }

    return $out;
}

function ws_api_message_present($viewer, $row, $refs, $reactions = null, $acks = null, $threads = null, $hidden = null, $polls = null)
{
    // The messages the reader deleted for themselves (ws_message_hide()).
    if ($hidden === null) {
        $hidden = ws_message_hidden_ids($viewer['id'], array($row['id']));
    }

    // The poll it asks (interact.php).
    if ($polls === null) {
        $polls = ws_polls_map(array($row['id']), $viewer);
    }

    if ($reactions === null) {
        $reactions = ws_reactions_map(array($row['id']), $viewer);
    }

    // The read receipt it asks for (acks.php).
    if ($acks === null) {
        $acks = function_exists('ws_acks_map') ? ws_acks_map(array($row), $viewer) : array();
    }

    // The discussion about it, or the one it was copied from (threads.php).
    if ($threads === null) {
        $threads = ws_api_message_threads($viewer, array($row['id']));
    }

    $ack = $acks[(int) $row['id']] ?? null;

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
        $stored = db_item("SELECT name, size FROM files WHERE id = '" . (int) $row['file_id'] . "'");

        if (is_array($stored) && ((string) $stored['name'] !== '')) {
            $file = array(
                'name'    => (string) $row['file_name'],
                'kind'    => ws_file_kind($row['file_name']),
                'size'    => (int) $stored['size'],
                'url'     => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path((string) $stored['name']),
                'api_url' => ws_api_file_url($row['id']),
            );
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
        'hidden'      => isset($hidden[(int) $row['id']]),
        'edited'      => ((int) $row['edited_at'] > 0),
        'reactions'   => $deleted ? array() : $emoji,
        'ack'         => array(
            'wanted' => ($ack !== null),
            'count'  => ($ack !== null) ? (int) $ack['count'] : 0,
            'total'  => ($ack !== null) ? (int) $ack['total'] : 0,
            'mine'   => ($ack !== null) && !empty($ack['mine']),
        ),
        'thread'         => $deleted ? null : ($threads['threads'][(int) $row['id']] ?? null),
        'from_thread_id' => $deleted ? null : ($threads['copies'][(int) $row['id']] ?? null),
        'poll'           => (!$deleted && isset($polls[(int) $row['id']])) ? ws_api_poll_present($polls[(int) $row['id']]) : null,
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
        'file'        => array('name' => 'string', 'kind' => 'string', 'size' => 'integer', 'url' => 'string', 'api_url' => 'string'),
        'deleted'     => 'boolean',
        'hidden'      => 'boolean',
        'edited'      => 'boolean',
        'reactions'   => array(array('emoji' => 'string', 'count' => 'integer')),
        'ack'         => array('wanted' => 'boolean', 'count' => 'integer', 'total' => 'integer', 'mine' => 'boolean'),
        'thread'         => array('id' => 'integer', 'title' => 'string', 'message_count' => 'integer', 'last_message_at' => 'string?', 'closed' => 'boolean'),
        'from_thread_id' => 'integer?',
        'poll'           => 'WorkspacePoll?',
        'created_at'  => 'string',
    );
}

/**
 * The poll a message asks, as the API shows it, from what the channel screen
 * shows of it for this reader (ws_polls_map()). Who chose an option is left
 * out of an anonymous poll; a guest who voted has no user id.
 *
 * @param array $poll one entry of ws_polls_map()
 * @return array
 */
function ws_api_poll_present($poll)
{
    return array(
        'id'                => (int) $poll['id'],
        'multiple'          => !empty($poll['multiple']),
        'anonymous'         => !empty($poll['anonymous']),
        'closed'            => !empty($poll['closed']),
        'closes_at'         => api_time($poll['closes_at']),
        'voters'            => (int) $poll['voters'],
        'voted'             => !empty($poll['voted']),
        'can_vote'          => !empty($poll['can_vote']),
        'options'           => array_map('ws_api_poll_option_present', array_values((array) $poll['options'])),
        'result_message_id' => ((int) $poll['result_id'] > 0) ? (int) $poll['result_id'] : null,
    );
}

/**
 * One option of a poll, with who chose it.
 *
 * @param array $option one of ws_polls_map()'s options
 * @return array
 */
function ws_api_poll_option_present($option)
{
    $voters = array();

    foreach ((array) $option['voters'] as $voter) {
        $voters[] = array('user_id' => ((int) $voter['id'] > 0) ? (int) $voter['id'] : null, 'name' => (string) $voter['name']);
    }

    return array(
        'id'      => (int) $option['id'],
        'label'   => (string) $option['label'],
        'count'   => (int) $option['count'],
        'mine'    => !empty($option['mine']),
        'leading' => !empty($option['leading']),
        'voters'  => $voters,
    );
}

// What ws_api_poll_present() returns.
function ws_api_poll_schema()
{
    return array(
        'id'                => 'integer',
        'multiple'          => 'boolean',
        'anonymous'         => 'boolean',
        'closed'            => 'boolean',
        'closes_at'         => 'string?',
        'voters'            => 'integer',
        'voted'             => 'boolean',
        'can_vote'          => 'boolean',
        'options'           => array(array('id' => 'integer', 'label' => 'string', 'count' => 'integer', 'mine' => 'boolean', 'leading' => 'boolean',
            'voters' => array(array('user_id' => 'integer?', 'name' => 'string')))),
        'result_message_id' => 'integer?',
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

    if (isset($params['changed_since'])) {
        // The messages already written that changed since a moment - an
        // edit, a deletion, a reaction, a tick, a vote, a read receipt - the
        // way the panel keeps an open channel up to date (ws_sync). since_id
        // pages through them; the current conversation only.
        if (!ws_interact_ready()) {
            api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
        }

        $after = max((int) ($params['since_id'] ?? 0), ws_channel_floor($channel['id']));
        $rows = ws_messages_touched($channel['id'], max(1, (int) $params['changed_since']), PHP_INT_MAX, $after, $limit);
    } elseif (!empty($params['since_id'])) {
        $rows = array_slice(ws_messages_since($channel['id'], (int) $params['since_id']), 0, $limit);
    } else {
        $rows = ws_messages_page($channel['id'], (int) ($params['before_id'] ?? 0), $limit);
    }

    api_ok_list(ws_api_messages_present($viewer, $rows), $limit);
}

/**
 * Messages as the API shows them, with their tags, reactions, read receipts
 * and discussions read once for the whole set.
 *
 * @param array   $viewer
 * @param array[] $rows ws_messages rows
 * @return array[]
 */
function ws_api_messages_present($viewer, $rows)
{
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
    $acks = function_exists('ws_acks_map') ? ws_acks_map($rows, $viewer) : array();
    $threads = ws_api_message_threads($viewer, $ids);
    $hidden = ws_message_hidden_ids($viewer['id'], $ids);
    $polls = ws_polls_map($ids, $viewer);
    $out = array();

    // A message the person deleted for themselves stays in the page, marked
    // hidden, so that since_id and before_id still page past it.
    foreach ($rows as $row) {
        $out[] = ws_api_message_present($viewer, $row, $refs, $reactions, $acks, $threads, $hidden, $polls);
    }

    return $out;
}

function ws_api_messages_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $kind = (string) ($params['kind'] ?? 'message');
    $ack = !empty($params['ack']);
    $app_id = ws_api_writer_app_id();

    // A read receipt is asked for only where it may be, and that is settled
    // before anything is written. The message about to be written comes
    // after every message the channel holds, so it is never in an earlier
    // version of the conversation.
    if ($ack) {
        if (!function_exists('ws_acks_ready') || !ws_acks_ready()) {
            api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
        }

        $about_to_write = array(
            'id'          => PHP_INT_MAX,
            'channel_id'  => (int) $channel['id'],
            'kind'        => $kind,
            'sender_kind' => ($app_id > 0) ? 'app' : 'user',
            'sender_id'   => ($app_id > 0) ? $app_id : (int) $viewer['id'],
            'deleted_at'  => 0,
        );

        if (!ws_ack_can_request($viewer, $about_to_write)) {
            api_fail(403, 'forbidden', lang('Only the writer of the message, or staff, can ask for read receipts.'));
        }
    }

    $result = ws_message_send($viewer, $channel, (string) $params['text'], array(
        'kind'   => $kind,
        'app_id' => $app_id,
    ));

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'text');
    }

    $row = ws_message($result['message_id']);

    if ($ack) {
        ws_ack_request($viewer, $row, true);
    }

    // @Claude, @ai or an MCP server's alias written from the person's own
    // device asks as it does from the panel's composer.
    if (ws_api_message_asks_assistants($app_id, $kind)) {
        ws_api_assistants_carry_on(ws_message_ask_assistants($viewer, $channel, $row['id'], (string) $params['text']));
    }

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), 201);
}

/**
 * Does a message written now ask the assistants it names? Only a person's
 * own message, as the panel's composer writes it: an application's words
 * never ask one (an agent quoting an alias in its answer would ask itself
 * again), and a decision or a note is kept, not asked.
 *
 * @param int    $app_id ws_api_writer_app_id()
 * @param string $kind   message | note | decision
 * @return bool
 */
function ws_api_message_asks_assistants($app_id, $kind)
{
    return ((int) $app_id === 0) && ((string) $kind === 'message');
}

/**
 * Where an API call carries Pinegrap AI and an MCP agent on: 'after' the
 * answer has gone back, where the server can send it first (PHP-FPM);
 * 'here', before the answer, where it cannot (IIS, mod_php) but the caller
 * is an open app that waits for news anyway (GET /workspace/sync); ''
 * otherwise, and the app's next sync, the screens that have the channel open
 * and the hourly job carry them on.
 *
 * @param bool $answer_first fastcgi_finish_request() exists
 * @param bool $here         the call may be held while the work is done
 * @return string after | here | ''
 */
function ws_api_carry_on_where($answer_first, $here)
{
    return $answer_first ? 'after' : ($here ? 'here' : '');
}

/**
 * Carries on what a message from the person's device asked, or what waits
 * in the channel an open app syncs. Claude's routine is started at once, as
 * for a task handed over: one short call to the routine's trigger. Pinegrap
 * AI and an MCP agent take a model call or the agent's answer, so they run
 * where ws_api_carry_on_where() says.
 *
 * @param array $asked ws_message_ask_assistants()
 * @param bool  $here  the call may be held while the work is done
 */
function ws_api_assistants_carry_on($asked, $here = false)
{
    if (!empty($asked['claude']) && function_exists('ws_claude_dispatch')) {
        ws_claude_dispatch();
    }

    $agent = !empty($asked['agent']) && function_exists('ws_mcp_agent_kick');
    $ai = !empty($asked['ai']) && function_exists('ws_ai_kick');
    $where = ws_api_carry_on_where(function_exists('fastcgi_finish_request'), $here);

    if ((!$agent && !$ai) || ($where === '')) {
        return;
    }

    // One agent call and one model call at most, so the app's sync is held
    // no longer than that; its next sync takes the next step.
    if ($where === 'here') {
        if ($agent) {
            ws_mcp_agent_kick();
        }

        if ($ai) {
            ws_ai_kick(1);
        }

        return;
    }

    register_shutdown_function(function () use ($agent, $ai) {
        fastcgi_finish_request();

        if ($agent) {
            ws_mcp_agent_kick();
        }

        if ($ai) {
            ws_ai_kick();
        }
    });
}

/**
 * Where the external API hands out a message's file with the caller's own
 * token (GET /workspace/messages/{id}/file).
 *
 * @param int $message_id
 * @return string
 */
function ws_api_file_url($message_id)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/integration.php/workspace/messages/' . (int) $message_id . '/file';
}

/**
 * The file a call posts: a form part named file, or content_base64 with a
 * name. A form part is read only off a request of its own; a route run by
 * the MCP endpoint has none.
 *
 * @param array $params
 * @return array name, base64 (string) or bytes (string), field
 */
function ws_api_upload_input($params)
{
    $part = null;

    if ((api_send_hook() === null) && isset($_FILES['file']) && is_array($_FILES['file'])) {
        $part = $_FILES['file'];
    }

    $base64 = (string) ($params['content_base64'] ?? '');
    $name = trim((string) ($params['name'] ?? ''));

    // A form part is held to the server's own upload limit as well, which
    // may be the lower of the two.
    $limits = function_exists('pg_upload_limits') ? pg_upload_limits() : array();
    $form_max = ws_upload_max_bytes();

    if (((int) ($limits['file_max'] ?? 0) > 0) && ((int) $limits['file_max'] < $form_max)) {
        $form_max = (int) $limits['file_max'];
    }

    $too_large = lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor($form_max / 1048576)));

    if (($part !== null) && ($base64 !== '')) {
        api_fail_validation(lang('Send the file once: as a form part named file, or as content_base64.'), 'content_base64');
    }

    if ($part !== null) {
        $error = is_array($part['error']) ? UPLOAD_ERR_NO_FILE : (int) $part['error'];

        if (in_array($error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
            api_fail_validation($too_large, 'file');
        }

        if (($error !== UPLOAD_ERR_OK) || !is_uploaded_file((string) $part['tmp_name'])) {
            api_fail_validation(lang('The file could not be read.'), 'file');
        }

        if ((int) @filesize((string) $part['tmp_name']) > $form_max) {
            api_fail_validation($too_large, 'file');
        }

        return array(
            'name'  => ($name !== '') ? $name : (string) $part['name'],
            'bytes' => (string) @file_get_contents((string) $part['tmp_name']),
            'field' => 'file',
        );
    }

    // A form larger than the server takes arrives with nothing in it.
    if (($base64 === '') && (api_send_hook() === null) && function_exists('pg_post_body_discarded') && pg_post_body_discarded()
        && (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? api_header('Content-Type')), 'multipart/form-data') !== false)) {
        api_fail_validation($too_large, 'file');
    }

    if ($base64 === '') {
        api_fail_validation(lang('Send the file as a form part named file, or as content_base64.'), 'content_base64');
    }

    if ($name === '') {
        api_fail_validation(lang('Name the file, with its extension.'), 'name');
    }

    // Line breaks some encoders add are still the file.
    return array('name' => $name, 'base64' => preg_replace('/[\r\n\t]+/', '', $base64), 'field' => 'content_base64');
}

/**
 * Posts a file into a channel as a message carrying it, the way the panel's
 * paperclip does (ws_attach).
 */
function ws_api_files_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $app_id = ws_api_writer_app_id();

    // The person's limit is met before the file is kept, so a message that
    // would be refused leaves no file behind.
    if (($app_id === 0) && ws_rate_limited($viewer['id'])) {
        api_fail_validation(lang('You are sending messages too quickly. Please wait a moment.'));
    }

    $upload = ws_api_upload_input($params);
    $stored = isset($upload['bytes'])
        ? ws_store_upload_bytes($viewer, $channel, $upload['name'], $upload['bytes'])
        : ws_store_upload($viewer, $channel, $upload['name'], $upload['base64']);

    if (!$stored['ok']) {
        if ($stored['error'] === lang('The file could not be saved.')) {
            api_fail_server($stored['error']);
        }

        api_fail_validation($stored['error'], $upload['field']);
    }

    $result = ws_message_send($viewer, $channel, (string) ($params['text'] ?? ''), array(
        'app_id'    => $app_id,
        'file_id'   => $stored['file_id'],
        'file_name' => $stored['name'],
    ));

    if (!$result['ok']) {
        ws_upload_discard($stored['file_id']);
        api_fail_validation($result['error'], 'text');
    }

    $row = ws_message($result['message_id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), 201);
}

/**
 * Sends the file a message carries to whoever may read the message, as its
 * own bytes.
 */
function ws_api_files_get($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || ((int) $message['file_id'] <= 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('File'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    $stored = basename((string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'"));
    $path = FILE_DIRECTORY_PATH . '/' . $stored;

    if (($stored === '') || !is_file($path)) {
        api_fail_not_found(lang('File'));
    }

    $type = ws_file_type($message['file_name']);

    api_send_file($path, $type['mime'], (string) $message['file_name'], $type['inline']);
}

function ws_api_messages_react($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    $on = !array_key_exists('on', $params) || !empty($params['on']);
    $result = ws_reaction_set($viewer, $message, (string) $params['emoji'], ws_api_writer_app_id(), $on);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'emoji');
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

/**
 * Answers that must be the person's own - a read receipt, an approval, a
 * channel marked read - are given from a device session only: an
 * application key acts for its owner, and an integration does not read or
 * approve in their name.
 *
 * @param string|null $refusal what the refusal says, when not an answer
 */
function ws_api_person_only($refusal = null)
{
    if (!function_exists('api_current_device') || (api_current_device() === null)) {
        api_fail(403, 'forbidden', ($refusal !== null) ? $refusal : lang('Only the person, signed in on a device, can give this answer; an application cannot give it for them.'));
    }
}

/**
 * A change to the person's own words comes from their team devices session
 * only (ws_api_device_writes_as_person()): an application key writes as the
 * application, and an OAuth connection writes as itself until it is settled
 * how a connection's words are shown, so neither rewrites what the person
 * wrote. Deleting a message from the person's own view is held to the same,
 * since it cannot be undone.
 *
 * @param string|null $refusal what the refusal says, when not a change or a deletion
 */
function ws_api_own_words_only($refusal = null)
{
    $device = function_exists('api_current_device') ? api_current_device() : null;

    if (!ws_api_device_writes_as_person($device)) {
        api_fail(403, 'forbidden', ($refusal !== null) ? $refusal : lang('Only the person, signed in on a device, can change or delete their messages; an application cannot do it for them.'));
    }
}

/**
 * A message the owner may read, not deleted, as the edit and the deletion
 * find it: anything else is a 404, and one of an earlier version of the
 * conversation a 422.
 *
 * @param array $viewer
 * @param int   $id
 * @return array ws_messages row
 */
function ws_api_message_or_404($viewer, $id)
{
    $message = ws_message((int) $id);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    // An earlier version of a conversation is kept as it was (eras.php).
    if (function_exists('ws_message_in_past') && ws_message_in_past($message)) {
        api_fail_validation(lang('This message is in an earlier version of the conversation, which is kept as it was.'));
    }

    return $message;
}

/**
 * A refusal of ws_message_edit() or ws_message_delete() as the API answers it.
 *
 * @param string      $error
 * @param string|null $field
 */
function ws_api_message_refused($error, $field = null)
{
    $forbidden = array(
        lang('You can only change your own messages.'),
        lang('You can only delete your own messages.'),
        lang('You cannot post in that channel.'),
        lang('This decision records a change to a record. People with the User role cannot delete it.'),
    );

    if (in_array($error, $forbidden, true)) {
        api_fail(403, 'forbidden', $error);
    }

    if ($error === lang('Access denied.')) {
        api_fail_not_found(lang('Message'));
    }

    api_fail_validation($error, $field);
}

function ws_api_messages_update($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);

    ws_api_own_words_only();

    // The checks ws_message_edit() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_message_edit_check($viewer, $message, (string) $params['text']);

    if (!$checked['ok']) {
        ws_api_message_refused($checked['error'], 'text');
    }

    api_dry_run_stop('updated', 'workspace_message', array('id' => (int) $message['id'], 'channel_id' => (int) $message['channel_id']));

    $result = ws_message_edit($viewer, $message, (string) $params['text']);

    if (!$result['ok']) {
        ws_api_message_refused($result['error'], 'text');
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

function ws_api_messages_delete($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);

    ws_api_own_words_only();

    $checked = ws_message_delete_check($viewer, $message);

    if (!$checked['ok']) {
        ws_api_message_refused($checked['error']);
    }

    api_dry_run_stop('deleted', 'workspace_message', array('id' => (int) $message['id'], 'channel_id' => (int) $message['channel_id'], 'mine' => $checked['mine']));

    $result = ws_message_delete($viewer, $message);

    if (!$result['ok']) {
        ws_api_message_refused($result['error']);
    }

    api_ok(ws_api_message_present($viewer, ws_message($message['id']), array()));
}

/**
 * Deletes a message from the person's own view, the way Delete for me in the
 * message menu does: whoever wrote it, also in an earlier version of the
 * conversation, but not a line the workspace wrote itself.
 */
function ws_api_messages_hide($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || ($message['kind'] === 'system') || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    ws_api_own_words_only(lang('Only the person, signed in on a device, can delete a message from their own view; an application cannot do it for them.'));

    if (!ws_message_hides_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $checked = ws_message_hide_check($viewer, $message);

    if (!$checked['ok']) {
        api_fail_not_found(lang('Message'));
    }

    api_dry_run_stop('hidden', 'workspace_message', array('id' => (int) $message['id'], 'channel_id' => (int) $message['channel_id']));

    $result = ws_message_hide($viewer, $message);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

/**
 * Marks a channel read up to a message, the way opening it in the panel does:
 * its unread count and the mentions in it up to there go.
 */
function ws_api_channels_read($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only();

    if (!ws_channel_membership($channel['id'], $viewer['id'])) {
        api_fail(409, 'not_member', lang('Only the people in a channel keep a read mark in it.'));
    }

    if (!empty($params['message_id'])) {
        $message = ws_message((int) $params['message_id']);

        if (!$message || ((int) $message['channel_id'] !== (int) $channel['id'])) {
            api_fail_validation(lang('That message could not be found.'), 'message_id');
        }

        $message_id = (int) $message['id'];
    } else {
        $message_id = (int) db_value("SELECT MAX(id) FROM ws_messages WHERE channel_id = '" . (int) $channel['id'] . "'");
    }

    api_dry_run_stop('read', 'workspace_channel', array('id' => (int) $channel['id'], 'message_id' => $message_id));

    ws_channel_mark_read($channel['id'], $viewer['id'], $message_id);

    api_ok(ws_api_channel_present(ws_api_channel_brief($viewer, ws_channel($channel['id']))));
}

/**
 * How a channel tells the person about what is written in it, the way the
 * bell in the channel menu sets it: all, mentions or none. A mention in a
 * channel set to none reaches neither the bell nor the person's devices.
 * Choosing it in a public channel the person is not in joins them to it, as
 * the panel does.
 */
function ws_api_channels_notify($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can choose how a channel notifies them; an application cannot choose it for them.'));

    $member = (bool) ws_channel_membership($channel['id'], $viewer['id']);

    if (!$member && ($channel['kind'] !== 'public')) {
        api_fail(409, 'not_member', lang('Only the people in a channel choose how it notifies them.'));
    }

    api_dry_run_stop('updated', 'workspace_channel', array('id' => (int) $channel['id'], 'notify' => (string) $params['notify'], 'joins' => !$member));

    $result = ws_channel_set_notify($viewer, $channel, (string) $params['notify']);

    if (!$result['ok']) {
        ws_api_channel_refused($result);
    }

    api_ok(ws_api_channel_present(ws_api_channel_brief($viewer, ws_channel($channel['id']))));
}

function ws_api_messages_ack($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    ws_api_person_only();

    $result = function_exists('ws_ack_set') ? ws_ack_set($viewer, $message) : array('ok' => false, 'error' => lang('Invalid request.'));

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

/**
 * Casts the person's vote in a poll, the way the poll in a channel does: the
 * options chosen replace the ones chosen before, and an empty list takes the
 * vote back. The answer is the message that asks the poll.
 */
function ws_api_polls_vote($params)
{
    $viewer = ws_api_viewer();
    $poll = ws_poll((int) $params['id']);
    $message = $poll ? ws_message((int) $poll['message_id']) : null;

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($poll['channel_id']))) {
        api_fail_not_found(lang('Poll'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $poll['channel_id']);

    ws_api_person_only();

    // An earlier version of a conversation is kept as it was (eras.php).
    if (function_exists('ws_message_in_past') && ws_message_in_past($message)) {
        api_fail_validation(lang('This message is in an earlier version of the conversation, which is kept as it was.'));
    }

    $checked = ws_poll_vote_check($viewer, $poll);

    if (!$checked['ok']) {
        // A vote after the poll's time closes it, as in the panel; a dry run
        // leaves that to the next vote or the hourly job.
        if ($checked['expired'] && !api_dry_run_requested()) {
            ws_poll_close(null, $poll);
        }

        if ($checked['error'] === lang('You cannot post in that channel.')) {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error']);
    }

    $options = array_map('intval', (array) db_values("SELECT id FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "'"));
    $chosen = array();

    foreach ((array) $params['option_ids'] as $item) {
        $option_id = (is_int($item) || (is_string($item) && ctype_digit($item))) ? (int) $item : 0;

        if (!in_array($option_id, $options, true)) {
            api_fail_validation(lang(array('string' => 'Each item of {var:1} must be the id of one of the poll\'s options.', 'vars' => 'option_ids')), 'option_ids');
        }

        $chosen[$option_id] = $option_id;
    }

    $chosen = array_values($chosen);

    if ((count($chosen) > 1) && ((int) $poll['multiple'] !== 1)) {
        api_fail_validation(lang('Only one option can be chosen in this poll.'), 'option_ids');
    }

    api_dry_run_stop('voted', 'workspace_poll', array('id' => (int) $poll['id'], 'message_id' => (int) $poll['message_id'], 'option_ids' => $chosen));

    $result = ws_poll_vote($viewer, $poll, $chosen);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

function ws_api_messages_ack_request($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    if (!function_exists('ws_acks_ready') || !ws_acks_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    if (!ws_ack_can_request($viewer, $message)) {
        api_fail(403, 'forbidden', lang('Only the writer of the message, or staff, can ask for read receipts.'));
    }

    $on = !array_key_exists('on', $params) || !empty($params['on']);

    api_dry_run_stop($on ? 'requested' : 'withdrawn', 'workspace_read_receipt', array('message_id' => (int) $message['id'], 'on' => $on));

    $result = ws_ack_request($viewer, $message, $on);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

/* ---------------------------------------------------------------------------
   Approval requests (approvals.php)
   --------------------------------------------------------------------------- */

/**
 * One request as the API shows it.
 *
 * @param array $viewer
 * @param array $row ws_approvals row
 * @return array
 */
function ws_api_approval_present($viewer, $row)
{
    $answers = ws_approval_people($row['id']);
    $people = array();
    $names = array((int) $row['created_by'], (int) $row['closed_by']);

    foreach ($answers as $answer) {
        $names[] = (int) $answer['user_id'];
    }

    $briefs = ws_people($names);

    foreach ($answers as $answer) {
        $people[] = ws_api_approval_person_present($answer, $briefs);
    }

    // People outside the team: their address is for staff alone.
    $guests = array();
    $staff = !empty($viewer['member']) && ((int) ($viewer['role'] ?? 3) <= 2);

    foreach (function_exists('ws_approval_guests_of') ? ws_approval_guests_of($row['id']) : array() as $guest) {
        $guests[] = ws_api_approval_guest_present($guest, $staff);
    }

    $message = ws_message($row['message_id']);

    return array(
        'id'                => (int) $row['id'],
        'message_id'        => (int) $row['message_id'],
        'channel_id'        => (int) $row['channel_id'],
        'title'             => (string) $row['title'],
        // The details: the message without the title line it starts with.
        'text'              => $message ? ws_plain_text($viewer, preg_replace('/^\*\*[^\n]*\*\*\n*/u', '', (string) $message['body'])) : '',
        'rule'              => (string) $row['rule'],
        'record'            => ((string) $row['record_type'] !== '') ? array('type' => (string) $row['record_type'], 'id' => (int) $row['record_id']) : null,
        'people'            => $people,
        'guests'            => $guests,
        'outcome'           => (string) $row['outcome'],
        'requested_by'      => ws_api_person_ref($row['created_by'], $briefs),
        'closed_by'         => ws_api_person_ref($row['closed_by'], $briefs),
        'result_message_id' => ((int) $row['result_message_id'] > 0) ? (int) $row['result_message_id'] : null,
        'closes_at'         => api_time($row['closes_at']),
        'closed_at'         => api_time($row['closed_at']),
        'created_at'        => api_time($row['created_at']),
    );
}

/**
 * One person asked, with their answer.
 *
 * @param array $answer ws_approval_people row
 * @param array $briefs ws_people()
 * @return array
 */
function ws_api_approval_person_present($answer, $briefs)
{
    return array(
        'user_id'    => (int) $answer['user_id'],
        'name'       => (string) ($briefs[(int) $answer['user_id']]['name'] ?? ''),
        'decision'   => (string) $answer['decision'],
        'note'       => ((string) $answer['note'] !== '') ? (string) $answer['note'] : null,
        'decided_at' => api_time($answer['decided_at']),
    );
}

/**
 * One person asked from outside the team, with their answer.
 *
 * @param array $guest ws_approval_guests row
 * @param bool  $staff the reader may see the address
 * @return array
 */
function ws_api_approval_guest_present($guest, $staff)
{
    return array(
        'id'              => (int) $guest['id'],
        'name'            => (string) $guest['name'],
        'email'           => $staff ? (string) $guest['email'] : null,
        'decision'        => (string) $guest['decision'],
        'note'            => ((string) $guest['note'] !== '') ? (string) $guest['note'] : null,
        'decided_at'      => api_time($guest['decided_at']),
        'link_expires_at' => ((string) $guest['decision'] === 'pending') ? api_time($guest['expires_at']) : null,
    );
}

// What ws_api_approval_present() returns.
function ws_api_approval_schema()
{
    return array(
        'id'                => 'integer',
        'message_id'        => 'integer',
        'channel_id'        => 'integer',
        'title'             => 'string',
        'text'              => 'string',
        'rule'              => 'string',
        'record'            => array('type' => 'string', 'id' => 'integer'),
        'people'            => array(array('user_id' => 'integer', 'name' => 'string', 'decision' => 'string', 'note' => 'string?', 'decided_at' => 'string?')),
        'guests'            => array(array('id' => 'integer', 'name' => 'string', 'email' => 'string?', 'decision' => 'string', 'note' => 'string?', 'decided_at' => 'string?', 'link_expires_at' => 'string?')),
        'outcome'           => 'string',
        'requested_by'      => array('user_id' => 'integer', 'name' => 'string'),
        'closed_by'         => array('user_id' => 'integer', 'name' => 'string'),
        'result_message_id' => 'integer?',
        'closes_at'         => 'string?',
        'closed_at'         => 'string?',
        'created_at'        => 'string',
    );
}

function ws_api_approval_or_404($viewer, $id)
{
    $approval = function_exists('ws_approval') ? ws_approval((int) $id) : null;

    if (!$approval) {
        api_fail_not_found(lang('Approval request'));
    }

    // The channel it is in decides who reads it, as for its messages.
    ws_api_channel_or_404($viewer, $approval['channel_id']);

    $message = ws_message($approval['message_id']);

    if (!$message || ((int) $message['deleted_at'] > 0)) {
        api_fail_not_found(lang('Approval request'));
    }

    return $approval;
}

function ws_api_approvals_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $limit = (int) ($params['limit'] ?? 50);
    $state = (string) ($params['state'] ?? 'all');

    if (!function_exists('ws_approvals_ready') || !ws_approvals_ready()) {
        api_ok_list(array(), $limit);
    }

    // What has run out is settled before it is read.
    ws_approvals_autoclose($channel['id']);

    $where = array("a.channel_id = '" . (int) $channel['id'] . "'", "m.deleted_at = 0");

    if ($state === 'open') {
        $where[] = "a.closed_at = 0";
    } elseif ($state === 'closed') {
        $where[] = "a.closed_at > 0";
    }

    $out = array();

    foreach ((array) db_items("SELECT a.* FROM ws_approvals a
        INNER JOIN ws_messages m ON m.id = a.message_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.id DESC LIMIT " . max(1, $limit)) as $row) {
        $out[] = ws_api_approval_present($viewer, $row);
    }

    api_ok_list($out, $limit);
}

function ws_api_approvals_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $data = array(
        'title'     => (string) ($params['title'] ?? ''),
        'text'      => (string) ($params['text'] ?? ''),
        'approvers' => (array) ($params['approvers'] ?? array()),
        'guests'    => (array) ($params['guests'] ?? array()),
        'rule'      => (string) ($params['rule'] ?? 'any'),
        'closes_at' => (int) ($params['closes_at'] ?? 0),
    );

    // The checks ws_approval_create() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_approval_input($viewer, $channel, $data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], (($checked['field'] ?? '') !== '') ? $checked['field'] : null);
    }

    api_dry_run_stop('created', 'workspace_approval', array('title' => $checked['title'], 'rule' => $checked['rule'], 'approvers' => $checked['approvers'], 'guests' => $checked['guests']));

    $result = ws_approval_create($viewer, $channel, $data, ws_api_writer_app_id());

    if (!$result['ok']) {
        api_fail_validation($result['error'], (($result['field'] ?? '') !== '') ? $result['field'] : null);
    }

    ws_api_log(lang(array('string' => 'asked for approval in #{var:1}', 'vars' => $channel['name'])));

    api_ok(ws_api_approval_present($viewer, ws_approval($result['approval_id'])), 201);
}

function ws_api_approvals_decide($params)
{
    $viewer = ws_api_viewer();
    $approval = ws_api_approval_or_404($viewer, $params['id']);

    ws_api_person_only();

    $result = ws_approval_decide($viewer, $approval, (string) $params['decision'], (string) ($params['note'] ?? ''));

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'decision');
    }

    api_ok(ws_api_approval_present($viewer, ws_approval($approval['id'])));
}

/* ---------------------------------------------------------------------------
   Tasks
   --------------------------------------------------------------------------- */

/**
 * One task as the API shows it.
 *
 * @param array      $viewer
 * @param array      $task
 * @param string[]   $warnings
 * @param array|null $known what a list read for all its tasks at once: links
 *                          (ws_task_links_map()), time (ws_task_time_totals()),
 *                          waiting (ws_task_delegation_waiting_map()) and
 *                          working (ws_task_delegation_open_map()); null reads
 *                          them for this task
 * @return array
 */
function ws_api_task_present($viewer, $task, $warnings = array(), $known = null)
{
    $id = (int) $task['id'];
    $assignees = ws_task_assignee_ids($task['id']);
    $minutes = isset($known['time']) ? $known['time'] : ws_task_time_totals(array($task['id']));
    $waiting = isset($known['waiting']) ? $known['waiting'] : ws_task_delegation_waiting_map(array($id));
    $working = isset($known['working']) ? $known['working'] : ws_task_delegation_open_map(array($id));
    $delegate = (string) ($task['delegate'] ?? '');
    $refs = array();

    foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'task' AND source_id = '" . (int) $task['id'] . "'") as $ref) {
        if (!in_array($ref['ref_type'], array('user', 'dept'), true)) {
            $refs[] = array('type' => (string) $ref['ref_type'], 'id' => (int) $ref['ref_id']);
        }
    }

    return array(
        'id'                => (int) $task['id'],
        'number'            => ws_task_number($task['id']),
        'title'             => (string) $task['title'],
        'description'       => (string) $task['description'],
        'status'            => (string) $task['status'],
        'priority'          => (string) $task['priority'],
        'start_date'        => $task['start_date'] ?: null,
        'due_date'          => $task['due_date'] ?: null,
        'estimate_minutes'  => (int) $task['estimate_minutes'],
        'department_id'     => ((int) $task['department_id'] > 0) ? (int) $task['department_id'] : null,
        'channel_id'        => ((int) $task['channel_id'] > 0) ? (int) $task['channel_id'] : null,
        'creator_id'        => (int) $task['creator_id'],
        'assignees'         => $assignees,
        'refs'              => $refs,
        'completed_at'      => ((int) $task['completed_at'] > 0) ? api_time($task['completed_at']) : null,
        'recurrence_id'     => ((int) ($task['recurrence_id'] ?? 0) > 0) ? (int) $task['recurrence_id'] : null,
        'checklist_done'    => (int) ($task['items_done'] ?? 0),
        'checklist_total'   => (int) ($task['items_total'] ?? 0),
        'notes_count'       => (int) ($task['notes_count'] ?? 0),
        // The tasks it cannot start before, and the ones that wait for it
        // (task_links.php); the minutes spent on it (task_time.php).
        'blocked_by'        => isset($known['links'][$id]) ? $known['links'][$id]['blocked_by'] : ws_task_link_ids($task['id']),
        'blocks'            => isset($known['links'][$id]) ? $known['links'][$id]['blocks'] : ws_task_link_waiting_ids($task['id']),
        'time_minutes'      => (int) ($minutes[$id] ?? 0),
        // The assistant the task was handed to, the one on a run on it now,
        // and what they proposed that waits to be applied (delegation.php).
        'delegate'          => ($delegate !== '') ? $delegate : null,
        'delegated_by'      => (($delegate !== '') && ((int) ($task['delegated_by'] ?? 0) > 0)) ? (int) $task['delegated_by'] : null,
        'delegated_at'      => ($delegate !== '') ? api_time((int) ($task['delegated_at'] ?? 0)) : null,
        'assistant_working' => isset($working[$id]) ? (string) $working[$id] : null,
        'waiting_changes'   => (int) ($waiting[$id] ?? 0),
        'created_at'        => api_time($task['created_at']),
        'updated_at'        => api_time($task['updated_at']),
        'warnings'          => array_values($warnings),
    );
}

// What ws_api_task_present() returns.
function ws_api_task_schema()
{
    return array(
        'id'                => 'integer',
        'number'            => 'string',
        'title'             => 'string',
        'description'       => 'string',
        'status'            => 'string',
        'priority'          => 'string',
        'start_date'        => 'string?',
        'due_date'          => 'string?',
        'estimate_minutes'  => 'integer',
        'department_id'     => 'integer?',
        'channel_id'        => 'integer?',
        'creator_id'        => 'integer',
        'assignees'         => array('integer'),
        'refs'              => array(array('type' => 'string', 'id' => 'integer')),
        'completed_at'      => 'string?',
        'recurrence_id'     => 'integer?',
        'checklist_done'    => 'integer',
        'checklist_total'   => 'integer',
        'notes_count'       => 'integer',
        'blocked_by'        => array('integer'),
        'blocks'            => array('integer'),
        'time_minutes'      => 'integer',
        'delegate'          => 'string?',
        'delegated_by'      => 'integer?',
        'delegated_at'      => 'string?',
        'assistant_working' => 'string?',
        'waiting_changes'   => 'integer',
        'created_at'        => 'string',
        'updated_at'        => 'string',
        'warnings'          => array('string'),
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

/**
 * What ws_api_task_present() reads for a whole list of tasks at once.
 *
 * @param int[] $ids
 * @return array
 */
function ws_api_tasks_known($ids)
{
    return array(
        'links'   => ws_task_links_map($ids),
        'time'    => ws_task_time_totals($ids),
        'waiting' => ws_task_delegation_waiting_map($ids),
        'working' => ws_task_delegation_open_map($ids),
    );
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

    if (!empty($params['delegate'])) {
        $delegate = (string) $params['delegate'];

        if (!in_array($delegate, array('any', 'none', 'ai', 'claude'), true) && (ws_assistant_mcp_id($delegate) <= 0)) {
            api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('delegate', 'any, none, ai, claude, mcp:<id>'))), 'delegate');
        }

        $filters['delegate'] = $delegate;
    }

    $briefs = ws_tasks_list($viewer, $filters);
    $ids = array();

    foreach ($briefs as $brief) {
        $ids[] = (int) $brief['id'];
    }

    // The links, the minutes and the assistants of the whole page, one read
    // each.
    $known = ws_api_tasks_known($ids);
    $out = array();

    foreach ($briefs as $brief) {
        $out[] = ws_api_task_present($viewer, ws_task($brief['id']), array(), $known);
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
        $sent = ws_message_send($viewer, ws_channel($data['channel_id']), '', array('kind' => 'task', 'task_id' => $result['task_id'], 'app_id' => ws_api_writer_app_id()));

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
    $result = ws_task_note_add($viewer, $task, (string) $params['text'], ws_api_writer_app_id());

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
 * @param array  $entry ws_changes_map() for this change
 * @return array[]
 */
function ws_api_change_fields_present($row, $action, $entry = array())
{
    $types = ws_change_types();
    $type = (string) $row['record_type'];
    $fields = json_decode((string) $row['fields'], true);
    $fields = is_array($fields) ? $fields : array();
    $out = array();

    // A call of an MCP server's tool: its arguments, as the card shows them.
    if ($type === 'mcp_call') {
        foreach ($fields as $item) {
            if (is_array($item) && function_exists('ws_mcp_argument_show')) {
                $name = (string) ($item['name'] ?? '');
                $out[] = array('name' => $name, 'label' => $name, 'from_shown' => null, 'to_shown' => ws_mcp_argument_show($item['to'] ?? null));
            }
        }

        return $out;
    }

    // A scheduled action Pinegrap AI drafted (scheduled_draft.php): its steps
    // in words, as the card shows them to the person who asked.
    if ($type === 'scheduled') {
        foreach ((array) ($entry['fields'] ?? array()) as $item) {
            $out[] = array('name' => (string) $item['name'], 'label' => (string) $item['label'], 'from_shown' => null, 'to_shown' => (string) $item['to']);
        }

        return $out;
    }

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

    // A call of an MCP server's tool (mcp_ask.php): the call is the record,
    // named by its tool; it has no tag. So is a drafted scheduled action
    // (scheduled_draft.php), named by its name, with the id of the action
    // saved from it.
    if (($type === 'mcp_call') || ($type === 'scheduled')) {
        return array(
            'type'       => $type,
            'type_label' => (string) $entry['type_label'],
            'id'         => ($id > 0) ? $id : null,
            'tag'        => null,
            'name'       => trim((string) $entry['action_label'] . ' ' . (string) $entry['record_label']),
        );
    }

    // A record deleted by the change is gone: no tag points at it any more.
    // A note has no tag at all.
    $tagged = ($id > 0) && ((string) $types[$type]['tag'] !== '') && !(($entry['action'] === 'delete') && ($row['status'] === 'applied'));

    return array(
        'type'       => $type,
        'type_label' => (string) $entry['type_label'],
        'id'         => ($id > 0) ? $id : null,
        'tag'        => $tagged ? '<#' . $types[$type]['tag'] . ':' . $id . '>' : null,
        'name'       => (string) $entry['record_label'],
    );
}

/**
 * The task a proposed change waits on, or 0 for one under an answer in a
 * channel (delegation.php).
 *
 * @param array $row ws_ai_changes
 * @return int
 */
function ws_api_change_task_id($row)
{
    return ((int) $row['channel_id'] === 0) ? (int) ($row['task_id'] ?? 0) : 0;
}

/**
 * One proposed record change as the API shows it. What the reader may see of
 * it - its values and its reason - is what the card in the channel, or on the
 * task, shows them (ws_changes_map()).
 *
 * @param array $row    ws_ai_changes, with agent when the requests carry it
 * @param array $entry  ws_changes_map() for this change
 * @param array $people ws_people()
 * @return array
 */
function ws_api_change_present($row, $entry, $people)
{
    $task_id = ws_api_change_task_id($row);

    return array(
        'id'                  => (int) $row['id'],
        'status'              => (string) $row['status'],
        'agent'               => in_array((string) ($row['agent'] ?? ''), array('ai', 'mcp'), true) ? (string) $row['agent'] : 'claude',
        'request_id'          => ((int) $row['request_id'] > 0) ? (int) $row['request_id'] : null,
        'channel_id'          => (int) $row['channel_id'],
        'message_id'          => (int) $row['message_id'],
        'task_id'             => ($task_id > 0) ? $task_id : null,
        'action'              => (string) $entry['action'],
        'record'              => ws_api_change_record_present($row, $entry),
        'hidden'              => !empty($entry['hidden']),
        'fields'              => !empty($entry['hidden']) ? array() : ws_api_change_fields_present($row, (string) $entry['action'], $entry),
        'reason'              => (string) $entry['reason'],
        'error'               => (string) $row['error'],
        'requested_by'        => ws_api_person_ref($row['requested_by'], $people),
        'decided_by'          => ws_api_person_ref($row['decided_by'], $people),
        'decided_at'          => api_time($row['decided_at']),
        'decision_message_id' => ((int) $row['decision_message_id'] > 0) ? (int) $row['decision_message_id'] : null,
        'can_apply'           => !empty($entry['can_apply']),
        'can_dismiss'         => !empty($entry['can_dismiss']),
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
        'task_id'             => 'integer?',
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
        'can_apply'           => 'boolean',
        'can_dismiss'         => 'boolean',
        'created_at'          => 'string?',
    );
}

/**
 * The proposed changes of these rows as the API shows them, each drawn for
 * this reader from the card it has: under an answer in a channel, or under a
 * run on a task. A kind of record the site no longer runs has no card and is
 * left out.
 *
 * @param array   $viewer
 * @param array[] $rows ws_ai_changes, with agent when the requests carry it
 * @return array[]
 */
function ws_api_changes_present($viewer, $rows)
{
    $message_ids = array();
    $request_ids = array();
    $people_ids = array();

    foreach ($rows as $row) {
        if (ws_api_change_task_id($row) > 0) {
            $request_ids[] = (int) $row['request_id'];
        } else {
            $message_ids[] = (int) $row['message_id'];
        }

        $people_ids[] = (int) $row['requested_by'];
        $people_ids[] = (int) $row['decided_by'];
    }

    $entries = array();
    $maps = array(
        ws_changes_map($viewer, array_unique($message_ids)),
        ws_changes_map($viewer, array_unique($request_ids), 'request_id'),
    );

    foreach ($maps as $map) {
        foreach ($map as $list) {
            foreach ($list as $entry) {
                $entries[(int) $entry['id']] = $entry;
            }
        }
    }

    $people = ws_people(array_unique(array_filter($people_ids)));
    $out = array();

    foreach ($rows as $row) {
        if (isset($entries[(int) $row['id']])) {
            $out[] = ws_api_change_present($row, $entries[(int) $row['id']], $people);
        }
    }

    return $out;
}

/**
 * The tasks the owner may see, as SQL over ws_tasks: the rule of
 * ws_can_see_task() for a whole table at once. A task opened in a discussion
 * is a task of the discussion's channel, so the channels the owner reads are
 * all it takes.
 *
 * @param array  $viewer
 * @param string $alias
 * @return string
 */
function ws_api_task_seen_sql($viewer, $alias)
{
    $me = (int) $viewer['id'];
    $or = array(
        $alias . ".creator_id = '" . $me . "'",
        "EXISTS (SELECT 1 FROM ws_task_assignees sa WHERE sa.task_id = " . $alias . ".id AND sa.user_id = '" . $me . "')",
    );

    $channel_ids = array_map('intval', array_keys(ws_timeline_channels($viewer)));

    if (!empty($channel_ids)) {
        $or[] = $alias . ".channel_id IN (" . implode(',', $channel_ids) . ")";
    }

    if ($viewer['board']) {
        $or[] = $alias . ".channel_id = 0";
    } else {
        $lead = array_map('intval', (array) ws_lead_departments($me));

        if (!empty($lead)) {
            $or[] = "(" . $alias . ".channel_id = 0 AND " . $alias . ".department_id IN (" . implode(',', $lead) . "))";
        }
    }

    return "(" . implode(' OR ', $or) . ")";
}

/**
 * Which proposed changes the owner may read, as SQL over ws_ai_changes c:
 * those under answers in the channels the owner reads, and those waiting on
 * the tasks they see.
 *
 * @param array $viewer
 * @return string
 */
function ws_api_changes_reach_sql($viewer)
{
    $reach = array();
    $channel_ids = ws_api_readable_channel_ids($viewer);

    if (!empty($channel_ids)) {
        $reach[] = "c.channel_id IN (" . implode(',', $channel_ids) . ")";
    }

    if (ws_delegation_ready()) {
        $reach[] = "(c.channel_id = 0 AND c.task_id IN (SELECT t.id FROM ws_tasks t WHERE " . ws_api_task_seen_sql($viewer, 't') . "))";
    }

    return empty($reach) ? '0 = 1' : "(" . implode(' OR ', $reach) . ")";
}

/**
 * Proposed changes, newest first, with the assistant that proposed each when
 * the requests say (ai.php).
 *
 * @param string[] $where SQL over ws_ai_changes c
 * @param int      $limit
 * @return array[]
 */
function ws_api_changes_rows($where, $limit)
{
    $agent = function_exists('ws_ai_schema_ready') && ws_ai_schema_ready();

    return (array) db_items("SELECT c.*" . ($agent ? ", r.agent" : '') . "
        FROM ws_ai_changes c
        " . ($agent ? "LEFT JOIN ws_ai_requests r ON r.id = c.request_id" : '') . "
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.id DESC
        LIMIT " . max(1, (int) $limit));
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

    // mcp_call: the calls of MCP server tools that wait the same way.
    if (($type !== '') && !isset($types[$type]) && ($type !== 'mcp_call')) {
        api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('type', implode(', ', array_merge(array_keys($types), array('mcp_call')))))), 'type');
    }

    $where = array(ws_api_changes_reach_sql($viewer));

    if (!empty($params['channel_id'])) {
        $where[] = "c.channel_id = '" . (int) ws_api_channel_or_404($viewer, $params['channel_id'])['id'] . "'";
    }

    if (!empty($params['task_id'])) {
        $task = ws_api_task_or_404($viewer, $params['task_id']);

        if (!ws_delegation_ready()) {
            api_ok_list(array(), $limit);
        }

        $where[] = "c.channel_id = 0 AND c.task_id = '" . (int) $task['id'] . "'";
    }

    if (!empty($params['status'])) {
        $where[] = "c.status = '" . e($params['status']) . "'";
    }

    if ($type !== '') {
        $where[] = "c.record_type = '" . e($type) . "'";
    }

    if ($cursor !== null) {
        $where[] = "c.id < '" . (int) $cursor['i'] . "'";
    }

    $rows = ws_api_changes_rows($where, $limit + 1);
    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = end($rows);
        $next = api_cursor_encode($last['id'], $last['id']);
    }

    api_ok_list(ws_api_changes_present($viewer, $rows), $limit, $next);
}

/**
 * A proposed change the owner may read, or a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array the row
 */
function ws_api_change_or_404($viewer, $id)
{
    $rows = (function_exists('ws_changes_ready') && ws_changes_ready())
        ? ws_api_changes_rows(array("c.id = '" . (int) $id . "'", ws_api_changes_reach_sql($viewer)), 1)
        : array();

    if (empty($rows)) {
        api_fail_not_found(lang('Proposal'));
    }

    return $rows[0];
}

/**
 * Answers with a proposed change as it stands now, after it was applied or
 * set aside.
 *
 * @param array $viewer
 * @param int   $id
 */
function ws_api_change_answer($viewer, $id)
{
    $out = ws_api_changes_present($viewer, ws_api_changes_rows(array("c.id = '" . (int) $id . "'"), 1));

    if (empty($out)) {
        api_fail_not_found(lang('Proposal'));
    }

    api_ok($out[0]);
}

/**
 * An apply or a set-aside that was refused, as the API answers it.
 *
 * @param array $result ws_change_apply() or ws_change_dismiss()
 */
function ws_api_change_refused($result)
{
    $error = (string) $result['error'];

    if (!empty($result['stale'])) {
        api_fail(409, 'stale', $error);
    }

    if ($error === lang('Somebody has already decided about this proposal.')) {
        api_fail(409, 'already_decided', $error);
    }

    $forbidden = array(
        lang('Only the person who asked Pinegrap AI can apply this change.'),
        lang('Only the person who asked Claude can apply this change.'),
        lang('Only the person who asked Pinegrap AI can set this change aside.'),
        lang('Only the person who asked Claude can set this change aside.'),
        lang('You may not change a record of this kind.'),
        lang('You cannot post in that channel.'),
        lang('That task could not be found.'),
    );

    if (in_array($error, $forbidden, true)) {
        api_fail(403, 'forbidden', $error);
    }

    api_fail_validation($error);
}

function ws_api_changes_apply($params)
{
    $viewer = ws_api_viewer();
    $change = ws_api_change_or_404($viewer, $params['id']);

    // Applying is the person's own decision, as on the card.
    ws_api_person_only();

    $result = ws_change_apply($viewer, (int) $change['id']);

    if (!$result['ok']) {
        ws_api_change_refused($result);
    }

    ws_api_change_answer($viewer, $change['id']);
}

function ws_api_changes_dismiss($params)
{
    $viewer = ws_api_viewer();
    $change = ws_api_change_or_404($viewer, $params['id']);

    ws_api_person_only();

    $result = ws_change_dismiss($viewer, (int) $change['id']);

    if (!$result['ok']) {
        ws_api_change_refused($result);
    }

    ws_api_change_answer($viewer, $change['id']);
}

/**
 * Where a request was asked: in a channel, on a task, in a note, or in the
 * Visual Page Editor.
 *
 * @param array $row ws_ai_requests
 * @return string
 */
function ws_api_assistant_request_origin($row)
{
    if ((int) $row['channel_id'] > 0) {
        return 'channel';
    }

    if ((int) ($row['task_id'] ?? 0) > 0) {
        return 'task';
    }

    if ((int) ($row['note_id'] ?? 0) > 0) {
        return 'note';
    }

    return ((int) ($row['page_id'] ?? 0) > 0) ? 'editor' : '';
}

function ws_api_assistant_request_present($viewer, $row, $people)
{
    $note_id = (int) ($row['note_id'] ?? 0);
    $page_id = (int) ($row['page_id'] ?? 0);

    // A run on a task (delegation.php) is read with the task, by whoever
    // sees it: what it was told and what it answered. Elsewhere the words are
    // read where they were asked.
    $task_id = ((int) $row['channel_id'] === 0) ? (int) ($row['task_id'] ?? 0) : 0;

    return array(
        'id'               => (int) $row['id'],
        'agent'            => in_array((string) ($row['agent'] ?? ''), array('ai', 'mcp'), true) ? (string) $row['agent'] : 'claude',
        'status'           => (string) $row['status'],
        'origin'           => ws_api_assistant_request_origin($row),
        'channel_id'       => ((int) $row['channel_id'] > 0) ? (int) $row['channel_id'] : null,
        'message_id'       => ((int) $row['message_id'] > 0) ? (int) $row['message_id'] : null,
        'task_id'          => ($task_id > 0) ? $task_id : null,
        'instruction'      => ($task_id > 0) ? ws_plain_text($viewer, (string) ($row['note_text'] ?? '')) : null,
        'answer'           => (($task_id > 0) && ($row['status'] === 'answered')) ? ws_plain_text($viewer, (string) ($row['note_reply'] ?? '')) : null,
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
        'task_id'          => 'integer?',
        'instruction'      => 'string?',
        'answer'           => 'string?',
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

/**
 * Requests to the assistants, newest first, with the columns later steps
 * added where they exist; the conversation and page trees kept on the row
 * are never read here.
 *
 * @param string $where SQL over ws_ai_requests
 * @param int    $limit
 * @return array[]
 */
function ws_api_assistant_requests_rows($where, $limit)
{
    $columns = array('id', 'channel_id', 'message_id', 'requested_by', 'status', 'reply_message_id', 'error', 'created_at', 'sent_at', 'claimed_at', 'answered_at');

    foreach (array('agent', 'note_id', 'page_id', 'node_id', 'task_id', 'note_text', 'note_reply') as $column) {
        if (function_exists('waf_table_has_column') && waf_table_has_column('ws_ai_requests', $column)) {
            $columns[] = $column;
        }
    }

    return (array) db_items("SELECT " . implode(', ', $columns) . " FROM ws_ai_requests
        WHERE " . $where . "
        ORDER BY id DESC
        LIMIT " . max(1, (int) $limit));
}

function ws_api_assistant_requests_list($params)
{
    $viewer = ws_api_viewer();
    $limit = (int) ($params['limit'] ?? 50);
    $cursor = ws_api_cursor($params);

    if (!function_exists('ws_claude_schema_ready') || !ws_claude_schema_ready()) {
        api_ok_list(array(), $limit);
    }

    // Asked in a channel the owner reads; on a task the owner sees; or by
    // the owner where neither is: in a note or in the page editor.
    $tasks = ws_delegation_ready();
    $reach = array("(channel_id = 0 AND requested_by = '" . (int) $viewer['id'] . "'" . ($tasks ? " AND task_id = 0" : '') . ")");
    $channel_ids = ws_api_readable_channel_ids($viewer);

    if (!empty($channel_ids)) {
        $reach[] = "channel_id IN (" . implode(',', $channel_ids) . ")";
    }

    if ($tasks) {
        $reach[] = "(channel_id = 0 AND task_id IN (SELECT t.id FROM ws_tasks t WHERE " . ws_api_task_seen_sql($viewer, 't') . "))";
    }

    $where = "(" . implode(' OR ', $reach) . ")";

    if (!empty($params['task_id'])) {
        $task = ws_api_task_or_404($viewer, $params['task_id']);

        if (!$tasks) {
            api_ok_list(array(), $limit);
        }

        $where .= " AND channel_id = 0 AND task_id = '" . (int) $task['id'] . "'";
    }

    if (!empty($params['status'])) {
        $where .= " AND status = '" . e($params['status']) . "'";
    }

    if (!empty($params['agent']) && function_exists('ws_ai_agent_where')) {
        $where .= ws_ai_agent_where((string) $params['agent']);
    }

    if ($cursor !== null) {
        $where .= " AND id < '" . (int) $cursor['i'] . "'";
    }

    $rows = ws_api_assistant_requests_rows($where, $limit + 1);
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
        $out[] = ws_api_assistant_request_present($viewer, $row, $people);
    }

    api_ok_list($out, $limit, $next);
}

/* ---------------------------------------------------------------------------
   A task handed to an assistant (delegation.php)
   --------------------------------------------------------------------------- */

/**
 * Is the current application one an assistant works through? An assistant
 * does not hand tasks to assistants, nor take them back.
 *
 * @return bool
 */
function ws_api_is_assistant()
{
    $app_id = ws_api_app_id();

    if ($app_id <= 0) {
        return false;
    }

    foreach (ws_assistants() as $assistant) {
        if ((int) $assistant['app_id'] === $app_id) {
            return true;
        }
    }

    return ws_api_is_claude();
}

function ws_api_tasks_delegate($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not hand tasks to assistants or take them back.'));
    }

    if (!ws_delegation_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $key = (string) $params['agent'];

    if (!ws_assistant($key)) {
        api_fail_validation(lang(array('string' => '{var:1} is not switched on on this site.', 'vars' => ws_assistant_name($key))), 'agent');
    }

    $result = ws_task_delegate($viewer, $task, $key, (string) ($params['instruction'] ?? ''));

    if (!$result['ok']) {
        if ($result['error'] === lang('You may not hand this task to an assistant.')) {
            api_fail(403, 'forbidden', $result['error']);
        }

        if (ws_task_delegation_open_run($task['id'])) {
            api_fail(409, 'assistant_busy', $result['error']);
        }

        api_fail_validation($result['error']);
    }

    ws_task_delegation_start($key);

    $rows = ws_api_assistant_requests_rows("id = '" . (int) $result['request_id'] . "'", 1);

    api_ok(ws_api_assistant_request_present($viewer, $rows[0], ws_people(array((int) $viewer['id']))), 201);
}

function ws_api_tasks_delegate_stop($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not hand tasks to assistants or take them back.'));
    }

    if (!ws_delegation_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $result = ws_task_delegation_stop($viewer, $task, !empty($params['take_back']));

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_task_present($viewer, ws_task($task['id'])));
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

    if (!$note || !ws_api_note_reachable($viewer, $note)) {
        api_fail_not_found(lang('Note'));
    }

    return $note;
}

/**
 * Whether the current application reaches a note the owner may read: always,
 * except for the application Claude works through and a note that reached
 * the owner only through a channel Claude may not be asked in.
 *
 * @param array $viewer
 * @param array $note with access (ws_note_for())
 * @return bool
 */
function ws_api_note_reachable($viewer, $note)
{
    if (!ws_api_is_claude() || ($note['access'] !== 'view')) {
        return true;
    }

    foreach ((array) db_items("SELECT user_id, channel_id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'") as $share) {
        if ((int) $share['user_id'] === (int) $viewer['id']) {
            return true;
        }

        if ((int) $share['channel_id'] > 0) {
            $channel = ws_channel($share['channel_id']);

            if ($channel && ws_can_read_channel($viewer, $channel) && ws_claude_channel_allowed($channel)) {
                return true;
            }
        }
    }

    return false;
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

/**
 * The fields of a note write, in the shape ws_note_save() reads; only those
 * sent.
 *
 * @param array $params
 * @return array
 */
function ws_api_note_input($params)
{
    $data = array();

    foreach (array('title', 'body', 'pinned') as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = $params[$field];
        }
    }

    return $data;
}

function ws_api_notes_create($params)
{
    $viewer = ws_api_viewer();

    if (!ws_notes_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $data = ws_api_note_input($params);

    if ((trim((string) ($data['title'] ?? '')) === '') && (trim((string) ($data['body'] ?? '')) === '')) {
        api_fail_validation(lang('The note is empty.'), 'body');
    }

    $result = ws_note_save($viewer, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    api_ok(ws_api_note_present($viewer, ws_note_present($viewer, ws_note_for($viewer, $result['note_id'], 'view'))), 201);
}

function ws_api_notes_update($params)
{
    $viewer = ws_api_viewer();

    if (!ws_notes_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $note = ws_api_note_or_404($viewer, $params['id']);

    if (!in_array($note['access'], array('owner', 'edit'), true)) {
        api_fail(403, 'forbidden', lang('This note was shared with you to read only.'));
    }

    $data = ws_api_note_input($params);

    if (array_key_exists('pinned', $data) && ($note['access'] !== 'owner')) {
        api_fail_validation(lang('Only the one who wrote a note can pin it.'), 'pinned');
    }

    $data['note_id'] = (int) $note['id'];

    // A change made by somebody else after the note was read is not written
    // over; ws_note_save() says who made it.
    if (isset($params['base'])) {
        $data['base'] = (int) $params['base'];
    }

    $result = ws_note_save($viewer, $data);

    if (!$result['ok']) {
        if ($result['conflict']) {
            api_fail(409, 'changed_meanwhile', $result['error'], 'base');
        }

        api_fail_validation($result['error'], 'body');
    }

    api_ok(ws_api_note_present($viewer, ws_note_present($viewer, ws_note_for($viewer, $note['id'], 'view'))));
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
