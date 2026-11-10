<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the rows of workflow tables: adding, changing, deleting and
 * moving them, and reading them with their formula columns worked out.
 *
 * A row's cells are one JSON object by column id (ws_wtable_cells_encode()),
 * checked against the table's columns as they are when the row is written
 * (ws_wtable_row_cells_check()). A change names the revision of the row it
 * was made on and is written by one UPDATE … WHERE revision = ?, so a change
 * made meanwhile by somebody else is never overwritten: it is refused with
 * changed_meanwhile (409). The table's row_count moves with every add and
 * delete in the same statement that checks the 5000-row limit, so two adds
 * at once cannot pass it.
 *
 * Person and record-link cells are read in the rows' JSON by the privacy
 * export and erasure (pg_privacy_wtable_rows()): a cell naming a person is
 * handed over with their pack and emptied when they are erased. The records
 * a row's link cells point at are kept in ws_refs with every add, change and
 * delete (ws_ref_source_write()), so a record's screen finds the row.
 *
 * A formula column names other columns of its table by name (Price *
 * Quantity) and is worked out from the row's cells on every read, by
 * ws_calc_evaluate(); nothing of it is stored.
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
   Pure
   --------------------------------------------------------------------------- */

/**
 * Column types whose cells may not be filled yet. None: the privacy export
 * and erasure read person and record-link cells in the rows' JSON
 * (pg_privacy_wtable_rows()). A later type that holds personal data the
 * privacy paths cannot read yet is listed here until they can.
 *
 * @return string[]
 */
function ws_wtable_row_closed_types()
{
    return array();
}

/**
 * A row's cells as given by the person, checked: ws_wtable_row_cells_check()
 * with a cell of a closed type (ws_wtable_row_closed_types()) refused
 * (not_writable_yet) unless it empties the cell.
 *
 * @param array $columns column id => column
 * @param mixed $cells
 * @return array ok, cells, errors (column id => code)
 */
function ws_wtable_row_cells_input($columns, $cells)
{
    if (is_array($cells)) {
        $closed = array();

        foreach ($cells as $id => $value) {
            $type = $columns[(string) $id]['type'] ?? '';

            if (in_array($type, ws_wtable_row_closed_types(), true) && ($value !== null) && ($value !== '')) {
                $closed[(string) $id] = 'not_writable_yet';
            }
        }

        if (!empty($closed)) {
            return array('ok' => false, 'cells' => array(), 'errors' => $closed);
        }
    }

    return ws_wtable_row_cells_check($columns, $cells);
}

/**
 * A stored cell as a formula reads it: a number, money in whole units (1250
 * minor units with two decimals is 12.5), a checkbox 1 or 0, a list how many
 * items it holds, an empty cell nought. Text, a date, a status, a person or
 * a link is a value error; a lookup or a rollup is a ref error.
 *
 * @param array $column
 * @param mixed $value
 * @return float|string the number, or the error code
 */
function ws_wtable_formula_operand($column, $value)
{
    // A formula is worked out from the row's own cells, before the linked
    // rows of its lookups and rollups are read.
    if (in_array((string) ($column['type'] ?? ''), array('lookup', 'rollup'), true)) {
        return 'ref';
    }

    if (($value === null) || ($value === '')) {
        return 0.0;
    }

    switch ((string) $column['type']) {
        case 'number':
            return is_numeric($value) ? (float) $value : 'value';

        case 'money':
            $decimals = isset($column['options']['decimals']) ? (int) $column['options']['decimals'] : 2;

            return is_numeric($value) ? ((float) $value / pow(10, $decimals)) : 'value';

        case 'checkbox':
            return $value ? 1.0 : 0.0;

        case 'list':
            return is_array($value) ? (float) count($value) : 1.0;
    }

    return 'value';
}

/**
 * The formula columns of one row worked out. A formula names the other
 * columns by name; one formula may use another, a loop between them is a
 * cycle error. A value is rounded to 10 decimals so that 0.1 + 0.2 reads 0.3.
 *
 * @param array $columns column id => column (ws_wtable_columns())
 * @param array $cells   column id => stored value
 * @param array $bound   column id => expression with names bound; worked
 *                       out here when not given (ws_wtable_formulas_bound())
 * @return array values (column id => float|null), errors (column id => code)
 */
