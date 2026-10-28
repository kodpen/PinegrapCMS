<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flow templates: ready-made flows the canvas offers in its
 * gallery, a starting point a person places on the canvas and finishes.
 * They are the scheduled actions' ready templates (saTemplates() in
 * assets/js/workspace.js) drawn as boxes: the "when" a start box, each
 * condition a condition box, the step and its follow-ups step boxes on the
 * arrows of their outcomes. Their titles and texts are the same sentences,
 * so a language that has them has these.
 *
 * A template is:
 *   key       the scheduled template's key
 *   category  ws_flow_template_categories()
 *   title     the scheduled template's name
 *   help      one line on what it does
 *   icon      the gallery's sign for it
 *   start     the kind of its start box
 *   graph     a draft graph (ws_flow_graph_normalize())
 *   boxes     how many boxes it has
 *   threads   how many values its boxes read from earlier boxes
 *             ({{node.<id>.<key>}})
 *   settings  what the person is asked before it runs, each a type
 *             (ws_flow_template_setting_types()) and the box settings it
 *             fills, [node id, key]
 *   motion    when each box, arrow and thread appears as the template is
 *             placed (ws_flow_template_motion())
 *
 * Beside them, templates a scheduled action has no form for: tasks that
 * wait for each other, a wait between boxes, approvals and their outcomes.
 *
 * Templates whose counts or events the person may not read, or whose texts
 * a flow cannot fill yet (a count "since the last run"), are not offered.
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
 * The groups the gallery shows templates in.
 *
 * @return array key => label
 */
function ws_flow_template_categories()
{
    return array(
        'team'  => lang('Team'),
        'site'  => lang('Site'),
        'sales' => lang('Sales'),
    );
}

/**
 * The kinds of value a template asks for: a channel id, people (a list of
 * ids), a time of day (HH:MM), a web address, e-mail addresses.
 *
 * @return string[]
 */
function ws_flow_template_setting_types()
{
    return array('channel', 'person', 'time', 'url', 'address');
}

/**
 * The flow templates offered to a person. Pure: what it knows of the person
 * and the site comes in $fill.
 *
 * @param array $fill me (the person's id), channel (the channel open, 0 for
 *                    none), site (the site's address), email (the person's),
 *                    today (YYYY-MM-DD), metrics (keys of the counts the
 *                    person may read), recap_ai (Pinegrap AI may sum up what
 *                    was missed), join (a flow may start when somebody
 *                    joins a channel), events (keys of the events the
 *                    person may wait for)
 * @return array[] see the file's docblock
 */
