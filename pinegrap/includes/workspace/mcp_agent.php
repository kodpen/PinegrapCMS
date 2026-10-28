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
 * An agent whose work takes longer than a call may answer with a task (the
 * tasks extension of the current generation of the protocol): a handle the
 * site keeps in ws_ai_requests.mcp_task and asks about (tasks/get) as often
 * as the server asks, from the same screens and the hourly job, until the
 * task ends; its result is posted as an answer given at once would be. The
 * line under the message says the agent is working, in the server's own
 * words when it gives some. The person who asked, or an administrator, can
 * stop a request that waits or runs; a task is then cancelled on the server
 * too (tasks/cancel), and so is one that stops meeting the rules below, asks
 * for more input or runs longer than a day.
 *
 * The rules of the other tools hold: the tool must be turned on, as one that
 * reads only (the server says so, or the administrator declares it) or, on a
 * server people sign in to, as one whose calls wait for approval; it runs
 * with the asker's own account where people sign in to the service and they
 * connected one, else with the site's, and it is asked only in a channel
 * where Pinegrap AI could be asked: what is written in a private channel
 * leaves the site only where its people allowed it. The agent reads the
 * message and, for a reply, the message it answers, nothing else of the
 * channel.
 *
 * A tool whose calls wait for approval (rule 'approve', mcp.php) is not
 * called when a message is written to it. The call is kept as pending and
 * waits as a proposal of record_type 'mcp_call' (mcp_ask.php) under the
 * message that asked, or on the task, while the request waits as 'sent'.
 * Only the person who asked can run it, and only with their own account;
 * it then goes to the agent as any request does (ws_mcp_agent_approved()),
 * and what the agent answers is posted as its answer rather than as a
 * decision. Setting the proposal aside, or stopping the request, ends both.
 *
 * What the agent answers is another service's text: it is posted as it is,
 * locked, in the name of the person who asked (as the answer of a tool run
 * with a form is, mcp_form.php), and a tag in it does not mention anybody.
 * The channel screen draws it under the agent's own name, and the person who
 * asked is told it came: their own message would tell them nothing. Every
 * other place that shows the message - a search, a pin, the API - reads the
 * line it starts with, which says who answered.
 *
 * An agent that keeps a conversation of its own gives its id back in the
 * structured part of its answer, and its tool takes it in an argument named
 * for it (conversation_id, contextId, ...). The id is kept on the request
 * (ws_ai_requests.mcp_conversation) and sent back by the next request that
 * follows the answer - a reply to it, the next run on the same task - which
 * then carries the new message alone: the agent knows what it answered. On a
 * server people sign in to, only the person who started a conversation
 * carries it on; anybody else starts a new one.
 *
 * A task goes to such an agent too (delegation.php): handed to its alias, or
 * asked about in a note on it that writes the alias. The run is a request
 * with task_id and no message; the agent is sent the task's number and
 * title, its description, its own last answer on the task and the
 * instruction - not the task's notes, people or channel - and its answer is
 * kept on the run (ws_task_delegation_answer()) and shown in the task's
 * Assistant part. The task's channel, when it has one, must let Pinegrap AI
 * be asked, as a channel must for a message.
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
 * The names the argument and the structured answer usually give the
 * conversation an agent keeps, the likeliest first.
 */
define('WS_MCP_AGENT_CONVERSATION_NAMES', 'conversation_id,conversationId,context_id,contextId,thread_id,threadId,session_id,sessionId');

/** A task an agent works on in the background is stopped after this many seconds. */
define('WS_MCP_AGENT_TASK_LIMIT', 86400);

/**
 * Seconds between two questions about a task: what the server asks for,
 * within these bounds, and what is waited when it does not say.
 */
define('WS_MCP_AGENT_POLL_MIN', 2);
define('WS_MCP_AGENT_POLL_MAX', 600);
define('WS_MCP_AGENT_POLL_DEFAULT', 5);

/** After a question about a task that did not reach the server, the next one waits this long. */
define('WS_MCP_AGENT_POLL_RETRY', 60);

/** How long one question about a task may take, in seconds. */
define('WS_MCP_AGENT_POLL_TIMEOUT', 20);

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
 * Has the schema step that lets agents work in the background (2026.4.10,
 * 10.110) run?
 *
 * @return bool
 */
function ws_mcp_agent_tasks_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mcp_agent_ready() && waf_table_has_column('ws_ai_requests', 'mcp_task');
    }

    return $ready;
}

/**
 * Has the schema step that keeps an agent's conversation (2026.4.10, 10.111)
 * run?
 *
 * @return bool
 */
function ws_mcp_agent_conversations_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mcp_agent_ready() && waf_table_has_column('ws_ai_requests', 'mcp_conversation');
    }

    return $ready;
}

/**
 * Has the schema step that keeps a server's preamble switch (2026.11.0,
 * 11.0.111) run? Before it no server sends the preamble.
 *
 * @return bool
 */
function ws_mcp_agent_preamble_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mcp_agent_ready() && waf_table_has_column('ws_mcp_servers', 'agent_preamble');
    }

    return $ready;
}

/**
 * Does the server's agent get the site's preamble before each request: an
 * administrator turned it on for the server (off by default).
 *
 * @param array $server
 * @return bool
 */
function ws_mcp_agent_preamble_on($server)
{
    return ws_mcp_agent_preamble_ready() && !empty($server['agent_preamble']);
}

/**
 * Seconds until a task is asked about again, from the interval the server
 * asked for (milliseconds, 0 when it did not say).
 *
 * @param int $poll_ms
 * @return int
 */
