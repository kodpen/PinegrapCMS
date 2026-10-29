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

/**
 * Runs a module function that writes its own line in the activity log in the
 * name of the signed-in person (ws_channel_create(), ws_group_save() and the
 * others) with the owner of the application as that person: the API keeps no
 * session, and the panel's line would otherwise carry no name.
 *
 * @param callable $call
 * @return mixed what $call returns
 */
function ws_api_as_owner($call)
{
    $app = api_current_app();

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = array();
    }

    $had = array_key_exists('sessionusername', $_SESSION);
    $old = $had ? $_SESSION['sessionusername'] : null;
    $_SESSION['sessionusername'] = (string) ($app['owner']['username'] ?? '');

    try {
        return call_user_func($call);
    } finally {
        if ($had) {
            $_SESSION['sessionusername'] = $old;
        } else {
            unset($_SESSION['sessionusername']);
        }
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
        'pinned'        => !empty($brief['pinned']),
        'order'         => ((int) ($brief['sort'] ?? 0) > 0) ? (int) $brief['sort'] : null,
        'contact_id'    => ((int) $brief['contact_id'] > 0) ? (int) $brief['contact_id'] : null,
        'customer_type' => (($brief['customer_type'] ?? '') !== '') ? (string) $brief['customer_type'] : null,
        'customer_id'   => ((int) ($brief['customer_id'] ?? 0) > 0) ? (int) $brief['customer_id'] : null,
        'department_id' => ((int) $brief['department_id'] > 0) ? (int) $brief['department_id'] : null,
        'group_id'      => ((int) ($brief['group_id'] ?? 0) > 0) ? (int) $brief['group_id'] : null,
        'color'         => ((string) ($brief['hex'] ?? '') !== '') ? (string) $brief['hex'] : null,
        'icon'          => (string) ($brief['icon'] ?? ws_channel_kind_icon($brief)),
        'icon_chosen'   => !empty($brief['icon_chosen']),
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
        'pinned'          => 'boolean',
        'order'           => 'integer?',
        'contact_id'      => 'integer?',
        'customer_type'   => 'string?',
        'customer_id'     => 'integer?',
        'department_id'   => 'integer?',
        'group_id'        => 'integer?',
        'color'           => 'string?',
        'icon'            => 'string',
        'icon_chosen'     => 'boolean',
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
        'my_pinned' => $membership['pinned'] ?? 0,
        'my_sort'   => $membership['sort'] ?? 0,
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

/**
 * The customer of a channel write, checked before anything is written so a
 * dry run meets the same refusals: the fields ws_customer_input() reads, or
 * none when the body names no customer. The user role may only name a
 * contact filed in its contact groups, as its contact search offers them
 * (ws_viewer_contacts_open()); one outside is refused like a missing one.
 *
 * @param array      $viewer
 * @param array      $params
 * @param array|null $channel the channel as it is, for an update
 * @return array customer_type, customer_id, or empty
 */
function ws_api_channel_customer_write($viewer, $params, $channel = null)
{
    $missing = ws_api_channel_customer_missing($params);

    if ($missing !== '') {
        api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => $missing)), $missing);
    }

    $data = ws_api_channel_customer_fields($params);

    if (empty($data)) {
        return array();
    }

    $customer = ws_customer_input($viewer, $data, $channel);

    if (is_array($customer) && !$customer['ok']) {
        api_fail_validation($customer['error'], 'customer_id');
    }

    $unchanged = is_array($channel) && (ws_channel_customer_of($channel) === array($customer['type'], $customer['id']));

    if (($customer['type'] === 'contact') && !$unchanged && empty(ws_viewer_contacts_open($viewer, array($customer['id'])))) {
        api_fail_validation(lang('That contact could not be found.'), 'customer_id');
    }

    return $data;
}

/**
 * The half of a customer a write left out while naming the other: a kind
 * with no id, or an id with no kind. Empty when both or neither are sent, or
 * the one sent clears the customer (empty, or id 0).
 *
 * @param array $params
 * @return string the missing field, or ''
 */
function ws_api_channel_customer_missing($params)
{
    $type = array_key_exists('customer_type', $params);
    $id = array_key_exists('customer_id', $params);

    if ($type && !$id && ((string) $params['customer_type'] !== '')) {
        return 'customer_id';
    }

    if ($id && !$type && ((int) $params['customer_id'] > 0)) {
        return 'customer_type';
    }

    return '';
}

/**
 * The customer fields of a channel write as ws_customer_input() reads them:
 * both, once either is sent; an empty value (the router's null) or 0 for
 * none.
 *
 * @param array $params
 * @return array
 */
function ws_api_channel_customer_fields($params)
{
    if (!array_key_exists('customer_type', $params) && !array_key_exists('customer_id', $params)) {
        return array();
    }

    return array(
        'customer_type' => (string) ($params['customer_type'] ?? ''),
        'customer_id'   => (int) ($params['customer_id'] ?? 0),
    );
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

    // The router has held both to their lists; empty is none.
    $color = ws_palette_place_of($params['color'] ?? '');
    $icon = ws_channel_icon_clean($params['icon'] ?? '');
    $customer = ws_api_channel_customer_write($viewer, $params);

    api_dry_run_stop('created', 'workspace_channel', array(
        'name'          => $name,
        'kind'          => $kind,
        'members'       => count($members),
        'color'         => ($color > 0) ? ws_palette_hex($color) : null,
        'icon'          => ($icon !== '') ? $icon : null,
        'customer_type' => (($customer['customer_type'] ?? '') !== '') ? $customer['customer_type'] : null,
        'customer_id'   => ((int) ($customer['customer_id'] ?? 0) > 0) ? (int) $customer['customer_id'] : null,
    ));

    $result = ws_api_as_owner(function () use ($viewer, $params, $kind, $department_id, $members, $color, $icon, $customer) {
        return ws_channel_create($viewer, array_merge(array(
            'name'          => (string) $params['name'],
            'kind'          => $kind,
            'topic'         => (string) ($params['topic'] ?? ''),
            'department_id' => $department_id,
            'members'       => $members,
            'color'         => $color,
            'icon'          => $icon,
        ), $customer));
    });

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

    // A palette code becomes its place; an icon was held to the list by the
    // router. Empty clears either.
    if (array_key_exists('color', $params)) {
        $data['color'] = ws_palette_place_of($params['color']);
    }

    if (array_key_exists('icon', $params)) {
        $data['icon'] = ws_channel_icon_clean($params['icon']);
    }

    $data = array_merge($data, ws_api_channel_customer_write($viewer, $params, $channel));

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

function ws_api_message_present($viewer, $row, $refs, $reactions = null, $acks = null, $threads = null, $hidden = null, $polls = null, $proposals = null, $note_cards = null)
{
    // The messages the reader deleted for themselves (ws_message_hide()).
    if ($hidden === null) {
        $hidden = ws_message_hidden_ids($viewer['id'], array($row['id']));
    }

    // The poll it asks (interact.php).
    if ($polls === null) {
        $polls = ws_polls_map(array($row['id']), $viewer);
    }

    // The note it shares (notes.php).
    if ($note_cards === null) {
        $note_cards = function_exists('ws_note_cards_map') ? ws_note_cards_map($viewer, array($row['id'])) : array();
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
    $mail = null;

    if ($row['sender_kind'] === 'user') {
        $people = ws_people(array($row['sender_id']));
        $sender = isset($people[(int) $row['sender_id']]) ? array('user_id' => (int) $row['sender_id'], 'name' => $people[(int) $row['sender_id']]['name']) : null;
    } elseif ($row['sender_kind'] === 'app') {
        $app = ws_app_sender((int) $row['sender_id']);

        // A proposal an OAuth connection made carries the client's own name
        // (proposals.php), as on the channel screen.
        if (($proposals === null) && function_exists('ws_proposals_by_reply')) {
            $proposals = ws_proposals_by_reply(array($row['id']));
        }

        if (isset($proposals[(int) $row['id']])) {
            $app = ws_proposal_sender($app, $proposals[(int) $row['id']]);
        }

        $sender = array('user_id' => null, 'name' => $app['name']);
    } elseif (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        $sender = array('user_id' => null, 'name' => ws_guest_sender((int) $row['sender_id'])['name']);
    } elseif (($row['sender_kind'] === 'mail') && function_exists('ws_mail_sender')) {
        // A mail: its sender as the mail names them, or the team's writer
        // of an answer (mail.php); mail tells its conversation.
        $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $row['sender_id'] . "'");
        $item = (is_array($item) && $item) ? $item : null;
        $from = ws_mail_sender($item);
        $sender = array('user_id' => ((int) $from['id'] > 0) ? (int) $from['id'] : null, 'name' => (string) $from['name']);
        $mail = $item ? array(
            'item_id'         => (int) $item['id'],
            'conversation_id' => ((int) $item['conversation_id'] > 0) ? (int) $item['conversation_id'] : null,
            'direction'       => (string) $item['direction'],
            'from_email'      => (string) $item['from_email'],
        ) : null;
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
                // A voice message's transcript (voice.php).
                'voice'   => ((ws_file_kind($row['file_name']) === 'audio') && function_exists('ws_voice_api_present')) ? ws_voice_api_present((int) $row['file_id']) : null,
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
        'note_card'      => ws_api_message_note_card($row, $note_cards),
        'mail'           => $deleted ? null : $mail,
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
        'sender'      => 'WorkspaceMessageSender?',
        'text'        => 'string',
        'body'        => 'string',
        'task_id'     => 'integer?',
        'file'        => 'WorkspaceMessageFile?',
        'deleted'     => 'boolean',
        'hidden'      => 'boolean',
        'edited'      => 'boolean',
        'reactions'   => array(array('emoji' => 'string', 'count' => 'integer')),
        'ack'         => array('wanted' => 'boolean', 'count' => 'integer', 'total' => 'integer', 'mine' => 'boolean'),
        'thread'         => 'WorkspaceMessageThread?',
        'from_thread_id' => 'integer?',
        'poll'           => 'WorkspacePoll?',
        'note_card'      => 'WorkspaceMessageNoteCard?',
        'mail'           => 'WorkspaceMessageMail?',
        'created_at'  => 'string',
    );
}

// A message of sender_kind mail: the mail it carries (GET
// /workspace/mail/conversations/{conversation_id} reads it whole).
function ws_api_message_mail_schema()
{
    return array('item_id' => 'integer', 'conversation_id' => 'integer?', 'direction' => 'string', 'from_email' => 'string');
}

// The parts of a message that are null when it has none: written by the
// workspace itself, carrying no file, with no discussion started from it.
// An object written inline cannot say it may be null, so each is declared
// on its own.
function ws_api_message_sender_schema()
{
    return array('user_id' => 'integer?', 'name' => 'string');
}

function ws_api_message_file_schema()
{
    return array('name' => 'string', 'kind' => 'string', 'size' => 'integer', 'url' => 'string', 'api_url' => 'string', 'voice' => 'WorkspaceMessageVoice?');
}

// The transcript of a voice message inside its file (ws_voice_api_present(),
// voice.php): status always, the rest once it is done.
function ws_api_message_voice_schema()
{
    return array(
        'status'         => 'string',
        'transcript'     => 'string?',
        'language'       => 'string?',
        'provider'       => 'string?',
        'model'          => 'string?',
        'transcribed_at' => 'string?',
    );
}

function ws_api_message_thread_schema()
{
    return array('id' => 'integer', 'title' => 'string', 'message_count' => 'integer', 'last_message_at' => 'string?', 'closed' => 'boolean');
}

function ws_api_message_note_card_schema()
{
    return array('id' => 'integer', 'name' => 'string', 'excerpt' => 'string');
}

/**
 * The note a message shares, from the page's ws_note_cards_map(). Null when
 * it shares none or the share was taken back (the share row is gone), and
 * on a deleted message, as on the channel screen.
 *
 * @param array $row        ws_messages row
 * @param array $note_cards ws_note_cards_map() by message id
 * @return array|null
 */
function ws_api_message_note_card($row, $note_cards)
{
    $card = $note_cards[(int) $row['id']] ?? null;

    if (((int) $row['deleted_at'] > 0) || !is_array($card)) {
        return null;
    }

    return array('id' => (int) $card['id'], 'name' => (string) $card['name'], 'excerpt' => (string) $card['excerpt']);
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
        'closed_at'         => api_time($poll['closed_at'] ?? 0),
        'voters'            => (int) $poll['voters'],
        'voted'             => !empty($poll['voted']),
        'can_vote'          => !empty($poll['can_vote']),
        'can_edit'          => !empty($poll['can_edit']),
        'can_close'         => !empty($poll['can_close']),
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
        'closed_at'         => 'string?',
        'voters'            => 'integer',
        'voted'             => 'boolean',
        'can_vote'          => 'boolean',
        'can_edit'          => 'boolean',
        'can_close'         => 'boolean',
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
        // edit, a deletion, a reaction, a tick, a vote, a read receipt, and
        // for the person alone one they deleted for themselves on another
        // device - the way the panel keeps an open channel up to date
        // (ws_sync). since_id pages through them; the current conversation
        // only.
        if (!ws_interact_ready()) {
            api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
        }

        $after = max((int) ($params['since_id'] ?? 0), ws_channel_floor($channel['id']));
        $rows = ws_messages_touched($channel['id'], max(1, (int) $params['changed_since']), PHP_INT_MAX, $after, $limit, (int) $viewer['id']);
    } elseif (!empty($params['since_id'])) {
        $rows = array_slice(ws_messages_since($channel['id'], (int) $params['since_id']), 0, $limit);
    } else {
        $rows = ws_messages_page($channel['id'], (int) ($params['before_id'] ?? 0), $limit);
    }

    api_ok_list(ws_api_messages_present($viewer, $rows), $limit);
}

