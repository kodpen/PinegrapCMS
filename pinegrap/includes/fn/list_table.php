<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's list table driver, server side.
 *
 * A list screen that moves to the driver describes its list once as a
 * "source": its columns (key, heading, width, kind, sortable, editable),
 * the FROM and WHERE of its own query with the user's rights already in
 * it, the record id, the search columns and a function that turns records
 * into rows of cells. From that source this module prints the first rows
 * with the page (pg_list_table_html()), answers the rows action that hands
 * the browser the next rows (pg_list_table_page(), pg_list_table_answer())
 * and resolves a selection - a list of ids, or the whole list by its
 * condition with exceptions - into the ids a bulk action acts on
 * (pg_list_table_selected_ids()).
 *
 * Rows come in batches by keyset, not by offset: the cursor holds the sort
 * values and the id of the last row handed out, and the next batch starts
 * after it. A row added or deleted meanwhile neither repeats nor skips one.
 * The count of the list runs once per condition, with the first batch.
 *
 * Row cells are pg_list_cell() cells (includes/fn/list_paging.php), keyed
 * by column key, so the browser can hide and reorder columns.
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
 * The lists the rows action serves: list name => array('file' => the module
 * under PG_FUNCTIONS_DIR that holds the source function, 'source' => the
 * function's name). The source function takes ($user, $params) and answers
 * the list's source (pg_list_table_source()), or null when the user may not
 * open the list. A list joins this table in the step that moves its screen.
 *
 * @return array
 */
function pg_list_table_definitions()
{
    return array(
        'view_pages' => array('file' => 'includes/fn/page_list.php', 'source' => 'pg_page_list_source'),
        'view_users' => array('file' => 'includes/fn/user_list.php', 'source' => 'pg_user_list_source'),
        'view_contacts' => array('file' => 'includes/fn/contact_list.php', 'source' => 'pg_contact_list_source'),
        'view_submitted_forms' => array('file' => 'includes/fn/submitted_form_list.php', 'source' => 'pg_submitted_form_list_source'),
        'view_products' => array('file' => 'includes/fn/product_list.php', 'source' => 'pg_product_list_source'),
        'view_log' => array('file' => 'includes/fn/site_log_list.php', 'source' => 'pg_site_log_list_source'),
        'view_audit_log' => array('file' => 'includes/fn/audit_list.php', 'source' => 'pg_audit_list_source'),
        'view_waf_log' => array('file' => 'includes/fn/waf_log_list.php', 'source' => 'pg_waf_log_list_source'),
        'view_stock_moves' => array('file' => 'includes/fn/stock_moves.php', 'source' => 'pg_stock_move_list_source'),
        'view_commissions' => array('file' => 'includes/fn/commission_list.php', 'source' => 'pg_commission_list_source'),
        'view_recurring_commission_profiles' => array('file' => 'includes/fn/commission_list.php', 'source' => 'pg_commission_profile_list_source'),
        'mail_queue' => array('file' => 'includes/fn/mail_queue_list.php', 'source' => 'pg_mail_queue_list_source'),
        'mail_queue_sms' => array('file' => 'includes/fn/mail_queue_list.php', 'source' => 'pg_sms_queue_list_source'),
    );
}

/**
 * A list's entry in pg_list_table_definitions(), or null for a name that
 * is not there.
 *
 * @param mixed $name
 * @return array|null array('file', 'source')
 */
function pg_list_table_definition($name)
{
    $definitions = pg_list_table_definitions();

    if (!is_string($name) || !isset($definitions[$name])) {
        return null;
    }

    return $definitions[$name];
}

/**
 * The rows one batch holds.
 *
 * @return int
 */
function pg_list_table_batch()
{
    return 100;
}

/**
 * The kinds of column the driver draws, each with the side its content
 * leans to.
 *
 * @return array kind => 'start'|'end'|'center'
 */
function pg_list_table_kinds()
{
    return array(
        'text' => 'start',
        'link' => 'start',
        'number' => 'end',
        'money' => 'end',
        'date' => 'start',
        'badge' => 'start',
        'image' => 'center',
        'check' => 'center',
        'actions' => 'end',
    );
}

/**
 * The kinds of input a quick-edit cell offers.
 *
 * @return array
 */
function pg_list_table_edit_kinds()
{
    return array('text', 'textarea', 'number', 'money', 'select', 'multiselect', 'check', 'date');
}

/**
 * One column of a list, its settings checked and filled in.
 *
 * @param string $key  the column's key: lower-case letters, digits and '_',
 *                     starting with a letter
 * @param array  $spec 'heading' (shown text, already translated), 'width'
 *                     (a CSS length: px, rem, em, ch or %), 'kind'
 *                     (pg_list_table_kinds(), default text), 'sort' (the SQL
 *                     expression the column sorts by; written into the query
 *                     as it is, so never from a request, and usable in WHERE:
 *                     no select alias), 'sortable' (default true when 'sort'
 *                     is given), 'editable' (a pg_list_table_edit_kinds()
 *                     input, or false), 'hideable' (default true), 'hidden'
 *                     (hidden until the user shows it, default false)
 * @return array|null the column, or null when the key is not valid
 */
function pg_list_table_column($key, array $spec)
{
    $key = is_string($key) ? $key : '';

    if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
        return null;
    }

    $kinds = pg_list_table_kinds();
    $kind = (isset($spec['kind']) && is_string($spec['kind']) && isset($kinds[$spec['kind']])) ? $spec['kind'] : 'text';
    $width = (isset($spec['width']) && is_scalar($spec['width'])) ? trim((string) $spec['width']) : '';

    if (!preg_match('/^\d{1,4}(\.\d{1,2})?(px|rem|em|ch|%)$/', $width)) {
        $width = '';
    }

    $sort = (isset($spec['sort']) && is_string($spec['sort'])) ? trim($spec['sort']) : '';
    $sortable = ($sort !== '') && (!array_key_exists('sortable', $spec) || !empty($spec['sortable']));
    $editable = (isset($spec['editable']) && is_string($spec['editable']) && in_array($spec['editable'], pg_list_table_edit_kinds(), true)) ? $spec['editable'] : '';

    return array(
        'key' => $key,
        'heading' => (isset($spec['heading']) && is_scalar($spec['heading'])) ? (string) $spec['heading'] : '',
        'width' => $width,
        'kind' => $kind,
        'align' => $kinds[$kind],
        'sortable' => $sortable,
        'sort' => $sortable ? $sort : '',
        'editable' => $editable,
        'hideable' => !array_key_exists('hideable', $spec) || !empty($spec['hideable']),
        'hidden' => !empty($spec['hidden']),
    );
}