function ws_flow_templates($fill)
{
    $me = (int) ($fill['me'] ?? 0);
    $channel = (int) ($fill['channel'] ?? 0);
    $site = (string) ($fill['site'] ?? '');
    $email = (string) ($fill['email'] ?? '');
    $metrics = (array) ($fill['metrics'] ?? array());
    $has = function ($key) use ($metrics) {
        return in_array($key, $metrics, true);
    };

    // The scheduler reads a start date as a day of the site's calendar, so
    // the days are counted on the date alone, in UTC: in PHP's default
    // timezone "next monday" is a local midnight, which east of UTC is
    // still the Sunday before in UTC.
    $utc = new DateTimeZone('UTC');
    $today = (string) ($fill['today'] ?? '');
    $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) === 1) ? date_create_immutable($today . ' 12:00:00', $utc) : false;

    if (!$day) {
        $day = date_create_immutable(date('Y-m-d') . ' 12:00:00', $utc);
    }

    $tomorrow = $day->modify('+1 day')->format('Y-m-d');
    $monday = $day->modify('next monday')->format('Y-m-d');
    $first_of_month = $day->modify('first day of next month')->format('Y-m-d');
    $today = $day->format('Y-m-d');

    // A box's sentence: a follow-up's {{result}} is what the box it follows
    // said, which a flow reads from that box.
    $result_of = function ($text, $node) {
        return str_replace('{{result}}', '{{node.' . $node . '.text}}', $text);
    };

    // Where a text goes: the channel open, else the person.
    $tell = function ($text) use ($channel, $me) {
        return $channel
            ? array('kind' => 'post', 'config' => array('channel_id' => $channel, 'body' => $text), 'setting' => array('channel', 'channel_id'))
            : array('kind' => 'notify', 'config' => array('user_ids' => array($me), 'text' => $text), 'setting' => array('person', 'user_ids'));
    };

    $start = function ($date, $time, $repeat, $every = 1, $days = array()) {
        return array('kind' => 'schedule', 'config' => array('date' => $date, 'time' => $time, 'repeat' => $repeat, 'every' => $every, 'days' => $days, 'until' => ''));
    };

    $count = function ($metric, $param = 0) {
        return array('kind' => 'condition', 'config' => array('rule' => array('type' => 'metric', 'metric' => $metric, 'param' => $param, 'op' => 'gt', 'value' => '0')));
    };

    $out = array();

    // Every Monday, each person the open tasks they are on.
    $out[] = array('key' => 'task_mail', 'category' => 'team', 'icon' => 'bi-envelope-check',
        'title' => lang('Tasks by e-mail every Monday'),
        'help' => lang('Every Monday morning each person gets the open tasks they are on, by e-mail.'),
        'boxes' => array(
            'start' => $start($monday, '09:00', 'weekly', 1, array(1)) + array('setting' => array('time', 'time')),
            'mail'  => array('kind' => 'task_digest', 'config' => array('mode' => $channel ? 'channel' : 'mine', 'channel_id' => $channel, 'user_ids' => array(),
                'only' => 'open', 'subject' => lang('Your open tasks this week'), 'intro' => '')),
        ),
        'edges' => array(array('start', 'mail', 'next')));

    // Every working morning, what the person missed and their tasks due
    // this week.
    $out[] = array('key' => 'morning', 'category' => 'team', 'icon' => 'bi-sunrise',
        'title' => lang('Morning summary by e-mail'),
        'help' => lang('Every working morning: what you missed in the channels and your tasks due this week.'),
        'boxes' => array(
            'start' => $start($tomorrow, '08:30', 'workdays') + array('setting' => array('time', 'time')),
            'mail'  => array('kind' => 'task_digest', 'config' => array('mode' => 'mine', 'channel_id' => 0, 'user_ids' => array($me), 'only' => 'week',
                'subject' => lang('Good morning: what you missed'), 'intro' => '', 'missed' => true, 'ai' => !empty($fill['recap_ai'])),
                'setting' => array('person', 'user_ids')),
        ),
        'edges' => array(array('start', 'mail', 'next')));

    $digest = array();

    foreach (array('tasks_open', 'tasks_overdue', 'tasks_due_today', 'orders_today', 'sales_today', 'forms_new', 'comments_pending', 'visitors_yesterday') as $key) {
        if ($has($key)) {
            $digest[] = array('key' => $key, 'param' => 0);
        }
    }

    if (!empty($digest)) {
        $out[] = array('key' => 'digest', 'category' => 'site', 'icon' => 'bi-clipboard-data',
            'title' => lang('Summary every working morning'),
            'help' => lang('Every working morning the site\'s counts in one summary: tasks, orders, sales, forms, visitors.'),
            'boxes' => array(
                'start'  => $start($tomorrow, '09:00', 'workdays') + array('setting' => array('time', 'time')),
                'report' => array('kind' => 'report', 'config' => array('title' => lang('Good morning: where we stand'), 'metrics' => $digest,
                    'channel_id' => $channel, 'to' => $channel ? '' : $email),
                    'setting' => $channel ? array('channel', 'channel_id') : array('address', 'to')),
            ),
            'edges' => array(array('start', 'report', 'next')));
    }

    // The site checked every 15 minutes; the person told, and e-mailed when
    // there is an address, when it does not answer.
    $down = lang('The site does not answer: {{result}}');
    $uptime = array(
        'start' => $start($today, '00:00', 'minutes', 15),
        'check' => array('kind' => 'web_check', 'config' => array('url' => ($site !== '') ? $site : 'https://', 'mode' => 'status', 'redirect' => true,
            'contains' => '', 'max_ms' => 5000), 'setting' => array('url', 'url')),
        'tell'  => array('kind' => 'notify', 'config' => array('user_ids' => array($me), 'text' => $result_of($down, 'check')), 'setting' => array('person', 'user_ids')),
    );
    $uptime_edges = array(array('check', 'tell', 'failed'));

    if ($email !== '') {
        $uptime['mail'] = array('kind' => 'email', 'config' => array('to' => $email, 'subject' => $result_of($down, 'check'),
            'body' => '{{node.check.text}}' . "\n" . '{{date}} {{time}}'), 'setting' => array('address', 'to'));
        $uptime_edges[] = array('check', 'mail', 'failed');
    }

    $out[] = array('key' => 'uptime', 'category' => 'site', 'icon' => 'bi-activity',
        'title' => lang('Is the site up? Every 15 minutes'),
        'help' => lang('Opens the site every 15 minutes and tells you when it does not answer.'),
        'boxes' => $uptime,
        'edges' => array_merge(array(array('start', 'check', 'next')), $uptime_edges));

    // The certificate looked at every Monday, three weeks before it runs out.
    $out[] = array('key' => 'ssl', 'category' => 'site', 'icon' => 'bi-shield-lock',
        'title' => lang('Certificate check every Monday'),
        'help' => lang('Every Monday tells you when the site\'s certificate runs out within three weeks.'),
        'boxes' => array(
            'start' => $start($monday, '09:00', 'weekly', 1, array(1)) + array('setting' => array('time', 'time')),
            'check' => array('kind' => 'web_check', 'config' => array('url' => ($site !== '') ? $site : 'https://', 'mode' => 'ssl', 'days' => 21),
                'setting' => array('url', 'url')),
            'tell'  => $tell($result_of(lang('The site\'s certificate needs renewing: {{result}}'), 'check')),
        ),
        'edges' => array(array('start', 'check', 'next'), array('check', 'tell', 'failed')));

    if ($has('tasks_overdue')) {
        $out[] = array('key' => 'overdue', 'category' => 'team', 'icon' => 'bi-exclamation-diamond',
            'title' => lang('Overdue tasks every working day'),
            'help' => lang('Every working morning says how many tasks have gone past their due date, when there are any.'),
            'boxes' => array(
                'start' => $start($tomorrow, '10:00', 'workdays') + array('setting' => array('time', 'time')),
                'any'   => $count('tasks_overdue'),
                'tell'  => $tell(lang('{{count:tasks_overdue}} tasks have gone past their due date. The list is under My Tasks.')),
            ),
            'edges' => array(array('start', 'any', 'next'), array('any', 'tell', 'yes')));
    }

    if ($has('stock_low')) {
        $out[] = array('key' => 'stock', 'category' => 'sales', 'icon' => 'bi-box-seam',
            'title' => lang('Stock warning every morning'),
            'help' => lang('Every morning tells you how many products have 5 or fewer left in stock, when there are any.'),
            'boxes' => array(
                'start' => $start($tomorrow, '08:30', 'daily') + array('setting' => array('time', 'time')),
                'any'   => $count('stock_low', 5),
                'tell'  => array('kind' => 'notify', 'config' => array('user_ids' => array($me), 'text' => lang('{{count:stock_low:5}} products have 5 or fewer left in stock.')),
                    'setting' => array('person', 'user_ids')),
            ),
            'edges' => array(array('start', 'any', 'next'), array('any', 'tell', 'yes')));
    }

    if ($has('invoices_overdue')) {
        $out[] = array('key' => 'invoices', 'category' => 'sales', 'icon' => 'bi-receipt',
            'title' => lang('Overdue invoices every Monday'),
            'help' => lang('Every Monday says how many sales invoices are overdue and their total, when there are any.'),
            'boxes' => array(
                'start' => $start($monday, '09:30', 'weekly', 1, array(1)) + array('setting' => array('time', 'time')),
                'any'   => $count('invoices_overdue'),
                'tell'  => $tell(lang('{{count:invoices_overdue}} sales invoices are overdue, {{count:invoices_overdue_amount}} in all.')),
            ),
            'edges' => array(array('start', 'any', 'next'), array('any', 'tell', 'yes')));
    }

    // On the first of every month, a task to write the monthly report.
    $out[] = array('key' => 'monthly', 'category' => 'team', 'icon' => 'bi-calendar-month',
        'title' => lang('Monthly report task'),
        'help' => lang('On the first of every month opens a task to write the monthly report, due in five days.'),
        'boxes' => array(
            'start' => $start($first_of_month, '09:00', 'monthly') + array('setting' => array('time', 'time')),
            'task'  => array('kind' => 'task_open', 'config' => array('title' => lang('Monthly report for {{date}}'), 'description' => '', 'assignees' => array($me),
                'channel_id' => $channel, 'due_in' => 5, 'priority' => 'high'), 'setting' => array('person', 'assignees')),
        ),
        'edges' => array(array('start', 'task', 'next')));

    // A channel greets the people who join it and asks who they are: any
    // public channel, the greeting posted in the one joined.
    if (!empty($fill['join'])) {
        $out[] = array('key' => 'welcome', 'category' => 'team', 'icon' => 'bi-person-plus',
            'title' => lang('Welcome message'),
            'help' => lang('Greets the people who join a channel and asks them to say a few words about themselves.'),
            'boxes' => array(
                'start' => array('kind' => 'join', 'config' => array('channel_id' => 0)),
                'greet' => array('kind' => 'post', 'config' => array('channel_id' => -1,
                    'body' => lang('Welcome to {{channel}}, {{newcomer}}! 👋 Could you tell us a little about yourself: who you are and what you work on?'))),
            ),
            'edges' => array(array('start', 'greet', 'next')));
    }

    // Started by something happening on the site: only the events the
    // person may wait for (ws_events_js_config()).
    $events = (array) ($fill['events'] ?? array());
    $on = function ($event, $filter = array()) {
        return array('kind' => 'event', 'config' => $filter + array('event' => $event, 'page_id' => 0, 'status' => '', 'channel_id' => 0));
    };
    $task = function ($title, $priority) use ($channel, $me) {
        return array('kind' => 'task_open', 'config' => array('title' => $title, 'description' => '{{record}}' . "\n" . '{{record_link}}',
            'assignees' => array($me), 'channel_id' => $channel, 'due_in' => 2, 'priority' => $priority), 'setting' => array('person', 'assignees'));
    };

    // The tag alone: each reader sees of the order what their rights show.
    if (in_array('order.created', $events, true)) {
        $out[] = array('key' => 'order_event', 'category' => 'sales', 'icon' => 'bi-bag-plus',
            'title' => lang('New order to the channel'),
            'help' => lang('Every new order is written in the channel as it comes in.'),
            'boxes' => array('start' => $on('order.created'), 'tell' => $tell(lang('New order {{record}}'))),
            'edges' => array(array('start', 'tell', 'next')));
    }

    // The fields are drawn by the form's card for the readers who may open
    // the submission; the text only tags it.
    if (in_array('form.submitted', $events, true)) {
        $out[] = array('key' => 'form_event', 'category' => 'site', 'icon' => 'bi-ui-checks',
            'title' => lang('Web form to the channel'),
            'help' => lang('Every submitted form is written in the channel with its fields; “Make a task of it” on the message makes it a task.'),
            'boxes' => array('start' => $on('form.submitted'), 'tell' => $tell('{{record}}')),
            'edges' => array(array('start', 'tell', 'next')));
    }

    if (in_array('stock.low', $events, true)) {
        $out[] = array('key' => 'stock_event', 'category' => 'sales', 'icon' => 'bi-box-seam',
            'title' => lang('Task when stock runs low'),
            'help' => lang('A product that falls to the site\'s low-stock threshold becomes a task to restock it.'),
            'boxes' => array('start' => $on('stock.low'), 'task' => $task(lang('Restock {{record_title}}'), 'high')),
            'edges' => array(array('start', 'task', 'next')));
    }

    // A line for each task that goes past its due date.
    if (in_array('workspace.task.overdue', $events, true)) {
        $out[] = array('key' => 'overdue_event', 'category' => 'team', 'icon' => 'bi-alarm',
            'title' => lang('Overdue tasks to the channel'),
            'help' => lang('A line in the channel for each of its tasks that goes past its due date.'),
            'boxes' => array('start' => $on('workspace.task.overdue', array('channel_id' => $channel)), 'tell' => $tell(lang('Overdue: {{record}}'))),
            'edges' => array(array('start', 'tell', 'next')));
    }

    // An approval request approved: the next step as a task.
    if (in_array('workspace.approval.decided', $events, true)) {
        $out[] = array('key' => 'approval_event', 'category' => 'team', 'icon' => 'bi-patch-check',
            'title' => lang('Next task when approved'),
            'help' => lang('When an approval request of the channel is approved, a task for the next step is opened for you.'),
            'boxes' => array('start' => $on('workspace.approval.decided', array('status' => 'approved', 'channel_id' => $channel)),
                'task' => $task(lang('Next step: {{record_title}}'), 'normal')),
            'edges' => array(array('start', 'task', 'next')));
    }

    // The templates a scheduled action has no form for: tasks that wait for
    // each other, a wait between them, approvals and their outcomes.

    // A new customer welcomed, then asked three days later how it is going;
    // the person is told when that follow-up is not done within two days.
    $out[] = array('key' => 'new_customer', 'category' => 'sales', 'icon' => 'bi-person-plus',
        'title' => lang('New customer'),
        'help' => lang('A welcome task, a follow-up three days after it is done, and a word to you when the follow-up waits over two days.'),
        'boxes' => array(
            'start'   => array('kind' => 'manual', 'config' => array('subject' => '')),
            'welcome' => array('kind' => 'task', 'config' => array('title' => lang('Welcome the new customer'),
                'description' => lang('Call or write to say welcome, and note what they need.'), 'assignees' => array($me), 'due_in' => 1, 'priority' => 'high'),
                'setting' => array('person', 'assignees')),
            'wait'    => array('kind' => 'delay', 'config' => array('amount' => 3, 'unit' => 'days')),
            'follow'  => array('kind' => 'task', 'config' => array('title' => lang('Ask the customer how it is going'), 'description' => '',
                'assignees' => array($me), 'due_in' => 2, 'timeout' => 48), 'setting' => array('person', 'assignees')),
            'late'    => array('kind' => 'notify', 'config' => array('user_ids' => array($me),
                'text' => lang('Nobody has asked the new customer how it is going yet.')), 'setting' => array('person', 'user_ids')),
        ),
        'edges' => array(array('start', 'welcome', 'next'), array('welcome', 'wait', 'done'), array('wait', 'follow', 'done'),
            array('follow', 'late', 'timeout')));

    // A quote approved in a channel, with a next step for each way the
    // request ends; a withdrawn request ends the flow.
    $out[] = array('key' => 'quote_approval', 'category' => 'sales', 'icon' => 'bi-file-earmark-check',
        'title' => lang('Quote approval'),
        'help' => lang('Asks for a quote to be approved within two days, then opens the next task: send it, or rework it.'),
        'boxes' => array(
            'start'  => array('kind' => 'manual', 'config' => array('subject' => '')),
            'ask'    => array('kind' => 'approval', 'config' => array('channel_id' => $channel, 'title' => lang('Approve this quote'),
                'text' => lang('Please look at the quote and approve or reject it.'), 'approvers' => array($me), 'rule' => 'any', 'closes_in' => 48),
                'settings' => array(array('channel', 'channel_id'), array('person', 'approvers'))),
            'send'   => array('kind' => 'task_open', 'config' => array('title' => lang('Send the approved quote to the customer'), 'description' => '',
                'assignees' => array($me), 'channel_id' => 0, 'due_in' => 1, 'priority' => 'high'), 'setting' => array('person', 'assignees')),
            'rework' => array('kind' => 'task_open', 'config' => array('title' => lang('Rework the quote'), 'description' => '',
                'assignees' => array($me), 'channel_id' => 0, 'due_in' => 2, 'priority' => 'normal'), 'setting' => array('person', 'assignees')),
            'late'   => array('kind' => 'notify', 'config' => array('user_ids' => array($me), 'text' => lang('Nobody decided on the quote in time.')),
                'setting' => array('person', 'user_ids')),
            'gone'   => array('kind' => 'end', 'config' => array()),
        ),
        'edges' => array(array('start', 'ask', 'next'), array('ask', 'send', 'approved'), array('ask', 'rework', 'rejected'),
            array('ask', 'late', 'expired'), array('ask', 'gone', 'withdrawn')));

    // A text written, approved once the writing task is done, then
    // published; the writer is told when it is rejected.
    $out[] = array('key' => 'text_review', 'category' => 'team', 'icon' => 'bi-pencil-square',
        'title' => lang('Text review before publishing'),
        'help' => lang('A writing task, an approval once it is done, then a task to publish it; you are told when the text is rejected.'),
        'boxes' => array(
            'start'   => array('kind' => 'manual', 'config' => array('subject' => '')),
            'write'   => array('kind' => 'task', 'config' => array('title' => lang('Write the text'), 'description' => '',
                'assignees' => array($me), 'due_in' => 5, 'priority' => 'normal'), 'setting' => array('person', 'assignees')),
            'review'  => array('kind' => 'approval', 'config' => array('channel_id' => $channel, 'title' => lang('Approve the text for publishing'),
                'text' => lang('The writing task #{{node.write.task_id}} is done. Please read the text and approve or reject it.'),
                'approvers' => array($me), 'rule' => 'any', 'closes_in' => 72),
                'settings' => array(array('channel', 'channel_id'), array('person', 'approvers'))),
            'publish' => array('kind' => 'task_open', 'config' => array('title' => lang('Publish the approved text'), 'description' => '',
                'assignees' => array($me), 'channel_id' => 0, 'due_in' => 1, 'priority' => 'normal'), 'setting' => array('person', 'assignees')),
            'told'    => array('kind' => 'notify', 'config' => array('user_ids' => array($me), 'text' => lang('The text was not approved.')),
                'setting' => array('person', 'user_ids')),
        ),
        'edges' => array(array('start', 'write', 'next'), array('write', 'review', 'done'), array('review', 'publish', 'approved'),
            array('review', 'told', 'rejected')));

    return array_map('ws_flow_template_build', $out);
}

