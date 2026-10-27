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

// The longest an approval box gives its request before it expires, in hours
// (30 days).
define('WS_STEP_APPROVAL_CLOSES_MAX', 720);

/**
 * The kinds of step box: their name, the scheduled action's step they are
 * (type), the texts of their settings that {{node.*}} values are filled into
 * when the box runs, and of those the ones kept on one line (a value read
 * into an e-mail's subject brings no line break into its header). A task
 * opened without waiting is task_open: the kind task is the box that waits.
 *
 * outputs: the values the box writes into its result's data for the boxes
 * after it (key, type, label; mode: only for a web check of that mode). A
 * report also gives each count it is set to send (ws_step_outputs()).
 *
 * outcomes: the arrows the box may leave on, when they are not done and
 * failed (ws_step_outcomes()): an approval box waits for its request and
 * leaves on how it ended (flow_runs.php, ws_flow_approval_settlement()).
 *
 * @return array kind => label, type, texts, lines, outputs, outcomes (optional)
 */
function ws_step_kinds()
{
    $number = function ($key, $label, $mode = '') {
        return array('key' => $key, 'type' => 'number', 'label' => $label) + (($mode !== '') ? array('mode' => $mode) : array());
    };
    $message = array($number('message_id', lang('Message')));

    return array(
        'post'      => array('label' => lang('Post a message'), 'type' => 'post', 'texts' => array('body'), 'lines' => array(), 'outputs' => $message),
        'email'     => array('label' => lang('Send an e-mail'), 'type' => 'email', 'texts' => array('subject', 'body'), 'lines' => array('subject'),
            'outputs' => array($number('sent', lang('E-mails sent')))),
        'notify'    => array('label' => lang('Let people know'), 'type' => 'notify', 'texts' => array('text'), 'lines' => array('text'),
            'outputs' => array($number('told', lang('People told')))),
        'task_open' => array('label' => lang('Open a task without waiting'), 'type' => 'task', 'texts' => array('title', 'description'), 'lines' => array('title'),
            'outputs' => array($number('task_id', lang('Task')))),
        'webhook'   => array('label' => lang('Call a webhook'), 'type' => 'webhook', 'texts' => array('text'), 'lines' => array(),
            'outputs' => array($number('status_code', lang('Status code')), $number('duration_ms', lang('Time taken (ms)')))),
        'web_check' => array('label' => lang('Check a web address'), 'type' => 'web_check', 'texts' => array(), 'lines' => array(),
            'outputs' => array($number('status_code', lang('Status code'), 'status'), $number('duration_ms', lang('Time taken (ms)'), 'status'),
                $number('days_left', lang('Days the certificate has left'), 'ssl'))),
        'task_digest' => array('label' => lang('E-mail people their tasks'), 'type' => 'task_digest', 'texts' => array('subject', 'intro'), 'lines' => array('subject'),
            'outputs' => array($number('sent', lang('People e-mailed')), $number('tasks', lang('Tasks listed')))),
        'report'    => array('label' => lang('Send a summary of counts'), 'type' => 'report', 'texts' => array('title'), 'lines' => array('title'), 'outputs' => $message),
        'checkin'   => array('label' => lang('Ask for a check-in'), 'type' => 'checkin', 'texts' => array('question'), 'lines' => array('question'), 'outputs' => $message),
        'start_action' => array('label' => lang('Start a scheduled action'), 'type' => 'trigger', 'texts' => array(), 'lines' => array(), 'outputs' => array()),
        'change'    => array('label' => lang('Change a record'), 'type' => 'change', 'texts' => array(), 'lines' => array(),
            'outputs' => array($number('record_id', lang('Record')))),
        'lead'      => array('label' => lang('Bring a customer into a channel'), 'type' => 'lead', 'texts' => array(), 'lines' => array(),
            'outputs' => array($number('channel_id', lang('Channel')))),
        'approval'  => array('label' => lang('Ask for approval'), 'type' => 'approval', 'texts' => array('title', 'text'), 'lines' => array('title'),
            'outputs' => array($number('approval_id', lang('Approval request')), $number('message_id', lang('Message'))),
            'outcomes' => array('approved', 'rejected', 'expired', 'withdrawn', 'failed')),
    );
}

/**
 * The types a box's output may have.
 *
 * @return string[]
 */
function ws_step_output_types()
{
    return array('number', 'text');
}

