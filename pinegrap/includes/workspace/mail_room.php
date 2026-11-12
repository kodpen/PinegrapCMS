<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: a mailbox kept in no channel. Such a box keeps its mail in a
 * room of its own, a ws_channels row of kind "mailbox" named after the box.
 * The room is no place among the channels: no channel list, picker or
 * search shows it, nothing is posted into it (ws_can_post_channel()) and
 * nothing about it is changed by a channel's actions
 * (ws_can_manage_channel()). Its members are the people chosen on the box,
 * one by one or by department, put in step whenever the box or one of those
 * departments is saved. Whoever is in it reads and answers its mail on the
 * E-mails screen, the room's only face.
 *
 * The box keeps the choice in its settings JSON: kept_in ("mailbox"; absent
 * for a box kept in a channel), room_id (the room, kept when the box moves
 * to a channel so that it gets the same room back), room_people and
 * room_departments.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most people a mailbox names one by one. */
define('WS_MAIL_ROOM_PEOPLE_MAX', 200);

/** The most departments a mailbox names. */
define('WS_MAIL_ROOM_DEPARTMENTS_MAX', 50);

/**
 * Can a mailbox be kept in no channel on this site: the mail tables are
 * there and ws_channels.kind knows "mailbox" (step 11.0.188)?
 *
 * @return bool
 */
function ws_mail_room_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mail_ready() && (strpos((string) db_value("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_channels' AND COLUMN_NAME = 'kind'"), "'mailbox'") !== false);
    }

    return $ready;
}

/**
 * Is this channel row the room of a mailbox kept in no channel? Pure.
 *
 * @param array|null $channel
 * @return bool
 */
function ws_channel_is_mailbox($channel)
{
    return is_array($channel) && ((string) ($channel['kind'] ?? '') === 'mailbox');
}

/**
 * Where a box keeps its mail: "mailbox" (a room of its own) or "channel".
 * Pure.
 *
 * @param array|null $box ws_mail_boxes row
 * @return string
 */
function ws_mail_box_kept_in($box)
{
    return ((string) (ws_mail_box_settings($box)['kept_in'] ?? '') === 'mailbox') ? 'mailbox' : 'channel';
}

/**
 * May this person answer, send and change the mail kept in this channel? In
 * a channel, whoever may post in it; in a mailbox's own room, where nobody
 * posts, its members. Team accounts only.
 *
 * @param array      $viewer
 * @param array|null $channel
 * @return bool
 */
function ws_mail_can_answer($viewer, $channel)
{
    if (!ws_channel_is_mailbox($channel)) {
        return is_array($channel) && ws_can_post_channel($viewer, $channel);
    }

    return !empty($viewer['member']) && ((int) $channel['archived_at'] === 0) && (bool) ws_channel_membership($channel['id'], $viewer['id']);
}

/**
 * The "Kept in" half of a mailbox's form as posted: where its mail is kept
 * and, for a box kept in no channel, who reads it. Only ids that can be
 * chosen are taken. Pure.
 *
 * @param array $input          kept_in, room_people[], room_departments[]
 * @param int[] $team_ids       the team's accounts
 * @param int[] $department_ids the departments in use
 * @return array ok, error, field, kept_in, people, departments
 */
function ws_mail_room_input($input, $team_ids, $department_ids)
{
    $chosen = function ($value, $known, $most) {
        $known = array_map('intval', (array) $known);
        $out = array();

        foreach (is_array($value) ? $value : array() as $id) {
            if (is_scalar($id) && ctype_digit((string) $id) && in_array((int) $id, $known, true)) {
                $out[(int) $id] = (int) $id;
            }
        }

        sort($out);

        return array_slice($out, 0, $most);
    };

    $out = array(
        'ok'          => true,
        'error'       => '',
        'field'       => '',
        'kept_in'     => (($input['kept_in'] ?? '') === 'mailbox') ? 'mailbox' : 'channel',
        'people'      => $chosen($input['room_people'] ?? array(), $team_ids, WS_MAIL_ROOM_PEOPLE_MAX),
        'departments' => $chosen($input['room_departments'] ?? array(), $department_ids, WS_MAIL_ROOM_DEPARTMENTS_MAX),
    );

    // A room nobody is in is mail nobody reads.
    if (($out['kept_in'] === 'mailbox') && !$out['people'] && !$out['departments']) {
        return array_merge($out, array('ok' => false, 'error' => lang('Choose the people or the departments who read this mailbox.'), 'field' => 'room_people'));
    }

    return $out;
}

