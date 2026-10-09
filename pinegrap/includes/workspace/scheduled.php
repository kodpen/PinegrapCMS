<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - scheduled actions: something the team wants done at a given
 * time, or when another scheduled action has finished, written once and left
 * to run. Each has a name, the rules that must all hold when it runs (its
 * time or "when another one starts it", and optionally the value of a record,
 * a count such as the overdue tasks or the new orders, a working day, some
 * days of the week or the hours of the day), one action, and follow-ups done
 * depending on how the action went: on success, on failure, when it was
 * skipped, or always.
 *
 * The actions are ones the workspace already does safely, and a few that
 * look outwards: write in a channel, send an e-mail, let people know in
 * their inbox, open a task, ask people of a channel to approve something
 * (approvals.php), change a record the way a change proposed by
 * Claude is applied (includes/workspace/changes.php), post a summary of the
 * counts, call a webhook, check that a web address answers or how long its
 * certificate has left, and start another scheduled action.
 *
 * An action may also have no time of its own and wait for somebody to join a
 * channel (the "join" rule): the join puts it in the queue with the newcomer
 * named, which is how a channel greets the people who come in and asks them
 * to say who they are.
 *
 * Or it waits for something to happen on the site (the "event" rule): a new
 * order, an order that changed status, a submitted form, a product low in
 * stock. The event is kept where it happened and the run picks it up
 * (includes/workspace/watch.php), putting the action in the queue with the
 * event carried, so its texts can name the record ({{record}}).
 *
 * A scheduled message (kind "message", scheduled_messages.php) is the plain
 * bridge from the writing box: one message written now and posted later, by
 * anybody in the team, run by the same machinery.
 *
 * Starting another one is how actions are chained: the follow-up puts the
 * other action in a queue (ws_scheduled_queue) for now or for a while later,
 * and the next run takes it from there. A chain carries its depth, and stops
 * at WS_SCHEDULED_CHAIN_DEPTH; an action is not started by others more often
 * than WS_SCHEDULED_CHAIN_HOURLY times an hour, so two actions that start
 * each other cannot run away.
 *
 * Staff only (roles 0-2) create, change and see them. An action runs with the
 * rights its creator holds when it runs, not the ones held when it was
 * written: somebody who has left the staff since leaves actions that fail.
 *
 * Nothing here runs by itself. ws_scheduled_run() is called by the screens'
 * ws_tick (a channel that is open asks for it as soon as ws_sync reports
 * something due), by the general job on every tick (job.php) and by the
 * workspace's scheduled job, so an action is late by at most the time until
 * somebody opens the workspace or the general job next runs - a minute on a
 * site that schedules it every minute. The same run lets approval requests
 * expire and reminds about them and about read receipts (approvals.php,
 * acks.php); ws_scheduled_due() reports those as due too.
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
 * The most addresses one e-mail action writes to.
 */
define('WS_SCHEDULED_MAX_RECIPIENTS', 20);

/**
 * Seconds a claimed run holds its action before another run may take it: a
 * request that died half way through does not keep the action forever.
 */
define('WS_SCHEDULED_LOCK', 600);

/**
 * The most actions one run carries out: a backlog after a long quiet spell
 * is worked off over several requests rather than in one.
 */
define('WS_SCHEDULED_BATCH', 5);

/**
 * How many actions may follow one another in a chain, the first included.
 */
define('WS_SCHEDULED_CHAIN_DEPTH', 8);

/**
 * How often one action may be started by others in an hour.
 */
define('WS_SCHEDULED_CHAIN_HOURLY', 60);

/**
 * The most follow-ups one action has.
 */
define('WS_SCHEDULED_MAX_FOLLOW', 5);

/**
 * The shortest repeat in minutes: a web check every five minutes is often
 * enough, and a page that is asked for every minute notices.
 */
define('WS_SCHEDULED_MIN_MINUTES', 5);

/**
 * Are the tables there (2026.4.5, 5.81)?
 *
 * @return bool
 */
function ws_scheduled_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_scheduled_actions', 'ws_scheduled_runs')") === 2);
    }

    return $ready;
}

/**
 * Are the follow-ups, the queue of chains and the notices there (2026.4.5,
 * 5.85)? Without them an action keeps the single "then" of 5.81.
 *
 * @return bool
 */
function ws_scheduled_chains_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_scheduled_ready()
            && function_exists('waf_table_has_column')
            && waf_table_has_column('ws_scheduled_actions', 'follow')
            && waf_table_has_column('ws_scheduled_runs', 'source_action_id')
            && waf_table_has_column('ws_scheduled_queue', 'due_at')
            && waf_table_has_column('ws_scheduled_notices', 'inbox_id');
    }

    return $ready;
}

/**
 * Do scheduled actions know their kind (2026.4.8, 8.81)? Without the column
 * every row is an action and no message can be scheduled.
 *
 * @return bool
 */
function ws_scheduled_messages_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_scheduled_ready() && function_exists('waf_table_has_column')
            && waf_table_has_column('ws_scheduled_actions', 'kind');
    }

    return $ready;
}

/**
 * The condition that keeps a list to the scheduled actions, leaving the
 * scheduled messages (each person's own) out.
 *
 * @param string $alias the table's alias in the query
 * @return string " AND ..." or ""
 */
function ws_scheduled_actions_only($alias = '')
{
    return ws_scheduled_messages_ready() ? " AND " . (($alias !== '') ? $alias . '.' : '') . "kind = 'action'" : '';
}

/**
 * Can a start in the queue carry who it is about (2026.4.8, 8.81)? The join
 * rule names the newcomer that way.
 *
 * @return bool
 */
function ws_scheduled_join_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_scheduled_chains_ready() && waf_table_has_column('ws_scheduled_queue', 'context');
    }

    return $ready;
}

/**
 * May this person create, change and see scheduled actions? Staff in the
 * team.
 *
 * @param array $viewer
 * @return bool
 */
function ws_can_schedule($viewer)
{
    return !empty($viewer['member']) && ((int) $viewer['role'] < 3) && ws_scheduled_ready();
}

/**
 * One scheduled action, its JSON parts decoded.
 *
 * @param int $id
 * @return array|null
 */
function ws_scheduled($id)
{
    if (!ws_scheduled_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_scheduled_actions WHERE id = '" . (int) $id . "'");

    return is_array($row) ? ws_scheduled_decode($row) : null;
}

/**
 * One scheduled action, never somebody's scheduled message: what the staff's
 * screen reads, changes and runs.
 *
 * @param int $id
 * @return array|null
 */
function ws_scheduled_action($id)
{
    $row = ws_scheduled($id);

    return ($row && ((string) ($row['kind'] ?? 'action') === 'action')) ? $row : null;
}

/**
 * The JSON columns of a row as arrays. An action written before the
 * follow-ups (5.81) has one "then", done when the action went well: it reads
 * as that follow-up.
 *
 * @param array $row
 * @return array
 */
function ws_scheduled_decode($row)
{
    foreach (array('rules', 'action', 'then_action', 'follow') as $column) {
        $value = json_decode((string) ($row[$column] ?? ''), true);
        $row[$column] = is_array($value) ? $value : null;
    }

    if (!is_array($row['rules'])) {
        $row['rules'] = array();
    }

    if (!is_array($row['follow'])) {
        $row['follow'] = is_array($row['then_action']) ? array(array('on' => 'done', 'do' => $row['then_action'])) : array();
    }

    return $row;
}

/**
 * How a time rule repeats.
 *
 * @return array key => label
 */
function ws_scheduled_repeats()
{
    return array(
        'none'     => lang('Once'),
        'minutes'  => lang('Every few minutes'),
        'hourly'   => lang('Every hour'),
        'daily'    => lang('Every day'),
        'workdays' => lang('Every working day'),
        'weekly'   => lang('Every week'),
        'monthly'  => lang('Every month'),
        'yearly'   => lang('Every year'),
    );
}

/**
 * How a record rule or a count compares.
 *
 * @return array key => label
 */
function ws_scheduled_operators()
{
    return array(
        'eq'        => lang('is'),
        'neq'       => lang('is not'),
        'empty'     => lang('is empty'),
        'not_empty' => lang('is not empty'),
        'gt'        => lang('is more than'),
        'gte'       => lang('is at least'),
        'lt'        => lang('is less than'),
        'lte'       => lang('is at most'),
        'contains'  => lang('contains'),
        'within'    => lang('falls within the next days'),
        'overdue'   => lang('has gone by'),
    );
}

/**
 * The comparisons a count takes.
 *
 * @return string[]
 */
function ws_scheduled_count_operators()
{
    return array('gt', 'gte', 'lt', 'lte', 'eq', 'neq');
}

/**
 * When a follow-up is done: by how the action went.
 *
 * @return array key => label
 */
function ws_scheduled_outcomes()
{
    return array(
        'done'    => lang('If it went well'),
        'failed'  => lang('If it failed'),
        'skipped' => lang('If it was skipped'),
        'always'  => lang('Always'),
    );
}

/**
 * The shapes a webhook's body takes: the full account of the run, or the one
 * text field the chat services read (Slack, Microsoft Teams and Google Chat
 * read "text", Discord "content").
 *
 * @return array key => label
 */
function ws_scheduled_webhook_formats()
{
    return array(
        'generic' => lang('JSON with the details of the run'),
        'text'    => lang('Chat message (Slack, Teams, Google Chat)'),
        'discord' => lang('Discord message'),
    );
}

/**
 * The kinds of record a rule can look at, and a change can be made to: the
 * kinds of change the workspace knows (a channel's summary aside - it is not
 * something to wait for), and tasks, whose state is the usual thing to wait
 * on.
 *
 * @return array type => [label, tag, fields => name => [kind, limit, label, column]]
 */
function ws_scheduled_record_types()
{
    $out = array();

    foreach (ws_change_types() as $type => $info) {
        if ($type === 'channel') {
            continue;
        }

        // A form's answers are a set, not one value to wait on.
        $fields = array_filter($info['fields'], function ($field) {
            return $field[0] !== 'answers';
        });

        $out[$type] = array('label' => $info['label'], 'tag' => $info['tag'], 'fields' => $fields);
    }

    $out['task'] = array(
        'label'  => lang('Task'),
        'tag'    => 'task',
        'fields' => array(
            'status'   => array('enum', array('todo', 'doing', 'waiting', 'done', 'cancelled'), lang('Status'), 'status'),
            'priority' => array('enum', array('low', 'normal', 'high', 'urgent'), lang('Priority'), 'priority'),
            'due_date' => array('date', 0, lang('Due date'), 'due_date'),
        ),
    );

    return $out;
}

// ─── Counts ─────────────────────────────────────────────────────────────

/**
 * The counts a rule can wait on and a summary can list. Each is one indexed
 * read of a table the site keeps anyway; the visitor counts come from the
 * hourly rollup, never from the raw log. "Since the last run" counts from the
 * action's previous run, or the last day for one that has not run.
 *
 * kind: count | money | minutes. scope: '' | channel (a channel's tasks only) |
 * threshold (a number the count is measured against). right: what the
 * creator must be able to see.
 *
 * @return array key => label, kind, scope, right, group
 */
function ws_scheduled_metrics()
{
    static $metrics = null;

    if ($metrics !== null) {
        return $metrics;
    }

    $metrics = array(
        'tasks_open'        => array('label' => lang('Open tasks'), 'kind' => 'count', 'scope' => 'channel', 'right' => '', 'group' => 'work'),
        'tasks_overdue'     => array('label' => lang('Overdue tasks'), 'kind' => 'count', 'scope' => 'channel', 'right' => '', 'group' => 'work'),
        'tasks_due_today'   => array('label' => lang('Tasks due today'), 'kind' => 'count', 'scope' => 'channel', 'right' => '', 'group' => 'work'),
        'tasks_done_today'  => array('label' => lang('Tasks done today'), 'kind' => 'count', 'scope' => 'channel', 'right' => '', 'group' => 'work'),
        'forms_new'         => array('label' => lang('New form submissions since the last run'), 'kind' => 'count', 'scope' => '', 'right' => 'forms', 'group' => 'web'),
        'comments_pending'  => array('label' => lang('Comments waiting for approval'), 'kind' => 'count', 'scope' => '', 'right' => '', 'group' => 'web'),
        'visitors_yesterday' => array('label' => lang('Visitors yesterday'), 'kind' => 'count', 'scope' => '', 'right' => '', 'group' => 'web'),
        'views_yesterday'   => array('label' => lang('Page views yesterday'), 'kind' => 'count', 'scope' => '', 'right' => '', 'group' => 'web'),
    );

    // Minutes written on tasks this week, from Monday (task_time.php).
    if (function_exists('ws_task_time_ready') && ws_task_time_ready()) {
        $metrics['time_logged_week'] = array('label' => lang('Time spent on tasks this week'), 'kind' => 'minutes', 'scope' => 'channel', 'right' => '', 'group' => 'work');
    }

    if (defined('ECOMMERCE') && (ECOMMERCE === true)) {
        $metrics += array(
            'orders_new'    => array('label' => lang('New orders since the last run'), 'kind' => 'count', 'scope' => '', 'right' => 'ecommerce', 'group' => 'shop'),
            'orders_today'  => array('label' => lang('Orders today'), 'kind' => 'count', 'scope' => '', 'right' => 'ecommerce', 'group' => 'shop'),
            'sales_today'   => array('label' => lang('Sales today'), 'kind' => 'money', 'scope' => '', 'right' => 'ecommerce', 'group' => 'shop'),
            'stock_low'     => array('label' => lang('Products low in stock'), 'kind' => 'count', 'scope' => 'threshold', 'right' => 'ecommerce', 'group' => 'shop'),
        );
    }

    if (defined('ERP_ENABLED') && ERP_ENABLED && function_exists('waf_table_has_column') && waf_table_has_column('erp_invoices', 'due_date')) {
        $metrics += array(
            'invoices_overdue'        => array('label' => lang('Overdue sales invoices'), 'kind' => 'count', 'scope' => '', 'right' => 'erp', 'group' => 'erp'),
            'invoices_overdue_amount' => array('label' => lang('Amount overdue on sales invoices'), 'kind' => 'money', 'scope' => '', 'right' => 'erp', 'group' => 'erp'),
        );
    }

    return $metrics;
}

/**
 * One count now.
 *
 * @param array  $viewer the creator: a count they may not see is refused
 * @param string $key
 * @param int    $param  a channel (scope channel) or a threshold
 * @param int    $since  the action's previous run, 0 for the last day
 * @return array ok, value (int: a number, or minor units for money), text, error
 */
function ws_scheduled_metric_value($viewer, $key, $param = 0, $since = 0)
{
    $metrics = ws_scheduled_metrics();
    $metric = $metrics[$key] ?? null;

    if ($metric === null) {
        return array('ok' => false, 'value' => 0, 'text' => '', 'error' => lang('That count is not known.'));
    }

    if (($metric['right'] !== '') && empty($viewer[$metric['right']])) {
        return array('ok' => false, 'value' => 0, 'text' => '', 'error' => lang('You may not see this count.'));
    }

    $since = ((int) $since > 0) ? (int) $since : time() - 86400;
    $today = date('Y-m-d');
    $midnight = strtotime($today . ' 00:00:00');
    $channel = (($metric['scope'] === 'channel') && ((int) $param > 0)) ? " AND channel_id = '" . (int) $param . "'" : '';
    $open = "status NOT IN ('done', 'cancelled')";
    $value = 0;

    switch ($key) {
        case 'tasks_open':
            $value = (int) db_value("SELECT COUNT(*) FROM ws_tasks WHERE " . $open . $channel);
            break;

        case 'tasks_overdue':
            $value = (int) db_value("SELECT COUNT(*) FROM ws_tasks WHERE " . $open . " AND due_date IS NOT NULL AND due_date <> '0000-00-00' AND due_date < '" . $today . "'" . $channel);
            break;

        case 'tasks_due_today':
            $value = (int) db_value("SELECT COUNT(*) FROM ws_tasks WHERE " . $open . " AND due_date = '" . $today . "'" . $channel);
            break;

        case 'tasks_done_today':
            $value = (int) db_value("SELECT COUNT(*) FROM ws_tasks WHERE status = 'done' AND completed_at >= '" . $midnight . "'" . $channel);
            break;

        case 'time_logged_week':
            $value = ws_task_time_week_minutes(($channel !== '') ? (int) $param : 0);
            break;

        case 'forms_new':
            $value = (int) db_value("SELECT COUNT(*) FROM forms WHERE complete = '1' AND submitted_timestamp > '" . $since . "'");
            break;

        case 'comments_pending':
            $value = (int) db_value("SELECT COUNT(*) FROM comments WHERE published = '0'");
            break;

        case 'visitors_yesterday':
        case 'views_yesterday':
            $column = ($key === 'visitors_yesterday') ? 'new_visitors' : 'page_views';
            $value = (int) db_value("SELECT COALESCE(SUM(" . $column . "), 0) FROM visitor_stats_hourly WHERE stat_date = '" . date('Y-m-d', strtotime('-1 day')) . "'");
            break;

        case 'orders_new':
            $value = (int) db_value("SELECT COUNT(*) FROM orders WHERE status IN ('complete', 'exported') AND order_date > '" . $since . "'");
            break;

        case 'orders_today':
            $value = (int) db_value("SELECT COUNT(*) FROM orders WHERE status IN ('complete', 'exported') AND order_date >= '" . $midnight . "'");
            break;

        case 'sales_today':
            $value = (int) db_value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('complete', 'exported') AND order_date >= '" . $midnight . "'");
            break;

        case 'stock_low':
            $threshold = max(0, (int) $param);
            $value = (int) db_value("SELECT COUNT(*) FROM products WHERE enabled = '1' AND inventory = '1' AND inventory_quantity <= '" . $threshold . "'"
                . (waf_table_has_column('products', 'recycled') ? " AND recycled = '0'" : ''));
            break;

        case 'invoices_overdue':
        case 'invoices_overdue_amount':
            $where = "direction = 'sales' AND doc_type = 'invoice' AND status IN ('issued', 'partially_paid')
                AND due_date <> '0000-00-00' AND due_date < '" . $today . "'";
            $value = ($key === 'invoices_overdue')
                ? (int) db_value("SELECT COUNT(*) FROM erp_invoices WHERE " . $where)
                : (int) db_value("SELECT COALESCE(SUM(grand_total - paid_total), 0) FROM erp_invoices WHERE " . $where);
            break;
    }

    return array('ok' => true, 'value' => $value, 'text' => ws_scheduled_metric_show($key, $value), 'error' => '');
}

/**
 * A count as a summary writes it.
 *
 * @param string $key
 * @param int    $value
 * @return string
 */
function ws_scheduled_metric_show($key, $value)
{
    $metric = ws_scheduled_metrics()[$key] ?? null;

    if ($metric && ($metric['kind'] === 'money')) {
        return ws_money_out((int) $value);
    }

    // Minutes, the way the planning screens say them ("9 h 30 min").
    if ($metric && ($metric['kind'] === 'minutes')) {
        return ws_minutes_label((int) $value);
    }

    return function_exists('pg_format_number') ? (string) pg_format_number((int) $value, 0) : number_format((int) $value);
}

/**
 * A count's name, with its scope.
 *
 * @param string $key
 * @param int    $param
 * @return string
 */
function ws_scheduled_metric_label($key, $param = 0)
{
    $metric = ws_scheduled_metrics()[$key] ?? null;

    if ($metric === null) {
        return (string) $key;
    }

    if (($metric['scope'] === 'channel') && ((int) $param > 0)) {
        $channel = ws_channel((int) $param);

        return $metric['label'] . ($channel ? ' (#' . $channel['name'] . ')' : '');
    }

    if ($metric['scope'] === 'threshold') {
        return $metric['label'] . ' (≤ ' . (int) $param . ')';
    }

    return $metric['label'];
}

// ─── Placeholders ───────────────────────────────────────────────────────

/**
 * The words a text of an action can hold in double braces, filled in when
 * it runs.
 *
 * @return array placeholder => what it becomes
 */
function ws_scheduled_placeholders()
{
    return array(
        '{{date}}'          => lang('today\'s date'),
        '{{time}}'          => lang('the time it runs'),
        '{{weekday}}'       => lang('the day of the week'),
        '{{name}}'          => lang('the name of the scheduled action'),
        '{{run}}'           => lang('which run this is'),
        '{{result}}'        => lang('what the action did, in a follow-up'),
        '{{status}}'        => lang('how the action went, in a follow-up'),
        '{{source}}'        => lang('the action that started this one'),
        '{{source_result}}' => lang('what the action that started this one did'),
        '{{newcomer}}'      => lang('the person who joined the channel, tagged'),
        '{{newcomer_name}}' => lang('the name of the person who joined'),
        '{{channel}}'       => lang('the channel they joined'),
        '{{event}}'         => lang('what happened, for an action started by an event'),
        '{{record}}'        => lang('the record it happened to, tagged'),
        '{{record_title}}'  => lang('the name of the record'),
        '{{record_link}}'   => lang('the address of the record in the panel'),
        '{{record_status}}' => lang('the status of the order'),
        '{{customer}}'      => lang('the customer of the order, the contact or the form'),
        '{{amount}}'        => lang('the amount of the order'),
        '{{form_fields}}'   => lang('the fields of the submitted form, a line each. Members who may not see forms read the values too.'),
        '{{count:tasks_overdue}}' => lang('a count, by its key'),
    );
}

/**
 * Fills the placeholders of a text.
 *
 * @param string $text
 * @param array  $context viewer, row, result, status, source, source_result
 * @return string
 */
function ws_scheduled_fill($text, $context)
{
    $text = (string) $text;

    if (strpos($text, '{{') === false) {
        return $text;
    }

    $row = $context['row'] ?? array();
    $names = function_exists('ws_recurrence_weekday_names') ? ws_recurrence_weekday_names() : array();
    $values = array(
        '{{date}}'          => date('d.m.Y'),
        '{{time}}'          => date('H:i'),
        '{{weekday}}'       => $names[(int) date('N')] ?? '',
        '{{name}}'          => (string) ($row['name'] ?? ''),
        '{{run}}'           => (string) ((int) ($row['run_count'] ?? 0) + 1),
        '{{result}}'        => (string) ($context['result'] ?? ''),
        '{{status}}'        => (string) ($context['status'] ?? ''),
        '{{source}}'        => (string) ($context['source'] ?? ''),
        '{{source_result}}' => (string) ($context['source_result'] ?? ''),
        '{{newcomer}}'      => ((int) ($context['newcomer'] ?? 0) > 0) ? '<@user:' . (int) $context['newcomer'] . '>' : '',
        '{{newcomer_name}}' => ((int) ($context['newcomer'] ?? 0) > 0) ? ws_person_name((int) $context['newcomer']) : '',
        '{{channel}}'       => ((int) ($context['join_channel_id'] ?? 0) > 0) ? '#' . (string) db_value("SELECT name FROM ws_channels WHERE id = '" . (int) $context['join_channel_id'] . "'") : '',
    );

    // An action started by an event: the record and what is known of it,
    // read only when the text asks for one of them (watch.php). Run by hand
    // or by another action, there is no event and they are left empty.
    if (preg_match_all('/\{\{(event|record|record_title|record_link|record_status|customer|amount|form_fields)\}\}/', $text, $asked)) {
        $values += (!empty($context['event']) && function_exists('ws_events_fill_values'))
            ? ws_events_fill_values(is_array($context['viewer'] ?? null) ? $context['viewer'] : array(), (string) $context['event'], (array) ($context['event_payload'] ?? array()))
            : array_fill_keys($asked[0], '');
    }

    $text = strtr($text, $values);

    // A count by its key: {{count:orders_new}}, {{count:stock_low:5}}.
    return preg_replace_callback('/\{\{count:([a-z_]+)(?::(\d+))?\}\}/', function ($match) use ($context, $row) {
        $viewer = $context['viewer'] ?? null;

        if (!is_array($viewer)) {
            return '';
        }

        $count = ws_scheduled_metric_value($viewer, $match[1], (int) ($match[2] ?? 0), (int) ($row['last_run_at'] ?? 0));

        return $count['ok'] ? $count['text'] : '?';
    }, $text);
}

/**
 * What the form needs: the kinds of record with their fields, the kinds of
 * change with their actions, the counts, and the lists it offers. Null for
 * somebody who may not schedule.
 *
 * @param array $viewer
 * @return array|null
 */
function ws_scheduled_js_config($viewer)
{
    if (!ws_can_schedule($viewer)) {
        return null;
    }

    $describe = function ($type, $fields) {
        $out = array();

        foreach ($fields as $name => $field) {
            $labels = array();

            if ($field[0] === 'enum') {
                foreach ((array) $field[1] as $value) {
                    $labels[$value] = ws_scheduled_value_label($type, $name, $value);
                }
            }

            $out[$name] = array(
                'kind'   => (string) $field[0],
                'label'  => (string) $field[2],
                'values' => ($field[0] === 'enum') ? array_values((array) $field[1]) : array(),
                'labels' => $labels,
                'column' => ($field[3] !== ''),
            );
        }

        return $out;
    };

    $records = array();

    foreach (ws_scheduled_record_types() as $type => $info) {
        // A kind the person may not change is no use to look at either.
        if (($type !== 'task') && !ws_change_allowed($viewer, $type, 0)) {
            continue;
        }

        $records[$type] = array('label' => $info['label'], 'tag' => $info['tag'], 'fields' => $describe($type, $info['fields']));
    }

    $changes = array();

    foreach (ws_change_types() as $type => $info) {
        if (($type === 'channel') || !ws_change_allowed($viewer, $type, 0)) {
            continue;
        }

        $changes[$type] = array(
            'label'   => $info['label'],
            'tag'     => $info['tag'],
            'actions' => array_values(array_intersect($info['actions'], array('update', 'delete'))),
            'fields'  => $describe($type, $info['fields']),
        );
    }

    $metrics = array();

    foreach (ws_scheduled_metrics() as $key => $metric) {
        if (($metric['right'] === '') || !empty($viewer[$metric['right']])) {
            $metrics[$key] = array('label' => $metric['label'], 'kind' => $metric['kind'], 'scope' => $metric['scope'], 'group' => $metric['group']);
        }
    }

    return array(
        'ready'        => true,
        'chains'       => ws_scheduled_chains_ready(),
        'approvals'    => function_exists('ws_approvals_ready') && ws_approvals_ready(),
        'join'         => ws_scheduled_join_ready(),
        // What an event rule offers (watch.php): null where it cannot be had.
        'events'       => function_exists('ws_events_js_config') ? ws_events_js_config($viewer) : null,
        'digest_only'  => ws_scheduled_digest_filters(),
        'records'      => $records,
        'changes'      => $changes,
        'metrics'      => $metrics,
        'repeats'      => ws_scheduled_repeats(),
        'operators'    => ws_scheduled_operators(),
        'counts'       => ws_scheduled_count_operators(),
        'outcomes'     => ws_scheduled_outcomes(),
        'formats'      => ws_scheduled_webhook_formats(),
        'placeholders' => ws_scheduled_placeholders(),
        'depth'        => WS_SCHEDULED_CHAIN_DEPTH,
        'max_follow'   => WS_SCHEDULED_MAX_FOLLOW,
        'min_minutes'  => WS_SCHEDULED_MIN_MINUTES,
        'site'         => (defined('URL_SCHEME') && defined('HOSTNAME')) ? URL_SCHEME . HOSTNAME . (defined('PATH') ? PATH : '/') : '',
        'email'        => (string) db_value("SELECT user_email FROM user WHERE user_id = '" . (int) $viewer['id'] . "'"),
        'now'          => date('Y-m-d H:i'),
    );
}

// ─── Reading what was sent ──────────────────────────────────────────────

/**
 * Checks and tidies a scheduled action as the form sends it.
 *
 * @param array $viewer
 * @param array $data name, rules, action, follow (or then_action), channel_id, note_id
 * @param array|null $existing the action being changed
 * @return array ok, error, field, row (columns to write)
 */
function ws_scheduled_input($viewer, $data, $existing = null)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'row' => null);
    };

    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($data['name'] ?? ''))), 0, 160);

    if ($name === '') {
        return $fail(lang('A scheduled action needs a name.'), 'name');
    }

    // The rules: one "when" - a time, or being started by another action -
    // and any number of conditions beside it.
    $rules = array();
    $when = null;

    foreach (array_slice(array_values((array) ($data['rules'] ?? array())), 0, 12) as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $checked = ws_scheduled_rule_input($viewer, $rule);

        if (!$checked['ok']) {
            return $fail($checked['error'], 'rules');
        }

        if (in_array($checked['rule']['type'], array('at', 'trigger', 'join', 'event'), true)) {
            if ($when !== null) {
                return $fail(lang('A scheduled action has one time; add the other rules as conditions.'), 'rules');
            }

            $when = $checked['rule'];
        }

        $rules[] = $checked['rule'];
    }

    if ($when === null) {
        return $fail(lang('Say when it should run.'), 'rules');
    }

    if ((($when['type'] === 'trigger') && !ws_scheduled_chains_ready()) || (($when['type'] === 'join') && !ws_scheduled_join_ready())
        || (($when['type'] === 'event') && (!ws_scheduled_join_ready() || !function_exists('ws_events_ready') || !ws_events_ready()))) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'), 'rules');
    }

    $next = 0;

    if ($when['type'] === 'at') {
        $next = ws_scheduled_next_run($when, time());

        // A time that has gone by is only accepted for an action that
        // repeats; a single run in the past would run at once, which nobody
        // meant.
        if ($next <= 0) {
            return $fail(lang('That time has already gone by.'), 'rules');
        }
    }

    $self_id = $existing ? (int) $existing['id'] : 0;
    $channel_id = (int) ($existing['channel_id'] ?? ($data['channel_id'] ?? 0));

    // "The channel they joined" is a channel only a join rule names.
    $joined = ($when['type'] === 'join');
    $action = ws_scheduled_action_input($viewer, $data['action'] ?? null, $channel_id, $self_id, $joined);

    if (!$action['ok']) {
        return $fail($action['error'], 'action');
    }

    // What follows, by how the action went. The one "then" of 5.81 is a
    // follow-up done when it went well.
    $given = $data['follow'] ?? null;

    if (!is_array($given)) {
        $given = (is_array($data['then_action'] ?? null) && !in_array(($data['then_action']['type'] ?? ''), array('', 'none'), true))
            ? array(array('on' => 'done', 'do' => $data['then_action']))
            : array();
    }

    if ((count($given) > 1) && !ws_scheduled_chains_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'), 'follow');
    }

    if (count($given) > WS_SCHEDULED_MAX_FOLLOW) {
        return $fail(lang(array('string' => 'An action has at most {var:1} follow-ups.', 'vars' => WS_SCHEDULED_MAX_FOLLOW)), 'follow');
    }

    $follow = array();
    $outcomes = ws_scheduled_outcomes();

    foreach (array_values($given) as $item) {
        if (!is_array($item) || !is_array($item['do'] ?? null) || in_array(($item['do']['type'] ?? ''), array('', 'none'), true)) {
            continue;
        }

        $on = (string) ($item['on'] ?? 'done');

        if (!isset($outcomes[$on])) {
            $on = 'done';
        }

        $checked = ws_scheduled_action_input($viewer, $item['do'], $channel_id, $self_id, $joined);

        if (!$checked['ok']) {
            return $fail($checked['error'], 'follow');
        }

        $follow[] = array('on' => $on, 'do' => $checked['action']);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'row' => array(
        'name'        => $name,
        'rules'       => $rules,
        'action'      => $action['action'],
        'follow'      => $follow,
        'next_run_at' => $next,
    ));
}

