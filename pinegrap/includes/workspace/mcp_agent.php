<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an MCP server that is an agent itself.
 *
 * Most servers only offer tools, and Pinegrap AI (mcp_ask.php) decides which
 * of them to call. Some servers are agents of their own - another Pinegrap
 * site, a company's assistant, a service that answers in sentences: they
 * take a message and answer it. For such a server an administrator chooses
 * the tool that takes the messages (ws_mcp_servers.agent_tool). A message
 * written to the alias, or a reply to one of its answers, then goes to that
 * tool as it is, in its one text argument, and what the tool answers is
 * posted under the message. Pinegrap AI is not needed and no model of the
 * site reads anything.
 *
 * The request waits in ws_ai_requests with agent 'mcp' and is carried on by
 * the screens waiting for it (ws_mcp_agent_kick) and by the hourly job, one
 * call per request; the call is kept in ws_mcp_calls with via 'agent'.
 *
 * The rules of the other tools hold: the tool must be turned on as one that
 * reads only (the server says so, or the administrator declares it), it runs
 * with the asker's own account where people sign in to the service and they
 * connected one, else with the site's, and it is asked only in a channel
 * where Pinegrap AI could be asked: what is written in a private channel
 * leaves the site only where its people allowed it. The agent reads the
 * message and, for a reply, the message it answers, nothing else of the
 * channel.
 *
 * What the agent answers is another service's text: it is posted as it is,
 * locked, in the name of the person who asked (as the answer of a tool run
 * with a form is, mcp_form.php), and a tag in it does not mention anybody.
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
 * How long one call to an agent may take, in seconds: from a screen, and on
 * the command line.
 */
define('WS_MCP_AGENT_TIMEOUT', 45);
define('WS_MCP_AGENT_TIMEOUT_JOB', 90);

/** A request still running after this many seconds is given up. */
define('WS_MCP_AGENT_STALE', 300);

/** A request nobody's screen and no job carried on in a day is cancelled. */
define('WS_MCP_AGENT_QUEUE_TTL', 86400);

/** The most requests of one person that may wait for agents. */
define('WS_MCP_AGENT_PER_PERSON', 3);

/** How much of the message, and of the message it answers, is sent. */
define('WS_MCP_AGENT_TEXT_LIMIT', 4000);
define('WS_MCP_AGENT_CONTEXT_LIMIT', 2000);

/** The names an agent's text argument usually has, the likeliest first. */
define('WS_MCP_AGENT_ARGUMENT_NAMES', 'message,prompt,query,question,input,text,request,task,content');

/**
 * Has the schema step that brought agents in (2026.4.10, 10.89) run?
 *
 * @return bool
 */
function ws_mcp_agent_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mcp_ready() && waf_table_has_column('ws_mcp_servers', 'agent_tool');
    }

    return $ready;
}

/**
 * Is the server an agent: did an administrator choose a tool for its
 * messages? Whether that tool can still take them is ws_mcp_agent_tool()'s.
 *
 * @param array $server
 * @return bool
 */
function ws_mcp_agent_mode($server)
{
    return trim((string) ($server['agent_tool'] ?? '')) !== '';
}

/**
 * The argument of a tool a message goes into: its one required text
 * argument, or, when it requires none, the text argument whose name says it
 * takes a message (message, prompt, query, ...), else its first one. '' when
 * the tool cannot take a message alone: it has no text argument, or it
 * requires something else too.
 *
 * @param array $tool as the client keeps it (schema is its inputSchema as JSON)
 * @return string
 */