/**
 * The values one step box gives the boxes after it, as it is set up: a web
 * check those of its mode; a report, beside its message, each count it is
 * set to send, keyed by the count (and its channel or threshold: open tasks
 * of channel 4 is tasks_open_4), labelled from the counts given, else by
 * its key. Pure.
 *
 * @param string $kind
 * @param array  $config ws_step_config_normalize()
 * @param array  $labels count key => label (ws_scheduled_metrics())
 * @return array[] key, type, label; count (a report's: the metric's place)
 */
function ws_step_outputs($kind, $config, $labels = array())
{
    $out = array();
    $mode = (($config['mode'] ?? '') === 'ssl') ? 'ssl' : 'status';

    foreach ((ws_step_kinds()[$kind]['outputs'] ?? array()) as $output) {
        if (!isset($output['mode']) || ($output['mode'] === $mode)) {
            unset($output['mode']);
            $out[] = $output;
        }
    }

    if ($kind !== 'report') {
        return $out;
    }

    $keys = array_column($out, 'key');

    foreach (array_values((array) ($config['metrics'] ?? array())) as $place => $item) {
        $key = (string) ($item['key'] ?? '') . (((int) ($item['param'] ?? 0) > 0) ? '_' . (int) $item['param'] : '');

        // A key {{node.*}} cannot read, or one taken already, is left out.
        if ((preg_match('/^[a-z_][a-z0-9_]{0,31}$/', $key) !== 1) || in_array($key, $keys, true)) {
            continue;
        }

        $keys[] = $key;
        $out[] = array('key' => $key, 'type' => 'number', 'label' => (string) ($labels[$item['key']] ?? $item['key']), 'count' => $place);
    }

    return $out;
}

/**
 * What a step box writes into its result's data: every value it declares
 * (ws_step_outputs()), from what its step answered (ws_scheduled_do(): an
 * id at the top, the rest under data, a report's counts in the order of its
 * metrics). A value the step did not give, or an id of 0, is null. Pure.
 *
 * @param string $kind
 * @param array  $config ws_step_config_normalize()
 * @param array  $done   ws_scheduled_do()
 * @return array key => int|string|null
 */