/**
 * A time as the form sends it.
 *
 * @param string $clock
 * @return bool
 */
function ws_scheduled_clock_ok($clock)
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $clock);
}

/**
 * One rule.
 *
 * @param array $viewer
 * @param array $rule
 * @return array ok, error, rule
 */
function ws_scheduled_rule_input($viewer, $rule)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'rule' => null);
    };

    $type = (string) ($rule['type'] ?? '');

    switch ($type) {

        case 'at':
            $date = (string) ($rule['date'] ?? '');
            $clock = (string) ($rule['time'] ?? '');
            $repeat = (string) ($rule['repeat'] ?? 'none');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || (strtotime($date . ' 12:00:00') === false)) {
                return $fail(lang('The date is not valid.'));
            }

            if (!ws_scheduled_clock_ok($clock)) {
                return $fail(lang('The time is not valid.'));
            }

            if (!isset(ws_scheduled_repeats()[$repeat])) {
                $repeat = 'none';
            }

            $out = array('type' => 'at', 'date' => $date, 'time' => $clock, 'repeat' => $repeat);

            if ($repeat === 'none') {
                return array('ok' => true, 'error' => '', 'rule' => $out);
            }

            // Every how many: minutes from WS_SCHEDULED_MIN_MINUTES, the
            // others from one.
            $every = max(1, (int) ($rule['every'] ?? 1));

            if ($repeat === 'minutes') {
                $every = max(WS_SCHEDULED_MIN_MINUTES, min(720, $every));
            } elseif ($repeat === 'hourly') {
                $every = min(24, $every);
            } elseif ($repeat === 'weekly') {
                $every = min(52, $every);
            } elseif ($repeat !== 'workdays') {
                $every = min(365, $every);
            } else {
                $every = 1;
            }

            $out['every'] = $every;

            // A weekly rule on some days of the week (1 Monday - 7 Sunday).
            if ($repeat === 'weekly') {
                $days = array_values(array_unique(array_filter(array_map('intval', (array) ($rule['days'] ?? array())), function ($day) {
                    return ($day >= 1) && ($day <= 7);
                })));
                sort($days);

                if (!empty($days)) {
                    $out['days'] = $days;
                }
            }

            $until = (string) ($rule['until'] ?? '');

            if ($until !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || (strtotime($until . ' 12:00:00') === false)) {
                    return $fail(lang('The last day is not valid.'));
                }

                if ($until < $date) {
                    return $fail(lang('The last day comes before the first.'));
                }

                $out['until'] = $until;
            }

            return array('ok' => true, 'error' => '', 'rule' => $out);

        // No time of its own: another action starts it, or somebody by hand.
        case 'trigger':
            return array('ok' => true, 'error' => '', 'rule' => array('type' => 'trigger'));

        // No time of its own either: somebody joining a channel starts it -
        // one channel, or (0) any public channel.
        case 'join':
            $join_channel = max(0, (int) ($rule['channel_id'] ?? 0));

            if ($join_channel > 0) {
                $channel = ws_channel($join_channel);

                if (!$channel || !ws_can_read_channel($viewer, $channel) || !ws_scheduled_greets($channel)) {
                    return $fail(lang('Choose a channel people join.'));
                }
            }

            return array('ok' => true, 'error' => '', 'rule' => array('type' => 'join', 'channel_id' => $join_channel));

        // No time either: something happening on the site starts it - an
        // event the creator may see the records of, narrowed to one form or
        // to one status an order moves to.
        case 'event':
            $event = (string) ($rule['event'] ?? '');
            $info = function_exists('ws_events_catalog') ? (ws_events_catalog()[$event] ?? null) : null;

            if (($info === null) || empty($viewer[$info['right']])) {
                return $fail(lang('Choose what happens.'));
            }

            $out = array('type' => 'event', 'event' => $event);

            if ($info['filter'] === 'page') {
                $page_id = max(0, (int) ($rule['page_id'] ?? 0));

                if (($page_id > 0) && !db_value("SELECT page_id FROM custom_form_pages WHERE page_id = '" . $page_id . "'")) {
                    return $fail(lang('That form could not be found.'));
                }

                $out['page_id'] = $page_id;
            }

            if ($info['filter'] === 'status') {
                $status = (string) ($rule['status'] ?? '');

                if (($status !== '') && !isset(ws_events_order_statuses()[$status])) {
                    return $fail(lang('Choose one of the values the field takes.'));
                }

                $out['status'] = $status;
            }

            return array('ok' => true, 'error' => '', 'rule' => $out);

        case 'workday':
            return array('ok' => true, 'error' => '', 'rule' => array('type' => 'workday'));

        case 'weekdays':
            $days = array_values(array_unique(array_filter(array_map('intval', (array) ($rule['days'] ?? array())), function ($day) {
                return ($day >= 1) && ($day <= 7);
            })));
            sort($days);

            if (empty($days)) {
                return $fail(lang('Choose at least one day of the week.'));
            }

            return array('ok' => true, 'error' => '', 'rule' => array('type' => 'weekdays', 'days' => $days));

        case 'hours':
            $from = (string) ($rule['from'] ?? '');
            $to = (string) ($rule['to'] ?? '');

            if (!ws_scheduled_clock_ok($from) || !ws_scheduled_clock_ok($to) || ($from === $to)) {
                return $fail(lang('Give the hours as a start and an end, like 09:00 and 18:00.'));
            }

            return array('ok' => true, 'error' => '', 'rule' => array('type' => 'hours', 'from' => $from, 'to' => $to));

        case 'metric':
            $key = (string) ($rule['metric'] ?? '');
            $metric = ws_scheduled_metrics()[$key] ?? null;

            if (($metric === null) || (($metric['right'] !== '') && empty($viewer[$metric['right']]))) {
                return $fail(lang('Choose a count.'));
            }

            $operator = (string) ($rule['op'] ?? 'gt');

            if (!in_array($operator, ws_scheduled_count_operators(), true)) {
                return $fail(lang('Choose how the value is compared.'));
            }

            $raw = str_replace(',', '.', trim((string) ($rule['value'] ?? '')));

            if (!is_numeric($raw)) {
                return $fail(lang('Give the number the count is compared with.'));
            }

            // Money is compared in minor units, like it is kept.
            $value = ($metric['kind'] === 'money') ? (int) round(((float) $raw) * 100) : (int) round((float) $raw);
            $param = max(0, (int) ($rule['param'] ?? 0));

            if (($metric['scope'] === 'channel') && ($param > 0)) {
                $channel = ws_channel($param);

                if (!$channel || !ws_can_read_channel($viewer, $channel)) {
                    return $fail(lang('That channel could not be found.'));
                }
            }

            return array('ok' => true, 'error' => '', 'rule' => array('type' => 'metric', 'metric' => $key, 'param' => $param, 'op' => $operator, 'value' => $value));

        case 'record':
            $types = ws_scheduled_record_types();
            $record_type = (string) ($rule['record'] ?? '');
            $id = (int) ($rule['id'] ?? 0);
            $field_name = (string) ($rule['field'] ?? '');
            $operator = (string) ($rule['op'] ?? 'eq');

            if (!isset($types[$record_type])) {
                return $fail(lang('Choose what kind of record the condition looks at.'));
            }

            if (($record_type !== 'task') && !ws_change_allowed($viewer, $record_type, $id)) {
                return $fail(lang('You may not see records of this kind.'));
            }

            if (($id <= 0) || (ws_scheduled_record($record_type, $id) === null)) {
                return $fail(lang('Choose the record the condition looks at.'));
            }

            $field = $types[$record_type]['fields'][$field_name] ?? null;

            if (($field === null) || ($field[3] === '')) {
                return $fail(lang('Choose the field the condition looks at.'));
            }

            if (!isset(ws_scheduled_operators()[$operator])) {
                return $fail(lang('Choose how the value is compared.'));
            }

            // "Falls within the next days" and "has gone by" are for dates.
            if (in_array($operator, array('within', 'overdue'), true) && !in_array($field[0], array('date', 'datetime'), true)) {
                return $fail(lang('That comparison is for a date.'));
            }

            $value = mb_substr(trim((string) ($rule['value'] ?? '')), 0, 500);

            if ($operator === 'within') {
                $value = (string) max(0, min(3650, (int) $value));
            }

            if (($field[0] === 'enum') && in_array($operator, array('eq', 'neq'), true) && !in_array($value, (array) $field[1], true)) {
                return $fail(lang('Choose one of the values the field takes.'));
            }

            return array('ok' => true, 'error' => '', 'rule' => array(
                'type'   => 'record',
                'record' => $record_type,
                'id'     => $id,
                'field'  => $field_name,
                'op'     => $operator,
                'value'  => $value,
            ));
    }

    return $fail(lang('That kind of rule is not known.'));
}

/**
 * E-mail addresses as the form sends them: a list or a line.
 *
 * @param mixed $given
 * @param bool  $required
 * @return array ok, error, to
 */
function ws_scheduled_addresses($given, $required = true)
{
    $to = array();
    $list = is_array($given) ? $given : preg_split('/[\s,;]+/', (string) $given, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($list as $address) {
        $address = mb_strtolower(trim((string) $address));

        if ($address === '') {
            continue;
        }

        if (!function_exists('validate_email_address') || !validate_email_address($address)) {
            return array('ok' => false, 'error' => lang(array('string' => '{var:1} is not an e-mail address.', 'vars' => $address)), 'to' => array());
        }

        if (!in_array($address, $to, true)) {
            $to[] = $address;
        }
    }

    if ($required && empty($to)) {
        return array('ok' => false, 'error' => lang('Write the address to send it to.'), 'to' => array());
    }

    if (count($to) > WS_SCHEDULED_MAX_RECIPIENTS) {
        return array('ok' => false, 'error' => lang(array('string' => 'An e-mail action writes to at most {var:1} addresses.', 'vars' => WS_SCHEDULED_MAX_RECIPIENTS)), 'to' => array());
    }

    return array('ok' => true, 'error' => '', 'to' => $to);
}

/**
 * The people an action names: members of the team only.
 *
 * @param mixed $given
 * @return int[]
 */
function ws_scheduled_people($given)
{
    $team = array_flip(ws_team_ids());
    $out = array();

    foreach ((array) $given as $user_id) {
        $user_id = (int) $user_id;

        if (($user_id > 0) && isset($team[$user_id]) && !in_array($user_id, $out, true)) {
            $out[] = $user_id;
        }
    }

    return array_slice($out, 0, 50);
}

/**
 * An address the server is to call: https, outside this network.
 *
 * @param string $url
 * @return array ok, error, url
 */
function ws_scheduled_url_input($url)
{
    $url = trim((string) $url);

    if (($url === '') || (mb_strlen($url) > 2000) || !preg_match('#^https://#i', $url)) {
        return array('ok' => false, 'error' => lang('Give an address that starts with https://'), 'url' => '');
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $check = api_http_check_url($url);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => lang($check['error']), 'url' => '');
    }

    return array('ok' => true, 'error' => '', 'url' => $url);
}

