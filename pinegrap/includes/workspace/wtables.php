<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - workflow tables. A person keeps tables of typed columns (text,
 * number, money, date, person, status, checkbox, record link, formula) whose
 * rows feed their flows. A table is its owner's alone, like a note: an
 * administrator reaches it only through a share.
 *
 * This part holds what needs no database: the column types, the options
 * each type takes, the check of one cell against its column and of a row's
 * cells, the limits, and how a row's cells are written as JSON. Every write
 * of a table goes through these checks, from the panel and the external API
 * alike, so that both refuse the same values with the same codes.
 *
 * Cells are kept by column id in one JSON object per row; filtering and
 * sorting are done in PHP, since MariaDB and older MySQL read JSON
 * differently. Money is an integer in minor units, never a float. A formula
 * column holds no cells: its value is worked out when the row is read.
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
 * The limits of the tables: columns per table, rows per table, tables per
 * owner, and the lengths of names, text cells, status choices and formulas.
 *
 * @return array
 */
function ws_wtable_limits()
{
    return array(
        'columns' => 50,
        'rows' => 5000,
        'tables' => 500,
        'name' => 120,
        'text' => 10000,
        'statuses' => 50,
        'status_label' => 60,
        'formula' => 1000,
        'link_kinds' => 20,
    );
}

/**
 * Whether one more of a thing fits: a column into a table holding $count,
 * a row, or a table of one owner.
 *
 * @param string $what  columns, rows or tables
 * @param int    $count how many there are now
 * @return bool
 */
function ws_wtable_limit_allows($what, $count)
{
    $limits = ws_wtable_limits();

    if (!in_array($what, array('columns', 'rows', 'tables'), true)) {
        return false;
    }

    return ((int) $count < $limits[$what]);
}

/**
 * The column types, each with its name as shown.
 *
 * @return array type => label
 */
function ws_wtable_column_types()
{
    return array(
        'text' => lang('Text'),
        'number' => lang('Number'),
        'money' => lang('Money'),
        'date' => lang('Date'),
        'person' => lang('Person'),
        'status' => lang('Status'),
        'checkbox' => lang('Checkbox'),
        'link' => lang('Record link'),
        'formula' => lang('Formula'),
    );
}

/**
 * Whether a column type keeps cells; a formula's value is worked out instead.
 *
 * @param string $type
 * @return bool
 */
function ws_wtable_type_stores($type)
{
    return isset(ws_wtable_column_types()[$type]) && ($type !== 'formula');
}

/**
 * The name of a table or a column, trimmed, or the reason it is refused.
 *
 * @param mixed $name
 * @return array ok, value, error (name_empty, name_too_long)
 */
function ws_wtable_name_check($name)
{
    $name = is_scalar($name) ? trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $name)) : '';

    if ($name === '') {
        return array('ok' => false, 'value' => '', 'error' => 'name_empty');
    }

    if (ws_wtable_length($name) > ws_wtable_limits()['name']) {
        return array('ok' => false, 'value' => '', 'error' => 'name_too_long');
    }

    return array('ok' => true, 'value' => $name, 'error' => '');
}

/** Characters in a string, without mbstring where the host lacks it. */
function ws_wtable_length($text)
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : (int) preg_match_all('/./us', $text);
}

/**
 * The options of a column of a type, checked and given their defaults.
 *
 * number: decimals (0-6, default 0). money: currency (three capitals, ''
 * for the site's) and decimals (0-3, default 2; how many minor units make
 * one). date: time (bool) to keep a time of day too. status: choices, a
 * list of key, label and colour (1-50, keys unique). link: kinds, the
 * record kinds a cell may point at (empty for any). formula: expression,
 * without the leading =. text, person and checkbox take none.
 *
 * @param string $type
 * @param mixed  $options
 * @return array ok, value (the options), error
 */