/**
 * Who is in a mailbox's room: the people named one by one and everybody of
 * the departments named, as long as they are in the team. Pure.
 *
 * @param int[]   $people
 * @param int[][] $department_members the members of each department named
 * @param int[]   $team_ids
 * @return int[] sorted
 */
function ws_mail_room_members($people, $department_members, $team_ids)
{
    $wanted = array_map('intval', (array) $people);

    foreach ((array) $department_members as $members) {
        $wanted = array_merge($wanted, array_map('intval', (array) $members));
    }

    $wanted = array_values(array_unique(array_intersect($wanted, array_map('intval', (array) $team_ids))));
    sort($wanted);

    return $wanted;
}

/**
 * What brings a room's members in step with the choice. Pure.
 *
 * @param int[] $wanted  ws_mail_room_members()
 * @param int[] $current the room's members now
 * @return array add, remove (int[] each)
 */
function ws_mail_room_plan($wanted, $current)
{
    $wanted = array_map('intval', (array) $wanted);
    $current = array_map('intval', (array) $current);

    return array(
        'add'    => array_values(array_diff($wanted, $current)),
        'remove' => array_values(array_diff($current, $wanted)),
    );
}

/**
 * The people a box's settings put in its room now. A department moved to
 * the archive brings nobody.
 *
 * @param array $settings ws_mail_box_settings()
 * @return int[]
 */
function ws_mail_room_wanted($settings)
{
    $departments = ws_departments(true);
    $members = array();

    foreach ((array) ($settings['room_departments'] ?? array()) as $department_id) {
        if (isset($departments[(int) $department_id])) {
            $members[] = $departments[(int) $department_id]['members'];
        }
    }

    return ws_mail_room_members((array) ($settings['room_people'] ?? array()), $members, ws_team_ids());
}

/**
 * The room of a box kept in no channel: the one its settings name, renamed
 * with the box and opened again if it was archived, else a new one. It has
 * no owner: nobody manages it as a channel.
 *
 * @param array  $viewer
 * @param array  $settings the box's settings (room_id)
 * @param string $name     the box's name
 * @return int the room's id, 0 when it could not be made
 */
