<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a flow an application proposes as a draft card.
 *
 * An application key or an OAuth connection proposes a flow in a channel with
 * the record changes of POST /workspace/channels/{id}/changes, as
 * {type: flow, action: draft, data, reason}: data is the flow as
 * POST /workspace/flows takes it (name, nodes, edges, notes, or template and
 * answers, and channel_id, its home). Nothing is created: the save is
 * rehearsed for the person it is proposed to (ws_flow_save_check()), the
 * draft is kept as a ws_ai_changes row of record_type 'flow', action
 * 'draft', and its card opens it as a new draft flow of theirs on the Flows
 * canvas (ws_flow_draft_open()), where they build on it and publish it in
 * their own session. Like a drafted scheduled action (scheduled_draft.php)
 * it is not one of ws_change_types() and is never applied; only the person
 * it is proposed to sees its boxes on the card.
 *
 * Pinegrap AI drafts the same card with its propose_flow tool, whose schema
 * is built from the box catalogue: a new flow laid out on the canvas here, or
 * changes (add, connect, remove, set) to a flow the person may change, made
 * again on that flow's draft when the card is opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most boxes a draft card names; the rest are counted. */
define('WS_FLOW_DRAFT_CARD_BOXES', 12);

/** The most problems a draft card tells before it is opened. */
define('WS_FLOW_DRAFT_CARD_PROBLEMS', 3);

/**
 * A flow an application proposes, read for its shape only (pure): the action
 * is draft, data an object, its parts of the right types. The flow itself is
 * checked for the person (ws_flow_draft_proposal_row()).
 *
 * @param mixed $change the change, as sent
 * @return array ok, error, data (name, graph, template, answers: the ones sent), home (channel_id sent, 0 for none), notes
 */
function ws_flow_draft_proposal_input($change)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'data' => array(), 'home' => 0, 'notes' => array());
    };
    $is_list = function ($value) {
        return is_array($value) && (array_keys($value) === range(0, count($value) - 1));
    };
    $is_object = function ($value) use ($is_list) {
        return is_array($value) && (empty($value) || !$is_list($value));
    };

    $change = is_array($change) ? $change : array();

    if ((string) ($change['action'] ?? 'draft') !== 'draft') {
        return $fail(lang('A flow is proposed with the action draft: the person opens it as a new flow and publishes it.'));
    }

    $sent = $change['data'] ?? null;

    if (!$is_object($sent)) {
        return $fail(lang('data is the flow: an object of name, nodes, edges, notes (or template and answers) and channel_id.'));
    }

    $data = array();

    if (array_key_exists('name', $sent)) {
        $data['name'] = is_scalar($sent['name']) ? (string) $sent['name'] : '';
    }

    $parts = array_values(array_intersect(array('nodes', 'edges', 'notes'), array_keys($sent)));

    foreach ($parts as $part) {
        if (!(is_array($sent[$part]) && (empty($sent[$part]) || $is_list($sent[$part])))) {
            return $fail(lang(array('string' => '{var:1} is a list.', 'vars' => $part)));
        }
    }

    if (array_key_exists('template', $sent)) {
        if (!empty($parts)) {
            return $fail(lang('Send a template or boxes, not both.'));
        }

        if (!is_string($sent['template']) || ($sent['template'] === '')) {
            return $fail(lang('template is the key of a flow template.'));
        }

        $data['template'] = $sent['template'];

        if (array_key_exists('answers', $sent)) {
            if (!$is_object($sent['answers'])) {
                return $fail(lang('answers is an object of setting type => value.'));
            }

            $data['answers'] = $sent['answers'];
        }
    } else {
        $graph = ws_flow_graph_empty();

        foreach ($parts as $part) {
            $graph[$part] = $sent[$part];
        }

        $data['graph'] = $graph;
    }

    $reason = trim(mb_substr(preg_replace('/\s+/u', ' ', is_scalar($change['reason'] ?? null) ? (string) $change['reason'] : ''), 0, 500));

    return array(
        'ok'    => true,
        'error' => '',
        'data'  => $data,
        'home'  => max(0, (int) ($sent['channel_id'] ?? 0)),
        'notes' => ($reason !== '') ? array($reason) : array(),
    );
}

/**
 * A flow an application proposes, as the row of its card: the save
 * rehearsed with the person it is proposed to (ws_flow_save_check(), the
 * check of POST /workspace/flows), so a flow they could not keep is refused
 * now; then kept as a ws_ai_changes row of record_type 'flow', action
 * 'draft', holding the name, the home and the graph as the check read them.
 * Its home is the channel_id sent, else the channel it is proposed in when a
 * flow may live there and the person reads it, else none.
 *
 * @param int   $asker_id   the person it is proposed to
 * @param mixed $change     the change, as sent
 * @param int   $channel_id the channel it is proposed in
 * @return array ok, error, row (as ws_changes_input() gives them)
 */
function ws_flow_draft_proposal_row($asker_id, $change, $channel_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'row' => null);
    };

    $input = ws_flow_draft_proposal_input($change);

    if (!$input['ok']) {
        return $fail($input['error']);
    }

    if (!ws_flows_ready()) {
        return $fail(lang('This site cannot take proposed changes yet: its database has to be upgraded.'));
    }

    $viewer = function_exists('ws_ai_viewer') ? ws_ai_viewer((int) $asker_id) : null;

    if (!$viewer || !ws_can_schedule($viewer)) {
        return $fail(lang('The person this is proposed to cannot build flows, so a flow cannot be proposed.'));
    }

    $data = $input['data'];

    // A template's boxes with the answers written in, as POST /workspace/flows
    // makes them, filled for the person.
    if (isset($data['template'])) {
        if (!function_exists('ws_api_flow_from_template')) {
            return $fail(lang('That template is not offered to you.'));
        }

        $made = ws_api_flow_from_template(ws_flow_templates_for($viewer), $data, array_intersect_key($data, array('name' => true)));

        if (!$made['ok']) {
            return $fail($made['field'] . ': ' . $made['error']);
        }

        $data = $made['data'];
    }

    if ($input['home'] > 0) {
        $data['channel_id'] = $input['home'];
    } else {
        $here = ws_channel($channel_id);
        $data['channel_id'] = ($here && in_array((string) $here['kind'], array('public', 'private'), true) && ws_can_read_channel($viewer, $here)) ? (int) $here['id'] : 0;
    }

    $checked = ws_flow_save_check($viewer, array_intersect_key($data, array('name' => true, 'channel_id' => true, 'graph' => true)));

    if (!$checked['ok']) {
        return $fail(($checked['field'] !== '') ? $checked['field'] . ': ' . $checked['error'] : $checked['error']);
    }

    $home = (int) ($checked['sets']['channel_id'] ?? 0);

    return array('ok' => true, 'error' => '', 'row' => array(
        'type'      => 'flow',
        'action'    => 'draft',
        'record_id' => 0,
        'fields'    => array(),
        'reason'    => '',
        'snapshot'  => array(
            'sentence'   => '',
            'data'       => array('name' => $checked['sets']['name'], 'channel_id' => $home, 'graph' => $checked['graph']),
            'notes'      => $input['notes'],
            'channel_id' => $home,
        ),
    ));
}

