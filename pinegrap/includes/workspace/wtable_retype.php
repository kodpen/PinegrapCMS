<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - changing the type of a workflow table column, as ALTER ...
 * MODIFY changes one: text, number, money, date, list and checkbox turn into
 * one another, and a list of texts into a list of numbers and back. Every
 * cell is converted to the new type and checked as a write checks it
 * (ws_wtable_cell_check()); while any cell does not convert the change is
 * refused, and the refusal lists each such cell, so the same check is the
 * dry run. Binned rows are converted too, since a restore brings their
 * cells back as they are kept.
 *
 * The first part is pure: the types that change, one cell converted, the
 * cells of a table's rows converted or listed. The second reads and writes
 * the table in one transaction, its rows locked while they are converted.
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
 * The column types a type change takes, from and to.
 *
 * @return string[]
 */
function ws_wtable_retype_types()
{
    return array('text', 'number', 'money', 'date', 'list', 'checkbox');
}

/**
 * Is it a change of type? Both types change, and they differ, or a list
 * turns from texts to numbers or back. Pure.
 *
 * @param array $from column: type, options
 * @param array $to   column: type, options
 * @return bool
 */
function ws_wtable_retype_is_change($from, $to)
{
    $types = ws_wtable_retype_types();
    $a = (string) ($from['type'] ?? '');
    $b = (string) ($to['type'] ?? '');

    if (!in_array($a, $types, true) || !in_array($b, $types, true)) {
        return false;
    }

    return ($a !== $b) || (($a === 'list') && (ws_wtable_list_of($from) !== ws_wtable_list_of($to)));
}

/**
 * How many minor units make one of a money column. Pure.
 *
 * @param array $column
 * @return int
 */
function ws_wtable_retype_unit($column)
{
    return (int) pow(10, isset($column['options']['decimals']) ? (int) $column['options']['decimals'] : 2);
}

/**
 * A number or an amount as a text cell keeps it: the site's decimal mark,
 * no thousands grouping, so a text turned back into a number reads the
 * same figure. Money keeps its decimals (12,50). Pure but for the site's
 * decimal mark.
 *
 * @param array     $from  the column the value is of
 * @param int|float $value
 * @return string
 */
function ws_wtable_retype_figure_text($from, $value)
{
    if ((string) ($from['type'] ?? '') === 'money') {
        $decimals = isset($from['options']['decimals']) ? (int) $from['options']['decimals'] : 2;

        return ws_wtable_screen_figure_text(ws_wtable_screen_money_text((int) $value, $decimals), false);
    }

    return ws_wtable_screen_figure_text(ws_wtable_screen_number_text($value), false);
}

/**
 * An amount as minor units of a money column, or decimals_lost when the
 * column keeps fewer decimals than the amount has. Pure.
 *
 * @param float $amount
 * @param array $to
 * @return array value, error
 */
function ws_wtable_retype_minor($amount, $to)
{
    $minor = round($amount * ws_wtable_retype_unit($to), 6);

    if (floor($minor) !== $minor) {
        return array('value' => null, 'error' => 'decimals_lost');
    }

    return array('value' => (int) $minor, 'error' => '');
}

/**
 * One kept cell turned into what the new type takes, before its check. An
 * empty cell (null, an unticked box, no item) stays empty. A text is read
 * the site's way, as the file import reads it: a number, an amount (to
 * minor units, rounded), a date (the time kept only by a column with a
 * time), a tick word or 1/0, one item a line. A number or an amount turns
 * into its text, its amount, its tick (1 and 0 only) or a list of it; a
 * ticked box into 1, one unit, "1" or [1]; a date into its text or a list
 * of it; a list of one item into what its item turns into, its items into
 * one a line of text. What has no conversion (a date into a number, a list
 * of several items into one value, an amount with more decimals than the
 * money column keeps) gives the error instead. Pure but for
 * the site's decimal mark.
 *
 * @param array $from  column: type, options
 * @param array $to    column: type, options
 * @param mixed $value as kept in the row's cells
 * @return array value, error ('' when converted)
 */
