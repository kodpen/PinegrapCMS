<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - note groups: a person's own folders in My Notes (name, colour,
 * order). A person keeps a note in one of their groups or in none - their
 * own notes and the notes shared with them by name alike - and orders the
 * notes inside a group. Deleting a group never deletes a note: its notes go
 * back to no group.
 *
 * Where a person keeps a note is a row of ws_note_places (user_id, note_id
 * -> group_id, sort); a group's notes are its rows, whoever put them there.
 * Groups are installed by the 2026.11.0 step 11.0.119
 * (ws_note_groups_ready() in notes.php); until it ran, every function here
 * answers as if the person had no groups.
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
 * How many groups one person keeps.
 */
define('WS_NOTE_GROUPS_MAX', 200);

/**
 * The longest group name, in characters.
 */
define('WS_NOTE_GROUP_NAME_MAX', 100);

/**
 * A group name as it is kept: spaces run together and trimmed, cut to
 * WS_NOTE_GROUP_NAME_MAX characters.
 *
 * @param mixed $name
 * @return string
 */
function ws_note_group_name($name)
{
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

    return trim(mb_substr($name, 0, WS_NOTE_GROUP_NAME_MAX));
}

/**
 * An ordered list of ids with one id moved to a position: taken out where
 * it was, put in at $position (0 the first; below 0 or past the end, the
 * last).
 *
 * @param int[] $ids
 * @param int   $id
 * @param int   $position
 * @return int[]
 */
function ws_note_group_order_insert($ids, $id, $position)
{
    $id = (int) $id;
    $list = array();

    foreach ((array) $ids as $one) {
        $one = (int) $one;

        if (($one > 0) && ($one !== $id) && !in_array($one, $list, true)) {
            $list[] = $one;
        }
    }

    $position = (int) $position;

    if (($position < 0) || ($position > count($list))) {
        $position = count($list);
    }

    array_splice($list, $position, 0, array($id));

    return $list;
}

/**
 * The order a person asked for, laid over the order there is: the ids asked
 * for that exist come first, in the order asked, then every other existing
 * id in its order before. Ids that do not exist and repeats are dropped, so
 * a list sent from a screen that missed a change loses nothing.
 *
 * @param int[] $current the existing ids, in their order
 * @param int[] $asked
 * @return int[]
 */