/**
 * What a flow draft's card says of it (pure but for the start's channel,
 * read with the person's rights): how it starts, its boxes by kind and name,
 * and what stops it from being published yet. A draft that changes a flow
 * names its changes instead of the boxes.
 *
 * @param array      $viewer
 * @param array      $graph ws_flow_graph_normalize()
 * @param array|null $patch ws_flow_patch_apply() ops of a draft that changes a flow
 * @return array lines (name, label, text), problems (texts)
 */
function ws_flow_draft_outline($viewer, $graph, $patch = null)
{
    $kinds = ws_flow_kinds();
    $lines = array();

    if ($patch !== null) {
        $all = ws_flow_patch_lines($patch, $graph);
        $lines = array_slice($all, 0, WS_FLOW_DRAFT_CARD_BOXES);
        $more = count($all) - WS_FLOW_DRAFT_CARD_BOXES;
    } else {
        $start = ws_flow_start_text($viewer, $graph);

        if (($start !== null) && ($start !== '')) {
            $lines[] = array('name' => 'start', 'label' => lang('Starts'), 'text' => $start);
        }

        foreach (array_slice($graph['nodes'], 0, WS_FLOW_DRAFT_CARD_BOXES) as $node) {
            $kind = (string) $node['kind'];
            $lines[] = array('name' => 'box_' . $node['id'], 'label' => (string) ($kinds[$kind]['label'] ?? $kind), 'text' => ws_flow_box_name($node));
        }

        $more = count($graph['nodes']) - WS_FLOW_DRAFT_CARD_BOXES;
    }

    if ($more > 0) {
        $lines[] = array('name' => 'more', 'label' => '', 'text' => lang(array('string' => 'and {var:1} more boxes', 'vars' => $more)));
    }

    $problems = array();

    foreach (array_slice(ws_flow_problems($graph), 0, WS_FLOW_DRAFT_CARD_PROBLEMS) as $problem) {
        $problems[] = (string) $problem['text'];
    }

    return array('lines' => $lines, 'problems' => $problems);
}

/**
 * A flow draft on the card, as ws_changes_map() draws a change. Only the
 * person it is proposed to sees its name and boxes, and only they open it.
 *
 * @param array  $viewer
 * @param array  $row      ws_ai_changes
 * @param array  $people   ws_people()
 * @param bool   $can_post may the viewer post where the card is
 * @param string $proposer the application that proposed it
 * @return array
 */
function ws_flow_draft_change_entry($viewer, $row, $people, $can_post, $proposer = '')
{
    $draft = json_decode((string) $row['snapshot'], true);
    $draft = is_array($draft) ? $draft : array();
    $data = is_array($draft['data'] ?? null) ? $draft['data'] : array();
    $notes = array_values(array_filter(array_map('strval', (array) ($draft['notes'] ?? array()))));
    $pending = ($row['status'] === 'pending');
    $asker = ((int) $row['requested_by'] === (int) $viewer['id']);
    $may = $pending && $asker && $can_post && ws_can_schedule($viewer);
    $items = array();

    if ($may) {
        $outline = ws_flow_draft_outline($viewer, ws_flow_graph_normalize($data['graph'] ?? array()), isset($draft['base']) ? (array) ($draft['patch'] ?? array()) : null);

        foreach ($outline['lines'] as $line) {
            $items[] = array('name' => $line['name'], 'label' => $line['label'], 'from' => '', 'to' => $line['text'], 'html' => '');
        }

        if (!empty($outline['problems'])) {
            $notes[] = lang(array('string' => 'Before it is published: {var:1}', 'vars' => implode(' ', $outline['problems'])));
        }
    }

    return array(
        'id'           => (int) $row['id'],
        'type'         => 'flow',
        'type_label'   => lang('Flow'),
        'action'       => 'draft',
        'action_label' => $pending ? lang('Draft') : '',
        'record_html'  => '',
        'record_label' => $asker ? (string) ($data['name'] ?? '') : '',
        'fields'       => $items,
        'count'        => count($items),
        'hidden'       => false,
        'reason'       => ($asker && $pending) ? implode(' ', $notes) : '',
        'warning'      => '',
        'status'       => (string) $row['status'],
        'error'        => (string) $row['error'],
        'asker'        => (string) ($people[(int) $row['requested_by']]['name'] ?? ''),
        'decided_by'   => (string) ($people[(int) $row['decided_by']]['name'] ?? ''),
        'decided'      => ((int) $row['decided_at'] > 0) ? ws_time_label($row['decided_at']) : '',
        'decision_id'  => 0,
        // The flow it was opened as (ws_flow_draft_open()), for the one it
        // was proposed to.
        'flow_id'      => $asker ? (int) ($draft['flow_id'] ?? 0) : 0,
        // Not applied: opened as a new flow and published there.
        'can_apply'    => false,
        'no_right'     => $pending && $asker && !ws_can_schedule($viewer),
        'can_dismiss'  => $pending && $can_post && ($asker || ((int) $viewer['role'] < 3)),
        'blocked'      => '',
        'proposer'     => (string) $proposer,
        'draft'        => $may ? array(
            'channel_id' => (int) ($draft['channel_id'] ?? 0),
            'sentence'   => '',
            'data'       => array('name' => (string) ($data['name'] ?? '')),
            'notes'      => $notes,
        ) : null,
    );
}

/**
 * A flow draft as the new flow it opens as (pure): its name, its home and
 * its graph, as the proposal's check kept them.
 *
 * @param array $draft the snapshot, decoded
 * @return array name, channel_id, graph, left
 */
