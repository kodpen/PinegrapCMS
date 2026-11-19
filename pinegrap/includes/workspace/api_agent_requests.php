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
 * What the answer route returns: the request, and how concluding the
 * discussion went (asked false when conclude was not sent).
 *
 * @return array
 */
function ws_api_agent_answer_schema()
{
    return ws_api_claude_request_schema() + array(
        'conclude' => array('asked' => 'boolean', 'done' => 'boolean', 'error' => 'string'),
        'offer'    => array('asked' => 'boolean', 'done' => 'boolean', 'error' => 'string'),
    );
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
            'description' => 'A message that woke the agent in a channel (its handle, its wake rule) made one request for it; a task a person handed to the agent made one with task filled and no message. status open (the default) is what waits to be claimed, oldest first, leaving out a request whose message the writer is editing now; all is the latest 50 of every state.' . $only,
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
            'description' => 'Marks the request as being worked on and leaves an eye on the message that asked. 409 already_claimed when another run has taken it, 409 closed when it is closed, 409 editing while the writer is editing the message that asked (try again in a minute).' . $only,
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
            'returns'     => ws_api_agent_answer_schema(),
            'summary'     => 'Answer a request',
            'description' => 'Writes text under the message that asked, as the application, with the person who asked mentioned, and swaps the eye for a tick. For a run on a task (task filled) the text is kept on the run and shown on the task, the person who asked is told, and no message is written; it takes text only (422 for no_answer, emoji, conclude or offer_conclude), 403 once the task\'s channel no longer allows the agent. no_answer true closes the request without writing anything (it takes no text). emoji leaves one emoji on the message that asked, with text or with no_answer. conclude true, for a request asked in a discussion by a person in it who asks for it, concludes the discussion after the answer is written (closed_by is that person); any other request is not concluded, conclude.error says why and the answer is still written. offer_conclude true, with text, unasked, puts a Conclude the discussion button under the answer for the people in the discussion; one of them presses it to conclude in their own name (the same asker rule; offer.error says why it was not made). Other things the agent does go through their own routes: a task POST /workspace/tasks, a poll POST /workspace/channels/{id}/polls, an approval request POST /workspace/channels/{id}/approvals (and POST /workspace/approvals/{id}/decide when asked to approve), a proposed change /workspace/changes. 403 when the channel no longer lets the agent answer or the person who asked can no longer write there: close the request with POST /workspace/agent-requests/{id}/fail; 422 on text for a wake made in no channel: write with POST /workspace/channels/{id}/messages, then close it with no_answer; 409 closed when the request is closed; 409 editing while the writer is editing the message that asked; 409 edited when it was edited after the claim: the request waits again, claim it and read the new words.' . $only,
            'params'      => array(
                $id,
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3900, 'description' => 'The answer; required unless no_answer is true.'),
                array('name' => 'no_answer', 'in' => 'body', 'type' => 'bool', 'description' => 'Close the request without writing anything: it needs no answer.'),
                array('name' => 'emoji', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'One fitting emoji left on the message that asked, as the application: beside text, or with no_answer in place of it. Never on the agent\'s own message; a wake asked by no message takes none.'),
                array('name' => 'conclude', 'in' => 'body', 'type' => 'bool', 'description' => 'Conclude the discussion the request was asked in, after the answer: only when a person in it asked for it. Never unasked.'),
                array('name' => 'offer_conclude', 'in' => 'body', 'type' => 'bool', 'description' => 'Unasked, when the discussion seems to have reached its end: a Conclude the discussion button under the answer, which a person in it presses. Takes text; not with conclude.'),
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
            'description' => 'Closes the request as failed with the reason, told to the person who asked under their message while they may still write there; for a run on a task the reason stays on the run and the person who asked is told. 409 closed when the request is closed.' . $only,
            'params'      => array(
                $id,
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 250, 'required' => true),
            ),
        ),
        array(
            'id'          => 'workspace.agent_requests.progress',
            'method'      => 'POST',
            'path'        => '/workspace/agent-requests/{id}/progress',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_agent_request_progress',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Say where the work on a request is',
            'description' => 'While the agent holds a request (after its claim, before its answer or fail), one short line on where it is ("reading the plans", "waiting for CI, back by 22:30"). The line under the message that asked shows it after "is working on it", with the time of the claim; the answer takes its place, and closing the request clears it. A new note replaces the last. Writes no message, wakes nobody, adds no inbox line. 409 not_claimed before the claim, 409 closed once the request is closed, 429 rate_limited for a second note inside ' . WS_AGENT_PROGRESS_GAP . ' seconds (Retry-After says when), 422 for an empty note or one over ' . WS_AGENT_PROGRESS_MAX . ' characters.' . $only,
            'params'      => array(
                $id,
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => WS_AGENT_PROGRESS_MAX, 'required' => true, 'description' => 'Where the work is, in one short line.'),
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
            'description' => 'The same as /wake @agent in a channel, for the agent itself: every (minutes, 1-1440) wakes it every so often from now; at (a moment to come) once; off clears the schedule. Give exactly one of them. Each wake makes a request with no message, carrying the note, and knocks a push door; the hourly brake holds. A person sets it with /wake in a channel, on the Agents card or with POST /workspace/channels/{id}/agents/{agent_id}/wake.' . $only,
            'params'      => array(
                array('name' => 'every', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => WS_AGENT_WAKE_EVERY_MAX),
                array('name' => 'at', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'off', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => WS_AGENT_WAKE_NOTE_MAX),
            ),
        ),
        array(
            'id'          => 'workspace.agent_feedback.get',
            'method'      => 'GET',
            'path'        => '/workspace/agent-feedback',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_agent_feedback_get',
            'mcp'         => false,
            'returns'     => ws_api_agent_feedback_schema(),
            'summary'     => 'How people marked the calling agent\'s answers',
            'description' => 'The person who asked or a manager of the channel may mark an answer of the agent right or wrong, with a short reason; one mark per answer, the last one stands. marks lists the newest ' . WS_AGENT_FEEDBACK_LIST_MAX . ' on answers that are still there and in channels the key reads (message_id is the answer); counts are the marks of the last 7 and 30 days by the time they were made.' . $only,
            'params'      => array(),
        ),
        array(
            'id'          => 'workspace.agent_report.set',
            'method'      => 'POST',
            'path'        => '/workspace/agent-report',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_agent_report_set',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => ws_api_agent_report_schema(),
            'summary'     => 'Report the calling agent\'s model and plan usage',
            'description' => 'The agent tells the site the model it runs on and its plan\'s usage as it reads it; the Agents card shows the last report with its age. The site cannot check either value. A report needs model or windows; anything outside the rules refuses the whole report with 422. It replaces the previous report: only the last one is kept. An agent that cannot read its usage leaves windows out.' . $only,
            'params'      => array(
                array('name' => 'model', 'in' => 'body', 'type' => 'string', 'max_length' => WS_AGENT_REPORT_MODEL_MAX, 'description' => 'The model the agent runs on, as it names it; at most 80 characters.'),
                array('name' => 'windows', 'in' => 'body', 'type' => 'list', 'of' => array('name' => 'string', 'percent' => 'integer', 'resets_at' => 'string'), 'max_items' => WS_AGENT_REPORT_WINDOWS_MAX,
                    'description' => 'Up to four usage windows, each exactly {name, percent, resets_at}: name one of ' . implode(', ', array_keys(ws_agent_report_window_list())) . ', each once; percent a whole number 0-100 used; resets_at when the window starts again (ISO-8601 or a unix timestamp).'),
            ),
        ),
    );
}

