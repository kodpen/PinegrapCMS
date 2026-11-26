<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the rules of a channel's board: what happens when a task gets
 * somewhere. A rule is a flow of the channel that starts on one of the task
 * events (a task completed, moved to a status, gone overdue). Four ready
 * rules are offered from templates; every other flow of the channel that
 * starts on a task event is listed beside them, whoever made it.
 *
 * Nothing is stored here: a ready rule is a flow whose start box carries the
 * rule's id (board_<key>) and waits for its event in this channel. Turning a
 * rule on makes its flow and publishes it, or resumes it where it was
 * paused; turning it off pauses it, never deletes it. Publishing and pausing
 * go through the flows' own checks, so a rule runs with its owner's rights
 * as any flow does.
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
 * The start box id of a ready rule's flow is this prefix and the rule's key.
 */
define('WS_BOARD_RULE_PREFIX', 'board_');

/**
 * How many last runs the Automations drawer shows at its foot.
 */
define('WS_BOARD_RULE_RUNS', 5);

/**
 * The task events a board rule starts on. Pure.
 *
 * @return string[]
 */
function ws_board_rule_events()
{
    return array('workspace.task.completed', 'workspace.task.status_changed', 'workspace.task.overdue');
}

/**
 * The ready rules, in the order the drawer lists them. Pure.
 *
 * Each: icon, tone (an icon tile's tone), when and then (the row's two
 * lines), name (the flow's name), event, status (the status a task moves to,
 * for workspace.task.status_changed), asks ('' none, 'person' the people
 * who approve, 'text' the next task's title) and ask_label.
 *
 * @return array key => rule
 */
function ws_board_rule_templates()
{
    return array(
        'done_post' => array(
            'icon'      => 'bi-chat-left-text',
            'tone'      => 'blue',
            'when'      => lang('When a task is done'),
            'then'      => lang('Write it in the channel'),
            'name'      => lang('Board: write finished tasks in the channel'),
            'event'     => 'workspace.task.completed',
            'status'    => '',
            'asks'      => '',
            'ask_label' => '',
        ),
        'overdue_remind' => array(
            'icon'      => 'bi-alarm',
            'tone'      => 'rose',
            'when'      => lang('When a task is overdue'),
            'then'      => lang('Remind its people in the channel'),
            'name'      => lang('Board: remind overdue tasks'),
            'event'     => 'workspace.task.overdue',
            'status'    => '',
            'asks'      => '',
            'ask_label' => '',
        ),
        'waiting_approval' => array(
            'icon'      => 'bi-patch-check',
            'tone'      => 'amber',
            'when'      => ws_board_rule_when(array('event' => 'workspace.task.status_changed', 'status' => 'waiting'), ws_task_statuses()),
            'then'      => lang('Ask for approval'),
            'name'      => lang('Board: ask for approval of waiting tasks'),
            'event'     => 'workspace.task.status_changed',
            'status'    => 'waiting',
            'asks'      => 'person',
            'ask_label' => lang('Who approves?'),
        ),
        'done_next_task' => array(
            'icon'      => 'bi-arrow-right-circle',
            'tone'      => 'green',
            'when'      => lang('When a task is done'),
            'then'      => lang('Make the next task'),
            'name'      => lang('Board: make the next task'),
            'event'     => 'workspace.task.completed',
            'status'    => '',
            'asks'      => 'text',
            'ask_label' => lang('Title of the next task'),
        ),
    );
}

/**
 * The answer a ready rule asks for when it is first turned on, checked and
 * cleaned. Pure.
 *
 * @param string $key    a ws_board_rule_templates() key
 * @param mixed  $answer person: a list of user ids (1 to 20); text: the
 *                       next task's title (1 to 200 characters)
 * @return array ok, error, value (the clean answer)
 */
