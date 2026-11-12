<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API of an application agent's requests
 * (agent_queue.php): the requests waiting for the calling agent, a claim
 * that one run wins (409 for the other), an answer under the message that
 * asked, a fail, the brief; and the agent's own scheduled wakes
 * (agent_wake.php). Only the key of an active app agent, used as itself, may
 * call them: a device or OAuth session of the same application is its
 * person. The agent reads the channel with its owner's reach, as every
 * application does.
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
 * The agent request routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_agent_request_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $only = ' For the key of an active application agent (Workspace Settings, Agents) only, used as itself: any other caller 403 forbidden.';

    return array(
        array(
            'id'          => 'workspace.agent_requests.list',
            'method'      => 'GET',
            'path'        => '/workspace/agent-requests',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_agent_requests_list',
            'mcp'         => false,
            'returns'     => array('list' => 'WorkspaceClaudeRequest'),
            'summary'     => 'The requests waiting for the calling agent',
            'description' => 'A message that woke the agent in a channel (its handle, its wake rule) made one request for it. status open (the default) is what waits to be claimed, oldest first; all is the latest 50 of every state.' . $only,
            'params'      => array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'running', 'answered', 'failed', 'cancelled', 'all'), 'default' => 'open'),
            ),
        ),
        array(
            'id'          => 'workspace.agent_requests.claim',
            'method'      => 'POST',
            'path'        => '/workspace/agent-requests/{id}/claim',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_agent_request_claim',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Take a request',
            'description' => 'Marks the request as being worked on and leaves an eye on the message that asked. 409 already_claimed when another run has taken it, 409 closed when it is closed.' . $only,
            'params'      => array($id),
        ),
        array(
            'id'          => 'workspace.agent_requests.answer',
            'method'      => 'POST',
            'path'        => '/workspace/agent-requests/{id}/answer',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_agent_request_answer',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Answer a request',
            'description' => 'Writes text under the message that asked, as the application, with the person who asked mentioned, and swaps the eye for a tick. no_answer true closes the request without writing anything (it takes no text). Other things the agent does (a task, a poll, a proposal) go through their own routes. 403 when the channel no longer lets the agent answer or the person who asked can no longer write there; 409 closed when the request is closed.' . $only,
            'params'      => array(
                $id,
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3900, 'description' => 'The answer; required unless no_answer is true.'),
                array('name' => 'no_answer', 'in' => 'body', 'type' => 'bool', 'description' => 'Close the request without writing anything: it needs no answer.'),
            ),
        ),
        array(
            'id'          => 'workspace.agent_requests.fail',
            'method'      => 'POST',
            'path'        => '/workspace/agent-requests/{id}/fail',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_agent_request_fail',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Give a request up',
            'description' => 'Closes the request as failed with the reason, told to the person who asked under their message while they may still write there. 409 closed when the request is closed.' . $only,
            'params'      => array(
                $id,
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 250, 'required' => true),
            ),
        ),
        array(
            'id'          => 'workspace.agent_requests.brief',
            'method'      => 'GET',
            'path'        => '/workspace/agent-requests/{id}/brief',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_agent_request_brief',
            'mcp'         => false,
            'returns'     => 'WorkspaceClaudeBrief',
            'summary'     => 'What the agent is told before it starts',
            'description' => 'One text: who the agent is, now, who asked, where (the channel or discussion, or the schedule that woke it and the channels it is in), the request inside a data block, the channel\'s memory index and decision lines, the loop-guard text unless the channel switched it off, the site\'s MCP address and the scopes of the key, the routes that close the request, the language of the answer and the rules. A wake request (no message) carries its note as the request.' . $only,
            'params'      => array($id),
        ),
        array(
            'id'          => 'workspace.agent_wake.get',
            'method'      => 'GET',
            'path'        => '/workspace/agent-wake',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_agent_wake_get',
            'mcp'         => false,
            'returns'     => ws_api_agent_wake_schema(),
            'summary'     => 'The calling agent\'s scheduled wakes',
            'description' => 'every: minutes between two wakes, 0 for a one-time wake or none; next_at: the next wake, null for none; note: what each wake request carries.' . $only,
            'params'      => array(),
        ),
        array(
            'id'          => 'workspace.agent_wake.set',
            'method'      => 'POST',
            'path'        => '/workspace/agent-wake',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_agent_wake_set',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => ws_api_agent_wake_schema(),
            'summary'     => 'Set the calling agent\'s scheduled wakes',
            'description' => 'The same as /wake @agent in a channel, for the agent itself: every (minutes, 1-1440) wakes it every so often from now; at (a moment to come) once; off clears the schedule. Give exactly one of them. Each wake makes a request with no message, carrying the note, and knocks a push door; the hourly brake holds. A person sets it with /wake in a channel or on the Agents card.' . $only,
            'params'      => array(
                array('name' => 'every', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => WS_AGENT_WAKE_EVERY_MAX),
                array('name' => 'at', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'off', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => WS_AGENT_WAKE_NOTE_MAX),
            ),
        ),
    );
}

// What the wake routes return.
function ws_api_agent_wake_schema()
{
    return array('handle' => 'string', 'every' => 'integer', 'next_at' => 'string?', 'note' => 'string');
}

