<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the flows part of the agents' risk tiers.
 *
 * The "Ask an agent" box (kind agent): it writes its prompt, filled with the
 * run's values, in a channel where a registry agent (an application or a
 * model agent, agents.php) is allowed, naming the agent, so the message
 * wakes it under every wake rule (ws_agent_after_send()) within the depth
 * and hourly brakes. The box waits on the request that message made
 * (agent:<request id>) and gives the boxes after it the agent's answer. The
 * agent answers on its own route, with its own tier: what it changes is
 * applied or proposed as in the channel (agent_tiers.php). An answer or a
 * failure settles the box at once (ws_step_agent_request_closed()); the
 * hourly look settles what was missed, and no answer by the box's deadline
 * leaves on failed.
 *
 * A run started by an agent: an application agent may start a run of a flow
 * only in a channel where it is allowed and only when every box of the flow
 * is within the tier it is allowed there (ws_flow_graph_tier()). Reading
 * and messages are tier 1, a reversible record change (a task, a table
 * row, an approval asked) tier 2, and anything that cannot be taken back
 * (an e-mail, a webhook, an MCP tool, another flow or scheduled action
 * started, a change box's deletion or money) tier 3.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The characters of the box's prompt, and of the answer it keeps. */
define('WS_STEP_AGENT_PROMPT_MAX', 4000);
define('WS_STEP_AGENT_ANSWER_MAX', 8000);

/** Hours the box waits for an answer: by default, and at most. */
define('WS_STEP_AGENT_CLOSES_DEFAULT', 24);
define('WS_STEP_AGENT_CLOSES_MAX', 720);

/* ---------------------------------------------------------------------------
   The box
   --------------------------------------------------------------------------- */

/**
 * The settings, as the box keeps them. Pure.
 *
 * @param array $config
 * @return array agent_id, channel_id, prompt, closes_in
 */
function ws_step_agent_normalize($config)
{
    $number = function ($value) {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) ? max(0, (int) $value) : 0;
    };
    $closes_in = $config['closes_in'] ?? '';

    return array(
        'agent_id'   => $number($config['agent_id'] ?? 0),
        'channel_id' => $number($config['channel_id'] ?? 0),
        'prompt'     => ws_flow_lines($config['prompt'] ?? '', WS_STEP_AGENT_PROMPT_MAX),
        'closes_in'  => (is_bool($closes_in) || !is_numeric($closes_in)) ? WS_STEP_AGENT_CLOSES_DEFAULT : max(1, min(WS_STEP_AGENT_CLOSES_MAX, (int) $closes_in)),
    );
}

/**
 * What the box still lacks, in sentences: the agent, the channel it is
 * asked in and the prompt. Pure.
 *
 * @param array  $config ws_step_agent_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_step_agent_problems($config, $name)
{
    $problems = array();

    if ((int) $config['agent_id'] <= 0) {
        $problems[] = lang(array('string' => 'The box {var:1} needs the agent it asks.', 'vars' => $name));
    }

    if ((int) $config['channel_id'] <= 0) {
        $problems[] = lang(array('string' => 'The box {var:1} needs the channel the agent is asked in.', 'vars' => $name));
    }

    if (trim($config['prompt']) === '') {
        $problems[] = lang(array('string' => 'The box {var:1} needs the prompt that says what to ask.', 'vars' => $name));
    }

    return $problems;
}

/**
 * The message that asks the agent: its @handle first, so it wakes the agent
 * whatever its wake rule, then the filled prompt. Pure.
 *
 * @param string $handle
 * @param string $prompt filled
 * @param int    $max    the longest message
 * @return string
 */
function ws_step_agent_message($handle, $prompt, $max)
{
    return mb_substr('@' . (string) $handle . ' ' . trim((string) $prompt), 0, max(1, (int) $max));
}

/**
 * The answer the box gives: the agent's message without the mention of the
 * person it answered, which the answer route puts first. Pure.
 *
 * @param string $body
 * @param int    $requested_by
 * @return string
 */
function ws_step_agent_answer_text($body, $requested_by)
{
    $body = trim((string) $body);
    $mention = '<@user:' . (int) $requested_by . '>';

    if (strpos($body, $mention) === 0) {
        $body = ltrim(substr($body, strlen($mention)));
    }

    return mb_substr($body, 0, WS_STEP_AGENT_ANSWER_MAX);
}