function ws_step_result_data($kind, $config, $done)
{
    $given = is_array($done['data'] ?? null) ? $done['data'] : array();
    $out = array();

    foreach (ws_step_outputs($kind, $config) as $output) {
        $key = $output['key'];
        $value = isset($output['count']) ? ($given['counts'][$output['count']] ?? null) : ($given[$key] ?? ($done[$key] ?? null));

        if ($output['type'] === 'number') {
            $value = (is_numeric($value) && !is_string($value)) ? (int) $value : null;

            if (($value !== null) && ($value <= 0) && (substr($key, -3) === '_id')) {
                $value = null;
            }
        } else {
            $value = is_scalar($value) ? (string) $value : null;
        }

        $out[$key] = $value;
    }

    return $out;
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
 * post:      channel_id (-1 for the channel joined, in a flow a join
 *            starts), body
 * email:     to (addresses, separated by commas or lines), subject, body,
 *            page_id (a page sent instead of the body, 0 for none)
 * notify:    user_ids (up to 50), text
 * task_open: title, description, assignees (up to 20), channel_id (0 for
 *            none), due_in ('' or 0-3650 days), priority
 * webhook:   url, format (ws_scheduled_webhook_formats()), text
 * web_check: url, mode (status, ssl), redirect, contains, max_ms, days
 * task_digest: mode (mine, channel), channel_id, user_ids (up to 50), only
 *            (ws_scheduled_digest_filters()), subject, intro, missed, ai
 * report:    title, metrics (up to 20 of key, param), channel_id, to
 * checkin:   channel_id, question
 * start_action: target_id (a scheduled action), delay (0-10080 minutes)
 * change:    record (a kind of the change catalogue), action (update,
 *            delete), id, fields (up to 30 of name => value; a value is a
 *            number, yes/no, a text, or a set of texts, a submission's
 *            answers; none for a delete)
 * lead:      private (a channel it opens is private), user_ids (up to 50,
 *            put in a channel it opens)
 * approval:  channel_id, title, text, approvers (up to 50), rule (any, all),
 *            closes_in ('' for no deadline, else 1 to
 *            WS_STEP_APPROVAL_CLOSES_MAX hours from when the box runs)
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
                'channel_id' => (is_numeric($config['channel_id'] ?? 0) && ((int) ($config['channel_id'] ?? 0) === -1)) ? -1 : $number($config['channel_id'] ?? 0),
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

        case 'task_digest':
            $only = ws_flow_token($config['only'] ?? '', 16);

            return array(
                'mode'       => (($config['mode'] ?? '') === 'channel') ? 'channel' : 'mine',
                'channel_id' => $number($config['channel_id'] ?? 0),
                'user_ids'   => $people($config['user_ids'] ?? array(), 50),
                'only'       => in_array($only, array('open', 'week', 'overdue', 'stale'), true) ? $only : 'open',
                'subject'    => ws_flow_line($config['subject'] ?? '', 250),
                'intro'      => ws_flow_lines($config['intro'] ?? '', 2000),
                'missed'     => !empty($config['missed']),
                'ai'         => !empty($config['missed']) && !empty($config['ai']),
            );

        case 'report':
            $metrics = array();

            foreach ((is_array($config['metrics'] ?? null) ? $config['metrics'] : array()) as $item) {
                $key = (string) preg_replace('/[^a-z_]/', '', strtolower(ws_flow_text(is_array($item) ? ($item['key'] ?? '') : $item)));

                if (($key !== '') && (count($metrics) < 20)) {
                    $metrics[] = array('key' => substr($key, 0, 40), 'param' => is_array($item) ? $number($item['param'] ?? 0) : 0);
                }
            }

            $to = $config['to'] ?? '';

            return array(
                'title'      => ws_flow_line($config['title'] ?? '', 160),
                'metrics'    => $metrics,
                'channel_id' => $number($config['channel_id'] ?? 0),
                'to'         => ws_flow_lines(is_array($to) ? implode(', ', array_map('ws_flow_text', $to)) : $to, 2000),
            );

        case 'checkin':
            return array(
                'channel_id' => $number($config['channel_id'] ?? 0),
                'question'   => ws_flow_line($config['question'] ?? '', 500),
            );

        case 'start_action':
            return array(
                'target_id' => $number($config['target_id'] ?? 0),
                'delay'     => min(10080, $number($config['delay'] ?? 0)),
            );

        case 'change':
            $verb = (($config['action'] ?? '') === 'delete') ? 'delete' : 'update';
            $fields = array();

            foreach ((($verb === 'update') && is_array($config['fields'] ?? null)) ? $config['fields'] : array() as $name => $value) {
                if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $name) || (count($fields) >= 30)) {
                    continue;
                }

                if (is_bool($value) || is_int($value) || is_float($value)) {
                    $fields[$name] = $value;
                } elseif (is_string($value)) {
                    $fields[$name] = ws_flow_lines($value, 20000);
                } elseif (is_array($value)) {
                    $set = array();

                    foreach ($value as $key => $item) {
                        if (is_scalar($item) && (count($set) < 50)) {
                            $set[(string) $key] = ws_flow_lines($item, 10000);
                        }
                    }

                    $fields[$name] = $set;
                }
            }

            return array(
                'record' => (string) preg_replace('/[^a-z_]/', '', strtolower(ws_flow_token($config['record'] ?? '', 32))),
                'action' => $verb,
                'id'     => $number($config['id'] ?? 0),
                'fields' => $fields,
            );

        case 'lead':
            return array(
                'private'  => !empty($config['private']),
                'user_ids' => $people($config['user_ids'] ?? array(), 50),
            );

        case 'approval':
            $closes_in = $config['closes_in'] ?? '';

            return array(
                'channel_id' => $number($config['channel_id'] ?? 0),
                'title'      => ws_flow_line($config['title'] ?? '', 255),
                'text'       => ws_flow_lines($config['text'] ?? '', 4000),
                'approvers'  => $people($config['approvers'] ?? array(), 50),
                'rule'       => (($config['rule'] ?? '') === 'all') ? 'all' : 'any',
                'closes_in'  => (is_bool($closes_in) || !is_numeric($closes_in)) ? '' : max(1, min(WS_STEP_APPROVAL_CLOSES_MAX, (int) $closes_in)),
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
 * @param bool   $joined the flow starts when somebody joins a channel
 *                       (ws_flow_starts_on_join()): a post box may write
 *                       in the channel joined
 * @return string[]
 */
function ws_step_config_problems($kind, $config, $name, $joined = false)
{
    $problems = array();

    switch ($kind) {
        case 'post':
            if (((int) $config['channel_id'] === -1) && !$joined) {
                $problems[] = lang(array('string' => 'The box {var:1} writes in the channel joined, but the flow does not start when somebody joins a channel.', 'vars' => $name));
            } elseif (((int) $config['channel_id'] === 0) || ((int) $config['channel_id'] < -1)) {
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

        case 'task_digest':
            if (($config['mode'] === 'channel') && ((int) $config['channel_id'] <= 0)) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the channel whose tasks are sent.', 'vars' => $name));
            }

            break;

        case 'report':
            if (empty($config['metrics'])) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the counts the summary lists.', 'vars' => $name));
            }

            if (((int) $config['channel_id'] <= 0) && (trim($config['to']) === '')) {
                $problems[] = lang(array('string' => 'The box {var:1} needs a channel or e-mail addresses to send the summary to.', 'vars' => $name));
            }

            break;

        case 'checkin':
            if ((int) $config['channel_id'] <= 0) {
                $problems[] = lang(array('string' => 'The box {var:1} needs a channel to write in.', 'vars' => $name));
            }

            break;

        case 'start_action':
            if ((int) $config['target_id'] <= 0) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the scheduled action it starts.', 'vars' => $name));
            }

            break;

        case 'change':
            if ($config['record'] === '') {
                $problems[] = lang(array('string' => 'The box {var:1} needs the kind of record it changes.', 'vars' => $name));
            }

            if ((int) $config['id'] <= 0) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the record it changes.', 'vars' => $name));
            }

            if (($config['action'] === 'update') && empty($config['fields'])) {
                $problems[] = lang(array('string' => 'The box {var:1} needs a field and its new value.', 'vars' => $name));
            }

            break;

        // An approval is asked in a channel, of its people.
        case 'approval':
            if ((int) $config['channel_id'] <= 0) {
                $problems[] = lang(array('string' => 'The box {var:1} needs a channel to ask in.', 'vars' => $name));
            }

            if ($config['title'] === '') {
                $problems[] = lang(array('string' => 'The box {var:1} needs the title of the request.', 'vars' => $name));
            }

            if (empty($config['approvers'])) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the people asked to approve.', 'vars' => $name));
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
 * @param bool   $joined the flow starts when somebody joins a channel: a
 *                       post box may write in the channel joined (-1)
 * @return array ok, error, action
 */
