<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a task handed to an assistant.
 *
 * The people on a task stay the ones who answer for it. An assistant - Pinegrap
 * AI (ai.php) or Claude (claude.php) - becomes the task's delegate and does the
 * work it can: it reads the task, its notes, its channel and the records it is
 * about, and reports what it did, what it found, what it proposes and what it
 * needs from the person to go on. It writes nothing itself; whatever it
 * proposes - a new state or date for the task, a note on it, a note of the
 * workspace, a change to a record - waits on the task (ws_ai_changes, by
 * task_id) for the person who asked to apply it with one click or set it
 * aside (changes.php).
 *
 * Every run of the assistant on a task is a request in the queue both
 * assistants already work from (ws_ai_requests), tied to the task by task_id
 * and asked by one person, the way a request written in a note is: the
 * instruction is kept in note_text, the answer in note_reply, and the request
 * has no message. So the limits, the watchdog, the daily allowance and the
 * payload-free fire of the routine are the ones a channel request has. A run
 * starts when the task is handed over, when the person who handed it writes
 * to the assistant again, and when a note on the task calls an assistant by
 * name (@ai, @Claude, an MCP server's alias) - a note asks without handing
 * the task over.
 *
 * ws_assistants() is the one list of assistants a task can go to. The task
 * drawer, the task lists and the API read it. An MCP server's alias
 * (mcp_ask.php) is one of them under the key mcp:<server id>: its runs are
 * Pinegrap AI's requests with the server set (agent ai, mcp_server_id), so
 * Pinegrap AI works the task with that server's tools, and a tool that
 * writes waits on the task as a call to approve, run only by the person who
 * asked, with their own account. A server that is an agent itself answers
 * on its own instead (mcp_agent.php): its runs are requests of agent mcp,
 * sent to the tool that takes its messages, and need no Pinegrap AI.
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
 * The longest instruction a run takes, in characters.
 */
define('WS_DELEGATION_INSTRUCTION_MAX', 2000);

/**
 * How many of a task's runs the drawer shows, newest first.
 */
define('WS_DELEGATION_RUNS_SHOWN', 5);

/**
 * How many earlier runs of a task a new run is told about.
 */
define('WS_DELEGATION_EARLIER', 3);

/**
 * Are the columns there (2026.4.10, 10.80), and the queue the runs wait in?
 *
 * @return bool
 */
function ws_delegation_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_tasks', 'delegate')
            && waf_table_has_column('ws_ai_requests', 'task_id')
            && waf_table_has_column('ws_ai_changes', 'task_id')
            && function_exists('ws_ai_schema_ready') && ws_ai_schema_ready()
            && function_exists('ws_claude_schema_ready') && ws_claude_schema_ready();
    }

    return $ready;
}

/**
 * The MCP server an assistant's key stands for (mcp:<server id>).
 *
 * @param string $key
 * @return int the server's id, 0 for Pinegrap AI or Claude
 */
function ws_assistant_mcp_id($key)
{
    return preg_match('/^mcp:([1-9][0-9]{0,9})$/', (string) $key, $match) ? (int) $match[1] : 0;
}

/**
 * The assistant a run was for, by key: a request to Pinegrap AI with an MCP
 * server set, or to the server's own agent, is that server's alias.
 *
 * @param array $row ws_ai_requests
 * @return string
 */
function ws_task_run_key($row)
{
    $agent = (string) ($row['agent'] ?? 'claude');

    if (in_array($agent, array('ai', 'mcp'), true) && ((int) ($row['mcp_server_id'] ?? 0) > 0)) {
        return 'mcp:' . (int) $row['mcp_server_id'];
    }

    return $agent;
}

/**
 * Is this row on a task an application's proposal (proposals.php) rather
 * than a run an assistant was asked for? Nobody asked for it, so the drawer
 * draws it with the person it waits for and no instruction.
 *
 * @param array $row ws_ai_requests
 * @return bool
 */
function ws_task_run_is_proposal($row)
{
    return function_exists('ws_proposal_agent_parse') && (ws_proposal_agent_parse((string) ($row['agent'] ?? '')) !== null);
}

/**
 * The assistant whose queue, limits and channel switch a key's runs go by:
 * an MCP server's alias is answered by Pinegrap AI, or by the server's own
 * agent when it is one (mcp_agent.php).
 *
 * @param string $key
 * @return string ai | claude | mcp
 */
function ws_assistant_agent($key)
{
    $server_id = ws_assistant_mcp_id($key);

    return ws_assistant_agent_of($key, (($server_id > 0) && function_exists('ws_mcp_server')) ? ws_mcp_server($server_id) : null);
}

/**
 * ws_assistant_agent() given the server a key names: null for none, or for
 * one that is gone, whose runs stay Pinegrap AI's.
 *
 * @param string     $key
 * @param array|null $server
 * @return string ai | claude | mcp
 */
function ws_assistant_agent_of($key, $server)
{
    if (ws_assistant_mcp_id($key) <= 0) {
        return (string) $key;
    }

    return (is_array($server) && function_exists('ws_mcp_agent_mode') && ws_mcp_agent_mode($server)) ? 'mcp' : 'ai';
}

