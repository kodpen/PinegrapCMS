<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the boxes that start a flow on their own: at a time, when
 * something happens on the site, when somebody joins a channel. Each keeps
 * its rule as a scheduled action keeps its "when", and publishing the flow
 * writes it into a hidden scheduled action of the flow (the anchor:
 * ws_scheduled_actions.flow_id), which ws_scheduled_execute() runs by
 * starting a run of the flow instead of a step (its action reads
 * {type: flow} and is no step ws_scheduled_do() knows).
 * The scheduled actions engine then keeps the time, hears the events and
 * the newcomers, takes its lock and runs it as the flow's owner; the anchor
 * is left out of every list of scheduled actions, its card, its history and
 * its report (ws_scheduled_action(), ws_scheduled_anchors_out()).
 * A scheduled action converted into a flow is an anchor too, marked
 * converted (ws_scheduled_actions.anchor): it stays the action it was, in
 * the lists, on its card and in its history; by hand and by a chain it
 * starts its flow as well, and the run's outcome is written back to the
 * action's run with its news (ws_flow_anchor_run_ended()).
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
 * The kinds of box that start a flow through its anchor, and the rule of a
 * scheduled action each is.
 *
 * @return array kind => label, rule
 */
function ws_flow_anchor_kinds()
{
    return array(
        'schedule' => array('label' => lang('At a time'), 'rule' => 'at'),
        'event'    => array('label' => lang('When something happens'), 'rule' => 'event'),
        'join'     => array('label' => lang('When somebody joins a channel'), 'rule' => 'join'),
    );
}

/**
 * The values what started a run gives its boxes ({{trigger.<field>}}), as
 * the start box is set up, in the shape of a box's outputs (key, type,
 * label): an event its name, the id of its record and what its payload
 * says of it (ws_flow_event_outputs()); a join the newcomer and the channel
 * joined. Started by hand or at a time: none. Pure.
 *
 * @param string $kind   the start box's kind
 * @param array  $config ws_flow_anchor_normalize()
 * @return array[] key, type, label
 */
function ws_flow_trigger_outputs($kind, $config = array())
{
    $value = function ($key, $type, $label) {
        return array('key' => $key, 'type' => $type, 'label' => $label);
    };

    if ($kind === 'join') {
        return array(
            $value('newcomer_id', 'number', lang('The person who joined')),
            $value('newcomer_name', 'text', lang('The name of the person who joined')),
            $value('channel_id', 'number', lang('The channel joined')),
            $value('channel_name', 'text', lang('The name of the channel joined')),
        );
    }

    if ($kind !== 'event') {
        return array();
    }

    $out = array($value('event', 'text', lang('What happened')), $value('record_id', 'number', lang('Record')));
    $keys = array('event', 'record_id');

    foreach ((ws_flow_event_outputs()[(string) ($config['event'] ?? '')] ?? array()) as $output) {
        if (!in_array($output['key'], $keys, true)) {
            $keys[] = $output['key'];
            $out[] = $output;
        }
    }

    return $out;
}

/**
 * What each event's payload gives {{trigger.<field>}} beside its name and
 * record: the single values the code that announces it always sends
 * (pg_announce(), ws_event()); a list (a task's assignees, a delivery's
 * parcels) is never one. Pure.
 *
 * @return array event => list of key, type, label
 */
function ws_flow_event_outputs()
{
    $value = function ($key, $type, $label) {
        return array('key' => $key, 'type' => $type, 'label' => $label);
    };
    $order = $value('order_number', 'text', lang('Order number'));
    $task = array($value('title', 'text', lang('Title')), $value('status', 'text', lang('Status')));
    $channel = $value('channel_id', 'number', lang('Channel'));
    $actor = $value('actor_id', 'number', lang('The person who did it'));

    return array(
        'order.created'          => array($order),
        'order.status_changed'   => array($order, $value('status', 'text', lang('Status')), $value('previous', 'text', lang('Previous status'))),
        'order.shipped'          => array($order),
        'order.delivered'        => array($order, $value('delivery_date', 'text', lang('Delivery date'))),
        'order.cancelled'        => array($order),
        'order.return.requested' => array($order),
        'stock.low'              => array($value('name', 'text', lang('Product')), $value('quantity', 'number', lang('Quantity')), $value('threshold', 'number', lang('Threshold'))),
        'customer.created'       => array($value('email', 'text', lang('E-mail'))),
        'customer.updated'       => array($value('email', 'text', lang('E-mail'))),
        'form.submitted'         => array(),
        'workspace.task.completed'      => array_merge($task, array($value('priority', 'text', lang('Priority')), $channel, $actor)),
        'workspace.task.status_changed' => array_merge($task, array($value('previous_status', 'text', lang('Previous status')), $value('priority', 'text', lang('Priority')), $channel, $actor)),
        'workspace.task.overdue'        => array_merge($task, array($channel, $value('due_date', 'text', lang('Due date')))),
        'workspace.approval.decided'    => array_merge($task, array($channel, $value('message_id', 'number', lang('Message')), $actor)),
        'workspace.guest.message'       => array($value('guest', 'text', lang('Guest')), $value('text', 'text', lang('What the guest wrote')), $channel, $value('message_id', 'number', lang('Message'))),
        'workspace.mail.received'       => array($value('customer', 'text', lang('Customer')), $value('customer_email', 'text', lang('E-mail')), $value('subject', 'text', lang('Subject')),
            $value('conversation_id', 'number', lang('Conversation')), $channel, $value('message_id', 'number', lang('Message'))),
    );
}

