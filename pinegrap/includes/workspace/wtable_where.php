<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - conditions over the rows of a workflow table, as SQL's WHERE,
 * ORDER BY and its totals read them: which rows meet a list of conditions
 * joined by all or any, the rows sorted by a column, and the sum, average,
 * smallest and largest value of each number column over them. Each
 * condition is a column, a comparison and a value, compared by the column's
 * type: a number, money (in minor units) and a formula by size, a date as
 * its ISO text, a status by its key, a checkbox as 1 or 0, a person and a
 * record link by their numbers, a text without case (ws_fold()).
 *
 * The cells are JSON per row, so all of it is worked out in PHP over the
 * rows a read gives (ws_wtable_rows(), formulas worked out); the table's
 * limits bound that read. Nothing here reads the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most conditions one list holds.
define('WS_WTABLE_WHERE_MAX', 10);

/**
 * The comparisons a condition takes, each with its words.
 *
 * @return array op => label
 */
function ws_wtable_where_operators()
{
    return array(
        'eq'        => lang('is'),
        'neq'       => lang('is not'),
        'lt'        => lang('is less than'),
        'gt'        => lang('is more than'),
        'lte'       => lang('is at most'),
        'gte'       => lang('is at least'),
        'contains'  => lang('contains'),
        'not_contains' => lang('does not contain'),
        'starts'    => lang('starts with'),
        'empty'     => lang('is empty'),
        'not_empty' => lang('is not empty'),
    );
}

/**
 * The comparisons a column of this type takes: sizes for a number, money, a
 * formula and a date; words for a text; what a status, a person and a record
 * link hold; a checkbox ticked or not; an item a list holds or not. None
 * for a type it does not know.
 * Pure.
 *
 * @param string $type ws_wtable_column_types()
 * @return string[]
 */
function ws_wtable_where_type_operators($type)
{
    $sizes = array('eq', 'neq', 'lt', 'gt', 'lte', 'gte', 'empty', 'not_empty');

    switch ((string) $type) {
        case 'text':
            return array('eq', 'neq', 'contains', 'starts', 'empty', 'not_empty');

        case 'number':
        case 'money':
        case 'formula':
        case 'date':
            return $sizes;

        case 'status':
        case 'person':
        case 'link':
            return array('eq', 'neq', 'empty', 'not_empty');

        case 'checkbox':
            return array('eq', 'neq');

        case 'list':
            return array('contains', 'not_contains', 'empty', 'not_empty');
    }

    return array();
}

/**
 * Does a comparison take a value? Empty and not empty look at the cell
 * alone. Pure.
 *
 * @param string $op
 * @return bool
 */
function ws_wtable_where_takes_value($op)
{
    return !in_array((string) $op, array('empty', 'not_empty'), true);
}

/**
 * How conditions join: every one holds, or any one does. The first is the
 * one an unknown word means.
 *
 * @return string[]
 */
function ws_wtable_where_joins()
{
    return array('all', 'any');
}

/**
 * The ways rows are sorted, the first the one an unknown word means.
 *
 * @return string[]
 */
function ws_wtable_sort_directions()
{
    return array('asc', 'desc');
}

/**
 * Is a column of this type totalled (sum, average, smallest, largest)? A
 * number, money and a formula, whose value is always a number. Pure.
 *
 * @param string $type
 * @return bool
 */
function ws_wtable_total_type($type)
{
    return in_array((string) $type, array('number', 'money', 'formula'), true);
}

/**
 * A list of conditions, given as a list or as its JSON text: each its
 * column (a whole number, 0 when it names none), its comparison ('' for
 * one it does not know) and its value as text (a number written as a
 * machine figure, true and false as 1 and 0, anything else ''). An empty
 * text is no condition; anything but a list of objects is null. Pure.
 *
 * @param mixed $where
 * @return array[]|null column, op, value
 */
