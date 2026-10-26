<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the steps a flow's boxes carry out: the same steps a scheduled
 * action takes (ws_scheduled_do()), each a kind of box of its own. A step box
 * keeps its settings as the scheduled action's step keeps them, is checked
 * with its owner's rights by ws_scheduled_action_input() when the flow is
 * published and again each time it runs, and is carried out by
 * ws_scheduled_do() unchanged. A box leaves on done when the step went well
 * and on failed when it did not; a failure no arrow leaves on fails the box,
 * and with it the run.
 *
 * A box's texts may read what the boxes before it in the same run did:
 * {{node.<id>.<field>}}, field being text (the box's sentence), outcome,
 * status, or a value the box kept (a message posted keeps message_id). A
 * value of a box the run has not reached, or that it does not keep, is
 * empty.
 *
 * A step box writes that it began (effect began:<kind>) before it does its
 * work. A request that dies half way leaves a box that may have posted or
 * sent already, so such a box is never run a second time: it fails, and the
 * run's owner sees why.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The effect a step box writes as it begins its work.
define('WS_STEP_BEGAN', 'began:');

/**
 * The kinds of step box: their name, the scheduled action's step they are
 * (type), the texts of their settings that {{node.*}} values are filled into
 * when the box runs, and of those the ones kept on one line (a value read
 * into an e-mail's subject brings no line break into its header). A task
 * opened without waiting is task_open: the kind task is the box that waits.
 *
 * @return array kind => label, type, texts, lines
 */
function ws_step_kinds()
{
    return array(
        'post'      => array('label' => lang('Post a message'), 'type' => 'post', 'texts' => array('body'), 'lines' => array()),
        'email'     => array('label' => lang('Send an e-mail'), 'type' => 'email', 'texts' => array('subject', 'body'), 'lines' => array('subject')),
        'notify'    => array('label' => lang('Let people know'), 'type' => 'notify', 'texts' => array('text'), 'lines' => array('text')),
        'task_open' => array('label' => lang('Open a task without waiting'), 'type' => 'task', 'texts' => array('title', 'description'), 'lines' => array('title')),
        'webhook'   => array('label' => lang('Call a webhook'), 'type' => 'webhook', 'texts' => array('text'), 'lines' => array()),
        'web_check' => array('label' => lang('Check a web address'), 'type' => 'web_check', 'texts' => array(), 'lines' => array()),
    );
}

/**
 * The outcomes an arrow may leave a step box on.
 *
 * @return string[]
 */
function ws_step_outcomes()
{
    return array('done', 'failed');
}

/**
 * The settings of a step box, as its kind keeps them; unknown keys are
 * dropped. Pure: whether the owner may write in the channel, or the
 * addresses are good, is asked by ws_step_check().
 *
 * post:      channel_id, body
 * email:     to (addresses, separated by commas or lines), subject, body,
 *            page_id (a page sent instead of the body, 0 for none)
 * notify:    user_ids (up to 50), text
 * task_open: title, description, assignees (up to 20), channel_id (0 for
 *            none), due_in ('' or 0-3650 days), priority
 * webhook:   url, format (ws_scheduled_webhook_formats()), text
 * web_check: url, mode (status, ssl), redirect, contains, max_ms, days
 *
 * @param string $kind
 * @param mixed  $config
 * @return array
 */
function ws_step_config_normalize($kind, $config)
{
    $config = is_array($config) ? $config : array();
    $number = function ($value) {
        return (is_bool($value) || !is_numeric($value)) ? 0 : max(0, (int) $value);
    };
    $people = function ($value, $max) use ($number) {
        $ids = array();

        foreach ((is_array($value) ? $value : array()) as $id) {
            if ($number($id) > 0) {
                $ids[$number($id)] = $number($id);
            }
        }

        return array_slice(array_values($ids), 0, $max);
    };

    switch ($kind) {
        case 'post':
            return array(
                'channel_id' => $number($config['channel_id'] ?? 0),
                'body'       => ws_flow_lines($config['body'] ?? '', 4000),
            );

        case 'email':
            $to = $config['to'] ?? '';

            return array(
                'to'      => ws_flow_lines(is_array($to) ? implode(', ', array_map('ws_flow_text', $to)) : $to, 2000),
                'subject' => ws_flow_line($config['subject'] ?? '', 250),
                'body'    => ws_flow_lines($config['body'] ?? '', 20000),
                'page_id' => $number($config['page_id'] ?? 0),
            );

        case 'notify':
            return array(
                'user_ids' => $people($config['user_ids'] ?? array(), 50),
                'text'     => ws_flow_line($config['text'] ?? '', 500),
            );

        case 'task_open':
            $due_in = $config['due_in'] ?? '';
            $priority = ws_flow_text($config['priority'] ?? '');

            return array(
                'title'       => ws_flow_line($config['title'] ?? '', 200),
                'description' => ws_flow_lines($config['description'] ?? '', 8000),
                'assignees'   => $people($config['assignees'] ?? array(), 20),
                'channel_id'  => $number($config['channel_id'] ?? 0),
                'due_in'      => (is_bool($due_in) || !is_numeric($due_in)) ? '' : max(0, min(3650, (int) $due_in)),
                'priority'    => in_array($priority, array('low', 'normal', 'high', 'urgent'), true) ? $priority : 'normal',
            );

        case 'webhook':
            $format = ws_flow_token($config['format'] ?? '', 16);

            return array(
                'url'    => ws_flow_line($config['url'] ?? '', 2000),
                'format' => in_array($format, array('generic', 'text', 'discord'), true) ? $format : 'generic',
                'text'   => ws_flow_lines($config['text'] ?? '', 4000),
            );

        case 'web_check':
            $days = $number($config['days'] ?? 14);

            return array(
                'url'      => ws_flow_line($config['url'] ?? '', 2000),
                'mode'     => (($config['mode'] ?? '') === 'ssl') ? 'ssl' : 'status',
                'redirect' => !empty($config['redirect']),
                'contains' => ws_flow_line($config['contains'] ?? '', 200),
                'max_ms'   => min(60000, $number($config['max_ms'] ?? 0)),
                'days'     => max(1, min(365, ($days > 0) ? $days : 14)),
            );
    }

    return array();
}

/**
 * What a step box still lacks, in sentences; none for one that may be
 * published as far as its own settings go. Pure.
 *
 * @param string $kind
 * @param array  $config ws_step_config_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_step_config_problems($kind, $config, $name)
{
    $problems = array();

    switch ($kind) {
        case 'post':
            if ((int) $config['channel_id'] <= 0) {
                $problems[] = lang(array('string' => 'The box {var:1} needs a channel to write in.', 'vars' => $name));
            }

            if ($config['body'] === '') {
                $problems[] = lang(array('string' => 'The box {var:1} needs the message to post.', 'vars' => $name));
            }

            break;

        case 'email':
            if (trim($config['to']) === '') {
                $problems[] = lang(array('string' => 'The box {var:1} needs an address to write to.', 'vars' => $name));
            }

            if (((int) $config['page_id'] <= 0) && (($config['subject'] === '') || ($config['body'] === ''))) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the subject and the text of the e-mail.', 'vars' => $name));
            }

            break;

        case 'notify':
            if (empty($config['user_ids'])) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the people to tell.', 'vars' => $name));
            }

            if ($config['text'] === '') {
                $problems[] = lang(array('string' => 'The box {var:1} needs what they are to be told.', 'vars' => $name));
            }

            break;

        case 'task_open':
            if ($config['title'] === '') {
                $problems[] = lang(array('string' => 'The task box {var:1} needs a title.', 'vars' => $name));
            }

            break;

        case 'webhook':
        case 'web_check':
            if (!preg_match('#^https://\S+$#i', $config['url'])) {
                $problems[] = lang(array('string' => 'The box {var:1} needs an address that starts with https://.', 'vars' => $name));
            }

            if (($kind === 'webhook') && ($config['format'] !== 'generic') && ($config['text'] === '')) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the message to send.', 'vars' => $name));
            }

            break;
    }

    return $problems;
}

/**
 * A step box's settings as the scheduled action's step they are.
 *
 * @param string $kind
 * @param array  $config ws_step_config_normalize()
 * @return array
 */
function ws_step_action($kind, $config)
{
    return array('type' => (string) (ws_step_kinds()[$kind]['type'] ?? $kind)) + (array) $config;
}

/**
 * Checks a step box with somebody's rights, as a scheduled action's step is
 * checked: the owner as they publish, and again as the box runs.
 *
 * @param array  $viewer
 * @param string $kind
 * @param array  $config
 * @return array ok, error, action
 */
function ws_step_check($viewer, $kind, $config)
{
    if (!isset(ws_step_kinds()[$kind])) {
        return array('ok' => false, 'error' => lang('This kind of box cannot run yet.'), 'action' => null);
    }

    return ws_scheduled_action_input($viewer, ws_step_action($kind, $config));
}

/**
 * The values the boxes of a run so far give {{node.<id>.<field>}}: each
 * box's sentence, outcome and status, and the values it kept that are a
 * single number, text or yes/no. Pure.
 *
 * @param array[] $rows ws_flow_run_steps rows: node_id, status, outcome, result
 * @return array node id => field => text
 */
function ws_step_node_values($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $result = json_decode((string) ($row['result'] ?? ''), true);
        $values = array();

        foreach ((is_array($result['data'] ?? null) ? $result['data'] : array()) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $values[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        }

        $out[(string) $row['node_id']] = array(
            'text'    => ws_flow_result_text($row['result'] ?? ''),
            'outcome' => (string) ($row['outcome'] ?? ''),
            'status'  => (string) ($row['status'] ?? ''),
        ) + $values;
    }

    return $out;
}

/**
 * Fills the {{node.<id>.<field>}} of a text; one the run has no value for
 * becomes empty. The other double braces are left for ws_scheduled_fill().
 * Pure.
 *
 * @param string $text
 * @param array  $values ws_step_node_values()
 * @return string
 */
function ws_step_fill_nodes($text, $values)
{
    $text = (string) $text;

    if (strpos($text, '{{node.') === false) {
        return $text;
    }

    return (string) preg_replace_callback('/\{\{node\.([a-z][a-z0-9_]{0,31})\.([a-z_][a-z0-9_]{0,31})\}\}/', function ($match) use ($values) {
        return (string) ($values[$match[1]][$match[2]] ?? '');
    }, $text);
}

/**
 * A step's texts with the {{node.*}} values of its run filled in. Pure.
 *
 * @param string $kind
 * @param array  $action ws_scheduled_action_input()
 * @param array  $values ws_step_node_values()
 * @return array
 */
function ws_step_fill_action($kind, $action, $values)
{
    $info = ws_step_kinds()[$kind] ?? array('texts' => array(), 'lines' => array());

    foreach ($info['texts'] as $key) {
        if (isset($action[$key]) && is_string($action[$key])) {
            $action[$key] = ws_step_fill_nodes($action[$key], $values);

            if (in_array($key, $info['lines'], true)) {
                $action[$key] = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $action[$key]));
            }
        }
    }

    return $action;
}