/**
 * One action: what is done when the rules hold, or after.
 *
 * @param array $viewer
 * @param mixed $action
 * @param int   $channel_id the channel the scheduled action was written in
 * @param int   $self_id    the action being changed, 0 for a new one
 * @param bool  $joined     started by somebody joining a channel: "the channel
 *                          they joined" (channel_id -1) may be written in
 * @return array ok, error, action
 */
function ws_scheduled_action_input($viewer, $action, $channel_id = 0, $self_id = 0, $joined = false)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'action' => null);
    };

    if (!is_array($action)) {
        return $fail(lang('Choose what should be done.'));
    }

    $type = (string) ($action['type'] ?? '');

    // What the kinds added in 5.85 need.
    if (in_array($type, array('notify', 'task', 'webhook', 'web_check', 'report', 'trigger', 'task_digest'), true) && !ws_scheduled_chains_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    switch ($type) {

        case 'post':
            $into_joined = $joined && ((int) ($action['channel_id'] ?? 0) === -1);
            $channel = $into_joined ? null : ws_channel((int) ($action['channel_id'] ?? 0));

            if (!$into_joined && (!$channel || !ws_can_post_channel($viewer, $channel))) {
                return $fail(lang('Choose a channel you can write in.'));
            }

            $body = ws_tokens_normalise(trim((string) ($action['body'] ?? '')));

            if ($body === '') {
                return $fail(lang('Write the message to post.'));
            }

            if (mb_strlen($body) > WS_MESSAGE_MAX) {
                return $fail(lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)));
            }

            return array('ok' => true, 'error' => '', 'action' => array('type' => 'post', 'channel_id' => $into_joined ? -1 : (int) $channel['id'], 'body' => $body));

        case 'email':
            $addresses = ws_scheduled_addresses($action['to'] ?? '');

            if (!$addresses['ok']) {
                return $fail($addresses['error']);
            }

            $to = $addresses['to'];
            $subject = mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string) ($action['subject'] ?? ''))), 0, 250);
            $page_id = (int) ($action['page_id'] ?? 0);

            // A page: sent as the e-mail campaigns send one.
            if ($page_id > 0) {
                if (!db_value("SELECT page_id FROM page WHERE page_id = '" . $page_id . "'")) {
                    return $fail(lang('That page could not be found.'));
                }

                return array('ok' => true, 'error' => '', 'action' => array('type' => 'email', 'to' => $to, 'subject' => $subject, 'page_id' => $page_id, 'body' => ''));
            }

            $body = trim(str_replace("\r\n", "\n", (string) ($action['body'] ?? '')));

            if ($subject === '') {
                return $fail(lang('Write the subject of the e-mail.'));
            }

            if ($body === '') {
                return $fail(lang('Write the e-mail, or choose a page to send.'));
            }

            return array('ok' => true, 'error' => '', 'action' => array('type' => 'email', 'to' => $to, 'subject' => $subject, 'page_id' => 0, 'body' => mb_substr($body, 0, 20000)));

        // A line in the inbox of some people, with their bell and their phone.
        case 'notify':
            $people = ws_scheduled_people($action['user_ids'] ?? array());
            $text = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($action['text'] ?? ''))), 0, 500);

            if (empty($people)) {
                return $fail(lang('Choose who is to be told.'));
            }

            if ($text === '') {
                return $fail(lang('Write what they are to be told.'));
            }

            return array('ok' => true, 'error' => '', 'action' => array('type' => 'notify', 'user_ids' => $people, 'text' => $text));

        // A task, opened with the creator's rights.
        case 'task':
            $title = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($action['title'] ?? ''))), 0, 200);

            if ($title === '') {
                return $fail(lang('A task needs a title.'));
            }

            $priority = (string) ($action['priority'] ?? 'normal');

            if (!isset(ws_task_priorities()[$priority])) {
                $priority = 'normal';
            }

            $task_channel = (int) ($action['channel_id'] ?? 0);

            if ($task_channel > 0) {
                $channel = ws_channel($task_channel);

                if (!$channel || !ws_can_post_channel($viewer, $channel)) {
                    return $fail(lang('Choose a channel you can write in.'));
                }
            }

            $assignees = ws_scheduled_people($action['assignees'] ?? array());

            if (!ws_can_assign_to($viewer, $assignees)) {
                return $fail(lang('You may only give tasks to yourself or to the people in the departments you lead.'));
            }

            $due = trim((string) ($action['due_in'] ?? ''));

            return array('ok' => true, 'error' => '', 'action' => array(
                'type'        => 'task',
                'title'       => $title,
                'description' => mb_substr(trim(str_replace("\r\n", "\n", (string) ($action['description'] ?? ''))), 0, 8000),
                'priority'    => $priority,
                'assignees'   => $assignees,
                'channel_id'  => $task_channel,
                'due_in'      => ($due === '') ? '' : max(0, min(3650, (int) $due)),
            ));

        // Each person their tasks by e-mail (a weekly list, say): their own
        // open tasks, or the open tasks of one channel.
        case 'task_digest':
            $mode = ((string) ($action['mode'] ?? 'mine') === 'channel') ? 'channel' : 'mine';
            $digest_channel = max(0, (int) ($action['channel_id'] ?? 0));

            if ($digest_channel > 0) {
                $channel = ws_channel($digest_channel);

                if (!$channel || !ws_can_read_channel($viewer, $channel) || in_array((string) $channel['kind'], array('guest', 'thread'), true)) {
                    return $fail(lang('That channel could not be found.'));
                }
            } elseif ($mode === 'channel') {
                return $fail(lang('Choose the channel whose tasks are sent.'));
            }

            $only = (string) ($action['only'] ?? 'open');

            if (!isset(ws_scheduled_digest_filters()[$only])) {
                $only = 'open';
            }

            return array('ok' => true, 'error' => '', 'action' => array(
                'type'       => 'task_digest',
                'mode'       => $mode,
                'channel_id' => $digest_channel,
                'user_ids'   => ws_scheduled_people($action['user_ids'] ?? array()),
                'only'       => $only,
                'subject'    => mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string) ($action['subject'] ?? ''))), 0, 250),
                'intro'      => mb_substr(trim(str_replace("\r\n", "\n", (string) ($action['intro'] ?? ''))), 0, 2000),
            ));

        // A call to another system: Slack, Teams, n8n, Zapier, Make.
        case 'webhook':
            $url = ws_scheduled_url_input($action['url'] ?? '');

            if (!$url['ok']) {
                return $fail($url['error']);
            }

            $format = (string) ($action['format'] ?? 'generic');

            if (!isset(ws_scheduled_webhook_formats()[$format])) {
                $format = 'generic';
            }

            $text = mb_substr(trim(str_replace("\r\n", "\n", (string) ($action['text'] ?? ''))), 0, 4000);

            if (($format !== 'generic') && ($text === '')) {
                return $fail(lang('Write the message to send.'));
            }

            return array('ok' => true, 'error' => '', 'action' => array('type' => 'webhook', 'url' => $url['url'], 'format' => $format, 'text' => $text));

        // Does a web address answer, and how long has its certificate left?
        case 'web_check':
            $url = ws_scheduled_url_input($action['url'] ?? '');

            if (!$url['ok']) {
                return $fail($url['error']);
            }

            $mode = ((string) ($action['mode'] ?? 'status') === 'ssl') ? 'ssl' : 'status';

            if ($mode === 'ssl') {
                return array('ok' => true, 'error' => '', 'action' => array('type' => 'web_check', 'url' => $url['url'], 'mode' => 'ssl', 'days' => max(1, min(365, (int) ($action['days'] ?? 14)))));
            }

            return array('ok' => true, 'error' => '', 'action' => array(
                'type'     => 'web_check',
                'url'      => $url['url'],
                'mode'     => 'status',
                'redirect' => !empty($action['redirect']),
                'contains' => mb_substr(trim((string) ($action['contains'] ?? '')), 0, 200),
                'max_ms'   => max(0, min(60000, (int) ($action['max_ms'] ?? 0))),
            ));

        // The counts, written in a channel and / or e-mailed.
        case 'report':
            $metrics = ws_scheduled_metrics();
            $list = array();

            foreach ((array) ($action['metrics'] ?? array()) as $item) {
                $key = is_array($item) ? (string) ($item['key'] ?? '') : (string) $item;
                $param = is_array($item) ? max(0, (int) ($item['param'] ?? 0)) : 0;

                if (isset($metrics[$key]) && (($metrics[$key]['right'] === '') || !empty($viewer[$metrics[$key]['right']]))) {
                    $list[] = array('key' => $key, 'param' => $param);
                }
            }

            if (empty($list)) {
                return $fail(lang('Choose the counts the summary lists.'));
            }

            $report_channel = (int) ($action['channel_id'] ?? 0);

            if ($report_channel > 0) {
                $channel = ws_channel($report_channel);

                if (!$channel || !ws_can_post_channel($viewer, $channel)) {
                    return $fail(lang('Choose a channel you can write in.'));
                }
            }

            $addresses = ws_scheduled_addresses($action['to'] ?? '', false);

            if (!$addresses['ok']) {
                return $fail($addresses['error']);
            }

            if (($report_channel <= 0) && empty($addresses['to'])) {
                return $fail(lang('Say where the summary goes: a channel, e-mail addresses or both.'));
            }

            return array('ok' => true, 'error' => '', 'action' => array(
                'type'       => 'report',
                'title'      => mb_substr(trim((string) ($action['title'] ?? '')), 0, 160),
                'metrics'    => array_slice($list, 0, 20),
                'channel_id' => $report_channel,
                'to'         => $addresses['to'],
            ));

        // Another scheduled action, now or a while later.
        case 'trigger':
            $target_id = (int) ($action['target_id'] ?? 0);
            $target = ws_scheduled_action($target_id);

            if (!$target || in_array($target['status'], array('cancelled', 'done'), true)) {
                return $fail(lang('Choose a scheduled action that is set to run.'));
            }

            $delay = max(0, min(10080, (int) ($action['delay'] ?? 0)));

            // Starting itself again is a retry: it has to wait at least a
            // minute, and the depth of the chain still stops it.
            if (($self_id > 0) && ($target_id === (int) $self_id) && ($delay < 1)) {
                return $fail(lang('An action that starts itself again has to wait at least a minute.'));
            }

            return array('ok' => true, 'error' => '', 'action' => array('type' => 'trigger', 'target_id' => $target_id, 'delay' => $delay));

        // An approval request in a channel (approvals.php): its title, its
        // text, the people asked and the rule. The people are checked
        // against the channel now and again when it runs.
        case 'approval':
            $channel = ws_channel((int) ($action['channel_id'] ?? 0));

            if (!$channel || !function_exists('ws_approval_input')) {
                return $fail(lang('Choose a channel you can write in.'));
            }

            $checked = ws_approval_input($viewer, $channel, array(
                'title'     => (string) ($action['title'] ?? ''),
                'text'      => (string) ($action['text'] ?? ''),
                'approvers' => (array) ($action['approvers'] ?? array()),
                'rule'      => (string) ($action['rule'] ?? 'any'),
            ));

            if (!$checked['ok']) {
                return $fail($checked['error']);
            }

            return array('ok' => true, 'error' => '', 'action' => array(
                'type'       => 'approval',
                'channel_id' => (int) $channel['id'],
                'title'      => $checked['title'],
                'text'       => mb_substr($checked['text'], 0, WS_MESSAGE_MAX),
                'approvers'  => $checked['approvers'],
                'rule'       => $checked['rule'],
            ));

        case 'change':
            $types = ws_change_types();
            $record_type = (string) ($action['record'] ?? '');
            $verb = (string) ($action['action'] ?? 'update');
            $id = (int) ($action['id'] ?? 0);

            if (!isset($types[$record_type]) || ($record_type === 'channel')) {
                return $fail(lang('Choose what kind of record to change.'));
            }

            if (!in_array($verb, array_intersect($types[$record_type]['actions'], array('update', 'delete')), true)) {
                return $fail(lang(array('string' => 'A {var:1} takes the actions {var:2}.', 'vars' => array($record_type, implode(', ', $types[$record_type]['actions'])))));
            }

            if (!ws_change_allowed($viewer, $record_type, $id)) {
                return $fail(lang('You may not change a record of this kind.'));
            }

            if (($id <= 0) || (ws_change_record($record_type, $id) === null)) {
                return $fail(lang('Choose the record to change.'));
            }

            $fields = array();

            if ($verb === 'update') {
                $allowed = ws_change_fields_for($types[$record_type], 'update');

                foreach ((array) ($action['fields'] ?? array()) as $name => $value) {
                    $name = (string) $name;

                    if (!isset($allowed[$name])) {
                        return $fail(lang(array('string' => '{var:1} cannot be changed. The fields are: {var:2}.', 'vars' => array($name, implode(', ', array_keys($allowed))))));
                    }

                    if (ws_change_value($allowed[$name], $value) === null) {
                        return $fail(lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => $allowed[$name][2])));
                    }

                    $fields[$name] = $value;
                }

                if (empty($fields)) {
                    return $fail(lang('Choose at least one field and its new value.'));
                }
            }

            return array('ok' => true, 'error' => '', 'action' => array(
                'type'   => 'change',
                'record' => $record_type,
                'action' => $verb,
                'id'     => $id,
                'fields' => $fields,
            ));
    }

    return $fail(lang('Choose what should be done.'));
}

/**
 * A record a rule looks at: a kind of change's own reader, or a task.
 *
 * @param string $type
 * @param int    $id
 * @return array|null
 */
function ws_scheduled_record($type, $id)
{
    if ($type === 'task') {
        return ws_task((int) $id);
    }

    return ws_change_record($type, (int) $id);
}

// ─── When ───────────────────────────────────────────────────────────────

/**
 * The next moment a time rule falls on after a given one, 0 for none. The
 * clock of the day stays the same for the rules that count in days; a
 * monthly rule on the 31st falls on the last day of a shorter month, a
 * yearly one on 29 February on the 28th; a rule for working days steps over
 * weekends and the company's days off (includes/workspace/workdays.php); a
 * weekly rule with days runs on each of them, every so many weeks counted
 * from the week of its first day. Nothing falls after the rule's last day.
 *
 * @param array $rule  type at: date, time, repeat, every, days, until
 * @param int   $after
 * @return int
 */
function ws_scheduled_next_run($rule, $after)
{
    $first = strtotime($rule['date'] . ' ' . $rule['time'] . ':00');

    if ($first === false) {
        return 0;
    }

    $repeat = (string) ($rule['repeat'] ?? 'none');
    $until = ((string) ($rule['until'] ?? '') !== '') ? strtotime($rule['until'] . ' 23:59:59') : 0;
    $every = max(1, (int) ($rule['every'] ?? 1));
    $next = 0;

    $cap = function ($time) use ($until) {
        return (($time > 0) && (($until <= 0) || ($time <= $until))) ? $time : 0;
    };

    if ($repeat === 'none') {
        return ($first > $after) ? $first : 0;
    }

    // Steps of a fixed length: counted from the first time, so the minutes
    // stay where they were set.
    if (($repeat === 'minutes') || ($repeat === 'hourly')) {
        $step = ($repeat === 'minutes') ? max(WS_SCHEDULED_MIN_MINUTES, $every) * 60 : $every * 3600;

        if ($first > $after) {
            return $cap($first);
        }

        return $cap($first + ((int) floor(($after - $first) / $step) + 1) * $step);
    }

    $clock = $rule['time'] . ':00';
    $day = (int) date('j', $first);
    $month_day = date('m-d', $first);

    // A cursor near "after" rather than at the first day, keeping the rhythm
    // of the rule: whole steps of "every" from the first day.
    $start_day = strtotime(date('Y-m-d', $first) . ' 12:00:00');
    $after_day = strtotime(date('Y-m-d', max($after, $first)) . ' 12:00:00');
    $days_between = max(0, (int) round(($after_day - $start_day) / 86400));
    $cursor = $first;

    if (in_array($repeat, array('daily', 'workdays'), true) && ($days_between > 2)) {
        $skip = (int) (floor(($days_between - 1) / $every) * $every);
        $cursor = strtotime(date('Y-m-d', strtotime('+' . $skip . ' days', $start_day)) . ' ' . $clock);
    }

    if ($repeat === 'weekly') {
        $days = !empty($rule['days']) ? array_map('intval', (array) $rule['days']) : array((int) date('N', $first));
        $week_of = function ($time) {
            // The Monday of the week, as a day count.
            return (int) floor((strtotime(date('Y-m-d', $time) . ' 12:00:00') - ((int) date('N', $time) - 1) * 86400) / 86400 / 7);
        };
        $first_week = $week_of($first);
        $probe = ($days_between > 14) ? strtotime(date('Y-m-d', strtotime('-7 days', $after_day)) . ' ' . $clock) : $first;

        for ($i = 0; $i < 800; $i++) {
            if (($probe >= $first) && ($probe > $after) && in_array((int) date('N', $probe), $days, true)
                && ((($week_of($probe) - $first_week) % $every) === 0)) {
                return $cap($probe);
            }

            $probe = strtotime(date('Y-m-d', strtotime('+1 day', strtotime(date('Y-m-d', $probe) . ' 12:00:00'))) . ' ' . $clock);

            if (($probe === false) || (($until > 0) && ($probe > $until))) {
                return 0;
            }
        }

        return 0;
    }

    if ($repeat === 'monthly') {
        $months = (((int) date('Y', $after_day) - (int) date('Y', $first)) * 12) + ((int) date('n', $after_day) - (int) date('n', $first));

        if ($months > 2) {
            $skip = (int) (floor(($months - 1) / $every) * $every);
            $base = strtotime(date('Y-m-01', $first) . ' 12:00:00 +' . $skip . ' months');
            $cursor = strtotime(date('Y-m-', $base) . sprintf('%02d', min($day, (int) date('t', $base))) . ' ' . $clock);
        }
    }

    if ($repeat === 'yearly') {
        $years = (int) date('Y', $after_day) - (int) date('Y', $first);

        if ($years > 1) {
            $skip = (int) (floor(($years - 1) / $every) * $every);
            $year = (int) date('Y', $first) + $skip;
            $cursor = strtotime(ws_scheduled_year_day($year, $month_day) . ' ' . $clock);
        }
    }

    for ($i = 0; $i < 1000; $i++) {
        if (($cursor > $after) && (($repeat !== 'workdays') || !function_exists('ws_day_off') || (ws_day_off(date('Y-m-d', $cursor)) === ''))) {
            return $cap($cursor);
        }

        switch ($repeat) {
            case 'monthly':
                $month = strtotime(date('Y-m-01', $cursor) . ' 12:00:00 +' . $every . ' month');
                $last = (int) date('t', $month);
                $cursor = strtotime(date('Y-m-', $month) . sprintf('%02d', min($day, $last)) . ' ' . $clock);
                break;

            case 'yearly':
                $cursor = strtotime(ws_scheduled_year_day((int) date('Y', $cursor) + $every, $month_day) . ' ' . $clock);
                break;

            case 'workdays':
                $cursor = strtotime(date('Y-m-d', strtotime('+1 day', strtotime(date('Y-m-d', $cursor) . ' 12:00:00'))) . ' ' . $clock);
                break;

            default:
                $cursor = strtotime(date('Y-m-d', strtotime('+' . $every . ' days', strtotime(date('Y-m-d', $cursor) . ' 12:00:00'))) . ' ' . $clock);
        }

        if (($cursor === false) || (($until > 0) && ($cursor > $until))) {
            return 0;
        }
    }

    return $next;
}

/**
 * A month and day in a year: 29 February is the 28th where there is none.
 *
 * @param int    $year
 * @param string $month_day mm-dd
 * @return string Y-m-d
 */
function ws_scheduled_year_day($year, $month_day)
{
    list($month, $day) = array_map('intval', explode('-', $month_day));
    $last = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month) . ' 12:00:00'));

    return sprintf('%04d-%02d-%02d', $year, $month, min($day, $last));
}

/**
 * The time rule of an action.
 *
 * @param array $row decoded
 * @return array|null
 */
