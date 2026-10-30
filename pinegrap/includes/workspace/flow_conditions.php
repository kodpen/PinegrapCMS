<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the condition box of a flow: one rule of a scheduled action's
 * conditions (a working day, days of the week, hours of the day, a count,
 * a record's field) or a value of the run ({{node.*}}, {{trigger.*}}), checked with the flow owner's rights when the flow is
 * published and again each time the box runs (ws_scheduled_rule_input()),
 * and looked at by ws_scheduled_rules_hold() unchanged. The box leaves on
 * yes when the rule holds and on no when it does not, keeping the reason; a
 * no that no arrow leaves on ends that branch, its boxes skipped, as a
 * scheduled action whose condition does not hold skips its run.
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
 * The rules a condition box may hold: the conditions of a scheduled action.
 * The ones that start it (a time, an event, a join) are the anchor's.
 *
 * @return string[]
 */
function ws_flow_condition_types()
{
    return array('workday', 'weekdays', 'hours', 'metric', 'record', 'value');
}

/**
 * The comparisons a value rule takes: a number's and a text's (the canvas
 * offers each type its own).
 *
 * @return string[]
 */
function ws_flow_condition_value_operators()
{
    return array('eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'not_empty');
}

/**
 * The outcomes an arrow may leave a condition box on; the first is the one
 * an arrow sent without one takes.
 *
 * @return string[]
 */
function ws_flow_condition_outcomes()
{
    return array('yes', 'no');
}

/**
 * The settings of a condition box: its one rule, as the scheduled form sends
 * it. Pure: whether the owner may read the count or the record is asked by
 * ws_flow_condition_check().
 *
 * rule: type (ws_flow_condition_types(), '' for none chosen) and
 *   weekdays: days (1 Monday - 7 Sunday)
 *   hours:    from, to (HH:MM)
 *   metric:   metric, param (a channel or a threshold), op, value (the number
 *             as written; money is turned into minor units by the check)
 *   record:   record, id, field, op, value
 *   value:    ref (one {{node.<id>.<field>}} or {{trigger.<field>}}), op,
 *             value
 *
 * @param mixed $config
 * @return array rule
 */
function ws_flow_condition_normalize($config)
{
    $rule = (is_array($config) && is_array($config['rule'] ?? null)) ? $config['rule'] : array();
    $type = ws_flow_token($rule['type'] ?? '', 16);
    $number = function ($value) {
        return (is_bool($value) || !is_numeric($value)) ? 0 : max(0, (int) $value);
    };

    switch ($type) {
        case 'workday':
            return array('rule' => array('type' => 'workday'));

        case 'weekdays':
            $days = array();

            foreach ((is_array($rule['days'] ?? null) ? $rule['days'] : array()) as $day) {
                if (($number($day) >= 1) && ($number($day) <= 7)) {
                    $days[$number($day)] = $number($day);
                }
            }

            sort($days);

            return array('rule' => array('type' => 'weekdays', 'days' => array_values($days)));

        case 'hours':
            return array('rule' => array('type' => 'hours', 'from' => ws_flow_token($rule['from'] ?? '', 5), 'to' => ws_flow_token($rule['to'] ?? '', 5)));

        case 'metric':
            return array('rule' => array(
                'type'   => 'metric',
                'metric' => ws_flow_token($rule['metric'] ?? '', 40),
                'param'  => $number($rule['param'] ?? 0),
                'op'     => ws_flow_token($rule['op'] ?? 'gt', 16),
                'value'  => ws_flow_token($rule['value'] ?? '', 32),
            ));

        case 'record':
            return array('rule' => array(
                'type'   => 'record',
                'record' => ws_flow_token($rule['record'] ?? '', 32),
                'id'     => $number($rule['id'] ?? 0),
                'field'  => ws_flow_token($rule['field'] ?? '', 64),
                'op'     => ws_flow_token($rule['op'] ?? 'eq', 16),
                'value'  => ws_flow_line($rule['value'] ?? '', 500),
            ));

        case 'value':
            $ref = is_string($rule['ref'] ?? null) ? trim($rule['ref']) : '';
            $op = ws_flow_token($rule['op'] ?? 'eq', 16);

            return array('rule' => array(
                'type'  => 'value',
                'ref'   => (preg_match('/^\{\{(?:node\.[a-z][a-z0-9_]{0,31}|trigger)\.[a-z_][a-z0-9_]{0,31}\}\}$/', $ref) === 1) ? $ref : '',
                'op'    => in_array($op, ws_flow_condition_value_operators(), true) ? $op : 'eq',
                'value' => ws_flow_line($rule['value'] ?? '', 500),
            ));
    }

    return array('rule' => array('type' => ''));
}

/**
 * What a condition box still lacks, in sentences. Pure.
 *
 * @param array  $config ws_flow_condition_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_flow_condition_problems($config, $name)
{
    $rule = $config['rule'];

    switch ($rule['type']) {
        case '':
            return array(lang(array('string' => 'The box {var:1} needs the condition it checks.', 'vars' => $name)));

        case 'weekdays':
            return empty($rule['days']) ? array(lang(array('string' => 'The box {var:1} needs at least one day of the week.', 'vars' => $name))) : array();

        case 'hours':
            return (($rule['from'] === '') || ($rule['to'] === '')) ? array(lang(array('string' => 'The box {var:1} needs the hours it holds in.', 'vars' => $name))) : array();

        case 'metric':
            return ($rule['metric'] === '') ? array(lang(array('string' => 'The box {var:1} needs the count it looks at.', 'vars' => $name))) : array();

        case 'record':
            return (($rule['record'] === '') || ((int) $rule['id'] <= 0) || ($rule['field'] === ''))
                ? array(lang(array('string' => 'The box {var:1} needs the record and the field it looks at.', 'vars' => $name)))
                : array();

        case 'value':
            if ($rule['ref'] === '') {
                return array(lang(array('string' => 'The box {var:1} needs the value it looks at.', 'vars' => $name)));
            }

            return ws_flow_condition_number_missing($rule)
                ? array(lang(array('string' => 'The box {var:1} compares sizes: {var:2} is not a number.', 'vars' => array($name, $rule['value']))))
                : array();
    }

    return array();
}

/**
 * Checks a condition box's rule with somebody's rights, as a scheduled
 * action's condition is checked: the owner as they publish, and again as
 * the box runs.
 *
 * @param array $viewer
 * @param array $config ws_flow_condition_normalize()
 * @return array ok, error, rule
 */
function ws_flow_condition_check($viewer, $config)
{
    $rule = (array) ($config['rule'] ?? array());

    if (!in_array((string) ($rule['type'] ?? ''), ws_flow_condition_types(), true)) {
        return array('ok' => false, 'error' => lang('Choose the condition the box checks.'), 'rule' => null);
    }

    // A value of the run reads no record: the reference check
    // (ws_flow_reference_problems()) answers for the box it names, the
    // trigger's values for the owner's rights (ws_step_trigger_values_for()).
    if ($rule['type'] === 'value') {
        $config = ws_flow_condition_normalize(array('rule' => $rule));

        if ($config['rule']['ref'] === '') {
            return array('ok' => false, 'error' => lang('Choose the value the box looks at.'), 'rule' => null);
        }

        // A flow published before typed numbers were checked fails the box
        // rather than leaving on no without a word.
        return ws_flow_condition_number_missing($config['rule'])
            ? array('ok' => false, 'error' => lang(array('string' => 'The condition compares sizes: {var:1} is not a number.', 'vars' => $config['rule']['value'])), 'rule' => null)
            : array('ok' => true, 'error' => '', 'rule' => $config['rule']);
    }

    return ws_scheduled_rule_input($viewer, $rule);
}

/**
 * Whether a value rule's operator compares sizes (more, less), which hold
 * only between two numbers. Pure.
 *
 * @param string $op
 * @return bool
 */
function ws_flow_condition_sized($op)
{
    return in_array((string) $op, array('gt', 'gte', 'lt', 'lte'), true);
}

/**
 * Whether a value rule compares sizes with a typed value that is no number
 * as the site writes it (ws_flow_typed_number()).
 *
 * @param array $rule ws_flow_condition_normalize() value rule
 * @return bool
 */
function ws_flow_condition_number_missing($rule)
{
    return ws_flow_condition_sized($rule['op'] ?? '') && (ws_flow_typed_number($rule['value'] ?? '') === null);
}

/**
 * How a condition box settles: done, leaving on yes or on no. Pure.
 *
 * @param bool $holds
 * @return array status, outcome
 */
function ws_flow_condition_settlement($holds)
{
    return array('status' => 'done', 'outcome' => $holds ? 'yes' : 'no');
}

/**
 * A condition box's settings as somebody who may not change the flow reads
 * them: the record a rule looks at may be one they may not open, and the
 * value it is compared with a customer's data or a figure of the business.
 * They read the kind of rule, the count or the kind of record and field, and
 * the comparison. Pure.
 *
 * @param array $config ws_flow_condition_normalize()
 * @return array
 */
function ws_flow_condition_masked($config)
{
    $rule = (array) ($config['rule'] ?? array());

    if (($rule['type'] ?? '') === 'record') {
        $rule['id'] = 0;
        $rule['value'] = '';
    }

    if ((($rule['type'] ?? '') === 'metric') || (($rule['type'] ?? '') === 'value')) {
        $rule['value'] = '';
    }

    return array('rule' => $rule);
}

/**
 * Looks at the rule of a condition box this request claimed, with the
 * owner's rights now. A rule the owner may no longer check (a count or a
 * record kind they lost the right to) fails the box, as a step box's
 * refusal does; one that does not hold leaves on no with its reason.
 *
 * @param array $run   ws_flow_runs row
 * @param array $step  ws_flow_run_steps row, running
 * @param array $node  the box
 * @param array $owner the flow's owner, on the staff
 */
function ws_flow_condition_run($run, $step, $node, $owner)
{
    $check = ws_flow_condition_check($owner, $node['config']);

    if (!$check['ok']) {
        ws_flow_step_settle($step, 'running', 'failed', '', $check['error']);
        return;
    }

    if ($check['rule']['type'] === 'value') {
        // Inside a loop, the boxes of its own item and the current item.
        $graph = ws_flow_run_graph($run);
        $values = ws_step_run_values($run, $graph, $owner, ws_flow_run_context($run), $step);
        $held = ws_flow_condition_value_holds($check['rule'], $values, $graph);
        $end = ws_flow_condition_settlement($held['holds']);

        ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $held['text']);
        return;
    }

    // "Since the last run" of a count reads the last day: a flow's run has
    // no run of the box before it.
    $held = ws_scheduled_rules_hold($owner, array($check['rule']), array('last_run_at' => 0));
    $end = ws_flow_condition_settlement(!empty($held['ok']));
    $text = !empty($held['ok'])
        ? lang(array('string' => 'The condition held: {var:1}.', 'vars' => ws_scheduled_rule_text($owner, $check['rule'])))
        : (string) $held['reason'];

    ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $text);
}

