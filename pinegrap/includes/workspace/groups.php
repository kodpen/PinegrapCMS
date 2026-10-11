<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the look and the order of the channel list: the palette of
 * solid, matte colours a channel or a group of channels takes, and the groups
 * themselves.
 *
 * A group is a named heading in the channel list with a colour, in the order
 * staff give it, and inside another group if it is one's part (a few levels
 * deep at most). A channel is in one group or none. The groups are the
 * workspace's, the same for everybody; staff (roles 0-2) arrange them.
 *
 * Access given on a group reaches every channel in it and in the groups
 * inside it, the private ones too: the person is made a member of each, and
 * ws_channel_members.via_group says which group brought them, so taking the
 * access back takes out only those memberships. A channel moved into the
 * group later lets the people with access in; one moved out lets out those
 * whom only the group had brought. Moving a private channel is for somebody
 * who may change it (its owner, or staff inside it): whoever holds access on
 * the group it goes to will read it, so the move is the consent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How deep groups go: a group at the top and three levels inside it.
define('WS_GROUP_DEPTH', 4);

// ── The palette ────────────────────────────────────────────────────────

/**
 * Twenty solid, matte colours: a channel's or a group's colour is its place
 * here (1-20; 0 is none), so the tones can be tuned without touching a row.
 * Dark enough for white text on them, light enough to tell apart on a dark
 * screen.
 *
 * @return array[] place => array(hex, name)
 */
function ws_palette()
{
    static $palette = null;

    if ($palette === null) {
        $palette = array(
            1  => array('#b5534b', lang('Brick')),
            2  => array('#c9774a', lang('Terracotta')),
            3  => array('#c2913a', lang('Ochre')),
            4  => array('#a3953f', lang('Olive')),
            5  => array('#7c9a4b', lang('Moss')),
            6  => array('#4f8a5b', lang('Sage')),
            7  => array('#3e8a7a', lang('Teal')),
            8  => array('#3f8599', lang('Petrol')),
            9  => array('#4a7fa8', lang('Steel blue')),
            10 => array('#5468a8', lang('Denim')),
            11 => array('#6b5ca5', lang('Indigo')),
            12 => array('#8759a0', lang('Plum')),
            13 => array('#a5578e', lang('Mulberry')),
            14 => array('#b85c73', lang('Rose')),
            15 => array('#8c6d5a', lang('Clay')),
            16 => array('#6f6a5e', lang('Taupe')),
            17 => array('#5e7470', lang('Slate green')),
            18 => array('#5f6b7a', lang('Slate')),
            19 => array('#7a7f87', lang('Pebble')),
            20 => array('#3f4a5a', lang('Charcoal')),
        );
    }

    return $palette;
}

/**
 * A place in the palette as a colour code, '' for none.
 *
 * @param int $place
 * @return string
 */
function ws_palette_hex($place)
{
    $palette = ws_palette();

    return isset($palette[(int) $place]) ? $palette[(int) $place][0] : '';
}

/**
 * The palette for the screen.
 *
 * @return array[] id, hex, name
 */
function ws_palette_js()
{
    $out = array();

    foreach (ws_palette() as $place => $color) {
        $out[] = array('id' => (int) $place, 'hex' => $color[0], 'name' => $color[1]);
    }

    return $out;
}

/**
 * A colour as it may be kept: a place in the palette or 0.
 *
 * @param mixed $value
 * @return int
 */
function ws_palette_place($value)
{
    $place = (int) $value;

    return isset(ws_palette()[$place]) ? $place : 0;
}

/**
 * Has the database the channel colours (2026.4.5, 5.82)?
 *
 * @return bool
 */
function ws_channel_colors_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_channels', 'color');
    }

    return $ready;
}

// ── Groups ─────────────────────────────────────────────────────────────

/**
 * Has the database the groups of channels (2026.4.5, 5.84)?
 *
 * @return bool
 */
function ws_groups_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_channels', 'group_id')
            && waf_table_has_column('ws_channel_members', 'via_group')
            && waf_table_has_column('ws_channel_groups', 'parent_id')
            && waf_table_has_column('ws_channel_group_access', 'user_id');
    }

    return $ready;
}

