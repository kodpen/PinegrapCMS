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
 *
 * Templates whose counts the person may not read, or whose texts a flow
 * cannot fill yet (a newcomer, an event's record, a count "since the last
 * run"), are not offered.
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
 *                    was missed)
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

    $today = (string) ($fill['today'] ?? '');
    $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) === 1) ? strtotime($today . ' 12:00:00 UTC') : time();
    $tomorrow = gmdate('Y-m-d', $day + 86400);
    $monday = gmdate('Y-m-d', strtotime('next monday', $day));
    $first_of_month = gmdate('Y-m-01', strtotime('first day of next month', $day));
    $today = gmdate('Y-m-d', $day);

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

    return array_map('ws_flow_template_build', $out);
}

/**
 * One template from its boxes in order and its arrows: the boxes placed
 * left to right in columns of 256 canvas units (a 208 box and its gap),
 * the boxes that share a column (the follow-ups of one outcome) stacked
 * 112 apart. Pure.
 *
 * @param array $spec key, category, icon, title, help, boxes (id => kind,
 *                    config, setting [type, key]), edges ([from, to, on])
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

        if (!empty($box['setting'])) {
            list($type, $key) = $box['setting'];
            $settings[$type] = $settings[$type] ?? array('type' => $type, 'targets' => array());
            $settings[$type]['targets'][] = array($id, $key);
        }
    }

    $graph = ws_flow_graph_normalize(array('v' => 1, 'nodes' => $nodes, 'edges' => $edges, 'notes' => array()));
    $threads = 0;

    foreach ($graph['nodes'] as $node) {
        array_walk_recursive($node['config'], function ($value) use (&$threads) {
            if (is_string($value)) {
                $threads += preg_match_all('/\{\{node\.[a-z][a-z0-9_]*\.[a-z_][a-z0-9_]*\}\}/', $value);
            }
        });
    }

    return array(
        'key'      => $spec['key'],
        'category' => $spec['category'],
        'title'    => $spec['title'],
        'help'     => $spec['help'],
        'icon'     => $spec['icon'],
        'start'    => $graph['nodes'][0]['kind'],
        'graph'    => $graph,
        'boxes'    => count($graph['nodes']),
        'threads'  => $threads,
        'settings' => array_values($settings),
    );
}
