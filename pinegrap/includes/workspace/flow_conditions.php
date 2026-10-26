<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the condition box of a flow: one rule of a scheduled action's
 * conditions (a working day, days of the week, hours of the day, a count,
 * a record's field), checked with the flow owner's rights when the flow is
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
    return array('workday', 'weekdays', 'hours', 'metric', 'record');
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

    return ws_scheduled_rule_input($viewer, $rule);
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

    if (($rule['type'] ?? '') === 'metric') {
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

    // "Since the last run" of a count reads the last day: a flow's run has
    // no run of the box before it.
    $held = ws_scheduled_rules_hold($owner, array($check['rule']), array('last_run_at' => 0));
    $end = ws_flow_condition_settlement(!empty($held['ok']));
    $text = !empty($held['ok'])
        ? lang(array('string' => 'The condition held: {var:1}.', 'vars' => ws_scheduled_rule_text($owner, $check['rule'])))
        : (string) $held['reason'];

    ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $text);
}