function ws_wtable_formula_values($columns, $cells, $bound = null)
{
    $bound = is_array($bound) ? $bound : ws_wtable_formulas_bound($columns);
    $values = array();
    $errors = array();
    $state = array();

    $work = null;
    $work = function ($id) use (&$work, &$values, &$errors, &$state, $columns, $cells, $bound) {
        if (($state[$id] ?? '') === 'done') {
            return isset($errors[$id]) ? $errors[$id] : $values[$id];
        }

        if (($state[$id] ?? '') === 'busy') {
            return 'cycle';
        }

        $state[$id] = 'busy';

        $outcome = ws_calc_evaluate((string) $bound[$id], function ($token) use (&$work, $columns, $cells, $bound) {
            if ($token[0] !== 'var') {
                return 'ref';
            }

            $used = (string) $token[1];

            if (!isset($columns[$used])) {
                return 'ref';
            }

            if (isset($bound[$used])) {
                return $work($used);
            }

            return ws_wtable_formula_operand($columns[$used], $cells[$used] ?? null);
        });

        $state[$id] = 'done';

        if ($outcome['ok'] && is_finite((float) $outcome['value'])) {
            $values[$id] = round((float) $outcome['value'], 10);

            return $values[$id];
        }

        $values[$id] = null;
        $errors[$id] = $outcome['ok'] ? 'value' : (string) $outcome['error'];

        return $errors[$id];
    };

    foreach (array_keys($bound) as $id) {
        $work((string) $id);
    }

    return array('values' => $values, 'errors' => $errors);
}

/**
 * The expression of every formula column with the table's column names put
 * in place (ws_calc_bind(), a column's id as its slot). Done once for a read
 * of many rows.
 *
 * @param array $columns column id => column
 * @return array column id => bound expression
 */
function ws_wtable_formulas_bound($columns)
{
    $known = array();

    foreach ($columns as $id => $column) {
        $known[ws_calc_fold((string) $column['name'])] = array('text' => (string) $column['name'], 'slot' => (int) $id);
    }

    $bound = array();

    foreach ($columns as $id => $column) {
        if ((string) $column['type'] === 'formula') {
            list($bound[(string) $id]) = ws_calc_bind((string) ($column['options']['expression'] ?? ''), $known);
        }
    }

    return $bound;
}

/**
 * A row as read: its stored cells of columns the table still has, the
 * formula values beside them, and the formula errors by column id.
 *
 * @param array      $record  the ws_wtable_rows row
 * @param array      $columns column id => column
 * @param array|null $bound   ws_wtable_formulas_bound()
 * @return array id, position, revision, cells, formula_errors, created_by, created_at, updated_by, updated_at
 */
function ws_wtable_row_out($record, $columns, $bound = null)
{
    $cells = ws_wtable_cells_merge($columns, ws_wtable_cells_decode($record['cells'] ?? null), array());
    $formulas = ws_wtable_formula_values($columns, $cells, $bound);

    foreach ($formulas['values'] as $id => $value) {
        $cells[$id] = $value;
    }

    return array(
        'id' => (int) $record['id'],
        'number' => (int) ($record['number'] ?? 0),
        'position' => (int) $record['position'],
        'revision' => (int) $record['revision'],
        'cells' => $cells,
        'formula_errors' => $formulas['errors'],
        'created_by' => (int) $record['created_by'],
        'created_at' => (int) $record['created_at'],
        'updated_by' => (int) $record['updated_by'],
        'updated_at' => (int) $record['updated_at'],
    );
}

/**
 * A row as it is handed to other software: cells and formula errors as
 * objects by column id (an empty one stays an object), the times as
 * ISO-8601 UTC.
 *
 * @param array $row ws_wtable_row_out()
 * @return array
 */
function ws_wtable_row_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'number' => (int) ($row['number'] ?? 0),
        'position' => (int) $row['position'],
        'revision' => (int) $row['revision'],
        'cells' => (object) $row['cells'],
        'formula_errors' => (object) $row['formula_errors'],
        'created_by' => (int) $row['created_by'],
        'created_at' => ws_flow_time($row['created_at']),
        'updated_by' => (int) $row['updated_by'],
        'updated_at' => ws_flow_time($row['updated_at']),
    );
}

