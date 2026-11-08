<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API of an application agent's requests
 * (agent_queue.php): the requests waiting for the calling agent, a claim
 * that one run wins (409 for the other), an answer under the message that
 * asked, a fail. Only the key of an active app agent, used as itself, may
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
    );
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
 * still allowed there, through the person who asked where the owner of the
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

    if (!$channel || ((int) db_value("SELECT COUNT(*) FROM ws_channel_agents
            WHERE channel_id = '" . (int) $channel['id'] . "' AND agent_id = '" . (int) $agent['id'] . "' AND allowed = 1") === 0)) {
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
        $place = ws_api_agent_request_place($viewer, $agent, $row);

        if (!$place) {
            api_fail(403, 'forbidden', lang('The answer cannot be posted there any more: the agent is no longer allowed in that channel, or the person who asked can no longer write there.'));
        }

        $text = trim((string) $params['text']);
        $mention = '<@user:' . (int) $row['requested_by'] . '>';

        if (strpos($text, $mention) === false) {
            $text = $mention . ' ' . $text;
        }

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
        ws_api_log(lang(array('string' => '@{var:1} answered a request in #{var:2}', 'vars' => array((string) $agent['handle'], (string) $place['channel']['name']))));
    } else {
        api_dry_run_stop('answered', 'workspace_agent_request', array('id' => (int) $row['id'], 'no_answer' => true));
    }

    db("UPDATE ws_ai_requests SET status = 'answered', reply_message_id = '" . $reply_id . "',
            answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);
    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '✅', true);

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

    if ($place && ($reason !== '')) {
        $sent = ws_message_send($place['writer'], $place['channel'], '<@user:' . (int) $row['requested_by'] . '> ' . $reason, array(
            'parent_id' => (int) $row['message_id'],
            'app_id'    => (int) $agent['app_id'],
        ));
        $reply_id = $sent['ok'] ? (int) $sent['message_id'] : 0;
    }

    db("UPDATE ws_ai_requests SET status = 'failed', error = '" . e($reason) . "', reply_message_id = '" . $reply_id . "', answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);

    api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])));
}
