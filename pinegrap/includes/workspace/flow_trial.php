<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a flow's draft tried from its start to its end without running
 * it (the trial). The draft is walked as a run walks it (ws_flow_walk()):
 * from the start box, with the record the person chose (an earlier run of
 * the flow, whose start and record are taken over, or a customer for a flow
 * started by hand), each box answered as trying it alone answers it
 * (ws_flow_box_try()), its values carried to the boxes after it.
 *
 * Boxes that read do read: a condition is looked at, a record, a table's
 * rows and a list page's records are read with the flow owner's rights, and
 * what they read is handed on. A box that sends, writes or calls does
 * nothing and hands on only what it would know without the send, so the
 * values only a real send knows stay empty. A Wait is passed at once, its
 * moment handed on. A box whose outcome a person or an answer decides (a
 * task, an approval, Pinegrap AI, an agent) takes the outcome the person
 * chose for it in the trial so far, else its first, and names the others.
 * A loop's boxes are walked once, with its first item.
 *
 * Nothing is stored: the screen sends the record and the outcomes chosen so
 * far, and the answer is the whole walk. Only the flow's owner tries it, as
 * only they try a box: the boxes are looked at with their rights.
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
 * The kinds of box whose outcome a person or an answer decides, so a trial
 * lets the person choose it. Pure.
 *
 * @return string[]
 */
function ws_flow_trial_person_kinds()
{
    return array('task', 'approval', 'ai', 'agent');
}

/**
 * The outcomes the person may choose for a box in a trial, the one taken
 * when none is chosen first; none for a box the trial decides itself.
 * Pure but for lang().
 *
 * @param array $node the box (kind, config)
 * @return string[]
 */
function ws_flow_trial_choices($node)
{
    $kind = (string) ($node['kind'] ?? '');
    $info = ws_flow_kinds()[$kind] ?? null;

    if (($info === null) || !in_array($kind, ws_flow_trial_person_kinds(), true)) {
        return array();
    }

    return array_values(array_diff(ws_flow_node_arrow_outcomes($info, $node), array('always')));
}

/**
 * The outcomes chosen so far as the screen or the API sends them: an object
 * of box id => outcome (or its JSON), or a text of id:outcome pairs
 * separated by commas. Null for anything else. Pure.
 *
 * @param mixed $value
 * @return array|null box id => outcome
 */
function ws_flow_trial_outcomes_read($value)
{
    if (($value === null) || ($value === '')) {
        return array();
    }

    if (is_string($value) && (ltrim($value) !== '') && (ltrim($value)[0] === '{')) {
        $value = json_decode($value, true);
    } elseif (is_string($value)) {
        $pairs = array();

        foreach (explode(',', $value) as $pair) {
            $parts = explode(':', trim($pair));

            if (count($parts) !== 2) {
                return null;
            }

            $pairs[$parts[0]] = $parts[1];
        }

        $value = $pairs;
    }

    if (!is_array($value)) {
        return null;
    }

    $out = array();

    foreach ($value as $id => $outcome) {
        if (!is_string($id) || !is_string($outcome)) {
            return null;
        }

        $out[$id] = $outcome;
    }

    return $out;
}

/**
 * Why the outcomes chosen do not fit the graph, '' when they do: each names
 * a box of the graph whose outcome the person decides, and one of its
 * outcomes. Pure but for lang().
 *
 * @param array $graph    ws_flow_graph_normalize()
 * @param array $outcomes box id => outcome
 * @return string
 */
function ws_flow_trial_outcomes_problem($graph, $outcomes)
{
    foreach ($outcomes as $id => $outcome) {
        $node = ws_flow_graph_node($graph, (string) $id);

        if (!$node || !in_array((string) $outcome, ws_flow_trial_choices($node), true)) {
            return lang(array('string' => 'The box {var:1} cannot be given the outcome {var:2} in a trial.', 'vars' => array((string) $id, (string) $outcome)));
        }
    }

    return '';
}

/**
 * How a box tried in a trial settles, as the run would settle it: one that
 * would fail fails, on its failed arrow where one leaves it; a start goes on;
 * a box the person decides takes the outcome chosen, else its first; an End,
 * or a Wait whose moment has passed and ends the run, is done without an
 * outcome; any other box takes the outcome its try gave. Pure.
 *
 * @param array       $graph  ws_flow_graph_normalize()
 * @param array       $node   the box
 * @param array       $tried  ws_flow_box_try()
 * @param string|null $chosen the outcome the person chose, null for none
 * @return array status (done | failed), outcome, choices
 */