function ws_step_check($viewer, $kind, $config, $joined = false)
{
    if (!isset(ws_step_kinds()[$kind])) {
        return array('ok' => false, 'error' => lang('This kind of box cannot run yet.'), 'action' => null);
    }

    return ws_scheduled_action_input($viewer, ws_step_action($kind, $config), 0, 0, $joined);
}

/**
 * What a box's step reads of what started its run, as a scheduled action's
 * step reads it (ws_scheduled_fill(), ws_events_fill_values()): the event
 * and its payload, the newcomer and the channel they joined, the flow
 * whose box started it ({{source}}, {{source_result}}). The run's
 * other keys (its trigger box, home channel, flow name) are the flow's own.
 * Pure.
 *
 * @param array $context ws_flow_run_context()
 * @return array event, event_payload, newcomer, join_channel_id, source,
 *               source_result, those known
 */
function ws_step_trigger_context($context)
{
    $out = array();

    if ((string) ($context['event'] ?? '') !== '') {
        $out['event'] = (string) $context['event'];

        if (is_array($context['event_payload'] ?? null)) {
            $out['event_payload'] = $context['event_payload'];
        }
    }

    if ((int) ($context['newcomer'] ?? 0) > 0) {
        $out['newcomer'] = (int) $context['newcomer'];
        $out['join_channel_id'] = max(0, (int) ($context['join_channel_id'] ?? 0));
    }

    // A run another flow's box started: that flow and what led to it.
    if ((string) ($context['source'] ?? '') !== '') {
        $out['source'] = (string) $context['source'];
        $out['source_result'] = (string) ($context['source_result'] ?? '');
    }

    return $out;
}

/**
 * Whether the person who started a run may make a change box's change too.
 * A change is made only with the rights of both the flow's owner and the
 * starter, so a colleague who starts someone else's flow has no change made
 * that they may not make themselves. A run an anchor starts has the owner as
 * its starter; the other kinds of box keep the owner's rights alone. A
 * starter who no longer exists or has left the team refuses the change.
 *
 * @param array    $run        ws_flow_runs row: started_by, actor_id
 * @param string   $kind
 * @param array    $config
 * @param callable $viewer_for person id => ws_change_viewer_for_id()
 * @param callable $check      ws_step_check()
 * @return string '' when the change may be made, else why not
 */