/**
 * A list's columns, in order. A column with a key that is not valid or
 * already taken is left out. The first column is the record's name: it can
 * never be hidden.
 *
 * @param array $specs key => spec, as pg_list_table_column() takes them
 * @return array key => column
 */
function pg_list_table_columns(array $specs)
{
    $columns = array();

    foreach ($specs as $key => $spec) {
        $column = pg_list_table_column($key, is_array($spec) ? $spec : array());

        if (($column === null) || isset($columns[$column['key']])) {
            continue;
        }

        if (!$columns) {
            $column['hideable'] = false;
            $column['hidden'] = false;
        }

        $columns[$column['key']] = $column;
    }

    return $columns;
}

/**
 * The columns as the browser gets them: without the SQL they sort by.
 *
 * @param array $columns pg_list_table_columns()
 * @return array list of columns
 */
function pg_list_table_client_columns(array $columns)
{
    $client = array();

    foreach ($columns as $column) {
        unset($column['sort']);
        $client[] = $column;
    }

    return $client;
}

/**
 * The sortable columns as pg_list_state() takes them: key => heading.
 *
 * @param array $columns pg_list_table_columns()
 * @return array
 */
function pg_list_table_sort_columns(array $columns)
{
    $sort_columns = array();

    foreach ($columns as $key => $column) {
        if ($column['sortable']) {
            $sort_columns[$key] = $column['heading'];
        }
    }

    return $sort_columns;
}

/**
 * A list's source, checked and filled in.
 *
 * @param array $spec 'list' (the list's name in pg_list_table_definitions()
 *                    and its pg_list_state() key), 'columns' (specs, as
 *                    pg_list_table_columns() takes them), 'from' (the FROM
 *                    part with its joins), 'where' (the list's condition
 *                    with the user's rights in it, default '1 = 1'),
 *                    'params' (values bound to the placeholders in 'from'
 *                    and 'where', in order), 'id' (the record id's SQL),
 *                    'id_order' ('asc' or 'desc', the last sort level),
 *                    'select' (the columns the rows function reads),
 *                    'group' (true when the joins can repeat a record),
 *                    'search' (array('columns', 'labels') for
 *                    pg_list_search_where()), 'levels' (pg_list_state()'s
 *                    sorts), 'created' (SQL of the record's creation time
 *                    in Unix seconds, for a selection's "as of"; '' when the
 *                    list has none), 'rows' (callable: records => rows,
 *                    each array('id', 'class', 'cells' => key =>
 *                    pg_list_cell())), 'all' (true for a small list printed
 *                    whole: the browser sorts and searches it itself),
 *                    'params_out' (the list parameters the browser sends
 *                    back with every rows request), 'edit' (quick edit:
 *                    array('url' => the screen pg_list_edit_request()
 *                    answers on, 'columns' => its field => definition);
 *                    left out for a viewer who may not change the list)
 * @return array
 */
function pg_list_table_source(array $spec)
{
    $columns = pg_list_table_columns(isset($spec['columns']) && is_array($spec['columns']) ? $spec['columns'] : array());

    return array(
        'list' => (string) ($spec['list'] ?? ''),
        'columns' => $columns,
        'from' => (string) ($spec['from'] ?? ''),
        'where' => (isset($spec['where']) && (trim((string) $spec['where']) !== '')) ? (string) $spec['where'] : '1 = 1',
        'params' => array_values(isset($spec['params']) && is_array($spec['params']) ? $spec['params'] : array()),
        'id' => (string) ($spec['id'] ?? ''),
        'id_order' => (($spec['id_order'] ?? '') === 'asc') ? 'asc' : 'desc',
        'select' => (string) ($spec['select'] ?? ''),
        'group' => !empty($spec['group']),
        'search' => array(
            'columns' => (array) ($spec['search']['columns'] ?? array()),
            'labels' => (array) ($spec['search']['labels'] ?? array()),
        ),
        'levels' => pg_list_table_levels(isset($spec['levels']) && is_array($spec['levels']) ? $spec['levels'] : array(), $columns),
        'created' => (string) ($spec['created'] ?? ''),
        'rows' => $spec['rows'] ?? null,
        'all' => !empty($spec['all']),
        'params_out' => isset($spec['params_out']) && is_array($spec['params_out']) ? $spec['params_out'] : array(),
        'edit' => pg_list_table_edit_spec($spec['edit'] ?? null, $columns),
    );
}

/**
 * A source's quick edit, checked: the address and the definitions of the
 * fields a cell may change (pg_list_edit_table()), kept only while some
 * column of the list is editable. Null when nothing can change: the list
 * gets no quick edit button then.
 *
 * @param mixed $edit    array('url' => string, 'columns' => field => definition)
 * @param array $columns pg_list_table_columns()
 * @return array|null
 */
function pg_list_table_edit_spec($edit, array $columns)
{
    if (!is_array($edit) || !isset($edit['url']) || !is_string($edit['url']) || ($edit['url'] === '')
        || empty($edit['columns']) || !is_array($edit['columns'])) {
        return null;
    }

    foreach ($columns as $column) {
        if ($column['editable'] !== '') {
            return array('url' => $edit['url'], 'columns' => $edit['columns']);
        }
    }

    return null;
}

/**
 * Sort levels kept to the list's sortable columns, each 'asc' or 'desc'; a
 * column named twice counts once. May be empty: the id alone orders then.
 *
 * @param array $levels  list of array('sort' => key, 'order' => direction)
 * @param array $columns pg_list_table_columns()
 * @return array list of array('sort', 'order')
 */
