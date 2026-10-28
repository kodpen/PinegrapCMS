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
 * and what stops it from being published yet.
 *
 * @param array $viewer
 * @param array $graph ws_flow_graph_normalize()
 * @return array lines (name, label, text), problems (texts)
 */
function ws_flow_draft_outline($viewer, $graph)
{
    $kinds = ws_flow_kinds();
    $lines = array();
    $start = ws_flow_start_text($viewer, $graph);

    if (($start !== null) && ($start !== '')) {
        $lines[] = array('name' => 'start', 'label' => lang('Starts'), 'text' => $start);
    }

    foreach (array_slice($graph['nodes'], 0, WS_FLOW_DRAFT_CARD_BOXES) as $node) {
        $kind = (string) $node['kind'];
        $lines[] = array('name' => 'box_' . $node['id'], 'label' => (string) ($kinds[$kind]['label'] ?? $kind), 'text' => ws_flow_box_name($node));
    }

    $more = count($graph['nodes']) - WS_FLOW_DRAFT_CARD_BOXES;

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
        $outline = ws_flow_draft_outline($viewer, ws_flow_graph_normalize($data['graph'] ?? array()));

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

    $draft['flow_id'] = (int) $saved['flow_id'];

    db("UPDATE ws_ai_changes SET status = 'applied', error = '',
            snapshot = '" . e(json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)) . "',
            decided_by = '" . (int) $viewer['id'] . "', decided_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status = 'pending'");

    if (mysqli_affected_rows(db::$con) === 1) {
        ws_message_touch($row['message_id']);
    }

    return array('ok' => true, 'error' => '', 'flow_id' => (int) $saved['flow_id'], 'left' => $input['left']);
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

    return ws_flow_draft_opened($viewer, $row, $draft, ws_flow_draft_flow_input($draft));
}
