<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The firewall log's events (view_waf_log.php) on the list table driver
 * (includes/fn/list_table.php), shared by the screen and the driver's rows
 * action (list_table_rows, includes/panel/lists.php).
 *
 * The screen printed the newest 500 rows of the period on every visit. The
 * events are now read one batch at a time, sorted by the kept sort
 * and searched on the server; the period, category and search filters stay in
 * the address and also drive the summary above the table.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The periods of the period selector, in days.
 *
 * @return array days (as a string) => label
 */
function pg_waf_log_list_ranges()
{
    return array(
        '1'  => lang('Last 24 hours'),
        '7'  => lang('Last 7 days'),
        '30' => lang('Last 30 days'),
    );
}

/**
 * The categories of the category filter; '' is every category.
 *
 * @return array category => label
 */
function pg_waf_log_list_categories()
{
    return array(
        ''         => lang('All categories'),
        'sqli'     => lang('SQL Injection'),
        'xss'      => lang('Cross-site Scripting'),
        'lfi'      => lang('Path Traversal'),
        'rce'      => lang('Command Injection'),
        'protocol' => lang('Protocol Abuse'),
        'bot'      => lang('Bots'),
        'tool'     => lang('Scanners'),
        'rate'     => lang('Rate Limit'),
        'login'    => lang('Sign-in Lockouts'),
        'iplist'   => lang('IP List'),
        'country'  => lang('Country Restriction'),
        'ban'      => lang('Bans'),
    );
}

/**
 * The badge class and label of each action.
 *
 * @return array action => array(class, label)
 */
function pg_waf_log_list_actions()
{
    return array(
        'block'       => array('bg-danger', lang('Blocked')),
        'rate'        => array('bg-danger', lang('Rate limited')),
        'ban'         => array('bg-black fg-white', lang('Banned')),
        'would-block' => array('bg-warning fg-black', lang('Would block')),
        'would-rate'  => array('bg-warning fg-black', lang('Would rate limit')),
        'would-ban'   => array('bg-warning fg-black', lang('Would ban')),
        'log'         => array('bg-secondary', lang('Recorded')),
    );
}

/**
 * The actions the summary adds up under one card. A ban row is the decision
 * about a request the refusing row already counts, so the request cards
 * leave it out and the bans are counted on their own.
 *
 * @param string $group blocked, would_block or bans
 * @return array actions
 */
function pg_waf_log_list_request_actions($group)
{
    $groups = array(
        'blocked'     => array('block', 'rate'),
        'would_block' => array('would-block', 'would-rate'),
        'bans'        => array('ban', 'would-ban'),
    );

    return isset($groups[$group]) ? $groups[$group] : array();
}

/**
 * The filters, each checked: a value that is not one of the choices (or not
 * one value: ?range[]=x) counts as not given. The search keeps its first 200
 * characters.
 *
 * @param array $input range, category, search
 * @return array range ('7' when not given), category, search
 */
function pg_waf_log_list_filters(array $input)
{
    $value = function ($key) use ($input) {
        return (isset($input[$key]) && is_scalar($input[$key])) ? (string) $input[$key] : '';
    };

    $range = $value('range');
    $category = $value('category');
    $search = trim($value('search'));

    if (strlen($search) > 200) {
        $search = substr($search, 0, 200);
    }

    return array(
        'range'    => isset(pg_waf_log_list_ranges()[$range]) ? $range : '7',
        'category' => isset(pg_waf_log_list_categories()[$category]) ? $category : '',
        'search'   => $search,
    );
}

/**
 * The screen's address with the filters that differ from the defaults
 * (period 7 days, every category, no search), relative to the software
 * directory. The Release, Allow and Clear forms post to it and their handlers
 * redirect to it, so the admin lands back on the view the post came from.
 *
 * @param array $filters pg_waf_log_list_filters()'s answer
 * @return string
 */