/**
 * Messages as the API shows them, with their tags, reactions, read receipts,
 * discussions and shared notes read once for the whole set.
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
    $proposals = function_exists('ws_proposals_by_reply') ? ws_proposals_by_reply($ids) : array();
    $note_cards = function_exists('ws_note_cards_map') ? ws_note_cards_map($viewer, $ids) : array();
    $out = array();

    // A message the person deleted for themselves stays in the page, marked
    // hidden, so that since_id and before_id still page past it.
    foreach ($rows as $row) {
        $out[] = ws_api_message_present($viewer, $row, $refs, $reactions, $acks, $threads, $hidden, $polls, $proposals, $note_cards);
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

    // The checks ws_message_send() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_message_send_check($viewer, $channel, (string) $params['text'], array('app_id' => $app_id));

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    api_dry_run_stop('created', 'workspace_message', array(
        'channel_id' => (int) $channel['id'],
        'kind'       => $kind,
        'ack'        => $ack,
        'joins'      => ($app_id === 0) && ($channel['kind'] === 'public') && !ws_channel_membership($channel['id'], $viewer['id']),
    ));

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
 * Forwards messages into a channel, as the panel's Forward does: a new
 * message there carrying a copy of each. It is an ordinary write, not a
 * change to the person's words: the copies are frozen as they were, and the
 * new message carries the writer's name like any other message, so an
 * application writes it as itself (ws_api_writer_app_id()).
 */