function ws_flow_draft_flow_input($draft)
{
    $data = is_array($draft['data'] ?? null) ? $draft['data'] : array();
    $name = trim(is_scalar($data['name'] ?? null) ? (string) $data['name'] : '');

    return array(
        'name'       => ($name !== '') ? $name : lang('Flow'),
        'channel_id' => max(0, (int) ($data['channel_id'] ?? 0)),
        'graph'      => ws_flow_graph_normalize($data['graph'] ?? array()),
        'left'       => array(),
    );
}

/**
 * Writes a draft card's flow as a new draft flow of the person it waits for
 * and marks the card applied with it: the home only when a flow may still
 * live there and they read it, else none. The flow's own save checks it all
 * again.
 *
 * @param array $viewer
 * @param array $row        ws_ai_changes, pending
 * @param array $draft      its snapshot, decoded
 * @param array $input      name, channel_id (the home), graph, left
 * @return array ok, error, flow_id, left
 */
function ws_flow_draft_opened($viewer, $row, $draft, $input)
{
    $home = ws_channel($input['channel_id']);
    $public = $home && in_array((string) $home['kind'], array('public', 'private'), true) && ws_can_read_channel($viewer, $home);
    $saved = ws_flow_save($viewer, array('name' => $input['name'], 'channel_id' => $public ? (int) $home['id'] : 0, 'graph' => $input['graph']));

    if (!$saved['ok']) {
        return array('ok' => false, 'error' => $saved['error'], 'flow_id' => 0, 'left' => array());
    }

    ws_flow_draft_mark_opened($viewer, $row, $draft, (int) $saved['flow_id']);

    return array('ok' => true, 'error' => '', 'flow_id' => (int) $saved['flow_id'], 'left' => $input['left']);
}

/**
 * Marks a draft card applied with the flow it was opened as, while it still
 * waits.
 *
 * @param array $viewer
 * @param array $row     ws_ai_changes
 * @param array $draft   its snapshot, decoded
 * @param int   $flow_id
 */
function ws_flow_draft_mark_opened($viewer, $row, $draft, $flow_id)
{
    $draft['flow_id'] = (int) $flow_id;

    db("UPDATE ws_ai_changes SET status = 'applied', error = '',
            snapshot = '" . e(json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)) . "',
            decided_by = '" . (int) $viewer['id'] . "', decided_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status = 'pending'");

    if (mysqli_affected_rows(db::$con) === 1) {
        ws_message_touch($row['message_id']);
    }
}

/**
 * Opens a draft card as a new flow of the person it waits for: a proposed
 * flow here, a drafted scheduled action through
 * ws_scheduled_draft_flow_open(). Only while it waits, where they may still
 * post, with the right to build flows.
 *
 * @param array $viewer
 * @param int   $change_id the ws_ai_changes row of the draft
 * @return array ok, error, flow_id, left
 */
function ws_flow_draft_open($viewer, $change_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'flow_id' => 0, 'left' => array());
    };

    if (!function_exists('ws_changes_ready') || !ws_changes_ready() || !ws_flows_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    $row = db_item("SELECT * FROM ws_ai_changes WHERE id = '" . (int) $change_id . "' AND record_type IN ('flow', 'scheduled') AND action = 'draft'");

    if (is_array($row) && ($row['record_type'] !== 'flow')) {
        return ws_scheduled_draft_flow_open($viewer, $change_id);
    }

    if (!is_array($row) || ((int) $row['requested_by'] !== (int) $viewer['id'])) {
        return $fail(lang('That draft could not be found.'));
    }

    if ($row['status'] !== 'pending') {
        return $fail(lang('This draft was already opened or dismissed.'));
    }

    if (!ws_can_schedule($viewer) || !ws_can_post_channel($viewer, ws_channel($row['channel_id']))) {
        return $fail(lang('Only staff in the team can build flows.'));
    }

    $draft = json_decode((string) $row['snapshot'], true);
    $draft = is_array($draft) ? $draft : array();

    // Changes to a flow that exists (propose_flow with flow_id).
    if (isset($draft['base'])) {
        return ws_flow_draft_patch_open($viewer, $row, $draft);
    }

    return ws_flow_draft_opened($viewer, $row, $draft, ws_flow_draft_flow_input($draft));
}

// ─── Drafted by Pinegrap AI ─────────────────────────────────────────────

/** The most flows Pinegrap AI drafts for one request. */
define('WS_FLOW_AI_PER_ANSWER', 2);

/** The most changes one patch to a flow makes. */
define('WS_FLOW_PATCH_MAX', 40);

/** Canvas units between two columns and two rows of an automatic layout (the grid is 16). */
define('WS_FLOW_LAYOUT_STEP_X', 288);
define('WS_FLOW_LAYOUT_STEP_Y', 128);
define('WS_FLOW_LAYOUT_ORIGIN', 96);

/**
 * May Pinegrap AI draft a flow for this request? Only for a request in a
 * channel (not a note, a task or an MCP server's alias), from a person who
 * builds flows.
 *
 * @param array      $row   the request
 * @param array|null $asker ws_ai_viewer()
 * @return bool
 */
function ws_flow_ai_offered($row, $asker)
{
    return is_array($asker)
        && ((int) ($row['channel_id'] ?? 0) > 0)
        && ((int) ($row['note_id'] ?? 0) === 0)
        && ((int) ($row['task_id'] ?? 0) === 0)
        && ((int) ($row['mcp_server_id'] ?? 0) === 0)
        && function_exists('ws_changes_ready') && ws_changes_ready()
        && ws_flows_ready() && ws_can_schedule($asker);
}

/**
 * The settings each kind of box takes, read from the catalogue (pure).
 *
 * @param array[] $catalogue ws_flow_kind_catalogue()
 * @return array kind => setting keys
 */
function ws_flow_ai_setting_keys($catalogue)
{
    $out = array();

    foreach ($catalogue as $kind) {
        $out[(string) $kind['kind']] = array_map(function ($setting) {
            return (string) $setting['key'];
        }, (array) $kind['settings']);
    }

    return $out;
}

/**
 * The catalogue as the model reads it, one line per kind of box: whether it
 * starts the flow, the outcomes an arrow leaves it on, the values later boxes
 * read, the settings filled with values and every setting with its type or
 * its words (pure).
 *
 * @param array[] $catalogue ws_flow_kind_catalogue()
 * @return string
 */