// What the report route returns.
function ws_api_agent_report_schema()
{
    return array(
        'handle'      => 'string',
        'model'       => 'string',
        'windows'     => array(array('name' => 'string', 'percent' => 'integer', 'resets_at' => 'datetime?')),
        'reported_at' => 'datetime?',
    );
}

/**
 * A report as the route gives it. Pure but for api_time().
 *
 * @param string $handle
 * @param array  $values ws_agent_report_check() values
 * @param int    $at     the report's time, 0 for one not written (a dry run)
 * @return array
 */
function ws_api_agent_report_present($handle, $values, $at)
{
    return array(
        'handle'      => (string) $handle,
        'model'       => (string) $values['model'],
        'windows'     => array_map(function ($window) {
            return array('name' => $window['name'], 'percent' => (int) $window['percent'], 'resets_at' => api_time($window['resets_at']));
        }, (array) $values['windows']),
        'reported_at' => ((int) $at > 0) ? api_time($at) : null,
    );
}

/**
 * The windows of a request with each reset time read as unix seconds; a
 * time that does not read stays as given, so the check refuses it. Pure but
 * for api_time_parse().
 *
 * @param mixed $windows
 * @return mixed
 */
function ws_api_agent_report_times($windows)
{
    if (!is_array($windows)) {
        return $windows;
    }

    foreach ($windows as $index => $window) {
        if (is_array($window) && isset($window['resets_at']) && is_string($window['resets_at'])) {
            $parsed = api_time_parse($window['resets_at']);
            $windows[$index]['resets_at'] = ($parsed === null) ? $window['resets_at'] : (int) $parsed;
        }
    }

    return $windows;
}