function ws_step_starter_refusal($run, $kind, $config, $viewer_for = 'ws_change_viewer_for_id', $check = 'ws_step_check')
{
    $starter_id = (int) ($run['started_by'] ?? 0);

    if (($kind !== 'change') || (($starter_id > 0) && ($starter_id === (int) ($run['actor_id'] ?? 0)))) {
        return '';
    }

    $starter = ($starter_id > 0) ? $viewer_for($starter_id) : null;

    if (is_array($starter) && !empty($starter['member']) && !empty($check($starter, $kind, $config)['ok'])) {
        return '';
    }

    return lang('The person who started the run may not make this change.');
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
 * Each value is made safe for where the text lands (ws_step_fill_value()).
 * Pure.
 *
 * @param string $text
 * @param array  $values ws_step_node_values()
 * @param string $target ws_step_fill_target()
 * @return string
 */
function ws_step_fill_nodes($text, $values, $target = 'text')
{
    $text = (string) $text;

    if (strpos($text, '{{node.') === false) {
        return $text;
    }

    return (string) preg_replace_callback('/\{\{node\.([a-z][a-z0-9_]{0,31})\.([a-z_][a-z0-9_]{0,31})\}\}/', function ($match) use ($values, $target) {
        return ws_step_fill_value((string) ($values[$match[1]][$match[2]] ?? ''), $target);
    }, $text);
}

/**
 * Where a step's texts land, which decides how a value is filled into them:
 * message (a Workspace message, task or approval request, whose
 * <@user:N> and <#order:N> tags would mention people and tag records), chat
 * (a webhook's text read by Slack, Teams or Google Chat), discord, or text
 * (a plain e-mail, an inbox notice, a webhook's JSON body, the task e-mail,
 * which escapes its HTML where it writes it). Pure.
 *
 * @param string $kind   a step kind, or task (the box that waits)
 * @param array  $action ws_scheduled_action_input()
 * @return string message | chat | discord | text
 */
function ws_step_fill_target($kind, $action)
{
    switch ($kind) {
        case 'post':
        case 'task':
        case 'task_open':
        case 'report':
        case 'checkin':
        case 'approval':
            return 'message';

        case 'webhook':
            $format = (string) ($action['format'] ?? '');

            return ($format === 'text') ? 'chat' : (($format === 'discord') ? 'discord' : 'text');
    }

    return 'text';
}

/**
 * One {{node.*}} value as it goes into a text landing at $target. Every "{"
 * is followed by a word joiner (U+2060), so nothing a box gave (a visitor's
 * words, a webhook's answer) is read again as a placeholder by
 * ws_scheduled_fill(), not even with a brace of the text around it. In a
 * message or a chat service so is every "<", so the value's tags, mentions
 * and links (<@user:5>, <!channel>, <url|label>) stay text; Discord's
 * @everyone and @here as well. The HTML is escaped where it is written (the
 * message renderer, the task e-mail) and a webhook's body by json_encode(),
 * so the value is not escaped here: it would show twice escaped. Pure.
 *
 * @param string $value
 * @param string $target ws_step_fill_target()
 * @return string
 */
function ws_step_fill_value($value, $target)
{
    $value = str_replace('{', "{\u{2060}", (string) $value);

    if (in_array($target, array('message', 'chat', 'discord'), true)) {
        $value = str_replace('<', "<\u{2060}", $value);
    }

    if ($target === 'discord') {
        $value = (string) preg_replace('/@(everyone|here)/i', "@\u{2060}\$1", $value);
    }

    return $value;
}

/**
 * A step's texts with the {{node.*}} values of its run filled in, each made
 * safe for where the texts land (ws_step_fill_target()); a line field loses
 * its control characters and line breaks after the fill. Pure.
 *
 * @param string $kind
 * @param array  $action ws_scheduled_action_input()
 * @param array  $values ws_step_node_values()
 * @return array
 */
function ws_step_fill_action($kind, $action, $values)
{
    $info = ws_step_kinds()[$kind] ?? array('texts' => array(), 'lines' => array());
    $target = ws_step_fill_target($kind, $action);

    foreach ($info['texts'] as $key) {
        if (isset($action[$key]) && is_string($action[$key])) {
            $action[$key] = ws_step_fill_nodes($action[$key], $values, $target);

            if (in_array($key, $info['lines'], true)) {
                $action[$key] = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $action[$key]));
            }
        }
    }

    return $action;
}

/**
 * The {{node.<id>.<field>}} of a text the run gives no value for, each
 * once, in the order they first appear: they would be filled in empty.
 * Pure.
 *
 * @param string $text
 * @param array  $values ws_step_node_values()
 * @return string[]
 */
