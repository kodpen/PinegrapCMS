<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - lookup columns of workflow tables, as a join reads a column
 * of another table: a column of type lookup names a row link column of its
 * own table (options.link) and a column of the linked table
 * (options.column), and shows that column of the linked row. Nothing is
 * stored: the value is worked out when the table is read, for whoever
 * reads it, one read of each linked table per page. A reader who does not
 * read the linked table sees an empty cell; a flow reads with its owner's
 * rights.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Where the screen's people list carries the column each lookup shows, so
// that the list, the board and the download write its value by that
// column's type. A user id is never a string with a NUL.
define('WS_WTABLE_LOOKUPS_KEY', "\0lookups");

/* ---------------------------------------------------------------------------
   Rules (pure)
   --------------------------------------------------------------------------- */

/**
 * The row link column and the linked table's column a lookup names, both 0
 * for any other column. Pure.
 *
 * @param array $column
 * @return int[] link, column
 */
function ws_wtable_lookup_parts($column)
{
    if ((($column['type'] ?? '') !== 'lookup') || !ws_wtable_is_whole($column['options']['link'] ?? null) || !ws_wtable_is_whole($column['options']['column'] ?? null)) {
        return array(0, 0);
    }

    return array((int) $column['options']['link'], (int) $column['options']['column']);
}

/**
 * Whether a lookup may show a column of this type: any but a row link and
 * another lookup, so a lookup reads one linked row and stops there. Pure.
 *
 * @param string $type
 * @return bool
 */
function ws_wtable_lookup_shows($type)
{
    return isset(ws_wtable_column_types()[(string) $type]) && !in_array((string) $type, array('row', 'lookup'), true);
}

/**
 * The lookup columns of a table, each with the table its row link points
 * at: column id => link, column, table. A lookup whose row link column is
 * gone or is no row link is left out. Pure.
 *
 * @param array $columns column id => column
 * @return array[]
 */
function ws_wtable_lookups($columns)
{
    $out = array();

    foreach ((array) $columns as $column_id => $column) {
        list($link, $target) = ws_wtable_lookup_parts($column);
        $table_id = ($link > 0) ? ws_wtable_link_table($columns[(string) $link] ?? array()) : 0;

        if ($table_id > 0) {
            $out[(string) $column_id] = array('link' => $link, 'column' => $target, 'table' => $table_id);
        }
    }

    return $out;
}

/**
 * Rows with their lookup cells filled from the linked rows. A linked table
 * missing from $linked (one the reader does not read), a linked row that
 * is not there and a column the linked table no longer has, or one a
 * lookup may not show, leave the cell empty. Pure.
 *
 * @param array   $columns column id => column
 * @param array[] $rows    as read (ws_wtable_row_out()): cells by column id
 * @param array   $linked  table id => columns (column id => column), cells (row id => cells, formulas worked out)
 * @return array[]
 */
function ws_wtable_lookup_values($columns, $rows, $linked)
{
    $lookups = ws_wtable_lookups($columns);

    foreach ((array) $rows as $place => $row) {
        foreach ($lookups as $column_id => $lookup) {
            unset($rows[$place]['cells'][$column_id]);
            $table = $linked[$lookup['table']] ?? null;
            $target = is_array($table) ? ($table['columns'][(string) $lookup['column']] ?? null) : null;
            $row_id = $row['cells'][(string) $lookup['link']] ?? null;

            if (!is_array($target) || !ws_wtable_lookup_shows($target['type'] ?? '') || !ws_wtable_is_whole($row_id)) {
                continue;
            }

            $value = $table['cells'][(int) $row_id][(string) $lookup['column']] ?? null;

            if (($value !== null) && ($value !== false)) {
                $rows[$place]['cells'][$column_id] = $value;
            }
        }
    }

    return $rows;
}

/**
 * What a lookup is called on the columns card: the row link column's name
 * and the linked column's, as Customer › Phone. Pure but for lang().
 *
 * @param array      $column         the lookup
 * @param array      $columns        its table's columns
 * @param array|null $linked_columns the linked table's, null when the reader does not read it
 * @return string
 */