function pg_list_table_levels(array $levels, array $columns)
{
    $clean = array();

    foreach ($levels as $level) {
        $key = (is_array($level) && isset($level['sort']) && is_scalar($level['sort'])) ? (string) $level['sort'] : '';

        if (!isset($columns[$key]) || !$columns[$key]['sortable'] || isset($clean[$key])) {
            continue;
        }

        $clean[$key] = array('sort' => $key, 'order' => (($level['order'] ?? '') === 'desc') ? 'desc' : 'asc');
    }

    return array_values($clean);
}

/**
 * A source sorted the way a rows request asks (a heading clicked in the
 * browser, no reload): the sort value as the address carries it
 * (pg_list_sort_value()). Columns that are not sortable are dropped; when
 * none is left, or no value was sent, the source keeps its levels.
 *
 * @param array $source pg_list_table_source()
 * @param mixed $value  e.g. 'name:asc,last_modified:desc'
 * @return array the source
 */
function pg_list_table_apply_sort(array $source, $value)
{
    if (!is_string($value) || ($value === '')) {
        return $source;
    }

    $levels = pg_list_table_levels(pg_list_sort_parse($value, pg_list_table_sort_columns($source['columns'])), $source['columns']);

    if ($levels) {
        $source['levels'] = $levels;
    }

    return $source;
}

/**
 * The source's sort kept in the session the way pg_list_state() keeps a
 * list's sort, so the screen comes back sorted the same way.
 *
 * @param array $source pg_list_table_source()
 * @return void
 */
function pg_list_table_keep_sort(array $source)
{
    if (($source['list'] === '') || !$source['levels']) {
        return;
    }

    $kept = $source['levels'][0];

    if (count($source['levels']) > 1) {
        $kept['then'] = array_slice($source['levels'], 1);
    }

    $_SESSION['software']['list_state'][$source['list']] = $kept;
}

/**
 * The ORDER BY list of a source: its sort levels, then the id, so no two
 * rows ever tie and a cursor names one place in the list.
 *
 * @param array $source pg_list_table_source()
 * @return string
 */
function pg_list_table_order_sql(array $source)
{
    $parts = array();

    foreach ($source['levels'] as $level) {
        $parts[] = $source['columns'][$level['sort']]['sort'] . ' ' . $level['order'];
    }

    $parts[] = $source['id'] . ' ' . $source['id_order'];

    return implode(', ', $parts);
}

/**
 * The condition that keeps the rows after a cursor, in the source's order.
 * MySQL sorts NULL before every value, so in an ascending level a NULL is
 * the lowest value and in a descending one the highest: a row is after a
 * NULL cursor value going up when it has any value, and nothing is after it
 * going down but a further level.
 *
 * @param array $source pg_list_table_source()
 * @param array $cursor pg_list_table_cursor_decode()'s answer
 * @return array array('sql', 'params')
 */
function pg_list_table_keyset_where(array $source, array $cursor)
{
    $levels = array();

    foreach ($source['levels'] as $number => $level) {
        $levels[] = array(
            'expr' => $source['columns'][$level['sort']]['sort'],
            'order' => $level['order'],
            'value' => $cursor['values'][$number] ?? null,
            'nullable' => true,
        );
    }

    // The id is never NULL: no IS NULL alternative for it.
    $levels[] = array('expr' => $source['id'], 'order' => $source['id_order'], 'value' => $cursor['id'], 'nullable' => false);

    $alternatives = array();
    $params = array();
    $equal_sql = array();
    $equal_params = array();

    foreach ($levels as $level) {
        $expr = $level['expr'];
        $value = $level['value'];
        $after = '';
        $after_params = array();

        if ($level['order'] === 'asc') {
            if ($value === null) {
                $after = $expr . ' IS NOT NULL';
            } else {
                $after = $expr . ' > ?';
                $after_params[] = $value;
            }
        } elseif ($value !== null) {
            $after = $level['nullable'] ? '(' . $expr . ' < ? OR ' . $expr . ' IS NULL)' : $expr . ' < ?';
            $after_params[] = $value;
        }

        if ($after !== '') {
            $alternatives[] = '(' . implode(' AND ', array_merge($equal_sql, array($after))) . ')';
            $params = array_merge($params, $equal_params, $after_params);
        }

        if ($value === null) {
            $equal_sql[] = $expr . ' IS NULL';
        } else {
            $equal_sql[] = $expr . ' = ?';
            $equal_params[] = $value;
        }
    }

    return array(
        'sql' => $alternatives ? '(' . implode(' OR ', $alternatives) . ')' : '(1 = 0)',
        'params' => $params,
    );
}

/**
 * The sort the cursor belongs to, so a cursor from another sort is not
 * read against this one.
 *
 * @param array $source pg_list_table_source()
 * @return string e.g. 'name:asc,last_modified:desc,#desc'
 */
function pg_list_table_sort_key(array $source)
{
    $pairs = array();

    foreach ($source['levels'] as $level) {
        $pairs[] = $level['sort'] . ':' . $level['order'];
    }

    $pairs[] = '#' . $source['id_order'];

    return implode(',', $pairs);
}

/**
 * The cursor after a row: its sort values and id, for the next batch.
 * Every value goes back to the query as a bound value, never as SQL.
 *
 * @param array $source pg_list_table_source()
 * @param array $values the row's sort values, one per level, in order
 * @param mixed $id     the row's id
 * @return string base64url text
 */
function pg_list_table_cursor_encode(array $source, array $values, $id)
{
    $values = array_map(function ($value) {
        return ($value === null) ? null : (string) $value;
    }, array_values($values));

    $json = json_encode(array('s' => pg_list_table_sort_key($source), 'v' => $values, 'i' => (string) $id));

    return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
}

/**
 * A cursor read back. A cursor that is not one, or belongs to another sort
 * (the user sorted again in another tab), answers null: the list starts
 * over from its first row.
 *
 * @param array $source pg_list_table_source()
 * @param mixed $cursor
 * @return array|null array('values' => list, 'id' => string)
 */
