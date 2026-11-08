<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - note groups in the external API and MCP
 * (includes/workspace/note_groups.php): the owner of the application lists
 * the groups they keep and the ones shared with them, makes, renames,
 * recolours, orders and deletes their own, and keeps a note in a group or in
 * none. Every write runs the panel's own check first, so a rehearsal meets
 * the same refusals. Sharing a group stays on the notes screen, as sharing a
 * note does.
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
 * The note group routes, merged into ws_api_routes(). Reading, writing and
 * placing are tools, as for notes; deleting is not.
 *
 * @return array[]
 */
function ws_note_group_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $fields = array(
        array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => WS_NOTE_GROUP_NAME_MAX, 'description' => 'What the group is called in My Notes; spaces at the ends are dropped.'),
        array('name' => 'color', 'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 255, 'description' => 'The place of its colour in the workspace palette, as the group menu offers it; a place beyond the palette starts over from its first colour.'),
    );
    $create_fields = $fields;
    $create_fields[0]['required'] = true;
    $errors = ' A missing or empty name answers 422 validation_failed (field name); the database not updated yet 503 service_unavailable.';

    return array(
        array(
            'id'          => 'workspace.note_groups.list',
            'method'      => 'GET',
            'path'        => '/workspace/note-groups',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_note_groups_list',
            'returns'     => array('list' => 'WorkspaceNoteGroup'),
            'summary'     => 'The owner\'s note groups',
            'description' => 'The folders of My Notes: the groups of the owner of the application in their order (access owner), then the groups colleagues shared with them by name or in a channel they may read (access edit or view), by name. A person keeps at most ' . WS_NOTE_GROUPS_MAX . ' groups of their own; all come in one page. note_ids is null in the listing; GET /workspace/note-groups/{id} carries it. A note object\'s group_id says which group the owner keeps that note in. Sharing a group is done on the notes screen.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'mine', 'shared'), 'description' => 'mine keeps the owner\'s own groups, shared the ones shared with them; all by default.'),
            ),
        ),

        array(
            'id'          => 'workspace.note_groups.get',
            'method'      => 'GET',
            'path'        => '/workspace/note-groups/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_note_groups_get',
            'returns'     => 'WorkspaceNoteGroup',
            'summary'     => 'One note group',
            'description' => 'A group of the owner of the application or one shared with them, with note_ids: the notes in it, in the group\'s order, that the owner may read (GET /workspace/notes/{id} reads each). Any other group is 404.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.note_groups.create',
            'method'      => 'POST',
            'path'        => '/workspace/note-groups',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_note_groups_create',
            'dry_run'     => true,
            'status'      => 201,
            'returns'     => 'WorkspaceNoteGroup',
            'summary'     => 'Make a note group',
            'description' => 'Makes a group of the owner of the application, last in their order, as New group on the notes screen does; nobody else sees it until the owner shares it there. A person keeps at most ' . WS_NOTE_GROUPS_MAX . ' (409 limit_reached).' . $errors,
            'params'      => $create_fields,
        ),

        array(
            'id'          => 'workspace.note_groups.order',
            'method'      => 'POST',
            'path'        => '/workspace/note-groups/order',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_note_groups_order',
            'dry_run'     => true,
            'returns'     => array('list' => 'WorkspaceNoteGroup'),
            'summary'     => 'Order the note groups',
            'description' => 'Puts the owner\'s own groups in the order of group_ids. A group of theirs left out keeps its place after the ones named, so an order read a while ago loses nothing; an id that is not one of their groups is passed over. The answer is the owner\'s groups in their new order.',
            'params'      => array(
                array('name' => 'group_ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => WS_NOTE_GROUPS_MAX, 'required' => true, 'description' => 'The ids of the owner\'s groups, first to last.'),
            ),
        ),

        array(
            'id'           => 'workspace.note_groups.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/note-groups/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_note_groups_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceNoteGroup',
            'summary'      => 'Rename or recolour a note group',
            'description'  => 'Changes only the fields sent on a group of the owner of the application. Only the owner of a group changes it; a group shared with them, or anybody else\'s, is 404. PATCH is accepted beside POST.' . $errors,
            'params'       => array_merge(array($id), $fields),
        ),

        array(
            'id'          => 'workspace.note_groups.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/note-groups/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_note_groups_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Delete a note group',
            'description' => 'Deletes a group of the owner of the application, as the group menu does: its notes go back to no group and none is deleted, and its shares are taken back, so the notes it brought to colleagues and channels leave them. Only the owner deletes a group; any other is 404. It cannot be undone, so it is not offered to assistants as a tool. POST /workspace/note-groups/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.note_groups.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/note-groups/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_note_groups_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Delete a note group (POST)',
            'description' => 'The same as DELETE /workspace/note-groups/{id}, for servers and clients that refuse DELETE.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.notes.place',
            'method'      => 'POST',
            'path'        => '/workspace/notes/{id}/group',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_notes_place',
            'dry_run'     => true,
            'returns'     => 'WorkspaceNote',
            'summary'     => 'Keep a note in a group',
            'description' => 'Keeps a note the owner of the application wrote or has shared with them by name in one of their groups, at a place in its order, or in no group (group_id 0), as Move to group on the notes screen does; a note is in one group of a person at a time. Into a group shared with them to change, or one of theirs they share, only their own notes go (403 forbidden), and the owner of a group moves any note in it. A note they cannot read is 404; a group they do not reach is 422 (field group_id). The answer is the note with its new group_id.',
            'params'      => array(
                $id,
                array('name' => 'group_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'required' => true, 'description' => 'The group, 0 for none.'),
                array('name' => 'position', 'in' => 'body', 'type' => 'int', 'min' => -1, 'description' => 'Its place in the group: 0 the first, -1 (the default) the last.'),
            ),
        ),
    );
}