function ws_mcp_agent_argument($tool)
{
    $schema = json_decode((string) ($tool['schema'] ?? ''), true);
    $properties = (is_array($schema) && isset($schema['properties']) && is_array($schema['properties'])) ? $schema['properties'] : array();
    $required = (is_array($schema) && isset($schema['required']) && is_array($schema['required'])) ? array_values(array_unique(array_map('strval', $schema['required']))) : array();
    $texts = array();

    foreach ($properties as $name => $property) {
        $type = is_array($property) ? ($property['type'] ?? '') : '';

        // A text argument that may also be null still takes a text.
        if (($type === 'string') || (is_array($type) && in_array('string', $type, true) && !array_diff($type, array('string', 'null')))) {
            $texts[] = (string) $name;
        }
    }

    if (count($required) > 1) {
        return '';
    }

    if (count($required) === 1) {
        return in_array($required[0], $texts, true) ? $required[0] : '';
    }

    foreach (explode(',', WS_MCP_AGENT_ARGUMENT_NAMES) as $name) {
        if (in_array($name, $texts, true)) {
            return $name;
        }
    }

    return $texts[0] ?? '';
}

/**
 * The tools of a server that could take its messages: one that can take a
 * message alone and that may be turned on as reading only (the server says
 * it reads only, or says nothing either way). A tool that writes waits for
 * an approval before each call, which a message to an agent does not have
 * yet.
 *
 * @param array $server
 * @return array tool name => the argument the message goes into
 */
function ws_mcp_agent_candidates($server)
{
    $out = array();

    foreach (ws_mcp_tools($server) as $name => $tool) {
        $argument = ws_mcp_agent_argument($tool);

        if (($argument !== '') && (ws_mcp_tool_can_run($tool) !== '')) {
            $out[(string) $name] = $argument;
        }
    }

    ksort($out);

    return $out;
}

/**
 * The tool a server's messages go to, while it can take them: chosen, still
 * listed by the server, turned on as reading only, and taking a message.
 *
 * @param array $server
 * @return array|null name, argument
 */
function ws_mcp_agent_tool($server)
{
    $name = trim((string) ($server['agent_tool'] ?? ''));
    $on = ws_mcp_tools_on($server);

    if (($name === '') || !isset($on[$name]) || !empty($on[$name]['approve'])) {
        return null;
    }

    $argument = ws_mcp_agent_argument($on[$name]);

    return ($argument !== '') ? array('name' => $name, 'argument' => $argument) : null;
}

/**
 * Why an agent cannot be asked now, as the channel is told; '' when it can.
 *
 * @param array      $server
 * @param array|null $asker
 * @param int        $channel_id where the offer to connect an account leads back to
 * @return string
 */
function ws_mcp_agent_problem($server, $asker = null, $channel_id = 0)
{
    $problem = ws_mcp_ask_problem($server, $asker, (int) $channel_id);

    if ($problem !== '') {
        return $problem;
    }

    if (!ws_mcp_agent_tool($server)) {
        return lang(array('string' => 'The tool that takes the messages of @{var:1} ({var:2}) is no longer turned on as one that reads only. An administrator chooses it again in Workspace Settings › MCP servers.', 'vars' => array(
            $server['alias'],
            '`' . str_replace('`', '', (string) $server['agent_tool']) . '`',
        )));
    }

    return '';
}

/**
 * After a message was written: if it is written to an agent's alias, or
 * answers one of its answers, the request is queued for the agent and
 * Pinegrap AI is not asked. The work is started by the screen's next call
 * (ws_mcp_agent_kick), so the message is on the screen at once.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param int    $message_id
 * @param string $body
 * @return bool|null null when the message is no agent's, false when it is
 *                   but could not be queued (the channel was told why)
 */