function ws_scheduled_time_rule($row)
{
    foreach ((array) $row['rules'] as $rule) {
        if (($rule['type'] ?? '') === 'at') {
            return $rule;
        }
    }

    return null;
}

/**
 * Has the action no time of its own: another one starts it, somebody joining
 * a channel does, or somebody by hand?
 *
 * @param array $row decoded
 * @return bool
 */
function ws_scheduled_trigger_only($row)
{
    return ws_scheduled_event_rule($row) !== null;
}

/**
 * The rule that starts an action with no time of its own: trigger (another
 * action), join (somebody joining a channel) or event (something happening
 * on the site).
 *
 * @param array $row decoded
 * @return array|null
 */
function ws_scheduled_event_rule($row)
{
    foreach ((array) $row['rules'] as $rule) {
        if (in_array(($rule['type'] ?? ''), array('trigger', 'join', 'event'), true)) {
            return $rule;
        }
    }

    return null;
}

// ─── Saying it in words ─────────────────────────────────────────────────

/**
 * A moment as the screens write it: the date and the clock.
 *
 * @param int $time
 * @return string
 */
function ws_scheduled_moment($time)
{
    $time = (int) $time;

    if ($time <= 0) {
        return '';
    }

    $day = date('Y-m-d', $time);
    $clock = date('H:i', $time);

    if ($day === date('Y-m-d')) {
        return lang('Today') . ' ' . $clock;
    }

    if ($day === date('Y-m-d', strtotime('+1 day'))) {
        return lang('Tomorrow') . ' ' . $clock;
    }

    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return lang('Yesterday') . ' ' . $clock;
    }

    return date((date('Y', $time) === date('Y')) ? 'd.m' : 'd.m.Y', $time) . ' ' . $clock;
}

/**
 * Days of the week in words: "Monday, Wednesday".
 *
 * @param int[] $days
 * @return string
 */
function ws_scheduled_days_text($days)
{
    $names = function_exists('ws_recurrence_weekday_names') ? ws_recurrence_weekday_names() : array();
    $out = array();

    foreach ((array) $days as $day) {
        $out[] = $names[(int) $day] ?? (string) $day;
    }

    return implode(', ', $out);
}

/**
 * A rule in words.
 *
 * @param array $viewer
 * @param array $rule
 * @return string
 */
function ws_scheduled_rule_text($viewer, $rule)
{
    switch ($rule['type'] ?? '') {

        case 'at':
            $time = strtotime($rule['date'] . ' ' . $rule['time'] . ':00');
            $repeat = (string) ($rule['repeat'] ?? 'none');
            $every = max(1, (int) ($rule['every'] ?? 1));
            $from = date('d.m.Y', $time);

            if ($repeat === 'none') {
                return lang(array('string' => 'On {var:1} at {var:2}', 'vars' => array($from, $rule['time'])));
            }

            switch ($repeat) {
                case 'minutes':
                    $text = lang(array('string' => 'Every {var:1} minutes, from {var:2} {var:3}', 'vars' => array($every, $from, $rule['time'])));
                    break;

                case 'hourly':
                    $text = ($every > 1)
                        ? lang(array('string' => 'Every {var:1} hours, from {var:2} {var:3}', 'vars' => array($every, $from, $rule['time'])))
                        : lang(array('string' => 'Every hour, from {var:1} {var:2}', 'vars' => array($from, $rule['time'])));
                    break;

                case 'daily':
                    $text = ($every > 1)
                        ? lang(array('string' => 'Every {var:1} days at {var:2}, from {var:3}', 'vars' => array($every, $rule['time'], $from)))
                        : lang(array('string' => 'Every day at {var:1}, from {var:2}', 'vars' => array($rule['time'], $from)));
                    break;

                case 'workdays':
                    $text = lang(array('string' => 'Every working day at {var:1}, from {var:2}', 'vars' => array($rule['time'], $from)));
                    break;

                case 'weekly':
                    $days = !empty($rule['days']) ? ws_scheduled_days_text($rule['days']) : ws_scheduled_days_text(array((int) date('N', $time)));
                    $text = ($every > 1)
                        ? lang(array('string' => 'Every {var:1} weeks on {var:2} at {var:3}, from {var:4}', 'vars' => array($every, $days, $rule['time'], $from)))
                        : lang(array('string' => 'Every week on {var:1} at {var:2}, from {var:3}', 'vars' => array($days, $rule['time'], $from)));
                    break;

                case 'monthly':
                    $text = ($every > 1)
                        ? lang(array('string' => 'Every {var:1} months on day {var:2} at {var:3}, from {var:4}', 'vars' => array($every, (int) date('j', $time), $rule['time'], $from)))
                        : lang(array('string' => 'Every month on day {var:1} at {var:2}, from {var:3}', 'vars' => array((int) date('j', $time), $rule['time'], $from)));
                    break;

                case 'yearly':
                    $text = lang(array('string' => 'Every year on {var:1} at {var:2}', 'vars' => array(date('d.m', $time), $rule['time'])));
                    break;

                default:
                    $text = (string) (ws_scheduled_repeats()[$repeat] ?? '');
            }

            if ((string) ($rule['until'] ?? '') !== '') {
                $text .= ' ' . lang(array('string' => 'until {var:1}', 'vars' => date('d.m.Y', strtotime($rule['until'] . ' 12:00:00'))));
            }

            return $text;

        case 'trigger':
            return lang('When another scheduled action starts it, or by hand');

        case 'join':
            $join_channel = ((int) ($rule['channel_id'] ?? 0) > 0) ? ws_channel((int) $rule['channel_id']) : null;

            return ((int) ($rule['channel_id'] ?? 0) > 0)
                ? lang(array('string' => 'When somebody joins {var:1}', 'vars' => $join_channel ? '#' . $join_channel['name'] : lang('a channel that is gone')))
                : lang('When somebody joins a public channel');

        case 'event':
            $info = function_exists('ws_events_catalog') ? (ws_events_catalog()[$rule['event'] ?? ''] ?? null) : null;

            if ($info === null) {
                return lang('When something happens that this site no longer tells');
            }

            if ((int) ($rule['page_id'] ?? 0) > 0) {
                $forms = ws_events_forms();

                return lang(array('string' => 'When a form is submitted: {var:1}', 'vars' => $forms[(int) $rule['page_id']] ?? lang('a form that is gone')));
            }

            if ((string) ($rule['status'] ?? '') !== '') {
                return lang(array('string' => 'When the status of an order becomes “{var:1}”', 'vars' => ws_events_order_statuses()[$rule['status']] ?? $rule['status']));
            }

            return (string) $info['when'];

        case 'workday':
            return lang('Only on a working day');

        case 'weekdays':
            return lang(array('string' => 'Only on {var:1}', 'vars' => ws_scheduled_days_text($rule['days'] ?? array())));

        case 'hours':
            return lang(array('string' => 'Only between {var:1} and {var:2}', 'vars' => array($rule['from'], $rule['to'])));

        case 'metric':
            $operators = ws_scheduled_operators();
            $metric = ws_scheduled_metrics()[$rule['metric']] ?? null;
            $value = ($metric && ($metric['kind'] === 'money')) ? ws_money_out((int) $rule['value']) : (($metric && ($metric['kind'] === 'minutes')) ? ws_minutes_label((int) $rule['value']) : (string) (int) $rule['value']);

            return lang(array('string' => 'If {var:1} {var:2} {var:3}', 'vars' => array(
                ws_scheduled_metric_label($rule['metric'], (int) ($rule['param'] ?? 0)), $operators[$rule['op']] ?? $rule['op'], $value)));

        case 'record':
            $types = ws_scheduled_record_types();
            $info = $types[$rule['record']] ?? null;
            $field = $info['fields'][$rule['field']] ?? null;
            $operators = ws_scheduled_operators();
            $name = ws_scheduled_record_label($viewer, $rule['record'], $rule['id']);

            if (in_array($rule['op'], array('empty', 'not_empty', 'overdue'), true)) {
                $value = '';
            } elseif ($rule['op'] === 'within') {
                $value = ' ' . lang(array('string' => '({var:1} days)', 'vars' => (int) $rule['value']));
            } else {
                $value = ' “' . ws_scheduled_value_label($rule['record'], $rule['field'], $rule['value']) . '”';
            }

            return lang(array('string' => 'If {var:1}: {var:2} {var:3}{var:4}', 'vars' => array(
                $name, $field ? $field[2] : $rule['field'], $operators[$rule['op']] ?? $rule['op'], $value)));
    }

    return '';
}

/**
 * A record named as the screens name it: its kind and its name.
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $id
 * @return string
 */
function ws_scheduled_record_label($viewer, $type, $id)
{
    $types = ws_scheduled_record_types();
    $label = $types[$type]['label'] ?? $type;

    if ($type === 'task') {
        $task = ws_task((int) $id);

        return $task ? ws_task_number($task['id']) . ' · ' . $task['title'] : $label . ' #' . (int) $id;
    }

    $record = ws_change_record($type, (int) $id);

    if ($record === null) {
        return $label . ' #' . (int) $id . ' (' . lang('gone') . ')';
    }

    return $label . ' “' . ws_change_record_name($type, $record) . '”';
}

/**
 * A value as the screen shows it: an order state by its name, a boolean as
 * yes / no, anything else as it is.
 *
 * @param string $type
 * @param string $field_name
 * @param mixed  $value
 * @return string
 */
function ws_scheduled_value_label($type, $field_name, $value)
{
    if (($type === 'task') && ($field_name === 'status')) {
        return (string) (ws_task_statuses()[$value] ?? $value);
    }

    if (($type === 'task') && ($field_name === 'priority')) {
        return (string) (ws_task_priorities()[$value] ?? $value);
    }

    if (($type !== 'task') && function_exists('ws_change_show')) {
        return (string) ws_change_show($type, $field_name, $value, 120);
    }

    return (string) $value;
}

/**
 * The name of another scheduled action, for the words of a chain.
 *
 * @param int $id
 * @return string
 */
function ws_scheduled_name($id)
{
    static $names = array();

    $id = (int) $id;

    if (!isset($names[$id])) {
        $name = ($id > 0) ? db_value("SELECT name FROM ws_scheduled_actions WHERE id = '" . $id . "'") : null;
        $names[$id] = ($name === null || $name === false) ? lang('a scheduled action that is gone') : '“' . $name . '”';
    }

    return $names[$id];
}

/**
 * An action in words.
 *
 * @param array $viewer
 * @param array $action
 * @return string
 */
function ws_scheduled_action_text($viewer, $action)
{
    if (!is_array($action)) {
        return lang('Nothing more');
    }

    switch ($action['type'] ?? '') {

        case 'post':
            $channel = ((int) $action['channel_id'] > 0) ? ws_channel((int) $action['channel_id']) : null;

            if ((int) $action['channel_id'] === -1) {
                return lang(array('string' => 'Write in the channel they joined: “{var:1}”', 'vars' => ws_plain_excerpt($viewer, (string) $action['body'], 140)));
            }

            return lang(array('string' => 'Write in {var:1}: “{var:2}”', 'vars' => array(
                $channel ? '#' . $channel['name'] : lang('a channel that is gone'), ws_plain_excerpt($viewer, (string) $action['body'], 140))));

        case 'task_digest':
            $channel = ((int) ($action['channel_id'] ?? 0) > 0) ? ws_channel((int) $action['channel_id']) : null;
            $who = !empty($action['user_ids'])
                ? implode(', ', array_filter(array_map('ws_person_name', (array) $action['user_ids'])))
                : ((($action['mode'] ?? '') === 'channel') ? lang('the members of the channel') : lang('everybody with open tasks'));
            $what = (string) (ws_scheduled_digest_filters()[$action['only'] ?? 'open'] ?? '');

            return (($action['mode'] ?? '') === 'channel')
                ? lang(array('string' => 'E-mail the tasks of {var:1} ({var:2}) to {var:3}', 'vars' => array($channel ? '#' . $channel['name'] : lang('a channel that is gone'), $what, $who)))
                : lang(array('string' => 'E-mail each person their tasks ({var:1}{var:2}) · {var:3}', 'vars' => array($what, $channel ? ', #' . $channel['name'] : '', $who)));

        case 'email':
            $to = implode(', ', (array) $action['to']);

            if ((int) $action['page_id'] > 0) {
                $page = db_item("SELECT page_name FROM page WHERE page_id = '" . (int) $action['page_id'] . "'");

                return lang(array('string' => 'E-mail the page {var:1} to {var:2}', 'vars' => array(
                    is_array($page) ? '“' . $page['page_name'] . '”' : '#' . (int) $action['page_id'], $to)));
            }

            return lang(array('string' => 'E-mail “{var:1}” to {var:2}', 'vars' => array($action['subject'], $to)));

        case 'notify':
            return lang(array('string' => 'Let {var:1} know: “{var:2}”', 'vars' => array(
                implode(', ', array_filter(array_map('ws_person_name', (array) $action['user_ids']))), mb_substr((string) $action['text'], 0, 140))));

        case 'task':
            $people = array_filter(array_map('ws_person_name', (array) ($action['assignees'] ?? array())));

            return lang(array('string' => 'Open the task “{var:1}”{var:2}', 'vars' => array(
                $action['title'], empty($people) ? '' : ' → ' . implode(', ', $people))));

        case 'webhook':
            return lang(array('string' => 'Call the webhook {var:1}', 'vars' => ws_scheduled_host($action['url'])));

        case 'web_check':
            if (($action['mode'] ?? '') === 'ssl') {
                return lang(array('string' => 'Check that the certificate of {var:1} has at least {var:2} days left', 'vars' => array(ws_scheduled_host($action['url']), (int) $action['days'])));
            }

            return lang(array('string' => 'Check that {var:1} answers', 'vars' => $action['url']))
                . (((string) ($action['contains'] ?? '') !== '') ? ' · ' . lang(array('string' => 'with “{var:1}” on it', 'vars' => $action['contains'])) : '')
                . (((int) ($action['max_ms'] ?? 0) > 0) ? ' · ' . lang(array('string' => 'within {var:1} ms', 'vars' => (int) $action['max_ms'])) : '');

        case 'report':
            $names = array();

            foreach ((array) $action['metrics'] as $item) {
                $names[] = ws_scheduled_metric_label($item['key'], (int) ($item['param'] ?? 0));
            }

            $where = array();
            $channel = ((int) $action['channel_id'] > 0) ? ws_channel((int) $action['channel_id']) : null;

            if ($channel) {
                $where[] = '#' . $channel['name'];
            }

            if (!empty($action['to'])) {
                $where[] = implode(', ', (array) $action['to']);
            }

            return lang(array('string' => 'Post a summary to {var:1}: {var:2}', 'vars' => array(implode(' · ', $where), implode(', ', $names))));

        case 'approval':
            $channel = ((int) ($action['channel_id'] ?? 0) > 0) ? ws_channel((int) $action['channel_id']) : null;

            return lang(array('string' => 'Ask {var:1} to approve “{var:2}” in {var:3}', 'vars' => array(
                implode(', ', array_filter(array_map('ws_person_name', (array) ($action['approvers'] ?? array())))),
                (string) ($action['title'] ?? ''),
                $channel ? '#' . $channel['name'] : lang('a channel that is gone'))));

        case 'trigger':
            return ((int) ($action['delay'] ?? 0) > 0)
                ? lang(array('string' => 'Start {var:1} {var:2} minutes later', 'vars' => array(ws_scheduled_name($action['target_id']), (int) $action['delay'])))
                : lang(array('string' => 'Start {var:1}', 'vars' => ws_scheduled_name($action['target_id'])));

        case 'change':
            $types = ws_change_types();
            $name = ws_scheduled_record_label($viewer, $action['record'], $action['id']);

            if ($action['action'] === 'delete') {
                return lang(array('string' => 'Delete {var:1}', 'vars' => $name));
            }

            $parts = array();

            foreach ((array) $action['fields'] as $field_name => $value) {
                $field = $types[$action['record']]['fields'][$field_name] ?? null;
                $parts[] = ($field ? $field[2] : $field_name) . ' → “' . ws_scheduled_value_label($action['record'], $field_name, $value) . '”';
            }

            return lang(array('string' => 'Change {var:1}: {var:2}', 'vars' => array($name, implode(', ', $parts))));
    }

    return '';
}

/**
 * The host of an address, for the words: a webhook's address may carry its
 * secret in the path, so only its host is shown.
 *
 * @param string $url
 * @return string
 */
function ws_scheduled_host($url)
{
    $host = parse_url((string) $url, PHP_URL_HOST);

    return is_string($host) ? $host : '';
}

/**
 * The words of a state.
 *
 * @return array state => label
 */
function ws_scheduled_states()
{
    return array(
        'active'    => lang('Set to run'),
        'paused'    => lang('Paused'),
        'done'      => lang('Done'),
        'failed'    => lang('Failed'),
        'cancelled' => lang('Cancelled'),
    );
}

/**
 * Which actions start which: id => the ids that start it, read once per
 * request (there are few).
 *
 * @return array target id => source ids
 */
function ws_scheduled_starters()
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $map = array();

    if (!ws_scheduled_chains_ready()) {
        return $map;
    }

    foreach ((array) db_items("SELECT id, action, follow FROM ws_scheduled_actions WHERE status IN ('active', 'paused', 'failed')" . ws_scheduled_actions_only()) as $row) {
        $row = ws_scheduled_decode($row + array('rules' => '[]', 'then_action' => ''));
        $actions = array($row['action']);

        foreach ((array) $row['follow'] as $follow) {
            $actions[] = $follow['do'] ?? null;
        }

        foreach ($actions as $action) {
            if (is_array($action) && (($action['type'] ?? '') === 'trigger')) {
                $map[(int) $action['target_id']][] = (int) $row['id'];
            }
        }
    }

    return $map;
}

/**
 * One scheduled action as the screens show it. Somebody who is not staff
 * sees that there is one, its name, when and in what state - never what it
 * does.
 *
 * @param array $viewer
 * @param array $row decoded
 * @param bool  $full with the latest runs
 * @return array
 */