/**
 * Moving a row from one position to another among $count rows (positions
 * 1..$count): where it lands, and the rows between that shift by one to
 * make room.
 *
 * @param int $from
 * @param int $to
 * @param int $count
 * @return array to, low, high, shift (+1 or -1; 0 when nothing moves)
 */
function ws_wtable_move_plan($from, $to, $count)
{
    $from = (int) $from;
    $to = max(1, min((int) $to, max(1, (int) $count)));

    if ($to < $from) {
        return array('to' => $to, 'low' => $to, 'high' => $from - 1, 'shift' => 1);
    }

    if ($to > $from) {
        return array('to' => $to, 'low' => $from + 1, 'high' => $to, 'shift' => -1);
    }

    return array('to' => $to, 'low' => 0, 'high' => 0, 'shift' => 0);
}

/**
 * The revision a change was made on, or 0 when none is given.
 *
 * @param mixed $input
 * @return int
 */
function ws_wtable_row_revision_given($input)
{
    $revision = is_array($input) ? ($input['revision'] ?? null) : null;

    return (ws_wtable_is_whole($revision) && ((int) $revision > 0)) ? (int) $revision : 0;
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * One row of a table as stored, or null.
 *
 * @param int $table_id
 * @param int $row_id
 * @return array|null
 */
function ws_wtable_row($table_id, $row_id)
{
    if (!ws_wtables_ready() || ((int) $row_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_rows WHERE id = '" . (int) $row_id . "' AND table_id = '" . (int) $table_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The row a table lists with this number (its position, 1 for the first
 * row), as stored, or null. Two rows added at once may share a number
 * (MAX(position) + 1 read by both): the first of them is given.
 *
 * @param int $table_id
 * @param int $position
 * @return array|null
 */
function ws_wtable_row_at($table_id, $position)
{
    if (!ws_wtables_ready() || ((int) $position <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "' AND position = '" . (int) $position . "' ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The row that holds this stable number in its table (1 for the first row
 * the table was given, never shifted by a delete), as stored, or null.
 *
 * @param int $table_id
 * @param int $number
 * @return array|null
 */
function ws_wtable_row_numbered($table_id, $number)
{
    if (!ws_wtable_numbers_ready() || ((int) $number <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "' AND number = '" . (int) $number . "' ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Take the table's next stable row number: the counter goes up by one in
 * a single statement, so two rows added at once never get the same number,
 * and a number once given is never given again (a row that then fails to
 * be written leaves a gap). 0 before the numbers are there.
 *
 * @param int $table_id
 * @return int
 */
function ws_wtable_number_take($table_id)
{
    if (!ws_wtable_numbers_ready() || ((int) $table_id <= 0)) {
        return 0;
    }

    db("UPDATE ws_wtables SET next_number = LAST_INSERT_ID(next_number + 1) WHERE id = '" . (int) $table_id . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return 0;
    }

    return (int) db_value("SELECT LAST_INSERT_ID()");
}

/**
 * The number a row is shown and found by: its stable number, or its place
 * before the numbers are there (a row binned before them restored without
 * one). Pure.
 *
 * @param array $row as stored or as read
 * @return int
 */
function ws_wtable_row_label($row)
{
    return ((int) ($row['number'] ?? 0) > 0) ? (int) $row['number'] : (int) ($row['position'] ?? 0);
}

/**
 * The number column of an INSERT into the rows or the bin, '' before the
 * numbers are there, so the statement fits the schema either way.
 *
 * @param int $number
 * @return string
 */
function ws_wtable_number_field($number)
{
    return ws_wtable_numbers_ready() ? ', number' : '';
}

/**
 * The number's value in that INSERT, '' before the numbers are there.
 *
 * @param int $number
 * @return string
 */
function ws_wtable_number_value($number)
{
    return ws_wtable_numbers_ready() ? ", '" . max(0, (int) $number) . "'" : '';
}

/**
 * The last row a table lists, as stored, or null for a table without rows.
 *
 * @param int $table_id
 * @return array|null
 */
function ws_wtable_row_last($table_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "' ORDER BY position DESC, id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The distinct text values the rows of a table hold in one column, as
 * stored: a status column's keys, read before its choices change so a new
 * choice never takes a key a row still holds.
 *
 * @param int $table_id
 * @param int $column_id
 * @return string[]
 */
function ws_wtable_column_held_values($table_id, $column_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0) || ((int) $column_id <= 0)) {
        return array();
    }

    $held = array();

    foreach ((array) db_items("SELECT cells FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "'") as $record) {
        $cells = ws_wtable_cells_decode($record['cells'] ?? null);
        $value = $cells[(string) (int) $column_id] ?? null;

        if (is_string($value) && ($value !== '')) {
            $held[$value] = true;
        }
    }

    return array_map('strval', array_keys($held));
}

/**
 * The rows of a table in order, as read (ws_wtable_row_out()). The 5000-row
 * limit bounds the read.
 *
 * @param int        $table_id
 * @param array|null $columns ws_wtable_columns(), read when not given
 * @return array[]
 */
function ws_wtable_rows($table_id, $columns = null)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return array();
    }

    $columns = is_array($columns) ? $columns : ws_wtable_columns($table_id);
    $bound = ws_wtable_formulas_bound($columns);
    $rows = array();

    foreach ((array) db_items("SELECT * FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "' ORDER BY position, id") as $record) {
        $rows[] = ws_wtable_row_out($record, $columns, $bound);
    }

    return $rows;
}

/**
 * Where the rows after the last row of a page begin, as SQL: past its
 * position, or, given its id too, past it in (position, id) order. Two rows
 * added at the same moment can share a position; by position alone the
 * second of them would be skipped when the page ended on the first. Pure.
 *
 * @param int $after    the last row's position, 0 for the first page
 * @param int $after_id the last row's id, 0 when not known
 * @return string
 */
function ws_wtable_rows_after_sql($after, $after_id = 0)
{
    $after = max(0, (int) $after);
    $after_id = max(0, (int) $after_id);

    if (($after === 0) || ($after_id === 0)) {
        return "position > '" . $after . "'";
    }

    return "(position > '" . $after . "' OR (position = '" . $after . "' AND id > '" . $after_id . "'))";
}

/**
 * One page of a table's rows in order, as read: the rows after a position,
 * and the position the next page starts after (null on the last page).
 *
 * @param int   $table_id
 * @param array $columns ws_wtable_columns()
 * @param int   $after    the position of the last row of the page before, 0 for the first
 * @param int   $limit    1-250
 * @param int   $after_id the id of that row, 0 when not known
 * @return array rows (ws_wtable_row_out()), next
 */
function ws_wtable_rows_page($table_id, $columns, $after, $limit, $after_id = 0)
{
    $limit = max(1, min(250, (int) $limit));

    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return array('rows' => array(), 'next' => null);
    }

    $records = (array) db_items("SELECT * FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "'
        AND " . ws_wtable_rows_after_sql($after, $after_id) . " ORDER BY position, id LIMIT " . ($limit + 1));
    $more = (count($records) > $limit);
    $bound = ws_wtable_formulas_bound($columns);
    $rows = array();

    foreach (array_slice($records, 0, $limit) as $record) {
        $rows[] = ws_wtable_row_out($record, $columns, $bound);
    }

    return array('rows' => $rows, 'next' => ($more && !empty($rows)) ? (int) $rows[count($rows) - 1]['position'] : null);
}

/* ---------------------------------------------------------------------------
   Writing
   --------------------------------------------------------------------------- */

/**
 * The refusal of a row's cells, with each refused column's code.
 *
 * @param array $errors column id => code
 * @return array
 */
function ws_wtable_cells_refusal($errors)
{
    return ws_wtable_refusal('cells_invalid', 'cells') + array('errors' => $errors);
}

/**
 * Mark a table changed by a row write, so that it comes first among its
 * owner's tables.
 *
 * @param int $table_id
 */
function ws_wtable_touch($table_id)
{
    db("UPDATE ws_wtables SET updated_at = '" . time() . "' WHERE id = '" . (int) $table_id . "'");
}

/**
 * May the person add a row to a table, and are its cells right?
 *
 * @param array      $viewer
 * @param array|null $table ws_wtable()
 * @param mixed      $input cells (column id => value)
 * @return array ok, cells, error, field, status (errors on cells_invalid)
 */
function ws_wtable_row_create_check($viewer, $table, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write')) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $cells = ws_wtable_row_cells_input($columns, is_array($input) ? ($input['cells'] ?? array()) : null);

    if (!$cells['ok']) {
        return ws_wtable_cells_refusal($cells['errors']);
    }

    $key = ws_wtable_key_cells_refusal((int) $table['id'], $columns, ws_wtable_cells_merge($columns, array(), $cells['cells']));

    if ($key === null) {
        $key = ws_wtable_link_cells_refusal($viewer, $columns, $cells['cells']);
    }

    if ($key !== null) {
        return $key;
    }

    if (!ws_wtable_limit_allows('rows', (int) $table['row_count'])) {
        return ws_wtable_refusal('too_many_rows', '', 409);
    }

    return array('ok' => true, 'cells' => $cells['cells'], 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Add a row at the end of a table. The row is counted first, by the same
 * statement that checks the limit, and the count given back when the row
 * cannot be written.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param mixed      $input cells
 * @return array ok, error, field, status, row_id, revision
 */
function ws_wtable_row_create($viewer, $table, $input)
{
    // Checked and written under the table's key lock when it has a key, so
    // two writes at once cannot both find a key free.
    $table_id = is_array($table) ? (int) $table['id'] : 0;
    $taken = (($table_id > 0) && (ws_wtable_key_column(ws_wtable_columns($table_id)) !== null)) ? ws_wtable_key_lock($table_id) : false;

    try {
        return ws_wtable_row_create_write($viewer, $table, $input);
    } finally {
        ws_wtable_key_unlock($table_id, $taken);
    }
}

/**
 * The write of ws_wtable_row_create(), under its lock.
 *
 * @return array
 */
function ws_wtable_row_create_write($viewer, $table, $input)
{
    $check = ws_wtable_row_create_check($viewer, $table, $input);

    if (!$check['ok']) {
        return $check + array('row_id' => 0, 'revision' => 0);
    }

    $table_id = (int) $table['id'];
    $now = time();

    db("UPDATE ws_wtables SET row_count = row_count + 1, updated_at = '" . $now . "'
        WHERE id = '" . $table_id . "' AND row_count < '" . (int) ws_wtable_limits()['rows'] . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return ws_wtable_refusal('too_many_rows', '', 409) + array('row_id' => 0, 'revision' => 0);
    }

    $columns = ws_wtable_columns($table_id);
    $cells = ws_wtable_cells_merge($columns, array(), $check['cells']);
    $position = (int) db_value("SELECT COALESCE(MAX(position), 0) FROM ws_wtable_rows WHERE table_id = '" . $table_id . "'") + 1;
    $number = ws_wtable_number_take($table_id);

    db("INSERT INTO ws_wtable_rows (table_id, position, cells, revision, created_by, created_at, updated_by, updated_at" . ws_wtable_number_field($number) . ")
        VALUES ('" . $table_id . "', '" . $position . "', '" . e(ws_wtable_cells_encode($cells)) . "', 1,
            '" . (int) $viewer['id'] . "', '" . $now . "', '" . (int) $viewer['id'] . "', '" . $now . "'" . ws_wtable_number_value($number) . ")");

    $row_id = (int) mysqli_insert_id(db::$con);

    if ($row_id <= 0) {
        db("UPDATE ws_wtables SET row_count = IF(row_count > 0, row_count - 1, 0) WHERE id = '" . $table_id . "'");

        return ws_wtable_refusal('not_saved', '', 500) + array('row_id' => 0, 'revision' => 0);
    }

    ws_ref_source_write('wtable_row', array($row_id => ws_wtable_row_ref_tokens($columns, $cells)));
    ws_wtable_row_announce('created', $table, $row_id, (int) $viewer['id']);

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 201, 'row_id' => $row_id, 'revision' => 1);
}

/**
 * May the person change a row, made on the revision they name, and are the
 * cells right? A row changed since is refused (changed_meanwhile, 409, with
 * the row's revision now).
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row   ws_wtable_row()
 * @param mixed      $input cells, revision
 * @return array ok, cells, error, field, status
 */
function ws_wtable_row_update_check($viewer, $table, $row, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($row)) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $revision = ws_wtable_row_revision_given($input);

    if ($revision === 0) {
        return ws_wtable_refusal('revision_missing', 'revision');
    }

    if ($revision !== (int) $row['revision']) {
        return ws_wtable_refusal('changed_meanwhile', 'revision', 409) + array('revision' => (int) $row['revision']);
    }

    if (empty($input['cells'])) {
        return ws_wtable_refusal('nothing_to_change', 'cells');
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $cells = ws_wtable_row_cells_input($columns, $input['cells']);

    if (!$cells['ok']) {
        return ws_wtable_cells_refusal($cells['errors']);
    }

    $key = ws_wtable_key_cells_refusal((int) $table['id'], $columns,
        ws_wtable_cells_merge($columns, ws_wtable_cells_decode($row['cells'] ?? null), $cells['cells']), (int) $row['id']);

    if ($key === null) {
        $key = ws_wtable_link_cells_refusal($viewer, $columns, $cells['cells'], ws_wtable_cells_decode($row['cells'] ?? null));
    }

    if ($key !== null) {
        return $key;
    }

    return array('ok' => true, 'cells' => $cells['cells'], 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Change a row's cells: the given ones laid over what it has, written only
 * while the row is still at the revision the change was made on.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row
 * @param mixed      $input cells, revision
 * @return array ok, error, field, status, revision
 */
function ws_wtable_row_update($viewer, $table, $row, $input)
{
    // Checked and written under the table's key lock when it has a key, so
    // two writes at once cannot both find a key free.
    $table_id = is_array($table) ? (int) $table['id'] : 0;
    $taken = (($table_id > 0) && (ws_wtable_key_column(ws_wtable_columns($table_id)) !== null)) ? ws_wtable_key_lock($table_id) : false;

    try {
        return ws_wtable_row_update_write($viewer, $table, $row, $input);
    } finally {
        ws_wtable_key_unlock($table_id, $taken);
    }
}

/**
 * The write of ws_wtable_row_update(), under its lock.
 *
 * @return array
 */
function ws_wtable_row_update_write($viewer, $table, $row, $input)
{
    $check = ws_wtable_row_update_check($viewer, $table, $row, $input);

    if (!$check['ok']) {
        return $check + array('revision' => (int) ($row['revision'] ?? 0));
    }

    $table_id = (int) $table['id'];
    $revision = (int) $row['revision'];
    $columns = ws_wtable_columns($table_id);
    $cells = ws_wtable_cells_merge($columns, ws_wtable_cells_decode($row['cells']), $check['cells']);

    db("UPDATE ws_wtable_rows SET cells = '" . e(ws_wtable_cells_encode($cells)) . "', revision = revision + 1,
            updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND table_id = '" . $table_id . "' AND revision = '" . $revision . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        $now = ws_wtable_row($table_id, (int) $row['id']);

        if (!is_array($now)) {
            return ws_wtable_refusal('not_found', '', 404) + array('revision' => 0);
        }

        return ws_wtable_refusal('changed_meanwhile', 'revision', 409) + array('revision' => (int) $now['revision']);
    }

    ws_ref_source_write('wtable_row', array((int) $row['id'] => ws_wtable_row_ref_tokens($columns, $cells)));
    ws_wtable_touch($table_id);
    ws_wtable_row_announce('updated', $table, (int) $row['id'], (int) $viewer['id'], array_keys($check['cells']));

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200, 'revision' => $revision + 1);
}

/**
 * May the person delete a row? A revision, when given, must still be the
 * row's: a row changed since is not deleted unseen.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row
 * @param mixed      $input revision (optional)
 * @return array ok, error, field, status
 */
function ws_wtable_row_delete_check($viewer, $table, $row, $input = array())
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($row)) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $revision = ws_wtable_row_revision_given($input);

    if (($revision !== 0) && ($revision !== (int) $row['revision'])) {
        return ws_wtable_refusal('changed_meanwhile', 'revision', 409) + array('revision' => (int) $row['revision']);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Delete a row: it goes to the table's bin with its links
 * (ws_wtable_row_bin_put(), wtable_bin.php), the rows after it move up one,
 * and the table counts one fewer, only when this request is the one that
 * removed it. The copy is written first, so that a request stopped halfway
 * never loses the row; a copy whose row this request did not remove is
 * taken out again. The row links of other rows to it are cleared
 * (ws_wtable_links_clear()), links_cleared counting those rows.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row
 * @param mixed      $input revision (optional)
 * @return array ok, error, field, status, links_cleared
 */
function ws_wtable_row_delete($viewer, $table, $row, $input = array())
{
    $check = ws_wtable_row_delete_check($viewer, $table, $row, $input);

    if (!$check['ok']) {
        return $check;
    }

    $table_id = (int) $table['id'];
    $where = "id = '" . (int) $row['id'] . "' AND table_id = '" . $table_id . "'";

    if (ws_wtable_row_revision_given($input) !== 0) {
        $where .= " AND revision = '" . (int) $row['revision'] . "'";
    }

    $stored = ws_wtable_row($table_id, (int) $row['id']);
    $bin_id = is_array($stored) ? ws_wtable_row_bin_put($stored, (int) $viewer['id']) : 0;

    db("DELETE FROM ws_wtable_rows WHERE " . $where);

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        ws_wtable_row_bin_drop($bin_id);

        return is_array(ws_wtable_row($table_id, (int) $row['id']))
            ? ws_wtable_refusal('changed_meanwhile', 'revision', 409)
            : ws_wtable_refusal('not_found', '', 404);
    }

    db("UPDATE ws_wtable_rows SET position = position - 1 WHERE table_id = '" . $table_id . "' AND position > '" . (int) $row['position'] . "'");
    db("UPDATE ws_wtables SET row_count = IF(row_count > 0, row_count - 1, 0), updated_at = '" . time() . "' WHERE id = '" . $table_id . "'");
    ws_ref_source_write('wtable_row', array((int) $row['id'] => array()));
    $check['links_cleared'] = ws_wtable_links_clear($table_id, (int) $row['id'], (int) $viewer['id']);
    ws_wtable_row_announce('deleted', $table, $row, (int) $viewer['id']);

    return $check;
}

/**
 * May the person move a row, and where to? The place is a position among
 * the table's rows, 1 the first; one past the end lands last.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row
 * @param mixed      $input position
 * @return array ok, position, error, field, status
 */
function ws_wtable_row_move_check($viewer, $table, $row, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($row)) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $position = is_array($input) ? ($input['position'] ?? null) : null;

    if (!ws_wtable_is_whole($position) || ((int) $position <= 0)) {
        return ws_wtable_refusal('position_invalid', 'position');
    }

    return array('ok' => true, 'position' => (int) $position, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Move a row to another place: the rows between move one place to make room.
 * The row keeps its revision, its cells being what they were.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row
 * @param mixed      $input position
 * @return array ok, error, field, status, position
 */
function ws_wtable_row_move($viewer, $table, $row, $input)
{
    $check = ws_wtable_row_move_check($viewer, $table, $row, $input);

    if (!$check['ok']) {
        return $check + array('position' => 0);
    }

    $table_id = (int) $table['id'];
    $count = (int) db_value("SELECT COUNT(*) FROM ws_wtable_rows WHERE table_id = '" . $table_id . "'");
    $plan = ws_wtable_move_plan((int) $row['position'], $check['position'], $count);

    if ($plan['shift'] !== 0) {
        db("UPDATE ws_wtable_rows SET position = position + (" . (int) $plan['shift'] . ")
            WHERE table_id = '" . $table_id . "' AND position BETWEEN '" . (int) $plan['low'] . "' AND '" . (int) $plan['high'] . "' AND id <> '" . (int) $row['id'] . "'");
        db("UPDATE ws_wtable_rows SET position = '" . (int) $plan['to'] . "' WHERE id = '" . (int) $row['id'] . "' AND table_id = '" . $table_id . "'");
        ws_wtable_touch($table_id);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200, 'position' => $plan['to']);
}