/**
 * One template from its boxes in order and its arrows: the boxes placed
 * left to right in columns of 256 canvas units (a 208 box and its gap),
 * the boxes that share a column (the follow-ups of one outcome) stacked
 * 112 apart. Pure.
 *
 * @param array $spec key, category, icon, title, help, boxes (id => kind,
 *                    config, setting [type, key] or settings, a list of
 *                    them), edges ([from, to, on])
 * @return array
 */
function ws_flow_template_build($spec)
{
    $depth = array();
    $rows = array();
    $nodes = array();
    $edges = array();
    $settings = array();

    foreach ($spec['edges'] as $edge) {
        $depth[$edge[1]] = ($depth[$edge[0]] ?? 0) + 1;
        $edges[] = array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
    }

    foreach ($spec['boxes'] as $id => $box) {
        $column = $depth[$id] ?? 0;
        $row = $rows[$column] ?? 0;
        $rows[$column] = $row + 1;
        $nodes[] = array('id' => $id, 'kind' => $box['kind'], 'label' => '', 'x' => $column * 256, 'y' => $row * 112, 'config' => $box['config']);

        foreach (!empty($box['setting']) ? array($box['setting']) : (array) ($box['settings'] ?? array()) as $setting) {
            list($type, $key) = $setting;
            $settings[$type] = $settings[$type] ?? array('type' => $type, 'targets' => array());
            $settings[$type]['targets'][] = array($id, $key);
        }
    }

    $graph = ws_flow_graph_normalize(array('v' => 1, 'nodes' => $nodes, 'edges' => $edges, 'notes' => array()));

    return array(
        'key'      => $spec['key'],
        'category' => $spec['category'],
        'title'    => $spec['title'],
        'help'     => $spec['help'],
        'icon'     => $spec['icon'],
        'start'    => $graph['nodes'][0]['kind'],
        'graph'    => $graph,
        'boxes'    => count($graph['nodes']),
        'threads'  => count(ws_flow_template_threads($graph)),
        'settings' => array_values($settings),
        'motion'   => ws_flow_template_motion($graph),
    );
}