function pg_list_table_cursor_decode(array $source, $cursor)
{
    if (!is_string($cursor) || ($cursor === '') || (strlen($cursor) > 4096) || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) {
        return null;
    }

    $data = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

    if (!is_array($data) || (($data['s'] ?? null) !== pg_list_table_sort_key($source))
        || !isset($data['v']) || !is_array($data['v']) || (count($data['v']) !== count($source['levels']))
        || !isset($data['i']) || !is_string($data['i']) || ($data['i'] === '')) {
        return null;
    }

    foreach ($data['v'] as $value) {
        if (($value !== null) && !is_string($value)) {
            return null;
        }
    }

    return array('values' => array_values($data['v']), 'id' => $data['i']);
}

/**
 * The search condition of a source for the words of a search box.
 *
 * @param array $source pg_list_table_source()
 * @param array $terms  pg_list_search_terms()
 * @return array array('sql', 'params'); 'sql' is '' without a word
 */
function pg_list_table_search_where(array $source, array $terms)
{
    if (!$terms) {
        return array('sql' => '', 'params' => array());
    }

    return pg_list_search_where($terms, $source['search']['columns'], $source['search']['labels']);
}

/**
 * One batch of a list's rows: the first when there is no cursor, with the
 * count of the condition; else the rows after the cursor, uncounted.
 *
 * @param array       $source pg_list_table_source()
 * @param array       $terms  pg_list_search_terms()
 * @param string|null $cursor the cursor the browser sent back, or null
 * @return array|false array('rows', 'next' => cursor or null, 'total' =>
 *                     int, or null when not counted, 'restarted' => true
 *                     when a cursor was sent but could not be read) or
 *                     false when a query failed
 */
function pg_list_table_page(array $source, array $terms, $cursor = null)
{
    $after = ($cursor === null) ? null : pg_list_table_cursor_decode($source, $cursor);
    $search = pg_list_table_search_where($source, $terms);
    $where = '(' . $source['where'] . ')';
    $params = $source['params'];

    if ($search['sql'] !== '') {
        $where .= ' AND ' . $search['sql'];
        $params = array_merge($params, $search['params']);
    }

    $total = null;

    if ($after === null) {
        $result = db_q('SELECT COUNT(DISTINCT ' . $source['id'] . ') FROM ' . $source['from'] . ' WHERE ' . $where, $params);

        if (!$result) {
            return false;
        }

        $total = (int) mysqli_fetch_row($result)[0];
    } else {
        $keyset = pg_list_table_keyset_where($source, $after);
        $where .= ' AND ' . $keyset['sql'];
        $params = array_merge($params, $keyset['params']);
    }

    $select = array($source['id'] . ' AS pg_lt_id');

    foreach ($source['levels'] as $number => $level) {
        $select[] = $source['columns'][$level['sort']]['sort'] . ' AS pg_lt_k' . $number;
    }

    if ($source['select'] !== '') {
        $select[] = $source['select'];
    }

    $limit = $source['all'] ? null : pg_list_table_batch();

    $result = db_q(
        'SELECT ' . implode(', ', $select)
        . ' FROM ' . $source['from']
        . ' WHERE ' . $where
        . ($source['group'] ? ' GROUP BY ' . $source['id'] : '')
        . ' ORDER BY ' . pg_list_table_order_sql($source)
        . (($limit === null) ? '' : ' LIMIT ' . ($limit + 1)),
        $params);

    if (!$result) {
        return false;
    }

    $records = array();

    while ($record = mysqli_fetch_assoc($result)) {
        $records[] = $record;
    }

    $next = null;

    if (($limit !== null) && (count($records) > $limit)) {
        $records = array_slice($records, 0, $limit);
        $last = end($records);
        $values = array();

        foreach (array_keys($source['levels']) as $number) {
            $values[] = $last['pg_lt_k' . $number];
        }

        $next = pg_list_table_cursor_encode($source, $values, $last['pg_lt_id']);
    }

    $rows = is_callable($source['rows']) ? call_user_func($source['rows'], $records) : array();

    return array(
        'rows' => array_values((array) $rows),
        'next' => $next,
        'total' => $total,
        'restarted' => ($cursor !== null) && ($after === null),
    );
}

/**
 * A row as the browser gets it: its id, classes and cells by column key;
 * a cell the row lacks is empty, a cell of no column is dropped.
 *
 * @param array $row     array('id', 'class', 'cells' => key => pg_list_cell())
 * @param array $columns pg_list_table_columns()
 * @return array
 */
function pg_list_table_row_data(array $row, array $columns)
{
    $cells = array();

    foreach ($columns as $key => $column) {
        $cell = $row['cells'][$key] ?? pg_list_cell('');
        $data = array('html' => (string) ($cell['html'] ?? ''), 'class' => (string) ($cell['class'] ?? ''));

        if (!empty($cell['attributes'])) {
            $data['attrs'] = $cell['attributes'];
        }

        $cells[$key] = $data;
    }

    return array('id' => (string) ($row['id'] ?? ''), 'class' => (string) ($row['class'] ?? ''), 'cells' => $cells);
}

/**
 * The answer of the rows action.
 *
 * @param array $page    pg_list_table_page()
 * @param array $columns pg_list_table_columns()
 * @return array
 */
function pg_list_table_answer(array $page, array $columns)
{
    $rows = array();

    foreach ($page['rows'] as $row) {
        $rows[] = pg_list_table_row_data($row, $columns);
    }

    $answer = array('status' => 'success', 'rows' => $rows, 'next' => $page['next']);

    if ($page['total'] !== null) {
        $answer['total'] = (int) $page['total'];
    }

    if (!empty($page['restarted'])) {
        $answer['restarted'] = true;
    }

    return $answer;
}

/**
 * A row as table markup, its cells in the columns' order.
 *
 * @param array $row     array('id', 'class', 'cells' => key => pg_list_cell())
 * @param array $columns pg_list_table_columns()
 * @return string
 */