function ws_step_fill_missing($text, $values)
{
    $missing = array();

    if (preg_match_all('/\{\{node\.([a-z][a-z0-9_]{0,31})\.([a-z_][a-z0-9_]{0,31})\}\}/', (string) $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (!isset($values[$match[1]][$match[2]]) && !in_array($match[0], $missing, true)) {
                $missing[] = $match[0];
            }
        }
    }

    return $missing;
}

/**
 * A step's texts as the box would send them, the {{node.*}} values of a run
 * filled in, and the values that run does not give, left empty. Pure.
 *
 * @param string $kind
 * @param array  $action ws_scheduled_action_input()
 * @param array  $values ws_step_node_values(), empty for no run
 * @return array action (filled), texts (list of key, text), empty (placeholders)
 */
function ws_step_try_texts($kind, $action, $values)
{
    $info = ws_step_kinds()[$kind] ?? array('texts' => array());
    $filled = ws_step_fill_action($kind, $action, $values);
    $texts = array();
    $empty = array();

    foreach ($info['texts'] as $key) {
        if (!isset($action[$key]) || !is_string($action[$key])) {
            continue;
        }

        $texts[] = array('key' => $key, 'text' => (string) $filled[$key]);

        foreach (ws_step_fill_missing($action[$key], $values) as $name) {
            if (!in_array($name, $empty, true)) {
                $empty[] = $name;
            }
        }
    }

    return array('action' => $filled, 'texts' => $texts, 'empty' => $empty);
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
 * What a box of a run says it did, as a reader of the run sees it. A change
 * box's sentence names the record and the values it wrote, a condition box's
 * the record, the value and the count it looked at, a start box's the flow
 * it started, which only whoever may change the flow reads
 * (ws_flow_graph_present() keeps them back the same way); everybody else
 * reads that a change was made, or not.
 * Pure.
 *
 * @param string $kind    the box's kind
 * @param string $outcome the step's outcome, '' while it has none
 * @param string $text    ws_flow_result_text()
 * @param bool   $edit    the reader may change the flow
 * @return string
 */
function ws_step_result_shown($kind, $outcome, $text, $edit)
{
    if ($edit || !in_array((string) $kind, array('change', 'condition', 'start_flow'), true) || ((string) $text === '')) {
        return (string) $text;
    }

    // The flow started may be one the reader does not reach: its name stays
    // back.
    if ((string) $kind === 'start_flow') {
        return ((string) $outcome === 'done') ? lang('Another flow was started.') : lang('Another flow could not be started.');
    }

    // A condition's sentence names the record, the value and the count now.
    if ((string) $kind === 'condition') {
        if ((string) $outcome === '') {
            return lang('The condition could not be checked.');
        }

        return ((string) $outcome === 'yes') ? lang('The condition held.') : lang('The condition did not hold.');
    }

    return ((string) $outcome === 'done') ? lang('The change was made.') : lang('The change could not be made.');
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
    $check = ws_step_check($owner, $kind, $node['config'], ws_flow_starts_on_join($graph));

    if (!$check['ok']) {
        if (!ws_flow_retry_wait($step, $node, $check['error'])) {
            $end = ws_step_settlement(false, $routed);
            ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $check['error']);
        }

        return;
    }

    $refusal = ws_step_starter_refusal($run, $kind, $node['config']);

    if ($refusal !== '') {
        if (!ws_flow_retry_wait($step, $node, $refusal)) {
            $end = ws_step_settlement(false, $routed);
            ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $refusal);
        }

        return;
    }

    $values = ws_step_node_values(db_items("SELECT node_id, status, outcome, result FROM ws_flow_run_steps
        WHERE run_id = '" . (int) $run['id'] . "' AND status IN ('done', 'failed', 'skipped')"));
    $context = ws_flow_run_context($run);

    // What ws_scheduled_fill() reads of the action: the flow's name as the
    // run started, and the run's number for {{run}}. A change posts its
    // decision under a scheduled action's card in that action's channel; a
    // box has neither, its run's result says what it changed.
    $row = array(
        'id'          => 0,
        'message_id'  => 0,
        'channel_id'  => 0,
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
    $step_context = array(
        'depth'           => (int) ($run['depth'] ?? 0),
        'idempotency_key' => ws_step_idempotency_key($run['id'], $step['node_id'], $step['pass'] ?? 0),
        'flow'            => array('id' => (int) $run['flow_id'], 'run' => (int) $run['id'], 'box' => (string) $step['node_id']),
        // A scheduled action a box starts carries no source action; the
        // depth of the run still counts in its chain.
        'run_id'          => 0,
    );

    // What started the run: the texts' {{record}}, {{newcomer}} and the
    // rest, a post in the channel joined, a lead box's form submission.
    $step_context += ws_step_trigger_context($context);

    // An approval's deadline counts from when the box runs.
    if (($kind === 'approval') && ($node['config']['closes_in'] ?? '') !== '') {
        $action['closes_at'] = time() + (int) $node['config']['closes_in'] * 3600;
    }

    // An event the step causes starts the actions waiting for it one step
    // further down the run's chain; the depth that stood before is given
    // back after, so a step run inside another run keeps that run's depth.
    $outer_depth = ws_event_depth();
    ws_event_depth((int) ($run['depth'] ?? 0));

    $done = ws_scheduled_do($owner, $row, $action, $step_context);

    ws_event_depth($outer_depth - 1);

    // An approval box asked: it waits for the request to end
    // (flow_runs.php).
    if (($kind === 'approval') && !empty($done['ok'])) {
        ws_flow_approval_wait($step, (int) ($done['approval_id'] ?? 0), (string) ($done['text'] ?? ''), ws_step_result_data($kind, $node['config'], $done));
        return;
    }

    $data = ws_step_result_data($kind, $node['config'], $done);

    // A failure tries again first where the box is set to (its owner's
    // answer to a passing outage); the arrow on failed is taken once the
    // tries are used.
    if (empty($done['ok']) && ws_flow_retry_wait($step, $node, (string) ($done['text'] ?? ''), $data)) {
        return;
    }

    $end = ws_step_settlement(!empty($done['ok']), $routed);

    ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], (string) ($done['text'] ?? ''), $data);
}