function ws_flow_trial_settle($graph, $node, $tried, $chosen)
{
    $kind = (string) $node['kind'];
    $failed = function ($choices) use ($graph, $node) {
        $end = ws_step_settlement(false, ws_flow_outcome_routed($graph, $node['id'], 'failed'));

        return array('status' => $end['status'], 'outcome' => $end['outcome'], 'choices' => $choices);
    };

    if (empty($tried['ok']) || ((string) ($tried['refusal'] ?? '') !== '')) {
        return $failed(array());
    }

    if (!empty(ws_flow_kinds()[$kind]['trigger'])) {
        return array('status' => 'done', 'outcome' => 'next', 'choices' => array());
    }

    $choices = ws_flow_trial_choices($node);

    if (!empty($choices)) {
        $outcome = in_array($chosen, $choices, true) ? (string) $chosen : $choices[0];

        return ($outcome === 'failed') ? $failed($choices) : array('status' => 'done', 'outcome' => $outcome, 'choices' => $choices);
    }

    $outcome = (string) ($tried['outcome'] ?? '');

    if (($outcome === 'failed') || (($outcome === '') && !in_array($kind, array('end', 'delay'), true))) {
        return $failed(array());
    }

    return array('status' => 'done', 'outcome' => $outcome, 'choices' => array());
}

/**
 * The draft walked from its start box, as a run walks it: the start
 * answered and settled, then each round the boxes ws_flow_walk() makes ready
 * answered with the values of the boxes settled before them and settled,
 * until no box is ready. Each box is walked once. Pure but for $answer.
 *
 * @param array    $graph    ws_flow_graph_normalize()
 * @param array    $start    the start box
 * @param array    $values   what started the run, under WS_STEP_TRIGGER
 * @param array    $outcomes box id => the outcome the person chose
 * @param callable $answer   (box, values) => ws_flow_box_try() plus data (key => value the box hands on)
 * @return array steps (node_id, status, outcome, choices, tried, data; in the order walked), skipped (box ids), state (done | failed)
 */
function ws_flow_trial_walk($graph, $start, $values, $outcomes, $answer)
{
    $state = array();
    $steps = array();
    $skipped = array();
    $queue = array($start);
    $rounds = count((array) ($graph['nodes'] ?? array())) + 1;

    while (!empty($queue) && ($rounds-- > 0)) {
        foreach ($queue as $node) {
            $tried = $answer($node, $values);
            $settled = ws_flow_trial_settle($graph, $node, $tried, $outcomes[(string) $node['id']] ?? null);
            $said = empty($tried['ok']) ? (string) ($tried['error'] ?? '') : ((((string) ($tried['refusal'] ?? '')) !== '') ? (string) $tried['refusal'] : (string) ($tried['said'] ?? ''));
            $data = (array) ($tried['data'] ?? array());

            $state[(string) $node['id']] = array('status' => $settled['status'], 'outcome' => $settled['outcome']);
            $steps[] = $settled + array('node_id' => (string) $node['id'], 'tried' => $tried, 'data' => $data);

            // As ws_step_node_values() keeps a settled box: its sentence,
            // outcome and status, and each single value it gave.
            $values[(string) $node['id']] = array('text' => $said, 'outcome' => $settled['outcome'], 'status' => $settled['status']) + array_map(function ($value) {
                return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }, array_filter($data, 'is_scalar'));
        }

        $walk = ws_flow_walk($graph, $state);
        $queue = array();

        foreach ($walk['skipped'] as $id) {
            $state[$id] = array('status' => 'skipped', 'outcome' => '');
            $skipped[] = $id;
        }

        foreach ($walk['ready'] as $id) {
            $queue[] = ws_flow_graph_node($graph, $id);
        }
    }

    return array('steps' => $steps, 'skipped' => $skipped, 'state' => ws_flow_run_state($graph, $state));
}

/**
 * The values a box of a trial hands on beyond its sentence, read where the
 * box reads: a record, a table's row or rows, a list page's records, with
 * the owner's rights; a Wait's moment; a loop's first item. A box that
 * sends, writes or calls hands on nothing, as only the real send knows it.
 * Reads only.
 *
 * @param array $flow   ws_flow_for()
 * @param array $graph  ws_flow_graph_normalize()
 * @param array $owner  the flow's owner, on the staff
 * @param array $node   the box
 * @param array $values the values of the boxes before it (ws_flow_trial_walk())
 * @return array ok, text (why a read failed), data
 */
