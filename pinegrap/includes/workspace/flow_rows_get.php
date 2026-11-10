<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the box of a flow that reads the rows of a workflow table
 * (kind rows_get), as SQL's SELECT … WHERE … ORDER BY … LIMIT: the rows
 * that meet its conditions (ws_wtable_rows_where()), read with the flow
 * owner's rights as they stand when the box runs. It gives the boxes after
 * it how many rows met them, the sum, average, smallest and largest value
 * of each number, money and formula column over those rows, and the first
 * of them in its sort as a list a For each box walks (row_id, position and
 * cell_<column id> per row, as a read row gives its cells). The same
 * conditions let "Read a row" find the first row that meets them (row_get
 * by match, ws_step_row_match()).
 *
 * Conditions are kept as a JSON list, each a column id, a comparison and a
 * value typed or one value of the run alone. The run's values go in as the
 * inside of their JSON strings (ws_step_fill_target() json), as a table
 * box's cells take them, so a value is compared as it was given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest a box's conditions are, as JSON text, in characters.
define('WS_STEP_WHERE_TEXT_MAX', 10000);

/**
 * How a box's conditions join and the conditions, as its settings keep
 * them: a join it does not know is all; conditions given as a list are
 * written as their JSON, a text is kept as written (the editor's problems
 * read it), none when $keep is false. Pure.
 *
 * @param array $config
 * @param bool  $keep   the box finds its rows by conditions
 * @return array match, where
 */