/**
 * The values a template's boxes read from earlier boxes: one thread for
 * each {{node.<id>.<field>}} of a box's setting, from the box it names to
 * the box and setting that read it, in the graph's order. Pure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array[] from, to, key
 */
function ws_flow_template_threads($graph)
{
    $ids = array_column($graph['nodes'], 'id');
    $out = array();

    foreach ($graph['nodes'] as $node) {
        foreach ($node['config'] as $key => $value) {
            $texts = array();

            if (is_array($value)) {
                array_walk_recursive($value, function ($item) use (&$texts) {
                    $texts[] = $item;
                });
            } else {
                $texts[] = $value;
            }

            foreach ($texts as $text) {
                if (!is_string($text) || !preg_match_all('/\{\{node\.([a-z][a-z0-9_]*)\.[a-z_][a-z0-9_]*\}\}/', $text, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $from) {
                    $thread = array('from' => $from, 'to' => $node['id'], 'key' => (string) $key);

                    if (in_array($from, $ids, true) && !in_array($thread, $out, true)) {
                        $out[] = $thread;
                    }
                }
            }
        }
    }

    return $out;
}

/**
 * When the parts of a template appear as it is placed, in milliseconds from
 * the start: the boxes one after another, 150 apart, each in 420; an arrow
 * grows in 200 once both its boxes are in; the threads draw after the last
 * arrow, 200 apart, each in 650; then the setup panel. Pure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array boxes [id, at, ms], arrows [edge (its index), at, ms],
 *               threads [from, to, key, at, ms], panel
 */
function ws_flow_template_motion($graph)
{
    $box_ms = 420;
    $box_gap = 150;
    $arrow_ms = 200;
    $thread_ms = 650;
    $thread_gap = 200;

    $boxes = array();
    $in = array();
    $end = 0;

    foreach ($graph['nodes'] as $n => $node) {
        $boxes[] = array('id' => $node['id'], 'at' => $n * $box_gap, 'ms' => $box_ms);
        $in[$node['id']] = $n * $box_gap + $box_ms;
        $end = max($end, $in[$node['id']]);
    }

    $arrows = array();

    foreach ($graph['edges'] as $index => $edge) {
        $at = max($in[$edge['from']] ?? 0, $in[$edge['to']] ?? 0);
        $arrows[] = array('edge' => $index, 'at' => $at, 'ms' => $arrow_ms);
        $end = max($end, $at + $arrow_ms);
    }

    $threads = array();
    $first = $end;

    foreach (ws_flow_template_threads($graph) as $n => $thread) {
        $at = $first + $n * $thread_gap;
        $threads[] = $thread + array('at' => $at, 'ms' => $thread_ms);
        $end = max($end, $at + $thread_ms);
    }

    return array('boxes' => $boxes, 'arrows' => $arrows, 'threads' => $threads, 'panel' => $end);
}

/**
 * The setup panel's answers checked, one for each setting of the template,
 * and given back as the boxes keep them: a channel id, people ids (at most
 * 20, each once), a time HH:MM, a web address, e-mail addresses joined by
 * ", ". Answers to settings the template does not have are dropped. Pure
 * but for the address check (validate_email_address()).
 *
 * @param array $template ws_flow_templates() entry
 * @param array $answers  setting type => value
 * @return array ok, error, field (the setting refused), values (type => value)
 */
function ws_flow_template_answers_check($template, $answers)
{
    $answers = is_array($answers) ? $answers : array();
    $values = array();

    foreach ((array) ($template['settings'] ?? array()) as $setting) {
        $type = (string) $setting['type'];
        $given = $answers[$type] ?? null;
        $error = '';
        $value = null;

        switch ($type) {
            case 'channel':
                $value = is_scalar($given) ? (int) $given : 0;
                $error = ($value > 0) ? '' : lang('Choose the channel it writes in.');
                break;

            case 'person':
                $value = array();

                foreach ((is_array($given) ? $given : array()) as $id) {
                    $id = is_scalar($id) ? (int) $id : 0;

                    if ($id > 0 && !in_array($id, $value, true)) {
                        $value[] = $id;
                    }
                }

                if (empty($value)) {
                    $error = lang('Choose at least one person.');
                } elseif (count($value) > 20) {
                    $error = lang(array('string' => 'Choose at most {var:1} people.', 'vars' => 20));
                }
                break;

            case 'time':
                $error = lang('Give a time of day, such as 09:00.');

                if (is_string($given) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($given), $match)) {
                    $value = sprintf('%02d:%02d', (int) $match[1], (int) $match[2]);
                    $error = '';
                }
                break;

            case 'url':
                $value = is_string($given) ? trim($given) : '';
                $error = ((strlen($value) <= 2000) && preg_match('#^https?://[^\s/?\#]+#i', $value) && (filter_var($value, FILTER_VALIDATE_URL) !== false))
                    ? '' : lang('Give a web address that starts with http:// or https://.');
                break;

            case 'address':
                $addresses = ws_scheduled_addresses(is_string($given) ? $given : '');
                $value = implode(', ', $addresses['to']);
                $error = $addresses['error'];
                break;

            default:
                continue 2;
        }

        if ($error !== '') {
            return array('ok' => false, 'error' => $error, 'field' => $type, 'values' => array());
        }

        $values[$type] = $value;
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => $values);
}

