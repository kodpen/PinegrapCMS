<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's agents in a message's "Who has seen it" drawer
 * (ws_message_seen_by()). An agent leaves no read mark, so it is counted
 * from what the message made it do: the request the message woke it with
 * (ws_ai_requests, taken or answered, with the time), or its answer or
 * reaction under the message. An agent the message made no request for is
 * named apart, never as seen. No table or column of its own.
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
 * The agents a reader of the channel sees beside its people, with the keys
 * their requests carry: Claude and Pinegrap AI where they can be asked
 * there, then the channel's registry agents its row allows. Each one's
 * application, so its answers and reactions are known. Empty in a guest's
 * room or a channel shared with guests, where no assistant is asked.
 *
 * @param array $channel ws_channels row
 * @return array[] key, keys, app_id, name, handle, tone, assistant
 */
function ws_message_seen_agents_of($channel)
{
    $out = array();
    $guests = (function_exists('ws_channel_is_guest') && ws_channel_is_guest($channel))
        || (function_exists('ws_channel_share_open') && ws_channel_share_open($channel));

    if ($guests) {
        return $out;
    }

    $claude = function_exists('ws_claude_channel_state') ? ws_claude_channel_state($channel) : null;

    if (!empty($claude['available'])) {
        $out[] = array('key' => 'claude', 'keys' => array('claude'), 'app_id' => (int) ws_claude_config()['app_id'],
            'name' => 'Claude', 'handle' => '', 'tone' => 3, 'assistant' => true);
    }

    $ai = function_exists('ws_ai_channel_state') ? ws_ai_channel_state($channel) : null;

    if (!empty($ai['available'])) {
        $out[] = array('key' => 'ai', 'keys' => array('ai'), 'app_id' => (int) ws_ai_config()['app_id'],
            'name' => 'Pinegrap AI', 'handle' => '', 'tone' => 1, 'assistant' => true);
    }

    foreach (ws_channel_agents($channel['id']) as $row) {
        $agent = (array) $row['agent'];

        if (empty($row['allowed']) || !in_array($agent['kind'] ?? '', array('app', 'model'), true)) {
            continue;
        }

        $id = (int) $agent['id'];
        $out[] = array('key' => 'agent-' . $id, 'keys' => array('agent:' . $id, 'model:' . $id), 'app_id' => (int) ($agent['app_id'] ?? 0),
            'name' => ((string) $agent['name'] !== '') ? (string) $agent['name'] : '@' . $agent['handle'],
            'handle' => (string) $agent['handle'], 'tone' => $id, 'assistant' => (($agent['kind'] ?? '') === 'model'));
    }

    return $out;
}

/**
 * Sorts a channel's agents for one message into seen, woken but not taken
 * yet, and not woken. Pure.
 *
 * Seen: the agent's newest request for the message was taken (claimed,
 * running, failed after it was taken) or answered, or the agent answered
 * or reacted under the message. Waiting: a request it has not taken (a
 * queued, sent, or cancelled or failed before it was taken). Not woken:
 * the rest. The agent that wrote the message is left out.
 *
 * @param array[] $agents   ws_message_seen_agents_of()
 * @param array[] $requests ws_ai_requests rows of the message (agent, status, created_at, claimed_at, answered_at), oldest first
 * @param array   $marks    app id => array(state answered|reacted, at)
 * @param int     $writer   the application that wrote the message, 0 for none
 * @return array seen, waiting, quiet (agent arrays with state and at added)
 */
function ws_message_seen_agents_sort($agents, $requests, $marks, $writer = 0)
{
    $newest = array();

    foreach ((array) $requests as $request) {
        $newest[(string) $request['agent']] = $request;
    }

    $rank = array('answered' => 3, 'took' => 2, 'reacted' => 1);
    $out = array('seen' => array(), 'waiting' => array(), 'quiet' => array());

    foreach ((array) $agents as $agent) {
        $app = (int) ($agent['app_id'] ?? 0);

        if (($writer > 0) && ($app === (int) $writer)) {
            continue;
        }

        $request = null;

        foreach ((array) $agent['keys'] as $key) {
            if (isset($newest[$key]) && (($request === null) || ((int) $newest[$key]['created_at'] >= (int) $request['created_at']))) {
                $request = $newest[$key];
            }
        }

        $state = '';
        $at = 0;

        if ($request !== null) {
            $status = (string) $request['status'];

            if ($status === 'answered') {
                $state = 'answered';
                $at = (int) $request['answered_at'];
            } elseif (($status === 'running') || ((int) $request['claimed_at'] > 0)) {
                $state = 'took';
                $at = (int) $request['claimed_at'];
            }
        }

        if (($app > 0) && isset($marks[$app]) && (($state === '') || ($rank[$marks[$app]['state']] > $rank[$state]))) {
            $state = (string) $marks[$app]['state'];
            $at = (int) $marks[$app]['at'];
        }

        if ($state !== '') {
            $out['seen'][] = $agent + array('state' => $state, 'at' => $at);
        } elseif ($request !== null) {
            $out['waiting'][] = $agent + array('state' => 'waiting', 'at' => (int) $request['created_at']);
        } else {
            $out['quiet'][] = $agent + array('state' => 'quiet', 'at' => 0);
        }
    }

    return $out;
}