/**
 * The assistants a task can be handed to, by key: the ones whose connection
 * is switched on, and the MCP servers' aliases that can be asked where
 * Pinegrap AI is. Each has the name and picture it is shown with and the
 * application it writes through.
 *
 * @return array key => key, name, handle, icon, avatar, app_id (server_id for an alias)
 */
function ws_assistants()
{
    static $list = null;

    if ($list !== null) {
        return $list;
    }

    $list = array();

    if (!ws_delegation_ready()) {
        return $list;
    }

    $ai = ws_ai_config();

    if ($ai['enabled'] && ((int) $ai['app_id'] > 0)) {
        $list['ai'] = array(
            'key'    => 'ai',
            'name'   => 'Pinegrap AI',
            'handle' => '@ai',
            'icon'   => 'bi-cpu',
            'avatar' => PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-ai.svg',
            'app_id' => (int) $ai['app_id'],
        );
    }

    $claude = ws_claude_config();

    if ($claude['enabled'] && ((int) $claude['app_id'] > 0)) {
        $list['claude'] = array(
            'key'    => 'claude',
            'name'   => 'Claude',
            'handle' => '@Claude',
            'icon'   => 'bi-stars',
            'avatar' => PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-assistant.svg',
            'app_id' => (int) $claude['app_id'],
        );
    }

    // An MCP server's alias: Pinegrap AI answers with that server's tools,
    // so an alias is there only where Pinegrap AI is, and only once it has a
    // tool turned on. A server that is an agent itself answers on its own
    // (mcp_agent.php): it is there without Pinegrap AI, while the tool that
    // takes its messages can take them, and writes through no application.
    if (function_exists('ws_mcp_ready') && ws_mcp_ready() && function_exists('ws_mcp_ask_problem')) {
        foreach (ws_mcp_servers() as $server) {
            $agent = function_exists('ws_mcp_agent_on') && ws_mcp_agent_on($server);

            if ($agent ? (ws_mcp_agent_problem($server) !== '') : (!isset($list['ai']) || (ws_mcp_ask_problem($server) !== ''))) {
                continue;
            }

            $key = 'mcp:' . (int) $server['id'];

            $list[$key] = array(
                'key'       => $key,
                'name'      => '@' . $server['alias'],
                'handle'    => '@' . $server['alias'],
                'icon'      => 'bi-plug',
                'avatar'    => PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-ai.svg',
                'app_id'    => $agent ? 0 : (int) $ai['app_id'],
                'server_id' => (int) $server['id'],
            );
        }
    }

    return $list;
}

/**
 * One assistant, or null when there is none by that key.
 *
 * @param string $key
 * @return array|null
 */
function ws_assistant($key)
{
    $list = ws_assistants();

    return $list[(string) $key] ?? null;
}

/**
 * The name an assistant is shown with, also for a key whose connection has
 * been switched off since: the runs it made keep their name. A proposal an
 * application made on a task (proposals.php) sits among the runs under the
 * application's name.
 *
 * @param string $key
 * @return string
 */
function ws_assistant_name($key)
{
    $assistant = ws_assistant($key);

    if ($assistant) {
        return $assistant['name'];
    }

    $proposer = function_exists('ws_proposal_name') ? ws_proposal_name($key) : '';

    if ($proposer !== '') {
        return $proposer;
    }

    $server_id = ws_assistant_mcp_id($key);

    if ($server_id > 0) {
        $server = function_exists('ws_mcp_server') ? ws_mcp_server($server_id) : null;

        return $server ? '@' . $server['alias'] : lang('MCP server');
    }

    return ($key === 'ai') ? 'Pinegrap AI' : (($key === 'claude') ? 'Claude' : (string) $key);
}

/**
 * The picture an assistant is shown with, also for one whose connection has
 * been switched off since.
 *
 * @param string $key
 * @return string
 */
function ws_assistant_avatar($key)
{
    $assistant = ws_assistant($key);

    if ($assistant) {
        return $assistant['avatar'];
    }

    if (function_exists('ws_proposal_agent_parse') && ws_proposal_agent_parse($key)) {
        return ws_proposal_avatar($key);
    }

    return PATH . SOFTWARE_DIRECTORY . '/assets/images/' . ((ws_assistant_agent($key) === 'claude') ? 'ws-assistant.svg' : 'ws-ai.svg');
}

/**
 * The assistant a text calls by name: its tag, its handle written by hand or
 * its command. Claude first when more than one is called, as a note decides
 * it; then an MCP server's alias before Pinegrap AI's own name, as in a
 * channel, since Pinegrap AI answers the alias too. An assistant whose
 * connection is off, or an alias that cannot be asked now, is not called.
 *
 * @param string $body
 * @return string key, or '' for none
 */