/**
 * The Idempotency-Key a webhook box sends: one per run, box and pass, the
 * same however often the call is made. Pure.
 *
 * @param int    $run_id
 * @param string $node_id
 * @param int    $pass
 * @return string
 */
function ws_step_idempotency_key($run_id, $node_id, $pass)
{
    return 'pg-flow-' . (int) $run_id . '-' . (string) $node_id . '-' . (int) $pass;
}

/**
 * How a step's end settles its box: done on done; a failure leaves on
 * failed where an arrow does, and fails the box where none does. Pure.
 *
 * @param bool $ok
 * @param bool $failed_routed an arrow leaves the box on failed
 * @return array status, outcome
 */
function ws_step_settlement($ok, $failed_routed)
{
    if ($ok) {
        return array('status' => 'done', 'outcome' => 'done');
    }

    return array('status' => $failed_routed ? 'done' : 'failed', 'outcome' => 'failed');
}

/**
 * Carries out a step box this request claimed, with the owner's rights now:
 * writes that it began, checks the step again, fills in the values of the
 * boxes before it and does it.
 *
 * @param array $run   ws_flow_runs row
 * @param array $step  ws_flow_run_steps row, running
 * @param array $node  the box
 * @param array $graph the run's graph
 * @param array $owner the flow's owner, on the staff
 */