/**
 * May this person arrange the groups and give access on them?
 *
 * @param array $viewer
 * @return bool
 */
function ws_can_manage_groups($viewer)
{
    return ws_groups_ready() && !empty($viewer['member']) && ((int) $viewer['role'] < 3);
}

/**
 * Every group, read once per request (there are few); ws_groups_forget()
 * after a change.
 *
 * @param bool $forget
 * @return array[] id => row
 */
function ws_groups_all($forget = false)
{
    static $groups = null;

    if ($forget) {
        $groups = null;
        return array();
    }

    if ($groups === null) {
        $groups = array();

        if (ws_groups_ready()) {
            foreach ((array) db_items("SELECT * FROM ws_channel_groups ORDER BY parent_id, sort, name, id") as $row) {
                $groups[(int) $row['id']] = $row;
            }
        }
    }

    return $groups;
}

function ws_groups_forget()
{
    ws_groups_all(true);
}

/**
 * @param int $group_id
 * @return array|null
 */
function ws_group($group_id)
{
    $groups = ws_groups_all();

    return $groups[(int) $group_id] ?? null;
}

/**
 * The group and every group inside it, however deep.
 *
 * @param int $group_id
 * @return int[]
 */
function ws_group_tree_ids($group_id)
{
    $groups = ws_groups_all();
    $group_id = (int) $group_id;

    if (!isset($groups[$group_id])) {
        return array();
    }

    $children = array();

    foreach ($groups as $id => $row) {
        $children[(int) $row['parent_id']][] = (int) $id;
    }

    $out = array();
    $queue = array($group_id);

    while (!empty($queue)) {
        $id = array_shift($queue);

        if (isset($out[$id])) {
            continue;
        }

        $out[$id] = $id;

        foreach ($children[$id] ?? array() as $child) {
            $queue[] = $child;
        }
    }

    return array_values($out);
}

/**
 * The group and the groups it is inside, nearest first.
 *
 * @param int $group_id
 * @return int[]
 */
function ws_group_chain($group_id)
{
    $groups = ws_groups_all();
    $out = array();
    $id = (int) $group_id;

    while (($id > 0) && isset($groups[$id]) && !in_array($id, $out, true) && (count($out) < 50)) {
        $out[] = $id;
        $id = (int) $groups[$id]['parent_id'];
    }

    return $out;
}

/**
 * How many levels the group and what is inside it take (1: nothing inside).
 *
 * @param int $group_id
 * @return int
 */
function ws_group_height($group_id)
{
    $height = 0;

    foreach (ws_group_tree_ids($group_id) as $id) {
        $height = max($height, count(ws_group_chain($id)) - count(ws_group_chain($group_id)) + 1);
    }

    return $height;
}

/**
 * The group's name with the groups above it: "Customers › Istanbul".
 *
 * @param int $group_id
 * @return string
 */
function ws_group_path($group_id)
{
    $names = array();

    foreach (array_reverse(ws_group_chain($group_id)) as $id) {
        $names[] = (string) ws_group($id)['name'];
    }

    return implode(' › ', $names);
}