function ws_assistant_called($body)
{
    if (!ws_delegation_ready()) {
        return '';
    }

    if (ws_assistant('claude') && ws_claude_asked($body)) {
        return 'claude';
    }

    $server = ws_assistant_alias_named($body);

    if ($server && ws_assistant('mcp:' . (int) $server['id'])) {
        return 'mcp:' . (int) $server['id'];
    }

    return (ws_assistant('ai') && ws_ai_asked($body)) ? 'ai' : '';
}

/**
 * The MCP server whose alias a text writes, whether it can be asked now or
 * not.
 *
 * @param string $body
 * @return array|null the server's row
 */
function ws_assistant_alias_named($body)
{
    if (!function_exists('ws_mcp_alias_named') || !ws_mcp_ready()) {
        return null;
    }

    return ws_mcp_alias_named($body, ws_mcp_servers());
}

/**
 * What keeps an assistant from working on a task: it is not connected, or
 * the task's channel does not let it be asked. A task outside any channel is
 * sent by the person who hands it over, who can read all of it. An MCP
 * server's alias goes by Pinegrap AI's setup and channel switch - one that is
 * an agent itself by the tool that takes its messages and the same channel
 * switch - and a server people sign in to needs an account for the one who
 * hands it over: their own, or the site's.
 *
 * @param array  $viewer
 * @param string $key
 * @param array  $task
 * @return string a sentence, '' when nothing does
 */
function ws_assistant_task_problem($viewer, $key, $task)
{
    if (!ws_assistant($key)) {
        return lang('Invalid request.');
    }

    $agent = ws_assistant_agent($key);

    if (($agent === 'ai') && !ws_ai_ready()) {
        return ws_plain_text($viewer, ws_ai_setup_hint());
    }

    if (($agent === 'claude') && !ws_claude_ready()) {
        return ws_plain_text($viewer, ws_claude_setup_hint());
    }

    $server_id = ws_assistant_mcp_id($key);

    if ($server_id > 0) {
        $server = ws_mcp_server($server_id);
        $problem = ($agent === 'mcp')
            ? ws_mcp_agent_problem($server, $viewer, 0, (int) $task['id'])
            : ws_mcp_ask_problem($server, $viewer, 0, (int) $task['id']);

        if ($problem !== '') {
            return $problem;
        }
    }

    $channel = ((int) $task['channel_id'] > 0) ? ws_channel($task['channel_id']) : null;

    if ($channel) {
        // The server's own agent may be asked where Pinegrap AI may.
        $allowed = ($agent === 'claude') ? ws_claude_channel_allowed($channel) : ws_ai_channel_allowed($channel);

        if (!$allowed) {
            return lang(array('string' => '{var:1} may not be asked in the channel of this task. Somebody who manages the channel can allow it from the channel menu.', 'vars' => ws_assistant_name(($agent === 'mcp') ? $key : $agent)));
        }
    }

    return '';
}

/**
 * An instruction as it is kept: one block of text, tags as tokens, cut to
 * the length a run takes.
 *
 * @param string $text
 * @return string
 */
function ws_delegation_instruction_clean($text)
{
    $text = trim(str_replace("\r\n", "\n", (string) $text));

    if (function_exists('ws_tokens_normalise')) {
        $text = ws_tokens_normalise($text);
    }

    return trim(mb_substr($text, 0, WS_DELEGATION_INSTRUCTION_MAX));
}

/**
 * Is a request status one the assistant is still on?
 *
 * @param string $status
 * @return bool
 */
function ws_delegation_status_open($status)
{
    return in_array((string) $status, array('queued', 'sent', 'running'), true);
}

/**
 * The run under way among a task's request rows, newest first: the newest
 * row whose status is open, not the newest row, since an application's
 * proposal (proposals.php) may have been written after the run began.
 *
 * @param array[] $rows ws_ai_requests rows, newest first
 * @return array|null
 */
function ws_task_delegation_open_row($rows)
{
    foreach ((array) $rows as $row) {
        if (is_array($row) && ws_delegation_status_open($row['status'] ?? '')) {
            return $row;
        }
    }

    return null;
}

/**
 * The column that ties a request to an MCP server, for a query that names
 * its columns: there only once its schema step (10.82) has run.
 *
 * @return string ", mcp_server_id" or ""
 */
function ws_delegation_server_column()
{
    return (function_exists('ws_mcp_ready') && ws_mcp_ready()) ? ', mcp_server_id' : '';
}

/**
 * The run of an assistant that is still under way on a task, if any.
 *
 * @param int $task_id
 * @return array|null
 */