function ws_api_messages_forward($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_channel_or_404($viewer, $params['id']);
    $text = (string) ($params['text'] ?? '');
    $app_id = ws_api_writer_app_id();

    $checked = ws_forward_to_channel_check($viewer, (array) $params['message_ids'], $target, $text, array('app_id' => $app_id));

    if (!$checked['ok']) {
        if ($checked['field'] === '') {
            api_fail(503, 'service_unavailable', $checked['error']);
        }

        if ($checked['field'] === 'channel') {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error'], ($checked['field'] === 'text') ? 'text' : 'message_ids');
    }

    // The channel the messages come from is read under the same rules as
    // any other: Claude's application reads only where Claude may be asked.
    $source = ws_api_channel_or_404($viewer, $checked['channel']['id']);

    api_dry_run_stop('created', 'workspace_message', array(
        'channel_id'        => (int) $target['id'],
        'source_channel_id' => (int) $source['id'],
        'forwards'          => count($checked['rows']),
    ));

    $result = ws_forward_to_channel($viewer, (array) $params['message_ids'], $target, $text, array('app_id' => $app_id));

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'message_ids');
    }

    $row = ws_message($result['message_id']);

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

    // An MCP tool call carries the file inside its own message: the small
    // file path, measured before the text is decoded.
    if ((api_send_hook() !== null) && (ws_upload_base64_bytes($base64) > ws_mcp_upload_max_bytes())) {
        api_fail_validation(lang(array('string' => 'Through MCP a file may be at most {var:1} KB; post a larger one over the REST API.', 'vars' => (int) floor(ws_mcp_upload_max_bytes() / 1024))), 'content_base64');
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
    $bytes = isset($upload['bytes']) ? $upload['bytes'] : ws_upload_decode($upload['base64']);

    // The checks the file and its message meet, made on the bytes in memory
    // before anything is kept, so that a dry run meets the same refusals and
    // leaves nothing behind: no file on the disk, no folder for the channel.
    // A form part is PHP's own upload, which it deletes when the request ends.
    $checked = ws_upload_check($viewer, $channel, $upload['name'], $bytes);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], $upload['field']);
    }

    // The file it is about to carry stands in for its id: words are optional
    // beside a file.
    $message = ws_message_send_check($viewer, $channel, (string) ($params['text'] ?? ''), array('app_id' => $app_id, 'file_id' => PHP_INT_MAX));

    if (!$message['ok']) {
        api_fail_validation($message['error'], 'text');
    }

    // The message it answers, as Reply in the panel: one of this channel or
    // discussion, not deleted, of the conversation as it is now.
    $parent_id = (int) ($params['parent_id'] ?? 0);
    $refused = ws_message_parent_check($channel, $parent_id);

    if ($refused !== '') {
        api_fail_validation($refused, 'parent_id');
    }

    api_dry_run_stop('created', 'workspace_message', array(
        'channel_id' => (int) $channel['id'],
        'parent_id'  => ($parent_id > 0) ? $parent_id : null,
        'file'       => array('name' => mb_substr($checked['name'], 0, 200), 'kind' => ws_file_kind($checked['name']), 'size' => strlen((string) $bytes)),
    ));

    $stored = ws_store_upload_bytes($viewer, $channel, $upload['name'], $bytes);

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
        'parent_id' => $parent_id,
    ));

    if (!$result['ok']) {
        ws_upload_discard($stored['file_id']);
        api_fail_validation($result['error'], 'text');
    }

    // A voice message waits to be written out (voice.php); the assistants
    // its words ask are asked once it is, or now when it will not be, as
    // a message from the person's own device asks them.
    if (function_exists('ws_voice_queue')) {
        $queued = ws_voice_queue($channel, $result['message_id'], $stored['file_id'], $stored['name']);

        if (ws_api_message_asks_assistants($app_id, 'message')) {
            ws_api_assistants_carry_on(ws_voice_posted($viewer, $channel, $result['message_id'], (string) ($params['text'] ?? ''), $stored['name'], $queued));
        }
    }

    $row = ws_message($result['message_id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), 201);
}

/**
 * Sends the file a message carries to whoever may read the message, as its
 * own bytes, a part of it (Range) or, for a picture, a smaller copy (size).
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

    if (!empty($params['size'])) {
        if (ws_file_kind($message['file_name']) !== 'image') {
            api_fail_validation(lang('Only a picture has a smaller copy.'), 'size');
        }

        $preview = ws_file_preview($path, (int) $params['size']);

        if ($preview !== null) {
            api_send_file($preview['path'], $preview['mime'], (string) $message['file_name'], true);
        }
    }

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
    $app_id = ws_api_writer_app_id();

    // The checks ws_reaction_set() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_reaction_check($viewer, $message, (string) $params['emoji'], $app_id, $on);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'emoji');
    }

    api_dry_run_stop($on ? 'added' : 'removed', 'workspace_reaction', array(
        'message_id' => (int) $message['id'],
        'channel_id' => (int) $message['channel_id'],
        'emoji'      => $checked['emoji'],
        'changes'    => $checked['changes'],
    ));

    $result = ws_reaction_set($viewer, $message, (string) $params['emoji'], $app_id, $on);

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
        api_fail(403, 'forbidden', ($refusal !== null) ? $refusal : lang('Only the person\'s own team devices session (POST /auth/login) can change or delete their messages; an application key or an OAuth connection cannot do it for them.'));
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
 * GET /workspace/messages/{id}/preamble: the preamble an MCP agent is sent
 * (ws_ai_agent_preamble()), for an outside application that answers the
 * message with a model of its own. The message stays out: the application
 * has it from the event and frames it itself.
 *
 * @param array $params
 */
function ws_api_message_preamble($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $channel = ws_channel((int) $message['channel_id']);
    $parent = ($channel && ws_channel_is_thread($channel)) ? ws_thread_parent_channel($channel) : null;
    $app = api_current_app();
    $name = trim((string) ($app['name'] ?? ''));
    $asker = array('name' => ws_mcp_agent_author($message));

    $text = ws_ai_agent_preamble(
        ws_ai_briefing_head($asker, 'agent', ($name !== '') ? $name : lang('Application')),
        $parent ? ws_ai_agent_place('thread', (string) $parent['name']) : ws_ai_agent_place('channel', $channel ? (string) $channel['name'] : ''),
        '',
        ws_ai_request_language((string) $message['body'])
    );

    api_ok(array('message_id' => (int) $message['id'], 'text' => $text));
}

// What ws_api_message_preamble() returns.
function ws_api_message_preamble_schema()
{
    return array('message_id' => 'integer', 'text' => 'string');
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

    // Somebody else's message taken out by staff leaves a line in the
    // activity log.
    $result = ws_api_as_owner(function () use ($viewer, $message) {
        return ws_message_delete($viewer, $message);
    });

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

    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can delete a message from their own view; an application key or an OAuth connection cannot do it for them.'));

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

/**
 * The person's own place for a channel in their list, the way the panel's
 * sidebar keeps it: pinned to the top or not, and its place in their order.
 * Only the fields sent change. The pin is set first, and pinned channels come
 * first, so a pinned channel's place is among the pinned ones and any other's
 * after them. Either one in a public channel the person is not in joins them
 * to it, as the panel does.
 */
function ws_api_channels_prefs($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can pin or order their channels; an application cannot do it for them.'));

    if (!array_key_exists('pinned', $params) && !array_key_exists('order', $params)) {
        api_fail_validation(lang('Send pinned, order or both.'));
    }

    if ($channel['kind'] === 'thread') {
        api_fail_validation(lang('A discussion is not in the channel list, so it cannot be pinned or put in order.'));
    }

    if (!ws_channel_order_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $member = (bool) ws_channel_membership($channel['id'], $viewer['id']);

    if (!$member && ($channel['kind'] !== 'public')) {
        api_fail(409, 'not_member', lang('Only the people in a channel pin it or put it in order.'));
    }

    $would = array('id' => (int) $channel['id'], 'joins' => !$member);

    foreach (array('pinned', 'order') as $field) {
        if (array_key_exists($field, $params)) {
            $would[$field] = $params[$field];
        }
    }

    api_dry_run_stop('updated', 'workspace_channel', $would);

    if (array_key_exists('pinned', $params)) {
        $result = ws_channel_pin($viewer, $channel, (bool) $params['pinned']);

        if (!$result['ok']) {
            ws_api_channel_refused($result);
        }
    }

    // The list is numbered again either way, so order on every channel
    // matches the place the list shows it at.
    $result = ws_channel_place($viewer, $channel, (int) ($params['order'] ?? 0));

    if (!$result['ok']) {
        ws_api_channel_refused($result);
    }

    api_ok(ws_api_channel_present(ws_api_channel_brief($viewer, ws_channel($channel['id']))));
}

/* ---------------------------------------------------------------------------
   Groups of channels
   --------------------------------------------------------------------------- */

/**
 * A group of channels as the sidebar draws it. access counts the people who
 * hold access on the group itself; only staff see it, as on the panel.
 *
 * @param array $group ws_groups_for() row, with path (ws_group_path())
 * @param bool  $staff
 * @return array
 */
function ws_api_channel_group_present($group, $staff)
{
    return array(
        'id'        => (int) $group['id'],
        'name'      => (string) $group['name'],
        'parent_id' => ((int) $group['parent_id'] > 0) ? (int) $group['parent_id'] : null,
        'path'      => (string) ($group['path'] ?? ''),
        'color'     => ((string) ($group['hex'] ?? '') !== '') ? (string) $group['hex'] : null,
        'access'    => $staff ? (int) ($group['access'] ?? 0) : null,
        'mine'      => !empty($group['mine']),
    );
}

// What ws_api_channel_group_present() returns.
function ws_api_channel_group_schema()
{
    return array(
        'id'        => 'integer',
        'name'      => 'string',
        'parent_id' => 'integer?',
        'path'      => 'string',
        'color'     => 'string?',
        'access'    => 'integer?',
        'mine'      => 'boolean',
    );
}

function ws_api_channel_groups_list($params)
{
    $viewer = ws_api_viewer();
    $staff = ws_can_manage_groups($viewer);
    $out = array();

    foreach (ws_groups_for($viewer) as $group) {
        $out[] = ws_api_channel_group_present($group + array('path' => ws_group_path($group['id'])), $staff);
    }

    api_ok_list($out, count($out));
}

/**
 * What moving a channel between groups answers with, and what its dry run
 * says it would do: where the channel goes, and whom the access given on the
 * groups brings into it and takes out of it, as the panel's question before
 * the move names them.
 *
 * @param array      $channel  the channel before the move
 * @param int        $group_id where it goes, 0 for out of every group
 * @param array      $move     ws_channel_move_group()
 * @param bool       $changes  false when the channel already was there
 * @param array|null $present  the channel after the move (ws_api_channel_present()), null on a dry run
 * @return array
 */
function ws_api_channel_group_move_present($channel, $group_id, $move, $changes, $present = null)
{
    $out = array(
        'channel_id'        => (int) $channel['id'],
        'group_id'          => ((int) $group_id > 0) ? (int) $group_id : null,
        'group_path'        => ((int) $group_id > 0) ? (string) $move['path'] : null,
        'previous_group_id' => ((int) ($channel['group_id'] ?? 0) > 0) ? (int) $channel['group_id'] : null,
        'changes'           => (bool) $changes,
        'joining'           => $changes ? ws_api_channel_group_people($move['joining_ids']) : array(),
        'leaving'           => $changes ? ws_api_channel_group_people($move['leaving_ids']) : array(),
    );

    if ($present !== null) {
        $out['channel'] = $present;
    }

    return $out;
}

/**
 * The people a move of a channel brings in or takes out, by id and name.
 *
 * @param int[] $ids
 * @return array[] user_id, name
 */
function ws_api_channel_group_people($ids)
{
    $out = array();

    foreach ((array) $ids as $id) {
        $out[] = array('user_id' => (int) $id, 'name' => ws_person_name($id));
    }

    return $out;
}

// What ws_api_channel_group_move_present() returns; channel is left out of the
// dry run's detail.
function ws_api_channel_group_move_schema()
{
    return array(
        'channel_id'        => 'integer',
        'group_id'          => 'integer?',
        'group_path'        => 'string?',
        'previous_group_id' => 'integer?',
        'changes'           => 'boolean',
        'joining'           => array(array('user_id' => 'integer', 'name' => 'string')),
        'leaving'           => array(array('user_id' => 'integer', 'name' => 'string')),
        'channel'           => 'WorkspaceChannel',
    );
}

/**
 * Moves a channel into a group of channels or out of every group, the way
 * dragging it onto a group or Move to a group in its menu does. A group is
 * the same for everybody, and whoever holds access on it becomes a member of
 * every channel in it, so this is staff work rather than the person's own
 * preference: it is gated like changing the channel, on the owner of the
 * application's rights (ws_can_move_channel_group()), and an application key
 * may do it. The answer, like the dry run before it, names who comes into the
 * channel and who leaves it. Moving a channel to where it already is changes
 * nothing.
 */
function ws_api_channels_group($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_groups_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    // A discussion or a room with a guest in it stays out of the groups.
    if (!in_array((string) $channel['kind'], array('public', 'private'), true)) {
        api_fail_validation(lang('Only a public or a private channel goes into a group.'));
    }

    // null and '' (a cleared nullable field) both take it out of its group.
    $group_id = max(0, (int) ($params['group_id'] ?? 0));
    $changes = ($group_id !== (int) ($channel['group_id'] ?? 0));

    // The same checks and the same question the panel asks before the move.
    $move = ws_channel_move_group($viewer, $channel, $group_id, true);

    if (!$move['ok']) {
        if ($move['error'] === lang('That group could not be found.')) {
            api_fail_validation($move['error'], 'group_id');
        }

        api_fail(403, 'forbidden', $move['error']);
    }

    api_dry_run_stop('moved', 'workspace_channel', ws_api_channel_group_move_present($channel, $group_id, $move, $changes));

    if ($changes) {
        $move = ws_channel_move_group($viewer, $channel, $group_id);

        if (!$move['ok']) {
            api_fail(403, 'forbidden', $move['error']);
        }
    }

    $after = ws_channel($channel['id']);

    api_ok(ws_api_channel_group_move_present($channel, $group_id, $move, $changes, ws_api_channel_present(ws_api_channel_brief($viewer, $after))));
}

/* ---------------------------------------------------------------------------
   Arranging the groups of channels
   --------------------------------------------------------------------------- */

/**
 * The owner of the application as somebody who arranges the groups of
 * channels: staff, on a database that has them. Anybody else gets 403 before
 * anything about a group is said.
 *
 * @return array the reader (ws_api_viewer())
 */
function ws_api_channel_groups_staff()
{
    $viewer = ws_api_viewer();

    if (!ws_groups_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    if (!ws_can_manage_groups($viewer)) {
        api_fail(403, 'forbidden', lang('Only staff can arrange the groups of channels.'));
    }

    return $viewer;
}

/**
 * @param int $id
 * @return array the group's row
 */
function ws_api_channel_group_or_404($id)
{
    $group = ws_group((int) $id);

    if (!$group) {
        api_fail_not_found(lang('Group'));
    }

    return $group;
}

/**
 * Answers a refusal of the module's group functions: 403 for a missing right,
 * 422 for the rest, on the field it names.
 *
 * @param array $result ok, error, field
 */
function ws_api_channel_group_refused($result)
{
    $error = (string) $result['error'];

    if (in_array($error, array(lang('Only staff can arrange the groups of channels.'), lang('Only staff can give access on a group.')), true)) {
        api_fail(403, 'forbidden', $error);
    }

    api_fail_validation($error, (($result['field'] ?? '') !== '') ? $result['field'] : null);
}

/**
 * A group as GET /workspace/channel-groups lists it to staff.
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array|null null when there is no such group
 */
function ws_api_channel_group_one($viewer, $group_id)
{
    foreach (ws_groups_for($viewer) as $group) {
        if ((int) $group['id'] === (int) $group_id) {
            return ws_api_channel_group_present($group + array('path' => ws_group_path($group['id'])), ws_can_manage_groups($viewer));
        }
    }

    return null;
}

/**
 * Where a group named so stands inside that one, as path names it.
 *
 * @param int    $parent_id 0 for the top
 * @param string $name
 * @return string
 */
function ws_api_channel_group_path_under($parent_id, $name)
{
    return (((int) $parent_id > 0) ? ws_group_path($parent_id) . ' › ' : '') . (string) $name;
}

/**
 * The names of the channels the owner of the application reads, out of these.
 * A channel they do not read - a private one they are not in - is counted
 * wherever a change to the groups reaches it, never named, as on the panel.
 *
 * @param array $viewer
 * @param int[] $channel_ids
 * @return string[] channel id => name
 */
function ws_api_channel_groups_seen($viewer, $channel_ids)
{
    $out = array();

    foreach (array_unique(array_map('intval', (array) $channel_ids)) as $channel_id) {
        $channel = ws_channel($channel_id);

        if ($channel && ws_can_read_channel($viewer, $channel) && (!ws_api_is_claude() || ws_claude_channel_allowed($channel))) {
            $out[$channel_id] = (string) $channel['name'];
        }
    }

    return $out;
}

/**
 * Channels or groups by id and name.
 *
 * @param string[] $names id => name
 * @param string   $key   what the id is called
 * @return array[]
 */
function ws_api_channel_group_named($names, $key)
{
    $out = array();

    foreach ((array) $names as $id => $name) {
        $out[] = array($key => (int) $id, 'name' => (string) $name);
    }

    return $out;
}

/**
 * One channel a change to the groups brings people into or takes them out of.
 *
 * @param int    $channel_id
 * @param string $name
 * @param int[]  $added
 * @param int[]  $removed
 * @return array
 */
function ws_api_channel_group_membership_present($channel_id, $name, $added, $removed)
{
    return array(
        'channel_id' => (int) $channel_id,
        'name'       => (string) $name,
        'joining'    => ws_api_channel_group_people($added),
        'leaving'    => ws_api_channel_group_people($removed),
    );
}

/**
 * Whom a change to the groups brings into channels and takes out of them,
 * channel by channel, as ws_group_sync_plan() foresees it or the sync did it.
 * joining and leaving count every membership; a channel the owner of the
 * application does not read is counted in unseen_channels, not named.
 *
 * @param array[]  $changes channel id => added, removed (user ids)
 * @param string[] $seen    channel id => name (ws_api_channel_groups_seen())
 * @return array
 */
function ws_api_channel_group_memberships_present($changes, $seen)
{
    $out = array(
        'joining'         => 0,
        'leaving'         => 0,
        'channels'        => array(),
        'unseen_channels' => 0,
    );

    foreach ((array) $changes as $channel_id => $change) {
        $added = (array) ($change['added'] ?? array());
        $removed = (array) ($change['removed'] ?? array());

        if (empty($added) && empty($removed)) {
            continue;
        }

        $out['joining'] += count($added);
        $out['leaving'] += count($removed);

        if (isset($seen[(int) $channel_id])) {
            $out['channels'][] = ws_api_channel_group_membership_present($channel_id, $seen[(int) $channel_id], $added, $removed);
        } else {
            $out['unseen_channels'] += 1;
        }
    }

    return $out;
}

// What ws_api_channel_group_memberships_present() returns.
function ws_api_channel_group_memberships_schema()
{
    return array(
        'joining'         => 'integer',
        'leaving'         => 'integer',
        'channels'        => array(array(
            'channel_id' => 'integer',
            'name'       => 'string',
            'joining'    => array(array('user_id' => 'integer', 'name' => 'string')),
            'leaving'    => array(array('user_id' => 'integer', 'name' => 'string')),
        )),
        'unseen_channels' => 'integer',
    );
}

/**
 * What changing a group answers with, and what its dry run says it would be.
 *
 * @param array $group       ws_api_channel_group_present()
 * @param array $memberships ws_api_channel_group_memberships_present()
 * @return array
 */
function ws_api_channel_group_change_present($group, $memberships)
{
    return array(
        'group'       => $group,
        'memberships' => $memberships,
    );
}

// What ws_api_channel_group_change_present() returns.
function ws_api_channel_group_change_schema()
{
    return array(
        'group'       => 'WorkspaceChannelGroup',
        'memberships' => 'WorkspaceChannelGroupMemberships',
    );
}

/**
 * What taking a group away does, and what its dry run says it would do: where
 * what was in it goes, whose access ends and who leaves which channel.
 *
 * @param array    $group       the group's row with its path, read before it goes
 * @param int[]    $channel_ids the channels in it
 * @param string[] $groups      the groups in it, id => name
 * @param int[]    $holders     the people who held access on it
 * @param string[] $seen        ws_api_channel_groups_seen()
 * @param array    $memberships ws_api_channel_group_memberships_present()
 * @param bool     $deleted     false on a dry run
 * @return array
 */
function ws_api_channel_group_removal_present($group, $channel_ids, $groups, $holders, $seen, $memberships, $deleted)
{
    $channels = array();
    $unseen = 0;

    foreach ((array) $channel_ids as $channel_id) {
        if (isset($seen[(int) $channel_id])) {
            $channels[(int) $channel_id] = $seen[(int) $channel_id];
        } else {
            $unseen++;
        }
    }

    return array(
        'id'              => (int) $group['id'],
        'name'            => (string) $group['name'],
        'path'            => (string) ($group['path'] ?? $group['name']),
        'parent_id'       => ((int) $group['parent_id'] > 0) ? (int) $group['parent_id'] : null,
        'channels'        => ws_api_channel_group_named($channels, 'channel_id'),
        'unseen_channels' => $unseen,
        'groups'          => ws_api_channel_group_named($groups, 'id'),
        'access'          => ws_api_channel_group_people($holders),
        'memberships'     => $memberships,
        'deleted'         => (bool) $deleted,
    );
}

// What ws_api_channel_group_removal_present() returns.
function ws_api_channel_group_removal_schema()
{
    return array(
        'id'              => 'integer',
        'name'            => 'string',
        'path'            => 'string',
        'parent_id'       => 'integer?',
        'channels'        => array(array('channel_id' => 'integer', 'name' => 'string')),
        'unseen_channels' => 'integer',
        'groups'          => array(array('id' => 'integer', 'name' => 'string')),
        'access'          => array(array('user_id' => 'integer', 'name' => 'string')),
        'memberships'     => 'WorkspaceChannelGroupMemberships',
        'deleted'         => 'boolean',
    );
}

/**
 * Somebody who holds access on a group, with who gave it and on which group.
 *
 * @param array $person   ws_group_access_people() row
 * @param int   $group_id
 * @return array
 */
function ws_api_channel_group_holder_present($person, $group_id)
{
    return array(
        'user_id'    => (int) $person['id'],
        'name'       => (string) $person['name'],
        'granted_by' => ((string) ($person['granted_by'] ?? '') !== '') ? (string) $person['granted_by'] : null,
        'granted_at' => ((int) ($person['granted_at'] ?? 0) > 0) ? api_time($person['granted_at']) : null,
        'group_id'   => (int) $group_id,
        'group_path' => ws_group_path($group_id),
    );
}

/**
 * Who holds access on a group, as Access on the group in its menu lists them:
 * on the group itself, and on the groups above it, whose access reaches here
 * too; and what that access reaches.
 *
 * @param int $group_id
 * @return array
 */
function ws_api_channel_group_access_present($group_id)
{
    $people = array();
    $above = array();

    foreach (ws_group_access_people($group_id) as $person) {
        $people[] = ws_api_channel_group_holder_present($person, $group_id);
    }

    foreach (array_slice(ws_group_chain($group_id), 1) as $parent_id) {
        foreach (ws_group_access_people($parent_id) as $person) {
            $above[] = ws_api_channel_group_holder_present($person, $parent_id);
        }
    }

    return array(
        'group_id' => (int) $group_id,
        'path'     => ws_group_path($group_id),
        'people'   => $people,
        'above'    => $above,
        'reach'    => ws_group_reach($group_id),
    );
}

// What ws_api_channel_group_access_present() returns.
function ws_api_channel_group_access_schema()
{
    $holder = array(
        'user_id'    => 'integer',
        'name'       => 'string',
        'granted_by' => 'string?',
        'granted_at' => 'string?',
        'group_id'   => 'integer',
        'group_path' => 'string',
    );

    return array(
        'group_id' => 'integer',
        'path'     => 'string',
        'people'   => array($holder),
        'above'    => array($holder),
        'reach'    => array('groups' => 'integer', 'channels' => 'integer', 'private' => 'integer', 'archived' => 'integer'),
    );
}

/**
 * What giving or taking back access on a group answers with: who holds it
 * now (as it stands before the change, in a dry run) and who comes into or
 * leaves which channel.
 *
 * @param int   $group_id
 * @param array $memberships ws_api_channel_group_memberships_present()
 * @return array
 */
function ws_api_channel_group_access_change_present($group_id, $memberships)
{
    return array(
        'access'      => ws_api_channel_group_access_present($group_id),
        'memberships' => $memberships,
    );
}

// What ws_api_channel_group_access_change_present() returns.
function ws_api_channel_group_access_change_schema()
{
    return array(
        'access'      => 'WorkspaceChannelGroupAccess',
        'memberships' => 'WorkspaceChannelGroupMemberships',
    );
}

/**
 * Creates a group of channels the way New group in the sidebar does. Staff
 * work, gated on the owner of the application's rights; an application key
 * may do it.
 */
function ws_api_channel_groups_create($params)
{
    $viewer = ws_api_channel_groups_staff();

    // The checks ws_group_save() makes, made first so that a dry run meets
    // the same refusals.
    $name = ws_group_clean_name($params['name'] ?? '');

    if ($name === '') {
        api_fail_validation(lang('A group needs a name.'), 'name');
    }

    $parent_id = max(0, (int) ($params['parent_id'] ?? 0));
    $refused = ws_group_place_refused(0, $parent_id);

    if ($refused !== '') {
        api_fail_validation($refused, 'parent_id');
    }

    $color = ws_palette_place_of($params['color'] ?? '');

    api_dry_run_stop('created', 'workspace_channel_group', array(
        'name'      => $name,
        'parent_id' => ($parent_id > 0) ? $parent_id : null,
        'path'      => ws_api_channel_group_path_under($parent_id, $name),
        'color'     => ($color > 0) ? ws_palette_hex($color) : null,
    ));

    $result = ws_api_as_owner(function () use ($viewer, $name, $color, $parent_id) {
        return ws_group_save($viewer, array('id' => 0, 'name' => $name, 'color' => $color, 'parent_id' => $parent_id));
    });

    if (!$result['ok']) {
        ws_api_channel_group_refused($result);
    }

    api_ok(ws_api_channel_group_one($viewer, $result['id']), 201);
}

/**
 * Changes a group of channels: its name and colour as the group's form does,
 * its place as the sidebar's moves do. A move inside another group changes
 * who is in its channels, so the answer, like the dry run before it, names
 * who comes in and who leaves.
 */
function ws_api_channel_groups_update($params)
{
    $viewer = ws_api_channel_groups_staff();
    $group = ws_api_channel_group_or_404($params['id']);
    $id = (int) $group['id'];

    $saving = array_key_exists('name', $params) || array_key_exists('color', $params);
    $reordering = array_key_exists('before_id', $params);

    if (!$saving && !$reordering && !array_key_exists('parent_id', $params)) {
        api_fail_validation(lang('Send at least one of name, color, parent_id or before_id.'));
    }

    // The whole row first, then what was sent on top of it.
    $name = array_key_exists('name', $params) ? ws_group_clean_name($params['name']) : (string) $group['name'];

    if ($name === '') {
        api_fail_validation(lang('A group needs a name.'), 'name');
    }

    $color = array_key_exists('color', $params) ? ws_palette_place_of($params['color']) : ws_palette_place($group['color']);
    $parent_id = array_key_exists('parent_id', $params) ? max(0, (int) $params['parent_id']) : (int) $group['parent_id'];
    $refused = ws_group_place_refused($id, $parent_id);

    if ($refused !== '') {
        api_fail_validation($refused, 'parent_id');
    }

    // ws_group_move() refuses such a before_id too, but only after the name
    // and colour are saved and without naming the field: asked here, before
    // the dry run as well.
    $before_id = max(0, (int) ($params['before_id'] ?? 0));

    if (!ws_group_before_fits($id, $parent_id, $before_id)) {
        api_fail_validation(lang('before_id must be another group of the level the group ends up on.'), 'before_id');
    }

    $moved = ($parent_id !== (int) $group['parent_id']);
    $plan = $moved ? ws_group_move_plan($id, $parent_id) : array();
    $seen = ws_api_channel_groups_seen($viewer, array_keys($plan));

    api_dry_run_stop('updated', 'workspace_channel_group', ws_api_channel_group_change_present(array_merge(ws_api_channel_group_one($viewer, $id), array(
        'name'      => $name,
        'parent_id' => ($parent_id > 0) ? $parent_id : null,
        'path'      => ws_api_channel_group_path_under($parent_id, $name),
        'color'     => ($color > 0) ? ws_palette_hex($color) : null,
    )), ws_api_channel_group_memberships_present($plan, $seen)));

    $synced = array();

    // The form saves the name, the colour and the place - last among the
    // groups there - and writes the line in the activity log. The sidebar's
    // move writes none, and is what puts a group in front of another one.
    if ($saving) {
        $result = ws_api_as_owner(function () use ($viewer, $id, $name, $color, $parent_id) {
            return ws_group_save($viewer, array('id' => $id, 'name' => $name, 'color' => $color, 'parent_id' => $parent_id));
        });

        if (!$result['ok']) {
            ws_api_channel_group_refused($result);
        }

        $synced = $result['synced'];
    }

    if ($reordering || ($moved && !$saving)) {
        $result = ws_group_move($viewer, $id, $parent_id, $before_id);

        if (!$result['ok']) {
            ws_api_channel_group_refused($result);
        }

        $synced += $result['synced'];
    }

    $seen += ws_api_channel_groups_seen($viewer, array_diff(array_keys($synced), array_keys($seen)));

    api_ok(ws_api_channel_group_change_present(ws_api_channel_group_one($viewer, $id), ws_api_channel_group_memberships_present($synced, $seen)));
}

/**
 * Takes a group of channels away the way its menu does: what was in it moves
 * up, the access given on it ends, and so do the memberships only that
 * access had given. The answer, like the dry run before it, says all three.
 */
function ws_api_channel_groups_delete($params)
{
    $viewer = ws_api_channel_groups_staff();
    $group = ws_api_channel_group_or_404($params['id']);
    $id = (int) $group['id'];

    // What moves up and whose access ends, read before it does.
    $channel_ids = array_map('intval', (array) db_values("SELECT id FROM ws_channels WHERE group_id = '" . $id . "'"));
    $groups = array();
    $holders = array();

    foreach (ws_groups_all() as $child_id => $row) {
        if ((int) $row['parent_id'] === $id) {
            $groups[(int) $child_id] = (string) $row['name'];
        }
    }

    foreach (ws_group_access_people($id) as $person) {
        $holders[] = (int) $person['id'];
    }

    $row = $group + array('path' => ws_group_path($id));
    $plan = ws_group_remove_plan($id);
    $seen = ws_api_channel_groups_seen($viewer, array_merge($channel_ids, array_keys($plan)));

    api_dry_run_stop('deleted', 'workspace_channel_group', ws_api_channel_group_removal_present($row, $channel_ids, $groups, $holders, $seen, ws_api_channel_group_memberships_present($plan, $seen), false));

    $result = ws_api_as_owner(function () use ($viewer, $id) {
        return ws_group_remove($viewer, $id);
    });

    if (!$result['ok']) {
        ws_api_channel_group_refused($result);
    }

    $seen += ws_api_channel_groups_seen($viewer, array_diff(array_keys($result['synced']), array_keys($seen)));

    api_ok(ws_api_channel_group_removal_present($row, $channel_ids, $groups, $holders, $seen, ws_api_channel_group_memberships_present($result['synced'], $seen), true));
}

function ws_api_channel_groups_access($params)
{
    ws_api_channel_groups_staff();
    $group = ws_api_channel_group_or_404($params['id']);

    api_ok(ws_api_channel_group_access_present($group['id']));
}

/**
 * Gives colleagues access on a group the way Give access does: they become
 * members of every channel in it and in the groups inside it. The answer,
 * like the dry run before it, names who comes into which channel.
 */
function ws_api_channel_groups_access_grant($params)
{
    $viewer = ws_api_channel_groups_staff();
    $group = ws_api_channel_group_or_404($params['id']);
    $id = (int) $group['id'];

    // The panel offers only the team: anybody else is left out, and nobody
    // left is the panel's own refusal.
    $user_ids = array_values(array_intersect(ws_api_user_ids($params['user_ids'] ?? array(), 'user_ids'), ws_team_ids()));

    if (empty($user_ids)) {
        api_fail_validation(lang('Choose who gets the access.'), 'user_ids');
    }

    $plan = ws_group_access_plan($id, $user_ids, array());
    $seen = ws_api_channel_groups_seen($viewer, array_keys($plan));

    api_dry_run_stop('granted', 'workspace_channel_group_access', ws_api_channel_group_access_change_present($id, ws_api_channel_group_memberships_present($plan, $seen)));

    $result = ws_api_as_owner(function () use ($viewer, $id, $user_ids) {
        return ws_group_access_grant($viewer, $id, $user_ids);
    });

    if (!$result['ok']) {
        ws_api_channel_group_refused(array('error' => $result['error'], 'field' => 'user_ids'));
    }

    $seen += ws_api_channel_groups_seen($viewer, array_diff(array_keys($result['synced']), array_keys($seen)));

    api_ok(ws_api_channel_group_access_change_present($id, ws_api_channel_group_memberships_present($result['synced'], $seen)));
}

/**
 * Takes the access on a group back from one person the way Take the access
 * back does. The panel offers it only for somebody who holds the access; for
 * anybody else nothing is written, not even the line in the activity log.
 */
function ws_api_channel_groups_access_revoke($params)
{
    $viewer = ws_api_channel_groups_staff();
    $group = ws_api_channel_group_or_404($params['id']);
    $id = (int) $group['id'];
    $user_id = (int) $params['user_id'];
    $holds = ((int) db_value("SELECT COUNT(*) FROM ws_channel_group_access WHERE group_id = '" . $id . "' AND user_id = '" . $user_id . "'") > 0);

    $plan = $holds ? ws_group_access_plan($id, array(), array($user_id)) : array();
    $seen = ws_api_channel_groups_seen($viewer, array_keys($plan));

    api_dry_run_stop('revoked', 'workspace_channel_group_access', ws_api_channel_group_access_change_present($id, ws_api_channel_group_memberships_present($plan, $seen)));

    $synced = array();

    if ($holds) {
        $result = ws_api_as_owner(function () use ($viewer, $id, $user_id) {
            return ws_group_access_revoke($viewer, $id, array($user_id));
        });

        if (!$result['ok']) {
            ws_api_channel_group_refused($result);
        }

        $synced = $result['synced'];
    }

    $seen += ws_api_channel_groups_seen($viewer, array_diff(array_keys($synced), array_keys($seen)));

    api_ok(ws_api_channel_group_access_change_present($id, ws_api_channel_group_memberships_present($synced, $seen)));
}

function ws_api_messages_ack($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    ws_api_person_only();

    // The checks ws_ack_set() makes, made first so that a dry run meets the
    // same refusals.
    $checked = function_exists('ws_ack_set_check') ? ws_ack_set_check($viewer, $message) : array('ok' => false, 'error' => lang('Invalid request.'));

    if (!$checked['ok']) {
        api_fail_validation($checked['error']);
    }

    api_dry_run_stop('acknowledged', 'workspace_read_receipt', array(
        'message_id' => (int) $message['id'],
        'channel_id' => (int) $message['channel_id'],
        'changes'    => $checked['changes'],
    ));

    $result = ws_ack_set($viewer, $message);

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
    $poll = ws_api_poll_or_404($viewer, $params['id']);

    ws_api_person_only();

    $checked = ws_poll_vote_check($viewer, $poll);

    if (!$checked['ok']) {
        // A vote after the poll's time closes it, as in the panel; a dry run
        // leaves that to the next vote or the next read of the channel
        // (ws_polls_autoclose()).
        if ($checked['expired'] && !api_dry_run_requested()) {
            ws_poll_close(null, $poll);
        }

        ws_api_poll_refused($checked['error'], $checked['field']);
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
        ws_api_poll_refused($result['error']);
    }

    ws_api_poll_message_ok($viewer, $poll);
}

/**
 * A poll the owner may read, as the vote, the change and the closing find it:
 * one whose message is gone, or in a channel the owner cannot read, is a 404,
 * and one of an earlier version of the conversation a 422.
 *
 * @param array $viewer
 * @param int   $id
 * @return array ws_polls row
 */
function ws_api_poll_or_404($viewer, $id)
{
    $poll = ws_poll((int) $id);
    $message = $poll ? ws_message((int) $poll['message_id']) : null;

    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($poll['channel_id']))) {
        api_fail_not_found(lang('Poll'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $poll['channel_id']);

    // An earlier version of a conversation is kept as it was (eras.php).
    if (function_exists('ws_message_in_past') && ws_message_in_past($message)) {
        ws_api_poll_refused(lang('This message is in an earlier version of the conversation, which is kept as it was.'), 'message_id');
    }

    return $poll;
}

/**
 * Answers with the message that asks the poll, as it is now.
 *
 * @param array $viewer
 * @param array $poll
 * @param int   $status
 */
function ws_api_poll_message_ok($viewer, $poll, $status = 200)
{
    $row = ws_message($poll['message_id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), $status);
}

/**
 * A refusal of a vote, ws_poll_edit() or ws_poll_close() as the API answers it.
 *
 * @param string $error
 * @param string $field
 */
function ws_api_poll_refused($error, $field = '')
{
    $forbidden = array(
        lang('Only the person who asked can change the poll.'),
        lang('Only the person who asked, or whoever runs the channel, can close the poll.'),
        lang('You cannot post in that channel.'),
    );

    if (in_array($error, $forbidden, true)) {
        api_fail(403, 'forbidden', $error);
    }

    if ($error === lang('That poll could not be found.')) {
        api_fail_not_found(lang('Poll'));
    }

    // A closed poll, or one whose time ran out, is refused on its closing time.
    if (($field === '') && ($error === lang('The poll is closed.'))) {
        $field = 'closes_at';
    }

    api_fail_validation($error, ($field !== '') ? $field : null);
}

/**
 * Asks a poll in a channel, the way Poll by the composer and the /poll
 * command do: the question is the person's message, and the options, the
 * settings and the votes are the poll's. The answer is that message.
 */
function ws_api_polls_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    // Asked in the person's own name: an application key and an OAuth
    // connection write as the application, and a poll asked so could be
    // neither changed by the person nor closed with its result in their name.
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can ask a poll; an application key or an OAuth connection cannot ask it in their name.'));

    if (!ws_interact_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $options = array();

    foreach ((array) $params['options'] as $item) {
        if (!is_string($item) && !is_int($item) && !is_float($item)) {
            api_fail_validation(lang(array('string' => 'Each item of {var:1} must be a text.', 'vars' => 'options')), 'options');
        }

        $options[] = (string) $item;
    }

    $data = array(
        'question'  => (string) $params['question'],
        'options'   => $options,
        'multiple'  => !empty($params['multiple']),
        'anonymous' => !empty($params['anonymous']),
        'closes_at' => (int) ($params['closes_at'] ?? 0),
    );

    // The checks ws_poll_create() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_poll_input($data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], ($checked['field'] !== '') ? $checked['field'] : null);
    }

    api_dry_run_stop('created', 'workspace_poll', array(
        'channel_id' => (int) $channel['id'],
        'options'    => $checked['options'],
        'multiple'   => $data['multiple'],
        'anonymous'  => $data['anonymous'],
        'closes_at'  => api_time($checked['closes_at']),
    ));

    $result = ws_poll_create($viewer, $channel, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error'], ($result['field'] !== '') ? $result['field'] : null);
    }

    ws_api_poll_message_ok($viewer, ws_poll($result['poll_id']), 201);
}

/**
 * Changes an open poll the person asked, the way Edit the poll on it does. A
 * field left out stays as it is. Once somebody has voted, an option with
 * votes keeps its words and stays, and multiple and anonymous no longer
 * change; options can still be added and the question worded better.
 */
function ws_api_polls_update($params)
{
    $viewer = ws_api_viewer();
    $poll = ws_api_poll_or_404($viewer, $params['id']);

    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can change their poll; an application key or an OAuth connection cannot change it for them.'));

    $fields = array('question', 'options', 'multiple', 'anonymous', 'closes_at');

    if (!array_intersect($fields, array_keys($params))) {
        api_fail_validation(lang('Send at least one of question, options, multiple, anonymous or closes_at.'));
    }

    $options = array();

    foreach ((array) db_items("SELECT id, label FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "' ORDER BY sort, id") as $row) {
        $options[(int) $row['id']] = array('id' => (int) $row['id'], 'label' => (string) $row['label']);
    }

    // The poll as it is, with what was sent on top.
    $data = array(
        'question'  => (string) ws_message($poll['message_id'])['body'],
        'options'   => array_values($options),
        'multiple'  => ((int) $poll['multiple'] === 1),
        'anonymous' => ((int) $poll['anonymous'] === 1),
        'closes_at' => (int) $poll['closes_at'],
    );

    if (array_key_exists('question', $params)) {
        $data['question'] = (string) $params['question'];
    }

    if (array_key_exists('multiple', $params)) {
        $data['multiple'] = !empty($params['multiple']);
    }

    if (array_key_exists('anonymous', $params)) {
        $data['anonymous'] = !empty($params['anonymous']);
    }

    // null clears it: open until somebody closes it.
    if (array_key_exists('closes_at', $params)) {
        $data['closes_at'] = ($params['closes_at'] === '') ? 0 : (int) $params['closes_at'];
    }

    if (array_key_exists('options', $params)) {
        $data['options'] = array();

        foreach ((array) $params['options'] as $item) {
            if (is_string($item) || is_int($item) || is_float($item)) {
                $item = array('label' => (string) $item);
            }

            if (!is_array($item) || !isset($item['label']) || (!is_string($item['label']) && !is_int($item['label']) && !is_float($item['label']))) {
                api_fail_validation(lang(array('string' => 'Each item of {var:1} needs a label.', 'vars' => 'options')), 'options');
            }

            $option_id = 0;

            if (isset($item['id'])) {
                $option_id = (is_int($item['id']) || (is_string($item['id']) && ctype_digit($item['id']))) ? (int) $item['id'] : -1;

                if (!isset($options[$option_id])) {
                    api_fail_validation(lang(array('string' => 'Each id in {var:1} must be the id of one of the poll\'s options.', 'vars' => 'options')), 'options');
                }
            }

            $data['options'][] = array('id' => $option_id, 'label' => (string) $item['label']);
        }
    }

    // The checks ws_poll_edit() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_poll_edit_check($viewer, $poll, $data);

    if (!$checked['ok']) {
        ws_api_poll_refused($checked['error'], $checked['field']);
    }

    $added = 0;

    foreach ($checked['options'] as $option) {
        $added += ($option['id'] === 0) ? 1 : 0;
    }

    api_dry_run_stop('updated', 'workspace_poll', array(
        'id'         => (int) $poll['id'],
        'message_id' => (int) $poll['message_id'],
        'options'    => count($checked['options']),
        'added'      => $added,
        'removed'    => count(array_diff(array_keys($options), array_column($checked['options'], 'id'))),
        'multiple'   => $checked['multiple'],
        'anonymous'  => $checked['anonymous'],
        'closes_at'  => api_time($checked['closes_at']),
    ));

    $result = ws_poll_edit($viewer, $poll, $data);

    if (!$result['ok']) {
        ws_api_poll_refused($result['error'], $result['field']);
    }

    ws_api_poll_message_ok($viewer, $poll);
}

/**
 * Closes a poll, the way Close the poll on it does: the asker closes their own,
 * whoever runs the channel any. The result goes into the channel - a clear
 * winner as a decision in the asker's name, a tie or a poll nobody voted in
 * as a line of the workspace's. A poll already closed is not an error.
 */
function ws_api_polls_close($params)
{
    $viewer = ws_api_viewer();
    $poll = ws_api_poll_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can close a poll; an application cannot close it for them.'));

    // Already closed: nothing to do.
    if ((int) $poll['closed_at'] > 0) {
        ws_api_poll_message_ok($viewer, $poll);
    }

    if (!ws_can_close_poll($viewer, $poll, ws_channel($poll['channel_id']))) {
        ws_api_poll_refused(lang('Only the person who asked, or whoever runs the channel, can close the poll.'));
    }

    api_dry_run_stop('closed', 'workspace_poll', array('id' => (int) $poll['id'], 'message_id' => (int) $poll['message_id']));

    $result = ws_poll_close($viewer, $poll);

    if (!$result['ok']) {
        ws_api_poll_refused($result['error']);
    }

    ws_api_poll_message_ok($viewer, $poll);
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

/**
 * The message pinned to the top of a channel (pins.php) is set from the
 * person's own app: the channel then says who pinned it, so like a change
 * to their words it comes from a team devices session only. The pin bar is
 * drawn from the channel's state, which reaches that session alone
 * (ws_api_sync_channel_state()).
 *
 * @param array $viewer
 * @param int   $id the message's
 * @return array message, channel
 */
function ws_api_pin_target($viewer, $id)
{
    $message = ws_api_message_or_404($viewer, $id);

    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can pin a message, take the pin off or close it; an application key or an OAuth connection cannot do it for them.'));

    if (!ws_pins_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    return array('message' => $message, 'channel' => ws_channel($message['channel_id']));
}

/**
 * Pins a message to the top of its channel in place of the one there, or
 * with on false takes it off, the way Pin to the top and Take the pin off in
 * the message menu do. Asking for what already is - pinning the pinned one,
 * taking the pin off another message - changes nothing and writes no line,
 * so a retried call does not say twice that a message was pinned, and an
 * app that missed a newer pin does not take it off.
 */
function ws_api_messages_pin($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_pin_target($viewer, $params['id']);
    $message = $target['message'];
    $channel = $target['channel'];

    if (ws_channel_is_thread($channel)) {
        api_fail_validation(lang('A discussion has no message pinned to the top.'));
    }

    if (!ws_can_pin($viewer, $channel)) {
        api_fail(403, 'forbidden', ((int) $channel['archived_at'] > 0) ? lang('This channel is archived. It can be read but not written in.') : lang('You cannot post in that channel.'));
    }

    $on = !array_key_exists('on', $params) || !empty($params['on']);
    $current = ws_channel_pinned_message($channel);
    $pinned = $current ? (int) $current['id'] : 0;

    if ($on && !in_array($message['kind'], array('message', 'note', 'decision', 'task'), true)) {
        api_fail_validation(lang('Only a message, a note, a decision or a task card can be pinned.'));
    }

    $changes = $on ? ($pinned !== (int) $message['id']) : ($pinned === (int) $message['id']);

    api_dry_run_stop($on ? 'pinned' : 'unpinned', 'workspace_pin', array(
        'channel_id' => (int) $channel['id'],
        'message_id' => (int) $message['id'],
        'replaces'   => ($on && $changes && ($pinned > 0)) ? $pinned : null,
        'changes'    => $changes,
    ));

    if ($changes) {
        $result = ws_channel_pin_message($viewer, $channel, $on ? (int) $message['id'] : 0);

        if (!$result['ok']) {
            api_fail_validation($result['error']);
        }
    }

    api_ok(ws_api_sync_channel_state_present(ws_api_sync_channel_state($viewer, ws_channel($channel['id']))));
}

/**
 * Closes the pinned message for the person, the way the cross on the pin
 * bar does: it stays closed for them, on every screen, until another one is
 * pinned. Only the message pinned now can be closed; for any other nothing
 * changes, so a newer pin the app has not drawn yet stays open.
 */
function ws_api_messages_pin_close($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_pin_target($viewer, $params['id']);
    $message = $target['message'];
    $channel = $target['channel'];

    if (!ws_channel_membership($channel['id'], $viewer['id'])) {
        api_fail(409, 'not_member', lang('Only the people in a channel keep the pinned message closed; anybody else closes it on their own screen.'));
    }

    $pinned = ws_channel_pinned_message($channel);
    $changes = $pinned && ((int) $pinned['id'] === (int) $message['id']);

    api_dry_run_stop('closed', 'workspace_pin', array(
        'channel_id' => (int) $channel['id'],
        'message_id' => (int) $message['id'],
        'changes'    => $changes,
    ));

    if ($changes) {
        $result = ws_channel_pin_hide($viewer, $channel);

        if (!$result['ok']) {
            api_fail(503, 'service_unavailable', $result['error']);
        }
    }

    api_ok(ws_api_sync_channel_state_present(ws_api_sync_channel_state($viewer, ws_channel($channel['id']))));
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
        'record'            => 'WorkspaceApprovalRecord?',
        'people'            => array(array('user_id' => 'integer', 'name' => 'string', 'decision' => 'string', 'note' => 'string?', 'decided_at' => 'string?')),
        'guests'            => array(array('id' => 'integer', 'name' => 'string', 'email' => 'string?', 'decision' => 'string', 'note' => 'string?', 'decided_at' => 'string?', 'link_expires_at' => 'string?')),
        'outcome'           => 'string',
        'requested_by'      => 'WorkspacePerson?',
        'closed_by'         => 'WorkspacePerson?',
        'result_message_id' => 'integer?',
        'closes_at'         => 'string?',
        'closed_at'         => 'string?',
        'created_at'        => 'string',
    );
}

// The record a request is about; null when it is about none.
function ws_api_approval_record_schema()
{
    return array('type' => 'string', 'id' => 'integer');
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

    // The checks ws_approval_decide() makes, made first so that a dry run
    // meets the same refusals. An answer after the request's time closes it,
    // as in the panel; a dry run leaves that to the next answer or the hourly
    // job.
    $checked = ws_approval_decide_check($viewer, $approval, (string) $params['decision']);

    if (!$checked['ok']) {
        if ($checked['expired'] && !api_dry_run_requested()) {
            ws_approval_evaluate($approval);
        }

        api_fail_validation($checked['error'], 'decision');
    }

    api_dry_run_stop('decided', 'workspace_approval', array(
        'id'         => (int) $approval['id'],
        'message_id' => (int) $approval['message_id'],
        'decision'   => (string) $params['decision'],
        'outcome'    => $checked['outcome'],
    ));

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
        // The time on the due date and the e-mail reminder (reminders.php);
        // null on a site whose tables do not have them yet.
        'due_time'          => ((string) ($task['due_time'] ?? '') !== '') ? substr((string) $task['due_time'], 0, 5) : null,
        'remind_minutes'    => (($task['remind_minutes'] ?? null) !== null) ? (int) $task['remind_minutes'] : null,
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
        'due_time'          => 'string?',
        'remind_minutes'    => 'integer?',
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

    foreach (array('title', 'description', 'status', 'priority', 'start_date', 'due_date', 'due_time', 'remind_minutes', 'estimate_minutes', 'department_id', 'channel_id') as $field) {
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

    // The checks ws_task_create() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_task_create_check($viewer, $data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], $checked['field'] ?: null);
    }

    api_dry_run_stop('created', 'workspace_task', array(
        'title'      => $checked['clean']['title'],
        'channel_id' => (int) ($checked['clean']['channel_id'] ?? 0),
        'assignees'  => $checked['clean']['assignees'] ?? array(),
        'warnings'   => $warnings,
    ));

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

/**
 * Makes one checklist item of a message a task, the way the button beside
 * the item does (task_work.php, ws_checklist_item_task()).
 */
function ws_api_messages_item_task($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $item = (int) $params['item'];

    // The checks ws_checklist_item_task() makes, made first so that a dry
    // run meets the same refusals.
    $checked = ws_checklist_item_task_check($viewer, $message, $item);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'item');
    }

    api_dry_run_stop('created', 'workspace_task', array(
        'title'      => $checked['data']['title'],
        'channel_id' => (int) $checked['data']['channel_id'],
        'message_id' => (int) $message['id'],
        'item'       => $item,
        'assignees'  => $checked['data']['assignees'],
    ));

    $result = ws_checklist_item_task($viewer, $message, $item);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'item');
    }

    ws_api_log(lang(array('string' => 'workspace task ({var:1}) was created', 'vars' => ws_task_number($result['task_id']))));

    api_ok(ws_api_task_present($viewer, ws_task($result['task_id']), array()), 201);
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

    // The checks ws_task_update() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_task_update_check($viewer, $task, $data);

    if (!$checked['ok']) {
        ws_api_task_refused($checked);
    }

    api_dry_run_stop('updated', 'workspace_task', array(
        'id'       => (int) $task['id'],
        'fields'   => array_merge(array_keys($data), ($blocked_by !== null) ? array('blocked_by') : array()),
        'added'    => $checked['added'],
        'warnings' => $warnings,
    ));

    $result = ws_task_update($viewer, $task, $data);

    if (!$result['ok']) {
        ws_api_task_refused($result);
    }

    if ($blocked_by !== null) {
        $linked = ws_task_links_set($viewer, ws_task($task['id']), $blocked_by);

        if (!$linked['ok']) {
            api_fail_validation($linked['error'], 'blocked_by');
        }
    }

    api_ok(ws_api_task_present($viewer, ws_task($task['id']), $warnings));
}

/**
 * A task write that was refused, as the API answers it: a task the owner may
 * not change is 403, anything else names its field.
 *
 * @param array $result ws_task_update() or ws_task_update_check()
 */
function ws_api_task_refused($result)
{
    if ($result['error'] === lang('You cannot change this task.')) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_fail_validation($result['error'], $result['field'] ?: null);
}

function ws_api_tasks_complete($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);

    $checked = ws_task_set_status_check($viewer, $task, 'done');

    if (!$checked['ok']) {
        api_fail(403, 'forbidden', $checked['error']);
    }

    api_dry_run_stop('completed', 'workspace_task', array('id' => (int) $task['id'], 'changes' => ((string) $task['status'] !== 'done')));

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

    // The checks ws_task_note_add() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_task_note_add_check($viewer, $task, (string) $params['text']);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    api_dry_run_stop('created', 'workspace_task_note', array('task_id' => (int) $task['id']));

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
    $rule = array(
        'frequency' => (string) ($params['frequency'] ?? 'none'),
        'interval'  => (int) ($params['interval'] ?? 1),
        'weekdays'  => array_map('intval', (array) ($params['weekdays'] ?? array())),
        'end_date'  => (string) ($params['end_date'] ?? ''),
    );

    // The checks ws_recurrence_save() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_recurrence_save_check($viewer, $task, $rule);

    if (!$checked['ok']) {
        ws_api_task_refused($checked);
    }

    $series = function_exists('ws_recurrence') ? ws_recurrence($task['recurrence_id'] ?? 0) : null;
    $active = $series && ((string) $series['status'] === 'active');

    api_dry_run_stop(($checked['rule']['frequency'] === 'none') ? 'stopped' : ($active ? 'updated' : 'created'), 'workspace_recurrence', array(
        'task_id'   => (int) $task['id'],
        'frequency' => (string) $checked['rule']['frequency'],
        'repeating' => $active,
    ));

    $result = ws_recurrence_save($viewer, $task, $rule);

    if (!$result['ok']) {
        ws_api_task_refused($result);
    }

    api_ok(ws_api_recurrence_present(ws_api_recurrence_or_404(ws_task($task['id']))));
}

function ws_api_task_recurrence_end($params)
{
    $viewer = ws_api_viewer();
    $task = ws_api_task_or_404($viewer, $params['id']);
    ws_api_recurrence_or_404($task);
    $mode = ((string) ($params['mode'] ?? 'complete') === 'stop') ? 'stop' : 'complete';

    $checked = ws_recurrence_end_check($viewer, $task, $mode);

    if (!$checked['ok']) {
        api_fail(403, 'forbidden', $checked['error']);
    }

    api_dry_run_stop('ended', 'workspace_recurrence', array(
        'task_id'   => (int) $task['id'],
        'mode'      => $mode,
        'completes' => ($mode === 'complete') && ws_task_is_open($task['status']),
    ));

    $result = ws_recurrence_end($viewer, $task, $mode);

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

/**
 * The parts of ws_record_refs() that sit in channels $allowed accepts: its
 * messages and customer channels by their channel, its tasks by theirs, and
 * a task in no channel kept. Only the three lists ws_api_refs_get() answers
 * come back.
 *
 * @param array    $refs    ws_record_refs()
 * @param callable $allowed channel id => bool
 * @return array messages, tasks, channels
 */
function ws_api_refs_within($refs, $allowed)
{
    $out = array('messages' => array(), 'tasks' => array(), 'channels' => array());

    foreach ($refs['messages'] as $message) {
        if ($allowed((int) $message['channel_id'])) {
            $out['messages'][] = $message;
        }
    }

    foreach ($refs['tasks'] as $task) {
        if (((int) $task['channel_id'] <= 0) || $allowed((int) $task['channel_id'])) {
            $out['tasks'][] = $task;
        }
    }

    foreach ($refs['channels'] as $channel) {
        if ($allowed((int) $channel['id'])) {
            $out['channels'][] = $channel;
        }
    }

    return $out;
}

function ws_api_refs_get($params)
{
    $viewer = ws_api_viewer();
    $refs = ws_record_refs($viewer, (string) $params['type'], (int) $params['id'], 100);

    // The application Claude works through reads only where Claude may be
    // asked, as it does everywhere else: ws_record_refs() counts every
    // channel the owner reads, private ones and guest rooms included.
    if (ws_api_is_claude()) {
        $refs = ws_api_refs_within($refs, function ($channel_id) {
            $channel = ws_channel($channel_id);

            return $channel && ws_claude_channel_allowed($channel);
        });
    }

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
        'user_id'     => 'integer',
        'name'        => 'string',
        'unscheduled' => 'integer',
        'days'        => array(array(
            'date'       => 'string',
            'workday'    => 'boolean',
            'leave'      => 'boolean',
            'holiday'    => 'boolean',
            'capacity'   => 'integer',
            'minutes'    => 'integer',
            'load'       => 'integer',
            'tasks'      => array('integer'),
            'task_items' => array(array(
                'id'         => 'integer',
                'minutes'    => 'integer',
                'due'        => 'boolean',
                'upcoming'   => 'boolean',
                'repeat'     => 'string',
                'moved_from' => 'string',
                'waits_for'  => 'string',
            )),
            'events'     => array(array(
                'id'          => 'integer',
                'kind'        => 'string',
                'title'       => 'string',
                'time'        => 'string',
                'minutes'     => 'integer',
                'meeting_url' => 'string',
            )),
        )),
    );
}

/**
 * One person's row of the planning board as the API answers it: the cells
 * ws_plan_grid() filled, with the task entries and plan items it placed on
 * each day, and the count of the person's tasks with neither date.
 *
 * @param array $row a ws_board() row
 * @return array
 */
function ws_api_plan_present($row)
{
    $days = array();

    foreach ($row['cells'] as $cell) {
        $task_ids = array();
        $task_items = array();

        foreach ($cell['tasks'] as $task) {
            $task_ids[] = (int) $task['id'];
            $task_items[] = array(
                'id'         => (int) $task['id'],
                'minutes'    => (int) $task['minutes'],
                'due'        => !empty($task['due']),
                'upcoming'   => !empty($task['upcoming']),
                'repeat'     => (string) ($task['repeat'] ?? ''),
                'moved_from' => (string) ($task['moved_from'] ?? ''),
                'waits_for'  => (string) ($task['waits_for'] ?? ''),
            );
        }

        $events = array();

        foreach ($cell['events'] ?? array() as $event) {
            $events[] = array(
                'id'          => (int) $event['id'],
                'kind'        => (string) $event['kind'],
                'title'       => (string) $event['title'],
                'time'        => (string) $event['time'],
                'minutes'     => (int) $event['minutes'],
                'meeting_url' => (string) ($event['meeting_url'] ?? ''),
            );
        }

        $days[] = array(
            'date'       => (string) $cell['date'],
            'workday'    => (bool) $cell['workday'],
            'leave'      => (bool) $cell['leave'],
            'holiday'    => (bool) $cell['holiday'],
            'capacity'   => (int) $cell['capacity'],
            'minutes'    => (int) $cell['minutes'],
            'load'       => (int) $cell['load'],
            'tasks'      => $task_ids,
            'task_items' => $task_items,
            'events'     => $events,
        );
    }

    return array(
        'user_id'     => (int) $row['person']['id'],
        'name'        => (string) $row['person']['name'],
        'unscheduled' => (int) ($row['unscheduled'] ?? 0),
        'days'        => $days,
    );
}

function ws_api_plan_get($params)
{
    $viewer = ws_api_viewer();
    $board = ws_board($viewer, (string) ($params['from'] ?? ''), (int) ($params['days'] ?? 7), (int) ($params['department_id'] ?? 0));
    $out = array();

    foreach ($board['rows'] as $row) {
        $out[] = ws_api_plan_present($row);
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
    } elseif (($row['sender_kind'] === 'mail') && function_exists('ws_mail_sender')) {
        // A mail: its sender as the mail names them, or the team's writer
        // of an answer (mail.php); mail tells its conversation.
        $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $row['sender_id'] . "'");
        $item = (is_array($item) && $item) ? $item : null;
        $from = ws_mail_sender($item);
        $sender = array('user_id' => ((int) $from['id'] > 0) ? (int) $from['id'] : null, 'name' => (string) $from['name']);
        $mail = $item ? array(
            'item_id'         => (int) $item['id'],
            'conversation_id' => ((int) $item['conversation_id'] > 0) ? (int) $item['conversation_id'] : null,
            'direction'       => (string) $item['direction'],
            'from_email'      => (string) $item['from_email'],
        ) : null;
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

    // Who proposed the change, as GET /workspace/changes lists it: Claude,
    // Pinegrap AI, an MCP server's agent or an application.
    if (isset($changes[$id])) {
        $change = array(
            'type'      => $changes[$id]['type'],
            'action'    => $changes[$id]['action'],
            'record_id' => $changes[$id]['record_id'],
            'agent'     => (string) ($changes[$id]['agent_key'] ?? 'claude'),
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
        'sender'        => 'WorkspaceMessageSender?',
        'marked_by'     => 'WorkspacePerson?',
        'locked'        => 'boolean',
        'claude_change' => 'WorkspaceDecisionChange?',
        'file'          => 'WorkspaceDecisionFile?',
        'decided_at'    => 'string',
        'created_at'    => 'string',
    );
}

// The parts of a decision that are null when it has none: the change it came
// from, and the file it carries.
function ws_api_decision_change_schema()
{
    return array('type' => 'string', 'action' => 'string', 'record_id' => 'integer?', 'agent' => 'string');
}

function ws_api_decision_file_schema()
{
    return array('name' => 'string', 'url' => 'string');
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

// What ws_api_person_ref() returns when it names somebody. A field that may
// name nobody is declared 'WorkspacePerson?'.
function ws_api_person_schema()
{
    return array('user_id' => 'integer', 'name' => 'string');
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

    // A scheduled action Pinegrap AI drafted (scheduled_draft.php), or a flow
    // an application proposed (flow_draft.php): its steps or boxes in words,
    // as the card shows them to the person who asked.
    if (($type === 'scheduled') || ($type === 'flow')) {
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
    // saved from it, and a proposed flow (flow_draft.php), named by its name.
    if (($type === 'mcp_call') || ($type === 'scheduled') || ($type === 'flow')) {
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
        'agent'               => ws_change_agent_key((string) ($row['agent'] ?? '')),
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
        'requested_by'        => 'WorkspacePerson?',
        'decided_by'          => 'WorkspacePerson?',
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

    // mcp_call: the calls of MCP server tools that wait the same way; flow:
    // the flows applications proposed.
    if (($type !== '') && !isset($types[$type]) && !in_array($type, array('mcp_call', 'flow'), true)) {
        api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('type', implode(', ', array_merge(array_keys($types), array('mcp_call', 'flow')))))), 'type');
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
 * @param array      $result ws_change_apply() or ws_change_dismiss()
 * @param array|null $change the ws_ai_changes row, for the refusals that
 *                           name the application that proposed it
 */
function ws_api_change_refused($result, $change = null)
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
        lang('Your account can read the ERP but not change anything in it.'),
        lang('You cannot post in that channel.'),
        lang('That task could not be found.'),
    );

    // Proposed by an application (proposals.php): the refusal names it.
    $proposer = function_exists('ws_change_proposer') ? ws_change_proposer($change) : '';

    if ($proposer !== '') {
        $forbidden[] = lang(array('string' => 'Only the person {var:1} proposed this change to can apply it.', 'vars' => $proposer));
        $forbidden[] = lang(array('string' => 'Only the person {var:1} proposed this change to can set it aside.', 'vars' => $proposer));
    }

    if (in_array($error, $forbidden, true)) {
        api_fail(403, 'forbidden', $error);
    }

    api_fail_validation($error);
}

/**
 * A change an application proposed (proposals.php) is applied or set aside
 * by the person alone: on its card, or from their own team devices session.
 * An OAuth connection is a device row too and passes ws_api_person_only(),
 * so it could otherwise apply the card it wrote itself and write the record
 * with the Workspace's scopes and no write scope of its kind; another
 * connection is no more the person than the one that proposed. A change an
 * assistant proposed keeps ws_api_person_only() alone.
 *
 * @param array $change ws_ai_changes row
 */
function ws_api_proposal_decision_gate($change)
{
    if (function_exists('ws_change_proposal_agent') && (ws_change_proposal_agent($change) !== '')) {
        ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can apply or set aside a change an application proposed; an application key or an OAuth connection cannot, whichever application proposed it.'));
    }
}

function ws_api_changes_apply($params)
{
    $viewer = ws_api_viewer();
    $change = ws_api_change_or_404($viewer, $params['id']);

    // Applying is the person's own decision, as on the card.
    ws_api_person_only();
    ws_api_proposal_decision_gate($change);

    // The checks ws_change_apply() makes, made first so that a dry run meets
    // the same refusals: a change to one record is laid against the record
    // as it is now, a call of an MCP server's tool and a change to many
    // records are checked as they run.
    $checked = ws_change_apply_check($viewer, (int) $change['id']);

    if (!$checked['ok']) {
        ws_api_change_refused($checked, $change);
    }

    $action = (string) ($checked['change']['action'] ?? 'update');

    if (!$checked['mcp'] && ($action !== 'bulk')) {
        $laid = ws_change_apply_record_check($viewer, $checked['change']);

        if (!$laid['ok']) {
            ws_api_change_refused($laid, $change);
        }
    }

    api_dry_run_stop('applied', 'workspace_change', array(
        'id'          => (int) $change['id'],
        'record_type' => (string) $checked['change']['record_type'],
        'action'      => $action,
    ));

    $result = ws_change_apply($viewer, (int) $change['id']);

    if (!$result['ok']) {
        ws_api_change_refused($result, $change);
    }

    ws_api_change_answer($viewer, $change['id']);
}

function ws_api_changes_dismiss($params)
{
    $viewer = ws_api_viewer();
    $change = ws_api_change_or_404($viewer, $params['id']);

    ws_api_person_only();
    ws_api_proposal_decision_gate($change);

    $checked = ws_change_dismiss_check($viewer, (int) $change['id']);

    if (!$checked['ok']) {
        ws_api_change_refused($checked, $change);
    }

    api_dry_run_stop('dismissed', 'workspace_change', array('id' => (int) $change['id'], 'record_type' => (string) $checked['change']['record_type']));

    $result = ws_change_dismiss($viewer, (int) $change['id']);

    if (!$result['ok']) {
        ws_api_change_refused($result, $change);
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
        'prompt_version'   => ((string) ($row['prompt_version'] ?? '') !== '') ? (string) $row['prompt_version'] : null,
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
        'requested_by'     => 'WorkspacePerson?',
        'reply_message_id' => 'integer?',
        'error'            => 'string',
        'created_at'       => 'string?',
        'sent_at'          => 'string?',
        'claimed_at'       => 'string?',
        'answered_at'      => 'string?',
        'prompt_version'   => 'string?',
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

    foreach (array('agent', 'note_id', 'page_id', 'node_id', 'task_id', 'note_text', 'note_reply', 'prompt_version') as $column) {
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

    // An application's proposal (proposals.php) asked no assistant: its
    // changes are listed by GET /workspace/changes.
    if (function_exists('ws_proposals_ready') && ws_proposals_ready()) {
        $where .= " AND " . ws_proposal_where('', false);
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

/**
 * A task handed to an assistant that was refused, as the API answers it.
 *
 * @param array $task
 * @param array $result ws_task_delegate() or ws_task_delegate_check()
 */
function ws_api_delegate_refused($task, $result)
{
    if ($result['error'] === lang('You may not hand this task to an assistant.')) {
        api_fail(403, 'forbidden', $result['error']);
    }

    if (ws_task_delegation_open_run($task['id'])) {
        api_fail(409, 'assistant_busy', $result['error']);
    }

    api_fail_validation($result['error']);
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

    // The checks ws_task_delegate() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_task_delegate_check($viewer, $task, $key);

    if (!$checked['ok']) {
        ws_api_delegate_refused($task, $checked);
    }

    api_dry_run_stop('delegated', 'workspace_assistant_request', array(
        'task_id' => (int) $task['id'],
        'agent'   => $key,
        'hands'   => ((string) ($task['delegate'] ?? '') !== $key),
    ));

    $result = ws_task_delegate($viewer, $task, $key, (string) ($params['instruction'] ?? ''));

    if (!$result['ok']) {
        ws_api_delegate_refused($task, $result);
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

    $checked = ws_task_delegation_stop_check($viewer, $task);

    if (!$checked['ok']) {
        api_fail(403, 'forbidden', $checked['error']);
    }

    api_dry_run_stop('stopped', 'workspace_assistant_request', array(
        'task_id'    => (int) $task['id'],
        'request_id' => $checked['run'] ? (int) $checked['run']['id'] : null,
        'take_back'  => !empty($params['take_back']) && ((string) ($task['delegate'] ?? '') !== ''),
    ));

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

// What ws_api_note_source_present() returns for a note taken from a message;
// a note written on its own has none.
function ws_api_note_source_schema()
{
    return array('channel_id' => 'integer?', 'channel_name' => 'string', 'author' => 'string');
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
        'text'        => $full ? ws_plain_text($viewer, (string) $note['body'], null, true) : null,
        'body'        => $full ? (string) $note['body'] : null,
        'created_at'  => api_time($note['created_at']),
        'updated_at'  => api_time($note['updated_at']),
        'revision'    => isset($note['revision']) ? (int) $note['revision'] : null,
    );
}

// What the delete of a note answers.
function ws_api_note_removal_schema()
{
    return array(
        'id'      => 'integer',
        'deleted' => 'boolean',
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
        'owner'       => 'WorkspacePerson?',
        'updated_by'  => 'WorkspacePerson?',
        'source'      => 'WorkspaceNoteSource?',
        'shared_with' => array(array('user_id' => 'integer', 'name' => 'string', 'can_edit' => 'boolean')),
        'shared_in'   => array(array('channel_id' => 'integer', 'name' => 'string')),
        'channel_id'  => 'integer?',
        'text'        => 'string?',
        'body'        => 'string?',
        'created_at'  => 'string?',
        'updated_at'  => 'string?',
        'revision'    => 'integer?',
    );
}

function ws_api_notes_list($params)
{
    $viewer = ws_api_viewer();
    $after = null;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);
        $after = ($cursor === null) ? null : ws_notes_position_unpack($cursor['v'], $cursor['i']);

        if ($after === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }
    }

    $limit = (int) ($params['limit'] ?? 100);
    $page = ws_notes_page($viewer, array(
        'scope'         => (string) ($params['scope'] ?? 'all'),
        'search'        => (string) ($params['search'] ?? ''),
        'updated_since' => isset($params['updated_since']) ? (int) $params['updated_since'] : 0,
        'after'         => $after,
        'limit'         => $limit,
    ));

    $out = array();

    foreach ($page['rows'] as $note) {
        $out[] = ws_api_note_present($viewer, $note);
    }

    $next = ($page['next'] !== null)
        ? api_cursor_encode(ws_notes_position_pack($page['next']['group'], $page['next']['updated_at']), $page['next']['id'])
        : null;

    // The notes deleted since, on the first page of a changes listing: an
    // app drops its copies of them.
    $deleted = array();

    if (($after === null) && isset($params['updated_since']) && ((int) $params['updated_since'] > 0)) {
        foreach (ws_note_deletions_since($viewer, (int) $params['updated_since']) as $row) {
            $deleted[] = array('id' => $row['id'], 'deleted_at' => api_time($row['deleted_at']));
        }
    }

    api_ok_list($out, $limit, $next, $page['total'], array('deleted' => $deleted));
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

    // The checks ws_note_save() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_note_save_check($viewer, $data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error']);
    }

    api_dry_run_stop('created', 'workspace_note', array('title' => (string) $checked['sets']['title'], 'pinned' => ((int) $checked['pin'] === 1)));

    $result = ws_note_save($viewer, $data);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    api_ok(ws_api_note_present($viewer, ws_note_present($viewer, ws_note_for($viewer, $result['note_id'], 'view'))), 201);
}

/**
 * A change to a note that was refused, as the API answers it.
 *
 * @param array  $result ws_note_save() or ws_note_save_check()
 * @param string $field  the field a conflict names: revision when it was sent
 */
function ws_api_note_refused($result, $field = 'base')
{
    if ($result['conflict']) {
        api_fail(409, 'changed_meanwhile', $result['error'], $field);
    }

    api_fail_validation($result['error'], 'body');
}

function ws_api_notes_delete($params)
{
    $viewer = ws_api_viewer();

    if (!ws_notes_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $note = ws_api_note_or_404($viewer, $params['id']);

    // The check the screen's delete makes, made first so that a dry run
    // meets the same refusal.
    $checked = ws_note_delete_check($viewer, $note);

    if (!$checked['ok']) {
        api_fail(403, 'forbidden', $checked['error']);
    }

    api_dry_run_stop('deleted', 'workspace_note', array('id' => (int) $note['id']));

    $result = ws_note_delete($viewer, $note);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(array('id' => (int) $note['id'], 'deleted' => true));
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

    if (isset($params['revision'])) {
        $data['revision'] = (int) $params['revision'];
    }

    // The checks ws_note_save() makes, made first so that a dry run meets
    // the same refusals, the change made meanwhile among them.
    $checked = ws_note_save_check($viewer, $data);

    if (!$checked['ok']) {
        ws_api_note_refused($checked, isset($data['revision']) ? 'revision' : 'base');
    }

    api_dry_run_stop('updated', 'workspace_note', array(
        'id'     => (int) $note['id'],
        'fields' => array_merge(array_keys($checked['sets']), ($checked['pin'] !== null) ? array('pinned') : array()),
    ));

    $result = ws_note_save($viewer, $data);

    if (!$result['ok']) {
        ws_api_note_refused($result, isset($data['revision']) ? 'revision' : 'base');
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
 * A row of the owner's time across tasks: the per-task row and its task.
 *
 * @param array $row  ws_task_time row
 * @param array $task ws_task_time_task_ref()
 * @return array
 */
function ws_api_task_time_range_present($row, $task)
{
    return ws_api_task_time_present($row) + array(
        'task' => array(
            'id'      => (int) $task['id'],
            'title'   => (string) $task['title'],
            'visible' => (bool) $task['visible'],
        ),
    );
}

// What ws_api_task_time_range_present() returns.
function ws_api_task_time_range_schema()
{
    return ws_api_task_time_schema() + array('task' => 'WorkspaceTimeTask');
}

// The task of a WorkspaceTimeEntry.
function ws_api_task_time_task_schema()
{
    return array(
        'id'      => 'integer',
        'title'   => 'string',
        'visible' => 'boolean',
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

function ws_api_time_list($params)
{
    $viewer = ws_api_viewer();

    if (!ws_task_time_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $range = ws_task_time_range_check((string) ($params['from'] ?? ''), (string) ($params['to'] ?? ''));

    if (!$range['ok']) {
        api_fail_validation($range['error'], $range['field']);
    }

    $after = null;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);
        $day = ($cursor === null) ? null : ws_task_time_position_unpack($cursor['v']);

        if ($day === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }

        $after = array('day' => $day, 'id' => (int) $cursor['i']);
    }

    $limit = (int) ($params['limit'] ?? 250);
    $page = ws_task_time_range($viewer, $range['from'], $range['to'], $after, $limit);
    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = ws_api_task_time_range_present($row, $row['task']);
    }

    $next = ($page['next'] !== null) ? api_cursor_encode(ws_task_time_position_pack($page['next']['day']), $page['next']['id']) : null;

    api_ok_list($out, $limit, $next, null, array('total_minutes' => (int) $page['total_minutes']));
}

function ws_api_task_time_update($params)
{
    list($viewer, $task) = ws_api_task_time_task($params);
    $entry = ws_task_time_entry((int) $params['entry_id']);

    if (!$entry || ((int) $entry['task_id'] !== (int) $task['id'])) {
        api_fail_not_found(lang('Time entry'));
    }

    // Only the fields sent change; a cleared note arrives as ''.
    $changes = array();

    foreach (array('minutes', 'worked_on', 'note', 'billable') as $field) {
        if (array_key_exists($field, $params)) {
            $changes[$field] = $params[$field];
        }
    }

    $checked = ws_task_time_update_check($viewer, $entry, $changes);

    if (!$checked['ok']) {
        if ((int) $checked['status'] === 403) {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error'], $checked['field'] ?: null);
    }

    api_dry_run_stop('updated', 'workspace_task_time', array('id' => (int) $entry['id'], 'changes' => array_keys($changes)));

    $result = ws_task_time_update($viewer, $entry, $changes);

    if (!$result['ok']) {
        api_fail_validation($result['error'], $result['field'] ?: null);
    }

    ws_api_log(lang(array('string' => 'time was changed on workspace task ({var:1})', 'vars' => ws_task_number($task['id']))));

    api_ok(ws_api_task_time_present(ws_task_time_entry($entry['id'])));
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
