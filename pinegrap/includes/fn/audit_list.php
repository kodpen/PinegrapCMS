<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail list (view_audit_log.php), shared by the screen and the
 * panel actions that page it (audit_log_list_rows) and open a line's changes
 * (audit_log_changes, includes/panel/lists.php).
 *
 * The screen draws its filters and the headings; the lines come one page at
 * a time, sorted by the headings' kept sort and searched on the server. A
 * line's changed fields can be dozens of indented JSON values, so a row
 * carries only their number and the table is read when it is opened.
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
 * A link to the screen with the filters of $query changed by $change; a
 * value of '', 0 or null drops that filter. Dates go into the link as the
 * date picker writes them.
 *
 * @param array $query   pg_audit_view_query()'s answer
 * @param array $change  filter => value
 * @return string
 */
function pg_audit_list_link(array $query, array $change = array())
{
    $query = pg_audit_list_link_query($query, $change);

    return 'view_audit_log.php' . ($query ? '?' . http_build_query($query) : '');
}

/**
 * The address query of pg_audit_list_link().
 *
 * @param array $query
 * @param array $change
 * @return array
 */
function pg_audit_list_link_query(array $query, array $change = array())
{
    $query = array_merge($query, $change);

    foreach (array('from', 'to') as $key) {
        if (isset($query[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $query[$key])) {
            $query[$key] = prepare_form_data_for_output($query[$key], 'date', false);
        }
    }

    return array_filter($query, function ($value) {
        return ($value !== '') && ($value !== 0) && ($value !== null);
    });
}

/**
 * The list's sort columns, the kept sort, the key each sorts by and the
 * ORDER BY. Lines that tie on every level keep the order they were written
 * in, in the first level's direction.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels',
 *               'sort_keys', 'order_by')
 */
function pg_audit_list_sort()
{
    $sort_columns = array(
        'date'   => lang('Date'),
        'who'    => lang('Who'),
        'action' => lang('Action'),
        'record' => lang('Record'),
        'ip'     => lang('IP Address'),
    );
    $sort_default = array('sort' => 'date', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // action's JSON body never fills $_REQUEST.
    $sort_levels = pg_list_state('view_audit_log', $sort_columns, $sort_default)['sorts'];

    // Only codes pg_list_state() let through reach pg_list_order_by(), and it
    // writes nothing but these.
    $sort_keys = array(
        'date'   => 'created_at',
        'who'    => 'username',
        'action' => 'action',
        'record' => 'label',
        'ip'     => 'ip',
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
 * The search box's condition: every word in the user's name, the record's
 * name or id, the IP address or the request id, or in the name a source,
 * an action or a record type is shown under.
 *
 * @param array $terms pg_list_search_terms()'s answer
 * @return array array('sql', 'params') as pg_list_search_where() answers
 */
function pg_audit_list_search(array $terms)
{
    $labels = array();

    foreach (array('source' => 'source', 'action' => 'action', 'type' => 'entity_type') as $kind => $column) {
        $labels[] = array('expr' => $column, 'labels' => pg_audit_view_labels($kind));
    }

    return pg_list_search_where($terms, array(
        'username',
        'label',
        'ip',
        'request_id',
        // A word written later than the labels is shown as it is stored.
        'action',
        'entity_type',
        // The record's "#id"; a word that is not a number is never an id.
        array('expr' => 'CAST(entity_id AS CHAR)', 'term' => function ($term) {
            $term = ltrim($term, '#');

            return ctype_digit($term) ? $term : '';
        }),
    ), $labels);
}

/**
 * A stored value as it is shown in the changes table.
 *
 * @param array $value pg_audit_view_value()'s answer
 * @return string
 */
function pg_audit_list_value_html(array $value)
{
    switch ($value['kind']) {
        case 'none':
            return '<span class="fg-secondary">—</span>';
        case 'secret':
            return '<span class="badge theme-secondary">' . h(lang('Hidden')) . '</span>';
        case 'cut':
            return '<span class="text-break">' . h($value['text']) . '…</span> <span class="small fg-secondary" title="SHA-1 ' . h($value['sha1']) . '">(' . h(lang(array('string' => '{var:1} bytes', 'vars' => array(pg_format_number($value['length'], 0))))) . ')</span>';
        case 'json':
            return '<pre class="small mb-0" style="max-height:16rem;overflow:auto;white-space:pre-wrap;word-break:break-word">' . h($value['text']) . '</pre>';
    }

    return ($value['text'] === '') ? '<span class="fg-secondary fst-italic">' . h(lang('empty')) . '</span>' : '<span class="text-break" style="white-space:pre-wrap">' . h($value['text']) . '</span>';
}

/**
 * The table of a line's changed fields, before and after.
 *
 * @param array $changes pg_audit_view_changes()'s answer
 * @return string
 */
function pg_audit_list_changes_html(array $changes)
{
    $rows = '';

    foreach ($changes as $change) {
        $too_large = '<span class="fg-secondary">' . h(lang('Too large to keep')) . '</span>';

        $rows .= '<tr>'
            . '<td class="text-nowrap"><code>' . h($change['field']) . '</code></td>'
            . '<td>' . ($change['names_only'] ? $too_large : pg_audit_list_value_html($change['before'])) . '</td>'
            . '<td>' . ($change['names_only'] ? $too_large : pg_audit_list_value_html($change['after'])) . '</td>'
            . '</tr>';
    }

    return '<table class="table table-sm table-bordered small mt-3 mb-0" style="table-layout:fixed">'
        . '<thead><tr><th style="width:30%">' . h(lang('Field')) . '</th><th>' . h(lang('Before')) . '</th><th>' . h(lang('After')) . '</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>';
}

/**
 * One line of the list. Its links narrow the list from the filters it was
 * drawn with: the user, the action, the record's whole history, the
 * request. The changed fields are folded and read when opened
 * (audit_log_changes).
 *
 * @param array $line  an audit_log row: id, created_at, request_id, source,
 *                     user_id, username, ip, action, entity_type, entity_id,
 *                     label, before_json, after_json
 * @param array $live  pg_audit_view_live_records() of the page
 * @param array $query pg_audit_view_query() of the filters
 * @return array array('class', 'cells')
 */
function pg_audit_list_row(array $line, array $live, array $query)
{
    $type = (string) $line['entity_type'];
    $id = (int) $line['entity_id'];

    // Who: the operator, the application (its owner's id) or the job.
    $who = '<span class="badge badge-subtle theme-secondary border me-1">' . h(pg_audit_view_label('source', $line['source'])) . '</span>';

    if ((string) $line['username'] !== '') {
        $who .= '<a href="' . h(pg_audit_list_link($query, array('user' => (string) $line['username']))) . '">' . h($line['username']) . '</a>';
    } elseif ((int) $line['user_id'] > 0) {
        $who .= '<a href="' . h(pg_audit_list_link($query, array('user' => (string) (int) $line['user_id']))) . '">#' . (int) $line['user_id'] . '</a>';
    }

    // What: the record's name links to its whole history; the icon opens it.
    $name = ((string) $line['label'] !== '') ? (string) $line['label'] : (($id > 0) ? '#' . $id : pg_audit_view_label('type', $type));
    $record = '<div class="small fg-secondary">' . h(pg_audit_view_label('type', $type)) . (($id > 0) ? ' #' . $id : '') . '</div>';
    $record .= '<a class="text-break" href="' . h(pg_audit_list_link($query, array('type' => $type, 'id' => $id, 'q' => ''))) . '" title="' . h(lang('Show the history of this record')) . '">' . h($name) . '</a>';

    $open = pg_audit_view_record_url($type, $id);

    // A deleted record's edit screen answers "not found".
    if (isset(pg_audit_view_record_tables()[$type]) && empty($live[$type][$id])) {
        $open = '';
    }

    if ($open !== '') {
        $record .= ' <a href="' . h($open) . '" class="pg-link-secondary" title="' . h(lang('Open')) . '" aria-label="' . h(lang('Open')) . '"><i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i></a>';
    }

    $fields = count(pg_audit_view_changes($line['before_json'], $line['after_json']));
    $changes = ($fields > 0)
        ? '<details class="pg-audit-changes" data-pg-audit-line="' . (int) $line['id'] . '"><summary class="small">' . h(lang(array('string' => '{var:1} fields', 'vars' => array($fields)))) . '</summary><div class="pg-audit-changes-body"></div></details>'
        : '';

    $request = ((string) $line['request_id'] !== '')
        ? '<div><a class="small pg-link-secondary" href="' . h(pg_audit_list_link($query, array('request' => (string) $line['request_id']))) . '" title="' . h(lang('Show every line of this request')) . '"><code>' . h($line['request_id']) . '</code></a></div>'
        : '';

    return array('class' => 'unselectable', 'cells' => array(
        pg_list_cell(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $line['created_at']), 'date and time'), 'text-nowrap'),
        pg_list_cell($who, ''),
        pg_list_cell('<a href="' . h(pg_audit_list_link($query, array('action' => (string) $line['action']))) . '" class="pg-link-body-emphasis">' . h(pg_audit_view_label('action', $line['action'])) . '</a>', 'text-nowrap'),
        pg_list_cell($record, '', array('style' => 'min-width:12rem')),
        pg_list_cell($changes, '', array('style' => 'min-width:16rem')),
        pg_list_cell(h($line['ip']) . $request, 'small text-nowrap'),
    ));
}

/**
 * The number of lines a condition selects.
 *
 * @param string $sql    a condition with ? placeholders
 * @param array  $params its values
 * @return int|false false when the query failed
 */
function pg_audit_list_count($sql, array $params)
{
    $result = db_q('SELECT COUNT(*) FROM audit_log WHERE ' . $sql, $params);

    return $result ? (int) mysqli_fetch_row($result)[0] : false;
}

/**
 * One page of the list: the counts without and with the search, and the
 * rows of the page window.
 *
 * @param array $filters pg_audit_view_filters()'s answer
 * @param array $terms   pg_list_search_terms()'s answer
 * @param array $window  pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_audit_list_page(array $filters, array $terms, array $window)
{
    $condition = pg_audit_view_condition($filters);
    $search = pg_audit_list_search($terms);
    $sql = $condition['sql'] . (($search['sql'] !== '') ? ' AND ' . $search['sql'] : '');
    $params = array_merge($condition['params'], $search['params']);

    $total = pg_audit_list_count($condition['sql'], $condition['params']);

    if ($total === false) {
        return false;
    }

    $filtered = ($search['sql'] !== '') ? pg_audit_list_count($sql, $params) : $total;

    if ($filtered === false) {
        return false;
    }

    $result = db_q('SELECT id, created_at, request_id, source, user_id, username, ip, action, entity_type, entity_id, label, before_json, after_json FROM audit_log'
        . ' WHERE ' . $sql . ' ORDER BY ' . pg_audit_list_sort()['order_by'] . pg_list_limit_sql($window), $params);

    if (!$result) {
        return false;
    }

    $lines = array();

    while ($line = mysqli_fetch_assoc($result)) {
        $lines[] = $line;
    }

    // The records of this page that still exist: only those get an "Open" link.
    $live = pg_audit_view_live_records($lines);
    $query = pg_audit_view_query($filters);
    $rows = array();

    foreach ($lines as $line) {
        $rows[] = pg_audit_list_row($line, $live, $query);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}

/**
 * The changes table of one line, or null when there is no such line.
 *
 * @param int $id
 * @return string|null|false false when the query failed
 */
function pg_audit_list_line_changes($id)
{
    $result = db_q('SELECT before_json, after_json FROM audit_log WHERE id = ?', array((int) $id));

    if (!$result) {
        return false;
    }

    $line = mysqli_fetch_assoc($result);

    return $line ? pg_audit_list_changes_html(pg_audit_view_changes($line['before_json'], $line['after_json'])) : null;
}