function ws_mcp_agent_after_send($viewer, $channel, $message_id, $body)
{
    if (!ws_mcp_agent_ready() || !function_exists('ws_mcp_ask_server')) {
        return null;
    }

    $server = ws_mcp_ask_server($body, $message_id);

    if (!$server || !ws_mcp_agent_mode($server)) {
        return null;
    }

    if (!ws_ai_channel_allowed($channel)) {
        ws_message_system($channel['id'], lang(array('string' => '@{var:1} cannot be asked in this channel: what is written here leaves the site only where Pinegrap AI may be asked. Somebody who manages the channel can allow it from the channel menu.', 'vars' => $server['alias'])));
        return false;
    }

    $waiting = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests
        WHERE agent = 'mcp' AND requested_by = '" . (int) $viewer['id'] . "' AND status IN ('queued', 'sent', 'running')");

    if ($waiting >= WS_MCP_AGENT_PER_PERSON) {
        ws_message_system($channel['id'], lang(array('string' => '{var:1} of your requests to MCP agents are still waiting. Please wait for those to be answered.', 'vars' => WS_MCP_AGENT_PER_PERSON)));
        return false;
    }

    $problem = ws_mcp_agent_problem($server, $viewer, (int) $channel['id']);

    if ($problem !== '') {
        ws_message_system($channel['id'], $problem);
        return false;
    }

    db("INSERT INTO ws_ai_requests (channel_id, message_id, requested_by, status, agent, mcp_server_id, created_at)
        VALUES ('" . (int) $channel['id'] . "', '" . (int) $message_id . "', '" . (int) $viewer['id'] . "', 'queued', 'mcp', '" . (int) $server['id'] . "', '" . time() . "')");

    ws_message_touch($message_id);

    return true;
}

/**
 * Where a request to an agent stands, as the line under it says it.
 *
 * @param array $row
 * @return array status, label, icon, session_url, agent
 */
function ws_mcp_agent_request_state($row)
{
    $server = ws_mcp_server((int) ($row['mcp_server_id'] ?? 0));
    $alias = $server ? (string) $server['alias'] : 'mcp';
    $status = (string) $row['status'];

    switch ($status) {
        case 'queued':
            $label = lang(array('string' => 'Waiting for @{var:1}', 'vars' => $alias));
            $icon = 'bi-hourglass-split';
            break;

        case 'sent':
        case 'running':
            $label = lang(array('string' => '@{var:1} is working on it', 'vars' => $alias));
            $icon = 'bi-plug';
            break;

        case 'answered':
            $label = lang(array('string' => '@{var:1} answered', 'vars' => $alias));
            $icon = 'bi-check2-circle';
            break;

        case 'cancelled':
            $label = trim(lang('Cancelled') . ': ' . (string) $row['error'], ': ');
            $icon = 'bi-x-circle';
            break;

        default:
            $label = trim(lang(array('string' => '@{var:1} could not answer', 'vars' => $alias)) . ': ' . (string) $row['error'], ': ');
            $icon = 'bi-exclamation-circle';
            break;
    }

    return array(
        'id'          => (int) $row['id'],
        'status'      => $status,
        'label'       => $label,
        'icon'        => $icon,
        'session_url' => '',
        'agent'       => 'mcp',
    );
}

/**
 * What is sent to the agent: the message, after a quote of the message it
 * answers when it is a reply. Tags are written as the names they stand for.
 *
 * @param string $text          the message, as text
 * @param string $parent_author who wrote the message it answers, '' for none
 * @param string $parent_text   that message, as text
 * @return string
 */
function ws_mcp_agent_compose($text, $parent_author = '', $parent_text = '')
{
    $text = trim((string) $text);
    $parent_text = trim((string) $parent_text);

    if ($parent_text === '') {
        return $text;
    }

    $quote = array();

    foreach (preg_split('/\R/u', $parent_text) as $index => $line) {
        $quote[] = '> ' . ((($index === 0) && ($parent_author !== '')) ? $parent_author . ': ' : '') . $line;
    }

    return implode("\n", $quote) . "\n\n" . $text;
}

/**
 * A message without the alias it starts with: the alias is this site's name
 * for the server, not the agent's, and "@alias question" would reach it as
 * part of the question. A message that is the alias alone is kept.
 *
 * @param string $text
 * @param string $alias
 * @return string
 */
function ws_mcp_agent_unaddressed($text, $alias)
{
    $text = trim((string) $text);
    $rest = trim((string) preg_replace('/^@' . preg_quote((string) $alias, '/') . '(?![\p{L}\p{N}_-])[\s,:;.!-]*/iu', '', $text));

    return ($rest !== '') ? $rest : $text;
}

/**
 * A message as the agent reads it: tags as the names the asker sees, a tag
 * of something they may not see as hidden, cut to a length.
 *
 * @param array  $asker
 * @param string $body
 * @param int    $limit
 * @return string
 */
function ws_mcp_agent_text($asker, $body, $limit)
{
    $body = function_exists('ws_calc_plain') ? ws_calc_plain((string) $body) : (string) $body;
    $refs = ws_refs_resolve($asker, ws_tokens($body));

    $text = preg_replace_callback('/<([@#])(' . ws_token_types_pattern() . '):([0-9]{1,10})>/', function ($match) use ($refs) {
        $ref = $refs[$match[2] . ':' . $match[3]] ?? null;

        return ($ref && !empty($ref['known'])) ? (string) $ref['label'] : '[' . lang('hidden') . ']';
    }, $body);

    $text = trim((string) $text);

    if (mb_strlen($text) > $limit) {
        $text = rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';
    }

    return $text;
}

/**
 * Who wrote a message, by name only: the agent is not told the site's tags.
 *
 * @param array $row ws_messages row
 * @return string
 */
function ws_mcp_agent_author($row)
{
    if ($row['sender_kind'] === 'user') {
        return ws_person_name((int) $row['sender_id']);
    }

    return trim(preg_replace('/<[@#][a-z_]+:[0-9]+>/', '', ws_ai_author($row)));
}

/**
 * The message the agent's answer is posted as: who answered and through
 * which tool, then the answer. The answer is another service's text: a tag
 * in it would mention somebody here, so its angle bracket is changed.
 *
 * @param string $alias
 * @param string $tool
 * @param string $answer
 * @return string
 */
function ws_mcp_agent_answer_body($alias, $tool, $answer)
{
    $head = lang(array('string' => '@{var:1} answered through {var:2}:', 'vars' => array($alias, '`' . str_replace('`', '', (string) $tool) . '`')));
    $answer = trim(preg_replace('/<(?=[@#])/', '‹', (string) $answer));
    $room = max(0, WS_MESSAGE_MAX - mb_strlen($head) - 2);

    if (mb_strlen($answer) > $room) {
        $answer = rtrim(mb_substr($answer, 0, max(0, $room - 1))) . '…';
    }

    return $head . "\n" . $answer;
}

/**
 * Closes a request as not done; the line under the message says why.
 *
 * @param array  $row
 * @param string $reason
 */
function ws_mcp_agent_fail($row, $reason)
{
    db("UPDATE ws_ai_requests SET status = 'failed', error = '" . e(trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $reason), 0, 250))) . "',
            answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    ws_message_touch((int) $row['message_id']);
}

