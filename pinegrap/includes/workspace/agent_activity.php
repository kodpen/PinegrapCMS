<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what the registry's app and model agents did, on the Agents
 * card: their requests of the last 7 and 30 days by state, the next wake,
 * the push door's failures, and their newest waiting and failed requests
 * with Cancel, Ask again and Knock now. The tokens a request's answers
 * used are counted here too (ws_ai_usage_add()), for Pinegrap AI and the
 * model assistants, and shown as sums of 7 and 30 days; a model
 * assistant's monthly token limit is checked here before each of its steps
 * (ws_agent_token_refusal()).
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
 * The tokens one answer used, from its usage: prompt_tokens and
 * completion_tokens in the OpenAI shape, input_tokens and output_tokens
 * where a provider names them so. A provider that sends no usage counts
 * nothing. Pure.
 *
 * @param mixed $json the decoded answer
 * @return array in, out (int, never below 0)
 */
function ws_ai_usage_tokens($json)
{
    $usage = (is_array($json) && isset($json['usage']) && is_array($json['usage'])) ? $json['usage'] : array();
    $read = function ($names) use ($usage) {
        foreach ($names as $name) {
            if (isset($usage[$name]) && is_numeric($usage[$name])) {
                return max(0, (int) $usage[$name]);
            }
        }

        return 0;
    };

    return array(
        'in'  => $read(array('prompt_tokens', 'input_tokens')),
        'out' => $read(array('completion_tokens', 'output_tokens')),
    );
}

/**
 * The tokens of the last 30 days, and of the last 7 within them, from rows
 * already narrowed to one agent. Pure.
 *
 * @param array[] $rows week (1: within 7 days), tokens_in, tokens_out
 * @return array d7 => in, out; d30 => in, out
 */
function ws_ai_tokens_periods($rows)
{
    $out = array('d7' => array('in' => 0, 'out' => 0), 'd30' => array('in' => 0, 'out' => 0));

    foreach ((array) $rows as $row) {
        $in = max(0, (int) ($row['tokens_in'] ?? 0));
        $used = max(0, (int) ($row['tokens_out'] ?? 0));

        $out['d30']['in'] += $in;
        $out['d30']['out'] += $used;

        if ((int) ($row['week'] ?? 0) === 1) {
            $out['d7']['in'] += $in;
            $out['d7']['out'] += $used;
        }
    }

    return $out;
}

/**
 * The tokens of every registry agent from grouped rows, both request key
 * shapes summed. Pure.
 *
 * @param array[] $rows agent, week, tokens_in, tokens_out
 * @return array agent id => ws_ai_tokens_periods()
 */
function ws_agent_activity_tokens($rows)
{
    $grouped = array();

    foreach ((array) $rows as $row) {
        $id = ws_agent_activity_agent_id((string) ($row['agent'] ?? ''));

        if ($id > 0) {
            $grouped[$id][] = $row;
        }
    }

    return array_map('ws_ai_tokens_periods', $grouped);
}

/**
 * One period's tokens in words. Pure but for the site's number separators.
 *
 * @param array $period in, out
 * @return string
 */
function ws_ai_tokens_text($period)
{
    $format = function ($n) {
        return function_exists('pg_format_number') ? pg_format_number((int) $n, 0) : number_format((int) $n);
    };

    return lang(array('string' => '{var:1} in, {var:2} out', 'vars' => array($format($period['in'] ?? 0), $format($period['out'] ?? 0))));
}

/**
 * The tokens of 7 and 30 days in one line. Pure but for the site's number
 * separators.
 *
 * @param array $tokens ws_ai_tokens_periods()
 * @return string
 */
function ws_ai_tokens_line($tokens)
{
    return lang(array('string' => 'Tokens, last 7 days: {var:1}; last 30 days: {var:2}', 'vars' => array(ws_ai_tokens_text($tokens['d7'] ?? array()), ws_ai_tokens_text($tokens['d30'] ?? array()))));
}