/**
 * An agent's schedule as the wake routes give it. Pure but for api_time().
 *
 * @param array $agent wake_every, wake_next_at, wake_note, handle
 * @return array
 */
function ws_api_agent_wake_present($agent)
{
    $next = (int) ($agent['wake_next_at'] ?? 0);

    return array(
        'handle'  => (string) ($agent['handle'] ?? ''),
        'every'   => (int) ($agent['wake_every'] ?? 0),
        'next_at' => ($next > 0) ? api_time($next) : null,
        'note'    => (string) ($agent['wake_note'] ?? ''),
    );
}

function ws_api_agent_wake_get($params)
{
    api_ok(ws_api_agent_wake_present(ws_api_agent_caller()));
}

function ws_api_agent_wake_set($params)
{
    $agent = ws_api_agent_caller();
    $now = time();
    $given = array_values(array_filter(array('every', 'at', 'off'), function ($name) use ($params) {
        return isset($params[$name]) && ($params[$name] !== null) && ($params[$name] !== false);
    }));

    if (count($given) !== 1) {
        api_fail_validation(lang('Give exactly one of every, at or off.'), 'every');
    }

    $parsed = array('action' => $given[0], 'every' => (int) ($params['every'] ?? 0), 'at' => (int) ($params['at'] ?? 0), 'note' => trim((string) ($params['note'] ?? '')));

    if (($parsed['action'] === 'at') && ($parsed['at'] <= $now)) {
        api_fail_validation(lang('That time has passed. Choose a time to come.'), 'at');
    }

    $values = ws_agent_wake_values($parsed, $now);

    api_dry_run_stop('set', 'workspace_agent_wake', ws_api_agent_wake_present(array('handle' => $agent['handle']) + $values));

    ws_agent_wake_save((int) $agent['id'], $values);
    ws_api_log(lang(array('string' => '@{var:1} set its wakes', 'vars' => (string) $agent['handle'])));

    api_ok(ws_api_agent_wake_present(array('handle' => $agent['handle']) + $values));
}

function ws_api_agent_request_brief($params)
{
    $agent = ws_api_agent_caller();
    $row = ws_api_agent_request_or_404($agent, $params['id']);

    api_ok(array('request_id' => (int) $row['id'], 'text' => ws_agent_brief($agent, $row, api_current_scopes())));
}

/**
 * The calling agent: the active app agent whose application's key is used
 * as itself. 403 for any other caller.
 *
 * @return array the raw ws_agents row
 */
function ws_api_agent_caller()
{
    $app_id = ws_api_app_id();
    $row = (ws_agents_ready() && ($app_id > 0) && (!function_exists('api_current_device') || (api_current_device() === null)))
        ? db_item("SELECT * FROM ws_agents WHERE kind = 'app' AND status = 'active' AND app_id = '" . $app_id . "' ORDER BY id LIMIT 1")
        : null;

    if (!$row) {
        api_fail(403, 'forbidden', lang('Only an active application agent, with its own key, may use this endpoint.'));
    }

    return (array) $row;
}

/**
 * One of the calling agent's requests, 404 for any other.
 *
 * @param array $agent
 * @param int   $id
 * @return array
 */
function ws_api_agent_request_or_404($agent, $id)
{
    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $id . "' AND agent = '" . e(ws_agent_request_value($agent)) . "'");

    if (!is_array($row)) {
        api_fail_not_found(lang('Request'));
    }

    return $row;
}

/**
 * Where the answer is written: the request's channel, while the agent is
 * still allowed there (in a discussion, by the channel it is about too), through the person who asked where the owner of the
 * application is not in the room (ws_assistant_writer()).
 *
 * @param array $viewer
 * @param array $agent
 * @param array $row
 * @return array|null channel, writer
 */
function ws_api_agent_request_place($viewer, $agent, $row)
{
    $channel = ws_channel($row['channel_id']);

    // In a discussion the channel it is about may allow the agent, read now.
    if (!$channel || !in_array((int) $agent['id'], array_map('intval', array_column(ws_agent_reach_rows($channel), 'id')), true)) {
        return null;
    }

    $writer = ws_assistant_writer($viewer, ws_change_viewer_for_id((int) $row['requested_by']), $channel);

    return $writer ? array('channel' => $channel, 'writer' => $writer) : null;
}

function ws_api_agent_requests_list($params)
{
    $agent = ws_api_agent_caller();
    $viewer = ws_api_viewer();
    $value = e(ws_agent_request_value($agent));
    $status = (string) ($params['status'] ?? 'open');

    if ($status === 'all') {
        $rows = (array) db_items("SELECT * FROM ws_ai_requests WHERE agent = '" . $value . "' ORDER BY id DESC LIMIT 50");
    } else {
        $where = ($status === 'open') ? "status IN ('queued', 'sent')" : "status = '" . e($status) . "'";
        $rows = (array) db_items("SELECT * FROM ws_ai_requests WHERE agent = '" . $value . "' AND " . $where . " ORDER BY id ASC LIMIT 50");
    }

    api_ok_list(array_map(function ($row) use ($viewer) {
        return ws_api_claude_request_present($viewer, $row);
    }, $rows), 50);
}

