<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - scheduled actions in the external API: reading them, pausing
 * and cancelling one. They are for staff in the team, who all see all of
 * them, as on the panel's screen (ws_can_schedule()); anybody else is
 * refused with 403. Only scheduled actions are reached, never a person's
 * scheduled message (ws_scheduled_action()) nor the row that starts a flow
 * (flow_id). Pausing and cancelling meet the panel's own refusals
 * (ws_scheduled_set_status_check()), so a dry run answers what the real call
 * would.
 *
 * An action may name channels - where it writes, the channel whose joiners
 * or events start it. Their names, and every sentence that could carry
 * them, are given only when the owner of the application reads all of
 * those channels (and, for Claude's key, when Claude may be asked in them).
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

/** The most scheduled actions one listing answers, as on the panel's screen. */
define('WS_API_SCHEDULED_LIST_MAX', 300);

/**
 * The routes, merged into ws_api_routes().
 *
 * @return array
 */
function ws_scheduled_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);

    return array(

        array(
            'id'          => 'workspace.scheduled_actions.list',
            'method'      => 'GET',
            'path'        => '/workspace/scheduled-actions',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_scheduled_actions_list',
            'returns'     => array('list' => 'WorkspaceScheduledAction'),
            'summary'     => 'List scheduled actions',
            'description' => 'The scheduled actions of the team, as the Scheduled actions screen lists them: the ones set to run first, by their next time, then the rest newest first; at most ' . WS_API_SCHEDULED_LIST_MAX . '. They are for staff in the team, who all see all of them: anybody else is refused with 403. A person\'s scheduled messages are not listed here (GET /workspace/channels/{id}/scheduled-messages). channel_name, message_id and the sentences (when, conditions, action_text, the follow-ups\' text, the last run\'s summary) are null, with texts_withheld true, when the action names a channel the owner of the application does not read. history is null in the listing.',
            'params'      => array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array_keys(ws_scheduled_states()), 'description' => 'Only the ones in this state.'),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the ones made in this channel.'),
            ),
        ),

        array(
            'id'          => 'workspace.scheduled_actions.get',
            'method'      => 'GET',
            'path'        => '/workspace/scheduled-actions/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_scheduled_actions_get',
            'returns'     => 'WorkspaceScheduledAction',
            'summary'     => 'One scheduled action, with its latest runs',
            'description' => 'A scheduled action, as the listing gives it, with history: its latest 20 runs, newest first (status done, skipped or failed; by_hand for a run started from its card; source_id the action that started it). For staff in the team only; 404 for an id that is not a scheduled action.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.scheduled_actions.pause',
            'method'      => 'POST',
            'path'        => '/workspace/scheduled-actions/{id}/pause',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_scheduled_actions_pause',
            'dry_run'     => true,
            'returns'     => 'WorkspaceScheduledAction',
            'summary'     => 'Pause a scheduled action',
            'description' => 'Pauses a scheduled action that is set to run, paused already or failed: it does not run, nor is it started by another action or an event, until it is resumed on the Scheduled actions screen. One done or cancelled is refused with 422. For staff in the team only.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.scheduled_actions.cancel',
            'method'      => 'POST',
            'path'        => '/workspace/scheduled-actions/{id}/cancel',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_scheduled_actions_cancel',
            'dry_run'     => true,
            'returns'     => 'WorkspaceScheduledAction',
            'summary'     => 'Cancel a scheduled action',
            'description' => 'Cancels a scheduled action for good: it never runs again, and the starts other actions left waiting for it are dropped. One done or cancelled already is refused with 422. It cannot be undone. For staff in the team only.',
            'params'      => array($id),
        ),

    );
}

/**
 * The ids of the channels an action names (pure): the one it was made in,
 * the ones its rules start it from and the ones it and its follow-ups act
 * in. -1 (the channel somebody joined) is no channel of its own.
 *
 * @param array $row decoded
 * @return int[]
 */
function ws_api_scheduled_channel_ids($row)
{
    $ids = array((int) ($row['channel_id'] ?? 0));

    foreach ((array) ($row['rules'] ?? array()) as $rule) {
        $ids[] = is_array($rule) ? (int) ($rule['channel_id'] ?? 0) : 0;
    }

    $actions = array($row['action'] ?? null);

    foreach ((array) ($row['follow'] ?? array()) as $follow) {
        $actions[] = is_array($follow) ? ($follow['do'] ?? null) : null;
    }

    foreach ($actions as $action) {
        $ids[] = is_array($action) ? (int) ($action['channel_id'] ?? 0) : 0;
    }

    return array_values(array_unique(array_filter($ids, function ($id) {
        return $id > 0;
    })));
}

/**
 * Does the owner of the application read this channel, and may Claude's key
 * be told about it? A channel that is gone is read by nobody.
 *
 * @param array      $viewer
 * @param array|null $channel
 * @return bool
 */
function ws_api_scheduled_channel_open($viewer, $channel)
{
    if (!is_array($channel) || !ws_can_read_channel($viewer, $channel)) {
        return false;
    }

    return !ws_api_is_claude() || ws_claude_channel_allowed($channel);
}

