<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what the registry's app and model agents did, on the Agents
 * card: their requests of the last 7 and 30 days by state, the next wake,
 * the push door's failures, and their newest waiting and failed requests
 * with Cancel, Ask again and Knock now.
 *
 * A request is matched to its agent by every value its row may carry
 * (ws_agent_activity_keys()), so older rows are still counted after the
 * request key's shape changes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many waiting and failed requests an agent's block lists. */
define('WS_AGENT_ACTIVITY_LIST', 10);

/* ---------------------------------------------------------------------------
   Pure helpers
   --------------------------------------------------------------------------- */

/**
 * Every ws_ai_requests.agent value a registry agent's requests may carry:
 * an app agent's rows were written as app:<id> and are written as
 * agent:<id>; a model agent's as model:<id>. Pure.
 *
 * @param array $agent kind, id
 * @return string[] empty for Claude's row or a bad id
 */
function ws_agent_activity_keys($agent)
{
    $id = (int) ($agent['id'] ?? 0);

    if ($id <= 0) {
        return array();
    }

    switch ((string) ($agent['kind'] ?? '')) {
        case 'app':
            return array('agent:' . $id, 'app:' . $id);

        case 'model':
            return array('model:' . $id);
    }

    return array();
}

/**
 * The registry agent's id a ws_ai_requests.agent value names, in either
 * shape. Pure.
 *
 * @param string $value agent:<id> | app:<id> | model:<id>
 * @return int 0 for any other value
 */
function ws_agent_activity_agent_id($value)
{
    return preg_match('/^(agent|app|model):([1-9][0-9]*)$/', (string) $value, $match) ? (int) $match[2] : 0;
}

/**
 * The column a request's status is counted under. Pure.
 *
 * @param string $status
 * @return string waiting | answered | failed | cancelled
 */
function ws_agent_activity_bucket($status)
{
    switch ((string) $status) {
        case 'answered':
        case 'failed':
        case 'cancelled':
            return (string) $status;
    }

    return 'waiting';
}

/**
 * The counts of every agent from grouped rows: the last 30 days, and the
 * last 7 within them. Pure.
 *
 * @param array[] $rows agent, status, week (1: within 7 days), n
 * @return array agent id => d7 => bucket => n, d30 => bucket => n
 */
function ws_agent_activity_counts($rows)
{
    $empty = array('waiting' => 0, 'answered' => 0, 'failed' => 0, 'cancelled' => 0);
    $out = array();

    foreach ((array) $rows as $row) {
        $id = ws_agent_activity_agent_id((string) ($row['agent'] ?? ''));

        if ($id <= 0) {
            continue;
        }

        if (!isset($out[$id])) {
            $out[$id] = array('d7' => $empty, 'd30' => $empty);
        }

        $bucket = ws_agent_activity_bucket((string) ($row['status'] ?? ''));
        $n = (int) ($row['n'] ?? 0);

        $out[$id]['d30'][$bucket] += $n;

        if ((int) ($row['week'] ?? 0) === 1) {
            $out[$id]['d7'][$bucket] += $n;
        }
    }

    return $out;
}

/**
 * One period's counts in words, the empty buckets left out. Pure.
 *
 * @param array $counts bucket => n
 * @return string
 */
function ws_agent_activity_counts_text($counts)
{
    $n = function ($bucket) use ($counts) {
        return (int) ($counts[$bucket] ?? 0);
    };
    $parts = array();

    if ($n('waiting') > 0) {
        $parts[] = lang(array('string' => '{var:1} waiting', 'vars' => $n('waiting')));
    }

    if ($n('answered') > 0) {
        $parts[] = lang(array('string' => '{var:1} answered', 'vars' => $n('answered')));
    }

    if ($n('failed') > 0) {
        $parts[] = lang(array('string' => '{var:1} failed', 'vars' => $n('failed')));
    }

    if ($n('cancelled') > 0) {
        $parts[] = lang(array('string' => '{var:1} cancelled', 'vars' => $n('cancelled')));
    }

    return empty($parts) ? lang('no requests') : implode(', ', $parts);
}

/**
 * Which buttons a request's line offers its reader: Cancel while it waits,
 * Ask again when it failed on a message, Knock now while it waits on a
 * push door. The one who asked and an administrator use them, as an MCP
 * agent's Cancel. Pure.
 *
 * @param array $viewer id, role
 * @param array $row    ws_ai_requests: status, requested_by, message_id, channel_id
 * @param array $agent  ws_agent_shape(): kind, status, door_kind
 * @return array cancel, retry, knock (bool)
 */