function ws_scheduled_present($viewer, $row, $full = false)
{
    $staff = ws_can_schedule($viewer);
    $states = ws_scheduled_states();
    $time = ws_scheduled_time_rule($row);
    $trigger_only = ws_scheduled_trigger_only($row);
    $channel = ((int) $row['channel_id'] > 0) ? ws_channel($row['channel_id']) : null;
    $note = (((int) $row['note_id'] > 0) && function_exists('ws_note')) ? ws_note($row['note_id']) : null;
    $last = null;

    if ((int) $row['last_run_at'] > 0) {
        $last = db_item("SELECT status, detail, finished_at FROM ws_scheduled_runs
            WHERE action_id = '" . (int) $row['id'] . "' AND finished_at > 0 ORDER BY id DESC LIMIT 1");
    }

    $out = array(
        'id'          => (int) $row['id'],
        'name'        => (string) $row['name'],
        'status'      => (string) $row['status'],
        'status_label' => $states[$row['status']] ?? $row['status'],
        'next'        => (((string) $row['status'] === 'active') && ((int) $row['next_run_at'] > 0)) ? ws_scheduled_moment($row['next_run_at']) : '',
        'when'        => $time ? ws_scheduled_rule_text($viewer, $time) : ($trigger_only ? ws_scheduled_rule_text($viewer, ws_scheduled_event_rule($row)) : ''),
        'joins'       => (($event = ws_scheduled_event_rule($row)) !== null) && ($event['type'] === 'join'),
        'on_event'    => ($event !== null) && ($event['type'] === 'event'),
        'repeats'     => $time && (($time['repeat'] ?? 'none') !== 'none'),
        'trigger_only' => $trigger_only,
        'runs'        => (int) $row['run_count'],
        'last'        => is_array($last) ? array(
            'status' => (string) $last['status'],
            'label'  => ws_scheduled_run_label($last['status']),
            'time'   => ws_scheduled_moment($last['finished_at']),
            'text'   => $staff ? ws_scheduled_run_summary($last) : '',
        ) : null,
        'staff'       => $staff,
    );

    if (!$staff) {
        return $out;
    }

    $rules = array();

    foreach ((array) $row['rules'] as $rule) {
        if (!in_array(($rule['type'] ?? ''), array('at', 'trigger', 'join', 'event'), true)) {
            $rules[] = ws_scheduled_rule_text($viewer, $rule);
        }
    }

    $outcomes = ws_scheduled_outcomes();
    $follow = array();

    foreach ((array) $row['follow'] as $item) {
        $follow[] = array(
            'on'      => (string) $item['on'],
            'label'   => $outcomes[$item['on']] ?? '',
            'text'    => ws_scheduled_action_text($viewer, $item['do']),
            'target'  => ((($item['do']['type'] ?? '') === 'trigger')) ? (int) $item['do']['target_id'] : 0,
        );
    }

    $starters = array();

    foreach (ws_scheduled_starters()[(int) $row['id']] ?? array() as $source_id) {
        $starters[] = array('id' => (int) $source_id, 'name' => trim(ws_scheduled_name($source_id), '“”'));
    }

    $out += array(
        'conditions'  => $rules,
        'action_text' => ws_scheduled_action_text($viewer, $row['action']),
        'follow'      => $follow,
        'then_text'   => '',
        'starters'    => $starters,
        'channel'     => $channel ? array('id' => (int) $channel['id'], 'name' => (string) $channel['name']) : null,
        'note'        => $note ? array('id' => (int) $note['id'], 'name' => ws_note_title($viewer, $note)) : null,
        'message_id'  => (int) $row['message_id'],
        'creator'     => current(ws_people(array((int) $row['created_by']))) ?: null,
        'created'     => ws_time_label($row['created_at']),
        'can_edit'    => in_array($row['status'], array('active', 'paused', 'failed'), true),
        // What the form is filled with when it is changed.
        'data'        => ws_scheduled_form_data($viewer, $row),
    );

    if ($full) {
        $out['history'] = array();

        foreach ((array) db_items("SELECT * FROM ws_scheduled_runs WHERE action_id = '" . (int) $row['id'] . "' AND finished_at > 0 ORDER BY id DESC LIMIT 20") as $run) {
            $out['history'][] = array(
                'status'  => (string) $run['status'],
                'label'   => ws_scheduled_run_label($run['status']),
                'time'    => ws_scheduled_moment($run['finished_at'] ?: $run['started_at']),
                'text'    => ws_scheduled_run_summary($run),
                'by_hand' => !empty($run['by_hand']),
                'source'  => ((int) ($run['source_action_id'] ?? 0) > 0) ? trim(ws_scheduled_name($run['source_action_id']), '“”') : '',
            );
        }
    }

    return $out;
}

/**
 * The parts of an action as the form takes them, with the names of the
 * records they name so its search boxes show them.
 *
 * @param array $viewer
 * @param array $row decoded
 * @return array name, rules, action, follow
 */
function ws_scheduled_form_data($viewer, $row)
{
    $name_of = function ($type, $id) use ($viewer) {
        if ($type === 'task') {
            $task = ws_task((int) $id);

            return $task ? ws_task_number($task['id']) . ' · ' . $task['title'] : '#' . (int) $id;
        }

        $record = ws_change_record($type, (int) $id);

        return $record ? ws_change_record_name($type, $record) : '#' . (int) $id;
    };

    $label = function ($action) use ($name_of) {
        if (!is_array($action)) {
            return $action;
        }

        if (($action['type'] ?? '') === 'change') {
            $action['label'] = $name_of($action['record'], $action['id']);
        }

        if ((($action['type'] ?? '') === 'email') && ((int) ($action['page_id'] ?? 0) > 0)) {
            $action['page_label'] = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . (int) $action['page_id'] . "'");
        }

        return $action;
    };

    $rules = array();

    foreach ((array) $row['rules'] as $rule) {
        if (($rule['type'] ?? '') === 'record') {
            $rule['label'] = $name_of($rule['record'], $rule['id']);
        }

        $rules[] = $rule;
    }

    $follow = array();

    foreach ((array) $row['follow'] as $item) {
        $follow[] = array('on' => $item['on'], 'do' => $label($item['do']));
    }

    return array(
        'name'   => (string) $row['name'],
        'rules'  => $rules,
        'action' => $label($row['action']),
        'follow' => $follow,
    );
}

/**
 * The word for how a run went.
 *
 * @param string $status
 * @return string
 */
function ws_scheduled_run_label($status)
{
    $labels = array('done' => lang('Ran'), 'skipped' => lang('Skipped'), 'failed' => lang('Failed'));

    return $labels[$status] ?? (string) $status;
}

/**
 * What a run did, in a line: each step and how it went.
 *
 * @param array $run status, detail (JSON)
 * @return string
 */
function ws_scheduled_run_summary($run)
{
    $detail = json_decode((string) ($run['detail'] ?? ''), true);

    if (!is_array($detail)) {
        return '';
    }

    $parts = array();

    foreach ($detail as $step) {
        $parts[] = (string) ($step['text'] ?? '');
    }

    return implode(' · ', array_filter($parts));
}

/**
 * The cards of the scheduled actions written in these messages.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message id => card
 */
function ws_scheduled_cards_map($viewer, $message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_scheduled_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_scheduled_actions WHERE message_id IN (" . implode(',', $message_ids) . ")") as $row) {
        $out[(int) $row['message_id']] = ws_scheduled_present($viewer, ws_scheduled_decode($row));
    }

    return $out;
}

/**
 * The scheduled actions of a note, for its screen.
 *
 * @param array $viewer
 * @param int   $note_id
 * @return array[]
 */
function ws_scheduled_for_note($viewer, $note_id)
{
    if (!ws_can_schedule($viewer) || ((int) $note_id <= 0)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_scheduled_actions WHERE note_id = '" . (int) $note_id . "' AND status <> 'cancelled' ORDER BY id") as $row) {
        $out[] = ws_scheduled_present($viewer, ws_scheduled_decode($row));
    }

    return $out;
}

/**
 * The list of the screen: every scheduled action, the ones still to run
 * first by their next time, then the rest newest first.
 *
 * @param array $viewer
 * @param array $filters status (active | paused | done | failed | cancelled | ''), channel_id
 * @return array[]
 */
function ws_scheduled_list($viewer, $filters = array())
{
    if (!ws_can_schedule($viewer)) {
        return array();
    }

    $where = array('1 = 1' . ws_scheduled_actions_only());
    $status = (string) ($filters['status'] ?? '');

    if (isset(ws_scheduled_states()[$status])) {
        $where[] = "status = '" . e($status) . "'";
    }

    if ((int) ($filters['channel_id'] ?? 0) > 0) {
        $where[] = "channel_id = '" . (int) $filters['channel_id'] . "'";
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_scheduled_actions WHERE " . implode(' AND ', $where) . "
        ORDER BY (status = 'active') DESC, IF(status = 'active' AND next_run_at > 0, next_run_at, 4294967295), id DESC LIMIT 300") as $row) {
        $out[] = ws_scheduled_present($viewer, ws_scheduled_decode($row));
    }

    return $out;
}

/**
 * The actions another one can start, for the form's list: the ones that
 * are not over.
 *
 * @param array $viewer
 * @return array[] id, name, status, trigger_only
 */
function ws_scheduled_targets($viewer)
{
    if (!ws_can_schedule($viewer)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_scheduled_actions WHERE status IN ('active', 'paused', 'failed')" . ws_scheduled_actions_only() . " ORDER BY name, id LIMIT 300") as $row) {
        $row = ws_scheduled_decode($row);
        $out[] = array('id' => (int) $row['id'], 'name' => (string) $row['name'], 'status' => (string) $row['status'], 'trigger_only' => ws_scheduled_trigger_only($row));
    }

    return $out;
}

// ─── Writing ────────────────────────────────────────────────────────────

/**
 * Creates or changes a scheduled action. A new one written in a channel
 * leaves a card there, the way a poll does; one written in a note belongs to
 * the note.
 *
 * @param array $viewer
 * @param array $data id (0 for a new one), name, rules, action, follow, channel_id, note_id
 * @return array ok, error, field, id, message_id
 */