function ws_mcp_agent_poll_delay($poll_ms)
{
    $poll_ms = (int) $poll_ms;

    if ($poll_ms <= 0) {
        return WS_MCP_AGENT_POLL_DEFAULT;
    }

    return (int) min(WS_MCP_AGENT_POLL_MAX, max(WS_MCP_AGENT_POLL_MIN, (int) ceil($poll_ms / 1000)));
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
 * Does the server answer on its own on this site: an agent, once the schema
 * step that brought agents in has run.
 *
 * @param array|null $server
 * @return bool
 */
function ws_mcp_agent_on($server)
{
    return is_array($server) && ws_mcp_agent_ready() && ws_mcp_agent_mode($server);
}

/**
 * The argument of a tool a message goes into: its one required text
 * argument, or, when it requires none, the text argument whose name says it
 * takes a message (message, prompt, query, ...). '' when the tool cannot
 * take a message alone: it requires something else too, or it has no
 * argument that takes any text - one with a fixed list of values or a
 * format (a date, an address) does not, nor does a paging cursor or a
 * filter the tool happens to have.
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
        if (ws_mcp_agent_takes_text($property)) {
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

    return '';
}

/**
 * Does an argument of a tool's input schema take any text: a string, or a
 * string that may be null, without a fixed list of values or a format.
 *
 * @param mixed $property
 * @return bool
 */
function ws_mcp_agent_takes_text($property)
{
    $type = is_array($property) ? ($property['type'] ?? '') : '';

    // A text argument that may also be null still takes a text.
    $text = ($type === 'string') || (is_array($type) && in_array('string', $type, true) && !array_diff($type, array('string', 'null')));

    return $text && !isset($property['enum']) && !isset($property['const']) && !isset($property['format']);
}

/**
 * The argument of a tool that carries on a conversation the agent keeps: a
 * text argument it does not require, named for a conversation
 * (conversation_id, contextId, ...), other than the one the message goes
 * into. '' when the tool has none: each message then starts afresh, with
 * the answer it replies to quoted.
 *
 * @param array $tool as the client keeps it (schema is its inputSchema as JSON)
 * @return string
 */
function ws_mcp_agent_conversation_argument($tool)
{
    $schema = json_decode((string) ($tool['schema'] ?? ''), true);
    $properties = (is_array($schema) && isset($schema['properties']) && is_array($schema['properties'])) ? $schema['properties'] : array();
    $required = (is_array($schema) && isset($schema['required']) && is_array($schema['required'])) ? array_map('strval', $schema['required']) : array();
    $message = ws_mcp_agent_argument($tool);

    foreach (explode(',', WS_MCP_AGENT_CONVERSATION_NAMES) as $name) {
        if (($name !== $message) && isset($properties[$name]) && !in_array($name, $required, true) && ws_mcp_agent_takes_text($properties[$name])) {
            return $name;
        }
    }

    return '';
}

/**
 * The conversation an agent's answer says it keeps: the text its structured
 * result gives under the name of the tool's conversation argument, or under
 * another of the names a conversation usually has. '' when it gives none, or
 * one that could not be sent back as it is (too long to keep, or with a
 * control character in it).
 *
 * @param array|null $result   the tool's result (CallToolResult)
 * @param string     $argument the tool's conversation argument
 * @return string
 */
function ws_mcp_agent_conversation_of($result, $argument)
{
    // A tool that takes no conversation cannot be given one back.
    if ((string) $argument === '') {
        return '';
    }

    $structured = (is_array($result) && isset($result['structuredContent']) && is_array($result['structuredContent'])) ? $result['structuredContent'] : array();

    foreach (array_unique(array_merge(array((string) $argument), explode(',', WS_MCP_AGENT_CONVERSATION_NAMES))) as $name) {
        $value = $structured[$name] ?? null;

        // A number is an id too; anything else is not one.
        $value = (is_string($value) || is_int($value)) ? trim((string) $value) : '';

        if (($value !== '') && (strlen($value) <= 255) && preg_match('/^[^\x00-\x1F\x7F]+$/u', $value)) {
            return $value;
        }
    }

    return '';
}

/**
 * The conversation an earlier request left that this one carries on, ''
 * for none: the agent gave one, and the same account talks to it again.
 * Everybody reaches a server without sign-in through the site's credential,
 * so anybody carries its conversations on; on a server people sign in to,
 * a reply of somebody else starts a new one - what the agent read with one
 * person's account is not for another's.
 *
 * @param array      $server
 * @param array|null $earlier  the request whose answer this one follows
 * @param int        $asker_id who asks now
 * @return string
 */
function ws_mcp_agent_conversation_carried($server, $earlier, $asker_id)
{
    $conversation = is_array($earlier) ? trim((string) ($earlier['mcp_conversation'] ?? '')) : '';

    if ($conversation === '') {
        return '';
    }

    if (((string) ($server['auth_kind'] ?? '') === 'oauth') && ((int) $earlier['requested_by'] !== (int) $asker_id)) {
        return '';
    }

    return $conversation;
}

/**
 * The tools of a server that could take its messages: one that can take a
 * message alone and that may be turned on - as reading only (the server says
 * it reads only, or says nothing either way), or, on a server people sign in
 * to, as one whose every message waits for the approval of the person who
 * asked, which is how a tool that writes is turned on there.
 *
 * @param array $server
 * @return array tool name => the argument the message goes into
 */
function ws_mcp_agent_candidates($server)
{
    $out = array();
    $approve = ws_mcp_can_approve($server);

    foreach (ws_mcp_tools($server) as $name => $tool) {
        $argument = ws_mcp_agent_argument($tool);

        if (($argument !== '') && ($approve || (ws_mcp_tool_can_run($tool) !== ''))) {
            $out[(string) $name] = $argument;
        }
    }

    ksort($out);

    return $out;
}

/**
 * The tool a server's messages go to, while it can take them: chosen, still
 * listed by the server, turned on, and taking a message. approve says each
 * message waits for the approval of the person who asked; conversation is
 * the argument that carries on a conversation the agent keeps, '' for none.
 *
 * @param array $server
 * @return array|null name, argument, approve, conversation
 */
function ws_mcp_agent_tool($server)
{
    $name = trim((string) ($server['agent_tool'] ?? ''));
    $on = ws_mcp_tools_on($server);

    if (($name === '') || !isset($on[$name])) {
        return null;
    }

    $argument = ws_mcp_agent_argument($on[$name]);

    return ($argument !== '') ? array(
        'name'         => $name,
        'argument'     => $argument,
        'approve'      => !empty($on[$name]['approve']),
        'conversation' => ws_mcp_agent_conversation_argument($on[$name]),
    ) : null;
}

/**
 * The argument that carries on the conversation of the tool a call went to,
 * '' when that tool is no longer the one that takes the messages, or takes
 * none.
 *
 * @param array  $server
 * @param string $tool
 * @return string
 */
function ws_mcp_agent_conversation_argument_of($server, $tool)
{
    $current = ws_mcp_agent_tool($server);

    return ($current && ($current['name'] === (string) $tool)) ? $current['conversation'] : '';
}

/**
 * Why an agent cannot be asked now, as the channel or the task is told; ''
 * when it can.
 *
 * @param array      $server
 * @param array|null $asker
 * @param int        $channel_id where the offer to connect an account leads back to
 * @param int        $task_id    asked for a task instead
 * @return string
 */
function ws_mcp_agent_problem($server, $asker = null, $channel_id = 0, $task_id = 0)
{
    $problem = ws_mcp_ask_problem($server, $asker, (int) $channel_id, (int) $task_id);

    if ($problem !== '') {
        return $problem;
    }

    if (!ws_mcp_agent_tool($server)) {
        // On a server people sign in to, a tool that writes may take the
        // messages too, each one approved: only being turned off stops it.
        return lang(array('string' => ws_mcp_can_approve($server)
            ? 'The tool that takes the messages of @{var:1} ({var:2}) is no longer turned on. An administrator turns it on again in Workspace Settings › MCP servers.'
            : 'The tool that takes the messages of @{var:1} ({var:2}) is no longer turned on as one that reads only. An administrator chooses it again in Workspace Settings › MCP servers.', 'vars' => array(
            $server['alias'],
            '`' . str_replace('`', '', (string) $server['agent_tool']) . '`',
        )));
    }

    return '';
}

/**
 * Why a person may not ask the agents for more now, '' when they may: a few
 * of their requests may wait at a time, in channels and on tasks together,
 * those that wait for their approval among them.
 *
 * @param int $user_id
 * @return string
 */
function ws_mcp_agent_limit_problem($user_id)
{
    $waiting = db_items("SELECT status FROM ws_ai_requests
        WHERE agent = 'mcp' AND requested_by = '" . (int) $user_id . "' AND status IN ('queued', 'sent', 'running')");
    $waiting = is_array($waiting) ? $waiting : array();

    if (count($waiting) < WS_MCP_AGENT_PER_PERSON) {
        return '';
    }

    // A request that waits as sent waits for its asker to approve it.
    foreach ($waiting as $row) {
        if ((string) $row['status'] === 'sent') {
            return lang(array('string' => '{var:1} of your requests to MCP agents are still waiting, some of them for your approval. Approve those or set them aside first.', 'vars' => WS_MCP_AGENT_PER_PERSON));
        }
    }

    return lang(array('string' => '{var:1} of your requests to MCP agents are still waiting. Please wait for those to be answered.', 'vars' => WS_MCP_AGENT_PER_PERSON));
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

    $limit = ws_mcp_agent_limit_problem((int) $viewer['id']);

    if ($limit !== '') {
        ws_message_system($channel['id'], $limit);
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
 * May a person stop a request to an agent: the one who asked, or an
 * administrator, while it waits or runs.
 *
 * @param array|null $viewer
 * @param array      $row
 * @return bool
 */
function ws_mcp_agent_can_cancel($viewer, $row)
{
    if (!is_array($viewer) || !in_array((string) $row['status'], array('queued', 'sent', 'running'), true)) {
        return false;
    }

    return ((int) $viewer['id'] === (int) $row['requested_by']) || ((int) $viewer['role'] === 0);
}

/**
 * Where a request to an agent stands, as the line under it says it. An
 * agent working in the background says where it is in its own words. A
 * request that waits for its asker's approval (sent) is shown as approval:
 * nothing is to be carried on for it until then, so the screens neither
 * hurry nor send it.
 *
 * @param array      $row
 * @param array|null $viewer who reads it: whether they may stop it
 * @return array status, label, icon, session_url, agent, cancel
 */
function ws_mcp_agent_request_state($row, $viewer = null)
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
            $label = lang(array('string' => 'Goes to @{var:1} once {var:2} approves it', 'vars' => array($alias, ws_person_name((int) $row['requested_by']))));
            $icon = 'bi-shield-check';
            $status = 'approval';
            break;

        case 'running':
            $label = lang(array('string' => '@{var:1} is working on it', 'vars' => $alias));
            $note = trim((string) ($row['mcp_task_note'] ?? ''));

            if ($note !== '') {
                $label .= ': ' . $note;
            }

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
        'cancel'      => ws_mcp_agent_can_cancel($viewer, $row),
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

    return ws_mcp_agent_quote($parent_text, (string) $parent_author) . "\n\n" . $text;
}

/**
 * A text quoted line by line, after the name of who wrote it when known.
 *
 * @param string $text
 * @param string $author
 * @return string
 */
function ws_mcp_agent_quote($text, $author = '')
{
    $quote = array();

    foreach (preg_split('/\R/u', trim((string) $text)) as $index => $line) {
        $quote[] = '> ' . ((($index === 0) && ($author !== '')) ? $author . ': ' : '') . $line;
    }

    return implode("\n", $quote);
}

/**
 * What is sent to the agent for a task: its number and title, its
 * description, the agent's own last answer on it quoted, and the
 * instruction, each under a line that says what it is. An instruction
 * written in a note loses the alias it starts with; a note that writes only
 * the alias gives none, and the task itself is the request.
 *
 * @param string $title       the task's number and title, as one line
 * @param string $description as text, '' for none
 * @param string $earlier     the agent's last answer on the task, '' for none
 * @param string $instruction as text, '' for none
 * @param string $alias
 * @return string
 */
function ws_mcp_agent_task_compose($title, $description, $earlier, $instruction, $alias = '')
{
    $description = trim((string) $description);
    $earlier = trim((string) $earlier);
    $instruction = trim((string) $instruction);
    $parts = array(trim((string) $title));

    if ((string) $alias !== '') {
        $instruction = ws_mcp_agent_unaddressed($instruction, $alias, false);
    }

    if ($description !== '') {
        $parts[] = lang('Description:') . "\n" . $description;
    }

    if ($earlier !== '') {
        $parts[] = lang('Your last answer on this task:') . "\n" . ws_mcp_agent_quote($earlier);
    }

    if ($instruction !== '') {
        $parts[] = lang('Instruction:') . "\n" . $instruction;
    }

    return implode("\n\n", $parts);
}

/**
 * A message without the alias it starts with: the alias is this site's name
 * for the server, not the agent's, and "@alias question" would reach it as
 * part of the question. A message that is the alias alone is kept, unless
 * $keep_alone is false.
 *
 * @param string $text
 * @param string $alias
 * @param bool   $keep_alone
 * @return string
 */
function ws_mcp_agent_unaddressed($text, $alias, $keep_alone = true)
{
    $text = trim((string) $text);
    $rest = trim((string) preg_replace('/^@' . preg_quote((string) $alias, '/') . '(?![\p{L}\p{N}_-])[\s,:;.!-]*/iu', '', $text));

    return (($rest !== '') || !$keep_alone) ? $rest : $text;
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

    return $head . "\n" . ws_mcp_agent_neutral($answer, max(0, WS_MESSAGE_MAX - mb_strlen($head) - 2));
}

/**
 * Another service's text as it is kept here: a tag in it would mention
 * somebody or link a record, so its angle bracket is changed; cut to the
 * room there is.
 *
 * @param string $answer
 * @param int    $room characters
 * @return string
 */
function ws_mcp_agent_neutral($answer, $room)
{
    $answer = trim(preg_replace('/<(?=[@#])/', '‹', (string) $answer));

    if (mb_strlen($answer) > $room) {
        $answer = rtrim(mb_substr($answer, 0, max(0, $room - 1))) . '…';
    }

    return $answer;
}

/**
 * Marks the message a request answers as changed, so the screens draw its
 * line again; a run on a task has no message.
 *
 * @param array $row
 */
function ws_mcp_agent_touch($row)
{
    if ((int) ($row['message_id'] ?? 0) > 0) {
        ws_message_touch((int) $row['message_id']);
    }
}

/**
 * Closes a request as not done - failed, or cancelled - unless it ended
 * already; the line under the message, or on the task's run, says why. The
 * person who asked for a run on a task is told it failed, as for the other
 * assistants.
 *
 * @param array  $row
 * @param string $reason
 * @param string $status failed | cancelled
 * @return bool closed here
 */
function ws_mcp_agent_fail($row, $reason, $status = 'failed')
{
    db("UPDATE ws_ai_requests SET status = '" . (($status === 'cancelled') ? 'cancelled' : 'failed') . "',
            error = '" . e(trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $reason), 0, 250))) . "',
            answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    $closed = (mysqli_affected_rows(db::$con) > 0);

    if ($closed) {
        ws_mcp_agent_drop_pending($row, $reason);
    }

    if ($closed && ($status !== 'cancelled') && ((int) ($row['task_id'] ?? 0) > 0) && function_exists('ws_task_delegation_told')) {
        ws_task_delegation_told($row, 'failed');
    }

    ws_mcp_agent_touch($row);

    return $closed;
}

/**
 * A request that ended leaves no call waiting for approval: the call is kept
 * as dismissed, and its proposal ends - set aside by the person who stopped
 * the request, or failed with the reason the request ended.
 *
 * @param array  $row
 * @param string $reason
 * @param int    $by who set it aside, 0 when nobody did
 */
function ws_mcp_agent_drop_pending($row, $reason, $by = 0)
{
    $calls = (array) db_values("SELECT id FROM ws_mcp_calls WHERE request_id = '" . (int) $row['id'] . "' AND via = 'agent' AND status = 'pending'");

    if (empty($calls)) {
        return;
    }

    $ids = implode(',', array_map('intval', $calls));

    db("UPDATE ws_mcp_calls SET status = 'dismissed', finished_at = '" . time() . "' WHERE id IN (" . $ids . ") AND status = 'pending'");

    if (function_exists('ws_changes_ready') && ws_changes_ready()) {
        db("UPDATE ws_ai_changes SET status = '" . (((int) $by > 0) ? 'dismissed' : 'failed') . "',
                error = '" . (((int) $by > 0) ? '' : e(mb_substr((string) $reason, 0, 250))) . "',
                decided_by = '" . (int) $by . "', decided_at = '" . time() . "'
            WHERE request_id = '" . (int) $row['id'] . "' AND record_type = 'mcp_call' AND record_id IN (" . $ids . ") AND status = 'pending'");
    }

    ws_mcp_agent_touch($row);
}

/**
 * What a request needs to be carried on, checked again at every step: the
 * server, the person who asked and their right to post in the channel, a
 * channel where Pinegrap AI may be asked, the message, and the rules of the
 * agent (ws_mcp_agent_problem()). A run on a task needs the task, which the
 * person who asked must still see, instead of the message, and its channel
 * only when it has one.
 *
 * @param array $row
 * @return array error ('' when it may go on), server, asker, channel, message, task
 */
function ws_mcp_agent_context($row)
{
    $out = array('error' => '', 'server' => null, 'asker' => null, 'channel' => null, 'message' => null, 'task' => null);

    $fail = function ($reason) use (&$out) {
        $out['error'] = $reason;

        return $out;
    };

    $server = ws_mcp_server((int) $row['mcp_server_id']);

    if (!$server) {
        return $fail(lang('That MCP server could not be found.'));
    }

    $asker = ws_ai_viewer((int) $row['requested_by']);

    if (!$asker) {
        return $fail(lang('The person who asked is no longer a member of the workspace.'));
    }

    if ((int) ($row['task_id'] ?? 0) > 0) {
        $task = function_exists('ws_task') ? ws_task((int) $row['task_id']) : null;

        if (!$task || !ws_can_see_task($asker, $task)) {
            return $fail(lang('The task could not be found, or the person who asked may no longer see it.'));
        }

        $channel = ((int) $task['channel_id'] > 0) ? ws_channel((int) $task['channel_id']) : null;

        if ($channel && !ws_ai_channel_allowed($channel)) {
            return $fail(lang(array('string' => '{var:1} may not be asked in the channel of this task. Somebody who manages the channel can allow it from the channel menu.', 'vars' => '@' . $server['alias'])));
        }

        $problem = ws_mcp_agent_problem($server, $asker, 0, (int) $task['id']);

        if ($problem !== '') {
            return $fail($problem);
        }

        return array('error' => '', 'server' => $server, 'asker' => $asker, 'channel' => $channel, 'message' => null, 'task' => $task);
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

    return array('error' => '', 'server' => $server, 'asker' => $asker, 'channel' => $channel, 'message' => $message, 'task' => null);
}

/**
 * The connection a request goes over: with the asker's own account when
 * they connected one (OAuth servers), else the site's - only their own for
 * a call they approved; and, once the schema step that keeps tasks has run,
 * telling the server the site follows tasks.
 *
 * @param array $server
 * @param int   $timeout
 * @param int   $asker_id
 * @param bool  $person_only only the asker's own account will do
 * @return array
 */
function ws_mcp_agent_connection($server, $timeout, $asker_id, $person_only = false)
{
    $conn = ws_mcp_connection($server, $timeout, (int) $asker_id, (bool) $person_only);

    if (ws_mcp_agent_tasks_ready()) {
        $conn['extensions'] = array(PG_MCP_CLIENT_TASKS);
    }

    return $conn;
}

/**
 * What a tool's result says: the answer as text, or why there is none, and
 * the conversation the agent keeps when the tool can be given it back.
 *
 * @param array      $server
 * @param array|null $result       the tool's result (CallToolResult)
 * @param string     $conversation the tool's conversation argument, '' for none
 * @return array answer, error ('' when there is an answer), conversation
 */
function ws_mcp_agent_result($server, $result, $conversation = '')
{
    $result = is_array($result) ? $result : array();
    $answer = pg_mcp_client_result_text($result, WS_MESSAGE_MAX);

    if (!empty($result['isError'])) {
        $error = lang(array('string' => 'The tool answered with an error: {var:1}', 'vars' => trim(preg_replace('/\s+/u', ' ', mb_substr($answer, 0, 200)))));
    } elseif ($answer === '') {
        $error = lang(array('string' => '@{var:1} sent back an empty answer.', 'vars' => $server['alias']));
    } else {
        $error = '';
    }

    return array('answer' => $answer, 'error' => $error, 'conversation' => ($error === '') ? ws_mcp_agent_conversation_of($result, $conversation) : '');
}

/**
 * The record of the call a request made (ws_mcp_calls).
 *
 * @param array $row
 * @return array|null
 */
function ws_mcp_agent_call_of($row)
{
    $call = db_item("SELECT * FROM ws_mcp_calls WHERE request_id = '" . (int) $row['id'] . "' AND via = 'agent' ORDER BY id DESC LIMIT 1");

    return is_array($call) ? $call : null;
}

/**
 * Was a call run on the approval of the person who asked? Then it went with
 * their own account, and so does everything that follows it: the questions
 * about its task, and stopping that task.
 *
 * @param array|null $call
 * @return bool
 */
function ws_mcp_agent_call_approved($call)
{
    return is_array($call) && ((int) ($call['approved_by'] ?? 0) > 0);
}

/**
 * Ends the record of a call whose agent worked on in the background: how it
 * ended, and how long it took from the call.
 *
 * @param array|null $call
 * @param string     $error '' when it was answered
 */
function ws_mcp_agent_call_end($call, $error)
{
    if (!$call || ((string) $call['status'] !== 'running')) {
        return;
    }

    db("UPDATE ws_mcp_calls SET status = '" . (($error === '') ? 'done' : 'failed') . "',
            error = '" . e(mb_substr((string) $error, 0, 250)) . "',
            duration_ms = '" . (int) min(4294967295, max(0, time() - (int) $call['created_at']) * 1000) . "',
            finished_at = '" . time() . "'
        WHERE id = '" . (int) $call['id'] . "' AND status = 'running'");
}

/**
 * Posts an agent's answer under the message that asked, in the asker's
 * name, locked, and closes the request as answered - unless it was given up
 * or stopped while the agent worked; the person who asked is told it came.
 * The answer of a run on a task is kept on the run instead, shown in the
 * task's Assistant part, and the person who asked is told as for the other
 * assistants. The conversation the agent keeps, when it gave one, is kept on
 * the request for the next one to carry on.
 *
 * @param array  $row
 * @param array  $context      as ws_mcp_agent_context() answers it
 * @param string $tool         the tool that answered
 * @param string $answer
 * @param int    $call_id      the record of the call
 * @param string $conversation the agent's conversation, '' for none
 * @return bool answered
 */
function ws_mcp_agent_post($row, $context, $tool, $answer, $call_id, $conversation = '')
{
    $current = (string) db_value("SELECT status FROM ws_ai_requests WHERE id = '" . (int) $row['id'] . "'");

    if ($current !== 'running') {
        return false;
    }

    $server = $context['server'];
    $asker = $context['asker'];

    if (($conversation !== '') && ws_mcp_agent_conversations_ready()) {
        db("UPDATE ws_ai_requests SET mcp_conversation = '" . e($conversation) . "' WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");
    }

    if ($context['task']) {
        if (!ws_task_delegation_answer($row, ws_mcp_agent_neutral($answer, WS_MESSAGE_MAX))) {
            return false;
        }

        log_activity(lang(array('string' => 'the MCP agent @{var:1} answered on task {var:2}', 'vars' => array($server['alias'], ws_task_number($context['task']['id'])))), (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $asker['id'] . "'"));

        return true;
    }

    $channel = $context['channel'];
    $message = $context['message'];

    $sent = ws_message_send($asker, $channel, ws_mcp_agent_answer_body($server['alias'], $tool, $answer), array('parent_id' => (int) $message['id']));

    if (!$sent['ok']) {
        ws_mcp_agent_fail($row, $sent['error']);

        return false;
    }

    $reply_id = (int) $sent['message_id'];

    // The answer is the service's, not the person's words: nobody edits it.
    db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $reply_id . "'");
    db("UPDATE ws_mcp_calls SET message_id = '" . $reply_id . "' WHERE id = '" . (int) $call_id . "'");

    if (function_exists('ws_thread_copy_sync')) {
        ws_thread_copy_sync($reply_id);
    }

    db("UPDATE ws_ai_requests SET status = 'answered', reply_message_id = '" . $reply_id . "', answered_at = '" . time() . "', error = ''
        WHERE id = '" . (int) $row['id'] . "'");

    ws_message_touch((int) $message['id']);

    // The answer is in the asker's own name, which tells them nothing: they
    // are told it came, as somebody who mentioned them would.
    if (function_exists('ws_notify')) {
        ws_notify((int) $asker['id'], 'agent_answer', array('channel_id' => (int) $channel['id'], 'message_id' => $reply_id, 'actor_id' => 0));
    }

    log_activity(lang(array('string' => 'the MCP agent @{var:1} answered a message in #{var:2}', 'vars' => array($server['alias'], $channel['name']))), (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $asker['id'] . "'"));

    return true;
}

/**
 * The request whose answer this one follows, null for none: for a reply,
 * the one the same agent answered the replied message with; for a run on a
 * task, the same agent's last answered run on it.
 *
 * @param array $row
 * @param array $context as ws_mcp_agent_context() answers it
 * @return array|null
 */
function ws_mcp_agent_earlier($row, $context)
{
    if ($context['task']) {
        $where = "task_id = '" . (int) $context['task']['id'] . "' AND id < '" . (int) $row['id'] . "'";
    } elseif ((int) $context['message']['parent_id'] > 0) {
        $where = "reply_message_id = '" . (int) $context['message']['parent_id'] . "'";
    } else {
        return null;
    }

    $earlier = db_item("SELECT * FROM ws_ai_requests
        WHERE " . $where . " AND agent = 'mcp' AND mcp_server_id = '" . (int) $context['server']['id'] . "' AND status = 'answered'
        ORDER BY id DESC LIMIT 1");

    return is_array($earlier) ? $earlier : null;
}

/**
 * What a request sends its agent. For a message: the message without the
 * alias it starts with, after a quote of the message it answers when it is
 * a reply. For a run on a task: the task (ws_mcp_agent_task_compose()), with
 * the agent's last answer on it, as a reply quotes the answer it replies to.
 * A request that carries on the agent's own conversation quotes nothing the
 * agent answered: it knows that already.
 *
 * @param array      $row
 * @param array      $context as ws_mcp_agent_context() answers it
 * @param array|null $earlier the request whose answer this one follows (ws_mcp_agent_earlier())
 * @param bool       $carried the agent's conversation goes with it
 * @return string
 */
function ws_mcp_agent_request_text($row, $context, $earlier = null, $carried = false)
{
    $server = $context['server'];
    $asker = $context['asker'];

    if ($context['task']) {
        $task = $context['task'];
        $answer = ($earlier && !$carried) ? trim((string) $earlier['note_reply']) : '';

        return ws_mcp_agent_task_compose(
            ws_task_number($task['id']) . ': ' . ws_mcp_agent_text($asker, (string) $task['title'], 300),
            ws_mcp_agent_text($asker, (string) $task['description'], WS_MCP_AGENT_TEXT_LIMIT),
            ($answer !== '') ? ws_mcp_agent_text($asker, $answer, WS_MCP_AGENT_CONTEXT_LIMIT) : '',
            ws_mcp_agent_text($asker, (string) $row['note_text'], WS_MCP_AGENT_TEXT_LIMIT),
            (string) $server['alias']
        );
    }

    $message = $context['message'];

    // A reply: the agent reads the message it answers too. An answer of an
    // agent is quoted under no name and without the line that says who
    // answered: it is posted in the asker's name, but they did not write it.
    $parent = (((int) $message['parent_id'] > 0) && !$carried) ? ws_message((int) $message['parent_id']) : null;
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

    return ws_mcp_agent_compose(
        ws_mcp_agent_unaddressed(ws_mcp_agent_text($asker, (string) $message['body'], WS_MCP_AGENT_TEXT_LIMIT), $server['alias']),
        $parent_author,
        $parent_text
    );
}

/**
 * What a request sends its agent when its server sends the preamble: who
 * the agent is here, now, who asked, where (ws_ai_agent_place()), the
 * request inside a data block, the language and the rules. The channel's
 * other messages and the people are not sent.
 *
 * @param array  $context as ws_mcp_agent_context() answers it
 * @param string $text    ws_mcp_agent_request_text()
 * @return string
 */
function ws_mcp_agent_preamble($context, $text)
{
    $channel = $context['channel'];
    $name = $channel ? (string) $channel['name'] : '';
    $kind = $context['task'] ? 'task' : 'channel';

    if ($channel && ws_channel_is_thread($channel)) {
        $parent = ws_thread_parent_channel($channel);
        $name = $parent ? (string) $parent['name'] : '';
        $kind = $context['task'] ? 'task' : 'thread';
    }

    return ws_ai_agent_preamble(
        ws_ai_briefing_head($context['asker'], 'agent', '@' . $context['server']['alias']),
        ws_ai_agent_place($kind, $name, $context['task'] ? ws_task_number($context['task']['id']) : ''),
        $text,
        ws_ai_request_language($text)
    );
}

/**
 * Sends one request to its agent and posts the answer. An agent that works
 * on in the background answers with a task instead: the task is kept and
 * asked about from then on (ws_mcp_agent_poll()).
 *
 * @param array $row     the request, claimed (status running)
 * @param int   $timeout seconds the call may take
 * @return bool answered
 */
function ws_mcp_agent_run($row, $timeout)
{
    $context = ws_mcp_agent_context($row);

    if ($context['error'] !== '') {
        ws_mcp_agent_fail($row, $context['error']);

        return false;
    }

    $server = $context['server'];
    $asker = $context['asker'];
    $message = $context['message'];
    $tool = ws_mcp_agent_tool($server);

    // The conversation of the answer it follows goes back to the agent, in
    // the argument its tool takes it in.
    $earlier = ws_mcp_agent_earlier($row, $context);
    $conversation = ($tool['conversation'] !== '') ? ws_mcp_agent_conversation_carried($server, $earlier, (int) $asker['id']) : '';
    $text = ws_mcp_agent_request_text($row, $context, $earlier, $conversation !== '');

    if (ws_mcp_agent_preamble_on($server)) {
        $text = ws_mcp_agent_preamble($context, $text);
    }

    $arguments = array($tool['argument'] => $text);

    if ($conversation !== '') {
        $arguments[$tool['conversation']] = $conversation;
    }

    $arguments = (string) json_encode((object) $arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // A tool that writes is not called now: the call waits for the approval
    // of the person who asked, and runs with their own account once given.
    if ($tool['approve']) {
        ws_mcp_agent_propose($row, $context, $tool['name'], $arguments);

        return false;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout * 2 + 30);
    }

    $conn = ws_mcp_agent_connection($server, $timeout, (int) $asker['id']);
    $started = microtime(true);
    $now = time();
    $out = pg_mcp_client_call_tool($conn, $tool['name'], json_decode($arguments));

    // A token the service no longer takes is renewed once (OAuth servers).
    if (!$out['ok'] && ws_mcp_reauthorize($conn, $server, $out['status'] ?? 0)) {
        $out = pg_mcp_client_call_tool($conn, $tool['name'], json_decode($arguments));
    }

    $duration = (int) round((microtime(true) - $started) * 1000);
    $task = ($out['ok'] && !empty($out['task'])) ? $out['task'] : null;
    $answer = '';
    $kept = '';

    if (!$out['ok']) {
        $error = lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], ws_mcp_error_text($server, $out['status'] ?? 0, $out['error'], $conn['person']))));
    } elseif ($task) {
        $error = '';
    } else {
        $outcome = ws_mcp_agent_result($server, $out['result'], $tool['conversation']);
        $answer = $outcome['answer'];
        $error = $outcome['error'];
        $kept = $outcome['conversation'];
    }

    ws_mcp_remember($server, $conn, $out['ok'] ? '' : $error);

    // A task's call goes on: it is ended when the task is (ws_mcp_agent_call_end()).
    db("INSERT INTO ws_mcp_calls (server_id, tool, arguments, via, requested_by, channel_id, message_id, request_id, status, error, duration_ms, created_at, finished_at)
        VALUES (
            '" . (int) $server['id'] . "',
            '" . e($tool['name']) . "',
            '" . e($arguments) . "',
            'agent',
            '" . (int) $asker['id'] . "',
            '" . (int) $row['channel_id'] . "',
            '" . ($message ? (int) $message['id'] : 0) . "',
            '" . (int) $row['id'] . "',
            '" . ($task ? 'running' : (($error === '') ? 'done' : 'failed')) . "',
            '" . e(mb_substr($error, 0, 250)) . "',
            '" . $duration . "',
            '" . $now . "',
            '" . ($task ? 0 : time()) . "')");

    $call_id = (int) mysqli_insert_id(db::$con);

    db("UPDATE ws_mcp_servers SET last_used = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");

    if ($error !== '') {
        ws_mcp_agent_fail($row, $error);

        return false;
    }

    if ($task) {
        ws_mcp_agent_task_keep($row, $task, $conn, $call_id);

        return false;
    }

    return ws_mcp_agent_post($row, $context, $tool['name'], $answer, $call_id, $kept);
}

/* ---------------------------------------------------------------------------
   Messages that wait for approval
   --------------------------------------------------------------------------- */

/**
 * Keeps the call a request would make with a tool whose calls wait for
 * approval and puts it before the person who asked: a proposal of kind
 * mcp_call (mcp_ask.php) under the message that asked, or on the task. The
 * request then waits as sent until it is approved (ws_mcp_agent_approved()),
 * set aside, or stopped. Calls nothing.
 *
 * @param array  $row       the request, claimed (status running)
 * @param array  $context   as ws_mcp_agent_context() answers it
 * @param string $tool
 * @param string $arguments as JSON
 * @return bool waiting for approval
 */
function ws_mcp_agent_propose($row, $context, $tool, $arguments)
{
    // Without the card nobody could approve it, and it would wait forever.
    if (!function_exists('ws_mcp_calls_propose') || !function_exists('ws_changes_store')) {
        ws_mcp_agent_fail($row, lang('The call could not be kept.'));

        return false;
    }

    $message = $context['message'];
    $message_id = $message ? (int) $message['id'] : 0;

    db("INSERT INTO ws_mcp_calls (server_id, tool, arguments, via, requested_by, channel_id, message_id, request_id, status, created_at)
        VALUES (
            '" . (int) $context['server']['id'] . "',
            '" . e($tool) . "',
            '" . e($arguments) . "',
            'agent',
            '" . (int) $row['requested_by'] . "',
            '" . (int) $row['channel_id'] . "',
            '" . $message_id . "',
            '" . (int) $row['id'] . "',
            'pending',
            '" . time() . "')");

    $call_id = (int) mysqli_insert_id(db::$con);

    if ($call_id <= 0) {
        ws_mcp_agent_fail($row, lang('The call could not be kept.'));

        return false;
    }

    ws_mcp_calls_propose($row, $message_id, array($call_id));

    db("UPDATE ws_ai_requests SET status = 'sent' WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");

    // Stopped while the call was being kept: nothing waits for it.
    if (mysqli_affected_rows(db::$con) < 1) {
        ws_mcp_agent_drop_pending($row, lang('Cancelled'));

        return false;
    }

    ws_mcp_agent_touch($row);

    return true;
}

/**
 * Sends a message that waited for approval, now that the person who asked
 * applied its proposal (ws_mcp_call_run()): the request goes to the agent
 * with their own account, never the site's, and what the agent answers is
 * posted as its answer - under the message, or in the task's Assistant part
 * - instead of a decision; the proposal then points at that answer. An agent
 * that works on in the background is followed as for any request. A token
 * the service refused gives the proposal back, and the request goes on
 * waiting, to be run once the account is connected again; anything else
 * that stops the call ends the proposal and the request together.
 *
 * @param array      $viewer
 * @param array      $change ws_ai_changes, taken (status applying)
 * @param array      $call   ws_mcp_calls, via agent
 * @param array|null $server
 * @return array ok, error, stale, decision_id
 */
function ws_mcp_agent_approved($viewer, $change, $call, $server)
{
    $end = function ($status, $error = '', $decision_id = 0) use ($change, $viewer) {
        db("UPDATE ws_ai_changes SET status = '" . e($status) . "', error = '" . e(mb_substr((string) $error, 0, 250)) . "',
                decided_by = '" . (int) $viewer['id'] . "', decided_at = '" . time() . "', decision_message_id = '" . (int) $decision_id . "'
            WHERE id = '" . (int) $change['id'] . "'");
        ws_message_touch($change['message_id']);
    };

    $out = function ($ok, $error = '', $decision_id = 0) {
        return array('ok' => $ok, 'error' => $error, 'stale' => false, 'decision_id' => (int) $decision_id);
    };

    // Ends the proposal, the call and the request, for one reason.
    $fail = function ($row, $error) use ($end, $out, $call) {
        $end('failed', $error);

        db("UPDATE ws_mcp_calls SET status = 'failed', approved_by = 0, error = '" . e(mb_substr((string) $error, 0, 250)) . "', finished_at = '" . time() . "'
            WHERE id = '" . (int) $call['id'] . "' AND status = 'pending'");

        if ($row) {
            ws_mcp_agent_fail($row, $error);
        }

        return $out(false, $error);
    };

    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $call['request_id'] . "' AND agent = 'mcp'");
    $row = is_array($row) ? $row : null;

    if (!$row || ((string) $row['status'] !== 'sent')) {
        return $fail(null, lang('The request this call waited for has ended.'));
    }

    $problem = ws_mcp_call_state_problem($call, $server);

    if (($problem === '') && ((string) $call['status'] !== 'pending')) {
        $problem = lang('Somebody has already decided about this proposal.');
    }

    if ($problem !== '') {
        return $fail($row, $problem);
    }

    $now = time();

    // Taken from its wait, so a Stop or a second click finds it running.
    db("UPDATE ws_ai_requests SET status = 'running', claimed_at = '" . $now . "', sent_at = '" . $now . "'
        WHERE id = '" . (int) $row['id'] . "' AND status = 'sent'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return $fail(null, lang('The request this call waited for has ended.'));
    }

    $row = array_merge($row, array('status' => 'running', 'claimed_at' => $now, 'sent_at' => $now));

    $give_back = function () use ($change, $row) {
        db("UPDATE ws_ai_requests SET status = 'sent' WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");
        db("UPDATE ws_ai_changes SET status = 'pending', decided_by = 0, decided_at = 0 WHERE id = '" . (int) $change['id'] . "' AND status = 'applying'");
        ws_message_touch($change['message_id']);
        ws_mcp_agent_touch($row);
    };

    $context = ws_mcp_agent_context($row);

    if ($context['error'] !== '') {
        return $fail($row, $context['error']);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_AGENT_TIMEOUT * 2 + 30);
    }

    $conn = ws_mcp_agent_connection($server, WS_MCP_AGENT_TIMEOUT, (int) $viewer['id'], true);

    if (empty($conn['headers'])) {
        $give_back();

        return $out(false, lang(array('string' => 'Connect your own account to @{var:1} to run it: the call runs with it.', 'vars' => $server['alias'])));
    }

    $tool = (string) $call['tool'];
    $started = microtime(true);
    $out_call = pg_mcp_client_call_tool($conn, $tool, json_decode((string) $call['arguments']));

    if (!$out_call['ok'] && ws_mcp_reauthorize($conn, $server, $out_call['status'] ?? 0)) {
        $out_call = pg_mcp_client_call_tool($conn, $tool, json_decode((string) $call['arguments']));
    }

    // Refused before anything ran: it can be run again once connected again.
    if (!$out_call['ok'] && ((int) ($out_call['status'] ?? 0) === 401)) {
        $give_back();

        return $out(false, ws_mcp_error_text($server, 401, '', true));
    }

    $duration = (int) round((microtime(true) - $started) * 1000);
    $task = ($out_call['ok'] && !empty($out_call['task'])) ? $out_call['task'] : null;
    $answer = '';
    $kept = '';

    if (!$out_call['ok']) {
        $error = lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], ws_mcp_error_text($server, $out_call['status'] ?? 0, $out_call['error'], true))));
    } elseif ($task) {
        $error = '';
    } else {
        $outcome = ws_mcp_agent_result($server, $out_call['result'], ws_mcp_agent_conversation_argument_of($server, $tool));
        $answer = $outcome['answer'];
        $error = $outcome['error'];
        $kept = $outcome['conversation'];
    }

    // The call counts from now; a task's call goes on until the task ends.
    db("UPDATE ws_mcp_calls SET
            status = '" . ($task ? 'running' : (($error === '') ? 'done' : 'failed')) . "',
            approved_by = '" . (int) $viewer['id'] . "',
            error = '" . e(mb_substr($error, 0, 250)) . "',
            duration_ms = '" . $duration . "',
            created_at = '" . $now . "',
            finished_at = '" . ($task ? 0 : time()) . "'
        WHERE id = '" . (int) $call['id'] . "'");

    db("UPDATE ws_mcp_servers SET last_used = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");

    log_activity(lang(array('string' => 'ran the tool {var:1} of the MCP server @{var:2} with their own account', 'vars' => array($tool, $server['alias']))), (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $viewer['id'] . "'"));

    if ($error !== '') {
        $end('failed', $error);
        ws_mcp_agent_fail($row, $error);

        return $out(false, $error);
    }

    if ($task) {
        ws_mcp_agent_task_keep($row, $task, $conn, (int) $call['id']);
        $end('applied');

        return $out(true);
    }

    // The call ran: the proposal is applied even when the answer could not
    // be posted any more (the request was stopped meanwhile); the line under
    // the message says that.
    ws_mcp_agent_post($row, $context, $tool, $answer, (int) $call['id'], $kept);

    $reply_id = (int) db_value("SELECT reply_message_id FROM ws_ai_requests WHERE id = '" . (int) $row['id'] . "' AND status = 'answered'");

    $end('applied', '', $reply_id);

    return $out(true, '', $reply_id);
}

/**
 * A message that waited for approval was set aside on its card: the request
 * ends with it, cancelled, and says by whom.
 *
 * @param array $call ws_mcp_calls, via agent
 * @param int   $by   who set it aside
 */
function ws_mcp_agent_set_aside($call, $by)
{
    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $call['request_id'] . "' AND agent = 'mcp'");

    if (is_array($row)) {
        ws_mcp_agent_fail($row, lang(array('string' => 'Set aside by {var:1}', 'vars' => ws_person_name((int) $by))), 'cancelled');
    }
}

/**
 * Keeps the task an agent answered a call with: the request runs on, and
 * the task is asked about after the interval the server asks for - at once
 * when it says it ended already. A request stopped while the call was on
 * its way gets its task cancelled on the server.
 *
 * @param array $row
 * @param array $task    as pg_mcp_client_task() keeps it
 * @param array $conn    the connection the call went over
 * @param int   $call_id the record of the call
 */
function ws_mcp_agent_task_keep($row, $task, $conn, $call_id)
{
    $ended = in_array($task['status'], array('completed', 'failed', 'cancelled', 'input_required'), true);
    $next = $ended ? time() : time() + ws_mcp_agent_poll_delay($task['poll_ms']);

    db("UPDATE ws_ai_requests SET mcp_task = '" . e($task['id']) . "', mcp_task_poll = '" . $next . "', mcp_task_note = '" . e($task['message']) . "'
        WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");

    if (mysqli_affected_rows(db::$con) < 1) {
        pg_mcp_client_cancel_task($conn, $task['id']);
        ws_mcp_agent_call_end(db_item("SELECT * FROM ws_mcp_calls WHERE id = '" . (int) $call_id . "'"), lang('Cancelled'));

        return;
    }

    ws_mcp_agent_touch($row);
}

/**
 * Asks about the task a request's agent works on and carries the request on
 * by what the server says: the result is posted, a task that failed or was
 * cancelled closes the request, one that asks for input is stopped (input
 * cannot be given from here yet), and one still working is asked about
 * again after the interval the server asks for. Every rule is checked again
 * first: one that no longer holds stops the task.
 *
 * @param array $row     the request, its question claimed
 * @param int   $timeout seconds the question may take at most
 * @return bool answered
 */
function ws_mcp_agent_poll($row, $timeout)
{
    $context = ws_mcp_agent_context($row);

    if ($context['error'] !== '') {
        ws_mcp_agent_task_stop($row, $context['error']);

        return false;
    }

    $server = $context['server'];
    $call = ws_mcp_agent_call_of($row);
    $task_id = (string) $row['mcp_task'];
    $conn = ws_mcp_agent_connection($server, min((int) $timeout, WS_MCP_AGENT_POLL_TIMEOUT), (int) $context['asker']['id'], ws_mcp_agent_call_approved($call));
    $out = pg_mcp_client_get_task($conn, $task_id);

    // A token the service no longer takes is renewed once (OAuth servers).
    if (!$out['ok'] && ws_mcp_reauthorize($conn, $server, $out['status'])) {
        $out = pg_mcp_client_get_task($conn, $task_id);
    }

    if (!$out['ok']) {
        $error = lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], ws_mcp_error_text($server, $out['status'], $out['error'], $conn['person']))));

        ws_mcp_remember($server, $conn, $error);

        // Not reached, or the server could not answer for a moment: asked
        // again a little later, until the task's time is up.
        if (($out['code'] === 0) && (($out['status'] === 0) || ($out['status'] === 429) || ($out['status'] >= 500))) {
            db("UPDATE ws_ai_requests SET mcp_task_poll = '" . (time() + WS_MCP_AGENT_POLL_RETRY) . "'
                WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");

            return false;
        }

        // The server no longer knows the task, or refuses: the work is lost.
        if (ws_mcp_agent_fail($row, $error)) {
            ws_mcp_agent_call_end($call, $error);
        }

        return false;
    }

    ws_mcp_remember($server, $conn, '');

    $task = $out['task'];
    $tool = $call ? (string) $call['tool'] : (string) $server['agent_tool'];

    switch ($task['status']) {
        case 'completed':
            $outcome = ws_mcp_agent_result($server, $task['result'], ws_mcp_agent_conversation_argument_of($server, $tool));

            ws_mcp_agent_call_end($call, $outcome['error']);

            if ($outcome['error'] !== '') {
                ws_mcp_agent_fail($row, $outcome['error']);

                return false;
            }

            return ws_mcp_agent_post($row, $context, $tool, $outcome['answer'], $call ? (int) $call['id'] : 0, $outcome['conversation']);

        case 'failed':
            $error = trim(lang('The work ended with an error') . ': ' . $task['error'], ': ');

            if (ws_mcp_agent_fail($row, $error)) {
                ws_mcp_agent_call_end($call, $error);
            }

            return false;

        case 'cancelled':
            $reason = ($task['message'] !== '') ? $task['message'] : lang(array('string' => '@{var:1} stopped the work.', 'vars' => $server['alias']));

            if (ws_mcp_agent_fail($row, $reason, 'cancelled')) {
                ws_mcp_agent_call_end($call, $reason);
            }

            return false;

        case 'input_required':
            ws_mcp_agent_task_stop($row, lang(array('string' => '@{var:1} asked for more input, which cannot be given from here yet.', 'vars' => $server['alias'])), 'failed', $conn);

            return false;
    }

    // Still working: asked about again after the interval the server asks
    // for. What it says of its progress shows under the message.
    db("UPDATE ws_ai_requests SET mcp_task_poll = '" . (time() + ws_mcp_agent_poll_delay($task['poll_ms'])) . "', mcp_task_note = '" . e($task['message']) . "'
        WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");

    if ($task['message'] !== (string) $row['mcp_task_note']) {
        ws_mcp_agent_touch($row);
    }

    return false;
}

/**
 * Stops a request: closes it, failed or cancelled, and when its agent works
 * on a task, asks the server to stop that too. Stopping is the server's to
 * do; the request is closed whatever it answers, and a server that is
 * switched off is not called, not even to stop.
 *
 * @param array      $row
 * @param string     $reason
 * @param string     $status failed | cancelled
 * @param array|null $conn   a connection already open to the server
 * @return bool stopped here (false when it had ended already)
 */
function ws_mcp_agent_task_stop($row, $reason, $status = 'failed', $conn = null)
{
    if (!ws_mcp_agent_fail($row, $reason, $status)) {
        return false;
    }

    $task_id = (string) ($row['mcp_task'] ?? '');

    if ($task_id === '') {
        return true;
    }

    $call = ws_mcp_agent_call_of($row);

    ws_mcp_agent_call_end($call, $reason);

    $server = ws_mcp_server((int) $row['mcp_server_id']);

    if ($server && ((string) $server['status'] === 'active')) {
        if (!$conn) {
            $conn = ws_mcp_agent_connection($server, WS_MCP_AGENT_POLL_TIMEOUT, (int) $row['requested_by'], ws_mcp_agent_call_approved($call));
        }

        pg_mcp_client_cancel_task($conn, $task_id);
    }

    return true;
}

/**
 * A person stops a request to an agent while it waits or runs: the one who
 * asked, or an administrator. A task the agent works on is cancelled on the
 * server too; an answer still on its way is not posted.
 *
 * @param array $viewer
 * @param int   $request_id
 * @return string '' when it is stopped (or had ended already), else why not
 */
function ws_mcp_agent_cancel($viewer, $request_id)
{
    $row = ws_mcp_agent_ready() ? db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $request_id . "' AND agent = 'mcp'") : null;

    if (!is_array($row)) {
        return lang('That request could not be found.');
    }

    if (!in_array((string) $row['status'], array('queued', 'sent', 'running'), true)) {
        return '';
    }

    if (!ws_mcp_agent_can_cancel($viewer, $row)) {
        return lang('Only the person who asked, or an administrator, can stop it.');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_AGENT_POLL_TIMEOUT * 2 + 30);
    }

    $reason = lang(array('string' => 'Stopped by {var:1}.', 'vars' => ws_person_name((int) $viewer['id'])));

    // A call that waited for approval is set aside by whoever stopped it.
    ws_mcp_agent_drop_pending($row, $reason, (int) $viewer['id']);
    ws_mcp_agent_task_stop($row, $reason, 'cancelled');

    return '';
}