function ws_flow_trial_data($flow, $graph, $owner, $node, $values)
{
    $kind = (string) $node['kind'];
    $config = (array) $node['config'];
    $values = ws_flow_pure_values($graph, $values) + $values;
    $out = array('ok' => true, 'text' => '', 'data' => array());

    if (in_array($kind, array('record_get', 'row_get', 'rows_get', 'list_records'), true)) {
        $check = ws_step_check($owner, $kind, $config, ws_flow_starts_on_join($graph));

        if (!$check['ok']) {
            return array('ok' => false, 'text' => (string) $check['error']) + $out;
        }

        $action = ws_step_fill_action($kind, $check['action'], $values);

        if ($kind === 'list_records') {
            $window = !empty($action['only_new']) ? ws_flow_records_window_try($flow) : null;
            $read = ws_flow_records_read(ws_flow_records_page((int) $action['page_id']), $window);
            $done = array('ok' => true, 'data' => ws_flow_records_data($read['items'], count($read['left_out']), WS_FLOW_LOOP_ITEMS_BYTES));
        } elseif ($kind === 'record_get') {
            $done = ws_step_record_get_do($owner, $action);
        } elseif ($kind === 'row_get') {
            $done = ws_step_row_get_do($owner, $action);
        } else {
            $done = ws_step_rows_get_do($owner, $action);
        }

        return array('ok' => !empty($done['ok']), 'text' => empty($done['ok']) ? (string) ($done['text'] ?? '') : '', 'data' => ws_step_result_data($kind, $config, $done));
    }

    if ($kind === 'delay') {
        $delay = ws_flow_delay_normalize($config);
        $until = ($delay['mode'] === 'until')
            ? ws_flow_delay_until_date($delay, ws_flow_derived_fill($delay['at'], $values), time())['until']
            : ws_flow_delay_until($delay, time());

        return array('data' => array('waited_until' => ($until > 0) ? ws_flow_time(max($until, time())) : '')) + $out;
    }

    if ($kind === 'for_each') {
        $count = (ws_flow_loop_source_key($config) === 'count');
        $text = trim((string) ($config[$count ? 'count' : 'list'] ?? ''));

        if (preg_match(WS_STEP_VALUE_PATTERN, $text, $match) === 1) {
            $text = (string) ($values[ws_step_value_box($match)][$match[2]] ?? '');
        }

        $list = $count ? ws_flow_loop_count_items($text) : ws_flow_loop_items($text, (string) ($config['path'] ?? ''));
        $items = !empty($list['ok']) ? (array) $list['items'] : array();

        return array('data' => array('count' => count($items)) + ws_flow_loop_item_values($config, (string) json_encode(array_values($items)), 1)) + $out;
    }

    return $out;
}

/**
 * The record a trial starts with and the run it stands in for: an earlier
 * run of the flow (run_id), whose start, event and record are taken over;
 * else a customer (contact_id) for a flow started by hand, as Run takes one;
 * else nothing. The run stands in only for what the boxes read of a run;
 * it is never written.
 *
 * @param array $viewer
 * @param array $flow  ws_flow_for()
 * @param array $start the draft's start box
 * @param array $owner the flow's owner, on the staff
 * @param array $data  run_id, contact_id
 * @return array ok, error, status, field; run (the stand-in), context, record (type, id; null for none)
 */
function ws_flow_trial_record($viewer, $flow, $start, $owner, $data)
{
    $run_id = (int) ($data['run_id'] ?? 0);
    $contact_id = (int) ($data['contact_id'] ?? 0);

    if (($run_id > 0) && ($contact_id > 0)) {
        return ws_flow_refusal(lang('Try the flow with a run or with a customer, not both.'), 422, 'contact_id');
    }

    $subject = array('', 0);

    if ($run_id > 0) {
        $earlier = ws_flow_run_for($viewer, $run_id);

        if (!$earlier || ((int) $earlier['flow_id'] !== (int) $flow['id'])) {
            return ws_flow_refusal(lang('That run could not be found.'), 404, 'run_id');
        }

        $context = ws_flow_run_context($earlier);
        $subject = array((string) $earlier['subject_type'], (int) $earlier['subject_id']);
    } else {
        $customer = null;

        if ($contact_id > 0) {
            $check = ws_flow_run_contact_check($viewer, $contact_id);

            if (!$check['ok']) {
                return $check;
            }

            $customer = $check['subject'];
            $subject = array('contact', (int) $customer['id']);
        }

        $context = ws_flow_run_context_new($flow, $start, null, $customer);
    }

    $run = array(
        'id'           => 0,
        'flow_id'      => (int) $flow['id'],
        'version_id'   => 0,
        'status'       => 'running',
        'started_by'   => (int) $viewer['id'],
        'actor_id'     => (int) $owner['id'],
        'subject_type' => $subject[0],
        'subject_id'   => $subject[1],
        'context'      => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'depth'        => 0,
    );
    $record = ws_flow_mail_person_source($subject[0], $subject[1], $context);

    return array(
        'ok'      => true,
        'error'   => '',
        'status'  => 200,
        'field'   => '',
        'run'     => $run,
        'context' => $context,
        'record'  => ($record[0] !== '') ? array('type' => $record[0], 'id' => (int) $record[1]) : null,
    );
}