function pg_waf_log_list_url(array $filters)
{
    $query = array();

    if (isset($filters['range']) && (string) $filters['range'] !== '' && (string) $filters['range'] !== '7') {
        $query['range'] = (string) $filters['range'];
    }

    if (isset($filters['category']) && (string) $filters['category'] !== '') {
        $query['category'] = (string) $filters['category'];
    }

    if (isset($filters['search']) && (string) $filters['search'] !== '') {
        $query['search'] = (string) $filters['search'];
    }

    return 'view_waf_log.php' . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

/**
 * The condition of the filters with its values as ? placeholders: the
 * period back from $now, the category, and the search by the table box's
 * rule (pg_waf_log_list_search()), so the toolbar search and the table's own
 * box find the same events.
 *
 * @param array $filters pg_waf_log_list_filters()'s answer
 * @param int   $now     the time the period ends
 * @return array sql, params
 */
function pg_waf_log_list_condition(array $filters, $now)
{
    $sql = 'log_timestamp >= ?';
    $params = array((int) $now - ((int) $filters['range'] * 86400));

    if ($filters['category'] !== '') {
        $sql .= ' AND category = ?';
        $params[] = (string) $filters['category'];
    }

    $search = pg_waf_log_list_search(pg_list_search_terms($filters['search']));

    if ($search['sql'] !== '') {
        $sql .= ' AND ' . $search['sql'];
        $params = array_merge($params, $search['params']);
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * The list's sort columns, the kept sort, the key each sorts by and the
 * ORDER BY. Rows that tie on every level keep the order they were written
 * in, in the first level's direction.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels',
 *               'sort_keys', 'order_by')
 */
function pg_waf_log_list_sort()
{
    $sort_columns = array(
        'time'   => lang('Time'),
        'hits'   => lang('Hits'),
        'action' => lang('Action'),
        'ip'     => lang('IP Address'),
        'rule'   => lang('Rule'),
        'score'  => lang('Score'),
    );
    $sort_default = array('sort' => 'time', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // action's JSON body never fills $_REQUEST.
    $sort_levels = pg_list_state('view_waf_log', $sort_columns, $sort_default)['sorts'];

    // Only codes pg_list_state() let through reach pg_list_order_by(), and it
    // writes nothing but these. The time shown first is the last hit.
    $sort_keys = array(
        'time'   => 'last_seen',
        'hits'   => 'hit_count',
        'action' => pg_list_label_order('action', array_map(function ($badge) {
            return $badge[1];
        }, pg_waf_log_list_actions())),
        'ip'     => 'ip_address',
        'rule'   => 'rule_id',
        'score'  => 'score',
    );

    return array(
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $sort_levels,
        'sort_keys' => $sort_keys,
        'order_by' => pg_list_order_by($sort_levels, $sort_keys) . ', id ' . (($sort_levels[0]['order'] === 'desc') ? 'desc' : 'asc'),
    );
}

/**
 * The search box's condition: every word in the address, the method, the
 * URL, the user agent, the rule, the reference, the target or the matched
 * text, or in the name an action is shown under.
 *
 * @param array $terms pg_list_search_terms()'s answer
 * @return array array('sql', 'params') as pg_list_search_where() answers
 */
function pg_waf_log_list_search(array $terms)
{
    $search = pg_waf_log_list_search_spec();

    return pg_list_search_where($terms, $search['columns'], $search['labels']);
}

/**
 * The columns and shown names the search box looks in, as
 * pg_list_search_where() takes them.
 *
 * @return array array('columns', 'labels')
 */
function pg_waf_log_list_search_spec()
{
    $labels = array();

    foreach (pg_waf_log_list_actions() as $action => $badge) {
        $labels[$action] = $badge[1];
    }

    return array(
        'columns' => array('ip_address', 'method', 'request_url', 'user_agent', 'rule_id', 'reference', 'target', 'matched'),
        'labels' => array(array('expr' => 'action', 'labels' => $labels)),
    );
}

/**
 * One event of the list.
 *
 * @param array $event a waf_log row
 * @param int   $now   the time "still firing" is measured from
 * @return array array('id', 'class', 'cells' => column key => cell)
 */
function pg_waf_log_list_row(array $event, $now)
{
    $actions = pg_waf_log_list_actions();
    $badge = isset($actions[$event['action']]) ? $actions[$event['action']] : array('bg-secondary', (string) $event['action']);

    // The relative age of the last hit tells a row still firing from one
    // that stopped after its cause was fixed; both show a clock time.
    $time = h(get_absolute_time(array('timestamp' => (int) $event['log_timestamp'], 'format' => 'plain_text')))
        . (((int) $event['last_seen'] > (int) $event['log_timestamp'])
            ? '<div class="fg-secondary" style="font-size:.75rem;">&rarr; ' . h(get_absolute_time(array('timestamp' => (int) $event['last_seen'], 'type' => 'time', 'format' => 'plain_text'))) . '</div>'
            : '')
        . '<div class="' . (((int) $event['last_seen'] > ((int) $now - 300)) ? 'fg-danger fw-semibold' : 'fg-secondary') . '" style="font-size:.75rem;">'
        . get_relative_time(array('timestamp' => (int) $event['last_seen']))
        . '</div>';

    $hits = ((int) $event['hit_count'] > 1)
        ? '<span class="badge bg-subtle-secondary fg-emphasis-secondary border">&times;' . pg_format_number((int) $event['hit_count'], 0) . '</span>'
        : '1';

    $rule = '<code>' . h($event['rule_id']) . '</code>'
        . ((isset($event['reference']) && ((string) $event['reference'] !== ''))
            ? '<div class="fg-secondary" style="font-size:.7rem;" title="' . h(lang('Reference')) . '">' . h($event['reference']) . '</div>'
            : '');

    $request = '<span class="fg-secondary">' . h($event['method']) . '</span> ' . h($event['request_url'])
        . '<div class="fg-secondary" style="font-size:.75rem;">' . h(mb_substr((string) $event['user_agent'], 0, 90)) . '</div>';

    $match = '<span class="fg-secondary">' . h($event['target']) . '</span><div><code>' . h($event['matched']) . '</code></div>';

    return array('id' => (string) (int) ($event['id'] ?? 0), 'class' => 'unselectable', 'cells' => array(
        'time' => pg_list_cell($time, 'small text-nowrap'),
        'hits' => pg_list_cell($hits, 'small'),
        'action' => pg_list_cell('<span class="badge ' . h($badge[0]) . '">' . h($badge[1]) . '</span>', ''),
        'ip' => pg_list_cell(h($event['ip_address']), 'font-monospace small text-nowrap'),
        'rule' => pg_list_cell($rule, 'small'),
        'score' => pg_list_cell((string) (int) $event['score'], 'small'),
        // A cell's max-width does not narrow an auto-layout column, so the
        // width limit sits on an inner block that wraps.
        'request' => pg_list_cell('<div class="text-break" style="white-space:normal;min-width:10rem;max-width:22rem;">' . $request . '</div>', 'small'),
        'match' => pg_list_cell('<div class="text-break" style="white-space:normal;min-width:8rem;max-width:16rem;">' . $match . '</div>', 'small'),
    ));
}

/**
 * The list's columns. The keys are the sort codes pg_waf_log_list_sort()
 * keeps, so the sort menu and the headings name the same sort.
 *
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_waf_log_list_columns()
{
    $sort_keys = pg_waf_log_list_sort()['sort_keys'];

    return array(
        'time' => array('heading' => lang('Time'), 'width' => '10rem', 'kind' => 'date', 'sort' => $sort_keys['time']),
        'hits' => array('heading' => lang('Hits'), 'width' => '5rem', 'sort' => $sort_keys['hits']),
        'action' => array('heading' => lang('Action'), 'width' => '8rem', 'kind' => 'badge', 'sort' => $sort_keys['action']),
        'ip' => array('heading' => lang('IP Address'), 'width' => '10rem', 'sort' => $sort_keys['ip']),
        'rule' => array('heading' => lang('Rule'), 'width' => '8rem', 'sort' => $sort_keys['rule']),
        'score' => array('heading' => lang('Score'), 'width' => '5rem', 'sort' => $sort_keys['score']),
        'request' => array('heading' => lang('Request'), 'width' => '22rem'),
        'match' => array('heading' => lang('Match'), 'width' => '16rem'),
    );
}

/**
 * The events as the list table driver reads them: the rows the screen's
 * filters select (its address, checked again), searched by
 * pg_waf_log_list_search()'s rule. The screen's gate is the source's: an
 * account above Manager, or a database without the log, gets null.
 *
 * @param array $user   the signed-in user
 * @param array $params array('filters' => range, category, search)
 * @param int|null $now the time the period ends; time() when null
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_waf_log_list_source($user, $params, $now = null)
{
    if (!isset($user['role']) || !is_numeric($user['role']) || ((int) $user['role'] > 2) || !pg_schema_has('waf_log')) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $now = ($now === null) ? time() : (int) $now;
    $filters = pg_waf_log_list_filters((is_array($params) && isset($params['filters']) && is_array($params['filters'])) ? $params['filters'] : array());
    $condition = pg_waf_log_list_condition($filters, $now);

    return pg_list_table_source(array(
        'list' => 'view_waf_log',
        'columns' => pg_waf_log_list_columns(),
        'from' => 'waf_log',
        'where' => $condition['sql'],
        'params' => $condition['params'],
        'id' => 'id',
        'select' => 'id, log_timestamp, last_seen, hit_count, action, ip_address, rule_id, reference, score, method, request_url, user_agent, target, matched',
        'search' => pg_waf_log_list_search_spec(),
        'levels' => pg_waf_log_list_sort()['sort_levels'],
        'rows' => function ($events) use ($now) {
            $rows = array();

            foreach ($events as $event) {
                $rows[] = pg_waf_log_list_row($event, $now);
            }

            return $rows;
        },
        'params_out' => array('filters' => $filters),
    ));
}