/**
 * Gives up on requests that stopped moving and on the ones nobody carried
 * on in a day, and stops the tasks agents worked on for longer than a day.
 */
function ws_mcp_agent_watchdog()
{
    static $done = false;

    if ($done || !ws_mcp_agent_ready()) {
        return;
    }

    $done = true;
    $now = time();
    $tasks = ws_mcp_agent_tasks_ready();

    // A task's request runs for as long as the task: its own limit holds.
    // One that waits for its asker's approval (sent) is not running at all.
    foreach ((array) db_items("SELECT * FROM ws_ai_requests
        WHERE agent = 'mcp' AND status = 'running' AND claimed_at < '" . ($now - WS_MCP_AGENT_STALE) . "'" . ($tasks ? " AND mcp_task = ''" : '') . "
        LIMIT 50") as $row) {
        $server = ws_mcp_server((int) $row['mcp_server_id']);
        ws_mcp_agent_fail($row, lang(array('string' => '@{var:1} did not answer in time.', 'vars' => $server ? $server['alias'] : 'mcp')));
    }

    foreach ((array) db_items("SELECT * FROM ws_ai_requests
        WHERE agent = 'mcp' AND status = 'queued' AND created_at < '" . ($now - WS_MCP_AGENT_QUEUE_TTL) . "'
        LIMIT 50") as $row) {
        db("UPDATE ws_ai_requests SET status = 'cancelled', error = '" . e(lang('It could not be sent within a day.')) . "'
            WHERE id = '" . (int) $row['id'] . "' AND status = 'queued'");
        ws_mcp_agent_touch($row);
    }

    // Stopping a task is a call to its server: a few at a time.
    if ($tasks) {
        foreach ((array) db_items("SELECT * FROM ws_ai_requests
            WHERE agent = 'mcp' AND status = 'running' AND mcp_task <> '' AND sent_at < '" . ($now - WS_MCP_AGENT_TASK_LIMIT) . "'
            ORDER BY id LIMIT 5") as $row) {
            $server = ws_mcp_server((int) $row['mcp_server_id']);
            ws_mcp_agent_task_stop($row, lang(array('string' => '@{var:1} did not finish the work within a day.', 'vars' => $server ? $server['alias'] : 'mcp')));
        }
    }
}

/**
 * Carries the waiting requests on: first asks about the tasks agents work
 * on whose next question is due, then takes the oldest request waiting,
 * sends it and posts the answer, and takes another only while the budget
 * lasts. Called by every screen waiting for an answer and by the hourly
 * job; requests and questions are claimed one by one, so two screens never
 * send the same request or ask the same question.
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

    // A question is claimed by moving its time on: a screen that dies while
    // it waits for the answer leaves it to be asked again a little later.
    if (ws_mcp_agent_tasks_ready()) {
        foreach ((array) db_items("SELECT * FROM ws_ai_requests
            WHERE agent = 'mcp' AND status = 'running' AND mcp_task <> '' AND mcp_task_poll <= '" . $started . "'
            ORDER BY mcp_task_poll, id LIMIT 5") as $row) {
            db("UPDATE ws_ai_requests SET mcp_task_poll = '" . (time() + WS_MCP_AGENT_POLL_TIMEOUT + 30) . "'
                WHERE id = '" . (int) $row['id'] . "' AND status = 'running' AND mcp_task_poll = '" . (int) $row['mcp_task_poll'] . "'");

            if (mysqli_affected_rows(db::$con) < 1) {
                continue;
            }

            $status = 'working';
            ws_mcp_agent_poll($row, $timeout);

            if ((time() - $started) >= $budget) {
                return array('status' => $status);
            }
        }
    }

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
        ws_mcp_agent_touch($row);
        ws_mcp_agent_run(array_merge($row, array('status' => 'running')), $timeout);

        if ((time() - $started) >= $budget) {
            break;
        }
    }

    return array('status' => $status);
}

/**
 * Why a request that waits for its asker's approval can never be sent, ''
 * while it still can: what it was about is gone - the server, the person
 * who asked, the message (deleted), or the task (or the asker's sight of
 * it). Rules that may hold again (a server turned off for a while, an
 * account to connect) are the card's to show and are checked when it is
 * approved.
 *
 * @param array $row
 * @return string
 */
function ws_mcp_agent_approval_gone($row)
{
    if (!ws_mcp_server((int) $row['mcp_server_id'])) {
        return lang('That MCP server could not be found.');
    }

    $asker = ws_ai_viewer((int) $row['requested_by']);

    if (!$asker) {
        return lang('The person who asked is no longer a member of the workspace.');
    }

    if ((int) ($row['task_id'] ?? 0) > 0) {
        $task = function_exists('ws_task') ? ws_task((int) $row['task_id']) : null;

        return (!$task || !ws_can_see_task($asker, $task)) ? lang('The task could not be found, or the person who asked may no longer see it.') : '';
    }

    $message = ws_message((int) $row['message_id']);

    return (!$message || ((int) $message['deleted_at'] > 0)) ? lang('The message that asked was deleted.') : '';
}

/**
 * The hourly job: the requests that wait for an approval they can no longer
 * use are closed, so they do not hold a place among their asker's; then the
 * watchdog, the questions about tasks that are due, and the requests
 * nobody's screen carried on.
 */
function ws_mcp_agent_job()
{
    if (ws_mcp_agent_ready()) {
        foreach ((array) db_items("SELECT * FROM ws_ai_requests WHERE agent = 'mcp' AND status = 'sent' ORDER BY id LIMIT 100") as $row) {
            $gone = ws_mcp_agent_approval_gone($row);

            if ($gone !== '') {
                ws_mcp_agent_fail($row, $gone);
            }
        }
    }

    ws_mcp_agent_kick(300, WS_MCP_AGENT_TIMEOUT_JOB);
}

/* ---------------------------------------------------------------------------
   The agent's answers under its own name
   --------------------------------------------------------------------------- */

/**
 * The requests agents answered with these messages: message id => request
 * (mcp_server_id, requested_by). One query for a page of messages.
 *
 * @param int[] $message_ids
 * @return array
 */
function ws_mcp_agent_answers_map($message_ids)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_mcp_agent_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT id, reply_message_id, mcp_server_id, requested_by FROM ws_ai_requests
        WHERE reply_message_id IN (" . implode(',', $message_ids) . ") AND agent = 'mcp' AND status = 'answered'") as $row) {
        $out[(int) $row['reply_message_id']] = $row;
    }

    return $out;
}

/**
 * An agent's answer as it was posted (ws_mcp_agent_answer_body()): the
 * tool named in the line it starts with - in its code span, the same in
 * every language - and the answer after that line.
 *
 * @param string $body
 * @return array tool ('' when the line names none), text
 */
function ws_mcp_agent_answer_split($body)
{
    $body = (string) $body;
    $cut = strpos($body, "\n");

    if ($cut === false) {
        return array('tool' => '', 'text' => $body);
    }

    return array(
        'tool' => preg_match('/`([^`]+)`/', substr($body, 0, $cut), $match) ? $match[1] : '',
        'text' => substr($body, $cut + 1),
    );
}

/**
 * How the channel screen draws an agent's answer: under the agent's own
 * name, as one of the workspace's applications, without the line that says
 * who answered; who asked, and through which tool, are told on its mark.
 *
 * @param array  $request as ws_mcp_agent_answers_map() gives it
 * @param string $body    the message the answer was posted as
 * @return array sender, text
 */
function ws_mcp_agent_answer_view($request, $body)
{
    $server = ws_mcp_server((int) $request['mcp_server_id']);
    $alias = $server ? (string) $server['alias'] : 'mcp';
    $split = ws_mcp_agent_answer_split($body);
    $asker = ws_person_name((int) $request['requested_by']);

    return array(
        'sender' => array(
            'id'       => 0,
            'app_id'   => 0,
            'name'     => '@' . $alias,
            'username' => '',
            'role'     => 3,
            'title'    => lang('MCP agent'),
            'about'    => ($split['tool'] !== '')
                ? lang(array('string' => 'Answered through {var:1}, for {var:2}', 'vars' => array($split['tool'], $asker)))
                : lang(array('string' => 'Answered for {var:1}', 'vars' => $asker)),
            'presence' => 'offline',
            'app'      => true,
            'mcp'      => $alias,
        ) + ws_mcp_agent_face(ws_mcp_agent_identity($server, (int) $request['mcp_server_id'])),
        'text'   => $split['text'],
    );
}

/**
 * The inbox line that tells the person who asked an agent that it answered
 * (ws_notify() kind agent_answer).
 *
 * @param array  $viewer
 * @param array  $row          ws_inbox
 * @param string $channel_name #name, '' when the channel is gone
 * @param string $base         the panel's address
 * @return array title, body, url, icon
 */
function ws_mcp_agent_inbox_describe($viewer, $row, $channel_name, $base)
{
    $message = ((int) $row['message_id'] > 0) ? ws_message((int) $row['message_id']) : null;
    $request = $message ? (ws_mcp_agent_answers_map(array((int) $message['id']))[(int) $message['id']] ?? null) : null;
    $server = $request ? ws_mcp_server((int) $request['mcp_server_id']) : null;

    return array(
        'title' => lang(array('string' => '@{var:1} answered you in {var:2}', 'vars' => array($server ? $server['alias'] : 'mcp', $channel_name))),
        'body'  => $message ? ws_plain_excerpt($viewer, ws_mcp_agent_answer_split((string) $message['body'])['text'], 140) : '',
        'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
        'icon'  => $request ? ws_mcp_agent_identity($server, (int) $request['mcp_server_id'])['icon'] : 'bi-plug',
    );
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
    $approve = ws_mcp_can_approve($server);
    $tools = ws_mcp_tools($server);
    $rules = ws_mcp_tool_rules($server);

    foreach ($candidates as $name => $argument) {
        // A tool that writes, or one turned on to ask first, waits for an
        // approval before each message.
        $asks = $approve && ((($rules[$name] ?? '') === 'approve') || (ws_mcp_tool_can_run($tools[$name]) === ''));

        $options .= '<option value="' . h($name) . '"' . (($name === $current) ? ' selected' : '') . '>'
            . h(lang(array('string' => $asks
                ? 'The service\'s own agent, through {var:1} (the message goes into {var:2}; each message waits for approval)'
                : 'The service\'s own agent, through {var:1} (the message goes into {var:2})', 'vars' => array($name, $argument)))) . '</option>';
    }

    // A tool chosen before that the server no longer offers stays visible.
    if (($current !== '') && !isset($candidates[$current])) {
        $options .= '<option value="' . h($current) . '" selected>' . h($current) . '</option>';
    }

    $problem = (($current !== '') && !ws_mcp_agent_tool($server))
        ? '<div class="form-text fg-danger">' . h($approve ? lang('This tool cannot take the messages now: turn it on below.') : lang('This tool cannot take the messages now: turn it on below as one that reads only.')) . '</div>'
        : '';

    // On a server people sign in to, a tool that writes can take the
    // messages too: each one waits for its asker's approval.
    $rule = $approve
        ? lang('The tool must be turned on below. One that reads only answers at once; one that changes things on the service waits, before each message, for the person who asked to approve it, and then runs with their own account.')
        : lang('The tool must be turned on below as one that reads only.');

    return '
        <div class="mb-5">
            <label class="form-label small fw-semibold" for="' . $field . '">' . h(lang(array('string' => 'Who answers @{var:1}', 'vars' => $server['alias']))) . '</label>
            <select class="form-control form-control-sm" name="mcp_agent_tool" id="' . $field . '"' . ($admin ? '' : ' disabled') . '>' . $options . '</select>
            <div class="form-text">' . h(lang('A server that is an agent itself - another Pinegrap site, a company\'s own assistant - can answer on its own: a message written to the alias goes to the tool chosen here as it is, with the message it replies to, and the answer comes back under it. A task handed to the alias, or a note on a task that writes it, goes to it with the task\'s title and description, and the answer comes back in the task\'s Assistant part. Pinegrap AI is not needed.')) . ' ' . h($rule) . '</div>
            ' . $problem . '
        </div>' . ws_mcp_agent_preamble_field($server, $admin);
}