/**
 * A monthly token limit as typed on a model assistant's form: empty is 0
 * (none), otherwise a whole number up to the column's 4,294,967,295. Pure.
 *
 * @param mixed $raw
 * @return array ok, value (int), error
 */
function ws_agent_token_limit_check($raw)
{
    $text = str_replace(array(' ', '.', ','), '', trim((string) $raw));

    if ($text === '') {
        return array('ok' => true, 'value' => 0, 'error' => '');
    }

    if ((preg_match('/^[0-9]{1,10}$/', $text) !== 1) || ((float) $text > 4294967295)) {
        return array('ok' => false, 'value' => 0, 'error' => lang('Give the monthly token limit as a whole number, or 0 for none.'));
    }

    return array('ok' => true, 'value' => (int) $text, 'error' => '');
}

/**
 * Whether a month's tokens have reached the limit; 0 is no limit. Pure.
 *
 * @param int $used
 * @param int $limit
 * @return bool
 */
function ws_agent_token_limit_reached($used, $limit)
{
    return ((int) $limit > 0) && ((int) $used >= (int) $limit);
}

/**
 * The first second of the calendar month a time falls in, in the site's
 * time zone. Pure.
 *
 * @param int $now
 * @return int
 */
function ws_agent_month_start($now)
{
    return (int) mktime(0, 0, 0, (int) date('n', (int) $now), 1, (int) date('Y', (int) $now));
}

/**
 * The sentence a request gets when its assistant has used its month's
 * tokens: names the assistant and the limit.
 *
 * @param string $name
 * @param int    $limit
 * @return string
 */
function ws_agent_token_limit_sentence($name, $limit)
{
    $format = function_exists('pg_format_number') ? pg_format_number((int) $limit, 0) : number_format((int) $limit);

    return lang(array('string' => '{var:1} has used its monthly limit of {var:2} tokens; it answers again next month or when the limit is raised.', 'vars' => array((string) $name, $format)));
}

/**
 * This month's tokens of a limit, in words. Pure but for the site's number
 * separators.
 *
 * @param int $used
 * @param int $limit
 * @return string
 */
function ws_agent_token_limit_text($used, $limit)
{
    $format = function ($n) {
        return function_exists('pg_format_number') ? pg_format_number((int) $n, 0) : number_format((int) $n);
    };

    return lang(array('string' => 'This month: {var:1} of {var:2} tokens', 'vars' => array($format($used), $format($limit))));
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
    $tokens = ws_ai_tokens_ready() ? ', SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out' : '';
    $rows = (array) db_items("SELECT agent, status, (created_at > '" . ($now - 7 * 86400) . "') AS week, COUNT(*) AS n" . $tokens . "
        FROM ws_ai_requests
        WHERE agent IN (" . implode(', ', $keys) . ") AND created_at > '" . ($now - 30 * 86400) . "'
        GROUP BY agent, status, week");
    $counts = ws_agent_activity_counts($rows);

    foreach (ws_agent_activity_tokens($rows) as $id => $periods) {
        if (isset($counts[$id])) {
            $counts[$id]['tokens'] = $periods;
        }
    }

    return $counts;
}

/**
 * Whether the schema step that added the token columns has run.
 *
 * @return bool
 */
function ws_ai_tokens_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_ai_requests', 'tokens_in') && pg_schema_has('ws_ai_requests', 'tokens_out');
    }

    return $ready;
}

/**
 * The request whose answers are counted now: set by the shared loop around
 * each ws_ai_step(), 0 outside it, so a call that answers no request (a
 * translation, a bulk text, the connection try) counts nothing.
 *
 * @param int|false $set a request id to hold, 0 to let go, false to read
 * @return int
 */
function ws_ai_usage_request($set = false)
{
    static $request_id = 0;

    if ($set !== false) {
        $request_id = max(0, (int) $set);
    }

    return $request_id;
}

/**
 * Adds one answer's tokens to the request being answered.
 *
 * @param array $response ws_ai_http()
 * @return void
 */