/**
 * The settings of a box that starts a flow, as its rule keeps them; unknown
 * keys are dropped. Pure: ws_flow_anchor_check() asks the rest.
 *
 * schedule: date (YYYY-MM-DD), time (HH:MM), repeat, every, days (1-7),
 *           until ('' or YYYY-MM-DD)
 * event:    event, page_id (a form), status ('' for any), channel_id
 * join:     channel_id (0 for any public channel)
 *
 * @param string $kind
 * @param mixed  $config
 * @return array
 */
function ws_flow_anchor_normalize($kind, $config)
{
    $config = is_array($config) ? $config : array();
    $number = function ($value) {
        return (is_bool($value) || !is_numeric($value)) ? 0 : max(0, (int) $value);
    };

    switch ($kind) {
        case 'schedule':
            $days = array();

            foreach ((is_array($config['days'] ?? null) ? $config['days'] : array()) as $day) {
                if (($number($day) >= 1) && ($number($day) <= 7)) {
                    $days[$number($day)] = $number($day);
                }
            }

            sort($days);

            return array(
                'date'   => ws_flow_token($config['date'] ?? '', 10),
                'time'   => ws_flow_token($config['time'] ?? '', 5),
                'repeat' => ws_flow_token($config['repeat'] ?? 'none', 16),
                'every'  => max(1, min(720, $number($config['every'] ?? 1))),
                'days'   => array_values($days),
                'until'  => ws_flow_token($config['until'] ?? '', 10),
            );

        case 'event':
            return array(
                'event'      => ws_flow_token($config['event'] ?? '', 64),
                'page_id'    => $number($config['page_id'] ?? 0),
                'status'     => ws_flow_token($config['status'] ?? '', 32),
                'channel_id' => $number($config['channel_id'] ?? 0),
            );

        case 'join':
            return array('channel_id' => $number($config['channel_id'] ?? 0));
    }

    return array();
}

/**
 * What a box that starts a flow still lacks, in sentences. Pure.
 *
 * @param string $kind
 * @param array  $config ws_flow_anchor_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_flow_anchor_problems($kind, $config, $name)
{
    if (($kind === 'schedule') && (($config['date'] === '') || ($config['time'] === ''))) {
        return array(lang(array('string' => 'The box {var:1} needs the day and the time it starts the flow.', 'vars' => $name)));
    }

    if (($kind === 'event') && ($config['event'] === '')) {
        return array(lang(array('string' => 'The box {var:1} needs what happens.', 'vars' => $name)));
    }

    return array();
}

/**
 * A box's settings as the rule of a scheduled action. Pure.
 *
 * @param string $kind
 * @param array  $config ws_flow_anchor_normalize()
 * @return array
 */
function ws_flow_anchor_rule($kind, $config)
{
    $rule = array('type' => (string) (ws_flow_anchor_kinds()[$kind]['rule'] ?? ''));

    foreach ((array) $config as $key => $value) {
        if (($key === 'days') && empty($value)) {
            continue;
        }

        $rule[$key] = $value;
    }

    return $rule;
}

/**
 * Checks a box that starts a flow with somebody's rights, as a scheduled
 * action's "when" is checked: the owner as they publish. A time that has
 * passed and does not come again starts nothing.
 *
 * @param array  $viewer
 * @param string $kind
 * @param array  $config ws_flow_anchor_normalize()
 * @return array ok, error, rule, next_run_at
 */
