<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Log list (view_log.php), shared by the screen and the panel action
 * that pages it (site_log_list_rows, includes/panel/lists.php).
 *
 * The log keeps six months of events, so a busy site printed thousands of
 * rows on one screen. The database view is now read one page at a time. The
 * view that mixes in the error_log files (administrators, "Show Error Logs")
 * merges and sorts file entries in PHP, so it stays printed whole on the
 * screen through the same row builder.
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
 * The date range the action is asked for: two whole numbers of seconds, the
 * start not after the stop. The screen sends the range it shows.
 *
 * @param mixed $start
 * @param mixed $stop
 * @return array|null array('start' => int, 'stop' => int), or null when
 *                    either is not a whole number
 */
function pg_site_log_list_range($start, $stop)
{
    foreach (array($start, $stop) as $value) {
        if (!is_scalar($value) || !preg_match('/^[0-9]{1,11}$/', (string) $value)) {
            return null;
        }
    }

    return array('start' => (int) $start, 'stop' => max((int) $start, (int) $stop));
}

/**
 * The list's sort columns, the kept sort, the key each sorts by and the
 * ORDER BY. Rows that tie on every level keep the order they were logged
 * in, in the first level's direction.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels',
 *               'sort_keys', 'tie_direction', 'order_by')
 */