function ws_wtable_lookup_label($column, $columns, $linked_columns)
{
    list($link, $target) = ws_wtable_lookup_parts($column);
    $first = (string) ($columns[(string) $link]['name'] ?? '?');

    if (!is_array($linked_columns)) {
        return $first . ' › ' . lang('A table you do not read');
    }

    return $first . ' › ' . (string) ($linked_columns[(string) $target]['name'] ?? '?');
}

/**
 * A people list (ws_people()) that carries the column each lookup shows,
 * for ws_wtable_screen_cell_text(). Pure.
 *
 * @param array $people
 * @param array $targets column id => the linked column (ws_wtable_lookup_targets())
 * @return array
 */
function ws_wtable_people_with_lookups($people, $targets)
{
    $people = (array) $people;
    $people[WS_WTABLE_LOOKUPS_KEY] = (array) $targets;

    return $people;
}

/**
 * The linked column a lookup cell is written by, null when there is none
 * to the reader. Pure.
 *
 * @param array $column the lookup
 * @param array $people ws_wtable_people_with_lookups()
 * @return array|null
 */
function ws_wtable_lookup_target_of($column, $people)
{
    $targets = (isset($people[WS_WTABLE_LOOKUPS_KEY]) && is_array($people[WS_WTABLE_LOOKUPS_KEY])) ? $people[WS_WTABLE_LOOKUPS_KEY] : array();
    $target = $targets[(string) ($column['id'] ?? '')] ?? null;

    return (is_array($target) && ws_wtable_lookup_shows($target['type'] ?? '')) ? $target : null;
}

/**
 * The people that the lookup cells of rows name, through a linked person
 * column. Pure.
 *
 * @param array   $targets column id => the linked column
 * @param array[] $rows    filled (ws_wtable_lookup_fill())
 * @return int[]
 */
