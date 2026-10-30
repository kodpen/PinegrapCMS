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
 * One kind is no scheduled action's step: the MCP box runs a tool of a
 * connected MCP server that reads only, with the owner's account there
 * (else the site's), through the call a tool's form makes
 * (ws_mcp_tool_call()); its answer is a value for the boxes after it. A tool
 * that changes something there asks before each call: the box asks its
 * owner to approve the call in a channel, waits on that request as the
 * approval box does, and makes the call with the owner's own account, never
 * the site's, once they approve it (ws_step_mcp_ask(),
 * ws_step_mcp_approved_call()).
 *
 * Two kinds write a row of a workflow table, no scheduled action's step
 * either: one adds a row, the other changes cells of one, the cells a JSON
 * object by column id whose values may read the run's. They write through
 * the table's own store with the owner's rights, so each cell is checked by
 * its column as any write is; a table whose owner has left the team pauses
 * the box until they are back (ws_step_row_check(), ws_step_row_do()).
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

// The key the values of what started a run ({{trigger.<field>}}) sit under
// among the boxes' values (ws_step_node_values()): never a box id.
define('WS_STEP_TRIGGER', '@trigger');

// The longest an MCP box's arguments are, in characters; the tool is sent
// at most this many bytes of JSON (as from its form, mcp_form.php).
define('WS_STEP_MCP_ARGUMENTS_MAX', 20000);

// The longest a table box's cells are, as JSON text, in characters.
define('WS_STEP_ROW_CELLS_MAX', 20000);

// A value a box's text reads: {{node.<id>.<field>}}, or {{trigger.<field>}}
// (group 1 the box id, empty for the trigger; group 2 the field).
define('WS_STEP_VALUE_PATTERN', '/\{\{(?:node\.([a-z][a-z0-9_]{0,31})|trigger)\.([a-z_][a-z0-9_]{0,31})\}\}/');

/**
 * The kinds of step box: their name, the scheduled action's step they are
 * (type), the texts of their settings that {{node.*}} values are filled into
 * when the box runs, and of those the ones kept on one line (a value read
 * into an e-mail's subject brings no line break into its header). A task
 * opened without waiting is task_open: the kind task is the box that waits.
 * The MCP box and the two table boxes are no scheduled action's step: their
 * type is their own.
 *
 * outputs: the values the box writes into its result's data for the boxes
 * after it (key, type, label; mode: only for a web check of that mode). A
 * report also gives each count it is set to send (ws_step_outputs()).
 *
 * outcomes: the arrows the box may leave on, when they are not done and
 * failed (ws_step_outcomes()): an approval box waits for its request and
 * leaves on how it ended (flow_runs.php, ws_flow_approval_settlement()), as
 * an MCP box does whose call was not approved.
 *
 * @return array kind => label, type, texts, lines, outputs, outcomes (optional)
 */
function ws_step_kinds()
{
    $number = function ($key, $label, $mode = '') {
        return array('key' => $key, 'type' => 'number', 'label' => $label) + (($mode !== '') ? array('mode' => $mode) : array());
    };
    $text = function ($key, $label) {
        return array('key' => $key, 'type' => 'text', 'label' => $label);
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
        'webhook'   => array('label' => lang('Call a webhook'), 'type' => 'webhook', 'texts' => array('text'), 'lines' => array(), 'urls' => array('url'),
            'outputs' => array($number('status_code', lang('Status code')), $number('duration_ms', lang('Time taken (ms)')), $text('body', lang('Answer')))),
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
        'mcp'       => array('label' => lang('Run a tool of an MCP server'), 'type' => 'mcp', 'texts' => array('arguments'), 'lines' => array(),
            'outputs' => array($text('result', lang('Answer')), $number('duration_ms', lang('Time taken (ms)'))),
            'outcomes' => array('done', 'failed', 'rejected', 'expired', 'withdrawn')),
        'row_add'   => array('label' => lang('Add a row to a table'), 'type' => 'row_add', 'texts' => array('cells'), 'lines' => array(),
            'outputs' => array($number('row_id', lang('Row')))),
        'row_set'   => array('label' => lang('Change a row of a table'), 'type' => 'row_set', 'texts' => array('row', 'cells'), 'lines' => array('row'),
            'outputs' => array($number('row_id', lang('Row')))),
    );
}

/**
 * The values of a box kept back from whoever may not change the flow, as
 * its sentence is (ws_step_result_shown()): a change box's record and the
 * run a start-a-flow box started (all its values), a webhook's or an MCP
 * tool's answer, which may carry what the service tells only the flow's
 * owner, the row a table box wrote, in a table the reader may not
 * reach, and what a loop read from such an answer: a For each box's items
 * and an End for each box's list.
 *
 * @return array kind => keys, ['*'] for all
 */
function ws_step_outputs_held()
{
    return array('change' => array('*'), 'start_flow' => array('*'), 'webhook' => array('body'), 'mcp' => array('result'),
        'row_add' => array('*'), 'row_set' => array('*'), 'for_each' => array('items'), 'for_each_end' => array('list'));
}

/**
 * The values a box kept that a reader sees. Pure.
 *
 * @param string $kind
 * @param array  $data key => value, as the result's data holds them
 * @param bool   $edit the reader may change the flow
 * @return array
 */
