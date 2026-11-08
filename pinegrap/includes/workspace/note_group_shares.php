<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - shared note groups: a person's group shared with colleagues
 * (to read or to change) or in a channel. A group's share is a row of
 * ws_note_group_shares; its notes are shared through ordinary ws_note_shares
 * rows whose via_group names the group - one per note and person or channel
 * - so every reader of note shares (access, files, the list, sync,
 * deletions) works on them unchanged. A channel gets one card for the group
 * (the group share's message_id), none per note.
 *
 * ws_note_group_share_sync() lays the rows a group wants over the rows it
 * has; it runs whenever a note goes in or out, a note or the group is
 * locked, a share is given or taken back or the group is deleted. A direct
 * share of a note (via_group 0) is never touched here.
 *
 * Who puts what in: the owner of the group any note of theirs (and moves or
 * takes out any note in it); a person it is shared with to change it their
 * own notes only, which stay theirs. A shared group holds only notes put in
 * by their owners: a note shared with the group's owner that sits in the
 * group is not passed on. One who leaves the group, or whose share is taken
 * back, takes their notes out.
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
 * The ws_note_shares rows a group wants: each note put in by its owner,
 * to the group's owner (who may change it), to each person the group is
 * shared with (with their right) and to each channel it is shared in (to
 * read), never to the note's own owner. A locked group wants none, a locked
 * note none.
 *
 * @param array   $group  id, user_id, locked
 * @param array[] $notes  note_id, placed_by, owner, locked
 * @param array[] $shares the group's ws_note_group_shares rows: user_id, channel_id, can_edit
 * @return array[] "<note>:u<user>" / "<note>:c<channel>" => note_id, user_id, channel_id, can_edit
 */
function ws_note_group_share_wanted($group, $notes, $shares)
{
    if (!empty($group['locked']) || empty($shares)) {
        return array();
    }

    $people = array((int) $group['user_id'] => 1);
    $channels = array();

    foreach ((array) $shares as $share) {
        $user_id = (int) ($share['user_id'] ?? 0);
        $channel_id = (int) ($share['channel_id'] ?? 0);

        if ($user_id > 0) {
            $people[$user_id] = max($people[$user_id] ?? 0, empty($share['can_edit']) ? 0 : 1);
        } elseif ($channel_id > 0) {
            $channels[$channel_id] = true;
        }
    }

    $wanted = array();

    foreach ((array) $notes as $note) {
        $note_id = (int) $note['note_id'];
        $owner = (int) $note['owner'];

        if (($note_id <= 0) || !empty($note['locked']) || ((int) $note['placed_by'] !== $owner)) {
            continue;
        }

        foreach ($people as $user_id => $can_edit) {
            if ($user_id !== $owner) {
                $wanted[$note_id . ':u' . $user_id] = array('note_id' => $note_id, 'user_id' => $user_id, 'channel_id' => 0, 'can_edit' => $can_edit);
            }
        }

        foreach (array_keys($channels) as $channel_id) {
            $wanted[$note_id . ':c' . $channel_id] = array('note_id' => $note_id, 'user_id' => 0, 'channel_id' => $channel_id, 'can_edit' => 0);
        }
    }

    return $wanted;
}

/**
 * The rows a group wants laid over the rows it has: what to write, whose
 * right to change in place (row id => can_edit), and which rows to delete
 * (not wanted, or a second row of the same note and reader).
 *
 * @param array[] $wanted   ws_note_group_share_wanted()
 * @param array[] $existing ws_note_shares rows with this via_group: id, note_id, user_id, channel_id, can_edit
 * @return array insert (wanted rows), update (id => can_edit), delete (existing rows)
 */
function ws_note_group_share_diff($wanted, $existing)
{
    $out = array('insert' => array(), 'update' => array(), 'delete' => array());
    $seen = array();

    foreach ((array) $existing as $row) {
        $key = (int) $row['note_id'] . (((int) $row['user_id'] > 0) ? ':u' . (int) $row['user_id'] : ':c' . (int) $row['channel_id']);

        if (!isset($wanted[$key]) || isset($seen[$key])) {
            $out['delete'][] = $row;
            continue;
        }

        $seen[$key] = true;

        if ((int) $row['can_edit'] !== (int) $wanted[$key]['can_edit']) {
            $out['update'][(int) $row['id']] = (int) $wanted[$key]['can_edit'];
        }
    }

    foreach ((array) $wanted as $key => $row) {
        if (!isset($seen[$key])) {
            $out['insert'][$key] = $row;
        }
    }

    return $out;
}

/**
 * How far a person reaches a group: its owner, a person it is shared with
 * to change it (edit) or to read it (view), a member of a channel it is
 * shared in that they may read (view), or not at all.
 *
 * @param int     $user_id
 * @param array   $group    user_id
 * @param array[] $shares   the group's shares: user_id, channel_id, can_edit
 * @param int[]   $channels channel ids the person may read
 * @return string owner | edit | view | ''
 */
function ws_note_group_reach_of($user_id, $group, $shares, $channels)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return '';
    }

    if ((int) $group['user_id'] === $user_id) {
        return 'owner';
    }

    $channels = array_map('intval', (array) $channels);
    $reach = '';

    foreach ((array) $shares as $share) {
        if ((int) ($share['user_id'] ?? 0) === $user_id) {
            if (!empty($share['can_edit'])) {
                return 'edit';
            }

            $reach = 'view';
        } elseif (((int) ($share['channel_id'] ?? 0) > 0) && in_array((int) $share['channel_id'], $channels, true)) {
            $reach = 'view';
        }
    }

    return $reach;
}

