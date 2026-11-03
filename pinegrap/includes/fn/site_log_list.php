<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Log list (view_log.php) on the list table driver
 * (includes/fn/list_table.php), shared by the screen and the driver's rows
 * action (list_table_rows, includes/panel/lists.php).
 *
 * The log keeps six months of events, so a busy site printed thousands of
 * rows on one screen. The database view is read one batch at a time. The
 * view that mixes in the error_log files (administrators, "Show Error Logs")
 * merges and sorts file entries in PHP, so it is printed whole and the
 * browser sorts, searches and filters it by type.
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
 * The list's columns. The keys are the sort codes pg_site_log_list_sort()
 * keeps, so the sort menu and the headings name the same sort.
 *
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_site_log_list_columns()
{
    return array(
        'time' => array('heading' => lang('Time'), 'width' => '10rem', 'kind' => 'date', 'sort' => 'log_timestamp'),
        'type' => array('heading' => lang('Type'), 'width' => '8rem', 'kind' => 'badge'),
        'user' => array('heading' => lang('User'), 'width' => '12rem', 'sort' => 'log_user'),
        'description' => array('heading' => lang('Description'), 'sort' => 'log_description'),
        'ip_address' => array('heading' => lang('IP Address'), 'width' => '10rem', 'sort' => 'log_ip'),
    );
}

/**
 * The type of an entry the type filter knows it by: a site log, an error
 * or a warning of an error_log file.
 *
 * @param array $entry log_type, log_description
 * @return string site, error or warn
 */
function pg_site_log_list_type($entry)
{
    if (($entry['log_type'] ?? 'Site Log') === 'Site Log') {
        return 'site';
    }

    return preg_match('/\bwarning\b/i', (string) ($entry['log_description'] ?? '')) ? 'warn' : 'error';
}

/**
 * One row of the list: a site log row from the database, or an error_log
 * entry (log_type 'Error Log', log_location already escaped). The type cell
 * carries the type for the driver's type filter and the time cell the Unix
 * time, so a list printed whole sorts by it in the browser.
 *
 * @param array $entry log_id, log_description, log_ip, log_user,
 *                     log_timestamp[, log_type, log_location]
 * @return array array('id', 'class', 'cells' => column key => cell)
 */
function pg_site_log_list_row($entry)
{
    $ip = (string) ($entry['log_ip'] ?? '');
    // A row without a user is named like the dashboard's Activity card names it.
    $user = pg_log_actor($entry['log_user'] ?? '', $ip);
    $type = pg_site_log_list_type($entry);
    $location = $entry['log_location'] ?? h(lang('Database'));
    $class = 'unselectable';

    if ($type === 'site') {
        $badge = '<span title="' . $location . '" class="badge theme-success me-1">' . lang('Site Log') . '</span>';
    } else {
        // The warning badge needs dark text: white is unreadable on yellow.
        $class .= ($type === 'warn') ? ' pg-log-row-warn' : ' pg-log-row-error';
        $badge = '<span title="' . $location . '" class="badge ' . (($type === 'warn') ? 'theme-warning fg-black' : 'theme-danger') . ' me-1">' . h(lang('Error Log')) . '</span>';
    }

    return array('id' => (string) $entry['log_id'], 'class' => $class, 'cells' => array(
        'time' => pg_list_cell(get_relative_time(array('timestamp' => $entry['log_timestamp'])), '', array('data-pg-sort-value' => (string) (int) $entry['log_timestamp'])),
        'type' => pg_list_cell($badge, '', array('data-pg-filter' => $type)),
        'user' => pg_list_cell(h($user), ''),
        'description' => pg_list_cell(pg_site_log_list_description($entry['log_description'], $type !== 'site', $entry['log_id']), ''),
        'ip_address' => pg_list_cell(($ip !== '') ? h($ip) : '<span class="fg-secondary">&mdash;</span>', ''),
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
 * The database view as the list table driver reads it: the log rows of the
 * range the screen shows, searched by every word in the user, the
 * description or the IP address. The screen's gate is the source's: an
 * account above Manager gets null.
 *
 * @param array $user   the signed-in user
 * @param array $params start_time, stop_time: the range in Unix seconds
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list or the range is not whole numbers
 */
function pg_site_log_list_source($user, $params)
{
    if (!isset($user['role']) || !is_numeric($user['role']) || ((int) $user['role'] > 2)) {
        return null;
    }

    $params = is_array($params) ? $params : array();
    $range = pg_site_log_list_range($params['start_time'] ?? null, $params['stop_time'] ?? null);

    if ($range === null) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    return pg_list_table_source(array(
        'list' => 'view_log',
        'columns' => pg_site_log_list_columns(),
        'from' => 'log',
        'where' => '(log_timestamp >= ?) AND (log_timestamp <= ?)',
        'params' => array((string) $range['start'], (string) $range['stop']),
        'id' => 'log_id',
        'select' => 'log_id, log_description, log_ip, log_user, log_timestamp',
        'search' => array('columns' => array('log_user', 'log_description', 'log_ip')),
        'levels' => pg_site_log_list_sort()['sort_levels'],
        'rows' => function ($records) {
            return array_map('pg_site_log_list_row', $records);
        },
        'params_out' => array('start_time' => (string) $range['start'], 'stop_time' => (string) $range['stop']),
    ));
}