// What ws_api_note_group_present() returns.
function ws_api_note_group_schema()
{
    return array(
        'id'         => 'integer',
        'name'       => 'string',
        'color'      => 'integer',
        'hex'        => 'string',
        'position'   => 'integer',
        'locked'     => 'boolean',
        'note_count' => 'integer',
        'access'     => 'string',
        'shared'     => 'boolean',
        'owner'      => 'WorkspacePerson?',
        'note_ids'   => 'integer[]?',
    );
}

/**
 * A group as the API shows it.
 *
 * @param array      $group    the row, with notes (its count)
 * @param string     $access   owner | edit | view
 * @param bool       $shared   anybody beside its owner has it
 * @param array|null $note_ids its notes in order, only for one group
 * @param array      $people   ws_people(), when at hand, for the owner's name
 * @return array
 */
function ws_api_note_group_present($group, $access, $shared, $note_ids = null, $people = array())
{
    $brief = ws_note_group_present($group);

    return array(
        'id'         => $brief['id'],
        'name'       => $brief['name'],
        'color'      => $brief['color'],
        'hex'        => $brief['hex'],
        'position'   => $brief['sort'],
        'locked'     => $brief['locked'],
        'note_count' => $brief['count'],
        'access'     => (string) $access,
        'shared'     => (bool) $shared,
        'owner'      => ws_api_person_ref($group['user_id'] ?? 0, $people),
        'note_ids'   => ($note_ids === null) ? null : array_values(array_map('intval', (array) $note_ids)),
    );
}

/**
 * How the API answers a refusal of ws_note_group_save_check(): a name it
 * turned down, else the most groups one person keeps.
 *
 * @param array $check error, field
 * @return array status, code, field
 */
function ws_api_note_group_refusal($check)
{
    if ((string) ($check['field'] ?? '') === 'name') {
        return array(422, 'validation_failed', 'name');
    }

    return array(409, 'limit_reached', null);
}

/**
 * How the API answers a refusal of ws_note_place_check(): a note the owner
 * cannot place is not theirs to see; a group refusal is the gate of a
 * shared group (a group not reached was answered before the check).
 *
 * @param array $check error, field
 * @return array status, code, field
 */
function ws_api_note_place_refusal($check)
{
    if ((string) ($check['field'] ?? '') === 'group_id') {
        return array(403, 'forbidden', 'group_id');
    }

    return array(404, 'not_found', 'note_id');
}

/**
 * Answers a refusal (ws_api_note_group_refusal(), ws_api_note_place_refusal()).
 *
 * @param array  $how   status, code, field
 * @param string $error the panel's sentence
 */
function ws_api_note_group_fail($how, $error)
{
    if ($how[1] === 'validation_failed') {
        api_fail_validation($error, $how[2]);
    }

    api_fail($how[0], $how[1], $error, $how[2]);
}

