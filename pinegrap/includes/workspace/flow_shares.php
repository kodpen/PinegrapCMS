<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a flow shared with the team. Its owner gives a person a view of
 * the flow, the right to run it, or the right to change it with them; a
 * channel gets a view of it for the people who read the channel. One row of
 * ws_flow_shares per flow and person or channel (2026.11.0, 11.0.83).
 *
 * What a person may do with a flow is the highest of what reaches them
 * (ws_flow_access_level(), pure): the owner changes it while on the staff,
 * the readers of a live flow's home channel run it, and the shares add
 * theirs. A person shared at edit changes the flow only while they are staff
 * in the team (ws_can_schedule()), and runs it otherwise. A channel share
 * gives a view only: running comes from the flow's home channel, as before.
 * Site members never reach a flow, shared or not.
 *
 * Only the owner manages the shares, as only the owner publishes the flow,
 * moves it to another home channel or deletes it: a person shared at edit
 * changes the draft and may pause the flow, but neither publishes what it
 * runs in the owner's name nor hands it on. Being an administrator gives
 * nothing here, as for a note. Whoever starts a run, it acts with the
 * owner's rights at the time each box runs.
 *
 * Sharing writes a Site Log line, and a person a flow is newly shared with is
 * told in their bell (flow_shared). The canvas draws the shares in its Share
 * dialog (assets/js/workspace_flow.js); the external API reads and writes
 * them through includes/workspace/api_flows.php with the same checks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most people and channels one flow is shared with. */
define('WS_FLOW_MAX_SHARES', 200);

/**
 * Is the share table there (2026.11.0, 11.0.83)? The flows' own tables come
 * with an earlier step.
 *
 * @return bool
 */
function ws_flow_shares_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_flows_ready() && pg_schema_has('ws_flow_shares');
    }

    return $ready;
}

/* ---------------------------------------------------------------------------
   Levels (pure)
   --------------------------------------------------------------------------- */

/**
 * The levels a share gives, lowest first.
 *
 * @return array level => label
 */
function ws_flow_share_levels()
{
    return array(
        'view' => lang('Can view'),
        'run'  => lang('Can run'),
        'edit' => lang('Can edit'),
    );
}

/**
 * What a flow is shared with.
 *
 * @return string[]
 */
function ws_flow_share_targets()
{
    return array('user', 'channel');
}

/**
 * How far an access reaches: '' nothing, view, run, edit.
 *
 * @param string $access
 * @return int 0-3
 */
function ws_flow_access_rank($access)
{
    $ranks = array('view' => 1, 'run' => 2, 'edit' => 3);

    return $ranks[(string) $access] ?? 0;
}

/**
 * What one share gives its holder now: a person shared at edit changes the
 * flow only while they are staff in the team and runs it otherwise; a
 * channel share gives a view, whatever is stored.
 *
 * @param string $target_type user | channel
 * @param string $level       view | run | edit
 * @param bool   $staff       the person builds flows (ws_can_schedule())
 * @return string edit | run | view | ''
 */
function ws_flow_share_gives($target_type, $level, $staff)
{
    if (!isset(ws_flow_share_levels()[(string) $level])) {
        return '';
    }

    if ($target_type === 'channel') {
        return 'view';
    }

    if ($target_type !== 'user') {
        return '';
    }

    return (($level === 'edit') && !$staff) ? 'run' : (string) $level;
}

/**
 * What a person may do with a flow, from what is known about them and it:
 *   owner    bool   the flow is theirs
 *   staff    bool   they build flows (ws_can_schedule())
 *   status   string the flow's status
 *   home     bool   they read its home channel, a public or private one
 *   person   string the level the flow is shared with them at, '' for none
 *   channel  bool   the flow is shared in a channel they read
 *   converted bool  the flow carries out a converted scheduled action
 * The owner edits while on the staff, and so does any staff member a
 * converted scheduled action's flow, as they changed the action; the home's
 * readers run a live flow;
 * each share gives what ws_flow_share_gives() says. The highest wins, and a
 * deleted flow gives nothing.
 *
 * @param array $facts
 * @return string edit | run | view | ''
 */
