<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - channels: where a conversation about a customer, a project or a
 * department lives, together with the decisions taken in it and the tasks it
 * produced.
 *
 * Two kinds. A public channel is open to the whole team: anyone can read it
 * without joining, and joining only means hearing about it. A private channel
 * exists for the people invited to it. Its owner can open it to the team;
 * the other way round is not offered, because what the team has already read
 * cannot be made private again by a switch.
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
 * The channels this person can see, with their unread counts.
 *
 * @param array $viewer
 * @param bool  $archived the archived ones instead
 * @return array[]
 */
function ws_channels_for($viewer, $archived = false)
{
    if (!$viewer['member']) {
        return array();
    }

    $me = (int) $viewer['id'];

    // Pinned first, then each person's own order, then by name.
    $ordered = ws_channel_order_ready();

    $rows = (array) db_items("SELECT c.*, m.user_id AS my_member, m.last_read_id AS my_last_read, m.notify AS my_notify, m.role AS my_role"
        . ($ordered ? ", m.sort AS my_sort, m.pinned AS my_pinned" : '') . "
        FROM ws_channels c
        LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . $me . "'
        WHERE c.archived_at " . ($archived ? '> 0' : '= 0') . "
        AND (c.kind = 'public' OR m.user_id IS NOT NULL) AND c.kind <> 'thread'
        ORDER BY " . ($ordered ? "(m.pinned = 1) DESC, (COALESCE(m.sort, 0) = 0), m.sort, " : '') . "c.name");

    // Unread messages and unread mentions for every joined channel, two
    // grouped reads however many channels there are.
    $unread = array();

    foreach ((array) db_items("SELECT msg.channel_id, COUNT(*) AS unread
        FROM ws_messages msg
        INNER JOIN ws_channel_members m ON m.channel_id = msg.channel_id AND m.user_id = '" . $me . "'
        WHERE msg.id > m.last_read_id AND NOT (msg.sender_kind = 'user' AND msg.sender_id = '" . $me . "') AND msg.deleted_at = 0 AND msg.sender_kind <> 'system'
        GROUP BY msg.channel_id") as $row) {
        $unread[(int) $row['channel_id']] = (int) $row['unread'];
    }

    $mentions = array();

    foreach ((array) db_items("SELECT channel_id, COUNT(*) AS mentions
        FROM ws_inbox
        WHERE user_id = '" . $me . "' AND read_at = 0 AND kind = 'mention'
        GROUP BY channel_id") as $row) {
        $mentions[(int) $row['channel_id']] = (int) $row['mentions'];
    }

    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_channel_brief($row, $unread[(int) $row['id']] ?? 0, $mentions[(int) $row['id']] ?? 0);
    }

    return $out;
}

/**
 * The short form of a channel for the sidebar.
 *
 * @param array $row      the channel, optionally with my_member / my_notify / my_role
 * @param int   $unread
 * @param int   $mentions
 * @return array
 */
function ws_channel_brief($row, $unread = 0, $mentions = 0)
{
    return array(
        'id'         => (int) $row['id'],
        'name'       => (string) $row['name'],
        'kind'       => (string) $row['kind'],
        'topic'      => (string) $row['topic'],
        'joined'     => !empty($row['my_member']),
        'notify'     => (string) ($row['my_notify'] ?? 'all'),
        'owner'      => ((string) ($row['my_role'] ?? '') === 'owner'),
        'contact_id' => (int) $row['contact_id'],
        'customer_type' => (string) ($row['customer_type'] ?? ''),
        'customer_id' => (int) ($row['customer_id'] ?? 0),
        'icon'       => ws_channel_icon($row),
        'department_id' => (int) $row['department_id'],
        'archived'   => ((int) $row['archived_at'] > 0),
        'unread'     => (int) $unread,
        'mentions'   => (int) $mentions,
        'pinned'     => !empty($row['my_pinned']),
        'sort'       => (int) ($row['my_sort'] ?? 0),
        'last_message_at' => (int) $row['last_message_at'],
        'color'      => (int) ($row['color'] ?? 0),
        'hex'        => ws_palette_hex($row['color'] ?? 0),
        'group_id'   => (int) ($row['group_id'] ?? 0),
    );
}

/**
 * Everything the channel screen needs about one channel.
 *
 * @param array $viewer
 * @param array $channel
 * @return array
 */
function ws_channel_detail($viewer, $channel)
{
    $membership = ws_channel_membership($channel['id'], $viewer['id']);
    $brief = ws_channel_brief(array_merge($channel, array(
        'my_member' => $membership ? 1 : 0,
        'my_notify' => $membership['notify'] ?? 'all',
        'my_role'   => $membership['role'] ?? '',
    )));

    $member_ids = array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members
        WHERE channel_id = '" . (int) $channel['id'] . "' ORDER BY role = 'owner' DESC, joined_at"));

    $brief['members'] = array_values(ws_people($member_ids));
    $brief['owner_id'] = (int) $channel['owner_user_id'];
    $brief['owner_name'] = ws_person_name($channel['owner_user_id']);
    $brief['can_post'] = ws_can_post_channel($viewer, $channel);
    $brief['can_manage'] = ws_can_manage_channel($viewer, $channel);
    $brief['can_change_kind'] = ws_can_change_channel_kind($viewer, $channel);
    $brief['can_clear'] = ws_can_clear_channel($viewer, $channel);
    $brief['can_pin'] = ws_can_pin($viewer, $channel);
    $brief['pin'] = ws_channel_pin_present($viewer, $channel, $membership);
    $brief['can_group'] = ws_can_move_channel_group($viewer, $channel);
    $brief['eras'] = ws_eras_ready() ? (int) db_value("SELECT COUNT(*) FROM ws_channel_eras WHERE channel_id = '" . (int) $channel['id'] . "'") : 0;
    $brief['claude'] = function_exists('ws_claude_channel_state') ? ws_claude_channel_state($channel) : null;
    $brief['ai'] = function_exists('ws_ai_channel_state') ? ws_ai_channel_state($channel) : null;

    // A room with a guest in it (guests.php): who, where their link stands,
    // what the reader may do. The assistants are never asked there.
    $brief['guest'] = function_exists('ws_guest_channel_state') ? ws_guest_channel_state($viewer, $channel) : null;

    // A channel of the team shared with guests: who reads along, and the
    // reader's way to share it (guests.php).
    $brief['shares'] = function_exists('ws_channel_shares_state') ? ws_channel_shares_state($viewer, $channel) : null;
    $brief['can_share'] = function_exists('ws_channel_can_share') && ws_channel_can_share($viewer, $channel);

    if (($brief['guest'] !== null) || (function_exists('ws_channel_share_open') && ws_channel_share_open($channel))) {
        $brief['claude'] = null;
        $brief['ai'] = null;
    }
    $brief['audit'] = !$membership && ($channel['kind'] === 'private');
    $brief['summary'] = (string) $channel['summary'];
    $summary_tokens = ws_tokens($channel['summary']);
    $summary_refs = ws_refs_resolve($viewer, $summary_tokens);
    $brief['summary_html'] = ws_render_body((string) $channel['summary'], $summary_refs);

    // The names of the tags in the summary, for the formatted box to show
    // them as chips again when it is edited.
    $brief['summary_labels'] = array();

    foreach ($summary_tokens as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($summary_refs[$key])) {
            $brief['summary_labels']['<' . $token['sigil'] . $key . '>'] = $summary_refs[$key]['label'];
        }
    }

    $brief['summary_updated'] = ((int) $channel['summary_updated_at'] > 0)
        ? lang(array('string' => 'Updated by {var:1}, {var:2}', 'vars' => array(ws_person_name($channel['summary_updated_by']), ws_time_label($channel['summary_updated_at']))))
        : '';
    $brief['contact'] = null;

    if ((int) $channel['contact_id'] > 0) {
        $refs = ws_refs_resolve($viewer, array(array('sigil' => '#', 'type' => 'contact', 'id' => (int) $channel['contact_id'])));
        $contact = $refs['contact:' . (int) $channel['contact_id']] ?? null;

        if ($contact) {
            $brief['contact'] = array('id' => $contact['id'], 'label' => $contact['label'], 'url' => $contact['url'], 'html' => ws_chip_html($contact));
        }
    }

    // The customer as it was chosen - a contact, a user or a current account -
    // with the records tied to it.
    $brief['customer'] = ws_channel_customer_present($viewer, $channel);

    $brief['department'] = null;

    if ((int) $channel['department_id'] > 0) {
        $department = ws_department($channel['department_id']);

        if ($department) {
            $brief['department'] = array('id' => (int) $department['id'], 'name' => $department['name'], 'color' => $department['color']);
        }
    }

    $brief['tab_counts'] = ws_channel_tab_counts($channel);

    // A discussion (threads.php): what it is about, and no pin of its own.
    if (ws_channel_is_thread($channel)) {
        $thread = ws_thread($channel['id']);
        $brief['thread'] = $thread ? ws_thread_present($viewer, $thread, $channel) : null;
        $brief['can_pin'] = false;
        $brief['can_share'] = false;
    }

    // The reader's own messages waiting to be posted here
    // (scheduled_messages.php).
    $brief['scheduled_mine'] = (function_exists('ws_can_schedule_messages') && ws_can_schedule_messages($viewer))
        ? (int) db_value("SELECT COUNT(*) FROM ws_scheduled_actions
            WHERE channel_id = '" . (int) $channel['id'] . "' AND kind = 'message' AND created_by = '" . (int) $viewer['id'] . "' AND status IN ('active', 'failed')")
        : 0;

    return $brief;
}

/**
 * What the channel's tabs hold, for the marks beside their names: the
 * decisions and notes, the open tasks, and whether the summary is written.
 * Two indexed counts (ws_messages.idx_kind, ws_tasks.idx_channel).
 *
 * @param array $channel
 * @return array decisions, tasks, summary
 */
function ws_channel_tab_counts($channel)
{
    $channel_id = (int) $channel['id'];

    return array(
        'decisions' => (int) db_value("SELECT COUNT(*) FROM ws_messages
            WHERE channel_id = '" . $channel_id . "' AND kind IN ('decision', 'note') AND deleted_at = 0"),
        'tasks'     => (int) db_value("SELECT COUNT(*) FROM ws_tasks
            WHERE channel_id = '" . $channel_id . "' AND status NOT IN ('done', 'cancelled')"),
        'summary'   => (trim((string) ($channel['summary'] ?? '')) !== ''),
    );
}

/**
 * A channel name as it is kept: trimmed, the leading # dropped, spaces
 * collapsed, at most 80 characters.
 *
 * @param string $name
 * @return string
 */
function ws_channel_clean_name($name)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $name = ltrim($name, '# ');

    return mb_substr($name, 0, 80);
}

/**
 * Is the name taken by another open channel? Two channels called "acme" are
 * two places to look for the same conversation.
 *
 * @param string $name
 * @param int    $except_id
 * @return bool
 */
function ws_channel_name_taken($name, $except_id = 0)
{
    // A discussion is named after what it talks over, not as a place to look.
    return (int) db_value("SELECT COUNT(*) FROM ws_channels
        WHERE archived_at = 0 AND kind <> 'thread' AND LOWER(name) = LOWER('" . e($name) . "') AND id <> '" . (int) $except_id . "'") > 0;
}

/**
 * Adds people to a channel; the ones who were not in it hear about it.
 *
 * @param array $viewer
 * @param array $channel
 * @param int[] $user_ids
 * @param bool  $announce write a line in the channel
 * @return int[] the people who were added
 */
function ws_channel_add_members($viewer, $channel, $user_ids, $announce = true)
{
    $added = array();
    $now = time();

    foreach ((array) $user_ids as $user_id) {
        $user_id = (int) $user_id;

        if (($user_id <= 0) || !ws_is_team_member($user_id) || ws_channel_membership($channel['id'], $user_id)) {
            continue;
        }

        // A room with a guest in it has staff in it and nobody else.
        if (((string) $channel['kind'] === 'guest') && ((int) (ws_rights_for_id($user_id)['role'] ?? 3) > 2)) {
            continue;
        }

        // A discussion takes in only people who read its channel.
        if (((string) $channel['kind'] === 'thread') && !ws_can_read_channel(ws_rights_for_id($user_id), ws_thread_parent_channel($channel))) {
            continue;
        }

        db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, last_read_id, joined_at)
            VALUES ('" . (int) $channel['id'] . "', '" . $user_id . "', 'member', '" . (int) $channel['last_message_id'] . "', '" . $now . "')");

        $added[] = $user_id;

        if ($user_id !== (int) $viewer['id']) {
            ws_notify($user_id, 'invited', array('channel_id' => $channel['id'], 'actor_id' => $viewer['id']));
        }
    }

    ws_channel_membership_forget();

    if (!empty($added)) {
        ws_channel_folder_access($channel, $added, true);
    }

    if ($announce && !empty($added)) {
        $mentions = array();

        foreach ($added as $user_id) {
            $mentions[] = '<@user:' . $user_id . '>';
        }

        ws_message_system($channel['id'], lang(array(
            'string' => '{var:1} added {var:2}',
            'vars'   => array('<@user:' . (int) $viewer['id'] . '>', implode(', ', $mentions)),
        )));

        // The scheduled actions that greet newcomers (scheduled.php). Not for
        // the people a channel is made with.
        if (function_exists('ws_scheduled_joined')) {
            ws_scheduled_joined(ws_channel($channel['id']), $added);
        }
    }

    return $added;
}

/**
 * Creates a channel.
 *
 * @param array $viewer
 * @param array $data name, kind, topic, contact_id, department_id, members (ids)
 * @return array ok, error, field, channel_id
 */
function ws_channel_create($viewer, $data)
{
    if (!$viewer['member']) {
        return array('ok' => false, 'error' => lang('Access denied.'), 'field' => '', 'channel_id' => 0);
    }

    $name = ws_channel_clean_name($data['name'] ?? '');

    if ($name === '') {
        return array('ok' => false, 'error' => lang('A channel needs a name.'), 'field' => 'name', 'channel_id' => 0);
    }

    if (ws_channel_name_taken($name)) {
        return array('ok' => false, 'error' => lang('There is already a channel with this name.'), 'field' => 'name', 'channel_id' => 0);
    }

    $kind = (($data['kind'] ?? 'public') === 'private') ? 'private' : 'public';
    $customer = ws_customer_input($viewer, $data);
    $department_id = max(0, (int) ($data['department_id'] ?? 0));

    if (is_array($customer) && !$customer['ok']) {
        return array('ok' => false, 'error' => $customer['error'], 'field' => $customer['field'], 'channel_id' => 0);
    }

    $contact_id = is_array($customer) ? (int) $customer['contact_id'] : 0;

    if (($department_id > 0) && !ws_department($department_id)) {
        $department_id = 0;
    }

    $now = time();

    db("INSERT INTO ws_channels (name, kind, topic, owner_user_id, contact_id, department_id, created_by, created_at, last_message_at)
        VALUES (
            '" . e($name) . "',
            '" . $kind . "',
            '" . e(mb_substr(trim((string) ($data['topic'] ?? '')), 0, 255)) . "',
            '" . (int) $viewer['id'] . "',
            '" . $contact_id . "',
            '" . $department_id . "',
            '" . (int) $viewer['id'] . "',
            '" . $now . "',
            '" . $now . "')");

    $channel_id = (int) mysqli_insert_id(db::$con);

    if ($channel_id <= 0) {
        return array('ok' => false, 'error' => lang('The channel could not be saved.'), 'field' => '', 'channel_id' => 0);
    }

    db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, joined_at)
        VALUES ('" . $channel_id . "', '" . (int) $viewer['id'] . "', 'owner', '" . $now . "')");

    ws_channel_membership_forget();

    if (is_array($customer) && ($customer['type'] !== '') && ws_customer_ready()) {
        db("UPDATE ws_channels SET " . implode(', ', ws_customer_set($customer)) . " WHERE id = '" . $channel_id . "'");
    }

    $channel = ws_channel($channel_id);

    ws_message_system($channel_id, lang(array(
        'string' => '{var:1} created this channel',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>'),
    )));

    ws_channel_add_members($viewer, ws_channel($channel_id), (array) ($data['members'] ?? array()), false);

    if (ws_channel_colors_ready() && (ws_palette_place($data['color'] ?? 0) > 0)) {
        db("UPDATE ws_channels SET color = '" . ws_palette_place($data['color']) . "' WHERE id = '" . $channel_id . "'");
    }

    // Made inside a group (staff): the access given on it lets its people in.
    $group_id = (int) ($data['group_id'] ?? 0);

    if (($group_id > 0) && ws_can_manage_groups($viewer) && ws_group($group_id)) {
        db("UPDATE ws_channels SET group_id = '" . $group_id . "' WHERE id = '" . $channel_id . "'");
        ws_group_sync_channel($viewer, ws_channel($channel_id), $group_id);
    }

    log_activity(lang(array('string' => 'workspace channel ({var:1}) was created', 'vars' => $name)), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'field' => '', 'channel_id' => $channel_id);
}

/**
 * Renames a channel or changes its topic, customer or department.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data
 * @return array ok, error, field
 */
function ws_channel_update($viewer, $channel, $data)
{
    if (!ws_can_manage_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only the owner of the channel can change it.'), 'field' => '');
    }

    $set = array();

    if (array_key_exists('name', $data)) {
        $name = ws_channel_clean_name($data['name']);

        if ($name === '') {
            return array('ok' => false, 'error' => lang('A channel needs a name.'), 'field' => 'name');
        }

        if (ws_channel_name_taken($name, $channel['id'])) {
            return array('ok' => false, 'error' => lang('There is already a channel with this name.'), 'field' => 'name');
        }

        $set[] = "name = '" . e($name) . "'";
    }

    if (array_key_exists('topic', $data)) {
        $set[] = "topic = '" . e(mb_substr(trim((string) $data['topic']), 0, 255)) . "'";
    }

    $customer = ws_customer_input($viewer, $data, $channel);

    if (is_array($customer)) {
        if (!$customer['ok']) {
            return array('ok' => false, 'error' => $customer['error'], 'field' => $customer['field']);
        }

        $set = array_merge($set, ws_customer_set($customer));
    }

    if (array_key_exists('department_id', $data)) {
        $department_id = max(0, (int) $data['department_id']);
        $set[] = "department_id = '" . ((($department_id > 0) && ws_department($department_id)) ? $department_id : 0) . "'";
    }

    if (array_key_exists('color', $data) && ws_channel_colors_ready()) {
        $set[] = "color = '" . ws_palette_place($data['color']) . "'";
    }

    if (!empty($set)) {
        db("UPDATE ws_channels SET " . implode(', ', $set) . " WHERE id = '" . (int) $channel['id'] . "'");
    }

    // The channel's folder in the file manager keeps its name.
    if (isset($name) && ($name !== (string) $channel['name'])) {
        ws_channel_folder_rename($channel, $name);
    }

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * Opens a private channel to the whole team.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ok, error
 */
function ws_channel_make_public($viewer, $channel)
{
    if ($channel['kind'] !== 'private') {
        return array('ok' => true, 'error' => '');
    }

    if (!ws_can_change_channel_kind($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only staff who are in the channel can make it public or private.'));
    }

    db("UPDATE ws_channels SET kind = 'public' WHERE id = '" . (int) $channel['id'] . "'");

    // The notes shared here follow: the whole team reads them now.
    ws_channel_folder_access($channel, array(), true);

    ws_message_system($channel['id'], lang(array(
        'string' => '{var:1} opened this channel to the whole team. Everything written in it can now be read by every team member.',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>'),
    )));

    log_activity(lang(array('string' => 'workspace channel ({var:1}) was opened to the team', 'vars' => $channel['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '');
}

/**
 * May this person make the channel public or private? Staff only (Erdal,
 * 2026-09-26), and only from inside it: a private channel is changed by a
 * member of it, and the one who closes a public channel stays in it.
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_can_change_channel_kind($viewer, $channel)
{
    return is_array($channel) && $viewer['member'] && ($viewer['role'] < 3)
        && ((int) $channel['archived_at'] === 0)
        && in_array((string) $channel['kind'], array('public', 'private'), true)
        && (bool) ws_channel_membership($channel['id'], $viewer['id']);
}

/**
 * Closes a public channel: from now on only its members read it. Who is in
 * it stays in it; the others stop seeing it, what was written in it and the
 * notes shared only there. Files of the channel's folder were given to its
 * members all along, so the folder needs nothing.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ok, error
 */
function ws_channel_make_private($viewer, $channel)
{
    if ($channel['kind'] !== 'public') {
        return array('ok' => true, 'error' => '');
    }

    if (!ws_can_change_channel_kind($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only staff who are in the channel can make it public or private.'));
    }

    db("UPDATE ws_channels SET kind = 'private' WHERE id = '" . (int) $channel['id'] . "'");

    ws_channel_folder_access($channel, array(), true);

    $members = (int) db_value("SELECT COUNT(*) FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "'");

    ws_message_system($channel['id'], lang(array(
        'string' => '{var:1} made this channel private. From now on only its {var:2} members read it; the rest of the team no longer sees it.',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>', $members),
    )));

    log_activity(lang(array('string' => 'workspace channel ({var:1}) was made private', 'vars' => $channel['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '');
}

/**
 * Archives a channel, or brings it back.
 *
 * @param array $viewer
 * @param array $channel
 * @param bool  $archive
 * @return array ok, error
 */
function ws_channel_archive($viewer, $channel, $archive = true)
{
    if (!ws_can_manage_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only the owner of the channel can change it.'));
    }

    if (!$archive && ws_channel_name_taken($channel['name'], $channel['id'])) {
        return array('ok' => false, 'error' => lang('There is already a channel with this name.'));
    }

    db("UPDATE ws_channels SET archived_at = '" . ($archive ? time() : 0) . "' WHERE id = '" . (int) $channel['id'] . "'");

    $actor = '<@user:' . (int) $viewer['id'] . '>';

    ws_message_system($channel['id'], $archive
        ? lang(array('string' => '{var:1} archived this channel', 'vars' => $actor))
        : lang(array('string' => '{var:1} brought this channel back', 'vars' => $actor)));

    return array('ok' => true, 'error' => '');
}

/**
 * Joins a public channel.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ok, error
 */
function ws_channel_join($viewer, $channel)
{
    if (($channel['kind'] !== 'public') || !$viewer['member']) {
        return array('ok' => false, 'error' => lang('Access denied.'));
    }

    if (!ws_channel_membership($channel['id'], $viewer['id'])) {
        db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, last_read_id, joined_at)
            VALUES ('" . (int) $channel['id'] . "', '" . (int) $viewer['id'] . "', 'member', '" . (int) $channel['last_message_id'] . "', '" . time() . "')");

        ws_channel_membership_forget();
        ws_channel_folder_access($channel, array((int) $viewer['id']), true);

        if (function_exists('ws_scheduled_joined')) {
            ws_scheduled_joined($channel, array((int) $viewer['id']));
        }
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Leaves a channel. An owner who leaves hands the channel to the member who
 * has been in it longest; a private channel nobody is left in stays, archived.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $user_id someone else, removed by the owner
 * @return array ok, error
 */
function ws_channel_leave($viewer, $channel, $user_id = 0)
{
    $user_id = ((int) $user_id > 0) ? (int) $user_id : (int) $viewer['id'];

    if (($user_id !== (int) $viewer['id']) && !ws_can_manage_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only the owner of the channel can change it.'));
    }

    $membership = ws_channel_membership($channel['id'], $user_id);

    if (!$membership) {
        return array('ok' => true, 'error' => '');
    }

    db("DELETE FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . $user_id . "'");
    ws_channel_membership_forget();

    // Out of the channel, out of its folder.
    ws_channel_folder_access($channel, array($user_id), false);

    if ((int) $channel['owner_user_id'] === $user_id) {
        $next = (int) db_value("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "' ORDER BY joined_at LIMIT 1");

        if ($next > 0) {
            db("UPDATE ws_channels SET owner_user_id = '" . $next . "' WHERE id = '" . (int) $channel['id'] . "'");
            db("UPDATE ws_channel_members SET role = 'owner' WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . $next . "'");
        } elseif ($channel['kind'] !== 'public') {
            db("UPDATE ws_channels SET archived_at = '" . time() . "' WHERE id = '" . (int) $channel['id'] . "'");
        }
    }

    if ($channel['kind'] !== 'public') {
        $vars = array('<@user:' . (int) $viewer['id'] . '>', '<@user:' . $user_id . '>');

        ws_message_system($channel['id'], ($user_id === (int) $viewer['id'])
            ? lang(array('string' => '{var:1} left the channel', 'vars' => $vars))
            : lang(array('string' => '{var:2} was removed by {var:1}', 'vars' => $vars)));
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Opens a private channel an administrator is not in, for inspection.
 *
 * Reading is all it grants, and it is never quiet: the channel gets a line
 * saying who opened it, and the activity log keeps it.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ok, error
 */
function ws_channel_audit_open($viewer, $channel)
{
    if (($viewer['role'] >= 3) || !$viewer['member'] || ($channel['kind'] !== 'private')) {
        return array('ok' => false, 'error' => lang('Access denied.'));
    }

    if (ws_channel_membership($channel['id'], $viewer['id']) || ws_audit_open($channel['id'])) {
        return array('ok' => true, 'error' => '');
    }

    if (!ws_can_audit_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('A member of staff is in this private channel. It cannot be opened for inspection.'));
    }

    $_SESSION['software']['ws_audit'][(int) $channel['id']] = time();

    ws_message_system($channel['id'], lang(array(
        'string' => '{var:1} opened this private channel for inspection',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>'),
    )));

    log_activity(lang(array('string' => 'private workspace channel ({var:1}) was opened for inspection', 'vars' => $channel['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '');
}

/**
 * The private channels an administrator could open for inspection: their
 * names, owners and sizes, never their content.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_private_channels_for_audit($viewer)
{
    if ($viewer['role'] >= 3) {
        return array();
    }

    $rows = (array) db_items("SELECT c.id, c.name, c.topic, c.owner_user_id, c.archived_at,
            (SELECT COUNT(*) FROM ws_channel_members m WHERE m.channel_id = c.id) AS member_count
        FROM ws_channels c
        LEFT JOIN ws_channel_members me ON me.channel_id = c.id AND me.user_id = '" . (int) $viewer['id'] . "'
        WHERE c.kind = 'private' AND me.user_id IS NULL
        ORDER BY c.name");

    $out = array();

    foreach ($rows as $row) {
        $out[] = array(
            'id'       => (int) $row['id'],
            'name'     => (string) $row['name'],
            'topic'    => (string) $row['topic'],
            'owner'    => ws_person_name($row['owner_user_id']),
            'members'  => (int) $row['member_count'],
            'archived' => ((int) $row['archived_at'] > 0),
            'opened'   => ws_audit_open($row['id']),
            // A member of staff is in it: listed, never opened.
            'locked'   => ws_channel_has_staff($row['id']),
        );
    }

    return $out;
}

/**
 * Keeps the channel's summary: the plan, the people to call, the rules - the
 * page a newcomer reads first.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $summary
 * @return array ok, error
 */
function ws_channel_set_summary($viewer, $channel, $summary)
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $summary = mb_substr(ws_tokens_normalise(trim((string) $summary)), 0, 20000);

    db("UPDATE ws_channels SET
            summary = '" . e($summary) . "',
            summary_updated_by = '" . (int) $viewer['id'] . "',
            summary_updated_at = '" . time() . "'
        WHERE id = '" . (int) $channel['id'] . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * How loudly a channel may call for this person.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $notify all | mentions | none
 * @return array ok, error
 */
function ws_channel_set_notify($viewer, $channel, $notify)
{
    if (!in_array($notify, array('all', 'mentions', 'none'), true)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (!ws_channel_membership($channel['id'], $viewer['id'])) {
        $join = ws_channel_join($viewer, $channel);

        if (!$join['ok']) {
            return $join;
        }
    }

    db("UPDATE ws_channel_members SET notify = '" . e($notify) . "'
        WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");

    ws_channel_membership_forget();

    return array('ok' => true, 'error' => '');
}

/**
 * Marks a channel read up to a message, and the mentions in it with it.
 *
 * @param int $channel_id
 * @param int $user_id
 * @param int $message_id
 */
function ws_channel_mark_read($channel_id, $user_id, $message_id)
{
    $message_id = (int) $message_id;

    if ($message_id <= 0) {
        return;
    }

    db("UPDATE ws_channel_members SET last_read_id = GREATEST(last_read_id, '" . $message_id . "')
        WHERE channel_id = '" . (int) $channel_id . "' AND user_id = '" . (int) $user_id . "'");

    db("UPDATE ws_inbox SET read_at = '" . time() . "'
        WHERE user_id = '" . (int) $user_id . "' AND channel_id = '" . (int) $channel_id . "'
        AND read_at = 0 AND ((kind = 'mention' AND message_id <= '" . $message_id . "') OR kind = 'guest_message')");

    if (mysqli_affected_rows(db::$con) > 0) {
        ws_bell_sync_read($user_id);
    }
}

/**
 * On a workspace that has no channel yet, the first visitor finds one: a
 * public "general" channel for the whole team, with everyone in it.
 *
 * @param array $viewer
 */
function ws_ensure_first_channel($viewer)
{
    if (!$viewer['member'] || ((int) db_value("SELECT COUNT(*) FROM ws_channels") > 0)) {
        return;
    }

    $result = ws_channel_create($viewer, array(
        'name'  => lang('general'),
        'kind'  => 'public',
        'topic' => lang('Anything the whole team should know.'),
    ));

    if ($result['ok']) {
        $channel = ws_channel($result['channel_id']);
        $now = time();

        foreach (ws_team_ids() as $user_id) {
            db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, joined_at)
                VALUES ('" . (int) $channel['id'] . "', '" . (int) $user_id . "', 'member', '" . $now . "')");
        }

        ws_channel_membership_forget();
    }
}

/**
 * The channels tied to a customer.
 *
 * @param array $viewer
 * @param int   $contact_id
 * @return array[]
 */
function ws_contact_channels($viewer, $contact_id)
{
    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_channels WHERE contact_id = '" . (int) $contact_id . "' ORDER BY archived_at, last_message_at DESC") as $channel) {
        if (ws_can_read_channel($viewer, $channel)) {
            $out[] = ws_channel_brief($channel);
        }
    }

    return $out;
}

/**
 * Can a person pin and order their channels yet (4.84)?
 *
 * @return bool
 */
function ws_channel_order_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_channel_members' AND COLUMN_NAME = 'pinned'") > 0);
    }

    return $ready;
}

/**
 * Pins a channel to the top of this person's list, or takes the pin off.
 *
 * @param array $viewer
 * @param array $channel
 * @param bool  $pinned
 * @return array ok, error
 */
function ws_channel_pin($viewer, $channel, $pinned)
{
    if (!ws_channel_order_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    // Pinning is for a channel one is in; a public one is joined on the way.
    if (!ws_channel_membership($channel['id'], $viewer['id'])) {
        $joined = ws_channel_join($viewer, $channel);

        if (!$joined['ok']) {
            return $joined;
        }
    }

    db("UPDATE ws_channel_members SET pinned = '" . ($pinned ? 1 : 0) . "'
        WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");

    ws_channel_membership_forget();

    return array('ok' => true, 'error' => '');
}

/**
 * Keeps the order this person dragged their channels into.
 *
 * @param array $viewer
 * @param int[] $channel_ids in the order wanted
 * @return array ok, error
 */
function ws_channel_order($viewer, $channel_ids)
{
    if (!ws_channel_order_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $position = 0;

    foreach ((array) $channel_ids as $channel_id) {
        $position++;

        db("UPDATE ws_channel_members SET sort = '" . $position . "'
            WHERE channel_id = '" . (int) $channel_id . "' AND user_id = '" . (int) $viewer['id'] . "'");
    }

    return array('ok' => true, 'error' => '');
}
