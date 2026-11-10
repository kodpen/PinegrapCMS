<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - rollup columns of workflow tables, as GROUP BY over a join: a
 * column of type rollup names another table (options.table), its row link
 * column that points at this table (options.link), an operation
 * (options.op: count, sum, avg, min, max), the column it totals
 * (options.column, 0 for a count) and, if any, conditions on those rows
 * (options.where, options.match). Nothing is stored: the value is worked
 * out when the table is read, for whoever reads it, one read of each other
 * table per page. A reader who does not read the other table sees an
 * empty cell; a flow reads with its owner's rights.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Rules (pure)
   --------------------------------------------------------------------------- */

/**
 * The operations a rollup works out, each with its name as shown.
 *
 * @return array op => label
 */
function ws_wtable_rollup_ops()
{
    return array(
        'count' => lang('Count'),
        'sum' => lang('Sum'),
        'avg' => lang('Average'),
        'min' => lang('Smallest'),
        'max' => lang('Largest'),
    );
}

/**
 * The options of a rollup column, checked: table and link whole numbers
 * from 1, op one of ws_wtable_rollup_ops(), column a whole number from 1
 * (0 for a count, which counts rows), where a list of at most
 * WS_WTABLE_WHERE_MAX conditions as the condition engine reads them, and
 * match all or any. Pure.
 *
 * @param array $options
 * @return array ok, value, error
 */
function ws_wtable_rollup_options_check($options)
{
    $options = is_array($options) ? $options : array();
    $fail = function ($error) {
        return array('ok' => false, 'value' => array(), 'error' => $error);
    };

    foreach (array('table' => 'rollup_table_invalid', 'link' => 'rollup_link_invalid') as $key => $error) {
        if (!ws_wtable_is_whole($options[$key] ?? null) || ((int) $options[$key] <= 0)) {
            return $fail($error);
        }
    }

    $op = $options['op'] ?? '';

    if (!is_string($op) || !isset(ws_wtable_rollup_ops()[$op])) {
        return $fail('rollup_op_invalid');
    }

    $column = $options['column'] ?? 0;

    if ($op === 'count') {
        $column = 0;
    } elseif (!ws_wtable_is_whole($column) || ((int) $column <= 0)) {
        return $fail('rollup_column_invalid');
    }

    $where = ws_wtable_where_list($options['where'] ?? array());

    if (($where === null) || (count($where) > WS_WTABLE_WHERE_MAX)) {
        return $fail('rollup_where_invalid');
    }

    $match = (string) ($options['match'] ?? 'all');

    return array('ok' => true, 'error' => '', 'value' => array(
        'table' => (int) $options['table'],
        'link' => (int) $options['link'],
        'op' => $op,
        'column' => (int) $column,
        'where' => $where,
        'match' => in_array($match, ws_wtable_where_joins(), true) ? $match : 'all',
    ));
}

/**
 * The rollup columns of a table: column id => table, link, op, column,
 * where, match. Pure.
 *
 * @param array $columns column id => column
 * @return array[]
 */
function ws_wtable_rollups($columns)
{
    $out = array();

    foreach ((array) $columns as $column_id => $column) {
        if (($column['type'] ?? '') !== 'rollup') {
            continue;
        }

        $check = ws_wtable_rollup_options_check($column['options'] ?? array());

        if ($check['ok']) {
            $out[(string) $column_id] = $check['value'];
        }
    }

    return $out;
}

/**
 * Rows with their rollup cells worked out from the other tables' rows.
 * A table missing from $others (one the reader does not read) leaves the
 * cell empty. Of the other rows, those whose link cell names the row and
 * that meet the conditions count: a count is how many (0 for none); a sum,
 * average, smallest and largest value are worked out as the totals line
 * does (ws_wtable_rows_totals(): money in minor units, its average
 * rounded), empty when no row gave a value. Pure.
 *
 * @param array   $columns column id => column
 * @param array[] $rows    as read: id, cells by column id
 * @param array   $others  table id => columns (column id => column), rows (each with cells, formulas worked out)
 * @return array[]
 */