function ws_flow_access_level($facts)
{
    if ((string) ($facts['status'] ?? '') === 'binned') {
        return '';
    }

    $staff = !empty($facts['staff']);
    $found = array();

    if ((!empty($facts['owner']) || !empty($facts['converted'])) && $staff) {
        $found[] = 'edit';
    }

    if (!empty($facts['home']) && ((string) ($facts['status'] ?? '') === 'live')) {
        $found[] = 'run';
    }

    if ((string) ($facts['person'] ?? '') !== '') {
        $found[] = ws_flow_share_gives('user', (string) $facts['person'], $staff);
    }

    if (!empty($facts['channel'])) {
        $found[] = 'view';
    }

    $best = '';

    foreach ($found as $access) {
        if (ws_flow_access_rank($access) > ws_flow_access_rank($best)) {
            $best = $access;
        }
    }

    return $best;
}

/**
 * May the person manage a flow's shares, publish it and delete it? Its
 * owner, while the flow is theirs to change; a person shared at edit is not,
 * nor is an administrator.
 *
 * @param bool   $is_owner
 * @param string $access ws_flow_access()
 * @return bool
 */
function ws_flow_share_manages($is_owner, $access)
{
    return $is_owner && ($access === 'edit');
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
function ws_flow_share_input_problem($target_type, $target_id, $level)
{
    if (!is_string($target_type) || !in_array($target_type, ws_flow_share_targets(), true)) {
        return 'target_type';
    }

    if (!is_numeric($target_id) || ((int) $target_id <= 0)) {
        return 'target_id';
    }

    if (!is_string($level) || !isset(ws_flow_share_levels()[$level])) {
        return 'level';
    }

    if (($target_type === 'channel') && ($level !== 'view')) {
        return 'channel_level';
    }

    return '';
}

/* ---------------------------------------------------------------------------
   Reading the shares
   --------------------------------------------------------------------------- */

/**
 * The shares read in this request, by flow: ws_flow_access() asks for them
 * on every flow it decides, and a list asks it for every flow listed.
 *
 * @return array flow id => share rows
 */
function &ws_flow_share_cache()
{
    static $cache = array();

    return $cache;
}

/**
 * The shares of a set of flows, read in one statement for the ones not read
 * yet in this request.
 *
 * @param int[] $flow_ids
 */
function ws_flow_share_rows_prime($flow_ids)
{
    $cache = &ws_flow_share_cache();
    $wanted = array();

    foreach ((array) $flow_ids as $flow_id) {
        if (((int) $flow_id > 0) && !isset($cache[(int) $flow_id])) {
            $wanted[(int) $flow_id] = (int) $flow_id;
        }
    }

    if (empty($wanted)) {
        return;
    }

    foreach ($wanted as $flow_id) {
        $cache[$flow_id] = array();
    }

    if (!ws_flow_shares_ready()) {
        return;
    }

    foreach ((array) db_items("SELECT * FROM ws_flow_shares WHERE flow_id IN (" . implode(',', $wanted) . ") ORDER BY id") as $row) {
        $cache[(int) $row['flow_id']][] = $row;
    }
}

/**
 * The shares of one flow, oldest first.
 *
 * @param int $flow_id
 * @return array[] ws_flow_shares rows
 */
function ws_flow_share_rows($flow_id)
{
    ws_flow_share_rows_prime(array((int) $flow_id));

    $cache = &ws_flow_share_cache();

    return $cache[(int) $flow_id] ?? array();
}

/**
 * Forgets the shares read for a flow, after they changed.
 *
 * @param int $flow_id
 */
function ws_flow_share_rows_forget($flow_id)
{
    $cache = &ws_flow_share_cache();

    unset($cache[(int) $flow_id]);
}

/**
 * Is this a channel a flow may be shared in, and one the person reads? A
 * public or private channel, as a flow's home is.
 *
 * @param array      $viewer
 * @param array|null $channel
 * @return bool
 */
function ws_flow_share_channel_readable($viewer, $channel)
{
    return is_array($channel) && in_array((string) $channel['kind'], array('public', 'private'), true) && ws_can_read_channel($viewer, $channel);
}

/**
 * Does the person read this channel, as a flow's home, a channel a flow is
 * shared in or the channel a run was started in
 * (ws_flow_share_channel_readable())? Answered once a request per person and
 * channel: a list of flows or runs asks it for every row and every channel
 * share, and ws_channel() reads the row each time. The membership generation
 * is part of the key, so the answer is read again once the request changed a
 * membership; the role and the member flag are, as ws_can_read_channel()
 * decides by them.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @return bool
 */
function ws_flow_channel_readable($viewer, $channel_id)
{
    static $seen = array();

    $channel_id = (int) $channel_id;

    if ($channel_id <= 0) {
        return false;
    }

    $key = (int) ($GLOBALS['ws_membership_generation'] ?? 0)
        . ':' . (int) ($viewer['id'] ?? 0)
        . ':' . (int) ($viewer['role'] ?? 3)
        . ':' . (empty($viewer['member']) ? 0 : 1)
        . ':' . $channel_id;

    if (!isset($seen[$key])) {
        $seen[$key] = ws_flow_share_channel_readable($viewer, ws_channel($channel_id));
    }

    return $seen[$key];
}

/**
 * What the shares of a flow give the person: the level of the one made to
 * them, and whether it is shared in a channel they read.
 *
 * @param array $viewer
 * @param array $flow
 * @return array person (level or ''), channel (bool)
 */
function ws_flow_share_reach($viewer, $flow)
{
    $reach = array('person' => '', 'channel' => false);

    foreach (ws_flow_share_rows($flow['id']) as $share) {
        if (((string) $share['target_type'] === 'user') && ((int) $share['target_id'] === (int) $viewer['id'])) {
            $reach['person'] = (string) $share['level'];
        } elseif (((string) $share['target_type'] === 'channel') && !$reach['channel']) {
            $reach['channel'] = ws_flow_channel_readable($viewer, (int) $share['target_id']);
        }
    }

    return $reach;
}

/**
 * May the person manage this flow's shares, publish it and delete it
 * (ws_flow_share_manages())?
 *
 * @param array      $viewer
 * @param array|null $flow
 * @return bool
 */
function ws_flow_share_manager($viewer, $flow)
{
    return is_array($flow) && ws_flow_share_manages((int) $flow['owner_id'] === (int) ($viewer['id'] ?? 0), ws_flow_access($viewer, $flow));
}

/**
 * One share of a flow.
 *
 * @param int $flow_id
 * @param int $share_id
 * @return array|null
 */
function ws_flow_share($flow_id, $share_id)
{
    if (!ws_flow_shares_ready() || ((int) $share_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_flow_shares WHERE id = '" . (int) $share_id . "' AND flow_id = '" . (int) $flow_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The name of what a flow is shared with, for a sentence: a person's name,
 * or a channel's with its # sign.
 *
 * @param string $target_type
 * @param int    $target_id
 * @return string
 */
function ws_flow_share_target_name($target_type, $target_id)
{
    if ($target_type === 'channel') {
        $channel = ws_channel((int) $target_id);

        return '#' . ($channel ? (string) $channel['name'] : (int) $target_id);
    }

    return ws_person_name((int) $target_id);
}

/**
 * The shares of a flow as the person may see them, oldest first, each with
 * name (the person's or the channel's) and gives (what it gives now: a
 * person who left the team nothing). Whoever reaches the flow sees who else
 * does; a channel they do not read is left out for them, and shown nameless
 * to the owner, who manages it.
 *
 * @param array $viewer
 * @param array $flow
 * @return array[] share rows with name and gives
 */
function ws_flow_shares_list($viewer, $flow)
{
    $manage = ws_flow_share_manager($viewer, $flow);
    $rows = ws_flow_share_rows($flow['id']);
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
        $name = '';
        $gives = '';

        if ($type === 'user') {
            $person = $people[(int) $row['target_id']] ?? null;
            $member = ws_is_team_member((int) $row['target_id']);
            $name = $person ? (string) $person['name'] : '';
            $gives = $member ? ws_flow_share_gives('user', (string) $row['level'], $person && ((int) $person['role'] < 3)) : '';
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

/* ---------------------------------------------------------------------------
   Writing the shares
   --------------------------------------------------------------------------- */

/**
 * Whether the person may share the flow with this person or channel at this
 * level: the checks of ws_flow_share_set(), made on their own so that the
 * API's dry run meets the same refusals. The owner shares; a person must be
 * a colleague in the team other than the owner, a channel a public or
 * private one the owner reads, shared at view. Sharing again with the same
 * person or channel changes its level.
 *
 * @param array $viewer
 * @param array $flow
 * @param array $data target_type, target_id, level (view when left out)
 * @return array ok, error, status, field, target_type, target_id, level, existing (the share made before, null for none)
 */
function ws_flow_share_set_check($viewer, $flow, $data)
{
    if (!ws_flow_shares_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (!ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of a flow can share it.'), 403);
    }

    // A role-3 member's own flow stays theirs alone (flow_member.php).
    if (ws_flow_builder_kind($viewer) === 'member') {
        return ws_flow_refusal(lang('Your own flows are not shared: only you run them.'), 403);
    }

    $type = $data['target_type'] ?? '';
    $target_id = $data['target_id'] ?? 0;
    $level = $data['level'] ?? 'view';

    switch (ws_flow_share_input_problem($type, $target_id, $level)) {
        case 'target_type':
            return ws_flow_refusal(lang('A flow is shared with a person or a channel.'), 422, 'target_type');

        case 'target_id':
            return ws_flow_refusal(($type === 'channel') ? lang('A flow is shared in a public or private channel you read.') : lang('Pick a colleague in the team.'), 422, 'target_id');

        case 'level':
            return ws_flow_refusal(lang('A share lets a person view the flow, run it or edit it.'), 422, 'level');

        case 'channel_level':
            return ws_flow_refusal(lang('A channel share lets the people who read the channel look at the flow; running it comes from the flow\'s home channel.'), 422, 'level');
    }

    $target_id = (int) $target_id;

    if ($type === 'user') {
        if ($target_id === (int) $flow['owner_id']) {
            return ws_flow_refusal(lang('The flow is its owner\'s already.'), 422, 'target_id');
        }

        if (!ws_is_team_member($target_id)) {
            return ws_flow_refusal(lang('Pick a colleague in the team.'), 422, 'target_id');
        }
    } elseif (!ws_flow_share_channel_readable($viewer, ws_channel($target_id))) {
        return ws_flow_refusal(lang('A flow is shared in a public or private channel you read.'), 422, 'target_id');
    }

    $existing = null;

    foreach (ws_flow_share_rows($flow['id']) as $share) {
        if (((string) $share['target_type'] === $type) && ((int) $share['target_id'] === $target_id)) {
            $existing = $share;
        }
    }

    if (($existing === null) && (count(ws_flow_share_rows($flow['id'])) >= WS_FLOW_MAX_SHARES)) {
        return ws_flow_refusal(lang(array('string' => 'A flow can be shared with at most {var:1} people and channels.', 'vars' => WS_FLOW_MAX_SHARES)));
    }

    return array(
        'ok'          => true,
        'error'       => '',
        'status'      => 200,
        'field'       => '',
        'target_type' => $type,
        'target_id'   => $target_id,
        'level'       => $level,
        'existing'    => $existing,
    );
}

/**
 * Shares the flow with a person or a channel, or changes the level of the
 * share made before. A person it is newly shared with is told in their bell.
 * Two requests that share with the same one at the same moment meet the
 * unique (flow, kind, id): the second changes the level of the first's row.
 *
 * @param array $viewer
 * @param array $flow
 * @param array $data see ws_flow_share_set_check()
 * @return array ok, error, status (201 new, 200 changed), field, share_id, created
 */
function ws_flow_share_set($viewer, $flow, $data)
{
    $check = ws_flow_share_set_check($viewer, $flow, $data);

    if (!$check['ok']) {
        return $check + array('share_id' => 0, 'created' => false);
    }

    $flow_id = (int) $flow['id'];
    $type = $check['target_type'];
    $target_id = $check['target_id'];
    $level = $check['level'];
    $share_id = is_array($check['existing']) ? (int) $check['existing']['id'] : 0;
    $created = false;

    // Shared again at the level it has: nothing to write or to log.
    if (($share_id > 0) && ((string) $check['existing']['level'] === $level)) {
        return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'share_id' => $share_id, 'created' => false);
    }

    if ($share_id === 0) {
        // Not db(): a failed statement there ends the request with an HTML
        // page, and the duplicate key of a share made meanwhile is an answer.
        $written = db_q("INSERT INTO ws_flow_shares (flow_id, target_type, target_id, level, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            array($flow_id, (string) $type, (int) $target_id, (string) $level, (int) $viewer['id'], time()));

        if ($written) {
            $share_id = (int) mysqli_insert_id(db::$con);
            $created = ($share_id > 0);
        } elseif ((int) mysqli_errno(db::$con) === 1062) {
            $share_id = (int) db_value("SELECT id FROM ws_flow_shares
                WHERE flow_id = '" . $flow_id . "' AND target_type = '" . e($type) . "' AND target_id = '" . $target_id . "'");
        }

        if ($share_id <= 0) {
            return ws_flow_refusal(lang('The flow could not be shared.')) + array('share_id' => 0, 'created' => false);
        }
    }

    if (!$created) {
        db("UPDATE ws_flow_shares SET level = '" . e($level) . "' WHERE id = '" . $share_id . "' AND flow_id = '" . $flow_id . "'");
    }

    ws_flow_share_rows_forget($flow_id);

    $name = ws_flow_share_target_name($type, $target_id);
    $label = ws_flow_share_levels()[$level];

    log_activity($created
        ? lang(array('string' => 'workspace flow ({var:1}) was shared with {var:2}: {var:3}', 'vars' => array($flow['name'], $name, $label)))
        : lang(array('string' => 'workspace flow ({var:1}): the share with {var:2} was changed to {var:3}', 'vars' => array($flow['name'], $name, $label))),
        (string) ($_SESSION['sessionusername'] ?? ''));

    if ($created && ($type === 'user')) {
        ws_flow_share_tell($viewer, $flow, $target_id, $level);
    }

    return array('ok' => true, 'error' => '', 'status' => $created ? 201 : 200, 'field' => '', 'share_id' => $share_id, 'created' => $created);
}

/**
 * Tells a person a flow was shared with them: a line of its own in their
 * bell ('separate': the inbox keys a line by its channel, message and task,
 * all 0 here, so a line still unread would otherwise take the sentence of
 * another flow shared later), whose sentence names the flow and what they
 * may do with it, kept beside the line as a run's end is
 * (ws_scheduled_notices).
 *
 * @param array  $viewer  who shared it
 * @param array  $flow
 * @param int    $user_id
 * @param string $level
 * @return int the inbox row, 0 when nobody was told
 */
function ws_flow_share_tell($viewer, $flow, $user_id, $level)
{
    $person = ws_people(array((int) $user_id))[(int) $user_id] ?? null;
    $gives = ws_flow_share_gives('user', $level, $person && ((int) $person['role'] < 3));
    $inbox_id = ws_notify($user_id, 'flow_shared', array('channel_id' => 0, 'message_id' => 0, 'actor_id' => (int) $viewer['id'], 'separate' => true));

    if (($inbox_id > 0) && ($gives !== '') && function_exists('ws_scheduled_chains_ready') && ws_scheduled_chains_ready()) {
        $text = lang(array('string' => 'Flow “{var:1}” · {var:2}', 'vars' => array($flow['name'], ws_flow_share_levels()[$gives])));

        db("REPLACE INTO ws_scheduled_notices (inbox_id, action_id, text, created_at)
            VALUES ('" . (int) $inbox_id . "', 0, '" . e(mb_substr($text, 0, 1000)) . "', '" . time() . "')");
    }

    return $inbox_id;
}

/**
 * Whether the person may take a share of the flow back: the checks of
 * ws_flow_share_remove(), made on their own for the API's dry run. The
 * owner takes any of them back.
 *
 * @param array $viewer
 * @param array $flow
 * @param int   $share_id
 * @return array ok, error, status, field, share (the row)
 */
function ws_flow_share_remove_check($viewer, $flow, $share_id)
{
    if (!ws_flow_shares_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (!ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of a flow can share it.'), 403);
    }

    $share = ws_flow_share($flow['id'], $share_id);

    if (!$share) {
        return ws_flow_refusal(lang('That share could not be found.'), 404);
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'share' => $share);
}

/**
 * Takes a share of the flow back: from then on the person or the channel's
 * readers reach the flow only as anybody else does. Runs they started stay
 * as they are.
 *
 * @param array $viewer
 * @param array $flow
 * @param int   $share_id
 * @return array ok, error, status, field, share (the row as it was)
 */
function ws_flow_share_remove($viewer, $flow, $share_id)
{
    $check = ws_flow_share_remove_check($viewer, $flow, $share_id);

    if (!$check['ok']) {
        return $check + array('share' => null);
    }

    $share = $check['share'];

    db("DELETE FROM ws_flow_shares WHERE id = '" . (int) $share['id'] . "' AND flow_id = '" . (int) $flow['id'] . "'");

    $removed = ((int) mysqli_affected_rows(db::$con) > 0);

    ws_flow_share_rows_forget($flow['id']);

    if ($removed) {
        log_activity(lang(array('string' => 'workspace flow ({var:1}) is no longer shared with {var:2}', 'vars' => array(
            $flow['name'],
            ws_flow_share_target_name((string) $share['target_type'], (int) $share['target_id']),
        ))), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return $check;
}