function ws_flow_anchor_check($viewer, $kind, $config)
{
    if (!isset(ws_flow_anchor_kinds()[$kind])) {
        return array('ok' => false, 'error' => lang('This kind of box cannot start a flow.'), 'rule' => null, 'next_run_at' => 0);
    }

    $checked = ws_scheduled_rule_input($viewer, ws_flow_anchor_rule($kind, $config));

    if (!$checked['ok']) {
        return $checked + array('next_run_at' => 0);
    }

    $next = 0;

    if ($checked['rule']['type'] === 'at') {
        $next = ws_scheduled_next_run($checked['rule'], time() - 1);

        if ($next <= 0) {
            return array('ok' => false, 'error' => lang('That time has passed, and the box does not start the flow again.'), 'rule' => null, 'next_run_at' => 0);
        }
    }

    return $checked + array('next_run_at' => $next);
}

/**
 * The next times a box's rule starts the flow after a moment, at most
 * $count of them: none for an event or a join, which have no time of their
 * own. Pure.
 *
 * @param array $rule  ws_flow_anchor_check()'s rule
 * @param int   $after
 * @param int   $count
 * @return int[]
 */
function ws_flow_anchor_next_times($rule, $after, $count = 3)
{
    $times = array();

    if (((string) ($rule['type'] ?? '') !== 'at')) {
        return $times;
    }

    for ($i = 0; $i < (int) $count; $i++) {
        $after = ws_scheduled_next_run($rule, $after);

        if ($after <= 0) {
            break;
        }

        $times[] = $after;
    }

    return $times;
}

/**
 * The anchor of a flow, decoded; null for none.
 *
 * @param int $flow_id
 * @return array|null
 */
function ws_flow_anchor($flow_id)
{
    if (!ws_flows_ready() || ((int) $flow_id <= 0)) {
        return null;
    }

    $id = (int) db_value("SELECT id FROM ws_scheduled_actions WHERE flow_id = '" . (int) $flow_id . "' ORDER BY id LIMIT 1");

    return ($id > 0) ? ws_scheduled($id) : null;
}

/**
 * Does the flow carry out a converted scheduled action? Read once a request
 * for every flow: lists ask it of each flow they show.
 *
 * @param int  $flow_id
 * @param bool $forget read again (after a conversion)
 * @return bool
 */
function ws_flow_converted($flow_id, $forget = false)
{
    static $ids = null;

    if ($forget) {
        $ids = null;
    }

    if (((int) $flow_id <= 0) || !function_exists('ws_scheduled_anchor_ready') || !ws_scheduled_anchor_ready()) {
        return false;
    }

    if ($ids === null) {
        $ids = array();

        foreach ((array) db_items("SELECT flow_id FROM ws_scheduled_actions WHERE anchor = 'converted' AND flow_id > 0") as $row) {
            $ids[(int) $row['flow_id']] = true;
        }
    }

    return isset($ids[(int) $flow_id]);
}

/**
 * Writes a flow's anchor from the box that starts its live version, as the
 * flow is published: made, or changed in place (its id, lock and history
 * kept), active with the rule's next time. A flow started by hand has no
 * anchor; one it had is cancelled, so nothing starts it on its own.
 *
 * @param array $viewer the publisher, the flow's owner
 * @param array $flow
 * @param array $graph  the version published
 * @return array ok, error
 */