function ws_api_agent_report_set($params)
{
    $agent = ws_api_agent_caller();

    if (!ws_agent_report_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $check = ws_agent_report_check($params['model'] ?? null, ws_api_agent_report_times($params['windows'] ?? null));

    if (!$check['ok']) {
        api_fail_validation($check['error'], $check['field']);
    }

    api_dry_run_stop('reported', 'workspace_agent_report', ws_api_agent_report_present($agent['handle'], $check['values'], 0));

    $now = time();
    ws_agent_report_save((int) $agent['id'], $check['values'], $now);

    api_ok(ws_api_agent_report_present($agent['handle'], $check['values'], $now));
}

// What the wake routes return.
function ws_api_agent_wake_schema()
{
    return array('handle' => 'string', 'every' => 'integer', 'next_at' => 'datetime?', 'note' => 'string');
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

/**
 * The every, at, off and note of a wake route as a /wake line's parse
 * (ws_agent_wake_parse()), or the refusal: exactly one of every, at and
 * off, and at a moment to come. The agent's own route and a person's
 * channel route read their input the same way.
 *
 * @param array $params
 * @param int   $now
 * @return array action, every, at, note
 */
function ws_api_agent_wake_parsed($params, $now)
{
    $given = array_values(array_filter(array('every', 'at', 'off'), function ($name) use ($params) {
        return isset($params[$name]) && ($params[$name] !== null) && ($params[$name] !== false);
    }));

    if (count($given) !== 1) {
        api_fail_validation(lang('Give exactly one of every, at or off.'), 'every');
    }

    $parsed = array('action' => $given[0], 'every' => (int) ($params['every'] ?? 0), 'at' => (int) ($params['at'] ?? 0), 'note' => trim((string) ($params['note'] ?? '')));

    if (($parsed['action'] === 'at') && ($parsed['at'] <= (int) $now)) {
        api_fail_validation(lang('That time has passed. Choose a time to come.'), 'at');
    }

    return $parsed;
}

function ws_api_agent_wake_set($params)
{
    $agent = ws_api_agent_caller();
    $now = time();
    $values = ws_agent_wake_values(ws_api_agent_wake_parsed($params, $now), $now);

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
    $row = ws_api_agent_caller_row();

    if ($row === null) {
        api_fail(403, 'forbidden', ws_agent_caller_refusal_text(ws_api_agent_caller_paused_handle()));
    }

    ws_agent_seen_mark((array) $row);

    return $row;
}

/**
 * The handle of the paused app agent the calling key belongs to, '' when
 * the key is no paused agent's own: the 403 then says where it is paused.
 *
 * @return string
 */
function ws_api_agent_caller_paused_handle()
{
    $app_id = ws_api_app_id();

    if (!function_exists('ws_agents_ready') || !ws_agents_ready() || ($app_id <= 0) || (function_exists('api_current_device') && (api_current_device() !== null))) {
        return '';
    }

    return (string) db_value("SELECT handle FROM ws_agents WHERE kind = 'app' AND status = 'paused' AND app_id = '" . $app_id . "' ORDER BY id LIMIT 1");
}

/**
 * The 403 an agent route gives a caller that is no active app agent: a
 * paused agent is told where it is paused and who turns it back on. Pure.
 *
 * @param string $paused_handle the paused agent's handle, '' for any other caller
 * @return string
 */
function ws_agent_caller_refusal_text($paused_handle)
{
    if ((string) $paused_handle !== '') {
        return lang(array('string' => '@{var:1} is paused on the Agents card (Site Settings): its own routes answer again once a person sets it back to Active there.', 'vars' => (string) $paused_handle));
    }

    return lang('Only an active application agent, with its own key, may use this endpoint.');
}

/**
 * The answer route's refusals that name the next step. Pure.
 *
 * @param string $kind       forbidden: the answer can no longer be posted
 *                           where it was asked; no_channel: a wake made in
 *                           no channel takes no text
 * @param int    $request_id
 * @return string
 */
function ws_agent_answer_refusal_text($kind, $request_id)
{
    if ($kind === 'no_channel') {
        return lang('This wake was made in no channel: write with POST /workspace/channels/{id}/messages, then close it with no_answer.');
    }

    return lang(array('string' => 'The answer cannot be posted there any more: the agent is no longer allowed in that channel, or the person who asked can no longer write there. Close the request with POST /workspace/agent-requests/{var:1}/fail (reason).', 'vars' => (string) (int) $request_id));
}

/**
 * The calling agent, or null when the caller is no active app agent using
 * its application's own key: for a route a person and an agent share. It
 * does not mark the agent seen; the route does once it acts as the agent.
 *
 * @return array|null the raw ws_agents row
 */
function ws_api_agent_caller_row()
{
    $app_id = ws_api_app_id();
    $row = (function_exists('ws_agents_ready') && ws_agents_ready() && ($app_id > 0) && (!function_exists('api_current_device') || (api_current_device() === null)))
        ? db_item("SELECT * FROM ws_agents WHERE kind = 'app' AND status = 'active' AND app_id = '" . $app_id . "' ORDER BY id LIMIT 1")
        : null;

    return is_array($row) ? $row : null;
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

/**
 * Stops a claim or an answer of a request whose message went into an
 * earlier version of a cleared conversation: the request is cancelled with
 * the clear's reason (not on a dry run) and nothing is written on the kept
 * message. A request made before its clear cancelled such requests, or
 * between the clear and its cancel, ends here.
 *
 * @param array $row ws_ai_requests
 */
function ws_api_agent_request_kept_stop($row)
{
    if (!function_exists('ws_agent_request_in_past') || !ws_agent_request_in_past($row)) {
        return;
    }

    if (!api_dry_run_requested()) {
        ws_agent_request_freeze((int) $row['id']);
    }

    api_fail(409, 'conversation_cleared', ws_agent_request_cleared_reason());
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
        // A request whose message is being edited waits out of the list.
        $where = ($status === 'open') ? "status IN ('queued', 'sent')" . (function_exists('ws_edit_hold_free_sql') ? ws_edit_hold_free_sql() : '') : "status = '" . e($status) . "'";
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

    ws_api_agent_request_kept_stop($row);

    if (function_exists('ws_edit_hold_is_held') && ws_edit_hold_is_held($row, time())) {
        $said = ws_edit_hold_refusal_error('held');
        api_fail(409, $said['code'], $said['error']);
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

    // A task handed to the agent: the answer is the run's note on the task.
    if ((int) ($row['task_id'] ?? 0) > 0) {
        ws_api_agent_task_answer($agent, $viewer, $row, $params);
        return;
    }

    ws_api_agent_request_kept_stop($row);

    // Held while the message is edited; edited after the claim, the
    // request waits again for a claim that reads the new words.
    if (function_exists('ws_edit_hold_answer_guard')) {
        ws_edit_hold_answer_guard($row, function () use ($agent, $row) {
            ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);
        });
    }

    $refused = ws_api_claude_answer_input_refusal('channel', $params);

    if ($refused['field'] !== '') {
        api_fail_validation($refused['error'], $refused['field']);
    }

    // The emoji is checked first, so a dry run meets the same refusals.
    $emoji = trim((string) ($params['emoji'] ?? ''));
    $asking = null;

    if ($emoji !== '') {
        $asking = ((int) $row['message_id'] > 0) ? ws_message((int) $row['message_id']) : null;
        $problem = ws_agent_emoji_problem($emoji, $asking, (int) $agent['app_id'], function_exists('ws_message_in_past') && ws_message_in_past($asking));

        if ($problem === '') {
            $checked = ws_reaction_check($viewer, $asking, $emoji, (int) $agent['app_id'], true);
            $problem = $checked['ok'] ? '' : (string) $checked['error'];
        }

        if ($problem !== '') {
            api_fail_validation($problem, 'emoji');
        }
    }

    // Concluding is checked before the dry run stops, so a dry run tells
    // whether it would be done; refused, the answer is still written.
    $conclude = !empty($params['conclude'])
        ? ws_thread_close_for_agent($row, (string) $agent['handle'], true) : null;
    $concluding = $conclude ? array('conclude' => array('asked' => true, 'done' => $conclude['ok'], 'error' => $conclude['error'])) : array();

    // An offer stands under the answer's words; concluding at once leaves
    // nothing to offer.
    if (!empty($params['offer_conclude']) && $conclude) {
        api_fail_validation(lang('Offer to conclude the discussion, or conclude it; not both.'), 'offer_conclude');
    }

    $offer_problem = !empty($params['offer_conclude']) ? ws_thread_offer_problem($row, empty($params['no_answer'])) : null;
    $concluding += ($offer_problem !== null) ? array('offer' => array('asked' => true, 'done' => ($offer_problem === ''), 'error' => (string) $offer_problem)) : array();

    $now = time();
    $reply_id = 0;

    if (empty($params['no_answer'])) {
        // A wake made in no channel: the agent writes where it needs with
        // workspace.messages.create and closes the request with no_answer.
        if (((int) $row['channel_id'] === 0) && ws_agent_wake_of($row)) {
            api_fail_validation(ws_agent_answer_refusal_text('no_channel', (int) $row['id']), 'text');
        }

        $place = ws_api_agent_request_place($viewer, $agent, $row);

        if (!$place) {
            api_fail(403, 'forbidden', ws_agent_answer_refusal_text('forbidden', (int) $row['id']));
        }

        $text = ws_agent_answer_text(trim((string) $params['text']), (int) $row['requested_by'], ws_agent_wake_of($row) || ws_agent_request_asker_app($row));

        $options = array('parent_id' => (int) $row['message_id'], 'app_id' => (int) $agent['app_id']);
        $checked = ws_message_send_check($place['writer'], $place['channel'], $text, $options);

        if (!$checked['ok']) {
            api_fail_validation($checked['error'], 'text');
        }

        api_dry_run_stop('answered', 'workspace_agent_request', array('id' => (int) $row['id'], 'channel_id' => (int) $place['channel']['id']) + $concluding);

        $sent = ws_message_send($place['writer'], $place['channel'], $text, $options);

        if (!$sent['ok']) {
            api_fail_validation($sent['error'], 'text');
        }

        $reply_id = (int) $sent['message_id'];

        if ($offer_problem === '') {
            ws_thread_offer_store($reply_id, (int) $place['channel']['id']);
        }

        // The answer wakes the agents it names, the asking one included, or
        // the one whose rule wakes it on answers to its messages.
        if (function_exists('ws_api_assistants_carry_on')) {
            ws_api_assistants_carry_on(ws_agent_after_app_send($place['writer'], $place['channel'], $reply_id, $text));
        }

        ws_api_log(lang(array('string' => '@{var:1} answered a request in #{var:2}', 'vars' => array((string) $agent['handle'], (string) $place['channel']['name']))));
    } else {
        api_dry_run_stop('answered', 'workspace_agent_request', array('id' => (int) $row['id'], 'no_answer' => true) + $concluding);
    }

    db("UPDATE ws_ai_requests SET status = 'answered', reply_message_id = '" . $reply_id . "',
            answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    // The answer takes the progress note's place.
    ws_agent_progress_clear((int) $row['id']);

    // The eye becomes a tick only with a reply; closed without text, the
    // eye stays: the agent saw it.
    if ($reply_id > 0) {
        ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);
        ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '✅', true);
    } else {
        ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', true);
    }

    // The agent's own emoji, beside the automatic eye or tick.
    if ($asking) {
        ws_reaction_set($viewer, $asking, $emoji, (int) $agent['app_id'], true);
    }

    // A flow's agent box waiting on this request goes on (flow_agent_box.php).
    if (function_exists('ws_step_agent_request_closed')) {
        ws_step_agent_request_closed((int) $row['id']);
    }

    // The discussion is concluded last: the answer is refused once it is.
    if ($conclude) {
        $conclude = ws_thread_close_for_agent($row, (string) $agent['handle'], false);
    }

    api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])) + array(
        'conclude' => array('asked' => (bool) $conclude, 'done' => $conclude ? $conclude['ok'] : false, 'error' => $conclude ? $conclude['error'] : ''),
        'offer'    => array('asked' => ($offer_problem !== null), 'done' => ($offer_problem === ''), 'error' => (string) $offer_problem),
    ));
}