function ws_flow_ai_catalogue_text($catalogue)
{
    $lines = array();

    foreach ($catalogue as $kind) {
        $settings = array();

        foreach ((array) $kind['settings'] as $setting) {
            $type = ($setting['type'] === 'list') ? 'list of ' . $setting['of'] : (string) $setting['type'];
            $settings[] = $setting['key'] . ' (' . (($setting['values'] !== null) ? implode('|', $setting['values']) : $type) . ')';
        }

        $outputs = array_map(function ($output) {
            return (string) $output['key'];
        }, (array) $kind['outputs']);
        $outcomes = (array) $kind['outcomes'];

        // An arrow on always is taken however the box ended (ws_flow_outcomes()).
        if (empty($kind['starts']) && !empty($outcomes)) {
            $outcomes[] = 'always';
        }

        $lines[] = '- ' . $kind['kind'] . ' "' . $kind['label'] . '"' . (!empty($kind['starts']) ? ', starts the flow' : '')
            . '; arrows leave on: ' . (empty($outcomes) ? 'none' : implode(', ', $outcomes))
            . (empty($outputs) ? '' : '; values: ' . implode(', ', $outputs))
            . (empty($kind['texts']) ? '' : '; {{...}} filled in: ' . implode(', ', (array) $kind['texts']))
            . '; settings: ' . (empty($settings) ? 'none' : implode(', ', $settings));
    }

    return implode("\n", $lines);
}

/**
 * The propose_flow tool, its schema built from the catalogue: the kinds of
 * box and the outcomes an arrow leaves on are the only words taken, and the
 * description lists what each kind takes (pure).
 *
 * @param array[] $catalogue ws_flow_kind_catalogue()
 * @return array a chat-completions tool
 */
function ws_flow_ai_tool($catalogue)
{
    $kinds = array();
    $outcomes = array();

    foreach ($catalogue as $kind) {
        $kinds[] = (string) $kind['kind'];

        foreach ((array) $kind['outcomes'] as $outcome) {
            $outcomes[(string) $outcome] = true;
        }
    }

    $outcomes['always'] = true;

    $box = array(
        'id'       => array('type' => 'string', 'description' => 'Small letters, digits and _, starting with a letter, such as task_1.'),
        'kind'     => array('type' => 'string', 'enum' => $kinds),
        'label'    => array('type' => 'string', 'description' => 'A short name shown on the box.'),
        'settings' => array('type' => 'object', 'description' => 'Only the settings its kind lists; leave the others out.'),
    );
    $arrow = array(
        'from' => array('type' => 'string', 'description' => 'The id of the box the arrow leaves.'),
        'to'   => array('type' => 'string', 'description' => 'The id of the box it leads to.'),
        'on'   => array('type' => 'string', 'enum' => array_keys($outcomes), 'description' => 'The outcome of the from box the arrow is taken on.'),
    );

    $description = 'Draft a flow: boxes joined by arrows, run one after another. Only when the request asks to build or change a flow, a process of several steps, or an automation with conditions or approvals. '
        . 'Nothing runs and nothing is saved: it waits under your answer as a draft, and the person who asked opens it on the Flows canvas and publishes it. '
        . 'A new flow: name, boxes and arrows, exactly one box that starts it. A flow that exists: flow_id (find it with read_flow) and changes, never the whole flow. '
        . 'A text setting may take {{node.ID.VALUE}} (a value of an earlier box) or {{trigger.FIELD}} (what started the run). Places on the canvas are set for you. '
        . "Kinds of box:\n" . ws_flow_ai_catalogue_text($catalogue);

    return array('type' => 'function', 'function' => array(
        'name'        => 'propose_flow',
        'description' => $description,
        'parameters'  => array('type' => 'object', 'properties' => (object) array(
            'name'    => array('type' => 'string', 'description' => 'The name of a new flow.'),
            'flow_id' => array('type' => 'integer', 'description' => 'A flow that exists, to change with changes.'),
            'boxes'   => array('type' => 'array', 'items' => array('type' => 'object', 'properties' => (object) $box, 'required' => array('id', 'kind'))),
            'arrows'  => array('type' => 'array', 'items' => array('type' => 'object', 'properties' => (object) $arrow, 'required' => array('from', 'to'))),
            'changes' => array('type' => 'array', 'description' => 'For flow_id, in order: add (id, kind, label, settings), connect (from, to, on), remove (id for a box with its arrows; from and to for an arrow), set (id, and label or settings to change).', 'items' => array('type' => 'object', 'properties' => (object) (array(
                'op' => array('type' => 'string', 'enum' => array('add', 'connect', 'remove', 'set')),
            ) + $box + $arrow), 'required' => array('op'))),
            'reason'  => array('type' => 'string', 'description' => 'One line on what the flow does.'),
        ), 'required' => array()),
    ));
}

/**
 * A box the model sent, as a box of the graph (pure): a kind of the
 * catalogue and only the settings that kind takes, so nothing is invented.
 *
 * @param mixed  $box
 * @param array  $keys  ws_flow_ai_setting_keys()
 * @param string $where where it was sent, for the error
 * @return array ok, error, node
 */
function ws_flow_ai_node($box, $keys, $where)
{
    $fail = function ($error) use ($where) {
        return array('ok' => false, 'error' => $where . $error, 'node' => null);
    };

    if (!is_array($box)) {
        return $fail('a box is an object of id, kind, label and settings.');
    }

    $kind = is_string($box['kind'] ?? null) ? $box['kind'] : '';

    if (!isset($keys[$kind])) {
        return $fail('"' . $kind . '" is not a kind of box. Use one of: ' . implode(', ', array_keys($keys)) . '.');
    }

    $settings = $box['settings'] ?? array();

    if (!is_array($settings)) {
        return $fail('settings is an object.');
    }

    $unknown = array_diff(array_map('strval', array_keys($settings)), $keys[$kind]);

    if (!empty($unknown)) {
        return $fail(implode(', ', $unknown) . ' is not a setting of a ' . $kind . ' box. Its settings are: ' . (empty($keys[$kind]) ? 'none' : implode(', ', $keys[$kind])) . '.');
    }

    return array('ok' => true, 'error' => '', 'node' => array(
        'id'     => is_scalar($box['id'] ?? null) ? (string) $box['id'] : '',
        'kind'   => $kind,
        'label'  => is_scalar($box['label'] ?? null) ? (string) $box['label'] : '',
        'x'      => 0,
        'y'      => 0,
        'config' => $settings,
    ));
}

