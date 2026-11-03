<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail list (view_audit_log.php) on the list table driver
 * (includes/fn/list_table.php), shared by the screen, the driver's rows
 * action (list_table_rows) and the action that opens a line's changes
 * (audit_log_changes, includes/panel/lists.php).
 *
 * The screen draws its filters and the first lines; the next ones come one
 * batch at a time, sorted by the kept sort and searched on the server. A
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
 * an action or a record type is shown under, or the name a settings line's
 * category is shown under (the line stores the category's key).
 *
 * @param array      $terms pg_list_search_terms()'s answer
 * @param array|null $names settings category key => shown name; null reads
 *                          pg_audit_view_settings_names()
 * @return array array('sql', 'params') as pg_list_search_where() answers
 */
function pg_audit_list_search(array $terms, $names = null)
{
    // Only a word can match a settings category; without one the names are
    // not read.
    return pg_list_search_where($terms, pg_audit_list_search_columns(), pg_audit_list_search_labels($terms ? $names : array()));
}

/**
 * The shown names the search box matches, as pg_list_search_where() takes
 * them: the source, action and record type names, and a settings line's
 * category name (the line stores the category's key; on any other line the
 * expression is NULL and never matches).
 *
 * @param array|null $names settings category key => shown name; null reads
 *                          pg_audit_view_settings_names(), an empty array
 *                          leaves the categories out
 * @return array
 */
function pg_audit_list_search_labels($names = null)
{
    $labels = array();

    foreach (array('source' => 'source', 'action' => 'action', 'type' => 'entity_type') as $kind => $column) {
        $labels[] = array('expr' => $column, 'labels' => pg_audit_view_labels($kind));
    }

    $names = is_array($names) ? $names : pg_audit_view_settings_names();

    if ($names) {
        $labels[] = array('expr' => "(CASE WHEN entity_type = 'settings' THEN label END)", 'labels' => $names);
    }

    return $labels;
}

/**
 * The text columns the search box looks in, as pg_list_search_where()
 * takes them.
 *
 * @return array
 */
function pg_audit_list_search_columns()
{
    return array(
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
    );
}

/**
 * A stored value as it is shown in the changes table.
 *
 * @param array  $value pg_audit_view_value()'s answer
 * @param string $name  the box's accessible name when the value scrolls
 * @return string
 */
function pg_audit_list_value_html(array $value, $name = '')
{
    switch ($value['kind']) {
        case 'none':
            return '<span class="fg-secondary">—</span>';
        case 'secret':
            return '<span class="badge theme-secondary">' . h(lang('Hidden')) . '</span>';
        case 'cut':
            return '<span class="text-break">' . h($value['text']) . '…</span> <span class="small fg-secondary" title="SHA-1 ' . h($value['sha1']) . '">(' . h(lang(array('string' => '{var:1} bytes', 'vars' => array(pg_format_number($value['length'], 0))))) . ')</span>';
        case 'json':
            // Capped and scrolling, so Tab must reach it (Safari skips a
            // scroller) and a screen reader must hear what it holds.
            return '<pre class="small mb-0" tabindex="0" role="region" aria-label="' . h($name) . '" style="max-height:16rem;overflow:auto;white-space:pre-wrap;word-break:break-word">' . h($value['text']) . '</pre>';
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
            . '<td>' . ($change['names_only'] ? $too_large : pg_audit_list_value_html($change['before'], lang(array('string' => '{var:1}, before', 'vars' => $change['field'])))) . '</td>'
            . '<td>' . ($change['names_only'] ? $too_large : pg_audit_list_value_html($change['after'], lang(array('string' => '{var:1}, after', 'vars' => $change['field'])))) . '</td>'
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
 * @param int|null $role the viewer's role, USER_ROLE when null: the "Open"
 *                     icon is drawn only when the record's screen admits it
 * @return array array('id', 'class', 'cells' => column key => cell)
 */
function pg_audit_list_row(array $line, array $live, array $query, $role = null)
{
    if ($role === null) {
        $role = defined('USER_ROLE') ? USER_ROLE : 3;
    }

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
    $name = ((string) $line['label'] !== '') ? pg_audit_view_record_name($type, $line['label']) : (($id > 0) ? '#' . $id : pg_audit_view_label('type', $type));
    $record = '<div class="small fg-secondary">' . h(pg_audit_view_label('type', $type)) . (($id > 0) ? ' #' . $id : '') . '</div>';
    $record .= '<a class="pg-audit-record-name" href="' . h(pg_audit_list_link($query, array('type' => $type, 'id' => $id, 'q' => ''))) . '" title="' . h(lang('Show the history of this record')) . '">' . h($name) . '</a>';

    $open = pg_audit_view_record_url($type, $id);

    // A deleted record's edit screen answers "not found", one that refuses
    // the viewer "Access denied".
    if (isset(pg_audit_view_record_tables()[$type])
        && (empty($live[$type][$id]) || !pg_audit_view_can_open($type, $live[$type][$id], $role))) {
        $open = '';
    }

    if ($open !== '') {
        $record .= ' <a href="' . h($open) . '" class="pg-link-secondary d-inline-flex align-items-center justify-content-center p-1" style="min-width:1.5rem;min-height:1.5rem" title="' . h(lang('Open it')) . '" aria-label="' . h(lang('Open it')) . '"><i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i></a>';
    }

    $fields = count(pg_audit_view_changes($line['before_json'], $line['after_json']));
    $changes = ($fields > 0)
        ? '<details class="pg-audit-changes" data-pg-audit-line="' . (int) $line['id'] . '"><summary class="small">' . h(lang(array('string' => '{var:1} fields', 'vars' => array($fields)))) . '</summary><div class="pg-audit-changes-body"></div></details>'
        : '';

    $request = ((string) $line['request_id'] !== '')
        ? '<div><a class="small pg-link-secondary" href="' . h(pg_audit_list_link($query, array('request' => (string) $line['request_id']))) . '" title="' . h(lang('Show every line of this request')) . '"><code>' . h($line['request_id']) . '</code></a></div>'
        : '';

    return array('id' => (string) (int) $line['id'], 'class' => 'unselectable', 'cells' => array(
        'date' => pg_list_cell(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $line['created_at']), 'date and time'), 'text-nowrap'),
        'who' => pg_list_cell($who, ''),
        'action' => pg_list_cell('<a href="' . h(pg_audit_list_link($query, array('action' => (string) $line['action']))) . '" class="pg-link-body-emphasis">' . h(pg_audit_view_label('action', $line['action'])) . '</a>', 'text-nowrap'),
        'record' => pg_list_cell('<div class="pg-audit-record">' . $record . '</div>', ''),
        'changes' => pg_list_cell($changes, ''),
        'ip' => pg_list_cell(h($line['ip']) . $request, 'small text-nowrap'),
    ));
}

/**
 * The list's columns. The keys are the sort codes pg_audit_list_sort()
 * keeps, so the sort menu and the headings name the same sort.
 *
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_audit_list_columns()
{
    return array(
        'date' => array('heading' => lang('Date'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'created_at'),
        'who' => array('heading' => lang('Who'), 'width' => '12rem', 'sort' => 'username'),
        'action' => array('heading' => lang('Action'), 'width' => '8rem', 'sort' => 'action'),
        'record' => array('heading' => lang('Record'), 'width' => '16rem', 'sort' => 'label'),
        'changes' => array('heading' => lang('Changes'), 'width' => '16rem'),
        'ip' => array('heading' => lang('IP Address'), 'width' => '10rem', 'sort' => 'ip'),
    );
}

/**
 * The trail as the list table driver reads it: the lines the screen's
 * filters select (its address, checked again), searched by
 * pg_audit_list_search()'s rule. The screen's gate is the source's: an
 * account above Manager, or a database without the trail, gets null.
 *
 * @param array $user   the signed-in user
 * @param array $params array('filters' => the screen's filters)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_audit_list_source($user, $params)
{
    if (!isset($user['role']) || !is_numeric($user['role']) || ((int) $user['role'] > 2) || !pg_audit_ready()) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $filters = pg_audit_view_filters((is_array($params) && isset($params['filters']) && is_array($params['filters'])) ? $params['filters'] : array());
    $condition = pg_audit_view_condition($filters);
    $query = pg_audit_view_query($filters);

    return pg_list_table_source(array(
        'list' => 'view_audit_log',
        'columns' => pg_audit_list_columns(),
        'from' => 'audit_log',
        'where' => $condition['sql'],
        'params' => $condition['params'],
        'id' => 'id',
        'select' => 'id, created_at, request_id, source, user_id, username, ip, action, entity_type, entity_id, label, before_json, after_json',
        'search' => array('columns' => pg_audit_list_search_columns(), 'labels' => pg_audit_list_search_labels()),
        'levels' => pg_audit_list_sort()['sort_levels'],
        'rows' => function ($lines) use ($query) {
            // The records of this batch that still exist: only those get an "Open" link.
            $live = pg_audit_view_live_records($lines);
            $rows = array();

            foreach ($lines as $line) {
                $rows[] = pg_audit_list_row($line, $live, $query);
            }

            return $rows;
        },
        'params_out' => array('filters' => (object) $query),
    ));
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
