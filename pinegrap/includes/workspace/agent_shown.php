<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - how the registry's app and model agents (agents.php) show
 * where people work: the line under a message that woke one, their tag
 * (<@app:N> of the agent's application) read as the agent's @handle, the
 * @ picker of a channel they are allowed in, their entries in
 * ws_assistants(), and the change or deletion of an agent's own messages
 * through the API.
 *
 * An agent is asked in its channels: it is listed among the assistants for
 * its name and picture, never as one a task is handed to.
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
 * The registry agent a ws_ai_requests.agent value names. Pure.
 *
 * @param string $value agent:<id> | model:<id>; app:<n> is a proposal's
 * @return array|null kind (app | model), id; null for any other value
 */
function ws_agent_request_parse($value)
{
    if (!preg_match('/^(agent|model):([1-9][0-9]*)$/', (string) $value, $match)) {
        return null;
    }

    return array('kind' => ($match[1] === 'agent') ? 'app' : 'model', 'id' => (int) $match[2]);
}

/**
 * The active app and model agents that write through an application, read
 * once per request.
 *
 * @return array[] raw ws_agents rows by id
 */
function ws_agents_shown()
{
    static $rows = null;

    if ($rows !== null) {
        return $rows;
    }

    $rows = array();

    if (!function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return $rows;
    }

    foreach ((array) db_items("SELECT * FROM ws_agents
        WHERE status = 'active' AND kind IN ('app', 'model') AND app_id > 0
        ORDER BY handle") as $row) {
        $rows[(int) $row['id']] = $row;
    }

    return $rows;
}

/**
 * The picture an agent is drawn with: its own, or the assistants' one.
 *
 * @param array $agent
 * @return string
 */
function ws_agent_avatar_url($agent)
{
    $avatar = trim((string) ($agent['avatar'] ?? ''));

    return ($avatar !== '') ? $avatar : PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-ai.svg';
}

/**
 * The agents' entries of ws_assistants(): name and picture for their
 * requests and runs. delegate false keeps them out of the task drawer and
 * ws_task_delegate(): an agent is asked in its channels.
 *
 * @return array key => key, name, handle, icon, avatar, app_id, agent_id, delegate
 */
function ws_agent_assistants()
{
    $list = array();

    foreach (ws_agents_shown() as $row) {
        $key = ws_agent_request_value($row);

        if ($key === '') {
            continue;
        }

        $list[$key] = array(
            'key'      => $key,
            'name'     => ((string) $row['name'] !== '') ? (string) $row['name'] : '@' . $row['handle'],
            'handle'   => '@' . $row['handle'],
            'icon'     => 'bi-robot',
            'avatar'   => ws_agent_avatar_url($row),
            'app_id'   => (int) $row['app_id'],
            'agent_id' => (int) $row['id'],
            'delegate' => false,
        );
    }

    return $list;
}

/**
 * The agents' tags by their application: an agent's application reads as
 * the agent's @handle with its name beside it (ws_app_tag()).
 *
 * @return array app_id => handle, name
 */
function ws_agent_tags()
{
    $tags = array();

    foreach (ws_agents_shown() as $row) {
        if (!isset($tags[(int) $row['app_id']])) {
            $tags[(int) $row['app_id']] = array('handle' => (string) $row['handle'], 'name' => (string) $row['name']);
        }
    }

    return $tags;
}

/**
 * The line under a message that woke an agent, in words. Pure.
 *
 * @param string $status    the request's status
 * @param string $handle    the agent's @handle
 * @param string $error     the request's error
 * @param int    $next_try  when a failed door or model is tried again (0: not held)
 * @param int    $now
 * @param bool   $replied   an answered request left a reply; false: closed without text
 * @return array label, icon
 */
function ws_agent_request_label($status, $handle, $error, $next_try, $now, $replied = true)
{
    switch ((string) $status) {
        case 'queued':
            return ((int) $next_try > (int) $now)
                ? array('label' => lang(array('string' => 'Waiting: {var:1} could not be reached. Next try: {var:2}.', 'vars' => array($handle, date('H:i', (int) $next_try)))), 'icon' => 'bi-hourglass-split')
                : array('label' => lang(array('string' => 'Waiting for {var:1}', 'vars' => $handle)), 'icon' => 'bi-hourglass-split');

        case 'sent':
        case 'running':
            return array('label' => lang(array('string' => '{var:1} is working on it', 'vars' => $handle)), 'icon' => 'bi-robot');

        case 'answered':
            // Closed with no_answer: the agent saw it and wrote nothing.
            return $replied
                ? array('label' => lang(array('string' => '{var:1} answered', 'vars' => $handle)), 'icon' => 'bi-check2-circle')
                : array('label' => lang(array('string' => '{var:1} saw it · no answer needed', 'vars' => $handle)), 'icon' => 'bi-eye');

        case 'cancelled':
            return array('label' => trim(lang('Cancelled') . ': ' . (string) $error, ': '), 'icon' => 'bi-x-circle');
    }

    return array('label' => trim(lang(array('string' => '{var:1} could not do it', 'vars' => $handle)) . ': ' . (string) $error, ': '), 'icon' => 'bi-exclamation-circle');
}

/**
 * Whether a request left a reply: a message, or an answer written in a note.
 * Pure.
 *
 * @param array $row ws_ai_requests: reply_message_id, note_reply when read
 * @return bool
 */
function ws_agent_request_replied($row)
{
    return ((int) ($row['reply_message_id'] ?? 0) > 0) || (trim((string) ($row['note_reply'] ?? '')) !== '');
}

/**
 * Whether a request was closed without text (no_answer): answered, no
 * reply. No column of its own. Pure.
 *
 * @param array $row ws_ai_requests: status, reply_message_id, note_reply when read
 * @return bool
 */
function ws_agent_request_no_answer($row)
{
    return ((string) ($row['status'] ?? '') === 'answered') && !ws_agent_request_replied($row);
}

/**
 * Where an agent's request stands, as the line under its message says it
 * (ws_claude_request_state()'s shape). A push door or a model that failed
 * says when it is tried again.
 *
 * @param array $row ws_ai_requests
 * @return array id, status, label, icon, session_url, agent
 */
function ws_agent_request_state($row)
{
    $parsed = ws_agent_request_parse((string) $row['agent']);
    $agent = ($parsed && function_exists('ws_agents_ready') && ws_agents_ready())
        ? db_item("SELECT handle, kind, door_kind, door_failures, door_last_knock FROM ws_agents WHERE id = '" . (int) $parsed['id'] . "'")
        : null;

    $handle = is_array($agent) ? '@' . $agent['handle'] : lang('The agent');
    $next_try = 0;

    // A poll door is never knocked: its failures say nothing of the queue.
    if (is_array($agent) && ((int) $agent['door_failures'] > 0) && (($agent['kind'] === 'model') || ($agent['door_kind'] === 'push'))) {
        $next_try = (int) $agent['door_last_knock'] + ws_model_hold_seconds((int) $agent['door_failures']);
    }

    $said = ws_agent_request_label((string) $row['status'], $handle, (string) $row['error'], $next_try, time(), ws_agent_request_replied($row));

    return array(
        'id'          => (int) $row['id'],
        'status'      => (string) $row['status'],
        'label'       => $said['label'],
        'icon'        => $said['icon'],
        'session_url' => '',
        'agent'       => $parsed ? $parsed['kind'] : 'app',
    );
}

/**
 * The application whose own messages the API caller may change or delete:
 * an active app agent's key used as itself (no device). 0 for any other
 * caller, which goes by the person's own words (ws_api_own_words_only()).
 *
 * @return int
 */
function ws_api_agent_own_app()
{
    $app_id = function_exists('ws_api_app_id') ? (int) ws_api_app_id() : 0;

    if (($app_id <= 0) || !function_exists('ws_agents_ready') || !ws_agents_ready()
        || (function_exists('api_current_device') && (api_current_device() !== null))) {
        return 0;
    }

    return db_value("SELECT id FROM ws_agents WHERE kind = 'app' AND status = 'active' AND app_id = '" . $app_id . "' LIMIT 1") ? $app_id : 0;
}

/**
 * The @ picker's entries for the agents a channel allows: the agent's
 * application tag, read as its @handle, so the pick wakes it under any
 * wake rule (ws_agent_wake_matches()). Pure.
 *
 * @param array[] $rows ws_agent_channel_rows(): handle, name, app_id
 * @return array[] label, meta, icon, token, shown; by handle
 */
function ws_agent_picker_entries($rows)
{
    $entries = array();

    foreach ((array) $rows as $row) {
        $app_id = (int) ($row['app_id'] ?? 0);
        $handle = (string) ($row['handle'] ?? '');

        if (($app_id <= 0) || ($handle === '') || isset($entries[$app_id])) {
            continue;
        }

        $entries[$app_id] = array(
            'label' => '@' . $handle,
            'meta'  => ((string) ($row['name'] ?? '') !== '') ? (string) $row['name'] : lang('The agent'),
            'icon'  => 'bi-robot',
            'token' => '<@app:' . $app_id . '>',
            'shown' => '@' . $handle,
        );
    }

    usort($entries, function ($a, $b) {
        return strcmp($a['label'], $b['label']);
    });

    return $entries;
}