/**
 * Sends one request to its agent and posts the answer.
 *
 * @param array $row     the request, claimed (status running)
 * @param int   $timeout seconds the call may take
 * @return bool answered
 */
function ws_mcp_agent_run($row, $timeout)
{
    $fail = function ($reason) use ($row) {
        ws_mcp_agent_fail($row, $reason);

        return false;
    };

    $server = ws_mcp_server((int) $row['mcp_server_id']);

    if (!$server) {
        return $fail(lang('That MCP server could not be found.'));
    }

    $asker = ws_ai_viewer((int) $row['requested_by']);

    if (!$asker) {
        return $fail(lang('The person who asked is no longer a member of the workspace.'));
    }

    $channel = ws_channel((int) $row['channel_id']);

    if (!$channel || !ws_can_post_channel($asker, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    if (!ws_ai_channel_allowed($channel)) {
        return $fail(lang(array('string' => '@{var:1} cannot be asked in this channel: what is written here leaves the site only where Pinegrap AI may be asked. Somebody who manages the channel can allow it from the channel menu.', 'vars' => $server['alias'])));
    }

    $message = ws_message((int) $row['message_id']);

    if (!$message || ((int) $message['deleted_at'] > 0)) {
        return $fail(lang('The message that asked was deleted.'));
    }

    $problem = ws_mcp_agent_problem($server, $asker, (int) $channel['id']);

    if ($problem !== '') {
        return $fail($problem);
    }

    $tool = ws_mcp_agent_tool($server);

    // A reply: the agent reads the message it answers too. An answer of an
    // agent is quoted under no name and without the line that says who
    // answered: it is posted in the asker's name, but they did not write it.
    $parent = ((int) $message['parent_id'] > 0) ? ws_message((int) $message['parent_id']) : null;
    $parent_author = '';
    $parent_text = '';

    if ($parent && ((int) $parent['deleted_at'] === 0)) {
        $parent_text = ws_mcp_agent_text($asker, (string) $parent['body'], WS_MCP_AGENT_CONTEXT_LIMIT);

        if (db_value("SELECT id FROM ws_ai_requests WHERE reply_message_id = '" . (int) $parent['id'] . "' AND agent = 'mcp' LIMIT 1")) {
            $parent_text = (string) preg_replace('/^[^\n]*\n?/', '', $parent_text, 1);
        } else {
            $parent_author = ws_mcp_agent_author($parent);
        }
    }

    $text = ws_mcp_agent_compose(
        ws_mcp_agent_unaddressed(ws_mcp_agent_text($asker, (string) $message['body'], WS_MCP_AGENT_TEXT_LIMIT), $server['alias']),
        $parent_author,
        $parent_text
    );

    $arguments = (string) json_encode((object) array($tool['argument'] => $text), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout * 2 + 30);
    }

    // With the asker's own account when they connected one (OAuth servers).
    $conn = ws_mcp_connection($server, $timeout, (int) $asker['id']);
    $started = microtime(true);
    $now = time();
    $out = pg_mcp_client_call_tool($conn, $tool['name'], json_decode($arguments));

    // A token the service no longer takes is renewed once (OAuth servers).
    if (!$out['ok'] && ws_mcp_reauthorize($conn, $server, $out['status'] ?? 0)) {
        $out = pg_mcp_client_call_tool($conn, $tool['name'], json_decode($arguments));
    }

    $duration = (int) round((microtime(true) - $started) * 1000);
    $answer = $out['ok'] ? pg_mcp_client_result_text($out['result'], WS_MESSAGE_MAX) : '';

    if (!$out['ok']) {
        $error = lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], ws_mcp_error_text($server, $out['status'] ?? 0, $out['error'], $conn['person']))));
    } elseif ($out['is_error']) {
        $error = lang(array('string' => 'The tool answered with an error: {var:1}', 'vars' => trim(preg_replace('/\s+/u', ' ', mb_substr($answer, 0, 200)))));
    } elseif ($answer === '') {
        $error = lang(array('string' => '@{var:1} sent back an empty answer.', 'vars' => $server['alias']));
    } else {
        $error = '';
    }

    ws_mcp_remember($server, $conn, $out['ok'] ? '' : $error);

    db("INSERT INTO ws_mcp_calls (server_id, tool, arguments, via, requested_by, channel_id, message_id, request_id, status, error, duration_ms, created_at, finished_at)
        VALUES (
            '" . (int) $server['id'] . "',
            '" . e($tool['name']) . "',
            '" . e($arguments) . "',
            'agent',
            '" . (int) $asker['id'] . "',
            '" . (int) $channel['id'] . "',
            '" . (int) $message['id'] . "',
            '" . (int) $row['id'] . "',
            '" . (($error === '') ? 'done' : 'failed') . "',
            '" . e(mb_substr($error, 0, 250)) . "',
            '" . $duration . "',
            '" . $now . "',
            '" . time() . "')");

    $call_id = (int) mysqli_insert_id(db::$con);

    db("UPDATE ws_mcp_servers SET last_used = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");

    if ($error !== '') {
        return $fail($error);
    }

    // The request may have been given up while the agent worked.
    $current = (string) db_value("SELECT status FROM ws_ai_requests WHERE id = '" . (int) $row['id'] . "'");

    if ($current !== 'running') {
        return false;
    }

    $sent = ws_message_send($asker, $channel, ws_mcp_agent_answer_body($server['alias'], $tool['name'], $answer), array('parent_id' => (int) $message['id']));

    if (!$sent['ok']) {
        return $fail($sent['error']);
    }

    $reply_id = (int) $sent['message_id'];

    // The answer is the service's, not the person's words: nobody edits it.
    db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $reply_id . "'");
    db("UPDATE ws_mcp_calls SET message_id = '" . $reply_id . "' WHERE id = '" . $call_id . "'");

    if (function_exists('ws_thread_copy_sync')) {
        ws_thread_copy_sync($reply_id);
    }

    db("UPDATE ws_ai_requests SET status = 'answered', reply_message_id = '" . $reply_id . "', answered_at = '" . time() . "', error = ''
        WHERE id = '" . (int) $row['id'] . "'");

    ws_message_touch((int) $message['id']);

    log_activity(lang(array('string' => 'the MCP agent @{var:1} answered a message in #{var:2}', 'vars' => array($server['alias'], $channel['name']))), (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $asker['id'] . "'"));

    return true;
}