function ws_wtable_where_list($where)
{
    if (is_string($where)) {
        if (trim($where) === '') {
            return array();
        }

        $where = json_decode($where, true);
    }

    if (!is_array($where) || (!empty($where) && (array_keys($where) !== range(0, count($where) - 1)))) {
        return null;
    }

    $out = array();

    foreach ($where as $item) {
        // Each condition is an object: a list in its place is no condition.
        if (!is_array($item) || (!empty($item) && (array_keys($item) === range(0, count($item) - 1)))) {
            return null;
        }

        $column = $item['column'] ?? 0;
        $op = $item['op'] ?? '';
        $value = $item['value'] ?? '';

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $out[] = array(
            'column' => (ws_wtable_is_whole($column) && ((int) $column > 0)) ? (int) $column : 0,
            'op'     => (is_string($op) && isset(ws_wtable_where_operators()[$op])) ? $op : '',
            'value'  => (is_scalar($value) && !is_bool($value)) ? (string) $value : '',
        );
    }

    return $out;
}

/**
 * What is wrong with a value typed into a condition on this column: ''
 * when it fits; operator for a comparison the column's type does not take;
 * empty_value when a comparison that takes a value has none; not_number (a
 * number or a formula takes a machine figure, 12.5), not_minor_units
 * (money is a whole number of minor units, 1250 for 12.50), not_date
 * (YYYY-MM-DD, or YYYY-MM-DD HH:MM), not_status (a status by one of its
 * keys), not_checkbox (1 or 0), not_whole (a person or a record by its
 * number); a list of numbers takes a machine figure too. A text and a list
 * of texts take any value. Pure.
 *
 * @param array  $column type, options
 * @param string $op
 * @param string $value
 * @return string
 */
function ws_wtable_where_value_fault($column, $op, $value)
{
    $type = (string) ($column['type'] ?? '');

    if (!in_array((string) $op, ws_wtable_where_type_operators($type), true)) {
        return 'operator';
    }

    if (!ws_wtable_where_takes_value($op)) {
        return '';
    }

    $value = trim((string) $value);

    if ($value === '') {
        return 'empty_value';
    }

    if (ws_wtable_where_value($column, $value) !== null) {
        return (($type === 'money') && !preg_match('/^-?\d{1,15}$/', $value)) ? 'not_minor_units' : '';
    }

    if ($type === 'list') {
        return 'not_number';
    }

    $faults = array('number' => 'not_number', 'money' => 'not_number', 'formula' => 'not_number', 'date' => 'not_date',
        'status' => 'not_status', 'checkbox' => 'not_checkbox', 'person' => 'not_whole', 'link' => 'not_whole');

    return $faults[$type] ?? '';
}

/**
 * A cell as a condition and a sort compare it, by its column's type: a
 * number (int or float) for a number, money and a formula; the ISO text of
 * a date; the folded text (ws_fold()) of a text; a status's key; 1 or 0 for
 * a checkbox; a person's or a record's number; a list's items each as a
 * text or a number of its kind gives them. null for an empty cell (a
 * checkbox is never empty: unticked is 0; a list without items is empty).
 * Pure.
 *
 * @param array $column type
 * @param mixed $cell   as a read row holds it (ws_wtable_row_out())
 * @return int|float|string|array|null
 */
function ws_wtable_where_cell($column, $cell)
{
    $type = (string) ($column['type'] ?? '');

    if ($type === 'checkbox') {
        return (($cell === true) || ($cell === 1) || ($cell === '1')) ? 1 : 0;
    }

    if (($cell === null) || ($cell === '') || ($cell === false)) {
        return null;
    }

    switch ($type) {
        case 'number':
        case 'money':
        case 'formula':
            return (is_int($cell) || is_float($cell)) ? $cell : (is_numeric($cell) ? (float) $cell : null);

        case 'text':
            $text = is_scalar($cell) ? trim((string) $cell) : '';

            return ($text === '') ? null : ws_fold($text);

        case 'date':
        case 'status':
            return is_string($cell) ? trim($cell) : null;

        case 'person':
            return ws_wtable_is_whole($cell) ? (int) $cell : null;

        case 'link':
            return (is_array($cell) && isset($cell['id']) && ws_wtable_is_whole($cell['id'])) ? (int) $cell['id'] : null;

        case 'list':
            $item_column = array('type' => ws_wtable_list_of($column));
            $items = array();

            foreach ((is_array($cell) ? $cell : array($cell)) as $item) {
                $item = is_scalar($item) ? ws_wtable_where_cell($item_column, $item) : null;

                if ($item !== null) {
                    $items[] = $item;
                }
            }

            return empty($items) ? null : $items;
    }

    return null;
}