function ws_step_run($run, $step, $node, $graph, $owner)
{
    $kind = (string) $node['kind'];

    // Taken by this request alone (the claim); a row another request settled
    // meanwhile is left as it is.
    db("UPDATE ws_flow_run_steps SET effect = '" . e(WS_STEP_BEGAN . $kind) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return;
    }

    $routed = ws_flow_outcome_routed($graph, $node['id'], 'failed');
    $check = ws_step_check($owner, $kind, $node['config']);

    if (!$check['ok']) {
        $end = ws_step_settlement(false, $routed);
        ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $check['error']);
        return;
    }

    $values = ws_step_node_values(db_items("SELECT node_id, status, outcome, result FROM ws_flow_run_steps
        WHERE run_id = '" . (int) $run['id'] . "' AND status IN ('done', 'failed', 'skipped')"));
    $context = ws_flow_run_context($run);

    // What ws_scheduled_fill() reads of the action: the flow's name as the
    // run started, and the run's number for {{run}}.
    $row = array(
        'id'          => 0,
        'message_id'  => 0,
        'name'        => (string) ($context['flow_name'] ?? ''),
        'run_count'   => max(0, (int) $run['id'] - 1),
        'last_run_at' => 0,
    );

    $action = ws_step_fill_action($kind, $check['action'], $values);

    // A line in somebody's inbox names the flow it comes from: the notice
    // has no scheduled action to take its title from.
    if ($kind === 'notify') {
        $action['text'] = lang(array('string' => 'Flow “{var:1}”: {var:2}', 'vars' => array($row['name'], $action['text'])));
    }

    // A webhook carries a key of the box's one try, so the system it calls
    // knows a call it has taken already (Idempotency-Key).
    $done = ws_scheduled_do($owner, $row, $action, array(
        'depth'           => (int) ($run['depth'] ?? 0),
        'idempotency_key' => ws_step_idempotency_key($run['id'], $step['node_id'], $step['pass'] ?? 0),
        'flow'            => array('id' => (int) $run['flow_id'], 'run' => (int) $run['id'], 'box' => (string) $step['node_id']),
    ));
    $end = ws_step_settlement(!empty($done['ok']), $routed);
    $data = array();

    foreach (array('message_id', 'task_id') as $key) {
        if ((int) ($done[$key] ?? 0) > 0) {
            $data[$key] = (int) $done[$key];
        }
    }

    ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], (string) ($done['text'] ?? ''), $data);
}