function ws_agent_activity_actions($viewer, $row, $agent)
{
    $none = array('cancel' => false, 'retry' => false, 'knock' => false);

    if (!is_array($viewer) || !(((int) $viewer['id'] === (int) ($row['requested_by'] ?? 0)) || ((int) $viewer['role'] === 0))) {
        return $none;
    }

    $status = (string) ($row['status'] ?? '');
    $active = ((string) ($agent['status'] ?? '') === 'active');

    return array(
        'cancel' => in_array($status, array('queued', 'sent', 'running'), true),
        'retry'  => ($status === 'failed') && $active && ((int) ($row['message_id'] ?? 0) > 0) && ((int) ($row['channel_id'] ?? 0) > 0),
        'knock'  => ($status === 'queued') && $active && ((string) ($agent['kind'] ?? '') === 'app') && ((string) ($agent['door_kind'] ?? '') === 'push'),
    );
}

/**
 * Where a request was asked, as its reader may see it: the channel's name
 * and the message's link, or neither when the reader may not read the
 * channel. A wake has no message: its channel alone. Pure.
 *
 * @param array       $row          channel_id, message_id
 * @param string|null $channel_name null when the channel is gone
 * @param bool        $can_read     ws_can_read_channel() for the reader
 * @return array name ('' when hidden), url ('' when none)
 */