function ws_task_delegation_open_run($task_id)
{
    if (!ws_delegation_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_ai_requests
        WHERE task_id = '" . (int) $task_id . "' AND status IN ('queued', 'sent', 'running')
        ORDER BY id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Whether the person may start a run of this assistant on a task: the checks
 * of ws_task_delegation_run(), made on their own so that the API's dry run
 * meets the same refusals.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $key
 * @return array ok, error
 */
function ws_task_delegation_run_check($viewer, $task, $key)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error);
    };

    if (!ws_delegation_ready() || !ws_can_see_task($viewer, $task)) {
        return $fail(lang('That task could not be found.'));
    }

    $problem = ws_assistant_task_problem($viewer, $key, $task);

    if ($problem !== '') {
        return $fail($problem);
    }

    if (ws_task_delegation_open_run($task['id'])) {
        return $fail(lang('An assistant is already working on this task. Wait for its answer or stop it first.'));
    }

    $agent = ws_assistant_agent($key);
    $waiting = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests
        WHERE requested_by = '" . (int) $viewer['id'] . "' AND status IN ('queued', 'sent', 'running')" . ws_ai_agent_where($agent));

    // The agents of MCP servers share one limit, in channels and on tasks.
    if ($agent === 'mcp') {
        $limit = function_exists('ws_mcp_agent_limit_problem') ? ws_mcp_agent_limit_problem((int) $viewer['id']) : '';

        if ($limit !== '') {
            return $fail($limit);
        }
    } else {
        $limit = ($agent === 'ai') ? WS_AI_PER_PERSON : WS_CLAUDE_PER_PERSON;

        if ($waiting >= $limit) {
            return $fail(lang(array('string' => '{var:1} already has {var:2} requests of yours waiting. Please wait for those to be answered.', 'vars' => array(ws_assistant_name($agent), $limit))));
        }
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Starts a run of an assistant on a task: the request waits in the queue
 * until the assistant takes it. One run at a time on a task, and no more
 * waiting requests per person than the assistant takes from anybody. A run
 * for an MCP server's alias is Pinegrap AI's request with the server set.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $key
 * @param string $instruction
 * @return array ok, error, request_id
 */
function ws_task_delegation_run($viewer, $task, $key, $instruction)
{
    $check = ws_task_delegation_run_check($viewer, $task, $key);

    if (!$check['ok']) {
        return $check + array('request_id' => 0);
    }

    $agent = ws_assistant_agent($key);
    $server_id = ws_assistant_mcp_id($key);

    // Written with its assistant in one go: a request to Pinegrap AI must
    // never sit in the table as Claude's, where Claude's run would take it,
    // nor a request to an alias as Pinegrap AI's own, nor one to a server's
    // own agent as Pinegrap AI's (mcp_agent.php takes agent mcp).
    db("INSERT INTO ws_ai_requests (channel_id, message_id, task_id, note_text, requested_by, status, agent, created_at" . (($server_id > 0) ? ', mcp_server_id' : '') . ")
        VALUES ('0', '0', '" . (int) $task['id'] . "', '" . e(ws_delegation_instruction_clean($instruction)) . "',
            '" . (int) $viewer['id'] . "', 'queued', '" . e($agent) . "', '" . time() . "'" . (($server_id > 0) ? ", '" . $server_id . "'" : '') . ")");

    $request_id = (int) mysqli_insert_id(db::$con);

    if ($request_id <= 0) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'request_id' => 0);
    }

    return array('ok' => true, 'error' => '', 'request_id' => $request_id);
}

/**
 * Whether the person may hand a task to this assistant: the checks of
 * ws_task_delegate() and of the run it starts, made on their own so that the
 * API's dry run meets the same refusals.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $key
 * @return array ok, error
 */
function ws_task_delegate_check($viewer, $task, $key)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error);
    };

    if (!ws_delegation_ready()) {
        return $fail(lang('Invalid request.'));
    }

    if (!ws_can_edit_task($viewer, $task)) {
        return $fail(lang('You may not hand this task to an assistant.'));
    }

    if (!ws_task_is_open($task['status'])) {
        return $fail(lang('Only an open task can be handed to an assistant.'));
    }

    $current = (string) ($task['delegate'] ?? '');

    if (($current !== '') && ($current !== $key) && ws_task_delegation_open_run($task['id'])) {
        return $fail(lang(array('string' => 'The task is with {var:1}, which is still working on it. Stop it first.', 'vars' => ws_assistant_name($current))));
    }

    return ws_task_delegation_run_check($viewer, $task, $key);
}

/**
 * Hands a task to an assistant and starts its first run. The person stays
 * on the task: a task nobody is on gets the person who hands it over, unless
 * it waits in a department's pool. Handing it again to the assistant it is
 * already with is one more run.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $key
 * @param string $instruction
 * @return array ok, error, request_id
 */