/**
 * A flow's graph with the setup panel's values written into the box
 * settings the template names. Only a box that is still the template's (the
 * same id and kind) is written; anything changed meanwhile on the canvas
 * stays as it is. Pure.
 *
 * @param array $template ws_flow_templates() entry
 * @param array $graph    the flow's draft (ws_flow_graph_normalize())
 * @param array $values   ws_flow_template_answers_check() values
 * @return array the graph, normalized
 */
function ws_flow_template_apply($template, $graph, $values)
{
    $kinds = array_column($template['graph']['nodes'], 'kind', 'id');

    foreach ((array) ($template['settings'] ?? array()) as $setting) {
        if (!array_key_exists($setting['type'], $values)) {
            continue;
        }

        foreach ($setting['targets'] as $target) {
            foreach ($graph['nodes'] as $n => $node) {
                if (($node['id'] === $target[0]) && (($kinds[$node['id']] ?? '') === $node['kind'])) {
                    $graph['nodes'][$n]['config'][$target[1]] = $values[$setting['type']];
                }
            }
        }
    }

    return ws_flow_graph_normalize($graph);
}

/**
 * The templates offered to the person, filled for them as the gallery fills
 * them: their texts go to them, no channel open.
 *
 * @param array      $viewer
 * @param array|null $scheduled ws_scheduled_js_config() of the person, when at hand
 * @return array[]
 */