function ws_agent_activity_place($row, $channel_name, $can_read)
{
    $channel_id = (int) ($row['channel_id'] ?? 0);

    if (($channel_id <= 0) || ($channel_name === null) || !$can_read) {
        return array('name' => '', 'url' => '');
    }

    $message_id = (int) ($row['message_id'] ?? 0);

    return array(
        'name' => '#' . $channel_name,
        'url'  => 'workspace.php?channel=' . $channel_id . (($message_id > 0) ? '&message=' . $message_id : ''),
    );
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * The counts of every registry agent over the last 30 days.
 *
 * @param array[] $agents ws_agent_shape()
 * @return array ws_agent_activity_counts()
 */
function ws_agent_activity_read_counts($agents)
{
    $keys = array();

    foreach ((array) $agents as $agent) {
        foreach (ws_agent_activity_keys($agent) as $key) {
            $keys[] = "'" . e($key) . "'";
        }
    }

    if (empty($keys) || !ws_ai_schema_ready()) {
        return array();
    }

    $now = time();

    return ws_agent_activity_counts((array) db_items("SELECT agent, status, (created_at > '" . ($now - 7 * 86400) . "') AS week, COUNT(*) AS n
        FROM ws_ai_requests
        WHERE agent IN (" . implode(', ', $keys) . ") AND created_at > '" . ($now - 30 * 86400) . "'
        GROUP BY agent, status, week"));
}

/**
 * An agent's newest waiting and failed requests.
 *
 * @param array $agent ws_agent_shape()
 * @return array[] ws_ai_requests rows with channel_name, channel_kind
 */
function ws_agent_activity_open($agent)
{
    $keys = array_map(function ($key) {
        return "'" . e($key) . "'";
    }, ws_agent_activity_keys($agent));

    if (empty($keys) || !ws_ai_schema_ready()) {
        return array();
    }

    return (array) db_items("SELECT r.*, c.name AS channel_name
        FROM ws_ai_requests r
        LEFT JOIN ws_channels c ON c.id = r.channel_id
        WHERE r.agent IN (" . implode(', ', $keys) . ") AND r.status IN ('queued', 'sent', 'running', 'failed')
        ORDER BY r.id DESC
        LIMIT " . WS_AGENT_ACTIVITY_LIST);
}

/**
 * One request of an agent, with the agent it belongs to.
 *
 * @param int $request_id
 * @return array|null row, agent (ws_agent_shape())
 */
function ws_agent_activity_request($request_id)
{
    if (!ws_agents_ready() || !ws_ai_schema_ready() || ((int) $request_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $request_id . "'");
    $agent = $row ? ws_agent(ws_agent_activity_agent_id((string) $row['agent'])) : null;

    if (!$agent || !in_array((string) $row['agent'], ws_agent_activity_keys($agent), true)) {
        return null;
    }

    return array('row' => $row, 'agent' => $agent);
}

/* ---------------------------------------------------------------------------
   Acting
   --------------------------------------------------------------------------- */

/**
 * Cancel, Ask again or Knock now from the Agents card.
 *
 * @param array    $viewer
 * @param string   $action agent_request_cancel | agent_request_retry | agent_request_knock
 * @param liveform $liveform
 */
function ws_agent_activity_post($viewer, $action, $liveform)
{
    $found = ws_agent_activity_request((int) ($_POST['request_id'] ?? 0));

    if (!$found) {
        $liveform->add_error(lang('That request could not be found.'));
        return;
    }

    $row = $found['row'];
    $agent = $found['agent'];
    $may = ws_agent_activity_actions($viewer, $row, $agent);

    if ($action === 'agent_request_cancel') {
        $error = $may['cancel'] ? ws_agent_activity_cancel($viewer, $row, $agent) : lang('Only the person who asked, or an administrator, can stop it.');
    } elseif ($action === 'agent_request_retry') {
        $error = $may['retry'] ? ws_agent_activity_retry($viewer, $row, $agent) : lang('Only a failed request can be asked again, by the person who asked or an administrator.');
    } else {
        $error = $may['knock'] ? (ws_agent_knock(db_item("SELECT * FROM ws_agents WHERE id = '" . (int) $agent['id'] . "'"), (int) $row['id']) ? '' : lang('The push door did not answer.'))
            : lang('Only a waiting request of a push door can be knocked, by the person who asked or an administrator.');
    }

    if ($error !== '') {
        $liveform->add_error(h($error));
        return;
    }

    $liveform->add_notice(h(($action === 'agent_request_cancel') ? lang('The request was cancelled.')
        : (($action === 'agent_request_retry') ? lang('The agent was asked again.') : lang('The push door was knocked.'))));
}

/**
 * Cancels a waiting request; the line under its message says who stopped
 * it. An agent that claimed it is refused when it answers.
 *
 * @param array $viewer
 * @param array $row
 * @param array $agent
 * @return string '' when cancelled, else why not
 */
function ws_agent_activity_cancel($viewer, $row, $agent)
{
    $reason = lang(array('string' => 'Stopped by {var:1}.', 'vars' => ws_person_name((int) $viewer['id'])));

    db("UPDATE ws_ai_requests SET status = 'cancelled', error = '" . e(mb_substr($reason, 0, 250)) . "', ai_state = NULL, answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    if ((int) mysqli_affected_rows(db::$con) <= 0) {
        return lang('The request had already ended.');
    }

    if ((int) $row['message_id'] > 0) {
        if (((string) $agent['kind'] === 'app') && ((int) $agent['app_id'] > 0)) {
            ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);
        } else {
            ws_message_touch((int) $row['message_id']);
        }
    }

    return '';
}

/**
 * Asks a failed request's agent again: a new request for the same message,
 * through the usual door and under the brakes, while the channel still
 * allows the agent.
 *
 * @param array $viewer
 * @param array $row
 * @param array $agent
 * @return string '' when asked, else why not
 */
function ws_agent_activity_retry($viewer, $row, $agent)
{
    $rows = ws_agent_channel_rows((int) $row['channel_id']);
    $channel_row = null;

    foreach ($rows as $candidate) {
        if ((int) $candidate['id'] === (int) $agent['id']) {
            $channel_row = $candidate;
        }
    }

    $value = $channel_row ? ws_agent_request_value($channel_row) : '';

    if ($value === '') {
        return lang('The channel no longer allows this agent.');
    }

    $keys = array_map(function ($key) {
        return "'" . e($key) . "'";
    }, ws_agent_activity_keys($agent));
    $last_hour = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests WHERE agent IN (" . implode(', ', $keys) . ") AND created_at > '" . (time() - 3600) . "'");
    $apps = array_values(array_filter(array_map(function ($candidate) {
        return (int) $candidate['app_id'];
    }, $rows)));
    $brake = ws_agent_brake(ws_agent_chain_depth(ws_agent_chain((int) $row['message_id'], $apps)), $last_hour);

    if ($brake === 'depth') {
        return lang('Agents have answered one another too many times in a row. A person\'s message starts the count again.');
    }

    if ($brake !== '') {
        return lang('It was woken too often in the last hour.');
    }

    list($pv_column, $pv_value) = pg_ai_prompt_version_insert();

    db("INSERT INTO ws_ai_requests (channel_id, message_id, requested_by, status, agent, created_at" . $pv_column . ")
        VALUES ('" . (int) $row['channel_id'] . "', '" . (int) $row['message_id'] . "', '" . (int) $viewer['id'] . "', 'queued', '" . e($value) . "', '" . time() . "'" . $pv_value . ")");

    $request_id = (int) mysqli_insert_id(db::$con);

    if ($request_id <= 0) {
        return lang('The request could not be saved.');
    }

    if (((string) $channel_row['kind'] === 'app') && ((string) $channel_row['door_kind'] === 'push')) {
        ws_agent_knock($channel_row, $request_id);
    }

    ws_message_touch((int) $row['message_id']);

    return '';
}

/* ---------------------------------------------------------------------------
   The card
   --------------------------------------------------------------------------- */

/**
 * An agent's activity under its row on the Agents card.
 *
 * @param string $self_url
 * @param array  $viewer
 * @param array  $agent  ws_agent_shape()
 * @param array  $counts ws_agent_activity_counts() of every agent
 * @return string
 */
function ws_agent_activity_block($self_url, $viewer, $agent, $counts)
{
    if (empty(ws_agent_activity_keys($agent))) {
        return '';
    }

    $mine = $counts[(int) $agent['id']] ?? array('d7' => array(), 'd30' => array());
    $facts = array(
        lang('Last 7 days') . ': ' . ws_agent_activity_counts_text($mine['d7']),
        lang('Last 30 days') . ': ' . ws_agent_activity_counts_text($mine['d30']),
    );

    if ((int) $agent['wake_next_at'] > 0) {
        $facts[] = lang(array('string' => 'Next wake: {var:1}', 'vars' => date('d.m.Y H:i', (int) $agent['wake_next_at'])));
    }

    // A poll door is never knocked: its failures say nothing.
    if (((int) $agent['door_failures'] > 0) && (($agent['kind'] === 'model') || ($agent['door_kind'] === 'push'))) {
        $facts[] = lang(array('string' => 'Door failures in a row: {var:1}', 'vars' => (int) $agent['door_failures']));
    }

    $lines = '';

    foreach (ws_agent_activity_open($agent) as $row) {
        $state = ws_agent_request_state($row);
        $channel = ((int) $row['channel_id'] > 0) ? ws_channel((int) $row['channel_id']) : null;
        $place = ws_agent_activity_place($row, is_array($channel) ? (string) $row['channel_name'] : null, is_array($channel) && ws_can_read_channel($viewer, $channel));
        $may = ws_agent_activity_actions($viewer, $row, $agent);
        $asker = ((int) $row['requested_by'] > 0) ? ws_person_name((int) $row['requested_by']) : '';
        $button = function ($action, $label, $theme) {
            return '<button type="submit" name="ws_action" value="' . $action . '" class="btn btn-sm btn-text ' . $theme . '">' . h($label) . '</button>';
        };
        $buttons = ($may['cancel'] ? $button('agent_request_cancel', lang('Cancel'), 'theme-danger') : '')
            . ($may['retry'] ? $button('agent_request_retry', lang('Ask again'), 'theme-primary') : '')
            . ($may['knock'] ? $button('agent_request_knock', lang('Knock now'), 'theme-secondary') : '');
        $where = ($place['url'] !== '') ? '<a href="' . h($place['url']) . '">' . h($place['name']) . '</a>' : h($place['name']);

        $lines .= '
            <li class="d-flex flex-wrap align-items-center gap-2 py-1">
                <i class="bi ' . h($state['icon']) . '" aria-hidden="true"></i>
                <span class="ws-grow">' . h($state['label']) . '</span>
                <span class="small fg-secondary">' . h(date('d.m.Y H:i', (int) $row['created_at'])) . (($asker !== '') ? ' · ' . h($asker) : '') . (($where !== '') ? ' · ' . $where : '') . '</span>
                ' . (($buttons !== '') ? '<form method="post" action="' . h($self_url) . '#ws-agent-' . (int) $agent['id'] . '" class="d-inline-flex gap-1">' . get_token_field()
                    . '<input type="hidden" name="request_id" value="' . (int) $row['id'] . '">' . $buttons . '</form>' : '') . '
            </li>';
    }

    return '
        <div class="small mt-3" id="ws-agent-' . (int) $agent['id'] . '-activity">
            <div class="fg-secondary">' . h(implode(' · ', $facts)) . '</div>
            ' . (($lines !== '') ? '<div class="fw-semibold mt-2">' . h(lang('Waiting and failed requests')) . '</div><ul class="list-unstyled mb-0">' . $lines . '</ul>' : '') . '
        </div>';
}