// 503 until the database step that adds note groups ran.
function ws_api_note_groups_ready_or_503()
{
    if (!ws_note_groups_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * The group id a note object carries: the group the person keeps the note
 * in (ws_note_places_add()), null for none.
 *
 * @param array $note with group_id
 * @return int|null
 */
function ws_api_note_group_id($note)
{
    $id = (int) ($note['group_id'] ?? 0);

    return ($id > 0) ? $id : null;
}

/**
 * The ids of groups shared with the person by name or in a channel the
 * application reads, without their own.
 *
 * @param array $viewer
 * @return int[]
 */
function ws_api_note_groups_shared_ids($viewer)
{
    $me = (int) $viewer['id'];
    $where = array("s.user_id = '" . $me . "'");
    $channels = ws_api_readable_channel_ids($viewer);

    if ($channels) {
        $where[] = 's.channel_id IN (' . implode(',', array_map('intval', $channels)) . ')';
    }

    $ids = array();

    foreach ((array) db_items("SELECT DISTINCT s.group_id FROM ws_note_group_shares s
        INNER JOIN ws_note_groups g ON g.id = s.group_id
        WHERE (" . implode(' OR ', $where) . ") AND g.user_id <> '" . $me . "'
        LIMIT 1000") as $row) {
        $ids[] = (int) $row['group_id'];
    }

    return $ids;
}

/**
 * Groups by id with their note counts and shares, each with the access the
 * person has to it; groups they do not reach are left out.
 *
 * @param array $viewer
 * @param int[] $ids
 * @return array[] each: group (row with notes), access, shared
 */
function ws_api_note_groups_reached($viewer, $ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $ids)));

    if (!$ids) {
        return array();
    }

    $list = implode(',', $ids);
    $shares = array();

    foreach ((array) db_items("SELECT * FROM ws_note_group_shares WHERE group_id IN (" . $list . ")") as $share) {
        $shares[(int) $share['group_id']][] = $share;
    }

    $channels = ws_api_readable_channel_ids($viewer);
    $out = array();

    foreach ((array) db_items("SELECT g.*, (SELECT COUNT(*) FROM ws_note_places p WHERE p.group_id = g.id) AS notes
        FROM ws_note_groups g WHERE g.id IN (" . $list . ") ORDER BY g.name, g.id") as $group) {
        $own = $shares[(int) $group['id']] ?? array();
        $access = ws_note_group_reach_of((int) $viewer['id'], $group, $own, $channels);

        if ($access !== '') {
            $out[(int) $group['id']] = array('group' => $group, 'access' => $access, 'shared' => !empty($own));
        }
    }

    return $out;
}

/**
 * The owner's own groups as the API shows them, in their order.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_api_note_groups_own($viewer)
{
    $groups = ws_note_groups($viewer);
    $shared = array();

    if ($groups) {
        $ids = array();

        foreach ($groups as $group) {
            $ids[] = (int) $group['id'];
        }

        foreach ((array) db_items("SELECT DISTINCT group_id FROM ws_note_group_shares WHERE group_id IN (" . implode(',', $ids) . ")") as $row) {
            $shared[(int) $row['group_id']] = true;
        }
    }

    $out = array();

    foreach ($groups as $group) {
        $row = array(
            'id' => $group['id'], 'user_id' => (int) $viewer['id'], 'name' => $group['name'], 'color' => $group['color'],
            'sort' => $group['sort'], 'locked' => $group['locked'], 'notes' => $group['count'],
        );
        $out[] = ws_api_note_group_present($row, 'owner', isset($shared[(int) $group['id']]));
    }

    return $out;
}

/**
 * A group the person reaches, as the API shows it with its notes, or 404.
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array
 */
function ws_api_note_group_or_404($viewer, $group_id)
{
    ws_api_note_groups_ready_or_503();

    $reached = ws_api_note_groups_reached($viewer, array((int) $group_id));
    $entry = $reached[(int) $group_id] ?? null;

    if (!$entry) {
        api_fail_not_found(lang('Group'));
    }

    $note_ids = array();

    foreach ((array) db_items("SELECT note_id FROM ws_note_places WHERE group_id = '" . (int) $group_id . "' ORDER BY sort, created_at, note_id") as $row) {
        $note = ws_note_for($viewer, (int) $row['note_id'], 'view');

        if ($note && ws_api_note_reachable($viewer, $note)) {
            $note_ids[] = (int) $note['id'];
        }
    }

    return ws_api_note_group_present($entry['group'], $entry['access'], $entry['shared'], $note_ids);
}

/**
 * One of the owner's own groups (the row), or 404: only the owner of a
 * group renames, recolours or deletes it.
 *
 * @param array $viewer
 * @param int   $group_id
 * @return array
 */
function ws_api_note_group_owned_or_404($viewer, $group_id)
{
    ws_api_note_groups_ready_or_503();

    $group = ws_note_group_for($viewer, (int) $group_id);

    if (!$group) {
        api_fail_not_found(lang('Group'));
    }

    return $group;
}

/**
 * The fields of a group write that were sent.
 *
 * @param array $params
 * @return array
 */
function ws_api_note_group_input($params)
{
    $data = array();

    foreach (array('name', 'color') as $field) {
        if (array_key_exists($field, $params) && ($params[$field] !== null)) {
            $data[$field] = $params[$field];
        }
    }

    return $data;
}

function ws_api_note_groups_list($params)
{
    $viewer = ws_api_viewer();
    $scope = (string) ($params['scope'] ?? 'all');
    $out = array();

    if (ws_note_groups_ready()) {
        if ($scope !== 'shared') {
            $out = ws_api_note_groups_own($viewer);
        }

        if ($scope !== 'mine') {
            foreach (ws_api_note_groups_reached($viewer, ws_api_note_groups_shared_ids($viewer)) as $entry) {
                $out[] = ws_api_note_group_present($entry['group'], $entry['access'], $entry['shared']);
            }
        }
    }

    api_ok_list($out, max(count($out), 1), null, count($out));
}

function ws_api_note_groups_get($params)
{
    $viewer = ws_api_viewer();

    api_ok(ws_api_note_group_or_404($viewer, $params['id']));
}

function ws_api_note_groups_create($params)
{
    $viewer = ws_api_viewer();

    ws_api_note_groups_ready_or_503();

    $data = ws_api_note_group_input($params);
    $checked = ws_note_group_save_check($viewer, $data);

    if (!$checked['ok']) {
        ws_api_note_group_fail(ws_api_note_group_refusal($checked), $checked['error']);
    }

    api_dry_run_stop('created', 'workspace_note_group', array('name' => $checked['name'], 'color' => $checked['color']));

    $result = ws_note_group_save($viewer, $data);

    if (!$result['ok']) {
        ws_api_note_group_fail(ws_api_note_group_refusal($result), $result['error']);
    }

    api_ok(ws_api_note_group_or_404($viewer, $result['group_id']), 201);
}

function ws_api_note_groups_update($params)
{
    $viewer = ws_api_viewer();
    $group = ws_api_note_group_owned_or_404($viewer, $params['id']);
    $data = ws_api_note_group_input($params);
    $checked = ws_note_group_save_check($viewer, $data, $group);

    if (!$checked['ok']) {
        ws_api_note_group_fail(ws_api_note_group_refusal($checked), $checked['error']);
    }

    api_dry_run_stop('updated', 'workspace_note_group', array('id' => (int) $group['id'], 'fields' => array_keys($data)));

    $result = ws_note_group_save($viewer, $data, $group);

    if (!$result['ok']) {
        ws_api_note_group_fail(ws_api_note_group_refusal($result), $result['error']);
    }

    api_ok(ws_api_note_group_or_404($viewer, $group['id']));
}

function ws_api_note_groups_order($params)
{
    $viewer = ws_api_viewer();

    ws_api_note_groups_ready_or_503();

    $ids = array_values(array_map('intval', (array) ($params['group_ids'] ?? array())));

    api_dry_run_stop('ordered', 'workspace_note_group', array('group_ids' => $ids));

    $result = ws_note_groups_order($viewer, $ids);

    if (!$result['ok']) {
        api_fail(503, 'service_unavailable', $result['error']);
    }

    $out = ws_api_note_groups_own($viewer);

    api_ok_list($out, max(count($out), 1), null, count($out));
}

function ws_api_note_groups_delete($params)
{
    $viewer = ws_api_viewer();
    $group = ws_api_note_group_owned_or_404($viewer, $params['id']);

    api_dry_run_stop('deleted', 'workspace_note_group', array('id' => (int) $group['id']));

    $result = ws_note_group_delete($viewer, $group);

    if (!$result['ok']) {
        api_fail_not_found(lang('Group'));
    }

    api_ok(array('id' => (int) $group['id'], 'deleted' => true));
}

function ws_api_notes_place($params)
{
    $viewer = ws_api_viewer();

    ws_api_note_groups_ready_or_503();

    $note = ws_api_note_or_404($viewer, $params['id']);
    ws_api_note_locked_stop($viewer, $note);
    $group_id = (int) ($params['group_id'] ?? 0);
    $position = isset($params['position']) ? (int) $params['position'] : -1;

    // A group the owner does not reach is a wrong value, not a refusal of
    // the shared group's gate.
    if (($group_id > 0) && !ws_note_group_reached($viewer, $group_id)) {
        api_fail_validation(lang('That group could not be found.'), 'group_id');
    }

    $checked = ws_note_place_check($viewer, $note, $group_id);

    if (!$checked['ok']) {
        ws_api_note_group_fail(ws_api_note_place_refusal($checked), $checked['error']);
    }

    api_dry_run_stop('placed', 'workspace_note', array('id' => (int) $note['id'], 'group_id' => $group_id, 'position' => $position));

    $result = ws_note_place($viewer, $note, $group_id, $position);

    if (!$result['ok']) {
        ws_api_note_group_fail(ws_api_note_place_refusal($result), $result['error']);
    }

    api_ok(ws_api_note_present($viewer, ws_note_present($viewer, ws_note_for($viewer, $note['id'], 'view'))));
}