function ws_wtable_retype_candidate($from, $to, $value)
{
    $a = (string) ($from['type'] ?? '');
    $b = (string) ($to['type'] ?? '');
    $give = function ($kept) {
        return array('value' => $kept, 'error' => '');
    };
    $none = array('value' => null, 'error' => 'no_conversion');

    if (($value === null) || ($value === '') || ($value === false) || ($value === array())) {
        return $give(null);
    }

    // A list of one item converts as that item would; into a list, each item.
    if ($a === 'list') {
        $items = array_values(is_array($value) ? $value : array($value));
        $item_from = array('type' => ws_wtable_list_of($from), 'options' => array());

        if ($b === 'list') {
            $kept = array();

            foreach ($items as $item) {
                $converted = ws_wtable_retype_candidate($item_from, array('type' => ws_wtable_list_of($to), 'options' => array()), $item);

                if ($converted['error'] !== '') {
                    return $converted;
                }

                $kept[] = $converted['value'];
            }

            return $give($kept);
        }

        if ($b === 'text') {
            $lines = array();

            foreach ($items as $item) {
                $lines[] = (is_int($item) || is_float($item)) ? ws_wtable_retype_figure_text($item_from, $item) : (string) $item;
            }

            return $give(implode("\n", $lines));
        }

        if (count($items) !== 1) {
            return array('value' => null, 'error' => 'list_many');
        }

        return ws_wtable_retype_candidate($item_from, $to, $items[0]);
    }

    // An item of a list into the type it already is.
    if ($a === $b) {
        return $give($value);
    }

    switch ($b) {
        case 'text':
            if (($a === 'number') || ($a === 'money')) {
                return is_numeric($value) ? $give(ws_wtable_retype_figure_text($from, $value + 0)) : $none;
            }

            return $give(($a === 'checkbox') ? '1' : (is_scalar($value) ? (string) $value : ''));

        case 'number':
            if ($a === 'money') {
                return is_numeric($value) ? $give(round((int) $value / ws_wtable_retype_unit($from), 6)) : $none;
            }

            if ($a === 'checkbox') {
                return $give(1);
            }

            if ($a === 'text') {
                $number = ws_wtable_import_number((string) $value);

                return ($number === null) ? array('value' => (string) $value, 'error' => 'not_number') : $give($number);
            }

            return $none;

        case 'money':
            if ($a === 'number') {
                return is_numeric($value) ? ws_wtable_retype_minor((float) $value, $to) : $none;
            }

            if ($a === 'checkbox') {
                return $give(ws_wtable_retype_unit($to));
            }

            if ($a === 'text') {
                $money = ws_wtable_import_money((string) $value);

                return ($money === null) ? array('value' => null, 'error' => 'not_minor_units') : ws_wtable_retype_minor((float) $money['amount'], $to);
            }

            return $none;

        case 'date':
            if ($a !== 'text') {
                return $none;
            }

            $date = ws_wtable_import_date((string) $value);

            if ($date === null) {
                return array('value' => null, 'error' => 'not_date');
            }

            if (!empty($to['options']['time'])) {
                return $give($date['date'] . ' ' . (($date['time'] !== '') ? $date['time'] : '00:00'));
            }

            // A time other than midnight would be lost: the cell is listed.
            return (($date['time'] === '') || ($date['time'] === '00:00')) ? $give($date['date']) : array('value' => null, 'error' => 'time_lost');

        case 'checkbox':
            if ($a === 'text') {
                $tick = ws_wtable_import_checkbox((string) $value, true);

                return ($tick === null) ? array('value' => null, 'error' => 'not_checkbox') : $give($tick);
            }

            if (($a === 'number') || ($a === 'money')) {
                $one = ($a === 'money') ? ws_wtable_retype_unit($from) : 1;

                if ((float) $value === 0.0) {
                    return $give(false);
                }

                return ((float) $value === (float) $one) ? $give(true) : array('value' => null, 'error' => 'not_checkbox');
            }

            return $none;

        case 'list':
            $number_items = (ws_wtable_list_of($to) === 'number');

            if ($a === 'checkbox') {
                return $give(array($number_items ? 1 : '1'));
            }

            if (($a === 'number') || ($a === 'money')) {
                if (!is_numeric($value)) {
                    return $none;
                }

                if ($number_items) {
                    return $give(array(($a === 'money') ? round((int) $value / ws_wtable_retype_unit($from), 6) : $value + 0));
                }

                return $give(array(ws_wtable_retype_figure_text($from, $value + 0)));
            }

            if (($a === 'text') && $number_items) {
                $items = array();

                foreach (preg_split('/\r\n|\r|\n/', (string) $value) as $line) {
                    if (trim($line) === '') {
                        continue;
                    }

                    $number = ws_wtable_import_number($line);

                    if ($number === null) {
                        return array('value' => null, 'error' => 'not_number');
                    }

                    $items[] = $number;
                }

                return $give($items);
            }

            if ($a === 'date') {
                return $number_items ? array('value' => null, 'error' => 'not_number') : $give(array((string) $value));
            }

            return $give((string) $value);
    }

    return $none;
}