/**
 * The switch that sends the site's preamble to a server's agent, on the
 * settings card: off by default.
 *
 * @param array $server
 * @param bool  $admin
 * @return string HTML, '' before the schema step
 */
function ws_mcp_agent_preamble_field($server, $admin)
{
    if (!ws_mcp_agent_preamble_ready()) {
        return '';
    }

    $field = 'ws_mcp_' . (int) $server['id'] . '_preamble';

    return '
        <div class="form-field mb-5">
            <input type="hidden" name="mcp_agent_preamble" value="0">
            <div class="switch"><input class="" type="checkbox" name="mcp_agent_preamble" value="1" id="' . $field . '"' . (!empty($server['agent_preamble']) ? ' checked' : '') . ($admin ? '' : ' disabled') . '></div>
            <label class="" for="' . $field . '">' . h(lang('Tell the agent about this site before each message')) . '</label>
            <div class="form-text">' . h(lang('A short preamble goes before the message: this site\'s address, the time, who asked, the channel or task by name, the language to answer in and the rules Pinegrap AI follows. Nothing else written in the channel is sent.')) . '</div>
        </div>';
}

/**
 * Saves the tool that takes a server's messages, with the tools form. It
 * must be turned on, by the rules just saved, as one that reads only - or,
 * on a server people sign in to, as one whose calls wait for approval.
 *
 * @param array    $server the row before the save
 * @param array    $rules  the rules just saved
 * @param liveform $liveform
 * @return bool saved as asked
 */