/**
 * A condition's value as ws_wtable_where_cell() gives a cell of its column,
 * or null when it does not fit the column's type: a machine figure for a
 * number, money and a formula; YYYY-MM-DD or YYYY-MM-DD HH:MM for a date; 1
 * or 0 for a checkbox (an empty value is 0, as a read row gives an unticked
 * box); a whole number for a person or a record; an item of a list as its
 * kind (a text or a number) takes it. A text is folded, a status kept as
 * written (its key). Pure.
 *
 * @param array  $column type, options
 * @param string $value
 * @return int|float|string|null
 */
function ws_wtable_where_value($column, $value)
{
    $value = trim((string) $value);

    switch ((string) ($column['type'] ?? '')) {
        case 'list':
            return ws_wtable_where_value(array('type' => ws_wtable_list_of($column)), $value);

        case 'number':
        case 'money':
        case 'formula':
            if (!preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $value)) {
                return null;
            }

            return preg_match('/^-?\d{1,15}$/', $value) ? (int) $value : (float) $value;

        case 'date':
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?$/', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                return null;
            }

            return (isset($parts[4]) && (((int) $parts[4] > 23) || ((int) $parts[5] > 59))) ? null : $value;

        case 'checkbox':
            $map = array('1' => 1, 'true' => 1, '0' => 0, 'false' => 0, '' => 0);

            return $map[strtolower($value)] ?? null;

        case 'status':
            $keys = array_column((array) ($column['options']['choices'] ?? array()), 'key');

            return in_array($value, array_map('strval', $keys), true) ? $value : null;

        case 'person':
        case 'link':
            return preg_match('/^[1-9]\d{0,17}$/', $value) ? (int) $value : null;

        case 'text':
            return ws_fold($value);
    }

    return null;
}

/**
 * Does one condition hold for a cell of its column? Empty and not empty
 * look at the cell; the other comparisons hold only with a value that fits
 * the column (ws_wtable_where_value()). A size never holds for an empty
 * cell, and "is not" holds for one. A date is compared at the coarser of
 * the two: 2026-11-09 takes the whole day of a cell that keeps a time.
 * Pure.
 *
 * @param array  $column type, options
 * @param mixed  $cell
 * @param string $op
 * @param string $value the value, filled in
 * @return bool
 */
function ws_wtable_where_holds($column, $cell, $op, $value)
{
    $type = (string) ($column['type'] ?? '');
    $op = (string) $op;

    if (!in_array($op, ws_wtable_where_type_operators($type), true)) {
        return false;
    }

    $here = ws_wtable_where_cell($column, $cell);

    if ($op === 'empty') {
        return $here === null;
    }

    if ($op === 'not_empty') {
        return $here !== null;
    }

    // A value that does not fit the column holds for no row, "is not"
    // included: "Stock is not five" says nothing of a stock.
    $wanted = ws_wtable_where_value($column, $value);

    if ($wanted === null) {
        return false;
    }

    // A list holds an item when one of them is the value: a text without
    // case, a number by size. An empty list holds none.
    if ($type === 'list') {
        $of = ws_wtable_list_of($column);
        $found = false;

        foreach ((array) $here as $item) {
            if (($of === 'number') ? (ws_wtable_where_order('number', $item, $wanted) === 0) : ((string) $item === (string) $wanted)) {
                $found = true;
                break;
            }
        }

        return ($op === 'contains') ? $found : (($op === 'not_contains') ? !$found : false);
    }

    // A text equal to nothing is an empty one.
    if ($type === 'text') {
        $here = ($here === null) ? '' : (string) $here;

        switch ($op) {
            case 'eq':
                return $here === $wanted;

            case 'neq':
                return $here !== $wanted;

            case 'contains':
                return ($wanted !== '') && (mb_strpos($here, (string) $wanted) !== false);

            case 'starts':
                return ($wanted !== '') && (mb_strpos($here, (string) $wanted) === 0);
        }

        return false;
    }

    $order = ws_wtable_where_order($type, $here, $wanted);

    if ($op === 'neq') {
        return $order !== 0;
    }

    if ($order === null) {
        return false;
    }

    switch ($op) {
        case 'eq':
            return $order === 0;

        case 'lt':
            return $order < 0;

        case 'gt':
            return $order > 0;

        case 'lte':
            return $order <= 0;

        case 'gte':
            return $order >= 0;
    }

    return false;
}