function ws_step_where_normalize($config, $keep = true)
{
    $match = $config['match'] ?? '';
    $where = $config['where'] ?? '';

    if (is_array($where) || is_object($where)) {
        $where = (string) json_encode($where, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    return array(
        'match' => (is_string($match) && in_array($match, ws_wtable_where_joins(), true)) ? $match : 'all',
        'where' => $keep ? ws_flow_lines(is_scalar($where) ? $where : '', WS_STEP_WHERE_TEXT_MAX) : '',
    );
}

/**
 * The settings of a box that reads rows: table_id, match and where
 * (ws_step_where_normalize()), sort (a column id, 0 for the table's
 * order), dir (asc, desc) and limit, the rows of its list (1 to the most a
 * For each box walks; none is the most). Pure.
 *
 * @param array $config
 * @return array
 */
function ws_step_rows_get_normalize($config)
{
    $table = $config['table_id'] ?? 0;
    $sort = $config['sort'] ?? 0;
    $dir = $config['dir'] ?? '';
    $limit = $config['limit'] ?? 0;
    $limit = (is_bool($limit) || !is_numeric($limit)) ? 0 : (int) $limit;

    return array('table_id' => (is_bool($table) || !is_numeric($table)) ? 0 : max(0, (int) $table)) + ws_step_where_normalize($config) + array(
        'sort'  => (ws_wtable_is_whole($sort) && ((int) $sort > 0)) ? (int) $sort : 0,
        'dir'   => (is_string($dir) && in_array($dir, ws_wtable_sort_directions(), true)) ? $dir : 'asc',
        'limit' => ($limit <= 0) ? WS_FLOW_LOOP_ITEMS_MAX : min(WS_FLOW_LOOP_ITEMS_MAX, $limit),
    );
}

/**
 * What one condition lacks as written, whatever its table: column (none
 * chosen), op (no comparison it knows), value (a comparison that takes a
 * value has none) or mixed (a value of the run beside other text: a value
 * is typed, or one value alone). '' when it is whole. Pure.
 *
 * @param array $condition ws_wtable_where_list() item
 * @return string
 */
function ws_step_where_fault($condition)
{
    if ((int) ($condition['column'] ?? 0) <= 0) {
        return 'column';
    }

    if ((string) ($condition['op'] ?? '') === '') {
        return 'op';
    }

    if (!ws_wtable_where_takes_value($condition['op'])) {
        return '';
    }

    $value = trim((string) ($condition['value'] ?? ''));

    if ($value === '') {
        return 'value';
    }

    return ((preg_match(WS_STEP_VALUE_PATTERN, $value, $match) === 1) && ($match[0] !== $value)) ? 'mixed' : '';
}

/**
 * A fault of ws_step_where_fault() in words. Pure but for lang().
 *
 * @param string $fault
 * @return string
 */
function ws_step_where_fault_words($fault)
{
    switch ((string) $fault) {
        case 'column':
            return lang('Choose its column.');

        case 'op':
            return lang('Choose how it compares.');

        case 'value':
            return lang('Give the value it compares with.');
    }

    return lang('Give one value alone, or type the value: not both.');
}

/**
 * The editor's problems with a box's conditions, named by the box: a text
 * that is no JSON list of conditions, more than WS_WTABLE_WHERE_MAX of
 * them, none where the box needs one, then what each one lacks. Pure but
 * for lang().
 *
 * @param array  $config    ws_step_where_normalize() keys
 * @param string $name      the box as the canvas names it
 * @param bool   $needs_one the box finds its row by them (row_get by match)
 * @return string[]
 */
function ws_step_where_problems($config, $name, $needs_one)
{
    $list = ws_wtable_where_list((string) ($config['where'] ?? ''));

    if ($list === null) {
        return array(lang(array('string' => 'The conditions of the box {var:1} are not a JSON list, each condition a column, a comparison and a value.', 'vars' => $name)));
    }

    if (count($list) > WS_WTABLE_WHERE_MAX) {
        return array(lang(array('string' => 'The box {var:1} has more than {var:2} conditions.', 'vars' => array($name, WS_WTABLE_WHERE_MAX))));
    }

    if ($needs_one && empty($list)) {
        return array(lang(array('string' => 'The box {var:1} finds its row by conditions, but has none.', 'vars' => $name)));
    }

    $problems = array();

    foreach ($list as $place => $condition) {
        $fault = ws_step_where_fault($condition);

        if ($fault !== '') {
            $problems[] = lang(array('string' => 'The box {var:1}, condition {var:2}: {var:3}', 'vars' => array($name, $place + 1, ws_step_where_fault_words($fault))));
        }
    }

    return $problems;
}

/**
 * Why a box's conditions and sort are refused against its table's columns
 * at publish, each run and a try, '' when they fit: what the editor's
 * problems name, then per condition a column the table no longer has, a
 * comparison its type does not take or a typed value that does not fit it
 * (a value of the run is known only as the box runs: it holds for no row
 * when it does not fit), and a sort column the table no longer has. Pure
 * but for lang().
 *
 * @param array $columns   column id => column (ws_wtable_columns())
 * @param array $config    where, and sort for a box that sorts
 * @param bool  $needs_one the box finds its row by them
 * @return string
 */
function ws_step_where_refusal($columns, $config, $needs_one)
{
    $list = ws_wtable_where_list((string) ($config['where'] ?? ''));

    if ($list === null) {
        return lang('The conditions are not a JSON list of conditions.');
    }

    if (count($list) > WS_WTABLE_WHERE_MAX) {
        return lang(array('string' => 'The box has more than {var:1} conditions.', 'vars' => WS_WTABLE_WHERE_MAX));
    }

    if ($needs_one && empty($list)) {
        return lang('The box finds its row by conditions, but has none.');
    }

    foreach ($list as $place => $condition) {
        $said = ws_step_where_column_refusal($columns, $condition);

        if ($said !== '') {
            return lang(array('string' => 'Condition {var:1}: {var:2}', 'vars' => array($place + 1, $said)));
        }
    }

    $sort = (int) ($config['sort'] ?? 0);

    return (($sort > 0) && !isset($columns[(string) $sort])) ? lang('The box sorts by a column the table no longer has.') : '';
}

/**
 * Why one condition does not fit its table's columns, '' when it does
 * (ws_step_where_refusal()). Pure but for lang().
 *
 * @param array $columns
 * @param array $condition ws_wtable_where_list() item
 * @return string
 */
function ws_step_where_column_refusal($columns, $condition)
{
    $fault = ws_step_where_fault($condition);

    if ($fault !== '') {
        return ws_step_where_fault_words($fault);
    }

    $column = $columns[(string) (int) $condition['column']] ?? null;

    if (!is_array($column)) {
        return lang('It names a column the table no longer has.');
    }

    $name = (string) $column['name'];
    $op = (string) $condition['op'];

    if (!in_array($op, ws_wtable_where_type_operators($column['type'] ?? ''), true)) {
        return lang(array('string' => 'The column “{var:1}” cannot be compared with “{var:2}”.', 'vars' => array($name, (string) (ws_wtable_where_operators()[$op] ?? $op))));
    }

    $value = trim((string) $condition['value']);

    if (preg_match(WS_STEP_VALUE_PATTERN, $value) === 1) {
        return '';
    }

    return ws_wtable_where_fault_text($column, ws_wtable_where_value_fault($column, $op, $value), $value);
}

/**
 * A box's conditions as a reader who may not change the flow reads them:
 * the columns and comparisons, never the values, which may be a
 * customer's data; a text the server cannot read keeps nothing. Pure.
 *
 * @param string $where
 * @return string
 */
function ws_step_where_masked($where)
{
    $list = ws_wtable_where_list((string) $where);

    if (empty($list)) {
        return '';
    }

    foreach ($list as $place => $condition) {
        $list[$place]['value'] = '';
    }

    return (string) json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Checks a box that reads rows with somebody's rights, the owner as they
 * publish and again each time the box runs: a table they may read
 * (ws_wtable_flow_check() at view) whose owner is in the team (else the box
 * waits for them: paused), and conditions and a sort that fit its columns
 * (ws_step_where_refusal()).
 *
 * @param array $viewer the flow's owner
 * @param array $config ws_step_config_normalize('rows_get')
 * @return array ok, error, paused, action (type, table_id, table_name, match, where, sort, dir, limit)
 */
function ws_step_rows_get_check($viewer, $config)
{
    $refuse = function ($error, $paused = false) {
        return array('ok' => false, 'error' => $error, 'paused' => $paused, 'action' => null);
    };

    if (!function_exists('ws_wtable_flow_check') || !ws_wtables_ready()) {
        return $refuse(lang('The database has to be updated first.'));
    }

    $table = ws_wtable((int) ($config['table_id'] ?? 0));
    $gate = $table ? ws_wtable_flow_check($viewer, (int) $table['id'], 'view') : 'refused';

    if ($gate === 'paused') {
        return $refuse(lang(array('string' => 'The owner of the table “{var:1}” has left the team: no row is read from it until they are back.', 'vars' => (string) $table['name'])), true);
    }

    if ($gate !== '') {
        return $refuse(lang('Choose a table you may read.'));
    }

    $refusal = ws_step_where_refusal(ws_wtable_columns((int) $table['id']), $config, false);

    if ($refusal !== '') {
        return $refuse($refusal);
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array(
        'type'       => 'rows_get',
        'table_id'   => (int) $table['id'],
        'table_name' => (string) $table['name'],
        'match'      => (string) $config['match'],
        'where'      => (string) $config['where'],
        'sort'       => (int) $config['sort'],
        'dir'        => (string) $config['dir'],
        'limit'      => (int) $config['limit'],
    ));
}

/**
 * The totals a box that reads rows gives beside its count and list: the
 * sum, average, smallest and largest value of each number, money and
 * formula column (sum_<id>, avg_<id>, min_<id>, max_<id>). Pure but for
 * lang().
 *
 * @param array[] $columns id, name, type
 * @return array[] key, type, label
 */
function ws_step_rows_get_outputs($columns)
{
    $out = array();

    foreach ((array) $columns as $column) {
        $id = (int) ($column['id'] ?? 0);

        if (($id <= 0) || !ws_wtable_total_type($column['type'] ?? '')) {
            continue;
        }

        $name = (string) ($column['name'] ?? '');
        $out[] = array('key' => 'sum_' . $id, 'type' => 'number', 'label' => lang(array('string' => 'Sum of {var:1}', 'vars' => $name)));
        $out[] = array('key' => 'avg_' . $id, 'type' => 'number', 'label' => lang(array('string' => 'Average of {var:1}', 'vars' => $name)));
        $out[] = array('key' => 'min_' . $id, 'type' => 'number', 'label' => lang(array('string' => 'Smallest {var:1}', 'vars' => $name)));
        $out[] = array('key' => 'max_' . $id, 'type' => 'number', 'label' => lang(array('string' => 'Largest {var:1}', 'vars' => $name)));
    }

    return $out;
}

/**
 * Rows as the items of the box's list: each its id, its number in the
 * table and one value per column, as a read row gives its cells
 * (ws_flow_row_cell_text()). Pure.
 *
 * @param array   $columns column id => column
 * @param array[] $rows    as read (ws_wtable_row_out())
 * @return array[] row_id, position, cell_<column id>
 */
function ws_step_rows_get_items($columns, $rows)
{
    $items = array();

    foreach ((array) $rows as $row) {
        $cells = (array) ($row['cells'] ?? array());
        $item = array('row_id' => (int) $row['id'], 'position' => (int) $row['position']);

        foreach ((array) $columns as $column) {
            $id = (int) ($column['id'] ?? 0);

            if ($id > 0) {
                $item['cell_' . $id] = ws_flow_row_cell_text($cells[(string) $id] ?? null);
            }
        }

        $items[] = $item;
    }

    return $items;
}

/**
 * The items of the box's list as the JSON a For each box reads, whole items
 * only while the list fits $bytes, and how many it kept. Pure.
 *
 * @param array[] $items ws_step_rows_get_items()
 * @param int     $bytes
 * @return array json, kept
 */
function ws_step_rows_get_list($items, $bytes)
{
    $kept = array();
    $json = '[]';

    foreach ((array) $items as $item) {
        $next = (string) json_encode(array_merge($kept, array($item)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (strlen($next) > (int) $bytes) {
            break;
        }

        $kept[] = $item;
        $json = $next;
    }

    return array('json' => $json, 'kept' => count($kept));
}

/**
 * What a box that reads rows gives, from what it read: the count of every
 * row that met its conditions, its list, and each total as the text it
 * fills in (a machine figure up to six places, money in minor units; ''
 * where no row had a value). Pure.
 *
 * @param array  $columns column id => column
 * @param int    $count   the rows that met the conditions
 * @param array  $totals  ws_wtable_rows_totals() of those rows
 * @param string $json    ws_step_rows_get_list() json
 * @return array count, rows, sum_<id>, avg_<id>, min_<id>, max_<id>
 */
function ws_step_rows_get_data($columns, $count, $totals, $json)
{
    $out = array('count' => (int) $count, 'rows' => (string) $json);

    foreach ((array) $columns as $column) {
        $id = (string) (int) ($column['id'] ?? 0);

        if (!isset($totals[$id])) {
            continue;
        }

        foreach (array('sum', 'avg', 'min', 'max') as $what) {
            $value = $totals[$id][$what];
            $out[$what . '_' . $id] = ($value === null) ? '' : (is_int($value) ? (string) $value : ws_flow_derived_number($value));
        }
    }

    return $out;
}

/**
 * What the box says it read: how many rows met its conditions (or the
 * table has), and how many its list holds when fewer. Pure but for
 * lang().
 *
 * @param string $table    the table's name
 * @param int    $count
 * @param int    $kept     the rows of its list
 * @param bool   $filtered it has conditions
 * @return string
 */
function ws_step_rows_get_said($table, $count, $kept, $filtered)
{
    $said = $filtered
        ? lang(array('string' => 'Rows of the table “{var:1}” that met the conditions: {var:2}.', 'vars' => array($table, (int) $count)))
        : lang(array('string' => 'Rows of the table “{var:1}”: {var:2}.', 'vars' => array($table, (int) $count)));

    if ((int) $kept < (int) $count) {
        $said .= ' ' . lang(array('string' => 'The list holds the first {var:1} of them.', 'vars' => (int) $kept));
    }

    return $said;
}

/**
 * Reads the rows a box asks for, with the flow owner's rights checked
 * (ws_step_rows_get_check()), the conditions filled in: every row of the
 * table as read (formulas worked out), those that meet the conditions,
 * their totals, and the first of them in the box's sort as its list, whole
 * rows only while they fit what a For each box takes. A table gone
 * meanwhile fails the box.
 *
 * @param array $owner  the flow's owner, checked
 * @param array $action ws_step_rows_get_check(), filled
 * @return array ok, text, data
 */
function ws_step_rows_get_do($owner, $action)
{
    $table = ws_wtable((int) $action['table_id']);
    $name = $table ? (string) $table['name'] : (string) ($action['table_name'] ?? '');

    if (!$table) {
        return array('ok' => false, 'text' => lang(array('string' => 'The table “{var:1}” is gone.', 'vars' => $name)), 'data' => array());
    }

    $conditions = ws_wtable_where_list((string) ($action['where'] ?? ''));

    if ($conditions === null) {
        return array('ok' => false, 'text' => lang('The conditions are not a JSON list of conditions.'), 'data' => array());
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $rows = ws_wtable_rows_where($columns, ws_wtable_rows((int) $table['id'], $columns), $conditions, (string) ($action['match'] ?? 'all'));
    $sorted = ws_wtable_rows_sorted($columns, $rows, (int) ($action['sort'] ?? 0), (string) ($action['dir'] ?? 'asc'));
    $limit = max(1, min(WS_FLOW_LOOP_ITEMS_MAX, (int) ($action['limit'] ?? WS_FLOW_LOOP_ITEMS_MAX)));
    $list = ws_step_rows_get_list(ws_step_rows_get_items($columns, array_slice($sorted, 0, $limit)), WS_FLOW_LOOP_ITEMS_BYTES);

    return array(
        'ok'   => true,
        'text' => ws_step_rows_get_said($name, count($rows), $list['kept'], !empty($conditions)),
        'data' => ws_step_rows_get_data($columns, count($rows), ws_wtable_rows_totals($columns, $rows), $list['json']),
    );
}

/**
 * What a box that reads rows does, in words. Pure but for lang().
 *
 * @param array $action ws_step_rows_get_check()
 * @return string
 */
function ws_step_rows_get_text($action)
{
    $table = (string) ($action['table_name'] ?? '');
    $limit = (int) ($action['limit'] ?? WS_FLOW_LOOP_ITEMS_MAX);

    return empty(ws_wtable_where_list((string) ($action['where'] ?? '')))
        ? lang(array('string' => 'Reads every row of the table “{var:1}”, at most {var:2} in its list.', 'vars' => array($table, $limit)))
        : lang(array('string' => 'Reads the rows of the table “{var:1}” that meet its conditions, at most {var:2} in its list.', 'vars' => array($table, $limit)));
}

/**
 * What the canvas writes a box's conditions with: each comparison's words,
 * the comparisons each column type takes, the most conditions and the
 * most rows of a list. Pure but for lang().
 *
 * @return array operators, types, most, limit
 */
function ws_flow_js_where()
{
    $types = array();

    foreach (array_keys(ws_wtable_column_types()) as $type) {
        $types[$type] = ws_wtable_where_type_operators($type);
    }

    return array('operators' => ws_wtable_where_operators(), 'types' => $types, 'most' => WS_WTABLE_WHERE_MAX, 'limit' => WS_FLOW_LOOP_ITEMS_MAX);
}

/**
 * The first row of a table, in its order, that meets a box's conditions
 * (row_get by match), as read (ws_wtable_row_out()); null for none.
 *
 * @param int   $table_id
 * @param array $columns column id => column
 * @param array $action  match, where (filled in)
 * @return array|null
 */
function ws_step_row_match($table_id, $columns, $action)
{
    $conditions = ws_wtable_where_list((string) ($action['where'] ?? ''));

    if (empty($conditions)) {
        return null;
    }

    $rows = ws_wtable_rows_where($columns, ws_wtable_rows((int) $table_id, $columns), $conditions, (string) ($action['match'] ?? 'all'));

    return $rows[0] ?? null;
}