function ws_note_group_order_merge($current, $asked)
{
    $current = array_values(array_unique(array_map('intval', (array) $current)));
    $out = array();

    foreach ((array) $asked as $id) {
        $id = (int) $id;

        if (in_array($id, $current, true) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    foreach ($current as $id) {
        if (!in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * A group row for the screen.
 *
 * @param array $row id, name, color, sort, locked, notes (count), shares (count)
 * @return array id, name, color, hex, sort, locked, count, shared
 */
function ws_note_group_present($row)
{
    $color = function_exists('ws_palette_place') ? ws_palette_place($row['color'] ?? 0) : (int) ($row['color'] ?? 0);

    return array(
        'id'     => (int) $row['id'],
        'name'   => (string) $row['name'],
        'color'  => $color,
        'hex'    => function_exists('ws_palette_hex') ? ws_palette_hex($color) : '',
        'sort'   => (int) ($row['sort'] ?? 0),
        'locked' => !empty($row['locked']),
        'count'  => (int) ($row['notes'] ?? 0),
        'shared' => (int) ($row['shares'] ?? 0) > 0,
    );
}

/**
 * The person's own groups, in their order, each with the number of notes in
 * it.
 *
 * @param array $viewer
 * @return array[] ws_note_group_present()
 */
function ws_note_groups($viewer)
{
    if (!ws_note_groups_ready()) {
        return array();
    }

    // Whether a group is shared: the screen asks before a note goes in or out.
    $rows = (array) db_items("SELECT g.id, g.name, g.color, g.sort, g.locked, COUNT(p.note_id) AS notes,
            (SELECT COUNT(*) FROM ws_note_group_shares s WHERE s.group_id = g.id) AS shares
        FROM ws_note_groups g
        LEFT JOIN ws_note_places p ON p.group_id = g.id
        WHERE g.user_id = '" . (int) $viewer['id'] . "'
        GROUP BY g.id, g.name, g.color, g.sort, g.locked
        ORDER BY g.sort, g.id
        LIMIT " . WS_NOTE_GROUPS_MAX);

    return array_map('ws_note_group_present', $rows);
}

/**
 * One of the person's own groups.
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array|null the row
 */
function ws_note_group_for($viewer, $group_id)
{
    if (((int) $group_id <= 0) || !ws_note_groups_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_note_groups WHERE id = '" . (int) $group_id . "' AND user_id = '" . (int) $viewer['id'] . "'");

    return is_array($row) ? $row : null;
}

/**
 * May the group be made or changed as asked? Shared by the panel and, later,
 * the API's dry run.
 *
 * @param array      $viewer
 * @param array      $data  name, color
 * @param array|null $group the group changed; null for a new one
 * @return array ok, error, field, name, color
 */
function ws_note_group_save_check($viewer, $data, $group = null)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'name' => '', 'color' => 0);
    };

    if (!ws_note_groups_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $name = array_key_exists('name', (array) $data) ? ws_note_group_name($data['name']) : (is_array($group) ? (string) $group['name'] : '');

    if ($name === '') {
        return $fail(lang('Give the group a name.'), 'name');
    }

    $color = array_key_exists('color', (array) $data) ? ws_palette_place($data['color']) : (is_array($group) ? ws_palette_place($group['color']) : 0);

    if (!is_array($group)) {
        $count = (int) db_value("SELECT COUNT(*) FROM ws_note_groups WHERE user_id = '" . (int) $viewer['id'] . "'");

        if ($count >= WS_NOTE_GROUPS_MAX) {
            return $fail(lang(array('string' => 'You can keep at most {var:1} groups.', 'vars' => WS_NOTE_GROUPS_MAX)));
        }
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'name' => $name, 'color' => $color);
}

/**
 * Makes a group, last in the person's order, or renames or recolours one.
 *
 * @param array      $viewer
 * @param array      $data  name, color
 * @param array|null $group
 * @return array ok, error, field, group_id
 */
function ws_note_group_save($viewer, $data, $group = null)
{
    $check = ws_note_group_save_check($viewer, $data, $group);

    if (!$check['ok']) {
        return $check + array('group_id' => 0);
    }

    $me = (int) $viewer['id'];
    $now = time();

    if (is_array($group)) {
        db("UPDATE ws_note_groups SET name = '" . e($check['name']) . "', color = '" . (int) $check['color'] . "', updated_at = '" . $now . "'
            WHERE id = '" . (int) $group['id'] . "' AND user_id = '" . $me . "'");

        return array('ok' => true, 'error' => '', 'field' => '', 'group_id' => (int) $group['id']);
    }

    $sort = (int) db_value("SELECT MAX(sort) FROM ws_note_groups WHERE user_id = '" . $me . "'") + 1;

    db("INSERT INTO ws_note_groups (user_id, name, color, sort, locked, created_at, updated_at)
        VALUES ('" . $me . "', '" . e($check['name']) . "', '" . (int) $check['color'] . "', '" . $sort . "', 0, '" . $now . "', '" . $now . "')");

    return array('ok' => true, 'error' => '', 'field' => '', 'group_id' => (int) mysqli_insert_id(db::$con));
}

/**
 * Puts the person's groups in the order asked (ws_note_group_order_merge()).
 *
 * @param array $viewer
 * @param int[] $group_ids
 * @return array ok, error
 */
function ws_note_groups_order($viewer, $group_ids)
{
    if (!ws_note_groups_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $me = (int) $viewer['id'];
    $current = array();

    foreach ((array) db_items("SELECT id FROM ws_note_groups WHERE user_id = '" . $me . "' ORDER BY sort, id") as $row) {
        $current[] = (int) $row['id'];
    }

    foreach (ws_note_group_order_merge($current, $group_ids) as $index => $id) {
        db("UPDATE ws_note_groups SET sort = '" . ($index + 1) . "' WHERE id = '" . $id . "' AND user_id = '" . $me . "'");
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Deletes one of the person's groups. Its notes go back to no group; no
 * note is deleted.
 *
 * @param array $viewer
 * @param array $group
 * @return array ok, error
 */
function ws_note_group_delete($viewer, $group)
{
    if (!is_array($group) || ((int) $group['user_id'] !== (int) $viewer['id'])) {
        return array('ok' => false, 'error' => lang('That group could not be found.'));
    }

    $id = (int) $group['id'];

    // Its shares are taken back first, so their notes leave the people and
    // channels the group brought them to (note_group_shares.php).
    if (function_exists('ws_note_group_shares_forget')) {
        ws_note_group_shares_forget($group, (int) $viewer['id']);
    }

    db("DELETE FROM ws_note_places WHERE group_id = '" . $id . "'");
    db("DELETE FROM ws_note_groups WHERE id = '" . $id . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * May the person keep the note in the group (0 for no group)? A person
 * places a note they own or one shared with them by name, in a group of
 * their own; into a group shared with them to change it, or one they share,
 * only their own notes (ws_note_group_place_gate()), and the owner of the
 * group moves any note in it.
 *
 * @param array $viewer
 * @param array $note     the note row
 * @param int   $group_id
 * @return array ok, error, field, group (the row, null for no group)
 */
function ws_note_place_check($viewer, $note, $group_id)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'group' => null);
    };

    if (!ws_note_groups_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $me = (int) $viewer['id'];

    if (!is_array($note) || (((int) $note['user_id'] !== $me)
        && !db_value("SELECT 1 FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id = '" . $me . "' LIMIT 1"))) {
        return $fail(lang('That note could not be found.'), 'note_id');
    }

    if ((int) $group_id <= 0) {
        return array('ok' => true, 'error' => '', 'field' => '', 'group' => null);
    }

    $group = function_exists('ws_note_group_reached') ? ws_note_group_reached($viewer, $group_id) : ws_note_group_for($viewer, $group_id);

    if (!$group) {
        return $fail(lang('That group could not be found.'), 'group_id');
    }

    $error = function_exists('ws_note_group_place_gate') ? ws_note_group_place_gate($viewer, $note, $group) : '';

    if ($error !== '') {
        return $fail($error, 'group_id');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'group' => $group);
}

/**
 * Keeps a note in one of the person's groups, at a place in its order, or in
 * no group.
 *
 * @param array $viewer
 * @param array $note
 * @param int   $group_id 0 for no group
 * @param int   $position 0 the first in the group; -1 the last
 * @return array ok, error, field, group_id
 */
function ws_note_place($viewer, $note, $group_id, $position = -1)
{
    $check = ws_note_place_check($viewer, $note, $group_id);

    if (!$check['ok']) {
        return $check + array('group_id' => 0);
    }

    $me = (int) $viewer['id'];
    $note_id = (int) $note['id'];
    $from = (int) db_value("SELECT group_id FROM ws_note_places WHERE user_id = '" . $me . "' AND note_id = '" . $note_id . "'");

    if (!$check['group']) {
        db("DELETE FROM ws_note_places WHERE user_id = '" . $me . "' AND note_id = '" . $note_id . "'");

        if (function_exists('ws_note_group_shares_after_place')) {
            ws_note_group_shares_after_place($viewer, $note, $from, 0);
        }

        return array('ok' => true, 'error' => '', 'field' => '', 'group_id' => 0);
    }

    $group_id = (int) $check['group']['id'];
    $placed_by = (int) db_value("SELECT user_id FROM ws_note_places WHERE group_id = '" . $group_id . "' AND note_id = '" . $note_id . "' LIMIT 1");
    $ids = array();

    foreach ((array) db_items("SELECT note_id FROM ws_note_places WHERE group_id = '" . $group_id . "' ORDER BY sort, created_at, note_id") as $row) {
        $ids[] = (int) $row['note_id'];
    }

    $order = ws_note_group_order_insert($ids, $note_id, $position);

    // One group per person and note: a note moved from another group leaves
    // it. A note somebody else keeps in this group (a shared group's owner
    // moving a member's note along) is only put in its new place.
    if (($placed_by <= 0) || ($placed_by === $me)) {
        db("INSERT INTO ws_note_places (user_id, note_id, group_id, sort, created_at)
            VALUES ('" . $me . "', '" . $note_id . "', '" . $group_id . "', 0, '" . time() . "')
            ON DUPLICATE KEY UPDATE group_id = VALUES(group_id)");
    }

    foreach ($order as $index => $id) {
        db("UPDATE ws_note_places SET sort = '" . ($index + 1) . "' WHERE group_id = '" . $group_id . "' AND note_id = '" . $id . "'");
    }

    if (($placed_by <= 0) && function_exists('ws_note_group_shares_after_place')) {
        ws_note_group_shares_after_place($viewer, $note, $from, $group_id);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'group_id' => $group_id);
}

/**
 * Where the person keeps each of the notes: note id => group id and its
 * place in the group. Notes in no group are left out.
 *
 * @param array $viewer
 * @param int[] $note_ids
 * @return array[] note_id => array(group_id, sort)
 */
function ws_note_places_map($viewer, $note_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $note_ids)));

    if (!$ids || !ws_note_groups_ready()) {
        return array();
    }

    $map = array();

    foreach (array_chunk($ids, 500) as $chunk) {
        foreach ((array) db_items("SELECT note_id, group_id, sort FROM ws_note_places
            WHERE user_id = '" . (int) $viewer['id'] . "' AND note_id IN (" . implode(',', $chunk) . ")") as $row) {
            $map[(int) $row['note_id']] = array('group_id' => (int) $row['group_id'], 'sort' => (int) $row['sort']);
        }
    }

    return $map;
}

/**
 * Each listed note with the group the person keeps it in (group_id, 0 for
 * none) and its place there (group_sort).
 *
 * @param array   $viewer
 * @param array[] $notes ws_notes_present() rows
 * @return array[]
 */
function ws_note_places_add($viewer, $notes)
{
    $ids = array();

    foreach ((array) $notes as $note) {
        $ids[] = (int) $note['id'];
    }

    $map = ws_note_places_map($viewer, $ids);

    foreach ($notes as $index => $note) {
        $place = $map[(int) $note['id']] ?? null;
        $notes[$index]['group_id'] = $place ? $place['group_id'] : 0;
        $notes[$index]['group_sort'] = $place ? $place['sort'] : 0;
    }

    return $notes;
}

/**
 * A deleted note leaves every group it was kept in.
 *
 * @param int $note_id
 */
function ws_note_places_forget_note($note_id)
{
    if (((int) $note_id > 0) && ws_note_groups_ready()) {
        db("DELETE FROM ws_note_places WHERE note_id = '" . (int) $note_id . "'");
    }
}

/**
 * A person who no longer has a note shared with them by name stops keeping
 * it in a group of theirs.
 *
 * @param int $user_id
 * @param int $note_id
 */
function ws_note_places_forget_reader($user_id, $note_id)
{
    if (((int) $user_id <= 0) || ((int) $note_id <= 0) || !ws_note_groups_ready()) {
        return;
    }

    $kept = db_value("SELECT 1 FROM ws_notes WHERE id = '" . (int) $note_id . "' AND user_id = '" . (int) $user_id . "'")
        || db_value("SELECT 1 FROM ws_note_shares WHERE note_id = '" . (int) $note_id . "' AND user_id = '" . (int) $user_id . "' LIMIT 1");

    if (!$kept) {
        db("DELETE FROM ws_note_places WHERE user_id = '" . (int) $user_id . "' AND note_id = '" . (int) $note_id . "'");
    }
}