/**
 * The groups for the screen. Staff see how many people hold access on each;
 * everybody sees which ones gave them access themselves.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_groups_for($viewer)
{
    if (!ws_groups_ready() || empty($viewer['member'])) {
        return array();
    }

    $staff = ((int) $viewer['role'] < 3);
    $counts = array();
    $mine = array();

    foreach ((array) db_items("SELECT group_id, COUNT(*) AS people, SUM(user_id = '" . (int) $viewer['id'] . "') AS mine
        FROM ws_channel_group_access GROUP BY group_id") as $row) {
        $counts[(int) $row['group_id']] = (int) $row['people'];

        if ((int) $row['mine'] > 0) {
            $mine[(int) $row['group_id']] = true;
        }
    }

    // Everybody else sees only the groups that hold a channel they see, and
    // the groups those are in: a group's name is not news to somebody who
    // has nothing in it.
    $shown = null;

    if (!$staff) {
        $shown = array();

        foreach ((array) db_values("SELECT DISTINCT c.group_id FROM ws_channels c
            LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . (int) $viewer['id'] . "'
            WHERE c.group_id > 0 AND c.archived_at = 0 AND (c.kind = 'public' OR m.user_id IS NOT NULL)") as $group_id) {
            foreach (ws_group_chain($group_id) as $id) {
                $shown[$id] = true;
            }
        }
    }

    $out = array();

    foreach (ws_groups_all() as $id => $row) {
        if (($shown !== null) && !isset($shown[(int) $id])) {
            continue;
        }

        $out[] = array(
            'id'        => (int) $id,
            'parent_id' => (int) $row['parent_id'],
            'name'      => (string) $row['name'],
            'color'     => (int) $row['color'],
            'hex'       => ws_palette_hex($row['color']),
            'sort'      => (int) $row['sort'],
            'access'    => $staff ? ($counts[(int) $id] ?? 0) : 0,
            'mine'      => isset($mine[(int) $id]),
        );
    }

    return $out;
}

/**
 * A group name as it is kept.
 *
 * @param string $name
 * @return string
 */
function ws_group_clean_name($name)
{
    return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $name)), 0, 80);
}

/**
 * Can the group go inside that one? Not inside itself or one of its own, and
 * not deeper than WS_GROUP_DEPTH.
 *
 * @param int $group_id  0 for a new group
 * @param int $parent_id 0 for the top
 * @return string '' or why not
 */
function ws_group_place_refused($group_id, $parent_id)
{
    $parent_id = (int) $parent_id;

    if ($parent_id <= 0) {
        return '';
    }

    if (!ws_group($parent_id)) {
        return lang('That group could not be found.');
    }

    if (((int) $group_id > 0) && in_array($parent_id, ws_group_tree_ids($group_id), true)) {
        return lang('A group cannot go inside itself or inside one of its own groups.');
    }

    $height = ((int) $group_id > 0) ? ws_group_height($group_id) : 1;

    if (count(ws_group_chain($parent_id)) + $height > WS_GROUP_DEPTH) {
        return lang(array('string' => 'Groups go at most {var:1} levels deep.', 'vars' => WS_GROUP_DEPTH));
    }

    return '';
}

/**
 * Creates a group, or changes one: its name, colour and the group it is in.
 *
 * @param array $viewer
 * @param array $data id (0 for a new one), name, color, parent_id
 * @return array ok, error, field, id
 */