function ws_flow_templates_for($viewer, $scheduled = null)
{
    if ($scheduled === null && function_exists('ws_scheduled_js_config')) {
        $scheduled = ws_scheduled_js_config($viewer);
    }

    if (!is_array($scheduled)) {
        return array();
    }

    return ws_flow_templates(array(
        'me'       => (int) $viewer['id'],
        'channel'  => 0,
        'site'     => (string) $scheduled['site'],
        'email'    => (string) $scheduled['email'],
        'today'    => date('Y-m-d'),
        'metrics'  => array_keys((array) $scheduled['metrics']),
        'recap_ai' => !empty($scheduled['recap_ai']),
        'join'     => !empty($scheduled['join']),
        'events'   => array_keys((array) ($scheduled['events']['list'] ?? array())),
    ));
}

/**
 * The setup panel's answers for a template placed on a flow: checked here,
 * whatever the screen checked, written into the flow's draft (a save, with
 * its draft_rev), and with publish the draft published, through the same
 * checks Publish makes (rights on each box's channel, people, addresses).
 * The draft keeps the answers when the publish is refused.
 *
 * @param array $viewer
 * @param array $flow    ws_flow_for() at edit
 * @param array $data    key (the template's), answers (type => value),
 *                       draft_rev, publish (bool)
 * @return array ok, error, status, field, draft_rev, problems, published,
 *               publish_error
 */