/**
 * Why an agent's answer to a run on a task is refused, from what it sent:
 * text, as Claude's answer on a task takes, and nothing that belongs under
 * a message (no_answer, an emoji, concluding a discussion). Pure.
 *
 * @param array $params text, no_answer, emoji, conclude, offer_conclude
 * @return array field, error ('' when nothing is refused)
 */
function ws_agent_task_answer_refusal($params)
{
    foreach (array('emoji', 'conclude', 'offer_conclude') as $field) {
        if (!empty($params[$field])) {
            return array('field' => $field, 'error' => lang('A run on a task is answered with text only: it is written on the task.'));
        }
    }

    return ws_api_claude_answer_input_refusal('task', $params);
}

/**
 * Answers a run on a task handed to the agent (delegation.php): the text is
 * kept on the run and shown on the task, and the person who asked is told,
 * as Claude's answer on a task is. 403 once the task's channel no longer
 * allows the agent.
 *
 * @param array $agent  the raw ws_agents row
 * @param array $viewer
 * @param array $row    ws_ai_requests, open
 * @param array $params
 */
function ws_api_agent_task_answer($agent, $viewer, $row, $params)
{
    $refused = ws_agent_task_answer_refusal($params);

    if ($refused['field'] !== '') {
        api_fail_validation($refused['error'], $refused['field']);
    }

    $task = ws_task((int) $row['task_id']);

    if (!$task || !function_exists('ws_agent_task_problem') || (ws_agent_task_problem(ws_agent_request_value($agent), $task) !== '')) {
        api_fail(403, 'forbidden', ws_agent_answer_refusal_text('forbidden', (int) $row['id']));
    }

    api_dry_run_stop('answered', 'workspace_agent_request', array('id' => (int) $row['id'], 'task_id' => (int) $task['id']));

    if (ws_task_delegation_answer($row, (string) $params['text'])) {
        ws_api_log(lang(array('string' => '@{var:1} answered on task {var:2}', 'vars' => array((string) $agent['handle'], ws_task_number($task['id'])))));
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
    // A message in an earlier version of the conversation is kept as it
    // was: the failure is recorded, nothing is written under it.
    $kept = function_exists('ws_agent_request_in_past') && ws_agent_request_in_past($row);
    $place = $kept ? null : ws_api_agent_request_place($viewer, $agent, $row);

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

    // A run on a task: the reason stays on the run, and the person who
    // asked is told it ended undone, as for Claude's.
    if (((int) ($row['task_id'] ?? 0) > 0) && (mysqli_affected_rows(db::$con) > 0) && function_exists('ws_task_delegation_told')) {
        ws_task_delegation_told($row, 'failed');
    }

    ws_agent_progress_clear((int) $row['id']);

    if (!$kept) {
        ws_agent_react((int) $agent['app_id'], (int) $row['message_id'], '👀', false);
    }

    if (function_exists('ws_step_agent_request_closed')) {
        ws_step_agent_request_closed((int) $row['id']);
    }

    api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])));
}