/**
 * The key a box waits on: the request its message made. Pure.
 *
 * @param int $request_id
 * @return string
 */
function ws_step_agent_key($request_id)
{
    return 'agent:' . (int) $request_id;
}

/**
 * The request a wait key names, 0 for another key. Pure.
 *
 * @param string $key
 * @return int
 */
function ws_step_agent_key_request($key)
{
    return preg_match('/^agent:([1-9][0-9]{0,9})$/', (string) $key, $match) ? (int) $match[1] : 0;
}

/**
 * When the box stops waiting: its claim and its hours. Pure.
 *
 * @param array $config ws_step_agent_normalize()
 * @param int   $since  the box's claimed_at
 * @return int
 */
function ws_step_agent_deadline($config, $since)
{
    return (int) $since + (int) $config['closes_in'] * 3600;
}

/**
 * How a closed request settles the box. An answer is done, with its words
 * (none when the agent said no answer was needed); a failure leaves on
 * failed where an arrow leaves on it, else the box fails; a request still
 * open settles nothing (null). Pure.
 *
 * @param array       $request       ws_ai_requests (status, error)
 * @param string|null $answer        the answer's words, null for no message
 * @param int         $reply_id      the answer's message
 * @param bool        $failed_routed an arrow leaves the box on failed
 * @param string      $handle
 * @return array|null status, outcome, said, data
 */
function ws_step_agent_settlement($request, $answer, $reply_id, $failed_routed, $handle)
{
    $status = (string) ($request['status'] ?? '');

    if ($status === 'answered') {
        return array(
            'status'  => 'done',
            'outcome' => 'done',
            'said'    => ($answer === null)
                ? lang(array('string' => '@{var:1} saw nothing to answer.', 'vars' => $handle))
                : lang(array('string' => '@{var:1} answered.', 'vars' => $handle)),
            'data'    => array('answer' => (string) $answer, 'message_id' => ((int) $reply_id > 0) ? (int) $reply_id : null),
        );
    }

    if (in_array($status, array('failed', 'cancelled'), true)) {
        $error = trim((string) ($request['error'] ?? ''));

        return array(
            'status'  => $failed_routed ? 'done' : 'failed',
            'outcome' => 'failed',
            'said'    => trim(lang(array('string' => '@{var:1} could not answer.', 'vars' => $handle)) . ' ' . $error),
            'data'    => array('answer' => null, 'message_id' => null),
        );
    }

    return null;
}

/**
 * An agent the box may ask: an active application or model agent of the
 * registry, null for any other.
 *
 * @param int $agent_id
 * @return array|null
 */
