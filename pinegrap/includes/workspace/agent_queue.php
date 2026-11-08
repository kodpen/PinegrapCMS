<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents' request queue: a person's message that matches a
 * channel-agent row's wake rule makes one ws_ai_requests row for that agent
 * (agent = 'app:<agent id>' or 'model:<agent id>'), within the depth and
 * hourly brakes. A push door is knocked at once with the ids and the site's
 * address only, never the words: the agent reads the request with its own
 * key. A knock that fails is tried again with a growing wait, and the
 * request stays queued for the agent's next read meanwhile. A model agent's
 * request is answered by the site itself (model_agents.php).
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
 * The value ws_ai_requests.agent holds for a registry agent. Pure.
 *
 * @param array $agent kind, id
 * @return string app:<id> | model:<id> | '' for any other kind
 */
function ws_agent_request_value($agent)
{
    $kind = (string) ($agent['kind'] ?? '');

    return in_array($kind, array('app', 'model'), true) && ((int) ($agent['id'] ?? 0) > 0) ? $kind . ':' . (int) $agent['id'] : '';
}

/**
 * Does a message wake an agent under its channel-agent row? Pure.
 *
 * mention: its @handle as a word, or its application's tag; all: every
 * message; prefix: the message starts with the row's prefix; replies: an
 * answer to one of the agent's own messages. A mention wakes it under every
 * rule.
 *
 * @param array  $row         wake_rule, wake_prefix, handle, app_id
 * @param string $body        the message, tags normalised
 * @param bool   $replies_own the message answers one of the agent's messages
 * @return bool
 */
function ws_agent_wake_matches($row, $body, $replies_own)
{
    $body = (string) $body;
    $handle = (string) ($row['handle'] ?? '');
    $app_id = (int) ($row['app_id'] ?? 0);

    $mentioned = (($handle !== '') && preg_match('/(^|[^\w@.-])@' . preg_quote($handle, '/') . '(?![\w-])/iu', $body))
        || (($app_id > 0) && (strpos($body, '<@app:' . $app_id . '>') !== false));

    if ($mentioned) {
        return true;
    }

    switch ((string) ($row['wake_rule'] ?? 'mention')) {
        case 'all':
            return trim($body) !== '';

        case 'prefix':
            $prefix = (string) ($row['wake_prefix'] ?? '');

            return ($prefix !== '') && (mb_strtolower(mb_substr(ltrim($body), 0, mb_strlen($prefix))) === mb_strtolower($prefix));

        case 'replies':
            return (bool) $replies_own;
    }

    return false;
}

/**
 * What a push door is sent: the event, the site's API address, the agent's
 * handle and the request's id. Never the words: the agent reads them with
 * its own key. Pure.
 *
 * @param string $site       the site's API address
 * @param string $handle
 * @param int    $request_id
 * @return string JSON
 */