function ws_mcp_agent_settings_save($server, $rules, $liveform)
{
    ws_mcp_agent_preamble_save($server);

    if (!ws_mcp_agent_ready() || !isset($_POST['mcp_agent_tool']) || !is_scalar($_POST['mcp_agent_tool'])) {
        return true;
    }

    $wanted = trim((string) $_POST['mcp_agent_tool']);
    $candidates = ws_mcp_agent_candidates($server);
    $ok = true;

    if ($wanted !== '') {
        $rule = (string) ($rules[$wanted] ?? '');
        $approve = ws_mcp_can_approve($server);
        $allowed = $approve ? array('auto', 'auto_declared', 'approve') : array('auto', 'auto_declared');

        if (!isset($candidates[$wanted]) || !in_array($rule, $allowed, true)) {
            $liveform->add_error(lang(array('string' => $approve
                ? 'The tool {var:1} cannot take the messages: it must take a text and be turned on. Pinegrap AI answers @{var:2} meanwhile.'
                : 'The tool {var:1} cannot take the messages: it must take a text and be turned on as one that reads only. Pinegrap AI answers @{var:2} meanwhile.', 'vars' => array(h($wanted), $server['alias']))));
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

/**
 * Saves the preamble switch of a server, with the tools form.
 *
 * @param array $server the row before the save
 */
function ws_mcp_agent_preamble_save($server)
{
    if (!ws_mcp_agent_preamble_ready() || !isset($_POST['mcp_agent_preamble']) || !is_scalar($_POST['mcp_agent_preamble'])) {
        return;
    }

    $wanted = ((string) $_POST['mcp_agent_preamble'] === '1') ? 1 : 0;

    if ($wanted !== (int) ($server['agent_preamble'] ?? 0)) {
        db("UPDATE ws_mcp_servers SET agent_preamble = '" . $wanted . "', updated_at = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");
        ws_mcp_servers(true);

        log_activity(lang(array('string' => $wanted ? 'the MCP server @{var:1} is told about the site before each message' : 'the MCP server @{var:1} is no longer told about the site before each message', 'vars' => $server['alias'])), (string) ($_SESSION['sessionusername'] ?? ''));
    }
}