function ws_wtable_column_options_check($type, $options)
{
    $options = is_array($options) ? $options : array();
    $limits = ws_wtable_limits();

    if (!isset(ws_wtable_column_types()[$type])) {
        return array('ok' => false, 'value' => array(), 'error' => 'type_unknown');
    }

    $fail = function ($error) {
        return array('ok' => false, 'value' => array(), 'error' => $error);
    };

    $value = array();

    switch ($type) {
        case 'number':
        case 'money':
            $most = ($type === 'money') ? 3 : 6;
            $decimals = array_key_exists('decimals', $options) ? $options['decimals'] : (($type === 'money') ? 2 : 0);

            if (!ws_wtable_is_whole($decimals) || ((int) $decimals < 0) || ((int) $decimals > $most)) {
                return $fail('decimals_invalid');
            }

            $value['decimals'] = (int) $decimals;

            if ($type === 'money') {
                $currency = isset($options['currency']) ? strtoupper(trim((string) $options['currency'])) : '';

                if (($currency !== '') && !preg_match('/^[A-Z]{3}$/', $currency)) {
                    return $fail('currency_invalid');
                }

                $value['currency'] = $currency;
            }

            break;

        case 'date':
            $value['time'] = !empty($options['time']);
            break;

        case 'status':
            $choices = isset($options['choices']) ? $options['choices'] : array();

            if (!is_array($choices) || empty($choices)) {
                return $fail('choices_empty');
            }

            if (count($choices) > $limits['statuses']) {
                return $fail('choices_too_many');
            }

            $value['choices'] = array();
            $keys = array();

            foreach (array_values($choices) as $choice) {
                $key = (is_array($choice) && isset($choice['key']) && is_scalar($choice['key'])) ? (string) $choice['key'] : '';
                $label = (is_array($choice) && isset($choice['label']) && is_scalar($choice['label'])) ? trim((string) $choice['label']) : '';
                $color = (is_array($choice) && isset($choice['color']) && is_scalar($choice['color'])) ? (string) $choice['color'] : '';

                if (!preg_match('/^[a-z0-9_-]{1,32}$/', $key) || isset($keys[$key])) {
                    return $fail('choice_key_invalid');
                }

                if (($label === '') || (ws_wtable_length($label) > $limits['status_label'])) {
                    return $fail('choice_label_invalid');
                }

                if (($color !== '') && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    return $fail('choice_color_invalid');
                }

                $keys[$key] = true;
                $value['choices'][] = array('key' => $key, 'label' => $label, 'color' => strtolower($color));
            }

            break;

        case 'link':
            $kinds = isset($options['kinds']) ? $options['kinds'] : array();

            if (!is_array($kinds) || (count($kinds) > $limits['link_kinds'])) {
                return $fail('kinds_invalid');
            }

            $value['kinds'] = array();

            foreach ($kinds as $kind) {
                if (!is_string($kind) || !preg_match('/^[a-z_]{1,32}$/', $kind)) {
                    return $fail('kinds_invalid');
                }

                $value['kinds'][$kind] = $kind;
            }

            $value['kinds'] = array_values($value['kinds']);
            break;

        case 'formula':
            $expression = isset($options['expression']) && is_scalar($options['expression']) ? trim((string) $options['expression']) : '';
            $expression = ltrim($expression, '=');

            if ($expression === '') {
                return $fail('formula_empty');
            }

            if (ws_wtable_length($expression) > $limits['formula']) {
                return $fail('formula_too_long');
            }

            $value['expression'] = $expression;
            break;
    }

    return array('ok' => true, 'value' => $value, 'error' => '');
}

/** Whether a value is a whole number: an int, or a string or float of one. */
function ws_wtable_is_whole($value)
{
    if (is_int($value)) {
        return true;
    }

    if (is_string($value)) {
        return (bool) preg_match('/^-?[0-9]{1,18}$/', trim($value));
    }

    return is_float($value) && is_finite($value) && (floor($value) === $value) && (abs($value) < 1e18);
}