function ws_ai_usage_add($response)
{
    $request_id = ws_ai_usage_request();

    if (($request_id <= 0) || !is_array($response) || !ws_ai_tokens_ready()) {
        return;
    }

    $tokens = ws_ai_usage_tokens($response['json'] ?? null);

    if (($tokens['in'] + $tokens['out']) <= 0) {
        return;
    }

    db_q("UPDATE ws_ai_requests SET tokens_in = tokens_in + ?, tokens_out = tokens_out + ? WHERE id = ?", array($tokens['in'], $tokens['out'], $request_id));
}

/**
 * Whether the schema step that added ws_agents.token_limit has run.
 *
 * @return bool
 */
function ws_agent_token_limit_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_ai_tokens_ready() && pg_schema_has('ws_agents', 'token_limit');
    }

    return $ready;
}

/**
 * A model assistant's monthly token limit, 0 for none.
 *
 * @param int $agent_id
 * @return int
 */
function ws_agent_token_limit($agent_id)
{
    if (((int) $agent_id <= 0) || !ws_agent_token_limit_ready()) {
        return 0;
    }

    return (int) db_value("SELECT token_limit FROM ws_agents WHERE id = '" . (int) $agent_id . "' AND kind = 'model'");
}

/**
 * Stores a model assistant's monthly token limit.
 *
 * @param int $agent_id
 * @param int $limit
 * @return void
 */
function ws_agent_token_limit_save($agent_id, $limit)
{
    if (((int) $agent_id <= 0) || !ws_agent_token_limit_ready()) {
        return;
    }

    db_q("UPDATE ws_agents SET token_limit = ? WHERE id = ? AND kind = 'model'", array(max(0, (int) $limit), (int) $agent_id));
}

/**
 * A model assistant's tokens in the calendar month so far, over its
 * requests made since the month began.
 *
 * @param int $agent_id
 * @return int
 */
function ws_agent_month_tokens($agent_id)
{
    if (((int) $agent_id <= 0) || !ws_ai_tokens_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COALESCE(SUM(tokens_in + tokens_out), 0) FROM ws_ai_requests
        WHERE agent = 'model:" . (int) $agent_id . "' AND created_at >= '" . ws_agent_month_start(time()) . "'");
}

/**
 * Why a model assistant may not run now for its tokens: the sentence of
 * ws_agent_token_limit_sentence() once the month's tokens reach its limit,
 * '' otherwise.
 *
 * @param array $agent the raw row: id, name, token_limit
 * @return string
 */
function ws_agent_token_refusal($agent)
{
    if (!is_array($agent) || !ws_agent_token_limit_ready()) {
        return '';
    }

    $limit = (int) ($agent['token_limit'] ?? 0);

    if (($limit <= 0) || !ws_agent_token_limit_reached(ws_agent_month_tokens((int) $agent['id']), $limit)) {
        return '';
    }

    return ws_agent_token_limit_sentence((string) $agent['name'], $limit);
}

/**
 * Pinegrap AI's own tokens over the last 30 days.
 *
 * @return array ws_ai_tokens_periods()
 */
function ws_ai_read_tokens()
{
    if (!ws_ai_schema_ready() || !ws_ai_tokens_ready()) {
        return ws_ai_tokens_periods(array());
    }

    $now = time();

    return ws_ai_tokens_periods((array) db_items("SELECT (created_at > '" . ($now - 7 * 86400) . "') AS week, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out
        FROM ws_ai_requests
        WHERE agent = 'ai' AND created_at > '" . ($now - 30 * 86400) . "'
        GROUP BY week"));
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

    // Claude and application agents run elsewhere: no figure of theirs.
    if (($agent['kind'] === 'model') && ws_ai_tokens_ready()) {
        $tokens = $mine['tokens'] ?? ws_ai_tokens_periods(array());
        $facts[] = ws_ai_tokens_line($tokens);

        $limit = ws_agent_token_limit((int) $agent['id']);

        if ($limit > 0) {
            $facts[] = ws_agent_token_limit_text(ws_agent_month_tokens((int) $agent['id']), $limit);
        }
    }

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