/**
 * Are the sentences of an action to be given (pure)? Only when every
 * channel it names is open to the caller.
 *
 * @param int[] $channel_ids ws_api_scheduled_channel_ids()
 * @param array $open        channel id => bool
 * @return bool
 */
function ws_api_scheduled_texts_given($channel_ids, $open)
{
    foreach ($channel_ids as $id) {
        if (empty($open[(int) $id])) {
            return false;
        }
    }

    return true;
}

/**
 * One scheduled action as the API gives it.
 *
 * @param array $viewer
 * @param array $row  decoded
 * @param bool  $full with its latest runs
 * @return array
 */
function ws_api_scheduled_action_present($viewer, $row, $full = false)
{
    $open = array();

    foreach (ws_api_scheduled_channel_ids($row) as $channel_id) {
        $open[$channel_id] = ws_api_scheduled_channel_open($viewer, ws_channel($channel_id));
    }

    $texts = ws_api_scheduled_texts_given(array_keys($open), $open);
    $home = (int) $row['channel_id'];
    $home_open = ($home > 0) && !empty($open[$home]);
    $channel = $home_open ? ws_channel($home) : null;
    $time = ws_scheduled_time_rule($row);
    $event = ws_scheduled_event_rule($row);
    $trigger_only = ws_scheduled_trigger_only($row);
    $last = null;

    if ((int) $row['last_run_at'] > 0) {
        $last = db_item("SELECT status, detail, finished_at FROM ws_scheduled_runs
            WHERE action_id = '" . (int) $row['id'] . "' AND finished_at > 0 ORDER BY id DESC LIMIT 1");
    }

    $conditions = array();

    foreach ((array) $row['rules'] as $rule) {
        if (!in_array(($rule['type'] ?? ''), array('at', 'trigger', 'join', 'event'), true)) {
            $conditions[] = ws_scheduled_rule_text($viewer, $rule);
        }
    }

    $follow = array();

    foreach ((array) $row['follow'] as $item) {
        $follow[] = array(
            'on'        => (string) $item['on'],
            'type'      => (string) ($item['do']['type'] ?? ''),
            'text'      => $texts ? ws_scheduled_action_text($viewer, $item['do']) : null,
            'target_id' => ((($item['do']['type'] ?? '') === 'trigger')) ? (int) $item['do']['target_id'] : null,
        );
    }

    $starters = array();

    foreach (ws_scheduled_starters()[(int) $row['id']] ?? array() as $source_id) {
        $starters[] = array('id' => (int) $source_id, 'name' => trim(ws_scheduled_name($source_id), '“”'));
    }

    $when = $time ? ws_scheduled_rule_text($viewer, $time) : ($trigger_only ? ws_scheduled_rule_text($viewer, $event) : '');
    $out = array(
        'id'             => (int) $row['id'],
        'name'           => (string) $row['name'],
        'status'         => (string) $row['status'],
        'next_run_at'    => (((string) $row['status'] === 'active') && ((int) $row['next_run_at'] > 0)) ? api_time($row['next_run_at']) : null,
        'repeats'        => $time && (($time['repeat'] ?? 'none') !== 'none'),
        'on_event'       => ($event !== null) && ($event['type'] === 'event'),
        'joins'          => ($event !== null) && ($event['type'] === 'join'),
        'trigger_only'   => $trigger_only,
        'texts_withheld' => !$texts,
        'when'           => $texts ? $when : null,
        'conditions'     => $texts ? $conditions : null,
        'action_type'    => (string) ($row['action']['type'] ?? ''),
        'action_text'    => $texts ? ws_scheduled_action_text($viewer, $row['action']) : null,
        'follow'         => $follow,
        'starters'       => $starters,
        'channel_id'     => ($home > 0) ? $home : null,
        'channel_name'   => $channel ? (string) $channel['name'] : null,
        'message_id'     => ($home_open && ((int) $row['message_id'] > 0)) ? (int) $row['message_id'] : null,
        'run_count'      => (int) $row['run_count'],
        'last_run'       => is_array($last) ? array(
            'status'      => (string) $last['status'],
            'finished_at' => api_time($last['finished_at']),
            'summary'     => $texts ? ws_scheduled_run_summary($last) : null,
        ) : null,
        'can_change'     => in_array($row['status'], array('active', 'paused', 'failed'), true),
        'created_by'     => (int) $row['created_by'],
        'created_at'     => api_time($row['created_at']),
        'updated_at'     => api_time($row['updated_at']),
        'history'        => null,
    );

    if ($full) {
        $out['history'] = array();

        foreach ((array) db_items("SELECT * FROM ws_scheduled_runs WHERE action_id = '" . (int) $row['id'] . "' AND finished_at > 0 ORDER BY id DESC LIMIT 20") as $run) {
            $out['history'][] = array(
                'status'      => (string) $run['status'],
                'started_at'  => api_time($run['started_at']),
                'finished_at' => api_time($run['finished_at']),
                'by_hand'     => !empty($run['by_hand']),
                'source_id'   => ((int) ($run['source_action_id'] ?? 0) > 0) ? (int) $run['source_action_id'] : null,
                'summary'     => $texts ? ws_scheduled_run_summary($run) : null,
            );
        }
    }

    return $out;
}

// What ws_api_scheduled_action_present() returns.
function ws_api_scheduled_action_schema()
{
    return array(
        'id'             => 'integer',
        'name'           => 'string',
        'status'         => 'string',
        'next_run_at'    => 'string?',
        'repeats'        => 'boolean',
        'on_event'       => 'boolean',
        'joins'          => 'boolean',
        'trigger_only'   => 'boolean',
        'texts_withheld' => 'boolean',
        'when'           => 'string?',
        'conditions'     => 'string[]?',
        'action_type'    => 'string',
        'action_text'    => 'string?',
        'follow'         => array(array('on' => 'string', 'type' => 'string', 'text' => 'string?', 'target_id' => 'integer?')),
        'starters'       => array(array('id' => 'integer', 'name' => 'string')),
        'channel_id'     => 'integer?',
        'channel_name'   => 'string?',
        'message_id'     => 'integer?',
        'run_count'      => 'integer',
        'last_run'       => 'object?',
        'can_change'     => 'boolean',
        'created_by'     => 'integer',
        'created_at'     => 'string?',
        'updated_at'     => 'string?',
        'history'        => 'object[]?',
    );
}

/**
 * Is the row the one that starts a flow (pure)? Flows keep their own
 * routes; such a row is no scheduled action of the API's.
 *
 * @param array $row
 * @return bool
 */
function ws_api_scheduled_is_flow_anchor($row)
{
    return (int) ($row['flow_id'] ?? 0) > 0;
}

/**
 * The caller is staff in the team, and the tables are there.
 *
 * @return array the viewer
 */
function ws_api_scheduled_viewer()
{
    $viewer = ws_api_viewer();

    if (!ws_scheduled_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    if (!ws_can_schedule($viewer)) {
        api_fail(403, 'forbidden', lang('Only staff can schedule actions.'));
    }

    return $viewer;
}

/**
 * A scheduled action, never a scheduled message nor a flow's row; anything
 * else is a 404.
 *
 * @param int $id
 * @return array decoded
 */
function ws_api_scheduled_action_or_404($id)
{
    $row = ws_scheduled_action((int) $id);

    if (!$row || ws_api_scheduled_is_flow_anchor($row)) {
        api_fail_not_found(lang('Scheduled action'));
    }

    return $row;
}

function ws_api_scheduled_actions_list($params)
{
    $viewer = ws_api_scheduled_viewer();
    $where = array('1 = 1' . ws_scheduled_actions_only());
    $status = (string) ($params['status'] ?? '');

    if (isset(ws_scheduled_states()[$status])) {
        $where[] = "status = '" . e($status) . "'";
    }

    if ((int) ($params['channel_id'] ?? 0) > 0) {
        $where[] = "channel_id = '" . (int) $params['channel_id'] . "'";
    }

    if (function_exists('pg_schema_has') && pg_schema_has('ws_scheduled_actions', 'flow_id')) {
        $where[] = "flow_id = 0";
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_scheduled_actions WHERE " . implode(' AND ', $where) . "
        ORDER BY (status = 'active') DESC, IF(status = 'active' AND next_run_at > 0, next_run_at, 4294967295), id DESC LIMIT " . WS_API_SCHEDULED_LIST_MAX) as $row) {
        $out[] = ws_api_scheduled_action_present($viewer, ws_scheduled_decode($row));
    }

    api_ok_list($out, WS_API_SCHEDULED_LIST_MAX);
}

function ws_api_scheduled_actions_get($params)
{
    $viewer = ws_api_scheduled_viewer();

    api_ok(ws_api_scheduled_action_present($viewer, ws_api_scheduled_action_or_404($params['id']), true));
}

/**
 * Pause or cancel: the panel's check, the stop of a dry run, then the
 * panel's write.
 *
 * @param array  $params
 * @param string $to paused | cancelled
 */
function ws_api_scheduled_actions_set_status($params, $to)
{
    $viewer = ws_api_scheduled_viewer();
    $row = ws_api_scheduled_action_or_404($params['id']);
    $checked = ws_scheduled_set_status_check($viewer, $row, $to);

    if (!$checked['ok']) {
        if ((int) $checked['status'] === 403) {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error'], 'status');
    }

    api_dry_run_stop($to, 'workspace_scheduled_action', array('id' => (int) $row['id'], 'status' => (string) $row['status']));

    $result = ws_scheduled_set_status($viewer, $row, $to);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'status');
    }

    api_ok(ws_api_scheduled_action_present($viewer, ws_scheduled_action($row['id']), true));
}

function ws_api_scheduled_actions_pause($params)
{
    ws_api_scheduled_actions_set_status($params, 'paused');
}

function ws_api_scheduled_actions_cancel($params)
{
    ws_api_scheduled_actions_set_status($params, 'cancelled');
}