function ws_board_rule_answer_check($key, $answer)
{
    $rule = ws_board_rule_templates()[(string) $key] ?? null;

    if ($rule === null) {
        return array('ok' => false, 'error' => lang('There is no such rule.'), 'value' => null);
    }

    if ($rule['asks'] === 'person') {
        $ids = array();

        foreach ((is_array($answer) ? $answer : array()) as $id) {
            if (is_numeric($id) && !is_bool($id) && ((int) $id > 0)) {
                $ids[(int) $id] = (int) $id;
            }
        }

        if (empty($ids)) {
            return array('ok' => false, 'error' => lang('Choose who approves.'), 'value' => null);
        }

        if (count($ids) > 20) {
            return array('ok' => false, 'error' => lang('Choose at most 20 people.'), 'value' => null);
        }

        return array('ok' => true, 'error' => '', 'value' => array_values($ids));
    }

    if ($rule['asks'] === 'text') {
        $title = trim(preg_replace('/\s+/u', ' ', is_scalar($answer) ? (string) $answer : '') ?? '');

        if ($title === '') {
            return array('ok' => false, 'error' => lang('Write the title of the next task.'), 'value' => null);
        }

        if (mb_strlen($title, 'UTF-8') > 200) {
            return array('ok' => false, 'error' => lang('The title is too long: at most 200 characters.'), 'value' => null);
        }

        return array('ok' => true, 'error' => '', 'value' => $title);
    }

    return array('ok' => true, 'error' => '', 'value' => null);
}

/**
 * A ready rule's flow graph for a channel: its event start, narrowed to the
 * channel, and the one box that does the work. Pure.
 *
 * @param string $key        a ws_board_rule_templates() key
 * @param int    $channel_id
 * @param mixed  $answer     ws_board_rule_answer_check()'s value
 * @return array|null ws_flow_graph_normalize() shape; null for an unknown key
 */
function ws_board_rule_graph($key, $channel_id, $answer = null)
{
    $rule = ws_board_rule_templates()[(string) $key] ?? null;

    if ($rule === null) {
        return null;
    }

    $channel_id = (int) $channel_id;

    switch ($key) {
        case 'done_post':
            $step = array('kind' => 'post', 'config' => array('channel_id' => $channel_id, 'body' => lang('Done: {{record}}')));
            break;

        case 'overdue_remind':
            $step = array('kind' => 'post', 'config' => array('channel_id' => $channel_id, 'body' => lang('Overdue: {{record}}')));
            break;

        case 'waiting_approval':
            $step = array('kind' => 'approval', 'config' => array('channel_id' => $channel_id, 'title' => lang('Approve: {{record_title}}'),
                'text' => lang('{{record}} is waiting for approval.'), 'approvers' => is_array($answer) ? $answer : array(), 'rule' => 'any', 'closes_in' => ''));
            break;

        default:
            $step = array('kind' => 'task_open', 'config' => array('title' => is_string($answer) ? $answer : '', 'description' => lang('Follows {{record}}'),
                'assignees' => array(), 'channel_id' => $channel_id, 'due_in' => '', 'priority' => 'normal'));
    }

    $start = WS_BOARD_RULE_PREFIX . $key;

    return ws_flow_graph_normalize(array(
        'v'     => 1,
        'nodes' => array(
            array('id' => $start, 'kind' => 'event', 'label' => '', 'x' => 0, 'y' => 0,
                'config' => array('event' => $rule['event'], 'page_id' => 0, 'status' => $rule['status'], 'channel_id' => $channel_id)),
            array('id' => 'step', 'kind' => $step['kind'], 'label' => '', 'x' => 256, 'y' => 0, 'config' => $step['config']),
        ),
        'edges' => array(array('from' => $start, 'to' => 'step', 'on' => 'next')),
        'notes' => array(),
    ));
}

/**
 * Where a flow starts, when it starts on a task event of this channel: an
 * event start waiting for a task event narrowed to the channel, or narrowed
 * to no channel in a flow whose home is the channel. Pure.
 *
 * @param array $graph      ws_flow_graph_normalize()
 * @param int   $channel_id
 * @param int   $home_id    the flow's home channel
 * @return array|null id (the start box), event, status
 */
