<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a workflow table shared with the team. Its owner gives a person
 * a view of the table or the right to change its columns and rows with them;
 * a channel gets a view of it for the people who read the channel. One row of
 * ws_wtable_shares per table and person or channel (2026.11.0, 11.0.110), as
 * ws_flow_shares is for a flow. ws_wtable_access() reads them.
 *
 * Only the owner manages the shares, as only the owner deletes the table: a
 * person shared at edit changes the columns and rows but does not hand the
 * table on. Being an administrator gives nothing here, as for a note. A
 * table whose owner is gone (deleted, excluded, without the workspace right)
 * keeps its shares, and they give nothing until the owner is back.
 *
 * Sharing writes a Site Log line, and a person a table is newly shared with
 * is told in their bell (wtable_shared), the sentence naming the table kept
 * beside the line as for a flow shared (ws_scheduled_notices).
 *
 * A flow's box that reads or writes a table asks ws_wtable_flow_gate(): a
 * table whose owner is gone pauses the box until the owner is back, while a
 * table the flow's owner does not reach far enough refuses it.
 *
 * Results are codes, as in wtables.php, for the panel and the API to word.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most people and channels one table is shared with. */
define('WS_WTABLE_MAX_SHARES', 200);

/* ---------------------------------------------------------------------------
   Rules (pure)
   --------------------------------------------------------------------------- */

/**
 * The levels a share gives, lowest first.
 *
 * @return array level => label
 */
function ws_wtable_share_levels()
{
    return array(
        'view' => lang('Can view'),
        'edit' => lang('Can edit'),
    );
}

/**
 * What is wrong with what a share names, before anything is read: '' when
 * nothing is, else the field: target_type (a person or a channel), target_id,
 * level, or channel_level (a channel is shared at view only).
 *
 * @param mixed $target_type
 * @param mixed $target_id
 * @param mixed $level
 * @return string
 */
function ws_wtable_share_input_problem($target_type, $target_id, $level)
{
    if (!is_string($target_type) || !in_array($target_type, array('user', 'channel'), true)) {
        return 'target_type';
    }

    if (!is_numeric($target_id) || ((int) $target_id <= 0) || ((string) (int) $target_id !== (string) $target_id)) {
        return 'target_id';
    }

    if (!is_string($level) || !isset(ws_wtable_share_levels()[$level])) {
        return 'level';
    }

    if (($target_type === 'channel') && ($level !== 'view')) {
        return 'channel_level';
    }

    return '';
}

/**
 * Whether a share may be made, from what is known about it:
 *   access   string the person's access to the table (ws_wtable_access())
 *   problem  string ws_wtable_share_input_problem()
 *   type     string user | channel
 *   is_owner bool   the person named owns the table
 *   member   bool   the person named is in the team
 *   readable bool   the channel named is a public or private one the owner reads
 *   existing bool   the table is shared with them already
 *   count    int    the table's shares now
 * A table the person does not reach is not found; one they reach but do
 * not own is theirs to look at, not to share.
 *
 * @param array $facts
 * @return array|null a refusal (ws_wtable_refusal()), null when allowed
 */
function ws_wtable_share_set_refusal($facts)
{
    $access = (string) ($facts['access'] ?? '');

    if ($access === '') {
        return ws_wtable_refusal('not_found', '', 404);
    }

    if (!ws_wtable_may($access, 'manage')) {
        return ws_wtable_refusal('forbidden', '', 403);
    }

    $problem = (string) ($facts['problem'] ?? '');

    if ($problem !== '') {
        return ws_wtable_refusal(($problem === 'channel_level') ? 'channel_level' : $problem . '_invalid', ($problem === 'channel_level') ? 'level' : $problem);
    }

    if (($facts['type'] ?? '') === 'user') {
        if (!empty($facts['is_owner'])) {
            return ws_wtable_refusal('target_is_owner', 'target_id');
        }

        if (empty($facts['member'])) {
            return ws_wtable_refusal('target_not_member', 'target_id');
        }
    } elseif (empty($facts['readable'])) {
        return ws_wtable_refusal('channel_unreadable', 'target_id');
    }

    if (empty($facts['existing']) && ((int) ($facts['count'] ?? 0) >= WS_WTABLE_MAX_SHARES)) {
        return ws_wtable_refusal('too_many_shares', '', 409);
    }

    return null;
}