function ws_wtable_rollup_values($columns, $rows, $others)
{
    $rollups = ws_wtable_rollups($columns);
    $groups = array();

    foreach ($rollups as $rollup) {
        $key = $rollup['table'] . ':' . $rollup['link'];

        if (isset($groups[$key]) || !isset($others[$rollup['table']])) {
            continue;
        }

        $groups[$key] = array();

        foreach ((array) $others[$rollup['table']]['rows'] as $other) {
            $parent = $other['cells'][(string) $rollup['link']] ?? null;

            if (ws_wtable_is_whole($parent) && ((int) $parent > 0)) {
                $groups[$key][(int) $parent][] = $other;
            }
        }
    }

    foreach ((array) $rows as $place => $row) {
        foreach ($rollups as $column_id => $rollup) {
            unset($rows[$place]['cells'][$column_id]);
            $other = $others[$rollup['table']] ?? null;

            if (!is_array($other) || (ws_wtable_link_table($other['columns'][(string) $rollup['link']] ?? array()) <= 0)) {
                continue;
            }

            $linked = $groups[$rollup['table'] . ':' . $rollup['link']][(int) ($row['id'] ?? 0)] ?? array();
            $kept = ws_wtable_rows_where($other['columns'], $linked, $rollup['where'], $rollup['match']);

            if ($rollup['op'] === 'count') {
                $rows[$place]['cells'][$column_id] = count($kept);
                continue;
            }

            $target = $other['columns'][(string) $rollup['column']] ?? null;

            if (!is_array($target) || !ws_wtable_total_type($target['type'] ?? '')) {
                continue;
            }

            $totals = ws_wtable_rows_totals(array((string) $rollup['column'] => $target), $kept);
            $value = $totals[(string) $rollup['column']][$rollup['op']] ?? null;

            if (($value !== null) && (($rollup['op'] !== 'sum') || ($totals[(string) $rollup['column']]['count'] > 0))) {
                $rows[$place]['cells'][$column_id] = $value;
            }
        }
    }

    return $rows;
}

/**
 * The column a rollup's value is written by: a count a whole number, a
 * money column's total its money, any other total a number. Pure.
 *
 * @param array      $rollup ws_wtable_rollups() item
 * @param array|null $target the column it totals
 * @return array|null
 */
function ws_wtable_rollup_display($rollup, $target)
{
    if ($rollup['op'] === 'count') {
        return array('id' => 0, 'name' => '', 'type' => 'number', 'options' => array());
    }

    if (!is_array($target) || !ws_wtable_total_type($target['type'] ?? '')) {
        return null;
    }

    return ($target['type'] === 'money') ? $target : array('id' => 0, 'name' => '', 'type' => 'number', 'options' => array());
}

/**
 * What a rollup is called on the columns card: its operation over the
 * other table, as "Sum of Orders › Amount" or "Count of Orders". Pure but
 * for lang().
 *
 * @param array      $column         the rollup
 * @param string     $table_name     the other table's name, '' when the reader does not read it
 * @param array|null $other_columns  its columns, null when the reader does not read it
 * @return string
 */