/**
 * Gives up on requests that stopped moving and on the ones nobody carried
 * on in a day.
 */
function ws_mcp_agent_watchdog()
{
    static $done = false;

    if ($done || !ws_mcp_agent_ready()) {
        return;
    }

    $done = true;
    $now = time();

    foreach ((array) db_items("SELECT * FROM ws_ai_requests
        WHERE agent = 'mcp' AND status IN ('sent', 'running') AND claimed_at < '" . ($now - WS_MCP_AGENT_STALE) . "'
        LIMIT 50") as $row) {
        $server = ws_mcp_server((int) $row['mcp_server_id']);
        ws_mcp_agent_fail($row, lang(array('string' => '@{var:1} did not answer in time.', 'vars' => $server ? $server['alias'] : 'mcp')));
    }

    foreach ((array) db_items("SELECT * FROM ws_ai_requests
        WHERE agent = 'mcp' AND status = 'queued' AND created_at < '" . ($now - WS_MCP_AGENT_QUEUE_TTL) . "'
        LIMIT 50") as $row) {
        db("UPDATE ws_ai_requests SET status = 'cancelled', error = '" . e(lang('It could not be sent within a day.')) . "'
            WHERE id = '" . (int) $row['id'] . "' AND status = 'queued'");
        ws_message_touch((int) $row['message_id']);
    }
}

/**
 * Carries the waiting requests on: takes the oldest one, sends it and posts
 * the answer, and takes another only while the budget lasts. Called by
 * every screen waiting for an answer and by the hourly job; requests are
 * taken one by one, so two screens never send the same one.
 *
 * The caller closes the session first: a call that waits for the agent
 * must not hold the person's other screens up.
 *
 * @param int $budget  seconds after which no new request is started
 * @param int $timeout seconds one call may take
 * @return array status (idle | working)
 */
function ws_mcp_agent_kick($budget = 1, $timeout = WS_MCP_AGENT_TIMEOUT)
{
    if (!ws_mcp_agent_ready()) {
        return array('status' => 'idle');
    }

    ws_mcp_agent_watchdog();

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    $started = time();
    $status = 'idle';

    for ($guard = 0; $guard < 20; $guard++) {
        $row = db_item("SELECT * FROM ws_ai_requests WHERE agent = 'mcp' AND status = 'queued' ORDER BY id LIMIT 1");

        if (!is_array($row)) {
            break;
        }

        $now = time();

        db("UPDATE ws_ai_requests SET status = 'running', claimed_at = '" . $now . "', sent_at = '" . $now . "'
            WHERE id = '" . (int) $row['id'] . "' AND status = 'queued'");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $status = 'working';
        ws_message_touch((int) $row['message_id']);
        ws_mcp_agent_run(array_merge($row, array('status' => 'running')), $timeout);

        if ((time() - $started) >= $budget) {
            break;
        }
    }

    return array('status' => $status);
}

/**
 * The hourly job: the watchdog, and the requests nobody's screen carried on.
 */
function ws_mcp_agent_job()
{
    ws_mcp_agent_kick(300, WS_MCP_AGENT_TIMEOUT_JOB);
}

/**
 * The choice of the tool that takes a server's messages, on the settings
 * card: Pinegrap AI with the tools, or one of the tools that could take
 * them.
 *
 * @param array $server
 * @param bool  $admin
 * @return string HTML, '' before the schema step
 */
function ws_mcp_agent_settings_field($server, $admin)
{
    if (!ws_mcp_agent_ready()) {
        return '';
    }

    $field = 'ws_mcp_' . (int) $server['id'] . '_agent';
    $current = trim((string) ($server['agent_tool'] ?? ''));
    $options = '<option value="">' . h(lang('Pinegrap AI, with the tools turned on below')) . '</option>';
    $candidates = ws_mcp_agent_candidates($server);

    foreach ($candidates as $name => $argument) {
        $options .= '<option value="' . h($name) . '"' . (($name === $current) ? ' selected' : '') . '>'
            . h(lang(array('string' => 'The service\'s own agent, through {var:1} (the message goes into {var:2})', 'vars' => array($name, $argument)))) . '</option>';
    }

    // A tool chosen before that the server no longer offers stays visible.
    if (($current !== '') && !isset($candidates[$current])) {
        $options .= '<option value="' . h($current) . '" selected>' . h($current) . '</option>';
    }

    $problem = (($current !== '') && !ws_mcp_agent_tool($server))
        ? '<div class="form-text text-danger">' . h(lang('This tool cannot take the messages now: turn it on below as one that reads only.')) . '</div>'
        : '';

    return '
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="' . $field . '">' . h(lang(array('string' => 'Who answers @{var:1}', 'vars' => $server['alias']))) . '</label>
            <select class="form-select form-select-sm" name="mcp_agent_tool" id="' . $field . '"' . ($admin ? '' : ' disabled') . '>' . $options . '</select>
            <div class="form-text">' . h(lang('A server that is an agent itself - another Pinegrap site, a company\'s own assistant - can answer on its own: a message written to the alias goes to the tool chosen here as it is, with the message it replies to, and the answer comes back under it. Pinegrap AI is not needed. The tool must be turned on below as one that reads only.')) . '</div>
            ' . $problem . '
        </div>';
}

/**
 * Saves the tool that takes a server's messages, with the tools form. It
 * must be turned on, by the rules just saved, as one that reads only.
 *
 * @param array    $server the row before the save
 * @param array    $rules  the rules just saved
 * @param liveform $liveform
 * @return bool saved as asked
 */
function ws_mcp_agent_settings_save($server, $rules, $liveform)
{
    if (!ws_mcp_agent_ready() || !isset($_POST['mcp_agent_tool']) || !is_scalar($_POST['mcp_agent_tool'])) {
        return true;
    }

    $wanted = trim((string) $_POST['mcp_agent_tool']);
    $candidates = ws_mcp_agent_candidates($server);
    $ok = true;

    if ($wanted !== '') {
        $rule = (string) ($rules[$wanted] ?? '');

        if (!isset($candidates[$wanted]) || !in_array($rule, array('auto', 'auto_declared'), true)) {
            $liveform->add_error(lang(array('string' => 'The tool {var:1} cannot take the messages: it must take a text and be turned on as one that reads only. Pinegrap AI answers @{var:2} meanwhile.', 'vars' => array($wanted, $server['alias']))));
            $wanted = '';
            $ok = false;
        }
    }

    if ($wanted !== trim((string) ($server['agent_tool'] ?? ''))) {
        db("UPDATE ws_mcp_servers SET agent_tool = '" . e($wanted) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");
        ws_mcp_servers(true);

        log_activity(lang(array('string' => ($wanted !== '') ? 'the MCP server @{var:1} answers its messages itself' : 'the MCP server @{var:1} is answered by Pinegrap AI again', 'vars' => $server['alias'])), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return $ok;
}