function pg_site_log_list_sort()
{
    $sort_columns = array(
        'time'        => lang('Time'),
        'user'        => lang('User'),
        'description' => lang('Description'),
        'ip_address'  => lang('IP Address'),
    );
    $sort_default = array('sort' => 'time', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // action's JSON body never fills $_REQUEST.
    $sort_levels = pg_list_state('view_log', $sort_columns, $sort_default)['sorts'];

    // Only codes pg_list_state() let through reach pg_list_order_by(), and it
    // writes nothing but these.
    $sort_keys = array(
        'time'        => 'log_timestamp',
        'user'        => 'log_user',
        'description' => 'log_description',
        'ip_address'  => 'log_ip',
    );
    $tie_direction = ($sort_levels[0]['order'] === 'desc') ? 'desc' : 'asc';

    return array(
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $sort_levels,
        'sort_keys' => $sort_keys,
        'tie_direction' => $tie_direction,
        'order_by' => pg_list_order_by($sort_levels, $sort_keys) . ', log_id ' . $tie_direction,
    );
}

/**
 * The description cell. error_log entries are often multi-line stack traces
 * that push every other row off the screen, so a long message is clipped to
 * a few lines and can be expanded; an error message gets a copy button.
 *
 * @param string $text     the message, not escaped
 * @param bool   $is_error whether it comes from an error_log file
 * @param string $key      unique on the screen, for the element id
 * @return string
 */
function pg_site_log_list_description($text, $is_error, $key)
{
    $text = (string) $text;

    // convert_text_to_html() already runs nl2br() internally, so it must not be wrapped again.
    $message_html = convert_text_to_html($text);
    $is_long = ((substr_count($text, "\n") > 2) || (strlen($text) > 300));
    $message_id = 'pg_log_msg_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $key);

    $tools = '';

    if ($is_long) {
        $tools .= '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none pg-log-toggle"'
            . ' data-target="' . $message_id . '"'
            . ' data-more="' . h(lang('Show More')) . '"'
            . ' data-less="' . h(lang('Show Less')) . '">'
            . '<i class="bi bi-chevron-down me-1" aria-hidden="true"></i><span class="pg-log-toggle-label">' . lang('Show More') . '</span></button>';
    }

    if ($is_error) {
        $tools .= '<button type="button" class="btn btn-link btn-sm p-0 ms-5 text-decoration-none pg-log-copy"'
            . ' data-target="' . $message_id . '"'
            . '>'
            . '<i class="bi bi-clipboard me-1" aria-hidden="true"></i><span class="pg-log-copy-label">' . lang('Copy') . '</span></button>'
            . '<span class="visually-hidden" role="status" aria-live="polite" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></span>';
    }

    $classes = 'pg-log-msg text-break' . ($is_error ? ' pg-log-msg-code' : '') . ($is_long ? ' pg-log-clip' : '');
    $output = '<div id="' . $message_id . '" class="' . $classes . '">' . $message_html . '</div>';

    if ($tools !== '') {
        $output .= '<div class="pg-log-tools">' . $tools . '</div>';
    }

    return $output;
}

/**
 * One row of the list: a site log row from the database, or an error_log
 * entry (log_type 'Error Log', log_location already escaped).
 *
 * @param array $entry log_id, log_description, log_ip, log_user,
 *                     log_timestamp[, log_type, log_location]
 * @return array array('id', 'class', 'cells')
 */
function pg_site_log_list_row($entry)
{
    $user = ((string) ($entry['log_user'] ?? '') === '') ? 'UNKNOWN' : (string) $entry['log_user'];
    $ip = (string) ($entry['log_ip'] ?? '');
    $is_error = (($entry['log_type'] ?? 'Site Log') !== 'Site Log');
    $location = $entry['log_location'] ?? h(lang('Database'));
    $class = 'unselectable';

    // Hidden tokens let the type filter buttons search this column without
    // depending on the translated badge text.
    if (!$is_error) {
        $type = '<span title="' . $location . '" class="badge theme-success me-1">' . lang('Site Log') . '</span><span class="d-none">pglogsite</span>';
    } else {
        $badge = 'bg-danger';
        $token = 'pglogerror';
        $class .= ' pg-log-row-error';

        if (preg_match('/\bwarning\b/i', (string) $entry['log_description'])) {
            // bg-warning needs dark text, the default white is unreadable on yellow.
            $badge = 'bg-warning fg-black';
            $token = 'pglogwarn';
            $class = 'unselectable pg-log-row-warn';
        }

        $type = '<span title="' . $location . '" class="badge ' . $badge . ' me-1">' . h(lang('Error Log')) . '</span><span class="d-none">' . $token . '</span>';
    }

    return array('id' => (string) $entry['log_id'], 'class' => $class, 'cells' => array(
        pg_list_cell(get_relative_time(array('timestamp' => $entry['log_timestamp'])), ''),
        pg_list_cell($type, ''),
        pg_list_cell(h($user), ''),
        pg_list_cell(pg_site_log_list_description($entry['log_description'], $is_error, $entry['log_id']), ''),
        pg_list_cell(($ip !== '') ? h($ip) : '<span class="fg-secondary">&mdash;</span>', ''),
    ));
}

/**
 * The number of site log rows in a range.
 *
 * @param array $range pg_site_log_list_range()'s answer
 * @return int|false false when the query failed
 */
function pg_site_log_list_count($range)
{
    $result = db_q('SELECT COUNT(*) FROM log WHERE (log_timestamp >= ?) AND (log_timestamp <= ?)', array((string) $range['start'], (string) $range['stop']));

    return $result ? (int) mysqli_fetch_row($result)[0] : false;
}

/**
 * One page of the database view: the counts without and with the search,
 * and the rows of the page window. The search finds every word in the user,
 * the description or the IP address.
 *
 * @param array $range  pg_site_log_list_range()'s answer
 * @param array $terms  pg_list_search_terms()'s answer
 * @param array $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_site_log_list_page($range, $terms, $window)
{
    $where = 'WHERE (log_timestamp >= ?) AND (log_timestamp <= ?)';
    $params = array((string) $range['start'], (string) $range['stop']);
    $search = pg_list_search_where($terms, array('log_user', 'log_description', 'log_ip'));
    $search_sql = ($search['sql'] !== '') ? ' AND ' . $search['sql'] : '';
    $search_params = array_merge($params, $search['params']);

    $total = pg_site_log_list_count($range);

    if ($total === false) {
        return false;
    }

    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(*) FROM log ' . $where . $search_sql, $search_params);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    $result = db_q('SELECT log_id, log_description, log_ip, log_user, log_timestamp FROM log ' . $where . $search_sql
        . ' ORDER BY ' . pg_site_log_list_sort()['order_by'] . pg_list_limit_sql($window), $search_params);

    if (!$result) {
        return false;
    }

    $rows = array();

    while ($entry = mysqli_fetch_assoc($result)) {
        $rows[] = pg_site_log_list_row($entry);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}