function ws_flow_anchor_sync($viewer, $flow, $graph)
{
    $trigger = ws_flow_trigger_node($graph);
    $anchor = ws_flow_anchor($flow['id']);

    // A converted action started by hand waits, as before, for its card's
    // Run now or another action's chain.
    if ($anchor && ws_scheduled_anchor_converted($anchor) && $trigger && ((string) $trigger['kind'] === 'manual')) {
        db("UPDATE ws_scheduled_actions SET name = '" . e(mb_substr((string) $flow['name'], 0, 160)) . "', created_by = '" . (int) $flow['owner_id'] . "',
                rules = '" . e(json_encode(array(array('type' => 'trigger')))) . "', status = 'active', next_run_at = 0,
                updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'
            WHERE id = '" . (int) $anchor['id'] . "'");

        return array('ok' => true, 'error' => '');
    }

    if (!$trigger || !isset(ws_flow_anchor_kinds()[$trigger['kind']])) {
        if ($anchor) {
            ws_flow_anchor_set_status($flow['id'], 'cancelled');
        }

        return array('ok' => true, 'error' => '');
    }

    $check = ws_flow_anchor_check($viewer, $trigger['kind'], $trigger['config']);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error']);
    }

    $now = time();
    $rules = "'" . e(json_encode(array($check['rule']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "'";
    $action = "'" . e(json_encode(array('type' => 'flow', 'flow_id' => (int) $flow['id']))) . "'";
    $name = mb_substr((string) $flow['name'], 0, 160);

    if ($anchor) {
        db("UPDATE ws_scheduled_actions SET name = '" . e($name) . "', created_by = '" . (int) $flow['owner_id'] . "', rules = " . $rules . ",
                action = " . $action . ", status = 'active', next_run_at = '" . (int) $check['next_run_at'] . "',
                updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . $now . "'
            WHERE id = '" . (int) $anchor['id'] . "'");

        return array('ok' => true, 'error' => '');
    }

    $follow = ws_scheduled_chains_ready() ? array('follow' => "'[]'") : array('then_action' => "''");

    if (ws_scheduled_anchor_ready()) {
        $follow['anchor'] = "'hidden'";
    }

    db("INSERT INTO ws_scheduled_actions (name, kind, flow_id, channel_id, note_id, created_by, rules, action, " . implode(', ', array_keys($follow)) . ",
            status, next_run_at, updated_by, created_at, updated_at)
        VALUES ('" . e($name) . "', 'action', '" . (int) $flow['id'] . "', 0, 0, '" . (int) $flow['owner_id'] . "', " . $rules . ", " . $action . ",
            " . implode(', ', $follow) . ", 'active', '" . (int) $check['next_run_at'] . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    return array('ok' => true, 'error' => '');
}

/**
 * Pauses or cancels a flow's anchor as the flow is paused or deleted; a
 * paused flow starts again only when it is published, which writes its
 * anchor anew.
 *
 * @param int    $flow_id
 * @param string $status paused | cancelled
 */
function ws_flow_anchor_set_status($flow_id, $status)
{
    if (!ws_flows_ready() || ((int) $flow_id <= 0) || !in_array($status, array('paused', 'cancelled'), true)) {
        return;
    }

    db("UPDATE ws_scheduled_actions SET status = '" . e($status) . "', updated_at = '" . time() . "'
        WHERE flow_id = '" . (int) $flow_id . "' AND status <> 'cancelled'");
}

/**
 * The context a run started by its anchor keeps: what started it (the kind
 * of box), the anchor's run, and what the start carried: the event and its
 * record, or the newcomer and the channel they joined. An event's payload
 * over 8 KB is left out. Pure.
 *
 * @param array $flow
 * @param array $trigger the box that starts the flow
 * @param array $fill    what ws_scheduled_execute() hands its step
 * @return array
 */
function ws_flow_anchor_context($flow, $trigger, $fill)
{
    $context = array(
        'trigger'    => (string) $trigger['kind'],
        'node'       => (string) $trigger['id'],
        'channel_id' => max(0, (int) ($flow['channel_id'] ?? 0)),
        'flow_name'  => (string) ($flow['name'] ?? ''),
        'sched_run'  => (int) ($fill['run_id'] ?? 0),
    );

    // A converted action's run: its steps write as the action, and the end
    // of the run is written back to the action's run.
    if (ws_scheduled_anchor_converted((array) ($fill['row'] ?? array()))) {
        $context['anchor_id'] = (int) $fill['row']['id'];
    }

    if ((string) ($fill['source'] ?? '') !== '') {
        $context['source'] = (string) $fill['source'];
        $context['source_result'] = (string) ($fill['source_result'] ?? '');
    }

    if ((string) ($fill['event'] ?? '') !== '') {
        $context['event'] = (string) $fill['event'];
        $payload = json_encode((array) ($fill['event_payload'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (is_string($payload) && (strlen($payload) <= 8192)) {
            $context['event_payload'] = (array) ($fill['event_payload'] ?? array());
        }
    }

    if ((int) ($fill['newcomer'] ?? 0) > 0) {
        $context['newcomer'] = (int) $fill['newcomer'];
        $context['join_channel_id'] = (int) ($fill['join_channel_id'] ?? 0);
    }

    return $context;
}

/**
 * Starts a run of a flow from its anchor's step, as its owner: the flow
 * live, started by the box its anchor was written from, the owner on the
 * staff, the open runs and the starts of the hour within their limits. The
 * anchor's run is the start key (sched:<run id>), so a step carried out
 * twice starts one run. A converted action also starts a flow whose start
 * box is by hand: its card's Run now, or another action's chain.
 *
 * @param array $viewer the anchor's creator, the flow's owner
 * @param array $row    the anchor, decoded
 * @param array $fill   what ws_scheduled_execute() hands its step, and by_hand
 * @return array ok, text, message_id, run_id (the run started now, 0 for none)
 */
function ws_flow_anchor_start($viewer, $row, $fill)
{
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'message_id' => 0, 'run_id' => 0);
    };

    $flow = ws_flow((int) ($row['flow_id'] ?? 0));

    if (!is_array($flow) || ((string) $flow['status'] !== 'live') || ((int) $flow['owner_id'] !== (int) $viewer['id'])) {
        return $fail(lang('Only a published flow that is live can be run.'));
    }

    $version = ws_flow_version($flow['version_id']);
    $graph = is_array($version) ? ws_flow_graph_of($version) : null;
    $trigger = $graph ? ws_flow_trigger_node($graph) : null;

    $converted = ws_scheduled_anchor_converted($row);

    if (!$trigger || !(isset(ws_flow_anchor_kinds()[$trigger['kind']]) || ($converted && ((string) $trigger['kind'] === 'manual')))) {
        return $fail(lang('This flow is not started on its own.'));
    }

    $flow_id = (int) $flow['id'];
    $start_key = 'sched:' . (int) ($fill['run_id'] ?? 0);

    if ((int) db_value("SELECT id FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND start_key = '" . e($start_key) . "'") > 0) {
        return array('ok' => true, 'text' => lang('the flow was started already'), 'message_id' => 0, 'run_id' => 0);
    }

    $open = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND status IN ('running', 'waiting')");
    $recent = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND started_at > '" . (time() - 3600) . "'");

    switch (ws_flow_start_refusal($open, $recent)) {
        case 'open_runs':
            return $fail(lang(array('string' => 'This flow has {var:1} runs open already; it starts again once some of them end.', 'vars' => WS_FLOW_MAX_OPEN_RUNS)));

        case 'hourly':
            return $fail(lang(array('string' => 'This flow was started {var:1} times in the last hour; try again later.', 'vars' => WS_FLOW_STARTS_HOURLY)));
    }

    $context = json_encode(ws_flow_anchor_context($flow, $trigger, $fill), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $now = time();

    // Not db(): the duplicate start key of a step carried out twice is an
    // answer here.
    $written = db_q("INSERT INTO ws_flow_runs (flow_id, version_id, status, started_by, actor_id, start_key,
            subject_type, subject_id, context, depth, boxes_run, started_at, updated_at, finished_at)
        SELECT ?, ?, 'running', ?, ?, ?, '', 0, ?, ?, 0, ?, ?, 0
        FROM ws_flows WHERE id = ? AND status = 'live'",
        array($flow_id, (int) $version['id'], (int) $flow['owner_id'], (int) $flow['owner_id'], (string) $start_key, (string) $context,
            (int) ($fill['depth'] ?? 0), $now, $now, $flow_id));

    if (!$written) {
        return ((int) mysqli_errno(db::$con) === 1062)
            ? array('ok' => true, 'text' => lang('the flow was started already'), 'message_id' => 0, 'run_id' => 0)
            : $fail(lang('The run could not be started.'));
    }

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return $fail(lang('Only a published flow that is live can be run.'));
    }

    $run_id = (int) mysqli_insert_id(db::$con);

    db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, outcome, attempt, ready_at, claimed_at, finished_at, result)
        VALUES ('" . $run_id . "', '" . e((string) $trigger['id']) . "', 0, 'done', 'next', 1, '" . $now . "', '" . $now . "', '" . $now . "',
            '" . e(ws_flow_result_pack(ws_flow_anchor_start_said($trigger['kind'], $fill))) . "')");

    ws_flow_run_refresh($run_id);

    return array('ok' => true, 'text' => lang(array('string' => 'flow “{var:1}” started, run {var:2}', 'vars' => array($flow['name'], $run_id))), 'message_id' => 0, 'run_id' => $run_id);
}

/**
 * What the start box of a run its anchor started says: another action's
 * chain names that action, a run by hand says so, the rest the kind of box.
 * Pure.
 *
 * @param string $kind the start box's kind
 * @param array  $fill what ws_scheduled_execute() hands its step, and by_hand
 * @return string
 */
function ws_flow_anchor_start_said($kind, $fill)
{
    if ((string) ($fill['source'] ?? '') !== '') {
        return lang(array('string' => 'Started by {var:1}.', 'vars' => '“' . $fill['source'] . '”'));
    }

    if (!empty($fill['by_hand']) || !isset(ws_flow_anchor_kinds()[$kind])) {
        return lang('Started by hand.');
    }

    return lang(array('string' => 'Started on its own: {var:1}.', 'vars' => ws_flow_anchor_kinds()[$kind]['label']));
}

/**
 * How a run's end is written back to the converted action that started it.
 * Pure.
 *
 * @param string $state done | failed | cancelled
 * @return string done | failed | skipped, '' for a run still open
 */
function ws_flow_anchor_run_status($state)
{
    $map = array('done' => 'done', 'failed' => 'failed', 'cancelled' => 'skipped');

    return $map[(string) $state] ?? '';
}

/**
 * Why a run of a converted action counts as skipped: the sentence of a
 * condition box (one of the action's conditions) that left on no, '' when
 * every condition held. Pure.
 *
 * @param array|null $graph the run's graph
 * @param array[]    $steps ws_flow_run_steps()
 * @return string
 */
function ws_flow_anchor_skip_reason($graph, $steps)
{
    $conditions = array();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if ((string) ($node['kind'] ?? '') === 'condition') {
            $conditions[(string) $node['id']] = true;
        }
    }

    foreach ((array) $steps as $step) {
        if (isset($conditions[(string) $step['node_id']]) && ((string) $step['status'] === 'done') && ((string) $step['outcome'] === 'no')) {
            $text = ws_flow_result_text((string) ($step['result'] ?? ''));

            return ($text !== '') ? $text : lang('A condition did not hold.');
        }
    }

    return '';
}

/**
 * Writes the end of a run a converted action started back to that action's
 * run (its context's anchor_id and sched_run): the outcome, a line naming
 * the run, the moment; a single action whose run failed reads failed. Then
 * the action's news, as a scheduled action's run gives it: the line under
 * its card, its creator's inbox when it newly failed, the Site Log
 * (ws_scheduled_report()). Called once, by the request whose UPDATE ended
 * the run; a hidden anchor's run writes nothing back.
 *
 * @param array  $run   ws_flow_runs row
 * @param string $state done | failed | cancelled
 */
function ws_flow_anchor_run_ended($run, $state)
{
    $context = ws_flow_run_context($run);
    $anchor_id = (int) ($context['anchor_id'] ?? 0);
    $sched_run = (int) ($context['sched_run'] ?? 0);
    $status = ws_flow_anchor_run_status($state);

    if (($anchor_id <= 0) || ($sched_run <= 0) || ($status === '') || !ws_scheduled_anchor_ready()) {
        return;
    }

    $row = ws_scheduled($anchor_id);
    $kept = db_item("SELECT id, detail, by_hand FROM ws_scheduled_runs WHERE id = '" . $sched_run . "' AND action_id = '" . $anchor_id . "'");

    if (!$row || !ws_scheduled_anchor_converted($row) || !is_array($kept)) {
        return;
    }

    // A condition that did not hold skipped the action, as its rules did
    // before it was converted, with the reason.
    $skipped = ($status === 'done') ? ws_flow_anchor_skip_reason(ws_flow_run_graph($run), ws_flow_run_steps($run['id'])) : '';

    if ($skipped !== '') {
        $status = 'skipped';
    }

    $said = array(
        'done'      => 'flow run {var:1} finished',
        'failed'    => 'flow run {var:1} failed',
        'cancelled' => 'flow run {var:1} was cancelled',
    );
    $steps = json_decode((string) $kept['detail'], true);
    $steps = is_array($steps) ? $steps : array();
    $steps[] = array('step' => 'flow', 'ok' => $status === 'done', 'text' => lang(array('string' => $said[$state], 'vars' => (int) $run['id'])));

    if ($skipped !== '') {
        $steps[] = array('step' => 'rules', 'ok' => false, 'text' => $skipped);
    }

    db("UPDATE ws_scheduled_runs SET status = '" . e($status) . "', finished_at = '" . time() . "',
            detail = '" . e(json_encode($steps, JSON_UNESCAPED_UNICODE)) . "'
        WHERE id = '" . $sched_run . "'");

    if ($status === 'failed') {
        db("UPDATE ws_scheduled_actions SET status = 'failed' WHERE id = '" . $anchor_id . "' AND status = 'done'");
    }

    ws_scheduled_report($row, $status, $steps, ws_scheduled_run_news($row, $sched_run, $status, !empty($kept['by_hand'])));
}