function ws_board_rule_start($graph, $channel_id, $home_id)
{
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if ((string) ($node['kind'] ?? '') !== 'event') {
            continue;
        }

        $config = is_array($node['config'] ?? null) ? $node['config'] : array();
        $event = (string) ($config['event'] ?? '');
        $narrowed = (int) ($config['channel_id'] ?? 0);

        if (!in_array($event, ws_board_rule_events(), true)) {
            return null;
        }

        if (($narrowed !== (int) $channel_id) && (($narrowed !== 0) || ((int) $home_id !== (int) $channel_id))) {
            return null;
        }

        return array(
            'id'     => (string) $node['id'],
            'event'  => $event,
            'status' => ($event === 'workspace.task.status_changed') ? (string) ($config['status'] ?? '') : '',
        );
    }

    return null;
}

/**
 * The ready rule a flow was made from: its start box's id names it and the
 * start still waits for that rule's event. Pure.
 *
 * @param array $start ws_board_rule_start()
 * @return string a ws_board_rule_templates() key, or ''
 */
function ws_board_rule_key_of($start)
{
    $id = (string) ($start['id'] ?? '');

    if (strpos($id, WS_BOARD_RULE_PREFIX) !== 0) {
        return '';
    }

    $key = substr($id, strlen(WS_BOARD_RULE_PREFIX));
    $rule = ws_board_rule_templates()[$key] ?? null;

    return (($rule !== null) && ($rule['event'] === $start['event']) && ($rule['status'] === $start['status'])) ? $key : '';
}

/**
 * The column of the board a rule belongs to, for the column's rule line: a
 * completed task's "Done", a status a task moves to; none for an overdue
 * task or a move to any status. Pure.
 *
 * @param array $start ws_board_rule_start()
 * @return string a ws_task_statuses() key, or ''
 */
function ws_board_rule_column($start)
{
    if ((string) ($start['event'] ?? '') === 'workspace.task.completed') {
        return 'done';
    }

    return ((string) ($start['event'] ?? '') === 'workspace.task.status_changed') ? (string) ($start['status'] ?? '') : '';
}

/**
 * The "when" line of a flow that starts on a task event. Pure.
 *
 * @param array $start    ws_board_rule_start()
 * @param array $statuses ws_task_statuses()
 * @return string
 */
function ws_board_rule_when($start, $statuses)
{
    switch ((string) ($start['event'] ?? '')) {
        case 'workspace.task.completed':
            return lang('When a task is done');

        case 'workspace.task.overdue':
            return lang('When a task is overdue');
    }

    $status = (string) ($start['status'] ?? '');

    return ($status === '')
        ? lang('When a task changes status')
        : lang(array('string' => 'When a task comes to “{var:1}”', 'vars' => (string) ($statuses[$status] ?? $status)));
}

/**
 * The rules of a channel's board, from the flows that may be its rules.
 * Pure.
 *
 * Each ready rule comes once: made (its flow: the live one first, then the
 * latest changed) or not made yet. Every other flow that starts on a task
 * event of the channel comes after them, as a rule of its own.
 *
 * @param int   $channel_id
 * @param array $flows    rows: id, owner_id, channel_id (home), name, status
 *                        (draft, live, paused), version_id, updated_at,
 *                        access (ws_flow_access()), graph
 *                        (ws_flow_graph_normalize(): the live version's, or
 *                        the draft of a flow never published)
 * @param array $statuses ws_task_statuses()
 * @return array ready (rules), flows (rules), on (how many are on)
 */