/**
 * An arrow the model sent, as an arrow of the graph (pure).
 *
 * @param mixed $arrow
 * @return array from, to, on
 */
function ws_flow_ai_edge($arrow)
{
    $arrow = is_array($arrow) ? $arrow : array();
    $text = function ($value) {
        return is_scalar($value) ? (string) $value : '';
    };

    return array('from' => $text($arrow['from'] ?? ''), 'to' => $text($arrow['to'] ?? ''), 'on' => $text($arrow['on'] ?? ''));
}

/**
 * A new flow the model sent as boxes and arrows, as a graph laid out on the
 * canvas (pure). The graph's own check is made afterwards.
 *
 * @param array $args boxes, arrows
 * @param array $keys ws_flow_ai_setting_keys()
 * @return array ok, error, graph
 */
function ws_flow_ai_graph($args, $keys)
{
    $boxes = $args['boxes'] ?? null;
    $arrows = $args['arrows'] ?? array();

    if (!is_array($boxes) || empty($boxes)) {
        return array('ok' => false, 'error' => 'boxes is the list of the flow\'s boxes, one of them the box that starts it.', 'graph' => null);
    }

    if (count($boxes) > WS_FLOW_MAX_NODES) {
        return array('ok' => false, 'error' => 'A flow holds at most ' . WS_FLOW_MAX_NODES . ' boxes.', 'graph' => null);
    }

    $graph = ws_flow_graph_empty();

    foreach (array_values($boxes) as $index => $box) {
        $made = ws_flow_ai_node($box, $keys, 'boxes[' . $index . ']: ');

        if (!$made['ok']) {
            return array('ok' => false, 'error' => $made['error'], 'graph' => null);
        }

        $graph['nodes'][] = $made['node'];
    }

    foreach (is_array($arrows) ? array_values($arrows) : array() as $arrow) {
        $graph['edges'][] = ws_flow_ai_edge($arrow);
    }

    return array('ok' => true, 'error' => '', 'graph' => ws_flow_auto_layout(ws_flow_graph_normalize($graph), array()));
}

/**
 * Changes to a flow (add, connect, remove, set) made on its graph, in order
 * (pure). Each change is kept as it was read, so the same patch can be made
 * again later on the draft as it is then. The boxes it adds are laid out next
 * to the box they follow; the others keep their places.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @param mixed $ops   the changes, as sent
 * @param array $keys  ws_flow_ai_setting_keys()
 * @return array ok, error, graph, ops (as read), touched (ids of the boxes added or changed)
 */
function ws_flow_patch_apply($graph, $ops, $keys)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'graph' => null, 'ops' => array(), 'touched' => array());
    };

    if (!is_array($ops) || empty($ops)) {
        return $fail('changes is the list of what to change in the flow.');
    }

    if (count($ops) > WS_FLOW_PATCH_MAX) {
        return $fail('At most ' . WS_FLOW_PATCH_MAX . ' changes at once.');
    }

    $graph = ws_flow_graph_normalize($graph);
    $placed = array();
    $index_of = function ($id) use (&$graph) {
        foreach ($graph['nodes'] as $index => $node) {
            if ($node['id'] === $id) {
                return $index;
            }
        }

        return -1;
    };

    foreach ($graph['nodes'] as $node) {
        $placed[$node['id']] = true;
    }

    $kept = array();
    $touched = array();
    $text = function ($value) {
        return is_scalar($value) ? (string) $value : '';
    };

    foreach (array_values($ops) as $i => $op) {
        $where = 'changes[' . $i . ']: ';
        $op = is_array($op) ? $op : array();
        $what = $text($op['op'] ?? '');

        if ($what === 'add') {
            $made = ws_flow_ai_node($op, $keys, $where);

            if (!$made['ok']) {
                return $fail($made['error']);
            }

            $id = $made['node']['id'];

            if (!ws_flow_node_id_valid($id) || ($index_of($id) >= 0)) {
                return $fail($where . 'a new box needs an id of small letters, digits and _ that no box has yet.');
            }

            $graph['nodes'][] = $made['node'];
            $kept[] = array('op' => 'add', 'id' => $id, 'kind' => $made['node']['kind'], 'label' => $made['node']['label'], 'settings' => $made['node']['config']);
            $touched[$id] = true;
            continue;
        }

        if ($what === 'connect') {
            $edge = ws_flow_ai_edge($op);

            if (($index_of($edge['from']) < 0) || ($index_of($edge['to']) < 0)) {
                return $fail($where . 'from and to are ids of boxes in the flow.');
            }

            if (!in_array($edge, $graph['edges'], true)) {
                $graph['edges'][] = $edge;
            }

            $kept[] = array('op' => 'connect') + $edge;
            continue;
        }

        if ($what === 'remove') {
            $id = $text($op['id'] ?? '');

            if ($id !== '') {
                $index = $index_of($id);

                if ($index < 0) {
                    return $fail($where . 'there is no box ' . $id . ' in the flow.');
                }

                $kept[] = array('op' => 'remove', 'id' => $id, 'name' => ws_flow_box_name($graph['nodes'][$index]));
                array_splice($graph['nodes'], $index, 1);
                $graph['edges'] = array_values(array_filter($graph['edges'], function ($edge) use ($id) {
                    return ($edge['from'] !== $id) && ($edge['to'] !== $id);
                }));
                unset($touched[$id], $placed[$id]);
                continue;
            }

            $edge = ws_flow_ai_edge($op);
            $left = array_values(array_filter($graph['edges'], function ($line) use ($edge) {
                return !(($line['from'] === $edge['from']) && ($line['to'] === $edge['to']) && (($edge['on'] === '') || ($line['on'] === $edge['on'])));
            }));

            if (count($left) === count($graph['edges'])) {
                return $fail($where . 'there is no arrow from ' . $edge['from'] . ' to ' . $edge['to'] . '.');
            }

            $graph['edges'] = $left;
            $kept[] = array('op' => 'remove') + $edge;
            continue;
        }

        if ($what === 'set') {
            $id = $text($op['id'] ?? '');
            $index = $index_of($id);

            if ($index < 0) {
                return $fail($where . 'there is no box ' . $id . ' in the flow.');
            }

            $node = $graph['nodes'][$index];
            $made = ws_flow_ai_node(array('kind' => $node['kind'], 'settings' => $op['settings'] ?? array()), $keys, $where);

            if (!$made['ok']) {
                return $fail($made['error']);
            }

            $change = array('op' => 'set', 'id' => $id, 'settings' => $made['node']['config']);

            if (array_key_exists('label', $op)) {
                $node['label'] = $change['label'] = $text($op['label']);
            }

            $node['config'] = array_merge($node['config'], $made['node']['config']);
            $graph['nodes'][$index] = $node;
            $kept[] = $change;
            $touched[$id] = true;
            continue;
        }

        return $fail($where . 'op is add, connect, remove or set.');
    }

    $graph = ws_flow_graph_normalize($graph);

    return array('ok' => true, 'error' => '', 'graph' => ws_flow_auto_layout($graph, array_keys($placed)), 'ops' => $kept, 'touched' => array_keys($touched));
}