function ws_step_agent_row($agent_id)
{
    if (((int) $agent_id <= 0) || !function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_agents WHERE id = '" . (int) $agent_id . "' AND status = 'active' AND kind IN ('app', 'model')");

    return is_array($row) ? $row : null;
}

/**
 * The agents a box may ask in a channel: the active ones allowed there.
 *
 * @param int $channel_id
 * @return int[] agent ids
 */
function ws_step_agent_channel_ids($channel_id)
{
    return array_map(function ($row) {
        return (int) $row['id'];
    }, function_exists('ws_agent_channel_rows') ? ws_agent_channel_rows((int) $channel_id) : array());
}

/**
 * Checks the box with somebody's rights: as it is published, and again as it
 * runs. The agent is active, it is allowed in the channel, and the person
 * may write there.
 *
 * @param array $viewer
 * @param array $config
 * @return array ok, error, paused, action
 */
function ws_step_agent_check($viewer, $config)
{
    $config = ws_step_agent_normalize($config);
    $problems = ws_step_agent_problems($config, lang('Ask an agent'));
    $fail = function ($error) {
        return array('ok' => false, 'error' => (string) $error, 'paused' => false, 'action' => null);
    };

    if (!empty($problems)) {
        return $fail($problems[0]);
    }

    $agent = ws_step_agent_row($config['agent_id']);

    if (!$agent) {
        return $fail(lang('That agent is not there or is paused.'));
    }

    $channel = ws_channel($config['channel_id']);

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    if (!in_array((int) $agent['id'], ws_step_agent_channel_ids((int) $channel['id']), true)) {
        return $fail(lang(array('string' => '@{var:1} is not allowed in #{var:2}.', 'vars' => array((string) $agent['handle'], (string) $channel['name']))));
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array(
        'type'         => 'agent',
        'handle'       => (string) $agent['handle'],
        'channel_name' => (string) $channel['name'],
    ) + $config);
}

/**
 * What the box does, in words, for a try. Nothing is sent in a try.
 *
 * @param array $action ws_step_agent_check()
 * @return string
 */
function ws_step_agent_text($action)
{
    return lang(array('string' => 'Asks @{var:1} in #{var:2} and waits for its answer.', 'vars' => array((string) ($action['handle'] ?? ''), (string) ($action['channel_name'] ?? ''))));
}

/**
 * A box this request claimed asks its agent: the message goes in the
 * channel in the owner's name, and the box waits on the request it made.
 * A message that made no request (a brake held, the agent was taken out of
 * the channel) settles the box at once.
 *
 * @param array $step    ws_flow_run_steps row, running
 * @param array $owner   the flow's owner
 * @param array $action  ws_step_agent_check(), its prompt filled
 * @param bool  $routed  an arrow leaves the box on failed
 */
function ws_step_agent_ask($step, $owner, $action, $routed)
{
    $agent = ws_step_agent_row($action['agent_id']);
    $channel = ws_channel($action['channel_id']);
    $fail = function ($said) use ($step, $routed) {
        $end = ws_step_settlement(false, $routed);
        ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $said);
    };

    if (!$agent || !$channel) {
        $fail(lang('That agent is not there or is paused.'));
        return;
    }

    $sent = ws_message_send($owner, $channel, ws_step_agent_message($agent['handle'], (string) $action['prompt'], WS_MESSAGE_MAX), array('app_id' => 0));

    if (!$sent['ok']) {
        $fail(lang(array('string' => 'Could not write in #{var:1}: {var:2}', 'vars' => array($channel['name'], $sent['error']))));
        return;
    }

    $request_id = (int) db_value("SELECT id FROM ws_ai_requests
        WHERE message_id = '" . (int) $sent['message_id'] . "' AND agent = '" . e(ws_agent_request_value($agent)) . "'
        ORDER BY id DESC LIMIT 1");

    if ($request_id <= 0) {
        $fail(lang(array('string' => '@{var:1} was not woken; see the channel for why.', 'vars' => (string) $agent['handle'])));
        return;
    }

    $key = ws_step_agent_key($request_id);
    $now = time();
    $config = ws_step_agent_normalize($action);
    $look = min($now + WS_FLOW_RECHECK, ws_step_agent_deadline($config, (int) ($step['claimed_at'] ?? $now)));
    $said = lang(array('string' => '@{var:1} was asked; the box waits for its answer.', 'vars' => (string) $agent['handle']));

    db("UPDATE ws_flow_run_steps SET effect = '" . e($key) . "', status = 'waiting',
            wait_event = '', wait_key = '" . e($key) . "', wake_at = '" . (int) $look . "',
            result = '" . e(ws_flow_result_pack($said, array('message_id' => (int) $sent['message_id']))) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return;
    }

    ws_flow_run_refresh((int) $step['run_id']);
    ws_flow_run_announce_waiting((int) $step['id'], $key);

    // An answer quicker than this request (a model agent on a fast loop).
    ws_step_agent_request_closed($request_id);
}

/**
 * Settles the box waiting on a request once the request is closed: called
 * where an agent answers or fails, and by the box's hourly look.
 *
 * @param int $request_id
 * @return bool whether a box settled
 */
function ws_step_agent_request_closed($request_id)
{
    if (((int) $request_id <= 0) || !function_exists('ws_flows_ready') || !ws_flows_ready()) {
        return false;
    }

    $step = db_item("SELECT * FROM ws_flow_run_steps
        WHERE status = 'waiting' AND wait_event = '' AND wait_key = '" . e(ws_step_agent_key($request_id)) . "' LIMIT 1");

    return is_array($step) ? ws_step_agent_settle($step) : false;
}

/**
 * Settles one waiting box from its request: a run that ended lets it go, a
 * closed request gives its answer or its failure.
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return bool whether the box settled
 */
function ws_step_agent_settle($step)
{
    $run = ws_flow_run($step['run_id']);

    if (!$run) {
        return false;
    }

    if (!ws_flow_run_open($run['status'])) {
        return ws_flow_step_settle($step, 'waiting', 'skipped', '', lang('Not reached in this run.'));
    }

    $request = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . ws_step_agent_key_request($step['wait_key']) . "'");

    if (!is_array($request)) {
        return ws_flow_step_settle($step, 'waiting', 'failed', 'failed', lang('The request to the agent is gone.'));
    }

    $graph = ws_flow_run_graph($run);
    $node = ws_flow_graph_node($graph, $step['node_id']);
    $agent = ws_step_agent_row((int) ($node['config']['agent_id'] ?? 0));
    $handle = $agent ? (string) $agent['handle'] : lang('The agent');
    $reply_id = (int) ($request['reply_message_id'] ?? 0);
    $reply = ($reply_id > 0) ? ws_message($reply_id) : null;
    $answer = $reply ? ws_step_agent_answer_text((string) $reply['body'], (int) $request['requested_by']) : null;
    $end = ws_step_agent_settlement($request, $answer, $reply_id, ws_flow_outcome_routed($graph, $step['node_id'], 'failed'), $handle);

    return $end ? ws_flow_step_settle($step, 'waiting', $end['status'], $end['outcome'], $end['said'], $end['data']) : false;
}

/**
 * The hourly look at a waiting box: a closed request settles it; past its
 * deadline it leaves on failed (done where an arrow leaves on failed);
 * else its next look, no later than the deadline.
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return bool whether the box settled
 */
function ws_step_agent_recheck($step)
{
    if (ws_step_agent_settle($step)) {
        return true;
    }

    $run = ws_flow_run($step['run_id']);

    if (!$run || !ws_flow_run_open($run['status'])) {
        return false;
    }

    $graph = ws_flow_run_graph($run);
    $node = ws_flow_graph_node($graph, $step['node_id']);
    $config = ws_step_agent_normalize($node['config'] ?? array());
    $deadline = ws_step_agent_deadline($config, (int) $step['claimed_at']);
    $now = time();

    if ($now >= $deadline) {
        $end = ws_step_settlement(false, ws_flow_outcome_routed($graph, $step['node_id'], 'failed'));

        return ws_flow_step_settle($step, 'waiting', $end['status'], $end['outcome'],
            lang(array('string' => 'The agent gave no answer within {var:1} hours.', 'vars' => $config['closes_in'])),
            array('answer' => null, 'message_id' => null));
    }

    db("UPDATE ws_flow_run_steps SET wake_at = '" . min($now + WS_FLOW_RECHECK, $deadline) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'waiting'");

    return false;
}

/**
 * The agents the box's form offers a flow's owner: the active ones allowed
 * in a channel the owner may write in, each with those channels' ids.
 *
 * @param array $viewer
 * @return array[] id, handle, name, channel_ids
 */
function ws_step_agent_form_list($viewer)
{
    if (!function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT a.id, a.handle, a.name, ca.channel_id
        FROM ws_channel_agents ca
        INNER JOIN ws_agents a ON a.id = ca.agent_id
        WHERE ca.allowed = 1 AND a.status = 'active' AND a.kind IN ('app', 'model')
        ORDER BY a.handle, ca.channel_id");
    $out = array();
    $postable = array();

    foreach ($rows as $row) {
        $channel_id = (int) $row['channel_id'];

        if (!isset($postable[$channel_id])) {
            $channel = ws_channel($channel_id);
            $postable[$channel_id] = $channel && ws_can_post_channel($viewer, $channel);
        }

        if (!$postable[$channel_id]) {
            continue;
        }

        $id = (int) $row['id'];

        if (!isset($out[$id])) {
            $out[$id] = array('id' => $id, 'handle' => (string) $row['handle'], 'name' => (string) $row['name'], 'channel_ids' => array());
        }

        $out[$id]['channel_ids'][] = $channel_id;
    }

    return array_values($out);
}

/* ---------------------------------------------------------------------------
   Runs started by an agent
   --------------------------------------------------------------------------- */

/**
 * The tier of every kind of box but a change box, whose change says it
 * (ws_flow_box_tier()). A kind not listed is tier 3, so nothing new runs in
 * an agent's name before it is classed. Starts are 1 (any kind that starts
 * a flow). Pure.
 *
 * @return array kind => tier
 */
function ws_flow_box_tiers()
{
    $tiers = array();

    foreach (array('manual', 'end', 'condition', 'delay', 'for_each', 'for_each_end', 'constants', 'value',
        'post', 'notify', 'report', 'checkin', 'web_check', 'row_get', 'rows_get', 'record_get', 'ai', 'agent') as $kind) {
        $tiers[$kind] = WS_TIER_READ;
    }

    foreach (array('task', 'task_open', 'row_add', 'row_set', 'approval', 'lead') as $kind) {
        $tiers[$kind] = WS_TIER_REVERSIBLE;
    }

    foreach (array('email', 'task_digest', 'webhook', 'mcp', 'start_action', 'start_flow') as $kind) {
        $tiers[$kind] = WS_TIER_IRREVERSIBLE;
    }

    return $tiers;
}

/**
 * The tier of one box: a start is 1, a change box its change's
 * (ws_change_tier(): a deletion, money or an order's status is 3), any
 * other its kind's. Pure but for the catalogues.
 *
 * @param array      $node  kind, config
 * @param array|null $types ws_change_types(), read when null
 * @return int
 */
function ws_flow_box_tier($node, $types = null)
{
    $kind = (string) ($node['kind'] ?? '');
    $info = ws_flow_kinds()[$kind] ?? null;

    if ($info && !empty($info['trigger'])) {
        return WS_TIER_READ;
    }

    if ($kind === 'change') {
        $config = is_array($node['config'] ?? null) ? $node['config'] : array();
        $fields = is_array($config['fields'] ?? null) ? array_keys($config['fields']) : array();

        return ws_change_tier((string) ($config['record'] ?? ''), (($config['action'] ?? '') === 'delete') ? 'delete' : 'update', $fields, $types);
    }

    return ws_flow_box_tiers()[$kind] ?? WS_TIER_IRREVERSIBLE;
}

/**
 * The tier of a flow: its highest box. Pure but for the catalogues.
 *
 * @param array      $graph nodes
 * @param array|null $types
 * @return int
 */
function ws_flow_graph_tier($graph, $types = null)
{
    $tier = WS_TIER_READ;

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        $tier = max($tier, ws_flow_box_tier((array) $node, $types));
    }

    return $tier;
}

/**
 * Why an agent may not start a run, '' when it may: it must be allowed in
 * the channel the run is in, and the flow's tier within the one it is
 * allowed there. Pure.
 *
 * @param int    $allowed    the agent's tier in the channel, 0 with no allowed row
 * @param int    $needed     ws_flow_graph_tier()
 * @param string $handle
 * @return string
 */
function ws_flow_agent_start_refusal($allowed, $needed, $handle)
{
    if ((int) $allowed < WS_TIER_READ) {
        return lang(array('string' => '@{var:1} may start a run only in a channel where it is allowed: send channel_id, or give the flow a home channel.', 'vars' => $handle));
    }

    if (!ws_agent_tier_allows($allowed, $needed)) {
        return lang(array('string' => 'This flow does what needs tier {var:1}; @{var:2} is allowed tier {var:3} in that channel. A channel manager may raise it, or a person starts the run.', 'vars' => array((int) $needed, $handle, (int) $allowed)));
    }

    return '';
}

/**
 * The calling application's agent, when it is an active application agent
 * of the registry, else null.
 *
 * @param int $app_id
 * @return array|null
 */
function ws_flow_start_agent($app_id)
{
    if (((int) $app_id <= 0) || !function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_agents WHERE kind = 'app' AND status = 'active' AND app_id = '" . (int) $app_id . "' ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The tier an application's agents are allowed in a channel: the highest
 * allowed row, 0 with none.
 *
 * @param int $channel_id
 * @param int $app_id
 * @return int
 */
function ws_flow_start_agent_tier($channel_id, $app_id)
{
    if (((int) $channel_id <= 0) || ((int) $app_id <= 0)) {
        return 0;
    }

    return (int) db_value("SELECT MAX(ca.tier) FROM ws_channel_agents ca
        JOIN ws_agents a ON a.id = ca.agent_id
        WHERE ca.channel_id = '" . (int) $channel_id . "' AND ca.allowed = 1
            AND a.kind = 'app' AND a.status = 'active' AND a.app_id = '" . (int) $app_id . "'");
}
