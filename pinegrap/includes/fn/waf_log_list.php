<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The firewall log's events (view_waf_log.php), shared by the screen and the
 * panel action that pages them (waf_log_list_rows, includes/panel/lists.php).
 *
 * The screen printed the newest 500 rows of the period on every visit. The
 * events are now read one page at a time, sorted by the headings' kept sort
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
        ''         => lang('All'),
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
 * The condition of the filters with its values as ? placeholders: the
 * period back from $now, the category, and the search text anywhere in the
 * address, the URL, the rule, the reference or the user agent.
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

    if ($filters['search'] !== '') {
        $sql .= ' AND (ip_address LIKE ? OR request_url LIKE ? OR rule_id LIKE ? OR reference LIKE ? OR user_agent LIKE ?)';
        $params = array_merge($params, array_fill(0, 5, pg_list_like_pattern($filters['search'])));
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
    $labels = array();

    foreach (pg_waf_log_list_actions() as $action => $badge) {
        $labels[$action] = $badge[1];
    }

    return pg_list_search_where(
        $terms,
        array('ip_address', 'method', 'request_url', 'user_agent', 'rule_id', 'reference', 'target', 'matched'),
        array(array('expr' => 'action', 'labels' => $labels))
    );
}

/**
 * One event of the list.
 *
 * @param array $event a waf_log row
 * @param int   $now   the time "still firing" is measured from
 * @return array array('class', 'cells')
 */
function pg_waf_log_list_row(array $event, $now)
{
    $actions = pg_waf_log_list_actions();
    $badge = isset($actions[$event['action']]) ? $actions[$event['action']] : array('bg-secondary', (string) $event['action']);

    // The relative age of the last hit tells a row still firing from one
    // that stopped after its cause was fixed; both show a clock time.
    $time = h(date('Y-m-d H:i', (int) $event['log_timestamp']))
        . (((int) $event['last_seen'] > (int) $event['log_timestamp'])
            ? '<div class="fg-secondary" style="font-size:.75rem;">&rarr; ' . h(date('H:i', (int) $event['last_seen'])) . '</div>'
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

    return array('class' => 'unselectable', 'cells' => array(
        pg_list_cell($time, 'small text-nowrap'),
        pg_list_cell($hits, 'small'),
        pg_list_cell('<span class="badge ' . h($badge[0]) . '">' . h($badge[1]) . '</span>', ''),
        pg_list_cell(h($event['ip_address']), 'font-monospace small text-nowrap'),
        pg_list_cell($rule, 'small'),
        pg_list_cell((string) (int) $event['score'], 'small'),
        pg_list_cell($request, 'small text-break', array('style' => 'max-width:22rem;')),
        pg_list_cell($match, 'small text-break', array('style' => 'max-width:16rem;')),
    ));
}

/**
 * The number of rows a condition selects.
 *
 * @param string $sql    a condition with ? placeholders
 * @param array  $params its values
 * @return int|false false when the query failed
 */
function pg_waf_log_list_count($sql, array $params)
{
    $result = db_q('SELECT COUNT(*) FROM waf_log WHERE ' . $sql, $params);

    return $result ? (int) mysqli_fetch_row($result)[0] : false;
}

/**
 * One page of the events: the row counts without and with the search box,
 * and the rows of the page window. The counts are rows, as DataTables pages
 * rows; the summary above the table counts requests (SUM(hit_count)).
 *
 * @param array $filters pg_waf_log_list_filters()'s answer
 * @param array $terms   pg_list_search_terms()'s answer
 * @param array $window  pg_list_page_window()'s answer
 * @param int   $now
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_waf_log_list_page(array $filters, array $terms, array $window, $now)
{
    $condition = pg_waf_log_list_condition($filters, $now);
    $search = pg_waf_log_list_search($terms);
    $sql = $condition['sql'] . (($search['sql'] !== '') ? ' AND ' . $search['sql'] : '');
    $params = array_merge($condition['params'], $search['params']);

    $total = pg_waf_log_list_count($condition['sql'], $condition['params']);

    if ($total === false) {
        return false;
    }

    $filtered = ($search['sql'] !== '') ? pg_waf_log_list_count($sql, $params) : $total;

    if ($filtered === false) {
        return false;
    }

    $result = db_q('SELECT id, log_timestamp, last_seen, hit_count, action, ip_address, rule_id, reference, score, method, request_url, user_agent, target, matched FROM waf_log'
        . ' WHERE ' . $sql . ' ORDER BY ' . pg_waf_log_list_sort()['order_by'] . pg_list_limit_sql($window), $params);

    if (!$result) {
        return false;
    }

    $rows = array();

    while ($event = mysqli_fetch_assoc($result)) {
        $rows[] = pg_waf_log_list_row($event, $now);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}