/**
 * What one box of a flow's draft would do, in words, without doing it: the
 * box is checked with the owner's rights as publishing and the run check it
 * (ws_step_check(), ws_flow_condition_check(), ws_flow_anchor_check()); a
 * step's texts are filled with the values of a run the person chose, or left
 * empty without one; a condition is looked at now; a start box at a time
 * names its next times. Nothing is sent, written or called. Only the flow's
 * owner tries its boxes, as only they publish it: the checks and sentences
 * use their rights.
 *
 * @param array $viewer
 * @param array $flow ws_flow_for() with access
 * @param array $data node_id, run_id (0 for none)
 * @return array ok, error, status, field; node_id, kind, label, outcome (what
 *               the box would leave on, '' when it would fail), refusal (why
 *               it would fail), said, texts (key, text), empty (values left
 *               empty), next (moments), run_id
 */
function ws_flow_box_try($viewer, $flow, $data)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    // The owner alone, as publishing: the boxes are looked at with the
    // owner's rights, which a colleague the flow is shared with at edit
    // does not hold (a record, a count or a channel of theirs).
    if (((string) ($flow['access'] ?? '') !== 'edit') || !ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of a flow can try its boxes.'), 403);
    }

    $graph = ws_flow_graph_of($flow);
    $node = ws_flow_graph_node($graph, (string) ($data['node_id'] ?? ''));

    if (!$node) {
        return ws_flow_refusal(lang('That box is not in the flow.'), 404, 'node_id');
    }

    // The boxes act with the rights the flow's owner holds when they run.
    $owner = ws_change_viewer_for_id((int) $flow['owner_id']);

    if (!is_array($owner) || !ws_can_schedule($owner)) {
        return ws_flow_refusal(lang('The owner of the flow is no longer on the staff, so the box did not run.'));
    }

    // The values of the boxes a run of the person's choice has settled; a
    // box it has not reached gives none.
    $values = array();
    $run_id = (int) ($data['run_id'] ?? 0);

    if ($run_id > 0) {
        $run = ws_flow_run_for($viewer, $run_id);

        if (!$run || ((int) $run['flow_id'] !== (int) $flow['id'])) {
            return ws_flow_refusal(lang('That run could not be found.'), 404, 'run_id');
        }

        $values = ws_step_node_values(array_filter(ws_flow_run_steps($run_id), function ($row) {
            return ws_flow_step_settled((string) $row['status']);
        }));
    }

    $kind = (string) $node['kind'];
    $answer = array(
        'ok'      => true,
        'error'   => '',
        'status'  => 200,
        'field'   => '',
        'node_id' => (string) $node['id'],
        'kind'    => $kind,
        'label'   => (string) (ws_flow_kinds()[$kind]['label'] ?? $kind),
        'outcome' => '',
        'refusal' => '',
        'said'    => '',
        'texts'   => array(),
        'empty'   => array(),
        'next'    => array(),
        'run_id'  => $run_id,
    );

    if (!empty(ws_flow_kinds()[$kind]['step'])) {
        $check = ws_step_check($owner, $kind, $node['config'], ws_flow_starts_on_join($graph));

        if (!$check['ok']) {
            return array('outcome' => 'failed', 'refusal' => (string) $check['error']) + $answer;
        }

        $tried = ws_step_try_texts($kind, $check['action'], $values);
        $action = $tried['action'];

        // As the run sends it: a notice names the flow it comes from.
        if ($kind === 'notify') {
            $action['text'] = lang(array('string' => 'Flow “{var:1}”: {var:2}', 'vars' => array((string) $flow['name'], $action['text'])));
        }

        return array(
            'outcome' => 'done',
            'said'    => ws_scheduled_action_text($owner, $action),
            'texts'   => $tried['texts'],
            'empty'   => $tried['empty'],
        ) + $answer;
    }

    if ($kind === 'condition') {
        $check = ws_flow_condition_check($owner, $node['config']);

        if (!$check['ok']) {
            return array('refusal' => (string) $check['error']) + $answer;
        }

        $held = ws_scheduled_rules_hold($owner, array($check['rule']), array('last_run_at' => 0));
        $end = ws_flow_condition_settlement(!empty($held['ok']));

        return array(
            'outcome' => $end['outcome'],
            'said'    => !empty($held['ok'])
                ? lang(array('string' => 'The condition held: {var:1}.', 'vars' => ws_scheduled_rule_text($owner, $check['rule'])))
                : (string) $held['reason'],
        ) + $answer;
    }

    if (isset(ws_flow_anchor_kinds()[$kind])) {
        $check = ws_flow_anchor_check($owner, $kind, $node['config']);

        if (!$check['ok']) {
            return array('refusal' => (string) $check['error']) + $answer;
        }

        return array(
            'outcome' => 'next',
            'said'    => ws_scheduled_rule_text($owner, $check['rule']),
            'next'    => array_map('ws_scheduled_moment', ws_flow_anchor_next_times($check['rule'], time(), 3)),
        ) + $answer;
    }

    switch ($kind) {
        case 'manual':
            return array('outcome' => 'next', 'said' => lang('The flow starts when somebody runs it.')) + $answer;

        case 'task':
            $title = ws_step_fill_nodes((string) ($node['config']['title'] ?? ''), $values, ws_step_fill_target('task', array()));

            $hours = function_exists('ws_flow_timeout_hours') ? ws_flow_timeout_hours($node['config']) : 0;

            return array(
                'said'  => ($hours > 0)
                    ? lang(array('string' => 'Opens the task “{var:1}” and waits until it is done or cancelled, at most {var:2} hours.', 'vars' => array($title, $hours)))
                    : lang(array('string' => 'Opens the task “{var:1}” and waits until it is done or cancelled.', 'vars' => $title)),
                'texts' => array(array('key' => 'title', 'text' => $title)),
                'empty' => ws_step_fill_missing((string) ($node['config']['title'] ?? ''), $values),
            ) + $answer;

        case 'end':
            return array('said' => lang('The flow reached its end.')) + $answer;

        // What it would wait, and when the boxes after it would start were
        // it reached now; nothing waits.
        case 'delay':
            $problems = ws_flow_delay_problems($node['config'], ws_flow_box_name($node));

            if (!empty($problems)) {
                return array('refusal' => (string) $problems[0]) + $answer;
            }

            return array(
                'outcome' => 'done',
                'said'    => lang(array('string' => 'Waits {var:1}, then the boxes after it start.', 'vars' => ws_flow_delay_text($node['config']))),
                'next'    => array(ws_scheduled_moment(ws_flow_delay_until($node['config'], time()))),
            ) + $answer;

        // The flow it would start; the chain's depth and the started flow's
        // limits are a run's, so they are not asked here.
        case 'start_flow':
            $chain = ws_flow_chain_check($owner, (int) $flow['id'], $node['config']);

            if (!$chain['ok']) {
                return array('outcome' => 'failed', 'refusal' => (string) $chain['error']) + $answer;
            }

            return array(
                'outcome' => 'done',
                'said'    => lang(array('string' => 'Starts the flow “{var:1}” and goes on without waiting for it.', 'vars' => (string) $chain['flow']['name'])),
            ) + $answer;
    }

    return array('refusal' => lang('This kind of box cannot run yet.')) + $answer;
}