function ws_mail_room_ensure($viewer, $settings, $name)
{
    $name = mb_substr(trim((string) $name), 0, 80);
    $room = ((int) ($settings['room_id'] ?? 0) > 0) ? ws_channel((int) $settings['room_id']) : null;

    if (ws_channel_is_mailbox($room)) {
        if (((string) $room['name'] !== $name) || ((int) $room['archived_at'] > 0)) {
            db("UPDATE ws_channels SET name = '" . e($name) . "', archived_at = 0 WHERE id = '" . (int) $room['id'] . "'");
            ws_channel_folder_rename($room, $name);
        }

        return (int) $room['id'];
    }

    $now = time();

    db("INSERT INTO ws_channels (name, kind, topic, owner_user_id, contact_id, department_id, created_by, created_at, last_message_at)
        VALUES ('" . e($name) . "', 'mailbox', '', 0, 0, 0, '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Puts a room's members in step with the choice on its box: whoever is
 * missing comes in, the others go out, with the room's folder and what
 * their inbox says about its mail following them.
 *
 * @param array $room   ws_channels row of kind mailbox
 * @param int[] $wanted ws_mail_room_wanted()
 * @return array add, remove (ws_mail_room_plan())
 */
function ws_mail_room_sync($room, $wanted)
{
    $room_id = (int) $room['id'];
    $plan = ws_mail_room_plan($wanted, (array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . $room_id . "'"));
    $now = time();

    foreach ($plan['add'] as $user_id) {
        db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, last_read_id, joined_at)
            VALUES ('" . $room_id . "', '" . (int) $user_id . "', 'member', '" . (int) $room['last_message_id'] . "', '" . $now . "')");
    }

    if ($plan['remove']) {
        db("DELETE FROM ws_channel_members WHERE channel_id = '" . $room_id . "' AND user_id IN (" . implode(',', array_map('intval', $plan['remove'])) . ")");
    }

    if ($plan['add'] || $plan['remove']) {
        ws_channel_membership_forget();
    }

    if ($plan['add']) {
        ws_channel_folder_access($room, $plan['add'], true);
        ws_inbox_reach_regained($room, $plan['add']);
    }

    if ($plan['remove']) {
        ws_channel_folder_access($room, $plan['remove'], false);
        ws_inbox_reach_lost($room, $plan['remove']);
    }

    return $plan;
}

/**
 * What a mailbox's save does about its room, once its form passed
 * (ws_mail_box_save_check()): a box kept in no channel gets its room, made
 * or found again, as its channel; any box that has a room keeps the room's
 * people in step with the choice, so mail it kept there earlier is read by
 * the people chosen now.
 *
 * @param array $viewer
 * @param array $values   the form's values (name, channel_id)
 * @param array $settings what the save stores (kept_in, room_id, room_people, room_departments)
 * @return array channel_id (what the box writes into), settings
 */
function ws_mail_room_save($viewer, $values, $settings)
{
    $channel_id = (int) $values['channel_id'];

    if ((string) ($settings['kept_in'] ?? '') === 'mailbox') {
        $settings['room_id'] = ws_mail_room_ensure($viewer, $settings, (string) $values['name']);
        $channel_id = (int) $settings['room_id'];
    }

    $room = ((int) ($settings['room_id'] ?? 0) > 0) ? ws_channel((int) $settings['room_id']) : null;

    if (ws_channel_is_mailbox($room)) {
        ws_mail_room_sync($room, ws_mail_room_wanted($settings));
    }

    return array('channel_id' => $channel_id, 'settings' => $settings);
}

/**
 * A department's people or its place in the archive changed: the rooms of
 * the mailboxes that name it follow.
 *
 * @param int $department_id
 * @return int rooms looked at
 */
function ws_mail_rooms_follow_department($department_id)
{
    if (!ws_mail_room_ready()) {
        return 0;
    }

    $rooms = 0;

    foreach ((array) db_items("SELECT id, settings FROM ws_mail_boxes ORDER BY id") as $box) {
        $settings = ws_mail_box_settings($box);

        if (!in_array((int) $department_id, array_map('intval', (array) ($settings['room_departments'] ?? array())), true)) {
            continue;
        }

        $room = ((int) ($settings['room_id'] ?? 0) > 0) ? ws_channel((int) $settings['room_id']) : null;

        if (ws_channel_is_mailbox($room)) {
            ws_mail_room_sync($room, ws_mail_room_wanted($settings));
            $rooms++;
        }
    }

    return $rooms;
}

/**
 * The E-mails screen for mail kept in a room: one conversation open, or the
 * list of one mailbox, or the whole list. Pure.
 *
 * @param string $base            the panel's address, ending in /
 * @param int    $conversation_id 0 for none
 * @param int    $box_id          0 for none
 * @return string
 */
function ws_mail_room_url($base, $conversation_id = 0, $box_id = 0)
{
    return $base . ws_mail_list_page_url(ws_mail_list_filters(array('view' => 'all', 'box' => (string) max(0, (int) $box_id))), (int) $conversation_id);
}

/**
 * Where an inbox line about a mail leads: the card in its channel, or, for
 * mail kept in a mailbox's own room, its conversation on the E-mails screen.
 *
 * @param string     $base       the panel's address, ending in /
 * @param array|null $channel    the line's channel
 * @param int        $message_id the card or the mail's message
 * @return string
 */
function ws_mail_notice_url($base, $channel, $message_id)
{
    if (!ws_channel_is_mailbox($channel)) {
        return $base . 'workspace.php?channel=' . (int) ($channel['id'] ?? 0) . '&message=' . (int) $message_id;
    }

    return ws_mail_room_url($base, ws_mail_room_conversation_id((int) $channel['id'], (int) $message_id));
}

/**
 * The conversation a message of a room belongs to: the mail it is, else the
 * conversation whose card it is.
 *
 * @param int $room_id
 * @param int $message_id
 * @return int 0 when it is no mail of the room
 */
function ws_mail_room_conversation_id($room_id, $message_id)
{
    if ((int) $message_id <= 0) {
        return 0;
    }

    return (int) db_value("SELECT c.id FROM ws_mail_conversations c
        LEFT JOIN ws_mail_items i ON i.conversation_id = c.id AND i.message_id = '" . (int) $message_id . "'
        WHERE c.channel_id = '" . (int) $room_id . "' AND (c.message_id = '" . (int) $message_id . "' OR i.id IS NOT NULL)
        ORDER BY c.id LIMIT 1");
}

/**
 * The address the channels screen was asked for, when it names a mailbox's
 * room: the E-mails screen, with the conversation of the message asked for
 * or the room's mailbox. '' for anything else, a room the person is not in
 * included.
 *
 * @param array  $viewer
 * @param array  $query the address's words (channel, message)
 * @param string $base  the panel's address, ending in /
 * @return string
 */
function ws_mail_room_redirect($viewer, $query, $base)
{
    $channel_id = (is_scalar($query['channel'] ?? null) && ctype_digit((string) $query['channel'])) ? (int) $query['channel'] : 0;
    $room = ($channel_id > 0) ? ws_channel($channel_id) : null;

    if (!ws_channel_is_mailbox($room) || !ws_can_read_channel($viewer, $room)) {
        return '';
    }

    $message_id = (is_scalar($query['message'] ?? null) && ctype_digit((string) $query['message'])) ? (int) $query['message'] : 0;

    return ws_mail_room_url($base, ws_mail_room_conversation_id($channel_id, $message_id),
        (int) db_value("SELECT id FROM ws_mail_boxes WHERE channel_id = '" . $channel_id . "' ORDER BY id LIMIT 1"));
}

/**
 * The "Kept in" half of a mailbox's form: a channel, or the mailbox itself
 * with the people and the departments who read it. Where the site cannot
 * keep a box in no channel yet, only the channel, as before.
 *
 * @param string     $prefix
 * @param array|null $box
 * @param string     $options the channel select's options, HTML
 * @return string HTML
 */
function ws_mail_room_fields($prefix, $box, $options)
{
    $channel = function ($hidden) use ($prefix, $options) {
        return '
                <div class="md:col-6' . ($hidden ? ' d-none' : '') . '" data-ws-mail-when="kept_in=channel">
                    <label class="form-label" for="' . $prefix . 'channel">' . h(lang('Channel')) . '</label>
                    <select class="form-control form-control-sm" name="channel_id" id="' . $prefix . 'channel" required>' . $options . '</select>
                    <div class="form-text">' . h(lang('Each new conversation becomes a card here; its answers go under the card.')) . '</div>
                </div>';
    };

    if (!ws_mail_room_ready()) {
        return '<input type="hidden" name="kept_in" value="channel">' . $channel(false);
    }

    $room = (ws_mail_box_kept_in($box) === 'mailbox');
    $settings = ws_mail_box_settings($box);
    $lists = array(
        'room_people'      => array('label' => lang('People'), 'chosen' => array_map('intval', (array) ($settings['room_people'] ?? array())), 'rows' => array()),
        'room_departments' => array('label' => lang('Departments'), 'chosen' => array_map('intval', (array) ($settings['room_departments'] ?? array())), 'rows' => array()),
    );

    foreach (ws_team_members() as $person) {
        $lists['room_people']['rows'][(int) $person['id']] = (string) $person['name'];
    }

    foreach (ws_departments() as $department) {
        $lists['room_departments']['rows'][(int) $department['id']] = (string) $department['name'];
    }

    $html = '
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'kept_in">' . h(lang('Kept in')) . '</label>
                    <select class="form-control form-control-sm" name="kept_in" id="' . $prefix . 'kept_in">
                        <option value="channel"' . ($room ? '' : ' selected') . '>' . h(lang('A channel')) . '</option>
                        <option value="mailbox"' . ($room ? ' selected' : '') . '>' . h(lang('The mailbox itself')) . '</option>
                    </select>
                    <div class="form-text">' . h(lang('Kept in the mailbox itself, its mail is in no channel: the people chosen here read and answer it on the E-mails screen.')) . '</div>
                </div>' . $channel($room);

    foreach ($lists as $name => $list) {
        $rows = '';

        foreach ($list['rows'] as $id => $label) {
            $rows .= '
                        <div class="form-field mb-0">
                            <input class="check" type="checkbox" name="' . $name . '[]" value="' . (int) $id . '" id="' . $prefix . $name . '_' . (int) $id . '"' . (in_array((int) $id, $list['chosen'], true) ? ' checked' : '') . '>
                            <label for="' . $prefix . $name . '_' . (int) $id . '">' . h($label) . '</label>
                        </div>';
        }

        $html .= '
                <div class="md:col-6' . ($room ? '' : ' d-none') . '" data-ws-mail-when="kept_in=mailbox">
                    <fieldset>
                        <legend class="form-label">' . h($list['label']) . '</legend>
                        <div class="ws-dept-people">' . (($rows !== '') ? $rows : '<div class="form-text">' . h(lang('None yet')) . '</div>') . '</div>
                    </fieldset>
                </div>';
    }

    return $html;
}