function ws_task_delegate($viewer, $task, $key, $instruction)
{
    $check = ws_task_delegate_check($viewer, $task, $key);

    if (!$check['ok']) {
        return $check + array('request_id' => 0);
    }

    $assignees = ws_task_assignee_ids($task['id']);
    $current = (string) ($task['delegate'] ?? '');
    $run = ws_task_delegation_run($viewer, $task, $key, $instruction);

    if (!$run['ok']) {
        return $run;
    }

    if ($current !== $key) {
        db("UPDATE ws_tasks SET delegate = '" . e($key) . "', delegated_by = '" . (int) $viewer['id'] . "', delegated_at = '" . time() . "', updated_at = '" . time() . "'
            WHERE id = '" . (int) $task['id'] . "'");

        if (empty($assignees) && ((int) $task['department_id'] === 0)) {
            ws_task_set_assignees($viewer, $task['id'], array((int) $viewer['id']));
            $assignees = array((int) $viewer['id']);
        }

        if (function_exists('pg_announce')) {
            pg_announce('workspace.task.delegated', ws_task_event_payload(ws_task($task['id']), $assignees) + array(
                'agent'        => $key,
                'delegated_by' => (int) $viewer['id'],
                'request_id'   => (int) $run['request_id'],
            ));
        }
    }

    return $run;
}

/**
 * Whether the person may stop the assistant's run on a task: the checks of
 * ws_task_delegation_stop(), made on their own so that the API's dry run
 * meets the same refusals.
 *
 * @param array $viewer
 * @param array $task
 * @return array ok, error, run (the run under way, null when there is none)
 */
function ws_task_delegation_stop_check($viewer, $task)
{
    if (!ws_delegation_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'run' => null);
    }

    $run = ws_task_delegation_open_run($task['id']);
    $mine = $run && ((int) $run['requested_by'] === (int) $viewer['id']);

    if (!$mine && !ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You may not change this task.'), 'run' => null);
    }

    return array('ok' => true, 'error' => '', 'run' => $run);
}

/**
 * Stops the assistant's run on a task, and with $undelegate takes the task
 * back from it. The person who asked for the run, or anybody who may change
 * the task.
 *
 * @param array $viewer
 * @param array $task
 * @param bool  $undelegate
 * @return array ok, error
 */