function ws_scheduled_save($viewer, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'id' => 0, 'message_id' => 0);
    };

    if (!ws_can_schedule($viewer)) {
        return $fail(lang('Only staff can schedule actions.'));
    }

    $id = (int) ($data['id'] ?? 0);
    $existing = ($id > 0) ? ws_scheduled_action($id) : null;

    if (($id > 0) && !$existing) {
        return $fail(lang('That scheduled action could not be found.'));
    }

    if ($existing && !in_array($existing['status'], array('active', 'paused', 'failed'), true)) {
        return $fail(lang('A scheduled action that is done or cancelled cannot be changed.'));
    }

    $checked = ws_scheduled_input($viewer, $data, $existing);

    if (!$checked['ok']) {
        return $fail($checked['error'], $checked['field']);
    }

    $row = $checked['row'];
    $now = time();
    $json = function ($value) {
        return ($value === null) ? 'NULL' : "'" . e(json_encode($value, JSON_UNESCAPED_UNICODE)) . "'";
    };

    // With the follow-ups of 5.85 they are kept as they are; before them, the
    // one that is done when the action went well is the old "then".
    if (ws_scheduled_chains_ready()) {
        $follow_sql = "follow = " . $json($row['follow']) . ", then_action = NULL";
        $follow_columns = array('follow' => $json($row['follow']), 'then_action' => 'NULL');
    } else {
        $then = !empty($row['follow']) ? $row['follow'][0]['do'] : null;
        $follow_sql = "then_action = " . $json($then);
        $follow_columns = array('then_action' => $json($then));
    }

    if ($existing) {
        // A failed one that is changed is scheduled again; a paused one
        // stays paused.
        $status = ($existing['status'] === 'paused') ? 'paused' : 'active';

        db("UPDATE ws_scheduled_actions SET
                name = '" . e($row['name']) . "',
                rules = " . $json($row['rules']) . ",
                action = " . $json($row['action']) . ",
                " . $follow_sql . ",
                status = '" . $status . "',
                next_run_at = '" . (int) $row['next_run_at'] . "',
                updated_by = '" . (int) $viewer['id'] . "',
                updated_at = '" . $now . "'
            WHERE id = '" . (int) $existing['id'] . "'");

        if ((int) $existing['message_id'] > 0) {
            ws_message_touch($existing['message_id']);
        }

        return array('ok' => true, 'error' => '', 'field' => '', 'id' => (int) $existing['id'], 'message_id' => (int) $existing['message_id']);
    }

    $channel = null;
    $note_id = 0;

    if ((int) ($data['channel_id'] ?? 0) > 0) {
        $channel = ws_channel((int) $data['channel_id']);

        if (!$channel || !ws_can_post_channel($viewer, $channel)) {
            return $fail(lang('You cannot post in that channel.'));
        }
    } elseif ((int) ($data['note_id'] ?? 0) > 0) {
        $note = function_exists('ws_note_for') ? ws_note_for($viewer, (int) $data['note_id'], 'edit') : null;

        if (!$note) {
            return $fail(lang('That note could not be found.'));
        }

        $note_id = (int) $note['id'];
    }

    db("INSERT INTO ws_scheduled_actions (name, channel_id, note_id, created_by, rules, action, " . implode(', ', array_keys($follow_columns)) . ", status, next_run_at,
            updated_by, created_at, updated_at)
        VALUES ('" . e($row['name']) . "', '" . ($channel ? (int) $channel['id'] : 0) . "', '" . $note_id . "', '" . (int) $viewer['id'] . "',
            " . $json($row['rules']) . ", " . $json($row['action']) . ", " . implode(', ', $follow_columns) . ", 'active',
            '" . (int) $row['next_run_at'] . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return $fail(lang('The scheduled action could not be saved.'));
    }

    $message_id = 0;

    if ($channel) {
        $sent = ws_message_send($viewer, $channel, $row['name']);

        if ($sent['ok']) {
            $message_id = (int) $sent['message_id'];
            db("UPDATE ws_scheduled_actions SET message_id = '" . $message_id . "' WHERE id = '" . $id . "'");
        }
    }

    log_activity(lang(array('string' => 'scheduled action “{var:1}” was created in the Workspace', 'vars' => $row['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id, 'message_id' => $message_id);
}

/**
 * What a scheduled action would do, in words, while its form is filled in:
 * checked the way a save checks it, nothing written. The form shows it
 * beside the fields, with the next times it would run.
 *
 * @param array $viewer
 * @param array $data as ws_scheduled_save() takes it
 * @return array ok, error, field, when, conditions, action, follow, next
 */
function ws_scheduled_preview($viewer, $data)
{
    if (!ws_can_schedule($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can schedule actions.'), 'field' => '');
    }

    $existing = ((int) ($data['id'] ?? 0) > 0) ? ws_scheduled_action((int) $data['id']) : null;
    $checked = ws_scheduled_input($viewer, $data, $existing);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'field' => $checked['field']);
    }

    $row = $checked['row'];
    $when = '';
    $conditions = array();
    $next = array();

    foreach ($row['rules'] as $rule) {
        if (in_array($rule['type'], array('at', 'trigger', 'join', 'event'), true)) {
            $when = ws_scheduled_rule_text($viewer, $rule);
        } else {
            $conditions[] = ws_scheduled_rule_text($viewer, $rule);
        }

        // The next three times a time rule falls on.
        if ($rule['type'] === 'at') {
            $after = time();

            for ($i = 0; $i < 3; $i++) {
                $after = ws_scheduled_next_run($rule, $after);

                if ($after <= 0) {
                    break;
                }

                $next[] = ws_scheduled_moment($after);
            }
        }
    }

    $outcomes = ws_scheduled_outcomes();
    $follow = array();

    foreach ($row['follow'] as $item) {
        $follow[] = array('label' => $outcomes[$item['on']] ?? '', 'text' => ws_scheduled_action_text($viewer, $item['do']));
    }

    return array(
        'ok'         => true,
        'error'      => '',
        'field'      => '',
        'when'       => $when,
        'conditions' => $conditions,
        'action'     => ws_scheduled_action_text($viewer, $row['action']),
        'follow'     => $follow,
        'next'       => $next,
    );
}

/**
 * Pauses, resumes or cancels a scheduled action. A cancelled one takes its
 * waiting starts out of the queue with it.
 *
 * @param array  $viewer
 * @param array  $row decoded
 * @param string $to  paused | active | cancelled
 * @return array ok, error
 */
function ws_scheduled_set_status($viewer, $row, $to)
{
    if (!ws_can_schedule($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can schedule actions.'));
    }

    if (!in_array($to, array('paused', 'active', 'cancelled'), true) || in_array($row['status'], array('done', 'cancelled'), true)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $next = (int) $row['next_run_at'];

    // Resumed: from the next time it falls on from now, not a time that
    // went by while it was paused. One without a time of its own waits to be
    // started again.
    if ($to === 'active') {
        $rule = ws_scheduled_time_rule($row);
        $next = $rule ? ws_scheduled_next_run($rule, time()) : 0;

        if (($next <= 0) && !ws_scheduled_trigger_only($row)) {
            return array('ok' => false, 'error' => lang('Its time has already gone by. Change it to run again.'));
        }
    }

    db("UPDATE ws_scheduled_actions SET status = '" . e($to) . "', next_run_at = '" . (int) $next . "',
            updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    if (($to === 'cancelled') && ws_scheduled_chains_ready()) {
        db("UPDATE ws_scheduled_queue SET status = 'dropped' WHERE action_id = '" . (int) $row['id'] . "' AND status = 'waiting'");
    }

    if ((int) $row['message_id'] > 0) {
        ws_message_touch($row['message_id']);
    }

    return array('ok' => true, 'error' => '');
}

// ─── Running ────────────────────────────────────────────────────────────

/**
 * Is something due? Indexed reads, for ws_sync to tell an open screen to
 * start a run.
 *
 * @return bool
 */
function ws_scheduled_due()
{
    // Events from the site waiting to be taken (watch.php): the channels
    // that watch a record hear about them even where nothing is scheduled.
    if (function_exists('ws_events_due') && ws_events_due()) {
        return true;
    }

    // A deadline of an approval request, a read receipt to remind about
    // (approvals.php, acks.php): one indexed row each, run below.
    if ((function_exists('ws_approvals_due') && ws_approvals_due()) || (function_exists('ws_acks_due') && ws_acks_due())) {
        return true;
    }

    if (!ws_scheduled_ready()) {
        return false;
    }

    $now = time();

    if (db_value("SELECT id FROM ws_scheduled_actions
        WHERE status = 'active' AND next_run_at > 0 AND next_run_at <= '" . $now . "'
        AND running_at < '" . ($now - WS_SCHEDULED_LOCK) . "' LIMIT 1")) {
        return true;
    }

    return ws_scheduled_chains_ready()
        && (bool) db_value("SELECT id FROM ws_scheduled_queue WHERE status = 'waiting' AND due_at <= '" . $now . "' LIMIT 1");
}

/**
 * Runs the scheduled actions whose time has come, and then the ones other
 * actions started, a few at a time. The starts are read after the timed
 * runs, so a chain whose next step waits for nothing goes on in the same
 * run.
 *
 * @param int $limit
 * @return array ran, skipped, failed
 */
function ws_scheduled_run($limit = WS_SCHEDULED_BATCH)
{
    $result = array('ran' => 0, 'skipped' => 0, 'failed' => 0);

    // What happened on the site since the last run: the actions it starts
    // are queued and read below, the channels watching its records are told
    // (watch.php).
    if (function_exists('ws_events_process')) {
        ws_events_process();
    }

    // Approval requests whose deadline passed or is a day away, and read
    // receipts a day old (approvals.php, acks.php).
    if (function_exists('ws_approvals_run')) {
        ws_approvals_run();
    }

    if (function_exists('ws_acks_run')) {
        ws_acks_run();
    }

    if (!ws_scheduled_ready()) {
        return $result;
    }

    $now = time();
    $left = max(1, (int) $limit);

    foreach ((array) db_values("SELECT id FROM ws_scheduled_actions
        WHERE status = 'active' AND next_run_at > 0 AND next_run_at <= '" . $now . "'
        AND running_at < '" . ($now - WS_SCHEDULED_LOCK) . "'
        ORDER BY next_run_at LIMIT " . $left) as $id) {

        $status = ws_scheduled_execute((int) $id, false);

        if (isset($result[$status])) {
            $result[$status]++;
            $left--;
        }
    }

    if (!ws_scheduled_chains_ready()) {
        return $result;
    }

    // A start that was taken by a request that died is taken again.
    db("UPDATE ws_scheduled_queue SET status = 'waiting' WHERE status = 'taken' AND taken_at < '" . ($now - WS_SCHEDULED_LOCK) . "'");

    // Round by round: a start written by this run with no wait is read in
    // the next round, as long as the batch has room.
    for ($round = 0; ($round < WS_SCHEDULED_CHAIN_DEPTH) && ($left > 0); $round++) {
        $queue = (array) db_items("SELECT * FROM ws_scheduled_queue WHERE status = 'waiting' AND due_at <= '" . time() . "' ORDER BY due_at, id LIMIT " . max(1, $left));

        if (empty($queue)) {
            break;
        }

        foreach ($queue as $item) {
            $status = ws_scheduled_queue_take($item);

            if (isset($result[$status])) {
                $result[$status]++;
                $left--;
            }
        }
    }

    return $result;
}

/**
 * Takes one start from the queue and runs its action. A paused or finished
 * action does not run, and its history says that a chain reached it; one
 * that is running already is tried again half a minute later.
 *
 * @param array $item a ws_scheduled_queue row
 * @return string ran | skipped | failed | ''
 */
function ws_scheduled_queue_take($item)
{
    db("UPDATE ws_scheduled_queue SET status = 'taken', taken_at = '" . time() . "' WHERE id = '" . (int) $item['id'] . "' AND status = 'waiting'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return '';
    }

    $target = ws_scheduled((int) $item['action_id']);
    $context = array(
        'trigger'          => true,
        'depth'            => (int) $item['depth'],
        'source_action_id' => (int) $item['source_action_id'],
        'source_run_id'    => (int) $item['source_run_id'],
    );

    // A start a join wrote: who joined, and where.
    $carried = json_decode((string) ($item['context'] ?? ''), true);

    if (is_array($carried)) {
        $context['newcomer'] = (int) ($carried['newcomer'] ?? 0);
        $context['join_channel_id'] = (int) ($carried['channel_id'] ?? 0);

        // A start an event wrote (watch.php): what happened, to what.
        if ((string) ($carried['event'] ?? '') !== '') {
            $context['event'] = (string) $carried['event'];
            $context['event_payload'] = is_array($carried['payload'] ?? null) ? $carried['payload'] : array();
            $context['event_id'] = (int) ($carried['event_id'] ?? 0);
        }
    }

    if (!$target || ($target['status'] !== 'active')) {
        // A join that finds the action paused leaves no trace: nobody chained
        // it, and a busy channel would fill its history with skips.
        if ($target && ((int) $item['source_action_id'] > 0)) {
            ws_scheduled_record_run($target, 'skipped', array(array('step' => 'rules', 'ok' => false, 'text' => lang(array(
                'string' => '{var:1} started it, but it is not set to run ({var:2}).',
                'vars'   => array(ws_scheduled_name($item['source_action_id']), ws_scheduled_states()[$target['status']] ?? $target['status']),
            )))), $context, 0, time());
        }

        db("UPDATE ws_scheduled_queue SET status = 'dropped' WHERE id = '" . (int) $item['id'] . "'");

        return $target ? 'skipped' : '';
    }

    $status = ws_scheduled_execute((int) $target['id'], false, $context);

    if ($status === '') {
        db("UPDATE ws_scheduled_queue SET status = 'waiting', due_at = '" . (time() + 30) . "' WHERE id = '" . (int) $item['id'] . "'");

        return '';
    }

    db("UPDATE ws_scheduled_queue SET status = 'done' WHERE id = '" . (int) $item['id'] . "'");

    return $status;
}

/**
 * Keeps a run.
 *
 * @param array  $row
 * @param string $status done | skipped | failed
 * @param array  $steps
 * @param array  $context trigger, depth, source_action_id, source_run_id, by_hand
 * @param int    $message_id
 * @param int    $started
 * @return int the run's id
 */
function ws_scheduled_record_run($row, $status, $steps, $context, $message_id, $started)
{
    $chains = ws_scheduled_chains_ready();

    db("INSERT INTO ws_scheduled_runs (action_id, started_at, finished_at, status, detail, message_id, by_hand"
        . ($chains ? ', source_action_id, source_run_id, depth' : '') . ")
        VALUES ('" . (int) $row['id'] . "', '" . (int) $started . "', '" . time() . "', '" . e($status) . "',
            '" . e(json_encode($steps, JSON_UNESCAPED_UNICODE)) . "', '" . (int) $message_id . "', '" . (!empty($context['by_hand']) ? 1 : 0) . "'"
            . ($chains ? ", '" . (int) ($context['source_action_id'] ?? 0) . "', '" . (int) ($context['source_run_id'] ?? 0) . "', '" . min(255, (int) ($context['depth'] ?? 0)) . "'" : '') . ")");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Runs one scheduled action now: on its time, by hand from its card, or
 * started by another one.
 *
 * Claimed first, so two requests that meet cannot both run it. The rules
 * are checked with its creator's rights as they are now, then the action,
 * then each follow-up whose outcome matches how the action went. What
 * happened is kept as a run, said in the channel it was written in when it
 * is news (the first run, a run that went otherwise than the one before, or
 * an action that runs once), and - when it newly failed - sent to its
 * creator's inbox.
 *
 * @param int   $id
 * @param bool  $by_hand run at once, whatever its time
 * @param array $context trigger, depth, source_action_id, source_run_id (a start from a chain)
 * @return string ran | skipped | failed | '' (not run: somebody else has it)
 */
function ws_scheduled_execute($id, $by_hand = false, $context = array())
{
    $now = time();
    $triggered = !empty($context['trigger']);

    if ($by_hand) {
        $claim = " AND status IN ('active', 'paused', 'failed')";
    } elseif ($triggered) {
        $claim = " AND status = 'active'";
    } else {
        $claim = " AND status = 'active' AND next_run_at > 0 AND next_run_at <= '" . $now . "'";
    }

    db("UPDATE ws_scheduled_actions SET running_at = '" . $now . "'
        WHERE id = '" . (int) $id . "' AND running_at < '" . ($now - WS_SCHEDULED_LOCK) . "'" . $claim);

    if (mysqli_affected_rows(db::$con) !== 1) {
        return '';
    }

    $row = ws_scheduled($id);

    if (!$row) {
        return '';
    }

    // A message written in the writing box and scheduled: posted, kept in
    // the log, gone (scheduled_messages.php).
    if ((string) ($row['kind'] ?? 'action') === 'message') {
        return ws_scheduled_message_run($row, $by_hand);
    }

    $context['by_hand'] = $by_hand;
    $context['depth'] = (int) ($context['depth'] ?? 0);
    $steps = array();
    $status = 'done';
    $posted = 0;
    $viewer = ws_change_viewer_for_id($row['created_by']);

    // What placeholders and follow-ups read: this action, and the run that
    // started it.
    $fill = array('row' => $row, 'viewer' => $viewer, 'depth' => $context['depth'], 'source' => '', 'source_result' => '');

    if ($triggered && ((int) ($context['source_action_id'] ?? 0) > 0)) {
        $fill['source'] = trim(ws_scheduled_name($context['source_action_id']), '“”');
        $source_run = ((int) ($context['source_run_id'] ?? 0) > 0) ? db_item("SELECT status, detail FROM ws_scheduled_runs WHERE id = '" . (int) $context['source_run_id'] . "'") : null;
        $fill['source_result'] = is_array($source_run) ? ws_scheduled_run_summary($source_run) : '';
        $steps[] = array('step' => 'source', 'ok' => true, 'text' => lang(array('string' => 'started by {var:1}', 'vars' => ws_scheduled_name($context['source_action_id']))));
    }

    if ($triggered && ((int) ($context['newcomer'] ?? 0) > 0)) {
        $fill['newcomer'] = (int) $context['newcomer'];
        $fill['join_channel_id'] = (int) ($context['join_channel_id'] ?? 0);
        $steps[] = array('step' => 'source', 'ok' => true, 'text' => lang(array('string' => '{var:1} joined {var:2}', 'vars' => array(
            ws_person_name($fill['newcomer']), '#' . (string) db_value("SELECT name FROM ws_channels WHERE id = '" . $fill['join_channel_id'] . "'")))));
    }

    if ($triggered && ((string) ($context['event'] ?? '') !== '')) {
        $fill['event'] = (string) $context['event'];
        $fill['event_payload'] = (array) ($context['event_payload'] ?? array());
        $info = function_exists('ws_events_catalog') ? (ws_events_catalog()[$fill['event']] ?? null) : null;
        $steps[] = array('step' => 'source', 'ok' => true, 'text' => $info ? (string) $info['label'] : $fill['event']);
    }

    // The run is written first, so a start it hands on can name it.
    $run_id = ws_scheduled_record_run($row, 'done', array(), $context, 0, $now);
    db("UPDATE ws_scheduled_runs SET finished_at = 0 WHERE id = '" . $run_id . "'");
    $fill['run_id'] = $run_id;

    if (!is_array($viewer) || !ws_can_schedule($viewer)) {
        $status = 'failed';
        $steps[] = array('step' => 'rules', 'ok' => false, 'text' => lang('The one who wrote it is no longer on the staff.'));
    } else {
        $hold = ws_scheduled_rules_hold($viewer, $row['rules'], $row);
        $outcome = 'done';

        if (!$hold['ok']) {
            $status = 'skipped';
            $outcome = 'skipped';
            $steps[] = array('step' => 'rules', 'ok' => false, 'text' => $hold['reason']);
            $fill['result'] = $hold['reason'];
        } else {
            $done = ws_scheduled_do($viewer, $row, $row['action'], $fill);
            $steps[] = array('step' => 'action', 'ok' => $done['ok'], 'text' => $done['text']);
            $posted = (int) $done['message_id'];
            $outcome = $done['ok'] ? 'done' : 'failed';
            $fill['result'] = $done['text'];

            if (!$done['ok']) {
                $status = 'failed';
            }
        }

        $fill['status'] = ws_scheduled_run_label($outcome);

        // The follow-ups whose outcome matches, in their order. One that
        // fails after an action that went well makes the run a failure.
        foreach ((array) $row['follow'] as $item) {
            $on = (string) ($item['on'] ?? 'done');

            if (($on !== 'always') && ($on !== $outcome)) {
                continue;
            }

            $then = ws_scheduled_do($viewer, $row, $item['do'], $fill);
            $steps[] = array('step' => 'follow', 'ok' => $then['ok'], 'text' => $then['text']);

            if (!$then['ok'] && ($status === 'done')) {
                $status = 'failed';
            }

            if (!$posted) {
                $posted = (int) $then['message_id'];
            }
        }
    }

    $finished = time();
    $previous = db_value("SELECT status FROM ws_scheduled_runs WHERE action_id = '" . (int) $row['id'] . "' AND id < '" . $run_id . "' AND finished_at > 0 ORDER BY id DESC LIMIT 1");

    db("UPDATE ws_scheduled_runs SET finished_at = '" . $finished . "', status = '" . e($status) . "',
            detail = '" . e(json_encode($steps, JSON_UNESCAPED_UNICODE)) . "', message_id = '" . $posted . "'
        WHERE id = '" . $run_id . "'");

    // What comes next: the rule's next time from now for one that repeats.
    $rule = ws_scheduled_time_rule($row);
    $next = $rule ? ws_scheduled_next_run($rule, $finished) : 0;
    $repeats = $rule && (($rule['repeat'] ?? 'none') !== 'none');

    if ($triggered || ws_scheduled_trigger_only($row)) {
        // A start from a chain leaves the action's own time as it was; one
        // with no time of its own waits for the next start.
        $new_status = ((string) $row['status'] === 'failed') ? 'active' : (string) $row['status'];
        $next = (int) $row['next_run_at'];
    } elseif ($repeats && $by_hand && ($row['status'] === 'paused')) {
        // A single run is over once it ran, by hand too: running it early is
        // running it instead. One that repeats goes on from its next time, and
        // one paused and run by hand stays paused.
        $new_status = 'paused';
    } elseif ($repeats) {
        $new_status = ($next > 0) ? 'active' : (($status === 'failed') ? 'failed' : 'done');
    } else {
        $new_status = ($status === 'failed') ? 'failed' : 'done';
        $next = 0;
    }

    db("UPDATE ws_scheduled_actions SET status = '" . e($new_status) . "', next_run_at = '" . (int) $next . "',
            last_run_at = '" . $finished . "', run_count = run_count + 1, running_at = 0
        WHERE id = '" . (int) $row['id'] . "'");

    // News is a first run, an outcome other than the last one, or a single
    // action: a check every five minutes that keeps going well is not, and
    // nor is a greeting that went well for the next newcomer.
    $event = ws_scheduled_event_rule($row);
    $every_time = $repeats || ($event && in_array($event['type'], array('join', 'event'), true));
    $news = !$every_time || ($previous === null) || ($previous === false) || ((string) $previous !== $status) || $by_hand;

    ws_scheduled_report($row, $status, $steps, $news);

    return ($status === 'done') ? 'ran' : $status;
}

/**
 * Tells the channel an action was written in how its run went, under its
 * card, and its creator when it failed.
 *
 * @param array  $row
 * @param string $status done | skipped | failed
 * @param array  $steps
 * @param bool   $news   written in the channel and to the creator
 */
function ws_scheduled_report($row, $status, $steps, $news = true)
{
    $lines = array();

    foreach ($steps as $step) {
        $lines[] = (string) $step['text'];
    }

    $sentence = array(
        'done'    => 'Scheduled action “{var:1}” ran: {var:2}',
        'skipped' => 'Scheduled action “{var:1}” was skipped: {var:2}',
        'failed'  => 'Scheduled action “{var:1}” failed: {var:2}',
    );

    if ($news && ((int) $row['channel_id'] > 0)) {
        ws_message_system((int) $row['channel_id'], lang(array('string' => $sentence[$status] ?? $sentence['done'], 'vars' => array($row['name'], implode(' · ', $lines)))));
    }

    if ((int) $row['message_id'] > 0) {
        ws_message_touch($row['message_id']);
    }

    if ($news && ($status === 'failed')) {
        ws_notify((int) $row['created_by'], 'scheduled_failed', array(
            'channel_id' => (int) $row['channel_id'],
            'message_id' => (int) $row['message_id'],
            'actor_id'   => 0,
        ));
    }

    if ($news) {
        log_activity(lang(array('string' => $sentence[$status] ?? $sentence['done'], 'vars' => array($row['name'], implode(' · ', $lines)))), ws_person_name($row['created_by']));
    }
}

/**
 * Do the conditions hold now? The time is why the run is happening; the
 * other rules are asked here.
 *
 * @param array $viewer the creator
 * @param array $rules
 * @param array $row    the action, for counts since its last run
 * @return array ok, reason
 */
function ws_scheduled_rules_hold($viewer, $rules, $row = array())
{
    foreach ((array) $rules as $rule) {
        switch ($rule['type'] ?? '') {

            // The creator has to be able to see the records of the event
            // still: a right taken away since stops the action.
            case 'event':
                $info = function_exists('ws_events_catalog') ? (ws_events_catalog()[$rule['event'] ?? ''] ?? null) : null;

                if (($info === null) || empty($viewer[$info['right']])) {
                    return array('ok' => false, 'reason' => lang('The one who wrote it may no longer see the records of this event.'));
                }

                break;

            case 'workday':
                $off = function_exists('ws_day_off') ? ws_day_off(date('Y-m-d')) : '';

                if ($off !== '') {
                    return array('ok' => false, 'reason' => lang(array('string' => 'Not a working day ({var:1}).', 'vars' => $off)));
                }

                break;

            case 'weekdays':
                if (!in_array((int) date('N'), array_map('intval', (array) ($rule['days'] ?? array())), true)) {
                    return array('ok' => false, 'reason' => lang(array('string' => 'Not one of its days ({var:1}).', 'vars' => ws_scheduled_days_text($rule['days'] ?? array()))));
                }

                break;

            case 'hours':
                $clock = date('H:i');
                $inside = ($rule['from'] < $rule['to'])
                    ? (($clock >= $rule['from']) && ($clock < $rule['to']))
                    : (($clock >= $rule['from']) || ($clock < $rule['to']));

                if (!$inside) {
                    return array('ok' => false, 'reason' => lang(array('string' => 'Outside its hours ({var:1} – {var:2}).', 'vars' => array($rule['from'], $rule['to']))));
                }

                break;

            case 'metric':
                $count = ws_scheduled_metric_value($viewer, $rule['metric'], (int) ($rule['param'] ?? 0), (int) ($row['last_run_at'] ?? 0));

                if (!$count['ok']) {
                    return array('ok' => false, 'reason' => $count['error']);
                }

                if (!ws_scheduled_compare($count['value'], $rule['op'], (string) (int) $rule['value'], 'int')) {
                    return array('ok' => false, 'reason' => lang(array('string' => 'The condition did not hold: {var:1} (now {var:2}).', 'vars' => array(ws_scheduled_rule_text($viewer, $rule), $count['text']))));
                }

                break;

            case 'record':
                $types = ws_scheduled_record_types();
                $field = $types[$rule['record']]['fields'][$rule['field']] ?? null;
                $record = ws_scheduled_record($rule['record'], $rule['id']);

                if (($field === null) || ($record === null)) {
                    return array('ok' => false, 'reason' => lang(array('string' => 'The record the condition looks at is gone: {var:1}.', 'vars' => ws_scheduled_record_label($viewer, $rule['record'], $rule['id']))));
                }

                $current = ($rule['record'] === 'task') ? trim((string) ($record[$field[3]] ?? '')) : ws_change_current_value($field, $record);

                if (!ws_scheduled_compare($current, $rule['op'], $rule['value'], $field[0])) {
                    return array('ok' => false, 'reason' => lang(array('string' => 'The condition did not hold: {var:1}.', 'vars' => ws_scheduled_rule_text($viewer, $rule))));
                }

                break;
        }
    }

    return array('ok' => true, 'reason' => '');
}

/**
 * One comparison of a rule.
 *
 * @param mixed  $current what the record holds
 * @param string $operator
 * @param string $value    what the rule says
 * @param string $kind     the field's kind of value
 * @return bool
 */
function ws_scheduled_compare($current, $operator, $value, $kind)
{
    // A list (tracking numbers) is compared as the words it holds.
    if (is_array($current)) {
        $current = implode(', ', array_map('strval', $current));
    }

    if (is_bool($current)) {
        $current = $current ? '1' : '0';
        $value = in_array(mb_strtolower((string) $value), array('1', 'yes', 'true', 'evet', 'on'), true) ? '1' : '0';
    }

    $text = trim((string) $current);
    $empty = ($text === '') || ($text === '0000-00-00') || ($text === '0000-00-00 00:00:00') || ((($kind === 'money') || ($kind === 'int')) && ((int) $current === 0));

    switch ($operator) {
        case 'empty':
            return $empty;

        case 'not_empty':
            return !$empty;

        case 'contains':
            return ($value !== '') && (mb_stripos($text, (string) $value) !== false);

        // A date, today or within so many days from today.
        case 'within':
            if ($empty) {
                return false;
            }

            $day = substr($text, 0, 10);

            return ($day >= date('Y-m-d')) && ($day <= date('Y-m-d', strtotime('+' . max(0, (int) $value) . ' days')));

        // A date before today.
        case 'overdue':
            return !$empty && (substr($text, 0, 10) < date('Y-m-d'));

        case 'gt':
        case 'gte':
        case 'lt':
        case 'lte':
            $a = is_numeric($current) ? (float) $current : $text;
            $b = is_numeric($value) ? (float) $value : (string) $value;

            switch ($operator) {
                case 'gt':
                    return $a > $b;

                case 'gte':
                    return $a >= $b;

                case 'lt':
                    return $a < $b;
            }

            return $a <= $b;

        case 'neq':
            return mb_strtolower($text) !== mb_strtolower(trim((string) $value));

        default:
            return mb_strtolower($text) === mb_strtolower(trim((string) $value));
    }
}

// ─── Doing ──────────────────────────────────────────────────────────────

/**
 * Does one action.
 *
 * @param array $viewer  the creator
 * @param array $row     the scheduled action
 * @param array $action
 * @param array $context what placeholders read (ws_scheduled_fill()), the run and its depth
 * @return array ok, text, message_id
 */
function ws_scheduled_do($viewer, $row, $action, $context = array())
{
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'message_id' => 0);
    };

    $context['viewer'] = $viewer;
    $context['row'] = $row;

    switch ($action['type'] ?? '') {

        case 'post':
            $channel = ws_channel(((int) $action['channel_id'] === -1) ? (int) ($context['join_channel_id'] ?? 0) : (int) $action['channel_id']);

            if (!$channel) {
                return $fail(lang('The channel to write in is gone.'));
            }

            $body = mb_substr(ws_scheduled_fill((string) $action['body'], $context), 0, WS_MESSAGE_MAX);
            $sent = ws_message_send($viewer, $channel, $body, array('app_id' => 0));

            if (!$sent['ok']) {
                return $fail(lang(array('string' => 'Could not write in #{var:1}: {var:2}', 'vars' => array($channel['name'], $sent['error']))));
            }

            return array('ok' => true, 'text' => lang(array('string' => 'written in #{var:1}', 'vars' => $channel['name'])), 'message_id' => (int) $sent['message_id']);

        case 'email':
            $subject = ws_scheduled_fill((string) $action['subject'], $context);
            $format = 'plain_text';
            $body = ws_scheduled_fill((string) $action['body'], $context);

            if ((int) $action['page_id'] > 0) {
                $page = db_item("SELECT page_id, page_name, page_title FROM page WHERE page_id = '" . (int) $action['page_id'] . "'");

                if (!is_array($page)) {
                    return $fail(lang('The page to send is gone.'));
                }

                require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

                $body = (string) get_page_content((int) $page['page_id'], '', '', 'preview', true);
                $format = 'html';

                if ($subject === '') {
                    $subject = ((string) ($page['page_title'] ?? '') !== '') ? (string) $page['page_title'] : (string) $page['page_name'];
                }
            }

            $sent = ws_scheduled_mail((array) $action['to'], $subject, $body, $format);

            if ($sent === 0) {
                return $fail(lang(array('string' => 'The e-mail to {var:1} could not be sent.', 'vars' => implode(', ', (array) $action['to']))));
            }

            return array('ok' => true, 'text' => lang(array('string' => 'e-mail “{var:1}” sent to {var:2}', 'vars' => array($subject, implode(', ', (array) $action['to'])))), 'message_id' => 0);

        case 'notify':
            return ws_scheduled_notify($viewer, $row, $action, $context);

        case 'task':
            return ws_scheduled_task($viewer, $row, $action, $context);

        case 'task_digest':
            return ws_scheduled_task_digest($viewer, $row, $action, $context);

        case 'webhook':
            return ws_scheduled_webhook($viewer, $row, $action, $context);

        case 'web_check':
            return (($action['mode'] ?? '') === 'ssl') ? ws_scheduled_ssl_check($action) : ws_scheduled_web_check($action);

        case 'report':
            return ws_scheduled_summary($viewer, $row, $action, $context);

        case 'trigger':
            return ws_scheduled_start($row, $action, $context);

        case 'change':
            return ws_scheduled_change($viewer, $row, $action);

        // An approval request, with the creator's rights; somebody who left
        // the channel since is no longer asked.
        case 'approval':
            $channel = ws_channel((int) $action['channel_id']);

            if (!$channel || !function_exists('ws_approval_create')) {
                return $fail(lang('The channel to write in is gone.'));
            }

            $created = ws_approval_create($viewer, $channel, array(
                'title'     => mb_substr(ws_scheduled_fill((string) $action['title'], $context), 0, 255),
                'text'      => mb_substr(ws_scheduled_fill((string) $action['text'], $context), 0, WS_MESSAGE_MAX),
                'approvers' => (array) $action['approvers'],
                'rule'      => (string) $action['rule'],
            ), 0, false);

            if (!$created['ok']) {
                return $fail(lang(array('string' => 'Could not ask for approval in #{var:1}: {var:2}', 'vars' => array($channel['name'], $created['error']))));
            }

            return array('ok' => true, 'text' => lang(array('string' => 'asked for approval in #{var:1}', 'vars' => $channel['name'])), 'message_id' => (int) $created['message_id']);
    }

    return $fail(lang('Nothing to do.'));
}

/**
 * Sends one e-mail to each address, from the site.
 *
 * @param string[] $to
 * @param string   $subject
 * @param string   $body
 * @param string   $format plain_text | html
 * @return int how many went out
 */
function ws_scheduled_mail($to, $subject, $body, $format = 'plain_text')
{
    $sent = 0;

    foreach ((array) $to as $address) {
        $ok = email(array(
            'to'                 => $address,
            'from_name'          => defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '',
            'from_email_address' => defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '',
            'subject'            => $subject,
            'format'             => $format,
            'body'               => $body,
        ));

        if ($ok !== false) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * Lets people know in their inbox. Their bell rings and, where it is set up,
 * their phone. A notice of the same action that is still unread is brought
 * up to date rather than written twice.
 *
 * @return array ok, text, message_id
 */
function ws_scheduled_notify($viewer, $row, $action, $context)
{
    $text = mb_substr(ws_scheduled_fill((string) $action['text'], $context), 0, 1000);
    $told = array();

    foreach (ws_scheduled_people($action['user_ids'] ?? array()) as $user_id) {
        $inbox_id = ws_notify($user_id, 'scheduled_notice', array(
            'channel_id' => 0,
            'message_id' => (int) $row['message_id'],
            'actor_id'   => (int) $viewer['id'],
        ));

        if ($inbox_id > 0) {
            db("REPLACE INTO ws_scheduled_notices (inbox_id, action_id, text, created_at)
                VALUES ('" . (int) $inbox_id . "', '" . (int) $row['id'] . "', '" . e($text) . "', '" . time() . "')");
            $told[] = ws_person_name($user_id);
        }
    }

    if (empty($told)) {
        return array('ok' => false, 'text' => lang('Nobody could be told: the people are no longer in the team.'), 'message_id' => 0);
    }

    return array('ok' => true, 'text' => lang(array('string' => '{var:1} told', 'vars' => implode(', ', $told))), 'message_id' => 0);
}

/**
 * The words of a notice in the inbox.
 *
 * @param int $inbox_id
 * @return array|null text, name, channel_id, message_id
 */
function ws_scheduled_notice($inbox_id)
{
    if (!ws_scheduled_chains_ready()) {
        return null;
    }

    $row = db_item("SELECT n.text, a.name, a.channel_id, a.message_id
        FROM ws_scheduled_notices n
        LEFT JOIN ws_scheduled_actions a ON a.id = n.action_id
        WHERE n.inbox_id = '" . (int) $inbox_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * Which tasks a task e-mail lists.
 *
 * @return array key => label
 */
function ws_scheduled_digest_filters()
{
    return array(
        'open'    => lang('all open tasks'),
        'week'    => lang('due within a week, and overdue'),
        'overdue' => lang('overdue only'),
    );
}

/**
 * E-mails people their tasks: each person the open tasks they are on (in
 * one channel, or in all), or the open tasks of one channel to each person
 * who reads it. Somebody with nothing to list gets no e-mail.
 *
 * @return array ok, text, message_id
 */
function ws_scheduled_task_digest($viewer, $row, $action, $context)
{
    $mode = (($action['mode'] ?? 'mine') === 'channel') ? 'channel' : 'mine';
    $channel_id = (int) ($action['channel_id'] ?? 0);
    $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

    if (($channel_id > 0) && !$channel) {
        return array('ok' => false, 'text' => lang('The channel whose tasks are sent is gone.'), 'message_id' => 0);
    }

    $today = date('Y-m-d');
    $where = array("t.status IN ('todo', 'doing', 'waiting')");

    if ($channel_id > 0) {
        $where[] = "t.channel_id = '" . $channel_id . "'";
    }

    switch ($action['only'] ?? 'open') {
        case 'overdue':
            $where[] = "t.due_date IS NOT NULL AND t.due_date <> '0000-00-00' AND t.due_date < '" . $today . "'";
            break;

        case 'week':
            $where[] = "t.due_date IS NOT NULL AND t.due_date <> '0000-00-00' AND t.due_date <= '" . date('Y-m-d', strtotime('+7 days')) . "'";
            break;
    }

    $people = ws_scheduled_people($action['user_ids'] ?? array());

    // Nobody named: the people on the tasks, or the members of the channel.
    if (empty($people)) {
        $people = ($mode === 'channel')
            ? ws_scheduled_people(db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . $channel_id . "'"))
            : ws_scheduled_people(db_values("SELECT DISTINCT a.user_id FROM ws_task_assignees a
                INNER JOIN ws_tasks t ON t.id = a.task_id WHERE " . implode(' AND ', $where) . " LIMIT 500"));
    }

    $channel_tasks = ($mode === 'channel')
        ? (array) db_items("SELECT t.* FROM ws_tasks t WHERE " . implode(' AND ', $where) . " ORDER BY (t.due_date IS NULL), t.due_date, t.id LIMIT 200")
        : array();

    $subject = ws_scheduled_fill(((string) ($action['subject'] ?? '') !== '') ? (string) $action['subject'] : (string) $row['name'], $context);
    $intro = ws_scheduled_fill((string) ($action['intro'] ?? ''), $context);
    $sent = 0;
    $tried = 0;
    $listed = 0;

    foreach ($people as $user_id) {
        $address = (string) db_value("SELECT user_email FROM user WHERE user_id = '" . (int) $user_id . "'");
        $rights = ws_rights_for_id($user_id);

        if (($address === '') || !$rights['member'] || ($channel && !ws_can_read_channel($rights, $channel))) {
            continue;
        }

        $tasks = ($mode === 'channel') ? $channel_tasks : (array) db_items("SELECT t.* FROM ws_tasks t
            INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . (int) $user_id . "'
            WHERE " . implode(' AND ', $where) . " ORDER BY (t.due_date IS NULL), t.due_date, t.id LIMIT 200");

        if (empty($tasks)) {
            continue;
        }

        $tried++;

        if (ws_scheduled_mail(array($address), $subject, ws_scheduled_digest_html($rights, $tasks, $subject, $intro, ($mode === 'channel')), 'html') > 0) {
            $sent++;
            $listed += count($tasks);
        }
    }

    if ($tried === 0) {
        return array('ok' => true, 'text' => lang('no task e-mail went out: nobody had a task to list'), 'message_id' => 0);
    }

    if ($sent === 0) {
        return array('ok' => false, 'text' => lang('The task e-mail could not be sent.'), 'message_id' => 0);
    }

    return array('ok' => true, 'text' => lang(array('string' => 'task e-mail sent to {var:1} people ({var:2} tasks listed)', 'vars' => array($sent, $listed))), 'message_id' => 0);
}

/**
 * The body of a task e-mail: a short table, each task linked to its page.
 *
 * @param array   $reader  the person it goes to
 * @param array[] $tasks
 * @param string  $title
 * @param string  $intro
 * @param bool    $with_people list who is on each task
 * @return string HTML
 */
function ws_scheduled_digest_html($reader, $tasks, $title, $intro, $with_people)
{
    $base = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/';
    $statuses = ws_task_statuses();
    $priorities = ws_task_priorities();
    $assignees = $with_people ? ws_task_assignees_map(array_map(function ($task) { return (int) $task['id']; }, $tasks)) : array();
    $today = date('Y-m-d');
    $style = 'padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top';
    $cell = 'style="' . $style . '"';
    $rows = '';

    foreach ($tasks as $task) {
        $due = ((string) ($task['due_date'] ?? '') !== '') && ($task['due_date'] !== '0000-00-00') ? (string) $task['due_date'] : '';
        $late = ($due !== '') && ($due < $today);
        $channel = ((int) $task['channel_id'] > 0) ? ws_channel((int) $task['channel_id']) : null;
        $people = $with_people ? implode(', ', array_filter(array_map('ws_person_name', (array) ($assignees[(int) $task['id']] ?? array())))) : '';

        $rows .= '<tr>'
            . '<td ' . $cell . '><a href="' . h($base . 'workspace_tasks.php?task=' . (int) $task['id']) . '">' . h(ws_task_number($task['id'])) . '</a></td>'
            . '<td ' . $cell . '><strong>' . h((string) $task['title']) . '</strong>' . ($channel ? '<br><span style="color:#6b7280">#' . h((string) $channel['name']) . '</span>' : '') . '</td>'
            . '<td ' . $cell . '>' . h((string) ($statuses[$task['status']] ?? $task['status'])) . '</td>'
            . '<td ' . $cell . '>' . h((string) ($priorities[$task['priority']] ?? $task['priority'])) . '</td>'
            . '<td style="' . $style . ($late ? ';color:#b91c1c;font-weight:600' : '') . '">' . h(($due !== '') ? date('d.m.Y', strtotime($due . ' 12:00:00')) : '—') . '</td>'
            . ($with_people ? '<td ' . $cell . '>' . h($people) . '</td>' : '')
            . '</tr>';
    }

    $head = '<tr>'
        . '<th ' . $cell . '>#</th>'
        . '<th ' . $cell . '>' . h(lang('Task')) . '</th>'
        . '<th ' . $cell . '>' . h(lang('Status')) . '</th>'
        . '<th ' . $cell . '>' . h(lang('Priority')) . '</th>'
        . '<th ' . $cell . '>' . h(lang('Due')) . '</th>'
        . ($with_people ? '<th ' . $cell . '>' . h(lang('People')) . '</th>' : '')
        . '</tr>';

    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827">'
        . '<h2 style="font-size:18px;margin:0 0 12px">' . h($title) . '</h2>'
        . '<p style="margin:0 0 12px">' . h(lang(array('string' => 'Hello {var:1},', 'vars' => ws_person_name($reader['id'])))) . '</p>'
        . (($intro !== '') ? '<p style="margin:0 0 12px">' . nl2br(h($intro), false) . '</p>' : '')
        . '<table style="border-collapse:collapse;width:100%;max-width:760px">' . $head . $rows . '</table>'
        . '<p style="margin:16px 0 0"><a href="' . h($base . 'workspace_tasks.php') . '">' . h(lang('Open My Tasks')) . '</a></p>'
        . '<p style="margin:12px 0 0;color:#6b7280;font-size:12px">' . h(lang(array('string' => 'Sent by the Workspace of {var:1} on {var:2}.', 'vars' => array((string) HOSTNAME_SETTING, date('d.m.Y H:i'))))) . '</p>'
        . '</div>';
}

/**
 * Can a channel greet the people who join it? Not a guest's room, nor a
 * discussion: nobody joins those, people are brought in.
 *
 * @param array $channel
 * @return bool
 */
function ws_scheduled_greets($channel)
{
    return is_array($channel) && in_array((string) $channel['kind'], array('public', 'private'), true) && ((int) $channel['archived_at'] === 0);
}

/**
 * Somebody joined a channel, or was brought into it: the actions waiting for
 * that (the join rule, for this channel or for any public one) are queued,
 * one start for each newcomer, the newcomer named. The next run - the screen
 * that is open, or the job - carries them out.
 *
 * @param array $channel
 * @param int[] $user_ids
 */
function ws_scheduled_joined($channel, $user_ids)
{
    if (!ws_scheduled_join_ready() || !ws_scheduled_greets($channel) || empty($user_ids)) {
        return;
    }

    static $waiting = null;

    if ($waiting === null) {
        $waiting = array();

        foreach ((array) db_items("SELECT id, rules FROM ws_scheduled_actions WHERE status = 'active'" . ws_scheduled_actions_only() . " AND rules LIKE '%\"join\"%'") as $row) {
            foreach ((array) json_decode((string) $row['rules'], true) as $rule) {
                if (is_array($rule) && (($rule['type'] ?? '') === 'join')) {
                    $waiting[(int) $row['id']] = (int) ($rule['channel_id'] ?? 0);
                }
            }
        }
    }

    $now = time();

    foreach ($waiting as $action_id => $join_channel) {
        if (($join_channel !== (int) $channel['id']) && (($join_channel !== 0) || ((string) $channel['kind'] !== 'public'))) {
            continue;
        }

        $recent = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_queue WHERE action_id = '" . (int) $action_id . "' AND created_at > '" . ($now - 3600) . "'");

        foreach (array_values(array_unique(array_map('intval', (array) $user_ids))) as $user_id) {
            if (($user_id <= 0) || ($recent >= WS_SCHEDULED_CHAIN_HOURLY)) {
                continue;
            }

            db("INSERT INTO ws_scheduled_queue (action_id, due_at, source_action_id, source_run_id, depth, status, created_at, taken_at, context)
                VALUES ('" . (int) $action_id . "', '" . $now . "', 0, 0, 0, 'waiting', '" . $now . "', 0,
                    '" . e(json_encode(array('newcomer' => $user_id, 'channel_id' => (int) $channel['id']))) . "')");
            $recent++;
        }
    }
}

/**
 * Opens a task with the creator's rights: in a channel if one was chosen,
 * due so many days from the day it runs.
 *
 * @return array ok, text, message_id
 */
function ws_scheduled_task($viewer, $row, $action, $context)
{
    $data = array(
        'title'       => mb_substr(ws_scheduled_fill((string) $action['title'], $context), 0, 200),
        'description' => ws_scheduled_fill((string) ($action['description'] ?? ''), $context),
        'priority'    => (string) ($action['priority'] ?? 'normal'),
        'assignees'   => ws_scheduled_people($action['assignees'] ?? array()),
    );

    if ((int) ($action['channel_id'] ?? 0) > 0) {
        $data['channel_id'] = (int) $action['channel_id'];
    }

    if ((string) ($action['due_in'] ?? '') !== '') {
        $data['due_date'] = date('Y-m-d', strtotime('+' . max(0, (int) $action['due_in']) . ' days'));
    }

    $made = ws_task_create($viewer, $data);

    if (!$made['ok']) {
        return array('ok' => false, 'text' => lang(array('string' => 'The task could not be opened: {var:1}', 'vars' => $made['error'])), 'message_id' => 0);
    }

    return array('ok' => true, 'text' => lang(array('string' => 'task {var:1} “{var:2}” opened', 'vars' => array(ws_task_number($made['task_id']), $data['title']))), 'message_id' => 0);
}

/**
 * Calls a webhook: a POST with a JSON body, through the client that refuses
 * addresses inside the network and does not follow redirects
 * (includes/api/outbound/http.php).
 *
 * @return array ok, text, message_id
 */
function ws_scheduled_webhook($viewer, $row, $action, $context)
{
    if (!function_exists('api_http_request')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $text = ws_scheduled_fill((string) ($action['text'] ?? ''), $context);

    switch ($action['format'] ?? 'generic') {
        case 'text':
            $payload = array('text' => $text);
            break;

        case 'discord':
            $payload = array('content' => mb_substr($text, 0, 2000));
            break;

        default:
            $payload = array(
                'event'  => 'workspace.scheduled_action',
                'site'   => (defined('URL_SCHEME') && defined('HOSTNAME')) ? URL_SCHEME . HOSTNAME . (defined('PATH') ? PATH : '/') : '',
                'action' => array('id' => (int) $row['id'], 'name' => (string) $row['name'], 'run' => (int) $row['run_count'] + 1),
                'text'   => $text,
                'result' => (string) ($context['result'] ?? ''),
                'status' => (string) ($context['status'] ?? ''),
                'source' => (string) ($context['source'] ?? ''),
                'at'     => date('c'),
            );
    }

    $answer = api_http_request('POST', (string) $action['url'], array(
        'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'headers' => array('Content-Type: application/json; charset=utf-8'),
        'timeout' => 10,
        'agent'   => 'workspace',
    ));

    $host = ws_scheduled_host($action['url']);

    if (!$answer['ok']) {
        return array('ok' => false, 'text' => lang(array('string' => 'the webhook {var:1} did not take it: {var:2}', 'vars' => array(
            $host, ($answer['status'] > 0) ? 'HTTP ' . $answer['status'] : lang((string) $answer['error'])))), 'message_id' => 0);
    }

    return array('ok' => true, 'text' => lang(array('string' => 'webhook {var:1} called (HTTP {var:2}, {var:3} ms)', 'vars' => array($host, $answer['status'], $answer['duration_ms']))), 'message_id' => 0);
}

/**
 * Does a web address answer? Asked once, without following a redirect (a
 * redirect is an answer: it counts as one when that was allowed), optionally
 * looking for a text on the page and at how long it took.
 *
 * @param array $action url, redirect, contains, max_ms
 * @return array ok, text, message_id
 */
function ws_scheduled_web_check($action)
{
    if (!function_exists('api_http_request')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $contains = (string) ($action['contains'] ?? '');
    $answer = api_http_request('GET', (string) $action['url'], array(
        'timeout'  => 20,
        'max_body' => ($contains !== '') ? 2000000 : 1000,
        'agent'    => 'uptime-check',
    ));

    $url = (string) $action['url'];
    $status = (int) $answer['status'];
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'message_id' => 0);
    };

    if ($status === 0) {
        return $fail(lang(array('string' => '{var:1} did not answer: {var:2}', 'vars' => array($url, lang((string) $answer['error'])))));
    }

    $good = ($status >= 200) && ($status < 300);

    if (!$good && !empty($action['redirect']) && ($status >= 300) && ($status < 400)) {
        $good = true;
    }

    if (!$good) {
        return $fail(lang(array('string' => '{var:1} answered HTTP {var:2}', 'vars' => array($url, $status))));
    }

    if (($contains !== '') && ($status < 300) && (mb_stripos((string) $answer['body'], $contains) === false)) {
        return $fail(lang(array('string' => '{var:1} answered, but “{var:2}” is not on the page', 'vars' => array($url, $contains))));
    }

    if (((int) ($action['max_ms'] ?? 0) > 0) && ($answer['duration_ms'] > (int) $action['max_ms'])) {
        return $fail(lang(array('string' => '{var:1} answered in {var:2} ms, slower than {var:3} ms', 'vars' => array($url, $answer['duration_ms'], (int) $action['max_ms']))));
    }

    return array('ok' => true, 'text' => lang(array('string' => '{var:1} answered HTTP {var:2} in {var:3} ms', 'vars' => array($url, $status, $answer['duration_ms']))), 'message_id' => 0);
}

/**
 * How many days the certificate of a web address has left. The host is
 * checked as every outgoing call is, and the connection goes to the address
 * that was checked; the certificate is read rather than trusted, so one that
 * ran out is reported as such instead of as a failed connection.
 *
 * @param array $action url, days
 * @return array ok, text, message_id
 */
function ws_scheduled_ssl_check($action)
{
    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $url = (string) $action['url'];
    $host = ws_scheduled_host($url);
    $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
    $check = api_http_check_url($url);
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'message_id' => 0);
    };

    if (!$check['ok']) {
        return $fail(lang(array('string' => 'The certificate of {var:1} could not be read: {var:2}', 'vars' => array($host, lang($check['error'])))));
    }

    $stream = stream_context_create(array('ssl' => array(
        'capture_peer_cert' => true,
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    )));

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client('ssl://' . (filter_var($check['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $check['ip'] . ']' : $check['ip']) . ':' . $port,
        $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $stream);

    if (!$socket) {
        return $fail(lang(array('string' => 'The certificate of {var:1} could not be read: {var:2}', 'vars' => array($host, ($errstr !== '') ? $errstr : (string) $errno))));
    }

    $params = stream_context_get_params($socket);
    fclose($socket);

    $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
    $parsed = $certificate ? openssl_x509_parse($certificate) : false;

    if (!is_array($parsed) || empty($parsed['validTo_time_t'])) {
        return $fail(lang(array('string' => 'The certificate of {var:1} could not be read: {var:2}', 'vars' => array($host, lang('no certificate')))));
    }

    $ends = (int) $parsed['validTo_time_t'];
    $left = (int) floor(($ends - time()) / 86400);

    if ($ends <= time()) {
        return $fail(lang(array('string' => 'The certificate of {var:1} ran out on {var:2}', 'vars' => array($host, date('d.m.Y', $ends)))));
    }

    if ($left < (int) $action['days']) {
        return $fail(lang(array('string' => 'The certificate of {var:1} ends in {var:2} days ({var:3})', 'vars' => array($host, $left, date('d.m.Y', $ends)))));
    }

    return array('ok' => true, 'text' => lang(array('string' => 'the certificate of {var:1} has {var:2} days left ({var:3})', 'vars' => array($host, $left, date('d.m.Y', $ends)))), 'message_id' => 0);
}

/**
 * A summary of the counts: written in a channel, e-mailed, or both.
 *
 * @return array ok, text, message_id
 */
function ws_scheduled_summary($viewer, $row, $action, $context)
{
    $title = ws_scheduled_fill(((string) ($action['title'] ?? '') !== '') ? (string) $action['title'] : (string) $row['name'], $context);
    $lines = array();
    $plain = array();

    foreach ((array) $action['metrics'] as $item) {
        $count = ws_scheduled_metric_value($viewer, $item['key'], (int) ($item['param'] ?? 0), (int) ($row['last_run_at'] ?? 0));
        $label = ws_scheduled_metric_label($item['key'], (int) ($item['param'] ?? 0));
        $value = $count['ok'] ? $count['text'] : '—';

        $lines[] = '• ' . $label . ': **' . $value . '**';
        $plain[] = '- ' . $label . ': ' . $value;
    }

    $heading = '**' . $title . '** · ' . date('d.m.Y H:i');
    $where = array();
    $message_id = 0;
    $channel = ((int) ($action['channel_id'] ?? 0) > 0) ? ws_channel((int) $action['channel_id']) : null;

    if ((int) ($action['channel_id'] ?? 0) > 0) {
        if (!$channel) {
            return array('ok' => false, 'text' => lang('The channel to write in is gone.'), 'message_id' => 0);
        }

        $sent = ws_message_send($viewer, $channel, mb_substr($heading . "\n" . implode("\n", $lines), 0, WS_MESSAGE_MAX), array('app_id' => 0));

        if (!$sent['ok']) {
            return array('ok' => false, 'text' => lang(array('string' => 'Could not write in #{var:1}: {var:2}', 'vars' => array($channel['name'], $sent['error']))), 'message_id' => 0);
        }

        $message_id = (int) $sent['message_id'];
        $where[] = '#' . $channel['name'];
    }

    if (!empty($action['to'])) {
        if (ws_scheduled_mail((array) $action['to'], $title . ' · ' . date('d.m.Y'), $title . "\n" . date('d.m.Y H:i') . "\n\n" . implode("\n", $plain)) === 0) {
            return array('ok' => false, 'text' => lang(array('string' => 'The e-mail to {var:1} could not be sent.', 'vars' => implode(', ', (array) $action['to']))), 'message_id' => $message_id);
        }

        $where[] = implode(', ', (array) $action['to']);
    }

    return array('ok' => true, 'text' => lang(array('string' => 'summary sent to {var:1}', 'vars' => implode(' · ', $where))), 'message_id' => $message_id);
}

/**
 * Starts another scheduled action: it goes in the queue, due now or so many
 * minutes later, one step deeper in the chain than the run that started it.
 *
 * @param array $row     the action that starts it
 * @param array $action  target_id, delay
 * @param array $context depth, run_id
 * @return array ok, text, message_id
 */
function ws_scheduled_start($row, $action, $context)
{
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'message_id' => 0);
    };

    if (!ws_scheduled_chains_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $target = ws_scheduled_action((int) $action['target_id']);

    if (!$target) {
        return $fail(lang('The scheduled action to start is gone.'));
    }

    if ($target['status'] !== 'active') {
        return $fail(lang(array('string' => '{var:1} is not set to run ({var:2}).', 'vars' => array('“' . $target['name'] . '”', ws_scheduled_states()[$target['status']] ?? $target['status']))));
    }

    $depth = (int) ($context['depth'] ?? 0) + 1;

    if ($depth >= WS_SCHEDULED_CHAIN_DEPTH) {
        return $fail(lang(array('string' => 'The chain stops here: at most {var:1} actions follow one another.', 'vars' => WS_SCHEDULED_CHAIN_DEPTH)));
    }

    $recent = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_queue WHERE action_id = '" . (int) $target['id'] . "' AND created_at > '" . (time() - 3600) . "'");

    if ($recent >= WS_SCHEDULED_CHAIN_HOURLY) {
        return $fail(lang(array('string' => '{var:1} was started {var:2} times in the last hour; it is not started again for now.', 'vars' => array('“' . $target['name'] . '”', $recent))));
    }

    $delay = max(0, (int) ($action['delay'] ?? 0));
    $due = time() + $delay * 60;

    db("INSERT INTO ws_scheduled_queue (action_id, due_at, source_action_id, source_run_id, depth, status, created_at, taken_at)
        VALUES ('" . (int) $target['id'] . "', '" . $due . "', '" . (int) $row['id'] . "', '" . (int) ($context['run_id'] ?? 0) . "',
            '" . $depth . "', 'waiting', '" . time() . "', 0)");

    return array('ok' => true, 'text' => ($delay > 0)
        ? lang(array('string' => '{var:1} starts at {var:2}', 'vars' => array('“' . $target['name'] . '”', ws_scheduled_moment($due))))
        : lang(array('string' => '{var:1} started', 'vars' => '“' . $target['name'] . '”')), 'message_id' => 0);
}

/**
 * A change to a record, through the same checks and writers as a change
 * Claude proposes: checked again as the record is now, written with the
 * creator's rights, and - when the action was written in a channel - kept
 * there as a locked decision, like every other change to a record the
 * workspace makes.
 *
 * @param array $viewer
 * @param array $row
 * @param array $action record, action, id, fields
 * @return array ok, text, message_id
 */
function ws_scheduled_change($viewer, $row, $action)
{
    $fail = function ($text) {
        return array('ok' => false, 'text' => $text, 'message_id' => 0);
    };

    $types = ws_change_types();
    $type = (string) $action['record'];

    if (!isset($types[$type])) {
        return $fail(lang('Invalid request.'));
    }

    $input = ws_changes_input(array(array(
        'type'   => $type,
        'action' => (string) $action['action'],
        'id'     => (int) $action['id'],
        'fields' => (array) $action['fields'],
    )), (int) $viewer['id'], (int) $row['channel_id']);

    if (!$input['ok'] || empty($input['rows'])) {
        return $fail(preg_replace('/^changes\[0\]: /', '', (string) $input['error']));
    }

    $change = $input['rows'][0];
    $record = ws_change_record($type, (int) $action['id']);

    if ($record === null) {
        return $fail(lang('The record is gone.'));
    }

    $values = array();

    foreach ($change['fields'] as $item) {
        $values[$item['name']] = $item['to'];
    }

    if ($change['action'] === 'delete') {
        $values = array();
    }

    // The writers log in words about Claude; this run logs its own line.
    $GLOBALS['ws_change_log_quiet'] = true;
    $done = call_user_func('ws_change_write_' . $type, $viewer, $change['action'], $record, $values);
    $GLOBALS['ws_change_log_quiet'] = false;

    if (!$done['ok']) {
        return $fail($done['error']);
    }

    $name = ws_change_record_name($type, $record);
    $lines = array();

    foreach ($change['fields'] as $item) {
        $field = ws_change_field($types[$type], (string) $item['name']);

        if ($field !== null) {
            $lines[] = $field[2] . ': “' . ws_change_show($type, $item['name'], $item['from'], 120) . '” → “' . ws_change_show($type, $item['name'], $item['to'], 120) . '”';
        }
    }

    $tag = '<#' . $types[$type]['tag'] . ':' . (int) $action['id'] . '>';
    $text = ($change['action'] === 'delete')
        ? lang(array('string' => '{var:1} deleted', 'vars' => '“' . $name . '”'))
        : lang(array('string' => '{var:1} changed ({var:2})', 'vars' => array('“' . $name . '”', implode(', ', $lines))));
    $decision_id = 0;
    $channel = ((int) $row['channel_id'] > 0) ? ws_channel($row['channel_id']) : null;

    if ($channel && ws_can_post_channel($viewer, $channel)) {
        $body = ($change['action'] === 'delete')
            ? lang(array('string' => '{var:1} was deleted by the scheduled action “{var:2}”.', 'vars' => array('“' . $name . '” (' . $types[$type]['label'] . ' #' . (int) $action['id'] . ')', $row['name'])))
            : lang(array('string' => '{var:1} was changed by the scheduled action “{var:2}”. {var:3}', 'vars' => array($tag, $row['name'], implode(' · ', $lines))));
        $sent = ws_message_send($viewer, $channel, mb_substr($body, 0, WS_MESSAGE_MAX), array('kind' => 'decision', 'parent_id' => (int) $row['message_id']));

        if ($sent['ok']) {
            $decision_id = (int) $sent['message_id'];
            db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $decision_id . "'");
        }
    }

    log_activity(lang(array('string' => 'scheduled action “{var:1}”: {var:2}', 'vars' => array($row['name'], $text))), ws_person_name($viewer['id']));

    return array('ok' => true, 'text' => $text, 'message_id' => $decision_id);
}

/**
 * The texts of the scheduled actions on the screens.
 *
 * @return array key => text
 */
function ws_scheduled_js_strings()
{
    return array(
        'sa_title'          => lang('Scheduled actions'),
        'sa_new'            => lang('Scheduled action'),
        'sa_new_long'       => lang('New scheduled action'),
        'sa_edit'           => lang('Change the scheduled action'),
        'sa_name'           => lang('Name'),
        'sa_name_help'      => lang('What it is for, in a few words. It is the title of its card.'),
        'sa_rules'          => lang('Rules'),
        'sa_rules_help'     => lang('When it runs, and what must hold then. Every rule has to hold; if one does not, the run is skipped.'),
        'sa_when'           => lang('When'),
        'sa_when_time'      => lang('At a time'),
        'sa_when_trigger'   => lang('When another action starts it'),
        'sa_when_trigger_help' => lang('It has no time of its own: a follow-up of another scheduled action starts it, or you do with “Run now”.'),
        'sa_date'           => lang('Date'),
        'sa_time'           => lang('Time'),
        'sa_repeat'         => lang('Repeat'),
        'sa_every'          => lang('Every'),
        'sa_every_minutes'  => lang('minutes'),
        'sa_every_hours'    => lang('hours'),
        'sa_every_days'     => lang('days'),
        'sa_every_weeks'    => lang('weeks'),
        'sa_every_months'   => lang('months'),
        'sa_every_years'    => lang('years'),
        'sa_on_days'        => lang('On these days'),
        'sa_until'          => lang('Last day'),
        'sa_until_help'     => lang('Optional: it stops repeating after this day.'),
        'sa_add_rule'       => lang('Add a condition'),
        'sa_rule_record'    => lang('A record has a value'),
        'sa_rule_metric'    => lang('A count'),
        'sa_rule_workday'   => lang('Only on a working day'),
        'sa_rule_weekdays'  => lang('Only on some days of the week'),
        'sa_rule_hours'     => lang('Only between some hours'),
        'sa_from'           => lang('From'),
        'sa_to_time'        => lang('To'),
        'sa_metric'         => lang('Count'),
        'sa_metric_channel' => lang('Tasks of'),
        'sa_metric_all'     => lang('All channels'),
        'sa_metric_threshold' => lang('At most'),
        'sa_record_kind'    => lang('Kind of record'),
        'sa_record'         => lang('Record'),
        'sa_record_search'  => lang('Search by name or number'),
        'sa_field'          => lang('Field'),
        'sa_operator'       => lang('Comparison'),
        'sa_value'          => lang('Value'),
        'sa_days_value'     => lang('Days'),
        'sa_action'         => lang('Action'),
        'sa_action_help'    => lang('One thing it does when the rules hold.'),
        'sa_follow'         => lang('Afterwards'),
        'sa_follow_help'    => lang('What is done next, by how the action went: tell somebody when a check failed, start another scheduled action when it went well. They run in their order.'),
        'sa_add_follow'     => lang('Add a follow-up'),
        'sa_then'           => lang('Then'),
        'sa_then_help'      => lang('Done only when the action went well.'),
        'sa_do_nothing'     => lang('Nothing more'),
        'sa_do_post'        => lang('Write in a channel'),
        'sa_do_email'       => lang('Send an e-mail'),
        'sa_do_notify'      => lang('Let people know'),
        'sa_do_task'        => lang('Open a task'),
        'sa_do_change'      => lang('Change a record'),
        'sa_do_webhook'     => lang('Call a webhook'),
        'sa_do_web_check'   => lang('Check a web address'),
        'sa_do_report'      => lang('Post a summary'),
        'sa_do_trigger'     => lang('Start another scheduled action'),
        'sa_group_team'     => lang('The team'),
        'sa_group_records'  => lang('Records'),
        'sa_group_web'      => lang('Web and other systems'),
        'sa_group_chain'    => lang('Chain'),
        'sa_channel'        => lang('Channel'),
        'sa_no_channel'     => lang('No channel'),
        'sa_message'        => lang('Message'),
        'sa_to'             => lang('To'),
        'sa_to_help'        => lang('One or more addresses, separated by commas.'),
        'sa_subject'        => lang('Subject'),
        'sa_email_text'     => lang('Text'),
        'sa_email_page'     => lang('A page'),
        'sa_page'           => lang('Page'),
        'sa_page_help'      => lang('Sent as the e-mail campaigns send a page. Without a subject, the page title is the subject.'),
        'sa_people'         => lang('People'),
        'sa_notice'         => lang('What they are told'),
        'sa_task_title'     => lang('Title of the task'),
        'sa_task_text'      => lang('Description'),
        'sa_task_people'    => lang('Given to'),
        'sa_task_priority'  => lang('Priority'),
        'sa_task_due'       => lang('Due in days'),
        'sa_task_due_help'  => lang('Counted from the day it runs; leave it empty for no due date.'),
        'sa_task_channel'   => lang('In the channel'),
        'sa_url'            => lang('Address'),
        'sa_webhook_help'   => lang('An https address: an incoming webhook of Slack, Microsoft Teams, Google Chat or Discord, or a flow of n8n, Zapier or Make. The address is kept with the action; only staff see it.'),
        'sa_format'         => lang('Shape of the body'),
        'sa_webhook_text'   => lang('Message'),
        'sa_check_mode'     => lang('What is checked'),
        'sa_check_status'   => lang('That it answers'),
        'sa_check_ssl'      => lang('How long its certificate has left'),
        'sa_check_redirect' => lang('A redirect counts as an answer'),
        'sa_check_contains' => lang('Text that must be on the page'),
        'sa_check_max_ms'   => lang('Slowest answer (ms)'),
        'sa_check_max_help' => lang('Optional: slower than this is a failure.'),
        'sa_check_days'     => lang('Days it must have left at least'),
        'sa_check_help'     => lang('A failed check makes the run a failure: add a follow-up “If it failed” to be told. The channel hears about a repeating check only when its answer changes.'),
        'sa_report_title'   => lang('Title of the summary'),
        'sa_report_metrics' => lang('Counts'),
        'sa_report_to'      => lang('Also e-mail it to'),
        'sa_target'         => lang('Scheduled action to start'),
        'sa_target_none'    => lang('No other scheduled action yet. Write the one to start first; “When another action starts it” gives it no time of its own.'),
        'sa_delay'          => lang('Wait (minutes)'),
        'sa_delay_help'     => lang('0 starts it at once.'),
        'sa_chain_help'     => ws_js_template('At most {var:1} actions follow one another in a chain.', 1),
        'sa_placeholders'   => lang('Words filled in when it runs'),
        'sa_change_verb'    => lang('What to do'),
        'sa_verb_update'    => lang('Change fields'),
        'sa_verb_delete'    => lang('Delete'),
        'sa_add_field'      => lang('Add a field'),
        'sa_save'           => lang('Schedule it'),
        'sa_saved'          => lang('The action is scheduled.'),
        'sa_next'           => lang('Next'),
        'sa_last'           => lang('Last run'),
        'sa_runs'           => lang('Runs'),
        'sa_pause'          => lang('Pause'),
        'sa_resume'         => lang('Resume'),
        'sa_cancel'         => lang('Cancel it'),
        'sa_cancel_confirm' => lang('Cancel this scheduled action? It will not run again.'),
        'sa_run_now'        => lang('Run now'),
        'sa_run_now_confirm' => lang('Run this scheduled action now? Its rules are checked first, as on its time.'),
        'sa_history'        => lang('History'),
        'sa_no_history'     => lang('It has not run yet.'),
        'sa_empty'          => lang('No scheduled actions yet. Write one from the + menu of a channel\'s writing box, or from a note.'),
        'sa_staff_only'     => lang('Only staff see what a scheduled action does.'),
        'sa_in_channel'     => lang('Written in'),
        'sa_in_note'        => lang('Written in the note'),
        'sa_started_by'     => lang('Started by'),
        'sa_starts'         => lang('Starts'),
        'sa_all'            => lang('All'),
        'sa_state_active'   => lang('Set to run'),
        'sa_state_paused'   => lang('Paused'),
        'sa_state_done'     => lang('Done'),
        'sa_state_failed'   => lang('Failed'),
        'sa_state_cancelled' => lang('Cancelled'),
        'sa_by_hand'        => lang('by hand'),
        'sa_by'             => lang('Written by'),
        'sa_help'           => lang('A scheduled action runs by itself when its time comes: a channel that is open asks for it within seconds, and the site\'s general job runs it otherwise. For it to run on the minute when nobody has the workspace open, the general job has to be scheduled every minute.'),
        'sa_money_help'     => lang('An amount, like 1250.50'),
        'sa_yes'            => lang('Yes'),
        'sa_no'             => lang('No'),
        'sa_templates'      => lang('Start from a ready one'),
        'sa_template_none'  => lang('An empty form'),
        'sa_tpl_digest'     => lang('Summary every working morning'),
        'sa_tpl_digest_title' => lang('Good morning: where we stand'),
        'sa_tpl_uptime'     => lang('Is the site up? Every 15 minutes'),
        'sa_tpl_uptime_down' => lang('The site does not answer: {{result}}'),
        'sa_tpl_ssl'        => lang('Certificate check every Monday'),
        'sa_tpl_ssl_warn'   => lang('The site\'s certificate needs renewing: {{result}}'),
        'sa_tpl_overdue'    => lang('Overdue tasks every working day'),
        'sa_tpl_overdue_text' => lang('{{count:tasks_overdue}} tasks have gone past their due date. The list is under My Tasks.'),
        'sa_tpl_forms'      => lang('New form submissions every hour'),
        'sa_tpl_forms_text' => lang('{{count:forms_new}} new form submissions in the last hour.'),
        'sa_tpl_stock'      => lang('Stock warning every morning'),
        'sa_tpl_stock_text' => lang('{{count:stock_low:5}} products have 5 or fewer left in stock.'),
        'sa_tpl_orders'     => lang('New orders to a chat service'),
        'sa_tpl_orders_text' => lang('{{count:orders_new}} new orders since the last look. Sales today: {{count:sales_today}}.'),
        'sa_tpl_invoices'   => lang('Overdue invoices every Monday'),
        'sa_tpl_invoices_text' => lang('{{count:invoices_overdue}} sales invoices are overdue, {{count:invoices_overdue_amount}} in all.'),
        'sa_tpl_monthly'    => lang('Monthly report task'),
        'sa_tpl_monthly_task' => lang('Monthly report for {{date}}'),
        'sa_tpl_chain'      => lang('A step of a chain'),
        'sa_tpl_chain_text' => lang('{{source}} finished: {{source_result}}'),
        'sa_tpl_welcome'    => lang('Welcome message'),
        'sa_tpl_welcome_help' => lang('Greets the people who join a channel and asks them to say a few words about themselves.'),
        'sa_tpl_welcome_text' => lang('Welcome to {{channel}}, {{newcomer}}! 👋 Could you tell us a little about yourself: who you are and what you work on?'),
        'sa_tpl_task_mail'  => lang('Tasks by e-mail every Monday'),
        'sa_tpl_task_mail_help' => lang('Every Monday morning each person gets the open tasks they are on, by e-mail.'),
        'sa_tpl_task_mail_subject' => lang('Your open tasks this week'),
        'sa_template_none_help' => lang('Build it yourself, step by step.'),
        'sa_step_when'      => lang('When'),
        'sa_step_when_help' => lang('What starts it: a time, another scheduled action, or somebody joining a channel.'),
        'sa_step_if'        => lang('Only if'),
        'sa_step_do'        => lang('Do'),
        'sa_when_join'      => lang('When somebody joins'),
        'sa_join_channel'   => lang('Channel'),
        'sa_join_any'       => lang('Any public channel'),
        'sa_join_help'      => lang('It runs once for each person who joins or is added; {{newcomer}} in a text tags them.'),
        'sa_joined_channel' => lang('The channel they joined'),
        'sa_do_task_digest' => lang('E-mail people their tasks'),
        'sa_digest_mine'    => lang('Each person their own tasks'),
        'sa_digest_channel' => lang('The tasks of a channel'),
        'sa_digest_all_channels' => lang('All channels'),
        'sa_digest_which'   => lang('Which tasks'),
        'sa_digest_people_mine' => lang('Left empty, everybody who is on an open task gets their list.'),
        'sa_digest_people_channel' => lang('Left empty, every member of the channel gets the list.'),
        'sa_digest_subject_placeholder' => lang('The name of the scheduled action'),
        'sa_digest_intro'   => lang('A few words above the list'),
        'sa_digest_intro_help' => lang('Optional. Nobody gets an e-mail with nothing in it.'),
        'sa_next_runs'      => lang('Next runs'),
        'sa_duplicate'      => lang('Duplicate'),
        'sa_copy_of'        => ws_js_template('Copy of {var:1}', 1),
    );
}