function ws_wtable_lookup_person_ids($targets, $rows)
{
    $ids = array();

    foreach ((array) $targets as $column_id => $target) {
        if (($target['type'] ?? '') !== 'person') {
            continue;
        }

        foreach ((array) $rows as $row) {
            if (!empty($row['cells'][(string) $column_id]) && ws_wtable_is_whole($row['cells'][(string) $column_id])) {
                $ids[] = (int) $row['cells'][(string) $column_id];
            }
        }
    }

    return $ids;
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * The columns of a linked table, read once a request for each.
 *
 * @param int $table_id
 * @return array column id => column
 */
function ws_wtable_lookup_columns_of($table_id)
{
    static $seen = array();

    if (!isset($seen[(int) $table_id])) {
        $seen[(int) $table_id] = ws_wtable_columns((int) $table_id);
    }

    return $seen[(int) $table_id];
}

/**
 * The linked column each lookup shows, for a reader: column id => column;
 * a lookup of a table the reader does not read, or whose column is gone,
 * is left out.
 *
 * @param array $viewer
 * @param array $columns column id => column
 * @return array
 */
function ws_wtable_lookup_targets($viewer, $columns)
{
    $out = array();

    foreach (ws_wtable_lookups($columns) as $column_id => $lookup) {
        if (!ws_wtable_link_readable($viewer, $lookup['table'])) {
            continue;
        }

        $target = ws_wtable_lookup_columns_of($lookup['table'])[(string) $lookup['column']] ?? null;

        if (is_array($target) && ws_wtable_lookup_shows($target['type'])) {
            $out[$column_id] = $target;
        }
    }

    return $out;
}

/**
 * Rows with their lookup cells worked out for a reader: one read of each
 * linked table the reader reads, of the rows these rows link to; a linked
 * formula is worked out as a read of its table does. A table without
 * lookups reads nothing more.
 *
 * @param array   $viewer  who reads (a flow's owner for a flow)
 * @param array   $columns column id => column
 * @param array[] $rows    as read (ws_wtable_row_out())
 * @return array[]
 */
function ws_wtable_lookup_fill($viewer, $columns, $rows)
{
    $lookups = ws_wtable_lookups($columns);

    if (empty($lookups) || empty($rows)) {
        return $rows;
    }

    $wanted = array();

    foreach ($lookups as $lookup) {
        foreach ((array) $rows as $row) {
            $id = $row['cells'][(string) $lookup['link']] ?? null;

            if (ws_wtable_is_whole($id) && ((int) $id > 0)) {
                $wanted[$lookup['table']][(int) $id] = true;
            }
        }
    }

    $linked = array();

    foreach ($wanted as $table_id => $ids) {
        if (!ws_wtable_link_readable($viewer, $table_id)) {
            continue;
        }

        $linked_columns = ws_wtable_lookup_columns_of($table_id);
        $bound = ws_wtable_formulas_bound($linked_columns);
        $cells = array();

        foreach (ws_wtable_link_rows($table_id, array_keys($ids)) as $id => $row) {
            $kept = ws_wtable_cells_merge($linked_columns, $row['cells'], array());

            if (!empty($bound)) {
                $kept = ws_wtable_formula_values($linked_columns, $kept, $bound)['values'] + $kept;
            }

            $cells[(int) $id] = $kept;
        }

        $linked[$table_id] = array('columns' => $linked_columns, 'cells' => $cells);
    }

    return ws_wtable_lookup_values($columns, $rows, $linked);
}

/* ---------------------------------------------------------------------------
   Making a lookup column
   --------------------------------------------------------------------------- */

/**
 * Why a lookup column is refused, null when it is not one or may be made
 * or changed so: its link is a row link column of the table
 * (lookup_link_invalid), the person reads the linked table
 * (lookup_table_unread), and its column is one of that table's that a
 * lookup may show (lookup_column_invalid).
 *
 * @param array $viewer
 * @param array $table
 * @param array $sets ws_wtable_column_input_check() sets, type given
 * @return array|null
 */
function ws_wtable_lookup_column_refusal($viewer, $table, $sets)
{
    if ((($sets['type'] ?? '') !== 'lookup') || !isset($sets['options'])) {
        return null;
    }

    $probe = array('type' => 'lookup', 'options' => $sets['options']);
    list($link, $target) = ws_wtable_lookup_parts($probe);
    $columns = ws_wtable_columns((int) $table['id']);
    $table_id = ws_wtable_link_table($columns[(string) $link] ?? array());

    if ($table_id <= 0) {
        return ws_wtable_refusal('lookup_link_invalid', 'options');
    }

    if (!ws_wtable_link_readable($viewer, $table_id)) {
        return ws_wtable_refusal('lookup_table_unread', 'options');
    }

    $shown = ws_wtable_columns($table_id)[(string) $target] ?? null;

    return (is_array($shown) && ws_wtable_lookup_shows($shown['type'])) ? null : ws_wtable_refusal('lookup_column_invalid', 'options');
}

/**
 * The columns a new lookup may show, for its form: each row link column of
 * the table whose linked table the person reads, with that table's columns
 * a lookup may show: "link:column" => "Customer › Phone".
 *
 * @param array $viewer
 * @param array $columns column id => column
 * @return array
 */
function ws_wtable_lookup_choices($viewer, $columns)
{
    $out = array();

    foreach ((array) $columns as $link_id => $column) {
        $table_id = ws_wtable_link_table($column);

        if (($table_id <= 0) || !ws_wtable_link_readable($viewer, $table_id)) {
            continue;
        }

        foreach (ws_wtable_lookup_columns_of($table_id) as $target_id => $target) {
            if (ws_wtable_lookup_shows($target['type'])) {
                $out[(int) $link_id . ':' . (int) $target_id] = (string) $column['name'] . ' › ' . (string) $target['name'];
            }
        }
    }

    return $out;
}

/**
 * What a lookup is called on the columns card for a reader, as Customer ›
 * Phone (ws_wtable_lookup_label()).
 *
 * @param array $viewer
 * @param array $column  the lookup
 * @param array $columns its table's columns
 * @return string
 */
function ws_wtable_lookup_column_label($viewer, $column, $columns)
{
    $lookup = ws_wtable_lookups(array((string) ($column['id'] ?? 0) => $column) + (array) $columns)[(string) ($column['id'] ?? 0)] ?? null;

    if ($lookup === null) {
        return ws_wtable_lookup_label($column, $columns, array());
    }

    return ws_wtable_lookup_label($column, $columns, ws_wtable_link_readable($viewer, $lookup['table']) ? ws_wtable_lookup_columns_of($lookup['table']) : null);
}