function pg_list_table_row_html(array $row, array $columns)
{
    $data = pg_list_table_row_data($row, $columns);
    $output = '<tr role="row" data-pg-id="' . h($data['id']) . '"' . (($data['class'] !== '') ? ' class="' . h($data['class']) . '"' : '') . '>';

    foreach ($data['cells'] as $key => $cell) {
        // A picture cell is marked, so the look sizes its thumbnail.
        $class = trim($cell['class'] . (($columns[$key]['align'] !== 'start') ? ' text-' . $columns[$key]['align'] : '')
            . (($columns[$key]['kind'] === 'image') ? ' pg-list-col-image' : ''));
        $output .= '<td role="cell" data-pg-col="' . h($key) . '"' . (($class !== '') ? ' class="' . h($class) . '"' : '');

        foreach ($cell['attrs'] ?? array() as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9-]*$/', (string) $name)) {
                $output .= ' ' . $name . '="' . h($value) . '"';
            }
        }

        $output .= ($columns[$key]['hidden'] ? ' hidden' : '') . '>' . $cell['html'] . '</td>';
    }

    return $output . '</tr>';
}

/**
 * The aria-sort value of a column: 'ascending' or 'descending' when it
 * leads the sort, '' otherwise (the attribute belongs to one heading).
 *
 * @param array  $levels pg_list_table_levels()
 * @param string $key
 * @return string
 */
function pg_list_table_aria_sort(array $levels, $key)
{
    if (!$levels || ($levels[0]['sort'] !== (string) $key)) {
        return '';
    }

    return ($levels[0]['order'] === 'desc') ? 'descending' : 'ascending';
}

/**
 * The driver's texts, translated here; '%n' stands for a number the
 * browser writes in.
 *
 * @return array
 */
function pg_list_table_texts()
{
    return array(
        'more' => lang('Show more'),
        'loading' => lang('Loading...'),
        'failed' => lang('The list could not be loaded.'),
        'retry' => lang('Try again'),
        'loaded' => lang(array('string' => '{var:1} rows loaded', 'vars' => array('%n'))),
        'selected' => lang(array('string' => '{var:1} selected', 'vars' => array('%n'))),
        'all' => lang(array('string' => 'All {var:1} rows selected', 'vars' => array('%n'))),
        'clear' => lang('Clear selection'),
        'undo' => lang('Undo the previous selection'),
        'sort' => lang('Sort (Shift+click adds a level)'),
        'sortBy' => lang('Sort by'),
        'ascending' => lang('Ascending'),
        'descending' => lang('Descending'),
        'sortAsc' => lang(array('string' => '{var:1}, ascending', 'vars' => array('%s'))),
        'sortDesc' => lang(array('string' => '{var:1}, descending', 'vars' => array('%s'))),
        'sortReverse' => lang('Reverse the direction'),
        'sortRemove' => lang('Remove this sort level'),
        'sortThen' => lang('Then sort by'),
        'sortAdd' => lang('Add'),
        'sortReset' => lang('Reset the sort order'),
        'selectAll' => lang('Select all'),
        'deselectAll' => lang('Deselect all'),
        'columns' => lang('Columns'),
        'details' => lang('Details'),
        'close' => lang('Close'),
        'up' => lang('Move up'),
        'down' => lang('Move down'),
        'reset' => lang('Reset'),
        'pick' => lang('Select a row to see its details.'),
        'quick' => lang('Quick edit'),
        'quickOn' => lang('Quick edit is on: a changed cell is saved as soon as you leave it.'),
        'quickOff' => lang('Quick edit is off.'),
        'saved' => lang('Saved'),
        'saveFailed' => lang('The change could not be saved. Check the connection and try again.'),
        'undoChange' => lang('Undo the last change'),
        'undone' => lang('The last change was undone.'),
        'apply' => lang(array('string' => 'Apply to {var:1} selected rows', 'vars' => array('%n'))),
        'applied' => lang(array('string' => '{var:1} rows changed', 'vars' => array('%n'))),
        'applyFailed' => lang(array('string' => '{var:1} rows could not be changed', 'vars' => array('%n'))),
    );
}

/**
 * A list printed with its first batch: the headings, the rows, the empty
 * line and the live region; the column model, the list's parameters, the
 * cursor of the next batch and the count ride on data attributes for the
 * browser part (assets/js/pg_list_table.js).
 *
 * @param array  $source  pg_list_table_source()
 * @param array  $page    pg_list_table_page() of the first batch
 * @param string $label   the list's name for assistive technology
 * @param string $actions the screen's bulk action buttons for the selection
 *                        bar (markup, escaped by the screen)
 * @return string
 */