/**
 * Why a person may not put a note into a group, move it along or take it
 * out ('' when they may). The owner puts in any note of theirs - into a
 * group nobody else has, a note shared with them too - and moves or takes
 * out any note in it; a person with edit puts in, moves and takes out their
 * own notes only.
 *
 * @param string $reach     ws_note_group_reach_of()
 * @param bool   $shared    the group is shared
 * @param bool   $own_note  the person owns the note
 * @param int    $placed_by who keeps the note in this group, 0 when it is not in it
 * @param int    $user_id   the person
 * @return string '' | no_group | own_only | not_yours
 */
function ws_note_group_place_refusal($reach, $shared, $own_note, $placed_by, $user_id)
{
    if (($reach !== 'owner') && ($reach !== 'edit')) {
        return 'no_group';
    }

    if ((int) $placed_by > 0) {
        if (($reach === 'owner') || (((int) $placed_by === (int) $user_id) && $own_note)) {
            return '';
        }

        return 'not_yours';
    }

    if (($shared || ($reach === 'edit')) && !$own_note) {
        return 'own_only';
    }

    return '';
}

/**
 * The sentence for a refusal of ws_note_group_place_refusal().
 *
 * @param string $code
 * @return string
 */
function ws_note_group_place_error($code)
{
    if ($code === 'own_only') {
        return lang('Only your own notes go into a shared group.');
    }

    if ($code === 'not_yours') {
        return lang('Only the owner of the group moves notes that are not yours.');
    }

    return lang('That group could not be found.');
}

/**
 * The shares of a group.
 *
 * @param int $group_id
 * @return array[] ws_note_group_shares rows, oldest first
 */