/**
 * Places on the canvas for the boxes not placed yet (pure): each one a
 * column after the box it follows, else after the boxes placed, one row
 * lower while that spot is taken. A graph with none placed is laid out in
 * columns from its start.
 *
 * @param array    $graph  ws_flow_graph_normalize()
 * @param string[] $placed ids of the boxes that keep their places
 * @return array the graph
 */
function ws_flow_auto_layout($graph, $placed)
{
    $placed = array_flip(array_map('strval', (array) $placed));
    $nodes = $graph['nodes'];
    $count = count($nodes);
    $before = array();
    $layer = array();

    foreach ($nodes as $node) {
        $before[$node['id']] = array();
        $layer[$node['id']] = 0;
    }

    foreach ($graph['edges'] as $edge) {
        if (isset($before[$edge['to']], $before[$edge['from']]) && ($edge['to'] !== $edge['from'])) {
            $before[$edge['to']][] = $edge['from'];
        }
    }

    // The longest way in from a box with none; a loop stops growing after
    // as many rounds as there are boxes.
    for ($round = 0; $round < $count; $round++) {
        $grew = false;

        foreach ($before as $id => $from) {
            foreach ($from as $other) {
                if ($layer[$other] + 1 > $layer[$id]) {
                    $layer[$id] = min($count, $layer[$other] + 1);
                    $grew = true;
                }
            }
        }

        if (!$grew) {
            break;
        }
    }

    $at = array();
    $right = null;
    $top = WS_FLOW_LAYOUT_ORIGIN;

    foreach ($nodes as $node) {
        if (isset($placed[$node['id']])) {
            $at[$node['id']] = array($node['x'], $node['y']);
            $right = ($right === null) ? $node['x'] : max($right, $node['x']);
            $top = min($top, $node['y']);
        }
    }

    $order = array_keys($nodes);

    usort($order, function ($a, $b) use ($nodes, $layer) {
        return ($layer[$nodes[$a]['id']] <=> $layer[$nodes[$b]['id']]) ?: ($a <=> $b);
    });

    $taken = function ($x, $y) use (&$at) {
        foreach ($at as $spot) {
            if ((abs($spot[0] - $x) < WS_FLOW_LAYOUT_STEP_X) && (abs($spot[1] - $y) < WS_FLOW_LAYOUT_STEP_Y)) {
                return true;
            }
        }

        return false;
    };

    foreach ($order as $index) {
        $id = $nodes[$index]['id'];

        if (isset($placed[$id])) {
            continue;
        }

        $x = null;
        $y = $top;

        foreach ($before[$id] as $from) {
            if (isset($at[$from])) {
                $x = $at[$from][0] + WS_FLOW_LAYOUT_STEP_X;
                $y = $at[$from][1];
                break;
            }
        }

        if ($x === null) {
            $x = ($right === null) ? WS_FLOW_LAYOUT_ORIGIN + $layer[$id] * WS_FLOW_LAYOUT_STEP_X : $right + WS_FLOW_LAYOUT_STEP_X;
        }

        $x = min(WS_FLOW_COORD_MAX, $x);

        for ($tries = 0; $taken($x, $y) && ($tries < WS_FLOW_MAX_NODES); $tries++) {
            $y += WS_FLOW_LAYOUT_STEP_Y;
        }

        $at[$id] = array($x, min(WS_FLOW_COORD_MAX, $y));
        $nodes[$index]['x'] = $at[$id][0];
        $nodes[$index]['y'] = $at[$id][1];
    }

    $graph['nodes'] = $nodes;

    return $graph;
}

/**
 * A patch on its card, one line per change (pure but for the words).
 *
 * @param array[] $ops   ws_flow_patch_apply() ops
 * @param array   $graph the flow with the patch made
 * @return array[] name, label, text
 */
function ws_flow_patch_lines($ops, $graph)
{
    $kinds = ws_flow_kinds();
    $names = array();

    foreach ($graph['nodes'] as $node) {
        $names[$node['id']] = ws_flow_box_name($node);
    }

    $name = function ($id) use ($names) {
        return $names[$id] ?? $id;
    };
    $lines = array();

    foreach (array_values((array) $ops) as $index => $op) {
        $what = (string) ($op['op'] ?? '');
        $kind = (string) ($op['kind'] ?? '');
        $arrow = isset($op['from']) ? $name((string) $op['from']) . ' → ' . $name((string) $op['to']) . ((($op['on'] ?? '') !== '') ? ' (' . $op['on'] . ')' : '') : '';

        if ($what === 'add') {
            $line = array(lang('Adds'), ($kinds[$kind]['label'] ?? $kind) . ': ' . $name((string) $op['id']));
        } elseif ($what === 'connect') {
            $line = array(lang('Connects'), $arrow);
        } elseif ($what === 'remove') {
            $line = array(lang('Removes'), isset($op['id']) ? (string) ($op['name'] ?? $op['id']) : $arrow);
        } elseif ($what === 'set') {
            $fields = array_keys((array) ($op['settings'] ?? array()));

            if (array_key_exists('label', $op)) {
                array_unshift($fields, 'label');
            }

            $line = array(lang('Changes'), $name((string) $op['id']) . (empty($fields) ? '' : ': ' . implode(', ', $fields)));
        } else {
            continue;
        }

        $lines[] = array('name' => 'patch_' . $index, 'label' => $line[0], 'text' => $line[1]);
    }

    return $lines;
}