function ws_agent_knock_body($site, $handle, $request_id)
{
    return (string) json_encode(array(
        'event'      => 'workspace.agent_request',
        'site'       => (string) $site,
        'agent'      => (string) $handle,
        'request_id' => (int) $request_id,
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * The headers of a knock: JSON, the door's secret as a bearer token (a
 * routine's fire address reads it so) and a signature of the body with the
 * secret, for a receiver that checks it. Pure.
 *
 * @param string $body   ws_agent_knock_body()
 * @param string $secret the door's secret, opened ('' for none)
 * @param int    $time
 * @return string[]
 */
function ws_agent_knock_headers($body, $secret, $time)
{
    $headers = array('Content-Type: application/json', 'Accept: application/json');
    $secret = (string) $secret;

    if ($secret !== '') {
        $headers[] = 'Authorization: Bearer ' . $secret;
        $headers[] = 'X-Pinegrap-Timestamp: ' . (int) $time;
        $headers[] = 'X-Pinegrap-Signature: sha256=' . hash_hmac('sha256', (int) $time . '.' . (string) $body, $secret);
    }

    return $headers;
}

/* ---------------------------------------------------------------------------
   Making the requests
   --------------------------------------------------------------------------- */

/**
 * The agents a channel's messages may wake: allowed rows of active app and
 * model agents, with their wake rule.
 *
 * @param int $channel_id
 * @return array[] ws_agents row + wake_rule, wake_prefix, tier, loop_guard
 */
function ws_agent_channel_rows($channel_id)
{
    if (!ws_agents_ready()) {
        return array();
    }

    return (array) db_items("SELECT a.*, ca.wake_rule, ca.wake_prefix, ca.tier, ca.loop_guard
        FROM ws_channel_agents ca
        INNER JOIN ws_agents a ON a.id = ca.agent_id
        WHERE ca.channel_id = '" . (int) $channel_id . "' AND ca.allowed = 1
            AND a.status = 'active' AND a.kind IN ('app', 'model')
        ORDER BY a.id");
}

/**
 * The reply chain a message stands at the end of, newest first, each marked
 * by_agent when an agent's application wrote it. At most the depth brake's
 * length is read.
 *
 * @param int   $message_id
 * @param int[] $agent_apps the applications agents write through
 * @return array[] id, by_agent, sender_kind, sender_id
 */
function ws_agent_chain($message_id, $agent_apps)
{
    $chain = array();
    $id = (int) $message_id;

    while (($id > 0) && (count($chain) <= WS_AGENT_DEPTH_MAX)) {
        $message = db_item("SELECT id, parent_id, sender_kind, sender_id FROM ws_messages WHERE id = '" . $id . "'");

        if (!$message) {
            break;
        }

        $chain[] = array(
            'id'          => (int) $message['id'],
            'by_agent'    => ((string) $message['sender_kind'] === 'app') && in_array((int) $message['sender_id'], $agent_apps, true),
            'sender_kind' => (string) $message['sender_kind'],
            'sender_id'   => (int) $message['sender_id'],
        );
        $id = (int) $message['parent_id'];
    }

    return $chain;
}

/**
 * After a message was written: one request for every agent of the channel it
 * wakes, within the brakes, and a knock on each push door. The agent's own
 * message never wakes it.
 *
 * @param array  $viewer the writer (a person, or an application's owner)
 * @param array  $channel
 * @param int    $message_id
 * @param string $body tags normalised
 * @return string[] the agent values a request was made for
 */
function ws_agent_after_send($viewer, $channel, $message_id, $body)
{
    if (!ws_agents_ready() || !ws_ai_schema_ready()) {
        return array();
    }

    $rows = ws_agent_channel_rows((int) $channel['id']);

    if (empty($rows)) {
        return array();
    }

    $apps = array_values(array_filter(array_map(function ($row) {
        return (int) $row['app_id'];
    }, $rows)));
    $chain = ws_agent_chain($message_id, $apps);
    $sender = $chain[0] ?? array('sender_kind' => 'user', 'sender_id' => 0);
    $parent = $chain[1] ?? null;
    $made = array();

    foreach ($rows as $row) {
        $value = ws_agent_request_value($row);
        $own_app = (int) $row['app_id'];

        if ($value === '') {
            continue;
        }

        // Its own words never wake it.
        if (($own_app > 0) && ($sender['sender_kind'] === 'app') && ($sender['sender_id'] === $own_app)) {
            continue;
        }

        $replies_own = $parent && ($own_app > 0) && ($parent['sender_kind'] === 'app') && ($parent['sender_id'] === $own_app);

        if (!ws_agent_wake_matches($row, $body, $replies_own)) {
            continue;
        }

        $last_hour = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests WHERE agent = '" . e($value) . "' AND created_at > '" . (time() - 3600) . "'");
        $brake = ws_agent_brake(ws_agent_chain_depth($chain), $last_hour);

        if ($brake !== '') {
            ws_message_system((int) $channel['id'], ($brake === 'depth')
                ? lang(array('string' => '@{var:1} was not woken: agents have answered one another too many times in a row. A person\'s message starts the count again.', 'vars' => (string) $row['handle']))
                : lang(array('string' => '@{var:1} was not woken: it was woken too often in the last hour.', 'vars' => (string) $row['handle'])));
            continue;
        }

        list($pv_column, $pv_value) = pg_ai_prompt_version_insert();

        db("INSERT INTO ws_ai_requests (channel_id, message_id, requested_by, status, agent, created_at" . $pv_column . ")
            VALUES ('" . (int) $channel['id'] . "', '" . (int) $message_id . "', '" . (int) $viewer['id'] . "', 'queued', '" . e($value) . "', '" . time() . "'" . $pv_value . ")");

        $request_id = (int) mysqli_insert_id(db::$con);

        if ($request_id <= 0) {
            continue;
        }

        $made[] = $value;

        if (((string) $row['kind'] === 'app') && ((string) $row['door_kind'] === 'push')) {
            ws_agent_knock($row, $request_id);
        }
    }

    if (!empty($made)) {
        ws_message_touch($message_id);
    }

    return $made;
}

/* ---------------------------------------------------------------------------
   The push door
   --------------------------------------------------------------------------- */

/**
 * Knocks an agent's push door for a request: the ids and the site's address,
 * signed with the door's secret. The outcome goes on the agent's row; a
 * failure is tried again by ws_agent_knocks_retry().
 *
 * @param array $agent the raw ws_agents row
 * @param int   $request_id
 * @return bool the door answered 2xx
 */
function ws_agent_knock($agent, $request_id)
{
    $now = time();
    $body = ws_agent_knock_body(ws_claude_api_base(), (string) $agent['handle'], (int) $request_id);
    $error = '';

    if (ws_agent_door_problem('push', (string) $agent['door_url']) !== '') {
        $error = lang('The push door has no valid https address.');
    } elseif (!function_exists('curl_init')) {
        $error = lang('This server cannot make outgoing HTTPS requests (cURL is missing).');
    } else {
        $answer = pg_curl_run('POST', (string) $agent['door_url'], array(
            'body'            => $body,
            'headers'         => ws_agent_knock_headers($body, ws_agent_unseal($agent['door_secret_sealed'] ?? ''), $now),
            'connect_timeout' => 5,
            'timeout'         => 10,
            'user_agent'      => 'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (workspace-agent)',
        ));

        if ((int) $answer['errno'] !== 0) {
            $error = lang(array('string' => 'The push door could not be reached: {var:1}', 'vars' => mb_substr((string) $answer['error'], 0, 150)));
        } elseif (((int) $answer['status'] < 200) || ((int) $answer['status'] >= 300)) {
            $error = lang(array('string' => 'The push door answered HTTP {var:1}.', 'vars' => (int) $answer['status']));
        }
    }

    if ($error === '') {
        db("UPDATE ws_agents SET door_failures = 0, door_error = '', door_last_knock = '" . $now . "' WHERE id = '" . (int) $agent['id'] . "'");
        return true;
    }

    db("UPDATE ws_agents SET door_failures = door_failures + 1, door_error = '" . e(mb_substr($error, 0, 250)) . "', door_last_knock = '" . $now . "'
        WHERE id = '" . (int) $agent['id'] . "'");

    return false;
}

/**
 * Knocks again on the push doors that failed and still have requests
 * queued, each once its wait (ws_model_hold_seconds()) is over. Called with
 * the queue's other work, by the screens waiting and the hourly job.
 */
function ws_agent_knocks_retry()
{
    static $done = false;

    if ($done || !ws_agents_ready() || !ws_ai_schema_ready()) {
        return;
    }

    $done = true;

    foreach ((array) db_items("SELECT * FROM ws_agents WHERE kind = 'app' AND status = 'active' AND door_kind = 'push' AND door_failures > 0 LIMIT 20") as $agent) {
        if (((int) $agent['door_last_knock'] + ws_model_hold_seconds((int) $agent['door_failures'])) > time()) {
            continue;
        }

        $request_id = (int) db_value("SELECT id FROM ws_ai_requests WHERE agent = '" . e(ws_agent_request_value($agent)) . "' AND status = 'queued' ORDER BY id DESC LIMIT 1");

        if ($request_id > 0) {
            ws_agent_knock($agent, $request_id);
        }
    }
}

/**
 * Marks a message for an agent's application: the eye while it works, the
 * tick when it answered.
 *
 * @param int    $app_id
 * @param int    $message_id
 * @param string $emoji
 * @param bool   $on
 */
function ws_agent_react($app_id, $message_id, $emoji, $on)
{
    if (((int) $message_id <= 0) || ((int) $app_id <= 0) || !ws_interact_ready()) {
        if ((int) $message_id > 0) {
            ws_message_touch($message_id);
        }

        return;
    }

    if ($on) {
        db("INSERT IGNORE INTO ws_reactions (message_id, sender_kind, sender_id, emoji, created_at)
            VALUES ('" . (int) $message_id . "', 'app', '" . (int) $app_id . "', '" . e($emoji) . "', '" . time() . "')");
    } else {
        db("DELETE FROM ws_reactions
            WHERE message_id = '" . (int) $message_id . "' AND sender_kind = 'app' AND sender_id = '" . (int) $app_id . "' AND emoji = '" . e($emoji) . "'");
    }

    ws_message_touch($message_id);
}

/* ---------------------------------------------------------------------------
   The brief
   --------------------------------------------------------------------------- */

/**
 * The loop-guard text every brief carries unless the channel's row switched
 * it off: when not to answer, and to check in on a schedule rather than
 * keep a conversation going. Guidance only: the depth and hourly brakes
 * hold in code either way. Pure.
 *
 * @return string[]
 */
function ws_agent_loop_guard_lines()
{
    return array(
        'Before you answer:',
        '- Answer only when the message needs something from you. An acknowledgement, a thank-you or an answer to an answer needs nothing: close the request with no_answer.',
        '- Do not answer another agent unless it asked you something.',
        '- After a few exchanges with other agents, stop and wait for a person.',
        '- Check in on a schedule (POST /workspace/agent-wake) rather than staying in the conversation.',
    );
}

/**
 * The tools part of a brief: the site's MCP address, the key's scopes and
 * the routes that close the request. Pure.
 *
 * @param string   $mcp        the MCP server's address
 * @param string[] $scopes     the scopes of the agent's key
 * @param int      $request_id
 * @return string[]
 */
function ws_agent_brief_tool_lines($mcp, $scopes, $request_id)
{
    $scopes = array_values(array_filter(array_map('strval', (array) $scopes), 'strlen'));
    sort($scopes);

    return array(
        'Tools: the site\'s MCP server at ' . $mcp . ' (every API route is a tool there) and the same routes over HTTP, both with your own key.',
        'Your key\'s scopes: ' . ($scopes ? implode(', ', $scopes) : 'none') . '.',
        'Close this request with POST /workspace/agent-requests/' . (int) $request_id . '/answer (text, or no_answer true), or give it up with POST /workspace/agent-requests/' . (int) $request_id . '/fail (reason).',
    );
}

/**
 * The place lines of a wake request's brief: the schedule that woke the
 * agent and where it can write. Pure.
 *
 * @param array    $wake     ws_agent_wake_of()
 * @param array    $channels id, name of the channels the agent is allowed in
 * @param int      $channel_id the request's channel, 0 for none
 * @return string[]
 */
function ws_agent_brief_wake_lines($wake, $channels, $channel_id)
{
    $lines = array(($wake['kind'] === 'every')
        ? 'Your schedule woke you (a wake every few minutes); no message asked.'
        : 'Your one-time scheduled wake has come; no message asked.');
    $names = array();

    foreach ((array) $channels as $channel) {
        $names[] = '#' . trim(str_replace(array("\r", "\n"), ' ', (string) $channel['name'])) . ' (id ' . (int) $channel['id'] . ')';
    }

    if ((int) $channel_id > 0) {
        $lines[] = 'Your answer goes into your channel ' . ($names ? $names[0] : '(id ' . (int) $channel_id . ')') . '.';
    } elseif ($names) {
        $lines[] = 'Your channels: ' . implode(', ', $names) . '. Write where you need with workspace.messages.create, then close this request with no_answer.';
    } else {
        $lines[] = 'You are in no channel now: close this request with no_answer.';
    }

    return $lines;
}

/**
 * What an app agent is told before it starts on a request: who it is, now,
 * who asked, where, the request inside a data block (a wake's note), the
 * channel's memory, the loop-guard text unless the channel switched it off,
 * its tools, the language of the answer and the rules.
 *
 * @param array    $agent  the raw ws_agents row
 * @param array    $row    ws_ai_requests
 * @param string[] $scopes the scopes of the agent's key
 * @return string
 */
function ws_agent_brief($agent, $row, $scopes)
{
    $asker = ws_change_viewer_for_id((int) $row['requested_by']);
    $asker = $asker ? $asker : array('id' => (int) $row['requested_by']);
    $channel = ((int) $row['channel_id'] > 0) ? ws_channel($row['channel_id']) : null;
    $wake = ws_agent_wake_of($row);
    $name = trim((string) $agent['name']);
    $name = (($name !== '') ? $name : (string) $agent['handle']) . ' (@' . (string) $agent['handle'] . ')';
    $request = '';
    $lines = ws_ai_briefing_head(array('name' => $wake ? 'your schedule' : ws_person_name($row['requested_by'])), 'agent', $name);
    $lines[] = '';

    if ($wake) {
        $channels = (array) db_items("SELECT c.id, c.name FROM ws_channel_agents ca
            INNER JOIN ws_channels c ON c.id = ca.channel_id
            WHERE ca.agent_id = '" . (int) $agent['id'] . "' AND ca.allowed = 1
            ORDER BY c.name LIMIT 20");
        $lines = array_merge($lines, ws_agent_brief_wake_lines($wake, $channels, (int) $row['channel_id']));
        $request = $wake['note'];
    } else {
        $parent = ($channel && ws_channel_is_thread($channel)) ? ws_thread_parent_channel($channel) : null;
        $lines[] = $parent ? ws_ai_agent_place('thread', (string) $parent['name']) : ws_ai_agent_place('channel', $channel ? (string) $channel['name'] : '');
        $message = ws_message($row['message_id']);

        if ($message && ((int) $message['deleted_at'] === 0)) {
            $request = ws_plain_text($asker, (string) $message['body']);
            $lines[] = 'Read more of it with GET /workspace/channels/' . (int) $row['channel_id'] . '/context.';
        }
    }

    if (trim($request) !== '') {
        $lines[] = '';
        $lines[] = $wake ? 'The note your wake carries:' : 'The request:';
        $lines[] = pg_ai_data_block('request', $request);
    }

    $lines[] = '';

    if ($channel) {
        $lines = array_merge($lines, ws_ai_memory_parts($asker, $channel));
    }

    $own = $channel ? ws_channel_agent_row((int) $channel['id'], (int) $agent['id']) : null;

    if (!$own || ((int) $own['loop_guard'] === 1)) {
        $lines = array_merge($lines, ws_agent_loop_guard_lines(), array(''));
    }

    $mcp = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/mcp.php';
    $lines = array_merge($lines, ws_agent_brief_tool_lines($mcp, $scopes, (int) $row['id']));

    return ws_ai_briefing_text($lines, ws_ai_request_language($request));
}