function ws_flow_template_setup($viewer, $flow, $data)
{
    $none = array('draft_rev' => 0, 'problems' => array(), 'published' => false, 'publish_error' => '');
    $template = null;

    foreach (ws_flow_templates_for($viewer) as $item) {
        if ($item['key'] === (string) ($data['key'] ?? '')) {
            $template = $item;
        }
    }

    if ($template === null) {
        return ws_flow_refusal(lang('That template is not offered to you.'), 404, 'key') + $none;
    }

    $check = ws_flow_template_answers_check($template, $data['answers'] ?? array());

    if (!$check['ok']) {
        return ws_flow_refusal($check['error'], 422, $check['field']) + $none;
    }

    $graph = ws_flow_template_apply($template, ws_flow_graph_of($flow), $check['values']);
    $saved = ws_flow_save($viewer, array('graph' => $graph, 'draft_rev' => (int) ($data['draft_rev'] ?? 0)), $flow);

    if (!$saved['ok']) {
        return $saved + $none;
    }

    $out = array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'draft_rev' => $saved['draft_rev'], 'problems' => $saved['problems'],
        'published' => false, 'publish_error' => '');

    if (empty($data['publish'])) {
        return $out;
    }

    $fresh = ws_flow_for($viewer, (int) $flow['id'], 'edit');
    $published = $fresh ? ws_flow_publish($viewer, $fresh, array('draft_rev' => $saved['draft_rev'])) : ws_flow_refusal(lang('The flow could not be published.'));

    $out['published'] = !empty($published['ok']);
    $out['publish_error'] = $out['published'] ? '' : (string) $published['error'];

    return $out;
}