function ws_note_group_shares_of($group_id)
{
    if (((int) $group_id <= 0) || !ws_note_groups_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_note_group_shares WHERE group_id = '" . (int) $group_id . "' ORDER BY id");
}

/**
 * How far the person reaches a group (ws_note_group_reach_of()).
 *
 * @param array      $viewer
 * @param array      $group
 * @param array|null $shares read here when null
 * @return string owner | edit | view | ''
 */
function ws_note_group_reach($viewer, $group, $shares = null)
{
    if (!is_array($group)) {
        return '';
    }

    if ($shares === null) {
        $shares = ws_note_group_shares_of($group['id']);
    }

    $channels = array();

    foreach ((array) $shares as $share) {
        if (((int) $share['channel_id'] > 0) && ws_can_read_channel($viewer, ws_channel((int) $share['channel_id']))) {
            $channels[] = (int) $share['channel_id'];
        }
    }

    return ws_note_group_reach_of((int) $viewer['id'], $group, $shares, $channels);
}

/**
 * A group the person reaches - their own or one shared with them - with
 * reach and shared (whether anybody else has it).
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array|null the row
 */
function ws_note_group_reached($viewer, $group_id)
{
    if (((int) $group_id <= 0) || !ws_note_groups_ready()) {
        return null;
    }

    $group = db_item("SELECT * FROM ws_note_groups WHERE id = '" . (int) $group_id . "'");

    if (!is_array($group)) {
        return null;
    }

    $shares = ws_note_group_shares_of($group['id']);
    $reach = ws_note_group_reach($viewer, $group, $shares);

    if ($reach === '') {
        return null;
    }

    $group['reach'] = $reach;
    $group['shared'] = !empty($shares);

    return $group;
}

/**
 * Who keeps a note in a group, 0 when it is not in it.
 *
 * @param int $group_id
 * @param int $note_id
 * @return int
 */
function ws_note_group_placed_by($group_id, $note_id)
{
    return (int) db_value("SELECT user_id FROM ws_note_places WHERE group_id = '" . (int) $group_id . "' AND note_id = '" . (int) $note_id . "' LIMIT 1");
}

/**
 * Why the person may not put the note into the group or move it there
 * ('' when they may): for ws_note_place_check().
 *
 * @param array $viewer
 * @param array $note
 * @param array $group ws_note_group_reached()
 * @return string the sentence
 */
function ws_note_group_place_gate($viewer, $note, $group)
{
    $code = ws_note_group_place_refusal(
        (string) ($group['reach'] ?? 'owner'),
        !empty($group['shared']),
        (int) $note['user_id'] === (int) $viewer['id'],
        ws_note_group_placed_by($group['id'], $note['id']),
        (int) $viewer['id']
    );

    return ($code === '') ? '' : ws_note_group_place_error($code);
}

/**
 * Brings a group's ws_note_shares rows in step with its notes and shares:
 * missing rows written, changed rights changed, rows no longer wanted
 * deleted. A person who loses a note this way has it dropped from their
 * list for sync clients (ws_note_deletions), and, without any share of it
 * left, from their groups and inbox. The folders of the notes touched
 * follow (ws_note_folder_sync()).
 *
 * @param int $group_id
 * @param int $by who made the change
 */
function ws_note_group_share_sync($group_id, $by = 0)
{
    if (((int) $group_id <= 0) || !ws_note_groups_ready()) {
        return;
    }

    $group_id = (int) $group_id;
    $group = db_item("SELECT * FROM ws_note_groups WHERE id = '" . $group_id . "'");
    $shares = is_array($group) ? ws_note_group_shares_of($group_id) : array();
    $notes = array();

    if (is_array($group) && !empty($shares)) {
        $locked = ws_note_lock_ready() ? 'n.locked' : '0';

        $notes = (array) db_items("SELECT p.note_id, p.user_id AS placed_by, n.user_id AS owner, " . $locked . " AS locked
            FROM ws_note_places p
            INNER JOIN ws_notes n ON n.id = p.note_id
            WHERE p.group_id = '" . $group_id . "'");
    } else {
        $group = array('id' => $group_id, 'user_id' => 0, 'locked' => 0);
    }

    $existing = (array) db_items("SELECT id, note_id, user_id, channel_id, can_edit FROM ws_note_shares WHERE via_group = '" . $group_id . "'");
    $diff = ws_note_group_share_diff(ws_note_group_share_wanted($group, $notes, $shares), $existing);
    $touched = array();
    $now = time();

    foreach ($diff['insert'] as $row) {
        db("INSERT INTO ws_note_shares (note_id, user_id, channel_id, can_edit, shared_by, created_at, via_group)
            VALUES ('" . $row['note_id'] . "', '" . $row['user_id'] . "', '" . $row['channel_id'] . "', '" . $row['can_edit'] . "', '" . (int) $by . "', '" . $now . "', '" . $group_id . "')");
        $touched[$row['note_id']] = true;
    }

    foreach ($diff['update'] as $id => $can_edit) {
        db("UPDATE ws_note_shares SET can_edit = '" . (int) $can_edit . "' WHERE id = '" . (int) $id . "'");
    }

    foreach ($diff['delete'] as $row) {
        db("DELETE FROM ws_note_shares WHERE id = '" . (int) $row['id'] . "'");
        $touched[(int) $row['note_id']] = true;

        $user_id = (int) $row['user_id'];

        if ($user_id <= 0) {
            continue;
        }

        $deletion = ws_note_unshare_deletion_row(array('id' => (int) $row['note_id']), $row, (int) $by, $now);

        if ($deletion && ws_note_deletions_ready()) {
            db("INSERT INTO ws_note_deletions (note_id, user_id, channel_id, deleted_by, deleted_at)
                VALUES ('" . $deletion['note_id'] . "', '" . $deletion['user_id'] . "', 0, '" . $deletion['deleted_by'] . "', '" . $deletion['deleted_at'] . "')");
        }

        ws_note_places_forget_reader($user_id, (int) $row['note_id']);

        if (ws_notes_inbox_ready() && !db_value("SELECT 1 FROM ws_note_shares WHERE note_id = '" . (int) $row['note_id'] . "' AND user_id = '" . $user_id . "' LIMIT 1")) {
            ws_inbox_delete("user_id = '" . $user_id . "' AND note_id = '" . (int) $row['note_id'] . "'");
        }
    }

    foreach (array_keys($touched) as $note_id) {
        $note = ws_note($note_id);

        if ($note) {
            ws_note_folder_sync($note);
        }
    }
}

/**
 * The groups a note sits in, for whoever keeps it there.
 *
 * @param int $note_id
 * @return int[]
 */
function ws_note_group_ids_of_note($note_id)
{
    if (((int) $note_id <= 0) || !ws_note_groups_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT DISTINCT group_id FROM ws_note_places WHERE note_id = '" . (int) $note_id . "'"));
}

/**
 * A note was locked or opened up, or changed hands: every group it sits in
 * shares it again as it now may be. The lock calls this after setting
 * ws_notes.locked.
 *
 * @param array $note
 * @param int   $by
 */
function ws_note_group_shares_note_changed($note, $by = 0)
{
    foreach (ws_note_group_ids_of_note((int) $note['id']) as $group_id) {
        ws_note_group_share_sync($group_id, $by);
    }
}

/**
 * After a note was put into a group, moved between groups or taken out:
 * the groups it left and the one it went into share it as they now may,
 * and the people a shared group is shared with by name hear of a note added
 * (one bell line per note, note_group_added).
 *
 * @param array $viewer
 * @param array $note
 * @param int   $from the group it was in, 0 none
 * @param int   $to   the group it is in now, 0 none
 */
function ws_note_group_shares_after_place($viewer, $note, $from, $to)
{
    if (!ws_note_groups_ready() || ((int) $from === (int) $to)) {
        return;
    }

    if ((int) $from > 0) {
        ws_note_group_share_sync((int) $from, (int) $viewer['id']);
    }

    if ((int) $to <= 0) {
        return;
    }

    ws_note_group_share_sync((int) $to, (int) $viewer['id']);

    if (!ws_notes_inbox_ready()) {
        return;
    }

    foreach ((array) db_values("SELECT DISTINCT user_id FROM ws_note_shares
        WHERE note_id = '" . (int) $note['id'] . "' AND via_group = '" . (int) $to . "' AND user_id > 0") as $user_id) {
        if ((int) $user_id !== (int) $viewer['id']) {
            ws_notify((int) $user_id, 'note_group_added', array('note_id' => (int) $note['id'], 'actor_id' => (int) $viewer['id'], 'quiet' => true));
        }
    }
}

/**
 * Takes a note out of a group, whoever put it there: the owner of the group
 * any note, a person with edit their own (ws_note_group_place_refusal()).
 *
 * @param array $viewer
 * @param array $group ws_note_group_reached()
 * @param array $note
 * @return array ok, error
 */
function ws_note_group_take_out($viewer, $group, $note)
{
    $placed_by = (is_array($group) && is_array($note)) ? ws_note_group_placed_by($group['id'], $note['id']) : 0;

    if ($placed_by <= 0) {
        return array('ok' => false, 'error' => lang('That note is not in the group.'));
    }

    $code = ws_note_group_place_refusal((string) $group['reach'], !empty($group['shared']), (int) $note['user_id'] === (int) $viewer['id'], $placed_by, (int) $viewer['id']);

    if ($code !== '') {
        return array('ok' => false, 'error' => ws_note_group_place_error($code));
    }

    db("DELETE FROM ws_note_places WHERE group_id = '" . (int) $group['id'] . "' AND note_id = '" . (int) $note['id'] . "'");
    ws_note_group_shares_after_place($viewer, $note, (int) $group['id'], 0);

    return array('ok' => true, 'error' => '');
}

/**
 * May the owner share the group as asked? Shared by the panel and, later,
 * the API's dry run.
 *
 * @param array      $viewer
 * @param array|null $group   one of the person's own groups
 * @param array|null $channel to share it in a channel; null for people
 * @return array ok, error
 */
function ws_note_group_share_check($viewer, $group, $channel = null)
{
    if (!ws_note_groups_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (!is_array($group) || ((int) $group['user_id'] !== (int) $viewer['id'])) {
        return array('ok' => false, 'error' => lang('Only the owner of a group can share it.'));
    }

    if (!empty($group['locked'])) {
        return array('ok' => false, 'error' => lang('A locked group cannot be shared.'));
    }

    if (is_array($channel)) {
        if (!ws_can_post_channel($viewer, $channel)) {
            return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
        }

        if (db_value("SELECT 1 FROM ws_note_group_shares WHERE group_id = '" . (int) $group['id'] . "' AND channel_id = '" . (int) $channel['id'] . "' LIMIT 1")) {
            return array('ok' => false, 'error' => lang('This group is already shared in that channel.'));
        }
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Shares a group with colleagues, to read its notes or also to put their
 * own in. Somebody it is shared with already gets the new right; each new
 * one gets one bell line for the group (note_group_shared).
 *
 * @param array $viewer
 * @param array $group
 * @param int[] $user_ids
 * @param bool  $can_edit
 * @return array ok, error, shared (how many)
 */
function ws_note_group_share_people($viewer, $group, $user_ids, $can_edit)
{
    $check = ws_note_group_share_check($viewer, $group);

    if (!$check['ok']) {
        return $check + array('shared' => 0);
    }

    $shared = 0;
    $told = array();
    $now = time();
    $group_id = (int) $group['id'];

    foreach (array_unique(array_map('intval', (array) $user_ids)) as $user_id) {
        if (($user_id <= 0) || ($user_id === (int) $group['user_id']) || !ws_is_team_member($user_id)) {
            continue;
        }

        $existing = (int) db_value("SELECT id FROM ws_note_group_shares WHERE group_id = '" . $group_id . "' AND user_id = '" . $user_id . "' LIMIT 1");

        if ($existing > 0) {
            db("UPDATE ws_note_group_shares SET can_edit = '" . ($can_edit ? 1 : 0) . "' WHERE id = '" . $existing . "'");
        } else {
            db("INSERT INTO ws_note_group_shares (group_id, user_id, channel_id, can_edit, message_id, shared_by, created_at)
                VALUES ('" . $group_id . "', '" . $user_id . "', 0, '" . ($can_edit ? 1 : 0) . "', 0, '" . (int) $viewer['id'] . "', '" . $now . "')");
            $told[] = $user_id;
        }

        $shared++;
    }

    if ($shared === 0) {
        return array('ok' => false, 'error' => lang('Pick at least one colleague.'), 'shared' => 0);
    }

    ws_note_group_share_sync($group_id, (int) $viewer['id']);

    foreach ($told as $user_id) {
        ws_note_group_share_tell($viewer, $group, $user_id, $can_edit);
    }

    return array('ok' => true, 'error' => '', 'shared' => $shared);
}

/**
 * Tells a person a group was shared with them: a line of its own in their
 * bell ('separate', as for a table shared), whose sentence names the group
 * and what they may do with it, kept beside the line in
 * ws_scheduled_notices.
 *
 * @param array $viewer
 * @param array $group
 * @param int   $user_id
 * @param bool  $can_edit
 */
function ws_note_group_share_tell($viewer, $group, $user_id, $can_edit)
{
    if (!ws_notes_inbox_ready()) {
        return;
    }

    $inbox_id = ws_notify($user_id, 'note_group_shared', array('channel_id' => 0, 'message_id' => 0, 'actor_id' => (int) $viewer['id'], 'separate' => true));

    if (($inbox_id > 0) && function_exists('ws_scheduled_chains_ready') && ws_scheduled_chains_ready()) {
        $text = lang(array('string' => 'Group “{var:1}” · {var:2}', 'vars' => array($group['name'], $can_edit ? lang('Can add notes') : lang('Can read'))));

        db("REPLACE INTO ws_scheduled_notices (inbox_id, action_id, text, created_at)
            VALUES ('" . (int) $inbox_id . "', 0, '" . e(mb_substr($text, 0, 1000)) . "', '" . time() . "')");
    }
}

/**
 * Shares a group in a channel, to be read there: one card for the group
 * goes into the conversation, none for each note.
 *
 * @param array $viewer
 * @param array $group
 * @param array $channel
 * @return array ok, error, message_id
 */
function ws_note_group_share_channel($viewer, $group, $channel)
{
    $check = ws_note_group_share_check($viewer, $group, $channel);

    if (!$check['ok']) {
        return $check + array('message_id' => 0);
    }

    $sent = ws_message_send($viewer, $channel, lang(array('string' => 'Shared a group of notes: {var:1}', 'vars' => (string) $group['name'])));

    if (!$sent['ok']) {
        return array('ok' => false, 'error' => $sent['error'], 'message_id' => 0);
    }

    db("INSERT INTO ws_note_group_shares (group_id, user_id, channel_id, can_edit, message_id, shared_by, created_at)
        VALUES ('" . (int) $group['id'] . "', 0, '" . (int) $channel['id'] . "', 0, '" . (int) $sent['message_id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    ws_note_group_share_sync((int) $group['id'], (int) $viewer['id']);

    return array('ok' => true, 'error' => '', 'message_id' => (int) $sent['message_id']);
}

/**
 * Takes a group's share back: the owner any of them, the person it was
 * shared with their own (leaving the group). The person no longer reached
 * takes their notes out; the group's notes leave whoever no longer has
 * them; a channel's card says the group is no longer shared.
 *
 * @param array $viewer
 * @param array $group
 * @param int   $share_id 0 for the person's own
 * @return array ok, error
 */
function ws_note_group_unshare($viewer, $group, $share_id = 0)
{
    if (!is_array($group) || !ws_note_groups_ready()) {
        return array('ok' => false, 'error' => lang('That group could not be found.'));
    }

    $owner = ((int) $group['user_id'] === (int) $viewer['id']);

    $share = ((int) $share_id > 0)
        ? db_item("SELECT * FROM ws_note_group_shares WHERE id = '" . (int) $share_id . "' AND group_id = '" . (int) $group['id'] . "'")
        : db_item("SELECT * FROM ws_note_group_shares WHERE group_id = '" . (int) $group['id'] . "' AND user_id = '" . (int) $viewer['id'] . "' LIMIT 1");

    if (!is_array($share) || (!$owner && ((int) $share['user_id'] !== (int) $viewer['id']))) {
        return array('ok' => false, 'error' => lang('That share could not be found.'));
    }

    ws_note_group_share_drop($group, $share, (int) $viewer['id']);
    ws_note_group_share_sync((int) $group['id'], (int) $viewer['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * One share of a group gone: its row, the notes of a person who no longer
 * reaches the group, a channel's card. The caller syncs the group after.
 *
 * @param array $group
 * @param array $share
 * @param int   $by
 */
function ws_note_group_share_drop($group, $share, $by)
{
    db("DELETE FROM ws_note_group_shares WHERE id = '" . (int) $share['id'] . "'");

    $user_id = (int) $share['user_id'];

    if (($user_id > 0) && ($user_id !== (int) $group['user_id'])
        && !db_value("SELECT 1 FROM ws_note_group_shares WHERE group_id = '" . (int) $group['id'] . "' AND user_id = '" . $user_id . "' LIMIT 1")) {
        db("DELETE FROM ws_note_places WHERE group_id = '" . (int) $group['id'] . "' AND user_id = '" . $user_id . "'");
    }

    if ((int) $share['message_id'] > 0) {
        db("UPDATE ws_messages SET body = '" . e(lang('A group of notes was shared here. It is no longer shared.')) . "'
            WHERE id = '" . (int) $share['message_id'] . "' AND deleted_at = 0");

        ws_message_touch($share['message_id']);
    }
}

/**
 * A group being deleted or locked: every share of it is taken back, the
 * notes others put in go out, and its ws_note_shares rows go. Its own
 * notes are not deleted (ws_note_group_delete()).
 *
 * @param array $group
 * @param int   $by
 */
function ws_note_group_shares_forget($group, $by = 0)
{
    if (!is_array($group) || !ws_note_groups_ready()) {
        return;
    }

    foreach (ws_note_group_shares_of($group['id']) as $share) {
        ws_note_group_share_drop($group, $share, $by);
    }

    // Notes put in by people the group was shared with leave it; the
    // owner's stay where they are.
    db("DELETE FROM ws_note_places WHERE group_id = '" . (int) $group['id'] . "' AND user_id <> '" . (int) $group['user_id'] . "'");

    ws_note_group_share_sync((int) $group['id'], $by);
}

/**
 * A group's shares for its Share dialog: who has it and how.
 *
 * @param array $viewer
 * @param array $group ws_note_group_reached()
 * @return array[] id, user_id, name, channel_id, can_edit, created_at
 */
function ws_note_group_shares_present($viewer, $group)
{
    $out = array();

    foreach (ws_note_group_shares_of($group['id']) as $share) {
        $user_id = (int) $share['user_id'];
        $channel = ((int) $share['channel_id'] > 0) ? ws_channel((int) $share['channel_id']) : null;

        $out[] = array(
            'id'         => (int) $share['id'],
            'user_id'    => $user_id,
            'name'       => ($user_id > 0) ? ws_person_name($user_id) : ($channel ? (string) $channel['name'] : ''),
            'channel_id' => (int) $share['channel_id'],
            'can_edit'   => !empty($share['can_edit']),
            'created_at' => (int) $share['created_at'],
        );
    }

    return $out;
}
