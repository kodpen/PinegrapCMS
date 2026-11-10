<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the key column of a workflow table: a text or number column
 * a person makes the table's key, filled in and different on every row, as
 * a primary key is. A text key is compared without case (ws_fold()), a
 * number by its value. The store refuses a row whose key is empty or taken
 * (key_empty, duplicate_key), and refuses to make a column the key while it
 * holds empties or repeats, naming those rows. Checks and writes run under
 * a lock per table, so two rows written at once cannot take the same key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most rows a refusal to make a column the key names.
define('WS_WTABLE_KEY_ROWS_SHOWN', 20);

/**
 * The column types that may be a table's key. Pure.
 *
 * @return string[]
 */
function ws_wtable_key_types()
{
    return array('text', 'number');
}

/**
 * Is this column the table's key? Pure.
 *
 * @param array $column
 * @return bool
 */
function ws_wtable_is_key($column)
{
    return !empty($column['options']['key']) && in_array((string) ($column['type'] ?? ''), ws_wtable_key_types(), true);
}

/**
 * The table's key column, null when it has none. Pure.
 *
 * @param array $columns column id => column
 * @return array|null
 */
function ws_wtable_key_column($columns)
{
    foreach ((array) $columns as $column) {
        if (ws_wtable_is_key($column)) {
            return $column;
        }
    }

    return null;
}

/**
 * A cell as keys are compared: a text folded and trimmed, a number as its
 * value written plainly (12, 12.5); null for an empty cell. Pure.
 *
 * @param array $column
 * @param mixed $cell
 * @return string|null
 */
function ws_wtable_key_value($column, $cell)
{
    $value = ws_wtable_where_cell($column, $cell);

    if (($value === null) || ($value === '')) {
        return null;
    }

    if (is_int($value) || is_float($value)) {
        $text = rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');

        return ($text === '-0') ? '0' : $text;
    }

    return (string) $value;
}

/**
 * The rows that stop a column from being the key: those whose cell is
 * empty, and those whose value another row also holds, each by the
 * number the table shows. Pure.
 *
 * @param array   $column
 * @param array[] $rows label, cells
 * @return array empty, repeated (labels)
 */
function ws_wtable_key_faults($column, $rows)
{
    $id = (string) (int) ($column['id'] ?? 0);
    $empty = array();
    $seen = array();

    foreach ((array) $rows as $row) {
        $value = ws_wtable_key_value($column, ((array) ($row['cells'] ?? array()))[$id] ?? null);

        if ($value === null) {
            $empty[] = (int) $row['label'];
        } else {
            $seen[$value][] = (int) $row['label'];
        }
    }

    $repeated = array();

    foreach ($seen as $labels) {
        if (count($labels) > 1) {
            $repeated = array_merge($repeated, $labels);
        }
    }

    sort($repeated);

    return array('empty' => $empty, 'repeated' => $repeated);
}

/**
 * Why a row's key cell is refused: key_empty for no value, duplicate_key
 * for a value another row holds; '' when it fits. Pure.
 *
 * @param array   $column the key column
 * @param array[] $rows   id, cells: the table's rows
 * @param mixed   $cell   the row's key cell as it will be written
 * @param int     $row_id the row written, 0 for a new one
 * @return string
 */
function ws_wtable_key_clash($column, $rows, $cell, $row_id = 0)
{
    $value = ws_wtable_key_value($column, $cell);

    if ($value === null) {
        return 'key_empty';
    }

    $id = (string) (int) ($column['id'] ?? 0);

    foreach ((array) $rows as $row) {
        if (((int) $row['id'] !== (int) $row_id) && (ws_wtable_key_value($column, ((array) ($row['cells'] ?? array()))[$id] ?? null) === $value)) {
            return 'duplicate_key';
        }
    }

    return '';
}