/**
 * One kept cell converted and checked as a write of the new type checks
 * it: the value to keep (null for an empty cell, false for an unticked
 * box) or the reason it does not convert; a number its column would round
 * is decimals_lost. Pure but for the site's decimal
 * mark.
 *
 * @param array $from
 * @param array $to
 * @param mixed $value
 * @return array ok, value, error
 */
function ws_wtable_retype_cell($from, $to, $value)
{
    $candidate = ws_wtable_retype_candidate($from, $to, $value);

    if ($candidate['error'] !== '') {
        return array('ok' => false, 'value' => null, 'error' => $candidate['error']);
    }

    $check = ws_wtable_cell_check($to, $candidate['value']);

    // A number column rounds to its decimals: a figure it would change is
    // listed, never rounded away.
    if ($check['ok'] && ((string) ($to['type'] ?? '') === 'number') && is_numeric($candidate['value'])
        && (abs((float) $candidate['value'] - (float) $check['value']) > 1e-9)) {
        return array('ok' => false, 'value' => null, 'error' => 'decimals_lost');
    }

    return $check['ok'] ? array('ok' => true, 'value' => $check['value'], 'error' => '')
        : array('ok' => false, 'value' => null, 'error' => $check['error']);
}

/**
 * A kept cell as the list of cells that do not convert shows it: a text,
 * a number in machine form, a list's items one a line; at most 80
 * characters. Pure.
 *
 * @param mixed $value
 * @return string
 */
function ws_wtable_retype_shown($value)
{
    if (is_bool($value)) {
        $text = $value ? '1' : '';
    } elseif (is_array($value)) {
        $text = isset($value['kind'], $value['id']) ? $value['kind'] . ':' . $value['id']
            : implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
    } else {
        $text = is_scalar($value) ? (string) $value : '';
    }

    $text = trim(preg_replace('/\s+/u', ' ', $text));

    return (ws_wtable_length($text) > 80) ? mb_substr($text, 0, 79, 'UTF-8') . '…' : $text;
}

/**
 * The cells of one column over a table's rows, converted to a new type:
 * each row whose kept cell changes with its new value (null when it
 * empties), and each cell that does not convert, in the order given. A row
 * is id, label (its # number, else its place), bin (true for a binned row)
 * and cells as kept. Pure but for the site's decimal mark.
 *
 * @param array   $from      the column as it is: id, type, options
 * @param array   $to        type, options
 * @param array[] $rows
 * @return array changed (key => value), faults (key, row_id, label, bin, value, error)
 */