function ws_board_rules($channel_id, $flows, $statuses)
{
    $templates = ws_board_rule_templates();
    $made = array();
    $others = array();

    usort($flows, function ($a, $b) {
        $live = (((string) $b['status'] === 'live') ? 1 : 0) - (((string) $a['status'] === 'live') ? 1 : 0);

        if ($live !== 0) {
            return $live;
        }

        $time = (int) $b['updated_at'] - (int) $a['updated_at'];

        return ($time !== 0) ? $time : ((int) $b['id'] - (int) $a['id']);
    });

    foreach ($flows as $flow) {
        if (!in_array((string) $flow['status'], array('draft', 'live', 'paused'), true)) {
            continue;
        }

        $start = ws_board_rule_start((array) ($flow['graph'] ?? array()), $channel_id, (int) $flow['channel_id']);

        if ($start === null) {
            continue;
        }

        $key = ws_board_rule_key_of($start);
        $row = array(
            'flow_id' => (int) $flow['id'],
            'name'    => (string) $flow['name'],
            'status'  => (string) $flow['status'],
            'on'      => ((string) $flow['status'] === 'live'),
            'owner'   => (int) $flow['owner_id'],
            'edit'    => ((string) ($flow['access'] ?? '') === 'edit'),
            'when'    => ws_board_rule_when($start, $statuses),
            'column'  => ws_board_rule_column($start),
        );

        if (($key !== '') && !isset($made[$key])) {
            $made[$key] = $row;
        } else {
            $others[] = $row;
        }
    }

    $ready = array();
    $on = 0;

    foreach ($templates as $key => $rule) {
        $flow = $made[$key] ?? null;
        $ready[] = array(
            'key'       => $key,
            'icon'      => $rule['icon'],
            'tone'      => $rule['tone'],
            'when'      => $rule['when'],
            'then'      => $rule['then'],
            'column'    => ws_board_rule_column(array('event' => $rule['event'], 'status' => $rule['status'])),
            // Asked only while no flow holds the answer.
            'asks'      => ($flow === null) ? $rule['asks'] : '',
            'ask_label' => ($flow === null) ? $rule['ask_label'] : '',
            'flow'      => $flow,
            'on'        => ($flow !== null) && $flow['on'],
        );
        $on += (($flow !== null) && $flow['on']) ? 1 : 0;
    }

    foreach ($others as $row) {
        $on += $row['on'] ? 1 : 0;
    }

    return array('ready' => $ready, 'flows' => $others, 'on' => $on);
}

/**
 * May the person see and set the rules of this channel's board? Flows run
 * on the site and the person builds them as staff (a member's own flow has
 * no home channel), and the channel has a board they read.
 *
 * @param array      $viewer
 * @param array|null $channel
 * @return bool
 */
function ws_board_rules_gate($viewer, $channel)
{
    return function_exists('pg_flows_enabled') && pg_flows_enabled() && ws_flows_ready()
        && is_array($channel) && ws_channel_board_available($channel)
        && in_array((string) $channel['kind'], array('public', 'private'), true)
        && ws_flow_builder($viewer) && (ws_flow_builder_kind($viewer) === 'staff')
        && ws_can_read_channel($viewer, $channel);
}

/**
 * The flows that may be rules of the channel's board, as the person reaches
 * them: their own and the live ones at home in the channel, with the graph
 * they run on (a flow never published: its draft).
 *
 * @param array $viewer
 * @param array $channel
 * @return array[] ws_board_rules() rows
 */