/**
 * The refusal to make a column the key: key_not_unique (409) with the rows
 * at fault, at most WS_WTABLE_KEY_ROWS_SHOWN, and how many there are. Pure.
 *
 * @param array $faults ws_wtable_key_faults()
 * @return array|null null when no row is at fault
 */
function ws_wtable_key_refusal($faults)
{
    $rows = array_values(array_unique(array_merge($faults['empty'], $faults['repeated'])));

    if (empty($rows)) {
        return null;
    }

    sort($rows);

    return ws_wtable_refusal('key_not_unique', 'options', 409)
        + array('rows' => array_slice($rows, 0, WS_WTABLE_KEY_ROWS_SHOWN), 'rows_count' => count($rows), 'empty' => count($faults['empty']));
}

/* ---------------------------------------------------------------------------
   Reading and locking
   --------------------------------------------------------------------------- */

/**
 * A table's rows as keys are checked: id, the number it is shown by, and
 * its stored cells (a key column is never a formula, so no formula is
 * worked out).
 *
 * @param int $table_id
 * @return array[] id, label, cells
 */
function ws_wtable_key_rows($table_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "' ORDER BY position, id") as $record) {
        $out[] = array('id' => (int) $record['id'], 'label' => ws_wtable_row_label($record), 'cells' => ws_wtable_cells_decode($record['cells'] ?? null));
    }

    return $out;
}

/**
 * The row of a table whose key is this value, as stored, or null: none for
 * a table without a key column.
 *
 * @param int    $table_id
 * @param string $value
 * @return array|null
 */
function ws_wtable_row_keyed($table_id, $value)
{
    $column = ws_wtable_key_column(ws_wtable_columns((int) $table_id));
    $value = trim((string) $value);

    // A number key is given as a machine figure (12.5), as values of a flow
    // are; a figure in the site's own writing is read the site's way.
    if ($column && ($column['type'] === 'number')) {
        $value = is_numeric($value) ? (float) $value : ws_calc_number($value);
    }

    $wanted = $column ? ws_wtable_key_value($column, $value) : null;

    if ($wanted === null) {
        return null;
    }

    foreach (ws_wtable_key_rows((int) $table_id) as $row) {
        if (ws_wtable_key_value($column, $row['cells'][(string) $column['id']] ?? null) === $wanted) {
            return ws_wtable_row((int) $table_id, (int) $row['id']);
        }
    }

    return null;
}

/**
 * Take the table's key lock before a check and write that read the keys,
 * so two writes at once cannot both find a value free. Released by
 * ws_wtable_key_unlock(); a lock not had in five seconds lets the write
 * go on, checked as before.
 *
 * @param int $table_id
 * @return bool whether the lock was taken
 */
function ws_wtable_key_lock($table_id)
{
    return ((int) db_value("SELECT GET_LOCK('pg_wtable_key_" . (int) $table_id . "', 5)") === 1);
}

/**
 * Give the table's key lock back.
 *
 * @param int  $table_id
 * @param bool $taken ws_wtable_key_lock()
 * @return void
 */
function ws_wtable_key_unlock($table_id, $taken)
{
    if ($taken) {
        db("SELECT RELEASE_LOCK('pg_wtable_key_" . (int) $table_id . "')");
    }
}

/**
 * Why a row's cells are refused by the table's key, as the store's cell
 * refusal: '' for none, or the key column's code.
 *
 * @param int   $table_id
 * @param array $columns
 * @param array $cells  the row's cells as they will be written
 * @param int   $row_id 0 for a new row
 * @return array|null ws_wtable_cells_refusal(), null when it fits
 */
function ws_wtable_key_cells_refusal($table_id, $columns, $cells, $row_id = 0)
{
    $column = ws_wtable_key_column($columns);

    if ($column === null) {
        return null;
    }

    $code = ws_wtable_key_clash($column, ws_wtable_key_rows((int) $table_id), $cells[(string) $column['id']] ?? null, (int) $row_id);

    return ($code === '') ? null : ws_wtable_cells_refusal(array((string) $column['id'] => $code));
}