/**
 * Whether a value rule holds against the run's values, and the sentence the
 * box keeps: the rule, the value named as the value picker names it, and
 * the value it read. A value the run lacks (a box skipped, a field it did
 * not give) is ''. A comparison of size holds only between two numbers, so
 * an empty value is never "less than 5". Pure.
 *
 * @param array $rule   ws_flow_condition_normalize() value rule
 * @param array $values ws_step_node_values(), the trigger's under WS_STEP_TRIGGER
 * @param array $graph  the graph the run follows, which names the value
 * @return array holds, text
 */
function ws_flow_condition_value_holds($rule, $values, $graph = array())
{
    $current = '';

    if (preg_match(WS_STEP_VALUE_PATTERN, (string) $rule['ref'], $match) === 1) {
        $current = (string) ($values[ws_step_value_box($match)][$match[2]] ?? '');
    }

    $op = (string) $rule['op'];
    $value = (string) $rule['value'];
    $shown = in_array($op, array('empty', 'not_empty'), true) ? '' : $value;

    if (ws_flow_condition_sized($op)) {
        // The run's value is mostly a machine number (1500); the typed one
        // is read as the site writes it (1.000,50).
        $a = is_numeric(trim($current)) ? (float) trim($current) : ws_flow_typed_number($current);
        $b = ws_flow_typed_number($value);
        $holds = ($a !== null) && ($b !== null) && ws_scheduled_compare(ws_flow_derived_number($a), $op, ws_flow_derived_number($b), 'text');
        $shown = ($b === null) ? $value : ws_flow_typed_number_shown($b);
    } else {
        $holds = ws_scheduled_compare($current, $op, $value, 'text');
    }

    $said = trim(ws_flow_condition_value_name((string) $rule['ref'], $graph) . ' ' . (ws_scheduled_operators()[$op] ?? $op) . ' ' . $shown);
    $text = $holds
        ? lang(array('string' => 'The condition held: {var:1} (it was "{var:2}").', 'vars' => array($said, $current)))
        : lang(array('string' => 'The condition did not hold: {var:1} (it was "{var:2}").', 'vars' => array($said, $current)));

    return array('holds' => $holds, 'text' => $text);
}