function ws_wtable_retype_rows($from, $to, $rows)
{
    $id = (string) (int) ($from['id'] ?? 0);
    $changed = array();
    $faults = array();

    foreach ((array) $rows as $row) {
        $cells = (array) ($row['cells'] ?? array());

        if (!array_key_exists($id, $cells)) {
            continue;
        }

        $key = (!empty($row['bin']) ? 'bin:' : 'row:') . (int) $row['id'];
        $converted = ws_wtable_retype_cell($from, $to, $cells[$id]);

        if (!$converted['ok']) {
            $faults[] = array('key' => $key, 'row_id' => (int) $row['id'], 'label' => (int) ($row['label'] ?? 0), 'bin' => !empty($row['bin']),
                'value' => ws_wtable_retype_shown($cells[$id]), 'error' => $converted['error']);
            continue;
        }

        $kept = (($converted['value'] === false) || ($converted['value'] === null)) ? null : $converted['value'];

        if ($kept !== $cells[$id]) {
            $changed[$key] = $kept;
        }
    }

    return array('changed' => $changed, 'faults' => $faults);
}

/**
 * The column a type change asks for, checked: the new type among those
 * that change, its options as the type takes them; never the table's key
 * column (key_column), nor options that make it the key (key_on_retype).
 * Pure.
 *
 * @param array $column the column as it is
 * @param mixed $input  type, options
 * @return array ok, to (type, options), error, field, status
 */
function ws_wtable_retype_input_check($column, $input)
{
    $input = is_array($input) ? $input : array();
    $type = (isset($input['type']) && is_string($input['type'])) ? $input['type'] : '';

    if (!in_array((string) ($column['type'] ?? ''), ws_wtable_retype_types(), true)) {
        return ws_wtable_refusal('type_not_changeable', 'type');
    }

    if (!in_array($type, ws_wtable_retype_types(), true)) {
        return ws_wtable_refusal('type_unknown', 'type');
    }

    // The key column stays filled and unique in a type a key takes: its
    // type changes only once it is no longer the key.
    if (!empty($column['options']['key'])) {
        return ws_wtable_refusal('key_column', 'type');
    }

    // Nor does a type change make a key: that goes through the column form,
    // whose key check reads every cell and the table's other key.
    if (is_array($input['options'] ?? null) && !empty($input['options']['key'])) {
        return ws_wtable_refusal('key_on_retype', 'options');
    }

    $options = ws_wtable_column_options_check($type, $input['options'] ?? array());

    if (!$options['ok']) {
        return ws_wtable_refusal($options['error'], 'options');
    }

    $to = array('id' => (int) ($column['id'] ?? 0), 'type' => $type, 'options' => $options['value']);

    if (!ws_wtable_retype_is_change($column, $to)) {
        return ws_wtable_refusal('type_same', 'type');
    }

    return array('ok' => true, 'to' => $to, 'error' => '', 'field' => '', 'status' => 200);
}

/* ---------------------------------------------------------------------------
   Store
   --------------------------------------------------------------------------- */

/**
 * The rows of a table and of its bin as a type change converts them: id,
 * label, bin, cells as kept. $lock reads the live rows FOR UPDATE, inside
 * the change's transaction.
 *
 * @param int  $table_id
 * @param bool $lock
 * @return array[]
 */
function ws_wtable_retype_read($table_id, $lock = false)
{
    $number = ws_wtable_numbers_ready() ? ', number' : '';
    $rows = array();

    foreach ((array) db_items("SELECT id, position, cells" . $number . " FROM ws_wtable_rows WHERE table_id = '" . (int) $table_id . "' ORDER BY position, id" . ($lock ? ' FOR UPDATE' : '')) as $row) {
        $rows[] = array('id' => (int) $row['id'], 'label' => ws_wtable_row_label($row), 'bin' => false, 'cells' => ws_wtable_cells_decode($row['cells']));
    }

    if (function_exists('ws_wtable_bin_ready') && ws_wtable_bin_ready()) {
        foreach ((array) db_items("SELECT id, position, cells" . $number . " FROM ws_wtable_row_bin WHERE table_id = '" . (int) $table_id . "' ORDER BY id" . ($lock ? ' FOR UPDATE' : '')) as $row) {
            $rows[] = array('id' => (int) $row['id'], 'label' => ws_wtable_row_label($row), 'bin' => true, 'cells' => ws_wtable_cells_decode($row['cells']));
        }
    }

    return $rows;
}