function ws_board_rule_flows($viewer, $channel)
{
    $rows = (array) db_items("SELECT f.id, f.owner_id, f.channel_id, f.name, f.status, f.draft, f.draft_rev, f.version_id, f.updated_at, v.graph AS live_graph
        FROM ws_flows f
        LEFT JOIN ws_flow_versions v ON v.id = f.version_id
        WHERE f.status IN ('draft', 'live', 'paused')
            AND (f.channel_id = '" . (int) $channel['id'] . "' OR f.owner_id = '" . (int) $viewer['id'] . "')
        ORDER BY f.updated_at DESC, f.id DESC
        LIMIT 500");

    $out = array();

    foreach ($rows as $row) {
        $access = ws_flow_access($viewer, $row);

        if ($access === '') {
            continue;
        }

        $published = ((int) $row['version_id'] > 0) && ((string) $row['live_graph'] !== '');
        $row['graph'] = ws_flow_graph_normalize($published ? (string) $row['live_graph'] : (string) $row['draft']);
        $row['access'] = $access;
        unset($row['draft'], $row['live_graph']);
        $out[] = $row;
    }

    return $out;
}

/**
 * The rules of a channel's board for the person, with the columns' rule
 * lines; with $runs (the Automations drawer) also how often each rule ran
 * this week and the last runs.
 *
 * @param array $viewer
 * @param array $channel
 * @param bool  $runs
 * @return array ok, error, ready, flows, on, lines, and with $runs runs
 */
function ws_board_rules_for($viewer, $channel, $runs = false)
{
    if (!ws_board_rules_gate($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot set the automations of this board.'));
    }

    $flows = ws_board_rule_flows($viewer, $channel);
    $rules = ws_board_rules((int) $channel['id'], $flows, ws_task_statuses());
    $rules['lines'] = ws_board_rule_lines($rules);

    if ($runs) {
        $rules = ws_board_rules_ran($rules, ws_board_rule_week_counts(ws_board_rule_flow_ids($rules), strtotime(ws_board_rule_week_start(date('Y-m-d')) . ' 00:00:00')));
        $rules['runs'] = ws_board_rule_last_runs($viewer, $channel, $rules, $flows);
    }

    return array('ok' => true, 'error' => '') + $rules;
}

/**
 * Turns a rule of the channel's board on or off.
 *
 * On: a ready rule not made yet is made (its answer checked) and published;
 * one made and paused is resumed on the version it was paused at; one never
 * published is published. Another flow of the channel is resumed. Off: the
 * rule's flow is paused, never deleted. A refusal of the flows' own checks
 * comes back as it is.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data key (a ready rule) or flow_id, on (bool), answer (for a
 *                    ready rule not made yet)
 * @return array ok, error, status, field, flow_id
 */
function ws_board_rule_set($viewer, $channel, $data)
{
    $refuse = function ($error, $status = 422, $field = '') {
        return array('ok' => false, 'error' => $error, 'status' => $status, 'field' => $field, 'flow_id' => 0);
    };

    if (!ws_board_rules_gate($viewer, $channel)) {
        return $refuse(lang('You cannot set the automations of this board.'), 403);
    }

    $rules = ws_board_rules((int) $channel['id'], ws_board_rule_flows($viewer, $channel), ws_task_statuses());
    $key = (string) ($data['key'] ?? '');
    $on = !empty($data['on']);
    $found = null;

    if ($key !== '') {
        foreach ($rules['ready'] as $rule) {
            if ($rule['key'] === $key) {
                $found = $rule;
            }
        }

        if ($found === null) {
            return $refuse(lang('There is no such rule.'), 404, 'key');
        }

        $row = $found['flow'];
    } else {
        $row = null;

        foreach (array_merge($rules['flows'], array_filter(array_column($rules['ready'], 'flow'))) as $flow) {
            if ($flow['flow_id'] === (int) ($data['flow_id'] ?? 0)) {
                $row = $flow;
            }
        }

        if ($row === null) {
            return $refuse(lang('There is no such rule.'), 404, 'flow_id');
        }
    }

    // A ready rule not made yet: off is already so; on makes its flow.
    if ($row === null) {
        if (!$on) {
            return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'flow_id' => 0);
        }

        $answer = ws_board_rule_answer_check($key, $data['answer'] ?? null);

        if (!$answer['ok']) {
            return $refuse($answer['error'], 422, 'answer');
        }

        $saved = ws_flow_save($viewer, array(
            'name'       => ws_board_rule_templates()[$key]['name'],
            'channel_id' => (int) $channel['id'],
            'graph'      => ws_board_rule_graph($key, (int) $channel['id'], $answer['value']),
        ));

        if (!$saved['ok']) {
            return $refuse($saved['error'], (int) $saved['status'], (string) $saved['field']);
        }

        $row = array('flow_id' => (int) $saved['flow_id'], 'status' => 'draft', 'on' => false, 'edit' => true);
    }

    $flow = ws_flow((int) $row['flow_id']);

    if (!$flow || ($row['on'] === $on)) {
        return array('ok' => (bool) $flow, 'error' => $flow ? '' : lang('There is no such rule.'), 'status' => $flow ? 200 : 404, 'field' => '', 'flow_id' => (int) $row['flow_id']);
    }

    if (!$row['edit']) {
        return $refuse(lang('Only the owner of a flow can change it.'), 403);
    }

    if (!$on) {
        $result = ws_flow_pause($viewer, $flow);
    } elseif (((string) $flow['status'] === 'paused') && ((int) $flow['version_id'] > 0)) {
        $result = ws_flow_resume($viewer, $flow);
    } elseif (($key !== '') && ((string) $flow['status'] === 'draft')) {
        $result = ws_flow_publish($viewer, $flow);
    } else {
        // Another flow never published is published on its canvas.
        return $refuse(lang('Open this flow in Flows to publish it.'), 409);
    }

    if (!$result['ok']) {
        return $refuse($result['error'], (int) $result['status'], (string) $result['field']);
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'flow_id' => (int) $flow['id']);
}

/**
 * The quiet line a rule that is on draws under its column's head: what
 * happens to a task that comes there. A ready rule says it in its own
 * words, another flow by its name. Pure.
 *
 * @param string $key  a ws_board_rule_templates() key, '' for another flow
 * @param string $name the flow's name
 * @return string
 */
function ws_board_rule_line($key, $name)
{
    switch ((string) $key) {
        case 'done_post':
            return lang('A task that comes here: it is written in the channel');

        case 'waiting_approval':
            return lang('A task that comes here: its approval is asked');

        case 'done_next_task':
            return lang('A task that comes here: the next task is made');
    }

    return lang(array('string' => 'A task that comes here: “{var:1}” runs', 'vars' => (string) $name));
}

/**
 * The rule lines of the board's columns: for each status column, the lines
 * of the rules that are on and are tied to it (an overdue task's rule has
 * no column). Pure.
 *
 * @param array $rules ws_board_rules()
 * @return array status => string[]
 */
function ws_board_rule_lines($rules)
{
    $lines = array();

    foreach ((array) ($rules['ready'] ?? array()) as $rule) {
        if (!empty($rule['on']) && ((string) $rule['column'] !== '')) {
            $lines[(string) $rule['column']][] = ws_board_rule_line((string) $rule['key'], '');
        }
    }

    foreach ((array) ($rules['flows'] ?? array()) as $flow) {
        if (!empty($flow['on']) && ((string) $flow['column'] !== '')) {
            $lines[(string) $flow['column']][] = ws_board_rule_line('', (string) $flow['name']);
        }
    }

    return $lines;
}

/**
 * The Monday a day's week starts on. Pure.
 *
 * @param string $today Y-m-d
 * @return string Y-m-d
 */
function ws_board_rule_week_start($today)
{
    $noon = strtotime($today . ' 12:00:00');

    return date('Y-m-d', strtotime('-' . ((int) date('N', $noon) - 1) . ' days', $noon));
}

/**
 * The flows the rules are, made ready rules and the other flows. Pure.
 *
 * @param array $rules ws_board_rules()
 * @return int[]
 */
function ws_board_rule_flow_ids($rules)
{
    $ids = array();

    foreach ((array) ($rules['ready'] ?? array()) as $rule) {
        if (is_array($rule['flow'] ?? null)) {
            $ids[] = (int) $rule['flow']['flow_id'];
        }
    }

    foreach ((array) ($rules['flows'] ?? array()) as $flow) {
        $ids[] = (int) $flow['flow_id'];
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * How often each rule ran this week, on its row: ran on a made ready rule
 * and on another flow; 0 for a ready rule not made yet. Pure.
 *
 * @param array $rules  ws_board_rules()
 * @param array $counts flow id => runs started this week
 * @return array the rules with ran
 */
function ws_board_rules_ran($rules, $counts)
{
    foreach ((array) ($rules['ready'] ?? array()) as $index => $rule) {
        $rules['ready'][$index]['ran'] = is_array($rule['flow'] ?? null) ? (int) ($counts[(int) $rule['flow']['flow_id']] ?? 0) : 0;
    }

    foreach ((array) ($rules['flows'] ?? array()) as $index => $flow) {
        $rules['flows'][$index]['ran'] = (int) ($counts[(int) $flow['flow_id']] ?? 0);
    }

    return $rules;
}

/**
 * The runs each flow started since a moment.
 *
 * @param int[] $flow_ids
 * @param int   $since
 * @return array flow id => count
 */
function ws_board_rule_week_counts($flow_ids, $since)
{
    $flow_ids = array_values(array_filter(array_map('intval', (array) $flow_ids)));
    $out = array();

    if (empty($flow_ids)) {
        return $out;
    }

    foreach ((array) db_items("SELECT flow_id, COUNT(*) AS n FROM ws_flow_runs
        WHERE flow_id IN (" . implode(',', $flow_ids) . ") AND started_at >= '" . (int) $since . "'
        GROUP BY flow_id") as $row) {
        $out[(int) $row['flow_id']] = (int) $row['n'];
    }

    return $out;
}

/**
 * The lines of the drawer's last runs, newest first. A run names its task
 * only when the task is one of this channel's (its title read here); what
 * was done is the run's result, else what its status says. Pure.
 *
 * @param array[] $runs    ws_flow_runs rows, newest first
 * @param array   $results run id => the result's sentence
 * @param array   $titles  task id => title, the channel's tasks only
 * @param array   $names   flow id => the flow's name
 * @param int     $limit
 * @return array[] id, flow_id, flow, at, task, text, status
 */
function ws_board_rule_run_lines($runs, $results, $titles, $names, $limit = WS_BOARD_RULE_RUNS)
{
    $lines = array();

    foreach ((array) $runs as $run) {
        if (count($lines) >= (int) $limit) {
            break;
        }

        $id = (int) $run['id'];
        $status = (string) $run['status'];
        $task_id = ((string) ($run['subject_type'] ?? '') === 'task') ? (int) ($run['subject_id'] ?? 0) : 0;
        $text = trim((string) ($results[$id] ?? ''));

        if ($text === '') {
            $text = ws_flow_run_statuses()[$status] ?? $status;
        }

        $lines[] = array(
            'id'      => $id,
            'flow_id' => (int) $run['flow_id'],
            'flow'    => (string) ($names[(int) $run['flow_id']] ?? ''),
            'at'      => (int) $run['started_at'],
            'task'    => ($task_id > 0) ? (string) ($titles[$task_id] ?? '') : '',
            'text'    => $text,
            'status'  => $status,
        );
    }

    return $lines;
}

/**
 * The last runs of the board's rules as the person reaches them
 * (ws_flow_run_access()), from the flows' own record.
 *
 * @param array   $viewer
 * @param array   $channel
 * @param array   $rules ws_board_rules()
 * @param array[] $flows ws_board_rule_flows()
 * @return array[] ws_board_rule_run_lines()
 */
function ws_board_rule_last_runs($viewer, $channel, $rules, $flows)
{
    $by_id = array();
    $names = array();

    foreach ((array) $flows as $flow) {
        $by_id[(int) $flow['id']] = $flow;
    }

    $ids = array_values(array_filter(ws_board_rule_flow_ids($rules), function ($id) use ($by_id) {
        return isset($by_id[$id]);
    }));

    if (empty($ids)) {
        return array();
    }

    foreach ($ids as $id) {
        $names[$id] = (string) $by_id[$id]['name'];
    }

    $runs = array();

    foreach ((array) db_items("SELECT * FROM ws_flow_runs WHERE flow_id IN (" . implode(',', $ids) . ") ORDER BY id DESC LIMIT " . (WS_BOARD_RULE_RUNS * 4)) as $run) {
        $access = ws_flow_run_access($viewer, $by_id[(int) $run['flow_id']], $run);

        if ($access !== '') {
            $run['access'] = $access;
            $runs[(int) $run['id']] = $run;
        }

        if (count($runs) >= WS_BOARD_RULE_RUNS) {
            break;
        }
    }

    $task_ids = array();

    foreach ($runs as $run) {
        if (((string) $run['subject_type'] === 'task') && ((int) $run['subject_id'] > 0)) {
            $task_ids[] = (int) $run['subject_id'];
        }
    }

    $titles = array();

    if ($task_ids) {
        foreach ((array) db_items("SELECT id, title FROM ws_tasks WHERE id IN (" . implode(',', array_unique($task_ids)) . ")
            AND channel_id = '" . (int) $channel['id'] . "'") as $task) {
            $titles[(int) $task['id']] = (string) $task['title'];
        }
    }

    return ws_board_rule_run_lines(array_values($runs), ws_flow_runs_results($runs), $titles, $names);
}