/**
 * One cell checked against its column, and the value to keep.
 *
 * An empty value (null, '') empties the cell, and keeps null; a checkbox
 * keeps false instead. A number is rounded to the column's decimals; money
 * is a whole number of minor units, refused when it has a fraction rather
 * than cut; a date is YYYY-MM-DD, with HH:MM after a space when the column
 * keeps a time; a person is a user id, whether they are on the team is
 * the store's check; a status is one of the column's keys; a record link
 * is a kind and an id, the kind among the column's kinds when it names any.
 *
 * @param array $column type and options (checked)
 * @param mixed $value
 * @return array ok, value, error
 */
function ws_wtable_cell_check($column, $value)
{
    $type = isset($column['type']) ? (string) $column['type'] : '';
    $options = (isset($column['options']) && is_array($column['options'])) ? $column['options'] : array();
    $limits = ws_wtable_limits();

    $ok = function ($kept) {
        return array('ok' => true, 'value' => $kept, 'error' => '');
    };

    $fail = function ($error) {
        return array('ok' => false, 'value' => null, 'error' => $error);
    };

    if (!isset(ws_wtable_column_types()[$type])) {
        return $fail('type_unknown');
    }

    if ($type === 'formula') {
        return $fail('computed');
    }

    if (($value === null) || ($value === '')) {
        return $ok(($type === 'checkbox') ? false : null);
    }

    switch ($type) {
        case 'text':
            if (!is_scalar($value) || is_bool($value)) {
                return $fail('not_text');
            }

            $value = (string) $value;

            if (ws_wtable_length($value) > $limits['text']) {
                return $fail('too_long');
            }

            return $ok($value);

        case 'number':
            if (is_string($value)) {
                $value = trim($value);

                if (!preg_match('/^-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][-+]?[0-9]+)?$/', $value)) {
                    return $fail('not_number');
                }

                $value = (float) $value;
            } elseif (is_int($value)) {
                $value = (float) $value;
            } elseif (!is_float($value)) {
                return $fail('not_number');
            }

            if (!is_finite($value) || (abs($value) >= 1e15)) {
                return $fail('out_of_range');
            }

            $decimals = isset($options['decimals']) ? (int) $options['decimals'] : 0;
            $value = round($value, $decimals);

            return $ok(($decimals === 0) ? (int) $value : $value);

        case 'money':
            if (!ws_wtable_is_whole($value)) {
                return $fail('not_minor_units');
            }

            if (is_float($value) && (abs($value) >= 1e15)) {
                return $fail('out_of_range');
            }

            $value = is_string($value) ? trim($value) : $value;

            if (is_string($value) && (strlen(ltrim($value, '-')) > 15)) {
                return $fail('out_of_range');
            }

            $value = (int) $value;

            if (abs($value) >= 1000000000000000) {
                return $fail('out_of_range');
            }

            return $ok($value);

        case 'date':
            if (!is_string($value)) {
                return $fail('not_date');
            }

            $value = trim($value);
            $time = !empty($options['time']);
            $pattern = $time ? '/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?$/' : '/^(\d{4})-(\d{2})-(\d{2})$/';

            if (!preg_match($pattern, $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                return $fail('not_date');
            }

            if (isset($parts[4]) && (((int) $parts[4] > 23) || ((int) $parts[5] > 59))) {
                return $fail('not_date');
            }

            return $ok($value);

        case 'person':
            if (!ws_wtable_is_whole($value) || ((int) $value <= 0)) {
                return $fail('not_person');
            }

            return $ok((int) $value);

        case 'status':
            if (!is_scalar($value) || is_bool($value)) {
                return $fail('not_status');
            }

            foreach ((isset($options['choices']) ? (array) $options['choices'] : array()) as $choice) {
                if (isset($choice['key']) && ((string) $choice['key'] === (string) $value)) {
                    return $ok((string) $value);
                }
            }

            return $fail('not_status');

        case 'checkbox':
            if (is_bool($value)) {
                return $ok($value);
            }

            $map = array('1' => true, '0' => false, 'true' => true, 'false' => false);
            $key = is_scalar($value) ? strtolower(trim((string) $value)) : null;

            if (($key === null) || !isset($map[$key])) {
                return $fail('not_checkbox');
            }

            return $ok($map[$key]);

        case 'link':
            if (!is_array($value) || !isset($value['kind'], $value['id'])) {
                return $fail('not_link');
            }

            $kind = is_string($value['kind']) ? $value['kind'] : '';

            if (!preg_match('/^[a-z_]{1,32}$/', $kind) || !ws_wtable_is_whole($value['id']) || ((int) $value['id'] <= 0)) {
                return $fail('not_link');
            }

            if (!empty($options['kinds']) && !in_array($kind, (array) $options['kinds'], true)) {
                return $fail('link_kind_refused');
            }

            return $ok(array('kind' => $kind, 'id' => (int) $value['id']));
    }

    return $fail('type_unknown');
}

/**
 * A row's cells checked against the table's columns.
 *
 * $cells maps column id to value; a column left out keeps what the row has.
 * A column the table does not have, or a formula column, is refused. The
 * answer's errors map each refused column id to its code; the cells to keep
 * are given only when none is refused, empty cells left out.
 *
 * @param array $columns column id => column (type, options)
 * @param mixed $cells
 * @return array ok, cells (column id => value), errors (column id => code)
 */
function ws_wtable_row_cells_check($columns, $cells)
{
    if (!is_array($cells)) {
        return array('ok' => false, 'cells' => array(), 'errors' => array('' => 'not_cells'));
    }

    $kept = array();
    $errors = array();

    foreach ($cells as $id => $value) {
        $id = (string) $id;

        if (!isset($columns[$id])) {
            $errors[$id] = 'unknown_column';
            continue;
        }

        $check = ws_wtable_cell_check($columns[$id], $value);

        if (!$check['ok']) {
            $errors[$id] = $check['error'];
            continue;
        }

        $kept[$id] = $check['value'];
    }

    if (!empty($errors)) {
        return array('ok' => false, 'cells' => array(), 'errors' => $errors);
    }

    return array('ok' => true, 'cells' => $kept, 'errors' => array());
}

/**
 * A row's cells after a change: the old cells with the checked new ones
 * laid over them; an emptied cell (null, an unticked checkbox) is dropped,
 * and so is a column the table no longer has.
 *
 * @param array $columns column id => column
 * @param array $old     column id => value
 * @param array $new     column id => checked value
 * @return array
 */
function ws_wtable_cells_merge($columns, $old, $new)
{
    $cells = array();

    foreach ((array) $old as $id => $value) {
        $cells[(string) $id] = $value;
    }

    foreach ((array) $new as $id => $value) {
        $cells[(string) $id] = $value;
    }

    foreach ($cells as $id => $value) {
        if (!isset($columns[$id]) || !ws_wtable_type_stores($columns[$id]['type']) || ($value === null) || ($value === false)) {
            unset($cells[$id]);
        }
    }

    ksort($cells, SORT_STRING);

    return $cells;
}

/**
 * A row's cells as kept in its MEDIUMTEXT column: a JSON object by column
 * id, '{}' when empty.
 *
 * @param array $cells
 * @return string
 */
function ws_wtable_cells_encode($cells)
{
    $json = json_encode((object) $cells, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

    return ($json === false) ? '{}' : $json;
}

/**
 * A row's cells read back; a value that is not a JSON object reads as no
 * cells rather than failing the table.
 *
 * @param mixed $json
 * @return array column id => value
 */
function ws_wtable_cells_decode($json)
{
    $cells = is_string($json) ? json_decode($json, true) : null;

    if (!is_array($cells)) {
        return array();
    }

    $out = array();

    foreach ($cells as $id => $value) {
        $out[(string) $id] = $value;
    }

    return $out;
}