function ws_api_agent_request_claim($params)
{
    $agent = ws_api_agent_caller();
    $viewer = ws_api_viewer();
    $row = ws_api_agent_request_or_404($agent, $params['id']);

    if ($row['status'] === 'running') {
        api_fail(409, 'already_claimed', lang('Another run is working on this request.'));
    }

    if (!in_array($row['status'], array('queued', 'sent'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    api_dry_run_stop('claimed', 'workspace_agent_request', array('id' => (int) $row['id']));

    db("UPDATE ws_ai_requests SET status = 'running', claimed_at = '" . time() . "', error = ''
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent')");

    if (mysqli_affected_rows(db::$con) < 1) {
        api_fail(409, 'already_claimed', lang('Another run is working on this request.'));
    }

    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', true);

    api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])));
}

function ws_api_agent_request_answer($params)
{
    $agent = ws_api_agent_caller();
    $viewer = ws_api_viewer();
    $row = ws_api_agent_request_or_404($agent, $params['id']);

    if (!in_array($row['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    $refused = ws_api_claude_answer_input_refusal('channel', $params);

    if ($refused['field'] !== '') {
        api_fail_validation($refused['error'], $refused['field']);
    }

    $now = time();
    $reply_id = 0;

    if (empty($params['no_answer'])) {
        // A wake made in no channel: the agent writes where it needs with
        // workspace.messages.create and closes the request with no_answer.
        if (((int) $row['channel_id'] === 0) && ws_agent_wake_of($row)) {
            api_fail_validation(lang('This wake was made in no channel: write with workspace.messages.create, then close it with no_answer.'), 'text');
        }

        $place = ws_api_agent_request_place($viewer, $agent, $row);

        if (!$place) {
            api_fail(403, 'forbidden', lang('The answer cannot be posted there any more: the agent is no longer allowed in that channel, or the person who asked can no longer write there.'));
        }

        $text = ws_agent_answer_text(trim((string) $params['text']), (int) $row['requested_by'], ws_agent_wake_of($row) || ws_agent_request_asker_app($row));

        $options = array('parent_id' => (int) $row['message_id'], 'app_id' => (int) $agent['app_id']);
        $checked = ws_message_send_check($place['writer'], $place['channel'], $text, $options);

        if (!$checked['ok']) {
            api_fail_validation($checked['error'], 'text');
        }

        api_dry_run_stop('answered', 'workspace_agent_request', array('id' => (int) $row['id'], 'channel_id' => (int) $place['channel']['id']));

        $sent = ws_message_send($place['writer'], $place['channel'], $text, $options);

        if (!$sent['ok']) {
            api_fail_validation($sent['error'], 'text');
        }

        $reply_id = (int) $sent['message_id'];

        // The answer wakes the agents it names, the asking one included, or
        // the one whose rule wakes it on answers to its messages.
        if (function_exists('ws_api_assistants_carry_on')) {
            ws_api_assistants_carry_on(ws_agent_after_app_send($place['writer'], $place['channel'], $reply_id, $text));
        }

        ws_api_log(lang(array('string' => '@{var:1} answered a request in #{var:2}', 'vars' => array((string) $agent['handle'], (string) $place['channel']['name']))));
    } else {
        api_dry_run_stop('answered', 'workspace_agent_request', array('id' => (int) $row['id'], 'no_answer' => true));
    }

    db("UPDATE ws_ai_requests SET status = 'answered', reply_message_id = '" . $reply_id . "',
            answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);
    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '✅', true);

    // A flow's agent box waiting on this request goes on (flow_agent_box.php).
    if (function_exists('ws_step_agent_request_closed')) {
        ws_step_agent_request_closed((int) $row['id']);
    }

    api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])));
}

function ws_api_agent_request_fail($params)
{
    $agent = ws_api_agent_caller();
    $viewer = ws_api_viewer();
    $row = ws_api_agent_request_or_404($agent, $params['id']);

    if (!in_array($row['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    $reason = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $params['reason']), 0, 250));
    $place = ws_api_agent_request_place($viewer, $agent, $row);

    api_dry_run_stop('failed', 'workspace_agent_request', array('id' => (int) $row['id'], 'told_in_channel' => (bool) $place));

    $reply_id = 0;

    // A wake was asked by nobody: there is nobody to tell.
    if ($place && ($reason !== '') && !ws_agent_wake_of($row)) {
        $sent = ws_message_send($place['writer'], $place['channel'], ws_agent_answer_text($reason, (int) $row['requested_by'], (bool) ws_agent_request_asker_app($row)), array(
            'parent_id' => (int) $row['message_id'],
            'app_id'    => (int) $agent['app_id'],
        ));
        $reply_id = $sent['ok'] ? (int) $sent['message_id'] : 0;
    }

    db("UPDATE ws_ai_requests SET status = 'failed', error = '" . e($reason) . "', reply_message_id = '" . $reply_id . "', answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);

    if (function_exists('ws_step_agent_request_closed')) {
        ws_step_agent_request_closed((int) $row['id']);
    }

    api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])));
}