/**
 * What stops a drafted flow from being published, as the model is told it
 * (the problems of ws_flow_problems(), warnings left out).
 *
 * @param array $graph
 * @return string[]
 */
function ws_flow_ai_problem_texts($graph)
{
    return array_map(function ($problem) {
        return (string) $problem['text'];
    }, array_slice(ws_flow_problems($graph), 0, 8));
}

/**
 * Pinegrap AI drafts a flow (propose_flow): a new one from boxes and arrows,
 * or changes to a flow the person who asked may change. It is checked as
 * POST /workspace/flows (or a draft save) would check it for them, and kept
 * among the proposals the answer carries, as the draft card an application's
 * flow proposal makes (ws_flow_draft_proposal_row()). What would stop it
 * from being published goes back to the model once to be fixed; a second
 * time it is kept and the card names it. Nothing is saved or run here.
 *
 * @param array $row   the request
 * @param array $state the conversation, by reference: proposals flows, flow_tries
 * @param array $args  name, flow_id, boxes, arrows, changes, reason
 * @return array what the model is told
 */
function ws_flow_ai_propose($row, &$state, $args)
{
    $asker = ws_ai_viewer((int) $row['requested_by']);

    if (!ws_flow_ai_offered($row, $asker)) {
        return array('error' => 'A flow cannot be drafted for this request.');
    }

    if (count((array) ($state['proposals']['flows'] ?? array())) >= WS_FLOW_AI_PER_ANSWER) {
        return array('error' => 'At most ' . WS_FLOW_AI_PER_ANSWER . ' flows are drafted for one request. Answer with those.');
    }

    $keys = ws_flow_ai_setting_keys(ws_flow_kind_catalogue());
    $reason = trim(mb_substr(preg_replace('/\s+/u', ' ', is_scalar($args['reason'] ?? null) ? (string) $args['reason'] : ''), 0, 500));
    $flow = null;
    $patch = null;

    if ((int) ($args['flow_id'] ?? 0) > 0) {
        if (!empty($args['boxes']) || !empty($args['arrows'])) {
            return array('error' => 'A flow that exists is changed with changes (add, connect, remove, set), never sent whole.');
        }

        $flow = ws_flow_for($asker, (int) $args['flow_id'], 'edit');

        if (!$flow || ($flow['status'] === 'binned')) {
            return array('error' => 'There is no flow with that id that the person who asked may change. Find it with read_flow.');
        }

        $patch = ws_flow_patch_apply(ws_flow_graph_of($flow), $args['changes'] ?? null, $keys);

        if (!$patch['ok']) {
            return array('error' => $patch['error']);
        }

        $graph = $patch['graph'];
    } else {
        if (!empty($args['changes'])) {
            return array('error' => 'changes are for a flow that exists (flow_id). A new flow is sent as name, boxes and arrows.');
        }

        $made = ws_flow_ai_graph($args, $keys);

        if (!$made['ok']) {
            return array('error' => $made['error']);
        }

        $graph = $made['graph'];
    }

    $problems = ws_flow_ai_problem_texts($graph);

    if (!empty($problems) && ((int) ($state['flow_tries'] ?? 0) < 1)) {
        $state['flow_tries'] = (int) ($state['flow_tries'] ?? 0) + 1;

        return array('error' => 'Not drafted yet; it could not be published like this: ' . implode(' ', $problems) . ' Fix that and call propose_flow again with all of it.');
    }

    if ($flow === null) {
        $made = ws_flow_draft_proposal_row((int) $asker['id'], array(
            'type'   => 'flow',
            'action' => 'draft',
            'data'   => array('name' => is_scalar($args['name'] ?? null) ? (string) $args['name'] : '', 'nodes' => $graph['nodes'], 'edges' => $graph['edges']),
            'reason' => $reason,
        ), (int) $row['channel_id']);

        if (!$made['ok']) {
            return array('error' => $made['error']);
        }

        $kept = $made['row'];
    } else {
        $checked = ws_flow_save_check($asker, array('graph' => $graph), $flow);

        if (!$checked['ok']) {
            return array('error' => $checked['error']);
        }

        $kept = array('type' => 'flow', 'action' => 'draft', 'record_id' => 0, 'fields' => array(), 'reason' => '', 'snapshot' => array(
            'sentence'   => '',
            'data'       => array('name' => (string) $flow['name'], 'channel_id' => (int) $flow['channel_id'], 'graph' => $checked['graph']),
            'notes'      => ($reason !== '') ? array($reason) : array(),
            'channel_id' => (int) $flow['channel_id'],
            // The flow it changes and the changes themselves, made again on
            // its draft as it is when the card is opened.
            'base'       => array('flow_id' => (int) $flow['id'], 'draft_rev' => (int) $flow['draft_rev']),
            'patch'      => $patch['ops'],
            'touched'    => $patch['touched'],
        ));
    }

    $state['proposals']['flows'][] = $kept;
    $outline = ws_flow_draft_outline($asker, ws_flow_graph_normalize($kept['snapshot']['data']['graph']), ($patch !== null) ? $patch['ops'] : null);
    $said = array();

    foreach ($outline['lines'] as $line) {
        $said[] = trim($line['label'] . ': ' . $line['text'], ': ');
    }

    return array(
        'ok'       => true,
        'drafted'  => $said,
        'problems' => $problems,
        'note'     => 'Drafted. It waits under your answer; the person who asked opens it on the Flows canvas and publishes it there. Say that you drafted it, never that it is set up or running.',
    );
}

/**
 * read_flow: one flow the person who asked reaches, with its boxes and
 * arrows, or the flows whose name holds a word, each saying whether they
 * may change it.
 *
 * @param array $asker
 * @param array $args id, query
 * @return array what the model is told
 */