/**
 * A flow's draft tried from its start to its end without running it: the
 * steps in the order walked, each with what the box would do in its Try's
 * words, the outcome it leaves on, the outcomes the person may choose
 * instead and the values it hands on; the boxes not reached; how the run
 * would end. Nothing is sent, written or called. Only the flow's owner tries
 * it, as only they try its boxes.
 *
 * @param array $viewer
 * @param array $flow ws_flow_for() with access
 * @param array $data run_id or contact_id (the record, optional), outcomes (ws_flow_trial_outcomes_read())
 * @return array ok, error, status, field; record (type, id; null), steps
 *               (node_id, kind, label, name, status, outcome, choices, said,
 *               refusal, texts, empty, next, values), skipped, state
 */
function ws_flow_trial($viewer, $flow, $data)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    // The owner alone, as trying a box: the boxes are looked at with the
    // owner's rights, which a colleague the flow is shared with at edit
    // does not hold.
    if (((string) ($flow['access'] ?? '') !== 'edit') || !ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of a flow can try it.'), 403);
    }

    $graph = ws_flow_graph_of($flow);
    $start = ws_flow_trigger_node($graph);

    if (!$start) {
        return ws_flow_refusal(lang('The flow has no start box to try it from.'));
    }

    $outcomes = ws_flow_trial_outcomes_read($data['outcomes'] ?? null);
    $problem = is_array($outcomes) ? ws_flow_trial_outcomes_problem($graph, $outcomes) : lang('The outcomes chosen are not a list of boxes and outcomes.');

    if ($problem !== '') {
        return ws_flow_refusal($problem, 422, 'outcomes');
    }

    $owner = ws_change_viewer_for_id((int) $flow['owner_id']);

    if (!is_array($owner) || !ws_flow_builder($owner)) {
        return ws_flow_refusal(lang('The owner of the flow is no longer on the staff, so the box did not run.'));
    }

    $record = ws_flow_trial_record($viewer, $flow, $start, $owner, $data);

    if (!$record['ok']) {
        return $record;
    }

    // What a run's texts read beside the boxes' values, as a box tried with
    // a run reads them.
    $context = $record['context'];
    $fill = array('viewer' => $owner, 'row' => ws_step_action_row($record['run'], $context, ws_step_run_anchor($context))) + ws_step_trigger_context($context);

    $walk = ws_flow_trial_walk($graph, $start, array(WS_STEP_TRIGGER => ws_step_trigger_values_for($owner, $context)), $outcomes, function ($node, $values) use ($viewer, $flow, $graph, $owner, $record, $fill) {
        $tried = ws_flow_box_try($viewer, $flow, array(
            'node_id' => (string) $node['id'],
            'trial'   => array('run' => $record['run'], 'values' => $values, 'fill' => $fill),
        ));

        if (empty($tried['ok']) || ((string) $tried['refusal'] !== '')) {
            return $tried;
        }

        $read = ws_flow_trial_data($flow, $graph, $owner, $node, $values);

        return $read['ok'] ? array('data' => $read['data']) + $tried : array('refusal' => $read['text']) + $tried;
    });

    $steps = array();

    foreach ($walk['steps'] as $step) {
        $tried = $step['tried'];
        $node = ws_flow_graph_node($graph, $step['node_id']);
        $kind = (string) $node['kind'];

        $steps[] = array(
            'node_id' => $step['node_id'],
            'kind'    => $kind,
            'label'   => (string) (ws_flow_kinds()[$kind]['label'] ?? $kind),
            'name'    => ws_flow_box_name($node),
            'status'  => $step['status'],
            'outcome' => $step['outcome'],
            'choices' => $step['choices'],
            'said'    => (string) ($tried['said'] ?? ''),
            'refusal' => !empty($tried['ok']) ? (string) $tried['refusal'] : (string) $tried['error'],
            'texts'   => (array) ($tried['texts'] ?? array()),
            'empty'   => (array) ($tried['empty'] ?? array()),
            'next'    => (array) ($tried['next'] ?? array()),
            'values'  => array_map(function ($value) {
                return is_scalar($value) ? (string) $value : '';
            }, $step['data']),
        );
    }

    return array(
        'ok'      => true,
        'error'   => '',
        'status'  => 200,
        'field'   => '',
        'record'  => $record['record'],
        'steps'   => $steps,
        'skipped' => $walk['skipped'],
        'state'   => $walk['state'],
    );
}