/**
 * A value a condition reads, named as the value picker's chip names it:
 * "Box name › Value" (a box by its label, a task or approval box by its
 * title, else by its kind; what started the run by its start box). A box
 * the graph no longer has, or a field it does not give, keeps the reference
 * as written. Pure but for a row start box's columns.
 *
 * @param string $ref   {{node.<id>.<field>}} or {{trigger.<field>}}
 * @param array  $graph the graph the run follows
 * @return string
 */
function ws_flow_condition_value_name($ref, $graph)
{
    $fallback = trim(substr((string) $ref, 2, -2));

    if (preg_match(WS_STEP_VALUE_PATTERN, (string) $ref, $match) !== 1) {
        return $fallback;
    }

    $kinds = ws_flow_kinds();
    $trigger = ((string) $match[1] === '');
    $box = null;

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        $kind = (string) ($node['kind'] ?? '');

        if ($trigger ? !empty($kinds[$kind]['trigger']) : ((string) ($node['id'] ?? '') === (string) $match[1])) {
            $box = $node;
            break;
        }
    }

    if ($box === null) {
        return $fallback;
    }

    $label = ws_flow_condition_field_label($box, (string) $match[2], $kinds);

    if ($label === '') {
        return $fallback;
    }

    $kind = (string) $box['kind'];
    $config = (array) ($box['config'] ?? array());
    $name = in_array($kind, array('task', 'task_open', 'approval'), true) ? (string) ($config['title'] ?? '') : '';
    $name = ($name !== '') ? $name : (string) ($box['label'] ?? '');
    $name = ($name !== '') ? $name : (string) ($kinds[$kind]['label'] ?? $kind);

    return $name . ' › ' . $label;
}