function ws_task_delegation_stop($viewer, $task, $undelegate)
{
    $check = ws_task_delegation_stop_check($viewer, $task);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error']);
    }

    $run = $check['run'];

    if ($run) {
        $reason = lang(array('string' => 'Stopped by {var:1}.', 'vars' => ws_person_name($viewer['id'])));

        // A server's own agent working on the run in the background is
        // stopped on the server too (mcp_agent.php).
        if (((string) ($run['agent'] ?? '') === 'mcp') && function_exists('ws_mcp_agent_task_stop')) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(WS_MCP_AGENT_POLL_TIMEOUT * 2 + 30);
            }

            ws_mcp_agent_task_stop($run, $reason, 'cancelled');
        }

        db("UPDATE ws_ai_requests SET status = 'cancelled', error = '" . e($reason) . "',
                answered_at = '" . time() . "', ai_state = NULL
            WHERE task_id = '" . (int) $task['id'] . "' AND status IN ('queued', 'sent', 'running')");
    }

    if ($undelegate && ((string) ($task['delegate'] ?? '') !== '')) {
        db("UPDATE ws_tasks SET delegate = '', delegated_by = 0, delegated_at = 0, updated_at = '" . time() . "' WHERE id = '" . (int) $task['id'] . "'");
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Keeps an assistant's answer on its run: shown on the task, and the person
 * who asked is told. A run that was stopped meanwhile keeps its state.
 *
 * @param array  $row  the request
 * @param string $text
 * @return bool written
 */
function ws_task_delegation_answer($row, $text)
{
    $now = time();

    db("UPDATE ws_ai_requests SET status = 'answered', note_reply = '" . e(ws_tokens_normalise(trim((string) $text))) . "',
            answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = '', ai_state = NULL
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    if (mysqli_affected_rows(db::$con) < 1) {
        return false;
    }

    ws_task_delegation_told($row, 'answered');

    return true;
}

/**
 * Tells the person who asked that a run ended, answered or not, and
 * announces it.
 *
 * @param array  $row
 * @param string $status answered | failed
 */
function ws_task_delegation_told($row, $status)
{
    $task = ws_task((int) $row['task_id']);

    if (!$task) {
        return;
    }

    ws_notify((int) $row['requested_by'], 'task_assistant', array('task_id' => (int) $task['id'], 'actor_id' => 0));

    if (function_exists('pg_announce')) {
        pg_announce('workspace.task.assistant_answered', ws_task_event_payload($task, ws_task_assignee_ids($task['id'])) + array(
            'agent'        => ws_task_run_key($row),
            'request_id'   => (int) $row['id'],
            'requested_by' => (int) $row['requested_by'],
            'outcome'      => $status,
        ));
    }
}

/**
 * The runs on a task a new run is told about: what the assistants were
 * asked and what they answered, oldest first. An application's proposal
 * (proposals.php) is left out: nobody asked for it, and its words would
 * reach the model as earlier work the person had asked for.
 *
 * @param array $task
 * @param int   $before_id only runs before this request
 * @return array[] agent (the assistant's key), instruction, status, answer, error, asked_by, at
 */
function ws_task_delegation_earlier($task, $before_id = 0)
{
    if (!ws_delegation_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT id, agent, note_text, note_reply, status, error, requested_by, created_at" . ws_delegation_server_column() . " FROM ws_ai_requests
        WHERE task_id = '" . (int) $task['id'] . "' AND status IN ('answered', 'failed')"
        . (((int) $before_id > 0) ? " AND id < '" . (int) $before_id . "'" : '')
        . (function_exists('ws_proposal_where') ? " AND " . ws_proposal_where('', false) : '') . "
        ORDER BY id DESC LIMIT " . WS_DELEGATION_EARLIER);

    $out = array();

    foreach (array_reverse($rows) as $row) {
        $out[] = array(
            'agent'       => ws_task_run_key($row),
            'instruction' => (string) $row['note_text'],
            'status'      => (string) $row['status'],
            'answer'      => (string) $row['note_reply'],
            'error'       => (string) $row['error'],
            'asked_by'    => (int) $row['requested_by'],
            'at'          => (int) $row['created_at'],
        );
    }

    return $out;
}

/**
 * Where a run stands, as the drawer's line says it.
 *
 * @param array $viewer
 * @param array $row
 * @return array status, label, icon
 */
function ws_task_delegation_state($viewer, $row)
{
    if ((string) ($row['agent'] ?? 'claude') === 'ai') {
        return ws_ai_request_state($viewer, $row);
    }

    if (((string) ($row['agent'] ?? '') === 'mcp') && function_exists('ws_mcp_agent_request_state')) {
        return ws_mcp_agent_request_state($row, $viewer);
    }

    // A proposal an application made on the task (proposals.php).
    if (ws_task_run_is_proposal($row)) {
        return ws_proposal_request_state($row);
    }

    return ws_claude_request_state($viewer, $row);
}

/**
 * The assistant part of a task's drawer: who the task is with, the runs on
 * it with their answers, and what the reader may do.
 *
 * @param array $viewer
 * @param array $task
 * @return array|null null where the module is not there
 */
function ws_task_delegation_detail($viewer, $task)
{
    if (!ws_delegation_ready()) {
        return null;
    }

    $assignees = ws_task_assignee_ids($task['id']);
    $can_edit = ws_can_edit_task($viewer, $task, $assignees);
    $assistants = array();

    foreach (ws_assistants() as $key => $assistant) {
        $server_id = ws_assistant_mcp_id($key);
        $server = ($server_id > 0) ? ws_mcp_server($server_id) : null;
        $connect = null;

        if ($server) {
            // A server people sign in to, where the reader has no account
            // yet (neither their own nor the site's): they connect their own
            // from here. A server that is an agent itself needs no Pinegrap
            // AI.
            $agent = (ws_assistant_agent($key) === 'mcp');
            $problem = $agent ? ws_mcp_agent_problem($server, $viewer) : ws_mcp_ask_problem($server, $viewer);
            $able = $agent || ws_ai_ready();
            $ready = $able && ($problem === '');

            if ($able && ($problem !== '') && ($server['auth_kind'] === 'oauth')) {
                $connect = array('server_id' => (int) $server['id'], 'alias' => (string) $server['alias']);
            }
        } else {
            $ready = ($key === 'ai') ? ws_ai_ready() : ws_claude_ready();
        }

        $assistants[] = array(
            'key'     => $key,
            'name'    => $assistant['name'],
            'title'   => $server ? (string) $server['name'] : '',
            'icon'    => $assistant['icon'],
            'avatar'  => $assistant['avatar'],
            'ready'   => $ready,
            'connect' => $connect,
        );
    }

    $rows = (array) db_items("SELECT * FROM ws_ai_requests
        WHERE task_id = '" . (int) $task['id'] . "'
        ORDER BY id DESC LIMIT " . WS_DELEGATION_RUNS_SHOWN);

    // An older run whose proposals still wait stays in view, or they could
    // not be applied.
    $shown = array_map(function ($row) {
        return (int) $row['id'];
    }, $rows);

    $older = (array) db_values("SELECT DISTINCT request_id FROM ws_ai_changes
        WHERE task_id = '" . (int) $task['id'] . "' AND channel_id = 0 AND status = 'pending'"
        . (empty($shown) ? '' : " AND request_id NOT IN (" . implode(',', $shown) . ")"));

    if (!empty($older)) {
        $rows = array_merge($rows, (array) db_items("SELECT * FROM ws_ai_requests
            WHERE id IN (" . implode(',', array_map('intval', $older)) . ") AND task_id = '" . (int) $task['id'] . "'
            ORDER BY id DESC"));
    }

    $tokens = array();
    $people_ids = array((int) ($task['delegated_by'] ?? 0));

    foreach ($rows as $row) {
        $tokens = array_merge($tokens, ws_tokens((string) $row['note_text']), ws_tokens((string) $row['note_reply']));
        $people_ids[] = (int) $row['requested_by'];
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people(array_unique(array_filter($people_ids)));
    $changes = ws_changes_map($viewer, array_map(function ($row) {
        return (int) $row['id'];
    }, $rows), 'request_id');
    $runs = array();
    $open = false;
    $waiting = 0;

    foreach ($rows as $row) {
        $agent = (string) ($row['agent'] ?? 'claude');
        $key = ws_task_run_key($row);
        $state = ws_task_delegation_state($viewer, $row);
        $running = ws_delegation_status_open($row['status']);
        $open = $open || $running;

        $proposed = $changes[(int) $row['id']] ?? array();

        foreach ($proposed as $change) {
            $waiting += ($change['status'] === 'pending') ? 1 : 0;
        }

        $runs[] = array(
            'id'          => (int) $row['id'],
            'agent'       => $agent,
            'assistant'   => array('name' => ws_assistant_name($key), 'avatar' => ws_assistant_avatar($key), 'app' => true),
            // An application's proposal: asked_by is the person its cards
            // wait for, not somebody who asked for it.
            'proposal'    => ws_task_run_is_proposal($row),
            'asked_by'    => $people[(int) $row['requested_by']]['name'] ?? ws_person_name($row['requested_by']),
            'asked_at'    => ws_time_label($row['created_at']),
            'instruction' => ((string) $row['note_text'] !== '') ? ws_render_body((string) $row['note_text'], $refs) : '',
            'answer'      => (($row['status'] === 'answered') && ((string) $row['note_reply'] !== '')) ? ws_render_body((string) $row['note_reply'], $refs) : '',
            'answered_at' => ((int) $row['answered_at'] > 0) ? ws_time_label($row['answered_at']) : '',
            'status'      => (string) $row['status'],
            'label'       => $state['label'],
            'icon'        => $state['icon'],
            'open'        => $running,
            'mine'        => ((int) $row['requested_by'] === (int) $viewer['id']),
            'changes'     => $proposed,
        );
    }

    $delegate = (string) ($task['delegate'] ?? '');
    $with = null;

    if ($delegate !== '') {
        $with = array(
            'key'    => $delegate,
            'name'   => ws_assistant_name($delegate),
            'avatar' => ws_assistant_avatar($delegate),
            'by'     => $people[(int) $task['delegated_by']]['name'] ?? ws_person_name($task['delegated_by']),
            'at'     => ((int) $task['delegated_at'] > 0) ? ws_time_label($task['delegated_at']) : '',
        );
    }

    // The run under way, which an application's proposal written after it
    // began does not hide.
    $first = ws_task_delegation_open_row($rows);

    // A run of an MCP server's own agent that waits for its asker's approval
    // (mcp_agent.php) has nothing for the screens to carry on until then.
    $carried = $open && $first && !(((string) $first['agent'] === 'mcp') && ((string) $first['status'] === 'sent'));

    return array(
        'delegate'   => $with,
        'assistants' => $assistants,
        'runs'       => $runs,
        'open'       => $open,
        'open_agent' => $carried ? (string) $first['agent'] : '',
        'can_hand'   => $can_edit && ws_task_is_open($task['status']) && !empty($assistants),
        'can_stop'   => $open && ($can_edit || ($first && ((int) $first['requested_by'] === (int) $viewer['id']))),
        'can_take'   => $can_edit && ($delegate !== ''),
        'waiting'    => $waiting,
        'max'        => WS_DELEGATION_INSTRUCTION_MAX,
    );
}

/**
 * How many changes the assistants proposed on each of these tasks wait to be
 * applied or set aside.
 *
 * @param int[] $task_ids
 * @return array task id => count
 */
function ws_task_delegation_waiting_map($task_ids)
{
    $task_ids = array_values(array_filter(array_map('intval', (array) $task_ids)));

    if (empty($task_ids) || !ws_delegation_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT task_id, COUNT(*) AS waiting FROM ws_ai_changes
        WHERE task_id IN (" . implode(',', $task_ids) . ") AND channel_id = 0 AND status = 'pending'
        GROUP BY task_id") as $row) {
        $out[(int) $row['task_id']] = (int) $row['waiting'];
    }

    return $out;
}

/**
 * The assistant on a run under way on each of these tasks, for those that
 * have one.
 *
 * @param int[] $task_ids
 * @return array task id => assistant key (mcp:<server id> for an alias)
 */
function ws_task_delegation_open_map($task_ids)
{
    $task_ids = array_values(array_filter(array_map('intval', (array) $task_ids)));

    if (empty($task_ids) || !ws_delegation_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT task_id, agent" . ws_delegation_server_column() . " FROM ws_ai_requests
        WHERE task_id IN (" . implode(',', $task_ids) . ") AND status IN ('queued', 'sent', 'running')
        ORDER BY id") as $row) {
        $out[(int) $row['task_id']] = ws_task_run_key($row);
    }

    return $out;
}

/**
 * A run started on the screen goes on at once: Claude's routine is fired,
 * Pinegrap AI is carried on by the screen that waits (ws_ai_kick).
 *
 * @param string $key
 */
function ws_task_delegation_start($key)
{
    if (($key === 'claude') && function_exists('ws_claude_dispatch')) {
        ws_claude_dispatch();
    }
}

/**
 * A run of an assistant on a task started by a note that calls it by name.
 * The note is the instruction; the task is not handed over.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $body the note
 * @return string a sentence when the assistant could not be asked, '' otherwise
 */
function ws_task_delegation_from_note($viewer, $task, $body)
{
    $key = ws_assistant_called($body);

    if ($key === '') {
        return ws_task_delegation_alias_problem($viewer, $body);
    }

    $run = ws_task_delegation_run($viewer, $task, $key, $body);

    if (!$run['ok']) {
        return $run['error'];
    }

    ws_task_delegation_start($key);

    return '';
}

/**
 * Why a note that writes an MCP server's alias asked nobody: the server is
 * turned off or has no tool on, or Pinegrap AI, which answers for it, is not
 * connected - for a server that is an agent itself, the tool that takes its
 * messages can no longer take them. In a channel the same message gets
 * these words as a system message (ai.php, mcp_agent.php); a note has its
 * notice instead.
 *
 * @param array  $viewer
 * @param string $body the note
 * @return string a sentence, '' when no alias is written
 */
function ws_task_delegation_alias_problem($viewer, $body)
{
    $server = ws_assistant_alias_named($body);

    if (!$server) {
        return '';
    }

    if (function_exists('ws_mcp_agent_on') && ws_mcp_agent_on($server)) {
        return ws_mcp_agent_problem($server);
    }

    $problem = ws_mcp_ask_problem($server);

    if ($problem !== '') {
        return $problem;
    }

    return ws_assistant('ai') ? '' : ws_plain_text($viewer, ws_ai_setup_hint());
}

/**
 * The task a run is for, as the assistant reads it: the task, the people,
 * its notes, and what the assistants were asked and answered on it before.
 *
 * @param array $asker
 * @param array $row the request
 * @return array|null null when the task is gone or the person who asked may
 *                    no longer see it
 */
function ws_task_delegation_context($asker, $row)
{
    $task = ws_task((int) $row['task_id']);

    if (!$task || !ws_can_see_task($asker, $task)) {
        return null;
    }

    $earlier = array();

    foreach (ws_task_delegation_earlier($task, (int) $row['id']) as $item) {
        $earlier[] = array(
            'agent'       => ws_assistant_name($item['agent']),
            'asked_by'    => ws_person_name($item['asked_by']),
            'instruction' => ws_plain_text($asker, $item['instruction']),
            'answer'      => ($item['status'] === 'answered') ? ws_plain_text($asker, $item['answer']) : '',
            'error'       => $item['error'],
        );
    }

    return array(
        'task'         => $task,
        'delegated'    => ((string) ($task['delegate'] ?? '') === ws_task_run_key($row)),
        'delegated_by' => (int) ($task['delegated_by'] ?? 0),
        'earlier'      => $earlier,
    );
}

/**
 * Every text assets/js/workspace_task_delegate.js shows.
 *
 * @return array
 */
function ws_delegation_js_strings()
{
    // An MCP server's alias written in a note asks it too: the first one
    // is named as the example.
    $alias = '';

    foreach (ws_assistants() as $key => $assistant) {
        if (ws_assistant_mcp_id($key) > 0) {
            $alias = $assistant['handle'];
            break;
        }
    }

    return array(
        'dg_title'         => lang('Assistant'),
        'dg_hand'          => lang('Hand to an assistant'),
        'dg_instruction'   => lang('What should it do? Left empty, it works from the task itself.'),
        'dg_follow'        => lang('Write to the assistant'),
        'dg_follow_hint'   => lang('Another instruction on the same task; it sees what it answered before.'),
        'dg_send'          => lang('Send'),
        'dg_with'          => ws_js_template('Assistant: {var:1}', 1),
        'dg_with_meta'     => ws_js_template('Handed over by {var:1}, {var:2}', 2),
        'dg_responsible'   => lang('The people on the task stay responsible. What the assistant proposes waits for the person who asked to apply it.'),
        'dg_stop'          => lang('Stop'),
        'dg_stop_confirm'  => lang('Stop the assistant\'s work on this task?'),
        'dg_take_back'     => lang('Take the task back'),
        'dg_take_confirm'  => lang('Take the task back from the assistant? A run under way is stopped.'),
        'dg_asked'         => ws_js_template('{var:1} asked, {var:2}', 2),
        'dg_proposed'      => ws_js_template('Proposed for {var:1}, {var:2}', 2),
        'dg_no_instruction' => lang('No instruction: the task itself.'),
        'dg_none'          => ($alias !== '')
            ? lang(array('string' => 'No assistant has worked on this task yet. A note that writes @ai, @Claude or an MCP server\'s alias such as {var:1} asks one too.', 'vars' => $alias))
            : lang('No assistant has worked on this task yet. A note that writes @ai or @Claude asks one too.'),
        'dg_not_ready'     => lang('not ready'),
        'dg_not_chosen'    => lang('— Not selected —'),
        'dg_started'       => lang('The task was handed to the assistant.'),
        'dg_sent'          => lang('Sent to the assistant.'),
        'dg_change_done'   => lang('The change was applied.'),
        'dg_waiting'       => ws_js_template('{var:1} proposed change(s) wait for approval', 1),
        'dg_waiting_short' => ws_js_template('{var:1} awaiting approval', 1),
        'dg_connect_first' => ws_js_template('@{var:1} has no account for you yet: connect your own account first.', 1),
    );
}

/**
 * What the screens are told about delegation when they open.
 *
 * @return array
 */
function ws_delegation_js_config()
{
    return array('ready' => ws_delegation_ready() && !empty(ws_assistants()));
}