function pg_list_table_html(array $source, array $page, $label, $actions = '')
{
    $columns = $source['columns'];
    $json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

    $output = '<div class="pg-list-table pg-card" data-pg-list-table="' . h($source['list']) . '"'
        . ' data-pg-list-mode="' . ($source['all'] ? 'browser' : 'server') . '"'
        . (!empty($source['printed']) ? ' data-pg-list-printed="1"' : '')
        . (!empty($source['ids_field']) ? ' data-pg-list-ids-field="' . h($source['ids_field']) . '"' : '')
        . ' data-pg-list-columns="' . h((string) json_encode(pg_list_table_client_columns($columns), $json)) . '"'
        . ' data-pg-list-params="' . h((string) json_encode((object) $source['params_out'], $json)) . '"'
        . ' data-pg-list-sort="' . h(pg_list_sort_value($source['levels'])) . '"'
        . ' data-pg-list-next="' . h((string) $page['next']) . '"'
        . ' data-pg-list-total="' . (int) $page['total'] . '"'
        . ' data-pg-list-user="' . (defined('USER_ID') ? (int) USER_ID : 0) . '"'
        . ' data-pg-list-texts="' . h((string) json_encode(pg_list_table_texts(), $json)) . '"'
        . ($source['edit'] ? pg_list_edit_table($source['edit']['url'], $source['edit']['columns']) : '') . '>';

    // The bar stays above the rows: the selection's count and bulk actions
    // while rows are selected, the columns menu and the details switch
    // always (quick edit and columns as icon buttons, Details by its name). The rows scroll in their own box under a sticky heading row,
    // so the list fills the window; the details panel stands beside them.
    $output .= '<div class="pg-list-table-bar is-empty"><span class="pg-list-table-count" aria-live="polite"></span>'
        . '<span class="pg-list-table-actions">' . $actions . '</span>'
        . '<span class="pg-list-table-tools">'
        . ($source['edit'] ? '<button type="button" class="pg-icon-button pg-icon-button-sm pg-list-table-quick" aria-pressed="false" aria-keyshortcuts="F2"'
            . ' aria-label="' . h(lang('Quick edit')) . '" title="' . h(lang('Quick edit')) . ' (F2)">'
            . '<i class="bi bi-pencil-square" aria-hidden="true"></i></button>' : '')
        . '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm pg-list-table-columns" data-bs-toggle="menu" data-bs-auto-close="outside" aria-expanded="false"'
        . ' aria-label="' . h(lang('Columns')) . '" title="' . h(lang('Columns')) . '">'
        . '<i class="bi bi-layout-three-columns" aria-hidden="true"></i></button>'
        . '<div class="menu pg-menu-end pg-list-table-columns-menu"></div></span>'
        . '<button type="button" class="pg-button pg-button-ghost pg-button-sm pg-list-table-details-switch" aria-pressed="false">'
        . '<i class="bi bi-layout-sidebar-reverse" aria-hidden="true"></i>' . h(lang('Details')) . '</button>'
        . '</span></div>';
    $output .= '<div class="pg-list-table-body">';
    $output .= '<div class="pg-list-table-scroll" tabindex="-1">';
    $output .= '<table class="table pg-list-table-grid" role="table" aria-label="' . h($label) . '">';
    $output .= '<thead role="rowgroup"><tr role="row">';

    foreach ($columns as $key => $column) {
        $class = 'pg-list-col-' . $column['kind'] . (($column['align'] !== 'start') ? ' text-' . $column['align'] : '');
        $aria_sort = pg_list_table_aria_sort($source['levels'], $key);

        $output .= '<th role="columnheader" scope="col" data-pg-col="' . h($key) . '" class="' . h($class) . '"'
            . ($column['sortable'] ? ' data-pg-sortable="1"' : '')
            . (($aria_sort !== '') ? ' aria-sort="' . $aria_sort . '"' : '')
            . (($column['width'] !== '') ? ' style="width: ' . h($column['width']) . '"' : '')
            . ($column['hidden'] ? ' hidden' : '')
            . '>' . h($column['heading']) . '</th>';
    }

    $output .= '</tr></thead><tbody role="rowgroup">';

    foreach ($page['rows'] as $row) {
        $output .= pg_list_table_row_html($row, $columns);
    }

    $output .= '</tbody></table>';
    $output .= '<p class="pg-list-table-empty fg-secondary"' . ($page['rows'] ? ' hidden' : '') . '>' . h(lang('No records found.')) . '</p>';
    $output .= '<div class="pg-list-table-foot"></div></div>';
    $output .= '<aside class="pg-list-table-details" aria-label="' . h(lang('Details')) . '" hidden>'
        . '<div class="pg-list-table-details-head"><strong>' . h(lang('Details')) . '</strong>'
        . '<button type="button" class="pg-icon-button pg-icon-button-sm pg-list-table-details-close" aria-label="' . h(lang('Close')) . '" title="' . h(lang('Close')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>'
        . '<div class="pg-list-table-details-body"></div></aside></div>';
    $output .= '<div class="visually-hidden" aria-live="polite" data-pg-list-live></div>';

    if (!empty($source['ids_field'])) {
        $output .= '<span class="pg-list-table-ids" hidden></span>';
    }

    return $output . '</div>';
}

/**
 * Record ids from a request: whole numbers above 0, each once, at most
 * $max of them.
 *
 * @param mixed $value
 * @param int   $max
 * @return array|null list of ints, or null when there are more than $max
 */
function pg_list_table_ids($value, $max)
{
    $ids = array();

    foreach (is_array($value) ? $value : array() as $id) {
        if ((is_int($id) || (is_string($id) && ctype_digit($id))) && ((int) $id > 0) && ((string) (int) $id === (string) $id)) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return (count($ids) > $max) ? null : array_values($ids);
}

/**
 * A selection as the browser sends it, checked: either the rows picked one
 * by one, array('ids' => [...]), or the whole list by its condition,
 * array('condition' => array('search' => the search box, 'params' => the
 * list's parameters), 'except' => ids left out, 'as_of' => Unix time the
 * selection was made). A condition never reaches past its moment: rows
 * created later are not in it.
 *
 * @param mixed $input the decoded request value
 * @param int   $now   the current Unix time
 * @param int   $max   the most ids a list of ids or exceptions may hold
 * @return array|null array('mode' => 'ids', 'ids') or array('mode' =>
 *                    'condition', 'search', 'params', 'except', 'as_of'),
 *                    or null when it is not a selection
 */
function pg_list_table_selection_parse($input, $now, $max = 10000)
{
    if (!is_array($input)) {
        return null;
    }

    if (array_key_exists('ids', $input)) {
        $ids = pg_list_table_ids($input['ids'], $max);

        return ($ids === null) ? null : array('mode' => 'ids', 'ids' => $ids);
    }

    if (!isset($input['condition']) || !is_array($input['condition'])) {
        return null;
    }

    $except = pg_list_table_ids($input['except'] ?? array(), $max);
    $as_of = $input['as_of'] ?? null;

    if (($except === null) || !(is_int($as_of) || (is_string($as_of) && ctype_digit($as_of))) || ((int) $as_of <= 0)) {
        return null;
    }

    $search = $input['condition']['search'] ?? '';
    $params = $input['condition']['params'] ?? array();

    return array(
        'mode' => 'condition',
        'search' => is_scalar($search) ? (string) $search : '',
        'params' => is_array($params) ? $params : array(),
        'except' => $except,
        'as_of' => min((int) $as_of, (int) $now),
    );
}

/**
 * The condition a selection adds to its list's own: the ids picked, or the
 * list's search, less the exceptions, up to the moment of the selection.
 * The list's own WHERE, with the user's rights, always comes first, so a
 * picked id the user may not reach is never in it.
 *
 * @param array $source    pg_list_table_source(), built from the
 *                         selection's parameters
 * @param array $selection pg_list_table_selection_parse()
 * @return array array('sql', 'params')
 */
function pg_list_table_selection_where(array $source, array $selection)
{
    if ($selection['mode'] === 'ids') {
        if (!$selection['ids']) {
            return array('sql' => '(1 = 0)', 'params' => array());
        }

        return array(
            'sql' => $source['id'] . ' IN (' . implode(', ', array_fill(0, count($selection['ids']), '?')) . ')',
            'params' => $selection['ids'],
        );
    }

    $parts = array();
    $params = array();
    $search = pg_list_table_search_where($source, pg_list_search_terms($selection['search']));

    if ($search['sql'] !== '') {
        $parts[] = $search['sql'];
        $params = $search['params'];
    }

    if ($selection['except']) {
        $parts[] = $source['id'] . ' NOT IN (' . implode(', ', array_fill(0, count($selection['except']), '?')) . ')';
        $params = array_merge($params, $selection['except']);
    }

    if ($source['created'] !== '') {
        $parts[] = '(' . $source['created'] . ' <= ?)';
        $params[] = $selection['as_of'];
    }

    return array('sql' => $parts ? implode(' AND ', $parts) : '(1 = 1)', 'params' => $params);
}

/**
 * The ids a selection stands for, read with the list's own query and the
 * user's rights, lowest first.
 *
 * @param array $source    pg_list_table_source()
 * @param array $selection pg_list_table_selection_parse()
 * @param int   $limit     the most ids handed back
 * @return array|false array('ids' => list of ints, 'more' => true when
 *                     the selection holds more than $limit) or false when
 *                     the query failed
 */
function pg_list_table_selected_ids(array $source, array $selection, $limit = 10000)
{
    $limit = max(1, (int) $limit);
    $where = pg_list_table_selection_where($source, $selection);

    $result = db_q(
        'SELECT DISTINCT ' . $source['id'] . ' AS pg_lt_id FROM ' . $source['from']
        . ' WHERE (' . $source['where'] . ') AND ' . $where['sql']
        . ' ORDER BY pg_lt_id LIMIT ' . ($limit + 1),
        array_merge($source['params'], $where['params']));

    if (!$result) {
        return false;
    }

    $ids = array();

    while ($row = mysqli_fetch_row($result)) {
        $ids[] = (int) $row[0];
    }

    $more = count($ids) > $limit;

    return array('ids' => array_slice($ids, 0, $limit), 'more' => $more);
}

/**
 * The lists a screen prints whole without a rows action (browser mode):
 * their sort is kept in the session by the list_table_sort action
 * (pg_list_table_printed_sort()). A list joins this table in the step that
 * moves its screen.
 *
 * @return array list names, as pg_list_state() keys them
 */
function pg_list_table_printed_lists()
{
    return array(
        'view_menus', 'view_short_links',
        'view_orders', 'view_gift_cards', 'view_key_codes', 'view_shipping_methods',
        'view_styles', 'view_themes', 'view_design_files', 'view_containers', 'view_comments',
        'view_regions_all_ad_regions', 'view_regions_all_login_regions', 'view_regions_all_dynamic_regions',
        'view_regions_all_designer_regions', 'view_regions_all_common_regions', 'view_ads', 'view_fields',
        'view_product_attributes', 'view_cart_quotes', 'view_returns', 'view_subscriptions', 'view_orders_for_contact',
        'view_arrival_dates', 'view_ship_date_adjustments', 'view_verified_shipping_addresses', 'view_order_reports', 'duplicate_products',
        'view_countries', 'view_states', 'view_zones', 'view_tax_zones', 'view_currencies', 'view_referral_sources',
        'view_contact_groups', 'view_calendars', 'view_calendar_event_locations', 'view_auto_dialogs',
        'view_email_campaigns', 'view_email_campaign_history', 'view_email_campaign_profiles', 'view_sms_campaigns', 'view_visitor_reports', 'view_not_found_log',
        'backups', 'cloudflare_dns', 'database_engine',
        'erp_accounts', 'erp_invoices', 'erp_waybills', 'erp_cash', 'erp_inbox',
        'erp_account_sync_differ', 'erp_account_sync_provider_only', 'erp_account_sync_erp_only',
        'erp_account_sync_same', 'erp_account_sync_ignored',
        'view_offers',
        'view_bookings', 'view_products_variant_sets',
    );
}

/**
 * A small list printed whole: the browser sorts and searches it itself
 * (browser mode), so no SQL comes with it. The screen reads its records with
 * its own query and rights and hands the rows over already built.
 *
 * Every column sorts in the browser unless its spec says 'sortable' =>
 * false, or it is an actions or image column; a cell can carry the value it
 * sorts by in a data-pg-sort-value attribute (pg_list_cell()'s attributes),
 * e.g. a Unix time behind a relative date.
 *
 * @param array $spec 'list' (the list's name, its pg_list_state() key and
 *                    one of pg_list_table_printed_lists()), 'columns'
 *                    (key => spec as pg_list_table_column() takes it, without
 *                    'sort'), 'levels' (the sort, e.g. pg_list_state()'s
 *                    'sorts'), 'rows' (list of array('id', 'class', 'cells'
 *                    => key => pg_list_cell())), 'edit' (quick edit, as
 *                    pg_list_table_source() takes it), 'ids_field' (the
 *                    name of the form field the selected ids are posted in,
 *                    e.g. 'styles[]', for a screen whose bulk endpoint reads
 *                    a list of ids: the driver keeps one hidden field per
 *                    selected row inside the list)
 * @return array array('source' => pg_list_table_source(), 'page' => the
 *               page pg_list_table_html() prints)
 */
function pg_list_table_printed(array $spec)
{
    $specs = isset($spec['columns']) && is_array($spec['columns']) ? $spec['columns'] : array();
    $source = pg_list_table_source(array(
        'list' => $spec['list'] ?? '',
        'columns' => $specs,
        'all' => true,
        'edit' => $spec['edit'] ?? null,
    ));

    foreach ($source['columns'] as $key => $column) {
        $own = is_array($specs[$key] ?? null) ? $specs[$key] : array();

        $source['columns'][$key]['sort'] = '';
        $source['columns'][$key]['sortable'] = !in_array($column['kind'], array('actions', 'image'), true)
            && (!array_key_exists('sortable', $own) || !empty($own['sortable']));
    }

    $source['levels'] = pg_list_table_levels(isset($spec['levels']) && is_array($spec['levels']) ? $spec['levels'] : array(), $source['columns']);
    $source['printed'] = in_array($source['list'], pg_list_table_printed_lists(), true);
    $source['ids_field'] = (isset($spec['ids_field']) && is_string($spec['ids_field']) && preg_match('/^[a-z][a-z0-9_]*(\[\])?$/', $spec['ids_field'])) ? $spec['ids_field'] : '';
    $rows = array_values(isset($spec['rows']) && is_array($spec['rows']) ? $spec['rows'] : array());

    return array(
        'source' => $source,
        'page' => array('rows' => $rows, 'next' => null, 'total' => count($rows), 'restarted' => false),
    );
}

/**
 * The sort codes of a printed list for pg_list_state() and
 * pg_list_sort_menu(): every column that sorts in the browser, keyed by
 * itself, with its heading.
 *
 * @param array $columns key => spec, as pg_list_table_printed() takes them
 * @return array key => heading
 */
function pg_list_table_printed_sort_columns(array $columns)
{
    $sort_columns = array();

    foreach ($columns as $key => $spec) {
        $kind = (string) ($spec['kind'] ?? 'text');

        if (in_array($kind, array('actions', 'image'), true) || (array_key_exists('sortable', $spec) && empty($spec['sortable']))) {
            continue;
        }

        $sort_columns[$key] = (string) ($spec['heading'] ?? '');
    }

    return $sort_columns;
}

/**
 * Keeps the sort a printed list was given by a heading click in the
 * browser, the way pg_list_state() keeps a list's sort, so the screen comes
 * back sorted the same way. pg_list_state() checks every level against the
 * screen's own columns when it reads them, so only the list's name and the
 * form of the keys are checked here.
 *
 * @param mixed $list  one of pg_list_table_printed_lists()
 * @param mixed $value the sort as the address carries it, e.g.
 *                     'name:asc,created:desc' (pg_list_sort_value())
 * @return bool false when the list or the sort was refused
 */
function pg_list_table_printed_sort($list, $value)
{
    if (!is_string($list) || !in_array($list, pg_list_table_printed_lists(), true) || !is_string($value) || ($value === '')) {
        return false;
    }

    $levels = array();

    foreach (array_slice(explode(',', $value), 0, 5) as $item) {
        $parts = explode(':', trim($item), 2);
        $key = trim($parts[0]);

        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
            return false;
        }

        if (!isset($levels[$key])) {
            $levels[$key] = array('sort' => $key, 'order' => (strtolower(trim($parts[1] ?? '')) === 'desc') ? 'desc' : 'asc');
        }
    }

    $levels = array_values($levels);
    $kept = $levels[0];

    if (count($levels) > 1) {
        $kept['then'] = array_slice($levels, 1);
    }

    $_SESSION['software']['list_state'][$list] = $kept;

    return true;
}

/**
 * The search box of a list in the screen's toolbar: the driver hears it by
 * the list's name (data-pg-list-search).
 *
 * @param string $list the list's name, as the source gives it
 * @return string
 */
function pg_list_table_search_box($list)
{
    return '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">'
        . '<span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>'
        . '<input type="search" class="form-ghost" data-pg-list-search="' . h($list) . '" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">'
        . '</label>';
}

/**
 * A date cell of a printed list: the relative time and who made the change,
 * sorted by the time itself.
 *
 * @param mixed  $timestamp Unix seconds
 * @param string $username  '' when not known
 * @return array pg_list_cell()
 */
function pg_list_table_when_cell($timestamp, $username = '')
{
    return pg_list_cell(pg_list_when($timestamp, (string) $username), 'align-middle', array('data-pg-sort-value' => (int) $timestamp));
}

/**
 * A yes/no cell of a printed list: a check mark that says Yes, or nothing;
 * sorted by 1 or 0.
 *
 * @param mixed $on
 * @return array pg_list_cell()
 */
function pg_list_table_check_cell($on)
{
    return pg_list_cell($on ? '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>' : '',
        'align-middle', array('data-pg-sort-value' => $on ? 1 : 0));
}

/**
 * The SEO column of a list: its heading, a narrow width and the score it
 * sorts by. The bar sits in this column of its own, never under the
 * record's name, so rows keep one height.
 *
 * @param string $sort the SQL expression of the score ('' when the list
 *                     does not sort by it)
 * @return array spec, as pg_list_table_column() takes it
 */
function pg_list_table_seo_column($sort)
{
    return array('heading' => lang('SEO'), 'width' => '7.5rem', 'kind' => 'badge', 'sort' => (string) $sort);
}

/**
 * The SEO column's cell: the score bar with its number, opening the
 * record's analysis; a fresh analysis redraws it (data-pg-seo-bar). Needs
 * seo.php.
 *
 * @param string $type 'page' or 'product'
 * @param int    $id
 * @param array  $row  the record with seo_score, seo_flags and
 *                     seo_analysis_current
 * @return array pg_list_cell()
 */
function pg_list_table_seo_cell($type, $id, array $row)
{
    $key = $type . ':' . (int) $id;
    $url = 'get_seo_analysis.php?type=' . rawurlencode($type) . '&amp;id=' . (int) $id . get_token_query_string_field();

    return pg_list_cell('<a href="javascript:void(0)" class="pg-seo-open pg-list-table-seo text-decoration-none" data-pg-seo-bar="' . h($key) . '"'
        . ' aria-label="' . h(pg_seo_link_name($row)) . '" title="' . h(lang('SEO Detail')) . '" data-seo-url="' . $url . '">'
        . pg_seo_render_bar($row) . '</a>',
        'align-middle', array('data-pg-sort-value' => pg_seo_row_scored($row) ? (int) $row['seo_score'] : -1));
}