/**
 * An agent as the drawer draws it: its face, name, badge and what it did
 * with the time in short words. Pure but for lang().
 *
 * @param array $agent ws_message_seen_agents_sort() entry
 * @param int   $now
 * @return array key, name, handle, tone, assistant, state, line
 */
function ws_message_seen_agent_row($agent, $now)
{
    $when = ($agent['at'] > 0) ? ws_seen_ago_words($agent['at'], $now) : '';

    switch ($agent['state']) {
        case 'answered':
            $line = lang(array('string' => 'answered {var:1}', 'vars' => $when));
            break;
        case 'took':
            $line = lang(array('string' => 'took it {var:1}', 'vars' => $when));
            break;
        case 'reacted':
            $line = lang(array('string' => 'reacted {var:1}', 'vars' => $when));
            break;
        case 'waiting':
            $line = lang('woken, not taken yet');
            break;
        default:
            $line = '';
    }

    return array(
        'key'       => (string) $agent['key'],
        'name'      => (string) $agent['name'],
        'handle'    => (string) $agent['handle'],
        'tone'      => (int) $agent['tone'],
        'assistant' => !empty($agent['assistant']),
        'state'     => (string) $agent['state'],
        'line'      => trim($line),
    );
}

/**
 * The channel's agents for one opened message: the requests it made (by the
 * message's own index), the replies under it and the reactions on it of the
 * agents' applications. Read for this message only; the replies through
 * the channel's index from the message on, ws_messages has none on parent_id.
 *
 * @param array $channel
 * @param array $message
 * @return array seen, waiting, quiet (ws_message_seen_agent_row() rows)
 */
function ws_message_seen_agents($channel, $message)
{
    $empty = array('seen' => array(), 'waiting' => array(), 'quiet' => array());
    $agents = ws_message_seen_agents_of($channel);

    if (empty($agents)) {
        return $empty;
    }

    $message_id = (int) $message['id'];
    $requests = (array) db_items("SELECT agent, status, created_at, claimed_at, answered_at FROM ws_ai_requests
        WHERE message_id = '" . $message_id . "' ORDER BY id");
    $apps = array_values(array_filter(array_map(function ($agent) { return (int) $agent['app_id']; }, $agents)));
    $marks = array();

    if (!empty($apps)) {
        $in = implode(',', $apps);

        foreach ((array) db_items("SELECT sender_id, MIN(created_at) AS at FROM ws_reactions
            WHERE message_id = '" . $message_id . "' AND sender_kind = 'app' AND sender_id IN (" . $in . ") GROUP BY sender_id") as $row) {
            $marks[(int) $row['sender_id']] = array('state' => 'reacted', 'at' => (int) $row['at']);
        }

        foreach ((array) db_items("SELECT sender_id, MIN(created_at) AS at FROM ws_messages
            WHERE channel_id = '" . (int) $message['channel_id'] . "' AND id > '" . $message_id . "'
            AND parent_id = '" . $message_id . "' AND sender_kind = 'app' AND sender_id IN (" . $in . ") AND deleted_at = 0 GROUP BY sender_id") as $row) {
            $marks[(int) $row['sender_id']] = array('state' => 'answered', 'at' => (int) $row['at']);
        }
    }

    $writer = ($message['sender_kind'] === 'app') ? (int) $message['sender_id'] : 0;
    $sorted = ws_message_seen_agents_sort($agents, $requests, $marks, $writer);
    $now = time();

    foreach ($sorted as $group => $rows) {
        $sorted[$group] = array_map(function ($agent) use ($now) { return ws_message_seen_agent_row($agent, $now); }, $rows);
    }

    return $sorted;
}