/**
 * How a cell stands to a value of the same type, as ws_wtable_where_cell()
 * and ws_wtable_where_value() give them: below 0, 0 or above; null for an
 * empty cell. Numbers by size (to ten places, as formulas are kept), dates
 * as ISO text at the coarser of the two, the rest equal or not. Pure.
 *
 * @param string               $type
 * @param int|float|string|null $here
 * @param int|float|string     $wanted
 * @return int|null
 */
function ws_wtable_where_order($type, $here, $wanted)
{
    if ($here === null) {
        return null;
    }

    if (in_array($type, array('number', 'money', 'formula'), true)) {
        $a = round((float) $here, 10);
        $b = round((float) $wanted, 10);

        return ($a < $b) ? -1 : (($a > $b) ? 1 : 0);
    }

    if ($type === 'date') {
        $length = min(strlen((string) $here), strlen((string) $wanted));

        return strcmp(substr((string) $here, 0, $length), substr((string) $wanted, 0, $length)) <=> 0;
    }

    return ((string) $here === (string) $wanted) ? 0 : 1;
}

/**
 * The rows that meet conditions, in the order given: every condition holds
 * (all), or one of them does (any); none keeps every row. A condition on a
 * column the table does not have holds for no row. Pure.
 *
 * @param array   $columns    column id => column (ws_wtable_columns())
 * @param array[] $rows       as read (ws_wtable_row_out()): cells by column id
 * @param array[] $conditions ws_wtable_where_list(), the values filled in
 * @param string  $join       ws_wtable_where_joins()
 * @return array[]
 */
