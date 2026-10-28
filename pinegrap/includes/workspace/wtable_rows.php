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
 * Person and record-link cells are not written yet: the privacy export and
 * erasure cannot read them in the rows' JSON, and a person's name or record
 * kept where those paths do not look could neither be handed over nor
 * erased. Emptying such a cell is allowed.
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
 * Column types whose cells may not be filled yet: they name a person or a
 * record that the privacy export and erasure do not find in the rows' JSON.
 *
 * @return string[]
 */
function ws_wtable_row_closed_types()
{
    return array('person', 'link');
}

/**
 * A row's cells as given by the person, checked: ws_wtable_row_cells_check()
 * with a person or record-link cell refused (not_writable_yet) unless it
 * empties the cell.
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
 * minor units with two decimals is 12.5), a checkbox 1 or 0, an empty cell
 * nought. Text, a date, a status, a person or a link is a value error.
 *
 * @param array $column
 * @param mixed $value
 * @return float|string the number, or the error code
 */
function ws_wtable_formula_operand($column, $value)
{
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

    $cells = ws_wtable_row_cells_input(ws_wtable_columns((int) $table['id']), is_array($input) ? ($input['cells'] ?? array()) : null);

    if (!$cells['ok']) {
        return ws_wtable_cells_refusal($cells['errors']);
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

    db("INSERT INTO ws_wtable_rows (table_id, position, cells, revision, created_by, created_at, updated_by, updated_at)
        VALUES ('" . $table_id . "', '" . $position . "', '" . e(ws_wtable_cells_encode($cells)) . "', 1,
            '" . (int) $viewer['id'] . "', '" . $now . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

    $row_id = (int) mysqli_insert_id(db::$con);

    if ($row_id <= 0) {
        db("UPDATE ws_wtables SET row_count = IF(row_count > 0, row_count - 1, 0) WHERE id = '" . $table_id . "'");

        return ws_wtable_refusal('not_saved', '', 500) + array('row_id' => 0, 'revision' => 0);
    }

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

    $cells = ws_wtable_row_cells_input(ws_wtable_columns((int) $table['id']), $input['cells']);

    if (!$cells['ok']) {
        return ws_wtable_cells_refusal($cells['errors']);
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
    $check = ws_wtable_row_update_check($viewer, $table, $row, $input);

    if (!$check['ok']) {
        return $check + array('revision' => (int) ($row['revision'] ?? 0));
    }

    $table_id = (int) $table['id'];
    $revision = (int) $row['revision'];
    $cells = ws_wtable_cells_merge(ws_wtable_columns($table_id), ws_wtable_cells_decode($row['cells']), $check['cells']);

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

    ws_wtable_touch($table_id);

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
 * Delete a row: the rows after it move up one, and the table counts one
 * fewer, only when this request is the one that removed it.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $row
 * @param mixed      $input revision (optional)
 * @return array ok, error, field, status
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

    db("DELETE FROM ws_wtable_rows WHERE " . $where);

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return is_array(ws_wtable_row($table_id, (int) $row['id']))
            ? ws_wtable_refusal('changed_meanwhile', 'revision', 409)
            : ws_wtable_refusal('not_found', '', 404);
    }

    db("UPDATE ws_wtable_rows SET position = position - 1 WHERE table_id = '" . $table_id . "' AND position > '" . (int) $row['position'] . "'");
    db("UPDATE ws_wtables SET row_count = IF(row_count > 0, row_count - 1, 0), updated_at = '" . time() . "' WHERE id = '" . $table_id . "'");

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