function ws_group_save($viewer, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'id' => 0);
    };

    if (!ws_can_manage_groups($viewer)) {
        return $fail(lang('Only staff can arrange the groups of channels.'));
    }

    $id = (int) ($data['id'] ?? 0);
    $group = ($id > 0) ? ws_group($id) : null;

    if (($id > 0) && !$group) {
        return $fail(lang('That group could not be found.'));
    }

    $name = ws_group_clean_name($data['name'] ?? '');

    if ($name === '') {
        return $fail(lang('A group needs a name.'), 'name');
    }

    $color = ws_palette_place($data['color'] ?? 0);
    $parent_id = array_key_exists('parent_id', $data) ? max(0, (int) $data['parent_id']) : ($group ? (int) $group['parent_id'] : 0);
    $refused = ws_group_place_refused($id, $parent_id);

    if ($refused !== '') {
        return $fail($refused, 'parent_id');
    }

    $now = time();

    if ($group) {
        $moved = ((int) $group['parent_id'] !== $parent_id);

        db("UPDATE ws_channel_groups SET
                name = '" . e($name) . "',
                color = '" . $color . "',
                parent_id = '" . $parent_id . "',
                " . ($moved ? "sort = '" . (ws_group_last_sort($parent_id) + 1) . "'," : '') . "
                updated_at = '" . $now . "'
            WHERE id = '" . $id . "'");

        ws_groups_forget();

        // Inside another group now: the access given higher up follows.
        if ($moved) {
            ws_group_sync_tree($viewer, $id);
        }
    } else {
        db("INSERT INTO ws_channel_groups (parent_id, name, color, sort, created_by, created_at, updated_at)
            VALUES ('" . $parent_id . "', '" . e($name) . "', '" . $color . "', '" . (ws_group_last_sort($parent_id) + 1) . "',
                '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

        $id = (int) mysqli_insert_id(db::$con);
        ws_groups_forget();
    }

    log_activity(lang(array('string' => 'workspace channel group ({var:1}) was saved', 'vars' => $name)), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id);
}

/**
 * @param int $parent_id
 * @return int
 */
function ws_group_last_sort($parent_id)
{
    return (int) db_value("SELECT MAX(sort) FROM ws_channel_groups WHERE parent_id = '" . (int) $parent_id . "'");
}

/**
 * Moves a group: inside another one (or to the top), before one of the
 * groups there (0: at the end).
 *
 * @param array $viewer
 * @param int   $group_id
 * @param int   $parent_id
 * @param int   $before_id
 * @return array ok, error
 */
function ws_group_move($viewer, $group_id, $parent_id, $before_id = 0)
{
    if (!ws_can_manage_groups($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can arrange the groups of channels.'));
    }

    $group = ws_group($group_id);

    if (!$group) {
        return array('ok' => false, 'error' => lang('That group could not be found.'));
    }

    $parent_id = max(0, (int) $parent_id);
    $refused = ws_group_place_refused($group['id'], $parent_id);

    if ($refused !== '') {
        return array('ok' => false, 'error' => $refused);
    }

    $moved = ((int) $group['parent_id'] !== $parent_id);

    // The groups there in their order, with this one where it was dropped.
    $siblings = array();

    foreach (ws_groups_all() as $id => $row) {
        if (((int) $row['parent_id'] === $parent_id) && ((int) $id !== (int) $group['id'])) {
            $siblings[] = (int) $id;
        }
    }

    $at = array_search((int) $before_id, $siblings, true);

    if ($at === false) {
        $siblings[] = (int) $group['id'];
    } else {
        array_splice($siblings, $at, 0, array((int) $group['id']));
    }

    foreach ($siblings as $index => $id) {
        db("UPDATE ws_channel_groups SET sort = '" . ($index + 1) . "'"
            . (($id === (int) $group['id']) ? ", parent_id = '" . $parent_id . "', updated_at = '" . time() . "'" : '') . "
            WHERE id = '" . $id . "'");
    }

    ws_groups_forget();

    if ($moved) {
        ws_group_sync_tree($viewer, $group['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Takes a group away. What was in it - channels and groups - moves up to the
 * group it was in (or to the top); the access given on it ends, and the
 * memberships only that access gave with it.
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array ok, error
 */
function ws_group_remove($viewer, $group_id)
{
    if (!ws_can_manage_groups($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can arrange the groups of channels.'));
    }

    $group = ws_group($group_id);

    if (!$group) {
        return array('ok' => false, 'error' => lang('That group could not be found.'));
    }

    $id = (int) $group['id'];
    $parent_id = (int) $group['parent_id'];
    $channel_ids = array_map('intval', (array) db_values("SELECT id FROM ws_channels WHERE group_id = '" . $id . "'"));
    $tree_channels = ws_group_channel_ids(ws_group_tree_ids($id));

    db("UPDATE ws_channels SET group_id = '" . $parent_id . "' WHERE group_id = '" . $id . "'");
    db("UPDATE ws_channel_groups SET parent_id = '" . $parent_id . "' WHERE parent_id = '" . $id . "'");
    db("DELETE FROM ws_channel_group_access WHERE group_id = '" . $id . "'");
    db("DELETE FROM ws_channel_groups WHERE id = '" . $id . "'");

    ws_groups_forget();

    foreach (array_unique(array_merge($channel_ids, $tree_channels)) as $channel_id) {
        $channel = ws_channel($channel_id);

        if ($channel) {
            ws_group_sync_channel($viewer, $channel);
        }
    }

    log_activity(lang(array('string' => 'workspace channel group ({var:1}) was removed', 'vars' => $group['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '');
}

/**
 * The channels in these groups (the archived ones too: back from the archive
 * they are where they were).
 *
 * @param int[] $group_ids
 * @return int[]
 */
function ws_group_channel_ids($group_ids)
{
    $group_ids = array_values(array_filter(array_map('intval', (array) $group_ids)));

    if (empty($group_ids) || !ws_groups_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT id FROM ws_channels WHERE group_id IN (" . implode(',', $group_ids) . ")"));
}

/**
 * What access on a group reaches: the groups inside it and the channels in
 * all of them, the private ones counted apart.
 *
 * @param int $group_id
 * @return array groups, channels, private, archived
 */
function ws_group_reach($group_id)
{
    $tree = ws_group_tree_ids($group_id);
    $out = array('groups' => max(0, count($tree) - 1), 'channels' => 0, 'private' => 0, 'archived' => 0);

    if (empty($tree)) {
        return $out;
    }

    $row = db_item("SELECT COUNT(*) AS channels, SUM(kind = 'private') AS private, SUM(archived_at > 0) AS archived
        FROM ws_channels WHERE group_id IN (" . implode(',', $tree) . ")");

    if (is_array($row)) {
        $out['channels'] = (int) $row['channels'];
        $out['private'] = (int) $row['private'];
        $out['archived'] = (int) $row['archived'];
    }

    return $out;
}

/**
 * The people who hold access on the group itself (not on one above it).
 *
 * @param int $group_id
 * @return array[] id, name, granted_by, granted_at
 */
function ws_group_access_people($group_id)
{
    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_channel_group_access WHERE group_id = '" . (int) $group_id . "' ORDER BY granted_at, user_id") as $row) {
        $out[] = array(
            'id'         => (int) $row['user_id'],
            'name'       => ws_person_name($row['user_id']),
            'granted_by' => ws_person_name($row['granted_by']),
            'granted_at' => (int) $row['granted_at'],
        );
    }

    return $out;
}

/**
 * Who a channel's groups give access to, each with the nearest group that
 * does.
 *
 * @param int $group_id the channel's group
 * @return int[] user_id => group_id
 */
function ws_group_wanted($group_id)
{
    $chain = ws_group_chain($group_id);
    $wanted = array();

    if (empty($chain)) {
        return $wanted;
    }

    $team = array_flip(ws_team_ids());
    $rows = (array) db_items("SELECT group_id, user_id FROM ws_channel_group_access WHERE group_id IN (" . implode(',', $chain) . ")");

    // Nearest first: the chain runs from the channel's own group upwards.
    foreach ($chain as $id) {
        foreach ($rows as $row) {
            $user_id = (int) $row['user_id'];

            if (((int) $row['group_id'] === $id) && isset($team[$user_id]) && !isset($wanted[$user_id])) {
                $wanted[$user_id] = $id;
            }
        }
    }

    return $wanted;
}

/**
 * Brings a channel's members in step with the access its groups give: in
 * come those it gives who are not members, out go those only a group had
 * brought and no group brings any more. Somebody in the channel by
 * themselves is never taken out, nor its owner.
 *
 * @param array $viewer  who made the change, for the line in the channel
 * @param array $channel
 * @param int   $group_id the channel's group, if it is not saved yet
 * @return array added, removed (user ids)
 */
function ws_group_sync_channel($viewer, $channel, $group_id = null)
{
    $out = array('added' => array(), 'removed' => array());

    if (!ws_groups_ready() || !is_array($channel)) {
        return $out;
    }

    $group_id = ($group_id === null) ? (int) db_value("SELECT group_id FROM ws_channels WHERE id = '" . (int) $channel['id'] . "'") : (int) $group_id;
    $wanted = ws_group_wanted($group_id);
    $members = array();

    foreach ((array) db_items("SELECT user_id, role, via_group FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "'") as $row) {
        $members[(int) $row['user_id']] = $row;
    }

    // In: one line in the channel names them all, and each hears it as an
    // invitation.
    $in = array_diff(array_keys($wanted), array_keys($members));

    if (!empty($in)) {
        $added = ws_channel_add_members($viewer, $channel, $in, false);

        foreach ($added as $user_id) {
            db("UPDATE ws_channel_members SET via_group = '" . (int) $wanted[$user_id] . "'
                WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . (int) $user_id . "'");
        }

        $out['added'] = $added;
    }

    // Still wanted, but through another group now: the note follows.
    foreach ($members as $user_id => $row) {
        if (((int) $row['via_group'] > 0) && isset($wanted[$user_id]) && ((int) $row['via_group'] !== (int) $wanted[$user_id])) {
            db("UPDATE ws_channel_members SET via_group = '" . (int) $wanted[$user_id] . "'
                WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . (int) $user_id . "'");
        }
    }

    // Out: only those a group had brought, who no group brings now.
    $gone = array();

    foreach ($members as $user_id => $row) {
        if (((int) $row['via_group'] > 0) && !isset($wanted[$user_id]) && ((string) $row['role'] !== 'owner')) {
            $gone[] = (int) $user_id;
        }
    }

    if (!empty($gone)) {
        db("DELETE FROM ws_channel_members
            WHERE channel_id = '" . (int) $channel['id'] . "' AND via_group > 0 AND role <> 'owner'
            AND user_id IN (" . implode(',', $gone) . ")");

        ws_channel_membership_forget();
        ws_channel_folder_access($channel, $gone, false);
        $out['removed'] = $gone;
    }

    $name = function ($ids) {
        return implode(', ', array_map(function ($id) { return '<@user:' . (int) $id . '>'; }, $ids));
    };

    if (!empty($out['added'])) {
        ws_message_system($channel['id'], lang(array(
            'string' => 'Access on the group “{var:1}” brought {var:2} into this channel.',
            'vars'   => array(ws_group_path($wanted[$out['added'][0]]), $name($out['added'])),
        )));
    }

    if (!empty($out['removed'])) {
        ws_message_system($channel['id'], lang(array(
            'string' => '{var:1} left this channel: the access that brought them in has ended.',
            'vars'   => array($name($out['removed'])),
        )));
    }

    return $out;
}

/**
 * Every channel in the group and the groups inside it, in step again.
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array added, removed (memberships)
 */
function ws_group_sync_tree($viewer, $group_id)
{
    $out = array('added' => 0, 'removed' => 0);

    foreach (ws_group_channel_ids(ws_group_tree_ids($group_id)) as $channel_id) {
        $channel = ws_channel($channel_id);

        if ($channel) {
            $result = ws_group_sync_channel($viewer, $channel);
            $out['added'] += count($result['added']);
            $out['removed'] += count($result['removed']);
        }
    }

    return $out;
}

/**
 * How many memberships access on the group would open for these people, for
 * the question before it is given.
 *
 * @param int   $group_id
 * @param int[] $user_ids
 * @return array reach (ws_group_reach()), joining
 */
function ws_group_access_preview($group_id, $user_ids)
{
    $user_ids = array_values(array_intersect(array_unique(array_map('intval', (array) $user_ids)), ws_team_ids()));
    $channels = ws_group_channel_ids(ws_group_tree_ids($group_id));
    $joining = 0;

    if (!empty($user_ids) && !empty($channels)) {
        $held = (int) db_value("SELECT COUNT(*) FROM ws_channel_members
            WHERE channel_id IN (" . implode(',', $channels) . ") AND user_id IN (" . implode(',', $user_ids) . ")");
        $joining = max(0, count($user_ids) * count($channels) - $held);
    }

    return array('reach' => ws_group_reach($group_id), 'joining' => $joining);
}

/**
 * Gives these people access on the group: they become members of every
 * channel in it and in the groups inside it.
 *
 * @param array $viewer
 * @param int   $group_id
 * @param int[] $user_ids
 * @return array ok, error, added (memberships)
 */
function ws_group_access_grant($viewer, $group_id, $user_ids)
{
    if (!ws_can_manage_groups($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can give access on a group.'), 'added' => 0);
    }

    $group = ws_group($group_id);

    if (!$group) {
        return array('ok' => false, 'error' => lang('That group could not be found.'), 'added' => 0);
    }

    $user_ids = array_values(array_intersect(array_unique(array_map('intval', (array) $user_ids)), ws_team_ids()));

    if (empty($user_ids)) {
        return array('ok' => false, 'error' => lang('Choose who gets the access.'), 'added' => 0);
    }

    $now = time();

    foreach ($user_ids as $user_id) {
        db("INSERT IGNORE INTO ws_channel_group_access (group_id, user_id, granted_by, granted_at)
            VALUES ('" . (int) $group['id'] . "', '" . $user_id . "', '" . (int) $viewer['id'] . "', '" . $now . "')");
    }

    $result = ws_group_sync_tree($viewer, $group['id']);

    log_activity(lang(array('string' => 'access on the workspace channel group ({var:1}) was given to {var:2}', 'vars' => array($group['name'], implode(', ', array_map('ws_person_name', $user_ids))))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'added' => $result['added']);
}

/**
 * Takes the access on the group back from these people; the memberships only
 * it had given end with it.
 *
 * @param array $viewer
 * @param int   $group_id
 * @param int[] $user_ids
 * @return array ok, error, removed (memberships)
 */
function ws_group_access_revoke($viewer, $group_id, $user_ids)
{
    if (!ws_can_manage_groups($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can give access on a group.'), 'removed' => 0);
    }

    $group = ws_group($group_id);

    if (!$group) {
        return array('ok' => false, 'error' => lang('That group could not be found.'), 'removed' => 0);
    }

    $user_ids = array_values(array_filter(array_unique(array_map('intval', (array) $user_ids))));

    if (empty($user_ids)) {
        return array('ok' => true, 'error' => '', 'removed' => 0);
    }

    db("DELETE FROM ws_channel_group_access WHERE group_id = '" . (int) $group['id'] . "' AND user_id IN (" . implode(',', $user_ids) . ")");

    $result = ws_group_sync_tree($viewer, $group['id']);

    log_activity(lang(array('string' => 'access on the workspace channel group ({var:1}) was taken from {var:2}', 'vars' => array($group['name'], implode(', ', array_map('ws_person_name', $user_ids))))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'removed' => $result['removed']);
}

/**
 * May this person move the channel into a group or out of one? Staff who may
 * change the channel: a private one only from inside it.
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_can_move_channel_group($viewer, $channel)
{
    // A room with a guest in it stays out of the groups: a group brings its
    // people into the channels in it.
    return ws_can_manage_groups($viewer) && ws_can_manage_channel($viewer, $channel)
        && in_array((string) ($channel['kind'] ?? ''), array('public', 'private'), true);
}

/**
 * Moves a channel into a group (0: out of every group). Asked with $preview,
 * it only says who would come in and who would go, for the question before
 * the move.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $group_id
 * @param bool  $preview
 * @return array ok, error, joining, leaving (names), path
 */
function ws_channel_move_group($viewer, $channel, $group_id, $preview = false)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'joining' => array(), 'leaving' => array(), 'path' => '');
    };

    if (!ws_can_move_channel_group($viewer, $channel)) {
        return $fail(($channel['kind'] === 'private')
            ? lang('A private channel is moved by staff who are in it.')
            : lang('Only staff can arrange the groups of channels.'));
    }

    $group_id = max(0, (int) $group_id);

    if (($group_id > 0) && !ws_group($group_id)) {
        return $fail(lang('That group could not be found.'));
    }

    $wanted = ws_group_wanted($group_id);
    $members = array();

    foreach ((array) db_items("SELECT user_id, role, via_group FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "'") as $row) {
        $members[(int) $row['user_id']] = $row;
    }

    $joining = array_values(array_diff(array_keys($wanted), array_keys($members)));
    $leaving = array();

    foreach ($members as $user_id => $row) {
        if (((int) $row['via_group'] > 0) && !isset($wanted[$user_id]) && ((string) $row['role'] !== 'owner')) {
            $leaving[] = (int) $user_id;
        }
    }

    $names = function ($ids) {
        return array_values(array_map('ws_person_name', $ids));
    };

    if ($preview) {
        return array('ok' => true, 'error' => '', 'joining' => $names($joining), 'leaving' => $names($leaving), 'path' => ($group_id > 0) ? ws_group_path($group_id) : '');
    }

    db("UPDATE ws_channels SET group_id = '" . $group_id . "' WHERE id = '" . (int) $channel['id'] . "'");

    ws_group_sync_channel($viewer, $channel, $group_id);

    return array('ok' => true, 'error' => '', 'joining' => $names($joining), 'leaving' => $names($leaving), 'path' => ($group_id > 0) ? ws_group_path($group_id) : '');
}

/**
 * The words the screen needs for colours and groups.
 *
 * @return array
 */
function ws_groups_js_strings()
{
    return array(
        'color'                 => lang('Colour'),
        'color_none'            => lang('No colour'),
        'color_help'            => lang('The channel\'s colour in the list, the same for everybody.'),
        'grp_new'               => lang('New group'),
        'grp_new_inside'        => lang('New group inside'),
        'grp_edit'              => lang('Change the group'),
        'grp_name'              => lang('Name of the group'),
        'grp_parent'            => lang('Inside'),
        'grp_top'               => lang('At the top'),
        'grp_saved'             => lang('The group is saved.'),
        'grp_access'            => lang('Access on the group'),
        'grp_access_help'       => lang('Whoever holds access on a group becomes a member of every channel in it and in the groups inside it, the private ones too, and of the channels moved into them later.'),
        'grp_access_none'       => lang('Nobody holds access on this group itself.'),
        'grp_access_above'      => lang('Access given on a group above this one reaches here too.'),
        'grp_access_give'       => lang('Give access'),
        'grp_access_take'       => lang('Take the access back'),
        'grp_access_given_by'   => ws_js_template('given by {var:1}', 1),
        'grp_access_confirm'    => ws_js_template('Give {var:1} access on “{var:2}”? It reaches {var:3} groups inside it and {var:4} channels ({var:5} of them private): {var:6} new memberships. Channels moved into these groups later are opened to them too.', 6),
        'grp_access_take_confirm' => ws_js_template('Take the access on “{var:1}” back from {var:2}? They leave the channels only this access had brought them into.', 2),
        'grp_access_done'       => ws_js_template('Access given: {var:1} new memberships.', 1),
        'grp_access_taken'      => ws_js_template('Access taken back: {var:1} memberships ended.', 1),
        'grp_move_up'           => lang('Move up'),
        'grp_move_down'         => lang('Move down'),
        'grp_move_top'          => lang('Move to the top level'),
        'grp_remove'            => lang('Take the group away'),
        'grp_remove_confirm'    => ws_js_template('Take the group “{var:1}” away? Its channels and groups move up to where it was; the access given on it ends, and the memberships only it had given.', 1),
        'grp_into'              => ws_js_template('Move the group “{var:1}” inside “{var:2}”? With it go {var:3} groups and {var:4} channels inside it; access given on “{var:2}” reaches them too.', 4),
        'grp_to_top'            => ws_js_template('Move the group “{var:1}” to the top level?', 1),
        'grp_reorder'           => ws_js_template('Move the group “{var:1}” to this place in “{var:2}”?', 2),
        'grp_channel_into'      => ws_js_template('Move #{var:1} into the group “{var:2}”?', 2),
        'grp_channel_out'       => ws_js_template('Take #{var:1} out of its group “{var:2}”?', 2),
        'grp_channel_joining'   => ws_js_template('Access on the group brings these people into the channel: {var:1}.', 1),
        'grp_channel_leaving'   => ws_js_template('These people leave the channel, since only the group they leave had brought them: {var:1}.', 1),
        'grp_channel_private'   => lang('The channel is private: whoever holds access on the group will read it.'),
        'grp_move_channel'      => lang('Move to a group'),
        'grp_no_group'          => lang('In no group'),
        'grp_empty'             => lang('No channels in this group yet. Drag one here.'),
        'grp_collapse'          => lang('Close the group'),
        'grp_expand'            => lang('Open the group'),
        'grp_drop_here'         => lang('Drop here'),
        'grp_levels'            => ws_js_template('Groups go at most {var:1} levels deep.', 1),
        'grp_reach'             => ws_js_template('Access here reaches {var:1} groups inside it and {var:2} channels, {var:3} of them private.', 3),
        'grp_access_pick'       => lang('Choose who gets the access.'),
        'grp_new_channel_in'    => ws_js_template('The channel is made in the group “{var:1}”: whoever holds access on the group is let in.', 1),
    );
}