/**
 * The holding agent's progress note: kept in the request's ai_state, shown
 * on the line under the asking message. No message, wake or inbox line.
 */
function ws_api_agent_request_progress($params)
{
    $agent = ws_api_agent_caller();
    $viewer = ws_api_viewer();
    $row = ws_api_agent_request_or_404($agent, $params['id']);
    $now = time();

    // Written only over the state it was checked against, so two notes sent
    // at once cannot both pass the brake; the loser is checked again.
    for ($try = 0; $try < 2; $try++) {
        $check = ws_agent_progress_check($row, $params['text'] ?? '', $now);

        if (!$check['ok']) {
            if ($check['status'] === 429) {
                api_extra_headers('Retry-After', (string) max(1, (int) $check['retry_after']));
            }

            api_fail($check['status'], $check['code'], $check['error'], $check['field']);
        }

        api_dry_run_stop('noted', 'workspace_agent_request', array('id' => (int) $row['id'], 'text' => $check['text']));

        $before = ($row['ai_state'] === null) ? 'ai_state IS NULL' : "ai_state = '" . e((string) $row['ai_state']) . "'";

        db("UPDATE ws_ai_requests SET ai_state = '" . e(ws_agent_progress_state($row['ai_state'], $check['text'], $now)) . "'
            WHERE id = '" . (int) $row['id'] . "' AND status = 'running' AND " . $before);

        if (mysqli_affected_rows(db::$con) > 0) {
            api_ok(ws_api_claude_request_present($viewer, ws_api_agent_request_or_404($agent, $row['id'])));
        }

        $row = ws_api_agent_request_or_404($agent, $row['id']);
    }

    api_fail(409, 'already_claimed', lang('Another run is working on this request.'));
}

// What the feedback route returns.
function ws_api_agent_feedback_schema()
{
    return array(
        'handle' => 'string',
        'counts' => array(
            'days_7'  => array('right' => 'integer', 'wrong' => 'integer'),
            'days_30' => array('right' => 'integer', 'wrong' => 'integer'),
        ),
        'marks'  => array(array('message_id' => 'integer', 'request_id' => 'integer', 'channel_id' => 'integer', 'verdict' => 'string', 'reason' => 'string', 'marked_at' => 'datetime')),
    );
}

/**
 * The calling agent's marks and their counts. The list leaves out a channel
 * the key no longer reads; the counts are numbers about its own answers.
 */
function ws_api_agent_feedback_get($params)
{
    $agent = ws_api_agent_caller();
    $viewer = ws_api_viewer();
    $value = ws_agent_request_value($agent);
    $now = time();
    $channels = array();

    $marks = array();

    foreach (ws_agent_feedback_rows($value) as $row) {
        $id = (int) $row['channel_id'];

        if (!isset($channels[$id])) {
            $channel = ws_channel($id);
            $channels[$id] = $channel ? ws_can_read_channel($viewer, $channel) : false;
        }

        if ($channels[$id]) {
            $marks[] = array(
                'message_id' => (int) $row['message_id'],
                'request_id' => (int) $row['request_id'],
                'channel_id' => $id,
                'verdict'    => (string) $row['verdict'],
                'reason'     => (string) $row['reason'],
                'marked_at'  => api_time($row['marked_at']),
            );
        }
    }

    api_ok(array(
        'handle' => (string) $agent['handle'],
        'counts' => ws_agent_feedback_tally(ws_agent_feedback_rows($value, $now - 30 * 86400, 100000), $now),
        'marks'  => $marks,
    ));
}