function ws_flow_ai_read($asker, $args)
{
    if (!ws_flows_ready() || !ws_can_schedule($asker)) {
        return array('error' => 'Flows cannot be read here.');
    }

    $id = (int) ($args['id'] ?? 0);

    if ($id > 0) {
        $flow = ws_flow_for($asker, $id, 'view');

        if (!$flow || ($flow['status'] === 'binned')) {
            return array('error' => 'There is no such flow, or the person who asked may not read it.');
        }

        $graph = ws_flow_graph_of($flow);
        $boxes = array();

        foreach ($graph['nodes'] as $node) {
            $boxes[] = array('id' => $node['id'], 'kind' => $node['kind'], 'label' => $node['label'], 'settings' => $node['config']);
        }

        return array(
            'id'         => (int) $flow['id'],
            'name'       => (string) $flow['name'],
            'status'     => (string) $flow['status'],
            'may_change' => ($flow['access'] === 'edit'),
            'boxes'      => $boxes,
            'arrows'     => $graph['edges'],
            'problems'   => ws_flow_ai_problem_texts($graph),
        );
    }

    $query = trim(is_scalar($args['query'] ?? null) ? (string) $args['query'] : '');
    $found = array();

    foreach (ws_flows_list($asker, 'all') as $flow) {
        if (($query !== '') && (mb_stripos((string) $flow['name'], $query) === false)) {
            continue;
        }

        $found[] = array('id' => (int) $flow['id'], 'name' => (string) $flow['name'], 'status' => (string) $flow['status'], 'may_change' => ($flow['access'] === 'edit'));

        if (count($found) >= 20) {
            break;
        }
    }

    return array('results' => $found, 'note' => empty($found) ? 'No flow matched. Try one shorter word.' : 'Read one with read_flow and its id before you change it.');
}

/**
 * Opens a draft card that changes a flow: its changes made again on that
 * flow's draft as it is now and saved there, where the person goes on and
 * publishes. Refused when the flow is gone, they may no longer change it, or
 * the changes no longer fit it.
 *
 * @param array $viewer
 * @param array $row   ws_ai_changes, pending
 * @param array $draft its snapshot, decoded
 * @return array ok, error, flow_id, left
 */
function ws_flow_draft_patch_open($viewer, $row, $draft)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'flow_id' => 0, 'left' => array());
    };

    $flow = ws_flow_for($viewer, (int) ($draft['base']['flow_id'] ?? 0), 'edit');

    if (!$flow || ($flow['status'] === 'binned')) {
        return $fail(lang('The flow this draft changes is gone, or you may no longer change it.'));
    }

    $patch = ws_flow_patch_apply(ws_flow_graph_of($flow), (array) ($draft['patch'] ?? array()), ws_flow_ai_setting_keys(ws_flow_kind_catalogue()));

    if (!$patch['ok']) {
        return $fail(lang('The flow was changed after this draft was made, and the draft no longer fits it.'));
    }

    $saved = ws_flow_save($viewer, array('graph' => $patch['graph'], 'draft_rev' => (int) $flow['draft_rev']), $flow);

    if (!$saved['ok']) {
        return $fail($saved['error']);
    }

    ws_flow_draft_mark_opened($viewer, $row, $draft, (int) $flow['id']);

    return array('ok' => true, 'error' => '', 'flow_id' => (int) $flow['id'], 'left' => array());
}

// ─── Marked on the canvas ───────────────────────────────────────────────

/** How long a patch opened onto a flow stays marked there unless published sooner (seconds). */
define('WS_FLOW_PATCH_MARK_DAYS', 30);

/**
 * The boxes a patch marks on the canvas (pure), in the order it made them:
 * a box it added 'added', a box whose label or settings it set 'changed' (an
 * added box it then set stays 'added'). A box it removed, or one no longer
 * on the graph, is not marked; arrows alone mark no box.
 *
 * @param mixed $ops   the changes as ws_flow_patch_apply() read them
 * @param array $graph the draft the canvas shows
 * @return array list of {id, mark}
 */
function ws_flow_patch_marks($ops, $graph)
{
    $there = array();
    $marks = array();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if (is_array($node) && isset($node['id'])) {
            $there[(string) $node['id']] = true;
        }
    }

    foreach ((array) $ops as $op) {
        $what = (is_array($op) && is_scalar($op['op'] ?? null)) ? (string) $op['op'] : '';
        $id = (is_array($op) && is_scalar($op['id'] ?? null)) ? (string) $op['id'] : '';

        if ($id === '') {
            continue;
        }

        if ($what === 'add') {
            unset($marks[$id]);
            $marks[$id] = 'added';
        } elseif (($what === 'set') && !isset($marks[$id])) {
            $marks[$id] = 'changed';
        } elseif ($what === 'remove') {
            unset($marks[$id]);
        }
    }

    $list = array();

    foreach ($marks as $id => $mark) {
        if (isset($there[$id])) {
            $list[] = array('id' => (string) $id, 'mark' => $mark);
        }
    }

    return $list;
}

/**
 * The latest patch of Pinegrap AI the person opened onto this flow, marked on
 * its canvas until the flow is published after it: the card's id and the
 * boxes it added or changed that are still on the draft. Null when none.
 *
 * @param array      $viewer
 * @param array      $flow    ws_flow_for()
 * @param array|null $version its live version, ws_flow_version()
 * @param array      $draft   ws_flow_graph_of($flow)
 * @return array|null change_id, boxes (ws_flow_patch_marks())
 */
function ws_flow_ai_marks($viewer, $flow, $version, $draft)
{
    if (!function_exists('ws_changes_ready') || !ws_changes_ready()) {
        return null;
    }

    $since = max(is_array($version) ? (int) $version['published_at'] : 0, time() - WS_FLOW_PATCH_MARK_DAYS * 86400);

    // Only cards of this person opened since; the flow is read from each
    // snapshot, so a few are looked at, newest first.
    $rows = db_items("SELECT id, snapshot FROM ws_ai_changes
        WHERE record_type = 'flow' AND action = 'draft' AND status = 'applied'
            AND decided_by = '" . (int) $viewer['id'] . "' AND decided_at > '" . (int) $since . "'
        ORDER BY decided_at DESC, id DESC LIMIT 20");

    foreach ((array) $rows as $row) {
        $snapshot = json_decode((string) $row['snapshot'], true);

        if (!is_array($snapshot) || ((int) ($snapshot['base']['flow_id'] ?? 0) !== (int) $flow['id'])) {
            continue;
        }

        $boxes = ws_flow_patch_marks($snapshot['patch'] ?? array(), $draft);

        return empty($boxes) ? null : array('change_id' => (int) $row['id'], 'boxes' => $boxes);
    }

    return null;
}