/**
 * May the person change the column's type, and does every cell convert?
 * The panel and the API's dry run ask this alone. Refused with
 * cells_not_converted (422) and every such cell in faults while any is
 * left.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $column ws_wtable_column()
 * @param mixed      $input  type, options
 * @param array[]|null $rows ws_wtable_retype_read(), read here when null
 * @return array ok, to, changed, faults, error, field, status
 */
function ws_wtable_column_retype_check($viewer, $table, $column, $input, $rows = null)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($column)) {
        return ws_wtable_refusal('not_found', '', 404) + array('faults' => array());
    }

    $check = ws_wtable_retype_input_check($column, $input);

    if (!$check['ok']) {
        return $check + array('faults' => array());
    }

    $plan = ws_wtable_retype_rows($column, $check['to'], is_array($rows) ? $rows : ws_wtable_retype_read((int) $table['id']));

    if (!empty($plan['faults'])) {
        return ws_wtable_refusal('cells_not_converted', 'type') + array('faults' => $plan['faults']);
    }

    return $check + array('changed' => $plan['changed'], 'faults' => array());
}

/**
 * Change a column's type: in one transaction its rows and binned rows are
 * locked, converted and written, each converted row's revision moved on,
 * and the column given its new type and options. Refused, nothing changes.
 * No row event is sent: the cells keep their meaning in the new type.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $column
 * @param mixed      $input type, options
 * @return array ok, changed (count), faults, error, field, status
 */
function ws_wtable_column_retype($viewer, $table, $column, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($column)) {
        return ws_wtable_refusal('not_found', '', 404) + array('faults' => array());
    }

    $table_id = (int) $table['id'];
    $column_id = (string) (int) $column['id'];

    db("START TRANSACTION");

    // Read once, locked: a plain read later in the transaction could see
    // the snapshot of an earlier statement rather than the rows now held.
    $rows = ws_wtable_retype_read($table_id, true);
    $check = ws_wtable_column_retype_check($viewer, $table, $column, $input, $rows);

    if (!$check['ok']) {
        db("ROLLBACK");

        return $check;
    }

    $now = time();
    $user_id = (int) ($viewer['id'] ?? 0);
    $by_key = array();

    foreach ($rows as $row) {
        $by_key[(!empty($row['bin']) ? 'bin:' : 'row:') . $row['id']] = $row;
    }

    foreach ($check['changed'] as $key => $value) {
        if (!isset($by_key[$key])) {
            continue;
        }

        $cells = $by_key[$key]['cells'];

        if ($value === null) {
            unset($cells[$column_id]);
        } else {
            $cells[$column_id] = $value;
        }

        $json = e(ws_wtable_cells_encode($cells));

        if (!empty($by_key[$key]['bin'])) {
            $done = db("UPDATE ws_wtable_row_bin SET cells = '" . $json . "' WHERE id = '" . (int) $by_key[$key]['id'] . "' AND table_id = '" . $table_id . "'");
        } else {
            $done = db("UPDATE ws_wtable_rows SET cells = '" . $json . "', revision = revision + 1, updated_by = '" . $user_id . "', updated_at = '" . $now . "'
                WHERE id = '" . (int) $by_key[$key]['id'] . "' AND table_id = '" . $table_id . "'");
        }

        if ($done === false) {
            db("ROLLBACK");

            return ws_wtable_refusal('not_saved', '', 500) + array('faults' => array());
        }
    }

    $done = db("UPDATE ws_wtable_columns SET type = '" . e($check['to']['type']) . "', options = '" . e(ws_wtable_options_encode($check['to']['options'])) . "'
        WHERE id = '" . (int) $column['id'] . "' AND table_id = '" . $table_id . "'");

    if ($done === false) {
        db("ROLLBACK");

        return ws_wtable_refusal('not_saved', '', 500) + array('faults' => array());
    }

    db("COMMIT");

    ws_wtable_columns_changed($table_id);

    return array('ok' => true, 'changed' => count($check['changed']), 'faults' => array(), 'error' => '', 'field' => '', 'status' => 200);
}