/**
 * What the value picker calls a field of a box: text, outcome and status of
 * a box that runs, else the label of the value it declares; '' for a field
 * it does not give. Pure but for a row start box's columns.
 *
 * @param array  $node  a box of the graph
 * @param string $field
 * @param array  $kinds ws_flow_kinds()
 * @return string
 */
function ws_flow_condition_field_label($node, $field, $kinds)
{
    $kind = (string) $node['kind'];
    $config = (array) ($node['config'] ?? array());
    $start = !empty($kinds[$kind]['trigger']);

    if (!$start && ($kind !== 'constants') && ($kind !== 'value')) {
        $own = array('text' => lang('What it did'), 'outcome' => lang('Outcome'), 'status' => lang('Status'));

        if (isset($own[$field])) {
            return $own[$field];
        }
    }

    if ($kind === 'value') {
        return ($field === 'value') ? lang('Value') : '';
    }

    if ($kind === 'constants') {
        $outputs = function_exists('ws_flow_constants_outputs') ? ws_flow_constants_outputs($config) : array();
    } elseif ($start) {
        $outputs = function_exists('ws_flow_trigger_outputs') ? ws_flow_trigger_outputs($kind, $config) : array();
    } elseif (($kind === 'for_each') && function_exists('ws_flow_loop_outputs')) {
        $outputs = ws_flow_loop_outputs($config);
    } else {
        $outputs = (!empty($kinds[$kind]['step']) && function_exists('ws_step_outputs')) ? ws_step_outputs($kind, $config) : ($kinds[$kind]['outputs'] ?? array());
    }

    foreach ($outputs as $output) {
        if ((string) ($output['key'] ?? '') === $field) {
            // A constants row is named by its key, as the picker names it.
            return (string) (($output['label'] ?? '') !== '' ? $output['label'] : $field);
        }
    }

    return '';
}