/**
 * A scheduled action as a flow graph its boxes run the same way (pure): the
 * "when" as the start box (at → schedule, event, join, trigger → started by
 * hand), each condition a condition box on the yes arrow, the step its step
 * box (task → task_open, trigger → start_action), each follow-up a box on
 * the arrow of its outcome (skipped: the no arrow of every condition). A
 * follow-up on failed that starts the action itself again ($self_id) is the
 * step box's own retry, as many times as the chain's depth allows. A
 * follow-up's {{result}} reads the step box's sentence. What a flow keeps
 * otherwise is told in left, in sentences: a follow-up on skipped without a
 * condition, and the follow-ups of an approval on done, which a flow runs
 * once the request is approved rather than once it is asked. Boxes are laid
 * out as the templates are (ws_flow_template_build()).
 *
 * @param array    $data    name, rules, action, follow as the form reads them
 *                          (ws_scheduled_form_data(), a draft's data)
 * @param string[] $money   the counts in money (ws_scheduled_money_metrics()):
 *                          their condition is kept in minor units, the box
 *                          takes the number as written
 * @param int      $self_id the action's own id, 0 for a draft
 * @return array name, graph (ws_flow_graph_normalize()), left (sentences)
 */
function ws_flow_graph_from_scheduled($data, $money = array(), $self_id = 0)
{
    $data = is_array($data) ? $data : array();
    $rules = array_values(array_filter((array) ($data['rules'] ?? array()), 'is_array'));
    $rules = function_exists('ws_scheduled_rules_as_sent') ? ws_scheduled_rules_as_sent($rules, $money) : $rules;
    $boxes = array();
    $edges = array();
    $left = array();
    $start = array('kind' => 'manual', 'config' => array('subject' => ''));
    $conditions = array();

    foreach ($rules as $rule) {
        $type = (string) ($rule['type'] ?? '');
        unset($rule['type'], $rule['label']);

        if (($type === 'at') && ($start['kind'] === 'manual')) {
            $start = array('kind' => 'schedule', 'config' => $rule);
        } elseif (in_array($type, array('event', 'join'), true) && ($start['kind'] === 'manual')) {
            $start = array('kind' => $type, 'config' => $rule);
        } elseif (in_array($type, ws_flow_condition_types(), true)) {
            $conditions[] = array('kind' => 'condition', 'config' => array('rule' => array('type' => $type) + $rule));
        }
    }

    // A box's settings as the action keeps them: its type gives the box,
    // addresses kept as a list become the one text the box takes.
    $box = function ($action) {
        $action = is_array($action) ? $action : array();
        $type = (string) ($action['type'] ?? '');
        $kinds = array('task' => 'task_open', 'trigger' => 'start_action');
        unset($action['type'], $action['label'], $action['page_label']);

        if (isset($action['to']) && is_array($action['to'])) {
            $action['to'] = implode(', ', $action['to']);
        }

        return array('kind' => $kinds[$type] ?? $type, 'config' => $action);
    };

    $boxes['start'] = $start;
    $from = array('start', 'next');

    foreach ($conditions as $i => $condition) {
        $id = 'if' . ($i + 1);
        $boxes[$id] = $condition;
        $edges[] = array($from[0], $id, $from[1]);
        $from = array($id, 'yes');
    }

    $step = $box($data['action'] ?? array());
    $boxes['step'] = $step;
    $edges[] = array($from[0], 'step', $from[1]);
    $n = 0;

    foreach ((array) ($data['follow'] ?? array()) as $item) {
        $on = (string) ($item['on'] ?? '');
        $do = is_array($item['do'] ?? null) ? $item['do'] : array();

        if (($on === 'failed') && ($self_id > 0) && (($do['type'] ?? '') === 'trigger') && ((int) ($do['target_id'] ?? 0) === (int) $self_id)) {
            $boxes['step']['config']['retry'] = WS_FLOW_RETRY_MAX;
            $boxes['step']['config']['retry_after'] = max(1, (int) ($do['delay'] ?? 1));
            continue;
        }

        if (($on === 'skipped') && empty($conditions)) {
            $left[] = lang('A follow-up on skipped was left out: the action has no condition that could skip it.');
            continue;
        }

        $id = 'then' . (++$n);
        $boxes[$id] = $box($do);

        foreach ($boxes[$id]['config'] as $key => $value) {
            if (is_string($value)) {
                $boxes[$id]['config'][$key] = str_replace('{{result}}', '{{node.step.text}}', $value);
            }
        }

        if ($on === 'skipped') {
            foreach (array_keys($conditions) as $i) {
                $edges[] = array('if' . ($i + 1), $id, 'no');
            }

            continue;
        }

        if (($on === 'done') && ($step['kind'] === 'approval')) {
            $left[] = lang('A follow-up of the approval now runs once the request is approved, not once it is asked.');
            $on = 'approved';
        }

        $edges[] = array('step', $id, in_array($on, array('done', 'failed', 'always', 'approved'), true) ? $on : 'always');
    }

    $built = ws_flow_template_build(array('key' => '', 'category' => '', 'title' => '', 'help' => '', 'icon' => '', 'boxes' => $boxes, 'edges' => $edges));

    return array('name' => (string) ($data['name'] ?? ''), 'graph' => $built['graph'], 'left' => $left);
}