function ws_step_outputs_shown($kind, $data, $edit)
{
    $held = $edit ? array() : (ws_step_outputs_held()[(string) $kind] ?? array());

    if (in_array('*', $held, true)) {
        return array();
    }

    return array_diff_key($data, array_flip($held));
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
 *            answers; none for a delete), channel_id and closes_in (for a
 *            box that proposes its change, ws_flow_change_asks(): where its
 *            owner is asked to approve it, and the request's deadline as an
 *            approval box's)
 * lead:      private (a channel it opens is private), user_ids (up to 50,
 *            put in a channel it opens)
 * approval:  channel_id, title, text, approvers (up to 50), rule (any, all),
 *            closes_in ('' for no deadline, else 1 to
 *            WS_STEP_APPROVAL_CLOSES_MAX hours from when the box runs)
 * mcp:       server_id, tool (a tool of that server that is turned on),
 *            arguments (a JSON object as text, '' for none; one given as an
 *            object is written as its JSON), channel_id and closes_in (for a
 *            tool that asks first: where the owner is asked to approve each
 *            call, and the request's deadline as an approval box's)
 * row_add:   table_id (a workflow table), cells (a JSON object of column id
 *            => value as text, '' for none; one given as an object is
 *            written as its JSON)
 * row_set:   table_id, row (the row's id, or one value such as
 *            {{trigger.row_id}}), cells
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

            $closes_in = $config['closes_in'] ?? '';

            return array(
                'record'     => (string) preg_replace('/[^a-z_]/', '', strtolower(ws_flow_token($config['record'] ?? '', 32))),
                'action'     => $verb,
                'id'         => $number($config['id'] ?? 0),
                'fields'     => $fields,
                'channel_id' => $number($config['channel_id'] ?? 0),
                'closes_in'  => (is_bool($closes_in) || !is_numeric($closes_in)) ? '' : max(1, min(WS_STEP_APPROVAL_CLOSES_MAX, (int) $closes_in)),
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

        case 'mcp':
            $arguments = $config['arguments'] ?? '';
            $closes_in = $config['closes_in'] ?? '';

            if (is_array($arguments) || is_object($arguments)) {
                $arguments = (string) json_encode((object) $arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return array(
                'server_id'  => $number($config['server_id'] ?? 0),
                'tool'       => ws_flow_line($config['tool'] ?? '', 200),
                'arguments'  => ws_flow_lines(is_scalar($arguments) ? $arguments : '', WS_STEP_MCP_ARGUMENTS_MAX),
                'channel_id' => $number($config['channel_id'] ?? 0),
                'closes_in'  => (is_bool($closes_in) || !is_numeric($closes_in)) ? '' : max(1, min(WS_STEP_APPROVAL_CLOSES_MAX, (int) $closes_in)),
            );

        case 'row_add':
        case 'row_set':
            $cells = $config['cells'] ?? '';

            if (is_array($cells) || is_object($cells)) {
                $cells = (string) json_encode((object) $cells, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $out = array('table_id' => $number($config['table_id'] ?? 0));

            if ($kind === 'row_set') {
                $out['row'] = ws_flow_line($config['row'] ?? '', 200);
            }

            return $out + array('cells' => ws_flow_lines($cells, WS_STEP_ROW_CELLS_MAX));
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

            if (($kind === 'webhook') && (ws_step_url_host_refs($config['url']) !== array())) {
                $problems[] = lang(array('string' => 'The box {var:1} may take a value only in the path or query of its address, never in its host or scheme.', 'vars' => $name));
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

        case 'mcp':
            if (((int) $config['server_id'] <= 0) || ($config['tool'] === '')) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the MCP server and the tool it runs.', 'vars' => $name));
            }

            if (ws_step_mcp_arguments($config['arguments']) === null) {
                $problems[] = lang(array('string' => 'The arguments of the box {var:1} are not a JSON object.', 'vars' => $name));
            }

            break;

        // A table box writes cells, named by their columns' ids, into one
        // table; a change names its row.
        case 'row_add':
        case 'row_set':
            if ((int) $config['table_id'] <= 0) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the table it writes in.', 'vars' => $name));
            }

            if (($kind === 'row_set') && (trim($config['row']) === '')) {
                $problems[] = lang(array('string' => 'The box {var:1} needs the row it changes.', 'vars' => $name));
            } elseif (($kind === 'row_set') && !ws_step_row_names_row($config['row'])) {
                $problems[] = lang(array('string' => 'The box {var:1} names its row by its id or by one value, such as {{trigger.row_id}}.', 'vars' => $name));
            }

            $cells = ws_step_row_cells($config['cells']);

            if ($cells === null) {
                $problems[] = lang(array('string' => 'The cells of the box {var:1} are not a JSON object of column ids and values.', 'vars' => $name));
            } elseif (empty($cells)) {
                $problems[] = lang(array('string' => 'The box {var:1} needs at least one cell to write.', 'vars' => $name));
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
    $config = (array) $config;

    // Where a proposed change is asked is the box's own, no scheduled
    // action's step setting.
    if ($kind === 'change') {
        unset($config['channel_id'], $config['closes_in']);
    }

    return array('type' => (string) (ws_step_kinds()[$kind]['type'] ?? $kind)) + $config;
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
 * @return array ok, error, action; paused for a table box whose table's
 *               owner has left the team (ws_step_row_check())
 */
function ws_step_check($viewer, $kind, $config, $joined = false)
{
    if (!isset(ws_step_kinds()[$kind])) {
        return array('ok' => false, 'error' => lang('This kind of box cannot run yet.'), 'action' => null);
    }

    // An MCP tool is no scheduled action's step: the box checks it itself.
    if ($kind === 'mcp') {
        return ws_step_mcp_check($viewer, ws_step_config_normalize('mcp', $config));
    }

    // Nor is a table box: it writes through the table's store.
    if (in_array($kind, ws_step_row_kinds(), true)) {
        return ws_step_row_check($viewer, $kind, ws_step_config_normalize($kind, $config));
    }

    $check = ws_scheduled_action_input($viewer, ws_step_action($kind, $config), 0, 0, $joined);

    // A role-3 member's change box proposes its change: the change is the
    // one they may make, and they must be able to ask in its channel.
    if ($check['ok'] && ($kind === 'change') && ws_flow_change_asks(ws_flow_builder_kind($viewer))) {
        return ws_step_change_ask_check($viewer, ws_step_config_normalize('change', $config), $check['action']);
    }

    return $check;
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
 * The values of what started a run, {{trigger.<field>}}: every field its
 * start box declares (ws_flow_trigger_outputs(), by the context's trigger
 * kind and event), from the run's context; a field the context lacks, or a
 * value that is not a single number or text, is empty. An event's record
 * and payload only where the owner may see the record now ($sees); its
 * name always. A row box's table, row and cells only where the owner reads
 * the table now ($sees), each cell as ws_flow_row_cell_text() says it. A
 * run started by hand or by another flow's box declares none, whatever it
 * carries. Pure but for a row box's columns when $names lacks them.
 *
 * @param array $context ws_flow_run_context()
 * @param bool  $sees    the owner may see what the event is about
 * @param array $names   newcomer, channel: their names; columns: a row box's table's (ws_step_trigger_values_for())
 * @return array field => text
 */
function ws_step_trigger_values($context, $sees, $names = array())
{
    $kind = (string) ($context['trigger'] ?? '');
    $event = (string) ($context['event'] ?? '');
    $payload = is_array($context['event_payload'] ?? null) ? $context['event_payload'] : array();
    $config = array('event' => $event);

    if ($kind === 'row') {
        $config = array('table_id' => (int) ($payload['table_id'] ?? 0));

        if (is_array($names['columns'] ?? null)) {
            $config['columns'] = $names['columns'];
        }
    }

    $outputs = function_exists('ws_flow_trigger_outputs') ? ws_flow_trigger_outputs($kind, $config) : array();
    $given = array();

    if ($kind === 'event') {
        $record = function_exists('ws_events_record_of') ? ws_events_record_of($event, $payload) : array('', 0);
        $given = $sees ? array('record_id' => ($record[1] > 0) ? $record[1] : null) + $payload : array();
        $given['event'] = $event;
    } elseif ($kind === 'join') {
        $given = array(
            'newcomer_id'   => ((int) ($context['newcomer'] ?? 0) > 0) ? (int) $context['newcomer'] : null,
            'newcomer_name' => $names['newcomer'] ?? null,
            'channel_id'    => ((int) ($context['join_channel_id'] ?? 0) > 0) ? (int) $context['join_channel_id'] : null,
            'channel_name'  => $names['channel'] ?? null,
        );
    } elseif (($kind === 'row') && $sees) {
        $given = array(
            'table_id'   => $payload['table_id'] ?? null,
            'table_name' => $payload['table_name'] ?? null,
            'row_id'     => $payload['row_id'] ?? null,
            'actor_id'   => $payload['actor_id'] ?? null,
        );

        foreach ((array) ($payload['cells'] ?? array()) as $id => $value) {
            $given['cell_' . (int) $id] = function_exists('ws_flow_row_cell_text') ? ws_flow_row_cell_text($value) : '';
        }
    }

    $out = array();

    foreach ($outputs as $output) {
        $value = $given[$output['key']] ?? null;
        $out[$output['key']] = (is_scalar($value) && !is_bool($value)) ? (string) $value : '';
    }

    return $out;
}

/**
 * ws_step_trigger_values() of a run for its owner: whether they may see the
 * event's record now (the right its kind of record needs, and for the
 * workspace's own events the task or the channel, ws_events_creator_sees()),
 * and the names of the newcomer and the channel joined.
 *
 * @param array $owner   the flow's owner, as the workspace sees their rights
 * @param array $context ws_flow_run_context()
 * @return array field => text
 */
function ws_step_trigger_values_for($owner, $context)
{
    $kind = (string) ($context['trigger'] ?? '');
    $sees = false;
    $names = array();

    if (($kind === 'event') && function_exists('ws_events_catalog')) {
        $event = (string) ($context['event'] ?? '');
        $right = (string) (ws_events_catalog()[$event]['right'] ?? '');
        $payload = is_array($context['event_payload'] ?? null) ? $context['event_payload'] : array();
        $sees = ($right !== '') && !empty($owner[$right])
            && (!ws_events_is_own($event) || ws_events_creator_sees((int) ($owner['id'] ?? 0), $event, $payload));
    }

    // A row box: the table read by the owner now, its columns as they are.
    if (($kind === 'row') && function_exists('ws_wtable_flow_check')) {
        $payload = is_array($context['event_payload'] ?? null) ? $context['event_payload'] : array();
        $table_id = (int) ($payload['table_id'] ?? 0);
        $sees = ($table_id > 0) && (ws_wtable_flow_check($owner, $table_id, 'view') === '');
        $names['columns'] = $sees ? array_values(ws_wtable_columns($table_id)) : array();
    }

    if ($kind === 'join') {
        $newcomer = (int) ($context['newcomer'] ?? 0);
        $channel = (int) ($context['join_channel_id'] ?? 0);
        $names = array(
            'newcomer' => ($newcomer > 0) ? ws_person_name($newcomer) : '',
            'channel'  => ($channel > 0) ? (string) db_value("SELECT name FROM ws_channels WHERE id = '" . $channel . "'") : '',
        );
    }

    return ws_step_trigger_values($context, $sees, $names);
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
 * @param callable $check      ws_step_change_reach_check(): the change
 *                             alone, not where a proposed one is asked
 * @return string '' when the change may be made, else why not
 */
function ws_step_starter_refusal($run, $kind, $config, $viewer_for = 'ws_change_viewer_for_id', $check = 'ws_step_change_reach_check')
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
 * Fills the {{node.<id>.<field>}} and {{trigger.<field>}} of a text; one the
 * run has no value for becomes empty. The other double braces are left for
 * ws_scheduled_fill(). Each value is made safe for where the text lands
 * (ws_step_fill_value()). Pure.
 *
 * @param string $text
 * @param array  $values ws_step_node_values(), the trigger's under WS_STEP_TRIGGER
 * @param string $target ws_step_fill_target()
 * @return string
 */
function ws_step_fill_nodes($text, $values, $target = 'text')
{
    $text = (string) $text;

    if ((strpos($text, '{{node.') === false) && (strpos($text, '{{trigger.') === false)) {
        return $text;
    }

    return (string) preg_replace_callback(WS_STEP_VALUE_PATTERN, function ($match) use ($values, $target) {
        return ws_step_fill_value((string) ($values[ws_step_value_box($match)][$match[2]] ?? ''), $target);
    }, $text);
}

/**
 * Where the values of a match of WS_STEP_VALUE_PATTERN sit among a run's
 * values: the box's id, WS_STEP_TRIGGER for {{trigger.<field>}}. Pure.
 *
 * @param string[] $match
 * @return string
 */
function ws_step_value_box($match)
{
    return ((string) ($match[1] ?? '') !== '') ? (string) $match[1] : WS_STEP_TRIGGER;
}

/**
 * Where a step's texts land, which decides how a value is filled into them:
 * message (a Workspace message, task or approval request, whose
 * <@user:N> and <#order:N> tags would mention people and tag records), chat
 * (a webhook's text read by Slack, Teams or Google Chat), discord, or text
 * (a plain e-mail, an inbox notice, a webhook's JSON body, the task e-mail,
 * which escapes its HTML where it writes it), or json (an MCP box's
 * arguments, a table box's cells and row: a value goes in as the inside of
 * a JSON string; a cell is plain data the table's screens escape where they
 * show it, so the value lands as it was given). Pure.
 *
 * @param string $kind   a step kind, or task (the box that waits)
 * @param array  $action ws_scheduled_action_input()
 * @return string message | chat | discord | text | json
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

        case 'mcp':
        case 'row_add':
        case 'row_set':
            return 'json';
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
    // The arguments go to the tool as filled, read by nothing after: the
    // value is the inside of a JSON string, quotes and breaks escaped.
    if ($target === 'json') {
        $json = json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? substr($json, 1, -1) : '';
    }

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

    foreach ($info['urls'] ?? array() as $key) {
        if (isset($action[$key]) && is_string($action[$key])) {
            $action[$key] = ws_step_url_fill($action[$key], $values)['url'];
        }
    }

    return $action;
}

/**
 * Where the host of an https address ends: the length of its scheme and
 * authority (user, host, port), up to the first "/", "?" or "#" after
 * "://"; the whole address when it has none. Pure.
 *
 * @param string $url
 * @return int
 */
function ws_step_url_host_end($url)
{
    $url = (string) $url;
    $at = strpos($url, '://');

    if ($at === false) {
        return strlen($url);
    }

    $end = strcspn($url, '/?#', $at + 3);

    return $at + 3 + $end;
}

/**
 * The {{node.*}} and {{trigger.*}} references in an address's scheme or
 * host, where no value may go: a value there would choose the system the
 * call goes to. Pure.
 *
 * @param string $url
 * @return string[]
 */
function ws_step_url_host_refs($url)
{
    $url = (string) $url;

    if (strpos($url, '{{') === false) {
        return array();
    }

    $head = substr($url, 0, ws_step_url_host_end($url));

    // A reference that starts in the host and runs past its end is in it
    // as well: "https://example.com{{node.a.b}}" has no "/" before it.
    $refs = array();

    if (preg_match_all(WS_STEP_VALUE_PATTERN, $url, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            if ($match[1] < strlen($head)) {
                $refs[] = $match[0];
            }
        }
    }

    // A brace pair the pattern does not read is no value, yet it is no
    // host either.
    if (empty($refs) && (strpos($head, '{{') !== false)) {
        $refs[] = '{{';
    }

    return $refs;
}

/**
 * A webhook address with the values of its run filled into its path or
 * query (decision #3410): each must be digits only and goes in
 * URL-encoded. A value in the scheme or host, or one that is not digits
 * (a text, a negative or decimal number, a box that did not run), is the
 * error, and that reference is filled in empty. Pure.
 *
 * @param string $url
 * @param array  $values ws_step_node_values(), the trigger's under WS_STEP_TRIGGER
 * @return array url, error ('' when every value fits)
 */
function ws_step_url_fill($url, $values)
{
    $url = (string) $url;

    if (ws_step_url_host_refs($url) !== array()) {
        return array('url' => $url, 'error' => lang('A value may go only into the path or query of the address, never into its host or scheme.'));
    }

    $error = '';
    $filled = (string) preg_replace_callback(WS_STEP_VALUE_PATTERN, function ($match) use ($values, &$error) {
        $value = $values[ws_step_value_box($match)][$match[2]] ?? '';
        $value = is_scalar($value) ? (string) $value : '';

        if (!preg_match('/^[0-9]+$/', $value)) {
            if ($error === '') {
                $error = lang(array('string' => 'The address takes only a whole number for {var:1}, and the run gave none.', 'vars' => $match[0]));
            }

            return '';
        }

        return rawurlencode($value);
    }, $url);

    return array('url' => $filled, 'error' => $error);
}

/**
 * Why a box's address may not be called with the values of its run, ''
 * when it may (ws_step_url_fill()); only the kinds whose `urls` take a
 * value (ws_step_kinds()) are read. Pure.
 *
 * @param string $kind
 * @param array  $action ws_scheduled_action_input()
 * @param array  $values ws_step_node_values()
 * @return string
 */
function ws_step_url_refusal($kind, $action, $values)
{
    foreach (ws_step_kinds()[$kind]['urls'] ?? array() as $key) {
        if (isset($action[$key]) && is_string($action[$key])) {
            $error = ws_step_url_fill($action[$key], $values)['error'];

            if ($error !== '') {
                return $error;
            }
        }
    }

    return '';
}

/**
 * The {{node.<id>.<field>}} and {{trigger.<field>}} of a text the run gives
 * no value for, each
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

    if (preg_match_all(WS_STEP_VALUE_PATTERN, (string) $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (!isset($values[ws_step_value_box($match)][$match[2]]) && !in_array($match[0], $missing, true)) {
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
 * The texts of a step box its run fills with ws_scheduled_fill() in
 * ws_scheduled_do() (or the function it hands the kind to), by kind. A
 * check-in's question goes out as written. Pure.
 *
 * @return array kind => list of text keys
 */
function ws_step_scheduled_texts()
{
    return array(
        'post'        => array('body'),
        'email'       => array('subject', 'body'),
        'notify'      => array('text'),
        'task_open'   => array('title', 'description'),
        'webhook'     => array('text'),
        'task_digest' => array('subject', 'intro'),
        'report'      => array('title'),
        'approval'    => array('title', 'text'),
    );
}

/**
 * A tried step's texts (ws_step_try_texts()) with the placeholders its run
 * fills as well: {{record}}, {{newcomer}}, {{source}} and the rest, through
 * $fill, on the texts ws_step_scheduled_texts() names. A placeholder the
 * fill leaves empty joins the empty ones. The values of the boxes before it
 * are already escaped, so they are never read as placeholders, as in a run.
 * Pure but for $fill.
 *
 * @param string   $kind
 * @param array    $tried ws_step_try_texts()
 * @param callable $fill  text => text, ws_scheduled_fill() with the run's context
 * @return array action, texts, empty, as ws_step_try_texts()
 */
function ws_step_try_scheduled($kind, $tried, $fill)
{
    $keys = ws_step_scheduled_texts()[$kind] ?? array();

    foreach ($tried['texts'] as $i => $text) {
        if (!in_array($text['key'], $keys, true)) {
            continue;
        }

        if (preg_match_all('/\{\{[a-z_]+\}\}/', (string) $text['text'], $matches)) {
            foreach (array_unique($matches[0]) as $name) {
                if (!in_array($name, $tried['empty'], true) && ((string) $fill($name) === '')) {
                    $tried['empty'][] = $name;
                }
            }
        }

        $tried['texts'][$i]['text'] = (string) $fill((string) $text['text']);
    }

    foreach ($keys as $key) {
        if (isset($tried['action'][$key]) && is_string($tried['action'][$key])) {
            $tried['action'][$key] = (string) $fill($tried['action'][$key]);
        }
    }

    return $tried;
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
 * the record, the value and the count it looked at (ws_flow_graph_present()
 * keeps them back the same way), a start box's the flow it started, a table
 * box's the table and its columns: only whoever may change the flow reads
 * them; everybody else reads that a change was made, or not.
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
    if ($edit || !in_array((string) $kind, array('change', 'condition', 'start_flow', 'row_add', 'row_set'), true) || ((string) $text === '')) {
        return (string) $text;
    }

    // The table may be one the reader does not reach: its name and its
    // columns stay back.
    if ((string) $kind === 'row_add') {
        return ((string) $outcome === 'done') ? lang('A row was added to a table.') : lang('No row was added to the table.');
    }

    if ((string) $kind === 'row_set') {
        return ((string) $outcome === 'done') ? lang('A row of a table was changed.') : lang('The row of the table was not changed.');
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
 * The scheduled action a step box writes as (what ws_scheduled_fill() and
 * ws_scheduled_do() read of it). A run a converted action started is that
 * action: its id, card, channel, name and runs, so {{run}}, a record's
 * "by the scheduled action", a chain's source and a decision under its card
 * read as before it was converted. Any other run is the flow's name as the
 * run started and the run's number for {{run}}; a change posts its decision
 * under a scheduled action's card in that action's channel, and such a box
 * has neither: its run's result says what it changed. Pure.
 *
 * @param array      $run     ws_flow_runs row
 * @param array      $context ws_flow_run_context()
 * @param array|null $anchor  ws_scheduled() of the context's anchor_id
 * @return array id, message_id, channel_id, name, run_count, last_run_at
 */
function ws_step_action_row($run, $context, $anchor)
{
    if (is_array($anchor) && ws_scheduled_anchor_converted($anchor) && ((int) $anchor['id'] === (int) ($context['anchor_id'] ?? 0))) {
        return array(
            'id'          => (int) $anchor['id'],
            'message_id'  => (int) ($anchor['message_id'] ?? 0),
            'channel_id'  => (int) ($anchor['channel_id'] ?? 0),
            'name'        => (string) $anchor['name'],
            'run_count'   => max(0, (int) ($anchor['run_count'] ?? 0) - 1),
            'last_run_at' => (int) ($anchor['last_run_at'] ?? 0),
        );
    }

    return array(
        'id'          => 0,
        'message_id'  => 0,
        'channel_id'  => 0,
        'name'        => (string) ($context['flow_name'] ?? ''),
        'run_count'   => max(0, (int) $run['id'] - 1),
        'last_run_at' => 0,
    );
}

/**
 * The converted scheduled action a run started from, as its steps count
 * from it: the action's run that started this one has already counted as
 * its last, so "since the last run" counts from the run before it.
 *
 * @param array $context ws_flow_run_context()
 * @return array|null ws_scheduled(), null for a run no action started
 */
function ws_step_run_anchor($context)
{
    $anchor = ((int) ($context['anchor_id'] ?? 0) > 0) ? ws_scheduled((int) $context['anchor_id']) : null;

    if (!is_array($anchor)) {
        return null;
    }

    $anchor['last_run_at'] = (int) db_value("SELECT MAX(started_at) FROM ws_scheduled_runs
        WHERE action_id = '" . (int) $anchor['id'] . "' AND id < '" . (int) ($context['sched_run'] ?? 0) . "'");

    return $anchor;
}

/**
 * The values a box of a run fills into its texts: those of the boxes the run
 * has settled so far, of what started it with the owner's rights now
 * (ws_step_trigger_values_for()), and of the value boxes. A box inside a
 * loop (pass > 0) reads the boxes of its own item and those outside the
 * loops, and its For each box gives the current item (flow_loops.php).
 *
 * @param array      $run     ws_flow_runs row
 * @param array      $graph   the run's graph
 * @param array      $owner   the flow's owner, as the workspace sees their rights
 * @param array      $context ws_flow_run_context()
 * @param array|null $step    the ws_flow_run_steps row of the box reading them
 * @return array ws_step_node_values(), the trigger's under WS_STEP_TRIGGER
 */
function ws_step_run_values($run, $graph, $owner, $context, $step = null)
{
    $pass = (int) ($step['pass'] ?? 0);
    $rows = (array) db_items("SELECT node_id, pass, status, outcome, result FROM ws_flow_run_steps
        WHERE run_id = '" . (int) $run['id'] . "' AND pass IN (0, " . $pass . ") AND status IN ('done', 'failed', 'skipped')
        ORDER BY pass, id");
    $values = ws_step_node_values($rows);

    if (($pass > 0) && function_exists('ws_flow_loops')) {
        $loop = ws_flow_loop_of(ws_flow_loops($graph), (string) $step['node_id']);
        $start = ($loop !== '') ? ws_flow_graph_node($graph, $loop) : null;

        foreach ($rows as $row) {
            if ($start && ((string) $row['node_id'] === $loop) && ((int) $row['pass'] === 0)) {
                $kept = json_decode((string) $row['result'], true)['data']['items'] ?? '';
                $values[$loop] = ws_flow_loop_item_values($start['config'], is_string($kept) ? $kept : '', $pass) + (array) ($values[$loop] ?? array());
            }
        }
    }

    $values[WS_STEP_TRIGGER] = ws_step_trigger_values_for($owner, $context);

    return ws_flow_pure_values($graph, $values) + $values;
}

/**
 * Carries out a step box this request claimed, with the owner's rights now:
 * writes that it began, checks the step again, fills in the values of the
 * boxes before it and does it. An MCP box whose call was approved makes the
 * call instead (ws_step_mcp_approved_call()); a table box whose table's
 * owner has left the team waits for them (ws_step_row_wait()).
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
    $approved = in_array($kind, array('mcp', 'change'), true) ? ws_flow_effect_approved_id((string) ($step['effect'] ?? '')) : 0;

    if (($approved > 0) && ($kind === 'change')) {
        ws_step_change_approved_write($run, $step, $node, $graph, $owner, $approved);
        return;
    }

    if ($approved > 0) {
        ws_step_mcp_approved_call($step, $node, $graph, $owner, $approved);
        return;
    }

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
        if (!empty($check['paused'])) {
            ws_step_row_wait($step, (int) ($node['config']['table_id'] ?? 0), $check['error']);
        } elseif (!ws_flow_retry_wait($step, $node, $check['error'])) {
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

    $context = ws_flow_run_context($run);
    $values = ws_step_run_values($run, $graph, $owner, $context, $step);

    $anchor = ws_step_run_anchor($context);
    $row = ws_step_action_row($run, $context, $anchor);

    // A webhook address takes only digits from the values of its run.
    $refusal = ws_step_url_refusal($kind, $check['action'], $values);

    if ($refusal !== '') {
        $end = ws_step_settlement(false, $routed);
        ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $refusal);

        return;
    }

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
        // A scheduled action a box starts names the converted action whose
        // run this is, else no source action; the depth of the run still
        // counts in its chain.
        'run_id'          => ((int) $row['id'] > 0) ? (int) ($context['sched_run'] ?? 0) : 0,
    );

    // What started the run: the texts' {{record}}, {{newcomer}} and the
    // rest, a post in the channel joined, a lead box's form submission.
    $step_context += ws_step_trigger_context($context);

    // An approval's deadline counts from when the box runs, as does that of
    // the request an MCP box's call asks with.
    if (in_array($kind, array('approval', 'mcp', 'change'), true) && (($node['config']['closes_in'] ?? '') !== '')) {
        $action['closes_at'] = time() + (int) $node['config']['closes_in'] * 3600;
    }

    // An event the step causes starts the actions waiting for it one step
    // further down the run's chain; the depth that stood before is given
    // back after, so a step run inside another run keeps that run's depth.
    $outer_depth = ws_event_depth();
    ws_event_depth((int) ($run['depth'] ?? 0));

    // An MCP tool is no scheduled action's step; one that asks before each
    // call asks the owner to approve it instead of making it. Nor is a
    // table box's row, which the table's store writes; the event it sends
    // carries this depth.
    // A role-3 member's change box proposes its change to its owner and
    // writes nothing now (ws_flow_change_asks()).
    $asks = (($kind === 'mcp') || ($kind === 'change')) && !empty($action['asks']);

    if ($asks && ($kind === 'change')) {
        $done = ws_step_change_ask($owner, $action, (string) $row['name'], (int) ($run['started_by'] ?? 0));
    } elseif ($asks) {
        $done = ws_step_mcp_ask($owner, $action, (string) $row['name'], (int) ($run['started_by'] ?? 0));
    } elseif ($kind === 'mcp') {
        $done = ws_step_mcp_do($owner, $action);
    } elseif (in_array($kind, ws_step_row_kinds(), true)) {
        $done = ws_step_row_do($owner, $action);
    } else {
        $done = ws_scheduled_do($owner, $row, $action, $step_context);
    }

    ws_event_depth($outer_depth - 1);

    // An approval box asked: it waits for the request to end
    // (flow_runs.php). So does an MCP box that asked, its arguments kept
    // beside its values for the call (ws_step_mcp_asked_arguments()), and a
    // change box that proposed its change.
    if ((($kind === 'approval') || $asks) && !empty($done['ok'])) {
        ws_flow_approval_wait($step, (int) ($done['approval_id'] ?? 0), (string) ($done['text'] ?? ''), ws_step_result_data($kind, $node['config'], $done) + array_intersect_key($done, array('call' => true)), $kind);
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

    if (!is_array($owner) || !ws_flow_builder($owner)) {
        return ws_flow_refusal(lang('The owner of the flow is no longer on the staff, so the box did not run.'));
    }

    // A role-3 member tries only a box their own flow may hold.
    $refused = ws_flow_member_box_refusal(ws_flow_builder_kind($owner), $node);

    if ($refused !== '') {
        return ws_flow_refusal($refused);
    }

    // The values of the boxes a run of the person's choice has settled; a
    // box it has not reached gives none. The value boxes give theirs always.
    $values = array();
    $fill = null;
    $run_id = (int) ($data['run_id'] ?? 0);

    if ($run_id > 0) {
        $run = ws_flow_run_for($viewer, $run_id);

        if (!$run || ((int) $run['flow_id'] !== (int) $flow['id'])) {
            return ws_flow_refusal(lang('That run could not be found.'), 404, 'run_id');
        }

        $values = ws_step_node_values(array_filter(ws_flow_run_steps($run_id), function ($row) {
            return ws_flow_step_settled((string) $row['status']);
        }));
        $context = ws_flow_run_context($run);
        $values[WS_STEP_TRIGGER] = ws_step_trigger_values_for($owner, $context);

        // What a run's texts read beside the boxes' values, as ws_step_run()
        // hands it to ws_scheduled_do(): the record, the newcomer, the flow
        // that started it, the date and counts of now with the owner's
        // rights.
        $fill = array('viewer' => $owner, 'row' => ws_step_action_row($run, $context, ws_step_run_anchor($context))) + ws_step_trigger_context($context);
    }

    $values = ws_flow_pure_values($graph, $values) + $values;

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

        // Without a run the address's values are only empty, as the texts'
        // are; with one, it is refused as the run would refuse it.
        $refusal = ($run_id > 0) ? ws_step_url_refusal($kind, $check['action'], $values) : '';

        if ($refusal !== '') {
            return array('outcome' => 'failed', 'refusal' => $refusal) + $answer;
        }

        $tried = ws_step_try_texts($kind, $check['action'], $values);

        if ($fill !== null) {
            $tried = ws_step_try_scheduled($kind, $tried, function ($text) use ($fill) {
                return ws_scheduled_fill($text, $fill);
            });
        }

        $action = $tried['action'];

        // As the run sends it: a notice names the flow it comes from.
        if ($kind === 'notify') {
            $action['text'] = lang(array('string' => 'Flow “{var:1}”: {var:2}', 'vars' => array((string) $flow['name'], $action['text'])));
        }

        if ($kind === 'mcp') {
            $said = ws_step_mcp_text($action);
        } elseif (($kind === 'change') && !empty($action['asks'])) {
            $said = ws_step_change_ask_said(ws_scheduled_action_text($owner, $action), (string) $action['channel_name']);
        } elseif (in_array($kind, ws_step_row_kinds(), true)) {
            $said = ws_step_row_text($action);
        } else {
            $said = ws_scheduled_action_text($owner, $action);
        }

        return array(
            'outcome' => 'done',
            'said'    => $said,
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

        // Its title and description as the run opens the task with them.
        case 'task':
            $texts = ws_flow_task_texts($node['config'], $values, ($fill !== null) ? function ($text) use ($fill) {
                return ws_scheduled_fill($text, $fill);
            } : null);
            $title = $texts['title'];

            $hours = function_exists('ws_flow_timeout_hours') ? ws_flow_timeout_hours($node['config']) : 0;

            return array(
                'said'  => ($hours > 0)
                    ? lang(array('string' => 'Opens the task “{var:1}” and waits until it is done or cancelled, at most {var:2} hours.', 'vars' => array($title, $hours)))
                    : lang(array('string' => 'Opens the task “{var:1}” and waits until it is done or cancelled.', 'vars' => $title)),
                'texts' => $texts['texts'],
                'empty' => $texts['empty'],
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

/* ---------------------------------------------------------------------------
   A tool of an MCP server (the box mcp)
   --------------------------------------------------------------------------- */

/**
 * An MCP box's arguments as the tool gets them: '' for none, else a JSON
 * object. A {{node.*}} or {{trigger.*}} value goes in as the inside of a
 * JSON string (ws_step_fill_value()), so the text is looked at with each
 * one empty: one written outside quotes leaves no object. Pure.
 *
 * @param string $text
 * @return array|null name => value, null when the text is not an object
 */
function ws_step_mcp_arguments($text)
{
    $text = trim((string) preg_replace(WS_STEP_VALUE_PATTERN, '', (string) $text));

    if ($text === '') {
        return array();
    }

    if ($text[0] !== '{') {
        return null;
    }

    $decoded = json_decode($text, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * Why a flow may not run a tool of a server, '' when it may: the tool is
 * turned on there. One that reads runs at once (ws_mcp_form_tools()); one
 * that asks before each call (ws_step_mcp_tool_asks()) waits for its
 * owner's approval of each call. Pure.
 *
 * @param array  $server ws_mcp_servers() row: alias, auth_kind, tools, tool_rules
 * @param string $tool
 * @return string
 */
function ws_step_mcp_tool_problem($server, $tool)
{
    if (!isset(ws_mcp_tools_on($server)[$tool])) {
        return lang(array('string' => 'The tool {var:1} is not turned on at @{var:2}.', 'vars' => array($tool, $server['alias'])));
    }

    return '';
}

/**
 * Does a tool of a server ask before each call: turned on as one that
 * changes something there (rule approve), which only a server people sign
 * in to with their own accounts has (ws_mcp_tools_on())? Pure.
 *
 * @param array  $server
 * @param string $tool
 * @return bool
 */
function ws_step_mcp_tool_asks($server, $tool)
{
    return !empty(ws_mcp_tools_on($server)[$tool]['approve']);
}

/**
 * What an MCP box's form on the flow canvas offers (ws_flow_js_config()):
 * each server the person may call, with the tools that read there
 * (ws_mcp_form_tools()) and the names of those that ask before each call,
 * which the form does not offer. A server without a tool that reads is left
 * out. Pure.
 *
 * @param array[]  $servers ws_mcp_active_servers()
 * @param callable $usable  function (array $server): bool, whether the
 *                          person may call it (an account on an OAuth server)
 * @return array[] id, alias, name, tools (name, description), asks
 */
function ws_step_mcp_form_servers($servers, $usable)
{
    $out = array();

    foreach ($servers as $server) {
        if (!$usable($server)) {
            continue;
        }

        $tools = array();

        foreach (ws_mcp_form_tools($server) as $name => $tool) {
            $about = trim((string) ($tool['description'] ?? ''));

            $tools[] = array('name' => (string) $name, 'description' => mb_substr($about, 0, 200, 'UTF-8'));
        }

        if (empty($tools)) {
            continue;
        }

        $asks = array();

        foreach (ws_mcp_tools_on($server) as $name => $tool) {
            if (!empty($tool['approve'])) {
                $asks[] = (string) $name;
            }
        }

        sort($asks);
        $out[] = array('id' => (int) $server['id'], 'alias' => (string) $server['alias'], 'name' => (string) ($server['name'] ?? ''), 'tools' => $tools, 'asks' => $asks);
    }

    return $out;
}

/**
 * Checks an MCP box with somebody's rights: the server turned on, a tool
 * of it a flow may run (ws_step_mcp_tool_problem()), an account to call it
 * with on a server people sign in to. A tool that asks before each call is
 * called only with the owner's own account, never the site's: the owner
 * approves each call, which acts in their name there (mcp.php). While the
 * call is still to be asked ($asking), the box needs a channel where the
 * owner may be asked to approve it (ws_approval_input()).
 *
 * @param array $viewer the flow's owner
 * @param array $config ws_step_config_normalize('mcp')
 * @param bool  $asking false for a call already approved, asked no more
 * @return array ok, error, action (type mcp, server_id, alias, tool,
 *               arguments, asks, channel_id and channel_name where it asks)
 */
function ws_step_mcp_check($viewer, $config, $asking = true)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'action' => null);
    };

    if (!function_exists('ws_mcp_ready') || !ws_mcp_ready()) {
        return $refuse(lang('The database has to be updated first.'));
    }

    $server = ws_mcp_active_servers()[(int) $config['server_id']] ?? null;

    if (!$server) {
        return $refuse(lang('That MCP server could not be found.'));
    }

    $tool = (string) $config['tool'];
    $problem = ws_step_mcp_tool_problem($server, $tool);

    if ($problem !== '') {
        return $refuse($problem);
    }

    if (ws_step_mcp_arguments($config['arguments']) === null) {
        return $refuse(lang('The arguments are not a JSON object.'));
    }

    $asks = ws_step_mcp_tool_asks($server, $tool);

    if ($asks && !ws_mcp_oauth_account($server, (int) $viewer['id'], true)) {
        return $refuse(lang(array('string' => 'Connect your own account to @{var:1} to run it: the call runs with it.', 'vars' => $server['alias'])));
    }

    if (!$asks && ($server['auth_kind'] === 'oauth') && !ws_mcp_oauth_account($server, (int) $viewer['id'])) {
        return $refuse(lang(array('string' => 'Connect your own account to @{var:1} first: nobody\'s account is connected there for you.', 'vars' => $server['alias'])));
    }

    $channel = null;

    if ($asks && $asking) {
        $channel = ((int) ($config['channel_id'] ?? 0) > 0) ? ws_channel((int) $config['channel_id']) : null;

        if (!$channel) {
            return $refuse(lang(array('string' => 'The tool {var:1} of @{var:2} asks before each call: choose the channel where you approve each call.', 'vars' => array($tool, $server['alias']))));
        }

        // The owner alone approves, in a channel they may write in.
        $checked = ws_approval_input($viewer, $channel, array(
            'title'     => ws_step_mcp_ask_title($tool, $server['alias']),
            'approvers' => array((int) $viewer['id']),
            'rule'      => 'any',
        ));

        if (!$checked['ok']) {
            return $refuse($checked['error']);
        }
    }

    return array(
        'ok'     => true,
        'error'  => '',
        'action' => array(
            'type'         => 'mcp',
            'server_id'    => (int) $server['id'],
            'alias'        => (string) $server['alias'],
            'tool'         => $tool,
            'arguments'    => (string) $config['arguments'],
            'asks'         => $asks,
            'channel_id'   => $channel ? (int) $channel['id'] : 0,
            'channel_name' => $channel ? (string) $channel['name'] : '',
        ),
    );
}

/**
 * What an MCP box does, in words. Pure.
 *
 * @param array $action ws_step_mcp_check()
 * @return string
 */
function ws_step_mcp_text($action)
{
    if (!empty($action['asks'])) {
        return lang(array('string' => 'Asks you in #{var:3} to approve the call, then runs the tool {var:1} of @{var:2} with your own account and keeps its answer.', 'vars' => array((string) $action['tool'], (string) $action['alias'], (string) ($action['channel_name'] ?? ''))));
    }

    return lang(array('string' => 'Runs the tool {var:1} of @{var:2} and keeps its answer.', 'vars' => array((string) $action['tool'], (string) $action['alias'])));
}

/**
 * The answer an MCP box keeps of a tool's result: its text (the parts the
 * form shows, else the structured content), cut as a webhook's answer is
 * (ws_scheduled_webhook_body()); null for none. Pure.
 *
 * @param mixed $result pg_mcp_client_call_tool() result
 * @return string|null
 */
function ws_step_mcp_result($result)
{
    $text = pg_mcp_client_result_text(is_array($result) ? $result : array(), WS_SCHEDULED_WEBHOOK_BODY_BYTES);

    return ($text === '') ? null : ws_scheduled_webhook_body($text);
}

/**
 * Runs an MCP box's tool with the flow owner's own account on the server
 * when they have one, else with the site's, through the call the form
 * makes (ws_mcp_tool_call()); a tool that asks before each call with their
 * own account only. The box's arguments are filled already
 * (ws_step_fill_action()). An answer the tool marks as an error fails the
 * box, its text kept.
 *
 * @param array $owner  the flow's owner, checked (ws_step_mcp_check())
 * @param array $action ws_step_mcp_check(), filled
 * @return array ok, text, data (result, duration_ms)
 */
function ws_step_mcp_do($owner, $action)
{
    $json = trim((string) $action['arguments']);
    $json = ($json === '') ? '{}' : $json;

    // Filled, the values may make it longer than the box keeps.
    if (!is_object(json_decode($json)) || (strlen($json) > WS_STEP_MCP_ARGUMENTS_MAX)) {
        return array('ok' => false, 'text' => lang('The arguments are not a JSON object.'), 'data' => array());
    }

    $server = ws_mcp_server((int) $action['server_id']);

    if (!$server) {
        return array('ok' => false, 'text' => lang('That MCP server could not be found.'), 'data' => array());
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_SCREEN_TIMEOUT * 3 + 30);
    }

    $call = ws_mcp_tool_call((int) $owner['id'], $server, (string) $action['tool'], $json, !empty($action['asks']));

    if (!$call['called']) {
        return array('ok' => false, 'text' => $call['error'], 'data' => array());
    }

    log_activity(lang(array('string' => 'ran the tool {var:1} of the MCP server @{var:2} in a flow', 'vars' => array((string) $action['tool'], $server['alias']))), (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $owner['id'] . "'"));

    $data = array('result' => null, 'duration_ms' => (int) $call['duration_ms']);

    if (!$call['reached']) {
        return array('ok' => false, 'text' => $call['error'], 'data' => $data);
    }

    $data['result'] = ws_step_mcp_result($call['out']['result'] ?? null);

    if (!empty($call['out']['is_error'])) {
        return array('ok' => false, 'text' => lang(array('string' => 'The tool {var:1} of @{var:2} answered with an error.', 'vars' => array((string) $action['tool'], $server['alias']))), 'data' => $data);
    }

    return array('ok' => true, 'text' => lang(array('string' => 'The tool {var:1} of @{var:2} answered.', 'vars' => array((string) $action['tool'], $server['alias']))), 'data' => $data);
}

/**
 * The title of the request an MCP box's call asks with: the tool and the
 * server. Pure.
 *
 * @param string $tool
 * @param string $alias the server's alias
 * @return string
 */
function ws_step_mcp_ask_title($tool, $alias)
{
    return lang(array('string' => 'Run the tool {var:1} of @{var:2}', 'vars' => array((string) $tool, (string) $alias)));
}

/**
 * The words of the request an MCP box's call asks with: the flow, who
 * approves it, who started the run when somebody else did, and the
 * arguments as filled, as JSON in a block. They are shown, not sent: a tag
 * in them stays text (<@user:5> mentions nobody) and a fence ends no block.
 * Arguments that do not fit the room are cut at a character, and the words
 * say how long they are. Pure.
 *
 * @param string $json    the arguments, a JSON object ('' for none)
 * @param string $flow    the flow's name
 * @param string $owner   who approves the call, by name
 * @param string $starter who started the run, '' when the owner did
 * @param int    $room    the most characters the words may take
 * @return string
 */
function ws_step_mcp_ask_text($json, $flow, $owner, $starter, $room)
{
    $json = trim((string) $json);
    $decoded = json_decode(($json === '') ? '{}' : $json);
    $pretty = ($decoded === null) ? $json : (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $text = function ($value) {
        return str_replace('<', "<\u{2060}", (string) $value);
    };
    $shown = $text(str_replace('```', "'''", $pretty));

    $intro = ((string) $starter !== '')
        ? lang(array('string' => 'Flow “{var:1}”, started by {var:3}, waits for {var:2} to approve this call. It runs with their own account.', 'vars' => array($text($flow), $text($owner), $text($starter))))
        : lang(array('string' => 'Flow “{var:1}” waits for {var:2} to approve this call. It runs with their own account.', 'vars' => array($text($flow), $text($owner))));
    $whole = $intro . "\n```\n" . $shown . "\n```";

    if (mb_strlen($whole) <= (int) $room) {
        return $whole;
    }

    $head = $intro . "\n" . lang(array('string' => 'Only the start of the arguments fits here; the call sends all {var:1} characters of them.', 'vars' => mb_strlen($json))) . "\n```\n";

    return $head . mb_substr($shown, 0, max(0, (int) $room - mb_strlen($head) - 5)) . "…\n```";
}

/**
 * Asks the owner to approve the call of an MCP box whose tool asks before
 * each call: a request in the box's channel (approvals.php), in the owner's
 * name, the owner its only approver. A request tells nobody of their own
 * asking, so the owner is told here. The arguments go back with the answer
 * (call), to be kept with the waiting box: what is approved is what is
 * sent. Nothing is called.
 *
 * @param array  $owner      the flow's owner, checked (ws_step_mcp_check())
 * @param array  $action     ws_step_mcp_check(), filled, with closes_at
 * @param string $flow_name  the flow's name, as the run started
 * @param int    $starter_id who started the run
 * @return array ok, text, data; approval_id, message_id, call (arguments)
 */
function ws_step_mcp_ask($owner, $action, $flow_name, $starter_id)
{
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'data' => array());
    };

    $json = trim((string) $action['arguments']);
    $json = ($json === '') ? '{}' : $json;

    if (!is_object(json_decode($json)) || (strlen($json) > WS_STEP_MCP_ARGUMENTS_MAX)) {
        return $fail(lang('The arguments are not a JSON object.'));
    }

    $channel = ws_channel((int) $action['channel_id']);

    if (!$channel) {
        return $fail(lang('The channel to write in is gone.'));
    }

    $title = ws_step_mcp_ask_title((string) $action['tool'], (string) $action['alias']);
    $starter = ((int) $starter_id !== (int) $owner['id']) ? ws_person_name((int) $starter_id) : '';

    // The message is the bold title, a blank line and these words.
    $created = ws_approval_create($owner, $channel, array(
        'title'     => $title,
        'text'      => ws_step_mcp_ask_text($json, $flow_name, ws_person_name((int) $owner['id']), $starter, WS_MESSAGE_MAX - mb_strlen($title) - 6),
        'approvers' => array((int) $owner['id']),
        'rule'      => 'any',
        'closes_at' => (int) ($action['closes_at'] ?? 0),
    ));

    if (!$created['ok']) {
        return $fail(lang(array('string' => 'Could not ask for approval in #{var:1}: {var:2}', 'vars' => array($channel['name'], $created['error']))));
    }

    ws_notify((int) $owner['id'], 'approval_requested', array('channel_id' => (int) $channel['id'], 'message_id' => (int) $created['message_id'], 'actor_id' => (int) $owner['id']));

    return array(
        'ok'          => true,
        'text'        => lang(array('string' => 'asked for approval in #{var:1}', 'vars' => $channel['name'])),
        'data'        => array(),
        'approval_id' => (int) $created['approval_id'],
        'message_id'  => (int) $created['message_id'],
        'call'        => array('arguments' => $json),
    );
}

/**
 * The arguments an MCP box asked its owner to approve, as its waiting
 * result keeps them (ws_step_mcp_ask()): one level down in its data, where
 * neither a reader of the run (ws_api_flow_step_outputs()) nor a later box
 * (ws_step_node_values()) reads them. Pure.
 *
 * @param mixed $stored ws_flow_run_steps.result
 * @return string|null a JSON object, null when none is kept
 */
function ws_step_mcp_asked_arguments($stored)
{
    $result = is_string($stored) ? json_decode($stored, true) : null;
    $json = $result['data']['call']['arguments'] ?? null;

    return (is_string($json) && is_object(json_decode($json))) ? $json : null;
}

/**
 * Makes the call of an MCP box its owner approved (ws_flow_approved_ready()),
 * claimed by this request. It writes that it began first, from the
 * approved effect only, so the call is made once whatever stops half way
 * (ws_flow_stale_action()); checks the box again with the owner's rights,
 * asking nobody now; sends the arguments that were approved, never filled
 * again; and calls with the owner's own account. It then settles as an MCP
 * box does; a failure tries again where the box is set to, and a new try
 * asks again.
 *
 * @param array $step        ws_flow_run_steps row, running
 * @param array $node        the box
 * @param array $graph       the run's graph
 * @param array $owner       the flow's owner, on the staff
 * @param int   $approval_id the request that approved the call
 */
function ws_step_mcp_approved_call($step, $node, $graph, $owner, $approval_id)
{
    db("UPDATE ws_flow_run_steps SET effect = '" . e(WS_STEP_BEGAN . 'mcp') . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running' AND effect = '" . e(ws_flow_approved_key($approval_id)) . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return;
    }

    $routed = ws_flow_outcome_routed($graph, $node['id'], 'failed');
    $check = ws_step_mcp_check($owner, ws_step_config_normalize('mcp', $node['config']), false);
    $arguments = ws_step_mcp_asked_arguments($step['result'] ?? '');

    if (!$check['ok']) {
        $done = array('ok' => false, 'text' => (string) $check['error'], 'data' => array());
    } elseif ($arguments === null) {
        $done = array('ok' => false, 'text' => lang('The arguments the call was approved with could not be read.'), 'data' => array());
    } else {
        $done = ws_step_mcp_do($owner, array('arguments' => $arguments, 'asks' => true) + $check['action']);
    }

    $data = ws_step_result_data('mcp', $node['config'], $done);

    if (empty($done['ok']) && ws_flow_retry_wait($step, $node, (string) $done['text'], $data)) {
        return;
    }

    $end = ws_step_settlement(!empty($done['ok']), $routed);

    ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], (string) $done['text'], $data);
}

/* ---------------------------------------------------------------------------
   A change a role-3 member's flow proposes (the box change, asking)
   --------------------------------------------------------------------------- */

/**
 * Checks a change box a role-3 member's flow holds (ws_flow_change_asks()):
 * the change is already checked with their rights (ws_scheduled_action_input());
 * here, the channel where they are asked to approve it, as an MCP box whose
 * tool asks first: there, in a channel they may write in, the owner alone
 * approves.
 *
 * @param array $viewer the owner
 * @param array $config ws_step_config_normalize('change')
 * @param array $action ws_scheduled_action_input() of the change
 * @return array ok, error, action (with asks, channel_id, channel_name)
 */
function ws_step_change_ask_check($viewer, $config, $action)
{
    $channel = ((int) ($config['channel_id'] ?? 0) > 0) ? ws_channel((int) $config['channel_id']) : null;

    if (!$channel) {
        return array('ok' => false, 'error' => lang('In your own flows a change is proposed for your approval first: choose the channel to ask in.'), 'action' => null);
    }

    $checked = ws_approval_input($viewer, $channel, array(
        'title'     => lang('Approve a change of one of your flows'),
        'approvers' => array((int) $viewer['id']),
        'rule'      => 'any',
    ));

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'action' => null);
    }

    return array('ok' => true, 'error' => '', 'action' => array(
        'asks'         => true,
        'channel_id'   => (int) $channel['id'],
        'channel_name' => (string) $channel['name'],
    ) + (array) $action);
}

/**
 * Checks the change of a change box with somebody's rights, without where
 * it is asked: what the run's starter must be able to make
 * (ws_step_starter_refusal()), and what is checked again as an approved
 * change is written.
 *
 * @param array  $viewer
 * @param string $kind change
 * @param array  $config
 * @return array ok, error, action
 */
function ws_step_change_reach_check($viewer, $kind, $config)
{
    return ws_scheduled_action_input($viewer, ws_step_action($kind, $config), 0, 0);
}

/**
 * The words of the request a change box asks its owner with: the flow, who
 * approves it, who started the run when somebody else did, and the change
 * as the box would make it. They are shown, not sent: a tag in them stays
 * text (<@user:5> mentions nobody). Words that do not fit the room are cut
 * at a character. Pure.
 *
 * @param string $change  the change in words (ws_scheduled_action_text())
 * @param string $flow    the flow's name
 * @param string $owner   who approves the change, by name
 * @param string $starter who started the run, '' when the owner did
 * @param int    $room    the most characters the words may take
 * @return string
 */
function ws_step_change_ask_text($change, $flow, $owner, $starter, $room)
{
    $text = function ($value) {
        return str_replace('<', "<\u{2060}", (string) $value);
    };

    $intro = ((string) $starter !== '')
        ? lang(array('string' => 'Flow “{var:1}”, started by {var:3}, proposes this change and waits for {var:2} to approve it. It is made with their rights.', 'vars' => array($text($flow), $text($owner), $text($starter))))
        : lang(array('string' => 'Flow “{var:1}” proposes this change and waits for {var:2} to approve it. It is made with their rights.', 'vars' => array($text($flow), $text($owner))));
    $whole = $intro . "\n" . $text($change);

    if (mb_strlen($whole) <= (int) $room) {
        return $whole;
    }

    return mb_substr($whole, 0, max(0, (int) $room - 1)) . '…';
}

/**
 * What trying a change box that proposes its change says. Pure.
 *
 * @param string $change  the change in words
 * @param string $channel the channel's name
 * @return string
 */
function ws_step_change_ask_said($change, $channel)
{
    return lang(array('string' => 'Asks you to approve this change in #{var:1} and writes nothing until you do: {var:2}', 'vars' => array((string) $channel, (string) $change)));
}

/**
 * Proposes the change of a role-3 member's change box: a request in the
 * box's channel (approvals.php), in the owner's name, the owner its only
 * approver, as an MCP box's call asks. A request tells nobody of their own
 * asking, so the owner is told here. Nothing is written: the box waits for
 * the request (ws_flow_approval_wait()) and writes once it is approved
 * (ws_step_change_approved_write()).
 *
 * @param array  $owner      the flow's owner, checked (ws_step_change_ask_check())
 * @param array  $action     the checked change, with asks, channel_id, closes_at
 * @param string $flow_name  the flow's name, as the run started
 * @param int    $starter_id who started the run
 * @return array ok, text, data; approval_id, message_id
 */
function ws_step_change_ask($owner, $action, $flow_name, $starter_id)
{
    $channel = ws_channel((int) $action['channel_id']);

    if (!$channel) {
        return array('ok' => false, 'text' => lang('The channel to write in is gone.'), 'data' => array());
    }

    $title = mb_substr(lang(array('string' => 'Approve the change: {var:1}', 'vars' => ws_scheduled_record_label($owner, $action['record'], $action['id']))), 0, 255);
    $starter = ((int) $starter_id !== (int) $owner['id']) ? ws_person_name((int) $starter_id) : '';

    // The message is the bold title, a blank line and these words.
    $created = ws_approval_create($owner, $channel, array(
        'title'     => $title,
        'text'      => ws_step_change_ask_text(ws_scheduled_action_text($owner, $action), $flow_name, ws_person_name((int) $owner['id']), $starter, WS_MESSAGE_MAX - mb_strlen($title) - 6),
        'approvers' => array((int) $owner['id']),
        'rule'      => 'any',
        'closes_at' => (int) ($action['closes_at'] ?? 0),
    ));

    if (!$created['ok']) {
        return array('ok' => false, 'text' => lang(array('string' => 'Could not ask for approval in #{var:1}: {var:2}', 'vars' => array($channel['name'], $created['error']))), 'data' => array());
    }

    ws_notify((int) $owner['id'], 'approval_requested', array('channel_id' => (int) $channel['id'], 'message_id' => (int) $created['message_id'], 'actor_id' => (int) $owner['id']));

    return array(
        'ok'          => true,
        'text'        => lang(array('string' => 'proposed the change for approval in #{var:1}', 'vars' => $channel['name'])),
        'data'        => array(),
        'approval_id' => (int) $created['approval_id'],
        'message_id'  => (int) $created['message_id'],
    );
}

/**
 * Writes the change a role-3 member's change box proposed, once approved
 * (ws_flow_approved_ready()), claimed by this request. It writes that it
 * began first, from the approved effect only, so the change is written once
 * whatever stops half way (ws_flow_stale_action()); checks the change again
 * with the owner's rights as they are now (and the starter's), so a right
 * lost meanwhile stops it; and writes it as a change box does
 * (ws_scheduled_change()). A failure tries again where the box is set to,
 * and a new try asks again.
 *
 * @param array $run         ws_flow_runs row
 * @param array $step        ws_flow_run_steps row, running
 * @param array $node        the box
 * @param array $graph       the run's graph
 * @param array $owner       the flow's owner
 * @param int   $approval_id the request that approved the change
 */
function ws_step_change_approved_write($run, $step, $node, $graph, $owner, $approval_id)
{
    db("UPDATE ws_flow_run_steps SET effect = '" . e(WS_STEP_BEGAN . 'change') . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running' AND effect = '" . e(ws_flow_approved_key($approval_id)) . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return;
    }

    $routed = ws_flow_outcome_routed($graph, $node['id'], 'failed');
    $check = ws_step_change_reach_check($owner, 'change', $node['config']);
    $refusal = $check['ok'] ? ws_step_starter_refusal($run, 'change', $node['config']) : '';

    if (!$check['ok']) {
        $done = array('ok' => false, 'text' => (string) $check['error'], 'data' => array());
    } elseif ($refusal !== '') {
        $done = array('ok' => false, 'text' => $refusal, 'data' => array());
    } else {
        $context = ws_flow_run_context($run);
        $row = ws_step_action_row($run, $context, ws_step_run_anchor($context));

        $outer_depth = ws_event_depth();
        ws_event_depth((int) ($run['depth'] ?? 0));
        $done = ws_scheduled_do($owner, $row, $check['action'], ws_step_trigger_context($context));
        ws_event_depth($outer_depth - 1);
    }

    $data = ws_step_result_data('change', $node['config'], $done);

    if (empty($done['ok']) && ws_flow_retry_wait($step, $node, (string) ($done['text'] ?? ''), $data)) {
        return;
    }

    $end = ws_step_settlement(!empty($done['ok']), $routed);

    ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], (string) ($done['text'] ?? ''), $data);
}

/* ---------------------------------------------------------------------------
   A row of a workflow table (the boxes row_add and row_set)
   --------------------------------------------------------------------------- */

/**
 * The kinds of box that write a row of a workflow table: one adds a row,
 * the other changes cells of one.
 *
 * @return string[]
 */
function ws_step_row_kinds()
{
    return array('row_add', 'row_set');
}

/**
 * A table box's cells, column id => value as written: none for '', else a
 * JSON object whose keys are column ids. A {{node.*}} or {{trigger.*}} value
 * goes in as the inside of a JSON string (ws_step_fill_value()), so the text
 * is also looked at with each one empty: one written outside quotes leaves
 * no object. A record link is an object of its own, {"kind": …, "id": …}.
 * Pure.
 *
 * @param string $text
 * @return array|null column id => value, null when the text is no such object
 */
function ws_step_row_cells($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return array();
    }

    $bare = trim((string) preg_replace(WS_STEP_VALUE_PATTERN, '', $text));
    $cells = json_decode($text, true);

    if (($bare === '') || ($bare[0] !== '{') || !is_array(json_decode($bare, true)) || !is_array($cells)) {
        return null;
    }

    $out = array();

    foreach ($cells as $id => $value) {
        if (!preg_match('/^[1-9][0-9]{0,9}$/', (string) $id)) {
            return null;
        }

        $out[(string) $id] = $value;
    }

    return $out;
}

/**
 * Does a cell's value read a value of the run ({{node.*}}, {{trigger.*}}),
 * known only as the box runs? A record link's id may. Pure.
 *
 * @param mixed $value
 * @return bool
 */
function ws_step_row_value_in($value)
{
    if (is_array($value)) {
        foreach ($value as $item) {
            if (ws_step_row_value_in($item)) {
                return true;
            }
        }

        return false;
    }

    return is_string($value) && (preg_match(WS_STEP_VALUE_PATTERN, $value) === 1);
}

/**
 * The row a row text names once it is filled in: its id, 0 for none. Pure.
 *
 * @param string $text
 * @return int
 */
function ws_step_row_id($text)
{
    $text = trim((string) $text);

    return preg_match('/^[1-9][0-9]{0,9}$/', $text) ? (int) $text : 0;
}

/**
 * Does a change box's row text name a row: its id, or one value of the run
 * alone, such as the row that started the flow ({{trigger.row_id}}) or the
 * one an earlier box added? Pure.
 *
 * @param string $text
 * @return bool
 */
function ws_step_row_names_row($text)
{
    $text = trim((string) $text);

    if (ws_step_row_id($text) > 0) {
        return true;
    }

    return (preg_replace(WS_STEP_VALUE_PATTERN, '', $text, -1, $count) === '') && ($count === 1);
}

/**
 * A cell's code (ws_wtable_cell_check()) in a box's words: as the tables
 * screen says it, but money in minor units and a record link as the object
 * the box writes. Pure but for lang().
 *
 * @param string $code
 * @return string
 */
function ws_step_row_cell_reason($code)
{
    switch ((string) $code) {
        case 'not_minor_units':
            return lang('Money is written in minor units: 1250 for 12.50.');

        case 'not_link':
            return lang('A record link is written as {"kind": "contact", "id": 12}.');

        case 'unknown_column':
            return lang('The column is not in the table.');

        case 'computed':
            return lang('The column is worked out from the others.');
    }

    return ws_wtable_screen_error_text($code);
}

/**
 * Why a table box may not write these cells into these columns, '' when it
 * may: each a column of the table that keeps cells (a formula's value is
 * worked out), and a value written as it is one its column takes. A value
 * read from the run is looked at once it is filled in. Pure but for lang().
 *
 * @param array $columns column id => column (name, type, options), ws_wtable_columns()
 * @param array $cells   ws_step_row_cells()
 * @return string
 */
function ws_step_row_cells_refusal($columns, $cells)
{
    foreach ($cells as $id => $value) {
        $column = $columns[(string) $id] ?? null;

        if (!$column) {
            return lang(array('string' => 'The table has no column {var:1}.', 'vars' => (string) $id));
        }

        if (!ws_wtable_type_stores((string) $column['type'])) {
            return lang(array('string' => 'The column “{var:1}” is worked out from the others: it cannot be written.', 'vars' => (string) $column['name']));
        }

        if (ws_step_row_value_in($value)) {
            continue;
        }

        $check = ws_wtable_cell_check($column, $value);

        if (!$check['ok']) {
            return lang(array('string' => 'The column “{var:1}” does not take that value: {var:2}', 'vars' => array((string) $column['name'], ws_step_row_cell_reason($check['error']))));
        }
    }

    return '';
}

/**
 * Why the table's store refused a table box's write, in a sentence: each
 * column whose value it refused, by name, or what else stopped it. Pure but
 * for lang().
 *
 * @param array  $written ws_wtable_row_create() or ws_wtable_row_update()
 * @param array  $columns column id => column
 * @param string $table   the table's name
 * @return string
 */
function ws_step_row_write_refusal($written, $columns, $table)
{
    switch ((string) ($written['error'] ?? '')) {
        case 'cells_invalid':
            $said = array();

            foreach ((array) ($written['errors'] ?? array()) as $id => $code) {
                $said[] = (isset($columns[(string) $id]) ? '“' . $columns[(string) $id]['name'] . '”' : '#' . $id) . ': ' . ws_step_row_cell_reason($code);
            }

            return lang(array('string' => 'The table “{var:1}” refused a value. {var:2}', 'vars' => array($table, implode(' ', $said))));

        case 'too_many_rows':
            return lang(array('string' => 'The table “{var:1}” has {var:2} rows, the most a table may have.', 'vars' => array($table, ws_wtable_limits()['rows'])));

        case 'changed_meanwhile':
            return lang(array('string' => 'Somebody changed the row in the table “{var:1}” while the box wrote it, twice; it was not changed.', 'vars' => $table));

        case 'not_found':
            return lang(array('string' => 'The flow\'s owner may no longer change the table “{var:1}”.', 'vars' => $table));
    }

    return lang(array('string' => 'The row could not be written in the table “{var:1}”.', 'vars' => $table));
}

/**
 * What a table box does, in words. Pure.
 *
 * @param array $action ws_step_row_check()
 * @return string
 */
function ws_step_row_text($action)
{
    $columns = implode(', ', array_map('strval', (array) ($action['columns'] ?? array())));
    $table = (string) ($action['table_name'] ?? '');

    if ((string) ($action['type'] ?? '') === 'row_set') {
        return lang(array('string' => 'Changes {var:1} in a row of the table “{var:2}”.', 'vars' => array($columns, $table)));
    }

    return lang(array('string' => 'Adds a row to the table “{var:1}”, filling in: {var:2}.', 'vars' => array($table, $columns)));
}

/**
 * Checks a table box with somebody's rights, the owner as they publish and
 * again each time the box runs: a table they may change (ws_wtable_flow_check()
 * at edit) whose owner is in the team (else the box waits for them: paused),
 * every cell one of its columns that keeps cells, a value written as it is
 * one the column takes, and a row named by its id there. Whoever may only
 * read the table, or reaches it through no share, may not. The run's values
 * are looked at once they are filled in.
 *
 * @param array  $viewer the flow's owner
 * @param string $kind   row_add | row_set
 * @param array  $config ws_step_config_normalize()
 * @return array ok, error, paused (the table's owner has left the team),
 *               action (type, table_id, table_name, row, cells, columns: the
 *               names of the columns it writes)
 */
function ws_step_row_check($viewer, $kind, $config)
{
    $refuse = function ($error, $paused = false) {
        return array('ok' => false, 'error' => $error, 'paused' => $paused, 'action' => null);
    };

    if (!function_exists('ws_wtable_flow_check') || !ws_wtables_ready()) {
        return $refuse(lang('The database has to be updated first.'));
    }

    $table = ws_wtable((int) $config['table_id']);
    $gate = $table ? ws_wtable_flow_check($viewer, (int) $table['id'], 'edit') : 'refused';

    if ($gate === 'paused') {
        return $refuse(lang(array('string' => 'The owner of the table “{var:1}” has left the team: nothing is written in it until they are back.', 'vars' => (string) $table['name'])), true);
    }

    if ($gate !== '') {
        return $refuse(lang('Choose a table you may change.'));
    }

    $cells = ws_step_row_cells($config['cells']);

    if (empty($cells)) {
        return $refuse(lang('The cells are not a JSON object of column ids and values.'));
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $refusal = ws_step_row_cells_refusal($columns, $cells);

    if ($refusal !== '') {
        return $refuse($refusal);
    }

    $row = ($kind === 'row_set') ? (string) $config['row'] : '';

    if (($kind === 'row_set') && !ws_step_row_names_row($row)) {
        return $refuse(lang('Name the row by its id or by one value, such as {{trigger.row_id}}.'));
    }

    if ((ws_step_row_id($row) > 0) && !ws_wtable_row((int) $table['id'], ws_step_row_id($row))) {
        return $refuse(lang(array('string' => 'The table “{var:1}” has no row with the id {var:2}.', 'vars' => array((string) $table['name'], ws_step_row_id($row)))));
    }

    $names = array();

    foreach (array_keys($cells) as $id) {
        $names[] = (string) $columns[(string) $id]['name'];
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array(
        'type'       => $kind,
        'table_id'   => (int) $table['id'],
        'table_name' => (string) $table['name'],
        'row'        => $row,
        'cells'      => (string) $config['cells'],
        'columns'    => $names,
    ));
}

/**
 * Writes a table box's row with the flow owner's rights, through the table's
 * store, so each cell is checked by its column, the table's limit is kept
 * and its row event goes out as for any write: ws_wtable_row_create() adds
 * the row at the end, ws_wtable_row_update() lays the cells over the row
 * named, on the revision it has now. The run's values are filled in
 * already, inside the JSON strings (ws_step_fill_action()). A row somebody
 * changed in between is read again and written once more; a second change
 * in between leaves it as it is.
 *
 * @param array $owner  the flow's owner, checked (ws_step_row_check())
 * @param array $action ws_step_row_check(), filled
 * @return array ok, text, row_id, data
 */
function ws_step_row_do($owner, $action)
{
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'row_id' => 0, 'data' => array());
    };

    $table = ws_wtable((int) $action['table_id']);
    $name = $table ? (string) $table['name'] : (string) $action['table_name'];
    $cells = ws_step_row_cells($action['cells']);

    if (!$table) {
        return $fail(lang(array('string' => 'The table “{var:1}” is gone.', 'vars' => $name)));
    }

    if (empty($cells)) {
        return $fail(lang('The cells are not a JSON object of column ids and values.'));
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $written = array('ok' => false, 'error' => '');

    if ((string) $action['type'] === 'row_add') {
        $written = ws_wtable_row_create($owner, $table, array('cells' => $cells));
        $row_id = (int) ($written['row_id'] ?? 0);
    } else {
        $row_id = ws_step_row_id($action['row']);

        if ($row_id <= 0) {
            return $fail(lang(array('string' => 'The box has no row to change: “{var:1}” is not the id of a row.', 'vars' => (string) $action['row'])));
        }

        for ($try = 0; $try < 2; $try++) {
            $row = ws_wtable_row((int) $table['id'], $row_id);

            if (!$row) {
                return $fail(lang(array('string' => 'The table “{var:1}” has no row with the id {var:2}.', 'vars' => array($name, $row_id))));
            }

            $written = ws_wtable_row_update($owner, $table, $row, array('cells' => $cells, 'revision' => (int) $row['revision']));

            if ((string) ($written['error'] ?? '') !== 'changed_meanwhile') {
                break;
            }
        }
    }

    if (empty($written['ok'])) {
        return $fail(ws_step_row_write_refusal($written, $columns, $name));
    }

    return array(
        'ok'     => true,
        'text'   => ((string) $action['type'] === 'row_add')
            ? lang(array('string' => 'Added a row to the table “{var:1}”.', 'vars' => $name))
            : lang(array('string' => 'Changed {var:1} in a row of the table “{var:2}”.', 'vars' => array(implode(', ', array_map('strval', (array) ($action['columns'] ?? array()))), $name))),
        'row_id' => $row_id,
        'data'   => array(),
    );
}

/**
 * The key a table box waits on while its table's owner is away
 * (ws_step_row_wait()): wtable:<table id>. Pure.
 *
 * @param int $table_id
 * @return string
 */
function ws_step_row_wait_key($table_id)
{
    return 'wtable:' . (int) $table_id;
}

/**
 * The table a table box's wait key names, 0 for a key of another kind. Pure.
 *
 * @param string $key
 * @return int
 */
function ws_step_row_wait_table($key)
{
    return preg_match('/^wtable:([1-9][0-9]{0,9})$/', (string) $key, $match) ? (int) $match[1] : 0;
}

/**
 * A table box whose table's owner has left the team waits for them, as the
 * table's gate says (ws_wtable_flow_gate(), paused): in one conditional
 * write it forgets that it began (nothing was written), waits on no event
 * and has its next look in WS_FLOW_RECHECK seconds (ws_step_row_recheck()),
 * keyed by the table. Its result says why it waits.
 *
 * @param array  $step     ws_flow_run_steps row, running
 * @param int    $table_id
 * @param string $text     why it waits
 */
function ws_step_row_wait($step, $table_id, $text)
{
    $next = time() + WS_FLOW_RECHECK;
    $said = lang(array('string' => '{var:1} The box waits and looks again at {var:2}.', 'vars' => array(rtrim((string) $text), ws_scheduled_moment($next))));

    db("UPDATE ws_flow_run_steps SET status = 'waiting', effect = '', wait_event = '',
            wait_key = '" . e(ws_step_row_wait_key($table_id)) . "', wake_at = '" . $next . "',
            result = '" . e(ws_flow_result_pack($said)) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) === 1) {
        ws_flow_run_refresh((int) $step['run_id']);
    }
}

/**
 * A table box waiting for its table's owner looks again: still away, it
 * goes on waiting (ws_flow_steps_recheck() has moved its next look on);
 * back, or the table no longer one to wait for, it is ready again, from
 * waiting only, so two looks make it ready once; its next claim checks it
 * and writes or fails, and counts no new try. A run that ended meanwhile
 * lets it go.
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return bool whether the box changed
 */
function ws_step_row_recheck($step)
{
    $run = ws_flow_run($step['run_id']);

    if (!$run) {
        return false;
    }

    if (!ws_flow_run_open($run['status'])) {
        return ws_flow_step_settle($step, 'waiting', 'skipped', '', lang('Not reached in this run.'));
    }

    $owner = ws_change_viewer_for_id((int) $run['actor_id']);

    if (is_array($owner) && (ws_wtable_flow_check($owner, ws_step_row_wait_table($step['wait_key']), 'edit') === 'paused')) {
        return false;
    }

    ws_flow_run_touch((int) $step['run_id']);

    db("UPDATE ws_flow_run_steps SET status = 'ready', outcome = '', effect = '', wait_event = '', wait_key = '', wake_at = 0,
            ready_at = '" . time() . "', claimed_at = 0, finished_at = 0, attempt = '" . max(0, (int) ($step['attempt'] ?? 0) - 1) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'waiting' AND wait_key = '" . e((string) $step['wait_key']) . "'");

    $ready = ((int) mysqli_affected_rows(db::$con) === 1);

    ws_flow_run_refresh((int) $step['run_id']);

    return $ready;
}