/**
 * What a flow's box may do with a table now:
 *   present bool   the table's owner is in the team still
 *   access  string the flow owner's access to the table (ws_wtable_access())
 *   need    string view to read it, edit to write it
 * A table whose owner is gone pauses the box ('paused'): nobody reaches the
 * table meanwhile, and the flow goes on once the owner is back. A table the
 * flow's owner does not reach far enough refuses it ('refused').
 *
 * @param array $facts
 * @return string '' | paused | refused
 */
function ws_wtable_flow_gate($facts)
{
    if (empty($facts['present'])) {
        return 'paused';
    }

    $need = ((string) ($facts['need'] ?? '') === 'edit') ? 'edit' : 'view';

    return (ws_wtable_access_rank((string) ($facts['access'] ?? '')) >= ws_wtable_access_rank($need)) ? '' : 'refused';
}

/* ---------------------------------------------------------------------------
   Reading the shares
   --------------------------------------------------------------------------- */

/**
 * The shares of one table, oldest first.
 *
 * @param int $table_id
 * @return array[] ws_wtable_shares rows
 */
function ws_wtable_share_rows($table_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_wtable_shares WHERE table_id = '" . (int) $table_id . "' ORDER BY id");
}

/**
 * One share of a table.
 *
 * @param int $table_id
 * @param int $share_id
 * @return array|null
 */