function ws_wtable_rows_where($columns, $rows, $conditions, $join = 'all')
{
    $conditions = array_values((array) $conditions);

    if (empty($conditions)) {
        return array_values((array) $rows);
    }

    $any = ((string) $join === 'any');
    $out = array();

    foreach ((array) $rows as $row) {
        $cells = (array) ($row['cells'] ?? array());
        $met = !$any;

        foreach ($conditions as $condition) {
            $id = (string) (int) ($condition['column'] ?? 0);
            $holds = isset($columns[$id]) && ws_wtable_where_holds($columns[$id], $cells[$id] ?? null, (string) ($condition['op'] ?? ''), (string) ($condition['value'] ?? ''));

            if ($holds === $any) {
                $met = $any;
                break;
            }
        }

        if ($met) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * Rows sorted by a column (asc or desc), its empty cells last either way,
 * rows alike in the order given; by no column (0, or one the table does
 * not have), the order given, the last row first when descending. A number
 * by size, a date by its ISO text, a text without case and marks
 * (Şeker by S), a status in the order of its choices, a checkbox unticked
 * first, a person or a record by its number, a list by how many items it
 * holds. Pure.
 *
 * @param array   $columns   column id => column
 * @param array[] $rows      as read, in the table's order
 * @param int     $column_id
 * @param string  $dir       ws_wtable_sort_directions()
 * @return array[]
 */
function ws_wtable_rows_sorted($columns, $rows, $column_id, $dir = 'asc')
{
    $rows = array_values((array) $rows);
    $down = ((string) $dir === 'desc');
    $id = (string) (int) $column_id;

    if (!isset($columns[$id])) {
        return $down ? array_reverse($rows) : $rows;
    }

    $column = $columns[$id];
    $keys = array();

    foreach ($rows as $place => $row) {
        $keys[$place] = ws_wtable_sort_key($column, ((array) ($row['cells'] ?? array()))[$id] ?? null);
    }

    $places = array_keys($rows);

    // Decorated with their places, so rows alike keep their order on any
    // PHP (usort() is stable only from PHP 8).
    usort($places, function ($a, $b) use ($keys, $down) {
        if (($keys[$a] === null) || ($keys[$b] === null)) {
            $order = ($keys[$a] === null) <=> ($keys[$b] === null);
        } else {
            $order = is_string($keys[$a]) ? strnatcmp($keys[$a], (string) $keys[$b]) : ($keys[$a] <=> $keys[$b]);
            $order = $down ? -$order : $order;
        }

        return ($order !== 0) ? $order : ($a <=> $b);
    });

    return array_map(function ($place) use ($rows) {
        return $rows[$place];
    }, $places);
}

/**
 * A cell as rows are sorted by it, null when empty: the size of a number;
 * a date's ISO text; a text folded and without marks; a status's place
 * among its choices (a key it no longer offers after them); a checkbox 0
 * or 1; a person's or a record's number (a record by its kind first); how
 * many items a list holds. Pure.
 *
 * @param array $column
 * @param mixed $cell
 * @return int|float|string|null
 */
function ws_wtable_sort_key($column, $cell)
{
    $type = (string) ($column['type'] ?? '');
    $key = ws_wtable_where_cell($column, $cell);

    if ($key === null) {
        return null;
    }

    // Without marks first, so Şeker sorts among the S; the folded text then
    // keeps Şeker and Seker apart.
    if ($type === 'text') {
        return pg_transliterate_to_ascii((string) $key) . "\x01" . $key;
    }

    if ($type === 'list') {
        return count((array) $key);
    }

    if ($type === 'status') {
        $place = array_search($key, array_map('strval', array_column((array) ($column['options']['choices'] ?? array()), 'key')), true);

        return ($place === false) ? 1000 : (int) $place;
    }

    if ($type === 'link') {
        return sprintf('%s:%020d', (string) ($cell['kind'] ?? ''), (int) $key);
    }

    return $key;
}

/**
 * The sum, the average, the smallest and the largest value of each number,
 * money and formula column over rows, and how many values they read. An
 * empty cell (a formula that could not be worked out) is left out; no
 * value sums to nought and has no average, smallest or largest. Money
 * stays in minor units, its average rounded to a whole one with round();
 * a sum or an average with decimals is kept to ten places, as formulas are.
 * Pure.
 *
 * @param array   $columns column id => column
 * @param array[] $rows    as read: cells by column id
 * @return array column id => sum, avg, min, max, count
 */
function ws_wtable_rows_totals($columns, $rows)
{
    $out = array();

    foreach ((array) $columns as $id => $column) {
        if (!ws_wtable_total_type($column['type'] ?? '')) {
            continue;
        }

        $values = array();

        foreach ((array) $rows as $row) {
            $value = ws_wtable_where_cell($column, ((array) ($row['cells'] ?? array()))[(string) $id] ?? null);

            if ($value !== null) {
                $values[] = $value;
            }
        }

        $count = count($values);
        $sum = array_sum($values);
        $sum = is_float($sum) ? round($sum, 10) : $sum;
        $average = null;

        if ($count > 0) {
            $average = ((string) $column['type'] === 'money') ? (int) round($sum / $count) : round($sum / $count, 10);
        }

        $out[(string) $id] = array(
            'sum'   => $sum,
            'avg'   => $average,
            'min'   => ($count > 0) ? min($values) : null,
            'max'   => ($count > 0) ? max($values) : null,
            'count' => $count,
        );
    }

    return $out;
}