function ws_wtable_rollup_label($column, $table_name, $other_columns)
{
    $check = ws_wtable_rollup_options_check($column['options'] ?? array());

    if (!$check['ok'] || !is_array($other_columns)) {
        return lang('A table you do not read');
    }

    $rollup = $check['value'];
    $link = (string) ($other_columns[(string) $rollup['link']]['name'] ?? '?');
    $of = $table_name . ' (' . $link . ')';

    if ($rollup['op'] !== 'count') {
        $of .= ' › ' . (string) ($other_columns[(string) $rollup['column']]['name'] ?? '?');
    }

    $words = array('count' => 'Count of {var:1}', 'sum' => 'Sum of {var:1}', 'avg' => 'Average of {var:1}', 'min' => 'Smallest {var:1}', 'max' => 'Largest {var:1}');

    return lang(array('string' => $words[$rollup['op']], 'vars' => $of)) . (empty($rollup['where']) ? '' : ' · ' . lang('with conditions'));
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * The column each rollup's value is written by, for a reader: column id =>
 * column (ws_wtable_rollup_display()); a rollup of a table the reader does
 * not read is left out.
 *
 * @param array $viewer
 * @param array $columns column id => column
 * @return array
 */
function ws_wtable_rollup_targets($viewer, $columns)
{
    $out = array();

    foreach (ws_wtable_rollups($columns) as $column_id => $rollup) {
        if (!ws_wtable_link_readable($viewer, $rollup['table'])) {
            continue;
        }

        $display = ws_wtable_rollup_display($rollup, ws_wtable_lookup_columns_of($rollup['table'])[(string) $rollup['column']] ?? null);

        if ($display !== null) {
            $out[$column_id] = $display;
        }
    }

    return $out;
}

/**
 * Rows with their rollup cells worked out for a reader: one read of each
 * other table the reader reads, of its rows that link to these rows (as
 * ws_refs keeps the links; every row of the table before ws_refs takes the
 * source); their formulas worked out as a read of their table does. A
 * table without rollups reads nothing more.
 *
 * @param array   $viewer  who reads (a flow's owner for a flow)
 * @param array   $columns column id => column
 * @param array[] $rows    as read (ws_wtable_row_out())
 * @return array[]
 */
function ws_wtable_rollup_fill($viewer, $columns, $rows)
{
    $rollups = ws_wtable_rollups($columns);

    if (empty($rollups) || empty($rows)) {
        return $rows;
    }

    $by_refs = function_exists('ws_ref_sources_ready') && ws_ref_sources_ready();
    $sources = array();

    if ($by_refs) {
        foreach (ws_wtable_linking_rows(array_column((array) $rows, 'id')) as $ids) {
            foreach ($ids as $id) {
                $sources[(int) $id] = (int) $id;
            }
        }
    }

    $others = array();

    foreach (array_unique(array_column($rollups, 'table')) as $table_id) {
        if (!ws_wtable_link_readable($viewer, $table_id)) {
            continue;
        }

        $other_columns = ws_wtable_lookup_columns_of($table_id);
        $bound = ws_wtable_formulas_bound($other_columns);
        $read = array();

        foreach (($by_refs ? ws_wtable_link_rows($table_id, array_values($sources)) : ws_wtable_link_rows($table_id)) as $id => $row) {
            $cells = ws_wtable_cells_merge($other_columns, $row['cells'], array());

            if (!empty($bound)) {
                $cells = ws_wtable_formula_values($other_columns, $cells, $bound)['values'] + $cells;
            }

            $read[] = array('id' => (int) $id, 'cells' => $cells);
        }

        $others[$table_id] = array('columns' => $other_columns, 'rows' => $read);
    }

    return ws_wtable_rollup_values($columns, $rows, $others);
}

/**
 * Rows with their joined columns worked out for a reader: lookups, then
 * rollups.
 *
 * @param array   $viewer
 * @param array   $columns column id => column
 * @param array[] $rows    as read (ws_wtable_row_out())
 * @return array[]
 */
function ws_wtable_joined_fill($viewer, $columns, $rows)
{
    return ws_wtable_rollup_fill($viewer, $columns, ws_wtable_lookup_fill($viewer, $columns, $rows));
}

/**
 * The columns the joined cells are written by, for a reader: the lookups'
 * linked columns and the rollups' displays.
 *
 * @param array $viewer
 * @param array $columns column id => column
 * @return array column id => column
 */
function ws_wtable_joined_targets($viewer, $columns)
{
    return ws_wtable_lookup_targets($viewer, $columns) + ws_wtable_rollup_targets($viewer, $columns);
}

/* ---------------------------------------------------------------------------
   Making a rollup column
   --------------------------------------------------------------------------- */

/**
 * Why a rollup column is refused, null when it is not one or may be made
 * or changed so: the person reads the other table (rollup_table_unread),
 * its link column is a row link to this table (rollup_link_invalid), the
 * column totalled is one of its number, money or formula columns
 * (rollup_column_invalid), and the conditions fit its columns
 * (rollup_where_invalid).
 *
 * @param array $viewer
 * @param array $table
 * @param array $sets ws_wtable_column_input_check() sets, type given
 * @return array|null
 */
function ws_wtable_rollup_column_refusal($viewer, $table, $sets)
{
    if ((($sets['type'] ?? '') !== 'rollup') || !isset($sets['options'])) {
        return null;
    }

    $rollup = ws_wtable_rollup_options_check($sets['options']);

    if (!$rollup['ok']) {
        return ws_wtable_refusal($rollup['error'], 'options');
    }

    $rollup = $rollup['value'];

    if (!ws_wtable_link_readable($viewer, $rollup['table'])) {
        return ws_wtable_refusal('rollup_table_unread', 'options');
    }

    $other_columns = ws_wtable_columns($rollup['table']);

    if (ws_wtable_link_table($other_columns[(string) $rollup['link']] ?? array()) !== (int) $table['id']) {
        return ws_wtable_refusal('rollup_link_invalid', 'options');
    }

    if (($rollup['op'] !== 'count') && !ws_wtable_total_type($other_columns[(string) $rollup['column']]['type'] ?? '')) {
        return ws_wtable_refusal('rollup_column_invalid', 'options');
    }

    if (ws_wtable_query_refusal($other_columns, ws_wtable_query($rollup['where'], $rollup['match'])) !== '') {
        return ws_wtable_refusal('rollup_where_invalid', 'options');
    }

    return null;
}

/**
 * The links a new rollup may total over, for its form: each row link
 * column of a table the person reads that points at this table, with
 * that table's number, money and formula columns: "table:link:column" =>
 * "Orders (Customer) › Amount", "table:link:0" for the rows alone.
 *
 * @param array $viewer
 * @param int   $table_id this table
 * @return array
 */
function ws_wtable_rollup_choices($viewer, $table_id)
{
    $out = array();

    foreach (ws_wtables_reached($viewer, 'all') as $other) {
        $other_columns = ws_wtable_lookup_columns_of((int) $other['id']);

        foreach ($other_columns as $link_id => $link) {
            if (ws_wtable_link_table($link) !== (int) $table_id) {
                continue;
            }

            $of = (string) $other['name'] . ' (' . (string) $link['name'] . ')';
            $out[(int) $other['id'] . ':' . (int) $link_id . ':0'] = $of . ' › ' . lang('Rows');

            foreach ($other_columns as $target_id => $target) {
                if (ws_wtable_total_type($target['type'])) {
                    $out[(int) $other['id'] . ':' . (int) $link_id . ':' . (int) $target_id] = $of . ' › ' . (string) $target['name'];
                }
            }
        }
    }

    return $out;
}

/**
 * What a rollup is called on the columns card for a reader.
 *
 * @param array $viewer
 * @param array $column the rollup
 * @return string
 */
function ws_wtable_rollup_column_label($viewer, $column)
{
    $table_id = (int) ($column['options']['table'] ?? 0);

    if (!ws_wtable_link_readable($viewer, $table_id)) {
        return ws_wtable_rollup_label($column, '', null);
    }

    return ws_wtable_rollup_label($column, (string) (ws_wtable($table_id)['name'] ?? ''), ws_wtable_lookup_columns_of($table_id));
}

/**
 * Is a column type a joined column, worked out per page from another
 * table (a lookup or a rollup)? Conditions, sorts, totals and formulas,
 * which run over the row or every row before that, do not read one. Pure.
 *
 * @param string $type
 * @return bool
 */
function ws_wtable_joined_type($type)
{
    return in_array((string) $type, array('lookup', 'rollup'), true);
}