function ws_wtable_share($table_id, $share_id)
{
    if (!ws_wtables_ready() || ((int) $share_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_shares WHERE id = '" . (int) $share_id . "' AND table_id = '" . (int) $table_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The shares of a table as the person may see them, oldest first, each with
 * name (the person's or the channel's) and gives (what it gives now: a
 * person who left the team nothing). Whoever reaches the table sees who else
 * does; a channel they do not read is left out for them, and shown nameless
 * to the owner, who manages it.
 *
 * @param array      $viewer
 * @param array|null $table
 * @return array[]|null share rows with name and gives; null when the table is not reached
 */
function ws_wtable_shares_list($viewer, $table)
{
    $access = ws_wtable_access($viewer, $table);

    if ($access === '') {
        return null;
    }

    $manage = ws_wtable_may($access, 'manage');
    $rows = ws_wtable_share_rows($table['id']);
    $user_ids = array();

    foreach ($rows as $row) {
        if ((string) $row['target_type'] === 'user') {
            $user_ids[] = (int) $row['target_id'];
        }
    }

    $people = empty($user_ids) ? array() : ws_people(array_unique($user_ids));
    $out = array();

    foreach ($rows as $row) {
        $type = (string) $row['target_type'];

        if ($type === 'user') {
            $person = $people[(int) $row['target_id']] ?? null;
            $name = $person ? (string) $person['name'] : '';
            $gives = ws_is_team_member((int) $row['target_id']) ? ws_wtable_share_gives('user', (string) $row['level']) : '';
        } elseif ($type === 'channel') {
            $channel = ws_channel((int) $row['target_id']);
            $readable = ws_flow_share_channel_readable($viewer, $channel);

            if (!$readable && !$manage) {
                continue;
            }

            $name = $readable ? (string) $channel['name'] : '';
            $gives = $channel ? 'view' : '';
        } else {
            continue;
        }

        $out[] = $row + array('name' => $name, 'gives' => $gives);
    }

    return $out;
}

/**
 * What a flow's box may do with a table now (ws_wtable_flow_gate()), the
 * flow acting with its owner's rights.
 *
 * @param array  $owner_rights ws_rights_for_id() of the flow's owner
 * @param int    $table_id
 * @param string $need view | edit
 * @return string '' | paused | refused
 */
function ws_wtable_flow_check($owner_rights, $table_id, $need)
{
    $table = ws_wtable($table_id);

    if (!$table) {
        return 'refused';
    }

    return ws_wtable_flow_gate(array(
        'present' => ws_wtable_owner_present((int) $table['user_id']),
        'access'  => ws_wtable_access($owner_rights, $table),
        'need'    => $need,
    ));
}

/* ---------------------------------------------------------------------------
   Writing the shares
   --------------------------------------------------------------------------- */

/**
 * Whether the person may share the table with this person or channel at
 * this level: the checks of ws_wtable_share_set(), made on their own so that
 * the API's dry run meets the same refusals. Sharing again with the same
 * person or channel changes its level.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array      $data target_type, target_id, level (view when left out)
 * @return array ok, error, field, status, target_type, target_id, level, existing (the share made before, null for none)
 */
function ws_wtable_share_set_check($viewer, $table, $data)
{
    if (!ws_wtables_ready()) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    $data = is_array($data) ? $data : array();
    $type = $data['target_type'] ?? '';
    $target_id = $data['target_id'] ?? 0;
    $level = $data['level'] ?? 'view';
    $access = ws_wtable_access($viewer, $table);
    $problem = ws_wtable_share_input_problem($type, $target_id, $level);
    $facts = array('access' => $access, 'problem' => $problem, 'type' => $type);
    $existing = null;

    if (ws_wtable_may($access, 'manage') && ($problem === '')) {
        $target_id = (int) $target_id;
        $rows = ws_wtable_share_rows($table['id']);

        foreach ($rows as $share) {
            if (((string) $share['target_type'] === $type) && ((int) $share['target_id'] === $target_id)) {
                $existing = $share;
            }
        }

        $facts += array(
            'is_owner' => ($type === 'user') && ($target_id === (int) $table['user_id']),
            'member'   => ($type === 'user') && ws_is_team_member($target_id),
            'readable' => ($type === 'channel') && ws_flow_share_channel_readable($viewer, ws_channel($target_id)),
            'existing' => ($existing !== null),
            'count'    => count($rows),
        );
    }

    $refusal = ws_wtable_share_set_refusal($facts);

    if ($refusal !== null) {
        return $refusal;
    }

    return array(
        'ok'          => true,
        'error'       => '',
        'field'       => '',
        'status'      => 200,
        'target_type' => $type,
        'target_id'   => (int) $target_id,
        'level'       => $level,
        'existing'    => $existing,
    );
}

/**
 * Shares the table with a person or a channel, or changes the level of the
 * share made before. A person it is newly shared with is told in their bell.
 * Two requests that share with the same one at the same moment meet the
 * unique (table, kind, id): the second changes the level of the first's row.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array      $data see ws_wtable_share_set_check()
 * @return array ok, error, field, status (201 new, 200 changed), share_id, created
 */
function ws_wtable_share_set($viewer, $table, $data)
{
    $check = ws_wtable_share_set_check($viewer, $table, $data);

    if (!$check['ok']) {
        return $check + array('share_id' => 0, 'created' => false);
    }

    $table_id = (int) $table['id'];
    $type = $check['target_type'];
    $target_id = $check['target_id'];
    $level = $check['level'];
    $share_id = is_array($check['existing']) ? (int) $check['existing']['id'] : 0;
    $created = false;

    // Shared again at the level it has: nothing to write or to log.
    if (($share_id > 0) && ((string) $check['existing']['level'] === $level)) {
        return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200, 'share_id' => $share_id, 'created' => false);
    }

    if ($share_id === 0) {
        // Not db(): a failed statement there ends the request with an HTML
        // page, and the duplicate key of a share made meanwhile is an answer.
        $written = @mysqli_query(db::$con, "INSERT INTO ws_wtable_shares (table_id, target_type, target_id, level, created_by, created_at)
            VALUES ('" . $table_id . "', '" . e($type) . "', '" . $target_id . "', '" . e($level) . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

        if ($written) {
            $share_id = (int) mysqli_insert_id(db::$con);
            $created = ($share_id > 0);
        } elseif ((int) mysqli_errno(db::$con) === 1062) {
            $share_id = (int) db_value("SELECT id FROM ws_wtable_shares
                WHERE table_id = '" . $table_id . "' AND target_type = '" . e($type) . "' AND target_id = '" . $target_id . "'");
        }

        if ($share_id <= 0) {
            return ws_wtable_refusal('not_saved', '', 500) + array('share_id' => 0, 'created' => false);
        }
    }

    if (!$created) {
        db("UPDATE ws_wtable_shares SET level = '" . e($level) . "' WHERE id = '" . $share_id . "' AND table_id = '" . $table_id . "'");
    }

    $name = ws_flow_share_target_name($type, $target_id);
    $label = ws_wtable_share_levels()[$level];

    log_activity($created
        ? lang(array('string' => 'workspace table ({var:1}) was shared with {var:2}: {var:3}', 'vars' => array($table['name'], $name, $label)))
        : lang(array('string' => 'workspace table ({var:1}): the share with {var:2} was changed to {var:3}', 'vars' => array($table['name'], $name, $label))),
        (string) ($_SESSION['sessionusername'] ?? ''));

    if ($created && ($type === 'user')) {
        ws_wtable_share_tell($viewer, $table, $target_id, $level);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => $created ? 201 : 200, 'share_id' => $share_id, 'created' => $created);
}

/**
 * Tells a person a table was shared with them: a line of its own in their
 * bell ('separate': the inbox keys a line by its channel, message and task,
 * all 0 here, so a line still unread would otherwise take the sentence of
 * another table shared later), whose sentence names the table and what they
 * may do with it, kept beside the line as a flow shared is
 * (ws_flow_share_tell()).
 *
 * @param array  $viewer  who shared it
 * @param array  $table
 * @param int    $user_id
 * @param string $level
 * @return int the inbox row, 0 when nobody was told
 */
function ws_wtable_share_tell($viewer, $table, $user_id, $level)
{
    $inbox_id = ws_notify($user_id, 'wtable_shared', array('channel_id' => 0, 'message_id' => 0, 'actor_id' => (int) $viewer['id'], 'separate' => true));
    $gives = ws_wtable_share_gives('user', $level);

    if (($inbox_id > 0) && ($gives !== '') && function_exists('ws_scheduled_chains_ready') && ws_scheduled_chains_ready()) {
        $text = lang(array('string' => 'Table “{var:1}” · {var:2}', 'vars' => array($table['name'], ws_wtable_share_levels()[$gives])));

        db("REPLACE INTO ws_scheduled_notices (inbox_id, action_id, text, created_at)
            VALUES ('" . (int) $inbox_id . "', 0, '" . e(mb_substr($text, 0, 1000)) . "', '" . time() . "')");
    }

    return $inbox_id;
}

/**
 * Whether the person may take a share of the table back: the checks of
 * ws_wtable_share_remove(), made on their own for the API's dry run. The
 * owner takes any of them back.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param int        $share_id
 * @return array ok, error, field, status, share (the row)
 */
function ws_wtable_share_remove_check($viewer, $table, $share_id)
{
    if (!ws_wtables_ready()) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    $access = ws_wtable_access($viewer, $table);

    if ($access === '') {
        return ws_wtable_refusal('not_found', '', 404);
    }

    if (!ws_wtable_may($access, 'manage')) {
        return ws_wtable_refusal('forbidden', '', 403);
    }

    $share = ws_wtable_share($table['id'], $share_id);

    if (!$share) {
        return ws_wtable_refusal('share_not_found', '', 404);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200, 'share' => $share);
}

/**
 * Takes a share of the table back: from then on the person or the channel's
 * readers reach the table only as anybody else does, which is not at all.
 * Their bell line about the share stays, as for a flow: it names the table
 * and opens nothing they no longer reach.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param int        $share_id
 * @return array ok, error, field, status, share (the row as it was)
 */
function ws_wtable_share_remove($viewer, $table, $share_id)
{
    $check = ws_wtable_share_remove_check($viewer, $table, $share_id);

    if (!$check['ok']) {
        return $check + array('share' => null);
    }

    $share = $check['share'];

    db("DELETE FROM ws_wtable_shares WHERE id = '" . (int) $share['id'] . "' AND table_id = '" . (int) $table['id'] . "'");

    if ((int) mysqli_affected_rows(db::$con) > 0) {
        log_activity(lang(array('string' => 'workspace table ({var:1}) is no longer shared with {var:2}', 'vars' => array(
            $table['name'],
            ws_flow_share_target_name((string) $share['target_type'], (int) $share['target_id']),
        ))), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return $check;
}

/* ---------------------------------------------------------------------------
   The tables a person reaches
   --------------------------------------------------------------------------- */

/**
 * The tables the person reaches: their own, and those of others shared with
 * them or in a channel they read, the latest changed first, each with its
 * access (ws_wtable_access()). A table whose owner left the team is in
 * nobody's list.
 *
 * @param array  $viewer
 * @param string $scope all | mine | shared
 * @return array[] ws_wtables rows with access
 */
function ws_wtables_reached($viewer, $scope = 'all')
{
    if (!ws_wtables_ready() || empty($viewer['member'])) {
        return array();
    }

    $rows = ($scope === 'shared') ? array() : ws_wtables_own($viewer);

    if ($scope === 'mine') {
        return $rows;
    }

    $ids = array();

    foreach ((array) db_items("SELECT table_id, target_type, target_id FROM ws_wtable_shares
        WHERE (target_type = 'user' AND target_id = '" . (int) $viewer['id'] . "') OR target_type = 'channel'") as $share) {
        if (((string) $share['target_type'] === 'user') || ws_flow_channel_readable($viewer, (int) $share['target_id'])) {
            $ids[(int) $share['table_id']] = true;
        }
    }

    if (!empty($ids)) {
        $others = (array) db_items("SELECT id, user_id, name, description, row_count, revision, created_at, updated_at FROM ws_wtables
            WHERE id IN ('" . implode("','", array_keys($ids)) . "') AND user_id <> '" . (int) $viewer['id'] . "'");

        foreach ($others as $table) {
            $table['access'] = ws_wtable_access($viewer, $table);

            if ($table['access'] !== '') {
                $rows[] = $table;
            }
        }
    }

    usort($rows, function ($a, $b) {
        return array((int) $b['updated_at'], (int) $b['id']) <=> array((int) $a['updated_at'], (int) $a['id']);
    });

    return $rows;
}

/* ---------------------------------------------------------------------------
   Webhook events of a table shared in a public channel
   --------------------------------------------------------------------------- */

/**
 * The public channels a table is shared in, oldest share first: its rows'
 * events go out only for these, since every reader of the site's public
 * channels may read them. A table shared only with people or in private
 * channels is never announced.
 *
 * @param int $table_id
 * @return int[]
 */
function ws_wtable_event_channels($table_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return array();
    }

    $ids = array();

    foreach ((array) db_items("SELECT s.target_id FROM ws_wtable_shares s JOIN ws_channels c ON c.id = s.target_id
        WHERE s.table_id = '" . (int) $table_id . "' AND s.target_type = 'channel' AND c.kind = 'public' AND c.archived_at = 0
        ORDER BY s.id") as $share) {
        $ids[] = (int) $share['target_id'];
    }

    return $ids;
}

/**
 * The body of a row event: the table, the row (ws_wtable_row_present(), or
 * id and position for a deleted one), who did it, the column ids written by
 * a change, and the public channels the table is shared in. channel_id is
 * the first of them, the one a subscription narrowed to a channel group is
 * matched by: one event per change, however many channels show the table.
 *
 * @param array $table       ws_wtables row
 * @param array $row         the row as presented
 * @param int[] $channel_ids ws_wtable_event_channels(), not empty
 * @param int   $actor_id
 * @param array $changed     column ids written (a change only)
 * @return array
 */
function ws_wtable_row_event_body($table, $row, $channel_ids, $actor_id, $changed = array())
{
    $body = array(
        'channel_id'  => (int) reset($channel_ids),
        'channel_ids' => array_map('intval', array_values($channel_ids)),
        'table'       => array('id' => (int) $table['id'], 'name' => (string) $table['name']),
        'row'         => $row,
        'by'          => (int) $actor_id,
    );

    if (!empty($changed)) {
        $body['changed'] = array_map('intval', array_values($changed));
    }

    return $body;
}

/**
 * Whether row writes announce nothing: set while a file is imported, since
 * one listing tells a receiver more than an event per row.
 *
 * @param bool|null $quiet true or false to set it, null to ask
 * @return bool
 */
function ws_wtable_row_announce_quiet($quiet = null)
{
    static $state = false;

    if ($quiet !== null) {
        $state = (bool) $quiet;
    }

    return $state;
}

/**
 * Announce a row written or deleted, when the table is shared in a public
 * channel and no import is running (ws_wtable_row_announce_quiet()).
 *
 * @param string    $what     created | updated | deleted
 * @param array     $table
 * @param int|array $row      the row id, or the stored row of a deleted one
 * @param int       $actor_id
 * @param array     $changed  column ids written (a change)
 */
function ws_wtable_row_announce($what, $table, $row, $actor_id, $changed = array())
{
    if (ws_wtable_row_announce_quiet()) {
        return;
    }

    $channel_ids = ws_wtable_event_channels((int) $table['id']);

    if (empty($channel_ids) || !function_exists('pg_announce')) {
        return;
    }

    if ($what === 'deleted') {
        $body = ws_wtable_row_event_body($table, array('id' => (int) $row['id'], 'position' => (int) $row['position']), $channel_ids, $actor_id);
        pg_announce('workspace.wtable.row.deleted', array('channel_id' => $body['channel_id']) + $body);

        return;
    }

    $stored = ws_wtable_row((int) $table['id'], (int) $row);

    if (!$stored) {
        return;
    }

    $body = ws_wtable_row_event_body($table, ws_wtable_row_present(ws_wtable_row_out($stored, ws_wtable_columns((int) $table['id']))), $channel_ids, $actor_id, $changed);

    // Each event named in full, with channel_id in the call itself, as the
    // check that every workspace event names its channel reads it.
    if ($what === 'created') {
        pg_announce('workspace.wtable.row.created', array('channel_id' => $body['channel_id']) + $body);
    } else {
        pg_announce('workspace.wtable.row.updated', array('channel_id' => $body['channel_id']) + $body);
    }
}
